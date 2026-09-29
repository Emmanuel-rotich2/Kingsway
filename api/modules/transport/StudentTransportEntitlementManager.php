<?php
declare(strict_types=1);

namespace App\API\Modules\transport;

use PDO;
use RuntimeException;
use App\API\Services\FinancialPostingCoordinator;

/**
 * Transport access is based on date-bounded entitlements, not on a monthly
 * subscription flag. School fees never participate in these calculations.
 */
class StudentTransportEntitlementManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function ensureMonthlyEntitlement(int $studentId, int $assignmentId, int $routeId, string $month, float $amount, ?int $userId = null): int
    {
        $start = date('Y-m-01', strtotime($month));
        $end = date('Y-m-t', strtotime($start));
        $allocatedDays = max(1, $this->countSchoolDays($start, $end));
        $periodId = $this->ensurePeriod('month', $start, $end, null, date('F Y', strtotime($start)), $userId);
        $stmt = $this->db->prepare(
            "INSERT INTO student_transport_entitlements
                (student_id, assignment_id, route_id, period_id, amount_due, allocated_school_days, source_type, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'subscription', ?)
             ON DUPLICATE KEY UPDATE amount_due = VALUES(amount_due), allocated_school_days=VALUES(allocated_school_days), entitlement_status='active'"
        );
        $stmt->execute([$studentId, $assignmentId ?: null, $routeId, $periodId, $amount, $allocatedDays, $userId]);
        if ($stmt->rowCount() > 0) return (int)$this->db->lastInsertId();
        $lookup = $this->db->prepare("SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=? LIMIT 1");
        $lookup->execute([$studentId, $routeId, $periodId]);
        return (int)$lookup->fetchColumn();
    }

    public function createEntitlement(array $data, int $userId): array
    {
        $studentId = (int)($data['student_id'] ?? 0);
        $routeId = (int)($data['route_id'] ?? 0);
        $type = strtolower(trim((string)($data['period_type'] ?? '')));
        $start = (string)($data['period_start'] ?? '');
        $end = (string)($data['period_end'] ?? '');
        $amount = (float)($data['amount_due'] ?? 0);
        $allocatedDays = max(1, (int)($data['allocated_school_days'] ?? $this->countSchoolDays($start, $end)));
        if (!$studentId || !$routeId || !$start || !$end || $amount < 0) throw new RuntimeException('student_id, route_id, dates and amount_due are required');
        if (!in_array($type, ['day','week','month','term','year','custom'], true)) throw new RuntimeException('Invalid transport entitlement period');
        if ($end < $start) throw new RuntimeException('period_end must not be before period_start');

        $assignment = $this->db->prepare("SELECT id FROM student_transport_assignments WHERE student_id=? AND route_id=? AND status IN ('active','suspended') ORDER BY id DESC LIMIT 1");
        $assignment->execute([$studentId, $routeId]);
        $assignmentId = (int)($assignment->fetchColumn() ?: 0);
        $periodId = $this->ensurePeriod($type, $start, $end, !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null, $data['label'] ?? null, $userId);
        $stmt = $this->db->prepare(
            "INSERT INTO student_transport_entitlements
                (student_id, assignment_id, route_id, period_id, amount_due, allocated_school_days, source_type, created_by)
             VALUES (?, NULLIF(?, 0), ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE amount_due=VALUES(amount_due), allocated_school_days=VALUES(allocated_school_days), entitlement_status='active'"
        );
        $source = in_array(($data['source_type'] ?? 'prepaid'), ['subscription','prepaid','bursary','waiver','override'], true) ? $data['source_type'] : 'prepaid';
        $stmt->execute([$studentId, $assignmentId, $routeId, $periodId, $amount, $allocatedDays, $source, $userId]);
        $id = (int)$this->db->lastInsertId();
        if (!$id) {
            $q = $this->db->prepare("SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=? LIMIT 1");
            $q->execute([$studentId, $routeId, $periodId]);
            $id = (int)$q->fetchColumn();
        }
        return $this->getEntitlement($id);
    }

    /**
     * Enrol a learner for transport in one transaction. The administrator
     * chooses the route, pickup/dropoff points, exact dates, period type, and
     * agreed amount. No distance or automatic tariff is assumed.
     */
    public function enrollStudent(array $data, int $userId): array
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $routeId = (int) ($data['route_id'] ?? 0);
        $pickupStopId = (int) ($data['pickup_stop_id'] ?? $data['stop_id'] ?? 0);
        $dropoffStopId = (int) ($data['dropoff_stop_id'] ?? $data['stop_id'] ?? 0);
        $type = strtolower(trim((string) ($data['period_type'] ?? '')));
        $start = (string) ($data['period_start'] ?? '');
        $end = (string) ($data['period_end'] ?? '');
        $amount = (float) ($data['amount_due'] ?? 0);
        $allocatedDays = max(1, (int)($data['allocated_school_days'] ?? $this->countSchoolDays($start, $end)));

        // New subscriptions define a length, not hand-entered dates or an
        // arbitrary school-day count. Resolve the dates against the school's
        // configured calendar on the server before writing the entitlement.
        if (!empty($data['duration_unit'])) {
            $window = $this->deriveSubscriptionWindow($data);
            $type = $window['period_type'];
            $start = $window['period_start'];
            $end = $window['period_end'];
            $allocatedDays = $window['allocated_school_days'];
            $data['academic_year_term_id'] = $window['academic_year_term_id'];
            $data['label'] = $window['label'];
        } else {
            // Even legacy callers cannot store a day count that disagrees
            // with the configured academic calendar.
            $allocatedDays = $this->countSchoolDays($start, $end);
        }

        if (!$studentId || !$routeId || !$pickupStopId || !$dropoffStopId || !$start || !$end) {
            throw new RuntimeException('student_id, route_id, pickup/dropoff stops, period dates and amount_due are required');
        }
        if ($amount < 0 || $end < $start) throw new RuntimeException('Invalid transport amount or date range');
        if (!in_array($type, ['day', 'week', 'month', 'term', 'year', 'custom'], true)) {
            throw new RuntimeException('Invalid transport period type');
        }

        $stopCheck = $this->db->prepare(
            'SELECT COUNT(*) FROM transport_stops WHERE route_id=? AND id IN (?,?) AND status=\'active\''
        );
        $stopCheck->execute([$routeId, $pickupStopId, $dropoffStopId]);
        if ((int) $stopCheck->fetchColumn() !== ($pickupStopId === $dropoffStopId ? 1 : 2)) {
            throw new RuntimeException('Pickup and dropoff points must belong to the selected route and be active');
        }

        $this->db->beginTransaction();
        try {
            $periodId = $this->ensurePeriod(
                $type,
                $start,
                $end,
                !empty($data['academic_year_term_id']) ? (int) $data['academic_year_term_id'] : null,
                $data['label'] ?? null,
                $userId
            );

            $month = (int) date('n', strtotime($start));
            $year = (int) date('Y', strtotime($start));
            $assignment = $this->db->prepare(
                'SELECT id FROM student_transport_assignments WHERE student_id=? AND month=? AND year=? ORDER BY id DESC LIMIT 1 FOR UPDATE'
            );
            $assignment->execute([$studentId, $month, $year]);
            $assignmentId = (int) ($assignment->fetchColumn() ?: 0);
            if ($assignmentId) {
                $this->db->prepare(
                    "UPDATE student_transport_assignments
                     SET route_id=?, stop_id=?, pickup_stop_id=?, dropoff_stop_id=?,
                         expected_amount=?, assignment_date=?, assigned_by=?, notes=?, status='active'
                     WHERE id=?"
                )->execute([$routeId, $pickupStopId, $pickupStopId, $dropoffStopId, $amount, $start, $userId, $data['notes'] ?? null, $assignmentId]);
            } else {
                $insertAssignment = $this->db->prepare(
                    "INSERT INTO student_transport_assignments
                     (student_id,route_id,stop_id,pickup_stop_id,dropoff_stop_id,assignment_date,assigned_by,notes,month,year,expected_amount,status)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,'active')"
                );
                $insertAssignment->execute([$studentId, $routeId, $pickupStopId, $pickupStopId, $dropoffStopId, $start, $userId, $data['notes'] ?? null, $month, $year, $amount]);
                $assignmentId = (int) $this->db->lastInsertId();
            }

            $entitlement = $this->db->prepare(
                "INSERT INTO student_transport_entitlements
                 (student_id,assignment_id,route_id,period_id,amount_due,allocated_school_days,source_type,created_by)
                 VALUES (?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE assignment_id=VALUES(assignment_id), amount_due=VALUES(amount_due), allocated_school_days=VALUES(allocated_school_days), entitlement_status='active'"
            );
            $source = in_array(($data['source_type'] ?? 'subscription'), ['subscription','prepaid','bursary','waiver','override'], true)
                ? $data['source_type'] : 'subscription';
            $entitlement->execute([$studentId, $assignmentId, $routeId, $periodId, $amount, $allocatedDays, $source, $userId]);
            $id = (int) $this->db->lastInsertId();
            if (!$id) {
                $lookup = $this->db->prepare('SELECT id FROM student_transport_entitlements WHERE student_id=? AND route_id=? AND period_id=?');
                $lookup->execute([$studentId, $routeId, $periodId]);
                $id = (int) $lookup->fetchColumn();
            }
            $this->db->commit();
            return $this->getEntitlement($id) + ['assignment_id' => $assignmentId, 'pickup_stop_id' => $pickupStopId, 'dropoff_stop_id' => $dropoffStopId];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function recordPayment(int $entitlementId, array $data, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT e.*, p.period_start, p.period_end
                 FROM student_transport_entitlements e JOIN transport_entitlement_periods p ON p.id=e.period_id
                 WHERE e.id=? AND e.entitlement_status='active' FOR UPDATE"
            );
            $stmt->execute([$entitlementId]);
            $e = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$e) throw new RuntimeException('Transport entitlement not found');
            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) throw new RuntimeException('Payment amount must be positive');
            $method = strtolower((string)($data['payment_method'] ?? 'cash'));
            if (in_array($method, ['mpesa', 'daraja_mpesa', 'buni_mpesa'], true) && empty($data['verified_provider_callback'])) {
                throw new RuntimeException('Mobile-money transport payments must be allocated from a verified provider confirmation');
            }
            $paidStmt = $this->db->prepare("SELECT COALESCE(SUM(a.amount),0) FROM transport_entitlement_payment_allocations a JOIN transport_entitlement_payments p ON p.id=a.payment_id AND p.payment_status='confirmed' WHERE a.entitlement_id=?");
            $paidStmt->execute([$entitlementId]);
            $remaining = max(0, (float)$e['amount_due'] - (float)$paidStmt->fetchColumn());
            if ($amount > $remaining && $remaining > 0 && empty($data['allow_credit'])) throw new RuntimeException('Payment exceeds this entitlement balance');
            $ref = trim((string)($data['provider_reference'] ?? '')) ?: null;
            $insert = $this->db->prepare("INSERT INTO transport_entitlement_payments (student_id,financial_account_id,amount,payment_method,provider_name,provider_reference,payment_status,payment_date,received_by,notes) VALUES (?,?,?,?,?,?,'confirmed',?,?,?)");
            $insert->execute([(int)$e['student_id'], (int)($data['financial_account_id'] ?? 0) ?: null, $amount, $method, $data['provider_name'] ?? 'manual', $ref, $data['payment_date'] ?? date('Y-m-d'), $userId ?: null, $data['notes'] ?? null]);
            $paymentId = (int)$this->db->lastInsertId();
            $allocation = $this->db->prepare("INSERT INTO transport_entitlement_payment_allocations (payment_id,entitlement_id,amount) VALUES (?,?,?)");
            $allocation->execute([$paymentId, $entitlementId, $amount]);
            if (!empty($data['financial_account_id'])) {
                (new FinancialPostingCoordinator($this->db))->postIncoming(
                    'transport_entitlement_payment',
                    $paymentId,
                    (int) $data['financial_account_id'],
                    'transport',
                    number_format($amount, 2, '.', ''),
                    $userId,
                    $ref
                );
            }
            $this->db->commit();
            return ['payment_id'=>$paymentId, 'entitlement_id'=>$entitlementId, 'amount'=>$amount, 'remaining_balance'=>max(0,$remaining-$amount), 'period_start'=>$e['period_start'], 'period_end'=>$e['period_end']];
        } catch (\Throwable $error) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $error; }
    }

    public function getAccess(int $studentId, int $routeId, string $date): array
    {
        $override = $this->db->prepare("SELECT id FROM transport_access_overrides WHERE student_id=? AND route_id=? AND status='active' AND valid_from<=? AND valid_until>=? LIMIT 1");
        $at = $date . ' ' . date('H:i:s');
        $override->execute([$studentId, $routeId, $at, $at]);
        if ($override->fetchColumn()) return ['decision'=>'authorized_override','payment_status'=>'waived','balance'=>0,'period_type'=>'override'];

        $stmt = $this->db->prepare(
            "SELECT e.id, e.amount_due, e.allocated_school_days, e.source_type, p.period_type, p.period_start, p.period_end,
                    (SELECT COUNT(*) FROM student_transport_day_usage u WHERE u.entitlement_id=e.id) AS used_school_days,
                    EXISTS(SELECT 1 FROM student_transport_day_usage u WHERE u.entitlement_id=e.id AND u.usage_date=?) AS used_today,
                    COALESCE(SUM(CASE WHEN tp.payment_status='confirmed' THEN a.amount ELSE 0 END),0) paid
             FROM student_transport_entitlements e
             JOIN transport_entitlement_periods p ON p.id=e.period_id
             LEFT JOIN transport_entitlement_payment_allocations a ON a.entitlement_id=e.id
             LEFT JOIN transport_entitlement_payments tp ON tp.id=a.payment_id
             WHERE e.student_id=? AND e.route_id=? AND e.entitlement_status='active'
               AND p.status='open' AND p.period_start<=? AND p.period_end>=?
             GROUP BY e.id, e.amount_due, e.allocated_school_days, e.source_type, p.period_type, p.period_start, p.period_end
             ORDER BY DATEDIFF(p.period_end,p.period_start) DESC, e.id DESC"
        );
        $stmt->execute([$date, $studentId, $routeId, $date, $date]);
        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $partial = null;
        foreach ($candidates as $row) {
            $due = (float)$row['amount_due']; $paid = (float)$row['paid'];
            $remainingDays = max(0, (int)$row['allocated_school_days'] - (int)$row['used_school_days']);
            $coverageAvailable = $remainingDays > 0 || (bool)$row['used_today'];
            if ($coverageAvailable && (in_array($row['source_type'], ['waiver', 'bursary'], true) || $due <= 0 || $paid >= $due)) return ['decision'=>'approved','payment_status'=>in_array($row['source_type'], ['waiver', 'bursary'], true) ? $row['source_type'] : ($due<=0?'waived':'paid'),'balance'=>max(0,$due-$paid),'period_type'=>$row['period_type'],'period_start'=>$row['period_start'],'period_end'=>$row['period_end'],'entitlement_id'=>(int)$row['id'],'allocated_school_days'=>(int)$row['allocated_school_days'],'used_school_days'=>(int)$row['used_school_days'],'remaining_school_days'=>$remainingDays,'used_today'=>(bool)$row['used_today']];
            // A partially paid annual entitlement must not block a fully paid
            // month/term entitlement that also covers this date.
            if ($partial === null) $partial = !$coverageAvailable
                ? ['decision'=>'deny_no_days','payment_status'=>$paid>0?'paid':'waived','balance'=>max(0,$due-$paid),'period_type'=>$row['period_type'],'period_start'=>$row['period_start'],'period_end'=>$row['period_end'],'entitlement_id'=>(int)$row['id'],'allocated_school_days'=>(int)$row['allocated_school_days'],'used_school_days'=>(int)$row['used_school_days'],'remaining_school_days'=>0]
                : ['decision'=>'deny_unpaid','payment_status'=>$paid>0?'partial':'unpaid','balance'=>max(0,$due-$paid),'period_type'=>$row['period_type'],'period_start'=>$row['period_start'],'period_end'=>$row['period_end'],'entitlement_id'=>(int)$row['id'],'allocated_school_days'=>(int)$row['allocated_school_days'],'used_school_days'=>(int)$row['used_school_days'],'remaining_school_days'=>$remainingDays];
        }
        return $partial ?: ['decision'=>'deny_no_entitlement','payment_status'=>'unpaid','balance'=>null,'period_type'=>null];
    }

    public function consumeSchoolDay(int $studentId, int $routeId, string $date, ?int $attendanceId = null): array
    {
        if (!$this->isSchoolDay($date)) throw new \InvalidArgumentException('Transport attendance can only consume configured school days');
        $access = $this->getAccess($studentId, $routeId, $date);
        if (($access['decision'] ?? '') !== 'approved' && ($access['decision'] ?? '') !== 'authorized_override') {
            throw new \InvalidArgumentException(($access['remaining_school_days'] ?? null) === 0 ? 'No paid transport school days remain' : 'The learner has no active paid transport coverage for this date');
        }
        if (!empty($access['entitlement_id'])) {
            $stmt = $this->db->prepare('INSERT IGNORE INTO student_transport_day_usage (entitlement_id,student_id,route_id,usage_date,first_attendance_id) VALUES (?,?,?,?,?)');
            $stmt->execute([(int)$access['entitlement_id'], $studentId, $routeId, $date, $attendanceId]);
            $access = $this->getAccess($studentId, $routeId, $date);
        }
        return $access;
    }

    private function isSchoolDay(string $date): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM academic_year_calendar_days d JOIN calendar_day_types t ON t.id=d.calendar_day_type_id WHERE d.date=? AND t.code='school_day' LIMIT 1");
        $stmt->execute([$date]);
        return (bool)$stmt->fetchColumn();
    }

    private function countSchoolDays(string $start, string $end): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(DISTINCT d.date) FROM academic_year_calendar_days d JOIN calendar_day_types t ON t.id=d.calendar_day_type_id WHERE d.date BETWEEN ? AND ? AND t.code='school_day'");
        $stmt->execute([$start, $end]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array{period_type:string,period_start:string,period_end:string,allocated_school_days:int,academic_year_term_id:?int,label:string} */
    private function deriveSubscriptionWindow(array $data): array
    {
        $unit = strtolower(trim((string) ($data['duration_unit'] ?? '')));
        $quantity = (float) ($data['duration_value'] ?? 0);
        if (!in_array($unit, ['school_days', 'weeks', 'months', 'terms', 'academic_year'], true)) {
            throw new RuntimeException('Choose a supported transport subscription length.');
        }
        if ($unit === 'academic_year') $quantity = 1;
        if ($quantity <= 0 || ($unit !== 'months' && floor($quantity) !== $quantity)) {
            throw new RuntimeException('Enter a valid transport subscription length.');
        }

        $termId = null;
        $start = '';
        $end = '';
        $type = match ($unit) {
            'school_days' => 'day',
            'weeks' => 'week',
            'months' => 'month',
            'terms' => 'term',
            default => 'year',
        };
        $label = $unit === 'academic_year' ? 'Full academic year' : $quantity . ' ' . str_replace('_', ' ', $unit);

        if ($unit === 'terms' || $unit === 'academic_year') {
            $yearId = $this->db->query("SELECT id FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
            if (!$yearId) throw new RuntimeException('The school has no current academic year calendar.');
            $termsStmt = $this->db->prepare(
                "SELECT ayt.id, t.name, t.id AS term_number,
                        COALESCE((SELECT MIN(d.date) FROM academic_year_calendar ac JOIN academic_year_calendar_days d ON d.academic_year_calendar_id=ac.id WHERE ac.academic_year_term_id=ayt.id), ayt.opening_date) AS period_start,
                        COALESCE((SELECT MAX(d.date) FROM academic_year_calendar ac JOIN academic_year_calendar_days d ON d.academic_year_calendar_id=ac.id WHERE ac.academic_year_term_id=ayt.id), ayt.closing_date) AS period_end,
                        (SELECT COUNT(DISTINCT d.date) FROM academic_year_calendar ac JOIN academic_year_calendar_days d ON d.academic_year_calendar_id=ac.id JOIN calendar_day_types cdt ON cdt.id=d.calendar_day_type_id WHERE ac.academic_year_term_id=ayt.id AND cdt.code='school_day') AS school_days
                   FROM academic_year_terms ayt JOIN terms t ON t.id=ayt.term_id
                  WHERE ayt.academic_year_id=? ORDER BY t.id"
            );
            $termsStmt->execute([(int) $yearId]);
            $terms = $termsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$terms) throw new RuntimeException('The current academic year has no configured terms.');
            if ($unit === 'academic_year') {
                $start = (string) $terms[0]['period_start'];
                $end = (string) $terms[count($terms) - 1]['period_end'];
                $label = 'Full academic year';
            } else {
                $requestedTermId = (int) ($data['academic_year_term_id'] ?? 0);
                $startIndex = null;
                foreach ($terms as $index => $term) {
                    if ((int) $term['id'] === $requestedTermId) { $startIndex = $index; break; }
                }
                if ($startIndex === null) throw new RuntimeException('Choose a starting term from the current academic year.');
                $selected = array_slice($terms, $startIndex, (int) $quantity);
                if (count($selected) !== (int) $quantity) throw new RuntimeException('The selected subscription extends beyond the current academic year.');
                $start = (string) $selected[0]['period_start'];
                $end = (string) $selected[count($selected) - 1]['period_end'];
                $termId = (int) $selected[0]['id'];
                $label = $quantity . ' ' . ($quantity === 1.0 ? 'term' : 'terms') . ' from ' . (string) $selected[0]['name'];
            }
        } else {
            $needed = $unit === 'weeks' ? (int) $quantity * 5 : (int) $quantity;
            $datesStmt = $this->db->prepare(
                "SELECT DISTINCT d.date FROM academic_year_calendar ac
                 JOIN academic_year_calendar_days d ON d.academic_year_calendar_id=ac.id
                 JOIN calendar_day_types cdt ON cdt.id=d.calendar_day_type_id
                 JOIN academic_year_terms ayt ON ayt.id=ac.academic_year_term_id
                 JOIN academic_years ay ON ay.id=ayt.academic_year_id
                 WHERE ay.is_current=1 AND cdt.code='school_day' AND d.date>=CURDATE()
                 ORDER BY d.date LIMIT " . ($unit === 'months' ? '1' : (int) $needed)
            );
            $schoolDates = array_map('strval', $datesStmt->fetchAll(PDO::FETCH_COLUMN));
            if (!$schoolDates) throw new RuntimeException('No upcoming school days are configured in the academic calendar.');
            $start = $schoolDates[0];
            if ($unit === 'school_days' || $unit === 'weeks') {
                if (count($schoolDates) < $needed) throw new RuntimeException('The academic calendar does not contain enough upcoming school days for this subscription.');
                $end = $schoolDates[$needed - 1];
            } else {
                $date = new \DateTimeImmutable($start);
                $wholeMonths = (int) floor($quantity);
                $fraction = $quantity - $wholeMonths;
                $targetMonth = $date->modify('first day of this month')->modify('+' . $wholeMonths . ' months');
                $originalDay = (int) $date->format('j');
                $daysInMonth = (int) $targetMonth->format('t');
                $targetMonth = $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('n'), min($originalDay, $daysInMonth));
                $exclusiveEnd = $targetMonth;
                if ($fraction > 0) {
                    $fractionDays = (int) round((int) $targetMonth->format('t') * $fraction);
                    $exclusiveEnd = $targetMonth->modify('+' . $fractionDays . ' days');
                }
                $end = $exclusiveEnd->modify('-1 day')->format('Y-m-d');
            }
        }

        $schoolDayCount = $this->countSchoolDays($start, $end);
        if ($schoolDayCount < 1) throw new RuntimeException('The selected subscription period contains no configured school days.');
        return [
            'period_type' => $type,
            'period_start' => $start,
            'period_end' => $end,
            'allocated_school_days' => $schoolDayCount,
            'academic_year_term_id' => $termId,
            'label' => $label,
        ];
    }

    public function getEntitlement(int $id): array
    {
        $stmt=$this->db->prepare("SELECT e.*,p.period_type,p.period_start,p.period_end,p.label FROM student_transport_entitlements e JOIN transport_entitlement_periods p ON p.id=e.period_id WHERE e.id=?"); $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function ensurePeriod(string $type, string $start, string $end, ?int $termId, ?string $label, ?int $userId): int
    {
        $stmt=$this->db->prepare("INSERT INTO transport_entitlement_periods (period_type,period_start,period_end,academic_year_term_id,label,created_by) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id), label=COALESCE(VALUES(label),label)"); $stmt->execute([$type,$start,$end,$termId,$label,$userId]); return (int)$this->db->lastInsertId();
    }
}
