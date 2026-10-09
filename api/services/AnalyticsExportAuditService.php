<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Authorizes and records files generated from governed report runs.
 */
final class AnalyticsExportAuditService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function authorize(int $runId, array $user, string $format): array
    {
        $userId = $this->userId($user);
        if (!$userId) {
            throw new RuntimeException('Authentication required.', 401);
        }
        if (!$this->hasPermission($user, 'analytics_report_export')) {
            throw new RuntimeException('You do not have permission to export governed reports.', 403);
        }
        $roleIds = $this->roleIds($user, $userId);
        if ($roleIds === []) {
            throw new RuntimeException('No authorized report role was found.', 403);
        }
        $marks = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT rr.id AS run_id, rr.requested_by, rr.status, rd.id AS report_definition_id,
                    rd.code, rd.version, rd.title, rd.sensitivity, rd.export_formats_json,
                    MAX(ara.can_export) AS can_export
             FROM " . ReadReplicaService::qualifiedRef("analytics_report_runs") . " rr
             JOIN analytics_report_definitions rd ON rd.id = rr.report_definition_id
             JOIN analytics_report_role_access ara
               ON ara.report_definition_id = rd.id
              AND ara.role_id IN ($marks)
             WHERE rr.id = ?
             GROUP BY rr.id, rd.id
             LIMIT 1"
        );
        $stmt->execute(array_merge($roleIds, [$runId]));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['can_export'])) {
            throw new RuntimeException('This report is not exportable by your role.', 403);
        }
        if ((int) $row['requested_by'] !== $userId && !$this->hasPermission($user, 'analytics_report_admin')) {
            throw new RuntimeException('You may export only report runs generated under your own authorization scope.', 403);
        }
        if ($row['status'] !== 'completed') {
            throw new RuntimeException('Only completed report runs can be exported.', 409);
        }
        $formats = json_decode((string) ($row['export_formats_json'] ?? '[]'), true);
        if (!is_array($formats) || !in_array($format, $formats, true)) {
            throw new RuntimeException('The requested export format is not approved for this report.', 422);
        }
        unset($row['export_formats_json'], $row['can_export']);
        return $row;
    }

    public function record(array $authorization, array $user, string $path, string $format, string $mimeType): array
    {
        $userId = $this->userId($user);
        if (!$userId || !is_file($path)) {
            throw new RuntimeException('Generated report file could not be audited.', 500);
        }
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum) || strlen($checksum) !== 64) {
            throw new RuntimeException('Generated report checksum could not be calculated.', 500);
        }
        $filename = basename($path);
        $size = filesize($path);
        $stmt = $this->db->prepare(
            "INSERT INTO analytics_generated_files
                (report_run_id, generated_by, format, original_filename,
                 storage_path, mime_type, file_size, checksum_sha256,
                 sensitivity, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
             ON DUPLICATE KEY UPDATE
                 original_filename = VALUES(original_filename),
                 storage_path = VALUES(storage_path),
                 mime_type = VALUES(mime_type),
                 file_size = VALUES(file_size),
                 expires_at = VALUES(expires_at),
                 deleted_at = NULL"
        );
        $stmt->execute([
            (int) $authorization['run_id'],
            $userId,
            $format,
            $filename,
            $path,
            $mimeType,
            $size === false ? 0 : (int) $size,
            $checksum,
            (string) $authorization['sensitivity'],
        ]);
        $id = (int) $this->db->lastInsertId();
        if ($id === 0) {
            $lookup = $this->db->prepare(
                'SELECT id FROM analytics_generated_files WHERE report_run_id = ? AND checksum_sha256 = ? LIMIT 1'
            );
            $lookup->execute([(int) $authorization['run_id'], $checksum]);
            $id = (int) $lookup->fetchColumn();
        }
        return [
            'id' => $id,
            'report_run_id' => (int) $authorization['run_id'],
            'checksum_sha256' => $checksum,
            'expires_in_days' => 30,
        ];
    }

    private function roleIds(array $user, int $userId): array
    {
        $ids = [];
        foreach (($user['roles'] ?? []) as $role) {
            if (is_numeric($role)) {
                $ids[] = (int) $role;
            } elseif (is_array($role) && isset($role['id'])) {
                $ids[] = (int) $role['id'];
            } elseif (is_object($role) && isset($role->id)) {
                $ids[] = (int) $role->id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids !== []) return $ids;
        $stmt = $this->db->prepare('SELECT role_id FROM user_roles WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function userId(array $user): ?int
    {
        $id = $user['user_id'] ?? $user['id'] ?? null;
        return $id ? (int) $id : null;
    }

    private function hasPermission(array $user, string $permission): bool
    {
        $permissions = $user['effective_permissions'] ?? $user['permissions'] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }
}
