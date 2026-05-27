<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\students\StudentsAPI;
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
        $this->mediaManager = new MediaManager($this->db->getConnection());
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
     * POST /api/students/id-card-generate
     */
    public function postIdCardGenerate($id = null, $data = [], $segments = [])
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
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }
}
