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
use App\API\Services\payments\StatutoryRemittanceService;
use App\API\Services\TeacherScopeService;
use App\API\Services\StaffMigrationService;

/**
 * StaffController - Explicit REST endpoints for Staff Management
 * 
 * Every method in StaffAPI has its own unique, explicit endpoint
 * Router calls methods with signature: methodName($id, $data, $segments)
 */
class StaffController extends BaseController
{
    private const STAFF_DIRECTORY_VIEW_ROLES = [
        'system administrator',
        'school administrator',
        'director',
        'headteacher',
        'deputy head - academic',
        'deputy head academic',
        'deputy head - discipline',
        'deputy head discipline',
    ];

    private const STAFF_LIFECYCLE_VIEW_ROLES = [
        'system administrator',
        'school administrator',
        'director',
        'headteacher',
        'deputy head - academic',
        'deputy head academic',
        'deputy head - discipline',
        'deputy head discipline',
    ];

    private const STAFF_APPOINTMENTS_VIEW_ROLES = [
        'system administrator',
        'school administrator',
        'director',
        'headteacher',
        'deputy head - discipline',
        'deputy head discipline',
    ];

    private const STAFF_ONBOARDING_VIEW_ROLES = [
        'system administrator',
        'school administrator',
        'director',
        'headteacher',
        'deputy head - academic',
        'deputy head academic',
        'deputy head - discipline',
        'deputy head discipline',
    ];

    private const STAFF_ONBOARDING_MANAGE_ROLES = [
        'system administrator',
        'school administrator',
        'headteacher',
    ];

    private const STAFF_PERFORMANCE_VIEW_ROLES = [
        'system administrator',
        'school administrator',
        'director',
        'headteacher',
        'deputy head - academic',
        'deputy head academic',
        'deputy head - discipline',
        'deputy head discipline',
    ];

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

    /** GET /api/staff/teacher-scope — effective blended academic scope for self */
    public function getTeacherScope($id = null, $data = [], $segments = [])
    {
        if (empty($this->user)) return $this->unauthorized('Authentication required');
        $yearId = !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $termId = !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null;
        return $this->success((new TeacherScopeService($this->db->getConnection()))->forUser($this->user, $yearId, $termId));
    }


    // ==================== BASE CRUD OPERATIONS ====================

    /**
     * GET /api/staff - List all staff
     * GET /api/staff/{id} - Get specific staff member
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.view', self::STAFF_DIRECTORY_VIEW_ROLES)) return $denied;
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
     * GET /api/staff/school-administrator-bootstrap
     * One-time bootstrap state and reference data. Test accounts never satisfy
     * the production bootstrap guard.
     */
    public function getSchoolAdministratorBootstrap($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasRole('System Administrator')) {
            return $this->forbidden('Only a System Administrator may bootstrap the first School Administrator.');
        }

