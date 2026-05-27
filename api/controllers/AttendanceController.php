<?php

namespace App\API\Controllers;

use App\API\Modules\attendance\AttendanceAPI;
use Exception;

class AttendanceController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AttendanceAPI();
    }
    public function index()
    {
        return $this->success(['message' => 'Attendance API is running']);
    }

    /**
     * GET /api/attendance/today - Get today's attendance statistics for dashboard
     * Returns: present count, absent count, total, percentage
     */
    public function getToday($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;
            $today = date('Y-m-d');

            // Get attendance records for today (combining student and staff)
            $query = "
                (SELECT 
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    COUNT(*) as total
                FROM student_attendance
                WHERE DATE(date) = ?)
                UNION ALL
                (SELECT 
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    COUNT(*) as total
                FROM staff_attendance
                WHERE DATE(date) = ?)
            ";

            $result = $db->query($query, [$today, $today]);
            $studentRow = $result->fetch() ?? ['present' => 0, 'absent' => 0, 'total' => 0];
            $staffRow = $result->fetch() ?? ['present' => 0, 'absent' => 0, 'total' => 0];

            // Combine the results
            $present = (int) ($studentRow['present'] ?? 0) + (int) ($staffRow['present'] ?? 0);
            $absent = (int) ($studentRow['absent'] ?? 0) + (int) ($staffRow['absent'] ?? 0);
            $total = (int) ($studentRow['total'] ?? 0) + (int) ($staffRow['total'] ?? 0);
            $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

            return $this->success([
                'present' => $present,
                'absent' => $absent,
                'total' => $total,
                'percentage' => (float) $percentage,
                'date' => $today,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Today attendance statistics');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch attendance statistics: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/today-attendance - Get today's student attendance percentage for dashboard
     */
    public function getTodayAttendance($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;
            $today = date('Y-m-d');

            // Get student attendance for today
            $query = "
                SELECT 
                    COUNT(*) as total_students,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_students
                FROM student_attendance
                WHERE DATE(date) = ?
            ";

            $result = $db->query($query, [$today]);
            $row = $result->fetch();

            $totalStudents = (int) ($row['total_students'] ?? 0);
            $presentStudents = (int) ($row['present_students'] ?? 0);
            $percentage = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100, 1) : 0;

            return $this->success([
                'total_students' => $totalStudents,
                'present_students' => $presentStudents,
                'attendance_percentage' => (float) $percentage,
                'date' => $today,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Student attendance statistics');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch student attendance: ' . $e->getMessage());
        }
    }

    public function getStudentHistory($studentId = null, $data = [], $segments = [])
    {
        $studentId = $studentId ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceHistory($studentId);
        return $this->handleResponse($result);
    }

    public function getStudentSummary($studentId = null, $data = [], $segments = [])
    {
        $studentId = $studentId ?? ($data['studentId'] ?? null);
        $result = $this->api->getStudentAttendanceSummary($studentId);
        return $this->handleResponse($result);
    }

    public function getClassAttendance($classId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getClassAttendance($classId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStudentPercentage($studentId = null, $data = [], $segments = [])
    {
        try {
            $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
            $sql = "SELECT COUNT(*) as total_days, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days FROM student_attendance WHERE student_id = ?";
            $params = [$studentId];
            if ($termId) {
                $sql .= " AND term_id = ?";
                $params[] = $termId;
            }
            $result = $this->db->query($sql, $params);
            $row = $result->fetch(\PDO::FETCH_ASSOC);
            $total = (int) ($row['total_days'] ?? 0);
            $present = (int) ($row['present_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 2) : 0;
            return $this->success([
                'student_id' => $studentId,
                'total_days' => $total,
                'present_days' => $present,
                'percentage' => $percentage,
                'term_id' => $termId
            ], 'Attendance percentage calculated');
        } catch (\Exception $e) {
            return $this->error('Failed to calculate percentage: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/trends - Return attendance trends for last 30 days
     * Returns: data (30-day trends), absent_students, absent_staff, summary
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        try {
            $service = new \App\API\Services\DirectorAnalyticsService();
            $trends = $service->getAttendanceTrends();
            if (!is_array($trends)) {
                return $this->serverError('Attendance trends not available');
            }
            // Return the full structured response (data, absent_students, absent_staff, summary)
            return $this->success($trends, 'Attendance trends retrieved');
        } catch (\Exception $e) {
            return $this->serverError('Failed to fetch attendance trends: ' . $e->getMessage());
        }
    }

    public function getChronicStudentAbsentees($classId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
        return $this->handleResponse($result);
    }


    public function getStaffHistory($staffId = null, $data = [], $segments = [])
    {
        $staffId = $staffId ?? ($data['staffId'] ?? null);
        $scope = $this->getAccessibleStaffScope();
        if (!$this->isStaffInScope($staffId ? (int) $staffId : null, $scope)) {
            return $this->forbidden('You are not allowed to access this staff attendance history');
        }
        $result = $this->api->getStaffAttendanceHistory($staffId);
        return $this->handleResponse($result);
    }

    public function getStaffSummary($staffId = null, $data = [], $segments = [])
    {
        $staffId = $staffId ?? ($data['staffId'] ?? null);
        $scope = $this->getAccessibleStaffScope();
        if (!$this->isStaffInScope($staffId ? (int) $staffId : null, $scope)) {
            return $this->forbidden('You are not allowed to access this staff attendance summary');
        }
        $result = $this->api->getStaffAttendanceSummary($staffId);
        return $this->handleResponse($result);
    }

    public function getDepartmentAttendance($departmentId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getDepartmentAttendance($departmentId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStaffPercentage($staffId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getChronicStaffAbsentees($departmentId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold);
        return $this->handleResponse($result);
    }

    // CRUD endpoints (list, get, create, update, delete)
    public function get($id = null, $data = [], $segments = [])
    {
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    public function post($id = null, $data = [], $segments = [])
    {
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    public function put($id = null, $data = [], $segments = [])
    {
        $id = $id ?? $data['id'] ?? null;
        if (!$id) {
            return $this->badRequest('Missing attendance record ID');
        }
        // Add type from data, query string, or default to 'student'
        $data['type'] = $data['type'] ?? $_GET['type'] ?? 'student';
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    public function delete($id = null, $data = [], $segments = [])
    {
        $id = $id ?? $data['id'] ?? null;
        if (!$id) {
            return $this->badRequest('Missing attendance record ID');
        }
        // Add type from data, query string, or default to 'student'
        $data['type'] = $data['type'] ?? $_GET['type'] ?? 'student';
        $result = $this->api->delete($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/attendance/classes - Get all classes for attendance marking dropdown
     */
    public function getClasses($id = null, $data = [], $segments = [])
    {
        try {
            $scope = $this->userCanAccessBoardingAttendance()
                ? [
                    'restricted' => false,
                    'staff_id' => $this->getCurrentStaffId(),
                    'class_ids' => [],
                    'stream_ids' => [],
                ]
                : $this->getAccessibleClassScope();
            if ($scope['restricted'] && empty($scope['class_ids'])) {
                return $this->success([], 'No classes assigned to the current user');
            }

            $where = "WHERE cs.status = 'active'";
            $params = [];

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['class_ids']), '?'));
                $where .= " AND c.id IN ({$placeholders})";
                $params = array_map('intval', $scope['class_ids']);
            }

            $query = "
                SELECT c.id, c.name, cs.id as stream_id, cs.stream_name,
                       CONCAT(
                           c.name,
                           CASE
                               WHEN cs.stream_name IS NULL OR cs.stream_name = '' OR cs.stream_name = c.name THEN ''
                               ELSE CONCAT(' - ', cs.stream_name)
                           END
                       ) as display_name,
                       (SELECT COUNT(*) FROM students s WHERE s.stream_id = cs.id AND s.status = 'active') as student_count
                FROM classes c
                JOIN class_streams cs ON cs.class_id = c.id
                {$where}
                ORDER BY c.id, cs.stream_name
            ";
            $result = $this->db->query($query, $params);
            $classes = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($classes, 'Classes retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch classes: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/students-by-class/{stream_id} - Get students for a class
     */
    public function getStudentsByClass($streamId = null, $data = [], $segments = [])
    {
        try {
            $streamId = $streamId ?? $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!$streamId) {
                return $this->badRequest('Missing stream_id');
            }

            $scope = $this->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) {
                return $this->forbidden('You are not allowed to access this class attendance register');
            }

            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $query = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       st.name as student_type,
                       st.code as student_type_code,
                       sa.id as attendance_id,
                       sa.status as stored_status,
                       sa.absence_reason,
                       CASE
                           WHEN sa.absence_reason = 'permission' THEN 'permission'
                           ELSE sa.status
                       END as attendance_status,
                       CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as has_permission,
                       spt.code as permission_type_code,
                       spt.name as permission_type,
                       sp.reason as permission_reason
                FROM students s
                LEFT JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ?
                LEFT JOIN student_permissions sp ON sp.student_id = s.id
                    AND ? BETWEEN sp.start_date AND sp.end_date
                    AND sp.status = 'approved'
                LEFT JOIN student_permission_types spt ON spt.id = sp.permission_type_id
                WHERE s.stream_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ";
            $result = $this->db->query($query, [$date, $date, $streamId]);
            $students = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($students, 'Students retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch students: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/attendance/mark-bulk - Mark attendance for multiple students at once
     * Expects: { stream_id, date, attendance: [ { student_id, status } ] }
     */
    public function postMarkBulk($id = null, $data = [], $segments = [])
    {
        try {
            $streamId    = $data['stream_id']    ?? null;
            $date        = $data['date']         ?? date('Y-m-d');
            $attendance  = $data['attendance']   ?? [];
            $sessionId   = $data['session_id']   ?? null;
            $registerType = $data['register_type'] ?? 'class';

            if (!$streamId)       return $this->badRequest('Missing stream_id');
            if (empty($attendance)) return $this->badRequest('No attendance data provided');

            // Resolve current term + year dynamically (never hardcode)
            $termRow = $this->_resolveTermForDate($date);
            $termId        = $termRow['term_id']  ?? null;
            $academicYearId = $termRow['year_id'] ?? null;

            // Get class_id from stream
            $classRow = $this->db->query("SELECT class_id FROM class_streams WHERE id = ?", [$streamId])->fetch(\PDO::FETCH_ASSOC);
            $classId  = $classRow['class_id'] ?? null;

            // Determine register_type from session if session given
            if ($sessionId) {
                $sess = $this->db->query("SELECT session_type FROM attendance_sessions WHERE id = ?", [$sessionId])->fetch(\PDO::FETCH_ASSOC);
                if ($sess) $registerType = $sess['session_type'] === 'boarding' ? 'boarding' : ($sess['session_type'] === 'activity' ? 'activity' : 'class');
            }

            $markedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $created = 0; $updated = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                if (!$studentId) continue;
                $status = in_array($record['status'] ?? '', ['present','absent','late']) ? $record['status'] : 'present';

                // Use unique key: student + date + session + register_type
                $existing = $this->db->query(
                    "SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND session_id <=> ? AND register_type = ?",
                    [$studentId, $date, $sessionId, $registerType]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $this->db->query(
                        "UPDATE student_attendance SET status = ?, marked_by = ? WHERE id = ?",
                        [$status, $markedBy, $existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->query(
                        "INSERT INTO student_attendance
                         (student_id, date, status, class_id, term_id, academic_year_id,
                          session_id, register_type, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$studentId, $date, $status, $classId, $termId, $academicYearId,
                         $sessionId, $registerType, $markedBy]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created, 'updated' => $updated,
                'total' => $created + $updated,
                'date' => $date, 'stream_id' => $streamId,
                'term_id' => $termId, 'academic_year_id' => $academicYearId,
                'register_type' => $registerType,
            ], 'Attendance marked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to mark attendance: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // SESSION-BASED ATTENDANCE METHODS (NEW)
    // ========================================================================

    /**
     * GET /api/attendance/sessions - Get all attendance sessions
     * Optionally filter by type (academic, boarding, activity)
     */
    public function getSessions($id = null, $data = [], $segments = [])
    {
        try {
            $type = $data['type'] ?? $_GET['type'] ?? null;
            $dayOfWeek = $data['day'] ?? $_GET['day'] ?? date('l'); // Default to today

            $sql = "SELECT * FROM attendance_sessions WHERE status = 'active'";
            $params = [];

            if ($type) {
                $sql .= " AND session_type = ?";
                $params[] = $type;
            }

            // Filter by applicable day
            if ($dayOfWeek) {
                $sql .= " AND JSON_CONTAINS(applicable_days, ?)";
                $params[] = json_encode($dayOfWeek);
            }

            $sql .= " ORDER BY display_order";

            $result = $this->db->query($sql, $params);
            $sessions = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($sessions, 'Attendance sessions retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch sessions: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/session-attendance - Get attendance for a specific session
     */
    public function getSessionAttendance($id = null, $data = [], $segments = [])
    {
        try {
            $sessionId = $id ?? $data['session_id'] ?? $_GET['session_id'] ?? null;
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;

            if (!$sessionId) {
                return $this->badRequest('Session ID is required');
            }

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->forbidden('You are not allowed to access this class attendance register');
            }
            if ($streamScope['empty']) {
                return $this->success([
                    'session' => null,
                    'date' => $date,
                    'students' => [],
                ], 'Session attendance retrieved');
            }

            $sql = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       st.name as student_type, st.code as student_type_code,
                       sa.status as stored_status,
                       sa.absence_reason,
                       CASE
                           WHEN sa.absence_reason = 'permission' THEN 'permission'
                           WHEN sa.status IS NOT NULL THEN sa.status
                           WHEN sp.id IS NOT NULL THEN 'permission'
                           ELSE NULL
                       END as attendance_status,
                       sa.check_in_time, sa.notes,
                       CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END as has_permission,
                       sp.id as permission_id, spt.name as permission_type,
                       spt.code as permission_type_code,
                       sp.reason as permission_reason,
                       sp.start_date as permission_start, sp.end_date as permission_end
                FROM students s
                JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN student_attendance sa ON sa.student_id = s.id 
                    AND sa.date = ? AND sa.session_id = ?
                LEFT JOIN student_permissions sp ON s.id = sp.student_id
                    AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'
                LEFT JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                WHERE s.status = 'active'
            ";
            $params = [$date, $sessionId, $date];

            $sql .= $streamScope['sql'];
            $params = array_merge($params, $streamScope['params']);

            $sql .= " ORDER BY s.last_name, s.first_name";

            $result = $this->db->query($sql, $params);
            $students = $result->fetchAll(\PDO::FETCH_ASSOC);

            // Get session info
            $sessionResult = $this->db->query(
                "SELECT * FROM attendance_sessions WHERE id = ?",
                [$sessionId]
            );
            $session = $sessionResult->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'session' => $session,
                'date' => $date,
                'students' => $students
            ], 'Session attendance retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch session attendance: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/attendance/mark-session - Mark attendance for a specific session
     * Expects: { session_id, stream_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkSession($id = null, $data = [], $segments = [])
    {
        try {
            $sessionId = $data['session_id'] ?? null;
            $streamId  = $data['stream_id']  ?? null;
            $date      = $data['date']       ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy  = $_SERVER['auth_user']['user_id'] ?? 1;

            // Resolve term + year dynamically for this date
            $termRow       = $this->_resolveTermForDate($date);
            $termId        = $termRow['term_id']  ?? null;
            $academicYearId = $termRow['year_id'] ?? null;

            // Determine register_type from session
            $registerType = 'class';
            if ($sessionId) {
                $sess = $this->db->query("SELECT session_type FROM attendance_sessions WHERE id = ?", [$sessionId])->fetch(\PDO::FETCH_ASSOC);
                if ($sess) $registerType = $sess['session_type'] === 'boarding' ? 'boarding' : ($sess['session_type'] === 'activity' ? 'activity' : 'class');
            }

            if (!$sessionId) {
                return $this->badRequest('Session ID is required');
            }
            if (empty($attendance)) {
                return $this->badRequest('No attendance data provided');
            }

            $scope = $this->getAccessibleClassScope();
            if ($streamId) {
                $streamScope = $this->buildStreamScopeClause((int) $streamId, $scope);
                if ($streamScope['forbidden']) {
                    return $this->forbidden('You are not allowed to mark attendance for this class');
                }
            }

            // Get class_id from stream
            $classQuery = $this->db->query("SELECT class_id FROM class_streams WHERE id = ?", [$streamId]);
            $classRow = $classQuery->fetch(\PDO::FETCH_ASSOC);
            $classId = $classRow['class_id'] ?? null;

            $created = 0;
            $updated = 0;
            $excused = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $requestedStatus = strtolower((string) ($record['status'] ?? 'present'));
                $notes = $record['notes'] ?? null;

                if (!$studentId)
                    continue;

                if (!in_array($requestedStatus, ['present', 'absent', 'late', 'permission'], true)) {
                    $requestedStatus = 'present';
                }

                // Check for active permission
                $permQuery = $this->db->query(
                    "SELECT id FROM student_permissions 
                     WHERE student_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'",
                    [$studentId, $date]
                );
                $permission = $permQuery->fetch(\PDO::FETCH_ASSOC);
                $permissionId = $permission['id'] ?? null;

                $status = $requestedStatus === 'permission' ? 'absent' : $requestedStatus;
                $absenceReason = null;

                if ($status === 'absent') {
                    if ($permissionId || $requestedStatus === 'permission') {
                        $absenceReason = 'permission';
                        if ($permissionId) {
                            $excused++;
                        }
                    } else {
                        $absenceReason = 'unexcused';
                    }
                }

                // Check existing (unique: student + date + session + register_type)
                $existsQuery = $this->db->query(
                    "SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND session_id = ? AND register_type = ?",
                    [$studentId, $date, $sessionId, $registerType]
                );
                $existing = $existsQuery->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $this->db->query(
                        "UPDATE student_attendance SET status = ?, absence_reason = ?,
                         permission_id = ?, notes = ?, check_in_time = CURTIME() WHERE id = ?",
                        [$status, $absenceReason, $permissionId, $notes, $existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->query(
                        "INSERT INTO student_attendance
                         (student_id, date, status, session_id, class_id, term_id, academic_year_id,
                          register_type, check_in_time, absence_reason, permission_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURTIME(), ?, ?, ?, ?, NOW())",
                        [
                            $studentId, $date, $status, $sessionId,
                            $classId, $termId, $academicYearId,
                            $registerType,
                            $absenceReason, $permissionId, $notes, $markedBy
                        ]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created,
                'updated' => $updated,
                'excused' => $excused,
                'total' => $created + $updated,
                'session_id' => $sessionId,
                'date' => $date
            ], 'Session attendance marked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to mark session attendance: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/academic-summary
     * Aggregate learner attendance for the shared reports page.
     */
    public function getAcademicSummary($id = null, $data = [], $segments = [])
    {
        try {
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $statusFilter = $data['status'] ?? $_GET['status'] ?? null;

            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->forbidden('You are not allowed to access attendance for this class');
            }
            if ($streamScope['empty']) {
                return $this->success(
                    $this->buildEmptyAcademicSummary($dateFrom, $dateTo, $streamId ? (int) $streamId : null),
                    'Academic attendance summary retrieved'
                );
            }

            $attendanceJoin = " AND sa.date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];
            if ($sessionId) {
                $attendanceJoin .= " AND sa.session_id = ?";
                $params[] = (int) $sessionId;
            }

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    c.name AS class_name,
                    cs.stream_name,
                    st.name AS student_type,
                    st.code AS student_type_code,
                    COUNT(sa.id) AS total_days,
                    SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN sa.status = 'absent' AND COALESCE(sa.absence_reason, 'unexcused') <> 'permission' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN sa.absence_reason = 'permission' THEN 1 ELSE 0 END) AS permission,
                    MAX(CASE WHEN sa.status = 'absent' OR sa.absence_reason = 'permission' THEN sa.date END) AS last_absent_date
                FROM students s
                JOIN class_streams cs ON cs.id = s.stream_id
                JOIN classes c ON c.id = cs.class_id
                LEFT JOIN student_types st ON st.id = s.student_type_id
                LEFT JOIN student_attendance sa ON sa.student_id = s.id {$attendanceJoin}
                WHERE s.status = 'active' {$streamScope['sql']}
                GROUP BY
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    s.middle_name,
                    c.name,
                    cs.stream_name,
                    st.name,
                    st.code
                ORDER BY c.name, cs.stream_name, s.last_name, s.first_name
            ";

            $params = array_merge($params, $streamScope['params']);
            $students = $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

            $students = array_map(static function (array $row): array {
                $row['student_id'] = (int) $row['student_id'];
                $row['total_days'] = (int) ($row['total_days'] ?? 0);
                $row['present'] = (int) ($row['present'] ?? 0);
                $row['absent'] = (int) ($row['absent'] ?? 0);
                $row['late'] = (int) ($row['late'] ?? 0);
                $row['permission'] = (int) ($row['permission'] ?? 0);
                $row['attendance_percentage'] = $row['total_days'] > 0
                    ? round(($row['present'] / $row['total_days']) * 100, 1)
                    : 0;
                return $row;
            }, $students);

            $students = $this->applyAcademicStatusFilter($students, $statusFilter);
            $summary = $this->summarizeAcademicRows($students);

            $trendSql = "
                SELECT
                    sa.date,
                    SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present,
                    SUM(CASE WHEN sa.status = 'absent' AND COALESCE(sa.absence_reason, 'unexcused') <> 'permission' THEN 1 ELSE 0 END) AS absent,
                    SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) AS late,
                    SUM(CASE WHEN sa.absence_reason = 'permission' THEN 1 ELSE 0 END) AS permission,
                    COUNT(sa.id) AS total
                FROM student_attendance sa
                JOIN students s ON s.id = sa.student_id
                WHERE sa.date BETWEEN ? AND ? {$streamScope['sql']}
            ";
            $trendParams = [$dateFrom, $dateTo];
            if ($sessionId) {
                $trendSql .= " AND sa.session_id = ?";
                $trendParams[] = (int) $sessionId;
            }
            $trendSql .= "
                GROUP BY sa.date
                ORDER BY sa.date ASC
            ";
            $trendParams = array_merge($trendParams, $streamScope['params']);
            $trend = $this->db->query($trendSql, $trendParams)->fetchAll(\PDO::FETCH_ASSOC);

            $trend = array_map(static function (array $row): array {
                return [
                    'date' => $row['date'],
                    'present' => (int) ($row['present'] ?? 0),
                    'absent' => (int) ($row['absent'] ?? 0),
                    'late' => (int) ($row['late'] ?? 0),
                    'permission' => (int) ($row['permission'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ];
            }, $trend);

            $lowAttendance = array_values(array_map(static function (array $student): array {
                return [
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'admission_no' => $student['admission_no'],
                    'attendance_percentage' => $student['attendance_percentage'],
                    'absent_days' => $student['absent'] + $student['permission'],
                    'last_absent_date' => $student['last_absent_date'] ?? null,
                ];
            }, array_filter($students, static function (array $student): bool {
                return ($student['total_days'] ?? 0) > 0 && ($student['attendance_percentage'] ?? 0) < 80;
            })));

            return $this->success([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'stream_id' => $streamId ? (int) $streamId : null,
                'students' => $students,
                'summary' => $summary,
                'trend' => $trend,
                'low_attendance' => $lowAttendance,
            ], 'Academic attendance summary retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to load academic attendance summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/daily-register
     * Return raw attendance rows for the selected day/session.
     */
    public function getDailyRegister($id = null, $data = [], $segments = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->forbidden('You are not allowed to access attendance for this class');
            }
            if ($streamScope['empty']) {
                return $this->success([], 'Daily register retrieved');
            }

            $sql = "
                SELECT
                    sa.id,
                    sa.student_id,
                    sa.date,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name AS class_name,
                    cs.stream_name,
                    st.name AS student_type,
                    st.code AS student_type_code,
                    ass.name AS session_name,
                    CASE
                        WHEN sa.absence_reason = 'permission' THEN 'permission'
                        ELSE sa.status
                    END AS status,
                    sa.status AS stored_status,
                    sa.absence_reason,
                    sa.check_in_time AS marked_at,
                    sa.notes
                FROM student_attendance sa
                JOIN students s ON s.id = sa.student_id
                JOIN class_streams cs ON cs.id = s.stream_id
                JOIN classes c ON c.id = cs.class_id
                LEFT JOIN student_types st ON st.id = s.student_type_id
                LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
                WHERE sa.date = ? {$streamScope['sql']}
            ";
            $params = [$date];

            if ($sessionId) {
                $sql .= " AND sa.session_id = ?";
                $params[] = (int) $sessionId;
            }

            $sql .= " ORDER BY cs.class_id, cs.stream_name, s.last_name, s.first_name";
            $params = array_merge($params, $streamScope['params']);

            $rows = $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows, 'Daily register retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to load daily register: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // BOARDING ATTENDANCE METHODS
    // ========================================================================

    /**
     * GET /api/attendance/dormitories - Get all dormitories
     */
    public function getDormitories($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->forbidden('You are not allowed to access boarding attendance');
            }

            $sql = "
                SELECT d.*, 
                       CONCAT(hp.first_name, ' ', hp.last_name) as house_parent_name,
                       (SELECT COUNT(*) FROM dormitory_assignments da 
                        WHERE da.dormitory_id = d.id AND da.status = 'active') as student_count
                FROM dormitories d
                LEFT JOIN staff hp ON d.house_parent_id = hp.id
                WHERE d.status = 'active'
                ORDER BY d.name
            ";
            $result = $this->db->query($sql);
            $dormitories = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($dormitories, 'Dormitories retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch dormitories: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/dormitory-students - Get students in a dormitory for roll call
     */
    public function getDormitoryStudents($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->forbidden('You are not allowed to access boarding attendance');
            }

            $dormitoryId = $id ?? $data['dormitory_id'] ?? $_GET['dormitory_id'] ?? null;
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;

            if (!$dormitoryId) {
                return $this->badRequest('Dormitory ID is required');
            }

            $sql = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       c.name as class_name, da.bed_number,
                       ba.status as current_status, ba.check_time, ba.notes,
                       sp.id as permission_id, spt.name as permission_type,
                       sp.end_date as permission_until
                FROM dormitory_assignments da
                JOIN students s ON da.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN boarding_attendance ba ON s.id = ba.student_id 
                    AND ba.date = ? AND ba.dormitory_id = ?
            ";
            $params = [$date, $dormitoryId];

            if ($sessionId) {
                $sql .= " AND ba.session_id = ?";
                $params[] = $sessionId;
            }

            $sql .= "
                LEFT JOIN student_permissions sp ON s.id = sp.student_id
                    AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'
                LEFT JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                WHERE da.dormitory_id = ? AND da.status = 'active' AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ";
            $params[] = $date;
            $params[] = $dormitoryId;

            $result = $this->db->query($sql, $params);
            $students = $result->fetchAll(\PDO::FETCH_ASSOC);

            // Get dormitory info
            $dormResult = $this->db->query(
                "SELECT d.*, CONCAT(hp.first_name, ' ', hp.last_name) as house_parent_name
                 FROM dormitories d LEFT JOIN staff hp ON d.house_parent_id = hp.id
                 WHERE d.id = ?",
                [$dormitoryId]
            );
            $dormitory = $dormResult->fetch(\PDO::FETCH_ASSOC);

            return $this->success([
                'dormitory' => $dormitory,
                'date' => $date,
                'session_id' => $sessionId,
                'students' => $students
            ], 'Dormitory students retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch dormitory students: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/attendance/mark-boarding - Mark boarding attendance (roll call)
     * Expects: { dormitory_id, session_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkBoarding($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->forbidden('You are not allowed to mark boarding attendance');
            }

            $dormitoryId = $data['dormitory_id'] ?? null;
            $sessionId = $data['session_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy = $_SERVER['auth_user']['user_id'] ?? 1;

            if (!$dormitoryId || !$sessionId) {
                return $this->badRequest('Dormitory ID and Session ID are required');
            }
            if (empty($attendance)) {
                return $this->badRequest('No attendance data provided');
            }

            $created = 0;
            $updated = 0;
            $onPermission = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $status = $record['status'] ?? 'present';
                $notes = $record['notes'] ?? null;

                if (!$studentId)
                    continue;

                // Check for active permission
                $permQuery = $this->db->query(
                    "SELECT id FROM student_permissions 
                     WHERE student_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'",
                    [$studentId, $date]
                );
                $permission = $permQuery->fetch(\PDO::FETCH_ASSOC);
                $permissionId = $permission['id'] ?? null;

                // If has permission and not present, mark as 'permission' status
                if ($permissionId && $status !== 'present') {
                    $status = 'permission';
                    $onPermission++;
                }

                // Check existing
                $existsQuery = $this->db->query(
                    "SELECT id FROM boarding_attendance 
                     WHERE student_id = ? AND date = ? AND session_id = ? AND dormitory_id = ?",
                    [$studentId, $date, $sessionId, $dormitoryId]
                );
                $existing = $existsQuery->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $this->db->query(
                        "UPDATE boarding_attendance SET status = ?, check_time = CURTIME(), 
                         permission_id = ?, notes = ?, marked_by = ? WHERE id = ?",
                        [$status, $permissionId, $notes, $markedBy, $existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->query(
                        "INSERT INTO boarding_attendance 
                         (student_id, dormitory_id, date, session_id, status, check_time, 
                          permission_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, CURTIME(), ?, ?, ?, NOW())",
                        [$studentId, $dormitoryId, $date, $sessionId, $status, $permissionId, $notes, $markedBy]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created,
                'updated' => $updated,
                'on_permission' => $onPermission,
                'total' => $created + $updated,
                'dormitory_id' => $dormitoryId,
                'session_id' => $sessionId,
                'date' => $date
            ], 'Boarding attendance marked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to mark boarding attendance: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/boarding-summary - Get boarding attendance summary for a date
     */
    public function getBoardingSummary($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->forbidden('You are not allowed to access boarding attendance');
            }

            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $sql = "
                SELECT 
                    d.id as dormitory_id, d.name as dormitory_name, d.code,
                    ass.id as session_id, ass.name as session_name, ass.code as session_code,
                    COUNT(DISTINCT da.student_id) as total_students,
                    SUM(CASE WHEN ba.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN ba.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN ba.status = 'permission' THEN 1 ELSE 0 END) as on_permission,
                    SUM(CASE WHEN ba.status = 'sick_bay' THEN 1 ELSE 0 END) as sick_bay
                FROM dormitories d
                LEFT JOIN dormitory_assignments da ON d.id = da.dormitory_id AND da.status = 'active'
                CROSS JOIN attendance_sessions ass
                LEFT JOIN boarding_attendance ba ON da.student_id = ba.student_id 
                    AND ba.date = ? AND ba.session_id = ass.id AND ba.dormitory_id = d.id
                WHERE d.status = 'active' AND ass.session_type = 'boarding' AND ass.status = 'active'
                GROUP BY d.id, d.name, d.code, ass.id, ass.name, ass.code
                ORDER BY d.name, ass.display_order
            ";

            $result = $this->db->query($sql, [$date]);
            $summary = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'date' => $date,
                'summary' => $summary
            ], 'Boarding summary retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch boarding summary: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STUDENT PERMISSION METHODS
    // ========================================================================

    /**
     * GET /api/attendance/permission-types - Get all student permission types
     */
    public function getPermissionTypes($id = null, $data = [], $segments = [])
    {
        try {
            $sql = "SELECT * FROM student_permission_types WHERE status = 'active' ORDER BY name";
            $result = $this->db->query($sql);
            $types = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($types, 'Permission types retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch permission types: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/permissions - Get student permissions (optionally filtered)
     */
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        try {
            $studentId = $data['student_id'] ?? $_GET['student_id'] ?? null;
            $status = $data['status'] ?? $_GET['status'] ?? null;
            $active = $data['active'] ?? $_GET['active'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $search = trim((string) ($data['search'] ?? $_GET['search'] ?? ''));
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? null;
            $permissionTypeId = $data['permission_type_id'] ?? $_GET['permission_type_id'] ?? null;

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->forbidden('You are not allowed to access permissions for this class');
            }
            if ($streamScope['empty']) {
                return $this->success([], 'Permissions retrieved');
            }

            $sql = "
                SELECT sp.*, 
                       CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       s.admission_no,
                       c.name as class_name,
                       cs.stream_name,
                       st.name as student_type,
                       st.code as student_type_code,
                       spt.name as permission_type_name, spt.code as permission_type_code,
                       spt.applies_to,
                       COALESCE(
                           CONCAT(approver_staff.first_name, ' ', approver_staff.last_name),
                           CONCAT(approver_user.first_name, ' ', approver_user.last_name),
                           approver_user.username
                       ) as approved_by_name
                FROM student_permissions sp
                JOIN students s ON sp.student_id = s.id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN student_types st ON st.id = s.student_type_id
                JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                LEFT JOIN users approver_user ON sp.approved_by = approver_user.id
                LEFT JOIN staff approver_staff ON approver_staff.user_id = approver_user.id
                WHERE 1=1 {$streamScope['sql']}
            ";
            $params = $streamScope['params'];

            if ($studentId) {
                $sql .= " AND sp.student_id = ?";
                $params[] = $studentId;
            }
            if ($status) {
                $sql .= " AND sp.status = ?";
                $params[] = $status;
            }
            if ($active === 'true' || $active === '1') {
                $sql .= " AND CURDATE() BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'";
            }
            if ($permissionTypeId) {
                $sql .= " AND sp.permission_type_id = ?";
                $params[] = (int) $permissionTypeId;
            }
            if ($dateFrom && $dateTo) {
                if ($dateFrom > $dateTo) {
                    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
                }
                $sql .= " AND sp.end_date >= ? AND sp.start_date <= ?";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            } elseif ($dateFrom) {
                $sql .= " AND sp.end_date >= ?";
                $params[] = $dateFrom;
            } elseif ($dateTo) {
                $sql .= " AND sp.start_date <= ?";
                $params[] = $dateTo;
            }
            if ($search !== '') {
                $sql .= " AND (
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?
                    OR s.admission_no LIKE ?
                    OR sp.reason LIKE ?
                    OR spt.name LIKE ?
                )";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY sp.created_at DESC LIMIT 250";

            $result = $this->db->query($sql, $params);
            $permissions = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($permissions, 'Permissions retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch permissions: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/attendance/permissions - Create a new student permission/exeat
     */
    public function postPermissions($id = null, $data = [], $segments = [])
    {
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
            if ($expectedReturn) {
                $expectedReturn = str_replace('T', ' ', (string) $expectedReturn);
            }

            if (!$studentId || !$permissionTypeId || !$startDate || !$endDate || $reason === '') {
                return $this->badRequest('Missing required fields');
            }

            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $permissionType = $this->db->query(
                "SELECT id, code, name, max_days, applies_to, status
                 FROM student_permission_types
                 WHERE id = ? AND status = 'active'
                 LIMIT 1",
                [$permissionTypeId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$permissionType) {
                return $this->badRequest('Invalid permission type');
            }

            $student = $this->db->query(
                "SELECT s.id, st.code AS student_type_code, st.name AS student_type
                 FROM students s
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE s.id = ?
                 LIMIT 1",
                [$studentId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->badRequest('Invalid student');
            }

            $studentTypeCode = strtoupper((string) ($student['student_type_code'] ?? ''));
            $isBoarder = str_contains($studentTypeCode, 'BOARD');
            if (($permissionType['applies_to'] ?? 'all') === 'boarders_only' && !$isBoarder) {
                return $this->badRequest('This permission type is only available for boarders');
            }
            if (($permissionType['applies_to'] ?? 'all') === 'day_only' && $isBoarder) {
                return $this->badRequest('This permission type is only available for day scholars');
            }

            if (!empty($permissionType['max_days'])) {
                $daysRequested = (new \DateTime($startDate))->diff(new \DateTime($endDate))->days + 1;
                if ($daysRequested > (int) $permissionType['max_days']) {
                    return $this->badRequest('Request exceeds the maximum allowed duration for this permission type');
                }
            }

            $this->db->query(
                "INSERT INTO student_permissions 
                 (student_id, permission_type_id, start_date, start_time, end_date, end_time, reason,
                  parent_id, requested_by_parent, expected_return, notes, status, requested_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())",
                [
                    $studentId,
                    $permissionTypeId,
                    $startDate,
                    $startTime ?: null,
                    $endDate,
                    $endTime ?: null,
                    $reason,
                    $parentId ?: null,
                    filter_var($requestedByParent, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                    $expectedReturn ?: null,
                    $notes ?: null,
                ]
            );

            $permissionId = $this->db->getConnection()->lastInsertId();

            return $this->success(['id' => $permissionId], 'Permission request created');
        } catch (\Exception $e) {
            return $this->error('Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/attendance/permissions/{id} - Approve/reject permission
     */
    public function putPermissions($id = null, $data = [], $segments = [])
    {
        try {
            if (!$id) {
                return $this->badRequest('Permission ID is required');
            }

            $existing = $this->db->query(
                "SELECT * FROM student_permissions WHERE id = ? LIMIT 1",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$existing) {
                return $this->notFound('Permission request not found');
            }

            $status = $data['status'] ?? null;
            $approvedBy = $_SERVER['auth_user']['user_id'] ?? 1;
            $rejectionReason = trim((string) ($data['rejection_reason'] ?? $data['comments'] ?? ''));

            $editableFields = [
                'permission_type_id',
                'start_date',
                'start_time',
                'end_date',
                'end_time',
                'reason',
                'parent_id',
                'requested_by_parent',
                'expected_return',
                'notes',
            ];

            $hasEditPayload = false;
            foreach ($editableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $hasEditPayload = true;
                    break;
                }
            }

            if ($hasEditPayload && !$status) {
                if (($existing['status'] ?? 'pending') !== 'pending') {
                    return $this->badRequest('Only pending requests can be edited');
                }

                $updates = [];
                $params = [];
                foreach ($editableFields as $field) {
                    if (!array_key_exists($field, $data)) {
                        continue;
                    }
                    $updates[] = "{$field} = ?";
                    if ($field === 'requested_by_parent') {
                        $params[] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                    } elseif ($field === 'expected_return' && !empty($data[$field])) {
                        $params[] = str_replace('T', ' ', (string) $data[$field]);
                    } else {
                        $params[] = $data[$field] === '' ? null : $data[$field];
                    }
                }

                if (empty($updates)) {
                    return $this->badRequest('No editable fields supplied');
                }

                $params[] = $id;
                $this->db->query(
                    "UPDATE student_permissions SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?",
                    $params
                );

                return $this->success(['id' => $id], 'Permission request updated');
            }

            if (!in_array($status, ['approved', 'rejected', 'cancelled', 'completed'], true)) {
                return $this->badRequest('Invalid status');
            }

            $sql = "UPDATE student_permissions SET status = ?, updated_at = NOW()";
            $params = [$status];

            if (in_array($status, ['approved', 'rejected'], true)) {
                $sql .= ", approved_by = ?, approved_at = NOW()";
                $params[] = $approvedBy;
            }

            if ($status === 'rejected') {
                $sql .= ", rejection_reason = ?";
                $params[] = $rejectionReason !== '' ? $rejectionReason : null;
            }

            if (!empty($data['notes'])) {
                $sql .= ", notes = ?";
                $params[] = $data['notes'];
            }

            $sql .= " WHERE id = ?";
            $params[] = $id;

            $this->db->query($sql, $params);

            return $this->success(['id' => $id, 'status' => $status], 'Permission updated');
        } catch (\Exception $e) {
            return $this->error('Failed to update permission: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // STAFF ATTENDANCE METHODS (ENHANCED)
    // ========================================================================

    /**
     * GET /api/attendance/staff-today - Get staff attendance for today with leave/off-day info
     */
    public function getStaffToday($id = null, $data = [], $segments = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $scope = $this->getAccessibleStaffScope();

            if ($scope['restricted'] && empty($scope['staff_ids'])) {
                return $this->success([
                    'date' => $date,
                    'summary' => [
                        'total' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'on_leave' => 0,
                        'off_day' => 0,
                        'not_marked' => 0,
                    ],
                    'staff' => [],
                ], 'Staff attendance retrieved');
            }

            $where = ["st.status = 'active'"];
            $params = [$date, $date, $date];

            if ($departmentId) {
                $where[] = "st.department_id = ?";
                $params[] = (int) $departmentId;
            }

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
                $where[] = "st.id IN ({$placeholders})";
                $params = array_merge($params, array_map('intval', $scope['staff_ids']));
            }

            $sql = "
                SELECT 
                    st.id AS staff_id,
                    st.id,
                    st.staff_no,
                    st.first_name,
                    st.last_name,
                    st.position,
                    d.name AS department_name,
                    d.name AS department,
                    sa.status AS attendance_status,
                    sa.status AS current_status,
                    sa.check_in_time,
                    sa.check_out_time,
                    sl.id as leave_id, lt.name as leave_type, sl.status as leave_status,
                    sdr.id as duty_id, sdt.id as duty_type_id, sdt.name as duty_type, sdt.code as duty_type_code,
                    CASE 
                        WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 'on_leave'
                        WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 'off_day'
                        WHEN sa.status IS NULL THEN 'not_marked'
                        ELSE sa.status
                    END as effective_status,
                    CASE WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 1 ELSE 0 END as is_on_leave,
                    CASE WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 1 ELSE 0 END as is_off_day
                FROM staff st
                LEFT JOIN departments d ON st.department_id = d.id
                LEFT JOIN staff_attendance sa ON st.id = sa.staff_id AND sa.date = ?
                LEFT JOIN staff_leaves sl ON st.id = sl.staff_id 
                    AND ? BETWEEN sl.start_date AND sl.end_date
                LEFT JOIN leave_types lt ON sl.leave_type_id = lt.id
                LEFT JOIN staff_duty_roster sdr ON st.id = sdr.staff_id AND sdr.date = ?
                LEFT JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
            ";
            $sql .= " WHERE " . implode(' AND ', $where);
            $sql .= " ORDER BY d.name, st.last_name, st.first_name";

            $result = $this->db->query($sql, $params);
            $staff = $result->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate summary
            $summary = [
                'total' => count($staff),
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0
            ];
            foreach ($staff as $s) {
                $status = $s['effective_status'] ?? 'not_marked';
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
            }

            return $this->success([
                'date' => $date,
                'summary' => $summary,
                'staff' => $staff
            ], 'Staff attendance retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch staff attendance: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/attendance/mark-staff - Mark staff attendance
     * Expects: { date, attendance: [{ staff_id, status, check_in_time, check_out_time, notes }] }
     */
    public function postMarkStaff($id = null, $data = [], $segments = [])
    {
        try {
            $date        = $data['date']       ?? date('Y-m-d');
            $shift       = $data['shift']      ?? 'full_day';
            $attendance  = $data['attendance'] ?? [];
            $markedBy    = $_SERVER['auth_user']['user_id'] ?? 1;
            $scope       = $this->getAccessibleStaffScope();

            if (empty($attendance))                                   return $this->badRequest('No attendance data provided');
            if ($scope['restricted'] && empty($scope['staff_ids']))   return $this->forbidden('You are not allowed to mark staff attendance');

            // Resolve academic year for this date
            $yearRow = $this->db->query(
                "SELECT id FROM academic_years WHERE YEAR(?) = year_code LIMIT 1", [$date]
            )->fetch(\PDO::FETCH_ASSOC);
            $academicYearId = $yearRow['id'] ?? null;

            $dayName = date('l', strtotime($date)); // Monday, Tuesday, ...

            $created = 0; $updated = 0; $autoMarked = 0;

            foreach ($attendance as $record) {
                $staffId  = $record['staff_id'] ?? null;
                $status   = strtolower((string)($record['status'] ?? 'present'));
                $checkIn  = $record['check_in_time']  ?? null;
                $checkOut = $record['check_out_time'] ?? null;
                $notes    = $record['notes']          ?? null;

                if (!$staffId) continue;
                if (!$this->isStaffInScope((int)$staffId, $scope)) {
                    return $this->forbidden('Not allowed to mark attendance for one or more staff members');
                }
                if (!in_array($status, ['present','absent','late'], true)) $status = 'present';

                // Get staff expected check-in for late detection
                $staffRow = $this->db->query(
                    "SELECT work_start_time, late_threshold_minutes FROM staff WHERE id = ?", [$staffId]
                )->fetch(\PDO::FETCH_ASSOC);
                $expectedCheckIn = $staffRow['work_start_time'] ?? null;
                $lateThresh      = (int)($staffRow['late_threshold_minutes'] ?? 15);

                // Auto-detect late: if check_in provided and > expected + threshold → override to late
                if ($status === 'present' && $checkIn && $expectedCheckIn) {
                    $expectedPlus = date('H:i:s', strtotime($expectedCheckIn) + $lateThresh * 60);
                    if ($checkIn > $expectedPlus) $status = 'late';
                }

                // Check if on approved leave — record it (don't skip silently)
                $leave = $this->db->query(
                    "SELECT id FROM staff_leaves WHERE staff_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'",
                    [$staffId, $date]
                )->fetch(\PDO::FETCH_ASSOC);

                // Check off-day: BOTH duty roster AND recurring pattern
                $rosterOff = $this->db->query(
                    "SELECT sdr.id FROM staff_duty_roster sdr
                     JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id
                     WHERE sdr.staff_id = ? AND sdr.date = ? AND sdt.code IN ('OFF','WEEKEND_OFF')",
                    [$staffId, $date]
                )->fetch(\PDO::FETCH_ASSOC);

                $patternOff = $this->db->query(
                    "SELECT id FROM staff_off_day_patterns
                     WHERE staff_id = ? AND day_of_week = ? AND is_off = 1
                       AND ? >= effective_from AND (effective_to IS NULL OR ? <= effective_to)",
                    [$staffId, $dayName, $date, $date]
                )->fetch(\PDO::FETCH_ASSOC);

                $isOffDay  = ($rosterOff || $patternOff);
                $isOnLeave = (bool)$leave;

                // Override status for leave/off-day unless explicitly overridden by marker
                $absenceReason = null;
                if ($isOnLeave && $record['status'] !== 'present') {
                    // Marker hasn't explicitly set present (unusual override) — auto-mark as absent+leave
                    $status        = 'absent';
                    $absenceReason = 'leave';
                    $autoMarked++;
                } elseif ($isOffDay && $record['status'] !== 'present') {
                    $status        = 'absent';
                    $absenceReason = 'off_day';
                    $autoMarked++;
                } elseif ($status === 'absent') {
                    $absenceReason = $record['absence_reason'] ?? 'unexcused';
                    // If no reason given and no leave, flag as unauthorized
                    if (!$absenceReason || !in_array($absenceReason, ['leave','sick','off_day','unauthorized','other'])) {
                        $absenceReason = 'unauthorized';
                    }
                }

                // Check existing (unique: staff_id + date + shift)
                $existing = $this->db->query(
                    "SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ? AND shift = ?",
                    [$staffId, $date, $shift]
                )->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $this->db->query(
                        "UPDATE staff_attendance
                         SET status = ?, check_in_time = ?, check_out_time = ?,
                             absence_reason = ?, leave_id = ?,
                             notes = ?, marked_by = ?
                         WHERE id = ?",
                        [$status, $checkIn, $checkOut, $absenceReason,
                         $isOnLeave ? $leave['id'] : null, $notes, $markedBy, $existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->query(
                        "INSERT INTO staff_attendance
                         (staff_id, date, academic_year_id, shift, status,
                          check_in_time, expected_check_in, check_out_time,
                          absence_reason, leave_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                        [
                            $staffId, $date, $academicYearId, $shift, $status,
                            $checkIn, $expectedCheckIn, $checkOut,
                            $absenceReason, $isOnLeave ? $leave['id'] : null,
                            $notes, $markedBy,
                        ]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created, 'updated' => $updated,
                'auto_marked' => $autoMarked,
                'total' => $created + $updated,
                'date' => $date, 'shift' => $shift,
            ], 'Staff attendance marked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to mark staff attendance: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/staff-register-context?date=X&department_id=Y
     * Returns full pre-computed register for a date: who is on leave, off, duty, expected time.
     */
    public function getStaffRegisterContext($id = null, $data = [], $segments = [])
    {
        try {
            $date         = $_GET['date']          ?? date('Y-m-d');
            $departmentId = $_GET['department_id'] ?? null;
            $shift        = $_GET['shift']         ?? 'full_day';

            $dayName   = date('l', strtotime($date));
            $dayNumber = (int)date('N', strtotime($date)); // 1=Mon 7=Sun

            // Calendar check
            $calEntry = $this->db->query(
                "SELECT day_type, title, affects_day_students, affects_boarders FROM school_calendar WHERE date = ?",
                [$date]
            )->fetch(\PDO::FETCH_ASSOC);

            $dayType   = $calEntry['day_type'] ?? ($dayNumber >= 6 ? 'weekend' : 'school_day');
            $eventName = $calEntry['title']    ?? ($dayNumber === 7 ? 'Sunday' : ($dayNumber === 6 ? 'Saturday' : 'Working Day'));

            $isWorkingDay = !in_array($dayType, ['public_holiday','school_holiday']);
            // On public holidays, only staff on explicit duty roster work
            $onlyRosterStaff = in_array($dayType, ['public_holiday']);

            $where  = ["s.status = 'active'"];
            $params = [$date, $date, $date, $dayName, $date, $date];

            if ($departmentId) { $where[] = "s.department_id = ?"; $params[] = (int)$departmentId; }

            $sql = "SELECT
              s.id AS staff_id, s.staff_no,
              CONCAT(s.first_name,' ',s.last_name) AS staff_name,
              s.position, s.work_start_time, s.late_threshold_minutes,
              d.id AS department_id, d.name AS department_name,
              sc.category_name AS staff_category,
              -- Attendance record for this date+shift
              sa.id AS attendance_id, sa.status AS marked_status,
              sa.shift AS marked_shift,
              sa.check_in_time, sa.expected_check_in, sa.check_out_time,
              sa.absence_reason, sa.notes AS attendance_notes,
              -- Leave
              sl.id AS leave_id, lt.name AS leave_type,
              sl.start_date AS leave_start, sl.end_date AS leave_end,
              CONCAT(rs.first_name,' ',rs.last_name) AS relief_staff_name,
              -- Duty roster assignment today
              sdr.id AS roster_id, sdt.code AS duty_code, sdt.name AS duty_name,
              sdr.shift AS duty_shift, sdr.start_time AS duty_start, sdr.end_time AS duty_end,
              sdr.location AS duty_location,
              -- Off-day pattern
              sop.id AS pattern_off_id,
              -- Effective status
              CASE
                WHEN sl.id IS NOT NULL AND sl.status='approved'     THEN 'on_leave'
                WHEN sdr.id IS NOT NULL AND sdt.code IN ('OFF','WEEKEND_OFF') THEN 'off_day'
                WHEN sop.id IS NOT NULL                             THEN 'off_day'
                WHEN sa.status IS NOT NULL                          THEN sa.status
                ELSE 'not_marked'
              END AS effective_status,
              -- Can be manually overridden? (no, if on leave/off_day)
              CASE
                WHEN sl.id IS NOT NULL AND sl.status='approved'     THEN 0
                WHEN sdr.id IS NOT NULL AND sdt.code IN ('OFF','WEEKEND_OFF') THEN 0
                WHEN sop.id IS NOT NULL                             THEN 0
                ELSE 1
              END AS can_mark
            FROM staff s
            LEFT JOIN departments d ON d.id = s.department_id
            LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
            LEFT JOIN staff_attendance sa ON sa.staff_id = s.id AND sa.date = ? AND sa.shift = '$shift'
            LEFT JOIN staff_leaves sl ON sl.staff_id = s.id
              AND ? BETWEEN sl.start_date AND sl.end_date AND sl.status = 'approved'
            LEFT JOIN leave_types lt ON lt.id = sl.leave_type_id
            LEFT JOIN staff rs ON rs.id = sl.relief_staff_id
            LEFT JOIN staff_duty_roster sdr ON sdr.staff_id = s.id AND sdr.date = ?
            LEFT JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id
            LEFT JOIN staff_off_day_patterns sop ON sop.staff_id = s.id
              AND sop.day_of_week = ?
              AND sop.is_off = 1
              AND ? >= sop.effective_from
              AND (sop.effective_to IS NULL OR ? <= sop.effective_to)
            WHERE " . implode(' AND ', $where) . "
            ORDER BY d.name, s.last_name, s.first_name";

            $staff = $this->db->query($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);

            // Summary
            $summary = ['total'=>0,'present'=>0,'absent'=>0,'late'=>0,
                        'on_leave'=>0,'off_day'=>0,'not_marked'=>0,'on_duty'=>0];
            foreach ($staff as $s) {
                $summary['total']++;
                $st = $s['effective_status'] ?? 'not_marked';
                if (isset($summary[$st])) $summary[$st]++;
                if ($s['duty_code'] && !in_array($s['duty_code'], ['OFF','WEEKEND_OFF'])) $summary['on_duty']++;
            }

            // Available shifts (for boarding shift selector)
            $shifts = ['full_day' => 'Full Day (08:00–17:00)'];
            if ($dayNumber >= 6 || !$isWorkingDay) {
                $shifts = [
                    'morning'   => 'Morning Shift (06:00–14:00)',
                    'afternoon' => 'Afternoon Shift (14:00–22:00)',
                    'night'     => 'Night Shift (22:00–06:00)',
                    'full_day'  => 'Full Day',
                ];
            }

            return $this->success([
                'date'            => $date,
                'day_name'        => $dayName,
                'day_type'        => $dayType,
                'event_name'      => $eventName,
                'is_working_day'  => $isWorkingDay,
                'only_roster'     => $onlyRosterStaff,
                'available_shifts' => $shifts,
                'current_shift'   => $shift,
                'staff'           => $staff,
                'summary'         => $summary,
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ========================================================================
    // STAFF DUTY AND REPORT METHODS
    // ========================================================================

    /**
     * GET /api/attendance/duty-types - Get all staff duty types
     */
    public function getDutyTypes($id = null, $data = [], $segments = [])
    {
        try {
            $sql = "SELECT id, code as duty_code, name as duty_name, description, color,
                           (status = 'active') AS is_active
                    FROM staff_duty_types
                    WHERE status = 'active'
                    ORDER BY name";
            $result = $this->db->query($sql);
            $dutyTypes = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success($dutyTypes, 'Duty types retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch duty types: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/staff-report - Get staff attendance report with aggregates
     * Params: date_from, date_to, department_id, duty_type_id, status
     */
    public function getStaffReport($id = null, $data = [], $segments = [])
    {
        try {
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $dutyTypeId = $data['duty_type_id'] ?? $_GET['duty_type_id'] ?? null;
            $statusFilter = $data['status'] ?? $_GET['status'] ?? null;

            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $scope = $this->getAccessibleStaffScope();
            if ($scope['restricted'] && empty($scope['staff_ids'])) {
                return $this->success(
                    $this->buildEmptyStaffReport($dateFrom, $dateTo),
                    'Staff report generated'
                );
            }

            $where = ["s.status = 'active'"];
            $params = [];

            if ($departmentId) {
                $where[] = "s.department_id = ?";
                $params[] = (int) $departmentId;
            }

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
                $where[] = "s.id IN ({$placeholders})";
                $params = array_merge($params, array_map('intval', $scope['staff_ids']));
            }

            if ($dutyTypeId) {
                $where[] = "EXISTS (
                    SELECT 1
                    FROM staff_duty_roster sdr_filter
                    WHERE sdr_filter.staff_id = s.id
                      AND sdr_filter.date BETWEEN ? AND ?
                      AND sdr_filter.duty_type_id = ?
                )";
                $params[] = $dateFrom;
                $params[] = $dateTo;
                $params[] = (int) $dutyTypeId;
            }

            $staffSql = "
                SELECT
                    s.id AS staff_id,
                    s.first_name,
                    s.last_name,
                    s.staff_no,
                    s.position,
                    s.user_id,
                    d.name AS department_name
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.last_name, s.first_name
            ";

            $staffRows = $this->db->query($staffSql, $params)->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($staffRows)) {
                return $this->success(
                    $this->buildEmptyStaffReport($dateFrom, $dateTo),
                    'Staff report generated'
                );
            }

            $staffIds = array_map('intval', array_column($staffRows, 'staff_id'));
            $dateKeys = $this->buildDateRangeArray($dateFrom, $dateTo);
            $staffPlaceholders = implode(',', array_fill(0, count($staffIds), '?'));

            $attendanceRows = $this->db->query(
                "SELECT
                    sa.staff_id,
                    sa.date,
                    sa.status,
                    sa.check_in_time,
                    sa.check_out_time,
                    sa.notes,
                    sa.absence_reason,
                    sa.duty_type_id,
                    sdt.name AS duty_type,
                    sdt.code AS duty_type_code
                 FROM staff_attendance sa
                 LEFT JOIN staff_duty_types sdt ON sa.duty_type_id = sdt.id
                 WHERE sa.date BETWEEN ? AND ?
                   AND sa.staff_id IN ({$staffPlaceholders})",
                array_merge([$dateFrom, $dateTo], $staffIds)
            )->fetchAll(\PDO::FETCH_ASSOC);

            $leaveRows = $this->db->query(
                "SELECT
                    sl.staff_id,
                    sl.start_date,
                    sl.end_date,
                    sl.reason,
                    lt.name AS leave_type
                 FROM staff_leaves sl
                 LEFT JOIN leave_types lt ON sl.leave_type_id = lt.id
                 WHERE sl.status = 'approved'
                   AND sl.end_date >= ?
                   AND sl.start_date <= ?
                   AND sl.staff_id IN ({$staffPlaceholders})",
                array_merge([$dateFrom, $dateTo], $staffIds)
            )->fetchAll(\PDO::FETCH_ASSOC);

            $rosterRows = $this->db->query(
                "SELECT
                    sdr.staff_id,
                    sdr.date,
                    sdt.id AS duty_type_id,
                    sdt.name AS duty_type,
                    sdt.code AS duty_type_code
                 FROM staff_duty_roster sdr
                 JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
                 WHERE sdr.date BETWEEN ? AND ?
                   AND sdr.staff_id IN ({$staffPlaceholders})",
                array_merge([$dateFrom, $dateTo], $staffIds)
            )->fetchAll(\PDO::FETCH_ASSOC);

            $attendanceMap = [];
            foreach ($attendanceRows as $row) {
                $attendanceMap[(int) $row['staff_id']][$row['date']] = $row;
            }

            $leaveMap = [];
            foreach ($leaveRows as $row) {
                $leaveMap[(int) $row['staff_id']][] = $row;
            }

            $rosterMap = [];
            foreach ($rosterRows as $row) {
                $rosterMap[(int) $row['staff_id']][$row['date']] = $row;
            }

            $staffData = [];
            foreach ($staffRows as $staff) {
                $staffId = (int) $staff['staff_id'];
                $dailyStatuses = [];
                $present = 0;
                $absent = 0;
                $late = 0;
                $onLeave = 0;
                $offDays = 0;
                $notMarked = 0;
                $primaryDutyType = null;

                foreach ($dateKeys as $date) {
                    $attendance = $attendanceMap[$staffId][$date] ?? null;
                    $roster = $rosterMap[$staffId][$date] ?? null;
                    $leave = $this->findActiveLeaveForDate($leaveMap[$staffId] ?? [], $date);

                    $effectiveStatus = 'not_marked';
                    $statusLabel = 'Not Marked';

                    if ($leave) {
                        $effectiveStatus = 'on_leave';
                        $statusLabel = 'On Leave';
                        $onLeave++;
                    } elseif ($roster && in_array($roster['duty_type_code'], ['OFF', 'WEEKEND_OFF'], true)) {
                        $effectiveStatus = 'off_day';
                        $statusLabel = 'Off Day';
                        $offDays++;
                    } elseif ($attendance) {
                        $effectiveStatus = $attendance['status'] ?: 'not_marked';
                        $statusLabel = ucfirst((string) $effectiveStatus);

                        if ($effectiveStatus === 'present') {
                            $present++;
                        } elseif ($effectiveStatus === 'absent') {
                            $absent++;
                        } elseif ($effectiveStatus === 'late') {
                            $late++;
                        } else {
                            $notMarked++;
                        }
                    } else {
                        $notMarked++;
                    }

                    if ($roster && !in_array($roster['duty_type_code'], ['OFF', 'WEEKEND_OFF'], true)) {
                        $primaryDutyType = $roster['duty_type'];
                    } elseif (!$primaryDutyType && !empty($attendance['duty_type'])) {
                        $primaryDutyType = $attendance['duty_type'];
                    }

                    $dailyStatuses[] = [
                        'date' => $date,
                        'status' => $effectiveStatus,
                        'label' => $statusLabel,
                        'duty_type' => $roster['duty_type'] ?? $attendance['duty_type'] ?? null,
                        'duty_type_code' => $roster['duty_type_code'] ?? $attendance['duty_type_code'] ?? null,
                        'leave_type' => $leave['leave_type'] ?? null,
                        'check_in_time' => $attendance['check_in_time'] ?? null,
                        'check_out_time' => $attendance['check_out_time'] ?? null,
                        'notes' => $attendance['notes'] ?? null,
                    ];
                }

                $staffRow = [
                    'staff_id' => $staffId,
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'staff_no' => $staff['staff_no'],
                    'position' => $staff['position'],
                    'department_name' => $staff['department_name'],
                    'duty_type' => $primaryDutyType ?: 'General',
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'on_leave' => $onLeave,
                    'off_days' => $offDays,
                    'not_marked' => $notMarked,
                    'daily_statuses' => $dailyStatuses,
                ];

                if ($this->matchesStaffStatusFilter($staffRow, $statusFilter)) {
                    $staffData[] = $staffRow;
                }
            }

            $reportMeta = $this->summarizeStaffReportRows($staffData, $dateKeys);

            return $this->success([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'filters' => [
                    'department_id' => $departmentId ? (int) $departmentId : null,
                    'duty_type_id' => $dutyTypeId ? (int) $dutyTypeId : null,
                    'status' => $statusFilter ?: null,
                ],
                'staff' => $staffData,
                'summary' => $reportMeta['summary'],
                'trend' => $reportMeta['trend'],
                'daily_breakdown' => array_map(static function (array $row): array {
                    return [
                        'staff_id' => $row['staff_id'],
                        'staff_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                        'staff_no' => $row['staff_no'] ?? null,
                        'department_name' => $row['department_name'] ?? null,
                        'duty_type' => $row['duty_type'] ?? null,
                        'statuses' => $row['daily_statuses'] ?? [],
                    ];
                }, $staffData),
            ], 'Staff report generated');
        } catch (\Exception $e) {
            return $this->error('Failed to generate staff report: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // SCHOOL CALENDAR METHODS
    // ========================================================================

    /**
     * GET /api/attendance/calendar - Get school calendar for a date range
     */
    public function getCalendar($id = null, $data = [], $segments = [])
    {
        try {
            $startDate = $data['start_date'] ?? $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $data['end_date'] ?? $_GET['end_date'] ?? date('Y-m-t');

            $sql = "
                SELECT * FROM school_calendar 
                WHERE date BETWEEN ? AND ?
                ORDER BY date
            ";

            $result = $this->db->query($sql, [$startDate, $endDate]);
            $calendar = $result->fetchAll(\PDO::FETCH_ASSOC);

            return $this->success([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'events' => $calendar
            ], 'Calendar retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch calendar: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/is-school-day - Check if a date is a school day
     */
    public function getIsSchoolDay($id = null, $data = [], $segments = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            // Check calendar
            $result = $this->db->query(
                "SELECT day_type, title FROM school_calendar WHERE date = ?",
                [$date]
            );
            $calendarEntry = $result->fetch(\PDO::FETCH_ASSOC);

            $isSchoolDay = true;
            $reason = 'Regular school day';

            if ($calendarEntry) {
                $dayType = $calendarEntry['day_type'];
                if (in_array($dayType, ['public_holiday', 'school_holiday', 'weekend'])) {
                    $isSchoolDay = false;
                    $reason = $calendarEntry['title'];
                }
            } else {
                // Check if weekend
                $dayOfWeek = date('N', strtotime($date)); // 6=Sat, 7=Sun
                if ($dayOfWeek == 7) { // Sunday only - Saturday may have classes
                    $isSchoolDay = false;
                    $reason = 'Sunday';
                }
            }

            return $this->success([
                'date' => $date,
                'is_school_day' => $isSchoolDay,
                'day_type' => $calendarEntry['day_type'] ?? 'school_day',
                'reason' => $reason,
                'calendar_event' => $calendarEntry ? [
                    'event_type' => $calendarEntry['day_type'],
                    'event_name' => $calendarEntry['title'],
                ] : null,
            ], 'School day check completed');
        } catch (\Exception $e) {
            return $this->error('Failed to check school day: ' . $e->getMessage());
        }
    }

    private function normalizeRoleName($roleName): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $roleName)), '_');
    }

    private function getCurrentRoleNames(): array
    {
        $roles = [];

        if (!empty($this->user['role_names']) && is_array($this->user['role_names'])) {
            foreach ($this->user['role_names'] as $roleName) {
                if ($roleName) {
                    $roles[] = $this->normalizeRoleName($roleName);
                }
            }
        }

        if (!empty($this->user['roles']) && is_array($this->user['roles'])) {
            foreach ($this->user['roles'] as $role) {
                if (is_array($role) && !empty($role['name'])) {
                    $roles[] = $this->normalizeRoleName($role['name']);
                } elseif (is_string($role) && $role !== '') {
                    $roles[] = $this->normalizeRoleName($role);
                }
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    private function currentUserHasAnyRole(array $roleNames): bool
    {
        $currentRoles = $this->getCurrentRoleNames();
        if (empty($currentRoles)) {
            return false;
        }

        $normalizedTargets = array_map([$this, 'normalizeRoleName'], $roleNames);
        return count(array_intersect($currentRoles, $normalizedTargets)) > 0;
    }

    private function getCurrentUserId(): ?int
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $userId ? (int) $userId : null;
    }

    private function getCurrentStaffId(): ?int
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return null;
        }

        $result = $this->db->query(
            "SELECT id FROM staff WHERE user_id = ? AND status = 'active' LIMIT 1",
            [$userId]
        );

        $staffId = $result->fetchColumn();
        return $staffId ? (int) $staffId : null;
    }

    private function getCurrentAcademicYearId(): ?int
    {
        $result = $this->db->query(
            "SELECT id
             FROM academic_years
             WHERE is_current = 1 OR status = 'active'
             ORDER BY is_current DESC, id DESC
             LIMIT 1"
        );

        $yearId = $result->fetchColumn();
        return $yearId ? (int) $yearId : null;
    }

    private function userCanManageAllAttendance(): bool
    {
        return $this->currentUserHasAnyRole([
            'System Administrator',
            'Director',
            'School Administrator',
            'Headteacher',
            'Deputy Head - Academic',
            'Deputy Head - Discipline',
        ]);
    }

    private function userCanAccessBoardingAttendance(): bool
    {
        return $this->userCanManageAllAttendance()
            || $this->currentUserHasAnyRole([
                'Boarding Master',
            ]);
    }

    private function getAccessibleClassScope(): array
    {
        $scope = [
            'restricted' => !$this->userCanManageAllAttendance(),
            'staff_id' => $this->getCurrentStaffId(),
            'class_ids' => [],
            'stream_ids' => [],
        ];

        if (!$scope['restricted']) {
            return $scope;
        }

        if (!$scope['staff_id']) {
            return $scope;
        }

        $academicYearId = $this->getCurrentAcademicYearId();
        if (!$academicYearId) {
            return $scope;
        }

        $classRows = $this->db->query(
            "SELECT DISTINCT class_id
             FROM staff_class_assignments
             WHERE staff_id = ?
               AND academic_year_id = ?
               AND status = 'active'
               AND role = 'class_teacher'
               AND class_id IS NOT NULL",
            [$scope['staff_id'], $academicYearId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        $classIds = array_map('intval', array_column($classRows, 'class_id'));

        if (empty($classIds)) {
            $streamRows = $this->db->query(
                "SELECT DISTINCT id AS stream_id, class_id
                 FROM class_streams
                 WHERE teacher_id = ?
                   AND status = 'active'",
                [$scope['staff_id']]
            )->fetchAll(\PDO::FETCH_ASSOC);

            $scope['stream_ids'] = array_map('intval', array_column($streamRows, 'stream_id'));
            $scope['class_ids'] = array_values(array_unique(array_map('intval', array_column($streamRows, 'class_id'))));
            return $scope;
        }

        $scope['class_ids'] = array_values(array_unique($classIds));

        $placeholders = implode(',', array_fill(0, count($scope['class_ids']), '?'));
        $streamRows = $this->db->query(
            "SELECT id AS stream_id
             FROM class_streams
             WHERE status = 'active'
               AND class_id IN ({$placeholders})",
            $scope['class_ids']
        )->fetchAll(\PDO::FETCH_ASSOC);

        $scope['stream_ids'] = array_values(array_unique(array_map('intval', array_column($streamRows, 'stream_id'))));
        return $scope;
    }

    private function getAccessibleStaffScope(): array
    {
        $canViewAll = $this->userHasAny(
            [
                'attendance_staff_view_all',
                'attendance_staff_create',
                'attendance_staff_submit',
                'attendance_staff_approve',
                'attendance_staff_generate',
                'attendance_staff_export',
                'attendance_staff_delete',
            ],
            [],
            [
                'System Administrator',
                'Director',
                'School Administrator',
                'Headteacher',
                'Deputy Headteacher',
                'Human Resources Officer',
            ]
        );

        $staffId = $this->getCurrentStaffId();

        if ($canViewAll) {
            return [
                'restricted' => false,
                'staff_id' => $staffId,
                'staff_ids' => [],
            ];
        }

        return [
            'restricted' => true,
            'staff_id' => $staffId,
            'staff_ids' => $staffId ? [(int) $staffId] : [],
        ];
    }

    private function isStaffInScope(?int $staffId, array $scope): bool
    {
        if (!$staffId) {
            return false;
        }

        if (!$scope['restricted']) {
            return true;
        }

        return in_array((int) $staffId, $scope['staff_ids'], true);
    }

    private function buildStreamScopeClause(?int $requestedStreamId, array $scope, string $column = 's.stream_id'): array
    {
        if ($requestedStreamId) {
            if ($scope['restricted'] && !in_array((int) $requestedStreamId, $scope['stream_ids'], true)) {
                return [
                    'forbidden' => true,
                    'empty' => false,
                    'sql' => '',
                    'params' => [],
                ];
            }

            return [
                'forbidden' => false,
                'empty' => false,
                'sql' => " AND {$column} = ?",
                'params' => [(int) $requestedStreamId],
            ];
        }

        if (!$scope['restricted']) {
            return [
                'forbidden' => false,
                'empty' => false,
                'sql' => '',
                'params' => [],
            ];
        }

        if (empty($scope['stream_ids'])) {
            return [
                'forbidden' => false,
                'empty' => true,
                'sql' => '',
                'params' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($scope['stream_ids']), '?'));

        return [
            'forbidden' => false,
            'empty' => false,
            'sql' => " AND {$column} IN ({$placeholders})",
            'params' => array_map('intval', $scope['stream_ids']),
        ];
    }

    private function buildDateRangeArray(string $dateFrom, string $dateTo): array
    {
        $dates = [];
        $current = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    private function findActiveLeaveForDate(array $leaveRows, string $date): ?array
    {
        foreach ($leaveRows as $leave) {
            if (($leave['start_date'] ?? null) <= $date && ($leave['end_date'] ?? null) >= $date) {
                return $leave;
            }
        }

        return null;
    }

    private function matchesStaffStatusFilter(array $row, ?string $statusFilter): bool
    {
        if (!$statusFilter) {
            return true;
        }

        switch ($statusFilter) {
            case 'present':
                return (int) ($row['present'] ?? 0) > 0;
            case 'absent':
                return (int) ($row['absent'] ?? 0) > 0;
            case 'late':
                return (int) ($row['late'] ?? 0) > 0;
            case 'on_leave':
                return (int) ($row['on_leave'] ?? 0) > 0;
            case 'off_day':
                return (int) ($row['off_days'] ?? 0) > 0;
            case 'not_marked':
                return (int) ($row['not_marked'] ?? 0) > 0;
            default:
                return true;
        }
    }

    private function buildEmptyAcademicSummary(string $dateFrom, string $dateTo, ?int $streamId = null): array
    {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'stream_id' => $streamId,
            'students' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'permission' => 0,
                'total_days' => 0,
                'average_attendance' => 0,
                'student_count' => 0,
            ],
            'trend' => [],
            'low_attendance' => [],
        ];
    }

    private function buildEmptyStaffReport(string $dateFrom, string $dateTo): array
    {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filters' => [
                'department_id' => null,
                'duty_type_id' => null,
                'status' => null,
            ],
            'staff' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
                'total_days' => 0,
                'average_attendance' => 0,
                'staff_count' => 0,
            ],
            'trend' => [],
            'daily_breakdown' => [],
        ];
    }

    private function summarizeStaffReportRows(array $rows, array $dateKeys): array
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'on_leave' => 0,
            'off_day' => 0,
            'not_marked' => 0,
            'total_days' => 0,
            'average_attendance' => 0,
            'staff_count' => count($rows),
        ];

        $trendMap = [];
        foreach ($dateKeys as $date) {
            $trendMap[$date] = [
                'date' => $date,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
            ];
        }

        foreach ($rows as $row) {
            $summary['present'] += (int) ($row['present'] ?? 0);
            $summary['absent'] += (int) ($row['absent'] ?? 0);
            $summary['late'] += (int) ($row['late'] ?? 0);
            $summary['on_leave'] += (int) ($row['on_leave'] ?? 0);
            $summary['off_day'] += (int) ($row['off_days'] ?? 0);
            $summary['not_marked'] += (int) ($row['not_marked'] ?? 0);

            foreach (($row['daily_statuses'] ?? []) as $daily) {
                $date = $daily['date'] ?? null;
                $status = $daily['status'] ?? 'not_marked';
                if ($date && isset($trendMap[$date]) && array_key_exists($status, $trendMap[$date])) {
                    $trendMap[$date][$status]++;
                }
            }
        }

        $summary['total_days'] = $summary['present'] + $summary['absent'] + $summary['late'];
        if ($summary['total_days'] > 0) {
            $summary['average_attendance'] = round((($summary['present'] + $summary['late']) / $summary['total_days']) * 100, 1);
        }

        return [
            'summary' => $summary,
            'trend' => array_values($trendMap),
        ];
    }

    private function applyAcademicStatusFilter(array $students, ?string $statusFilter): array
    {
        if (!$statusFilter) {
            return $students;
        }

        return array_values(array_filter($students, static function (array $student) use ($statusFilter) {
            switch ($statusFilter) {
                case 'present':
                    return ($student['present'] ?? 0) > 0;
                case 'absent':
                    return ($student['absent'] ?? 0) > 0;
                case 'late':
                    return ($student['late'] ?? 0) > 0;
                case 'permission':
                    return ($student['permission'] ?? 0) > 0;
                default:
                    return true;
            }
        }));
    }

    private function summarizeAcademicRows(array $students): array
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'permission' => 0,
            'total_days' => 0,
            'average_attendance' => 0,
            'student_count' => count($students),
        ];

        foreach ($students as $student) {
            $summary['present'] += (int) ($student['present'] ?? 0);
            $summary['absent'] += (int) ($student['absent'] ?? 0);
            $summary['late'] += (int) ($student['late'] ?? 0);
            $summary['permission'] += (int) ($student['permission'] ?? 0);
            $summary['total_days'] += (int) ($student['total_days'] ?? 0);
        }

        if ($summary['total_days'] > 0) {
            $summary['average_attendance'] = round(($summary['present'] / $summary['total_days']) * 100, 1);
        }

        return $summary;
    }

    /**
     * Unified API response handler (matches other controllers)
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            // Handle successResponse/errorResponse format: {status, message, type, code, data}
            if (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['message'] ?? 'Operation failed');
                }
            }
            // Handle legacy {success: true/false, data, message} format
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

    // ========================================================================
    // REGISTER CONTEXT — calendar + session awareness for a given date
    // ========================================================================

    /**
     * GET /api/attendance/register-context?date=2026-04-26&stream_id=3&session_id=1
     *
     * Returns everything the frontend needs to decide:
     * - Is today a school day? A boarding day?
     * - Which sessions apply today?
     * - How many students already marked?
     * - What is the current academic term + year?
     */
    public function getRegisterContext($id = null, $data = [], $segments = [])
    {
        try {
            $date     = $_GET['date']       ?? date('Y-m-d');
            $streamId = $_GET['stream_id']  ?? null;
            $sessionId = $_GET['session_id'] ?? null;

            $dayName    = date('l', strtotime($date));      // Monday, Tuesday...
            $dayNumber  = (int)date('N', strtotime($date)); // 1=Mon, 7=Sun

            // 1. Check school_calendar for this date
            $calEntry = $this->db->query(
                "SELECT day_type, title, affects_day_students, affects_boarders, requires_attendance, term_id
                 FROM school_calendar WHERE date = ?",
                [$date]
            )->fetch(\PDO::FETCH_ASSOC);

            // Determine day type if not in calendar
            $dayType = $calEntry['day_type'] ?? ($dayNumber === 7 ? 'weekend' : ($dayNumber === 6 ? 'weekend' : 'school_day'));
            $eventName = $calEntry['title'] ?? ($dayNumber === 7 ? 'Sunday' : ($dayNumber === 6 ? 'Saturday' : 'Regular School Day'));

            // 2. Is it a class register day? Boarding day?
            $isClassDay    = !in_array($dayType, ['public_holiday','school_holiday','weekend']) && $dayNumber < 7;
            // Saturday: only if school has Saturday classes configured
            if ($dayNumber === 6) {
                $swc = $this->db->query(
                    "SELECT saturday_classes FROM school_week_config WHERE academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)"
                )->fetchColumn();
                $isClassDay = (bool)$swc;
            }
            $isBoardingDay = !in_array($dayType, ['school_holiday']); // boarding runs every day except holiday breaks

            $blockedReason = null;
            if (!$isClassDay) {
                if ($dayType === 'public_holiday')  $blockedReason = "Public Holiday: $eventName — class register not required";
                elseif ($dayType === 'school_holiday') $blockedReason = "School Holiday/Break: $eventName — class register closed";
                elseif ($dayType === 'weekend')     $blockedReason = $dayNumber === 7 ? "Sunday — class register not required" : "Saturday — no scheduled classes";
                else $blockedReason = $eventName;
            }

            // 3. Get applicable sessions for this date
            $sessions = $this->db->query(
                "SELECT id, code, name, session_type, applies_to, start_time, end_time, applicable_days
                 FROM attendance_sessions WHERE status = 'active' ORDER BY display_order"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $applicableSessions = array_values(array_filter($sessions, function ($s) use ($dayName, $isClassDay, $isBoardingDay) {
                $days = json_decode($s['applicable_days'] ?? '[]', true) ?: [];
                if (!in_array($dayName, $days)) return false; // session doesn't run today

                if ($s['session_type'] === 'academic' && !$isClassDay) return false;
                if ($s['session_type'] === 'boarding'  && !$isBoardingDay) return false;
                return true;
            }));

            // 4. Resolve current term + year for this date
            $termRow = $this->_resolveTermForDate($date);

            // 5. Count existing marks (class register and boarding separately)
            $existingCounts = ['class' => 0, 'boarding' => 0, 'activity' => 0];
            if ($streamId) {
                $classId = $this->db->query("SELECT class_id FROM class_streams WHERE id = ?", [$streamId])->fetchColumn();
                $markParams = $sessionId ? [$date, $classId, $sessionId] : [$date, $classId];
                $markRows = $this->db->query(
                    "SELECT register_type, COUNT(DISTINCT student_id) AS cnt
                     FROM student_attendance
                     WHERE date = ? AND class_id = ?" . ($sessionId ? " AND session_id = ?" : "") . "
                     GROUP BY register_type",
                    $markParams
                )->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($markRows as $r) $existingCounts[$r['register_type']] = (int)$r['cnt'];
            }

            // 6. Total students in the stream
            $totalStudents = $streamId
                ? (int)$this->db->query("SELECT COUNT(*) FROM students WHERE stream_id = ? AND status='active'", [$streamId])->fetchColumn()
                : 0;

            return $this->success([
                'date'               => $date,
                'day_name'           => $dayName,
                'day_number'         => $dayNumber,
                'day_type'           => $dayType,
                'event_name'         => $eventName,
                'is_class_day'       => $isClassDay,
                'is_boarding_day'    => $isBoardingDay,
                'blocked_reason'     => $blockedReason,
                'affects_day_students' => (bool)($calEntry['affects_day_students'] ?? 1),
                'affects_boarders'   => (bool)($calEntry['affects_boarders'] ?? 1),
                'applicable_sessions' => $applicableSessions,
                'current_term'       => $termRow,
                'existing_marks'     => $existingCounts,
                'total_students'     => $totalStudents,
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/attendance/student-history-by-year/{student_id}
     * Returns attendance records grouped by academic year → term
     * with clear differentiation even if student repeated a class
     */
    public function getStudentHistoryByYear($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($segments[0] ?? null);
        if (!$studentId) return $this->error('student_id required');

        try {
            $rows = $this->db->query(
                "SELECT
                   sa.academic_year_id,
                   ay.year_code,
                   ay.year_name,
                   sa.term_id,
                   at2.term_number,
                   at2.name   AS term_name,
                   sa.class_id,
                   c.name     AS class_name,
                   sa.register_type,
                   sa.date,
                   sa.status,
                   sa.absence_reason,
                   sa.session_id,
                   ass.name   AS session_name,
                   ass.session_type
                 FROM student_attendance sa
                 LEFT JOIN academic_years     ay  ON ay.id  = sa.academic_year_id
                 LEFT JOIN academic_terms     at2 ON at2.id = sa.term_id
                 LEFT JOIN classes            c   ON c.id   = sa.class_id
                 LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
                 WHERE sa.student_id = ?
                 ORDER BY sa.date ASC, sa.session_id ASC",
                [$studentId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Group by year → term → register_type → summary
            $grouped = [];
            foreach ($rows as $r) {
                $yk  = $r['year_code'] ?? 'unknown';
                $tk  = $r['term_id']   ?? 0;
                $rt  = $r['register_type'];
                if (!isset($grouped[$yk])) $grouped[$yk] = ['year_name' => $r['year_name'], 'year_code' => $r['year_code'], 'terms' => []];
                if (!isset($grouped[$yk]['terms'][$tk])) $grouped[$yk]['terms'][$tk] = [
                    'term_name' => $r['term_name'], 'term_number' => $r['term_number'],
                    'class_name' => $r['class_name'], 'records' => [],
                    'summary' => ['class' => ['present'=>0,'absent'=>0,'late'=>0,'total'=>0],
                                  'boarding' => ['present'=>0,'absent'=>0,'late'=>0,'total'=>0]],
                ];
                $grouped[$yk]['terms'][$tk]['records'][] = $r;
                if (isset($grouped[$yk]['terms'][$tk]['summary'][$rt])) {
                    $grouped[$yk]['terms'][$tk]['summary'][$rt][$r['status'] ?? 'absent']++;
                    $grouped[$yk]['terms'][$tk]['summary'][$rt]['total']++;
                }
            }

            // Convert nested terms to arrays
            foreach ($grouped as &$y) {
                ksort($y['terms']);
                $y['terms'] = array_values($y['terms']);
            }

            return $this->success([
                'student_id' => $studentId,
                'by_year'    => array_values($grouped),
                'total_rows' => count($rows),
            ]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

    /**
     * Look up which term and academic year a given date belongs to.
     * Falls back to current active term if date not in any term range.
     */
    private function _resolveTermForDate(string $date): array
    {
        $row = $this->db->query(
            "SELECT t.id AS term_id, t.academic_year_id AS year_id, t.name AS term_name,
                    t.term_number, ay.year_code
             FROM academic_terms t
             JOIN academic_years ay ON ay.id = t.academic_year_id
             WHERE ? BETWEEN t.start_date AND t.end_date
             LIMIT 1",
            [$date]
        )->fetch(\PDO::FETCH_ASSOC);

        if ($row) return $row;

        // Fallback: current active term
        return $this->db->query(
            "SELECT t.id AS term_id, t.academic_year_id AS year_id, t.name AS term_name,
                    t.term_number, ay.year_code
             FROM academic_terms t
             JOIN academic_years ay ON ay.id = t.academic_year_id
             WHERE ay.is_current = 1 AND t.status = 'current'
             LIMIT 1"
        )->fetch(\PDO::FETCH_ASSOC) ?: ['term_id' => null, 'year_id' => null, 'term_name' => null, 'term_number' => null, 'year_code' => null];
    }

}
