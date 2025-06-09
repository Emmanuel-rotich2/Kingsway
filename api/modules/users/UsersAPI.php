<?php
namespace App\API\Modules\users;

use App\API\Includes\BaseAPI;
use App\API\Modules\communications\CommunicationsAPI;
use PDO;
use Exception;

class UsersAPI extends BaseAPI {
    private $communicationsApi;

    public function __construct() {
        parent::__construct('users');
        $this->communicationsApi = new CommunicationsAPI();
    }

    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE u.username LIKE ? OR u.email LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(DISTINCT u.id) 
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id OR u.role_id = r.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results with all roles
            $sql = "
                SELECT 
                    u.*,
                    GROUP_CONCAT(DISTINCT r.name) as roles,
                    GROUP_CONCAT(DISTINCT r.permissions) as role_permissions
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id OR u.role_id = r.id
                $where
                GROUP BY u.id
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Process roles and permissions
            foreach ($users as &$user) {
                unset($user['password']); // Remove sensitive data
                
                // Parse roles
                $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
                
                // Parse and merge permissions
                $permissions = [];
                if ($user['role_permissions']) {
                    $rolePermissions = explode(',', $user['role_permissions']);
                    foreach ($rolePermissions as $permSet) {
                        $perms = json_decode($permSet, true);
                        if (is_array($perms)) {
                            $permissions = array_merge($permissions, array_keys($perms));
                        }
                    }
                    $user['permissions'] = array_values(array_unique($permissions));
                } else {
                    $user['permissions'] = [];
                }
                unset($user['role_permissions']);
            }

            return $this->response([
                'status' => 'success',
                'data' => [
                    'users' => $users,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function get($id) {
        try {
            $sql = "
                SELECT 
                    u.*,
                    GROUP_CONCAT(DISTINCT r.name) as roles,
                    GROUP_CONCAT(DISTINCT r.permissions) as role_permissions
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id OR u.role_id = r.id
                WHERE u.id = ?
                GROUP BY u.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            // Remove sensitive data
            unset($user['password']);

            // Parse roles and permissions
            $user['roles'] = $user['roles'] ? explode(',', $user['roles']) : [];
            
            // Parse and merge permissions
            $permissions = [];
            if ($user['role_permissions']) {
                $rolePermissions = explode(',', $user['role_permissions']);
                foreach ($rolePermissions as $permSet) {
                    $perms = json_decode($permSet, true);
                    if (is_array($perms)) {
                        $permissions = array_merge($permissions, array_keys($perms));
                    }
                }
                $user['permissions'] = array_values(array_unique($permissions));
            } else {
                $user['permissions'] = [];
            }
            unset($user['role_permissions']);

            return $this->response(['status' => 'success', 'data' => $user]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function create($data) {
        try {
            $required = ['first_name', 'last_name', 'email', 'password', 'role', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate status
            $validStatuses = ['active', 'inactive', 'pending'];
            if (!in_array($data['status'], $validStatuses)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)
                ], 400);
            }

            // Convert role to array if string provided
            $roles = is_array($data['role']) ? $data['role'] : [$data['role']];
            
            // Find role_ids from role names (case-insensitive)
            $roleIds = [];
            foreach ($roles as $role) {
                $stmt = $this->db->prepare("SELECT id FROM roles WHERE LOWER(name) = LOWER(:role) LIMIT 1");
                $stmt->execute([':role' => $role]);
                $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$roleRow) {
                    return $this->response([
                        'status' => 'error',
                        'message' => "Invalid role name: $role"
                    ], 400);
                }
                $roleIds[] = $roleRow['id'];
            }

            if (empty($roleIds)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'At least one valid role is required'
                ], 400);
            }

            // Generate username from first and last name
            $data['first_name'] = trim($data['first_name']);
            $data['last_name'] = trim($data['last_name']);
            $username = $this->generateUsername($data);
            if (!$username) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Unable to generate unique username.'
                ], 400);
            }

