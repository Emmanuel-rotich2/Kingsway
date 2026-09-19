<?php

namespace App\API\Services;

use PDO;

/**
 * Fee-collection forecast detector (deterministic, P3a).
 *
 * Extrapolates the next month's expected collections from the governed
 * monthly collection trend view using a simple trailing-average + linear
 * drift model. Zero provider calls: the forecast is a computed series, and a
 * declining trend raises a reviewable alert. It never matches, posts, or
 * settles payments.
 */
final class FeeCollectionForecastDetector extends AbstractIntelligenceDetector
{
    /** How many trailing months feed the forecast. */
    private const TRAILING_MONTHS = 3;

    /** Month-on-month decline (pct) that raises an alert. */
    private const DECLINE_ALERT_PCT = 10.0;

    public function detect(PDO $pdo, array $options = []): array
    {
        $trailing = max(2, min(12, (int) ($options['trailing_months'] ?? self::TRAILING_MONTHS)));
        $declinePct = max(0.0, min(100.0, (float) ($options['decline_alert_pct'] ?? self::DECLINE_ALERT_PCT)));

        $stmt = $pdo->prepare(
            'SELECT month, amount_collected, payment_count, collection_rate_pct
             FROM ' . $this->view('fee_collection_monthly_trend')
            . ' WHERE month IS NOT NULL AND amount_collected IS NOT NULL
               ORDER BY month ASC LIMIT 60'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $months = [];
        foreach ($rows as $row) {
            $months[] = [
                'month' => (string) $row['month'],
                'amount_collected' => (float) $row['amount_collected'],
                'payment_count' => (int) ($row['payment_count'] ?? 0),
                'collection_rate_pct' => $row['collection_rate_pct'] !== null
                    ? (float) $row['collection_rate_pct']
                    : null,
            ];
        }

        // Drop trailing empty/in-progress months (zero collected, zero payments)
        // so a partially-rolled current month is never read as a collapse.
        while ($months !== [] && (float) end($months)['amount_collected'] <= 0 && (int) end($months)['payment_count'] === 0) {
            array_pop($months);
        }

        $trailingWindow = array_slice($months, -$trailing);
        $count = count($trailingWindow);
        if ($count === 0) {
            return $this->result('finance', [
                'forecast' => null,
                'latest_month' => null,
                'trailing_months_used' => 0,
                'trend' => 'insufficient_data',
            ], []);
        }

        $sumX = 0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;
        foreach ($trailingWindow as $i => $row) {
            $sumX += $i;
            $sumY += $row['amount_collected'];
            $sumXY += $i * $row['amount_collected'];
            $sumXX += $i * $i;
        }
        $slope = $count > 1 ? ($count * $sumXY - $sumX * $sumY) / ($count * $sumXX - $sumX * $sumX) : 0.0;
        $mean = $sumY / $count;
        $forecast = max(0.0, $mean + $slope * $count);

        $latest = end($trailingWindow);
        $previous = $count >= 2 ? $trailingWindow[$count - 2]['amount_collected'] : $latest['amount_collected'];
        $changePct = $previous > 0 ? ($latest['amount_collected'] - $previous) / $previous * 100 : 0.0;

        $alerts = [];
        if ($changePct <= -$declinePct) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'finance.collection_declining',
                'message' => 'Fee collections declined ' . number_format(abs($changePct), 1)
                    . '% month-on-month; review collections before the next billing cycle.',
                'target_scopes' => ['finance', 'director'],
            ];
        }

        return $this->result('finance', [
            'latest_month' => $latest['month'],
            'latest_collected' => round($latest['amount_collected'], 2),
            'month_on_month_change_pct' => round($changePct, 1),
            'forecast_next_month' => round($forecast, 2),
            'trailing_months_used' => $count,
            'trend' => $changePct <= -$declinePct ? 'declining' : ($changePct >= $declinePct ? 'improving' : 'stable'),
        ], $alerts);
    }
}