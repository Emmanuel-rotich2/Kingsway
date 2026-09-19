<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Controllers\BaseController;

class StudentParentService
{
    private const PARENT_ACCESS_PERMS = ['students_parents_view', 'students_parents_view_all', 'students_parents_view_own', 'students_view', 'students_view_all', 'students_view_own', 'students_edit', 'students_create', 'admission_view', 'finance_view'];
    private const STUDENT_EDIT_PERMS = ['students_edit'];
    private const STUDENT_CREATE_PERMS = ['students_create'];

    private StudentsAPI $api;
    private FamilyGroupsManager $familyGroupsManager;

    public function __construct(StudentsAPI $api, FamilyGroupsManager $familyGroupsManager)
    {
        $this->api = $api;
        $this->familyGroupsManager = $familyGroupsManager;
    }

    public function getParentsGet($id, $data, $segments, BaseController $controller)
    {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) {
            return $auth;
        }

        $parentId = $data['parent_id'] ?? null;
        if ($parentId !== null) {
            return $controller->handleResponse($this->familyGroupsManager->getParentDetails((int) $parentId));
        }

        $studentId = $id ?? $data['student_id'] ?? null;
        if ($studentId === null) {
            return $controller->badRequest('Student ID is required');
        }

        $result = $this->api->getStudentParentsInfo($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function getParentsList($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) { return $auth; }
        return $controller->handleResponse($this->familyGroupsManager->getParents($data));
    }

    // TODO: Delegate to StudentParentService
    public function getParentsChildren($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) { return $auth; }
        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        $result = $this->familyGroupsManager->getParentDetails((int) $parentId);
        if (is_array($result) && ($result['success'] ?? false)) { $result['data'] = $result['data']['children'] ?? []; }
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function postParentsAdd($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to link parent records')) { return $auth; }
        $result = $this->api->addParentToStudent($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function postParentsCreate($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(array_merge(self::STUDENT_EDIT_PERMS, self::STUDENT_CREATE_PERMS), 'Insufficient permission to create parent records')) { return $auth; }
        return $controller->handleResponse($this->familyGroupsManager->createParent($data));
    }

    // TODO: Delegate to StudentParentService
    public function postParentsUpdate($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) { return $auth; }
        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        return $controller->handleResponse($this->familyGroupsManager->updateParent((int) $parentId, $data));
    }

    // TODO: Delegate to StudentParentService
    public function putParentsUpdate($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) { return $auth; }
        $parentId = $id ?? $data['parent_id'] ?? null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        $result = $this->api->updateParentInfo($parentId, $data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function postParentsRemove($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to unlink parent records')) { return $auth; }
        $result = $this->api->removeParentFromStudent($data);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function postParentsDelete($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to delete parent records')) { return $auth; }
        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        return $controller->handleResponse($this->familyGroupsManager->deleteParent((int) $parentId));
    }

    // TODO: Delegate to StudentParentService
    public function postParentsLinkChild($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to link parent/child records')) { return $auth; }
        $parentId = $data['parent_id'] ?? null;
        $studentId = $data['student_id'] ?? null;
        if (!$parentId || !$studentId) { return $controller->badRequest('Parent ID and Student ID are required'); }
        $linkData = $data;
        unset($linkData['parent_id'], $linkData['student_id']);
        return $controller->handleResponse($this->familyGroupsManager->linkParentToStudent((int) $parentId, (int) $studentId, $linkData));
    }

    // TODO: Delegate to StudentParentService
    public function postParentsUnlinkChild($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to unlink parent/child records')) { return $auth; }
        $parentId = $data['parent_id'] ?? null;
        $studentId = $data['student_id'] ?? null;
        if (!$parentId || !$studentId) { return $controller->badRequest('Parent ID and Student ID are required'); }
        return $controller->handleResponse($this->familyGroupsManager->unlinkParentFromStudent((int) $parentId, (int) $studentId));
    }

    // TODO: Delegate to StudentParentService
    public function getParentsAvailableStudents($id, $data, $segments, BaseController $controller) {
        $parentId = $data['parent_id'] ?? $id ?? null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        return $controller->handleResponse($this->familyGroupsManager->getAvailableStudentsForParent((int) $parentId));
    }

    // TODO: Delegate to StudentParentService
    public function getFamilyParentGet($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view parent records')) { return $auth; }
        if (!$id) { return $controller->badRequest('Parent ID required'); }
        return $controller->handleResponse($this->familyGroupsManager->getParentDetails((int) $id));
    }

    // TODO: Delegate to StudentParentService
    public function putFamilyParentUpdate($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::STUDENT_EDIT_PERMS, 'Insufficient permission to update parent records')) { return $auth; }
        if (!$id) { return $controller->badRequest('Parent ID required'); }
        return $controller->handleResponse($this->familyGroupsManager->updateParent((int) $id, $data));
    }

    // TODO: Delegate to StudentParentService
    public function getFamilyGroupsMetaV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroupsMeta());
    }

    // TODO: Delegate to StudentParentService
    public function getFamilyGroupsV2($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroups(array_merge($_GET, $data)));
    }

    // TODO: Delegate to StudentParentService
    public function getFamilyGroupsStats($id, $data, $segments, BaseController $controller) {
        if ($auth = $controller->authorizeStudents(self::PARENT_ACCESS_PERMS, 'Insufficient permission to view family group statistics')) {
            return $auth;
        }
        return $controller->handleResponse($this->familyGroupsManager->getFamilyGroupStats());
    }

    // TODO: Delegate to StudentParentService
    public function getFamilyGroup($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        $result = $this->familyGroupsManager->getParentDetails($parentId);
        if (is_array($result) && ($result['success'] ?? false)) {
            $result['data'] = ['parent' => $result['data']['parent'] ?? null, 'students' => $result['data']['children'] ?? []];
        }
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to StudentParentService
    public function postFamilyGroupLinkStudent($id, $data, $segments, BaseController $controller) {
        if (!$controller->getUser()) { return $controller->unauthorized('Authentication required'); }
        $parentId = $id !== null ? (int)$id : null;
        if ($parentId === null) { return $controller->badRequest('Parent ID is required'); }
        return $controller->handleResponse($this->familyGroupsManager->linkStudentToFamilyGroup($parentId, $data));
    }
}
