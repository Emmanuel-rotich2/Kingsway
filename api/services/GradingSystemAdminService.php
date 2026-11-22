<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Creating and versioning grading systems (School Administrator).
 *
 * When the Ministry introduces a new scale the admin uploads its table (CSV
 * or Excel) or copies an existing system, reviews the preview, and publishes.
 * A new system starts as a draft and can be chosen for terms and exams only
 * once published. A published system that has results graded on it is
 * locked: changes go into a new version (copy), so exams already graded keep
 * the scale they were graded on.
 */
final class GradingSystemAdminService
{
    private const MAX_BANDS = 20;

    public function __construct(private PDO $db, private int $userId = 0)
    {
    }

    // ── Reading ──────────────────────────────────────────────────────────

    /** Systems with bands, lock state and usage, newest first within status. */
    public function list(): array
    {
        $systems = $this->db->query(
            "SELECT id, code, name, levels_count, category, view_of_system_id, supersedes_id, description, authority,
                    status, published_at, created_at
             FROM grading_systems
             ORDER BY FIELD(status, 'active', 'draft', 'superseded', 'inactive'), id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $bands = $this->db->query(
            'SELECT id, grading_system_id, band_code, group_code, band_name, min_percentage, max_percentage, points,
                    achievement_level, performance_level, description, sort_order
             FROM grading_system_bands ORDER BY grading_system_id, sort_order, min_percentage DESC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $bySystem = [];
        foreach ($bands as $b) $bySystem[(int) $b['grading_system_id']][] = $b;
        $names = array_column($systems, 'name', 'id');
        foreach ($systems as &$s) {
            $id = (int) $s['id'];
            $s['bands'] = $bySystem[$id] ?? [];
            $s['usage'] = $this->usage($id);
            $s['locked'] = $this->isLocked($id, $s['status'], $s['usage']);
            $s['view_of_name'] = $s['view_of_system_id'] ? ($names[(int) $s['view_of_system_id']] ?? null) : null;
            $s['supersedes_name'] = $s['supersedes_id'] ? ($names[(int) $s['supersedes_id']] ?? null) : null;
        }
        unset($s);
        return $systems;
    }

    /** How many exams and term settings use a system (directly or as a view). */
    public function usage(int $systemId): array
    {
        $family = $this->family($systemId);
        $in = implode(',', array_fill(0, count($family), '?'));
        $exams = (int) $this->value("SELECT COUNT(*) FROM exam_periods WHERE deleted_at IS NULL AND grading_system_id IN ($in)", $family);
        $examsWithResults = (int) $this->value(
            "SELECT COUNT(*) FROM exam_periods ep WHERE ep.deleted_at IS NULL AND ep.grading_system_id IN ($in)
               AND (ep.status IN ('results_open','moderation','completed') OR ep.results_published_at IS NOT NULL)",
            $family
        );
        $bindings = (int) $this->value("SELECT COUNT(*) FROM academic_year_term_grading_bindings WHERE status = 'active' AND grading_system_id IN ($in)", $family);
        $profiles = (int) $this->value("SELECT COUNT(*) FROM assessment_term_profiles WHERE grading_system_id IN ($in)", $family);
        $results = ($bindings || $profiles) ? (int) $this->value('SELECT COUNT(*) FROM assessment_results WHERE deleted_at IS NULL AND marks_obtained IS NOT NULL', []) : 0;
        return ['exams' => $exams, 'exams_with_results' => $examsWithResults, 'term_settings' => $bindings + $profiles, 'results_graded' => $results];
    }

    public function isLocked(int $systemId, ?string $status = null, ?array $usage = null): bool
    {
        $status ??= (string) $this->value('SELECT status FROM grading_systems WHERE id = ?', [$systemId]);
        if ($status === 'draft') return false;
        $usage ??= $this->usage($systemId);
        return $usage['exams_with_results'] > 0 || $usage['results_graded'] > 0;
    }

    /** Refuse band changes on a locked system. */
    public function assertEditable(int $systemId): void
    {
        if ($this->isLocked($systemId)) {
            throw new RuntimeException('Results have been graded on this grading system, so its bands are locked. Copy it to make a new version, publish the copy, and use it for new exams.', 409);
        }
    }

    // ── Upload preview ───────────────────────────────────────────────────

    /**
     * Read a CSV or Excel grading table. Columns (header names are flexible):
     * code, name, min %, max %, points, group (optional), description (optional).
     * @return array{bands:array,problems:array}
     */
    public function preview(array $file): array
    {
        $path = (string) ($file['tmp_name'] ?? '');
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($path === '' || !is_uploaded_file($path) && !is_file($path)) throw new RuntimeException('Choose the grading table file.', 422);
        if (!in_array($ext, ['csv', 'xlsx', 'xls'], true)) throw new RuntimeException('Upload the grading table as CSV or Excel (.xlsx).', 422);
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) throw new RuntimeException('The grading table file is too large (2 MB at most).', 422);

        $rows = $ext === 'csv' ? $this->readCsv($path) : $this->readSpreadsheet($path);
        if (count($rows) < 2) throw new RuntimeException('The file needs a header row and at least two levels.', 422);
        $header = array_map(fn ($h) => $this->key((string) $h), array_shift($rows));
        $col = static function (array $names) use ($header): ?int {
            foreach ($header as $i => $h) if (in_array($h, $names, true)) return $i;
            return null;
        };
        $map = [
            'band_code' => $col(['code', 'level', 'levelcode', 'grade', 'gradecode', 'bandcode', 'band']),
            'band_name' => $col(['name', 'levelname', 'gradename', 'descriptor', 'bandname', 'performancelevel']),
            'min_percentage' => $col(['min', 'minimum', 'from', 'minpercentage', 'minscore', 'lower', 'low']),
            'max_percentage' => $col(['max', 'maximum', 'to', 'maxpercentage', 'maxscore', 'upper', 'high']),
            'points' => $col(['points', 'point', 'score', 'value']),
            'group_code' => $col(['group', 'groupcode', 'parent', 'parentlevel', 'grouplevel', '4level']),
            'description' => $col(['description', 'remark', 'remarks', 'comment']),
        ];
        $missing = array_keys(array_filter(['band_code' => $map['band_code'], 'min_percentage' => $map['min_percentage'], 'max_percentage' => $map['max_percentage']], static fn ($v) => $v === null));
        if ($missing) {
            throw new RuntimeException('The file needs columns for the level code and the minimum and maximum percentage (missing: ' . implode(', ', $missing) . ').', 422);
        }
        $bands = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row[$map['band_code']] ?? ''));
            if ($code === '' && !array_filter($row, static fn ($v) => trim((string) $v) !== '')) continue;
            $get = static fn (?int $i) => $i === null ? '' : trim((string) ($row[$i] ?? ''));
            $bands[] = [
                'band_code' => $code,
                'band_name' => $get($map['band_name']) ?: $code,
                'min_percentage' => str_replace('%', '', $get($map['min_percentage'])),
                'max_percentage' => str_replace('%', '', $get($map['max_percentage'])),
                'points' => $get($map['points']),
                'group_code' => $get($map['group_code']),
                'description' => $get($map['description']),
            ];
        }
        $clean = $this->normaliseBands($bands);
        return ['bands' => $clean, 'problems' => $this->problems($clean)];
    }

    // ── Writing ──────────────────────────────────────────────────────────

    /** Create a draft system from reviewed bands. */
    public function create(array $data): array
    {
        $meta = $this->meta($data);
        $bands = $this->normaliseBands((array) ($data['bands'] ?? []));
        if ($problems = $this->problems($bands)) throw new RuntimeException(implode(' ', array_slice($problems, 0, 3)), 422);
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO grading_systems (code, name, levels_count, category, view_of_system_id, supersedes_id, description, authority, status, created_by)
                 VALUES (?, ?, ?, 'custom', ?, ?, ?, ?, 'draft', ?)"
            )->execute([$meta['code'], $meta['name'], count($bands), $meta['view_of_system_id'], $meta['supersedes_id'], $meta['description'], $meta['authority'], $this->userId ?: null]);
            $id = (int) $this->db->lastInsertId();
            $this->writeBands($id, $bands);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('Another grading system already uses that code.', 409);
            throw $e;
        }
        GradingScopeService::forgetCache();
        return $this->one($id);
    }

    /** Edit a draft: details and its full band list. */
    public function update(int $id, array $data): array
    {
        $current = $this->one($id);
        if ($current['status'] !== 'draft') throw new RuntimeException('Only a draft can be edited. Copy this system to make a new version.', 409);
        $meta = $this->meta($data + ['code' => $current['code'], 'name' => $current['name']], $id);
        $bands = $this->normaliseBands((array) ($data['bands'] ?? $current['bands']));
        if ($problems = $this->problems($bands)) throw new RuntimeException(implode(' ', array_slice($problems, 0, 3)), 422);
        $this->db->beginTransaction();
        try {
            $this->db->prepare('UPDATE grading_systems SET code = ?, name = ?, levels_count = ?, view_of_system_id = ?, supersedes_id = ?, description = ?, authority = ? WHERE id = ?')
                ->execute([$meta['code'], $meta['name'], count($bands), $meta['view_of_system_id'], $meta['supersedes_id'], $meta['description'], $meta['authority'], $id]);
            $this->db->prepare('DELETE FROM grading_system_bands WHERE grading_system_id = ?')->execute([$id]);
            $this->writeBands($id, $bands);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($e instanceof \PDOException && (int) ($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('Another grading system already uses that code.', 409);
            throw $e;
        }
        GradingScopeService::forgetCache();
        return $this->one($id);
    }

    /** Copy a system into a new draft version that will replace it. */
    public function copy(int $id): array
    {
        $src = $this->one($id);
        $base = preg_replace('/-V\d+$/', '', strtoupper((string) $src['code']));
        $n = 2;
        do { $code = substr($base, 0, 34) . '-V' . $n++; } while ((int) $this->value('SELECT COUNT(*) FROM grading_systems WHERE code = ?', [$code]));
        return $this->create([
            'code' => $code,
            'name' => $src['name'] . ' (new version)',
            'description' => $src['description'],
            'authority' => $src['authority'],
            'view_of_system_id' => $src['view_of_system_id'],
            'supersedes_id' => $id,
            'bands' => $src['bands'],
        ]);
    }

    /** Publish a draft; the version it replaces stops being offered for new use. */
    public function publish(int $id): array
    {
        $s = $this->one($id);
        if ($s['status'] !== 'draft') throw new RuntimeException('This grading system is already published.', 409);
        if ($problems = $this->problems($s['bands'])) throw new RuntimeException('Fix the levels before publishing: ' . implode(' ', array_slice($problems, 0, 3)), 422);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE grading_systems SET status = 'active', published_at = NOW() WHERE id = ?")->execute([$id]);
            if (!empty($s['supersedes_id'])) {
                // Old exams keep grading on the old version; it is just no
                // longer offered for new terms and exams.
                $this->db->prepare("UPDATE grading_systems SET status = 'superseded' WHERE id = ? AND status = 'active'")->execute([(int) $s['supersedes_id']]);
                // A grouped view that pointed at the old version follows the new one.
                $this->db->prepare('UPDATE grading_systems SET view_of_system_id = ? WHERE view_of_system_id = ? AND id <> ?')->execute([$id, (int) $s['supersedes_id'], $id]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        GradingScopeService::forgetCache();
        return $this->one($id);
    }

    public function deleteDraft(int $id): void
    {
        $s = $this->one($id);
        if ($s['status'] !== 'draft') throw new RuntimeException('Only a draft can be deleted. A published system stays so past results keep their meaning.', 409);
        $this->db->prepare('DELETE FROM grading_systems WHERE id = ?')->execute([$id]);
        GradingScopeService::forgetCache();
    }

    public function one(int $id): array
    {
        foreach ($this->list() as $s) if ((int) $s['id'] === $id) return $s;
        throw new RuntimeException('Grading system not found', 404);
    }

    // ── Validation ───────────────────────────────────────────────────────

    /** Plain-language problems with a band list; empty when it is usable. */
    public function problems(array $bands): array
    {
        $problems = [];
        if (count($bands) < 2) $problems[] = 'A grading system needs at least two levels.';
        if (count($bands) > self::MAX_BANDS) $problems[] = 'A grading system can have at most ' . self::MAX_BANDS . ' levels.';
        $codes = [];
        foreach ($bands as $i => $b) {
            $label = $b['band_code'] !== '' ? $b['band_code'] : 'Row ' . ($i + 1);
            if ($b['band_code'] === '') $problems[] = "Row " . ($i + 1) . ' has no level code.';
            elseif (isset($codes[$b['band_code']])) $problems[] = "Level {$b['band_code']} appears twice.";
            $codes[$b['band_code']] = true;
            if ($b['min_percentage'] === null || $b['max_percentage'] === null) { $problems[] = "{$label}: enter both the minimum and maximum percentage."; continue; }
            if ($b['min_percentage'] < 0 || $b['max_percentage'] > 100) $problems[] = "{$label}: percentages must be between 0 and 100.";
            if ($b['min_percentage'] > $b['max_percentage']) $problems[] = "{$label}: the minimum is above the maximum.";
            if ($b['points'] === null) $problems[] = "{$label}: points must be a number.";
        }
        $ranged = array_values(array_filter($bands, static fn ($b) => $b['min_percentage'] !== null && $b['max_percentage'] !== null));
        usort($ranged, static fn ($a, $b) => $a['min_percentage'] <=> $b['min_percentage']);
        if ($ranged) {
            if ($ranged[0]['min_percentage'] > 0) $problems[] = 'No level covers scores from 0%.';
            if (end($ranged)['max_percentage'] < 100) $problems[] = 'No level covers 100%.';
            for ($i = 1, $n = count($ranged); $i < $n; $i++) {
                $prev = $ranged[$i - 1]; $cur = $ranged[$i];
                if ($cur['min_percentage'] <= $prev['max_percentage']) {
                    $problems[] = "{$prev['band_code']} and {$cur['band_code']} overlap.";
                } elseif ($cur['min_percentage'] - $prev['max_percentage'] > 1.0001) {
                    $problems[] = "Scores between {$prev['max_percentage']}% and {$cur['min_percentage']}% are not covered.";
                }
            }
        }
        return array_values(array_unique($problems));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Normalise band rows. Whole-number tables ("75-89", "58-74") are made
     * continuous by extending each maximum to just below the next minimum
     * (89 -> 89.99), so 89.5% is not left uncovered.
     */
    private function normaliseBands(array $bands): array
    {
        $num = static function ($v): ?float {
            $v = trim(str_replace(['%', ','], ['', '.'], (string) ($v ?? '')));
            return $v === '' || !is_numeric($v) ? null : round((float) $v, 2);
        };
        $out = [];
        foreach (array_slice($bands, 0, self::MAX_BANDS + 1) as $b) {
            $b = (array) $b;
            $code = strtoupper(preg_replace('/\s+/', '', (string) ($b['band_code'] ?? '')));
            $out[] = [
                'band_code' => mb_substr($code, 0, 8),
                'group_code' => ($g = strtoupper(preg_replace('/\s+/', '', (string) ($b['group_code'] ?? '')))) !== '' ? mb_substr($g, 0, 8) : null,
                'band_name' => mb_substr(trim((string) ($b['band_name'] ?? '')) ?: $code, 0, 80),
                'min_percentage' => $num($b['min_percentage'] ?? null),
                'max_percentage' => $num($b['max_percentage'] ?? null),
                'points' => ($p = trim((string) ($b['points'] ?? ''))) === '' ? 0.0 : (is_numeric($p) ? round((float) $p, 1) : null),
                'description' => ($d = trim((string) ($b['description'] ?? ''))) !== '' ? mb_substr($d, 0, 255) : null,
            ];
        }
        // Highest level first, as the school reads a scale.
        usort($out, static fn ($a, $b) => ($b['min_percentage'] ?? -1) <=> ($a['min_percentage'] ?? -1));
        for ($i = 0, $n = count($out); $i < $n - 1; $i++) {
            $hi = $out[$i]; $lo = $out[$i + 1];
            if ($hi['min_percentage'] !== null && $lo['max_percentage'] !== null
                && abs($hi['min_percentage'] - $lo['max_percentage'] - 1) < 0.0001
                && floor($lo['max_percentage']) === $lo['max_percentage']) {
                $out[$i + 1]['max_percentage'] = round($hi['min_percentage'] - 0.01, 2);
            }
        }
        return $out;
    }

    private function writeBands(int $systemId, array $bands): void
    {
        $insert = $this->db->prepare(
            'INSERT INTO grading_system_bands (grading_system_id, band_code, group_code, band_name, min_percentage, max_percentage, points, achievement_level, performance_level, description, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $n = count($bands);
        foreach (array_values($bands) as $i => $b) {
            $insert->execute([
                $systemId, $b['band_code'], $b['group_code'], $b['band_name'], $b['min_percentage'], $b['max_percentage'],
                $b['points'] ?? 0, $n - $i, $b['group_code'] ?? $b['band_name'], $b['description'], $i + 1,
            ]);
        }
    }

    private function meta(array $data, ?int $selfId = null): array
    {
        $name = mb_substr(trim((string) ($data['name'] ?? '')), 0, 150);
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]+/', '-', trim((string) ($data['code'] ?? ''))));
        $code = trim(mb_substr($code, 0, 40), '-');
        if ($name === '') throw new RuntimeException('Give the grading system a name, for example "MoE CBE 8-Level 2027".', 422);
        if ($code === '') $code = trim(mb_substr(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $name)), 0, 40), '-');
        $viewOf = !empty($data['view_of_system_id']) ? (int) $data['view_of_system_id'] : null;
        if ($viewOf && $viewOf === $selfId) throw new RuntimeException('A grading system cannot be a view of itself.', 422);
        if ($viewOf && !(int) $this->value('SELECT COUNT(*) FROM grading_systems WHERE id = ?', [$viewOf])) throw new RuntimeException('The linked grading system does not exist.', 422);
        $supersedes = !empty($data['supersedes_id']) ? (int) $data['supersedes_id'] : null;
        return [
            'name' => $name,
            'code' => $code,
            'view_of_system_id' => $viewOf,
            'supersedes_id' => $supersedes && $supersedes !== $selfId ? $supersedes : null,
            'description' => ($d = trim((string) ($data['description'] ?? ''))) !== '' ? mb_substr($d, 0, 500) : null,
            'authority' => ($a = trim((string) ($data['authority'] ?? ''))) !== '' ? mb_substr($a, 0, 120) : null,
        ];
    }

    /** The system plus any system that is its view or that it is a view of. */
    private function family(int $systemId): array
    {
        $ids = [$systemId];
        $parent = $this->value('SELECT view_of_system_id FROM grading_systems WHERE id = ?', [$systemId]);
        if ($parent) $ids[] = (int) $parent;
        $stmt = $this->db->prepare('SELECT id FROM grading_systems WHERE view_of_system_id = ?');
        $stmt->execute([$systemId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $child) $ids[] = (int) $child;
        return array_values(array_unique($ids));
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'rb');
        if (!$fh) throw new RuntimeException('The file could not be read.', 422);
        $first = fgets($fh) ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($fh);
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '\\')) !== false && count($rows) < 200) {
            if (isset($row[0])) $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function readSpreadsheet(string $path): array
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) throw new RuntimeException('Excel files cannot be read on this server; save the table as CSV.', 422);
        try {
            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        } catch (\Throwable $e) {
            throw new RuntimeException('The Excel file could not be read. Save it as .xlsx or CSV and try again.', 422);
        }
        return array_slice($sheet->toArray(null, true, false, false), 0, 200);
    }

    private function key(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(preg_replace('/^\xEF\xBB\xBF/', '', $header)));
    }

    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
