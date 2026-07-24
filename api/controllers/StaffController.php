<?php

namespace App\API\Controllers;

use App\API\Modules\staff\StaffAPI;
use App\API\Modules\staff\StaffPayrollManager;
use App\API\Modules\staff\StaffIDCardGenerator;
use App\API\Modules\staff\StaffLeaveManager;
use App\API\Services\StaffDomainAccessService;
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
    private $access;

    public function __construct()
    {
        parent::__construct();
        $this->api = new StaffAPI();
        $this->payroll = new StaffPayrollManager();
        $this->idCardGenerator = new StaffIDCardGenerator();
        $this->leaveManager = new StaffLeaveManager();
        $this->access = new StaffDomainAccessService($this->user);
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
     * GET /api/staff/teachers - Get teaching staff only
     */
    public function getTeachers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        
        $params = array_merge($_GET ?? [], $data);
        $params['staff_type_id'] = 1; // Teaching staff
        
        $result = $this->api->list($params);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/non-teaching - Get non-teaching staff only
     */
    public function getNonTeaching($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        
        $params = array_merge($_GET ?? [], $data);
        $params['staff_type_id'] = 2; // Non-teaching staff
        
        $result = $this->api->list($params);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/performance-review-history/{staffId} - Get performance review history
     */
    public function getPerformanceReviewHistory($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        
        $staffId = (int) $id;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        try {
            $db = $this->db;
            $query = $db->prepare("
                SELECT pr.id as review_id, pr.review_date, pr.review_period, 
                       pr.overall_score, pr.performance_grade, pr.comments,
                       u.first_name || ' ' || u.last_name as reviewer_name,
                       s.first_name || ' ' || s.last_name as staff_name,
                       s.department_id
                FROM staff_performance_reviews pr
                LEFT JOIN users u ON pr.reviewer_id = u.id
                LEFT JOIN staff s ON pr.staff_id = s.id
                WHERE pr.staff_id = ?
                ORDER BY pr.review_date DESC
            ");
            $query->execute([$staffId]);
            $reviews = $query->fetchAll();

            return $this->success([
                'reviews' => $reviews,
                'count' => count($reviews)
            ], 'Performance review history retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch performance review history: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/academic-kpi-summary/{staffId} - Get academic KPI summary
     */
    public function getAcademicKPISummary($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        
        $staffId = (int) $id;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        try {
            $db = $this->db;
            $params = array_merge($_GET ?? [], $data);
            $academicYearId = $params['academic_year_id'] ?? null;

            $whereClause = "WHERE kpi.staff_id = ?";
            $queryParams = [$staffId];
            
            if ($academicYearId) {
                $whereClause .= " AND kpi.academic_year_id = ?";
                $queryParams[] = $academicYearId;
            }

            $query = $db->prepare("
                SELECT kpi.id, kpi.kpi_name, kpi.kpi_code, 
                       kpi.target_value, kpi.actual_value, 
                       kpi.achievement_percentage, kpi.period,
                       kpi.academic_year_id
                FROM staff_academic_kpis kpi
                $whereClause
                ORDER BY kpi.period DESC
            ");
            $query->execute($queryParams);
            $kpis = $query->fetchAll();

            return $this->success([
                'kpis' => $kpis,
                'count' => count($kpis)
            ], 'Academic KPI summary retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch academic KPI summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/performance-reviews - Get performance reviews with filters
     */
    public function getPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        
        try {
            $db = $this->db;
            $params = array_merge($_GET ?? [], $data);
            
            $whereClause = "WHERE 1=1";
            $queryParams = [];
            
            if (!empty($params['teacher_id'])) {
                $whereClause .= " AND pr.staff_id = ?";
                $queryParams[] = $params['teacher_id'];
            }
            
            if (!empty($params['subject_id'])) {
                $whereClause .= " AND pr.subject_id = ?";
                $queryParams[] = $params['subject_id'];
            }
            
            if (!empty($params['review_date'])) {
                $whereClause .= " AND pr.review_date = ?";
                $queryParams[] = $params['review_date'];
            }

            $query = $db->prepare("
                SELECT pr.id, pr.staff_id as teacher_id, pr.subject_id,
                       pr.review_date, pr.rating, pr.category, pr.remarks,
                       u.first_name || ' ' || u.last_name as reviewer_name,
                       s.first_name || ' ' || s.last_name as teacher_name
                FROM teacher_performance_reviews pr
                LEFT JOIN users u ON pr.reviewer_id = u.id
                LEFT JOIN staff s ON pr.staff_id = s.id
                $whereClause
                ORDER BY pr.review_date DESC
            ");
            $query->execute($queryParams);
            $reviews = $query->fetchAll();

            return $this->success($reviews, 'Performance reviews retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch performance reviews: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/available-roles - Get available system roles
     */
    public function getAvailableRoles($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        
        try {
            $db = $this->db;
            $query = $db->query("
                SELECT id, name, description, level
                FROM roles
                WHERE status = 'active'
                ORDER BY level ASC, name ASC
            ");
            $roles = $query->fetchAll();

            return $this->success($roles, 'Available roles retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch available roles: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/role-assignments/{staffId} - Get role assignments for staff
     */
    public function getRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        
        $staffId = (int) $id;
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        try {
            $db = $this->db;
            $query = $db->prepare("
                SELECT r.id as role_id, r.name, r.description, r.level,
                       sr.assigned_at, sr.assigned_by
                FROM staff_roles sr
                LEFT JOIN roles r ON sr.role_id = r.id
                WHERE sr.staff_id = ? AND sr.status = 'active'
                ORDER BY r.level ASC
            ");
            $query->execute([$staffId]);
            $assignments = $query->fetchAll();

            return $this->success($assignments, 'Role assignments retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch role assignments: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/staff/assign-role - Assign role to staff
     */
    public function postAssignRole($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        
        $staffId = (int) ($data['staff_id'] ?? 0);
        $roleId = (int) ($data['role_id'] ?? 0);
        
        if (!$staffId || !$roleId) {
            return $this->badRequest('Staff ID and Role ID are required');
        }

        try {
            $db = $this->db;
            
            // Check if assignment already exists
            $checkQuery = $db->prepare("
                SELECT id FROM staff_roles 
                WHERE staff_id = ? AND role_id = ? AND status = 'active'
            ");
            $checkQuery->execute([$staffId, $roleId]);
            
            if ($checkQuery->fetch()) {
                return $this->error('Role already assigned to this staff member');
            }
            
            // Assign role
            $insertQuery = $db->prepare("
                INSERT INTO staff_roles (staff_id, role_id, assigned_at, assigned_by, status)
                VALUES (?, ?, NOW(), ?, 'active')
            ");
            $insertQuery->execute([$staffId, $roleId, $this->user['id'] ?? null]);

            return $this->success(['assigned' => true], 'Role assigned successfully');

        } catch (Exception $e) {
            return $this->error('Failed to assign role: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/staff/revoke-role/{staffId}/{roleId} - Revoke role from staff
     */
    public function deleteRevokeRole($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        
        $staffId = (int) $id;
        $roleId = (int) ($segments[0] ?? 0);
        
        if (!$staffId || !$roleId) {
            return $this->badRequest('Staff ID and Role ID are required');
        }

        try {
            $db = $this->db;
            
            $updateQuery = $db->prepare("
                UPDATE staff_roles 
                SET status = 'inactive', revoked_at = NOW(), revoked_by = ?
                WHERE staff_id = ? AND role_id = ? AND status = 'active'
            ");
            $updateQuery->execute([$this->user['id'] ?? null, $staffId, $roleId]);

            return $this->success(['revoked' => true], 'Role revoked successfully');

        } catch (Exception $e) {
            return $this->error('Failed to revoke role: ' . $e->getMessage());
        }
    }

    // ==================== ADDITIONAL STAFF MANAGEMENT ENDPOINTS ====================

    /**
     * GET /api/staff/onboarding - Get staff onboarding records
     */
    public function getOnboarding($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        
        try {
            $db = $this->db;
            $params = array_merge($_GET ?? [], $data);
            
            $whereClause = "WHERE 1=1";
            $queryParams = [];
            
            if (!empty($params['staff_id'])) {
                $whereClause .= " AND so.staff_id = ?";
                $queryParams[] = $params['staff_id'];
            }
            
            if (!empty($params['status'])) {
                $whereClause .= " AND so.status = ?";
                $queryParams[] = $params['status'];
            }

            $query = $db->prepare("
                SELECT so.id, so.staff_id, so.start_date, so.probation_months,
                       so.contract_type, so.mentor_id, so.status, so.notes,
                       s.first_name || ' ' || s.last_name as staff_name,
                       s.staff_no, s.position, s.department_id,
                       m.first_name || ' ' || m.last_name as mentor_name
                FROM staff_onboarding so
                LEFT JOIN staff s ON so.staff_id = s.id
                LEFT JOIN staff m ON so.mentor_id = m.id
                $whereClause
                ORDER BY so.start_date DESC
            ");
            $query->execute($queryParams);
            $onboarding = $query->fetchAll();

            return $this->success($onboarding, 'Staff onboarding records retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch onboarding records: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/staff/onboarding - Create onboarding record
     */
    public function postOnboarding($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', ['system administrator','school administrator'])) return $denied;
        
        $staffId = (int) ($data['staff_id'] ?? 0);
        $startDate = $data['start_date'] ?? null;
        
        if (!$staffId || !$startDate) {
            return $this->badRequest('Staff ID and start date are required');
        }

        try {
            $db = $this->db;
            
            $insertQuery = $db->prepare("
                INSERT INTO staff_onboarding (staff_id, start_date, probation_months, contract_type, mentor_id, notes, status, created_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
            ");
            $insertQuery->execute([
                $staffId,
                $startDate,
                $data['probation_months'] ?? 3,
                $data['contract_type'] ?? 'probation',
                $data['mentor_id'] ?? null,
                $data['notes'] ?? null,
                $this->user['id'] ?? null
            ]);

            return $this->success(['created' => true], 'Onboarding record created successfully');

        } catch (Exception $e) {
            return $this->error('Failed to create onboarding record: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/lifecycle - Get staff lifecycle records
     */
    public function getLifecycle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.lifecycle.view', ['system administrator','school administrator','director','headteacher','deputy head discipline'])) return $denied;
        
        try {
            $db = $this->db;
            $params = array_merge($_GET ?? [], $data);
            
            $whereClause = "WHERE 1=1";
            $queryParams = [];
            
            if (!empty($params['staff_id'])) {
                $whereClause .= " AND sl.staff_id = ?";
                $queryParams[] = $params['staff_id'];
            }
            
            if (!empty($params['action_type'])) {
                $whereClause .= " AND sl.action_type = ?";
                $queryParams[] = $params['action_type'];
            }

            $query = $db->prepare("
                SELECT sl.id, sl.staff_id, sl.action_type, sl.effective_date,
                       sl.to_position, sl.to_department_id, sl.to_salary,
                       sl.reason, sl.notes, sl.status, sl.approved_by,
                       s.first_name || ' ' || s.last_name as staff_name,
                       s.staff_no, s.position as current_position,
                       d.name as department_name
                FROM staff_lifecycle sl
                LEFT JOIN staff s ON sl.staff_id = s.id
                LEFT JOIN departments d ON sl.to_department_id = d.id
                $whereClause
                ORDER BY sl.effective_date DESC
            ");
            $query->execute($queryParams);
            $lifecycle = $query->fetchAll();

            return $this->success($lifecycle, 'Staff lifecycle records retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch lifecycle records: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/staff/lifecycle - Create lifecycle action
     */
    public function postLifecycle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.lifecycle.manage', ['system administrator','school administrator','director','deputy head discipline'])) return $denied;
        
        $staffId = (int) ($data['staff_id'] ?? 0);
        $actionType = $data['action_type'] ?? null;
        $effectiveDate = $data['effective_date'] ?? null;
        
        if (!$staffId || !$actionType || !$effectiveDate) {
            return $this->badRequest('Staff ID, action type, and effective date are required');
        }

        try {
            $db = $this->db;
            
            $insertQuery = $db->prepare("
                INSERT INTO staff_lifecycle (staff_id, action_type, effective_date, to_position, to_department_id, to_salary, reason, notes, status, created_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
            ");
            $insertQuery->execute([
                $staffId,
                $actionType,
                $effectiveDate,
                $data['to_position'] ?? null,
                $data['to_department_id'] ?? null,
                $data['to_salary'] ?? null,
                $data['reason'] ?? null,
                $data['notes'] ?? null,
                $this->user['id'] ?? null
            ]);

            return $this->success(['created' => true], 'Lifecycle action created successfully');

        } catch (Exception $e) {
            return $this->error('Failed to create lifecycle action: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/staff/appointments - Get staff appointments
     */
    public function getAppointments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.appointments.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        
        try {
            $db = $this->db;
            $params = array_merge($_GET ?? [], $data);
            
            $whereClause = "WHERE 1=1";
            $queryParams = [];
            
            if (!empty($params['staff_id'])) {
                $whereClause .= " AND sa.staff_id = ?";
                $queryParams[] = $params['staff_id'];
            }
            
            if (!empty($params['status'])) {
                $whereClause .= " AND sa.status = ?";
                $queryParams[] = $params['status'];
            }

            $query = $db->prepare("
                SELECT sa.id, sa.staff_id, sa.position, sa.department_id,
                       sa.appointment_date, sa.contract_type, sa.salary,
                       sa.status, sa.approved_by, sa.approved_at,
                       s.first_name || ' ' || s.last_name as staff_name,
                       s.staff_no, d.name as department_name
                FROM staff_appointments sa
                LEFT JOIN staff s ON sa.staff_id = s.id
                LEFT JOIN departments d ON sa.department_id = d.id
                $whereClause
                ORDER BY sa.appointment_date DESC
            ");
            $query->execute($queryParams);
            $appointments = $query->fetchAll();

            return $this->success($appointments, 'Staff appointments retrieved');

        } catch (Exception $e) {
            return $this->error('Failed to fetch appointments: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/staff/appointments - Create appointment
     */
    public function postAppointments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.appointments.manage', ['system administrator','school administrator','director'])) return $denied;
        
        $staffId = (int) ($data['staff_id'] ?? 0);
        $position = $data['position'] ?? null;
        $appointmentDate = $data['appointment_date'] ?? null;
        
        if (!$staffId || !$position || !$appointmentDate) {
            return $this->badRequest('Staff ID, position, and appointment date are required');
        }

        try {
            $db = $this->db;
            
            $insertQuery = $db->prepare("
                INSERT INTO staff_appointments (staff_id, position, department_id, appointment_date, contract_type, salary, status, created_at, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
            ");
            $insertQuery->execute([
                $staffId,
                $position,
                $data['department_id'] ?? null,
                $appointmentDate,
                $data['contract_type'] ?? 'permanent',
                $data['salary'] ?? null,
                $this->user['id'] ?? null
            ]);

            return $this->success(['created' => true], 'Appointment created successfully');

        } catch (Exception $e) {
            return $this->error('Failed to create appointment: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/staff/import-existing - Import existing staff records
     */
    public function postImportExisting($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.import.manage', ['system administrator','school administrator'])) return $denied;
        
        try {
            $db = $this->db;
            
            if (empty($data['staff_records']) || !is_array($data['staff_records'])) {
                return $this->badRequest('Staff records array is required');
            }

            $imported = 0;
            $failed = 0;
            
            foreach ($data['staff_records'] as $record) {
                try {
                    $insertQuery = $db->prepare("
                        INSERT INTO staff (staff_no, first_name, last_name, email, phone, position, department_id, staff_type_id, status, created_at, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), ?)
                    ");
                    $insertQuery->execute([
                        $record['staff_no'] ?? null,
                        $record['first_name'] ?? null,
                        $record['last_name'] ?? null,
                        $record['email'] ?? null,
                        $record['phone'] ?? null,
                        $record['position'] ?? null,
                        $record['department_id'] ?? null,
                        $record['staff_type_id'] ?? 2,
                        $this->user['id'] ?? null
                    ]);
                    $imported++;
                } catch (Exception $e) {
                    $failed++;
                }
            }

            return $this->success([
                'imported' => $imported,
                'failed' => $failed
            ], 'Staff import completed');

        } catch (Exception $e) {
            return $this->error('Failed to import staff: ' . $e->getMessage());
        }
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

    // =========================================================================
    // ONBOARDING
    // =========================================================================

    /**
     * GET /api/staff/onboarding        — list all onboardings
     * GET /api/staff/onboarding/{id}   — single onboarding + tasks + documents
     */
    public function getOnboarding($id = null, $data = [], $segments = [])
    {
        $db = \App\Database\Database::getInstance();
        try {
            if ($id) {
                $row = $db->query(
                    "SELECT * FROM vw_onboarding_dashboard WHERE onboarding_id = ?", [$id]
                )->fetch(\PDO::FETCH_ASSOC);
                if (!$row) return $this->error('Not found', 404);

                $tasks = $db->query(
                    "SELECT ot.*, u.name AS assigned_to_name, cb.name AS completed_by_name
                     FROM onboarding_tasks ot
                     LEFT JOIN users u  ON u.id  = ot.assigned_to
                     LEFT JOIN users cb ON cb.id = ot.completed_by
                     WHERE ot.onboarding_id = ?
                     ORDER BY ot.sequence ASC, ot.due_date ASC",
                    [$id]
                )->fetchAll(\PDO::FETCH_ASSOC);

                $docs = $db->query(
                    "SELECT * FROM onboarding_documents WHERE onboarding_id = ?", [$id]
                )->fetchAll(\PDO::FETCH_ASSOC);

                $reviews = $db->query(
                    "SELECT pr.*, CONCAT(r.first_name,' ',r.last_name) AS reviewer_name
                     FROM staff_probation_reviews pr
                     LEFT JOIN staff r ON r.id = pr.reviewer_id
                     WHERE pr.onboarding_id = ? ORDER BY pr.review_month ASC",
                    [$id]
                )->fetchAll(\PDO::FETCH_ASSOC);

                return $this->success([
                    'onboarding' => $row,
                    'tasks'      => $tasks,
                    'documents'  => $docs,
                    'reviews'    => $reviews,
                ]);
            }

            // List view
            $status     = $_GET['status']      ?? null;
            $staffId    = $_GET['staff_id']    ?? null;
            $deptId     = $_GET['department_id'] ?? null;
            $where = ['1=1']; $params = [];
            if ($status)  { $where[] = 'status = ?';      $params[] = $status; }
            if ($staffId) { $where[] = 'staff_id = ?';    $params[] = $staffId; }
            if ($deptId)  {
                // Join through staff table — use subquery
                $where[] = 'staff_id IN (SELECT id FROM staff WHERE department_id = ?)';
                $params[] = $deptId;
            }

            $rows = $db->query(
                "SELECT * FROM vw_onboarding_dashboard WHERE " . implode(' AND ', $where) .
                " ORDER BY start_date DESC LIMIT 200",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);

            $stats = [
                'total'       => count($rows),
                'in_progress' => count(array_filter($rows, fn($r) => $r['status'] === 'in_progress')),
                'completed'   => count(array_filter($rows, fn($r) => $r['status'] === 'completed')),
                'overdue'     => count(array_filter($rows, fn($r) => ($r['overdue_tasks'] ?? 0) > 0)),
                'pending'     => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
            ];

            return $this->success(['onboardings' => $rows, 'stats' => $stats]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/staff/onboarding
     * Initiate onboarding for a staff member. Auto-generates tasks from templates.
     */
    public function postOnboarding($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        if (!$staffId) return $this->error('staff_id required');

        $db = \App\Database\Database::getInstance();
        try {
            // Check staff exists and get their type
            $staff = $db->query(
                "SELECT s.*, sc.id AS staff_category_id, st.id AS staff_type_id
                 FROM staff s
                 LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                 LEFT JOIN staff_types st ON st.id = s.staff_type_id
                 WHERE s.id = ?",
                [$staffId]
            )->fetch(\PDO::FETCH_ASSOC);
            if (!$staff) return $this->error('Staff not found', 404);

            // Check no active onboarding already running
            $existing = $db->query(
                "SELECT id FROM staff_onboarding WHERE staff_id = ? AND status IN ('pending','in_progress')",
                [$staffId]
            )->fetch();
            if ($existing) return $this->error('Staff already has an active onboarding record', 409);

            $startDate  = $data['start_date']   ?? date('Y-m-d');
            $probMonths = (int)($data['probation_months'] ?? 3);
            $target     = date('Y-m-d', strtotime($startDate . " +$probMonths months"));
            $mentorId   = $data['mentor_id']    ?? null;
            $contractType = $data['contract_type'] ?? 'probation';

            // Create onboarding record
            $db->query(
                "INSERT INTO staff_onboarding
                 (staff_id, mentor_id, contract_type, probation_months, start_date,
                  target_completion, expected_end_date, status, progress_percent,
                  initiated_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?)",
                [
                    $staffId, $mentorId, $contractType, $probMonths,
                    $startDate, $target, $target,
                    $this->user['id'] ?? null,
                    $data['notes'] ?? null,
                ]
            );
            $onboardingId = $db->lastInsertId();

            // Auto-generate tasks from templates
            $staffTypeId  = (int)($staff['staff_type_id'] ?? 0);
            $templates = $db->query(
                "SELECT * FROM onboarding_task_templates WHERE status = 'active' ORDER BY display_order"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $tasksCreated = 0;
            foreach ($templates as $t) {
                // Check if this template applies to this staff type
                $appliesToTypes = json_decode($t['applies_to_type_ids'] ?? 'null', true);
                if ($appliesToTypes !== null && $staffTypeId && !in_array($staffTypeId, $appliesToTypes)) {
                    continue; // Skip — not applicable to this staff type
                }

                $dueDate = date('Y-m-d', strtotime($startDate . " +" . $t['days_from_start'] . " days"));

                $db->query(
                    "INSERT INTO onboarding_tasks
                     (onboarding_id, task_name, description, category,
                      due_date, priority, sequence, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                    [
                        $onboardingId,
                        $t['task_name'],
                        $t['description'],
                        $t['category'],
                        $dueDate,
                        $t['priority'],
                        $t['display_order'],
                    ]
                );
                $tasksCreated++;
            }

            // Update status to in_progress
            $db->query("UPDATE staff_onboarding SET status = 'in_progress' WHERE id = ?", [$onboardingId]);

            // Also auto-create contract record
            $db->query(
                "INSERT INTO staff_contracts (staff_id, contract_type, start_date, end_date, salary, status, created_by)
                 VALUES (?, ?, ?, ?, ?, 'active', ?)",
                [
                    $staffId, $contractType, $startDate, $target,
                    $staff['salary'] ?? 0,
                    $this->user['id'] ?? null,
                ]
            );

            return $this->success([
                'onboarding_id' => (int)$onboardingId,
                'tasks_created' => $tasksCreated,
                'start_date'    => $startDate,
                'target_date'   => $target,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/staff/onboarding/{id}
     * Update onboarding status or overall notes.
     */
    public function putOnboarding($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('onboarding id required');
        $db = \App\Database\Database::getInstance();
        try {
            $allowed = ['status','mentor_id','target_completion','probation_outcome','notes'];
            $set = []; $params = [];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $set[] = "$f = ?"; $params[] = $data[$f];
                }
            }
            // If completing, record completion date
            if (($data['status'] ?? '') === 'completed') {
                $set[] = 'actual_completion = ?'; $params[] = date('Y-m-d');
                $set[] = 'completion_date = ?';   $params[] = date('Y-m-d');
                $set[] = 'progress_percent = ?';  $params[] = 100;
            }
            if (empty($set)) return $this->error('Nothing to update');
            $params[] = $id;
            $db->query("UPDATE staff_onboarding SET " . implode(', ', $set) . " WHERE id = ?", $params);
            return $this->success(['updated' => true]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * PUT /api/staff/onboarding-task/{id}
     * Mark a task complete, in_progress, blocked, or skipped.
     */
    public function putOnboardingTask($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('task id required');
        $db = \App\Database\Database::getInstance();
        try {
            $newStatus = $data['status'] ?? 'completed';
            $notes     = $data['notes'] ?? null;
            $userId    = $this->user['id'] ?? null;

            $set = "status = ?, notes = ?, updated_at = NOW()";
            $params = [$newStatus, $notes];

            if ($newStatus === 'completed') {
                $set .= ", completed_date = NOW(), completed_by = ?";
                $params[] = $userId;
            }
            $params[] = $id;
            $db->query("UPDATE onboarding_tasks SET $set WHERE id = ?", $params);

            // Recalculate onboarding progress %
            $task = $db->query("SELECT onboarding_id FROM onboarding_tasks WHERE id = ?", [$id])->fetch();
            if ($task) {
                $this->_recalcOnboardingProgress((int)$task['onboarding_id'], $db);
            }

            return $this->success(['updated' => true]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/staff/onboarding-document
     * Record that a document has been collected.
     */
    public function postOnboardingDocument($id = null, $data = [], $segments = [])
    {
        $onboardingId = $data['onboarding_id'] ?? null;
        $staffId      = $data['staff_id']      ?? null;
        $docType      = $data['document_type'] ?? null;
        if (!$onboardingId || !$staffId || !$docType) return $this->error('onboarding_id, staff_id, document_type required');

        $db = \App\Database\Database::getInstance();
        try {
            $db->query(
                "INSERT INTO onboarding_documents
                 (onboarding_id, staff_id, document_type, document_name,
                  is_original_seen, is_copy_filed, verified_by, verified_at, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)",
                [
                    $onboardingId, $staffId, $docType,
                    $data['document_name'] ?? null,
                    $data['is_original_seen'] ?? 0,
                    $data['is_copy_filed']    ?? 0,
                    $this->user['id'] ?? null,
                    $data['notes']    ?? null,
                ]
            );
            // Auto-complete the matching documentation task
            $db->query(
                "UPDATE onboarding_tasks
                 SET status = 'completed', completed_date = NOW()
                 WHERE onboarding_id = ?
                   AND category = 'documentation'
                   AND LOWER(task_name) LIKE ?
                   AND status != 'completed'
                 LIMIT 1",
                [$onboardingId, '%' . strtolower(str_replace('_', ' ', $docType)) . '%']
            );
            $task = $db->query("SELECT id FROM onboarding_tasks WHERE onboarding_id = ? LIMIT 1", [$onboardingId])->fetch();
            if ($task) $this->_recalcOnboardingProgress((int)$onboardingId, $db);
            return $this->success(['id' => (int)$db->lastInsertId()], 201);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * POST /api/staff/probation-review
     * Record a probation review outcome.
     */
    public function postProbationReview($id = null, $data = [], $segments = [])
    {
        $onboardingId = $data['onboarding_id'] ?? null;
        $staffId      = $data['staff_id']      ?? null;
        if (!$onboardingId || !$staffId) return $this->error('onboarding_id and staff_id required');

        $db = \App\Database\Database::getInstance();
        try {
            $db->query(
                "INSERT INTO staff_probation_reviews
                 (onboarding_id, staff_id, review_month, review_date, reviewer_id,
                  overall_rating, attendance_score, performance_score, conduct_score,
                  strengths, areas_to_improve, outcome, outcome_notes, next_review_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $onboardingId, $staffId,
                    $data['review_month']       ?? 1,
                    $data['review_date']        ?? date('Y-m-d'),
                    $this->user['id']           ?? null,
                    $data['overall_rating']     ?? 'satisfactory',
                    $data['attendance_score']   ?? null,
                    $data['performance_score']  ?? null,
                    $data['conduct_score']      ?? null,
                    $data['strengths']          ?? null,
                    $data['areas_to_improve']   ?? null,
                    $data['outcome']            ?? 'continue',
                    $data['outcome_notes']      ?? null,
                    $data['next_review_date']   ?? null,
                ]
            );

            // Handle outcome
            if (($data['outcome'] ?? '') === 'confirm_permanent') {
                $db->query(
                    "UPDATE staff_onboarding SET probation_outcome='confirmed', status='completed', actual_completion=? WHERE id=?",
                    [date('Y-m-d'), $onboardingId]
                );
                // Update staff contract to permanent
                $db->query(
                    "UPDATE staff_contracts SET contract_type='permanent', status='active', end_date=NULL WHERE staff_id=? AND status='active'",
                    [$staffId]
                );
            } elseif (($data['outcome'] ?? '') === 'extend_probation') {
                $extendMonths = (int)($data['extend_months'] ?? 3);
                $newTarget = date('Y-m-d', strtotime(date('Y-m-d') . " +$extendMonths months"));
                $db->query(
                    "UPDATE staff_onboarding SET probation_outcome='extended', target_completion=?, expected_end_date=? WHERE id=?",
                    [$newTarget, $newTarget, $onboardingId]
                );
            } elseif (($data['outcome'] ?? '') === 'terminate') {
                $db->query(
                    "UPDATE staff_onboarding SET probation_outcome='terminated', status='terminated' WHERE id=?",
                    [$onboardingId]
                );
                $db->query("UPDATE staff SET status='inactive' WHERE id=?", [$staffId]);
            }

            return $this->success(['id' => (int)$db->lastInsertId()]);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/staff/onboarding-templates
     * List all task templates (for HR to customise before generating).
     */
    public function getOnboardingTemplates($id = null, $data = [], $segments = [])
    {
        $db = \App\Database\Database::getInstance();
        try {
            $rows = $db->query(
                "SELECT * FROM onboarding_task_templates WHERE status='active' ORDER BY display_order"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    /**
     * GET /api/staff/onboarding-pending
     * All overdue or pending tasks across all active onboardings — HR dashboard feed.
     */
    public function getOnboardingPending($id = null, $data = [], $segments = [])
    {
        $db = \App\Database\Database::getInstance();
        try {
            $rows = $db->query(
                "SELECT * FROM vw_onboarding_pending_by_role ORDER BY is_overdue DESC, due_date ASC LIMIT 100"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows);
        } catch (\Exception $e) {
            return $this->serverError($e->getMessage());
        }
    }

    private function _recalcOnboardingProgress(int $onboardingId, $db): void
    {
        $counts = $db->query(
            "SELECT COUNT(*) AS total,
                    SUM(status='completed') AS done,
                    SUM(status='skipped')   AS skipped
             FROM onboarding_tasks WHERE onboarding_id = ?",
            [$onboardingId]
        )->fetch(\PDO::FETCH_ASSOC);

        $active = (int)$counts['total'] - (int)$counts['skipped'];
        $pct    = $active > 0 ? round((int)$counts['done'] * 100 / $active) : 0;

        $db->query(
            "UPDATE staff_onboarding SET progress_percent = ? WHERE id = ?",
            [$pct, $onboardingId]
        );
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
        try {
            $where = ["s.status <> 'inactive'", "(LOWER(st.name) LIKE '%teach%' OR s.tsc_no IS NOT NULL)"];
            $params = [];
            if (!empty($_GET['department_id'])) { $where[] = 's.department_id = ?'; $params[] = (int)$_GET['department_id']; }
            $rows = $this->db->query(
                "SELECT s.id, s.staff_no AS employee_id, s.staff_no, s.first_name, s.last_name,
                        s.profile_pic_url AS photo_url, s.department_id, d.name AS department_name,
                        s.position, s.status, s.user_id, s.tsc_no,
                        GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_name,
                        COUNT(DISTINCT tsa.subject_id) AS subjects_count,
                        MAX(CASE WHEN cta.staff_id IS NOT NULL THEN 1 ELSE 0 END) AS is_class_teacher
                 FROM staff s
                 LEFT JOIN staff_types st ON st.id = s.staff_type_id
                 LEFT JOIN departments d ON d.id = s.department_id
                 LEFT JOIN user_roles ur ON ur.user_id = s.user_id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 LEFT JOIN staff_class_assignments tsa ON tsa.staff_id = s.id AND tsa.role = 'subject_teacher' AND tsa.status = 'active'
                 LEFT JOIN staff_class_assignments cta ON cta.staff_id = s.id AND cta.role = 'class_teacher' AND cta.status = 'active'
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY s.id ORDER BY s.first_name, s.last_name",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows);
        } catch (\Throwable $e) {
            return $this->serverError('Failed to load teachers', $e->getMessage());
        }
    }

    /** GET /api/staff/non-teaching */
    public function getNonTeaching($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.non_teaching.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        try {
            $where = ["s.status <> 'inactive'", "NOT (LOWER(COALESCE(st.name,'')) LIKE '%teach%' OR s.tsc_no IS NOT NULL)"];
            $params = [];
            if (!empty($_GET['department_id'])) { $where[] = 's.department_id = ?'; $params[] = (int)$_GET['department_id']; }
            $rows = $this->db->query(
                "SELECT s.*, d.name AS department_name, st.name AS staff_type_name,
                        CONCAT(sp.first_name, ' ', sp.last_name) AS supervisor_name,
                        GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names
                 FROM staff s
                 LEFT JOIN staff_types st ON st.id = s.staff_type_id
                 LEFT JOIN departments d ON d.id = s.department_id
                 LEFT JOIN staff sp ON sp.id = s.supervisor_id
                 LEFT JOIN user_roles ur ON ur.user_id = s.user_id
                 LEFT JOIN roles r ON r.id = ur.role_id
                 WHERE " . implode(' AND ', $where) . "
                 GROUP BY s.id ORDER BY d.name, s.first_name, s.last_name",
                $params
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows);
        } catch (\Throwable $e) {
            return $this->serverError('Failed to load non-teaching staff', $e->getMessage());
        }
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
        $staffId = (int)($data['staff_id'] ?? 0);
        $roleId = (int)($data['role_id'] ?? 0);
        if (!$staffId || !$roleId) return $this->badRequest('staff_id and role_id are required');
        $staff = $this->db->query('SELECT id, user_id FROM staff WHERE id = ? LIMIT 1', [$staffId])->fetch(\PDO::FETCH_ASSOC);
        if (!$staff || !(int)$staff['user_id']) return $this->badRequest('Staff member has no linked user account');
        $exists = $this->db->query('SELECT id FROM user_roles WHERE user_id = ? AND role_id = ? LIMIT 1', [(int)$staff['user_id'], $roleId])->fetch();
        if (!$exists) {
            $this->db->query('INSERT INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())', [(int)$staff['user_id'], $roleId]);
        }
        $this->access->audit('assign_role', 'staff', $staffId, null, ['role_id' => $roleId]);
        return $this->success(['staff_id' => $staffId, 'role_id' => $roleId], 'Role assigned');
    }

    /** DELETE /api/staff/role-assignments/{roleId}?staff_id=X */
    public function deleteRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        $roleId = (int)($id ?? $data['role_id'] ?? 0);
        $staffId = (int)($_GET['staff_id'] ?? $data['staff_id'] ?? 0);
        if (!$staffId || !$roleId) return $this->badRequest('staff_id and role_id are required');
        $staff = $this->db->query('SELECT user_id FROM staff WHERE id = ? LIMIT 1', [$staffId])->fetch(\PDO::FETCH_ASSOC);
        if (!$staff) return $this->notFound('Staff member not found');
        $this->db->query('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?', [(int)$staff['user_id'], $roleId]);
        $this->access->audit('remove_role', 'staff', $staffId, ['role_id' => $roleId], null);
        return $this->success(null, 'Role removed');
    }

    /** GET /api/staff/id-cards */
    public function getIdCards($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        $where = ['1=1']; $params = [];
        if (!empty($_GET['staff_id'])) { $where[] = 'c.staff_id = ?'; $params[] = (int)$_GET['staff_id']; }
        if (!empty($_GET['status'])) { $where[] = 'c.status = ?'; $params[] = $_GET['status']; }
        $rows = $this->db->query(
            "SELECT c.*, s.staff_no, s.first_name, s.last_name, s.position, s.profile_pic_url,
                    d.name AS department_name
             FROM staff_id_cards c JOIN staff s ON s.id = c.staff_id
             LEFT JOIN departments d ON d.id = s.department_id
             WHERE " . implode(' AND ', $where) . " ORDER BY c.created_at DESC",
            $params
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
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
            $this->db->query(
                "INSERT INTO staff_id_cards (staff_id, card_number, status, issued_at, expires_at, generated_by, created_at, updated_at)
                 VALUES (?, ?, 'generated', NULL, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE status='generated', expires_at=VALUES(expires_at), generated_by=VALUES(generated_by), updated_at=NOW()",
                [$staffId, $number, $data['expires_at'] ?? date('Y-m-d', strtotime('+2 years')), $this->access->userId()]
            );
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
        $this->db->query("UPDATE staff_id_cards SET status='issued', issued_at=NOW(), issued_by=?, updated_at=NOW() WHERE staff_id=?", [$this->access->userId(), $staffId]);
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
            $where=['1=1'];$params=[];
            if($id){$where[]='pr.id=?';$params[]=(int)$id;}
            if(!empty($_GET['staff_id'])){$where[]='pr.staff_id=?';$params[]=(int)$_GET['staff_id'];}
            if(!empty($_GET['academic_year_id'])){$where[]='pr.academic_year_id=?';$params[]=(int)$_GET['academic_year_id'];}
            if(!empty($_GET['status'])){$where[]='pr.status=?';$params[]=$_GET['status'];}
            $rows=$this->db->query("SELECT pr.*,CONCAT(s.first_name,' ',s.last_name) teacher_name,
                    CONCAT(r.first_name,' ',r.last_name) reviewer_name,ay.year_name academic_year
                FROM staff_performance_reviews pr JOIN staff s ON s.id=pr.staff_id
                LEFT JOIN staff r ON r.id=pr.reviewer_id LEFT JOIN academic_years ay ON ay.id=pr.academic_year_id
                WHERE ".implode(' AND ',$where)." ORDER BY pr.review_date DESC,pr.id DESC",$params)->fetchAll(\PDO::FETCH_ASSOC);
            if($id) return $rows ? $this->success($rows[0]) : $this->notFound('Performance review not found');
            return $this->success($rows);
        } catch(\Throwable $e){return $this->serverError('Failed to load performance reviews',$e->getMessage());}
    }

    /** POST /api/staff/performance-reviews */
    public function postPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        $staffId=(int)($data['staff_id']??0);$yearId=(int)($data['academic_year_id']??0);
        if(!$staffId||!$yearId)return $this->badRequest('staff_id and academic_year_id are required');
        $reviewerStaffId=(int)($data['reviewer_id']??$this->access->staffId()??0);
        if(!$reviewerStaffId)return $this->badRequest('reviewer_id is required');
        $this->db->query("INSERT INTO staff_performance_reviews(staff_id,academic_year_id,term_id,review_period,review_type,reviewer_id,review_date,overall_score,performance_grade,overall_rating,strengths,areas_for_improvement,recommendations,action_plan,follow_up_date,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())",[
            $staffId,$yearId,$data['term_id']??null,$data['review_period']??null,$data['review_type']??'annual',$reviewerStaffId,$data['review_date']??date('Y-m-d'),$data['overall_score']??null,$data['performance_grade']??null,$data['overall_rating']??null,$data['strengths']??null,$data['areas_for_improvement']??null,$data['recommendations']??null,$data['action_plan']??null,$data['follow_up_date']??null,$data['status']??'draft'
        ]);
        $newId=(int)$this->db->lastInsertId();$this->access->audit('create_performance_review','staff_performance_review',$newId,null,$data);
        return $this->created(['id'=>$newId],'Performance review created');
    }

    /** PUT /api/staff/performance-reviews/{id} */
    public function putPerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        if(!$id)return $this->badRequest('Review ID is required');
        $before=$this->db->query('SELECT * FROM staff_performance_reviews WHERE id=?',[(int)$id])->fetch(\PDO::FETCH_ASSOC);
        if(!$before)return $this->notFound('Performance review not found');
        $allowed=['review_period','review_type','review_date','overall_score','performance_grade','overall_rating','strengths','areas_for_improvement','recommendations','action_plan','follow_up_date','status','term_id'];
        $sets=[];$params=[];foreach($allowed as $field){if(array_key_exists($field,$data)){$sets[]="$field=?";$params[]=$data[$field];}}
        if(!$sets)return $this->badRequest('No supported fields supplied');$params[]=(int)$id;
        $this->db->query('UPDATE staff_performance_reviews SET '.implode(',',$sets).',updated_at=NOW() WHERE id=?',$params);
        $this->access->audit('update_performance_review','staff_performance_review',(int)$id,$before,$data);
        return $this->success(['id'=>(int)$id],'Performance review updated');
    }

    /** DELETE /api/staff/performance-reviews/{id} — drafts only */
    public function deletePerformanceReviews($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.performance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        if(!$id)return $this->badRequest('Review ID is required');
        $before=$this->db->query('SELECT * FROM staff_performance_reviews WHERE id=?',[(int)$id])->fetch(\PDO::FETCH_ASSOC);
        if(!$before)return $this->notFound('Performance review not found');
        if(($before['status']??'')!=='draft')return $this->conflict('Only draft reviews can be deleted');
        $this->db->query('DELETE FROM staff_performance_reviews WHERE id=?',[(int)$id]);
        $this->access->audit('delete_performance_review','staff_performance_review',(int)$id,$before,null);
        return $this->success(null,'Performance review deleted');
    }


    /** GET /api/staff/leave-types */
    public function getLeaveTypes($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        $rows=$this->db->query("SELECT id,code,name,description,days_allowed,requires_approval,is_paid,applicable_to FROM leave_types WHERE status='active' ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

    /** GET /api/staff/available-roles */
    public function getAvailableRoles($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        $rows=$this->db->query("SELECT id,name,description,scope,is_system FROM roles WHERE is_active=1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

    /** GET /api/staff/role-assignments?staff_id=X */
    public function getRoleAssignments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        $staffId=(int)($_GET['staff_id']??$data['staff_id']??$id??0);
        if(!$staffId)return $this->badRequest('staff_id is required');
        $rows=$this->db->query("SELECT r.id role_id,r.name,r.description,ur.created_at FROM staff s JOIN user_roles ur ON ur.user_id=s.user_id JOIN roles r ON r.id=ur.role_id WHERE s.id=? ORDER BY r.name",[$staffId])->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

}
