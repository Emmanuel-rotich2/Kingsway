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
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $result = $this->api->getClassAttendance($classId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStudentPercentage($studentId = null, $data = [], $segments = [])
    {
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $result = $this->api->getStudentAttendancePercentage($studentId, $termId, $yearId);
        return $this->handleResponse($result);
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
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $threshold = $segments[2] ?? $data['threshold'] ?? 0.2;
        $result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
        return $this->handleResponse($result);
    }


    public function getStaffHistory($staffId = null, $data = [], $segments = [])
    {
        $staffId = $staffId ?? ($data['staffId'] ?? null);
        $result = $this->api->getStaffAttendanceHistory($staffId);
        return $this->handleResponse($result);
    }

    public function getStaffSummary($staffId = null, $data = [], $segments = [])
    {
        $staffId = $staffId ?? ($data['staffId'] ?? null);
        $result = $this->api->getStaffAttendanceSummary($staffId);
        return $this->handleResponse($result);
    }

    public function getDepartmentAttendance($departmentId = null, $data = [], $segments = [])
    {
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $result = $this->api->getDepartmentAttendance($departmentId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStaffPercentage($staffId = null, $data = [], $segments = [])
    {
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getChronicStaffAbsentees($departmentId = null, $data = [], $segments = [])
    {
        $termId = $segments[0] ?? $data['termId'] ?? null;
        $yearId = $segments[1] ?? $data['yearId'] ?? null;
        $threshold = $segments[2] ?? $data['threshold'] ?? 0.2;
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
            $query = "
                SELECT c.id, c.name, cs.id as stream_id,
                       (SELECT COUNT(*) FROM students s WHERE s.stream_id = cs.id AND s.status = 'active') as student_count
                FROM classes c
                JOIN class_streams cs ON cs.class_id = c.id
                ORDER BY c.id
            ";
            $result = $this->db->query($query);
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

            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $query = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       st.name as student_type,
                       sa.status as attendance_status,
                       sa.id as attendance_id
                FROM students s
                LEFT JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN student_attendance sa ON sa.student_id = s.id AND sa.date = ?
                WHERE s.stream_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ";
            $result = $this->db->query($query, [$date, $streamId]);
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
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $termId = $data['term_id'] ?? 7; // Current term

            if (!$streamId) {
                return $this->badRequest('Missing stream_id');
            }
            if (empty($attendance)) {
                return $this->badRequest('No attendance data provided');
            }

            // Get class_id from stream
            $classQuery = $this->db->query("SELECT class_id FROM class_streams WHERE id = ?", [$streamId]);
            $classRow = $classQuery->fetch(\PDO::FETCH_ASSOC);
            $classId = $classRow['class_id'] ?? null;

            $markedBy = $_SESSION['user_id'] ?? 1;
            $created = 0;
            $updated = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $status = $record['status'] ?? 'present';

                if (!$studentId)
                    continue;
                if (!in_array($status, ['present', 'absent', 'late'])) {
                    $status = 'present';
                }

                // Check if record exists
                $existsQuery = $this->db->query(
                    "SELECT id FROM student_attendance WHERE student_id = ? AND date = ?",
                    [$studentId, $date]
                );
                $existing = $existsQuery->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    // Update
                    $this->db->query(
                        "UPDATE student_attendance SET status = ? WHERE id = ?",
                        [$status, $existing['id']]
                    );
                    $updated++;
                } else {
                    // Insert
                    $this->db->query(
                        "INSERT INTO student_attendance (student_id, date, status, class_id, term_id, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())",
                        [$studentId, $date, $status, $classId, $termId, $markedBy]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
                'date' => $date,
                'stream_id' => $streamId
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

            $sql = "
                SELECT s.id, s.admission_no, s.first_name, s.last_name,
                       st.name as student_type, st.code as student_type_code,
                       sa.status as attendance_status, sa.check_in_time, sa.notes,
                       sp.id as permission_id, spt.name as permission_type,
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

            if ($streamId) {
                $sql .= " AND s.stream_id = ?";
                $params[] = $streamId;
            }

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
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $termId = $data['term_id'] ?? 7;
            $markedBy = $_SESSION['user_id'] ?? 1;

            if (!$sessionId) {
                return $this->badRequest('Session ID is required');
            }
            if (empty($attendance)) {
                return $this->badRequest('No attendance data provided');
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

                // If absent but has permission, set absence_reason
                $absenceReason = null;
                if ($status === 'absent') {
                    if ($permissionId) {
                        $absenceReason = 'permission';
                        $excused++;
                    } else {
                        $absenceReason = 'unexcused';
                    }
                }

                // Check existing
                $existsQuery = $this->db->query(
                    "SELECT id FROM student_attendance WHERE student_id = ? AND date = ? AND session_id = ?",
                    [$studentId, $date, $sessionId]
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
                         (student_id, date, status, session_id, class_id, term_id, 
                          check_in_time, absence_reason, permission_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, CURTIME(), ?, ?, ?, ?, NOW())",
                        [
                            $studentId,
                            $date,
                            $status,
                            $sessionId,
                            $classId,
                            $termId,
                            $absenceReason,
                            $permissionId,
                            $notes,
                            $markedBy
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

    // ========================================================================
    // BOARDING ATTENDANCE METHODS
    // ========================================================================

    /**
     * GET /api/attendance/dormitories - Get all dormitories
     */
    public function getDormitories($id = null, $data = [], $segments = [])
    {
        try {
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
            $dormitoryId = $data['dormitory_id'] ?? null;
            $sessionId = $data['session_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy = $_SESSION['user_id'] ?? 1;

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

            $sql = "
                SELECT sp.*, 
                       CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       s.admission_no,
                       spt.name as permission_type_name, spt.code as permission_type_code,
                       CONCAT(staff.first_name, ' ', staff.last_name) as approved_by_name
                FROM student_permissions sp
                JOIN students s ON sp.student_id = s.id
                JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                LEFT JOIN staff ON sp.approved_by = staff.id
                WHERE 1=1
            ";
            $params = [];

            if ($studentId) {
                $sql .= " AND sp.student_id = ?";
                $params[] = $studentId;
            }
            if ($status) {
                $sql .= " AND sp.status = ?";
                $params[] = $status;
            }
            if ($active === 'true' || $active === '1') {
                $sql .= " AND CURDATE() BETWEEN sp.start_date AND sp.end_date";
            }

            $sql .= " ORDER BY sp.created_at DESC LIMIT 100";

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
            $studentId = $data['student_id'] ?? null;
            $permissionTypeId = $data['permission_type_id'] ?? null;
            $startDate = $data['start_date'] ?? null;
            $endDate = $data['end_date'] ?? null;
            $reason = $data['reason'] ?? null;
            $parentId = $data['parent_id'] ?? null;
            $requestedByParent = $data['requested_by_parent'] ?? false;

            if (!$studentId || !$permissionTypeId || !$startDate || !$endDate || !$reason) {
                return $this->badRequest('Missing required fields');
            }

            $this->db->query(
                "INSERT INTO student_permissions 
                 (student_id, permission_type_id, start_date, end_date, reason, 
                  parent_id, requested_by_parent, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
                [$studentId, $permissionTypeId, $startDate, $endDate, $reason, $parentId, $requestedByParent ? 1 : 0]
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

            $status = $data['status'] ?? null;
            $rejectionReason = $data['rejection_reason'] ?? null;
            $approvedBy = $_SESSION['user_id'] ?? 1;

            if (!in_array($status, ['approved', 'rejected', 'cancelled'])) {
                return $this->badRequest('Invalid status');
            }

            $sql = "UPDATE student_permissions SET status = ?, approved_by = ?, approved_at = NOW()";
            $params = [$status, $approvedBy];

            if ($status === 'rejected' && $rejectionReason) {
                $sql .= ", rejection_reason = ?";
                $params[] = $rejectionReason;
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

            $sql = "
                SELECT 
                    st.id, st.staff_no, st.first_name, st.last_name, st.position,
                    d.name as department,
                    sa.status as attendance_status, sa.check_in_time, sa.check_out_time,
                    sl.id as leave_id, lt.name as leave_type, sl.status as leave_status,
                    sdr.id as duty_id, sdt.name as duty_type,
                    CASE 
                        WHEN sl.id IS NOT NULL AND sl.status = 'approved' THEN 'on_leave'
                        WHEN sdt.code IN ('OFF', 'WEEKEND_OFF') THEN 'off_day'
                        WHEN sa.status IS NULL THEN 'not_marked'
                        ELSE sa.status
                    END as effective_status
                FROM staff st
                LEFT JOIN departments d ON st.department_id = d.id
                LEFT JOIN staff_attendance sa ON st.id = sa.staff_id AND sa.date = ?
                LEFT JOIN staff_leaves sl ON st.id = sl.staff_id 
                    AND ? BETWEEN sl.start_date AND sl.end_date
                LEFT JOIN leave_types lt ON sl.leave_type_id = lt.id
                LEFT JOIN staff_duty_roster sdr ON st.id = sdr.staff_id AND sdr.date = ?
                LEFT JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
                WHERE st.status = 'active'
                ORDER BY d.name, st.last_name, st.first_name
            ";

            $result = $this->db->query($sql, [$date, $date, $date]);
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
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy = $_SESSION['user_id'] ?? 1;

            if (empty($attendance)) {
                return $this->badRequest('No attendance data provided');
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($attendance as $record) {
                $staffId = $record['staff_id'] ?? null;
                $status = $record['status'] ?? 'present';
                $checkIn = $record['check_in_time'] ?? null;
                $checkOut = $record['check_out_time'] ?? null;
                $notes = $record['notes'] ?? null;

                if (!$staffId)
                    continue;

                // Check if staff is on approved leave
                $leaveQuery = $this->db->query(
                    "SELECT id FROM staff_leaves 
                     WHERE staff_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'",
                    [$staffId, $date]
                );
                $leave = $leaveQuery->fetch(\PDO::FETCH_ASSOC);

                $leaveId = null;
                $absenceReason = null;

                if ($leave) {
                    $leaveId = $leave['id'];
                    $absenceReason = 'leave';
                    $skipped++;
                    continue; // Skip marking - they're on leave
                }

                // Check if off-day
                $offDayQuery = $this->db->query(
                    "SELECT sdr.id FROM staff_duty_roster sdr
                     JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
                     WHERE sdr.staff_id = ? AND sdr.date = ? AND sdt.code IN ('OFF', 'WEEKEND_OFF')",
                    [$staffId, $date]
                );
                $offDay = $offDayQuery->fetch(\PDO::FETCH_ASSOC);

                if ($offDay) {
                    $absenceReason = 'off_day';
                    $skipped++;
                    continue; // Skip marking - it's their off day
                }

                // Check existing
                $existsQuery = $this->db->query(
                    "SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ?",
                    [$staffId, $date]
                );
                $existing = $existsQuery->fetch(\PDO::FETCH_ASSOC);

                if ($existing) {
                    $this->db->query(
                        "UPDATE staff_attendance SET status = ?, check_in_time = ?, 
                         check_out_time = ?, notes = ? WHERE id = ?",
                        [$status, $checkIn, $checkOut, $notes, $existing['id']]
                    );
                    $updated++;
                } else {
                    $this->db->query(
                        "INSERT INTO staff_attendance 
                         (staff_id, date, status, check_in_time, check_out_time, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
                        [$staffId, $date, $status, $checkIn, $checkOut, $notes, $markedBy]
                    );
                    $created++;
                }
            }

            return $this->success([
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'total' => $created + $updated,
                'date' => $date
            ], 'Staff attendance marked successfully');
        } catch (\Exception $e) {
            return $this->error('Failed to mark staff attendance: ' . $e->getMessage());
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
            $sql = "SELECT id, code as duty_code, name as duty_name, description, 
                           requires_location, is_active 
                    FROM staff_duty_types 
                    WHERE is_active = 1 
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

            // Build dynamic query
            $params = [$dateFrom, $dateTo];
            $whereClause = "";

            if ($departmentId) {
                $whereClause .= " AND s.department_id = ?";
                $params[] = $departmentId;
            }

            $sql = "
                SELECT 
                    s.id as staff_id,
                    s.first_name,
                    s.last_name,
                    s.staff_no,
                    d.name as department_name,
                    
                    -- Aggregate attendance counts
                    SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN sa.status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN sa.status = 'late' THEN 1 ELSE 0 END) as late,
                    
                    -- Count leave days
                    (SELECT COUNT(*) FROM staff_leaves sl 
                     WHERE sl.staff_id = s.id 
                     AND sl.status = 'approved'
                     AND sl.start_date <= ? 
                     AND sl.end_date >= ?
                    ) as on_leave,
                    
                    -- Count off days from roster
                    (SELECT COUNT(*) FROM staff_duty_roster sdr
                     JOIN staff_duty_types sdt ON sdr.duty_type_id = sdt.id
                     WHERE sdr.staff_id = s.id 
                     AND sdr.date BETWEEN ? AND ?
                     AND sdt.code IN ('OFF', 'WEEKEND_OFF')
                    ) as off_days,
                    
                    -- Get typical duty type
                    (SELECT sdt2.name FROM staff_duty_roster sdr2
                     JOIN staff_duty_types sdt2 ON sdr2.duty_type_id = sdt2.id
                     WHERE sdr2.staff_id = s.id
                     AND sdt2.code NOT IN ('OFF', 'WEEKEND_OFF')
                     ORDER BY sdr2.date DESC LIMIT 1
                    ) as duty_type
                    
                FROM staff s
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN staff_attendance sa ON s.id = sa.staff_id 
                    AND sa.date BETWEEN ? AND ?
                WHERE s.status = 'active' {$whereClause}
                GROUP BY s.id, s.first_name, s.last_name, s.staff_no, d.name
                ORDER BY s.first_name, s.last_name
            ";

            // Add extra params for subqueries
            $params = array_merge([$dateFrom, $dateTo], [$dateTo, $dateFrom], [$dateFrom, $dateTo], [$dateFrom, $dateTo]);
            if ($departmentId) {
                $params[] = $departmentId;
            }

            $result = $this->db->query($sql, $params);
            $staffData = $result->fetchAll(\PDO::FETCH_ASSOC);

            // Filter by status if specified
            if ($statusFilter && $statusFilter !== '') {
                $staffData = array_filter($staffData, function ($s) use ($statusFilter) {
                    if ($statusFilter === 'off_day') {
                        return ($s['off_days'] ?? 0) > 0;
                    } elseif ($statusFilter === 'on_leave') {
                        return ($s['on_leave'] ?? 0) > 0;
                    }
                    return true;
                });
                $staffData = array_values($staffData);
            }

            return $this->success($staffData, 'Staff report generated');
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
                'reason' => $reason
            ], 'School day check completed');
        } catch (\Exception $e) {
            return $this->error('Failed to check school day: ' . $e->getMessage());
        }
    }

    /**
     * Unified API response handler (matches other controllers)
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

}

