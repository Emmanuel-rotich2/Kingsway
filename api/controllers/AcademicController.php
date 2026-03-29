<?php

namespace App\API\Controllers;

use App\API\Modules\academic\AcademicAPI;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;

/**
 * AcademicController
 *
 * Explicit REST endpoints for Academic Management. This controller exposes a
 * large collection of explicit methods that map to academic workflows and
 * CRUD operations via a consistent routing convention. It delegates business
 * logic to App\API\Modules\academic\AcademicAPI and adapts HTTP-style calls
 * into API method calls.
 *
 * Routing & Method Conventions
 * - Base CRUD endpoints:
 *     - index()                          -> GET  /api/academic
 *     - get($id = null, $data = [], ...) -> GET  /api/academic         (list)
 *     - get($id, ...)                    -> GET  /api/academic/{id}    (retrieve)
 *     - post($id = null, $data = [], ...) -> POST /api/academic        (create)
 *     - put($id, $data, ...)             -> PUT  /api/academic/{id}   (update)
 *     - delete($id, $data, ...)          -> DELETE /api/academic/{id} (delete)
 *
 * - Router call signature used by controller methods:
 *     methodName($id, $data, $segments)
 *   where:
 *     - $id       : optional resource id (from URL segment)
 *     - $data     : associative array of request payload / query params
 *     - $segments : remaining URL segments for nested routing (array)
 *
 * Nested routing & naming
 * - Nested POST/GET requests are routed through routeNestedPost / routeNestedGet.
 * - URL segments are converted from kebab-case or snake_case to camelCase
 *   using toCamelCase().
 * - Controller method names follow the pattern:
 *     <httpVerb><Resource><Action>
 *   Examples:
 *     - POST /api/academic/exams/start-workflow  -> postExamsStartWorkflow(...)
 *     - POST /api/academic/promotions/execute    -> postPromotionsExecute(...)
 * - When an $id is present in the URL it is merged into $data['id'] before
 *   invoking the target method.
 *
 * Data & common parameters
 * - Many workflow endpoints expect (or optionally accept) common keys in $data:
 *     - instance_id    : academic instance / school context
 *     - term_id        : academic term identifier
 *     - exam_type      : type/category of exam
 *     - schedule_entries, schedule_entries[] etc.
 *     - subject_id, student_id, competency_id, core_value_id
 *     - assignments, marks_data, moderation_data, grading_data
 *     - promotion_data, year_config, resources, mappings, items
 *     - filters, params, action (for custom endpoints)
 *
 * Example grouped workflows (representative)
 * - Examination workflow: startExaminationWorkflow, createExamSchedule,
 *   submitQuestionPaper, prepareExamLogistics, conductExamination,
 *   assignExamMarking, recordExamMarks, verifyExamMarks, moderateExamMarks,
 *   compileExamResults, approveExamResults.
 *
 * - Promotion workflow: startPromotionWorkflow, identifyPromotionCandidates,
 *   validatePromotionEligibility, executePromotions, generatePromotionReports.
 *
 * - Assessment workflow: startAssessmentWorkflow, createAssessmentItems,
 *   administerAssessment, markAndGradeAssessment, analyzeAssessmentResults.
 *
 * - Reporting, Library, Curriculum, Year Transition and other domain-specific
 *   workflows follow similar naming and usage patterns.
 *
 * Response handling
 * - handleResponse($result) normalizes API return values and maps them to the
 *   controller's success/badRequest responses:
 *     - If $result is an array and contains 'success' (boolean), it is used to
 *       determine success vs failure; 'data' and 'message' fields are honored.
 *     - If $result is an array and contains 'status' ('success'/'error'), it
 *       is similarly honored.
 *     - If $result is a plain array (without the above keys) it is returned as
 *       success payload.
 *     - Non-array results are wrapped as ['result' => $result].
 *
 * Error handling & validation notes
 * - put() and delete() require an $id; otherwise a badRequest response is
 *   returned.
 * - routeNestedPost / routeNestedGet return notFound() when the computed
 *   controller method does not exist.
 *
 * BaseController integration
 * - This controller relies on BaseController helper methods for HTTP responses:
 *     - success($data, $message = null)
 *     - badRequest($message = null, $data = [])
 *     - notFound($message = null)
 *
 * Extension points
 * - Add new workflow endpoints by:
 *     1) implementing the corresponding method on AcademicAPI, and
 *     2) adding a controller wrapper method following the <verb><Resource><Action>
 *        naming convention or letting nested routing invoke it.
 *
 * Notes
 * - This docblock summarizes the controller's routing and expected payload
 *   conventions; refer to specific endpoint method docblocks (or AcademicAPI)
 *   for more precise parameter contracts and return schemas for each action.
 */

