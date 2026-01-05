<?php

namespace App\API\Controllers;

use App\API\Modules\staff\StaffAPI;
use RuntimeException;
use Exception;

/**
 * StaffController - Explicit REST endpoints for Staff Management
 * 
 * Every method in StaffAPI has its own unique, explicit endpoint
 * Router calls methods with signature: methodName($id, $data, $segments)
 */
class StaffController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new StaffAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Staff API is running']);
    }

    /**
     * GET /api/staff/stats - Get staff statistics for dashboard
     * Returns: total staff count, present today, percentage
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get total staff count by type
            $totalResult = $db->query(
                "SELECT COUNT(*) as total FROM staff WHERE status = 'active'"
            );
            $totalRow = $totalResult->fetch();
            $totalStaff = (int) ($totalRow['total'] ?? 0);

            // Get teacher count
            $teachersResult = $db->query(
                "SELECT COUNT(*) as count FROM staff WHERE status = 'active' AND staff_type = 'teaching'"
            );
            $teachersRow = $teachersResult->fetch();
            $teacherCount = (int) ($teachersRow['count'] ?? 0);

            // Get staff present today
            $today = date('Y-m-d');
            $presentResult = $db->query(
                "SELECT COUNT(DISTINCT staff_id) as present FROM staff_attendance 
                 WHERE DATE(date) = ? AND status = 'present'",
                [$today]
            );
            $presentRow = $presentResult->fetch();
            $staffPresentToday = (int) ($presentRow['present'] ?? 0);

            // Department distribution
            $deptResult = $db->query(
                "SELECT d.name as department, COUNT(s.id) as count 
                 FROM staff s
                 LEFT JOIN departments d ON s.department_id = d.id
                 WHERE s.status = 'active'
                 GROUP BY s.department_id, d.name
                 ORDER BY count DESC"
            );
            $departmentDistribution = [];
            while ($row = $deptResult->fetch()) {
                $departmentDistribution[] = [
                    'department' => $row['department'] ?? 'Unassigned',
                    'count' => (int) $row['count']
                ];
            }

            $percentage = $totalStaff > 0 ? round(($staffPresentToday / $totalStaff) * 100, 2) : 100;

            return $this->success([
                'total_staff' => $totalStaff,
                'teacher_count' => $teacherCount,
                'staff_present_today' => $staffPresentToday,
                'attendance_percentage' => (float) $percentage,
                'department_distribution' => $departmentDistribution,
                'date' => $today,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Staff statistics');

        } catch (Exception $e) {
            return $this->error('Failed to fetch staff statistics: ' . $e->getMessage());
        }
    }


    // ==================== BASE CRUD OPERATIONS ====================

    /**
     * GET /api/staff - List all staff
     * GET /api/staff/{id} - Get specific staff member
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff - Create new staff member
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/{id} - Update staff member
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Staff ID is required for update');
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/staff/{id} - Delete staff member
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Staff ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ==================== STAFF INFORMATION ====================

    /**
     * GET /api/staff/profile/get - Get staff profile
     */
    public function getProfileGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->getProfile($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/schedule/get - Get staff schedule
     */
    public function getScheduleGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->getSchedule($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/departments/get - Get all departments
     */
    public function getDepartmentsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDepartments();
        return $this->handleResponse($result);
    }

    // ==================== ASSIGNMENT OPERATIONS ====================

    /**
     * POST /api/staff/assign/class - Assign staff to class
     */
    public function postAssignClass($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? null;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->assignClass($staffId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/assign/subject - Assign staff to subject
     */
    public function postAssignSubject($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? null;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->assignSubject($staffId, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/assignments/get - Get staff assignments
     */
    public function getAssignmentsGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $academicYearId = $data['academic_year_id'] ?? null;
        $includeHistory = $data['include_history'] ?? false;
        
        $result = $this->api->getStaffAssignments($staffId, $academicYearId, $includeHistory);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/assignments/current - Get current assignments
     */
    public function getAssignmentsCurrent($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->getCurrentAssignments($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/workload/get - Get staff workload
     */
    public function getWorkloadGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $academicYearId = $data['academic_year_id'] ?? null;
        
        $result = $this->api->getStaffWorkload($staffId, $academicYearId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/assignment/initiate - Initiate assignment workflow
     */
    public function postAssignmentInitiate($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $classStreamId = $data['class_stream_id'] ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        
        if (!$staffId || !$classStreamId || !$academicYearId) {
            return $this->badRequest('Staff ID, Class Stream ID, and Academic Year ID are required');
        }
        
        $result = $this->api->initiateAssignment($staffId, $classStreamId, $academicYearId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    // ==================== ATTENDANCE OPERATIONS ====================

    /**
     * GET /api/staff/attendance/get - Get staff attendance records
     */
    public function getAttendanceGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAttendance($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/attendance/mark - Mark staff attendance
     */
    public function postAttendanceMark($id = null, $data = [], $segments = [])
    {
        $result = $this->api->markAttendance($data);
        return $this->handleResponse($result);
    }

    // ==================== LEAVE MANAGEMENT ====================

    /**
     * GET /api/staff/leaves/list - List leave requests
     */
    public function getLeavesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLeaves($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/leaves/apply - Apply for leave
     */
    public function postLeavesApply($id = null, $data = [], $segments = [])
    {
        $result = $this->api->applyLeave($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/leaves/update-status - Update leave status
     */
    public function putLeavesUpdateStatus($id = null, $data = [], $segments = [])
    {
        $leaveId = $id ?? $data['leave_id'] ?? null;
        if (!$leaveId) {
            return $this->badRequest('Leave ID is required');
        }
        
        $result = $this->api->updateLeaveStatus($leaveId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/leave/initiate-request - Initiate leave request workflow
     */
    public function postLeaveInitiateRequest($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->initiateLeaveRequest($staffId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    // ==================== PAYROLL OPERATIONS ====================

    /**
     * GET /api/staff/payroll/payslip - View payslip
     */
    public function getPayrollPayslip($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $month = $data['month'] ?? date('m');
        $year = $data['year'] ?? date('Y');
        
        $result = $this->api->viewPayslip($staffId, $month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/history - Get payroll history
     */
    public function getPayrollHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        $result = $this->api->getPayrollHistory($staffId, $startDate, $endDate);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/allowances - View allowances
     */
    public function getPayrollAllowances($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->viewAllowances($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/deductions - View deductions
     */
    public function getPayrollDeductions($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->viewDeductions($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/loan-details - Get loan details
     */
    public function getPayrollLoanDetails($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $loanId = $data['loan_id'] ?? null;
        
        $result = $this->api->getLoanDetails($staffId, $loanId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/request-advance - Request salary advance
     */
    public function postPayrollRequestAdvance($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->requestAdvance($staffId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/apply-loan - Apply for loan
     */
    public function postPayrollApplyLoan($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->applyForLoan($staffId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-p9 - Download P9 form
     */
    public function getPayrollDownloadP9($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $year = $data['year'] ?? date('Y');
        
        $result = $this->api->downloadP9Form($staffId, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-payslip - Download payslip
     */
    public function getPayrollDownloadPayslip($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $month = $data['month'] ?? date('m');
        $year = $data['year'] ?? date('Y');
        
        $result = $this->api->downloadPayslip($staffId, $month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/export-history - Export payroll history
     */
    public function getPayrollExportHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        $result = $this->api->exportPayrollHistory($staffId, $startDate, $endDate);
        return $this->handleResponse($result);
    }

    // ==================== PERFORMANCE MANAGEMENT ====================

    /**
     * GET /api/staff/performance/review-history - Get review history
     */
    public function getPerformanceReviewHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $result = $this->api->getReviewHistory($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/performance/generate-report - Generate performance report
     */
    public function getPerformanceGenerateReport($id = null, $data = [], $segments = [])
    {
        $reviewId = $id ?? $data['review_id'] ?? null;
        if (!$reviewId) {
            return $this->badRequest('Review ID is required');
        }
        
        $result = $this->api->generatePerformanceReport($reviewId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/performance/academic-kpi-summary - Get academic KPI summary
     */
    public function getPerformanceAcademicKpiSummary($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->getUserId();
        $academicYearId = $data['academic_year_id'] ?? null;
        
        $result = $this->api->getAcademicKPISummary($staffId, $academicYearId);
        return $this->handleResponse($result);
    }

    // ==================== HELPER METHODS ====================

    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

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
