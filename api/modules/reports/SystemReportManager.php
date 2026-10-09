<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;
use App\API\Includes\FileLogger;
use App\API\Services\ReadReplicaService;

class SystemReportManager extends BaseAPI
{
    public function getLoginActivity($filters = [])
    {
        $limit = (int) ($filters['limit'] ?? 200);
        $entries = FileLogger::recent('auth', max(1, min($limit, 500)));
        $rows = [];
        foreach ($entries as $e) {
            if (($e['type'] ?? '') !== 'login_attempt') {
                continue;
            }
            if (!empty($filters['user_id']) && (int) ($e['user_id'] ?? 0) !== (int) $filters['user_id']) {
                continue;
            }
            $status = $e['status'] ?? 'failed';
            $rows[] = [
                'user_id' => $e['user_id'] ?? null,
                'username' => $e['username'] ?? null,
                'action' => $status === 'success' ? 'login_success' : 'login_failed',
                'ip_address' => $e['ip'] ?? $e['ip_address'] ?? null,
                'login_time' => $e['timestamp'] ?? null,
                'description' => $e['failure_reason'] ?? null,
            ];
        }
        return $rows;
    }

    public function getAccountUnlocks($filters = [])
    {
        $entries = FileLogger::recent('audit', 500);
        $rows = [];
        foreach ($entries as $e) {
            if (($e['action'] ?? '') !== 'account_unlock') {
                continue;
            }
            $rows[] = [
                'user_id' => $e['user_id'] ?? null,
                'username' => null,
                'description' => $e['details'] ?? null,
                'unlock_time' => $e['timestamp'] ?? null,
                'ip_address' => $e['ip'] ?? $e['ip_address'] ?? null,
            ];
        }
        return $rows;
    }

    public function getAuditTrailSummary($filters = [])
    {
        $entries = FileLogger::recent('audit', 2000);
        $agg = [];
        foreach ($entries as $e) {
            $action = $e['action'] ?? null;
            if (!in_array($action, ['create', 'update', 'delete', 'approve', 'reject'], true)) {
                continue;
            }
            $timestamp = (string) ($e['timestamp'] ?? '');
            if (!empty($filters['date_from']) && substr($timestamp, 0, 10) < $filters['date_from']) continue;
            if (!empty($filters['date_to']) && substr($timestamp, 0, 10) > $filters['date_to']) continue;
            if (!empty($filters['action']) && $action !== $filters['action']) continue;
            if (!empty($filters['user_id']) && (int) ($e['user_id'] ?? 0) !== (int) $filters['user_id']) continue;
            $key = ($e['user_id'] ?? '') . '|' . ($e['entity'] ?? '') . '|' . $action;
            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'user_id' => $e['user_id'] ?? null,
                    'username' => null,
                    'action' => $action,
                    'module' => $e['entity'] ?? null,
                    'event_count' => 0,
                    'last_event_at' => $e['timestamp'] ?? null,
                ];
            }
            $agg[$key]['event_count']++;
            if (($e['timestamp'] ?? '') > ($agg[$key]['last_event_at'] ?? '')) {
                $agg[$key]['last_event_at'] = $e['timestamp'];
            }
        }
        usort($agg, fn($a, $b) => $b['event_count'] <=> $a['event_count']);
        return array_slice($agg, 0, 100);
    }

    public function getBlockedDevicesStats($filters = [])
    {
        try {
            $sql = "SELECT
                        bd.user_agent_pattern AS device_id,
                        bd.created_by AS user_id,
                        u.username,
                        bd.created_at AS blocked_at,
                        bd.reason
                    FROM " . ReadReplicaService::qualifiedRef("blocked_devices") . "
                    LEFT JOIN users u ON u.id = bd.created_by
                    ORDER BY bd.created_at DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback: read device block events from the file log
            $entries = FileLogger::recent('device', 500);
            $rows = [];
            foreach ($entries as $e) {
                if (($e['action'] ?? '') !== 'device_blocked') {
                    continue;
                }
                $rows[] = [
                    'user_id' => $e['user_id'] ?? null,
                    'description' => $e['details'] ?? null,
                    'blocked_at' => $e['timestamp'] ?? null,
                    'ip_address' => $e['ip'] ?? null,
                ];
            }
            return $rows;
        }
    }
}
