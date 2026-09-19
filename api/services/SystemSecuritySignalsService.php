<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;

/** Identity-free, deterministic security aggregates for administrator review. */
final class SystemSecuritySignalsService
{
    public const WORKFLOW = 'system.security_brief';
    public function __construct(private $reader = null) { $this->reader ??= static fn(string $category, int $limit): array => FileLogger::recent($category, $limit); }

    public function summary(): array
    {
        $failed = 0; $denied = 0; $incidents = 0; $signals = [];
        foreach (['auth', 'audit', 'security', 'errors'] as $category) foreach (($this->reader)($category, 500) as $entry) {
            if (!is_array($entry) || (($ts = strtotime((string) ($entry['timestamp'] ?? ''))) === false) || $ts < time() - 86400) continue;
            $type = strtolower((string) ($entry['type'] ?? $entry['action'] ?? ''));
            if (in_array($type, ['login_failed', 'failed_login', 'login_attempt'], true) && strtolower((string) ($entry['status'] ?? '')) !== 'success') { $failed++; $signals['repeated_authentication_failure'] = ($signals['repeated_authentication_failure'] ?? 0) + 1; }
            if (in_array($type, ['permission_denied', 'rbac_denied', 'access_denied', 'unauthorized_access'], true)) { $denied++; $signals['authorization_denial'] = ($signals['authorization_denial'] ?? 0) + 1; }
            if ($type === 'security_incident') { $incidents++; $signals['security_incident'] = ($signals['security_incident'] ?? 0) + 1; }
        }
        arsort($signals);
        return ['generated_at' => date('Y-m-d H:i:s'), 'failed_login_count' => $failed, 'permission_denied_count' => $denied, 'security_incident_count' => $incidents, 'top_signals' => array_map(static fn($k, $v): string => $k . ' (x' . $v . ')', array_keys($signals), array_values($signals))];
    }

    public function aiPayload(array $summary): array
    {
        return ['report_date' => date('Y-m-d'), 'failed_login_count' => (string) (int) ($summary['failed_login_count'] ?? 0), 'failed_login_identities' => [], 'permission_denied_count' => (string) (int) ($summary['permission_denied_count'] ?? 0), 'security_incident_count' => (string) (int) ($summary['security_incident_count'] ?? 0), 'top_signals' => array_slice(array_map('strval', (array) ($summary['top_signals'] ?? [])), 0, 8), 'follow_up_intent' => 'Prepare advisory verification steps only; do not lock accounts, change permissions, or modify logs.'];
    }
}
