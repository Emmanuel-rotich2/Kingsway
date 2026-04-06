<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class MealReportManager extends BaseAPI
{
    public function getMealAllocations($filters = [])
    {
        try {
            $sql = "SELECT meal_date, meal_type, COUNT(student_id) as allocated_count
                    FROM meal_allocations
                    GROUP BY meal_date, meal_type
                    ORDER BY meal_date DESC, meal_type";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFoodConsumptionTrends($filters = [])
    {
        try {
            $sql = "SELECT food_item_id, YEAR(consumption_date) as year, MONTH(consumption_date) as month, SUM(quantity) as total_consumed
                    FROM food_consumption
                    GROUP BY food_item_id, year, month
                    ORDER BY year DESC, month DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
