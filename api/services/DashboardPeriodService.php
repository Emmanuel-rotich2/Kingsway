<?php

namespace App\API\Services;

use DateTimeImmutable;
use PDO;

/** Resolves the shared dashboard period control to one authoritative date range. */
final class DashboardPeriodService
{
    private const PERIODS = ['today', 'week', 'month', 'term', 'year'];

    public static function resolve(PDO $db, array $filters = []): array
    {
        $period = strtolower(trim((string) ($filters['period'] ?? 'today')));
        if (!in_array($period, self::PERIODS, true)) {
            $period = 'today';
        }

        $today = new DateTimeImmutable('today');
        $from = $today;
        $to = $today;
        $termId = null;

        if ($period === 'week') {
            $from = $today->modify('monday this week');
        } elseif ($period === 'month') {
            $from = $today->modify('first day of this month');
        } elseif ($period === 'year') {
            $from = $today->setDate((int) $today->format('Y'), 1, 1);
        } elseif ($period === 'term') {
            $stmt = $db->query(
                "SELECT id, opening_date, closing_date
                 FROM academic_year_terms
                 WHERE opening_date <= CURDATE() AND closing_date >= CURDATE()
                 ORDER BY opening_date DESC LIMIT 1"
            );
            $term = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
            if ($term) {
                $termId = (int) $term['id'];
                $from = new DateTimeImmutable((string) $term['opening_date']);
                $closing = new DateTimeImmutable((string) $term['closing_date']);
                $to = $closing < $today ? $closing : $today;
            } else {
                $from = $today->modify('first day of this month');
            }
        }

        return [
            'period' => $period,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'term_id' => $termId,
        ];
    }
}