        $pdo = $this->db->getConnection();
        $existing = $pdo->query("
            SELECT u.id AS user_id, s.id AS staff_id, u.username,
                   CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                   p.email, u.status, s.staff_no
            FROM users u
            JOIN persons p ON p.id = u.person_id
            JOIN staff s ON s.person_id = p.id
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'School Administrator' AND COALESCE(u.is_test_user, 0) = 0
            ORDER BY u.id LIMIT 1
        ")->fetch(\PDO::FETCH_ASSOC) ?: null;

        $departments = $pdo->query("SELECT id, name, code FROM departments ORDER BY name")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $staffTypes = $pdo->query("SELECT id, name FROM staff_types WHERE is_active = 1 ORDER BY name")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $categories = $pdo->query("SELECT id, staff_type_id, category_name AS name FROM staff_categories WHERE is_active = 1 ORDER BY category_name")
            ->fetchAll(\PDO::FETCH_ASSOC);
        $supervisors = $pdo->query("
            SELECT s.id, s.staff_no, CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS name
            FROM staff s JOIN persons p ON p.id = s.person_id
            WHERE s.status = 'active' AND s.data_scope='live' ORDER BY name
        ")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->success([
            'available' => $existing === null,
            'existing_administrator' => $existing,
            'role' => ['id' => 4, 'name' => 'School Administrator'],
            'departments' => $departments,
            'staff_types' => $staffTypes,
            'staff_categories' => $categories,
            'supervisors' => $supervisors,
        ]);
    }

    /** POST /api/staff/school-administrator-bootstrap */
    public function postSchoolAdministratorBootstrap($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasRole('System Administrator')) {
            return $this->forbidden('Only a System Administrator may bootstrap the first School Administrator.');
        }

        $required = [
            'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'gender',
            'national_id_no', 'address', 'marital_status', 'emergency_contact_name',
            'emergency_contact_phone', 'emergency_contact_relationship', 'department_id',
            'position', 'employment_date', 'contract_type', 'staff_type_id',
            'staff_category_id', 'kra_pin', 'nssf_no', 'nhif_no', 'bank_name',
            'bank_account', 'salary', 'work_start_time', 'work_end_time',
            'late_threshold_minutes'
        ];
        $missing = array_values(array_filter($required, static fn($key) => !isset($data[$key]) || trim((string)$data[$key]) === ''));
        if ($missing) {
            return $this->badRequest('Complete every required School Administrator onboarding field.', ['fields' => $missing]);
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->badRequest('Enter a valid email address.');
        }
        if (!in_array($data['gender'], ['male', 'female', 'other'], true)
            || !in_array($data['contract_type'], ['permanent', 'contract', 'temporary'], true)) {
            return $this->badRequest('Gender or contract type is invalid.');
        }
        if ((float)$data['salary'] < 0 || (int)$data['late_threshold_minutes'] < 0) {
            return $this->badRequest('Salary and late threshold cannot be negative.');
        }
        foreach (['date_of_birth', 'employment_date'] as $dateField) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string)$data[$dateField]);
            if (!$date || $date->format('Y-m-d') !== $data[$dateField]) {
                return $this->badRequest("{$dateField} must be a valid YYYY-MM-DD date.");
            }
        }
        if (strtotime($data['work_end_time']) <= strtotime($data['work_start_time'])) {
            return $this->badRequest('Work end time must be later than work start time.');
        }

        $pdo = $this->db->getConnection();
        $reference = $pdo->prepare("
            SELECT
              EXISTS(SELECT 1 FROM departments WHERE id=?) AS department_ok,
              EXISTS(SELECT 1 FROM staff_types WHERE id=? AND is_active=1) AS type_ok,
              EXISTS(SELECT 1 FROM staff_categories WHERE id=? AND staff_type_id=? AND is_active=1) AS category_ok
        ");
        $reference->execute([(int)$data['department_id'], (int)$data['staff_type_id'], (int)$data['staff_category_id'], (int)$data['staff_type_id']]);
        $validReference = $reference->fetch(\PDO::FETCH_ASSOC) ?: [];
        if (in_array(0, array_map('intval', $validReference), true)) {
            return $this->badRequest('Select a valid active department, staff type and matching staff category.');
        }
        if (!empty($data['supervisor_id'])) {
            $supervisor = $pdo->prepare("SELECT 1 FROM staff WHERE id=? AND status='active' AND data_scope='live'");
            $supervisor->execute([(int)$data['supervisor_id']]);
            if (!$supervisor->fetchColumn()) return $this->badRequest('Select a valid active supervisor.');
        }
        $lock = (int)$pdo->query("SELECT GET_LOCK('kingsway:first_school_administrator', 10)")->fetchColumn();
        if ($lock !== 1) return $this->conflict('The School Administrator bootstrap is already being processed.');

        try {
            $exists = (int)$pdo->query("
                SELECT COUNT(*) FROM users u
                JOIN persons p ON p.id=u.person_id
                JOIN staff s ON s.person_id=p.id
                JOIN user_roles ur ON ur.user_id=u.id
                JOIN roles r ON r.id=ur.role_id
                WHERE r.name='School Administrator' AND COALESCE(u.is_test_user,0)=0
            ")->fetchColumn();
            if ($exists > 0) {
                return $this->conflict('The first production School Administrator has already been established.');
            }

            $pdo->beginTransaction();
            $data['role_id'] = 4;
            $data['role_ids'] = [4];
            $data['status'] = 'active';
            $data['force_password_change'] = 1;
            $data['defer_invitation_delivery'] = true;
            $data['staff_info'] = array_merge($data['staff_info'] ?? [], $data);
            $result = $this->api->create($data);
            if (($result['status'] ?? '') !== 'success') {
                if ($pdo->inTransaction()) $pdo->rollBack();
                return $this->handleResponse($result);
            }

            $email = strtolower(trim((string)$data['email']));
            $lookup = $pdo->prepare("SELECT u.id AS user_id, s.id AS staff_id, s.staff_no FROM users u JOIN persons p ON p.id=u.person_id JOIN staff s ON s.person_id=p.id WHERE LOWER(p.email)=? LIMIT 1");
            $lookup->execute([$email]);
            $created = $lookup->fetch(\PDO::FETCH_ASSOC);
            if (!$created) throw new \RuntimeException('The created School Administrator could not be verified.');

            $pdo->prepare('UPDATE users SET is_test_user=0 WHERE id=?')->execute([(int)$created['user_id']]);
            $pdo->prepare('INSERT INTO staff_attendance_profiles (staff_id,work_start_time,work_end_time,late_threshold_minutes,is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE work_start_time=VALUES(work_start_time),work_end_time=VALUES(work_end_time),late_threshold_minutes=VALUES(late_threshold_minutes),is_active=1')
                ->execute([(int)$created['staff_id'], $data['work_start_time'], $data['work_end_time'], (int)$data['late_threshold_minutes']]);
            $pdo->commit();
            try {
                (new StaffMigrationService($pdo))->processEmailQueue(1);
            } catch (\Throwable $mailError) {
                \App\API\Services\Logger::legacyError('[SchoolAdministratorBootstrap] Invitation queued but immediate delivery failed: '.$mailError->getMessage());
            }

            return $this->created([
                'user_id' => (int)$created['user_id'],
                'staff_id' => (int)$created['staff_id'],
                'staff_no' => $created['staff_no'],
                'email' => $email,
                'invitation_sent' => true,
            ], 'The first School Administrator was created and the secure invitation was sent.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \App\API\Services\Logger::legacyError('[SchoolAdministratorBootstrap] '.$e->getMessage());
            return $this->serverError('The School Administrator bootstrap could not be completed.');
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('kingsway:first_school_administrator')");
        }
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
        if ($denied = $this->guardStaffDomain('staff.performance.view', self::STAFF_PERFORMANCE_VIEW_ROLES)) return $denied;
        try {
            $params = array_merge($_GET ?? [], $data);
            return $this->handleResponse($this->api->getAcademicKPISummary(
                (int)$id,
                isset($params['academic_year_id']) ? (int)$params['academic_year_id'] : null
            ));
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    // ==================== ADDITIONAL STAFF MANAGEMENT ENDPOINTS ====================

    /**
     * GET /api/staff/lifecycle - Get staff lifecycle records
     */
    public function getLifecycle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.lifecycle.view', self::STAFF_LIFECYCLE_VIEW_ROLES)) return $denied;
        try {
            $params = array_merge($_GET ?? [], $data);
            return $this->success(
                !empty($params['staff_id'])
                    ? $this->lifecycleService->timeline((int)$params['staff_id'])
                    : $this->lifecycleService->dashboard($params),
                'Staff lifecycle records retrieved'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /**
     * GET /api/staff/appointments - Get staff appointments
     */
    public function getAppointments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.appointments.view', self::STAFF_APPOINTMENTS_VIEW_ROLES)) return $denied;
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
        $allowedRoles = ['system administrator', 'admin', 'school_admin', 'headteacher', 'director', 'human_resources'];
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
     *
     * School-domain staff return their employment/payroll profile. Users with no
     * linked staff record (system/account-only users such as the platform
     * operator) get an account-level profile flagged `_domain: 'system'` so the
     * UI can avoid showing school-employment fields they do not have.
     */
    public function getProfileGet($id = null, $data = [], $segments = [])
    {
        try {
            $viewingOwn = empty($id) && empty($_GET['staff_id']) && empty($data['staff_id']);
            $ownStaffId = $this->access->staffId();

            if ($viewingOwn && !$ownStaffId) {
                return $this->handleResponse($this->systemUserProfile());
            }

            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.directory.view',
                self::STAFF_DIRECTORY_VIEW_ROLES
            );
            return $this->handleResponse($this->api->getProfile($staffId));
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
    }

    /**
     * Account-level profile for authenticated users with no linked staff record.
     * Sourced from the person + user auth payload; never carries school
     * employment, payroll, or statutory data.
     */
    private function systemUserProfile(): array
    {
        $userId = $this->getUserId();
        $email = $this->user['email'] ?? $this->user['username'] ?? null;

        $stmt = $this->db->getConnection()->prepare(
            'SELECT p.first_name, p.middle_name, p.last_name, p.email,
                    p.phone, p.gender, p.dob AS date_of_birth,
                    u.username, u.status, u.last_login
             FROM users u
             LEFT JOIN persons p ON p.id = u.person_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $profile = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            '_domain'          => 'system',
            'first_name'       => $profile['first_name'] ?? $this->user['first_name'] ?? null,
            'middle_name'      => $profile['middle_name'] ?? null,
            'last_name'        => $profile['last_name'] ?? $this->user['last_name'] ?? null,
            'email'            => $profile['email'] ?? $email,
            'username'         => $profile['username'] ?? $this->user['username'] ?? null,
            'gender'           => $profile['gender'] ?? null,
            'date_of_birth'    => $profile['date_of_birth'] ?? null,
            'phone'            => $profile['phone'] ?? $this->user['phone'] ?? null,
            'account_status'   => $profile['status'] ?? $this->user['status'] ?? null,
            'last_login'       => $profile['last_login'] ?? $this->user['last_login'] ?? null,
            'roles'            => $this->access->roles(),
        ];
    }

    /**
     * GET /api/staff/schedule/get - Get staff schedule
     */
    public function getScheduleGet($id = null, $data = [], $segments = [])
    {
        try {
            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.directory.view',
                self::STAFF_DIRECTORY_VIEW_ROLES
            );
            return $this->handleResponse($this->api->getSchedule($staffId));
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
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
        catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }

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
        try { $data = $this->access->forceSelfScope(array_merge($_GET, $data)); } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }
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
        if (!$this->access->allows('staff.leave.manage', ['system administrator','school administrator'])) {
            try {
                $this->access->require('staff.leave.request');
            } catch (RuntimeException $error) {
                return $this->selfServiceError($error);
            }
            $data['staff_id'] = $this->access->staffId();
        }
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
        try {
            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.payslip.manage',
                ['system administrator', 'school administrator', 'director', 'accountant']
            );
            $month = $data['month'] ?? $_GET['month'] ?? date('m');
            $year = $data['year'] ?? $_GET['year'] ?? date('Y');
            return $this->handleResponse($this->api->viewPayslip($staffId, $month, $year));
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
    }

    /**
     * GET /api/staff/payroll/history - Get payroll history
     */
    public function getPayrollHistory($id = null, $data = [], $segments = [])
    {
        try {
            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.payslip.manage',
                ['system administrator', 'school administrator', 'director', 'accountant']
            );
            $filters = array_merge($_GET ?? [], is_array($data) ? $data : []);
            return $this->handleResponse(
                $this->api->getPayrollHistory($staffId, $filters)
            );
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
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
        try {
            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.payslip.manage',
                ['system administrator', 'school administrator', 'director', 'accountant']
            );
            $year = $data['year'] ?? $_GET['year'] ?? date('Y');
            return $this->handleResponse($this->api->downloadP9Form($staffId, $year));
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
    }

    /**
     * GET /api/staff/payroll/download-payslip - Download payslip
     */
    public function getPayrollDownloadPayslip($id = null, $data = [], $segments = [])
    {
        try {
            $staffId = $this->resolveSelfOrManagedStaffId(
                $id,
                $data,
                'staff.payslip.manage',
                ['system administrator', 'school administrator', 'director', 'accountant']
            );
            $month = $data['month'] ?? $_GET['month'] ?? date('m');
            $year = $data['year'] ?? $_GET['year'] ?? date('Y');
            return $this->handleResponse($this->api->downloadPayslip($staffId, $month, $year));
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        }
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
        if ($denied = $this->guardStaffDomain('staff.performance.view', self::STAFF_PERFORMANCE_VIEW_ROLES)) return $denied;
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
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
        if ($denied = $this->guardStaffDomain('staff.onboarding.view', self::STAFF_ONBOARDING_VIEW_ROLES)) return $denied;
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
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES)) return $denied;
        $data['initiated_by'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->createOnboarding($data));
    }

    /**
     * PUT /api/staff/onboarding/{id}
     * Update onboarding status or overall notes.
     */
    public function putOnboarding($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES)) return $denied;
        if (!$id) \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        return $this->handleResponse($this->onboardingManager->updateOnboarding((int)$id, $data));
    }

    /**
     * PUT /api/staff/onboarding-task/{id}
     * Mark a task complete, in_progress, blocked, or skipped.
     */
    public function putOnboardingTask($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES)) return $denied;
        if (!$id) \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        return $this->handleResponse($this->onboardingManager->updateTaskStatus((int)$id, $data));
    }

    /**
     * POST /api/staff/onboarding-document
     * Record that a document has been collected.
     */
    public function postOnboardingDocument($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES)) return $denied;
        $data['verified_by'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->recordDocument($data));
    }

    /**
     * POST /api/staff/probation-review
     * Record a probation review outcome.
     */
    public function postProbationReview($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES)) return $denied;
        $data['reviewer_id'] = $this->user['id'] ?? $this->user['user_id'] ?? null;
        return $this->handleResponse($this->onboardingManager->recordProbationReview($data));
    }

    /**
     * GET /api/staff/onboarding-templates
     * List all task templates (for HR to customise before generating).
     */
    public function getOnboardingTemplates($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.view', self::STAFF_ONBOARDING_VIEW_ROLES)) return $denied;
        return $this->handleResponse($this->onboardingManager->getActiveTemplates());
    }

    /**
     * GET /api/staff/onboarding-pending
     * All overdue or pending tasks across all active onboardings — HR dashboard feed.
     */
    public function getOnboardingPending($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.onboarding.view', self::STAFF_ONBOARDING_VIEW_ROLES)) return $denied;
        return $this->handleResponse($this->onboardingManager->getPendingTasks());
    }

    // ========================================================================
    // STAFF SECURITY-PASS ENDPOINTS
    //
    // Compatibility: route and permission identifiers retain id-card naming.
    // ========================================================================

    /**
     * POST /api/staff/id-card/generate
     * Legacy-compatible route for generating one staff security pass.
     */
    public function postIdCardGenerate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;

        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId <= 0) {
            return $this->badRequest('Staff ID is required');
        }

        $side = $data['side'] ?? 'both';
        $printMode = $data['print_mode'] ?? 'direct_card';
        $result = $this->idCardGenerator->generateIDCard(
            $staffId,
            'pdf',
            $side,
            $printMode
        );

        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/id-card/generate-bulk-pdf
     * Generate bulk PDF for selected staff with A4 layout
     */
    public function postIdCardGenerateBulkPdf($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.view', ['system administrator','school administrator','director','headteacher'])) return $denied;

        $staffIds = $data['staff_ids'] ?? [];
        if (empty($staffIds) || !is_array($staffIds)) {
            return $this->badRequest('Staff IDs array is required');
        }

        $printMode = $data['print_mode'] ?? 'a4_pdf';
        $includeFront = $data['include_front'] ?? true;
        $includeBack = $data['include_back'] ?? true;

        $result = $this->idCardGenerator->generateBulkIDCardsPDF(
            $staffIds,
            $printMode,
            $includeFront,
            $includeBack,
            null,
            true
        );

        return $this->handleResponse($result);
    }

    /**
     * POST /api/staff/id-card/print-single
     * Prepare a print-ready copy of one existing staff security pass.
     */
    public function postIdCardPrintSingle($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.view', ['system administrator','school administrator','director','headteacher'])) return $denied;

        $staffId = $data['staff_id'] ?? ($segments[0] ?? null);
        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $side = $data['side'] ?? 'both';
        $printMode = $data['print_mode'] ?? 'direct_card';

        $result = $this->idCardGenerator->generatePrintableSingle((int) $staffId, $side, $printMode);
        return $this->handleResponse($result);
    }

    /** POST /api/staff/id-cards-bulk-generate */
    public function postIdCardsBulkGenerate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;

        $staffIds = $data['staff_ids'] ?? [];
        if (empty($staffIds) || !is_array($staffIds)) {
            return $this->badRequest('staff_ids array is required');
        }

        $printMode = $data['print_mode'] ?? 'a4_pdf';
        $includeFront = array_key_exists('include_front', $data) ? (bool)$data['include_front'] : true;
        $includeBack = array_key_exists('include_back', $data) ? (bool)$data['include_back'] : true;

        try {
            $normalizedStaffIds = array_values(
                array_unique(
                    array_filter(
                        array_map('intval', $staffIds),
                        static fn (int $staffId): bool => $staffId > 0
                    )
                )
            );

            if ($normalizedStaffIds === []) {
                return $this->badRequest('staff_ids must contain valid staff IDs');
            }

            $result = $this->idCardGenerator->generateBulkIDCardsPDF(
                $normalizedStaffIds,
                $printMode,
                $includeFront,
                $includeBack,
                null,
                false
            );

            if (($result['status'] ?? 'error') !== 'success') {
                return $this->handleResponse($result);
            }

            $persisted = $this->recordsService->persistBulkGeneratedIdCards(
                $normalizedStaffIds,
                null,
                $this->access->userId()
            );

            $this->access->audit(
                'bulk_generate_staff_security_passes',
                'staff',
                null,
                null,
                [
                    'count' => count($persisted),
                    'print_mode' => $printMode,
                    'include_front' => $includeFront,
                    'include_back' => $includeBack,
                ]
            );

            return $this->success(
                [
                    'document' => $result['data'] ?? null,
                    'passes' => $persisted,
                    // Legacy response key retained for existing consumers.
                    'cards' => $persisted,
                    'count' => count($persisted),
                ],
                'Staff security passes generated successfully'
            );
        } catch (\Throwable $e) {
            return $this->serverError(
                'Failed to generate staff security passes', 'An internal error occurred.'
            );
        }
    }

    /**
     * POST /api/staff/id-card/upload-photo
     * Upload the portrait used on a staff security pass.
     */
    public function postIdCardUploadPhoto($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;

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
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->serverError('An internal error occurred.'); }
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
                'staff_directory_view' => $this->access->allows('staff.directory.view', self::STAFF_DIRECTORY_VIEW_ROLES),
                'staff_directory_manage' => $this->access->allows('staff.directory.manage', ['system administrator','school administrator']),
                'teachers_view' => $this->access->allows('staff.teachers.view', ['system administrator','school administrator','director','headteacher','deputy head - academic']),
                'non_teaching_view' => $this->access->allows('staff.non_teaching.view', ['system administrator','school administrator','director','headteacher']),
                'staff_lifecycle_view' => $this->access->allows('staff.lifecycle.view', self::STAFF_LIFECYCLE_VIEW_ROLES),
                'staff_appointments_view' => $this->access->allows('staff.appointments.view', self::STAFF_APPOINTMENTS_VIEW_ROLES),
                'staff_appointments_approve' => $this->access->allows('staff.appointments.approve', ['director','school administrator']),
                'staff_appointments_onboard' => $this->access->allows('staff.appointments.onboard', ['system administrator','school administrator','headteacher']),
                'staff_onboarding_view' => $this->access->allows('staff.onboarding.view', self::STAFF_ONBOARDING_VIEW_ROLES),
                'staff_onboarding_manage' => $this->access->allows('staff.onboarding.manage', self::STAFF_ONBOARDING_MANAGE_ROLES),
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
                'staff_performance_view' => $this->access->allows('staff.performance.view', self::STAFF_PERFORMANCE_VIEW_ROLES),
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

    /**
     * GET /api/staff/key-contacts
     * Curated leadership/admin contacts for the student/parent staff viewer.
     * Self-service scoping: staff-view or student/parent-view accounts.
     */
    public function getKeyContacts($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(
            ['staff.directory.view', 'staff.view.directory', 'staff.view.contacts', 'staff.view.own',
             'staff_view_directory', 'staff_view_contacts', 'staff_view_own', 'staff_view',
             'students_view_own', 'students_view'],
            [],
            self::STAFF_DIRECTORY_VIEW_ROLES
        )) {
            return $this->forbidden('Access to staff key contacts is not available for this account');
        }
        try {
            return $this->handleResponse($this->api->keyContacts());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('Failed to load key contacts', 'An internal error occurred.');
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
        try {
            $staffId = (int)($data['staff_id'] ?? 0);
            $roleId = (int)($data['role_id'] ?? 0);
            $result = $this->recordsService->assignRole($staffId, $roleId);
            $this->access->audit('assign_role', 'staff', $staffId, null, ['role_id' => $roleId]);
            return $this->success($result, 'Role assigned');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /** GET /api/staff/id-cards — legacy route name, security-pass registry. */
    public function getIdCards($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.view', ['system administrator','school administrator','director','headteacher'])) return $denied;
        return $this->success($this->recordsService->idCards($_GET ?? []));
    }

    /** POST /api/staff/id-cards/generate — generate and register one pass. */
    public function postIdCardsGenerate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;

        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId <= 0) {
            return $this->badRequest('staff_id is required');
        }

        $side = $data['side'] ?? 'both';
        $printMode = $data['print_mode'] ?? 'direct_card';

        try {
            $result = $this->idCardGenerator->generateIDCard(
                $staffId,
                'pdf',
                $side,
                $printMode
            );

            if (($result['status'] ?? 'error') !== 'success') {
                return $this->handleResponse($result);
            }

            $passNumber = $this->recordsService
                ->securityPassNumberForStaff($staffId);

            $this->recordsService->persistGeneratedIdCard(
                $staffId,
                $passNumber,
                null,
                $this->access->userId()
            );

            $this->access->audit(
                'generate_staff_security_pass',
                'staff',
                $staffId,
                null,
                [
                    'pass_number' => $passNumber,
                    // Legacy field retained for audit-query compatibility.
                    'card_number' => $passNumber,
                    'print_mode' => $printMode,
                    'side' => $side,
                ]
            );

            return $this->success(
                [
                    'pass_number' => $passNumber,
                    // Legacy response field retained for existing consumers.
                    'card_number' => $passNumber,
                    'document' => $result['data'] ?? null,
                ],
                'Staff security pass generated successfully'
            );
        } catch (\Throwable $e) {
            return $this->serverError(
                'Failed to generate staff security pass', 'An internal error occurred.'
            );
        }
    }

    /** POST /api/staff/id-cards/issue — mark a registered pass as issued. */
    public function postIdCardsIssue($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.id_cards.manage', ['system administrator','school administrator'])) return $denied;

        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId <= 0) {
            return $this->badRequest('staff_id is required');
        }

        try {
            $this->recordsService->issueIdCard(
                $staffId,
                $this->access->userId()
            );

            $this->access->audit(
                'issue_staff_security_pass',
                'staff',
                $staffId,
                null,
                ['status' => 'issued']
            );

            return $this->success(
                null,
                'Staff security pass issued successfully'
            );
        } catch (RuntimeException $exception) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
