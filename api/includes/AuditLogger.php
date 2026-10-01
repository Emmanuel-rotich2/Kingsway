<?php
namespace App\API\Includes;

use PDO;

/**
 * AuditLogger - Comprehensive audit logging system
 * 
 * Tracks all user management actions for security and compliance.
 * All entries are written to log files (never the database).
 * Logs: who, what, when, where (IP), and changes made
 */
class AuditLogger
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Log user action
     * 
     * @param string $action Type of action (create, update, delete, login, etc.)
     * @param string $entity Type of entity (user, role, permission)
     * @param int $entityId ID of affected entity
     * @param int $userId ID of user performing action
     * @param array $details Additional details (old values, new values, etc.)
     * @param string $status success or failure
     */
    public function log(
        string $action,
        string $entity,
        $entityId,
        $userId,
        array $details = [],
        string $status = 'success'
    ): bool {
        try {
            FileLogger::write('audit', [
                'type' => 'audit',
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'user_id' => $userId,
                'ip' => $this->getClientIP(),
                'user_agent' => $this->getUserAgent(),
                'details' => $details,
                'status' => $status,
            ]);
            return true;
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("Audit log failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log user creation
     */
    public function logUserCreate(int $userId, int $createdUserId, array $userData): bool
    {
        return $this->log(
            'create',
            'user',
            $createdUserId,
            $userId,
            [
                'username' => $userData['username'],
                // Email is optional for parents (phone-only onboarding); the
                // audit entry must not fail the creation when it is absent.
                'email' => $userData['email'] ?? null,
                'role_id' => $userData['role_id'] ?? null,
                'status' => $userData['status'] ?? 'active'
            ]
        );
    }

    /**
     * Log user update with before/after values
     */
    public function logUserUpdate(int $userId, int $updatedUserId, array $oldData, array $newData): bool
    {
        $changes = $this->detectChanges($oldData, $newData);
        
        return $this->log(
            'update',
            'user',
            $updatedUserId,
            $userId,
            [
                'changes' => $changes,
                'fields_changed' => array_keys($changes)
            ]
        );
    }

    /**
     * Log user deletion
     */
    public function logUserDelete(int $userId, int $deletedUserId, array $userData): bool
    {
        return $this->log(
            'delete',
            'user',
            $deletedUserId,
            $userId,
            [
                'deleted_username' => $userData['username'],
                'deleted_email' => $userData['email']
            ]
        );
    }

    /**
     * Log role assignment
     */
    public function logRoleAssign(int $userId, int $targetUserId, int $roleId, string $roleType = 'main'): bool
    {
        return $this->log(
            'assign_role',
            'user',
            $targetUserId,
            $userId,
            [
                'role_id' => $roleId,
                'role_type' => $roleType
            ]
        );
    }

    /**
     * Log role removal
     */
    public function logRoleRevoke(int $userId, int $targetUserId, int $roleId): bool
    {
        return $this->log(
            'revoke_role',
            'user',
            $targetUserId,
            $userId,
            [
                'role_id' => $roleId
            ]
        );
    }

    /**
     * Log permission assignment
     */
    public function logPermissionAssign(int $userId, int $targetUserId, $permissionId, string $permType = 'grant'): bool
    {
        return $this->log(
            'assign_permission',
            'user',
            $targetUserId,
            $userId,
            [
                'permission_id' => $permissionId,
                'permission_type' => $permType
            ]
        );
    }

    /**
     * Log permission revocation
     */
    public function logPermissionRevoke(int $userId, int $targetUserId, $permissionId): bool
    {
        return $this->log(
            'revoke_permission',
            'user',
            $targetUserId,
            $userId,
            [
                'permission_id' => $permissionId
            ]
        );
    }

    /**
     * Log password change
     */
    public function logPasswordChange(int $userId, int $targetUserId, bool $selfChange = false): bool
    {
        return $this->log(
            'password_change',
            'user',
            $targetUserId,
            $userId,
            [
                'self_change' => $selfChange,
                'admin_reset' => !$selfChange
            ]
        );
    }

    /**
     * Log failed login attempt
     */
    public function logFailedLogin(string $username, string $reason): bool
    {
        return $this->log(
            'login_failed',
            'user',
            null,
            null,
            [
                'username' => $username,
                'reason' => $reason
            ],
            'failure'
        );
    }

    /**
     * Log successful login
     */
    public function logSuccessfulLogin(int $userId, string $username): bool
    {
        return $this->log(
            'login_success',
            'user',
            $userId,
            $userId,
            [
                'username' => $username
            ]
        );
    }

    /**
     * Log bulk operation
     */
    public function logBulkOperation(int $userId, string $action, string $entity, array $entityIds, array $details = []): bool
    {
        return $this->log(
            "bulk_$action",
            $entity,
            null,
            $userId,
            array_merge([
                'affected_count' => count($entityIds),
                'entity_ids' => $entityIds
            ], $details)
        );
    }

    /**
     * Get audit logs for a user (file-based).
     */
    public function getUserLogs(int $userId, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min((int) $limit, 500));
        $offset = max(0, (int) $offset);
        $entries = FileLogger::recent('audit', $limit + $offset);
        $rows = [];
        foreach ($entries as $e) {
            $eUserId = $e['user_id'] ?? null;
            if ((string) $eUserId !== (string) $userId) {
                continue;
            }
            $rows[] = $e;
        }
        return array_slice($rows, $offset, $limit);
    }

    /**
     * Get all audit logs with filters (file-based).
     */
    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min((int) $limit, 500));
        $offset = max(0, (int) $offset);
        // Pull a generous tail so filters can be applied without missing recent entries.
        $entries = FileLogger::recent('audit', max(1000, $limit + $offset));
        $rows = [];

        foreach ($entries as $e) {
            if (!empty($filters['action']) && ($e['action'] ?? null) !== $filters['action']) {
                continue;
            }
            if (!empty($filters['entity']) && ($e['entity'] ?? null) !== $filters['entity']) {
                continue;
            }
            if (isset($filters['user_id']) && $filters['user_id'] !== '' && (string) ($e['user_id'] ?? null) !== (string) $filters['user_id']) {
                continue;
            }
            if (isset($filters['entity_id']) && $filters['entity_id'] !== '' && (string) ($e['entity_id'] ?? null) !== (string) $filters['entity_id']) {
                continue;
            }
            if (!empty($filters['status']) && ($e['status'] ?? null) !== $filters['status']) {
                continue;
            }
            if (!empty($filters['start_date']) && ($e['timestamp'] ?? '') < ($filters['start_date'] . ' 00:00:00')) {
                continue;
            }
            if (!empty($filters['end_date']) && ($e['timestamp'] ?? '') > ($filters['end_date'] . ' 23:59:59')) {
                continue;
            }

            $row = $e;
            $row['performer_username'] = $e['username'] ?? null;
            $rows[] = $row;
        }

        return array_slice($rows, $offset, $limit);
    }

    /**
     * Get audit log statistics (file-based).
     */
    public function getStats(array $filters = []): array
    {
        $entries = FileLogger::recent('audit', 5000);
        $total = 0;
        $users = [];
        $successful = 0;
        $failed = 0;
        $loginAttempts = 0;

        foreach ($entries as $e) {
            if (!empty($filters['start_date']) && ($e['timestamp'] ?? '') < ($filters['start_date'] . ' 00:00:00')) {
                continue;
            }
            if (!empty($filters['end_date']) && ($e['timestamp'] ?? '') > ($filters['end_date'] . ' 23:59:59')) {
                continue;
            }

            $total++;
            $userId = $e['user_id'] ?? null;
            if ($userId !== null && $userId !== '') {
                $users[(string) $userId] = true;
            }
            $status = strtolower((string) ($e['status'] ?? ''));
            if (in_array($status, ['success', 'ok', 'completed'], true)) {
                $successful++;
            }
            if (in_array($status, ['failure', 'failed', 'error'], true)) {
                $failed++;
            }
            if (stripos((string) ($e['action'] ?? ''), 'login') !== false) {
                $loginAttempts++;
            }
        }

        return [
            'total_logs' => $total,
            'unique_users' => count($users),
            'successful_actions' => $successful,
            'failed_actions' => $failed,
            'login_attempts' => $loginAttempts,
        ];
    }

    /**
     * Detect changes between old and new data
     */
    private function detectChanges(array $old, array $new): array
    {
        $changes = [];
        
        foreach ($new as $key => $value) {
            if ($key === 'password') continue; // Don't log password values
            
            if (!isset($old[$key]) || $old[$key] != $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value
                ];
            }
        }
        
        return $changes;
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Get user agent
     */
    private function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }

    /**
     * Create audit_logs table if not exists
     */
    public function createTableIfNotExists(): bool
    {
        $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(50) NOT NULL,
            entity VARCHAR(50) NOT NULL,
            entity_id INT NULL,
            user_id INT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            details TEXT NULL,
            status ENUM('success', 'failure') DEFAULT 'success',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_entity (entity, entity_id),
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        try {
            $this->db->exec($sql);
            return true;
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("Failed to create audit_logs table: " . $e->getMessage());
            return false;
        }
    }
}
