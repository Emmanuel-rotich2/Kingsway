<?php
declare(strict_types=1);

namespace App\API\Modules\chaplaincy;

use App\API\Includes\BaseAPI;
use App\API\Modules\students\StudentLeadershipService;
use Exception;
use PDO;

/**
 * ChaplaincyAPI
 *
 * Business logic for the Chaplaincy (Campus Ministry) department — Phase A:
 * the department team and its borrowed members and parent/community volunteers.
 *
 * Schema (migration 228):
 *   - chaplaincy_team_roles      ministry role lookup
 *   - chaplaincy_volunteers      parent/community volunteers (no staff record)
 *   - departments + staff_department_assignments  department + borrowed staff
 *
 * The Chaplain heads the Chaplaincy department (departments.head_id / code 'CHAP');
 * staff members are borrowed from teaching/administration via
 * staff_department_assignments; parents/community helpers are volunteers.
 */
class ChaplaincyAPI extends BaseAPI
{
    private const CHAPLAINCY_CODE = 'CHAP';

    /** leadership_positions id for the student "Spiritual / Ministry Group Leader". */
    private const STUDENT_MINISTRY_POSITION_ID = 51;

    /** Role that is appointed by the School Administrator, never assignable here. */
    private const DEPT_HEAD_ROLE_CODE = 'DEPT_HEAD';

    public function __construct()
    {
        parent::__construct('chaplaincy');
    }

