<?php

namespace App\API\Controllers;

use App\API\Modules\academic\AcademicAPI;
use App\API\Modules\academic\AcademicManager;
use App\API\Modules\academic\AcademicExamService;
use App\API\Modules\academic\AcademicReportService;
use App\API\Modules\academic\AcademicCurriculumService;
use App\API\Modules\academic\AcademicYearService;
use App\API\Modules\students\StudentProfileManager;
use App\API\Modules\students\FamilyGroupsManager;
use PDO;
use App\API\Services\DirectorAnalyticsService;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\StaffTeachingAssignmentService;
use App\API\Services\ReportCardReleaseService;
use App\API\Services\AcademicContextService;
use App\API\Services\TeacherCurriculumScopeService;
use App\API\Services\CurriculumProposalService;
use App\API\Modules\academic\AcademicCohortProjectionService;
use RuntimeException;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use Exception;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

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
    private $staffAccess;
    private $teachingAssignments;
    private $api;
    private $contextService;
    private $cohortProjectionService;
    private AcademicManager $academicManager;
    private AcademicExamService $examService;
    private AcademicReportService $reportService;
    private AcademicCurriculumService $curriculumService;
    private AcademicYearService $yearService;
    private TeacherCurriculumScopeService $curriculumScopeService;
    private CurriculumProposalService $curriculumProposalService;

    public function __construct()
    {
        parent::__construct();
        $this->academicManager = $this->contract('App\API\Modules\academic\AcademicManager');
        $this->api = $this->contract('academic');
        $this->staffAccess = $this->contract('App\API\Services\StaffDomainAccessService', $this->user);
        $this->teachingAssignments = $this->contract('App\API\Services\StaffTeachingAssignmentService');

        // Initialize Academic Context Service
        $this->contextService = $this->contract('App\API\Services\AcademicContextService');

        // Initialize Cohort Projection Service (Admission Stage 5)
        $this->cohortProjectionService = $this->contract('App\API\Modules\academic\AcademicCohortProjectionService');
        $this->examService = $this->contract('App\API\Modules\academic\AcademicExamService', $this->api);
        $this->reportService = $this->contract('App\API\Modules\academic\AcademicReportService', $this->api);
        $this->curriculumService = $this->contract('App\API\Modules\academic\AcademicCurriculumService', $this->api);
        $this->yearService = $this->contract('App\API\Modules\academic\AcademicYearService', $this->api);
        $this->curriculumScopeService = $this->contract('App\API\Services\TeacherCurriculumScopeService', $this->db->getConnection());
        $this->curriculumProposalService = $this->contract('App\API\Services\CurriculumProposalService', 
            $this->db->getConnection(),
            $this->curriculumScopeService
        );
    }

    private function scopedCurriculumQuery(array $query): array
    {
        $yearId = isset($query['academic_year_id']) && $query['academic_year_id'] !== ''
            ? (int) $query['academic_year_id']
            : null;
        $scope = $this->curriculumScopeService->resolve(
            (int) $this->getUserId(),
            $this->getUserRoleIds(),
            $yearId
        );
        if (!empty($scope['restricted'])) {
            $query['_scope_contexts'] = $scope['contexts'] ?? [];
            $query['_scope_learning_area_ids'] = $scope['learning_area_ids'] ?? [];
            $query['_scope_academic_year_id'] = $scope['academic_year_id'] ?? null;
        }
        return $query;
    }

    /** GET /api/academic/teacher-curriculum-scope */
    public function getTeacherCurriculumScope($id = null, $data = [], $segments = [])
    {
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->success($this->curriculumScopeService->resolve(
            (int) $this->getUserId(),
            $this->getUserRoleIds(),
            !empty($query['academic_year_id']) ? (int) $query['academic_year_id'] : null
        ), 'Curriculum assignment scope retrieved');
    }

    /** GET /api/academic/curriculum-proposal-context */
    public function getCurriculumProposalContext($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        $pdo = $this->db->getConnection();
        $yearId = (int) ($_GET['academic_year_id'] ?? ($data['academic_year_id'] ?? 0));
        $scope = $this->curriculumScopeService->resolve((int) $this->getUserId(), $this->getUserRoleIds(), $yearId ?: null);
        $years = $pdo->query('SELECT id,year_code,year_name,status,is_current,start_date,end_date FROM academic_years ORDER BY start_date DESC')->fetchAll(PDO::FETCH_ASSOC);
        $terms = [];
        if (!empty($scope['academic_year_id'])) {
            $stmt = $pdo->prepare('SELECT ayt.id,ayt.academic_year_id,ayt.status,t.name term_name,t.id term_number FROM academic_year_terms ayt JOIN terms t ON t.id=ayt.term_id WHERE ayt.academic_year_id=? ORDER BY t.id');
            $stmt->execute([(int) $scope['academic_year_id']]);
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        if (!empty($scope['restricted'])) {
            $areaIds = array_map('intval', $scope['learning_area_ids'] ?? []);
            $areas = [];
            if ($areaIds) {
                $marks = implode(',', array_fill(0, count($areaIds), '?'));
                $stmt = $pdo->prepare("SELECT id,name,code,level_band,description,status,levels,is_optional FROM learning_areas WHERE id IN ($marks) ORDER BY name");
                $stmt->execute($areaIds); $areas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $areas = $pdo->query('SELECT id,name,code,level_band,description,status,levels,is_optional FROM learning_areas ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        }
        return $this->success(['scope' => $scope, 'years' => $years, 'terms' => $terms, 'learning_areas' => $areas]);
    }

    private function curriculumProposalRoleGuard()
    {
        if (!array_intersect([4, 5, 6, 7, 8, 9], $this->getUserRoleIds())) {
            return $this->forbidden('Curriculum proposals are available only to academic staff.');
        }
        return null;
    }

    /** GET /api/academic/curriculum-proposals */
    public function getCurriculumProposals($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success($this->curriculumProposalService->list(
                $filters, (int) $this->getUserId(), in_array(4, $this->getUserRoleIds(), true)
            ), 'Curriculum proposals retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[Curriculum proposals] ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/academic/curriculum-proposals */
    public function postCurriculumProposals($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        try {
            return $this->success($this->curriculumProposalService->saveDraft(
                is_array($data) ? $data : [], (int) $this->getUserId(), $this->getUserRoleIds()
            ), 'Curriculum proposal saved as draft');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** PUT /api/academic/curriculum-proposals/{id} */
    public function putCurriculumProposals($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        if (!$id) return $this->badRequest('Proposal ID is required.');
        try {
            return $this->success($this->curriculumProposalService->saveDraft(
                is_array($data) ? $data : [], (int) $this->getUserId(), $this->getUserRoleIds(), (int) $id
            ), 'Curriculum proposal draft updated');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/academic/curriculum-proposals-submit */
    public function postCurriculumProposalsSubmit($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        $proposalId = (int) ($id ?: ($data['proposal_id'] ?? 0));
        if (!$proposalId) return $this->badRequest('Proposal ID is required.');
        try {
            return $this->success($this->curriculumProposalService->submit($proposalId, (int) $this->getUserId()), 'Proposal submitted for School Administrator review');
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/academic/curriculum-proposals-review */
    public function postCurriculumProposalsReview($id = null, $data = [], $segments = [])
    {
        if (!in_array(4, $this->getUserRoleIds(), true)) {
            return $this->forbidden('Only the School Administrator can approve or reject curriculum changes.');
        }
        $proposalId = (int) ($id ?: ($data['proposal_id'] ?? 0));
        if (!$proposalId) return $this->badRequest('Proposal ID is required.');
        try {
            return $this->success($this->curriculumProposalService->review(
                $proposalId, (string) ($data['decision'] ?? ''), (string) ($data['review_notes'] ?? ''), (int) $this->getUserId()
            ), 'Curriculum review recorded');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** GET /api/academic/curriculum-history */
    public function getCurriculumHistory($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->curriculumProposalRoleGuard()) return $guard;
        try {
            return $this->success($this->curriculumProposalService->history(
                array_merge($_GET, is_array($data) ? $data : []), (int) $this->getUserId(), $this->getUserRoleIds()
            ), 'Curriculum history retrieved');
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
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
                'delete' => '/api/academic/{id} (DELETE)',
                'context' => '/api/academic/context (GET)'
            ],
            'health' => 'ok',
            'timestamp' => date('c')
        ]);
    }

    /**
     * GET /api/academic/context - Get current academic context
     * Returns current academic year, term, and operational status
     */
    public function getContext()
    {
        try {
            $context = $this->contextService->getCurrentContext();
            return $this->success($context);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /** GET /api/academic/teacher-planning-context */
    public function getTeacherPlanningContext($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getTeacherPlanningContext());
    }

    /** GET /api/academic/lesson-planning-context/{schemeId} */
    public function getLessonPlanningContext($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getLessonPlanningContext($id ?? ($data['id'] ?? 0)));
    }

    public function postLessonPlanLearnerEvidence($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->saveLessonPlanLearnerEvidence($id ?? ($data['lesson_plan_id'] ?? 0), $data));
    }

    public function getLegacyContentReconciliation($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->getLegacyContentReconciliation()); }

    public function postResolveLegacyAcademicContent($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->resolveLegacyAcademicContent($data)); }

    public function postSchemeOfWorkBatch($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->createSchemeOfWorkBatch($data)); }

    public function postSchemeWorkbookSave($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->saveSchemeWorkbook($data)); }

    public function postSchemeWorkbookSubmit($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->submitSchemeWorkbook($data)); }

    public function postSchemeWorkbookRequestRevision($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->requestSchemeWorkbookRevision((int)($id ?? ($data['workbook_id'] ?? 0)), $data)); }

    public function postSchemeWorkbookReopenRevision($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->reopenSchemeWorkbookRevision((int)($id ?? ($data['workbook_id'] ?? 0)), $data)); }

    public function postSchemeWorkbookApprove($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->approveSchemeWorkbook((int)($id ?? ($data['workbook_id'] ?? 0)), $data)); }

    public function getSchemeWorkbook($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->getSchemeWorkbook((int)$id)); }

    /** Compatibility wrapper for the explicit /scheme-workbook-get/{id} route. */
    public function getSchemeWorkbookGet($id = null, $data = [], $segments = [])
    { return $this->handleResponse($this->api->getSchemeWorkbook((int)$id)); }

    /**
     * GET /api/academics/kpis
     * Director/CEO-only: Academic performance KPIs.
     * NOTE: routes under /api/academics/* dispatch to AcademicController, so this
     * method lives here (not DashboardController) to be reachable from the router.
     * Router builds the method name "getKpis" from the "kpis" resource segment.
     */
    public function getKpis($id = null, $data = [], $segments = [])
    {
        if (!$this->hasRoleId(3)) {
            return $this->forbidden('Director access only');
        }
        try {
            $analytics = $this->contract('App\API\Services\DirectorAnalyticsService');
            $kpis = $analytics->getAcademicKPIs();

            return $this->success([
                'kpis' => $kpis
            ], 'Academic KPIs retrieved');

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/academics/performance-matrix
     * Director/CEO-only: Performance heatmap data.
     * Router builds "getPerformanceMatrix" from the "performance-matrix" resource.
     */
    public function getPerformanceMatrix($id = null, $data = [], $segments = [])
    {
        if (!$this->hasRoleId(3)) {
            return $this->forbidden('Director access only');
        }
        try {
            $analytics = $this->contract('App\API\Services\DirectorAnalyticsService');
            $matrix = $analytics->getPerformanceMatrix();

            return $this->success([
                'data' => $matrix
            ], 'Performance matrix retrieved');

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }


    // ==================== ADMISSION STAGE 5: COHORT CAPACITY PROJECTION ====
    // GET /api/academic/cohort-capacity
    //     ?target_academic_year_id=7&target_term_id=19&target_class_id=4
    //     [&target_stream_id=12][&applied_academic_year=2027]
    // Period-aware, cohort-aware capacity projection for a target
    // class/stream in a (possibly future) academic year.
    public function getCohortCapacity($id = null, $data = [], $segments = [])
    {
        $targetYearId = isset($data['target_academic_year_id'])
            ? (int) $data['target_academic_year_id'] : null;
        $targetTermId = isset($data['target_term_id']) && $data['target_term_id'] !== ''
            ? (int) $data['target_term_id'] : null;
        $targetClassId = isset($data['target_class_id'])
            ? (int) $data['target_class_id'] : null;
        $targetStreamId = isset($data['target_stream_id']) && $data['target_stream_id'] !== ''
            ? (int) $data['target_stream_id'] : null;
        $appliedYear = isset($data['applied_academic_year']) && $data['applied_academic_year'] !== ''
            ? (int) $data['applied_academic_year'] : null;

        if (!$targetYearId || !$targetClassId) {
            \App\API\Services\Logger::legacyError('[AcademicController] getCohortProjection: target year/class required');
            return $this->error('Target academic year and class are required.');
        }

        $result = $this->cohortProjectionService->projectClassCapacity(
            $targetYearId,
            $targetTermId,
            $targetClassId,
            $targetStreamId,
            $appliedYear
        );
        return $this->handleResponse($result);
    }

    // GET /api/academic/cohort-projection?application_id=8
    // Projects capacity for a specific admission application.
    public function getCohortProjection($id = null, $data = [], $segments = [])
    {
        $applicationId = isset($data['application_id'])
            ? (int) $data['application_id'] : null;
        if (!$applicationId) {
            \App\API\Services\Logger::legacyError('[AcademicController] getCohortProjection: application_id required');
            return $this->error('application_id is required.');
        }
        $result = $this->cohortProjectionService->projectCapacityForApplication($applicationId);
        return $this->handleResponse($result);
    }

    // ==================== TEACHING RESOURCES ====================
    // GET /api/academic/resources?type=material|past_paper[&class_id=&subject_id=&term_id=&q=]
    // Unified listing across teaching_materials and past_papers (all statuses).
    public function getResources($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getResources($data));
    }

    // POST /api/academic/resources  (multipart FormData upload to teaching_materials)
    // Fields: file, title, subject_id, class, type, term, description
    public function postResources($id = null, $data = [], $segments = [])
    {
        $payload = array_merge($_POST, is_array($data) ? $data : []);
        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        $userId = (int) ($this->user['id'] ?? $this->user['user_id'] ?? 0);
        return $this->handleResponse($this->academicManager->postResources($payload, $file, $userId));
    }

    // GET /api/academic/resources/{id}/download  — serve the file (browser must hit this directly)
    public function getResourcesDownload($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->error('Resource id is required.');
        }
        $result = $this->academicManager->getResourceDownloadMeta((int) $id);
        if (($result['success'] ?? false) !== true) {
            return $this->handleResponse($result);
        }
        $row = $result['data'] ?? [];
        $abs = $row['absolute_path'] ?? '';

        header('Content-Type: ' . ($row['file_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . basename($row['file_name'] ?: 'download') . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }


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
     * GET /api/academic/levels-list - List active school levels
     */
    public function getLevelsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLevelsList($data);
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
    public function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
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
    public function requireAcademicWorkflowAccess(array $permissions = ['academic_manage', 'academic_approve'])
    {
        $aliases = [];
        foreach ($permissions as $permission) {
            $aliases[] = $permission;
            if (strpos($permission, 'academic_') === 0) {
                $aliases[] = 'academics_' . substr($permission, strlen('academic_'));
            }
        }

        if (!$this->userHasAny(array_values(array_unique($aliases)), [1, 3, 4, 5, 6], ['system admin', 'director', 'principal', 'headteacher', 'deputy head - academic'])) {
            return $this->forbidden('You do not have permission to perform this academic workflow action');
        }

        return null;
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
        return $this->examService->postExamsStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/exams/create-schedule - Create exam schedule
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsCreateSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

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
    // TODO: Delegate to AcademicExamService
    public function postExamsSubmitQuestions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

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
    // TODO: Delegate to AcademicExamService
    public function postExamsPrepareLogistics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->prepareExamLogistics($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/conduct - Conduct examination
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsConduct($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->conductExamination($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/assign-marking - Assign exam marking
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsAssignMarking($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

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
    // TODO: Delegate to AcademicExamService
    public function postExamsRecordMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

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
    // TODO: Delegate to AcademicExamService
    public function postExamsVerifyMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->verifyExamMarks($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/moderate-marks - Moderate exam marks
     */
    // TODO: Delegate to AcademicExamService
    public function postExamsModerateMarks($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

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
    // TODO: Delegate to AcademicExamService
    public function postExamsCompileResults($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->compileExamResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/exams/approve-results - Approve exam results (Director/academic_approve)
     * Body: { instance_id, approved (bool, default true), comments }
     */
    // TODO: Delegate to AcademicExamService
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

    /** Backward-compatible plural alias used by the exam schedule route. */
    public function getExamSchedules($id = null, $data = [], $segments = [])
    {
        return $this->getExamSchedule($id, $data, $segments);
    }

    /**
     * GET /api/academic/exam-result-entry/{examScheduleId}
     * Returns the exact published assessment, enrolled learners and saved marks.
     * The service applies the authenticated teacher's stream/learning-area scope.
     */
    public function getExamResultEntry($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['assessments_view', 'assessments_edit', 'academic_edit', 'academics_edit'],
            [1, 4, 5, 6, 7, 8],
            ['system administrator', 'school administrator', 'headteacher', 'deputy head - academic', 'class teacher', 'subject teacher']
        )) {
            return $this->forbidden('You do not have permission to enter or review exam results');
        }
        $examScheduleId = (int) ($id ?? ($data['exam_schedule_id'] ?? 0));
        if ($examScheduleId <= 0) {
            return $this->badRequest('exam_schedule_id is required');
        }
        return $this->handleResponse($this->api->getExamResultEntry($examScheduleId));
    }

    /**
     * POST /api/academic/exam-schedule - Create new exam schedule
     * Router calls: postExamSchedule(null, $data, $segments)
     */
    public function postExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->createExamScheduleEntry($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/exam-schedule/{id} - Update exam schedule
     * Router calls: putExamSchedule($id, $data, $segments)
     */
    public function putExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

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
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage'])) {
            return $guard;
        }

        if ($id === null) {
            return $this->badRequest('Exam schedule ID is required for deletion');
        }
        $result = $this->api->deleteExamScheduleEntry($id);
        return $this->handleResponse($result);
    }

    // ==================== SUPERVISION ROSTER CRUD ====================
    // URLs: GET/POST/PUT/DELETE /api/academic/supervision-roster
    //       POST /api/academic/supervision-roster-auto-generate
    // Used by: js/pages/supervision_roster.js

    /**
     * GET /api/academic/supervision-roster - List supervision roster
     * GET /api/academic/supervision-roster/{id} - Get single entry
     * Router calls: getSupervisionRoster($id, $data, $segments)
     */
    public function getSupervisionRoster($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $result = $this->api->getSupervisionRosterById($id);
        } else {
            $result = $this->api->listSupervisionRosters($data);
        }
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/supervision-roster - Create supervision roster entry
     * Router calls: postSupervisionRoster(null, $data, $segments)
     */
    public function postSupervisionRoster($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->createSupervisionRoster($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/academic/supervision-roster/{id} - Update supervision roster entry
     * Router calls: putSupervisionRoster($id, $data, $segments)
     */
    public function putSupervisionRoster($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        if ($id === null) {
            return $this->badRequest('Supervision roster ID is required for update');
        }
        $result = $this->api->updateSupervisionRoster($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/academic/supervision-roster/{id} - Delete supervision roster entry
     * Router calls: deleteSupervisionRoster($id, $data, $segments)
     */
    public function deleteSupervisionRoster($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage'])) {
            return $guard;
        }

        if ($id === null) {
            return $this->badRequest('Supervision roster ID is required for deletion');
        }
        $result = $this->api->deleteSupervisionRoster($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/supervision-roster-auto-generate - Auto-generate roster
     * for the current term's unassigned upcoming exam schedules.
     * Router calls: postSupervisionRosterAutoGenerate(null, $data, $segments)
     */
    public function postSupervisionRosterAutoGenerate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $result = $this->api->autoGenerateSupervisionRoster($data);
        return $this->handleResponse($result);
    }

    // ==================== PROMOTION WORKFLOW ====================

    /**
     * POST /api/academic/promotions/start-workflow - Start promotion workflow
     */
    public function postPromotionsStartWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startPromotionWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/identify-candidates - Identify promotion candidates
     */
    public function postPromotionsIdentifyCandidates($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $result = $this->api->identifyPromotionCandidates($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/promotions/validate-eligibility - Validate promotion eligibility
     */
    public function postPromotionsValidateEligibility($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

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
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'students_promote'])) {
            return $guard;
        }

        $result = $this->api->generatePromotionReports($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ASSESSMENT WORKFLOW ====================

    /**
     * POST /api/academic/assessments/start-workflow - Start assessment workflow
     */
    public function postAssessmentsStartWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

        $payload = is_array($data) ? $data : [];
        $result = $this->api->startAssessmentWorkflow($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/assessments/create-items - Create assessment items
     */
    public function postAssessmentsCreateItems($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

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
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_edit'])) {
            return $guard;
        }

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
        if (!$this->userHasAny(
            ['assessments_edit', 'academic_edit', 'academics_edit'],
            [1, 4, 5, 6, 7, 8],
            ['system administrator', 'school administrator', 'headteacher', 'deputy head - academic', 'class teacher', 'subject teacher']
        )) {
            return $this->forbidden('You do not have permission to enter assessment results');
        }

        $instanceId = $data['instance_id'] ?? null;
        $assessmentId = $data['assessment_id'] ?? null;
        $gradingData = $data['grading_data'] ?? $data['marks_data'] ?? $data['marks'] ?? [];

        // Prefer direct mode when no workflow instance is provided.
        if (empty($instanceId) && !empty($assessmentId)) {
            $result = $this->api->saveAssessmentResults([
                'assessment_id' => (int) $assessmentId,
                'marks' => $gradingData,
                'is_final' => (bool) ($data['is_final'] ?? false),
                'reason' => $data['reason'] ?? '',
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
        if ($guard = $this->requireAcademicWorkflowAccess()) {
            return $guard;
        }

        $result = $this->api->analyzeAssessmentResults($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== REPORT WORKFLOW ====================

    /**
     * POST /api/academic/reports/start-workflow - Start report workflow
     */
    public function postReportsStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->reportService->postReportsStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/reports/compile-data - Compile report data
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsCompileData($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        $result = $this->api->compileReportData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/reports/generate-student-reports - Generate student reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsGenerateStudentReports($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        if (!empty($data['instance_id'])) {
            return $this->badRequest('Legacy workflow instances cannot generate official report cards. Submit term_id with student_ids or class_id.');
        }

        $termId = (int) ($data['term_id'] ?? 0);
        if (!$termId) return $this->badRequest('term_id is required');
        $studentIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['student_ids'] ?? [])))));
        if (!$studentIds && !empty($data['class_id'])) {
            $pdo = $this->db->getConnection();
            $stmt = $pdo->prepare(
                "SELECT DISTINCT sae.student_id
                 FROM student_academic_enrollments sae
                 JOIN academic_year_class_streams aycs ON aycs.id=sae.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
                 JOIN academic_year_terms ayt ON ayt.academic_year_id=ayc.academic_year_id
                 WHERE ayt.id=? AND (ayc.class_id=? OR aycs.id=?)
                   AND sae.enrollment_status IN ('pending','active')"
            );
            $stmt->execute([$termId, (int) $data['class_id'], (int) $data['class_id']]);
            $studentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }
        if (!$studentIds) return $this->badRequest('student_ids or class_id is required');

        $service = $this->contract('App\API\Services\ReportCardReleaseService', $this->db->getConnection());
        $actor = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
        $generated = [];
        $failed = [];
        foreach ($studentIds as $studentId) {
            try {
                $payload = $this->academicManager->getReportCardData($studentId, $termId);
                if (($payload['status'] ?? '') !== 'success' || !is_array($payload['data'] ?? null)) {
                    throw new RuntimeException($payload['message'] ?? 'Report card data is unavailable');
                }
                $generated[] = $service->generate($payload['data'], $actor);
            } catch (\Throwable $e) {
                $failed[] = ['student_id' => $studentId, 'message' => $e->getMessage()];
            }
        }
        return $this->success([
            'generated' => $generated,
            'failed' => $failed,
            'generated_count' => count($generated),
            'failed_count' => count($failed),
        ], count($failed) ? 'Report card generation completed with exceptions' : 'Report cards generated for approval');
    }

    /**
     * POST /api/academic/reports/review-and-approve - Review and approve reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsReviewAndApprove($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        if (!empty($data['release_id'])) {
            try {
                $service = $this->contract('App\API\Services\ReportCardReleaseService', $this->db->getConnection());
                $actor = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
                return $this->success($service->approve((int) $data['release_id'], $actor), 'Report card approved');
            } catch (RuntimeException $e) {
                return $e->getCode() === 404 ? $this->notFound($e->getMessage()) : $this->badRequest($e->getMessage());
            }
        }
        return $this->badRequest('release_id is required; legacy workflow approval is disabled for official report cards');
    }

    /**
     * POST /api/academic/reports/distribute - Distribute reports
     */
    // TODO: Delegate to AcademicReportService
    public function postReportsDistribute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) {
            return $guard;
        }

        if (!empty($data['release_id'])) {
            try {
                $service = $this->contract('App\API\Services\ReportCardReleaseService', $this->db->getConnection());
                $actor = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
                $channels = $data['channels'] ?? ['sms', 'email', 'whatsapp'];
                return $this->success(
                    $service->release((int) $data['release_id'], $actor, is_array($channels) ? $channels : [$channels]),
                    'Report card released to parents and guardians'
                );
            } catch (RuntimeException $e) {
                return $e->getCode() === 404 ? $this->notFound($e->getMessage()) : $this->badRequest($e->getMessage());
            }
        }
        return $this->badRequest('release_id is required; report cards must be approved before guardian distribution');
    }

    /** GET /api/academic/report-card-releases — latest immutable release per learner/term. */
    public function getReportCardReleases($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            if ($id !== null) $filters['student_id'] = (int) $id;
            $service = $this->contract('App\API\Services\ReportCardReleaseService', $this->db->getConnection());
            return $this->success($service->list($filters));
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] report card releases: ' . $e->getMessage());
            return $this->serverError('Unable to load report card releases');
        }
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
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->reviewLibraryRequest($data['instance_id'] ?? null, $data['decision'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/catalog-resources - Catalog library resources
     */
    public function postLibraryCatalogResources($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->catalogLibraryResources($data['instance_id'] ?? null, $data['resources'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/library/distribute-and-track - Distribute and track resources
     */
    public function postLibraryDistributeAndTrack($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'library_manage'])) {
            return $guard;
        }

        $result = $this->api->distributeAndTrackResources($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== CURRICULUM WORKFLOW ====================

    /**
     * POST /api/academic/curriculum/start-workflow - Start curriculum workflow
     */
    public function postCurriculumStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->postCurriculumStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/curriculum/map-outcomes - Map curriculum outcomes
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumMapOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) {
            return $guard;
        }

        $result = $this->api->mapCurriculumOutcomes($data['instance_id'] ?? null, $data['mappings'] ?? [], $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/create-scheme - Create curriculum scheme
     */
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumCreateScheme($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) {
            return $guard;
        }

        // Assuming createCurriculumScheme expects ($instanceId, $data)
        $result = $this->api->createCurriculumScheme($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/curriculum/review-and-approve - Review and approve curriculum (Director only)
     * Body: { instance_id, action (approve|reject), comments }
     */
    // TODO: Delegate to AcademicCurriculumService
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
            [1, 3, 4, 5, 6],
            ['director', 'system admin', 'school administrator', 'head teacher', 'deputy head']
        )) {
            return $this->forbidden('You do not have permission to start year transition workflows');
        }
        return $this->yearService->postYearTransitionStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/year-transition/archive-data - Archive academic data
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionArchiveData($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->archiveAcademicData($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/execute-promotions - Execute year promotions
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionExecutePromotions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'students_promote', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->executeYearPromotions($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /** GET /api/academic/year-transition/promotion-candidates */
    public function getYearTransitionPromotionCandidates($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'students_promote', 'system_admin'])) return $guard;
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        return $this->handleResponse($this->api->getYearPromotionCandidates($instanceId));
    }

    /** POST /api/academic/year-transition/assign-promotion-streams */
    public function postYearTransitionAssignPromotionStreams($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'students_promote', 'system_admin'])) return $guard;
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        return $this->handleResponse($this->api->assignYearPromotionStreams($instanceId, $data));
    }

    /** POST /api/academic/year-transition/complete-stage */
    public function postYearTransitionCompleteStage($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) return $guard;
        $instanceId = $data['instance_id'] ?? ($id ?? null);
        return $this->handleResponse($this->api->completeYearTransitionStage($instanceId, $data));
    }

    /**
     * POST /api/academic/year-transition/setup-new-year - Setup new academic year (Director only)
     * Body: { instance_id, year_id (optional), class_structures[], clone_subjects, clone_staff_assignments }
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionSetupNewYear($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(
            ['academic_year_manage', 'system_admin'],
            [1, 3, 4, 5, 6],
            ['director', 'system admin', 'school administrator', 'head teacher', 'deputy head']
        )) {
            return $this->forbidden('You do not have permission to setup the new academic year');
        }

        $instanceId = $data['instance_id'] ?? ($id ?? null);
        $yearConfig = $data['year_config'] ?? $data;
        $result = $this->api->setupNewAcademicYear($instanceId, $yearConfig);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/migrate-competency-baselines - Migrate competency baselines
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionMigrateCompetencyBaselines($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->migrateCompetencyBaselines($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/year-transition/validate-readiness - Validate year readiness
     */
    // TODO: Delegate to AcademicYearService
    public function postYearTransitionValidateReadiness($id = null, $data = [], $segments = [])
    {
        $result = $this->api->validateYearReadiness($data['instance_id'] ?? null, $data);
        return $this->handleResponse($result);
    }

    // ==================== ACADEMIC CALENDAR ====================

    /**
     * POST /api/academic/calendar/generate - Generate/regenerate term calendar
     * Body: { year_id, week_counts: {1:14, 2:14, 3:10} } (week_counts optional)
     */
    public function postCalendarGenerate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $yearId = $data['year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->generateAcademicCalendar((int) $yearId, $data['week_counts'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/calendar/get/{year_id} - Get generated calendar summary
     */
    public function getCalendarGet($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? ($data['year_id'] ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->getAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/calendar/list - Get calendar summary for a year
     * Query: ?year_id= (defaults to current year)
     */
    public function getCalendarList($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? ($data['year_id'] ?? null);
        if (!$yearId) {
            $yearId = $this->academicManager->resolveCurrentAcademicYearId();
        }
        if (!$yearId) {
            return $this->badRequest('No academic year found');
        }

        $result = $this->api->getAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/calendar/validate - Readiness check for the calendar
     * Body: { year_id }
     */
    public function postCalendarValidate($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->validateAcademicCalendar((int) $yearId);
        return $this->handleResponse($result);
    }

    // ==================== LEARNING AREAS ====================

    /**
     * POST /api/academic/learning-areas/seed - Seed per-class CBC learning areas
     * Body: { academic_year_id }
     */
    public function postLearningAreasSeed($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $yearId = $data['academic_year_id'] ?? ($id ?? null);
        if (!$yearId) {
            return $this->badRequest('academic_year_id is required');
        }

        $result = $this->api->seedAcademicLearningAreas(['academic_year_id' => (int) $yearId]);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/learning-areas/coverage/{ayc_id} - Class curriculum coverage
     */
    public function getLearningAreasCoverage($id = null, $data = [], $segments = [])
    {
        $aycId = $id ?? ($data['academic_year_class_id'] ?? null);
        if (!$aycId) {
            return $this->badRequest('academic_year_class_id is required');
        }

        $result = $this->api->getClassLearningAreaCoverage(['academic_year_class_id' => (int) $aycId]);
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
    // TODO: Delegate to AcademicYearService
    public function getYearsList($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsList($id, $data, $segments, $this);
    }

    /**
     * GET /api/academic/years/current - Get current academic year
     */
    // TODO: Delegate to AcademicYearService
    public function getYearsCurrent($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsCurrent($id, $data, $segments, $this);
    }

    /**
     * GET /api/academic/years/get/{id} - Get academic year by ID
     */
    // TODO: Delegate to AcademicYearService
    public function getYearsGet($id = null, $data = [], $segments = [])
    {
        return $this->yearService->getYearsGet($id, $data, $segments, $this);
    }

    /**
     * POST /api/academic/years/create - Create academic year
     */
    // TODO: Delegate to AcademicYearService
    public function postYearsCreate($id = null, $data = [], $segments = [])
    {
        return $this->yearService->postYearsCreate($id, $data, $segments, $this);
    }

    /**
     * PUT /api/academic/years/update/{id} - Update academic year
     */
    // TODO: Delegate to AcademicYearService
    public function putYearsUpdate($id = null, $data = [], $segments = [])
    {
        return $this->yearService->putYearsUpdate($id, $data, $segments, $this);
    }

    /**
     * DELETE /api/academic/years/delete/{id} - Delete academic year
     */
    // TODO: Delegate to AcademicYearService
    public function deleteYearsDelete($id = null, $data = [], $segments = [])
    {
        return $this->yearService->deleteYearsDelete($id, $data, $segments, $this);
    }

    /**
     * PUT /api/academic/years/set-current - Set year as current (Director/System Admin only)
     * Accepts year_id from URL segment (/set-current/5) or from request body (year_id or id)
     */
    // TODO: Delegate to AcademicYearService
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
     * GET /api/academic/timetable-stats?term_id= - Timetable coverage for a term
     * (backed by the live timetable_entries table, unlike /schedules/timetable-get).
     */
    public function getTimetableStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getTimetableStats($data));
    }

    /**
     * GET /api/academic/term-transition/context - Normalized term transition context
     */
    public function getTermTransitionContext($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getTermTransitionContext($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/term-transition/execute - Atomic term transition
     */
    public function postTermTransitionExecute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }

        $result = $this->api->executeTermTransition($data);
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

    /**
     * GET /api/academic/terms - Bare alias of getTermsList.
     * manage_terms.js calls the bare `/academic/terms?academic_year_id=` route.
     */
    public function getTerms($id = null, $data = [], $segments = [])
    {
        return $this->getTermsList($id, $data, $segments);
    }

    /**
     * POST /api/academic/terms - Bare alias of postTermsCreate.
     * manage_terms.js POSTs a new term to the bare `/academic/terms` route.
     */
    public function postTerms($id = null, $data = [], $segments = [])
    {
        return $this->postTermsCreate($id, $data, $segments);
    }

    /**
     * PUT /api/academic/terms/{id} - Bare alias of putTermsUpdate.
     * manage_terms.js PUTs updates to `/academic/terms/{id}`.
     */
    public function putTerms($id = null, $data = [], $segments = [])
    {
        return $this->putTermsUpdate($id, $data, $segments);
    }

    // ==================== LEARNING AREAS (SUBJECTS) ====================

    /**
     * GET /api/academic/learning-areas/list - List all learning areas
     */
    public function getLearningAreasList($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        $result = $this->api->getLearningAreasList($query);
        if (isset($query['_scope_learning_area_ids']) && isset($result['data']) && is_array($result['data'])) {
            $allowed = array_flip(array_map('intval', $query['_scope_learning_area_ids']));
            $result['data'] = array_values(array_filter(
                $result['data'],
                static fn(array $area): bool => isset($allowed[(int) ($area['id'] ?? 0)])
            ));
        }
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/learning-areas - Alias for learning-areas-list.
     * Router convention: /academic/learning-areas → getLearningAreas().
     */
    public function getLearningAreas($id = null, $data = [], $segments = [])
    {
        return $this->getLearningAreasList($id, $data, $segments);
    }

    /**
     * GET /api/academic/subjects-list - Alias for learning-areas-list.
     * The frontend consistently calls subjects "subjects" while the data model
     * and API name them "learning_areas". This adapter keeps the UI contract
     * stable without re-pointing 15+ call sites, and resolves the slug that the
     * router previously fell through to the generic get() (subjects-list -> getSubjectList).
     */
    public function getSubjectsList($id = null, $data = [], $segments = [])
    {
        return $this->getLearningAreasList($id, $data, $segments);
    }

    public function getSubjects($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->getLearningAreasGet($id, $data, $segments);
        }

        return $this->getLearningAreasList($id, $data, $segments);
    }

    /**
     * GET /api/academic/learning-areas/get/{id} - Get specific learning area
     */
    public function getLearningAreasGet($id = null, $data = [], $segments = [])
    {
        $areaId = (int) ($id ?? ($data['id'] ?? 0));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        $scope = $this->curriculumScopeService->resolve(
            (int) $this->getUserId(),
            $this->getUserRoleIds(),
            !empty($query['academic_year_id']) ? (int) $query['academic_year_id'] : null
        );
        if (!empty($scope['restricted']) && !in_array($areaId, array_map('intval', $scope['learning_area_ids'] ?? []), true)) {
            return $this->forbidden('This learning area is not assigned to you for the selected academic year.');
        }
        $result = $this->api->get($areaId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/learning-areas/create - Create learning area
     */
    public function postLearningAreasCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    public function postSubjects($id = null, $data = [], $segments = [])
    {
        return $this->postLearningAreasCreate($id, $data, $segments);
    }

    /**
     * PUT /api/academic/learning-areas/update/{id} - Update learning area
     */
    public function putLearningAreasUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Learning-area changes must use Curriculum Proposals so they are reviewed and versioned.');
    }

    public function putSubjects($id = null, $data = [], $segments = [])
    {
        return $this->putLearningAreasUpdate($id, $data, $segments);
    }

    /**
     * DELETE /api/academic/learning-areas/delete/{id} - Delete learning area
     */
    public function deleteLearningAreasDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Learning areas are retired through Curriculum Proposals, never directly deleted.');
    }

    public function deleteSubjects($id = null, $data = [], $segments = [])
    {
        return $this->deleteLearningAreasDelete($id, $data, $segments);
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
     * GET /api/academic/class-capacity - Stream-level capacity and enrollment
     */
    public function getClassCapacity($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getClassCapacity($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/academic/assessments-list - List assessments with submission stats
     */
    public function getAssessmentsList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getAssessmentsList($filters));
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
     * GET /api/academic/performance-overview - Performance overview for student/class/stream/school views
     */
    public function getPerformanceOverview($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPerformanceOverview($data);
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
     * GET /api/academic/report-cards - Full report card payload for one student.
     * Accepts student_id (+ optional term_id) via query params. Returns normalized
     * subject rows, attendance, averages and grades consumable by the report-card
     * print/export flow. Mirrors getReportCardsDownload but sources ids from data.
     */
    public function getReportCards($id = null, $data = [], $segments = [])
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

    // ==================== CLASS STREAMS (bare slug /academic/class-streams) ====================
    // manage_classes.js calls the BARE slug with CRUD verbs (no /list, /create sub-segments).
    // Router maps: GET -> getClassStreams, POST -> postClassStreams, PUT -> putClassStreams,
    // DELETE -> deleteClassStreams. These differ from the streams/* handlers (getStreamsList, etc.)
    // — the "streams" slug is a separate alias. Reuse the same AcademicAPI methods to avoid drift.

    public function getClassStreams($id = null, $data = [], $segments = [])
    {
        $classId = !empty($_GET['class_id']) ? (int) $_GET['class_id'] : ($data['class_id'] ?? $id ?? null);
        return $this->handleResponse($this->api->listClassStreams($classId));
    }

    public function postClassStreams($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? null;
        return $this->handleResponse($this->api->createStream($classId, $data));
    }

    public function putClassStreams($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        return $this->handleResponse($this->api->updateStream($streamId, $data));
    }

    public function deleteClassStreams($id = null, $data = [], $segments = [])
    {
        $streamId = $id ?? ($data['id'] ?? null);
        return $this->handleResponse($this->api->deleteStream($streamId));
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
    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnitsCreate($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->postCurriculumUnitsCreate($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function postCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->postCurriculumUnitsCreate($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsList($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->getCurriculumUnitsList($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnits($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->getCurriculumUnitsGet($id, $data, $segments);
        }

        return $this->getCurriculumUnitsList($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function getCurriculumUnitsGet($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->getCurriculumUnitsGet($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnitsUpdate($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->putCurriculumUnitsUpdate($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function putCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->putCurriculumUnitsUpdate($id, $data, $segments);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnitsDelete($id = null, $data = [], $segments = [])
    {
        return $this->curriculumService->deleteCurriculumUnitsDelete($id, $data, $segments, $this);
    }

    // TODO: Delegate to AcademicCurriculumService
    public function deleteCurriculumUnits($id = null, $data = [], $segments = [])
    {
        return $this->deleteCurriculumUnitsDelete($id, $data, $segments);
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

    public function postSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $data['created_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->postSchemeOfWorkCreate($id, $data, $segments);
    }

    /**
     * POST /api/academic/schemes-of-work/generate
     * Auto-generate weekly scheme-of-work entries from the seeded CBC curriculum
     * (strands -> sub-strands -> learning outcomes) for a selected learning area/class/term.
     */
    public function postSchemesOfWorkGenerate($id = null, $data = [], $segments = [])
    {
        $data['created_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->generateSchemeOfWork($data);
        return $this->handleResponse($result);
    }

    /** POST /api/academic/ai-scheme-draft-queue */
    public function postAiSchemeDraftQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiSchemeDraft()) return $this->forbidden('Academic curriculum permission is required');
        try {
            $input = $this->buildAiSchemeInput($data);
            $queued = $this->contract(AiDraftService::class)->queue(
                'academics.scheme_draft', $this->aiAcademicContext(), $input,
                ['subject_type' => 'academic_scheme_draft', 'subject_id' => 0, 'scope' => 'authorized_teacher_curriculum']
            );
            return $this->accepted($queued, 'CBC scheme draft queued for academic review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI scheme draft queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue CBC scheme assistance');
        }
    }

    /** GET /api/academic/ai-scheme-drafts?scope=own|review */
    public function getAiSchemeDrafts($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiSchemeDraft()) return $this->forbidden('Academic approval permission is required');
        try {
            return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview(
                $this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'academics'
            ), 'scope' => $review ? 'review' : 'own'], 'CBC AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI scheme draft list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load CBC scheme assistance');
        }
    }

    /** POST /api/academic/ai-scheme-draft-approve/{id} */
    public function postAiSchemeDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiSchemeDraft()) return $this->forbidden('Academic approval permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'academics.scheme_draft');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'CBC draft approved for teacher editing; existing academic approval remains required');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI scheme draft approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve CBC scheme assistance');
        }
    }

    /** POST /api/academic/ai-lesson-plan-draft-queue */
    public function postAiLessonPlanDraftQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiLessonPlanDraft()) return $this->forbidden('Academic lesson-planning permission is required');
        try {
            $schemeId = (int) ($data['scheme_of_work_id'] ?? $id ?? 0);
            if ($schemeId < 1) throw new DomainException('An approved scheme row is required.', 422);
            $input = $this->buildAiLessonPlanInput($schemeId, $data);
            $queued = $this->contract(AiDraftService::class)->queue(
                'academics.lesson_plan_draft', $this->aiAcademicContext(), $input,
                ['subject_type' => 'academic_lesson_plan_draft', 'subject_id' => $schemeId, 'scope' => 'approved_scheme_context']
            );
            return $this->accepted($queued, 'Lesson-plan draft queued for academic review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI lesson-plan queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue lesson-plan assistance');
        }
    }

    /** GET /api/academic/ai-lesson-plan-drafts?scope=own|review */
    public function getAiLessonPlanDrafts($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiLessonPlanDraft()) return $this->forbidden('Academic approval permission is required');
        try {
            return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview(
                $this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'academics'
            ), 'scope' => $review ? 'review' : 'own'], 'Lesson-plan AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI lesson-plan list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load lesson-plan assistance');
        }
    }

    /** POST /api/academic/ai-lesson-plan-draft-approve/{id} */
    public function postAiLessonPlanDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiLessonPlanDraft()) return $this->forbidden('Academic approval permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'academics.lesson_plan_draft');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Lesson-plan draft approved for teacher editing');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI lesson-plan approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve lesson-plan assistance');
        }
    }

    private function canUseAiLessonPlanDraft(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('academics.lesson_plan_draft', $this->aiAcademicContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canApproveAiLessonPlanDraft(): bool
    {
        return $this->canApproveAiSchemeDraft();
    }

    private function buildAiLessonPlanInput(int $schemeId, array $data): array
    {
        $context = $this->api->getLessonPlanningContext($schemeId);
        $payload = is_array($context) ? ($context['data'] ?? $context) : [];
        if (isset($payload['data']) && is_array($payload['data'])) $payload = $payload['data'];
        if (empty($payload['id']) || ($payload['status'] ?? '') !== 'approved') throw new DomainException('Only an approved scheme row can ground a lesson-plan draft.', 403);
        $choices = (array) ($payload['choices'] ?? []);
        $texts = static function (array $rows, array $keys = ['text', 'name']): array {
            $out = [];
            foreach ($rows as $row) { if (!is_array($row)) continue; foreach ($keys as $key) if (isset($row[$key]) && trim((string) $row[$key]) !== '') { $out[] = trim((string) $row[$key]); break; } }
            return array_slice(array_values(array_unique($out)), 0, 20);
        };
        return [
            'learning_area' => (string) ($payload['learning_area_name'] ?? ''),
            'grade_level' => (string) ($payload['class_name'] ?? ''),
            'term_name' => (string) ($payload['term_name'] ?? ''),
            'week_number' => (string) ($payload['week_number'] ?? ''),
            'strand' => (string) ($payload['strand_name'] ?? ''),
            'sub_strand' => (string) ($payload['sub_strand_name'] ?? ''),
            'scheme_title' => (string) ($payload['title'] ?? ''),
            'learning_outcomes' => $texts((array) ($choices['outcomes'] ?? [])),
            'learning_experiences' => $texts((array) ($choices['experiences'] ?? [])),
            'resources' => $texts((array) ($choices['resources'] ?? [])),
            'assessment_tools' => $texts((array) ($choices['assessment_tools'] ?? [])),
            'competencies' => $texts((array) ($choices['competencies'] ?? [])),
            'inquiry_questions' => $texts((array) ($choices['inquiry_questions'] ?? [])),
            'teacher_intent' => mb_substr(trim((string) ($data['teacher_intent'] ?? 'Create an inclusive lesson with clear activities and formative assessment.')), 0, 500),
        ];
    }

    /** POST /api/academic/ai-assessment-draft-queue */
    public function postAiAssessmentDraftQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiAssessmentDraft()) return $this->forbidden('Academic assessment permission is required');
        try {
            $outcomeId = (int) ($data['learning_outcome_id'] ?? 0);
            if ($outcomeId < 1) throw new DomainException('Select an authorized CBC learning outcome first.', 422);
            $query = $this->scopedCurriculumQuery(['learning_area_id' => (int) ($data['learning_area_id'] ?? 0)]);
            $outcomeResult = $this->academicManager->getLearningOutcomes($outcomeId, $query);
            $outcomePayload = is_array($outcomeResult) ? ($outcomeResult['data'] ?? $outcomeResult) : [];
            if (isset($outcomePayload['data']) && is_array($outcomePayload['data'])) $outcomePayload = $outcomePayload['data'];
            if (empty($outcomePayload['id'])) throw new DomainException('The selected learning outcome is outside your academic scope.', 403);
            $type = trim((string) ($data['assessment_type'] ?? ''));
            if ($type === '' || mb_strlen($type) > 100) throw new DomainException('A valid assessment type is required.', 422);
            $input = [
                'learning_area' => (string) ($outcomePayload['learning_area_name'] ?? ''),
                'grade_level' => (string) ($outcomePayload['grade_level'] ?? ''),
                'term_name' => mb_substr(trim((string) ($data['term_name'] ?? '')), 0, 100),
                'assessment_type' => $type,
                'max_marks' => (string) max(1, min(1000, (int) ($data['max_marks'] ?? 100))),
                'learning_outcome' => (string) ($outcomePayload['outcome'] ?? ''),
                'teacher_intent' => mb_substr(trim((string) ($data['teacher_intent'] ?? 'Create a fair, age-appropriate assessment with clear evidence of the outcome.')), 0, 500),
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'academics.assessment_draft', $this->aiAcademicContext(), $input,
                ['subject_type' => 'academic_assessment_draft', 'subject_id' => $outcomeId, 'scope' => 'authorized_cbc_outcome']
            );
            return $this->accepted($queued, 'Assessment draft queued for academic review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI assessment queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue assessment assistance');
        }
    }

    /** GET /api/academic/ai-assessment-drafts?scope=own|review */
    public function getAiAssessmentDrafts($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiAssessmentDraft()) return $this->forbidden('Academic approval permission is required');
        try {
            return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview(
                $this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'academics'
            ), 'scope' => $review ? 'review' : 'own'], 'Assessment AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI assessment list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load assessment assistance');
        }
    }

    /** POST /api/academic/ai-assessment-draft-approve/{id} */
    public function postAiAssessmentDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiAssessmentDraft()) return $this->forbidden('Academic approval permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'academics.assessment_draft');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Assessment draft approved for teacher editing');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] AI assessment approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve assessment assistance');
        }
    }

    /** POST /api/academic/ai-coverage-review-queue */
    public function postAiCoverageReviewQueue($id = null, $data = [], $segments = [])
    {
        try { $this->contract(AiWorkflowService::class)->authorize('academics.coverage_review', $this->aiAcademicContext()); }
        catch (DomainException $e) { return $this->forbidden('Academic curriculum permission is required'); }
        $classId = (int) ($data['academic_year_class_id'] ?? $id ?? 0);
        if ($classId < 1) return $this->badRequest('academic_year_class_id is required');
        try {
            $raw = $this->api->getClassLearningAreaCoverage(['academic_year_class_id' => $classId]);
            $rows = is_array($raw) ? ($raw['data']['learning_areas'] ?? $raw['learning_areas'] ?? []) : [];
            $planned = count($rows); $withCurriculum = 0;
            foreach ($rows as $row) if ((int) ($row['strand_count'] ?? 0) > 0 || (int) ($row['sub_strand_count'] ?? 0) > 0) $withCurriculum++;
            $input = ['report_date' => date('Y-m-d'), 'grade_level' => mb_substr(trim((string) ($data['grade_level'] ?? '')), 0, 100), 'learning_area' => mb_substr(trim((string) ($data['learning_area'] ?? 'All learning areas')), 0, 100), 'planned_count' => (string) $planned, 'completed_count' => (string) $withCurriculum, 'overdue_count' => '0', 'coverage_rate' => $planned > 0 ? (string) round(($withCurriculum / $planned) * 100, 2) : '0', 'unmapped_count' => (string) max(0, $planned - $withCurriculum), 'follow_up_intent' => 'Verify curriculum mapping and delivery records before taking action.'];
            $queued = $this->contract(AiDraftService::class)->queue('academics.coverage_review', $this->aiAcademicContext(), $input, ['subject_type' => 'academic_coverage_review', 'subject_id' => $classId, 'scope' => 'authorized_class_curriculum_aggregate']);
            return $this->accepted($queued, 'Curriculum coverage review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (Exception $e) { return $this->serverError('Unable to queue curriculum coverage review'); }
    }

    /** POST /api/academic/ai-rubric-draft-queue */
    public function postAiRubricDraftQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAcademicAi('academics.rubric_draft')) return $this->forbidden('Academic rubric permission is required');
        try {
            $outcomeId = (int) ($data['learning_outcome_id'] ?? 0);
            if ($outcomeId < 1) throw new DomainException('Select an authorized CBC learning outcome first.', 422);
            $result = $this->academicManager->getLearningOutcomes($outcomeId, $this->scopedCurriculumQuery([]));
            $outcome = is_array($result) ? ($result['data'] ?? $result) : [];
            if (isset($outcome['data']) && is_array($outcome['data'])) $outcome = $outcome['data'];
            if (empty($outcome['id'])) throw new DomainException('The selected learning outcome is outside your academic scope.', 403);
            $input = ['learning_area' => (string) ($outcome['learning_area_name'] ?? ''), 'grade_level' => (string) ($outcome['grade_level'] ?? ''), 'term_name' => mb_substr(trim((string) ($data['term_name'] ?? '')), 0, 100), 'learning_outcome' => (string) ($outcome['outcome'] ?? ''), 'criterion_count' => (string) max(1, min(12, (int) ($data['criterion_count'] ?? 4))), 'performance_levels' => mb_substr(trim((string) ($data['performance_levels'] ?? 'Beginning, Developing, Meeting, Exceeding')), 0, 300), 'teacher_intent' => mb_substr(trim((string) ($data['teacher_intent'] ?? 'Create observable, age-appropriate criteria.')), 0, 500)];
            return $this->accepted($this->contract(AiDraftService::class)->queue('academics.rubric_draft', $this->aiAcademicContext(), $input, ['subject_type' => 'academic_rubric_draft', 'subject_id' => $outcomeId, 'scope' => 'authorized_cbc_outcome']), 'CBC rubric draft queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (Exception $e) { return $this->serverError('Unable to queue rubric assistance'); }
    }

    /** POST /api/academic/ai-learning-gap-review-queue */
    public function postAiLearningGapReviewQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAcademicAi('academics.learning_gap_review')) return $this->forbidden('Academic learning-gap permission is required');
        try {
            // Request only the aggregate school/class view from the academic
            // source service; learner rows never enter the AI payload.
            $overview = $this->api->getPerformanceOverview(array_merge(['view_mode' => 'school'], $_GET, $data));
            $rows = (array) ($overview['rows'] ?? $overview['data'] ?? []); $below = 0; $missing = 0; $bands = ['below_50' => 0, '50_to_74' => 0, '75_plus' => 0];
            foreach ($rows as $row) { $score = $row['average_score'] ?? null; if ($score === null) { $missing++; continue; } $score = (float) $score; if ($score < 50) { $below++; $bands['below_50']++; } elseif ($score < 75) { $bands['50_to_74']++; } else $bands['75_plus']++; }
            $input = ['report_date' => date('Y-m-d'), 'grade_level' => mb_substr((string) ($data['grade_level'] ?? ''), 0, 100), 'learning_area' => mb_substr((string) ($data['learning_area'] ?? 'All learning areas'), 0, 100), 'learner_count' => (string) count($rows), 'assessment_count' => (string) count($rows), 'below_threshold_count' => (string) $below, 'missing_evidence_count' => (string) $missing, 'gap_bands' => json_encode($bands), 'follow_up_intent' => 'Verify authorized assessment evidence and provide teacher-led support; do not identify learners in the AI response.'];
            return $this->accepted($this->contract(AiDraftService::class)->queue('academics.learning_gap_review', $this->aiAcademicContext(), $input, ['subject_type' => 'academic_learning_gap_review', 'scope' => 'authorized_aggregate_performance']), 'Learning-gap review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (Exception $e) { return $this->serverError('Unable to queue learning-gap review'); }
    }

    private function canUseAcademicAi(string $workflow): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize($workflow, $this->aiAcademicContext()); return true; } catch (DomainException $e) { return false; }
    }

    public function postAiRubricDraftApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAcademicAiDraft($id, $data, $segments, 'academics.rubric_draft', 'rubric');
    }

    public function postAiCoverageReviewApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAcademicAiDraft($id, $data, $segments, 'academics.coverage_review', 'coverage');
    }

    public function postAiLearningGapReviewApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAcademicAiDraft($id, $data, $segments, 'academics.learning_gap_review', 'learning-gap');
    }

    private function approveAcademicAiDraft($id, array $data, array $segments, string $workflow, string $label): array
    {
        if (!$this->canApproveAiSchemeDraft()) return $this->forbidden('Academic approval permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), $workflow);
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], ucfirst($label) . ' review approved for teacher editing');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false); }
    }

    private function canUseAiAssessmentDraft(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('academics.assessment_draft', $this->aiAcademicContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canApproveAiAssessmentDraft(): bool
    {
        return $this->canApproveAiSchemeDraft();
    }

    private function aiAcademicContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? ''];
    }

    private function canUseAiSchemeDraft(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('academics.scheme_draft', $this->aiAcademicContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canApproveAiSchemeDraft(): bool
    {
        return $this->userHasAny(['academic_approve', 'academic_manage', 'academics_manage', 'curriculum_approve'], [3, 4, 5, 10], ['headteacher', 'deputy headteacher', 'admin', 'director']);
    }

    private function buildAiSchemeInput(array $data): array
    {
        $context = $this->api->getTeacherPlanningContext();
        $payload = is_array($context) ? ($context['data'] ?? $context) : [];
        if (isset($payload['data']) && is_array($payload['data'])) $payload = $payload['data'];
        $streamId = (int) ($data['stream_id'] ?? 0);
        $areaId = (int) ($data['learning_area_id'] ?? 0);
        if ($streamId < 1 || $areaId < 1) throw new DomainException('Select an authorized class stream and learning area.', 422);
        $stream = null;
        foreach ((array) ($payload['streams'] ?? []) as $candidate) if ((int) ($candidate['academic_year_class_stream_id'] ?? 0) === $streamId) { $stream = $candidate; break; }
        if (!$stream) throw new DomainException('The selected class stream is outside your academic scope.', 403);
        $area = null;
        foreach ((array) ($stream['learning_areas'] ?? []) as $candidate) if ((int) ($candidate['id'] ?? 0) === $areaId) { $area = $candidate; break; }
        if (!$area) throw new DomainException('The selected learning area is outside your academic scope.', 403);
        $rows = (array) ($payload['curriculum'][$streamId . ':' . $areaId] ?? []);
        $strandId = (int) ($data['strand_id'] ?? 0);
        $subStrandId = (int) ($data['sub_strand_id'] ?? 0);
        $rows = array_values(array_filter($rows, static fn(array $row): bool => (!$strandId || (int) ($row['strand_id'] ?? 0) === $strandId) && (!$subStrandId || (int) ($row['sub_strand_id'] ?? 0) === $subStrandId)));
        if (!$rows) throw new DomainException('No authorized CBC strands or sub-strands were found for the selection.', 422);
        $strands = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['strand_name'] ?? ''), $rows))));
        $subStrands = array_values(array_unique(array_filter(array_map(static fn(array $row): string => (string) ($row['sub_strand_name'] ?? ''), $rows))));
        $outcomes = [];
        foreach ($rows as $row) foreach ((array) ($row['learning_outcomes'] ?? []) as $outcome) $outcomes[] = (string) $outcome;
        return ['learning_area' => (string) ($area['name'] ?? ''), 'grade_level' => (string) ($stream['class_name'] ?? ''), 'term_name' => (string) ($payload['term_name'] ?? ''), 'instructional_weeks' => (string) count(array_filter((array) ($payload['weeks'] ?? []), static fn(array $week): bool => empty($week['is_reserved']))), 'strands' => array_slice($strands, 0, 20), 'sub_strands' => array_slice($subStrands, 0, 20), 'learning_outcomes' => array_slice(array_values(array_unique($outcomes)), 0, 20), 'teacher_intent' => mb_substr(trim((string) ($data['teacher_intent'] ?? 'Create a practical, learner-centred sequence with assessment opportunities.')), 0, 500)];
    }

    /**
     * GET /api/academic/scheme-of-work/get/{id} - Get scheme of work
     */
    public function getSchemeOfWorkGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSchemeOfWork($id ?? ($data['id'] ?? null));
        return $this->handleResponse($result);
    }

    public function getSchemesOfWork($id = null, $data = [], $segments = [])
    {
        return $this->getSchemeOfWorkGet($id, $data, $segments);
    }

    public function putSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $action = $segments[0] ?? null;
        if ($action === 'approve') {
            $result = $this->api->approveSchemeOfWork($id, $data);
        } elseif ($action === 'reject') {
            $result = $this->api->rejectSchemeOfWork($id, $data);
        } else {
            $result = $this->api->updateSchemeOfWork($id, $data);
        }

        return $this->handleResponse($result);
    }

    public function postSchemesOfWorkApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->approveSchemeOfWork($id, $data);
        return $this->handleResponse($result);
    }

    public function putSchemesOfWorkApprove($id = null, $data = [], $segments = [])
    {
        return $this->postSchemesOfWorkApprove($id, $data, $segments);
    }

    public function postSchemesOfWorkReject($id = null, $data = [], $segments = [])
    {
        $result = $this->api->rejectSchemeOfWork($id, $data);
        return $this->handleResponse($result);
    }

    public function putSchemesOfWorkReject($id = null, $data = [], $segments = [])
    {
        return $this->postSchemesOfWorkReject($id, $data, $segments);
    }

    public function deleteSchemesOfWork($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteSchemeOfWork($id);
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

    /**
     * GET /api/academic/subject-teachers - Bare alias of getSubjectsTeachers.
     * Subject pages call the kebab `subject-teachers?subject_id=` route.
     */
    public function getSubjectTeachers($id = null, $data = [], $segments = [])
    {
        return $this->getSubjectsTeachers($id, $data, $segments);
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
        if (($data['action'] ?? null) === 'parent-meetings') {
            $roles = $this->getUserRoleIds();
            if (empty(array_intersect($roles, [2, 3, 4]))) {
                $data['_meeting_scope_user_id'] = (int) $this->getUserId();
                $data['_meeting_scope'] = 'class';
            } else {
                $data['_meeting_scope'] = 'all';
            }
        }
        $result = $this->api->handleCustomGet(
            $data['id'] ?? $id,
            $data['action'] ?? null,
            $data
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/academic/custom - Handle custom POST operations
     */
    public function postCustom($id = null, $data = [], $segments = [])
    {
        if (($data['action'] ?? null) === 'schedule-meeting') {
            $roles = $this->getUserRoleIds();
            if (empty(array_intersect($roles, [2, 3, 4]))) {
                $data['_meeting_scope_user_id'] = (int) $this->getUserId();
                $data['_meeting_scope'] = 'class';
            } else {
                $data['_meeting_scope'] = 'all';
            }
        }
        $result = $this->api->handleCustomPost(
            $data['id'] ?? $id,
            $data['action'] ?? null,
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
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to view formative assessments');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        if (!$this->canManageAllFormativeAssessments()) {
            $filters['teacher_scope_only'] = true;
        }
        return $this->handleResponse($this->academicManager->getFormativeAssessments($filters, $staffId));
    }

    public function postFormativeAssessments($id = null, $data = [], $segments = [])
    {
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to create formative assessments');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        $user = is_object($this->user) ? get_object_vars($this->user) : (array) $this->user;
        $user['staff_id'] = $staffId;
        return $this->handleResponse($this->academicManager->postFormativeAssessments(
            is_array($data) ? $data : [],
            $user,
            $this->canManageAllFormativeAssessments()
        ));
    }

    /**
     * PUT /api/academic/formative-assessments/{id} → update a formative assessment
     */
    public function putFormativeAssessments($id = null, $data = [], $segments = [])
    {
        if ($id === null) return $this->badRequest('assessment id is required');
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to update formative assessments');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        return $this->handleResponse($this->academicManager->putFormativeAssessments(
            (int) $id,
            is_array($data) ? $data : [],
            $staffId,
            $this->canManageAllFormativeAssessments()
        ));
    }

    /**
     * DELETE /api/academic/formative-assessments/{id} → delete a formative assessment
     */
    public function deleteFormativeAssessments($id = null, $data = [], $segments = [])
    {
        if ($id === null) return $this->badRequest('assessment id is required');
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to delete formative assessments');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        return $this->handleResponse($this->academicManager->deleteFormativeAssessments(
            (int) $id,
            $staffId,
            $this->canManageAllFormativeAssessments()
        ));
    }

    /**
     * GET /api/academic/conduct-grades?class=self&term= → conduct ratings for a class
     */
    public function getConductGrades($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        return $this->handleResponse($this->academicManager->getConductGrades($staffId, $filters));
    }

    /**
     * GET /api/academic/results?year_id=&term_id=&subject_id=&class_id= → result rows
     */
    public function getResults($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getResults($filters, $this->getCurrentStaffId()));
    }

    /**
     * GET /api/academic/reports?report_type=&year_id=&term_id=&class_id=&subject_id=
     * Returns { columns, rows, summary } for printable performance/attendance/behavior reports.
     */
    public function getReports($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getReports($filters, $this->getCurrentStaffId()));
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
        $assessmentId = $id ?? (int) ($_GET['assessment_id'] ?? 0);
        if (!$assessmentId) return $this->badRequest('assessment_id is required');
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to view formative assessment marks');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        return $this->handleResponse($this->academicManager->getFormativeAssessmentMarks(
            (int) $assessmentId,
            $staffId,
            $this->canManageAllFormativeAssessments()
        ));
    }

    /**
     * POST /api/academic/formative-assessment-marks
     * Bulk upsert marks for an assessment. Payload: { assessment_id, marks: [{student_id, score, remarks}] }
     */
    public function postFormativeAssessmentMarks($id = null, $data = [], $segments = [])
    {
        $assessmentId = $id ?? (int) ($data['assessment_id'] ?? 0);
        if (!$assessmentId) return $this->badRequest('assessment_id is required');
        if (!$this->canAccessFormativeAssessments()) {
            return $this->forbidden('You do not have permission to record formative assessment marks');
        }
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->notFound('Staff account not found');
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleResponse($this->academicManager->postFormativeAssessmentMarks(
            (int) $assessmentId,
            is_array($data) ? $data : [],
            $userId,
            $staffId,
            $this->canManageAllFormativeAssessments()
        ));
    }

    /**
     * GET /api/academic/formative-summary?class_id=&subject_id=&term_id=&strand_id=&sub_strand_id=&group_by=
     *
     * Returns per-student formative averages grouped by learning area, strand,
     * or sub-strand. `group_by` accepts 'learning_area' (default), 'strand',
     * or 'sub_strand'. Passing strand_id/sub_strand_id forces the matching
     * grouping level so CBC per-strand/sub-strand reporting is supported.
     */
    public function getFormativeSummary($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getFormativeSummary($filters));
    }

    // ==================== CBC: ASSESSMENT TYPES ====================

    /**
     * GET /api/academic/assessment-tools → list all CBC assessment tools
     */
    public function getAssessmentTools($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getAssessmentTools($query));
    }

    /** POST /api/academic/assessment-tools */
    public function postAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        $createdBy = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
        return $this->handleResponse($this->academicManager->postAssessmentTools(is_array($data) ? $data : [], $createdBy));
    }

    /** PUT /api/academic/assessment-tools/{id} */
    public function putAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Assessment tool ID is required');
        return $this->handleResponse($this->academicManager->putAssessmentTools((int) $id, is_array($data) ? $data : []));
    }

    /** DELETE /api/academic/assessment-tools/{id} */
    public function deleteAssessmentTools($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Assessment tool ID is required');
        return $this->handleResponse($this->academicManager->deleteAssessmentTools((int) $id));
    }

    /**
     * GET /api/academic/assessment-types → list all CBC assessment types
     */
    public function getAssessmentTypes($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getAssessmentTypes($filters));
    }

    /** GET /api/academic/assessment-classifications */
    public function getAssessmentClassifications($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getAssessmentClassifications());
    }

    /**
     * GET /api/academic/core-competencies-list → CBC 8 core competencies from DB
     */
    public function getCoreCompetenciesList($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getCoreCompetenciesList());
    }

    /**
     * GET /api/academic/core-competencies — Router-friendly alias.
     */
    public function getCoreCompetencies($id = null, $data = [], $segments = [])
    {
        return $this->getCoreCompetenciesList($id, $data, $segments);
    }

    /**
     * GET /api/academic/core-values-list — CBC core values from DB
     */
    public function getCoreValuesList($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getCoreValuesList());
    }

    /**
     * GET /api/academic/core-values — Router-friendly alias.
     */
    public function getCoreValues($id = null, $data = [], $segments = [])
    {
        return $this->getCoreValuesList($id, $data, $segments);
    }

    // ==================== CBC: COMPETENCY RATINGS ====================

    /**
     * GET  /api/academic/competency-ratings?class_id=&term_id=&student_id=
     * POST /api/academic/competency-ratings  → bulk upsert
     */
    public function getCompetencyRatings($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getCompetencyRatings($filters));
    }

    public function postCompetencyRatings($id = null, $data = [], $segments = [])
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleResponse($this->academicManager->postCompetencyRatings(is_array($data) ? $data : [], $userId));
    }

    // ==================== CBC: NATIONAL EXAMS ====================

    /**
     * GET  /api/academic/national-exams?exam_type=KPSEA_G6&exam_year=2024
     * POST /api/academic/national-exams → enter results
     */
    public function getNationalExams($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getNationalExams($filters));
    }

    public function postNationalExams($id = null, $data = [], $segments = [])
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleResponse($this->academicManager->postNationalExams(is_array($data) ? $data : [], $userId));
    }

    // ==================== CBC: STRANDS ====================

    /**
     * GET /api/academic/strands?learning_area_id=X
     */
    public function getStrands($id = null, $data = [], $segments = [])
    {
        $filters = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getStrands($filters));
    }

    /** POST /api/academic/strands */
    public function postStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Direct curriculum changes are disabled. Create and approve a governed curriculum proposal.');
    }

    /** PUT /api/academic/strands/{id} */
    public function putStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Direct curriculum changes are disabled. Create and approve a governed curriculum proposal.');
    }

    /** DELETE /api/academic/strands/{id} */
    public function deleteStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Curriculum items are retired through the governed proposal workflow, never directly deleted.');
    }

    // ==================== CBC: CLASS STUDENTS ====================

    /**
     * GET /api/academic/class-students?class_id=X
     * Returns active enrolled students for a class.
     */
    public function getClassStudents($id = null, $data = [], $segments = [])
    {
        $classId = (int) ($id ?: ($data['class_id'] ?? ($_GET['class_id'] ?? 0)));
        if (!$classId) return $this->badRequest('class_id is required');
        return $this->handleResponse($this->academicManager->getClassStudents($classId));
    }

    // ==================== CBC: COMPUTE TERM SCORES ====================

    /**
     * POST /api/academic/compute-term-scores
     * Computes formative/summative aggregates from formative_scores → term_subject_scores.
     * Body: { class_id, term_id, subject_id? }  OR  { assessment_id }
     */
    public function postComputeTermScores($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->postComputeTermScores(is_array($data) ? $data : []));
    }

    // ==================== CBC: REPORT CARD DATA ====================

    /**
     * GET /api/academic/report-card-data/{student_id}?term_id=
     * Consolidated CBC report card: term_subject_scores + competency ratings + attendance + values.
     */
    public function getReportCardData($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? (int) ($_GET['student_id'] ?? 0);
        if (!$studentId) return $this->badRequest('student_id is required');
        $termId = (int) ($_GET['term_id'] ?? 0);
        return $this->handleResponse($this->academicManager->getReportCardData((int) $studentId, $termId));
    }

    // ==================== CBC: STUDENT GROWTH ====================

    /**
     * GET /api/academic/student-assessment-history?student_id=X&term_id=&subject_id=
     * Returns all graded assessments for a student with their scores.
     */
    public function getStudentAssessmentHistory($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getStudentAssessmentHistory($filters));
    }

    /**
     * GET /api/academic/student-growth-trend?student_id=X&learning_area_id=Y
     * Returns per-term average scores for a student in a learning area (for charting).
     */
    public function getStudentGrowthTrend($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getStudentGrowthTrend($filters));
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
        if (!$studentId) {
            return $this->error('Student id is required.');
        }
        return $this->handleResponse($this->academicManager->getStudentTimeline((int) $studentId));
    }

    /**
     * GET /api/academic/staff-timeline/{staff_id}
     */
    public function getStaffTimeline($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($segments[0] ?? null);
        if (!$staffId && isset($data['staff_id'])) $staffId = $data['staff_id'];
        if (!$staffId) {
            return $this->error('Staff id is required.');
        }
        return $this->handleResponse($this->academicManager->getStaffTimeline((int) $staffId));
    }

    // ==================== TRANSFER REQUESTS ====================

    /**
     * GET /api/academic/transfer-requests
     * POST /api/academic/transfer-requests
     */
    public function getTransferRequests($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getTransferRequests($id ? (int) $id : null));
    }

    public function postTransferRequests($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->error('student_id is required.');
        }
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->postTransferRequests(is_array($data) ? $data : [], $userId));
    }

    /**
     * PUT /api/academic/transfer-requests/{id}
     * Update clearance status or approve/reject transfer
     */
    public function putTransferRequests($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->error('Transfer request id is required.');
        }
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->putTransferRequests((int) $id, is_array($data) ? $data : [], $userId));
    }

    // ==================== YEAR-END ROLLOVER ====================

    /**
     * GET /api/academic/year-rollover-status
     * Returns the current state of the rollover checklist.
     */
    // TODO: Delegate to AcademicYearService
    public function getYearRolloverStatus($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getYearRolloverStatus());
    }

    /**
     * POST /api/academic/year-rollover
     * Executes one step of the year-end rollover process.
     * Body: { step: 'fee_carryover' | 'staff_reassignment' | 'create_new_year' | ... }
     */
    // TODO: Delegate to AcademicYearService
    public function postYearRollover($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->postYearRollover(is_array($data) ? $data : [], $userId));
    }

    // =========================================================================
    // DEPUTY HEADTEACHER — shared "My Teaching Today" panel
    // GET /api/academic/my-teaching-today
    // Returns the current user's class assignment, today's lessons, attendance,
    // and pending lesson plans — same data shown on both deputy dashboards.
    // =========================================================================
    public function getMyTeachingToday($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->getMyTeachingToday($userId));
    }

    // =========================================================================
    // DEPUTY HEAD (ACADEMIC) — admin summary
    // GET /api/academic/deputy-academic-summary
    // =========================================================================
    public function getDeputyAcademicSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getDeputyAcademicSummary());
    }

    // =========================================================================
    // DEPUTY HEAD (DISCIPLINE) — admin summary
    // GET /api/academic/deputy-discipline-summary
    // =========================================================================
    public function getDeputyDisciplineSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getDeputyDisciplineSummary());
    }

    // ========================================================================
    // STAFF TEACHING ASSIGNMENTS — CHECKPOINT 2
    // ========================================================================
    private function guardTeachingAssignments(string $permission = 'staff.teaching_assignments.manage')
    {
        try {
            $roles = $permission === 'staff.teaching_assignments.view'
                ? ['system administrator','school administrator','director','headteacher','deputy head - academic','class teacher','subject teacher']
                : ['system administrator','school administrator','headteacher','deputy head - academic'];
            $this->staffAccess->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->serverError('An internal error occurred.'); }
    }

    /** GET /api/academic/class-teachers or /{id} */
    public function getClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments('staff.teaching_assignments.view')) return $denied;
        try {
            if ($id !== null) {
                $row = $this->teachingAssignments->getClassTeacher((int)$id);
                return $row ? $this->success($row) : $this->notFound('Class teacher assignment not found');
            }
            return $this->success($this->teachingAssignments->listClassTeachers(array_merge($_GET, $data)));
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Throwable $e) {
            return $this->serverError('Failed to load class teacher assignments', 'An internal error occurred.');
        }
    }

    /** POST /api/academic/class-teachers */
    public function postClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        try {
            $newId = $this->teachingAssignments->saveClassTeacher($data, null, $this->staffAccess->userId());
            $this->staffAccess->audit('create_class_teacher_assignment', 'staff_class_assignment', $newId, null, $data);
            return $this->created(['id'=>$newId], 'Class teacher assigned');
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Throwable $e) {
            return $this->serverError('Failed to assign class teacher', 'An internal error occurred.');
        }
    }

    /** PUT /api/academic/class-teachers/{id} */
    public function putClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if (!$id) return $this->badRequest('Assignment ID is required');
        try {
            $before = $this->teachingAssignments->getClassTeacher((int)$id);
            $this->teachingAssignments->saveClassTeacher($data, (int)$id, $this->staffAccess->userId());
            $this->staffAccess->audit('update_class_teacher_assignment', 'staff_class_assignment', (int)$id, $before, $data);
            return $this->success(['id'=>(int)$id], 'Class teacher assignment updated');
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** DELETE /api/academic/class-teachers/{id} */
    public function deleteClassTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if (!$id) return $this->badRequest('Assignment ID is required');
        $before = $this->teachingAssignments->getClassTeacher((int)$id);
        $this->teachingAssignments->removeClassTeacher((int)$id);
        $this->staffAccess->audit('remove_class_teacher_assignment', 'staff_class_assignment', (int)$id, $before, ['status'=>'completed']);
        return $this->success(null, 'Class teacher assignment removed');
    }

    /** GET /api/academic/subject-assignments or /{id} */
    public function getSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments('staff.teaching_assignments.view')) return $denied;
        try {
            if ($id !== null) {
                $row = $this->teachingAssignments->getSubjectAssignment((int)$id);
                return $row ? $this->success($row) : $this->notFound('Subject assignment not found');
            }
            return $this->success($this->teachingAssignments->listSubjectAssignments(array_merge($_GET, $data)));
        } catch (\Throwable $e) {
            return $this->serverError('Failed to load subject assignments', 'An internal error occurred.');
        }
    }

    /** POST /api/academic/subject-assignments */
    public function postSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        try {
            $newId=$this->teachingAssignments->saveSubjectAssignment($data,null,$this->staffAccess->userId());
            $this->staffAccess->audit('create_subject_assignment','staff_class_assignment',$newId,null,$data);
            return $this->created(['id'=>$newId],'Subject assignment created');
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** PUT /api/academic/subject-assignments/{id} */
    public function putSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if(!$id)return $this->badRequest('Assignment ID is required');
        try{$before=$this->teachingAssignments->getSubjectAssignment((int)$id);$this->teachingAssignments->saveSubjectAssignment($data,(int)$id,$this->staffAccess->userId());$this->staffAccess->audit('update_subject_assignment','staff_class_assignment',(int)$id,$before,$data);return $this->success(['id'=>(int)$id],'Subject assignment updated');}
        catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); }
    }

    /** DELETE /api/academic/subject-assignments/{id} */
    public function deleteSubjectAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardTeachingAssignments()) return $denied;
        if(!$id)return $this->badRequest('Assignment ID is required');
        $before=$this->teachingAssignments->getSubjectAssignment((int)$id);$this->teachingAssignments->removeSubjectAssignment((int)$id);$this->staffAccess->audit('remove_subject_assignment','staff_class_assignment',(int)$id,$before,['status'=>'completed']);return $this->success(null,'Subject assignment removed');
    }

    // ========================================================================
    // STUDENT/PARENT TEACHER DIRECTORY (viewer_staff page)
    // GET /api/academic/my-teachers           — current student's class/subject teachers
    // GET /api/academic/parent-class-teachers — children's class teachers
    // Both are self-scoped: the student id(s) are resolved from the authenticated
    // user's own profile/parent links — never from client input.
    // ========================================================================

    /** GET /api/academic/my-teachers */
    public function getMyTeachers($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['students_view_own', 'students_view', 'academic_view_own', 'academic_view', 'staff_view_own', 'staff_view'])) {
            return $this->forbidden('Access to my teachers is not available for this account');
        }
        try {
            $studentIds = ($this->contract('App\API\Modules\students\StudentProfileManager'))->resolveStudentIds($this->user);
            if (empty($studentIds)) {
                return $this->success([], 'No student profile is linked to the current user');
            }
            $contacts = [];
            foreach ($studentIds as $studentId) {
                $contacts = array_merge($contacts, $this->studentTeacherContacts((int)$studentId));
            }
            return $this->success($contacts, 'Teachers retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('Failed to load teachers', 'An internal error occurred.');
        }
    }

    /** GET /api/academic/parent-class-teachers */
    public function getParentClassTeachers($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['students_parents_view', 'students_view_own', 'students_view', 'academic_view_own', 'academic_view', 'staff_view_own', 'staff_view'])) {
            return $this->forbidden('Access to class teacher contacts is not available for this account');
        }
        try {
            $profileManager = $this->contract('App\API\Modules\students\StudentProfileManager');
            $parentIds = $profileManager->resolveParentIds($this->user);
            if (empty($parentIds)) {
                return $this->success([], 'No linked student profiles found for the current user');
            }
            $children = ($this->contract('App\API\Modules\students\FamilyGroupsManager'))->getChildrenForParentIds($parentIds);
            $studentIds = ($children['success'] ?? false) ? ($children['data'] ?? []) : [];
            $contacts = [];
            foreach ($studentIds as $studentId) {
                $contacts = array_merge($contacts, $this->studentTeacherContacts((int)$studentId, true));
            }
            return $this->success($contacts, 'Class teachers retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('Failed to load class teachers', 'An internal error occurred.');
        }
    }

    /**
     * Build contact rows for one student's current active class: class teacher
     * (always) plus subject teachers unless $classTeacherOnly. The student id is
     * resolved from the authenticated user by the callers above.
     */
    private function studentTeacherContacts(int $studentId, bool $classTeacherOnly = false): array
    {
        $enrollment = $this->db->query(
            "SELECT aycs.id AS class_stream_id,
                    aycs.class_teacher_id,
                    ayc.academic_year_id,
                    c.name AS class_name, st.name AS stream_name,
                    CONCAT_WS(' ', sp.first_name, sp.last_name) AS student_name
               FROM student_academic_enrollments sae
               JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
               JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
               JOIN classes c ON c.id = ayc.class_id
               LEFT JOIN streams st ON st.id = aycs.stream_id
               JOIN students s ON s.id = sae.student_id
               JOIN persons sp ON sp.id = s.person_id
              WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
              ORDER BY ayc.academic_year_id DESC
              LIMIT 1",
            [$studentId]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$enrollment || !$enrollment['class_teacher_id']) {
            return [];
        }

        $context = trim(($enrollment['student_name'] ?? '') . ' — ' . trim($enrollment['class_name'] . ' ' . $enrollment['stream_name']));
        $contacts = [];

        $teacherRow = $this->db->query(
            "SELECT CONCAT_WS(' ', p.first_name, p.last_name) AS name,
                    p.phone AS phone, p.email AS email
               FROM staff s
               JOIN persons p ON p.id = s.person_id
              WHERE s.id = ?",
            [(int)$enrollment['class_teacher_id']]
        )->fetch(PDO::FETCH_ASSOC);

        if ($teacherRow) {
            $contacts[] = [
                'name' => $teacherRow['name'],
                'role' => 'Class Teacher',
                'phone' => $teacherRow['phone'],
                'email' => $teacherRow['email'],
                'icon' => '👩‍🏫',
                'context' => $context,
            ];
        }

        if ($classTeacherOnly) {
            return $contacts;
        }

        $subjectRows = $this->db->query(
            "SELECT v.staff_name AS name, v.subject_name AS subject,
                    sp.phone AS phone, sp.email AS email
               FROM vw_staff_assignments_detailed v
               JOIN staff s ON s.id = v.staff_id
               JOIN persons sp ON sp.id = s.person_id
              WHERE v.class_stream_id = ? AND v.academic_year_id = ? AND v.role = 'subject_teacher'
              GROUP BY v.staff_id, v.subject_name, sp.phone, sp.email
              ORDER BY v.subject_name",
            [(int)$enrollment['class_stream_id'], (int)$enrollment['academic_year_id']]
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjectRows as $row) {
            $contacts[] = [
                'name' => $row['name'],
                'role' => $row['subject'] . ' Teacher',
                'phone' => $row['phone'],
                'email' => $row['email'],
                'icon' => '👩‍🏫',
                'context' => $context,
            ];
        }

        return $contacts;
    }

    // ==================== CBC: SUB-STRANDS ====================

    /**
     * GET /api/academic/sub-strands?strand_id=X
     * Get sub-strands, optionally filtered by strand_id. If numeric ID in URL, return single.
     */
    public function getSubStrands($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getSubStrands($id !== null ? (int)$id : null, $query));
    }

    /** POST /api/academic/sub-strands */
    public function postSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Direct curriculum changes are disabled. Create and approve a governed curriculum proposal.');
    }

    /** PUT /api/academic/sub-strands/{id} */
    public function putSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Direct curriculum changes are disabled. Create and approve a governed curriculum proposal.');
    }

    /** DELETE /api/academic/sub-strands/{id} */
    public function deleteSubStrands($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->badRequest('Curriculum items are retired through the governed proposal workflow, never directly deleted.');
    }

    /** POST /api/academic/sub-strands/bulk — auto-populate orphaned strands with default sub-strands */
    public function postSubStrandsBulk($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;

        // Curriculum records must come from an authoritative syllabus import.
        // Never fabricate sub-strands from strand names: that creates data which
        // looks complete but cannot support valid outcomes or assessment mapping.
        return $this->badRequest(
            'Bulk sub-strand creation is disabled. Import approved CBC curriculum data instead.'
        );
    }

    // ==================== CBC: LEARNING OUTCOMES ====================

    /**
     * GET /api/academic/learning-outcomes?sub_strand_id=X&strand_id=X&learning_area_id=X
     */
    public function getLearningOutcomes($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getLearningOutcomes($id !== null ? (int)$id : null, $query));
    }

    /** POST /api/academic/learning-outcomes */
    public function postLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->handleResponse($this->academicManager->postLearningOutcomes(is_array($data) ? $data : []));
    }

    /** PUT /api/academic/learning-outcomes/{id} */
    public function putLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Learning outcome ID is required');
        return $this->handleResponse($this->academicManager->putLearningOutcomes((int)$id, is_array($data) ? $data : []));
    }

    /** DELETE /api/academic/learning-outcomes/{id} */
    public function deleteLearningOutcomes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Learning outcome ID is required');
        return $this->handleResponse($this->academicManager->deleteLearningOutcomes((int)$id));
    }

    // ==================== CBC: ASSESSMENT RUBRICS ====================

    /** GET /api/academic/assessment-rubrics?tool_id=X */
    public function getAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getAssessmentRubrics($id !== null ? (int)$id : null, $query));
    }

    /** POST /api/academic/assessment-rubrics */
    public function postAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        return $this->handleResponse($this->academicManager->postAssessmentRubrics(is_array($data) ? $data : []));
    }

    /** PUT /api/academic/assessment-rubrics/{id} */
    public function putAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Rubric ID is required');
        return $this->handleResponse($this->academicManager->putAssessmentRubrics((int)$id, is_array($data) ? $data : []));
    }

    /** DELETE /api/academic/assessment-rubrics/{id} */
    public function deleteAssessmentRubrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Rubric ID is required');
        return $this->handleResponse($this->academicManager->deleteAssessmentRubrics((int)$id));
    }

    // ==================== CBC: GRADING SCALE (DB-DRIVEN) ====================
    // Single source of truth for grade boundaries lives in `grading_scales`
    // (the scale header) and `grade_rules` (per-band rows: min/max marks,
    // grade code, points, performance level, description). No thresholds are
    // hardcoded in the frontend; all pages resolve grades from these rows.

    /** GET /api/academic/grading-scale|/grading-scale/{id} - Fetch a grading scale + its grade rules */
    public function getGradingScale($id = null, $data = [], $segments = [])
    {
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getGradingScale($id !== null ? (int)$id : null, $query));
    }

    /** POST /api/academic/grading-scale - Create a grading scale */
    public function postGradingScale($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        return $this->handleResponse($this->academicManager->postGradingScale(is_array($data) ? $data : []));
    }

    /** PUT /api/academic/grading-scale/{id} - Update a grading scale */
    public function putGradingScale($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Scale ID is required');
        return $this->handleResponse($this->academicManager->putGradingScale((int)$id, is_array($data) ? $data : []));
    }

    /** POST /api/academic/grade-rules - Create a grade rule (range → grade) */
    public function postGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        return $this->handleResponse($this->academicManager->postGradeRules(is_array($data) ? $data : []));
    }

    /** PUT /api/academic/grade-rules/{id} - Update a grade rule */
    public function putGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Grade rule ID is required');
        return $this->handleResponse($this->academicManager->putGradeRules((int)$id, is_array($data) ? $data : []));
    }

    /** DELETE /api/academic/grade-rules/{id} - Delete a grade rule */
    public function deleteGradeRules($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage', 'assessments_rubric_manage'])) return $guard;
        if (!$id) return $this->badRequest('Grade rule ID is required');
        return $this->handleResponse($this->academicManager->deleteGradeRules((int)$id));
    }

    // ==================== CBC: STRAND-COMPETENCY CROSSWALK ====================

    /** GET /api/academic/strand-competencies?strand_id=X&competency_id=X */
    public function getStrandCompetencies($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getStrandCompetencies($id !== null ? (int)$id : null, $query));
    }

    /** POST /api/academic/strand-competencies */
    public function postStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        return $this->handleResponse($this->academicManager->postStrandCompetencies(is_array($data) ? $data : []));
    }

    /** PUT /api/academic/strand-competencies/{id} */
    public function putStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Mapping ID is required');
        return $this->handleResponse($this->academicManager->putStrandCompetencies((int)$id, is_array($data) ? $data : []));
    }

    /** DELETE /api/academic/strand-competencies/{id} */
    public function deleteStrandCompetencies($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'curriculum_manage'])) return $guard;
        if (!$id) return $this->badRequest('Mapping ID is required');
        return $this->handleResponse($this->academicManager->deleteStrandCompetencies((int)$id));
    }

    // ==================== CBC: CURRICULUM TREE ====================

    /**
     * GET /api/academic/curriculum-tree?learning_area_id=X&strand_id=X
     * Returns the full CBC curriculum tree: learning areas -> strands -> sub-strands -> learning outcomes
     */
    public function getCurriculumTree($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getCurriculumTree($query));
    }

    // ==================== EXAM MODERATION ====================

    /**
     * GET /api/academic/pending-moderation?class_id=X&subject_id=X
     * Returns assessments with results submitted but not yet approved (pending moderation).
     */
    public function getPendingModeration($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getPendingModeration($query));
    }

    /** POST /api/academic/approve-assessment — approve individual assessment results */
    public function postApproveAssessment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        try {
            $assessmentId = (int)($data['assessment_id'] ?? 0);
            $studentId = isset($data['student_id']) ? (int)$data['student_id'] : null;
            if (!$assessmentId) return $this->badRequest('assessment_id is required');

            return $this->handleResponse($this->api->moderateAssessmentResults([
                'assessment_id' => $assessmentId,
                'student_id' => $studentId,
                'action' => 'approve',
                'reason' => $data['reason'] ?? $data['remarks'] ?? '',
            ]));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/academic/reject-assessment — reject individual result */
    public function postRejectAssessment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_manage', 'academic_approve'])) return $guard;
        try {
            $assessmentId = (int)($data['assessment_id'] ?? 0);
            $studentId = (int)($data['student_id'] ?? 0);
            $reason = $data['reason'] ?? '';
            if (!$assessmentId) return $this->badRequest('assessment_id is required');

            return $this->handleResponse($this->api->moderateAssessmentResults([
                'assessment_id' => $assessmentId,
                'student_id' => $studentId ?: null,
                'action' => 'reject',
                'reason' => $reason,
            ]));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AcademicController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // ==================== LEGACY CURRICULUM ENDPOINT ====================

    /**
     * GET /api/academic/curriculum — backward-compatible flat curriculum list
     * Returns strands + sub-strands in flat format for curriculum_cbc.js.
     * With a trailing {id}, returns a single flattened entry for edit().
     */
    public function getCurriculum($id = null, $data = [], $segments = [])
    {
        $query = $this->scopedCurriculumQuery(array_merge($_GET, is_array($data) ? $data : []));
        return $this->handleResponse($this->academicManager->getCurriculum($query, $id ? (int) $id : null));
    }

    // ==================== TEACHER PORTAL (my-*/intern-*) ====================

    private function getCurrentStaffId(): ?int
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return null;
        }
        $stmt = $this->getDb()->getConnection()->prepare("SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1");
        $stmt->execute([(int) $userId]);
        $staffId = $stmt->fetchColumn();
        return $staffId ? (int) $staffId : null;
    }

    private function canAccessFormativeAssessments(): bool
    {
        return $this->userHasAny(
            [
                'academic_assessments_view',
                'academic_assessments_create',
                'academic_assessments_edit',
                'assessments_view',
                'assessments_create',
                'assessments_edit',
            ],
            [1, 3, 4, 5, 6, 7, 8],
            [
                'system administrator',
                'school administrator',
                'director',
                'principal',
                'headteacher',
                'deputy head - academic',
                'class teacher',
                'subject teacher',
            ]
        );
    }

    private function canManageAllFormativeAssessments(): bool
    {
        return $this->userHasAny(
            [],
            [1, 3, 4, 5, 6],
            [
                'system administrator',
                'school administrator',
                'director',
                'principal',
                'headteacher',
                'deputy head - academic',
            ]
        );
    }

    /**
     * GET /api/academic/my-classes — classes + subjects for the logged-in teacher.
     */
    public function getMyClasses($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getMyClasses($staffId, $query));
    }

    /**
     * GET /api/academic/intern-classes — classes a teaching intern is attached to.
     */
    public function getInternClasses($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getInternClasses($staffId, $query));
    }

    /**
     * GET /api/academic/intern-subjects — subjects assigned to a teaching intern.
     */
    public function getInternSubjects($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getInternSubjects($staffId, $query));
    }

    /**
     * GET /api/academic/my-subjects — subjects the teacher teaches (with status).
     */
    public function getMySubjects($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getMySubjects($staffId, $query));
    }

    /**
     * GET /api/academic/my-schemes — schemes of work owned by the teacher.
     */
    public function getMySchemes($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getMySchemes($staffId, $query));
    }

    /**
     * GET /api/academic/subject-schemes — teacher's schemes for one subject.
     */
    public function getSubjectSchemes($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getSubjectSchemes($staffId, $query));
    }

    /**
     * GET /api/academic/syllabus — read-only flat curriculum list.
     */
    public function getSyllabus($id = null, $data = [], $segments = [])
    {
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getSyllabus($query));
    }

    /**
     * GET /api/academic/my-syllabus — syllabus for the teacher's assigned subjects.
     */
    public function getMySyllabus($id = null, $data = [], $segments = [])
    {
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return $this->handleResponse(errorResponse('Staff account not found', 404));
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getMySyllabus($staffId, $query));
    }

    /**
     * GET /api/academic/year-calendar — current year's calendar days.
     */
    public function getYearCalendar($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getYearCalendar());
    }

    /**
     * GET /api/academic/calendar/days/{year_id} — calendar day rows for a year
     * (including gazetted holidays) for the day editor.
     */
    public function getCalendarDays($id = null, $data = [], $segments = [])
    {
        $yearId = $id ? (int) $id : (int) ($_GET['academic_year_id'] ?? 0);
        if (!$yearId) {
            return $this->badRequest('academic_year_id is required');
        }
        return $this->handleResponse($this->academicManager->getCalendarDays($yearId));
    }

    /**
     * PUT /api/academic/calendar/day/{id} — mark a calendar day as a holiday,
     * closure or special event (or back to a normal school day).
     */
    public function putCalendarDay($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requireAcademicWorkflowAccess(['academic_year_manage', 'system_admin'])) {
            return $guard;
        }
        return $this->handleResponse($this->academicManager->updateCalendarDay($id, is_array($data) ? $data : []));
    }

    /**
     * GET /api/academic/year-history — all academic years with enrolment counts.
     */
    public function getYearHistory($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->getYearHistory());
    }

    /**
     * GET /api/academic/lesson-plans/by-class — class lesson-plan coverage.
     * With a trailing {classId} returns per-subject detail for that class.
     */
    public function getLessonPlansByClass($id = null, $data = [], $segments = [])
    {
        $query = array_merge($_GET, is_array($data) ? $data : []);
        if ($id) {
            $query['class_id'] = (int) $id;
        }
        return $this->handleResponse($this->academicManager->getLessonPlansByClass($query));
    }

    /**
     * POST /api/academic/curriculum — create a flat curriculum entry.
     */
    public function postCurriculum($id = null, $data = [], $segments = [])
    {
        return $this->badRequest('Direct curriculum changes are disabled. Use Curriculum Proposals so the change is reviewed and versioned.');
    }

    /**
     * PUT /api/academic/curriculum/{id} — update a flat curriculum entry.
     */
    public function putCurriculum($id = null, $data = [], $segments = [])
    {
        return $this->badRequest('Direct curriculum changes are disabled. Use Curriculum Proposals so the change is reviewed and versioned.');
    }

    /**
     * DELETE /api/academic/curriculum/{id} — delete a flat curriculum entry.
     */
    public function deleteCurriculum($id = null, $data = [], $segments = [])
    {
        return $this->badRequest('Curriculum items are retired through the governed proposal workflow, never directly deleted.');
    }

    // ==================== PORTFOLIO MANAGEMENT ====================

    /**
     * GET /api/academic/portfolio/all/{studentId}
     * Returns cumulative portfolio data across ALL years for print/PDF.
     * Response: { student, portfolios[], artifacts[], competencySummary[],
     *             valuesSummary[], teacherFeedback, yearRange, totalArtifacts }
     */
    public function getPortfolioAll($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
        if (!$studentId) return $this->badRequest('Student ID is required');

        // Keep portfolio viewing aligned with the learner context list. A
        // class teacher may open only learners in their assigned active class
        // streams; management roles retain school-wide access.
        $roles = $this->getUserRoleIds();
        $roleName = strtolower((string) ($this->user['role'] ?? $this->user['role_name'] ?? ''));
        if ((in_array(7, $roles, true) || strpos($roleName, 'class teacher') !== false)
            && !array_intersect($roles, [1, 2, 3, 4, 5, 6, 10, 63])) {
            $userId = (int) ($this->user['id'] ?? 0);
            $stmt = $this->db->prepare("SELECT 1
                FROM student_academic_enrollments sae
                JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                JOIN staff st ON st.id = aycs.class_teacher_id
                JOIN users u ON u.person_id = st.person_id
                WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
                  AND aycs.status = 'active' AND u.id = ? LIMIT 1");
            $stmt->execute([$studentId, $userId]);
            if (!$stmt->fetchColumn()) return $this->forbidden('You can only view portfolios for your assigned learners');
        }
        return $this->handleResponse($this->academicManager->getPortfolioAll($studentId));
    }

    /**
     * GET /api/academic/portfolio/list — List portfolios for a student or class
     * Query params: student_id, class_id, stream_id, status
     */
    public function getPortfolioList($id = null, $data = [], $segments = [])
    {
        $query = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->academicManager->getPortfolioList($query));
    }

    /**
     * GET /api/academic/portfolio/get/{studentId} — Get portfolio + artifacts for a student
     */
    public function getPortfolioGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : (int)($data['student_id'] ?? $_GET['student_id'] ?? 0);
        if (!$studentId) return $this->badRequest('student_id is required');
        return $this->handleResponse($this->academicManager->getPortfolioGet($studentId));
    }

    /**
     * POST /api/academic/portfolio/create — Create a portfolio for a student
     */
    public function postPortfolioCreate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->academicManager->postPortfolioCreate(is_array($data) ? $data : []));
    }

    /**
     * POST /api/academic/portfolio/artifact-add — Add artifact to portfolio
     * Optional multipart `file` field stores evidence via MediaManager under
     * UPLOAD_PATH/students/portfolios/{student_id}/, linked back to the
     * portfolio_artifacts row via media_files.id.
     */
    public function postPortfolioArtifactAdd($id = null, $data = [], $segments = [])
    {
        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->postPortfolioArtifactAdd(is_array($data) ? $data : [], $file, $userId));
    }

    /**
     * PUT /api/academic/portfolio/artifact-update — Update artifact metadata
     * (JSON body). File replacement is handled separately by
     * POST /api/academic/portfolio/artifact-file-replace so multipart uploads
     * keep using the POST path that PHP parses natively.
     */
    public function putPortfolioArtifactUpdate($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $payload['id'] = $payload['id'] ?? $id ?? 0;
        return $this->handleResponse($this->academicManager->putPortfolioArtifactUpdate($payload));
    }

    /**
     * POST /api/academic/portfolio/artifact-file-replace — Replace an artifact's
     * evidence file (multipart: id + file). Uploads the new file via MediaManager
     * under UPLOAD_PATH/students/portfolios/{student_id}/, updates the artifact's
     * file_path/media_id, then removes the previous media record and file.
     */
    public function postPortfolioArtifactFileReplace($id = null, $data = [], $segments = [])
    {
        $artifactId = (int)($data['id'] ?? $id ?? 0);
        if (!$artifactId) return $this->badRequest('artifact id is required');
        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        $userId = $this->getUserId();
        return $this->handleResponse($this->academicManager->postPortfolioArtifactFileReplace($artifactId, is_array($data) ? $data : [], $file, $userId));
    }

    /**
     * DELETE /api/academic/portfolio/artifact-delete/{id} — Delete artifact
     * Removes the DB row plus its linked media_files record and stored file.
     */
    public function deletePortfolioArtifactDelete($id = null, $data = [], $segments = [])
    {
        $artifactId = $id ? (int)$id : (int)($data['id'] ?? 0);
        if (!$artifactId) return $this->badRequest('artifact id is required');
        return $this->handleResponse($this->academicManager->deletePortfolioArtifactDelete($artifactId));
    }
}