class AcademicController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AcademicAPI();
    }

    public function index()
    {
        // GET /api/academic/index - API health/info endpoint
        return $this->success([
            'message' => 'Academic API is running',
            'endpoints' => [
                'list' => '/api/academic (GET)',
                'create' => '/api/academic (POST)',
                'update' => '/api/academic/{id} (PUT)',
                'delete' => '/api/academic/{id} (DELETE)'
            ],
            'health' => 'ok',
            'timestamp' => date('c')
        ]);
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
            return $this->handleResponse($result);
        } else {
            // GET /api/academic - List all records
            $result = $this->api->list($data);
            return $this->handleResponse($result);
        }
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
                if ($result['status'] === 'success') {
                    return $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful');
                }

                $message = $result['message'] ?? 'Operation failed';
                $data = $result['data'] ?? [];
                $code = (int) ($result['code'] ?? 400);

                if ($code === 401) {
                    return $this->unauthorized($message);
                }
                if ($code === 403) {
                    return $this->forbidden($message);
                }
                if ($code === 404) {
                    return $this->notFound($message);
                }
                if ($code >= 500) {
                    return $this->serverError($message, $data);
                }

                return $this->badRequest($message, is_array($data) ? $data : []);
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
        $result = $this->api->startExaminationWorkflow($data);
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

    // ==================== EXAM SCHEDULE DIRECT CRUD ====================
    // URLs: GET/POST/PUT/DELETE /api/academic/exam-schedule
    // Used by: js/pages/exam_schedule.js

    /**
     * GET /api/academic/exam-schedule - List exam schedules with filters
     * GET /api/academic/exam-schedule/{id} - Get single exam schedule
     * Router calls: getExamSchedule($id, $data, $segments)
     */
    public function getExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $result = $this->api->getExamScheduleById($id);
        } else {
            $result = $this->api->listExamSchedules($data);
        }
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exam-schedule - Create new exam schedule
     * Router calls: postExamSchedule(null, $data, $segments)
     */
    public function postExamSchedule($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createExamScheduleEntry($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/exam-schedule/{id} - Update exam schedule
     * Router calls: putExamSchedule($id, $data, $segments)
     */
    public function putExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Exam schedule ID is required for update');
        }
        $result = $this->api->updateExamScheduleEntry($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/exam-schedule/{id} - Delete exam schedule
     * Router calls: deleteExamSchedule($id, $data, $segments)
     */
    public function deleteExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Exam schedule ID is required for deletion');
        }
        $result = $this->api->deleteExamScheduleEntry($id);
        return $this->handleResponse($result);
    }

    // ==================== PROMOTION WORKFLOW ====================

    /**
     * POST /api/academic/promotions/start-workflow - Start promotion workflow
     */
    public function postPromotionsStartWorkflow($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $result = $this->api->startPromotionWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/identify-candidates - Identify promotion candidates
     */
    public function postPromotionsIdentifyCandidates($id = null, $data = [], $segments = [])
    {
        $result = $this->api->identifyPromotionCandidates($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/validate-eligibility - Validate promotion eligibility
     */
    public function postPromotionsValidateEligibility($id = null, $data = [], $segments = [])
    {
        $result = $this->api->validatePromotionEligibility($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/execute - Execute promotions
     */
    public function postPromotionsExecute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->executePromotions($data['instance_id'] ?? null, $data['promotion_data'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/generate-reports - Generate promotion reports
     */
    public function postPromotionsGenerateReports($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generatePromotionReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ASSESSMENT WORKFLOW ====================

    /**
     * POST /api/academic/assessments/start-workflow - Start assessment workflow
     */
    public function postAssessmentsStartWorkflow($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $result = $this->api->startAssessmentWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/create-items - Create assessment items
     */
    public function postAssessmentsCreateItems($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAssessmentItems(
            $data['instance_id'] ?? null,
            $data['items'] ?? $data['assessment_items'] ?? []
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/administer - Record assessment administration
     */
    public function postAssessmentsAdminister($id = null, $data = [], $segments = [])
    {
        $result = $this->api->administerAssessment(
            $data['instance_id'] ?? null,
            $data['administration_data'] ?? $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/mark-and-grade
     * Supports both:
     * - Workflow mode (instance_id + grading_data)
     * - Direct mode (assessment_id + grading_data/marks) fallback
     */
    public function postAssessmentsMarkAndGrade($id = null, $data = [], $segments = [])
    {
        $instanceId = $data['instance_id'] ?? null;
        $assessmentId = $data['assessment_id'] ?? null;
        $gradingData = $data['grading_data'] ?? $data['marks_data'] ?? $data['marks'] ?? [];

        // Prefer direct mode when no workflow instance is provided.
        if (empty($instanceId) && !empty($assessmentId)) {
            $result = $this->api->saveAssessmentResults([
                'assessment_id' => (int) $assessmentId,
                'marks' => $gradingData,
                'is_final' => (bool) ($data['is_final'] ?? true),
                'marked_by' => $data['marked_by'] ?? null,
            ]);
            return $this->handleResponse($result);
        }

        $result = $this->api->markAndGradeAssessment($instanceId, $gradingData, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/analyze-results - Analyze assessment results
     */
    public function postAssessmentsAnalyzeResults($id = null, $data = [], $segments = [])
    {
        $result = $this->api->analyzeAssessmentResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== REPORT WORKFLOW ====================

    /**
     * POST /api/academic/reports/start-workflow - Start report workflow
     */
    public function postReportsStartWorkflow($id = null, $data = [], $segments = [])
    {
        $result = $this->api->startReportWorkflow($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/compile-data - Compile report data
     */
    public function postReportsCompileData($id = null, $data = [], $segments = [])
    {
        $result = $this->api->compileReportData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/generate-student-reports - Generate student reports
     */
    public function postReportsGenerateStudentReports($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateStudentReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/review-and-approve - Review and approve reports
     */
    public function postReportsReviewAndApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewAndApproveReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/distribute - Distribute reports
     */
    public function postReportsDistribute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->distributeReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== LIBRARY WORKFLOW ====================

    /**
     * POST /api/academic/library/start-workflow - Start library workflow
     */
    // (Removed duplicate, correct version already defined above)

    /**
     * POST /api/academic/library/review-request - Review library request
     */
    public function postLibraryReviewRequest($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewLibraryRequest($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/catalog-resources - Catalog library resources
     */
    public function postLibraryCatalogResources($id = null, $data = [], $segments = [])
    {
        $result = $this->api->catalogLibraryResources($data['instance_id'] ?? null, $data['resources'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/distribute-and-track - Distribute and track resources
     */
    public function postLibraryDistributeAndTrack($id = null, $data = [], $segments = [])
    {
        $result = $this->api->distributeAndTrackResources($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM WORKFLOW ====================

    /**
     * POST /api/academic/curriculum/start-workflow - Start curriculum workflow
     */
    public function postCurriculumStartWorkflow($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $result = $this->api->startCurriculumWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/map-outcomes - Map curriculum outcomes
     */
    public function postCurriculumMapOutcomes($id = null, $data = [], $segments = [])
    {
        $result = $this->api->mapCurriculumOutcomes($data['instance_id'] ?? null, $data['mappings'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/create-scheme - Create curriculum scheme
     */
    public function postCurriculumCreateScheme($id = null, $data = [], $segments = [])
    {
        // Assuming createCurriculumScheme expects ($instanceId, $data)
        $result = $this->api->createCurriculumScheme($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/review-and-approve - Review and approve curriculum
     */
    public function postCurriculumReviewAndApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewAndApproveCurriculum($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== YEAR TRANSITION WORKFLOW ====================

    /**
     * POST /api/academic/year-transition/start-workflow - Start year transition workflow
     */
    public function postYearTransitionStartWorkflow($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $result = $this->api->startYearTransitionWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/archive-data - Archive academic data
     */
    public function postYearTransitionArchiveData($id = null, $data = [], $segments = [])
    {
        $result = $this->api->archiveAcademicData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/execute-promotions - Execute year promotions
     */
    public function postYearTransitionExecutePromotions($id = null, $data = [], $segments = [])
    {
        $result = $this->api->executeYearPromotions($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/setup-new-year - Setup new academic year
     */
    public function postYearTransitionSetupNewYear($id = null, $data = [], $segments = [])
    {
        $result = $this->api->setupNewAcademicYear($data['instance_id'] ?? null, $data['year_config'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/migrate-competency-baselines - Migrate competency baselines
     */
    public function postYearTransitionMigrateCompetencyBaselines($id = null, $data = [], $segments = [])
    {
        $result = $this->api->migrateCompetencyBaselines($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/validate-readiness - Validate year readiness
     */
    public function postYearTransitionValidateReadiness($id = null, $data = [], $segments = [])
    {
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== COMPETENCY & CORE VALUES ====================

    /**
     * POST /api/academic/competency/record-evidence - Record competency evidence
     */
    public function postCompetencyRecordEvidence($id = null, $data = [], $segments = [])
    {
        // Assuming recordCompetencyEvidence expects ($studentId, $competencyId, $evidence, $data)
        $result = $this->api->recordCompetencyEvidence(
            $data['student_id'] ?? null,
            $data['competency_id'] ?? null,
            $data['evidence'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/competency/record-core-value-evidence - Record core value evidence
     */
    public function postCompetencyRecordCoreValueEvidence($id = null, $data = [], $segments = [])
    {
        // Assuming recordCoreValueEvidence expects ($studentId, $coreValueId, $data)
        $result = $this->api->recordCoreValueEvidence(
            $data['student_id'] ?? null,
            $data['core_value_id'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/competency/dashboard - Get competency dashboard
     */
    public function getCompetencyDashboard($id = null, $data = [], $segments = [])
    {
        $studentId = isset($data['student_id']) ? $data['student_id'] : null;
        $termId = isset($data['term_id']) ? $data['term_id'] : null;
        $result = $this->api->getCompetencyDashboard($studentId, $termId);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC YEARS ====================

    /**
     * GET /api/academic/years/list - Get all academic years
     */
    public function getYearsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAcademicYears($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/years/current - Get current academic year
     */
    public function getYearsCurrent($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurrentAcademicYear($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/years/create - Create academic year
     */
    public function postYearsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAcademicYear($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/years/update/{id} - Update academic year
     */
    public function putYearsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAcademicYear($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/years/delete/{id} - Delete academic year
     */
    public function deleteYearsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAcademicYear($id);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/years/set-current/{id} - Set year as current
     */
    public function putYearsSetCurrent($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? ($data['id'] ?? null);
        $result = $this->api->setCurrentAcademicYear($yearId);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC TERMS ====================

    /**
     * POST /api/academic/terms/create - Create academic term
     */
    public function postTermsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAcademicTerm($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/terms/list - Get all academic terms
     */
    public function getTermsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAcademicTerms($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/terms/update/{id} - Update academic term
     */
    public function putTermsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAcademicTerm($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/terms/delete/{id} - Delete academic term
     */
    public function deleteTermsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAcademicTerm($id);
        return $this->handleResponse($result);
    }

    // ==================== LEARNING AREAS (SUBJECTS) ====================

    /**
     * GET /api/academic/learning-areas/list - List all learning areas
     */
    public function getLearningAreasList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/learning-areas/get/{id} - Get specific learning area
     */
    public function getLearningAreasGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->get($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/learning-areas/create - Create learning area
     */
    public function postLearningAreasCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/learning-areas/update/{id} - Update learning area
     */
    public function putLearningAreasUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/learning-areas/delete/{id} - Delete learning area
     */
    public function deleteLearningAreasDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ==================== CLASS MANAGEMENT ====================

    /**
     * POST /api/academic/classes/create - Create class
     */
    public function postClassesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createClass($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/list - List all classes
     */
    public function getClassesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listClasses($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/assessments-list - List assessments with submission stats
     */
    public function getAssessmentsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAssessmentsList($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/grading-results - List student grading rows with filters
     */
    public function getGradingResults($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getGradingResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/results-analysis - Aggregate class/subject performance metrics
     */
    public function getResultsAnalysis($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getResultsAnalysis($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/student-results - Get result summary for one student
     */
    public function getStudentResults($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['student_id'] = $id;
        }
        $result = $this->api->getStudentResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/report-cards/download/{student_id}
     * Route compatibility endpoint for report card download payload.
     * Returns normalized student-results data consumable by frontend exporters.
     */
    public function getReportCardsDownload($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['student_id'] = (int) $id;
        }

        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }

        $result = $this->api->getStudentResults($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/classes/get/{id} - Get specific class
     */
    public function getClassesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClass($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/classes/update/{id} - Update class
     */
    public function putClassesUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateClass($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/classes/delete/{id} - Delete class
     */
    public function deleteClassesDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteClass($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/assign-teacher - Assign class teacher
     */
    public function postClassesAssignTeacher($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignClassTeacher($data['class_id'] ?? null, $data['teacher_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/classes/auto-create-streams - Auto-create class streams
     */
    public function postClassesAutoCreateStreams($id = null, $data = [], $segments = [])
    {
        $result = $this->api->autoCreateStreams($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CLASS STREAMS ====================

    /**
     * POST /api/academic/streams/create - Create stream
     */
    public function postStreamsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createStream($data['class_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/streams/list - List class streams
     */
    public function getStreamsList($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? $id ?? null;
        $result = $this->api->listClassStreams($classId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/streams/get/{id} - Get specific stream
     */
    public function getStreamsGet($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->getStream($streamId);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/streams/update/{id} - Update class stream
     */
    public function putStreamsUpdate($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->updateStream($streamId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/streams/delete/{id} - Delete/deactivate stream
     */
    public function deleteStreamsDelete($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        $result = $this->api->deleteStream($streamId);
        return $this->handleResponse($result);
    }

    // ==================== CLASS SCHEDULES ====================

    /**
     * POST /api/academic/schedules/create - Create class schedule
     */
    public function postSchedulesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createClassSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/list - List class schedules
     */
    public function getSchedulesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listClassSchedules($data['class_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/schedules/get/{id} - Get specific schedule
     */
    public function getSchedulesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClassSchedule($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/schedules/update/{id} - Update schedule
     */
    public function putSchedulesUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateClassSchedule($id ?? ($data['id'] ?? null), $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/schedules/delete/{id} - Delete schedule
     */
    public function deleteSchedulesDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteClassSchedule($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/schedules/assign-room - Assign room to schedule
     */
    public function postSchedulesAssignRoom($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignRoom($data['schedule_id'] ?? null, $data['room_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM UNITS ====================

    /**
     * POST /api/academic/curriculum-units/create - Create curriculum unit
     */
    public function postCurriculumUnitsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createCurriculumUnit($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/curriculum-units/list - List curriculum units
     */
    public function getCurriculumUnitsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurriculumUnits($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/curriculum-units/get/{id} - Get specific curriculum unit
     */
    public function getCurriculumUnitsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurriculumUnit($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/curriculum-units/update/{id} - Update curriculum unit
     */
    public function putCurriculumUnitsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateCurriculumUnit($id ?? ($data['id'] ?? null), $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/curriculum-units/delete/{id} - Delete curriculum unit
     */
    public function deleteCurriculumUnitsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteCurriculumUnit($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    // ==================== UNIT TOPICS ====================

    /**
     * POST /api/academic/topics/create - Create unit topic
     */
    public function postTopicsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createUnitTopic($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/list - List unit topics
     */
    public function getTopicsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listUnitTopics($data['unit_id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/topics/get/{id} - Get specific topic
     */
    public function getTopicsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getUnitTopic($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/topics/update/{id} - Update topic
     */
    public function putTopicsUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateUnitTopic($id ?? ($data['id'] ?? null), $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/topics/delete/{id} - Delete topic
     */
    public function deleteTopicsDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteUnitTopic($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    // ==================== LESSON PLANS ====================

    /**
     * POST /api/academic/lesson-plans/create - Create lesson plan
     */
    public function postLessonPlansCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createLessonPlan($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/list - List lesson plans
     * Passes full query params so getLessonPlans can filter by
     * teacher_id, class_id, status, term_id, academic_year_id, etc.
     */
    public function getLessonPlansList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonPlans($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/get/{id} - Get specific lesson plan
     */
    public function getLessonPlansGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonPlan($id ?? $data['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/update/{id} - Update lesson plan
     */
    public function putLessonPlansUpdate($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['id'] ?? null;
        $result = $this->api->updateLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/lesson-plans/delete/{id} - Delete lesson plan
     */
    public function deleteLessonPlansDelete($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['id'] ?? null;
        $result = $this->api->deleteLessonPlan($planId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/approve - Approve lesson plan
     */
    public function postLessonPlansApprove($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->approveLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/reject - Reject lesson plan
     */
    public function postLessonPlansReject($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->rejectLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/lesson-plans/submit - Submit lesson plan for review
     */
    public function postLessonPlansSubmit($id = null, $data = [], $segments = [])
    {
        $planId = $data['plan_id'] ?? $id ?? null;
        $result = $this->api->submitLessonPlan($planId, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-plans/approval - List plans pending approval (for headteacher)
     */
    public function getLessonPlansApproval($id = null, $data = [], $segments = [])
    {
        // Default to 'submitted' status for the approval queue
        if (empty($data['status'])) {
            $data['status'] = 'submitted';
        }
        $result = $this->api->getLessonPlans($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/review/{id} - Submit review (approve/reject) for a lesson plan
     */
    public function putLessonPlansReview($id = null, $data = [], $segments = [])
    {
        $planId = $id ?? $data['plan_id'] ?? null;
        $status = $data['status'] ?? null;

        if ($status === 'approved') {
            $result = $this->api->approveLessonPlan($planId, $data);
        } elseif ($status === 'rejected') {
            $result = $this->api->rejectLessonPlan($planId, $data);
        } else {
            return $this->handleResponse(errorResponse('Invalid review status. Must be "approved" or "rejected"', 400));
        }
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/lesson-plans/bulk-approve - Bulk approve multiple plans
     */
    public function putLessonPlansBulkApprove($id = null, $data = [], $segments = [])
    {
        $ids = $data['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return $this->handleResponse(errorResponse('No plan IDs provided', 400));
        }

        $results = ['approved' => 0, 'failed' => 0, 'errors' => []];
        foreach ($ids as $planId) {
            $result = $this->api->approveLessonPlan($planId, $data);
            if (isset($result['status']) && $result['status'] === 'success') {
                $results['approved']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Plan #{$planId}: " . ($result['message'] ?? 'Unknown error');
            }
        }

        return $this->handleResponse(successResponse([
            'message' => "{$results['approved']} plans approved, {$results['failed']} failed",
            'data' => $results
        ]));
    }

    // ==================== LESSON OBSERVATIONS ====================

    /**
     * POST /api/academic/lesson-observations/create - Create lesson observation
     */
    public function postLessonObservationsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createLessonObservation($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/lesson-observations/list - List lesson observations
     */
    public function getLessonObservationsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLessonObservations($data['filters'] ?? []);
        return $this->handleResponse($result);
    }

    // ==================== SCHEME OF WORK ====================

    /**
     * POST /api/academic/scheme-of-work/create - Create scheme of work
     */
    public function postSchemeOfWorkCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createSchemeOfWork($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/scheme-of-work/get/{id} - Get scheme of work
     */
    public function getSchemeOfWorkGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSchemeOfWork($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    // ==================== TEACHER OPERATIONS ====================

    /**
     * GET /api/academic/teachers/classes - Get teacher's classes
     */
    public function getTeachersClasses($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherClasses($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/subjects - Get teacher's subjects
     */
    public function getTeachersSubjects($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherSubjects($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/schedule - Get teacher's schedule
     */
    public function getTeachersSchedule($id = null, $data = [], $segments = [])
    {
        $teacherId = $data['teacher_id'] ?? $id ?? null;
        $result = $this->api->getTeacherSchedule($teacherId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/teachers/list - List available teaching staff
     */
    public function getTeachersList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listTeachers($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/subjects/teachers - Get subject teachers
     */
    public function getSubjectsTeachers($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSubjectTeachers($data['subject_id'] ?? null);
        return $this->handleResponse($result);
    }

    // ==================== WORKFLOW STATUS ====================

    /**
     * GET /api/academic/workflow/status - Get workflow status
     */
    public function getWorkflowStatus($id = null, $data = [], $segments = [])
    {
        $workflowType = $data['workflow_type'] ?? $data['type'] ?? null;
        $instanceId = $data['instance_id'] ?? null;
        if (empty($workflowType) || empty($instanceId)) {
            return $this->badRequest('workflow_type and instance_id are required');
        }
        $result = $this->api->getWorkflowStatus($workflowType, $instanceId);
        return $this->handleResponse($result);
    }

    // ==================== CUSTOM OPERATIONS ====================

    /**
     * GET /api/academic/custom - Handle custom GET operations
     */
    public function getCustom($id = null, $data = [], $segments = [])
    {
        // Assuming handleCustomGet expects ($action, $params, $data)
        $result = $this->api->handleCustomGet(
            $data['action'] ?? null,
            $data['params'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/custom - Handle custom POST operations
     */
    public function postCustom($id = null, $data = [], $segments = [])
    {
        // Assuming handleCustomPost expects ($action, $params, $data)
        $result = $this->api->handleCustomPost(
            $data['action'] ?? null,
            $data['params'] ?? [],
            $data
        );
        return $this->handleResponse($result);
    }
}
