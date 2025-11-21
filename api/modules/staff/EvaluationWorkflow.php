<?php
namespace App\API\Modules\Staff;

use App\API\Includes\WorkflowHandler;
use App\API\Modules\Staff\StaffPerformanceManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Evaluation Workflow
 * 
 * Multi-stage approval workflow for performance evaluations
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. self_assessment - Staff completes self-review
 * 2. supervisor_review - Supervisor evaluates performance
 * 3. hr_review - HR validates and approves
 * 4. finalization - Complete evaluation process
 */
class EvaluationWorkflow extends WorkflowHandler
{
    protected $workflowType = 'staff_evaluation';
    private $performanceManager;

    public function __construct()
    {
        parent::__construct($this->workflowType);
        $this->performanceManager = new StaffPerformanceManager();
    }

    /**
     * Initiate evaluation workflow
     * @param int $staffId Staff ID
     * @param int $userId User initiating workflow
     * @param array $data Evaluation data
     * @return array Response
     */
    public function initiateEvaluation($staffId, $userId, $data = [])
    {
        try {
            $required = ['academic_year_id', 'review_period'];
            $missing = [];

            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->beginTransaction();

            // Validate staff exists
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name,
                       CONCAT(sup.first_name, ' ', sup.last_name) as supervisor_name, s.supervisor_id
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN staff sup ON s.supervisor_id = sup.id
                WHERE s.id = ? AND s.status = 'active'
            ");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->rollback();
                return formatResponse(false, null, 'Active staff member not found');
            }

            // Check for existing active evaluation workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.reference_type = 'staff_evaluation'
                AND wi.reference_id = ?
                AND wi.status IN ('in_progress', 'pending')
            ");
            $stmt->execute([$staffId]);

            if ($stmt->fetch()) {
                $this->rollback();
                return formatResponse(false, null, 'Active evaluation workflow already exists for this staff member');
            }

            // Create performance review using StaffPerformanceManager
            $reviewResult = $this->performanceManager->createReview([
                'staff_id' => $staffId,
                'academic_year_id' => $data['academic_year_id'],
                'review_period' => $data['review_period']
            ]);

            if (!$reviewResult['success']) {
                $this->rollback();
                return $reviewResult;
            }

            $reviewId = $reviewResult['data']['review_id'];

            // Start workflow
            $workflowData = [
                'staff_id' => $staffId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_no' => $staff['staff_no'],
                'position' => $staff['position'],
                'department' => $staff['department_name'],
                'supervisor_id' => $staff['supervisor_id'],
                'supervisor_name' => $staff['supervisor_name'],
                'academic_year_id' => $data['academic_year_id'],
                'review_period' => $data['review_period'],
                'review_id' => $reviewId
            ];

            $workflowId = $this->startWorkflow('staff_evaluation', $staffId, $workflowData);

            if (!$workflowId) {
                $this->rollback();
                return formatResponse(false, null, 'Failed to start workflow');
            }

            // Update performance review with workflow ID
            $stmt = $this->db->prepare("
                UPDATE staff_performance_reviews SET
                    workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$workflowId, $reviewId]);

