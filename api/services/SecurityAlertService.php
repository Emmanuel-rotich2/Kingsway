<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;

/** Governed persistence and recipient lookup for machine generated security alerts. */
final class SecurityAlertService
{
    public function activeAdministratorRecipients(\PDO $db): array
    {
        $stmt = $db->prepare("SELECT u.email, u.first_name, u.last_name
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            WHERE r.name = 'System Administrator'
              AND u.status = 'active'
              AND u.email IS NOT NULL
              AND u.email != ''");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function record(array $alert): void
    {
        FileLogger::write('security', [
            'type' => $alert['type'],
            'severity' => $alert['severity'],
            'source' => 'node_threat_detector',
            'event_time' => $alert['timestamp'],
            'ip' => $alert['details']['ip'] ?? null,
            'count' => $alert['details']['count'] ?? 0,
            'topics' => $alert['details']['topics'] ?? [],
        ], $alert['severity'] === 'critical' ? 'critical' : 'warning');
    }
}
