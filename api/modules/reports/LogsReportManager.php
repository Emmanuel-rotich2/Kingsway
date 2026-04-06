<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class LogsReportManager extends BaseAPI
{
    public function getCommunicationLogs($filters = [])
    {
        try {
            $sql = "SELECT * FROM communication_logs ORDER BY created_at DESC LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFeeStructureLogs($filters = [])
    {
        try {
            $sql = "SELECT * FROM fee_structure_change_log ORDER BY changed_at DESC LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryLogs($filters = [])
    {
        try {
            $sql = "SELECT * FROM inventory_logs ORDER BY created_at DESC LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getSystemLogs($filters = [])
    {
        try {
            $limit = (int)($filters['limit'] ?? 100);
            $sql = "SELECT
                        sl.id,
                        sl.user_id,
                        u.username,
                        sl.action,
                        sl.entity_type,
                        sl.entity_id,
                        sl.description,
                        sl.ip_address,
                        sl.created_at
                    FROM system_logs sl
                    LEFT JOIN users u ON u.id = sl.user_id
                    ORDER BY sl.created_at DESC
                    LIMIT {$limit}";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
