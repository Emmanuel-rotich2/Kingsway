<?php
namespace App\API\Modules\users;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Includes\AuditLogger;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\AuthSessionService;
use App\API\Services\TestAccountAccessService;
use App\API\Services\EnvironmentPhaseService;
use App\API\Services\UsernameService;
use Firebase\JWT\JWT;
use PDO;
use Exception;

class UsersAPI extends BaseAPI
{


    private $communicationsApi;
    private $roleManager;
    private $permissionManager;
    private $userRoleManager;
    private $userPermissionManager;
    private $auditLogger;
    private ?bool $hasFailedLoginDateColumn = null;

    public function __construct()
    {
        parent::__construct('users');
        $this->communicationsApi = $this->contract('App\API\Modules\communications\CommunicationsAPI');
        $this->roleManager = $this->contract('App\API\Modules\users\RoleManager', $this->db);
        $this->permissionManager = $this->contract('App\API\Modules\users\PermissionManager', $this->db);
        $this->userRoleManager = $this->contract('App\API\Modules\users\UserRoleManager', $this->db);
        $this->userPermissionManager = $this->contract('App\API\Modules\users\UserPermissionManager', $this->db);
        $this->auditLogger = new AuditLogger($this->db);
    }

    // --- Role CRUD and bulk ---
    public function createRole($data)
    {
        return $this->roleManager->createRole($data);
    }
    public function getRole($id)
    {
        return $this->roleManager->getRole($id);
    }
    public function getAllRoles()
    {
        return $this->roleManager->getAllRoles();
    }
    public function updateRole($id, $data)
    {
        return $this->roleManager->updateRole($id, $data);
    }
    public function deleteRole($id)
    {
        return $this->roleManager->deleteRole($id);
    }
    public function bulkCreateRoles($roles)
    {
        return $this->roleManager->bulkCreateRoles($roles);
    }
    public function bulkUpdateRoles($roles)
    {
        return $this->roleManager->bulkUpdateRoles($roles);
    }
    public function bulkDeleteRoles($roleIds)
    {
        return $this->roleManager->bulkDeleteRoles($roleIds);
    }

    // --- Permission CRUD and bulk ---
    public function getAllPermissions()
    {
        return $this->permissionManager->getAllPermissions();
    }
    public function getPermissionsByUser($userId)
    {
        return $this->permissionManager->getPermissionsByUser($userId);
    }
    public function getPermissionsByRole($roleId)
    {
        return $this->permissionManager->getPermissionsByRole($roleId);
    }
    public function assignPermissionToUser($userId, $permission)
    {
        return $this->permissionManager->assignPermissionToUser($userId, $permission);
    }
    public function revokePermissionFromUser($userId, $permission)
    {
        return $this->permissionManager->revokePermissionFromUser($userId, $permission);
    }
    public function bulkAssignPermissionsToUser($userId, $permissions)
    {
        return $this->permissionManager->bulkAssignPermissionsToUser($userId, $permissions);
    }
    public function bulkRevokePermissionsFromUser($userId, $permissions)
    {
        return $this->permissionManager->bulkRevokePermissionsFromUser($userId, $permissions);
    }
    public function assignPermissionToRole($roleId, $permission)
    {
        return $this->permissionManager->assignPermissionToRole($roleId, $permission);
    }
    public function revokePermissionFromRole($roleId, $permission)
    {
        return $this->permissionManager->revokePermissionFromRole($roleId, $permission);
    }
    public function bulkAssignPermissionsToRole($roleId, $permissions)
    {
        return $this->permissionManager->bulkAssignPermissionsToRole($roleId, $permissions);
    }
    public function bulkRevokePermissionsFromRole($roleId, $permissions)
    {
        return $this->permissionManager->bulkRevokePermissionsFromRole($roleId, $permissions);
    }

