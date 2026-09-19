<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\transport\StudentTransportEntitlementManager;
use App\Database\Database;
use PDO;

/**
 * Authenticated staff scanning endpoint for school-issued learner cards.
 *
 * QR contents are treated as an opaque credential. The endpoint deliberately
 * does not trust names, balances, or portal URLs encoded in a QR image.
 */
class ScanController extends BaseController {

    private PDO $pdo;
    private StudentTransportEntitlementManager $transportEntitlements;

    public function __construct() {
        parent::__construct();
        $this->pdo = $this->db;
        $this->transportEntitlements = $this->contract('App\API\Modules\transport\StudentTransportEntitlementManager', Database::getInstance()->getConnection());
    }

    /* =========================================================
       POST /api/scan/verify
       Body: { qr_data, context, operator_id, session_id? }
       ========================================================= */

    public function postVerify(int $id = null, array $data = [], array $segments = []): array {
        $qrData     = trim((string)($data['qr_data'] ?? ''));
        $context    = strtolower(trim((string)($data['context'] ?? 'gate')));
        $operatorId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
        $sessionId  = (int)($data['session_id'] ?? 0);
        $action     = strtolower(trim((string)($data['action'] ?? '')));
        $tripSession = strtolower(trim((string)($data['trip_session'] ?? 'morning_pickup')));
        $clientReference = trim((string)($data['client_reference'] ?? ''));

        if (!$this->user || $operatorId <= 0) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'transport_officer', 'admin', 'school_administrator', 'director', 'headteacher', 'deputy_headteacher', 'teacher'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to scan learner cards');
        }

        if ($qrData === '') {
            return ['success' => false, 'message' => 'qr_data is required', 'status' => 400];
        }

        $validContexts = ['transport', 'exam', 'gate', 'attendance'];
        if (!in_array($context, $validContexts, true)) {
            return ['success' => false, 'message' => "Invalid context. Must be: " . implode(', ', $validContexts), 'status' => 400];
        }

        $student = $this->resolveStudent($qrData);
        if (!$student) {
            $this->recordScanEvent(null, $operatorId, $context, $action, 'rejected', null, $clientReference, 'Invalid or inactive learner credential');
            return ['success' => false, 'message' => 'Student not found', 'status' => 404];
        }

        $result = match($context) {
            'transport'  => $this->processTransport($student, $operatorId, $action, $tripSession),
            'exam'       => $this->verifyExam($student),
            'gate'       => $this->verifyGate($student),
            'attendance' => $this->recordAttendance($student, $operatorId, $sessionId),
            default      => ['eligible' => false, 'message' => 'Unknown context'],
        };

        $output = array_merge([
            'success' => true,
            'context' => $context,
            'student' => [
                'id'           => $student['student_id'],
                'admission_no' => $student['admission_no'],
                'first_name'   => $student['first_name'],
                'last_name'    => $student['last_name'],
                'full_name'    => trim($student['first_name'] . ' ' . $student['last_name']),
                'class_name'   => $student['class_name'] ?? '',
                'stream_name'  => $student['stream_name'] ?? '',
                'student_type' => $student['student_type'] ?? '',
            ],
            'scanned_at' => date('Y-m-d H:i:s'),
        ], $result);

        $this->recordScanEvent(
            (int)$student['student_id'],
            $operatorId,
            $context,
            $action,
            !empty($output['eligible']) ? 'accepted' : 'rejected',
            $output['record']['id'] ?? $output['attendance_id'] ?? null,
            $clientReference,
            $output['message'] ?? null
        );

