<?php

namespace App\API\Modules\students;

use App\API\Services\ReadReplicaService;
use App\API\Services\StaffRecordsService;
use PDO;

/**
 * The learners a teacher teaches (or taught), by academic year, term, class
 * stream and learning area. Scope comes only from the teacher's own
 * stream x learning-area assignments; nothing outside them is returned.
 * Read-only: only identity and placement columns, no contact or health data.
 */
final class SubjectLearnersService
{
    private const TEACHER_VIEW = 'vw_teacher_effective_stream_learning_areas';

    public function __construct(private PDO $db) {}

    public function forUser(int $userId, array $filters): array
    {
        $staffId = StaffRecordsService::staffIdForUserId($this->db, $userId);
        if ($staffId < 1) {
            return ['years' => [], 'terms' => [], 'assignments' => [], 'learners' => [], 'filters' => []];
        }

        $years = $this->years($staffId);
        $yearIds = array_map(static fn(array $y): int => (int) $y['id'], $years);
        $yearId = (int) ($filters['academic_year_id'] ?? 0);
        if (!in_array($yearId, $yearIds, true)) {
            $current = array_values(array_filter($years, static fn(array $y): bool => !empty($y['is_current'])));
            $yearId = (int) ($current[0]['id'] ?? ($yearIds[0] ?? 0));
        }
        if ($yearId < 1) {
            return ['years' => $years, 'terms' => [], 'assignments' => [], 'learners' => [], 'filters' => []];
        }

        $terms = $this->terms($yearId);
        $termIds = array_map(static fn(array $t): int => (int) $t['id'], $terms);
        $termId = (int) ($filters['academic_year_term_id'] ?? 0);
        if (!in_array($termId, $termIds, true)) $termId = 0;

        $assignments = $this->assignments($staffId, $yearId, $termId);
        $streamId = (int) ($filters['academic_year_class_stream_id'] ?? 0);
        $areaId = (int) ($filters['learning_area_id'] ?? 0);

        return [
            'years' => $years,
            'terms' => $terms,
            'assignments' => $assignments,
            'learners' => $this->learners($staffId, $yearId, $termId, $streamId, $areaId, trim((string) ($filters['search'] ?? ''))),
            'filters' => [
                'academic_year_id' => $yearId,
                'academic_year_term_id' => $termId ?: null,
                'academic_year_class_stream_id' => $streamId ?: null,
                'learning_area_id' => $areaId ?: null,
            ],
        ];
    }

    /** Years in which this teacher has (or had) a teaching assignment. */
    private function years(int $staffId): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT ay.id, ay.year_name, ay.status, ay.start_date,
                    CASE WHEN ay.is_current = 1 THEN 1 ELSE 0 END AS is_current
               FROM ' . self::TEACHER_VIEW . ' ts
               JOIN ' . ReadReplicaService::qualifiedRef('academic_years') . ' ay ON ay.id = ts.academic_year_id
              WHERE ts.staff_id = ?
              ORDER BY ay.start_date DESC'
        );
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function terms(int $yearId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, term_id AS term_number, CONCAT(\'Term \', term_id) AS term_name, status, opening_date, closing_date
               FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . '
              WHERE academic_year_id = ? ORDER BY term_id, id'
        );
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Term filter: a whole-year assignment (no term) applies to every term. */
    private function termCondition(int $termId, array &$params): string
    {
        if ($termId < 1) return '';
        $params[] = $termId;
        return ' AND (ts.academic_year_term_id = ? OR ts.academic_year_term_id IS NULL)';
    }

    private function assignments(int $staffId, int $yearId, int $termId): array
    {
        $params = [$staffId, $yearId];
        $term = $this->termCondition($termId, $params);
        $stmt = $this->db->prepare(
            'SELECT DISTINCT ts.academic_year_class_stream_id, ts.learning_area_id,
                    csd.class_name, csd.stream_name, la.name AS learning_area_name
               FROM ' . self::TEACHER_VIEW . ' ts
               JOIN ' . ReadReplicaService::qualifiedRef('class_stream_directory') . ' csd
                 ON csd.academic_year_class_stream_id = ts.academic_year_class_stream_id
               JOIN ' . ReadReplicaService::qualifiedRef('learning_areas') . ' la ON la.id = ts.learning_area_id
              WHERE ts.staff_id = ? AND ts.academic_year_id = ?' . $term . '
              ORDER BY csd.class_name, csd.stream_name, la.name'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function learners(int $staffId, int $yearId, int $termId, int $streamId, int $areaId, string $search): array
    {
        $params = [$staffId, $yearId];
        $where = $this->termCondition($termId, $params);
        if ($streamId > 0) { $where .= ' AND ts.academic_year_class_stream_id = ?'; $params[] = $streamId; }
        if ($areaId > 0) { $where .= ' AND ts.learning_area_id = ?'; $params[] = $areaId; }
        if ($search !== '') {
            $where .= ' AND (sd.full_name LIKE ? OR sd.admission_no LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $stmt = $this->db->prepare(
            'SELECT DISTINCT sd.student_id AS id, sd.admission_no, sd.full_name, sd.gender,
                    sd.student_status AS status, sd.student_type_code, sd.enrollment_status,
                    sd.class_name, sd.stream_name, ts.academic_year_class_stream_id,
                    ts.learning_area_id, la.name AS learning_area_name
               FROM ' . self::TEACHER_VIEW . ' ts
               JOIN ' . ReadReplicaService::qualifiedRef('student_directory') . ' sd
                 ON sd.academic_year_class_stream_id = ts.academic_year_class_stream_id
                AND sd.academic_year_id = ts.academic_year_id
               JOIN ' . ReadReplicaService::qualifiedRef('learning_areas') . ' la ON la.id = ts.learning_area_id
              WHERE ts.staff_id = ? AND ts.academic_year_id = ?' . $where . '
              ORDER BY sd.class_name, sd.stream_name, la.name, sd.full_name
              LIMIT 3000'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
