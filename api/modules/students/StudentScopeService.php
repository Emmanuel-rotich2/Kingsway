<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Services\TeacherScopeService;
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
     * Visibility WHERE-clause fragments built against the normalized projection
     * (students s, persons p, academic_year_class_streams aycs, academic_year_classes
     * ayc, student_types st, student_transport_assignments sta). Caller owns the
     * FROM/JOINs and must alias them consistently with StudentRepository::joins().
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
                $clauses[] = 'aycs.stream_id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['stream_ids']);
            }
            if (!empty($scope['class_ids'])) {
                $clauses[] = 'ayc.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['class_ids']);
            }
            if (!empty($scope['transport_route_ids'])) {
                $clauses[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['transport_route_ids']);
            }
            if (!empty($scope['class_stream_pairs'])) {
                $pairClauses = [];
                foreach ($scope['class_stream_pairs'] as $pair) {
                    $pairClauses[] = '(ayc.class_id = ? AND aycs.stream_id = ?)';
                    $bindings[] = (int) $pair['class_id'];
                    $bindings[] = (int) $pair['stream_id'];
                }
                $clauses[] = '(' . implode(' OR ', $pairClauses) . ')';
            }
            $conditions[] = $clauses ? '(' . implode(' OR ', $clauses) . ')' : '1 = 0';
        } elseif (!empty($scope['transport_route_ids'])) {
            $conditions[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
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
            FROM students s
            LEFT JOIN student_types st ON st.id = s.student_type_id
            LEFT JOIN academic_years ay ON ay.is_current = 1
            LEFT JOIN student_academic_enrollments sae
                ON sae.student_id = s.id AND sae.academic_year_id = ay.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
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
            FROM academic_year_class_learning_area_teachers la_teachers
            JOIN academic_year_class_learning_areas la
                ON la.id = la_teachers.academic_year_class_learning_area_id
            JOIN academic_year_classes ayc
                ON ayc.id = la.academic_year_class_id
            LEFT JOIN academic_year_class_streams aycs
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
            $classStmt = $this->db->prepare("SELECT DISTINCT ayc.class_id, aycs.stream_id
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
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
                $conditions[] = 'LOWER(pp.email) = ?';
                $bindings[] = $email;
            }
            if ($phone !== '') {
                $conditions[] = 'pp.phone = ?';
                $bindings[] = $phone;
            }
            if (!empty($conditions)) {
                $stmt = $this->db->prepare(
                    'SELECT par.id FROM parents par JOIN persons pp ON pp.id = par.person_id WHERE '
                    . implode(' OR ', $conditions)
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
             FROM transport_vehicle_routes tvr
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
        $stmt = $this->db->prepare(
            "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? AND s.status = 'active' LIMIT 1"
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
}
