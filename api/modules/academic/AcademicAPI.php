<?php
namespace App\API\Modules\academic;
use App\API\Modules\academic\ExaminationWorkflow;
use App\API\Modules\academic\StudentPromotionWorkflow;
use App\API\Modules\academic\AcademicAssessmentWorkflow;
use App\API\Modules\academic\ReportGenerationWorkflow;
use App\API\Modules\academic\LibraryManagementWorkflow;
use App\API\Modules\academic\CurriculumPlanningWorkflow;
use App\API\Modules\academic\AcademicYearTransitionWorkflow;

use App\API\Includes\BaseAPI;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use PDO;
use Exception;

class AcademicAPI extends BaseAPI
{
    private $examinationWorkflow;
    private $promotionWorkflow;
    private $assessmentWorkflow;
    private $reportWorkflow;
    private $libraryWorkflow;
    private $curriculumWorkflow;
    private $yearTransitionWorkflow;

    public function __construct()
    {
        parent::__construct('academic');

        // Initialize all workflows (each workflow now has its own constructor that sets workflow_code)
        $this->examinationWorkflow = new ExaminationWorkflow();
        $this->promotionWorkflow = new StudentPromotionWorkflow();
        $this->assessmentWorkflow = new AcademicAssessmentWorkflow();
        $this->reportWorkflow = new ReportGenerationWorkflow();
        $this->libraryWorkflow = new LibraryManagementWorkflow();
        $this->curriculumWorkflow = new CurriculumPlanningWorkflow();
        $this->yearTransitionWorkflow = new AcademicYearTransitionWorkflow();
    }

    // ========================================================================
    // WORKFLOW METHODS - Examination (11-Stage Workflow)
    // Maps to actual ExaminationWorkflow methods
    // ========================================================================

    // Stage 1: Planning
    public function startExaminationWorkflow($data)
    {
        return $this->examinationWorkflow->planExamination($data);
    }

    // Stage 2: Schedule Creation
    public function createExamSchedule($instanceId, $scheduleEntries)
    {
        return $this->examinationWorkflow->createSchedule($instanceId, $scheduleEntries);
    }

    // Stage 3: Question Paper Submission
    public function submitQuestionPaper($instanceId, $assessmentId, $file)
    {
        return $this->examinationWorkflow->submitQuestionPaper($instanceId, $assessmentId, $file);
    }

    // Stage 4: Exam Logistics Preparation
    public function prepareExamLogistics($instanceId, $logisticsData)
    {
        return $this->examinationWorkflow->prepareLogistics($instanceId, $logisticsData);
    }

    // Stage 5: Exam Administration/Conduct
    public function conductExamination($instanceId, $assessmentId, $conductData = [])
    {
        return $this->examinationWorkflow->conductExamination($instanceId, $assessmentId, $conductData);
    }

    // Stage 6: Marking Assignment
    public function assignExamMarking($instanceId, $assignments)
    {
        return $this->examinationWorkflow->assignMarking($instanceId, $assignments);
    }

    // Stage 7: Marks Recording
    public function recordExamMarks($instanceId, $assessmentId, $marksData)
    {
        return $this->examinationWorkflow->recordMarks($instanceId, $assessmentId, $marksData);
    }

    // Stage 8: Marks Verification
    public function verifyExamMarks($instanceId, $assessmentId, $verified = true, $corrections = [])
    {
        return $this->examinationWorkflow->verifyMarks($instanceId, $assessmentId, $verified, $corrections);
    }

    // Stage 9: Marks Moderation
    public function moderateExamMarks($instanceId, $moderationNotes = '', $applyScaling = false)
    {
        return $this->examinationWorkflow->moderateMarks($instanceId, $moderationNotes, $applyScaling);
    }

    // Stage 10: Results Compilation
    public function compileExamResults($instanceId)
    {
        return $this->examinationWorkflow->compileResults($instanceId);
    }

    // Stage 11: Results Approval
    public function approveExamResults($instanceId, $approved = true, $remarks = '')
    {
        return $this->examinationWorkflow->approveResults($instanceId, $approved, $remarks);
    }

    // Additional: Competency & Core Values Recording
    public function recordCompetencyEvidence($instanceId, $competencyId, $studentEntries, $evidenceDate, $notes = null)
    {
        return $this->examinationWorkflow->recordCompetencyEvidence($instanceId, $competencyId, $studentEntries, $evidenceDate, $notes);
    }

    public function recordCoreValueEvidence($instanceId, $valueId, $studentEntries)
    {
        return $this->examinationWorkflow->recordCoreValueEvidence($instanceId, $valueId, $studentEntries);
    }

    // Dashboard/Reporting
    public function getCompetencyDashboard($studentId, $termId = null, $academicYear = null)
    {
        if (empty($studentId)) {
            return [
                'status' => 'error',
                'message' => 'Missing required student_id',
                'code' => 400
            ];
        }
        return $this->examinationWorkflow->getCompetencyDashboard($studentId, $termId, $academicYear);
    }

    // ========================================================================
    // WORKFLOW METHODS - Student Promotion
    // ========================================================================

    public function startPromotionWorkflow($data)
    {
        return $this->promotionWorkflow->defineCriteria($data);
    }

    public function identifyPromotionCandidates($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->identifyCandidates($instanceId, $data);
    }

    public function validatePromotionEligibility($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->validateEligibility($instanceId, $data);
    }

    public function executePromotions($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->executePromotion($instanceId, $data);
    }

