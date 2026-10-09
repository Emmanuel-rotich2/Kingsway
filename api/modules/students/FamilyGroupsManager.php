<?php

namespace App\API\Modules\students;

use App\API\Services\ReadReplicaService;
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

            // A parent directory is still useful when the separately
            // materialized finance projection is unavailable. Never block
            // names/contact rows on fee totals or substitute stale values.
            $feeBalanceExpression = 'NULL';
            try {
                $feeView = ReadReplicaService::qualifiedRef('student_fee_balances');
                $feeBalanceExpression = "COALESCE((SELECT SUM(vfb.balance)
                    FROM {$feeView} vfb
                    JOIN " . ReadReplicaService::qualifiedRef('student_parents') . " sp2 ON sp2.student_id = vfb.student_id
                    WHERE sp2.parent_id = p.id), 0)";
            } catch (\RuntimeException $projectionError) {
                \App\API\Includes\FileLogger::write('reads', [
                    'event' => 'parent_directory_fee_totals_unavailable',
                    'projection' => 'student_fee_balances',
                    'error_class' => get_class($projectionError),
                    'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? ''),
                ], 'warning');
            }

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
                    {$feeBalanceExpression} AS total_fee_balance
                FROM " . ReadReplicaService::qualifiedRef("parents") . " p
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id = p.person_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("student_parents") . " sp ON p.id = sp.parent_id
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
                FROM " . ReadReplicaService::qualifiedRef("parents") . " p
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id = p.person_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("student_parents") . " sp ON p.id = sp.parent_id
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
            $stmt = $this->pdo->prepare("SELECT s.staff_id FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s WHERE s.user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $staffId = (int) $stmt->fetchColumn();
            if ($staffId < 1) {
                return ['success' => true, 'data' => [], 'pagination' => ['total' => 0, 'limit' => 0, 'offset' => 0, 'pages' => 0]];
            }

            $sql = "
                SELECT
                    lg.student_id,
                    lg.student_full_name AS student_name,
                    lp.class_name,
                    lp.stream_name,
                    lg.parent_id,
                    lg.parent_full_name AS parent_name,
                    lg.parent_phone,
                    lg.parent_email,
                    lg.relationship,
                    lg.is_primary_contact,
                    lg.is_emergency_contact,
                    NULL AS preferred_contact_method,
                    NULL AS last_contacted
                FROM " . ReadReplicaService::qualifiedRef('learner_guardian') . " lg
                LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement') . " lp ON lp.student_id = lg.student_id
                WHERE EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = ?
                      AND tscope.academic_year_class_stream_id = lp.aycs_id
                      AND tscope.scope_type = 'class_teacher'
                )
                ORDER BY lp.class_name, lp.stream_name, lg.student_full_name, lg.is_primary_contact DESC, lg.parent_full_name
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
            // Native prepares (emulation off) cannot bind one named
            // placeholder to several positions: each occurrence needs its
            // own parameter, otherwise PDO raises HY093.
            $stmt = $this->pdo->prepare("
                SELECT
                    gl.parent_id,
                    CONCAT_WS(' ', gl.parent_first_name, gl.parent_middle_name, gl.parent_last_name) AS parent_name,
                    gl.parent_phone AS phone_1,
                    gl.parent_email AS email,
                    COUNT(DISTINCT gl.student_id) AS students_count
                FROM " . ReadReplicaService::qualifiedRef('guardian_link') . " gl
                WHERE (
                    gl.parent_first_name LIKE :search_1 OR
                    gl.parent_last_name LIKE :search_2 OR
                    gl.parent_email LIKE :search_3
                )
                GROUP BY gl.parent_id, gl.parent_first_name, gl.parent_middle_name, gl.parent_last_name, gl.parent_phone, gl.parent_email
                ORDER BY gl.parent_first_name, gl.parent_last_name
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':search_1', "%{$searchTerm}%");
            $stmt->bindValue(':search_2', "%{$searchTerm}%");
            $stmt->bindValue(':search_3', "%{$searchTerm}%");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT gl.parent_id) AS total_count
                FROM " . ReadReplicaService::qualifiedRef('guardian_link') . " gl
                WHERE (
                    gl.parent_first_name LIKE :search_1 OR
                    gl.parent_last_name LIKE :search_2 OR
                    gl.parent_email LIKE :search_3
                )
            ");
            $stmt->bindValue(':search_1', "%{$searchTerm}%");
            $stmt->bindValue(':search_2', "%{$searchTerm}%");
            $stmt->bindValue(':search_3', "%{$searchTerm}%");
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
            \App\API\Services\Logger::legacyError('[FamilyGroupsManager] family group search failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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
            $stmt = $this->pdo->prepare(
                "SELECT
                    parent_id,
                    first_name,
                    middle_name,
                    last_name,
                    full_name,
                    national_id_no AS id_number,
                    gender,
                    dob AS date_of_birth,
                    phone AS phone_1,
                    NULL AS phone_2,
                    email,
                    parent_occupation,
                    parent_address,
                    parent_status,
                    parent_created_at
                 FROM " . ReadReplicaService::qualifiedRef('person_directory') . "
                 WHERE parent_id = ?"
            );
            $stmt->execute([$parentId]);
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
                    student_id,
                    admission_no,
                    student_first_name AS first_name,
                    student_last_name AS last_name,
                    student_full_name,
                    student_gender AS gender,
                    student_status AS status,
                    relationship,
                    is_primary_contact,
                    is_emergency_contact,
                    NULL AS financial_responsibility
                FROM " . ReadReplicaService::qualifiedRef('guardian_link') . "
                WHERE parent_id = :parent_id
                ORDER BY student_first_name, student_last_name
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
                    student_id,
                    admission_no,
                    student_first_name AS first_name,
                    student_last_name AS last_name,
                    student_full_name,
                    student_gender AS gender,
                    student_status AS status,
                    relationship,
                    is_primary_contact,
                    is_emergency_contact
                FROM " . ReadReplicaService::qualifiedRef('guardian_link') . "
                WHERE parent_id = :parent_id
                ORDER BY student_first_name, student_last_name
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
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE person_id=? LIMIT 1");
        $stmt->execute([$personId]);
        $userId = $stmt->fetchColumn();
        if ($userId !== false) (new \App\API\Modules\users\UserRoleManager($this->pdo))->assignRole((int)$userId, 73, false);
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
                FROM " . ReadReplicaService::qualifiedRef("student_directory") . " 
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
                FROM " . ReadReplicaService::qualifiedRef("student_directory") . " 
                WHERE s.status = 'active'
                AND s.id NOT IN (
                    SELECT student_id FROM " . ReadReplicaService::qualifiedRef("student_parents") . " WHERE parent_id = :parent_id
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

            // All aggregates run on the replica projections; the previous
            // statement filtered p.status over the student_directory table
            // without defining that alias and could never prepare.
            $parents = ReadReplicaService::qualifiedRef('parents');
            $guardianLink = ReadReplicaService::qualifiedRef('guardian_link');
            $students = ReadReplicaService::qualifiedRef('students');
            $studentParents = ReadReplicaService::qualifiedRef('student_parents');

            // Total parents
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM {$parents} WHERE status = 'active'");
            $stats['total_parents'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Parents with children
            $stmt = $this->pdo->query("
                SELECT COUNT(DISTINCT gl.parent_id) as total
                FROM {$guardianLink} gl
                WHERE gl.parent_status = 'active'
            ");
            $stats['parents_with_children'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Parents without children
            $stats['parents_without_children'] = $stats['total_parents'] - $stats['parents_with_children'];

            // Students without parents
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as total
                FROM {$students} s
                LEFT JOIN {$studentParents} sp ON s.id = sp.student_id
                WHERE sp.student_id IS NULL AND s.status = 'active'
            ");
            $stats['students_without_parents'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Average children per parent
            $stmt = $this->pdo->query("
                SELECT AVG(child_count) as avg_children
                FROM (
                    SELECT COUNT(*) as child_count
                    FROM {$studentParents}
                    GROUP BY parent_id
                ) as counts
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['avg_children_per_parent'] = round((float) ($result['avg_children'] ?? 0), 1);

            // Total linked students
            $stmt = $this->pdo->query("SELECT COUNT(DISTINCT student_id) as total FROM {$studentParents}");
            $stats['total_linked_students'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FamilyGroupsManager] family group stats failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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
                ->query("SELECT class_stream_id, class_id, stream_name
                         FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
                         WHERE class_stream_status = 'active' ORDER BY stream_name ASC")
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
            // The guardian_link projection carries both parent and student
            // identity per linked row, so the family directory is one
            // self-contained aggregate read on the replica - no master-table
            // joins. The previous statement selected p./pp. aliases over a
            // single-table FROM and could never prepare.
            $guardianLink = ReadReplicaService::qualifiedRef('guardian_link');
            $stmt = $this->pdo->prepare("
                SELECT
                    gl.parent_id,
                    gl.parent_full_name AS parent_name,
                    gl.parent_phone AS phone_1,
                    gl.parent_email AS email,
                    gl.parent_status AS parent_status,
                    COUNT(DISTINCT gl.student_id) AS students_count,
                    GROUP_CONCAT(
                        DISTINCT gl.student_full_name
                        ORDER BY gl.student_first_name SEPARATOR ', '
                    ) AS student_names
                FROM {$guardianLink} gl
                WHERE gl.parent_status = 'active'
                GROUP BY gl.parent_id, gl.parent_full_name, gl.parent_phone, gl.parent_email, gl.parent_status
                ORDER BY gl.parent_first_name, gl.parent_last_name
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalStmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT gl.parent_id) AS total
                FROM {$guardianLink} gl
                WHERE gl.parent_status = 'active'
            ");
            $totalStmt->execute();
            $total = (int) ($totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? count($rows));

            return [
                'success' => true,
                'data' => $rows,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'total' => $total,
                ],
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FamilyGroupsManager] family groups list failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
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
                FROM " . ReadReplicaService::qualifiedRef("student_parents") . "
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = sp.student_id
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