            $this->commit();
            $this->logAction('create', $workflowId, "Initiated evaluation workflow for {$staff['first_name']} {$staff['last_name']}");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'review_id' => $reviewId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'current_stage' => 'self_assessment',
                'status' => 'in_progress'
            ], 'Evaluation workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }

    /**
     * Submit self-assessment (Stage 1)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Self-assessment data
     * @return array Response
     */
    public function submitSelfAssessment($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'self_assessment') {
                return formatResponse(false, null, "Cannot submit self-assessment. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $reviewId = $workflowData['review_id'];

            // Update KPIs with self-assessment
            if (!empty($data['kpi_assessments'])) {
                foreach ($data['kpi_assessments'] as $kpiId => $assessment) {
                    $this->performanceManager->updateKPI($reviewId, $kpiId, [
                        'self_rating' => $assessment['self_rating'] ?? null,
                        'self_comments' => $assessment['self_comments'] ?? ''
                    ]);
                }
            }

            $workflowData['self_assessment'] = [
                'submitted_by' => $userId,
                'submitted_at' => date('Y-m-d H:i:s'),
                'overall_comments' => $data['overall_comments'] ?? ''
            ];

            // Advance to supervisor_review stage
            $this->advanceStage(
                $workflowId,
                'supervisor_review',
                'self_assessment_submitted',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Self-assessment completed'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }

    /**
     * Submit supervisor review (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action (must be supervisor)
     * @param array $data Review data
     * @return array Response
     */
    public function supervisorReview($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'supervisor_review') {
                return formatResponse(false, null, "Cannot submit supervisor review. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Validate user is the supervisor
            if ($userId != ($workflowData['supervisor_id'] ?? null)) {
                return formatResponse(false, null, 'Only the assigned supervisor can complete this review');
            }

            $this->beginTransaction();

            $reviewId = $workflowData['review_id'];

            // Update KPIs with supervisor scores
            if (!empty($data['kpi_scores'])) {
                foreach ($data['kpi_scores'] as $kpiId => $score) {
                    $updateResult = $this->performanceManager->updateKPI($reviewId, $kpiId, [
                        'actual_value' => $score['actual_value'] ?? null,
                        'score' => $score['score'] ?? null,
                        'rating' => $score['rating'] ?? null,
                        'comments' => $score['comments'] ?? ''
                    ]);

                    if (!$updateResult['success']) {
                        $this->rollback();
                        return $updateResult;
                    }
                }
            }

            $workflowData['supervisor_review'] = [
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'overall_comments' => $data['overall_comments'] ?? '',
                'strengths' => $data['strengths'] ?? '',
                'areas_for_improvement' => $data['areas_for_improvement'] ?? ''
            ];

            // Advance to hr_review stage
            $this->advanceStage(
                $workflowId,
                'hr_review',
                'supervisor_review_completed',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'Supervisor review completed'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }

    /**
     * HR review and validation (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId HR user performing review
     * @param array $data HR review data
     * @return array Response
     */
    public function hrReview($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'hr_review') {
                return formatResponse(false, null, "Cannot submit HR review. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            $workflowData['hr_review'] = [
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'hr_comments' => $data['hr_comments'] ?? '',
                'approved' => $data['approved'] ?? true,
                'recommendations' => $data['recommendations'] ?? ''
            ];

            // Advance to finalization stage
            $this->advanceStage(
                $workflowId,
                'finalization',
                'hr_review_completed',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['remarks'] ?? 'HR review completed'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }

    /**
     * Finalize evaluation (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Finalization data
     * @return array Response
     */
    public function finalizeEvaluation($workflowId, $userId, $data = [])
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];

            if ($currentStage !== 'finalization') {
                return formatResponse(false, null, "Cannot finalize evaluation. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $reviewId = $workflowData['review_id'];

            // Complete the performance review
            $completeResult = $this->performanceManager->completeReview($reviewId, [
                'final_comments' => $data['final_comments'] ?? ''
            ]);

            if (!$completeResult['success']) {
                $this->rollback();
                return $completeResult;
            }

            $workflowData['finalized_by'] = $userId;
            $workflowData['finalized_at'] = date('Y-m-d H:i:s');
            $workflowData['final_grade'] = $completeResult['data']['performance_grade'] ?? null;

            // Complete workflow
            $complete = $this->completeWorkflow($workflowId, $workflowData);

            if (!$complete) {
                $this->rollback();
                return formatResponse(false, null, 'Failed to complete workflow');
            }

            $this->commit();
            $this->logAction('update', $workflowId, "Finalized evaluation workflow");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'review_id' => $reviewId,
                'status' => 'completed',
                'final_grade' => $workflowData['final_grade'],
                'completed_at' => date('Y-m-d H:i:s')
            ], 'Evaluation finalized successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }

    /**
     * Reject evaluation at any stage
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param string $reason Rejection reason
     * @return array Response
     */
    public function rejectEvaluation($workflowId, $userId, $reason)
    {
        try {
            $this->beginTransaction();

            $this->cancelWorkflow($workflowId, $reason);

            // Update performance review status
            $workflow = $this->getWorkflowInstance($workflowId);
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $reviewId = $workflowData['review_id'];

            $stmt = $this->db->prepare("
                UPDATE staff_performance_reviews SET
                    status = 'cancelled'
                WHERE id = ?
            ");
            $stmt->execute([$reviewId]);

            $this->commit();
            $this->logAction('update', $workflowId, "Rejected evaluation workflow: {$reason}");

            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'review_id' => $reviewId,
                'status' => 'rejected'
            ], 'Evaluation rejected');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            $this->handleException($e);
            return formatResponse(false, null, 'Internal server error');
        }
    }
}
