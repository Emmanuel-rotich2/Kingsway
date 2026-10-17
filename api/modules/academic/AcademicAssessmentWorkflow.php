<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use App\API\Services\RealtimeGatewayPublisher;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
            $classStreamId = $this->resolveClassStreamId((int) $plan['class_id']);
            $assessmentTypeId = $this->resolveAssessmentTypeId($plan['classification_code'], $plan['assessment_type'] ?? null);

            $assessmentStmt = $this->db->prepare(
                "INSERT INTO assessments (
                    title, learning_area_id, academic_year_class_stream_id, academic_year_term_id,
                    max_marks, assessment_date, assessment_type_classification_id, status
                ) VALUES (
                    :title, :learning_area_id, :academic_year_class_stream_id, :academic_year_term_id,
                    :max_marks, :assessment_date, :assessment_type_classification_id, 'pending_submission'
                )"
            );
            $assessmentStmt->execute([
                'title' => $plan['title'],
                'learning_area_id' => (int)$plan['subject_id'],
                'academic_year_class_stream_id' => $classStreamId,
                'academic_year_term_id' => (int)$plan['term_id'],
                'max_marks' => (int)$plan['total_marks'],
                'assessment_date' => $plan['assessment_date'] ?? date('Y-m-d'),
                'assessment_type_classification_id' => $assessmentTypeId,
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
                SET status = 'submitted',
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
     * Opaque token describing the observable state of one mark row.
     *
     * Sent to the client when results are read and echoed back on save. It
     * combines the row's timestamp with the values a marker could have been
     * looking at, so it detects a same-second concurrent edit that a timestamp
     * comparison alone would miss.
     */
    public static function resultRowToken(array $row): string
    {
        return implode('|', [
            (string) ($row['updated_at'] ?? ''),
            (string) ($row['marks_obtained'] ?? ''),
            (string) ($row['grade'] ?? ''),
            (string) ($row['remarks'] ?? ''),
        ]);
    }

    /**
     * Publish one ROW_UPDATED descriptor per saved mark.
     *
     * Bounded descriptors only: the row identity and the fields that changed,
     * never the mark sheet, the learner record or any other body. Parents hold
     * family channels and therefore never receive scope 'all' staff traffic.
     *
     * @param int   $assessmentId
     * @param array $writtenRows Rows written by this call
     */
    private function publishMarkUpdates(int $assessmentId, array $writtenRows): void
    {
        if ($writtenRows === []) {
            return;
        }
        foreach ($writtenRows as $row) {
            try {
                RealtimeGatewayPublisher::publish('ROW_UPDATED', 'all', [
                    'entity_id' => (int) $row['enrollment_id'],
                    'id' => (int) $row['student_id'],
                    'domain' => 'assessment_result',
                    'action' => 'marked',
                    'targets' => ['academic/grading-results', 'academic/assessments-mark-and-grade'],
                    'changed_fields' => ['marks_obtained', 'grade', 'points', 'remarks'],
                    'version' => $assessmentId,
                    'responder_id' => (int) $row['responder_id'],
                    // Matches what the editing browser declares via
                    // RealtimePresence.begin(): the gateway withholds this row's
                    // patch from that connection and sends a CONFLICT_HINT
                    // instead, so a peer's save never overwrites in-progress input.
                    'activity_key' => 'assessment_result:' . $assessmentId . ':' . (int) $row['student_id'],
                ]);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError(
                    'Realtime mark publish failed: ' . $e->getMessage()
                );
            }
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
                    assessment_id, student_academic_enrollment_id, marks_obtained,
                    grade, points, remarks, responder_type, responder_id
                ) VALUES (
                    :assessment_id, :enrollment_id, :score,
                    :grade, :points, :remarks, 'teacher', :responder_id
                ) ON DUPLICATE KEY UPDATE
                    marks_obtained = VALUES(marks_obtained),
                    grade = VALUES(grade),
                    points = VALUES(points),
                    remarks = VALUES(remarks),
                    responder_type = 'teacher',
                    responder_id = VALUES(responder_id)"
            );

            $gradedCount = 0;
            $gradeDistribution = [];
            $conflicts = [];
            $writtenRows = [];

            // Lock the current row so a concurrent marker cannot change it
            // between the staleness check and the write below.
            $lockStmt = $this->db->prepare(
                "SELECT id, marks_obtained, grade, remarks, updated_at
                FROM assessment_results
                WHERE assessment_id = :assessment_id
                  AND student_academic_enrollment_id = :enrollment_id
                  AND deleted_at IS NULL
                FOR UPDATE"
            );

            foreach ($marks as $mark) {
                $studentId = (int)$mark['student_id'];
                $enrollmentId = $this->resolveEnrollmentId($studentId);
                if ($enrollmentId <= 0) {
                    continue;
                }
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

                // Optimistic concurrency. Two markers (or one marker in two
                // tabs) loading the same sheet and saving must not silently
                // overwrite each other — a mark is an official record.
                //
                // The token covers the row's VALUES as well as its timestamp:
                // `updated_at` is a TIMESTAMP with one-second resolution, so a
                // timestamp-only check would accept a second save made inside
                // the same second. Comparing the observed state means a
                // conflict is only raised when the state the marker actually
                // saw is genuinely gone, and an identical re-save (which
                // changes nothing) is correctly not a conflict.
                $lockStmt->execute([
                    'assessment_id' => $assessmentId,
                    'enrollment_id' => $enrollmentId,
                ]);
                $existing = $lockStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $expectedToken = isset($mark['expected_token'])
                        ? trim((string) $mark['expected_token']) : '';
                    $expectedUpdatedAt = isset($mark['expected_updated_at'])
                        ? trim((string) $mark['expected_updated_at']) : '';
                    $stale = false;
                    if ($expectedToken !== '') {
                        $stale = !hash_equals(self::resultRowToken($existing), $expectedToken);
                    } elseif ($expectedUpdatedAt !== '') {
                        $stale = (string) $existing['updated_at'] !== $expectedUpdatedAt;
                    }
                    if ($stale) {
                        $conflicts[] = [
                            'student_id' => $studentId,
                            'enrollment_id' => $enrollmentId,
                            'expected_token' => $expectedToken !== '' ? $expectedToken : null,
                            'expected_updated_at' => $expectedUpdatedAt !== '' ? $expectedUpdatedAt : null,
                            'current_token' => self::resultRowToken($existing),
                            'current_updated_at' => $existing['updated_at'],
                            'current_marks' => $existing['marks_obtained'] !== null
                                ? (float) $existing['marks_obtained'] : null,
                            'current_grade' => $existing['grade'],
                        ];
                        continue;
                    }
                }

                $insertStmt->execute([
                    'assessment_id' => $assessmentId,
                    'enrollment_id' => $enrollmentId,
                    'score' => $score,
                    'grade' => $gradeInfo['grade_code'],
                    'points' => $gradeInfo['grade_points'],
                    'remarks' => $mark['remarks'] ?? '',
                    'responder_id' => $this->user_id,
                ]);

                $gradedCount++;

                $writtenRows[] = [
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollmentId,
                    'marks_obtained' => $score,
                    'grade' => $gradeInfo['grade_code'],
                    'responder_id' => (int) $this->user_id,
                ];

                // Track grade distribution
                $grade = $gradeInfo['grade_code'];
                $gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
            }

            // A mark sheet is one unit of work. Writing the rows that happened to be
            // clean while silently dropping the stale ones would flip the
            // assessment to 'submitted' with a partial, misleading set of
            // marks — worse than refusing. Roll the whole save back and let
            // the marker reconcile.
            if ($conflicts) {
                $this->db->rollBack();
                return formatResponse(false, [
                    'conflict_count' => count($conflicts),
                    'conflicts' => $conflicts,
                ], 'Assessment result conflict: these marks were changed by someone else after you loaded them. '
                    . 'Reload the sheet and re-enter your marks to avoid overwriting their work.');
            }

            // Update assessment status
            $this->db->prepare(
                "UPDATE assessments 
                SET status = 'submitted'
                WHERE id = :id"
            )->execute(['id' => $assessmentId]);

            // Calculate statistics
            $statsStmt = $this->db->prepare(
                "SELECT 
                    COUNT(*) as total_marked,
                    AVG(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END) as mean_percentage,
                    MAX(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END) as highest_percentage,
                    MIN(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END) as lowest_percentage,
                    STDDEV(CASE WHEN a.max_marks > 0 THEN (ar.marks_obtained / a.max_marks) * 100 END) as std_deviation
                FROM " . ReadReplicaService::qualifiedRef("assessment_results") . "
                JOIN assessments a ON a.id = ar.assessment_id
                WHERE ar.assessment_id = :id"
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

            // Tell connected browsers which specific marks changed, so an open
            // mark sheet can patch those rows instead of reloading the table.
            // Fire-and-forget by design: realtime is an acceleration layer and
            // must never fail a successful save.
            $this->publishMarkUpdates($assessmentId, $writtenRows);

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
                ORDER BY marks_obtained DESC"
            );
            $resultsStmt->execute(['id' => $assessmentId]);
            $results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                return formatResponse(false, null, 'No results to analyze');
            }

            // Performance level distribution
            $perfLevelDist = [];
            foreach ($results as $result) {
                $level = $this->derivePerformanceLevel($result['grade'] ?? '');
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
                    FROM " . ReadReplicaService::qualifiedRef("learner_competencies") . "
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
                    la.name as subject_name,
                    c.name as class_name,
                    st.name as stream_name,
                    t.name as term_name,
                    CAST(NULL AS CHAR) AS classification_name
                FROM " . ReadReplicaService::qualifiedRef("assessments") . " a
                LEFT JOIN " . ReadReplicaService::qualifiedRef("learning_areas") . " la ON a.learning_area_id = la.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON a.academic_year_class_stream_id = aycs.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st ON st.id = aycs.stream_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON a.academic_year_term_id = ayt.id
                LEFT JOIN terms t ON t.id = ayt.term_id
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

    /**
     * Resolve an input class id to an academic_year_class_streams row id.
     * Accepts either a stream id (used directly) or a classes.id resolved
     * through the most recent academic year.
     */
    private function resolveClassStreamId(int $classId): int
    {
        if ($classId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_class_streams WHERE id = ? LIMIT 1");
        $stmt->execute([$classId]);
        if ($streamId = $stmt->fetchColumn()) {
            return (int) $streamId;
        }
        $stmt = $this->db->prepare(
            "SELECT aycs.id
             FROM " . ReadReplicaService::qualifiedRef("academic_year_classes") . "
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
             WHERE ayc.class_id = ?
             ORDER BY ayc.academic_year_id DESC, aycs.id
             LIMIT 1"
        );
        $stmt->execute([$classId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve the assessment_type_classifications row id from a CBC classification (CA/SBA/SA)
     * and an optional assessment type name, falling back to the first active
     * formative (CA) or summative (SBA/SA) type.
     */
    private function resolveAssessmentTypeId(string $classification, $assessmentType = null): ?int
    {
        if ($assessmentType) {
            $stmt = $this->db->prepare("SELECT id FROM assessment_type_classifications WHERE LOWER(name) = LOWER(?) AND status = 'active' LIMIT 1");
            $stmt->execute([(string) $assessmentType]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int) $id;
            }
        }
        $isSummative = in_array(strtoupper($classification), ['SBA', 'SA'], true) ? 1 : 0;
        $stmt = $this->db->query("SELECT id FROM assessment_type_classifications WHERE status='active' AND is_summative = {$isSummative} ORDER BY id LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /**
     * Resolve a student id to the current student_academic_enrollments row id.
     */
    private function resolveEnrollmentId(int $studentId): int
    {
        if ($studentId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT id
             FROM student_academic_enrollments
             WHERE student_id = ? AND enrollment_status IN ('active', 'pending')
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute([$studentId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Map a CBC grade code back to its performance level label.
     */
    private function derivePerformanceLevel(string $grade): string
    {
        $map = [
            'EE' => 'Exceeding Expectations',
            'ME' => 'Meeting Expectations',
            'AE' => 'Approaching Expectations',
            'BE' => 'Below Expectations',
        ];
        return $map[strtoupper($grade)] ?? 'Unknown';
    }
}
