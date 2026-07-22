<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\students\StudentsAPI;
use App\API\Modules\students\StudentService;
use App\API\Modules\system\MediaManager;
use App\API\Modules\students\FamilyGroupsManager;
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
        $this->mediaManager = new MediaManager($connection);
        $this->studentService = new StudentService($connection);
        $this->api = new StudentsAPI();
    }

    private function authorizeStudents(array $permissions, string $message = 'Insufficient permissions')
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if (!$this->userHasAny($permissions)) {
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

    /* =====================================================
     * BASE CRUD
     * ===================================================== */

    public function getStudent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_VIEW_PERMS, 'Insufficient permission to view students')) {
            return $auth;
        }

        if ($id && empty($segments)) {
            return $this->handleResponse($this->api->get($id));
        }

        if (!empty($segments)) {
            return $this->routeNestedGet(array_shift($segments), $id, $data, $segments);
        }

        return $this->handleResponse($this->api->list($data));
    }

    public function postStudent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_CREATE_PERMS, 'Insufficient permission to create students')) {
            return $auth;
        }

        if (!empty($segments)) {
            return $this->routeNestedPost(array_shift($segments), $id, $data, $segments);
        }

        return $this->handleResponse($this->api->create($data));
    }

    public function putStudent($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update students')) {
            return $auth;
        }

        if (!$id) {
            return $this->badRequest('Student ID is required');
        }

        if (!empty($segments)) {
            return $this->routeNestedPut(array_shift($segments), $id, $data, $segments);
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
            $data['file'] = $_FILES['file'];
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
            $data['file'] = $_FILES['file'];
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
        return $this->success($result, 'ID cards loaded');
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
        if ($auth = $this->authorizeStudents(
            self::STUDENT_ID_CARD_GENERATE_PERMS,
            'Insufficient permission to generate ID cards'
        )) {
            return $auth;
        }

        $studentId = $data['student_id'] ?? null;
        if (!$studentId) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->studentService->generateIdCard((int) $studentId, $this->user['id'] ?? null);
        if (($result['success'] ?? false) && !empty($data['generate_qr'])) {
            $qrResult = $this->api->generateQRCodeEnhanced((int) $studentId);
            if (($qrResult['status'] ?? false) === true) {
                $result['qr_code_path'] = $qrResult['data']['qr_code_path'] ?? null;
            }
        }

        return $this->handleResponse($result);
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
        if ($auth = $this->authorizeStudents(self::STUDENT_TRANSFER_PERMS, 'Insufficient permission to initiate student transfers')) {
            return $auth;
        }

        $result = $this->api->startTransferWorkflow($data);
        return $this->handleResponse($result);
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
        if ($auth = $this->authorizeStudents(self::STUDENT_PROMOTE_PERMS, 'Insufficient permission to promote students')) {
            return $auth;
        }

        $result = $this->api->promoteSingleStudent($data);
        return $this->handleResponse($result);
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
     * POST /api/students/academic-year-archive
     */
    public function postAcademicYearArchive($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_ACADEMIC_YEAR_MANAGE_PERMS, 'Insufficient permission to archive academic year')) {
            return $auth;
        }

        $yearId = $data['year_id'] ?? $data['id'] ?? $id;
        if ($yearId === null) {
            return $this->badRequest('year_id is required');
        }

        $userId = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
        if ($userId <= 0) {
            return $this->unauthorized('Authentication required');
        }

        $notes = $data['notes'] ?? $data['closure_notes'] ?? null;
        $result = $this->api->archiveAcademicYear((int) $yearId, $userId, $notes);
        return $this->success(['archived' => (bool) $result], 'Academic year archived');
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
        if ($auth = $this->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            return $this->handleResponse(
                (new FamilyGroupsManager())->getParentDetails((int) $parentId)
            );
        }

        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->getStudentParentsInfo($studentId);
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

        return $this->handleResponse(
            (new FamilyGroupsManager())->getParents($data)
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

        $result = (new FamilyGroupsManager())->getParentDetails((int) $parentId);
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
            (new FamilyGroupsManager())->createParent($data)
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
            (new FamilyGroupsManager())->updateParent((int) $parentId, $data)
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
            (new FamilyGroupsManager())->deleteParent((int) $parentId)
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
            (new FamilyGroupsManager())->linkParentToStudent((int) $parentId, (int) $studentId, $linkData)
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
            (new FamilyGroupsManager())->unlinkParentFromStudent((int) $parentId, (int) $studentId)
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
            (new FamilyGroupsManager())->getAvailableStudentsForParent((int) $parentId)
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
            (new FamilyGroupsManager())->getStudentsWithoutParents()
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
        $studentIds = $this->resolveCurrentStudentIds();

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
        $parentIds = $this->resolveCurrentParentIds();
        if (empty($parentIds)) {
            return $this->success([], 'No linked student profiles found for the current user');
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $this->db->query(
            "SELECT DISTINCT sp.student_id
             FROM student_parents sp
             JOIN students s ON s.id = sp.student_id
             WHERE sp.parent_id IN ({$placeholders})
               AND s.status = 'active'
             ORDER BY sp.student_id ASC",
            $parentIds
        );

        $studentIds = array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'student_id'));
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
        if ($auth = $this->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        if (!$id) {
            return $this->badRequest('Parent ID required');
        }

        return $this->handleResponse(
            (new FamilyGroupsManager())->getParentDetails((int) $id)
        );
    }

    public function putFamilyParentUpdate($id = null, $data = [])
    {
        if ($auth = $this->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) {
            return $auth;
        }

        if (!$id) {
            return $this->badRequest('Parent ID required');
        }

        return $this->handleResponse(
            (new FamilyGroupsManager())->updateParent((int) $id, $data)
        );
    }

    /* =====================================================
     * HELPERS
     * ===================================================== */

    private function getAuthenticatedUserId(): ?int
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $userId ? (int) $userId : null;
    }

    private function resolveCurrentStudentIds(): array
    {
        $studentIds = [];

        foreach (['student_id', 'linked_student_id'] as $field) {
            if (!empty($this->user[$field])) {
                $studentIds[] = (int) $this->user[$field];
            }
        }

        if (!empty($this->user['student_ids']) && is_array($this->user['student_ids'])) {
            foreach ($this->user['student_ids'] as $studentId) {
                if ($studentId) {
                    $studentIds[] = (int) $studentId;
                }
            }
        }

        $studentIds = array_values(array_unique(array_filter($studentIds)));
        if (!empty($studentIds)) {
            return $studentIds;
        }

        $username = trim((string) ($this->user['username'] ?? ''));
        if ($username !== '') {
            $stmt = $this->db->query(
                "SELECT id FROM students WHERE admission_no = ? LIMIT 1",
                [$username]
            );
            $studentId = $stmt->fetchColumn();
            if ($studentId) {
                return [(int) $studentId];
            }
        }

        return [];
    }

    private function resolveCurrentParentIds(): array
    {
        $parentIds = [];

        foreach (['parent_id', 'linked_parent_id'] as $field) {
            if (!empty($this->user[$field])) {
                $parentIds[] = (int) $this->user[$field];
            }
        }

        if (!empty($this->user['parent_ids']) && is_array($this->user['parent_ids'])) {
            foreach ($this->user['parent_ids'] as $parentId) {
                if ($parentId) {
                    $parentIds[] = (int) $parentId;
                }
            }
        }

        $parentIds = array_values(array_unique(array_filter($parentIds)));
        if (!empty($parentIds)) {
            return $parentIds;
        }

        $conditions = [];
        $bindings = [];

        $email = strtolower(trim((string) ($this->user['email'] ?? '')));
        if ($email !== '') {
            $conditions[] = 'LOWER(p.email) = ?';
            $bindings[] = $email;
        }

        $phones = [];
        foreach (['phone', 'phone_number', 'mobile', 'telephone'] as $field) {
            $value = trim((string) ($this->user[$field] ?? ''));
            if ($value !== '') {
                $phones[] = $value;
            }
        }
        $phones = array_values(array_unique(array_filter($phones)));
        foreach ($phones as $phone) {
            $conditions[] = '(p.phone_1 = ? OR p.phone_2 = ?)';
            $bindings[] = $phone;
            $bindings[] = $phone;
        }

        if (empty($conditions)) {
            $firstName = strtolower(trim((string) ($this->user['first_name'] ?? '')));
            $lastName = strtolower(trim((string) ($this->user['last_name'] ?? '')));

            if ($firstName !== '' && $lastName !== '') {
                $conditions[] = '(LOWER(p.first_name) = ? AND LOWER(p.last_name) = ?)';
                $bindings[] = $firstName;
                $bindings[] = $lastName;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT DISTINCT p.id
                FROM parents p
                WHERE " . implode(' OR ', array_map(static fn($condition) => "({$condition})", $conditions)) . "
                ORDER BY p.id ASC";

        $stmt = $this->db->query($sql, $bindings);
        return array_map('intval', array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'id'));
    }

    private function handleResponse($result)
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
            $page   = max(1, (int) ($_GET['page']  ?? $data['page']  ?? 1));
            $limit  = max(1, min(200, (int) ($_GET['limit'] ?? $data['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $search = trim($_GET['search'] ?? $data['search'] ?? '');

            $where  = ["(hr.disability_notes IS NOT NULL AND hr.disability_notes != ''
                         OR hr.chronic_conditions IS NOT NULL AND hr.chronic_conditions != ''
                         OR hr.allergies IS NOT NULL AND hr.allergies != '')"];
            $params = [];

            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
                $params  = array_merge($params, [$like, $like, $like]);
            }

            $whereClause = implode(' AND ', $where);

            $sql = "SELECT
                        s.id, s.admission_no,
                        CONCAT(s.first_name, ' ', COALESCE(s.middle_name,''), ' ', s.last_name) AS full_name,
                        s.first_name, s.last_name, s.gender, s.date_of_birth, s.status,
                        st.name AS stream_name,
                        hr.disability_notes, hr.chronic_conditions, hr.allergies,
                        hr.special_diet, hr.blood_group, hr.notes AS health_notes
                    FROM students s
                    LEFT JOIN streams st ON st.id = s.stream_id
                    LEFT JOIN student_health_records hr ON hr.student_id = s.id
                    WHERE s.status = 'active' AND $whereClause
                    ORDER BY s.first_name, s.last_name
                    LIMIT ? OFFSET ?";

            $rows = $this->db->query($sql, array_merge($params, [$limit, $offset]))->fetchAll();

            $countSql = "SELECT COUNT(*) FROM students s
                         LEFT JOIN student_health_records hr ON hr.student_id = s.id
                         WHERE s.status = 'active' AND $whereClause";
            $total = (int) $this->db->query($countSql, $params)->fetchColumn();

            return $this->success([
                'data'        => $rows,
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => (int) ceil($total / $limit),
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch special needs records: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Terms
            $termsStmt = $db->query("SELECT id, academic_year_id, name, term_number, status FROM academic_terms ORDER BY term_number ASC");
            $terms = $termsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Assessments
            $assessmentsStmt = $db->query("SELECT DISTINCT title AS name, id FROM assessments ORDER BY title ASC");
            $assessments = $assessmentsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'classes' => $classes,
                'streams' => $streams,
                'academic_years' => $years,
                'terms' => $terms,
                'assessments' => $assessments
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load metadata: ' . $e->getMessage());
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
        $context = $contextRes['context'];

        $scope = $this->studentService->scopeService->buildScope($context, $this->user);
        [$scopeConditions, $scopeBindings] = $this->studentService->scopeService->whereClause($scope);

        $viewMode = strtolower((string) ($_GET['view_mode'] ?? $data['view_mode'] ?? 'students'));
        $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : (!empty($data['class_id']) ? (int)$data['class_id'] : null);
        $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : (!empty($data['stream_id']) ? (int)$data['stream_id'] : null);
        $gender = !empty($_GET['gender']) ? $_GET['gender'] : (!empty($data['gender']) ? $data['gender'] : null);
        $academicYearVal = !empty($_GET['academic_year']) ? $_GET['academic_year'] : (!empty($data['academic_year']) ? $data['academic_year'] : null);
        $termId = !empty($_GET['term_id']) ? (int)$_GET['term_id'] : (!empty($data['term_id']) ? (int)$data['term_id'] : null);
        $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : (!empty($data['search']) ? trim((string)$data['search']) : '');

        try {
            $db = $this->db->getConnection();

            // Resolve Academic Year ID and Year Code
            $yearId = null;
            $yearCode = null;
            if ($academicYearVal !== null) {
                if (is_numeric($academicYearVal)) {
                    if ((int)$academicYearVal > 2000 && (int)$academicYearVal < 2100) {
                        $yearCode = (string)$academicYearVal;
                        $stmt = $db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
                        $stmt->execute([$yearCode]);
                        $yearId = $stmt->fetchColumn() ?: null;
                    } else {
                        $yearId = (int)$academicYearVal;
                        $stmt = $db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
                        $stmt->execute([$yearId]);
                        $yearCode = $stmt->fetchColumn() ?: null;
                    }
                } else {
                    $yearCode = (string)$academicYearVal;
                    $stmt = $db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
                    $stmt->execute([$yearCode]);
                    $yearId = $stmt->fetchColumn() ?: null;
                }
            } else {
                $stmt = $db->query("SELECT id, year_code FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1");
                $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($res) {
                    $yearId = (int)$res['id'];
                    $yearCode = $res['year_code'];
                }
            }

            $conditions = ["s.status = 'active'"];
            $bindings = [];

            if ($classId !== null) {
                $conditions[] = "cs.class_id = ?";
                $bindings[] = $classId;
            }
            if ($streamId !== null) {
                $conditions[] = "s.stream_id = ?";
                $bindings[] = $streamId;
            }
            if ($gender !== null) {
                $conditions[] = "s.gender = ?";
                $bindings[] = $gender;
            }
            if ($search !== '') {
                $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term);
            }

            if (!empty($scopeConditions)) {
                foreach ($scopeConditions as $scCond) {
                    $conditions[] = $scCond;
                }
                $bindings = array_merge($bindings, $scopeBindings);
            }

            $whereClause = 'WHERE ' . implode(' AND ', $conditions);

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no AS admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    c.name AS class_name,
                    cs.stream_name AS stream_name,
                    s.gender AS gender,

                    COALESCE(
                        (
                            SELECT ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2)
                            FROM assessment_results ar
                            JOIN assessments a ON a.id = ar.assessment_id
                            WHERE ar.student_id = s.id
                              AND (? IS NULL OR a.term_id = ?)
                        ),
                        0.00
                    ) AS average_score,

                    COALESCE(
                        (
                            SELECT ROUND(COUNT(CASE WHEN status IN ('present', 'late') THEN 1 END) / COUNT(*) * 100, 2)
                            FROM student_attendance
                            WHERE student_id = s.id
                              AND (? IS NULL OR academic_year_id = ?)
                        ),
                        100.00
                    ) AS attendance_rate,

                    COALESCE(
                        (
                            SELECT SUM(balance)
                            FROM student_fee_obligations
                            WHERE student_id = s.id
                              AND (? IS NULL OR academic_year = ?)
                        ),
                        0.00
                    ) AS fee_balance,

                    (
                        SELECT COUNT(*)
                        FROM student_discipline
                        WHERE student_id = s.id
                    ) AS discipline_cases,

                    (
                        SELECT COUNT(*)
                        FROM activity_participants
                        WHERE student_id = s.id
                    ) AS activities_count,

                    '-' AS position
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                {$whereClause}
            ";

            $queryBindings = array_merge(
                [$termId, $termId],       // First subquery (assessment_results term filter)
                [$yearId, $yearId],       // Second subquery (attendance year filter)
                [$yearCode, $yearCode],   // Third subquery (fee balance year filter)
                $bindings                 // WHERE clause bindings
            );

            $stmt = $db->prepare($sql);
            $stmt->execute($queryBindings);
            $studentRows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add grades to each student row
            foreach ($studentRows as &$row) {
                $row['grade'] = $this->deriveGradeFromPercentage($row['average_score']);
            }
            unset($row);

            // Grouping and aggregating depending on view_mode
            if ($viewMode === 'class') {
                $classes = [];
                foreach ($studentRows as $row) {
                    $className = $row['class_name'] ?? 'Unassigned';
                    if (!isset($classes[$className])) {
                        $classes[$className] = [
                            'class_name' => $className,
                            'total_students' => 0,
                            'average_score_sum' => 0,
                            'average_score_count' => 0,
                            'attendance_sum' => 0,
                            'attendance_count' => 0,
                            'fee_balance' => 0,
                            'discipline_cases' => 0,
                            'activities_count' => 0
                        ];
                    }
                    $classes[$className]['total_students']++;
                    if ($row['average_score'] !== null) {
                        $classes[$className]['average_score_sum'] += (float)$row['average_score'];
                        $classes[$className]['average_score_count']++;
                    }
                    if ($row['attendance_rate'] !== null) {
                        $classes[$className]['attendance_sum'] += (float)$row['attendance_rate'];
                        $classes[$className]['attendance_count']++;
                    }
                    $classes[$className]['fee_balance'] += (float)$row['fee_balance'];
                    $classes[$className]['discipline_cases'] += (int)$row['discipline_cases'];
                    $classes[$className]['activities_count'] += (int)$row['activities_count'];
                }
                $resultRows = [];
                foreach ($classes as $className => $c) {
                    $avgScore = $c['average_score_count'] > 0 ? round($c['average_score_sum'] / $c['average_score_count'], 2) : 0;
                    $resultRows[] = [
                        'class_name' => $c['class_name'],
                        'total_students' => $c['total_students'],
                        'average_score' => $avgScore,
                        'grade' => $this->deriveGradeFromPercentage($avgScore),
                        'attendance_rate' => $c['attendance_count'] > 0 ? round($c['attendance_sum'] / $c['attendance_count'], 2) : 100,
                        'fee_balance' => $c['fee_balance'],
                        'discipline_cases' => $c['discipline_cases'],
                        'activities_count' => $c['activities_count']
                    ];
                }
                return $this->success($resultRows);
            }

            if ($viewMode === 'stream') {
                $streams = [];
                foreach ($studentRows as $row) {
                    $className = $row['class_name'] ?? 'Unassigned';
                    $streamName = $row['stream_name'] ?? 'Unassigned';
                    $key = $className . ' - ' . $streamName;
                    if (!isset($streams[$key])) {
                        $streams[$key] = [
                            'class_name' => $className,
                            'stream_name' => $streamName,
                            'total_students' => 0,
                            'average_score_sum' => 0,
                            'average_score_count' => 0,
                            'attendance_sum' => 0,
                            'attendance_count' => 0,
                            'fee_balance' => 0,
                            'discipline_cases' => 0,
                            'activities_count' => 0
                        ];
                    }
                    $streams[$key]['total_students']++;
                    if ($row['average_score'] !== null) {
                        $streams[$key]['average_score_sum'] += (float)$row['average_score'];
                        $streams[$key]['average_score_count']++;
                    }
                    if ($row['attendance_rate'] !== null) {
                        $streams[$key]['attendance_sum'] += (float)$row['attendance_rate'];
                        $streams[$key]['attendance_count']++;
                    }
                    $streams[$key]['fee_balance'] += (float)$row['fee_balance'];
                    $streams[$key]['discipline_cases'] += (int)$row['discipline_cases'];
                    $streams[$key]['activities_count'] += (int)$row['activities_count'];
                }
                $resultRows = [];
                foreach ($streams as $key => $s) {
                    $avgScore = $s['average_score_count'] > 0 ? round($s['average_score_sum'] / $s['average_score_count'], 2) : 0;
                    $resultRows[] = [
                        'class_name' => $s['class_name'],
                        'stream_name' => $s['stream_name'],
                        'total_students' => $s['total_students'],
                        'average_score' => $avgScore,
                        'grade' => $this->deriveGradeFromPercentage($avgScore),
                        'attendance_rate' => $s['attendance_count'] > 0 ? round($s['attendance_sum'] / $s['attendance_count'], 2) : 100,
                        'fee_balance' => $s['fee_balance'],
                        'discipline_cases' => $s['discipline_cases'],
                        'activities_count' => $s['activities_count']
                    ];
                }
                return $this->success($resultRows);
            }

            if ($viewMode === 'school') {
                $totalStudents = count($studentRows);
                $avgScoreSum = 0;
                $avgScoreCount = 0;
                $attendanceSum = 0;
                $attendanceCount = 0;
                $feeBalance = 0;
                $disciplineCases = 0;
                $activitiesCount = 0;

                foreach ($studentRows as $row) {
                    if ($row['average_score'] !== null) {
                        $avgScoreSum += (float)$row['average_score'];
                        $avgScoreCount++;
                    }
                    if ($row['attendance_rate'] !== null) {
                        $attendanceSum += (float)$row['attendance_rate'];
                        $attendanceCount++;
                    }
                    $feeBalance += (float)$row['fee_balance'];
                    $disciplineCases += (int)$row['discipline_cases'];
                    $activitiesCount += (int)$row['activities_count'];
                }

                $avgScore = $avgScoreCount > 0 ? round($avgScoreSum / $avgScoreCount, 2) : 0;
                $resultRows = [[
                    'scope' => 'Whole School',
                    'total_students' => $totalStudents,
                    'average_score' => $avgScore,
                    'grade' => $this->deriveGradeFromPercentage($avgScore),
                    'attendance_rate' => $attendanceCount > 0 ? round($attendanceSum / $attendanceCount, 2) : 100,
                    'fee_balance' => $feeBalance,
                    'discipline_cases' => $disciplineCases,
                    'activities_count' => $activitiesCount
                ]];
                return $this->success($resultRows);
            }

            return $this->success($studentRows);

        } catch (\Exception $e) {
            return $this->badRequest('Failed to load performance overview: ' . $e->getMessage());
        }
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

        $db = $this->db->getConnection();

        // 1. Context and Permission check
        $requestedContext = $_GET['context'] ?? null;
        $contextRes = $this->studentService->resolveContext($this->user, $requestedContext);
        if (!$contextRes['allowed']) {
            return $this->forbidden($contextRes['message'] ?? 'Forbidden');
        }
        $context = $contextRes['context'];

        // 2. Scope check to see if the user can access this specific student
        $scope = $this->studentService->scopeService->buildScope($context, $this->user);
        if (!$this->studentService->scopeService->canAccessStudent($studentId, $scope)) {
            return $this->forbidden('You do not have permission to view this student.');
        }

        // 3. Fetch student profile
        $studentStmt = $db->prepare("
            SELECT
                s.id,
                s.admission_no,
                s.first_name,
                s.middle_name,
                s.last_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                s.gender,
                s.photo_url,
                c.name AS class_name,
                cs.stream_name AS stream_name
            FROM students s
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$student) {
            return $this->notFound('Student not found');
        }

        // Filters
        $academicYearVal = $_GET['academic_year'] ?? $_GET['academic_year_id'] ?? null;
        $termId = !empty($_GET['term_id']) ? (int)$_GET['term_id'] : null;
        $assessmentId = !empty($_GET['assessment_id']) ? (int)$_GET['assessment_id'] : null;

        // Resolve Academic Year ID and Year Code
        $yearId = null;
        $yearCode = null;
        if ($academicYearVal !== null) {
            if (is_numeric($academicYearVal)) {
                if ((int)$academicYearVal > 2000 && (int)$academicYearVal < 2100) {
                    $yearCode = (string)$academicYearVal;
                    $stmt = $db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
                    $stmt->execute([$yearCode]);
                    $yearId = $stmt->fetchColumn() ?: null;
                } else {
                    $yearId = (int)$academicYearVal;
                    $stmt = $db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
                    $stmt->execute([$yearId]);
                    $yearCode = $stmt->fetchColumn() ?: null;
                }
            } else {
                $yearCode = (string)$academicYearVal;
                $stmt = $db->prepare("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1");
                $stmt->execute([$yearCode]);
                $yearId = $stmt->fetchColumn() ?: null;
            }
        }

        // 4. Fetch subject performance
        $subjects = [];
        if ($termId !== null) {
            $scoresSql = "
                SELECT
                    tss.subject_id,
                    COALESCE(la.name, cu.name, CONCAT('Subject ', tss.subject_id)) AS subject,
                    tss.overall_percentage AS score,
                    tss.overall_grade AS grade,
                    class_subject_avg.class_average AS classAverage,
                    NULL AS position,
                    NULL AS teacher,
                    NULL AS remarks
                FROM term_subject_scores tss
                LEFT JOIN learning_areas la ON la.id = tss.subject_id
                LEFT JOIN curriculum_units cu ON cu.id = tss.subject_id
                LEFT JOIN (
                    SELECT
                        subject_id,
                        ROUND(AVG(overall_percentage), 2) AS class_average
                    FROM term_subject_scores
                    WHERE term_id = ?
                    GROUP BY subject_id
                ) class_subject_avg ON class_subject_avg.subject_id = tss.subject_id
                WHERE tss.student_id = ? AND tss.term_id = ?
                ORDER BY subject ASC
            ";
            $scoresStmt = $db->prepare($scoresSql);
            $scoresStmt->execute([$termId, $studentId, $termId]);
            $subjects = $scoresStmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        if (empty($subjects)) {
            // Fallback to assessment_results
            $fallbackSql = "
                SELECT
                    a.subject_id,
                    COALESCE(la.name, cu.name, CONCAT('Subject ', a.subject_id)) AS subject,
                    ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2) AS score,
                    NULL AS grade,
                    NULL AS classAverage,
                    NULL AS position,
                    NULL AS teacher,
                    MIN(ar.remarks) AS remarks
                FROM assessment_results ar
                JOIN assessments a ON a.id = ar.assessment_id
                LEFT JOIN learning_areas la ON la.id = a.subject_id
                LEFT JOIN curriculum_units cu ON cu.id = a.subject_id
                WHERE ar.student_id = ?
            ";
            $fallbackBindings = [$studentId];
            if ($termId !== null) {
                $fallbackSql .= " AND a.term_id = ?";
                $fallbackBindings[] = $termId;
            }
            if ($yearId !== null) {
                $fallbackSql .= " AND a.academic_year_id = ?";
                $fallbackBindings[] = $yearId;
            }
            $fallbackSql .= " GROUP BY a.subject_id ORDER BY subject ASC";

            $fallbackStmt = $db->prepare($fallbackSql);
            $fallbackStmt->execute($fallbackBindings);
            $subjects = $fallbackStmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Populate grade if null
        foreach ($subjects as &$sub) {
            if ($sub['grade'] === null && $sub['score'] !== null) {
                $sub['grade'] = $this->deriveGradeFromPercentage($sub['score']);
            }
        }
        unset($sub);

        // 5. Fetch Attendance summary
        $attConditions = ["student_id = ?"];
        $attBindings = [$studentId];
        if ($termId !== null) {
            $attConditions[] = "term_id = ?";
            $attBindings[] = $termId;
        }
        if ($yearId !== null) {
            $attConditions[] = "academic_year_id = ?";
            $attBindings[] = $yearId;
        }
        $attWhere = implode(' AND ', $attConditions);

        $attStmt = $db->prepare("
            SELECT
                COUNT(CASE WHEN status = 'present' THEN 1 END) as days_present,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as days_absent,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as days_late,
                ROUND(
                    (COUNT(CASE WHEN status = 'present' OR status = 'late' THEN 1 END) / COUNT(*)) * 100,
                    2
                ) as attendance_rate
            FROM student_attendance
            WHERE {$attWhere}
        ");
        $attStmt->execute($attBindings);
        $attendance = $attStmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'days_present' => 0,
            'days_absent' => 0,
            'days_late' => 0,
            'attendance_rate' => 100.00
        ];

        // 6. Fetch Discipline summary
        $dispStmt = $db->prepare("
            SELECT id, incident_date AS date, description AS case_title, severity, status, action_taken
            FROM student_discipline
            WHERE student_id = ?
            ORDER BY incident_date DESC
        ");
        $dispStmt->execute([$studentId]);
        $disciplineRecords = $dispStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $disciplineSummary = [
            'count' => count($disciplineRecords),
            'records' => $disciplineRecords
        ];

        // 7. Fetch Activities (co-curricular)
        $actStmt = $db->prepare("
            SELECT ap.activity_id as id, ac.name as title, ap.joined_at
            FROM activity_participants ap
            LEFT JOIN activity_categories ac ON ac.id = ap.activity_id
            WHERE ap.student_id = ?
            ORDER BY ap.joined_at DESC
        ");
        $actStmt->execute([$studentId]);
        $activities = $actStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // 8. Fetch Finance summary
        $finStmt = $db->prepare("
            SELECT
                COALESCE(SUM(amount_due), 0) as total_due,
                COALESCE(SUM(amount_paid), 0) as total_paid,
                COALESCE(SUM(amount_waived), 0) as total_waived,
                COALESCE(SUM(balance), 0) as balance
            FROM student_fee_obligations
            WHERE student_id = ?
        ");
        $finStmt->execute([$studentId]);
        $finance = $finStmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'total_due' => 0,
            'total_paid' => 0,
            'total_waived' => 0,
            'balance' => 0
        ];

        // 9. Fetch Teacher comments
        $enrollmentStmt = $db->prepare("
            SELECT teacher_comments, head_teacher_comments, special_notes
            FROM class_enrollments
            WHERE student_id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $enrollmentStmt->execute([$studentId]);
        $enrollment = $enrollmentStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        $comments = [];
        if (!empty($enrollment['teacher_comments'])) {
            $comments[] = [
                'teacher' => 'Class Teacher',
                'comment' => $enrollment['teacher_comments']
            ];
        }
        if (!empty($enrollment['head_teacher_comments'])) {
            $comments[] = [
                'teacher' => 'Head Teacher',
                'comment' => $enrollment['head_teacher_comments']
            ];
        }

        $recommendations = [];
        if (!empty($enrollment['special_notes'])) {
            $recommendations[] = $enrollment['special_notes'];
        }

        $responsePayload = [
            'student' => $student,
            'performance' => $subjects,
            'attendance_summary' => $attendance,
            'discipline_summary' => $disciplineSummary,
            'activities' => $activities,
            'finance_summary' => $finance,
            'teacher_comments' => $comments,
            'recommendations' => $recommendations
        ];

        return $this->success($responsePayload);
    }

    private function deriveGradeFromPercentage($score)
    {
        if ($score === null) return '-';
        $score = (float)$score;
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'E';
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
            $db = $this->db->getConnection();

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Terms
            $termsStmt = $db->query("SELECT id, academic_year_id, name, term_number, status FROM academic_terms ORDER BY term_number ASC");
            $terms = $termsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'classes' => $classes,
                'streams' => $streams,
                'academic_years' => $years,
                'terms' => $terms,
                'statuses' => ['pending', 'resolved', 'escalated'],
                'severities' => ['low', 'medium', 'high']
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load discipline metadata: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            $academicYearVal = $_GET['academic_year'] ?? null;
            $termId = !empty($_GET['term_id']) ? (int)$_GET['term_id'] : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $status = $_GET['status'] ?? null;
            $severity = $_GET['severity'] ?? null;
            $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : '';

            // Build query
            $sql = "
                SELECT
                    sd.id,
                    sd.student_id,
                    sd.incident_date,
                    sd.description,
                    sd.severity,
                    sd.status,
                    sd.action_taken,
                    sd.resolution_date,
                    sd.created_at,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    c.name AS class_name,
                    cs.stream_name,
                    s.photo_url
                FROM student_discipline sd
                JOIN students s ON s.id = sd.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE s.status = 'active'
            ";

            $bindings = [];

            if ($status) {
                $sql .= " AND sd.status = ?";
                $bindings[] = $status;
            }

            if ($severity) {
                $sql .= " AND sd.severity = ?";
                $bindings[] = $severity;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR sd.description LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term);
            }

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            $sql .= " ORDER BY sd.incident_date DESC, sd.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $cases = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success($cases);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load discipline cases: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            // Get case details
            $caseStmt = $db->prepare("
                SELECT
                    sd.*,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.admission_no,
                    s.photo_url,
                    c.name AS class_name,
                    cs.stream_name,
                    CONCAT_WS(' ', res.first_name, res.last_name) AS resolved_by_name
                FROM student_discipline sd
                JOIN students s ON s.id = sd.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN users res ON res.id = sd.resolved_by
                WHERE sd.id = ?
                LIMIT 1
            ");
            $caseStmt->execute([$caseId]);
            $case = $caseStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$case) {
                return $this->notFound('Discipline case not found');
            }

            return $this->success([
                'case' => $case,
                'student' => [
                    'first_name' => $case['student_name'] ?? '',
                    'last_name' => '',
                    'admission_no' => $case['admission_no'] ?? '',
                    'photo_url' => $case['photo_url'] ?? '',
                ],
                'class_name' => $case['class_name'] ?? '',
                'stream_name' => $case['stream_name'] ?? '',
                'reported_by_name' => $case['reported_by_name'] ?? 'System',
                'resolved_by_name' => $case['resolved_by_name'] ?? ''
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load discipline case: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            $updates = [];
            $bindings = [];
            $userId = $this->user['id'];

            if (!empty($data['status'])) {
                $updates[] = "status = ?";
                $bindings[] = $data['status'];
            }

            if (!empty($data['action_taken'])) {
                $updates[] = "action_taken = ?";
                $bindings[] = $data['action_taken'];
            }

            if ($data['status'] === 'resolved') {
                $updates[] = "resolution_date = CURDATE()";
                $updates[] = "resolved_by = ?";
                $bindings[] = $userId;
            }

            if (empty($updates)) {
                return $this->badRequest('No valid fields to update');
            }

            $sql = "UPDATE student_discipline SET " . implode(', ', $updates) . " WHERE id = ?";
            $bindings[] = $caseId;

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);

            return $this->success(['message' => 'Discipline case updated successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to update discipline case: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Dormitories (for boarding role)
            $dormStmt = $db->query("SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC");
            $dormitories = $dormStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'classes' => $classes,
                'streams' => $streams,
                'academic_years' => $years,
                'dormitories' => $dormitories,
                'statuses' => ['draft', 'active', 'completed', 'archived'],
                'iep_types' => ['learning', 'behavioral', 'physical', 'medical', 'other']
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load special needs metadata: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            $academicYearVal = $_GET['academic_year'] ?? null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $dormitoryId = !empty($_GET['dormitory_id']) ? (int)$_GET['dormitory_id'] : null;
            $status = $_GET['status'] ?? null;
            $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : '';

            // Build query
            $sql = "
                SELECT
                    i.id,
                    i.student_id,
                    i.academic_year,
                    i.iep_type,
                    i.special_needs_category,
                    i.goals_summary,
                    i.strategies,
                    i.accommodations,
                    i.progress_monitoring_plan,
                    i.status,
                    i.approved_date,
                    i.created_at,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    c.name AS class_name,
                    cs.stream_name,
                    d.name AS dormitory_name,
                    s.photo_url
                FROM ieps i
                JOIN students s ON s.id = i.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN dormitories d ON d.id = da.dormitory_id
                WHERE s.status = 'active'
            ";

            $bindings = [];

            if ($status) {
                $sql .= " AND i.status = ?";
                $bindings[] = $status;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR i.iep_type LIKE ? OR i.special_needs_category LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($dormitoryId) {
                $sql .= " AND d.id = ?";
                $bindings[] = $dormitoryId;
            }

            if ($academicYearVal) {
                $sql .= " AND i.academic_year = ?";
                $bindings[] = $academicYearVal;
            }

            $sql .= " ORDER BY i.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $ieps = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success($ieps);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load special needs records: ' . $e->getMessage());
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
            $db = $this->db->getConnection();

            // Get IEP details
            $iepStmt = $db->prepare("
                SELECT
                    i.*,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.admission_no,
                    s.photo_url,
                    c.name AS class_name,
                    cs.stream_name,
                    d.name AS dormitory_name,
                    CONCAT_WS(' ', cb.first_name, cb.last_name) AS created_by_name,
                    CONCAT_WS(' ', ab.first_name, ab.last_name) AS approved_by_name
                FROM ieps i
                JOIN students s ON s.id = i.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN dormitories d ON d.id = da.dormitory_id
                LEFT JOIN users cb ON cb.id = i.created_by
                LEFT JOIN users ab ON ab.id = i.approved_by
                WHERE i.id = ?
                LIMIT 1
            ");
            $iepStmt->execute([$iepId]);
            $iep = $iepStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$iep) {
                return $this->notFound('IEP not found');
            }

            return $this->success([
                'iep' => $iep,
                'student' => [
                    'first_name' => $iep['student_name'] ?? '',
                    'last_name' => '',
                    'admission_no' => $iep['admission_no'] ?? '',
                    'photo_url' => $iep['photo_url'] ?? '',
                ],
                'class_name' => $iep['class_name'] ?? '',
                'stream_name' => $iep['stream_name'] ?? '',
                'created_by_name' => $iep['created_by_name'] ?? 'System',
                'approved_by_name' => $iep['approved_by_name'] ?? ''
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load IEP details: ' . $e->getMessage());
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
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'classes' => $classes,
                'streams' => $streams,
                'relationship_types' => ['father', 'mother', 'guardian', 'step_father', 'step_mother', 'grandparent', 'uncle', 'aunt', 'sibling', 'other']
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load family groups metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/family-groups-v2
     */
    public function getFamilyGroupsV2($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();

            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : '';

            // Build query using existing parents and student_parents tables
            $sql = "
                SELECT
                    p.id AS parent_id,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS parent_name,
                    p.phone_1,
                    p.email,
                    p.status AS parent_status,
                    COUNT(sp.student_id) AS students_count,
                    GROUP_CONCAT(CONCAT(s.first_name, ' ', s.last_name) ORDER BY s.first_name SEPARATOR ', ') AS student_names
                FROM parents p
                LEFT JOIN student_parents sp ON sp.parent_id = p.id
                LEFT JOIN students s ON s.id = sp.student_id
                WHERE p.status = 'active'
            ";

            $bindings = [];

            if ($search) {
                $sql .= " AND (p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone_1 LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term);
            }

            $sql .= " GROUP BY p.id, p.first_name, p.middle_name, p.last_name, p.phone_1, p.email, p.status";
            $sql .= " ORDER BY p.first_name, p.last_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $families = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success($families);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load family groups: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/family-group/{parentId}
     */
    public function getFamilyGroup($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get parent details
            $parentStmt = $db->prepare("SELECT * FROM parents WHERE id = ?");
            $parentStmt->execute([$parentId]);
            $parent = $parentStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$parent) {
                return $this->notFound('Parent not found');
            }

            // Get linked students
            $studentsStmt = $db->prepare("
                SELECT
                    s.*,
                    sp.relationship,
                    sp.is_primary_contact,
                    sp.is_emergency_contact,
                    sp.financial_responsibility,
                    c.name AS class_name,
                    cs.stream_name
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE sp.parent_id = ?
            ");
            $studentsStmt->execute([$parentId]);
            $students = $studentsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'parent' => $parent,
                'students' => $students
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load family group details: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/family-group/{parentId}/link-student
     */
    public function postFamilyGroupLinkStudent($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) {
            return $this->badRequest('Parent ID is required');
        }

        try {
            $db = $this->db->getConnection();

            $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
            $relationship = $data['relationship'] ?? 'guardian';
            $isPrimary = !empty($data['is_primary_contact']) ? 1 : 0;
            $isEmergency = !empty($data['is_emergency_contact']) ? 1 : 0;
            $financialResp = $data['financial_responsibility'] ?? 100.00;

            if (!$studentId) {
                return $this->badRequest('Student ID is required');
            }

            // Check if link already exists
            $checkStmt = $db->prepare("SELECT id FROM student_parents WHERE parent_id = ? AND student_id = ?");
            $checkStmt->execute([$parentId, $studentId]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                // Update existing
                $updateStmt = $db->prepare("
                    UPDATE student_parents
                    SET relationship = ?, is_primary_contact = ?, is_emergency_contact = ?, financial_responsibility = ?
                    WHERE parent_id = ? AND student_id = ?
                ");
                $updateStmt->execute([$relationship, $isPrimary, $isEmergency, $financialResp, $parentId, $studentId]);
            } else {
                // Insert new
                $insertStmt = $db->prepare("
                    INSERT INTO student_parents (parent_id, student_id, relationship, is_primary_contact, is_emergency_contact, financial_responsibility)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$parentId, $studentId, $relationship, $isPrimary, $isEmergency, $financialResp]);
            }

            return $this->success(['message' => 'Student linked to parent successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to link student: ' . $e->getMessage());
        }
    }

    /* =====================================================
     * STUDENT PROMOTION ENDPOINTS (NEW)
     ===================================================== */

    /**
     * GET /api/students/promotion-meta-v2
     */
    public function getPromotionMetaV2($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Terms
            $termsStmt = $db->query("SELECT id, name FROM academic_terms ORDER BY start_date ASC");
            $terms = $termsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'academic_years' => $years,
                'classes' => $classes,
                'streams' => $streams,
                'terms' => $terms,
                'promotion_rules' => ['promote_all', 'promote_passed', 'repeat_failed', 'custom'],
                'statuses' => ['pending_approval', 'approved', 'rejected', 'transferred', 'retained', 'graduated']
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load promotion metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/promotion-candidates-v2
     */
    public function getPromotionCandidatesV2($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();

            $fromYearId = !empty($_GET['from_academic_year_id']) ? (int)$_GET['from_academic_year_id'] : null;
            $fromTermId = !empty($_GET['from_term_id']) ? (int)$_GET['from_term_id'] : null;
            $fromClassId = !empty($_GET['from_class_id']) ? (int)$_GET['from_class_id'] : null;
            $fromStreamId = !empty($_GET['from_stream_id']) ? (int)$_GET['from_stream_id'] : null;
            $toClassId = !empty($_GET['to_class_id']) ? (int)$_GET['to_class_id'] : null;
            $search = !empty($_GET['search']) ? trim((string)$_GET['search']) : '';

            // Build query to get students for promotion
            $sql = "
                SELECT
                    s.id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    c.name AS current_class,
                    cs.stream_name AS current_stream,
                    s.stream_id,
                    ay.year_code AS current_year,
                    s.status AS student_status
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN class_enrollments ce ON ce.student_id = s.id
                LEFT JOIN academic_years ay ON ay.id = ce.academic_year_id
                WHERE s.status = 'active'
            ";

            $bindings = [];

            if ($fromClassId) {
                $sql .= " AND c.id = ?";
                $bindings[] = $fromClassId;
            }

            if ($fromStreamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $fromStreamId;
            }

            if ($fromYearId) {
                $sql .= " AND ay.id = ?";
                $bindings[] = $fromYearId;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term);
            }

            $sql .= " ORDER BY s.first_name, s.last_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success($students);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load promotion candidates: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/promotion-execute-v2
     */
    public function postPromotionExecuteV2($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = (int)($this->user['id'] ?? $this->user['user_id'] ?? 0);
            if ($userId <= 0) {
                return $this->unauthorized('Authenticated user ID could not be resolved');
            }

            $fromYearId = !empty($data['from_academic_year_id']) ? (int)$data['from_academic_year_id'] : null;
            $toYearId = !empty($data['to_academic_year_id']) ? (int)$data['to_academic_year_id'] : null;
            $fromTermId = !empty($data['from_term_id']) ? (int)$data['from_term_id'] : null;
            $fromClassId = !empty($data['from_class_id']) ? (int)$data['from_class_id'] : null;
            $toClassId = !empty($data['to_class_id']) ? (int)$data['to_class_id'] : null;
            $fromStreamId = !empty($data['from_stream_id']) ? (int)$data['from_stream_id'] : null;
            $toStreamId = !empty($data['to_stream_id']) ? (int)$data['to_stream_id'] : null;
            $students = !empty($data['students']) ? (array)$data['students'] : [];
            $notes = $data['notes'] ?? null;

            if (!$fromYearId || !$toYearId || empty($students)) {
                return $this->badRequest('Required fields: from_academic_year_id, to_academic_year_id, students');
            }

            $yearStmt = $db->prepare("SELECT id, year_code FROM academic_years WHERE id IN (?, ?)");
            $yearStmt->execute([$fromYearId, $toYearId]);
            $yearRows = $yearStmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $extractYear = static function ($value) {
                if (preg_match('/^\d{4}/', (string)$value, $matches)) {
                    return (int)$matches[0];
                }
                return null;
            };

            $fromYear = $extractYear($yearRows[$fromYearId] ?? null);
            $toYear = $extractYear($yearRows[$toYearId] ?? null);

            if (!$fromYear || !$toYear) {
                return $this->badRequest('Selected academic years do not contain valid YEAR values');
            }

            if (!$fromTermId) {
                $termStmt = $db->query("
                    SELECT id
                    FROM academic_terms
                    WHERE status IN ('current', 'active') OR CURDATE() BETWEEN start_date AND end_date
                    ORDER BY
                        CASE status WHEN 'current' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,
                        start_date DESC
                    LIMIT 1
                ");
                $fromTermId = (int)($termStmt->fetchColumn() ?: 0);
            }

            if (!$fromTermId) {
                return $this->badRequest('Current academic term could not be resolved');
            }

            // Start transaction
            $db->beginTransaction();

            // Create promotion batch
            $insertBatchStmt = $db->prepare("
                INSERT INTO promotion_batches (from_academic_year, to_academic_year, batch_type, status, created_by, notes)
                VALUES (?, ?, 'manual', 'in_progress', ?, ?)
            ");
            $insertBatchStmt->execute([$fromYear, $toYear, $userId, $notes]);
            $batchId = $db->lastInsertId();

            $promoted = 0;
            $retained = 0;
            $processed = 0;
            foreach ($students as $studentData) {
                $studentId = (int)$studentData['student_id'];
                $finalAction = $studentData['final_action'] ?? 'promote';
                $studentNotes = $studentData['notes'] ?? null;

                // Get current enrollment
                $enrollStmt = $db->prepare("
                    SELECT id, class_id, stream_id, academic_year_id
                    FROM class_enrollments
                    WHERE student_id = ? AND academic_year_id = ?
                    LIMIT 1
                ");
                $enrollStmt->execute([$studentId, $fromYearId]);
                $enrollment = $enrollStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$enrollment) {
                    continue;
                }

                $targetClassId = $toClassId ?: (int)$enrollment['class_id'];
                $targetStreamId = $toStreamId ?: (int)$enrollment['stream_id'];
                $toEnrollmentId = null;
                $promotionStatus = $finalAction === 'retain' ? 'retained' : 'approved';

                if ($finalAction === 'promote') {
                    // Update or create new enrollment for next year
                    $checkNewEnrollStmt = $db->prepare("
                        SELECT id FROM class_enrollments
                        WHERE student_id = ? AND academic_year_id = ?
                    ");
                    $checkNewEnrollStmt->execute([$studentId, $toYearId]);
                    $newEnrollment = $checkNewEnrollStmt->fetch();

                    if ($newEnrollment) {
                        // Update existing
                        $updateEnrollStmt = $db->prepare("
                            UPDATE class_enrollments
                            SET class_id = ?, stream_id = ?, enrollment_status = 'active'
                            WHERE id = ?
                        ");
                        $updateEnrollStmt->execute([$targetClassId, $targetStreamId, $newEnrollment['id']]);
                        $toEnrollmentId = (int)$newEnrollment['id'];
                    } else {
                        // Create new
                        $insertEnrollStmt = $db->prepare("
                            INSERT INTO class_enrollments
                                (student_id, class_id, stream_id, academic_year_id, enrollment_date, enrollment_status)
                            VALUES (?, ?, ?, ?, CURDATE(), 'active')
                        ");
                        $insertEnrollStmt->execute([$studentId, $targetClassId, $targetStreamId, $toYearId]);
                        $toEnrollmentId = (int)$db->lastInsertId();
                    }

                    // Update student stream if needed
                    if ($toStreamId) {
                        $updateStudentStmt = $db->prepare("UPDATE students SET stream_id = ? WHERE id = ?");
                        $updateStudentStmt->execute([$toStreamId, $studentId]);
                    }

                    $updateOldEnrollStmt = $db->prepare("
                        UPDATE class_enrollments
                        SET promotion_status = 'promoted',
                            promoted_to_class_id = ?,
                            promoted_to_stream_id = ?,
                            promotion_date = CURDATE(),
                            completed_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updateOldEnrollStmt->execute([$targetClassId, $targetStreamId, $enrollment['id']]);

                    $promoted++;
                } else {
                    $updateOldEnrollStmt = $db->prepare("
                        UPDATE class_enrollments
                        SET promotion_status = 'retained',
                            promotion_date = CURDATE()
                        WHERE id = ?
                    ");
                    $updateOldEnrollStmt->execute([$enrollment['id']]);
                    $retained++;
                }

                // Record in student_promotions
                $insertPromoStmt = $db->prepare("
                    INSERT INTO student_promotions
                    (batch_id, from_enrollment_id, to_enrollment_id, from_academic_year_id, to_academic_year_id,
                     student_id, current_class_id, current_stream_id, promoted_to_class_id, promoted_to_stream_id,
                     from_academic_year, to_academic_year, from_term_id, promotion_status, overall_score,
                     promotion_reason, approved_by, approval_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, NOW())
                ");
                $insertPromoStmt->execute([
                    $batchId,
                    $enrollment['id'],
                    $toEnrollmentId,
                    $fromYearId,
                    $toYearId,
                    $studentId,
                    $enrollment['class_id'],
                    $enrollment['stream_id'],
                    $targetClassId,
                    $targetStreamId,
                    $fromYear,
                    $toYear,
                    $fromTermId,
                    $promotionStatus,
                    $studentNotes,
                    $userId
                ]);
                $processed++;
            }

            // Update batch
            $updateBatchStmt = $db->prepare("
                UPDATE promotion_batches
                SET status = 'completed',
                    total_students_processed = ?,
                    total_promoted = ?,
                    total_rejected = ?,
                    completed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateBatchStmt->execute([$processed, $promoted, $retained, $batchId]);

            $db->commit();

            return $this->success([
                'message' => "Promotion completed successfully. {$promoted} promoted, {$retained} retained.",
                'batch_id' => $batchId,
                'processed' => $processed,
                'promoted' => $promoted,
                'retained' => $retained
            ]);
        } catch (\Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            return $this->badRequest('Failed to execute promotion: ' . $e->getMessage());
        }
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

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        $hasAccess = false;
        foreach ($allowedRoles as $role) {
            if ($this->userHasRole($role)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess) {
            return $this->forbidden('You do not have permission to access counseling data');
        }

        try {
            $db = $this->db->getConnection();

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Terms
            $termsStmt = $db->query("SELECT id, name FROM academic_terms ORDER BY start_date ASC");
            $terms = $termsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Staff (for assignment)
            $staffStmt = $db->query("SELECT id, CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE status = 'active' ORDER BY full_name ASC");
            $staff = $staffStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Students (for case creation)
            $studentsStmt = $db->query("SELECT id, admission_no, CONCAT_WS(' ', first_name, last_name) AS full_name FROM students WHERE status = 'active' ORDER BY full_name ASC");
            $students = $studentsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'academic_years' => $years,
                'terms' => $terms,
                'classes' => $classes,
                'streams' => $streams,
                'staff' => $staff,
                'students' => $students,
                'case_types' => ['academic', 'behavioral', 'personal', 'family', 'career', 'disciplinary', 'other'],
                'priorities' => ['low', 'medium', 'high', 'urgent'],
                'statuses' => ['open', 'in_progress', 'resolved', 'closed', 'cancelled'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load counseling metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/counseling-cases
     */
    public function getCounselingCases($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];
        $hasAccess = false;
        foreach ($allowedRoles as $role) {
            if ($this->userHasRole($role)) {
                $hasAccess = true;
                break;
            }
        }
        if (!$hasAccess) {
            return $this->forbidden('You do not have permission to access counseling data');
        }

        try {
            $db = $this->db->getConnection();

            $academicYear = !empty($_GET['academic_year']) ? trim($_GET['academic_year']) : null;
            $termId = !empty($_GET['term_id']) ? (int)$_GET['term_id'] : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $caseType = !empty($_GET['case_type']) ? trim($_GET['case_type']) : null;
            $priority = !empty($_GET['priority']) ? trim($_GET['priority']) : null;
            $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
            $gender = !empty($_GET['gender']) ? trim($_GET['gender']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query
            $sql = "
                SELECT
                    c.id,
                    c.case_code,
                    c.title,
                    c.case_type,
                    c.priority,
                    c.status,
                    c.referral_source,
                    c.opened_at,
                    c.next_follow_up_at,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS counselor_name
                FROM student_counseling_cases c
                JOIN students s ON s.id = c.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN users u ON u.id = c.assigned_to
                WHERE c.status != 'cancelled'
            ";

            $bindings = [];

            // Apply chaplain/counselor scope filtering - only show cases assigned to them
            if ($userRole === 'chaplain') {
                $sql .= " AND c.assigned_to = ?";
                $bindings[] = $this->user['id'];
            }

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($caseType) {
                $sql .= " AND c.case_type = ?";
                $bindings[] = $caseType;
            }

            if ($priority) {
                $sql .= " AND c.priority = ?";
                $bindings[] = $priority;
            }

            if ($status) {
                $sql .= " AND c.status = ?";
                $bindings[] = $status;
            }

            if ($gender) {
                $sql .= " AND s.gender = ?";
                $bindings[] = $gender;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR c.title LIKE ? OR CONCAT_WS(' ', u.first_name, u.last_name) LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY c.opened_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $cases = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add last session date
            foreach ($cases as &$case) {
                $sessionStmt = $db->prepare("
                    SELECT MAX(session_date) AS last_session
                    FROM student_counseling_sessions
                    WHERE case_id = ?
                ");
                $sessionStmt->execute([$case['id']]);
                $sessionData = $sessionStmt->fetch(\PDO::FETCH_ASSOC);
                $case['last_session'] = $sessionData['last_session'] ?? null;
            }

            return $this->success($cases);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load counseling cases: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/counseling-case/{caseId}
     */
    public function getCounselingCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get case details
            $caseStmt = $db->prepare("
                SELECT
                    c.*,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS counselor_name
                FROM student_counseling_cases c
                JOIN students s ON s.id = c.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN users u ON u.id = c.assigned_to
                WHERE c.id = ?
            ");
            $caseStmt->execute([$caseId]);
            $case = $caseStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$case) {
                return $this->notFound('Case not found');
            }

            // Get student details
            $studentStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
            $studentStmt->execute([$case['student_id']]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            // Get sessions
            $sessionsStmt = $db->prepare("
                SELECT * FROM student_counseling_sessions
                WHERE case_id = ?
                ORDER BY session_date DESC
            ");
            $sessionsStmt->execute([$caseId]);
            $sessions = $sessionsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Filter confidential notes based on role (simplified - implement role-based filtering as needed)
            $userRole = $this->user['role'] ?? '';
            if (!in_array($userRole, ['counselor', 'chaplain', 'headteacher', 'admin'])) {
                foreach ($sessions as &$session) {
                    unset($session['confidential_notes']);
                }
            }

            return $this->success([
                'case' => $case,
                'student' => $student,
                'sessions' => $sessions,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load case details: ' . $e->getMessage());
        }
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

        try {
            $db = $this->db->getConnection();

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'academic_years' => $years,
                'classes' => $classes,
                'streams' => $streams,
                'health_categories' => ['general', 'allergy', 'condition', 'medication', 'injury', 'incident', 'other'],
                'severities' => ['low', 'medium', 'high', 'critical'],
                'statuses' => ['active', 'inactive', 'resolved', 'monitoring'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load health metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/health-records
     */
    public function getHealthRecords($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $db = $this->db->getConnection();

            $academicYear = !empty($_GET['academic_year']) ? trim($_GET['academic_year']) : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $healthCategory = !empty($_GET['health_category']) ? trim($_GET['health_category']) : null;
            $alertStatus = !empty($_GET['alert_status']) ? trim($_GET['alert_status']) : null;
            $severity = !empty($_GET['severity']) ? trim($_GET['severity']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query
            $sql = "
                SELECT
                    h.id,
                    h.record_code,
                    h.health_category,
                    h.alert_type,
                    h.condition_name,
                    h.allergy_name,
                    h.medication_name,
                    h.severity,
                    h.status,
                    h.emergency_flag,
                    h.description,
                    h.action_instructions,
                    h.next_review_date,
                    h.emergency_contact_name,
                    h.emergency_contact_phone,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name
                FROM student_health_records h
                JOIN students s ON s.id = h.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                WHERE 1=1
            ";

            $bindings = [];

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($healthCategory) {
                $sql .= " AND h.health_category = ?";
                $bindings[] = $healthCategory;
            }

            if ($alertStatus) {
                $sql .= " AND h.status = ?";
                $bindings[] = $alertStatus;
            }

            if ($severity) {
                $sql .= " AND h.severity = ?";
                $bindings[] = $severity;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR h.condition_name LIKE ? OR h.allergy_name LIKE ? OR h.medication_name LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY h.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $records = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add clinic visits count and last visit date
            foreach ($records as &$record) {
                $visitStmt = $db->prepare("
                    SELECT COUNT(*) AS visits_count, MAX(visit_date) AS last_visit
                    FROM student_health_visits
                    WHERE student_id = ?
                ");
                $visitStmt->execute([$record['student_id']]);
                $visitData = $visitStmt->fetch(\PDO::FETCH_ASSOC);
                $record['clinic_visits_count'] = $visitData['visits_count'] ?? 0;
                $record['last_visit'] = $visitData['last_visit'] ?? null;
            }

            // Filter sensitive notes based on role
            $userRole = $this->user['role'] ?? '';
            if (!in_array($userRole, ['headteacher', 'director', 'admin', 'nurse'])) {
                foreach ($records as &$record) {
                    unset($record['sensitive_notes']);
                }
            }

            return $this->success($records);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load health records: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/health-record/{recordId}
     */
    public function getHealthRecord($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $recordId = $id !== null ? (int)$id : null;
        if ($recordId === null) {
            return $this->badRequest('Record ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get record details
            $recordStmt = $db->prepare("
                SELECT
                    h.*,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.gender,
                    s.blood_group,
                    s.allergies,
                    s.chronic_conditions,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name
                FROM student_health_records h
                JOIN students s ON s.id = h.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                WHERE h.id = ?
            ");
            $recordStmt->execute([$recordId]);
            $record = $recordStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->notFound('Health record not found');
            }

            // Get student details
            $studentStmt = $db->prepare("SELECT * FROM students WHERE id = ?");
            $studentStmt->execute([$record['student_id']]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            // Get clinic visits
            $visitsStmt = $db->prepare("
                SELECT * FROM student_health_visits
                WHERE student_id = ?
                ORDER BY visit_date DESC
            ");
            $visitsStmt->execute([$record['student_id']]);
            $visits = $visitsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get reviews
            $reviewsStmt = $db->prepare("
                SELECT * FROM student_health_reviews
                WHERE health_record_id = ?
                ORDER BY review_date DESC
            ");
            $reviewsStmt->execute([$recordId]);
            $reviews = $reviewsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Filter sensitive notes based on role
            $userRole = $this->user['role'] ?? '';
            if (!in_array($userRole, ['headteacher', 'director', 'admin', 'nurse'])) {
                unset($record['sensitive_notes']);
            }

            return $this->success([
                'record' => $record,
                'student' => $student,
                'visits' => $visits,
                'reviews' => $reviews,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load record details: ' . $e->getMessage());
        }
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

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access catering data');

        }

        try {
            $db = $this->db->getConnection();

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Dormitories
            $dormStmt = $db->query("SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC");
            $dormitories = $dormStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'classes' => $classes,
                'streams' => $streams,
                'dormitories' => $dormitories,
                'diet_types' => ['normal', 'vegetarian', 'diabetic', 'allergy', 'medical', 'religious', 'other'],
                'meal_types' => ['breakfast', 'lunch', 'supper', 'snack'],
                'boarding_statuses' => ['active', 'on_leave', 'sick', 'suspended', 'checked_out'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load catering metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/catering-boarding-students
     */
    public function getCateringBoardingStudents($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access catering data');

        }

        try {
            $db = $this->db->getConnection();

            $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
            $meal = !empty($_GET['meal']) ? trim($_GET['meal']) : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $gender = !empty($_GET['gender']) ? trim($_GET['gender']) : null;
            $dormitoryId = !empty($_GET['dormitory_id']) ? (int)$_GET['dormitory_id'] : null;
            $boardingStatus = !empty($_GET['boarding_status']) ? trim($_GET['boarding_status']) : null;
            $dietType = !empty($_GET['diet_type']) ? trim($_GET['diet_type']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query to get boarding students
            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name,
                    d.id AS dormitory_id,
                    d.name AS dormitory_name,
                    da.status AS boarding_status,
                    smp.diet_type,
                    (smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL) AS has_food_restriction
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN dormitories d ON d.id = da.dormitory_id
                LEFT JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                WHERE da.id IS NOT NULL
            ";

            $bindings = [];

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND s.gender = ?";
                $bindings[] = $gender;
            }

            if ($dormitoryId) {
                $sql .= " AND d.id = ?";
                $bindings[] = $dormitoryId;
            }

            if ($dietType) {
                $sql .= " AND smp.diet_type = ?";
                $bindings[] = $dietType;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term);
            }

            $sql .= " ORDER BY s.first_name, s.last_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add meal status for the selected date
            foreach ($students as &$student) {
                $student['breakfast'] = true;
                $student['lunch'] = true;
                $student['supper'] = true;
                $student['meal_status_today'] = 'eating';

                // Get meal status for the date
                $mealStatusStmt = $db->prepare("
                    SELECT meal_type, status
                    FROM catering_meal_statuses
                    WHERE student_id = ? AND meal_date = ?
                ");
                $mealStatusStmt->execute([$student['student_id'], $date]);
                $mealStatuses = $mealStatusStmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($mealStatuses as $ms) {
                    if ($ms['meal_type'] === 'breakfast') {
                        $student['breakfast'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    } elseif ($ms['meal_type'] === 'lunch') {
                        $student['lunch'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    } elseif ($ms['meal_type'] === 'supper') {
                        $student['supper'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    }
                }

                // Check boarding attendance for status
                $attendanceStmt = $db->prepare("
                    SELECT status
                    FROM boarding_attendance
                    WHERE student_id = ? AND date = ?
                ");
                $attendanceStmt->execute([$student['student_id'], $date]);
                $attendance = $attendanceStmt->fetch(\PDO::FETCH_ASSOC);

                if ($attendance) {
                    if ($attendance['status'] === 'sick_bay') {
                        $student['meal_status_today'] = 'sick_meal';
                    } elseif ($attendance['status'] === 'absent' || $attendance['status'] === 'permission') {
                        $student['meal_status_today'] = 'on_leave';
                    }
                }
            }

            return $this->success($students);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load boarding students: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/catering-boarding-summary
     */
    public function getCateringBoardingSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access catering data');

        }

        try {
            $db = $this->db->getConnection();

            $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
            $meal = !empty($_GET['meal']) ? trim($_GET['meal']) : null;

            // Get total boarders
            $totalBoardersStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS total
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
            ");
            $totalBoarders = $totalBoardersStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Get meal counts based on meal statuses
            $breakfastCount = $totalBoarders;
            $lunchCount = $totalBoarders;
            $supperCount = $totalBoarders;

            // Count students not eating/on leave/sick
            $notEatingStmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN catering_meal_statuses cms ON cms.student_id = s.id AND cms.meal_date = ? AND cms.status IN ('not_eating', 'on_leave', 'sick_meal')
                WHERE cms.id IS NOT NULL
            ");
            $notEatingStmt->execute([$date]);
            $notEating = $notEatingStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get special diet count
            $specialDietStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                INNER JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1 AND smp.diet_type != 'normal'
            ");
            $specialDiet = $specialDietStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get sick bay count from attendance
            $sickBayStmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                INNER JOIN boarding_attendance ba ON ba.student_id = s.id AND ba.date = ? AND ba.status = 'sick_bay'
            ");
            $sickBayStmt->execute([$date]);
            $sickBay = $sickBayStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get breakdown by class
            $breakdownByClassStmt = $db->query("
                SELECT cls.name AS class_name, COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                GROUP BY cls.id, cls.name
                ORDER BY cls.name
            ");
            $breakdownByClass = $breakdownByClassStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get breakdown by diet
            $breakdownByDietStmt = $db->query("
                SELECT smp.diet_type, COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                GROUP BY smp.diet_type
                ORDER BY smp.diet_type
            ");
            $breakdownByDiet = $breakdownByDietStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'total_boarders' => $totalBoarders,
                'breakfast_count' => $breakfastCount - $notEating,
                'lunch_count' => $lunchCount - $notEating,
                'supper_count' => $supperCount - $notEating,
                'special_diet_count' => $specialDiet,
                'absent_or_leave_count' => $notEating,
                'sick_meal_count' => $sickBay,
                'food_store_items_required' => 0, // Would need integration with inventory
                'breakdown_by_class' => $breakdownByClass,
                'breakdown_by_diet' => $breakdownByDiet,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load catering summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/catering-boarding-student/{studentId}
     */
    public function getCateringBoardingStudent($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin', 'headteacher', 'director', 'boarding_master', 'boarding_matron'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access catering data');

        }

        $studentId = $id !== null ? (int)$id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get student basic info (catering-safe only)
            $studentStmt = $db->prepare("
                SELECT id, admission_no, first_name, last_name, gender
                FROM students WHERE id = ?
            ");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->notFound('Student not found');
            }

            // Get boarding info
            $boardingStmt = $db->prepare("
                SELECT da.*, d.name AS dormitory_name, d.gender AS dormitory_gender
                FROM dormitory_assignments da
                JOIN dormitories d ON d.id = da.dormitory_id
                WHERE da.student_id = ? AND da.status = 'active'
                ORDER BY da.assigned_date DESC
                LIMIT 1
            ");
            $boardingStmt->execute([$studentId]);
            $boarding = $boardingStmt->fetch(\PDO::FETCH_ASSOC);

            // Get class/stream info
            $classInfoStmt = $db->prepare("
                SELECT cs.stream_name, cls.name AS class_name
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                WHERE s.id = ?
            ");
            $classInfoStmt->execute([$studentId]);
            $classInfo = $classInfoStmt->fetch(\PDO::FETCH_ASSOC);

            // Get meal profile
            $dietStmt = $db->prepare("
                SELECT * FROM student_meal_profiles WHERE student_id = ? AND active = 1
            ");
            $dietStmt->execute([$studentId]);
            $diet = $dietStmt->fetch(\PDO::FETCH_ASSOC);

            // Get meal history
            $mealHistoryStmt = $db->prepare("
                SELECT meal_date, meal_type, status, notes
                FROM catering_meal_statuses
                WHERE student_id = ?
                ORDER BY meal_date DESC, meal_type DESC
                LIMIT 10
            ");
            $mealHistoryStmt->execute([$studentId]);
            $mealHistory = $mealHistoryStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get today's status
            $todayStatusStmt = $db->prepare("
                SELECT * FROM catering_meal_statuses
                WHERE student_id = ? AND meal_date = CURDATE()
            ");
            $todayStatusStmt->execute([$studentId]);
            $todayStatus = $todayStatusStmt->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'student' => $student,
                'boarding' => $boarding,
                'diet' => $diet,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'dormitory_name' => $boarding['dormitory_name'] ?? null,
                'meal_restrictions' => [],
                'meal_history' => $mealHistory,
                'today_status' => $todayStatus,
                'catering_notes' => [],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load meal profile: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/catering-menu-plan
     */
    public function postCateringMenuPlan($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to plan meals');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $planDate = !empty($data['date']) ? $data['date'] : null;
            $mealType = !empty($data['meal_type']) ? $data['meal_type'] : null;
            $menuItem = !empty($data['menu_item']) ? $data['menu_item'] : null;
            $expectedCount = !empty($data['expected_count']) ? (int)$data['expected_count'] : 0;
            $specialDietCount = !empty($data['special_diet_count']) ? (int)$data['special_diet_count'] : 0;
            $notes = $data['notes'] ?? null;

            if (!$planDate || !$mealType) {
                return $this->badRequest('Date and meal type are required');
            }

            // Insert or update meal plan
            $checkStmt = $db->prepare("
                SELECT id FROM meal_plans
                WHERE plan_date = ? AND meal_type = ?
            ");
            $checkStmt->execute([$planDate, $mealType]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $updateStmt = $db->prepare("
                    UPDATE meal_plans
                    SET menu_item_id = ?, planned_servings = ?, prepared_quantity = ?, actual_servings = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateStmt->execute([null, $expectedCount, 0, 0, $notes, $existing['id']]);
            } else {
                $insertStmt = $db->prepare("
                    INSERT INTO meal_plans (plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings, status, created_by, notes)
                    VALUES (?, ?, ?, ?, 0, 0, 'planned', ?, ?)
                ");
                $insertStmt->execute([$planDate, $mealType, null, $expectedCount, $userId, $notes]);
            }

            return $this->success(['message' => 'Meal plan saved successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to save meal plan: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/catering-food-requisition
     */
    public function getCateringFoodRequisition($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check catering permissions
        $allowedRoles = ['cateress', 'catering_manager', 'admin'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access food requisition');

        }

        try {
            $db = $this->db->getConnection();

            // Check if inventory tables exist
            $tableCheck = $db->query("SHOW TABLES LIKE 'inventory_items'");
            $inventoryExists = $tableCheck->fetchAll();

            if (!$inventoryExists) {
                return $this->success(['available' => false, 'message' => 'Inventory tables not found']);
            }

            // This is a simplified implementation - in production, you would calculate
            // required quantities based on meal plans and recipes
            return $this->success([
                'available' => true,
                'message' => 'Food requisition calculation requires recipe integration',
                'items' => [],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load food requisition: ' . $e->getMessage());
        }
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

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access boarding data');

        }

        try {
            $db = $this->db->getConnection();

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Dormitories
            $dormStmt = $db->query("SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC");
            $dormitories = $dormStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'academic_years' => $years,
                'classes' => $classes,
                'streams' => $streams,
                'dormitories' => $dormitories,
                'boarding_statuses' => ['active', 'on_leave', 'sick', 'checked_out', 'suspended'],
                'roll_call_statuses' => ['present', 'absent', 'late', 'excused', 'sick_bay', 'on_exeat'],
                'exeat_statuses' => ['requested', 'approved', 'out', 'returned', 'overdue', 'cancelled'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load boarding metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/boarding-students
     */
    public function getBoardingStudents($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access boarding data');

        }

        try {
            $db = $this->db->getConnection();

            $academicYear = !empty($_GET['academic_year']) ? trim($_GET['academic_year']) : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $gender = !empty($_GET['gender']) ? trim($_GET['gender']) : null;
            $dormitoryId = !empty($_GET['dormitory_id']) ? (int)$_GET['dormitory_id'] : null;
            $bedStatus = !empty($_GET['bed_status']) ? trim($_GET['bed_status']) : null;
            $boardingStatus = !empty($_GET['boarding_status']) ? trim($_GET['boarding_status']) : null;
            $rollCallStatus = !empty($_GET['roll_call_status']) ? trim($_GET['roll_call_status']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query to get boarding students
            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name,
                    d.id AS dormitory_id,
                    d.name AS dormitory_name,
                    da.bed_number,
                    da.status AS boarding_status
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                LEFT JOIN dormitories d ON d.id = da.dormitory_id
                WHERE da.id IS NOT NULL
            ";

            $bindings = [];

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND s.gender = ?";
                $bindings[] = $gender;
            }

            if ($dormitoryId) {
                $sql .= " AND d.id = ?";
                $bindings[] = $dormitoryId;
            }

            if ($bedStatus === 'assigned') {
                $sql .= " AND da.bed_number IS NOT NULL AND da.bed_number != ''";
            } elseif ($bedStatus === 'unassigned') {
                $sql .= " AND (da.bed_number IS NULL OR da.bed_number = '')";
            }

            if ($boardingStatus) {
                $sql .= " AND da.status = ?";
                $bindings[] = $boardingStatus;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR d.name LIKE ? OR da.bed_number LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY s.first_name, s.last_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $students = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add roll call status and exeat status for today
            $today = date('Y-m-d');
            foreach ($students as &$student) {
                $student['roll_call_status_today'] = 'present';
                $student['exeat_status'] = 'none';
                $student['has_special_alert'] = false;
                $student['special_alert_summary'] = '';

                // Get roll call status for today
                $rollCallStmt = $db->prepare("
                    SELECT status FROM boarding_attendance
                    WHERE student_id = ? AND date = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $rollCallStmt->execute([$student['student_id'], $today]);
                $rollCall = $rollCallStmt->fetch(\PDO::FETCH_ASSOC);
                if ($rollCall) {
                    $student['roll_call_status_today'] = $rollCall['status'];
                }

                // Check for active exeat (using student_permissions with EXEAT type)
                $exeatStmt = $db->prepare("
                    SELECT status FROM student_permissions
                    WHERE student_id = ? AND permission_type_id = 1
                    AND start_date <= ? AND (end_date >= ? OR end_date IS NULL)
                    AND status IN ('approved', 'out')
                    ORDER BY start_date DESC
                    LIMIT 1
                ");
                $exeatStmt->execute([$student['student_id'], $today, $today]);
                $exeat = $exeatStmt->fetch(\PDO::FETCH_ASSOC);
                if ($exeat) {
                    $student['exeat_status'] = $exeat['status'];
                }

                // Check for special alerts (food restrictions, health alerts, special needs)
                $alertStmt = $db->prepare("
                    SELECT COUNT(*) AS alert_count
                    FROM student_meal_profiles smp
                    WHERE smp.student_id = ? AND smp.active = 1
                    AND (smp.diet_type != 'normal' OR smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL)
                ");
                $alertStmt->execute([$student['student_id']]);
                $alertCount = $alertStmt->fetch(\PDO::FETCH_ASSOC)['alert_count'] ?? 0;
                $student['has_special_alert'] = $alertCount > 0;
            }

            return $this->success($students);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load boarding students: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/boarding-summary
     */
    public function getBoardingSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access boarding data');

        }

        try {
            $db = $this->db->getConnection();

            // Get total boarders
            $totalBoardersStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS total
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
            ");
            $totalBoarders = $totalBoardersStmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Get boys and girls
            $boysStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                WHERE s.gender = 'male'
            ");
            $boys = $boysStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            $girlsStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                WHERE s.gender = 'female'
            ");
            $girls = $girlsStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get on exeat count
            $today = date('Y-m-d');
            $onExeatStmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                INNER JOIN student_permissions sp ON sp.student_id = s.id AND sp.permission_type_id = 1
                WHERE sp.status IN ('approved', 'out')
                AND sp.start_date <= ? AND (sp.end_date >= ? OR sp.end_date IS NULL)
            ");
            $onExeatStmt->execute([$today, $today]);
            $onExeat = $onExeatStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get absent count from roll call
            $absentStmt = $db->prepare("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                INNER JOIN boarding_attendance ba ON ba.student_id = s.id AND ba.date = ?
                WHERE ba.status IN ('absent', 'sick_bay')
            ");
            $absentStmt->execute([$today]);
            $absent = $absentStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get special alerts count
            $specialAlertsStmt = $db->query("
                SELECT COUNT(DISTINCT s.id) AS count
                FROM students s
                INNER JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                INNER JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                WHERE smp.diet_type != 'normal' OR smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL
            ");
            $specialAlerts = $specialAlertsStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            return $this->success([
                'total_boarders' => $totalBoarders,
                'boys_boarders' => $boys,
                'girls_boarders' => $girls,
                'on_exeat_count' => $onExeat,
                'absent_count' => $absent,
                'special_alerts_count' => $specialAlerts,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load boarding summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/boarding-student/{studentId}
     */
    public function getBoardingStudent($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access boarding data');

        }

        $studentId = $id !== null ? (int)$id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get student basic info (boarding-safe only)
            $studentStmt = $db->prepare("
                SELECT id, admission_no, first_name, last_name, gender
                FROM students WHERE id = ?
            ");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->notFound('Student not found');
            }

            // Get boarding info
            $boardingStmt = $db->prepare("
                SELECT da.*, d.name AS dormitory_name, d.gender AS dormitory_gender
                FROM dormitory_assignments da
                JOIN dormitories d ON d.id = da.dormitory_id
                WHERE da.student_id = ? AND da.status = 'active'
                ORDER BY da.assigned_date DESC
                LIMIT 1
            ");
            $boardingStmt->execute([$studentId]);
            $boarding = $boardingStmt->fetch(\PDO::FETCH_ASSOC);

            // Get class/stream info
            $classInfoStmt = $db->prepare("
                SELECT cs.stream_name, cls.name AS class_name
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                WHERE s.id = ?
            ");
            $classInfoStmt->execute([$studentId]);
            $classInfo = $classInfoStmt->fetch(\PDO::FETCH_ASSOC);

            // Get roll call history
            $rollCallStmt = $db->prepare("
                SELECT date AS roll_call_date, session_id, status
                FROM boarding_attendance
                WHERE student_id = ?
                ORDER BY date DESC
                LIMIT 10
            ");
            $rollCallStmt->execute([$studentId]);
            $rollCallHistory = $rollCallStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get exeat history
            $exeatStmt = $db->prepare("
                SELECT exeat_type, start_date AS leave_at, end_date AS expected_return_at, status
                FROM student_permissions
                WHERE student_id = ? AND permission_type_id = 1
                ORDER BY start_date DESC
                LIMIT 10
            ");
            $exeatStmt->execute([$studentId]);
            $exeatHistory = $exeatStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get boarding notes
            $notesStmt = $db->prepare("
                SELECT note_type, note, created_at
                FROM student_boarding_notes
                WHERE student_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $notesStmt->execute([$studentId]);
            $boardingNotes = $notesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'student' => $student,
                'boarding' => $boarding,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'dormitory_name' => $boarding['dormitory_name'] ?? null,
                'roll_call_history' => $rollCallHistory,
                'exeat_history' => $exeatHistory,
                'boarding_notes' => $boardingNotes,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load boarding profile: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/boarding-assign-dorm
     */
    public function postBoardingAssignDorm($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'housemother', 'admin'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to assign dormitories');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
            $dormitoryId = !empty($data['dormitory_id']) ? (int)$data['dormitory_id'] : null;
            $bedNumber = $data['bed_number'] ?? null;
            $allocationDate = !empty($data['allocation_date']) ? $data['allocation_date'] : date('Y-m-d');
            $notes = $data['notes'] ?? null;

            if (!$studentId || !$dormitoryId) {
                return $this->badRequest('Student ID and Dormitory ID are required');
            }

            // End current active assignment if exists
            $endStmt = $db->prepare("
                UPDATE dormitory_assignments
                SET status = 'transferred', end_date = ?, updated_at = CURRENT_TIMESTAMP
                WHERE student_id = ? AND status = 'active'
            ");
            $endStmt->execute([$allocationDate, $studentId]);

            // Create new assignment
            $insertStmt = $db->prepare("
                INSERT INTO dormitory_assignments (student_id, dormitory_id, bed_number, assigned_date, status, assigned_by, notes)
                VALUES (?, ?, ?, ?, 'active', ?, ?)
            ");
            $insertStmt->execute([$studentId, $dormitoryId, $bedNumber, $allocationDate, $userId, $notes]);

            return $this->success(['message' => 'Dormitory assigned successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to assign dormitory: ' . $e->getMessage());
        }
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

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access transport data');

        }

        try {
            $db = $this->db->getConnection();

            // Routes
            $routesStmt = $db->query("SELECT id, name AS route_name, code FROM transport_routes WHERE status = 'active' ORDER BY name ASC");
            $routes = $routesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Vehicles
            $vehiclesStmt = $db->query("SELECT id, registration_number AS vehicle_number, type, make, model, capacity FROM transport_vehicles WHERE status = 'active' ORDER BY registration_number ASC");
            $vehicles = $vehiclesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'routes' => $routes,
                'vehicles' => $vehicles,
                'classes' => $classes,
                'streams' => $streams,
                'trip_sessions' => ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'],
                'transport_statuses' => ['active', 'suspended', 'not_riding', 'transferred'],
                'attendance_statuses' => ['pending', 'picked_up', 'dropped_off', 'absent', 'excused', 'not_riding'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load transport metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/transport-passengers
     */
    public function getTransportPassengers($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access transport data');

        }

        try {
            $db = $this->db->getConnection();

            $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
            $routeId = !empty($_GET['route_id']) ? (int)$_GET['route_id'] : null;
            $vehicleId = !empty($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;
            $tripSession = !empty($_GET['trip_session']) ? trim($_GET['trip_session']) : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $gender = !empty($_GET['gender']) ? trim($_GET['gender']) : null;
            $transportStatus = !empty($_GET['transport_status']) ? trim($_GET['transport_status']) : null;
            $attendanceStatus = !empty($_GET['attendance_status']) ? trim($_GET['attendance_status']) : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query to get transport passengers
            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    s.gender,
                    cs.class_id,
                    cls.name AS class_name,
                    cs.stream_name,
                    tr.id AS route_id,
                    tr.name AS route_name,
                    tv.id AS vehicle_id,
                    tv.registration_number AS vehicle_name,
                    ts.name AS pickup_point,
                    ts_drop.name AS dropoff_point,
                    sp.phone_1 AS guardian_phone
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                LEFT JOIN transport_routes tr ON tr.id = sta.route_id
                LEFT JOIN transport_vehicles tv ON tv.id = sta.vehicle_id
                LEFT JOIN transport_stops ts ON ts.id = sta.pickup_stop_id
                LEFT JOIN transport_stops ts_drop ON ts_drop.id = sta.dropoff_stop_id
                LEFT JOIN student_parents sp ON sp.student_id = s.id AND sp.is_primary_contact = 1
                LEFT JOIN parents p ON p.id = sp.parent_id
                WHERE sta.id IS NOT NULL
            ";

            // Apply driver scope filtering - only show passengers for driver's assigned vehicle
            $bindings = [];
            if ($userRole === 'driver') {
                $sql .= " AND tv.driver_id = ?";
                $bindings[] = $this->user['id'];
            }

            if ($routeId) {
                $sql .= " AND tr.id = ?";
                $bindings[] = $routeId;
            }

            if ($vehicleId) {
                $sql .= " AND tv.id = ?";
                $bindings[] = $vehicleId;
            }

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND s.gender = ?";
                $bindings[] = $gender;
            }

            if ($transportStatus) {
                $sql .= " AND sta.status = ?";
                $bindings[] = $transportStatus;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR ts.name LIKE ? OR sp.phone_1 LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY s.first_name, s.last_name";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $passengers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Add attendance status for today
            foreach ($passengers as &$passenger) {
                $passenger['today_status'] = 'pending';
                $passenger['has_transport_alert'] = false;
                $passenger['transport_alert_summary'] = '';

                // Get attendance status for today
                $attendanceStmt = $db->prepare("
                    SELECT status FROM student_transport_attendance
                    WHERE student_id = ? AND attendance_date = ?
                    AND trip_session = ?
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $session = $tripSession ?: 'morning_pickup';
                $attendanceStmt->execute([$passenger['student_id'], $date, $session]);
                $attendance = $attendanceStmt->fetch(\PDO::FETCH_ASSOC);
                if ($attendance) {
                    $passenger['today_status'] = $attendance['status'];
                }

                // Check for transport alerts
                $alertStmt = $db->prepare("
                    SELECT COUNT(*) AS alert_count
                    FROM student_transport_notes
                    WHERE student_id = ? AND visibility = 'public' AND resolved = 0
                ");
                $alertStmt->execute([$passenger['student_id']]);
                $alertCount = $alertStmt->fetch(\PDO::FETCH_ASSOC)['alert_count'] ?? 0;
                $passenger['has_transport_alert'] = $alertCount > 0;
            }

            return $this->success($passengers);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load transport passengers: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/transport-summary
     */
    public function getTransportSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access transport data');

        }

        try {
            $db = $this->db->getConnection();

            $date = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');
            $routeId = !empty($_GET['route_id']) ? (int)$_GET['route_id'] : null;
            $vehicleId = !empty($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : null;

            // Get total passengers for driver's route/vehicle
            $sql = "
                SELECT COUNT(DISTINCT s.id) AS total
                FROM students s
                INNER JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                LEFT JOIN transport_routes tr ON tr.id = sta.route_id
                LEFT JOIN transport_vehicles tv ON tv.id = sta.vehicle_id
            ";

            $bindings = [];
            if ($routeId) {
                $sql .= " AND tr.id = ?";
                $bindings[] = $routeId;
            }
            if ($vehicleId) {
                $sql .= " AND tv.id = ?";
                $bindings[] = $vehicleId;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $totalPassengers = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;

            // Get today's attendance counts
            $attendanceSql = "
                SELECT
                    COUNT(DISTINCT CASE WHEN status = 'picked_up' THEN s.id END) AS picked_up,
                    COUNT(DISTINCT CASE WHEN status = 'dropped_off' THEN s.id END) AS dropped_off,
                    COUNT(DISTINCT CASE WHEN status = 'absent' THEN s.id END) AS absent,
                    COUNT(DISTINCT CASE WHEN status = 'not_riding' THEN s.id END) AS not_riding,
                    COUNT(DISTINCT CASE WHEN status = 'pending' THEN s.id END) AS pending
                FROM student_transport_attendance sta
                JOIN students s ON s.student_id = sta.student_id
                WHERE sta.attendance_date = ?
            ";

            $attendanceBindings = [$date];
            if ($routeId) {
                $attendanceSql .= " AND sta.route_id = ?";
                $attendanceBindings[] = $routeId;
            }
            if ($vehicleId) {
                $attendanceSql .= " AND sta.vehicle_id = ?";
                $attendanceBindings[] = $vehicleId;
            }

            $attendanceStmt = $db->prepare($attendanceSql);
            $attendanceStmt->execute($attendanceBindings);
            $attendance = $attendanceStmt->fetch(\PDO::FETCH_ASSOC);

            // Get emergency alerts
            $alertSql = "
                SELECT COUNT(DISTINCT s.id) AS count
                FROM student_transport_notes stn
                JOIN students s ON s.student_id = stn.student_id
                LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                WHERE stn.visibility = 'public' AND stn.resolved = 0
            ";

            $alertBindings = [];
            if ($routeId) {
                $alertSql .= " AND sta.route_id = ?";
                $alertBindings[] = $routeId;
            }
            if ($vehicleId) {
                $alertSql .= " AND sta.vehicle_id = ?";
                $alertBindings[] = $vehicleId;
            }

            $alertStmt = $db->prepare($alertSql);
            $alertStmt->execute($alertBindings);
            $alerts = $alertStmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0;

            // Get route and vehicle names
            $routeName = '';
            $vehicleName = '';
            if ($routeId) {
                $routeStmt = $db->prepare("SELECT name FROM transport_routes WHERE id = ?");
                $routeStmt->execute([$routeId]);
                $routeName = $routeStmt->fetch(\PDO::FETCH_ASSOC)['name'] ?? '';
            }
            if ($vehicleId) {
                $vehicleStmt = $db->prepare("SELECT registration_number FROM transport_vehicles WHERE id = ?");
                $vehicleStmt->execute([$vehicleId]);
                $vehicleName = $vehicleStmt->fetch(\PDO::FETCH_ASSOC)['registration_number'] ?? '';
            }

            return $this->success([
                'total_passengers' => $totalPassengers,
                'expected_today' => $totalPassengers,
                'picked_up' => $attendance['picked_up'] ?? 0,
                'dropped_off' => $attendance['dropped_off'] ?? 0,
                'absent' => $attendance['absent'] ?? 0,
                'not_riding' => $attendance['not_riding'] ?? 0,
                'pending' => $attendance['pending'] ?? $totalPassengers,
                'emergency_alerts' => $alerts,
                'route_name' => $routeName,
                'vehicle_name' => $vehicleName,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load transport summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/transport-passenger/{studentId}
     */
    public function getTransportPassenger($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access transport data');

        }

        $studentId = $id !== null ? (int)$id : null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get student basic info (transport-safe only)
            $studentStmt = $db->prepare("
                SELECT id, admission_no, first_name, last_name, gender
                FROM students WHERE id = ?
            ");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->notFound('Student not found');
            }

            // Get transport info
            $transportStmt = $db->prepare("
                SELECT sta.*, tr.name AS route_name, tv.registration_number AS vehicle_name,
                       ts.name AS pickup_point, ts_drop.name AS dropoff_point,
                       sta.pickup_time, sta.dropoff_time
                FROM student_transport_assignments sta
                JOIN transport_routes tr ON tr.id = sta.route_id
                LEFT JOIN transport_vehicles tv ON tv.id = sta.vehicle_id
                LEFT JOIN transport_stops ts ON ts.id = sta.pickup_stop_id
                LEFT JOIN transport_stops ts_drop ON ts_drop.id = sta.dropoff_stop_id
                WHERE sta.student_id = ? AND sta.status = 'active'
                ORDER BY sta.created_at DESC
                LIMIT 1
            ");
            $transportStmt->execute([$studentId]);
            $transport = $transportStmt->fetch(\PDO::FETCH_ASSOC);

            // Get class/stream info
            $classInfoStmt = $db->prepare("
                SELECT cs.stream_name, cls.name AS class_name
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                WHERE s.id = ?
            ");
            $classInfoStmt->execute([$studentId]);
            $classInfo = $classInfoStmt->fetch(\PDO::FETCH_ASSOC);

            // Get guardian contact
            $guardianStmt = $db->prepare("
                SELECT p.phone_1 FROM student_parents sp
                JOIN parents p ON p.id = sp.parent_id
                WHERE sp.student_id = ? AND sp.is_primary_contact = 1 LIMIT 1
            ");
            $guardianStmt->execute([$studentId]);
            $guardian = $guardianStmt->fetch(\PDO::FETCH_ASSOC);

            // Get attendance history
            $attendanceStmt = $db->prepare("
                SELECT attendance_date AS date, trip_session AS session, status, marked_time AS time
                FROM student_transport_attendance
                WHERE student_id = ?
                ORDER BY attendance_date DESC
                LIMIT 10
            ");
            $attendanceStmt->execute([$studentId]);
            $attendance = $attendanceStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Get transport notes
            $notesStmt = $db->prepare("
                SELECT note_type, note, created_at
                FROM student_transport_notes
                WHERE student_id = ? AND visibility = 'public'
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $notesStmt->execute([$studentId]);
            $notes = $notesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'student' => $student,
                'transport' => $transport,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'route_name' => $transport['route_name'] ?? null,
                'vehicle_name' => $transport['vehicle_name'] ?? null,
                'guardian_phone' => $guardian['phone_1'] ?? null,
                'attendance' => $attendance,
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load transport profile: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/transport-attendance
     */
    public function postTransportAttendance($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to mark transport attendance');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $attendanceDate = !empty($data['attendance_date']) ? $data['attendance_date'] : date('Y-m-d');
            $routeId = !empty($data['route_id']) ? (int)$data['route_id'] : null;
            $vehicleId = !empty($data['vehicle_id']) ? (int)$data['vehicle_id'] : null;
            $tripSession = !empty($data['trip_session']) ? $data['trip_session'] : 'morning_pickup';
            $records = !empty($data['records']) ? $data['records'] : [];

            if (empty($records)) {
                return $this->badRequest('Attendance records are required');
            }

            foreach ($records as $record) {
                $studentId = !empty($record['student_id']) ? (int)$record['student_id'] : null;
                $status = !empty($record['status']) ? $record['status'] : 'pending';
                $markedTime = !empty($record['marked_time']) ? $record['marked_time'] : null;
                $notes = !empty($record['notes']) ? $record['notes'] : null;

                if (!$studentId) continue;

                // Check if attendance already exists
                $checkStmt = $db->prepare("
                    SELECT id FROM student_transport_attendance
                    WHERE student_id = ? AND attendance_date = ? AND trip_session = ?
                ");
                $checkStmt->execute([$studentId, $attendanceDate, $tripSession]);
                $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update existing
                    $updateStmt = $db->prepare("
                        UPDATE student_transport_attendance
                        SET status = ?, marked_time = ?, notes = ?, marked_by = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$status, $markedTime, $notes, $userId, $existing['id']]);
                } else {
                    // Insert new
                    $insertStmt = $db->prepare("
                        INSERT INTO student_transport_attendance (student_id, route_id, vehicle_id, attendance_date, trip_session, status, marked_time, notes, marked_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([$studentId, $routeId, $vehicleId, $attendanceDate, $tripSession, $status, $markedTime, $notes, $userId]);
                }
            }

            return $this->success(['message' => 'Attendance saved successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to save attendance: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/transport-incident
     */
    public function postTransportIncident($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check driver permissions
        $allowedRoles = ['driver', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to report incidents');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
            $routeId = !empty($data['route_id']) ? (int)$data['route_id'] : null;
            $vehicleId = !empty($data['vehicle_id']) ? (int)$data['vehicle_id'] : null;
            $incidentDateTime = !empty($data['incident_datetime']) ? $data['incident_datetime'] : date('Y-m-d H:i:s');
            $incidentType = !empty($data['incident_type']) ? $data['incident_type'] : 'other';
            $description = !empty($data['description']) ? $data['description'] : '';
            $actionTaken = !empty($data['action_taken']) ? $data['action_taken'] : null;
            $escalated = !empty($data['escalated']) ? (int)$data['escalated'] : 0;
            $notes = !empty($data['notes']) ? $data['notes'] : null;

            if (!$description) {
                return $this->badRequest('Description is required');
            }

            $insertStmt = $db->prepare("
                INSERT INTO student_transport_incidents (student_id, route_id, vehicle_id, incident_datetime, incident_type, description, action_taken, escalated, reported_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$studentId, $routeId, $vehicleId, $incidentDateTime, $incidentType, $description, $actionTaken, $escalated, $userId, $notes]);

            return $this->success(['message' => 'Incident reported successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to report incident: ' . $e->getMessage());
        }
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

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access welfare data');

        }

        try {
            $db = $this->db->getConnection();

            // Academic Years
            $yearsStmt = $db->query("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC");
            $years = $yearsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Terms
            $termsStmt = $db->query("SELECT id, name FROM terms ORDER BY name ASC");
            $terms = $termsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Classes
            $classesStmt = $db->query("SELECT id, name FROM classes ORDER BY name ASC");
            $classes = $classesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Streams
            $streamsStmt = $db->query("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC");
            $streams = $streamsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Staff (for assignment)
            $staffStmt = $db->query("SELECT id, CONCAT_WS(' ', first_name, last_name) AS full_name FROM users WHERE status = 'active' ORDER BY full_name ASC");
            $staff = $staffStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Students (for case creation)
            $studentsStmt = $db->query("SELECT id, admission_no, CONCAT_WS(' ', first_name, last_name) AS full_name FROM students WHERE status = 'active' ORDER BY full_name ASC");
            $students = $studentsStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'academic_years' => $years,
                'terms' => $terms,
                'classes' => $classes,
                'streams' => $streams,
                'staff' => $staff,
                'students' => $students,
                'welfare_categories' => ['emotional', 'social', 'behavioral', 'family', 'chapel', 'pastoral', 'referral', 'other'],
                'referral_sources' => ['self', 'teacher', 'parent', 'discipline', 'health', 'other'],
                'priorities' => ['low', 'medium', 'high', 'urgent'],
                'statuses' => ['open', 'in_progress', 'resolved', 'closed', 'cancelled'],
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load welfare metadata: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/welfare-cases
     */
    public function getWelfareCases($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access welfare data');

        }

        try {
            $db = $this->db->getConnection();

            $academicYear = !empty($_GET['academic_year']) ? trim($_GET['academic_year']) : null;
            $termId = !empty($_GET['term_id']) ? (int)$_GET['term_id'] : null;
            $classId = !empty($_GET['class_id']) ? (int)$_GET['class_id'] : null;
            $streamId = !empty($_GET['stream_id']) ? (int)$_GET['stream_id'] : null;
            $gender = !empty($_GET['gender']) ? trim($_GET['gender']) : null;
            $welfareCategory = !empty($_GET['welfare_category']) ? trim($_GET['welfare_category']) : null;
            $referralSource = !empty($_GET['referral_source']) ? trim($_GET['referral_source']) : null;
            $priority = !empty($_GET['priority']) ? trim($_GET['priority']) : null;
            $status = !empty($_GET['status']) ? trim($_GET['status']) : null;
            $assignedTo = !empty($_GET['assigned_to']) ? (int)$_GET['assigned_to'] : null;
            $search = !empty($_GET['search']) ? trim($_GET['search']) : '';

            // Build query
            $sql = "
                SELECT
                    swc.id,
                    swc.case_code,
                    swc.student_id,
                    swc.title,
                    swc.welfare_category,
                    swc.referral_source,
                    swc.priority,
                    swc.status,
                    swc.opened_at,
                    swc.next_follow_up_at,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    s.gender,
                    cls.name AS class_name,
                    cs.stream_name,
                    CONCAT_WS(' ', u.first_name, u.last_name) AS assigned_to_name,
                    MAX(swn.created_at) AS last_interaction
                FROM student_welfare_cases swc
                JOIN students s ON s.id = swc.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN users u ON u.id = swc.assigned_to
                LEFT JOIN student_welfare_notes swn ON swn.welfare_case_id = swc.id
                WHERE s.status = 'active'
            ";

            $bindings = [];

            // Apply chaplain/counselor scope filtering - only show cases assigned to them
            if ($userRole === 'chaplain') {
                $sql .= " AND swc.assigned_to = ?";
                $bindings[] = $this->user['id'];
            }

            if ($welfareCategory) {
                $sql .= " AND swc.welfare_category = ?";
                $bindings[] = $welfareCategory;
            }

            if ($referralSource) {
                $sql .= " AND swc.referral_source = ?";
                $bindings[] = $referralSource;
            }

            if ($priority) {
                $sql .= " AND swc.priority = ?";
                $bindings[] = $priority;
            }

            if ($status) {
                $sql .= " AND swc.status = ?";
                $bindings[] = $status;
            }

            if ($assignedTo) {
                $sql .= " AND swc.assigned_to = ?";
                $bindings[] = $assignedTo;
            }

            if ($classId) {
                $sql .= " AND cs.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND s.gender = ?";
                $bindings[] = $gender;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR swc.title LIKE ? OR swc.referral_source LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " GROUP BY swc.id ORDER BY swc.opened_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($bindings);
            $cases = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success($cases);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load welfare cases: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/students/welfare-case/{caseId}
     */
    public function getWelfareCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to access welfare data');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();

            // Get case details
            $caseStmt = $db->prepare("
                SELECT swc.*,
                       CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                       s.admission_no,
                       cls.name AS class_name,
                       cs.stream_name,
                       CONCAT_WS(' ', u.first_name, u.last_name) AS assigned_to_name,
                       CONCAT_WS(' ', ob.first_name, ob.last_name) AS opened_by_name,
                       CONCAT_WS(' ', rb.first_name, rb.last_name) AS resolved_by_name
                FROM student_welfare_cases swc
                JOIN students s ON s.id = swc.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes cls ON cls.id = cs.class_id
                LEFT JOIN users u ON u.id = swc.assigned_to
                LEFT JOIN users ob ON ob.id = swc.opened_by
                LEFT JOIN users rb ON rb.id = swc.resolved_by
                WHERE swc.id = ?
                LIMIT 1
            ");
            $caseStmt->execute([$caseId]);
            $case = $caseStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$case) {
                return $this->notFound('Welfare case not found');
            }

            // Get student basic info
            $studentStmt = $db->prepare("
                SELECT id, admission_no, first_name, last_name, gender
                FROM students WHERE id = ?
            ");
            $studentStmt->execute([$case['student_id']]);
            $student = $studentStmt->fetch(\PDO::FETCH_ASSOC);

            // Get notes
            $notesStmt = $db->prepare("
                SELECT note_type, note, created_at
                FROM student_welfare_notes
                WHERE welfare_case_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $notesStmt->execute([$caseId]);
            $notes = $notesStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            return $this->success([
                'case' => $case,
                'student' => $student,
                'class_name' => $case['class_name'] ?? null,
                'stream_name' => $case['stream_name'] ?? null,
                'assigned_to_name' => $case['assigned_to_name'] ?? null,
                'notes' => $notes,
            ]);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to load welfare case: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/welfare-case
     */
    public function postWelfareCase($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to create welfare cases');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
            $title = !empty($data['title']) ? $data['title'] : '';
            $welfareCategory = !empty($data['welfare_category']) ? $data['welfare_category'] : 'other';
            $referralSource = !empty($data['referral_source']) ? $data['referral_source'] : null;
            $priority = !empty($data['priority']) ? $data['priority'] : 'medium';
            $description = !empty($data['description']) ? $data['description'] : null;
            $assignedTo = !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null;
            $nextFollowUpAt = !empty($data['next_follow_up_at']) ? $data['next_follow_up_at'] : null;

            if (!$studentId || !$title) {
                return $this->badRequest('Student and title are required');
            }

            // Generate case code
            $caseCode = 'WEL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $insertStmt = $db->prepare("
                INSERT INTO student_welfare_cases (student_id, case_code, title, welfare_category, referral_source, priority, status, description, assigned_to, opened_by, opened_at, next_follow_up_at)
                VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, CURRENT_TIMESTAMP, ?)
            ");
            $insertStmt->execute([$studentId, $caseCode, $title, $welfareCategory, $referralSource, $priority, $description, $assignedTo, $userId, $nextFollowUpAt]);

            return $this->success(['message' => 'Welfare case created successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to create welfare case: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/welfare-case/{caseId}/note
     */
    public function postWelfareCaseNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to add welfare notes');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $noteType = !empty($data['note_type']) ? $data['note_type'] : 'general';
            $note = !empty($data['note']) ? $data['note'] : '';
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;

            if (!$note) {
                return $this->badRequest('Note content is required');
            }

            $insertStmt = $db->prepare("
                INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, follow_up_date, recorded_by, recorded_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $insertStmt->execute([$caseId, $noteType, $note, $followUpDate, $userId]);

            // Update case follow-up date if provided
            if ($followUpDate) {
                $updateStmt = $db->prepare("
                    UPDATE student_welfare_cases SET next_follow_up_at = ? WHERE id = ?
                ");
                $updateStmt->execute([$followUpDate, $caseId]);
            }

            return $this->success(['message' => 'Note added successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to add note: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/welfare-case/{caseId}/follow-up
     */
    public function postWelfareCaseFollowUp($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to update follow-up');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();

            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
            $note = !empty($data['note']) ? $data['note'] : null;

            if (!$followUpDate) {
                return $this->badRequest('Follow-up date is required');
            }

            $updateStmt = $db->prepare("
                UPDATE student_welfare_cases SET next_follow_up_at = ? WHERE id = ?
            ");
            $updateStmt->execute([$followUpDate, $caseId]);

            // Optionally add a note about the follow-up
            if ($note) {
                $userId = $this->user['id'];
                $insertStmt = $db->prepare("
                    INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, follow_up_date, recorded_by, recorded_at)
                    VALUES (?, 'follow_up', ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([$caseId, $note, $followUpDate, $userId]);
            }

            return $this->success(['message' => 'Follow-up scheduled successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to schedule follow-up: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/welfare-case/{caseId}/resolve
     */
    public function postWelfareCaseResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to resolve cases');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $resolutionNote = !empty($data['resolution_note']) ? $data['resolution_note'] : null;

            $updateStmt = $db->prepare("
                UPDATE student_welfare_cases
                SET status = 'resolved', resolved_by = ?, resolved_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([$userId, $caseId]);

            // Add resolution note
            if ($resolutionNote) {
                $insertStmt = $db->prepare("
                    INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, recorded_by, recorded_at)
                    VALUES (?, 'resolution', ?, ?, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([$caseId, $resolutionNote, $userId]);
            }

            return $this->success(['message' => 'Case resolved successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to resolve case: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/welfare-case/{caseId}/escalate
     */
    public function postWelfareCaseEscalate($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to escalate cases');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $escalationNote = !empty($data['escalation_note']) ? $data['escalation_note'] : null;
            $escalatedTo = !empty($data['escalated_to']) ? (int)$data['escalated_to'] : null;

            $updateStmt = $db->prepare("
                UPDATE student_welfare_cases
                SET status = 'in_progress', escalated = 1, escalated_to = ?, escalated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([$escalatedTo, $caseId]);

            // Add escalation note
            if ($escalationNote) {
                $insertStmt = $db->prepare("
                    INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, recorded_by, recorded_at)
                    VALUES (?, 'escalation', ?, ?, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([$caseId, $escalationNote, $userId]);
            }

            return $this->success(['message' => 'Case escalated successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to escalate case: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/counseling-case/{caseId}/session-note
     */
    public function postCounselingCaseSessionNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to add session notes');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $sessionDate = !empty($data['session_date']) ? $data['session_date'] : date('Y-m-d');
            $sessionType = !empty($data['session_type']) ? $data['session_type'] : 'individual';
            $sessionNotes = !empty($data['session_notes']) ? $data['session_notes'] : '';
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;

            if (!$sessionNotes) {
                return $this->badRequest('Session notes are required');
            }

            // Insert session note into student_counseling_sessions
            $insertStmt = $db->prepare("
                INSERT INTO student_counseling_sessions (counseling_case_id, session_date, session_type, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ");
            $insertStmt->execute([$caseId, $sessionDate, $sessionType, $sessionNotes, $userId]);

            // Update case follow-up date if provided
            if ($followUpDate) {
                $updateStmt = $db->prepare("
                    UPDATE student_counseling_cases SET next_follow_up_at = ? WHERE id = ?
                ");
                $updateStmt->execute([$followUpDate, $caseId]);
            }

            return $this->success(['message' => 'Session note added successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to add session note: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/counseling-case/{caseId}/follow-up
     */
    public function postCounselingCaseFollowUp($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to update follow-up');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();

            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
            $note = !empty($data['note']) ? $data['note'] : null;

            if (!$followUpDate) {
                return $this->badRequest('Follow-up date is required');
            }

            $updateStmt = $db->prepare("
                UPDATE student_counseling_cases SET next_follow_up_at = ? WHERE id = ?
            ");
            $updateStmt->execute([$followUpDate, $caseId]);

            // Optionally add a session note about the follow-up
            if ($note) {
                $userId = $this->user['id'];
                $insertStmt = $db->prepare("
                    INSERT INTO student_counseling_sessions (counseling_case_id, session_date, session_type, notes, created_by, created_at)
                    VALUES (?, ?, 'follow_up', ?, ?, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([$caseId, date('Y-m-d'), $note, $userId]);
            }

            return $this->success(['message' => 'Follow-up scheduled successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to schedule follow-up: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/counseling-case/{caseId}/close
     */
    public function postCounselingCaseClose($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check chaplain/counselor permissions
        $allowedRoles = ['chaplain', 'admin', 'school_administrator'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to close cases');

        }

        $caseId = $id !== null ? (int)$id : null;
        if ($caseId === null) {
            return $this->badRequest('Case ID is required');
        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $closureNote = !empty($data['closure_note']) ? $data['closure_note'] : null;

            $updateStmt = $db->prepare("
                UPDATE student_counseling_cases
                SET status = 'closed', closed_by = ?, closed_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $updateStmt->execute([$userId, $caseId]);

            // Add closure note as a session
            if ($closureNote) {
                $insertStmt = $db->prepare("
                    INSERT INTO student_counseling_sessions (counseling_case_id, session_date, session_type, notes, created_by, created_at)
                    VALUES (?, ?, 'closure', ?, ?, CURRENT_TIMESTAMP)
                ");
                $insertStmt->execute([$caseId, date('Y-m-d'), $closureNote, $userId]);
            }

            return $this->success(['message' => 'Case closed successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to close case: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/students/boarding-note
     */
    public function postBoardingNote($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        // Check boarding permissions
        $allowedRoles = ['boarding_master', 'boarding_matron', 'admin', 'school_administrator', 'headteacher', 'director'];

        if (!$this->userHasAnyRole($allowedRoles)) {

            return $this->forbidden('You do not have permission to add boarding notes');

        }

        try {
            $db = $this->db->getConnection();
            $userId = $this->user['id'];

            $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : null;
            $noteType = !empty($data['note_type']) ? $data['note_type'] : 'general';
            $note = !empty($data['note']) ? $data['note'] : '';
            $visibility = !empty($data['visibility']) ? $data['visibility'] : 'boarding';
            $priority = !empty($data['priority']) ? $data['priority'] : 'medium';

            if (!$studentId || !$note) {
                return $this->badRequest('Student ID and note content are required');
            }

            $insertStmt = $db->prepare("
                INSERT INTO student_boarding_notes (student_id, note_type, note, visibility, priority, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$studentId, $noteType, $note, $visibility, $priority, $userId]);

            return $this->success(['message' => 'Boarding note added successfully']);
        } catch (\Exception $e) {
            return $this->badRequest('Failed to add boarding note: ' . $e->getMessage());
        }
    }
}
