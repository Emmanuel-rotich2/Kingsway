<?php

namespace App\API\Modules\parent;

use App\API\Includes\BaseAPI;
use App\API\Services\AuthSessionService;
use App\API\Services\OTPDeliveryService;
use App\API\Services\NotificationService;
use App\API\Services\DownloadService;
use App\API\Services\payments\MpesaPaymentService;
use App\API\Services\payments\KcbMpesaExpressService;
use App\API\Services\payments\FinancialAccountService;
use App\API\Services\ServiceContractBroker;
use App\API\Services\ReadReplicaService;
use App\API\Services\DataScopeService;
use Firebase\JWT\JWT;
use PDO;
use Exception;

/**
 * ParentPortalManager
 *
 * Owns every data read, session/OTP decision and messaging orchestration for
 * the parent-facing portal so ParentPortalController stays a thin endpoint
 * exposer (no direct DB access, no business decisions).
 *
 * Live-schema mapping (verified against KingsWayAcademy — normalised targets
 * only, never the legacy portal tables):
 *   - `parents.email / phone_1 / phone_2` → `persons` (parents holds only
 *     person_id, occupation, address, status)
 *   - `parents.portal_password / portal_status / portal_last_login`
 *     → `users.password_hash / status / last_login` (account not a person copy)
 *   - `parent_otp_sessions`            → `user_2fa_otp_sessions`
 *   - `parent_portal_sessions`         → `user_sessions`
 *   - `parent_statement_downloads`     → `audit_logs` (append-only download log)
 *   - `parent_portal_messages`         → `internal_messages` + `conversation_participants`
 *     (one deterministic conversation per parent×student, `title` = canonical
 *     `ParentPortal|<parent_id>|<student_id>`; participants are user IDs)
 *   - fee statement/balance data       → `vw_student_fee_balances` /
 *     `vw_student_fee_ledger` / `vw_payment_transactions_with_amount`
 *   - attendance context               → `vw_student_term_attendance_summary`
 *     (+ `student_attendance` via `student_academic_enrollments`)
 *   - `academic_terms`                 → `academic_year_terms` + `terms`
 *   - `class_streams`/`class_enrollments` → `academic_year_class_streams` +
 *     `academic_year_classes` + `classes` + `streams` + `student_academic_enrollments`
 *   - `student_core_values`            → `learner_values_acquisition`
 *   - `fee_structures_detailed`        → `academic_year_fee_schedules` + `fee_catalog`
 *
 * Session token format: standard HS256 JWT (same iss/aud/secret as staff
 * tokens) stored in `user_sessions.session_token`
 * (SHA-256 hashed via AuthSessionService). The single AuthMiddleware JWT path
 * accepts parents exactly like staff; each parent device may hold its own
 * session row and sessions slide on activity using the
 * shared idle window (AUTH_IDLE_TIMEOUT_SECONDS); the server advertises the sliding
 * expiry to the client via the X-Parent-Session-Expires header.
 */
class ParentPortalManager extends BaseAPI
{

    /** @var int */
    private $parentId = 0;

    /** @var int */
    private $sessionId = 0;

    /**
     * Access-token lifetime ceiling (seconds) for parent JWTs. The session's
     * true validity is the shared sliding idle window in user_sessions; exp is
     * a generous hard cap so very long-lived continuous use still re-authenticates.
     */
    private const PARENT_ACCESS_TOKEN_TTL = 604800;

    public function __construct()
    {
        parent::__construct('parent_portal');

        $auth = $_SERVER['auth_user'] ?? null;
        if (is_array($auth)) {
            $this->parentId  = (int)($auth['parent_id'] ?? 0);
            $this->user_id   = (int)($auth['user_id'] ?? $auth['id'] ?? 0) ?: null;
        }
        $this->sessionId = (int)($_SERVER['auth_session_id'] ?? 0);
    }

    // ========================================================================
    // AUTH ENDPOINTS (public — no session required)
    // ========================================================================

