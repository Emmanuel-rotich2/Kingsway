<?php

namespace App\API\Modules\security;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;

/**
 * The school security office: the gate desk and security incidents.
 *
 *  Gate desk (Security staff; leadership may also use it)
 *   - sign visitors in and out (or turn them away), with host, learner,
 *     badge, vehicle and items declared
 *   - log gate movements (vehicles, deliveries, school buses, after-hours)
 *   - stamp learners out and back in against an APPROVED exeat/permission
 *  Security incidents
 *   - any staff member reports; the gate team and leadership see all
 *   - Security staff respond, contain, add notes and escalate
 *   - leadership (Director, School Administrator, Headteacher, Deputy
 *     Discipline) also resolve, close and reopen
 * Learner details shown at the gate are the minimum needed to verify a
 * pick-up: name, admission number, class, photo and day/boarding.
 */
final class SecurityGateService
{
    public const GATE_ROLES = [33];
    public const OVERSIGHT_ROLES = [3, 4, 5, 63];
    private const PURPOSES = ['parent_visit', 'learner_pickup', 'delivery', 'contractor', 'official', 'interview', 'meeting', 'other'];
    private const MOVEMENTS = ['vehicle', 'delivery', 'school_bus', 'staff_after_hours', 'learner_exeat', 'occurrence', 'other'];
    private const CATEGORIES = ['trespass', 'suspicious_person', 'suspicious_vehicle', 'theft', 'break_in', 'fight', 'injury', 'fire', 'unauthorised_exit', 'missing_learner', 'property_damage', 'threat', 'power_water_failure', 'other'];
    private const SEVERITIES = ['low', 'medium', 'high', 'critical'];
    /** action => status it moves the incident to (null = no change) */
    private const ACTIONS = [
        'note' => null, 'responding' => 'responding', 'contained' => 'contained', 'escalated' => 'escalated',
        'police_notified' => null, 'parent_notified' => null, 'resolved' => 'resolved', 'closed' => 'closed', 'reopened' => 'responding',
    ];
    private const LEADERSHIP_ONLY_ACTIONS = ['resolved', 'closed', 'reopened'];

    public function __construct(private PDO $db) {}

    public static function canUseGate(array $roleIds): bool
    {
        return (bool) array_intersect(array_merge(self::GATE_ROLES, self::OVERSIGHT_ROLES), $roleIds);
    }