        return $output;
    }

    /* =========================================================
       Resolve student — SINGLE combined query.
       Handles: JSON payload, card number, numeric ID, qr_token.
       ========================================================= */

    private function resolveStudent(string $raw): ?array {
        $decoded = json_decode($raw, true);
        $studentId = 0;
        $admissionNo = '';
        $qrToken = '';

        if (is_array($decoded)) {
            $studentId   = 0;
            $admissionNo = (string)($decoded['admission_no'] ?? '');
            $qrToken     = (string)($decoded['token'] ?? $decoded['qr_token'] ?? '');
        } elseif (preg_match('/^KWA-\d{4}-\d{4,6}$/i', $raw)) {
            $admissionNo = $raw;
        } elseif (preg_match('/^(?:qr_[a-f0-9]{16,}|KWA1\.[A-Za-z0-9_-]{32,})$/i', $raw)) {
            $qrToken = $raw;
        } else {
            // Could be a card number without the KWA prefix, or a raw token
            $qrToken = $raw;
        }

        // Single query: resolve identity + fetch student context in one shot
        // Uses UNION to handle card/token lookup OR direct student lookup efficiently
        if ($qrToken !== '' || $admissionNo !== '') {
            // Card/token/admission lookup path
            $whereClauses = [];
            $params = [];
            if ($qrToken !== '') {
                $whereClauses[] = '(sic.qr_token = ? OR sic.card_number = ?)';
                $params[] = $qrToken;
                $params[] = $qrToken;
            }
            if ($admissionNo !== '') {
                $whereClauses[] = 's.admission_no = ?';
                $params[] = $admissionNo;
            }

            $sql = "SELECT s.id AS student_id, s.admission_no, s.student_type_id,
                           p.first_name, p.last_name, p.gender, p.photo_url,
                           st.code AS student_type,
                           c.name AS class_name, cs.name AS stream_name,
                           ay.id AS academic_year_id, ay.year_code AS academic_year,
                           sae.id AS enrollment_id, sae.enrollment_status,
                           ayt.id AS term_id, t.code AS term_code
                    FROM student_id_cards sic
                    JOIN students s ON s.id = sic.student_id
                    JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_types st ON st.id = s.student_type_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                        AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
                    LEFT JOIN academic_year_class_stream ayucs ON ayucs.id = sae.academic_year_class_stream_id
                    LEFT JOIN class_streams cs ON cs.id = ayucs.class_stream_id
                    LEFT JOIN classes c ON c.id = cs.class_id
                    LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id
                        AND ayt.start_date <= CURDATE() AND ayt.end_date >= CURDATE()
                    LEFT JOIN terms t ON t.id = ayt.term_id
                    WHERE sic.status NOT IN ('lost', 'replaced', 'revoked')
                      AND (" . implode(' OR ', $whereClauses) . ")
                      AND (sic.expiry_year IS NULL OR sic.expiry_year >= YEAR(CURDATE()))
                    LIMIT 1";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } else {
            // Direct student ID lookup
            $stmt = $this->pdo->prepare(
                "SELECT s.id AS student_id, s.admission_no, s.student_type_id,
                        p.first_name, p.last_name, p.gender, p.photo_url,
                        st.code AS student_type,
                        c.name AS class_name, cs.name AS stream_name,
                        ay.id AS academic_year_id, ay.year_code AS academic_year,
                        sae.id AS enrollment_id, sae.enrollment_status,
                        ayt.id AS term_id, t.code AS term_code
                 FROM students s
                 JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id
                     AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
                 LEFT JOIN academic_year_class_stream ayucs ON ayucs.id = sae.academic_year_class_stream_id
                 LEFT JOIN class_streams cs ON cs.id = ayucs.class_stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id
                     AND ayt.start_date <= CURDATE() AND ayt.end_date >= CURDATE()
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE s.id = ? LIMIT 1"
            );
            $stmt->execute([$studentId]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================================================
       TRANSPORT — single combined query (assignment + route + bills)
       ========================================================= */

    private function verifyTransport(array $student): array {
        $studentId = (int)$student['student_id'];
        $month     = (int)date('m');
        $year      = (int)date('Y');
        $monthDate = $year . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01';

        // One query: assignment + route + current month bills
        $stmt = $this->pdo->prepare(
            "SELECT sta.id AS assignment_id, sta.route_id, sta.status AS assignment_status,
                    sta.stop_id, sta.month, sta.year, sta.expected_amount,
                    tr.name AS route_name, tr.fee AS route_fee,
                    tr.start_point, tr.end_point,
                    tr.morning_departure, tr.afternoon_departure,
                    tmb.id AS bill_id, tmb.amount_due, tmb.payment_status,
                    tmb.due_date,
                    COALESCE(SUM(tbp.amount), 0) AS amount_paid
             FROM student_transport_assignments sta
             JOIN transport_routes tr ON tr.id = sta.route_id
             LEFT JOIN transport_monthly_bills tmb ON tmb.student_id = sta.student_id
                 AND tmb.billing_month = ?
             LEFT JOIN transport_bill_payments tbp ON tbp.bill_id = tmb.id
             WHERE sta.student_id = ? AND sta.month = ? AND sta.year = ?
             GROUP BY sta.id, tr.id, tmb.id
             ORDER BY sta.id DESC
             LIMIT 5"
        );
        $stmt->execute([$monthDate, $studentId, $month, $year]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            // A prepaid term/year entitlement may exist before a monthly
            // assignment row is generated. Resolve its route independently;
            // payment authorization is still decided below by the entitlement
            // manager, so this fallback never grants free access.
            $entitlementRoute = $this->pdo->prepare(
                "SELECT e.route_id, tr.name AS route_name, tr.fee AS route_fee,
                        tr.start_point, tr.end_point, tr.morning_departure,
                        tr.afternoon_departure
                 FROM student_transport_entitlements e
                 JOIN transport_entitlement_periods ep ON ep.id=e.period_id
                 JOIN transport_routes tr ON tr.id=e.route_id
                 WHERE e.student_id=? AND e.entitlement_status='active'
                   AND ep.status='open' AND ep.period_start<=CURDATE() AND ep.period_end>=CURDATE()
                 ORDER BY DATEDIFF(ep.period_end, ep.period_start) DESC, e.id DESC LIMIT 1"
            );
            $entitlementRoute->execute([$studentId]);
            $fallback = $entitlementRoute->fetch(PDO::FETCH_ASSOC);
            if ($fallback) {
                return [
                    'eligible' => true,
                    'status' => 'active',
                    'message' => 'Transport assignment found through date-bounded entitlement',
                    'route' => [
                        'id' => (int)$fallback['route_id'], 'name' => $fallback['route_name'] ?? '',
                        'fee' => (float)($fallback['route_fee'] ?? 0), 'start_point' => $fallback['start_point'] ?? '',
                        'end_point' => $fallback['end_point'] ?? '', 'morning_departure' => $fallback['morning_departure'] ?? '',
                        'afternoon_departure' => $fallback['afternoon_departure'] ?? '', 'pickup_stop' => null,
                    ], 'bills' => [], 'summary' => null,
                ];
            }
            return [
                'eligible' => false, 'status' => 'not_assigned',
                'message'  => 'Student is not assigned to any transport route this month',
                'route' => null, 'bills' => [], 'summary' => null,
            ];
        }

        $first = $rows[0];
        $routeStatus = $first['assignment_status'] ?? 'active';
        $isEligible = $routeStatus === 'active';

        $bills = [];
        $totalDue = 0;
        $totalPaid = 0;
        foreach ($rows as $r) {
            if ($r['bill_id']) {
                $due = (float)$r['amount_due'];
                $paid = (float)$r['amount_paid'];
                $bills[] = [
                    'month' => $r['billing_month'] ?? $monthDate,
                    'amount_due' => $due, 'amount_paid' => $paid,
                    'balance' => $due - $paid,
                    'status' => $r['payment_status'] ?? 'pending',
                    'due_date' => $r['due_date'] ?? '',
                ];
                $totalDue += $due;
                $totalPaid += $paid;
            }
        }
        $balance = $totalDue - $totalPaid;

        return [
            'eligible' => $isEligible,
            'status'   => $routeStatus,
            'message'  => $isEligible
                ? "Transport active — Route: {$first['route_name']}"
                : "Transport {$routeStatus} — " . ($first['route_name'] ?? ''),
            'route' => [
                'id'                  => (int)$first['route_id'],
                'name'                => $first['route_name'] ?? '',
                'fee'                 => (float)($first['route_fee'] ?? 0),
                'start_point'         => $first['start_point'] ?? '',
                'end_point'           => $first['end_point'] ?? '',
                'morning_departure'   => $first['morning_departure'] ?? '',
                'afternoon_departure' => $first['afternoon_departure'] ?? '',
                'pickup_stop'         => $first['stop_id'] ?? null,
            ],
            // Transport is billed and settled independently from school fees.
            // Do not disclose financial amounts to a driver/scan operator.
            'bills' => [],
            'summary' => null,
        ];
    }

    /** Record a transport boarding/drop-off scan against the normalized attendance table. */
    private function processTransport(array $student, int $operatorId, string $action, string $tripSession): array
    {
        $allowedActions = ['verify', 'picked_up', 'dropped_off'];
        if ($action === '') {
            $action = 'verify';
        }
        if (!in_array($action, $allowedActions, true) || !in_array($tripSession, ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'], true)) {
            return ['eligible' => false, 'status' => 'invalid_scan_action', 'message' => 'Select a valid transport action and trip session'];
        }

        $result = $this->verifyTransport($student);
        $routeId = (int)($result['route']['id'] ?? 0);
        if ($routeId <= 0) {
            return ['eligible' => false, 'status' => 'not_assigned', 'message' => 'Student has no active transport assignment'];
        }

        $access = $this->transportEntitlements->getAccess((int)$student['student_id'], $routeId, date('Y-m-d'));
        $result['assignment_status'] = $result['status'] ?? 'active';
        $result['payment_status'] = $access['payment_status'];
        $result['payment_balance'] = $access['balance'];
        $result['entitlement_period'] = $access['period_type'];
        $result['entitlement_start'] = $access['period_start'] ?? null;
        $result['entitlement_end'] = $access['period_end'] ?? null;
        $result['boarding_decision'] = $access['decision'];
        $result['eligible'] = in_array($access['decision'], ['approved', 'authorized_override'], true);

        if ($action === 'verify') {
            $result['message'] = $result['eligible']
                ? 'Transport access approved — valid payment coverage'
                : 'Transport access denied — no valid payment coverage';
            return $result;
        }

        if ($action === 'dropped_off' && !$result['eligible']) {
            $prior = $this->pdo->prepare("SELECT id FROM student_transport_attendance WHERE student_id=? AND attendance_date=CURDATE() AND trip_session=? AND status='picked_up' LIMIT 1");
            $prior->execute([(int)$student['student_id'], $tripSession]);
            if ($prior->fetchColumn()) {
                $result['eligible'] = true;
                $result['boarding_decision'] = 'dropoff_allowed';
            }
        }

        if (!$result['eligible']) {
            $result['status'] = 'boarding_denied';
            $result['message'] = 'Boarding denied — no valid transport payment coverage';
            return $result;
        }

        $status = $action === 'picked_up' ? 'picked_up' : 'dropped_off';
        try {
            $this->pdo->beginTransaction();
            $dayBalance = $this->transportEntitlements->consumeSchoolDay((int)$student['student_id'], $routeId, date('Y-m-d'));
        $stmt = $this->pdo->prepare(
            "INSERT INTO student_transport_attendance
                (student_id, route_id, attendance_date, trip_session, status, marked_time, marked_by)
             VALUES (?, ?, CURDATE(), ?, ?, CURTIME(), ?)
             ON DUPLICATE KEY UPDATE route_id = VALUES(route_id), status = VALUES(status),
                 marked_time = CURTIME(), marked_by = VALUES(marked_by)"
        );
        $stmt->execute([(int)$student['student_id'], $routeId, $tripSession, $status, $operatorId]);
        $recordId = (int)$this->pdo->lastInsertId();
        if ($recordId <= 0) {
            $lookup = $this->pdo->prepare("SELECT id FROM student_transport_attendance WHERE student_id = ? AND attendance_date = CURDATE() AND trip_session = ? LIMIT 1");
            $lookup->execute([(int)$student['student_id'], $tripSession]);
            $recordId = (int)$lookup->fetchColumn();
        }
            $this->pdo->commit();
        } catch (\InvalidArgumentException $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $result['eligible'] = false;
            $result['status'] = 'boarding_denied';
            $result['message'] = $error->getMessage();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }

        $result['status'] = $status;
        $result['message'] = $action === 'picked_up' ? 'Student boarding recorded — payment coverage verified' : 'Student drop-off recorded';
        $result['attendance_id'] = $recordId;
        $result['allocated_school_days'] = $dayBalance['allocated_school_days'] ?? null;
        $result['used_school_days'] = $dayBalance['used_school_days'] ?? null;
        $result['remaining_school_days'] = $dayBalance['remaining_school_days'] ?? null;
        $result['attendance_marked'] = true;
        return $result;
    }

    private function recordScanEvent(?int $studentId, int $operatorId, string $context, string $action, string $result, ?int $recordId, string $clientReference, ?string $reason): void
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO qr_scan_events
                    (student_id, operator_user_id, context, action, result, record_id, client_reference, reason, scanned_at)
                 VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NOW())"
            );
            $stmt->execute([$studentId, $operatorId, $context, $action ?: 'verify', $result, $recordId, $clientReference, $reason]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[ScanController] scan audit failed: ' . $e->getMessage());
        }
    }

    /* =========================================================
       EXAM — single combined query (fee clearance + exam count)
       ========================================================= */

    private function verifyExam(array $student): array {
        $enrollmentId = (int)($student['enrollment_id'] ?? 0);
        $studentId    = (int)$student['student_id'];
        $termId       = (int)($student['term_id'] ?? 0);
        $enrollmentStatus = $student['enrollment_status'] ?? '';

        // One query: total billed + total paid + upcoming exam count
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(sfo.amount_due), 0) AS total_billed,
                    (SELECT COALESCE(SUM(p.amount), 0)
                     FROM payments p
                     WHERE p.student_id = ?
                       AND p.status IN ('confirmed','completed','success')
                    ) AS total_paid,
                    (SELECT COUNT(*)
                     FROM exam_schedules es
                     WHERE es.academic_year_class_stream_id = (
                         SELECT sae2.academic_year_class_stream_id
                         FROM student_academic_enrollments sae2 WHERE sae2.id = ? LIMIT 1
                     )
                     AND es.academic_year_term_id = ?
                     AND es.status IN ('scheduled','upcoming')
                    ) AS upcoming_exams
             FROM student_fee_obligations sfo
             WHERE sfo.student_academic_enrollment_id = ?"
        );
        $stmt->execute([$studentId, $enrollmentId, $termId, $enrollmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalBilled = (float)($row['total_billed'] ?? 0);
        $totalPaid   = (float)($row['total_paid'] ?? 0);
        $balance     = max($totalBilled - $totalPaid, 0);
        $isCleared   = $balance <= 0;
        $isEligible  = $isCleared && $enrollmentStatus === 'active';

        $reasons = [];
        if (!$isCleared) $reasons[] = "Outstanding balance: KES " . number_format($balance, 2);
        if ($enrollmentStatus !== 'active') $reasons[] = "Enrollment: {$enrollmentStatus}";

        return [
            'eligible' => $isEligible,
            'status'   => $isCleared ? 'cleared' : 'outstanding',
            'message'  => $isEligible
                ? "Fee cleared — eligible for exams"
                : "NOT eligible — " . implode('; ', $reasons),
            'fee_summary' => [
                'total_billed' => $totalBilled,
                'total_paid'   => $totalPaid,
                'balance'      => $balance,
                'clearance'    => $isCleared ? 'cleared' : 'outstanding',
            ],
            'enrollment' => [
                'status' => $enrollmentStatus,
                'class'  => $student['class_name'] ?? '',
                'stream' => $student['stream_name'] ?? '',
                'year'   => $student['academic_year'] ?? '',
                'term'   => $student['term_code'] ?? '',
            ],
            'upcoming_exams' => (int)($row['upcoming_exams'] ?? 0),
        ];
    }

    /* =========================================================
       GATE — two queries (attendance + parents), transport is cached
       ========================================================= */

    private function verifyGate(array $student): array {
        $studentId    = (int)$student['student_id'];
        $enrollmentId = (int)($student['enrollment_id'] ?? 0);
        $today        = date('Y-m-d');

        // Query 1: today's attendance + checked-in status
        $stmt = $this->pdo->prepare(
            "SELECT sa.status, sa.check_in_time, sa.check_out_time, sa.register_type,
                    asess.name AS session_name
             FROM student_attendance sa
             LEFT JOIN attendance_sessions asess ON asess.id = sa.session_id
             WHERE sa.student_academic_enrollment_id = ? AND sa.date = ?
             ORDER BY sa.check_in_time DESC"
        );
        $stmt->execute([$enrollmentId, $today]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $checkedIn = false;
        foreach ($attendance as $a) {
            if ($a['status'] === 'present' && $a['check_in_time']) { $checkedIn = true; break; }
        }

        // Query 2: parents + transport balance (combined)
        $stmt = $this->pdo->prepare(
            "SELECT 'parent' AS row_type,
                    CONCAT(p.first_name, ' ', p.last_name) AS name,
                    p.phone_primary AS phone, p.phone_secondary AS phone_alt,
                    par.relationship_to_student AS relationship,
                    NULL AS balance
             FROM parent_student ps
             JOIN parents par ON par.id = ps.parent_id
             JOIN persons p ON p.id = par.person_id
             WHERE ps.student_id = ? ORDER BY par.is_primary DESC LIMIT 3

             UNION ALL

             SELECT 'transport' AS row_type,
                    NULL AS name, NULL AS phone, NULL AS phone_alt, NULL AS relationship,
                    tmb.amount_due - COALESCE(SUM(tbp.amount), 0) AS balance
             FROM transport_monthly_bills tmb
             LEFT JOIN transport_bill_payments tbp ON tbp.bill_id = tmb.id
             WHERE tmb.student_id = ? AND tmb.billing_month = ?-01
             GROUP BY tmb.id LIMIT 1"
        );
        $stmt->execute([$studentId, $studentId, date('Y-m')]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $parents = [];
        $transportBalance = 0;
        foreach ($rows as $r) {
            if ($r['row_type'] === 'parent') {
                $parents[] = [
                    'name' => $r['name'] ?? '', 'phone' => $r['phone'] ?? '',
                    'phone_alt' => $r['phone_alt'] ?? '', 'relationship' => $r['relationship'] ?? '',
                ];
            } else {
                $transportBalance = (float)$r['balance'];
            }
        }

        return [
            'eligible' => true, 'status' => 'authorized',
            'message'  => 'Student authorized for entry',
            'today_attendance' => array_map(fn($a) => [
                'session' => $a['session_name'] ?? '', 'status' => $a['status'],
                'check_in' => $a['check_in_time'] ?? '', 'check_out' => $a['check_out_time'] ?? '',
                'register' => $a['register_type'] ?? '',
            ], $attendance),
            'checked_in_today' => $checkedIn,
            // Parent contact details and balances are not scan-screen data.
            // They remain available through the authorized student/gate workflow.
            'parents' => [],
            'transport_balance' => 0,
        ];
    }

    /* =========================================================
       ATTENDANCE — INSERT IGNORE (single query, no race condition)
       ========================================================= */

    private function recordAttendance(array $student, int $operatorId, int $sessionId): array {
        $enrollmentId = (int)($student['enrollment_id'] ?? 0);
        $today = date('Y-m-d');
        $now   = date('H:i:s');

        // Resolve session ID if not provided (cached in static for repeat scans)
        if ($sessionId <= 0) {
            static $defaultSessionId = null;
            if ($defaultSessionId === null) {
                $stmt = $this->pdo->prepare(
                    "SELECT id FROM attendance_sessions
                     WHERE code = 'MORNING_CLASS' AND status = 'active' LIMIT 1"
                );
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $defaultSessionId = $row ? (int)$row['id'] : 1;
            }
            $sessionId = $defaultSessionId;
        }

        // INSERT IGNORE — if already marked, affected_rows = 0, we fetch the existing row
        // This eliminates the SELECT-then-INSERT race condition entirely.
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO student_attendance
             (student_academic_enrollment_id, date, session_id, status, check_in_time, marked_by, register_type)
             VALUES (?, ?, ?, 'present', ?, ?, 'class')"
        );
        $stmt->execute([$enrollmentId, $today, $sessionId, $now, $operatorId ?: null]);
        $inserted = $stmt->rowCount();

        if ($inserted > 0) {
            $newId = (int)$this->pdo->lastInsertId();
            return [
                'eligible' => true, 'status' => 'present',
                'message'  => "Attendance recorded — {$student['first_name']} marked present at {$now}",
                'record' => ['id' => $newId, 'status' => 'present', 'check_in' => $now, 'date' => $today],
                'attendance_marked' => true,
            ];
        }

        // Already existed — fetch it
        $stmt = $this->pdo->prepare(
            "SELECT id, status, check_in_time FROM student_attendance
             WHERE student_academic_enrollment_id = ? AND date = ? AND session_id = ?
               AND register_type = 'class' LIMIT 1"
        );
        $stmt->execute([$enrollmentId, $today, $sessionId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'eligible' => true, 'status' => 'already_recorded',
            'message'  => "Attendance already recorded for today (" . ($existing['status'] ?? 'present') . ")",
            'record' => [
                'id' => (int)($existing['id'] ?? 0),
                'status' => $existing['status'] ?? 'present',
                'check_in' => $existing['check_in_time'] ?? '',
                'date' => $today,
            ],
            'attendance_marked' => false,
        ];
    }
}
