<?php

namespace App\API\Modules\students;

use App\API\Services\ReadReplicaService;
use App\API\Services\StaffRecordsService;
use PDO;

/**
 * Who may see which part of a learner's record, decided per viewer and per
 * learner. Each viewer gets the data relevant to their job and nothing more:
 *
 *  - Finance (fees, sponsorships, waivers, balances): Director, School
 *    Administrator, Accountant only. Parents see their own children's fees
 *    on the parent portal, which does not use these endpoints.
 *  - Academic leadership (Director, School Admin, Headteacher, Deputies):
 *    the full learner record.
 *  - Class teacher of a stream the learner is or was in: pastoral record
 *    (contacts, health, attendance, performance, academic record, discipline).
 *  - Any teacher who teaches or taught the learner a learning area: a basic
 *    profile and their own teaching history with the learner (learning areas,
 *    where and when, how the learner performed). Nothing else.
 *  - Accountant: basic profile, guardian contacts and finance.
 *  - Matron: basic profile, health, contacts, attendance.
 *  - Counsellor/Chaplain: basic profile, discipline, health, contacts.
 * Everyone else: no access.
 */
final class StudentDataAccessService
{
    public const FINANCE_ROLES = [3, 4, 10];
    private const LEADERSHIP_ROLES = [3, 4, 5, 6, 63];
    private const TEACHER_VIEW = 'vw_teacher_effective_stream_learning_areas';

    public function __construct(private PDO $db) {}

    /** @return array{access:bool, relations:list<string>, sections:list<string>} */
    public function forViewer(int $userId, array $roleIds, int $studentId): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $sections = [];
        $relations = [];
        $add = static function (string $relation, array $grant) use (&$sections, &$relations): void {
            $relations[] = $relation;
            $sections = array_merge($sections, $grant);
        };

        if ($studentId < 1 || !$this->studentExists($studentId)) {
            return ['access' => false, 'relations' => [], 'sections' => []];
        }
        if (array_intersect(self::LEADERSHIP_ROLES, $roleIds)) {
            $add('leadership', ['profile', 'personal', 'contacts', 'health', 'attendance', 'performance', 'record', 'discipline']);
        }
        if (array_intersect(self::FINANCE_ROLES, $roleIds)) {
            $add('finance', ['profile', 'contacts', 'finance']);
        }
        if (in_array(18, $roleIds, true)) {
            $add('boarding', ['profile', 'health', 'contacts', 'attendance']);
        }
        if (in_array(24, $roleIds, true)) {
            $add('welfare', ['profile', 'discipline', 'health', 'contacts']);
        }
        if (array_intersect([7, 8, 9], $roleIds)) {
            $staffId = StaffRecordsService::staffIdForUserId($this->db, $userId);
            if ($staffId > 0) {
                if ($this->isClassTeacherOf($staffId, $studentId)) {
                    $add('class_teacher', ['profile', 'contacts', 'health', 'attendance', 'performance', 'record', 'discipline', 'teaching_history']);
                }
                if ($this->hasTaught($staffId, $studentId)) {
                    $add('subject_teacher', ['profile', 'teaching_history']);
                }
            }
        }

