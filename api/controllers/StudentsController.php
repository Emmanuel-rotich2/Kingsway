<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\students\StudentsAPI;
use App\API\Modules\students\StudentService;
use App\API\Modules\system\MediaManager;
use App\API\Modules\students\FamilyGroupsManager;
use App\API\Modules\students\PromotionManager;
use App\API\Modules\students\StudentInsightsService;
use App\API\Modules\students\StudentIDCardService;
use App\API\Modules\students\StudentTransferService;
use App\API\Modules\students\StudentPromotionService;
use App\API\Modules\students\StudentParentService;
use App\API\Modules\students\StudentLeadershipService;
use App\API\Modules\students\StudentProfileManager;
use App\API\Modules\academic\AcademicYearManager;
use Exception;

/**
 * StudentsController
 * Handles all student-related operations
 */
class StudentsController extends BaseController
{
    private MediaManager $mediaManager;
    private StudentsAPI $api;
    private StudentService $studentService;
    private FamilyGroupsManager $familyGroupsManager;
    private PromotionManager $promotionManager;
    private StudentInsightsService $studentInsightsService;
    private StudentIDCardService $idCardService;
    private StudentTransferService $transferService;
    private StudentPromotionService $promotionService;
    private StudentParentService $parentService;
    private StudentLeadershipService $leadershipService;
    private StudentProfileManager $studentProfileManager;
    private const STUDENT_VIEW_PERMS = [
        'students_view',
        'students_view_all',
        'students_view_own',
        'students_edit',
        'students_create',
        'students_delete',
        'students_fees_view',
        'students_parents_view',
        'finance_view',
    ];
    private const STUDENT_CREATE_PERMS = ['students_create'];
    private const STUDENT_EDIT_PERMS = ['students_edit'];
    private const STUDENT_DELETE_PERMS = ['students_delete'];
    private const STUDENT_PHOTO_APPROVE_PERMS = ['students_edit', 'students_id_cards_generate', 'admission_manage'];

    /**
     * Leadership/houses/awards records are managed only by the Student
     * Organisation owners (School Admin, Headteacher, Deputies) — NOT by any
     * staff holding generic students_edit (teachers get read-only access).
     */
    private const LEADERSHIP_MANAGE_PERMS = ['student_leadership_manage'];
    private const STUDENT_PROMOTE_PERMS = ['students_generate', 'students_edit'];
    private const STUDENT_TRANSFER_PERMS = [
        'students_transfers_create',
        'students_transfers_edit',
        'students_transfers_submit',
        'students_transfers_approve',
        'students_transfers_view',
        'students_edit'
    ];
    private const STUDENT_ACADEMIC_YEAR_MANAGE_PERMS = [
        'students_generate',
        'students_edit',
        'students_create',
    ];
    private const PARENT_ACCESS_PERMS = [
        'students_parents_view',
        'students_parents_view_all',
        'students_parents_view_own',
        'students_view',
        'students_view_all',
        'students_view_own',
        'students_edit',
        'students_create',
        'admission_view',
        'finance_view',
    ];
    private const STUDENT_DISCIPLINE_PERMS = [
        'students_discipline_view',
        'students_discipline_view_all',
        'students_discipline_view_own',
        'students_discipline_create',
        'students_discipline_edit',
        'students_discipline_approve',
        'students_view',
        'students_view_all',
    ];
    private const STUDENT_FEES_PERMS = [
        'students_fees_view',
        'students_fees_view_all',
        'students_fees_view_own',
        'finance_view',
        'students_view',
        'students_view_all',
        'students_edit',
    ];
    private const STUDENT_ID_CARD_VIEW_PERMS = [
        'students_qr_view',
        'students_qr_view_all',
        'students_qr_view_own',
        'students_view',
        'students_view_all',
        'students_view_own',
    ];
    private const STUDENT_ID_CARD_GENERATE_PERMS = [
        'students_qr_generate',
        'students_qr_create',
        'students_generate',
        'students_print',
        // backward compatibility with existing student editors
        'students_edit',
        'students_create',
    ];
    private const STUDENT_ID_CARD_UPLOAD_PERMS = [
        'students_qr_upload',
        'students_upload',
        // backward compatibility with existing student editors
        'students_edit',
        'students_create',
    ];
    private const STUDENT_ID_CARD_EXPORT_PERMS = [
        'students_qr_download',
        'students_qr_export',
        'students_export',
        'students_print',
    ];

    public function __construct()
    {
        parent::__construct();
        $connection = $this->db->getConnection();
        $this->mediaManager = $this->contract('App\API\Modules\system\MediaManager', $connection);
        $this->studentService = $this->contract('App\API\Modules\students\StudentService', $connection);
        $this->familyGroupsManager = $this->contract('App\API\Modules\students\FamilyGroupsManager');
        $this->promotionManager = $this->contract('App\API\Modules\students\PromotionManager', $connection, $this->contract('App\API\Modules\academic\AcademicYearManager', $connection));
        $this->studentInsightsService = $this->contract('App\API\Modules\students\StudentInsightsService', $connection, $this->studentService);
        $this->api = $this->contract('App\API\Modules\students\StudentsAPI');
        $this->idCardService = $this->contract('App\API\Modules\students\StudentIDCardService', $this->api, $this->studentService);
        $this->transferService = $this->contract('App\API\Modules\students\StudentTransferService', $this->api);
        $this->promotionService = $this->contract('App\API\Modules\students\StudentPromotionService', $this->api, $this->promotionManager);
        $this->parentService = $this->contract('App\API\Modules\students\StudentParentService', $this->api, $this->familyGroupsManager);
        $this->leadershipService = $this->contract('App\API\Modules\students\StudentLeadershipService', $connection);
        $this->studentProfileManager = $this->contract('App\API\Modules\students\StudentProfileManager');
    }

    public function authorizeStudents(array $permissions, string $message = 'Insufficient permissions')
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if (!$this->userHasAny($permissions)) {
            return $this->forbidden($message);
        }

