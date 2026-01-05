<?php
namespace App\API\Modules\users;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Includes\AuditLogger;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\MenuBuilderService;
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
        // Fetch user by ID
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return ['success' => true, 'data' => $user];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    }
    public function list($data = [])
    {
        // List all users (optionally filter by status, role, etc.)
        $sql = 'SELECT * FROM users';
        $params = [];
        if (isset($data['status'])) {
            $sql .= ' WHERE status = ?';
            $params[] = $data['status'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $users];
    }
    public function create($data)
    {
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
            'date_of_birth',
            'first_name',
            'last_name'
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

        // Start transaction for atomicity
        $this->db->beginTransaction();

        try {
            // STEP 1: Create user record with PRIMARY role only
            // The primary role goes in users.role_id
            $primaryRoleId = $roleIds[0];

            // Allow optional user fields to be set at creation time
            $sql = 'INSERT INTO users (username, email, password, first_name, last_name, role_id, status, last_login, password_changed_at, failed_login_attempts, account_locked_until, password_expires_at, force_password_change, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $validatedData['username'],
                $validatedData['email'],
                password_hash($validatedData['password'], PASSWORD_DEFAULT),
                $validatedData['first_name'],
                $validatedData['last_name'],
                $primaryRoleId,
                $validatedData['status'] ?? 'active',
                $data['last_login'] ?? null,
                $data['password_changed_at'] ?? null,
                $data['failed_login_attempts'] ?? 0,
                $data['account_locked_until'] ?? null,
                $data['password_expires_at'] ?? null,
                $data['force_password_change'] ?? 0
            ]);

            if (!$ok) {
                throw new Exception('User creation failed');
            }

            $userId = $this->db->lastInsertId();
            error_log("User creation: inserted id=$userId");

            // STEP 2: Assign PRIMARY role and copy its permissions
            // Only the primary role is assigned to user_roles (for consistency)
            $rolesAssigned = 0;
            $roleResult = $this->userRoleManager->assignRole($userId, $primaryRoleId);
            error_log("User creation: assignRole result=" . json_encode($roleResult));
            if ($roleResult['success']) {
                $rolesAssigned++;
            } else {
                throw new Exception('Failed to assign primary role ' . $primaryRoleId);
            }

            // STEP 2b: If there are ADDITIONAL roles beyond the primary, assign them too
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

            // STEP 3: Override permissions if explicitly provided
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                foreach ($data['permissions'] as $perm) {
                    $permData = is_array($perm) ? $perm : ['permission_code' => $perm];
                    $this->userPermissionManager->assignPermission($userId, $permData);
                }
            }

            // STEP 4: Add to staff table (only if front-end provided staff_info). Do NOT auto-create staff without explicit data.
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

            // STEP 5: Audit log
            $currentUserId = $this->getCurrentUserId();
            $this->auditLogger->logUserCreate($currentUserId, $userId, $validatedData);

            $this->db->commit();

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

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("User creation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
        error_log($e->getTraceAsString());

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
            $stmt = $this->db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role_id, status, last_login, password_changed_at, failed_login_attempts, account_locked_until, password_expires_at, force_password_change, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');

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
                    'date_of_birth',
                    'first_name',
                    'last_name'
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
                if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'Missing required fields: username, email, password'
                    ];
                    continue;
                }

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
                    // Create user
                    $ok = $stmt->execute([
                        $userData['username'],
                        $userData['email'],
                        password_hash($userData['password'], PASSWORD_DEFAULT),
                        $userData['first_name'] ?? '',
                        $userData['last_name'] ?? '',
                        $roleIds[0] ?? 1,
                        $userData['status'] ?? 'active',
                        $userData['last_login'] ?? null,
                        $userData['password_changed_at'] ?? null,
                        $userData['failed_login_attempts'] ?? 0,
                        $userData['account_locked_until'] ?? null,
                        $userData['password_expires_at'] ?? null,
                        $userData['force_password_change'] ?? 0
                    ]);

                    if (!$ok) {
                        throw new Exception('User creation failed');
                    }

                    $userId = $this->db->lastInsertId();
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

                    $created[] = [
                        'index' => $index,
                        'user_id' => $userId,
                        'username' => $userData['username'],
                        'email' => $userData['email'],
                        'roles_assigned' => $rolesAssigned,
                        'staff_added' => $staffAdded
                    ];

                } catch (Exception $e) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => $e->getMessage()
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
            error_log("Bulk user creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Bulk creation failed: ' . $e->getMessage()];
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

        // Build update query with validated data
        $fields = [];
        $params = [];

        foreach (['username', 'email', 'first_name', 'last_name', 'status', 'role_id'] as $field) {
            if (isset($validatedData[$field])) {
                $fields[] = "$field = ?";
                $params[] = $validatedData[$field];
            }
        }

        if (isset($validatedData['password'])) {
            $fields[] = 'password = ?';
            $params[] = password_hash($validatedData['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }
        
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';

        try {
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute($params);

            if ($ok) {
                // Audit log
                $currentUserId = $this->getCurrentUserId();
                $this->auditLogger->logUserUpdate($currentUserId, $id, $oldData, $validatedData);

                return ['success' => true, 'data' => $this->get($id)['data']];
            } else {
                return ['success' => false, 'error' => 'User update failed'];
            }
        } catch (Exception $e) {
            error_log("User update error: " . $e->getMessage());
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
            error_log("User deletion error: " . $e->getMessage());
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

        // Use MenuBuilderService to build sidebar (ensures consistency with login response)
        $items = [];
        if (!empty($roleIds)) {
            try {
                $menuBuilder = MenuBuilderService::getInstance();

                // If single role, use buildSidebarForUser; if multiple, use buildSidebarForMultipleRoles
                if (count($roleIds) === 1) {
                    $items = $menuBuilder->buildSidebarForUser($userId, $roleIds[0]);
                } else {
                    $items = $menuBuilder->buildSidebarForMultipleRoles($userId, $roleIds);
                }
            } catch (\Exception $e) {
                error_log("UsersAPI.getSidebarItems() MenuBuilderService error: " . $e->getMessage());
                $items = [];
            }
        }

        return ['success' => true, 'data' => $items];
    }
    public function login($data)
    {
        // Validate input
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        if (!$username || !$password) {
            return ['success' => false, 'error' => 'Username and password required'];
        }

        // Lookup user by username or email
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or Email'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Incorrectpassword'];
        }

        // Optionally: check user status
        if (isset($user['status']) && $user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Account is not active'];
        }

        // Get roles and permissions
        // Get roles and permissions
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

        // Generate JWT token - ONLY authentication data (no permissions!)
        $token = $this->generateJWT([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'roles' => $roles['data'] ?? []
            // NO permissions in token!
        ]);

        // Return user info with token
        // Permissions are returned in the response body (not in token)
        // Return user info with token
        // Permissions are returned in the response body (not in token)
        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role_id' => $user['role_id'],
                    'status' => $user['status'] ?? null,
                    'roles' => $roles['data'] ?? [],
                    'permissions' => $permissionCodes  // In response body, NOT in token
                ]
            ]
        ];
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
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
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
        $stmt = $this->db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
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

        // Update user password
        $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $ok = $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $reset['user_id']
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
                'iss' => 'kingsway.ac.ke'
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
            14 => 4,  // Inventory Manager → Administration (4)
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
            14 => 20,  // Inventory Manager → Secretary (20)
            21 => 7,   // Talent Development → Activities Coordinator (7)
        ];

        return $categoryMapping[$roleId] ?? null; // Return null if no specific mapping
    }

    private function addToStaffTable($userId, $staffInfo, $roleIds = [])
    {
        try {
            // Check if staff record already exists
            $checkStmt = $this->db->prepare('SELECT id FROM staff WHERE user_id = ?');
            $checkStmt->execute([$userId]);
            if ($checkStmt->fetch()) {
                return true;
            }

            // Get user data for basic fields
            $userStmt = $this->db->prepare('SELECT first_name, last_name, email FROM users WHERE id = ?');
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Get primary role ID (first role assigned)
            $primaryRoleId = $roleIds[0] ?? null;

            // Determine department intelligently or use provided value
            $departmentId = $staffInfo['department_id'] ?? ($primaryRoleId ? $this->mapRoleToDepartment($primaryRoleId) : 1);

            // Determine staff type intelligently or use provided value
            $staffTypeId = $staffInfo['staff_type_id'] ?? ($primaryRoleId ? $this->mapRoleToStaffType($primaryRoleId) : 2);

            // Determine staff category intelligently or use provided value
            $staffCategoryId = $staffInfo['staff_category_id'] ?? ($primaryRoleId ? $this->getStaffCategoryIdForRole($primaryRoleId) : null);

            // Enforce mandatory payroll fields for staff (NSSF/KRA/NHIF/bank/salary)
            $requiredPayroll = ['nssf_no', 'kra_pin', 'nhif_no', 'bank_account', 'salary'];
            foreach ($requiredPayroll as $pf) {
                if (empty($staffInfo[$pf])) {
                    throw new Exception("Missing required staff payroll field: $pf");
                }
            }

            // Generate next KWPS staff number (KWPS001, KWPS002, etc.)
            $maxStmt = $this->db->prepare('SELECT MAX(CAST(SUBSTRING(staff_no, 5) AS UNSIGNED)) as max_num FROM staff WHERE staff_no LIKE "KWPS%"');
            $maxStmt->execute();
            $result = $maxStmt->fetch(PDO::FETCH_ASSOC);
            $nextNum = (int) ($result['max_num'] ?? 0) + 1;
            $staffNo = 'KWPS' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

            // Insert staff record with all determined fields
            $sql = 'INSERT INTO staff (user_id, staff_type_id, staff_category_id, staff_no, first_name, last_name, department_id, supervisor_id, position, employment_date, contract_type, nssf_no, kra_pin, nhif_no, bank_account, salary, gender, marital_status, tsc_no, address, profile_pic_url, documents_folder, status, date_of_birth, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';

            $stmt = $this->db->prepare($sql);

            $ok = $stmt->execute([
                $userId,
                $staffInfo['staff_type_id'] ?? $staffTypeId,
                $staffInfo['staff_category_id'] ?? $staffCategoryId,
                $staffNo,
                $staffInfo['first_name'] ?? $user['first_name'],
                $staffInfo['last_name'] ?? $user['last_name'],
                $departmentId,
                $staffInfo['supervisor_id'] ?? null,
                $staffInfo['position'] ?? 'Staff',
                $staffInfo['employment_date'] ?? date('Y-m-d'),
                $staffInfo['contract_type'] ?? 'permanent',
                $staffInfo['nssf_no'] ?? null,
                $staffInfo['kra_pin'] ?? null,
                $staffInfo['nhif_no'] ?? null,
                $staffInfo['bank_account'] ?? null,
                $staffInfo['salary'] ?? null,
                $staffInfo['gender'] ?? $staffInfo['gender'] ?? null,
                $staffInfo['marital_status'] ?? null,
                $staffInfo['tsc_no'] ?? null,
                $staffInfo['address'] ?? null,
                $staffInfo['profile_pic_url'] ?? null,
                $staffInfo['documents_folder'] ?? null,
                $staffInfo['status'] ?? 'active',
                $staffInfo['date_of_birth'] ?? null
            ]);

            if ($ok) {
                $staffId = $this->db->lastInsertId();
                return $staffId;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error adding staff record: " . $e->getMessage());
            return false;
        }
    }
    
}