    private function isOversight(array $roleIds): bool
    {
        return (bool) array_intersect(self::OVERSIGHT_ROLES, $roleIds);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function clip($value, int $max): ?string
    {
        $v = trim((string) ($value ?? ''));
        return $v === '' ? null : mb_substr($v, 0, $max);
    }

    // ================================================================ summary
    public function summary(): array
    {
        $today = date('Y-m-d');
        $count = function (string $sql, array $p = []): int {
            $s = $this->db->prepare($sql);
            $s->execute($p);
            return (int) $s->fetchColumn();
        };
        return [
            'on_site' => $count("SELECT COUNT(*) FROM visitor_visits WHERE status = 'on_site'"),
            'visitors_today' => $count('SELECT COUNT(*) FROM visitor_visits WHERE checked_in_at >= ?', [$today . ' 00:00:00']),
            'denied_today' => $count("SELECT COUNT(*) FROM visitor_visits WHERE status = 'denied' AND checked_in_at >= ?", [$today . ' 00:00:00']),
            'movements_today' => $count('SELECT COUNT(*) FROM gate_movements WHERE recorded_at >= ?', [$today . ' 00:00:00']),
            'open_incidents' => $count("SELECT COUNT(*) FROM security_incidents WHERE status NOT IN ('resolved','closed')"),
            'exeats_out' => $count("SELECT COUNT(*) FROM student_permissions WHERE status = 'approved' AND checked_out_at IS NOT NULL AND checked_in_at IS NULL"),
        ];
    }

    // =============================================================== visitors
    public function listVisits(array $f): array
    {
        $where = [];
        $p = [];
        if (in_array($f['status'] ?? '', ['on_site', 'left', 'denied'], true)) { $where[] = 'status = ?'; $p[] = $f['status']; }
        if (!empty($f['from'])) { $where[] = 'checked_in_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $where[] = 'checked_in_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (in_array($f['purpose'] ?? '', self::PURPOSES, true)) { $where[] = 'purpose = ?'; $p[] = $f['purpose']; }
        if (!empty($f['q'])) {
            $where[] = '(visitor_name LIKE ? OR id_number LIKE ? OR phone LIKE ? OR vehicle_reg LIKE ? OR badge_no LIKE ?)';
            for ($i = 0; $i < 5; $i++) $p[] = '%' . $f['q'] . '%';
        }
        $stmt = $this->db->prepare('SELECT * FROM visitor_visits' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY checked_in_at DESC LIMIT 500');
        $stmt->execute($p);
        return $this->decorateVisits($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function checkIn(array $data, int $userId): array
    {
        $name = $this->clip($data['visitor_name'] ?? '', 150);
        if (!$name) throw new RuntimeException("Enter the visitor's name.", 400);
        $purpose = (string) ($data['purpose'] ?? 'other');
        if (!in_array($purpose, self::PURPOSES, true)) $purpose = 'other';
        $deny = !empty($data['deny']);
        $denyReason = $this->clip($data['denial_reason'] ?? '', 255);
        if ($deny && !$denyReason) throw new RuntimeException('Say why the visitor was turned away.', 400);
        $studentId = (int) ($data['student_id'] ?? 0) ?: null;
        if ($purpose === 'learner_pickup' && !$studentId) throw new RuntimeException('Choose the learner being picked up.', 400);
        $count = max(1, min(50, (int) ($data['people_count'] ?? 1)));
        $now = $this->now();
        $stmt = $this->db->prepare(
            'INSERT INTO visitor_visits
                (visitor_name, id_number, phone, organisation, purpose, purpose_detail, host_staff_id, host_note, student_id,
                 badge_no, vehicle_reg, people_count, items_declared, status, denial_reason, notes, checked_in_at, checked_in_by,
                 checked_out_at, checked_out_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name, $this->clip($data['id_number'] ?? '', 40), $this->clip($data['phone'] ?? '', 30), $this->clip($data['organisation'] ?? '', 150),
            $purpose, $this->clip($data['purpose_detail'] ?? '', 255), (int) ($data['host_staff_id'] ?? 0) ?: null, $this->clip($data['host_note'] ?? '', 150),
            $studentId, $this->clip($data['badge_no'] ?? '', 30), $this->vehicle($data['vehicle_reg'] ?? ''), $count,
            $this->clip($data['items_declared'] ?? '', 255), $deny ? 'denied' : 'on_site', $deny ? $denyReason : null,
            $this->clip($data['notes'] ?? '', 500), $now, $userId, $deny ? $now : null, $deny ? $userId : null,
        ]);
        $id = (int) $this->db->lastInsertId();
        if (!$deny && $this->vehicle($data['vehicle_reg'] ?? '')) {
            $this->recordMovement(['direction' => 'in', 'movement_type' => $purpose === 'delivery' ? 'delivery' : 'vehicle', 'vehicle_reg' => $data['vehicle_reg'], 'person_name' => $name, 'description' => 'Visitor vehicle', 'visitor_visit_id' => $id], $userId);
        }
        return ['id' => $id, 'status' => $deny ? 'denied' : 'on_site'];
    }

    public function checkOut(int $id, int $userId, ?string $note = null): array
    {
        $row = $this->one('SELECT * FROM visitor_visits WHERE id = ?', [$id]);
        if (!$row) throw new RuntimeException('Visit not found.', 404);
        if ($row['status'] !== 'on_site') throw new RuntimeException('This visitor has already left.', 409);
        $this->db->prepare("UPDATE visitor_visits SET status = 'left', checked_out_at = ?, checked_out_by = ?, notes = COALESCE(?, notes) WHERE id = ?")
            ->execute([$this->now(), $userId, $this->clip($note, 500), $id]);
        if (!empty($row['vehicle_reg'])) {
            $this->recordMovement(['direction' => 'out', 'movement_type' => $row['purpose'] === 'delivery' ? 'delivery' : 'vehicle', 'vehicle_reg' => $row['vehicle_reg'], 'person_name' => $row['visitor_name'], 'description' => 'Visitor vehicle', 'visitor_visit_id' => $id], $userId);
        }
        return ['id' => $id, 'status' => 'left'];
    }

    private function decorateVisits(array $rows): array
    {
        $students = $this->studentCards(array_column($rows, 'student_id'));
        $staff = $this->staffNames(array_column($rows, 'host_staff_id'));
        $now = time();
        foreach ($rows as &$r) {
            $r['student'] = $students[(int) $r['student_id']] ?? null;
            $r['host_name'] = $staff[(int) $r['host_staff_id']] ?? $r['host_note'];
            $r['minutes_on_site'] = $r['status'] === 'on_site' ? (int) floor(($now - strtotime($r['checked_in_at'])) / 60) : null;
        }
        unset($r);
        return $rows;
    }

    // ============================================================= movements
    public function listMovements(array $f): array
    {
        $where = [];
        $p = [];
        if (!empty($f['from'])) { $where[] = 'recorded_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $where[] = 'recorded_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (in_array($f['movement_type'] ?? '', self::MOVEMENTS, true)) { $where[] = 'movement_type = ?'; $p[] = $f['movement_type']; }
        if (!empty($f['q'])) { $where[] = '(vehicle_reg LIKE ? OR person_name LIKE ? OR description LIKE ?)'; for ($i = 0; $i < 3; $i++) $p[] = '%' . $f['q'] . '%'; }
        $stmt = $this->db->prepare('SELECT * FROM gate_movements' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY recorded_at DESC LIMIT 500');
        $stmt->execute($p);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = $this->userNames(array_column($rows, 'recorded_by'));
        foreach ($rows as &$r) $r['recorded_by_name'] = $users[(int) $r['recorded_by']] ?? null;
        unset($r);
        return $rows;
    }

    public function recordMovement(array $data, int $userId): array
    {
        $direction = ($data['direction'] ?? '') === 'out' ? 'out' : 'in';
        $type = (string) ($data['movement_type'] ?? 'other');
        if (!in_array($type, self::MOVEMENTS, true)) $type = 'other';
        $vehicle = $this->vehicle($data['vehicle_reg'] ?? '');
        $person = $this->clip($data['person_name'] ?? '', 150);
        $description = $this->clip($data['description'] ?? '', 500);
        if (!$vehicle && !$person && !$description) throw new RuntimeException('Record the vehicle, the person or what happened.', 400);
        $this->db->prepare(
            'INSERT INTO gate_movements (direction, movement_type, vehicle_reg, person_name, description, student_permission_id, visitor_visit_id, recorded_at, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$direction, $type, $vehicle, $person, $description, (int) ($data['student_permission_id'] ?? 0) ?: null, (int) ($data['visitor_visit_id'] ?? 0) ?: null, $this->now(), $userId]);
        return ['id' => (int) $this->db->lastInsertId()];
    }

    private function vehicle($reg): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', ' ', trim((string) $reg)));
        return $v === '' ? null : mb_substr($v, 0, 20);
    }

    // ================================================================ exeats
    /** Approved exeats/permissions for today: who may leave, who is out, who is back. */
    public function exeatsToday(): array
    {
        $today = date('Y-m-d');
        $stmt = $this->db->prepare(
            "SELECT id, student_id, permission_type_id, start_date, start_time, end_date, end_time, reason, expected_return,
                    checked_out_at, checked_in_at
               FROM student_permissions
              WHERE status = 'approved'
                AND ((start_date <= ? AND end_date >= ?) OR (checked_out_at IS NOT NULL AND checked_in_at IS NULL))
              ORDER BY start_date, start_time
              LIMIT 300"
        );
        $stmt->execute([$today, $today]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $students = $this->studentCards(array_column($rows, 'student_id'));
        $types = $this->lookup('student_permission_types', 'id', 'name', array_column($rows, 'permission_type_id'), false);
        foreach ($rows as &$r) {
            $r['student'] = $students[(int) $r['student_id']] ?? null;
            $r['permission_type'] = $types[(int) $r['permission_type_id']] ?? null;
            $r['gate_state'] = $r['checked_in_at'] ? 'returned' : ($r['checked_out_at'] ? 'out' : 'may_leave');
            $r['overdue'] = $r['gate_state'] === 'out' && $r['expected_return'] && strtotime($r['expected_return']) < time();
        }
        unset($r);
        return $rows;
    }

    /** Stamp a learner out of, or back into, school against an approved permission. */
    public function exeatGate(int $permissionId, string $direction, int $userId): array
    {
        $row = $this->one('SELECT id, student_id, status, checked_out_at, checked_in_at FROM student_permissions WHERE id = ?', [$permissionId]);
        if (!$row) throw new RuntimeException('Permission not found.', 404);
        if ($row['status'] !== 'approved') throw new RuntimeException('Only an approved permission lets a learner leave.', 409);
        $card = $this->studentCards([$row['student_id']])[(int) $row['student_id']] ?? [];
        $name = $card['full_name'] ?? 'Learner';
        if ($direction === 'out') {
            if ($row['checked_out_at']) throw new RuntimeException('This learner is already signed out.', 409);
            $this->db->prepare('UPDATE student_permissions SET checked_out_at = ?, checked_out_by = ? WHERE id = ?')->execute([$this->now(), $userId, $permissionId]);
        } else {
            if (!$row['checked_out_at']) throw new RuntimeException('This learner was not signed out.', 409);
            if ($row['checked_in_at']) throw new RuntimeException('This learner is already back.', 409);
            $this->db->prepare("UPDATE student_permissions SET checked_in_at = ?, checked_in_by = ?, status = 'completed' WHERE id = ?")->execute([$this->now(), $userId, $permissionId]);
        }
        $this->recordMovement(['direction' => $direction === 'out' ? 'out' : 'in', 'movement_type' => 'learner_exeat', 'person_name' => $name, 'description' => 'Exeat / permission', 'student_permission_id' => $permissionId], $userId);
        return ['id' => $permissionId, 'direction' => $direction];
    }

    /** Minimal learner lookup for verifying a pick-up at the gate. */
    public function learnerLookup(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $stmt = $this->db->prepare(
            "SELECT DISTINCT student_id FROM " . ReadReplicaService::qualifiedRef('student_directory') . "
              WHERE enrollment_status = 'active' AND (full_name LIKE ? OR admission_no LIKE ?)
              LIMIT 15"
        );
        $stmt->execute(['%' . $q . '%', '%' . $q . '%']);
        return array_values($this->studentCards($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    }

    /** Staff a visitor may be seeing: name and position only. */
    public function hostLookup(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $stmt = $this->db->prepare(
            "SELECT DISTINCT staff_id, full_name, position FROM " . ReadReplicaService::qualifiedRef('staff_directory') . "
              WHERE staff_status = 'active' AND full_name LIKE ? ORDER BY full_name LIMIT 15"
        );
        $stmt->execute(['%' . $q . '%']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ============================================================= incidents
    public function listIncidents(array $f, int $userId, array $roleIds): array
    {
        $where = [];
        $p = [];
        if (!self::canUseGate($roleIds)) { $where[] = 'reported_by = ?'; $p[] = $userId; }
        if (($f['status'] ?? '') === 'open') { $where[] = "status NOT IN ('resolved','closed')"; }
        elseif (in_array($f['status'] ?? '', ['reported', 'responding', 'contained', 'escalated', 'resolved', 'closed'], true)) { $where[] = 'status = ?'; $p[] = $f['status']; }
        if (in_array($f['severity'] ?? '', self::SEVERITIES, true)) { $where[] = 'severity = ?'; $p[] = $f['severity']; }
        if (in_array($f['category'] ?? '', self::CATEGORIES, true)) { $where[] = 'category = ?'; $p[] = $f['category']; }
        if (!empty($f['from'])) { $where[] = 'occurred_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (!empty($f['to'])) { $where[] = 'occurred_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        $stmt = $this->db->prepare('SELECT * FROM security_incidents' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY FIELD(status,'reported','responding','escalated','contained','resolved','closed'), FIELD(severity,'critical','high','medium','low'), occurred_at DESC LIMIT 500");
        $stmt->execute($p);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = $this->userNames(array_merge(array_column($rows, 'reported_by'), array_column($rows, 'responder_id')));
        foreach ($rows as &$r) {
            $r['reported_by_name'] = $users[(int) $r['reported_by']] ?? null;
            $r['responder_name'] = $users[(int) $r['responder_id']] ?? null;
        }
        unset($r);
        return $rows;
    }

    public function incident(int $id, int $userId, array $roleIds): array
    {
        $row = $this->one('SELECT * FROM security_incidents WHERE id = ?', [$id]);
        if (!$row || (!self::canUseGate($roleIds) && (int) $row['reported_by'] !== $userId)) throw new RuntimeException('Incident not found.', 404);
        $stmt = $this->db->prepare('SELECT id, action_type, notes, acted_by, acted_at FROM security_incident_actions WHERE incident_id = ? ORDER BY acted_at, id');
        $stmt->execute([$id]);
        $actions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = $this->userNames(array_merge([$row['reported_by'], $row['responder_id']], array_column($actions, 'acted_by')));
        foreach ($actions as &$a) $a['acted_by_name'] = $users[(int) $a['acted_by']] ?? null;
        unset($a);
        $row['reported_by_name'] = $users[(int) $row['reported_by']] ?? null;
        $row['responder_name'] = $users[(int) $row['responder_id']] ?? null;
        $row['actions'] = $actions;
        $row['can_respond'] = self::canUseGate($roleIds);
        $row['can_close'] = $this->isOversight($roleIds);
        return $row;
    }

    public function reportIncident(array $data, int $userId): array
    {
        $category = (string) ($data['category'] ?? 'other');
        if (!in_array($category, self::CATEGORIES, true)) $category = 'other';
        $severity = (string) ($data['severity'] ?? 'medium');
        if (!in_array($severity, self::SEVERITIES, true)) $severity = 'medium';
        $location = $this->clip($data['location'] ?? '', 200);
        $description = $this->clip($data['description'] ?? '', 5000);
        $occurred = trim((string) ($data['occurred_at'] ?? ''));
        if (!$location || !$description || $occurred === '' || strtotime($occurred) === false) {
            throw new RuntimeException('Say where and when it happened, and describe it.', 400);
        }
        $this->db->beginTransaction();
        try {
            $year = date('Y');
            $seq = (int) $this->one('SELECT COUNT(*) AS n FROM security_incidents WHERE reference_no LIKE ?', ['SEC-' . $year . '-%'])['n'] + 1;
            $ref = sprintf('SEC-%s-%04d', $year, $seq);
            $this->db->prepare(
                'INSERT INTO security_incidents (reference_no, category, severity, location, occurred_at, description, persons_involved, immediate_action, status, reported_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'reported\', ?)'
            )->execute([$ref, $category, $severity, $location, date('Y-m-d H:i:s', strtotime($occurred)), $description,
                $this->clip($data['persons_involved'] ?? '', 500), $this->clip($data['immediate_action'] ?? '', 1000), $userId]);
            $id = (int) $this->db->lastInsertId();
            $this->db->prepare('INSERT INTO security_incident_actions (incident_id, action_type, notes, acted_by, acted_at) VALUES (?, \'note\', ?, ?, ?)')
                ->execute([$id, 'Reported.', $userId, $this->now()]);
            $this->db->commit();
            return ['id' => $id, 'reference_no' => $ref, 'severity' => $severity];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function actOnIncident(int $id, array $data, int $userId, array $roleIds): array
    {
        if (!self::canUseGate($roleIds)) throw new RuntimeException('Only the security team and school leadership respond to incidents.', 403);
        $row = $this->one('SELECT id, status FROM security_incidents WHERE id = ?', [$id]);
        if (!$row) throw new RuntimeException('Incident not found.', 404);
        $action = (string) ($data['action_type'] ?? 'note');
        if (!array_key_exists($action, self::ACTIONS)) throw new RuntimeException('Unknown action.', 400);
        if (in_array($action, self::LEADERSHIP_ONLY_ACTIONS, true) && !$this->isOversight($roleIds)) {
            throw new RuntimeException('Resolving, closing and reopening are done by school leadership.', 403);
        }
        $notes = $this->clip($data['notes'] ?? '', 1000);
        if (in_array($action, ['note', 'escalated', 'resolved'], true) && !$notes) throw new RuntimeException('Add a note describing what was done.', 400);
        if (in_array($row['status'], ['closed'], true) && $action !== 'reopened') throw new RuntimeException('This incident is closed. Reopen it first.', 409);
        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $this->db->prepare('INSERT INTO security_incident_actions (incident_id, action_type, notes, acted_by, acted_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$id, $action, $notes, $userId, $now]);
            $sets = [];
            $p = [];
            if (self::ACTIONS[$action]) { $sets[] = 'status = ?'; $p[] = self::ACTIONS[$action]; }
            if ($action === 'responding') { $sets[] = 'responder_id = ?'; $p[] = $userId; }
            if ($action === 'resolved') { $sets[] = 'resolution = ?'; $p[] = $notes; $sets[] = 'resolved_at = ?'; $p[] = $now; }
            if ($action === 'reopened') { $sets[] = 'resolved_at = NULL'; }
            if ($action === 'police_notified' && !empty($data['police_ob_no'])) { $sets[] = 'police_ob_no = ?'; $p[] = $this->clip($data['police_ob_no'], 40); }
            if ($sets) {
                $p[] = $id;
                $this->db->prepare('UPDATE security_incidents SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($p);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
        return $this->incident($id, $userId, $roleIds);
    }

    // =============================================================== lookups
    private function one(string $sql, array $p): ?array
    {
        $s = $this->db->prepare($sql);
        $s->execute($p);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /** @return array<int,array> student_id => minimal gate card */
    private function studentCards(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            'SELECT student_id, full_name, admission_no, class_name, stream_name, photo_url, student_type_code, enrollment_status
               FROM ' . ReadReplicaService::qualifiedRef('student_directory') . " WHERE student_id IN ({$in})
              ORDER BY (enrollment_status = 'active') DESC"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $id = (int) $r['student_id'];
            if (isset($out[$id])) continue;
            $out[$id] = [
                'id' => $id, 'full_name' => $r['full_name'], 'admission_no' => $r['admission_no'],
                'class_name' => trim(($r['class_name'] ?? '') . ' ' . ($r['stream_name'] ?? '')),
                'photo_url' => $r['photo_url'], 'boarding' => $r['student_type_code'] === 'BOARD' ? 'Boarder' : 'Day scholar',
            ];
        }
        return $out;
    }

    private function staffNames(array $ids): array
    {
        return $this->lookup('staff_directory', 'staff_id', 'full_name', $ids);
    }

    private function userNames(array $ids): array
    {
        return $this->lookup('staff_directory', 'user_id', 'full_name', $ids);
    }

    private function lookup(string $source, string $idCol, string $labelCol, array $ids, bool $projection = true): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $table = $projection ? ReadReplicaService::qualifiedRef($source) : $source;
        $stmt = $this->db->prepare("SELECT {$idCol}, {$labelCol} FROM {$table} WHERE {$idCol} IN ({$in})");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r[$idCol]] = (string) $r[$labelCol];
        return $out;
    }
}
