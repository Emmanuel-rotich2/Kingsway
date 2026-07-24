<?php

namespace App\API\Controllers;

use App\API\Modules\staff\StaffAPI;
use App\API\Modules\staff\StaffPayrollManager;
use App\API\Modules\staff\StaffIDCardGenerator;
use App\API\Modules\staff\StaffLeaveManager;
use App\API\Modules\staff\StaffOnboardingManager;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\StaffLifecycleService;
use App\API\Services\StaffRecordsService;
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
    private $idCardGenerator;
    private $leaveManager;
    private $onboardingManager;
    private $access;
    private $lifecycleService;
    private $recordsService;

    public function __construct()
    {
        parent::__construct();
        $this->api = new StaffAPI();
        $this->payroll = new StaffPayrollManager();
        $this->idCardGenerator = new StaffIDCardGenerator();
        $this->leaveManager = new StaffLeaveManager();
        $this->onboardingManager = new StaffOnboardingManager();
        $this->access = new StaffDomainAccessService($this->user);
        $this->lifecycleService = new StaffLifecycleService();
        $this->recordsService = new StaffRecordsService($this->db);
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
        return $this->handleResponse($this->api->stats());
    }


    // ==================== BASE CRUD OPERATIONS ====================

    /**
     * GET /api/staff - List all staff
     * GET /api/staff/{id} - Get specific staff member
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
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
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
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
     * POST /api/staff/upload-photo/{id}
     * Uploads a staff profile photo.
     * Expects multipart/form-data with a "file" field.
     * Stored under uploads/staff/profile_pictures/{staff_no}/ and the
     * resulting URL is written to staff.profile_pic_url.
     *
     * POST /api/staff/upload-document/{id}
     * Uploads a staff document (CV, certificate, etc.).
     * Stored under uploads/staff/documents/{staff_no}/
     */
    public function postUploadPhoto($id = null, $data = [], $segments = [])
    {
        return $this->handleStaffUpload($id, $data, $segments, 'photo');
    }

    public function postUploadDocument($id = null, $data = [], $segments = [])
    {
        return $this->handleStaffUpload($id, $data, $segments, 'document');
    }

    // ==================== NEW ENDPOINTS FOR STAFF UI CONTROLLERS ====================

    /**
     * GET /api/staff/academic-kpi-summary/{staffId} - Get academic KPI summary
     */
    public function getAcademicKPISummary($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        try {
            $params = array_merge($_GET ?? [], $data);
            return $this->handleResponse($this->api->getAcademicKPISummary(
                (int)$id,
                isset($params['academic_year_id']) ? (int)$params['academic_year_id'] : null
            ));
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * POST /api/staff/assign-role - Assign role to staff
     */
    public function postAssignRole($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        try {
            $staffId = (int)($data['staff_id'] ?? 0);
            $roleId = (int)($data['role_id'] ?? 0);
            $result = $this->recordsService->assignRole($staffId, $roleId);
            $this->access->audit('assign_role', 'staff', $staffId, null, ['role_id' => $roleId]);
            return $this->success($result + ['assigned' => true], 'Role assigned successfully');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * DELETE /api/staff/revoke-role/{staffId}/{roleId} - Revoke role from staff
     */
    public function deleteRevokeRole($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        try {
            $staffId = (int)$id;
            $roleId = (int)($segments[0] ?? 0);
            $this->recordsService->revokeRole($staffId, $roleId);
            $this->access->audit('remove_role', 'staff', $staffId, ['role_id' => $roleId], null);
            return $this->success(['revoked' => true], 'Role revoked successfully');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    // ==================== ADDITIONAL STAFF MANAGEMENT ENDPOINTS ====================

    /**
     * GET /api/staff/lifecycle - Get staff lifecycle records
     */
    public function getLifecycle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.lifecycle.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        try {
            $params = array_merge($_GET ?? [], $data);
            return $this->success(
                !empty($params['staff_id'])
                    ? $this->lifecycleService->timeline((int)$params['staff_id'])
                    : $this->lifecycleService->dashboard($params),
                'Staff lifecycle records retrieved'
            );
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * POST /api/staff/lifecycle - Create lifecycle action
     */
    public function postLifecycle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.lifecycle.manage', ['system administrator','school administrator','director','deputy head discipline'])) return $denied;
        try {
            $actionId = $this->lifecycleService->createAction($data, $this->access->userId());
            return $this->created(['id' => $actionId], 'Lifecycle action created successfully');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * GET /api/staff/appointments - Get staff appointments
     */
    public function getAppointments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.appointments.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        return $this->success($this->recordsService->appointmentSummary(), 'Staff appointments retrieved');
    }

    /**
     * POST /api/staff/appointments - Create appointment
     */
    public function postAppointments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.appointments.manage', ['system administrator','school administrator','director'])) return $denied;
        return $this->badRequest('Use /api/staff-appointments/internal or /api/staff-appointments/new for appointment creation.');
    }

    /**
     * POST /api/staff/import-existing - Import existing staff records
     */
    public function postImportExisting($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.import.manage', ['system administrator','school administrator'])) return $denied;
        return $this->badRequest('Use /api/staff-migration/stage and /api/staff-migration/commit for existing staff imports.');
    }

    private function handleStaffUpload($id = null, $data = [], $segments = [], $forcedType = 'document')
    {
        $staffId = (int) ($id ?: ($data['staff_id'] ?? 0));
        if (!$staffId) {
            return $this->badRequest('Staff ID is required for upload');
        }
        if (empty($_FILES['file'])) {
            return $this->badRequest('No file provided (expected field "file")');
        }

        // RBAC: require an authenticated user with a staff-management role.
        if (empty($this->user)) {
            return $this->unauthorized('Authentication required to upload staff files');
        }
        $allowedRoles = ['admin', 'school_admin', 'headteacher', 'director', 'human_resources'];
        if (!$this->userHasAny([], [], $allowedRoles)) {
            return $this->forbidden('Insufficient permission to upload staff files');
        }

        $type = $forcedType;
        $description = $data['description'] ?? ($_POST['description'] ?? '');
        $tags = $data['tags'] ?? ($_POST['tags'] ?? '');
        $uploaderId = $this->user['id'] ?? null;

        try {
            $mediaId = $this->api->uploadStaffMedia($staffId, $_FILES['file'], $type, $uploaderId, $description, $tags);
        } catch (\Exception $e) {
            return $this->serverError('Upload failed: ' . $e->getMessage());
        }

        if (!$mediaId) {
            return $this->serverError('Upload failed: media service returned no identifier');
        }

        // Reflect the new photo URL on the staff record when uploading a photo.
        if ($type === 'photo') {
            try {
                $url = $this->api->getMediaFileUrl($mediaId);
                if ($url) {
                    $this->api->setProfilePicUrl($staffId, $url);
                }
            } catch (\Exception $e) {
                // Non-fatal: photo uploaded but record update failed; client can re-fetch.
            }
        }

        return $this->json([
            'success' => true,
            'media_id' => $mediaId,
            'type' => $type
        ]);
    }

    /**
     * PUT /api/staff/{id} - Update staff member
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
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
        if ($denied = $this->guardStaffDomain('staff.directory.delete', ['system administrator'])) return $denied;
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
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->getProfile($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/schedule/get - Get staff schedule
     */
    public function getScheduleGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
            $staffId = $this->recordsService->staffIdForChild((int)$childId);
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $filters = array_merge($_GET, $data);
        if (!$this->access->allows('staff.payroll.manage', ['system administrator','accountant','director'])) { $filters['staff_id'] = $this->access->staffId(); }
        $result = $this->api->listPayroll($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/summary
     */
    public function getPayrollSummary($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','accountant','director'])) return $denied;
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
            $staffId = $this->access->staffId();
        }
        if (!$staffId) return $this->badRequest('Staff ID is required');
        try { $this->access->requireSelfOr('staff.payslip.manage', (int)$staffId, ['system administrator','accountant']); }
        catch (RuntimeException $e) { return $e->getCode() === 401 ? $this->unauthorized($e->getMessage()) : $this->forbidden($e->getMessage()); }

        $result = $this->api->generateDetailedPayslip((int) $staffId, $month, $year, $this->getUserId());
        return $this->handleResponse($result);
    }

    // ==================== ASSIGNMENT OPERATIONS ====================

    /**
     * POST /api/staff/assign/class - Assign staff to class
     */
    public function postAssignClass($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teaching_assignments.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
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
        if ($denied = $this->guardStaffDomain('staff.teaching_assignments.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
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
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->getCurrentAssignments($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/workload/get - Get staff workload
     */
    public function getWorkloadGet($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $academicYearId = $data['academic_year_id'] ?? null;
        
        $result = $this->api->getStaffWorkload($staffId, $academicYearId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/assignment/initiate - Initiate assignment workflow
     */
    public function postAssignmentInitiate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teaching_assignments.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        try { $data = $this->access->forceSelfScope(array_merge($_GET, $data)); } catch (RuntimeException $e) { return $this->forbidden($e->getMessage()); }
        $result = $this->api->getAttendance($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/attendance/mark - Mark staff attendance
     */
    public function postAttendanceMark($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.attendance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        $result = $this->api->markAttendance($data);
        return $this->handleResponse($result);
    }

    // ==================== LEAVE MANAGEMENT ====================

    /**
     * GET /api/staff/leaves/list - List leave requests
     */
    public function getLeavesList($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if (!$this->access->allows('staff.leave.manage', ['system administrator','school administrator','headteacher','director'])) { $data['staff_id'] = $this->access->staffId(); }
        $result = $this->api->getLeaves($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/leaves/apply - Apply for leave
     */
    public function postLeavesApply($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if (!$this->access->allows('staff.leave.manage', ['system administrator','school administrator'])) { $data['staff_id'] = $this->access->staffId(); }
        if (empty($data['staff_id'])) return $this->forbidden('No staff profile is linked to this account');
        $result = $this->api->applyLeave($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/staff/leaves/update-status - Update leave status
     */
    public function putLeavesUpdateStatus($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.leave.approve', ['director','headteacher','school administrator'])) return $denied;
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->viewAllowances($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/deductions - View deductions
     */
    public function getPayrollDeductions($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->viewDeductions($staffId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/loan-details - Get loan details
     */
    public function getPayrollLoanDetails($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $loanId = $data['loan_id'] ?? null;
        
        $result = $this->api->getLoanDetails($staffId, $loanId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/request-advance - Request salary advance
     */
    public function postPayrollRequestAdvance($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->requestAdvance($staffId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/payroll/apply-loan - Apply for loan
     */
    public function postPayrollApplyLoan($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $result = $this->api->applyForLoan($staffId, $this->getUserId(), $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-p9 - Download P9 form
     */
    public function getPayrollDownloadP9($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
        $year = $data['year'] ?? date('Y');
        
        $result = $this->api->downloadP9Form($staffId, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/payroll/download-payslip - Download payslip
     */
    public function getPayrollDownloadPayslip($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
        $staffId = $id ?? $data['staff_id'] ?? $this->access->staffId();
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
            return $this->success($this->recordsService->promotions($data));
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
            $id = $this->recordsService->createPromotion($data, $this->access->userId());
            return $this->created(['id' => $id], 'Promotion submitted for approval');
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
            $promotionId = (int)($id ?? $data['id'] ?? 0);
            if (!$promotionId) return $this->badRequest('Promotion ID is required');
            $action = $data['action'] ?? '';
            $this->recordsService->decidePromotion($promotionId, $action, $this->access->userId(), $data['reason'] ?? null);
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
            return $this->success($this->recordsService->offboarding($data));
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
            $id = $this->recordsService->createOffboarding($data, $this->access->userId());
            return $this->created(['id' => $id], 'Offboarding initiated');
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
            $offId = (int)($id ?? $data['id'] ?? 0);
            if (!$offId) return $this->badRequest('Offboarding ID is required');
            $this->recordsService->updateOffboarding($offId, $data, $this->access->userId());
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
            return $this->success($this->recordsService->upcomingRetirements((int)($data['months'] ?? 12)));
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
        return $this->success($this->recordsService->scheduleForUser((int)$userId));
    }

    // =========================================================================
    // ONBOARDING
    // =========================================================================

    /**
     * GET /api/staff/onboarding        — list all onboardings
     * GET /api/staff/onboarding/{id}   — single onboarding + tasks + documents
     */
    public function getOnboarding($id = null, $data = [], $segments = [])
    {
        $result = $id
            ? $this->onboardingManager->getOnboardingDetail((int)$id)
            : $this->onboardingManager->listOnboardings(array_merge($_GET ?? [], $data));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/onboarding
     * Initiate onboarding for a staff member. Auto-generates tasks from templates.
     */
    public function postOnboarding($id = null, $data = [], $segments = [])
    {
        $data['initiated_by'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->createOnboarding($data));
    }

    /**
     * PUT /api/staff/onboarding/{id}
     * Update onboarding status or overall notes.
     */
    public function putOnboarding($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('onboarding id required');
        return $this->handleResponse($this->onboardingManager->updateOnboarding((int)$id, $data));
    }

    /**
     * PUT /api/staff/onboarding-task/{id}
     * Mark a task complete, in_progress, blocked, or skipped.
     */
    public function putOnboardingTask($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('task id required');
        return $this->handleResponse($this->onboardingManager->updateTaskStatus((int)$id, $data));
    }

    /**
     * POST /api/staff/onboarding-document
     * Record that a document has been collected.
     */
    public function postOnboardingDocument($id = null, $data = [], $segments = [])
    {
        $data['verified_by'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->recordDocument($data));
    }

    /**
     * POST /api/staff/probation-review
     * Record a probation review outcome.
     */
    public function postProbationReview($id = null, $data = [], $segments = [])
    {
        $data['reviewer_id'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->recordProbationReview($data));
    }

    /**
     * GET /api/staff/onboarding-templates
     * List all task templates (for HR to customise before generating).
     */
    public function getOnboardingTemplates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->onboardingManager->getActiveTemplates());
    }

    /**
     * GET /api/staff/onboarding-pending
     * All overdue or pending tasks across all active onboardings — HR dashboard feed.
     */
    public function getOnboardingPending($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->onboardingManager->getPendingTasks());
    }

    // ========================================================================
    // STAFF ID CARD ENDPOINTS
    // ========================================================================

    /**
     * POST /api/staff/id-card/generate
     * Generate staff ID card
     */
    public function postIdCardGenerate($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $staffId = $data['staff_id'] ?? null;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $format = $data['format'] ?? 'html';
        $side = $data['side'] ?? 'both';

        $result = $this->idCardGenerator->generateIDCard((int) $staffId, $format, $side);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/id-card/generate-bulk-pdf
     * Generate bulk PDF for selected staff with A4 layout
     */
    public function postIdCardGenerateBulkPdf($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $staffIds = $data['staff_ids'] ?? [];
        if (empty($staffIds) || !is_array($staffIds)) {
            return $this->badRequest('Staff IDs array is required');
        }

        $printMode = $data['print_mode'] ?? 'a4_sheet';
        $includeFront = $data['include_front'] ?? true;
        $includeBack = $data['include_back'] ?? true;

        $result = $this->idCardGenerator->generateBulkIDCardsPDF($staffIds, $printMode, $includeFront, $includeBack);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/id-card/print-single
     * Generate print-ready single card HTML for browser/system printing.
     */
    public function postIdCardPrintSingle($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $staffId = $data['staff_id'] ?? ($segments[0] ?? null);
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $side = $data['side'] ?? 'both';
        $printMode = $data['print_mode'] ?? 'direct_card';

        $result = $this->idCardGenerator->generatePrintableSingle((int) $staffId, $side, $printMode);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/id-card/upload-photo
     * Upload staff photo for ID card
     */
    public function postIdCardUploadPhoto($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        $staffId = $data['staff_id'] ?? null;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        if (!isset($_FILES['photo'])) {
            return $this->badRequest('Photo file is required');
        }

        $result = $this->idCardGenerator->uploadStaffPhoto((int) $staffId, $_FILES['photo']);
        return $this->handleResponse($result);
    }

    // ========================================================================
    // CHECKPOINT 2 — CANONICAL STAFF DOMAIN ENDPOINTS
    // ========================================================================

    private function guardStaffDomain(string $permission, array $roles = [])
    {
        try {
            $this->access->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) {
            return $e->getCode() === 401
                ? $this->unauthorized($e->getMessage())
                : $this->forbidden($e->getMessage());
        }
    }

    /** GET /api/staff/access-context */
    public function getAccessContext($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) {
            return $this->unauthorized('Authentication required');
        }
        return $this->success([
            'user_id' => $this->access->userId(),
            'staff_id' => $this->access->staffId(),
            'permissions' => $this->access->permissions(),
            'roles' => $this->access->roles(),
            'capabilities' => [
                'staff_directory_view' => $this->access->allows('staff.directory.view', ['system administrator','school administrator','director','headteacher']),
                'staff_directory_manage' => $this->access->allows('staff.directory.manage', ['system administrator','school administrator']),
                'teachers_view' => $this->access->allows('staff.teachers.view', ['system administrator','school administrator','director','headteacher','deputy head - academic']),
                'non_teaching_view' => $this->access->allows('staff.non_teaching.view', ['system administrator','school administrator','director','headteacher']),
                'attendance_manage' => $this->access->allows('staff.attendance.manage', ['system administrator','school administrator','headteacher']),
                'attendance_self' => $this->access->allows('staff.attendance.self', ['staff','class teacher','subject teacher','accountant']),
                'leave_manage' => $this->access->allows('staff.leave.manage', ['system administrator','school administrator','headteacher']),
                'leave_approve' => $this->access->allows('staff.leave.approve', ['director','headteacher','school administrator']),
                'payroll_manage' => $this->access->allows('staff.payroll.manage', ['system administrator','accountant']),
                'payroll_approve' => $this->access->allows('staff.payroll.approve', ['director']),
                'payslip_self' => $this->access->allows('staff.payslip.self', ['staff','class teacher','subject teacher','accountant']),
                'id_cards_manage' => $this->access->allows('staff.id_cards.manage', ['system administrator','school administrator']),
                'role_assignments_manage' => $this->access->allows('staff.roles.manage', ['system administrator','school administrator']),
                'teaching_assignments_manage' => $this->access->allows('staff.teaching_assignments.manage', ['system administrator','school administrator','headteacher','deputy head - academic']),
            ],
        ]);
    }

    /** GET /api/staff/teachers */
    public function getTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teachers.view', ['system administrator','school administrator','director','headteacher','deputy head - academic'])) return $denied;
        return $this->handleResponse($this->api->listTeachers($_GET ?? []));
    }

    /** GET /api/staff/non-teaching */
    public function getNonTeaching($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.non_teaching.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        return $this->handleResponse($this->api->listNonTeaching($_GET ?? []));
    }

    /** Alias required by all_teachers.js: GET /api/staff/departments */
    public function getDepartments($id = null, $data = [], $segments = [])
    {
        return $this->getDepartmentsGet($id, $data, $segments);
    }

    /** GET /api/staff/payroll-eligibility/{staffId} */
    public function getPayrollEligibility($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.payroll.eligibility.view', ['system administrator','school administrator','accountant','director'])) return $denied;
        $staffId = (int)($id ?? $_GET['staff_id'] ?? 0);
        if (!$staffId) return $this->badRequest('Staff ID is required');
        return $this->success($this->access->payrollEligibility($staffId));
    }

    /** POST /api/staff/payroll-eligibility/validate */
    public function postPayrollEligibilityValidate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.payroll.eligibility.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $staffIds = array_values(array_unique(array_map('intval', (array)($data['staff_ids'] ?? []))));
        if (!$staffIds && !empty($data['staff_id'])) $staffIds = [(int)$data['staff_id']];
        if (!$staffIds) return $this->badRequest('staff_id or staff_ids is required');
        $results = [];
        foreach ($staffIds as $staffId) $results[] = $this->access->payrollEligibility($staffId);
        return $this->success($results);
    }

    /** POST /api/staff/role-assignments */
    public function postRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        try {
            $staffId = (int)($data['staff_id'] ?? 0);
            $roleId = (int)($data['role_id'] ?? 0);
            $result = $this->recordsService->assignRole($staffId, $roleId);
            $this->access->audit('assign_role', 'staff', $staffId, null, ['role_id' => $roleId]);
            return $this->success($result, 'Role assigned');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** DELETE /api/staff/role-assignments/{roleId}?staff_id=X */
    public function deleteRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        try {
            $roleId = (int)($id ?? $data['role_id'] ?? 0);
            $staffId = (int)($_GET['staff_id'] ?? $data['staff_id'] ?? 0);
            $this->recordsService->revokeRole($staffId, $roleId);
            $this->access->audit('remove_role', 'staff', $staffId, ['role_id' => $roleId], null);
            return $this->success(null, 'Role removed');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** GET /api/staff/id-cards */
    public function getIdCards($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        return $this->success($this->recordsService->idCards($_GET ?? []));
    }

    /** POST /api/staff/id-cards/generate */
    public function postIdCardsGenerate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) return $this->badRequest('staff_id is required');
        try {
            $card = $this->idCardGenerator->generateIDCard($staffId, $data['format'] ?? 'html', $data['side'] ?? 'both');
            $number = 'KWA-S-' . str_pad((string)$staffId, 6, '0', STR_PAD_LEFT);
            $this->recordsService->persistGeneratedIdCard($staffId, $number, $data['expires_at'] ?? date('Y-m-d', strtotime('+2 years')), $this->access->userId());
            $this->access->audit('generate_id_card', 'staff', $staffId, null, ['card_number' => $number]);
            return $this->success(['card_number' => $number, 'document' => $card], 'Staff ID card generated');
        } catch (\Throwable $e) {
            return $this->serverError('Failed to generate staff ID card', $e->getMessage());
        }
    }

    /** POST /api/staff/id-cards/issue */
    public function postIdCardsIssue($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId) return $this->badRequest('staff_id is required');
        $this->recordsService->issueIdCard($staffId, $this->access->userId());
        $this->access->audit('issue_id_card', 'staff', $staffId, null, ['status' => 'issued']);
        return $this->success(null, 'Staff ID card issued');
    }

    /** GET /api/staff/leave-requests — admin scope or own records */
    public function getLeaveRequests($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $filters = $_GET;
        if (!$this->access->allows('staff.leave.manage', ['system administrator','school administrator','headteacher','director'])) {
            $filters['staff_id'] = $this->access->staffId();
        }
        return $this->handleResponse($this->leaveManager->getLeaveHistory($filters));
    }

    /** POST /api/staff/leave-requests */
    public function postLeaveRequests($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $ownId = $this->access->staffId();
        if (!$ownId) return $this->forbidden('No staff profile is linked to this account');
        if (!$this->access->allows('staff.leave.manage', ['system administrator','school administrator'])) {
            $data['staff_id'] = $ownId;
        }
        $result = $this->leaveManager->createLeaveRequest($data);
        $this->access->audit('create_leave_request', 'staff', (int)$data['staff_id'], null, $data);
        return $this->handleResponse($result);
    }

    /** PUT /api/staff/leave-requests/{id}/status */
    public function putLeaveRequestsStatus($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.leave.approve', ['director','headteacher','school administrator'])) return $denied;
        $leaveId = (int)($id ?? $data['id'] ?? 0);
        if (!$leaveId) return $this->badRequest('Leave request ID is required');
        $data['approved_by'] = $this->access->userId();
        $result = $this->leaveManager->updateLeaveStatus($leaveId, $data);
        $this->access->audit('update_leave_status', 'leave_request', $leaveId, null, $data);
        return $this->handleResponse($result);
    }


    /** GET /api/staff/performance-reviews */
    public function getPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.view', ['system administrator','school administrator','director','headteacher','deputy head - academic'])) return $denied;
        try {
            $rows = $this->recordsService->performanceReviews($_GET ?? [], $id ? (int)$id : null);
            if($id) return $rows ? $this->success($rows[0]) : $this->notFound('Performance review not found');
            return $this->success($rows);
        } catch(\Throwable $e){return $this->serverError('Failed to load performance reviews',$e->getMessage());}
    }

    /** POST /api/staff/performance-reviews */
    public function postPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        try {
            $data['reviewer_id'] = $data['reviewer_id'] ?? $this->access->staffId();
            $newId = $this->recordsService->createPerformanceReview($data);
            $this->access->audit('create_performance_review','staff_performance_review',$newId,null,$data);
            return $this->created(['id'=>$newId],'Performance review created');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** PUT /api/staff/performance-reviews/{id} */
    public function putPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        if(!$id)return $this->badRequest('Review ID is required');
        try {
            $before = $this->recordsService->updatePerformanceReview((int)$id, $data);
            $this->access->audit('update_performance_review','staff_performance_review',(int)$id,$before,$data);
            return $this->success(['id'=>(int)$id],'Performance review updated');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** DELETE /api/staff/performance-reviews/{id} — drafts only */
    public function deletePerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        if(!$id)return $this->badRequest('Review ID is required');
        try {
            $before = $this->recordsService->deletePerformanceReview((int)$id);
            $this->access->audit('delete_performance_review','staff_performance_review',(int)$id,$before,null);
            return $this->success(null,'Performance review deleted');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }


    /** GET /api/staff/leave-types */
    public function getLeaveTypes($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        return $this->success($this->recordsService->leaveTypes());
    }

    /** GET /api/staff/available-roles */
    public function getAvailableRoles($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        return $this->success($this->recordsService->availableRoles());
    }

    /** GET /api/staff/role-assignments?staff_id=X */
    public function getRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        try {
            $staffId=(int)($_GET['staff_id']??$data['staff_id']??$id??0);
            return $this->success($this->recordsService->roleAssignments($staffId));
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

}