            // Check if username or email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $data['email']]);
            if ($stmt->fetch()) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Username or email already exists'
                ], 400);
            }

            $this->db->beginTransaction();

            try {
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $now = date('Y-m-d H:i:s');
                
                // Insert into users table with primary role
                $sql = "
                    INSERT INTO users (
                        username, 
                        email, 
                        password, 
                        role_id,
                        status, 
                        created_at, 
                        updated_at
                    ) VALUES (
                        :username,
                        :email,
                        :password,
                        :role_id,
                        :status,
                        :created_at,
                        :updated_at
                    )
                ";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':username' => $username,
                    ':email' => $data['email'],
                    ':password' => $hashedPassword,
                    ':role_id' => $roleIds[0], // Primary role
                    ':status' => $data['status'],
                    ':created_at' => $now,
                    ':updated_at' => $now
                ]);
                
                $userId = $this->db->lastInsertId();

                // Insert primary role into user_roles table
                $sql = "INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $roleIds[0]]);

                // Insert additional roles into user_roles table
                if (count($roleIds) > 1) {
                    $values = array_map(function($roleId) use ($userId) {
                        return "($userId, $roleId)";
                    }, array_slice($roleIds, 1));
                    
                    $sql = "INSERT INTO user_roles (user_id, role_id) VALUES " . implode(',', $values);
                    $this->db->exec($sql);
                }

                // If role is staff, create staff record but using the staff module
                // Send welcome email
                $welcomeTemplate = "Welcome to Kingsway Preparatory School, {{first_name}}!\n\n"
                    . "Your account has been created successfully.\n\n"
                    . "Username: {{username}}\n"
                    . "Password: {{password}}\n\n"
                    . "Please use the temporary password provided to log in and change your password.";

                $this->communicationsApi->sendNotification([
                    'email' => $data['email'],
                    'subject' => 'Welcome to Kingsway Preparatory School',
                    'message' => $this->parseTemplate($welcomeTemplate, [
                        'first_name' => $data['first_name'],
                        'username' => $username
                    ]),
                    'send_email' => true,
                    'send_sms' => false
                ]);

                $this->db->commit();

                return $this->response([
                    'status' => 'success',
                    'message' => 'User created successfully',
                    'data' => [
                        'id' => $userId,
                        'username' => $username,
                        'role' => $data['role']
                    ]
                ], 201);
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Username generation helper
    private function generateUsername($data) {
        $base = '';
        if (!empty($data['first_name']) && !empty($data['last_name'])) {
            $base = strtolower($data['first_name'] . '.' . $data['last_name']);
        } elseif (!empty($data['last_name']) && !empty($data['first_name'])) {
            $base = strtolower($data['last_name'] . '.' . $data['first_name']);
        } elseif (!empty($data['first_name'])) {
            $base = strtolower($data['first_name']);
        } elseif (!empty($data['last_name'])) {
            $base = strtolower($data['last_name']);
        } else {
            return null;
        }
        $domain = '@kingsway.ac.ke';
        $username = $base . $domain;
        $i = 1;
        while ($this->usernameExists($username)) {
            $username = $base . $i . $domain;
            $i++;
            if ($i > 1000) return null; // avoid infinite loop
        }
        return $username;
    }
    private function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch() ? true : false;
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            // Check if username or email already exists for other users
            if (isset($data['username']) || isset($data['email'])) {
                $sql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['username'] ?? '',
                    $data['email'] ?? '',
                    $id
                ]);
                if ($stmt->fetch()) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Username or email already exists'
                    ], 400);
                }
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'username', 'email', 'role_id', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            // Handle password update separately
            if (!empty($data['password'])) {
                $updates[] = "password = ?";
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'User updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getRoles() {
        try {
            $sql = "SELECT * FROM roles ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $roles]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updatePermissions($id, $data) {
        try {
            if (empty($data['permissions'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Permissions are required'
                ], 400);
            }

            $sql = "UPDATE roles SET permissions = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([json_encode($data['permissions']), $id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Role not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Permissions updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function changePassword($id, $data) {
        try {
            $required = ['current_password', 'new_password'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Verify current password
            $sql = "SELECT password FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['current_password'], $user['password'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            // Update password
            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                password_hash($data['new_password'], PASSWORD_DEFAULT),
                $id
            ]);

            // Send password change notification
            $template = "Dear {{username}},\n\n"
                . "Your password has been changed successfully.\n"
                . "If you did not make this change, please contact support immediately.";

            $this->communicationsApi->sendNotification([
                'email' => $user['email'],
                'phone' => $user['phone'] ?? null,
                'subject' => 'Password Changed',
                'message' => $this->parseTemplate($template, [
                    'username' => $user['username']
                ]),
                'send_email' => true,
                'send_sms' => !empty($user['phone'])
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Password changed successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function resetPassword($data) {
        try {
            if (empty($data['email'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Email is required'
                ], 400);
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $sql = "UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE email = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$token, $expires, $data['email']]);

            if ($stmt->rowCount() === 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'User not found'
                ], 404);
            }

            // Get user details
            $sql = "SELECT username FROM users WHERE email = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send reset email
            $resetLink = "http://localhost/Kingsway/reset-password?token=" . $token;
            $template = "Dear {{username}},\n\n"
                . "A password reset has been requested for your account.\n"
                . "Please click the link below to reset your password:\n\n"
                . "{{resetLink}}\n\n"
                . "This link will expire in 1 hour.\n"
                . "If you did not request this reset, please ignore this email.";

            $this->communicationsApi->sendNotification([
                'email' => $data['email'],
                'subject' => 'Password Reset Request',
                'message' => $this->parseTemplate($template, [
                    'username' => $user['username'],
                    'resetLink' => $resetLink
                ]),
                'send_email' => true,
                'send_sms' => false
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Password reset instructions sent'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getProfile($id) {
        try {
            $sql = "
                SELECT 
                    u.*,
                    GROUP_CONCAT(DISTINCT r.name) as roles,
                    GROUP_CONCAT(DISTINCT r.permissions) as role_permissions,
                    GROUP_CONCAT(DISTINCT p.name) as assigned_permissions
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id OR u.role_id = r.id
                LEFT JOIN user_permissions up ON u.id = up.user_id
                LEFT JOIN permissions p ON up.permission_id = p.id
                WHERE u.id = ?
                GROUP BY u.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            // Remove sensitive data
            unset($profile['password']);

            // Parse roles and permissions
            $profile['roles'] = $profile['roles'] ? explode(',', $profile['roles']) : [];
            
            // Parse and merge permissions
            $permissions = [];
            if ($profile['role_permissions']) {
                $rolePermissions = explode(',', $profile['role_permissions']);
                foreach ($rolePermissions as $permSet) {
                    $perms = json_decode($permSet, true);
                    if (is_array($perms)) {
                        $permissions = array_merge($permissions, array_keys($perms));
                    }
                }
                $profile['permissions'] = array_values(array_unique($permissions));
            } else {
                $profile['permissions'] = [];
            }
            unset($profile['role_permissions']);

            // Parse direct permissions
            $profile['assigned_permissions'] = $profile['assigned_permissions'] 
                ? explode(',', $profile['assigned_permissions']) 
                : [];

            // Get activity history
            $sql = "
                SELECT * FROM activity_logs 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $profile['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $profile]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPermissions() {
        try {
            // Get all unique permissions from roles table
            $sql = "SELECT DISTINCT permissions FROM roles WHERE permissions IS NOT NULL";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Merge and extract unique permissions from JSON
            $allPermissions = [];
            foreach ($results as $row) {
                $perms = json_decode($row['permissions'], true);
                if (is_array($perms)) {
                    $allPermissions = array_merge($allPermissions, array_keys($perms));
                }
            }
            
            // Remove duplicates and format
            $uniquePermissions = array_values(array_unique($allPermissions));
            
            return $this->response([
                'status' => 'success',
                'data' => $uniquePermissions
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignRole($id, $data) {
        try {
            if (!isset($data['roles']) || !is_array($data['roles'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Roles array is required'
                ], 400);
            }

            // Verify user exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            // Verify all roles exist
            $roleIds = [];
            foreach ($data['roles'] as $roleId) {
                $stmt = $this->db->prepare("SELECT id FROM roles WHERE id = ?");
                $stmt->execute([$roleId]);
                if (!$stmt->fetch()) {
                    return $this->response([
                        'status' => 'error',
                        'message' => "Role not found: $roleId"
                    ], 404);
                }
                $roleIds[] = $roleId;
            }

            if (empty($roleIds)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'At least one valid role is required'
                ], 400);
            }

            $this->db->beginTransaction();

            try {
                // Set primary role
                $sql = "UPDATE users SET role_id = ? WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$roleIds[0], $id]);

                // Remove existing additional roles
                $sql = "DELETE FROM user_roles WHERE user_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$id]);

                // Add additional roles
                if (count($roleIds) > 1) {
                    $values = array_map(function($roleId) use ($id) {
                        return "($id, $roleId)";
                    }, array_slice($roleIds, 1));
                    
                    $sql = "INSERT INTO user_roles (user_id, role_id) VALUES " . implode(',', $values);
                    $this->db->exec($sql);
                }

                $this->db->commit();
                $this->logAction('update', $id, "Updated user roles");

                return $this->response([
                    'status' => 'success',
                    'message' => 'Roles assigned successfully'
                ]);
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignPermission($id, $data) {
        try {
            if (!isset($data['permission'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Permission name is required'
                ], 400);
            }

            // Verify user exists
            $stmt = $this->db->prepare("SELECT role_id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                return $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            }

            // Get current role permissions
            $stmt = $this->db->prepare("SELECT permissions FROM roles WHERE id = ?");
            $stmt->execute([$user['role_id']]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Parse current permissions
            $permissions = $role['permissions'] ? json_decode($role['permissions'], true) : [];
            
            // Add new permission if not exists
            if (!isset($permissions[$data['permission']])) {
                $permissions[$data['permission']] = true;
                
                // Update role permissions
                $stmt = $this->db->prepare("UPDATE roles SET permissions = ? WHERE id = ?");
                $stmt->execute([json_encode($permissions), $user['role_id']]);
                
                $this->logAction('create', $id, "Added permission {$data['permission']} to user's role");
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Permission assigned successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Example login method (token generation and user info in response)
    public function login($data) {
        try {
            $required = ['username', 'password'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing credentials',
                    'fields' => $missing
                ], 400);
            }

            // Get user with all roles and permissions
            $sql = "
                SELECT 
                    u.*,
                    GROUP_CONCAT(DISTINCT r.name) as roles,
                    GROUP_CONCAT(DISTINCT r.permissions) as role_permissions
                FROM users u
                LEFT JOIN user_roles ur ON u.id = ur.user_id
                LEFT JOIN roles r ON ur.role_id = r.id OR u.role_id = r.id
                WHERE u.username = ?
                GROUP BY u.id
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['password'], $user['password'])) {
                return $this->response(['status' => 'error', 'message' => 'Invalid username or password'], 401);
            }

            if ($user['status'] !== 'active') {
                return $this->response(['status' => 'error', 'message' => 'Account is inactive. Contact admin.'], 403);
            }

            // Parse roles and permissions
            $roles = $user['roles'] ? explode(',', $user['roles']) : [];
            
            // Parse and merge permissions
            $permissions = [];
            if ($user['role_permissions']) {
                $rolePermissions = explode(',', $user['role_permissions']);
                foreach ($rolePermissions as $permSet) {
                    $perms = json_decode($permSet, true);
                    if (is_array($perms)) {
                        $permissions = array_merge($permissions, array_keys($perms));
                    }
                }
                $permissions = array_values(array_unique($permissions));
            }

            // Generate JWT token with all roles
            $token = $this->generateJWT([
                'id' => $user['id'],
                'username' => $user['username'],
                'roles' => $roles,
                'email' => $user['email'],
                'status' => $user['status'],
                'permissions' => $permissions
            ]);

            // Remove sensitive data
            unset($user['password']);
            unset($user['role_permissions']);
            unset($user['roles']);

            // Return token and user details
            return $this->response([
                'status' => 'success',
                'token' => $token,
                'user' => array_merge($user, [
                    'roles' => $roles,
                    'permissions' => $permissions
                ])
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // JWT generation helper (uses firebase/php-jwt)
    private function generateJWT($payload) {
        require_once __DIR__ . '/../../../vendor/autoload.php';
        require_once __DIR__ . '/../../../config/config.php';
        $key = $GLOBALS['jwt_secret'] ?? 'changeme';
        $now = time();
        $payload = array_merge($payload, [
            'iat' => $now,
            'exp' => $now + 86400 // 1 day expiry
        ]);
        return \Firebase\JWT\JWT::encode($payload, $key, 'HS256');
    }

    private function parseTemplate($template, $data) {
        $parsed = $template;
        foreach ($data as $key => $value) {
            $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
        }
        return $parsed;
    }

    /**
     * Get the main role (string) for a user by user ID
     */
    public function getMainRole($userId) {
        $stmt = $this->db->prepare('
            SELECT r.name 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE u.id = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    }

    /**
     * Get extra roles (array of strings) for a user by user ID
     */
    public function getExtraRoles($userId) {
        $stmt = $this->db->prepare('
            SELECT r.name 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ?
            ORDER BY r.name
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getSidebarItems($data) {
        $username = $data['username'] ?? '';
        $roles = $data['roles'] ?? [];
        $main_role = $data['main_role'] ?? '';
        $user_id = $data['user_id'] ?? null;

        // Get menu items from config
        $menu_items = require __DIR__ . '/../../../config/menu_items.php';
        
        $sidebar = [];
        
        // Add main role items first
        if (isset($menu_items[$main_role])) {
            $sidebar = array_merge($sidebar, $menu_items[$main_role]);
        }
        
        // Add items from additional roles
        foreach ($roles as $role) {
            if ($role !== $main_role && isset($menu_items[$role])) {
                $sidebar = array_merge($sidebar, $menu_items[$role]);
            }
        }
        
        // Add universal items last
        if (isset($menu_items['universal'])) {
            $sidebar = array_merge($sidebar, $menu_items['universal']);
        }
        
        // Remove duplicates while preserving order
        $seen = [];
        $unique_sidebar = [];
        
        foreach ($sidebar as $item) {
            $key = $item['url'] ?? '';
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique_sidebar[] = $item;
            }
        }
        
        return [
            'status' => 'success',
            'sidebar' => $unique_sidebar,
            'default_dashboard' => $this->getDefaultDashboard($main_role)
        ];
    }

    private function getDefaultDashboard($role) {
        $dashboards = [
            'admin' => 'admin_dashboard',
            'teacher' => 'teacher_dashboard',
            'accountant' => 'accounts_dashboard',
            'registrar' => 'admissions_dashboard',
            'headteacher' => 'head_teacher_dashboard',
            'head_teacher' => 'head_teacher_dashboard',
            'non_teaching' => 'non_teaching_dashboard',
            'student' => 'student_dashboard',
        ];
        
        return $dashboards[$role] ?? 'admin_dashboard';
    }
}