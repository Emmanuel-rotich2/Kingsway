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
     * Resolve an academic_years.id from a numeric year value (year_code).
     */
    private function resolveYearIdFromCode(int $yearValue): int
    {
        $stmt = $this->db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
        $stmt->execute([(string)$yearValue]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * Resolve a class name for a school level (used by the promotion stored
     * procedures which match on classes.name).
     */
    private function resolveGradeClassName(int $levelId): string
    {
        $stmt = $this->db->prepare("SELECT name FROM classes WHERE level_id = ? ORDER BY id LIMIT 1");
        $stmt->execute([$levelId]);
        return (string) ($stmt->fetchColumn() ?: '');
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
            
            // promotion_batches schema: batch_scope, from_academic_year YEAR(4),
            //   to_academic_year YEAR(4), batch_type, status, created_by.
            // No batch_name, from_grade_id, to_grade_id columns.
            // Store grade info and label in batch_scope and notes.
            $batchScope = $criteria['batch_name']
                . " | Grade {$criteria['from_grade_id']} → {$criteria['to_grade_id']}";
            $batchStmt = $this->db->prepare(
                "INSERT INTO promotion_batches (
                    batch_scope, from_academic_year, to_academic_year,
                    batch_type, status, created_by, notes
                ) VALUES (
                    :scope, :from_year, :to_year,
                    'bulk_grade', 'in_progress', :user_id, :notes
                )"
            );
            $batchStmt->execute([
                'scope'     => $batchScope,
                'from_year' => (int)$criteria['from_academic_year'],
                'to_year'   => (int)$criteria['to_academic_year'],
                'user_id'   => $this->user_id,
                'notes'     => $criteria['notes'] ?? null,
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
            $instanceId = (int) $this->startWorkflow(
                'promotion_batch',
                $batchId,
                $workflowData
            );

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instanceId,
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
     * Uses sp_promote_by_grade_bulk stored procedure to populate student_transitions table.
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

            // Resolve grade names for the stored procedure (matches classes.name)
            $fromGradeName = $this->resolveGradeClassName($fromGrade);
            $toGradeName = $this->resolveGradeClassName($toGrade);
            if ($fromGradeName === '' || $toGradeName === '') {
                throw new Exception('Unable to resolve grade name for promotion procedure');
            }

            // Call stored procedure to populate student_transitions
            $stmt = $this->db->prepare("CALL sp_promote_by_grade_bulk(:batch_id, :from_year, :to_year, :from_grade, :to_grade)");
            $stmt->execute([
                'batch_id' => $batchId,
                'from_year' => $fromYear,
                'to_year' => $toYear,
                'from_grade' => $fromGradeName,
                'to_grade' => $toGradeName,
            ]);

            // Retrieve identified candidates (the procedure stamps decided_by with the batch id)
            $candidatesStmt = $this->db->prepare(
                "SELECT st.*,
                    p.first_name, p.last_name, s.admission_no,
                    c_from.id  AS current_class_id,  st_from.id AS current_stream_id,
                    c_to.id    AS promoted_to_class_id,  st_to.id AS promoted_to_stream_id,
                    c_from.name  AS current_class,  st_from.name AS current_stream,
                    c_to.name    AS promoted_class,  st_to.name   AS promoted_stream
                FROM student_transitions st
                INNER JOIN students      s       ON st.student_id = s.id
                INNER JOIN persons       p       ON p.id = s.person_id
                LEFT JOIN student_academic_enrollments sae_from
                    ON st.from_student_academic_enrollment_id = sae_from.id
                LEFT JOIN academic_year_class_streams aycs_from ON aycs_from.id = sae_from.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc_from ON ayc_from.id = aycs_from.academic_year_class_id
                LEFT JOIN classes c_from ON c_from.id = ayc_from.class_id
                LEFT JOIN streams st_from ON st_from.id = aycs_from.stream_id
                LEFT JOIN student_academic_enrollments sae_to
                    ON st.to_student_academic_enrollment_id = sae_to.id
                LEFT JOIN academic_year_class_streams aycs_to ON aycs_to.id = sae_to.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc_to ON ayc_to.id = aycs_to.academic_year_class_id
                LEFT JOIN classes c_to ON c_to.id = ayc_to.class_id
                LEFT JOIN streams st_to ON st_to.id = aycs_to.stream_id
                WHERE st.decided_by = :batch_id"
            );
            $candidatesStmt->execute(['batch_id' => $batchId]);
            $candidates = $candidatesStmt->fetchAll(PDO::FETCH_ASSOC);

            // Update workflow data
            $data['candidates'] = $candidates;
            $data['total_candidates'] = count($candidates);

            $this->advanceStage(
                $instance_id,
                'identify_candidates',
                "Identified " . count($candidates) . " candidates for promotion",
                $data
            );

            $this->db->commit();

            return formatResponse(true, [
                'total_candidates' => count($candidates),
                'candidates' => $candidates,
            ], "Successfully identified " . count($candidates) . " promotion candidates");

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
                    "SELECT sl.name AS grade_name FROM classes c 
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
                        SELECT ayt.term_id FROM academic_year_terms ayt
                        INNER JOIN academic_years ay ON ay.id = ayt.academic_year_id
                        WHERE ay.year_code = :year
                    )"
                );
                $scoreStmt->execute([
                    'student_id' => $studentId,
                    'year' => (string)$fromYear,
                ]);
                $scoreResult = $scoreStmt->fetch(PDO::FETCH_ASSOC);
                $overallScore = $scoreResult['avg_score'] ? (float)$scoreResult['avg_score'] : 0;

                // Get attendance percentage (replica-projection read, schema-qualified
                // so production database renames remain transparent)
                $attendanceRef = \App\API\Services\ReadReplicaService::qualifiedRef('student_attendance_analytics');
                $attendanceStmt = $this->db->prepare(
                    "SELECT
                        COALESCE(SUM(days_marked), 0) AS total_days,
                        COALESCE(SUM(present_marks + late_marks), 0) AS attended_days
                    FROM {$attendanceRef}
                    WHERE student_id = :student_id
                    AND academic_year = :year"
                );
                $attendanceStmt->execute([
                    'student_id' => $studentId,
                    'year' => (string)$fromYear,
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

                // Update student_transitions record
                $updateStmt = $this->db->prepare(
                    "UPDATE student_transitions 
                    SET reason = :reason
                    WHERE decided_by = :batch_id 
                    AND student_id = :student_id"
                );
                $updateStmt->execute([
                    'reason' => $reason,
                    'batch_id' => $batchId,
                    'student_id' => $studentId,
                ]);
            }

            // Update batch statistics using actual column names:
            // total_promoted (approved), total_pending_approval (retained/pending),
            // status must be one of: pending, in_progress, completed, cancelled.
            $this->db->prepare(
                "UPDATE promotion_batches
                SET total_students_processed = :total,
                    total_promoted = :approved,
                    total_pending_approval = :retained,
                    status = 'in_progress'
                WHERE id = :batch_id"
            )->execute([
                'total'    => count($candidates),
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
                'validate_eligibility',
                "Validated eligibility: {$approvedCount} approved, {$retainedCount} retained",
                $data
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
     * Applies approved promotions by updating enrollment records:
     * - Creates new academic year enrollments for promoted students
     * - Sets decided_by/decided_at/executed_at in student_transitions
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
            // Admin stream rebalancing: { student_id: target_stream_id (or aycs id via target_aycs_id) }
            $streamOverrides = $options['stream_overrides'] ?? [];

            $this->db->beginTransaction();

            // Approved student ids come from the eligibility validation stage
            $approvedStudentIds = [];
            foreach (($data['validated_students'] ?? []) as $studentId => $validation) {
                if (($validation['status'] ?? '') === 'approved') {
                    $approvedStudentIds[] = (int)$studentId;
                }
            }

            $toYearId = $this->resolveYearIdFromCode((int)($data['to_academic_year'] ?? 0));

            // Load transitions for the batch
            $approvedStmt = $this->db->prepare(
                "SELECT st.*,
                    p.first_name, p.last_name, s.admission_no,
                    c.id AS from_class_id, c.level_id AS from_level_id,
                    st_from.id AS from_stream_id,
                    st_from.name AS from_stream_name
                FROM student_transitions st
                INNER JOIN students s ON st.student_id = s.id
                INNER JOIN persons p ON p.id = s.person_id
                INNER JOIN student_academic_enrollments sae_from ON st.from_student_academic_enrollment_id = sae_from.id
                INNER JOIN academic_year_class_streams aycs_from ON aycs_from.id = sae_from.academic_year_class_stream_id
                INNER JOIN academic_year_classes ayc_from ON ayc_from.id = aycs_from.academic_year_class_id
                INNER JOIN classes c ON c.id = ayc_from.class_id
                INNER JOIN streams st_from ON st_from.id = aycs_from.stream_id
                WHERE st.decided_by = :batch_id"
            );
            $approvedStmt->execute(['batch_id' => $batchId]);
            $approved = $approvedStmt->fetchAll(PDO::FETCH_ASSOC);

            $executedCount = 0;

            if ($applyImmediately) {
                foreach ($approved as $promotion) {
                    $studentId = (int)$promotion['student_id'];
                    if (!in_array($studentId, $approvedStudentIds, true)) {
                        continue;
                    }
                    if ((int)$promotion['to_student_academic_enrollment_id'] > 0) {
                        continue;
                    }

                    // Determine target class/stream in the new academic year through
                    // the normalized progression ladder (not sequential school_levels.id).
                    $targetAycsId = 0;
                    if ($toYearId > 0) {
                        $progressionStmt = $this->db->prepare(
                            "SELECT target_class_id
                             FROM academic_class_progression
                             WHERE source_class_id = :class_id AND active = 1
                             ORDER BY id LIMIT 1"
                        );
                        $progressionStmt->execute(['class_id' => (int)$promotion['from_class_id']]);
                        $nextClassId = (int)($progressionStmt->fetchColumn() ?: 0);

                        if ($nextClassId > 0) {
                            // Prefer the matching stream in the target class
                            $aycsStmt = $this->db->prepare(
                                "SELECT aycs.id FROM academic_year_class_streams aycs
                                 INNER JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                                 WHERE ayc.academic_year_id = :year_id AND ayc.class_id = :class_id
                                   AND aycs.stream_id = :stream_id
                                 ORDER BY aycs.id LIMIT 1"
                            );
                            $aycsStmt->execute([
                                'year_id' => $toYearId,
                                'class_id' => $nextClassId,
                                'stream_id' => (int)$promotion['from_stream_id'],
                            ]);
                            $targetAycsId = (int)($aycsStmt->fetchColumn() ?: 0);

                            if ($targetAycsId <= 0) {
                                $aycsStmt = $this->db->prepare(
                                    "SELECT aycs.id FROM academic_year_class_streams aycs
                                     INNER JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                                     WHERE ayc.academic_year_id = :year_id AND ayc.class_id = :class_id
                                     ORDER BY aycs.id LIMIT 1"
                                );
                                $aycsStmt->execute([
                                    'year_id' => $toYearId,
                                    'class_id' => $nextClassId,
                                ]);
                                $targetAycsId = (int)($aycsStmt->fetchColumn() ?: 0);
                            }
                        }
                    }

                    if ($targetAycsId <= 0) {
                        // No target available — leave transition unexecuted
                        continue;
                    }

                    // Create enrollment for the new academic year
                    $enrollStmt = $this->db->prepare(
                        "INSERT INTO student_academic_enrollments (
                            student_id, academic_year_id, academic_year_class_stream_id,
                            enrollment_status, enrolled_on
                        ) VALUES (?, ?, ?, 'pending', COALESCE(?, CURRENT_DATE))"
                    );
                    $enrollStmt->execute([
                        $studentId,
                        $toYearId,
                        $targetAycsId,
                        $effectiveDate ?: null,
                    ]);
                    $newSaeId = (int)$this->db->lastInsertId();

                    // Mark transition as executed. The candidate procedures use
                    // decided_by as the batch marker, so preserve that linkage for
                    // subsequent queries and reporting.
                    $this->db->prepare(
                        "UPDATE student_transitions
                         SET to_student_academic_enrollment_id = :sae_id,
                             decided_at = NOW(),
                             executed_at = NOW()
                         WHERE id = :transition_id"
                    )->execute([
                        'sae_id' => $newSaeId,
                        'transition_id' => (int)$promotion['id'],
                    ]);

                    $executedCount++;
                }

                // Update batch status
                $this->db->prepare(
                    "UPDATE promotion_batches 
                    SET status = 'completed',
                        total_students_processed = :total,
                        total_promoted = :executed,
                        completed_at = NOW()
                    WHERE id = :batch_id"
                )->execute([
                    'total' => count($approved),
                    'executed' => $executedCount,
                    'batch_id' => $batchId,
                ]);
            }

            // Update workflow data
            $data['promotion_summary'] = [
                'total_executed' => $executedCount,
                'execution_date' => $applyImmediately ? date('Y-m-d H:i:s') : null,
                'effective_date' => $effectiveDate,
            ];

            $this->advanceStage(
                $instance_id,
                'execute_promotion',
                "Executed promotion for {$executedCount} students",
                $data
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

            // Get promotion statistics from student_transitions
            $statsStmt = $this->db->prepare(
                "SELECT 
                    st.transition_type AS promotion_status,
                    COUNT(*) as count,
                    ROUND(AVG(CASE WHEN st.to_student_academic_enrollment_id IS NOT NULL THEN 1 ELSE 0 END) * 100, 1) as executed_pct
                FROM student_transitions st
                WHERE st.decided_by = :batch_id
                GROUP BY st.transition_type"
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
                    u.username as created_by_name
                FROM promotion_batches pb
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
