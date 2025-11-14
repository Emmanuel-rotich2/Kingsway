<?php

namespace App\API\Controllers;

use App\API\Modules\Academic\AcademicAPI;

/**
 * AcademicController - Explicit REST endpoints for Academic Management
 * 
 * Every method in AcademicAPI has its own unique, explicit endpoint
 */
class AcademicController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AcademicAPI();
    }

    // ==================== BASE CRUD OPERATIONS ====================
    // Router calls methods with: methodName($id, $data, $segments)

    /**
     * GET /api/academic - List all academic records
     * Called as: get(null, $data, [])
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            // GET /api/academic/{id} - Get specific record
            $result = $this->api->get($id, $data);
        } else {
            // GET /api/academic - List all records
            $result = $this->api->list($data);
        }
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic - Create new academic record
     * Called as: post(null, $data, [])
     */
    public function post($id = null, $data = [], $segments = [])
    {
        // Merge id into data if provided in URL
        if ($id !== null) {
            $data['id'] = $id;
        }

        // Check for nested resource routing
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }

        // Default: create new record
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/{id} - Update academic record
     * Called as: put($id, $data, [])
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required for update operation');
        }

        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/{id} - Delete academic record
     * Called as: delete($id, $data, [])
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required for delete operation');
        }

        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ==================== HELPER METHODS ====================

    /**
     * Route nested POST requests to specific workflow methods
     * Example: POST /api/academic/exams/start-workflow
     * Called with: routeNestedPost('exams', null, $data, ['start-workflow'])
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        // Convert kebab-case to camelCase for method lookup
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        // Build method name: post + Resource + Action
        // Example: 'exams' + 'startWorkflow' = 'postExamsStartWorkflow'
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        // Check if method exists
        if (method_exists($this, $methodName)) {
            // Merge ID into data if provided
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to specific methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case or snake_case to camelCase
     * Examples: 'start-workflow' -> 'startWorkflow', 'user_profile' -> 'userProfile'
     */
    private function toCamelCase($string)
    {
        // Replace both - and _ with spaces, then ucwords, then remove spaces
        $string = str_replace(['-', '_'], ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }
    // ==================== EXAMINATION WORKFLOW ====================
    // URLs: POST /api/academic/exams/start-workflow
    //       POST /api/academic/exams/create-schedule
    // Router calls: postExams($id, $data, ['start-workflow'])

    /**
     * POST /api/academic/exams/start-workflow - Start examination workflow
     * Called as: postExamsStartWorkflow(null, $data, [])
     */
    public function postExamsStartWorkflow($id = null, $data = [], $segments = [])
    {
        $result = $this->api->startExaminationWorkflow(
            $data['instance_id'] ?? null,
            $data['term_id'] ?? null,
            $data['exam_type'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/create-schedule - Create exam schedule
     */
    public function postExamsCreateSchedule($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createExamSchedule(
            $data['instance_id'] ?? null,
            $data['schedule_entries'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/submit-questions - Submit question papers
     */
    public function postExamsSubmitQuestions($id = null, $data = [], $segments = [])
    {
        $result = $this->api->submitQuestionPaper(
            $data['instance_id'] ?? null,
            $data['subject_id'] ?? null,
            $data['paper_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/prepare-logistics - Prepare exam logistics
     */
    public function postExamsPrepareLogistics($id = null, $data = [], $segments = [])
    {
        $result = $this->api->prepareExamLogistics($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/conduct - Conduct examination
     */
    public function postExamsConduct($id = null, $data = [], $segments = [])
    {
        $result = $this->api->conductExamination($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/assign-marking - Assign exam marking
     */
    public function postExamsAssignMarking($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignExamMarking(
            $data['instance_id'] ?? null,
            $data['assignments'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/record-marks - Record exam marks
     */
    public function postExamsRecordMarks($id = null, $data = [], $segments = [])
    {
        $result = $this->api->recordExamMarks(
            $data['instance_id'] ?? null,
            $data['marks_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/verify-marks - Verify exam marks
     */
    public function postExamsVerifyMarks($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyExamMarks($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/moderate-marks - Moderate exam marks
     */
    public function postExamsModerateMarks($id = null, $data = [], $segments = [])
    {
        $result = $this->api->moderateExamMarks(
            $data['instance_id'] ?? null,
            $data['moderation_data'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/compile-results - Compile exam results
     */
    public function postExamsCompileResults($id = null, $data = [], $segments = [])
    {
        $result = $this->api->compileExamResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/approve-results - Approve exam results
     */
    public function postExamsApproveResults($id = null, $data = [], $segments = [])
    {
        $result = $this->api->approveExamResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== PROMOTION WORKFLOW ====================

    /**
     * POST /api/academic/promotions/start-workflow - Start promotion workflow
     */
    public function postPromotionsStartWorkflow($data = [])
    {
        $result = $this->api->startPromotionWorkflow($data['instance_id'] ?? null, $data['from_year'] ?? null, $data['to_year'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/identify-candidates - Identify promotion candidates
     */
    public function postPromotionsIdentifyCandidates($data = [])
    {
        $result = $this->api->identifyPromotionCandidates($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/validate-eligibility - Validate promotion eligibility
     */
    public function postPromotionsValidateEligibility($data = [])
    {
        $result = $this->api->validatePromotionEligibility($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/execute - Execute promotions
     */
    public function postPromotionsExecute($data = [])
    {
        $result = $this->api->executePromotions($data['instance_id'] ?? null, $data['promotion_data'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/generate-reports - Generate promotion reports
     */
    public function postPromotionsGenerateReports($data = [])
    {
        $result = $this->api->generatePromotionReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ASSESSMENT WORKFLOW ====================

    /**
     * POST /api/academic/assessments/start-workflow - Start assessment workflow
     */
    public function postAssessmentsStartWorkflow($data = [])
    {
        $result = $this->api->startAssessmentWorkflow($data['instance_id'] ?? null, $data['assessment_type'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/create-items - Create assessment items
     */
    public function postAssessmentsCreateItems($data = [])
    {
        $result = $this->api->createAssessmentItems($data['instance_id'] ?? null, $data['items'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/administer - Administer assessment
     */
    public function postAssessmentsAdminister($data = [])
    {
        $result = $this->api->administerAssessment($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/mark-and-grade - Mark and grade assessment
     */
    public function postAssessmentsMarkAndGrade($data = [])
    {
        $result = $this->api->markAndGradeAssessment($data['instance_id'] ?? null, $data['grading_data'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/analyze-results - Analyze assessment results
     */
    public function postAssessmentsAnalyzeResults($data = [])
    {
        $result = $this->api->analyzeAssessmentResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== REPORT WORKFLOW ====================

    /**
     * POST /api/academic/reports/start-workflow - Start report workflow
     */
    public function postReportsStartWorkflow($data = [])
    {
        $result = $this->api->startReportWorkflow($data['instance_id'] ?? null, $data['term_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/compile-data - Compile report data
     */
    public function postReportsCompileData($data = [])
    {
        $result = $this->api->compileReportData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/generate-student-reports - Generate student reports
     */
    public function postReportsGenerateStudentReports($data = [])
    {
        $result = $this->api->generateStudentReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/review-and-approve - Review and approve reports
     */
    public function postReportsReviewAndApprove($data = [])
    {
        $result = $this->api->reviewAndApproveReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/distribute - Distribute reports
     */
    public function postReportsDistribute($data = [])
    {
        $result = $this->api->distributeReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== LIBRARY WORKFLOW ====================

    /**
     * POST /api/academic/library/start-workflow - Start library workflow
     */
    public function postLibraryStartWorkflow($data = [])
    {
        $result = $this->api->startLibraryWorkflow($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/review-request - Review library request
     */
    public function postLibraryReviewRequest($data = [])
    {
        $result = $this->api->reviewLibraryRequest($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/catalog-resources - Catalog library resources
     */
    public function postLibraryCatalogResources($data = [])
    {
        $result = $this->api->catalogLibraryResources($data['instance_id'] ?? null, $data['resources'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/distribute-and-track - Distribute and track resources
     */
    public function postLibraryDistributeAndTrack($data = [])
    {
        $result = $this->api->distributeAndTrackResources($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM WORKFLOW ====================

    /**
     * POST /api/academic/curriculum/start-workflow - Start curriculum workflow
     */
    public function postCurriculumStartWorkflow($data = [])
    {
        $result = $this->api->startCurriculumWorkflow($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/map-outcomes - Map curriculum outcomes
     */
    public function postCurriculumMapOutcomes($data = [])
    {
        $result = $this->api->mapCurriculumOutcomes($data['instance_id'] ?? null, $data['mappings'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/create-scheme - Create curriculum scheme
     */
    public function postCurriculumCreateScheme($data = [])
    {
        $result = $this->api->createCurriculumScheme($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/review-and-approve - Review and approve curriculum
     */
    public function postCurriculumReviewAndApprove($data = [])
    {
        $result = $this->api->reviewAndApproveCurriculum($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== YEAR TRANSITION WORKFLOW ====================

    /**
     * POST /api/academic/year-transition/start-workflow - Start year transition workflow
     */
    public function postYearTransitionStartWorkflow($data = [])
    {
        $result = $this->api->startYearTransitionWorkflow($data['instance_id'] ?? null, $data['from_year'] ?? null, $data['to_year'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/archive-data - Archive academic data
     */
    public function postYearTransitionArchiveData($data = [])
    {
        $result = $this->api->archiveAcademicData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/execute-promotions - Execute year promotions
     */
    public function postYearTransitionExecutePromotions($data = [])
    {
        $result = $this->api->executeYearPromotions($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/setup-new-year - Setup new academic year
     */
    public function postYearTransitionSetupNewYear($data = [])
    {
        $result = $this->api->setupNewAcademicYear($data['instance_id'] ?? null, $data['year_config'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/migrate-competency-baselines - Migrate competency baselines
     */
    public function postYearTransitionMigrateCompetencyBaselines($data = [])
    {
        $result = $this->api->migrateCompetencyBaselines($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/validate-readiness - Validate year readiness
     */
    public function postYearTransitionValidateReadiness($data = [])
    {
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== COMPETENCY & CORE VALUES ====================

    /**
     * POST /api/academic/competency/record-evidence - Record competency evidence
     */
    public function postCompetencyRecordEvidence($data = [])
    {
        $result = $this->api->recordCompetencyEvidence($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/competency/record-core-value-evidence - Record core value evidence
     */
    public function postCompetencyRecordCoreValueEvidence($data = [])
    {
        $result = $this->api->recordCoreValueEvidence($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/competency/dashboard - Get competency dashboard
     */
    public function getCompetencyDashboard($data = [])
    {
        $result = $this->api->getCompetencyDashboard($data['student_id'] ?? null, $data['term_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC TERMS ====================

    /**
     * POST /api/academic/terms/create - Create academic term
     */
    public function postTermsCreate($data = [])
    {
        $result = $this->api->createAcademicTerm($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/terms/list - Get all academic terms
     */
    public function getTermsList($data = [])
    {
        $result = $this->api->getAcademicTerms($data);
        return $this->handleResponse($result);
    }

    // ==================== CLASS MANAGEMENT ====================

    /**
     * POST /api/academic/classes/create - Create class
     */
    public function postClassesCreate($data = [])
    {
        $result = $this->api->createClass($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/list - List all classes
     */
    public function getClassesList($data = [])
    {
        $result = $this->api->listClasses($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/get/{id} - Get specific class
     */
    public function getClassesGet($data = [])
    {
        $result = $this->api->getClass($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/classes/update/{id} - Update class
     */
    public function putClassesUpdate($data = [])
    {
        $result = $this->api->updateClass($data['id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/classes/delete/{id} - Delete class
     */
    public function deleteClassesDelete($data = [])
    {
        $result = $this->api->deleteClass($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/assign-teacher - Assign class teacher
     */
    public function postClassesAssignTeacher($data = [])
    {
        $result = $this->api->assignClassTeacher($data['class_id'] ?? null, $data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/auto-create-streams - Auto-create class streams
     */
    public function postClassesAutoCreateStreams($data = [])
    {
        $result = $this->api->autoCreateStreams($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CLASS STREAMS ====================

    /**
     * POST /api/academic/streams/create - Create stream
     */
    public function postStreamsCreate($data = [])
    {
        $result = $this->api->createStream($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/streams/list - List class streams
     */
    public function getStreamsList($data = [])
    {
        $result = $this->api->listClassStreams($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CLASS SCHEDULES ====================

    /**
     * POST /api/academic/schedules/create - Create class schedule
     */
    public function postSchedulesCreate($data = [])
    {
        $result = $this->api->createClassSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/list - List class schedules
     */
    public function getSchedulesList($data = [])
    {
        $result = $this->api->listClassSchedules($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/get/{id} - Get specific schedule
     */
    public function getSchedulesGet($data = [])
    {
        $result = $this->api->getClassSchedule($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/schedules/update/{id} - Update schedule
     */
    public function putSchedulesUpdate($data = [])
    {
        $result = $this->api->updateClassSchedule($data['id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/schedules/delete/{id} - Delete schedule
     */
    public function deleteSchedulesDelete($data = [])
    {
        $result = $this->api->deleteClassSchedule($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/schedules/assign-room - Assign room to schedule
     */
    public function postSchedulesAssignRoom($data = [])
    {
        $result = $this->api->assignRoom($data['schedule_id'] ?? null, $data['room_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM UNITS ====================

    /**
     * POST /api/academic/curriculum-units/create - Create curriculum unit
     */
    public function postCurriculumUnitsCreate($data = [])
    {
        $result = $this->api->createCurriculumUnit($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/curriculum-units/list - List curriculum units
     */
    public function getCurriculumUnitsList($data = [])
    {
        $result = $this->api->getCurriculumUnits($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/curriculum-units/get/{id} - Get specific curriculum unit
     */
    public function getCurriculumUnitsGet($data = [])
    {
        $result = $this->api->getCurriculumUnit($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/curriculum-units/update/{id} - Update curriculum unit
     */
    public function putCurriculumUnitsUpdate($data = [])
    {
        $result = $this->api->updateCurriculumUnit($data['id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/curriculum-units/delete/{id} - Delete curriculum unit
     */
    public function deleteCurriculumUnitsDelete($data = [])
    {
        $result = $this->api->deleteCurriculumUnit($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== UNIT TOPICS ====================

    /**
     * POST /api/academic/topics/create - Create unit topic
     */
    public function postTopicsCreate($data = [])
    {
        $result = $this->api->createUnitTopic($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/list - List unit topics
     */
    public function getTopicsList($data = [])
    {
        $result = $this->api->listUnitTopics($data['unit_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/get/{id} - Get specific topic
     */
    public function getTopicsGet($data = [])
    {
        $result = $this->api->getUnitTopic($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/topics/update/{id} - Update topic
     */
    public function putTopicsUpdate($data = [])
    {
        $result = $this->api->updateUnitTopic($data['id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/topics/delete/{id} - Delete topic
     */
    public function deleteTopicsDelete($data = [])
    {
        $result = $this->api->deleteUnitTopic($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== LESSON PLANS ====================

    /**
     * POST /api/academic/lesson-plans/create - Create lesson plan
     */
    public function postLessonPlansCreate($data = [])
    {
        $result = $this->api->createLessonPlan($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/list - List lesson plans
     */
    public function getLessonPlansList($data = [])
    {
        $result = $this->api->getLessonPlans($data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/get/{id} - Get specific lesson plan
     */
    public function getLessonPlansGet($data = [])
    {
        $result = $this->api->getLessonPlan($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/update/{id} - Update lesson plan
     */
    public function putLessonPlansUpdate($data = [])
    {
        $result = $this->api->updateLessonPlan($data['id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/lesson-plans/delete/{id} - Delete lesson plan
     */
    public function deleteLessonPlansDelete($data = [])
    {
        $result = $this->api->deleteLessonPlan($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/approve - Approve lesson plan
     */
    public function postLessonPlansApprove($data = [])
    {
        $result = $this->api->approveLessonPlan($data['plan_id'] ?? null, $data['approved_by'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== LESSON OBSERVATIONS ====================

    /**
     * POST /api/academic/lesson-observations/create - Create lesson observation
     */
    public function postLessonObservationsCreate($data = [])
    {
        $result = $this->api->createLessonObservation($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-observations/list - List lesson observations
     */
    public function getLessonObservationsList($data = [])
    {
        $result = $this->api->getLessonObservations($data['filters'] ?? []);
        return $this->handleResponse($result);
    }

    // ==================== SCHEME OF WORK ====================

    /**
     * POST /api/academic/scheme-of-work/create - Create scheme of work
     */
    public function postSchemeOfWorkCreate($data = [])
    {
        $result = $this->api->createSchemeOfWork($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/scheme-of-work/get/{id} - Get scheme of work
     */
    public function getSchemeOfWorkGet($data = [])
    {
        $result = $this->api->getSchemeOfWork($data['id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== TEACHER OPERATIONS ====================

    /**
     * GET /api/academic/teachers/classes - Get teacher's classes
     */
    public function getTeachersClasses($data = [])
    {
        $result = $this->api->getTeacherClasses($data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/subjects - Get teacher's subjects
     */
    public function getTeachersSubjects($data = [])
    {
        $result = $this->api->getTeacherSubjects($data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/schedule - Get teacher's schedule
     */
    public function getTeachersSchedule($data = [])
    {
        $result = $this->api->getTeacherSchedule($data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/subjects/teachers - Get subject teachers
     */
    public function getSubjectsTeachers($data = [])
    {
        $result = $this->api->getSubjectTeachers($data['subject_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== WORKFLOW STATUS ====================

    /**
     * GET /api/academic/workflow/status - Get workflow status
     */
    public function getWorkflowStatus($data = [])
    {
        $result = $this->api->getWorkflowStatus($data['instance_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CUSTOM OPERATIONS ====================

    /**
     * GET /api/academic/custom - Handle custom GET operations
     */
    public function getCustom($data = [])
    {
        $result = $this->api->handleCustomGet($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/custom - Handle custom POST operations
     */
    public function postCustom($data = [])
    {
        $result = $this->api->handleCustomPost($data);
        return $this->handleResponse($result);
    }
}
