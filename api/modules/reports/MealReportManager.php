<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class MealReportManager extends BaseAPI
{
    public function getMealAllocations($filters = [])
    {
        // Example: Get meal allocations by date and meal type
        $sql = "SELECT meal_date, meal_type, COUNT(student_id) as allocated_count
                FROM meal_allocations
                GROUP BY meal_date, meal_type
                ORDER BY meal_date DESC, meal_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFoodConsumptionTrends($filters = [])
    {
        // Example: Sum food consumption by item and month
        $sql = "SELECT food_item_id, YEAR(consumption_date) as year, MONTH(consumption_date) as month, SUM(quantity) as total_consumed
                FROM food_consumption
                GROUP BY food_item_id, year, month";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
