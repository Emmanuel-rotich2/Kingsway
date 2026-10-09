<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * GateScanService — owns every QR/gate-scan query and write (student lookup,
 * transport verification/recording, exam eligibility, gate check-in). Extracted
 * from ScanController under the strict SQL placement rule: the controller only
 * authenticates, validates input shape and formats the response.
 */
final class GateScanService
{
    public function __construct(private PDO $pdo, private ?\App\API\Modules\transport\StudentTransportEntitlementManager $transportEntitlements = null)
    {
    }

    public function resolveStudent(string $raw): ?array {
        $decoded = json_decode($raw, true);
        $studentId = 0;
        $admissionNo = '';
        $qrToken = '';

        if (is_array($decoded)) {
            $studentId   = 0;
            $admissionNo = (string)($decoded['admission_no'] ?? '');
            $qrToken     = (string)($decoded['token'] ?? $decoded['qr_token'] ?? '');
        } elseif (preg_match('/^(?:KPS|KA|KWA)[-_]?\d{4,10}$/i', $raw)) {
            // Real admission numbers are stored as KPS1002 (and the generator emits
            // KA-<year>-<seq>); the previous /KWA-\d{4}-\d{4,6}/ pattern matched none
            // of them, so every admission-number scan silently degraded to a token.
            $admissionNo = $raw;
        } elseif (preg_match('/^(?:qr_[a-f0-9]{16,}|KWA1\.[A-Za-z0-9_-]{32,})$/i', $raw)) {
            $qrToken = $raw;
        } else {
            // Could be a card number without the KWA prefix, or a raw token
            $qrToken = $raw;
        }

        // Zero-join resolution.
        //
        // This used to be a single 12-table JOIN built on names that do not exist
        // (academic_year_class_stream, class_streams) and on ayt.start_date /
        // ayt.end_date, which academic_year_terms does not define (it has
        // opening_date / closing_date), so this lookup could never return a row.
        //
        // It is now split into simple keyed reads: resolve student_id, validate the
        // ID card, then read the pre-composed mmv_student_directory row (one row per
        // active enrollment, already carrying class, stream, year and current term).
        // No JOIN is evaluated live at request time.
        if ($qrToken !== '' || $admissionNo !== '') {
            $lookupId = 0;

            if ($admissionNo !== '') {
                // admission_no is carried by the projection, so resolve it there.
                $resolve = $this->pdo->prepare(
                    "SELECT student_id FROM " . ReadReplicaService::qualifiedRef('student_directory') . "
                     WHERE admission_no = ? AND enrollment_status = 'active' LIMIT 1"
                );
                $resolve->execute([$admissionNo]);
                $resolved = $resolve->fetchColumn();
                if ($resolved !== false && $resolved !== null) {
                    $lookupId = (int)$resolved;
                }
            }

            if ($lookupId <= 0 && $qrToken !== '') {
                // Card number or QR token -> student_id, honouring card validity so a
                // lost/replaced/revoked or expired card can never scan.
                $byToken = $this->pdo->prepare(
                    "SELECT student_id FROM student_id_cards
                     WHERE (qr_token = ? OR card_number = ?)
                       AND status NOT IN ('lost', 'replaced', 'revoked')
                       AND (expiry_year IS NULL OR expiry_year >= YEAR(CURDATE()))
                     LIMIT 1"
                );
                $byToken->execute([$qrToken, $qrToken]);
                $tokenStudent = $byToken->fetchColumn();
                if ($tokenStudent !== false && $tokenStudent !== null) {
                    $lookupId = (int)$tokenStudent;
                }
            }

            // The original anchor was always a valid, unexpired ID card row, so an
            // admission-number scan with no usable card must not be trusted.
            if ($lookupId <= 0) {
                return null;
            }
            $card = $this->pdo->prepare(
                "SELECT id FROM student_id_cards
                 WHERE student_id = ?
                   AND status NOT IN ('lost', 'replaced', 'revoked')
                   AND (expiry_year IS NULL OR expiry_year >= YEAR(CURDATE()))
                 LIMIT 1"
            );
            $card->execute([$lookupId]);
            if ($card->fetchColumn() === false) {
                return null;
            }
            $studentId = $lookupId;
        }

        if ($studentId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT student_id, admission_no, student_type_id,
                    first_name, last_name, gender, photo_url,
                    student_type_code AS student_type,
                    class_name, stream_name,
                    academic_year_id, year_code AS academic_year,
                    enrollment_id, enrollment_status,
                    term_id, term_code
             FROM ' . ReadReplicaService::qualifiedRef('student_directory') . "
             WHERE student_id = ? AND enrollment_status = 'active' LIMIT 1"
        );
        $stmt->execute([$studentId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function verifyTransport(array $student): array {
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
             FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " sta
             JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " tr ON tr.id = sta.route_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " tmb ON tmb.student_id = sta.student_id
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
                 FROM " . ReadReplicaService::qualifiedRef("student_transport_entitlements") . " e
                 JOIN transport_entitlement_periods ep ON ep.id=e.period_id
                 JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " tr ON tr.id=e.route_id
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

    public function processTransport(array $student, int $operatorId, string $action, string $tripSession): array
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

    public function recordScanEvent(?int $studentId, int $operatorId, string $context, string $action, string $result, ?int $recordId, string $clientReference, ?string $reason): void
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

    public function verifyExam(array $student): array {
        $enrollmentId = (int)($student['enrollment_id'] ?? 0);
        $studentId    = (int)$student['student_id'];
        $termId       = (int)($student['term_id'] ?? 0);
        $enrollmentStatus = $student['enrollment_status'] ?? '';

        // One query: total billed + total paid + upcoming exam count
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(sfo.amount_due), 0) AS total_billed,
                    (SELECT COALESCE(SUM(p.amount), 0)
                     FROM " . ReadReplicaService::qualifiedRef("payments") . " p
                     WHERE p.student_id = ?
                       AND p.status IN ('confirmed','completed','success')
                    ) AS total_paid,
                    (SELECT COUNT(*)
                     FROM " . ReadReplicaService::qualifiedRef("exam_schedules") . " es
                     WHERE es.academic_year_class_stream_id = (
                         SELECT sae2.academic_year_class_stream_id
                         FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae2 WHERE sae2.id = ? LIMIT 1
                     )
                     AND es.academic_year_term_id = ?
                     AND es.status IN ('scheduled','upcoming')
                    ) AS upcoming_exams
             FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " sfo
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

    public function verifyGate(array $student): array {
        $studentId    = (int)$student['student_id'];
        $enrollmentId = (int)($student['enrollment_id'] ?? 0);
        $today        = date('Y-m-d');

        // Query 1: today's attendance + checked-in status
        $stmt = $this->pdo->prepare(
            "SELECT sa.status, sa.check_in_time, sa.check_out_time, sa.register_type,
                    asess.name AS session_name
             FROM " . ReadReplicaService::qualifiedRef("student_attendance") . "
             LEFT JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " asess ON asess.id = sa.session_id
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
             JOIN " . ReadReplicaService::qualifiedRef("parents") . " par ON par.id = ps.parent_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = par.person_id
             WHERE ps.student_id = ? ORDER BY par.is_primary DESC LIMIT 3

             UNION ALL

             SELECT 'transport' AS row_type,
                    NULL AS name, NULL AS phone, NULL AS phone_alt, NULL AS relationship,
                    tmb.amount_due - COALESCE(SUM(tbp.amount), 0) AS balance
             FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " tmb
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

    public function recordAttendance(array $student, int $operatorId, int $sessionId): array {
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
