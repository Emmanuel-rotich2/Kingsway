<?php
namespace App\API\Modules\reports;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * Canonical catering reporting manager.
 *
 * Uses the existing meal-planning, consumption and inventory sources. It owns
 * reusable catering reports; it is not a dashboard-specific service.
 */
class MealReportManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('catering');
    }

    public function getStats($date = null)
    {
        $date = $this->normaliseDate($date);

        $mealStmt = $this->db->prepare(
            "SELECT COUNT(*) AS meals_planned,
                    COALESCE(SUM(planned_servings), 0) AS planned_servings,
                    SUM(status IN ('prepared', 'served')) AS prepared_meals,
                    COALESCE(SUM(actual_servings), 0) AS actual_servings
             FROM meal_plans
             WHERE plan_date = ?"
        );
        $mealStmt->execute([$date]);
        $meal = $mealStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $costStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(total_cost), 0) AS daily_cost,
                    COALESCE(SUM(quantity_used), 0) AS quantity_used,
                    COALESCE(SUM(waste_quantity), 0) AS waste_quantity
             FROM food_consumption_records
             WHERE consumption_date = ?"
        );
        $costStmt->execute([$date]);
        $consumption = $costStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stockStmt = $this->db->query(
            "SELECT COUNT(*) AS food_items,
                    SUM(stock_status IN ('OUT OF STOCK', 'REORDER', 'LOW STOCK')) AS low_stock
             FROM vw_inventory_health
             WHERE status = 'active'"
        );
        $stock = $stockStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'success' => true,
            'data' => [
                'date' => $date,
                'meals_planned' => (int) ($meal['meals_planned'] ?? 0),
                'planned_servings' => (int) ($meal['planned_servings'] ?? 0),
                'prepared_meals' => (int) ($meal['prepared_meals'] ?? 0),
                'actual_servings' => (int) ($meal['actual_servings'] ?? 0),
                'food_items' => (int) ($stock['food_items'] ?? 0),
                'low_stock' => (int) ($stock['low_stock'] ?? 0),
                'daily_cost' => (float) ($consumption['daily_cost'] ?? 0),
                'quantity_used' => (float) ($consumption['quantity_used'] ?? 0),
                'waste_quantity' => (float) ($consumption['waste_quantity'] ?? 0),
            ],
        ];
    }

    public function getMenu($date = null)
    {
        $date = $this->normaliseDate($date);
        $stmt = $this->db->prepare(
            "SELECT mp.id, mp.plan_date, mp.meal_type,
                    mi.name AS menu_item, mi.description,
                    mp.planned_servings, mp.prepared_quantity,
                    mp.actual_servings, mp.status, mp.prepared_at, mp.notes
             FROM meal_plans mp
             LEFT JOIN menu_items mi ON mi.id = mp.menu_item_id
             WHERE mp.plan_date = ?
             ORDER BY FIELD(mp.meal_type, 'breakfast', 'snack', 'lunch', 'dinner'),
                      mp.id"
        );
        $stmt->execute([$date]);

        return [
            'success' => true,
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getFoodStock($lowStockOnly = false, $limit = 50)
    {
        $limit = max(1, min(200, (int) $limit));
        $where = "status = 'active'";
        if ($lowStockOnly) {
            $where .= " AND stock_status IN ('OUT OF STOCK', 'REORDER', 'LOW STOCK')";
        }

        $sql = "SELECT id, name, code, category, current_quantity,
                       minimum_quantity, reorder_level, stock_status,
                       expiry_status, expiry_date, location, unit_cost,
                       inventory_value, updated_at
                FROM vw_inventory_health
                WHERE {$where}
                ORDER BY FIELD(
                    stock_status,
                    'OUT OF STOCK', 'REORDER', 'LOW STOCK', 'ADEQUATE'
                ), name
                LIMIT {$limit}";

        $rows = $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $rows];
    }

    public function getMealAllocations($filters = [])
    {
        $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $filters['date_to'] ?? date('Y-m-d');

        $stmt = $this->db->prepare(
            "SELECT plan_date AS meal_date, meal_type,
                    COUNT(*) AS planned_items,
                    COALESCE(SUM(planned_servings), 0) AS allocated_count,
                    COALESCE(SUM(actual_servings), 0) AS served_count
             FROM meal_plans
             WHERE plan_date BETWEEN ? AND ?
             GROUP BY plan_date, meal_type
             ORDER BY plan_date DESC,
                      FIELD(meal_type, 'breakfast', 'snack', 'lunch', 'dinner')"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFoodConsumptionTrends($filters = [])
    {
        $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $filters['date_to'] ?? date('Y-m-d');

        $stmt = $this->db->prepare(
            "SELECT fcr.consumption_date,
                    fcr.inventory_item_id,
                    i.name AS item_name,
                    fcr.unit,
                    COALESCE(SUM(fcr.quantity_used), 0) AS total_consumed,
                    COALESCE(SUM(fcr.waste_quantity), 0) AS total_waste,
                    COALESCE(SUM(fcr.total_cost), 0) AS total_cost
             FROM food_consumption_records fcr
             INNER JOIN inventory_items i ON i.id = fcr.inventory_item_id
             WHERE fcr.consumption_date BETWEEN ? AND ?
             GROUP BY fcr.consumption_date, fcr.inventory_item_id,
                      i.name, fcr.unit
             ORDER BY fcr.consumption_date, i.name"
        );
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function normaliseDate($date)
    {
        $value = trim((string) $date);
        if ($value === '') {
            return date('Y-m-d');
        }
        $parsed = strtotime($value);
        if ($parsed === false) {
            throw new \InvalidArgumentException('Invalid date supplied');
        }
        return date('Y-m-d', $parsed);
    }
}
