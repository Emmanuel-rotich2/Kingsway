<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Services\TeacherScopeService;
use App\API\Services\ReadReplicaService;
use PDO;

/**
 * Normalized student visibility scoping (3NF/4NF).
 *
 * Teacher scope resolves through `academic_year_class_learning_area_teachers`
 * (the canonical year-scoped teacher→class-learning-area binding) joined to
 * `academic_year_class_learning_areas` → `academic_year_classes` (class) and
 * the stream context. Parent scope resolves through `student_parents` →
 * `parents` → `persons`. Transport scope uses `student_transport_assignments`.
 *
 * Never references retired tables: staff_class_assignments, class_streams,
 * students.stream_id.
 */
class StudentScopeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function buildScope(string $context, array $user): array
    {
        $scope = [
            'restricted' => true,
            'student_ids' => [],
            'class_ids' => [],
            'stream_ids' => [],
            'class_stream_pairs' => [],
            'boarding_only' => false,
            'transport_route_ids' => [],
        ];

        if (in_array($context, ['full_management', 'oversight', 'academic', 'discipline', 'welfare'], true)) {
            $scope['restricted'] = false;
        }

        if (in_array($context, ['boarding', 'catering'], true)) {
            $scope['restricted'] = false;
            $scope['boarding_only'] = true;
        }

        if ($context === 'teacher_class') {
            // Class teachers have pastoral ownership of their streams and may
            // also teach specialist areas in other streams (especially Grades
            // 4-9). Their learner visibility is the blended union.
            $teacher = (new TeacherScopeService($this->db))->forUser($user);
            $scope['class_stream_pairs'] = $teacher['class_stream_pairs'] ?? [];
        }

        if ($context === 'subject_teacher') {
            $teacher = (new TeacherScopeService($this->db))->forUser($user);
            $scope['class_stream_pairs'] = $teacher['subject_stream_pairs'] ?? [];
        }

        if ($context === 'parent_children') {
            $scope['student_ids'] = $this->parentStudentIds($user);
        }

        if ($context === 'transport') {
            $scope['transport_route_ids'] = $this->driverRouteIds($user);
        }

        return $scope;
    }

    /**
     * Visibility WHERE-clause fragments built against StudentRepository's
     * normalized aliases (students s, persons p, student_types st, learner
     * placement lp, and transport_scope). Caller owns the FROM/JOINs.
     */
    public function whereClause(array $scope): array
    {
        $conditions = [];
        $bindings = [];

        if (!empty($scope['boarding_only'])) {
            $conditions[] = "UPPER(COALESCE(st.code, '')) = 'BOARD'";
        }

        if (!empty($scope['restricted'])) {
            $clauses = [];
            if (!empty($scope['student_ids'])) {
                $clauses[] = 's.id IN (' . implode(',', array_fill(0, count($scope['student_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['student_ids']);
            }
            if (!empty($scope['stream_ids']) && empty($scope['class_stream_pairs'])) {
                $clauses[] = 'lp.stream_id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['stream_ids']);
            }
            if (!empty($scope['class_ids'])) {
                $clauses[] = 'lp.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['class_ids']);
            }
            if (!empty($scope['transport_route_ids'])) {
                $clauses[] = 'transport_scope.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['transport_route_ids']);
            }
            if (!empty($scope['class_stream_pairs'])) {
                $pairClauses = [];
                foreach ($scope['class_stream_pairs'] as $pair) {
                    $pairClauses[] = '(lp.class_id = ? AND lp.stream_id = ?)';
                    $bindings[] = (int) $pair['class_id'];
                    $bindings[] = (int) $pair['stream_id'];
                }
                $clauses[] = '(' . implode(' OR ', $pairClauses) . ')';
            }
            $conditions[] = $clauses ? '(' . implode(' OR ', $clauses) . ')' : '1 = 0';
        } elseif (!empty($scope['transport_route_ids'])) {
            $conditions[] = 'transport_scope.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['transport_route_ids']);
        }

        return [$conditions, $bindings];
    }

    public function canAccessStudent(int $studentId, array $scope): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        if (empty($scope['restricted']) && empty($scope['boarding_only'])) {
            return true;
        }

        if (!empty($scope['student_ids']) && in_array($studentId, $scope['student_ids'], true)) {
            return true;
        }

        $where = ['s.id = ?'];
        $bindings = [$studentId];

        if (!empty($scope['boarding_only'])) {
            $where[] = "UPPER(COALESCE(st.code, '')) = 'BOARD'";
        }

        $classClauses = [];
        if (!empty($scope['class_stream_pairs'])) {
            foreach ($scope['class_stream_pairs'] as $pair) {
                $classClauses[] = '(ayc.class_id = ? AND aycs.stream_id = ?)';
                $bindings[] = (int) $pair['class_id'];
                $bindings[] = (int) $pair['stream_id'];
            }
        }
        if (!empty($scope['stream_ids']) && empty($scope['class_stream_pairs'])) {
            $classClauses[] = 'aycs.stream_id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['stream_ids']);
        }
        if (!empty($scope['class_ids'])) {
            $classClauses[] = 'ayc.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['class_ids']);
        }
        if (!empty($classClauses)) {
            $where[] = '(' . implode(' OR ', $classClauses) . ')';
        }

        if (!empty($scope['transport_route_ids'])) {
            $where[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['transport_route_ids']);
        }

        if (!empty($scope['restricted']) && empty($scope['student_ids']) && empty($scope['class_ids']) && empty($scope['stream_ids']) && empty($scope['class_stream_pairs']) && empty($scope['transport_route_ids'])) {
            return false;
        }

        // Use the canonical normalized projection join shape so placement filters
        // resolve through academic_year_class_streams/academic_year_classes.
        $sql = "
            SELECT s.id
            FROM " . ReadReplicaService::masterSourceRef("students") . " s
            LEFT JOIN student_types st ON st.id = s.student_type_id
            LEFT JOIN " . ReadReplicaService::masterSourceRef("academic_years") . " ay ON ay.is_current = 1
            LEFT JOIN " . ReadReplicaService::masterSourceRef("student_academic_enrollments") . " sae
                ON sae.student_id = s.id AND sae.academic_year_id = ay.id AND sae.enrollment_status = 'active'
            LEFT JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN " . ReadReplicaService::masterSourceRef("student_transport_assignments") . " sta ON sta.student_id = s.id AND sta.status = 'active'
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Resolve the year-scoped class/stream scope for a staff member through
     * `academic_year_class_learning_area_teachers` joined to its learning-area
     * context → `academic_year_classes` (class) and the stream layer.
     */
    private function staffClassScope(array $user, array $roles): array
    {
        $staffId = $this->staffId($user);
        if (!$staffId) {
            return ['class_ids' => [], 'stream_ids' => []];
        }

        $yearId = $this->currentAcademicYearId();
        $bindings = [$staffId];
        $where = ['la_teachers.staff_id = ?'];
        if ($yearId) {
            $where[] = 'ayc.academic_year_id = ?';
            $bindings[] = $yearId;
        }
        $where[] = 'la_teachers.role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
        $bindings = array_merge($bindings, $roles);

        $rows = [];
        if (!in_array('class_teacher', $roles, true)) {
        $stmt = $this->db->prepare("
            SELECT DISTINCT ayc.class_id, aycs.stream_id, aycs.id AS academic_year_class_stream_id
            FROM " . ReadReplicaService::masterSourceRef("academic_year_class_learning_area_teachers") . " la_teachers
            JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_learning_areas") . " la
                ON la.id = la_teachers.academic_year_class_learning_area_id
            JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " ayc
                ON ayc.id = la.academic_year_class_id
            LEFT JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs
                ON aycs.academic_year_class_id = ayc.id
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // Class-teacher ownership is stored on the active class-stream row,
        // not in the learning-area teacher table. Include those streams so a
        // class teacher sees the learners actually assigned to her/his class.
        if (in_array('class_teacher', $roles, true)) {
            $classWhere = ["EXISTS (
                SELECT 1 FROM vw_teacher_effective_stream_learning_areas teacher_scope
                WHERE teacher_scope.staff_id = ?
                  AND teacher_scope.academic_year_class_stream_id = aycs.id
                  AND teacher_scope.scope_type = 'class_teacher'
            )"];
            $classBindings = [$staffId];
            if ($yearId) {
                $classWhere[] = 'ayc.academic_year_id = ?';
                $classBindings[] = $yearId;
            }
            $classStmt = $this->db->prepare("SELECT DISTINCT class_id, stream_id
                FROM " . ReadReplicaService::masterSourceRef('academic_calendar') . "
                WHERE " . implode(' AND ', $classWhere));
            $classStmt->execute($classBindings);
            $rows = array_merge($rows, $classStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }

        return [
            'class_ids' => array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'class_id'))))),
            'stream_ids' => array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'stream_id'))))),
            'class_stream_pairs' => array_values(array_map(static function ($row) {
                return ['class_id' => (int) $row['class_id'], 'stream_id' => (int) $row['stream_id']];
            }, array_filter($rows, static function ($row) {
                return !empty($row['class_id']) && !empty($row['stream_id']);
            }))),
        ];
    }

    /**
     * Parent scope: resolve through student_parents → parents → persons.
     * Parent identity contact (email/phone) lives on `persons`, not `parents`.
     */
    private function parentStudentIds(array $user): array
    {
        $parentIds = [];
        foreach (['parent_id', 'linked_parent_id'] as $field) {
            if (!empty($user[$field])) {
                $parentIds[] = (int) $user[$field];
            }
        }

        if (empty($parentIds)) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            $phone = trim((string) ($user['phone'] ?? $user['phone_number'] ?? ''));
            $conditions = [];
            $bindings = [];
            if ($email !== '') {
                $conditions[] = 'LOWER(p.email) = ?';
                $bindings[] = $email;
            }
            if ($phone !== '') {
                $conditions[] = 'p.phone = ?';
                $bindings[] = $phone;
            }
            if (!empty($conditions)) {
                $stmt = $this->db->prepare(
                    'SELECT par.id FROM parents par
                     JOIN ' . ReadReplicaService::masterRef('persons') . ' p ON p.id = par.person_id
                     WHERE par.status = \'active\' AND (' . implode(' OR ', $conditions) . ')'
                );
                $stmt->execute($bindings);
                $parentIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
            }
        }

        if (empty($parentIds)) {
            return [];
        }

        $stmt = $this->db->prepare(
            'SELECT DISTINCT student_id FROM student_parents WHERE parent_id IN ('
            . implode(',', array_fill(0, count($parentIds), '?')) . ')'
        );
        $stmt->execute($parentIds);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));
    }

    private function driverRouteIds(array $user): array
    {
        $driverId = $user['driver_id'] ?? null;
        if (!$driverId) {
            $staffId = $this->staffId($user);
            if ($staffId && $this->columnExists('staff', 'position')) {
                $stmt = $this->db->prepare("SELECT id FROM staff WHERE id = ? AND position = 'Driver' AND status = 'active' LIMIT 1");
                $stmt->execute([$staffId]);
                $driverId = $stmt->fetchColumn();
            }
        }

        if (!$driverId) {
            return [];
        }

        if (!$this->columnExists('transport_vehicle_routes', 'route_id')) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT tvr.route_id
             FROM " . ReadReplicaService::masterSourceRef("transport_vehicle_routes") . "
             JOIN transport_vehicles v ON v.id = tvr.vehicle_id
             WHERE v.driver_id = ? AND tvr.status = 'active'"
        );
        $stmt->execute([(int) $driverId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'route_id'));
    }

    private function staffId(array $user): ?int
    {
        if (!empty($user['staff_id'])) {
            return (int) $user['staff_id'];
        }
        // staff has no user_id; the link is users.person_id = staff.person_id.
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        if (!$userId) {
            return null;
        }
        $staffDirectory = ReadReplicaService::masterSourceRef('staff_directory');
        $stmt = $this->db->prepare(
            "SELECT staff_id FROM {$staffDirectory} WHERE user_id = ? AND staff_status = 'active' LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        $staffId = $stmt->fetchColumn();
        return $staffId ? (int) $staffId : null;
    }

    private function currentAcademicYearId(): ?int
    {
        $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1");
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
    /** Class teacher + subject teachers for a student's current class (contact cards). */
    public function teacherContactsForStudent(int $studentId, bool $classTeacherOnly = false): array
    {
        $enroll = $this->db->query(
            "SELECT aycs.id AS class_stream_id, aycs.class_teacher_id,
                    ayc.academic_year_id, c.name AS class_name, st.name AS stream_name,
                    CONCAT_WS(' ', sp.first_name, sp.last_name) AS student_name
               FROM " . ReadReplicaService::masterSourceRef("student_academic_enrollments") . " sae
               JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
               JOIN " . ReadReplicaService::masterSourceRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
               JOIN " . ReadReplicaService::masterSourceRef("classes") . " c ON c.id = ayc.class_id
               LEFT JOIN " . ReadReplicaService::masterSourceRef("streams") . " st ON st.id = aycs.stream_id
              WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
              ORDER BY ayc.academic_year_id DESC LIMIT 1",
            [$studentId]
        )?->fetch(\PDO::FETCH_ASSOC);
        if (!$enroll || empty($enroll['class_teacher_id'])) return [];
        $context = trim((string) ($enroll['student_name'] ?? '') . ' - ' . trim((string) $enroll['class_name'] . ' ' . (string) ($enroll['stream_name'] ?? '')));
        $contacts = [];
        $teacher = $this->db->query(
            "SELECT CONCAT_WS(' ', s.first_name, s.last_name) AS name, s.phone, s.email
               FROM " . ReadReplicaService::masterSourceRef('staff_directory') . " s WHERE s.staff_id = ?",
            [(int) $enroll['class_teacher_id']]
        )?->fetch(\PDO::FETCH_ASSOC);
        if ($teacher) {
            $contacts[] = ['name' => $teacher['name'], 'role' => 'Class Teacher', 'phone' => $teacher['phone'] ?? null, 'email' => $teacher['email'] ?? null, 'icon' => 'teacher', 'context' => $context];
        }
        if ($classTeacherOnly) return $contacts;
        $subs = $this->db->query(
            "SELECT v.staff_name AS name, v.subject_name AS subject, sp.phone, sp.email
               FROM vw_staff_assignments_detailed v JOIN " . ReadReplicaService::masterSourceRef("staff") . " s ON s.id = v.staff_id
               JOIN " . ReadReplicaService::masterSourceRef("persons") . " sp ON sp.id = s.person_id
              WHERE v.class_stream_id = ? AND v.academic_year_id = ? AND v.role = 'subject_teacher'
              GROUP BY v.staff_id, v.subject_name, sp.phone, sp.email ORDER BY v.subject_name",
            [(int) $enroll['class_stream_id'], (int) $enroll['academic_year_id']]
        )?->fetchAll(\PDO::FETCH_ASSOC);
        foreach (($subs ?: []) as $row) {
            $contacts[] = ['name' => $row['name'], 'role' => $row['subject'] . ' Teacher', 'phone' => $row['phone'] ?? null, 'email' => $row['email'] ?? null, 'icon' => 'subject', 'context' => $context];
        }
        return $contacts;
    }

    /** Whether this class-teacher's user may view this student's portfolio. */
    public function teacherCanViewStudent(int $studentId, int $userId): bool
    {
        $stmt = $this->db->prepare("SELECT 1
            FROM " . ReadReplicaService::masterSourceRef("student_academic_enrollments") . " sae
            JOIN " . ReadReplicaService::masterSourceRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
            JOIN " . ReadReplicaService::masterSourceRef("staff") . " st ON st.id = aycs.class_teacher_id
            JOIN users u ON u.person_id = st.person_id
            WHERE sae.student_id = ? AND sae.enrollment_status = 'active'
              AND aycs.status = 'active' AND u.id = ? LIMIT 1");
        $stmt->execute([$studentId, $userId]);
        return (bool) $stmt->fetchColumn();
    }
}
