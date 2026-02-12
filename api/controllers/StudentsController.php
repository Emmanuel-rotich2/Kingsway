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

    public function __construct()
    {
        parent::__construct();
        $this->mediaManager = new MediaManager($this->db->getConnection());
        $this->api = new StudentsAPI();
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
        if (!empty($segments)) {
            return $this->routeNestedPost(array_shift($segments), $id, $data, $segments);
        }

        return $this->handleResponse($this->api->create($data));
    }

    public function putStudent($id = null, $data = [], $segments = [])
    {
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
        $result = $this->api->bulkDelete($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/bulk-promote
     */
    public function postBulkPromote($id = null, $data = [], $segments = [])
    {
        $result = $this->api->bulkPromoteStudents($data);
        return $this->handleResponse($result);
    }

    /* =====================================================
     * MEDIA
     * ===================================================== */

    public function postMediaUpload($id = null, $data = [], $segments = [])
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

    /**
     * GET /api/students/enrollment-history/{id}
     */
    public function getEnrollmentHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->getEnrollmentHistory($studentId);
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
        return $this->handleResponse(
            (new FamilyGroupsManager())->getParents($data)
        );
    }

    /**
     * GET /api/students/parents/children
     */
    public function getParentsChildren($id = null, $data = [], $segments = [])
    {
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
        $result = $this->api->addParentToStudent($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/students/parents/create
     */
    public function postParentsCreate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse(
            (new FamilyGroupsManager())->createParent($data)
        );
    }

    /**
     * POST /api/students/parents/update
     */
    public function postParentsUpdate($id = null, $data = [], $segments = [])
    {
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

    /**
     * POST /api/students/parents/delete
     */
    public function postParentsDelete($id = null, $data = [], $segments = [])
    {
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
        return $this->handleResponse($this->api->getStudentStatistics($data));
    }

    // ========================================
    // SECTION 10: Discipline Management
    // ========================================

    /**
     * GET /api/students/discipline-get
     */
    public function getDisciplineGet($id = null, $data = [], $segments = [])
    {
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
        if (!$id) {
            return $this->badRequest('Parent ID required');
        }

        return $this->handleResponse(
            (new FamilyGroupsManager())->getParentDetails((int) $id)
        );
    }

    public function putFamilyParentUpdate($id = null, $data = [])
    {
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

    private function handleResponse($result)
    {
        if (is_array($result) && isset($result['success'])) {
            return $result['success']
                ? $this->success($result['data'] ?? null, $result['message'] ?? 'Success')
                : $this->badRequest($result['message'] ?? 'Operation failed');
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
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }
}
