<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * How the expected fee revenue for a year is made up.
 *
 * Same source and filters as the "Expected Revenue" figure on the fee
 * structure page (fees actually billed to learners: student fee obligations
 * that are not cancelled or void), broken down by grade and stream, learner
 * type, term and fee item, so every table adds up to the headline figure.
 *
 * Aggregates run on the read projections; names from tables without a
 * projection (terms, fee catalogue) are looked up by id afterwards.
 */
final class FeeRevenueBreakdownService
{
    public function __construct(private PDO $db)
    {
    }

    public function breakdown(int $yearId, ?int $termId = null): array
    {
        $obligations = ReadReplicaService::qualifiedRef('student_fee_obligations');
        $enrolments = ReadReplicaService::qualifiedRef('student_academic_enrollments');
        $schedules = ReadReplicaService::qualifiedRef('academic_year_fee_schedules');

        $base = "FROM {$obligations} sfo
                 LEFT JOIN {$enrolments} sae ON sae.id = sfo.student_academic_enrollment_id
                 JOIN {$schedules} ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
                 WHERE sfo.status NOT IN ('cancelled', 'void') AND ayfs.academic_year_id = ?";
        $params = [$yearId];
        if ($termId) {
            $base .= ' AND ayfs.academic_year_term_id = ?';
            $params[] = $termId;
        }
        $learner = 'COALESCE(sae.student_id, -sfo.student_academic_enrollment_id)';
        $measures = "COUNT(DISTINCT {$learner}) AS learners,
                     COALESCE(SUM(sfo.amount_due), 0) AS billed,
                     COALESCE(SUM(sfo.sponsored_waiver_amount), 0) AS sponsored";

        $rows = fn (string $group, string $key) => $this->rows("SELECT {$key}, {$measures} {$base} GROUP BY {$group}", $params);

        $totals = $this->rows("SELECT {$measures} {$base}", $params)[0] ?? ['learners' => 0, 'billed' => 0, 'sponsored' => 0];
        $byStreamType = $rows('sae.academic_year_class_stream_id, ayfs.student_type_id', 'sae.academic_year_class_stream_id AS stream_id, ayfs.student_type_id AS type_id');
        $byStream = $rows('sae.academic_year_class_stream_id', 'sae.academic_year_class_stream_id AS stream_id');
        $byType = $rows('ayfs.student_type_id', 'ayfs.student_type_id AS type_id');
        $byTerm = $rows('ayfs.academic_year_term_id', 'ayfs.academic_year_term_id AS term_id');
        $byItem = $rows('ayfs.fee_catalog_id', 'ayfs.fee_catalog_id AS item_id');

        $typeNames = $this->typeNames();
        $streamNames = $this->streamNames(array_column($byStream, 'stream_id'));
        $termNames = $this->termNames(array_column($byTerm, 'term_id'));
        $itemNames = $this->keyedNames('fee_catalog', 'name', array_column($byItem, 'item_id'));

        // Grade / stream table with each learner type's share as columns.
        $typeIds = array_values(array_unique(array_map(static fn ($r) => (int) $r['type_id'], $byStreamType)));
        sort($typeIds);
        $split = [];
        foreach ($byStreamType as $r) {
            $split[(int) $r['stream_id']][(int) $r['type_id']] = ['learners' => (int) $r['learners'], 'billed' => (float) $r['billed']];
        }
        $streams = array_map(function ($r) use ($streamNames, $split, $typeIds) {
            $id = (int) $r['stream_id'];
            $name = $streamNames[$id] ?? ['class' => $id ? 'Stream ' . $id : 'Not in a stream', 'stream' => '', 'sort' => PHP_INT_MAX];
            $types = [];
            foreach ($typeIds as $t) $types[$t] = $split[$id][$t] ?? ['learners' => 0, 'billed' => 0.0];
            return [
                'stream_id' => $id,
                'class_name' => $name['class'],
                'stream_name' => $name['stream'],
                'sort' => $name['sort'],
                'learners' => (int) $r['learners'],
                'billed' => (float) $r['billed'],
                'sponsored' => (float) $r['sponsored'],
                'average_per_learner' => (int) $r['learners'] > 0 ? round((float) $r['billed'] / (int) $r['learners'], 2) : 0.0,
                'by_type' => $types,
            ];
        }, $byStream);
        usort($streams, static fn ($a, $b) => [$a['sort'], $a['stream_name']] <=> [$b['sort'], $b['stream_name']]);
        foreach ($streams as &$s) unset($s['sort']);
        unset($s);

        $label = static fn (array $rows, string $key, array $names, string $fallback) => array_map(
            static fn ($r) => [
                'id' => (int) $r[$key],
                'name' => $names[(int) $r[$key]] ?? $fallback,
                'learners' => (int) $r['learners'],
                'billed' => (float) $r['billed'],
                'sponsored' => (float) $r['sponsored'],
            ],
            $rows
        );
        $types = $label($byType, 'type_id', $typeNames, 'Unspecified');
        $terms = $label($byTerm, 'term_id', $termNames, 'Unspecified term');
        $items = $label($byItem, 'item_id', $itemNames, 'Unnamed fee item');
        usort($types, static fn ($a, $b) => $b['billed'] <=> $a['billed']);
        usort($terms, static fn ($a, $b) => strnatcmp($a['name'], $b['name']));
        usort($items, static fn ($a, $b) => $b['billed'] <=> $a['billed']);

        return [
            'academic_year_id' => $yearId,
            'term_id' => $termId,
            'totals' => ['learners' => (int) $totals['learners'], 'billed' => (float) $totals['billed'], 'sponsored' => (float) $totals['sponsored']],
            'learner_types' => array_map(fn ($t) => ['id' => $t, 'name' => $typeNames[$t] ?? 'Unspecified'], $typeIds),
            'by_stream' => $streams,
            'by_type' => $types,
            'by_term' => $terms,
            'by_item' => $items,
        ];
    }

