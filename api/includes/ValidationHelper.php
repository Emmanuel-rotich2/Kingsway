<?php
namespace App\API\Includes;

/**
 * ValidationHelper - Centralized input validation and sanitization
 * 
 * Provides reusable validation methods for all controllers
 * Prevents SQL injection, XSS, and invalid data
 */
class ValidationHelper
{
    /**
     * Validate email format
     */
    public static function validateEmail(string $email): array
    {
        if (empty($email)) {
            return ['valid' => false, 'error' => 'Email is required'];
        }

        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }

        // Additional check for common typos
        if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email)) {
            return ['valid' => false, 'error' => 'Invalid email format'];
        }

        return ['valid' => true, 'value' => $email];
    }

    /**
     * Validate username format
     * Rules: 3-30 chars, alphanumeric + underscore/hyphen, must start with letter
     */
    public static function validateUsername(string $username): array
    {
        if (empty($username)) {
            return ['valid' => false, 'error' => 'Username is required'];
        }

        // Remove whitespace
        $username = trim($username);

        // Length check
        if (strlen($username) < 3 || strlen($username) > 30) {
            return ['valid' => false, 'error' => 'Username must be 3-30 characters'];
        }

        // Format check: alphanumeric + underscore/hyphen, must start with letter
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $username)) {
            return ['valid' => false, 'error' => 'Username must start with a letter and contain only letters, numbers, underscore, or hyphen'];
        }

        return ['valid' => true, 'value' => $username];
    }

    /**
     * Validate password strength
     * Rules: Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
     */
    public static function validatePassword(string $password): array
    {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password is required'];
        }

        // Minimum length
        if (strlen($password) < 8) {
            return ['valid' => false, 'error' => 'Password must be at least 8 characters long'];
        }

        // Maximum length (prevent DoS)
        if (strlen($password) > 128) {
            return ['valid' => false, 'error' => 'Password must not exceed 128 characters'];
        }

        // Check for uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one uppercase letter'];
        }

        // Check for lowercase
        if (!preg_match('/[a-z]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one lowercase letter'];
        }

        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one number'];
        }

        // Check for special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return ['valid' => false, 'error' => 'Password must contain at least one special character (!@#$%^&*etc)'];
        }

        // Check for common weak passwords
        $weakPasswords = [
            'password', 'Password1!', '12345678', 'qwerty123', 'admin123',
            'Welcome1!', 'Password123!', 'Admin@123', 'Test@123'
        ];
        
        if (in_array($password, $weakPasswords)) {
            return ['valid' => false, 'error' => 'This password is too common. Please choose a stronger password'];
        }

        return ['valid' => true, 'value' => $password];
    }

    /**
     * Sanitize text input to prevent XSS
     */
    public static function sanitizeText(string $text): string
    {
        $text = trim($text);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $text;
    }

    /**
     * Validate name (first_name, last_name)
     * Rules: 1-50 chars, letters, spaces, hyphens, apostrophes only
     */
    public static function validateName(string $name, string $fieldName = 'Name'): array
    {
        if (empty($name)) {
            return ['valid' => false, 'error' => "$fieldName is required"];
        }

        $name = trim($name);

        // Length check
        if (strlen($name) < 1 || strlen($name) > 50) {
            return ['valid' => false, 'error' => "$fieldName must be 1-50 characters"];
        }

        // Format check: letters, spaces, hyphens, apostrophes
        if (!preg_match("/^[a-zA-Z\s'-]+$/u", $name)) {
            return ['valid' => false, 'error' => "$fieldName can only contain letters, spaces, hyphens, and apostrophes"];
        }

        return ['valid' => true, 'value' => self::sanitizeText($name)];
    }

    /**
     * Validate status field
     */
    public static function validateStatus(string $status): array
    {
        $validStatuses = ['active', 'inactive', 'suspended', 'pending'];
        
        if (!in_array($status, $validStatuses)) {
            return ['valid' => false, 'error' => 'Invalid status. Must be: active, inactive, suspended, or pending'];
        }

        return ['valid' => true, 'value' => $status];
    }

    /**
     * Validate role ID exists
     */
    public static function validateRoleId($roleId, \PDO $db): array
    {
        if (empty($roleId)) {
            return ['valid' => false, 'error' => 'Role ID is required'];
        }

        if (!is_numeric($roleId)) {
            return ['valid' => false, 'error' => 'Role ID must be a number'];
        }

        // Check if role exists (roles table uses 'id')
        $stmt = $db->prepare('SELECT id FROM roles WHERE id = ?');
        $stmt->execute([$roleId]);
        
        if (!$stmt->fetch()) {
            return ['valid' => false, 'error' => 'Invalid role ID'];
        }

        return ['valid' => true, 'value' => (int)$roleId];
    }

    /**
     * Validate user ID exists
     */
    public static function validateUserId($userId, \PDO $db): array
    {
        if (empty($userId)) {
            return ['valid' => false, 'error' => 'User ID is required'];
        }

        if (!is_numeric($userId)) {
            return ['valid' => false, 'error' => 'User ID must be a number'];
        }

        // Check if user exists
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        
        if (!$stmt->fetch()) {
            return ['valid' => false, 'error' => 'Invalid user ID'];
        }

        return ['valid' => true, 'value' => (int)$userId];
    }

    /**
     * Check if username is unique
     */
    public static function isUsernameUnique(string $username, \PDO $db, $excludeUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE username = ?';
        $params = [$username];
        
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() == 0;
    }

    /**
     * Check if email is unique
     */
    public static function isEmailUnique(string $email, \PDO $db, $excludeUserId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = ?';
        $params = [$email];
        
        if ($excludeUserId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeUserId;
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() == 0;
    }

    /**
     * Comprehensive user data validation
     * Used for create/update operations
     */
    public static function validateUserData(array $data, \PDO $db, $isUpdate = false, $userId = null): array
    {
        $errors = [];
        $validated = [];

        // Username validation
        if (!$isUpdate || isset($data['username'])) {
            $result = self::validateUsername($data['username'] ?? '');
            if (!$result['valid']) {
                $errors[] = $result['error'];
            } else {
                if (!self::isUsernameUnique($result['value'], $db, $userId)) {
                    $errors[] = 'Username already exists';
                } else {
                    $validated['username'] = $result['value'];
                }
            }
        }

        // Email validation
        if (!$isUpdate || isset($data['email'])) {
            $result = self::validateEmail($data['email'] ?? '');
            if (!$result['valid']) {
                $errors[] = $result['error'];
            } else {
                if (!self::isEmailUnique($result['value'], $db, $userId)) {
                    $errors[] = 'Email already exists';
                } else {
                    $validated['email'] = $result['value'];
                }
            }
        }

        // Password validation (only for create or if password is being updated)
        if (!$isUpdate || isset($data['password'])) {
            if (!$isUpdate || !empty($data['password'])) {
                $result = self::validatePassword($data['password'] ?? '');
                if (!$result['valid']) {
                    $errors[] = $result['error'];
                } else {
                    $validated['password'] = $result['value'];
                }
            }
        }

        // First name validation
        if (!$isUpdate || isset($data['first_name'])) {
            $result = self::validateName($data['first_name'] ?? '', 'First name');
            if (!$result['valid']) {
                $errors[] = $result['error'];
            } else {
                $validated['first_name'] = $result['value'];
            }
        }

        // Last name validation
        if (!$isUpdate || isset($data['last_name'])) {
            $result = self::validateName($data['last_name'] ?? '', 'Last name');
            if (!$result['valid']) {
                $errors[] = $result['error'];
            } else {
                $validated['last_name'] = $result['value'];
            }
        }

        // Role validation - accept either single role_id/main_role_id or role_ids array (preferred)
        $roleIds = null;
        if (isset($data['role_ids']) && is_array($data['role_ids'])) {
            $roleIds = array_values(array_filter($data['role_ids'], function ($v) {
                return is_numeric($v); }));
        }
        $singleRole = $data['main_role_id'] ?? $data['role_id'] ?? null;

        if (!$isUpdate) {
            if (empty($roleIds) && $singleRole === null) {
                $errors[] = 'Role ID(s) are required';
            } else {
                if (!empty($roleIds)) {
                    $validated['role_ids'] = array_map('intval', $roleIds);
                    // Validate each role id
                    foreach ($validated['role_ids'] as $rid) {
                        $result = self::validateRoleId($rid, $db);
                        if (!$result['valid']) {
                            $errors[] = 'Invalid role id: ' . $rid;
                        }
                    }
                    // set primary role for backward compatibility
                    $validated['role_id'] = $validated['role_ids'][0];
                } else {
                    $result = self::validateRoleId($singleRole, $db);
                    if (!$result['valid']) {
                        $errors[] = $result['error'];
                    } else {
                        $validated['role_id'] = $result['value'];
                        $validated['role_ids'] = [$result['value']];
                    }
                }
            }
        } else {
            // Update: allow role change via single role_id or role_ids
            if (!empty($roleIds)) {
                $validated['role_ids'] = array_map('intval', $roleIds);
                $validated['role_id'] = $validated['role_ids'][0] ?? null;
            } elseif ($singleRole !== null) {
                $result = self::validateRoleId($singleRole, $db);
                if (!$result['valid']) {
                    $errors[] = $result['error'];
                } else {
                    $validated['role_id'] = $result['value'];
                    $validated['role_ids'] = [$result['value']];
                }
            }
        }

        // Permissions (optional array of codes or objects)
        if (isset($data['permissions'])) {
            if (!is_array($data['permissions'])) {
                $errors[] = 'permissions must be an array';
            } else {
                $validated['permissions'] = $data['permissions'];
            }
        }

        // Staff info (optional) - lightly validate known staff fields if provided
        if (isset($data['staff_info']) && is_array($data['staff_info'])) {
            $staffInfo = $data['staff_info'];
            $validatedStaff = [];

            if (isset($staffInfo['department_id']) && !is_numeric($staffInfo['department_id'])) {
                $errors[] = 'department_id must be numeric';
            } else {
                $validatedStaff['department_id'] = isset($staffInfo['department_id']) ? intval($staffInfo['department_id']) : null;
            }

            if (isset($staffInfo['employment_date'])) {
                // basic date format check YYYY-MM-DD
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $staffInfo['employment_date'])) {
                    $errors[] = 'employment_date must be in YYYY-MM-DD format';
                } else {
                    $validatedStaff['employment_date'] = $staffInfo['employment_date'];
                }
            }

            if (isset($staffInfo['salary']) && !is_numeric($staffInfo['salary'])) {
                $errors[] = 'salary must be numeric';
            } else {
                $validatedStaff['salary'] = isset($staffInfo['salary']) ? $staffInfo['salary'] : null;
            }

            // Accept other staff fields as-is (autoprocessing occurs later)
            foreach (['staff_type_id', 'staff_category_id', 'supervisor_id', 'position', 'contract_type', 'nssf_no', 'kra_pin', 'nhif_no', 'bank_account', 'gender', 'marital_status', 'tsc_no', 'address', 'profile_pic_url', 'documents_folder', 'date_of_birth', 'first_name', 'last_name'] as $sf) {
                if (isset($staffInfo[$sf])) {
                    $validatedStaff[$sf] = $staffInfo[$sf];
                }
            }

            $validated['staff_info'] = $validatedStaff;
        }

        // Status validation
        if (!$isUpdate || isset($data['status'])) {
            $status = $data['status'] ?? 'active';
            $result = self::validateStatus($status);
            if (!$result['valid']) {
                $errors[] = $result['error'];
            } else {
                $validated['status'] = $result['value'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        ];
    }
}
