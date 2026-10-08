<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
                FROM " . ReadReplicaService::qualifiedRef("inventory_categories") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " i ON c.id = i.category_id
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
                FROM " . ReadReplicaService::qualifiedRef("inventory_categories") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " i ON c.id = i.category_id
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
            if (empty($data['category_name']) && empty($data['name'])) {
                return formatResponse(false, null, 'Category name is required');
            }

            $name = $data['category_name'] ?? $data['name'];
            $code = $data['code'] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', trim($name)), 0, 20));

            $sql = "
                INSERT INTO inventory_categories (
                    name, code, description, parent_id, status, created_at
                ) VALUES (?, ?, ?, ?, 'active', NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $name,
                $code,
                $data['description'] ?? null,
                $data['parent_category_id'] ?? $data['parent_id'] ?? null
            ]);

            $categoryId = $this->db->lastInsertId();
            $this->logAction('create', $categoryId, "Created category: {$name}");

            return formatResponse(true, ['id' => $categoryId], 'Category created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateCategory($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT name FROM inventory_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return formatResponse(false, null, 'Category not found', 404);
            }

            $updates = [];
            $params = [];

            $fieldMap = [
                'category_name' => 'name',
                'name' => 'name',
                'description' => 'description',
                'parent_category_id' => 'parent_id',
                'parent_id' => 'parent_id',
                'status' => 'status'
            ];

            foreach ($fieldMap as $key => $column) {
                if (isset($data[$key]) && !in_array("$column = ?", $updates, true)) {
                    $updates[] = "$column = ?";
                    $params[] = $data[$key];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE inventory_categories SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated category: {$category['name']}");

            return formatResponse(true, null, 'Category updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT name FROM inventory_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                return formatResponse(false, null, 'Category not found', 404);
            }

            $stmt = $this->db->prepare("UPDATE inventory_categories SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Deactivated category: {$category['name']}");

            return formatResponse(true, null, 'Category deleted successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
