<?php

namespace App\API\Modules\attendance;

use App\API\Controllers\BaseController;
use App\API\Services\ReadReplicaService;

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
            $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present_days FROM " . ReadReplicaService::qualifiedRef("student_attendance_enrollment") . "  WHERE sae.student_id = ?";
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
            $query = "SELECT s.id, s.admission_no, p.first_name, p.last_name, st.name as student_type, st.code as student_type_code, sa.id as attendance_id, sa.status as stored_status, sa.absence_reason, CASE WHEN sa.absence_reason = 'permission' THEN 'permission' ELSE sa.status END as attendance_status, CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as has_permission, spt.code as permission_type_code, spt.name as permission_type, sp.reason as permission_reason FROM " . ReadReplicaService::qualifiedRef("student_directory") . "  WHERE aycs.id = ? AND s.status = 'active' AND COALESCE(CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END, CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END) <= ? ORDER BY p.last_name, p.first_name";
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

            // Set-based N+1 fix (scaling masterplan): resolve every submitted
            // learner's ACTIVE enrollment in ONE query and all existing marks in
            // ONE query, then run prepared-once writes. A whole class register
            // previously cost 2 queries per learner (~100 queries for 50 pupils).
            $db = $controller->getDb();
            $studentIds = array_values(array_unique(array_filter(array_map(
                static fn ($r) => (int) ($r['student_id'] ?? 0),
                $attendance
            ))));
            if ($studentIds === []) {
                return $controller->success(['created' => 0, 'updated' => 0, 'total' => 0, 'date' => $date, 'stream_id' => $streamId], 'Attendance marked successfully');
            }

            $inStudents = implode(',', array_fill(0, count($studentIds), '?'));
            $enrollStmt = $db->prepare(
                "SELECT sae.student_id, sae.id
                 FROM student_academic_enrollments sae
                 JOIN students s ON s.id = sae.student_id
                 LEFT JOIN admission_applications aa ON aa.id = s.application_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 WHERE sae.student_id IN ({$inStudents})
                   AND aycs.id = ?
                   AND sae.enrollment_status = 'active'
                   AND COALESCE(
                       CASE WHEN s.entry_source = 'admission' THEN aa.enrolled_at END,
                       CASE WHEN s.entry_source IS NULL OR s.entry_source <> 'admission' THEN sae.enrolled_on END
                   ) <= ?"
            );
            $enrollStmt->execute(array_merge($studentIds, [$streamId, $date]));
            $enrollmentByStudent = [];
            foreach ($enrollStmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $sid => $saeId) {
                $enrollmentByStudent[(int) $sid] = (int) $saeId;
            }
            if ($enrollmentByStudent === []) {
                return $controller->success(['created' => 0, 'updated' => 0, 'total' => 0, 'date' => $date, 'stream_id' => $streamId], 'Attendance marked successfully');
            }

            $saeIds = array_values($enrollmentByStudent);
            $inSae = implode(',', array_fill(0, count($saeIds), '?'));
            $existingStmt = $db->prepare(
                "SELECT id, student_academic_enrollment_id FROM student_attendance
                 WHERE student_academic_enrollment_id IN ({$inSae})
                   AND date = ? AND register_type = ?
                   AND (session_id = ? OR (session_id IS NULL AND ? IS NULL))"
            );
            $existingStmt->execute(array_merge($saeIds, [$date, $registerType, $sessionId, $sessionId]));
            $existingBySae = [];
            foreach ($existingStmt->fetchAll(\PDO::FETCH_KEY_PAIR) as $attId => $saeId) {
                $existingBySae[(int) $saeId] = (int) $attId;
            }

            $updateStmt = $db->prepare(
                "UPDATE student_attendance
                 SET status = ?, absence_reason = ?, marked_by = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $insertStmt = $db->prepare(
                "INSERT INTO student_attendance
                    (student_academic_enrollment_id, date, register_type, session_id, status, absence_reason, marked_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $created = 0; $updated = 0;
            $db->beginTransaction();
            try {
                foreach ($attendance as $record) {
                    $studentId = (int) ($record['student_id'] ?? 0);
                    if (!$studentId || !isset($enrollmentByStudent[$studentId])) continue;
                    $status = strtolower((string)($record['status'] ?? 'present'));
                    $reason = $record['absence_reason'] ?? null;
                    if (!in_array($status, ['present', 'absent', 'late'], true)) $status = 'present';
                    $saeId = $enrollmentByStudent[$studentId];
                    if (isset($existingBySae[$saeId])) {
                        $updateStmt->execute([$status, $reason, $markedBy, $existingBySae[$saeId]]);
                        $updated++;
                    } else {
                        $insertStmt->execute([$saeId, $date, $registerType, $sessionId, $status, $reason, $markedBy]);
                        $created++;
                    }
                }
                $db->commit();
            } catch (\Throwable $writeError) {
                if ($db->inTransaction()) $db->rollBack();
                throw $writeError;
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
                 FROM " . ReadReplicaService::qualifiedRef("student_attendance") . " sa
                 JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sa.student_academic_enrollment_id = sae.id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id = sae.academic_year_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("attendance_sessions") . " ass ON ass.id = sa.session_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.academic_year_id = sae.academic_year_id
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