        return null;
    }

    private function authorizeLeadershipManagement(string $message)
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $managementRoles = [
            'school administrator',
            'headteacher',
            'deputy head academic',
            'deputy head discipline',
        ];
        $normalizeRole = static fn($role) => trim(preg_replace('/[^a-z0-9]+/i', ' ', strtolower((string)$role)));
        $userRoles = array_map($normalizeRole, $this->getUserRoleNames());
        $hasManagementRole = (bool)array_intersect($managementRoles, $userRoles);
        if (!$this->userHasAny(self::LEADERSHIP_MANAGE_PERMS) && !$hasManagementRole) {
            return $this->forbidden($message);
        }
        return null;
    }

    /**
     * GET /api/students
     */
    public function getIndex()
    {
        return $this->success(['message' => 'Students API is running']);
    }

    /**
     * GET /api/students/context-list?context=academic
     */
    public function getContextList($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $requestedContext = $data['context'] ?? $_GET['context'] ?? null;
        $context = $this->studentService->resolveContext($this->user, $requestedContext);
        if (empty($context['allowed'])) {
            return $this->forbidden($context['message'] ?? 'Student context is not allowed');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        unset($filters['context']);

        $result = $this->studentService->listForContext($this->user, $context['context'], $filters);
        return $this->success($result, 'Students loaded');
    }

    private ?\App\API\Modules\students\StudentDataAccessService $dataAccess = null;

    private function dataAccess(): \App\API\Modules\students\StudentDataAccessService
    {
        return $this->dataAccess ??= new \App\API\Modules\students\StudentDataAccessService($this->db->getConnection());
    }

    /** Refuse unless this viewer may see that section of this learner's record. */
    private function studentSectionGuard($studentId, string $section)
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $studentId = (int) $studentId;
        if ($studentId < 1) return null; // list endpoints keep their own guards
        if (!$this->dataAccess()->allows((int) $this->getUserId(), (array) $this->getUserRoleIds(), $studentId, $section)) {
            $messages = [
                'finance' => 'Fee and payment details are available only to the Director, School Administrator and Accountant.',
                'contacts' => 'Parent and guardian details are not shared with your role.',
                'discipline' => 'Discipline records are not shared with your role.',
            ];
            return $this->forbidden($messages[$section] ?? 'This part of the learner record is not shared with your role.');
        }
        return null;
    }

    /** GET /api/students/data-access/{id} — the sections this viewer may see. */
    public function getDataAccess($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $studentId = (int) ($id ?? ($data['student_id'] ?? 0));
        if ($studentId < 1) return $this->badRequest('Student ID is required');
        return $this->success($this->dataAccess()->forViewer((int) $this->getUserId(), (array) $this->getUserRoleIds(), $studentId));
    }

    /** GET /api/students/basic-profile/{id} — identity and placement only. */
    public function getBasicProfile($id = null, $data = [], $segments = [])
    {
        $studentId = (int) ($id ?? ($data['student_id'] ?? 0));
        if ($guard = $this->studentSectionGuard($studentId, 'profile')) return $guard;
        $profile = $this->dataAccess()->basicProfile($studentId);
        return $profile ? $this->success($profile) : $this->notFound('Student not found');
    }

    /**
     * GET /api/students/teaching-history/{id} — the signed-in teacher's own
     * history with this learner: learning areas taught, where and when, and
     * how the learner performed in them.
     */
    public function getTeachingHistory($id = null, $data = [], $segments = [])
    {
        $studentId = (int) ($id ?? ($data['student_id'] ?? 0));
        if ($guard = $this->studentSectionGuard($studentId, 'teaching_history')) return $guard;
        return $this->success(['history' => $this->dataAccess()->teachingHistory((int) $this->getUserId(), $studentId)]);
    }

    /**
     * GET /api/students/subject-learners?academic_year_id=&academic_year_term_id=
     *     &academic_year_class_stream_id=&learning_area_id=&search=
     * The learners the signed-in teacher teaches or taught, grouped by class
     * stream and learning area. Scoped to the teacher's own assignments.
     */
    public function getSubjectLearners($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $service = new \App\API\Modules\students\SubjectLearnersService($this->db->getConnection());
        return $this->success($service->forUser((int) $this->getUserId(), $filters), 'Learners loaded');
    }

    /**
     * GET /api/students/context-profile/{id}?context=welfare
     */
    public function getContextProfile($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $studentId = $id ?? $data['student_id'] ?? $_GET['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        $requestedContext = $data['context'] ?? $_GET['context'] ?? null;
        $context = $this->studentService->resolveContext($this->user, $requestedContext);
        if (empty($context['allowed'])) {
            return $this->forbidden($context['message'] ?? 'Student context is not allowed');
        }

        $result = $this->studentService->profileForContext($this->user, $context['context'], (int) $studentId);
        if (!$result) {
            return $this->notFound('Student not found in this context');
        }

        return $this->success($result, 'Student profile loaded');
    }

    /**
     * GET /api/students/context-meta?context=boarding
     */
    public function getContextMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $requestedContext = $data['context'] ?? $_GET['context'] ?? null;
        $context = $this->studentService->resolveContext($this->user, $requestedContext);
        if (empty($context['allowed'])) {
            return $this->forbidden($context['message'] ?? 'Student context is not allowed');
        }

        return $this->success([
            'context' => $context['context'],
            'actions' => $context['actions'] ?? [],
            'fields' => $context['fields'] ?? [],
        ], 'Student context loaded');
    }

    /** GET /api/students/import-context */
    public function getImportContext($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to import students')) {
            return $auth;
        }

        return $this->success(
            $this->api->getExistingStudentImportContext(),
            'Existing student import context loaded'
        );
    }

    /* =====================================================
     * BASE CRUD
     * ===================================================== */

    public function getStudent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view students')) {
            return $auth;
        }

        if ($id && empty($segments)) {
            $access = $this->dataAccess()->forViewer((int) $this->getUserId(), (array) $this->getUserRoleIds(), (int) $id);
            if (!$access['access']) return $this->forbidden('This learner is outside your access.');
            $sections = $access['sections'];
            // A teacher who only teaches the learner gets the basic profile;
            // the full record is for pastoral, finance and leadership roles.
            if (!array_intersect(['personal', 'contacts', 'finance', 'health'], $sections)) {
                $profile = $this->dataAccess()->basicProfile((int) $id);
                return $profile ? $this->success($profile + ['data_sections' => $sections]) : $this->notFound('Student not found');
            }
            $result = $this->api->get($id);
            if (is_array($result) && isset($result['data']) && is_array($result['data'])) {
                if (!in_array('finance', $sections, true)) {
                    unset($result['data']['active_sponsorships'], $result['data']['fee_waiver'], $result['data']['financial_migration'],
                        $result['data']['fee_balance'], $result['data']['balance']);
                }
                if (!in_array('contacts', $sections, true)) {
                    unset($result['data']['parents'], $result['data']['transport_arrangements']);
                }
                $result['data']['data_sections'] = $sections;
            }
            return $this->handleResponse($result);
        }

        if (!empty($segments)) {
            return $this->routeNestedGet(array_shift($segments), $id, $data, $segments);
        }

        return $this->handleResponse($this->api->list($data));
    }

    public function postStudent($id = null, $data = [], $segments = [])
    {
        if (!empty($segments)) {
            return $this->routeNestedPost(array_shift($segments), $id, $data, $segments);
        }

        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to create students')) {
            return $auth;
        }

        return $this->handleResponse($this->api->create($data));
    }

    /**
     * POST /api/students/existing-add
     * Register a learner already attending the school. This deliberately
     * bypasses admissions and does not add an admission/registration fee.
     */
    public function postExistingAdd($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to add existing students')) {
            return $auth;
        }
        return $this->handleResponse($this->api->addExistingStudent($data));
    }

    /**
     * POST /api/students/import-existing
     * Import learners who are already enrolled at the school.
     */
    public function postImportExisting($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to import existing students')) {
            return $auth;
        }
        if (!empty($_FILES['file'])) {
            $data['file'] = $this->persistImportFile($_FILES['file'], 'students_existing');
        }
        return $this->handleResponse($this->api->importExistingStudents($data));
    }

    public function putStudent($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Student ID is required');
        }

        if (!empty($segments)) {
            return $this->routeNestedPut(array_shift($segments), $id, $data, $segments);
        }

        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update students')) {
            return $auth;
        }
        // The learner's core record is edited by the School Administrator
        // only. Teachers and other staff may hold students_edit for narrower
        // tasks, but they read the student record and never change it.
        if (!in_array(4, array_map('intval', (array) $this->getUserRoleIds()), true)) {
            return $this->forbidden('Only the School Administrator can edit a student record. You have read-only access.');
        }

        return $this->handleResponse($this->api->update($id, $data));
    }

    public function deleteStudent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_DELETE_PERMS, 'Insufficient permission to delete students')) {
            return $auth;
        }

        if (!$id) {
            return $this->badRequest('Student ID is required');
        }

        if (!empty($segments)) {
            return $this->routeNestedDelete(array_shift($segments), $id, $data, $segments);
        }

        return $this->handleResponse($this->api->delete($id));
    }

    /* =====================================================
     * BULK OPERATIONS
     * ===================================================== */

    /**
     * POST /api/students/bulk-create
     * Accepts multipart file upload (file) or JSON payload
     */
    public function postBulkCreate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to bulk-create students')) {
            return $auth;
        }

        if (!empty($_FILES['file'])) {
            $data['file'] = $this->persistImportFile($_FILES['file'], 'students_bulk_create');
        }
        $result = $this->api->bulkCreate($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk-update
     * Accepts multipart file upload (file) or JSON payload
     */
    public function postBulkUpdate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to bulk-update students')) {
            return $auth;
        }

        if (!empty($_FILES['file'])) {
            $data['file'] = $this->persistImportFile($_FILES['file'], 'students_bulk_update');
        }
        $result = $this->api->bulkUpdate($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk-delete
     */
    public function postBulkDelete($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_DELETE_PERMS, 'Insufficient permission to bulk-delete students')) {
            return $auth;
        }

        $result = $this->api->bulkDelete($data);
        return $this->handleResponse($result);
    }

    /** Keep submitted import originals in the shared private imports/files directory. */
    private function persistImportFile(array $file, string $prefix): array
    {
        $stored = $this->uploadManaged($file, 'import_file', [
            'subdirectory' => 'files',
            'prefix' => $prefix,
        ]);
        $file['tmp_name'] = $stored['absolute_path'];
        $file['size'] = (int)$stored['file_size_bytes'];
        return $file;
    }

    /**
     * POST /api/students/bulk-promote
     */
    public function postBulkPromote($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->bulkPromoteStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/photo-upload
     * Uploads a profile photo for a student.
     * Expects multipart/form-data with: photo (file), student_id (field)
     */
    public function postPhotoUpload($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_UPLOAD_PERMS,
            'Insufficient permission to upload student photos'
        )) {
            return $auth;
        }

        $studentId = $id ?: ($data['student_id'] ?? null);
        if (!$studentId) {
            return $this->badRequest('Student ID is required for photo upload');
        }
        if (empty($_FILES['photo'])) {
            return $this->badRequest('No photo file provided');
        }
        $result = $this->api->uploadPhoto((int) $studentId, $_FILES['photo']);
        return $this->handleResponse($result);
    }

    public function getPhotoHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student photo history')) return $auth;
        return $this->handleResponse($this->api->getPhotoHistory((int) $id));
    }

    public function postPhotoApprove($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PHOTO_APPROVE_PERMS, 'Insufficient permission to approve student photos')) return $auth;
        return $this->handleResponse($this->api->approvePhoto((int) ($id ?: ($data['version_id'] ?? 0)), (string) ($data['reason'] ?? '')));
    }

    public function getPhotoPending($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PHOTO_APPROVE_PERMS, 'Insufficient permission to review student photos')) return $auth;
        return $this->handleResponse($this->api->getPendingPhotos());
    }

    public function postPhotoReject($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PHOTO_APPROVE_PERMS, 'Insufficient permission to reject student photos')) return $auth;
        return $this->handleResponse($this->api->rejectPhoto((int) ($id ?: ($data['version_id'] ?? 0)), (string) ($data['reason'] ?? '')));
    }

    /**
     * POST /api/students/qr-code-generate
     */
    public function postQrCodeGenerate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate student QR codes'
        )) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->generateQRCode((int) $studentId));
    }

    /**
     * POST /api/students/qr-code-generate-enhanced
     */
    public function postQrCodeGenerateEnhanced($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate enhanced student QR codes'
        )) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->generateQRCodeEnhanced((int) $studentId));
    }

    /**
     * POST /api/students/id-card-generate-legacy
     */
    public function postIdCardGenerateLegacy($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate student ID cards'
        )) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->generateStudentIDCard((int) $studentId));
    }

    /**
     * POST /api/students/id-card-generate-class
     */
    public function postIdCardGenerateClass($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate class ID cards'
        )) {
            return $auth;
        }

        $classId = $id ?? $data['class_id'] ?? null;
        if (!$classId) {
            return $this->badRequest('Class ID is required');
        }

        $streamId = $data['stream_id'] ?? null;
        return $this->handleResponse($this->api->generateClassIDCards((int) $classId, $streamId ? (int) $streamId : null));
    }

    /**
     * GET /api/students/id-card-get/{id}
     */
    public function getIdCardGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view student ID card details'
        )) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getIdCardPayload((int) $studentId));
    }

    /**
     * GET /api/students/id-card-statistics-get
     */
    public function getIdCardStatisticsGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            array_merge(self::STUDENT_ID_CARD_VIEW_PERMS, self::STUDENT_ID_CARD_EXPORT_PERMS),
            'Insufficient permission to view student ID card statistics'
        )) {
            return $auth;
        }

        return $this->handleResponse($this->api->getIdCardStatistics($data));
    }

    // ========================================
    // SECTION 5.5: ID Card Management Endpoints
    // ========================================

    /**
     * GET /api/students/id-card-meta
     * Returns academic years, classes, streams, card statuses, school settings, permissions
     */
    public function getIdCardMeta($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view ID card metadata'
        )) {
            return $auth;
        }

        $result = $this->studentService->getIdCardMeta($this->user);
        if (empty($result['success'])) {
            return $this->serverError('ID card metadata is temporarily unavailable');
        }
        return $this->success($result, 'ID card metadata loaded');
    }

    /**
     * GET /api/students/id-cards
     * Returns students with ID card status, accepts filters
     */
    public function getIdCards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view ID cards'
        )) {
            return $auth;
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $result = $this->studentService->getIdCards($this->user, $filters);
        if (empty($result['success'])) {
            return $this->serverError('Student ID card data is temporarily unavailable');
        }
        return $this->success($result, 'ID cards loaded');
    }

    /**
     * GET /api/students/card-status
     * Returns card status map for students (student_id -> status)
     * Used by Node.js gateway for aggregation
     */
    public function getCardStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view ID card status'
        )) {
            return $auth;
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        $result = $this->studentService->getCardStatus($this->user, $filters);
        if (empty($result['success'])) {
            return $this->serverError('Student ID card status is temporarily unavailable');
        }
        return $this->success($result, 'Card status loaded');
    }

    /**
     * GET /api/students/id-card-details/{studentId}
     * Returns full student card preview data including school profile, QR payload, card history
     */
    public function getIdCardDetails($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view ID card details'
        )) {
            return $auth;
        }

        $studentId = $id ?? $segments[0] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->studentService->getIdCardDetails((int) $studentId);
        if (!$result) {
            return $this->notFound('Student ID card not found');
        }

        return $this->success($result, 'ID card details loaded');
    }

    /**
     * POST /api/students/id-card-mark-printed/{cardId}
     * Mark card as printed
     */
    public function postIdCardMarkPrinted($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to mark ID card as printed'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $result = $this->studentService->markCardPrinted((int) $cardId, $this->user['id']);
        return $this->success($result, 'Card marked as printed');
    }

    /**
     * POST /api/students/id-card-mark-lost/{cardId}
     * Mark card as lost
     */
    public function postIdCardMarkLost($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to mark ID card as lost'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $result = $this->studentService->markCardLost((int) $cardId, $this->user['id']);
        return $this->success($result, 'Card marked as lost');
    }


    /**
     * POST /api/students/id-cards/print
     * Canonical single and bulk student ID-card PDF endpoint.
     */
    public function postIdCardsPrint(
        $id = null,
        $data = [],
        $segments = []
    ) {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to print student ID cards'
        )) {
            return $auth;
        }

        $studentIds = $data['student_ids'] ?? [];

        if (!is_array($studentIds) || $studentIds === []) {
            return $this->badRequest(
                'Select at least one student before printing.'
            );
        }

        $printerMode = $data['printer_mode'] ?? 'a4_pdf';
        $side = strtolower((string) ($data['side'] ?? 'both'));

        if (!in_array($side, ['front', 'back', 'both'], true)) {
            return $this->badRequest(
                'Card side must be front, back or both.'
            );
        }

        $result = $this->api->generateBulkIDCardsPDF(
            $studentIds,
            $printerMode,
            $side !== 'back',
            $side !== 'front'
        );

        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/generate
     * Enhanced to generate unique card numbers (KPA-ID-YYYY-000001 format), QR tokens, expiry years
     */
    public function postIdCardGenerate($id = null, $data = [], $segments = [])
    {
        return $this->idCardService->postIdCardGenerate($id, $data, $segments, $this);
    }

    /**
     * POST /api/students/id-card/generate-bulk
     * Bulk generate cards for selected students
     */
    public function postIdCardGenerateBulk($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to bulk generate ID cards'
        )) {
            return $auth;
        }

        $studentIds = $data['student_ids'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) {
            return $this->badRequest('Student IDs array is required');
        }

        $result = $this->studentService->generateIdCardsBulk($studentIds, $this->user['id'] ?? null);
        if (!empty($data['generate_qr'])) {
            $result['qr_generated'] = 0;
            $result['qr_errors'] = [];

            foreach ($studentIds as $studentId) {
                $qrResult = $this->api->generateQRCodeEnhanced((int) $studentId);
                if (($qrResult['status'] ?? false) === true || ($qrResult['success'] ?? false) === true) {
                    $result['qr_generated']++;
                } else {
                    $result['qr_errors'][(int) $studentId] = $qrResult['message'] ?? 'Failed to generate student QR code';
                }
            }
        }

        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/generate-bulk-pdf
     * Generate bulk PDF for selected students with A4 layout
     */
    public function postIdCardGenerateBulkPdf($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate bulk ID card PDFs'
        )) {
            return $auth;
        }

        $studentIds = $data['student_ids'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) {
            return $this->badRequest('Student IDs array is required');
        }

        $printMode = $data['print_mode'] ?? 'a4_sheet';
        $includeFront = $data['include_front'] ?? true;
        $includeBack = $data['include_back'] ?? true;

        $result = $this->api->generateBulkIDCardsPDF($studentIds, $printMode, $includeFront, $includeBack);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/print-single
     * Generate print-ready single card HTML for browser/system printing.
     * Returns renderer HTML (CR80, QR as data URI, front|back side-by-side)
     * which the frontend opens in a print window.
     */
    public function postIdCardPrintSingle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to print ID cards'
        )) {
            return $auth;
        }

        $studentId = $data['student_id'] ?? ($segments[0] ?? null);
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        $side = $data['side'] ?? 'both';
        $printMode = $data['print_mode'] ?? 'direct_card';
        $format = 'pdf';

        $result = $this->api->generatePrintableSingle((int) $studentId, $side, $printMode, $format);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/{cardId}/generate-qr
     * Generate QR code for a card
     */
    public function postIdCardGenerateQr($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate QR codes'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $result = $this->studentService->generateCardQrCode((int) $cardId, $this->user['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card-mark-issued/{cardId}
     * Mark card as issued
     */
    public function postIdCardMarkIssued($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to mark cards as issued'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $result = $this->studentService->markCardIssued((int) $cardId, $this->user['id'] ?? null);
        return $this->success($result, 'Card marked as issued');
    }

    /**
     * POST /api/students/id-card-renew/{cardId}
     * Renew expired card (create new card, mark old as replaced)
     */
    public function postIdCardRenew($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to renew ID cards'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $result = $this->studentService->renewCard((int) $cardId, $this->user['id'] ?? null);
        return $this->success($result, 'Card renewed');
    }

    /**
     * POST /api/students/id-card-replace/{cardId}
     * Replace lost/damaged card (create new card, mark old as replaced)
     */
    public function postIdCardReplace($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to replace ID cards'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $reason = $data['reason'] ?? 'other';
        $result = $this->studentService->replaceCard((int) $cardId, $reason, $this->user['id'] ?? null);
        return $this->success($result, 'Card replaced');
    }

    /**
     * POST /api/students/id-card/{cardId}/revoke
     * Revoke a card
     */
    public function postIdCardRevoke($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to revoke ID cards'
        )) {
            return $auth;
        }

        $cardId = $id ?? $segments[0] ?? null;
        if (!$cardId) {
            return $this->badRequest('Card ID is required');
        }

        $reason = $data['reason'] ?? null;
        $result = $this->studentService->revokeCard((int) $cardId, $reason, $this->user['id'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/id-card-history/{studentId}
     * Get card history for a student
     */
    public function getIdCardHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_VIEW_PERMS,
            'Insufficient permission to view ID card history'
        )) {
            return $auth;
        }

        $studentId = $id ?? $segments[0] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->studentService->getCardHistory((int) $studentId);
        return $this->success($result, 'Card history loaded');
    }

    /**
     * GET /api/students/id-card/verify/{cardNumber}
     * Verify card by number
     */
    public function getIdCardVerify($id = null, $data = [], $segments = [])
    {
        // Public endpoint - no auth required for verification
        $cardNumber = $id ?? $segments[0] ?? null;
        if (!$cardNumber) {
            return $this->badRequest('Card number is required');
        }

        $result = $this->studentService->verifyCard($cardNumber);
        if (!$result) {
            return $this->notFound('Card not found or invalid');
        }

        return $this->success($result, 'Card verified');
    }

    // ========================================
    // SECTION 6: Transfer Workflow
    // ========================================

    /**
     * POST /api/students/transfer/start-workflow
     */
    public function postTransferStartWorkflow($id = null, $data = [], $segments = [])
    {
        return $this->transferService->postTransferStartWorkflow($id, $data, $segments, $this);
    }

    /**
     * POST /api/students/transfer/verify-eligibility
     */
    public function postTransferVerifyEligibility($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_TRANSFER_PERMS, 'Insufficient permission to verify student transfers')) {
            return $auth;
        }

        $result = $this->api->verifyTransferEligibility($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/transfer/approve
     */
    public function postTransferApprove($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_TRANSFER_PERMS, 'Insufficient permission to approve student transfers')) {
            return $auth;
        }

        $result = $this->api->approveTransfer($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/transfer/execute
     */
    public function postTransferExecute($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_TRANSFER_PERMS, 'Insufficient permission to execute student transfers')) {
            return $auth;
        }

        $result = $this->api->executeTransfer($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/transfer/workflow-status
     */
    public function getTransferWorkflowStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view transfer status')) {
            return $auth;
        }

        $instanceId = $data['instance_id'] ?? $id ?? null;

        if ($instanceId === null) {
            return $this->badRequest('Instance ID is required');
        }

        $result = $this->api->getTransferWorkflowStatus($instanceId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/transfer/history/{id}
     */
    public function getTransferHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view transfer history')) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->getTransferHistory($studentId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Promotion Operations
    // ========================================

    /**
     * POST /api/students/promotion/single
     */
    public function postPromotionSingle($id = null, $data = [], $segments = [])
    {
        return $this->promotionService->postPromotionSingle($id, $data, $segments, $this);
    }

    /**
     * POST /api/students/promotion/multiple
     */
    public function postPromotionMultiple($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->promoteMultipleStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/entire-class
     */
    public function postPromotionEntireClass($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->promoteEntireClass($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/multiple-classes
     */
    public function postPromotionMultipleClasses($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->promoteMultipleClasses($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/graduate-grade9
     */
    public function postPromotionGraduateGrade9($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to graduate students')) {
            return $auth;
        }

        $result = $this->api->graduateGrade9Students($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotion/batches
     */
    public function getPromotionBatches($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view promotion batches')) {
            return $auth;
        }

        $result = $this->api->getPromotionBatches($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotion/history/{id}
     */
    public function getPromotionHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view promotion history')) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->getPromotionHistory($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/enrollment-history/{id}
     */
    public function getEnrollmentHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view enrollment history')) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->getEnrollmentHistory($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/by-class-get/{id}
     */
    public function getByClassGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view students by class')) {
            return $auth;
        }

        $classId = $id ?? $data['class_id'] ?? null;
        if ($classId === null) {
            return $this->badRequest('Class ID is required');
        }

        $result = $this->api->getStudentsByClass((int) $classId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/by-stream-get/{id}
     */
    public function getByStreamGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view students by stream')) {
            return $auth;
        }

        $streamId = $id ?? $data['stream_id'] ?? null;
        if ($streamId === null) {
            return $this->badRequest('Stream ID is required');
        }

        $result = $this->api->getStudentsByStream((int) $streamId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/get-all
     * Returns paginated list of all students with optional filtering
     */
    public function getGetAll($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view students')) {
            return $auth;
        }

        $result = $this->api->getAllStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotion-eligible
     */
    public function getPromotionEligible($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view promotion eligible students')) {
            return $auth;
        }

        $result = $this->api->getPromotionEligible($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotions
     */
    public function getPromotions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view promotions')) {
            return $auth;
        }

        $result = $this->api->getPromotions($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/roster-get/{classId}?stream_id={streamId}&year_id={yearId}
     */
    public function getRosterGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view class roster')) {
            return $auth;
        }

        $classId = $id ?? $data['class_id'] ?? null;
        if ($classId === null) {
            return $this->badRequest('Class ID is required');
        }

        $streamId = $data['stream_id'] ?? null;
        if ($streamId === null) {
            // Backward-compatible fallback for callers that only pass class ID.
            $result = $this->api->getStudentsByClass((int) $classId);
            return $this->handleResponse($result);
        }

        $yearId = $data['year_id'] ?? null;
        $result = $this->api->getClassRoster((int) $classId, (int) $streamId, $yearId !== null ? (int) $yearId : null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/academic-year-current
     */
    public function getAcademicYearCurrent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view academic year')) {
            return $auth;
        }

        $result = $this->api->getCurrentAcademicYear();
        return $this->success($result);
    }

    /**
     * GET /api/students/academic-year-get/{id}
     */
    public function getAcademicYearGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view academic year')) {
            return $auth;
        }

        $yearId = $id ?? $data['year_id'] ?? $data['id'] ?? null;
        if ($yearId === null) {
            // Keep backward compatibility for callers without an explicit ID.
            $result = $this->api->getCurrentAcademicYear();
            return $this->success($result);
        }

        $result = $this->api->getAcademicYear((int) $yearId);
        return $this->success($result);
    }

    /**
     * GET /api/students/academic-year-all
     */
    public function getAcademicYearAll($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view academic years')) {
            return $auth;
        }

        $result = $this->api->getAllAcademicYears($data);
        return $this->success($result);
    }

    /**
     * POST /api/students/academic-year-create
     */
    public function postAcademicYearCreate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_ACADEMIC_YEAR_MANAGE_PERMS, 'Insufficient permission to create academic years')) {
            return $auth;
        }

        if (empty($data['created_by'])) {
            $data['created_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        }

        $result = $this->api->createAcademicYear($data);
        return $this->success($result);
    }

    /**
     * POST /api/students/academic-year-create-next
     */
    public function postAcademicYearCreateNext($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_ACADEMIC_YEAR_MANAGE_PERMS, 'Insufficient permission to create next academic year')) {
            return $auth;
        }

        $userId = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
        if ($userId <= 0) {
            return $this->unauthorized('Authentication required');
        }

        $result = $this->api->createNextAcademicYear($userId);
        return $this->success($result);
    }

    /**
     * POST /api/students/academic-year-set-current
     */
    public function postAcademicYearSetCurrent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_ACADEMIC_YEAR_MANAGE_PERMS, 'Insufficient permission to set current academic year')) {
            return $auth;
        }

        $yearId = $data['year_id'] ?? $data['id'] ?? $id;
        if ($yearId === null) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->setCurrentAcademicYear((int) $yearId);
        return $this->success(['updated' => (bool) $result], 'Current academic year updated');
    }

    /**
     * PUT /api/students/academic-year-update-status
     */
    public function putAcademicYearUpdateStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_ACADEMIC_YEAR_MANAGE_PERMS, 'Insufficient permission to update academic year status')) {
            return $auth;
        }

        $yearId = $data['year_id'] ?? $data['id'] ?? $id;
        $status = $data['status'] ?? null;
        if ($yearId === null || $status === null) {
            return $this->badRequest('year_id and status are required');
        }

        $result = $this->api->updateAcademicYearStatus((int) $yearId, (string) $status);
        return $this->success(['updated' => (bool) $result], 'Academic year status updated');
    }

    /**
     * GET /api/students/academic-year-terms
     */
    public function getAcademicYearTerms($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view academic year terms')) {
            return $auth;
        }

        $yearId = $id ?? $data['year_id'] ?? null;
        if ($yearId === null) {
            return $this->badRequest('year_id is required');
        }

        $result = $this->api->getTermsForYear((int) $yearId);
        return $this->success($result);
    }

    /**
     * GET /api/students/academic-year-current-term
     */
    public function getAcademicYearCurrentTerm($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view current term')) {
            return $auth;
        }

        $result = $this->api->getCurrentTerm();
        return $this->success($result);
    }

    /**
     * GET /api/students/alumni-get
     */
    public function getAlumniGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view alumni')) {
            return $auth;
        }

        $result = $this->api->getAlumni($data);
        return $this->success($result);
    }

    /**
     * POST /api/students/alumni-update
     */
    public function postAlumniUpdate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update alumni')) {
            return $auth;
        }

        $result = $this->api->updateAlumni($data);
        return $this->success($result);
    }

    /**
     * POST /api/students/alumni-delete
     */
    public function postAlumniDelete($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to delete alumni')) {
            return $auth;
        }

        $result = $this->api->deleteAlumni($data);
        return $this->success($result);
    }

    /**
     * GET /api/students/enrollment-current
     */
    public function getEnrollmentCurrent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view current enrollments')) {
            return $auth;
        }

        $yearId = $data['year_id'] ?? null;
        $result = $this->api->getCurrentEnrollments($yearId !== null ? (int) $yearId : null);
        return $this->success($result);
    }

    // ========================================
    // SECTION 8: Parent/Guardian Management
    // ========================================

    /**
     * GET /api/students/parents/get/{id}
     */
    public function getParentsGet($id = null, $data = [], $segments = [])
    {
        if ($g = $this->studentSectionGuard($id ?? ($data['student_id'] ?? null), 'contacts')) return $g;
        return $this->parentService->getParentsGet($id, $data, $segments, $this);
    }

    /* =====================================================================
     * STUDENT LEADERSHIP, HOUSES & AWARDS
     *
     * Single governed source (school_leader / houses / student_awards)
     * feeding the student portfolio and longitudinal participation record.
     * Guarded by the students view permission set; learner records are
     * restricted to live (non-test) people server-side.
     * =================================================================== */

    /**
     * GET /api/students/leadership
     */
    public function getLeadership($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view leadership records')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->list($data));
    }

    /**
     * GET /api/students/leadership/positions
     */
    public function getLeadershipPositions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view leadership positions')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->positions($data));
    }

    /** POST /api/students/leadership/positions */
    public function postLeadershipPositions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to create a student leadership position')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->createPosition($data));
    }

    /** PUT /api/students/leadership/positions/{id} */
    public function putLeadershipPositions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to edit a student leadership position')) {
            return $auth;
        }
        if (!$id) {
            return $this->badRequest('Leadership position ID is required');
        }
        return $this->handleResponse($this->leadershipService->updatePosition((int)$id, $data));
    }

    /**
     * GET /api/students/leadership/history/{studentId}
     */
    public function getLeadershipHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view leadership history')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->history((int) $id));
    }

    /**
     * POST /api/students/leadership
     */
    public function postLeadership($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to assign leadership')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->create($data));
    }

    /**
     * PUT /api/students/leadership/{id}
     */
    public function putLeadership($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to edit leadership')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->update((int) $id, $data));
    }

    /**
     * DELETE /api/students/leadership/{id}
     */
    public function deleteLeadership($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to remove leadership')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->delete((int) $id));
    }

    /**
     * GET /api/students/houses
     */
    public function getHouses($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view houses')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->listHouses($data));
    }

    /**
     * POST /api/students/houses
     */
    public function postHouses($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to create a house')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->createHouse($data));
    }

    /**
     * PUT /api/students/houses/{id}
     */
    public function putHouses($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to edit a house')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->updateHouse((int) $id, $data));
    }

    /**
     * GET /api/students/awards
     */
    public function getAwards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view awards')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->listAwards($data));
    }

    /**
     * GET /api/students/awards/history/{studentId}
     */
    public function getAwardsHistory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view award history')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->history((int) $id));
    }

    /**
     * POST /api/students/awards
     */
    public function postAwards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to issue an award')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->createAward($data));
    }

    /**
     * PUT /api/students/awards/{id}
     */
    public function putAwards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to edit an award')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->updateAward((int) $id, $data));
    }

    /**
     * DELETE /api/students/awards/{id}
     */
    public function deleteAwards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to remove an award')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->deleteAward((int) $id));
    }

    /**
     * GET /api/students/awards/catalogue
     *      Categories with their active types (cascade selects, modal).
     */
    public function getAwardsCatalogue($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view award types')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->getAwardCatalog());
    }

    /**
     * GET /api/students/awards/types
     */
    public function getAwardsTypes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view award types')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->listAwardTypes($data));
    }

    /**
     * GET /api/students/awards/categories
     */
    public function getAwardsCategories($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view award categories')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->listAwardCategories());
    }

    /**
     * POST /api/students/awards/types
     */
    public function postAwardsTypes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to create an award type')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->createAwardType($data));
    }

    /**
     * PUT /api/students/awards/types/{id}
     */
    public function putAwardsTypes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to edit an award type')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->updateAwardType((int) $id, $data));
    }

    /**
     * DELETE /api/students/awards/types/{id}
     */
    public function deleteAwardsTypes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to remove an award type')) {
            return $auth;
        }
        return $this->handleResponse($this->leadershipService->deleteAwardType((int) $id));
    }

    /**
     * POST /api/students/awards/certificate
     *      POST /api/students/awards/certificate/{awardId}
     *
     * Generate certificate PDF(s) for one award or a batch of award IDs.
     * Body (batch): { "award_ids": [5, 6] }
     */
    public function postAwardsCertificate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeLeadershipManagement('Insufficient permission to print certificates')) {
            return $auth;
        }
        $service = $this->contract('App\API\Modules\students\AwardCertificateService', $this->db->getConnection());
        $operatorId = (int) $this->getUserId();
        $awardIds = $data['award_ids'] ?? [];
        if (is_array($awardIds) && !empty($awardIds)) {
            $awardIds = array_values(array_unique(array_map('intval', $awardIds)));
        } elseif ($id) {
            $awardIds = [(int) $id];
        } else {
            return $this->badRequest('award_ids or an award ID is required');
        }
        $result = $service->generateForAwards($awardIds, $operatorId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/parents/list
     */
    public function getParentsList($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        // Class-parent contacts are a staff self-service view. Management may
        // request the full parent directory; everyone else is restricted to
        // students in their own class-teacher stream.
        $roles = $this->getUserRoleIds();
        if (empty(array_intersect($roles, [2, 3, 4, 5, 6, 10, 24, 63]))) {
            $data['class'] = 'self';
            $data['staff_user_id'] = (int) $this->getUserId();
        }
        if (($data['class'] ?? '') === 'self') {
            return $this->handleResponse($this->familyGroupsManager->getClassParentContacts((int) ($data['staff_user_id'] ?? $this->getUserId())));
        }

        return $this->handleResponse(
            $this->familyGroupsManager->getParents($data)
        );
    }

    /**
     * GET /api/students/parents/children
     */
    public function getParentsChildren($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        $result = $this->familyGroupsManager->getParentDetails((int) $parentId);
        if (is_array($result) && ($result['success'] ?? false)) {
            $result['data'] = $result['data']['children'] ?? [];
        }

        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/add
     */
    public function postParentsAdd($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to link parent records')) {
            return $auth;
        }

        $result = $this->api->addParentToStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/create
     */
    public function postParentsCreate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(array_merge(self::STUDENT_EDIT_PERMS, self::STUDENT_CREATE_PERMS), 'Insufficient permission to create parent records')) {
            return $auth;
        }

        return $this->handleResponse(
            $this->familyGroupsManager->createParent($data)
        );
    }

    /**
     * POST /api/students/parents/update
     */
    public function postParentsUpdate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        return $this->handleResponse(
            $this->familyGroupsManager->updateParent((int) $parentId, $data)
        );
    }

    /**
     * PUT /api/students/parents/update/{id}
     */
    public function putParentsUpdate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) {
            return $auth;
        }

        $parentId = $id ?? $data['parent_id'] ?? null;

        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        $result = $this->api->updateParentInfo($parentId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/remove
     */
    public function postParentsRemove($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to unlink parent records')) {
            return $auth;
        }

        $result = $this->api->removeParentFromStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/delete
     */
    public function postParentsDelete($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to delete parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        return $this->handleResponse(
            $this->familyGroupsManager->deleteParent((int) $parentId)
        );
    }

    /**
     * POST /api/students/parents/link-child
     */
    public function postParentsLinkChild($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to link parent/child records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? null;
        $studentId = $data['student_id'] ?? null;

        if (!$parentId || !$studentId) {
            return $this->badRequest('Parent ID and Student ID are required');
        }

        $linkData = $data;
        unset($linkData['parent_id'], $linkData['student_id']);

        return $this->handleResponse(
            $this->familyGroupsManager->linkParentToStudent((int) $parentId, (int) $studentId, $linkData)
        );
    }

    /**
     * POST /api/students/parents/unlink-child
     */
    public function postParentsUnlinkChild($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to unlink parent/child records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? null;
        $studentId = $data['student_id'] ?? null;

        if (!$parentId || !$studentId) {
            return $this->badRequest('Parent ID and Student ID are required');
        }

        return $this->handleResponse(
            $this->familyGroupsManager->unlinkParentFromStudent((int) $parentId, (int) $studentId)
        );
    }

    /**
     * GET /api/students/parents/available-students
     */
    public function getParentsAvailableStudents($id = null, $data = [], $segments = [])
    {
        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        return $this->handleResponse(
            $this->familyGroupsManager->getAvailableStudentsForParent((int) $parentId)
        );
    }

    /**
     * GET /api/students/without-parents
     */
    public function getWithoutParents($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view students without parents')) {
            return $auth;
        }

        return $this->handleResponse(
            $this->familyGroupsManager->getStudentsWithoutParents()
        );
    }

    // ========================================
    // SECTION 9: Student Profile & Insights
    // ========================================

    /**
     * GET /api/students/profile-get/{id}
     */
    public function getProfileGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student profiles')) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getProfile($studentId));
    }

    /**
     * GET /api/students/attendance-get/{id}
     */
    public function getAttendanceGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student attendance')) {
            return $auth;
        }
        if ($g = $this->studentSectionGuard($id ?? ($data['student_id'] ?? null), 'attendance')) return $g;

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getAttendance($studentId, $data));
    }

    /**
     * GET /api/students/performance-get/{id}
     */
    public function getPerformanceGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student performance')) {
            return $auth;
        }
        if ($g = $this->studentSectionGuard($id ?? ($data['student_id'] ?? null), 'performance')) return $g;

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getPerformance($studentId, $data));
    }

    /**
     * GET /api/students/fees-get/{id}
     */
    public function getFeesGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_FEES_PERMS, 'Insufficient permission to view student fees')) {
            return $auth;
        }
        if ($g = $this->studentSectionGuard($id ?? ($data['student_id'] ?? null), 'finance')) return $g;

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getFees($studentId));
    }

    /**
     * GET /api/students/qr-info-get/{id}
     */
    public function getQrInfoGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student QR information')) {
            return $auth;
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->getQrInfo($studentId));
    }

    /**
     * GET /api/students/statistics-get
     */
    // ==================== STUDENT CLEARANCE ====================

    /**
     * GET /api/students/clearance-leavers?academic_year_id=&outcome=&state=&search=
     * Learners who left in the year (graduated, transferred, withdrawn) with
     * their final fee position and clearance progress.
     */
    public function getClearanceLeavers($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_view', 'students_view_all', 'finance_view'])) {
            return $this->forbidden('You cannot view student clearance');
        }
        return $this->clearanceCall(function ($service, $db) use ($data) {
            $filters = is_array($data) ? $data : [];
            return $service->leavers((int) \App\API\Services\YearScope::listYear($db, $filters)['year_id'], $filters);
        }, 'Clearance loaded');
    }

    /** POST /api/students/clearance-start {student_id, academic_year_id} */
    public function postClearanceStart($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_edit', 'students_manage', 'finance_manage', 'students_create'])) {
            return $this->forbidden('You cannot start a clearance');
        }
        $data = is_array($data) ? $data : [];
        return $this->clearanceCall(fn ($service) => $service->start(
            (int) ($data['student_id'] ?? 0),
            (int) ($data['academic_year_id'] ?? 0),
            (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0) ?: null
        ), 'Clearance started');
    }

    /** POST /api/students/clearance-decide {student_id, academic_year_id, type, status, note} */
    public function postClearanceDecide($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_edit', 'students_manage', 'finance_manage', 'students_create', 'library_manage', 'inventory_manage'])) {
            return $this->forbidden('You cannot record a clearance decision');
        }
        $data = is_array($data) ? $data : [];
        return $this->clearanceCall(fn ($service) => $service->decide(
            (int) ($data['student_id'] ?? 0),
            (int) ($data['academic_year_id'] ?? 0),
            (string) ($data['type'] ?? ''),
            (string) ($data['status'] ?? ''),
            isset($data['note']) ? (string) $data['note'] : null,
            (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0) ?: null
        ), 'Clearance updated');
    }

    /**
     * GET /api/students/clearance-document?student_id=&academic_year_id=
     * The learner's exit document (leaving certificate or transfer letter):
     * what it will print, the stored head teacher's report and defaults.
     */
    public function getClearanceDocument($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_view', 'students_view_all', 'students_edit', 'students_manage'])) {
            return $this->forbidden('You cannot view exit documents');
        }
        $data = is_array($data) ? $data : [];
        return $this->clearanceCall(function ($service, $db) use ($data) {
            return (new \App\API\Services\LearnerExitDocumentService($db))->context(
                (int) ($data['student_id'] ?? 0),
                (int) ($data['academic_year_id'] ?? 0)
            );
        }, 'Exit document loaded');
    }

    /**
     * POST /api/students/clearance-document-issue
     * {student_id, academic_year_id, academic_report, conduct, co_curricular, leaving_date,
     *  destination_school, destination_school_code, destination_county, destination_sub_county, reason}
     * Issues (or reprints) the leaving certificate or transfer letter once the
     * learner is cleared, and returns the PDF.
     */
    public function postClearanceDocumentIssue($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_edit', 'students_manage', 'students_create'])) {
            return $this->forbidden('You cannot issue exit documents');
        }
        if (method_exists($this, 'userCanPrint') && !$this->userCanPrint('students')) {
            return $this->forbidden('You do not have permission to print');
        }
        $data = is_array($data) ? $data : [];
        return $this->clearanceCall(function ($service, $db) use ($data) {
            $issued = (new \App\API\Services\LearnerExitDocumentService($db))->issue(
                (int) ($data['student_id'] ?? 0),
                (int) ($data['academic_year_id'] ?? 0),
                $data,
                (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0) ?: null
            );
            $url = (new \App\API\Services\DownloadService())->printUrlForAbsolutePath($issued['path'], 1800);
            return [
                'serial_no' => $issued['serial_no'],
                'document_type' => $issued['document_type'],
                'filename' => basename($issued['path']),
                'mime_type' => 'application/pdf',
                'url' => $url,
                'download_url' => $url,
            ];
        }, 'Document issued');
    }

    /**
     * POST /api/students/clearance-transfer-complete {student_id, academic_year_id, nemis_released_at}
     * Completes a transfer after clearance and the transfer letter.
     */
    public function postClearanceTransferComplete($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['students_edit', 'students_manage', 'students_transfers_approve', 'students_transfers_edit'])) {
            return $this->forbidden('You cannot complete a transfer');
        }
        $data = is_array($data) ? $data : [];
        return $this->clearanceCall(function ($service, $db) use ($data) {
            return (new \App\API\Services\LearnerExitDocumentService($db))->completeTransfer(
                (int) ($data['student_id'] ?? 0),
                (int) ($data['academic_year_id'] ?? 0),
                !empty($data['nemis_released_at']) ? (string) $data['nemis_released_at'] : null,
                (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0) ?: null
            );
        }, 'Transfer completed');
    }

    private function clearanceCall(callable $run, string $message)
    {
        try {
            $db = $this->getDb()->getConnection();
            return $this->success($run(new \App\API\Services\StudentClearanceService($db), $db), $message);
        } catch (\InvalidArgumentException $e) {
            return $e->getCode() === 404 ? $this->notFound($e->getMessage()) : $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] clearance: ' . $e->getMessage());
            return $this->serverError('Failed to process the clearance', 'An internal error occurred.');
        }
    }

    public function getStatisticsGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view student statistics')) {
            return $auth;
        }

        return $this->handleResponse($this->api->getStudentStatistics($data));
    }

    /**
     * GET /api/students/my-profile
     * Resolve the authenticated user to a learner record and return the full profile.
     */
    public function getMyProfile($id = null, $data = [], $segments = [])
    {
        $studentIds = $this->studentProfileManager->resolveStudentIds($this->user ?? []);

        if (empty($studentIds)) {
            return $this->notFound('No student profile is linked to the current user');
        }

        return $this->handleResponse($this->api->getProfile((int) $studentIds[0]));
    }

    /**
     * GET /api/students/my-children
     * Resolve the authenticated user to one or more parent records and return linked learners.
     */
    public function getMyChildren($id = null, $data = [], $segments = [])
    {
        $parentIds = $this->studentProfileManager->resolveParentIds($this->user ?? []);
        if (empty($parentIds)) {
            return $this->success([], 'No linked student profiles found for the current user');
        }

        $children = $this->familyGroupsManager->getChildrenForParentIds($parentIds);
        if (empty($children['success'])) {
            return $this->badRequest($children['message'] ?? 'Failed to load linked student profiles');
        }

        $studentIds = $children['data'] ?? [];
        if (empty($studentIds)) {
            return $this->success([], 'No linked student profiles found for the current user');
        }

        $profiles = [];
        foreach ($studentIds as $studentId) {
            $profile = $this->api->getProfile($studentId);
            if (is_array($profile) && ($profile['success'] ?? false) && !empty($profile['data'])) {
                $profiles[] = $profile['data'];
            }
        }

        return $this->success($profiles, 'Linked student profiles retrieved');
    }

    // ========================================
    // SECTION 10: Discipline Management
    // ========================================

    /**
     * GET /api/students/discipline-get
     */
    public function getDisciplineGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_DISCIPLINE_PERMS, 'Insufficient permission to view discipline records')) {
            return $auth;
        }
        if ($g = $this->studentSectionGuard($id ?? ($data['student_id'] ?? null), 'discipline')) return $g;

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId !== null) {
            return $this->handleResponse($this->api->getDisciplineRecordsInfo($studentId));
        }

        return $this->handleResponse($this->api->listDisciplineCases($data));
    }

    /**
     * POST /api/students/discipline-record
     */
    public function postDisciplineRecord($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            ['students_discipline_create', 'students_discipline_edit', 'students_discipline_approve'],
            'Insufficient permission to record discipline cases'
        )) {
            return $auth;
        }

        $studentId = $data['student_id'] ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->api->recordDisciplineCase($studentId, $data));
    }

    /**
     * PUT /api/students/discipline-update/{id}
     */
    public function putDisciplineUpdate($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            ['students_discipline_edit', 'students_discipline_approve'],
            'Insufficient permission to update discipline cases'
        )) {
            return $auth;
        }

        $recordId = $id ?? $data['record_id'] ?? null;
        if ($recordId === null) {
            return $this->badRequest('Discipline record ID is required');
        }

        return $this->handleResponse($this->api->updateDisciplineCase($recordId, $data));
    }

    /**
     * POST /api/students/discipline-resolve
     */
    public function postDisciplineResolve($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            ['students_discipline_approve', 'students_discipline_edit'],
            'Insufficient permission to resolve discipline cases'
        )) {
            return $auth;
        }

        $recordId = $data['record_id'] ?? $id ?? null;
        if ($recordId === null) {
            return $this->badRequest('Discipline record ID is required');
        }

        return $this->handleResponse($this->api->resolveDisciplineCase($recordId, $data));
    }

    // ========================================
    // SECTION 11: Medical Records
    // ========================================

    /**
     * GET /api/students/medical/get/{id}
     */
    public function getMedicalGet($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(
            ['students_view', 'students_view_all', 'students_view_own', 'students_edit'],
            'Insufficient permission to view student medical records'
        )) {
            return $auth;
        }

        if (!$id) {
            return $this->badRequest('Student ID required');
        }

        return $this->success(
            $this->mediaManager->listMedia([
                'context' => 'students',
                'entity_id' => $id
            ])
        );
    }

    /* =====================================================
     * FAMILY GROUPS (FIXED NAMING)
     * ===================================================== */

    public function getFamilyParentGet($id = null, $data = [])
    {
        return $this->parentService->getFamilyParentGet($id, $data, [], $this);
    }

    public function putFamilyParentUpdate($id = null, $data = [])
    {
        return $this->parentService->putFamilyParentUpdate($id, $data, [], $this);
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function getAuthenticatedUserId(): ?int
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $userId ? (int) $userId : null;
    }

    public function handleResponse($result)
    {
        if (!is_array($result)) {
            return $this->success($result);
        }

        // Preferred module format: ['status' => 'success|error', 'code' => int, 'message' => ..., 'data' => ...]
        if (isset($result['status'])) {
            $status = strtolower((string) $result['status']);
            $code = (int) ($result['code'] ?? 0);
            $message = $result['message'] ?? ($status === 'success' ? 'Success' : 'Operation failed');
            $data = $result['data'] ?? null;

            if ($status === 'success') {
                return $this->success($data, $message);
            }

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

            return $this->badRequest($message, is_array($data) ? $data : null);
        }

        // Legacy format: ['success' => bool, 'message' => ..., 'data' => ...]
        if (isset($result['success'])) {
            return $result['success']
                ? $this->success($result['data'] ?? null, $result['message'] ?? 'Success')
                : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? null);
        }

        return $this->success($result);
    }

    /* =====================================================
     * NESTED ROUTING HELPERS
     * ===================================================== */

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $resourceCamel = $this->toCamelCase($resource);
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        $methodName = 'get' . ucfirst($resourceCamel);
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, $segments);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $resourceCamel = $this->toCamelCase($resource);
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        $methodName = 'post' . ucfirst($resourceCamel);
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, $segments);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $resourceCamel = $this->toCamelCase($resource);
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        $methodName = 'put' . ucfirst($resourceCamel);
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            return $this->$methodName($id, $data, $segments);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested DELETE requests to appropriate methods
     */
    private function routeNestedDelete($resource, $id, $data, $segments)
    {
        $resourceCamel = $this->toCamelCase($resource);
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;

        $methodName = 'delete' . ucfirst($resourceCamel);
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            return $this->$methodName($id, $data, $segments);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * GET /api/students/special-needs
     * List students that have recorded health conditions, disability notes, or special requirements.
     */
    public function getSpecialNeeds($id = null, $data = [], $segments = [])
    {
        try {
            return $this->success($this->studentInsightsService->listHealthSpecialNeeds(array_merge($_GET, $data)));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/performance-meta
     */
    public function getPerformanceMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $requestedContext = $_GET['context'] ?? null;
        $contextRes = $this->studentService->resolveContext($this->user, $requestedContext);
        if (!$contextRes['allowed']) {
            return $this->forbidden($contextRes['message'] ?? 'Forbidden');
        }

        try {
            return $this->success($this->studentInsightsService->getPerformanceMeta());
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/performance-overview
     */
    public function getPerformanceOverview($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $requestedContext = $_GET['context'] ?? null;
        $contextRes = $this->studentService->resolveContext($this->user, $requestedContext);
        if (!$contextRes['allowed']) {
            return $this->forbidden($contextRes['message'] ?? 'Forbidden');
        }

        try {
            return $this->success($this->studentInsightsService->getPerformanceOverview(
                $this->user,
                $contextRes['context'],
                array_merge($_GET, $data)
            ));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /** POST /api/students/ai-support-planning-queue */
    public function postAiSupportPlanningQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $contextRes = $this->studentService->resolveContext($this->user, $data['context'] ?? ($_GET['context'] ?? null));
        if (!$contextRes['allowed']) return $this->forbidden($contextRes['message'] ?? 'Forbidden');
        $aiContext = ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique((array) ($this->user['effective_permissions'] ?? $this->user['permissions'] ?? []))), 'request_id' => $_SERVER['REQUEST_ID'] ?? ''];
        try {
            $this->contract('App\\API\\Services\\WorkflowAuthorization')->authorize('learners.support_planning', $aiContext);
            $filters = \App\API\Services\LearnerSupportAiScopeService::sanitizeFilters(array_merge($_GET, $data));
            $input = $this->studentInsightsService->buildSupportPlanningInput(
                $this->user,
                $contextRes['context'],
                $filters
            );
            return $this->accepted($this->contract('App\\API\\Services\\DraftRepository')->queue('learners.support_planning', $aiContext, $input, [
                'subject_type' => 'learner_support_planning',
                'scope' => 'authorized_aggregate_learner_signals',
                'support_context' => (string) $contextRes['context'],
                'support_filters' => $filters,
            ]), 'Learner support planning queued');
        } catch (\DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (\Throwable $e) { return $this->serverError('Unable to queue learner support planning'); }
    }

    /** GET /api/students/ai-support-planning-reviews?scope=own|review */
    public function getAiSupportPlanningReviews($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiSupportPlanning()) {
            return $this->forbidden('Learner support review permission is required');
        }
        try {
            return $this->success([
                'drafts' => $this->contract('App\\API\\Services\\DraftRepository')->listForReview(
                    $this->getDb()->getConnection(),
                    (int) ($this->getUserId() ?? 0),
                    $review,
                    'learners'
                ),
                'scope' => $review ? 'review' : 'own',
            ], 'Learner support AI reviews retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] learner support review list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load learner support reviews');
        }
    }

    /** POST /api/students/ai-support-planning-approve/{id} */
    public function postAiSupportPlanningApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->canApproveAiSupportPlanning()) {
            return $this->forbidden('Learner support review permission is required');
        }
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract('App\\API\\Services\\DraftRepository')->approve(
                $this->getDb()->getConnection(),
                $draftId,
                (int) ($this->getUserId() ?? 0),
                'learners.support_planning'
            );
            return $this->success([
                'draft_id' => $draftId,
                'status' => 'approved',
                'review_only' => true,
                'draft' => $approved['draft'] ?? [],
            ], 'Learner support review approved for staff guidance');
        } catch (\DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] learner support review approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve learner support review');
        }
    }

    private function canApproveAiSupportPlanning(): bool
    {
        return $this->userHasAny(
            ['students_edit', 'students_view_all'],
            [3, 4, 5, 10],
            ['school administrator', 'headteacher', 'deputy head academic', 'school counselor', 'school counsellor', 'admin']
        );
    }

    /**
     * GET /api/students/performance-full/{studentId}
     */
    public function getPerformanceFull($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $studentId = $id !== null ? (int)$id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $requestedContext = $_GET['context'] ?? null;
        $contextRes = $this->studentService->resolveContext($this->user, $requestedContext);
        if (!$contextRes['allowed']) {
            return $this->forbidden($contextRes['message'] ?? 'Forbidden');
        }

        try {
            $payload = $this->studentInsightsService->getPerformanceFull(
                $studentId,
                $this->user,
                $contextRes['context'],
                array_merge($_GET, $data)
            );
            if (!$payload) {
                return $this->notFound('Student not found');
            }

            return $this->success($payload);
        } catch (\RuntimeException $e) { \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /* =====================================================
     * DISCIPLINE ENDPOINTS
     * ===================================================== */

    /**
     * GET /api/students/discipline-meta
     */
    public function getDisciplineMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            return $this->success($this->studentInsightsService->getDisciplineMeta());
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/discipline-cases
     */
    public function getDisciplineCases($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            return $this->success($this->studentInsightsService->listDisciplineCases(array_merge($_GET, $data)));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/discipline-case/{caseId}
     */
    public function getDisciplineCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $payload = $this->studentInsightsService->getDisciplineCase($caseId);
            if (!$payload) {
                return $this->notFound('Discipline case not found');
            }

            return $this->success($payload);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * PUT /api/students/discipline-case/{caseId}
     */
    public function putDisciplineCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $this->studentInsightsService->updateDisciplineCase($caseId, $data, (int)$this->user['id']);
            return $this->success(['message' => 'Discipline case updated successfully']);
        } catch (\RuntimeException $e) { \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return $this->serverError('An internal error occurred.'); } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /* =====================================================
     * SPECIAL NEEDS / IEP ENDPOINTS
     * ===================================================== */

    /**
     * GET /api/students/special-needs-meta
     */
    public function getSpecialNeedsMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            return $this->success($this->studentInsightsService->getSpecialNeedsMeta());
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/special-needs
     * Lists IEP records (new method for IEPs, distinct from health records)
     */
    public function getSpecialNeedsIEPs($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $filters = array_merge($_GET, $data);
            $roles = $this->getUserRoleIds();
            $roleName = strtolower((string) ($this->user['role'] ?? $this->user['role_name'] ?? ''));
            // Class teachers see IEPs only for learners in their assigned streams.
            if (in_array(7, $roles, true) || strpos($roleName, 'class teacher') !== false) {
                $filters['_class_teacher_user_id'] = (int) $this->user['id'];
            }
            return $this->success($this->studentInsightsService->listSpecialNeedsIEPs($filters));
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/students/special-needs-ieps/{iepId}
     */
    public function getSpecialNeedsIepDetail($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $iepId = $id !== null ? (int)$id : null;
        if ($iepId === null) {
            return $this->badRequest('IEP ID is required');
        }

        try {
            $payload = $this->studentInsightsService->getSpecialNeedsIepDetail($iepId);
            if (!$payload) {
                return $this->notFound('IEP not found');
            }

            return $this->success($payload);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * POST /api/students/special-needs-ieps
     * Creates a new IEP record for an active student.
     */
    public function postSpecialNeedsIeps($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to create IEP records')) {
            return $auth;
        }

        try {
            $payload = $this->studentInsightsService->createSpecialNeedsIep($data, (int)$this->user['id']);
            return $this->success($payload, 'IEP record created successfully.');
        } catch (\RuntimeException $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest($e->getMessage());
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        }
    }

    /* =====================================================
     * FAMILY GROUPS ENDPOINTS (NEW)
     ===================================================== */

    /**
     * GET /api/students/family-groups-meta-v2
     */
    public function getFamilyGroupsMetaV2($id = null, $data = [], $segments = [])
    {
        return $this->parentService->getFamilyGroupsMetaV2($id, $data, $segments, $this);
    }

    /**
     * GET /api/students/family-groups-v2
     */
    public function getFamilyGroupsV2($id = null, $data = [], $segments = [])
    {
        return $this->parentService->getFamilyGroupsV2($id, $data, $segments, $this);
    }

    /**
     * GET /api/students/family-group/{parentId}
     */
    public function getFamilyGroup($id = null, $data = [], $segments = [])
    {
        return $this->parentService->getFamilyGroup($id, $data, $segments, $this);
    }

    /**
     * GET /api/students/family-groups/stats
     */
    public function getFamilyGroupsStats($id = null, $data = [], $segments = [])
    {
        return $this->parentService->getFamilyGroupsStats($id, $data, $segments, $this);
    }

    /**
     * POST /api/students/family-group/{parentId}/link-student
     */
    public function postFamilyGroupLinkStudent($id = null, $data = [], $segments = [])
    {
        return $this->parentService->postFamilyGroupLinkStudent($id, $data, $segments, $this);
    }

    /* =====================================================
     * STUDENT PROMOTION ENDPOINTS (NEW)
     ===================================================== */

    /**
     * GET /api/students/promotion-meta-v2
     */
    public function getPromotionMetaV2($id = null, $data = [], $segments = [])
    {
        return $this->promotionService->getPromotionMetaV2($id, $data, $segments, $this);
    }

    /**
     * GET /api/students/promotion-candidates-v2
     */
    public function getPromotionCandidatesV2($id = null, $data = [], $segments = [])
    {
        return $this->promotionService->getPromotionCandidatesV2($id, $data, $segments, $this);
    }

    /**
     * POST /api/students/promotion-execute-v2
     */
    public function postPromotionExecuteV2($id = null, $data = [], $segments = [])
    {
        return $this->promotionService->postPromotionExecuteV2($id, $data, $segments, $this);
    }

    /* =====================================================
     * STUDENT COUNSELING ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/counseling-meta
     */
    public function getCounselingMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access counseling data');
        }

        return $this->handleResponse($this->studentProfileManager->getCounselingMeta());
    }

    /**
     * GET /api/students/counseling-cases
     */
    public function getCounselingCases($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access counseling data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getCounselingCases($filters, $this->user));
    }

    /**
     * GET /api/students/counseling-case/{caseId}
     */
    public function getCounselingCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getCounselingCase($caseId, $this->user['role'] ?? ''));
    }

    /* =====================================================
     * STUDENT HEALTH ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/health-meta
     */
    public function getHealthMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        return $this->handleResponse($this->studentProfileManager->getHealthMeta());
    }

    /**
     * GET /api/students/health-records
     */
    public function getHealthRecords($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getHealthRecords($filters, $this->user['role'] ?? ''));
    }

    /**
     * GET /api/students/health-record/{recordId}
     */
    public function getHealthRecord($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $recordId = $id !== null ? (int) $id : null;
        if ($recordId === null) {
            return $this->badRequest('Record ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getHealthRecord($recordId, $this->user['role'] ?? ''));
    }

    /* =====================================================
     * CATERING BOARDING ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/catering-boarding-meta
     */
    public function getCateringBoardingMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access catering data');
        }

        return $this->handleResponse($this->studentProfileManager->getCateringBoardingMeta());
    }

    /**
     * GET /api/students/catering-boarding-students
     */
    public function getCateringBoardingStudents($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access catering data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getCateringBoardingStudents($filters));
    }

    /**
     * GET /api/students/catering-boarding-summary
     */
    public function getCateringBoardingSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access catering data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getCateringBoardingSummary($filters));
    }

    /**
     * GET /api/students/catering-boarding-student/{studentId}
     */
    public function getCateringBoardingStudent($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access catering data');
        }

        $studentId = $id !== null ? (int) $id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getCateringBoardingStudent($studentId));
    }

    /**
     * POST /api/students/catering-menu-plan
     */
    public function postCateringMenuPlan($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to plan meals');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postCateringMenuPlan($data, $userId));
    }

    /**
     * GET /api/students/catering-food-requisition
     */
    public function getCateringFoodRequisition($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['cateress', 'catering_manager', 'admin'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access food requisition');
        }

        return $this->handleResponse($this->studentProfileManager->getCateringFoodRequisition());
    }

    /* =====================================================
     * BOARDING MASTER / MATRON ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/boarding-meta
     */
    public function getBoardingMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access boarding data');
        }

        return $this->handleResponse($this->studentProfileManager->getBoardingMeta());
    }

    /**
     * GET /api/students/boarding-students
     */
    public function getBoardingStudents($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access boarding data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getBoardingStudents($filters));
    }

    /**
     * GET /api/students/boarding-summary
     */
    public function getBoardingSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access boarding data');
        }

        return $this->handleResponse($this->studentProfileManager->getBoardingSummary());
    }

    /**
     * GET /api/students/boarding-student/{studentId}
     */
    public function getBoardingStudent($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access boarding data');
        }

        $studentId = $id !== null ? (int) $id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getBoardingStudent($studentId));
    }

    /**
     * POST /api/students/boarding-assign-dorm
     */
    public function postBoardingAssignDorm($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to assign dormitories');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postBoardingAssignDorm($data, $userId));
    }

    /* =====================================================
     * DRIVER / TRANSPORT ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/transport-meta
     */
    public function getTransportMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access transport data');
        }

        return $this->handleResponse($this->studentProfileManager->getTransportMeta());
    }

    /**
     * GET /api/students/transport-passengers
     */
    public function getTransportPassengers($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access transport data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getTransportPassengers($filters, $this->user));
    }

    /**
     * GET /api/students/transport-summary
     */
    public function getTransportSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access transport data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getTransportSummary($filters));
    }

    /**
     * GET /api/students/transport-passenger/{studentId}
     */
    public function getTransportPassenger($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access transport data');
        }

        $studentId = $id !== null ? (int) $id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getTransportPassenger($studentId));
    }

    /**
     * POST /api/students/transport-attendance
     */
    public function postTransportAttendance($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to mark transport attendance');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postTransportAttendance($data, $userId));
    }

    /**
     * POST /api/students/transport-incident
     */
    public function postTransportIncident($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['driver', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to report incidents');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postTransportIncident($data, $userId));
    }

    /* =====================================================
     * CHAPLAIN / COUNSELOR WELFARE ENDPOINTS
     ===================================================== */

    /**
     * GET /api/students/welfare-meta
     */
    public function getWelfareMeta($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access welfare data');
        }

        return $this->handleResponse($this->studentProfileManager->getWelfareMeta());
    }

    /**
     * GET /api/students/welfare-cases
     */
    public function getWelfareCases($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access welfare data');
        }

        $filters = array_merge($_GET, is_array($data) ? $data : []);
        return $this->handleResponse($this->studentProfileManager->getWelfareCases($filters, $this->user));
    }

    /**
     * GET /api/students/welfare-case/{caseId}
     */
    public function getWelfareCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to access welfare data');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        return $this->handleResponse($this->studentProfileManager->getWelfareCase($caseId));
    }

    /**
     * POST /api/students/welfare-case
     */
    public function postWelfareCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to create welfare cases');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postWelfareCase($data, $userId));
    }

    /**
     * POST /api/students/welfare-case/{caseId}/note
     */
    public function postWelfareCaseNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to add welfare notes');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postWelfareCaseNote($caseId, $data, $userId));
    }

    /**
     * POST /api/students/welfare-case/{caseId}/follow-up
     */
    public function postWelfareCaseFollowUp($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to update follow-up');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postWelfareCaseFollowUp($caseId, $data, $userId));
    }

    /**
     * POST /api/students/welfare-case/{caseId}/resolve
     */
    public function postWelfareCaseResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to resolve cases');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postWelfareCaseResolve($caseId, $data, $userId));
    }

    /**
     * POST /api/students/welfare-case/{caseId}/escalate
     */
    public function postWelfareCaseEscalate($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to escalate cases');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postWelfareCaseEscalate($caseId, $data, $userId));
    }

    /**
     * POST /api/students/counseling-case/{caseId}/session-note
     */
    public function postCounselingCaseSessionNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to add session notes');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postCounselingCaseSessionNote($caseId, $data, $userId));
    }

    /**
     * POST /api/students/counseling-case/{caseId}/follow-up
     */
    public function postCounselingCaseFollowUp($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to update follow-up');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postCounselingCaseFollowUp($caseId, $data, $userId));
    }

    /**
     * POST /api/students/counseling-case/{caseId}/close
     */
    public function postCounselingCaseClose($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to close cases');
        }

        $caseId = $id !== null ? (int) $id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postCounselingCaseClose($caseId, $data, $userId));
    }

    /**
     * POST /api/students/boarding-note
     */
    public function postBoardingNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $allowedRoles = ['boarding_master', 'boarding_matron', 'admin', 'school_administrator', 'headteacher', 'director'];
        if (!$this->userHasAnyRole($allowedRoles)) {
            return $this->forbidden('You do not have permission to add boarding notes');
        }

        $userId = (int) ($this->getAuthenticatedUserId() ?? 0);
        return $this->handleResponse($this->studentProfileManager->postBoardingNote($data, $userId));
    }
}
