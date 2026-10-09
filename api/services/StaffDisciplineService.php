<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Staff disciplinary handling — parallel to the students' discipline system.
 *
 * Used by Deputy Heads, Headteacher, School Administrator and Director. Cases
 * are numbered SDC-<year>-<seq>; a case that cannot be handled at the level it
 * was raised escalates to Deputy Head - Discipline while the senior authority
 * roles see and act on everything.
 */
final class StaffDisciplineService
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string,mixed> KPI + list payload for the workspace. */
    public function listCases(array $filters = []): array
    {
        $conditions = ['1=1'];
        $bindings = [];
        $map = [
            'status' => 'sdc.status = ?',
            'severity' => 'sdc.severity = ?',
            'category' => 'sdc.category = ?',
            'staff_id' => 'sdc.staff_id = ?',
            'department_id' => 'sdc.department_id = ?',
        ];
        foreach ($map as $field => $condition) {
            if (!empty($filters[$field])) {
                $conditions[] = $condition;
                $bindings[] = (string) $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = '(sdc.case_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR s.staff_no LIKE ? OR sdc.description LIKE ?)';
            $term = '%' . trim((string) $filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term, $term);
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'sdc.incident_date >= ?';
            $bindings[] = (string) $filters['from'];
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'sdc.incident_date <= ?';
            $bindings[] = (string) $filters['to'];
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare(
            "SELECT sdc.id, sdc.case_no, sdc.staff_id, sdc.department_id, sdc.category,
                    sdc.severity, sdc.incident_date, sdc.description, sdc.action_taken,
                    sdc.status, sdc.assigned_to, sdc.resolution, sdc.resolved_by, sdc.resolved_at,
                    sdc.created_at,
                    s.staff_no,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS staff_name,
                    d.name AS department_name,
                    ra.staff_no AS assigned_staff_no,
                    CONCAT_WS(' ', rap.first_name, rap.last_name) AS assigned_to_name,
                    CONCAT_WS(' ', rp.first_name, rp.last_name) AS resolved_by_name
             FROM staff_disciplinary_cases sdc
             JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = sdc.staff_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON d.id = sdc.department_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " ra ON ra.id = sdc.assigned_to
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rap ON rap.id = ra.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " rr ON rr.id = sdc.resolved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rp ON rp.id = rr.person_id
             WHERE $where
             ORDER BY sdc.incident_date DESC, sdc.id DESC
             LIMIT 500"
        );
        $stmt->execute($bindings);
        $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'cases' => $cases,
            'total' => count($cases),
            'stats' => $this->stats($filters),
        ];
    }

    public function getCase(int $caseId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT sdc.id, sdc.case_no, sdc.staff_id, sdc.department_id, sdc.category,
                    sdc.severity, sdc.incident_date, sdc.description, sdc.action_taken,
                    sdc.status, sdc.assigned_to, sdc.resolution, sdc.resolved_by, sdc.resolved_at,
                    sdc.created_at, sdc.updated_at,
                    s.staff_no,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS staff_name,
                    d.name AS department_name
             FROM staff_disciplinary_cases sdc
             JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = sdc.staff_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON d.id = sdc.department_id
             WHERE sdc.id = ?
             LIMIT 1"
        );
        $stmt->execute([$caseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed> */
    public function createCase(array $data, int $operatorId): array
    {
        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId <= 0) {
            throw new \InvalidArgumentException('staff_id is required');
        }
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            throw new \InvalidArgumentException('description is required');
        }
        $incidentDate = trim((string) ($data['incident_date'] ?? ''));
        if ($incidentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $incidentDate)) {
            throw new \InvalidArgumentException('incident_date must use YYYY-MM-DD');
        }

        $severity = strtolower(trim((string) ($data['severity'] ?? 'low')));
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new \InvalidArgumentException('severity must be low, medium, high or critical');
        }
        $category = strtolower(trim((string) ($data['category'] ?? 'other')));

        // A critical or high case raised here is escalated straight to Deputy
        // Head - Discipline; lower severities stay open for local handling.
        $status = in_array($severity, ['high', 'critical'], true) ? 'escalated' : 'open';

        $stmt = $this->db->prepare(
            "INSERT INTO staff_disciplinary_cases
                (staff_id, department_id, category, severity, incident_date, description, action_taken, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $staffId,
            !empty($data['department_id']) ? (int) $data['department_id'] : null,
            $category,
            $severity,
            $incidentDate,
            $description,
            !empty($data['action_taken']) ? trim((string) $data['action_taken']) : null,
            $status,
            $operatorId > 0 ? $operatorId : null,
        ]);
        $caseId = (int) $this->db->lastInsertId();

        $year = (int) date('Y');
        $caseNo = $this->db->prepare("SELECT fn_generate_staff_discipline_no(?)");
        $caseNo->execute([$year]);
        $number = (string) $caseNo->fetchColumn();
        $this->db->prepare("UPDATE staff_disciplinary_cases SET case_no = ? WHERE id = ?")
            ->execute([$number, $caseId]);

        return $this->getCase($caseId) ?? ['id' => $caseId, 'case_no' => $number, 'status' => $status];
    }

    public function updateCase(int $caseId, array $data, int $operatorId): ?array
    {
        $current = $this->getCase($caseId);
        if (!$current) {
            return null;
        }

        $sets = [];
        $params = [];
        foreach (['category', 'severity', 'description', 'action_taken', 'assigned_to', 'resolution'] as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "$field = ?";
                $params[] = $field === 'assigned_to' && !empty($data[$field])
                    ? (int) $data[$field]
                    : (trim((string) $data[$field]) !== '' ? trim((string) $data[$field]) : null);
            }
        }
        if (array_key_exists('incident_date', $data) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $data['incident_date'])) {
            $sets[] = 'incident_date = ?';
            $params[] = (string) $data['incident_date'];
        }
        if ($sets) {
            $params[] = $caseId;
            $this->db->prepare("UPDATE staff_disciplinary_cases SET " . implode(', ', $sets) . " WHERE id = ?")
                ->execute($params);
        }

        if (!empty($data['status'])) {
            $this->transitionStatus($caseId, (string) $data['status'], $operatorId);
        }

        return $this->getCase($caseId);
    }

    /**
     * Status transition with the escalation semantics: escalating assigns the
     * case to Deputy Head - Discipline; resolving/dismissing stamps the
     * resolver. The senior authority roles may act on any case.
     */
    public function transitionStatus(int $caseId, string $status, int $operatorId): ?array
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['open', 'in_review', 'escalated', 'resolved', 'dismissed'], true)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        $staffId = null;
        if ($status === 'escalated') {
            $stmt = $this->db->query(
                "SELECT s.id FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " 
                 WHERE LOWER(r.name) = 'deputy head - discipline' AND s.status='active'
                 LIMIT 1"
            );
            $staffId = (int) ($stmt?->fetchColumn() ?: 0) ?: null;
        }

        if ($status === 'resolved' || $status === 'dismissed') {
            $this->db->prepare(
                "UPDATE staff_disciplinary_cases
                 SET status = ?, resolved_by = ?, resolved_at = NOW()
                 WHERE id = ?"
            )->execute([$status, $operatorId > 0 ? $operatorId : null, $caseId]);
            return $this->getCase($caseId);
        }

        $this->db->prepare(
            "UPDATE staff_disciplinary_cases SET status = ?, assigned_to = COALESCE(?, assigned_to) WHERE id = ?"
        )->execute([$status, $staffId, $caseId]);

        return $this->getCase($caseId);
    }

    /** @return array<string,int> */
    public function stats(array $filters = []): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'open') AS open,
                SUM(status = 'in_review') AS in_review,
                SUM(status = 'escalated') AS escalated,
                SUM(status = 'resolved') AS resolved,
                SUM(status = 'dismissed') AS dismissed,
                SUM(severity = 'critical') AS critical,
                SUM(severity = 'high') AS high
             FROM staff_disciplinary_cases"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn($v) => (int) ($v ?? 0), $row);
    }

    /** Dropdown context: staff, departments. */
    public function context(): array
    {
        return [
            'staff' => $this->db->query(
                "SELECT s.staff_id AS id, s.staff_no, CONCAT_WS(' ', s.first_name, s.last_name) AS name
                 FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s
                 WHERE s.staff_status = 'active' AND s.person_id IS NOT NULL ORDER BY s.last_name, s.first_name"
            )->fetchAll(PDO::FETCH_ASSOC),
            'departments' => $this->db->query(
                "SELECT id, code, name FROM departments WHERE status='active' ORDER BY name"
            )->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
