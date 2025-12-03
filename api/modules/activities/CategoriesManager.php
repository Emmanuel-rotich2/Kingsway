<?php
namespace App\API\Modules\activities;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * CategoriesManager - Manages activity categories
 * Handles categorization and classification of activities
 */
class CategoriesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activity_categories');
    }

    /**
     * List all activity categories
     * 
     * @param array $params Filter parameters
     * @return array List of categories
     */
    public function listCategories($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            // Filter by search term
            if (!empty($params['search'])) {
                $where[] = '(name LIKE ? OR description LIKE ?)';
                $searchTerm = '%' . $params['search'] . '%';
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            // Filter by active status
            if (isset($params['is_active'])) {
                $where[] = 'is_active = ?';
                $bindings[] = (int) $params['is_active'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    ac.*,
                    COUNT(DISTINCT a.id) as activity_count,
                    COUNT(DISTINCT ap.id) as total_participants
                FROM activity_categories ac
                LEFT JOIN activities a ON ac.id = a.category_id
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.status = 'active'
                WHERE $whereClause
                GROUP BY ac.id
                ORDER BY ac.name ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $categories
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to list categories');
            throw $e;
        }
    }

    /**
     * Get a single category by ID
     * 
     * @param int $id Category ID
     * @return array Category details
     */
    public function getCategory($id)
    {
        try {
            $sql = "
                SELECT 
                    ac.*,
                    COUNT(DISTINCT a.id) as activity_count,
                    COUNT(DISTINCT ap.id) as total_participants,
                    SUM(CASE WHEN a.status = 'planned' THEN 1 ELSE 0 END) as planned_activities,
                    SUM(CASE WHEN a.status = 'ongoing' THEN 1 ELSE 0 END) as ongoing_activities,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_activities
                FROM activity_categories ac
                LEFT JOIN activities a ON ac.id = a.category_id
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.status = 'active'
                WHERE ac.id = ?
                GROUP BY ac.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                throw new Exception('Category not found');
            }

            return [
                'success' => true,
                'data' => $category
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get category $id");
            throw $e;
        }
    }

    /**
     * Create a new category
     * 
     * @param array $data Category data
     * @param int $userId User creating the category
     * @return array Created category ID
     */
    public function createCategory($data, $userId)
    {
        $transactionStarted = false;
        try {
            // Validate required fields
            if (empty($data['name'])) {
                throw new Exception('Category name is required');
            }

            // Check for duplicate name
            $stmt = $this->db->prepare("SELECT id FROM activity_categories WHERE name = ?");
            $stmt->execute([$data['name']]);
            if ($stmt->fetch()) {
                throw new Exception('A category with this name already exists');
            }

            $this->db->beginTransaction();
            $transactionStarted = true;

            $sql = "
                INSERT INTO activity_categories (
                    name,
                    description,
                    is_active,
                    created_at
                ) VALUES (?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['is_active'] ?? 1
            ]);

            $categoryId = $this->db->lastInsertId();

            $this->db->commit();

            $this->logAction('create', $categoryId, "Created category: {$data['name']}");

            return [
                'success' => true,
                'data' => ['id' => $categoryId],
                'message' => 'Category created successfully'
            ];

        } catch (Exception $e) {
            if ($transactionStarted) {
                $this->db->rollBack();
            }
            $this->logError($e, 'Failed to create category');
            throw $e;
        }
    }

    /**
     * Update a category
     * 
     * @param int $id Category ID
     * @param array $data Updated data
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateCategory($id, $data, $userId)
    {
        try {
            // Check if category exists
            $stmt = $this->db->prepare("SELECT id, name FROM activity_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                throw new Exception('Category not found');
            }

            // Check for duplicate name if name is being updated
            if (isset($data['name']) && $data['name'] !== $category['name']) {
                $stmt = $this->db->prepare("SELECT id FROM activity_categories WHERE name = ? AND id != ?");
                $stmt->execute([$data['name'], $id]);
                if ($stmt->fetch()) {
                    throw new Exception('A category with this name already exists');
                }
            }

            $this->db->beginTransaction();

            $updates = [];
            $params = [];
            $allowedFields = ['name', 'description', 'is_active'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE activity_categories SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->db->commit();

            $this->logAction('update', $id, "Updated category: {$category['name']}");

            return [
                'success' => true,
                'message' => 'Category updated successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to update category $id");
            throw $e;
        }
    }

    /**
     * Delete a category
     * 
     * @param int $id Category ID
     * @param int $userId User performing the deletion
     * @return array Deletion result
     */
    public function deleteCategory($id, $userId)
    {
        try {
            // Check if category exists
            $stmt = $this->db->prepare("
                SELECT 
                    ac.id, 
                    ac.name,
                    COUNT(a.id) as activity_count
                FROM activity_categories ac
                LEFT JOIN activities a ON ac.id = a.category_id
                WHERE ac.id = ?
                GROUP BY ac.id
            ");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                throw new Exception('Category not found');
            }

            if ($category['activity_count'] > 0) {
                throw new Exception('Cannot delete category with existing activities. Please reassign or delete activities first.');
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM activity_categories WHERE id = ?");
            $stmt->execute([$id]);

            $this->db->commit();

            $this->logAction('delete', $id, "Deleted category: {$category['name']}");

            return [
                'success' => true,
                'message' => 'Category deleted successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to delete category $id");
            throw $e;
        }
    }

    /**
     * Get category statistics
     * 
     * @return array Category statistics
     */
    public function getCategoryStatistics()
    {
        try {
            $sql = "
                SELECT 
                    ac.id,
                    ac.name,
                    COUNT(DISTINCT a.id) as total_activities,
                    COUNT(DISTINCT ap.id) as total_participants,
                    SUM(CASE WHEN a.status = 'planned' THEN 1 ELSE 0 END) as planned,
                    SUM(CASE WHEN a.status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    MAX(a.start_date) as latest_activity_date
                FROM activity_categories ac
                LEFT JOIN activities a ON ac.id = a.category_id
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.status = 'active'
                WHERE ac.is_active = 1
                GROUP BY ac.id
                ORDER BY total_activities DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $stats
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get category statistics');
            throw $e;
        }
    }

    /**
     * Toggle category active status
     * 
     * @param int $id Category ID
     * @param int $userId User performing the action
     * @return array Result
     */
    public function toggleCategoryStatus($id, $userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name, is_active FROM activity_categories WHERE id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                throw new Exception('Category not found');
            }

            $newStatus = $category['is_active'] ? 0 : 1;

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE activity_categories SET is_active = ? WHERE id = ?");
            $stmt->execute([$newStatus, $id]);

            $this->db->commit();

            $statusText = $newStatus ? 'activated' : 'deactivated';
            $this->logAction('update', $id, "Category {$statusText}: {$category['name']}");

            return [
                'success' => true,
                'message' => "Category {$statusText} successfully",
                'data' => ['is_active' => $newStatus]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError($e, "Failed to toggle category status $id");
            throw $e;
        }
    }
}
