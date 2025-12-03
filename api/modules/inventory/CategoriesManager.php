<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Categories Manager
 * 
 * Manages inventory categories and hierarchy
 */
class CategoriesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listCategories($params = [])
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT i.id) as item_count,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM inventory_categories c
                LEFT JOIN inventory_items i ON c.id = i.category_id
                WHERE c.status = 'active'
                GROUP BY c.id
                ORDER BY c.category_name
            ";
            $stmt = $this->db->query($sql);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['categories' => $categories]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getCategory($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT i.id) as item_count,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM inventory_categories c
                LEFT JOIN inventory_items i ON c.id = i.category_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return formatResponse(false, null, 'Category not found', 404);
            }

            return formatResponse(true, $category);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createCategory($data)
    {
        try {
            if (empty($data['category_name'])) {
                return formatResponse(false, null, 'Category name is required');
            }

            $sql = "
                INSERT INTO inventory_categories (
                    category_name, description, parent_category_id, status, created_at
                ) VALUES (?, ?, ?, 'active', NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['category_name'],
                $data['description'] ?? null,
                $data['parent_category_id'] ?? null
            ]);

            $categoryId = $this->db->lastInsertId();
            $this->logAction('create', $categoryId, "Created category: {$data['category_name']}");

            return formatResponse(true, ['id' => $categoryId], 'Category created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateCategory($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT category_name FROM inventory_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return formatResponse(false, null, 'Category not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = ['category_name', 'description', 'parent_category_id', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE inventory_categories SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated category: {$category['category_name']}");

            return formatResponse(true, null, 'Category updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
