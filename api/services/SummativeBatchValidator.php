<?php

namespace App\API\Services;

/**
 * SummativeBatchValidator
 *
 * Pure, dependency-free validation rules for the pivoted results workspace's
 * batched single-learner saves. Every write path in the results table funnels
 * through these checks so client-side and server-side rules can never drift,
 * and the rules stay hermetically testable.
 */
final class SummativeBatchValidator
{
    public const MAX_DECIMALS = 2;
    public const ENTRY_STATUSES = ['present', 'absent', 'exempted'];

    /**
     * Validate one subject-cell payload item.
     *
     * @param array $item  {marks_obtained, entry_status, max_marks}
     * @return array{ok:bool, value:?float, errors:list<string>}
     */
    public static function validateItem(array $item): array
    {
        $errors = [];
        $status = strtolower(trim((string) ($item['entry_status'] ?? 'present')));
        $maxMarks = (float) ($item['max_marks'] ?? 100);

        if (!in_array($status, self::ENTRY_STATUSES, true)) {
            $errors[] = 'entry_status must be present, absent, or exempted.';
        }

        $value = null;
        $raw = $item['marks_obtained'] ?? null;

        if ($status === 'present') {
            if ($raw === null || $raw === '') {
                $errors[] = 'A mark is required for a present learner.';
            } elseif (!is_numeric($raw)) {
                $errors[] = 'The mark must be numeric.';
            } else {
                $value = (float) $raw;
                if ($value < 0 || $value > $maxMarks) {
                    $errors[] = sprintf('The mark must be between 0 and %s.', self::formatMarks($maxMarks));
                }
                $decimals = self::decimalsOf((string) $raw);
                if ($decimals > self::MAX_DECIMALS) {
                    $errors[] = sprintf('The mark may not exceed %d decimal place(s).', self::MAX_DECIMALS);
                }
            }
        } elseif ($raw !== null && $raw !== '' && !is_numeric($raw)) {
            $errors[] = 'The mark must be numeric.';
        }

        return ['ok' => $errors === [], 'value' => $value, 'errors' => $errors];
    }

    /**
     * Validate a whole batch up-front so no partial apply can happen on a
     * payload that contains at least one invalid cell.
     *
     * @param array $items
     * @param int   $maxItems
     * @return array{ok:bool, errors:array<int,string>}
     */
    public static function validateBatch(array $items, int $maxItems = 60): array
    {
        if ($items === []) {
            return ['ok' => false, 'errors' => ['At least one changed subject is required.']];
        }
        if (count($items) > $maxItems) {
            return ['ok' => false, 'errors' => [sprintf('A single save may not exceed %d subject cells.', $maxItems)]];
        }
        $errors = [];
        foreach (array_values($items) as $index => $item) {
            $outcome = self::validateItem((array) $item);
            foreach ($outcome['errors'] as $message) {
                $errors[$index] = $message;
            }
        }
        return ['ok' => $errors === [], 'errors' => $errors];
    }

    /**
     * Optimistic-concurrency comparison for the result's updated_at token.
     * Both sides are normalized to seconds so a client echo of the server
     * string always matches regardless of microsecond precision.
     */
    public static function updateMatches(?string $expected, ?string $actual): bool
    {
        if ($expected === null || $expected === '' ) {
            // No expectation means "force" — allowed for the classic modal flow.
            return true;
        }
        if ($actual === null || $actual === '') {
            return false;
        }
        return self::normalize($expected) === self::normalize($actual);
    }

    private static function normalize(string $stamp): string
    {
        // ISO "T" separators arrive as client echoes; MySQL always uses a space.
        $trimmed = preg_replace('/\s+/', ' ', str_replace('T', ' ', trim($stamp))) ?? $stamp;
        [$datePart, $timePart] = array_pad(explode(' ', $trimmed, 2), 2, '00:00:00');
        // Drop microseconds: 12:34:56.789 -> 12:34:56
        $timePart = preg_replace('/(\d{2}:\d{2}:\d{2}).*$/', '$1', $timePart) ?? $timePart;
        return $datePart . ' ' . $timePart;
    }

    /**
     * Paper-exam rollup for ONE learner. $papers: [{id, max_marks, is_active}]
     * for one assessment; $results: that learner's assessment_paper_results
     * rows. A total exists only when every ACTIVE paper is settled:
     * exempted papers drop out of the sum and the completeness rule, any
     * absent paper withholds the total (parent becomes absent), and a paper
     * with no row yet means the rollup is not complete and the parent total
     * must stay untouched.
     *
     * @return array{complete:bool,total:?float,max_marks:float,entry_status:string,absent_paper_ids:list<int>}
     */
    public static function computePaperRollup(array $papers, array $results): array
    {
        $byPaper = [];
        foreach ($results as $result) {
            $byPaper[(int) ($result['assessment_paper_id'] ?? 0)] = (array) $result;
        }
        $total = 0.0;
        $maxSum = 0.0;
        $complete = $papers !== [];
        $absent = [];
        foreach ($papers as $paper) {
            if ((int) ($paper['is_active'] ?? 1) !== 1) continue;
            $maxSum += (float) $paper['max_marks'];
            $row = $byPaper[(int) ($paper['id'] ?? 0)] ?? null;
            if ($row === null) { $complete = false; continue; }
            $status = strtolower(trim((string) ($row['entry_status'] ?? '')));
            if ($status === 'exempted') continue;
            $marks = $row['marks_obtained'] ?? null;
            if ($status === 'absent') {
                // Absent is settled: the rollup stays decidable but the
                // total is withheld (parent records an absence).
                $absent[] = (int) ($paper['id'] ?? 0);
                continue;
            }
            if ($marks === null || $marks === '' || !is_numeric($marks)) {
                $complete = false;
                continue;
            }
            $total += (float) $marks;
        }
        return [
            'complete' => $complete,
            'total' => ($complete && $absent === []) ? round($total, 2) : null,
            'max_marks' => round($maxSum, 2),
            'entry_status' => $absent !== [] ? 'absent' : ($complete ? 'present' : 'incomplete'),
            'absent_paper_ids' => $absent,
        ];
    }

    private static function decimalsOf(string $raw): int
    {
        if (stripos($raw, 'e') !== false) {
            // Scientific notation: normalize through the float value.
            $raw = self::formatMarks((float) $raw);
        }
        $pos = strrpos($raw, '.');
        return $pos === false ? 0 : strlen($raw) - $pos - 1;
    }

    /** 100.0 -> "100", 37.5 -> "37.5": no rtrim zero-eating in error text. */
    private static function formatMarks(float $value): string
    {
        return (string) (((int) $value) === $value ? (int) $value : $value);
    }
}
