<?php
namespace App\API\Modules\users;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Includes\AuditLogger;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\AuthSessionService;
use App\API\Services\TestAccountAccessService;
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
        $this->communicationsApi = new CommunicationsAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
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
                 SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.id LIMIT 1
             )
             WHERE u.id = ?"
        );
        $stmt->execute([TestAccountAccessService::environment(), $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return ['success' => true, 'data' => $user];
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
                    SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.id LIMIT 1
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
        return ['success' => true, 'data' => $users];
    }
    public function create($data)
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
        $dataScope = $isTestAccount ? 'test' : 'live';
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
        if (in_array(4, array_map('intval', $roleIds), true)
            && (!isset($data['staff_info']) || !is_array($data['staff_info']))) {
            throw new Exception('School Administrator accounts must be created through the first-administrator or staff-onboarding workflow.');
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
            $personId = $this->nextId('persons');
            $personStmt = $this->db->prepare(
                'INSERT INTO persons (id, first_name, middle_name, last_name, email, data_scope)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $personOk = $personStmt->execute([
                $personId,
                $validatedData['first_name'] ?? '',
                $data['middle_name'] ?? null,
                $validatedData['last_name'] ?? '',
                $validatedData['email'] ?? null,
                $dataScope,
            ]);
            if (!$personOk) {
                throw new Exception('Person creation failed');
            }

            // STEP 2: Create user record linked to the person (roles via user_roles)
            $userId = $this->nextId('users');
            $sql = 'INSERT INTO users (id, username, password_hash, person_id, status, last_login, password_changed_at, force_password_change, is_test_user, account_type, data_scope, two_factor_enabled, two_factor_method, two_factor_verified_at, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'email\', NULL, NOW(), NOW())';
            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $userId,
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
                    $roleResult = $this->userRoleManager->assignRole($userId, $additionalRoleId);
                    if ($roleResult['success']) {
                        $rolesAssigned++;
                    } else {
                        throw new Exception('Failed to assign additional role ' . $additionalRoleId);
                    }
                }
            }

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

                // Required staff fields for payroll/legal reasons
                $requiredStaffFields = ['department_id', 'position', 'employment_date', 'date_of_birth', 'nssf_no', 'kra_pin', 'nhif_no', 'bank_account', 'salary'];
                $missingStaff = [];
                foreach ($requiredStaffFields as $f) {
                    if (empty($staffInfo[$f])) {
                        $missingStaff[] = $f;
                    }
                }
                if (!empty($missingStaff)) {
                    throw new Exception('Missing required staff fields: ' . implode(', ', $missingStaff));
                }

                // TSC number required for teacher-like roles
                $primaryRoleId = $roleIds[0] ?? null;
                if ($primaryRoleId) {
                    $cat = $this->getStaffCategoryIdForRole($primaryRoleId);
                    // If mapping indicates teacher types (cat values for teachers are 4,6,8 etc.) require tsc_no
                    $teacherCategories = [4, 6, 8];
                    if (in_array($cat, $teacherCategories) && empty($staffInfo['tsc_no'])) {
                        throw new Exception('tsc_no is required for Teacher role');
                    }
                }

                // Pass roleIds to allow intelligent department/type/category mapping
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
        try {
            $this->db->beginTransaction();
            $staffId = $this->addToStaffTable($userId, $staffInfo, $roleIds);
            $this->db->commit();
            if ($staffId) {
                return ['success' => true, 'staff_id' => $staffId];
            }
            return ['success' => false, 'error' => 'Failed to add staff record'];
        } catch (Exception $e) {
            $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UsersAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'error' => 'An internal error occurred.'];
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
            $personStmt = $this->db->prepare('INSERT INTO persons (id, first_name, middle_name, last_name, email, data_scope) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt = $this->db->prepare('INSERT INTO users (id, username, password_hash, person_id, status, last_login, password_changed_at, force_password_change, is_test_user, account_type, data_scope, two_factor_enabled, two_factor_method, two_factor_verified_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, \'email\', NULL, NOW(), NOW())');

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
                    $roleIds = [1];
                }

                try {
                    $this->db->exec('SAVEPOINT bulk_user_row');
                    $accountType = strtolower((string) ($userData['account_type']
                        ?? (TestAccountAccessService::environment() === 'development' ? 'test' : 'real')));
                    if (!in_array($accountType, ['real', 'test', 'service'], true)) {
                        throw new Exception('Invalid account type');
                    }
                    $isTestAccount = $accountType === 'test';
                    $dataScope = $isTestAccount ? 'test' : 'live';
                    if ($isTestAccount && TestAccountAccessService::environment() !== 'development'
                        && (empty($userData['test_access_purpose']) || empty($userData['test_access_expires_at']))) {
                        throw new Exception('Production and staging test accounts require a purpose and expiry date');
                    }

                    // Create person record
                    $personId = $this->nextId('persons');
                    $personOk = $personStmt->execute([
                        $personId,
                        $userData['first_name'] ?? '',
                        $userData['middle_name'] ?? null,
                        $userData['last_name'] ?? '',
                        $userData['email'],
                        $dataScope,
                    ]);
                    if (!$personOk) {
                        throw new Exception('Person creation failed');
                    }

                    // Create user
                    $userId = $this->nextId('users');
                    $ok = $stmt->execute([
                        $userId,
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

                    if (!$ok) {
                        throw new Exception('User creation failed');
                    }
                    $this->db->prepare("INSERT INTO user_two_factor_methods (user_id, method, label, is_primary, is_enabled, verified_at) VALUES (?, 'email', 'Account email', 1, 1, NULL) ON DUPLICATE KEY UPDATE is_enabled=1, is_primary=1")
                        ->execute([$userId]);

                    $rolesAssigned = 0;

                    // Assign roles (auto-copies permissions)
                    foreach ($roleIds as $roleId) {
                        $roleResult = $this->userRoleManager->assignRole($userId, $roleId);
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

                    // Add to staff (unless system admin)
                    $isSystemAdmin = $this->isSystemAdmin($roleIds);
                    $staffAdded = false;
                    if (!$isSystemAdmin) {
                        // Use provided staff_info or create default from user data
                        $staffInfo = isset($userData['staff_info']) ? $userData['staff_info'] : [
                            'first_name' => $userData['first_name'],
                            'last_name' => $userData['last_name'],
                            'position' => $userData['position'] ?? 'Staff',
                            'employment_date' => date('Y-m-d'),
                            'contract_type' => $userData['contract_type'] ?? 'permanent'
                        ];
                        // Pass roleIds to allow intelligent department/type/category mapping
                        $staffAdded = $this->addToStaffTable($userId, $staffInfo, $roleIds);
                    }

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

        if (isset($data['account_type']) && $data['account_type'] !== $oldData['account_type']) {
            return ['success' => false, 'error' => 'Account type is immutable; create a clean account instead of converting test and real identities'];
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

        if (empty($userFields) && empty($personFields) && empty($validatedData['role_ids']) && !$hasTestAccessChange) {
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

            if (!empty($validatedData['role_ids'])) {
                $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$id]);
                foreach ($validatedData['role_ids'] as $rid) {
                    $this->userRoleManager->assignRole($id, (int) $rid);
                }
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

        // Lookup user by username or email
        $failureDayColumn = $this->hasDailyFailureColumn()
            ? 'u.failed_login_date'
            : 'DATE(u.updated_at)';
        $stmt = $this->db->prepare(
            'SELECT
                u.id,
                u.username,
                p.email,
                u.password_hash AS password,
                p.first_name,
                p.last_name,
                (SELECT ur.role_id FROM user_roles ur WHERE ur.user_id = u.id ORDER BY ur.id LIMIT 1) AS role_id,
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
             WHERE u.username = ? OR p.email = ?
             LIMIT 1'
        );
        $stmt->execute([$username, $username]);
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
        if (empty($roleIds)) {
            return false;
        }

        // System Administrator = role_id 2 (the system creator, not a school employee)
        // Do NOT add to staff table
        return in_array(2, $roleIds);
    }

    /**
     * Add user to staff table for non-admin users
     * System Administrator (role_id=2) excluded from staff table
     */
    /**
     * Intelligent role-to-department mapping based on role name
     */
    private function mapRoleToDepartment($roleId)
    {
        $roleMapping = [
            // Administration roles
            3 => 4,  // Director → Administration (4)
            4 => 4,  // School Administrator → Administration (4)
            5 => 4,  // Headteacher → Administration (4)
            6 => 4,  // Deputy Head - Academic → Administration (4)
            63 => 4,  // Deputy Head - Discipline → Administration (4)
            10 => 4,  // Accountant → Administration (4)
            19 => 4,  // Registrar → Administration (4)
            20 => 4,  // Secretary → Administration (4)

            // Academic roles
            7 => 1,  // Class Teacher → Academics (1)
            8 => 1,  // Subject Teacher → Academics (1)
            9 => 1,  // Intern/Student Teacher → Academics (1)
            17 => 1,  // Head of Department → Academics (1)

            // Support roles
            23 => 2,  // Driver → Transport (2)
            16 => 3,  // Cateress → Food and Nutrition (3)
            32 => 3,  // Kitchen Staff → Food and Nutrition (3)
            18 => 4,  // Boarding Master → Administration (4)
            33 => 4,  // Security Staff → Administration (4)
            34 => 4,  // Janitor → Administration (4)
            14 => 4,  // Uniform Store Manager → Administration (4)
            24 => 6,  // Chaplain → Student & Staff Welfare (6)
            21 => 7,  // Talent Development → Talent Development (7)
        ];

        return $roleMapping[$roleId] ?? 1; // Default to Academics if not mapped
    }

    /**
     * Intelligent role-to-staff-type mapping
     */
    private function mapRoleToStaffType($roleId)
    {
        // Teaching staff
        $teachingRoles = [7, 8, 9]; // Class Teacher, Subject Teacher, Intern
        if (in_array($roleId, $teachingRoles)) {
            return 1; // Teaching Staff
        }

        // Administrative staff
        $adminRoles = [3, 4, 5, 6, 63, 10, 19, 20, 18, 33, 34, 14];
        if (in_array($roleId, $adminRoles)) {
            return 3; // Administration
        }

        // Non-teaching staff (drivers, cooks, cleaners, etc.)
        return 2; // Non-Teaching Staff (default)
    }

    /**
     * Get staff category ID based on role
     */
    private function getStaffCategoryIdForRole($roleId)
    {
        // Category mapping from staff_categories table
        $categoryMapping = [
            3 => 14,  // Director → Director (14)
            5 => 15,  // Headteacher → Headteacher (15)
            6 => 16,  // Deputy Head - Academic → Deputy Headteacher (16)
            63 => 16,  // Deputy Head - Discipline → Deputy Headteacher (16)
            17 => 17,  // Head of Department → Head of Department (17)
            4 => 20,  // School Administrator → Secretary (20)
            10 => 18,  // Accountant → Accountant (18)
            19 => 19,  // Registrar → Registrar (19)
            20 => 20,  // Secretary → Secretary (20)
            24 => 21,  // Chaplain → Chaplain (21)

            7 => 4,   // Class Teacher → Upper Primary Teacher (4) - default for teachers
            8 => 6,   // Subject Teacher → Subject Specialist (6)
            9 => 8,   // Intern/Student Teacher → Intern Teacher (8)

            23 => 9,   // Driver → Driver (9)
            16 => 13,  // Cateress → Cook (13)
            32 => 13,  // Kitchen Staff → Cook (13)
            33 => 12,  // Security Staff → Security Guard (12)
            34 => 10,  // Janitor → Cleaner (10)
            14 => 20,  // Uniform Store Manager → Secretary (20)
            21 => 7,   // Talent Development → Activities Coordinator (7)
        ];

        return $categoryMapping[$roleId] ?? null; // Return null if no specific mapping
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
            $userStmt = $this->db->prepare('SELECT u.person_id, u.data_scope, p.first_name, p.last_name, p.email FROM users u JOIN persons p ON p.id = u.person_id WHERE u.id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['person_id'])) {
                return false;
            }

            // Get primary role ID (first role assigned)
            $primaryRoleId = $roleIds[0] ?? null;

            if (empty($roleIds)) {
                throw new Exception('Missing required staff payroll field: assigned role');
            }

            // Determine department intelligently or use provided value
            $departmentId = $staffInfo['department_id'] ?? ($primaryRoleId ? $this->mapRoleToDepartment($primaryRoleId) : null);
            if (empty($departmentId)) {
                throw new Exception('Missing required staff payroll field: department_id');
            }

            // Determine staff type intelligently or use provided value
            $staffTypeId = $staffInfo['staff_type_id'] ?? ($primaryRoleId ? $this->mapRoleToStaffType($primaryRoleId) : 2);

            // Determine staff category intelligently or use provided value
            $staffCategoryId = $staffInfo['staff_category_id'] ?? ($primaryRoleId ? $this->getStaffCategoryIdForRole($primaryRoleId) : null);

            // Normalize aliases used by UI/API clients before validation.
            if (empty($staffInfo['phone']) && !empty($staffInfo['phone_number'])) {
                $staffInfo['phone'] = $staffInfo['phone_number'];
            }
            if (empty($staffInfo['bank_account']) && !empty($staffInfo['bank_account_number'])) {
                $staffInfo['bank_account'] = $staffInfo['bank_account_number'];
            }

            // Enforce mandatory payroll fields for staff.
            // A staff member cannot be payroll-eligible without statutory, contact, payment, department, role and salary details.
            $requiredPayroll = [
                'nssf_no',
                'kra_pin',
                'nhif_no',
                'phone',
                'bank_name',
                'bank_account',
                'salary'
            ];
            foreach ($requiredPayroll as $pf) {
                if (empty($staffInfo[$pf])) {
                    throw new Exception("Missing required staff payroll field: $pf");
                }
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

            // Insert staff record (id is manual, identity via person_id)
            $staffId = $this->nextId('staff');
            $sql = 'INSERT INTO staff (id, person_id, staff_type_id, staff_category_id, staff_no, position, contract_type, employment_date, status, data_scope, supervisor_id, salary, bank_name, bank_account, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $staffId,
                $user['person_id'],
                $staffInfo['staff_type_id'] ?? $staffTypeId,
                $staffInfo['staff_category_id'] ?? $staffCategoryId,
                $staffNo,
                $staffInfo['position'] ?? 'Staff',
                $staffInfo['contract_type'] ?? 'permanent',
                $staffInfo['employment_date'] ?? date('Y-m-d'),
                $staffInfo['status'] ?? 'active',
                ($user['data_scope'] ?? 'live') === 'test' ? 'test' : 'live',
                $staffInfo['supervisor_id'] ?? null,
                $staffInfo['salary'] ?? null,
                $staffInfo['bank_name'] ?? null,
                $staffInfo['bank_account'] ?? null
            ]);

            if (!$ok) {
                return false;
            }

            // Department assignment (join table)
            if (!empty($departmentId)) {
                $deptCheck = $this->db->prepare('SELECT id FROM staff_department_assignments WHERE staff_id = ? AND department_id = ?');
                $deptCheck->execute([$staffId, $departmentId]);
                if (!$deptCheck->fetch()) {
                    $this->db->prepare('INSERT INTO staff_department_assignments (id, staff_id, department_id, role, effective_from) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$this->nextId('staff_department_assignments'), $staffId, $departmentId, $staffInfo['position'] ?? null, $staffInfo['employment_date'] ?? date('Y-m-d')]);
                }
            }

            // Payroll profile (statutory + payment details)
            $this->db->prepare('INSERT INTO staff_payroll_profiles (staff_id, basic_salary, bank_name, bank_account, kra_pin, nssf_no, nhif_no, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE
                                    basic_salary = VALUES(basic_salary),
                                    bank_name = VALUES(bank_name),
                                    bank_account = VALUES(bank_account),
                                    kra_pin = VALUES(kra_pin),
                                    nssf_no = VALUES(nssf_no),
                                    nhif_no = VALUES(nhif_no),
                                    status = VALUES(status)')
                ->execute([
                    $staffId,
                    $staffInfo['salary'] ?? 0,
                    $staffInfo['bank_name'] ?? null,
                    $staffInfo['bank_account'] ?? null,
                    $staffInfo['kra_pin'] ?? null,
                    $staffInfo['nssf_no'] ?? null,
                    $staffInfo['nhif_no'] ?? null,
                    'active'
                ]);

            return $staffId;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Error adding staff record: " . $e->getMessage());
            return false;
        }
    }

    private function nextId($table)
    {
        return (int) $this->db->query("SELECT COALESCE(MAX(id), 0) + 1 FROM `$table`")->fetchColumn();
    }

}
