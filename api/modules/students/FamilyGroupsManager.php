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
                    pp.first_name LIKE :search 
                    OR pp.last_name LIKE :search 
                    OR pp.national_id_no LIKE :search 
                    OR pp.phone LIKE :search 
                    OR pp.email LIKE :search
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
                    pp.first_name,
                    pp.middle_name,
                    pp.last_name,
                    CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) AS full_name,
                    pp.national_id_no AS id_number,
                    pp.gender,
                    pp.dob AS date_of_birth,
                    pp.phone AS phone_1,
                    NULL AS phone_2,
                    pp.email,
                    p.occupation,
                    p.address,
                    p.status,
                    p.created_at,
                    COUNT(DISTINCT sp.student_id) AS children_count,
                    COALESCE(
                        (SELECT SUM(vfb.balance)
                         FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " vfb
                         JOIN student_parents sp2 ON vfb.student_id = sp2.student_id
                         WHERE sp2.parent_id = p.id),
                        0
                    ) AS total_fee_balance
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                LEFT JOIN student_parents sp ON p.id = sp.parent_id
                {$whereClause}
                GROUP BY p.id
                ORDER BY pp.first_name, pp.last_name
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
                'message' => 'An internal error occurred.'
            ];
        }
    }

    /**
     * Return one row per learner/parent relationship for the logged-in
     * teacher's assigned class-teacher streams.
     */
    public function getClassParentContacts(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $staffId = (int) $stmt->fetchColumn();
            if ($staffId < 1) {
                return ['success' => true, 'data' => [], 'pagination' => ['total' => 0, 'limit' => 0, 'offset' => 0, 'pages' => 0]];
            }

            $sql = "
                SELECT
                    s.id AS student_id,
                    CONCAT_WS(' ', student_person.first_name, student_person.middle_name, student_person.last_name) AS student_name,
                    c.name AS class_name,
                    streams.name AS stream_name,
                    parent.id AS parent_id,
                    CONCAT_WS(' ', parent_person.first_name, parent_person.middle_name, parent_person.last_name) AS parent_name,
                    parent_person.phone AS parent_phone,
                    parent_person.email AS parent_email,
                    sp.relationship,
                    sp.is_primary_contact,
                    sp.is_emergency_contact,
                    NULL AS preferred_contact_method,
                    NULL AS last_contacted
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id AND ay.is_current = 1
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams ON streams.id = aycs.stream_id
                JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active'
                JOIN students s ON s.id = sae.student_id
                JOIN persons student_person ON student_person.id = s.person_id
                JOIN student_parents sp ON sp.student_id = s.id
                JOIN parents parent ON parent.id = sp.parent_id
                JOIN persons parent_person ON parent_person.id = parent.person_id
                WHERE EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = ?
                      AND tscope.academic_year_class_stream_id = aycs.id
                      AND tscope.scope_type = 'class_teacher'
                )
                ORDER BY c.name, streams.name, student_name, sp.is_primary_contact DESC, parent_name
            ";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$staffId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return [
                'success' => true,
                'data' => $rows,
                'pagination' => ['total' => count($rows), 'limit' => count($rows), 'offset' => 0, 'pages' => $rows ? 1 : 0],
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FamilyGroupsManager::getClassParentContacts] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to load class parent contacts'];
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
            $stmt = $this->pdo->prepare("
                SELECT
                    sp.parent_id,
                    CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) AS parent_name,
                    pp.email,
                    COUNT(DISTINCT sp.student_id) AS child_count
                FROM student_parents sp
                JOIN parents pr ON pr.id = sp.parent_id
                JOIN persons pp ON pp.id = pr.person_id
                WHERE (
                    pp.first_name LIKE :search OR
                    pp.last_name LIKE :search OR
                    pp.email LIKE :search
                )
                GROUP BY sp.parent_id, pp.first_name, pp.middle_name, pp.last_name, pp.email
                ORDER BY pp.first_name, pp.last_name
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':search', "%{$searchTerm}%");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT sp.parent_id) AS total_count
                FROM student_parents sp
                JOIN parents pr ON pr.id = sp.parent_id
                JOIN persons pp ON pp.id = pr.person_id
                WHERE (
                    pp.first_name LIKE :search OR
                    pp.last_name LIKE :search OR
                    pp.email LIKE :search
                )
            ");
            $stmt->bindValue(':search', "%{$searchTerm}%");
            $stmt->execute();
            $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total_count'] ?? count($results);

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
                'message' => 'An internal error occurred.'
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
            $stmt = $this->pdo->prepare("
                SELECT
                    p.id,
                    pp.first_name,
                    pp.middle_name,
                    pp.last_name,
                    CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) AS full_name,
                    pp.national_id_no AS id_number,
                    pp.gender,
                    pp.dob AS date_of_birth,
                    pp.phone AS phone_1,
                    NULL AS phone_2,
                    pp.email,
                    p.occupation,
                    p.address,
                    p.status,
                    p.created_at
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                WHERE p.id = :parent_id
            ");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();

            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$parent) {
                return [
                    'success' => false,
                    'message' => 'Parent not found'
                ];
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    ps.first_name,
                    ps.last_name,
                    CONCAT_WS(' ', ps.first_name, ps.middle_name, ps.last_name) AS student_full_name,
                    ps.gender,
                    s.status,
                    sp.relationship,
                    sp.is_primary_contact,
                    sp.is_emergency_contact,
                    NULL AS financial_responsibility
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id
                JOIN persons ps ON ps.id = s.person_id
                WHERE sp.parent_id = :parent_id
                ORDER BY ps.first_name, ps.last_name
            ");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->execute();
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
                'message' => 'An internal error occurred.'
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
            $stmt = $this->pdo->prepare("
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    ps.first_name,
                    ps.last_name,
                    CONCAT_WS(' ', ps.first_name, ps.middle_name, ps.last_name) AS student_full_name,
                    ps.gender,
                    s.status,
                    sp.relationship,
                    sp.is_primary_contact,
                    sp.is_emergency_contact
                FROM student_parents sp
                JOIN students s ON s.id = sp.student_id
                JOIN persons ps ON ps.id = s.person_id
                WHERE sp.parent_id = :parent_id
                ORDER BY ps.first_name, ps.last_name
            ");
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
                'message' => 'An internal error occurred.'
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
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO persons (first_name, middle_name, last_name, dob, gender, national_id_no, email, phone, data_scope)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'live')
            ");
            $stmt->execute([
                $data['first_name'] ?? '',
                $data['middle_name'] ?? null,
                $data['last_name'] ?? '',
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? 'other',
                $data['id_number'] ?? null,
                $data['email'] ?? null,
                $data['phone_1'] ?? null,
            ]);
            $personId = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("
                INSERT INTO parents (person_id, occupation, address, status)
                VALUES (?, ?, ?, 'active')
            ");
            $stmt->execute([
                $personId,
                $data['occupation'] ?? null,
                $data['address'] ?? null,
            ]);
            $parentId = (int) $this->pdo->lastInsertId();

            $this->ensureParentRoleForPerson($personId);

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Parent created successfully',
                'data' => ['id' => $parentId]
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            \App\API\Services\Logger::legacyError('FamilyGroupsManager::createParent error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An internal error occurred.'
            ];
        }
    }

    private function ensureParentRoleForPerson(int $personId): void
    {
        $this->pdo->prepare(
            "INSERT INTO user_roles (user_id, role_id)
             SELECT u.id, 73
             FROM users u
             JOIN roles r ON r.id = 73 AND r.name = 'Parent'
             WHERE u.person_id = ?
             ON DUPLICATE KEY UPDATE user_id = user_id"
        )->execute([$personId]);
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
            $this->pdo->beginTransaction();

            $personSets = [];
            $personParams = [];
            foreach ([
                'first_name' => 'first_name',
                'middle_name' => 'middle_name',
                'last_name' => 'last_name',
                'id_number' => 'national_id_no',
                'gender' => 'gender',
                'date_of_birth' => 'dob',
                'phone_1' => 'phone',
                'email' => 'email',
            ] as $inputKey => $column) {
                if (array_key_exists($inputKey, $data) && $data[$inputKey] !== null) {
                    $personSets[] = "{$column} = COALESCE(:{$inputKey}, {$column})";
                    $personParams[":{$inputKey}"] = $data[$inputKey];
                }
            }

            if ($personSets) {
                $stmt = $this->pdo->prepare("
                    UPDATE parents p
                    JOIN persons pp ON pp.id = p.person_id
                    SET " . implode(', ', $personSets) . "
                    WHERE p.id = :parent_id
                ");
                foreach ($personParams as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
                $stmt->execute();
            }

            $parentSets = [];
            $parentParams = [];
            foreach (['occupation', 'address'] as $column) {
                if (array_key_exists($column, $data) && $data[$column] !== null) {
                    $parentSets[] = "{$column} = COALESCE(:{$column}, {$column})";
                    $parentParams[":{$column}"] = $data[$column];
                }
            }

            if ($parentSets) {
                $stmt = $this->pdo->prepare("
                    UPDATE parents SET " . implode(', ', $parentSets) . " WHERE id = :parent_id
                ");
                foreach ($parentParams as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Parent updated successfully'
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            \App\API\Services\Logger::legacyError('FamilyGroupsManager::updateParent error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An internal error occurred.'
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
            $stmt = $this->pdo->prepare("
                INSERT INTO student_parents (
                    student_id, parent_id, relationship,
                    is_primary_contact, is_emergency_contact
                ) VALUES (
                    :student_id, :parent_id, :relationship,
                    :is_primary_contact, :is_emergency_contact
                ) ON DUPLICATE KEY UPDATE
                    relationship = :relationship2,
                    is_primary_contact = :is_primary_contact2,
                    is_emergency_contact = :is_emergency_contact2
            ");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':relationship', $linkData['relationship'] ?? 'guardian');
            $stmt->bindValue(':is_primary_contact', $linkData['is_primary_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':is_emergency_contact', $linkData['is_emergency_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':relationship2', $linkData['relationship'] ?? 'guardian');
            $stmt->bindValue(':is_primary_contact2', $linkData['is_primary_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':is_emergency_contact2', $linkData['is_emergency_contact'] ?? 0, PDO::PARAM_INT);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Parent linked to student successfully'
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('FamilyGroupsManager::linkParentToStudent error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An internal error occurred.'
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
            $stmt = $this->pdo->prepare("
                DELETE FROM student_parents
                WHERE parent_id = :parent_id AND student_id = :student_id
            ");
            $stmt->bindValue(':parent_id', $parentId, PDO::PARAM_INT);
            $stmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Parent unlinked from student successfully'
                ];
            }
            return [
                'success' => false,
                'message' => 'Parent-student link not found'
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('FamilyGroupsManager::unlinkParentFromStudent error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An internal error occurred.'
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
                    CONCAT(p.first_name, COALESCE(CONCAT(' ', p.middle_name), ''), ' ', p.last_name) AS full_name,
                    p.gender,
                    s.status,
                    c.name AS class_name,
                    sm.name AS stream_name
                FROM students s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN student_parents sp ON s.id = sp.student_id
                LEFT JOIN student_academic_enrollments sae 
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                WHERE sp.student_id IS NULL AND s.status = 'active'
                ORDER BY c.name, p.first_name
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
                'message' => 'An internal error occurred.'
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
                    CONCAT(p.first_name, COALESCE(CONCAT(' ', p.middle_name), ''), ' ', p.last_name) AS full_name,
                    p.gender,
                    s.status,
                    c.name AS class_name,
                    sm.name AS stream_name,
                    CONCAT(c.name, ' - ', sm.name) AS class_stream
                FROM students s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN student_academic_enrollments sae 
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                WHERE s.status = 'active'
                AND s.id NOT IN (
                    SELECT student_id FROM student_parents WHERE parent_id = :parent_id
                )
                ORDER BY c.name, p.first_name
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
                'message' => 'An internal error occurred.'
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
                WHERE sp.student_id IS NULL AND s.status = 'active'
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
                'message' => 'An internal error occurred.'
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
                'message' => 'An internal error occurred.'
            ];
        }
    }

    public function getFamilyGroupsMeta(): array
    {
        try {
            $classes = $this->pdo
                ->query("SELECT id, name FROM classes ORDER BY name ASC")
                ->fetchAll(PDO::FETCH_ASSOC);
            $streams = $this->pdo
                ->query("SELECT aycs.id, aycs.academic_year_class_id AS class_id, sm.name AS stream_name
                         FROM academic_year_class_streams aycs
                         JOIN streams sm ON sm.id = aycs.stream_id
                         WHERE aycs.status = 'active' ORDER BY sm.name ASC")
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
                'message' => 'An internal error occurred.',
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
                    CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) AS parent_name,
                    pp.phone AS phone_1,
                    pp.email,
                    p.status AS parent_status,
                    COUNT(sp.student_id) AS students_count,
                    GROUP_CONCAT(CONCAT_WS(' ', ps.first_name, ps.middle_name, ps.last_name) ORDER BY ps.first_name SEPARATOR ', ') AS student_names
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                LEFT JOIN student_parents sp ON sp.parent_id = p.id
                LEFT JOIN students s ON s.id = sp.student_id
                LEFT JOIN persons ps ON ps.id = s.person_id
                WHERE p.status = 'active'
                GROUP BY p.id, pp.first_name, pp.middle_name, pp.last_name, pp.phone, pp.email, p.status
                ORDER BY pp.first_name, pp.last_name
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
                'message' => 'An internal error occurred.',
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
                'message' => 'An internal error occurred.',
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
                'message' => 'An internal error occurred.'
            ];
        }
    }

}
