<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\Database\Database;
use Exception;

/**
 * ParentPortalController
 * Handles all parent-facing portal endpoints.
 * Uses ParentAuthMiddleware instead of staff JWT auth.
 *
 * ROUTES (all under /api/parent-portal/):
 * POST /api/parent-portal/login                    → postLogin()
 * POST /api/parent-portal/login-otp-request        → postLoginOtpRequest()
 * POST /api/parent-portal/login-otp-verify         → postLoginOtpVerify()
 * POST /api/parent-portal/logout                   → postLogout()
 * GET  /api/parent-portal/dashboard                → getDashboard()
 * GET  /api/parent-portal/student-fees/{id}        → getStudentFees($id)
 * GET  /api/parent-portal/student-payment-history/{id} → getStudentPaymentHistory($id)
 * GET  /api/parent-portal/student-statement/{id}   → getStudentStatement($id)
 * GET  /api/parent-portal/fee-balance/{id}         → getFeeBalance($id)
 */
class ParentPortalController extends BaseController
{
    private int $parentId = 0;

    public function __construct()
    {
        parent::__construct();
        // Override: parent_auth instead of auth_user
        $auth = $_SERVER['parent_auth'] ?? null;
        if ($auth) {
            $this->parentId = (int)($auth['parent_id'] ?? 0);
        }
    }

    // ============================================================
    // AUTH ENDPOINTS (no ParentAuthMiddleware required)
    // ============================================================

    /**
     * POST /api/parent-portal/login
     * Body: {email, password}
     */
    public function postLogin($id = null, $data = [], $segments = [])
    {
        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            return $this->badRequest('Email and password are required');
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT id, first_name, last_name, email, portal_password, portal_status
                 FROM parents WHERE email = :email AND status = 'active' LIMIT 1"
            );
            $stmt->execute([':email' => $email]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$parent || !$parent['portal_password']) {
                return $this->unauthorized('Invalid email or password');
            }
            if ($parent['portal_status'] !== 'active') {
                return $this->forbidden('Portal access is not active for this account');
            }
            if (!password_verify($password, $parent['portal_password'])) {
                return $this->unauthorized('Invalid email or password');
            }

            $token = $this->createSession((int)$parent['id']);

