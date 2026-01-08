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
}
