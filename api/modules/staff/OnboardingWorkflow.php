<?php
namespace App\API\Modules\staff;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Onboarding Workflow
 * 
 * Multi-stage approval workflow for staff onboarding process
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. documentation - Submit required documents
 * 2. orientation - Complete orientation sessions
 * 3. system_access - Grant system credentials
 * 4. completion - Finalize onboarding
 */
class OnboardingWorkflow extends WorkflowHandler
{
    protected $workflowType = 'staff_onboarding';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('staff_onboarding');
    }

    /**
     * Initiate staff onboarding workflow
     * @param int $staffId Staff ID
     * @param int $userId User initiating workflow
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateOnboarding($staffId, $userId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Validate staff exists
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id = ?
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Staff member not found');
            }

            // Check for existing active onboarding workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.reference_type = 'staff_onboarding'
                AND wi.reference_id = ?
                AND wi.status IN ('in_progress', 'pending')
            ");
            $stmt->execute([$staffId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active onboarding workflow already exists for this staff member');
            }

            // Start workflow
            $workflowData = [
                'staff_id' => $staffId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_no' => $staff['staff_no'],
                'position' => $staff['position'],
                'department' => $staff['department_name'],
                'employment_date' => $staff['employment_date'],
                'mentor_id' => $data['mentor_id'] ?? null,
                'expected_completion_date' => $data['expected_completion_date'] ?? null
            ];

            $result = $this->startWorkflow('staff_onboarding', $staffId, $userId, $workflowData);

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            $workflowId = $result['data']['workflow_id'];

            // Create onboarding record in staff_onboarding table
            $stmt = $this->db->prepare("
                INSERT INTO staff_onboarding (
                    staff_id, workflow_instance_id, mentor_id, 
                    expected_end_date, status, created_at
                ) VALUES (?, ?, ?, ?, 'in_progress', NOW())
            ");
            $stmt->execute([
                $staffId,
                $workflowId,
                $data['mentor_id'] ?? null,
                $data['expected_completion_date'] ?? null
            ]);

            $onboardingId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $workflowId, "Initiated onboarding workflow for {$staff['first_name']} {$staff['last_name']}");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'onboarding_id' => $onboardingId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'current_stage' => 'documentation',
                'status' => 'in_progress'
            ], 'Onboarding workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Submit required documents (Stage 1)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Document data
     * @return array Response
     */
    public function submitDocuments($workflowId, $userId, $data = [])
    {
        try {
            // Validate current stage
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'documentation') {
                return formatResponse(false, null, "Cannot submit documents. Current stage is: {$currentStage}");
            }

            // Validate required documents
            $required = ['id_copy', 'certificates', 'bank_details'];
            $missing = [];

            foreach ($required as $doc) {
                if (empty($data['documents'][$doc])) {
                    $missing[] = $doc;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required documents: ' . implode(', ', $missing));
            }

            // Update workflow data with documents
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $workflowData['documents'] = $data['documents'];
            $workflowData['documents_submitted_by'] = $userId;
            $workflowData['documents_submitted_at'] = date('Y-m-d H:i:s');

            // Advance to orientation stage
            $this->advanceStage(
                $workflowId,
                'orientation',
                'documents_submitted',
                $workflowData
            );

            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Documents submitted successfully'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Complete orientation sessions (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Orientation data
     * @return array Response
     */
    public function completeOrientation($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'orientation') {
                return formatResponse(false, null, "Cannot complete orientation. Current stage is: {$currentStage}");
            }

            // Validate orientation completion
            $required = ['policy_review', 'department_intro', 'facility_tour'];
            $missing = [];

            foreach ($required as $item) {
                if (empty($data['orientation'][$item])) {
                    $missing[] = $item;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Incomplete orientation items: ' . implode(', ', $missing));
            }

            // Update workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $workflowData['orientation'] = $data['orientation'];
            $workflowData['orientation_completed_by'] = $userId;
            $workflowData['orientation_completed_at'] = date('Y-m-d H:i:s');

            // Advance to system_access stage
            $this->advanceStage(
                $workflowId,
                'system_access',
                'orientation_completed',
                $workflowData
            );

            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Orientation sessions completed successfully'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Grant system access (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data System access data
     * @return array Response
     */
    public function grantSystemAccess($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'system_access') {
                return formatResponse(false, null, "Cannot grant system access. Current stage is: {$currentStage}");
            }

            // Validate system access details
            $required = ['email', 'username', 'role'];
            $missing = [];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing system access details: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Update workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $workflowData['system_access'] = [
                'email' => $data['email'],
                'username' => $data['username'],
                'role' => $data['role'],
                'granted_by' => $userId,
                'granted_at' => date('Y-m-d H:i:s')
            ];

            // Create user account in users table
            $staffId = $workflow['data']['reference_id'];

            $stmt = $this->db->prepare("
                SELECT * FROM users WHERE staff_id = ?
            ");
            $stmt->execute([$staffId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'User account already exists for this staff member');
            }

            // Create user (password should be generated and sent via email in production)
            $defaultPassword = password_hash('temp_password_' . uniqid(), PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("
                INSERT INTO users (
                    username, email, password, role, staff_id, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $data['username'],
                $data['email'],
                $defaultPassword,
                $data['role'],
                $staffId
            ]);

            $userId_new = $this->db->lastInsertId();
            $workflowData['user_id'] = $userId_new;

            // Advance to completion stage and capture the result
            $advanceResult = $this->advanceStage(
                $workflowId,
                'completion',
                'system_access_granted',
                $workflowData
            );

            // If advancing the stage succeeded, commit and return a formatted response
            if (is_array($advanceResult) && isset($advanceResult['success']) && $advanceResult['success']) {
                $this->db->commit();
                $this->logAction('update', $workflowId, "System access granted and user account created (user_id: {$userId_new})");
                return formatResponse(
                    true,
                    [
                        'workflow_id' => $workflowId,
                        'user_id' => $userId_new
                    ],
                    $advanceResult['message'] ?? 'System access granted and user account created'
                );
            }

            // Otherwise rollback and return the advance result or a generic failure
            $this->db->rollBack();
            return $advanceResult ?? formatResponse(false, null, 'Failed to advance workflow stage after creating user');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Finalize onboarding (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Completion data
     * @return array Response
     */
    public function finalizeOnboarding($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'completion') {
                return formatResponse(false, null, "Cannot finalize onboarding. Current stage is: {$currentStage}");
            }

            $this->db->beginTransaction();

            // Update workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $workflowData['completed_by'] = $userId;
            $workflowData['completed_at'] = date('Y-m-d H:i:s');
            $workflowData['completion_notes'] = $data['completion_notes'] ?? '';

            // Complete workflow
            $result = $this->completeWorkflow(
                $workflowId,
                $userId,
                'Onboarding completed successfully',
                $workflowData
            );

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            // Update staff_onboarding record
            $staffId = $workflow['data']['reference_id'];

            $stmt = $this->db->prepare("
                UPDATE staff_onboarding SET
                    status = 'completed',
                    completion_date = NOW()
                WHERE staff_id = ? AND workflow_instance_id = ?
            ");
            $stmt->execute([$staffId, $workflowId]);

            $this->db->commit();
            $this->logAction('update', $workflowId, "Finalized onboarding workflow");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s')
            ], 'Onboarding finalized successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Reject onboarding at any stage
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param string $reason Rejection reason
     * @return array Response
     */
    public function rejectOnboarding($workflowId, $userId, $reason)
    {
        try {
            $this->db->beginTransaction();

            $this->cancelWorkflow($workflowId, $reason);

            // Update staff_onboarding record
            $workflow = $this->getWorkflowInstance($workflowId);
            $staffId = $workflow['data']['reference_id'];

            $stmt = $this->db->prepare("
                UPDATE staff_onboarding SET
                    status = 'cancelled',
                    cancellation_reason = ?
                WHERE staff_id = ? AND workflow_instance_id = ?
            ");
            $stmt->execute([$reason, $staffId, $workflowId]);

            $this->db->commit();
            $this->logAction('update', $workflowId, "Rejected onboarding workflow: {$reason}");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'status' => 'rejected'
            ], 'Onboarding rejected');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Validate workflow stage transition
     * @param string $fromStage Current stage
     * @param string $toStage Target stage
     * @param array $data Transition data
     * @return bool Whether transition is valid
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        // Define valid stage transitions for onboarding workflow
        $validTransitions = [
            'documentation' => ['orientation'],
            'orientation' => ['system_access'],
            'system_access' => ['completion'],
            'completion' => []
        ];

        // Check if transition is valid
        if (!isset($validTransitions[$fromStage])) {
            return false;
        }

        if (!in_array($toStage, $validTransitions[$fromStage])) {
            return false;
        }

        // Stage-specific validation
        switch ($fromStage) {
            case 'documentation':
                // Ensure documents are submitted
                $required = ['id_copy', 'certificates', 'bank_details'];
                foreach ($required as $doc) {
                    if (empty($data['documents'][$doc])) {
                        return false;
                    }
                }
                break;

            case 'orientation':
                // Ensure orientation items are completed
                $required = ['policy_review', 'department_intro', 'facility_tour'];
                foreach ($required as $item) {
                    if (empty($data['orientation'][$item])) {
                        return false;
                    }
                }
                break;

            case 'system_access':
                // Ensure system access details are provided
                $required = ['email', 'username', 'role'];
                foreach ($required as $field) {
                    if (empty($data[$field])) {
                        return false;
                    }
                }
                break;

            default:
                // No additional validation for other stages
                break;
        }

        return true;
    }

    /**
     * Process a workflow stage
     * @param int $instanceId Workflow instance ID
     * @param string $stage Stage to process
     * @param array $data Stage data
     * @return array Processing result
     */
    protected function processStage($instanceId, $stage, $data)
    {
        try {
            switch ($stage) {
                case 'documentation':
                    // Log document submission
                    $this->logAction('update', $instanceId, "Documents submitted for review");
                    return [
                        'success' => true,
                        'message' => 'Documents submitted successfully',
                        'next_stage' => 'orientation'
                    ];

                case 'orientation':
                    // Log orientation completion
                    $this->logAction('update', $instanceId, "Orientation sessions completed");
                    return [
                        'success' => true,
                        'message' => 'Orientation completed successfully',
                        'next_stage' => 'system_access'
                    ];

                case 'system_access':
                    // Log system access grant
                    $this->logAction('update', $instanceId, "System access granted");
                    return [
                        'success' => true,
                        'message' => 'System access granted successfully',
                        'next_stage' => 'completion'
                    ];

                case 'completion':
                    // Log onboarding completion
                    $this->logAction('update', $instanceId, "Onboarding process completed");
                    return [
                        'success' => true,
                        'message' => 'Onboarding completed successfully',
                        'next_stage' => null
                    ];

                default:
                    return [
                        'success' => false,
                        'message' => "Unknown stage: {$stage}"
                    ];
            }
        } catch (Exception $e) {
            $this->logError('processStage', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
