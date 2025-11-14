<?php
namespace App\API\Modules\Academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Curriculum Planning Workflow - CBC-Aligned
 * 
 * Manages curriculum planning and scheme of work development aligned to CBC.
 * Ensures learning outcomes, competencies, and assessments are properly mapped.
 * 
 * CBC Curriculum Requirements:
 * - Learning Areas: 12 learning areas for primary level
 * - Learning Outcomes: Specific, measurable, achievable outcomes per strand
 * - Core Competencies: Integration of 8 competencies across subjects
 * - Core Values: Infusion of 6 values throughout curriculum
 * - Assessment Integration: CA, SBA, SA alignment
 * - Cross-curricular Links: Connections between learning areas
 * 
 * Planning Levels:
 * - Annual Planning: Year-long curriculum overview
 * - Term Planning: Termly schemes of work
 * - Unit Planning: Detailed unit/topic breakdown
 * - Lesson Planning: Individual lesson designs
 * 
 * Workflow Stages:
 * 1. Review Framework - Review CBC curriculum framework and requirements
 * 2. Map Outcomes - Map learning outcomes to strands and competencies
 * 3. Create Schemes - Develop schemes of work with timelines
 * 4. Review & Approve - Academic team validates and approves
 */
class CurriculumPlanningWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('curriculum_planning');
    }
    
    protected function getWorkflowDefinitionCode(): string {
        return 'curriculum_planning';
    }

    /**
     * Stage 1: Review curriculum framework
     * 
     * @param array $framework {
     *   @type int $subject_id Subject/learning area ID
     *   @type int $grade_level Target grade level
     *   @type int $academic_year Academic year
     *   @type string $planning_level Level: annual, term, unit, lesson
     *   @type int $term_id Term ID (for term-level planning)
     *   @type string $curriculum_version CBC version (e.g., "CBC 2017")
     *   @type array $strand_ids Curriculum strands to cover
     *   @type array $competency_focus Primary competencies to emphasize
     *   @type array $value_focus Core values to integrate
     *   @type string $notes Planning notes and considerations
     * }
     * @return array Response with workflow instance
     */
    public function reviewFramework(array $framework): array {
        try {
            // Validation
            $required = ['subject_id', 'grade_level', 'academic_year', 'planning_level'];
            foreach ($required as $field) {
                if (!isset($framework[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Validate planning level
            $validLevels = ['annual', 'term', 'unit', 'lesson'];
            if (!in_array($framework['planning_level'], $validLevels)) {
                return formatResponse(false, null, 'Invalid planning level');
            }

            $this->db->beginTransaction();

            // Get subject information
            $subjectStmt = $this->db->prepare("SELECT * FROM subjects WHERE id = :id");
            $subjectStmt->execute(['id' => (int)$framework['subject_id']]);
            $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                return formatResponse(false, null, 'Subject not found');
            }

            // Get relevant learning outcomes for this subject and grade
            $outcomesStmt = $this->db->prepare(
                "SELECT * FROM learning_outcomes 
                WHERE learning_area_id = :learning_area_id 
                AND grade_level = :grade_level"
            );
            $outcomesStmt->execute([
                'learning_area_id' => (int)$subject['learning_area_id'],
                'grade_level' => $framework['grade_level'],
            ]);
            $outcomes = $outcomesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Get competencies if focus areas specified
            $competencies = [];
            if (!empty($framework['competency_focus'])) {
                $compIds = array_map('intval', $framework['competency_focus']);
                $placeholders = str_repeat('?,', count($compIds) - 1) . '?';
                $compStmt = $this->db->prepare(
                    "SELECT * FROM core_competencies 
                    WHERE id IN ($placeholders) 
                    AND status = 'active'"
                );
                $compStmt->execute($compIds);
                $competencies = $compStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Get values if focus areas specified
            $values = [];
            if (!empty($framework['value_focus'])) {
                $valueIds = array_map('intval', $framework['value_focus']);
                $placeholders = str_repeat('?,', count($valueIds) - 1) . '?';
                $valueStmt = $this->db->prepare(
                    "SELECT * FROM core_values 
                    WHERE id IN ($placeholders) 
                    AND status = 'active'"
                );
                $valueStmt->execute($valueIds);
                $values = $valueStmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Prepare workflow data
            $workflowData = [
                'subject_id' => (int)$framework['subject_id'],
                'subject_name' => $subject['name'],
                'grade_level' => $framework['grade_level'],
                'academic_year' => (int)$framework['academic_year'],
                'planning_level' => $framework['planning_level'],
                'term_id' => isset($framework['term_id']) ? (int)$framework['term_id'] : null,
                'curriculum_version' => $framework['curriculum_version'] ?? 'CBC 2017',
                'strand_ids' => $framework['strand_ids'] ?? [],
                'competency_focus' => $competencies,
                'value_focus' => $values,
                'available_outcomes' => $outcomes,
                'notes' => $framework['notes'] ?? '',
                'outcome_mapping' => [],
                'scheme_of_work' => [],
                'approval_status' => 'draft',
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                'curriculum_plan',
                $framework['subject_id'],
                $workflowData,
                "Curriculum planning started: {$subject['name']} - Grade {$framework['grade_level']}"
            );

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance['id'],
                'workflow_data' => $workflowData,
                'outcomes_available' => count($outcomes),
            ], 'Framework review completed');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Map learning outcomes
     * 
     * Map learning outcomes to curriculum strands, competencies, and assessment strategies.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $mapping Array of outcome mappings {
     *   @type int $outcome_id Learning outcome ID
     *   @type int $strand_id Curriculum strand
     *   @type array $competency_ids Competencies addressed
     *   @type array $value_ids Values integrated
     *   @type string $assessment_strategy CA/SBA/SA approaches
     *   @type int $weeks_allocated Time allocation
     *   @type array $resources Required teaching resources
     *   @type array $cross_curricular Cross-curricular links
     * }
     * @return array Response with mapping summary
     */
    public function mapOutcomes(int $instance_id, array $mapping): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            if (empty($mapping)) {
                return formatResponse(false, null, 'No outcome mappings provided');
            }

            // Validate that outcomes belong to this subject/grade
            $availableOutcomeIds = array_column($data['available_outcomes'] ?? [], 'id');
            
            $outcomeMapping = [];
            $totalWeeks = 0;

            foreach ($mapping as $map) {
                $outcomeId = (int)($map['outcome_id'] ?? 0);
                
                if (!in_array($outcomeId, $availableOutcomeIds)) {
                    continue; // Skip invalid outcomes
                }

                $weeksAllocated = isset($map['weeks_allocated']) ? (int)$map['weeks_allocated'] : 1;
                $totalWeeks += $weeksAllocated;

                $outcomeMapping[] = [
                    'outcome_id' => $outcomeId,
                    'strand_id' => isset($map['strand_id']) ? (int)$map['strand_id'] : null,
                    'competency_ids' => $map['competency_ids'] ?? [],
                    'value_ids' => $map['value_ids'] ?? [],
                    'assessment_strategy' => $map['assessment_strategy'] ?? 'CA',
                    'weeks_allocated' => $weeksAllocated,
                    'resources' => $map['resources'] ?? [],
                    'cross_curricular' => $map['cross_curricular'] ?? [],
                    'mapped_at' => date('Y-m-d H:i:s'),
                ];
            }

            $data['outcome_mapping'] = $outcomeMapping;
            $data['total_outcomes_mapped'] = count($outcomeMapping);
            $data['total_weeks_allocated'] = $totalWeeks;

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Mapped {count($outcomeMapping)} learning outcomes ({$totalWeeks} weeks)"
            );

            return formatResponse(true, [
                'total_outcomes_mapped' => count($outcomeMapping),
                'total_weeks_allocated' => $totalWeeks,
                'outcome_mapping' => $outcomeMapping,
            ], 'Learning outcomes mapped successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Create scheme of work
     * 
     * Develop detailed scheme of work with week-by-week breakdown.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $scheme {
     *   @type string $title Scheme title
     *   @type string $start_date Start date
     *   @type string $end_date End date
     *   @type array $weekly_plans Array of weekly plans {
     *     @type int $week_number Week number
     *     @type array $outcome_ids Learning outcomes for the week
     *     @type string $topic Topic/unit title
     *     @type array $subtopics Subtopic breakdown
     *     @type array $learning_activities Suggested activities
     *     @type array $assessment_methods Assessment approaches
     *     @type array $resources Teaching/learning resources
     *     @type string $homework Homework assignments
     *     @type string $notes Teacher notes
     *   }
     *   @type array $assessment_plan Overall assessment strategy
     *   @type array $differentiation Differentiation strategies
     * }
     * @return array Response with scheme details
     */
    public function createScheme(int $instance_id, array $scheme): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            if (empty($data['outcome_mapping'])) {
                return formatResponse(false, null, 'Please map learning outcomes before creating scheme');
            }

            $required = ['title', 'start_date', 'end_date', 'weekly_plans'];
            foreach ($required as $field) {
                if (!isset($scheme[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            $weeklyPlans = $scheme['weekly_plans'] ?? [];
            
            if (empty($weeklyPlans)) {
                return formatResponse(false, null, 'No weekly plans provided');
            }

            // Validate and enrich weekly plans
            $enrichedPlans = [];
            foreach ($weeklyPlans as $plan) {
                $enrichedPlans[] = [
                    'week_number' => (int)($plan['week_number'] ?? 0),
                    'outcome_ids' => $plan['outcome_ids'] ?? [],
                    'topic' => $plan['topic'] ?? '',
                    'subtopics' => $plan['subtopics'] ?? [],
                    'learning_activities' => $plan['learning_activities'] ?? [],
                    'assessment_methods' => $plan['assessment_methods'] ?? [],
                    'resources' => $plan['resources'] ?? [],
                    'homework' => $plan['homework'] ?? '',
                    'notes' => $plan['notes'] ?? '',
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }

            $schemeOfWork = [
                'title' => $scheme['title'],
                'start_date' => $scheme['start_date'],
                'end_date' => $scheme['end_date'],
                'total_weeks' => count($enrichedPlans),
                'weekly_plans' => $enrichedPlans,
                'assessment_plan' => $scheme['assessment_plan'] ?? [],
                'differentiation' => $scheme['differentiation'] ?? [],
                'created_by' => $this->user_id,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $data['scheme_of_work'] = $schemeOfWork;

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Created scheme of work: {count($enrichedPlans)} weeks planned"
            );

            return formatResponse(true, [
                'scheme_of_work' => $schemeOfWork,
                'total_weeks' => count($enrichedPlans),
            ], 'Scheme of work created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Review and approve
     * 
     * Academic coordinator/head of department reviews and approves the curriculum plan.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $review {
     *   @type bool $approved Approve or request revision
     *   @type string $reviewer_role Role: hod, academic_coordinator, principal
     *   @type array $feedback Feedback comments
     *   @type array $revision_requests Specific revision requests
     *   @type string $approval_notes Final approval notes
     * }
     * @return array Response with approval status
     */
    public function reviewAndApprove(int $instance_id, array $review): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            if (empty($data['scheme_of_work'])) {
                return formatResponse(false, null, 'No scheme of work available for review');
            }

            $approved = $review['approved'] ?? false;
            $reviewerRole = $review['reviewer_role'] ?? 'hod';

            if (!$approved) {
                // Request revision
                $data['approval_status'] = 'revision_requested';
                $data['revision_requests'] = $review['revision_requests'] ?? [];
                $data['feedback'] = $review['feedback'] ?? [];
                $data['reviewed_by'] = $this->user_id;
                $data['reviewed_at'] = date('Y-m-d H:i:s');

                $this->advanceStage(
                    $instance_id,
                    json_encode($data),
                    "Revision requested by {$reviewerRole}"
                );

                return formatResponse(true, [
                    'approval_status' => 'revision_requested',
                    'revision_requests' => $data['revision_requests'],
                ], 'Revision requested');
            }

            // Approval path
            $data['approval_status'] = 'approved';
            $data['approved_by'] = $this->user_id;
            $data['approved_at'] = date('Y-m-d H:i:s');
            $data['approver_role'] = $reviewerRole;
            $data['approval_notes'] = $review['approval_notes'] ?? '';
            $data['feedback'] = $review['feedback'] ?? [];

            // Log curriculum plan approval
            $this->logAction(
                'curriculum_plan_approved',
                "Curriculum plan approved: {$data['subject_name']} - Grade {$data['grade_level']}",
                [
                    'subject_id' => $data['subject_id'],
                    'grade_level' => $data['grade_level'],
                    'planning_level' => $data['planning_level'],
                    'total_weeks' => $data['scheme_of_work']['total_weeks'] ?? 0,
                ]
            );

            // Complete workflow
            $this->completeWorkflow(
                $instance_id,
                json_encode($data),
                "Curriculum plan approved by {$reviewerRole}"
            );

            return formatResponse(true, [
                'approval_status' => 'approved',
                'approved_by' => $reviewerRole,
                'scheme_summary' => [
                    'subject' => $data['subject_name'],
                    'grade_level' => $data['grade_level'],
                    'total_weeks' => $data['scheme_of_work']['total_weeks'] ?? 0,
                    'outcomes_mapped' => $data['total_outcomes_mapped'] ?? 0,
                ],
            ], 'Curriculum plan approved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get curriculum plan details
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with plan details
     */
    public function getPlanDetails(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];

            return formatResponse(true, [
                'subject_name' => $data['subject_name'] ?? '',
                'grade_level' => $data['grade_level'] ?? '',
                'academic_year' => $data['academic_year'] ?? '',
                'planning_level' => $data['planning_level'] ?? '',
                'approval_status' => $data['approval_status'] ?? 'draft',
                'outcomes_mapped' => $data['total_outcomes_mapped'] ?? 0,
                'weeks_allocated' => $data['total_weeks_allocated'] ?? 0,
                'scheme_of_work' => $data['scheme_of_work'] ?? null,
                'outcome_mapping' => $data['outcome_mapping'] ?? [],
            ], 'Curriculum plan details retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
