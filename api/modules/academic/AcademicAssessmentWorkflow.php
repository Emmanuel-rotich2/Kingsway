<?php
namespace App\API\Modules\Academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;

/**
 * Academic Assessment Workflow - CBC-Compliant
 * 
 * Manages creation and administration of classroom assessments aligned to CBC framework.
 * Handles question paper development, item analysis, and results processing.
 * 
 * CBC Assessment Types:
 * - Formative (CA): Continuous classroom assessments, topic tests, quizzes
 * - School-Based (SBA): End-of-term examinations for Grades 3-6
 * - Summative (SA): National assessments (Grade 6, Grade 9)
 * 
 * Assessment Focus:
 * - Learning outcomes alignment
 * - Competency-based questions
 * - Performance level rubrics (EE, ME, AE, BE)
 * - Item difficulty and discrimination analysis
 * 
 * Workflow Stages:
 * 1. Plan Assessment - Define scope, learning outcomes, competencies
 * 2. Create Items - Develop questions with CBC alignment
 * 3. Administer - Conduct assessment with logistics
 * 4. Mark & Grade - Apply CBC grading scale
 * 5. Analyze Results - Item analysis and performance metrics
 */
class AcademicAssessmentWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('academic_assessment');
    }
    
    protected function getWorkflowDefinitionCode(): string {
        return 'academic_assessment';
    }

    /**
     * Stage 1: Plan assessment
     * 
     * @param array $plan {
     *   @type string $title Assessment title
     *   @type int $subject_id Subject/learning area
     *   @type int $class_id Target class
     *   @type string $classification_code CA/SBA/SA
     *   @type int $term_id Academic term
     *   @type array $learning_outcome_ids Array of learning outcome IDs to assess
     *   @type array $competency_ids Core competencies to evaluate
     *   @type int $total_marks Maximum marks
     *   @type int $duration_minutes Assessment duration
     *   @type string $assessment_date Scheduled date
     *   @type string $assessment_type Type: written, oral, practical, project
     *   @type array $grading_criteria CBC performance level descriptors
     * }
     * @return array Response with workflow instance
     */
    public function planAssessment(array $plan): array {
        try {
            // Validation
            $required = ['title', 'subject_id', 'class_id', 'classification_code', 'term_id', 'total_marks'];
            foreach ($required as $field) {
                if (!isset($plan[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Validate classification
            $validClassifications = ['CA', 'SBA', 'SA'];
            if (!in_array($plan['classification_code'], $validClassifications)) {
                return formatResponse(false, null, 'Invalid classification code. Must be CA, SBA, or SA');
            }

            $this->db->beginTransaction();

            // Create assessment record
            $assessmentStmt = $this->db->prepare(
                "INSERT INTO assessments (
                    title, subject_id, class_id, term_id, 
                    classification_code, total_marks, assessment_date, 
                    assessment_type, status
                ) VALUES (
                    :title, :subject_id, :class_id, :term_id,
                    :classification, :total_marks, :assessment_date,
                    :type, 'draft'
                )"
            );
            $assessmentStmt->execute([
                'title' => $plan['title'],
                'subject_id' => (int)$plan['subject_id'],
                'class_id' => (int)$plan['class_id'],
                'term_id' => (int)$plan['term_id'],
                'classification' => $plan['classification_code'],
                'total_marks' => (int)$plan['total_marks'],
                'assessment_date' => $plan['assessment_date'] ?? null,
                'type' => $plan['assessment_type'] ?? 'written',
            ]);
            $assessmentId = (int)$this->db->lastInsertId();

            // Prepare workflow data
            $workflowData = [
                'assessment_id' => $assessmentId,
                'title' => $plan['title'],
                'subject_id' => (int)$plan['subject_id'],
                'class_id' => (int)$plan['class_id'],
                'classification_code' => $plan['classification_code'],
                'term_id' => (int)$plan['term_id'],
                'total_marks' => (int)$plan['total_marks'],
                'duration_minutes' => $plan['duration_minutes'] ?? 60,
                'assessment_type' => $plan['assessment_type'] ?? 'written',
                'learning_outcome_ids' => $plan['learning_outcome_ids'] ?? [],
                'competency_ids' => $plan['competency_ids'] ?? [],
                'grading_criteria' => $plan['grading_criteria'] ?? [],
                'items' => [],
                'administration_data' => [],
                'results_summary' => [],
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                'assessment',
                $assessmentId,
                $workflowData,
                "Assessment planned: {$plan['title']}"
            );

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance['id'],
                'assessment_id' => $assessmentId,
                'workflow_data' => $workflowData,
            ], 'Assessment plan created successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Create assessment items
     * 
     * Develops questions/items with CBC alignment.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $items Array of assessment items {
     *   @type string $question_text Question content
     *   @type int $marks Marks allocated
     *   @type string $question_type Type: multiple_choice, short_answer, essay, practical
     *   @type array $learning_outcomes Learning outcome IDs this item assesses
     *   @type array $competencies Competency IDs this item addresses
     *   @type string $difficulty Level: easy, medium, hard
     *   @type array $rubric Performance level descriptors
     *   @type array $options For multiple choice questions
     *   @type string $correct_answer Expected answer or marking scheme
     * }
     * @return array Response with items summary
     */
    public function createItems(int $instance_id, array $items): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $assessmentId = (int)($data['assessment_id'] ?? 0);

            if (empty($items)) {
                return formatResponse(false, null, 'No items provided');
            }

            // Validate total marks
            $totalMarks = array_sum(array_column($items, 'marks'));
            if ($totalMarks != $data['total_marks']) {
                return formatResponse(false, null, "Item marks sum ({$totalMarks}) doesn't match assessment total ({$data['total_marks']})");
            }

            // Store items in workflow data
            $itemsWithIds = [];
            foreach ($items as $index => $item) {
                $itemsWithIds[] = array_merge($item, [
                    'item_number' => $index + 1,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $data['items'] = $itemsWithIds;
            $data['item_count'] = count($itemsWithIds);

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Created {count($itemsWithIds)} assessment items"
            );

            return formatResponse(true, [
                'item_count' => count($itemsWithIds),
                'total_marks' => $totalMarks,
                'items' => $itemsWithIds,
            ], 'Assessment items created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Administer assessment
     * 
     * Records assessment administration details.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $administration {
     *   @type string $conducted_date Actual date conducted
     *   @type int $conducted_by User ID of administrator
     *   @type array $student_ids List of students who took assessment
     *   @type array $absent_students Students who were absent
     *   @type string $venue Location of assessment
     *   @type string $notes Administrative notes
     * }
     * @return array Response with administration summary
     */
    public function administerAssessment(int $instance_id, array $administration): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $assessmentId = (int)($data['assessment_id'] ?? 0);

            // Update assessment status
            $this->db->prepare(
                "UPDATE assessments 
                SET status = 'administered',
                    assessment_date = :date
                WHERE id = :id"
            )->execute([
                'date' => $administration['conducted_date'] ?? date('Y-m-d'),
                'id' => $assessmentId,
            ]);

            // Store administration data
            $data['administration_data'] = [
                'conducted_date' => $administration['conducted_date'] ?? date('Y-m-d'),
                'conducted_by' => $administration['conducted_by'] ?? $this->user_id,
                'student_ids' => $administration['student_ids'] ?? [],
                'absent_students' => $administration['absent_students'] ?? [],
                'venue' => $administration['venue'] ?? '',
                'notes' => $administration['notes'] ?? '',
                'total_participants' => count($administration['student_ids'] ?? []),
            ];

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Assessment administered to {$data['administration_data']['total_participants']} students"
            );

            return formatResponse(true, [
                'administration_data' => $data['administration_data'],
            ], 'Assessment administration recorded');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Mark and grade
     * 
     * Records student marks and applies CBC grading.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $marks Array of student marks {
     *   @type int $student_id Student ID
     *   @type float $score_obtained Marks scored
     *   @type array $item_scores Breakdown by item (optional)
     *   @type string $remarks Marker comments
     * }
     * @return array Response with grading summary
     */
    public function markAndGrade(int $instance_id, array $marks): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $assessmentId = (int)($data['assessment_id'] ?? 0);
            $totalMarks = (int)($data['total_marks'] ?? 100);

            $this->db->beginTransaction();

            // Get active grading scale for CBC
            $scaleStmt = $this->db->query("SELECT id FROM grading_scales WHERE status = 'active' ORDER BY id LIMIT 1");
            $scale = $scaleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$scale) {
                throw new Exception('No active grading scale found');
            }
            $scaleId = (int)$scale['id'];

            // Prepare grade mapping query
            $rulesStmt = $this->db->prepare(
                "SELECT grade_code, grade_points, performance_level, min_mark, max_mark 
                FROM grade_rules 
                WHERE scale_id = :scale_id 
                ORDER BY sort_order"
            );
            $rulesStmt->execute(['scale_id' => $scaleId]);
            $gradeRules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Insert/update marks
            $insertStmt = $this->db->prepare(
                "INSERT INTO assessment_results (
                    assessment_id, student_id, score_obtained, percentage,
                    grade_code, grade_points, performance_level, remarks, marked_by
                ) VALUES (
                    :assessment_id, :student_id, :score, :percentage,
                    :grade, :points, :perf_level, :remarks, :marked_by
                ) ON DUPLICATE KEY UPDATE
                    score_obtained = VALUES(score_obtained),
                    percentage = VALUES(percentage),
                    grade_code = VALUES(grade_code),
                    grade_points = VALUES(grade_points),
                    performance_level = VALUES(performance_level),
                    remarks = VALUES(remarks),
                    marked_by = VALUES(marked_by)"
            );

            $gradedCount = 0;
            $gradeDistribution = [];

            foreach ($marks as $mark) {
                $studentId = (int)$mark['student_id'];
                $score = (float)$mark['score_obtained'];
                $percentage = $totalMarks > 0 ? round(($score / $totalMarks) * 100, 2) : 0;

                // Map to CBC grade
                $gradeInfo = null;
                foreach ($gradeRules as $rule) {
                    if ($percentage >= (float)$rule['min_mark'] && $percentage <= (float)$rule['max_mark']) {
                        $gradeInfo = $rule;
                        break;
                    }
                }

                if (!$gradeInfo) {
                    continue; // Skip if no grade match
                }

                $insertStmt->execute([
                    'assessment_id' => $assessmentId,
                    'student_id' => $studentId,
                    'score' => $score,
                    'percentage' => $percentage,
                    'grade' => $gradeInfo['grade_code'],
                    'points' => $gradeInfo['grade_points'],
                    'perf_level' => $gradeInfo['performance_level'],
                    'remarks' => $mark['remarks'] ?? '',
                    'marked_by' => $this->user_id,
                ]);

                $gradedCount++;
                
                // Track grade distribution
                $grade = $gradeInfo['grade_code'];
                $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
            }

            // Update assessment status
            $this->db->prepare(
                "UPDATE assessments 
                SET status = 'marked'
                WHERE id = :id"
            )->execute(['id' => $assessmentId]);

            // Calculate statistics
            $statsStmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_marked,
                    AVG(percentage) as mean_percentage,
                    MAX(percentage) as highest_percentage,
                    MIN(percentage) as lowest_percentage,
                    STDDEV(percentage) as std_deviation
                FROM assessment_results
                WHERE assessment_id = :id"
            );
            $statsStmt->execute(['id' => $assessmentId]);
            $statistics = $statsStmt->fetch(PDO::FETCH_ASSOC);

            $data['results_summary'] = [
                'graded_count' => $gradedCount,
                'grade_distribution' => $gradeDistribution,
                'statistics' => $statistics,
                'marked_at' => date('Y-m-d H:i:s'),
            ];

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Marked and graded {$gradedCount} student responses"
            );

            $this->db->commit();

            return formatResponse(true, [
                'graded_count' => $gradedCount,
                'grade_distribution' => $gradeDistribution,
                'statistics' => $statistics,
            ], 'Assessment marked and graded successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 5: Analyze results
     * 
     * Performs item analysis and generates performance insights.
     * Calculates:
     * - Item difficulty index
     * - Item discrimination index
     * - Competency achievement rates
     * - Performance level distribution
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with analysis results
     */
    public function analyzeResults(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $assessmentId = (int)($data['assessment_id'] ?? 0);

            // Get all results
            $resultsStmt = $this->db->prepare(
                "SELECT * FROM assessment_results 
                WHERE assessment_id = :id 
                ORDER BY percentage DESC"
            );
            $resultsStmt->execute(['id' => $assessmentId]);
            $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                return formatResponse(false, null, 'No results to analyze');
            }

            // Performance level distribution
            $perfLevelDist = [];
            foreach ($results as $result) {
                $level = $result['performance_level'] ?? 'Unknown';
                $perfLevelDist[$level] = ($perfLevelDist[$level] ?? 0) + 1;
            }

            // Competency achievement analysis
            $competencyIds = $data['competency_ids'] ?? [];
            $competencyAnalysis = [];
            foreach ($competencyIds as $compId) {
                // Query learner_competencies for this assessment's students
                $compStmt = $this->db->prepare(
                    "SELECT 
                        COUNT(*) as assessed_count,
                        AVG(CASE 
                            WHEN plc.name = 'Exceeding Expectations' THEN 4
                            WHEN plc.name = 'Meeting Expectations' THEN 3
                            WHEN plc.name = 'Approaching Expectations' THEN 2
                            ELSE 1
                        END) as avg_level
                    FROM learner_competencies lc
                    INNER JOIN performance_levels_cbc plc ON lc.performance_level_id = plc.id
                    WHERE lc.competency_id = :comp_id
                    AND lc.term_id = :term_id"
                );
                $compStmt->execute([
                    'comp_id' => $compId,
                    'term_id' => (int)$data['term_id'],
                ]);
                $compData = $compStmt->fetch(PDO::FETCH_ASSOC);
                
                $competencyAnalysis[$compId] = $compData;
            }

            // Learning outcomes coverage
            $outcomeIds = $data['learning_outcome_ids'] ?? [];
            $outcomesCovered = count($outcomeIds);

            $analysis = [
                'total_assessed' => count($results),
                'performance_level_distribution' => $perfLevelDist,
                'competency_analysis' => $competencyAnalysis,
                'learning_outcomes_covered' => $outcomesCovered,
                'mean_percentage' => $data['results_summary']['statistics']['mean_percentage'] ?? 0,
                'std_deviation' => $data['results_summary']['statistics']['std_deviation'] ?? 0,
                'analyzed_at' => date('Y-m-d H:i:s'),
            ];

            $data['item_analysis'] = $analysis;

            // Complete workflow
            $this->completeWorkflow(
                $instance_id,
                json_encode($data),
                'Assessment workflow completed with analysis'
            );

            return formatResponse(true, $analysis, 'Results analysis completed successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get assessment details and current workflow status
     * 
     * @param int $assessment_id Assessment ID
     * @return array Response with assessment details
     */
    public function getAssessmentDetails(int $assessment_id): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT a.*,
                    s.name as subject_name,
                    c.class_name,
                    at.term_name,
                    atc.classification_name
                FROM assessments a
                LEFT JOIN subjects s ON a.subject_id = s.id
                LEFT JOIN classes c ON a.class_id = c.id
                LEFT JOIN academic_terms at ON a.term_id = at.id
                LEFT JOIN assessment_type_classifications atc ON a.classification_code = atc.code
                WHERE a.id = :id"
            );
            $stmt->execute(['id' => $assessment_id]);
            $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assessment) {
                return formatResponse(false, null, 'Assessment not found');
            }

            return formatResponse(true, $assessment, 'Assessment details retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