    /**
     * The Chaplaincy department id (creates it on demand if missing).
     */
    private function departmentId(): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM departments WHERE code = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([self::CHAPLAINCY_CODE]);
        $id = (int) $stmt->fetchColumn();
        if (!$id) {
            $this->db->prepare(
                "INSERT INTO departments (name, code, description, status)
                 VALUES ('Chaplaincy', 'CHAP',
                         'Spiritual leadership and campus ministry (SDA)', 'active')"
            )->execute();
            $id = (int) $this->db->lastInsertId();
        }
        return $id;
    }

    /**
     * List ministry team roles.
     */
    public function listTeamRoles(): array
    {
        $stmt = $this->db->query(
            "SELECT id, code, name, description, applies_to, is_active
             FROM chaplaincy_team_roles
             ORDER BY is_active DESC, id"
        );
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Team roster = borrowed staff members assigned to the Chaplaincy
     * department, plus the department head.
     */
    public function getTeam(): array
    {
        $deptId = $this->departmentId();

        $stmt = $this->db->prepare(
            "SELECT sa.id AS assignment_id,
                    s.id AS staff_id, s.staff_no,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS staff_name,
                    sa.role AS team_role, sa.effective_from, sa.effective_to,
                    sa.department_id,
                    d.name AS primary_department
             FROM staff_department_assignments sa
             JOIN staff s ON s.id = sa.staff_id
             JOIN persons p ON p.id = s.person_id
             -- The member's home department is their earliest ACTIVE assignment
             -- OUTSIDE the Chaplaincy dept. A plain equality join would match
             -- both the home row and the team row (and any other open dept),
             -- duplicating the roster; the MIN(id) subquery yields exactly one.
             LEFT JOIN staff_department_assignments home_pd
                    ON home_pd.id = (
                        SELECT MIN(pd.id)
                        FROM staff_department_assignments pd
                        WHERE pd.staff_id = s.id
                          AND pd.department_id <> sa.department_id
                          AND (pd.effective_to IS NULL OR pd.effective_to >= CURDATE())
                    )
             LEFT JOIN departments d ON d.id = home_pd.department_id
             WHERE sa.department_id = ?
               AND (sa.effective_to IS NULL OR sa.effective_to >= CURDATE())
             ORDER BY p.first_name, p.last_name"
        );
        $stmt->execute([$deptId]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->successResponse([
            'department_id' => $deptId,
            'department_name' => 'Chaplaincy',
            'head' => $this->departmentHead($deptId),
            'members' => $members,
        ]);
    }

    private function departmentHead(int $deptId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT d.head_id AS staff_id,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS staff_name,
                    s.staff_no
             FROM departments d
             LEFT JOIN staff s ON s.id = d.head_id
             LEFT JOIN persons p ON p.id = s.person_id
             WHERE d.id = ?"
        );
        $stmt->execute([$deptId]);
        $head = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($head && $head['staff_id']) ? $head : null;
    }

    /**
     * Assign a staff member (borrowed from another department) to the
     * Chaplaincy team with a ministry role.
     */
    public function addMember(array $data): array
    {
        $role      = trim((string) ($data['team_role'] ?? ''));
        $effFrom   = $data['effective_from'] ?? date('Y-m-d');
        $effTo     = $data['effective_to'] ?? null;
        $deptId    = $this->departmentId();

        // Support bulk assignment: accept either a single staff_id or an
        // array of staff_ids so the Chaplain (or School Admin) can select
        // and assign many team members in one submit.
        if (isset($data['staff_ids']) && is_array($data['staff_ids'])) {
            $staffIds = array_values(array_unique(array_map('intval', $data['staff_ids'])));
        } else {
            $staffIds = [(int) ($data['staff_id'] ?? 0)];
        }
        $staffIds = array_values(array_filter($staffIds, fn($id) => $id > 0));

        if (!$staffIds) {
            return $this->errorResponse('staff_ids is required', 422);
        }

        // Validate every staff record exists.
        $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
        $check = $this->db->prepare("SELECT id FROM staff WHERE id IN ($placeholders)");
        $check->execute($staffIds);
        $valid = array_map('intval', $check->fetchAll(PDO::FETCH_COLUMN));
        $invalid = array_values(array_diff($staffIds, $valid));
        if ($invalid) {
            return $this->errorResponse('Unknown staff member(s) supplied', 422, ['invalid_staff_ids' => $invalid]);
        }

        // Insert assignment (borrowed membership). The unique constraint is
        // (staff_id, department_id, effective_from); if the same person is
        // re-assigned with a new start date a second row is created.
        // NOTE: staff_department_assignments.id is NOT auto-increment; it is
        // derived from MAX(id)+1, same convention as the staff services.
        $ins = $this->db->prepare(
            "INSERT INTO staff_department_assignments
                (id, staff_id, department_id, role, effective_from, effective_to)
             VALUES
                (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE role = VALUES(role), effective_to = VALUES(effective_to)"
        );
        $assigned = [];
        foreach ($staffIds as $staffId) {
            $nextId = (int) $this->db->query(
                'SELECT COALESCE(MAX(id), 0) + 1 FROM staff_department_assignments'
            )->fetchColumn();
            $ins->execute([$nextId, $staffId, $deptId, $role, $effFrom, $effTo]);
            $this->logAction('chaplaincy_team_add_member', $staffId, 'Assigned staff to Chaplaincy team');
            $assigned[] = $staffId;
        }

        return $this->successResponse(['staff_ids' => $assigned, 'department_id' => $deptId]);
    }

    /**
     * Update a team member's ministry role / effective range.
     */
    public function updateMember(array $data): array
    {
        $assignmentId = (int) ($data['assignment_id'] ?? 0);
        if (!$assignmentId) {
            return $this->errorResponse('assignment_id is required', 422);
        }
        $role   = trim((string) ($data['team_role'] ?? ''));
        $effTo  = $data['effective_to'] ?? null;

        $upd = $this->db->prepare(
            "UPDATE staff_department_assignments
             SET role = ?, effective_to = ?
             WHERE id = ?"
        );
        $upd->execute([$role, $effTo, $assignmentId]);
        return $this->successResponse(['assignment_id' => $assignmentId]);
    }

    /**
     * List parent/community volunteers.
     */
    public function listVolunteers(array $filters = []): array
    {
        $where = ['v.is_active = 1'];
        $params = [];
        if (!empty($filters['role_id'])) {
            $where[] = 'v.role_id = ?';
            $params[] = (int) $filters['role_id'];
        }
        if (!empty($filters['search'])) {
            $where[] = 'v.full_name LIKE ?';
            $params[] = '%' . $filters['search'] . '%';
        }

        $stmt = $this->db->prepare(
            "SELECT v.id, v.full_name, v.contact_phone, v.contact_email,
                    v.police_clearance_verified, v.notes, v.is_active,
                    r.name AS role_name,
                    v.linked_student_id
             FROM chaplaincy_volunteers v
             LEFT JOIN chaplaincy_team_roles r ON r.id = v.role_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY v.full_name"
        );
        $stmt->execute($params);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Register a parent/community volunteer.
     */
    public function addVolunteer(array $data): array
    {
        $fullName  = trim((string) ($data['full_name'] ?? ''));
        if ($fullName === '') {
            return $this->errorResponse('full_name is required', 422);
        }
        $roleId = !empty($data['role_id']) ? (int) $data['role_id'] : null;
        $userId = $this->getCurrentUserId();

        $ins = $this->db->prepare(
            "INSERT INTO chaplaincy_volunteers
                (full_name, contact_phone, contact_email, role_id,
                 linked_student_id, police_clearance_verified, notes, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)"
        );
        $ins->execute([
            $fullName,
            $data['contact_phone'] ?? null,
            $data['contact_email'] ?? null,
            $roleId,
            !empty($data['linked_student_id']) ? (int) $data['linked_student_id'] : null,
            !empty($data['police_clearance_verified']) ? 1 : 0,
            $data['notes'] ?? null,
            $userId ? (int) $userId : null,
        ]);

        $this->logAction('chaplaincy_volunteer_add', (int) $this->db->lastInsertId(), 'Registered Chaplaincy volunteer');
        return $this->successResponse(['id' => (int) $this->db->lastInsertId()]);
    }

    /**
     * Deactivate a volunteer.
     */
    public function deactivateVolunteer(int $id): array
    {
        $upd = $this->db->prepare("UPDATE chaplaincy_volunteers SET is_active = 0 WHERE id = ?");
        $upd->execute([$id]);
        return $this->successResponse(['id' => $id]);
    }

    /* ================================================================
     * One-submit bulk assignment across all three registries.
     * The assign-member modal stages staff + parents + students across
     * tabs in localStorage, then fires a single POST to this endpoint.
     * ================================================================ */

    /**
     * Payload (every top-level key optional, at least one present):
     *   staff:    { role_id, ids: [staff_id,...], effective_from?, effective_to? }
     *   parents:  { role_id, ids: [parent_id,...] }
     *   students: { role_id, ids: [student_id,...] }
     *   effective_from / effective_to   — shared start/end when a group omits them
     *   academic_year_id                — optional; auto-resolved from the start date
     *
     * Writes:
     *   staff   → staff_department_assignments (borrowed team member, role name)
     *   parents → chaplaincy_volunteers        (role_id FK, linked to the person)
     *   students→ school_leadership            (Spiritual / Ministry Group Leader)
     *
     * The Department Head role is never assignable here; it belongs to the
     * School Administrator (departments.head_id).
     */
    public function bulkAssign(array $data): array
    {
        $from   = $data['effective_from'] ?? date('Y-m-d');
        $to     = $data['effective_to'] ?? null;
        $yearId = !empty($data['academic_year_id']) ? (int) $data['academic_year_id'] : 0;

        if (!isset($data['staff']) && !isset($data['parents']) && !isset($data['students'])) {
            return $this->errorResponse('Nothing to assign — supply staff, parents or students', 422);
        }

        $roles = $this->teamRoleLookup();
        $summary = ['staff' => [], 'parents' => [], 'students' => [], 'skipped' => []];

        // ---- Staff (borrowed team members) ----
        if (!empty($data['staff']) && is_array($data['staff'])) {
            $g = $data['staff'];
            $role = $this->resolveAssignableRole($roles, $g['role_id'] ?? null);
            if (!$role) {
                return $this->errorResponse('A valid, assignable ministry role is required for staff', 422);
            }
            $ids = $this->positiveIds($g['ids'] ?? $g['id'] ?? []);
            $gFrom = $g['effective_from'] ?? $from;
            $gTo   = $g['effective_to'] ?? $to;
            foreach ($ids as $staffId) {
                if (!$this->staffExists((int) $staffId)) {
                    $summary['skipped'][] = ['type' => 'staff', 'id' => (int) $staffId, 'reason' => 'unknown or inactive staff'];
                    continue;
                }
                $nextId = (int) $this->db->query(
                    'SELECT COALESCE(MAX(id), 0) + 1 FROM staff_department_assignments'
                )->fetchColumn();
                $this->db->prepare(
                    "INSERT INTO staff_department_assignments
                        (id, staff_id, department_id, role, effective_from, effective_to)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE role = VALUES(role), effective_to = VALUES(effective_to)"
                )->execute([$nextId, (int) $staffId, $this->departmentId(), $role['name'], $gFrom, $gTo]);
                $this->logAction('chaplaincy_team_add_member', (int) $staffId, 'Assigned staff to Chaplaincy team');
                $summary['staff'][] = (int) $staffId;
            }
        }

        // ---- Parents (community volunteers) ----
        if (!empty($data['parents']) && is_array($data['parents'])) {
            $g = $data['parents'];
            $role = $this->resolveAssignableRole($roles, $g['role_id'] ?? null);
            if (!$role) {
                return $this->errorResponse('A valid, assignable ministry role is required for parents', 422);
            }
            $ids = $this->positiveIds($g['ids'] ?? $g['id'] ?? []);
            $userId = $this->getCurrentUserId() ? (int) $this->getCurrentUserId() : null;
            foreach ($ids as $parentId) {
                $parent = $this->parentRecord((int) $parentId);
                if (!$parent) {
                    $summary['skipped'][] = ['type' => 'parent', 'id' => (int) $parentId, 'reason' => 'unknown parent'];
                    continue;
                }
                if ($this->volunteerExists($parent['full_name'], (int) $role['id'])) {
                    $summary['skipped'][] = ['type' => 'parent', 'id' => (int) $parentId, 'reason' => 'already a volunteer with this role'];
                    continue;
                }
                $this->db->prepare(
                    "INSERT INTO chaplaincy_volunteers
                        (full_name, contact_phone, contact_email, role_id, linked_student_id,
                         police_clearance_verified, is_active, created_by)
                     VALUES (?, ?, ?, ?, ?, 0, 1, ?)"
                )->execute([
                    $parent['full_name'],
                    $parent['phone_1'] ?? null,
                    $parent['email'] ?? null,
                    (int) $role['id'],
                    null,
                    $userId,
                ]);
                $volId = (int) $this->db->lastInsertId();
                $this->logAction('chaplaincy_volunteer_register', $volId, 'Registered parent as Chaplaincy volunteer');
                $summary['parents'][] = $volId;
            }
            if ($summary['parents']) {
                $this->logAction('chaplaincy_volunteers_bulk_register', 0, 'Registered ' . count($summary['parents']) . ' parent volunteers');
            }
        }

        // ---- Students (spiritual ministry leaders in the leadership register) ----
        if (!empty($data['students']) && is_array($data['students'])) {
            $g = $data['students'];
            $role = $this->resolveAssignableRole($roles, $g['role_id'] ?? null);
            if (!$role) {
                return $this->errorResponse('A valid, assignable ministry role is required for students', 422);
            }
            $ids = $this->positiveIds($g['ids'] ?? $g['id'] ?? []);
            $gFrom = $g['effective_from'] ?? $from;
            $leadership = new StudentLeadershipService($this->db);
            foreach ($ids as $studentId) {
                $resp = $leadership->create([
                    'student_id' => (int) $studentId,
                    'position_id' => self::STUDENT_MINISTRY_POSITION_ID,
                    'position_category' => 'spiritual',
                    'academic_year_id' => $yearId,
                    'start_date' => $gFrom,
                    'end_date' => $to,
                    'public_bio' => $role['name'],
                ]);
                if (($resp['status'] ?? '') === 'success') {
                    $summary['students'][] = (int) $studentId;
                } else {
                    $summary['skipped'][] = [
                        'type' => 'student',
                        'id' => (int) $studentId,
                        'reason' => $resp['message'] ?? 'assignment_failed',
                    ];
                }
            }
            if ($summary['students']) {
                $this->logAction('chaplaincy_student_leadership_bulk', 0, 'Recorded ' . count($summary['students']) . ' student ministry leaders');
            }
        }

        $counts = [
            'staff' => count($summary['staff']),
            'parents' => count($summary['parents']),
            'students' => count($summary['students']),
        ];
        $total = array_sum($counts);
        if ($total === 0) {
            $reason = $summary['skipped'][0]['reason'] ?? 'validation failed';
            return $this->errorResponse('No members could be assigned — ' . $reason, 409, ['skipped' => $summary['skipped']]);
        }

        return $this->successResponse(
            array_merge($summary, ['assigned' => $counts, 'total' => $total]),
            'Assignments saved'
        );
    }

    /** Map of chaplaincy_team_roles id => row. */
    private function teamRoleLookup(): array
    {
        $stmt = $this->db->query(
            "SELECT id, code, name, applies_to, is_active FROM chaplaincy_team_roles"
        );
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['id']] = $row;
        }
        return $map;
    }

    /** Return the role only when it exists, is active and is not Department Head. */
    private function resolveAssignableRole(array $roles, $roleId): ?array
    {
        if ($roleId === null || $roleId === '' || $roleId === 0) {
            return null;
        }
        $row = $roles[(int) $roleId] ?? null;
        if (!$row || (int) $row['is_active'] !== 1) {
            return null;
        }
        if (strtoupper((string) $row['code']) === self::DEPT_HEAD_ROLE_CODE) {
            return null;
        }
        return $row;
    }

    /** Normalize a scalar or array of values to unique positive ints. */
    private function positiveIds($value): array
    {
        $values = is_array($value) ? $value : [$value];
        $ids = [];
        foreach ($values as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    private function staffExists(int $staffId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        return (bool) $stmt->fetchColumn();
    }

    private function parentRecord(int $parentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id AS parent_id,
                    CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) AS full_name,
                    pp.phone AS phone_1, pp.email
             FROM parents p
             JOIN persons pp ON pp.id = p.person_id
             WHERE p.id = ?"
        );
        $stmt->execute([$parentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function volunteerExists(string $fullName, int $roleId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM chaplaincy_volunteers
             WHERE full_name = ? AND role_id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$fullName, $roleId]);
        return (bool) $stmt->fetchColumn();
    }

    /* ================================================================
     * Phase B — SDA spiritual program catalog, sessions, attendance.
     * ================================================================ */

    /**
     * List the spiritual program catalog.
     */
    public function listPrograms(array $filters = []): array
    {
        $includeInactive = !empty($filters['include_inactive']);
        $sql = "SELECT id, code, name, description, applies_sabbath,
                       default_start_time, default_day, applies_to, is_active, display_order
                FROM chapel_programs";
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY display_order, id";
        $stmt = $this->db->query($sql);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Create a catalog program (full CRUD on chapel_programs).
     */
    public function createProgram(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->errorResponse('name is required', 422);
        }
        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', strtolower($name)), 0, 35));
        }
        $defaultDay = in_array($data['default_day'] ?? '', ['SATURDAY', 'FRIDAY', 'SUNDAY', 'WEEKDAY', 'ANY'], true)
            ? $data['default_day'] : 'ANY';
        $appliesTo = in_array($data['applies_to'] ?? '', ['students', 'staff', 'both'], true)
            ? $data['applies_to'] : 'both';
        $defaultStart = ($data['default_start_time'] ?? '') !== '' ? $data['default_start_time'] : null;
        $maxOrder = (int) $this->db->query("SELECT COALESCE(MAX(display_order), 0) FROM chapel_programs")->fetchColumn();
        $ins = $this->db->prepare(
            "INSERT INTO chapel_programs
                (code, name, description, applies_sabbath, default_start_time, default_day, applies_to, is_active, display_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            $code,
            $name,
            $data['description'] ?? null,
            !empty($data['applies_sabbath']) ? 1 : 0,
            $defaultStart,
            $defaultDay,
            $appliesTo,
            array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1,
            (int) ($data['display_order'] ?? $maxOrder + 1),
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->logAction('chaplaincy_program_create', $id, 'Created spiritual program');
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Update catalog program fields (name, description, defaults, active, order).
     */
    public function updateProgram(int $id, array $data): array
    {
        $sets = [];
        $params = [];
        foreach ([
            'name' => 'name',
            'code' => 'code',
            'description' => 'description',
            'default_start_time' => 'default_start_time',
            'applies_to' => 'applies_to',
            'display_order' => 'display_order',
        ] as $key => $col) {
            if (array_key_exists($key, $data)) {
                $sets[] = "$col = ?";
                $params[] = $data[$key];
            }
        }
        if (array_key_exists('applies_sabbath', $data)) {
            $sets[] = 'applies_sabbath = ?';
            $params[] = !empty($data['applies_sabbath']) ? 1 : 0;
        }
        if (array_key_exists('is_active', $data)) {
            $sets[] = 'is_active = ?';
            $params[] = !empty($data['is_active']) ? 1 : 0;
        }
        if (empty($sets)) {
            return $this->errorResponse('No fields to update', 422);
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $id;
        $this->db->prepare("UPDATE chapel_programs SET " . implode(', ', $sets) . " WHERE id = ?")
            ->execute($params);
        $this->logAction('chaplaincy_program_update', $id, 'Updated spiritual program');
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Remove a catalog program. Programs with scheduled sessions are
     * soft-deactivated (is_active = 0) to preserve session history; unused
     * programs are hard-deleted.
     */
    public function deleteProgram(int $id): array
    {
        $count = $this->db->prepare("SELECT COUNT(*) FROM chapel_program_sessions WHERE program_id = ?");
        $count->execute([$id]);
        if ((int) $count->fetchColumn() > 0) {
            $this->db->prepare("UPDATE chapel_programs SET is_active = 0, updated_at = NOW() WHERE id = ?")
                ->execute([$id]);
            $this->logAction('chaplaincy_program_deactivate', $id, 'Deactivated spiritual program with scheduled sessions');
            return $this->successResponse(['id' => $id, 'deactivated' => true]);
        }
        $this->db->prepare("DELETE FROM chapel_programs WHERE id = ?")->execute([$id]);
        $this->logAction('chaplaincy_program_delete', $id, 'Deleted spiritual program');
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Resolve the next qualifying date for a program (mostly the coming
     * Saturday for Sabbath programs). Uses the real academic-calendar day when
     * available; otherwise falls back to the next weekday matching default_day.
     */
    public function resolveNextDate(array $filters = []): array
    {
        $programId = (int) ($filters['program_id'] ?? 0);
        $stmt = $this->db->prepare(
            "SELECT code, default_day, applies_sabbath FROM chapel_programs WHERE id = ?"
        );
        $stmt->execute([$programId]);
        $prog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prog) {
            return $this->errorResponse('Unknown program', 422);
        }
        return $this->successResponse(['suggested_date' => $this->suggestedDate($prog)]);
    }

    private function suggestedDate(array $prog): string
    {
        $wantSaturday = (int) $prog['applies_sabbath'];
        for ($i = 0; $i < 14; $i++) {
            $d = new \DateTime('+' . $i . ' days');
            if ($wantSaturday && (int) $d->format('N') === 6) { // Saturday
                return $d->format('Y-m-d');
            }
            if (!$wantSaturday) {
                $dayName = strtolower((string) $prog['default_day']);
                if ($dayName === 'any') return $d->format('Y-m-d');
                $want = ['saturday' => 6, 'friday' => 5, 'sunday' => 7][$dayName] ?? null;
                if ($want === null || (int) $d->format('N') === $want) return $d->format('Y-m-d');
            }
        }
        return (new \DateTime())->format('Y-m-d');
    }

    /**
     * List program sessions (optionally upcoming / by program / by date).
     */
    public function listSessions(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['program_id'])) {
            $where[] = 's.program_id = ?';
            $params[] = (int) $filters['program_id'];
        }
        if (!empty($filters['upcoming'])) {
            $where[] = 's.session_date >= CURDATE() AND s.status = \'scheduled\'';
        }
        if (!empty($filters['from'])) { $where[] = 's.session_date >= ?'; $params[] = $filters['from']; }
        if (!empty($filters['to']))   { $where[] = 's.session_date <= ?';   $params[] = $filters['to']; }

        $stmt = $this->db->prepare(
            "SELECT s.id, s.title, s.session_date, s.start_at, s.end_at,
                    s.speaker, s.worship_leader, s.music_director, s.location,
                    s.expected_attendance, s.status, s.notes,
                    p.code AS program_code, p.name AS program_name, p.applies_sabbath,
                    s.class_stream_id,
                    COALESCE(str.name, cls.name) AS class_name,
                    CONCAT_WS(' ', lp.first_name, lp.last_name) AS leader_name
             FROM chapel_program_sessions s
             JOIN chapel_programs p ON p.id = s.program_id
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = s.class_stream_id
             LEFT JOIN streams str ON str.id = aycs.stream_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN classes cls ON cls.id = ayc.class_id
             LEFT JOIN staff ls ON ls.id = s.leader_id
             LEFT JOIN persons lp ON lp.id = ls.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.session_date DESC, s.start_at"
        );
        $stmt->execute($params);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Schedule a program session.
     */
    public function createSession(array $data): array
    {
        $programId = (int) ($data['program_id'] ?? 0);
        $date      = $data['session_date'] ?? '';
        $title     = trim((string) ($data['title'] ?? ''));
        if (!$programId || !$date || $title === '') {
            return $this->errorResponse('program_id, session_date and title are required', 422);
        }
        $stmt = $this->db->prepare("SELECT id FROM chapel_programs WHERE id = ?");
        $stmt->execute([$programId]);
        if (!$stmt->fetchColumn()) return $this->errorResponse('Unknown program', 422);

        $userId = $this->getCurrentUserId();
        $ins = $this->db->prepare(
            "INSERT INTO chapel_program_sessions
                (program_id, title, session_date, start_at, end_at, leader_id,
                 speaker, worship_leader, music_director, location, class_stream_id,
                 expected_attendance, status, calendar_day_id, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $ins->execute([
            $programId, $title, $date,
            $data['start_at'] ?? null,
            $data['end_at'] ?? null,
            !empty($data['leader_id']) ? (int) $data['leader_id'] : null,
            $data['speaker'] ?? null,
            $data['worship_leader'] ?? null,
            $data['music_director'] ?? null,
            $data['location'] ?? null,
            !empty($data['class_stream_id']) ? (int) $data['class_stream_id'] : null,
            !empty($data['expected_attendance']) ? (int) $data['expected_attendance'] : null,
            $data['status'] ?? 'scheduled',
            !empty($data['calendar_day_id']) ? (int) $data['calendar_day_id'] : null,
            $data['notes'] ?? null,
            $userId ? (int) $userId : null,
        ]);
        $this->logAction('chaplaincy_session_create', (int) $this->db->lastInsertId(), 'Scheduled spiritual program session');
        return $this->successResponse(['id' => (int) $this->db->lastInsertId()]);
    }

    /**
     * Update a session status / attendance count targets.
     */
    public function updateSession(int $id, array $data): array
    {
        $status = $data['status'] ?? null;
        $expected = isset($data['expected_attendance']) ? (int) $data['expected_attendance'] : null;
        $upd = $this->db->prepare(
            "UPDATE chapel_program_sessions
             SET status = COALESCE(?, status),
                 expected_attendance = COALESCE(?, expected_attendance)
             WHERE id = ?"
        );
        $upd->execute([$status, $expected, $id]);
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Delete a scheduled session.
     */
    public function deleteSession(int $id): array
    {
        $del = $this->db->prepare("DELETE FROM chapel_program_sessions WHERE id = ?");
        $del->execute([$id]);
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Attendance roll for a session: list eligible + recorded attendees.
     */
    public function getSessionAttendance(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id AS session_id, s.title, s.session_date, s.status,
                    p.name AS program_name, p.code AS program_code
             FROM chapel_program_sessions s
             JOIN chapel_programs p ON p.id = s.program_id
             WHERE s.id = ?"
        );
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) return $this->errorResponse('Session not found', 404);

        $att = $this->db->prepare(
            "SELECT a.id, a.attendee_type, a.student_id, a.staff_id, a.parent_id, a.attended,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS name
             FROM chapel_session_attendance a
             LEFT JOIN students st ON st.id = a.student_id
             LEFT JOIN staff sf ON sf.id = a.staff_id
             LEFT JOIN persons p ON p.id = COALESCE(st.person_id, sf.person_id)
             WHERE a.session_id = ?"
        );
        $att->execute([$sessionId]);
        $records = $att->fetchAll(PDO::FETCH_ASSOC);

        return $this->successResponse([
            'session' => $session,
            'attendance' => $records,
            'present_count' => count(array_filter($records, static fn($r) => $r['attended'])),
        ]);
    }

    /**
     * Record per-person attendance. Idempotent: a batch of rows keyed by
     * (session, attendee) returns the count saved; re-saving is a no-op.
     */
    public function saveAttendance(int $sessionId, array $data): array
    {
        $rows = $data['attendance'] ?? $data['records'] ?? $data['rows'] ?? [];
        if (!is_array($rows) || !count($rows)) {
            return $this->errorResponse('attendance rows are required', 422);
        }
        $userId = $this->getCurrentUserId();
        $ins = $this->db->prepare(
            "INSERT INTO chapel_session_attendance
                (session_id, attendee_type, student_id, staff_id, parent_id, attended, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attended = VALUES(attended)"
        );
        $saved = 0;
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $type = strtolower((string) ($r['attendee_type'] ?? 'student'));
            if (!in_array($type, ['student', 'staff', 'parent', 'volunteer'], true)) continue;
            $studentId = $type === 'student' ? (int) ($r['student_id'] ?? 0) : 0;
            $staffId   = $type === 'staff'   ? (int) ($r['staff_id'] ?? 0)   : 0;
            $parentId  = $type === 'parent'  ? (int) ($r['parent_id'] ?? 0)  : 0;
            $ins->execute([
                $sessionId, $type,
                $studentId ?: null,
                $staffId   ?: null,
                $parentId  ?: null,
                !empty($r['attended']) ? 1 : 0,
                $userId ? (int) $userId : null,
            ]);
            $saved++;
        }
        $this->logAction('chaplaincy_attendance_save', $sessionId, 'Recorded spiritual program attendance');
        return $this->successResponse(['saved' => $saved]);
    }

    /* =====================================================================
     * PHASE C — Recurring groups, spiritual profiles & pastoral care
     * Confidential: learner spiritual profile, milestones and pastoral visits
     * are served only under authorized pastoral roles (see controller guard).
     * ===================================================================== */

    /**
     * List recurring spiritual groups with member counts.
     */
    public function listGroups(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['group_type'])) {
            $where[] = 'g.group_type = ?';
            $params[] = (string) $filters['group_type'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare(
            "SELECT g.id, g.code, g.name, g.group_type, g.description,
                    g.leader_id,
                    CONCAT_WS(' ', lp.first_name, lp.last_name) AS leader_name,
                    g.meeting_day, g.meeting_time, g.meeting_location,
                    g.is_active,
                    (SELECT COUNT(*) FROM chaplaincy_spiritual_group_members m
                      WHERE m.group_id = g.id AND m.status = 'active') AS member_count
             FROM chaplaincy_spiritual_groups g
             LEFT JOIN staff ls ON ls.id = g.leader_id
             LEFT JOIN persons lp ON lp.id = ls.person_id
             $whereSql
             ORDER BY g.is_active DESC, g.name"
        );
        $stmt->execute($params);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createGroup(array $data): array
    {
        $code = strtoupper(preg_replace('/[^A-Z0-9_-]/', '_', (string) ($data['code'] ?? $data['name'] ?? 'GROUP')));
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') return $this->errorResponse('Group name is required', 422);
        $groupType = (string) ($data['group_type'] ?? 'other');
        $allowed = ['pathfinders', 'adventurers', 'ay', 'choir', 'bible_study', 'prayer', 'other'];
        if (!in_array($groupType, $allowed, true)) $groupType = 'other';
        $userId = $this->getCurrentUserId();
        $this->db->prepare(
            "INSERT INTO chaplaincy_spiritual_groups
                (code, name, group_type, description, leader_id, meeting_day, meeting_time,
                 meeting_location, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?)"
        )->execute([
            $code, $name, $groupType,
            $data['description'] ?? null,
            isset($data['leader_id']) && $data['leader_id'] !== '' ? (int) $data['leader_id'] : null,
            $data['meeting_day'] ?? null,
            $data['meeting_time'] ?? null,
            $data['meeting_location'] ?? null,
            isset($data['is_active']) ? (int) $data['is_active'] : 1,
            $userId ? (int) $userId : null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->logAction('chaplaincy_group_create', $id, "Created spiritual group: $name");
        return $this->successResponse(['id' => $id]);
    }

    public function updateGroup(int $id, array $data): array
    {
        $allowed = ['name', 'group_type', 'description', 'leader_id', 'meeting_day', 'meeting_time', 'meeting_location', 'is_active'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;
            if ($col === 'leader_id' || $col === 'is_active') {
                $sets[] = "$col = ?";
                $params[] = $data[$col] === '' || $data[$col] === null ? null : (int) $data[$col];
            } else {
                $sets[] = "$col = ?";
                $params[] = $data[$col] === '' ? null : $data[$col];
            }
        }
        if (!$sets) return $this->errorResponse('Nothing to update', 422);
        $params[] = (int) $id;
        $this->db->prepare("UPDATE chaplaincy_spiritual_groups SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
        $this->logAction('chaplaincy_group_update', $id, 'Updated spiritual group');
        return $this->successResponse(['id' => $id]);
    }

    /**
     * List group members (learners and staff) with resolved names.
     */
    public function listGroupMembers(int $groupId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.member_type, m.student_id, m.staff_id, m.joined_at, m.status, m.notes,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS name
             FROM chaplaincy_spiritual_group_members m
             LEFT JOIN students st ON st.id = m.student_id
             LEFT JOIN staff sf ON sf.id = m.staff_id
             LEFT JOIN persons p ON p.id = COALESCE(st.person_id, sf.person_id)
             WHERE m.group_id = ?
             ORDER BY m.status, name"
        );
        $stmt->execute([$groupId]);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function addGroupMember(int $groupId, array $data): array
    {
        $type = strtolower((string) ($data['member_type'] ?? 'student'));
        if (!in_array($type, ['student', 'staff'], true)) {
            return $this->errorResponse('member_type must be student or staff', 422);
        }
        $studentId = $type === 'student' ? (int) ($data['student_id'] ?? 0) : 0;
        $staffId   = $type === 'staff'   ? (int) ($data['staff_id'] ?? 0)   : 0;
        if (($type === 'student' && !$studentId) || ($type === 'staff' && !$staffId)) {
            return $this->errorResponse('Valid member id is required', 422);
        }
        $this->db->prepare(
            "INSERT INTO chaplaincy_spiritual_group_members
                (group_id, member_type, student_id, staff_id, joined_at, status, notes)
             VALUES (?, ?, ?, ?, ?, 'active', ?)
             ON DUPLICATE KEY UPDATE status = 'active'"
        )->execute([
            $groupId, $type,
            $studentId ?: null,
            $staffId ?: null,
            $data['joined_at'] ?? date('Y-m-d'),
            $data['notes'] ?? null,
        ]);
        $this->logAction('chaplaincy_group_member_add', $groupId, "Added $type member to spiritual group");
        return $this->successResponse(['group_id' => $groupId]);
    }

    public function removeGroupMember(int $memberId): array
    {
        $this->db->prepare("DELETE FROM chaplaincy_spiritual_group_members WHERE id = ?")->execute([$memberId]);
        $this->logAction('chaplaincy_group_member_remove', $memberId, 'Removed member from spiritual group');
        return $this->successResponse(['id' => $memberId]);
    }

    /**
     * Group attendance for a meeting date. If members exist but no attendance
     * is recorded, we return the roster defaults so the UI can prefill.
     */
    public function getGroupAttendance(int $groupId, string $meetingDate): array
    {
        $att = $this->db->prepare(
            "SELECT a.member_type, a.student_id, a.staff_id, a.attended,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS name
             FROM chaplaincy_group_attendance a
             LEFT JOIN students st ON st.id = a.student_id
             LEFT JOIN staff sf ON sf.id = a.staff_id
             LEFT JOIN persons p ON p.id = COALESCE(st.person_id, sf.person_id)
             WHERE a.group_id = ? AND a.meeting_date = ?"
        );
        $att->execute([$groupId, $meetingDate]);
        $records = $att->fetchAll(PDO::FETCH_ASSOC);
        return $this->successResponse([
            'meeting_date' => $meetingDate,
            'attendance' => $records,
            'present_count' => count(array_filter($records, static fn($r) => $r['attended'])),
        ]);
    }

    public function saveGroupAttendance(int $groupId, array $data): array
    {
        $meetingDate = (string) ($data['meeting_date'] ?? '');
        $rows = $data['attendance'] ?? $data['rows'] ?? [];
        if ($meetingDate === '' || !is_array($rows) || !count($rows)) {
            return $this->errorResponse('meeting_date and attendance rows are required', 422);
        }
        $userId = $this->getCurrentUserId();
        $ins = $this->db->prepare(
            "INSERT INTO chaplaincy_group_attendance
                (group_id, meeting_date, member_type, student_id, staff_id, attended, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE attended = VALUES(attended)"
        );
        $saved = 0;
        foreach ($rows as $r) {
            $type = strtolower((string) ($r['member_type'] ?? 'student'));
            if (!in_array($type, ['student', 'staff'], true)) continue;
            $studentId = $type === 'student' ? (int) ($r['student_id'] ?? 0) : 0;
            $staffId   = $type === 'staff'   ? (int) ($r['staff_id'] ?? 0)   : 0;
            $ins->execute([
                $groupId, $meetingDate, $type,
                $studentId ?: null,
                $staffId ?: null,
                !empty($r['attended']) ? 1 : 0,
                $userId ? (int) $userId : null,
            ]);
            $saved++;
        }
        $this->logAction('chaplaincy_group_attendance_save', $groupId, "Recorded group attendance for $meetingDate");
        return $this->successResponse(['saved' => $saved]);
    }

    /**
     * Confidential spiritual profile for a learner (get or create-on-read).
     */
    public function getSpiritualProfile(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sp.*, CONCAT_WS(' ', p.first_name, p.last_name) AS student_name
             FROM learner_spiritual_profile sp
             JOIN students st ON st.id = sp.student_id
             LEFT JOIN persons p ON p.id = st.person_id
             WHERE sp.student_id = ?"
        );
        $stmt->execute([$studentId]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$profile) {
            $this->db->prepare(
                "INSERT INTO learner_spiritual_profile (student_id) VALUES (?)"
            )->execute([$studentId]);
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        $ml = $this->db->prepare(
            "SELECT id, milestone_type, milestone_date, title, details
             FROM learner_spiritual_milestones
             WHERE student_id = ?
             ORDER BY milestone_date DESC"
        );
        $ml->execute([$studentId]);
        $profile['milestones'] = $ml->fetchAll(PDO::FETCH_ASSOC);
        return $this->successResponse($profile);
    }

    public function updateSpiritualProfile(int $studentId, array $data): array
    {
        $allowed = ['baptism_status', 'baptism_date', 'baptism_church', 'church_membership',
                    'sabbath_school_class', 'interests', 'spiritual_gifts', 'prayer_concerns', 'pastoral_notes'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;
            $sets[] = "$col = ?";
            $params[] = $data[$col] === '' ? null : $data[$col];
        }
        if (!$sets) return $this->errorResponse('Nothing to update', 422);
        $params[] = (int) $studentId;
        $this->db->prepare(
            "UPDATE learner_spiritual_profile SET " . implode(', ', $sets) . " WHERE student_id = ?"
        )->execute($params);
        $this->logAction('chaplaincy_spiritual_profile_update', $studentId, 'Updated learner spiritual profile');
        return $this->successResponse(['student_id' => $studentId]);
    }

    public function addSpiritualMilestone(int $studentId, array $data): array
    {
        $type = (string) ($data['milestone_type'] ?? 'other');
        $allowed = ['baptism', 'commitment', 'investiture', 'award', 'dedication', 'other'];
        if (!in_array($type, $allowed, true)) $type = 'other';
        $title = trim((string) ($data['title'] ?? ''));
        $date = (string) ($data['milestone_date'] ?? '');
        if ($title === '' || $date === '') return $this->errorResponse('title and milestone_date are required', 422);
        $userId = $this->getCurrentUserId();
        $this->db->prepare(
            "INSERT INTO learner_spiritual_milestones
                (student_id, milestone_type, milestone_date, title, details, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([
            $studentId, $type, $date, $title, $data['details'] ?? null,
            $userId ? (int) $userId : null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->logAction('chaplaincy_spiritual_milestone_add', $id, "Recorded $type milestone for learner");
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Pastoral care visits / contacts log. Private rows are only shown to the
     * conductor and pastoral coordinators; the controller may filter further.
     */
    public function listPastoralVisits(array $filters = []): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'v.status = ?';
            $params[] = (string) $filters['status'];
        }
        if (!empty($filters['care_subject_type'])) {
            $where[] = 'v.care_subject_type = ?';
            $params[] = (string) $filters['care_subject_type'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $stmt = $this->db->prepare(
            "SELECT v.id, v.visit_type, v.visit_date, v.care_subject_type,
                    v.student_id, v.staff_id,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS subject_name,
                    v.purpose, v.next_action_at, v.status, v.confidential,
                    CONCAT_WS(' ', cp.first_name, cp.last_name) AS conducted_by_name
             FROM chaplaincy_pastoral_visits v
             LEFT JOIN students st ON st.id = v.student_id
             LEFT JOIN staff sf ON sf.id = v.staff_id
             LEFT JOIN persons p ON p.id = COALESCE(st.person_id, sf.person_id)
             LEFT JOIN staff cs ON cs.id = v.conducted_by
             LEFT JOIN persons cp ON cp.id = cs.person_id
             $whereSql
             ORDER BY v.visit_date DESC, v.id DESC"
        );
        $stmt->execute($params);
        return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function createPastoralVisit(array $data): array
    {
        $visitType = (string) ($data['visit_type'] ?? 'campus');
        $allowed = ['home', 'campus', 'hospital', 'phone', 'other'];
        if (!in_array($visitType, $allowed, true)) $visitType = 'campus';
        $subjectType = strtolower((string) ($data['care_subject_type'] ?? 'student'));
        if (!in_array($subjectType, ['student', 'staff'], true)) $subjectType = 'student';
        $studentId = $subjectType === 'student' ? (int) ($data['student_id'] ?? 0) : 0;
        $staffId   = $subjectType === 'staff'   ? (int) ($data['staff_id'] ?? 0)   : 0;
        $purpose = trim((string) ($data['purpose'] ?? ''));
        $date = (string) ($data['visit_date'] ?? '');
        if ($purpose === '' || $date === '') return $this->errorResponse('purpose and visit_date are required', 422);
        if (($subjectType === 'student' && !$studentId) || ($subjectType === 'staff' && !$staffId)) {
            return $this->errorResponse('Valid care subject id is required', 422);
        }
        $userId = $this->getCurrentUserId();
        $this->db->prepare(
            "INSERT INTO chaplaincy_pastoral_visits
                (visit_type, visit_date, care_subject_type, student_id, staff_id, purpose,
                 follow_up_notes, next_action_at, status, conducted_by, confidential)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, 'normal')"
        )->execute([
            $visitType, $date, $subjectType,
            $studentId ?: null,
            $staffId ?: null,
            $purpose,
            $data['follow_up_notes'] ?? null,
            $data['next_action_at'] ?? null,
            $data['status'] ?? 'open',
            $userId ? (int) $userId : null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->logAction('chaplaincy_pastoral_visit_create', $id, 'Recorded pastoral care visit');
        return $this->successResponse(['id' => $id]);
    }

    public function updatePastoralVisit(int $id, array $data): array
    {
        $allowed = ['visit_type', 'visit_date', 'purpose', 'follow_up_notes', 'next_action_at', 'status', 'confidential'];
        $sets = [];
        $params = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $data)) continue;
            $sets[] = "$col = ?";
            $params[] = $data[$col] === '' ? null : $data[$col];
        }
        if (!$sets) return $this->errorResponse('Nothing to update', 422);
        $params[] = (int) $id;
        $this->db->prepare(
            "UPDATE chaplaincy_pastoral_visits SET " . implode(', ', $sets) . " WHERE id = ?"
        )->execute($params);
        $this->logAction('chaplaincy_pastoral_visit_update', $id, 'Updated pastoral care visit');
        return $this->successResponse(['id' => $id]);
    }

    /**
     * Aggregate spiritual KPIs for the Chaplain dashboard. Confidential
     * learner-scoped values are presented only in safe aggregates.
     */
    public function getDashboardSummary(): array
    {
        $today = date('Y-m-d');
        $wkStart = date('Y-m-d', strtotime('monday this week'));
        $wkEnd = date('Y-m-d', strtotime('sunday this week'));

        $upcomingSabbath = $this->db->prepare(
            "SELECT COUNT(*) FROM chapel_program_sessions s
             JOIN chapel_programs p ON p.id = s.program_id
             WHERE s.session_date BETWEEN ? AND ? AND p.applies_sabbath = 1 AND s.status = 'scheduled'"
        );
        $upcomingSabbath->execute([$wkStart, $wkEnd]);

        $sessionsThisWeek = $this->db->prepare(
            "SELECT COUNT(*) FROM chapel_program_sessions
             WHERE session_date BETWEEN ? AND ?"
        );
        $sessionsThisWeek->execute([$wkStart, $wkEnd]);

        $presentStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM chapel_session_attendance WHERE attended = 1 AND DATE(created_at) BETWEEN ? AND ?"
        );
        $presentStmt->execute([$wkStart, $wkEnd]);

        $activeGroups = $this->db->query(
            "SELECT COUNT(*) FROM chaplaincy_spiritual_groups WHERE is_active = 1"
        )->fetchColumn();

        $groupMembers = $this->db->query(
            "SELECT COUNT(*) FROM chaplaincy_spiritual_group_members WHERE status = 'active'"
        )->fetchColumn();

        $followupOverdue = $this->db->prepare(
            "SELECT COUNT(*) FROM chaplaincy_pastoral_visits
             WHERE status IN ('open', 'followup') AND next_action_at IS NOT NULL AND next_action_at <= ?"
        );
        $followupOverdue->execute([$today]);

        $baptized = $this->db->query(
            "SELECT COUNT(*) FROM learner_spiritual_profile WHERE baptism_status = 'baptized'"
        )->fetchColumn();

        $openVisits = $this->db->query(
            "SELECT COUNT(*) FROM chaplaincy_pastoral_visits WHERE status IN ('open', 'followup')"
        )->fetchColumn();

        return $this->successResponse([
            'sabbath_services_this_week' => (int) $upcomingSabbath->fetchColumn(),
            'program_sessions_this_week' => (int) $sessionsThisWeek->fetchColumn(),
            'attendance_recorded_this_week' => (int) $presentStmt->fetchColumn(),
            'active_groups' => (int) $activeGroups,
            'active_group_members' => (int) $groupMembers,
            'pastoral_followups_overdue' => (int) $followupOverdue->fetchColumn(),
            'open_pastoral_visits' => (int) $openVisits,
            'baptized_learners' => (int) $baptized,
        ]);
    }
}
