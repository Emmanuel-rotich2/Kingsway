<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Modules\academic\AcademicYearManager;
use App\API\Modules\students\PromotionManager;
use PDO;
use Exception;

use App\API\Modules\students\StudentIDCardGenerator;

class StudentsAPI extends BaseAPI
{
    private $idCardGenerator;
    private $yearManager;
    private $promotionManager;

    public function __construct()
    {
        parent::__construct('students');
        $this->idCardGenerator = new StudentIDCardGenerator();
        $this->yearManager = new AcademicYearManager($this->db);
        $this->promotionManager = new PromotionManager($this->db, $this->yearManager);
    }

    // List all students with pagination and search
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();
            $currentAcademicYear = $this->getCurrentAcademicYearValue();
            $visibilityScope = $this->buildStudentVisibilityScope();

            $conditions = [];
            $bindings = [];

            if (!empty($visibilityScope['restricted'])) {
                $scopeClauses = [];

                if (!empty($visibilityScope['student_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($visibilityScope['student_ids']), '?'));
                    $scopeClauses[] = "s.id IN ($placeholders)";
                    $bindings = array_merge($bindings, $visibilityScope['student_ids']);
                }

                if (!empty($visibilityScope['stream_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($visibilityScope['stream_ids']), '?'));
                    $scopeClauses[] = "s.stream_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $visibilityScope['stream_ids']);
                }

                if (!empty($visibilityScope['class_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($visibilityScope['class_ids']), '?'));
                    $scopeClauses[] = "cs.class_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $visibilityScope['class_ids']);
                }

                if (empty($scopeClauses)) {
                    $conditions[] = "1 = 0";
                } else {
                    $conditions[] = '(' . implode(' OR ', $scopeClauses) . ')';
                }
            }

            if (!empty($search)) {
                $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm]);
            }

            // Optional filters
            $classId = $params['class_id'] ?? $_GET['class_id'] ?? null;
            if (!empty($classId)) {
                $conditions[] = "cs.class_id = ?";
                $bindings[] = $classId;
            }

            $streamId = $params['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!empty($streamId)) {
                $conditions[] = "s.stream_id = ?";
                $bindings[] = $streamId;
            }

            $status = $params['status'] ?? $_GET['status'] ?? null;
            if (!empty($status)) {
                $conditions[] = "s.status = ?";
                $bindings[] = $status;
            }

            $gender = $params['gender'] ?? $_GET['gender'] ?? null;
            if (!empty($gender)) {
                $conditions[] = "s.gender = ?";
                $bindings[] = $gender;
            }

            $studentTypeId = $params['student_type_id'] ?? $_GET['student_type_id'] ?? null;
            if (!empty($studentTypeId)) {
                $conditions[] = "s.student_type_id = ?";
                $bindings[] = $studentTypeId;
            }

            $feeStatus = $params['fee_status'] ?? $_GET['fee_status'] ?? null;
            if (!empty($feeStatus)) {
                switch ($feeStatus) {
                    case 'fully_paid':
                        $conditions[] = "COALESCE(fee_summary.total_due, 0) > 0 AND COALESCE(fee_summary.total_balance, 0) <= 0";
                        break;
                    case 'partial':
                        $conditions[] = "COALESCE(fee_summary.total_due, 0) > 0
                            AND COALESCE(fee_summary.total_paid, 0) > 0
                            AND COALESCE(fee_summary.total_balance, 0) > 0";
                        break;
                    case 'unpaid':
                        $conditions[] = "COALESCE(fee_summary.total_due, 0) > 0
                            AND COALESCE(fee_summary.total_paid, 0) <= 0";
                        break;
                    case 'overdue':
                        $conditions[] = "COALESCE(fee_summary.total_balance, 0) > 0
                            AND fee_summary.earliest_balance_due IS NOT NULL
                            AND fee_summary.earliest_balance_due < CURDATE()";
                        break;
                }
            }

            $where = '';
            if (!empty($conditions)) {
                $where = "WHERE " . implode(' AND ', $conditions);
            }

            $feeSummaryWhere = '';
            $joinBindings = [];
            if ($currentAcademicYear !== null) {
                $feeSummaryWhere = "WHERE academic_year = ?";
                $joinBindings[] = $currentAcademicYear;
            }

            $joins = "
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN (
                    SELECT
                        student_id,
                        SUM(amount_due) AS total_due,
                        SUM(amount_paid) AS total_paid,
                        SUM(amount_waived) AS total_waived,
                        SUM(balance) AS total_balance,
                        MIN(CASE WHEN balance > 0 THEN due_date END) AS earliest_balance_due
                    FROM student_fee_obligations
                    {$feeSummaryWhere}
                    GROUP BY student_id
                ) fee_summary ON fee_summary.student_id = s.id
            ";

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM students s
                {$joins}
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($joinBindings, $bindings));
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    s.*,
                    cs.class_id as class_id,
                    c.name as class_name,
                    cs.stream_name,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    st.name AS student_type_name,
                    st.name AS student_type,
                    st.code AS student_type_code,
                    CASE
                        WHEN st.code = 'BOARD' THEN 'boarding'
                        WHEN st.code = 'WEEKLY' THEN 'weekly_boarding'
                        ELSE 'day'
                    END AS boarding_status,
                    COALESCE(fee_summary.total_due, 0) AS total_fees,
                    COALESCE(fee_summary.total_paid, 0) AS total_paid,
                    COALESCE(fee_summary.total_balance, 0) AS fee_balance,
                    (
                        SELECT CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)
                        FROM student_parents sp
                        JOIN parents p ON p.id = sp.parent_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                        LIMIT 1
                    ) AS parent_name,
                    (
                        SELECT p.phone_1
                        FROM student_parents sp
                        JOIN parents p ON p.id = sp.parent_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                        LIMIT 1
                    ) AS parent_phone,
                    (
                        SELECT p.email
                        FROM student_parents sp
                        JOIN parents p ON p.id = sp.parent_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                        LIMIT 1
                    ) AS parent_email,
                    (
                        SELECT p.address
                        FROM student_parents sp
                        JOIN parents p ON p.id = sp.parent_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                        LIMIT 1
                    ) AS parent_address
                FROM students s
                {$joins}
                $where
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($joinBindings, $bindings, [$limit, $offset]));
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed students');

            return $this->response([
                'status' => 'success',
                'data' => [
                    'students' => $students,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single student
    public function get($id)
    {
        try {
            $scope = $this->buildStudentVisibilityScope();
            if (!$this->canAccessStudentId((int) $id, $scope)) {
                return $this->response(['status' => 'error', 'message' => 'Access denied'], 403);
            }

            $student = $this->getStudentOverviewRecord($id);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Optionally, add more details if available (e.g., attendance, fee summary)
            // $student['attendance'] = $this->getAttendanceSummary($id);

            $this->logAction('read', $id, "Retrieved student details: {$student['first_name']} {$student['last_name']}");

            return $this->response(['status' => 'success', 'data' => $student]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getCurrentAcademicYearValue()
    {
        $stmt = $this->db->query("SELECT year_code FROM academic_years WHERE is_current = 1 LIMIT 1");
        $yearCode = $stmt->fetchColumn();

        if ($yearCode === false || $yearCode === null || $yearCode === '') {
            return null;
        }

        if (preg_match('/(\d{4})/', (string) $yearCode, $matches)) {
            return (int) $matches[1];
        }

        return is_numeric($yearCode) ? (int) $yearCode : null;
    }

    private function getCurrentAuthUser(): array
    {
        $user = $_SERVER['auth_user'] ?? $_REQUEST['user'] ?? [];
        return is_array($user) ? $user : [];
    }

    private function normalizeRoleName(string $roleName): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($roleName)), '_');
    }

    private function getCurrentRoleNamesForScope(array $user): array
    {
        $roleNames = [];

        if (!empty($user['role_names']) && is_array($user['role_names'])) {
            foreach ($user['role_names'] as $roleName) {
                if ($roleName) {
                    $roleNames[] = $this->normalizeRoleName((string) $roleName);
                }
            }
        }

        if (!empty($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if (is_array($role) && !empty($role['name'])) {
                    $roleNames[] = $this->normalizeRoleName((string) $role['name']);
                } elseif (is_object($role) && !empty($role->name)) {
                    $roleNames[] = $this->normalizeRoleName((string) $role->name);
                } elseif (is_string($role) && $role !== '') {
                    $roleNames[] = $this->normalizeRoleName($role);
                }
            }
        }

        return array_values(array_unique(array_filter($roleNames)));
    }

    private function getCurrentPermissionCodesForScope(array $user): array
    {
        $permissions = [];

        foreach (['effective_permissions', 'permissions'] as $field) {
            if (!empty($user[$field]) && is_array($user[$field])) {
                foreach ($user[$field] as $permission) {
                    if ($permission) {
                        $permissions[] = strtolower((string) $permission);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }

    private function userHasGlobalStudentViewAccess(array $user): bool
    {
        $permissions = $this->getCurrentPermissionCodesForScope($user);
        if (in_array('*', $permissions, true) || in_array('students_view_all', $permissions, true)) {
            return true;
        }

        $roleNames = $this->getCurrentRoleNamesForScope($user);
        $globalRoles = [
            'system_administrator',
            'director',
            'school_administrator',
            'headteacher',
            'deputy_head_academic',
            'deputy_head_discipline',
            'registrar'
        ];

        return count(array_intersect($roleNames, $globalRoles)) > 0;
    }

    private function buildStudentVisibilityScope(): array
    {
        $scope = [
            'restricted' => true,
            'student_ids' => [],
            'class_ids' => [],
            'stream_ids' => []
        ];

        $user = $this->getCurrentAuthUser();
        if (empty($user)) {
            return $scope;
        }

        if ($this->userHasGlobalStudentViewAccess($user)) {
            $scope['restricted'] = false;
            return $scope;
        }

        $scope['student_ids'] = $this->resolveCurrentStudentIdsForScope($user);

        $parentIds = $this->resolveCurrentParentIdsForScope($user);
        if (!empty($parentIds)) {
            $scope['student_ids'] = array_values(array_unique(array_merge(
                $scope['student_ids'],
                $this->getStudentIdsForParentIds($parentIds)
            )));
        }

        $staffId = $this->resolveCurrentStaffIdForScope($user);
        if ($staffId) {
            $academicYearId = $this->getCurrentAcademicYearIdForScope();
            $staffScope = $this->resolveClassScopeForStaff($staffId, $academicYearId);
            $scope['class_ids'] = $staffScope['class_ids'];
            $scope['stream_ids'] = $staffScope['stream_ids'];
        }

        return $scope;
    }

    private function canAccessStudentId(int $studentId, array $scope): bool
    {
        if ($studentId <= 0) {
            return false;
        }

        if (empty($scope['restricted'])) {
            return true;
        }

        if (!empty($scope['student_ids']) && in_array($studentId, $scope['student_ids'], true)) {
            return true;
        }

        if (empty($scope['class_ids']) && empty($scope['stream_ids'])) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT s.stream_id, cs.class_id
            FROM students s
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $streamId = !empty($row['stream_id']) ? (int) $row['stream_id'] : null;
        $classId = !empty($row['class_id']) ? (int) $row['class_id'] : null;

        if ($streamId !== null && !empty($scope['stream_ids']) && in_array($streamId, $scope['stream_ids'], true)) {
            return true;
        }

        if ($classId !== null && !empty($scope['class_ids']) && in_array($classId, $scope['class_ids'], true)) {
            return true;
        }

        return false;
    }

    private function resolveCurrentStudentIdsForScope(array $user): array
    {
        $studentIds = [];

        foreach (['student_id', 'linked_student_id'] as $field) {
            if (!empty($user[$field])) {
                $studentIds[] = (int) $user[$field];
            }
        }

        if (!empty($user['student_ids']) && is_array($user['student_ids'])) {
            foreach ($user['student_ids'] as $studentId) {
                if ($studentId) {
                    $studentIds[] = (int) $studentId;
                }
            }
        }

        $studentIds = array_values(array_unique(array_filter($studentIds)));
        if (!empty($studentIds)) {
            return $studentIds;
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username === '') {
            return [];
        }

        $stmt = $this->db->prepare("SELECT id FROM students WHERE admission_no = ? LIMIT 1");
        $stmt->execute([$username]);
        $studentId = $stmt->fetchColumn();

        return $studentId ? [(int) $studentId] : [];
    }

    private function resolveCurrentParentIdsForScope(array $user): array
    {
        $parentIds = [];

        foreach (['parent_id', 'linked_parent_id'] as $field) {
            if (!empty($user[$field])) {
                $parentIds[] = (int) $user[$field];
            }
        }

        if (!empty($user['parent_ids']) && is_array($user['parent_ids'])) {
            foreach ($user['parent_ids'] as $parentId) {
                if ($parentId) {
                    $parentIds[] = (int) $parentId;
                }
            }
        }

        $parentIds = array_values(array_unique(array_filter($parentIds)));
        if (!empty($parentIds)) {
            return $parentIds;
        }

        $conditions = [];
        $bindings = [];

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($email !== '') {
            $conditions[] = 'LOWER(p.email) = ?';
            $bindings[] = $email;
        }

        $phones = [];
        foreach (['phone', 'phone_number', 'mobile', 'telephone'] as $field) {
            $value = trim((string) ($user[$field] ?? ''));
            if ($value !== '') {
                $phones[] = $value;
            }
        }
        $phones = array_values(array_unique(array_filter($phones)));

        foreach ($phones as $phone) {
            $conditions[] = '(p.phone_1 = ? OR p.phone_2 = ?)';
            $bindings[] = $phone;
            $bindings[] = $phone;
        }

        if (empty($conditions)) {
            $firstName = strtolower(trim((string) ($user['first_name'] ?? '')));
            $lastName = strtolower(trim((string) ($user['last_name'] ?? '')));
            if ($firstName !== '' && $lastName !== '') {
                $conditions[] = '(LOWER(p.first_name) = ? AND LOWER(p.last_name) = ?)';
                $bindings[] = $firstName;
                $bindings[] = $lastName;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT DISTINCT p.id
                FROM parents p
                WHERE " . implode(' OR ', array_map(static fn($condition) => "({$condition})", $conditions));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    private function getStudentIdsForParentIds(array $parentIds): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($parentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT DISTINCT sp.student_id
            FROM student_parents sp
            JOIN students s ON s.id = sp.student_id
            WHERE sp.parent_id IN ($placeholders)
              AND s.status = 'active'
        ");
        $stmt->execute($parentIds);

        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));
    }

    private function resolveCurrentStaffIdForScope(array $user): ?int
    {
        $userId = $user['user_id'] ?? $user['id'] ?? null;
        if (!$userId) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id FROM staff WHERE user_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([(int) $userId]);
        $staffId = $stmt->fetchColumn();

        return $staffId ? (int) $staffId : null;
    }

    private function getCurrentAcademicYearIdForScope(): ?int
    {
        $stmt = $this->db->query("
            SELECT id
            FROM academic_years
            WHERE is_current = 1 OR status = 'active'
            ORDER BY is_current DESC, id DESC
            LIMIT 1
        ");
        $yearId = $stmt->fetchColumn();

        return $yearId ? (int) $yearId : null;
    }

    private function resolveClassScopeForStaff(int $staffId, ?int $academicYearId): array
    {
        $scope = [
            'class_ids' => [],
            'stream_ids' => []
        ];

        if ($academicYearId !== null) {
            $stmt = $this->db->prepare("
                SELECT DISTINCT class_id, stream_id
                FROM staff_class_assignments
                WHERE staff_id = ?
                  AND academic_year_id = ?
                  AND status = 'active'
            ");
            $stmt->execute([$staffId, $academicYearId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $row) {
                if (!empty($row['class_id'])) {
                    $scope['class_ids'][] = (int) $row['class_id'];
                }
                if (!empty($row['stream_id'])) {
                    $scope['stream_ids'][] = (int) $row['stream_id'];
                }
            }
        }

        if (empty($scope['stream_ids'])) {
            $streamStmt = $this->db->prepare("
                SELECT DISTINCT id AS stream_id, class_id
                FROM class_streams
                WHERE teacher_id = ?
                  AND status = 'active'
            ");
            $streamStmt->execute([$staffId]);
            $streamRows = $streamStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($streamRows as $row) {
                if (!empty($row['stream_id'])) {
                    $scope['stream_ids'][] = (int) $row['stream_id'];
                }
                if (!empty($row['class_id'])) {
                    $scope['class_ids'][] = (int) $row['class_id'];
                }
            }
        }

        $scope['class_ids'] = array_values(array_unique(array_filter($scope['class_ids'])));
        $scope['stream_ids'] = array_values(array_unique(array_filter($scope['stream_ids'])));

        return $scope;
    }

    private function getCurrentTermId($academicYear = null)
    {
        if ($academicYear !== null) {
            $stmt = $this->db->prepare("
                SELECT id
                FROM academic_terms
                WHERE year = ? AND status = 'current'
                ORDER BY term_number ASC
                LIMIT 1
            ");
            $stmt->execute([$academicYear]);
            $termId = $stmt->fetchColumn();
            if ($termId) {
                return (int) $termId;
            }

            $stmt = $this->db->prepare("
                SELECT id
                FROM academic_terms
                WHERE year = ?
                ORDER BY term_number DESC
                LIMIT 1
            ");
            $stmt->execute([$academicYear]);
            $termId = $stmt->fetchColumn();
            if ($termId) {
                return (int) $termId;
            }
        }

        $stmt = $this->db->query("
            SELECT id
            FROM academic_terms
            WHERE status = 'current'
            ORDER BY year DESC, term_number ASC
            LIMIT 1
        ");
        $termId = $stmt->fetchColumn();
        if ($termId) {
            return (int) $termId;
        }

        $stmt = $this->db->query("
            SELECT id
            FROM academic_terms
            ORDER BY year DESC, term_number DESC
            LIMIT 1
        ");

        $termId = $stmt->fetchColumn();

        return $termId ? (int) $termId : null;
    }

    private function normalizePaymentMethod($method)
    {
        $normalized = strtolower(trim((string) $method));
        $map = [
            'cash' => 'cash',
            'mpesa' => 'mpesa',
            'm-pesa' => 'mpesa',
            'bank' => 'bank_transfer',
            'bank_transfer' => 'bank_transfer',
            'bank transfer' => 'bank_transfer',
            'cheque' => 'cheque',
            'check' => 'cheque',
            'other' => 'other'
        ];

        return $map[$normalized] ?? 'other';
    }

    private function getPrimaryParentId($studentId)
    {
        $stmt = $this->db->prepare("
            SELECT parent_id
            FROM student_parents
            WHERE student_id = ?
            ORDER BY is_primary_contact DESC, is_emergency_contact DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $parentId = $stmt->fetchColumn();

        return $parentId ? (int) $parentId : null;
    }

    private function linkStudentParent($studentId, $parentId, $parentData = [])
    {
        $validRelationships = [
            'father',
            'mother',
            'guardian',
            'step_father',
            'step_mother',
            'grandparent',
            'uncle',
            'aunt',
            'sibling',
            'other'
        ];

        $relationship = $parentData['relationship'] ?? 'guardian';
        if (!in_array($relationship, $validRelationships, true)) {
            $relationship = 'guardian';
        }

        $existingCountStmt = $this->db->prepare("SELECT COUNT(*) FROM student_parents WHERE student_id = ?");
        $existingCountStmt->execute([$studentId]);
        $existingCount = (int) $existingCountStmt->fetchColumn();

        $isPrimary = array_key_exists('is_primary_contact', $parentData)
            ? (int) !empty($parentData['is_primary_contact'])
            : ($existingCount === 0 ? 1 : 0);
        $isEmergency = array_key_exists('is_emergency_contact', $parentData)
            ? (int) !empty($parentData['is_emergency_contact'])
            : $isPrimary;
        $financialResponsibility = isset($parentData['financial_responsibility']) && is_numeric($parentData['financial_responsibility'])
            ? (float) $parentData['financial_responsibility']
            : ($existingCount === 0 ? 100.00 : 0.00);

        $stmt = $this->db->prepare("
            INSERT INTO student_parents (
                student_id,
                parent_id,
                relationship,
                is_primary_contact,
                is_emergency_contact,
                financial_responsibility,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                relationship = VALUES(relationship),
                is_primary_contact = VALUES(is_primary_contact),
                is_emergency_contact = VALUES(is_emergency_contact),
                updated_at = NOW()
        ");
        $stmt->execute([
            $studentId,
            $parentId,
            $relationship,
            $isPrimary,
            $isEmergency,
            $financialResponsibility
        ]);

        if ($isPrimary) {
            $stmt = $this->db->prepare("
                UPDATE student_parents
                SET is_primary_contact = 0, updated_at = NOW()
                WHERE student_id = ? AND parent_id != ?
            ");
            $stmt->execute([$studentId, $parentId]);
        }
    }

    private function getStudentOverviewRecord($id)
    {
        $sql = "
            SELECT
                s.*,
                cs.class_id as class_id,
                c.name as class_name,
                cs.stream_name,
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                st.name AS student_type_name,
                st.name AS student_type,
                st.code AS student_type_code,
                CASE
                    WHEN st.code = 'BOARD' THEN 'boarding'
                    WHEN st.code = 'WEEKLY' THEN 'weekly_boarding'
                    ELSE 'day'
                END AS boarding_status,
                (
                    SELECT CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name)
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                    LIMIT 1
                ) AS parent_name,
                (
                    SELECT p.phone_1
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                    LIMIT 1
                ) AS parent_phone,
                (
                    SELECT p.email
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                    LIMIT 1
                ) AS parent_email,
                (
                    SELECT p.occupation
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                    LIMIT 1
                ) AS parent_occupation,
                (
                    SELECT p.address
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
                    LIMIT 1
                ) AS parent_address
            FROM students s
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            LEFT JOIN classes c ON cs.class_id = c.id
            LEFT JOIN student_types st ON s.student_type_id = st.id
            WHERE s.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function refreshStudentPaymentSummary($studentId, $academicYear, $termId)
    {
        try {
            $stmt = $this->db->prepare("CALL sp_refresh_student_payment_summary(?, ?, ?)");
            $stmt->execute([$studentId, $academicYear, $termId]);
            $stmt->closeCursor();
        } catch (Exception $e) {
            $this->logError($e, "Unable to refresh payment summary for student {$studentId}");
        }
    }

    private function getCurrentAcademicYearRecord(): ?array
    {
        $stmt = $this->db->query("
            SELECT id, year_code, year_name, start_date, end_date
            FROM academic_years
            WHERE is_current = 1 OR status = 'active'
            ORDER BY is_current DESC, start_date DESC, id DESC
            LIMIT 1
        ");

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        return $record ?: null;
    }

    private function extractAcademicYearNumber(?array $academicYearRecord): ?int
    {
        if (!$academicYearRecord) {
            return null;
        }

        $yearCode = (string) ($academicYearRecord['year_code'] ?? '');
        if ($yearCode !== '' && preg_match('/(\d{4})/', $yearCode, $matches)) {
            return (int) $matches[1];
        }

        $startDate = $academicYearRecord['start_date'] ?? null;
        if (!empty($startDate)) {
            return (int) date('Y', strtotime((string) $startDate));
        }

        return null;
    }

    private function mapStudentStatusToEnrollmentStatus(string $studentStatus): string
    {
        $normalized = strtolower(trim($studentStatus));

        switch ($normalized) {
            case 'graduated':
                return 'graduated';
            case 'transferred':
                return 'transferred';
            case 'inactive':
            case 'suspended':
                return 'withdrawn';
            default:
                return 'active';
        }
    }

    private function resolveClassFromStream(int $streamId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cs.id, cs.class_id, cs.stream_name
            FROM class_streams cs
            WHERE cs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$streamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getClassAssignmentId(int $academicYearId, int $classId, int $streamId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM class_year_assignments
            WHERE academic_year_id = ?
              AND class_id = ?
              AND stream_id = ?
              AND status IN ('active', 'planning')
            ORDER BY status = 'active' DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$academicYearId, $classId, $streamId]);
        $assignmentId = $stmt->fetchColumn();

        return $assignmentId ? (int) $assignmentId : null;
    }

    private function ensureClassEnrollment(
        int $studentId,
        int $streamId,
        ?int $academicYearId = null,
        string $studentStatus = 'active',
        ?string $reason = null
    ): ?int {
        $stream = $this->resolveClassFromStream($streamId);
        if (!$stream || empty($stream['class_id'])) {
            throw new Exception('Invalid class stream selected');
        }

        $academicYearRecord = $this->getCurrentAcademicYearRecord();
        if ($academicYearId === null) {
            $academicYearId = (int) ($academicYearRecord['id'] ?? 0);
        }
        if ($academicYearId <= 0) {
            throw new Exception('No active academic year found');
        }

        $classId = (int) $stream['class_id'];
        $assignmentId = $this->getClassAssignmentId($academicYearId, $classId, $streamId);
        $enrollmentStatus = $this->mapStudentStatusToEnrollmentStatus($studentStatus);

        $existingStmt = $this->db->prepare("
            SELECT id, class_id, stream_id, special_notes
            FROM class_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $existingStmt->execute([$studentId, $academicYearId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $note = $reason ? trim($reason) : null;
            $updateStmt = $this->db->prepare("
                UPDATE class_enrollments
                SET class_id = ?,
                    stream_id = ?,
                    class_assignment_id = ?,
                    enrollment_status = ?,
                    special_notes = CASE
                        WHEN ? IS NULL OR ? = '' THEN special_notes
                        WHEN special_notes IS NULL OR special_notes = '' THEN ?
                        ELSE CONCAT(special_notes, '\n', ?)
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $classId,
                $streamId,
                $assignmentId,
                $enrollmentStatus,
                $note,
                $note,
                $note,
                $note,
                (int) $existing['id']
            ]);

            return (int) $existing['id'];
        }

        $admissionDateStmt = $this->db->prepare("SELECT admission_date FROM students WHERE id = ? LIMIT 1");
        $admissionDateStmt->execute([$studentId]);
        $enrollmentDate = $admissionDateStmt->fetchColumn() ?: date('Y-m-d');
        $promotionStatus = $enrollmentStatus === 'graduated'
            ? 'graduated'
            : ($enrollmentStatus === 'transferred' ? 'transferred' : 'pending');

        $insertStmt = $this->db->prepare("
            INSERT INTO class_enrollments (
                student_id,
                academic_year_id,
                class_id,
                stream_id,
                class_assignment_id,
                enrollment_date,
                enrollment_status,
                promotion_status,
                special_notes,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $insertStmt->execute([
            $studentId,
            $academicYearId,
            $classId,
            $streamId,
            $assignmentId,
            $enrollmentDate,
            $enrollmentStatus,
            $promotionStatus,
            $reason
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function generateStudentFeeObligationsForCurrentYear(int $studentId, ?int $academicYearId = null): int
    {
        $academicYearRecord = null;

        if ($academicYearId !== null) {
            $stmt = $this->db->prepare("
                SELECT id, year_code, year_name, start_date, end_date
                FROM academic_years
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$academicYearId]);
            $academicYearRecord = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $academicYearRecord = $this->getCurrentAcademicYearRecord();
            $academicYearId = (int) ($academicYearRecord['id'] ?? 0);
        }

        if (!$academicYearRecord || !$academicYearId) {
            return 0;
        }

        $academicYear = $this->extractAcademicYearNumber($academicYearRecord);
        if ($academicYear === null) {
            return 0;
        }

        $studentStmt = $this->db->prepare("
            SELECT s.student_type_id, s.is_sponsored, s.sponsor_waiver_percentage,
                   c.level_id AS level_id
            FROM students s
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $studentStmt->execute([$studentId]);
        $studentMeta = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentMeta || empty($studentMeta['student_type_id']) || empty($studentMeta['level_id'])) {
            return 0;
        }

        $structureStmt = $this->db->prepare("
            SELECT id, term_id, amount, due_date
            FROM fee_structures_detailed
            WHERE level_id = ?
              AND academic_year = ?
              AND student_type_id = ?
              AND status IN ('approved', 'active')
            ORDER BY term_id ASC, id ASC
        ");
        $structureStmt->execute([
            (int) $studentMeta['level_id'],
            $academicYear,
            (int) $studentMeta['student_type_id']
        ]);
        $feeStructures = $structureStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($feeStructures)) {
            return 0;
        }

        $isSponsored = (int) ($studentMeta['is_sponsored'] ?? 0) === 1;
        $waiverPercent = (float) ($studentMeta['sponsor_waiver_percentage'] ?? 0);
        $createdCount = 0;

        foreach ($feeStructures as $row) {
            $existsStmt = $this->db->prepare("
                SELECT id
                FROM student_fee_obligations
                WHERE student_id = ?
                  AND fee_structure_detail_id = ?
                  AND academic_year = ?
                LIMIT 1
            ");
            $existsStmt->execute([
                $studentId,
                (int) $row['id'],
                $academicYear
            ]);

            if ($existsStmt->fetchColumn()) {
                continue;
            }

            $amountDue = (float) $row['amount'];
            $waivedAmount = $isSponsored && $waiverPercent > 0
                ? round($amountDue * ($waiverPercent / 100), 2)
                : 0.0;
            $waivedAmount = min($waivedAmount, $amountDue);
            $netBalance = max(0, $amountDue - $waivedAmount);
            $status = $netBalance <= 0 ? 'paid' : 'pending';
            $paymentStatus = $netBalance <= 0 ? 'waived' : 'pending';
            $dueDate = !empty($row['due_date']) ? $row['due_date'] : date('Y-m-d', strtotime('+30 days'));

            $insertStmt = $this->db->prepare("
                INSERT INTO student_fee_obligations (
                    student_id,
                    academic_year,
                    term_id,
                    fee_structure_detail_id,
                    amount_due,
                    amount_paid,
                    amount_waived,
                    status,
                    due_date,
                    year_balance,
                    term_balance,
                    previous_year_balance,
                    previous_term_balance,
                    is_sponsored,
                    sponsored_waiver_amount,
                    payment_status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?, NOW(), NOW())
            ");
            $insertStmt->execute([
                $studentId,
                $academicYear,
                (int) $row['term_id'],
                (int) $row['id'],
                $amountDue,
                $waivedAmount,
                $status,
                $dueDate,
                $netBalance,
                $netBalance,
                $isSponsored ? 1 : 0,
                $waivedAmount,
                $paymentStatus
            ]);
            $createdCount++;
        }

        return $createdCount;
    }

    private function recordInternalClassTransferAudit(
        int $studentId,
        int $fromStreamId,
        int $toStreamId,
        ?string $reason = null
    ): ?int {
        $from = $this->resolveClassFromStream($fromStreamId);
        $to = $this->resolveClassFromStream($toStreamId);
        $academicYearRecord = $this->getCurrentAcademicYearRecord();
        $academicYearId = (int) ($academicYearRecord['id'] ?? 0);
        $academicYear = $this->extractAcademicYearNumber($academicYearRecord);
        $termId = $this->getCurrentTermId($academicYear);

        if (!$from || !$to || !$academicYearId || !$academicYear || !$termId) {
            return null;
        }

        $enrollmentStmt = $this->db->prepare("
            SELECT id
            FROM class_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $enrollmentStmt->execute([$studentId, $academicYearId]);
        $enrollmentId = $enrollmentStmt->fetchColumn();

        $note = $reason ?: 'Internal class/stream transfer';
        $userId = $this->getCurrentUserId();

        $sql = "
            INSERT INTO student_promotions (
                batch_id,
                from_enrollment_id,
                to_enrollment_id,
                from_academic_year_id,
                to_academic_year_id,
                student_id,
                current_class_id,
                current_stream_id,
                promoted_to_class_id,
                promoted_to_stream_id,
                from_academic_year,
                to_academic_year,
                from_term_id,
                promotion_status,
                promotion_reason,
                approved_by,
                approval_date,
                approval_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'transferred', ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                current_class_id = VALUES(current_class_id),
                current_stream_id = VALUES(current_stream_id),
                promoted_to_class_id = VALUES(promoted_to_class_id),
                promoted_to_stream_id = VALUES(promoted_to_stream_id),
                promotion_status = 'transferred',
                promotion_reason = VALUES(promotion_reason),
                approved_by = VALUES(approved_by),
                approval_date = NOW(),
                approval_notes = VALUES(approval_notes),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            0,
            $enrollmentId ?: null,
            $enrollmentId ?: null,
            $academicYearId,
            $academicYearId,
            $studentId,
            (int) $from['class_id'],
            $fromStreamId,
            (int) $to['class_id'],
            $toStreamId,
            $academicYear,
            $academicYear,
            $termId,
            $note,
            $userId,
            $note
        ]);

        if ($this->db->lastInsertId()) {
            return (int) $this->db->lastInsertId();
        }

        $lookupStmt = $this->db->prepare("
            SELECT id
            FROM student_promotions
            WHERE student_id = ? AND from_academic_year = ? AND to_academic_year = ?
            LIMIT 1
        ");
        $lookupStmt->execute([$studentId, $academicYear, $academicYear]);
        $id = $lookupStmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    // Create new student
    public function create($data)
    {
        try {
            $required = ['admission_no', 'first_name', 'last_name', 'stream_id', 'date_of_birth', 'gender', 'admission_date', 'parent_info'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // parent_info must include either a parent_id or basic contact info
            $parentInfo = $data['parent_info'] ?? [];
            $hasParentId = !empty($parentInfo['parent_id']);
            if (
                !$hasParentId &&
                (empty($parentInfo['first_name']) ||
                    (empty($parentInfo['phone_1']) && empty($parentInfo['email'])))
            ) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Parent information must include parent_id or parent first name and either phone_1 or email'
                ], 400);
            }

            // Validate gender enum
            $validGenders = ['male', 'female', 'other'];
            if (!in_array($data['gender'], $validGenders)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            // Validate status enum if provided
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'graduated', 'transferred', 'suspended'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: active, inactive, graduated, transferred, or suspended'
                ], 400);
            }

            // BUSINESS RULE: Student must be either sponsored OR have initial payment
            // If not sponsored and no initial_payment_amount provided, reject
            $isSponsored = !empty($data['is_sponsored']) && $data['is_sponsored'] == 1;
            $initialPayment = $data['initial_payment_amount'] ?? 0;
            $skipPaymentCheck = $data['skip_payment_check'] ?? false; // Admin override flag

            if (!$isSponsored && $initialPayment <= 0 && !$skipPaymentCheck) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student cannot be enrolled without payment. Either mark as sponsored or provide initial payment amount.',
                    'hint' => 'Use is_sponsored=1 for sponsored students, or provide initial_payment_amount with payment details'
                ], 400);
            }

            // Start transaction so parent linking and student insert are atomic
            $this->db->beginTransaction();

            $sql = "
                INSERT INTO students (
                    admission_no,
                    first_name,
                    middle_name,
                    last_name,
                    date_of_birth,
                    gender,
                    stream_id,
                    student_type_id,
                    admission_date,
                    assessment_number,
                    assessment_status,
                    nemis_number,
                    nemis_status,
                    status,
                    photo_url,
                    qr_code_path,
                    is_sponsored,
                    sponsor_name,
                    sponsor_type,
                    sponsor_waiver_percentage,
                    blood_group
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['admission_no'],
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['stream_id'],
                $data['student_type_id'] ?? null,
                $data['admission_date'],
                $data['assessment_number'] ?? null,
                $data['assessment_status'] ?? 'not_assigned',
                $data['nemis_number'] ?? null,
                $data['nemis_status'] ?? 'not_assigned',
                $data['status'] ?? 'active',
                $data['photo_url'] ?? null,
                $data['qr_code_path'] ?? null,
                $data['is_sponsored'] ?? 0,
                $data['sponsor_name'] ?? null,
                $data['sponsor_type'] ?? null,
                $data['sponsor_waiver_percentage'] ?? null,
                $data['blood_group'] ?? null
            ]);

            $studentId = $this->db->lastInsertId();

            // Link parent as part of student creation
            try {
                $this->addParent($studentId, $data['parent_info']);
            } catch (Exception $e) {
                // If parent creation/link fails, rollback student and return error
                $this->db->rollBack();
                return $this->handleException($e);
            }

            // Create class enrollment and fee obligations
            $enrollmentId = null;
            $feeObligationsCreated = 0;

            if (!empty($data['stream_id'])) {
                try {
                    $enrollmentId = $this->ensureClassEnrollment(
                        (int) $studentId,
                        (int) $data['stream_id'],
                        null,
                        (string) ($data['status'] ?? 'active'),
                        'Initial enrollment'
                    );
                    $feeObligationsCreated = $this->generateStudentFeeObligationsForCurrentYear((int) $studentId);
                } catch (Exception $e) {
                    // Log but don't fail - enrollment and fees can be created later.
                    error_log("Warning: Could not create enrollment/fees for student $studentId: " . $e->getMessage());
                }
            }

            // Record initial payment if provided
            if ($initialPayment > 0 && !empty($data['payment_method'])) {
                try {
                    $this->recordInitialPayment($studentId, [
                        'amount' => $initialPayment,
                        'method' => $data['payment_method'],
                        'reference' => $data['payment_reference'] ?? null,
                        'receipt_no' => $data['receipt_no'] ?? null
                    ]);
                } catch (Exception $e) {
                    error_log("Warning: Could not record initial payment for student $studentId: " . $e->getMessage());
                }
            }

            $this->logAction('create', $studentId, "Created new student: {$data['first_name']} {$data['last_name']}");

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => [
                    'id' => $studentId,
                    'enrollment_id' => $enrollmentId,
                    'fee_obligations_created' => $feeObligationsCreated
                ]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Record initial payment for a newly enrolled student
     */
    private function recordInitialPayment($studentId, $paymentData)
    {
        $academicYear = $this->getCurrentAcademicYearValue();
        $termId = $this->getCurrentTermId($academicYear);
        $parentId = $this->getPrimaryParentId($studentId);
        $receivedBy = $paymentData['received_by'] ?? $this->getCurrentUserId();
        $paymentMethod = $this->normalizePaymentMethod($paymentData['method'] ?? '');
        $paymentDate = $paymentData['payment_date'] ?? date('Y-m-d H:i:s');
        $receiptNo = $paymentData['receipt_no'] ?? ('ADM-' . date('YmdHis') . '-' . $studentId);

        $sql = "INSERT INTO payment_transactions (
            student_id, parent_id, academic_year, term_id, amount_paid,
            payment_date, payment_method, reference_no, receipt_no,
            received_by, status, notes, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $studentId,
            $parentId,
            $academicYear,
            $termId,
            $paymentData['amount'],
            $paymentDate,
            $paymentMethod,
            $paymentData['reference'] ?? null,
            $receiptNo,
            $receivedBy,
            $paymentData['notes'] ?? 'Initial admission payment'
        ]);

        $paymentId = $this->db->lastInsertId();

        $remainingAmount = $paymentData['amount'];

        $stmt = $this->db->prepare("
            SELECT id, balance
            FROM student_fee_obligations 
            WHERE student_id = ?
                AND balance > 0
                AND status IN ('pending', 'partial', 'arrears')
            ORDER BY due_date ASC
        ");
        $stmt->execute([$studentId]);
        $obligations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($obligations as $obligation) {
            if ($remainingAmount <= 0) {
                break;
            }

            $paymentForThis = min($remainingAmount, $obligation['balance']);

            $stmt = $this->db->prepare("
                UPDATE student_fee_obligations 
                SET amount_paid = amount_paid + ?,
                    status = CASE
                        WHEN (amount_paid + ? + amount_waived) >= amount_due THEN 'paid'
                        WHEN (amount_paid + ? + amount_waived) > 0 THEN 'partial'
                        ELSE 'pending'
                    END,
                    payment_status = CASE 
                        WHEN (amount_paid + ? + amount_waived) >= amount_due THEN 'paid'
                        WHEN (amount_paid + ? + amount_waived) > 0 THEN 'partial'
                        ELSE 'pending'
                    END,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $paymentForThis,
                $paymentForThis,
                $paymentForThis,
                $paymentForThis,
                $paymentForThis,
                $paymentForThis,
                $obligation['id']
            ]);

            $stmt = $this->db->prepare("
                INSERT INTO payment_allocations_detailed (
                    payment_transaction_id,
                    student_fee_obligation_id,
                    amount_allocated,
                    allocated_by,
                    notes
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $paymentId,
                $obligation['id'],
                $paymentForThis,
                $receivedBy,
                $paymentData['notes'] ?? 'Initial admission payment allocation'
            ]);

            $remainingAmount -= $paymentForThis;
        }

        if ($academicYear !== null && $termId !== null) {
            $this->refreshStudentPaymentSummary($studentId, $academicYear, $termId);
        }

        return $paymentId;
    }

    // Update student
    public function update($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, stream_id, status FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existingStudent) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Validate gender enum if provided
            if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            // Validate status enum if provided
            if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'graduated', 'transferred', 'suspended'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: active, inactive, graduated, transferred, or suspended'
                ], 400);
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'admission_no',
                'first_name',
                'middle_name',
                'last_name',
                'date_of_birth',
                'gender',
                'stream_id',
                'student_type_id',
                'admission_date',
                'status',
                'photo_url',
                'assessment_number',
                'assessment_status',
                'nemis_number',
                'nemis_status',
                'is_sponsored',
                'sponsor_name',
                'sponsor_type',
                'sponsor_waiver_percentage',
                'blood_group'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $currentStreamId = (int) ($existingStudent['stream_id'] ?? 0);
            $nextStreamId = isset($data['stream_id']) ? (int) $data['stream_id'] : $currentStreamId;
            $currentStatus = (string) ($existingStudent['status'] ?? 'active');
            $nextStatus = (string) ($data['status'] ?? $currentStatus);

            if ($nextStreamId > 0 && ($nextStreamId !== $currentStreamId || $nextStatus !== $currentStatus)) {
                $reason = null;
                if ($nextStreamId !== $currentStreamId) {
                    $reason = $data['transfer_reason']
                        ?? $data['reason']
                        ?? 'Updated class/stream assignment';
                }

                $this->ensureClassEnrollment(
                    (int) $id,
                    $nextStreamId,
                    null,
                    $nextStatus,
                    $reason
                );

                if ($nextStreamId !== $currentStreamId && $currentStreamId > 0) {
                    $this->recordInternalClassTransferAudit(
                        (int) $id,
                        $currentStreamId,
                        $nextStreamId,
                        $reason
                    );
                }
            }

            $this->logAction('update', $id, "Updated student details");

            return $this->response([
                'status' => 'success',
                'message' => 'Student updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete student (soft delete)
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE students SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $this->logAction('delete', $id, "Deactivated student");

            return $this->response([
                'status' => 'success',
                'message' => 'Student deactivated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params)
    {
        switch ($action) {
            case 'attendance':
                return $this->getAttendanceRecord($id, $params);
            case 'performance':
                return $this->getAcademicPerformance($id, $params);
            case 'fees':
                return $this->getFeeStatement($id);
            case 'report':
                return $this->generateTermReport($id, $params);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data)
    {
        switch ($action) {
            case 'attendance':
                return $this->markAttendance($id, $data);
            case 'transfer':
                return $this->transferStudent($id, $data);
            case 'discipline':
                return $this->recordDisciplineCase($id, $data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Helper methods
    private function generateAdmissionNumber()
    {
        $year = date('Y');
        $stmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as next_number 
            FROM students 
            WHERE admission_no LIKE ?
        ");
        $stmt->execute(["{$year}%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNumber = str_pad($result['next_number'], 4, '0', STR_PAD_LEFT);
        return "{$year}{$nextNumber}";
    }

    private function addParent($studentId, $parentData)
    {
        if (!empty($parentData['parent_id'])) {
            $parentId = (int) $parentData['parent_id'];
            $stmt = $this->db->prepare("SELECT id FROM parents WHERE id = ? LIMIT 1");
            $stmt->execute([$parentId]);
            if (!$stmt->fetch()) {
                throw new Exception('Parent not found for provided parent_id');
            }

            $sql = "SELECT id FROM student_parents WHERE student_id = ? AND parent_id = ? LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId, $parentId]);
            if (!$stmt->fetch()) {
                $this->linkStudentParent($studentId, $parentId, $parentData);
            }
            return;
        }

        // Validate gender if provided
        if (isset($parentData['gender']) && !in_array($parentData['gender'], ['male', 'female', 'other'])) {
            throw new Exception('Invalid gender value. Must be: male, female, or other');
        }

        // Robust parent lookup: check by phone_1, phone_2, email, or name+phone
        $stmt = $this->db->prepare("SELECT id, phone_1, phone_2, email, first_name, last_name FROM parents WHERE phone_1 = ? OR phone_2 = ? OR (email IS NOT NULL AND email = ?) LIMIT 1");
        $stmt->execute([$parentData['phone_1'] ?? null, $parentData['phone_2'] ?? null, $parentData['email'] ?? null]);
        $existingParent = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingParent) {
            $parentId = $existingParent['id'];
            // Update missing info if provided
            $updates = [];
            $params = [];
            if (!empty($parentData['first_name']) && $parentData['first_name'] !== $existingParent['first_name']) {
                $updates[] = 'first_name = ?';
                $params[] = $parentData['first_name'];
            }
            if (!empty($parentData['last_name']) && $parentData['last_name'] !== $existingParent['last_name']) {
                $updates[] = 'last_name = ?';
                $params[] = $parentData['last_name'];
            }
            if (!empty($parentData['phone_1']) && $parentData['phone_1'] !== $existingParent['phone_1']) {
                $updates[] = 'phone_1 = ?';
                $params[] = $parentData['phone_1'];
            }
            if (!empty($parentData['phone_2']) && $parentData['phone_2'] !== $existingParent['phone_2']) {
                $updates[] = 'phone_2 = ?';
                $params[] = $parentData['phone_2'];
            }
            if (!empty($parentData['email']) && $parentData['email'] !== $existingParent['email']) {
                $updates[] = 'email = ?';
                $params[] = $parentData['email'];
            }
            if (!empty($updates)) {
                $params[] = $existingParent['id'];
                $sql = 'UPDATE parents SET ' . implode(', ', $updates) . ' WHERE id = ?';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
        } else {
            // Create new parent
            $sql = "INSERT INTO parents (first_name, last_name, gender, phone_1, phone_2, email, occupation, address, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $parentData['first_name'],
                $parentData['last_name'],
                $parentData['gender'] ?? 'other',
                $parentData['phone_1'] ?? null,
                $parentData['phone_2'] ?? null,
                $parentData['email'] ?? null,
                $parentData['occupation'] ?? null,
                $parentData['address'] ?? null
            ]);
            $parentId = $this->db->lastInsertId();
        }

        // Create student-parent relationship if not exists
        $this->linkStudentParent($studentId, $parentId, $parentData);
    }

    private function getStudentParents($studentId)
    {
        $sql = "
            SELECT 
                sp.id as student_parent_id,
                sp.student_id,
                sp.parent_id,
                sp.relationship,
                sp.is_primary_contact,
                sp.is_emergency_contact,
                sp.financial_responsibility,
                p.first_name,
                p.middle_name,
                p.last_name,
                CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) as full_name,
                p.gender,
                p.date_of_birth,
                p.id_number,
                p.phone_1,
                p.phone_2,
                p.phone_1 as phone,
                p.phone_1 as phone1,
                p.phone_2 as phone2,
                p.email,
                p.occupation,
                p.address,
                p.status,
                p.created_at,
                p.updated_at
            FROM parents p
            JOIN student_parents sp ON p.id = sp.parent_id
            WHERE sp.student_id = ?
            ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeeSummary($studentId)
    {
        $academicYear = $this->getCurrentAcademicYearValue();
        $obligationSql = "
            SELECT
                COALESCE(SUM(amount_due), 0) AS total_fees,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(amount_waived), 0) AS total_waived,
                COALESCE(SUM(balance), 0) AS balance,
                MIN(CASE WHEN balance > 0 THEN due_date END) AS earliest_due_date
            FROM student_fee_obligations
            WHERE student_id = ?
        ";
        $obligationBindings = [$studentId];

        if ($academicYear !== null) {
            $obligationSql .= " AND academic_year = ?";
            $obligationBindings[] = $academicYear;
        }

        $stmt = $this->db->prepare($obligationSql);
        $stmt->execute($obligationBindings);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $paymentSql = "
            SELECT
                MAX(payment_date) AS last_payment_date,
                COUNT(*) AS number_of_payments
            FROM payment_transactions
            WHERE student_id = ?
                AND status = 'confirmed'
        ";
        $paymentBindings = [$studentId];

        if ($academicYear !== null) {
            $paymentSql .= " AND academic_year = ?";
            $paymentBindings[] = $academicYear;
        }

        $stmt = $this->db->prepare($paymentSql);
        $stmt->execute($paymentBindings);
        $paymentMeta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $totalFees = (float) ($summary['total_fees'] ?? 0);
        $totalPaid = (float) ($summary['total_paid'] ?? 0);
        $balance = (float) ($summary['balance'] ?? 0);

        return [
            'academic_year' => $academicYear,
            'total_fees' => $summary['total_fees'] ?? 0,
            'total_paid' => $summary['total_paid'] ?? 0,
            'total_waived' => $summary['total_waived'] ?? 0,
            'balance' => $summary['balance'] ?? 0,
            'payment_percentage' => $totalFees > 0 ? round(($totalPaid / $totalFees) * 100, 2) : 0,
            'payment_status' => $balance <= 0 && $totalFees > 0
                ? 'paid'
                : ($totalPaid > 0 ? 'partial' : 'pending'),
            'last_payment_date' => $paymentMeta['last_payment_date'] ?? null,
            'number_of_payments' => $paymentMeta['number_of_payments'] ?? 0,
            'arrears_status' => ($balance > 0 && !empty($summary['earliest_due_date']) && $summary['earliest_due_date'] < date('Y-m-d'))
                ? 'overdue'
                : 'current'
        ];
    }

    private function getAttendanceSummary($studentId)
    {
        $sql = "
            SELECT 
                MONTH(date) as month,
                YEAR(date) as year,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
            FROM student_attendance
            WHERE student_id = ?
            GROUP BY YEAR(date), MONTH(date)
            ORDER BY year DESC, month DESC
            LIMIT 12
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAttendanceRecord($id, $params)
    {
        $termId = $params['term_id'] ?? $params['term'] ?? null;
        $academicYear = $params['academic_year'] ?? null;

        // Compatibility: legacy callers pass year/month without academic_year
        if ($academicYear === null && isset($params['year']) && !isset($params['month'])) {
            $academicYear = $params['year'];
        }

        $month = $params['month'] ?? null;
        $calendarYear = $params['calendar_year'] ?? null;
        if ($calendarYear === null && $academicYear === null && isset($params['year'])) {
            $calendarYear = $params['year'];
        }

        $dateFrom = $params['date_from'] ?? null;
        $dateTo = $params['date_to'] ?? null;

        $where = ['sa.student_id = ?'];
        $bindings = [$id];

        if (!empty($termId) && ctype_digit((string) $termId)) {
            $where[] = 'sa.term_id = ?';
            $bindings[] = (int) $termId;
        }

        if (!empty($academicYear)) {
            $where[] = '(at.year = ? OR YEAR(sa.date) = ?)';
            $bindings[] = (int) $academicYear;
            $bindings[] = (int) $academicYear;
        }

        if (!empty($month) && ctype_digit((string) $month)) {
            $where[] = 'MONTH(sa.date) = ?';
            $bindings[] = (int) $month;
        }

        if (!empty($calendarYear) && ctype_digit((string) $calendarYear)) {
            $where[] = 'YEAR(sa.date) = ?';
            $bindings[] = (int) $calendarYear;
        }

        if (!empty($dateFrom)) {
            $where[] = 'sa.date >= ?';
            $bindings[] = $dateFrom;
        }

        if (!empty($dateTo)) {
            $where[] = 'sa.date <= ?';
            $bindings[] = $dateTo;
        }

        // Default filter keeps profile cards lightweight when no explicit scope is provided.
        if (
            empty($academicYear)
            && empty($termId)
            && empty($dateFrom)
            && empty($dateTo)
            && empty($month)
            && empty($calendarYear)
        ) {
            $where[] = 'YEAR(sa.date) = ?';
            $bindings[] = (int) date('Y');
            $where[] = 'MONTH(sa.date) = ?';
            $bindings[] = (int) date('m');
        }

        $sql = "
            SELECT
                sa.id,
                sa.student_id,
                sa.date,
                sa.status,
                sa.check_in_time,
                sa.check_out_time,
                sa.absence_reason,
                sa.notes,
                sa.class_id,
                sa.term_id,
                sa.session_id,
                sa.marked_by,
                at.name AS term_name,
                at.term_number,
                at.year AS academic_year,
                ats.name AS session_name,
                ats.session_type
            FROM student_attendance sa
            LEFT JOIN academic_terms at ON sa.term_id = at.id
            LEFT JOIN attendance_sessions ats ON sa.session_id = ats.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY sa.date DESC, sa.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildAttendanceSummary(array $records): array
    {
        $total = count($records);
        $present = 0;
        $absent = 0;
        $late = 0;

        foreach ($records as $record) {
            $status = strtolower((string) ($record['status'] ?? ''));
            if ($status === 'present') {
                $present++;
            } elseif ($status === 'late') {
                $late++;
            } elseif ($status === 'absent') {
                $absent++;
            }
        }

        $attendanceRate = $total > 0
            ? number_format(($present / $total) * 100, 2, '.', '')
            : '0.00';

        return [
            'total' => $total,
            'total_days' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'attendance_rate' => $attendanceRate
        ];
    }

    private function getAcademicPerformance($id, $params)
    {
        $termId = $params['term_id'] ?? $params['term'] ?? null;
        $academicYear = $params['academic_year'] ?? $params['year'] ?? null;

        // Primary source: consolidated term scores (schema-aligned)
        $where = ['tss.student_id = ?'];
        $bindings = [$id];

        if (!empty($termId) && ctype_digit((string) $termId)) {
            $where[] = 'tss.term_id = ?';
            $bindings[] = (int) $termId;
        }

        if (!empty($academicYear)) {
            $where[] = 'at.year = ?';
            $bindings[] = (int) $academicYear;
        }

        $sql = "
            SELECT
                tss.id,
                tss.student_id,
                tss.term_id,
                at.name AS term_name,
                at.term_number,
                at.year AS academic_year,
                tss.subject_id,
                COALESCE(la.name, cu.name, CONCAT('Subject #', tss.subject_id)) AS subject_name,
                tss.formative_total,
                tss.formative_max,
                tss.formative_percentage,
                tss.formative_grade,
                tss.summative_total,
                tss.summative_max,
                tss.summative_percentage,
                tss.summative_grade,
                tss.overall_score,
                tss.overall_percentage,
                tss.overall_grade,
                tss.overall_points,
                tss.assessment_count,
                tss.calculated_at
            FROM term_subject_scores tss
            LEFT JOIN academic_terms at ON tss.term_id = at.id
            LEFT JOIN learning_areas la ON tss.subject_id = la.id
            LEFT JOIN curriculum_units cu ON tss.subject_id = cu.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY at.year DESC, at.term_number DESC, subject_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            return $rows;
        }

        // Fallback source: raw assessment results where rollups are missing
        $fallbackWhere = ['ar.student_id = ?'];
        $fallbackBindings = [$id];

        if (!empty($termId) && ctype_digit((string) $termId)) {
            $fallbackWhere[] = 'a.term_id = ?';
            $fallbackBindings[] = (int) $termId;
        }
        if (!empty($academicYear)) {
            $fallbackWhere[] = 'at.year = ?';
            $fallbackBindings[] = (int) $academicYear;
        }

        $fallbackSql = "
            SELECT
                ar.id AS result_id,
                ar.student_id,
                ar.assessment_id,
                ar.marks_obtained,
                ar.grade,
                ar.points,
                ar.remarks,
                ar.is_submitted,
                ar.submitted_at,
                a.title AS assessment_title,
                a.max_marks,
                a.assessment_date,
                a.term_id,
                at.name AS term_name,
                at.term_number,
                at.year AS academic_year,
                a.subject_id,
                COALESCE(la.name, cu.name, CONCAT('Subject #', a.subject_id)) AS subject_name
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            LEFT JOIN academic_terms at ON a.term_id = at.id
            LEFT JOIN learning_areas la ON a.subject_id = la.id
            LEFT JOIN curriculum_units cu ON a.subject_id = cu.id
            WHERE " . implode(' AND ', $fallbackWhere) . "
            ORDER BY at.year DESC, at.term_number DESC, a.assessment_date DESC, subject_name ASC
        ";

        $fallbackStmt = $this->db->prepare($fallbackSql);
        $fallbackStmt->execute($fallbackBindings);
        return $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeePayments($studentId)
    {
        $sql = "
            SELECT
                pt.id,
                pt.student_id,
                pt.academic_year,
                pt.term_id,
                at.name AS term_name,
                at.term_number,
                pt.amount_paid AS amount,
                pt.amount_paid,
                pt.payment_date,
                pt.payment_method,
                pt.reference_no,
                COALESCE(pt.reference_no, pt.receipt_no) AS reference,
                pt.receipt_no,
                pt.status,
                pt.notes
            FROM payment_transactions pt
            LEFT JOIN academic_terms at ON pt.term_id = at.id
            WHERE pt.student_id = ?
                AND pt.status = 'confirmed'
            ORDER BY pt.payment_date DESC, pt.id DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeeObligations($studentId)
    {
        $academicYear = $this->getCurrentAcademicYearValue();
        $sql = "
            SELECT
                sfo.id,
                sfo.student_id,
                sfo.academic_year,
                sfo.term_id,
                at.name AS term_name,
                at.term_number,
                sfo.fee_structure_detail_id,
                ft.name AS fee_type,
                sfo.amount_due,
                sfo.amount_paid,
                sfo.amount_waived,
                sfo.balance,
                sfo.status,
                sfo.payment_status,
                sfo.due_date
            FROM student_fee_obligations sfo
            LEFT JOIN academic_terms at ON sfo.term_id = at.id
            LEFT JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
            LEFT JOIN fee_types ft ON fsd.fee_type_id = ft.id
            WHERE sfo.student_id = ?
        ";
        $bindings = [$studentId];

        if ($academicYear !== null) {
            $sql .= " AND sfo.academic_year = ?";
            $bindings[] = $academicYear;
        }

        $sql .= " ORDER BY at.term_number ASC, ft.name ASC, sfo.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeeStatement($id)
    {
        try {
            return $this->response([
                'status' => 'success',
                'data' => $this->getFeePayments($id)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function generateTermReport($id, $params)
    {
        try {
            $term = isset($params['term']) ? (int) $params['term'] : (defined('CURRENT_TERM') ? (int) CURRENT_TERM : 1);
            $year = isset($params['year']) ? (int) $params['year'] : (defined('CURRENT_YEAR') ? (int) CURRENT_YEAR : (int) date('Y'));

            // Get student details
            $stmt = $this->db->prepare("SELECT * FROM view_student_details WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Get academic performance (schema-aligned)
            $results = $this->getAcademicPerformance($id, [
                'term' => $term,
                'year' => $year
            ]);

            // Get attendance summary
            $sql = "
                SELECT 
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days
                FROM student_attendance
                WHERE student_id = ? AND YEAR(date) = ? AND MONTH(date) BETWEEN ? AND ?
            ";

            $termMonths = [
                1 => [9, 10, 11],
                2 => [1, 2, 3],
                3 => [5, 6, 7]
            ];

            $termNumber = $term;
            if ($termNumber > 3) {
                $termStmt = $this->db->prepare("SELECT term_number FROM academic_terms WHERE id = ? LIMIT 1");
                $termStmt->execute([$termNumber]);
                $resolved = $termStmt->fetch(PDO::FETCH_ASSOC);
                if (!empty($resolved['term_number'])) {
                    $termNumber = (int) $resolved['term_number'];
                }
            }
            $selectedMonths = $termMonths[$termNumber] ?? [1, 12];

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $year,
                min($selectedMonths),
                max($selectedMonths)
            ]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get class teacher comments
            $sql = "
                SELECT 
                    comments,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name
                FROM term_reports tr
                JOIN staff s ON tr.class_teacher_id = s.id
                WHERE tr.student_id = ? AND tr.term = ? AND tr.year = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $term, $year]);
            $comments = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'student' => $student,
                    'academic_results' => $results,
                    'attendance' => $attendance,
                    'comments' => $comments
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function markAttendance($id, $data)
    {
        try {
            // Validate required fields
            $required = ['date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO student_attendance (student_id, date, status, notes)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE status = VALUES(status), notes = VALUES(notes)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['date'],
                $data['status'],
                $data['notes'] ?? $data['remarks'] ?? null
            ]);

            $this->logAction('create', null, "Marked attendance for student ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance marked successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function transferStudent($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['new_stream_id', 'transfer_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Record transfer history
            $sql = "
                INSERT INTO student_transfers (
                    student_id, 
                    from_stream_id, 
                    to_stream_id, 
                    transfer_date, 
                    reason, 
                    approved_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            // Get current stream
            $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $currentStream = $stmt->fetchColumn();

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $currentStream,
                $data['new_stream_id'],
                $data['transfer_date'],
                $data['reason'],
                $this->user_id
            ]);

            // Update student's stream
            $stmt = $this->db->prepare("UPDATE students SET stream_id = ? WHERE id = ?");
            $stmt->execute([$data['new_stream_id'], $id]);

            $this->db->commit();
            $this->logAction('update', $id, "Transferred student to new stream");

            return $this->response([
                'status' => 'success',
                'message' => 'Student transferred successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function recordDisciplineCase($id, $data)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['incident_date', 'description', 'severity'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate severity enum
            $validSeverity = ['low', 'medium', 'high'];
            if (!in_array($data['severity'], $validSeverity)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid severity value. Must be: low, medium, or high'
                ], 400);
            }

            $status = $data['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'resolved', 'escalated'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: pending, resolved, or escalated'
                ], 400);
            }

            $sql = "
                INSERT INTO student_discipline (
                    student_id,
                    incident_date,
                    description,
                    severity,
                    action_taken,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['incident_date'],
                $data['description'],
                $data['severity'],
                $data['action_taken'] ?? null,
                $status
            ]);

            $caseId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $caseId, "Recorded discipline case for student ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case recorded successfully',
                'data' => ['id' => $caseId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    //generate unique QR code for student - should also conatin fees balance and transportation information
    public function generateQRCode($id)
    {
        try {
            // Get student details
            $stmt = $this->db->prepare("SELECT admission_no FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student not found'
                ], 404);
            }

            // Ensure QR code library is available
            if (!class_exists('\Endroid\QrCode\QrCode') || !class_exists('\Endroid\QrCode\Writer\PngWriter')) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'QR generation library not available. Please install "endroid/qr-code" via Composer (composer require endroid/qr-code).'
                ], 500);
            }

            // Instantiate classes dynamically to avoid static analyzer errors if library is missing
            $qrClass = '\Endroid\QrCode\QrCode';
            $writerClass = '\Endroid\QrCode\Writer\PngWriter';

            // Generate QR code
            $qrCode = new $qrClass('https://kingsway.ac.ke/student/qr/' . $id);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            // Create writer
            $writer = new $writerClass();

            // Generate QR code image
            $result = $writer->write($qrCode);

            // Save QR code to images folder
            $qrPath = 'images/qr_codes/' . $student['admission_no'] . '.png';
            $dir = dirname($qrPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $result->saveToFile($qrPath);

            // Import generated QR into MediaManager so it's managed under uploads/students/{id}
            try {
                $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
                $projectRoot = realpath(__DIR__ . '/../../..');
                if ($projectRoot) {
                    $fullSource = $projectRoot . DIRECTORY_SEPARATOR . $qrPath;
                    if (file_exists($fullSource)) {
                        $mediaId = $mediaManager->import($fullSource, 'students', $id, basename($fullSource), null, 'student qr code');
                        $preview = $mediaManager->getPreviewUrl($mediaId);
                        // Update student record with managed preview URL if available
                        if ($preview) {
                            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                            $stmt->execute([$preview, $id]);
                        } else {
                            $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                            $stmt->execute(['/' . $qrPath, $id]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Fallback: store local path
                $stmt = $this->db->prepare("UPDATE students SET qr_code_path = ? WHERE id = ?");
                $stmt->execute(['/' . $qrPath, $id]);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'QR code generated successfully',
                'data' => [
                    'qr_path' => $qrPath
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getProfile($id) {
        try {
            $profile = $this->getStudentOverviewRecord($id);

            if (!$profile) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            // Get additional profile details
            $profile['parents'] = $this->getStudentParents($id);
            $profile['academic_history'] = $this->getAcademicPerformance($id, []);
            $profile['attendance_history'] = $this->getAttendanceRecord($id, []);
            $profile['discipline_records'] = $this->getDisciplineRecords($id);

            return $this->response(['status' => 'success', 'data' => $profile]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAttendance($id, $params = []) {
        try {
            $params = array_merge($_GET ?? [], $params ?? []);
            $records = $this->getAttendanceRecord($id, $params);
            $summary = $this->buildAttendanceSummary($records);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'records' => $records,
                    'summary' => $summary,
                    // Backward-compatible aliases for existing consumers
                    'data' => $records,
                    'total' => $summary['total'] ?? 0
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPerformance($id, $params = []) {
        try {
            $params = array_merge($_GET ?? [], $params ?? []);
            return $this->response([
                'status' => 'success',
                'data' => $this->getAcademicPerformance($id, $params)
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getFees($id) {
        try {
            $summary = $this->getFeeSummary($id);
            $payments = $this->getFeePayments($id);
            $obligations = $this->getFeeObligations($id);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'summary' => $summary,
                    'payments' => $payments,
                    'obligations' => $obligations
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getQrInfo($id) {
        try {
            $sql = "
                SELECT
                    s.id, s.admission_no, s.first_name, s.last_name, s.qr_code_path,
                    c.name as class_name,
                    cs.stream_name
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE s.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'data' => $student
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function promote($id, $data) {
        try {
            $required = ['new_class_id', 'new_stream_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Fetch current student stream
            $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = ?");
            $stmt->execute([$id]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }
            $currentStreamId = (int) $student['stream_id'];

            // Resolve current class_id from stream
            $stmt = $this->db->prepare("SELECT class_id FROM class_streams WHERE id = ?");
            $stmt->execute([$currentStreamId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $currentClassId = $row ? (int)$row['class_id'] : (int)($data['current_class_id'] ?? 0);

            // Get active academic year values
            $stmt = $this->db->query(
                "SELECT id, CAST(SUBSTRING(year_code,1,4) AS UNSIGNED) as yr
                 FROM academic_years WHERE is_current = 1 LIMIT 1"
            );
            $yearRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $fromYearId  = $yearRow ? (int)$yearRow['id'] : 0;
            $fromYearVal = $yearRow ? (int)$yearRow['yr'] : (int)date('Y');
            $toYearVal   = $fromYearVal + 1;

            // Get current term id
            $stmt = $this->db->prepare(
                "SELECT id FROM academic_terms WHERE year = ?
                 ORDER BY FIELD(status,'current','completed','upcoming'), term_number DESC LIMIT 1"
            );
            $stmt->execute([$fromYearVal]);
            $termRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $termId = $termRow ? (int)$termRow['id'] : 1;

            // Create a manual batch for this single promotion
            $batchStmt = $this->db->prepare(
                "INSERT INTO promotion_batches
                    (batch_scope, from_academic_year, to_academic_year,
                     batch_type, total_students_processed, created_by, status)
                 VALUES (?, ?, ?, 'manual', 1, ?, 'completed')"
            );
            $batchStmt->execute([
                "Direct promotion - student {$id}",
                $fromYearVal, $toYearVal,
                $this->user_id ?? 1
            ]);
            $batchId = $this->db->lastInsertId();

            $this->db->beginTransaction();

            // Update student's current stream
            $stmt = $this->db->prepare("UPDATE students SET stream_id = ? WHERE id = ?");
            $stmt->execute([$data['new_stream_id'], $id]);

            // Mark current enrollment as promoted
            $stmt = $this->db->prepare(
                "UPDATE class_enrollments
                 SET promotion_status = 'promoted',
                     promoted_to_class_id = ?,
                     promoted_to_stream_id = ?,
                     promotion_date = CURDATE()
                 WHERE student_id = ? AND academic_year_id = ?"
            );
            $stmt->execute([$data['new_class_id'], $data['new_stream_id'], $id, $fromYearId]);

            // Record in student_promotions with correct column names
            $sql = "INSERT INTO student_promotions (
                        batch_id, student_id,
                        current_class_id, current_stream_id,
                        promoted_to_class_id, promoted_to_stream_id,
                        from_academic_year, to_academic_year,
                        from_term_id, promotion_status, promotion_reason
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval', ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $batchId, $id,
                $currentClassId, $currentStreamId,
                $data['new_class_id'], $data['new_stream_id'],
                $fromYearVal, $toYearVal,
                $termId,
                $data['remarks'] ?? null
            ]);

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Student promoted successfully'
            ]);
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function transfer($id, $data) {
        try {
            return $this->transferStudent($id, $data);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getDisciplineRecords($id) {
        $sql = "
            SELECT * FROM student_discipline
            WHERE student_id = ?
            ORDER BY incident_date DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bulkCreate($data) {
        try {
            $rows = [];
            if (!empty($data['file'])) {
                $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
                $result = $bulkHelper->processUploadedFile($data['file']);
                if ($result['status'] === 'error') {
                    return $this->response($result, 400);
                }
                $rows = $result['data'] ?? [];
            } elseif (!empty($data['students']) && is_array($data['students'])) {
                $rows = $data['students'];
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded or students payload provided'
                ], 400);
            }

            if (empty($rows)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No student rows found'
                ], 400);
            }

            // Build stream lookup map (class + stream name => stream_id)
            $streamLookup = [];
            $classStreams = [];
            $classDisplayNames = [];
            $stmt = $this->db->query("
                SELECT cs.id, cs.stream_name, c.name AS class_name
                FROM class_streams cs
                JOIN classes c ON cs.class_id = c.id
            ");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $classKey = strtolower(trim((string) $r['class_name']));
                $streamKey = strtolower(trim((string) $r['stream_name']));
                $streamLookup[$classKey . '|' . $streamKey] = (int) $r['id'];
                $classStreams[$classKey][] = [
                    'id' => (int) $r['id'],
                    'name' => $r['stream_name']
                ];
                $classDisplayNames[$classKey] = $r['class_name'];
            }
            $classDefaultStreams = [];
            foreach ($classStreams as $classKey => $streams) {
                if (count($streams) === 1) {
                    $classDefaultStreams[$classKey] = $streams[0];
                    $streamLookup[$classKey . '|'] = $streams[0]['id'];
                }
            }

            // Student type lookup
            $studentTypeLookup = [];
            $typeStmt = $this->db->query("SELECT id, code, name FROM student_types WHERE status = 'active'");
            foreach ($typeStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $studentTypeLookup[strtolower($t['code'])] = (int) $t['id'];
                $studentTypeLookup[strtolower($t['name'])] = (int) $t['id'];
            }

            $updateExisting = !empty($data['update_existing']) && (int) $data['update_existing'] === 1;
            $existingStmt = $this->db->query("SELECT admission_no FROM students");
            $existingAdmissions = array_flip(array_map('strtolower', $existingStmt->fetchAll(PDO::FETCH_COLUMN)));

            $processedData = [];
            $errors = [];
            $warnings = [];
            $duplicates = [];
            $seenAdmissions = [];
            $rowIndex = 1;

            $normalizeKey = function ($key) {
                $key = strtolower(trim((string) $key));
                $key = preg_replace('/[^a-z0-9]+/', '_', $key);
                return trim($key, '_');
            };

            $normalizeDate = function ($value, $fieldLabel, $rowIndex, $admissionNo) use (&$errors, &$warnings) {
                if ($value === null || $value === '') {
                    return null;
                }

                if (is_numeric($value)) {
                    $num = floatval($value);
                    if ($num < 1 || $num > 60000) {
                        $errors[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => "Invalid {$fieldLabel} value. Expected YYYY-MM-DD."
                        ];
                        return null;
                    }

                    try {
                        $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($num);
                        $formatted = $dt->format('Y-m-d');
                        $warnings[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => "{$fieldLabel} was an Excel date number and was converted to {$formatted}"
                        ];
                        return $formatted;
                    } catch (Exception $e) {
                        $errors[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => "Invalid {$fieldLabel} value. Expected YYYY-MM-DD."
                        ];
                        return null;
                    }
                }

                $value = trim((string) $value);
                if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $value)) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'admission_no' => $admissionNo,
                        'message' => "Invalid {$fieldLabel} format. Use YYYY-MM-DD."
                    ];
                    return null;
                }

                $dt = \DateTime::createFromFormat('Y-m-d', $value);
                $dateErrors = \DateTime::getLastErrors();
                if ($dt === false || $dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'admission_no' => $admissionNo,
                        'message' => "Invalid {$fieldLabel} date. Use YYYY-MM-DD."
                    ];
                    return null;
                }

                return $dt->format('Y-m-d');
            };

            foreach ($rows as $row) {
                $rowIndex++;
                $normalized = [];
                foreach ($row as $k => $v) {
                    $nk = $normalizeKey($k);
                    $normalized[$nk] = is_string($v) ? trim($v) : $v;
                }

                // Map common header variants to canonical fields
                $map = [
                    'admission_number' => 'admission_no',
                    'admissionno' => 'admission_no',
                    'admission_no' => 'admission_no',
                    'firstname' => 'first_name',
                    'first_name' => 'first_name',
                    'middlename' => 'middle_name',
                    'middle_name' => 'middle_name',
                    'lastname' => 'last_name',
                    'last_name' => 'last_name',
                    'surname' => 'last_name',
                    'dateofbirth' => 'date_of_birth',
                    'dob' => 'date_of_birth',
                    'date_of_birth' => 'date_of_birth',
                    'gender' => 'gender',
                    'sex' => 'gender',
                    'stream_id' => 'stream_id',
                    'stream' => 'stream_name',
                    'stream_name' => 'stream_name',
                    'class' => 'class_name',
                    'class_name' => 'class_name',
                    'student_type_id' => 'student_type_id',
                    'student_type' => 'student_type',
                    'student_type_code' => 'student_type',
                    'boarding_status' => 'student_type',
                    'admission_date' => 'admission_date',
                    'date_of_admission' => 'admission_date',
                    'assessment_number' => 'assessment_number',
                    'nemis_number' => 'nemis_number',
                    'status' => 'status',
                    'blood_group' => 'blood_group',
                    'is_sponsored' => 'is_sponsored',
                    'sponsor_name' => 'sponsor_name',
                    'sponsor_type' => 'sponsor_type',
                    'sponsor_waiver_percentage' => 'sponsor_waiver_percentage'
                ];

                $canon = [];
                foreach ($normalized as $k => $v) {
                    $ck = $map[$k] ?? $k;
                    $canon[$ck] = $v;
                }

                // Required fields
                $admissionNo = $canon['admission_no'] ?? null;
                if (empty($admissionNo)) {
                    $admissionNo = $this->generateAdmissionNumber();
                }

                $admKey = strtolower($admissionNo);
                if (isset($seenAdmissions[$admKey])) {
                    $duplicates[] = [
                        'row' => $rowIndex,
                        'admission_no' => $admissionNo,
                        'message' => 'Duplicate admission_no in file; row skipped'
                    ];
                    continue;
                }
                $seenAdmissions[$admKey] = true;

                $firstName = $canon['first_name'] ?? null;
                $lastName = $canon['last_name'] ?? null;
                $dob = $canon['date_of_birth'] ?? null;
                $gender = $canon['gender'] ?? null;
                $admissionDate = $canon['admission_date'] ?? date('Y-m-d');

                if (empty($firstName) || empty($lastName) || empty($dob) || empty($gender)) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'admission_no' => $admissionNo,
                        'message' => 'Missing required fields: first_name, last_name, date_of_birth, gender'
                    ];
                    continue;
                }

                $normalizedDob = $normalizeDate($dob, 'date_of_birth', $rowIndex, $admissionNo);
                if ($normalizedDob === null) {
                    continue;
                }
                $dob = $normalizedDob;

                // Normalize gender
                $genderVal = strtolower(trim((string) $gender));
                if (in_array($genderVal, ['m', 'male'])) {
                    $genderVal = 'male';
                } elseif (in_array($genderVal, ['f', 'female'])) {
                    $genderVal = 'female';
                } elseif (!in_array($genderVal, ['male', 'female', 'other'])) {
                    $genderVal = 'other';
                }

                // Resolve stream_id
                $streamId = $canon['stream_id'] ?? null;
                if (!empty($streamId) && is_numeric($streamId)) {
                    $streamId = (int) $streamId;
                } else {
                    $classNameRaw = trim((string) ($canon['class_name'] ?? ''));
                    $streamNameRaw = trim((string) ($canon['stream_name'] ?? ''));
                    $className = strtolower($classNameRaw);
                    $streamName = strtolower($streamNameRaw);

                    if ($className === '' && $streamName === '') {
                        $errors[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => 'Missing class_name and stream_name'
                        ];
                        continue;
                    }

                    if ($streamName !== '') {
                        if ($className === '') {
                            $errors[] = [
                                'row' => $rowIndex,
                                'admission_no' => $admissionNo,
                                'message' => 'stream_name provided without class_name'
                            ];
                            continue;
                        }
                        $key = $className . '|' . $streamName;
                        $streamId = $streamLookup[$key] ?? null;
                        if (empty($streamId)) {
                            $errors[] = [
                                'row' => $rowIndex,
                                'admission_no' => $admissionNo,
                                'message' => "Stream '{$streamNameRaw}' not found for class '{$classNameRaw}'"
                            ];
                            continue;
                        }
                    } else {
                        if (isset($classDefaultStreams[$className])) {
                            $streamId = $classDefaultStreams[$className]['id'];
                            $displayClass = $classDisplayNames[$className] ?? $classNameRaw;
                            $warnings[] = [
                                'row' => $rowIndex,
                                'admission_no' => $admissionNo,
                                'message' => "Stream not provided for class '{$displayClass}'. Defaulted to '{$classDefaultStreams[$className]['name']}'."
                            ];
                        } elseif (isset($classStreams[$className])) {
                            $displayClass = $classDisplayNames[$className] ?? $classNameRaw;
                            $errors[] = [
                                'row' => $rowIndex,
                                'admission_no' => $admissionNo,
                                'message' => "Class '{$displayClass}' has multiple streams. Provide stream_name."
                            ];
                            continue;
                        } else {
                            $errors[] = [
                                'row' => $rowIndex,
                                'admission_no' => $admissionNo,
                                'message' => "Class '{$classNameRaw}' not found"
                            ];
                            continue;
                        }
                    }
                }

                if (empty($streamId)) {
                    $errors[] = [
                        'row' => $rowIndex,
                        'admission_no' => $admissionNo,
                        'message' => 'Missing or invalid stream_id (or class/stream name)'
                    ];
                    continue;
                }

                // Resolve student_type_id
                $studentTypeId = $canon['student_type_id'] ?? null;
                if (!empty($studentTypeId) && is_numeric($studentTypeId)) {
                    $studentTypeId = (int) $studentTypeId;
                } else {
                    $stypeRaw = strtolower(trim((string) ($canon['student_type'] ?? '')));
                    if (empty($stypeRaw)) {
                        $studentTypeId = 1;
                    } else {
                        $stypeRaw = str_replace(['boarder', 'boarding'], ['board', 'board'], $stypeRaw);
                        $studentTypeId = $studentTypeLookup[$stypeRaw] ?? $studentTypeLookup[strtoupper($stypeRaw)] ?? null;
                        if ($studentTypeId === null) {
                            if (in_array($stypeRaw, ['day', 'day_scholar'], true)) {
                                $studentTypeId = 1;
                            } elseif (in_array($stypeRaw, ['board', 'full_boarder'], true)) {
                                $studentTypeId = 2;
                            } elseif (in_array($stypeRaw, ['weekly', 'weekly_boarder'], true)) {
                                $studentTypeId = 3;
                            } else {
                                $studentTypeId = 1;
                            }
                        }
                    }
                }

                $status = $canon['status'] ?? 'active';
                $status = in_array($status, ['active', 'inactive', 'graduated', 'transferred', 'suspended']) ? $status : 'active';

                $isSponsored = !empty($canon['is_sponsored']) ? 1 : 0;
                $sponsorWaiverPct = $canon['sponsor_waiver_percentage'] ?? 0;
                if ($isSponsored && $sponsorWaiverPct === '') {
                    $sponsorWaiverPct = 0;
                }

                // Skip duplicates when update_existing is false
                if (!$updateExisting) {
                    if (isset($existingAdmissions[$admKey])) {
                        $duplicates[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => 'Admission number already exists; row skipped'
                        ];
                        continue;
                    }
                    $existingAdmissions[$admKey] = true;
                } else {
                    if (isset($existingAdmissions[$admKey])) {
                        $duplicates[] = [
                            'row' => $rowIndex,
                            'admission_no' => $admissionNo,
                            'message' => 'Admission number exists; record will be updated'
                        ];
                    }
                }

                $processedData[] = [
                    'admission_no' => $admissionNo,
                    'first_name' => $firstName,
                    'middle_name' => $canon['middle_name'] ?? null,
                    'last_name' => $lastName,
                    'date_of_birth' => $dob,
                    'gender' => $genderVal,
                    'stream_id' => $streamId,
                    'student_type_id' => $studentTypeId,
                    'admission_date' => $admissionDate,
                    'assessment_number' => $canon['assessment_number'] ?? null,
                    'assessment_status' => $canon['assessment_status'] ?? 'not_assigned',
                    'nemis_number' => $canon['nemis_number'] ?? null,
                    'nemis_status' => $canon['nemis_status'] ?? 'not_assigned',
                    'status' => $status,
                    'photo_url' => $canon['photo_url'] ?? null,
                    'qr_code_path' => $canon['qr_code_path'] ?? null,
                    'is_sponsored' => $isSponsored,
                    'sponsor_name' => $canon['sponsor_name'] ?? null,
                    'sponsor_type' => $canon['sponsor_type'] ?? null,
                    'sponsor_waiver_percentage' => $sponsorWaiverPct,
                    'blood_group' => $canon['blood_group'] ?? null
                ];
            }

            if (empty($processedData)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No valid student rows to import',
                    'data' => [
                        'errors' => $errors,
                        'warnings' => $warnings,
                        'duplicates' => $duplicates
                    ]
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $uniqueColumns = $updateExisting ? ['admission_no'] : [];
            $insertResult = $bulkHelper->bulkInsert('students', $processedData, $uniqueColumns);

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student creation completed',
                'data' => [
                    'insert' => $insertResult,
                    'processed' => count($processedData),
                    'errors' => $errors,
                    'warnings' => $warnings,
                    'duplicates' => $duplicates
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkUpdate($data) {
        try {
            if (empty($data['file'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $result = $bulkHelper->processUploadedFile($data['file']);

            if ($result['status'] === 'error') {
                return $this->response($result, 400);
            }

            // Normalize admission_number -> admission_no if provided
            foreach ($result['data'] as &$row) {
                if (isset($row['admission_number']) && !isset($row['admission_no'])) {
                    $row['admission_no'] = $row['admission_number'];
                    unset($row['admission_number']);
                }
            }

            // Update students
            $updateResult = $bulkHelper->bulkUpdate(
                'students',
                $result['data'],
                'admission_no'
            );

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student update completed',
                'data' => $updateResult
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // getQRInfo removed - duplicate of getQrInfo at line ~1198

    // Transfer Workflow Methods
    public function startTransferWorkflow($data)
    {
        try {
            $studentId = (int) ($data['student_id'] ?? 0);
            if ($studentId <= 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student ID is required'
                ], 400);
            }

            $targetStreamId = isset($data['target_stream_id']) ? (int) $data['target_stream_id'] : null;
            $targetClassId = isset($data['target_class_id']) ? (int) $data['target_class_id'] : null;
            $transferToSchool = trim((string) ($data['transfer_to_school'] ?? ''));
            $reason = trim((string) ($data['reason'] ?? $data['transfer_reason'] ?? ''));

            $studentStmt = $this->db->prepare("
                SELECT id, stream_id, status
                FROM students
                WHERE id = ?
                LIMIT 1
            ");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $currentStreamId = (int) ($student['stream_id'] ?? 0);
            $currentStream = $this->resolveClassFromStream($currentStreamId);
            if (!$currentStream) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student is assigned to an invalid stream'
                ], 400);
            }

            // Internal class/stream movement (current workflow from Students page)
            if ($targetStreamId !== null) {
                $targetStream = $this->resolveClassFromStream($targetStreamId);
                if (!$targetStream) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Target stream not found'
                    ], 404);
                }

                if ($targetClassId !== null && (int) $targetStream['class_id'] !== $targetClassId) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Target class does not match selected stream'
                    ], 400);
                }

                if ($targetStreamId === $currentStreamId) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Student is already assigned to the selected stream'
                    ], 400);
                }

                $note = $reason !== '' ? $reason : 'Internal class/stream transfer';

                $this->db->beginTransaction();
                $enrollmentId = $this->ensureClassEnrollment(
                    $studentId,
                    $targetStreamId,
                    null,
                    (string) ($student['status'] ?? 'active'),
                    $note
                );

                $updateStudentStmt = $this->db->prepare("
                    UPDATE students
                    SET stream_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStudentStmt->execute([$targetStreamId, $studentId]);

                $transferId = $this->recordInternalClassTransferAudit(
                    $studentId,
                    $currentStreamId,
                    $targetStreamId,
                    $note
                );

                $this->db->commit();
                $this->logAction(
                    'update',
                    $studentId,
                    "Transferred student {$studentId} from stream {$currentStreamId} to {$targetStreamId}"
                );

                return $this->response([
                    'status' => 'success',
                    'message' => 'Student class allocation updated successfully',
                    'data' => [
                        'transfer_type' => 'internal',
                        'student_id' => $studentId,
                        'from_stream_id' => $currentStreamId,
                        'to_stream_id' => $targetStreamId,
                        'transfer_id' => $transferId,
                        'enrollment_id' => $enrollmentId
                    ]
                ]);
            }

            // External transfer request
            if ($transferToSchool === '') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'transfer_to_school is required for external transfers'
                ], 400);
            }

            if ($reason === '') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'transfer_reason is required for external transfers'
                ], 400);
            }

            $academicYearRecord = $this->getCurrentAcademicYearRecord();
            $academicYearId = (int) ($academicYearRecord['id'] ?? 0);
            $academicYear = $this->extractAcademicYearNumber($academicYearRecord);
            $termId = $this->getCurrentTermId($academicYear);

            if ($academicYearId <= 0 || !$academicYear || !$termId) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Cannot start transfer request without current academic year and term setup'
                ], 400);
            }

            $this->db->beginTransaction();
            $sql = "
                INSERT INTO student_promotions (
                    batch_id,
                    from_enrollment_id,
                    to_enrollment_id,
                    from_academic_year_id,
                    to_academic_year_id,
                    student_id,
                    current_class_id,
                    current_stream_id,
                    promoted_to_class_id,
                    promoted_to_stream_id,
                    from_academic_year,
                    to_academic_year,
                    from_term_id,
                    promotion_status,
                    promotion_reason,
                    transfer_to_school,
                    rejection_reason,
                    approval_notes
                ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, 'pending_approval', ?, ?, ?, NULL)
                ON DUPLICATE KEY UPDATE
                    current_class_id = VALUES(current_class_id),
                    current_stream_id = VALUES(current_stream_id),
                    promotion_status = 'pending_approval',
                    promotion_reason = VALUES(promotion_reason),
                    transfer_to_school = VALUES(transfer_to_school),
                    rejection_reason = VALUES(rejection_reason),
                    approval_notes = NULL,
                    approved_by = NULL,
                    approval_date = NULL,
                    updated_at = NOW()
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                0,
                $academicYearId,
                $academicYearId,
                $studentId,
                (int) $currentStream['class_id'],
                $currentStreamId,
                $academicYear,
                $academicYear,
                (int) $termId,
                $reason,
                $transferToSchool,
                $reason
            ]);

            $transferId = $this->db->lastInsertId();
            if (!$transferId) {
                $lookupStmt = $this->db->prepare("
                    SELECT id
                    FROM student_promotions
                    WHERE student_id = ? AND from_academic_year = ? AND to_academic_year = ?
                    LIMIT 1
                ");
                $lookupStmt->execute([$studentId, $academicYear, $academicYear]);
                $transferId = (int) ($lookupStmt->fetchColumn() ?: 0);
            }

            $this->db->commit();
            $this->logAction('create', $transferId, "Started external transfer request for student {$studentId} to {$transferToSchool}");

            return $this->response([
                'status' => 'success',
                'message' => 'External transfer request started successfully',
                'data' => [
                    'transfer_type' => 'external',
                    'transfer_id' => $transferId,
                    'student_id' => $studentId
                ]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function verifyTransferEligibility($data)
    {
        try {
            $required = ['transfer_id', 'student_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Check for outstanding fees or other blockers
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as pending_fees,
                    COALESCE(SUM(balance), 0) as pending_balance
                FROM student_fee_obligations
                WHERE student_id = ? AND balance > 0
            ");
            $stmt->execute([$data['student_id']]);
            $feeCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            $eligible = ($feeCheck['pending_fees'] == 0);
            $notes = $eligible
                ? 'No pending fee obligations - eligible for transfer'
                : 'Student has outstanding fee obligations';

            return $this->response([
                'status' => 'success',
                'data' => [
                    'eligible' => $eligible,
                    'notes' => $notes,
                    'pending_fees' => $feeCheck['pending_fees'],
                    'pending_balance' => $feeCheck['pending_balance']
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function approveTransfer($data)
    {
        try {
            $required = ['transfer_id', 'decision'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Validate decision
            if ($data['decision'] === 'approved') {
                $newStatus = 'approved';
            } elseif ($data['decision'] === 'rejected') {
                $newStatus = 'rejected';
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid decision. Must be: approved or rejected'
                ], 400);
            }

            $currentUserId = $this->getCurrentUserId();
            $sql = "UPDATE student_promotions 
                    SET promotion_status = ?, 
                        approved_by = ?, 
                        approval_date = NOW(), 
                        approval_notes = ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newStatus,
                $currentUserId,
                $data['notes'] ?? null,
                $data['transfer_id']
            ]);

            $this->db->commit();
            $this->logAction('update', $data['transfer_id'], "Transfer decision: {$data['decision']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer decision recorded successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function executeTransfer($data)
    {
        try {
            $required = ['transfer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            // Get transfer data
            $stmt = $this->db->prepare("SELECT * FROM student_promotions WHERE id = ?");
            $stmt->execute([$data['transfer_id']]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            if ($transfer['promotion_status'] !== 'approved') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Cannot execute transfer - not approved'
                ], 400);
            }

            // Update promotion status to transferred
            $sql = "UPDATE student_promotions SET promotion_status = 'transferred' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['transfer_id']]);

            // Update student status to transferred
            $sql = "UPDATE students SET status = 'transferred' WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$transfer['student_id']]);

            $this->db->commit();
            $this->logAction('update', $data['transfer_id'], "Transfer executed - student moved to {$transfer['transfer_to_school']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer executed successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function getTransferWorkflowStatus($instanceId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sp.*, s.first_name, s.last_name, s.admission_no, s.status as student_status
                FROM student_promotions sp
                JOIN students s ON sp.student_id = s.id
                WHERE sp.id = ? AND sp.promotion_status IN ('pending_approval', 'approved', 'rejected', 'transferred')
            ");
            $stmt->execute([$instanceId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'data' => $transfer
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== ADDITIONAL PUBLIC METHODS ====================

    public function getStudentParentsInfo($id)
    {
        try {
            $parents = $this->getStudentParents($id);
            return $this->response([
                'status' => 'success',
                'data' => $parents
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getMedicalRecords($id)
    {
        try {
            // Get student's admission application
            $stmt = $this->db->prepare("
                SELECT aa.id as application_id
                FROM students s
                LEFT JOIN admission_applications aa ON aa.applicant_name = CONCAT(s.first_name, ' ', s.last_name) 
                    AND aa.status = 'enrolled'
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->response([
                    'status' => 'success',
                    'message' => 'No medical records found',
                    'data' => []
                ]);
            }

            // Get medical records from admission documents
            $stmt = $this->db->prepare("
                SELECT * FROM admission_documents 
                WHERE application_id = ? AND document_type = 'medical_records'
                ORDER BY created_at DESC
            ");
            $stmt->execute([$application['application_id']]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDisciplineRecordsInfo($id)
    {
        try {
            $records = $this->getDisciplineRecords($id);
            return $this->response([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listDisciplineCases($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search] = $this->getSearchParams();

            $search = $params['search'] ?? $_GET['search'] ?? $search ?? null;
            $status = $params['status'] ?? $_GET['status'] ?? null;
            $severity = $params['severity'] ?? $_GET['severity'] ?? null;
            $classId = $params['class_id'] ?? $_GET['class_id'] ?? null;

            $conditions = [];
            $bindings = [];

            if (!empty($search)) {
                $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR sd.description LIKE ?)";
                $term = "%{$search}%";
                $bindings = array_merge($bindings, [$term, $term, $term, $term]);
            }

            if (!empty($status)) {
                $conditions[] = "sd.status = ?";
                $bindings[] = $status;
            }

            if (!empty($severity)) {
                $conditions[] = "sd.severity = ?";
                $bindings[] = $severity;
            }

            if (!empty($classId)) {
                $conditions[] = "c.id = ?";
                $bindings[] = $classId;
            }

            $where = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

            $countSql = "
                SELECT COUNT(*)
                FROM student_discipline sd
                JOIN students s ON sd.student_id = s.id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                {$where}
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = (int) $stmt->fetchColumn();

            $sql = "
                SELECT 
                    sd.*,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    s.gender,
                    cs.stream_name,
                    c.name AS class_name
                FROM student_discipline sd
                JOIN students s ON sd.student_id = s.id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                {$where}
                ORDER BY sd.incident_date DESC, sd.id DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $summaryStmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
                    SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
                FROM student_discipline
            ");
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total' => 0,
                'pending' => 0,
                'resolved' => 0,
                'escalated' => 0
            ];

            $termCount = 0;
            $termStmt = $this->db->query("
                SELECT start_date, end_date FROM academic_terms 
                WHERE status = 'current'
                ORDER BY start_date DESC
                LIMIT 1
            ");
            $term = $termStmt->fetch(PDO::FETCH_ASSOC);
            if ($term) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM student_discipline 
                    WHERE incident_date BETWEEN ? AND ?
                ");
                $stmt->execute([$term['start_date'], $term['end_date']]);
                $termCount = (int) $stmt->fetchColumn();
            }

            return $this->response([
                'status' => 'success',
                'data' => [
                    'cases' => $cases,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total
                    ],
                    'summary' => [
                        'total' => (int) $summary['total'],
                        'pending' => (int) $summary['pending'],
                        'resolved' => (int) $summary['resolved'],
                        'escalated' => (int) $summary['escalated'],
                        'term' => $termCount
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTransferHistory($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sp.*,
                       c_from.name AS current_class_name,
                       cs_from.stream_name AS current_stream_name,
                       c_to.name AS promoted_to_class_name,
                       cs_to.stream_name AS promoted_to_stream_name
                FROM student_promotions sp
                LEFT JOIN classes c_from ON c_from.id = sp.current_class_id
                LEFT JOIN class_streams cs_from ON cs_from.id = sp.current_stream_id
                LEFT JOIN classes c_to ON c_to.id = sp.promoted_to_class_id
                LEFT JOIN class_streams cs_to ON cs_to.id = sp.promoted_to_stream_id
                WHERE sp.student_id = ? AND sp.promotion_status = 'transferred'
                ORDER BY sp.approval_date DESC, sp.id DESC
            ");
            $stmt->execute([$id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPromotionHistory($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sp.*,
                       c_from.name AS current_class_name,
                       cs_from.stream_name AS current_stream_name,
                       c_to.name AS promoted_to_class_name,
                       cs_to.stream_name AS promoted_to_stream_name
                FROM student_promotions sp
                LEFT JOIN classes c_from ON c_from.id = sp.current_class_id
                LEFT JOIN class_streams cs_from ON cs_from.id = sp.current_stream_id
                LEFT JOIN classes c_to ON c_to.id = sp.promoted_to_class_id
                LEFT JOIN class_streams cs_to ON cs_to.id = sp.promoted_to_stream_id
                WHERE sp.student_id = ? AND sp.promotion_status IN ('approved', 'graduated', 'retained', 'transferred')
                ORDER BY sp.approval_date DESC, sp.id DESC
            ");
            $stmt->execute([$id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getEnrollmentHistory($studentId)
    {
        try {
            $sql = "
                SELECT
                    ce.id AS enrollment_id,
                    ce.student_id,
                    ce.academic_year_id,
                    ay.year_code,
                    ay.year_name,
                    ce.class_id,
                    c.name AS class_name,
                    ce.stream_id,
                    cs.stream_name,
                    ce.enrollment_status,
                    ce.enrollment_date,
                    ce.promotion_status,
                    ce.promotion_date
                FROM class_enrollments ce
                LEFT JOIN academic_years ay ON ce.academic_year_id = ay.id
                LEFT JOIN classes c ON ce.class_id = c.id
                LEFT JOIN class_streams cs ON ce.stream_id = cs.id
                WHERE ce.student_id = ?
                ORDER BY ay.start_date DESC, ce.enrollment_date DESC, ce.id DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $history
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentDocuments($id)
    {
        try {
            // Get student's admission application
            $stmt = $this->db->prepare("
                SELECT aa.id as application_id, aa.application_no, aa.status
                FROM students s
                LEFT JOIN admission_applications aa ON aa.applicant_name = CONCAT(s.first_name, ' ', s.last_name) 
                    AND aa.status = 'enrolled'
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->response([
                    'status' => 'success',
                    'message' => 'No admission documents found for this student',
                    'data' => []
                ]);
            }

            // Get admission documents
            $stmt = $this->db->prepare("SELECT * FROM admission_documents WHERE application_id = ? ORDER BY created_at DESC");
            $stmt->execute([$application['application_id']]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'application_info' => $application,
                    'documents' => $documents
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentsByClass($classId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*,
                       cs.stream_name,
                       c.name AS class_name,
                       ce.term1_average,
                       ce.term2_average,
                       ce.term3_average,
                       ce.year_average,
                       ce.overall_grade,
                       ce.class_rank,
                       ce.stream_rank,
                       ce.attendance_percentage,
                       ce.days_present,
                       ce.days_absent
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON c.id = cs.class_id
                LEFT JOIN class_enrollments ce
                    ON ce.student_id = s.id
                   AND ce.academic_year_id = (
                       SELECT ay.id
                       FROM academic_years ay
                       WHERE ay.is_current = 1 OR ay.status = 'active'
                       ORDER BY ay.is_current DESC, ay.start_date DESC, ay.id DESC
                       LIMIT 1
                   )
                WHERE cs.class_id = ? AND s.status = 'active'
                ORDER BY s.last_name, s.first_name
            ");
            $stmt->execute([$classId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $students
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentsByStream($streamId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM students 
                WHERE stream_id = ? AND status = 'active'
                ORDER BY last_name, first_name
            ");
            $stmt->execute([$streamId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $students
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStudentStatistics($params = [])
    {
        try {
            // Total students
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
            $total = $stmt->fetchColumn();

            // By gender
            $stmt = $this->db->query("SELECT gender, COUNT(*) as count FROM students WHERE status = 'active' GROUP BY gender");
            $byGender = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // By class
            $stmt = $this->db->query("
                SELECT c.name as class_name, COUNT(s.id) as count
                FROM classes c
                LEFT JOIN class_streams cs ON c.id = cs.class_id
                LEFT JOIN students s ON cs.id = s.stream_id AND s.status = 'active'
                GROUP BY c.id
                ORDER BY c.name
            ");
            $byClass = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => $total,
                    'by_gender' => $byGender,
                    'by_class' => $byClass
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkPromoteStudents($data)
    {
        try {
            $required = ['student_ids', 'to_class_id', 'to_stream_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $promoted = 0;
            foreach ($data['student_ids'] as $studentId) {
                $result = $this->promote($studentId, [
                    'new_class_id' => $data['to_class_id'],
                    'new_stream_id' => $data['to_stream_id'],
                    'current_class_id' => $data['from_class_id'] ?? null,
                    'current_stream_id' => $data['from_stream_id'] ?? null
                ]);

                if ($result['status'] === 'success') {
                    $promoted++;
                }
            }

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => "Promoted $promoted students successfully",
                'data' => ['promoted_count' => $promoted]
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function addMedicalRecord($data)
    {
        try {
            $required = ['application_id', 'file'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Handle file upload
            $filePath = "documents/admissions/{$data['application_id']}/medical/" . $data['file']['name'];

            $sql = "INSERT INTO admission_documents (application_id, document_type, document_path, is_mandatory, verification_status, notes) 
                    VALUES (?, 'medical_records', ?, ?, 'pending', ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['application_id'],
                $filePath,
                $data['is_mandatory'] ?? false,
                $data['notes'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Medical record document added successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateMedicalRecord($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['verification_status', 'notes'];

            // Validate verification_status if provided
            if (isset($data['verification_status']) && !in_array($data['verification_status'], ['pending', 'verified', 'rejected'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid verification_status. Must be: pending, verified, or rejected'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (isset($data['verification_status']) && $data['verification_status'] === 'verified') {
                $updates[] = "verified_by = ?";
                $updates[] = "verified_at = NOW()";
                $params[] = $this->getCurrentUserId();
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE admission_documents SET " . implode(', ', $updates) . " WHERE id = ? AND document_type = 'medical_records'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Medical record document updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateDisciplineCase($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['description', 'severity', 'action_taken', 'status'];

            // Validate severity if provided
            if (isset($data['severity']) && !in_array($data['severity'], ['low', 'medium', 'high'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid severity value. Must be: low, medium, or high'
                ], 400);
            }

            // Validate status if provided
            if (isset($data['status']) && !in_array($data['status'], ['pending', 'resolved', 'escalated'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid status value. Must be: pending, resolved, or escalated'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE student_discipline SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function resolveDisciplineCase($id, $data)
    {
        try {
            $currentUserId = $this->getCurrentUserId();

            $sql = "UPDATE student_discipline 
                    SET status = 'resolved', 
                        action_taken = ?, 
                        resolved_by = ?, 
                        resolution_date = NOW() 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['action_taken'] ?? 'Resolved',
                $currentUserId,
                $id
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case resolved successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function uploadStudentDocument($data)
    {
        try {
            $required = ['application_id', 'document_type', 'file'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate document_type enum
            $validDocTypes = [
                'birth_certificate',
                'immunization_card',
                'progress_report',
                'medical_records',
                'passport_photo',
                'nemis_upi',
                'leaving_certificate',
                'transfer_letter',
                'behavior_report',
                'other'
            ];
            if (!in_array($data['document_type'], $validDocTypes)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid document type'
                ], 400);
            }

            // Handle file upload logic here
            $filePath = "documents/admissions/{$data['application_id']}/" . $data['file']['name'];

            $sql = "INSERT INTO admission_documents (application_id, document_type, document_path, is_mandatory, verification_status) 
                    VALUES (?, ?, ?, ?, 'pending')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['application_id'],
                $data['document_type'],
                $filePath,
                $data['is_mandatory'] ?? false
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Document uploaded successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteStudentDocument($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM admission_documents WHERE id = ?");
            $stmt->execute([$id]);

            return $this->response([
                'status' => 'success',
                'message' => 'Document deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function addParentToStudent($data)
    {
        try {
            $required = ['student_id', 'parent_info'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->addParent($data['student_id'], $data['parent_info']);

            return $this->response([
                'status' => 'success',
                'message' => 'Parent added successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateParentInfo($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowedFields = ['first_name', 'last_name', 'gender', 'phone_1', 'phone_2', 'email', 'occupation', 'address', 'status'];

            // Validate gender if provided
            if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE parents SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Parent information updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function removeParentFromStudent($data)
    {
        try {
            $required = ['student_id', 'parent_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $stmt = $this->db->prepare("DELETE FROM student_parents WHERE student_id = ? AND parent_id = ?");
            $stmt->execute([$data['student_id'], $data['parent_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Parent relationship removed successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function bulkDelete($data)
    {
        try {
            $required = ['student_ids'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $placeholders = implode(',', array_fill(0, count($data['student_ids']), '?'));
            $stmt = $this->db->prepare("UPDATE students SET status = 'inactive' WHERE id IN ($placeholders)");
            $stmt->execute($data['student_ids']);

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Students deleted successfully',
                'data' => ['count' => $stmt->rowCount()]
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function markAttendanceForStudent($data)
    {
        try {
            $required = ['student_id', 'date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            return $this->markAttendance($data['student_id'], $data);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    protected function getCurrentUserId()
    {
        return $_SERVER['auth_user']['user_id'] ?? $this->user_id ?? null;
    }

    // ========================================================================
    // EXISTING STUDENT IMPORT METHODS
    // ========================================================================

    /**
     * Quick add existing student (bypasses admission workflow)
     * Use this when school starts using the system with already enrolled students
     * 
     * @param array $data Student data
     * @return array Response
     */
    public function addExistingStudent($data)
    {
        try {
            // Required fields for existing students
            $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'class_id', 'admission_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate gender
            $validGenders = ['male', 'female', 'other'];
            if (!in_array($data['gender'], $validGenders)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            $this->db->beginTransaction();

            // Generate admission number if not provided
            if (empty($data['admission_no'])) {
                $data['admission_no'] = $this->generateAdmissionNumber();
            }

            // Get stream_id from class_id and stream_name (if provided)
            $streamId = $this->getOrCreateStreamId($data['class_id'], $data['stream_name'] ?? 'A');

            // Insert student record
            $sql = "
                INSERT INTO students (
                    admission_no, first_name, middle_name, last_name, 
                    date_of_birth, gender, stream_id, student_type_id, admission_date,
                    assessment_number, blood_group,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['admission_no'],
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $streamId,
                $data['student_type_id'] ?? 1,
                $data['admission_date'],
                $data['assessment_number'] ?? null,
                $data['blood_group'] ?? null,
            ]);

            $studentId = $this->db->lastInsertId();

            // Add parent/guardian if provided
            if (!empty($data['parent'])) {
                $this->addStudentParent($studentId, $data['parent']);
            }

            // Add address if provided
            if (!empty($data['address'])) {
                $this->addStudentAddress($studentId, $data['address']);
            }

            // Generate QR code
            $this->generateQRCode($studentId);

            $this->db->commit();

            $this->logAction('create', $studentId, "Added existing student: {$data['first_name']} {$data['last_name']} (Quick Add)");

            return $this->response([
                'status' => 'success',
                'message' => 'Existing student added successfully',
                'data' => [
                    'id' => $studentId,
                    'admission_no' => $data['admission_no']
                ]
            ], 201);

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Add multiple existing students at once
     * Accepts array of student data
     * 
     * @param array $data Array of students
     * @return array Response with success/failure details
     */
    public function addMultipleExistingStudents($data)
    {
        try {
            if (empty($data['students']) || !is_array($data['students'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Students array is required'
                ], 400);
            }

            $results = [
                'total' => count($data['students']),
                'successful' => 0,
                'failed' => 0,
                'errors' => [],
                'students' => []
            ];

            foreach ($data['students'] as $index => $studentData) {
                try {
                    $response = $this->addExistingStudent($studentData);

                    if ($response['status'] === 'success') {
                        $results['successful']++;
                        $results['students'][] = [
                            'index' => $index,
                            'status' => 'success',
                            'data' => $response['data']
                        ];
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'index' => $index,
                            'student' => $studentData['first_name'] . ' ' . $studentData['last_name'],
                            'error' => $response['message']
                        ];
                    }
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'index' => $index,
                        'student' => ($studentData['first_name'] ?? 'Unknown') . ' ' . ($studentData['last_name'] ?? ''),
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logAction('create', null, "Bulk added {$results['successful']} existing students");

            return $this->response([
                'status' => $results['failed'] > 0 ? 'partial' : 'success',
                'message' => "Processed {$results['total']} students: {$results['successful']} successful, {$results['failed']} failed",
                'data' => $results
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Import existing students from CSV/Excel file
     * Enhanced version with better validation and error reporting
     * 
     * @param array $data File upload data
     * @return array Response
     */
    public function importExistingStudents($data)
    {
        try {
            if (empty($data['file'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No file uploaded'
                ], 400);
            }

            $bulkHelper = new \App\API\Includes\BulkOperationsHelper($this->db);
            $fileResult = $bulkHelper->processUploadedFile($data['file']);

            if ($fileResult['status'] === 'error') {
                return $this->response($fileResult, 400);
            }

            $results = [
                'total' => count($fileResult['data']),
                'successful' => 0,
                'failed' => 0,
                'skipped' => 0,
                'errors' => [],
                'warnings' => []
            ];

            $this->db->beginTransaction();

            foreach ($fileResult['data'] as $index => $row) {
                $rowNum = $index + 2; // +2 for header row and 0-based index

                try {
                    // Validate required fields
                    $requiredFields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'class_id'];
                    $missingFields = [];

                    foreach ($requiredFields as $field) {
                        if (empty($row[$field])) {
                            $missingFields[] = $field;
                        }
                    }

                    if (!empty($missingFields)) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'error' => 'Missing required fields: ' . implode(', ', $missingFields)
                        ];
                        continue;
                    }

                    // Check for duplicate admission number
                    if (!empty($row['admission_no'])) {
                        $stmt = $this->db->prepare("SELECT id FROM students WHERE admission_no = ?");
                        $stmt->execute([$row['admission_no']]);
                        if ($stmt->fetch()) {
                            $results['skipped']++;
                            $results['warnings'][] = [
                                'row' => $rowNum,
                                'message' => "Student with admission number {$row['admission_no']} already exists"
                            ];
                            continue;
                        }
                    } else {
                        $row['admission_no'] = $this->generateAdmissionNumber();
                    }

                    // Set default admission date if not provided
                    if (empty($row['admission_date'])) {
                        $row['admission_date'] = date('Y-m-d');
                    }

                    // Prepare student data
                    $studentData = [
                        'admission_no' => $row['admission_no'],
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'] ?? null,
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'],
                        'gender' => strtolower($row['gender']),
                        'class_id' => $row['class_id'],
                        'stream_name' => $row['stream_name'] ?? 'A',
                        'admission_date' => $row['admission_date'],
                        'assessment_number' => $row['assessment_number'] ?? null,
                        'nationality' => $row['nationality'] ?? 'Kenyan',
                        'religion' => $row['religion'] ?? null
                    ];

                    // Add parent data if available
                    if (!empty($row['parent_first_name']) && !empty($row['parent_last_name'])) {
                        $studentData['parent'] = [
                            'first_name' => $row['parent_first_name'],
                            'last_name' => $row['parent_last_name'],
                            'phone_1' => $row['parent_phone'] ?? null,
                            'email' => $row['parent_email'] ?? null,
                            'relationship' => $row['parent_relationship'] ?? 'parent'
                        ];
                    }

                    // Add the student
                    $response = $this->addExistingStudent($studentData);

                    if ($response['status'] === 'success') {
                        $results['successful']++;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'error' => $response['message']
                        ];
                    }

                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->db->commit();

            $this->logAction('create', null, "Imported {$results['successful']} existing students from file");

            return $this->response([
                'status' => $results['failed'] > 0 ? 'partial' : 'success',
                'message' => "Import completed: {$results['successful']} successful, {$results['failed']} failed, {$results['skipped']} skipped",
                'data' => $results
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Download template for importing existing students
     * 
     * @return array CSV template data
     */
    public function getImportTemplate()
    {
        $headers = [
            'admission_no',
            'first_name',
            'middle_name',
            'last_name',
            'date_of_birth',
            'gender',
            'class_id',
            'stream_name',
            'admission_date',
            'assessment_number',
            'birth_certificate_no',
            'nationality',
            'religion',
            'blood_group',
            'previous_school',
            'previous_class',
            'parent_first_name',
            'parent_last_name',
            'parent_phone',
            'parent_email',
            'parent_relationship',
            'address_line1',
            'address_line2',
            'city',
            'county',
            'postal_code'
        ];

        $sampleData = [
            [
                'KWA/2024/001',
                'John',
                'Kamau',
                'Doe',
                '2010-05-15',
                'male',
                '5',
                'A',
                '2020-01-15',
                'NEM123456',
                'BC123456',
                'Kenyan',
                'Christian',
                'O+',
                'Previous Primary School',
                'Grade 4',
                'Jane',
                'Doe',
                '0712345678',
                'jane.doe@email.com',
                'mother',
                '123 Main Street',
                'Apartment 4B',
                'Nairobi',
                'Nairobi',
                '00100'
            ]
        ];

        return $this->response([
            'status' => 'success',
            'data' => [
                'headers' => $headers,
                'sample' => $sampleData,
                'instructions' => [
                    'Required fields: first_name, last_name, date_of_birth, gender, class_id',
                    'Date format: YYYY-MM-DD',
                    'Gender: male, female, or other',
                    'class_id: Numeric ID of the class (e.g., 1 for Grade 1)',
                    'If admission_no is empty, it will be auto-generated',
                    'If admission_date is empty, current date will be used'
                ]
            ]
        ]);
    }

    // ========================================================================
    // HELPER METHODS FOR EXISTING STUDENT IMPORT
    // ========================================================================

    /**
     * Get or create stream ID for a class
     */
    private function getOrCreateStreamId($classId, $streamName = 'A')
    {
        $streamName = trim((string) $streamName);
        if ($streamName === '') {
            $streamName = 'A';
        }

        // Check if stream exists
        $stmt = $this->db->prepare("SELECT id FROM class_streams WHERE class_id = ? AND stream_name = ?");
        $stmt->execute([$classId, $streamName]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($stream) {
            return $stream['id'];
        }

        // Fallback: use an active stream for the class if requested stream does not exist.
        $fallbackStmt = $this->db->prepare("
            SELECT id
            FROM class_streams
            WHERE class_id = ?
              AND status = 'active'
            ORDER BY id ASC
            LIMIT 1
        ");
        $fallbackStmt->execute([$classId]);
        $fallbackId = $fallbackStmt->fetchColumn();
        if ($fallbackId) {
            return $fallbackId;
        }

        throw new Exception("No active stream is configured for class_id: {$classId}");
    }

    /**
     * Add parent/guardian for student
     */
    private function addStudentParent($studentId, $parentData)
    {
        // Check if parent already exists by phone or email
        $parentId = null;
        $phone = trim((string) ($parentData['phone_1'] ?? ''));
        $email = trim((string) ($parentData['email'] ?? ''));
        $firstName = trim((string) ($parentData['first_name'] ?? ''));
        $lastName = trim((string) ($parentData['last_name'] ?? ''));
        $gender = strtolower(trim((string) ($parentData['gender'] ?? 'other')));
        if (!in_array($gender, ['male', 'female', 'other'], true)) {
            $gender = 'other';
        }

        if ($phone !== '') {
            $stmt = $this->db->prepare("SELECT id FROM parents WHERE phone_1 = ? OR phone_2 = ?");
            $stmt->execute([$phone, $phone]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($parent) {
                $parentId = $parent['id'];
            }
        }

        if (!$parentId && $email !== '') {
            $stmt = $this->db->prepare("SELECT id FROM parents WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($parent) {
                $parentId = $parent['id'];
            }
        }

        // Create new parent if not found
        if (!$parentId) {
            if ($firstName === '' || $lastName === '' || $phone === '') {
                throw new Exception('Parent first_name, last_name and phone_1 are required when creating a new parent record');
            }

            $stmt = $this->db->prepare("
                INSERT INTO parents (
                    first_name,
                    last_name,
                    gender,
                    phone_1,
                    email,
                    status,
                    created_at,
                    updated_at
                ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $firstName,
                $lastName,
                $gender,
                $phone,
                $email !== '' ? $email : null
            ]);
            $parentId = $this->db->lastInsertId();
        }

        $this->linkStudentParent($studentId, $parentId, $parentData);

        return $parentId;
    }

    /**
     * Add address for student
     */
    private function addStudentAddress($studentId, $addressData)
    {
        $tableCheck = $this->db->query("SHOW TABLES LIKE 'student_addresses'");
        if (!$tableCheck->fetchColumn()) {
            $this->logError('student_addresses table is not available', "Skipped address save for student {$studentId}");
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO student_addresses (
                student_id, address_line1, address_line2, 
                city, county, postal_code, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $studentId,
            $addressData['address_line1'] ?? null,
            $addressData['address_line2'] ?? null,
            $addressData['city'] ?? null,
            $addressData['county'] ?? null,
            $addressData['postal_code'] ?? null
        ]);
    }

    // ========================================================================
    // STUDENT ID CARD & PHOTO MANAGEMENT
    // ========================================================================

    /**
     * Upload student photo
     */
    public function uploadPhoto($studentId, $fileData)
    {
        return $this->idCardGenerator->uploadStudentPhoto($studentId, $fileData);
    }

    /**
     * Generate or regenerate QR code for student
     */
    public function generateQRCodeEnhanced($studentId)
    {
        return $this->idCardGenerator->generateEnhancedQRCode($studentId);
    }

    /**
     * Generate student ID card
     */
    public function generateStudentIDCard($studentId)
    {
        return $this->idCardGenerator->generateIDCard($studentId);
    }

    /**
     * Generate ID cards for entire class
     */
    public function generateClassIDCards($classId, $streamId = null)
    {
        return $this->idCardGenerator->generateBulkIDCards($classId, $streamId);
    }

    /**
     * Get normalized student payload for ID card preview.
     */
    public function getIdCardPayload($studentId)
    {
        try {
            $student = $this->getStudentOverviewRecord($studentId);
            if (!$student) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student not found'
                ], 404);
            }

            $payload = [
                'id' => (int) $student['id'],
                'admission_no' => $student['admission_no'] ?? null,
                'first_name' => $student['first_name'] ?? '',
                'last_name' => $student['last_name'] ?? '',
                'full_name' => trim((string) ($student['full_name'] ?? (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? '')))),
                'class_name' => $student['class_name'] ?? null,
                'stream_name' => $student['stream_name'] ?? null,
                'date_of_birth' => $student['date_of_birth'] ?? null,
                'status' => $student['status'] ?? null,
                'photo_url' => $this->normalizePublicAssetPath($student['photo_url'] ?? ''),
                'qr_code_url' => $this->normalizePublicAssetPath($student['qr_code_path'] ?? ''),
                'qr_code_path' => $this->normalizePublicAssetPath($student['qr_code_path'] ?? ''),
            ];

            if (empty($payload['photo_url'])) {
                $payload['photo_url'] = '/Kingsway/images/logo.jpg';
            }

            return $this->response([
                'status' => 'success',
                'data' => $payload
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Aggregate ID card preparation statistics.
     */
    public function getIdCardStatistics($params = [])
    {
        try {
            $params = array_merge($_GET ?? [], $params ?? []);
            $conditions = ["s.status = 'active'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $conditions[] = 'cs.class_id = ?';
                $bindings[] = (int) $params['class_id'];
            }

            if (!empty($params['stream_id'])) {
                $conditions[] = 's.stream_id = ?';
                $bindings[] = (int) $params['stream_id'];
            }

            if (!empty($params['search'])) {
                $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)";
                $search = '%' . trim((string) $params['search']) . '%';
                $bindings[] = $search;
                $bindings[] = $search;
                $bindings[] = $search;
            }

            $where = 'WHERE ' . implode(' AND ', $conditions);

            $sql = "
                SELECT
                    COUNT(*) AS total_students,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(s.photo_url), ''), '') <> '' THEN 1 ELSE 0 END) AS students_with_photos,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(s.qr_code_path), ''), '') <> '' THEN 1 ELSE 0 END) AS students_with_qr_codes,
                    SUM(
                        CASE
                            WHEN COALESCE(NULLIF(TRIM(s.photo_url), ''), '') <> ''
                             AND COALESCE(NULLIF(TRIM(s.qr_code_path), ''), '') <> ''
                            THEN 1
                            ELSE 0
                        END
                    ) AS id_cards_ready
                FROM students s
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                {$where}
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => (int) ($stats['total_students'] ?? 0),
                    'with_photos' => (int) ($stats['students_with_photos'] ?? 0),
                    'with_qr_codes' => (int) ($stats['students_with_qr_codes'] ?? 0),
                    'id_cards_generated' => (int) ($stats['id_cards_ready'] ?? 0),
                ],
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function normalizePublicAssetPath($path)
    {
        $value = trim((string) $path);
        if ($value === '') {
            return '';
        }

        if (preg_match('/^(https?:)?\\/\\//i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        if (str_starts_with($value, '/Kingsway/')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return '/Kingsway' . $value;
        }

        return '/Kingsway/' . ltrim($value, '/');
    }

    // ============================================================
    // ACADEMIC YEAR MANAGEMENT METHODS
    // ============================================================

    /**
     * Get current academic year
     */
    public function getCurrentAcademicYear()
    {
        return $this->yearManager->getCurrentAcademicYear();
    }

    /**
     * Get academic year by ID
     */
    public function getAcademicYear($yearId)
    {
        return $this->yearManager->getAcademicYear($yearId);
    }

    /**
     * Get all academic years
     */
    public function getAllAcademicYears($filters = [])
    {
        return $this->yearManager->getAllYears($filters);
    }

    /**
     * Create new academic year
     */
    public function createAcademicYear($data)
    {
        return $this->yearManager->createAcademicYear($data);
    }

    /**
     * Create next academic year
     */
    public function createNextAcademicYear($userId)
    {
        return $this->yearManager->createNextYear($userId);
    }

    /**
     * Set year as current
     */
    public function setCurrentAcademicYear($yearId)
    {
        return $this->yearManager->setCurrentYear($yearId);
    }

    /**
     * Update academic year status
     */
    public function updateAcademicYearStatus($yearId, $status)
    {
        return $this->yearManager->updateYearStatus($yearId, $status);
    }

    /**
     * Archive academic year
     */
    public function archiveAcademicYear($yearId, $userId, $notes = null)
    {
        return $this->yearManager->archiveYear($yearId, $userId, $notes);
    }

    /**
     * Get terms for academic year
     */
    public function getTermsForYear($yearId)
    {
        return $this->yearManager->getTermsForYear($yearId);
    }

    /**
     * Get current term
     */
    public function getCurrentTerm()
    {
        return $this->yearManager->getCurrentTerm();
    }

    // ============================================================
    // NEW PROMOTION SYSTEM METHODS (5 Scenarios)
    // ============================================================

    private function getAcademicYearRecordById(int $yearId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, year_code, year_name, start_date, end_date
            FROM academic_years
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function resolvePromotionBatchCreatorId(): int
    {
        $userId = (int) ($this->getCurrentUserId() ?? 0);
        if ($userId > 0) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn()) {
                return $userId;
            }
        }

        $fallback = $this->db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
        return $fallback ? (int) $fallback : 1;
    }

    private function createPromotionBatchRecord(
        int $fromAcademicYear,
        int $toAcademicYear,
        string $batchType,
        string $batchScope,
        ?string $notes = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO promotion_batches (
                from_academic_year,
                to_academic_year,
                batch_type,
                batch_scope,
                status,
                total_students_processed,
                total_promoted,
                total_pending_approval,
                total_rejected,
                created_by,
                notes
            ) VALUES (?, ?, ?, ?, 'in_progress', 0, 0, 0, 0, ?, ?)
        ");
        $stmt->execute([
            $fromAcademicYear,
            $toAcademicYear,
            $batchType,
            $batchScope,
            $this->resolvePromotionBatchCreatorId(),
            $notes
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function closePromotionBatchRecord(
        int $batchId,
        int $processed,
        int $promoted,
        int $rejected = 0,
        string $status = 'completed',
        ?string $notes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE promotion_batches
            SET total_students_processed = ?,
                total_promoted = ?,
                total_rejected = ?,
                total_pending_approval = 0,
                status = ?,
                completed_at = NOW(),
                notes = COALESCE(?, notes)
            WHERE id = ?
        ");
        $stmt->execute([
            $processed,
            $promoted,
            $rejected,
            $status,
            $notes,
            $batchId
        ]);
    }

    private function lookupPromotionId(int $studentId, int $fromAcademicYear, int $toAcademicYear): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM student_promotions
            WHERE student_id = ? AND from_academic_year = ? AND to_academic_year = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $fromAcademicYear, $toAcademicYear]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function promoteStudentBetweenAcademicYears(
        int $studentId,
        int $toClassId,
        int $toStreamId,
        int $fromYearId,
        int $toYearId,
        int $performedBy,
        ?string $remarks,
        int $batchId
    ): array {
        if ($studentId <= 0) {
            throw new Exception('student_id is required');
        }
        if ($toClassId <= 0 || $toStreamId <= 0) {
            throw new Exception('to_class_id and to_stream_id are required');
        }
        if ($fromYearId <= 0 || $toYearId <= 0) {
            throw new Exception('from_year_id and to_year_id are required');
        }
        if ($fromYearId === $toYearId) {
            throw new Exception('Promotion must move to a different academic year');
        }

        $fromYearRecord = $this->getAcademicYearRecordById($fromYearId);
        $toYearRecord = $this->getAcademicYearRecordById($toYearId);
        if (!$fromYearRecord || !$toYearRecord) {
            throw new Exception('Invalid academic year selection');
        }

        $fromAcademicYear = $this->extractAcademicYearNumber($fromYearRecord);
        $toAcademicYear = $this->extractAcademicYearNumber($toYearRecord);
        if (!$fromAcademicYear || !$toAcademicYear) {
            throw new Exception('Could not resolve academic year values');
        }
        if ($toAcademicYear <= $fromAcademicYear) {
            throw new Exception('Target academic year must be greater than source academic year');
        }

        $targetStream = $this->resolveClassFromStream($toStreamId);
        if (!$targetStream || (int) ($targetStream['class_id'] ?? 0) !== $toClassId) {
            throw new Exception('Invalid target class/stream selection');
        }

        $studentStmt = $this->db->prepare("
            SELECT id, stream_id, status
            FROM students
            WHERE id = ?
            LIMIT 1
        ");
        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$student) {
            throw new Exception('Student not found');
        }

        $studentStatus = strtolower((string) ($student['status'] ?? 'active'));
        if (in_array($studentStatus, ['transferred', 'graduated'], true)) {
            throw new Exception('Student cannot be promoted from current status');
        }

        $sourceEnrollmentStmt = $this->db->prepare("
            SELECT id, class_id, stream_id
            FROM class_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $sourceEnrollmentStmt->execute([$studentId, $fromYearId]);
        $sourceEnrollment = $sourceEnrollmentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sourceEnrollment) {
            $currentStreamId = (int) ($student['stream_id'] ?? 0);
            if ($currentStreamId <= 0) {
                throw new Exception('Student has no current stream assignment');
            }

            $this->ensureClassEnrollment(
                $studentId,
                $currentStreamId,
                $fromYearId,
                $studentStatus,
                'Backfilled source enrollment for promotion'
            );

            $sourceEnrollmentStmt->execute([$studentId, $fromYearId]);
            $sourceEnrollment = $sourceEnrollmentStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$sourceEnrollment) {
            throw new Exception('Unable to resolve source enrollment for student');
        }

        $existingPromotionStmt = $this->db->prepare("
            SELECT id, promotion_status
            FROM student_promotions
            WHERE student_id = ? AND from_academic_year = ? AND to_academic_year = ?
            LIMIT 1
        ");
        $existingPromotionStmt->execute([$studentId, $fromAcademicYear, $toAcademicYear]);
        $existingPromotion = $existingPromotionStmt->fetch(PDO::FETCH_ASSOC);
        if ($existingPromotion && in_array((string) $existingPromotion['promotion_status'], ['approved', 'graduated', 'retained', 'transferred'], true)) {
            throw new Exception('Student already processed for the selected promotion cycle');
        }

        $reason = trim((string) ($remarks ?? 'Academic promotion'));
        if ($reason === '') {
            $reason = 'Academic promotion';
        }

        $destinationEnrollmentId = $this->ensureClassEnrollment(
            $studentId,
            $toStreamId,
            $toYearId,
            $studentStatus,
            $reason
        );

        $destinationEnrollmentStmt = $this->db->prepare("
            SELECT id
            FROM class_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $destinationEnrollmentStmt->execute([$studentId, $toYearId]);
        $destinationEnrollment = $destinationEnrollmentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$destinationEnrollment) {
            throw new Exception('Unable to resolve destination enrollment for student');
        }
        $destinationEnrollmentId = (int) ($destinationEnrollment['id'] ?? $destinationEnrollmentId);

        $updateSourceStmt = $this->db->prepare("
            UPDATE class_enrollments
            SET promoted_to_class_id = ?,
                promoted_to_stream_id = ?,
                promotion_status = 'promoted',
                promotion_date = CURDATE(),
                enrollment_status = CASE
                    WHEN enrollment_status IN ('pending', 'active') THEN 'completed'
                    ELSE enrollment_status
                END,
                completed_at = CASE WHEN completed_at IS NULL THEN NOW() ELSE completed_at END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateSourceStmt->execute([
            $toClassId,
            $toStreamId,
            (int) $sourceEnrollment['id']
        ]);

        $activateDestinationStmt = $this->db->prepare("
            UPDATE class_enrollments
            SET enrollment_status = 'active',
                updated_at = NOW()
            WHERE id = ?
        ");
        $activateDestinationStmt->execute([$destinationEnrollmentId]);

        $updateStudentStmt = $this->db->prepare("
            UPDATE students
            SET stream_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $updateStudentStmt->execute([$toStreamId, $studentId]);

        $this->generateStudentFeeObligationsForCurrentYear($studentId, $toYearId);

        $fromTermId = $this->getCurrentTermId($fromAcademicYear);
        if (!$fromTermId) {
            throw new Exception('No term is configured for the source academic year');
        }

        $promotionStmt = $this->db->prepare("
            INSERT INTO student_promotions (
                batch_id,
                from_enrollment_id,
                to_enrollment_id,
                from_academic_year_id,
                to_academic_year_id,
                student_id,
                current_class_id,
                current_stream_id,
                promoted_to_class_id,
                promoted_to_stream_id,
                from_academic_year,
                to_academic_year,
                from_term_id,
                promotion_status,
                promotion_reason,
                approved_by,
                approval_date,
                approval_notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                batch_id = VALUES(batch_id),
                from_enrollment_id = VALUES(from_enrollment_id),
                to_enrollment_id = VALUES(to_enrollment_id),
                current_class_id = VALUES(current_class_id),
                current_stream_id = VALUES(current_stream_id),
                promoted_to_class_id = VALUES(promoted_to_class_id),
                promoted_to_stream_id = VALUES(promoted_to_stream_id),
                promotion_status = 'approved',
                promotion_reason = VALUES(promotion_reason),
                approved_by = VALUES(approved_by),
                approval_date = NOW(),
                approval_notes = VALUES(approval_notes),
                updated_at = NOW()
        ");
        $promotionStmt->execute([
            $batchId,
            (int) $sourceEnrollment['id'],
            $destinationEnrollmentId,
            $fromYearId,
            $toYearId,
            $studentId,
            (int) $sourceEnrollment['class_id'],
            (int) $sourceEnrollment['stream_id'],
            $toClassId,
            $toStreamId,
            $fromAcademicYear,
            $toAcademicYear,
            $fromTermId,
            $reason,
            $performedBy,
            $reason
        ]);

        $promotionId = (int) $this->db->lastInsertId();
        if ($promotionId <= 0) {
            $promotionId = (int) ($this->lookupPromotionId($studentId, $fromAcademicYear, $toAcademicYear) ?? 0);
        }

        return [
            'promotion_id' => $promotionId,
            'student_id' => $studentId,
            'from_enrollment_id' => (int) $sourceEnrollment['id'],
            'to_enrollment_id' => $destinationEnrollmentId,
            'from_class_id' => (int) $sourceEnrollment['class_id'],
            'from_stream_id' => (int) $sourceEnrollment['stream_id'],
            'to_class_id' => $toClassId,
            'to_stream_id' => $toStreamId,
            'from_year_id' => $fromYearId,
            'to_year_id' => $toYearId
        ];
    }

    /**
     * SCENARIO 1: Promote single student
     */
    public function promoteSingleStudent($data)
    {
        $required = ['student_id', 'to_class_id', 'to_stream_id', 'from_year_id', 'to_year_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $studentId = (int) $data['student_id'];
        $toClassId = (int) $data['to_class_id'];
        $toStreamId = (int) $data['to_stream_id'];
        $fromYearId = (int) $data['from_year_id'];
        $toYearId = (int) $data['to_year_id'];
        $performedBy = (int) ($this->getCurrentUserId() ?? $this->resolvePromotionBatchCreatorId());
        $remarks = $data['remarks'] ?? null;

        $batchId = 0;
        try {
            $fromYearRecord = $this->getAcademicYearRecordById($fromYearId);
            $toYearRecord = $this->getAcademicYearRecordById($toYearId);
            $fromAcademicYear = $this->extractAcademicYearNumber($fromYearRecord);
            $toAcademicYear = $this->extractAcademicYearNumber($toYearRecord);
            if (!$fromAcademicYear || !$toAcademicYear) {
                throw new Exception('Invalid academic year values for promotion');
            }

            $batchId = $this->createPromotionBatchRecord(
                $fromAcademicYear,
                $toAcademicYear,
                'manual',
                "student:{$studentId}",
                $remarks
            );

            $this->db->beginTransaction();
            $promotion = $this->promoteStudentBetweenAcademicYears(
                $studentId,
                $toClassId,
                $toStreamId,
                $fromYearId,
                $toYearId,
                $performedBy,
                $remarks,
                $batchId
            );
            $this->closePromotionBatchRecord($batchId, 1, 1, 0, 'completed');
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Student promoted successfully',
                'data' => [
                    'batch_id' => $batchId,
                    'promotion' => $promotion
                ]
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($batchId > 0) {
                $this->closePromotionBatchRecord($batchId, 1, 0, 1, 'cancelled', $e->getMessage());
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * SCENARIO 2: Promote multiple students to same class
     */
    public function promoteMultipleStudents($data)
    {
        $required = ['student_ids', 'to_class_id', 'to_stream_id', 'from_year_id', 'to_year_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $studentIds = array_values(array_unique(array_filter(array_map('intval', (array) $data['student_ids']))));
        if (empty($studentIds)) {
            return ['success' => false, 'message' => 'student_ids must contain at least one student'];
        }

        $toClassId = (int) $data['to_class_id'];
        $toStreamId = (int) $data['to_stream_id'];
        $fromYearId = (int) $data['from_year_id'];
        $toYearId = (int) $data['to_year_id'];
        $performedBy = (int) ($this->getCurrentUserId() ?? $this->resolvePromotionBatchCreatorId());
        $remarks = $data['remarks'] ?? null;

        $batchId = 0;
        try {
            $fromYearRecord = $this->getAcademicYearRecordById($fromYearId);
            $toYearRecord = $this->getAcademicYearRecordById($toYearId);
            $fromAcademicYear = $this->extractAcademicYearNumber($fromYearRecord);
            $toAcademicYear = $this->extractAcademicYearNumber($toYearRecord);
            if (!$fromAcademicYear || !$toAcademicYear) {
                throw new Exception('Invalid academic year values for promotion');
            }

            $batchId = $this->createPromotionBatchRecord(
                $fromAcademicYear,
                $toAcademicYear,
                'single_class',
                "stream:{$toStreamId}",
                $remarks
            );

            $results = [
                'total' => count($studentIds),
                'promoted' => 0,
                'failed' => 0,
                'errors' => [],
                'records' => []
            ];

            foreach ($studentIds as $studentId) {
                try {
                    $this->db->beginTransaction();
                    $record = $this->promoteStudentBetweenAcademicYears(
                        $studentId,
                        $toClassId,
                        $toStreamId,
                        $fromYearId,
                        $toYearId,
                        $performedBy,
                        $remarks,
                        $batchId
                    );
                    $this->db->commit();

                    $results['promoted']++;
                    $results['records'][] = $record;
                } catch (Exception $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $results['failed']++;
                    $results['errors'][] = [
                        'student_id' => $studentId,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $batchStatus = $results['promoted'] > 0 ? 'completed' : 'cancelled';
            $this->closePromotionBatchRecord(
                $batchId,
                $results['total'],
                $results['promoted'],
                $results['failed'],
                $batchStatus
            );

            if ($results['promoted'] === 0) {
                return [
                    'success' => false,
                    'message' => 'No students were promoted',
                    'data' => [
                        'batch_id' => $batchId,
                        'results' => $results
                    ]
                ];
            }

            $message = $results['failed'] > 0
                ? "Promotion completed with {$results['failed']} errors"
                : 'Students promoted successfully';

            return [
                'success' => true,
                'message' => $message,
                'data' => [
                    'batch_id' => $batchId,
                    'results' => $results
                ]
            ];
        } catch (Exception $e) {
            if ($batchId > 0) {
                $this->closePromotionBatchRecord($batchId, count($studentIds), 0, count($studentIds), 'cancelled', $e->getMessage());
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * SCENARIO 3: Promote entire class with teacher/room assignment
     */
    public function promoteEntireClass($data)
    {
        $required = ['from_class_id', 'from_stream_id', 'to_class_id', 'to_stream_id', 'from_year_id', 'to_year_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $fromClassId = (int) $data['from_class_id'];
        $fromStreamId = (int) $data['from_stream_id'];
        $fromYearId = (int) $data['from_year_id'];

        $stmt = $this->db->prepare("
            SELECT ce.student_id
            FROM class_enrollments ce
            JOIN students s ON s.id = ce.student_id
            WHERE ce.class_id = ?
              AND ce.stream_id = ?
              AND ce.academic_year_id = ?
              AND ce.enrollment_status IN ('pending', 'active')
              AND s.status != 'transferred'
            ORDER BY ce.student_id ASC
        ");
        $stmt->execute([$fromClassId, $fromStreamId, $fromYearId]);
        $studentIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));

        if (empty($studentIds)) {
            $fallback = $this->db->prepare("
                SELECT id
                FROM students
                WHERE stream_id = ? AND status = 'active'
                ORDER BY id ASC
            ");
            $fallback->execute([$fromStreamId]);
            $studentIds = array_map('intval', array_column($fallback->fetchAll(PDO::FETCH_ASSOC), 'id'));
        }

        if (empty($studentIds)) {
            return ['success' => false, 'message' => 'No students found in the selected class/stream'];
        }

        return $this->promoteMultipleStudents([
            'student_ids' => $studentIds,
            'to_class_id' => (int) $data['to_class_id'],
            'to_stream_id' => (int) $data['to_stream_id'],
            'from_year_id' => (int) $data['from_year_id'],
            'to_year_id' => (int) $data['to_year_id'],
            'remarks' => $data['remarks'] ?? null
        ]);
    }

    /**
     * SCENARIO 4: Bulk promote multiple classes (whole school)
     */
    public function promoteMultipleClasses($data)
    {
        if (empty($data['class_map']) || !is_array($data['class_map'])) {
            return ['success' => false, 'message' => 'class_map must be provided'];
        }
        if (empty($data['from_year_id']) || empty($data['to_year_id'])) {
            return ['success' => false, 'message' => 'from_year_id and to_year_id are required'];
        }

        $summary = [
            'classes_processed' => 0,
            'classes_failed' => 0,
            'students_promoted' => 0,
            'students_failed' => 0,
            'class_results' => []
        ];

        foreach ($data['class_map'] as $mapping) {
            $payload = [
                'from_class_id' => (int) ($mapping['from_class_id'] ?? $mapping['from_class'] ?? 0),
                'from_stream_id' => (int) ($mapping['from_stream_id'] ?? $mapping['from_stream'] ?? 0),
                'to_class_id' => (int) ($mapping['to_class_id'] ?? $mapping['to_class'] ?? 0),
                'to_stream_id' => (int) ($mapping['to_stream_id'] ?? $mapping['to_stream'] ?? 0),
                'from_year_id' => (int) $data['from_year_id'],
                'to_year_id' => (int) $data['to_year_id'],
                'remarks' => $mapping['remarks'] ?? $data['remarks'] ?? null
            ];

            $result = $this->promoteEntireClass($payload);
            $summary['classes_processed']++;

            if (!empty($result['success'])) {
                $results = $result['data']['results'] ?? [];
                $summary['students_promoted'] += (int) ($results['promoted'] ?? 0);
                $summary['students_failed'] += (int) ($results['failed'] ?? 0);
            } else {
                $summary['classes_failed']++;
            }

            $summary['class_results'][] = [
                'mapping' => $payload,
                'result' => $result
            ];
        }

        if ($summary['students_promoted'] === 0) {
            return [
                'success' => false,
                'message' => 'No classes were successfully promoted',
                'data' => $summary
            ];
        }

        $message = $summary['classes_failed'] > 0
            ? 'Bulk promotion completed with some class failures'
            : 'Bulk promotion completed successfully';

        return [
            'success' => true,
            'message' => $message,
            'data' => $summary
        ];
    }

    /**
     * SCENARIO 5: Graduate Grade 9 students to alumni
     */
    public function graduateGrade9Students($data)
    {
        $required = ['class_id', 'stream_id', 'academic_year_id'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $classId = (int) $data['class_id'];
        $streamId = (int) $data['stream_id'];
        $yearId = (int) $data['academic_year_id'];
        $graduationData = (array) ($data['graduation_data'] ?? []);
        $performedBy = (int) ($this->getCurrentUserId() ?? $this->resolvePromotionBatchCreatorId());

        $yearRecord = $this->getAcademicYearRecordById($yearId);
        $academicYear = $this->extractAcademicYearNumber($yearRecord);
        if (!$academicYear) {
            return ['success' => false, 'message' => 'Invalid academic year selected'];
        }

        $batchId = $this->createPromotionBatchRecord(
            $academicYear,
            $academicYear,
            'single_class',
            "graduation:class={$classId},stream={$streamId}",
            $graduationData['notes'] ?? null
        );

        try {
            $fromTermId = $this->getCurrentTermId($academicYear);
            if (!$fromTermId) {
                throw new Exception('No academic term found for graduation year');
            }

            $studentsStmt = $this->db->prepare("
                SELECT ce.id AS enrollment_id,
                       ce.student_id,
                       ce.year_average,
                       ce.overall_grade,
                       ce.class_rank,
                       ce.stream_rank
                FROM class_enrollments ce
                JOIN students s ON s.id = ce.student_id
                WHERE ce.class_id = ?
                  AND ce.stream_id = ?
                  AND ce.academic_year_id = ?
                  AND ce.enrollment_status IN ('pending', 'active')
                  AND s.status != 'transferred'
                ORDER BY ce.student_id ASC
            ");
            $studentsStmt->execute([$classId, $streamId, $yearId]);
            $rows = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->closePromotionBatchRecord($batchId, 0, 0, 0, 'cancelled', 'No students found');
                return ['success' => false, 'message' => 'No students found for graduation'];
            }

            $this->db->beginTransaction();

            $graduatedCount = 0;
            foreach ($rows as $row) {
                $studentId = (int) $row['student_id'];
                $enrollmentId = (int) $row['enrollment_id'];

                $updateEnrollment = $this->db->prepare("
                    UPDATE class_enrollments
                    SET enrollment_status = 'graduated',
                        promotion_status = 'graduated',
                        promotion_date = CURDATE(),
                        completed_at = CASE WHEN completed_at IS NULL THEN NOW() ELSE completed_at END,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateEnrollment->execute([$enrollmentId]);

                $updateStudent = $this->db->prepare("
                    UPDATE students
                    SET status = 'graduated', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStudent->execute([$studentId]);

                $alumniExists = $this->db->prepare("
                    SELECT id
                    FROM alumni
                    WHERE student_id = ? AND graduation_year = ?
                    LIMIT 1
                ");
                $alumniExists->execute([$studentId, $academicYear]);
                if (!$alumniExists->fetchColumn()) {
                    $insertAlumni = $this->db->prepare("
                        INSERT INTO alumni (
                            student_id,
                            graduation_year,
                            graduated_class_id,
                            graduated_stream_id,
                            final_enrollment_id,
                            final_average,
                            final_grade,
                            final_class_rank,
                            final_stream_rank,
                            awards,
                            achievements,
                            next_school,
                            graduation_date,
                            created_at,
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insertAlumni->execute([
                        $studentId,
                        $academicYear,
                        $classId,
                        $streamId,
                        $enrollmentId,
                        $row['year_average'] !== null ? (float) $row['year_average'] : null,
                        $row['overall_grade'] ?? null,
                        $row['class_rank'] !== null ? (int) $row['class_rank'] : null,
                        $row['stream_rank'] !== null ? (int) $row['stream_rank'] : null,
                        $graduationData['awards'][$studentId] ?? null,
                        $graduationData['achievements'][$studentId] ?? null,
                        $graduationData['next_school'][$studentId] ?? null,
                        $graduationData['graduation_date'] ?? date('Y-m-d')
                    ]);
                }

                $reason = trim((string) ($graduationData['reason'] ?? 'Completed Grade 9'));
                $promotionStmt = $this->db->prepare("
                    INSERT INTO student_promotions (
                        batch_id,
                        from_enrollment_id,
                        to_enrollment_id,
                        from_academic_year_id,
                        to_academic_year_id,
                        student_id,
                        current_class_id,
                        current_stream_id,
                        promoted_to_class_id,
                        promoted_to_stream_id,
                        from_academic_year,
                        to_academic_year,
                        from_term_id,
                        promotion_status,
                        promotion_reason,
                        approved_by,
                        approval_date,
                        approval_notes
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?, 'graduated', ?, ?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE
                        batch_id = VALUES(batch_id),
                        from_enrollment_id = VALUES(from_enrollment_id),
                        to_enrollment_id = VALUES(to_enrollment_id),
                        promotion_status = 'graduated',
                        promotion_reason = VALUES(promotion_reason),
                        approved_by = VALUES(approved_by),
                        approval_date = NOW(),
                        approval_notes = VALUES(approval_notes),
                        updated_at = NOW()
                ");
                $promotionStmt->execute([
                    $batchId,
                    $enrollmentId,
                    $enrollmentId,
                    $yearId,
                    $yearId,
                    $studentId,
                    $classId,
                    $streamId,
                    $academicYear,
                    $academicYear,
                    $fromTermId,
                    $reason,
                    $performedBy,
                    $reason
                ]);

                $graduatedCount++;
            }

            $this->closePromotionBatchRecord($batchId, count($rows), $graduatedCount, 0, 'completed');
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Graduation processed successfully',
                'data' => [
                    'batch_id' => $batchId,
                    'total' => count($rows),
                    'graduated' => $graduatedCount
                ]
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->closePromotionBatchRecord($batchId, 0, 0, 0, 'cancelled', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get promotion batches
     */
    public function getPromotionBatches($filters = [])
    {
        try {
            $sql = "SELECT * FROM promotion_batches WHERE 1=1";
            $params = [];

            $fromYear = $filters['from_academic_year'] ?? $filters['academic_year_from'] ?? null;
            if (!empty($fromYear)) {
                $sql .= " AND from_academic_year = ?";
                $params[] = $fromYear;
            }

            $toYear = $filters['to_academic_year'] ?? $filters['academic_year_to'] ?? null;
            if (!empty($toYear)) {
                $sql .= " AND to_academic_year = ?";
                $params[] = $toYear;
            }

            $batchType = $filters['batch_type'] ?? $filters['promotion_type'] ?? null;
            if (!empty($batchType)) {
                $sql .= " AND batch_type = ?";
                $params[] = $batchType;
            }

            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $rows,
                'message' => 'Promotion batches fetched successfully'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get alumni (graduated students)
     */
    public function getAlumni($filters = [])
    {
        try {
            $sql = "
                SELECT a.*,
                       s.first_name,
                       s.middle_name,
                       s.last_name,
                       s.admission_no,
                       c.name AS class_name,
                       cs.stream_name
                FROM alumni a
                JOIN students s ON s.id = a.student_id
                LEFT JOIN classes c ON c.id = a.graduated_class_id
                LEFT JOIN class_streams cs ON cs.id = a.graduated_stream_id
                WHERE 1 = 1
            ";
            $params = [];

            $graduationYear = $filters['graduation_year'] ?? null;
            if (!empty($filters['academic_year_id']) && !$graduationYear) {
                $yearRecord = $this->getAcademicYearRecordById((int) $filters['academic_year_id']);
                $graduationYear = $this->extractAcademicYearNumber($yearRecord);
            }
            if (!empty($graduationYear)) {
                $sql .= " AND a.graduation_year = ?";
                $params[] = (int) $graduationYear;
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND a.graduated_class_id = ?";
                $params[] = (int) $filters['class_id'];
            }

            $sql .= " ORDER BY a.graduation_year DESC, a.graduation_date DESC, s.last_name ASC, s.first_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $rows,
                'message' => 'Alumni fetched successfully'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get current enrollments for an academic year
     */
    public function getCurrentEnrollments($yearId = null)
    {
        if (!$yearId) {
            $currentYear = $this->yearManager->getCurrentAcademicYear();
            $yearId = $currentYear['id'] ?? null;
        }

        $sql = "SELECT * FROM vw_current_enrollments WHERE academic_year_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get class roster for specific year
     */
    public function getClassRoster($classId, $streamId, $yearId = null)
    {
        if (!$yearId) {
            $currentYear = $this->yearManager->getCurrentAcademicYear();
            $yearId = $currentYear['id'] ?? null;
        }

        $sql = "SELECT ce.*, s.first_name, s.middle_name, s.last_name, s.admission_no, s.gender
                FROM class_enrollments ce
                JOIN students s ON ce.student_id = s.id
                WHERE ce.class_id = ? AND ce.stream_id = ? AND ce.academic_year_id = ?
                AND ce.enrollment_status IN ('pending', 'active')
                ORDER BY s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $streamId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