            return $this->success([
                'token'      => $token['token'],
                'expires_at' => $token['expires_at'],
                'parent'     => [
                    'id'         => $parent['id'],
                    'first_name' => $parent['first_name'],
                    'last_name'  => $parent['last_name'],
                    'email'      => $parent['email'],
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError('Login failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/parent-portal/login-otp-request
     * Body: {phone}
     */
    public function postLoginOtpRequest($id = null, $data = [], $segments = [])
    {
        $phone = preg_replace('/\D/', '', $data['phone'] ?? '');
        if (!$phone) return $this->badRequest('Phone number required');

        // Normalize to 254XXXXXXXXX
        if (strlen($phone) === 9) $phone = '254' . $phone;
        if (strlen($phone) === 10 && $phone[0] === '0') $phone = '254' . substr($phone, 1);

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT id FROM parents WHERE (phone_1 = :p1 OR phone_2 = :p2) AND status = 'active' LIMIT 1"
            );
            $stmt->execute([':p1' => $phone, ':p2' => $phone]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$parent) {
                // Return success anyway to prevent phone enumeration
                return $this->success(['message' => 'If this number is registered, an OTP will be sent']);
            }

            $otp     = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmt = $db->prepare(
                "INSERT INTO parent_otp_sessions (parent_id, phone, otp_code, otp_expires_at)
                 VALUES (:pid, :phone, :otp, :exp)"
            );
            $stmt->execute([
                ':pid'   => $parent['id'],
                ':phone' => $phone,
                ':otp'   => password_hash($otp, PASSWORD_DEFAULT),
                ':exp'   => $expires,
            ]);
            $sessionId = $db->lastInsertId();

            // TODO: integrate SMS service here
            // For now, log OTP (remove in production)
            error_log("Parent OTP for {$phone}: {$otp}");

            return $this->success([
                'otp_session_id' => $sessionId,
                'message'        => 'OTP sent to registered phone number',
                'expires_in'     => '10 minutes',
            ]);
        } catch (Exception $e) {
            return $this->serverError('OTP request failed');
        }
    }

    /**
     * POST /api/parent-portal/login-otp-verify
     * Body: {otp_session_id, otp_code}
     */
    public function postLoginOtpVerify($id = null, $data = [], $segments = [])
    {
        $sessionId = (int)($data['otp_session_id'] ?? 0);
        $otpCode   = trim($data['otp_code'] ?? '');

        if (!$sessionId || !$otpCode) {
            return $this->badRequest('otp_session_id and otp_code required');
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT * FROM parent_otp_sessions
                 WHERE id = :id AND otp_expires_at > NOW() AND verified = 0 LIMIT 1"
            );
            $stmt->execute([':id' => $sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->badRequest('OTP session not found or expired');
            }
            if ((int)$session['attempts'] >= 5) {
                return $this->badRequest('Too many failed attempts. Request a new OTP.');
            }

            // Increment attempts
            $db->prepare("UPDATE parent_otp_sessions SET attempts = attempts + 1 WHERE id = :id")
               ->execute([':id' => $sessionId]);

            if (!password_verify($otpCode, $session['otp_code'])) {
                return $this->badRequest('Invalid OTP code');
            }

            // Mark verified
            $db->prepare("UPDATE parent_otp_sessions SET verified = 1 WHERE id = :id")
               ->execute([':id' => $sessionId]);

            // Get parent info
            $stmt = $db->prepare("SELECT id, first_name, last_name, email FROM parents WHERE id = :id");
            $stmt->execute([':id' => $session['parent_id']]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);

            $token = $this->createSession((int)$session['parent_id']);

            return $this->success([
                'token'      => $token['token'],
                'expires_at' => $token['expires_at'],
                'parent'     => $parent,
            ]);
        } catch (Exception $e) {
            return $this->serverError('OTP verification failed');
        }
    }

    /**
     * POST /api/parent-portal/logout
     */
    public function postLogout($id = null, $data = [], $segments = [])
    {
        $auth = $_SERVER['parent_auth'] ?? null;
        if ($auth) {
            try {
                Database::getInstance()
                    ->prepare("UPDATE parent_portal_sessions SET status = 'revoked' WHERE id = :id")
                    ->execute([':id' => $auth['session_id']]);
            } catch (Exception $e) {}
        }
        return $this->success(['message' => 'Logged out successfully']);
    }

    // ============================================================
    // AUTHENTICATED ENDPOINTS (require ParentAuthMiddleware)
    // ============================================================

    /**
     * GET /api/parent-portal/dashboard
     */
    public function getDashboard($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare("
                SELECT s.id, s.first_name, s.last_name, s.admission_no, s.photo_url,
                       c.name AS class_name, sl.name AS level_name,
                       COALESCE(SUM(sfo.balance), 0) AS current_balance,
                       MAX(sfo.payment_status) AS payment_status,
                       (SELECT MAX(pt.payment_date) FROM payment_transactions pt
                        WHERE pt.student_id = s.id AND pt.status = 'confirmed') AS last_payment_date
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id AND s.status = 'active'
                JOIN class_enrollments ce ON ce.student_id = s.id
                JOIN classes c ON ce.class_id = c.id
                JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN student_fee_obligations sfo
                    ON sfo.student_id = s.id
                    AND sfo.academic_year = YEAR(CURDATE())
                WHERE sp.parent_id = :pid
                AND ce.academic_year_id = (SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1)
                GROUP BY s.id
                ORDER BY s.first_name
            ");
            $stmt->execute([':pid' => $this->parentId]);
            $children = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get parent info
            $pStmt = $db->prepare("SELECT first_name, last_name, email, phone_1 FROM parents WHERE id = :id");
            $pStmt->execute([':id' => $this->parentId]);
            $parentInfo = $pStmt->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'parent'   => $parentInfo,
                'children' => $children,
            ]);
        } catch (Exception $e) {
            return $this->serverError('Failed to load dashboard: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/parent-portal/student-fees/{id}
     */
    public function getStudentFees($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare("
                SELECT sfo.*, at.name AS term_name, at.term_number,
                       ft.name AS fee_type_name, ft.code AS fee_type_code
                FROM student_fee_obligations sfo
                JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
                JOIN fee_types ft ON fsd.fee_type_id = ft.id
                JOIN academic_terms at ON sfo.term_id = at.id
                WHERE sfo.student_id = :sid
                ORDER BY sfo.academic_year DESC, at.term_number ASC
            ");
            $stmt->execute([':sid' => $id]);
            $obligations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Group by year → term
            $grouped = [];
            foreach ($obligations as $o) {
                $yr  = $o['academic_year'];
                $tid = $o['term_id'];
                if (!isset($grouped[$yr])) $grouped[$yr] = ['year' => $yr, 'terms' => []];
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
                $grouped[$yr]['terms'][$tid]['total_due']  += $o['amount_due'];
                $grouped[$yr]['terms'][$tid]['total_paid'] += $o['amount_paid'];
                $grouped[$yr]['terms'][$tid]['balance']    += $o['balance'];
            }

            // Re-index
            $result = [];
            foreach ($grouped as $yData) {
                $yData['terms'] = array_values($yData['terms']);
                $result[] = $yData;
            }

            return $this->success(['academic_years' => $result]);
        } catch (Exception $e) {
            return $this->serverError('Failed to load fees');
        }
    }

    /**
     * GET /api/parent-portal/student-payment-history/{id}
     */
    public function getStudentPaymentHistory($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare("
                SELECT pt.*, at.name AS term_name, at.term_number
                FROM payment_transactions pt
                LEFT JOIN academic_terms at ON pt.term_id = at.id
                WHERE pt.student_id = :sid AND pt.status = 'confirmed'
                ORDER BY pt.payment_date DESC
                LIMIT 100
            ");
            $stmt->execute([':sid' => $id]);
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->serverError('Failed to load payment history');
        }
    }

    /**
     * GET /api/parent-portal/student-statement/{id}
     * Returns fee statement data for printing
     */
    public function getStudentStatement($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db = Database::getInstance();

            // Get student info
            $stmt = $db->prepare(
                "SELECT s.*, c.name AS class_name
                 FROM students s
                 LEFT JOIN class_enrollments ce ON ce.student_id = s.id
                 LEFT JOIN classes c ON ce.class_id = c.id
                 WHERE s.id = :id
                 ORDER BY ce.academic_year_id DESC LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Reuse getStudentFees — returns a BaseController response array
            $feesResp = $this->getStudentFees($id, $data, $segments);
            // Extract the academic_years data from the response array
            $feesData = $feesResp['data']['academic_years'] ?? [];

            // Get payments
            $stmt = $db->prepare(
                "SELECT pt.*, at.name AS term_name
                 FROM payment_transactions pt
                 LEFT JOIN academic_terms at ON pt.term_id = at.id
                 WHERE pt.student_id = :sid AND pt.status = 'confirmed'
                 ORDER BY pt.payment_date DESC"
            );
            $stmt->execute([':sid' => $id]);
            $payments = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Log download (non-fatal if table missing)
            try {
                $db->prepare(
                    "INSERT INTO parent_statement_downloads (parent_id, student_id, downloaded_at, ip_address)
                     VALUES (:pid, :sid, NOW(), :ip)"
                )->execute([
                    ':pid' => $this->parentId,
                    ':sid' => $id,
                    ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
                ]);
            } catch (Exception $e) {}

            return $this->success([
                'student'      => $student,
                'fees'         => $feesData,
                'payments'     => $payments,
                'generated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            return $this->serverError('Failed to generate statement');
        }
    }

    /**
     * GET /api/parent-portal/fee-balance/{id}
     */
    public function getFeeBalance($id = null, $data = [], $segments = [])
    {
        if (!$this->parentId) return $this->unauthorized('Not authenticated');
        if (!$id) return $this->badRequest('student_id required');
        if (!$this->verifyAccess((int)$id)) return $this->forbidden('Access denied');

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare("
                SELECT academic_year, term_id,
                       SUM(amount_due) AS total_due,
                       SUM(amount_paid) AS total_paid,
                       SUM(balance) AS balance,
                       MAX(payment_status) AS payment_status
                FROM student_fee_obligations
                WHERE student_id = :sid
                GROUP BY academic_year, term_id
                ORDER BY academic_year DESC, term_id ASC
            ");
            $stmt->execute([':sid' => $id]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalBalance = array_sum(array_column($rows, 'balance'));
            return $this->success(['per_term' => $rows, 'total_balance' => $totalBalance]);
        } catch (Exception $e) {
            return $this->serverError('Failed to load balance');
        }
    }

    // ============================================================
    // HELPERS
    // ============================================================

    private function createSession(int $parentId): array
    {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        Database::getInstance()->prepare("
            INSERT INTO parent_portal_sessions
                (parent_id, session_token, issued_at, expires_at, ip_address, user_agent)
            VALUES (:pid, :tok, NOW(), :exp, :ip, :ua)
        ")->execute([
            ':pid' => $parentId,
            ':tok' => $token,
            ':exp' => $expires,
            ':ip'  => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'  => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);
        return ['token' => $token, 'expires_at' => $expires];
    }

    private function verifyAccess(int $studentId): bool
    {
        try {
            $stmt = Database::getInstance()->prepare(
                "SELECT id FROM student_parents WHERE parent_id = :pid AND student_id = :sid LIMIT 1"
            );
            $stmt->execute([':pid' => $this->parentId, ':sid' => $studentId]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
}
