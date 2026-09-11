<?php

namespace App\API\Modules\attendance;

use App\API\Controllers\BaseController;

class AttendanceStaffService
{
    private AttendanceAPI $api;

    public function __construct(AttendanceAPI $api)
    {
        $this->api = $api;
    }

    public function getStaffHistory($id, $data, $segments, BaseController $controller)
    {
        if (!$controller->guardStaffAttendance('staff.attendance.view', ['system administrator', 'school administrator', 'headteacher', 'director'])) {
            $staffId = $id;
            $scope = $controller->getAccessibleStaffScope();
            if (!$controller->isStaffInScope($staffId ? (int) $staffId : null, $scope)) {
                return $controller->forbidden('You are not allowed to access this staff attendance history');
            }
            $result = $this->api->getStaffAttendanceHistory($staffId);
            return $controller->handleResponse($result);
        }
        // Re-check: guardStaffAttendance returns null on success, response array on failure
        $requested = (int)($id ?? $data['staff_id'] ?? $_GET['staff_id'] ?? 0);
        if (!$requested) $requested = (int)(0);
        try {
            $staffId = $id;
            if (!$controller->isStaffInScope($staffId ? (int) $staffId : null, $controller->getAccessibleStaffScope())) {
                return $controller->forbidden('You are not allowed to access this staff attendance history');
            }
            $result = $this->api->getStaffAttendanceHistory($staffId);
            return $controller->handleResponse($result);
        } catch (\RuntimeException $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->serverError('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStaffService
    public function getStaffSummary($id, $data, $segments, BaseController $controller) {
        $staffId = $id;
        $scope = $controller->getAccessibleStaffScope();
        if (!$controller->isStaffInScope($staffId ? (int) $staffId : null, $scope)) { return $controller->forbidden('You are not allowed to access this staff attendance summary'); }
        $result = $this->api->getStaffAttendanceSummary($staffId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStaffService
    public function getStaffPercentage($id, $data, $segments, BaseController $controller) {
        $staffId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStaffService
    public function getChronicStaffAbsentees($id, $data, $segments, BaseController $controller) {
        $departmentId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStaffService
    public function getStaffToday($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('staff.attendance.view', ['system administrator', 'school administrator', 'headteacher', 'director'])) return $denied;
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $scope = $controller->getAccessibleStaffScope();
            if ($scope['restricted'] && empty($scope['staff_ids'])) { return $controller->success(['date' => $date, 'summary' => ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0, 'off_day' => 0, 'not_marked' => 0], 'staff' => []], 'Staff attendance retrieved'); }
            $where = ["1=1"]; $params = [$date];
            if ($departmentId) { $where[] = "v.department_id = ?"; $params[] = (int) $departmentId; }
            if ($scope['restricted']) { $placeholders = implode(',', array_fill(0, count($scope['staff_ids']), '?')); $where[] = "v.staff_id IN ({$placeholders})"; $params = array_merge($params, array_map('intval', $scope['staff_ids'])); }
            $result = $controller->getDb()->query(
                "SELECT v.staff_id, v.staff_id AS id, v.staff_no, v.first_name, v.last_name, v.position, v.department_name, v.department_name AS department, v.marked_status AS attendance_status, v.marked_status AS current_status, v.check_in_time, v.check_out_time, v.leave_id, v.leave_type, v.leave_status, v.duty_roster_id AS duty_id, v.duty_code AS duty_type_id, v.duty_name AS duty_type, v.duty_code AS duty_type_code, v.effective_status, CASE WHEN v.leave_id IS NOT NULL AND v.leave_status = 'approved' THEN 1 ELSE 0 END as is_on_leave, CASE WHEN v.duty_code IN ('OFF', 'WEEKEND_OFF') THEN 1 ELSE 0 END as is_off_day FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('staff_daily_register') . " v WHERE v.date = ? AND " . implode(' AND ', $where) . " ORDER BY v.department_name, v.last_name, v.first_name",
                $params
            );
            $staff = $result->fetchAll(\PDO::FETCH_ASSOC);
            $summary = ['total' => count($staff), 'present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0, 'off_day' => 0, 'not_marked' => 0];
            foreach ($staff as $s) { $status = $s['effective_status'] ?? 'not_marked'; if (isset($summary[$status])) $summary[$status]++; }
            return $controller->success(['date' => $date, 'summary' => $summary, 'staff' => $staff], 'Staff attendance retrieved');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStaffService
    public function postMarkStaff($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('staff.attendance.manage', ['system administrator', 'school administrator', 'headteacher'])) return $denied;
        try {
            $date = $data['date'] ?? date('Y-m-d');
            $shift = $data['shift'] ?? 'full_day';
            $attendance = $data['attendance'] ?? [];
            $markedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $scope = $controller->getAccessibleStaffScope();
            if (empty($attendance)) return $controller->badRequest('No attendance data provided');
            if ($scope['restricted'] && empty($scope['staff_ids'])) return $controller->forbidden('You are not allowed to mark staff attendance');
            $yearRow = $controller->getDb()->query("SELECT id FROM academic_years WHERE YEAR(?) = year_code LIMIT 1", [$date])->fetch(\PDO::FETCH_ASSOC);
            $academicYearId = $yearRow['id'] ?? null;
            $dayName = date('l', strtotime($date));
            $created = 0; $updated = 0; $autoMarked = 0;
            foreach ($attendance as $record) {
                $staffId = $record['staff_id'] ?? null;
                $status = strtolower((string)($record['status'] ?? 'present'));
                $checkIn = $record['check_in_time'] ?? null;
                $checkOut = $record['check_out_time'] ?? null;
                $notes = $record['notes'] ?? null;
                if (!$staffId) continue;
                if (!$controller->isStaffInScope((int)$staffId, $scope)) { return $controller->forbidden('Not allowed to mark attendance for one or more staff members'); }
                if (!in_array($status, ['present', 'absent', 'late'], true)) $status = 'present';
                $staffRow = $controller->getDb()->query("SELECT sap.work_start_time, sap.late_threshold_minutes FROM staff_attendance_profiles sap WHERE sap.staff_id = ? AND sap.is_active = 1", [$staffId])->fetch(\PDO::FETCH_ASSOC);
                $expectedCheckIn = $staffRow['work_start_time'] ?? null;
                $lateThresh = (int)($staffRow['late_threshold_minutes'] ?? 15);
                if ($status === 'present' && $checkIn && $expectedCheckIn) {
                    $expectedPlus = date('H:i:s', strtotime($expectedCheckIn) + $lateThresh * 60);
                    if ($checkIn > $expectedPlus) $status = 'late';
                }
                $leave = $controller->getDb()->query("SELECT id FROM staff_leaves WHERE staff_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'", [$staffId, $date])->fetch(\PDO::FETCH_ASSOC);
                $rosterOff = $controller->getDb()->query("SELECT sdr.id FROM staff_duty_roster sdr JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id WHERE sdr.staff_id = ? AND sdr.date = ? AND sdt.code IN ('OFF','WEEKEND_OFF')", [$staffId, $date])->fetch(\PDO::FETCH_ASSOC);
                $patternOff = $controller->getDb()->query("SELECT id FROM staff_off_day_patterns WHERE staff_id = ? AND day_of_week = ? AND is_off = 1 AND ? >= effective_from AND (effective_to IS NULL OR ? <= effective_to)", [$staffId, $dayName, $date, $date])->fetch(\PDO::FETCH_ASSOC);
                $isOffDay = ($rosterOff || $patternOff);
                $isOnLeave = (bool)$leave;
                $absenceReason = null;
                if ($isOnLeave && $record['status'] !== 'present') { $status = 'absent'; $absenceReason = 'leave'; $autoMarked++; }
                elseif ($isOffDay && $record['status'] !== 'present') { $status = 'absent'; $absenceReason = 'off_day'; $autoMarked++; }
                elseif ($status === 'absent') {
                    $absenceReason = $record['absence_reason'] ?? 'unexcused';
                    if (!$absenceReason || !in_array($absenceReason, ['leave', 'sick', 'off_day', 'unauthorized', 'other'])) { $absenceReason = 'unauthorized'; }
                }
                $existing = $controller->getDb()->query("SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ?", [$staffId, $date])->fetch(\PDO::FETCH_ASSOC);
                if ($existing) {
                    $controller->getDb()->query("UPDATE staff_attendance SET status = ?, check_in = ?, check_out = ?, absence_reason = ?, leave_id = ?, notes = ?, marked_by = ? WHERE id = ?", [$status, $checkIn, $checkOut, $absenceReason, $isOnLeave ? $leave['id'] : null, $notes, $markedBy, $existing['id']]);
                    $updated++;
                } else {
                    $controller->getDb()->query("INSERT INTO staff_attendance (staff_id, date, academic_year_id, status, check_in, check_out, absence_reason, leave_id, notes, marked_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())", [$staffId, $date, $academicYearId, $status, $checkIn, $checkOut, $absenceReason, $isOnLeave ? $leave['id'] : null, $notes, $markedBy]);
                    $created++;
                }
            }
            return $controller->success(['created' => $created, 'updated' => $updated, 'auto_marked' => $autoMarked, 'total' => $created + $updated, 'date' => $date, 'shift' => $shift], 'Staff attendance marked successfully');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStaffService
    public function getStaffRegisterContext($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('staff.attendance.view', ['system administrator', 'school administrator', 'headteacher', 'director'])) return $denied;
        try {
            $date = $_GET['date'] ?? date('Y-m-d');
            $departmentId = $_GET['department_id'] ?? null;
            $shift = $_GET['shift'] ?? 'full_day';
            $dayName = date('l', strtotime($date));
            $dayNumber = (int)date('N', strtotime($date));
            $calEntry = $controller->getDb()->query("SELECT cdt.name AS day_type, acd.title, cdt.affects_day_students, cdt.affects_boarders FROM academic_year_calendar_days acd LEFT JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id WHERE acd.date = ?", [$date])->fetch(\PDO::FETCH_ASSOC);
            $dayType = $calEntry['day_type'] ?? ($dayNumber >= 6 ? 'weekend' : 'school_day');
            $eventName = $calEntry['title'] ?? ($dayNumber === 7 ? 'Sunday' : ($dayNumber === 6 ? 'Saturday' : 'Working Day'));
            $isWorkingDay = !in_array($dayType, ['public_holiday', 'school_holiday']);
            $onlyRosterStaff = in_array($dayType, ['public_holiday']);
            $where = []; $params = [$date];
            if ($departmentId) { $where[] = "v.department_id = ?"; $params[] = (int)$departmentId; }
            $staff = $controller->getDb()->query(
                "SELECT v.staff_id, v.staff_no, v.staff_name, v.position, v.work_start_time, v.late_threshold_minutes, v.department_id, v.department_name, v.staff_category, v.attendance_id, v.marked_status, v.shift AS marked_shift, v.check_in_time, v.expected_check_in, v.check_out_time, v.absence_reason, v.attendance_notes, v.leave_id, v.leave_type, v.leave_start, v.leave_end, v.relief_staff_name, v.duty_roster_id AS roster_id, v.duty_code, v.duty_name, v.duty_shift, v.duty_start, v.duty_end, v.duty_location, v.pattern_off_id, v.effective_status, v.can_mark FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('staff_daily_register') . " v WHERE v.date = ?" . ($where ? ' AND ' . implode(' AND ', $where) : '') . " ORDER BY v.department_name, v.staff_name",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
            $summary = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'on_leave' => 0, 'off_day' => 0, 'not_marked' => 0, 'on_duty' => 0];
            foreach ($staff as $s) { $summary['total']++; $st = $s['effective_status'] ?? 'not_marked'; if (isset($summary[$st])) $summary[$st]++; if ($s['duty_code'] && !in_array($s['duty_code'], ['OFF', 'WEEKEND_OFF'])) $summary['on_duty']++; }
            $shifts = ['full_day' => 'Full Day (08:00–17:00)'];
            if ($dayNumber >= 6 || !$isWorkingDay) { $shifts = ['morning' => 'Morning Shift (06:00–14:00)', 'afternoon' => 'Afternoon Shift (14:00–22:00)', 'night' => 'Night Shift (22:00–06:00)', 'full_day' => 'Full Day']; }
            return $controller->success(['date' => $date, 'day_name' => $dayName, 'day_type' => $dayType, 'event_name' => $eventName, 'is_working_day' => $isWorkingDay, 'only_roster' => $onlyRosterStaff, 'available_shifts' => $shifts, 'current_shift' => $shift, 'staff' => $staff, 'summary' => $summary]);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->serverError('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStaffService
    public function getStaffReport($id, $data, $segments, BaseController $controller) {
        if ($denied = $controller->guardStaffAttendance('staff.attendance.view', ['system administrator', 'school administrator', 'headteacher', 'director'])) return $denied;
        try {
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $dutyTypeId = $data['duty_type_id'] ?? $_GET['duty_type_id'] ?? null;
            $statusFilter = $data['status'] ?? $_GET['status'] ?? null;
            if ($dateFrom > $dateTo) { [$dateFrom, $dateTo] = [$dateTo, $dateFrom]; }
            $where = ["sa.date BETWEEN ? AND ?"]; $params = [$dateFrom, $dateTo];
            if ($departmentId) { $where[] = "sda.department_id = ?"; $params[] = (int)$departmentId; }
            if ($statusFilter) { $where[] = "sa.status = ?"; $params[] = $statusFilter; }
            $joinDept = "LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id";
            $joinDuty = "";
            if ($dutyTypeId) { $joinDuty = "JOIN staff_duty_roster sdr ON sdr.staff_id = s.id AND sdr.date = sa.date"; $where[] = "sdr.duty_type_id = ?"; $params[] = (int)$dutyTypeId; }
            $sql = "SELECT s.id, s.staff_no, CONCAT(p.first_name, ' ', p.last_name) AS staff_name, d.name AS department, sa.date, sa.status, sa.check_in, sa.check_out, sa.absence_reason, sa.notes FROM staff_attendance sa JOIN staff s ON s.id = sa.staff_id LEFT JOIN persons p ON p.id = s.person_id {$joinDept} LEFT JOIN departments d ON d.id = sda.department_id {$joinDuty} WHERE " . implode(' AND ', $where) . " ORDER BY p.last_name, sa.date";
            $rows = $controller->getDb()->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            $aggregate = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
            foreach ($rows as $r) { $aggregate[$r['status'] ?? 'absent']++; $aggregate['total']++; }
            return $controller->success(['records' => $rows, 'aggregate' => $aggregate], 'Staff attendance report retrieved');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->serverError('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStaffService
    public function getDepartmentAttendance($id, $data, $segments, BaseController $controller) {
        $departmentId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getDepartmentAttendance($departmentId, $termId, $yearId);
        return $controller->handleResponse($result);
    }
}
