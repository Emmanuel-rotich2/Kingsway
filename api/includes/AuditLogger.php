<?php
namespace App\API\Includes;

use PDO;

/**
 * AuditLogger - Comprehensive audit logging system
 * 
 * Tracks all user management actions for security and compliance
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
            $sql = "INSERT INTO audit_logs 
                    (action, entity, entity_id, user_id, ip_address, user_agent, details, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                $action,
                $entity,
                $entityId,
                $userId,
                $this->getClientIP(),
                $this->getUserAgent(),
                json_encode($details),
                $status
            ]);
        } catch (\Exception $e) {
            error_log("Audit log failed: " . $e->getMessage());
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
                'email' => $userData['email'],
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
     * Get audit logs for a user
     */
    public function getUserLogs(int $userId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM audit_logs 
                WHERE entity = 'user' AND entity_id = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, $limit, $offset]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all audit logs with filters
     */
    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT al.*, u.username as performer_username 
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }

        if (!empty($filters['entity'])) {
            $sql .= " AND al.entity = ?";
            $params[] = $filters['entity'];
        }

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['entity_id'])) {
            $sql .= " AND al.entity_id = ?";
            $params[] = $filters['entity_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND al.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['end_date'];
        }

        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get audit log statistics
     */
    public function getStats(array $filters = []): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_actions,
                    COUNT(CASE WHEN status = 'failure' THEN 1 END) as failed_actions,
                    COUNT(CASE WHEN action LIKE '%login%' THEN 1 END) as login_attempts
                FROM audit_logs
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
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
            error_log("Failed to create audit_logs table: " . $e->getMessage());
            return false;
        }
    }
}
