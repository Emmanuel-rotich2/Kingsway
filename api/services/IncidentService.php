<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * School incidents management — events that happen in school reported by ANY
 * staff member (a student fainted, fire, an accident, a security concern…).
 *
 * DISTINCT from staff disciplinary and student indiscipline: an incident is a
 * school event. Any staff role may report one; it gets an incident number
 * INC-<year>-<seq>; it can be escalated to the level of authority the need
 * requires; relevant administrative staff handle and resolve it.
 *
 * Authority levels: 0 = reported/unassigned, 1 = deputy, 2 = headteacher,
 * 3 = director. Severity drives the default escalation path; the senior roles
 * always see every incident.
 */
final class IncidentService
{
    /** Severity -> the escalation level it demands. */
    private const SEVERITY_LEVEL = [
        'low' => 0,
        'medium' => 1,
        'high' => 2,
        'critical' => 3,
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<string,mixed> KPI + list payload for the workspace. */
    public function listIncidents(array $filters = [], array $scope = []): array
    {
        $conditions = ['1=1'];
        $bindings = [];
        $map = [
            'status' => 'i.status = ?',
            'severity' => 'i.severity = ?',
            'category' => 'i.category = ?',
            'escalation_level' => 'i.escalation_level = ?',
        ];
        foreach ($map as $field => $condition) {
            if (!empty($filters[$field]) || (isset($filters[$field]) && $filters[$field] === '0')) {
                $conditions[] = $condition;
                $bindings[] = (string) $filters[$field];
            }
        }
        if (!empty($scope['reported_by'])) {
            // A reporter without the view-all permission sees only their own
            // reports plus what has been assigned to them.
            $conditions[] = '(i.reported_by = ? OR i.assigned_to = ?)';
            $bindings[] = (int) $scope['reported_by'];
            $bindings[] = (int) $scope['assigned_staff_id'];
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(i.incident_no LIKE ? OR i.title LIKE ? OR i.description LIKE ? OR i.location LIKE ?)';
            $term = '%' . trim((string) $filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term);
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'i.occurred_at >= ?';
            $bindings[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'i.occurred_at <= ?';
            $bindings[] = (string) $filters['to'];
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare(
            "SELECT i.id, i.incident_no, i.title, i.category, i.severity, i.occurred_at,
                    i.location, i.description, i.immediate_action, i.reported_by,
                    i.status, i.escalation_level, i.assigned_to, i.resolution,
                    i.resolved_by, i.resolved_at, i.created_at,
                    CONCAT_WS(' ', rp.first_name, rp.last_name) AS reported_by_name,
                    ra.staff_no AS assigned_staff_no,
                    CONCAT_WS(' ', rap.first_name, rap.last_name) AS assigned_to_name,
                    CONCAT_WS(' ', rr.first_name, rr.last_name) AS resolved_by_name
             FROM school_incidents i
             LEFT JOIN users ru ON ru.id = i.reported_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rp ON rp.id = ru.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " ra ON ra.id = i.assigned_to
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rap ON rap.id = ra.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " rz ON rz.id = i.resolved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rr ON rr.id = rz.person_id
             WHERE $where
             ORDER BY i.occurred_at DESC, i.id DESC
             LIMIT 500"
        );
        $stmt->execute($bindings);
        $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'incidents' => $incidents,
            'total' => count($incidents),
            'stats' => $this->stats($scope),
        ];
    }

    public function getIncident(int $incidentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.incident_no, i.title, i.category, i.severity, i.occurred_at,
                    i.location, i.description, i.immediate_action, i.reported_by,
                    i.status, i.escalation_level, i.assigned_to, i.resolution,
                    i.resolved_by, i.resolved_at, i.created_at, i.updated_at,
                    CONCAT_WS(' ', rp.first_name, rp.last_name) AS reported_by_name
             FROM school_incidents i
             LEFT JOIN users ru ON ru.id = i.reported_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rp ON rp.id = ru.person_id
             WHERE i.id = ?
             LIMIT 1"
        );
        $stmt->execute([$incidentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed> */
    public function reportIncident(array $data, int $operatorId): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new \InvalidArgumentException('title is required');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw new \InvalidArgumentException('description is required');
        }
        $occurredAt = trim((string) ($data['occurred_at'] ?? ''));
        if ($occurredAt === '') {
            $occurredAt = date('Y-m-d H:i:s');
        }
        $severity = strtolower(trim((string) ($data['severity'] ?? 'low')));
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new \InvalidArgumentException('severity must be low, medium, high or critical');
        }
        $category = strtolower(trim((string) ($data['category'] ?? 'other')));

        // Severity drives the default escalation: a critical or high incident
        // goes straight to the authority level the need requires.
        $escalationLevel = self::SEVERITY_LEVEL[$severity] ?? 0;
        $status = $escalationLevel >= 2 ? 'escalated' : 'reported';

        $stmt = $this->db->prepare(
            "INSERT INTO school_incidents
                (title, category, severity, occurred_at, location, description,
                 immediate_action, reported_by, status, escalation_level)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $title,
            $category,
            $severity,
            $occurredAt,
            !empty($data['location']) ? trim((string) $data['location']) : null,
            $description,
            !empty($data['immediate_action']) ? trim((string) $data['immediate_action']) : null,
            $operatorId > 0 ? $operatorId : null,
            $status,
            $escalationLevel,
        ]);
        $incidentId = (int) $this->db->lastInsertId();

        $year = (int) date('Y');
        $caseNo = $this->db->prepare("SELECT fn_generate_incident_no(?)");
        $caseNo->execute([$year]);
        $number = (string) $caseNo->fetchColumn();
        $this->db->prepare("UPDATE school_incidents SET incident_no = ? WHERE id = ?")
            ->execute([$number, $incidentId]);

        return $this->getIncident($incidentId) ?? ['id' => $incidentId, 'incident_no' => $number, 'status' => $status];
    }

