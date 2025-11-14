<?php
namespace App\API\Controllers;

use App\API\Modules\staff\StaffAPI;
use Exception;

/**
 * StaffController - REST endpoints for all staff operations
 * Handles staff CRUD, assignments, attendance, leave, payroll, and performance
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class StaffController extends BaseController
{
    private StaffAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new StaffAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/staff - List all staff members
     * GET /api/staff/{id} - Get single staff member
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
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
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

    // ========================================
    // SECTION 2: Staff Information
    // ========================================

    /**
     * GET /api/staff/{id}/profile
     */
    public function getProfile($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getProfile($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/{id}/schedule
     */
    public function getSchedule($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getSchedule($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/departments
     */
    public function getDepartments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDepartments();
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Assignment Operations
    // ========================================

    /**
     * POST /api/staff/{id}/assign-class
     */
    public function postAssignClass($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->assignClass($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/{id}/assign-subject
     */
    public function postAssignSubject($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->assignSubject($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/assignments/get
     */
    public function getAssignmentsGet($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        $includeHistory = $data['include_history'] ?? false;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getStaffAssignments($staffId, $academicYearId, $includeHistory);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/assignments/current
     */
    public function getAssignmentsCurrent($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getCurrentAssignments($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/workload/get
     */
    public function getWorkloadGet($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getStaffWorkload($staffId, $academicYearId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/assignment/initiate
     */
    public function postAssignmentInitiate($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $classStreamId = $data['class_stream_id'] ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        $userId = $this->getCurrentUserId();
        
        if ($staffId === null || $classStreamId === null || $academicYearId === null) {
            return $this->badRequest('Staff ID, class stream ID, and academic year ID are required');
        }
        
        $result = $this->api->initiateAssignment($staffId, $classStreamId, $academicYearId, $userId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Attendance Operations
    // ========================================

    /**
     * GET /api/staff/attendance/get
     */
    public function getAttendanceGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAttendance($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/attendance/mark
     */
    public function postAttendanceMark($id = null, $data = [], $segments = [])
    {
        $result = $this->api->markAttendance($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Leave Management
    // ========================================

    /**
     * GET /api/staff/leaves/list
     */
    public function getLeavesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getLeaves($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/leaves/apply
     */
    public function postLeavesApply($id = null, $data = [], $segments = [])
    {
        $result = $this->api->applyLeave($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/leaves/{id}/update-status
     */
    public function putLeavesUpdateStatus($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Leave ID is required');
        }
        
        $result = $this->api->updateLeaveStatus($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/leave/initiate-request
     */
    public function postLeaveInitiateRequest($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $userId = $this->getCurrentUserId();
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->initiateLeaveRequest($staffId, $userId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Payroll Operations
    // ========================================

    /**
     * GET /api/staff/payroll/payslip
     */
    public function getPayrollPayslip($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $month = $data['month'] ?? null;
        $year = $data['year'] ?? null;
        
        if ($staffId === null || $month === null || $year === null) {
            return $this->badRequest('Staff ID, month, and year are required');
        }
        
        $result = $this->api->viewPayslip($staffId, $month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/history
     */
    public function getPayrollHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getPayrollHistory($staffId, $startDate, $endDate);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/allowances
     */
    public function getPayrollAllowances($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->viewAllowances($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/deductions
     */
    public function getPayrollDeductions($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->viewDeductions($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/loan-details
     */
    public function getPayrollLoanDetails($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $loanId = $data['loan_id'] ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getLoanDetails($staffId, $loanId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/request-advance
     */
    public function postPayrollRequestAdvance($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $userId = $this->getCurrentUserId();
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->requestAdvance($staffId, $userId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/apply-loan
     */
    public function postPayrollApplyLoan($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $userId = $this->getCurrentUserId();
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->applyForLoan($staffId, $userId, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-p9
     */
    public function getPayrollDownloadP9($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $year = $data['year'] ?? null;
        
        if ($staffId === null || $year === null) {
            return $this->badRequest('Staff ID and year are required');
        }
        
        $result = $this->api->downloadP9Form($staffId, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-payslip
     */
    public function getPayrollDownloadPayslip($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $month = $data['month'] ?? null;
        $year = $data['year'] ?? null;
        
        if ($staffId === null || $month === null || $year === null) {
            return $this->badRequest('Staff ID, month, and year are required');
        }
        
        $result = $this->api->downloadPayslip($staffId, $month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/export-history
     */
    public function getPayrollExportHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->exportPayrollHistory($staffId, $startDate, $endDate);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Performance Management
    // ========================================

    /**
     * GET /api/staff/performance/review-history
     */
    public function getPerformanceReviewHistory($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getReviewHistory($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/performance/generate-report
     */
    public function getPerformanceGenerateReport($id = null, $data = [], $segments = [])
    {
        $reviewId = $data['review_id'] ?? $id ?? null;
        
        if ($reviewId === null) {
            return $this->badRequest('Review ID is required');
        }
        
        $result = $this->api->generatePerformanceReport($reviewId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/performance/academic-kpi-summary
     */
    public function getPerformanceAcademicKpiSummary($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        $academicYearId = $data['academic_year_id'] ?? null;
        
        if ($staffId === null) {
            return $this->badRequest('Staff ID is required');
        }
        
        $result = $this->api->getAcademicKPISummary($staffId, $academicYearId);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
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

    /**
     * Route nested GET requests to appropriate methods
     */
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

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
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

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
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

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
