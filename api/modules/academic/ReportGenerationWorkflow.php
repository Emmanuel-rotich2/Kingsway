<?php
namespace App\API\Modules\academic;

use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;
/**
 * Report Generation Workflow - CBC-Compliant
 * 
 * Generates comprehensive academic reports aligned to CBC reporting requirements.
 * Produces learner progress reports with academic performance, competencies, and values.
 * 
 * CBC Report Requirements:
 * - Academic Performance: Subject scores with CBC grading (EE, ME, AE, BE)
 * - Core Competencies: Assessment of 8 competencies with performance levels
 * - Core Values: Evidence of values acquisition throughout the term
 * - Learning Outcomes: Achievement of specific learning outcomes
 * - Teacher's Remarks: Holistic feedback on learner development
 * - Attendance Record: Days present, absent, late
 * - Co-curricular Activities: Participation and achievements
 * 
 * Report Types:
 * - End-of-Term Reports: Comprehensive term performance
 * - Progress Reports: Mid-term updates
 * - Individualized Learning Plans: For learners needing support
 * - Subject-specific Reports: Detailed subject analysis
 * 
 * Workflow Stages:
 * 1. Define Scope - Select students, term, report type
 * 2. Compile Data - Aggregate academic, competency, values data
 * 3. Generate Reports - Create formatted CBC reports
 * 4. Review & Approve - Validation by class teacher/head teacher
 * 5. Distribute - Send to parents/guardians
 */
class ReportGenerationWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('report_generation');
    }
    
    protected function getWorkflowDefinitionCode(): string {
        return 'report_generation';
    }

    /**
     * Resolve an academic year id from a scope value.
     * Accepts either the academic_years.id or the numeric year_code; falls back
     * to the latest non-archived year.
     */
    private function resolveYearId($value): int
    {
        $value = (int)$value;
        if ($value > 0) {
            $stmt = $this->db->prepare("SELECT id FROM academic_years WHERE id = ? OR year_code = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$value, (string)$value]);
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
        }
        $stmt = $this->db->query("SELECT id FROM academic_years WHERE status <> 'archived' ORDER BY id DESC LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : 0;
    }

    /**
     * Resolve report term context.
     * The frontend term_id refers to academic_year_terms.id while
     * term_subject_scores.term_id uses terms.id and learner_* tables use the
     * numeric year value.
     *
     * @return array{ayt_id:int, term_id:int, academic_year_id:int, year_value:int, term_number:int}
     */
    private function resolveReportTermContext(int $termId): array
    {
        $stmt = $this->db->prepare("
            SELECT ayt.id AS ayt_id, ayt.term_id, ayt.academic_year_id,
                   CAST(ay.year_code AS UNSIGNED) AS year_value,
                   CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number
            FROM academic_year_terms ayt
            JOIN terms t ON t.id = ayt.term_id
            JOIN academic_years ay ON ay.id = ayt.academic_year_id
            WHERE ayt.id = ?
        ");
        $stmt->execute([$termId]);
        $ctx = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ctx) {
            $stmt = $this->db->prepare("SELECT id AS term_id, CAST(SUBSTRING(code, 2) AS UNSIGNED) AS term_number FROM terms WHERE id = ?");
            $stmt->execute([$termId]);
            $ctx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ctx) {
                return ['ayt_id' => 0, 'term_id' => 0, 'academic_year_id' => 0, 'year_value' => 0, 'term_number' => 0];
            }
            return [
                'ayt_id' => 0,
                'term_id' => (int)$ctx['term_id'],
                'academic_year_id' => 0,
                'year_value' => 0,
                'term_number' => (int)$ctx['term_number'],
            ];
        }
        return [
            'ayt_id' => (int)$ctx['ayt_id'],
            'term_id' => (int)$ctx['term_id'],
            'academic_year_id' => (int)$ctx['academic_year_id'],
            'year_value' => (int)$ctx['year_value'],
            'term_number' => (int)$ctx['term_number'],
        ];
    }

    /**
     * Stage 1: Define report scope
     * 
     * @param array $scope {
     *   @type string $report_type Type: end_of_term, progress, subject_specific, ilp
     *   @type int $term_id Academic term
     *   @type int $academic_year Year
     *   @type int $class_id Target class (or null for individual students)
     *   @type array $student_ids Specific students (or null for entire class)
     *   @type array $subject_ids Subjects to include (or null for all)
     *   @type bool $include_competencies Include competency section (default: true)
     *   @type bool $include_values Include core values section (default: true)
     *   @type bool $include_attendance Include attendance summary (default: true)
     *   @type bool $include_activities Include co-curricular activities (default: true)
     *   @type string $report_template Template to use
     * }
     * @return array Response with workflow instance
     */
    public function defineScope(array $scope): array {
        try {
            // Validation
            $required = ['report_type', 'term_id', 'academic_year'];
            foreach ($required as $field) {
                if (!isset($scope[$field])) {
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Validate report type
            $validTypes = ['end_of_term', 'progress', 'subject_specific', 'ilp'];
            if (!in_array($scope['report_type'], $validTypes)) {
                return formatResponse(false, null, 'Invalid report type');
            }

            // Determine student list
            $studentIds = [];
            if (!empty($scope['student_ids'])) {
                $studentIds = $scope['student_ids'];
            } elseif (!empty($scope['class_id'])) {
                // Get all active students in class/stream for the academic year
                $termCtx = $this->resolveReportTermContext((int)($scope['term_id'] ?? 0));
                $yearId = $termCtx['academic_year_id'] > 0
                    ? $termCtx['academic_year_id']
                    : $this->resolveYearId($scope['academic_year'] ?? 0);

                $stmt = $this->db->prepare(
                    "SELECT DISTINCT s.id FROM students s
                    INNER JOIN student_academic_enrollments sae
                        ON sae.student_id = s.id AND sae.academic_year_id = :year_id
                        AND sae.enrollment_status IN ('pending', 'active')
                    INNER JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    INNER JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    WHERE (ayc.class_id = :class_id OR aycs.id = :class_id)
                    AND s.status = 'active'"
                );
                $stmt->execute(['year_id' => $yearId, 'class_id' => (int)$scope['class_id']]);
                $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } else {
                return formatResponse(false, null, 'Either class_id or student_ids must be provided');
            }

            if (empty($studentIds)) {
                return formatResponse(false, null, 'No students found for report generation');
            }

            // Prepare workflow data
            $workflowData = [
                'report_type' => $scope['report_type'],
                'term_id' => (int)$scope['term_id'],
                'academic_year' => (int)$scope['academic_year'],
                'class_id' => $scope['class_id'] ?? null,
                'student_ids' => $studentIds,
                'subject_ids' => $scope['subject_ids'] ?? [],
                'include_competencies' => $scope['include_competencies'] ?? true,
                'include_values' => $scope['include_values'] ?? true,
                'include_attendance' => $scope['include_attendance'] ?? true,
                'include_activities' => $scope['include_activities'] ?? true,
                'report_template' => $scope['report_template'] ?? 'cbc_standard',
                'total_students' => count($studentIds),
                'compiled_data' => [],
                'generated_reports' => [],
                'approval_status' => 'pending',
            ];

            // Start workflow
            $instance = $this->startWorkflow(
                'report_batch',
                $scope['term_id'],
                $workflowData,
                "Report generation scope defined for {count($studentIds)} students"
            );

            return formatResponse(true, [
                'instance_id' => $instance['id'],
                'workflow_data' => $workflowData,
            ], 'Report scope defined successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 2: Compile data
     * 
     * Aggregates all required data for report generation:
     * - Academic scores from term_subject_scores
     * - Competency assessments from learner_competencies
     * - Values evidence from learner_values_acquisition
     * - Attendance records
     * - Activity participation
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with compiled data summary
     */
    public function compileData(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $studentIds = $data['student_ids'] ?? [];
            $termId = (int)($data['term_id'] ?? 0);
            $academicYear = (int)($data['academic_year'] ?? 0);
            $includeCompetencies = $data['include_competencies'] ?? true;
            $includeValues = $data['include_values'] ?? true;
            $includeAttendance = $data['include_attendance'] ?? true;

            $termCtx = $this->resolveReportTermContext($termId);
            $tssTermId = $termCtx['term_id'];
            $yearValue = $termCtx['year_value'] > 0 ? $termCtx['year_value'] : $academicYear;
            $yearId = $termCtx['academic_year_id'];

            $compiledData = [];

            foreach ($studentIds as $studentId) {
                $studentId = (int)$studentId;

                // Get student info
                $studentStmt = $this->db->prepare(
                    "SELECT s.*, p.first_name, p.last_name, p.gender, p.photo_url,
                        CONCAT(p.first_name, ' ', p.last_name) as full_name,
                        c.name as class_name, st.name as stream_name
                    FROM students s
                    INNER JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae
                        ON sae.student_id = s.id AND sae.enrollment_status IN ('pending', 'active')
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    WHERE s.id = :id"
                );
                $studentStmt->execute(['id' => $studentId]);
                $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$studentInfo) {
                    continue;
                }

                // Get academic scores
                $scoresStmt = $this->db->prepare(
                    "SELECT tss.*, la.name as subject_name, la.code as subject_code
                    FROM term_subject_scores tss
                    INNER JOIN learning_areas la ON tss.subject_id = la.id
                    WHERE tss.student_id = :student_id
                    AND tss.term_id = :term_id"
                );
                $scoresStmt->execute([
                    'student_id' => $studentId,
                    'term_id' => $tssTermId,
                ]);
                $scores = $scoresStmt->fetchAll(PDO::FETCH_ASSOC);

                // Get competencies if included
                $competencies = [];
                if ($includeCompetencies) {
                    $compStmt = $this->db->prepare(
                        "SELECT lc.*, cc.code as comp_code, cc.name as comp_name,
                            plc.code as perf_code, plc.name as perf_name
                        FROM learner_competencies lc
                        INNER JOIN core_competencies cc ON lc.competency_id = cc.id
                        LEFT JOIN performance_levels_cbc plc ON lc.performance_level_id = plc.id
                        WHERE lc.student_id = :student_id
                        AND lc.term_id = :term_id
                        AND lc.academic_year = :year
                        ORDER BY cc.sort_order"
                    );
                    $compStmt->execute([
                        'student_id' => $studentId,
                        'term_id' => $tssTermId,
                        'year' => $yearValue,
                    ]);
                    $competencies = $compStmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // Get core values if included
                $values = [];
                if ($includeValues) {
                    $valuesStmt = $this->db->prepare(
                        "SELECT lva.*, cv.code as value_code, cv.name as value_name
                        FROM learner_values_acquisition lva
                        INNER JOIN core_values cv ON lva.value_id = cv.id
                        WHERE lva.student_id = :student_id
                        AND lva.term_id = :term_id
                        AND lva.academic_year = :year
                        ORDER BY lva.incident_date DESC"
                    );
                    $valuesStmt->execute([
                        'student_id' => $studentId,
                        'term_id' => $tssTermId,
                        'year' => $yearValue,
                    ]);
                    $values = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // Get attendance if included
                $attendance = [];
                if ($includeAttendance) {
                    $attendanceRef = \App\API\Services\ReadReplicaService::qualifiedRef('student_attendance_analytics');
                    $attendanceStmt = $this->db->prepare(
                        "SELECT
                            days_marked AS total_days,
                            present_marks AS days_present,
                            unexcused_marks + excused_marks AS days_absent,
                            late_marks AS days_late,
                            attendance_rate_pct
                        FROM {$attendanceRef}
                        WHERE student_id = :student_id
                        AND academic_year = :year
                        AND term_number = :term_number"
                    );
                    $attendanceStmt->execute([
                        'student_id' => $studentId,
                        'year' => $yearValue,
                        'term_number' => $termCtx['term_number'] > 0 ? $termCtx['term_number'] : 1,
                    ]);
                    $attendance = $attendanceStmt->fetch(PDO::FETCH_ASSOC);
                }

                // Calculate overall statistics
                $avgScore = 0;
                $overallGrade = null;
                if (!empty($scores)) {
                    $avgScore = round(array_sum(array_column($scores, 'overall_percentage')) / count($scores), 2);
                    // Map average to CBC grade
                    $gradeStmt = $this->db->prepare(
                        "SELECT gr.grade_code, gr.performance_level 
                        FROM grade_rules gr
                        INNER JOIN grading_scales gs ON gr.scale_id = gs.id
                        WHERE gs.status = 'active'
                        AND :score >= gr.min_mark AND :score <= gr.max_mark
                        LIMIT 1"
                    );
                    $gradeStmt->execute(['score' => $avgScore]);
                    $overallGrade = $gradeStmt->fetch(PDO::FETCH_ASSOC);
                }

                $compiledData[$studentId] = [
                    'student_info' => $studentInfo,
                    'academic_scores' => $scores,
                    'overall_average' => $avgScore,
                    'overall_grade' => $overallGrade,
                    'competencies' => $competencies,
                    'values' => $values,
                    'attendance' => $attendance,
                    'compiled_at' => date('Y-m-d H:i:s'),
                ];
            }

            $data['compiled_data'] = $compiledData;

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Compiled data for {count($compiledData)} students"
            );

            return formatResponse(true, [
                'compiled_count' => count($compiledData),
                'sample_data' => !empty($compiledData) ? reset($compiledData) : null,
            ], 'Data compilation completed');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 3: Generate reports
     * 
     * Creates formatted CBC reports for each student.
     * Reports are generated as structured data (can be rendered to PDF/HTML).
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Response with generated reports
     */
    public function generateReports(int $instance_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $compiledData = $data['compiled_data'] ?? [];
            $reportType = $data['report_type'] ?? 'end_of_term';

            if (empty($compiledData)) {
                return formatResponse(false, null, 'No compiled data available. Please compile data first.');
            }

            $generatedReports = [];

            foreach ($compiledData as $studentId => $studentData) {
                $report = [
                    'report_type' => $reportType,
                    'student_id' => $studentId,
                    'student_info' => $studentData['student_info'],
                    'term_id' => (int)$data['term_id'],
                    'academic_year' => (int)$data['academic_year'],
                    'generated_at' => date('Y-m-d H:i:s'),
                    
                    // Academic Performance Section
                    'academic_performance' => [
                        'subjects' => $studentData['academic_scores'],
                        'overall_average' => $studentData['overall_average'],
                        'overall_grade' => $studentData['overall_grade'],
                        'total_subjects' => count($studentData['academic_scores']),
                    ],
                    
                    // Core Competencies Section
                    'competencies' => $studentData['competencies'],
                    
                    // Core Values Section
                    'values' => $studentData['values'],
                    
                    // Attendance Section
                    'attendance' => $studentData['attendance'],
                    
                    // Teacher's Remarks (placeholder - can be added later)
                    'class_teacher_remarks' => '',
                    'head_teacher_remarks' => '',
                    
                    // Meta information
                    'status' => 'draft',
                    'approved_by' => null,
                    'approved_at' => null,
                ];

                $generatedReports[$studentId] = $report;
            }

            $data['generated_reports'] = $generatedReports;

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Generated {count($generatedReports)} CBC reports"
            );

            return formatResponse(true, [
                'generated_count' => count($generatedReports),
                'reports' => $generatedReports,
            ], 'Reports generated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 4: Review and approve
     * 
     * Allows class teacher and head teacher to review and approve reports.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $approval {
     *   @type array $teacher_remarks Array keyed by student_id with remarks
     *   @type string $approver_role Role: class_teacher, head_teacher
     *   @type bool $approve Approve (true) or reject (false)
     *   @type string $notes Approval notes
     * }
     * @return array Response with approval status
     */
    public function reviewAndApprove(int $instance_id, array $approval): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $reports = $data['generated_reports'] ?? [];

            if (empty($reports)) {
                return formatResponse(false, null, 'No reports available for approval');
            }

            $approverRole = $approval['approver_role'] ?? 'class_teacher';
            $approve = $approval['approve'] ?? true;
            $teacherRemarks = $approval['teacher_remarks'] ?? [];

            // Add teacher remarks to reports
            foreach ($reports as $studentId => &$report) {
                if (isset($teacherRemarks[$studentId])) {
                    if ($approverRole === 'class_teacher') {
                        $report['class_teacher_remarks'] = $teacherRemarks[$studentId];
                    } elseif ($approverRole === 'head_teacher') {
                        $report['head_teacher_remarks'] = $teacherRemarks[$studentId];
                    }
                }

                if ($approve) {
                    $report['status'] = $approverRole === 'head_teacher' ? 'approved' : 'pending_head_teacher';
                    $report['approved_by'] = $this->user_id;
                    $report['approved_at'] = date('Y-m-d H:i:s');
                }
            }

            $data['generated_reports'] = $reports;
            $data['approval_status'] = $approve ? 'approved' : 'rejected';
            $data['approval_notes'] = $approval['notes'] ?? '';
            $data['approved_by'] = $this->user_id;
            $data['approved_at'] = date('Y-m-d H:i:s');

            $this->advanceStage(
                $instance_id,
                json_encode($data),
                "Reports " . ($approve ? 'approved' : 'rejected') . " by {$approverRole}"
            );

            return formatResponse(true, [
                'approval_status' => $data['approval_status'],
                'approved_count' => count($reports),
            ], 'Report approval processed');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Stage 5: Distribute reports
     * 
     * Sends reports to parents/guardians and marks workflow as complete.
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $options {
     *   @type string $distribution_method Method: email, sms, portal, print
     *   @type bool $send_notifications Send notifications to parents
     *   @type string $message_template Message to accompany reports
     * }
     * @return array Response with distribution summary
     */
    public function distributeReports(int $instance_id, array $options = []): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $reports = $data['generated_reports'] ?? [];

            if (empty($reports)) {
                return formatResponse(false, null, 'No reports available for distribution');
            }

            $distributionMethod = $options['distribution_method'] ?? 'portal';
            $sendNotifications = $options['send_notifications'] ?? true;

            $distributedCount = 0;

            foreach ($reports as $studentId => $report) {
                // Log distribution (actual implementation would send emails/SMS/upload to portal)
                $this->logAction(
                    'report_distributed',
                    "Distributed report for student {$studentId} via {$distributionMethod}",
                    ['student_id' => $studentId, 'method' => $distributionMethod]
                );

                $distributedCount++;
            }

            $data['distribution_summary'] = [
                'method' => $distributionMethod,
                'distributed_count' => $distributedCount,
                'distributed_at' => date('Y-m-d H:i:s'),
                'notifications_sent' => $sendNotifications,
            ];

            // Complete workflow
            $this->completeWorkflow(
                $instance_id,
                json_encode($data),
                "Distributed {$distributedCount} reports via {$distributionMethod}"
            );

            return formatResponse(true, [
                'distributed_count' => $distributedCount,
                'distribution_summary' => $data['distribution_summary'],
            ], 'Reports distributed successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get individual student report
     * 
     * @param int $instance_id Workflow instance ID
     * @param int $student_id Student ID
     * @return array Response with student report
     */
    public function getStudentReport(int $instance_id, int $student_id): array {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            $data = json_decode($instance['data_json'], true) ?: [];
            $reports = $data['generated_reports'] ?? [];

            if (!isset($reports[$student_id])) {
                return formatResponse(false, null, 'Report not found for this student');
            }

            return formatResponse(true, $reports[$student_id], 'Student report retrieved');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