    /**
     * Academic year id from what the fee structure page sends: the year id,
     * the stored year code or the four-digit starting year (same rule as the
     * fee structure list).
     */
    public function yearIdFor(string $year): int
    {
        $year = trim($year);
        if ($year === '') return 0;
        $start = (str_contains($year, '/') || str_contains($year, '-')) ? (int) substr($year, 0, 4) : $year;
        $stmt = $this->db->prepare('SELECT id FROM academic_years WHERE id = ? OR year_code = ? OR YEAR(start_date) = ? LIMIT 1');
        $stmt->execute([$year, $year, $start]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** The active academic year (the page's default). */
    public function currentYearId(): int
    {
        return (int) ($this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 0);
    }

    private function typeNames(): array
    {
        $rows = $this->rows('SELECT id, name FROM ' . ReadReplicaService::qualifiedRef('student_types'), []);
        return array_column($rows, 'name', 'id');
    }

    /** stream id => [class, stream, sort] from the class/stream projections. */
    private function streamNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->rows(
            'SELECT aycs.id, c.name AS class_name, c.id AS class_sort, COALESCE(st.name, "") AS stream_name
             FROM ' . ReadReplicaService::qualifiedRef('academic_year_class_streams') . ' aycs
             JOIN ' . ReadReplicaService::qualifiedRef('academic_year_classes') . ' ayc ON ayc.id = aycs.academic_year_class_id
             JOIN ' . ReadReplicaService::qualifiedRef('classes') . ' c ON c.id = ayc.class_id
             LEFT JOIN ' . ReadReplicaService::qualifiedRef('streams') . " st ON st.id = aycs.stream_id
             WHERE aycs.id IN ({$in})",
            $ids
        );
        $out = [];
        foreach ($rows as $r) $out[(int) $r['id']] = ['class' => (string) $r['class_name'], 'stream' => (string) $r['stream_name'], 'sort' => (int) $r['class_sort']];
        return $out;
    }

    /** academic-year term id => "Term 1" (terms has no projection: keyed reads). */
    private function termNames(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $map = array_column($this->rows('SELECT id, term_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . " WHERE id IN ({$in})", $ids), 'term_id', 'id');
        $names = $this->keyedNames('terms', 'name', array_values($map));
        $out = [];
        foreach ($map as $aytId => $termId) $out[(int) $aytId] = $names[(int) $termId] ?? ('Term ' . $termId);
        return $out;
    }

    private function keyedNames(string $table, string $column, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        return array_column($this->rows("SELECT id, {$column} FROM {$table} WHERE id IN ({$in})", $ids), $column, 'id');
    }

    private function rows(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
