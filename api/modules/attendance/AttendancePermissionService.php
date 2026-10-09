<?php

namespace App\API\Modules\attendance;

use App\API\Controllers\BaseController;
use App\API\Services\ReadReplicaService;

class AttendancePermissionService
{
    private AttendanceAPI $api;

    public function __construct(AttendanceAPI $api)
    {
        $this->api = $api;
    }

    public function getPermissionTypes($id, $data, $segments, BaseController $controller)
    {
        if ($denied = $controller->guardStaffAttendance(
            'attendance_boarding_view',
            ['system administrator', 'school administrator', 'headteacher', 'director', 'boarding master']
        )) return $denied;

        try {
            $sql = "SELECT * FROM student_permission_types WHERE status = 'active' ORDER BY name";
            $result = $controller->getDb()->query($sql);
            $types = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $controller->success($types, 'Permission types retrieved');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendancePermissionService
    public function getPermissions($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('attendance_boarding_view', ['system administrator', 'school administrator', 'headteacher', 'director', 'boarding master'])) return $denied;
        try {
            $studentId = $data['student_id'] ?? $_GET['student_id'] ?? null;
            $status = $data['status'] ?? $_GET['status'] ?? null;
            $active = $data['active'] ?? $_GET['active'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $search = trim((string) ($data['search'] ?? $_GET['search'] ?? ''));
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? null;
            $permissionTypeId = $data['permission_type_id'] ?? $_GET['permission_type_id'] ?? null;
            $scope = $controller->getAccessibleClassScope();
            $streamScope = $controller->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) { return $controller->forbidden('You are not allowed to access permissions for this class'); }
            if ($streamScope['empty']) { return $controller->success([], 'Permissions retrieved'); }
            $lp = ReadReplicaService::qualifiedRef('learner_placement');
            $sql = "SELECT sp.*, CONCAT(p.first_name, ' ', p.last_name) as student_name, s.admission_no, lp.class_name as class_name, lp.stream_name AS stream_name, st.name as student_type, st.code as student_type_code, spt.name as permission_type_name, spt.code as permission_type_code, spt.applies_to, COALESCE(CONCAT(approver_p.first_name, ' ', approver_p.last_name), approver_user.username) as approved_by_name FROM " . ReadReplicaService::qualifiedRef("student_permissions") . " sp JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON sp.student_id = s.id LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id LEFT JOIN {$lp} lp ON lp.student_id = s.id LEFT JOIN student_types st ON st.id = s.student_type_id JOIN student_permission_types spt ON sp.permission_type_id = spt.id LEFT JOIN users approver_user ON sp.approved_by = approver_user.id LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " approver_staff ON approver_staff.id IN (SELECT staff.id FROM " . ReadReplicaService::qualifiedRef("staff") . " WHERE staff.person_id IN (SELECT persons.id FROM " . ReadReplicaService::qualifiedRef("persons") . " WHERE persons.id = (SELECT u.person_id FROM users u WHERE u.id = approver_user.id))) LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " approver_p ON approver_p.id = approver_staff.person_id WHERE 1=1 {$streamScope['sql']}";
            $params = $streamScope['params'];
            if ($studentId) { $sql .= " AND sp.student_id = ?"; $params[] = $studentId; }
            if ($status) { $sql .= " AND sp.status = ?"; $params[] = $status; }
            if ($active === 'true' || $active === '1') { $sql .= " AND CURDATE() BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'"; }
            if ($permissionTypeId) { $sql .= " AND sp.permission_type_id = ?"; $params[] = (int) $permissionTypeId; }
            if ($dateFrom && $dateTo) { if ($dateFrom > $dateTo) { [$dateFrom, $dateTo] = [$dateTo, $dateFrom]; } $sql .= " AND sp.end_date >= ? AND sp.start_date <= ?"; $params[] = $dateFrom; $params[] = $dateTo; }
            elseif ($dateFrom) { $sql .= " AND sp.end_date >= ?"; $params[] = $dateFrom; }
            elseif ($dateTo) { $sql .= " AND sp.start_date <= ?"; $params[] = $dateTo; }
            if ($search !== '') { $sql .= " AND (CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ? OR s.admission_no LIKE ? OR sp.reason LIKE ? OR spt.name LIKE ?)"; $searchTerm = '%' . $search . '%'; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; $params[] = $searchTerm; }
            $sql .= " ORDER BY sp.created_at DESC LIMIT 250";
            $permissions = $controller->getDb()->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            return $controller->success($permissions, 'Permissions retrieved');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendancePermissionService
    public function postPermissions($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('attendance_boarding_create', ['system administrator', 'school administrator', 'headteacher', 'boarding master'])) return $denied;
        try {
            $studentId = isset($data['student_id']) ? (int) $data['student_id'] : null;
            $permissionTypeId = isset($data['permission_type_id']) ? (int) $data['permission_type_id'] : null;
            $startDate = $data['start_date'] ?? null;
            $startTime = $data['start_time'] ?? null;
            $endDate = $data['end_date'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $reason = trim((string) ($data['reason'] ?? ''));
            $parentId = $data['parent_id'] ?? null;
            $requestedByParent = $data['requested_by_parent'] ?? false;
            $expectedReturn = $data['expected_return'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($expectedReturn) { $expectedReturn = str_replace('T', ' ', (string) $expectedReturn); }
            if (!$studentId || !$permissionTypeId || !$startDate || !$endDate || $reason === '') { return $controller->badRequest('Missing required fields'); }
            if ($startDate > $endDate) { [$startDate, $endDate] = [$endDate, $startDate]; }
            $permissionType = $controller->getDb()->query("SELECT id, code, name, max_days, applies_to, status FROM student_permission_types WHERE id = ? AND status = 'active' LIMIT 1", [$permissionTypeId])->fetch(\PDO::FETCH_ASSOC);
            if (!$permissionType) { return $controller->badRequest('Invalid permission type'); }
            $student = $controller->getDb()->query("SELECT s.id, st.code AS student_type_code, st.name AS student_type FROM " . ReadReplicaService::qualifiedRef("student_directory") . "  WHERE s.id = ? LIMIT 1", [$studentId])->fetch(\PDO::FETCH_ASSOC);
            if (!$student) { return $controller->badRequest('Invalid student'); }
            $studentTypeCode = strtoupper((string) ($student['student_type_code'] ?? ''));
            $isBoarder = strpos($studentTypeCode, 'BOARD') !== false;
            if (($permissionType['applies_to'] ?? 'all') === 'boarders_only' && !$isBoarder) { return $controller->badRequest('This permission type is only available for boarders'); }
            if (($permissionType['applies_to'] ?? 'all') === 'day_only' && $isBoarder) { return $controller->badRequest('This permission type is only available for day scholars'); }
            if (!empty($permissionType['max_days'])) { $daysRequested = (new \DateTime($startDate))->diff(new \DateTime($endDate))->days + 1; if ($daysRequested > (int) $permissionType['max_days']) { return $controller->badRequest('Request exceeds the maximum allowed duration for this permission type'); } }
            $controller->getDb()->query("INSERT INTO student_permissions (student_id, permission_type_id, start_date, start_time, end_date, end_time, reason, parent_id, requested_by_parent, expected_return, notes, status, requested_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())", [$studentId, $permissionTypeId, $startDate, $startTime ?: null, $endDate, $endTime ?: null, $reason, $parentId ?: null, filter_var($requestedByParent, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, $expectedReturn ?: null, $notes ?: null]);
            $permissionId = $controller->getDb()->getConnection()->lastInsertId();
            return $controller->success(['id' => $permissionId], 'Permission request created');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendancePermissionService
    public function putPermissions($id, $data, $segments, BaseController $controller) {
        $requestedStatus = $data['status'] ?? null;
        $approvalDecision = in_array($requestedStatus, ['approved', 'rejected'], true);
        $permission = $approvalDecision ? 'attendance_boarding_approve' : 'attendance_boarding_edit';
        $fallbackRoles = $approvalDecision ? ['system administrator', 'school administrator', 'headteacher', 'director'] : ['system administrator', 'school administrator', 'headteacher', 'boarding master'];
        if ($denied = $controller->guardStaffAttendance($permission, $fallbackRoles)) { return $denied; }
        try {
            if (!$id) { return $controller->badRequest('Permission ID is required'); }
            $existing = $controller->getDb()->query("SELECT * FROM student_permissions WHERE id = ? LIMIT 1", [$id])->fetch(\PDO::FETCH_ASSOC);
            if (!$existing) { return $controller->notFound('Permission request not found'); }
            $status = $data['status'] ?? null;
            $approvedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $rejectionReason = trim((string) ($data['rejection_reason'] ?? $data['comments'] ?? ''));
            $editableFields = ['permission_type_id', 'start_date', 'start_time', 'end_date', 'end_time', 'reason', 'parent_id', 'requested_by_parent', 'expected_return', 'notes'];
            $hasEditPayload = false;
            foreach ($editableFields as $field) { if (array_key_exists($field, $data)) { $hasEditPayload = true; break; } }
            if ($hasEditPayload && !$status) {
                if (($existing['status'] ?? 'pending') !== 'pending') { return $controller->badRequest('Only pending requests can be edited'); }
                $updates = []; $params = [];
                foreach ($editableFields as $field) {
                    if (!array_key_exists($field, $data)) continue;
                    $updates[] = "{$field} = ?";
                    if ($field === 'requested_by_parent') { $params[] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
                    elseif ($field === 'expected_return' && !empty($data[$field])) { $params[] = str_replace('T', ' ', (string) $data[$field]); }
                    else { $params[] = $data[$field] === '' ? null : $data[$field]; }
                }
                if (empty($updates)) { return $controller->badRequest('No editable fields supplied'); }
                $params[] = $id;
                $controller->getDb()->query("UPDATE student_permissions SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?", $params);
                return $controller->success(['id' => $id], 'Permission request updated');
            }
            if (!in_array($status, ['approved', 'rejected', 'cancelled', 'completed'], true)) { return $controller->badRequest('Invalid status'); }
            $sql = "UPDATE student_permissions SET status = ?, updated_at = NOW()"; $params = [$status];
            if (in_array($status, ['approved', 'rejected'], true)) { $sql .= ", approved_by = ?, approved_at = NOW()"; $params[] = $approvedBy; }
            if ($status === 'rejected') { $sql .= ", rejection_reason = ?"; $params[] = $rejectionReason !== '' ? $rejectionReason : null; }
            if (!empty($data['notes'])) { $sql .= ", notes = ?"; $params[] = $data['notes']; }
            $sql .= " WHERE id = ?"; $params[] = $id;
            $controller->getDb()->query($sql, $params);
            return $controller->success(['id' => $id, 'status' => $status], 'Permission updated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }
}
