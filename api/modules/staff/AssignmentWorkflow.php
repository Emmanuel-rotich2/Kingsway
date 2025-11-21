<?php
namespace App\API\Modules\Staff;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Assignment Workflow
 * 
 * Multi-stage approval workflow for staff-class assignments
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. assignment_request - Request to assign staff to class
 * 2. validation - Validate assignment (workload, qualifications)
 * 3. head_teacher_approval - Head teacher approves assignment
 * 4. approved - Assignment approved and activated
 * 5. rejected - Assignment rejected at any stage
 */
class AssignmentWorkflow extends WorkflowHandler
{
    protected $workflowType = 'staff_assignment';

    /**
     * Initiate assignment request workflow
     * @param int $assignmentId Assignment ID
     * @param int $userId User initiating workflow
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateAssignmentRequest($assignmentId, $userId, $data = [])
    {
        try {
            $this->beginTransaction();

            // Get assignment details
            $stmt = $this->db->prepare("
                SELECT sca.*, 
                       s.id as staff_id, s.staff_no, s.first_name, s.last_name, 
                       s.position, s.department_id,
                       cs.stream_name, c.name as class_name, c.id as class_id,
                       ay.year_name as academic_year,
                       sub.name as subject_name,
                       d.name as department_name
                FROM staff_class_assignments sca
                JOIN staff s ON sca.staff_id = s.id
                JOIN class_streams cs ON sca.class_stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN academic_years ay ON sca.academic_year_id = ay.id
                LEFT JOIN subjects sub ON sca.subject_id = sub.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE sca.id = ?
            ");
            $stmt->execute([$assignmentId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                $this->rollback();
                return formatResponse(false, null, 'Assignment not found');
            }

            // Check for existing active workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.reference_type = 'staff_assignment'
                AND wi.reference_id = ?
                AND wi.status IN ('in_progress', 'pending')
            ");
            $stmt->execute([$assignmentId]);

            if ($stmt->fetch()) {
                $this->rollback();
                return formatResponse(false, null, 'Active workflow already exists for this assignment');
            }

            // Prepare workflow data
            $workflowData = [
                'assignment_id' => $assignmentId,
                'staff_id' => $assignment['staff_id'],
                'staff_no' => $assignment['staff_no'],
                'staff_name' => $assignment['first_name'] . ' ' . $assignment['last_name'],
                'position' => $assignment['position'],
                'department' => $assignment['department_name'],
                'class' => $assignment['class_name'] . ' - ' . $assignment['stream_name'],
                'class_id' => $assignment['class_id'],
                'class_stream_id' => $assignment['class_stream_id'],
                'academic_year' => $assignment['academic_year'],
                'academic_year_id' => $assignment['academic_year_id'],
                'role' => $assignment['role'],
                'subject' => $assignment['subject_name'],
                'requested_by' => $userId
            ];

            // Start workflow
            $instanceId = $this->startWorkflow('staff_assignment', $assignmentId, $userId, $workflowData);

            // Update assignment status
            $stmt = $this->db->prepare("
                UPDATE staff_class_assignments 
                SET status = 'pending', workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$instanceId, $assignmentId]);

            $this->commit();
            $this->logAction(
                'create',
                $instanceId,
                "Initiated assignment workflow for {$assignment['first_name']} {$assignment['last_name']} to {$assignment['class_name']} as {$assignment['role']}"
            );

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'assignment_id' => $assignmentId,
                'staff_name' => $assignment['first_name'] . ' ' . $assignment['last_name'],
                'class' => $assignment['class_name'] . ' - ' . $assignment['stream_name'],
                'role' => $assignment['role'],
                'current_stage' => 'assignment_request',
                'next_stage' => 'validation',
                'status' => 'pending'
            ], 'Assignment workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Validate assignment (Stage 2)
     * @param int $instanceId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Validation data
     * @return array Response
     */
    public function validateAssignment($instanceId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);

            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $currentStage = $workflow['current_stage'];

