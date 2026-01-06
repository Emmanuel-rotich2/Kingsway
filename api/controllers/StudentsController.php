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
        $studentId = $data['student_id'] ?? $id;
        $file = $_FILES['file'] ?? null;

        if (!$studentId || !$file) {
            return $this->badRequest('Student ID and file are required');
        }

        return $this->success(
            $this->mediaManager->upload($file, 'students', $studentId),
            'Media uploaded'
        );
    }

    public function getMedia($id = null)
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
