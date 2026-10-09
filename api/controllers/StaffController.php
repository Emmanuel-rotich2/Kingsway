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
use Throwable;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

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
    private $teacherSpecializations;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\staff\StaffAPI');
        $this->payroll = $this->contract('App\API\Modules\staff\StaffPayrollManager');
        $this->idCardGenerator = $this->contract('App\API\Modules\staff\StaffIDCardGenerator');
        $this->leaveManager = $this->contract('App\API\Modules\staff\StaffLeaveManager');
        $this->onboardingManager = $this->contract('App\API\Modules\staff\StaffOnboardingManager');
        $this->access = $this->contract('App\API\Services\StaffDomainAccessService', $this->user);
        $this->lifecycleService = $this->contract('App\API\Services\StaffLifecycleService');
        $this->recordsService = $this->contract('App\API\Services\StaffRecordsService', $this->db);
        $this->teacherSpecializations = $this->contract('App\API\Services\TeacherSpecializationService', $this->db->getConnection());
    }

    private function schoolAdminInvitations(): \App\API\Services\SchoolAdminInvitationService
    {
        return new \App\API\Services\SchoolAdminInvitationService($this->db->getConnection());
    }

    private function migrationService(): \App\API\Services\StaffMigrationService
    {
        return $this->contract('App\API\Services\StaffMigrationService', $this->db->getConnection());
    }

    public function index()
    {
        // For /staff/index, return list to match frontend expectations
        if (!$this->userHasPermission('staff_view') && !$this->userHasAnyRole(self::STAFF_DIRECTORY_VIEW_ROLES)) {
            return $this->forbidden('You do not have permission to view the staff directory');
        }
        $result = $this->api->list($_GET ?? []);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/staff/stats - Get staff statistics for dashboard
     * Returns: total staff count, present today, percentage
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasPermission('staff_view') && !$this->userHasAnyRole(self::STAFF_DIRECTORY_VIEW_ROLES)) {
            return $this->forbidden('You do not have permission to view staff statistics');
        }
        return $this->handleResponse($this->api->stats());
    }

    /** GET /api/staff/teacher-scope — effective blended academic scope for self */
    public function getTeacherScope($id = null, $data = [], $segments = [])
    {
        if (empty($this->user)) return $this->unauthorized('Authentication required');
        $yearId = !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null;
        $termId = !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null;
        return $this->success(($this->contract('App\API\Services\TeacherScopeService', $this->db->getConnection()))->forUser($this->user, $yearId, $termId));
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

    public function postAiHrReviewQueue($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.view', self::STAFF_DIRECTORY_VIEW_ROLES)) return $denied;
        $context = $this->aiHrContext();
        try { $this->contract(AiWorkflowService::class)->authorize('staff.hr_review', $context); } catch (DomainException $e) { return $this->forbidden('Staff HR AI assistance is not available for your account'); }
        try {
            $result = $this->api->list(['status' => 'active', 'limit' => 1000]);
            $rows = is_array($result['data']['staff'] ?? null) ? $result['data']['staff'] : (is_array($result['staff'] ?? null) ? $result['staff'] : (is_array($result['data'] ?? null) ? $result['data'] : []));
            $teaching = $leave = $pending = $onboarding = 0;
            foreach ($rows as $row) { if ((int)($row['staff_type_id'] ?? 0) === 1) $teaching++; if (str_contains(strtolower((string) ($row['status'] ?? '')), 'leave')) $leave++; if (str_contains(strtolower((string) ($row['onboarding_status'] ?? '')), 'pending')) $onboarding++; }
            $input = ['report_date' => date('Y-m-d'), 'staff_count' => (string) count($rows), 'teaching_staff_count' => (string) $teaching, 'non_teaching_staff_count' => (string) max(0, count($rows) - $teaching), 'on_leave_count' => (string) $leave, 'pending_leave_count' => (string) $pending, 'onboarding_count' => (string) $onboarding, 'workload_exception_count' => '0', 'follow_up_intent' => 'Prepare aggregate HR follow-up; do not identify staff or make employment decisions.'];
            return $this->accepted($this->contract(AiDraftService::class)->queue('staff.hr_review', $context, $input, ['subject_type' => 'staff_hr_review', 'scope' => 'authorized_staff_aggregates']), 'Staff HR review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); } catch (Throwable $e) { return $this->serverError('Staff HR assistance is temporarily unavailable'); }
    }

    public function getAiHrReviews($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->userHasAny(['staff_hr_review', 'staff_manage'], [3, 4, 5, 10], ['school administrator', 'headteacher', 'director', 'admin'])) return $this->forbidden('Staff HR review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'staff'), 'scope' => $review ? 'review' : 'own'], 'Staff HR AI reviews retrieved');
    }

    public function postAiHrReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['staff_hr_review', 'staff_manage'], [3, 4, 5, 10], ['school administrator', 'headteacher', 'director', 'admin'])) return $this->forbidden('Staff HR review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0); if ($draftId < 1) return $this->badRequest('draft_id is required');
        try { $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'staff.hr_review'); return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Staff HR review approved for guidance'); } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false); }
    }

    private function aiHrContext(): array { return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? '']; }

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

        try {
            if (trim((string)($data['position'] ?? '')) === '') {
                $default = \App\API\Services\StaffPositionCatalog::defaultForRole(
                    $this->db->getConnection(),
                    (int)($data['role_id'] ?? 0),
                    isset($data['staff_type_id']) ? (int)$data['staff_type_id'] : null,
                    isset($data['staff_category_id']) ? (int)$data['staff_category_id'] : null
                );
                if ($default) $data['position'] = $default['name'];
            }
            $data['position'] = \App\API\Services\StaffPositionCatalog::assertActive(
                $this->db->getConnection(),
                (string)($data['position'] ?? ''),
                isset($data['staff_type_id']) ? (int)$data['staff_type_id'] : null,
                isset($data['staff_category_id']) ? (int)$data['staff_category_id'] : null,
                isset($data['role_id']) ? (int)$data['role_id'] : null
            );
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
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

    /** GET /api/staff/school-administrator-bootstrap */
    public function getSchoolAdministratorBootstrap($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasRole('System Administrator')) {
            return $this->forbidden('Only a System Administrator may invite a School Administrator.');
        }
        return $this->success($this->schoolAdminInvitations()->bootstrapData());
    }

    /** POST /api/staff/school-administrator-bootstrap */
    public function postSchoolAdministratorBootstrap($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasRole('System Administrator')) {
            return $this->forbidden('Only a System Administrator may invite a School Administrator.');
        }
        $required = ['first_name', 'last_name', 'email', 'department_id', 'employment_date', 'contract_type', 'staff_type_id', 'staff_category_id'];
        $missing = array_values(array_filter($required, static fn($key) => !isset($data[$key]) || trim((string)$data[$key]) === ''));
        if ($missing) return $this->badRequest('Enter the administrator identity and school-owned employment assignment.', ['fields' => $missing]);
        if (!filter_var(trim((string)$data['email']), FILTER_VALIDATE_EMAIL)) return $this->badRequest('Enter a valid email address.');
        if (mb_strlen(trim((string)($data['position'] ?? ''))) > 100) return $this->badRequest('Position must be 100 characters or fewer.');
        $dt = trim((string)$data['employment_date']);
        if (!\DateTimeImmutable::createFromFormat('!Y-m-d', $dt) || $dt > date('Y-m-d')) return $this->badRequest('Enter a valid employment date that is not in the future.');
        if (!in_array(trim((string)$data['contract_type']), ['permanent', 'contract', 'temporary'], true)) return $this->badRequest('Choose a valid contract type.');

        try {
            $result = $this->schoolAdminInvitations()->invite(
                $data,
                (int) ($this->getUserId() ?? 0),
                $this->contract('App\\API\\Modules\\users\\UsersAPI'),
                $this->migrationService()
            );
            return $this->created($result, $result['email_sent'] ? 'School Administrator account created and invitation email sent.' : 'School Administrator account created. Invitation is queued for email delivery.');
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->respond(null, $e->getMessage(), $code >= 400 && $code < 600 ? $code : 500, false);
        }
    }

    /** POST /api/staff/school-administrator-invitation-action */
    public function postSchoolAdministratorInvitationAction($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasRole('System Administrator')) {
            return $this->forbidden('Only a System Administrator may manage School Administrator invitations.');
        }
        $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $action = strtolower(trim((string)($data['action'] ?? '')));
        if (!$userId || !in_array($action, ['resend', 'resend_otp', 'cancel'], true)) {
            return $this->badRequest('Choose a valid invitation action and account.');
        }

        $service = $this->schoolAdminInvitations();
        if (!$service->isSchoolAdmin((int) $userId)) return $this->notFound('School Administrator account not found.');
        $migration = $this->migrationService();

        if ($action === 'resend_otp') {
            try {
                $result = $migration->resendSetupOtp((int)$userId, (int)($this->getUserId() ?? 0));
                return $this->success($result, 'A new setup verification code was sent.');
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError('[SchoolAdministratorInvitation] OTP resend failed: ' . $e->getMessage());
                return $this->badRequest($e->getMessage());
            }
        }

        if ($action === 'resend') {
            try {
                $invitation = $migration->resendInvitation((int)$userId, (int)($this->getUserId() ?? 0));
                $delivery = $migration->processEmailQueue(1, (int)($invitation['message_id'] ?? 0));
                $sent = !empty($delivery['sent']);
                return $this->success([
                    'user_id' => (int)$userId,
                    'email_sent' => $sent,
                    'invitation_queued' => true,
                ], $sent ? 'Invitation email resent.' : 'Invitation queued for email delivery.');
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError('[SchoolAdministratorInvitation] Resend failed: ' . $e->getMessage());
                return $this->serverError('The invitation could not be resent.');
            }
        }

        try {
            $service->cancelInvitation((int) $userId, $this->access);
            return $this->success(['user_id' => (int)$userId, 'cancelled' => true], 'Invitation cancelled.');
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->respond(null, $e->getMessage(), $code >= 400 && $code < 600 ? $code : 500, false);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SchoolAdministratorInvitation] Cancellation failed: ' . $e->getMessage());
            return $this->serverError('The invitation could not be cancelled.');
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
        // Qualification provenance is server-owned; never trust an actor ID
        // supplied by the browser.
        $data['_admin_actor_user_id'] = (int) ($this->getUserId() ?? 0);
        if (array_key_exists('position', $data)) {
            $pdo = $this->db->getConnection();
            $current = $this->recordsService->positionSnapshot((int) $id);
            if (!$current) return $this->notFound('Staff member not found.');
            if (trim((string)$current['position']) !== trim((string)$data['position'])) {
                try {
                    $data['position'] = \App\API\Services\StaffPositionCatalog::assertActive(
                        $pdo,
                        (string)$data['position'],
                        isset($data['staff_type_id']) ? (int)$data['staff_type_id'] : (int)$current['staff_type_id'],
                        isset($data['staff_category_id']) ? (int)$data['staff_category_id'] : (int)$current['staff_category_id'],
                        (int) ($current['role_id'] ?? 0)
                    );
                } catch (\RuntimeException $e) {
                    return $this->badRequest($e->getMessage(), ['field' => 'position']);
                }
            }
        }
        return $this->handleResponse($this->api->update((int) $id, $data));
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
     * POST /api/staff/my-admission-application
     * Staff self-service admission for their own child. The staff person's
     * identity is resolved from the authenticated account; the browser can
     * provide only learner details and the relationship.
     */
    public function postMyAdmissionApplication($id = null, $data = [], $segments = [])
    {
        try {
            $staffId = $this->access->staffId();
            if (!$staffId) return $this->forbidden('No staff profile is linked to this account.');

            $pdo = $this->db->getConnection();
            // Identity + parent provisioning live in the services layer.
            $person = \App\API\Services\StaffRecordsService::personForStaffId($pdo, (int) $staffId);
            if (!$person) return $this->forbidden('Active staff profile not found.');
            $parentId = \App\API\Modules\parent\ParentPortalManager::ensureParentForPerson($pdo, (int) $person['person_id']);

            $files = [];
            foreach ([
                'birth_certificate' => 'doc_birth_certificate',
                'passport_photo' => 'doc_passport_photo',
                'previous_school_report' => 'doc_previous_school_report',
                'immunization_card' => 'doc_immunization_card',
                'progress_report' => 'doc_progress_report',
                'leaving_certificate' => 'doc_leaving_certificate',
                'transfer_letter' => 'doc_transfer_letter',
                'medical_records' => 'doc_medical_records',
                'other' => 'doc_other',
            ] as $type => $field) {
                if (isset($_FILES[$field])) $files[$type] = $_FILES[$field];
            }

            $payload = [
                'applicant_name' => trim((string) ($data['child_name'] ?? '')),
                'date_of_birth' => trim((string) ($data['child_dob'] ?? '')),
                'gender' => trim((string) ($data['child_gender'] ?? '')),
                'grade_applying_for' => trim((string) ($data['grade_applying'] ?? '')),
                'birth_certificate_no' => trim((string) ($data['birth_certificate_no'] ?? '')),
                'boarding_preference' => trim((string) ($data['boarding_preference'] ?? 'day')),
                'admission_window_id' => (int) ($data['admission_window_id'] ?? 0),
                'application_source' => 'staff_portal',
                'parent_id' => $parentId,
                'parent_relationship' => trim((string) ($data['parent_relationship'] ?? '')),
                'special_needs' => trim((string) ($data['special_needs'] ?? '')),
            ];

            $result = $this->contract('App\\API\\Modules\\admission\\StudentAdmissionWorkflow')
                ->submitApplication($payload, $files);
            if (($result['code'] ?? 0) < 400) {
                $application = $result['data'] ?? [];
                return $this->created([
                    'ref' => $application['ref'] ?? $application['application_no'] ?? '',
                    'application_no' => $application['application_no'] ?? '',
                ], $result['message'] ?? 'Application received.');
            }
            return $this->respond(null, $result['message'] ?? 'Submission failed.', (int) ($result['code'] ?? 422), false);
        } catch (\Throwable $error) {
            return $this->serverError('Unable to submit the staff admission application.');
        }
    }


    /** GET /api/staff/my-admission-options */
    public function getMyAdmissionOptions($id = null, $data = [], $segments = [])
    {
        if (!$this->access->staffId()) return $this->forbidden('No staff profile is linked to this account.');
        $pdo = $this->db->getConnection();
        return $this->success([
            'windows' => \App\API\Modules\admission\AdmissionAdminManager::openWindows($pdo),
            'grades' => $this->contract(\App\API\Services\AcademicContextService::class)->gradeNames(),
        ]);
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
        $profile = $this->contract(\App\API\Modules\users\UserPermissionManager::class, $this->db->getConnection())->profileForUser((int) $userId);

        return [
            '_domain'          => 'system',
            'first_name'       => $profile['first_name'] ?? $this->user['first_name'] ?? null,
            'middle_name'      => $profile['middle_name'] ?? null,
            'last_name'        => $profile['last_name'] ?? $this->user['last_name'] ?? null,
            'email'            => $profile['email'] ?? $email,
            'phone'            => $profile['phone'] ?? $this->user['phone'] ?? null,
            'gender'           => $profile['gender'] ?? null,
            'date_of_birth'    => $profile['date_of_birth'] ?? null,
            'username'         => $profile['username'] ?? $this->user['username'] ?? null,
            'last_login'       => $profile['last_login'] ?? null,
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
            // A person_exists result is a CONFIRMATION REQUEST, not a plain
            // error: the client must receive the message AND the matched
            // existing person (roles, match score, matched-on fields) so the
            // operator can decide whether to link onto the existing record.
            // The flag may sit on the envelope or inside the nested
            // formatResponse data.
            $nested = is_array($result['data'] ?? null) ? $result['data'] : [];
            $resultStatus = $result['status'] ?? null;
            if (($nested['status'] ?? null) === 'person_exists') {
                $resultStatus = 'person_exists';
                $result = $nested;
            }
            if ($resultStatus === 'person_exists') {
                return $this->respond($result['data'] ?? $result, (string)($result['message'] ?? 'This person already exists'), 409, false);
            }
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
                'teacher_specializations_view' => $this->access->allows('staff.teacher_specializations.view', ['system administrator','school administrator','headteacher','deputy head - academic']),
                'teacher_specializations_manage' => $this->access->allows('staff.teacher_specializations.manage', ['system administrator','school administrator','headteacher','deputy head - academic']),
                'teacher_level_authorizations_view' => $this->access->allows('staff.teacher_level_authorizations.view', ['system administrator','school administrator','headteacher','deputy head - academic']),
                'teacher_level_authorizations_manage' => $this->access->allows('staff.teacher_level_authorizations.manage', ['system administrator','school administrator','headteacher','deputy head - academic']),
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

    /** GET /api/staff/teacher-specializations */
    public function getTeacherSpecializations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_specializations.view', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        return $this->success($this->teacherSpecializations->list($_GET ?? []));
    }

    /** GET /api/staff/teacher-specialization-candidates */
    public function getTeacherSpecializationCandidates($id = null, $data = [], $segments = [])
    {
        $leadershipRoles = ['system administrator','school administrator','headteacher','deputy head - academic'];
        $isLeadership = $this->access->allows('staff.teacher_specializations.view', $leadershipRoles);
        if (!$isLeadership) {
            if (!$this->userHasAny(['academic_view', 'schedules_view'], [], ['teacher', 'class teacher', 'subject teacher'])) {
                return $this->forbidden('Academic timetable permission is required to view eligible teacher suggestions');
            }
            $streamId = (int)($_GET['class_stream_id'] ?? 0);
            if ($streamId < 1) return $this->badRequest('A class stream is required for teacher-scoped suggestions');
            $scope = $this->contract('App\\API\\Services\\TeacherScopeService', $this->db->getConnection())->forUser(
                $this->user ?: [],
                !empty($_GET['academic_year_id']) ? (int)$_GET['academic_year_id'] : null,
                !empty($_GET['academic_year_term_id']) ? (int)$_GET['academic_year_term_id'] : null
            );
            if (!in_array($streamId, array_map('intval', (array)($scope['visible_stream_ids'] ?? [])), true)) {
                return $this->forbidden('You may only request suggestions for an assigned class stream');
            }
        }
        try {
            $query = $_GET ?? [];
            return $this->success($this->teacherSpecializations->suggestCandidates(
                (int)($query['learning_area_id'] ?? 0),
                (string)($query['class_name'] ?? ''),
                $query['grade_level'] ?? null,
                isset($query['academic_year_id']) ? (int)$query['academic_year_id'] : null,
                isset($query['class_stream_id']) ? (int)$query['class_stream_id'] : null
            ));
        } catch (RuntimeException $e) { return $this->badRequest($e->getMessage()); }
    }

    /** GET /api/staff/teacher-level-authorizations */
    public function getTeacherLevelAuthorizations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_level_authorizations.view', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        return $this->success($this->teacherSpecializations->listLevelAuthorizations($_GET ?? []));
    }

    /** POST /api/staff/teacher-level-authorizations */
    public function postTeacherLevelAuthorizations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_level_authorizations.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        try { return $this->created(['id' => $this->teacherSpecializations->saveLevelAuthorization($data, (int)$this->getUserId()), 'status' => 'pending'], 'Teaching level authorization saved for approval.'); }
        catch (RuntimeException $e) { return $e->getCode() === 403 ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }
    }

    /** POST /api/staff/teacher-level-authorizations/{id}/approve */
    public function postTeacherLevelAuthorizationsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_level_authorizations.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        try { $this->teacherSpecializations->approveLevelAuthorization((int)$id, (int)$this->getUserId()); return $this->success(['id' => (int)$id, 'status' => 'approved'], 'Teaching level authorization approved.'); }
        catch (RuntimeException $e) { return $e->getCode() === 403 ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }
    }

    /** POST /api/staff/teacher-specializations — records always start pending. */
    public function postTeacherSpecializations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_specializations.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        try {
            $newId = $this->teacherSpecializations->save($data, (int)$this->getUserId());
            return $this->created(['id' => $newId, 'status' => 'pending'], 'Teacher specialization saved for approval.');
        } catch (RuntimeException $e) {
            return $e->getCode() === 403 ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/staff/teacher-specializations/{id}/approve */
    public function postTeacherSpecializationsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_specializations.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        try {
            $this->teacherSpecializations->approve((int)$id, (int)$this->getUserId());
            return $this->success(['id' => (int)$id, 'status' => 'approved'], 'Teacher specialization approved.');
        } catch (RuntimeException $e) {
            return $e->getCode() === 403 ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/staff/teacher-qualifications/{id}/verify */
    public function postTeacherQualificationsVerify($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.teacher_specializations.manage', ['system administrator','school administrator','headteacher','deputy head - academic'])) return $denied;
        try {
            $status = (string)($data['status'] ?? 'verified');
            $this->teacherSpecializations->verifyQualification((int)$id, (int)$this->getUserId(), $status);
            return $this->success(['id' => (int)$id, 'verification_status' => $status], 'Qualification verification updated.');
        } catch (RuntimeException $e) {
            return $e->getCode() === 403 ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage());
        }
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

    /** GET /api/staff/classification-options */
    public function getClassificationOptions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        $pdo = $this->db->getConnection();
        return $this->success([
            'staff_types' => $this->recordsService->staffTypes(),
            'staff_categories' => $this->recordsService->staffCategories(),
            'positions' => \App\API\Services\StaffPositionCatalog::list($pdo),
        ]);
    }

    /** GET /api/staff/positions — controlled position catalogue. */
    public function getPositions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        return $this->success(['positions' => \App\API\Services\StaffPositionCatalog::list($this->db->getConnection(), false)]);
    }

    /** GET /api/staff/department-catalog — all department rows for administrators. */
    public function getDepartmentCatalog($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        return $this->success(['departments' => $this->recordsService->departmentCatalog()]);
    }


    /** POST /api/staff/departments — create a school department. */
    public function postDepartments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        $name = trim((string)($data['name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        if ($name === '' || mb_strlen($name) > 100 || !preg_match('/^[A-Z0-9_&-]{2,20}$/', $code)) return $this->badRequest('Enter a department name and a 2–20 character code using letters, numbers, ampersands, hyphens or underscores.');
        try {
            $id = $this->recordsService->createDepartment($name, $code);
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') return $this->conflict('The department name or code is already in use.');
            throw $e;
        }
        return $this->success(['id' => $id], 'Department added.');
    }


    /** PUT /api/staff/departments/{id} — edit or deactivate without removing history. */
    public function putDepartments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        $departmentId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $name = trim((string)($data['name'] ?? ''));
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        if (!$departmentId || $name === '' || mb_strlen($name) > 100 || !preg_match('/^[A-Z0-9_&-]{2,20}$/', $code)) return $this->badRequest('Enter a valid department, name and code.');
        try {
            $updated = $this->recordsService->updateDepartment($departmentId, $name, $code, !empty($data['is_active']) ? 'active' : 'inactive');
        } catch (\PDOException $e) {
            if ((string)$e->getCode() === '23000') return $this->conflict('The department code is already in use.');
            throw $e;
        }
        return $this->success(['updated' => $updated], 'Department saved.');
    }


    /** POST /api/staff/positions */
    public function postPositions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) return $this->badRequest('Enter a position name up to 120 characters.');
        foreach (['staff_type_id','staff_category_id'] as $key) if (!$this->validOptionalCatalogId($data[$key] ?? null)) return $this->badRequest('Choose valid staff classifications and role values.');
        if (!$this->recordsService->staffCategoryInType((int)($data['staff_type_id'] ?? 0), (int)($data['staff_category_id'] ?? 0))) return $this->badRequest('The selected staff category does not belong to the selected staff type.');
        $roleIds = $this->positionRoleIds($data['role_ids'] ?? []);
        if ($roleIds === null) return $this->badRequest('Choose active system roles.');
        $defaultRoleIds = $this->defaultPositionRoleIds($data['default_role_ids'] ?? [], $roleIds);
        if ($defaultRoleIds === null) return $this->badRequest('Default roles must be selected from the position’s related system roles.');
        $pdo = $this->db->getConnection();
        try {
            $pdo->beginTransaction();
            $positionId = $this->recordsService->insertPosition($name, (int)($data['staff_type_id'] ?? 0) ?: null, (int)($data['staff_category_id'] ?? 0) ?: null, $this->access->userId());
            $this->recordsService->syncPositionRoles($pdo, $positionId, $roleIds);
            $this->recordsService->syncDefaultPositionRoles($pdo, $positionId, $defaultRoleIds, (int)$this->access->userId());
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$e->getCode() === '23000') return $this->conflict('That position already exists or its selected classification is invalid.');
            throw $e;
        }
        return $this->success(['id' => $positionId], 'Position added.');
    }


    /** PUT /api/staff/positions/{id}; deactivation preserves staff history. */
    public function putPositions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator'])) return $denied;
        $positionId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$positionId) return $this->badRequest('A valid position id is required.');
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) return $this->badRequest('Enter a position name up to 120 characters.');
        foreach (['staff_type_id','staff_category_id'] as $key) if (!$this->validOptionalCatalogId($data[$key] ?? null)) return $this->badRequest('Choose valid staff classifications and role values.');
        if (!$this->recordsService->staffCategoryInType((int)($data['staff_type_id'] ?? 0), (int)($data['staff_category_id'] ?? 0))) return $this->badRequest('The selected staff category does not belong to the selected staff type.');
        $roleIds = $this->positionRoleIds($data['role_ids'] ?? []);
        if ($roleIds === null) return $this->badRequest('Choose active system roles.');
        $defaultRoleIds = $this->defaultPositionRoleIds($data['default_role_ids'] ?? [], $roleIds);
        if ($defaultRoleIds === null) return $this->badRequest('Default roles must be selected from the position’s related system roles.');
        $pdo = $this->db->getConnection();
        try {
            $pdo->beginTransaction();
            $updated = $this->recordsService->updatePosition($positionId, $name, (int)($data['staff_type_id'] ?? 0) ?: null, (int)($data['staff_category_id'] ?? 0) ?: null, !empty($data['is_active']) ? 1 : 0, $this->access->userId());
            $this->recordsService->syncPositionRoles($pdo, $positionId, $roleIds);
            $this->recordsService->syncDefaultPositionRoles($pdo, $positionId, $defaultRoleIds, (int)$this->access->userId());
            $pdo->commit();
        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$e->getCode() === '23000') return $this->conflict('That position already exists or its selected classification is invalid.');
            throw $e;
        }
        return $this->success(['updated' => $updated], 'Position saved.');
    }


    private function nullablePositiveInt($value): ?int
    {
        if ($value === null || $value === '') return null;
        return (int)$value;
    }

    private function positionRoleIds($value): ?array
    {
        if ($value === null || $value === '') return [];
        if (!is_array($value)) return null;
        $ids = array_values(array_unique(array_filter(array_map('intval', $value), static fn(int $id): bool => $id > 0)));
        if (!$ids) return $value === [] ? [] : null;
        return $this->recordsService->countActiveRoles($ids) === count($ids) ? $ids : null;
    }


    private function defaultPositionRoleIds($value, array $linkedRoleIds): ?array
    {
        if (!is_array($value)) return null;
        $ids = array_values(array_unique(array_filter(array_map('intval', $value), static fn(int $id): bool => $id > 0)));
        return array_diff($ids, $linkedRoleIds) ? null : $ids;
    }


    private function validOptionalCatalogId($value): bool
    {
        return $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
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

    /**
     * POST /api/staff/bulk-management
     * School administrators may update account state or replace school roles
     * for selected staff in one authorized transaction.
     */
    public function postBulkManagement($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;

        $staffIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['staff_ids'] ?? [])), static fn($value) => $value > 0)));
        $action = (string)($data['action'] ?? '');
        if (!$staffIds || count($staffIds) > 300) return $this->badRequest('Select between 1 and 300 staff accounts.');
        if (!in_array($action, ['set_status', 'set_roles'], true)) return $this->badRequest('Unsupported staff management action.');

        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['role_ids'] ?? [])), static fn($value) => $value > 0)));
        $primaryRoleId = (int)($data['primary_role_id'] ?? ($roleIds[0] ?? 0));
        $status = (string)($data['status'] ?? '');
        if ($action === 'set_status' && !in_array($status, ['active', 'inactive'], true)) return $this->badRequest('Status must be active or inactive.');
        if ($action === 'set_roles' && (!$roleIds || !in_array($primaryRoleId, $roleIds, true))) return $this->badRequest('Select a primary role from the selected school roles.');

        $pdo = $this->db->getConnection();
        try {
            $pdo->beginTransaction();
            $targets = $this->recordsService->lockedBulkTargets($pdo, $staffIds);
            if (count($targets) !== count($staffIds)) {
                $pdo->rollBack();
                return $this->forbidden('One or more selected staff records are unavailable in your data scope.');
            }

            if ($action === 'set_roles') {
                $roleManager = $this->contract(\App\API\Modules\users\UserRoleManager::class, $pdo);
                $found = $this->recordsService->activeSchoolRolesIn($pdo, $roleIds);
                if (count($found) !== count($roleIds)) {
                    $pdo->rollBack();
                    return $this->badRequest('Choose active school roles only.');
                }
                $actorId = (int)($this->getUserId() ?? 0);
                foreach ($targets as $target) {
                    if (empty($target['user_id'])) {
                        $pdo->rollBack();
                        return $this->badRequest('Every selected staff member must have a user account before roles can be updated.');
                    }
                    if ((int)($target['user_id'] ?? 0) === $actorId) {
                        $pdo->rollBack();
                        return $this->badRequest('You cannot change your own staff roles here.');
                    }
                    $updated = $roleManager->replaceRoles((int)$target['user_id'], $roleIds, $primaryRoleId, 'school');
                    if (empty($updated['success'])) {
                        $pdo->rollBack();
                        return $this->badRequest($updated['error'] ?? 'Could not update staff roles.');
                    }
                }
            } else {
                $actorId = (int)($this->getUserId() ?? 0);
                $userIds = [];
                foreach ($targets as $target) {
                    if (empty($target['user_id'])) {
                        $pdo->rollBack();
                        return $this->badRequest('Every selected staff member must have an account before account status can be changed.');
                    }
                    if ($status === 'inactive' && (int)($target['user_id'] ?? 0) === $actorId) {
                        $pdo->rollBack();
                        return $this->badRequest('You cannot deactivate your own account.');
                    }
                    if ($status === 'active' && ($target['user_status'] ?? null) === 'pending') {
                        $pdo->rollBack();
                        return $this->badRequest('Pending invitations must complete setup before their accounts can be activated.');
                    }
                    $userIds[] = (int)($target['user_id'] ?? 0);
                }
                $this->recordsService->bulkSetUserStatus($pdo, $userIds, $status);
            }
            $pdo->commit();
            $this->access->audit('bulk_' . $action, 'staff', null, null, ['staff_ids' => $staffIds, 'status' => $status ?: null, 'role_ids' => $roleIds, 'primary_role_id' => $action === 'set_roles' ? $primaryRoleId : null]);
            return $this->success(['updated' => count($targets), 'staff_ids' => $staffIds], 'Selected staff records updated.');
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            \App\API\Services\Logger::legacyError('[StaffController] bulk staff management failed: ' . $error->getMessage());
            return $this->serverError('Unable to update the selected staff records.');
        }
    }


    /** POST /api/staff/password-reset-link: issue reset mail for a managed staff account. */
    public function postPasswordResetLink($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffDomain('staff.roles.manage', ['system administrator','school administrator'])) return $denied;
        $staffId = (int)($data['staff_id'] ?? $id ?? 0);
        if ($staffId < 1) return $this->badRequest('staff_id is required.');
        $email = $this->recordsService->emailForStaff($staffId);
        if ($email === '') return $this->badRequest('This staff member has no user account email.');
        try {
            $result = $this->contract('App\\API\\Modules\\auth\\AuthAPI')->forgotPassword(['email' => $email]);
            if (($result['success'] ?? false) !== true) return $this->badRequest($result['message'] ?? 'Could not create a password reset request.');
            $this->access->audit('password_reset_link_sent', 'staff', $staffId, null, ['delivery_requested' => true]);
            return $this->success(['staff_id' => $staffId], 'Password reset instructions were sent to the staff account email.');
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[StaffController] staff password reset request failed: ' . $error->getMessage());
            return $this->serverError('Could not send password reset instructions.');
        }
    }


    /** GET /api/staff/statutory-remittances */
    public function getStatutoryRemittances($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        try {
            $year = (int) ($_GET['year'] ?? date('Y'));
            return $this->success((new \App\API\Services\StaffStatutoryService($this->db->getConnection()))
                ->remittances($year, $_GET['agency'] ?? null, $_GET['status'] ?? null));
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
            $month = (int) ($data['period_month'] ?? 0);
            $year = (int) ($data['period_year'] ?? 0);
            if (empty($data['agency']) || !$month || !$year) return $this->badRequest('agency, period_month, and period_year are required');
            $newId = (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))
                ->saveRemittance($data, (int) $this->access->staffId());
            return $this->success(['id' => $newId], 'Remittance saved');
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
        $remId = (int) ($id ?? $data['id'] ?? 0);
        if (!$remId) return $this->badRequest('Remittance ID required');
        try {
            (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))->updateRemittance($remId, $data);
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
            $result = ($this->contract('App\API\Services\payments\StatutoryRemittanceService', $this->db))->initiate($remittanceId, (int) $this->access->staffId(), $data);
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
            return $this->success(['accounts' => (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))->agencyAccounts((string) $agency)]);
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
            $agency = (string) ($_GET['agency'] ?? '');
            $month = (int) ($_GET['month'] ?? 0);
            $year = (int) ($_GET['year'] ?? 0);
            if ($agency === '' || !$month || !$year) return $this->badRequest('agency, month, year required');
            return $this->success((new \App\API\Services\StaffStatutoryService($this->db->getConnection()))->calcDeductions($agency, $month, $year));
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
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
            $year = (int)($_GET['year'] ?? $data['year'] ?? date('Y'));
            return $this->success((new \App\API\Services\StaffStatutoryService($this->db->getConnection()))->complianceBundle($year));
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
        if ($agency === '' || $ruleCode === '' || $version === '' || $effectiveFrom === '' || !is_array($data['rules'] ?? null)) {
            return $this->badRequest('Agency, rule code, version, effective date and rule values are required.');
        }
        try {
            $id = (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))
                ->addRuleVersion($data, (int) ($this->getUserId() ?? 0));
            return $this->success(['id' => $id], 'Statutory rule version added.');
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
        $month = (int) ($data['month'] ?? 0);
        $year = (int) ($data['year'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 2000) return $this->badRequest('A valid payroll month and year are required.');
        try {
            $result = (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))
                ->generatePayrollRegister($month, $year, (int) ($this->getUserId() ?? 0));
            return $this->success($result, 'Statutory payroll register generated.');
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] statutory register: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/staff/statutory-compliance/certificate */
    public function postStatutoryComplianceCertificate($id = null, $data = [], $segments = [])
    {
        if (!$this->access->authenticated()) return $this->unauthorized('Authentication required');
        if ($denied = $this->guardStaffDomain('staff.directory.manage', ['system administrator','school administrator','director'])) return $denied;
        $staffId = (int) ($data['staff_id'] ?? 0);
        if (!$staffId || empty($data['employment_start_date']) || empty($data['employment_end_date'])) return $this->badRequest('Staff member and employment dates are required.');
        try {
            $result = (new \App\API\Services\StaffStatutoryService($this->db->getConnection()))
                ->recordServiceCertificate($staffId, $data, (int) ($this->getUserId() ?? 0));
            return $this->success($result, 'Certificate of service recorded.');
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[StaffController] certificate of service: ' . $e->getMessage());
            return $this->badRequest('Failed to record certificate of service.');
        }
    }

}
