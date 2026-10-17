<?php

namespace App\API\Services;

use App\API\Modules\users\UserPermissionManager;
use PDO;

/** Server-side permission helpers for Node-coordinated data and job results. */
final class NodeReadService
{
    /** Resolve effective permissions from the authoritative PHP policy layer. */
    public static function resolvePermissions(PDO $db, int $operatorId): array
    {
        $manager = new UserPermissionManager($db);
        $byUser = $manager->effectivePermissionsByUser([$operatorId]);
        return $byUser[$operatorId] ?? [];
    }

    /** Use the authoritative permission function for role, deny and wildcard semantics. */
    public static function permits(PDO $db, int $operatorId, string $permission): bool
    {
        $manager = new UserPermissionManager($db);
        $result = $manager->hasPermission($operatorId, $permission);
        return is_array($result) && !empty($result['has_permission']);
    }

    /** Reauthorize access to a completed automation artifact after the job finishes. */
    public static function canReadJobResult(PDO $db, array $job, int $operatorId): bool
    {
        if ($operatorId < 1 || (string) ($job['job_type'] ?? '') !== 'automation.run') {
            return false;
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $automation = trim((string) ($payload['automation'] ?? ''));
        $artifactId = trim((string) ($payload['artifact_id'] ?? ''));
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        if ((int) ($payload['user_id'] ?? 0) !== $operatorId
            || $automation === ''
            || preg_match('/^[a-f0-9]{32}$/', $artifactId) !== 1
            || !hash_equals($artifactId, (string) ($result['artifact_id'] ?? ''))
            || (int) ($result['operator_id'] ?? 0) !== $operatorId) {
            return false;
        }

        $permissions = self::resolvePermissions($db, $operatorId);
        return AutomationRegistry::isPermitted($permissions, $automation);
    }
}