    public function updateIncident(int $incidentId, array $data, int $operatorId): ?array
    {
        $current = $this->getIncident($incidentId);
        if (!$current) {
            return null;
        }

        $sets = [];
        $params = [];
        foreach (['title', 'category', 'description', 'immediate_action', 'assigned_to', 'resolution'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $field === 'assigned_to' && !empty($data[$field])
                    ? (int) $data[$field]
                    : (trim((string) $data[$field]) !== '' ? trim((string) $data[$field]) : null);
            }
        }
        if (array_key_exists('severity', $data)) {
            $severity = strtolower(trim((string) $data['severity']));
            if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
                throw new \InvalidArgumentException('severity must be low, medium, high or critical');
            }
            $sets[] = 'severity = ?';
            $params[] = $severity;
            // A raised severity raises the escalation level to the level the
            // need requires; it never lowers an existing escalation.
            $level = self::SEVERITY_LEVEL[$severity] ?? 0;
            if ($level > (int) $current['escalation_level']) {
                $sets[] = 'escalation_level = ?';
                $params[] = $level;
            }
        }
        if (array_key_exists('occurred_at', $data) && trim((string) $data['occurred_at']) !== '') {
            $sets[] = 'occurred_at = ?';
            $params[] = trim((string) $data['occurred_at']);
        }
        if ($sets) {
            $params[] = $incidentId;
            $this->db->prepare("UPDATE school_incidents SET " . implode(', ', $sets) . " WHERE id = ?")
                ->execute($params);
        }

        if (!empty($data['status'])) {
            $this->transitionStatus($incidentId, (string) $data['status'], $operatorId);
        }

        return $this->getIncident($incidentId);
    }

    /**
     * Status transition with the escalation semantics.
     * escalate   -> raises the escalation level to the level of authority the
     *               need requires (1 deputy, 2 headteacher, 3 director) and
     *               marks the incident escalated.
     * acknowledge/review -> acknowledges and assigns the handler.
     * resolve/close      -> stamps the resolver.
     */
    public function transitionStatus(int $incidentId, string $status, int $operatorId, int $toLevel = 0): ?array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['reported', 'acknowledged', 'escalated', 'in_review', 'resolved', 'closed'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        if ($status === 'escalated') {
            $current = $this->getIncident($incidentId);
            $level = $toLevel > 0
                ? $toLevel
                : max(1, (int) ($current['escalation_level'] ?? 0));
            if ($level < 1 || $level > 3) {
                throw new \InvalidArgumentException('Escalation level must be 1 (deputy), 2 (headteacher) or 3 (director)');
            }
            $this->db->prepare(
                "UPDATE school_incidents SET status = 'escalated', escalation_level = ? WHERE id = ?"
            )->execute([$level, $incidentId]);
            return $this->getIncident($incidentId);
        }

        if ($status === 'resolved' || $status === 'closed') {
            $this->db->prepare(
                "UPDATE school_incidents
                 SET status = ?, resolved_by = ?, resolved_at = NOW()
                 WHERE id = ?"
            )->execute([$status, $operatorId > 0 ? $operatorId : null, $incidentId]);
            return $this->getIncident($incidentId);
        }

        $this->db->prepare(
            "UPDATE school_incidents SET status = ? WHERE id = ?"
        )->execute([$status, $incidentId]);

        return $this->getIncident($incidentId);
    }

    /** @return array<string,int> */
    public function stats(array $scope = []): array
    {
        $where = '1=1';
        $bindings = [];
        if (!empty($scope['reported_by'])) {
            $where = '(reported_by = ? OR assigned_to = ?)';
            $bindings = [(int) $scope['reported_by'], (int) $scope['assigned_staff_id']];
        }
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'reported') AS reported,
                SUM(status = 'acknowledged') AS acknowledged,
                SUM(status = 'escalated') AS escalated,
                SUM(status = 'in_review') AS in_review,
                SUM(status = 'resolved') AS resolved,
                SUM(status = 'closed') AS closed,
                SUM(severity = 'critical') AS critical,
                SUM(severity = 'high') AS high
             FROM school_incidents WHERE $where"
        );
        $stmt->execute($bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($v) => (int) ($v ?? 0), $row);
    }
}
