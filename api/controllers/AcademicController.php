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
     * POST /api/academic/exams/approve-results - Approve exam results (Director/academic_approve)
     * Body: { instance_id, approved (bool, default true), comments }
     */
    public function postExamsApproveResults($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_approve', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to approve exam results');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $approved = isset($data['action'])
            ? (strtolower($data['action']) === 'approve')
            : (bool) ($data['approved'] ?? true);
        $remarks = $data['comments'] ?? ($data['remarks'] ?? '');

        $result = $this->api->approveExamResults($instanceId, $approved, $remarks);
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
     * POST /api/academic/promotions/execute - Execute promotions (Director or students_promote)
     * Body: { instance_id, apply_immediately (bool), effective_date (optional) }
     */
    public function postPromotionsExecute($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['students_promote', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to execute student promotions');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $result = $this->api->executePromotions($instanceId, $data);
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
     * POST /api/academic/curriculum/review-and-approve - Review and approve curriculum (Director only)
     * Body: { instance_id, action (approve|reject), comments }
     */
    public function postCurriculumReviewAndApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_approve', 'curriculum_approve', 'academic_manage'],
            [1, 3],
            ['director', 'principal']
        )) {
            return $this->forbidden('You do not have permission to approve curriculum changes');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $action = strtolower($data['action'] ?? ($data['decision'] ?? 'approve'));
        $review = array_merge($data, [
            'approved' => ($action === 'approve'),
            'feedback' => $data['comments'] ?? ($data['feedback'] ?? []),
        ]);

        $result = $this->api->reviewAndApproveCurriculum($instanceId, $review);
        return $this->handleResponse($result);
    }

    // ==================== YEAR TRANSITION WORKFLOW ====================

    /**
     * POST /api/academic/year-transition/start-workflow - Start year transition workflow (Director only)
     * Body: { from_year, to_year, year_start_date, year_end_date, terms[] }
     */
    public function postYearTransitionStartWorkflow($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin']
        )) {
            return $this->forbidden('Only Director or System Admin can start year transition workflows');
        }

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
     * POST /api/academic/year-transition/setup-new-year - Setup new academic year (Director only)
     * Body: { instance_id, year_id (optional), class_structures[], clone_subjects, clone_staff_assignments }
     */
    public function postYearTransitionSetupNewYear($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin']
        )) {
            return $this->forbidden('Only Director or System Admin can setup new academic year');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $yearConfig = $data['year_config'] ?? $data;
        $result = $this->api->setupNewAcademicYear($instanceId, $yearConfig);
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
     * PUT /api/academic/years/set-current - Set year as current (Director/System Admin only)
     * Accepts year_id from URL segment (/set-current/5) or from request body (year_id or id)
     */
    public function putYearsSetCurrent($id = null, $data = [], $segments = [])
    {
        // Only Director (role_id=3) or System Admin (role_id=1) may change the current year
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3],
            ['director', 'system admin', 'systemadmin']
        )) {
            return $this->forbidden('Only Director or System Admin can set the current academic year');
        }

        $yearId = $id ?? ($data['year_id'] ?? ($data['id'] ?? null));
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->setCurrentAcademicYear((int) $yearId);
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
        try {
            $where  = ['1=1'];
            $params = [];
            if (!empty($_GET['class_id']))           { $where[] = 'a.class_id=:cid';     $params[':cid']  = (int)$_GET['class_id']; }
            if (!empty($_GET['term_id']))             { $where[] = 'a.term_id=:tid';      $params[':tid']  = (int)$_GET['term_id']; }
            if (!empty($_GET['subject_id']))          { $where[] = 'a.subject_id=:sid';   $params[':sid']  = (int)$_GET['subject_id']; }
            if (!empty($_GET['status']))              { $where[] = 'a.status=:st';        $params[':st']   = $_GET['status']; }
            if (!empty($_GET['assessment_type_id'])) { $where[] = 'a.assessment_type_id=:atid'; $params[':atid'] = (int)$_GET['assessment_type_id']; }

            $stmt = $this->db->query(
                "SELECT a.id, a.class_id, a.subject_id, a.term_id, a.title, a.max_marks,
                        a.assessment_date, a.status, a.assessment_type_id,
                        c.name  AS class_name,
                        la.name AS learning_area_name, la.code AS learning_area_code,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        t.name  AS term_name, t.term_number,
                        COUNT(DISTINCT fs.student_id) AS graded_count,
                        COUNT(DISTINCT ce.student_id) AS total_students,
                        ROUND(AVG(fs.percentage), 2)  AS average_pct
                 FROM assessments a
                 LEFT JOIN classes c           ON c.id  = a.class_id
                 LEFT JOIN learning_areas la   ON la.id = a.subject_id
                 LEFT JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN academic_terms t    ON t.id  = a.term_id
                 LEFT JOIN formative_scores fs ON fs.assessment_id = a.id
                 LEFT JOIN class_enrollments ce ON ce.class_id = a.class_id
                        AND ce.enrollment_status IN ('active','completed')
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY a.id
                 ORDER BY a.assessment_date DESC, a.id DESC
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
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

    // ==================== CBC: FORMATIVE ASSESSMENTS ====================

    /**
     * GET  /api/academic/formative-assessments         → list formative assessments
     * POST /api/academic/formative-assessments         → create formative assessment
     */
    public function getFormativeAssessments($id = null, $data = [], $segments = [])
    {
        try {
            $db     = $this->db;
            $where  = ["a.assessment_type_id IS NOT NULL"];
            $params = [];

            // Join to assessment_types and filter is_formative=1
            $where[] = "at.is_formative = 1";

            if (!empty($_GET['class_id']))    { $where[] = "a.class_id=:cid";     $params[':cid'] = (int)$_GET['class_id']; }
            if (!empty($_GET['subject_id']))  { $where[] = "a.subject_id=:sid";   $params[':sid'] = (int)$_GET['subject_id']; }
            if (!empty($_GET['term_id']))     { $where[] = "a.term_id=:tid";      $params[':tid'] = (int)$_GET['term_id']; }
            if (!empty($_GET['type_id']))     { $where[] = "a.assessment_type_id=:atid"; $params[':atid'] = (int)$_GET['type_id']; }

            $stmt = $db->query(
                "SELECT a.*,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        c.name AS class_name,
                        t.name AS term_name,
                        CONCAT(st.first_name,' ',st.last_name) AS assigned_by_name
                 FROM assessments a
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.subject_id
                 LEFT JOIN classes c ON c.id = a.class_id
                 LEFT JOIN academic_terms t ON t.id = a.term_id
                 LEFT JOIN staff st ON st.user_id = a.assigned_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date DESC
                 LIMIT 500",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postFormativeAssessments($id = null, $data = [], $segments = [])
    {
        try {
            $required = ['class_id','subject_id','term_id','title','assessment_type_id','max_marks'];
            foreach ($required as $f) {
                if (empty($data[$f])) return $this->badRequest("$f is required");
            }
            // Verify type is formative
            $typeCheck = $this->db->query("SELECT is_formative FROM assessment_types WHERE id=:id LIMIT 1", [':id' => (int)$data['assessment_type_id']]);
            $type = $typeCheck->fetch(\PDO::FETCH_ASSOC);
            if (!$type || !$type['is_formative']) return $this->badRequest('assessment_type_id must refer to a formative type');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
            $this->db->query(
                "INSERT INTO assessments
                    (class_id, subject_id, term_id, title, max_marks, assessment_date, assigned_by, assessment_type_id, learning_outcome_id, status)
                 VALUES
                    (:cid, :sid, :tid, :title, :marks, :dt, :aby, :atid, :loid, 'pending_submission')",
                [
                    ':cid'   => (int)$data['class_id'],
                    ':sid'   => (int)$data['subject_id'],
                    ':tid'   => (int)$data['term_id'],
                    ':title' => trim($data['title']),
                    ':marks' => (float)$data['max_marks'],
                    ':dt'    => $data['assessment_date'] ?? date('Y-m-d'),
                    ':aby'   => $userId,
                    ':atid'  => (int)$data['assessment_type_id'],
                    ':loid'  => !empty($data['learning_outcome_id']) ? (int)$data['learning_outcome_id'] : null,
                ]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Formative assessment created');
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/academic/formative-assessments/{id}/marks → bulk mark entry
     */
    /**
     * GET /api/academic/formative-assessment-marks?assessment_id=X
     * Returns all students for the assessment's class with their existing scores (or null).
     */
    public function getFormativeAssessmentMarks($id = null, $data = [], $segments = [])
    {
        try {
            $id = $id ?? (int)($_GET['assessment_id'] ?? 0);
            if (!$id) return $this->badRequest('assessment_id is required');

            // Get assessment + class info
            $aStmt = $this->db->query(
                "SELECT a.id, a.class_id, a.max_marks, a.title,
                        c.name AS class_name
                 FROM assessments a
                 LEFT JOIN classes c ON c.id = a.class_id
                 WHERE a.id=:id LIMIT 1",
                [':id' => (int)$id]
            );
            $assessment = $aStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$assessment) return $this->notFound('Assessment not found');

            // Get all active students in the class
            $sStmt = $this->db->query(
                "SELECT s.id AS student_id, s.first_name, s.last_name, s.admission_no,
                        fs.score, fs.max_score, fs.percentage, fs.cbc_grade, fs.remarks
                 FROM students s
                 JOIN class_streams cs ON cs.id = s.stream_id AND cs.class_id = :cid
                 LEFT JOIN formative_scores fs ON fs.student_id = s.id AND fs.assessment_id = :aid
                 WHERE s.status = 'active'
                 ORDER BY s.last_name, s.first_name",
                [':cid' => (int)$assessment['class_id'], ':aid' => (int)$id]
            );
            return $this->success($sStmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/academic/formative-assessment-marks
     * Bulk upsert marks for an assessment. Payload: { assessment_id, marks: [{student_id, score, remarks}] }
     */
    public function postFormativeAssessmentMarks($id = null, $data = [], $segments = [])
    {
        try {
            $assessmentId = $id ?? (int)($data['assessment_id'] ?? 0);
            if (!$assessmentId) return $this->badRequest('assessment_id is required');
            $id = $assessmentId;
            // Accept both 'marks' and 'scores' keys for compatibility
            $scores = $data['marks'] ?? $data['scores'] ?? [];
            if (empty($scores)) return $this->badRequest('marks array is required');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            // Get max_marks for this assessment
            $maxStmt = $this->db->query("SELECT max_marks FROM assessments WHERE id=:id LIMIT 1", [':id' => (int)$id]);
            $asmnt = $maxStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$asmnt) return $this->notFound('Assessment not found');
            $maxMarks = (float)$asmnt['max_marks'];

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO formative_scores (assessment_id, student_id, score, max_score, remarks, entered_by)
                 VALUES (:aid, :sid, :score, :max, :rmk, :eby)
                 ON DUPLICATE KEY UPDATE score=:score, max_score=:max, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($scores as $entry) {
                $ins->execute([
                    ':aid'   => (int)$id,
                    ':sid'   => (int)$entry['student_id'],
                    ':score' => min((float)($entry['marks_obtained'] ?? $entry['score'] ?? 0), $maxMarks),
                    ':max'   => $maxMarks,
                    ':rmk'   => $entry['remarks'] ?? null,
                    ':eby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($scores)], 'Marks saved successfully');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/academic/formative-summary?class_id=&subject_id=&term_id=
     * Returns per-student per-learning-area formative averages
     */
    public function getFormativeSummary($id = null, $data = [], $segments = [])
    {
        try {
            $classId   = (int)($_GET['class_id']   ?? 0);
            $subjectId = (int)($_GET['subject_id']  ?? 0);
            $termId    = (int)($_GET['term_id']     ?? 0);
            if (!$classId || !$termId) return $this->success([], 'No filters selected — specify class_id and term_id');

            $stmt = $this->db->query(
                "SELECT
                    s.id AS student_id,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    s.admission_no,
                    la.id AS learning_area_id,
                    la.name AS learning_area_name,
                    COUNT(fs.id) AS assessment_count,
                    ROUND(AVG(fs.percentage),2) AS formative_avg_pct,
                    CASE
                        WHEN AVG(fs.percentage) >= 75 THEN 'EE'
                        WHEN AVG(fs.percentage) >= 60 THEN 'ME'
                        WHEN AVG(fs.percentage) >= 40 THEN 'AE'
                        ELSE 'BE'
                    END AS formative_grade
                 FROM students s
                 JOIN class_streams cs ON cs.id = s.stream_id
                 JOIN formative_scores fs ON fs.student_id = s.id
                 JOIN assessments a ON a.id = fs.assessment_id AND a.term_id = :tid
                 JOIN assessment_types at ON at.id = a.assessment_type_id AND at.is_formative = 1
                 JOIN learning_areas la ON la.id = a.subject_id
                 WHERE cs.class_id = :cid
                   AND (:sid1 = 0 OR la.id = :sid2)
                   AND s.status = 'active'
                 GROUP BY s.id, la.id
                 ORDER BY s.last_name, la.name",
                [':tid' => $termId, ':cid' => $classId, ':sid1' => $subjectId, ':sid2' => $subjectId]
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: ASSESSMENT TYPES ====================

    /**
     * GET /api/academic/assessment-types → list all CBC assessment types
     */
    public function getAssessmentTypes($id = null, $data = [], $segments = [])
    {
        try {
            $filter = $_GET['filter'] ?? 'all'; // all | formative | summative | national
            $where  = ["status='active'"];
            if ($filter === 'formative')  $where[] = "is_formative=1";
            if ($filter === 'summative')  $where[] = "is_summative=1";
            if ($filter === 'national')   $where[] = "name IN ('KNEC Grade 3 Assessment','KPSEA','KJSEA')";

            $stmt = $this->db->query("SELECT * FROM assessment_types WHERE " . implode(' AND ', $where) . " ORDER BY is_formative DESC, name");
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/academic/core-competencies-list → CBC 8 core competencies from DB
     */
    public function getCoreCompetenciesList($id = null, $data = [], $segments = [])
    {
        try {
            $stmt = $this->db->query("SELECT id, code, name, description FROM core_competencies WHERE status='active' ORDER BY sort_order, id");
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: COMPETENCY RATINGS ====================

    /**
     * GET  /api/academic/competency-ratings?class_id=&term_id=&student_id=
     * POST /api/academic/competency-ratings  → bulk upsert
     */
    public function getCompetencyRatings($id = null, $data = [], $segments = [])
    {
        try {
            $termId    = (int)($_GET['term_id']    ?? 0);
            $classId   = (int)($_GET['class_id']   ?? 0);
            $studentId = (int)($_GET['student_id'] ?? 0);
            if (!$termId) return $this->badRequest('term_id is required');

            $where  = ['lc.term_id = :tid'];
            $params = [':tid' => $termId];
            if ($studentId) { $where[] = 'lc.student_id=:sid'; $params[':sid'] = $studentId; }
            elseif ($classId) {
                $where[] = 's.id IN (SELECT st.id FROM students st JOIN class_streams cs2 ON cs2.id=st.stream_id WHERE cs2.class_id=:cid)';
                $params[':cid'] = $classId;
            }

            $stmt = $this->db->query(
                "SELECT lc.*,
                        cc.code AS competency_code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.admission_no
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 JOIN students s ON s.id = lc.student_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY s.last_name, cc.sort_order",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postCompetencyRatings($id = null, $data = [], $segments = [])
    {
        try {
            $ratings = $data['ratings'] ?? []; // [{student_id, competency_id, level_code, evidence, notes}]
            $termId  = (int)($data['term_id'] ?? 0);
            $acadYear = $data['academic_year'] ?? date('Y');
            if (!$termId || empty($ratings)) return $this->badRequest('term_id and ratings are required');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            // Map level_code to performance_level_id
            $lvlStmt = $this->db->query("SELECT id, code FROM performance_levels_cbc");
            $lvlMap  = [];
            foreach ($lvlStmt->fetchAll(\PDO::FETCH_ASSOC) as $lv) $lvlMap[$lv['code']] = $lv['id'];

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO learner_competencies
                    (student_id, competency_id, academic_year, term_id, performance_level_id, evidence, teacher_notes, assessed_by, assessed_date)
                 VALUES (:sid, :cid, :yr, :tid, :lvl, :ev, :notes, :aby, CURDATE())
                 ON DUPLICATE KEY UPDATE performance_level_id=:lvl, evidence=:ev, teacher_notes=:notes, assessed_by=:aby, updated_at=NOW()"
            );
            foreach ($ratings as $r) {
                $levelId = $lvlMap[$r['level_code'] ?? ''] ?? null;
                $ins->execute([
                    ':sid'   => (int)$r['student_id'],
                    ':cid'   => (int)$r['competency_id'],
                    ':yr'    => $acadYear,
                    ':tid'   => $termId,
                    ':lvl'   => $levelId,
                    ':ev'    => $r['evidence']     ?? null,
                    ':notes' => $r['notes']        ?? null,
                    ':aby'   => $userId,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($ratings)], 'Competency ratings saved');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: NATIONAL EXAMS ====================

    /**
     * GET  /api/academic/national-exams?exam_type=KPSEA_G6&exam_year=2024
     * POST /api/academic/national-exams → enter results
     */
    public function getNationalExams($id = null, $data = [], $segments = [])
    {
        try {
            $where  = ['1=1'];
            $params = [];
            foreach (['exam_type','exam_year'] as $f) {
                if (!empty($_GET[$f])) { $where[] = "ne.$f=:$f"; $params[":$f"] = $_GET[$f]; }
            }
            if (!empty($_GET['student_id'])) { $where[] = 'ne.student_id=:sid'; $params[':sid'] = (int)$_GET['student_id']; }
            if (!empty($_GET['class_id'])) {
                $where[] = 'ne.student_id IN (SELECT s.id FROM students s JOIN class_streams cs ON cs.id=s.stream_id WHERE cs.class_id=:cid)';
                $params[':cid'] = (int)$_GET['class_id'];
            }

            $stmt = $this->db->query(
                "SELECT ne.*,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.admission_no,
                        la.name AS learning_area_name
                 FROM national_exam_results ne
                 JOIN students s ON s.id = ne.student_id
                 LEFT JOIN learning_areas la ON la.id = ne.learning_area_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY s.last_name, ne.learning_area_id",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postNationalExams($id = null, $data = [], $segments = [])
    {
        try {
            $results  = $data['results'] ?? []; // [{student_id, learning_area_id, score, max_score, raw_grade, points, pathway}]
            $examType = $data['exam_type'] ?? '';
            $examYear = (int)($data['exam_year'] ?? date('Y'));
            if (!$examType || empty($results)) return $this->badRequest('exam_type and results are required');

            $validTypes = ['KNEC_G3','KPSEA_G6','KJSEA_G9'];
            if (!in_array($examType, $validTypes)) return $this->badRequest('Invalid exam_type');

            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;

            $this->db->beginTransaction();
            $ins = $this->db->getConnection()->prepare(
                "INSERT INTO national_exam_results
                    (student_id, exam_type, exam_year, learning_area_id, score, max_score, percentage,
                     cbc_grade, raw_grade, points, pathway, remarks, entered_by, academic_year_id)
                 VALUES (:sid, :et, :ey, :la, :sc, :mx, :pct, :cg, :rg, :pt, :pw, :rmk, :eby, :ayid)
                 ON DUPLICATE KEY UPDATE
                    score=:sc, max_score=:mx, percentage=:pct, cbc_grade=:cg,
                    raw_grade=:rg, points=:pt, pathway=:pw, remarks=:rmk, entered_by=:eby, updated_at=NOW()"
            );
            foreach ($results as $r) {
                $score   = (float)($r['score']     ?? 0);
                $max     = (float)($r['max_score'] ?? 100);
                $pct     = $max > 0 ? round(($score / $max) * 100, 2) : 0;
                $grade   = $pct >= 75 ? 'EE' : ($pct >= 60 ? 'ME' : ($pct >= 40 ? 'AE' : 'BE'));
                $ins->execute([
                    ':sid'  => (int)$r['student_id'],
                    ':et'   => $examType,
                    ':ey'   => $examYear,
                    ':la'   => (int)$r['learning_area_id'],
                    ':sc'   => $score,
                    ':mx'   => $max,
                    ':pct'  => $pct,
                    ':cg'   => $grade,
                    ':rg'   => $r['raw_grade']  ?? null,
                    ':pt'   => !empty($r['points']) ? (float)$r['points'] : null,
                    ':pw'   => $r['pathway']    ?? null,
                    ':rmk'  => $r['remarks']    ?? null,
                    ':eby'  => $userId,
                    ':ayid' => !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null,
                ]);
            }
            $this->db->commit();
            return $this->success(['saved' => count($results)], 'National exam results saved');
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollback();
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: STRANDS ====================

    /**
     * GET /api/academic/strands?learning_area_id=X
     */
    public function getStrands($id = null, $data = [], $segments = [])
    {
        try {
            $laId  = (int)($_GET['learning_area_id'] ?? 0);
            $where = $laId ? 'WHERE learning_area_id=:la' : '';
            $stmt  = $this->db->query(
                "SELECT s.id, s.code, s.name, s.level_range, s.sort_order,
                        la.id AS learning_area_id, la.name AS learning_area_name
                 FROM strands s
                 LEFT JOIN learning_areas la ON la.id = s.learning_area_id
                 $where
                 ORDER BY s.sort_order, s.id",
                $laId ? [':la' => $laId] : []
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: CLASS STUDENTS ====================

    /**
     * GET /api/academic/class-students?class_id=X
     * Returns active enrolled students for a class.
     */
    public function getClassStudents($id = null, $data = [], $segments = [])
    {
        try {
            $classId = (int)($_GET['class_id'] ?? 0);
            if (!$classId) return $this->badRequest('class_id is required');

            $stmt = $this->db->query(
                "SELECT DISTINCT s.id, s.first_name, s.last_name, s.admission_no, s.stream_id
                 FROM students s
                 JOIN class_enrollments ce ON ce.student_id = s.id AND ce.class_id = :cid
                    AND ce.enrollment_status IN ('active','completed')
                 WHERE s.status = 'active'
                 ORDER BY s.last_name, s.first_name",
                [':cid' => $classId]
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: COMPUTE TERM SCORES ====================

    /**
     * POST /api/academic/compute-term-scores
     * Computes formative/summative aggregates from formative_scores → term_subject_scores.
     * Body: { class_id, term_id, subject_id? }  OR  { assessment_id }
     */
    public function postComputeTermScores($id = null, $data = [], $segments = [])
    {
        try {
            $classId   = (int)($data['class_id']   ?? 0);
            $termId    = (int)($data['term_id']     ?? 0);
            $subjectId = (int)($data['subject_id']  ?? 0);
            $asmtId    = (int)($data['assessment_id'] ?? 0);

            // If only assessment_id given, derive class/term/subject from it
            if ($asmtId && (!$classId || !$termId)) {
                $r = $this->db->query(
                    "SELECT class_id, term_id, subject_id FROM assessments WHERE id=:id LIMIT 1",
                    [':id' => $asmtId]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$r) return $this->notFound('Assessment not found');
                $classId   = $classId   ?: (int)$r['class_id'];
                $termId    = $termId    ?: (int)$r['term_id'];
                $subjectId = $subjectId ?: (int)$r['subject_id'];
            }
            if (!$classId || !$termId) return $this->badRequest('class_id and term_id are required');

            // Build list of (student_id, subject_id) pairs to compute
            $where  = ['a.class_id=:cid', 'a.term_id=:tid'];
            $params = [':cid' => $classId, ':tid' => $termId];
            if ($subjectId) { $where[] = 'a.subject_id=:sid'; $params[':sid'] = $subjectId; }

            $rows = $this->db->query(
                "SELECT DISTINCT fs.student_id, a.subject_id,
                        at.is_formative, at.is_summative
                 FROM formative_scores fs
                 JOIN assessments a       ON a.id  = fs.assessment_id
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 WHERE " . implode(' AND ', $where),
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($rows)) return $this->success(['computed' => 0], 'No scored assessments found for these filters');

            // Aggregate per student per subject
            $combos = [];
            foreach ($rows as $r) {
                $key = $r['student_id'] . '_' . $r['subject_id'];
                $combos[$key] = ['student_id' => (int)$r['student_id'], 'subject_id' => (int)$r['subject_id']];
            }

            $upsert = $this->db->getConnection()->prepare(
                "INSERT INTO term_subject_scores
                    (student_id, term_id, subject_id,
                     formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                     summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                     overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at)
                 VALUES
                    (:sid, :tid, :subid,
                     :ft, :fm, :fp, :fg, :fc,
                     :st, :sm, :sp, :sg, :sc,
                     :ov, :op, :og, :opts, :ac, NOW())
                 ON DUPLICATE KEY UPDATE
                     formative_total=:ft, formative_max=:fm, formative_percentage=:fp,
                     formative_grade=:fg, formative_count=:fc,
                     summative_total=:st, summative_max=:sm, summative_percentage=:sp,
                     summative_grade=:sg, summative_count=:sc,
                     overall_score=:ov, overall_percentage=:op, overall_grade=:og,
                     overall_points=:opts, assessment_count=:ac, calculated_at=NOW()"
            );

            $computed = 0;
            foreach ($combos as $combo) {
                $stu  = $combo['student_id'];
                $subj = $combo['subject_id'];

                $agg = $this->db->query(
                    "SELECT
                        SUM(CASE WHEN at.is_formative=1 THEN fs.score ELSE 0 END)     AS ft,
                        SUM(CASE WHEN at.is_formative=1 THEN fs.max_score ELSE 0 END) AS fm,
                        COUNT(CASE WHEN at.is_formative=1 THEN 1 END)                 AS fc,
                        SUM(CASE WHEN at.is_summative=1 THEN fs.score ELSE 0 END)     AS st,
                        SUM(CASE WHEN at.is_summative=1 THEN fs.max_score ELSE 0 END) AS sm,
                        COUNT(CASE WHEN at.is_summative=1 THEN 1 END)                 AS sc,
                        COUNT(fs.id) AS ac
                     FROM formative_scores fs
                     JOIN assessments a ON a.id = fs.assessment_id
                        AND a.term_id=:tid AND a.subject_id=:subid
                     JOIN assessment_types at ON at.id = a.assessment_type_id
                     WHERE fs.student_id=:stu",
                    [':tid' => $termId, ':subid' => $subj, ':stu' => $stu]
                )->fetch(\PDO::FETCH_ASSOC);

                $ft = (float)($agg['ft'] ?? 0);
                $fm = (float)($agg['fm'] ?? 0);
                $fc = (int)  ($agg['fc'] ?? 0);
                $fp = $fm > 0 ? round(($ft / $fm) * 100, 2) : 0;
                $fg = $fp >= 75 ? 'EE' : ($fp >= 60 ? 'ME' : ($fp >= 40 ? 'AE' : 'BE'));

                $st = (float)($agg['st'] ?? 0);
                $sm = (float)($agg['sm'] ?? 0);
                $sc = (int)  ($agg['sc'] ?? 0);
                $sp = $sm > 0 ? round(($st / $sm) * 100, 2) : 0;
                $sg = $sp >= 75 ? 'EE' : ($sp >= 60 ? 'ME' : ($sp >= 40 ? 'AE' : 'BE'));

                // CBC: 40% formative + 60% summative
                $op = round(($fp * 0.4) + ($sp * 0.6), 2);
                $og = $op >= 75 ? 'EE' : ($op >= 60 ? 'ME' : ($op >= 40 ? 'AE' : 'BE'));
                $opts = $og === 'EE' ? 4.0 : ($og === 'ME' ? 3.0 : ($og === 'AE' ? 2.0 : 1.0));
                $ov = round(($ft + $st), 2);

                $upsert->execute([
                    ':sid'   => $stu,  ':tid' => $termId, ':subid' => $subj,
                    ':ft'    => $ft,   ':fm'  => $fm,  ':fp' => $fp,  ':fg' => $fg,  ':fc' => $fc,
                    ':st'    => $st,   ':sm'  => $sm,  ':sp' => $sp,  ':sg' => $sg,  ':sc' => $sc,
                    ':ov'    => $ov,   ':op'  => $op,  ':og' => $og,  ':opts' => $opts,
                    ':ac'    => (int)($agg['ac'] ?? 0),
                ]);
                $computed++;
            }
            return $this->success(['computed' => $computed], "$computed student-subject scores recomputed");
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: REPORT CARD DATA ====================

    /**
     * GET /api/academic/report-card-data/{student_id}?term_id=
     * Consolidated CBC report card: term_subject_scores + competency ratings + attendance + values.
     */
    public function getReportCardData($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = $id ?? (int)($_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');
            $termId    = (int)($_GET['term_id'] ?? 0);

            // Student info
            $student = $this->db->query(
                "SELECT s.id, s.first_name, s.last_name, s.admission_no,
                        c.name AS class_name, cs.stream_name
                 FROM students s
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c        ON c.id  = cs.class_id
                 WHERE s.id=:id LIMIT 1",
                [':id' => $studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$student) return $this->notFound('Student not found');

            // Term info
            $termWhere  = $termId ? 'WHERE id=:tid LIMIT 1' : "WHERE status='current' LIMIT 1";
            $termParams = $termId ? [':tid' => $termId] : [];
            $term = $this->db->query("SELECT id, name, term_number, year FROM academic_terms $termWhere", $termParams)
                             ->fetch(\PDO::FETCH_ASSOC);
            $resolvedTermId = $term ? (int)$term['id'] : $termId;

            // Subject scores
            $scores = $this->db->query(
                "SELECT tss.*,
                        la.name AS subject_name, la.code AS subject_code
                 FROM term_subject_scores tss
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id=:sid AND tss.term_id=:tid
                 ORDER BY la.name",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Core competency ratings
            $competencies = $this->db->query(
                "SELECT lc.competency_id, lc.performance_level_id, lc.evidence, lc.notes,
                        cc.code, cc.name AS competency_name,
                        plc.code AS level_code, plc.name AS level_name
                 FROM learner_competencies lc
                 JOIN core_competencies cc ON cc.id = lc.competency_id
                 LEFT JOIN performance_levels_cbc plc ON plc.id = lc.performance_level_id
                 WHERE lc.student_id=:sid AND lc.term_id=:tid",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Core values
            $values = $this->db->query(
                "SELECT sv.value_id, sv.rating, sv.evidence,
                        cv.name AS value_name
                 FROM student_core_values sv
                 JOIN core_values cv ON cv.id = sv.value_id
                 WHERE sv.student_id=:sid AND sv.term_id=:tid",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance summary
            $attendance = $this->db->query(
                "SELECT
                    COUNT(*) AS total_days,
                    SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) AS days_present,
                    SUM(CASE WHEN status='absent'  THEN 1 ELSE 0 END) AS days_absent,
                    SUM(CASE WHEN status='late'    THEN 1 ELSE 0 END) AS days_late
                 FROM student_attendance
                 WHERE student_id=:sid AND term_id=:tid",
                [':sid' => $studentId, ':tid' => $resolvedTermId]
            )->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'student'      => $student,
                'term'         => $term,
                'scores'       => $scores,
                'competencies' => $competencies,
                'values'       => $values,
                'attendance'   => $attendance,
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== CBC: STUDENT GROWTH ====================

    /**
     * GET /api/academic/student-assessment-history?student_id=X&term_id=&subject_id=
     * Returns all graded assessments for a student with their scores.
     */
    public function getStudentAssessmentHistory($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($_GET['student_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $where  = ['fs.student_id=:sid'];
            $params = [':sid' => $studentId];
            if (!empty($_GET['term_id']))    { $where[] = 'a.term_id=:tid';    $params[':tid']  = (int)$_GET['term_id']; }
            if (!empty($_GET['subject_id'])) { $where[] = 'a.subject_id=:sub'; $params[':sub']  = (int)$_GET['subject_id']; }

            $stmt = $this->db->query(
                "SELECT a.id AS assessment_id, a.title, a.assessment_date, a.max_marks,
                        fs.score, fs.percentage, fs.cbc_grade,
                        at.name AS type_name, at.is_formative, at.is_summative,
                        la.name AS subject_name, la.code AS subject_code,
                        t.name AS term_name, t.term_number, t.year
                 FROM formative_scores fs
                 JOIN assessments a       ON a.id  = fs.assessment_id
                 JOIN assessment_types at ON at.id = a.assessment_type_id
                 LEFT JOIN learning_areas la ON la.id = a.subject_id
                 LEFT JOIN academic_terms t  ON t.id  = a.term_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY a.assessment_date ASC, a.id ASC",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/academic/student-growth-trend?student_id=X&learning_area_id=Y
     * Returns per-term average scores for a student in a learning area (for charting).
     */
    public function getStudentGrowthTrend($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = (int)($_GET['student_id']       ?? 0);
            $laId      = (int)($_GET['learning_area_id'] ?? 0);
            if (!$studentId) return $this->badRequest('student_id is required');

            $where  = ['tss.student_id=:sid'];
            $params = [':sid' => $studentId];
            if ($laId) { $where[] = 'tss.subject_id=:la'; $params[':la'] = $laId; }

            $stmt = $this->db->query(
                "SELECT t.id AS term_id, t.name AS term_name, t.term_number, t.year,
                        la.id AS subject_id, la.name AS subject_name,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade, tss.overall_points
                 FROM term_subject_scores tss
                 JOIN academic_terms t  ON t.id  = tss.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY t.year ASC, t.term_number ASC, la.name ASC",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== HELPERS ====================

    // ==================== STUDENT TIMELINE ====================

    /**
     * GET /api/academic/student-timeline/{student_id}
     * Full academic, finance, discipline, attendance history across all years.
     */
    public function getStudentTimeline($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($segments[0] ?? null);
        if (!$studentId) return $this->error('student_id required');

        try {
            $db = $this->db;

            // Core student record
            $student = $db->query(
                "SELECT s.id, s.admission_no, s.first_name, s.middle_name, s.last_name,
                        s.date_of_birth, s.gender, s.admission_date, s.status,
                        s.is_sponsored, s.sponsor_name, s.sponsor_type, s.sponsor_waiver_percentage,
                        s.photo_url, s.nemis_number,
                        c.name AS current_class, cs.stream_name AS current_stream,
                        st.type_name AS student_type
                 FROM students s
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE s.id = ?",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$student) return $this->error('Student not found', 404);

            // Academic history per year
            $academics = $db->query(
                "SELECT ce.academic_year_id, ay.year_code, ay.year_name,
                        c.name AS class_name, cs.stream_name,
                        ce.term1_average, ce.term2_average, ce.term3_average,
                        ce.year_average, ce.overall_grade, ce.class_rank,
                        ce.attendance_percentage, ce.days_present, ce.days_absent,
                        ce.promotion_status,
                        pc.name AS promoted_to_class,
                        ce.teacher_comments, ce.head_teacher_comments
                 FROM class_enrollments ce
                 JOIN academic_years ay ON ay.id = ce.academic_year_id
                 JOIN classes c ON c.id = ce.class_id
                 LEFT JOIN class_streams cs ON cs.id = ce.stream_id
                 LEFT JOIN classes pc ON pc.id = ce.promoted_to_class_id
                 WHERE ce.student_id = ?
                 ORDER BY ay.start_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Term-level subject scores per year
            $subjectScores = $db->query(
                "SELECT tss.academic_year_id, ay.year_code, t.term_number, t.name AS term_name,
                        la.name AS subject_name, la.code AS subject_code,
                        tss.formative_percentage, tss.summative_percentage,
                        tss.overall_percentage, tss.overall_grade
                 FROM term_subject_scores tss
                 JOIN academic_years ay ON ay.id = tss.academic_year_id
                 JOIN academic_terms t ON t.id = tss.term_id
                 JOIN learning_areas la ON la.id = tss.subject_id
                 WHERE tss.student_id = ?
                 ORDER BY ay.start_date ASC, t.term_number ASC, la.name ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Payment / finance history
            $payments = $db->query(
                "SELECT pt.academic_year, at2.term_number, at2.name AS term_name,
                        pt.amount_paid, pt.payment_date, pt.payment_method,
                        pt.receipt_no, pt.reference_no, pt.status
                 FROM payment_transactions pt
                 LEFT JOIN academic_terms at2 ON at2.id = pt.term_id
                 WHERE pt.student_id = ?
                 ORDER BY pt.payment_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Outstanding fee balances per year
            $feeObligations = $db->query(
                "SELECT o.academic_year, t.term_number, t.name AS term_name,
                        ft.fee_name,
                        o.amount_due, o.amount_paid, o.amount_waived, o.balance,
                        o.payment_status
                 FROM student_fee_obligations o
                 JOIN academic_terms t ON t.id = o.term_id
                 JOIN fee_structure_details fsd ON fsd.id = o.fee_structure_detail_id
                 JOIN fee_types ft ON ft.id = fsd.fee_type_id
                 WHERE o.student_id = ?
                 ORDER BY o.academic_year ASC, t.term_number ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Discipline history
            $discipline = $db->query(
                "SELECT dc.incident_date, dc.incident_type, dc.severity,
                        dc.description, dc.action_taken, dc.status,
                        ay.year_code AS academic_year,
                        t.term_number
                 FROM discipline_cases dc
                 LEFT JOIN academic_years ay ON ay.id = dc.academic_year_id
                 LEFT JOIN academic_terms t ON t.id = dc.term_id
                 WHERE dc.student_id = ?
                 ORDER BY dc.incident_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Attendance summary per term
            $attendance = $db->query(
                "SELECT ay.year_code AS academic_year, t.term_number, t.name AS term_name,
                        COUNT(CASE WHEN sa.status = 'present' THEN 1 END) AS days_present,
                        COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) AS days_absent,
                        COUNT(CASE WHEN sa.status = 'late' THEN 1 END) AS days_late,
                        COUNT(sa.id) AS total_recorded
                 FROM student_attendance sa
                 JOIN academic_years ay ON ay.id = sa.academic_year_id
                 JOIN academic_terms t ON t.id = sa.term_id
                 WHERE sa.student_id = ?
                 GROUP BY ay.id, t.id
                 ORDER BY ay.start_date ASC, t.term_number ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Fee credit notes
            $creditNotes = $db->query(
                "SELECT credit_number, academic_year, credit_amount, credit_reason,
                        status, applied_amount, remaining_amount, created_at
                 FROM fee_credit_notes
                 WHERE student_id = ? ORDER BY academic_year ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Transfer history
            $transfers = $db->query(
                "SELECT str.request_number, str.request_date, str.transfer_type,
                        str.destination_school, str.reason, str.status,
                        str.fee_balance_at_request, str.completed_at
                 FROM student_transfer_requests str
                 WHERE str.student_id = ? ORDER BY str.request_date ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Summary stats
            $totalPaid = array_sum(array_column($payments, 'amount_paid'));
            $totalOwed = array_sum(array_column($feeObligations, 'amount_due'));
            $totalOutstanding = array_sum(array_column($feeObligations, 'balance'));

            return $this->success([
                'student'        => $student,
                'academics'      => $academics,
                'subject_scores' => $subjectScores,
                'payments'       => $payments,
                'fee_obligations' => $feeObligations,
                'discipline'     => $discipline,
                'attendance'     => $attendance,
                'credit_notes'   => $creditNotes,
                'transfers'      => $transfers,
                'summary' => [
                    'years_enrolled'   => count($academics),
                    'total_fees_billed' => $totalOwed,
                    'total_fees_paid'  => $totalPaid,
                    'current_balance'  => $totalOutstanding,
                    'discipline_cases' => count($discipline),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/academic/staff-timeline/{staff_id}
     */
    public function getStaffTimeline($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($segments[0] ?? null);
        if (!$staffId) return $this->error('staff_id required');

        try {
            $db = $this->db;

            $staff = $db->query(
                "SELECT s.id, s.employee_number, s.first_name, s.last_name, s.email,
                        s.phone, s.gender, s.date_of_birth, s.hire_date, s.employment_status,
                        s.basic_salary, s.photo_url,
                        d.name AS department_name, sc.name AS staff_category,
                        p.title AS position_title
                 FROM staff s
                 LEFT JOIN departments d ON d.id = s.department_id
                 LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                 LEFT JOIN positions p ON p.id = s.position_id
                 WHERE s.id = ?",
                [$staffId]
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$staff) return $this->error('Staff not found', 404);

            $assignments = $db->query(
                "SELECT ay.year_code AS academic_year, c.name AS class_name,
                        cs.stream_name, sca.role, la.name AS subject_name,
                        sca.status, sca.start_date, sca.end_date
                 FROM staff_class_assignments sca
                 JOIN academic_years ay ON ay.id = sca.academic_year_id
                 JOIN classes c ON c.id = sca.class_id
                 LEFT JOIN class_streams cs ON cs.id = sca.stream_id
                 LEFT JOIN learning_areas la ON la.id = sca.subject_id
                 WHERE sca.staff_id = ?
                 ORDER BY ay.start_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $promotions = $db->query(
                "SELECT sp.promotion_type, sp.from_position, sp.to_position,
                        sp.from_salary, sp.to_salary, sp.effective_date, sp.status, sp.reason,
                        fd.name AS from_department, td.name AS to_department
                 FROM staff_promotions sp
                 LEFT JOIN departments fd ON fd.id = sp.from_department_id
                 LEFT JOIN departments td ON td.id = sp.to_department_id
                 WHERE sp.staff_id = ? ORDER BY sp.effective_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $payrollHistory = $db->query(
                "SELECT payroll_month, basic_salary, total_allowances, total_deductions,
                        paye_tax, nssf_deduction, nhif_deduction, net_pay, status,
                        payment_date
                 FROM staff_payroll
                 WHERE staff_id = ? ORDER BY payroll_month ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $advances = $db->query(
                "SELECT advance_number, requested_amount, approved_amount,
                        request_date, deduction_schedule, amount_deducted, balance_remaining, status
                 FROM staff_salary_advances
                 WHERE staff_id = ? ORDER BY request_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $leaves = $db->query(
                "SELECT leave_type, start_date, end_date, days_taken, reason, status
                 FROM staff_leaves WHERE staff_id = ? ORDER BY start_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $performance = $db->query(
                "SELECT review_period, overall_rating, strengths, areas_for_improvement,
                        goals_set, reviewer_comments, status, review_date
                 FROM staff_performance_reviews
                 WHERE staff_id = ? ORDER BY review_date ASC",
                [$staffId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'staff'       => $staff,
                'assignments' => $assignments,
                'promotions'  => $promotions,
                'payroll'     => $payrollHistory,
                'advances'    => $advances,
                'leaves'      => $leaves,
                'performance' => $performance,
                'summary' => [
                    'years_of_service'   => count(array_unique(array_column($assignments, 'academic_year'))),
                    'total_promotions'   => count($promotions),
                    'leave_days_taken'   => array_sum(array_column($leaves, 'days_taken')),
                    'active_advance'     => count(array_filter($advances, fn($a) => $a['status'] === 'active')),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== TRANSFER REQUESTS ====================

    /**
     * GET /api/academic/transfer-requests
     * POST /api/academic/transfer-requests
     */
    public function getTransferRequests($id = null, $data = [], $segments = [])
    {
        try {
            if ($id) {
                $row = $this->db->query(
                    "SELECT tr.*, s.first_name, s.last_name, s.admission_no,
                            c.name AS class_name, u.name AS requested_by_name,
                            au.name AS approved_by_name
                     FROM student_transfer_requests tr
                     JOIN students s ON s.id = tr.student_id
                     LEFT JOIN class_streams cs ON cs.id = s.stream_id
                     LEFT JOIN classes c ON c.id = cs.class_id
                     LEFT JOIN users u ON u.id = tr.requested_by
                     LEFT JOIN users au ON au.id = tr.approved_by
                     WHERE tr.id = ?",
                    [$id]
                )->fetch(\PDO::FETCH_ASSOC);

                // also get clearances
                $clearances = $this->db->query(
                    "SELECT sc.*, u.name AS checked_by_name
                     FROM student_clearances sc
                     LEFT JOIN users u ON u.id = sc.checked_by
                     WHERE sc.transfer_request_id = ?",
                    [$id]
                )->fetchAll(\PDO::FETCH_ASSOC);

                return $this->success(['request' => $row, 'clearances' => $clearances]);
            }

            $rows = $this->db->query(
                "SELECT tr.id, tr.request_number, tr.request_date, tr.transfer_type,
                        tr.destination_school, tr.clearance_status, tr.status,
                        tr.fee_balance_at_request,
                        CONCAT(s.first_name,' ',s.last_name) AS student_name,
                        s.admission_no, c.name AS class_name
                 FROM student_transfer_requests tr
                 JOIN students s ON s.id = tr.student_id
                 LEFT JOIN class_streams cs ON cs.id = s.stream_id
                 LEFT JOIN classes c ON c.id = cs.class_id
                 ORDER BY tr.created_at DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($rows);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    public function postTransferRequests($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? null;
        if (!$studentId) return $this->error('student_id required');

        try {
            $db = $this->db;

            // GUARD: Check for outstanding fees before allowing transfer
            $feeCheck = $db->query(
                "SELECT COALESCE(SUM(balance),0) AS outstanding
                 FROM student_fee_obligations
                 WHERE student_id = ? AND academic_year = YEAR(CURDATE())",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);

            $outstanding = (float)($feeCheck['outstanding'] ?? 0);

            // Log the business rule check
            if ($outstanding > 0) {
                $db->query(
                    "INSERT INTO business_rule_violations_log
                     (rule_code, rule_description, entity_type, entity_id,
                      triggered_by, action_attempted, violation_data)
                     VALUES ('TRANS_FEE_BLOCK','Student has outstanding fees — transfer blocked',
                             'student', ?, ?, 'initiate_transfer',
                             JSON_OBJECT('outstanding', ?, 'student_id', ?))",
                    [
                        $studentId,
                        $this->user['id'] ?? null,
                        $outstanding,
                        $studentId,
                    ]
                );

                return $this->error(
                    "Cannot initiate transfer: student has outstanding fees of KES " .
                    number_format($outstanding, 2) .
                    ". Fees must be paid or waived before transfer can proceed.",
                    422
                );
            }

            // Generate request number
            $reqNumber = 'TRF-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);

            $db->query(
                "INSERT INTO student_transfer_requests
                 (request_number, student_id, academic_year_id, request_date, requested_by,
                  transfer_type, destination_school, reason, fee_balance_at_request, status)
                 SELECT ?, ?, ay.id, CURDATE(), ?, ?, ?, ?, ?, 'pending_clearance'
                 FROM academic_years ay WHERE ay.is_current = 1 LIMIT 1",
                [
                    $reqNumber,
                    $studentId,
                    $this->user['id'] ?? null,
                    $data['transfer_type'] ?? 'inter_school',
                    $data['destination_school'] ?? null,
                    $data['reason'] ?? null,
                    $outstanding,
                ]
            );
            $requestId = $db->lastInsertId();

            // Auto-create clearance items
            foreach (['finance', 'library', 'uniform', 'property', 'academic'] as $type) {
                $db->query(
                    "INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status)
                     VALUES (?, ?, ?, 'pending')",
                    [$studentId, $requestId, $type]
                );
            }

            return $this->success(['request_id' => $requestId, 'request_number' => $reqNumber], 201);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/academic/transfer-requests/{id}
     * Update clearance status or approve/reject transfer
     */
    public function putTransferRequests($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('id required');
        $action = $data['action'] ?? null;

        try {
            $db = $this->db;

            if ($action === 'update_clearance') {
                $db->query(
                    "UPDATE student_clearances SET status = ?, checked_by = ?, checked_at = NOW(),
                            amount_outstanding = ?, notes = ?
                     WHERE transfer_request_id = ? AND clearance_type = ?",
                    [
                        $data['status'],
                        $this->user['id'] ?? null,
                        $data['amount_outstanding'] ?? 0,
                        $data['notes'] ?? null,
                        $id,
                        $data['clearance_type'],
                    ]
                );

                // Check if all clearances are done
                $pending = $db->query(
                    "SELECT COUNT(*) FROM student_clearances
                     WHERE transfer_request_id = ? AND status != 'cleared'",
                    [$id]
                )->fetchColumn();

                $blocked = $db->query(
                    "SELECT COUNT(*) FROM student_clearances
                     WHERE transfer_request_id = ? AND status = 'blocked'",
                    [$id]
                )->fetchColumn();

                if ($blocked > 0) {
                    $db->query("UPDATE student_transfer_requests SET clearance_status = 'blocked' WHERE id = ?", [$id]);
                } elseif ($pending == 0) {
                    $db->query(
                        "UPDATE student_transfer_requests SET clearance_status = 'fully_cleared', status = 'clearance_passed' WHERE id = ?",
                        [$id]
                    );
                }

                return $this->success(['updated' => true]);
            }

            if ($action === 'approve') {
                $db->query(
                    "UPDATE student_transfer_requests SET status = 'approved', approved_by = ?, approval_date = NOW() WHERE id = ?",
                    [$this->user['id'] ?? null, $id]
                );
                // Update student status
                $req = $db->query("SELECT student_id FROM student_transfer_requests WHERE id = ?", [$id])->fetch();
                if ($req) {
                    $db->query("UPDATE students SET status = 'transferred' WHERE id = ?", [$req['student_id']]);
                }
                return $this->success(['approved' => true]);
            }

            if ($action === 'reject') {
                $db->query(
                    "UPDATE student_transfer_requests SET status = 'rejected', rejection_reason = ? WHERE id = ?",
                    [$data['reason'] ?? null, $id]
                );
                return $this->success(['rejected' => true]);
            }

            return $this->error('Unknown action');
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ==================== YEAR-END ROLLOVER ====================

    /**
     * GET /api/academic/year-rollover-status
     * Returns the current state of the rollover checklist.
     */
    public function getYearRolloverStatus($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            $currentYear = $db->query(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$currentYear) return $this->error('No active academic year');

            // Check each prerequisite
            $termsStatus = $db->query(
                "SELECT term_number, name, status FROM academic_terms
                 WHERE academic_year_id = ? ORDER BY term_number",
                [$currentYear['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $pendingResults = $db->query(
                "SELECT COUNT(*) FROM class_enrollments
                 WHERE academic_year_id = ? AND year_average IS NULL",
                [$currentYear['id']]
            )->fetchColumn();

            $pendingPromotions = $db->query(
                "SELECT COUNT(*) FROM class_enrollments
                 WHERE academic_year_id = ? AND promotion_status IS NULL",
                [$currentYear['id']]
            )->fetchColumn();

            $outstandingFees = $db->query(
                "SELECT COUNT(DISTINCT student_id) FROM student_fee_obligations
                 WHERE academic_year = ? AND balance > 0",
                [$currentYear['year_code']]
            )->fetchColumn();

            $rolloverLog = $db->query(
                "SELECT step, status, students_promoted, students_retained, fee_balances_carried,
                        credit_notes_created, performed_at
                 FROM academic_year_rollover_log
                 WHERE from_year_id = ?
                 ORDER BY performed_at DESC LIMIT 20",
                [$currentYear['id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $allTermsComplete = !array_filter($termsStatus, fn($t) => $t['status'] !== 'completed');

            return $this->success([
                'current_year'        => $currentYear,
                'terms'               => $termsStatus,
                'all_terms_complete'  => $allTermsComplete,
                'pending_results'     => (int)$pendingResults,
                'pending_promotions'  => (int)$pendingPromotions,
                'students_with_fees'  => (int)$outstandingFees,
                'ready_for_rollover'  => $allTermsComplete && $pendingResults == 0 && $pendingPromotions == 0,
                'rollover_log'        => $rolloverLog,
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/academic/year-rollover
     * Executes one step of the year-end rollover process.
     * Body: { step: 'fee_carryover' | 'staff_reassignment' | 'create_new_year' | ... }
     */
    public function postYearRollover($id = null, $data = [], $segments = [])
    {
        $step = $data['step'] ?? null;
        if (!$step) return $this->error('step required');

        try {
            $db = $this->db;
            $userId = $this->user['id'] ?? null;

            $currentYear = $db->query(
                "SELECT * FROM academic_years WHERE is_current = 1 LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);

            if (!$currentYear) return $this->error('No active academic year');

            $rolloverRef = 'ROL-' . date('Ymd');
            $result = ['step' => $step, 'status' => 'completed'];

            if ($step === 'fee_carryover') {
                // For each student with outstanding balance, set previous_year_balance on new year obligations
                // For students with surplus (credit), create fee_credit_notes
                $students = $db->query(
                    "SELECT student_id,
                            SUM(balance) AS outstanding,
                            SUM(CASE WHEN balance < 0 THEN ABS(balance) ELSE 0 END) AS surplus
                     FROM student_fee_obligations
                     WHERE academic_year = ?
                     GROUP BY student_id",
                    [$currentYear['year_code']]
                )->fetchAll(\PDO::FETCH_ASSOC);

                $carried = 0; $credits = 0;
                foreach ($students as $s) {
                    if ((float)$s['outstanding'] > 0) {
                        // Record carryover
                        $db->query(
                            "INSERT INTO student_fee_carryover
                             (student_id, academic_year, previous_balance, action_taken, notes)
                             VALUES (?, ?, ?, 'add_to_current', 'Year-end carryover')
                             ON DUPLICATE KEY UPDATE previous_balance = VALUES(previous_balance)",
                            [$s['student_id'], (int)$currentYear['year_code'] + 1, $s['outstanding']]
                        );
                        $carried++;
                    }
                    if ((float)$s['surplus'] > 0) {
                        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad($credits + 1, 4, '0', STR_PAD_LEFT);
                        $db->query(
                            "INSERT INTO fee_credit_notes
                             (credit_number, student_id, academic_year, credit_amount, credit_reason, expiry_date, created_by)
                             VALUES (?, ?, ?, ?, 'overpayment', DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?)",
                            [$creditNum, $s['student_id'], $currentYear['year_code'], $s['surplus'], $userId]
                        );
                        $credits++;
                    }
                }
                $result['fee_balances_carried'] = $carried;
                $result['credit_notes_created'] = $credits;

            } elseif ($step === 'staff_reassignment') {
                // Copy active staff_class_assignments to new year (admin adjusts class/stream after)
                $count = $db->query(
                    "SELECT COUNT(*) FROM staff_class_assignments WHERE academic_year_id = ? AND status = 'active'",
                    [$currentYear['id']]
                )->fetchColumn();
                $result['staff_to_reassign'] = (int)$count;
                $result['note'] = 'Use Manage Staff → Class Assignments to confirm new year assignments';

            } elseif ($step === 'create_new_year') {
                $newYearCode = (int)$currentYear['year_code'] + 1;
                // Create new academic year
                $existing = $db->query("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch();
                if ($existing) {
                    $result['note'] = "Academic year $newYearCode already exists";
                    $result['new_year_id'] = $existing['id'];
                } else {
                    $db->query(
                        "INSERT INTO academic_years (year_code, year_name, start_date, end_date, status, created_by)
                         VALUES (?, ?, ?, ?, 'planning', ?)",
                        [
                            $newYearCode,
                            "$newYearCode Academic Year",
                            "$newYearCode-01-06",
                            "$newYearCode-11-28",
                            $userId,
                        ]
                    );
                    $newYearId = $db->lastInsertId();

                    // Create 3 terms
                    $terms = [
                        [1, "$newYearCode-01-06", "$newYearCode-04-04"],
                        [2, "$newYearCode-04-28", "$newYearCode-08-01"],
                        [3, "$newYearCode-08-25", "$newYearCode-11-28"],
                    ];
                    foreach ($terms as [$termNo, $start, $end]) {
                        $db->query(
                            "INSERT INTO academic_terms (academic_year_id, name, start_date, end_date, year, term_number, status)
                             VALUES (?, ?, ?, ?, ?, ?, 'upcoming')",
                            [$newYearId, "Term $termNo", $start, $end, $newYearCode, $termNo]
                        );
                    }
                    $result['new_year_id'] = $newYearId;
                    $result['new_year_code'] = $newYearCode;
                    $result['terms_created'] = 3;
                }

            } elseif ($step === 'archive_old_year') {
                $db->query(
                    "UPDATE academic_years SET status = 'archived', is_current = 0 WHERE id = ?",
                    [$currentYear['id']]
                );
                $db->query(
                    "INSERT INTO academic_year_archives
                     (academic_year, status, closure_initiated_by, closure_date)
                     VALUES (?, 'archived', ?, NOW())
                     ON DUPLICATE KEY UPDATE status = 'archived', archived_at = NOW()",
                    [$currentYear['year_code'], $userId]
                );
                $result['archived_year'] = $currentYear['year_code'];

            } elseif ($step === 'activate_new_year') {
                $newYearCode = (int)$currentYear['year_code'] + 1;
                $newYear = $db->query("SELECT id FROM academic_years WHERE year_code = ?", [$newYearCode])->fetch();
                if (!$newYear) return $this->error("New year $newYearCode not created yet. Run 'create_new_year' first.");

                $db->query("UPDATE academic_years SET is_current = 0");
                $db->query(
                    "UPDATE academic_years SET is_current = 1, status = 'active' WHERE id = ?",
                    [$newYear['id']]
                );
                $db->query(
                    "UPDATE academic_terms SET status = 'current' WHERE academic_year_id = ? AND term_number = 1",
                    [$newYear['id']]
                );
                $result['activated_year'] = $newYearCode;
            }

            // Log the rollover step
            $db->query(
                "INSERT INTO academic_year_rollover_log
                 (rollover_id, from_year_id, step, status, fee_balances_carried,
                  credit_notes_created, staff_reassigned, performed_by)
                 VALUES (?, ?, ?, 'completed', ?, ?, ?, ?)",
                [
                    $rolloverRef,
                    $currentYear['id'],
                    $step,
                    $result['fee_balances_carried'] ?? 0,
                    $result['credit_notes_created'] ?? 0,
                    $result['staff_to_reassign'] ?? 0,
                    $userId,
                ]
            );

            return $this->success($result);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }
}
