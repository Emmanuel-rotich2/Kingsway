<?php
namespace App\API\Modules\Staff;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Leave Approval Workflow
 * 
 * Multi-stage approval workflow for staff leave requests
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. leave_request - Staff submits leave request
 * 2. supervisor_review - Direct supervisor reviews and approves/rejects
 * 3. hr_approval - HR reviews leave balance and approves
 * 4. director_approval - Director gives final approval (if > 5 days)
 * 5. approved - Leave approved and recorded
 * 6. rejected - Leave rejected at any stage
 */
class LeaveWorkflow extends WorkflowHandler
{
    protected $workflowType = 'staff_leave';

    /**
     * Initiate leave request workflow
     * @param int $leaveId Leave request ID
     * @param int $userId User initiating workflow (staff member)
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateLeaveRequest($leaveId, $userId, $data = [])
    {
        try {
            $this->beginTransaction();

            // Get leave request details
            $stmt = $this->db->prepare("
                SELECT sl.*, 
                       s.id as staff_id, s.staff_no, s.first_name, s.last_name, 
                       s.position, s.supervisor_id,
                       lt.name as leave_type_name, lt.requires_balance_check,
                       d.name as department_name
                FROM staff_leaves sl
                JOIN staff s ON sl.staff_id = s.id
                JOIN leave_types lt ON sl.leave_type = lt.code
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE sl.id = ?
            ");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leave) {
                $this->rollback();
                return formatResponse(false, null, 'Leave request not found');
            }

            // Check for existing active workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.reference_type = 'staff_leave'
                AND wi.reference_id = ?
                AND wi.status IN ('in_progress', 'pending')
            ");
            $stmt->execute([$leaveId]);

            if ($stmt->fetch()) {
                $this->rollback();
                return formatResponse(false, null, 'Active workflow already exists for this leave request');
            }

            // Validate leave balance if required
            if ($leave['requires_balance_check']) {
                $stmt = $this->db->prepare("CALL sp_calculate_staff_leave_balance(?, ?, @entitled, @used, @available)");
                $stmt->execute([$leave['staff_id'], $leave['leave_type']]);
                $stmt->closeCursor();

                $result = $this->db->query("SELECT @entitled AS entitled, @used AS used, @available AS available")->fetch(PDO::FETCH_ASSOC);

                if ($result['available'] < $leave['days_requested']) {
                    $this->rollback();
                    return formatResponse(
                        false,
                        null,
                        "Insufficient leave balance. Available: {$result['available']} days, Requested: {$leave['days_requested']} days"
                    );
                }
            }

            // Prepare workflow data
            $workflowData = [
                'leave_id' => $leaveId,
                'staff_id' => $leave['staff_id'],
                'staff_no' => $leave['staff_no'],
                'staff_name' => $leave['first_name'] . ' ' . $leave['last_name'],
                'position' => $leave['position'],
                'department' => $leave['department_name'],
                'leave_type' => $leave['leave_type_name'],
                'start_date' => $leave['start_date'],
                'end_date' => $leave['end_date'],
                'days_requested' => $leave['days_requested'],
                'reason' => $leave['reason'],
                'supervisor_id' => $leave['supervisor_id'],
                'requires_director_approval' => $leave['days_requested'] > 5
            ];

            // Start workflow
            $instanceId = $this->startWorkflow('staff_leave', $leaveId, $userId, $workflowData);

            // Update leave status
            $stmt = $this->db->prepare("
                UPDATE staff_leaves 
                SET status = 'pending', workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$instanceId, $leaveId]);

            $this->commit();
            $this->logAction(
                'create',
                $instanceId,
                "Initiated leave workflow for {$leave['first_name']} {$leave['last_name']} - {$leave['leave_type_name']} ({$leave['days_requested']} days)"
            );

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'leave_id' => $leaveId,
                'staff_name' => $leave['first_name'] . ' ' . $leave['last_name'],
                'leave_type' => $leave['leave_type_name'],
                'days_requested' => $leave['days_requested'],
                'current_stage' => 'leave_request',
                'next_stage' => 'supervisor_review',
                'status' => 'pending'
            ], 'Leave request workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Supervisor reviews and approves/rejects (Stage 2)
     * @param int $instanceId Workflow instance ID
     * @param int $userId User performing action (supervisor)
     * @param string $action 'approve' or 'reject'
     * @param array $data Review data
     * @return array Response
     */
    public function supervisorReview($instanceId, $userId, $action, $data = [])
    {
        try {
            // Load workflow instance
            $workflow = $this->getWorkflowInstance($instanceId);

            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $currentStage = $workflow['current_stage'];

            if ($currentStage !== 'supervisor_review') {
                return formatResponse(false, null, "Cannot perform supervisor review. Current stage is: {$currentStage}");
            }

            // Validate supervisor
            $workflowData = json_decode($workflow['data_json'], true);
                // Check if user has admin or HR role
                $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                }
            }

