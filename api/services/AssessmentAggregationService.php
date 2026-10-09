<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * DB-driven assessment aggregation (research: docs/CBC_ASSESSMENT_AGGREGATION_RESEARCH.md).
 *
 * Three layers:
 *  1. Term report layer — formative + summative -> competency level via
 *     school-defined thresholds (assessment_term_profiles; default 40/60).
 *     Scope resolution: exam_period -> term -> academic_year -> school_default,
 *     so two exams in the same term can use different grading scales.
 *  2. National composite layer — versioned KNEC weight profiles
 *     (assessment_composite_profiles + components). Weights are data, never
 *     code: KPSEA current 60% SBA + 40% national; KJSEA 20/20/60; KEYA 100% SBA.
 *  3. National results store — SA imports land in national_assessment_results
 *     as pending_review and must be approved before use.
 */
final class AssessmentAggregationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ------------------------------------------------------------------
    // Term aggregation profiles (formative/summative weighting)
    // ------------------------------------------------------------------

    /**
     * Resolve the active profile for a scope. Walks exam_period -> term ->
     * academic_year -> school_default and returns the most specific hit.
     */
    public function resolveProfile(?int $examPeriodId, ?int $termId, ?int $yearId): array
    {
        $checks = [];
        if ($examPeriodId) {
            $checks[] = ['scope' => 'exam_period', 'id' => $examPeriodId];
        }
        if ($termId) {
            $checks[] = ['scope' => 'term', 'id' => $termId];
        }
        if ($yearId) {
            $checks[] = ['scope' => 'academic_year', 'id' => $yearId];
        }
        $checks[] = ['scope' => 'school_default', 'id' => null];

        foreach ($checks as $c) {
            $sql = "SELECT p.*, gs.code AS grading_system_code, gs.name AS grading_system_name, gs.levels_count
                    FROM assessment_term_profiles p
                    JOIN grading_systems gs ON gs.id = p.grading_system_id
                    WHERE p.scope = :scope AND p.status = 'active'";
            $params = [':scope' => $c['scope']];
            if ($c['scope'] === 'exam_period') { $sql .= ' AND p.exam_period_id = :eid';  $params[':eid'] = $c['id']; }
            if ($c['scope'] === 'term')       { $sql .= ' AND p.academic_year_term_id = :tid'; $params[':tid'] = $c['id']; }
            if ($c['scope'] === 'academic_year') { $sql .= ' AND p.academic_year_id = :yid'; $params[':yid'] = $c['id']; }
            $sql .= ' LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $row['resolved_scope'] = $c['scope'];
                $row['bands'] = $this->bands((int) $row['grading_system_id']);
                return $row;
            }
        }
        throw new \RuntimeException('No active assessment_term_profiles row (school_default missing)');
    }

    /** Bands for a grading system, ordered best-to-worst. */
    public function bands(int $gradingSystemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, band_code, band_name, min_percentage, max_percentage, points,
                    achievement_level, performance_level, description, sort_order
             FROM grading_system_bands
             WHERE grading_system_id = :gsid
             ORDER BY sort_order"
        );
        $stmt->execute([':gsid' => $gradingSystemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Map a percentage to the band row of a grading system (null when out of range). */
    public function mapToBand(int $gradingSystemId, float $percentage): ?array
    {
        foreach ($this->bands($gradingSystemId) as $band) {
            if ($percentage >= (float) $band['min_percentage'] && $percentage <= (float) $band['max_percentage']) {
                return $band;
            }
        }
        return null;
    }

    /**
     * Compute a learner's term score for one learning area:
     *   formative average (is_formative=1 assessments in the term, percentage basis)
     *   summative (exam-period assessments in the term, percentage basis)
     *   term_score = formative * F% + summative * S%
     *   band = mapToBand(profile.grading_system_id, term_score)
     */
    public function computeTermScore(int $studentId, int $learningAreaId, int $termId, ?int $examPeriodId = null, ?int $yearId = null): array
    {
        $profile = $this->resolveProfile($examPeriodId, $termId, $yearId);
        $fWeight = ((float) $profile['formative_weight']) / 100.0;
        $sWeight = ((float) $profile['summative_weight']) / 100.0;

        $stmt = $this->db->prepare(
            "SELECT a.is_formative,
                    COUNT(ar.id) AS n,
                    AVG(100.0 * ar.marks_obtained / NULLIF(a.max_marks, 0)) AS avg_pct
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id
             JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
             WHERE sae.student_id = :sid
               AND ar.deleted_at IS NULL
               AND ar.entry_status = 'final'
               AND a.academic_year_term_id = :tid
               AND a.learning_area_id = :laid
             GROUP BY a.is_formative"
        );
        $stmt->execute([':sid' => $studentId, ':tid' => $termId, ':laid' => $learningAreaId]);
        $formative = ['n' => 0, 'avg' => null];
        $summative = ['n' => 0, 'avg' => null];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $bucket = ((int) $r['is_formative']) === 1 ? 'formative' : 'summative';
            ${$bucket} = ['n' => (int) $r['n'], 'avg' => $r['avg_pct'] !== null ? round((float) $r['avg_pct'], 2) : null];
        }

        $parts = [];
        if ($formative['avg'] !== null) $parts[] = $formative['avg'] * $fWeight;
        if ($summative['avg'] !== null) $parts[] = $summative['avg'] * $sWeight;
        $divisor = ($formative['avg'] !== null ? $fWeight : 0.0) + ($summative['avg'] !== null ? $sWeight : 0.0);
        $termScore = $divisor > 0 ? round(array_sum($parts) / $divisor, 2) : null;

        $band = $termScore !== null ? $this->mapToBand((int) $profile['grading_system_id'], $termScore) : null;

        return [
            'formative_avg' => $formative['avg'],
            'formative_count' => $formative['n'],
            'summative_avg' => $summative['avg'],
            'summative_count' => $summative['n'],
            'term_score' => $termScore,
            'band' => $band,
            'profile' => [
                'id' => (int) $profile['id'],
                'scope' => $profile['scope'],
                'resolved_scope' => $profile['resolved_scope'],
                'formative_weight' => (float) $profile['formative_weight'],
                'summative_weight' => (float) $profile['summative_weight'],
                'grading_system_id' => (int) $profile['grading_system_id'],
                'grading_system_code' => $profile['grading_system_code'],
                'grading_system_name' => $profile['grading_system_name'],
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Profile CRUD (school admin / headteacher / deputy management page)
    // ------------------------------------------------------------------

    public function listTermProfiles(): array
    {
        return $this->db->query(
            "SELECT p.id, p.scope, p.academic_year_id, p.academic_year_term_id, p.exam_period_id,
                    p.formative_weight, p.summative_weight, p.grading_system_id, p.title, p.notes,
                    p.status, p.created_at, p.updated_at,
                    gs.code AS grading_system_code, gs.name AS grading_system_name,
                    ay.year_code, t.name AS term_label, ep.title AS exam_title
             FROM assessment_term_profiles p
             JOIN grading_systems gs ON gs.id = p.grading_system_id
             LEFT JOIN academic_years ay ON ay.id = p.academic_year_id
             LEFT JOIN academic_year_terms ayt ON ayt.id = p.academic_year_term_id
             LEFT JOIN terms t ON t.id = ayt.term_id
             LEFT JOIN exam_periods ep ON ep.id = p.exam_period_id
             ORDER BY FIELD(p.scope,'school_default','academic_year','term','exam_period'), p.id"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Upsert: one active profile per scope target. Weights must total 100. */
    public function upsertTermProfile(array $data, int $userId): array
    {
        $scope = $data['scope'] ?? '';
        if (!in_array($scope, ['school_default', 'academic_year', 'term', 'exam_period'], true)) {
            return ['ok' => false, 'error' => 'Invalid scope'];
        }
        $yearId  = $scope === 'academic_year' || $scope === 'term' ? (int) ($data['academic_year_id'] ?? 0) : null;
        $termId  = $scope === 'term' ? (int) ($data['academic_year_term_id'] ?? 0) : null;
        $examId  = $scope === 'exam_period' ? (int) ($data['exam_period_id'] ?? 0) : null;
        if ($scope === 'academic_year' && !$yearId)  return ['ok' => false, 'error' => 'academic_year_id is required'];
        if ($scope === 'term' && !$termId)          return ['ok' => false, 'error' => 'academic_year_term_id is required'];
        if ($scope === 'exam_period' && !$examId)    return ['ok' => false, 'error' => 'exam_period_id is required'];

        $f = (float) ($data['formative_weight'] ?? 40);
        $s = (float) ($data['summative_weight'] ?? 60);
        if ($f < 0 || $s < 0 || abs($f + $s - 100) > 0.01) {
            return ['ok' => false, 'error' => 'Weights must total 100%'];
        }
        $gsId = (int) ($data['grading_system_id'] ?? 1);
        $check = $this->db->prepare("SELECT id FROM grading_systems WHERE id = :id AND status='active'");
        $check->execute([':id' => $gsId]);
        if (!$check->fetch()) return ['ok' => false, 'error' => 'Unknown grading system'];

        $notes  = trim((string) ($data['notes'] ?? '')) ?: null;
        $title  = trim((string) ($data['title'] ?? '')) ?: null;
        $status = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

        // Deactivate any existing active profile for the same target first.
        $sql = "UPDATE assessment_term_profiles SET status='inactive' WHERE status='active' AND scope = :scope";
        $params = [':scope' => $scope];
        if ($scope === 'school_default') {
            $sql .= ' AND academic_year_id IS NULL AND academic_year_term_id IS NULL AND exam_period_id IS NULL';
        } else {
            $col = $scope === 'academic_year' ? 'academic_year_id' : ($scope === 'term' ? 'academic_year_term_id' : 'exam_period_id');
            $sql .= " AND $col = :tid";
            $params[':tid'] = (int) ($scope === 'academic_year' ? $yearId : ($scope === 'term' ? $termId : $examId));
        }
        $this->db->prepare($sql)->execute($params);

        $ins = $this->db->prepare(
            "INSERT INTO assessment_term_profiles
               (scope, academic_year_id, academic_year_term_id, exam_period_id,
                formative_weight, summative_weight, grading_system_id, title, notes, status, created_by)
             VALUES (:scope, :yid, :tid, :eid, :f, :s, :gsid, :title, :notes, :status, :uid)"
        );
        $ins->execute([
            ':scope' => $scope, ':yid' => $yearId, ':tid' => $termId, ':eid' => $examId,
            ':f' => $f, ':s' => $s, ':gsid' => $gsId,
            ':title' => $title, ':notes' => $notes, ':status' => $status, ':uid' => $userId,
        ]);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function deactivateTermProfile(int $profileId): array
    {
        $stmt = $this->db->prepare("UPDATE assessment_term_profiles SET status='inactive' WHERE id = :id");
        $stmt->execute([':id' => $profileId]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    // ------------------------------------------------------------------
    // Grading systems + bands CRUD
    // ------------------------------------------------------------------

    public function listGradingSystems(): array
    {
        $systems = $this->db->query(
            "SELECT id, code, name, levels_count, category, description, status
             FROM grading_systems ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($systems as &$sys) {
            $sys['bands'] = $this->bands((int) $sys['id']);
        }
        return $systems;
    }

    public function upsertBand(array $data): array
    {
        $required = ['grading_system_id', 'band_code', 'band_name', 'min_percentage', 'max_percentage'];
        foreach ($required as $k) {
            if (!isset($data[$k]) || $data[$k] === '') return ['ok' => false, 'error' => "$k is required"];
        }
        $gsId = (int) $data['grading_system_id'];
        $min = (float) $data['min_percentage'];
        $max = (float) $data['max_percentage'];
        if ($min < 0 || $max > 100 || $min > $max) return ['ok' => false, 'error' => 'Invalid percentage range'];

        $fields = [
            ':gsid' => $gsId,
            ':code' => strtoupper(trim((string) $data['band_code'])),
            ':name' => trim((string) $data['band_name']),
            ':min' => $min,
            ':max' => $max,
            ':points' => (float) ($data['points'] ?? 0),
            ':level' => trim((string) ($data['performance_level'] ?? '')),
            ':desc' => trim((string) ($data['description'] ?? '')) ?: null,
            ':sort' => (int) ($data['sort_order'] ?? 0),
        ];
        $ach = $data['achievement_level'] ?? null;
        if ($ach !== null && $ach !== '') { $fields[':ach'] = (int) $ach; }
        $achSql = $ach !== null && $ach !== '' ? ', achievement_level' : '';
        $achVal = $ach !== null && $ach !== '' ? ', :ach' : '';

        if (!empty($data['id'])) {
            $sets = "band_code=:code, band_name=:name, min_percentage=:min, max_percentage=:max,
                     points=:points, performance_level=:level, description=:desc, sort_order=:sort";
            if ($ach !== null && $ach !== '') { $sets .= ', achievement_level=:ach'; }
            $stmt = $this->db->prepare("UPDATE grading_system_bands SET $sets WHERE id=:id AND grading_system_id=:gsid");
            $stmt->execute($fields + [':id' => (int) $data['id']]);
            return ['ok' => true, 'id' => (int) $data['id']];
        }
        // Overlap check within the system.
        $overlap = $this->db->prepare(
            "SELECT COUNT(*) FROM grading_system_bands
             WHERE grading_system_id = :gsid AND (:min BETWEEN min_percentage AND max_percentage OR :max BETWEEN min_percentage AND max_percentage)"
        );
        $overlap->execute([':gsid' => $gsId, ':min' => $min, ':max' => $max]);
        if ((int) $overlap->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Range overlaps an existing band in this system'];
        }
        $stmt = $this->db->prepare(
            "INSERT INTO grading_system_bands
               (grading_system_id, band_code, band_name, min_percentage, max_percentage, points, performance_level, description, sort_order$achSql)
             VALUES (:gsid, :code, :name, :min, :max, :points, :level, :desc, :sort$achVal)"
        );
        $stmt->execute($fields);
        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    public function deleteBand(int $bandId): array
    {
        $stmt = $this->db->prepare("DELETE FROM grading_system_bands WHERE id = :id");
        $stmt->execute([':id' => $bandId]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    // ------------------------------------------------------------------
    // National composite profiles (read-only from management page)
    // ------------------------------------------------------------------

    public function listCompositeProfiles(): array
    {
        $profiles = $this->db->query(
            "SELECT id, code, name, assessment_code, description, status, version, effective_note
             FROM assessment_composite_profiles
             ORDER BY assessment_code, version"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$profiles) return [];
        $ids = implode(',', array_map('intval', array_column($profiles, 'id')));
        $compStmt = $this->db->query(
            "SELECT profile_id, component_code, component_label, source_grades, weight_percent, sort_order
             FROM assessment_composite_components WHERE profile_id IN ($ids) ORDER BY profile_id, sort_order"
        );
        $byProfile = [];
        foreach ($compStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $byProfile[(int) $c['profile_id']][] = $c;
        }
        foreach ($profiles as &$p) {
            $p['components'] = $byProfile[(int) $p['id']] ?? [];
            $sum = 0.0;
            foreach ($p['components'] as $c) $sum += (float) $c['weight_percent'];
            $p['weights_total'] = round($sum, 2);
        }
        return $profiles;
    }

    /**
     * Compute a learner's national composite for an assessment code using the
     * ACTIVE profile. Inputs: component averages (percentage basis).
     * $inputs = ['sba' => 65.0, 'national_exam' => 82.0, 'banked_kpsea' => 78.0]
     */
    public function computeComposite(string $assessmentCode, array $inputs): array
    {
        $profile = $this->db->prepare(
            "SELECT id, code, name FROM assessment_composite_profiles
             WHERE assessment_code = :code AND status = 'active' ORDER BY version DESC LIMIT 1"
        );
        $profile->execute([':code' => $assessmentCode]);
        $profile = $profile->fetch(PDO::FETCH_ASSOC);
        if (!$profile) return ['ok' => false, 'error' => 'No active composite profile for ' . $assessmentCode];

        $comp = $this->db->prepare(
            "SELECT component_code, component_label, source_grades, weight_percent
             FROM assessment_composite_components WHERE profile_id = :pid ORDER BY sort_order"
        );
        $comp->execute([':pid' => (int) $profile['id']]);
        $rows = $comp->fetchAll(PDO::FETCH_ASSOC);

        $total = 0.0; $missing = []; $parts = [];
        foreach ($rows as $r) {
            $code = $r['component_code'];
            $w = ((float) $r['weight_percent']) / 100.0;
            if (!isset($inputs[$code]) || $inputs[$code] === null) {
                $missing[] = $code;
                continue;
            }
            $contribution = ((float) $inputs[$code]) * $w;
            $total += $contribution;
            $parts[] = [
                'component' => $code,
                'label' => $r['component_label'],
                'value' => (float) $inputs[$code],
                'weight' => (float) $r['weight_percent'],
                'contribution' => round($contribution, 2),
            ];
        }
        return [
            'ok' => true,
            'profile' => ['code' => $profile['code'], 'name' => $profile['name']],
            'composite' => round($total, 2),
            'parts' => $parts,
            'missing_inputs' => $missing,
        ];
    }

    // ------------------------------------------------------------------
    // CBA-compatible SBA export (per requested classes)
    // ------------------------------------------------------------------

    /**
     * CBA-portal-compatible CSV: one row per learner, one column per learning
     * area, percentage SBA scores for the selected term. Header row carries
     * school identity + period so the file is self-describing for KNEC review.
     */
    public function buildCbaExport(array $classStreamIds, int $termId, ?int $classId = null, ?int $streamId = null): array
    {
        $term = $this->db->prepare(
            "SELECT ayt.id, ay.year_code, ay.id AS academic_year_id, t.name AS term_name
             FROM academic_year_terms ayt
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.id = :tid"
        );
        $term->execute([':tid' => $termId]);
        $term = $term->fetch(PDO::FETCH_ASSOC);
        if (!$term) return ['ok' => false, 'error' => 'Unknown term'];

        // Resolve class/stream filters into academic_year_class_stream ids.
        // With no explicit scope, export ALL active classes in the term's year.
        if (!$classStreamIds) {
            $sql = "SELECT id FROM vw_class_stream_directory
                    WHERE academic_year_id = :yid AND class_stream_status = 'active'";
            $params = [':yid' => (int) $term['academic_year_id']];
            if ($classId)  { $sql .= ' AND class_id = :cid';   $params[':cid'] = $classId; }
            if ($streamId) { $sql .= ' AND stream_id = :sid'; $params[':sid'] = $streamId; }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $classStreamIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (!$classStreamIds) return ['ok' => false, 'error' => 'No matching class streams for the selected scope'];
        }

        $ids = implode(',', array_map('intval', $classStreamIds));

        // Learning areas with SBA evidence in the term for these streams.
        $areas = $this->db->query(
            "SELECT DISTINCT a.learning_area_id, la.name AS area_name, la.code AS area_code
             FROM assessments a
             JOIN learning_areas la ON la.id = a.learning_area_id
             WHERE a.academic_year_term_id = {$termId}
               AND a.academic_year_class_stream_id IN ($ids)
               AND a.assessment_type_classification_id = 2
             ORDER BY la.name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $areaIds = array_map('intval', array_column($areas, 'learning_area_id'));
        $areaCols = $areaIds ? implode(',', $areaIds) : '0';

        // Learners enrolled in these streams.
        $learners = $this->db->query(
            "SELECT s.id AS student_id, s.admission_no, p.first_name, p.last_name,
                    csd.class_name, csd.stream_name
             FROM student_academic_enrollments sae
             JOIN students s ON s.id = sae.student_id
             JOIN persons p ON p.id = s.person_id
             JOIN vw_class_stream_directory csd ON csd.id = sae.academic_year_class_stream_id
             WHERE sae.academic_year_class_stream_id IN ($ids)
               AND sae.enrollment_status = 'active'
             ORDER BY csd.class_name, csd.stream_name, p.last_name, p.first_name"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$learners) return ['ok' => false, 'error' => 'No active learners in the selected classes'];

        // SBA percentages: learner x area -> avg percentage.
        $scoresStmt = $this->db->prepare(
            "SELECT ar.student_academic_enrollment_id, a.learning_area_id,
                    AVG(100.0 * ar.marks_obtained / NULLIF(a.max_marks, 0)) AS pct
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id
             WHERE a.academic_year_term_id = :tid
               AND a.assessment_type_classification_id = 2
               AND a.academic_year_class_stream_id IN ($ids)
               AND a.learning_area_id IN ($areaCols)
               AND ar.deleted_at IS NULL AND ar.entry_status = 'final'
             GROUP BY ar.student_academic_enrollment_id, a.learning_area_id"
        );
        $scoresStmt->execute([':tid' => $termId]);
        $scores = [];
        foreach ($scoresStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $scores[(int) $row['student_academic_enrollment_id']][(int) $row['learning_area_id']] = round((float) $row['pct'], 2);
        }

        $enrollmentToStudent = [];
        foreach ($this->db->query(
            "SELECT id, student_id FROM student_academic_enrollments WHERE academic_year_class_stream_id IN ($ids) AND enrollment_status='active'"
        )->fetchAll(PDO::FETCH_ASSOC) as $en) {
            $enrollmentToStudent[(int) $en['id']] = (int) $en['student_id'];
        }

        // Key the SBA averages by student (they come keyed by enrollment).
        $scoresByKey = [];
        foreach ($scores as $enrId => $byArea) {
            $sid = $enrollmentToStudent[$enrId] ?? null;
            if ($sid) $scoresByKey[$sid] = $byArea;
        }

        // Build CSV.
        $header = ['ADM_NO', 'LEARNER_NAME', 'CLASS', 'STREAM'];
        foreach ($areas as $ar) $header[] = strtoupper((string) $ar['area_code']) . ' - ' . $ar['area_name'];
        $lines = [implode(',', $header)];
        foreach ($learners as $l) {
            $sid = (int) $l['student_id'];
            $row = [
                (string) $l['admission_no'],
                trim(($l['first_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                (string) $l['class_name'],
                (string) $l['stream_name'],
            ];
            foreach ($areaIds as $aid) {
                $row[] = isset($scoresByKey[$sid][$aid]) ? (string) $scoresByKey[$sid][$aid] : '';
            }
            $lines[] = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row));
        }

        $meta = [
            'school' => 'Kingsway Preparatory School',
            'assessment' => 'School-Based Assessment (SBA)',
            'year' => $term['year_code'],
            'term' => $term['term_name'],
            'portal' => 'cba.knec.ac.ke',
        ];
        array_unshift($lines, '# ' . implode(' | ', array_map(fn($k, $v) => "$k: $v", array_keys($meta), $meta)));

        return [
            'ok' => true,
            'filename' => 'Kingsway_SBA_' . preg_replace('/\W+/', '_', (string) $term['term_name']) . '_' . date('Ymd_His') . '.csv',
            'csv' => implode("\r\n", $lines),
            'learner_count' => count($learners),
            'area_count' => count($areas),
        ];
    }

    // ------------------------------------------------------------------
    // National results import (Excel/CSV) -> pending_review
    // ------------------------------------------------------------------

    /**
     * Parse a CSV of national results into pending_review rows.
     * Expected columns (header): admission_no (or admission_number), assessment_code, percentage[, learning_area_code]
     */
    public function importNationalResultsCsv(string $csv, int $yearId, int $importedBy): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));
        if (count($lines) < 2) return ['ok' => false, 'error' => 'CSV requires a header and at least one data row'];
        $header = array_map('trim', str_getcsv((string) array_shift($lines), ',', '"', '\\'));
        $admKey = in_array('admission_no', $header, true) ? 'admission_no'
            : (in_array('admission_number', $header, true) ? 'admission_number' : null);
        $codeKey = in_array('assessment_code', $header, true) ? 'assessment_code' : null;
        $pctKey = in_array('percentage', $header, true) ? 'percentage' : null;
        if (!$admKey)  return ['ok' => false, 'error' => 'Missing required column: admission_no'];
        if (!$codeKey) return ['ok' => false, 'error' => 'Missing required column: assessment_code'];
        if (!$pctKey)  return ['ok' => false, 'error' => 'Missing required column: percentage'];
        $idx = array_flip($header);
        $laCol = $idx['learning_area_code'] ?? null;

        $studentStmt = $this->db->prepare("SELECT id FROM students WHERE admission_no = :adm LIMIT 1");
        $insert = $this->db->prepare(
            "INSERT INTO national_assessment_results
               (assessment_code, assessment_year_id, student_id, learning_area_id, percentage, source_file, source_row_hash, imported_by)
             VALUES (:code, :yid, :sid, :laid, :pct, :file, :hash, :uid)
             ON DUPLICATE KEY UPDATE percentage = VALUES(percentage), updated_at = NOW()"
        );

        $rows = 0; $skipped = [];
        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(trim($line), '#')) continue;
            $cells = array_map('trim', str_getcsv($line, ',', '"', '\\'));
            $adm = $cells[$idx[$admKey]] ?? '';
            $code = strtoupper($cells[$idx[$codeKey]] ?? '');
            $pctRaw = $cells[$idx[$pctKey]] ?? '';
            if ($adm === '' || $code === '' || $pctRaw === '') { $skipped[] = 'missing fields'; continue; }
            if (!is_numeric($pctRaw)) { $skipped[] = "non-numeric percentage for $adm"; continue; }
            $pct = round(max(0.0, min(100.0, (float) $pctRaw)), 2);

            $studentStmt->execute([':adm' => $adm]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) { $skipped[] = "unknown admission number $adm"; continue; }

            $laid = null;
            if ($laCol !== null && ($cells[$laCol] ?? '') !== '') {
                $laStmt = $this->db->prepare("SELECT id FROM learning_areas WHERE code = :code LIMIT 1");
                $laStmt->execute([':code' => $cells[$laCol]]);
                $la = $laStmt->fetch(PDO::FETCH_ASSOC);
                if (!$la) { $skipped[] = "unknown learning area {$cells[$laCol]}"; continue; }
                $laid = (int) $la['id'];
            }

            $hash = sha1($line);
            $insert->execute([
                ':code' => $code, ':yid' => $yearId, ':sid' => (int) $student['id'],
                ':laid' => $laid, ':pct' => $pct,
                ':file' => 'upload:' . date('Ymd_His'), ':hash' => $hash, ':uid' => $importedBy,
            ]);
            $rows++;
        }
        return ['ok' => true, 'imported' => $rows, 'skipped' => array_count_values($skipped)];
    }

    public function listNationalResults(array $filters = []): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($filters['assessment_code'])) { $where[] = 'nar.assessment_code = :code'; $params[':code'] = strtoupper($filters['assessment_code']); }
        if (!empty($filters['import_status'])) { $where[] = 'nar.import_status = :st'; $params[':st'] = $filters['import_status']; }
        if (!empty($filters['student_id'])) { $where[] = 'nar.student_id = :sid'; $params[':sid'] = (int) $filters['student_id']; }
        $sql = "SELECT nar.*, s.admission_no,
                       CONCAT(p.first_name,' ',p.last_name) AS learner_name,
                       la.code AS learning_area_code
                FROM national_assessment_results nar
                JOIN students s ON s.id = nar.student_id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN learning_areas la ON la.id = nar.learning_area_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY nar.id DESC LIMIT 1000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reviewNationalResult(int $resultId, string $decision, int $reviewedBy): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) return ['ok' => false, 'error' => 'Invalid decision'];
        $stmt = $this->db->prepare(
            "UPDATE national_assessment_results
             SET import_status = :st, reviewed_by = :uid, reviewed_at = NOW()
             WHERE id = :id AND import_status = 'pending_review'"
        );
        $stmt->execute([':st' => $decision, ':uid' => $reviewedBy, ':id' => $resultId]);
        return ['ok' => $stmt->rowCount() > 0];
    }

    // ------------------------------------------------------------------
    // Term exam analysis: per exam -> papers -> exams average -> term blend
    // ------------------------------------------------------------------

    /**
     * Full term analysis for the View Results "Average" tab.
     *
     * Model (user-specified, 2026-10-10):
     *  - Each summative exam (Midterm CA, Endterm CA/SBA, national…) is viewed
     *    per learner x learning area, per PAPER where papers exist.
     *  - A summative exam carries the formative work AS OF ITS DATE: the
     *    midterm picks formatives taken before the midterm window; the
     *    endterm picks the whole term's formatives.
     *  - exams_average = mean of the per-exam percentages.
     *  - term_score = profile blend (default 40% formative + 60% summative),
     *    using the exams_average as the summative component and the whole-term
     *    formative average as the formative component; band via the resolved
     *    grading system. Per-exam blends use each period's own resolved
     *    profile (two exams in one term can carry different scales/weights).
     */
    public function examTermAnalysis(array $filters): array
    {
        $termId = (int) ($filters['term_id'] ?? 0);
        if (!$termId) return ['ok' => false, 'error' => 'term_id is required'];
        $yearId = (int) ($filters['year_id'] ?? 0);
        $classId = (int) ($filters['class_id'] ?? 0);
        $streamId = (int) ($filters['stream_id'] ?? 0);
        $studentId = (int) ($filters['student_id'] ?? 0);
        $search = trim((string) ($filters['search'] ?? ''));

        // ---- Exam periods in the term (the summative column set) ----
        $periods = $this->db->prepare(
            "SELECT ep.id, ep.title, ep.starts_on, ep.ends_on,
                    ep.assessment_type_classification_id, atc.code AS type_code, atc.name AS type_name
             FROM exam_periods ep
             LEFT JOIN assessment_type_classifications atc ON atc.id = ep.assessment_type_classification_id
             WHERE ep.academic_year_term_id = :tid
               AND ep.status <> 'cancelled' AND ep.deleted_at IS NULL
             ORDER BY ep.starts_on, ep.id"
        );
        $periods->execute([':tid' => $termId]);
        $periods = $periods->fetchAll(PDO::FETCH_ASSOC);
        $periodIds = array_map('intval', array_column($periods, 'id'));

        // Enrollment scope SQL (class / stream / learner) shared by the queries.
        $scopeJoin = ' JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id ';
        $scopeWhere = ''; $scopeParams = [':tid' => $termId];
        if ($classId)  { $scopeWhere .= ' AND aycs.academic_year_class_id = :cid';  $scopeParams[':cid'] = $classId; }
        if ($streamId) { $scopeWhere .= ' AND aycs.stream_id = :sid';               $scopeParams[':sid'] = $streamId; }
        if ($studentId){ $scopeWhere .= ' AND sae.student_id = :stid';             $scopeParams[':stid'] = $studentId; }

        // ---- Summative results per exam (register totals) ----
        $exams = [];
        if ($periodIds) {
            $piList = implode(',', $periodIds);
            $sql = "SELECT ep.id AS exam_period_id, sae.student_id, a.learning_area_id, ar.assessment_id,
                           ar.marks_obtained, a.max_marks
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
                    {$scopeJoin}
                    JOIN exam_schedule_assessments esa ON esa.assessment_id = ar.assessment_id
                    JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = esa.exam_schedule_id
                    JOIN exam_period_class_learning_areas epcla ON epcla.id = ept.exam_period_class_learning_area_id
                    JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
                    JOIN exam_periods ep ON ep.id = epc.exam_period_id AND ep.id IN ({$piList})
                    WHERE ar.deleted_at IS NULL AND ar.entry_status = 'final' {$scopeWhere}";
            $stmt = $this->db->prepare($sql);
            // The exams query scopes periods via the IN list, not :tid — drop it
            // or native prepares reject the unused bind (HY093).
            $examParams = array_filter($scopeParams, fn($k) => $k !== ':tid', ARRAY_FILTER_USE_KEY);
            $stmt->execute($examParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $key = ((int) $r['exam_period_id']) . ':' . ((int) $r['student_id']) . ':' . ((int) $r['learning_area_id']);
                $max = (float) $r['max_marks'];
                $exams[$key][] = [
                    'assessment_id' => (int) $r['assessment_id'],
                    'marks' => $r['marks_obtained'] !== null ? (float) $r['marks_obtained'] : null,
                    'max' => $max > 0 ? $max : null,
                ];
            }
        }

        // ---- Papers detail for the same term ----
        $papers = [];
        if ($periodIds) {
            $sql = "SELECT ap.assessment_id, CONCAT('P', ap.paper_number, ' ', ap.title) AS paper_code, ap.max_marks AS paper_max,
                           sae.student_id, apr.marks_obtained
                    FROM assessment_papers ap
                    JOIN assessments a ON a.id = ap.assessment_id AND a.academic_year_term_id = :tid AND a.is_formative = 0
                    JOIN assessment_paper_results apr ON apr.assessment_paper_id = ap.id AND apr.entry_status = 'final'
                    JOIN student_academic_enrollments sae ON sae.id = apr.student_academic_enrollment_id
                    {$scopeJoin}
                    WHERE 1=1 {$scopeWhere}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($scopeParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $papers[(int) $r['assessment_id']][(int) $r['student_id']][] = [
                    'code' => (string) $r['paper_code'],
                    'marks' => $r['marks_obtained'] !== null ? (float) $r['marks_obtained'] : null,
                    'max' => (float) $r['paper_max'],
                ];
            }
        }

        // ---- Formative evidence: both stores, deduped (formative_scores wins) ----
        $formative = [];
        $seenFormative = [];
        $fs = $this->db->prepare(
            "SELECT DISTINCT fs.student_id, a.learning_area_id, a.assessment_date AS taken_on, fs.percentage AS pct
             FROM formative_scores fs
             JOIN assessments a ON a.id = fs.assessment_id AND a.academic_year_term_id = :tid AND a.is_formative = 1
             JOIN student_academic_enrollments sae ON sae.student_id = fs.student_id AND sae.enrollment_status = 'active'
             {$scopeJoin}
             WHERE fs.percentage IS NOT NULL {$scopeWhere}"
        );
        $fs->execute($scopeParams);
        foreach ($fs->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sid = (int) $r['student_id'];
            $formative[$sid][(int) $r['learning_area_id']][] = ['on' => (string) $r['taken_on'], 'pct' => (float) $r['pct']];
            $seenFormative[$sid][] = (int) $r['learning_area_id'];
        }
        $ar = $this->db->prepare(
            "SELECT sae.student_id, a.learning_area_id, a.assessment_date AS taken_on,
                    100.0 * ar.marks_obtained / NULLIF(a.max_marks, 0) AS pct
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id AND a.academic_year_term_id = :tid AND a.is_formative = 1
             JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
             {$scopeJoin}
             WHERE ar.deleted_at IS NULL AND ar.entry_status = 'final'
               AND ar.marks_obtained IS NOT NULL AND a.max_marks > 0 {$scopeWhere}"
        );
        $ar->execute($scopeParams);
        foreach ($ar->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $sid = (int) $r['student_id'];
            // Dedup: skip when formative_scores already covered this learner+area.
            if (in_array((int) $r['learning_area_id'], $seenFormative[$sid] ?? [], true)) continue;
            $formative[$sid][(int) $r['learning_area_id']][] = ['on' => (string) $r['taken_on'], 'pct' => round((float) $r['pct'], 2)];
        }

        // ---- Learners in scope ----
        $learnerSql = "SELECT sae.student_id, s.admission_no, p.first_name, p.middle_name, p.last_name,
                              csd.class_name, csd.stream_name
                       FROM student_academic_enrollments sae
                       JOIN students s ON s.id = sae.student_id
                       JOIN persons p ON p.id = s.person_id
                       JOIN vw_class_stream_directory csd ON csd.id = sae.academic_year_class_stream_id
                       {$scopeJoin}
                       WHERE sae.enrollment_status = 'active' AND csd.academic_year_id =
                             (SELECT academic_year_id FROM academic_year_terms WHERE id = :tid) {$scopeWhere}";
        if ($search !== '') {
            $learnerSql .= ' AND (p.first_name LIKE :q OR p.middle_name LIKE :q OR p.last_name LIKE :q OR s.admission_no LIKE :q)';
            $scopeParams[':q'] = "%{$search}%";
        }
        $learnerSql .= ' GROUP BY sae.student_id, csd.class_name, csd.stream_name, s.admission_no, p.first_name, p.middle_name, p.last_name ORDER BY csd.class_name, csd.stream_name, p.last_name, p.first_name';
        $learners = $this->db->prepare($learnerSql);
        $learners->execute($scopeParams);
        $learners = $learners->fetchAll(PDO::FETCH_ASSOC);

        // ---- Learning areas with any evidence in scope ----
        $areas = [];
        $areaStmt = $this->db->prepare(
            "SELECT DISTINCT a.learning_area_id, la.name AS learning_area_name, la.code AS learning_area_code
             FROM assessments a
             JOIN learning_areas la ON la.id = a.learning_area_id
             JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = a.academic_year_class_stream_id
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             WHERE a.academic_year_term_id = :tid {$scopeWhere}
             ORDER BY la.name"
        );
        $areaStmt->execute(array_filter($scopeParams, fn($k) => $k !== ':q', ARRAY_FILTER_USE_KEY));
        $areas = $areaStmt->fetchAll(PDO::FETCH_ASSOC);

        // ---- Profiles: term-level + per-period resolutions ----
        $termProfile = $this->resolveProfile(null, $termId, $yearId ?: null);
        $periodProfiles = [];
        foreach ($periods as $p) {
            $periodProfiles[(int) $p['id']] = $this->resolveProfile((int) $p['id'], $termId, $yearId ?: null);
        }

        // ---- Assemble per learner ----
        $items = [];
        foreach ($learners as $l) {
            $sid = (int) $l['student_id'];
            $areaRows = [];
            foreach ($areas as $a) {
                $aid = (int) $a['learning_area_id'];
                $examList = [];
                $pcts = [];
                foreach ($periods as $p) {
                    $pid = (int) $p['id'];
                    $key = $pid . ':' . $sid . ':' . $aid;
                    if (!isset($exams[$key])) continue;
                    $marks = null; $max = null;
                    foreach ($exams[$key] as $r) { $marks += (float) $r['marks']; $max += (float) $r['max']; }
                    $pct = $max > 0 ? round($marks * 100.0 / $max, 2) : null;
                    $paperDetail = $papers[$exams[$key][0]['assessment_id']][$sid] ?? [];
                    $fAsOf = null;
                    $fPcts = [];
                    foreach ($formative[$sid][$aid] ?? [] as $f) {
                        if ($f['on'] !== null && $f['on'] >= (string) $p['starts_on']) continue; // formative BEFORE the exam window
                        $fPcts[] = $f['pct'];
                    }
                    $fAsOf = $fPcts ? round(array_sum($fPcts) / count($fPcts), 2) : null;
                    $pp = $periodProfiles[$pid];
                    $fw = ((float) $pp['formative_weight']) / 100.0;
                    $sw = ((float) $pp['summative_weight']) / 100.0;
                    $blendParts = 0.0; $blendDiv = 0.0;
                    if ($fAsOf !== null)  { $blendParts += $fAsOf * $fw;  $blendDiv += $fw; }
                    if ($pct !== null)   { $blendParts += $pct * $sw;    $blendDiv += $sw; }
                    $examList[] = [
                        'exam_period_id' => $pid,
                        'title' => $p['title'],
                        'type_code' => $p['type_code'],
                        'starts_on' => $p['starts_on'],
                        'marks' => $marks !== null ? round($marks, 2) : null,
                        'max' => $max !== null ? round($max, 2) : null,
                        'pct' => $pct,
                        'papers' => $paperDetail,
                        'formative_asof_pct' => $fAsOf,
                        'blend_pct' => $blendDiv > 0 ? round($blendParts / $blendDiv, 2) : null,
                    ];
                    if ($pct !== null) $pcts[] = $pct;
                }
                $fAll = $formative[$sid][$aid] ?? [];
                $formativeAvg = $fAll ? round(array_sum(array_column($fAll, 'pct')) / count($fAll), 2) : null;
                $examsAvg = $pcts ? round(array_sum($pcts) / count($pcts), 2) : null;
                $fw = ((float) $termProfile['formative_weight']) / 100.0;
                $sw = ((float) $termProfile['summative_weight']) / 100.0;
                $tParts = 0.0; $tDiv = 0.0;
                if ($formativeAvg !== null) { $tParts += $formativeAvg * $fw; $tDiv += $fw; }
                if ($examsAvg !== null)     { $tParts += $examsAvg * $sw;     $tDiv += $sw; }
                $termScore = $tDiv > 0 ? round($tParts / $tDiv, 2) : null;
                $band = $termScore !== null ? $this->mapToBand((int) $termProfile['grading_system_id'], $termScore) : null;
                if (!$examList && $formativeAvg === null) continue; // no evidence at all for this area
                $areaRows[] = [
                    'learning_area_id' => $aid,
                    'learning_area_name' => $a['learning_area_name'],
                    'learning_area_code' => $a['learning_area_code'],
                    'exams' => $examList,
                    'exams_average_pct' => $examsAvg,
                    'formative_avg_pct' => $formativeAvg,
                    'formative_count' => count($fAll),
                    'term_score_pct' => $termScore,
                    'band_code' => $band['band_code'] ?? null,
                    'band_name' => $band['band_name'] ?? null,
                    'achievement_level' => $band['achievement_level'] ?? null,
                ];
            }
            if (!$areaRows && $search === '' && !$studentId) {
                // Keep learners only when they carry evidence (unless explicitly searched).
                $hasAny = isset($exams) || isset($formative);
                $studentHasEvidence = false;
                foreach ($periods as $p) foreach ($areas as $a) {
                    if (isset($exams[((int) $p['id']) . ':' . $sid . ':' . ((int) $a['learning_area_id'])])) { $studentHasEvidence = true; break 2; }
                }
                if (!$studentHasEvidence && empty($formative[$sid])) continue;
            }
            $items[] = [
                'student_id' => $sid,
                'admission_no' => (string) $l['admission_no'],
                'learner_name' => trim(($l['first_name'] ?? '') . ' ' . ($l['middle_name'] ?? '') . ' ' . ($l['last_name'] ?? '')),
                'class_name' => (string) $l['class_name'],
                'stream_name' => (string) $l['stream_name'],
                'areas' => $areaRows,
            ];
        }

        return [
            'ok' => true,
            'exam_periods' => array_map(function ($p) {
                return ['id' => (int) $p['id'], 'title' => $p['title'], 'type_code' => $p['type_code'], 'type_name' => $p['type_name'], 'starts_on' => $p['starts_on'], 'ends_on' => $p['ends_on']];
            }, $periods),
            'term_profile' => [
                'formative_weight' => (float) $termProfile['formative_weight'],
                'summative_weight' => (float) $termProfile['summative_weight'],
                'grading_system_code' => $termProfile['grading_system_code'],
                'resolved_scope' => $termProfile['resolved_scope'],
            ],
            'items' => $items,
        ];
    }
}
