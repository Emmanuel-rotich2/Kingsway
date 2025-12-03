<?php
namespace App\API\Controllers;

use App\API\Modules\students\StudentsAPI;
use Exception;

/**
 * StudentsController - REST endpoints for all student operations
 * Handles student CRUD, workflows, medical records, discipline, documents, parents, promotions
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
use App\API\Modules\system\MediaManager;


class StudentsController extends BaseController
{
    private $mediaManager;
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->mediaManager = new MediaManager($this->db);
        $this->api = new StudentsAPI();
    }
    public function index()
    {
        return $this->success(['message' => 'Students API is running']);
    }

    // --- Media Operations ---
    // Upload student document or photo
    public function postMediaUpload($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        $file = $_FILES['file'] ?? null;
        $uploaderId = $data['uploader_id'] ?? ($_REQUEST['user']['id'] ?? null);
        $description = $data['description'] ?? '';
        $tags = $data['tags'] ?? '';
        if ($studentId === null || !$file) {
            return $this->badRequest('Student ID and file are required');
        }
        $result = $this->mediaManager->upload($file, 'students', $studentId, null, $uploaderId, $description, $tags);
        return $this->success($result, 'Media uploaded');
    }

    // List student media
    public function getMedia($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        $filters = ['context' => 'students', 'entity_id' => $studentId];
        $result = $this->mediaManager->listMedia($filters);
        return $this->success($result, 'Media list');
    }

    // Delete student media
    public function postMediaDelete($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id ?? null;
        if ($mediaId === null) {
            return $this->badRequest('Media ID is required');
        }
        $result = $this->mediaManager->deleteMedia($mediaId);
        return $this->success($result, 'Media deleted');
    }


    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/students - List all students
     * GET /api/students/{id} - Get single student
     */
        public function getStudent($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students - Create new student
     */
    public function postStudent($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/students/{id} - Update student
     */
    public function putStudent($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Student ID is required for update');
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/students/{id} - Delete student
     */
    public function deleteStudent($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Student ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Student Information
    // ========================================

    /**
     * GET /api/students/profile/get/{id}
     */
    public function getProfileGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getProfile($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/attendance/get/{id}
     */
    public function getAttendanceGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getAttendance($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/performance/get/{id}
     */
    public function getPerformanceGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getPerformance($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/fees/get/{id}
     */
    public function getFeesGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getFees($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/qr-info/get/{id}
     */
    public function getQrInfoGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getQRInfo($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/statistics/get
     */
    public function getStatisticsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStudentStatistics($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Bulk Operations
    // ========================================

    /**
     * POST /api/students/bulk/create
     */
    public function postBulkCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->bulkCreate($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk/update
     */
    public function postBulkUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->bulkUpdate($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk/delete
     */
    public function postBulkDelete($id = null, $data = [], $segments = [])
    {
        $result = $this->api->bulkDelete($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk/promote
     */
    public function postBulkPromote($id = null, $data = [], $segments = [])
    {
        $result = $this->api->bulkPromoteStudents($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: QR Code & ID Card Operations
    // ========================================

    /**
     * POST /api/students/qr-code/generate
     */
    public function postQrCodeGenerate($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->generateQRCode($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/qr-code/generate-enhanced
     */
    public function postQrCodeGenerateEnhanced($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->generateQRCodeEnhanced($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/generate
     */
    public function postIdCardGenerate($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->generateStudentIDCard($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/id-card/generate-class
     */
    public function postIdCardGenerateClass($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? null;
        $streamId = $data['stream_id'] ?? null;
        
        if ($classId === null) {
            return $this->badRequest('Class ID is required');
        }
        
        $result = $this->api->generateClassIDCards($classId, $streamId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/photo/upload
     */
    public function postPhotoUpload($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        $fileData = $data['file_data'] ?? $_FILES ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->uploadPhoto($studentId, $fileData);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Admission Workflow
    // ========================================

    /**
     * POST /api/students/admission/start-workflow
     */
    public function postAdmissionStartWorkflow($id = null, $data = [], $segments = [])
    {
        $result = $this->api->startAdmissionWorkflow($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/admission/verify-documents
     */
    public function postAdmissionVerifyDocuments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyAdmissionDocuments($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/admission/conduct-interview
     */
    public function postAdmissionConductInterview($id = null, $data = [], $segments = [])
    {
        $result = $this->api->conductAdmissionInterview($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/admission/approve
     */
    public function postAdmissionApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->approveAdmission($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/admission/complete-registration
     */
    public function postAdmissionCompleteRegistration($id = null, $data = [], $segments = [])
    {
        $result = $this->api->completeRegistration($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/admission/workflow-status
     */
    public function getAdmissionWorkflowStatus($id = null, $data = [], $segments = [])
    {
        $applicationId = $data['application_id'] ?? $id ?? null;
        
        if ($applicationId === null) {
            return $this->badRequest('Application ID is required');
        }
        
        $result = $this->api->getAdmissionWorkflowStatus($applicationId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Transfer Workflow
    // ========================================

    /**
     * POST /api/students/transfer/start-workflow
     */
    public function postTransferStartWorkflow($id = null, $data = [], $segments = [])
    {
        $result = $this->api->startTransferWorkflow($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/transfer/verify-eligibility
     */
    public function postTransferVerifyEligibility($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyTransferEligibility($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/transfer/approve
     */
    public function postTransferApprove($id = null, $data = [], $segments = [])
    {
        $result = $this->api->approveTransfer($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/transfer/execute
     */
    public function postTransferExecute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->executeTransfer($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/transfer/workflow-status
     */
    public function getTransferWorkflowStatus($id = null, $data = [], $segments = [])
    {
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
        $result = $this->api->promoteSingleStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/multiple
     */
    public function postPromotionMultiple($id = null, $data = [], $segments = [])
    {
        $result = $this->api->promoteMultipleStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/entire-class
     */
    public function postPromotionEntireClass($id = null, $data = [], $segments = [])
    {
        $result = $this->api->promoteEntireClass($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/multiple-classes
     */
    public function postPromotionMultipleClasses($id = null, $data = [], $segments = [])
    {
        $result = $this->api->promoteMultipleClasses($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/promotion/graduate-grade9
     */
    public function postPromotionGraduateGrade9($id = null, $data = [], $segments = [])
    {
        $result = $this->api->graduateGrade9Students($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotion/batches
     */
    public function getPromotionBatches($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPromotionBatches($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/promotion/history/{id}
     */
    public function getPromotionHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getPromotionHistory($studentId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Parent/Guardian Management
    // ========================================

    /**
     * GET /api/students/parents/get/{id}
     */
    public function getParentsGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentParentsInfo($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/add
     */
    public function postParentsAdd($id = null, $data = [], $segments = [])
    {
        $result = $this->api->addParentToStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/students/parents/update/{id}
     */
    public function putParentsUpdate($id = null, $data = [], $segments = [])
    {
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
        $result = $this->api->removeParentFromStudent($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Medical Records
    // ========================================

    /**
     * GET /api/students/medical/get/{id}
     */
    public function getMedicalGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getMedicalRecords($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/medical/add
     */
    public function postMedicalAdd($id = null, $data = [], $segments = [])
    {
        $result = $this->api->addMedicalRecord($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/students/medical/update/{id}
     */
    public function putMedicalUpdate($id = null, $data = [], $segments = [])
    {
        $recordId = $id ?? $data['record_id'] ?? null;
        
        if ($recordId === null) {
            return $this->badRequest('Medical record ID is required');
        }
        
        $result = $this->api->updateMedicalRecord($recordId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 10: Discipline Records
    // ========================================

    /**
     * GET /api/students/discipline/get/{id}
     */
    public function getDisciplineGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getDisciplineRecordsInfo($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/discipline/record
     */
    public function postDisciplineRecord($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->recordDisciplineCase($studentId, $data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/students/discipline/update/{id}
     */
    public function putDisciplineUpdate($id = null, $data = [], $segments = [])
    {
        $caseId = $id ?? $data['case_id'] ?? null;
        
        if ($caseId === null) {
            return $this->badRequest('Discipline case ID is required');
        }
        
        $result = $this->api->updateDisciplineCase($caseId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/discipline/resolve
     */
    public function postDisciplineResolve($id = null, $data = [], $segments = [])
    {
        $caseId = $data['case_id'] ?? $id ?? null;
        
        if ($caseId === null) {
            return $this->badRequest('Discipline case ID is required');
        }
        
        $result = $this->api->resolveDisciplineCase($caseId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 11: Document Management
    // ========================================

    /**
     * GET /api/students/documents/get/{id}
     */
    public function getDocumentsGet($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentDocuments($studentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/documents/upload
     */
    public function postDocumentsUpload($id = null, $data = [], $segments = [])
    {
        $result = $this->api->uploadStudentDocument($data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/students/documents/delete/{id}
     */
    public function deleteDocumentsDelete($id = null, $data = [], $segments = [])
    {
        $documentId = $id ?? $data['document_id'] ?? null;
        
        if ($documentId === null) {
            return $this->badRequest('Document ID is required');
        }
        
        $result = $this->api->deleteStudentDocument($documentId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 12: Class & Stream Queries
    // ========================================

    /**
     * GET /api/students/by-class/get
     */
    public function getByClassGet($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? $id ?? null;
        
        if ($classId === null) {
            return $this->badRequest('Class ID is required');
        }
        
        $result = $this->api->getStudentsByClass($classId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/by-stream/get
     */
    public function getByStreamGet($id = null, $data = [], $segments = [])
    {
        $streamId = $data['stream_id'] ?? $id ?? null;
        
        if ($streamId === null) {
            return $this->badRequest('Stream ID is required');
        }
        
        $result = $this->api->getStudentsByStream($streamId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/roster/get
     */
    public function getRosterGet($id = null, $data = [], $segments = [])
    {
        $classId = $data['class_id'] ?? null;
        $streamId = $data['stream_id'] ?? null;
        $yearId = $data['year_id'] ?? null;
        
        if ($classId === null || $streamId === null) {
            return $this->badRequest('Class ID and Stream ID are required');
        }
        
        $result = $this->api->getClassRoster($classId, $streamId, $yearId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 13: Attendance Operations
    // ========================================

    /**
     * POST /api/students/attendance/mark
     */
    public function postAttendanceMark($id = null, $data = [], $segments = [])
    {
        $result = $this->api->markAttendanceForStudent($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 14: Import/Export Operations
    // ========================================

    /**
     * POST /api/students/import/existing
     */
    public function postImportExisting($id = null, $data = [], $segments = [])
    {
        $result = $this->api->importExistingStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/import/add-existing
     */
    public function postImportAddExisting($id = null, $data = [], $segments = [])
    {
        $result = $this->api->addExistingStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/import/add-multiple
     */
    public function postImportAddMultiple($id = null, $data = [], $segments = [])
    {
        $result = $this->api->addMultipleExistingStudents($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/import/template
     */
    public function getImportTemplate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getImportTemplate();
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 15: Academic Year Operations
    // ========================================

    /**
     * GET /api/students/academic-year/current
     */
    public function getAcademicYearCurrent($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurrentAcademicYear();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/academic-year/get/{id}
     */
    public function getAcademicYearGet($id = null, $data = [], $segments = [])
    {
        $yearId = $id ?? $data['year_id'] ?? null;
        
        if ($yearId === null) {
            return $this->badRequest('Academic year ID is required');
        }
        
        $result = $this->api->getAcademicYear($yearId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/academic-year/all
     */
    public function getAcademicYearAll($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllAcademicYears($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/academic-year/create
     */
    public function postAcademicYearCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAcademicYear($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/academic-year/create-next
     */
    public function postAcademicYearCreateNext($id = null, $data = [], $segments = [])
    {
        $userId = $this->getCurrentUserId();
        $result = $this->api->createNextAcademicYear($userId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/academic-year/set-current
     */
    public function postAcademicYearSetCurrent($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? $id ?? null;
        
        if ($yearId === null) {
            return $this->badRequest('Academic year ID is required');
        }
        
        $result = $this->api->setCurrentAcademicYear($yearId);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/students/academic-year/update-status
     */
    public function putAcademicYearUpdateStatus($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? $id ?? null;
        $status = $data['status'] ?? null;
        
        if ($yearId === null || $status === null) {
            return $this->badRequest('Academic year ID and status are required');
        }
        
        $result = $this->api->updateAcademicYearStatus($yearId, $status);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/academic-year/archive
     */
    public function postAcademicYearArchive($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? $id ?? null;
        $userId = $this->getCurrentUserId();
        $notes = $data['notes'] ?? null;
        
        if ($yearId === null) {
            return $this->badRequest('Academic year ID is required');
        }
        
        $result = $this->api->archiveAcademicYear($yearId, $userId, $notes);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/academic-year/terms
     */
    public function getAcademicYearTerms($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? $id ?? null;
        
        if ($yearId === null) {
            return $this->badRequest('Academic year ID is required');
        }
        
        $result = $this->api->getTermsForYear($yearId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/academic-year/current-term
     */
    public function getAcademicYearCurrentTerm($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurrentTerm();
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 16: Alumni & Enrollment
    // ========================================

    /**
     * GET /api/students/alumni/get
     */
    public function getAlumniGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAlumni($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/students/enrollment/current
     */
    public function getEnrollmentCurrent($id = null, $data = [], $segments = [])
    {
        $yearId = $data['year_id'] ?? null;
        $result = $this->api->getCurrentEnrollments($yearId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 17: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
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
     * Route nested GET requests to appropriate methods
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
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
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
     * Route nested DELETE requests to appropriate methods
     */
    private function routeNestedDelete($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'delete' . ucfirst($this->toCamelCase($resource));
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
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }
        
        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