    public function generatePromotionReports($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing promotion instance_id',
                'code' => 400
            ];
        }
        return $this->promotionWorkflow->generateReports($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Academic Assessment
    // ========================================================================

    public function startAssessmentWorkflow($data)
    {
        return $this->assessmentWorkflow->planAssessment($data);
    }

    public function createAssessmentItems($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->createItems($instanceId, $data);
    }

    public function administerAssessment($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->administerAssessment($instanceId, $data);
    }

    public function markAndGradeAssessment($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->markAndGrade($instanceId, $data);
    }

    public function analyzeAssessmentResults($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing assessment instance_id',
                'code' => 400
            ];
        }
        return $this->assessmentWorkflow->analyzeResults($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Report Generation
    // ========================================================================

    public function startReportWorkflow($data)
    {
        return $this->reportWorkflow->defineScope($data);
    }

    public function compileReportData($instanceId, $data)
    {
        return $this->reportWorkflow->compileData($instanceId, $data);
    }

    public function generateStudentReports($instanceId, $data)
    {
        return $this->reportWorkflow->generateReports($instanceId, $data);
    }

    public function reviewAndApproveReports($instanceId, $data)
    {
        return $this->reportWorkflow->reviewAndApprove($instanceId, $data);
    }

    public function distributeReports($instanceId, $data)
    {
        return $this->reportWorkflow->distributeReports($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Library Management
    // ========================================================================

    public function startLibraryWorkflow($data)
    {
        return $this->libraryWorkflow->acquisitionRequest($data);
    }

    public function reviewLibraryRequest($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->reviewAndApprove($instanceId, $data);
    }

    public function catalogLibraryResources($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->catalogResources($instanceId, $data);
    }

    public function distributeAndTrackResources($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing library instance_id',
                'code' => 400
            ];
        }
        return $this->libraryWorkflow->distributeAndTrack($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Curriculum Planning
    // ========================================================================

    public function startCurriculumWorkflow($data)
    {
        return $this->curriculumWorkflow->reviewFramework($data);
    }

    public function mapCurriculumOutcomes($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->mapOutcomes($instanceId, $data);
    }

    public function createCurriculumScheme($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->createScheme($instanceId, $data);
    }

    public function reviewAndApproveCurriculum($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing curriculum instance_id',
                'code' => 400
            ];
        }
        return $this->curriculumWorkflow->reviewAndApprove($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW METHODS - Academic Year Transition
    // ========================================================================

    public function startYearTransitionWorkflow($data)
    {
        return $this->yearTransitionWorkflow->prepareCalendar($data);
    }

    public function archiveAcademicData($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->archiveData($instanceId, $data);
    }

    public function executeYearPromotions($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->executePromotions($instanceId, $data);
    }

    public function setupNewAcademicYear($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->setupNewYear($instanceId, $data);
    }

    public function migrateCompetencyBaselines($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->migrateBaselines($instanceId, $data);
    }

    public function validateYearReadiness($instanceId, $data)
    {
        if (empty($instanceId)) {
            return [
                'status' => 'error',
                'message' => 'Missing year transition instance_id',
                'code' => 400
            ];
        }
        return $this->yearTransitionWorkflow->validateReadiness($instanceId, $data);
    }

    // ========================================================================
    // WORKFLOW STATUS AND MANAGEMENT
    // ========================================================================

    public function getWorkflowStatus($workflowType, $instanceId)
    {
        $workflow = null;
        switch ($workflowType) {
            case 'examination':
                $workflow = $this->examinationWorkflow;
                break;
            case 'promotion':
                $workflow = $this->promotionWorkflow;
                break;
            case 'assessment':
                $workflow = $this->assessmentWorkflow;
                break;
            case 'report':
                $workflow = $this->reportWorkflow;
                break;
            case 'library':
                $workflow = $this->libraryWorkflow;
                break;
            case 'curriculum':
                $workflow = $this->curriculumWorkflow;
                break;
            case 'year-transition':
                $workflow = $this->yearTransitionWorkflow;
                break;
            default:
                throw new Exception('Invalid workflow type');
        }

        return $workflow->getWorkflowInstance($instanceId);
    }

    // List all learning areas with pagination and search
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE name LIKE ? OR code LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM learning_areas $where");
            if (!empty($bindings)) {
                $stmt->execute($bindings);
            } else {
                $stmt->execute();
            }
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "SELECT * FROM learning_areas $where ORDER BY $sort $order LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);

            if (!empty($bindings)) {
                $stmt->execute(array_merge($bindings, [$limit, $offset]));
            } else {
                $stmt->execute([$limit, $offset]);
            }

            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed learning areas');

            return successResponse([
                'subjects' => $subjects,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single learning area
    public function get($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            $subject = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subject) {
                return errorResponse('Learning area not found');
            }

            $this->logAction('read', $id, "Retrieved learning area details: {$subject['name']}");

            return successResponse($subject);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new learning area
    public function create($data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['name', 'code'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "INSERT INTO learning_areas (name, code, description, status) VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['code'],
                $data['description'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $subjectId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $subjectId, "Created new learning area: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area created successfully',
                'data' => ['id' => $subjectId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update learning area
    public function update($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if learning area exists
            $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Learning area not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['name', 'code', 'description', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE learning_areas SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated learning area details");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete learning area (soft delete)
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE learning_areas SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Learning area not found');
            }

            $this->logAction('delete', $id, "Deactivated learning area");

            return successResponse([
                'status' => 'success',
                'message' => 'Learning area deactivated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints - Redirect to new methods
    public function handleCustomGet($id, $action, $params)
    {
        try {
            $result = null;
            switch ($action) {
                case 'teachers':
                    // Use new method: getSubjectTeachers() instead of old getAssignedTeachers()
                    $result = $this->getSubjectTeachers($id);
                    break;
                case 'classes':
                    // Get classes where this subject is taught via class_schedules
                    $result = $this->getSubjectClasses($id);
                    break;
                case 'assessments':
                    // Get assessments for this curriculum unit
                    $result = $this->getSubjectAssessments($id, $params);
                    break;
                default:
                    $result = errorResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
                    break;
            }
            return $result;
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom POST endpoints - Redirect to workflows or new methods
    public function handleCustomPost($id, $action, $data)
    {
        switch ($action) {
            case 'assign-teacher':
                // Create a schedule entry instead of using phantom teacher_subjects table
                // This assigns a teacher to a subject for a specific class via timetable
                $data['subject_id'] = $id;
                if (empty($data['day_of_week']) || empty($data['start_time']) || empty($data['end_time'])) {
                    return errorResponse('To assign a teacher, you must create a timetable entry. Required: class_id, teacher_id, day_of_week, start_time, end_time');
                }
                return $this->createClassSchedule($data);

            case 'create-assessment':
                // Use the assessment workflow for proper assessment creation (Stage 1: Plan Assessment)
                $data['subject_id'] = $id;
                return $this->assessmentWorkflow->planAssessment($data);

            default:
                return errorResponse('Invalid action');
        }
    }

    // Helper method: Get classes where a subject is taught
    private function getSubjectClasses($subjectId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    c.id as class_id,
                    c.name as class_name,
                    c.grade_level,
                    COUNT(DISTINCT cs.id) as schedule_count,
                    GROUP_CONCAT(DISTINCT CONCAT(staff.first_name, ' ', staff.last_name) SEPARATOR ', ') as teachers
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN staff ON cs.teacher_id = staff.id
                WHERE cs.subject_id = ? AND cs.status = 'active'
                GROUP BY c.id
                ORDER BY c.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => $classes
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Helper method: Get assessments for a curriculum unit/subject
    private function getSubjectAssessments($subjectId, $params)
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = "WHERE a.subject_id = ?";
            $bindings = [$subjectId];

            if (!empty($params['term_id'])) {
                $where .= " AND a.term_id = ?";
                $bindings[] = $params['term_id'];
            }

            if (!empty($params['class_id'])) {
                $where .= " AND a.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            if (!empty($params['status'])) {
                $where .= " AND a.status = ?";
                $bindings[] = $params['status'];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM assessments a
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    a.*,
                    c.name as class_name,
                    cu.name as subject_name,
                    at.name as term_name,
                    CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
                    COUNT(ar.id) as total_submissions,
                    AVG(ar.marks_obtained) as average_marks
                FROM assessments a
                JOIN classes c ON a.class_id = c.id
                JOIN curriculum_units cu ON a.subject_id = cu.id
                JOIN academic_terms at ON a.term_id = at.id
                JOIN users u ON a.assigned_by = u.id
                JOIN staff creator ON u.id = creator.user_id
                LEFT JOIN assessment_results ar ON a.id = ar.assessment_id
                $where
                GROUP BY a.id
                ORDER BY a.assessment_date DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => [
                    'assessments' => $assessments,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // DEPRECATED OLD HELPER METHODS - NOW USING WORKFLOWS AND NEW METHODS
    // These have been replaced by proper implementations using actual DB schema
    // ========================================================================
    // OLD: getAssignedTeachers() - NOW USE: getSubjectTeachers()
    // OLD: getAssignedClasses() - NOW USE: getSubjectClasses() 
    // OLD: getAssessments() - NOW USE: getSubjectAssessments()
    // OLD: assignTeacher() - NOW USE: createClassSchedule() with teacher assignment
    // OLD: createAssessment() - NOW USE: assessmentWorkflow->createAssessment()

    public function getLessonPlans($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            // Build WHERE clause
            $where = ["1=1"];
            $bindings = [];

            if (!empty($params['teacher_id'])) {
                $where[] = "lp.teacher_id = ?";
                $bindings[] = $params['teacher_id'];
            }

            if (!empty($params['class_id'])) {
                $where[] = "lp.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            if (!empty($params['learning_area_id'])) {
                $where[] = "lp.learning_area_id = ?";
                $bindings[] = $params['learning_area_id'];
            }

            if (!empty($params['status'])) {
                $where[] = "lp.status = ?";
                $bindings[] = $params['status'];
            }

            if (!empty($params['from_date'])) {
                $where[] = "lp.lesson_date >= ?";
                $bindings[] = $params['from_date'];
            }

            if (!empty($params['to_date'])) {
                $where[] = "lp.lesson_date <= ?";
                $bindings[] = $params['to_date'];
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "
                SELECT COUNT(*)
                FROM lesson_plans lp
                WHERE $whereClause
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    lp.*,
                    la.name as learning_area_name,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    cu.name as unit_name,
                    approver.first_name as approver_first_name,
                    approver.last_name as approver_last_name
                FROM lesson_plans lp
                JOIN learning_areas la ON lp.learning_area_id = la.id
                JOIN classes c ON lp.class_id = c.id
                JOIN staff s ON lp.teacher_id = s.id
                LEFT JOIN curriculum_units cu ON lp.unit_id = cu.id
                LEFT JOIN staff approver ON lp.approved_by = approver.id
                WHERE $whereClause
                ORDER BY lp.lesson_date DESC, lp.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed lesson plans');

            return successResponse([
                'status' => 'success',
                'data' => [
                    'lesson_plans' => $plans,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonPlan($data)
    {
        try {
            $required = ['learning_area_id', 'class_id', 'teacher_id', 'unit_id', 'topic', 'objectives', 'activities', 'lesson_date', 'duration'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $sql = "
                INSERT INTO lesson_plans (
                    teacher_id,
                    learning_area_id,
                    class_id,
                    unit_id,
                    topic,
                    subtopic,
                    objectives,
                    resources,
                    activities,
                    assessment,
                    homework,
                    lesson_date,
                    duration,
                    status,
                    remarks
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['teacher_id'],
                $data['learning_area_id'],
                $data['class_id'],
                $data['unit_id'],
                $data['topic'],
                $data['subtopic'] ?? null,
                $data['objectives'],
                $data['resources'] ?? null,
                $data['activities'],
                $data['assessment'] ?? null,
                $data['homework'] ?? null,
                $data['lesson_date'],
                $data['duration'],
                $data['status'] ?? 'draft',
                $data['remarks'] ?? null
            ]);

            $planId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $planId, "Created lesson plan: {$data['topic']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan created successfully',
                'data' => ['id' => $planId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getCurriculumUnits($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            // Build WHERE clause
            $where = "WHERE cu.status = 'active'";
            $bindings = [];

            if (!empty($search)) {
                $where .= " AND (cu.name LIKE ? OR la.name LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Filter by learning area if specified
            if (!empty($params['learning_area_id'])) {
                $where .= " AND cu.learning_area_id = ?";
                $bindings[] = $params['learning_area_id'];
            }

            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT cu.id)
                FROM curriculum_units cu
                JOIN learning_areas la ON cu.learning_area_id = la.id
                $where
            ";
            $stmt = $this->db->prepare($countSql);
            if (!empty($bindings)) {
                $stmt->execute($bindings);
            } else {
                $stmt->execute();
            }
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    cu.*,
                    la.name as learning_area_name,
                    la.code as learning_area_code,
                    COUNT(DISTINCT ut.id) as topic_count
                FROM curriculum_units cu
                JOIN learning_areas la ON cu.learning_area_id = la.id
                LEFT JOIN unit_topics ut ON cu.id = ut.unit_id AND ut.status = 'active'
                $where
                GROUP BY cu.id
                ORDER BY cu.order_sequence ASC, cu.name ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            if (!empty($bindings)) {
                $stmt->execute(array_merge($bindings, [$limit, $offset]));
            } else {
                $stmt->execute([$limit, $offset]);
            }
            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed curriculum units');

            return successResponse([
                'status' => 'success',
                'data' => [
                    'units' => $units,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createCurriculumUnit($data)
    {
        try {
            $required = ['learning_area_id', 'name'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get next order_sequence if not provided
            if (!isset($data['order_sequence'])) {
                $stmt = $this->db->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 FROM curriculum_units WHERE learning_area_id = ?");
                $stmt->execute([$data['learning_area_id']]);
                $data['order_sequence'] = $stmt->fetchColumn();
            }

            $sql = "
                INSERT INTO curriculum_units (
                    learning_area_id,
                    name,
                    description,
                    learning_outcomes,
                    suggested_resources,
                    duration,
                    order_sequence,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['learning_outcomes'] ?? null,
                $data['suggested_resources'] ?? null,
                $data['duration'] ?? 0,
                $data['order_sequence'],
                $data['status'] ?? 'active'
            ]);

            $unitId = $this->db->lastInsertId();

            // Add topics if provided
            if (!empty($data['topics'])) {
                $sql = "
                    INSERT INTO unit_topics (
                        unit_id,
                        name,
                        description,
                        learning_outcomes,
                        suggested_activities,
                        duration,
                        order_sequence,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['topics'] as $index => $topic) {
                    $stmt->execute([
                        $unitId,
                        $topic['name'],
                        $topic['description'] ?? null,
                        $topic['learning_outcomes'] ?? null,
                        $topic['suggested_activities'] ?? null,
                        $topic['duration'] ?? 0,
                        $topic['order_sequence'] ?? ($index + 1),
                        $topic['status'] ?? 'active'
                    ]);
                }
            }

            $this->db->commit();
            $this->logAction('create', $unitId, "Created curriculum unit: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit created successfully',
                'data' => ['id' => $unitId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getAcademicTerms($params = [])
    {
        try {
            $sql = "
                SELECT 
                    at.*,
                    COUNT(DISTINCT ac.id) as calendar_events
                FROM academic_terms at
                LEFT JOIN academic_calendar ac ON at.id = ac.term_id
                GROUP BY at.id
                ORDER BY at.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($terms);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createAcademicTerm($data)
    {
        try {
            $required = ['name', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO academic_terms (
                    name,
                    start_date,
                    end_date,
                    description,
                    status
                ) VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['start_date'],
                $data['end_date'],
                $data['description'] ?? null,
                'active'
            ]);

            $termId = $this->db->lastInsertId();

            return successResponse([
                'status' => 'success',
                'message' => 'Academic term created successfully',
                'data' => ['id' => $termId]
            ],201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchemeOfWork($params = [])
    {
        try {
            $sql = "
                SELECT 
                    sw.*,
                    la.name as learning_area_name,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM schemes_of_work sw
                JOIN learning_areas la ON sw.learning_area_id = la.id
                JOIN classes c ON sw.class_id = c.id
                JOIN staff s ON sw.teacher_id = s.id
                WHERE sw.status = 'active'
                ORDER BY sw.term_id, sw.week_number
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schemes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schemes);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSchemeOfWork($data)
    {
        try {
            $required = ['learning_area_id', 'class_id', 'teacher_id', 'term_id', 'week_number'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO schemes_of_work (
                    learning_area_id,
                    class_id,
                    teacher_id,
                    term_id,
                    week_number,
                    topics,
                    objectives,
                    resources,
                    activities,
                    assessment,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['learning_area_id'],
                $data['class_id'],
                $data['teacher_id'],
                $data['term_id'],
                $data['week_number'],
                json_encode($data['topics'] ?? []),
                json_encode($data['objectives'] ?? []),
                json_encode($data['resources'] ?? []),
                json_encode($data['activities'] ?? []),
                json_encode($data['assessment'] ?? []),
                'active'
            ]);

            $schemeId = $this->db->lastInsertId();

            return successResponse([
                'status' => 'success',
                'message' => 'Scheme of work created successfully',
                'data' => ['id' => $schemeId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getLessonObservations($params = [])
    {
        try {
            $sql = "
                SELECT 
                    lo.*,
                    CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                    CONCAT(o.first_name, ' ', o.last_name) as observer_name,
                    la.name as learning_area_name,
                    c.name as class_name
                FROM lesson_observations lo
                JOIN staff t ON lo.teacher_id = t.id
                JOIN staff o ON lo.observer_id = o.id
                JOIN learning_areas la ON lo.learning_area_id = la.id
                JOIN classes c ON lo.class_id = c.id
                ORDER BY lo.observation_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $observations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($observations);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLessonObservation($data)
    {
        try {
            $required = ['teacher_id', 'observer_id', 'learning_area_id', 'class_id', 'observation_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO lesson_observations (
                    teacher_id,
                    observer_id,
                    learning_area_id,
                    class_id,
                    observation_date,
                    strengths,
                    areas_for_improvement,
                    recommendations,
                    rating,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['teacher_id'],
                $data['observer_id'],
                $data['learning_area_id'],
                $data['class_id'],
                $data['observation_date'],
                json_encode($data['strengths'] ?? []),
                json_encode($data['areas_for_improvement'] ?? []),
                json_encode($data['recommendations'] ?? []),
                $data['rating'] ?? null,
                'completed'
            ]);

            $observationId = $this->db->lastInsertId();

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson observation created successfully',
                'data' => ['id' => $observationId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // CURRICULUM UNITS CRUD (Additional Methods)
    // ========================================================================

    public function getCurriculumUnit($id)
    {
        try {
            $sql = "
                SELECT 
                    cu.*,
                    la.name as learning_area_name,
                    la.code as learning_area_code
                FROM curriculum_units cu
                JOIN learning_areas la ON cu.learning_area_id = la.id
                WHERE cu.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return errorResponse('Curriculum unit not found');
            }

            // Get topics for this unit
            $sql = "SELECT * FROM unit_topics WHERE unit_id = ? AND status = 'active' ORDER BY order_sequence";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $unit['topics'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', $id, "Retrieved curriculum unit: {$unit['name']}");

            return successResponse($unit);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateCurriculumUnit($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if unit exists
            $stmt = $this->db->prepare("SELECT id, name FROM curriculum_units WHERE id = ?");
            $stmt->execute([$id]);
            $unit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$unit) {
                return errorResponse('Curriculum unit not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['learning_area_id', 'name', 'description', 'learning_outcomes', 'suggested_resources', 'duration', 'order_sequence', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE curriculum_units SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated curriculum unit: {$unit['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteCurriculumUnit($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE curriculum_units SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Curriculum unit not found');
            }

            $this->logAction('delete', $id, "Soft deleted curriculum unit");

            return successResponse([
                'status' => 'success',
                'message' => 'Curriculum unit deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // UNIT TOPICS CRUD
    // ========================================================================

    public function listUnitTopics($unitId = null, $params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = "WHERE 1=1";
            $bindings = [];

            if ($unitId !== null) {
                $where .= " AND ut.unit_id = ?";
                $bindings[] = $unitId;
            }

            if (!empty($params['status'])) {
                $where .= " AND ut.status = ?";
                $bindings[] = $params['status'];
            } else {
                $where .= " AND ut.status = 'active'";
            }

            // Get total count
            $countSql = "SELECT COUNT(*) FROM unit_topics ut $where";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    ut.*,
                    cu.name as unit_name
                FROM unit_topics ut
                JOIN curriculum_units cu ON ut.unit_id = cu.id
                $where
                ORDER BY ut.order_sequence ASC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'status' => 'success',
                'data' => [
                    'topics' => $topics,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getUnitTopic($id)
    {
        try {
            $sql = "
                SELECT 
                    ut.*,
                    cu.name as unit_name,
                    cu.learning_area_id,
                    la.name as learning_area_name
                FROM unit_topics ut
                JOIN curriculum_units cu ON ut.unit_id = cu.id
                JOIN learning_areas la ON cu.learning_area_id = la.id
                WHERE ut.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $topic = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$topic) {
                return errorResponse('Unit topic not found');
            }

            return successResponse($topic);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createUnitTopic($data)
    {
        try {
            $required = ['unit_id', 'name'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get next order_sequence if not provided
            if (!isset($data['order_sequence'])) {
                $stmt = $this->db->prepare("SELECT COALESCE(MAX(order_sequence), 0) + 1 FROM unit_topics WHERE unit_id = ?");
                $stmt->execute([$data['unit_id']]);
                $data['order_sequence'] = $stmt->fetchColumn();
            }

            $sql = "
                INSERT INTO unit_topics (
                    unit_id,
                    name,
                    description,
                    learning_outcomes,
                    suggested_activities,
                    duration,
                    order_sequence,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['unit_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['learning_outcomes'] ?? null,
                $data['suggested_activities'] ?? null,
                $data['duration'] ?? 0,
                $data['order_sequence'],
                $data['status'] ?? 'active'
            ]);

            $topicId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $topicId, "Created unit topic: {$data['name']}");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic created successfully',
                'data' => ['id' => $topicId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function updateUnitTopic($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if topic exists
            $stmt = $this->db->prepare("SELECT id FROM unit_topics WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Unit topic not found');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['unit_id', 'name', 'description', 'learning_outcomes', 'suggested_activities', 'duration', 'order_sequence', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE unit_topics SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated unit topic");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteUnitTopic($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE unit_topics SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Unit topic not found');
            }

            $this->logAction('delete', $id, "Soft deleted unit topic");

            return successResponse([
                'status' => 'success',
                'message' => 'Unit topic deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // LESSON PLANS CRUD (Additional Methods)
    // ========================================================================

    public function getLessonPlan($id)
    {
        try {
            $sql = "
                SELECT 
                    lp.*,
                    la.name as learning_area_name,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    cu.name as unit_name,
                    CONCAT(approver.first_name, ' ', approver.last_name) as approved_by_name
                FROM lesson_plans lp
                JOIN learning_areas la ON lp.learning_area_id = la.id
                JOIN classes c ON lp.class_id = c.id
                JOIN staff s ON lp.teacher_id = s.id
                LEFT JOIN curriculum_units cu ON lp.unit_id = cu.id
                LEFT JOIN staff approver ON lp.approved_by = approver.id
                WHERE lp.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            return successResponse($plan);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateLessonPlan($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists
            $stmt = $this->db->prepare("SELECT id, status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            // Prevent editing approved plans
            if ($plan['status'] === 'approved' && !isset($data['allow_edit_approved'])) {
                return errorResponse('Cannot edit approved lesson plan');
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['teacher_id', 'learning_area_id', 'class_id', 'unit_id', 'topic', 'subtopic', 'objectives', 'resources', 'activities', 'assessment', 'homework', 'lesson_date', 'duration', 'status', 'remarks'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE lesson_plans SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteLessonPlan($id)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists and status
            $stmt = $this->db->prepare("SELECT status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            // Prevent deleting approved plans
            if ($plan['status'] === 'approved') {
                return errorResponse('Cannot delete approved lesson plan');
            }

            // Soft delete by updating status
            $stmt = $this->db->prepare("DELETE FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();
            $this->logAction('delete', $id, "Deleted lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function approveLessonPlan($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if plan exists
            $stmt = $this->db->prepare("SELECT id, status FROM lesson_plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$plan) {
                return errorResponse('Lesson plan not found');
            }

            if ($plan['status'] !== 'submitted') {
                return errorResponse('Only submitted lesson plans can be approved');
            }

            $approver_id = $data['approved_by'] ?? $this->getCurrentUserId();

            $sql = "UPDATE lesson_plans SET status = 'approved', approved_by = ?, approved_at = NOW(), remarks = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$approver_id, $data['remarks'] ?? null, $id]);

            $this->db->commit();
            $this->logAction('update', $id, "Approved lesson plan");

            return successResponse([
                'status' => 'success',
                'message' => 'Lesson plan approved successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // TIMETABLE (CLASS SCHEDULES) CRUD
    // ========================================================================

    public function listClassSchedules($classId = null, $params = [])
    {
        try {
            $where = ["cs.status = 'active'"];
            $bindings = [];

            if ($classId !== null) {
                $where[] = "cs.class_id = ?";
                $bindings[] = $classId;
            }

            if (!empty($params['teacher_id'])) {
                $where[] = "cs.teacher_id = ?";
                $bindings[] = $params['teacher_id'];
            }

            if (!empty($params['day_of_week'])) {
                $where[] = "cs.day_of_week = ?";
                $bindings[] = $params['day_of_week'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    cs.*,
                    c.name as class_name,
                    cu.name as subject_name,
                    la.name as learning_area_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    r.name as room_name
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
                LEFT JOIN learning_areas la ON cu.learning_area_id = la.id
                LEFT JOIN staff s ON cs.teacher_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE $whereClause
                ORDER BY 
                    FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    cs.start_time ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getClassSchedule($id)
    {
        try {
            $sql = "
                SELECT 
                    cs.*,
                    c.name as class_name,
                    cu.name as subject_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    r.name as room_name
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
                LEFT JOIN staff s ON cs.teacher_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse('Class schedule not found');
            }

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createClassSchedule($data)
    {
        try {
            $required = ['class_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Check for conflicts
            $conflict = $this->checkScheduleConflict($data);
            if ($conflict !== null) {
                $this->db->rollBack();
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Schedule conflict detected',
                    'conflict' => $conflict
                ], 409);
            }

            $sql = "
                INSERT INTO class_schedules (
                    class_id,
                    day_of_week,
                    start_time,
                    end_time,
                    subject_id,
                    teacher_id,
                    room_id,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['class_id'],
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['subject_id'] ?? null,
                $data['teacher_id'] ?? null,
                $data['room_id'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $scheduleId, "Created class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function updateClassSchedule($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if schedule exists
            $stmt = $this->db->prepare("SELECT * FROM class_schedules WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                return errorResponse('Class schedule not found');
            }

            // Merge existing with updates for conflict check
            $checkData = array_merge($existing, $data);
            $checkData['exclude_id'] = $id;

            // Check for conflicts
            $conflict = $this->checkScheduleConflict($checkData);
            if ($conflict !== null) {
                $this->db->rollBack();
                return errorResponse([
                    'status' => 'error',
                    'message' => 'Schedule conflict detected',
                    'conflict' => $conflict
                ], 409);
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = ['class_id', 'day_of_week', 'start_time', 'end_time', 'subject_id', 'teacher_id', 'room_id', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE class_schedules SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule updated successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function deleteClassSchedule($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM class_schedules WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse('Class schedule not found');
            }

            $this->logAction('delete', $id, "Deleted class schedule");

            return successResponse([
                'status' => 'success',
                'message' => 'Class schedule deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTeacherSchedule($teacherId)
    {
        try {
            $sql = "
                SELECT 
                    cs.*,
                    c.name as class_name,
                    cu.name as subject_name,
                    r.name as room_name
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.teacher_id = ? AND cs.status = 'active'
                ORDER BY 
                    FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    cs.start_time ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function checkScheduleConflict($data)
    {
        try {
            // Check teacher conflict
            if (!empty($data['teacher_id'])) {
                $sql = "
                    SELECT id, class_id 
                    FROM class_schedules 
                    WHERE teacher_id = ? 
                      AND day_of_week = ? 
                      AND status = 'active'
                      AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                      )
                ";

                $params = [
                    $data['teacher_id'],
                    $data['day_of_week'],
                    $data['end_time'],
                    $data['start_time'],
                    $data['end_time'],
                    $data['start_time'],
                    $data['start_time'],
                    $data['end_time']
                ];

                if (!empty($data['exclude_id'])) {
                    $sql .= " AND id != ?";
                    $params[] = $data['exclude_id'];
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    return ['type' => 'teacher', 'schedule_id' => $conflict['id']];
                }
            }

            // Check room conflict
            if (!empty($data['room_id'])) {
                $sql = "
                    SELECT id 
                    FROM class_schedules 
                    WHERE room_id = ? 
                      AND day_of_week = ? 
                      AND status = 'active'
                      AND (
                        (start_time < ? AND end_time > ?) OR
                        (start_time < ? AND end_time > ?) OR
                        (start_time >= ? AND end_time <= ?)
                      )
                ";

                $params = [
                    $data['room_id'],
                    $data['day_of_week'],
                    $data['end_time'],
                    $data['start_time'],
                    $data['end_time'],
                    $data['start_time'],
                    $data['start_time'],
                    $data['end_time']
                ];

                if (!empty($data['exclude_id'])) {
                    $sql .= " AND id != ?";
                    $params[] = $data['exclude_id'];
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                if ($conflict = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    return ['type' => 'room', 'schedule_id' => $conflict['id']];
                }
            }

            return null; // No conflict
        } catch (Exception $e) {
            throw $e;
        }
    }

    // ========================================================================
    // TEACHER ASSIGNMENT METHODS
    // ========================================================================

    public function getTeacherSubjects($teacherId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    cu.id as subject_id,
                    cu.name as subject_name,
                    la.id as learning_area_id,
                    la.name as learning_area_name,
                    COUNT(DISTINCT cs.class_id) as class_count
                FROM class_schedules cs
                JOIN curriculum_units cu ON cs.subject_id = cu.id
                JOIN learning_areas la ON cu.learning_area_id = la.id
                WHERE cs.teacher_id = ? AND cs.status = 'active'
                GROUP BY cu.id, la.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($subjects);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSubjectTeachers($subjectId)
    {
        try {
            $sql = "
                SELECT DISTINCT
                    s.id as teacher_id,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    s.email,
                    s.phone,
                    COUNT(DISTINCT cs.class_id) as class_count
                FROM class_schedules cs
                JOIN staff s ON cs.teacher_id = s.id
                WHERE cs.subject_id = ? AND cs.status = 'active'
                GROUP BY s.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$subjectId]);
            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($teachers);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ========================================================================
    // CLASS STREAMS METHODS
    // ========================================================================

    public function listClassStreams($classId)
    {
        try {
            $sql = "
                SELECT 
                    cs.*,
                    c.name as class_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM class_streams cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN staff s ON cs.teacher_id = s.id
                WHERE cs.class_id = ? AND cs.status = 'active'
                ORDER BY cs.stream_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$classId]);
            $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($streams);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignClassTeacher($streamId, $teacherId)
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE class_streams SET teacher_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$teacherId, $streamId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return errorResponse('Class stream not found');
            }

            $this->db->commit();
            $this->logAction('update', $streamId, "Assigned teacher to class stream");

            return successResponse([
                'status' => 'success',
                'message' => 'Teacher assigned successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getTeacherClasses($teacherId)
    {
        try {
            // Get from class_streams (class teacher)
            $sql1 = "
                SELECT DISTINCT
                    c.id as class_id,
                    c.name as class_name,
                    cs.stream_name,
                    'class_teacher' as role
                FROM class_streams cs
                JOIN classes c ON cs.class_id = c.id
                WHERE cs.teacher_id = ? AND cs.status = 'active'
            ";

            $stmt = $this->db->prepare($sql1);
            $stmt->execute([$teacherId]);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get from class_schedules (subject teacher)
            $sql2 = "
                SELECT DISTINCT
                    c.id as class_id,
                    c.name as class_name,
                    NULL as stream_name,
                    'subject_teacher' as role
                FROM class_schedules csch
                JOIN classes c ON csch.class_id = c.id
                WHERE csch.teacher_id = ? AND csch.status = 'active'
            ";

            $stmt = $this->db->prepare($sql2);
            $stmt->execute([$teacherId]);
            $subjectClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Merge results
            $allClasses = array_merge($classes, $subjectClasses);

            return successResponse($allClasses);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== CLASS MANAGEMENT ====================
    // 
    // IMPORTANT: This section leverages several database features:
    //
    // TRIGGERS:
    // - `trg_auto_create_default_stream`: Auto-creates default stream when class is created
    // - `trg_manage_default_stream_on_insert`: Auto-deactivates default stream when custom streams are added
    // - `trg_manage_default_stream_on_delete`: Auto-reactivates default stream when all custom streams are removed
    // - `trg_validate_class_capacity`: Validates that stream capacity is not exceeded when adding students
    //
    // VIEWS:
    // - `vw_active_students_per_class`: Aggregates active student counts per class/stream
    // - `vw_upcoming_class_schedules`: Shows upcoming class schedules (timetable)
    //
    // STORED PROCEDURES (available but not used in basic CRUD):
    // - `sp_generate_student_report`: Comprehensive student report generation
    //
    // EVENTS:
    // - Events emitted to `system_events` table for frontend real-time updates
    //
    // ==========================================================================

    /**
     * List all classes with optional filtering
     * Uses view `vw_active_students_per_class` for student counts
     */
    public function listClasses($params = [])
    {
        try {
            $page = $params['page'] ?? 1;
            $limit = $params['limit'] ?? 20;
            $offset = ($page - 1) * $limit;

            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['level_id'])) {
                $where[] = 'c.level_id = ?';
                $bindings[] = $params['level_id'];
            }

            if (!empty($params['academic_year'])) {
                $where[] = 'c.academic_year = ?';
                $bindings[] = $params['academic_year'];
            }

            if (!empty($params['status'])) {
                $where[] = 'c.status = ?';
                $bindings[] = $params['status'];
            } else {
                $where[] = "c.status = 'active'";
            }

            $whereClause = implode(' AND ', $where);

            // Use view for student counts instead of manual aggregation
            $sql = "
                SELECT 
                    c.*,
                    sl.name as level_name,
                    sl.code as level_code,
                    CONCAT(s.first_name, ' ', s.last_name) as class_teacher_name,
                    r.name as room_name,
                    COUNT(DISTINCT cs.id) as stream_count,
                    COALESCE(SUM(vsc.active_students), 0) as student_count
                FROM classes c
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN staff s ON c.teacher_id = s.id
                LEFT JOIN rooms r ON c.room_number = r.code
                LEFT JOIN class_streams cs ON c.id = cs.class_id AND cs.status = 'active'
                LEFT JOIN vw_active_students_per_class vsc ON c.id = vsc.class_id
                WHERE {$whereClause}
                GROUP BY c.id
                ORDER BY c.academic_year DESC, sl.code, c.name
                LIMIT ? OFFSET ?
            ";

            $bindings[] = $limit;
            $bindings[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(DISTINCT c.id) as total FROM classes c WHERE {$whereClause}";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute(array_slice($bindings, 0, count($bindings) - 2));
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return successResponse([
                'status' => 'success',
                'data' => $classes,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => (int) $page,
                    'limit' => (int) $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get single class with detailed information
     */
    public function getClass($id)
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    sl.name as level_name,
                    sl.code as level_code,
                    CONCAT(s.first_name, ' ', s.last_name) as class_teacher_name,
                    s.email as class_teacher_email,
                    s.phone as class_teacher_phone
                FROM classes c
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN staff s ON c.teacher_id = s.id
                WHERE c.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Get streams
            $class['streams'] = $this->listClassStreams($id)['data'] ?? [];

            // Get student count
            $countSql = "SELECT COUNT(*) as total FROM students WHERE class_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute([$id]);
            $class['student_count'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return successResponse($class);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new class
     * NOTE: Database trigger `trg_auto_create_default_stream` automatically creates a default stream
     */
    public function createClass($data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['name', 'level_id', 'academic_year'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: {$field}");
                }
            }

            // Check for duplicate class name in the same academic year
            $checkSql = "SELECT id FROM classes WHERE name = ? AND academic_year = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$data['name'], $data['academic_year']]);
            if ($stmt->fetch()) {
                throw new Exception("Class '{$data['name']}' already exists for academic year {$data['academic_year']}");
            }

            // Set defaults
            $capacity = $data['capacity'] ?? 40;
            $status = $data['status'] ?? 'active';

            // Create the class - trigger will auto-create default stream
            $sql = "
                INSERT INTO classes (
                    name, level_id, teacher_id, capacity, 
                    room_number, academic_year, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['level_id'],
                $data['teacher_id'] ?? null,
                $capacity,
                $data['room_number'] ?? null,
                $data['academic_year'],
                $status
            ]);

            $classId = $this->db->lastInsertId();

            // If custom streams are specified, create them
            // Trigger `trg_manage_default_stream_on_insert` will auto-deactivate default stream
            if (!empty($data['streams'])) {
                foreach ($data['streams'] as $stream) {
                    $streamSql = "
                        INSERT INTO class_streams (class_id, stream_name, capacity, teacher_id, status)
                        VALUES (?, ?, ?, ?, 'active')
                    ";
                    $stmt = $this->db->prepare($streamSql);
                    $stmt->execute([
                        $classId,
                        $stream['name'],
                        $stream['capacity'],
                        $stream['teacher_id'] ?? null
                    ]);
                }
            }

            $this->db->commit();

            $this->logAction('class_created', "Class created: {$data['name']} for {$data['academic_year']}", [
                'class_id' => $classId
            ]);

            // Emit event for frontend updates
            $this->emitEvent('class_created', [
                'class_id' => $classId,
                'class_name' => $data['name'],
                'academic_year' => $data['academic_year']
            ]);

            return successResponse([
                'status' => 'success',
                'message' => 'Class created successfully',
                'data' => ['id' => $classId]
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Update class details
     */
    public function updateClass($id, $data)
    {
        try {
            // Check if class exists
            $checkSql = "SELECT id FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Class not found');
            }

            $allowedFields = ['name', 'level_id', 'teacher_id', 'capacity', 'room_number', 'status'];
            $updates = [];
            $bindings = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $bindings[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return errorResponse('No fields to update');
            }

            $bindings[] = $id;
            $sql = "UPDATE classes SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $this->logAction('class_updated', "Class ID {$id} updated", ['class_id' => $id]);

            return successResponse(null, 'Class updated successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete a class (soft delete by setting status to inactive)
     */
    public function deleteClass($id)
    {
        try {
            // Check if class exists
            $checkSql = "SELECT name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$id]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Check if class has active students
            $studentCheckSql = "SELECT COUNT(*) as count FROM students WHERE class_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($studentCheckSql);
            $stmt->execute([$id]);
            $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($studentCount > 0) {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Cannot delete class with {$studentCount} active students. Please transfer students first."
                ], 400);
            }

            // Soft delete
            $sql = "UPDATE classes SET status = 'inactive' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $this->logAction('class_deleted', "Class deleted: {$class['name']}", ['class_id' => $id]);

            return successResponse(null, 'Class deleted successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Assign room to a class
     */
    public function assignRoom($classId, $roomId)
    {
        try {
            // Verify class exists
            $classSql = "SELECT id, name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Verify room exists and is available
            $roomSql = "SELECT id, name, code, capacity, status FROM rooms WHERE id = ?";
            $stmt = $this->db->prepare($roomSql);
            $stmt->execute([$roomId]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                return errorResponse('Room not found');
            }

            if ($room['status'] !== 'active') {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Room {$room['name']} is currently {$room['status']} and cannot be assigned"
                ], 400);
            }

            // Check if room is already assigned to another active class
            $assignedSql = "SELECT name FROM classes WHERE room_number = ? AND status = 'active' AND id != ?";
            $stmt = $this->db->prepare($assignedSql);
            $stmt->execute([$room['code'], $classId]);
            $assignedClass = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($assignedClass) {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Room {$room['name']} is already assigned to {$assignedClass['name']}"
                ], 400);
            }

            // Assign room
            $updateSql = "UPDATE classes SET room_number = ? WHERE id = ?";
            $stmt = $this->db->prepare($updateSql);
            $stmt->execute([$room['code'], $classId]);

            $this->logAction('room_assigned', "Room {$room['name']} assigned to class {$class['name']}", [
                'class_id' => $classId,
                'room_id' => $roomId
            ]);

            return successResponse([
                'status' => 'success',
                'message' => "Room {$room['name']} assigned to class {$class['name']} successfully",
                'data' => [
                    'class_id' => $classId,
                    'room_code' => $room['code'],
                    'room_name' => $room['name']
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Auto-create streams based on student count
     * Creates streams dynamically when students exceed class capacity
     * NOTE: Trigger `trg_manage_default_stream_on_insert` automatically manages default stream status
     */
    public function autoCreateStreams($classId, $studentCount = null)
    {
        try {
            $this->db->beginTransaction();

            // Get class details
            $classSql = "SELECT name, capacity FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                throw new Exception('Class not found');
            }

            // Get current student count from view if not provided
            if ($studentCount === null) {
                $countSql = "
                    SELECT COALESCE(SUM(active_students), 0) as total 
                    FROM vw_active_students_per_class 
                    WHERE class_id = ?
                ";
                $stmt = $this->db->prepare($countSql);
                $stmt->execute([$classId]);
                $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            }

            $classCapacity = $class['capacity'];

            // Determine number of streams needed
            if ($studentCount <= $classCapacity) {
                $this->db->commit();
                return errorResponse([
                    'status' => 'success',
                    'message' => 'Single stream is sufficient for current student count',
                    'data' => ['streams_created' => 0, 'student_count' => $studentCount]
                ]);
            }

            // Calculate number of streams needed
            $streamsNeeded = ceil($studentCount / $classCapacity);

            // Get existing active streams
            $existingSql = "SELECT COUNT(*) as count FROM class_streams WHERE class_id = ? AND status = 'active'";
            $stmt = $this->db->prepare($existingSql);
            $stmt->execute([$classId]);
            $existingCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $streamsToCreate = max(0, $streamsNeeded - $existingCount);

            if ($streamsToCreate > 0) {
                $streamNames = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
                $createdCount = 0;

                for ($i = $existingCount; $i < $streamsNeeded && $i < count($streamNames); $i++) {
                    $streamName = $streamNames[$i];

                    // Check if stream already exists
                    $checkSql = "SELECT id FROM class_streams WHERE class_id = ? AND stream_name = ?";
                    $stmt = $this->db->prepare($checkSql);
                    $stmt->execute([$classId, $streamName]);

                    if (!$stmt->fetch()) {
                        // Insert - trigger will manage default stream deactivation
                        $insertSql = "INSERT INTO class_streams (class_id, stream_name, capacity, status) VALUES (?, ?, ?, 'active')";
                        $stmt = $this->db->prepare($insertSql);
                        $stmt->execute([$classId, $streamName, $classCapacity]);
                        $createdCount++;
                    }
                }

                $this->db->commit();

                $this->logAction('streams_auto_created', "Auto-created {$createdCount} streams for class {$class['name']}", [
                    'class_id' => $classId,
                    'streams_created' => $createdCount,
                    'student_count' => $studentCount
                ]);

                // Emit event for frontend updates
                $this->emitEvent('streams_created', [
                    'class_id' => $classId,
                    'streams_created' => $createdCount,
                    'total_streams' => $streamsNeeded
                ]);

                return errorResponse([
                    'status' => 'success',
                    'message' => "{$createdCount} stream(s) created to accommodate {$studentCount} students",
                    'data' => [
                        'streams_created' => $createdCount,
                        'total_streams' => $streamsNeeded,
                        'student_count' => $studentCount
                    ]
                ]);
            }

            $this->db->commit();
            return successResponse([
                'status' => 'success',
                'message' => 'Sufficient streams already exist',
                'data' => ['streams_created' => 0, 'existing_streams' => $existingCount]
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Create a new stream for a class
     * NOTE: Trigger `trg_manage_default_stream_on_insert` will auto-deactivate default stream
     * NOTE: Trigger `trg_validate_class_capacity` validates capacity constraints
     */
    public function createStream($classId, $data)
    {
        try {
            // Verify class exists
            $classSql = "SELECT id, name FROM classes WHERE id = ?";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classId]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return errorResponse('Class not found');
            }

            // Validate required fields
            if (empty($data['stream_name'])) {
                return errorResponse('Stream name is required');
            }

            if (empty($data['capacity'])) {
                return errorResponse('Capacity is required');
            }

            // Check for duplicate stream name
            $checkSql = "SELECT id FROM class_streams WHERE class_id = ? AND stream_name = ?";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([$classId, $data['stream_name']]);
            if ($stmt->fetch()) {
                return errorResponse([
                    'status' => 'error',
                    'message' => "Stream '{$data['stream_name']}' already exists for this class"
                ], 400);
            }

            // Create stream - triggers will handle capacity validation and default stream management
            $sql = "INSERT INTO class_streams (class_id, stream_name, capacity, teacher_id, status) VALUES (?, ?, ?, ?, 'active')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $classId,
                $data['stream_name'],
                $data['capacity'],
                $data['teacher_id'] ?? null
            ]);

            $streamId = $this->db->lastInsertId();

            $this->logAction('stream_created', "Stream {$data['stream_name']} created for class {$class['name']}", [
                'class_id' => $classId,
                'stream_id' => $streamId
            ]);

            // Emit event for frontend updates
            $this->emitEvent('stream_created', [
                'class_id' => $classId,
                'stream_id' => $streamId,
                'stream_name' => $data['stream_name']
            ]);

            return successResponse([
                'status' => 'success',
                'message' => 'Stream created successfully',
                'data' => ['id' => $streamId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}