return $this->badRequest('An internal error occurred.');
        }
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

    /** GET /api/staff/leave-balance — authenticated staff member's own balance. */
    public function getLeaveBalance($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) {
            return $this->unauthorized('Authentication required');
        }
        $staffId = (int) ($this->access->staffId() ?? 0);
        if ($staffId <= 0) {
            return $this->forbidden('No staff profile is linked to this account');
        }
        return $this->handleResponse($this->leaveManager->getLeaveBalance($staffId));
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
        if ($denied = $this->guardStaffDomain('staff.performance.view', self::STAFF_PERFORMANCE_VIEW_ROLES)) return $denied;
        try {
            $rows = $this->recordsService->performanceReviews($_GET ?? [], $id ? (int)$id : null);
            if($id) return $rows ? $this->success($rows[0]) : $this->notFound('Performance review not found');
            return $this->success($rows);
        } catch(\Throwable $e){return $this->serverError('Failed to load performance reviews', 'An internal error occurred.');}
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }


    /** GET /api/staff/internal-opportunities */
    public function getInternalOpportunities($id = null, $data = [], $segments = [])
    {
        try {
            $this->access->require('staff.opportunities.self');
            $staffId = (int) ($this->access->staffId() ?? 0);
            if ($staffId <= 0) {
                throw new RuntimeException('No staff profile is linked to this account', 403);
            }
            return $this->success(
                $this->api->listInternalOpportunities($staffId),
                'Internal opportunities retrieved'
            );
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/staff/internal-opportunities/apply */
    public function postInternalOpportunitiesApply($id = null, $data = [], $segments = [])
    {
        try {
            $this->access->require('staff.opportunities.self');
            $staffId = (int) ($this->access->staffId() ?? 0);
            if ($staffId <= 0) {
                throw new RuntimeException('No staff profile is linked to this account', 403);
            }
            $result = $this->api->applyForInternalOpportunity(
                $staffId,
                $this->access->userId(),
                $data
            );
            $this->access->audit(
                'apply_internal_opportunity',
                'job_application',
                (int) $result['id'],
                null,
                $result
            );
            return $this->created($result, 'Internal application submitted');
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** GET /api/staff/incidents */
    public function getIncidents($id = null, $data = [], $segments = [])
    {
        try {
            $this->access->require('staff.incidents.self');
            $staffId = (int) ($this->access->staffId() ?? 0);
            if ($staffId <= 0) {
                throw new RuntimeException('No staff profile is linked to this account', 403);
            }
            return $this->success(
                $this->api->listIncidentReports($staffId),
                'Incident reports retrieved'
            );
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/staff/incidents */
    public function postIncidents($id = null, $data = [], $segments = [])
    {
        try {
            $this->access->require('staff.incidents.self');
            $staffId = (int) ($this->access->staffId() ?? 0);
            if ($staffId <= 0) {
                throw new RuntimeException('No staff profile is linked to this account', 403);
            }
            $result = $this->api->createIncidentReport(
                $staffId,
                $this->access->userId(),
                $data
            );
            $this->access->audit(
                'create_incident_report',
                'staff_incident_report',
                (int) $result['id'],
                null,
                $result
            );
            return $this->created($result, 'Incident report submitted');
        } catch (RuntimeException $error) {
            return $this->selfServiceError($error);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    private function resolveSelfOrManagedStaffId(
        $id,
        array $data,
        string $permission,
        array $fallbackRoles = []
    ): int {
        $requestedStaffId = (int) (
            $id
            ?? $_GET['staff_id']
            ?? $data['staff_id']
            ?? $this->access->staffId()
            ?? 0
        );

        if ($requestedStaffId <= 0) {
            throw new RuntimeException('No staff profile is linked to this account', 403);
        }

        return $this->access->requireSelfOr(
            $permission,
            $requestedStaffId,
            $fallbackRoles
        );
    }

    private function selfServiceError(RuntimeException $error)
    {
        $code = (int) $error->getCode();
        if ($code === 401) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
            return $this->unauthorized('An internal error occurred.');
        }
        if ($code === 403) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
            return $this->forbidden('An internal error occurred.');
        }
        if ($code === 409) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
            return $this->conflict('An internal error occurred.');
        }
        if ($code === 422) {
            \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
            return $this->unprocessable('An internal error occurred.');
        }
        \App\API\Services\Logger::legacyError('[StaffController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
        return $this->serverError('An internal error occurred.');
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
            \App\API\Services\Logger::legacyError('[StaffController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        }
    }

    /** GET /api/staff/statutory-remittances */
    public function getStatutoryRemittances($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        try {
            $year = (int)($_GET['year'] ?? date('Y'));
            $agency = $_GET['agency'] ?? null;
            $status = $_GET['status'] ?? null;
            $sql = "SELECT * FROM statutory_remittances WHERE period_year = ?";
            $params = [$year];
            if ($agency) { $sql .= " AND agency = ?"; $params[] = $agency; }
            if ($status) { $sql .= " AND status = ?"; $params[] = $status; }
            $sql .= " ORDER BY period_year DESC, period_month DESC, agency";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $remittances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $ruleStmt = $this->db->getConnection()->prepare("SELECT deadline_day,deadline_basis FROM statutory_rule_versions
                WHERE agency=? AND active=1 AND effective_from <= STR_TO_DATE(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'), '%Y-%m-%d')
                AND (effective_to IS NULL OR effective_to >= STR_TO_DATE(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'), '%Y-%m-%d'))
                ORDER BY effective_from DESC, id DESC LIMIT 1");
            foreach ($remittances as &$remittance) {
                if (!empty($remittance['due_date'])) continue;
                $ruleStmt->execute([$remittance['agency'], $remittance['period_year'], $remittance['period_month'], $remittance['period_year'], $remittance['period_month']]);
                $rule = $ruleStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                $day = (int)($rule['deadline_day'] ?? 0);
                if ($day < 1 || $day > 31) continue;
                $due = new \DateTime(sprintf('%04d-%02d-01', (int)$remittance['period_year'], (int)$remittance['period_month']));
                $due->modify('+1 month')->setDate((int)$due->format('Y'), (int)$due->format('m'), $day);
                if (($rule['deadline_basis'] ?? '') === 'working_day_of_following_month') {
                    while (in_array((int)$due->format('N'), [6, 7], true)) $due->modify('+1 day');
                }
                $remittance['due_date'] = $due->format('Y-m-d');
            }
            unset($remittance);
            $monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $agencies = ['KRA','SHIF','NSSF','Housing Levy'];
            $breakdown = [];
            foreach (range(1, 12) as $m) {
                $row = ['period_month' => $m, 'kra' => 0, 'shif' => 0, 'nssf' => 0, 'housing_levy' => 0];
                foreach ($agencies as $a) {
                    $key = strtolower(str_replace(' ', '_', str_replace('/', '_', $a)));
                    foreach ($remittances as $r) {
                        if ((int)$r['period_month'] === $m && $r['agency'] === $a) {
                            $row[$key === 'kra_(paye)' ? 'kra' : $key] = (float)$r['total_deducted'];
                        }
                    }
                }
                $breakdown[] = $row;
            }
            $totalDeducted = array_sum(array_column($remittances, 'total_deducted'));
            $totalRemitted = array_sum(array_column($remittances, 'amount_remitted'));
            $summary = [
                'total_deducted' => $totalDeducted,
                'total_remitted' => $totalRemitted,
                'outstanding' => $totalDeducted - $totalRemitted,
                'overdue_count' => count(array_filter($remittances, fn($r) => $r['status'] === 'overdue')),
            ];
            return $this->success(['remittances' => $remittances, 'breakdown' => $breakdown, 'summary' => $summary]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] getStatutoryRemittances: ' . $e->getMessage());
            return $this->badRequest('Failed to load remittances.');
        }
    }

    /** POST /api/staff/statutory-remittances */
    public function postStatutoryRemittances($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        try {
            $agency = $data['agency'] ?? null;
            $month = (int)($data['period_month'] ?? 0);
            $year = (int)($data['period_year'] ?? 0);
            if (!$agency || !$month || !$year) return $this->badRequest('agency, period_month, and period_year are required');
            $stmt = $this->db->getConnection()->prepare("INSERT INTO statutory_remittances (agency, period_month, period_year, total_deducted, amount_remitted, status, due_date, remittance_date, filing_reference, notes, filed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $agency, $month, $year,
                $data['total_deducted'] ?? 0, $data['amount_remitted'] ?? 0,
                $data['status'] ?? 'pending',
                $data['due_date'] ?? null, $data['remittance_date'] ?? null,
                $data['filing_reference'] ?? null, $data['notes'] ?? null,
                $this->access->staffId()
            ]);
            return $this->success(['id' => $this->db->getConnection()->lastInsertId()], 'Remittance saved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] createStatutoryRemittance: ' . $e->getMessage());
            return $this->badRequest('Failed to save remittance.');
        }
    }

    /** PUT /api/staff/statutory-remittances/{id} */
    public function putStatutoryRemittances($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $remId = (int)($id ?? $data['id'] ?? 0);
        if (!$remId) return $this->badRequest('Remittance ID required');
        try {
            $stmt = $this->db->getConnection()->prepare("UPDATE statutory_remittances SET amount_remitted = ?, status = ?, remittance_date = ?, filing_reference = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $data['amount_remitted'] ?? 0, $data['status'] ?? 'pending',
                $data['remittance_date'] ?? null, $data['filing_reference'] ?? null,
                $data['notes'] ?? null, $remId
            ]);
            return $this->success(null, 'Remittance updated');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] updateStatutoryRemittance: ' . $e->getMessage());
            return $this->badRequest('Failed to update remittance.');
        }
    }

    /** POST /api/staff/statutory-remittances/{id}/initiate-payment */
    public function postStatutoryRemittancePayment($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $remittanceId = (int) ($id ?? $data['id'] ?? 0);
        if (!$remittanceId || empty($data['agency_account_id'])) return $this->badRequest('Remittance ID and agency_account_id are required');
        try {
            $result = (new StatutoryRemittanceService($this->db))->initiate($remittanceId, (int) $this->access->staffId(), $data);
            return $this->success($result, 'Statutory payment submitted for confirmation');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] initiate statutory payment: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** GET /api/staff/statutory-agency-accounts?agency=KRA */
    public function getStatutoryAgencyAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $agency = $_GET['agency'] ?? $data['agency'] ?? null;
        if (!$agency) return $this->badRequest('Agency is required');
        try {
            $stmt = $this->db->getConnection()->prepare("SELECT id, agency, account_name, account_number, bank_name, bank_code, payment_reference_rule FROM statutory_agency_accounts WHERE agency = ? AND active = 1 ORDER BY account_name, id");
            $stmt->execute([$agency]);
            return $this->success(['accounts' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] statutory agency accounts: ' . $e->getMessage());
            return $this->badRequest('Failed to load agency accounts.');
        }
    }

    /** GET /api/staff/statutory-remittances/calc?agency=X&month=X&year=X */
    public function getStatutoryRemittancesCalc($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        try {
            $agency = $_GET['agency'] ?? null;
            $month = (int)($_GET['month'] ?? 0);
            $year = (int)($_GET['year'] ?? 0);
            if (!$agency || !$month || !$year) return $this->badRequest('agency, month, year required');
            $amountExpressions = [
                'KRA' => 'p.paye_tax',
                'SHIF' => 'p.shif_contribution',
                'NSSF' => '(p.nssf_contribution + p.employer_nssf_contribution)',
                'Housing Levy' => '(p.housing_levy + p.employer_housing_levy)',
            ];
            $amountExpression = $amountExpressions[$agency] ?? null;
            if (!$amountExpression) return $this->badRequest('Unknown agency');
            $sql = "SELECT p.staff_id, s.staff_no, CONCAT(ps.first_name, ' ', ps.last_name) AS staff_name,
                {$amountExpression} AS amount
                FROM payslips p JOIN staff s ON s.id = p.staff_id JOIN persons ps ON ps.id = s.person_id
                WHERE p.payroll_month = ? AND p.payroll_year = ?
                AND p.payslip_status IN ('approved','paid') AND {$amountExpression} > 0
                ORDER BY ps.last_name, ps.first_name";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$month, $year]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $total = array_sum(array_column($rows, 'amount'));
            return $this->success(['total' => $total, 'staff' => $rows]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] calcStatutoryDeduction: ' . $e->getMessage());
            return $this->badRequest('Failed to calculate deductions.');
        }
    }

    /** GET /api/staff/statutory-compliance */
    public function getStatutoryCompliance($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        try {
            $pdo = $this->db->getConnection();
            $year = (int)($_GET['year'] ?? $data['year'] ?? date('Y'));
            $registers = $pdo->prepare('SELECT * FROM statutory_payroll_registers WHERE period_year = ? ORDER BY period_year DESC, period_month DESC');
            $registers->execute([$year]);
            $certificates = $pdo->query("SELECT c.*, s.staff_no, CONCAT(p.first_name, ' ', p.last_name) staff_name
                FROM staff_certificates_of_service c
                JOIN staff s ON s.id=c.staff_id
                JOIN persons p ON p.id=s.person_id
                ORDER BY c.issued_date DESC, c.id DESC")->fetchAll(\PDO::FETCH_ASSOC);
            $rules = $pdo->query('SELECT id,agency,rule_code,version,effective_from,effective_to,calculation_method,
                employee_rate,employer_rate,lower_earnings_limit,upper_earnings_limit,cap_amount,personal_relief,
                deadline_day,deadline_basis,source_name,source_url,active
                FROM statutory_rule_versions WHERE active=1 ORDER BY agency,effective_from DESC')->fetchAll(\PDO::FETCH_ASSOC);
            $bands = $pdo->query('SELECT rule_version_id,band_order,lower_bound,upper_bound,tax_rate FROM statutory_tax_bands ORDER BY rule_version_id,band_order')->fetchAll(\PDO::FETCH_ASSOC);
            $bandsByRule = [];
            foreach ($bands as $band) {
                $bandsByRule[(int)$band['rule_version_id']][] = [
                    'up_to' => $band['upper_bound'] === null ? null : (float)$band['upper_bound'],
                    'rate' => (float)$band['tax_rate'],
                ];
            }
            foreach ($rules as &$rule) {
                $rule['rules'] = [
                    'calculation' => $rule['calculation_method'],
                    'employee_rate' => $rule['employee_rate'] === null ? null : (float)$rule['employee_rate'],
                    'employer_rate' => $rule['employer_rate'] === null ? null : (float)$rule['employer_rate'],
                    'lower_earnings_limit' => $rule['lower_earnings_limit'] === null ? null : (float)$rule['lower_earnings_limit'],
                    'upper_earnings_limit' => $rule['upper_earnings_limit'] === null ? null : (float)$rule['upper_earnings_limit'],
                    'cap_amount' => $rule['cap_amount'] === null ? null : (float)$rule['cap_amount'],
                    'personal_relief' => $rule['personal_relief'] === null ? null : (float)$rule['personal_relief'],
                    'deadline_day' => $rule['deadline_day'],
                    'deadline_basis' => $rule['deadline_basis'],
                    'bands' => $bandsByRule[(int)$rule['id']] ?? [],
                ];
            }
            unset($rule);
            $retention = $pdo->query("SELECT COUNT(*) FROM statutory_record_retention WHERE status='active' AND retain_until <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();
            return $this->success([
                'registers' => $registers->fetchAll(\PDO::FETCH_ASSOC),
                'certificates' => $certificates,
                'rules' => $rules,
                'retention_due_90_days' => (int)$retention,
            ]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] statutory compliance: ' . $e->getMessage());
            return $this->badRequest('Failed to load statutory compliance records.');
        }
    }

    /** POST /api/staff/statutory-rules
     * Append an effective-dated rule version; existing payroll snapshots are
     * never rewritten.
     */
    public function postStatutoryRules($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','director'])) return $denied;
        $agency = trim((string)($data['agency'] ?? ''));
        $ruleCode = trim((string)($data['rule_code'] ?? ''));
        $version = trim((string)($data['version'] ?? ''));
        $effectiveFrom = trim((string)($data['effective_from'] ?? ''));
        $rules = $data['rules'] ?? null;
        if ($agency === '' || $ruleCode === '' || $version === '' || $effectiveFrom === '' || !is_array($rules)) {
            return $this->badRequest('Agency, rule code, version, effective date and rule values are required.');
        }
        try {
            $pdo = $this->db->getConnection();
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO statutory_rule_versions
                (agency,rule_code,version,effective_from,effective_to,calculation_method,employee_rate,employer_rate,
                 lower_earnings_limit,upper_earnings_limit,cap_amount,personal_relief,deadline_day,deadline_basis,
                 source_name,source_url,active,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)");
            $stmt->execute([$agency,$ruleCode,$version,$effectiveFrom,$data['effective_to'] ?? null,
                $rules['calculation'] ?? 'percentage_of_gross',$rules['employee_rate'] ?? null,$rules['employer_rate'] ?? null,
                $rules['lower_earnings_limit'] ?? null,$rules['upper_earnings_limit'] ?? null,$rules['cap_amount'] ?? null,
                $rules['personal_relief'] ?? null,$rules['deadline_day'] ?? null,$rules['deadline_basis'] ?? null,
                $data['source_name'] ?? null,$data['source_url'] ?? null,$this->getUserId()]);
            $id = (int)$pdo->lastInsertId();
            if (!empty($rules['bands']) && is_array($rules['bands'])) {
                $band = $pdo->prepare('INSERT INTO statutory_tax_bands(rule_version_id,band_order,lower_bound,upper_bound,tax_rate) VALUES(?,?,?,?,?)');
                foreach (array_values($rules['bands']) as $index => $item) {
                    $upper = array_key_exists('up_to', $item) && $item['up_to'] !== null ? $item['up_to'] : null;
                    $lower = $index === 0 ? 0 : ($rules['bands'][$index - 1]['up_to'] ?? 0);
                    $band->execute([$id,$index + 1,$lower,$upper,$item['rate'] ?? 0]);
                }
            }
            $pdo->commit();
            return $this->success(['id'=>$id], 'Statutory rule version added.');
        } catch (\Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            \App\API\Services\Logger::legacyError('[StaffController] statutory rule: ' . $e->getMessage());
            return $this->badRequest('Failed to add statutory rule version.');
        }
    }

    /** POST /api/staff/statutory-compliance/register */
    public function postStatutoryComplianceRegister($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $month = (int)($data['month'] ?? 0);
        $year = (int)($data['year'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 2000) return $this->badRequest('A valid payroll month and year are required.');
        try {
            $pdo = $this->db->getConnection();
            $pdo->beginTransaction();
            $q = $pdo->prepare("SELECT p.*, pr.id payroll_run_id FROM payslips p
                LEFT JOIN payroll_runs pr ON pr.month=p.payroll_month AND pr.year=p.payroll_year
                WHERE p.payroll_month=? AND p.payroll_year=? AND p.payslip_status IN ('approved','paid')
                ORDER BY p.staff_id");
            $q->execute([$month, $year]);
            $payslips = $q->fetchAll(\PDO::FETCH_ASSOC);
            if (!$payslips) throw new RuntimeException('No approved or paid payslips exist for this period.');
            $gross = $employee = $employer = 0.0;
            foreach ($payslips as $p) {
                $employee += (float)$p['paye_tax'] + (float)$p['shif_contribution'] + (float)$p['nssf_contribution'] + (float)$p['housing_levy'];
                $employer += (float)$p['employer_nssf_contribution'] + (float)$p['employer_housing_levy'];
                $gross += (float)$p['gross_salary'];
            }
            $retentionUntil = sprintf('%04d-%02d-01', $year + 5, $month);
            $runId = $payslips[0]['payroll_run_id'] ?: null;
            $upsert = $pdo->prepare("INSERT INTO statutory_payroll_registers
                (payroll_run_id,period_month,period_year,employee_count,gross_total,employee_deductions_total,employer_contributions_total,status,retention_until,created_by)
                VALUES(?,?,?,?,?,?,?,'draft',?,?)
                ON DUPLICATE KEY UPDATE payroll_run_id=VALUES(payroll_run_id),employee_count=VALUES(employee_count),
                gross_total=VALUES(gross_total),employee_deductions_total=VALUES(employee_deductions_total),
                employer_contributions_total=VALUES(employer_contributions_total),retention_until=VALUES(retention_until),updated_at=NOW()");
            $upsert->execute([$runId,$month,$year,count($payslips),$gross,$employee,$employer,$retentionUntil,$this->getUserId()]);
            $registerId = (int)$pdo->lastInsertId();
            if (!$registerId) {
                $find = $pdo->prepare('SELECT id FROM statutory_payroll_registers WHERE period_month=? AND period_year=?');
                $find->execute([$month,$year]); $registerId = (int)$find->fetchColumn();
            }
            $item = $pdo->prepare("INSERT INTO statutory_payroll_register_items
                (register_id,payslip_id,staff_id,gross_amount,paye_amount,shif_employee_amount,nssf_employee_amount,
                 housing_employee_amount,nssf_employer_amount,housing_employer_amount,rule_snapshot)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE gross_amount=VALUES(gross_amount),paye_amount=VALUES(paye_amount),
                shif_employee_amount=VALUES(shif_employee_amount),nssf_employee_amount=VALUES(nssf_employee_amount),
                housing_employee_amount=VALUES(housing_employee_amount),nssf_employer_amount=VALUES(nssf_employer_amount),
                housing_employer_amount=VALUES(housing_employer_amount),rule_snapshot=VALUES(rule_snapshot)");
            foreach ($payslips as $p) {
                $item->execute([$registerId,$p['id'],$p['staff_id'],$p['gross_salary'],$p['paye_tax'],$p['shif_contribution'],
                    $p['nssf_contribution'],$p['housing_levy'],$p['employer_nssf_contribution'],$p['employer_housing_levy'],
                    json_encode(['source'=>'payslip','payroll_month'=>$month,'payroll_year'=>$year])]);
            }
            $ret = $pdo->prepare("INSERT INTO statutory_record_retention(record_type,record_id,period_start,period_end,retain_until)
                VALUES('payroll_register',?,?,LAST_DAY(?),?) ON DUPLICATE KEY UPDATE retain_until=VALUES(retain_until)");
            $period = sprintf('%04d-%02d-01', $year, $month);
            $ret->execute([$registerId,$period,$period,$retentionUntil]);
            $pdo->prepare("INSERT INTO statutory_audit_log(actor_user_id,action,entity_type,entity_id,after_json) VALUES(?,?,?,?,?)")
                ->execute([$this->getUserId(),'generated','statutory_payroll_register',$registerId,json_encode(['month'=>$month,'year'=>$year,'payslips'=>count($payslips)])]);
            $pdo->commit();
            return $this->success(['register_id'=>$registerId,'employee_count'=>count($payslips)], 'Statutory payroll register generated.');
        } catch (\Throwable $e) {
            if ($this->db->getConnection()->inTransaction()) $this->db->getConnection()->rollBack();
            \App\API\Services\Logger::legacyError('[StaffController] statutory register: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/staff/statutory-compliance/certificate */
    public function postStatutoryComplianceCertificate($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator','director'])) return $denied;
        $staffId = (int)($data['staff_id'] ?? 0);
        if (!$staffId || empty($data['employment_start_date']) || empty($data['employment_end_date'])) return $this->badRequest('Staff member and employment dates are required.');
        try {
            $pdo = $this->db->getConnection();
            $staff = $pdo->prepare('SELECT s.staff_no,s.position,s.employment_date FROM staff s WHERE s.id=?');
            $staff->execute([$staffId]); $row = $staff->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return $this->badRequest('Staff member not found.');
            $certificateNo = trim((string)($data['certificate_number'] ?? ('COS-' . date('YmdHis') . '-' . $row['staff_no'])));
            $issued = $data['issued_date'] ?? date('Y-m-d');
            $retention = date('Y-m-d', strtotime($issued . ' +5 years'));
            $stmt = $pdo->prepare("INSERT INTO staff_certificates_of_service
                (staff_id,certificate_number,employment_start_date,employment_end_date,designation,department,reason_for_leaving,issued_date,status,retention_until,issued_by)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([$staffId,$certificateNo,$data['employment_start_date'],$data['employment_end_date'],$data['designation'] ?? $row['position'],
                $data['department'] ?? null,$data['reason_for_leaving'] ?? null,$issued,$data['status'] ?? 'draft',$retention,$this->getUserId()]);
            $certificateId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO statutory_record_retention(record_type,record_id,period_start,period_end,retain_until) VALUES('certificate_of_service',?,?,?,?)")
                ->execute([$certificateId,$data['employment_start_date'],$issued,$retention]);
            return $this->success(['id'=>$certificateId,'certificate_number'=>$certificateNo], 'Certificate of service recorded.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] certificate of service: ' . $e->getMessage());
            return $this->badRequest('Failed to record certificate of service.');
        }
    }

}
