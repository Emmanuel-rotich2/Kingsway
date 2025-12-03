<?php
namespace App\API\Modules\academic;


use App\API\Includes\WorkflowHandler;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;

/**
 * Examination Management Workflow Handler
 * 
 * 11-STAGE WORKFLOW:
 * 1. Exam Planning → 2. Schedule Creation → 3. Question Paper Submission
 * → 4. Exam Logistics → 5. Exam Administration → 6. Marking Assignment
 * → 7. Marks Recording → 8. Marks Verification → 9. Marks Moderation
 * → 10. Results Compilation → 11. Results Approval
 * 
 * Database Objects Used (CBC-aligned):
 * - Tables: assessments, assessment_results, exam_schedules, grade_rules, grading_scales, term_subject_scores, assessment_type_classifications
 * - Notes: We use the workflow instance as the parent "cycle" (no separate examinations table)
 */
class ExaminationWorkflow extends WorkflowHandler {
    
    public function __construct() {
        parent::__construct('examination_management');
    }

    /**
     * =======================================================================
     * STAGE 1: EXAMINATION PLANNING
     * =======================================================================
     * Role: Academic Director
     * Plan examination including type, term, dates
     */
    public function planExamination($exam_data) {
        try {
            $this->db->beginTransaction();

            // Validate required fields (CBC): title, classification_code (CA/SBA/SA), term_id, academic_year, window dates
            $required = ['title', 'classification_code', 'term_id', 'academic_year', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($exam_data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }
            // Validate classification exists and active
            $clsStmt = $this->db->prepare("SELECT * FROM assessment_type_classifications WHERE code = :code AND status = 'active' LIMIT 1");
            $clsStmt->execute(['code' => $exam_data['classification_code']]);
            $classification = $clsStmt->fetch(PDO::FETCH_ASSOC);
            if (!$classification) {
                throw new Exception('Invalid or inactive assessment classification code');
            }

            // Per-cycle weighting overrides (default: 40% formative, 60% summative)
            $formative_weight = isset($exam_data['formative_weight']) ? (float)$exam_data['formative_weight'] : 0.40;
            $summative_weight = isset($exam_data['summative_weight']) ? (float)$exam_data['summative_weight'] : 0.60;
            
            // Validate weights sum to 1.0
            if (abs(($formative_weight + $summative_weight) - 1.0) > 0.001) {
                throw new Exception('Formative and summative weights must sum to 1.0');
            }

            // Start workflow
            $workflow_data = [
                'title' => $exam_data['title'],
                'classification_code' => $exam_data['classification_code'], // CA | SBA | SA
                'classification' => $classification,
                'term_id' => (int)$exam_data['term_id'],
                'academic_year' => $exam_data['academic_year'],
                'start_date' => $exam_data['start_date'],
                'end_date' => $exam_data['end_date'],
                'formative_weight' => $formative_weight,
                'summative_weight' => $summative_weight,
                'planned_by' => $this->user_id,
                'planned_at' => date('Y-m-d H:i:s')
            ];

            // Use term_id as reference_id for grouping cycles within a term
            $instance_id = $this->startWorkflow('examination_cycle', (int)$exam_data['term_id'], $workflow_data);

            $this->db->commit();

            return formatResponse(true, [
                'workflow_instance_id' => $instance_id,
                'next_stage' => 'schedule_creation'
            ], 'Assessment cycle planned successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('exam_planning_failed', $e->getMessage());
            return formatResponse(false, null, 'Exam planning failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 2: SCHEDULE CREATION
     * =======================================================================
     * Role: Academic Coordinator
     * Create detailed exam timetable
     */
    public function createSchedule($instance_id, $schedule_entries) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'exam_planning') {
                throw new Exception("Invalid workflow state for schedule creation");
            }

            // Validate schedule entries
            if (empty($schedule_entries)) {
                throw new Exception("Schedule entries cannot be empty");
            }

            $instanceData = json_decode($instance['data_json'], true) ?: [];
            $termId = (int)($instanceData['term_id'] ?? 0);
            if ($termId <= 0) {
                throw new Exception('Missing term_id in assessment cycle');
            }

            // Create assessment items (one per class/subject) and optional invigilation schedule
            $assessStmt = $this->db->prepare(
                "INSERT INTO assessments (class_id, subject_id, term_id, title, max_marks, assessment_date, assigned_by, status, learning_outcome_id) 
                 VALUES (:class_id, :subject_id, :term_id, :title, :max_marks, :assessment_date, :assigned_by, 'pending_submission', :learning_outcome_id)"
            );

            $schedStmt = $this->db->prepare(
                "INSERT INTO exam_schedules (class_id, subject_id, exam_date, start_time, end_time, room_id, invigilator_id) 
                 VALUES (:class_id, :subject_id, :exam_date, :start_time, :end_time, :room_id, :invigilator_id)"
            );

            $createdAssessments = [];
            foreach ($schedule_entries as $entry) {
                // Basic validation per entry
                foreach (['class_id','subject_id','exam_date','start_time','end_time','max_marks','title'] as $f) {
                    if (!isset($entry[$f]) || $entry[$f] === '') {
                        throw new Exception("Missing required schedule field: $f");
                    }
                }

                // Create assessment
                $assessStmt->execute([
                    'class_id' => (int)$entry['class_id'],
                    'subject_id' => (int)$entry['subject_id'],
                    'term_id' => $termId,
                    'title' => (string)$entry['title'],
                    'max_marks' => (float)$entry['max_marks'],
                    'assessment_date' => $entry['exam_date'],
                    'assigned_by' => $this->user_id,
                    'learning_outcome_id' => isset($entry['learning_outcome_id']) ? (int)$entry['learning_outcome_id'] : null,
                ]);
                $assessmentId = (int)$this->db->lastInsertId();
                $createdAssessments[] = $assessmentId;

                // Auto-link learning_outcome_id → competency_ids
                $autoLinkedCompetencies = [];
                if (isset($entry['learning_outcome_id']) && (int)$entry['learning_outcome_id'] > 0) {
                    $autoLinkedCompetencies = $this->getCompetenciesForOutcome((int)$entry['learning_outcome_id']);
                }

                // Attach competencies for this assessment (merge auto-linked + manually provided)
                $manualCompIds = !empty($entry['competency_ids']) && is_array($entry['competency_ids']) 
                    ? array_map('intval', $entry['competency_ids']) 
                    : [];
                $allCompIds = array_values(array_unique(array_merge($autoLinkedCompetencies, $manualCompIds)));
                
                if (!empty($allCompIds)) {
                    $instanceData['assessment_competencies'] = $instanceData['assessment_competencies'] ?? [];
                    $instanceData['assessment_competencies'][(string)$assessmentId] = $allCompIds;
                }

                // Create exam schedule (optional room/invigilator)
                $schedStmt->execute([
                    'class_id' => (int)$entry['class_id'],
                    'subject_id' => (int)$entry['subject_id'],
                    'exam_date' => $entry['exam_date'],
                    'start_time' => $entry['start_time'],
                    'end_time' => $entry['end_time'],
                    'room_id' => $entry['room_id'] ?? null,
                    'invigilator_id' => $entry['invigilator_id'] ?? null,
                ]);
            }

            // Persist back to workflow data
            $instanceData['assessments'] = array_values(array_unique(array_merge($instanceData['assessments'] ?? [], $createdAssessments)));
            $this->advanceStage($instance['id'], 'schedule_creation', 'schedule_created', [
                'assessments_created' => count($createdAssessments),
                'assessment_ids' => $createdAssessments,
                'schedule_created_by' => $this->user_id,
                'schedule_created_at' => date('Y-m-d H:i:s')
            ] + $instanceData);

            // Advance workflow
            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance_id,
                'assessments_created' => count($createdAssessments)
            ], 'Assessment schedule created successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('schedule_creation_failed', $e->getMessage());
            return formatResponse(false, null, 'Schedule creation failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 3: QUESTION PAPER SUBMISSION
     * =======================================================================
     * Role: Subject Teachers
     * Submit question papers for examination
     */
    public function submitQuestionPaper($instance_id, $assessment_id, $file) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'schedule_creation') {
                throw new Exception("Invalid workflow state for question paper submission");
            }

            // Upload question paper file
            $uploaded = $this->uploadFile($file, [
                'allowed_types' => ['pdf', 'doc', 'docx'],
                'max_size' => 10 * 1024 * 1024, // 10MB
                'destination' => UPLOAD_PATH . '/assessments/' . $assessment_id . '/papers'
            ]);
            // Store paper path in workflow data
            $data = json_decode($instance['data_json'], true) ?: [];
            $data['papers'] = $data['papers'] ?? [];
            $data['papers'][(string)$assessment_id] = [
                'path' => $uploaded['path'],
                'submitted_by' => $this->user_id,
                'submitted_at' => date('Y-m-d H:i:s')
            ];
            $this->advanceStage($instance['id'], 'question_paper_submission', 'paper_submitted', $data);

            $this->db->commit();

            return formatResponse(true, [
                'assessment_id' => $assessment_id,
                'paper_path' => $uploaded['path']
            ], 'Question paper submitted successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('paper_submission_failed', $e->getMessage());
            return formatResponse(false, null, 'Paper submission failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 4: EXAMINATION LOGISTICS
     * =======================================================================
     * Role: Exam Coordinator
     * Prepare materials, venues, seating arrangements
     */
    public function prepareLogistics($instance_id, $logistics_data) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'question_paper_submission') {
                throw new Exception("Invalid workflow state for exam logistics");
            }

            // Store logistics preparation details
            $instance_data = json_decode($instance['data_json'], true);
            $instance_data['logistics'] = [
                'materials_prepared' => $logistics_data['materials_prepared'] ?? true,
                'venues_confirmed' => $logistics_data['venues_confirmed'] ?? true,
                'seating_arranged' => $logistics_data['seating_arranged'] ?? true,
                'invigilators_briefed' => $logistics_data['invigilators_briefed'] ?? true,
                'prepared_by' => $this->user_id,
                'prepared_at' => date('Y-m-d H:i:s')
            ];

            // Advance to administration stage
            $this->advanceStage($instance['id'], 'exam_logistics', 'logistics_prepared', $instance_data);

            $this->db->commit();

            return formatResponse(true, null, 'Exam logistics prepared successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('logistics_preparation_failed', $e->getMessage());
            return formatResponse(false, null, 'Logistics preparation failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 5: EXAMINATION ADMINISTRATION
     * =======================================================================
     * Role: Invigilators
     * Conduct examinations
     */
    public function conductExamination($instance_id, $assessment_id, $conduct_data = []) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'exam_logistics') {
                throw new Exception("Invalid workflow state for exam administration");
            }

            // Mark assessment as conducted (store metadata only, status remains managed by approvals)
            $data = json_decode($instance['data_json'], true) ?: [];
            $data['administration'] = $data['administration'] ?? [];
            $data['administration'][(string)$assessment_id] = [
                'conducted_by' => $this->user_id,
                'conducted_at' => date('Y-m-d H:i:s'),
                'notes' => $conduct_data['notes'] ?? null
            ];
            $this->advanceStage($instance['id'], 'exam_administration', 'assessment_conducted', $data);

            $this->db->commit();

            return formatResponse(true, [
                'assessment_id' => $assessment_id
            ], 'Exam conducted successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('exam_administration_failed', $e->getMessage());
            return formatResponse(false, null, 'Exam administration failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 6: MARKING ASSIGNMENT
     * =======================================================================
     * Role: Head of Department
     * Assign answer scripts to teachers for marking
     */
    public function assignMarking($instance_id, $assignments) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'exam_administration') {
                throw new Exception("Invalid workflow state for marking assignment");
            }

            // Persist assignments in workflow data (schema has no marker assignment field)
            $data = json_decode($instance['data_json'], true) ?: [];
            $data['marking_assignments'] = $assignments; // [{assessment_id, marker_id}]

            // Advance to marks recording
            $this->advanceStage($instance['id'], 'marking_assignment', 'markers_assigned', [
                'assignments_count' => count($assignments),
                'assigned_by' => $this->user_id,
                'assigned_at' => date('Y-m-d H:i:s')
            ] + $data);

            $this->db->commit();

            return formatResponse(true, [
                'assignments' => count($assignments)
            ], 'Marking assignments completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('marking_assignment_failed', $e->getMessage());
            return formatResponse(false, null, 'Marking assignment failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 7: MARKS RECORDING
     * =======================================================================
     * Role: Subject Teachers
     * Record examination marks
     */
    public function recordMarks($instance_id, $assessment_id, $marks_data) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'marking_assignment') {
                throw new Exception("Invalid workflow state for marks recording");
            }

            // Bulk upsert marks into assessment_results
            // marks_data: [{student_id, marks, remarks?}]
            $ins = $this->db->prepare(
                "INSERT INTO assessment_results (assessment_id, student_id, marks_obtained, grade, points, remarks, submitted_at, is_submitted, is_approved)
                 VALUES (:assessment_id, :student_id, :marks, :grade, :points, :remarks, NOW(), 1, 0)
                 ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), grade = VALUES(grade), points = VALUES(points), remarks = VALUES(remarks), submitted_at = NOW(), is_submitted = 1"
            );

            foreach ($marks_data as $row) {
                if (!isset($row['student_id'], $row['marks'])) {
                    throw new Exception('Each marks entry requires student_id and marks');
                }
                $gradeInfo = $this->mapMarkToGrade((float)$row['marks']);
                $ins->execute([
                    'assessment_id' => $assessment_id,
                    'student_id' => (int)$row['student_id'],
                    'marks' => (float)$row['marks'],
                    'grade' => $gradeInfo['grade_code'] ?? null,
                    'points' => $gradeInfo['grade_points'] ?? null,
                    'remarks' => $row['remarks'] ?? null,
                ]);
            }

            // Mark this assessment as recorded in workflow data
            $data = json_decode($instance['data_json'], true) ?: [];
            $data['marks_recorded'] = $data['marks_recorded'] ?? [];
            $data['marks_recorded'][(string)$assessment_id] = date('Y-m-d H:i:s');
            $this->advanceStage($instance['id'], 'marks_recording', 'marks_recorded', $data);

            $this->db->commit();

            return formatResponse(true, [
                'assessment_id' => $assessment_id,
                'marks_count' => count($marks_data)
            ], 'Marks recorded successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('marks_recording_failed', $e->getMessage());
            return formatResponse(false, null, 'Marks recording failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 8: MARKS VERIFICATION
     * =======================================================================
     * Role: Head of Department
     * Verify recorded marks for accuracy
     */
    public function verifyMarks($instance_id, $assessment_id, $verified = true, $corrections = []) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'marks_recording') {
                throw new Exception("Invalid workflow state for marks verification");
            }

            if (!$verified) {
                $this->db->commit();
                return formatResponse(true, null, 'Marks rejected. Please correct and resubmit.');
            }

            // Apply corrections if any
            if (!empty($corrections)) {
                foreach ($corrections as $correction) {
                    $upd = $this->db->prepare("UPDATE assessment_results SET marks_obtained = :marks WHERE assessment_id = :assessment_id AND student_id = :student_id");
                    $upd->execute([
                        'marks' => (float)$correction['marks'],
                        'assessment_id' => $assessment_id,
                        'student_id' => (int)$correction['student_id']
                    ]);
                    // Recompute grade after correction
                    $gradeInfo = $this->mapMarkToGrade((float)$correction['marks']);
                    $upd2 = $this->db->prepare("UPDATE assessment_results SET grade = :grade, points = :points WHERE assessment_id = :assessment_id AND student_id = :student_id");
                    $upd2->execute([
                        'grade' => $gradeInfo['grade_code'] ?? null,
                        'points' => $gradeInfo['grade_points'] ?? null,
                        'assessment_id' => $assessment_id,
                        'student_id' => (int)$correction['student_id']
                    ]);
                }
            }

            // Mark results as approved
            $approve = $this->db->prepare("UPDATE assessment_results SET is_approved = 1 WHERE assessment_id = :assessment_id");
            $approve->execute(['assessment_id' => $assessment_id]);

            // Aggregate this approved assessment into term_subject_scores (delta-safe)
            $this->aggregateAssessmentToTSS($instance, (int)$assessment_id);

            // Mark aggregated in workflow data to avoid double counting during compilation
            $data = json_decode($instance['data_json'], true) ?: [];
            $agg = $data['aggregated_assessments'] ?? [];
            if (!in_array((int)$assessment_id, $agg, true)) {
                $agg[] = (int)$assessment_id;
            }
            $data['aggregated_assessments'] = $agg;

            $this->advanceStage($instance['id'], 'marks_verification', 'marks_verified', $data);

            $this->db->commit();

            return formatResponse(true, [
                'assessment_id' => $assessment_id,
                'status' => 'approved_and_aggregated'
            ], 'Marks verified and aggregated successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('marks_verification_failed', $e->getMessage());
            return formatResponse(false, null, 'Marks verification failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 9: MARKS MODERATION
     * =======================================================================
     * Role: Principal/Academic Director
     * Moderate marks and apply scaling if needed
     */
    public function moderateMarks($instance_id, $moderation_notes = '', $apply_scaling = false) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'marks_verification') {
                throw new Exception("Invalid workflow state for marks moderation");
            }

            // Record moderation
            $instance_data = json_decode($instance['data_json'], true);
            $instance_data['moderation'] = [
                'moderated_by' => $this->user_id,
                'moderated_at' => date('Y-m-d H:i:s'),
                'notes' => $moderation_notes,
                'scaling_applied' => $apply_scaling
            ];

            if ($apply_scaling) {
                // Apply scaling logic if needed (implementation specific)
                // This is where statistical adjustments would be made
            }

            // Advance to results compilation
            $this->advanceStage($instance['id'], 'marks_moderation', 'moderation_complete', $instance_data);

            $this->db->commit();

            return formatResponse(true, null, 'Marks moderation completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('marks_moderation_failed', $e->getMessage());
            return formatResponse(false, null, 'Marks moderation failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 10: RESULTS COMPILATION
     * =======================================================================
     * Role: System/Academic Coordinator
     * Compile final results, calculate grades and positions
     */
    public function compileResults($instance_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'marks_moderation') {
                throw new Exception("Invalid workflow state for results compilation");
            }

            // Aggregate to term_subject_scores using CBC weighting (default 40% CA, 60% SBA/SA)
            $data = json_decode($instance['data_json'], true) ?: [];
            $termId = (int)($data['term_id'] ?? 0);
            $classification = strtoupper($data['classification_code'] ?? '');
            $assessments = $data['assessments'] ?? [];
            $already = array_map('intval', $data['aggregated_assessments'] ?? []);
            // Consider only not-yet-aggregated assessments
            $assessments = array_values(array_diff(array_map('intval', $assessments), $already));
            if ($termId <= 0 || empty($assessments)) {
                throw new Exception('Missing term or assessments for compilation');
            }

            // Fetch all results for these assessments
            $inClause = implode(',', array_map('intval', $assessments));
            $resStmt = $this->db->query(
                "SELECT ar.assessment_id, a.subject_id, a.class_id, ar.student_id, ar.marks_obtained, a.max_marks
                 FROM assessment_results ar
                 JOIN assessments a ON a.id = ar.assessment_id
                 WHERE ar.is_submitted = 1 AND ar.is_approved = 1 AND ar.assessment_id IN ($inClause)"
            );
            $rows = $resStmt->fetchAll(PDO::FETCH_ASSOC);

            // Group by student_id + subject_id
            $byStudentSubject = [];
            foreach ($rows as $r) {
                $key = $r['student_id'] . ':' . $r['subject_id'];
                if (!isset($byStudentSubject[$key])) {
                    $byStudentSubject[$key] = [
                        'student_id' => (int)$r['student_id'],
                        'subject_id' => (int)$r['subject_id'],
                        'form_total' => 0.0,
                        'form_max' => 0.0,
                        'sum_total' => 0.0,
                        'sum_max' => 0.0,
                    ];
                }
                if ($classification === 'CA') {
                    $byStudentSubject[$key]['form_total'] += (float)$r['marks_obtained'];
                    $byStudentSubject[$key]['form_max'] += (float)$r['max_marks'];
                } else { // SBA or SA → summative bucket
                    $byStudentSubject[$key]['sum_total'] += (float)$r['marks_obtained'];
                    $byStudentSubject[$key]['sum_max'] += (float)$r['max_marks'];
                }
            }

            // Upsert into term_subject_scores
            $up = $this->db->prepare(
                "INSERT INTO term_subject_scores (
                    student_id, term_id, subject_id,
                    formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                    summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                    overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at
                ) VALUES (
                    :student_id, :term_id, :subject_id,
                    :f_total, :f_max, :f_pct, :f_grade, :f_cnt,
                    :s_total, :s_max, :s_pct, :s_grade, :s_cnt,
                    :o_score, :o_pct, :o_grade, :o_points, :a_cnt, NOW()
                ) ON DUPLICATE KEY UPDATE
                    formative_total = formative_total + VALUES(formative_total),
                    formative_max = formative_max + VALUES(formative_max),
                    summative_total = summative_total + VALUES(summative_total),
                    summative_max = summative_max + VALUES(summative_max),
                    formative_percentage = CASE WHEN (formative_max + VALUES(formative_max))>0 THEN ROUND((formative_total + VALUES(formative_total))*100/(formative_max + VALUES(formative_max)),2) ELSE 0 END,
                    summative_percentage = CASE WHEN (summative_max + VALUES(summative_max))>0 THEN ROUND((summative_total + VALUES(summative_total))*100/(summative_max + VALUES(summative_max)),2) ELSE 0 END,
                    assessment_count = assessment_count + VALUES(assessment_count),
                    calculated_at = NOW()"
            );

        // Extract cycle-specific weights from instance data
        $fw = (float)($data['formative_weight'] ?? 0.40);
        $sw = (float)($data['summative_weight'] ?? 0.60);

        foreach ($byStudentSubject as $agg) {
            $f_pct = $agg['form_max'] > 0 ? round($agg['form_total'] * 100 / $agg['form_max'], 2) : 0;
            $s_pct = $agg['sum_max'] > 0 ? round($agg['sum_total'] * 100 / $agg['sum_max'], 2) : 0;
            // Use cycle-specific weighting from instance data
            $overall = round(($f_pct * $fw) + ($s_pct * $sw), 2);
            $gradeInfo = $this->mapMarkToGrade($overall);

            $up->execute([
                    'student_id' => $agg['student_id'],
                    'term_id' => $termId,
                    'subject_id' => $agg['subject_id'],
                    'f_total' => $agg['form_total'],
                    'f_max' => $agg['form_max'],
                    'f_pct' => $f_pct,
                    'f_grade' => $this->mapMarkToGrade($f_pct)['grade_code'] ?? null,
                    'f_cnt' => $classification === 'CA' ? 1 : 0,
                    's_total' => $agg['sum_total'],
                    's_max' => $agg['sum_max'],
                    's_pct' => $s_pct,
                    's_grade' => $this->mapMarkToGrade($s_pct)['grade_code'] ?? null,
                    's_cnt' => ($classification !== 'CA') ? 1 : 0,
                    'o_score' => $overall,
                    'o_pct' => $overall,
                    'o_grade' => $gradeInfo['grade_code'] ?? null,
                    'o_points' => $gradeInfo['grade_points'] ?? null,
                    'a_cnt' => 1,
                ]);
            }

            // Advance to results approval
            $this->advanceStage($instance['id'], 'results_compilation', 'results_compiled', [
                'compiled_by' => $this->user_id,
                'compiled_at' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            return formatResponse(true, null, 'Results compiled successfully (CBC aggregation)');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('results_compilation_failed', $e->getMessage());
            return formatResponse(false, null, 'Results compilation failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 11: RESULTS APPROVAL
     * =======================================================================
     * Role: Principal
     * Final approval and publication of results
     */
    public function approveResults($instance_id, $approved = true, $remarks = '') {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstance($instance_id);
            if (!$instance || $instance['current_stage'] !== 'results_compilation') {
                throw new Exception("Invalid workflow state for results approval");
            }

            if (!$approved) {
                // Send back to compilation
                $this->advanceStage($instance['id'], 'results_compilation', 'approval_rejected', [
                    'rejected_by' => $this->user_id,
                    'rejection_reason' => $remarks
                ]);

                $this->db->commit();
                return formatResponse(true, null, 'Results rejected. Please review and recompile.');
            }

            // Complete workflow (publication is logical in portal visibility)
            $this->completeWorkflow($instance['id'], [
                'approved_by' => $this->user_id,
                'approved_at' => date('Y-m-d H:i:s'),
                'remarks' => $remarks,
                'published' => true
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'instance_id' => $instance_id,
                'status' => 'published'
            ], 'Results approved and published successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('results_approval_failed', $e->getMessage());
            return formatResponse(false, null, 'Results approval failed: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getWorkflowInstanceByReference($ref_type, $ref_id) {
        $sql = "SELECT * FROM workflow_instances 
                WHERE reference_type = :type 
                AND reference_id = :id 
                AND status = 'in_progress'
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $ref_type, 'id' => $ref_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Aggregate a single approved assessment into term_subject_scores (delta update)
    private function aggregateAssessmentToTSS(array $instance, int $assessmentId): void {
        $data = json_decode($instance['data_json'], true) ?: [];
        $termId = (int)($data['term_id'] ?? 0);
        $classification = strtoupper($data['classification_code'] ?? '');
        if ($termId <= 0 || !in_array($classification, ['CA','SBA','SA'], true)) {
            return; // insufficient context
        }

        $resStmt = $this->db->prepare(
            "SELECT ar.assessment_id, a.subject_id, a.class_id, ar.student_id, ar.marks_obtained, a.max_marks
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id
             WHERE ar.is_submitted = 1 AND ar.is_approved = 1 AND ar.assessment_id = :aid"
        );
        $resStmt->execute(['aid' => $assessmentId]);
        $rows = $resStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }

        // Group by student_id + subject_id
        $byStudentSubject = [];
        foreach ($rows as $r) {
            $key = $r['student_id'] . ':' . $r['subject_id'];
            if (!isset($byStudentSubject[$key])) {
                $byStudentSubject[$key] = [
                    'student_id' => (int)$r['student_id'],
                    'subject_id' => (int)$r['subject_id'],
                    'form_total' => 0.0,
                    'form_max' => 0.0,
                    'sum_total' => 0.0,
                    'sum_max' => 0.0,
                ];
            }
            if ($classification === 'CA') {
                $byStudentSubject[$key]['form_total'] += (float)$r['marks_obtained'];
                $byStudentSubject[$key]['form_max'] += (float)$r['max_marks'];
            } else {
                $byStudentSubject[$key]['sum_total'] += (float)$r['marks_obtained'];
                $byStudentSubject[$key]['sum_max'] += (float)$r['max_marks'];
            }
        }

        $up = $this->db->prepare(
            "INSERT INTO term_subject_scores (
                student_id, term_id, subject_id,
                formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at
            ) VALUES (
                :student_id, :term_id, :subject_id,
                :f_total, :f_max, :f_pct, :f_grade, :f_cnt,
                :s_total, :s_max, :s_pct, :s_grade, :s_cnt,
                :o_score, :o_pct, :o_grade, :o_points, :a_cnt, NOW()
            ) ON DUPLICATE KEY UPDATE
                formative_total = formative_total + VALUES(formative_total),
                formative_max = formative_max + VALUES(formative_max),
                summative_total = summative_total + VALUES(summative_total),
                summative_max = summative_max + VALUES(summative_max),
                formative_percentage = CASE WHEN (formative_max + VALUES(formative_max))>0 THEN ROUND((formative_total + VALUES(formative_total))*100/(formative_max + VALUES(formative_max)),2) ELSE 0 END,
                summative_percentage = CASE WHEN (summative_max + VALUES(summative_max))>0 THEN ROUND((summative_total + VALUES(summative_total))*100/(summative_max + VALUES(summative_max)),2) ELSE 0 END,
                assessment_count = assessment_count + VALUES(assessment_count),
                calculated_at = NOW()"
        );

        // Extract cycle-specific weights from instance data
        $fw = (float)($data['formative_weight'] ?? 0.40);
        $sw = (float)($data['summative_weight'] ?? 0.60);

        foreach ($byStudentSubject as $agg) {
            $f_pct = $agg['form_max'] > 0 ? round($agg['form_total'] * 100 / $agg['form_max'], 2) : 0;
            $s_pct = $agg['sum_max'] > 0 ? round($agg['sum_total'] * 100 / $agg['sum_max'], 2) : 0;
            $overall = round(($f_pct * $fw) + ($s_pct * $sw), 2);
            $gradeInfo = $this->mapMarkToGrade($overall);

            $up->execute([
                'student_id' => $agg['student_id'],
                'term_id' => $termId,
                'subject_id' => $agg['subject_id'],
                'f_total' => $agg['form_total'],
                'f_max' => $agg['form_max'],
                'f_pct' => $f_pct,
                'f_grade' => $this->mapMarkToGrade($f_pct)['grade_code'] ?? null,
                'f_cnt' => $classification === 'CA' ? 1 : 0,
                's_total' => $agg['sum_total'],
                's_max' => $agg['sum_max'],
                's_pct' => $s_pct,
                's_grade' => $this->mapMarkToGrade($s_pct)['grade_code'] ?? null,
                's_cnt' => ($classification !== 'CA') ? 1 : 0,
                'o_score' => $overall,
                'o_pct' => $overall,
                'o_grade' => $gradeInfo['grade_code'] ?? null,
                'o_points' => $gradeInfo['grade_points'] ?? null,
                'a_cnt' => 1,
            ]);
        }
    }

    // Map mark percentage to grade using active grading scale
    private function mapMarkToGrade(float $mark): array {
        static $cache = null;
        if ($cache === null) {
            $scaleStmt = $this->db->query("SELECT id FROM grading_scales WHERE status = 'active' ORDER BY id LIMIT 1");
            $scale = $scaleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$scale) {
                return [];
            }
            $rulesStmt = $this->db->prepare("SELECT grade_code, grade_points, performance_level, min_mark, max_mark FROM grade_rules WHERE scale_id = :sid ORDER BY sort_order");
            $rulesStmt->execute(['sid' => (int)$scale['id']]);
            $cache = $rulesStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        foreach ($cache as $r) {
            if ($mark >= (float)$r['min_mark'] && $mark <= (float)$r['max_mark']) {
                return $r;
            }
        }
        return [];
    }

    // Record competency evidence for a set of students for one competency in this term
    public function recordCompetencyEvidence($instance_id, $competency_id, $student_entries, $evidence_date, $notes = null) {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) {
            return formatResponse(false, null, 'Workflow instance not found');
        }
        $data = json_decode($instance['data_json'], true) ?: [];
        $termId = (int)($data['term_id'] ?? 0);
        $year = (int)date('Y');
        if ($termId <= 0) {
            return formatResponse(false, null, 'Missing term context for competency recording');
        }
        try {
            $this->db->beginTransaction();
            $ins = $this->db->prepare("INSERT INTO learner_competencies (student_id, competency_id, academic_year, term_id, performance_level_id, evidence, teacher_notes, assessed_by, assessed_date) VALUES (:student_id, :competency_id, :year, :term_id, :pl_id, :evidence, :notes, :assessed_by, :assessed_date) ON DUPLICATE KEY UPDATE performance_level_id = VALUES(performance_level_id), evidence = VALUES(evidence), teacher_notes = VALUES(teacher_notes), assessed_date = VALUES(assessed_date)");
            foreach ($student_entries as $entry) {
                if (!isset($entry['student_id'])) continue;
                $ins->execute([
                    'student_id' => (int)$entry['student_id'],
                    'competency_id' => (int)$competency_id,
                    'year' => $year,
                    'term_id' => $termId,
                    'pl_id' => isset($entry['performance_level_id']) ? (int)$entry['performance_level_id'] : null,
                    'evidence' => $entry['evidence'] ?? null,
                    'notes' => $notes,
                    'assessed_by' => $this->user_id,
                    'assessed_date' => $evidence_date,
                ]);
            }
            $this->db->commit();
            return formatResponse(true, ['records' => count($student_entries)], 'Competency evidence recorded');
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('competency_record_failed', $e->getMessage());
            return formatResponse(false, null, 'Failed to record competency evidence: ' . $e->getMessage());
        }
    }

    // Bulk record core values acquisition (behavioral evidence)
    public function recordCoreValueEvidence($instance_id, $value_id, $student_entries) {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) {
            return formatResponse(false, null, 'Workflow instance not found');
        }
        $data = json_decode($instance['data_json'], true) ?: [];
        $termId = (int)($data['term_id'] ?? 0);
        $year = (int)date('Y');
        if ($termId <= 0) {
            return formatResponse(false, null, 'Missing term context for value recording');
        }
        try {
            $this->db->beginTransaction();
            $ins = $this->db->prepare("INSERT INTO learner_values_acquisition (student_id, value_id, academic_year, term_id, evidence, incident_date, recorded_by) VALUES (:student_id, :value_id, :year, :term_id, :evidence, :incident_date, :recorded_by)");
            $count = 0;
            foreach ($student_entries as $entry) {
                if (!isset($entry['student_id'], $entry['evidence'], $entry['incident_date'])) continue;
                $ins->execute([
                    'student_id' => (int)$entry['student_id'],
                    'value_id' => (int)$value_id,
                    'year' => $year,
                    'term_id' => $termId,
                    'evidence' => $entry['evidence'],
                    'incident_date' => $entry['incident_date'],
                    'recorded_by' => $this->user_id,
                ]);
                $count++;
            }
            $this->db->commit();
            return formatResponse(true, ['records' => $count], 'Core value evidence recorded');
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('core_value_record_failed', $e->getMessage());
            return formatResponse(false, null, 'Failed to record core value evidence: ' . $e->getMessage());
        }
    }

    /**
     * Auto-link learning outcome to competencies based on subject/learning area context
     * Since the schema doesn't have a direct mapping table, we'll use subject context
     * to return relevant core competencies
     * 
     * @param int $outcome_id The learning outcome ID
     * @return array Array of competency IDs applicable to this outcome
     */
    private function getCompetenciesForOutcome(int $outcome_id): array {
        // Query the learning outcome to get its learning area and grade
        $stmt = $this->db->prepare("SELECT learning_area_id, grade_level FROM learning_outcomes WHERE id = :id");
        $stmt->execute(['id' => $outcome_id]);
        $outcome = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$outcome) {
            return [];
        }
        
        // Get the subject associated with this learning area (if any)
        $subjectStmt = $this->db->prepare("SELECT name FROM subjects WHERE learning_area_id = :la_id LIMIT 1");
        $subjectStmt->execute(['la_id' => (int)$outcome['learning_area_id']]);
        $subject = $subjectStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get competencies that apply to this grade range
        // All active competencies that are relevant to the grade level
        $compStmt = $this->db->prepare("
            SELECT id FROM core_competencies 
            WHERE status = 'active' 
            AND (grade_range IS NULL OR grade_range LIKE :grade OR grade_range LIKE '%PP1-9%')
            ORDER BY sort_order
        ");
        $compStmt->execute(['grade' => '%' . $outcome['grade_level'] . '%']);
        $competencies = $compStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // For now, return a default set of core competencies for all outcomes
        // In a more sophisticated system, you would map specific learning areas to specific competencies
        // For CBC, most subjects touch on: Communication, Critical Thinking, Creativity, Learning to Learn
        
        // If we have subject-specific logic, we could add it here:
        // For example: Mathematics -> Critical Thinking, Problem Solving
        //              Languages -> Communication, Digital Literacy
        //              Sciences -> Critical Thinking, Creativity
        
        return $competencies ?: [];
    }

    /**
     * Get a competency dashboard/summary for a student showing their progress
     * across all core competencies for a given term or academic year
     * 
     * @param int $student_id The student ID
     * @param int|null $term_id Optional term filter
     * @param int|null $academic_year Optional year filter
     * @return array Formatted response with competency summary
     */
    public function getCompetencyDashboard(int $student_id, ?int $term_id = null, ?int $academic_year = null): array {
        try {
            $sql = "
                SELECT 
                    cc.id as competency_id,
                    cc.code as competency_code,
                    cc.name as competency_name,
                    cc.description,
                    plc.code as performance_code,
                    plc.name as performance_level,
                    plc.description as performance_description,
                    COUNT(lc.id) as evidence_count,
                    MAX(lc.assessed_date) as latest_assessment,
                    GROUP_CONCAT(DISTINCT lc.evidence ORDER BY lc.assessed_date DESC SEPARATOR ' | ') as evidence_summary,
                    GROUP_CONCAT(DISTINCT lc.teacher_notes ORDER BY lc.assessed_date DESC SEPARATOR ' | ') as teacher_notes
                FROM core_competencies cc
                LEFT JOIN learner_competencies lc ON lc.competency_id = cc.id AND lc.student_id = :student_id
                LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                WHERE cc.status = 'active'
            ";
            
            $params = ['student_id' => $student_id];
            
            if ($term_id !== null) {
                $sql .= " AND (lc.term_id = :term_id OR lc.term_id IS NULL)";
                $params['term_id'] = $term_id;
            }
            
            if ($academic_year !== null) {
                $sql .= " AND (lc.academic_year = :year OR lc.academic_year IS NULL)";
                $params['year'] = $academic_year;
            }
            
            $sql .= " GROUP BY cc.id, cc.code, cc.name, cc.description, plc.code, plc.name, plc.description ORDER BY cc.sort_order";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $competencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return formatResponse(true, [
                'student_id' => $student_id,
                'term_id' => $term_id,
                'academic_year' => $academic_year,
                'competencies' => $competencies,
                'total_competencies' => count($competencies),
                'assessed_competencies' => count(array_filter($competencies, fn($c) => $c['evidence_count'] > 0))
            ], 'Competency dashboard retrieved');
            
        } catch (Exception $e) {
            $this->logError('competency_dashboard_failed', $e->getMessage());
            return formatResponse(false, null, 'Failed to retrieve competency dashboard: ' . $e->getMessage());
        }
    }
}