            $this->beginTransaction();

            if ($action === 'reject') {
                // Reject leave
                $this->advanceStage(
                    $instanceId,
                    'rejected',
                    'supervisor_rejected',
                    $workflowData
                );

                // Update leave status
                $stmt = $this->db->prepare("
                    UPDATE staff_leaves 
                    SET status = 'rejected', approved_by = ?, approval_date = NOW(), approval_comments = ?
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $data['remarks'] ?? null, $workflowData['leave_id']]);

                $this->commit();

                return formatResponse(true, [
                    'workflow_id' => $instanceId,
                    'status' => 'rejected',
                    'stage' => 'rejected'
                ], 'Leave request rejected by supervisor');
            }

            // Approve - move to HR approval
            $workflowData['supervisor_approved_by'] = $userId;
            $workflowData['supervisor_approved_at'] = date('Y-m-d H:i:s');
            $workflowData['supervisor_remarks'] = $data['remarks'] ?? null;

            $this->advanceStage(
                $instanceId,
                'hr_approval',
                'supervisor_approved',
                $workflowData
            );

            $this->commit();

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'current_stage' => 'hr_approval',
                'status' => 'pending_hr_approval'
            ], 'Leave approved by supervisor, forwarded to HR');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * HR reviews and approves/rejects (Stage 3)
     * @param int $instanceId Workflow instance ID
     * @param int $userId User performing action (HR)
     * @param string $action 'approve' or 'reject'
     * @param array $data Review data
     * @return array Response
     */
    public function hrApproval($instanceId, $userId, $action, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);

            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $currentStage = $workflow['current_stage'];

            if ($currentStage !== 'hr_approval') {
                return formatResponse(false, null, "Cannot perform HR approval. Current stage is: {$currentStage}");
            }

            // Validate HR role
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return formatResponse(false, null, 'Only HR managers can perform HR approval');
            }

            $workflowData = json_decode($workflow['data_json'], true);

            $this->beginTransaction();

            if ($action === 'reject') {
                // Reject leave
                $this->advanceStage(
                    $instanceId,
                    'rejected',
                    'hr_rejected',
                    $workflowData
                );

                // Update leave status
                $stmt = $this->db->prepare("
                    UPDATE staff_leaves 
                    SET status = 'rejected', approved_by = ?, approval_date = NOW(), approval_comments = ?
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $data['remarks'] ?? null, $workflowData['leave_id']]);

                $this->commit();

                return formatResponse(true, [
                    'workflow_id' => $instanceId,
                    'status' => 'rejected',
                    'stage' => 'rejected'
                ], 'Leave request rejected by HR');
            }

            // Approve
            $workflowData['hr_approved_by'] = $userId;
            $workflowData['hr_approved_at'] = date('Y-m-d H:i:s');
            $workflowData['hr_remarks'] = $data['remarks'] ?? null;

            // Check if director approval is required (> 5 days)
            if ($workflowData['requires_director_approval']) {
                $this->advanceStage(
                    $instanceId,
                    'director_approval',
                    'hr_approved',
                    $workflowData
                );

                $this->commit();

                return formatResponse(true, [
                    'workflow_id' => $instanceId,
                    'current_stage' => 'director_approval',
                    'status' => 'pending_director_approval'
                ], 'Leave approved by HR, forwarded to Director for final approval');
            }

            // No director approval needed - approve directly
            $this->advanceStage(
                $instanceId,
                'approved',
                'hr_approved',
                $workflowData
            );

            // Update leave status
            $stmt = $this->db->prepare("
                UPDATE staff_leaves 
                SET status = 'approved', approved_by = ?, approval_date = NOW(), approval_comments = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $data['remarks'] ?? null, $workflowData['leave_id']]);

            $this->commit();

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'status' => 'approved',
                'stage' => 'approved'
            ], 'Leave request approved');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Director final approval (Stage 4 - for leaves > 5 days)
     * @param int $instanceId Workflow instance ID
     * @param int $userId User performing action (Director)
     * @param string $action 'approve' or 'reject'
     * @param array $data Review data
     * @return array Response
     */
    public function directorApproval($instanceId, $userId, $action, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);

            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $currentStage = $workflow['current_stage'];

            if ($currentStage !== 'director_approval') {
                return formatResponse(false, null, "Cannot perform director approval. Current stage is: {$currentStage}");
            }

            // Validate Director role
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return formatResponse(false, null, 'Only the Director can perform final approval');
            }

            $workflowData = json_decode($workflow['data_json'], true);

            $this->beginTransaction();

            if ($action === 'reject') {
                // Reject leave
                $this->advanceStage(
                    $instanceId,
                    'rejected',
                    'director_rejected',
                    $workflowData
                );

                // Update leave status
                $stmt = $this->db->prepare("
                    UPDATE staff_leaves 
                    SET status = 'rejected', approved_by = ?, approval_date = NOW(), approval_comments = ?
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $data['remarks'] ?? null, $workflowData['leave_id']]);

                $this->commit();

                return formatResponse(true, [
                    'workflow_id' => $instanceId,
                    'status' => 'rejected',
                    'stage' => 'rejected'
                ], 'Leave request rejected by Director');
            }

            // Approve
            $workflowData['director_approved_by'] = $userId;
            $workflowData['director_approved_at'] = date('Y-m-d H:i:s');
            $workflowData['director_remarks'] = $data['remarks'] ?? null;

            $this->advanceStage(
                $instanceId,
                'approved',
                'director_approved',
                $workflowData
            );

            // Update leave status
            $stmt = $this->db->prepare("
                UPDATE staff_leaves 
                SET status = 'approved', approved_by = ?, approval_date = NOW(), approval_comments = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $data['remarks'] ?? null, $workflowData['leave_id']]);

            $this->commit();

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'status' => 'approved',
                'stage' => 'approved'
            ], 'Leave request approved by Director');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Validate workflow transition
     * @param string $fromStage Current stage
     * @param string $toStage Target stage
     * @param array $data Transition data
     * @return bool
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        $validTransitions = [
            'leave_request' => ['supervisor_review', 'rejected'],
            'supervisor_review' => ['hr_approval', 'rejected'],
            'hr_approval' => ['director_approval', 'approved', 'rejected'],
            'director_approval' => ['approved', 'rejected']
        ];

        if (!isset($validTransitions[$fromStage])) {
            return false;
        }

        return in_array($toStage, $validTransitions[$fromStage]);
    }

    /**
     * Process stage-specific logic
     * @param string $stage Current stage
     * @param array $data Stage data
     * @return bool
     */
    protected function processStage($instanceId, $stage, $data)
    {
        switch ($stage) {
            case 'leave_request':
                // Initial submission - no additional processing
                return true;

            case 'supervisor_review':
                // Notify supervisor
                $this->createNotification(
                    $instanceId,
                    $data['supervisor_id'],
                    'Leave Request Pending Review',
                    "{$data['staff_name']} has requested {$data['days_requested']} days of {$data['leave_type']} leave",
                    'workflow'
                );
                return true;

            case 'hr_approval':
                // Notify HR
                $this->createNotification(
                    $instanceId,
                    null, // Will be sent to HR role
                    'Leave Request Pending HR Approval',
                    "{$data['staff_name']} - {$data['leave_type']} ({$data['days_requested']} days)",
                    'workflow'
                );
                return true;

            case 'director_approval':
                // Notify Director
                $this->createNotification(
                    $instanceId,
                    null, // Will be sent to Director role
                    'Leave Request Pending Director Approval',
                    "{$data['staff_name']} - {$data['leave_type']} ({$data['days_requested']} days)",
                    'workflow'
                );
                return true;

            case 'approved':
                // Notify staff member
                $this->createNotification(
                    $instanceId,
                    $data['staff_id'],
                    'Leave Request Approved',
                    "Your {$data['leave_type']} leave request has been approved",
                    'workflow'
                );
                return true;

            case 'rejected':
                // Notify staff member
                $this->createNotification(
                    $instanceId,
                    $data['staff_id'],
                    'Leave Request Rejected',
                    "Your {$data['leave_type']} leave request has been rejected",
                    'workflow'
                );
                return true;

            default:
                return false;
        }
    }

    /**
     * Create notification - uses parent implementation
     * Removed override to use WorkflowHandler::createNotification()
     */
}
