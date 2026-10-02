<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use InvalidArgumentException;

/**
 * Normalises the query filters of the student-fee ledger.
 *
 * The ledger reads from either the materialized summary (numeric
 * `term_number`, `class_id`) or the enhanced view (`term_number` as stored,
 * no `class_id`), and the browser may send a term as 3 or T3, a class as a
 * name or an id, and an overpayment as `credit` or `overpaid`. Those spellings
 * must all resolve to the same stored value, and anything unrecognised must be
 * reported instead of quietly matching zero rows.
 *
 * Deliberately free of database access so the rules are unit-testable.
 */
final class FeeLedgerFilter
{
    /** Statuses the fee ledger stores. */
    public const STATUS_VALUES = [
        'paid', 'partial', 'pending', 'credit', 'no_due', 'waived', 'arrears',
    ];

    /**
     * UI label -> stored value. A page rename must never silently turn
     * "Overpaid" into "Paid" and report the wrong money.
     */
    private const STATUS_ALIASES = [
        'overpaid' => 'credit',
        'credit' => 'credit',
        'unpaid' => 'pending',
        'not_paid' => 'pending',
        'fully_paid' => 'paid',
        'fully paid' => 'paid',
        'partially_paid' => 'partial',
        'part paid' => 'partial',
    ];

    /**
     * Resolve a payment-status filter to a stored status.
     *
     * @throws InvalidArgumentException when the value is not a known status,
     *         so a stale or invented filter fails loudly.
     */
    public function status($value): string
    {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            throw new InvalidArgumentException('Empty payment status filter.');
        }
        $resolved = self::STATUS_ALIASES[$raw] ?? $raw;
        if (!in_array($resolved, self::STATUS_VALUES, true)) {
            throw new InvalidArgumentException('Invalid payment status filter: ' . $raw);
        }
        return $resolved;
    }

    /**
     * Resolve a term filter to the numeric term the ledger stores.
     *
     * Returns null for "no scope" (empty or "all"), which the ledger reads as
     * the whole year rather than a term literally numbered 0.
     *
     * @throws InvalidArgumentException for a term that cannot exist.
     */
    public function term($value): ?int
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '' || $raw === 'ALL') {
            return null;
        }
        if (!preg_match('/^T?0*(\d{1,2})$/', $raw, $m)) {
            throw new InvalidArgumentException('Invalid term filter: ' . $raw);
        }
        $term = (int) $m[1];
        if ($term < 1 || $term > 3) {
            throw new InvalidArgumentException('Invalid term filter: ' . $raw);
        }
        return $term;
    }

    /**
     * The canonical academic-year value: an id, a "2026/2027" code, or a bare
     * "2026" that identifies the year opening in it.
     *
     * This must NOT be reduced to the first four digits — that destroys the
     * stored year code and makes the filter match on a guess.
     */
    public function academicYear($value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        if (preg_match('/^\d{4}\s*[-\/]\s*\d{4}$/', $text)) {
            return str_replace(' ', '', $text);
        }
        return $text;
    }

    /** Whether the academic-year filter selects a year by its opening year. */
    public function isOpeningYearFilter(string $value): bool
    {
        return (bool) preg_match('/^\d{4}$/', trim($value));
    }

    /**
     * Normalise the whole filter set. Empty and zero values are dropped so
     * they never reach the query as a real constraint.
     */
    public function normalize(array $filters): array
    {
        $out = [];
        foreach (['student_id', 'class_id'] as $key) {
            if (!empty($filters[$key])) {
                $out[$key] = (int) $filters[$key];
            }
        }
        foreach (['class_name', 'search', 'amount_range'] as $key) {
            if (!empty($filters[$key])) {
                $out[$key] = trim((string) $filters[$key]);
            }
        }
        foreach (['academic_year', 'term_number', 'status'] as $key) {
            if (!empty($filters[$key])) {
                $out[$key] = trim((string) $filters[$key]);
            }
        }
        if (!empty($filters['academic_year'])) {
            $out['academic_year'] = $this->academicYear($filters['academic_year']);
        }
        if (!empty($filters['term_number'])) {
            $out['term_number'] = $this->term($filters['term_number']);
        }
        if (!empty($filters['status'])) {
            $out['status'] = $this->status($filters['status']);
        }
        return $out;
    }
}