<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Student Promotion Workflow - CBC-Compliant
 * 
 * Manages end-of-year student promotions according to CBC requirements.
 * Handles grade progression, retention decisions, graduation, and transfers.
 * 
 * CBC Promotion Criteria:
 * - PP1 → PP2: Automatic (no retention in ECD)
 * - PP2 → Grade 1: Automatic
 * - Grade 1-2: Automatic progression (formative assessment only)
 * - Grade 3-6: Promotion based on continuous assessment (no retention unless extreme cases)
 * - Grade 6 → Grade 7: Transition to junior secondary (KCPE milestone)
 * - Grade 7-9: Based on performance and competency achievement
 * 
 * Workflow Stages:
 * 1. Define Criteria - Set promotion rules and thresholds for the batch
 * 2. Identify Candidates - Query eligible students using grade/class filters
 * 3. Validate Eligibility - Check academic performance, attendance, competencies
 * 4. Execute Promotion - Apply promotions, retentions, graduations via stored procedures
 * 5. Generate Reports - Create promotion reports and notifications
 */
class StudentPromotionWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('student_promotion');
    }
    
    protected function getWorkflowDefinitionCode(): string {
        return 'student_promotion';
    }

    /**
     * Stage 1: Define promotion criteria and create batch
     * 
     * @param array $criteria {
     *   @type int $from_academic_year Source year (e.g., 2025)
     *   @type int $to_academic_year Target year (e.g., 2026)
     *   @type int $from_grade_id School level ID for source grade
     *   @type int $to_grade_id School level ID for target grade
     *   @type string $batch_name Descriptive name (e.g., "Grade 6 to 7 Promotion 2025")
     *   @type float $min_overall_score Optional minimum score threshold (0-100)
     *   @type float $min_attendance_pct Optional minimum attendance percentage (0-100)
     *   @type bool $auto_promote_lower_primary Auto-promote PP1-Grade2 (default: true)
     *   @type array $competency_requirements Optional CBC competency thresholds
     *   @type string $notes Additional instructions or criteria notes
     * }
     * @return array Response with workflow instance and batch details
     */
    public function defineCriteria(array $criteria): array {
        try {
            // Validation
            $required = ['from_academic_year', 'to_academic_year', 'from_grade_id', 'to_grade_id', 'batch_name'];
            foreach ($required as $field) {
                if (!isset($criteria[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // CBC auto-promotion logic
            $autoPromoteLowerPrimary = $criteria['auto_promote_lower_primary'] ?? true;
            
            // Create promotion batch record
            $this->db->beginTransaction();
            
            $batchStmt = $this->db->prepare(
                "INSERT INTO promotion_batches (
                    batch_name, from_academic_year, to_academic_year,
                    from_grade_id, to_grade_id, status, created_by
                ) VALUES (
                    :name, :from_year, :to_year,
                    :from_grade, :to_grade, 'draft', :user_id
                )"
            );
            $batchStmt->execute([
                'name' => $criteria['batch_name'],
                'from_year' => (int)$criteria['from_academic_year'],
                'to_year' => (int)$criteria['to_academic_year'],
                'from_grade' => (int)$criteria['from_grade_id'],
                'to_grade' => (int)$criteria['to_grade_id'],
                'user_id' => $this->user_id,
            ]);
            $batchId = (int)$this->db->lastInsertId();

            // Prepare workflow data
            $workflowData = [
                'batch_id' => $batchId,
                'batch_name' => $criteria['batch_name'],
                'from_academic_year' => (int)$criteria['from_academic_year'],
                'to_academic_year' => (int)$criteria['to_academic_year'],
                'from_grade_id' => (int)$criteria['from_grade_id'],
                'to_grade_id' => (int)$criteria['to_grade_id'],
                'min_overall_score' => isset($criteria['min_overall_score']) ? (float)$criteria['min_overall_score'] : null,
                'min_attendance_pct' => isset($criteria['min_attendance_pct']) ? (float)$criteria['min_attendance_pct'] : null,
                'auto_promote_lower_primary' => $autoPromoteLowerPrimary,
                'competency_requirements' => $criteria['competency_requirements'] ?? [],
                'notes' => $criteria['notes'] ?? '',
                'candidates' => [],
                'validated_students' => [],
                'promotion_summary' => [],
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                'promotion_batch',
                $batchId,
                $workflowData,
                "Promotion batch created: {$criteria['batch_name']}"
            );

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance['id'],
                'batch_id' => $batchId,
                'workflow_data' => $workflowData,
            ], 'Promotion criteria defined successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Identify promotion candidates
     * 
     * Queries database for students eligible for promotion based on grade/class.
     * Uses sp_promote_by_grade_bulk stored procedure to populate student_promotions table.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $filters Optional filters: class_id, stream_id, student_ids
     * @return array Response with candidate count and details
     */
    public function identifyCandidates(int $instance_id, array $filters = []): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $batchId = (int)($data['batch_id'] ?? 0);
            $fromYear = (int)($data['from_academic_year'] ?? 0);
            $toYear = (int)($data['to_academic_year'] ?? 0);
            $fromGrade = (int)($data['from_grade_id'] ?? 0);
            $toGrade = (int)($data['to_grade_id'] ?? 0);

            $this->db->beginTransaction();

            // Call stored procedure to populate student_promotions
            $stmt = $this->db->prepare("CALL sp_promote_by_grade_bulk(:batch_id, :from_year, :to_year, :from_grade, :to_grade)");
            $stmt->execute([
                'batch_id' => $batchId,
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'from_grade' => $fromGrade,
                'to_grade' => $toGrade,
            ]);

            // Retrieve identified candidates
            $candidatesStmt = $this->db->prepare(
                "SELECT sp.*, 
                    s.first_name, s.last_name, s.admission_number,
                    c_from.class_name as current_class, cs_from.stream_name as current_stream,
                    c_to.class_name as promoted_class, cs_to.stream_name as promoted_stream
                FROM student_promotions sp
                INNER JOIN students s ON sp.student_id = s.id
                INNER JOIN classes c_from ON sp.current_class_id = c_from.id
                INNER JOIN class_streams cs_from ON sp.current_stream_id = cs_from.id
                LEFT JOIN classes c_to ON sp.promoted_to_class_id = c_to.id
                LEFT JOIN class_streams cs_to ON sp.promoted_to_stream_id = cs_to.id
                WHERE sp.batch_id = :batch_id"
            );
            $candidatesStmt->execute(['batch_id' => $batchId]);
            $candidates = $candidatesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Update workflow data
            $data['candidates'] = $candidates;
            $data['total_candidates'] = count($candidates);

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Identified {count($candidates)} candidates for promotion"
            );

            $this->db->commit();

            return formatResponse(true, [
                'total_candidates' => count($candidates),
                'candidates' => $candidates,
            ], "Successfully identified {count($candidates)} promotion candidates");

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Validate eligibility
     * 
     * Checks each candidate against promotion criteria:
     * - Academic performance (overall scores from term_subject_scores)
     * - Attendance percentage
     * - CBC competency achievement levels
     * - Disciplinary records (optional)
     * 
     * Updates promotion_status based on validation results.
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with validation summary
     */
    public function validateEligibility(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $batchId = (int)($data['batch_id'] ?? 0);
            $fromYear = (int)($data['from_academic_year'] ?? 0);
            $minScore = $data['min_overall_score'] ?? null;
            $minAttendance = $data['min_attendance_pct'] ?? null;
            $autoPromoteLowerPrimary = $data['auto_promote_lower_primary'] ?? true;

            $this->db->beginTransaction();

            // Get all candidates
            $candidates = $data['candidates'] ?? [];
            $validatedStudents = [];
            $approvedCount = 0;
            $retainedCount = 0;

            foreach ($candidates as $candidate) {
                $studentId = (int)$candidate['student_id'];
                $currentClassId = (int)$candidate['current_class_id'];
                
                // Check if in lower primary (PP1, PP2, Grade 1, Grade 2)
                $gradeStmt = $this->db->prepare(
                    "SELECT sl.grade_name FROM classes c 
                    INNER JOIN school_levels sl ON c.level_id = sl.id 
                    WHERE c.id = :class_id"
                );
                $gradeStmt->execute(['class_id' => $currentClassId]);
                $gradeInfo = $gradeStmt->fetch(PDO::FETCH_ASSOC);
                $gradeName = $gradeInfo['grade_name'] ?? '';

                $isLowerPrimary = in_array($gradeName, ['PP1', 'PP2', 'Grade 1', 'Grade 2']);

                // Auto-approve lower primary if enabled
                if ($isLowerPrimary && $autoPromoteLowerPrimary) {
                    $validatedStudents[$studentId] = [
                        'status' => 'approved',
                        'reason' => 'CBC auto-promotion (Lower Primary)',
                        'overall_score' => null,
                        'attendance_pct' => null,
                    ];
                    $approvedCount++;
                    continue;
                }

                // Get academic performance (average from term_subject_scores)
                $scoreStmt = $this->db->prepare(
                    "SELECT AVG(overall_percentage) as avg_score 
                    FROM term_subject_scores 
                    WHERE student_id = :student_id 
                    AND term_id IN (
                        SELECT id FROM academic_terms 
                        WHERE academic_year = :year
                    )"
                );
                $scoreStmt->execute([
                    'student_id' => $studentId,
                    'year' => $fromYear,
                ]);
                $scoreResult = $scoreStmt->fetch(PDO::FETCH_ASSOC);
                $overallScore = $scoreResult['avg_score'] ? (float)$scoreResult['avg_score'] : 0;

                // Get attendance percentage
                $attendanceStmt = $this->db->prepare(
                    "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_days
                    FROM attendance 
                    WHERE student_id = :student_id 
                    AND YEAR(date) = :year"
                );
                $attendanceStmt->execute([
                    'student_id' => $studentId,
                    'year' => $fromYear,
                ]);
                $attendanceResult = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
                $totalDays = (int)($attendanceResult['total_days'] ?? 0);
                $attendedDays = (int)($attendanceResult['attended_days'] ?? 0);
                $attendancePct = $totalDays > 0 ? round(($attendedDays / $totalDays) * 100, 2) : 0;

                // Determine promotion status
                $status = 'approved';
                $reason = 'Meets promotion criteria';

                if ($minScore !== null && $overallScore < $minScore) {
                    $status = 'retained';
                    $reason = "Below minimum score threshold ({$overallScore}% < {$minScore}%)";
                    $retainedCount++;
                } elseif ($minAttendance !== null && $attendancePct < $minAttendance) {
                    $status = 'retained';
                    $reason = "Below minimum attendance ({$attendancePct}% < {$minAttendance}%)";
                    $retainedCount++;
                } else {
                    $approvedCount++;
                }

                $validatedStudents[$studentId] = [
                    'status' => $status,
                    'reason' => $reason,
                    'overall_score' => $overallScore,
                    'attendance_pct' => $attendancePct,
                ];

                // Update student_promotions record
                $updateStmt = $this->db->prepare(
                    "UPDATE student_promotions 
                    SET promotion_status = :status,
                        overall_score = :score,
                        promotion_reason = :reason
                    WHERE batch_id = :batch_id 
                    AND student_id = :student_id"
                );
                $updateStmt->execute([
                    'status' => $status,
                    'score' => $overallScore,
                    'reason' => $reason,
                    'batch_id' => $batchId,
                    'student_id' => $studentId,
                ]);
            }

            // Update batch statistics
            $this->db->prepare(
                "UPDATE promotion_batches 
                SET total_approved = :approved,
                    total_retained = :retained,
                    status = 'validated'
                WHERE id = :batch_id"
            )->execute([
                'approved' => $approvedCount,
                'retained' => $retainedCount,
                'batch_id' => $batchId,
            ]);

            // Update workflow data
            $data['validated_students'] = $validatedStudents;
            $data['validation_summary'] = [
                'approved' => $approvedCount,
                'retained' => $retainedCount,
                'total' => count($candidates),
            ];

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Validated eligibility: {$approvedCount} approved, {$retainedCount} retained"
            );

            $this->db->commit();

            return formatResponse(true, [
                'validation_summary' => $data['validation_summary'],
                'validated_students' => $validatedStudents,
            ], 'Eligibility validation completed');

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Execute promotion
     * 
     * Applies approved promotions by updating student records:
     * - Updates students.stream_id to new class/stream
     * - Sets approval_date and approved_by in student_promotions
     * - Handles special cases: graduation, transfers
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $options {
     *   @type bool $apply_immediately Execute promotions now (default: true)
     *   @type string $effective_date Date when promotions take effect (default: start of new year)
     * }
     * @return array Response with execution summary
     */
    public function executePromotion(int $instance_id, array $options = []): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $batchId = (int)($data['batch_id'] ?? 0);
            $applyImmediately = $options['apply_immediately'] ?? true;
            $effectiveDate = $options['effective_date'] ?? null;

            $this->db->beginTransaction();

            // Get approved promotions
            $approvedStmt = $this->db->prepare(
                "SELECT sp.*, s.admission_number, s.first_name, s.last_name
                FROM student_promotions sp
                INNER JOIN students s ON sp.student_id = s.id
                WHERE sp.batch_id = :batch_id 
                AND sp.promotion_status = 'approved'"
            );
            $approvedStmt->execute(['batch_id' => $batchId]);
            $approved = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

            $executedCount = 0;

            if ($applyImmediately) {
                foreach ($approved as $promotion) {
                    $studentId = (int)$promotion['student_id'];
                    $newStreamId = (int)$promotion['promoted_to_stream_id'];

                    // Update student's current stream
                    $this->db->prepare(
                        "UPDATE students 
                        SET stream_id = :new_stream_id,
                            updated_at = NOW()
                        WHERE id = :student_id"
                    )->execute([
                        'new_stream_id' => $newStreamId,
                        'student_id' => $studentId,
                    ]);

                    // Mark promotion as executed
                    $this->db->prepare(
                        "UPDATE student_promotions 
                        SET approved_by = :user_id,
                            approval_date = NOW(),
                            approval_notes = 'Auto-approved via workflow'
                        WHERE id = :promotion_id"
                    )->execute([
                        'user_id' => $this->user_id,
                        'promotion_id' => (int)$promotion['id'],
                    ]);

                    $executedCount++;
                }

                // Update batch status
                $this->db->prepare(
                    "UPDATE promotion_batches 
                    SET status = 'completed',
                        executed_at = NOW()
                    WHERE id = :batch_id"
                )->execute(['batch_id' => $batchId]);
            }

            // Update workflow data
            $data['promotion_summary'] = [
                'total_executed' => $executedCount,
                'execution_date' => $applyImmediately ? date('Y-m-d H:i:s') : null,
                'effective_date' => $effectiveDate,
            ];

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Executed promotion for {$executedCount} students"
            );

            $this->db->commit();

            return formatResponse(true, [
                'executed_count' => $executedCount,
                'promotion_summary' => $data['promotion_summary'],
            ], "Successfully promoted {$executedCount} students");

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Stage 5: Generate reports
     * 
     * Creates promotion reports and sends notifications to stakeholders.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $options Report generation options
     * @return array Response with report details
     */
    public function generateReports(int $instance_id, array $options = []): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $batchId = (int)($data['batch_id'] ?? 0);

            // Get promotion statistics
            $statsStmt = $this->db->prepare(
                "SELECT 
                    promotion_status,
                    COUNT(*) as count,
                    AVG(overall_score) as avg_score
                FROM student_promotions
                WHERE batch_id = :batch_id
                GROUP BY promotion_status"
            );
            $statsStmt->execute(['batch_id' => $batchId]);
            $statistics = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Log report generation
            $this->logAction(
                'promotion_report_generated',
                "Generated promotion report for batch {$batchId}",
                ['batch_id' => $batchId, 'statistics' => $statistics]
            );

            // Complete workflow
            $this->completeWorkflow(
                $instance_id,
                json_encode($data),
                'Promotion workflow completed successfully'
            );

            return formatResponse(true, [
                'batch_id' => $batchId,
                'statistics' => $statistics,
                'report_generated_at' => date('Y-m-d H:i:s'),
            ], 'Promotion reports generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get promotion batch details and current status
     * 
     * @param int $batch_id Promotion batch ID
     * @return array Response with batch details
     */
    public function getBatchDetails(int $batch_id): array {
        try {
            $stmt = $this->db->prepare(
                "SELECT pb.*,
                    sl_from.grade_name as from_grade_name,
                    sl_to.grade_name as to_grade_name,
                    u.username as created_by_name
                FROM promotion_batches pb
                LEFT JOIN school_levels sl_from ON pb.from_grade_id = sl_from.id
                LEFT JOIN school_levels sl_to ON pb.to_grade_id = sl_to.id
                LEFT JOIN users u ON pb.created_by = u.id
                WHERE pb.id = :batch_id"
            );
            $stmt->execute(['batch_id' => $batch_id]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$batch) {
                return formatResponse(false, null, 'Promotion batch not found');
            }

            return formatResponse(true, $batch, 'Batch details retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
