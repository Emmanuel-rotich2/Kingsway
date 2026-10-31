<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;
use App\API\Includes\FileLogger;

class LogsReportManager extends BaseAPI
{
    public function getCommunicationLogs($filters = [])
    {
        $entries = FileLogger::recent('audit', 100, ['entity' => 'communication']);
        return array_map(static function (array $entry): array {
            return [
                'action' => $entry['action'] ?? null,
                'entity_type' => $entry['entity'] ?? null,
                'level' => $entry['level'] ?? null,
                'created_at' => $entry['timestamp'] ?? null,
            ];
        }, $entries);
    }

    public function getFeeStructureLogs($filters = [])
    {
        $entries = FileLogger::recent('finance', 100);
        return array_map([$this, 'normalizeEntry'], array_filter($entries, function ($e) {
            $action = $e['action'] ?? $e['type'] ?? '';
            return in_array($action, ['fee_structure_create', 'fee_structure_update', 'fee_structure_delete'], true);
        }));
    }

    public function getInventoryLogs($filters = [])
    {
        $entries = FileLogger::recent('inventory', 100);
        return array_map([$this, 'normalizeEntry'], $entries);
    }

    public function getSystemLogs($filters = [])
    {
        $limit = (int) ($filters['limit'] ?? 100);
        $entries = FileLogger::recent('audit', max(1, min($limit, 500)));
        return array_map([$this, 'normalizeEntry'], $entries);
    }

    private function normalizeEntry(array $entry): array
    {
        return [
            'id' => null,
            'user_id' => $entry['user_id'] ?? null,
            'username' => null,
            'action' => $entry['action'] ?? null,
            'entity' => $entry['entity'] ?? null,
            'entity_type' => $entry['entity'] ?? null,
            'entity_id' => $entry['entity_id'] ?? null,
            'details' => $entry['details'] ?? null,
            'description' => $entry['details'] ?? null,
            'ip_address' => $entry['ip'] ?? $entry['ip_address'] ?? null,
            'created_at' => $entry['timestamp'] ?? null,
            'level' => $entry['level'] ?? null,
        ];
    }
}
