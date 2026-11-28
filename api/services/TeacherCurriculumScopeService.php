<?php

namespace App\API\Services;

use PDO;

final class TeacherCurriculumScopeService
{
    public function __construct(private PDO $db) {}

    /**
     * System and school leadership roles require school-wide oversight. Classroom,
     * subject and intern teachers are restricted to their learning-area/class
     * assignments for the requested academic year.
     */
    public function resolve(int $userId, array $roleIds, ?int $academicYearId = null): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (array_intersect([2, 3, 4, 5, 6], $roleIds)) {
            return ['restricted' => false, 'academic_year_id' => $academicYearId, 'contexts' => []];
        }

        $yearId = $academicYearId ?: (int) $this->db->query(
            "SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $staff = $this->db->prepare(
            'SELECT s.staff_id FROM ' . ReadReplicaService::masterSourceRef("staff_directory") . ' s WHERE s.user_id = ? LIMIT 1'
        );
        $staff->execute([$userId]);
        $staffId = (int) $staff->fetchColumn();
        if (!$staffId || !$yearId) {
            return ['restricted' => true, 'academic_year_id' => $yearId ?: null, 'contexts' => [], 'learning_area_ids' => []];
        }

        $contexts = [];
        $stmt = $this->db->prepare(
            "SELECT DISTINCT v.subject_id AS learning_area_id, v.class_id, c.grade_level, v.class_name, v.subject_name
               FROM " . ReadReplicaService::masterRef("vw_staff_assignments_detailed") . " v
               JOIN " . ReadReplicaService::masterRef("classes") . " c ON c.id=v.class_id
              WHERE v.staff_id=? AND v.academic_year_id=?"
        );
        $stmt->execute([$staffId, $yearId]);
        $contexts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // A class teacher may teach the whole class without separate subject
        // rows. In that case, use the learning areas configured for that class
        // in this academic year rather than granting the entire curriculum.
        // This widening is for the Class Teacher role only: a Subject Teacher
        // (or intern) sees exactly the learning areas assigned to them, even
        // when a stream record happens to name them as its class teacher.
        if (!in_array(7, $roleIds, true)) {
            return $this->restrictedScope($yearId, $contexts);
        }
        $classStmt = $this->db->prepare(
            "SELECT DISTINCT aycla.learning_area_id, c.id AS class_id, c.grade_level,
                    c.name AS class_name, la.name AS subject_name
               FROM " . ReadReplicaService::masterRef("academic_year_class_streams") . " aycs
               JOIN " . ReadReplicaService::masterRef("academic_year_classes") . " ayc ON ayc.id=aycs.academic_year_class_id
               JOIN " . ReadReplicaService::masterRef("classes") . " c ON c.id=ayc.class_id
               JOIN " . ReadReplicaService::masterRef("academic_year_class_learning_areas") . " aycla ON aycla.academic_year_class_id=ayc.id
               JOIN " . ReadReplicaService::masterRef("learning_areas") . " la ON la.id=aycla.learning_area_id
              WHERE aycs.class_teacher_id=? AND ayc.academic_year_id=?"
        );
        $classStmt->execute([$staffId, $yearId]);
        $contexts = array_merge($contexts, $classStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return $this->restrictedScope($yearId, $contexts);
    }

    /** De-duplicate assignment rows into the restricted scope shape. */
    private function restrictedScope(int $yearId, array $contexts): array
    {
        $unique = [];
        foreach ($contexts as $context) {
            $areaId = (int) ($context['learning_area_id'] ?? 0);
            if (!$areaId) continue;
            $grade = trim((string) ($context['grade_level'] ?? ''));
            $classId = (int) ($context['class_id'] ?? 0);
            $unique[$areaId . '|' . strtolower($grade) . '|' . $classId] = [
                'learning_area_id' => $areaId,
                'class_id' => $classId,
                'grade_level' => $grade,
                'class_name' => $context['class_name'] ?? null,
                'learning_area_name' => $context['subject_name'] ?? null,
            ];
        }
        $contexts = array_values($unique);
        return [
            'restricted' => true,
            'academic_year_id' => $yearId,
            'contexts' => $contexts,
            'learning_area_ids' => array_values(array_unique(array_column($contexts, 'learning_area_id'))),
        ];
    }

    /** Revalidate access to a class aggregate in the class's own academic year. */
    public function canAccessAcademicYearClass(int $userId, array $roleIds, int $academicYearClassId): bool
    {
        if ($userId < 1) return false;
        $class = $this->getAcademicYearClass($academicYearClassId);
        $classId = (int) ($class['class_id'] ?? 0);
        $academicYearId = (int) ($class['academic_year_id'] ?? 0);
        if ($classId < 1 || $academicYearId < 1) {
            return false;
        }

        $scope = $this->resolve($userId, $roleIds, $academicYearId);
        if (empty($scope['restricted'])) {
            return true;
        }
        foreach ((array) ($scope['contexts'] ?? []) as $assignment) {
            if ((int) ($assignment['class_id'] ?? 0) === $classId) {
                return true;
            }
        }
        return false;
    }

    /** Return the immutable class/year pair for a selected academic-year class. */
    public function getAcademicYearClass(int $academicYearClassId): array
    {
        if ($academicYearClassId < 1) return [];
        $stmt = $this->db->prepare(
            'SELECT ayc.class_id, ayc.academic_year_id, c.grade_level FROM '
            . ReadReplicaService::masterRef('academic_year_classes') . ' ayc JOIN '
            . ReadReplicaService::masterRef('classes') . ' c ON c.id = ayc.class_id WHERE ayc.id = ? LIMIT 1'
        );
        $stmt->execute([$academicYearClassId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    }

    /** List only academic-year classes available to this staff member. */
    public function listAccessibleAcademicYearClasses(int $userId, array $roleIds): array
    {
        if ($userId < 1) return [];
        $stmt = $this->db->query(
            'SELECT ayc.id AS academic_year_class_id, ayc.class_id, ayc.academic_year_id, '
            . 'c.name AS class_name, c.grade_level, ay.year_code '
            . 'FROM ' . ReadReplicaService::masterRef('academic_year_classes') . ' ayc '
            . 'JOIN ' . ReadReplicaService::masterRef('classes') . ' c ON c.id = ayc.class_id '
            . 'JOIN ' . ReadReplicaService::masterRef('academic_years') . ' ay ON ay.id = ayc.academic_year_id '
            . 'WHERE ayc.status <> \'planning\' AND ay.status <> \'planning\' ORDER BY ay.start_date DESC, c.name'
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (array_intersect([2, 3, 4, 5, 6], array_map('intval', $roleIds))) return $rows;

        $scopeByYear = [];
        return array_values(array_filter($rows, function (array $row) use ($userId, $roleIds, &$scopeByYear): bool {
            $yearId = (int) ($row['academic_year_id'] ?? 0);
            if (!array_key_exists($yearId, $scopeByYear)) {
                $scopeByYear[$yearId] = $this->resolve($userId, $roleIds, $yearId);
            }
            $scope = $scopeByYear[$yearId];
            if (empty($scope['restricted'])) return true;
            foreach ((array) ($scope['contexts'] ?? []) as $assignment) {
                if ((int) ($assignment['class_id'] ?? 0) === (int) ($row['class_id'] ?? 0)) return true;
            }
            return false;
        }));
    }
}
