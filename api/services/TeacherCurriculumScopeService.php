<?php

namespace App\API\Services;

use PDO;

final class TeacherCurriculumScopeService
{
    public function __construct(private PDO $db) {}

    /**
     * Curriculum leadership roles require school-wide oversight. Classroom,
     * subject and intern teachers are restricted to their learning-area/class
     * assignments for the requested academic year.
     */
    public function resolve(int $userId, array $roleIds, ?int $academicYearId = null): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (array_intersect([3, 4, 5, 6], $roleIds)) {
            return ['restricted' => false, 'academic_year_id' => $academicYearId, 'contexts' => []];
        }

        $yearId = $academicYearId ?: (int) $this->db->query(
            "SELECT id FROM academic_years WHERE is_current=1 OR status='active' ORDER BY is_current DESC, id DESC LIMIT 1"
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
               FROM " . ReadReplicaService::masterRef("vw_staff_assignments_detailed") . "
               JOIN " . ReadReplicaService::masterRef("classes") . " c ON c.id=v.class_id
              WHERE v.staff_id=? AND v.academic_year_id=?"
        );
        $stmt->execute([$staffId, $yearId]);
        $contexts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // A class teacher may teach the whole class without separate subject
        // rows. In that case, use the learning areas configured for that class
        // in this academic year rather than granting the entire curriculum.
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
}
