<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class InventoryReportManager extends BaseAPI
{
    public function getTransportReport($filters = [])
    {
        try {
            $sql = "SELECT
                        v.id,
                        v.registration_number,
                        v.make,
                        v.model,
                        v.capacity,
                        v.status,
                        COUNT(DISTINCT tr.student_id) AS assigned_students
                    FROM transport_vehicles v
                    LEFT JOIN transport_routes tr ON tr.vehicle_id = v.id
                    GROUP BY v.id, v.registration_number, v.make, v.model, v.capacity, v.status
                    ORDER BY v.registration_number";
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryStockLevels($filters = [])
    {
        try {
            $sql = "SELECT i.id, i.name, i.current_quantity, i.unit, c.name as category
                    FROM inventory_items i
                    LEFT JOIN inventory_categories c ON i.category_id = c.id
                    ORDER BY i.name";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryUsageRates($filters = [])
    {
        try {
            $sql = "SELECT item_id, YEAR(usage_date) as year, MONTH(usage_date) as month, SUM(quantity_used) as total_used
                    FROM inventory_usage
                    GROUP BY item_id, year, month
                    ORDER BY year DESC, month DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getRequisitionsSummary($filters = [])
    {
        try {
            $sql = "SELECT status, COUNT(*) as total
                    FROM inventory_requisitions
                    GROUP BY status
                    ORDER BY total DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getAssetMaintenanceStats($filters = [])
    {
        try {
            $sql = "SELECT asset_id, maintenance_type, COUNT(*) as event_count
                    FROM asset_maintenance
                    GROUP BY asset_id, maintenance_type
                    ORDER BY event_count DESC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryAdjustmentLogs($filters = [])
    {
        try {
            $sql = "SELECT * FROM inventory_adjustment_logs ORDER BY adjusted_at DESC LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
