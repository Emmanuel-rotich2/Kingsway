<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class SystemReportManager extends BaseAPI
{
    public function getLoginActivity($filters = [])
    {
        // Login activity: user logins, timestamps, IPs
        $where = [];
        $params = [];
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        $sql = "SELECT user_id, login_time, ip_address, device_info
                FROM user_login_activity";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY login_time DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAccountUnlocks($filters = [])
    {
        // Account unlocks: user, admin, timestamp
        $sql = "SELECT user_id, unlocked_by, unlock_time, reason
                FROM account_unlocks
                ORDER BY unlock_time DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getAuditTrailSummary($filters = [])
    {
        // Audit trail summary: user actions, counts
        $sql = "SELECT user_id, action, COUNT(*) as action_count
                FROM audit_logs
                GROUP BY user_id, action
                ORDER BY action_count DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getBlockedDevicesStats($filters = [])
    {
        // Blocked devices statistics
        $sql = "SELECT device_id, user_id, blocked_at, reason
                FROM blocked_devices
                ORDER BY blocked_at DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
