<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class SystemReportManager extends BaseAPI
{
    public function getLoginActivity($filters = [])
    {
        // Login activity from system_logs (action = 'login')
        try {
            $where = ["sl.action IN ('login','logout','failed_login','account_locked')"];
            $params = [];
            if (!empty($filters['user_id'])) {
                $where[] = 'sl.user_id = ?';
                $params[] = $filters['user_id'];
            }
            $limit = (int)($filters['limit'] ?? 200);
            $sql = "SELECT
                        sl.user_id,
                        u.username,
                        sl.action,
                        sl.ip_address,
                        sl.created_at AS login_time,
                        sl.description
                    FROM system_logs sl
                    LEFT JOIN users u ON u.id = sl.user_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY sl.created_at DESC
                    LIMIT {$limit}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAccountUnlocks($filters = [])
    {
        // Account unlocks from system_logs
        try {
            $sql = "SELECT
                        sl.user_id,
                        u.username,
                        sl.description,
                        sl.created_at AS unlock_time,
                        sl.ip_address
                    FROM system_logs sl
                    LEFT JOIN users u ON u.id = sl.user_id
                    WHERE sl.action = 'account_unlock'
                    ORDER BY sl.created_at DESC
                    LIMIT 200";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAuditTrailSummary($filters = [])
    {
        // Audit trail summary from system_logs
        try {
            $sql = "SELECT
                        sl.user_id,
                        u.username,
                        sl.action,
                        sl.entity_type AS module,
                        COUNT(*) AS action_count,
                        MAX(sl.created_at) AS last_action
                    FROM system_logs sl
                    LEFT JOIN users u ON u.id = sl.user_id
                    WHERE sl.action IN ('create','update','delete','approve','reject')
                    GROUP BY sl.user_id, u.username, sl.action, sl.entity_type
                    ORDER BY action_count DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getBlockedDevicesStats($filters = [])
    {
        // Blocked devices statistics from device_blacklist or system_logs
        try {
            $sql = "SELECT
                        d.device_fingerprint AS device_id,
                        d.user_id,
                        u.username,
                        d.blocked_at,
                        d.reason
                    FROM device_blacklist d
                    LEFT JOIN users u ON u.id = d.user_id
                    ORDER BY d.blocked_at DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback: query system_logs for block events
            try {
                $sql2 = "SELECT user_id, description, created_at AS blocked_at, ip_address
                         FROM system_logs
                         WHERE action = 'device_blocked'
                         ORDER BY created_at DESC
                         LIMIT 100";
                $stmt2 = $this->db->query($sql2);
                return $stmt2->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e2) {
                return [];
            }
        }
    }
}
