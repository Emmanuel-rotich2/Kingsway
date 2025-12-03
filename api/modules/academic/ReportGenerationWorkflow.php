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
                // Get all active students in class
                $stmt = $this->db->prepare(
                    "SELECT s.id FROM students s
                    INNER JOIN class_streams cs ON s.stream_id = cs.id
                    WHERE cs.class_id = :class_id AND s.status = 'active'"
                );
                $stmt->execute(['class_id' => (int)$scope['class_id']]);
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

            $compiledData = [];

            foreach ($studentIds as $studentId) {
                $studentId = (int)$studentId;
                
                // Get student info
                $studentStmt = $this->db->prepare(
                    "SELECT s.*, c.class_name, cs.stream_name,
                        CONCAT(s.first_name, ' ', s.last_name) as full_name
                    FROM students s
                    LEFT JOIN class_streams cs ON s.stream_id = cs.id
                    LEFT JOIN classes c ON cs.class_id = c.id
                    WHERE s.id = :id"
                );
                $studentStmt->execute(['id' => $studentId]);
                $studentInfo = $studentStmt->fetch(PDO::FETCH_ASSOC);

                if (!$studentInfo) {
                    continue;
                }

                // Get academic scores
                $scoresStmt = $this->db->prepare(
                    "SELECT tss.*, sub.name as subject_name, sub.code as subject_code
                    FROM term_subject_scores tss
                    INNER JOIN subjects sub ON tss.subject_id = sub.id
                    WHERE tss.student_id = :student_id
                    AND tss.term_id = :term_id"
                );
                $scoresStmt->execute([
                    'student_id' => $studentId,
                    'term_id' => $termId,
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
                        'term_id' => $termId,
                        'year' => $academicYear,
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
                        'term_id' => $termId,
                        'year' => $academicYear,
                    ]);
                    $values = $valuesStmt->fetchAll(PDO::FETCH_ASSOC);
                }

                // Get attendance if included
                $attendance = [];
                if ($includeAttendance) {
                    $attendanceStmt = $this->db->prepare(
                        "SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as days_present,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as days_absent,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as days_late
                        FROM attendance
                        WHERE student_id = :student_id
                        AND term_id = :term_id"
                    );
                    $attendanceStmt->execute([
                        'student_id' => $studentId,
                        'term_id' => $termId,
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
