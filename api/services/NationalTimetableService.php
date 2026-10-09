<?php

declare(strict_types=1);

namespace App\API\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Persists reviewed national assessment timetables and generates internal
 * exam sittings from them.
 *
 * Division of labour (blueprint): the Python platform performs the bounded,
 * review-only document extraction (python_platform/app/exam_documents.py).
 * This service — the deterministic PHP edge — receives ONLY the rows the staff
 * member has just reviewed together with the column mapping they confirmed,
 * validates every row, saves the authoritative paper schedule, and can mirror
 * it into the internal sitting/register model. Nothing here calls a provider.
 */
final class NationalTimetableService
{
    private const MAX_ROWS = 1000;
    private const MAX_CELL = 1000;

    public function __construct(private PDO $db, private int $userId)
    {
    }

    // ── Normalisation helpers (KNEC document formats, deterministic) ───────

    /** "26.10.2026" or "2026-10-26" -> "2026-10-26"; null when unparseable. */
    public static function normaliseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m) === 1) {
            return self::validDate((int) $m[1], (int) $m[2], (int) $m[3]) ? sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]) : null;
        }
        if (preg_match('/(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})/', $raw, $m) === 1) {
            return self::validDate((int) $m[3], (int) $m[2], (int) $m[1]) ? sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]) : null;
        }
        return null;
    }

    private static function validDate(int $y, int $m, int $d): bool
    {
        return $y >= 2000 && $y <= 2200 && $m >= 1 && $m <= 12 && $d >= 1 && $d <= 31;
    }

    /** "8.30 am" / "10.40 a.m." / "1.10 pm" -> "08:30:00" / "10:40:00" / "13:10:00". */
    public static function normaliseTime(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?/', $raw, $m) === 1) {
            return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
        }
        if (preg_match('/(\d{1,2})[.](\d{2})\s*(a\.?m\.?|p\.?m\.?)/i', $raw, $m) === 1) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
            $meridiem = strtolower(str_replace('.', '', $m[3]));
            if ($meridiem === 'pm' && $hour < 12) $hour += 12;
            if ($meridiem === 'am' && $hour === 12) $hour = 0;
            if ($hour > 23 || $minute > 59) return null;
            return sprintf('%02d:%02d:00', $hour, $minute);
        }
        return null;
    }

    /**
     * "8.30 am - 10.10 am" (one cell) -> ["08:30:00","10:10:00"]. Used when
     * the start and end times share a single KNEC session column.
     *
     * @return array{start:?string,end:?string}
     */
    public static function normaliseTimeRange(string $raw): array
    {
        $raw = str_replace(['–', '—'], '-', $raw);
        if (preg_match('/(\d{1,2}\.\d{2}\s*(?:a\.?m\.?|p\.?m\.?)|(?:\d{2}:\d{2}))\s*-\s*(\d{1,2}\.\d{2}\s*(?:a\.?m\.?|p\.?m\.?)|(?:\d{2}:\d{2}))/i', $raw, $m) === 1) {
            return ['start' => self::normaliseTime($m[1]), 'end' => self::normaliseTime($m[2])];
        }
        return ['start' => null, 'end' => null];
    }

    /**
     * "2 hours 10 minutes" -> 130, "1 hour" -> 60, "45 minutes" -> 45,
     * "3 months" -> null months carried in duration_label.
     *
     * @return array{minutes:?int,label:string}
     */
    public static function normaliseDuration(string $raw): array
    {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($raw === '') {
            return ['minutes' => null, 'label' => ''];
        }
        $minutes = 0;
        $matched = false;
        if (preg_match('/(\d+)\s*hours?\s*(?:and\s*)?(\d+)\s*min/i', $raw, $m) === 1) {
            $minutes = ((int) $m[1]) * 60 + (int) $m[2];
            $matched = true;
        } elseif (preg_match('/(\d+)\s*hours?/i', $raw, $m) === 1) {
            $minutes = ((int) $m[1]) * 60;
            $matched = true;
        } elseif (preg_match('/(\d+)\s*min/i', $raw, $m) === 1) {
            $minutes = (int) $m[1];
            $matched = true;
        }
        if (preg_match('/(\d+)\s*months?/i', $raw, $m) === 1) {
            return ['minutes' => null, 'label' => trim($raw)];
        }
        return ['minutes' => $matched ? $minutes : null, 'label' => $raw];
    }

    /** "(Braille)" / "(Hearing Impaired)" -> variant, stripped from the name. */
    public static function extractVariant(string $paperName): array
    {
        if (preg_match('/\((Large print|Braille|Hearing [Ii]mpaired)\)/', $paperName, $m) === 1) {
            return [
                'variant' => ucfirst(strtolower($m[1])),
                'name' => trim(preg_replace('/\s*\(' . preg_quote($m[1], '/') . '\)\s*/', ' ', $paperName) ?? ''),
            ];
        }
        return ['variant' => null, 'name' => $paperName];
    }

    // ── Save a reviewed grid as the period's national timetable ────────────

    /**
     * Validate and persist the staff-reviewed rows as the authoritative
     * national paper schedule for an exam period.
     *
     * @param array<int,array<int,string>> $rows the exact reviewed rows
     * @param array{item_no:?int,paper_code:?int,paper_name:?int,date:?int,start_time:?int,end_time:?int,duration:?int,session:?int} $mapping
     * @return array{timetable_id:int,papers:int,warnings:array<int,string>}
     */
    public function saveReviewedTimetable(int $examPeriodId, array $rows, array $mapping, string $filename, string $sourceFormat): array
    {
        $period = $this->period($examPeriodId);
        if (($period['assessment_kind'] ?? 'school_based') !== 'national') {
            throw new RuntimeException('National timetables are saved on national (KNEC) exam periods only', 422);
        }
        if (!$rows) {
            throw new RuntimeException('No rows to save', 422);
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Timetables are limited to ' . self::MAX_ROWS . ' rows', 422);
        }

        $warnings = [];
        $papers = [];
        foreach ($rows as $index => $row) {
            $get = function (string $key) use ($mapping, $row): string {
                $column = $mapping[$key] ?? null;
                if ($column === null || !is_int($column) || $column < 0 || $column >= count($row)) {
                    return '';
                }
                $value = trim((string) ($row[$column] ?? ''));
                return mb_substr($value, 0, self::MAX_CELL);
            };

            $paperName = $get('paper_name');
            $break = preg_match('/\bBREAK\b/i', $paperName) === 1;
            $rehearsal = preg_match('/\bREHEARSAL\b/i', $get('session') . ' ' . $paperName) === 1;
            $code = $get('paper_code');
            if (!$break && !$rehearsal && $paperName === '' && $code === '') {
                $warnings[] = 'Row ' . ($index + 1) . ' skipped: no paper name or code.';
                continue;
            }

            $sharedTimeColumn = null;
            $startColumn = $mapping['start_time'] ?? null;
            $endColumn = $mapping['end_time'] ?? null;
            if ($startColumn !== null && $startColumn === $endColumn) {
                $sharedTimeColumn = self::normaliseTimeRange($get('start_time'));
            }

            $variant = null;
            if (!$break && !$rehearsal) {
                $extracted = self::extractVariant($paperName);
                $variant = $extracted['variant'];
                $paperName = $extracted['name'];
            }

            $papers[] = [
                'item_no' => $get('item_no') !== '' ? mb_substr($get('item_no'), 0, 10) : null,
                'paper_code' => $code !== '' ? mb_substr($code, 0, 20) : null,
                'paper_name' => mb_substr($paperName ?: ($break ? 'BREAK' : ($rehearsal ? 'REHEARSAL' : '')), 0, 255),
                'paper_date' => self::normaliseDate($get('date')),
                'start_time' => $sharedTimeColumn !== null ? $sharedTimeColumn['start'] : self::normaliseTime($get('start_time')),
                'end_time' => $sharedTimeColumn !== null ? $sharedTimeColumn['end'] : self::normaliseTime($get('end_time')),
                'duration' => self::normaliseDuration($get('duration')),
                'session_label' => $get('session') !== '' ? mb_substr($get('session'), 0, 120) : null,
                'variant' => $variant,
                'is_break' => $break ? 1 : 0,
                'sort_order' => $index,
            ];
        }
        if (!$papers) {
            throw new RuntimeException('None of the reviewed rows contained a savable paper', 422);
        }

        $this->db->beginTransaction();
        try {
            $documentId = $this->nationalDocumentId($period);
            $insertTimetable = $this->db->prepare(
                'INSERT INTO national_assessment_timetables
                    (exam_period_id, policy_document_id, original_filename, source_format, extraction_method, extraction_status, notice, papers_count, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insertTimetable->execute([
                $examPeriodId,
                $documentId,
                mb_substr($filename, 0, 255),
                mb_substr($sourceFormat, 0, 20),
                'python_reviewed_grid',
                $warnings ? 'parsed_with_warnings' : 'parsed',
                $warnings ? implode(' ', array_slice($warnings, 0, 20)) : null,
                count($papers),
                $this->userId,
            ]);
            $timetableId = (int) $this->db->lastInsertId();

            $insertPaper = $this->db->prepare(
                'INSERT INTO national_assessment_papers
                    (timetable_id, item_no, paper_code, paper_name, paper_date, start_time, end_time, duration_minutes, duration_label, session_label, variant, is_break, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($papers as $paper) {
                $insertPaper->execute([
                    $timetableId,
                    $paper['item_no'],
                    $paper['paper_code'],
                    $paper['paper_name'],
                    $paper['paper_date'],
                    $paper['start_time'],
                    $paper['end_time'],
                    $paper['duration']['minutes'],
                    $paper['duration']['label'] !== '' ? mb_substr($paper['duration']['label'], 0, 60) : null,
                    $paper['session_label'],
                    $paper['variant'],
                    $paper['is_break'],
                    $paper['sort_order'],
                ]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return ['timetable_id' => $timetableId, 'papers' => count($papers), 'warnings' => $warnings];
    }

    private function period(int $examPeriodId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM exam_periods WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$examPeriodId]);
        $period = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            throw new RuntimeException('Exam period not found', 404);
        }
        return $period;
    }

    /** The policy document matching the period's national code, when seeded. */
    private function nationalDocumentId(array $period): ?int
    {
        $code = strtoupper(trim((string) ($period['national_assessment_code'] ?? '')));
        if ($code === '' || $code === 'OTHER') {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id FROM assessment_policy_documents WHERE doc_type = \'timetable\' AND status = \'active\' AND title LIKE ? LIMIT 1');
        $stmt->execute(['%' . $code . '%']);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    // ── Reads ──────────────────────────────────────────────────────────────

    /** Latest saved timetable + its papers for a period (UI + CSV + print). */
    public function papersForPeriod(int $examPeriodId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, original_filename, source_format, extraction_method, extraction_status, papers_count, created_at
             FROM national_assessment_timetables WHERE exam_period_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$examPeriodId]);
        $timetable = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$timetable) {
            return ['timetable' => null, 'papers' => []];
        }
        $papersStmt = $this->db->prepare(
            'SELECT id, item_no, paper_code, paper_name, paper_date, start_time, end_time, duration_minutes, duration_label, session_label, variant, is_break
             FROM national_assessment_papers WHERE timetable_id = ? ORDER BY sort_order, id'
        );
        $papersStmt->execute([(int) $timetable['id']]);
        return ['timetable' => $timetable, 'papers' => $papersStmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
    }

    /**
     * Generate the internal sitting + register model from the saved national
     * papers. Mirrors the school timetable model: one exam_schedule per
     * class learning area, dated from the earliest matching paper, with every
     * KNEC paper recorded in the notes. Idempotent: class learning areas that
     * already have a sitting are skipped and reported.
     */
    public function generateSittings(int $examPeriodId): array
    {
        $period = $this->period($examPeriodId);
        $saved = $this->papersForPeriod($examPeriodId);
        $papers = $saved['papers'];
        if (!$papers) {
            throw new RuntimeException('Save a national timetable first', 409);
        }

        $areas = $this->periodAreas($examPeriodId);
        if (!$areas) {
            throw new RuntimeException('This period has no class learning areas', 409);
        }

        $typeId = (int) ($this->db->query("SELECT id FROM assessment_type_classifications WHERE is_summative=1 AND status='active' ORDER BY (LOWER(name)='end of term exam') DESC,id LIMIT 1")->fetchColumn() ?: 0);
        if (!$typeId) {
            throw new RuntimeException('No active summative assessment type is configured', 409);
        }

        $existing = $this->db->prepare(
            'SELECT COUNT(*) FROM exam_period_timetable_entries epte
             JOIN exam_period_class_learning_areas epcla ON epcla.id = epte.exam_period_class_learning_area_id
             JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
             WHERE epc.exam_period_id = ?'
        );
        $existing->execute([$examPeriodId]);
        if ((int) $existing->fetchColumn() > 0) {
            throw new RuntimeException('This period already has sittings; delete them before generating from the KNEC timetable', 409);
        }

        $insertSchedule = $this->db->prepare(
            "INSERT INTO exam_schedules (academic_year_class_id,academic_year_term_id,learning_area_id,max_marks,exam_name,exam_type,exam_date,start_time,end_time,duration_minutes,notes,created_by,status,source)
             VALUES (?,?,?,?,?,'summative',?,?,?,?,?,?,'scheduled','national')"
        );
        $linkTimetable = $this->db->prepare('INSERT INTO exam_period_timetable_entries (exam_period_class_learning_area_id, exam_schedule_id) VALUES (?,?)');
        $insertAssessment = $this->db->prepare(
            "INSERT INTO assessments (academic_year_class_stream_id,academic_year_term_id,learning_area_id,assessment_type_classification_id,title,max_marks,assessment_date,assigned_by,status)
             VALUES (?,?,?,?,?,?,?,?,'pending_submission')"
        );
        $insertAssessmentLink = $this->db->prepare('INSERT INTO exam_schedule_assessments (exam_schedule_id,academic_year_class_stream_id,assessment_id) VALUES (?,?,?)');
        $streamCache = [];

        $created = 0;
        $unmatched = [];
        $this->db->beginTransaction();
        try {
            foreach ($areas as $area) {
                $matching = array_values(array_filter($papers, fn (array $paper): bool => !$paper['is_break']
                    && $paper['paper_date'] !== null
                    && $paper['start_time'] !== null
                    && $this->subjectMatchesLearningArea((string) $paper['paper_name'], (int) $area['learning_area_id'])));
                if (!$matching) {
                    $unmatched[] = ($area['class_name'] ?? 'Class') . ' - ' . ($area['learning_area_name'] ?? 'learning area');
                    continue;
                }
                usort($matching, fn (array $a, array $b): int => [$a['paper_date'], $a['start_time']] <=> [$b['paper_date'], $b['start_time']]);
                $primary = $matching[0];
                $codes = implode(', ', array_filter(array_map(fn (array $p): string => (string) $p['paper_code'], $matching)));
                $label = ($period['title'] ?? 'National assessment') . ' - ' . ($area['class_name'] ?? '') . ' - ' . ($area['learning_area_name'] ?? '');
                $insertSchedule->execute([
                    (int) $area['academic_year_class_id'],
                    (int) $period['academic_year_term_id'],
                    (int) $area['learning_area_id'],
                    100,
                    $label,
                    (string) $primary['paper_date'],
                    (string) $primary['start_time'],
                    (string) ($primary['end_time'] ?? $primary['start_time']),
                    $primary['duration_minutes'] !== null ? (int) $primary['duration_minutes'] : null,
                    $codes !== '' ? 'KNEC papers: ' . $codes : null,
                    $this->userId,
                ]);
                $scheduleId = (int) $this->db->lastInsertId();
                $linkTimetable->execute([(int) $area['exam_period_class_learning_area_id'], $scheduleId]);

                $classId = (int) $area['academic_year_class_id'];
                if (!isset($streamCache[$classId])) {
                    $streamCache[$classId] = $this->assessableStreams($classId);
                }
                foreach ($streamCache[$classId] as $streamId) {
                    $insertAssessment->execute([$streamId, (int) $period['academic_year_term_id'], (int) $area['learning_area_id'], $typeId, $label, 100, (string) $primary['paper_date'], $this->userId]);
                    $insertAssessmentLink->execute([$scheduleId, $streamId, (int) $this->db->lastInsertId()]);
                }
                $created++;
            }
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return ['sittings_created' => $created, 'unmatched_learning_areas' => array_values(array_unique($unmatched))];
    }

    /**
     * Deterministic KNEC subject -> learning-area match: exact normalised
     * containment either way (KNEC names are the superset, e.g. "English
     * Language" contains "English").
     */
    private function subjectMatchesLearningArea(string $paperName, int $learningAreaId): bool
    {
        static $areas = null;
        if ($areas === null) {
            $areas = [];
            foreach ($this->db->query('SELECT id, name FROM learning_areas')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $areas[(int) $row['id']] = $this->normaliseSubject((string) $row['name']);
            }
        }
        $paper = $this->normaliseSubject($paperName);
        $area = $areas[$learningAreaId] ?? '';
        if ($paper === '' || $area === '') {
            return false;
        }
        return $paper === $area
            || str_contains($paper, $area)
            || str_contains($area, $paper);
    }

    private function normaliseSubject(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\(.*?\)/', '', $name) ?? $name;
        $name = str_replace(['&', '-', '_'], ' ', $name);
        $name = preg_replace('/\b(language|lugha|activities|studies|and|the)\b/', '', $name) ?? $name;
        return trim(preg_replace('/\s+/', ' ', $name) ?? '');
    }

    /** Class learning areas of the period, shaped like writeSittings areas. */
    private function periodAreas(int $examPeriodId): array
    {
        $stmt = $this->db->prepare(
            'SELECT epcla.id AS exam_period_class_learning_area_id, acd.id AS academic_year_class_id,
                    aycla.learning_area_id, acd.class_name AS class_name, la.name AS learning_area_name
             FROM exam_period_classes epc
             JOIN exam_period_class_learning_areas epcla ON epcla.exam_period_class_id = epc.id
             JOIN ' . ReadReplicaService::qualifiedRef('academic_year_class_learning_areas') . ' aycla ON aycla.id = epcla.academic_year_class_learning_area_id
             JOIN ' . ReadReplicaService::qualifiedRef('academic_class_directory') . ' acd ON acd.id = aycla.academic_year_class_id
             JOIN learning_areas la ON la.id = aycla.learning_area_id
             WHERE epc.exam_period_id = ?'
        );
        $stmt->execute([$examPeriodId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Streams of a class with active/completed enrollments (register owners). */
    private function assessableStreams(int $academicYearClassId): array
    {
        $stmt = $this->db->prepare(
            "SELECT aycs.id FROM " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs
             WHERE aycs.academic_year_class_id = ? AND aycs.status = 'active'
               AND EXISTS (SELECT 1 FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
                           WHERE sae.academic_year_class_stream_id = aycs.id
                             AND sae.enrollment_status IN ('active','completed'))"
        );
        $stmt->execute([$academicYearClassId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
