<?php
declare(strict_types=1);

namespace App\API\Controllers;

use Exception;

/**
 * CateringController
 *
 * ROUTES:
 * GET /api/catering/stats       → getStats()
 * GET /api/catering/menu        → getMenu()      query: date=YYYY-MM-DD
 * GET /api/catering/food-stock  → getFoodStock() query: low_stock=1, limit=N
 */
class CateringController extends BaseController
{
    public function index($id = null, $data = [], $segments = [])
    {
        return $this->success(['message' => 'Catering API is running']);
    }

    /**
     * GET /api/catering/stats
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        $db = \App\Database\Database::getInstance();
        $stats = ['meals_today' => 0, 'food_items' => 0, 'low_stock' => 0, 'daily_cost' => 0];

        try {
            $stmt = $db->query("SELECT COUNT(*) FROM meal_records WHERE DATE(served_at) = CURDATE()");
            $stats['meals_today'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) FROM food_store");
            $stats['food_items'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        try {
            $stmt = $db->query("SELECT COUNT(*) FROM food_store WHERE quantity <= reorder_level");
            $stats['low_stock'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        try {
            $stmt = $db->query("SELECT COALESCE(SUM(total_cost),0) FROM meal_records WHERE DATE(served_at) = CURDATE()");
            $stats['daily_cost'] = (float)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        return $this->success($stats);
    }

    /**
     * GET /api/catering/menu?date=YYYY-MM-DD
     */
    public function getMenu($id = null, $data = [], $segments = [])
    {
        $date = $_GET['date'] ?? date('Y-m-d');
        try {
            $db   = \App\Database\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT * FROM menu_items WHERE menu_date = :date ORDER BY meal_type, id"
            );
            $stmt->execute([':date' => $date]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows ?: []);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }

    /**
     * GET /api/catering/food-stock?low_stock=1&limit=N
     */
    public function getFoodStock($id = null, $data = [], $segments = [])
    {
        $lowStock = !empty($_GET['low_stock']);
        $limit    = min((int)($_GET['limit'] ?? 50), 200);
        try {
            $db    = \App\Database\Database::getInstance();
            $where = $lowStock ? "WHERE quantity <= reorder_level" : "";
            $stmt  = $db->prepare(
                "SELECT * FROM food_store {$where} ORDER BY item_name LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows ?: []);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }
}