    /**
     * Email-or-phone + password login against users.password_hash (normalised
     * account). The identifier may be the parent's registered email address OR
     * phone number; the verification code always goes to the registered email.
     *
     * @param array $data {email (or phone), password}
     * @return array
     */
    public function postLogin(array $data): array
    {
        $identifier = trim((string)($data['email'] ?? ''));
        $password   = (string)($data['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return $this->errorResponse('Email or phone and password are required', 400);
        }

        // Build a match clause: exact email, or phone equal to any plausible
        // entry format (+254..., 254..., 0..., local digits). Registering on
        // the staff side uses the same person row, so phone login lands on the
        // exact same parent account.
        $phoneVariants = $this->phoneLookupVariants($identifier);
        $phoneOrEmail  = 'p.email = :email';
        $params        = [':email' => $identifier];
        if ($phoneVariants !== []) {
            $ands = [];
            foreach ($phoneVariants as $i => $variant) {
                $key = ':phone_'.$i;
                // Stored phones may carry a leading '+'/spacing; compare digits-only.
                $ands[] = "REPLACE(REPLACE(p.phone, '+', ''), ' ', '') = {$key}";
                $params[$key] = $variant;
            }
            $phoneOrEmail .= ' OR '.implode(' OR ', $ands);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT u.id AS user_id, pr.id AS parent_id,
                        p.first_name, p.last_name, p.email,
                       u.password_hash, u.status AS user_status,
                       u.data_scope AS user_data_scope, p.data_scope AS person_data_scope
                FROM users u
                JOIN persons p ON p.id = u.person_id
                JOIN parents pr ON pr.person_id = u.person_id
                WHERE ({$phoneOrEmail})
                  AND pr.status = 'active'
                  AND u.status = 'active'
                  AND u.data_scope = p.data_scope
                  AND EXISTS (
                      SELECT 1
                      FROM user_roles ur
                      JOIN roles r ON r.id = ur.role_id
                      WHERE ur.user_id = u.id
                        AND r.id = 73
                        AND r.name = 'Parent'
                  )
                 LIMIT 1"
            );
            $stmt->execute($params);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                // Symmetric guard: a real staff-only account that tries the
                // Parent Portal is directed back to the school workspace. The
                // password must verify first so we never reveal account
                // existence; unknown addresses keep the generic message.
                $account = str_contains($identifier, '@') ? $this->lookupActiveAccount($identifier) : null;
                if ($account && password_verify($password, $account['password_hash'])) {
                    return $this->successResponse([
                        'portal_mismatch' => 'staff',
                        'staff_login_url' => $this->staffLoginUrl(),
                        'message' => 'This account uses the school staff workspace, not the Parent Portal.',
                    ], 'This account uses the school workspace.');
                }
                return $this->errorResponse('Invalid email/phone or password', 401);
            }

            // No portal password set yet → account can't log in via password
            if (empty($parent['password_hash'])) {
                return $this->errorResponse('Portal access not yet activated. Use OTP or contact the school.', 401);
            }

            if (!password_verify($password, $parent['password_hash'])) {
                return $this->errorResponse('Invalid email/phone or password', 401);
            }

            $otpSessionId = $this->sendParentEmailOtp((int)$parent['user_id'], (string)$parent['email']);
            if (!$otpSessionId) return $this->errorResponse('Verification email could not be delivered. Please try again later.', 503);
            return $this->successResponse([
                'requires_otp' => true,
                'otp_session_id' => $otpSessionId,
                'masked_destination' => $this->maskEmail((string)$parent['email']),
                'expires_in' => 600,
            ], 'Enter the verification code sent to your email.');
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 429) return $this->errorResponse($e->getMessage(), 429);
            \App\API\Services\Logger::legacyError('[ParentPortalManager] '.$e->getMessage());
            return $this->errorResponse('Login failed', 500);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Login failed', 500);
        }
    }

    /**
     * Email OTP request. Anti-enumeration: returns success for unknown addresses.
     *
     * @param array $data {email}
     * @return array
     */
    public function postLoginOtpRequest(array $data): array
    {
        return $this->errorResponse('For your security, enter your email and password to request a verification code.', 403);
    }

    /**
     * Verify the email OTP and issue a session token.
     *
     * @param array $data {otp_session_id, otp_code}
     * @return array
     */
    public function postLoginOtpVerify(array $data): array
    {
        $sessionId = (int)($data['otp_session_id'] ?? 0);
        $otpCode   = trim((string)($data['otp_code'] ?? ''));

        if (!$sessionId || $otpCode === '') {
            return $this->errorResponse('otp_session_id and otp_code required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, otp_code, attempts
                 FROM user_2fa_otp_sessions
                 WHERE id = :id AND otp_expires_at > NOW() AND verified = 0
                 LIMIT 1"
            );
            $stmt->execute([':id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->errorResponse('OTP session not found or expired', 400);
            }
            if ((int)$session['attempts'] >= 5) {
                $this->db->prepare(
                    "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ?"
                )->execute([$sessionId]);
                return $this->errorResponse('Too many failed attempts. Request a new OTP.', 400);
            }

            // Increment attempts
            $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET attempts = attempts + 1 WHERE id = ?"
            )->execute([$sessionId]);

            if (!password_verify($otpCode, $session['otp_code'])) {
                return $this->errorResponse('Invalid OTP code', 400);
            }

            // Consume exactly once. A concurrent replay loses this update.
            $consume = $this->db->prepare(
                "UPDATE user_2fa_otp_sessions SET verified = 1 WHERE id = ? AND verified = 0"
            );
            $consume->execute([$sessionId]);
            if ($consume->rowCount() !== 1) return $this->errorResponse('OTP session was already used.', 409);

            $parent = $this->getParentByUserId((int)$session['user_id']);
            if (!$parent) {
                return $this->errorResponse('Parent account is not authorized', 403);
            }

            $session = $this->createSession(
                (int)$parent['user_id'],
                (int)$parent['person_id'],
                (int)$parent['parent_id']
            );

            return $this->successResponse([
                'token'      => $session['token'],
                'expires_at' => $session['expires_at'],
                'csrf_token' => $session['csrf_token'],
                'parent'     => [
                    'id'         => (int)$parent['parent_id'],
                    'first_name' => $parent['first_name'],
                    'last_name'  => $parent['last_name'],
                    'email'      => $parent['email'],
                ],
            ], 'Login successful');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('OTP verification failed', 500);
        }
    }

    /**
     * Build plausible local variants of a phone identifier so the entered
     * format (+254/254/0/local digits) can match any stored representation.
     * Returns [] when the identifier is clearly not a phone (e.g. an email).
     */
    private function phoneLookupVariants(string $identifier): array
    {
        if (str_contains($identifier, '@')) {
            return [];
        }
        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits === '' || !preg_match('/^\d{9,13}$/', $digits)) {
            return [];
        }

        $variants = [$digits];
        if (str_starts_with($digits, '0')) {
            $variants[] = '254' . substr($digits, 1);
        } elseif (str_starts_with($digits, '254')) {
            $variants[] = '0' . substr($digits, 3);
            $variants[] = substr($digits, 3);
        } elseif (str_starts_with($digits, '7')) {
            $variants[] = '2547' . substr($digits, 1);
            $variants[] = '07' . substr($digits, 1);
        }

        return array_values(array_unique($variants));
    }

    private function lookupActiveAccount(string $email): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT u.id, u.password_hash, u.status, u.data_scope, p.email
                 FROM users u
                 JOIN persons p ON p.id = u.person_id
                 WHERE p.email = :email
                   AND u.status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage());
            return null;
        }
    }

    private function staffLoginUrl(): string
    {
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
            $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;
            $baseUrl = $scheme . '://' . $host . $appBase;
        }

        return rtrim($baseUrl, '/') . '/login.php';
    }

    private function sendParentEmailOtp(int $userId, string $email): ?int
    {
        try {
            $tfa = new \App\API\Services\TwoFactorService($this->db);
            $code = $tfa->generateOTP($userId, 'email', 'login');
            if (!$code) return null;
            if (!(new OTPDeliveryService())->sendEmailOTP($email, $code, 'login')) {
                $tfa->invalidateLatestOTP($userId, 'login', 'email');
                return null;
            }
            $stmt = $this->db->prepare("SELECT id FROM user_2fa_otp_sessions WHERE user_id=? AND otp_type='login' AND method='email' AND verified=0 ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId]);
            return (int)($stmt->fetchColumn() ?: 0) ?: null;
        } catch (\RuntimeException $error) {
            if ($error->getCode() === 429) throw $error;
            \App\API\Services\Logger::legacyError('[ParentPortal] Email OTP failed: '.$error->getMessage());
            return null;
        }
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return substr($local, 0, 1).str_repeat('*', max(2, strlen($local)-1)).'@'.$domain;
    }

    /**
     * Revoke the active portal session.
     *
     * @return array
     */
    public function postLogout(): array
    {
        if ($this->sessionId) {
            try {
                $this->db->prepare(
                    "UPDATE user_sessions
                     SET session_status = 'logged_out', logout_time = NOW()
                     WHERE id = ?"
                )->execute([$this->sessionId]);
            } catch (Exception $e) {
                \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
        }
        return $this->successResponse(['message' => 'Logged out successfully']);
    }

    // ========================================================================
    // AUTHENTICATED ENDPOINTS (require the shared AuthMiddleware JWT path)
    // ========================================================================

    /**
     * Dashboard: parent profile + children with current fee balance.
     *
     * @return array
     */
    public function getDashboard(): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $children = [];
            $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
            $scopes = DataScopeService::scopes();
            $scopeIn = implode(',', array_fill(0, count($scopes), '?'));
            $stmt = $this->db->prepare(
                "SELECT s.id, ps.first_name, ps.last_name, s.admission_no, s.status,
                        c.name AS class_name, sl.name AS level_name,
                        COALESCE((SELECT SUM(fb.balance) FROM $feeBalView fb
                                  WHERE fb.student_id = s.id), 0) AS current_balance,
                        (SELECT MAX(pt.payment_date) FROM vw_payment_transactions_with_amount pt
                         WHERE pt.student_id = s.id
                           AND pt.status IN ('confirmed','completed','success')) AS last_payment_date
                 FROM student_parents sp
                 JOIN students s ON s.id = sp.student_id AND s.status = 'active'
                 JOIN persons ps ON ps.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae
                        ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN school_levels sl ON sl.id = c.level_id
                 WHERE sp.parent_id = ?
                   AND ps.data_scope IN ($scopeIn)
                 ORDER BY ps.first_name, ps.last_name"
            );
            $stmt->execute(array_merge([$this->parentId], array_values($scopes)));
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $parentInfo = $this->getParentProfile($this->parentId);

            return $this->successResponse([
                'parent'   => $parentInfo,
                'children' => $children,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** Parent community: PTA notices, representative meetings and invitations. */
    public function getCommunity(): array
    {
        if (!$this->parentId) return $this->errorResponse('Parent authentication required', 401);
        $stmt = $this->db->prepare("SELECT id,title,meeting_date,start_time,venue,purpose,description,status,type FROM parent_meetings WHERE (parent_id=? OR parent_id IS NULL AND type IN ('pta','general')) AND status IN ('scheduled','confirmed','postponed') AND meeting_date>=CURDATE() ORDER BY meeting_date,start_time LIMIT 50");
        $stmt->execute([$this->parentId]);
        $membership = $this->db->prepare("SELECT role,membership_status,appointed_at FROM parent_pta_memberships WHERE parent_id=? AND membership_status='active' AND (ended_at IS NULL OR ended_at>=CURDATE()) ORDER BY appointed_at DESC");
        $membership->execute([$this->parentId]);
        $memberships=$membership->fetchAll(PDO::FETCH_ASSOC);
        return $this->successResponse(['meetings'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'is_representative'=>!empty($memberships),'memberships'=>$memberships], 'Parent community loaded');
    }

    /**
     * Fee obligations grouped by academic year → term (per-fee-type breakdown).
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentFees(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $data = $this->buildStudentFeesData($studentId);
            return $this->successResponse(['academic_years' => $data]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load fees', 500);
        }
    }

    /**
     * Confirmed payment history (latest 100).
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentPaymentHistory(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $rows = $this->fetchPaymentHistory($studentId);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load payment history', 500);
        }
    }

    /**
     * Printable fee statement: student info + fees + payments + download log.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentStatement(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $student = $this->getStudentInfo($studentId);
            $fees    = $this->buildStudentFeesData($studentId);
            $payments = $this->fetchPaymentHistory($studentId);

            // Append-only download log (parent_statement_downloads → audit_logs)
            $this->logStatementDownload($studentId);

            return $this->successResponse([
                'student'      => $student,
                'fees'         => ['academic_years' => $fees],
                'payments'     => $payments,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to generate statement', 500);
        }
    }

    /**
     * Per-term balance summary + running total.
     *
     * @param int $studentId
     * @return array
     */
    public function getFeeBalance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
            $stmt = $this->db->prepare(
                "SELECT academic_year, term_id,
                        SUM(amount_due) AS total_due,
                        SUM(amount_paid) AS total_paid,
                        SUM(balance) AS balance,
                        MAX(payment_status) AS payment_status
                 FROM $feeBalView
                 WHERE student_id = :sid
                 GROUP BY academic_year, term_id
                 ORDER BY academic_year DESC, term_id ASC"
            );
            $stmt->execute([':sid' => $studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalBalance = array_sum(array_map(function ($r) {
                return (float)($r['balance'] ?? 0);
            }, $rows));

            return $this->successResponse(['per_term' => $rows, 'total_balance' => $totalBalance]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load balance', 500);
        }
    }

    /**
     * Attendance summary, recent entries and monthly breakdown.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentAttendance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $term = $this->getCurrentTerm();
            $termId = $term ? (int)$term['id'] : 0;

            // Summary for current term (class register)
            $summary = [
                'total_days'   => 0,
                'days_present' => 0,
                'days_absent'  => 0,
                'days_late'    => 0,
            ];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(class_days_marked),0)   AS total_days,
                            COALESCE(SUM(class_days_present),0)  AS days_present,
                            COALESCE(SUM(class_days_absent),0)   AS days_absent,
                            COALESCE(SUM(class_days_late),0)     AS days_late
                     FROM vw_student_term_attendance_summary
                     WHERE student_id = :sid AND term_id = :tid AND register_type = 'class'"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $summary = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$summary) {
                    $summary = ['total_days' => 0, 'days_present' => 0, 'days_absent' => 0, 'days_late' => 0];
                }
            }

            // Recent 30 entries
            $stmt = $this->db->prepare(
                "SELECT sa.date, sa.status, sa.absence_reason
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sae.student_id = :sid
                 ORDER BY sa.date DESC, sa.id DESC
                 LIMIT 30"
            );
            $stmt->execute([':sid' => $studentId]);
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Monthly breakdown for current year
            $yearBounds = $this->getCurrentYearBounds();
            $stmt = $this->db->prepare(
                "SELECT DATE_FORMAT(sa.date, '%Y-%m') AS month,
                        COUNT(*) AS total_days,
                        SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS days_present,
                        SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) AS days_absent,
                        SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS days_late
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 WHERE sae.student_id = :sid AND sa.date BETWEEN :start AND :end
                 GROUP BY DATE_FORMAT(sa.date, '%Y-%m')
                 ORDER BY month ASC"
            );
            $stmt->execute([
                ':sid'   => $studentId,
                ':start' => $yearBounds['start'],
                ':end'   => $yearBounds['end'],
            ]);
            $monthly = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $present = (int)($summary['days_present'] ?? 0);
            $total   = (int)($summary['total_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 1) : 0;

            return $this->successResponse([
                'term'       => $term,
                'summary'    => $summary,
                'percentage' => $percentage,
                'recent'     => $recent,
                'monthly'    => $monthly,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getStudentTransport(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) return $access;
        try {
            $stmt = $this->db->prepare(
                "SELECT a.status, a.pickup_time, a.dropoff_time, a.expected_amount,
                        r.name AS route_name, ps.name AS pickup_stop, ds.name AS dropoff_stop,
                        v.registration_number, CONCAT_WS(' ', dp.first_name, dp.last_name) AS driver_name
                   FROM student_transport_assignments a
                   JOIN transport_routes r ON r.id = a.route_id
              LEFT JOIN transport_stops ps ON ps.id = COALESCE(a.pickup_stop_id, a.stop_id)
              LEFT JOIN transport_stops ds ON ds.id = COALESCE(a.dropoff_stop_id, a.stop_id)
              LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = r.id AND tvr.status = 'active'
              LEFT JOIN transport_vehicles v ON v.id = tvr.vehicle_id
              LEFT JOIN staff d ON d.id = v.driver_id
              LEFT JOIN persons dp ON dp.id = d.person_id
                  WHERE a.student_id = ?
               ORDER BY a.year DESC, a.month DESC LIMIT 1"
            );
            $stmt->execute([$studentId]);
            return $this->successResponse($stmt->fetch(PDO::FETCH_ASSOC) ?: []);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] transport: ' . $e->getMessage());
            return $this->errorResponse('Failed to load transport information', 500);
        }
    }

    /**
     * Report-card-style performance data: subject scores, competencies, values.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentPerformance(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        return $this->buildPerformancePayload($studentId, false);
    }

    /**
     * Full KICD-CBC report card data.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentReportCard(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            // A guardian sees the exact immutable snapshot approved and
            // released by the school—not a live reconstruction from mutable
            // score tables and never an unapproved report.
            $stmt = $this->db->prepare(
                "SELECT id, version_no, report_data_json, pdf_path, pdf_sha256, released_at
                 FROM report_card_releases
                 WHERE student_id = ? AND status = 'released'
                 ORDER BY released_at DESC, version_no DESC
                 LIMIT 1"
            );
            $stmt->execute([$studentId]);
            $release = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$release) {
                return $this->successResponse([
                    'released' => false,
                    'message' => 'The school has not released a report card for this learner yet.',
                ], 'Report card not released');
            }
            $path = (string) $release['pdf_path'];
            if (!is_file($path) || !hash_equals((string) $release['pdf_sha256'], (string) hash_file('sha256', $path))) {
                \App\API\Services\Logger::legacyError('[ParentPortalManager] Released report card PDF failed integrity verification: ' . (int) $release['id']);
                return $this->errorResponse('The released report card is temporarily unavailable.', 503);
            }
            $payload = json_decode((string) $release['report_data_json'], true);
            if (!is_array($payload)) {
                return $this->errorResponse('The released report card is unavailable.', 500);
            }
            $payload['released'] = true;
            $payload['official_release'] = [
                'release_id' => (int) $release['id'],
                'version_no' => (int) $release['version_no'],
                'released_at' => $release['released_at'],
                'download_url' => (new DownloadService())->generatedDownloadUrlForAbsolutePath($path),
            ];
            $this->db->prepare(
                "UPDATE report_card_deliveries
                 SET status='delivered', failure_reason=NULL
                 WHERE report_card_release_id=? AND parent_id=? AND channel='portal'"
            )->execute([(int) $release['id'], $this->parentId]);
            return $this->successResponse($payload, 'Official report card loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] official report card: ' . $e->getMessage());
            return $this->errorResponse('Unable to load the released report card.', 500);
        }
    }

    /**
     * Read-only CBC planning view for a parent's child. Only the child's
     * current academic-year stream is considered, and draft content never
     * leaves the staff workflow.
     */
    public function getStudentLearningPlan(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) return $access;

        try {
            $context = $this->db->prepare(
                "SELECT ay.id AS academic_year_id, ay.year_code, ayt.id AS academic_year_term_id,
                        t.name AS term_name, sae.academic_year_class_stream_id,
                        c.name AS class_name, sn.name AS stream_name
                 FROM student_academic_enrollments sae
                 JOIN academic_years ay ON ay.id = sae.academic_year_id AND ay.is_current = 1
                 JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND ayt.status = 'current'
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
                 LIMIT 1"
            );
            $context->execute([$studentId]);
            $ctx = $context->fetch(PDO::FETCH_ASSOC);
            if (!$ctx) return $this->successResponse(['context' => null, 'schemes' => [], 'lesson_plans' => []]);

            $scheme = $this->db->prepare(
                "SELECT sw.id, sw.status, sw.updated_at,
                        sw.academic_year_calendar_week_id AS week_id,
                        ac.week_number, ac.week_start, ac.week_end,
                        la.name AS learning_area, st.title,
                        st.strand_id, st.sub_strand_id,
                        sn.name AS strand_name, ss.name AS sub_strand_name,
                        st.activities, st.resources, st.assessment_methods
                 FROM schemes_of_work sw
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = sw.academic_year_class_stream_learning_area_id
                 JOIN scheme_templates st ON st.id = sw.scheme_template_id
                 JOIN learning_areas la ON la.id = st.learning_area_id
                 LEFT JOIN strands sn ON sn.id = st.strand_id
                 LEFT JOIN sub_strands ss ON ss.id = st.sub_strand_id
                 JOIN academic_year_calendar ac ON ac.id = sw.academic_year_calendar_week_id
                 WHERE aysla.academic_year_class_stream_id = ?
                   AND ac.academic_year_term_id = ?
                   AND sw.status = 'approved'
                 ORDER BY ac.week_number, la.name, sw.id"
            );
            $scheme->execute([(int) $ctx['academic_year_class_stream_id'], (int) $ctx['academic_year_term_id']]);

            $plans = $this->db->prepare(
                "SELECT lp.id, lp.scheme_of_work_id, lp.status, lp.updated_at,
                        d.date AS lesson_date, ac.week_number,
                        la.name AS learning_area, lt.title,
                        lt.duration, lt.activities, lt.resources, lt.assessment,
                        sn.name AS strand_name, ss.name AS sub_strand_name
                 FROM lesson_plans lp
                 JOIN schemes_of_work sw ON sw.id = lp.scheme_of_work_id AND sw.status = 'approved'
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = lp.academic_year_class_stream_learning_area_id
                  AND aysla.academic_year_class_stream_id = ?
                 JOIN lesson_templates lt ON lt.id = lp.lesson_template_id
                 JOIN learning_areas la ON la.id = lt.learning_area_id
                 LEFT JOIN strands sn ON sn.id = lt.strand_id
                 LEFT JOIN sub_strands ss ON ss.id = lt.sub_strand_id
                 JOIN academic_year_calendar_days d ON d.id = lp.academic_year_calendar_day_id
                 JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                 WHERE ac.academic_year_term_id = ?
                   AND lp.status IN ('approved','delivered')
                 ORDER BY d.date, lp.id"
            );
            $plans->execute([(int) $ctx['academic_year_class_stream_id'], (int) $ctx['academic_year_term_id']]);

            return $this->successResponse([
                'context' => $ctx,
                'schemes' => $scheme->fetchAll(PDO::FETCH_ASSOC),
                'lesson_plans' => $plans->fetchAll(PDO::FETCH_ASSOC),
            ], 'Learning plans loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage());
            return $this->errorResponse('Unable to load learning plans', 500);
        }
    }

    /**
     * Learning workspace for a parent's child: published assignments with the
     * child's submission status, approved scheme workbook items with their
     * learning outcomes, practical key-inquiry questions and suggested
     * experiences, plus the grade-level CBC learning outcomes.
     *
     * Only the child's current academic-year stream is considered; draft leak
     * is impossible because scheme workbooks must be `approved` and
     * assignments must be `published` and not deleted.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentLearning(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $context = $this->db->prepare(
                "SELECT ay.id AS academic_year_id, ay.year_code, ayt.id AS academic_year_term_id,
                        ayt.status AS term_status, t.name AS term_name,
                        sae.academic_year_class_stream_id,
                        c.name AS class_name, sn.name AS stream_name
                 FROM student_academic_enrollments sae
                 JOIN academic_years ay ON ay.id = sae.academic_year_id AND ay.is_current = 1
                 JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND ayt.status = 'current'
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
                 LIMIT 1"
            );
            $context->execute([$studentId]);
            $ctx = $context->fetch(PDO::FETCH_ASSOC);
            if (!$ctx) {
                return $this->successResponse([
                    'context' => null,
                    'assignments' => [],
                    'workbook_items' => [],
                    'learning_outcomes' => [],
                ]);
            }

            // Published, non-deleted assignments for the child's class in the
            // current term, with the child's submission state attached.
            $assignStmt = $this->db->prepare(
                "SELECT a.id, a.title, a.description, a.due_date, a.total_marks,
                        a.attachment_url, a.status, a.created_at,
                        la.name AS learning_area,
                        sn.name AS strand_name, ss.name AS sub_strand_name,
                        (SELECT ap.id FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS submission_id,
                        (SELECT ap.status FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS submission_status,
                        (SELECT ap.marks_awarded FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS marks_awarded,
                        (SELECT ap.feedback FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS feedback,
                        (SELECT ap.graded_at FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS graded_at,
                        (SELECT ap.marks_awarded IS NOT NULL FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS is_graded
                 FROM assignments a
                 JOIN student_academic_enrollments a2s ON a2s.student_id = ? AND a2s.enrollment_status = 'active'
                 JOIN academic_year_class_streams aycs ON aycs.id = a2s.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN strands sn ON sn.id = a.strand_id
                 LEFT JOIN sub_strands ss ON ss.id = a.sub_strand_id
                 WHERE a.class_id = c.id
                   AND a.status = 'published'
                   AND a.deleted_at IS NULL
                   AND a.academic_year_id = ?
                   AND a.term_id = ?
                 ORDER BY a.due_date DESC, a.id DESC"
            );
            $assignStmt->execute([$studentId, (int) $ctx['academic_year_id'], (int) $ctx['academic_year_term_id']]);
            $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);

            // Approved scheme workbooks → their items → outcomes, practical
            // questions and suggested experiences, ordered by week then item.
            $workbookStmt = $this->db->prepare(
                "SELECT swb.title AS workbook_title, wbw.week_number,
                        la.name AS learning_area,
                        st.title AS strand_title, st.name AS strand_name,
                        sst.name AS sub_strand_name,
                        it.id AS item_id, it.title AS item_title,
                        swbi.outcome_text, swbi.is_custom AS outcome_is_custom,
                        q.question_text, q.is_custom AS question_is_custom,
                        ex.experience_text, ex.is_custom AS experience_is_custom
                 FROM scheme_workbooks swb
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = swb.academic_year_class_stream_learning_area_id
                 JOIN academic_year_class_learning_areas ayscla
                   ON ayscla.id = aysla.academic_year_class_learning_area_id
                 JOIN learning_areas la ON la.id = ayscla.learning_area_id
                 JOIN scheme_workbook_weeks wbw ON wbw.workbook_id = swb.id
                 JOIN scheme_workbook_items it ON it.workbook_week_id = wbw.id
                 LEFT JOIN strands st ON st.id = it.strand_id
                 LEFT JOIN sub_strands sst ON sst.id = it.sub_strand_id
                 LEFT JOIN scheme_workbook_item_outcomes swbi ON swbi.workbook_item_id = it.id
                 LEFT JOIN scheme_workbook_item_questions q ON q.workbook_item_id = it.id
                 LEFT JOIN scheme_workbook_item_experiences ex ON ex.workbook_item_id = it.id
                 WHERE aysla.academic_year_class_stream_id = ?
                   AND swb.academic_year_term_id = ?
                   AND swb.status = 'approved'
                 ORDER BY wbw.week_number, it.sort_order, it.id"
            );
            $workbookStmt->execute([(int) $ctx['academic_year_class_stream_id'], (int) $ctx['academic_year_term_id']]);
            $workbookItems = $workbookStmt->fetchAll(PDO::FETCH_ASSOC);

            // Grade-level CBC outcomes (structured expectations a parent can
            // read against the items above). The class name IS the grade
            // level ('Playgroup','PP1',..,'Grade 9').
            $grade = trim((string) ($ctx['class_name'] ?? ''));
            $outcomes = [];
            if ($grade !== '') {
                $gradeMatch = $grade . '%';
                $outcomeStmt = $this->db->prepare(
                    "SELECT lo.id, lo.learning_area_id, la.name AS learning_area,
                            lo.strand_id, lo.sub_strand_id,
                            sn.name AS strand_name, ss.name AS sub_strand_name,
                            lo.outcome
                     FROM learning_outcomes lo
                     JOIN learning_areas la ON la.id = lo.learning_area_id
                     LEFT JOIN strands sn ON sn.id = lo.strand_id
                     LEFT JOIN sub_strands ss ON ss.id = lo.sub_strand_id
                     WHERE lo.grade_level LIKE ?
                     ORDER BY la.name, sn.name, ss.name, lo.id"
                );
                $outcomeStmt->execute([$gradeMatch]);
                $outcomes = $outcomeStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->successResponse([
                'context' => $ctx,
                'assignments' => $assignments,
                'workbook_items' => $workbookItems,
                'learning_outcomes' => $outcomes,
            ], 'Learning workspace loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] learning: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load the learning workspace', 500);
        }
    }

    /**
     * Covered content for a parent's child: the approved lesson plans actually
     * taught this term (approved/delivered) with their learning area, strand,
     * sub-strand, week and date, plus published assignments with the child's
     * submission state. Grouping into learning area → week → day is done by
     * the frontend from these flat arrays.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentCoverage(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $context = $this->db->prepare(
                "SELECT ay.id AS academic_year_id, ay.year_code, ayt.id AS academic_year_term_id,
                        ayt.status AS term_status, t.name AS term_name,
                        sae.academic_year_class_stream_id,
                        c.name AS class_name, sn.name AS stream_name
                 FROM student_academic_enrollments sae
                 JOIN academic_years ay ON ay.id = sae.academic_year_id AND ay.is_current = 1
                 JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND ayt.status = 'current'
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
                 LIMIT 1"
            );
            $context->execute([$studentId]);
            $ctx = $context->fetch(PDO::FETCH_ASSOC);
            if (!$ctx) {
                return $this->successResponse(['context' => null, 'lessons' => [], 'assignments' => []]);
            }

            // Taught (approved/delivered) lesson plans for the child's stream
            // this term, flat with their strand/sub-strand and week/date.
            $lessons = $this->db->prepare(
                "SELECT lp.id, lp.scheme_of_work_id, lp.status, lp.updated_at,
                        d.date AS lesson_date, ac.week_number, ac.week_start, ac.week_end,
                        la.name AS learning_area, lt.title,
                        lt.duration, lt.activities, lt.resources, lt.assessment,
                        sn.name AS strand_name, ss.name AS sub_strand_name
                 FROM lesson_plans lp
                 JOIN schemes_of_work sw ON sw.id = lp.scheme_of_work_id AND sw.status = 'approved'
                 JOIN academic_year_class_stream_learning_areas aysla
                   ON aysla.id = lp.academic_year_class_stream_learning_area_id
                  AND aysla.academic_year_class_stream_id = ?
                 JOIN lesson_templates lt ON lt.id = lp.lesson_template_id
                 JOIN learning_areas la ON la.id = lt.learning_area_id
                 LEFT JOIN strands sn ON sn.id = lt.strand_id
                 LEFT JOIN sub_strands ss ON ss.id = lt.sub_strand_id
                 JOIN academic_year_calendar_days d ON d.id = lp.academic_year_calendar_day_id
                 JOIN academic_year_calendar ac ON ac.id = d.academic_year_calendar_id
                 WHERE ac.academic_year_term_id = ?
                   AND lp.status IN ('approved','delivered')
                 ORDER BY ac.week_number, d.date, la.name, lp.id"
            );
            $lessons->execute([(int) $ctx['academic_year_class_stream_id'], (int) $ctx['academic_year_term_id']]);

            // Published assignments with the child's submission state.
            $assignStmt = $this->db->prepare(
                "SELECT a.id, a.title, a.description, a.due_date, a.total_marks,
                        a.attachment_url, a.status, a.created_at,
                        la.name AS learning_area,
                        sn.name AS strand_name, ss.name AS sub_strand_name,
                        (SELECT ap.id FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS submission_id,
                        (SELECT ap.status FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS submission_status,
                        (SELECT ap.marks_awarded FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS marks_awarded,
                        (SELECT ap.marks_awarded IS NOT NULL FROM assignment_submissions ap
                          WHERE ap.assignment_id = a.id AND ap.student_id = a2s.student_id
                          ORDER BY ap.submitted_at DESC LIMIT 1) AS is_graded
                 FROM assignments a
                 JOIN student_academic_enrollments a2s ON a2s.student_id = ? AND a2s.enrollment_status = 'active'
                 JOIN academic_year_class_streams aycs ON aycs.id = a2s.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN strands sn ON sn.id = a.strand_id
                 LEFT JOIN sub_strands ss ON ss.id = a.sub_strand_id
                 WHERE a.class_id = c.id
                   AND a.status = 'published'
                   AND a.deleted_at IS NULL
                   AND a.academic_year_id = ?
                   AND a.term_id = ?
                 ORDER BY a.due_date DESC, a.id DESC"
            );
            $assignStmt->execute([$studentId, (int) $ctx['academic_year_id'], (int) $ctx['academic_year_term_id']]);

            return $this->successResponse([
                'context' => $ctx,
                'lessons' => $lessons->fetchAll(PDO::FETCH_ASSOC),
                'assignments' => $assignStmt->fetchAll(PDO::FETCH_ASSOC),
            ], 'Covered content loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load covered content', 500);
        }
    }

    /**
     * Learning analytics for a parent's child: term-to-term and year-to-year
     * performance, learning-area profile, class comparison and rubric
     * distribution — built from the governed analytic views — plus a
     * deterministic, rule-based SWOT summary (never an LLM call).
     *
     * The views are per-child guarded; this method never exposes other
     * learners, and class comparison only aggregates the child's own stream.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentAnalytics(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $student = $this->getStudentInfo($studentId);
            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $term = $this->getCurrentTerm();
            $year = $term['year'] ?? null;
            $tnum = $term ? (int) $term['id'] : 0;
            $tid  = $tnum;

            $termOverview = [];
            $stp = ReadReplicaService::qualifiedRef('student_term_performance');
            if ($year) {
                $stmt = $this->db->prepare(
                    "SELECT academic_year, term_number, term_name, subjects_count,
                            total_points, average_percentage, overall_grade, subjects_passed
                     FROM $stp
                     WHERE student_id = :sid
                     ORDER BY academic_year, term_number"
                );
                $stmt->execute([':sid' => $studentId]);
                $termOverview = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $yearOverview = [];
            foreach ($termOverview as $row) {
                $ay = $row['academic_year'] ?? 'Unknown';
                if (!isset($yearOverview[$ay])) {
                    $yearOverview[$ay] = ['academic_year' => $ay, 'terms' => 0, 'avg_total' => 0.0, 'percentage_values' => []];
                }
                if (($row['average_percentage'] ?? null) !== null) {
                    $yearOverview[$ay]['percentage_values'][] = (float) $row['average_percentage'];
                    $yearOverview[$ay]['avg_total'] += (float) $row['average_percentage'];
                }
                $yearOverview[$ay]['terms']++;
            }
            foreach ($yearOverview as $ay => $agg) {
                $n = count($agg['percentage_values']);
                $yearOverview[$ay]['average_percentage'] = $n ? round($agg['avg_total'] / $n, 2) : null;
                unset($yearOverview[$ay]['avg_total'], $yearOverview[$ay]['percentage_values']);
            }
            $yearOverview = array_values($yearOverview);

            // Per-learning-area profile for the child in the current term.
            $learningAreas = [];
            $rubric = ['ee' => 0, 'me' => 0, 'ae' => 0, 'be' => 0];
            $slp = ReadReplicaService::qualifiedRef('student_learning_progress');
            if ($year && $tnum) {
                $stmt = $this->db->prepare(
                    "SELECT learning_area,
                            ROUND(AVG(average_percentage), 2) AS average_percentage,
                            COUNT(*) AS rows_aggregated,
                            SUM(ee_count) AS ee_count, SUM(me_count) AS me_count,
                            SUM(ae_count) AS ae_count, SUM(be_count) AS be_count
                     FROM $slp
                     WHERE student_id = :sid AND academic_year = :year AND term_number = :tnum
                     GROUP BY learning_area
                     ORDER BY learning_area"
                );
                $stmt->execute([':sid' => $studentId, ':year' => $year, ':tnum' => $tnum]);
                $learningAreas = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(ee_count),0) AS ee, COALESCE(SUM(me_count),0) AS me,
                            COALESCE(SUM(ae_count),0) AS ae, COALESCE(SUM(be_count),0) AS be
                     FROM $slp
                     WHERE student_id = :sid AND academic_year = :year AND term_number = :tnum"
                );
                $stmt->execute([':sid' => $studentId, ':year' => $year, ':tnum' => $tnum]);
                $rubricRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($rubricRow) {
                    $rubric = [
                        'ee' => (int) $rubricRow['ee'],
                        'me' => (int) $rubricRow['me'],
                        'ae' => (int) $rubricRow['ae'],
                        'be' => (int) $rubricRow['be'],
                    ];
                }
            }

            // Class-level comparison (the child's own stream only) so a parent
            // can see standing without exposing other learners by name.
            $classComparison = [];
            $clp = ReadReplicaService::qualifiedRef('class_learning_area_performance');
            if ($year && $tnum && !empty($student['class_name']) && !empty($student['stream_name'])) {
                $stmt = $this->db->prepare(
                    "SELECT learning_area, average_percentage, students_assessed
                     FROM $clp
                     WHERE academic_year = :year AND term_number = :tnum
                       AND class_name = :cls AND stream_name = :stream
                     GROUP BY learning_area
                     ORDER BY learning_area"
                );
                $stmt->execute([
                    ':year' => $year,
                    ':tnum' => $tnum,
                    ':cls' => $student['class_name'],
                    ':stream' => $student['stream_name'],
                ]);
                $classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $childByArea = [];
                foreach ($learningAreas as $la) {
                    $childByArea[$la['learning_area']] = isset($la['average_percentage']) ? (float) $la['average_percentage'] : null;
                }
                foreach ($classRows as $row) {
                    $classComparison[] = [
                        'learning_area' => $row['learning_area'],
                        'class_average' => isset($row['average_percentage']) ? (float) $row['average_percentage'] : null,
                        'students_assessed' => (int) $row['students_assessed'],
                        'child_average' => $childByArea[$row['learning_area']] ?? null,
                    ];
                }
                foreach ($learningAreas as $la) {
                    $found = false;
                    foreach ($classComparison as $cc) {
                        if ($cc['learning_area'] === $la['learning_area']) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $classComparison[] = [
                            'learning_area' => $la['learning_area'],
                            'class_average' => null,
                            'students_assessed' => 0,
                            'child_average' => isset($la['average_percentage']) ? (float) $la['average_percentage'] : null,
                        ];
                    }
                }
                usort($classComparison, static function ($a, $b) {
                    return strcmp($a['learning_area'], $b['learning_area']);
                });
            }

            // Attendance context (class register).
            $attendance = ['total_days' => 0, 'days_present' => 0, 'days_absent' => 0, 'days_late' => 0];
            if ($tid) {
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(class_days_marked),0)   AS total_days,
                            COALESCE(SUM(class_days_present),0)  AS days_present,
                            COALESCE(SUM(class_days_absent),0)   AS days_absent,
                            COALESCE(SUM(class_days_late),0)     AS days_late
                     FROM vw_student_term_attendance_summary
                     WHERE student_id = :sid AND term_id = :tid AND register_type = 'class'"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $tid]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: $attendance;
            }

            return $this->successResponse([
                'student' => $student,
                'term' => $term,
                'term_overview' => $termOverview,
                'year_overview' => $yearOverview,
                'learning_areas' => $learningAreas,
                'class_comparison' => $classComparison,
                'rubric_distribution' => $rubric,
                'attendance' => $attendance,
                'swot' => $this->buildSwot($learningAreas, $classComparison, $termOverview, $rubric, $attendance),
            ], 'Learning analytics loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load learning analytics', 500);
        }
    }

    /**
     * Deterministic SWOT summary for a child (rule-based only — no LLM).
     *
     * @param array $learningAreas   per-LA child profile
     * @param array $classComparison child vs class per LA
     * @param array $termOverview    term-to-term performance rows
     * @param array $rubric          ee/me/ae/be totals
     * @param array $attendance      total/present/absent/late
     * @return array
     */
    private function buildSwot(array $learningAreas, array $classComparison, array $termOverview, array $rubric, array $attendance): array
    {
        $strengths = [];
        $weaknesses = [];
        $opportunities = [];
        $threats = [];

        foreach ($learningAreas as $la) {
            $avg = isset($la['average_percentage']) ? (float) $la['average_percentage'] : null;
            $name = $la['learning_area'] ?? 'Learning area';
            if ($avg === null) {
                continue;
            }
            if ($avg >= 75.0) {
                $strengths[] = "$name is a strong area (average {$avg}%).";
            } elseif ($avg < 50.0) {
                $weaknesses[] = "$name needs support (average {$avg}%).";
            } else {
                $classAvg = null;
                foreach ($classComparison as $cc) {
                    if ($cc['learning_area'] === $name && $cc['class_average'] !== null) {
                        $classAvg = $cc['class_average'];
                        break;
                    }
                }
                if ($classAvg !== null && $avg < $classAvg - 5.0) {
                    $opportunities[] = "$name ($avg%) is below the class average ($classAvg%) — a clear growth opportunity.";
                }
            }
        }

        $assessedRubric = array_sum($rubric);
        if ($assessedRubric > 0) {
            $eeShare = round(100 * (($rubric['ee'] ?? 0) + ($rubric['me'] ?? 0)) / $assessedRubric);
            if ($eeShare >= 70) {
                $strengths[] = "Most assessed work ($eeShare%) meets or exceeds expectations.";
            } elseif (($rubric['be'] ?? 0) > 0 && (($rubric['be'] ?? 0) / $assessedRubric) >= 0.3) {
                $weaknesses[] = 'A notable share of assessed work is below expectations — review support needs.';
            }
        }

        $rates = [];
        $total = (int) ($attendance['total_days'] ?? 0);
        if ($total > 0) {
            $rates['present'] = round(100 * (int) ($attendance['days_present'] ?? 0) / $total);
            $rates['absent'] = round(100 * (int) ($attendance['days_absent'] ?? 0) / $total);
        }
        if (isset($rates['present']) && $rates['present'] >= 90) {
            $strengths[] = "Attendance is excellent ({$rates['present']}%).";
        } elseif (isset($rates['present']) && $rates['present'] < 75) {
            $weaknesses[] = "Attendance is low ({$rates['present']}%) — missed learning days add up.";
        }

        $avgs = array_values(array_filter(array_map(static function ($r) {
            return isset($r['average_percentage']) ? (float) $r['average_percentage'] : null;
        }, $termOverview), static function ($v) {
            return $v !== null;
        }));
        if (count($avgs) >= 2) {
            $last = array_pop($avgs);
            $prev = array_pop($avgs);
            if ($last > $prev + 5) {
                $opportunities[] = 'Overall performance is improving term over term — momentum to build on.';
            } elseif ($last < $prev - 5) {
                $threats[] = 'Overall performance has dipped recently — worth an early conversation.';
            }
        }

        if (empty($strengths) && empty($weaknesses) && empty($opportunities) && empty($threats)) {
            return ['strengths' => [], 'weaknesses' => [], 'opportunities' => [], 'threats' => []];
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'opportunities' => $opportunities,
            'threats' => $threats,
        ];
    }

    /**
     * Full CBC competencies view for a parent's child: every core competency
     * (assessed or not) with its current-term performance level, evidence and
     * teacher notes, all core values with evidence, and the level legend.
     *
     * Unassessed competencies are returned too so the parent sees the full
     * picture rather than only assessed rows.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentCompetencies(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $student = $this->getStudentInfo($studentId);
            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $term = $this->getCurrentTerm();
            $termId = $term ? (int) $term['id'] : 0;

            $competencies = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT cc.id, cc.code, cc.name AS competency_name,
                            lc.performance_level_id,
                            plc.code AS level_code, plc.name AS level_name,
                            lc.evidence, lc.teacher_notes,
                            lc.assessed_date,
                            (lc.id IS NOT NULL) AS has_assessment
                     FROM core_competencies cc
                     LEFT JOIN learner_competencies lc
                            ON lc.competency_id = cc.id
                           AND lc.student_id = :sid
                           AND lc.term_id = :tid
                     LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                     ORDER BY cc.id"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $values = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT cv.id, cv.code, cv.name AS value_name,
                            lva.evidence, lva.incident_date,
                            (lva.id IS NOT NULL) AS has_evidence
                     FROM core_values cv
                     LEFT JOIN learner_values_acquisition lva
                            ON lva.value_id = cv.id
                           AND lva.student_id = :sid
                           AND lva.term_id = :tid
                     ORDER BY cv.id"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $levels = $this->db->prepare(
                "SELECT id, code, name FROM performance_levels_cbc ORDER BY id"
            );
            $levels->execute();
            $levelLegend = $levels->fetchAll(PDO::FETCH_ASSOC);

            $assessed = 0;
            foreach ($competencies as $comp) {
                if (!empty($comp['has_assessment'])) {
                    $assessed++;
                }
            }

            return $this->successResponse([
                'student' => $student,
                'term' => $term,
                'competencies' => $competencies,
                'values' => $values,
                'levels' => $levelLegend,
                'summary' => [
                    'assessed' => $assessed,
                    'total' => count($competencies),
                    'assessed_values' => count(array_filter($values, static function ($v) {
                        return !empty($v['has_evidence']);
                    })),
                ],
            ], 'Competencies loaded');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load competencies', 500);
        }
    }

    /**
     * Aggregate health summary for a parent's child (vw_student_health_summary).
     * Returns counts/flags only — never raw health records or notes.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentHealth(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $view = ReadReplicaService::qualifiedRef('student_health_summary');
            $stmt = $this->db->prepare(
                "SELECT student_id, admission_no, student_name, class_name, stream_name,
                        health_records, emergency_flags,
                        active_allergies, active_conditions, active_medications
                 FROM $view
                 WHERE student_id = :sid
                 LIMIT 1"
            );
            $stmt->execute([':sid' => $studentId]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return $this->successResponse([
                'summary' => $summary,
                'emergency_flags' => (int) ($summary['emergency_flags'] ?? 0) > 0,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] health: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load health summary', 500);
        }
    }

    /**
     * Co-curricular participation for a parent's child: activities in which
     * the child participates (via activity_participants) with category and
     * schedule.
     *
     * @param int $studentId
     * @return array
     */
    public function getStudentActivities(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT act.id, act.title, act.description,
                        act.start_date, act.end_date, act.status AS activity_status,
                        ac.name AS category_name,
                        ap.role, ap.status AS participant_status, ap.joined_at, ap.notes,
                        ap.student_academic_enrollment_id
                 FROM activity_participants ap
                 JOIN student_academic_enrollments sae
                   ON sae.id = ap.student_academic_enrollment_id AND sae.student_id = ?
                 JOIN activities act ON act.id = ap.activity_id
                 LEFT JOIN activity_categories ac ON ac.id = act.category_id
                 ORDER BY act.start_date DESC, act.id DESC"
            );
            $stmt->execute([$studentId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse(['activities' => $activities]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] activities: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load activities', 500);
        }
    }

    /**
     * Parent-facing school updates: published announcements targeting all or
     * parents, plus upcoming school events. Read-only; no mutation of feed
     * state.
     *
     * @return array
     */
    public function getParentUpdates(): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $annStmt = $this->db->prepare(
                "SELECT id, title, content, announcement_type, priority,
                        target_audience, status, published_at, expires_at
                 FROM announcements_bulletin
                 WHERE status = 'published'
                   AND target_audience IN ('all', 'parents')
                   AND (expires_at IS NULL OR expires_at > NOW())
                   AND published_at <= NOW()
                 ORDER BY priority = 'critical' DESC,
                          priority = 'high' DESC,
                          published_at DESC
                 LIMIT 50"
            );
            $annStmt->execute();
            $announcements = $annStmt->fetchAll(PDO::FETCH_ASSOC);

            $eventStmt = $this->db->prepare(
                "SELECT id, title, description, start_at, end_at, type, category,
                        location, status
                 FROM school_events
                 WHERE source = 'manual'
                   AND calendar_day_id IS NULL
                   AND status IN ('upcoming', 'ongoing')
                   AND end_at >= CURDATE()
                   AND category NOT IN ('holiday', 'public_holiday')
                 ORDER BY start_at ASC
                 LIMIT 30"
            );
            $eventStmt->execute();
            $events = $eventStmt->fetchAll(PDO::FETCH_ASSOC);

            // Upcoming assessments/exams for the parent's children.
            $exams = $this->db->prepare(
                "SELECT a.id, a.title, a.assessment_date, a.status, a.max_marks,
                        a.max_marks,
                        la.name AS learning_area,
                        aty.name AS assessment_type,
                        c.name AS class_name, sn.name AS stream_name,
                        s.id AS student_id,
                        CONCAT_WS(' ', p.first_name, p.last_name) AS student_name
                 FROM assessments a
                 JOIN academic_year_class_streams aycs ON aycs.id = a.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN streams sn ON sn.id = aycs.stream_id
                 LEFT JOIN learning_areas la ON la.id = a.learning_area_id
                 LEFT JOIN assessment_types aty ON aty.id = a.assessment_type_id
                 JOIN student_academic_enrollments sae
                   ON sae.academic_year_class_stream_id = a.academic_year_class_stream_id
                  AND sae.enrollment_status = 'active'
                 JOIN students s ON s.id = sae.student_id
                 JOIN persons p ON p.id = s.person_id
                 JOIN student_parents sp ON sp.student_id = s.id AND sp.parent_id = :pid
                 WHERE a.assessment_date >= CURDATE()
                   AND a.status IN ('pending_submission', 'submitted')
                 GROUP BY a.id
                 ORDER BY a.assessment_date ASC
                 LIMIT 30"
            );
            $exams->execute([':pid' => $this->parentId]);

            // Current academic-year term dates (opening / half-term / closing).
            $termDates = $this->db->prepare(
                "SELECT ayt.id, t.name AS term_name,
                        ayt.opening_date, ayt.half_term_start, ayt.half_term_end,
                        ayt.closing_date, ayt.status
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE ay.is_current = 1
                 ORDER BY t.id"
            );
            $termDates->execute();
            $termDates = $termDates->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'announcements' => $announcements,
                'events' => $events,
                'exams' => $exams->fetchAll(PDO::FETCH_ASSOC),
                'term_dates' => $termDates,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] updates: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load updates', 500);
        }
    }

    /**
     * Messages between the parent and the school, scoped to a student.
     *
     * @param int|null $studentId
     * @return array
     */
    public function getMessages(?int $studentId): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }
        if ($studentId && $this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            $conversation = $studentId
                ? $this->findConversation($studentId)
                : null;

            $messages = [];
            if ($conversation) {
                $stmt = $this->db->prepare(
                    "SELECT im.id, im.conversation_id, im.sender_id, im.subject,
                            im.message_body AS message, im.status, im.created_at,
                            CASE WHEN im.sender_id = :pid THEN 'parent' ELSE 'staff' END AS sender_type,
                            CASE WHEN im.sender_id = :pid THEN 'You'
                                 ELSE (SELECT CONCAT_WS(' ', sp.first_name, sp.last_name)
                                       FROM users su JOIN persons sp ON sp.id = su.person_id
                                       WHERE su.id = im.sender_id)
                            END AS sender_name
                     FROM internal_messages im
                     JOIN internal_conversations c ON c.id = im.conversation_id
                     WHERE c.id = :cid
                     ORDER BY im.created_at DESC
                     LIMIT 100"
                );
                $stmt->execute([':pid' => $this->user_id, ':cid' => (int)$conversation['id']]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $this->markMessagesRead((int)$conversation['id']);
            }

            return $this->successResponse($messages);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Send a parent→school message in the student's conversation.
     *
     * @param array $data {student_id, subject, message}
     * @return array
     */
    public function postSendMessage(array $data): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        $studentId = (int)($data['student_id'] ?? 0);
        $subject   = trim((string)($data['subject'] ?? ''));
        $message   = trim((string)($data['message'] ?? ''));

        if (!$studentId || $subject === '' || $message === '') {
            return $this->errorResponse('student_id, subject, and message required', 400);
        }
        if ($this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            $conversation = $this->getOrCreateConversation($studentId);
            $schoolUser   = $this->resolveSchoolUser($studentId);

            $this->ensureParticipant((int)$conversation['id'], (int)$this->user_id);
            if ($schoolUser) {
                $this->ensureParticipant((int)$conversation['id'], (int)$schoolUser);
            }

            $ins = $this->db->prepare(
                "INSERT INTO internal_messages
                    (conversation_id, sender_id, subject, message_body, message_type, priority, status)
                 VALUES (?, ?, ?, ?, 'personal', 'normal', 'sent')"
            );
            $ins->execute([
                (int)$conversation['id'],
                (int)$this->user_id,
                $subject,
                $message,
            ]);
            $messageId = (int)$this->db->lastInsertId();

            // Link the portal conversation to the canonical communications
            // audit/thread model. The portal remains the source of truth for
            // the conversation UI, while the communication platform records
            // the event and any later email/SMS/WhatsApp alert.
            $eventService = new \App\API\Services\CommunicationBusinessEventService($this->db);
            $eventId = $eventService->getOrCreate(
                'parent_portal_message',
                (int) $conversation['id'] . ':' . $messageId,
                date('Y-m-d H:i:s'),
                (int) $this->user_id
            );
            $threadStmt = $this->db->prepare(
                "SELECT thread_id FROM communication_thread_internal_conversations WHERE conversation_id = ? LIMIT 1"
            );
            $threadStmt->execute([(int) $conversation['id']]);
            $threadId = (int) ($threadStmt->fetchColumn() ?: 0);
            if (!$threadId) {
                $this->db->prepare("INSERT INTO communication_threads (thread_type, subject, created_by) VALUES ('parent_portal', ?, ?)")
                    ->execute([$subject, (int) $this->user_id]);
                $threadId = (int) $this->db->lastInsertId();
                $this->db->prepare("INSERT INTO communication_thread_internal_conversations (thread_id, conversation_id) VALUES (?, ?)")
                    ->execute([$threadId, (int) $conversation['id']]);
            }
            $this->db->prepare(
                "INSERT INTO communication_thread_messages (thread_id, sender_user_id, direction, subject, body) VALUES (?, ?, 'inbound', ?, ?)"
            )->execute([$threadId, (int) $this->user_id, $subject, $message]);
            $this->db->prepare(
                "INSERT INTO communication_event_messages (event_id, internal_message_id) VALUES (?, ?)"
            )->execute([$eventId, $messageId]);
            $this->db->prepare(
                "INSERT INTO communication_audit_events (thread_id, event_type, actor_user_id, rendered_subject, rendered_body) VALUES (?, 'portal_message_received', ?, ?, ?)"
            )->execute([$threadId, (int) $this->user_id, $subject, $message]);
            $eventService->markProcessed($eventId);

            $this->db->prepare(
                "UPDATE internal_conversations
                 SET last_message_at = NOW(), last_message_by = ?
                 WHERE id = ?"
            )->execute([(int)$this->user_id, (int)$conversation['id']]);

            // Unread counter for the school participant (parent reads their own)
            if ($schoolUser) {
                $this->db->prepare(
                    "UPDATE conversation_participants
                     SET unread_count = unread_count + 1
                     WHERE conversation_id = ? AND participant_id = ?"
                )->execute([(int)$conversation['id'], (int)$schoolUser]);

                $nameStmt = $this->db->prepare(
                    "SELECT CONCAT_WS(' ', p.first_name, p.last_name)
                       FROM users u JOIN persons p ON p.id = u.person_id WHERE u.id = ?"
                );
                $nameStmt->execute([(int) $this->user_id]);
                $sender = trim((string) $nameStmt->fetchColumn()) ?: 'a parent or guardian';
                (new NotificationService($this->db))->push(
                    (int) $schoolUser,
                    'message',
                    "New message from {$sender}",
                    NotificationService::messageText($sender, $message),
                    'medium',
                    [
                        'action_url' => 'home.php?route=communications/messages_inbox&conversation_id=' . (int) $conversation['id'],
                        'reference_type' => 'conversation',
                        'reference_id' => (int) $conversation['id'],
                    ]
                );

                $this->db->prepare(
                    "INSERT IGNORE INTO message_read_status (message_id, recipient_id)
                     VALUES (?, ?)"
                )->execute([$messageId, (int)$this->user_id]);
            }

            return $this->successResponse(['message_id' => $messageId], 'Message sent successfully', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Student portfolio + artifacts.
     *
     * @param int $studentId
     * @return array
     */
    public function getPortfolio(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT p.*,
                        (SELECT COUNT(*) FROM portfolio_artifacts WHERE portfolio_id = p.id) AS artifact_count
                 FROM portfolios p
                 WHERE p.student_id = :sid AND p.status = 'active'
                 ORDER BY p.created_date DESC
                 LIMIT 1"
            );
            $stmt->execute([':sid' => $studentId]);
            $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);

            $artifacts = [];
            if ($portfolio) {
                $stmt = $this->db->prepare(
                    "SELECT pa.*, cc.name AS competency_name, cv.name AS value_name
                     FROM portfolio_artifacts pa
                     LEFT JOIN core_competencies cc ON cc.id = pa.competency_id
                     LEFT JOIN core_values cv ON cv.id = pa.value_id
                     WHERE pa.portfolio_id = :pid
                     ORDER BY pa.upload_date DESC"
                );
                $stmt->execute([':pid' => (int)$portfolio['id']]);
                $artifacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->successResponse([
                'portfolio' => $portfolio,
                'artifacts' => $artifacts,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Active grading scale + rules (no hardcoded CBC thresholds).
     *
     * @return array
     */
    public function getGradingScale(): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM grading_scales WHERE status = 'active' ORDER BY id LIMIT 1"
            );
            $stmt->execute();
            $scale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$scale) {
                return $this->successResponse(['scale' => null, 'rules' => []]);
            }

            $stmt = $this->db->prepare(
                "SELECT id, grade_code, grade_name, min_mark, max_mark, grade_points,
                        performance_level, description, sort_order
                 FROM grade_rules
                 WHERE scale_id = :sid
                 ORDER BY sort_order, min_mark DESC"
            );
            $stmt->execute([':sid' => (int)$scale['id']]);
            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse(['scale' => $scale, 'rules' => $rules]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Initiate an M-Pesa STK Push for the student's outstanding balance.
     *
     * @param array $data {student_id, phone?, amount?}
     * @return array
     */
    public function postInitiateMpesaPayment(array $data): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        $studentId = (int)($data['student_id'] ?? 0);
        if (!$studentId) {
            return $this->errorResponse('student_id required', 400);
        }
        if ($this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        try {
            // Get parent's phone (fallback for phone input)
            $parent = $this->getParentProfile($this->parentId);

            // Phone: explicit param or parent's primary phone
            $phone = trim((string)($data['phone'] ?? $parent['phone'] ?? ''));
            // Normalize to 254XXXXXXXXX
            if (strlen($phone) === 9) $phone = '254' . $phone;
            if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                return $this->errorResponse('A valid phone number is required', 400);
            }

            // Get student admission number
            $stmt = $this->db->prepare(
                "SELECT admission_no FROM students WHERE id = :id"
            );
            $stmt->execute([':id' => $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->errorResponse('Student not found', 400);
            }

            // Get current fee balance
            $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(balance), 0) AS total_balance
                 FROM $feeBalView
                 WHERE student_id = :sid"
            );
            $stmt->execute([':sid' => $studentId]);
            $totalBalance = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0);
            if ($totalBalance <= 0) {
                return $this->errorResponse('No outstanding balance to pay', 400);
            }

            // Amount: explicit or full balance
            $amount = (float)($data['amount'] ?? $totalBalance);
            if ($amount <= 0) {
                return $this->errorResponse('Amount must be greater than zero', 400);
            }
            if ($amount > $totalBalance) {
                return $this->errorResponse('Amount exceeds outstanding balance', 400);
            }

            $provider = strtolower(trim((string) ($data['provider'] ?? 'daraja')));
            if (!in_array($provider, ['daraja', 'buni'], true)) {
                return $this->errorResponse('Unsupported payment provider', 400);
            }

            if ($provider === 'buni') {
                $buniAccount = (new FinancialAccountService($this->db))->requireFor(0, 'fees', 'buni_ipn');
                $result = (new KcbMpesaExpressService())->initiate([
                    'phone_number' => $phone,
                    'amount' => $amount,
                    'invoice_number' => $student['admission_no'],
                    'description' => 'School fees',
                    'org_short_code' => (string)$buniAccount['account_identifier'],
                    'callback_url' => rtrim((string) (defined('KCB_CALLBACK_BASE_URL') ? KCB_CALLBACK_BASE_URL : BASE_URL), '/') . '/api/payments/kcb-mpesa-express-callback',
                ]);
                if (!empty($result['accepted'])) {
                    return $this->successResponse([
                        'provider' => 'buni',
                        'checkout_request_id' => $result['checkout_request_id'] ?? null,
                        'merchant_request_id' => $result['merchant_request_id'] ?? null,
                        'message' => 'KCB Buni M-Pesa prompt sent. Check your phone and enter your PIN.',
                    ]);
                }
                return $this->errorResponse($result['message'] ?? 'Failed to initiate Buni payment', 400);
            }

            $result = (new MpesaPaymentService())->initiateSTKPush(
                $student['admission_no'], $phone, $amount,
                'Parent Portal Payment - ' . $student['admission_no']
            );

            if (!empty($result['success'])) {
                $resultData = $result['data'] ?? [];
                return $this->successResponse([
                    'checkout_request_id' => $resultData['checkout_request_id'] ?? null,
                    'merchant_request_id'  => $resultData['merchant_request_id'] ?? null,
                    'provider'             => 'daraja',
                    'message'             => 'M-Pesa STK Push sent. Check your phone and enter PIN.',
                ]);
            }

            return $this->errorResponse($result['message'] ?? 'Failed to initiate M-Pesa payment', 400);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Purpose-aware portal payment. `purpose` in
     * {fees, transport, uniforms}. Fees reuse the existing fee STK flow;
     * transport goes through TransportPaymentService (entitlement-bound) and
     * uniforms through UniformPaymentService (accumulated balance). All paths
     * create provider intents and purpose routing references — money is never
     * guessed from a phone number.
     *
     * @param array $data {student_id, purpose, phone?, amount?, provider?}
     * @return array
     */
    public function postPortalPayment(array $data): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        $studentId = (int)($data['student_id'] ?? 0);
        $purpose   = strtolower(trim((string)($data['purpose'] ?? '')));
        if (!$studentId) {
            return $this->errorResponse('student_id required', 400);
        }
        if (!in_array($purpose, ['fees', 'transport', 'uniforms'], true)) {
            return $this->errorResponse('purpose must be fees, transport, or uniforms', 400);
        }
        if ($this->assertAccess($studentId) !== null) {
            return $this->errorResponse('Access denied', 403);
        }

        if ($purpose === 'fees') {
            // Reuse the exact fee flow above (student_id, phone, amount, provider).
            return $this->postInitiateMpesaPayment($data);
        }

        try {
            // Transport and uniforms share phone normalization.
            $parent = $this->getParentProfile($this->parentId);
            $phone = trim((string)($data['phone'] ?? $parent['phone'] ?? ''));
            if (strlen($phone) === 9) $phone = '254' . $phone;
            if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                return $this->errorResponse('A valid phone number is required', 400);
            }

            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) {
                return $this->errorResponse('Amount must be greater than zero', 400);
            }

            if ($purpose === 'transport') {
                // Resolve the child's active transport entitlement (one row).
                $entStmt = $this->db->prepare(
                    "SELECT te.id, te.amount_due, te.student_id,
                            te.entitlement_status, r.name AS route_name
                     FROM student_transport_entitlements te
                     LEFT JOIN transport_routes r ON r.id = te.route_id
                     WHERE te.student_id = ? AND te.entitlement_status = 'active'
                     ORDER BY te.id DESC LIMIT 1"
                );
                $entStmt->execute([$studentId]);
                $entitlement = $entStmt->fetch(PDO::FETCH_ASSOC);
                if (!$entitlement) {
                    return $this->successResponse([
                        'initiated' => false,
                        'message' => 'No active transport entitlement was found for this learner. The school may not have enrolled them in transport yet.',
                    ]);
                }

                // Amount default = entitlement due; never exceed it.
                $maxAmount = (float) $entitlement['amount_due'];
                if ($amount > $maxAmount) {
                    return $this->errorResponse('Amount exceeds the transport entitlement balance', 400);
                }

                $transport = ServiceContractBroker::contract(
                    'App\API\Services\payments\TransportPaymentService',
                    [],
                    $this->db
                );
                $intent = $transport->initiate([
                    'entitlement_id' => (int) $entitlement['id'],
                    'channel' => 'daraja_mpesa',
                    'amount' => $amount,
                    'phone' => $phone,
                    'financial_account_id' => 0,
                ], (int) $this->user_id);

                return $this->successResponse([
                    'initiated' => true,
                    'purpose' => 'transport',
                    'intent' => [
                        'id' => (int) ($intent['id'] ?? 0),
                        'status' => $intent['status'] ?? null,
                        'reference' => $intent['idempotency_reference'] ?? null,
                        'provider_request_id' => $intent['provider_request_id'] ?? null,
                        'checkout_request_id' => $intent['checkout_request_id'] ?? $intent['data']['checkout_request_id'] ?? null,
                        'message' => 'M-Pesa STK Push sent. Check your phone and enter your PIN.',
                    ],
                ], 'Transport payment initiated');
            }

            // purpose === 'uniforms'
            if ($amount > 0 && $purpose === 'uniforms') {
                $uniforms = ServiceContractBroker::contract(
                    'App\API\Services\payments\UniformPaymentService',
                    [],
                    $this->db
                );
                $intent = $uniforms->initiateAccumulated([
                    'student_id' => $studentId,
                    'parent_id' => $this->parentId,
                    'amount' => $amount,
                    'phone' => $phone,
                    'channel' => 'daraja_mpesa',
                    'financial_account_id' => 0,
                ], (int) $this->user_id);

                return $this->successResponse([
                    'initiated' => true,
                    'purpose' => 'uniforms',
                    'intent' => [
                        'id' => (int) ($intent['id'] ?? 0),
                        'status' => $intent['status'] ?? null,
                        'reference' => $intent['idempotency_reference'] ?? null,
                        'provider_request_id' => $intent['provider_request_id'] ?? null,
                        'checkout_request_id' => $intent['checkout_request_id'] ?? $intent['data']['checkout_request_id'] ?? null,
                        'message' => 'M-Pesa STK Push sent for the uniform balance. Check your phone and enter your PIN.',
                    ],
                ], 'Uniform balance payment initiated');
            }

            return $this->errorResponse('Unsupported purpose', 400);
        } catch (\LogicException $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] payment service: ' . $e->getMessage());
            return $this->errorResponse('The payment service is temporarily unavailable', 503);
        } catch (\RuntimeException $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] payment: ' . $e->getMessage());
            return $this->errorResponse($this->friendlyPaymentMessage($e), 400);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to process the payment', 500);
        }
    }

    /**
     * Downloads for a parent's child: released report cards (each immutable
     * snapshot), plus outstanding transport/uniform balances that drive
     * invoice/statement downloads.
     *
     * @param int $studentId
     * @return array
     */
    public function getDownloads(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            // Released report cards (all versions for permanent access).
            $releaseStmt = $this->db->prepare(
                "SELECT id, version_no, pdf_path, pdf_sha256, released_at
                 FROM report_card_releases
                 WHERE student_id = ? AND status = 'released'
                 ORDER BY released_at DESC, version_no DESC"
            );
            $releaseStmt->execute([$studentId]);
            $reportCards = [];
            foreach ($releaseStmt->fetchAll(PDO::FETCH_ASSOC) as $release) {
                $path = (string) ($release['pdf_path'] ?? '');
                if ($path === '' || !is_file($path)
                    || !hash_equals((string) ($release['pdf_sha256'] ?? ''), (string) @hash_file('sha256', $path))) {
                    continue;
                }
                $reportCards[] = [
                    'release_id' => (int) $release['id'],
                    'version_no' => (int) $release['version_no'],
                    'released_at' => $release['released_at'],
                    'download_url' => (new DownloadService())->generatedDownloadUrlForAbsolutePath($path),
                ];
            }

            // Transport summary (billing status per month).
            $transportView = ReadReplicaService::qualifiedRef('student_transport_summary');
            $trStmt = $this->db->prepare(
                "SELECT bill_id, billing_month, amount_due, amount_paid, balance_due, payment_status
                 FROM $transportView
                 WHERE student_id = ?
                 ORDER BY billing_month DESC
                 LIMIT 6"
            );
            $trStmt->execute([$studentId]);
            $transport = $trStmt->fetchAll(PDO::FETCH_ASSOC);

            // Uniform balance aggregate.
            $uniformView = ReadReplicaService::qualifiedRef('student_uniform_balance');
            $unStmt = $this->db->prepare(
                "SELECT total_billed, total_paid, total_balance, last_purchase
                 FROM $uniformView
                 WHERE student_id = ?
                 LIMIT 1"
            );
            $unStmt->execute([$studentId]);
            $uniform = $unStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            // Fee statement data mirror (used by the frontend to offer a PDF).
            $statement = $this->getStudentStatement($studentId);
            $statementData = $statement['data'] ?? [];

            return $this->successResponse([
                'student' => $this->getStudentInfo($studentId),
                'report_cards' => $reportCards,
                'transport' => $transport,
                'uniform' => $uniform,
                'statement' => $statementData,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] downloads: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to load downloads', 500);
        }
    }

    /**
     * Generate a fee-statement PDF for a parent's child and return its
     * short-lived download URL. Uses the authoritative PrintService data
     * builder (prepareStudentFeeStatement) so the file matches the accounts
     * office.
     *
     * @param int $studentId
     * @return array
     */
    public function postDownloadStatement(int $studentId): array
    {
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $print = new \App\API\Services\PrintService();
            $data = $print->prepareStudentFeeStatement($studentId);
            $path = $print->printFeeStatement($data, [
                'filename' => 'fee_statement_student_' . $studentId . '_parent_' . $this->parentId . '_' . date('Ymd_His'),
            ]);

            $url = (new DownloadService())->generatedDownloadUrlForAbsolutePath($path);
            $this->logStatementDownload($studentId);

            return $this->successResponse([
                'download_url' => $url,
                'filename' => basename($path),
                'generated_at' => date('Y-m-d H:i:s'),
            ], 'Statement generated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] statement PDF: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to generate the fee statement', 500);
        }
    }

    /**
     * Generate a payment-receipt PDF for a confirmed payment belonging to a
     * parent's child.
     *
     * @param array $data {student_id, payment_id}
     * @return array
     */
    public function postDownloadReceipt(array $data): array
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $paymentId = (int) ($data['payment_id'] ?? 0);
        if (!$studentId || !$paymentId) {
            return $this->errorResponse('student_id and payment_id required', 400);
        }
        $access = $this->assertAccess($studentId);
        if ($access !== null) {
            return $access;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT vp.id, vp.receipt_no, vp.reference_no, vp.payment_method,
                        vp.amount_paid AS amount, vp.payment_date, vp.notes,
                        t.name AS term_name
                 FROM vw_payment_transactions_with_amount vp
                 LEFT JOIN academic_year_terms ayt ON ayt.id = vp.term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE vp.id = ? AND vp.student_id = ?
                   AND vp.status IN ('confirmed','completed','success')
                 LIMIT 1"
            );
            $stmt->execute([$paymentId, $studentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$payment) {
                return $this->errorResponse('Payment not found', 404);
            }

            $student = $this->getStudentInfo($studentId);
            $print = new \App\API\Services\PrintService();
            $path = $print->printReceiptTemplate([
                'receiptNo' => $payment['receipt_no'] ?? ('RCP-' . $payment['id']),
                'date' => date('d F Y', strtotime((string) $payment['payment_date'])),
                'receivedFrom' => trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')) . ' (' . ($student['admission_no'] ?? '') . ')',
                'amount' => (float) $payment['amount'],
                'paymentMethod' => $payment['payment_method'] ?? 'M-Pesa',
                'reference' => $payment['reference_no'] ?? '',
                'items' => [['description' => 'School fees payment — ' . ($payment['term_name'] ?? 'Current term'), 'amount' => (float) $payment['amount']]],
                'total' => (float) $payment['amount'],
                'receivedBy' => 'Kingsway Accounts Office',
                'remarks' => $payment['notes'] ?? 'Portal payment',
            ], [
                'filename' => 'receipt_' . $payment['id'] . '_parent_' . $this->parentId . '_' . date('Ymd_His'),
            ]);

            $url = (new DownloadService())->generatedDownloadUrlForAbsolutePath($path);
            $this->logStatementDownload($studentId);

            return $this->successResponse([
                'download_url' => $url,
                'filename' => basename($path),
                'generated_at' => date('Y-m-d H:i:s'),
            ], 'Receipt generated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] receipt PDF: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Unable to generate the receipt', 500);
        }
    }

    /**
     * Human-friendly payment error messages (never leak internals).
     *
     * @param \Throwable $e
     * @return string
     */
    private function friendlyPaymentMessage(\Throwable $e): string
    {
        $message = $e->getMessage();
        $haystack = strtolower((string) $message);
        if (str_contains($haystack, 'exceeds accumulated')) {
            return 'Amount exceeds the accumulated uniform balance.';
        }
        if (str_contains($haystack, 'not found')) {
            return 'The selected payment record could not be found.';
        }
        if (str_contains($haystack, 'must be selected')) {
            return 'No collection account is configured for this payment type yet.';
        }
        return 'The payment could not be initiated. Please review your phone number and amount.';
    }

    /**
     * Poll Safaricom for the transaction status of an STK Push.
     *
     * @param string $checkoutRequestId
     * @return array
     */
    public function getMpesaStatus(string $checkoutRequestId): array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }

        try {
            $mpesa = new MpesaPaymentService();
            $status = $mpesa->queryTransactionStatus($checkoutRequestId);
            $raw = $status['data'] ?? null;

            return $this->successResponse($raw);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Create an opaque portal session in user_sessions and touch users.last_login.
     *
     * @param int $userId
     * @param int $personId
     * @param int $parentId
     * @return array {token, expires_at, csrf_token}
     */
    private function createSession(
        int $userId,
        int $personId,
        int $parentId
    ): array {
        $now   = time();
        $expire = $now + self::PARENT_ACCESS_TOKEN_TTL;

        // Standard HS256 JWT with the same iss/aud/secret as staff tokens so
        // the single AuthMiddleware JWT path authenticates parents exactly
        // like every other user. The Parent role travels as a role claim, and
        // the parent/person identities ride as extra claims for controllers.
        $token = JWT::encode([
            'user_id'   => $userId,
            'id'        => $userId,
            'person_id' => $personId,
            'parent_id' => $parentId,
            'roles'     => [['id' => 73, 'name' => 'Parent']],
            'iat'       => $now,
            'exp'       => $expire,
            'iss'       => JWT_ISSUER,
            'aud'       => JWT_AUDIENCE,
        ], JWT_SECRET, 'HS256');

        $session = new AuthSessionService($this->db);

        // Stores the SHA-256 hash and rotates the user's single active row in
        // place, so the row store matches how staff access tokens are held.
        $this->sessionId = $session->upsertAccessSession(
            $userId,
            $token,
            null,
            date('Y-m-d H:i:s', $expire),
            false
        );

        $this->db->prepare(
            "UPDATE users SET last_login = NOW() WHERE id = ?"
        )->execute([$userId]);

        return [
            'token'      => $token,
            'expires_at' => date('Y-m-d H:i:s', $now + $session->idleTimeoutSeconds()),
            'csrf_token' => $this->generateCsrfToken($userId),
        ];
    }

    /**
     * Generate a CSRF token for parent mutating requests.
     * Must match CsrfMiddleware::validateToken().
     */
    private function generateCsrfToken(int $userId): string
    {
        $timestamp = time();
        $random = bin2hex(random_bytes(16));
        $plaintext = $userId . ':' . $timestamp . ':' . $random;
        $signature = hash_hmac('sha256', $plaintext, JWT_SECRET);
        return base64_encode($plaintext . ':' . $signature);
    }

    /**
     * Parent identity resolved through users → persons → parents (normalised).
     *
     * @param int $userId
     * @return array|null
     */
    private function getParentByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id AS user_id, pr.id AS parent_id, p.id AS person_id,
                    p.first_name, p.last_name, p.email
             FROM users u
             JOIN persons p ON p.id = u.person_id
             JOIN parents pr ON pr.person_id = u.person_id
             WHERE u.id = :uid
               AND u.status = 'active'
               AND pr.status = 'active'
               AND u.data_scope = p.data_scope
               AND EXISTS (
               SELECT 1
               FROM user_roles ur
               JOIN roles r ON r.id = ur.role_id
               WHERE ur.user_id = u.id
                 AND r.id = 73
                 AND r.name = 'Parent'
               )
             LIMIT 1"
        );
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Parent profile for the dashboard / M-Pesa phone fallback.
     *
     * @param int $parentId
     * @return array
     */
    private function getParentProfile(int $parentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.first_name, p.last_name, p.email, p.phone
             FROM parents pr
             JOIN persons p ON p.id = pr.person_id
             WHERE pr.id = :pid"
        );
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Access guard: does this parent have a link to the student?
     *
     * @param int $studentId
     * @return bool
     */
    private function verifyAccess(int $studentId): bool
    {
        try {
            $scopes = DataScopeService::scopes();
            $scopeIn = implode(',', array_fill(0, count($scopes), '?'));
            $stmt = $this->db->prepare(
                "SELECT sp.student_id
                 FROM student_parents sp
                 JOIN students s ON s.id = sp.student_id
                 JOIN persons child_person ON child_person.id = s.person_id
                 WHERE sp.parent_id = ?
                   AND sp.student_id = ?
                   AND child_person.data_scope IN ($scopeIn)
                 LIMIT 1"
            );
            $stmt->execute(array_merge([$this->parentId, $studentId], array_values($scopes)));
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns an errorResponse array when access is denied, else null.
     *
     * @param int $studentId
     * @return array|null
     */
    private function assertAccess(int $studentId): ?array
    {
        if (!$this->parentId) {
            return $this->errorResponse('Not authenticated', 401);
        }
        if (!$this->verifyAccess($studentId)) {
            return $this->errorResponse('Access denied', 403);
        }
        return null;
    }

    /**
     * Current term context from academic_year_terms + terms + academic_years.
     *
     * @return array|null
     */
    private function getCurrentTerm(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.code AS term_number, ay.year_code AS year
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE ayt.status = 'current'
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Calendar bounds for the current academic year (fallback to calendar year).
     *
     * @return array {start, end}
     */
    private function getCurrentYearBounds(): array
    {
        $stmt = $this->db->prepare(
            "SELECT start_date, end_date FROM academic_years WHERE is_current = 1 LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'start' => $row['start_date'] ?? (date('Y') . '-01-01'),
            'end'   => $row['end_date'] ?? (date('Y') . '-12-31'),
        ];
    }

    /**
     * Student info (names on persons, current class/stream via active enrollment).
     *
     * @param int $studentId
     * @return array|null
     */
    private function getStudentInfo(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, ps.first_name, ps.last_name, ps.middle_name,
                    s.admission_no, s.status, ps.gender, ps.dob, ps.photo_url,
                    c.name AS class_name, sn.name AS stream_name
             FROM students s
             JOIN persons ps ON ps.id = s.person_id
             LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN streams sn ON sn.id = aycs.stream_id
             WHERE s.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Fee obligations (per fee type) grouped by year → term, with term-level
     * paid/balance totals from vw_student_fee_balances.
     *
     * @param int $studentId
     * @return array
     */
    private function buildStudentFeesData(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sfo.id, sfo.student_academic_enrollment_id, sfo.academic_year_id,
                    sfo.academic_year_term_id, sfo.academic_year_fee_schedule_id,
                    sfo.amount_due, sfo.status AS obligation_status, sfo.due_date,
                    sfo.is_sponsored, sfo.sponsored_waiver_amount,
                    ay.year_code AS academic_year, t.id AS term_id, t.name AS term_name,
                    t.code AS term_number, 'School Fees' AS fee_type_name, 'SCHOOL_FEES' AS fee_type_code
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             JOIN academic_years ay ON ay.id = sfo.academic_year_id
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_year_fee_schedules ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
             WHERE sae.student_id = :sid
             ORDER BY ay.year_code DESC, t.id ASC"
        );
        $stmt->execute([':sid' => $studentId]);
        $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Term-level paid/balance/waived from the ledger view
        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
        $stmt = $this->db->prepare(
            "SELECT academic_year_term_id,
                    SUM(amount_due)   AS total_due,
                    SUM(amount_waived) AS total_waived,
                    SUM(amount_paid)  AS total_paid,
                    SUM(balance)      AS total_balance
             FROM $feeBalView
             WHERE student_id = :sid
             GROUP BY academic_year_term_id"
        );
        $stmt->execute([':sid' => $studentId]);
        $termTotals = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $termTotals[(int)$t['academic_year_term_id']] = [
                'due'   => (float)($t['total_due'] ?? 0),
                'paid'  => (float)($t['total_paid'] ?? 0),
                'bal'   => (float)($t['total_balance'] ?? 0),
            ];
        }

        // Group obligations by year → term
        $grouped = [];
        foreach ($obligations as $o) {
            $yr  = $o['academic_year'];
            $tid = (int)$o['term_id'];

            if (!isset($grouped[$yr])) {
                $grouped[$yr] = ['year' => $yr, 'terms' => []];
            }
            if (!isset($grouped[$yr]['terms'][$tid])) {
                $grouped[$yr]['terms'][$tid] = [
                    'term_id'      => $tid,
                    'term_name'    => $o['term_name'],
                    'term_number'  => $o['term_number'],
                    'obligations'  => [],
                    'total_due'    => 0,
                    'total_paid'   => 0,
                    'balance'      => 0,
                ];
            }
            $grouped[$yr]['terms'][$tid]['obligations'][] = $o;
        }

        // Allocate term-level paid/balance across obligations proportionally
        foreach ($grouped as &$yData) {
            foreach ($yData['terms'] as &$term) {
                $aytId = null;
                $termDue = 0;
                foreach ($term['obligations'] as $o) {
                    $aytId = (int)$o['academic_year_term_id'];
                    $termDue += (float)$o['amount_due'];
                }

                $paid = 0;
                $bal  = 0;
                if ($aytId !== null && isset($termTotals[$aytId])) {
                    $paid = $termTotals[$aytId]['paid'];
                    $bal  = $termTotals[$aytId]['bal'];
                }

                foreach ($term['obligations'] as &$o) {
                    $amountDue = (float)$o['amount_due'];
                    $rowPaid   = $termDue > 0 ? round($paid * ($amountDue / $termDue), 2) : 0;
                    $waived    = (float)($o['sponsored_waiver_amount'] ?? 0);
                    $rowBal    = max($amountDue - $waived - $rowPaid, 0);

                    $o['amount_paid'] = $rowPaid;
                    $o['balance']     = $rowBal;
                    $o['payment_status'] = $rowBal <= 0 ? 'paid' : ($rowPaid > 0 ? 'partial' : 'pending');
                }
                unset($o);

                $term['total_due']  = $termDue;
                $term['total_paid'] = $paid;
                $term['balance']    = $bal;
            }
            unset($term);
            $yData['terms'] = array_values($yData['terms']);
        }
        unset($yData);

        return array_values($grouped);
    }

    /**
     * Confirmed payment history rows.
     *
     * @param int $studentId
     * @return array
     */
    private function fetchPaymentHistory(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT vp.id, vp.payment_date, vp.payment_method, vp.amount_paid,
                    vp.receipt_no, vp.reference_no, vp.term_id, vp.status,
                    t.name AS term_name
             FROM vw_payment_transactions_with_amount vp
             LEFT JOIN academic_year_terms ayt ON ayt.id = vp.term_id
             LEFT JOIN terms t ON t.id = ayt.term_id
             WHERE vp.student_id = :sid AND vp.status IN ('confirmed','completed','success')
             ORDER BY vp.payment_date DESC
             LIMIT 100"
        );
        $stmt->execute([':sid' => $studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Append-only statement download log (parent_statement_downloads → audit_logs).
     *
     * @param int $studentId
     * @return void
     */
    private function logStatementDownload(int $studentId): void
    {
        try {
            \App\API\Includes\FileLogger::write('audit', [
                'type' => 'audit',
                'action' => 'parent_statement_download',
                'entity' => 'student',
                'entity_id' => $studentId,
                'user_id' => $this->user_id,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'details' => ['parent_id' => $this->parentId, 'student_id' => $studentId],
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    /**
     * Performance/report-card payload builder shared by the two endpoints.
     *
     * @param int  $studentId
     * @param bool $reportCard include school/enrollment context
     * @return array
     */
    private function buildPerformancePayload(int $studentId, bool $reportCard): array
    {
        try {
            $student = $this->getStudentInfo($studentId);
            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $term   = $this->getCurrentTerm();
            $termId = $term ? (int)$term['id'] : 0;

            // Subject scores
            $scores = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT tss.*, la.name AS subject_name, la.code AS subject_code
                     FROM term_subject_scores tss
                     JOIN learning_areas la ON la.id = tss.subject_id
                     WHERE tss.student_id = :sid AND tss.term_id = :tid
                     ORDER BY la.name"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Competency ratings
            $competencies = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT lc.competency_id, lc.performance_level_id, lc.evidence,
                            lc.teacher_notes AS notes,
                            cc.code, cc.name AS competency_name,
                            plc.code AS level_code, plc.name AS level_name
                     FROM learner_competencies lc
                     JOIN core_competencies cc ON cc.id = lc.competency_id
                     LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                     WHERE lc.student_id = :sid AND lc.term_id = :tid"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Core values (learner_values_acquisition carries evidence only)
            $values = [];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT lva.value_id, cv.name AS value_name, lva.evidence
                     FROM learner_values_acquisition lva
                     JOIN core_values cv ON cv.id = lva.value_id
                     WHERE lva.student_id = :sid AND lva.term_id = :tid"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $values = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Attendance context (class register)
            $attendance = ['total_days' => 0, 'days_present' => 0, 'days_absent' => 0, 'days_late' => 0];
            if ($termId) {
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(class_days_marked),0)   AS total_days,
                            COALESCE(SUM(class_days_present),0)  AS days_present,
                            COALESCE(SUM(class_days_absent),0)   AS days_absent,
                            COALESCE(SUM(class_days_late),0)     AS days_late
                     FROM vw_student_term_attendance_summary
                     WHERE student_id = :sid AND term_id = :tid AND register_type = 'class'"
                );
                $stmt->execute([':sid' => $studentId, ':tid' => $termId]);
                $attendance = $stmt->fetch(PDO::FETCH_ASSOC) ?: $attendance;
            }

            $payload = [
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
            ];

            if ($reportCard) {
                $year = $term ? (int)($term['year'] ?? 0) : (int)date('Y');

                // Class teacher name via active stream; comment fields are not
                // stored in the normalised schema (student_academic_enrollments
                // carries no comment columns) — left null for the frontend.
                $enrollment = ['teacher_comments' => null, 'head_teacher_comments' => null, 'class_teacher_name' => null];
                $teacher = $this->getClassTeacher($studentId);
                if ($teacher) {
                    $enrollment['class_teacher_name'] = $teacher;
                }

                $payload['enrollment'] = $enrollment;
                $payload['school']     = $this->getSchoolSettings();
                $payload['year']       = $year;
                $payload['generated_at'] = date('Y-m-d H:i:s');
            }

            return $this->successResponse($payload);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Class teacher full name from the active stream's class_teacher_id.
     *
     * @param int $studentId
     * @return string|null
     */
    private function getClassTeacher(int $studentId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', p.first_name, p.last_name) AS teacher_name
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN staff st ON st.id = aycs.class_teacher_id
             LEFT JOIN persons p ON p.id = st.person_id
             WHERE sae.student_id = :sid AND sae.enrollment_status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId]);
        $name = $stmt->fetchColumn();
        return $name ? (string)$name : null;
    }

    /**
     * School header settings pivoted for the report card.
     *
     * @return array
     */
    private function getSchoolSettings(): array
    {
        $pivoted = [
            'name'     => '',
            'address'  => '',
            'phone'    => '',
            'email'    => '',
            'motto'    => '',
            'logo_url' => null,
        ];

        try {
            $stmt = $this->db->query(
                "SELECT school_name, address, phone, email, motto
                 FROM school_profile LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $pivoted['name']    = $row['school_name'] ?? '';
                $pivoted['address'] = $row['address'] ?? '';
                $pivoted['phone']   = $row['phone'] ?? '';
                $pivoted['email']   = $row['email'] ?? '';
                $pivoted['motto']   = $row['motto'] ?? '';
            }
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        return $pivoted;
    }

    /**
     * Canonical conversation title for a parent×student pair.
     *
     * @param int $studentId
     * @return string
     */
    private function conversationTitle(int $studentId): string
    {
        return 'ParentPortal|' . $this->parentId . '|' . $studentId;
    }

    /**
     * Find an existing conversation for this parent×student pair.
     *
     * @param int $studentId
     * @return array|null
     */
    private function findConversation(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM internal_conversations WHERE title = :title LIMIT 1"
        );
        $stmt->execute([':title' => $this->conversationTitle($studentId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get (or lazily create) the parent×student conversation.
     *
     * @param int $studentId
     * @return array
     */
    private function getOrCreateConversation(int $studentId): array
    {
        $existing = $this->findConversation($studentId);
        if ($existing) {
            return $existing;
        }

        $this->db->prepare(
            "INSERT INTO internal_conversations
                (title, conversation_type, created_by, is_locked, last_message_at, participant_count)
             VALUES (?, 'one_on_one', ?, 0, NOW(), 0)"
        )->execute([
            $this->conversationTitle($studentId),
            (int)$this->user_id,
        ]);

        $id = (int)$this->db->lastInsertId();

        // Parent participant
        $this->ensureParticipant($id, (int)$this->user_id);

        return ['id' => $id];
    }

    /**
     * Ensure a participant row exists for a conversation.
     *
     * @param int $conversationId
     * @param int $participantId
     * @return void
     */
    private function ensureParticipant(int $conversationId, int $participantId): void
    {
        $this->db->prepare(
            "INSERT INTO conversation_participants (conversation_id, participant_id, role)
             VALUES (?, ?, 'participant')
             ON DUPLICATE KEY UPDATE conversation_id = VALUES(conversation_id)"
        )->execute([$conversationId, $participantId]);

        $this->db->prepare(
            "UPDATE internal_conversations
             SET participant_count = (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = ?)
             WHERE id = ?"
        )->execute([$conversationId, $conversationId]);
    }

    /**
     * Resolve a school-side recipient user for the parent's messages: the
     * student's class teacher when they hold a user account, else any active
     * admin/super_admin/administrator user, else the parent user itself.
     *
     * @param int $studentId
     * @return int|null
     */
    private function resolveSchoolUser(int $studentId): ?int
    {
        // Class teacher account first
        $stmt = $this->db->prepare(
            "SELECT u.id
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN staff st ON st.id = aycs.class_teacher_id
             JOIN users u ON u.person_id = st.person_id
             WHERE sae.student_id = :sid AND sae.enrollment_status = 'active' AND u.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        // Any active admin account
        $stmt = $this->db->prepare(
            "SELECT u.id
             FROM users u
             JOIN user_roles ur ON ur.user_id = u.id
             JOIN roles r ON r.id = ur.role_id
             WHERE u.status = 'active'
               AND (r.name = 'admin' OR r.name = 'super_admin' OR r.name = 'administrator')
             ORDER BY r.id
             LIMIT 1"
        );
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int)$id;
        }

        return (int)$this->user_id;
    }

    /**
     * Mark school→parent messages as read by the parent and reset unread count.
     *
     * @param int $conversationId
     * @return void
     */
    private function markMessagesRead(int $conversationId): void
    {
        try {
            $this->db->prepare(
                "UPDATE conversation_participants
                 SET unread_count = 0
                 WHERE conversation_id = ? AND participant_id = ?"
            )->execute([$conversationId, (int)$this->user_id]);

            $this->db->prepare(
                "INSERT IGNORE INTO message_read_status (message_id, recipient_id, read_at, created_at)
                 SELECT im.id, ?, NOW(), NOW()
                 FROM internal_messages im
                 WHERE im.conversation_id = ? AND im.sender_id <> ?
                   AND im.id NOT IN (SELECT mrs.message_id FROM message_read_status mrs
                                     WHERE mrs.recipient_id = ?)"
            )->execute([(int)$this->user_id, $conversationId, (int)$this->user_id, (int)$this->user_id]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ParentPortalManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
    }
}
