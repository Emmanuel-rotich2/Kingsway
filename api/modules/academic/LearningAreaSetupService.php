<?php
namespace App\API\Modules\academic;

use PDO;

/**
 * Learning Area Setup Service
 *
 * Seeds per-class CBC learning-area coverage (academic_year_class_learning_areas)
 * for a new academic year from the global curriculum reference data:
 * learning_areas.levels drives which areas apply to a grade; strands and
 * sub_strands are counted/attached by grade_level.
 */
class LearningAreaSetupService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Map a classes.name to the grade label used by learning_areas.levels.
     * Returns null for grades without CBC curriculum (Playgroup).
     */
    private function normalizeGrade(string $className): ?string
    {
        $name = trim($className);
        if ($name === '' || strcasecmp($name, 'Playgroup') === 0 || strcasecmp($name, 'Grade 0') === 0) {
            return null;
        }
        return $name;
    }

    /**
     * Seed learning-area coverage rows for every class in an academic year.
     *
     * @param int $academicYearId
     * @return array Per-class summary
     */
    public function seedForYear(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ayc.id AS ayc_id, c.name AS class_name
             FROM academic_year_classes ayc
             JOIN classes c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ?
             ORDER BY c.id"
        );
        $stmt->execute([$academicYearId]);

        $summary = ['year_id' => $academicYearId, 'classes' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $summary['classes'][] = $this->seedForClass((int) $row['ayc_id'], (string) $row['class_name']);
        }
        return $summary;
    }

    /**
     * Seed coverage rows for a single class.
     *
     * @param int $aycId academic_year_classes.id
     * @param string $className classes.name
     * @return array Coverage summary for the class
     */
    public function seedForClass(int $aycId, string $className): array
    {
        $grade = $this->normalizeGrade($className);
        $result = [
            'ayc_id' => $aycId,
            'class_name' => $className,
            'grade_level' => $grade,
            'learning_areas' => 0,
            'strands' => 0,
            'sub_strands' => 0,
            'created' => 0,
            'skipped' => [],
        ];

        if ($grade === null) {
            $result['skipped'][] = 'no curriculum for this grade';
            return $result;
        }

        // Applicable learning areas (levels is a comma-separated grade list).
        // FIND_IN_SET does exact member matching without trimming spaces, so
        // strip spaces on both sides (members are e.g. ' PP2').
        $laStmt = $this->db->prepare(
            "SELECT id FROM learning_areas
             WHERE status = 'active'
               AND FIND_IN_SET(REPLACE(?, ' ', ''), REPLACE(levels, ' ', ''))
             ORDER BY id"
        );
        $laStmt->execute([$grade]);
        $learningAreaIds = array_map('intval', $laStmt->fetchAll(PDO::FETCH_COLUMN));

        $strandStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM strands WHERE grade_level = ? AND status = 'active'"
        );
        $strandStmt->execute([$grade]);
        $result['strands'] = (int) $strandStmt->fetchColumn();

        $subStrandStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM sub_strands WHERE grade_level = ? AND status = 'active'"
        );
        $subStrandStmt->execute([$grade]);
        $result['sub_strands'] = (int) $subStrandStmt->fetchColumn();

        $existingStmt = $this->db->prepare(
            "SELECT 1 FROM academic_year_class_learning_areas
             WHERE academic_year_class_id = ? AND learning_area_id = ?"
        );

        foreach ($learningAreaIds as $learningAreaId) {
            $existingStmt->execute([$aycId, $learningAreaId]);
            if ($existingStmt->fetchColumn()) {
                continue; // already covered - idempotent
            }

            $ins = $this->db->prepare(
                "INSERT INTO academic_year_class_learning_areas
                    (academic_year_class_id, learning_area_id, status, planned_weeks)
                 VALUES (?, ?, 'planned', NULL)"
            );
            $ins->execute([$aycId, $learningAreaId]);
            $result['created']++;
        }

        $result['learning_areas'] = count($learningAreaIds);
        return $result;
    }

    /**
     * Return the curriculum coverage for one class: each learning area with its
     * strand and sub-strand counts for that grade (for display/planning).
     */
    public function getClassCoverage(int $aycId): array
    {
        $sql = "
            SELECT la.id AS learning_area_id, la.name AS learning_area_name, la.code,
                   la.is_optional,
                   (SELECT COUNT(*) FROM strands st
                     WHERE st.learning_area_id = la.id
                       AND st.grade_level = c.name AND st.status = 'active') AS strand_count,
                   (SELECT COUNT(*) FROM sub_strands ss
                     JOIN strands st2 ON st2.id = ss.strand_id
                     WHERE st2.learning_area_id = la.id
                       AND ss.grade_level = c.name AND ss.status = 'active') AS sub_strand_count
            FROM academic_year_class_learning_areas acla
            JOIN learning_areas la ON la.id = acla.learning_area_id
            JOIN academic_year_classes ayc ON ayc.id = acla.academic_year_class_id
            JOIN classes c ON c.id = ayc.class_id
            WHERE acla.academic_year_class_id = ?
            ORDER BY la.id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$aycId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['strand_count'] = (int) $row['strand_count'];
            $row['sub_strand_count'] = (int) $row['sub_strand_count'];
            $row['is_optional'] = (int) $row['is_optional'];
        }
        return $rows;
    }
}
