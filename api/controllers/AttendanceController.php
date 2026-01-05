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
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        try {
            $service = new \App\API\Services\DirectorAnalyticsService();
            $trends = $service->getAttendanceTrends();
            if (!is_array($trends)) {
                return $this->serverError('Attendance trends not available');
            }
            return $this->success(['data' => $trends], 'Attendance trends retrieved');
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

