<?php

namespace App\API\Modules\students;

use App\Database\Database;
use PDO;
use Exception;

/**
 * FamilyGroupsManager
 * Manages family groups - linking parents/guardians with their children
 * 
 * @package App\API\Modules\students
 * @since 2026-01-05
 */
class FamilyGroupsManager
{
    private Database $db;
    private PDO $pdo;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
    }

    /**
     * Get all parents/guardians with optional search and pagination
     * 
     * @param array $filters Search and filter options
     * @return array
     */
    public function getParents(array $filters = []): array
    {
        try {
            $search = $filters['search'] ?? '';
            $status = $filters['status'] ?? '';
            $limit = (int) ($filters['limit'] ?? 50);
            $offset = (int) ($filters['offset'] ?? 0);

            $params = [];
            $conditions = [];

            if (!empty($search)) {
                $conditions[] = "(
                    p.first_name LIKE :search 
                    OR p.last_name LIKE :search 
                    OR p.id_number LIKE :search 
                    OR p.phone_1 LIKE :search 
                    OR p.email LIKE :search
                )";
                $params['search'] = "%{$search}%";
            }

            if (!empty($status)) {
                $conditions[] = "p.status = :status";
                $params['status'] = $status;
            }

            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $sql = "
                SELECT 
                    p.id,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    CONCAT(p.first_name, COALESCE(CONCAT(' ', p.middle_name), ''), ' ', p.last_name) AS full_name,
                    p.id_number,
                    p.gender,
                    p.date_of_birth,
                    p.phone_1,
                    p.phone_2,
                    p.email,
                    p.occupation,
                    p.address,
                    p.status,
                    p.created_at,
                    COUNT(DISTINCT sp.student_id) AS children_count,
                    COALESCE(
                        (SELECT SUM(sfb.balance) 
                         FROM student_fee_balances sfb 
                         JOIN student_parents sp2 ON sfb.student_id = sp2.student_id 
                         WHERE sp2.parent_id = p.id),
                        0
                    ) AS total_fee_balance
                FROM parents p
                LEFT JOIN student_parents sp ON p.id = sp.parent_id
                {$whereClause}
                GROUP BY p.id
                ORDER BY p.first_name, p.last_name
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "
                SELECT COUNT(DISTINCT p.id) as total
                FROM parents p
                LEFT JOIN student_parents sp ON p.id = sp.parent_id
                {$whereClause}
            ";
            $countStmt = $this->pdo->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(":{$key}", $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'success' => true,
                'data' => $parents,
                'pagination' => [
                    'total' => (int) $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'pages' => ceil($total / $limit)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch parents: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search family groups
     * 
     * @param string $searchTerm Search term
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array
     */
    public function searchFamilyGroups(string $searchTerm = '', int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare("CALL sp_search_family_groups(:search, :limit, :offset)");
            $stmt->bindValue(':search', $searchTerm);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count from second result set
            $stmt->nextRowset();
            $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $total = $totalRow['total_count'] ?? count($results);

            return [
                'success' => true,
                'data' => $results,
                'pagination' => [
                    'total' => (int) $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'pages' => ceil($total / max($limit, 1))
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to search family groups: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get parent details with all children
     * 
     * @param int $parentId Parent ID
     * @return array
     */
    public function getParentDetails(int $parentId): array
    {
        try {
            $stmt = $this->pdo->prepare("CALL sp_get_family_group_details(:parent_id)");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();

            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                return [
                    'success' => false,
                    'message' => 'Parent not found'
                ];
            }

            // Get children from second result set
            $stmt->nextRowset();
            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'parent' => $parent,
                    'children' => $children,
                    'total_children' => count($children)
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get parent details: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get all children for a parent
     * 
     * @param int $parentId Parent ID
     * @return array
     */
    public function getParentChildren(int $parentId): array
    {
        try {
            $stmt = $this->pdo->prepare("CALL sp_get_parent_children(:parent_id)");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();

            $children = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $children
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get parent children: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new parent
     * 
     * @param array $data Parent data
     * @return array
     */
    public function createParent(array $data): array
    {
        try {
            $sql = "CALL sp_create_parent(
                :first_name,
                :middle_name,
                :last_name,
                :id_number,
                :gender,
                :date_of_birth,
                :phone_1,
                :phone_2,
                :email,
                :occupation,
                :address,
                @parent_id,
                @success
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':first_name', $data['first_name'] ?? '');
            $stmt->bindValue(':middle_name', $data['middle_name'] ?? null);
            $stmt->bindValue(':last_name', $data['last_name'] ?? '');
            $stmt->bindValue(':id_number', $data['id_number'] ?? null);
            $stmt->bindValue(':gender', $data['gender'] ?? 'other');
            $stmt->bindValue(':date_of_birth', $data['date_of_birth'] ?? null);
            $stmt->bindValue(':phone_1', $data['phone_1'] ?? '');
            $stmt->bindValue(':phone_2', $data['phone_2'] ?? null);
            $stmt->bindValue(':email', $data['email'] ?? null);
            $stmt->bindValue(':occupation', $data['occupation'] ?? null);
            $stmt->bindValue(':address', $data['address'] ?? null);
            $stmt->execute();

            // Get output parameters
            $result = $this->pdo->query("SELECT @parent_id as parent_id, @success as success")->fetch(PDO::FETCH_ASSOC);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Parent created successfully',
                    'data' => ['id' => (int) $result['parent_id']]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create parent'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create parent: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update a parent
     * 
     * @param int $parentId Parent ID
     * @param array $data Parent data
     * @return array
     */
    public function updateParent(int $parentId, array $data): array
    {
        try {
            $sql = "CALL sp_update_parent(
                :parent_id,
                :first_name,
                :middle_name,
                :last_name,
                :id_number,
                :gender,
                :date_of_birth,
                :phone_1,
                :phone_2,
                :email,
                :occupation,
                :address,
                @success
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':first_name', $data['first_name'] ?? null);
            $stmt->bindValue(':middle_name', $data['middle_name'] ?? null);
            $stmt->bindValue(':last_name', $data['last_name'] ?? null);
            $stmt->bindValue(':id_number', $data['id_number'] ?? null);
            $stmt->bindValue(':gender', $data['gender'] ?? null);
            $stmt->bindValue(':date_of_birth', $data['date_of_birth'] ?? null);
            $stmt->bindValue(':phone_1', $data['phone_1'] ?? null);
            $stmt->bindValue(':phone_2', $data['phone_2'] ?? null);
            $stmt->bindValue(':email', $data['email'] ?? null);
            $stmt->bindValue(':occupation', $data['occupation'] ?? null);
            $stmt->bindValue(':address', $data['address'] ?? null);
            $stmt->execute();

            // Get output parameter
            $result = $this->pdo->query("SELECT @success as success")->fetch(PDO::FETCH_ASSOC);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Parent updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update parent'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update parent: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Link parent to student
     * 
     * @param int $parentId Parent ID
     * @param int $studentId Student ID
     * @param array $linkData Relationship data
     * @return array
     */
    public function linkParentToStudent(int $parentId, int $studentId, array $linkData = []): array
    {
        try {
            $sql = "CALL sp_link_parent_to_student(
                :parent_id,
                :student_id,
                :relationship,
                :is_primary_contact,
                :is_emergency_contact,
                :financial_responsibility,
                @success
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':relationship', $linkData['relationship'] ?? 'guardian');
            $stmt->bindValue(':is_primary_contact', $linkData['is_primary_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':is_emergency_contact', $linkData['is_emergency_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':financial_responsibility', $linkData['financial_responsibility'] ?? 100.00);
            $stmt->execute();

            // Get output parameter
            $result = $this->pdo->query("SELECT @success as success")->fetch(PDO::FETCH_ASSOC);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Parent linked to student successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to link parent to student'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to link parent to student: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Unlink parent from student
     * 
     * @param int $parentId Parent ID
     * @param int $studentId Student ID
     * @return array
     */
    public function unlinkParentFromStudent(int $parentId, int $studentId): array
    {
        try {
            $sql = "CALL sp_unlink_parent_from_student(:parent_id, :student_id, @success)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->execute();

            // Get output parameter
            $result = $this->pdo->query("SELECT @success as success")->fetch(PDO::FETCH_ASSOC);

            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'Parent unlinked from student successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Parent-student link not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to unlink parent from student: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get students not linked to any parent
     * 
     * @return array
     */
    public function getStudentsWithoutParents(): array
    {
        try {
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    CONCAT(s.first_name, COALESCE(CONCAT(' ', s.middle_name), ''), ' ', s.last_name) AS full_name,
                    s.gender,
                    s.status,
                    c.name AS class_name,
                    cs.stream_name
                FROM students s
                LEFT JOIN student_parents sp ON s.id = sp.student_id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE sp.id IS NULL AND s.status = 'active'
                ORDER BY c.name, s.first_name
            ";

            $stmt = $this->pdo->query($sql);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $students
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get students without parents: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get available students for linking to a parent
     * 
     * @param int $parentId Parent ID (to exclude already linked students)
     * @return array
     */
    public function getAvailableStudentsForParent(int $parentId): array
    {
        try {
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    CONCAT(s.first_name, COALESCE(CONCAT(' ', s.middle_name), ''), ' ', s.last_name) AS full_name,
                    s.gender,
                    s.status,
                    c.name AS class_name,
                    cs.stream_name,
                    CONCAT(c.name, ' - ', cs.stream_name) AS class_stream
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.status = 'active'
                AND s.id NOT IN (
                    SELECT student_id FROM student_parents WHERE parent_id = :parent_id
                )
                ORDER BY c.name, s.first_name
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $students
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get available students: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get family group statistics
     * 
     * @return array
     */
    public function getFamilyGroupStats(): array
    {
        try {
            $stats = [];

            // Total parents
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM parents WHERE status = 'active'");
            $stats['total_parents'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Parents with children
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT parent_id) as total 
                FROM student_parents sp 
                JOIN parents p ON sp.parent_id = p.id 
                WHERE p.status = 'active'
            ");
            $stats['parents_with_children'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Parents without children
            $stats['parents_without_children'] = $stats['total_parents'] - $stats['parents_with_children'];

            // Students without parents
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total 
                FROM students s 
                LEFT JOIN student_parents sp ON s.id = sp.student_id 
                WHERE sp.id IS NULL AND s.status = 'active'
            ");
            $stats['students_without_parents'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Average children per parent
            $stmt = $this->pdo->query("
                SELECT AVG(child_count) as avg_children
                FROM (
                    SELECT COUNT(*) as child_count
                    FROM student_parents
                    GROUP BY parent_id
                ) as counts
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['avg_children_per_parent'] = round((float) ($result['avg_children'] ?? 0), 1);

            // Total linked students
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT student_id) as total FROM student_parents");
            $stats['total_linked_students'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get family groups view data
     * 
     * @param array $filters Filter options
     * @return array
     */
    public function getFamilyGroupsView(array $filters = []): array
    {
        try {
            $parentId = $filters['parent_id'] ?? null;
            $status = $filters['status'] ?? 'active';
            $limit = (int) ($filters['limit'] ?? 100);

            $sql = "
                SELECT * FROM vw_family_groups
                WHERE 1=1
            ";
            $params = [];

            if ($parentId) {
                $sql .= " AND parent_id = :parent_id";
                $params['parent_id'] = $parentId;
            }

            if ($status) {
                $sql .= " AND parent_status = :status";
                $params['status'] = $status;
            }

            $sql .= " ORDER BY parent_full_name, student_full_name LIMIT :limit";

            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get family groups view: ' . $e->getMessage()
            ];
        }
    }

    public function getFamilyGroupsMeta(): array
    {
        try {
            $classes = $this->pdo
                ->query("SELECT id, name FROM classes WHERE status IN ('active','completed') ORDER BY name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);
            $streams = $this->pdo
                ->query("SELECT id, class_id, stream_name FROM class_streams WHERE status = 'active' ORDER BY stream_name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'classes' => $classes,
                    'streams' => $streams,
                    'relationship_types' => [
                        'father', 'mother', 'guardian', 'step_father', 'step_mother',
                        'grandparent', 'uncle', 'aunt', 'sibling', 'other',
                    ],
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to load family groups metadata: ' . $e->getMessage(),
            ];
        }
    }

    public function getFamilyGroups(array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 100)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        if ($search !== '') {
            return $this->searchFamilyGroups($search, $limit, $offset);
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id AS parent_id,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS parent_name,
                    p.phone_1,
                    p.email,
                    p.status AS parent_status,
                    COUNT(sp.student_id) AS students_count,
                    GROUP_CONCAT(CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) ORDER BY s.first_name SEPARATOR ', ') AS student_names
                FROM parents p
                LEFT JOIN student_parents sp ON sp.parent_id = p.id
                LEFT JOIN students s ON s.id = sp.student_id
                WHERE p.status = 'active'
                GROUP BY p.id, p.first_name, p.middle_name, p.last_name, p.phone_1, p.email, p.status
                ORDER BY p.first_name, p.last_name
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $rows,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => count($rows),
                ],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to load family groups: ' . $e->getMessage(),
            ];
        }
    }

    public function linkStudentToFamilyGroup(int $parentId, array $data): array
    {
        $studentId = !empty($data['student_id']) ? (int)$data['student_id'] : 0;
        if (!$studentId) {
            return [
                'success' => false,
                'message' => 'Student ID is required',
            ];
        }

        return $this->linkParentToStudent($parentId, $studentId, [
            'relationship' => $data['relationship'] ?? 'guardian',
            'is_primary_contact' => !empty($data['is_primary_contact']) ? 1 : 0,
            'is_emergency_contact' => !empty($data['is_emergency_contact']) ? 1 : 0,
            'financial_responsibility' => $data['financial_responsibility'] ?? 100.00,
        ]);
    }

    public function getChildrenForParentIds(array $parentIds): array
    {
        $parentIds = array_values(array_unique(array_filter(array_map('intval', $parentIds))));
        if (!$parentIds) {
            return [
                'success' => true,
                'data' => [],
            ];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT sp.student_id
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id
                WHERE sp.parent_id IN ({$placeholders})
                  AND s.status = 'active'
                ORDER BY sp.student_id ASC
            ");
            $stmt->execute($parentIds);

            return [
                'success' => true,
                'data' => array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id')),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to load linked children: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a parent (soft delete by setting status to inactive)
     * 
     * @param int $parentId Parent ID
     * @return array
     */
    public function deleteParent(int $parentId): array
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE parents SET status = 'inactive' WHERE id = :id");
            $stmt->bindValue(':id', $parentId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Parent deactivated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Parent not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete parent: ' . $e->getMessage()
            ];
        }
    }
}