    // --- UserRole assignment and bulk ---
    public function assignRoleToUser($userId, $roleId)
    {
        return $this->userRoleManager->assignRole($userId, $roleId);
    }
    public function revokeRoleFromUser($userId, $roleId)
    {
        return $this->userRoleManager->revokeRole($userId, $roleId);
    }
    public function getUserRoles($userId)
    {
        return $this->userRoleManager->getUserRoles($userId);
    }
    public function getUserRolesDetailed($userId)
    {
        return $this->userRoleManager->getRolesDetailed($userId);
    }
    public function bulkAssignRolesToUser($userId, $roleIds)
    {
        return $this->userRoleManager->bulkAssignRoles($userId, $roleIds);
    }
    public function bulkRevokeRolesFromUser($userId, $roleIds)
    {
        return $this->userRoleManager->bulkRevokeRoles($userId, $roleIds);
    }
    public function bulkAssignUsersToRole($roleId, $userIds)
    {
        return $this->userRoleManager->bulkAssignUsersToRole($roleId, $userIds);
    }
    public function bulkUpdateDataScope(array $userIds, string $scope): array
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
        if (!$ids) {
            return ['success' => false, 'error' => 'Select at least one valid account.'];
        }
        if (count($ids) > 500) {
            return ['success' => false, 'error' => 'A bulk workspace change is limited to 500 accounts.'];
        }
        if (!in_array($scope, ['live', 'test', 'both'], true)) {
            return ['success' => false, 'error' => 'Invalid workspace scope.'];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();
            $find = $this->db->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
            $find->execute($ids);
            $foundIds = array_map('intval', $find->fetchAll(PDO::FETCH_COLUMN));
            if (!$foundIds) {
                if ($ownsTransaction) $this->db->commit();
                return ['success' => false, 'error' => 'None of the selected accounts still exist.'];
            }
            $foundPlaceholders = implode(',', array_fill(0, count($foundIds), '?'));
            $update = $this->db->prepare("UPDATE users SET data_scope = ?, updated_at = NOW() WHERE id IN ($foundPlaceholders)");
            $update->execute(array_merge([$scope], $foundIds));
            if ($ownsTransaction) $this->db->commit();

            Logger::audit('user_data_scope_bulk_updated', 'user', $foundIds[0], 'User workspace scope updated in bulk.', [
                'user_ids' => $foundIds,
                'data_scope' => $scope,
                'updated_by' => (int) $this->getCurrentUserId(),
            ]);
            return [
                'success' => true,
                'data' => ['updated' => $foundIds, 'skipped' => array_values(array_diff($ids, $foundIds)), 'data_scope' => $scope],
            ];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            Logger::legacyError('Bulk user workspace update failed: ' . $error->getMessage());
            return ['success' => false, 'code' => 500, 'error' => 'Workspace changes could not be saved.'];
        }
    }
    public function bulkRevokeUsersFromRole($roleId, $userIds)
    {
        return $this->userRoleManager->bulkRevokeUsersFromRole($roleId, $userIds);
    }
    public function getUsersWithRole($roleName)
    {
        return $this->userRoleManager->getUsersWithRole($roleName);
    }
    public function getUsersWithMultipleRoles()
    {
        return $this->userRoleManager->getUsersWithMultipleRoles();
    }

    // --- UserPermission assignment and bulk ---
    public function assignPermissionToUserDirect($userId, $permission)
    {
        return $this->userPermissionManager->assignPermission($userId, $permission);
    }
    public function revokePermissionFromUserDirect($userId, $permissionId)
    {
        return $this->userPermissionManager->revokePermission($userId, $permissionId);
    }
    public function getUserPermissionsEffective($userId)
    {
        return $this->userPermissionManager->getEffectivePermissions($userId);
    }
    public function getUserPermissionsDirect($userId)
    {
        return $this->userPermissionManager->getDirectPermissions($userId);
    }
    public function getUserPermissionsDenied($userId)
    {
        return $this->userPermissionManager->getDeniedPermissions($userId);
    }
    public function getUserPermissionsByEntity($userId)
    {
        return $this->userPermissionManager->getPermissionsByEntity($userId);
    }
    public function getUserPermissionSummary($userId)
    {
        return $this->userPermissionManager->getPermissionSummary($userId);
    }
    public function checkUserPermission($userId, $permissionCode)
    {
        return $this->userPermissionManager->hasPermission($userId, $permissionCode);
    }
    public function checkUserPermissions($userId, $permissionCodes)
    {
        return $this->userPermissionManager->hasPermissions($userId, $permissionCodes);
    }
    public function bulkAssignPermissionsToUserDirect($userId, $permissions)
    {
        return $this->userPermissionManager->bulkAssignPermissions($userId, $permissions);
    }
    public function bulkRevokePermissionsFromUserDirect($userId, $permissionIds)
    {
        return $this->userPermissionManager->bulkRevokePermissions($userId, $permissionIds);
    }
    public function bulkAssignUsersToPermission($permissionId, $userIds, $permType = 'grant')
    {
        return $this->userPermissionManager->bulkAssignUsersToPermission($permissionId, $userIds, $permType);
    }
    public function bulkRevokeUsersFromPermission($permissionId, $userIds)
    {
        return $this->userPermissionManager->bulkRevokeUsersFromPermission($permissionId, $userIds);
    }
    public function getUsersWithPermission($permissionCode)
    {
        return $this->userPermissionManager->getUsersWithPermission($permissionCode);
    }
    public function getUsersWithTemporaryPermissions()
    {
        return $this->userPermissionManager->getUsersWithTemporaryPermissions();
    }

    // === Controller-required CRUD and utility methods ===
    public function get($id)
    {
        // Never expose password hashes through the user-management API.
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, p.email, p.first_name, p.last_name,
                    r.id AS role_id, r.name AS role_name, u.status, u.last_login,
                    u.password_changed_at, u.created_at, u.updated_at,
                    u.failed_login_attempts, u.account_locked_until,
                    u.password_expires_at, u.force_password_change, u.is_test_user,
                    u.account_type, u.data_scope,
                    g.id AS test_access_grant_id, g.purpose AS test_access_purpose,
                    g.starts_at AS test_access_starts_at,
                    g.expires_at AS test_access_expires_at,
                    g.status AS test_access_status
             FROM users u
             LEFT JOIN persons p ON p.id = u.person_id
             LEFT JOIN test_account_access_grants g ON g.id = (
                 SELECT tg.id FROM test_account_access_grants tg
                 WHERE tg.user_id=u.id AND tg.environment=?
                 ORDER BY tg.created_at DESC,tg.id DESC LIMIT 1
             )
             LEFT JOIN roles r ON r.id = (
                 SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.is_primary DESC, ur.id LIMIT 1
             )
             WHERE u.id = ?"
        );
        $stmt->execute([TestAccountAccessService::environment(), $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $single = [$user];
            $this->attachRolesToUsers($single);
            return ['success' => true, 'data' => $single[0]];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    }
    public function list($data = [])
    {
        (new TestAccountAccessService($this->db))->expireDueGrants();
        // List all users (optionally filter by status, role, etc.)
        $sql = "SELECT u.id, u.username, p.email, p.first_name, p.last_name,
                       r.id AS role_id, r.name AS role_name, u.status, u.last_login,
                       u.password_changed_at, u.created_at, u.updated_at,
                       u.failed_login_attempts, u.account_locked_until,
                       u.password_expires_at, u.force_password_change, u.is_test_user,
                       u.account_type, u.data_scope,
                       g.id AS test_access_grant_id, g.purpose AS test_access_purpose,
                       g.starts_at AS test_access_starts_at,
                       g.expires_at AS test_access_expires_at,
                       g.status AS test_access_status
                FROM users u
                LEFT JOIN persons p ON p.id = u.person_id
                LEFT JOIN test_account_access_grants g ON g.id = (
                    SELECT tg.id FROM test_account_access_grants tg
                    WHERE tg.user_id=u.id AND tg.environment=?
                    ORDER BY tg.created_at DESC,tg.id DESC LIMIT 1
                )
                LEFT JOIN roles r ON r.id = (
                    SELECT ur.role_id
                    FROM user_roles ur
                    INNER JOIN roles rl ON rl.id = ur.role_id
                    WHERE ur.user_id = u.id AND rl.is_active = 1
                    ORDER BY ur.is_primary DESC, ur.id LIMIT 1
                )";
        $params = [TestAccountAccessService::environment()];
        if (isset($data['status'])) {
            $sql .= ' WHERE u.status = ?';
            $params[] = $data['status'];
        }
        $sql .= ' ORDER BY u.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->attachRolesToUsers($users);
        return ['success' => true, 'data' => $users];
    }

    /**
     * Attach a `roles` array (all active roles, each with id/name/is_active)
     * to each user row while preserving `role_name`/`role_id` as the primary.
     */
    private function attachRolesToUsers(array &$users)
    {
        if (empty($users)) {
            return;
        }
        $ids = array_values(array_unique(array_map('intval', array_column($users, 'id'))));
        if (empty($ids)) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT ur.user_id, r.id AS role_id, r.name AS role_name, r.is_active, ur.is_primary
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id IN ($in)
             ORDER BY ur.user_id, ur.is_primary DESC, ur.id"
        );
        $stmt->execute($ids);
        $rolesByUser = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rolesByUser[(int) $row['user_id']][] = [
                'id' => (int) $row['role_id'],
                'name' => $row['role_name'],
                'role_name' => $row['role_name'],
                'is_active' => (int) $row['is_active'],
                'is_primary' => (int) ($row['is_primary'] ?? 0) === 1,
            ];
        }
        foreach ($users as &$user) {
            $uid = (int) $user['id'];
            $user['roles'] = $rolesByUser[$uid] ?? [];
            if (empty($user['role_name']) && !empty($user['roles'])) {
                $user['role_id'] = $user['roles'][0]['id'];
                $user['role_name'] = $user['roles'][0]['name'];
            }
        }
        unset($user);
    }
    public function create($data, bool $allowIncompleteStaffProfile = false, bool $allowStaffLifecycle = false)
    {
        // Username formation has one owner. Callers provide identity data only;
        // this service derives a valid, unique username for every creation route.
        $data['username'] = UsernameService::generate(
            $this->db,
            (string) ($data['email'] ?? ''),
            (string) ($data['first_name'] ?? ''),
            (string) ($data['last_name'] ?? '')
        );
        // Normalize incoming payload: accept flattened staff fields (staff_type_id, department_id, etc.)
        // and move them into `staff_info` expected by business logic/validation.
        $staffFieldKeys = [
            'staff_type_id',
            'staff_category_id',
            'department_id',
            'supervisor_id',
            'position',
            'employment_date',
            'contract_type',
            'nssf_no',
            'kra_pin',
            'nhif_no',
            'bank_account',
            'salary',
            'gender',
            'marital_status',
            'tsc_no',
            'address',
            'profile_pic_url',
            'documents_folder',
            'date_of_birth'
        ];
        if (empty($data['staff_info'])) {
            $staffInfo = [];
            foreach ($staffFieldKeys as $k) {
                if (isset($data[$k])) {
                    $staffInfo[$k] = $data[$k];
                    // keep payload tidy by unsetting top-level staff fields (optional)
                    unset($data[$k]);
                }
            }
            if (!empty($staffInfo)) {
                $data['staff_info'] = $staffInfo;
            }
        }

        $accountType = strtolower((string) ($data['account_type']
            ?? (TestAccountAccessService::environment() === 'development' ? 'test' : 'real')));
        if (!in_array($accountType, ['real', 'test', 'service'], true)) {
            return ['success' => false, 'error' => 'Invalid account type'];
        }
        $isTestAccount = $accountType === 'test';
        $dataScope = strtolower((string) ($data['data_scope'] ?? ''));
        if (!in_array($dataScope, ['live', 'test', 'both'], true)) {
            $dataScope = $isTestAccount ? 'test' : 'live';
        }
        $recordScope = $isTestAccount ? 'test' : 'live';
        if ($isTestAccount && TestAccountAccessService::environment() !== 'development') {
            if (empty($data['test_access_expires_at']) || empty($data['test_access_purpose'])) {
                return ['success' => false, 'error' => 'Production and staging test accounts require a purpose and expiry date'];
            }
        }

        // Validate input data
        $validation = ValidationHelper::validateUserData($data, $this->db, false);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        $validatedData = $validation['data'];

        // Extract role_ids from input (accept role_ids array or single role_id)
        $roleIds = [];
        if (isset($data['role_ids']) && is_array($data['role_ids'])) {
            $roleIds = array_values(array_filter($data['role_ids'], 'is_numeric'));
        } elseif (isset($data['role_id']) && is_numeric($data['role_id'])) {
            $roleIds = [(int) $data['role_id']];
        }

        // Do not auto-assign a default role. Role must be provided by frontend.
        if (empty($roleIds)) {
            throw new Exception('Role ID(s) must be provided on user creation');
        }
        $rolePlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
        $roleScopeStmt = $this->db->prepare("SELECT id,name,scope,is_system FROM roles WHERE id IN ($rolePlaceholders)");
        $roleScopeStmt->execute(array_map('intval', $roleIds));
        $requestedRoles = $roleScopeStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($requestedRoles) !== count(array_unique(array_map('intval', $roleIds)))) {
            return ['success' => false, 'error' => 'One or more selected roles do not exist.'];
        }
        $hasStaffRole = false;
        foreach ($requestedRoles as $requestedRole) {
            $roleName = strtolower(trim((string)$requestedRole['name']));
            if (strtolower((string)$requestedRole['scope']) === 'school'
                && $roleName !== 'parent'
                && (int)$requestedRole['is_system'] !== 1) {
                $hasStaffRole = true;
            }
        }
        if ($hasStaffRole && !$allowStaffLifecycle) {
            return ['success' => false, 'error' => 'Staff accounts must be created through the staff directory or staff onboarding workflow so employment assignment, invitation, and onboarding records are created together.'];
        }
        $hasSchoolAdministratorRole = false;
        foreach ($requestedRoles as $requestedRole) {
            if (strtolower(trim((string)$requestedRole['name'])) === 'school administrator') {
                $hasSchoolAdministratorRole = true;
                break;
            }
        }
        if ($hasSchoolAdministratorRole
            && (!isset($data['staff_info']) || !is_array($data['staff_info']))
            && !$allowIncompleteStaffProfile) {
            return ['success' => false, 'error' => 'School Administrator accounts must be created through the administrator invitation or staff-onboarding workflow.'];
        }

        // Join an outer workflow transaction when one exists. This lets staff
        // onboarding commit Person + User + Staff + payroll as one unit.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $primaryRoleId = $roleIds[0];

            // STEP 1: Create the person record (identity: names + email)
            $personStmt = $this->db->prepare(
                'INSERT INTO persons (first_name, middle_name, last_name, email, data_scope)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $personOk = $personStmt->execute([
                $validatedData['first_name'] ?? '',
                $data['middle_name'] ?? null,
                $validatedData['last_name'] ?? '',
                $validatedData['email'] ?? null,
                $recordScope,
            ]);
            if (!$personOk) {
                throw new Exception('Person creation failed');
            }
            $personId = (int)$this->db->lastInsertId();

            // STEP 2: Create user record linked to the person (roles via user_roles)
            $sql = 'INSERT INTO users (username, password_hash, person_id, status, last_login, password_changed_at, force_password_change, is_test_user, account_type, data_scope, two_factor_enabled, two_factor_method, two_factor_verified_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'email\', NULL, NOW(), NOW())';
            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $validatedData['username'],
                password_hash($validatedData['password'], PASSWORD_DEFAULT),
                $personId,
                $validatedData['status'] ?? 'active',
                $data['last_login'] ?? null,
                $data['password_changed_at'] ?? null,
                $data['force_password_change'] ?? 0,
                $isTestAccount ? 1 : 0,
                $accountType,
                $dataScope,
            ]);
            $userId = (int)$this->db->lastInsertId();

            if (!$ok) {
                throw new Exception('User creation failed');
            }

            $this->db->prepare("INSERT INTO user_two_factor_methods (user_id, method, label, is_primary, is_enabled, verified_at) VALUES (?, 'email', 'Account email', 1, 1, NULL) ON DUPLICATE KEY UPDATE is_enabled=1, is_primary=1")
                ->execute([$userId]);

            \App\API\Services\Logger::legacyError("User creation: inserted id=$userId");

            // STEP 3: Assign PRIMARY role and copy its permissions
            // Only the primary role is assigned to user_roles (for consistency)
            $rolesAssigned = 0;
            $roleResult = $this->userRoleManager->assignRole($userId, $primaryRoleId);
            \App\API\Services\Logger::legacyError("User creation: assignRole result=" . json_encode($roleResult));
            if ($roleResult['success']) {
                $rolesAssigned++;
            } else {
                throw new Exception('Failed to assign primary role ' . $primaryRoleId);
            }

            // STEP 3b: If there are ADDITIONAL roles beyond the primary, assign them too
            if (count($roleIds) > 1) {
                for ($i = 1; $i < count($roleIds); $i++) {
                    $additionalRoleId = $roleIds[$i];
                    if ($additionalRoleId === $primaryRoleId) {
                        continue; // Skip duplicate primary role
                    }
                    $roleResult = $this->userRoleManager->assignRole($userId, $additionalRoleId, false);
                    if ($roleResult['success']) {
                        $rolesAssigned++;
                    } else {
                        throw new Exception('Failed to assign additional role ' . $additionalRoleId);
                    }
                }
            }

            // Parent accounts must always carry the canonical Parent role,
            // even when provisioned through a generic user-creation workflow.
            $activeParent = $this->db->prepare("SELECT 1 FROM parents WHERE person_id=? AND status='active' LIMIT 1");
            $activeParent->execute([$personId]);
            if ($activeParent->fetchColumn()) $this->userRoleManager->assignRole($userId, 73, false);

            // STEP 4: Override permissions if explicitly provided
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $perm) {
                    $permData = is_array($perm) ? $perm : ['permission_code' => $perm];
                    $this->userPermissionManager->assignPermission($userId, $permData);
                }
            }

            // STEP 5: Add to staff table (only if front-end provided staff_info). Do NOT auto-create staff without explicit data.
            $isSystemAdmin = $this->isSystemAdmin($roleIds);
            if (!$isSystemAdmin && isset($data['staff_info']) && is_array($data['staff_info'])) {
                $staffInfo = $data['staff_info'];

                // These define the school-owned assignment. Personal and payroll
                // details may be completed by the employee after account setup.
                $requiredStaffFields = [
                    'department_id', 'position', 'employment_date', 'contract_type',
                    'staff_type_id', 'staff_category_id',
                ];
                $missingStaff = [];
                foreach ($requiredStaffFields as $f) {
                    if (empty($staffInfo[$f])) {
                        $missingStaff[] = $f;
                    }
                }
                if (!empty($missingStaff)) {
                    throw new Exception('Missing required staff fields: ' . implode(', ', $missingStaff));
                }

                // The explicit employment assignment is validated before the
                // normalized staff record is written.
                $staffId = $this->addToStaffTable($userId, $staffInfo, $roleIds);
                if (!$staffId) {
                    throw new Exception('Failed to add staff record');
                }
            } elseif (!$isSystemAdmin) {
                // If not system admin and no staff_info provided, enforce explicitness
                // Do NOT auto-add staff. Frontend must create staff explicitly if needed.
                // We don't throw here to allow non-staff users to be created, but we require explicit staff creation when needed.
            }

            // STEP 6: Audit log
            $currentUserId = $this->getCurrentUserId();
            if ($isTestAccount && !empty($data['test_access_expires_at'])) {
                (new TestAccountAccessService($this->db))->grant(
                    $userId,
                    (string) ($data['test_access_purpose'] ?? 'Feature testing'),
                    (string) ($data['test_access_starts_at'] ?? date('Y-m-d H:i:s')),
                    (string) $data['test_access_expires_at'],
                    (int) $currentUserId
                );
            }
            $this->auditLogger->logUserCreate($currentUserId, $userId, $validatedData);

            if ($ownsTransaction) {
                $this->db->commit();
            }

            // Return complete user data with roles and permissions
            $userData = $this->get($userId)['data'];
            $userData['roles'] = $this->userRoleManager->getUserRoles($userId)['data'] ?? [];
            $userData['permissions'] = $this->userPermissionManager->getEffectivePermissions($userId)['data'] ?? [];

            return [
                'success' => true,
                'data' => $userData,
                'meta' => [
                    'roles_assigned' => $rolesAssigned,
                    'staff_added' => !$isSystemAdmin && isset($data['staff_info'])
                ]
            ];

        } catch (\DomainException|\InvalidArgumentException $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError("User creation error: " . $e->getMessage());
            \App\API\Services\Logger::legacyError('[UsersAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
        \App\API\Services\Logger::legacyError($e->getTraceAsString());

    }

    // Add staff record for an existing user (useful when user exists but staff row is missing)
    public function addStaffForUser($userId, $staffInfo, $roleIds = [])
    {
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();
            $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)$roleIds), static fn($id) => $id > 0)));
            if (!$roleIds) throw new Exception('At least one staff role is required.');
            $holders = implode(',', array_fill(0, count($roleIds), '?'));
            $roleCheck = $this->db->prepare("SELECT id,name FROM roles WHERE is_active=1 AND scope='school' AND is_system=0 AND id IN ($holders)");
            $roleCheck->execute($roleIds);
            $roles = $roleCheck->fetchAll(PDO::FETCH_ASSOC);
            if (count($roles) !== count($roleIds)) throw new Exception('A selected staff role is inactive or invalid.');
            foreach ($roles as $role) {
                if (in_array(strtolower(trim((string)$role['name'])), ['system administrator', 'parent'], true)) {
                    throw new Exception('System Administrator and Parent roles cannot be assigned through staff creation.');
                }
            }
            $hasPrimary = $this->userRoleManager->primaryRoleId((int)$userId) !== null;
            foreach ($roleIds as $index => $roleId) {
                $assigned = $this->userRoleManager->assignRole((int)$userId, $roleId, !$hasPrimary && $index === 0);
                if (empty($assigned['success'])) throw new Exception('Failed to assign the selected staff role.');
            }
            $staffId = $this->addToStaffTable($userId, $staffInfo, $roleIds);
            if (!$staffId) throw new Exception('Failed to add staff record.');
            if ($ownsTransaction) $this->db->commit();
            return ['success' => true, 'staff_id' => $staffId];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UsersAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Create the first login for a person who already has an established staff
     * row. This keeps the existing persons/staff identity and does not create a
     * duplicate staff record.
     */
    public function createStaffAccountForExistingPerson(int $personId, array $data, array $roleIds): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $person = $this->db->prepare(
                "SELECT p.id,p.email,p.first_name,p.last_name
                 FROM persons p JOIN staff s ON s.person_id=p.id
                 WHERE p.id=? LIMIT 1 FOR UPDATE"
            );
            $person->execute([$personId]);
            $identity = $person->fetch(PDO::FETCH_ASSOC);
            if (!$identity || !filter_var($identity['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Existing staff identity has no valid email address.');
            }
            // This branch is only for an existing staff row that has never had
            // a user account. Bring its identity fields up to the school's
            // verified record before creating the initial login.
            $this->db->prepare("UPDATE persons SET
                    first_name=COALESCE(NULLIF(?,''),first_name),
                    middle_name=COALESCE(NULLIF(?,''),middle_name),
                    last_name=COALESCE(NULLIF(?,''),last_name),
                    phone=COALESCE(NULLIF(?,''),phone),
                    gender=COALESCE(NULLIF(?,''),gender),
                    dob=COALESCE(NULLIF(?,''),dob)
                WHERE id=?")
                ->execute([
                    trim((string)($data['first_name'] ?? '')),
                    trim((string)($data['middle_name'] ?? '')),
                    trim((string)($data['last_name'] ?? '')),
                    trim((string)($data['staff_info']['phone'] ?? '')),
                    trim((string)($data['staff_info']['gender'] ?? '')),
                    trim((string)($data['staff_info']['date_of_birth'] ?? '')),
                    $personId,
                ]);
            $existing = $this->db->prepare('SELECT id FROM users WHERE person_id=? LIMIT 1 FOR UPDATE');
            $existing->execute([$personId]);
            if ($existing->fetchColumn()) throw new Exception('This staff identity already has a user account.');
            if (!$roleIds) throw new Exception('At least one school role is required.');

            $roleCheck = $this->db->prepare('SELECT id FROM roles WHERE id=? AND is_active=1');
            foreach ($roleIds as $roleId) {
                $roleCheck->execute([(int)$roleId]);
                if (!$roleCheck->fetchColumn()) throw new Exception('A selected staff role is inactive or invalid.');
            }
            $username = UsernameService::generate(
                $this->db,
                (string)$identity['email'],
                (string)$identity['first_name'],
                (string)$identity['last_name']
            );
            $password = (string)($data['password'] ?? '');
            if ($password === '') throw new Exception('A one-time credential is required for the invitation.');
            $stmt = $this->db->prepare(
                "INSERT INTO users
                    (username,password_hash,person_id,status,force_password_change,is_test_user,account_type,data_scope,two_factor_enabled,two_factor_method,created_at,updated_at)
                 VALUES (?,?,?,'active',1,0,'real','live',1,'email',NOW(),NOW())"
            );
            $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT),$personId]);
            $userId = (int)$this->db->lastInsertId();
            foreach (array_values(array_unique(array_map('intval',$roleIds))) as $index => $roleId) {
                $result = $this->userRoleManager->assignRole($userId, $roleId, $index === 0);
                if (empty($result['success'])) throw new Exception('Failed to assign the selected staff role.');
            }
            $this->db->prepare("INSERT INTO user_two_factor_methods(user_id,method,label,is_primary,is_enabled,verified_at) VALUES(?,'email','Account email',1,1,NULL) ON DUPLICATE KEY UPDATE is_enabled=1,is_primary=1")
                ->execute([$userId]);
            if ($ownsTransaction) $this->db->commit();
            return ['success'=>true,'data'=>['id'=>$userId,'username'=>$username,'person_id'=>$personId]];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UsersAPI] Existing staff account creation failed: '.$error->getMessage());
            return ['success'=>false,'error'=>$error->getMessage()];
        }
    }

    public function bulkCreate($data)
    {
        // Create multiple users with automatic role/permission assignment in a transaction
        if (!isset($data['users']) || !is_array($data['users']) || empty($data['users'])) {
            return ['success' => false, 'error' => 'users array is required and must not be empty'];
        }

        $this->db->beginTransaction();
        $created = [];
        $failed = [];

        try {
            $personStmt = $this->db->prepare('INSERT INTO persons (first_name, middle_name, last_name, email, data_scope) VALUES (?, ?, ?, ?, ?)');
            $stmt = $this->db->prepare('INSERT INTO users (username, password_hash, person_id, status, last_login, password_changed_at, force_password_change, is_test_user, account_type, data_scope, two_factor_enabled, two_factor_method, two_factor_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'email\', NULL, NOW(), NOW())');

            foreach ($data['users'] as $index => $userData) {
                // Normalize top-level staff fields into staff_info for each user record
                $staffFieldKeys = [
                    'staff_type_id',
                    'staff_category_id',
                    'department_id',
                    'supervisor_id',
                    'position',
                    'employment_date',
                    'contract_type',
                    'nssf_no',
                    'kra_pin',
                    'nhif_no',
                    'bank_account',
                    'salary',
                    'gender',
                    'marital_status',
                    'tsc_no',
                    'address',
                    'profile_pic_url',
                    'documents_folder',
                    'date_of_birth'
                ];
                if (empty($userData['staff_info'])) {
                    $staffInfoLocal = [];
                    foreach ($staffFieldKeys as $k) {
                        if (isset($userData[$k])) {
                            $staffInfoLocal[$k] = $userData[$k];
                            unset($userData[$k]);
                        }
                    }
                    if (!empty($staffInfoLocal)) {
                        $userData['staff_info'] = $staffInfoLocal;
                    }
                }
                // Validate required fields
                if (empty($userData['email']) || empty($userData['password'])) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'Missing required fields: email, password'
                    ];
                    continue;
                }

                $userData['username'] = UsernameService::generate(
                    $this->db,
                    (string) $userData['email'],
                    (string) ($userData['first_name'] ?? ''),
                    (string) ($userData['last_name'] ?? '')
                );

                // Extract role_ids
                $roleIds = [];
                if (isset($userData['role_ids']) && is_array($userData['role_ids'])) {
                    $roleIds = array_filter($userData['role_ids'], 'is_numeric');
                } elseif (isset($userData['role_id']) && is_numeric($userData['role_id'])) {
                    $roleIds = [$userData['role_id']];
                }
                if (empty($roleIds)) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'A role is required.'
                    ];
                    continue;
                }
                $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
                $rolePlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
                $roleStmt = $this->db->prepare("SELECT id,name,scope,is_system FROM roles WHERE is_active=1 AND id IN ($rolePlaceholders)");
                $roleStmt->execute($roleIds);
                $bulkRoles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($bulkRoles) !== count($roleIds)) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'One or more selected roles are inactive or invalid.'
                    ];
                    continue;
                }
                $hasStaffRole = false;
                foreach ($bulkRoles as $bulkRole) {
                    if (strtolower((string)$bulkRole['scope']) === 'school'
                        && strtolower(trim((string)$bulkRole['name'])) !== 'parent'
                        && (int)$bulkRole['is_system'] !== 1) {
                        $hasStaffRole = true;
                    }
                }
                if ($hasStaffRole) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'Staff accounts must be created through the staff directory or spreadsheet import so employment assignment, invitation, and onboarding records are created together.'
                    ];
                    continue;
                }
                if (!empty($userData['staff_info']) && is_array($userData['staff_info'])) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'Employment assignments can only be added through the staff directory or staff onboarding workflow.'
                    ];
                    continue;
                }

                try {
                    $this->db->exec('SAVEPOINT bulk_user_row');
                    $accountType = strtolower((string) ($userData['account_type']
                        ?? (TestAccountAccessService::environment() === 'development' ? 'test' : 'real')));
                    if (!in_array($accountType, ['real', 'test', 'service'], true)) {
                        throw new Exception('Invalid account type');
                    }
                    $isTestAccount = $accountType === 'test';
                    $dataScope = strtolower((string) ($userData['data_scope'] ?? ''));
                    if (!in_array($dataScope, ['live', 'test', 'both'], true)) {
                        $dataScope = $isTestAccount ? 'test' : 'live';
                    }
                    $recordScope = $isTestAccount ? 'test' : 'live';
                    if ($isTestAccount && TestAccountAccessService::environment() !== 'development'
                        && (empty($userData['test_access_purpose']) || empty($userData['test_access_expires_at']))) {
                        throw new Exception('Production and staging test accounts require a purpose and expiry date');
                    }

                    // Create person record
                    $personOk = $personStmt->execute([
                        $userData['first_name'] ?? '',
                        $userData['middle_name'] ?? null,
                        $userData['last_name'] ?? '',
                        $userData['email'],
                        $recordScope,
                    ]);
                    if (!$personOk) {
                        throw new Exception('Person creation failed');
                    }
                    $personId = (int)$this->db->lastInsertId();

                    // Create user
                    $ok = $stmt->execute([
                        $userData['username'],
                        password_hash($userData['password'], PASSWORD_DEFAULT),
                        $personId,
                        $userData['status'] ?? 'active',
                        $userData['last_login'] ?? null,
                        $userData['password_changed_at'] ?? null,
                        $userData['force_password_change'] ?? 0,
                        $isTestAccount ? 1 : 0,
                        $accountType,
                        $dataScope,
                    ]);
                    $userId = (int)$this->db->lastInsertId();

                    if (!$ok) {
                        throw new Exception('User creation failed');
                    }
                    $this->db->prepare("INSERT INTO user_two_factor_methods (user_id, method, label, is_primary, is_enabled, verified_at) VALUES (?, 'email', 'Account email', 1, 1, NULL) ON DUPLICATE KEY UPDATE is_enabled=1, is_primary=1")
                        ->execute([$userId]);

                    $rolesAssigned = 0;

                    // Assign roles (auto-copies permissions)
                    foreach ($roleIds as $roleId) {
                        $roleResult = $this->userRoleManager->assignRole($userId, $roleId, $rolesAssigned === 0);
                        if ($roleResult['success']) {
                            $rolesAssigned++;
                        }
                    }

                    // Override permissions if provided
                    if (isset($userData['permissions']) && is_array($userData['permissions'])) {
                        foreach ($userData['permissions'] as $perm) {
                            $permData = is_array($perm) ? $perm : ['permission_code' => $perm];
                            $this->userPermissionManager->assignPermission($userId, $permData);
                        }
                    }

                    // Bulk user creation is for non-staff accounts only. Staff
                    // identity, assignment, invitation, and onboarding must be
                    // created atomically through the dedicated staff workflows.
                    $staffAdded = false;

                    if ($isTestAccount && !empty($userData['test_access_expires_at'])) {
                        (new TestAccountAccessService($this->db))->grant(
                            $userId,
                            (string) $userData['test_access_purpose'],
                            (string) ($userData['test_access_starts_at'] ?? date('Y-m-d H:i:s')),
                            (string) $userData['test_access_expires_at'],
                            (int) $this->getCurrentUserId()
                        );
                    }

                    $this->db->exec('RELEASE SAVEPOINT bulk_user_row');

                    $created[] = [
                        'index' => $index,
                        'user_id' => $userId,
                        'username' => $userData['username'],
                        'email' => $userData['email'],
                        'roles_assigned' => $rolesAssigned,
                        'staff_added' => $staffAdded
                    ];

                } catch (Exception $e) {
                    $this->db->exec('ROLLBACK TO SAVEPOINT bulk_user_row');
                    $this->db->exec('RELEASE SAVEPOINT bulk_user_row');
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'An internal error occurred.'
                    ];
                }
            }

            $this->db->commit();
            return [
                'success' => true,
                'data' => [
                    'created' => $created,
                    'failed' => $failed,
                    'summary' => [
                        'total' => count($data['users']),
                        'created_count' => count($created),
                        'failed_count' => count($failed)
                    ]
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            \App\API\Services\Logger::legacyError("Bulk user creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }
    public function update($id, $data)
    {
        // Get current user data for audit log
        $oldDataResult = $this->get($id);
        if (!$oldDataResult['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $oldData = $oldDataResult['data'];

        // Account type conversion (real <-> test) is an explicit System Admin
        // decision and cascades to the linked person, user mirror flag and the
        // person's staff records. 'service' accounts can also be converted.
        $conversion = null;
        if (isset($data['account_type'])) {
            $newType = strtolower((string) $data['account_type']);
            if (!in_array($newType, ['real', 'test', 'service'], true)) {
                return ['success' => false, 'error' => 'Invalid account type'];
            }
            $oldType = strtolower((string) ($oldData['account_type'] ?? ''));
            if ($newType !== $oldType) {
                $conversion = ['from' => $oldType, 'to' => $newType];
            }
        }

        $testAccessAction = strtolower((string) ($data['test_access_action'] ?? ''));
        $hasTestAccessChange = in_array($testAccessAction, ['grant', 'revoke'], true);

        // Validate input data
        $validation = ValidationHelper::validateUserData($data, $this->db, true, $id);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        $validatedData = $validation['data'];

        // Build update queries: user columns on users, identity columns on persons
        $userFields = [];
        $userParams = [];
        $personFields = [];
        $personParams = [];

        foreach (['username', 'status'] as $field) {
            if (isset($validatedData[$field])) {
                $userFields[] = "$field = ?";
                $userParams[] = $validatedData[$field];
            }
        }

        foreach (['first_name', 'last_name', 'email'] as $field) {
            if (isset($validatedData[$field])) {
                $personFields[] = "$field = ?";
                $personParams[] = $validatedData[$field];
            }
        }

        if (isset($validatedData['password'])) {
            $userFields[] = 'password_hash = ?';
            $userParams[] = password_hash($validatedData['password'], PASSWORD_DEFAULT);
        }

        // Per-account visibility knob: overrides which sides this account can see.
        if (isset($data['data_scope'])) {
            $dataScope = strtolower((string) $data['data_scope']);
            if (in_array($dataScope, ['live', 'test', 'both'], true)) {
                $userFields[] = 'data_scope = ?';
                $userParams[] = $dataScope;
            }
        }

        // Account type conversion fields + cascade.
        $cascade = null;
        if ($conversion !== null) {
            $targetIsTest = $conversion['to'] === 'test';
            $recordScope = $targetIsTest ? 'test' : 'live';
            $userFields[] = 'account_type = ?';
            $userParams[] = $conversion['to'];
            $userFields[] = 'is_test_user = ?';
            $userParams[] = $targetIsTest ? 1 : 0;
            // Default the visibility knob to the new side unless the UI picked 'both'.
            if (empty($data['data_scope']) && $oldData['data_scope'] !== 'both') {
                $userFields[] = 'data_scope = ?';
                $userParams[] = $recordScope;
            }
            $cascade = [
                'record_scope' => $recordScope,
                'target_is_test' => $targetIsTest,
            ];
        }

        if (empty($userFields) && empty($personFields) && empty($validatedData['role_ids']) && !$hasTestAccessChange && $cascade === null) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        try {
            if (!empty($userFields)) {
                $userParams[] = $id;
                $sql = 'UPDATE users SET ' . implode(', ', $userFields) . ', updated_at = NOW() WHERE id = ?';
                $stmt = $this->db->prepare($sql);
                $ok = $stmt->execute($userParams);
                if (!$ok) {
                    return ['success' => false, 'error' => 'User update failed'];
                }
            }

            if (!empty($personFields)) {
                $personParams[] = $id;
                $personSql = 'UPDATE persons SET ' . implode(', ', $personFields)
                    . ' WHERE id = (SELECT person_id FROM users WHERE id = ?)';
                $stmt = $this->db->prepare($personSql);
                $stmt->execute($personParams);
            }

            // Account conversion cascade: restamp the linked person and its
            // staff records so the whole identity graph moves to the new side.
            if ($cascade !== null) {
                $recordScope = $cascade['record_scope'];
                $personStmt = $this->db->prepare('SELECT person_id FROM users WHERE id = ?');
                $personStmt->execute([$id]);
                $personId = (int) $personStmt->fetchColumn();
                if ($personId > 0) {
                    $this->db->prepare('UPDATE persons SET data_scope = ? WHERE id = ?')
                        ->execute([$recordScope, $personId]);
                    $this->db->prepare('UPDATE staff SET data_scope = ? WHERE person_id = ?')
                        ->execute([$recordScope, $personId]);
                    $this->db->prepare('UPDATE staff_payroll_profiles spp
                         JOIN staff s ON s.id = spp.staff_id
                         SET spp.data_scope = ?
                         WHERE s.person_id = ?')
                        ->execute([$recordScope, $personId]);
                }
            }

            if (!empty($validatedData['role_ids'])) {
                $roleIds = array_values(array_unique(array_map('intval', $validatedData['role_ids'])));
                $primaryRoleId = (int)($data['primary_role_id'] ?? ($roleIds[0] ?? 0));
                $updatedRoles = $this->userRoleManager->replaceRoles((int)$id, $roleIds, $primaryRoleId, 'all');
                if (empty($updatedRoles['success'])) throw new Exception($updatedRoles['error'] ?? 'Roles could not be updated.');
            }

            if ($testAccessAction === 'grant') {
                (new TestAccountAccessService($this->db))->grant(
                    (int) $id,
                    (string) ($data['test_access_purpose'] ?? ''),
                    (string) ($data['test_access_starts_at'] ?? date('Y-m-d H:i:s')),
                    (string) ($data['test_access_expires_at'] ?? ''),
                    (int) $this->getCurrentUserId()
                );
            } elseif ($testAccessAction === 'revoke') {
                (new TestAccountAccessService($this->db))->revoke(
                    (int) $id,
                    (int) $this->getCurrentUserId(),
                    (string) ($data['test_access_revocation_reason'] ?? 'Revoked by System Administrator')
                );
            }

            // Audit log
            $currentUserId = $this->getCurrentUserId();
            $this->auditLogger->logUserUpdate($currentUserId, $id, $oldData, $validatedData);

            return ['success' => true, 'data' => $this->get($id)['data']];
        } catch (\DomainException|\InvalidArgumentException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("User update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    /**
     * Bulk grant/revoke temporary test access across several test accounts at
     * once. Localhost is exempt from grants, so these are no-ops there but still
     * return a clear result. Enforcement is decided by EnvironmentPhaseService.
     */
    public function bulkGrantTestAccess(array $userIds, array $data)
    {
        $host = (new EnvironmentPhaseService($this->db))->current()['host'];
        if ($host === 'localhost') {
            return [
                'success' => true,
                'data' => [
                    'granted' => array_values(array_unique(array_map('intval', $userIds))),
                    'skipped' => [],
                    'message' => 'Development host: test accounts need no grant.',
                ],
            ];
        }
        try {
            $service = new TestAccountAccessService($this->db);
            $result = $service->grantBulk(
                $userIds,
                (string) ($data['test_access_purpose'] ?? ''),
                (string) ($data['test_access_starts_at'] ?? date('Y-m-d H:i:s')),
                (string) ($data['test_access_expires_at'] ?? ''),
                (int) $this->getCurrentUserId()
            );
            return ['success' => true, 'data' => $result];
        } catch (\DomainException|\InvalidArgumentException $e) {
            return ['success' => false, 'code' => 400, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('Bulk test grant failed: ' . $e->getMessage());
            return ['success' => false, 'code' => 500, 'error' => 'Bulk test access could not be granted.'];
        }
    }

    public function bulkRevokeTestAccess(array $userIds, array $data)
    {
        $host = (new EnvironmentPhaseService($this->db))->current()['host'];
        if ($host === 'localhost') {
            return [
                'success' => true,
                'data' => [
                    'revoked' => array_values(array_unique(array_map('intval', $userIds))),
                    'message' => 'Development host: test accounts need no grant to revoke.',
                ],
            ];
        }
        try {
            $service = new TestAccountAccessService($this->db);
            $revoked = $service->revokeBulk(
                $userIds,
                (int) $this->getCurrentUserId(),
                (string) ($data['test_access_revocation_reason'] ?? 'Revoked by System Administrator')
            );
            return ['success' => true, 'data' => ['revoked' => $revoked]];
        } catch (\DomainException|\InvalidArgumentException $e) {
            return ['success' => false, 'code' => 400, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('Bulk test revoke failed: ' . $e->getMessage());
            return ['success' => false, 'code' => 500, 'error' => 'Bulk test access could not be revoked.'];
        }
    }

    public function delete($id)
    {
        // Get user data before deletion for audit log
        $userDataResult = $this->get($id);
        if (!$userDataResult['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $userData = $userDataResult['data'];

        // Prevent deletion of own account
        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId == $id) {
            return ['success' => false, 'error' => 'Cannot delete your own account'];
        }

        if (
            ($userData['account_type'] ?? '') === 'test' ||
            (int) ($userData['is_test_user'] ?? 0) === 1
        ) {
            try {
                $result = (new \App\API\Services\TestDataManagementService($this->db))
                    ->purgeAccount((int) $id, (int) $currentUserId, 'Deleted from System Administrator User Accounts');
                $this->auditLogger->logUserDelete($currentUserId, $id, $userData);
                return ['success' => true, 'data' => $result];
            } catch (\DomainException $error) {
                return ['success' => false, 'error' => $error->getMessage()];
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError('Test account deletion failed and was rolled back: ' . $error->getMessage());
                return ['success' => false, 'error' => 'Test account and related test data could not be deleted'];
            }
        }

        try {
            // Delete user
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $ok = $stmt->execute([$id]);

            if ($ok) {
                // Audit log
                $this->auditLogger->logUserDelete($currentUserId, $id, $userData);

                return ['success' => true, 'data' => ['id' => $id, 'deleted' => true]];
            } else {
                return ['success' => false, 'error' => 'User deletion failed'];
            }
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("User deletion error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    public function getProfile($userId)
    {
        // Fetch user profile (basic info + roles + permissions)
        $user = $this->get($userId);
        if (!$user['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $roles = $this->userRoleManager->getUserRoles($userId);
        $permissions = $this->userPermissionManager->getEffectivePermissions($userId);
        return [
            'success' => true,
            'data' => [
                'id' => $userId,
                'profile' => $user['data'],
                'roles' => $roles['data'] ?? [],
                'permissions' => $permissions['data'] ?? []
            ]
        ];
    }

    /**
     * Self-service update of the authenticated user's own personal/account
     * details. Only whitelisted person columns are accepted; employment,
     * payroll, statutory, role, and permission data is never editable here.
     */
    public function updateSelfProfile($userId, array $data)
    {
        // Whitelisted person columns a user may update on their own shared row.
        $stringFields = ['first_name', 'middle_name', 'last_name', 'phone'];
        $emailField = 'email';
        $enumGender = ['male', 'female', 'other'];
        $photoField = 'photo_url';

        $personFields = [];
        $params = [];
        $errors = [];

        foreach ($stringFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) $data[$field]);
                if (in_array($field, ['first_name', 'last_name'], true) && $value === '') {
                    $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
                    continue;
                }
                $personFields[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (array_key_exists($emailField, $data)) {
            $email = trim((string) $data[$emailField]);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email is required';
            } elseif (!ValidationHelper::isEmailUnique($email, $this->db, $userId)) {
                $errors[] = 'Email already in use by another account';
            } else {
                $personFields[] = 'email = ?';
                $params[] = $email;
            }
        }

        if (array_key_exists('gender', $data)) {
            $gender = strtolower((string) $data['gender']);
            if ($gender !== '' && !in_array($gender, $enumGender, true)) {
                $errors[] = 'Invalid gender value';
            } else {
                $personFields[] = 'gender = ?';
                $params[] = $gender !== '' ? $gender : null;
            }
        }

        if (array_key_exists('date_of_birth', $data)) {
            $dob = trim((string) $data['date_of_birth']);
            if ($dob !== '') {
                $dt = date_create($dob);
                if (!$dt) {
                    $errors[] = 'Invalid date of birth';
                } else {
                    $personFields[] = 'dob = ?';
                    $params[] = $dt->format('Y-m-d');
                }
            } else {
                $personFields[] = 'dob = ?';
                $params[] = null;
            }
        }

        if (array_key_exists($photoField, $data)) {
            $url = trim((string) $data[$photoField]);
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid photo URL';
            } else {
                $personFields[] = 'photo_url = ?';
                $params[] = $url !== '' ? $url : null;
            }
        }

        if ($errors) {
            return ['success' => false, 'error' => implode('; ', $errors)];
        }

        if (empty($personFields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }

        $params[] = $userId;
        $sql = 'UPDATE persons SET ' . implode(', ', $personFields)
            . ' WHERE id = (SELECT person_id FROM users WHERE id = ?)';
        $this->db->prepare($sql)->execute($params);

        return ['success' => true, 'data' => $this->get($userId)['data']];
    }

    /**
     * Recent session/login history for one user, from user_sessions.
     * Device/browser are derived from the stored user-agent string.
     */
    public function loginHistory($userId, int $limit = 20)
    {
        $stmt = $this->db->prepare(
            'SELECT login_time AS created_at, ip_address, user_agent,
                    session_status AS status, last_activity, logout_time
             FROM user_sessions
             WHERE user_id = ?
             ORDER BY login_time DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, (int) $userId, \PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['browser'] = $this->browserFromAgent($row['user_agent'] ?? '');
            $row['device'] = $this->deviceFromAgent($row['user_agent'] ?? '');
            unset($row['user_agent']);
        }

        return ['success' => true, 'data' => $rows];
    }

    private function browserFromAgent($agent)
    {
        foreach (['Edg/' => 'Edge', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Safari/' => 'Safari'] as $needle => $name) {
            if (stripos($agent, $needle) !== false) return $name;
        }
        return '—';
    }

    private function deviceFromAgent($agent)
    {
        if (stripos($agent, 'Mobile') !== false) return 'Mobile';
        if (stripos($agent, 'Tablet') !== false) return 'Tablet';
        if (stripos($agent, 'curl') !== false) return 'API / CLI';
        if (stripos($agent, 'bot') !== false || stripos($agent, 'spider') !== false) return 'Bot';
        return 'Desktop';
    }

    public function getRoles()
    {
        // Delegate to getAllRoles
        return ['success' => true, 'data' => $this->getAllRoles()];
    }
    public function getPermissions()
    {
        // Delegate to getAllPermissions
        return ['success' => true, 'data' => $this->getAllPermissions()];
    }
    public function updatePermissions($id, $data)
    {
        // Replace all direct user permissions with the provided list
        if (!isset($data['permissions']) || !is_array($data['permissions'])) {
            return ['success' => false, 'error' => 'permissions array required'];
        }
        // Get current permissions IDs
        $currentPerms = $this->userPermissionManager->getDirectPermissions($id);
        $currentPermIds = array_column($currentPerms['data'] ?? [], 'id');

        // Remove all current direct permissions
        if (!empty($currentPermIds)) {
            $this->userPermissionManager->bulkRevokePermissions($id, $currentPermIds);
        }
        // Assign new permissions
        $result = $this->userPermissionManager->bulkAssignPermissions($id, $data['permissions']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'permissions_updated' => true]];
    }
    public function assignRole($id, $data)
    {
        // Assign a single role to user (many-to-many)
        if (!isset($data['role_id'])) {
            return ['success' => false, 'error' => 'role_id required'];
        }
        $result = $this->userRoleManager->assignRole($id, $data['role_id']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'role_assigned' => $result['success']]];
    }
    public function assignPermission($id, $data)
    {
        // Assign a single permission to user (many-to-many)
        if (!isset($data['permission_id'])) {
            return ['success' => false, 'error' => 'permission_id required'];
        }
        $result = $this->userPermissionManager->assignPermission($id, $data['permission_id']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'permission_assigned' => $result['success']]];
    }
    public function getMainRole($id)
    {
        // Main role: first role assigned to user (if any)
        $roles = $this->userRoleManager->getUserRoles($id);
        $mainRole = null;
        if ($roles['success'] && !empty($roles['data'])) {
            $mainRole = $roles['data'][0];
        }
        return ['success' => true, 'data' => ['id' => $id, 'main_role' => $mainRole]];
    }
    public function getExtraRoles($id)
    {
        // Extra roles: all except the first assigned role
        $roles = $this->userRoleManager->getUserRoles($id);
        $extraRoles = [];
        if ($roles['success'] && count($roles['data']) > 1) {
            $extraRoles = array_slice($roles['data'], 1);
        }
        return ['success' => true, 'data' => ['id' => $id, 'extra_roles' => $extraRoles]];
    }
    public function getSidebarItems($data)
    {
        // Determine user ID
        $userId = $data['user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'error' => 'user_id required'];
        }

        // Get all roles for the user
        $rolesResult = $this->userRoleManager->getUserRoles($userId);
        $roleIds = [];
        if ($rolesResult['success'] && !empty($rolesResult['data'])) {
            foreach ($rolesResult['data'] as $role) {
                $roleIds[] = $role['role_id'] ?? $role['id'] ?? null;
            }
            $roleIds = array_values(array_filter(array_unique($roleIds)));
        }

        // Sidebar is built from config/role_sidebars.php (the single source of
        // truth) via SidebarConfigReader, identical to the login/refresh path.
        // This keeps the profile sidebar in lockstep with the login sidebar.
        $items = \App\API\Services\SidebarConfigReader::forRoles($roleIds);

        return ['success' => true, 'data' => $items];
    }
    /**
     * Build plausible local variants of a phone identifier so the entered
     * format (+254/254/0/07/local digits) can match the stored persons.phone
     * representation. Returns [] when the identifier is clearly not a phone
     * (e.g. an email or a bare username).
     */
    private function phoneLookupVariants(string $identifier): array
    {
        if (str_contains($identifier, '@')) {
            return [];
        }
        $digits = preg_replace('/\D+/', '', $identifier);
        if ($digits === '' || !preg_match('/^\d{9,13}$/', $digits)) {
            return [];
        }

        $variants = [$digits];
        if (str_starts_with($digits, '0')) {
            $variants[] = '254' . substr($digits, 1);
            $variants[] = substr($digits, 1);
        } elseif (str_starts_with($digits, '254')) {
            $variants[] = '0' . substr($digits, 3);
            $variants[] = substr($digits, 3);
        } elseif (str_starts_with($digits, '7')) {
            $variants[] = '2547' . substr($digits, 1);
            $variants[] = '07' . substr($digits, 1);
        }

        return array_values(array_unique($variants));
    }

    public function login($data, bool $issueAccessToken = true)
    {
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($username === '' || $password === '') {
            $this->recordAuthenticationAttempt(
                $username,
                null,
                'failed',
                'missing_credentials'
            );
            return ['success' => false, 'error' => 'Username and password required'];
        }

        // Lookup user by username, email or a normalized phone number. Local
        // entry formats (+254/254/0/07/7...) are reconciled to the stored
        // persons.phone representation so parents can sign in with the phone
        // number they actually remember.
        $phoneVariants = $this->phoneLookupVariants($username);
        $phoneIn = '';
        $lookupParams = [$username, $username];
        if ($phoneVariants) {
            $phoneIn = ' OR p.phone IN (' .
                implode(',', array_fill(0, count($phoneVariants), '?')) . ')';
            $lookupParams = array_merge($lookupParams, $phoneVariants);
        }
        $failureDayColumn = $this->hasDailyFailureColumn()
            ? 'u.failed_login_date'
            : 'DATE(u.updated_at)';
        $stmt = $this->db->prepare(
            'SELECT
                u.id,
                u.username,
                p.email,
                p.phone,
                u.password_hash AS password,
                p.first_name,
                p.last_name,
                (SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.is_primary DESC, ur.id LIMIT 1) AS role_id,
                u.status,
                u.force_password_change,
                u.is_test_user,
                u.account_type,
                u.data_scope,
                CASE
                    WHEN ' . $failureDayColumn . ' = CURDATE()
                    THEN COALESCE(u.failed_login_attempts, 0)
                    ELSE 0
                END AS failed_login_attempts,
                u.account_locked_until,
                CASE
                    WHEN u.account_locked_until IS NOT NULL
                     AND u.account_locked_until > NOW()
                    THEN 1
                    ELSE 0
                END AS is_locked
             FROM users u
             LEFT JOIN persons p ON p.id = u.person_id
             WHERE u.username = ? OR p.email = ?' . $phoneIn . '
             LIMIT 1'
        );
        $stmt->execute($lookupParams);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->recordAuthenticationAttempt(
                $username,
                null,
                'failed',
                'invalid_credentials'
            );
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Check lockout BEFORE password_verify to prevent timing-based
        // account enumeration (attacker would otherwise learn an account
        // exists because password_verify takes longer than the "not found" path).
        if ((int) ($user['is_locked'] ?? 0) === 1) {
            $this->recordAuthenticationAttempt(
                $username,
                (int) $user['id'],
                'failed',
                'account_locked'
            );
            return [
                'success' => false,
                'error' => 'Account locked for 15 minutes after too many unsuccessful login attempts. Please wait before trying again or contact a system administrator.'
            ];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            $failedAttempts = (int) ($user['failed_login_attempts'] ?? 0) + 1;
            $this->recordAuthenticationAttempt(
                $username,
                (int) $user['id'],
                'failed',
                'invalid_credentials',
                true
            );

            // The fifth failed attempt creates the lock. Tell the user in this
            // response instead of making them submit a sixth time to discover it.
            if ($failedAttempts >= 5) {
                return [
                    'success' => false,
                    'error' => 'Account locked for 15 minutes after too many unsuccessful login attempts. Please wait before trying again or contact a system administrator.'
                ];
            }
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        if (isset($user['status']) && $user['status'] !== 'active') {
            $this->recordAuthenticationAttempt(
                $username,
                (int) $user['id'],
                'failed',
                'account_inactive'
            );
            return ['success' => false, 'error' => 'Account is not active'];
        }

        try {
            $accessContext = (new TestAccountAccessService($this->db))
                ->requireAccess((int) $user['id']);
            $user['account_type'] = $accessContext['account_type'];
            $user['data_scope'] = $accessContext['data_scope'];
            $user['test_access_expires_at'] = $accessContext['test_access_expires_at'];
        } catch (\DomainException $error) {
            $this->recordAuthenticationAttempt(
                $username,
                (int) $user['id'],
                'failed',
                'test_access_expired'
            );
            return ['success' => false, 'error' => $error->getMessage()];
        }

        // Get roles and permissions.
        $roles = $this->userRoleManager->getUserRoles($user['id']);
        $permissions = $this->userPermissionManager->getEffectivePermissions($user['id']);

        // Extract permission CODES only (not full objects)
        $permissionCodes = [];
        if (!empty($permissions['data'])) {
            foreach ($permissions['data'] as $perm) {
                // Handle both objects and arrays
                $code = is_array($perm) ? ($perm['code'] ?? $perm['permission_code'] ?? null) : $perm;
                if ($code) {
                    $permissionCodes[] = $code;
                }
            }
        }

        // IMPORTANT: DO NOT store permissions in JWT token!
        // JWT tokens are sent with EVERY request in the Authorization header
        // Permissions should be stored in localStorage and sent separately when needed
        // This keeps the token small and prevents "Request Header Too Large" errors

        $token = null;
        $sessionId = null;
        if ($issueAccessToken) {
            // The legacy /users/login endpoint still issues its own short-lived
            // token. The canonical /auth/login path passes false and lets
            // AuthAPI create the refresh-backed session exactly once.
            $token = $this->generateJWT([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'roles' => $roles['data'] ?? []
                // NO permissions in token!
            ]);

            try {
                $sessionId = (new AuthSessionService($this->db))
                    ->upsertAccessSession(
                        (int) $user['id'],
                        $token,
                        null,
                        date('Y-m-d H:i:s', time() + 3600)
                    );
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError(
                    'Legacy user session creation failed: ' .
                    $error->getMessage()
                );
                return [
                    'success' => false,
                    'error' => 'The authenticated session could not be established',
                ];
            }
        }

        $this->recordAuthenticationAttempt(
            $username,
            (int) $user['id'],
            'success',
            null,
            false,
            true
        );

        // Return user info with token
        // Permissions are returned in the response body (not in token)
        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'session_id' => $sessionId,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role_id' => $user['role_id'],
                    'status' => $user['status'] ?? null,
                    'force_password_change' => (int)($user['force_password_change'] ?? 0),
                    'is_test_user' => (int)($user['is_test_user'] ?? 0),
                    'account_type' => $user['account_type'] ?? 'real',
                    'data_scope' => $user['data_scope'] ?? 'live',
                    'test_access_expires_at' => $user['test_access_expires_at'] ?? null,
                    'roles' => $roles['data'] ?? [],
                    'permissions' => $permissionCodes  // In response body, NOT in token
                ]
            ]
        ];
    }

    /**
     * Persist real authentication telemetry without ever storing a password.
     *
     * The login response remains available if telemetry persistence fails, but
     * the failure is written to the server error log for operational follow-up.
     */
    private function recordAuthenticationAttempt(
        string $identifier,
        ?int $userId,
        string $status,
        ?string $failureReason = null,
        bool $incrementFailedAttempts = false,
        bool $markSuccessfulLogin = false
    ): void {
        $ownsTransaction = false;

        try {
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $ownsTransaction = true;
            }

            if ($userId !== null && $incrementFailedAttempts) {
                if ($this->hasDailyFailureColumn()) {
                    $stmt = $this->db->prepare(
                        'UPDATE users
                     SET account_locked_until =
                            CASE
                                WHEN (
                                    CASE
                                        WHEN failed_login_date = CURDATE()
                                        THEN COALESCE(failed_login_attempts, 0)
                                        ELSE 0
                                    END
                                ) + 1 >= 5
                                THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE DATE_SUB(NOW(), INTERVAL 1 DAY)
                            END,
                         failed_login_attempts = (
                            CASE
                                WHEN failed_login_date = CURDATE()
                                THEN COALESCE(failed_login_attempts, 0)
                                ELSE 0
                            END
                         ) + 1,
                         failed_login_date = CURDATE(),
                         updated_at = NOW()
                     WHERE id = ?'
                    );
                } else {
                    // Backward-compatible deployment path: updated_at is set by
                    // every failed attempt and therefore identifies the day to
                    // which the current counter belongs.
                    $stmt = $this->db->prepare(
                        'UPDATE users
                         SET account_locked_until = CASE
                                WHEN (
                                    CASE WHEN DATE(updated_at) = CURDATE()
                                         THEN COALESCE(failed_login_attempts, 0)
                                         ELSE 0 END
                                ) + 1 >= 5
                                THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE DATE_SUB(NOW(), INTERVAL 1 DAY)
                             END,
                             failed_login_attempts = (
                                CASE WHEN DATE(updated_at) = CURDATE()
                                     THEN COALESCE(failed_login_attempts, 0)
                                     ELSE 0 END
                             ) + 1,
                             updated_at = NOW()
                         WHERE id = ?'
                    );
                }
                $stmt->execute([$userId]);
            } elseif ($userId !== null && $markSuccessfulLogin) {
                $dailyReset = $this->hasDailyFailureColumn()
                    ? ', failed_login_date = NULL'
                    : '';
                $stmt = $this->db->prepare(
                    'UPDATE users
                     SET last_login = NOW(),
                         failed_login_attempts = 0' . $dailyReset . ',
                         account_locked_until = DATE_SUB(NOW(), INTERVAL 1 DAY),
                         updated_at = NOW()
                     WHERE id = ?'
                );
                $stmt->execute([$userId]);
            }

            $ipAddress = substr(
                (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                0,
                45
            );
            if ($ipAddress === '') {
                $ipAddress = 'unknown';
            }

            \App\API\Includes\FileLogger::write('auth', [
                'type' => 'login_attempt',
                'username' => substr($identifier, 0, 100),
                'user_id' => $userId,
                'ip' => $ipAddress,
                'user_agent' => substr(
                    (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                    0,
                    255
                ) ?: null,
                'status' => $status,
                'failure_reason' => $failureReason === null
                    ? null
                    : substr($failureReason, 0, 100),
            ]);

            // Mirror failed authentication into the audit journal with the
            // canonical actions the Audit & Forensics console filters for.
            if (!in_array($status, ['success', 'info', 'ok'], true)) {
                \App\API\Includes\SecurityEventNotifier::failedLogin(
                    (string) $identifier,
                    (string) ($failureReason ?? 'unknown'),
                    [
                        'user_id' => $userId,
                        'entity_id' => $userId,
                        'details' => ['ip' => $ipAddress, 'identifier' => substr($identifier, 0, 100)],
                    ]
                );
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError(
                'Authentication telemetry write failed: ' .
                $error->getMessage()
            );
        }
    }

    /**
     * Allow application code and the additive migration to be deployed in
     * either order without breaking authentication.
     */
    private function hasDailyFailureColumn(): bool
    {
        if ($this->hasFailedLoginDateColumn !== null) {
            return $this->hasFailedLoginDateColumn;
        }

        try {
            $stmt = $this->db->query(
                "SHOW COLUMNS FROM users LIKE 'failed_login_date'"
            );
            $this->hasFailedLoginDateColumn = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $error) {
            $this->hasFailedLoginDateColumn = false;
        }

        return $this->hasFailedLoginDateColumn;
    }

    public function changePassword($userId, $data)
    {
        // Validate input
        $oldPassword = $data['old_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        if (!$oldPassword || !$newPassword) {
            return ['success' => false, 'error' => 'Old and new password required'];
        }

        // Fetch user
        $stmt = $this->db->prepare('SELECT id, password_hash AS password FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Old password is incorrect'];
        }
        // Update password
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?');
        $ok = $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId
        ]);
        return ['success' => $ok, 'data' => ['id' => $userId, 'changed' => $ok]];
    }
    public function resetPassword($data)
    {
        // Validate input
        $token = $data['token'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        if (!$token || !$newPassword) {
            return ['success' => false, 'error' => 'Token and new password required'];
        }

        // Lookup password reset request
        $stmt = $this->db->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()');
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reset) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }

        // Resolve the user from the reset email via persons
        $userStmt = $this->db->prepare('SELECT u.id FROM users u JOIN persons p ON p.id = u.person_id WHERE p.email = ? LIMIT 1');
        $userStmt->execute([$reset['email']]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }

        // Update user password
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW(), updated_at = NOW() WHERE id = ?');
        $ok = $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $user['id']
        ]);
        if ($ok) {
            // Mark token as used
            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $stmt->execute([$token]);
        }
        return ['success' => $ok, 'data' => ['reset' => $ok]];
    }

    /**
     * Generate JWT token for authenticated user
     */
    private function generateJWT($userData)
    {
        $issuedAt = time();
        $expire = $issuedAt + (3600); // 1 hour expiry

        $payload = array_merge(
            $userData,
            [
                'iat' => $issuedAt,
                'exp' => $expire,
                'iss' => JWT_ISSUER,
                'aud' => JWT_AUDIENCE
            ]
        );

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    /**
     * Check if a set of role IDs includes system admin
     */
    private function isSystemAdmin($roleIds)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$roleIds), static fn($id) => $id > 0)));
        if (!$ids) return false;
        $holders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT 1 FROM roles
             WHERE id IN ($holders) AND LOWER(TRIM(name))='system administrator'
             LIMIT 1"
        );
        $stmt->execute($ids);
        return (bool)$stmt->fetchColumn();
    }

    private function addToStaffTable($userId, $staffInfo, $roleIds = [])
    {
        try {
            // Check if staff record already exists (via the shared person)
            $checkStmt = $this->db->prepare('SELECT id FROM staff WHERE person_id = (SELECT person_id FROM users WHERE id = ?)');
            $checkStmt->execute([$userId]);
            if ($checkStmt->fetch()) {
                return true;
            }

            // Get user data (identity lives on the person record)
            $userStmt = $this->db->prepare('SELECT u.person_id, u.data_scope, u.is_test_user, p.first_name, p.last_name, p.email FROM users u JOIN persons p ON p.id = u.person_id WHERE u.id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['person_id'])) {
                return false;
            }

            if (empty($roleIds)) {
                throw new Exception('Missing required staff payroll field: assigned role');
            }

            // Employment assignment is school-owned. Never infer department or
            // staff classification from role IDs, which vary between databases.
            $departmentId = filter_var($staffInfo['department_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $staffTypeId = filter_var($staffInfo['staff_type_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $staffCategoryId = filter_var($staffInfo['staff_category_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $position = trim((string)($staffInfo['position'] ?? ''));
            $employmentDate = trim((string)($staffInfo['employment_date'] ?? ''));
            $contractType = strtolower(trim((string)($staffInfo['contract_type'] ?? '')));
            if (!$departmentId || !$staffTypeId || !$staffCategoryId || $position === '') {
                throw new Exception('Department, position, staff type, and staff category must be assigned by the school.');
            }
            $parsedEmploymentDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $employmentDate);
            if (!$parsedEmploymentDate || $parsedEmploymentDate->format('Y-m-d') !== $employmentDate) {
                throw new Exception('A valid school-assigned employment date is required.');
            }
            if (!in_array($contractType, ['permanent', 'contract', 'temporary'], true)) {
                throw new Exception('A valid school-assigned contract type is required.');
            }
            $departmentCheck = $this->db->prepare("SELECT 1 FROM departments WHERE id=? AND status='active' LIMIT 1");
            $departmentCheck->execute([$departmentId]);
            if (!$departmentCheck->fetchColumn()) throw new Exception('Choose an active department for the staff assignment.');
            $classificationCheck = $this->db->prepare(
                'SELECT 1 FROM staff_types st JOIN staff_categories sc ON sc.staff_type_id=st.id
                 WHERE st.id=? AND sc.id=? AND st.is_active=1 AND sc.is_active=1 LIMIT 1'
            );
            $classificationCheck->execute([$staffTypeId, $staffCategoryId]);
            if (!$classificationCheck->fetchColumn()) throw new Exception('Choose an active staff category belonging to the selected staff type.');

            // Normalize aliases used by UI/API clients before validation.
            if (empty($staffInfo['phone']) && !empty($staffInfo['phone_number'])) {
                $staffInfo['phone'] = $staffInfo['phone_number'];
            }
            if (empty($staffInfo['bank_account']) && !empty($staffInfo['bank_account_number'])) {
                $staffInfo['bank_account'] = $staffInfo['bank_account_number'];
            }

            // Generate staff number via the centralized StaffNumberService.
            $staffNoService = new \App\API\Services\StaffNumberService($this->db);
            $staffNo = $staffNoService->generate();

            // Update the shared person record with profile fields (phone/gender/dob/photo)
            $personSets = [];
            $personParams = [];
            if (!empty($staffInfo['first_name'])) {
                $personSets[] = 'first_name = ?';
                $personParams[] = $staffInfo['first_name'];
            }
            if (!empty($staffInfo['last_name'])) {
                $personSets[] = 'last_name = ?';
                $personParams[] = $staffInfo['last_name'];
            }
            if (!empty($staffInfo['phone'])) {
                $personSets[] = 'phone = ?';
                $personParams[] = $staffInfo['phone'];
            }
            if (!empty($staffInfo['gender'])) {
                $personSets[] = 'gender = ?';
                $personParams[] = $staffInfo['gender'];
            }
            if (!empty($staffInfo['date_of_birth'])) {
                $personSets[] = 'dob = ?';
                $personParams[] = $staffInfo['date_of_birth'];
            }
            if (!empty($staffInfo['national_id_no'])) {
                $personSets[] = 'national_id_no = ?';
                $personParams[] = $staffInfo['national_id_no'];
            }
            if (!empty($staffInfo['profile_pic_url'])) {
                $personSets[] = 'photo_url = ?';
                $personParams[] = $staffInfo['profile_pic_url'];
            }
            if (!empty($personSets)) {
                $personParams[] = $user['person_id'];
                $this->db->prepare('UPDATE persons SET ' . implode(', ', $personSets) . ' WHERE id = ?')->execute($personParams);
            }

            // Insert staff record (AUTO_INCREMENT id, identity via person_id)
            $sql = 'INSERT INTO staff (person_id, staff_type_id, staff_category_id, staff_no, position, contract_type, employment_date, status, data_scope, supervisor_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $user['person_id'],
                $staffInfo['staff_type_id'] ?? $staffTypeId,
                $staffInfo['staff_category_id'] ?? $staffCategoryId,
                $staffNo,
                $staffInfo['position'] ?? 'Staff',
                $staffInfo['contract_type'] ?? 'permanent',
                $staffInfo['employment_date'] ?? date('Y-m-d'),
                $staffInfo['status'] ?? 'active',
                (int) ($user['is_test_user'] ?? 0) === 1 ? 'test' : 'live',
                $staffInfo['supervisor_id'] ?? null
            ]);

            if (!$ok) {
                return false;
            }
            $staffId = (int) $this->db->lastInsertId();

            $positionName = trim((string)($staffInfo['position'] ?? ''));
            $positionId = \App\API\Services\StaffPositionCatalog::resolveId($this->db, $positionName);
            $this->db->prepare('INSERT INTO staff_employment_profiles (staff_id, department_id, position_id, position, employment_date, contract_type, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$staffId, $departmentId ?: null, $positionId, $positionName, $staffInfo['employment_date'] ?? date('Y-m-d'), $staffInfo['contract_type'] ?? 'permanent', $staffInfo['status'] ?? 'active']);

            // Department assignment (join table)
            if (!empty($departmentId)) {
                $deptCheck = $this->db->prepare('SELECT id FROM staff_department_assignments WHERE staff_id = ? AND department_id = ?');
                $deptCheck->execute([$staffId, $departmentId]);
                if (!$deptCheck->fetch()) {
                    $this->db->prepare('INSERT INTO staff_department_assignments (staff_id, department_id, role, effective_from) VALUES (?, ?, NULL, ?)')
                        ->execute([$staffId, $departmentId, $staffInfo['employment_date'] ?? date('Y-m-d')]);
                }
            }

            // Payroll starts as a draft. Missing employee identifiers, bank
            // details, or salary must never make an account creation fail or
            // create an apparently payroll-ready record.
            $payrollComplete = !empty($staffInfo['bank_name'])
                && !empty($staffInfo['bank_account'])
                && !empty($staffInfo['kra_pin'])
                && !empty($staffInfo['nssf_no'])
                && !empty($staffInfo['nhif_no']);
            $this->db->prepare('INSERT INTO staff_payroll_profiles (staff_id, bank_name, bank_account, kra_pin, nssf_no, nhif_no, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    bank_name = VALUES(bank_name),
                                    bank_account = VALUES(bank_account),
                                    kra_pin = VALUES(kra_pin),
                                    nssf_no = VALUES(nssf_no),
                                    nhif_no = VALUES(nhif_no),
                                    status = VALUES(status)')
                ->execute([
                    $staffId,
                    $staffInfo['bank_name'] ?? null,
                    $staffInfo['bank_account'] ?? null,
                    $staffInfo['kra_pin'] ?? null,
                    $staffInfo['nssf_no'] ?? null,
                    $staffInfo['nhif_no'] ?? null,
                    $payrollComplete ? 'active' : 'draft'
                ]);

            return $staffId;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Error adding staff record: " . $e->getMessage());
            return false;
        }
    }

}
