<?php
namespace App\API\Modules\activities\workflows;

require_once __DIR__ . '/../../../includes/WorkflowHandler.php';
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;

/**
 * ActivityRegistrationWorkflow - Manages student enrollment process for activities
 * 
 * Workflow Stages:
 * 1. apply - Student/parent submits application
 * 2. review - Coordinator reviews application
 * 3. approve/reject - Approval decision made
 * 4. confirm - Student confirms participation
 * 5. active - Student actively participating
 * 6. complete - Activity/participation completed
 */
class ActivityRegistrationWorkflow extends WorkflowHandler
{
    private $workflowType = 'activity_registration';

    public function __construct()
    {
        parent::__construct('activity_registration_workflow');
    }

    /**
     * Initiate registration workflow
     * 
     * @param array $data Registration data (student_id, activity_id, notes)
     * @param int $userId User initiating the workflow
     * @return array Workflow instance ID and details
     */
    public function initiateRegistration($data, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] initiateRegistration called. userId=" . var_export($userId, true) . ", data=" . json_encode($data));
        try {
            // Validate required fields
            $required = ['student_id', 'activity_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Check if activity exists and is accepting registrations
            $stmt = $this->db->prepare("
                SELECT id, title, status, max_participants 
                FROM activities 
                WHERE id = ?
            ");
            $stmt->execute([$data['activity_id']]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            if (!in_array($activity['status'], ['planned', 'ongoing'])) {
                throw new Exception('Activity is not accepting registrations');
            }

            // Check student exists
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, admission_no 
                FROM students 
                WHERE id = ?
            ");
            $stmt->execute([$data['student_id']]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                throw new Exception('Student not found');
            }

            // Check if student already has an active/pending registration
            $stmt = $this->db->prepare("
                SELECT id, status 
                FROM activity_participants 
                WHERE activity_id = ? AND student_id = ? 
                AND status IN ('active', 'pending')
            ");
            $stmt->execute([$data['activity_id'], $data['student_id']]);
            if ($stmt->fetch()) {
                throw new Exception('Student already has an active or pending registration for this activity');
            }

            $this->db->beginTransaction();

            // Create workflow instance
            $workflowData = [
                'student_id' => $data['student_id'],
                'student_name' => "{$student['first_name']} {$student['last_name']}",
                'student_admission_no' => $student['admission_no'],
                'activity_id' => $data['activity_id'],
                'activity_title' => $activity['title'],
                'application_notes' => $data['notes'] ?? null,
                'role' => $data['role'] ?? 'participant'
            ];


            $stmt = $this->db->prepare("
                INSERT INTO workflow_instances (
                    workflow_id,
                    reference_type,
                    reference_id,
                    current_stage,
                    status,
                    started_by,
                    data_json,
                    started_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $this->workflow_id,
                'activity_participant',
                $data['activity_id'],
                'apply',
                'pending',
                $userId,
                json_encode($workflowData)
            ]);

            $workflowId = $this->db->lastInsertId();

            // Create participant record with pending status
            $stmt = $this->db->prepare("
                INSERT INTO activity_participants (
                    activity_id,
                    student_id,
                    role,
                    status,
                    notes,
                    registered_by,
                    registered_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $data['activity_id'],
                $data['student_id'],
                $data['role'] ?? 'participant',
                'pending',
                $data['notes'] ?? null,
                $userId
            ]);

            $participantId = $this->db->lastInsertId();

            // Record workflow history
            $this->recordHistory($workflowId, 'apply', 'Application submitted', $userId);

            $this->db->commit();

            $this->logAction(
                'create',
                $workflowId,
                "Initiated registration workflow for {$student['first_name']} {$student['last_name']} - {$activity['title']}"
            );

            // TODO: Send notification to activity coordinator

            return [
                'success' => true,
                'data' => [
                    'workflow_id' => $workflowId,
                    'participant_id' => $participantId,
                    'current_stage' => 'apply',
                    'status' => 'pending'
                ],
                'message' => 'Registration application submitted successfully'
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, 'Failed to initiate registration workflow');
            throw $e;
        }
    }

    /**
     * Review application
     * 
     * @param int $workflowId Workflow instance ID
     * @param array $data Review data
     * @param int $userId Reviewer user ID
     * @return array Review result
     */
    public function reviewApplication($workflowId, $data, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] reviewApplication called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if ($workflow['current_stage'] !== 'apply') {
                throw new Exception('Workflow is not in apply stage');
            }

            $this->db->beginTransaction();

            // Update workflow stage
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'review',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.reviewer_notes', ?)
                WHERE id = ?
            ");
            $stmt->execute([$data['notes'] ?? null, $workflowId]);

            // Record history
            $this->recordHistory($workflowId, 'review', 'Application under review', $userId);

            $this->db->commit();

            $this->logAction('update', $workflowId, 'Application moved to review stage');

            return [
                'success' => true,
                'message' => 'Application moved to review',
                'data' => ['current_stage' => 'review']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to review application $workflowId");
            throw $e;
        }
    }

    /**
     * Approve registration
     * 
     * @param int $workflowId Workflow instance ID
     * @param array $data Approval data
     * @param int $userId Approver user ID
     * @return array Approval result
     */
    public function approveRegistration($workflowId, $data, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] approveRegistration called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!in_array($workflow['current_stage'], ['apply', 'review'])) {
                throw new Exception('Workflow must be in apply or review stage to approve');
            }

            $metadata = json_decode($workflow['data_json'], true);

            $this->db->beginTransaction();

            // Update workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'approve',
                    status = 'approved',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.approval_notes', ?, '$.approved_by', ?, '$.approved_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$data['notes'] ?? null, $userId, $workflowId]);

            // Update participant status to confirmed (waiting for student confirmation)
            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'confirmed'
                WHERE activity_id = ? AND student_id = ?
            ");
            $stmt->execute([$metadata['activity_id'], $metadata['student_id']]);

            // Record history
            $this->recordHistory($workflowId, 'approve', 'Registration approved', $userId);

            $this->db->commit();

            $this->logAction('update', $workflowId, 'Registration approved');

            // TODO: Send notification to student/parent

            return [
                'success' => true,
                'message' => 'Registration approved successfully',
                'data' => ['current_stage' => 'approve', 'status' => 'approved']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to approve registration $workflowId");
            throw $e;
        }
    }

    /**
     * Reject registration
     * 
     * @param int $workflowId Workflow instance ID
     * @param string $reason Rejection reason
     * @param int $userId Rejector user ID
     * @return array Rejection result
     */
    public function rejectRegistration($workflowId, $reason, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] rejectRegistration called. userId=" . var_export($userId, true) . ", reason=" . var_export($reason, true) . ", workflowId=" . var_export($workflowId, true));
        $transactionStarted = false;
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!in_array($workflow['current_stage'], ['apply', 'review'])) {
                throw new Exception('Workflow must be in apply or review stage to reject');
            }

            $metadata = json_decode($workflow['data_json'], true);

            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $transactionStarted = true;
            }

            // Update workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'reject',
                    status = 'rejected',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.rejection_reason', ?, '$.rejected_by', ?, '$.rejected_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$reason, $userId, $workflowId]);

            // Update participant status
            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'rejected',
                    notes = CONCAT(COALESCE(notes, ''), ' | Rejected: ', ?)
                WHERE activity_id = ? AND student_id = ?
            ");
            $stmt->execute([$reason, $metadata['activity_id'], $metadata['student_id']]);

            // Record history
            $this->recordHistory($workflowId, 'reject', "Registration rejected: $reason", $userId);

            if ($transactionStarted) {
                $this->db->commit();
            }

            $this->logAction('update', $workflowId, 'Registration rejected');

            // TODO: Send notification to student/parent

            return [
                'success' => true,
                'message' => 'Registration rejected',
                'data' => ['current_stage' => 'reject', 'status' => 'rejected']
            ];

        } catch (Exception $e) {
            if ($transactionStarted && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, "Failed to reject registration $workflowId");
            throw $e;
        }
    }

    /**
     * Student confirms participation
     * 
     * @param int $workflowId Workflow instance ID
     * @param int $userId User confirming
     * @return array Confirmation result
     */
    public function confirmParticipation($workflowId, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] confirmParticipation called. userId=" . var_export($userId, true) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if ($workflow['current_stage'] !== 'approve') {
                throw new Exception('Registration must be approved before confirmation');
            }

            $metadata = json_decode($workflow['data_json'], true);

            $this->db->beginTransaction();

            // Update workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'confirm',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.confirmed_by', ?, '$.confirmed_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            // Record history
            $this->recordHistory($workflowId, 'confirm', 'Participation confirmed', $userId);

            $this->db->commit();

            // Auto-activate
            return $this->activateParticipation($workflowId, $userId);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to confirm participation $workflowId");
            throw $e;
        }
    }

    /**
     * Activate participation
     * 
     * @param int $workflowId Workflow instance ID
     * @param int $userId User activating
     * @return array Activation result
     */
    public function activateParticipation($workflowId, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] activateParticipation called. userId=" . var_export($userId, true) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $metadata = json_decode($workflow['data_json'], true);

            $this->db->beginTransaction();

            // Update workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'active',
                    status = 'active',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.activated_by', ?, '$.activated_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            // Update participant status
            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'active'
                WHERE activity_id = ? AND student_id = ?
            ");
            $stmt->execute([$metadata['activity_id'], $metadata['student_id']]);

            // Record history
            $this->recordHistory($workflowId, 'active', 'Participation activated', $userId);

            $this->db->commit();

            $this->logAction('update', $workflowId, 'Participation activated');

            return [
                'success' => true,
                'message' => 'Participation activated successfully',
                'data' => ['current_stage' => 'active', 'status' => 'active']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to activate participation $workflowId");
            throw $e;
        }
    }

    /**
     * Complete participation
     * 
     * @param int $workflowId Workflow instance ID
     * @param array $data Completion data
     * @param int $userId User completing
     * @return array Completion result
     */
    public function completeParticipation($workflowId, $data, $userId)
    {
        error_log("[ActivityRegistrationWorkflow] completeParticipation called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $metadata = json_decode($workflow['data_json'], true);

            $this->db->beginTransaction();

            // Update workflow
            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'complete',
                    status = 'completed',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.completion_notes', ?, '$.completed_by', ?, '$.completed_at', NOW()),
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$data['notes'] ?? null, $userId, $workflowId]);

            // Update participant status
            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'completed'
                WHERE activity_id = ? AND student_id = ?
            ");
            $stmt->execute([$metadata['activity_id'], $metadata['student_id']]);

            // Record history
            $this->recordHistory($workflowId, 'complete', 'Participation completed', $userId);

            $this->db->commit();

            $this->logAction('update', $workflowId, 'Participation completed');

            return [
                'success' => true,
                'message' => 'Participation completed successfully',
                'data' => ['current_stage' => 'complete', 'status' => 'completed']
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to complete participation $workflowId");
            throw $e;
        }
    }
    /**
     * Record workflow history
     */
    private function recordHistory($workflowId, $stage, $action, $userId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO workflow_history (
                workflow_id,
                stage,
                action,
                performed_by,
                created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$workflowId, $stage, $action, $userId]);
    }
}
