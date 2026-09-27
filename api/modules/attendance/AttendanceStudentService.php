<?php

namespace App\API\Modules\attendance;

use App\API\Controllers\BaseController;

class AttendanceStudentService
{
    private AttendanceAPI $api;

    public function __construct(AttendanceAPI $api)
    {
        $this->api = $api;
    }

    public function getStudentHistory($id, $data, $segments, BaseController $controller)
    {
        $studentId = $id ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceHistory($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentSummary($id, $data, $segments, BaseController $controller) {
        $studentId = $id ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceSummary($studentId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getClassAttendance($id, $data, $segments, BaseController $controller) {
        $classId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getClassAttendance($classId, $termId, $yearId);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentPercentage($id, $data, $segments, BaseController $controller) {
        $studentId = $id;
        try {
            $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
            $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days FROM student_attendance sa JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id WHERE sae.student_id = ?";
            $params = [$studentId];
            if ($termId) { $sql .= " AND sae.academic_year_terms_id = ?"; $params[] = $termId; }
            $result = $controller->getDb()->query($sql, $params);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            $total = (int) ($row['total_days'] ?? 0);
            $present = (int) ($row['present_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 2) : 0;
            return $controller->success(['student_id' => $studentId, 'total_days' => $total, 'present_days' => $present, 'percentage' => $percentage, 'term_id' => $termId], 'Attendance percentage calculated');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function getChronicStudentAbsentees($id, $data, $segments, BaseController $controller) {
        $classId = $id;
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
        return $controller->handleResponse($result);
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentsByClass($id, $data, $segments, BaseController $controller) {
        try {
            $streamId = $id ?? $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!$streamId) { return $controller->badRequest('Missing stream_id'); }
            $scope = $controller->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) { return $controller->forbidden('You are not allowed to access this class attendance register'); }
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $query = "SELECT s.id, s.admission_no, p.first_name, p.last_name, st.name as student_type, st.code as student_type_code, sa.id as attendance_id, sa.status as stored_status, sa.absence_reason, CASE WHEN sa.absence_reason = 'permission' THEN 'permission' ELSE sa.status END as attendance_status, CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as has_permission, spt.code as permission_type_code, spt.name as permission_type, sp.reason as permission_reason FROM students s JOIN persons p ON p.id = s.person_id LEFT JOIN student_types st ON s.student_type_id = st.id JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active' JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id LEFT JOIN admission_applications aa ON aa.id = s.application_id LEFT JOIN student_attendance sa ON sa.student_academic_enrollment_id = sae.id AND sa.date = ? LEFT JOIN student_permissions sp ON sp.student_id = s.id AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved' LEFT JOIN student_permission_types spt ON spt.id = sp.permission_type_id WHERE aycs.id = ? AND s.status = 'active' AND COALESCE(CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END, CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END) <= ? ORDER BY p.last_name, p.first_name";
            $result = $controller->getDb()->query($query, [$date, $date, $streamId, $date]);
            $students = $result->fetchAll(\PDO::FETCH_ASSOC);
            return $controller->success($students, 'Students retrieved successfully');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function postMarkBulk($id, $data, $segments, BaseController $controller) {
        try {
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $sessionId = $data['session_id'] ?? null;
            $registerType = $data['register_type'] ?? 'class';
            if (!$streamId) { return $controller->badRequest('Stream ID is required'); }
            if (empty($attendance)) { return $controller->badRequest('Attendance data is required'); }
            $scope = $controller->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) { return $controller->forbidden('You are not allowed to mark attendance for this class'); }
            $markedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $activeYear = $controller->getDb()->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            $academicYearId = $activeYear['id'] ?? null;
            $created = 0; $updated = 0;
            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $status = strtolower((string)($record['status'] ?? 'present'));
                $reason = $record['absence_reason'] ?? null;
                if (!$studentId) continue;
                if (!in_array($status, ['present', 'absent', 'late'], true)) $status = 'present';
                $enrollment = $controller->getDb()->query("SELECT sae.id FROM student_academic_enrollments sae JOIN students s ON s.id=sae.student_id LEFT JOIN admission_applications aa ON aa.id=s.application_id JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id WHERE sae.student_id = ? AND aycs.id = ? AND sae.enrollment_status = 'active' AND COALESCE(CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END, CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END) <= ? LIMIT 1", [$studentId, $streamId, $date])->fetch(\PDO::FETCH_ASSOC);
                if (!$enrollment) continue;
                $saeId = (int) $enrollment['id'];
                $existing = $controller->getDb()->query("SELECT id FROM student_attendance WHERE student_academic_enrollment_id = ? AND date = ? AND register_type = ? AND (session_id = ? OR (session_id IS NULL AND ? IS NULL))", [$saeId, $date, $registerType, $sessionId, $sessionId])->fetch(\PDO::FETCH_ASSOC);
                if ($existing) {
                    $controller->getDb()->query("UPDATE student_attendance SET status = ?, absence_reason = ?, marked_by = ?, updated_at = NOW() WHERE id = ?", [$status, $reason, $markedBy, $existing['id']]);
                    $updated++;
                } else {
                    $controller->getDb()->query("INSERT INTO student_attendance (student_academic_enrollment_id, date, register_type, session_id, status, absence_reason, marked_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())", [$saeId, $date, $registerType, $sessionId, $status, $reason, $markedBy]);
                    $created++;
                }
            }
            return $controller->success(['created' => $created, 'updated' => $updated, 'total' => $created + $updated, 'date' => $date, 'stream_id' => $streamId], 'Attendance marked successfully');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }

    // TODO: Delegate to AttendanceStudentService
    public function getStudentHistoryByYear($id, $data, $segments, BaseController $controller) {
        $studentId = $id ?? ($segments[0] ?? null);
        if (!$studentId) { return $controller->badRequest('Student ID is required'); }
        try {
            $rows = $controller->getDb()->query(
                "SELECT sae.academic_year_id, ay.year_code, ay.year_name, ayt.id AS term_instance_id,
                        ROW_NUMBER() OVER (PARTITION BY ayt.academic_year_id ORDER BY ayt.opening_date) AS term_number,
                        t.name AS term_name, ayc.class_id, c.name AS class_name,
                        sa.register_type, sa.date, sa.status, sa.absence_reason, sa.session_id,
                        ass.name AS session_name, ass.type AS session_type
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sa.student_academic_enrollment_id = sae.id
                 LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
                 LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = sae.academic_year_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE sae.student_id = ? ORDER BY sa.date ASC, sa.session_id ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rows as $r) {
                $yk = $r['year_code'] ?? 'unknown'; $tk = $r['term_id'] ?? 0; $rt = $r['register_type'];
                if (!isset($grouped[$yk])) $grouped[$yk] = ['year_name' => $r['year_name'], 'year_code' => $r['year_code'], 'terms' => []];
                if (!isset($grouped[$yk]['terms'][$tk])) $grouped[$yk]['terms'][$tk] = ['term_name' => $r['term_name'], 'term_number' => $r['term_number'], 'class_name' => $r['class_name'], 'records' => [], 'summary' => ['class' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0], 'boarding' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0]]];
                $grouped[$yk]['terms'][$tk]['records'][] = $r;
                if (isset($grouped[$yk]['terms'][$tk]['summary'][$rt])) { $grouped[$yk]['terms'][$tk]['summary'][$rt][$r['status'] ?? 'absent']++; $grouped[$yk]['terms'][$tk]['summary'][$rt]['total']++; }
            }
            foreach ($grouped as &$y) { ksort($y['terms']); $y['terms'] = array_values($y['terms']); }
            return $controller->success(['student_id' => $studentId, 'by_year' => array_values($grouped), 'total_rows' => count($rows)]);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $controller->error('An internal error occurred.');
        }
    }
}
