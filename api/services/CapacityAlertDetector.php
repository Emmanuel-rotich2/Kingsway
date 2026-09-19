<?php

namespace App\API\Services;

use PDO;

/**
 * Capacity alert detector (deterministic, P3a).
 *
 * Monitors governed capacity signals:
 *   - boarding dormitory utilization vs. serviceable beds (over-capacity);
 *   - inventory stock health (out-of-stock and reorder-level breaches).
 *
 * Outputs are aggregate counts and rates only - no learner/student identity,
 * no dormitory occupants, no item-level purchase preferences. This computes
 * alerts; it never assigns beds, buys stock, or changes stock levels.
 */
final class CapacityAlertDetector extends AbstractIntelligenceDetector
{
    /** Utilization % above which a dormitory is over capacity. */
    private const DORM_OVER_CAPACITY_PCT = 95.0;

    /** Utilization % at which a warning is raised. */
    private const DORM_WARNING_PCT = 90.0;

    public function detect(PDO $pdo, array $options = []): array
    {
        $dormOverCapacity = max(0.0, min(110.0, (float) ($options['dorm_over_capacity_pct'] ?? self::DORM_OVER_CAPACITY_PCT)));
        $dormWarning = max(0.0, min(110.0, (float) ($options['dorm_warning_pct'] ?? self::DORM_WARNING_PCT)));

        // Dormitory occupancy.
        $dormStmt = $pdo->prepare(
            'SELECT dormitory_name, gender, capacity, total_assignments, active_assignments,
                    free_beds, utilization_pct
             FROM ' . $this->view('dormitory_occupancy')
            . ' WHERE utilization_pct IS NOT NULL'
        );
        $dormStmt->execute();
        $dorms = $dormStmt->fetchAll(PDO::FETCH_ASSOC);

        $over = [];
        $warn = [];
        $totalBeds = 0;
        $usedBeds = 0;
        foreach ($dorms as $dorm) {
            $util = (float) $dorm['utilization_pct'];
            $capacity = (int) $dorm['capacity'];
            $active = (int) $dorm['active_assignments'];
            $totalBeds += $capacity;
            $usedBeds += $active;
            $entry = [
                'dormitory_name' => (string) $dorm['dormitory_name'],
                'gender' => (string) $dorm['gender'],
                'capacity' => $capacity,
                'active_assignments' => $active,
                'free_beds' => (int) ($dorm['free_beds'] ?? max(0, $capacity - $active)),
                'utilization_pct' => round($util, 1),
            ];
            if ($util >= $dormOverCapacity) {
                $over[] = $entry;
            } elseif ($util >= $dormWarning) {
                $warn[] = $entry;
            }
        }

        // Inventory health.
        $invStmt = $pdo->prepare(
            'SELECT category, stock_status, COUNT(*) AS count
             FROM ' . $this->masterView('vw_inventory_health')
            . ' GROUP BY category, stock_status'
        );
        $invStmt->execute();
        $stockRows = $invStmt->fetchAll(PDO::FETCH_ASSOC);
        $byStatus = [];
        foreach ($stockRows as $row) {
            $status = (string) $row['stock_status'];
            $byStatus[$status] = ($byStatus[$status] ?? 0) + (int) $row['count'];
        }
        $outOfStock = (int) ($byStatus['out_of_stock'] ?? 0);
        $lowStock = (int) ($byStatus['low_stock'] ?? 0);

        $alerts = [];
        if ($over !== []) {
            $alerts[] = [
                'level' => 'critical',
                'code' => 'capacity.dormitory_over_capacity',
                'message' => count($over) . ' dormitor(ies) at or above ' . $dormOverCapacity . '% utilization.',
                'target_scopes' => ['boarding', 'director'],
            ];
        }
        if ($outOfStock > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'capacity.inventory_out_of_stock',
                'message' => $outOfStock . ' stock item(s) are out of stock; review the requisition queue.',
                'target_scopes' => ['inventory', 'director'],
            ];
        } elseif ($lowStock > 0) {
            $alerts[] = [
                'level' => 'info',
                'code' => 'capacity.inventory_reorder_needed',
                'message' => $lowStock . ' stock item(s) at or below reorder level.',
                'target_scopes' => ['inventory'],
            ];
        }

        $overallUtil = $totalBeds > 0 ? round($usedBeds / $totalBeds * 100, 1) : 0.0;

        return $this->result('capacity', [
            'boarding' => [
                'dormitories' => count($dorms),
                'total_capacity_beds' => $totalBeds,
                'active_assignments' => $usedBeds,
                'free_beds' => $totalBeds - $usedBeds,
                'utilization_pct' => $overallUtil,
                'over_capacity_count' => count($over),
                'warning_count' => count($warn),
            ],
            'inventory' => [
                'out_of_stock' => $outOfStock,
                'low_stock' => $lowStock,
                'healthy' => (int) ($byStatus['in_stock'] ?? 0),
            ],
        ], $alerts);
    }
}