            if ($currentStage !== 'validation') {
                return formatResponse(false, null, "Cannot validate assignment. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data_json'], true);

            $this->beginTransaction();

            // Use stored procedure to validate assignment
            $stmt = $this->db->prepare("CALL sp_validate_staff_assignment(?, ?, ?, ?, @is_valid, @error_message)");
            $stmt->execute([
                $workflowData['staff_id'],
                $workflowData['class_stream_id'],
                $workflowData['academic_year_id'],
                $workflowData['role']
            ]);
            $stmt->closeCursor();

            $result = $this->db->query("SELECT @is_valid AS is_valid, @error_message AS error_message")->fetch(PDO::FETCH_ASSOC);

            if (!$result['is_valid']) {
                // Validation failed - reject
                $this->advanceStage(
                    $instanceId,
                    'rejected',
                    'validation_failed',
                    $workflowData
                );

                // Update assignment status
                $stmt = $this->db->prepare("
                    UPDATE staff_class_assignments 
                    SET status = 'rejected', removal_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$result['error_message'], $workflowData['assignment_id']]);

                $this->commit();

                return formatResponse(false, [
                    'workflow_id' => $instanceId,
                    'status' => 'rejected',
                    'reason' => $result['error_message']
                ], 'Assignment validation failed: ' . $result['error_message']);
            }

            // Validation passed
            $workflowData['validation_passed'] = true;
            $workflowData['validated_by'] = $userId;
            $workflowData['validated_at'] = date('Y-m-d H:i:s');
            $workflowData['validation_remarks'] = $data['remarks'] ?? 'Assignment validated successfully';

            $this->advanceStage(
                $instanceId,
                'head_teacher_approval',
                'validation_passed',
                $workflowData
            );

            $this->commit();

            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'current_stage' => 'head_teacher_approval',
                'status' => 'pending_approval'
            ], 'Assignment validated, forwarded to Head Teacher for approval');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Head Teacher approval (Stage 3)
     * @param int $instanceId Workflow instance ID
     * @param int $userId User performing action (Head Teacher)
     * @param string $action 'approve' or 'reject'
     * @param array $data Approval data
     * @return array Response
     */
    public function headTeacherApproval($instanceId, $userId, $action, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($instanceId);
            if (!$workflow) {
                return formatResponse(false, null, 'Workflow instance not found');
            }
            $currentStage = $workflow['current_stage'];
            if ($currentStage !== 'head_teacher_approval') {
                return formatResponse(false, null, "Cannot perform head teacher approval. Current stage is: {$currentStage}");
            }
            // Validate Head Teacher or Admin role
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || $user['role'] !== 'Head Teacher') {
                return formatResponse(false, null, 'Only Head Teacher can approve assignments');
            }
            $workflowData = json_decode($workflow['data_json'], true);
            $this->beginTransaction();
            if ($action === 'reject') {
                // Reject assignment
                $this->advanceStage(
                    $instanceId,
                    'rejected',
                    'head_teacher_rejected',
                    $workflowData
                );
                // Update assignment status
                $stmt = $this->db->prepare("
                    UPDATE staff_class_assignments 
                    SET status = 'rejected', removal_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['remarks'] ?? 'Rejected by Head Teacher', $workflowData['assignment_id']]);
                $this->commit();
                return formatResponse(true, [
                    'workflow_id' => $instanceId,
                    'status' => 'rejected',
                    'stage' => 'rejected'
                ], 'Assignment rejected by Head Teacher');
            }
            // Approve
            $workflowData['approved_by'] = $userId;
            $workflowData['approved_at'] = date('Y-m-d H:i:s');
            $workflowData['approval_remarks'] = $data['remarks'] ?? null;
            $this->advanceStage(
                $instanceId,
                'approved',
                'head_teacher_approved',
                $workflowData
            );
            // Update assignment status to active
            $stmt = $this->db->prepare("
                UPDATE staff_class_assignments 
                SET status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$workflowData['assignment_id']]);
            $this->commit();
            return formatResponse(true, [
                'workflow_id' => $instanceId,
                'status' => 'approved',
                'stage' => 'approved'
            ], 'Assignment approved and activated');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return [];
        }
    }

    /**
     * Get assignment workload analysis
     * @param int $staffId Staff ID
     * @param int $academicYearId Academic year ID
     * @return array Response
     */
    public function getWorkloadAnalysis($staffId, $academicYearId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vw_staff_workload 
                WHERE staff_id = ? AND academic_year_id = ?
            ");
            $stmt->execute([$staffId, $academicYearId]);
            $workload = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $workload ?? [], 'Workload analysis retrieved');

        } catch (Exception $e) {
            $this->handleException($e);
            return [];
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
            'assignment_request' => ['validation', 'rejected'],
            'validation' => ['head_teacher_approval', 'rejected'],
            'head_teacher_approval' => ['approved', 'rejected']
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
            case 'assignment_request':
                // Initial submission - no additional processing
                return true;

            case 'validation':
                // Notify validation team (admin/HR)
                $this->createNotification(
                    $instanceId,
                    null,
                    'Assignment Pending Validation',
                    "{$data['staff_name']} to {$data['class']} as {$data['role']}",
                    'workflow'
                );
                return true;

            case 'head_teacher_approval':
                // Notify Head Teacher
                $this->createNotification(
                    $instanceId,
                    null,
                    'Assignment Pending Approval',
                    "{$data['staff_name']} to {$data['class']} as {$data['role']}",
                    'workflow'
                );
                return true;

            case 'approved':
                // Notify staff member and head teacher
                $this->createNotification(
                    $instanceId,
                    $data['staff_id'],
                    'Assignment Approved',
                    "You have been assigned to {$data['class']} as {$data['role']}",
                    'workflow'
                );
                return true;

            case 'rejected':
                // Notify requester
                $this->createNotification(
                    $instanceId,
                    $data['requested_by'],
                    'Assignment Rejected',
                    "Assignment of {$data['staff_name']} to {$data['class']} has been rejected",
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
