<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Exam paper bank, attached to exam periods.
 *
 * For every exam period, class and learning area the school records its
 * papers: set by a teacher or bought from a supplier (with cost, date and
 * receipt), the files (question paper, marking scheme), its progress
 * (draft -> submitted -> approved -> printed -> issued -> archived) and the
 * copies issued to each stream. Papers can be prepared well before the exam;
 * nothing here depends on results being open.
 *
 * Files live in the private exam_paper upload category; their paths are
 * stored relative to the uploads root. The "folder" of a paper is its place
 * in Year / Term / Exam / Grade / Learning area.
 */
final class ExamPaperBankService
{
    public const STATUSES = ['draft', 'submitted', 'approved', 'printed', 'issued', 'archived'];
    public const FILE_KINDS = ['question_paper', 'marking_scheme', 'answer_sheet', 'other'];

    public function __construct(private PDO $db, private int $userId = 0)
    {
    }

    /** Papers of an exam period with files, issues and folder labels. */
    public function list(int $examPeriodId): array
    {
        $period = $this->period($examPeriodId);
        $stmt = $this->db->prepare(
            'SELECT * FROM exam_papers WHERE exam_period_id = ? AND deleted_at IS NULL ORDER BY academic_year_class_id, learning_area_id, paper_number'
        );
        $stmt->execute([$examPeriodId]);
        $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_map(static fn ($p) => (int) $p['id'], $papers);
        $files = $this->byPaper('SELECT id, exam_paper_id, kind, original_name, mime_type, file_size_bytes, created_at FROM exam_paper_files WHERE exam_paper_id IN (%s) ORDER BY id', $ids);
        $issues = $this->byPaper('SELECT id, exam_paper_id, academic_year_class_stream_id, copies_issued, candidates_present, issued_at, notes FROM exam_paper_issues WHERE exam_paper_id IN (%s) ORDER BY issued_at', $ids);
        $classNames = $this->classNames(array_unique(array_map(static fn ($p) => (int) $p['academic_year_class_id'], $papers)));
        $areaNames = $this->names('learning_areas', array_unique(array_map(static fn ($p) => (int) $p['learning_area_id'], $papers)));
        $streamNames = $this->streamNames(array_unique(array_merge(...array_map(static fn ($list) => array_map(static fn ($i) => (int) $i['academic_year_class_stream_id'], $list), array_values($issues) ?: [[]]))));

        foreach ($papers as &$p) {
            $class = $classNames[(int) $p['academic_year_class_id']] ?? 'Class';
            $area = $areaNames[(int) $p['learning_area_id']] ?? 'Learning area';
            $p['class_name'] = $class;
            $p['learning_area_name'] = $area;
            $p['folder'] = implode(' / ', array_filter([$period['year_code'] ?? '', $period['term_name'] ?? '', $period['title'] ?? '', $class, $area]));
            $p['files'] = $files[(int) $p['id']] ?? [];
            $p['issues'] = array_map(static function ($i) use ($streamNames) {
                $i['stream_name'] = $streamNames[(int) $i['academic_year_class_stream_id']] ?? '';
                return $i;
            }, $issues[(int) $p['id']] ?? []);
            $p['copies_issued_total'] = array_sum(array_map(static fn ($i) => (int) $i['copies_issued'], $p['issues']));
        }
        unset($p);
        return ['period' => $period, 'papers' => $papers, 'options' => $this->options($examPeriodId)];
    }

