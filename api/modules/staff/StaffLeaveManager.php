<?php
namespace App\API\Modules\staff;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Leave Manager
 * 
 * Handles CRUD operations for staff leave management
 * - Creates and manages leave requests
 * - Calculates leave balances
 * - Tracks leave history
 * - Handles different leave types (annual, sick, maternity, etc.)
 * - Respects staff types and leave entitlements
 */
class StaffLeaveManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create leave request
     * @param array $data Leave request data
     * @return array Response
     */
    public function createLeaveRequest($data)
    {
        try {
            $required = ['staff_id', 'leave_type', 'start_date', 'end_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Get staff details
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name,
                       CONCAT(sup.first_name, ' ', sup.last_name) as supervisor_name
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN staff sup ON s.supervisor_id = sup.id
                WHERE s.id = ? AND s.status = 'active'
            ");
            $stmt->execute([$data['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active staff member not found');
            }

            // Validate leave type
            $stmt = $this->db->prepare("SELECT * FROM leave_types WHERE code = ? AND is_active = 1");
            $stmt->execute([$data['leave_type']]);
            $leaveType = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leaveType) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid or inactive leave type');
            }

            // Calculate leave days
            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);

            if ($startDate > $endDate) {
                $this->db->rollBack();
                return formatResponse(false, null, 'End date must be after start date');
            }

            $leaveDays = $this->calculateWorkingDays($startDate, $endDate);

            // Note: Overlap validation handled by trg_check_leave_overlap trigger
            // The trigger will automatically prevent overlapping leave requests

            // Check leave balance if applicable using stored procedure
            if ($leaveType['requires_balance_check']) {
                $stmt = $this->db->prepare("CALL sp_calculate_staff_leave_balance(?, ?, @entitled, @used, @available)");
                $stmt->execute([$data['staff_id'], $data['leave_type']]);
                $stmt->closeCursor();

                $result = $this->db->query("SELECT @entitled AS entitled, @used AS used, @available AS available")->fetch(PDO::FETCH_ASSOC);

                if ($result['available'] < $leaveDays) {
                    $this->db->rollBack();
                    return formatResponse(
                        false,
                        null,
                        "Insufficient leave balance. Available: {$result['available']} days, Requested: {$leaveDays} days"
                    );
                }
            }

            // Create leave request
            $sql = "INSERT INTO staff_leaves (
                staff_id, leave_type, start_date, end_date, days_requested,
                reason, relief_staff_id, status, applied_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['leave_type'],
                $data['start_date'],
                $data['end_date'],
                $leaveDays,
                $data['reason'],
                $data['relief_staff_id'] ?? null
            ]);

            $leaveId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction(
                'create',
                $leaveId,
                "Created leave request for {$staff['first_name']} {$staff['last_name']} - {$leaveType['name']} ({$leaveDays} days)"
            );

            return formatResponse(true, [
                'leave_id' => $leaveId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'leave_type' => $leaveType['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'days_requested' => $leaveDays,
                'status' => 'pending'
            ], 'Leave request created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Calculate working days between two dates (excluding weekends)
     */
    private function calculateWorkingDays(\DateTime $start, \DateTime $end)
    {
        $workingDays = 0;
        $current = clone $start;

        while ($current <= $end) {
            $dayOfWeek = $current->format('N'); // 1 (Monday) to 7 (Sunday)
            if ($dayOfWeek < 6) { // Monday to Friday
                $workingDays++;
            }
            $current->modify('+1 day');
        }

        return $workingDays;
    }

    /**
     * Update leave status (approve/reject)
     * @param int $leaveId Leave ID
     * @param array $data Status update data
     * @return array Response
     */
    public function updateLeaveStatus($leaveId, $data)
    {
        try {
            $required = ['status', 'approved_by'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $validStatuses = ['approved', 'rejected', 'cancelled'];
            if (!in_array($data['status'], $validStatuses)) {
                return formatResponse(false, null, 'Invalid status. Must be: ' . implode(', ', $validStatuses));
            }

            $this->db->beginTransaction();

            // Get leave details
            $stmt = $this->db->prepare("
                SELECT sl.*, s.first_name, s.last_name, lt.name as leave_type_name
                FROM staff_leaves sl
                JOIN staff s ON sl.staff_id = s.id
                JOIN leave_types lt ON sl.leave_type = lt.code
                WHERE sl.id = ?
            ");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leave) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Leave request not found');
            }

            if ($leave['status'] !== 'pending') {
                $this->db->rollBack();
                return formatResponse(false, null, "Cannot update leave with status: {$leave['status']}");
            }

            // Update status
            $sql = "UPDATE staff_leaves SET
                status = ?,
                approved_by = ?,
                approval_date = NOW(),
                approval_comments = ?
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['status'],
                $data['approved_by'],
                $data['approval_comments'] ?? null,
                $leaveId
            ]);

            $this->db->commit();
            $this->logAction(
                'update',
                $leaveId,
                "Updated leave status to: {$data['status']} for {$leave['first_name']} {$leave['last_name']}"
            );

            return formatResponse(true, [
                'leave_id' => $leaveId,
                'staff_name' => $leave['first_name'] . ' ' . $leave['last_name'],
                'leave_type' => $leave['leave_type_name'],
                'status' => $data['status']
            ], "Leave request {$data['status']} successfully");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get leave balance for a staff member
     * Uses vw_staff_leave_balances view for automated calculations
     * @param int $staffId Staff ID
     * @param string $leaveTypeName Optional leave type name filter
     * @return array Response
     */
    public function getLeaveBalance($staffId, $leaveTypeName = null)
    {
        try {
            $sql = "SELECT * FROM vw_staff_leave_balances WHERE staff_id = ?";
            $params = [$staffId];

            if ($leaveTypeName) {
                $sql .= " AND leave_type_name = ?";
                $params[] = $leaveTypeName;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($leaveTypeName) {
                return formatResponse(true, $balances[0] ?? null, 'Leave balance retrieved successfully');
            }

            return formatResponse(true, [
                'balances' => $balances,
                'count' => count($balances)
            ], 'Leave balances retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get leave history
     * @param array $filters Filter criteria
     * @return array Response
     */
    public function getLeaveHistory($filters = [])
    {
        try {
            $sql = "SELECT sl.*,
                       s.staff_no, s.first_name, s.last_name, s.position,
                       st.name as staff_type, d.name as department_name,
                       lt.name as leave_type_name,
                       CONCAT(relief.first_name, ' ', relief.last_name) as relief_staff_name,
                       CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                FROM staff_leaves sl
                JOIN staff s ON sl.staff_id = s.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN departments d ON s.department_id = d.id
                JOIN leave_types lt ON sl.leave_type = lt.code
                LEFT JOIN staff relief ON sl.relief_staff_id = relief.id
                LEFT JOIN users approver ON sl.approved_by = approver.id
                WHERE 1=1";

            $params = [];

            if (!empty($filters['staff_id'])) {
                $sql .= " AND sl.staff_id = ?";
                $params[] = $filters['staff_id'];
            }

            if (!empty($filters['leave_type'])) {
                $sql .= " AND sl.leave_type = ?";
                $params[] = $filters['leave_type'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND sl.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND s.department_id = ?";
                $params[] = $filters['department_id'];
            }

            if (!empty($filters['year'])) {
                $sql .= " AND YEAR(sl.start_date) = ?";
                $params[] = $filters['year'];
            }

            $sql .= " ORDER BY sl.applied_date DESC, sl.start_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $leaveRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'leave_records' => $leaveRecords,
                'count' => count($leaveRecords)
            ], 'Leave history retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Calculate accrued leave for a staff member
     * @param int $staffId Staff ID
     * @param string $leaveType Leave type code
     * @return array Response
     */
    public function calculateAccruedLeave($staffId, $leaveType = 'ANNUAL')
    {
        try {
            // Get staff employment date
            $stmt = $this->db->prepare("
                SELECT employment_date, DATEDIFF(CURDATE(), employment_date) as days_employed
                FROM staff WHERE id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                return formatResponse(false, null, 'Staff member not found');
            }

            // Get leave type details
            $stmt = $this->db->prepare("SELECT * FROM leave_types WHERE code = ?");
            $stmt->execute([$leaveType]);
            $lt = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lt) {
                return formatResponse(false, null, 'Leave type not found');
            }

            $annualEntitlement = $lt['default_days_per_year'] ?? 21; // Default 21 days annual leave
            $monthsEmployed = floor($staff['days_employed'] / 30);
            $accruedDays = min($annualEntitlement, ($annualEntitlement / 12) * $monthsEmployed);

            return formatResponse(true, [
                'staff_id' => $staffId,
                'leave_type' => $leaveType,
                'employment_date' => $staff['employment_date'],
                'months_employed' => $monthsEmployed,
                'annual_entitlement' => $annualEntitlement,
                'accrued_days' => round($accruedDays, 2)
            ], 'Accrued leave calculated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Cancel leave request
     * @param int $leaveId Leave ID
     * @param array $data Cancellation data
     * @return array Response
     */
    public function cancelLeaveRequest($leaveId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT * FROM staff_leaves WHERE id = ? AND status IN ('pending', 'approved')
            ");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leave) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Leave request not found or cannot be cancelled');
            }

            $sql = "UPDATE staff_leaves SET
                status = 'cancelled',
                cancellation_reason = ?,
                cancellation_date = NOW()
            WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['cancellation_reason'] ?? 'Cancelled by staff',
                $leaveId
            ]);

            $this->db->commit();
            $this->logAction('update', $leaveId, "Cancelled leave request");

            return formatResponse(true, [
                'leave_id' => $leaveId,
                'status' => 'cancelled'
            ], 'Leave request cancelled successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }
}
