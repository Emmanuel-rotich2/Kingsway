<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;
use App\API\Services\ReadReplicaService;

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
                        COUNT(DISTINCT sta.student_id) AS assigned_students
                    FROM transport_vehicles v
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " tvr ON tvr.vehicle_id = v.id AND tvr.status = 'active'
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " sta ON sta.route_id = tvr.route_id
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
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['category_id'])) { $where[] = 'i.category_id = ?'; $params[] = (int) $filters['category_id']; }
            if (!empty($filters['store_id'])) { $where[] = 'i.location_id = ?'; $params[] = (int) $filters['store_id']; }
            if (!empty($filters['status'])) { $where[] = "CASE WHEN i.current_quantity <= 0 THEN 'out_of_stock' WHEN i.current_quantity <= GREATEST(i.minimum_quantity, i.reorder_level) THEN 'low_stock' ELSE 'adequate' END = ?"; $params[] = $filters['status']; }
            $sql = "SELECT i.id, i.name AS item_name, i.current_quantity AS quantity,
                           GREATEST(i.minimum_quantity, i.reorder_level) AS reorder_level,
                           CASE WHEN i.current_quantity <= 0 THEN 'out_of_stock'
                                WHEN i.current_quantity <= GREATEST(i.minimum_quantity, i.reorder_level) THEN 'low_stock'
                                ELSE 'adequate' END AS stock_status,
                           i.unit, c.name AS category
                    FROM " . ReadReplicaService::qualifiedRef("inventory_items") . "
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_categories") . " c ON i.category_id = c.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY i.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getInventoryUsageRates($filters = [])
    {
        try {
            $sql = "SELECT item_id, YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(quantity) as total_used
                    FROM inventory_transactions
                    WHERE transaction_type = 'out'
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
                    FROM requisitions
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
            $sql = "SELECT equipment_id AS asset_id, emt.name AS maintenance_type, COUNT(*) as event_count
                    FROM " . ReadReplicaService::qualifiedRef("equipment_maintenance") . "
                    LEFT JOIN equipment_maintenance_types emt ON emt.id = em.maintenance_type_id
                    GROUP BY equipment_id, emt.name
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
            $sql = "SELECT
                        it.id,
                        it.item_id,
                        it.item_name,
                        it.quantity,
                        it.unit_cost,
                        it.transaction_date AS adjusted_at,
                        it.reference_id,
                        it.notes
                    FROM " . ReadReplicaService::qualifiedRef("inventory_transactions_items") . " it
                    WHERE it.reference_type = 'adjustment'
                    ORDER BY it.transaction_date DESC
                    LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
