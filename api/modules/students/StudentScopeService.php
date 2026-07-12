<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use PDO;

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
            $scope = array_merge($scope, $this->staffClassScope($user, ['class_teacher']));
        }

        if ($context === 'subject_teacher') {
            $scope = array_merge($scope, $this->staffClassScope($user, ['subject_teacher', 'assistant_teacher', 'head_of_department']));
        }

        if ($context === 'parent_children') {
            $scope['student_ids'] = $this->parentStudentIds($user);
        }

        if ($context === 'transport') {
            $scope['transport_route_ids'] = $this->driverRouteIds($user);
        }

        return $scope;
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
            $where[] = "UPPER(COALESCE(st.code, '')) IN ('BOARD', 'WEEKLY')";
        }

        $classClauses = [];
        if (!empty($scope['stream_ids'])) {
            $classClauses[] = 's.stream_id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['stream_ids']);
        }
        if (!empty($scope['class_ids'])) {
            $classClauses[] = 'cs.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['class_ids']);
        }
        if (!empty($classClauses)) {
            $where[] = '(' . implode(' OR ', $classClauses) . ')';
        }

        if (!empty($scope['transport_route_ids'])) {
            $where[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['transport_route_ids']);
        }

        if (!empty($scope['restricted']) && empty($scope['student_ids']) && empty($scope['class_ids']) && empty($scope['stream_ids']) && empty($scope['transport_route_ids'])) {
            return false;
        }

        $sql = "
            SELECT s.id
            FROM students s
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN student_types st ON st.id = s.student_type_id
            LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
            WHERE " . implode(' AND ', $where) . "
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return (bool) $stmt->fetchColumn();
    }

    public function whereClause(array $scope): array
    {
        $conditions = [];
        $bindings = [];

        if (!empty($scope['boarding_only'])) {
            $conditions[] = "UPPER(COALESCE(st.code, '')) IN ('BOARD', 'WEEKLY')";
        }

        if (!empty($scope['restricted'])) {
            $clauses = [];
            if (!empty($scope['student_ids'])) {
                $clauses[] = 's.id IN (' . implode(',', array_fill(0, count($scope['student_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['student_ids']);
            }
            if (!empty($scope['stream_ids'])) {
                $clauses[] = 's.stream_id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['stream_ids']);
            }
            if (!empty($scope['class_ids'])) {
                $clauses[] = 'cs.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['class_ids']);
            }
            if (!empty($scope['transport_route_ids'])) {
                $clauses[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
                $bindings = array_merge($bindings, $scope['transport_route_ids']);
            }
            $conditions[] = $clauses ? '(' . implode(' OR ', $clauses) . ')' : '1 = 0';
        } elseif (!empty($scope['transport_route_ids'])) {
            $conditions[] = 'sta.route_id IN (' . implode(',', array_fill(0, count($scope['transport_route_ids']), '?')) . ')';
            $bindings = array_merge($bindings, $scope['transport_route_ids']);
        }

        return [$conditions, $bindings];
    }

    private function staffClassScope(array $user, array $roles): array
    {
        $staffId = $this->staffId($user);
        if (!$staffId) {
            return ['class_ids' => [], 'stream_ids' => []];
        }

        $yearId = $this->currentAcademicYearId();
        $bindings = [$staffId];
        $where = ['staff_id = ?', "status = 'active'"];
        if ($yearId) {
            $where[] = 'academic_year_id = ?';
            $bindings[] = $yearId;
        }
        $where[] = 'role IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
        $bindings = array_merge($bindings, $roles);

        $stmt = $this->db->prepare("
            SELECT DISTINCT class_id, stream_id
            FROM staff_class_assignments
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'class_ids' => array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'class_id'))))),
            'stream_ids' => array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'stream_id'))))),
        ];
    }

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
            if ($email !== '') {
                $stmt = $this->db->prepare('SELECT id FROM parents WHERE LOWER(email) = ?');
                $stmt->execute([$email]);
                $parentIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
            }
        }

        if (empty($parentIds)) {
            return [];
        }

        $stmt = $this->db->prepare('SELECT DISTINCT student_id FROM student_parents WHERE parent_id IN (' . implode(',', array_fill(0, count($parentIds), '?')) . ')');
        $stmt->execute($parentIds);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));
    }

    private function driverRouteIds(array $user): array
    {
        if (!$this->columnExists('transport_routes', 'driver_id')) {
            return [];
        }

        $driverId = $user['driver_id'] ?? null;
        if (!$driverId) {
            $staffId = $this->staffId($user);
            if ($staffId && $this->columnExists('drivers', 'staff_id')) {
                $stmt = $this->db->prepare("SELECT id FROM drivers WHERE staff_id = ? AND status = 'active' LIMIT 1");
                $stmt->execute([$staffId]);
                $driverId = $stmt->fetchColumn();
            }
        }

        if (!$driverId) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT id FROM transport_routes WHERE driver_id = ? AND status = 'active'");
        $stmt->execute([(int) $driverId]);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    private function staffId(array $user): ?int
    {
        if (!empty($user['staff_id'])) {
            return (int) $user['staff_id'];
        }
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        if (!$userId) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT id FROM staff WHERE user_id = ? AND status = 'active' LIMIT 1");
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