    /** Class / learning-area pairs sitting the exam, and each class's streams. */
    private function options(int $examPeriodId): array
    {
        // Keyed reads only: period classes -> their learning-area rows -> areas.
        $stmt = $this->db->prepare('SELECT id, academic_year_class_id FROM exam_period_classes WHERE exam_period_id = ?');
        $stmt->execute([$examPeriodId]);
        $periodClasses = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'academic_year_class_id', 'id');
        $pairs = [];
        if ($periodClasses) {
            $ids = array_keys($periodClasses);
            $st = $this->db->prepare('SELECT exam_period_class_id, academic_year_class_learning_area_id FROM exam_period_class_learning_areas WHERE exam_period_class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $st->execute($ids);
            $links = $st->fetchAll(PDO::FETCH_ASSOC);
            $claIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['academic_year_class_learning_area_id'], $links)));
            $areaOf = [];
            if ($claIds) {
                $st = $this->db->prepare('SELECT id, learning_area_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_class_learning_areas') . ' WHERE id IN (' . implode(',', array_fill(0, count($claIds), '?')) . ')');
                $st->execute($claIds);
                $areaOf = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'learning_area_id', 'id');
            }
            $seen = [];
            foreach ($links as $l) {
                $cid = (int) ($periodClasses[$l['exam_period_class_id']] ?? 0);
                $aid = (int) ($areaOf[$l['academic_year_class_learning_area_id']] ?? 0);
                if (!$cid || !$aid || isset($seen["$cid:$aid"])) continue;
                $seen["$cid:$aid"] = true;
                $pairs[] = ['academic_year_class_id' => $cid, 'learning_area_id' => $aid];
            }
        }
        $classIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['academic_year_class_id'], $pairs)));
        $classNames = $this->classNames($classIds);
        $areaNames = $this->names('learning_areas', array_unique(array_map(static fn ($r) => (int) $r['learning_area_id'], $pairs)));
        $streams = [];
        if ($classIds) {
            $in = implode(',', array_fill(0, count($classIds), '?'));
            $st = $this->db->prepare('SELECT DISTINCT class_stream_id, academic_year_class_id, stream_name FROM ' . ReadReplicaService::qualifiedRef('academic_calendar') . " WHERE academic_year_class_id IN ({$in}) ORDER BY stream_name");
            $st->execute($classIds);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $streams[(int) $r['academic_year_class_id']][] = ['id' => (int) $r['class_stream_id'], 'name' => trim(($classNames[(int) $r['academic_year_class_id']] ?? '') . ' ' . ($r['stream_name'] ?? ''))];
            }
        }
        $classes = [];
        foreach ($pairs as $r) {
            $cid = (int) $r['academic_year_class_id'];
            $classes[$cid] ??= ['id' => $cid, 'name' => $classNames[$cid] ?? 'Class', 'learning_areas' => [], 'streams' => $streams[$cid] ?? []];
            $classes[$cid]['learning_areas'][] = ['id' => (int) $r['learning_area_id'], 'name' => $areaNames[(int) $r['learning_area_id']] ?? 'Learning area'];
        }
        foreach ($classes as &$c) usort($c['learning_areas'], static fn ($a, $b) => strcmp($a['name'], $b['name']));
        unset($c);
        usort($classes, static fn ($a, $b) => strnatcmp($a['name'], $b['name']));
        return ['classes' => array_values($classes)];
    }

    public function save(array $data, ?int $id = null): array
    {
        $fields = [
            'exam_period_id' => (int) ($data['exam_period_id'] ?? 0),
            'academic_year_class_id' => (int) ($data['academic_year_class_id'] ?? 0),
            'learning_area_id' => (int) ($data['learning_area_id'] ?? 0),
            'paper_number' => max(1, min(9, (int) ($data['paper_number'] ?? 1))),
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 200),
            'source' => ($data['source'] ?? '') === 'purchased' ? 'purchased' : 'set_internally',
            'setter_staff_id' => ($data['setter_staff_id'] ?? '') !== '' ? (int) $data['setter_staff_id'] : null,
            'supplier_name' => $this->nullable($data['supplier_name'] ?? null, 150),
            'purchase_cost' => ($data['purchase_cost'] ?? '') !== '' ? round((float) $data['purchase_cost'], 2) : null,
            'purchase_date' => $this->date($data['purchase_date'] ?? null),
            'purchase_reference' => $this->nullable($data['purchase_reference'] ?? null, 80),
            'copies_required' => ($data['copies_required'] ?? '') !== '' ? max(0, (int) $data['copies_required']) : null,
            'duration_minutes' => ($data['duration_minutes'] ?? '') !== '' ? max(0, (int) $data['duration_minutes']) : null,
            'max_marks' => ($data['max_marks'] ?? '') !== '' ? round((float) $data['max_marks'], 2) : null,
            'notes' => $this->nullable($data['notes'] ?? null, 5000),
        ];
        if ($fields['source'] === 'set_internally') {
            $fields['supplier_name'] = $fields['purchase_cost'] = $fields['purchase_date'] = $fields['purchase_reference'] = null;
        }
        if (!$fields['exam_period_id'] || !$fields['academic_year_class_id'] || !$fields['learning_area_id']) {
            throw new RuntimeException('Choose the exam, class and learning area for this paper.', 422);
        }
        if ($fields['title'] === '') {
            throw new RuntimeException('Give the paper a title, for example "Mathematics Paper 1".', 422);
        }
        $this->period($fields['exam_period_id']);
        $this->assertClassInPeriod($fields['exam_period_id'], $fields['academic_year_class_id']);

        if ($id) {
            $current = $this->paper($id);
            $sets = implode(', ', array_map(static fn ($k) => "$k = ?", array_keys($fields)));
            $this->db->prepare("UPDATE exam_papers SET $sets WHERE id = ?")->execute([...array_values($fields), $id]);
            return $this->paper((int) $current['id']);
        }
        $fields['created_by'] = $this->userId ?: null;
        $cols = implode(', ', array_keys($fields));
        $marks = implode(', ', array_fill(0, count($fields), '?'));
        $this->db->prepare("INSERT INTO exam_papers ($cols) VALUES ($marks)")->execute(array_values($fields));
        return $this->paper((int) $this->db->lastInsertId());
    }

    public function setStatus(int $id, string $status): array
    {
        if (!in_array($status, self::STATUSES, true)) throw new RuntimeException('Choose a valid paper status.', 422);
        $paper = $this->paper($id);
        if (in_array($status, ['approved', 'printed', 'issued'], true)) {
            $hasPaper = $this->value("SELECT COUNT(*) FROM exam_paper_files WHERE exam_paper_id = ? AND kind = 'question_paper'", [$id]);
            if (!(int) $hasPaper) throw new RuntimeException('Upload the question paper before marking it ' . $status . '.', 409);
        }
        $approved = $status === 'approved' ? ', approved_by = ?, approved_at = NOW()' : '';
        $params = $status === 'approved' ? [$status, $this->userId ?: null, $id] : [$status, $id];
        $this->db->prepare("UPDATE exam_papers SET status = ?{$approved} WHERE id = ?")->execute($params);
        return $this->paper((int) $paper['id']);
    }

    public function delete(int $id): void
    {
        $this->paper($id);
        $this->db->prepare('UPDATE exam_papers SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
    }

    /** Store an uploaded file against a paper. */
    public function addFile(int $paperId, string $kind, array $file): array
    {
        $paper = $this->paper($paperId);
        if (!in_array($kind, self::FILE_KINDS, true)) $kind = 'question_paper';
        $stored = (new UploadService())->store($file, 'exam_paper', [
            'owner_id' => (string) $paper['exam_period_id'],
            'prefix' => $kind,
            'preferred_name' => pathinfo((string) ($file['name'] ?? 'paper'), PATHINFO_FILENAME),
        ]);
        $this->db->prepare(
            'INSERT INTO exam_paper_files (exam_paper_id, kind, file_path, original_name, mime_type, file_size_bytes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $paperId, $kind, $stored['relative_path'], $stored['original_filename'], $stored['mime_type'],
            $stored['file_size_bytes'], $this->userId ?: null,
        ]);
        if ($kind === 'question_paper' && $paper['status'] === 'draft') {
            $this->db->prepare("UPDATE exam_papers SET status = 'submitted' WHERE id = ? AND status = 'draft'")->execute([$paperId]);
        }
        return $this->paper($paperId);
    }

    /** @return array{absolute_path:string,name:string,mime:string} */
    public function fileForDownload(int $fileId): array
    {
        $stmt = $this->db->prepare('SELECT f.file_path, f.original_name, f.mime_type FROM exam_paper_files f WHERE f.id = ? LIMIT 1');
        $stmt->execute([$fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('File not found', 404);
        $absolute = rtrim((string) UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim((string) $row['file_path'], '/'));
        $real = realpath($absolute);
        $root = realpath((string) UPLOAD_PATH . '/academic/exam_papers');
        if ($real === false || $root === false || !str_starts_with($real, $root)) {
            throw new RuntimeException('The file is missing from storage.', 404);
        }
        return ['absolute_path' => $real, 'name' => (string) $row['original_name'], 'mime' => (string) ($row['mime_type'] ?: 'application/octet-stream')];
    }

    public function deleteFile(int $fileId): void
    {
        $file = $this->fileForDownload($fileId);
        $this->db->prepare('DELETE FROM exam_paper_files WHERE id = ?')->execute([$fileId]);
        @unlink($file['absolute_path']);
    }

    /** Record copies issued to a stream. */
    public function issue(int $paperId, array $data): array
    {
        $paper = $this->paper($paperId);
        $streamId = (int) ($data['academic_year_class_stream_id'] ?? 0);
        $copies = max(0, (int) ($data['copies_issued'] ?? 0));
        if (!$streamId || $copies < 1) throw new RuntimeException('Choose the stream and the number of copies issued.', 422);
        $streamClass = (int) $this->value('SELECT academic_year_class_id FROM academic_year_class_streams WHERE id = ?', [$streamId]);
        if ($streamClass !== (int) $paper['academic_year_class_id']) {
            throw new RuntimeException('That stream is not part of the class this paper is for.', 422);
        }
        $issuedAt = $this->date($data['issued_at'] ?? null);
        $this->db->prepare(
            'INSERT INTO exam_paper_issues (exam_paper_id, academic_year_class_stream_id, copies_issued, candidates_present, issued_at, issued_by, received_by_staff_id, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $paperId, $streamId, $copies,
            ($data['candidates_present'] ?? '') !== '' ? max(0, (int) $data['candidates_present']) : null,
            $issuedAt ? $issuedAt . ' ' . date('H:i:s') : date('Y-m-d H:i:s'),
            $this->userId ?: null,
            ($data['received_by_staff_id'] ?? '') !== '' ? (int) $data['received_by_staff_id'] : null,
            $this->nullable($data['notes'] ?? null, 500),
        ]);
        if (in_array($paper['status'], ['approved', 'printed', 'submitted', 'draft'], true)) {
            $this->db->prepare("UPDATE exam_papers SET status = 'issued' WHERE id = ?")->execute([$paperId]);
        }
        return $this->paper($paperId);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function paper(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM exam_papers WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Exam paper not found', 404);
        return $row;
    }

    private function period(int $id): array
    {
        $stmt = $this->db->prepare('SELECT id, title, academic_year_term_id, starts_on, ends_on, status FROM exam_periods WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Exam period not found', 404);
        $term = $this->db->prepare('SELECT term_name, year_code FROM ' . ReadReplicaService::qualifiedRef('academic_term') . ' WHERE academic_year_term_id = ? LIMIT 1');
        $term->execute([(int) $row['academic_year_term_id']]);
        return $row + ($term->fetch(PDO::FETCH_ASSOC) ?: []);
    }

    private function assertClassInPeriod(int $periodId, int $classId): void
    {
        $found = $this->value('SELECT COUNT(*) FROM exam_period_classes WHERE exam_period_id = ? AND academic_year_class_id = ?', [$periodId, $classId]);
        if (!(int) $found) throw new RuntimeException('That class is not sitting this exam.', 422);
    }

    private function byPaper(string $sqlTemplate, array $ids): array
    {
        if (!$ids) return [];
        $stmt = $this->db->prepare(sprintf($sqlTemplate, implode(',', array_fill(0, count($ids), '?'))));
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $out[(int) $row['exam_paper_id']][] = $row;
        return $out;
    }

    private function classNames(array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if (!$ids) return [];
        $stmt = $this->db->prepare('SELECT id, class_name FROM ' . ReadReplicaService::qualifiedRef('academic_class_directory') . ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'class_name', 'id');
    }

    private function streamNames(array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if (!$ids) return [];
        $stmt = $this->db->prepare('SELECT class_stream_id AS id, CONCAT(class_name, " ", COALESCE(stream_name, "")) AS name FROM ' . ReadReplicaService::qualifiedRef('academic_calendar') . ' WHERE class_stream_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY class_stream_id, class_name, stream_name');
        $stmt->execute($ids);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
    }

    private function names(string $table, array $ids): array
    {
        $ids = array_values(array_filter($ids));
        if (!$ids) return [];
        $stmt = $this->db->prepare("SELECT id, name FROM {$table} WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')');
        $stmt->execute($ids);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');
    }

    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function nullable($value, int $max): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private function date($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