        $sections = array_values(array_unique($sections));
        return ['access' => $sections !== [], 'relations' => array_values(array_unique($relations)), 'sections' => $sections];
    }

    public function allows(int $userId, array $roleIds, int $studentId, string $section): bool
    {
        return in_array($section, $this->forViewer($userId, $roleIds, $studentId)['sections'], true);
    }

    /** The identity and current placement shown to every permitted viewer. */
    public function basicProfile(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT student_id AS id, admission_no, first_name, middle_name, last_name, full_name, gender,
                    student_status AS status, student_type_code, student_type_name, photo_url,
                    class_name, stream_name, year_name, academic_year_id
               FROM ' . ReadReplicaService::qualifiedRef('student_directory') . '
              WHERE student_id = ?
              ORDER BY (enrollment_status = \'active\') DESC, academic_year_start_date DESC
              LIMIT 1'
        );
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * The teacher's own history with the learner: every year, term, class
     * stream and learning area they taught them, with the learner's formative
     * and summative results in exactly those learning areas.
     */
    public function teachingHistory(int $userId, int $studentId): array
    {
        $staffId = StaffRecordsService::staffIdForUserId($this->db, $userId);
        if ($staffId < 1) return [];

        $periods = $this->db->prepare(
            'SELECT DISTINCT sae.id AS enrollment_id, sae.academic_year_id, ts.academic_year_term_id,
                    ts.academic_year_class_stream_id, ts.learning_area_id, ts.scope_type,
                    csd.class_name, csd.stream_name, la.name AS learning_area_name
               FROM ' . self::TEACHER_VIEW . ' ts
               JOIN ' . ReadReplicaService::qualifiedRef('student_academic_enrollments') . ' sae
                 ON sae.academic_year_class_stream_id = ts.academic_year_class_stream_id AND sae.student_id = ?
               JOIN ' . ReadReplicaService::qualifiedRef('class_stream_directory') . ' csd
                 ON csd.academic_year_class_stream_id = ts.academic_year_class_stream_id
               JOIN ' . ReadReplicaService::qualifiedRef('learning_areas') . ' la ON la.id = ts.learning_area_id
              WHERE ts.staff_id = ?'
        );
        $periods->execute([$studentId, $staffId]);
        $rows = $periods->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) return [];

        $years = $this->labels('academic_years', 'id', 'year_name', array_column($rows, 'academic_year_id'));
        $terms = $this->labels('academic_year_terms', 'id', 'term_id', array_column($rows, 'academic_year_term_id'));

        // One entry per year x term x stream x learning area.
        $history = [];
        foreach ($rows as $r) {
            $key = implode('|', [$r['academic_year_id'], $r['academic_year_term_id'], $r['academic_year_class_stream_id'], $r['learning_area_id']]);
            $history[$key] ??= [
                'academic_year_id' => (int) $r['academic_year_id'],
                'year_name' => $years[(int) $r['academic_year_id']] ?? '',
                'academic_year_term_id' => $r['academic_year_term_id'] !== null ? (int) $r['academic_year_term_id'] : null,
                'term_name' => isset($terms[(int) $r['academic_year_term_id']]) ? 'Term ' . $terms[(int) $r['academic_year_term_id']] : 'Whole year',
                'class_name' => $r['class_name'],
                'stream_name' => $r['stream_name'],
                'learning_area_id' => (int) $r['learning_area_id'],
                'learning_area_name' => $r['learning_area_name'],
                'role' => $r['scope_type'] === 'class_teacher' ? 'Class teacher' : 'Subject teacher',
                'enrollment_id' => (int) $r['enrollment_id'],
                'stream_id' => (int) $r['academic_year_class_stream_id'],
                'formative' => [],
                'summative' => [],
            ];
        }

        foreach ($history as &$h) {
            $params = [$h['stream_id'], $h['learning_area_id']];
            $termSql = '';
            if ($h['academic_year_term_id']) { $termSql = ' AND a.academic_year_term_id = ?'; $params[] = $h['academic_year_term_id']; }
            $assessments = $this->db->prepare(
                'SELECT a.id, a.title, a.assessment_date, a.max_marks, a.is_formative
                   FROM ' . ReadReplicaService::qualifiedRef('assessments') . ' a
                  WHERE a.academic_year_class_stream_id = ? AND a.learning_area_id = ?' . $termSql . '
                  ORDER BY a.assessment_date'
            );
            $assessments->execute($params);
            foreach ($assessments->fetchAll(PDO::FETCH_ASSOC) as $a) {
                if ((int) $a['is_formative'] === 1) {
                    $s = $this->db->prepare('SELECT score, max_score, percentage, remarks FROM formative_scores WHERE assessment_id = ? AND student_id = ? LIMIT 1');
                    $s->execute([(int) $a['id'], $studentId]);
                    $score = $s->fetch(PDO::FETCH_ASSOC);
                    $h['formative'][] = [
                        'title' => $a['title'], 'date' => $a['assessment_date'],
                        'score' => $score ? (float) $score['score'] : null,
                        'max' => (float) ($score['max_score'] ?? $a['max_marks']),
                        'percentage' => $score && $score['percentage'] !== null ? (float) $score['percentage'] : null,
                        'level' => null,
                        'remarks' => $score['remarks'] ?? null,
                    ];
                } else {
                    $s = $this->db->prepare('SELECT marks_obtained, grade, remarks FROM ' . ReadReplicaService::qualifiedRef('assessment_results') . ' WHERE assessment_id = ? AND student_academic_enrollment_id = ? LIMIT 1');
                    $s->execute([(int) $a['id'], $h['enrollment_id']]);
                    $res = $s->fetch(PDO::FETCH_ASSOC);
                    $max = (float) $a['max_marks'];
                    $marks = $res && $res['marks_obtained'] !== null ? (float) $res['marks_obtained'] : null;
                    $h['summative'][] = [
                        'title' => $a['title'], 'date' => $a['assessment_date'], 'score' => $marks, 'max' => $max,
                        'percentage' => $marks !== null && $max > 0 ? round($marks / $max * 100, 1) : null,
                        'level' => $res['grade'] ?? null, 'remarks' => $res['remarks'] ?? null,
                    ];
                }
            }
            $pcts = array_values(array_filter(
                array_map(static fn($x) => $x['percentage'], array_merge($h['formative'], $h['summative'])),
                static fn($v) => $v !== null
            ));
            $h['average_percentage'] = $pcts ? round(array_sum($pcts) / count($pcts), 1) : null;
            unset($h['enrollment_id'], $h['stream_id']);
        }
        unset($h);

        $list = array_values($history);
        usort($list, static fn($a, $b) => [$b['academic_year_id'], $b['academic_year_term_id'] ?? 0, $a['learning_area_name']]
            <=> [$a['academic_year_id'], $a['academic_year_term_id'] ?? 0, $b['learning_area_name']]);
        return $list;
    }

    private function studentExists(int $studentId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM students WHERE id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        return (bool) $stmt->fetchColumn();
    }

    private function isClassTeacherOf(int $staffId, int $studentId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM ' . ReadReplicaService::qualifiedRef('student_academic_enrollments') . ' sae
               JOIN ' . ReadReplicaService::qualifiedRef('academic_year_class_streams') . ' aycs ON aycs.id = sae.academic_year_class_stream_id
              WHERE sae.student_id = ? AND aycs.class_teacher_id = ? LIMIT 1'
        );
        $stmt->execute([$studentId, $staffId]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasTaught(int $staffId, int $studentId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM ' . self::TEACHER_VIEW . ' ts
               JOIN ' . ReadReplicaService::qualifiedRef('student_academic_enrollments') . ' sae
                 ON sae.academic_year_class_stream_id = ts.academic_year_class_stream_id
              WHERE ts.staff_id = ? AND sae.student_id = ? LIMIT 1'
        );
        $stmt->execute([$staffId, $studentId]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array<int,string> id => label for a small keyed lookup. */
    private function labels(string $projection, string $idCol, string $labelCol, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT {$idCol}, {$labelCol} FROM " . ReadReplicaService::qualifiedRef($projection) . " WHERE {$idCol} IN ({$in})");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $out[(int) $r[$idCol]] = (string) $r[$labelCol];
        return $out;
    }
}
