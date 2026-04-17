<?php

namespace App\API\Controllers;

use App\API\Modules\staff\StaffAPI;
use App\API\Modules\staff\StaffPayrollManager;
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
    private $payroll;

    public function __construct()
    {
        parent::__construct();
        $this->api = new StaffAPI();
        $this->payroll = new StaffPayrollManager();
    }

    public function index()
    {
        // For /staff/index, return list to match frontend expectations
        $result = $this->api->list($_GET ?? []);
        return $this->handleResponse($result);
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
                "SELECT COUNT(*) as count FROM staff WHERE status = 'active' AND staff_type_id = 1"
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
     * GET /api/staff/staff - Alias for base GET
     * GET /api/staff/staff/{id} - Alias for base GET with ID
     */
    public function getStaff($id = null, $data = [], $segments = [])
    {
        return $this->get($id, $data, $segments);
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
     * POST /api/staff/staff - Alias for base POST
     */
    public function postStaff($id = null, $data = [], $segments = [])
    {
        return $this->post($id, $data, $segments);
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
     * PUT /api/staff/staff/{id} - Alias for base PUT
     */
    public function putStaff($id = null, $data = [], $segments = [])
    {
        return $this->put($id, $data, $segments);
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

    /**
     * DELETE /api/staff/staff/{id} - Alias for base DELETE
     */
    public function deleteStaff($id = null, $data = [], $segments = [])
    {
        return $this->delete($id, $data, $segments);
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

    // ==================== STAFF CHILDREN (Fee Deductions) ====================

    /**
     * GET /api/staff/children-list?staff_id=X
     */
    public function getChildrenList($id = null, $data = [], $segments = [])
    {
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? $id ?? null;
        if (!$staffId) {
            return $this->badRequest('staff_id is required');
        }
        $result = $this->payroll->getStaffChildren($staffId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/children-add
     */
    public function postChildrenAdd($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? $id ?? null;
        if (!$staffId) {
            return $this->badRequest('staff_id is required');
        }
        $result = $this->payroll->addStaffChild($staffId, $data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/children-update/{id}
     */
    public function putChildrenUpdate($id = null, $data = [], $segments = [])
    {
        $childId = $id ?? $data['id'] ?? null;
        $staffId = $data['staff_id'] ?? null;
        if (!$staffId || !$childId) {
            return $this->badRequest('staff_id and child id are required');
        }
        $result = $this->payroll->updateStaffChild($staffId, $childId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/staff/children-remove/{id}?staff_id=X
     */
    public function deleteChildrenRemove($id = null, $data = [], $segments = [])
    {
        $childId = $id ?? $data['id'] ?? null;
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? null;
        if (!$staffId && $childId) {
            // Fallback: resolve staff_id from child record
            $stmt = $this->db->getConnection()->prepare("SELECT staff_id FROM staff_children WHERE id = ?");
            $stmt->execute([$childId]);
            $staffId = $stmt->fetchColumn() ?: null;
        }
        if (!$staffId || !$childId) {
            return $this->badRequest('staff_id and child id are required');
        }
        $result = $this->payroll->removeStaffChild($staffId, $childId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/children-fee-config
     */
    public function getChildrenFeeConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->payroll->getChildFeeConfig();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/children-calculate-deductions?staff_id=X&month=Y&year=Z
     */
    public function getChildrenCalculateDeductions($id = null, $data = [], $segments = [])
    {
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? $id ?? null;
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');
        if (!$staffId) {
            return $this->badRequest('staff_id is required');
        }
        $result = $this->payroll->calculateChildFeeDeductions($staffId, (int) $month, (int) $year);
        return $this->handleResponse($result);
    }

    // ==================== CONTRACT MANAGEMENT ====================

    /**
     * GET /api/staff/contracts/list
     */
    public function getContractsList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->api->listContracts($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/contracts/get/{id}
     */
    public function getContractsGet($id = null, $data = [], $segments = [])
    {
        $contractId = $id ?? $data['id'] ?? null;
        if (!$contractId) {
            return $this->badRequest('Contract ID is required');
        }
        $result = $this->api->getContract($contractId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/contracts/create
     */
    public function postContractsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createContract($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/contracts/update/{id}
     */
    public function putContractsUpdate($id = null, $data = [], $segments = [])
    {
        $contractId = $id ?? $data['id'] ?? null;
        if (!$contractId) {
            return $this->badRequest('Contract ID is required');
        }
        $result = $this->api->updateContract($contractId, $data);
        return $this->handleResponse($result);
    }

    // ==================== PAYROLL LISTING (SUMMARY VIEW) ====================

    /**
     * GET /api/staff/payroll/list
     */
    public function getPayrollList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->api->listPayroll($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/summary
     */
    public function getPayrollSummary($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->api->getPayrollSummary($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/detailed-payslip?staff_id=&month=&year=
     */
    public function getPayrollDetailedPayslip($id = null, $data = [], $segments = [])
    {
        $params  = array_merge($_GET, $data);
        $staffId = $id ?? $params['staff_id'] ?? null;
        $month   = (int) ($params['month'] ?? date('n'));
        $year    = (int) ($params['year']  ?? date('Y'));

        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $result = $this->api->generateDetailedPayslip((int) $staffId, $month, $year, $this->getUserId());
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
        // Fix double-nesting: StaffAPI already returns {status, data, status_code}
        // Don't wrap it again with $this->success()
        if (is_array($result)) {
            // If StaffAPI returns {status: 'success', data: ...}
            if (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    // Extract just the data portion, avoid double wrapping
                    return $this->success($result['data'] ?? null, 'Success');
                } else {
                    // Error from StaffAPI
                    return $this->badRequest($result['message'] ?? 'Operation failed');
                }
            }
            // Legacy format: {success: true, data: ...}
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
    // STAFF PROMOTIONS
    // ========================================================================

    /**
     * GET /api/staff/promotions - List all promotions
     */
    public function getPromotions($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();

            $where = ['1=1'];
            $params = [];
            if (!empty($data['staff_id'])) {
                $where[] = 'sp.staff_id=:sid';
                $params[':sid'] = (int)$data['staff_id'];
            }
            if (!empty($data['status'])) {
                $where[] = 'sp.status=:status';
                $params[':status'] = $data['status'];
            }

            $stmt = $db->query(
                "SELECT sp.*,
                        CONCAT(s.first_name,' ',s.last_name) AS staff_name,
                        s.staff_no,
                        fd.name AS from_department,
                        td.name AS to_department,
                        r.name AS approved_by_name,
                        c.name AS created_by_name
                 FROM staff_promotions sp
                 JOIN staff s ON s.id = sp.staff_id
                 LEFT JOIN departments fd ON fd.id = sp.from_department_id
                 LEFT JOIN departments td ON td.id = sp.to_department_id
                 LEFT JOIN staff r ON r.id = sp.approved_by
                 JOIN staff c ON c.id = sp.created_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY sp.created_at DESC
                 LIMIT 200",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/staff/promotions - Create a promotion
     */
    public function postPromotions($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();

            $staffId = (int)($data['staff_id'] ?? 0);
            if (!$staffId) return $this->badRequest('staff_id is required');

            $staff = $db->query("SELECT * FROM staff WHERE id=?", [$staffId])->fetch();
            if (!$staff) return $this->badRequest('Staff member not found');

            $effectiveDate = $data['effective_date'] ?? null;
            if (!$effectiveDate) return $this->badRequest('effective_date is required');

            $db->query(
                "INSERT INTO staff_promotions
                  (staff_id, promotion_type, from_position, to_position,
                   from_department_id, to_department_id, from_salary, to_salary,
                   effective_date, status, reason, letter_url, created_by)
                 VALUES (:sid, :ptype, :fpos, :tpos, :fdept, :tdept, :fsal, :tsal, :edate, 'pending', :reason, :lurl, :cby)",
                [
                    ':sid'   => $staffId,
                    ':ptype' => $data['promotion_type'] ?? 'substantive',
                    ':fpos'  => $staff['position'],
                    ':tpos'  => $data['to_position'] ?? $staff['position'],
                    ':fdept' => $staff['department_id'],
                    ':tdept' => $data['to_department_id'] ?? $staff['department_id'],
                    ':fsal'  => $staff['salary'],
                    ':tsal'  => isset($data['to_salary']) ? (float)$data['to_salary'] : null,
                    ':edate' => $effectiveDate,
                    ':reason'=> $data['reason'] ?? null,
                    ':lurl'  => $data['letter_url'] ?? null,
                    ':cby'   => $this->user['user_id'] ?? null,
                ]
            );
            return $this->created(['id' => (int)$db->lastInsertId()], 'Promotion submitted for approval');
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/staff/promotions/{id}/approve - Approve or reject a promotion
     */
    public function putPromotionsApprove($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();
            $promotionId = (int)($id ?? $data['id'] ?? 0);
            if (!$promotionId) return $this->badRequest('Promotion ID is required');

            $action = $data['action'] ?? '';
            if (!in_array($action, ['approve', 'reject'])) {
                return $this->badRequest('action must be approve or reject');
            }

            $promo = $db->query("SELECT * FROM staff_promotions WHERE id=?", [$promotionId])->fetch();
            if (!$promo) return $this->badRequest('Promotion not found');

            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $db->query(
                "UPDATE staff_promotions
                 SET status=:status, approved_by=:aby, approved_at=NOW(),
                     rejected_reason=:rj, updated_at=NOW()
                 WHERE id=:id",
                [
                    ':status' => $newStatus,
                    ':aby'    => $this->user['user_id'] ?? null,
                    ':rj'     => $action === 'reject' ? ($data['reason'] ?? null) : null,
                    ':id'     => $promotionId,
                ]
            );

            if ($action === 'approve') {
                $db->query(
                    "UPDATE staff SET position=:pos, salary=:sal, updated_at=NOW() WHERE id=:sid",
                    [':pos' => $promo['to_position'], ':sal' => $promo['to_salary'], ':sid' => $promo['staff_id']]
                );
                if ($promo['effective_date'] <= date('Y-m-d')) {
                    $db->query("UPDATE staff_promotions SET status='effective' WHERE id=?", [$promotionId]);
                }
            }

            return $this->success(null, "Promotion {$action}d");
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    // ========================================================================
    // STAFF OFFBOARDING / RETIREMENT
    // ========================================================================

    /**
     * GET /api/staff/offboarding - List all offboarding records
     */
    public function getOffboarding($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();

            $where = ['1=1'];
            $params = [];
            if (!empty($data['staff_id'])) {
                $where[] = 'so.staff_id=:sid';
                $params[':sid'] = (int)$data['staff_id'];
            }
            if (!empty($data['status'])) {
                $where[] = 'so.status=:status';
                $params[':status'] = $data['status'];
            }
            if (!empty($data['type'])) {
                $where[] = 'so.offboarding_type=:type';
                $params[':type'] = $data['type'];
            }

            $stmt = $db->query(
                "SELECT so.*,
                        CONCAT(s.first_name,' ',s.last_name) AS staff_name,
                        s.staff_no,
                        p.name AS processed_by_name,
                        c.name AS created_by_name
                 FROM staff_offboarding so
                 JOIN staff s ON s.id = so.staff_id
                 LEFT JOIN staff p ON p.id = so.processed_by
                 JOIN staff c ON c.id = so.created_by
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY so.created_at DESC
                 LIMIT 200",
                $params
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/staff/offboarding - Initiate offboarding
     */
    public function postOffboarding($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();

            $staffId = (int)($data['staff_id'] ?? 0);
            if (!$staffId) return $this->badRequest('staff_id is required');

            $staff = $db->query("SELECT * FROM staff WHERE id=?", [$staffId])->fetch();
            if (!$staff) return $this->badRequest('Staff member not found');

            $lastWorkingDay = $data['last_working_day'] ?? null;
            if (!$lastWorkingDay) return $this->badRequest('last_working_day is required');

            $db->query(
                "INSERT INTO staff_offboarding
                  (staff_id, offboarding_type, last_working_day,
                   exit_interview_date, exit_interview_notes,
                   asset_return_complete, clearance_form_complete, handover_report_complete,
                   final_pay_calculated, outstanding_leave_days, outstanding_salary,
                   leave_pay_amount, final_settlement_amount,
                   nssf_clearance, paye_clearance, documents_url,
                   notify_hr, notify_finance, notify_it, status, processed_by, created_by)
                 VALUES
                  (:sid, :otype, :lwd,
                   :eid, :ein, :arc, :cfc, :hrc, :fpc, :old, :osal, :lpa, :fsa,
                   :nssf, :paye, :doc, :nhr, :nfin, :nit, 'initiated', :pby, :cby)",
                [
                    ':sid'   => $staffId,
                    ':otype' => $data['offboarding_type'] ?? 'retirement',
                    ':lwd'   => $lastWorkingDay,
                    ':eid'   => $data['exit_interview_date'] ?? null,
                    ':ein'   => $data['exit_interview_notes'] ?? null,
                    ':arc'   => (int)($data['asset_return_complete'] ?? false),
                    ':cfc'   => (int)($data['clearance_form_complete'] ?? false),
                    ':hrc'   => (int)($data['handover_report_complete'] ?? false),
                    ':fpc'   => (int)($data['final_pay_calculated'] ?? false),
                    ':old'   => $data['outstanding_leave_days'] ?? null,
                    ':osal'  => $data['outstanding_salary'] ?? null,
                    ':lpa'   => $data['leave_pay_amount'] ?? null,
                    ':fsa'   => $data['final_settlement_amount'] ?? null,
                    ':nssf'  => (int)($data['nssf_clearance'] ?? false),
                    ':paye'  => (int)($data['paye_clearance'] ?? false),
                    ':doc'   => $data['documents_url'] ?? null,
                    ':nhr'   => (int)($data['notify_hr'] ?? true),
                    ':nfin'  => (int)($data['notify_finance'] ?? true),
                    ':nit'   => (int)($data['notify_it'] ?? false),
                    ':pby'   => $this->user['user_id'] ?? null,
                    ':cby'   => $this->user['user_id'] ?? null,
                ]
            );
            return $this->created(['id' => (int)$db->lastInsertId()], 'Offboarding initiated');
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/staff/offboarding/{id} - Update offboarding record
     */
    public function putOffboarding($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();
            $offId = (int)($id ?? $data['id'] ?? 0);
            if (!$offId) return $this->badRequest('Offboarding ID is required');

            $off = $db->query("SELECT * FROM staff_offboarding WHERE id=?", [$offId])->fetch();
            if (!$off) return $this->badRequest('Offboarding record not found');

            $allowed = [
                'exit_interview_date', 'exit_interview_notes',
                'asset_return_complete', 'clearance_form_complete',
                'handover_report_complete', 'final_pay_calculated',
                'outstanding_leave_days', 'outstanding_salary',
                'leave_pay_amount', 'final_settlement_amount',
                'nssf_clearance', 'paye_clearance',
                'documents_url', 'notify_hr', 'notify_finance', 'notify_it', 'status',
            ];

            $fields = [];
            $vals = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f = :$f";
                    $vals[":$f"] = $data[$f];
                }
            }

            if (!empty($fields)) {
                $fields[] = "updated_at = NOW()";
                $vals[':id'] = $offId;
                $db->query("UPDATE staff_offboarding SET " . implode(', ', $fields) . " WHERE id=:id", $vals);
            }

            if (($data['status'] ?? '') === 'completed') {
                $db->query("UPDATE staff SET status='inactive', updated_at=NOW() WHERE id=?", [$off['staff_id']]);
                $db->query(
                    "UPDATE staff_offboarding SET processed_by=:pby, processed_at=NOW() WHERE id=:id",
                    [':pby' => $this->user['user_id'] ?? null, ':id' => $offId]
                );
            }

            return $this->success(null, 'Offboarding updated');
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/staff/upcoming-retirements - Staff approaching retirement
     */
    public function getUpcomingRetirements($id = null, $data = [], $segments = [])
    {
        try {
            $db = \App\Database\Database::getInstance();
            $months = max(1, (int)($data['months'] ?? 12));
            $cutoff = date('Y-m-d', strtotime("+{$months} months"));

            $stmt = $db->query(
                "SELECT s.id, s.staff_no, s.first_name, s.last_name,
                        s.position, s.employment_date, s.date_of_birth,
                        d.name AS department,
                        TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
                        DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR) AS retirement_date,
                        DATEDIFF(DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR), CURDATE()) AS days_remaining,
                        s.status
                 FROM staff s
                 LEFT JOIN departments d ON d.id = s.department_id
                 WHERE s.status = 'active'
                   AND TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) >= 55
                   AND DATE_ADD(s.date_of_birth, INTERVAL 60 YEAR) <= :cutoff
                 ORDER BY days_remaining ASC",
                [':cutoff' => $cutoff]
            );
            return $this->success($stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/staff/my-schedule
     * Returns the timetable/schedule for the authenticated staff member
     */
    public function getMySchedule($id = null, $data = [], $segments = [])
    {
        $userId = $this->user['id'] ?? null;
        if (!$userId) {
            return $this->success([]);
        }
        try {
            $db = \App\Database\Database::getInstance();
            // Try timetable_entries first
            $stmt = $db->prepare("
                SELECT te.*, s.name AS subject_name, c.name AS class_name
                FROM timetable_entries te
                LEFT JOIN subjects s ON s.id = te.subject_id
                LEFT JOIN classes c ON c.id = te.class_id
                WHERE te.staff_id = :uid
                ORDER BY te.day_of_week, te.start_time
            ");
            $stmt->execute([':uid' => $userId]);
            $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($entries ?: []);
        } catch (\Exception $e) {
            try {
                $db = \App\Database\Database::getInstance();
                $stmt = $db->prepare("
                    SELECT * FROM staff_schedules WHERE staff_id = :uid ORDER BY day_of_week, start_time
                ");
                $stmt->execute([':uid' => $userId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                return $this->success($rows ?: []);
            } catch (\Exception $e2) {
                return $this->success([]);
            }
        }
    }
}
