<?php

namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use PDO;
use Throwable;
use function App\API\Includes\formatResponse;

/**
 * Canonical Staff leave micro-manager.
 *
 * Owns leave request validation, persistence, approval and balance queries.
 * Controllers only apply authenticated scope and expose these operations.
 */
class StaffLeaveManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('staff_leave');
    }

    public function createLeaveRequest(array $data): array
    {
        $required = ['staff_id', 'leave_type_id', 'start_date', 'end_date', 'reason'];
        $missing = $this->validateRequired($data, $required);
        if ($missing) {
            return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
        }

        $staffId = (int) $data['staff_id'];
        $leaveTypeId = (int) $data['leave_type_id'];
        $startDate = (string) $data['start_date'];
        $endDate = (string) $data['end_date'];
        $reason = trim((string) $data['reason']);

        if ($staffId <= 0 || $leaveTypeId <= 0 || $reason === '') {
            return formatResponse(false, null, 'Invalid leave request details');
        }
        if (!$this->validDate($startDate) || !$this->validDate($endDate)) {
            return formatResponse(false, null, 'Leave dates must use YYYY-MM-DD');
        }
        if (strtotime($endDate) < strtotime($startDate)) {
            return formatResponse(false, null, 'End date must be on or after the start date');
        }

        try {
            $this->db->beginTransaction();

            $staffStmt = $this->db->prepare(
                "SELECT id, first_name, last_name, staff_type_id, status
                 FROM staff
                 WHERE id = ? AND status IN ('active', 'on_leave')
                 LIMIT 1"
            );
            $staffStmt->execute([$staffId]);
            $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active staff member not found');
            }

            $typeStmt = $this->db->prepare(
                "SELECT id, code, name, days_allowed, requires_approval, is_paid, applicable_to
                 FROM leave_types
                 WHERE id = ? AND status = 'active'
                 LIMIT 1"
            );
            $typeStmt->execute([$leaveTypeId]);
            $leaveType = $typeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$leaveType) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Leave type was not found or is inactive');
            }

            $overlapStmt = $this->db->prepare(
                "SELECT id
                 FROM staff_leaves
                 WHERE staff_id = ?
                   AND status IN ('pending', 'approved')
                   AND start_date <= ?
                   AND end_date >= ?
                 LIMIT 1"
            );
            $overlapStmt->execute([$staffId, $endDate, $startDate]);
            if ($overlapStmt->fetchColumn()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'The requested dates overlap an existing pending or approved leave');
            }

            $daysRequested = $this->calculateWorkingDays($startDate, $endDate);
            $allowed = $leaveType['days_allowed'] !== null ? (int) $leaveType['days_allowed'] : null;
            if ($allowed !== null) {
                $usedStmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(days_requested), 0)
                     FROM staff_leaves
                     WHERE staff_id = ?
                       AND leave_type_id = ?
                       AND status = 'approved'
                       AND YEAR(start_date) = YEAR(?)"
                );
                $usedStmt->execute([$staffId, $leaveTypeId, $startDate]);
                $used = (int) $usedStmt->fetchColumn();
                if (($used + $daysRequested) > $allowed) {
                    $this->db->rollBack();
                    return formatResponse(
                        false,
                        null,
                        sprintf(
                            'Insufficient %s balance. Available: %d days, requested: %d days',
                            $leaveType['name'],
                            max(0, $allowed - $used),
                            $daysRequested
                        )
                    );
                }
            }

            $stmt = $this->db->prepare(
                "INSERT INTO staff_leaves
                    (staff_id, leave_type_id, leave_type, start_date, end_date,
                     days_requested, reason, relief_staff_id, status, attachments_folder)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
            );
            $stmt->execute([
                $staffId,
                $leaveTypeId,
                $leaveType['code'],
                $startDate,
                $endDate,
                $daysRequested,
                $reason,
                !empty($data['relief_staff_id']) ? (int) $data['relief_staff_id'] : null,
                $data['attachments_folder'] ?? null,
            ]);

            $leaveId = (int) $this->db->lastInsertId();
            $this->db->commit();
            $this->logAction('create', $leaveId, 'Staff leave request submitted');

            return formatResponse(true, [
                'id' => $leaveId,
                'staff_id' => $staffId,
                'leave_type_id' => $leaveTypeId,
                'leave_type_name' => $leaveType['name'],
                'start_date' => $startDate,
                'end_date' => $endDate,
                'days_requested' => $daysRequested,
                'status' => 'pending',
            ], 'Leave request created successfully');
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($error);
        }
    }

    public function getLeaveHistory(array $filters = []): array
    {
        try {
            $where = [];
            $params = [];
            if (!empty($filters['staff_id'])) {
                $where[] = 'sl.staff_id = ?';
                $params[] = (int) $filters['staff_id'];
            }
            if (!empty($filters['status'])) {
                $where[] = 'sl.status = ?';
                $params[] = (string) $filters['status'];
            }
            if (!empty($filters['year'])) {
                $where[] = 'YEAR(sl.start_date) = ?';
                $params[] = (int) $filters['year'];
            }

            $sql = "SELECT sl.id, sl.staff_id, sl.leave_type_id, sl.leave_type,
                           lt.name AS leave_type_name, lt.code AS leave_type_code,
                           sl.start_date, sl.end_date, sl.days_requested, sl.reason,
                           sl.status, sl.approved_at, sl.rejection_reason, sl.created_at,
                           CONCAT_WS(' ', s.first_name, s.last_name) AS staff_name,
                           s.staff_no,
                           CONCAT_WS(' ', approver.first_name, approver.last_name) AS approved_by_name
                    FROM staff_leaves sl
                    INNER JOIN staff s ON s.id = sl.staff_id
                    INNER JOIN leave_types lt ON lt.id = sl.leave_type_id
                    LEFT JOIN users au ON au.id = sl.approved_by
                    LEFT JOIN staff approver ON approver.user_id = au.id";
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY sl.created_at DESC, sl.id DESC LIMIT 100';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Leave requests retrieved');
        } catch (Throwable $error) {
            return $this->handleException($error);
        }
    }

    public function getLeaveBalance(int $staffId, ?string $leaveType = null): array
    {
        try {
            $sql = "SELECT lt.id, lt.code, lt.name, lt.days_allowed,
                           COALESCE(SUM(CASE
                               WHEN sl.status = 'approved' AND YEAR(sl.start_date) = YEAR(CURDATE())
                               THEN sl.days_requested ELSE 0 END), 0) AS used_days
                    FROM leave_types lt
                    LEFT JOIN staff_leaves sl
                           ON sl.leave_type_id = lt.id
                          AND sl.staff_id = ?
                    WHERE lt.status = 'active'";
            $params = [$staffId];
            if ($leaveType !== null && trim($leaveType) !== '') {
                $sql .= ' AND (lt.code = ? OR lt.name = ?)';
                $params[] = trim($leaveType);
                $params[] = trim($leaveType);
            }
            $sql .= ' GROUP BY lt.id, lt.code, lt.name, lt.days_allowed ORDER BY lt.name';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['used_days'] = (int) $row['used_days'];
                $row['available_days'] = $row['days_allowed'] === null
                    ? null
                    : max(0, (int) $row['days_allowed'] - $row['used_days']);
            }
            unset($row);
            return formatResponse(true, $rows, 'Leave balances retrieved');
        } catch (Throwable $error) {
            return $this->handleException($error);
        }
    }

    public function updateLeaveStatus(int $leaveId, array $data): array
    {
        $status = (string) ($data['status'] ?? '');
        if (!in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
            return formatResponse(false, null, 'Invalid leave status');
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare('SELECT * FROM staff_leaves WHERE id = ? FOR UPDATE');
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$leave) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Leave request not found');
            }
            if ($leave['status'] !== 'pending') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Only pending leave requests can be updated');
            }

            $actorId = !empty($data['approved_by']) ? (int) $data['approved_by'] : null;
            $update = $this->db->prepare(
                "UPDATE staff_leaves
                 SET status = ?,
                     approved_by = ?,
                     approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE NULL END,
                     rejection_reason = CASE WHEN ? = 'rejected' THEN ? ELSE NULL END
                 WHERE id = ?"
            );
            $update->execute([
                $status,
                $actorId,
                $status,
                $status,
                $data['rejection_reason'] ?? null,
                $leaveId,
            ]);
            $this->db->commit();
            $this->logAction('update', $leaveId, "Leave request {$status}");

            return formatResponse(true, ['id' => $leaveId, 'status' => $status], 'Leave request updated');
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($error);
        }
    }


    /**
     * Preserve the existing public leave-accrual capability using the actual
     * leave_types.days_allowed schema. This method is reusable by payroll and
     * staff workflows; it is not dashboard-specific.
     */
    public function calculateAccruedLeave($staffId, $leaveType = 'ANNUAL'): array
    {
        try {
            $staffStmt = $this->db->prepare(
                'SELECT employment_date, DATEDIFF(CURDATE(), employment_date) AS days_employed
                 FROM staff WHERE id = ? LIMIT 1'
            );
            $staffStmt->execute([(int) $staffId]);
            $staff = $staffStmt->fetch(PDO::FETCH_ASSOC);
            if (!$staff) {
                return formatResponse(false, null, 'Staff member not found');
            }

            $typeStmt = $this->db->prepare(
                "SELECT code, name, days_allowed
                 FROM leave_types
                 WHERE code = ? AND status = 'active'
                 LIMIT 1"
            );
            $typeStmt->execute([(string) $leaveType]);
            $type = $typeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$type) {
                return formatResponse(false, null, 'Leave type not found');
            }

            $annualEntitlement = $type['days_allowed'] !== null
                ? (int) $type['days_allowed']
                : null;
            $monthsEmployed = max(0, (int) floor(((int) $staff['days_employed']) / 30));
            $accruedDays = $annualEntitlement === null
                ? null
                : min(
                    $annualEntitlement,
                    ($annualEntitlement / 12) * $monthsEmployed
                );

            return formatResponse(true, [
                'staff_id' => (int) $staffId,
                'leave_type' => $type['code'],
                'leave_name' => $type['name'],
                'employment_date' => $staff['employment_date'],
                'months_employed' => $monthsEmployed,
                'annual_entitlement' => $annualEntitlement,
                'accrued_days' => $accruedDays === null
                    ? null
                    : round($accruedDays, 2),
            ], 'Accrued leave calculated successfully');
        } catch (Throwable $error) {
            return $this->handleException($error);
        }
    }

    /** Preserve the existing cancellation capability. */
    public function cancelLeaveRequest($leaveId, $data = []): array
    {
        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                "SELECT id, status FROM staff_leaves
                 WHERE id = ? AND status IN ('pending', 'approved')
                 FOR UPDATE"
            );
            $stmt->execute([(int) $leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$leave) {
                $this->db->rollBack();
                return formatResponse(
                    false,
                    null,
                    'Leave request not found or cannot be cancelled'
                );
            }

            $update = $this->db->prepare(
                "UPDATE staff_leaves
                 SET status = 'cancelled', rejection_reason = ?
                 WHERE id = ?"
            );
            $update->execute([
                trim((string) ($data['cancellation_reason'] ?? ''))
                    ?: 'Cancelled by staff',
                (int) $leaveId,
            ]);
            $this->db->commit();
            $this->logAction('update', (int) $leaveId, 'Cancelled leave request');

            return formatResponse(true, [
                'leave_id' => (int) $leaveId,
                'status' => 'cancelled',
            ], 'Leave request cancelled successfully');
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($error);
        }
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function calculateWorkingDays(string $startDate, string $endDate): int
    {
        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $end->modify('+1 day');
        $days = 0;
        for ($date = clone $start; $date < $end; $date->modify('+1 day')) {
            if ((int) $date->format('N') <= 5) {
                $days++;
            }
        }
        return max(1, $days);
    }
}
