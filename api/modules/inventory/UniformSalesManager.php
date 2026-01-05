<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Uniform Sales Manager
 * 
 * Handles uniform inventory, sales tracking, student sizing, and sales analytics
 * Uniforms: Sweaters, Socks, Shorts, Trousers, Shirts, Blouses, Skirts, Games Skirt, Pajamas
 */
class UniformSalesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    /**
     * Get all uniform items with size availability
     * @param array $params Filter parameters
     * @return array Response
     */
    public function listUniformItems($params = [])
    {
        try {
            $sql = "
                SELECT 
                    ii.id,
                    ii.name,
                    ii.code,
                    ii.category_id,
                    ii.unit_cost,
                    ii.current_quantity as total_stock,
                    ii.reorder_level,
                    ii.status,
                    (SELECT COUNT(*) FROM uniform_sizes WHERE item_id = ii.id AND quantity_available > 0) as available_sizes,
                    (SELECT SUM(quantity_available) FROM uniform_sizes WHERE item_id = ii.id) as total_available,
                    (SELECT SUM(quantity_sold) FROM uniform_sizes WHERE item_id = ii.id) as total_sold
                FROM inventory_items ii
                WHERE ii.category_id = 10
                ORDER BY ii.name ASC
            ";

            $result = $this->db->query($sql, []);
            $items = $result->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return $this->formatSuccess([
                'items' => $items,
                'total_count' => count($items)
            ], 'Uniform items retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve uniform items: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get size variants for a specific uniform item
     * @param int $item_id
     * @return array Response
     */
    public function getUniformSizes($item_id)
    {
        try {
            // Get item details
            $itemSql = "SELECT * FROM inventory_items WHERE id = ? AND category_id = 10";
            $itemStmt = $this->db->query($itemSql, [$item_id]);
            $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return $this->formatError('Uniform item not found', 404);
            }

            // Get size variants
            $sizesSql = "
                SELECT 
                    id,
                    size,
                    quantity_available,
                    quantity_reserved,
                    quantity_sold,
                    unit_price,
                    last_restocked
                FROM uniform_sizes
                WHERE item_id = ?
                ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL')
            ";

            $sizesStmt = $this->db->query($sizesSql, [$item_id]);
            $sizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return $this->formatSuccess([
                'item' => $item,
                'sizes' => $sizes
            ], 'Uniform sizes retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve uniform sizes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Register a uniform sale
     * @param int $student_id
     * @param int $item_id
     * @param array $data Sale details
     * @return array Response
     */
    public function registerUniformSale($student_id, $item_id, $data = [])
    {
        try {
            $size = $data['size'] ?? null;
            $quantity = $data['quantity'] ?? 1;
            $unit_price = $data['unit_price'] ?? 0;
            $sold_by = $data['sold_by'] ?? null;
            $notes = $data['notes'] ?? '';

            if (!$size || !$unit_price) {
                return $this->formatError('Size and unit price are required', 400);
            }

            // Check student exists
            $studentSql = "SELECT id FROM students WHERE id = ?";
            $studentStmt = $this->db->query($studentSql, [$student_id]);
            if (!$studentStmt->fetch()) {
                return $this->formatError('Student not found', 404);
            }

            // Check uniform item exists and size available
            $sizeSql = "SELECT quantity_available, unit_price FROM uniform_sizes 
                       WHERE item_id = ? AND size = ?";
            $sizeStmt = $this->db->query($sizeSql, [$item_id, $size]);
            $sizeData = $sizeStmt->fetch(PDO::FETCH_ASSOC);

            if (!$sizeData || $sizeData['quantity_available'] < $quantity) {
                return $this->formatError('Insufficient stock for size ' . $size, 409);
            }

            // Begin transaction
            $this->db->beginTransaction();

            try {
                // Call stored procedure
                $procSql = "CALL sp_register_uniform_sale(?, ?, ?, ?, ?, ?, ?)";
                $procStmt = $this->db->query(
                    $procSql,
                    [$student_id, $item_id, $size, $quantity, $unit_price, $sold_by, $notes]
                );

                $this->db->commit();

                return $this->formatSuccess([
                    'student_id' => $student_id,
                    'item_id' => $item_id,
                    'size' => $size,
                    'quantity' => $quantity,
                    'total_amount' => $quantity * $unit_price
                ], 'Uniform sale registered successfully');
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            return $this->formatError('Failed to register uniform sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get student uniform sales history
     * @param int $student_id
     * @return array Response
     */
    public function getStudentUniformSales($student_id)
    {
        try {
            $sql = "
                SELECT 
                    us.id,
                    us.item_id,
                    ii.name as item_name,
                    us.size,
                    us.quantity,
                    us.unit_price,
                    us.total_amount,
                    us.payment_status,
                    us.sale_date,
                    us.received_date,
                    us.notes
                FROM uniform_sales us
                JOIN inventory_items ii ON us.item_id = ii.id
                WHERE us.student_id = ?
                ORDER BY us.sale_date DESC
            ";

            $result = $this->db->query($sql, [$student_id]);
            $sales = $result->fetchAll(PDO::FETCH_ASSOC) ?? [];

            // Calculate totals
            $total_amount = array_sum(array_column($sales, 'total_amount'));
            $pending_amount = 0;
            $paid_amount = 0;

            foreach ($sales as $sale) {
                if ($sale['payment_status'] === 'pending' || $sale['payment_status'] === 'partial') {
                    $pending_amount += $sale['total_amount'];
                } else if ($sale['payment_status'] === 'paid') {
                    $paid_amount += $sale['total_amount'];
                }
            }

            return $this->formatSuccess([
                'sales' => $sales,
                'summary' => [
                    'total_sales_count' => count($sales),
                    'total_amount' => $total_amount,
                    'paid_amount' => $paid_amount,
                    'pending_amount' => $pending_amount
                ]
            ], 'Student uniform sales retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve student uniform sales: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update uniform sale payment status
     * @param int $sale_id
     * @param string $payment_status
     * @return array Response
     */
    public function updateUniformSalePayment($sale_id, $payment_status = 'paid')
    {
        try {
            // Validate payment status
            $valid_statuses = ['paid', 'pending', 'partial'];
            if (!in_array($payment_status, $valid_statuses)) {
                return $this->formatError('Invalid payment status', 400);
            }

            $sql = "CALL sp_mark_uniform_sale_paid(?, ?)";
            $this->db->query($sql, [$sale_id, $payment_status]);

            return $this->formatSuccess([
                'sale_id' => $sale_id,
                'payment_status' => $payment_status
            ], 'Uniform sale payment status updated');
        } catch (Exception $e) {
            return $this->formatError('Failed to update payment status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get uniform sales dashboard metrics
     * @return array Response
     */
    public function getUniformSalesDashboard()
    {
        try {
            // Total uniforms sold this month
            $monthlySql = "
                SELECT 
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status IN ('pending', 'partial') THEN total_amount ELSE 0 END) as pending_amount
                FROM uniform_sales
                WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                AND YEAR(sale_date) = YEAR(CURDATE())
            ";

            $monthlyStmt = $this->db->query($monthlySql, []);
            $monthlyData = $monthlyStmt->fetch(PDO::FETCH_ASSOC);

            // Top selling uniform items
            $topItemsSql = "
                SELECT 
                    ii.id,
                    ii.name,
                    COUNT(us.id) as sales_count,
                    SUM(us.quantity) as total_quantity,
                    SUM(us.total_amount) as total_amount
                FROM uniform_sales us
                JOIN inventory_items ii ON us.item_id = ii.id
                WHERE MONTH(us.sale_date) = MONTH(CURDATE())
                AND YEAR(us.sale_date) = YEAR(CURDATE())
                GROUP BY us.item_id
                ORDER BY total_amount DESC
                LIMIT 10
            ";

            $topItemsStmt = $this->db->query($topItemsSql, []);
            $topItems = $topItemsStmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

            // Inventory stock status
            $stockSql = "
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN current_quantity > reorder_level THEN 1 ELSE 0 END) as in_stock,
                    SUM(CASE WHEN current_quantity <= reorder_level AND current_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN current_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock
                FROM inventory_items
                WHERE category_id = 10
            ";

            $stockStmt = $this->db->query($stockSql, []);
            $stockStatus = $stockStmt->fetch(PDO::FETCH_ASSOC);

            return $this->formatSuccess([
                'monthly_metrics' => $monthlyData,
                'top_selling_items' => $topItems,
                'inventory_status' => $stockStatus
            ], 'Uniform sales dashboard data retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve dashboard data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get uniform sales by payment status
     * @return array Response
     */
    public function getUniformPaymentSummary()
    {
        try {
            $sql = "
                SELECT 
                    payment_status,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_amount,
                    COUNT(DISTINCT student_id) as unique_students
                FROM uniform_sales
                GROUP BY payment_status
            ";

            $result = $this->db->query($sql, []);
            $summary = $result->fetchAll(PDO::FETCH_ASSOC) ?? [];

            return $this->formatSuccess([
                'payment_summary' => $summary
            ], 'Uniform payment summary retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve payment summary: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Save/Update student uniform size profile
     * @param int $student_id
     * @param array $sizes Size information
     * @return array Response
     */
    public function updateStudentUniformProfile($student_id, $sizes = [])
    {
        try {
            $sql = "
                INSERT INTO student_uniforms 
                    (student_id, uniform_size, shirt_size, trousers_size, skirt_size, sweater_size)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    uniform_size = VALUES(uniform_size),
                    shirt_size = VALUES(shirt_size),
                    trousers_size = VALUES(trousers_size),
                    skirt_size = VALUES(skirt_size),
                    sweater_size = VALUES(sweater_size),
                    last_updated = NOW()
            ";

            $this->db->query($sql, [
                $student_id,
                $sizes['uniform_size'] ?? null,
                $sizes['shirt_size'] ?? null,
                $sizes['trousers_size'] ?? null,
                $sizes['skirt_size'] ?? null,
                $sizes['sweater_size'] ?? null
            ]);

            return $this->formatSuccess([
                'student_id' => $student_id,
                'sizes' => $sizes
            ], 'Student uniform profile updated');
        } catch (Exception $e) {
            return $this->formatError('Failed to update student profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get student uniform size profile
     * @param int $student_id
     * @return array Response
     */
    public function getStudentUniformProfile($student_id)
    {
        try {
            $sql = "SELECT * FROM student_uniforms WHERE student_id = ?";
            $result = $this->db->query($sql, [$student_id]);
            $profile = $result->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->formatSuccess([], 'No uniform profile found for student');
            }

            return $this->formatSuccess($profile, 'Student uniform profile retrieved');
        } catch (Exception $e) {
            return $this->formatError('Failed to retrieve student profile: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format success response
     */
    private function formatSuccess($data, $message = 'Success')
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Format error response
     */
    private function formatError($message, $code = 400)
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code
        ];
    }
}
