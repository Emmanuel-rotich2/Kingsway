<?php

namespace App\API\Controllers;

use App\API\Modules\attendance\AttendanceAPI;

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

    // Student attendance endpoints
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
