<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Modules\academic\AcademicYearManager;
use App\API\Modules\students\PromotionManager;
use App\API\Services\AdmissionNumberService;
use App\API\Services\FieldCleaner;
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
        $this->idCardGenerator = $this->contract('App\API\Modules\students\StudentIDCardGenerator');
        $this->yearManager = $this->contract('App\API\Modules\academic\AcademicYearManager', $this->db);
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
                    $scopeClauses[] = "aycs.id IN ($placeholders)";
                    $bindings = array_merge($bindings, $visibilityScope['stream_ids']);
                }

                if (!empty($visibilityScope['class_ids'])) {
                    $placeholders = implode(',', array_fill(0, count($visibilityScope['class_ids']), '?'));
                    $scopeClauses[] = "ayc.class_id IN ($placeholders)";
                    $bindings = array_merge($bindings, $visibilityScope['class_ids']);
                }

                if (empty($scopeClauses)) {
                    $conditions[] = "1 = 0";
                } else {
                    $conditions[] = '(' . implode(' OR ', $scopeClauses) . ')';
                }
            }

            if (!empty($search)) {
                $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm]);
            }

            // Optional filters
            $classId = $params['class_id'] ?? $_GET['class_id'] ?? null;
            if (!empty($classId)) {
                $conditions[] = "ayc.class_id = ?";
                $bindings[] = $classId;
            }

            $streamId = $params['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!empty($streamId)) {
                $conditions[] = "aycs.id = ?";
                $bindings[] = $streamId;
            }

            $status = $params['status'] ?? $_GET['status'] ?? null;
            if (!empty($status)) {
                $conditions[] = "s.status = ?";
                $bindings[] = $status;
            }

            $gender = $params['gender'] ?? $_GET['gender'] ?? null;
            if (!empty($gender)) {
                $conditions[] = "per.gender = ?";
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
                $feeSummaryWhere = "WHERE CAST(SUBSTRING(academic_year, 1, 4) AS UNSIGNED) = ?";
                $joinBindings[] = $currentAcademicYear;
            }

            $joins = "
                LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    AND sae.id = (SELECT MAX(sae_latest.id) FROM student_academic_enrollments sae_latest WHERE sae_latest.student_id = s.id AND sae_latest.enrollment_status = 'active')
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams cs ON cs.id = aycs.stream_id
                LEFT JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN (
                    SELECT
                        student_id,
                        SUM(amount_due) AS total_due,
                        SUM(amount_paid) AS total_paid,
                        SUM(amount_waived) AS total_waived,
                        SUM(balance) AS total_balance,
                        MIN(CASE WHEN balance > 0 THEN latest_due_date END) AS earliest_balance_due
                    FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . "
                    {$feeSummaryWhere}
                    GROUP BY student_id
                ) fee_summary ON fee_summary.student_id = s.id
            ";

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM students s
                JOIN persons per ON per.id = s.person_id
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
                    ayc.class_id as class_id,
                    c.name as class_name,
                    cs.name AS stream_name,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                    per.first_name AS first_name,
                    per.middle_name AS middle_name,
                    per.last_name AS last_name,
                    per.gender,
                    per.dob AS date_of_birth,
                    per.photo_url,
                    st.name AS student_type_name,
                    st.name AS student_type,
                    st.code AS student_type_code,
                    CASE
                        WHEN st.code = 'BOARD' THEN 'boarding'
                        ELSE 'day'
                    END AS boarding_status,
                    COALESCE(fee_summary.total_due, 0) AS total_fees,
                    COALESCE(fee_summary.total_paid, 0) AS total_paid,
                    COALESCE(fee_summary.total_balance, 0) AS fee_balance,
                    (
                        SELECT CONCAT_WS(' ', parp.first_name, parp.middle_name, parp.last_name)
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        JOIN persons parp ON parp.id = par.person_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS parent_name,
                    (
                        SELECT parp.phone
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        JOIN persons parp ON parp.id = par.person_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS parent_phone,
                    (
                        SELECT parp.phone
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        JOIN persons parp ON parp.id = par.person_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS guardian_contact,
                    (
                        SELECT parp.email
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        JOIN persons parp ON parp.id = par.person_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS parent_email,
                    (
                        SELECT parp.email
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        JOIN persons parp ON parp.id = par.person_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS guardian_email,
                    (
                        SELECT par.address
                        FROM student_parents sp
                        JOIN parents par ON par.id = sp.parent_id
                        WHERE sp.student_id = s.id
                        ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC
                        LIMIT 1
                    ) AS parent_address
                FROM students s
                JOIN persons per ON per.id = s.person_id
                {$joins}
                $where
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($joinBindings, $bindings, [$limit, $offset]));
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($students as &$student) {
                $student['photo_url'] = $this->normalizePublicAssetPath($student['photo_url'] ?? '');
            }
            unset($student);

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

    /**
     * Return the authoritative context used by the existing-learner import
     * form.  The import screen must not reproduce fee amounts or academic
     * context in JavaScript; those values belong to the configured database.
     */
    public function getExistingStudentImportContext(): array
    {
        $year = $this->db->query(
            "SELECT id, year_code, start_date, end_date
             FROM academic_years
             WHERE is_current = 1
             ORDER BY id DESC
             LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: null;

        $transportPeriods = [
            ['code' => 'day', 'label' => 'Specific day'],
            ['code' => 'week', 'label' => 'Specific week'],
            ['code' => 'month', 'label' => 'Specific month'],
            ['code' => 'term', 'label' => 'School term'],
            ['code' => 'year', 'label' => 'School year'],
            ['code' => 'custom', 'label' => 'Custom dates'],
        ];

        $transportRoutes = $this->db->query(
            "SELECT id, name, code, status
             FROM transport_routes
             WHERE status = 'active'
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $transportStops = $this->db->query(
            "SELECT id, route_id, name, sequence, status
             FROM transport_stops
             WHERE status = 'active'
             ORDER BY route_id, sequence, name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $scholarshipPrograms = $this->db->query(
            "SELECT id, code, name, coverage_type, default_percentage,
                    default_amount, description
             FROM scholarship_programs
             WHERE is_active = 1
             ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $waiverTypes = ['none'];
        $waiverColumn = $this->db->query(
            "SHOW COLUMNS FROM fee_discounts_waivers LIKE 'discount_type'"
        )->fetch(PDO::FETCH_ASSOC);
        if (!empty($waiverColumn['Type']) && preg_match("/^enum\\((.*)\\)$/", $waiverColumn['Type'], $matches)) {
            $waiverTypes = array_merge(
                ['none'],
                array_values(array_filter(array_map(
                    static fn(string $value): string => trim($value, "'"),
                    str_getcsv($matches[1], ',', "'", '\\')
                )))
            );
        }

        if (!$year) {
            return [
                'academic_year' => null,
                'current_term' => null,
                'academic_year_terms' => [],
                'fee_schedules' => [],
                'transport_routes' => $transportRoutes,
                'transport_stops' => $transportStops,
                'transport_periods' => $transportPeriods,
                'scholarship_programs' => $scholarshipPrograms,
                'fee_waiver_types' => $waiverTypes,
            ];
        }

        $termStmt = $this->db->prepare(
            "SELECT ayt.id, t.id AS term_number, t.code, t.name
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
               AND (
                   ayt.status = 'current'
                   OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date
               )
             ORDER BY CASE WHEN ayt.status = 'current' THEN 0 ELSE 1 END, t.id
             LIMIT 1"
        );
        $termStmt->execute([(int) $year['id']]);
        $term = $termStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $termsStmt = $this->db->prepare(
            "SELECT ayt.id, ayt.term_id, ayt.opening_date, ayt.closing_date,
                    t.code, t.name
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY t.id"
        );
        $termsStmt->execute([(int) $year['id']]);

        $scheduleStmt = $this->db->prepare(
            "SELECT
                afs.academic_year_id,
                afs.academic_year_term_id,
                t.id AS term_number,
                t.code AS term_code,
                t.name AS term_name,
                ayc.class_id,
                c.name AS class_name,
                afs.student_type_id,
                st.code AS student_type_code,
                st.name AS student_type_name,
                fc.code AS fee_code,
                fc.name AS fee_name,
                afs.amount
             FROM academic_year_fee_schedules afs
             JOIN academic_years ay ON ay.id = afs.academic_year_id
             LEFT JOIN academic_year_terms ayt ON ayt.id = afs.academic_year_term_id
             LEFT JOIN terms t ON t.id = ayt.term_id
             LEFT JOIN academic_year_classes ayc ON ayc.id = afs.academic_year_class_id
             LEFT JOIN classes c ON c.id = ayc.class_id
             LEFT JOIN student_types st ON st.id = afs.student_type_id
             JOIN fee_catalog fc ON fc.id = afs.fee_catalog_id
             WHERE afs.academic_year_id = ?
               AND afs.status = 'active'
             ORDER BY ayc.class_id, afs.student_type_id, t.id, fc.code"
        );
        $scheduleStmt->execute([(int) $year['id']]);

        return [
            'academic_year' => $year,
            'current_term' => $term,
            'academic_year_terms' => $termsStmt->fetchAll(PDO::FETCH_ASSOC),
            'fee_schedules' => $scheduleStmt->fetchAll(PDO::FETCH_ASSOC),
            'transport_routes' => $transportRoutes,
            'transport_stops' => $transportStops,
            'transport_periods' => $transportPeriods,
            'scholarship_programs' => $scholarshipPrograms,
            'fee_waiver_types' => $waiverTypes,
        ];
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

            $student['learning_areas'] = $this->getCurrentStudentLearningAreas((int) $id);
            $student['active_sponsorships'] = $this->getCurrentStudentSponsorships((int) $id);
            $student['transport_arrangements'] = $this->getCurrentStudentTransport((int) $id);
            $student['parents'] = $this->getStudentParents((int) $id);
            $student['fee_waiver'] = $this->getCurrentStudentFeeWaiver((int) $id);
            $student['financial_migration'] = $this->getExistingStudentMigrationPosition(
                (int) $id,
                $this->getCurrentAcademicYearValue()
            );

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

        // Auth payloads from the refresh-token flow also expose the primary
        // role as role_name rather than roles[].
        if (!empty($user['role_name'])) {
            $roleNames[] = $this->normalizeRoleName((string) $user['role_name']);
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
            'school_accountant',
            'accountant',
            'bursar',
            'finance_manager',
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
            SELECT sae.academic_year_class_stream_id AS stream_id, ayc.class_id
            FROM students s
            LEFT JOIN student_academic_enrollments sae 
                ON sae.student_id = s.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
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
            $conditions[] = 'LOWER(pp.email) = ?';
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
            $conditions[] = 'pp.phone = ?';
            $bindings[] = $phone;
        }

        if (empty($conditions)) {
            $firstName = strtolower(trim((string) ($user['first_name'] ?? '')));
            $lastName = strtolower(trim((string) ($user['last_name'] ?? '')));
            if ($firstName !== '' && $lastName !== '') {
                $conditions[] = '(LOWER(pp.first_name) = ? AND LOWER(pp.last_name) = ?)';
                $bindings[] = $firstName;
                $bindings[] = $lastName;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT DISTINCT p.id
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
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

        // staff is linked to users through person_id; staff has no user_id
        // column in the normalized schema.
        $stmt = $this->db->prepare(
            "SELECT s.id
             FROM staff s
             JOIN users u ON u.person_id = s.person_id
             WHERE u.id = ? AND s.status = 'active'
             LIMIT 1"
        );
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
                SELECT DISTINCT ayc.class_id, v.academic_year_class_stream_id AS stream_id
                FROM vw_teacher_effective_stream_learning_areas v
                JOIN academic_year_class_streams aycs ON aycs.id = v.academic_year_class_stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE v.staff_id = ?
                  AND v.academic_year_id = ?
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
                SELECT DISTINCT aycs.id AS stream_id, ayc.class_id
                FROM academic_year_class_streams aycs
                JOIN streams sm ON sm.id = aycs.stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE EXISTS (
                    SELECT 1 FROM vw_teacher_effective_stream_learning_areas tscope
                    WHERE tscope.staff_id = ?
                      AND tscope.academic_year_class_stream_id = aycs.id
                      AND tscope.scope_type = 'class_teacher'
                )
                  AND aycs.status = 'active'
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
                SELECT ayt.id
                FROM academic_year_terms ayt
                JOIN academic_years ay ON ay.id = ayt.academic_year_id
                WHERE CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?
                  AND ayt.status = 'current'
                ORDER BY (SELECT code FROM terms WHERE id = ayt.term_id) ASC
                LIMIT 1
            ");
            $stmt->execute([$academicYear]);
            $termId = $stmt->fetchColumn();
            if ($termId) {
                return (int) $termId;
            }

            $stmt = $this->db->prepare("
                SELECT ayt.id
                FROM academic_year_terms ayt
                JOIN academic_years ay ON ay.id = ayt.academic_year_id
                WHERE CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?
                ORDER BY (SELECT code FROM terms WHERE id = ayt.term_id) DESC
                LIMIT 1
            ");
            $stmt->execute([$academicYear]);
            $termId = $stmt->fetchColumn();
            if ($termId) {
                return (int) $termId;
            }
        }

        $stmt = $this->db->query("
            SELECT ayt.id
            FROM academic_year_terms ayt
            WHERE ayt.status = 'current'
            ORDER BY ayt.academic_year_id DESC,
                     (SELECT code FROM terms WHERE id = ayt.term_id) ASC
            LIMIT 1
        ");
        $termId = $stmt->fetchColumn();
        if ($termId) {
            return (int) $termId;
        }

        $stmt = $this->db->query("
            SELECT ayt.id
            FROM academic_year_terms ayt
            ORDER BY ayt.academic_year_id DESC,
                     (SELECT code FROM terms WHERE id = ayt.term_id) DESC
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
            ORDER BY is_primary_contact DESC, is_emergency_contact DESC, parent_id ASC
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

        $stmt = $this->db->prepare("
            INSERT INTO student_parents (
                student_id,
                parent_id,
                relationship,
                is_primary_contact,
                is_emergency_contact
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                relationship = VALUES(relationship),
                is_primary_contact = VALUES(is_primary_contact),
                is_emergency_contact = VALUES(is_emergency_contact)
        ");
        $stmt->execute([
            $studentId,
            $parentId,
            $relationship,
            $isPrimary,
            $isEmergency
        ]);

        if ($isPrimary) {
            $stmt = $this->db->prepare("
                UPDATE student_parents
                SET is_primary_contact = 0
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
                per.first_name AS first_name,
                per.middle_name AS middle_name,
                per.last_name AS last_name,
                per.gender,
                per.dob AS date_of_birth,
                per.photo_url,
                per.email,
                per.phone,
                (
                    SELECT sic.qr_code_path
                    FROM student_id_cards sic
                    WHERE sic.student_id = s.id
                      AND sic.qr_code_path IS NOT NULL
                    ORDER BY sic.id DESC
                    LIMIT 1
                ) AS qr_code_path,
                ayc.class_id as class_id,
                c.name as class_name,
                aycs.stream_id AS stream_id,
                aycs.id AS academic_year_class_stream_id,
                sm.name AS stream_name,
                CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                st.name AS student_type_name,
                st.name AS student_type,
                st.code AS student_type_code,
                CASE
                    WHEN st.code = 'BOARD' THEN 'boarding'
                        ELSE 'day'
                END AS boarding_status,
                (
                    SELECT CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name)
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    JOIN persons pp ON pp.id = p.person_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
                    LIMIT 1
                ) AS parent_name,
                (
                    SELECT pp.phone
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    JOIN persons pp ON pp.id = p.person_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
                    LIMIT 1
                ) AS parent_phone,
                (
                    SELECT pp.email
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    JOIN persons pp ON pp.id = p.person_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
                    LIMIT 1
                ) AS parent_email,
                (
                    SELECT p.occupation
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
                    LIMIT 1
                ) AS parent_occupation,
                (
                    SELECT p.address
                    FROM student_parents sp
                    JOIN parents p ON p.id = sp.parent_id
                    WHERE sp.student_id = s.id
                    ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
                    LIMIT 1
                ) AS parent_address
            FROM students s
            JOIN persons per ON per.id = s.person_id
            LEFT JOIN student_academic_enrollments sae 
                ON sae.student_id = s.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN streams sm ON sm.id = aycs.stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN student_types st ON s.student_type_id = st.id
            WHERE s.id = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCurrentStudentLearningAreas(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT la.id, la.name, la.code
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs
               ON aycs.id = sae.academic_year_class_stream_id
             JOIN academic_year_class_stream_learning_areas scla
               ON scla.academic_year_class_stream_id = aycs.id
              AND scla.status IN ('active', 'planned')
             JOIN academic_year_class_learning_areas acla
               ON acla.id = scla.academic_year_class_learning_area_id
              AND acla.status IN ('active', 'planned')
             JOIN learning_areas la ON la.id = acla.learning_area_id
             WHERE sae.student_id = ?
               AND sae.enrollment_status = 'active'
             ORDER BY la.name"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getCurrentStudentSponsorships(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ssa.scholarship_program_id, ssa.coverage_type, ssa.coverage_percentage, ssa.coverage_amount,
                    ssa.period_type, ssa.starts_on, ssa.ends_on, ssa.reason,
                    sp.name AS programme_name
             FROM student_scholarship_awards ssa
             JOIN scholarship_programs sp ON sp.id = ssa.scholarship_program_id
             WHERE ssa.student_id = ? AND ssa.status = 'active'
             ORDER BY ssa.id DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getCurrentStudentTransport(int $studentId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sta.route_id, sta.pickup_stop_id, sta.dropoff_stop_id, tr.name AS route_name, sta.status,
                    sta.month, sta.year, sta.expected_amount,
                    ps.name AS pickup_stop, ds.name AS dropoff_stop,
                    sta.pickup_time, sta.dropoff_time, sta.notes,
                    ep.period_type, ep.period_start, ep.period_end,
                    te.amount_due AS entitlement_amount
             FROM student_transport_assignments sta
             JOIN transport_routes tr ON tr.id = sta.route_id
             LEFT JOIN transport_stops ps ON ps.id = COALESCE(sta.pickup_stop_id, sta.stop_id)
             LEFT JOIN transport_stops ds ON ds.id = sta.dropoff_stop_id
             LEFT JOIN student_transport_entitlements te
               ON te.assignment_id = sta.id AND te.entitlement_status IN ('active', 'suspended')
             LEFT JOIN transport_entitlement_periods ep ON ep.id = te.period_id
             WHERE sta.student_id = ? AND sta.status IN ('active', 'suspended')
             ORDER BY sta.year DESC, sta.month DESC, sta.id DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getCurrentStudentFeeWaiver(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT fdw.discount_type, fdw.discount_value, fdw.discount_percentage,
                    fdw.reason, fdw.academic_year, fdw.term_id
             FROM fee_discounts_waivers fdw
             WHERE fdw.student_id = ? AND fdw.status = 'active'
             ORDER BY fdw.id DESC LIMIT 1"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
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
            SELECT aycs.id, ayc.class_id, aycs.stream_id AS stream_id, sm.name AS stream_name
            FROM academic_year_class_streams aycs
            JOIN streams sm ON sm.id = aycs.stream_id
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE aycs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$streamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getClassAssignmentId(int $academicYearId, int $classId, int $streamId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT aycs.id
            FROM academic_year_class_streams aycs
            JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            WHERE ayc.academic_year_id = ?
              AND ayc.class_id = ?
              AND aycs.stream_id = ?
              AND aycs.status IN ('active', 'planning')
            ORDER BY aycs.status = 'active' DESC, aycs.id DESC
            LIMIT 1
        ");
        $stmt->execute([$academicYearId, $classId, $streamId]);
        $assignmentId = $stmt->fetchColumn();

        return $assignmentId ? (int) $assignmentId : null;
    }

    private function getActiveEnrollmentStreamId(int $studentId): int
    {
        $stmt = $this->db->prepare("
            SELECT aycs.id
            FROM student_academic_enrollments sae
            JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            WHERE sae.student_id = ?
              AND sae.enrollment_status = 'active'
            ORDER BY sae.id DESC
            LIMIT 1
        ");
        $stmt->execute([$studentId]);
        $streamId = $stmt->fetchColumn();

        return $streamId ? (int) $streamId : 0;
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
        $masterStreamId = (int) ($stream['stream_id'] ?? 0);
        $assignmentId = $this->getClassAssignmentId($academicYearId, $classId, $masterStreamId);
        if ($assignmentId === null) {
            $assignmentId = $streamId;
        }
        $enrollmentStatus = $this->mapStudentStatusToEnrollmentStatus($studentStatus);

        $existingStmt = $this->db->prepare("
            SELECT id, academic_year_class_stream_id
            FROM student_academic_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $existingStmt->execute([$studentId, $academicYearId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $updateStmt = $this->db->prepare("
                UPDATE student_academic_enrollments
                SET academic_year_class_stream_id = ?,
                    enrollment_status = ?,
                    enrolled_on = COALESCE(enrolled_on, ?)
                WHERE id = ?
            ");
            $updateStmt->execute([
                $assignmentId,
                $enrollmentStatus,
                date('Y-m-d'),
                (int) $existing['id']
            ]);

            return (int) $existing['id'];
        }

        $admissionDateStmt = $this->db->prepare("SELECT admission_date FROM students WHERE id = ? LIMIT 1");
        $admissionDateStmt->execute([$studentId]);
        $enrollmentDate = $admissionDateStmt->fetchColumn() ?: date('Y-m-d');

        $insertStmt = $this->db->prepare("
            INSERT INTO student_academic_enrollments (
                student_id,
                academic_year_id,
                academic_year_class_stream_id,
                enrolled_on,
                enrollment_status
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $insertStmt->execute([
            $studentId,
            $academicYearId,
            $assignmentId,
            $enrollmentDate,
            $enrollmentStatus
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function generateStudentFeeObligationsForCurrentYear(int $studentId, ?int $academicYearId = null, array $sponsorship = []): int
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
            SELECT s.student_type_id,
                   c.level_id AS level_id,
                   ayc.id AS academic_year_class_id
            FROM students s
            LEFT JOIN student_academic_enrollments sae 
                ON sae.student_id = s.id
               AND sae.academic_year_id = ?
               AND sae.enrollment_status IN ('active', 'pending')
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            WHERE s.id = ?
            ORDER BY sae.id DESC
            LIMIT 1
        ");
        $studentStmt->execute([$academicYearId, $studentId]);
        $studentMeta = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$studentMeta || empty($studentMeta['student_type_id']) || empty($studentMeta['academic_year_class_id'])) {
            return 0;
        }

        $termStmt = $this->db->prepare(
            "SELECT MIN(CAST(SUBSTRING(t.code, 2) AS UNSIGNED))
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
               AND ayt.status = 'current'"
        );
        $termStmt->execute([$academicYearId]);
        $startTermNumber = (int) ($termStmt->fetchColumn() ?: 0);
        if ($startTermNumber <= 0) {
            $termStmt = $this->db->prepare(
                "SELECT MIN(CAST(SUBSTRING(t.code, 2) AS UNSIGNED))
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE ayt.academic_year_id = ?
                   AND CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date"
            );
            $termStmt->execute([$academicYearId]);
            $startTermNumber = (int) ($termStmt->fetchColumn() ?: 1);
        }

        $structureStmt = $this->db->prepare("
            SELECT ayfs.id, ayfs.academic_year_term_id AS term_id, ayfs.amount,
                   ayt.opening_date AS term_opening_date, ayt.closing_date AS term_closing_date,
                   COALESCE(ayfs.due_date, ayt.closing_date) AS due_date
            FROM academic_year_fee_schedules ayfs
            JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
            JOIN terms t ON t.id = ayt.term_id
            WHERE ayfs.academic_year_class_id = ?
              AND ayfs.academic_year_id = ?
              AND ayfs.student_type_id = ?
              AND ayfs.status = 'active'
              AND CAST(SUBSTRING(t.code, 2) AS UNSIGNED) >= ?
            ORDER BY CAST(SUBSTRING(t.code, 2) AS UNSIGNED) ASC, ayfs.id ASC
        ");
        $structureStmt->execute([
            (int) $studentMeta['academic_year_class_id'],
            $academicYearId,
            (int) $studentMeta['student_type_id'],
            $startTermNumber
        ]);
        $feeStructures = $structureStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($feeStructures)) {
            return 0;
        }

        // Annual award overrides the legacy student-level sponsorship flag for
        // this academic year.  The award is deliberately resolved here so
        // newly generated obligations receive the same treatment as existing
        // obligations immediately.
        $awardStmt = $this->db->prepare(
            "SELECT coverage_type, coverage_percentage, coverage_amount, starts_on, ends_on
             FROM student_scholarship_awards
             WHERE student_id=? AND academic_year_id=? AND status='active'
             LIMIT 1"
        );
        $awardStmt->execute([$studentId, $academicYearId]);
        $annualAward = $awardStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $isSponsored = !empty($sponsorship['is_sponsored']);
        $waiverPercent = (float) ($sponsorship['sponsor_waiver_percentage'] ?? 0);
        $fixedAwardAmount = 0.0;
        $createdCount = 0;

        foreach ($feeStructures as $row) {
            $awardApplies = $annualAward &&
                (!$annualAward['starts_on'] || $row['term_closing_date'] >= $annualAward['starts_on']) &&
                (!$annualAward['ends_on'] || $row['term_opening_date'] <= $annualAward['ends_on']);
            $rowSponsored = $isSponsored || $awardApplies;
            $rowWaiverPercent = $waiverPercent;
            $rowFixedAwardAmount = 0.0;
            if ($awardApplies) {
                if ($annualAward['coverage_type'] === 'full') $rowWaiverPercent = 100.0;
                if ($annualAward['coverage_type'] === 'percentage') $rowWaiverPercent = (float)$annualAward['coverage_percentage'];
                if ($annualAward['coverage_type'] === 'fixed_amount') $rowFixedAwardAmount = (float)$annualAward['coverage_amount'];
            }
            $existsStmt = $this->db->prepare("
                SELECT sfo.id
                FROM student_fee_obligations sfo
                JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
                WHERE sae.student_id = ?
                  AND sfo.academic_year_fee_schedule_id = ?
                LIMIT 1
            ");
            $existsStmt->execute([
                $studentId,
                (int) $row['id']
            ]);

            if ($existsStmt->fetchColumn()) {
                continue;
            }

            $amountDue = (float) $row['amount'];
            $waivedAmount = $rowSponsored && $rowWaiverPercent > 0
                ? round($amountDue * ($rowWaiverPercent / 100), 2)
                : ($rowSponsored && $rowFixedAwardAmount > 0 ? $rowFixedAwardAmount : 0.0);
            $waivedAmount = min($waivedAmount, $amountDue);
            $netBalance = max(0, $amountDue - $waivedAmount);
            $status = $netBalance <= 0 ? 'paid' : 'pending';
            $dueDate = !empty($row['due_date']) ? $row['due_date'] : date('Y-m-d', strtotime('+30 days'));

            // Resolve active enrollment id for this student+year
            $enrStmt = $this->db->prepare("
                SELECT id FROM student_academic_enrollments
                WHERE student_id = ? AND academic_year_id = ? AND enrollment_status = 'active'
                LIMIT 1
            ");
            $enrStmt->execute([$studentId, $academicYearId]);
            $enrollmentId = $enrStmt->fetchColumn() ?: null;
            if (!$enrollmentId) {
                continue;
            }

            $insertStmt = $this->db->prepare("
                INSERT INTO student_fee_obligations (
                    student_academic_enrollment_id,
                    academic_year_id,
                    academic_year_term_id,
                    academic_year_fee_schedule_id,
                    amount_due,
                    status,
                    due_date,
                    is_sponsored,
                    sponsored_waiver_amount
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $enrollmentId,
                $academicYearId,
                (int) $row['term_id'],
                (int) $row['id'],
                $amountDue,
                $status,
                $dueDate,
                $isSponsored ? 1 : 0,
                $waivedAmount
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
            FROM student_academic_enrollments
            WHERE student_id = ? AND academic_year_id = ?
            LIMIT 1
        ");
        $enrollmentStmt->execute([$studentId, $academicYearId]);
        $enrollmentId = $enrollmentStmt->fetchColumn();

        $note = $reason ?: 'Internal class/stream transfer';
        $userId = $this->getCurrentUserId();

        $sql = "
            INSERT INTO student_transitions (
                student_id,
                from_student_academic_enrollment_id,
                to_student_academic_enrollment_id,
                academic_year_id,
                transition_type,
                reason,
                decided_by,
                decided_at,
                executed_at
            ) VALUES (?, ?, ?, ?, 'internal', ?, ?, NOW(), NOW())
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $studentId,
            $enrollmentId ?: null,
            $enrollmentId ?: null,
            $academicYearId,
            $note,
            $userId
        ]);

        return (int) $this->db->lastInsertId();
    }

    // Create new student
    public function create($data)
    {
        try {
            $required = ['first_name', 'last_name', 'stream_id', 'date_of_birth', 'gender', 'admission_date', 'parent_info'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // admission_no: optional. When absent it is auto-generated from the
            // configured admission_no_format; when present it must match that
            // format so manual creates stay consistent with the admission workflow.
            $admissionNoService = new AdmissionNumberService($this->db);
            $admissionNo = trim((string) ($data['admission_no'] ?? ''));
            if ($admissionNo !== '') {
                if (!$admissionNoService->isValid($admissionNo)) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Invalid admission_no. Expected format: ' . $admissionNoService->getFormat()
                    ], 400);
                }
            } else {
                $admissionYear = $this->getCurrentAcademicYearValue() ?? (int) date('Y', strtotime($data['admission_date']));
                $admissionNo = $admissionNoService->generate($admissionYear);
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

            // Clean person-level free-text fields before storage (letters-only
            // names, title-cased; DOB strictly in the past). Reject when a
            // required cleaned field comes back empty.
            $studentClean = FieldCleaner::clean($data, [
                'first_name' => 'name',
                'last_name' => 'name',
                'date_of_birth' => 'dob'
            ]);
            $studentCleanErrors = $studentClean['errors'];
            if (isset($studentCleanErrors['first_name'])) {
                $this->db->rollBack();
                return $this->response(['status' => 'error', 'message' => $studentCleanErrors['first_name']], 400);
            }
            if (isset($studentCleanErrors['last_name'])) {
                $this->db->rollBack();
                return $this->response(['status' => 'error', 'message' => $studentCleanErrors['last_name']], 400);
            }
            if (isset($studentCleanErrors['date_of_birth'])) {
                $this->db->rollBack();
                return $this->response(['status' => 'error', 'message' => $studentCleanErrors['date_of_birth']], 400);
            }
            $studentCleaned = $studentClean['cleaned'];

            // middle_name is optional: only clean when actually provided.
            $middleName = $data['middle_name'] ?? null;
            if ($middleName !== null && trim((string) $middleName) !== '') {
                $cleanedMiddle = FieldCleaner::cleanName((string) $middleName);
                if ($cleanedMiddle === null) {
                    $this->db->rollBack();
                    return $this->response(['status' => 'error', 'message' => 'Middle name must contain letters only (no digits or symbols)'], 400);
                }
                $middleName = $cleanedMiddle;
            }

            $personStmt = $this->db->prepare("
                INSERT INTO persons (first_name, middle_name, last_name, dob, gender, photo_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $personStmt->execute([
                $studentCleaned['first_name'],
                $middleName,
                $studentCleaned['last_name'],
                $studentCleaned['date_of_birth'],
                $data['gender'] ?? null,
                $data['photo_url'] ?? null
            ]);
            $newPersonId = (int) $this->db->lastInsertId();

            $sql = "
                INSERT INTO students (
                    person_id,
                    admission_no,
                    student_type_id,
                    admission_date,
                    assessment_number,
                    assessment_status,
                    nemis_number,
                    nemis_status,
                    status,
                    application_id,
                    entry_source,
                    blood_group
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newPersonId,
                $admissionNo,
                $data['student_type_id'] ?? null,
                $data['admission_date'] ?? null,
                $data['assessment_number'] ?? null,
                !empty($data['assessment_number']) ? 'assigned' : ($data['assessment_status'] ?? 'not_assigned'),
                $data['nemis_number'] ?? null,
                !empty($data['nemis_number']) ? 'assigned' : ($data['nemis_status'] ?? 'not_assigned'),
                $data['status'] ?? 'active',
                $data['application_id'] ?? null,
                !empty($data['application_id']) ? 'admission' : 'existing_student',
                $data['blood_group'] ?? null
            ]);

            $studentId = (int) $this->db->lastInsertId();

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
                    $feeObligationsCreated = $this->generateStudentFeeObligationsForCurrentYear((int) $studentId, null, [
                        'is_sponsored' => !empty($data['is_sponsored']),
                        'sponsor_waiver_percentage' => $data['sponsor_waiver_percentage'] ?? 0
                    ]);
                } catch (Exception $e) {
                    // Log but don't fail - enrollment and fees can be created later.
                    \App\API\Services\Logger::legacyError("Warning: Could not create enrollment/fees for student $studentId: " . $e->getMessage());
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
                    \App\API\Services\Logger::legacyError("Warning: Could not record initial payment for student $studentId: " . $e->getMessage());
                }
            }

            $this->logAction('create', $studentId, "Created new student: {$data['first_name']} {$data['last_name']}");

            // Enrollment onboarding may be performed by the database trigger
            // on student_academic_enrollments. Some deployments complete the
            // transaction inside that trigger/procedure, so do not call
            // commit() a second time when PDO is no longer in a transaction.
            if ($this->db->inTransaction()) {
                $this->db->commit();
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Student created successfully',
                'data' => [
                    'id' => $studentId,
                    'admission_no' => $admissionNo,
                    'enrollment_id' => $enrollmentId,
                    'fee_obligations_created' => $feeObligationsCreated
                ]
            ], 201);
        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->response([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
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

        $sql = "INSERT INTO payments (
            student_id, parent_id, amount, payment_date, method, reference,
            receipt_no, received_by, status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $studentId,
            $parentId,
            $paymentData['amount'],
            $paymentDate,
            $paymentMethod,
            $paymentData['reference'] ?? null,
            $receiptNo,
            $receivedBy,
            $paymentData['notes'] ?? 'Initial admission payment'
        ]);
        $paymentId = (int) $this->db->lastInsertId();

        if ($academicYear !== null && $termId !== null) {
            $this->refreshStudentPaymentSummary($studentId, $academicYear, $termId);
        }

        return $paymentId;
    }

    // Update student
    public function update($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, status FROM students WHERE id = ?");
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

            // Person-level fields (normalized into persons). Free-text fields
            // are cleaned before storage (letters-only names title-cased; DOB
            // strictly in the past).
            $personFieldMap = [
                'first_name' => 'first_name',
                'middle_name' => 'middle_name',
                'last_name' => 'last_name',
                'date_of_birth' => 'dob',
                'gender' => 'gender',
                'photo_url' => 'photo_url',
            ];
            $personUpdates = [];
            $personParams = [];
            foreach ($personFieldMap as $inputKey => $column) {
                if (isset($data[$inputKey])) {
                    if ($inputKey === 'first_name' || $inputKey === 'last_name' || $inputKey === 'middle_name') {
                        if (trim((string) $data[$inputKey]) === '') {
                            continue;
                        }
                        $cleanedName = FieldCleaner::cleanName((string) $data[$inputKey]);
                        if ($cleanedName === null) {
                            return $this->response(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $inputKey)) . ' must contain letters only (no digits or symbols)'], 400);
                        }
                        $data[$inputKey] = $cleanedName;
                    }
                    if ($inputKey === 'date_of_birth') {
                        $cleanedDob = FieldCleaner::cleanDob((string) $data[$inputKey]);
                        if ($cleanedDob === null) {
                            return $this->response(['status' => 'error', 'message' => 'Date of birth must be a date strictly in the past (not today or future)'], 400);
                        }
                        $data[$inputKey] = $cleanedDob;
                    }
                    $personUpdates[] = "{$column} = ?";
                    $personParams[] = $data[$inputKey];
                }
            }

            if (!empty($personUpdates)) {
                $personParams[] = $id;
                $personSql = "
                    UPDATE persons
                    SET " . implode(', ', $personUpdates) . "
                    WHERE id = (SELECT person_id FROM students WHERE id = ?)
                ";
                $stmt = $this->db->prepare($personSql);
                $stmt->execute($personParams);
            }

            // Student-level fields
            $updates = [];
            $params = [];
            $allowedFields = [
                'admission_no',
                'student_type_id',
                'admission_date',
                'status',
                'assessment_number',
                'assessment_status',
                'nemis_number',
                'nemis_status',
                'blood_group'
            ];

            foreach ([
                'assessment_number' => 'assessment_status',
                'nemis_number' => 'nemis_status',
            ] as $numberField => $statusField) {
                if (array_key_exists($numberField, $data)) {
                    $number = trim((string) ($data[$numberField] ?? ''));
                    if ($number !== '') {
                        $data[$numberField] = $number;
                        $data[$statusField] = 'assigned';
                    } elseif (!isset($data[$statusField])) {
                        $data[$statusField] = 'not_assigned';
                    }
                }
            }

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

            $currentStreamId = $this->getActiveEnrollmentStreamId($id);
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

            // The manual student form also edits the learner's financial
            // onboarding records. Keep these writes on the same API path as
            // the student update instead of silently dropping them.
            if (!empty($data['school_sponsorship'])) {
                $this->saveExistingSchoolSponsorship((int) $id, $data['school_sponsorship']);
                $this->applyExistingStudentSponsorshipToObligations((int) $id);
            }
            if (!empty($data['school_fee_waiver'])) {
                $this->saveExistingSchoolFeeWaiver((int) $id, $data['school_fee_waiver']);
            }
            if (!empty($data['financial_migration'])) {
                $financial = !empty($data['financial_migration']['calculate_from_schedule'])
                    ? $this->deriveExistingImportFinancialPosition($data, (int) $id)
                    : $data['financial_migration'];
                $this->saveFinancialMigrationSnapshot((int) $id, $financial);
                $this->applyFinancialMigrationToObligations((int) $id, $financial);
            }
            if (!empty($data['transport_arrangement'])) {
                (new \App\API\Modules\transport\StudentTransportEntitlementManager($this->db))->enrollStudent(
                    array_merge($data['transport_arrangement'], ['student_id' => (int) $id]),
                    (int) ($this->getCurrentUserId() ?? 0)
                );
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
        $admissionYear = $this->getCurrentAcademicYearValue() ?? (int) date('Y');
        return (new AdmissionNumberService($this->db))->generate($admissionYear);
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

        // Clean parent free-text/contact fields before lookup and storage
        // (letters-only names, canonical phones, canonical email).
        foreach (['first_name', 'last_name'] as $nameField) {
            if (isset($parentData[$nameField]) && trim((string) $parentData[$nameField]) !== '') {
                $cleanedRelName = FieldCleaner::cleanName((string) $parentData[$nameField]);
                if ($cleanedRelName === null) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $nameField)) . ' must contain letters only (no digits or symbols)');
                }
                $parentData[$nameField] = $cleanedRelName;
            }
        }
        foreach (['phone_1', 'phone_2'] as $phoneField) {
            if (isset($parentData[$phoneField]) && trim((string) $parentData[$phoneField]) !== '') {
                $cleanedRelPhone = FieldCleaner::cleanPhone((string) $parentData[$phoneField]);
                if ($cleanedRelPhone === null) {
                    throw new Exception(ucfirst(str_replace('_', ' ', $phoneField)) . ' is not a valid Kenyan phone number');
                }
                $parentData[$phoneField] = $cleanedRelPhone;
            }
        }
        if (isset($parentData['email']) && trim((string) $parentData['email']) !== '') {
            $cleanedRelEmail = FieldCleaner::cleanEmail((string) $parentData['email']);
            if ($cleanedRelEmail === null || !filter_var($cleanedRelEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Email is not a valid email address');
            }
            $parentData['email'] = $cleanedRelEmail;
        }

        // Robust parent lookup: match existing person+parent by phone or email
        $parentId = null;
        if (!empty($parentData['phone_1']) || !empty($parentData['phone_2']) || !empty($parentData['email'])) {
            $stmt = $this->db->prepare("
                SELECT p.id
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                WHERE pp.phone = ? OR pp.phone = ? OR (pp.email IS NOT NULL AND pp.email = ?)
                LIMIT 1
            ");
            $stmt->execute([
                $parentData['phone_1'] ?? null,
                $parentData['phone_2'] ?? null,
                $parentData['email'] ?? null
            ]);
            $parentId = $stmt->fetchColumn() ?: null;
        }

        if ($parentId) {
            // Update person-level details on the matched parent
            $this->updateParentRecord((int) $parentId, $parentData);
        } else {
            $parentId = $this->createParentRecord($parentData);
        }

        // Create student-parent relationship if not exists
        $this->linkStudentParent($studentId, (int) $parentId, $parentData);
    }

    /**
     * Create a person + parent pair and return the parent id.
     */
    private function createParentRecord(array $parentData): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO persons (first_name, middle_name, last_name, dob, gender, national_id_no, email, phone, data_scope)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'live')
        ");
        $stmt->execute([
            $parentData['first_name'] ?? '',
            $parentData['middle_name'] ?? null,
            $parentData['last_name'] ?? '',
            $parentData['date_of_birth'] ?? null,
            $parentData['gender'] ?? 'other',
            $parentData['id_number'] ?? null,
            $parentData['email'] ?? null,
            $parentData['phone_1'] ?? null
        ]);
        $personId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare("
            INSERT INTO parents (person_id, occupation, address, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([
            $personId,
            $parentData['occupation'] ?? null,
            $parentData['address'] ?? null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update person-level + parent-level details for an existing parent.
     */
    private function updateParentRecord(int $parentId, array $parentData): void
    {
        $personFieldMap = [
            'first_name' => 'first_name',
            'middle_name' => 'middle_name',
            'last_name' => 'last_name',
            'id_number' => 'national_id_no',
            'gender' => 'gender',
            'date_of_birth' => 'dob',
            'phone_1' => 'phone',
            'phone_2' => 'phone',
            'email' => 'email',
        ];

        $personSets = [];
        $personParams = [];
        foreach ($personFieldMap as $inputKey => $column) {
            if (!empty($parentData[$inputKey])) {
                $personSets[] = "{$column} = ?";
                $personParams[] = $parentData[$inputKey];
            }
        }

        if (!empty($personSets)) {
            $personParams[] = $parentId;
            $stmt = $this->db->prepare("
                UPDATE parents p
                JOIN persons pp ON pp.id = p.person_id
                SET " . implode(', ', $personSets) . "
                WHERE p.id = ?
            ");
            $stmt->execute($personParams);
        }

        $parentSets = [];
        $parentParams = [];
        foreach (['occupation', 'address'] as $column) {
            if (array_key_exists($column, $parentData) && $parentData[$column] !== null) {
                $parentSets[] = "{$column} = ?";
                $parentParams[] = $parentData[$column];
            }
        }

        if (!empty($parentSets)) {
            $parentParams[] = $parentId;
            $stmt = $this->db->prepare("
                UPDATE parents SET " . implode(', ', $parentSets) . " WHERE id = ?
            ");
            $stmt->execute($parentParams);
        }

        $parentUser = $this->db->prepare('SELECT id FROM users WHERE person_id=? LIMIT 1');
        $parentUser->execute([$personId]);
        $parentUserId = $parentUser->fetchColumn();
        if ($parentUserId !== false) (new \App\API\Modules\users\UserRoleManager($this->db))->assignRole((int)$parentUserId, 73, false);
    }

    private function getStudentParents($studentId)
    {
        $sql = "
            SELECT 
                sp.parent_id AS student_parent_id,
                sp.student_id,
                sp.parent_id,
                sp.relationship,
                sp.is_primary_contact,
                sp.is_emergency_contact,
                pp.first_name,
                pp.middle_name,
                pp.last_name,
                CONCAT_WS(' ', pp.first_name, pp.middle_name, pp.last_name) as full_name,
                pp.gender,
                pp.dob AS date_of_birth,
                pp.national_id_no AS id_number,
                pp.phone AS phone_1,
                NULL AS phone_2,
                pp.phone AS phone,
                pp.phone AS phone1,
                NULL AS phone2,
                pp.email,
                p.occupation,
                p.address,
                p.status,
                p.created_at,
                p.updated_at
            FROM parents p
            JOIN persons pp ON pp.id = p.person_id
            JOIN student_parents sp ON p.id = sp.parent_id
            WHERE sp.student_id = ?
            ORDER BY sp.is_primary_contact DESC, sp.is_emergency_contact DESC, sp.parent_id ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeeSummary($studentId)
    {
        $academicYear = $this->getCurrentAcademicYearValue();
        $where = ['vfb.student_id = ?'];
        $bindings = [$studentId];

        if ($academicYear !== null) {
            $where[] = 'CAST(SUBSTRING(vfb.academic_year, 1, 4) AS UNSIGNED) = ?';
            $bindings[] = $academicYear;
        }

        $obligationSql = "
            SELECT
                COALESCE(SUM(amount_due), 0) AS total_fees,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(amount_waived), 0) AS total_waived,
                COALESCE(SUM(balance), 0) AS balance,
                MIN(CASE WHEN balance > 0 THEN latest_due_date END) AS earliest_due_date
            FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " vfb
            WHERE " . implode(' AND ', $where) . "
        ";
        $stmt = $this->db->prepare($obligationSql);
        $stmt->execute($bindings);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $paymentSql = "
            SELECT
                MAX(payment_date) AS last_payment_date,
                COUNT(*) AS number_of_payments
            FROM payments
            WHERE student_id = ?
                AND status = 'confirmed'
        ";
        $paymentBindings = [$studentId];

        if ($academicYear !== null) {
            $paymentSql .= " AND YEAR(payment_date) = ?";
            $paymentBindings[] = $academicYear;
        }

        $stmt = $this->db->prepare($paymentSql);
        $stmt->execute($paymentBindings);
        $paymentMeta = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $tuitionFees = (float) ($summary['total_fees'] ?? 0);
        $tuitionPaid = (float) ($summary['total_paid'] ?? 0);
        $tuitionBalance = (float) ($summary['balance'] ?? 0);

        // Admission/registration is a separate obligation from tuition. It
        // is stored in admission_payments while tuition is stored in the fee
        // ledger, so combine them here without double-counting the same
        // payment transaction.
        $admissionDue = 0.0;
        $admissionPaid = 0.0;
        $admissionStmt = $this->db->prepare(
            "SELECT aa.id, aa.parent_id
             FROM admission_applications aa
             WHERE aa.enrolled_student_id = ?
             ORDER BY aa.id DESC
             LIMIT 1"
        );
        $admissionStmt->execute([$studentId]);
        $admission = $admissionStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($admission) {
            $existingParentStudents = $this->db->prepare(
                "SELECT COUNT(*)
                 FROM student_parents
                 WHERE parent_id = ? AND student_id <> ?"
            );
            $existingParentStudents->execute([
                (int) ($admission['parent_id'] ?? 0),
                $studentId,
            ]);
            $admissionDue = ((int) $existingParentStudents->fetchColumn() > 0) ? 1000.0 : 2000.0;

            $admissionPaidStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM admission_payments
                 WHERE application_id = ?
                   AND status IN ('recorded', 'posted')"
            );
            $admissionPaidStmt->execute([(int) $admission['id']]);
            $admissionPaid = (float) $admissionPaidStmt->fetchColumn();
        }

        // Migration credit is already included in vw_student_fee_balances.
        // Keep the snapshot for provenance and the year/term breakdown, but
        // do not add it to these totals a second time.
        $migration = $this->getExistingStudentMigrationPosition((int) $studentId, $academicYear);
        $totalFees = $tuitionFees + $admissionDue;
        $totalPaid = $tuitionPaid + min($admissionPaid, $admissionDue);
        $balance = $tuitionBalance + max(0.0, $admissionDue - $admissionPaid);

        $currentTermStmt = $this->db->prepare(
            "SELECT ayt.id AS id, t.name AS name
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = (SELECT id FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1)
               AND (ayt.status='current' OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date)
             ORDER BY ayt.status='current' DESC, ayt.opening_date
             LIMIT 1"
        );
        $currentTermStmt->execute();
        $currentTerm = $currentTermStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $currentTermSummary = [
            'total_fees' => 0,
            'gross_expected' => 0,
            'net_expected' => 0,
            'total_paid' => 0,
            'total_waived' => 0,
            'balance' => 0,
        ];
        if ($currentTerm) {
            $termSummaryStmt = $this->db->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN academic_year_term_id < ? THEN balance ELSE 0 END),0)
                      + COALESCE(SUM(CASE WHEN academic_year_term_id = ? THEN amount_due - amount_waived ELSE 0 END),0) AS total_fees,
                    COALESCE(SUM(CASE WHEN academic_year_term_id = ? THEN amount_due ELSE 0 END),0) AS gross_expected,
                    COALESCE(SUM(CASE WHEN academic_year_term_id = ? THEN amount_due - amount_waived ELSE 0 END),0) AS net_expected,
                    COALESCE(SUM(CASE WHEN academic_year_term_id = ? THEN amount_paid ELSE 0 END),0) AS total_paid,
                    COALESCE(SUM(CASE WHEN academic_year_term_id = ? THEN amount_waived ELSE 0 END),0) AS total_waived,
                    COALESCE(SUM(CASE WHEN academic_year_term_id <= ? THEN balance ELSE 0 END),0) AS balance
                 FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . "
                 WHERE student_id=? AND academic_year= (SELECT year_code FROM academic_years WHERE is_current=1 LIMIT 1)"
            );
            $termSummaryStmt->execute([
                (int) $currentTerm['id'], (int) $currentTerm['id'],
                (int) $currentTerm['id'], (int) $currentTerm['id'],
                (int) $currentTerm['id'], (int) $currentTerm['id'],
                (int) $currentTerm['id'], $studentId,
            ]);
            $currentTermSummary = $termSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: $currentTermSummary;
        }

        $coveredAmount = min($tuitionFees, $tuitionPaid + (float) ($summary['total_waived'] ?? 0));
        $coveragePercentage = $tuitionFees > 0
            ? round(($coveredAmount / $tuitionFees) * 100, 2)
            : 0.0;

        $result = [
            'academic_year' => $academicYear,
            'total_fees' => $totalFees,
            'total_paid' => $totalPaid,
            'total_waived' => $summary['total_waived'] ?? 0,
            'balance' => $balance,
            'tuition_fees' => $tuitionFees,
            'tuition_paid' => $tuitionPaid,
            'tuition_balance' => $tuitionBalance,
            'annual_expected' => $tuitionFees,
            'annual_paid' => $tuitionPaid,
            'annual_balance' => $tuitionBalance,
            'current_term' => $currentTerm['name'] ?? null,
            'current_term_expected' => (float) $currentTermSummary['total_fees'],
            'current_term_gross_expected' => (float) $currentTermSummary['gross_expected'],
            'current_term_net_expected' => (float) $currentTermSummary['net_expected'],
            'current_term_paid' => $migration !== null
                ? (float) $migration['current_term_paid_amount']
                : (float) $currentTermSummary['total_paid'],
            'current_term_waived' => (float) $currentTermSummary['total_waived'],
            'current_term_balance' => (float) $currentTermSummary['balance'],
            'covered_amount' => $coveredAmount,
            'coverage_percentage' => $coveragePercentage,
            'admission_fee_due' => $admissionDue,
            'admission_fee_paid' => min($admissionPaid, $admissionDue),
            'admission_fee_balance' => max(0.0, $admissionDue - $admissionPaid),
            'payment_percentage' => $coveragePercentage,
            'payment_status' => $balance <= 0 && $totalFees > 0
                ? 'paid'
                : ($totalPaid > 0 ? 'partial' : 'pending'),
            'last_payment_date' => $paymentMeta['last_payment_date'] ?? null,
            'number_of_payments' => $paymentMeta['number_of_payments'] ?? 0,
            'arrears_status' => ($balance > 0 && !empty($summary['earliest_due_date']) && $summary['earliest_due_date'] < date('Y-m-d'))
                ? 'overdue'
                : 'current'
        ];
        if ($migration !== null) {
            $result['migration_position'] = $migration;
        }
        return $result;
    }

    /** Return the schedule-based annual position and historical figures, if supplied. */
    private function getExistingStudentMigrationPosition(int $studentId, ?int $academicYear): ?array
    {
        $snapshotStmt = $this->db->prepare(
            "SELECT snap.*, ay.year_code
             FROM student_fee_migration_snapshots snap
             JOIN academic_years ay ON ay.id = snap.academic_year_id
             WHERE snap.student_id = ?
               AND (? IS NULL OR CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?)
             ORDER BY snap.academic_year_id DESC LIMIT 1"
        );
        $snapshotStmt->execute([$studentId, $academicYear, $academicYear]);
        $snapshot = $snapshotStmt->fetch(PDO::FETCH_ASSOC);
        if (!$snapshot) return null;

        $yearId = (int) $snapshot['academic_year_id'];
        $classStmt = $this->db->prepare(
            "SELECT ayc.class_id, s.student_type_id
             FROM student_academic_enrollments sae
             JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             JOIN students s ON s.id = sae.student_id
             WHERE sae.student_id = ? AND sae.academic_year_id = ?
             ORDER BY sae.id DESC LIMIT 1"
        );
        $classStmt->execute([$studentId, $yearId]);
        $placement = $classStmt->fetch(PDO::FETCH_ASSOC);
        if (!$placement) return null;

        $feesStmt = $this->db->prepare(
            "SELECT afs.amount, ayt.opening_date, ayt.closing_date
             FROM academic_year_fee_schedules afs
             JOIN academic_year_classes ayc ON ayc.id = afs.academic_year_class_id
             JOIN academic_year_terms ayt ON ayt.id = afs.academic_year_term_id
             WHERE afs.academic_year_id = ? AND ayc.class_id = ?
               AND (afs.student_type_id = ? OR afs.student_type_id IS NULL)
               AND afs.status = 'active'"
        );
        $feesStmt->execute([$yearId, (int) $placement['class_id'], (int) $placement['student_type_id']]);
        $feeRows = $feesStmt->fetchAll(PDO::FETCH_ASSOC);
        $gross = array_sum(array_map(static fn($row) => (float) $row['amount'], $feeRows));

        $awardsStmt = $this->db->prepare(
            "SELECT coverage_type, coverage_percentage, coverage_amount, starts_on, ends_on
             FROM student_scholarship_awards
             WHERE student_id = ? AND academic_year_id = ? AND status = 'active'"
        );
        $awardsStmt->execute([$studentId, $yearId]);
        $relief = 0.0;
        foreach ($awardsStmt->fetchAll(PDO::FETCH_ASSOC) as $award) {
            foreach ($feeRows as $fee) {
                if (($award['starts_on'] && $fee['closing_date'] < $award['starts_on'])
                    || ($award['ends_on'] && $fee['opening_date'] > $award['ends_on'])) continue;
                $amount = (float) $fee['amount'];
                $cover = match ($award['coverage_type']) {
                    'full' => $amount,
                    'percentage' => $amount * (float) ($award['coverage_percentage'] ?? 0) / 100,
                    default => (float) ($award['coverage_amount'] ?? 0),
                };
                $relief += min($amount, max(0, $cover));
            }
        }

        $annualPaid = (float) $snapshot['academic_year_paid_amount'];
        $annualBalance = max(0, round($gross - $relief - $annualPaid, 2));
        return [
            'academic_year' => $snapshot['year_code'],
            'gross_annual_fees' => round($gross, 2),
            'annual_sponsorship' => round($relief, 2),
            'net_annual_fees' => round(max(0, $gross - $relief), 2),
            'academic_year_paid_amount' => $annualPaid,
            'current_term_paid_amount' => (float) $snapshot['current_term_paid_amount'],
            'arrears_amount' => (float) $snapshot['arrears_amount'],
            'annual_balance' => $annualBalance,
            'advance_amount' => (float) $snapshot['advance_amount'],
            'label' => 'Includes amounts transcribed from prior school records; not payment transactions',
        ];
    }

    private function getAttendanceSummary($studentId)
    {
        $sql = "
            SELECT 
                MONTH(sa.date) as month,
                YEAR(sa.date) as year,
                COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as late_days
            FROM student_attendance sa
            JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
            WHERE sae.student_id = ?
            GROUP BY YEAR(sa.date), MONTH(sa.date)
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

        $where = ['sae.student_id = ?'];
        $bindings = [$id];

        if (!empty($termId) && ctype_digit((string) $termId)) {
            $where[] = 'ayt.id = ?';
            $bindings[] = (int) $termId;
        }

        if (!empty($academicYear)) {
            $where[] = 'YEAR(sa.date) = ?';
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
                sae.student_id,
                sa.date,
                sa.status,
                sa.check_in_time,
                sa.check_out_time,
                sa.absence_reason,
                sa.notes,
                ayc.class_id,
                ayt.id AS term_id,
                sa.session_id,
                sa.marked_by,
                t.name AS term_name,
                SUBSTRING(t.code, 2) AS term_number,
                CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) AS academic_year,
                ats.name AS session_name,
                ats.type AS session_type
            FROM student_attendance sa
            JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN academic_year_terms ayt
                ON ayt.academic_year_id = sae.academic_year_id
               AND sa.date BETWEEN ayt.opening_date AND ayt.closing_date
            LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
            LEFT JOIN terms t ON t.id = ayt.term_id
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

    /** Compare marked rows with attendance-required school-calendar dates. */
    private function getAttendanceCoverage(int $studentId, array $params, array $records): array
    {
        $academicYear = $params['academic_year'] ?? $params['year'] ?? null;
        $yearFilter = $academicYear !== null && ctype_digit((string) $academicYear)
            ? 'AND CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?'
            : 'AND ay.is_current = 1';
        $yearBindings = $academicYear !== null && ctype_digit((string) $academicYear)
            ? [(int) $academicYear] : [];
        $stmt = $this->db->prepare(
            "SELECT sae.id, sae.academic_year_id, ay.start_date, ay.end_date,
                    CASE WHEN s.entry_source='admission'
                         THEN COALESCE(DATE(aa.enrolled_at),s.admission_date,sae.enrolled_on)
                         ELSE sae.enrolled_on END AS attendance_start
             FROM student_academic_enrollments sae
             JOIN academic_years ay ON ay.id=sae.academic_year_id
             JOIN students s ON s.id=sae.student_id
             LEFT JOIN admission_applications aa ON aa.enrolled_student_id=s.id
             WHERE sae.student_id=? AND sae.enrollment_status IN ('active','completed','transferred','graduated')
             {$yearFilter} ORDER BY sae.id DESC LIMIT 1"
        );
        $stmt->execute(array_merge([$studentId], $yearBindings));
        $enrollment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$enrollment) {
            return ['expected_days'=>0,'marked_days'=>0,'unmarked_days'=>0,'unmarked_dates'=>[],
                'attendance_date_from'=>null,'attendance_date_to'=>null,'unmarked_date_from'=>null,
                'unmarked_date_to'=>null,'coverage_percentage'=>0];
        }

        $start = max(
            (string)($enrollment['start_date'] ?? '9999-12-31'),
            (string)($enrollment['attendance_start'] ?? $enrollment['start_date'] ?? '9999-12-31'),
            (string)($params['date_from'] ?? '0000-01-01')
        );
        $end = min(
            (string)($enrollment['end_date'] ?? date('Y-m-d')),
            date('Y-m-d'),
            (string)($params['date_to'] ?? '9999-12-31')
        );
        $calendar = $this->db->prepare(
            "SELECT acd.date,COALESCE(cdt.affects_day_students,1) affects_day_students,
                    COALESCE(cdt.requires_attendance,1) requires_attendance
             FROM academic_year_calendar_days acd
             JOIN academic_year_calendar ac ON ac.id=acd.academic_year_calendar_id
             JOIN academic_year_terms ayt ON ayt.id=ac.academic_year_term_id
             LEFT JOIN calendar_day_types cdt ON cdt.id=acd.calendar_day_type_id
             WHERE ayt.academic_year_id=? AND acd.date BETWEEN ? AND ? ORDER BY acd.date"
        );
        $calendar->execute([(int)$enrollment['academic_year_id'], $start, $end]);
        $expected = [];
        foreach ($calendar->fetchAll(PDO::FETCH_ASSOC) as $day) {
            if ((int)$day['affects_day_students'] === 1 && (int)$day['requires_attendance'] === 1) {
                $expected[] = $day['date'];
            }
        }
        $marked = array_values(array_unique(array_filter(array_map(static fn($r)=>$r['date']??null, $records))));
        $markedSet = array_fill_keys($marked, true);
        $missing = array_values(array_filter($expected, static fn($date)=>!isset($markedSet[$date])));
        $expectedCount = count($expected);
        $markedCount = count(array_intersect($expected, $marked));
        return [
            'expected_days'=>$expectedCount,
            'marked_days'=>$markedCount,
            'unmarked_days'=>count($missing),
            'unmarked_dates'=>$missing,
            'attendance_date_from'=>$expected[0]??null,
            'attendance_date_to'=>$expectedCount?$expected[$expectedCount-1]:null,
            'unmarked_date_from'=>$missing[0]??null,
            'unmarked_date_to'=>$missing?$missing[count($missing)-1]:null,
            'coverage_percentage'=>$expectedCount?round($markedCount/$expectedCount*100,2):0,
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
            $where[] = 'CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?';
            $bindings[] = (int) $academicYear;
        }

        $sql = "
            SELECT
                tss.id,
                tss.student_id,
                tss.term_id,
                t.name AS term_name,
                SUBSTRING(t.code, 2) AS term_number,
                CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) AS academic_year,
                tss.subject_id,
                COALESCE(la.name, CONCAT('Subject #', tss.subject_id)) AS subject_name,
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
            LEFT JOIN academic_year_terms ayt ON tss.term_id = ayt.id
            LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
            LEFT JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN learning_areas la ON tss.subject_id = la.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ay.year_code DESC, t.id DESC, subject_name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            return $rows;
        }

        // Fallback source: raw assessment results where rollups are missing
        $fallbackWhere = ['sae.student_id = ?'];
        $fallbackBindings = [$id];

        if (!empty($termId) && ctype_digit((string) $termId)) {
            $fallbackWhere[] = 'a.academic_year_term_id = ?';
            $fallbackBindings[] = (int) $termId;
        }
        if (!empty($academicYear)) {
            $fallbackWhere[] = 'CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?';
            $fallbackBindings[] = (int) $academicYear;
        }

        $fallbackSql = "
            SELECT
                ar.id AS result_id,
                sae.student_id,
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
                a.academic_year_term_id AS term_id,
                t.name AS term_name,
                SUBSTRING(t.code, 2) AS term_number,
                CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) AS academic_year,
                a.learning_area_id AS subject_id,
                COALESCE(la.name, CONCAT('Subject #', a.learning_area_id)) AS subject_name
            FROM assessment_results ar
            JOIN student_academic_enrollments sae ON sae.id = ar.student_academic_enrollment_id
            JOIN assessments a ON ar.assessment_id = a.id
            LEFT JOIN academic_year_terms ayt ON a.academic_year_term_id = ayt.id
            LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
            LEFT JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN learning_areas la ON a.learning_area_id = la.id
            WHERE " . implode(' AND ', $fallbackWhere) . "
            ORDER BY ay.year_code DESC, t.id DESC, a.assessment_date DESC, subject_name ASC
        ";

        $fallbackStmt = $this->db->prepare($fallbackSql);
        $fallbackStmt->execute($fallbackBindings);
        return $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getFeePayments($studentId)
    {
        $sql = "
            SELECT
                p.id,
                p.student_id,
                ayt.id AS term_id,
                t.name AS term_name,
                SUBSTRING(t.code, 2) AS term_number,
                CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) AS academic_year,
                p.amount AS amount,
                p.amount AS amount_paid,
                p.payment_date,
                p.method AS payment_method,
                p.reference AS reference_no,
                COALESCE(p.reference, p.receipt_no) AS reference,
                p.receipt_no,
                p.status,
                p.notes
            FROM payments p
            LEFT JOIN academic_year_terms ayt
                ON p.payment_date BETWEEN ayt.opening_date AND ayt.closing_date
            LEFT JOIN academic_years ay ON ay.id = ayt.academic_year_id
            LEFT JOIN terms t ON t.id = ayt.term_id
            WHERE p.student_id = ?
                AND p.status = 'confirmed'
            ORDER BY p.payment_date DESC, p.id DESC
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
                sfo.student_academic_enrollment_id,
                sae.student_id,
                sae.academic_year_id,
                sfo.academic_year_term_id AS term_id,
                t.name AS term_name,
                SUBSTRING(t.code, 2) AS term_number,
                CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) AS academic_year,
                sfo.academic_year_fee_schedule_id AS fee_structure_detail_id,
                'School Fees' AS fee_type,
                sfo.amount_due,
                COALESCE(vfb.amount_paid, 0) AS amount_paid,
                COALESCE(vfb.amount_waived, 0) AS amount_waived,
                COALESCE(vfb.balance, GREATEST(sfo.amount_due, 0)) AS balance,
                sfo.status,
                COALESCE(vfb.payment_status, sfo.status) AS payment_status,
                sfo.due_date
            FROM student_fee_obligations sfo
            JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
            LEFT JOIN academic_years ay ON ay.id = sfo.academic_year_id
            LEFT JOIN terms t ON t.id = ayt.term_id
            LEFT JOIN academic_year_fee_schedules ayfs ON ayfs.id = sfo.academic_year_fee_schedule_id
            LEFT JOIN " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " vfb
                ON vfb.student_academic_enrollment_id = sfo.student_academic_enrollment_id
               AND vfb.academic_year_term_id <=> sfo.academic_year_term_id
            WHERE sae.student_id = ?
        ";
        $bindings = [$studentId];

        if ($academicYear !== null) {
            $sql .= " AND CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?";
            $bindings[] = $academicYear;
        }

        $sql .= " ORDER BY term_id ASC, sfo.id ASC";

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
            $student = $this->getStudentOverviewRecord($id);

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
                    COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as late_days
                FROM student_attendance sa
                JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                WHERE sae.student_id = ? AND YEAR(sa.date) = ? AND MONTH(sa.date) BETWEEN ? AND ?
            ";

            $termMonths = [
                1 => [9, 10, 11],
                2 => [1, 2, 3],
                3 => [5, 6, 7]
            ];

            $termNumber = $term;
            if ($termNumber > 3) {
                $termStmt = $this->db->prepare("
                    SELECT SUBSTRING(t.code, 2) AS term_number
                    FROM academic_year_terms ayt
                    JOIN terms t ON t.id = ayt.term_id
                    WHERE ayt.id = ?
                    LIMIT 1
                ");
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

            // Class teacher comments (term report notes are not persisted in the live schema)
            $comments = null;

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

            $enrollmentStmt = $this->db->prepare("
                SELECT id FROM student_academic_enrollments
                WHERE student_id = ? AND enrollment_status = 'active'
                ORDER BY academic_year_id DESC, id DESC
                LIMIT 1
            ");
            $enrollmentStmt->execute([$id]);
            $enrollmentId = $enrollmentStmt->fetchColumn();

            if (!$enrollmentId) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No active enrollment found for student'
                ], 400);
            }

            $sql = "
                INSERT INTO student_attendance (
                    student_academic_enrollment_id,
                    date,
                    session_id,
                    status,
                    check_in_time,
                    check_out_time,
                    absence_reason,
                    notes,
                    register_type,
                    marked_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    notes = VALUES(notes),
                    check_in_time = COALESCE(VALUES(check_in_time), check_in_time),
                    check_out_time = COALESCE(VALUES(check_out_time), check_out_time),
                    absence_reason = VALUES(absence_reason),
                    marked_by = VALUES(marked_by)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                (int) $enrollmentId,
                $data['date'],
                isset($data['session_id']) ? (int) $data['session_id'] : null,
                $data['status'],
                $data['check_in_time'] ?? null,
                $data['check_out_time'] ?? null,
                $data['absence_reason'] ?? null,
                $data['notes'] ?? $data['remarks'] ?? null,
                $data['register_type'] ?? 'class',
                $this->getCurrentUserId()
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

            // Get current stream from active enrollment
            $stmt = $this->db->prepare("
                SELECT sae.id AS enrollment_id,
                       sae.academic_year_class_stream_id AS stream_id,
                       sae.academic_year_id
                FROM student_academic_enrollments sae
                WHERE sae.student_id = ?
                  AND sae.enrollment_status = 'active'
                ORDER BY sae.academic_year_id DESC, sae.id DESC
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $currentEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentEnrollment) {
                $this->db->rollBack();
                return $this->response([
                    'status' => 'error',
                    'message' => 'No active enrollment found for student'
                ], 400);
            }

            // Record transfer history
            $stmt = $this->db->prepare("
                INSERT INTO student_transitions (
                    student_id,
                    from_student_academic_enrollment_id,
                    to_student_academic_enrollment_id,
                    academic_year_id,
                    transition_type,
                    reason,
                    decided_by,
                    decided_at,
                    executed_at
                ) VALUES (?, ?, ?, ?, 'transfer', ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $id,
                (int) $currentEnrollment['enrollment_id'],
                (int) $currentEnrollment['enrollment_id'],
                (int) $currentEnrollment['academic_year_id'],
                $data['reason'],
                $this->getCurrentUserId(),
                $data['transfer_date'] ?: date('Y-m-d H:i:s')
            ]);

            // Update active enrollment to the new stream
            $stmt = $this->db->prepare("
                UPDATE student_academic_enrollments
                SET academic_year_class_stream_id = ?
                WHERE student_id = ? AND enrollment_status = 'active'
                ORDER BY academic_year_id DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([$data['new_stream_id'], $id]);

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }
            $this->logAction('update', $id, "Transferred student to new stream");

            return $this->response([
                'status' => 'success',
                'message' => 'Student transferred successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

            $enrollmentStmt = $this->db->prepare("
                SELECT id, academic_year_id
                FROM student_academic_enrollments
                WHERE student_id = ? AND enrollment_status = 'active'
                ORDER BY academic_year_id DESC, id DESC
                LIMIT 1
            ");
            $enrollmentStmt->execute([$id]);
            $enrollment = $enrollmentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollment) {
                $this->db->rollBack();
                return $this->response([
                    'status' => 'error',
                    'message' => 'No active enrollment found for student'
                ], 400);
            }

            $termStmt = $this->db->prepare("
                SELECT id FROM academic_year_terms
                WHERE academic_year_id = ? AND status = 'current'
                LIMIT 1
            ");
            $termStmt->execute([(int) $enrollment['academic_year_id']]);
            $termId = $termStmt->fetchColumn();

            $sql = "
                INSERT INTO discipline_incidents (
                    student_academic_enrollment_id,
                    academic_year_term_id,
                    type,
                    severity,
                    incident_date,
                    description,
                    action_taken,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                (int) $enrollment['id'],
                $termId ? (int) $termId : null,
                $data['type'] ?? 'general',
                $data['severity'],
                $data['incident_date'],
                $data['description'],
                $data['action_taken'] ?? null,
                $status
            ]);
            $caseId = (int) $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $caseId, "Recorded discipline case for student ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Discipline case recorded successfully',
                'data' => ['id' => $caseId]
            ], 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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

            // Use SVG when the active PHP runtime lacks GD. SVG QR assets do
            // not depend on image extensions and remain suitable for cards.
            $qrClass = '\Endroid\QrCode\QrCode';
            $hasGd = extension_loaded('gd') && function_exists('imagecreatetruecolor');
            $writerClass = $hasGd
                ? '\Endroid\QrCode\Writer\PngWriter'
                : '\Endroid\QrCode\Writer\SvgWriter';
            $qrExtension = $hasGd ? 'png' : 'svg';
            if (!class_exists($writerClass)) {
                throw new Exception('No supported QR writer is available in this PHP environment');
            }

            // Print only an opaque, non-enumerable credential. The scanner API
            // resolves it server-side and never trusts identity, fees, or URLs
            // embedded in the image.
            $qrToken = 'KWA1.' . rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $qrCode = new $qrClass($qrToken);
            $qrCode->setSize(300);
            $qrCode->setMargin(10);

            // Create writer
            $writer = new $writerClass();

            // Generate QR code image
            $result = $writer->write($qrCode);

            // Persist through the inherited UploadService gateway.
            $qrFilename = $student['admission_no'] . '.' . $qrExtension;
            $qrPath = $this->managedPath(
                'student_photo',
                (string) $id,
                'qr_codes',
                $qrFilename
            );
            $this->writeManagedFile($qrPath, $result->getString());
            $webQrPath = $this->managedPublicUrl(
                'student_photo',
                (string) $id,
                'qr_codes',
                $qrFilename
            );

            // Persist to student_id_cards (the live schema has no students.qr_code_path)
            $qrPayload = json_encode([
                'token' => $qrToken,
                'version' => 1
            ], JSON_UNESCAPED_SLASHES);
            // student_id_cards.card_number is VARCHAR(20). Admission numbers
            // such as KA-2026-0005 cannot safely be concatenated with a year
            // without exceeding that limit. Keep the card number compact and
            // unique; the admission number remains the learner-facing account
            // reference.
            $cardNumber = 'CARD-' . str_pad((string) $id, 15, '0', STR_PAD_LEFT);
            $academicYearId = $this->getCurrentAcademicYearIdForScope();
            $expiryYear = (int) date('Y') + 4;

            $existingStmt = $this->db->prepare("
                SELECT id FROM student_id_cards WHERE student_id = ? ORDER BY id DESC LIMIT 1
            ");
            $existingStmt->execute([$id]);
            $existingCardId = $existingStmt->fetchColumn();

            if ($existingCardId) {
                $stmt = $this->db->prepare("
                    UPDATE student_id_cards
                    SET qr_code_path = ?,
                        qr_payload = ?,
                        qr_token = ?,
                        status = 'generated',
                        generated_by = ?,
                        generated_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $webQrPath,
                    $qrPayload,
                    $qrToken,
                    $this->getCurrentUserId(),
                    (int) $existingCardId
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO student_id_cards (
                        student_id, card_number, qr_token, qr_payload, qr_code_path,
                        academic_year_id, expiry_year, status, generated_by, generated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'generated', ?, NOW())
                ");
                $stmt->execute([
                    $id,
                    $cardNumber,
                    $qrToken,
                    $qrPayload,
                    $webQrPath,
                    $academicYearId,
                    $expiryYear,
                    $this->getCurrentUserId()
                ]);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'QR code generated successfully',
                'data' => [
                    'qr_path' => $webQrPath
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
            $summary = array_merge($summary, $this->getAttendanceCoverage((int) $id, $params, $records));

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
                    s.id, s.admission_no, per.first_name, per.last_name,
                    (
                        SELECT sic.qr_code_path
                        FROM student_id_cards sic
                        WHERE sic.student_id = s.id
                          AND sic.qr_code_path IS NOT NULL
                        ORDER BY sic.id DESC
                        LIMIT 1
                    ) AS qr_code_path,
                    c.name as class_name,
                    sm.name as stream_name
                FROM students s
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
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

            $yearRecord = $this->getCurrentAcademicYearRecord();
            $fromYearId = (int) ($yearRecord['id'] ?? 0);
            $fromYearVal = $this->extractAcademicYearNumber($yearRecord);
            if (!$fromYearId || !$fromYearVal) {
                return $this->response(['status' => 'error', 'message' => 'No active academic year found'], 400);
            }

            $targetStmt = $this->db->prepare("
                SELECT id FROM academic_years
                WHERE CAST(SUBSTRING(year_code, 1, 4) AS UNSIGNED) = ?
                LIMIT 1
            ");
            $targetStmt->execute([$fromYearVal + 1]);
            $toYearId = (int) ($targetStmt->fetchColumn() ?: 0);
            if (!$toYearId) {
                return $this->response(['status' => 'error', 'message' => 'Next academic year is not set up yet'], 400);
            }

            $result = $this->promotionManager->promoteSingleStudent(
                (int) $id,
                (int) $data['new_class_id'],
                (int) $data['new_stream_id'],
                $fromYearId,
                $toYearId,
                $this->getCurrentUserId(),
                $data['remarks'] ?? null
            );

            if (empty($result['success'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Promotion failed'
                ], 400);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Student promoted successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
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
            SELECT di.*
            FROM discipline_incidents di
            JOIN student_academic_enrollments sae ON sae.id = di.student_academic_enrollment_id
            WHERE sae.student_id = ?
            ORDER BY di.incident_date DESC
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
            // New schema: academic_year_class_streams (year-scoped) -> streams (master) + classes
            $streamLookup = [];
            $classStreams = [];
            $classDisplayNames = [];
            $stmt = $this->db->query("
                SELECT aycs.id, sm.name AS stream_name, c.name AS class_name
                FROM academic_year_class_streams aycs
                JOIN streams sm ON sm.id = aycs.stream_id
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
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
                    'sponsor_waiver_percentage' => 'sponsor_waiver_percentage',
                    'paid_this_academic_year' => 'academic_year_paid_amount',
                    'paid_this_term' => 'current_term_paid_amount'
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
                // Existing learners predate this system. Do not invent an
                // admission date when the source records do not provide one.
                $admissionDate = $canon['admission_date'] ?? null;

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

            $createdCount = 0;
            $failedCount = 0;
            foreach ($processedData as $row) {
                try {
                    $this->db->beginTransaction();

                    $personStmt = $this->db->prepare("
                        INSERT INTO persons (first_name, middle_name, last_name, dob, gender, photo_url)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $personStmt->execute([
                        $row['first_name'] ?? null,
                        $row['middle_name'] ?? null,
                        $row['last_name'] ?? null,
                        $row['date_of_birth'] ?? null,
                        $row['gender'] ?? null,
                        $row['photo_url'] ?? null,
                    ]);
                    $personId = (int) $this->db->lastInsertId();

                    $studentStmt = $this->db->prepare("
                        INSERT INTO students (
                            person_id, admission_no, student_type_id, assessment_number,
                            assessment_status, nemis_number, nemis_status, status,
                            admission_date, blood_group, entry_source, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'existing_student', NOW())
                    ");
                    $studentStmt->execute([
                        $personId,
                        $row['admission_no'],
                        (int) ($row['student_type_id'] ?? 1),
                        $row['assessment_number'] ?? null,
                        $row['assessment_status'] ?? 'not_assigned',
                        $row['nemis_number'] ?? null,
                        $row['nemis_status'] ?? 'not_assigned',
                        $row['status'] ?? 'active',
                        $row['admission_date'] ?? null,
                        $row['blood_group'] ?? null,
                    ]);
                    $studentId = (int) $this->db->lastInsertId();

                    $streamId = (int) ($row['stream_id'] ?? 0);
                    if ($streamId > 0) {
                        $this->ensureClassEnrollment($studentId, $streamId);
                    }

                    $this->generateStudentFeeObligationsForCurrentYear(
                        $studentId,
                        null,
                        [
                            'is_sponsored' => !empty($row['is_sponsored']) ? 1 : 0,
                            'sponsor_waiver_percentage' => (float) ($row['sponsor_waiver_percentage'] ?? 0),
                        ]
                    );

                    $this->db->commit();
                    $createdCount++;
                } catch (\Exception $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $failedCount++;
                    $errors[] = [
                        'row' => $rowIndex,
                        'admission_no' => $row['admission_no'] ?? null,
                        'message' => 'Insert failed'
                    ];
                }
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student creation completed',
                'data' => [
                    'created' => $createdCount,
                    'failed' => $failedCount,
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

            $rows = $result['data'] ?? [];
            if (empty($rows)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No rows to update'
                ], 400);
            }

            // Map input keys to live persons columns (date_of_birth -> dob)
            $personKeyMap = [
                'first_name' => 'first_name',
                'middle_name' => 'middle_name',
                'last_name' => 'last_name',
                'date_of_birth' => 'dob',
                'dob' => 'dob',
                'gender' => 'gender',
                'photo_url' => 'photo_url',
            ];
            $studentCols = [
                'student_type_id', 'admission_date', 'assessment_number', 'assessment_status',
                'nemis_number', 'nemis_status', 'status', 'blood_group'
            ];

            $updated = 0;
            $failed = 0;
            $errors = [];

            foreach ($rows as $idx => $row) {
                $admissionNo = $row['admission_no'] ?? $row['admission_number'] ?? null;
                if (!$admissionNo) {
                    $errors[] = ['row' => $idx + 1, 'message' => 'Missing admission_no'];
                    $failed++;
                    continue;
                }

                $stmt = $this->db->prepare("
                    SELECT id, person_id FROM students WHERE admission_no = ? LIMIT 1
                ");
                $stmt->execute([$admissionNo]);
                $stu = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$stu) {
                    $errors[] = [
                        'row' => $idx + 1,
                        'admission_no' => $admissionNo,
                        'message' => 'Student not found'
                    ];
                    $failed++;
                    continue;
                }

                $personId = (int) $stu['person_id'];
                $studentId = (int) $stu['id'];

                try {
                    $this->db->beginTransaction();

                    $pSet = [];
                    $pVals = [];
                    foreach ($personKeyMap as $inputKey => $colName) {
                        if (array_key_exists($inputKey, $row) && $row[$inputKey] !== '') {
                            $pSet[] = "$colName = ?";
                            $pVals[] = $row[$inputKey];
                        }
                    }
                    if (!empty($pSet)) {
                        $pVals[] = $personId;
                        $stmt = $this->db->prepare("UPDATE persons SET " . implode(', ', $pSet) . " WHERE id = ?");
                        $stmt->execute($pVals);
                    }

                    $sSet = [];
                    $sVals = [];
                    foreach ($studentCols as $col) {
                        if (array_key_exists($col, $row) && $row[$col] !== '') {
                            $sSet[] = "$col = ?";
                            $sVals[] = $row[$col];
                        }
                    }
                    if (!empty($sSet)) {
                        $sVals[] = $studentId;
                        $stmt = $this->db->prepare("UPDATE students SET " . implode(', ', $sSet) . " WHERE id = ?");
                        $stmt->execute($sVals);
                    }

                    if (array_key_exists('stream_id', $row) && $row['stream_id'] !== '') {
                        $newStreamId = (int) $row['stream_id'];
                        if ($newStreamId > 0) {
                            $this->ensureClassEnrollment($studentId, $newStreamId);
                        }
                    }

                    $this->db->commit();
                    $updated++;
                } catch (\Exception $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    $errors[] = [
                        'row' => $idx + 1,
                        'admission_no' => $admissionNo,
                        'message' => 'Update failed'
                    ];
                    $failed++;
                }
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Bulk student update completed',
                'data' => [
                    'updated' => $updated,
                    'failed' => $failed,
                    'errors' => $errors
                ]
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
                SELECT s.id, s.status, sae.academic_year_class_stream_id AS stream_id
                FROM students s
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                WHERE s.id = ?
                ORDER BY sae.id DESC
                LIMIT 1
            ");
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return $this->response(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $currentStreamId = (int) ($student['stream_id'] ?? 0);
            $currentStream = $currentStreamId > 0 ? $this->resolveClassFromStream($currentStreamId) : null;
            if ($currentStreamId > 0 && !$currentStream) {
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

                // students.stream_id does not exist on the live schema; the active enrollment row carries the assignment.
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

            // External transfer request — delegate to TransferWorkflow (live: student_transitions + student_clearances)
            $transferWorkflow = new TransferWorkflow();
            $result = $transferWorkflow->initiateTransfer([
                'student_id' => $studentId,
                'transfer_type' => 'external',
                'transfer_reason' => $reason,
                'request_date' => date('Y-m-d H:i:s'),
                'transfer_to_school' => $transferToSchool,
            ]);

            if (empty($result['success'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Transfer initiation failed'
                ], 400);
            }

            $transferId = (int) ($result['data']['transfer_id'] ?? 0);
            $this->logAction('create', $transferId, "Started external transfer request for student {$studentId} to {$transferToSchool}");

            return $this->response([
                'status' => 'success',
                'message' => $result['message'] ?? 'External transfer request started successfully',
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

            $studentId = (int) $data['student_id'];

            // Live: use vw_student_fee_balances for outstanding balance (live schema has no student_fee_obligations.student_id or balance column)
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(GREATEST(balance, 0)), 0) AS pending_balance,
                       SUM(CASE WHEN payment_status IN ('pending', 'partial') THEN 1 ELSE 0 END) AS pending_fees
                FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . "
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $feeCheck = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['pending_balance' => 0, 'pending_fees' => 0];

            $eligible = ((int) $feeCheck['pending_fees'] === 0);
            $notes = $eligible
                ? 'No pending fee obligations - eligible for transfer'
                : 'Student has outstanding fee obligations';

            return $this->response([
                'status' => 'success',
                'data' => [
                    'eligible' => $eligible,
                    'notes' => $notes,
                    'pending_fees' => (int) $feeCheck['pending_fees'],
                    'pending_balance' => (float) $feeCheck['pending_balance'],
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

            if (!in_array($data['decision'], ['approved', 'rejected'], true)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid decision. Must be: approved or rejected'
                ], 400);
            }

            $transferId = (int) $data['transfer_id'];

            $transferWorkflow = new TransferWorkflow();
            $result = $transferWorkflow->approveTransfer($transferId, [
                'decision' => $data['decision'],
                'notes' => $data['notes'] ?? null,
                'approved_by' => $this->getCurrentUserId(),
            ]);

            if (empty($result['success'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Transfer approval failed'
                ], 400);
            }

            $this->logAction('update', $transferId, "Transfer decision: {$data['decision']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer decision recorded successfully'
            ]);
        } catch (Exception $e) {
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

            $transferId = (int) $data['transfer_id'];

            // Resolve student from student_transitions (live)
            $stmt = $this->db->prepare("
                SELECT student_id FROM student_transitions
                WHERE id = ? AND transition_type IN ('transfer', 'internal')
                LIMIT 1
            ");
            $stmt->execute([$transferId]);
            $studentId = (int) ($stmt->fetchColumn() ?: 0);
            if (!$studentId) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            $this->db->beginTransaction();

            // Mark transition as executed (live: no status column; executed_at drives completion)
            $stmt = $this->db->prepare("
                UPDATE student_transitions
                SET executed_at = COALESCE(executed_at, NOW()),
                    decided_by  = COALESCE(decided_by, ?)
                WHERE id = ? AND executed_at IS NULL
            ");
            $stmt->execute([$this->getCurrentUserId(), $transferId]);

            // Update student status to 'transferred'
            $stmt = $this->db->prepare("UPDATE students SET status = 'transferred', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$studentId]);

            // Mark active enrollment as transferred
            $stmt = $this->db->prepare("
                UPDATE student_academic_enrollments
                SET enrollment_status = 'transferred'
                WHERE student_id = ? AND enrollment_status = 'active'
            ");
            $stmt->execute([$studentId]);

            $this->db->commit();
            $this->logAction('update', $transferId, "Transfer executed for student {$studentId}");

            return $this->response([
                'status' => 'success',
                'message' => 'Transfer executed successfully'
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function getTransferWorkflowStatus($instanceId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT st.id,
                       st.student_id,
                       st.academic_year_id,
                       st.transition_type,
                       st.reason,
                       st.decided_by,
                       st.decided_at,
                       st.executed_at,
                       s.admission_no,
                       s.status AS student_status,
                       CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name
                FROM student_transitions st
                JOIN students s ON s.id = st.student_id
                JOIN persons per ON per.id = s.person_id
                WHERE st.id = ?
                  AND st.transition_type IN ('transfer', 'internal')
                LIMIT 1
            ");
            $stmt->execute([(int) $instanceId]);
            $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transfer) {
                return $this->response(['status' => 'error', 'message' => 'Transfer not found'], 404);
            }

            // Derive workflow state from executed_at/decided_at (live has no status column)
            if (!empty($transfer['executed_at'])) {
                $transfer['status'] = 'transferred';
            } elseif (!empty($transfer['decided_at'])) {
                $transfer['status'] = 'approved';
            } else {
                $transfer['status'] = 'pending_approval';
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
                LEFT JOIN admission_applications aa ON aa.id = s.application_id
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
                $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ? OR sd.description LIKE ?)";
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
                FROM discipline_incidents sd
                JOIN student_academic_enrollments sae ON sae.id = sd.student_academic_enrollment_id
                JOIN students s ON s.id = sae.student_id
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                {$where}
            ";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($bindings);
            $total = (int) $stmt->fetchColumn();

            $sql = "
                SELECT
                    sd.*,
                    s.admission_no,
                    per.first_name,
                    per.last_name,
                    per.gender,
                    sm.name AS stream_name,
                    c.name AS class_name
                FROM discipline_incidents sd
                JOIN student_academic_enrollments sae ON sae.id = sd.student_academic_enrollment_id
                JOIN students s ON s.id = sae.student_id
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                LEFT JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
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
                FROM discipline_incidents
            ");
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total' => 0,
                'pending' => 0,
                'resolved' => 0,
                'escalated' => 0
            ];

            $termCount = 0;
            $termStmt = $this->db->query("
                SELECT ayt.opening_date, ayt.closing_date
                FROM academic_year_terms ayt
                WHERE ayt.status = 'current'
                ORDER BY ayt.opening_date DESC
                LIMIT 1
            ");
            $term = $termStmt->fetch(PDO::FETCH_ASSOC);
            if ($term) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM discipline_incidents 
                    WHERE incident_date BETWEEN ? AND ?
                ");
                $stmt->execute([$term['opening_date'], $term['closing_date']]);
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
            // Live schema: transfers live in student_transitions (transition_type IN ('transfer','internal')).
            $stmt = $this->db->prepare("
                SELECT st.id,
                       st.student_id,
                       st.academic_year_id,
                       ay.year_code,
                       ay.year_name,
                       st.transition_type,
                       st.reason,
                       st.decided_by,
                       st.decided_at,
                       st.executed_at,
                       CASE
                           WHEN st.executed_at IS NOT NULL THEN 'transferred'
                           WHEN st.decided_at IS NOT NULL THEN 'approved'
                           ELSE 'pending_approval'
                       END AS status,
                       from_ayc.class_id AS from_class_id,
                       c_from.name AS from_class_name,
                       from_sm.name AS from_stream_name,
                       to_ayc.class_id AS to_class_id,
                       c_to.name AS to_class_name,
                       to_sm.name AS to_stream_name
                FROM student_transitions st
                LEFT JOIN academic_years ay ON ay.id = st.academic_year_id
                LEFT JOIN student_academic_enrollments from_sae ON from_sae.id = st.from_student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams from_aycs ON from_aycs.id = from_sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes from_ayc ON from_ayc.id = from_aycs.academic_year_class_id
                LEFT JOIN classes c_from ON c_from.id = from_ayc.class_id
                LEFT JOIN streams from_sm ON from_sm.id = from_aycs.stream_id
                LEFT JOIN student_academic_enrollments to_sae ON to_sae.id = st.to_student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams to_aycs ON to_aycs.id = to_sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes to_ayc ON to_ayc.id = to_aycs.academic_year_class_id
                LEFT JOIN classes c_to ON c_to.id = to_ayc.class_id
                LEFT JOIN streams to_sm ON to_sm.id = to_aycs.stream_id
                WHERE st.student_id = ?
                  AND st.transition_type IN ('transfer', 'internal')
                ORDER BY COALESCE(st.executed_at, st.decided_at, st.id) DESC
            ");
            $stmt->execute([(int) $id]);
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
                SELECT st.id,
                       st.student_id,
                       st.academic_year_id,
                       ay.year_code,
                       ay.year_name,
                       st.transition_type,
                       st.reason,
                       st.decided_by,
                       st.decided_at,
                       st.executed_at,
                       CASE
                           WHEN st.executed_at IS NOT NULL THEN 'approved'
                           WHEN st.decided_at IS NOT NULL THEN 'pending'
                           ELSE 'pending'
                       END AS status,
                       from_ayc.class_id AS from_class_id,
                       c_from.name AS from_class_name,
                       from_sm.name AS from_stream_name,
                       to_ayc.class_id AS to_class_id,
                       c_to.name AS to_class_name,
                       to_sm.name AS to_stream_name
                FROM student_transitions st
                LEFT JOIN academic_years ay ON ay.id = st.academic_year_id
                LEFT JOIN student_academic_enrollments from_sae ON from_sae.id = st.from_student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams from_aycs ON from_aycs.id = from_sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes from_ayc ON from_ayc.id = from_aycs.academic_year_class_id
                LEFT JOIN classes c_from ON c_from.id = from_ayc.class_id
                LEFT JOIN streams from_sm ON from_sm.id = from_aycs.stream_id
                LEFT JOIN student_academic_enrollments to_sae ON to_sae.id = st.to_student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams to_aycs ON to_aycs.id = to_sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes to_ayc ON to_ayc.id = to_aycs.academic_year_class_id
                LEFT JOIN classes c_to ON c_to.id = to_ayc.class_id
                LEFT JOIN streams to_sm ON to_sm.id = to_aycs.stream_id
                WHERE st.student_id = ?
                  AND st.transition_type IN ('promotion', 'graduation')
                ORDER BY COALESCE(st.decided_at, st.id) DESC
            ");
            $stmt->execute([(int) $id]);
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
            // Live schema: student_academic_enrollments has only enrolled_on (no enrollment_date, promotion_status, promotion_date).
            $sql = "
                SELECT
                    sae.id AS enrollment_id,
                    sae.student_id,
                    sae.academic_year_id,
                    ay.year_code,
                    ay.year_name,
                    ayc.class_id,
                    c.name AS class_name,
                    aycs.stream_id,
                    sm.name AS stream_name,
                    sae.enrollment_status,
                    sae.enrolled_on AS enrollment_date
                FROM student_academic_enrollments sae
                LEFT JOIN academic_years ay ON sae.academic_year_id = ay.id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams sm ON sm.id = aycs.stream_id
                WHERE sae.student_id = ?
                ORDER BY ay.start_date DESC, sae.enrolled_on DESC, sae.id DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $studentId]);
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
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN admission_applications aa ON aa.applicant_name = CONCAT(per.first_name, ' ', per.last_name)
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
                       per.first_name,
                       per.last_name,
                       per.middle_name,
                       per.gender,
                       per.dob AS date_of_birth,
                       per.photo_url,
                       sm.name AS stream_name,
                       c.name AS class_name,
                       MAX(CASE WHEN tc.term_id = 1 THEN tc.avg_overall_percentage END) AS term1_average,
                       MAX(CASE WHEN tc.term_id = 2 THEN tc.avg_overall_percentage END) AS term2_average,
                       MAX(CASE WHEN tc.term_id = 3 THEN tc.avg_overall_percentage END) AS term3_average,
                       MAX(tc.avg_overall_percentage) AS year_average,
                       MAX(tc.avg_overall_grade) AS overall_grade,
                       MAX(tc.class_position) AS class_rank,
                       sa.attendance_percentage,
                       sa.days_present,
                       sa.days_absent
                FROM students s
                JOIN persons per ON per.id = s.person_id
                JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                JOIN streams sm ON sm.id = aycs.stream_id
                JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN term_consolidations tc
                    ON tc.student_id = s.id
                   AND tc.academic_year = CAST(SUBSTRING((SELECT year_code FROM academic_years WHERE id = sae.academic_year_id),1,4) AS UNSIGNED)
                LEFT JOIN (
                    SELECT vws.student_id,
                           ROUND(
                               (SUM(vws.status IN ('present', 'late')) / NULLIF(COUNT(*), 0)) * 100,
                               1
                           ) AS attendance_percentage,
                           SUM(vws.status IN ('present', 'late')) AS days_present,
                           SUM(vws.status = 'absent') AS days_absent
                    FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_attendance_summary') . " vws
                    GROUP BY vws.student_id
                ) sa ON sa.student_id = s.id
                WHERE c.id = ? AND s.status = 'active'
                GROUP BY s.id, per.first_name, per.last_name, per.middle_name,
                         per.gender, per.dob, per.photo_url,
                         sm.name, c.name,
                         sa.attendance_percentage, sa.days_present, sa.days_absent
                ORDER BY per.last_name, per.first_name
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
            // $streamId in the new schema is academic_year_class_streams.id
            $stmt = $this->db->prepare("
                SELECT s.*, per.first_name, per.last_name, per.middle_name,
                       per.gender, per.dob AS date_of_birth, per.photo_url
                FROM students s
                JOIN persons per ON per.id = s.person_id
                JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                WHERE sae.academic_year_class_stream_id = ?
                  AND s.status = 'active'
                ORDER BY per.last_name, per.first_name
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
            $year = $this->db->query("SELECT year_code FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1")->fetchColumn();
            $termStmt = $this->db->query(
                "SELECT ayt.opening_date, ayt.closing_date
                 FROM academic_year_terms ayt
                 JOIN academic_years ay ON ay.id=ayt.academic_year_id
                 WHERE ay.is_current=1 AND ayt.status='current'
                 ORDER BY ayt.id DESC LIMIT 1"
            );
            $term = $termStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $conditions = [];
            $bindings = [];
            $scope = $this->buildStudentVisibilityScope();
            if (!empty($scope['restricted'])) {
                $scopeParts = [];
                if (!empty($scope['student_ids'])) {
                    $scopeParts[] = 's.id IN (' . implode(',', array_fill(0, count($scope['student_ids']), '?')) . ')';
                    $bindings = array_merge($bindings, $scope['student_ids']);
                }
                if (!empty($scope['stream_ids'])) {
                    $scopeParts[] = 'aycs.id IN (' . implode(',', array_fill(0, count($scope['stream_ids']), '?')) . ')';
                    $bindings = array_merge($bindings, $scope['stream_ids']);
                }
                if (!empty($scope['class_ids'])) {
                    $scopeParts[] = 'ayc.class_id IN (' . implode(',', array_fill(0, count($scope['class_ids']), '?')) . ')';
                    $bindings = array_merge($bindings, $scope['class_ids']);
                }
                $conditions[] = $scopeParts ? '(' . implode(' OR ', $scopeParts) . ')' : '1=0';
            }

            $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $feeYear = $year ? 'WHERE CAST(SUBSTRING(academic_year,1,4) AS UNSIGNED)=?' : '';
            $feeParams = $year ? [(int)preg_replace('/[^0-9].*$/', '', (string)$year)] : [];
            $feeView = \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances');
            $joins = "
                LEFT JOIN student_academic_enrollments sae ON sae.student_id=s.id
                    AND sae.enrollment_status='active'
                    AND sae.id=(SELECT MAX(x.id) FROM student_academic_enrollments x
                                WHERE x.student_id=s.id AND x.enrollment_status='active')
                LEFT JOIN academic_year_class_streams aycs ON aycs.id=sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
                LEFT JOIN (
                    SELECT student_id, SUM(balance) AS total_balance, SUM(amount_due) AS total_due
                    FROM {$feeView} {$feeYear} GROUP BY student_id
                ) fees ON fees.student_id=s.id";
            $sql = "SELECT COUNT(*) AS total,
                           SUM(s.status='active') AS active,
                           SUM(s.status<>'active') AS inactive,
                           SUM(CASE WHEN ? IS NOT NULL AND COALESCE(s.admission_date, sae.enrolled_on, s.created_at) BETWEEN ? AND ? THEN 1 ELSE 0 END) AS new_this_term,
                           SUM(CASE WHEN COALESCE(fees.total_balance,0)>0 THEN 1 ELSE 0 END) AS with_outstanding_fees,
                           SUM(CASE WHEN COALESCE(fees.total_due,0)>0 AND COALESCE(fees.total_balance,0)<=0 THEN 1 ELSE 0 END) AS fully_paid,
                           COALESCE(SUM(GREATEST(COALESCE(fees.total_balance,0),0)),0) AS total_outstanding
                    FROM students s JOIN persons per ON per.id=s.person_id {$joins} {$where}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge([$term['opening_date'] ?? null, $term['opening_date'] ?? null, $term['closing_date'] ?? null], $feeParams, $bindings));
            $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $byGenderStmt = $this->db->prepare("SELECT per.gender, COUNT(*) AS count FROM students s JOIN persons per ON per.id=s.person_id {$joins} {$where} GROUP BY per.gender");
            $byGenderStmt->execute(array_merge($feeParams, $bindings));
            $byGender = $byGenderStmt->fetchAll(PDO::FETCH_ASSOC);
            $byClass = [];

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => (int)($summary['total'] ?? 0),
                    'active' => (int)($summary['active'] ?? 0),
                    'inactive' => (int)($summary['inactive'] ?? 0),
                    'new_this_term' => (int)($summary['new_this_term'] ?? 0),
                    'with_outstanding_fees' => (int)($summary['with_outstanding_fees'] ?? 0),
                    'fully_paid' => (int)($summary['fully_paid'] ?? 0),
                    'total_outstanding' => (float)($summary['total_outstanding'] ?? 0),
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

            $yearRecord = $this->getCurrentAcademicYearRecord();
            $fromYearId = (int) ($yearRecord['id'] ?? 0);
            $fromYearVal = $this->extractAcademicYearNumber($yearRecord);
            if (!$fromYearId || !$fromYearVal) {
                return $this->response(['status' => 'error', 'message' => 'No active academic year found'], 400);
            }

            $targetStmt = $this->db->prepare("
                SELECT id FROM academic_years
                WHERE CAST(SUBSTRING(year_code, 1, 4) AS UNSIGNED) = ?
                LIMIT 1
            ");
            $targetStmt->execute([$fromYearVal + 1]);
            $toYearId = (int) ($targetStmt->fetchColumn() ?: 0);
            if (!$toYearId) {
                return $this->response(['status' => 'error', 'message' => 'Next academic year is not set up yet'], 400);
            }

            $studentIds = array_map('intval', (array) $data['student_ids']);

            $result = $this->promotionManager->promoteMultipleStudents(
                $studentIds,
                (int) $data['to_class_id'],
                (int) $data['to_stream_id'],
                $fromYearId,
                $toYearId,
                $this->getCurrentUserId(),
                $data['remarks'] ?? null
            );

            return $this->response([
                'status' => 'success',
                'message' => "Promoted {$result['promoted']} students successfully",
                'data' => [
                    'promoted_count' => $result['promoted'],
                    'failed_count' => $result['failed'],
                    'errors' => $result['errors'] ?? []
                ]
            ]);
        } catch (Exception $e) {
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
                $sql = "UPDATE discipline_incidents SET " . implode(', ', $updates) . " WHERE id = ?";
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
            $sql = "UPDATE discipline_incidents 
                    SET status = 'resolved', 
                        action_taken = ?
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['action_taken'] ?? 'Resolved',
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
            // Validate gender if provided
            if (isset($data['gender']) && !in_array($data['gender'], ['male', 'female', 'other'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid gender value. Must be: male, female, or other'
                ], 400);
            }

            // Live schema: person fields live on persons; parent fields on parents.
            $personStmt = $this->db->prepare("SELECT person_id FROM parents WHERE id = ? LIMIT 1");
            $personStmt->execute([(int) $id]);
            $personId = (int) ($personStmt->fetchColumn() ?: 0);
            if (!$personId) {
                return $this->response(['status' => 'error', 'message' => 'Parent not found'], 404);
            }

            $personFieldMap = [
                'first_name' => 'first_name',
                'middle_name' => 'middle_name',
                'last_name' => 'last_name',
                'gender' => 'gender',
                'email' => 'email',
                'phone_1' => 'phone',
                'phone' => 'phone',
                'national_id_no' => 'national_id_no',
            ];
            $parentFieldMap = ['occupation' => 'occupation', 'address' => 'address', 'status' => 'status'];

            $personSet = [];
            $personParams = [];
            foreach ($personFieldMap as $inputKey => $col) {
                if (isset($data[$inputKey])) {
                    $personSet[] = "$col = ?";
                    $personParams[] = $data[$inputKey];
                }
            }
            if (!empty($personSet)) {
                $personParams[] = $personId;
                $stmt = $this->db->prepare("UPDATE persons SET " . implode(', ', $personSet) . " WHERE id = ?");
                $stmt->execute($personParams);
            }

            $parentSet = [];
            $parentParams = [];
            foreach ($parentFieldMap as $inputKey => $col) {
                if (isset($data[$inputKey])) {
                    $parentSet[] = "$col = ?";
                    $parentParams[] = $data[$inputKey];
                }
            }
            if (!empty($parentSet)) {
                $parentParams[] = $id;
                $stmt = $this->db->prepare("UPDATE parents SET " . implode(', ', $parentSet) . " WHERE id = ?");
                $stmt->execute($parentParams);
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
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
            if (!empty($data['application_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Admissions must be completed through the admissions workflow; existing-student registration cannot carry an application.'
                ], 400);
            }

            // Required fields for existing students
            $required = ['first_name', 'last_name', 'date_of_birth', 'gender', 'class_id'];
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

            $validStatuses = ['active', 'inactive', 'graduated', 'transferred', 'suspended'];
            $studentStatus = (string) ($data['status'] ?? 'active');
            if (!in_array($studentStatus, $validStatuses, true)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Invalid student status'
                ], 400);
            }

            $this->db->beginTransaction();

            // Existing learners may supply their numeric school register
            // number. Store it with the canonical KPS prefix; otherwise carry
            // on from the latest inserted KPS number.
            $admissionNumbers = new AdmissionNumberService($this->db);
            $submittedAdmissionNo = trim((string) ($data['admission_no'] ?? ''));
            $candidate = $submittedAdmissionNo !== ''
                ? $admissionNumbers->normalizeExisting($submittedAdmissionNo)
                : null;
            $data['admission_no'] = ($candidate !== null && $admissionNumbers->isAvailable($candidate))
                ? $candidate
                : $admissionNumbers->generateNextExisting();

            if (empty($data['stream_name'])) {
                throw new Exception('A configured stream is required for an existing learner');
            }
            $streamId = $this->getOrCreateStreamId($data['class_id'], $data['stream_name']);

            $personStmt = $this->db->prepare("
                INSERT INTO persons (first_name, middle_name, last_name, dob, gender, photo_url, email, phone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $personStmt->execute([
                $data['first_name'],
                $data['middle_name'] ?? null,
                $data['last_name'],
                $data['date_of_birth'],
                $data['gender'],
                $data['photo_url'] ?? null,
                $data['email'] ?? null,
                $data['phone'] ?? null,
            ]);
            $personId = (int) $this->db->lastInsertId();

            $studentStmt = $this->db->prepare("
                INSERT INTO students (
                    person_id, admission_no, student_type_id, admission_date,
                    assessment_number, assessment_status, nemis_number, nemis_status,
                    blood_group, status, entry_source, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'existing_student', NOW())
            ");
            $studentStmt->execute([
                $personId,
                $data['admission_no'],
                $data['student_type_id'] ?? 1,
                $data['admission_date'] ?? null,
                $data['assessment_number'] ?? null,
                !empty($data['assessment_number']) ? 'assigned' : ($data['assessment_status'] ?? 'not_assigned'),
                $data['nemis_number'] ?? null,
                $data['nemis_status'] ?? (!empty($data['nemis_number']) ? 'assigned' : 'not_assigned'),
                $data['blood_group'] ?? null,
                $studentStatus,
            ]);
            $studentId = (int) $this->db->lastInsertId();

            // Persist the school sponsorship award before enrollment billing
            // creates fee obligations, so the trigger sees the award.
            if (!empty($data['school_sponsorship'])) {
                $this->saveExistingSchoolSponsorship($studentId, $data['school_sponsorship']);
            }

            // Enroll into class/stream for the current year
            $enrollmentId = $this->ensureClassEnrollment($studentId, $streamId);

            // The enrollment trigger may run before the application method
            // returns and seed all annual rows. For a newly imported learner
            // there is no historical ledger to protect, so normalize that
            // trigger output to the current term and future terms only.
            if ($enrollmentId) {
                $termStmt = $this->db->query(
                    "SELECT COALESCE(
                        MAX(CASE WHEN ayt.status = 'current' THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                        MAX(CASE WHEN CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                        MIN(CASE WHEN ayt.opening_date >= CURDATE() THEN CAST(SUBSTRING(t.code, 2) AS UNSIGNED) END),
                        1
                    )
                     FROM academic_year_terms ayt
                     JOIN terms t ON t.id = ayt.term_id
                     JOIN academic_years ay ON ay.id = ayt.academic_year_id
                     WHERE ay.is_current = 1"
                );
                $currentTermNumber = (int) ($termStmt ? $termStmt->fetchColumn() : 1);
                $cleanStmt = $this->db->prepare(
                    "DELETE sfo
                     FROM student_fee_obligations sfo
                     JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
                     JOIN terms t ON t.id = ayt.term_id
                     WHERE sfo.student_academic_enrollment_id = ?
                       AND CAST(SUBSTRING(t.code, 2) AS UNSIGNED) < ?"
                );
                $cleanStmt->execute([(int) $enrollmentId, $currentTermNumber]);
            }

            // The enrollment trigger creates the obligations before returning.
            // Apply this learner's active school award to those rows so the
            // first ledger balance already reflects the approved sponsorship.
            $this->applyExistingStudentSponsorshipToObligations($studentId);

            // Fee waivers are separate concessions from sponsorship awards.
            if (!empty($data['school_fee_waiver'])) {
                $this->saveExistingSchoolFeeWaiver($studentId, $data['school_fee_waiver']);
            }

            // The AFTER INSERT enrollment trigger is the single billing
            // authority. It invokes sp_onboard_student_enrollment, which now
            // applies the current-term/future-term rule. Calling the legacy
            // PHP generator here as well could reintroduce an all-term bill.

            // Add parent/guardian if provided
            if (!empty($data['parent'])) {
                $this->addStudentParent($studentId, $data['parent']);
            }

            if ((float) ($data['initial_payment'] ?? 0) > 0) {
                if (!empty($data['financial_migration'])) {
                    throw new \InvalidArgumentException('Use either initial_payment or financial_migration, not both');
                }
                $this->recordInitialPayment($studentId, [
                    'amount' => (float) $data['initial_payment'],
                    'method' => $data['payment_method'] ?? 'bank_transfer',
                    'reference' => $data['payment_reference'] ?? null,
                    'receipt_no' => $data['receipt_no'] ?? null,
                    'payment_date' => $data['payment_date'] ?? null,
                    'notes' => 'Opening balance payment imported for existing learner',
                ]);
            }

            if (!empty($data['financial_migration'])) {
                $financial = (!empty($data['migration_mode']) || !empty($data['financial_migration']['calculate_from_schedule']))
                    ? $this->deriveExistingImportFinancialPosition($data, $studentId)
                    : $data['financial_migration'];
                foreach (['academic_year_paid_amount', 'current_term_paid_amount', 'fee_arrears_amount', 'advance_amount'] as $field) {
                    if (isset($financial[$field]) && (!is_numeric($financial[$field]) || (float) $financial[$field] < 0)) {
                        throw new \InvalidArgumentException("{$field} must be a non-negative number");
                    }
                }
                if ((float) ($financial['current_term_paid_amount'] ?? 0) > (float) ($financial['academic_year_paid_amount'] ?? 0)) {
                    throw new \InvalidArgumentException('Current-term paid amount cannot exceed academic-year paid amount');
                }
                $this->saveFinancialMigrationSnapshot($studentId, $financial);
                $this->applyFinancialMigrationToObligations($studentId, $financial);
            }

            if ($this->db->inTransaction()) {
                $this->db->commit();
            }

            // Transport is part of the manual registration payload. Persist it
            // on the server after the student transaction commits so the
            // browser cannot silently discard a valid arrangement when the
            // second request is skipped or interrupted.
            $transportSaved = false;
            if (!empty($data['transport_arrangement'])) {
                $transport = new \App\API\Modules\transport\StudentTransportEntitlementManager($this->db);
                $transport->enrollStudent(
                    array_merge($data['transport_arrangement'], ['student_id' => $studentId]),
                    (int) ($this->getCurrentUserId() ?? 0)
                );
                $transportSaved = true;
            }

            // QR artwork is supplementary. A missing image extension or
            // storage problem must never roll back the learner's registration.
            $qrGenerated = false;
            try {
                $qrResult = $this->generateQRCode($studentId);
                $qrGenerated = ($qrResult['status'] ?? null) === 'success';
            } catch (\Throwable $qrError) {
                $this->logError($qrError, 'Existing student QR generation failed after registration');
            }

            $this->logAction('create', $studentId, "Added existing student: {$data['first_name']} {$data['last_name']} (Quick Add)");

            return $this->response([
                'status' => 'success',
                'message' => 'Existing student added successfully',
                'data' => [
                    'id' => $studentId,
                    'admission_no' => $data['admission_no'],
                    'transport_saved' => $transportSaved,
                    'qr_generated' => $qrGenerated,
                ]
            ], 201);

        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->response([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /** Store a school scholarship programme award for an existing learner. */
    private function saveExistingSchoolSponsorship(int $studentId, array $data): void
    {
        $programId = (int) ($data['scholarship_program_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($programId <= 0 || $reason === '') {
            throw new \InvalidArgumentException('A school sponsorship programme and approval reason are required');
        }

        $programStmt = $this->db->prepare(
            "SELECT id, coverage_type, default_percentage, default_amount
             FROM scholarship_programs WHERE id=? AND is_active=1 LIMIT 1"
        );
        $programStmt->execute([$programId]);
        $program = $programStmt->fetch(PDO::FETCH_ASSOC);
        if (!$program) throw new \InvalidArgumentException('The selected school sponsorship programme is not active');

        $yearStmt = $this->db->query("SELECT id FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1");
        $academicYearId = (int) ($yearStmt->fetchColumn() ?: 0);
        if ($academicYearId <= 0) throw new \InvalidArgumentException('No current academic year is configured');

        $coverageType = (string) $program['coverage_type'];
        $coveragePercentage = null;
        $coverageAmount = null;
        if ($coverageType === 'percentage') {
            $coveragePercentage = (float) ($data['coverage_percentage'] ?? $program['default_percentage'] ?? 0);
            if ($coveragePercentage < 0 || $coveragePercentage > 100) {
                throw new \InvalidArgumentException('Sponsorship percentage must be between 0 and 100');
            }
        } elseif ($coverageType === 'fixed_amount') {
            $coverageAmount = (float) ($data['coverage_amount'] ?? $program['default_amount'] ?? 0);
            if ($coverageAmount <= 0) throw new \InvalidArgumentException('A fixed school grant amount per obligation is required');
        }

        $periodType = (string) ($data['period_type'] ?? 'academic_year');
        if (!in_array($periodType, ['term', 'academic_year', 'custom'], true)) {
            throw new \InvalidArgumentException('Invalid sponsorship period');
        }
        if ($periodType === 'term') {
            $termStmt = $this->db->prepare('SELECT opening_date, closing_date FROM academic_year_terms WHERE id=? AND academic_year_id=?');
            $termStmt->execute([(int) ($data['academic_year_term_id'] ?? 0), $academicYearId]);
            $term = $termStmt->fetch(PDO::FETCH_ASSOC);
            if (!$term) throw new \InvalidArgumentException('The selected sponsorship term is invalid');
            $startsOn = $term['opening_date'];
            $endsOn = $term['closing_date'];
        } elseif ($periodType === 'custom') {
            $startsOn = $data['starts_on'] ?? null;
            $endsOn = $data['ends_on'] ?? null;
            if (!$startsOn || !$endsOn || $startsOn > $endsOn) {
                throw new \InvalidArgumentException('Custom sponsorship period must have valid start and end dates');
            }
        } else {
            $yearDates = $this->db->prepare('SELECT start_date, end_date FROM academic_years WHERE id=?');
            $yearDates->execute([$academicYearId]);
            $dates = $yearDates->fetch(PDO::FETCH_ASSOC);
            if (!$dates) throw new \InvalidArgumentException('The selected academic year is invalid');
            $startsOn = $dates['start_date'];
            $endsOn = $dates['end_date'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO student_scholarship_awards
                (student_id, scholarship_program_id, academic_year_id, period_type, coverage_type,
                 coverage_percentage, coverage_amount, reason, starts_on, ends_on, status, awarded_by, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'active',?,?)
             ON DUPLICATE KEY UPDATE
                scholarship_program_id=VALUES(scholarship_program_id),
                period_type=VALUES(period_type), coverage_type=VALUES(coverage_type), coverage_percentage=VALUES(coverage_percentage),
                coverage_amount=VALUES(coverage_amount), reason=VALUES(reason), status='active',
                starts_on=VALUES(starts_on), ends_on=VALUES(ends_on),
                awarded_by=VALUES(awarded_by), revoked_by=NULL, revoked_at=NULL,
                updated_at=NOW()"
        );
        $stmt->execute([
            $studentId, $programId, $academicYearId, $periodType, $coverageType,
            $coveragePercentage, $coverageAmount, $reason, $startsOn, $endsOn,
            (int) ($this->getCurrentUserId() ?? 0),
            'Created during existing-student registration',
        ]);
    }

    /** Apply active school sponsorship awards to generated fee obligations. */
    private function applyExistingStudentSponsorshipToObligations(int $studentId): void
    {
        $awardsStmt = $this->db->prepare(
            "SELECT id, academic_year_id, coverage_type, coverage_percentage, coverage_amount,
                    starts_on, ends_on
             FROM student_scholarship_awards
             WHERE student_id = ? AND status = 'active'
             ORDER BY id"
        );
        $awardsStmt->execute([$studentId]);
        $update = $this->db->prepare(
            "UPDATE student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             SET sfo.is_sponsored = 1,
                 sfo.sponsored_waiver_amount = LEAST(sfo.amount_due, CASE
                    WHEN ? = 'full' THEN sfo.amount_due
                    WHEN ? = 'percentage' THEN sfo.amount_due * ? / 100
                    ELSE ? END),
                 sfo.status = CASE
                    WHEN LEAST(sfo.amount_due, CASE
                        WHEN ? = 'full' THEN sfo.amount_due
                        WHEN ? = 'percentage' THEN sfo.amount_due * ? / 100
                        ELSE ? END) >= sfo.amount_due THEN 'paid'
                    ELSE sfo.status END,
                 sfo.updated_at = NOW()
             WHERE sae.student_id = ? AND sfo.academic_year_id = ?
               AND ( ? IS NULL OR ayt.closing_date >= ? )
               AND ( ? IS NULL OR ayt.opening_date <= ? )"
        );
        foreach ($awardsStmt->fetchAll(PDO::FETCH_ASSOC) as $award) {
            $type = (string) $award['coverage_type'];
            $percentage = (float) ($award['coverage_percentage'] ?? 0);
            $amount = (float) ($award['coverage_amount'] ?? 0);
            $starts = $award['starts_on'] ?: null;
            $ends = $award['ends_on'] ?: null;
            $update->execute([
                $type, $type, $percentage, $amount,
                $type, $type, $percentage, $amount,
                $studentId, (int) $award['academic_year_id'],
                $starts, $starts, $ends, $ends,
            ]);
        }
    }

    /** Store a current-term fee waiver separately from school sponsorship. */
    private function saveExistingSchoolFeeWaiver(int $studentId, array $data): void
    {
        $type = trim((string) ($data['discount_type'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $allowed = ['percentage', 'fixed_amount', 'full_waiver', 'merit', 'need_based', 'sibling', 'other'];
        if (!in_array($type, $allowed, true) || $reason === '') {
            throw new \InvalidArgumentException('A valid fee-waiver type and approval reason are required');
        }

        $year = $this->db->query(
            "SELECT id, year_code FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$year) throw new \InvalidArgumentException('No current academic year is configured');

        $termStmt = $this->db->prepare(
            "SELECT id, term_id FROM academic_year_terms
             WHERE academic_year_id=? AND (status='current' OR CURDATE() BETWEEN opening_date AND closing_date)
             ORDER BY CASE WHEN status='current' THEN 0 ELSE 1 END, id LIMIT 1"
        );
        $termStmt->execute([(int) $year['id']]);
        $term = $termStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $academicYearTermId = (int) ($term['id'] ?? 0);
        $termId = (int) ($term['term_id'] ?? 0);
        if ($academicYearTermId <= 0 || $termId <= 0) throw new \InvalidArgumentException('No current term is configured');

        $value = max(0, (float) ($data['discount_value'] ?? 0));
        $percentage = $type === 'percentage' ? $value : null;
        if ($type === 'percentage' && $value > 100) throw new \InvalidArgumentException('Fee-waiver percentage must be between 0 and 100');
        if ($type !== 'full_waiver' && $value <= 0) throw new \InvalidArgumentException('A fee-waiver value is required');

        $obligationStmt = $this->db->prepare(
            "SELECT sfo.id, sfo.amount_due, COALESCE(sfo.sponsored_waiver_amount,0) AS sponsored_waiver_amount
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
             WHERE sae.student_id=? AND sfo.academic_year_id=? AND sfo.academic_year_term_id=?
               AND sfo.status <> 'paid' ORDER BY sfo.id"
        );
        $obligationStmt->execute([$studentId, (int) $year['id'], $academicYearTermId]);
        $obligations = $obligationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$obligations) return;

        $yearValue = (int) preg_replace('/[^0-9].*$/', '', (string) $year['year_code']);
        $remaining = $type === 'full_waiver' ? PHP_FLOAT_MAX : $value;
        $insert = $this->db->prepare(
            "INSERT INTO fee_discounts_waivers
                (student_id, student_fee_obligation_id, discount_type, discount_value,
                 discount_percentage, reason, academic_year, term_id, approved_by, approved_date, status)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),'active')"
        );
        foreach ($obligations as $obligation) {
            $net = max(0, (float) $obligation['amount_due'] - (float) $obligation['sponsored_waiver_amount']);
            if ($net <= 0) continue;
            $amount = $type === 'full_waiver'
                ? $net
                : ($type === 'percentage' ? round($net * $value / 100, 2) : min($net, $remaining));
            if ($amount <= 0) continue;
            $insert->execute([
                $studentId, (int) $obligation['id'], $type, $amount, $percentage,
                $reason, $yearValue, $termId, (int) ($this->getCurrentUserId() ?? 0),
            ]);
            if ($type !== 'full_waiver') {
                $remaining -= $amount;
                if ($remaining <= 0) break;
            }
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
                        'error' => 'An internal error occurred.'
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
                'warnings' => [],
                'duplicates' => []
            ];
            $seenAdmissions = [];

            foreach ($fileResult['data'] as $index => $row) {
                $rowNum = $index + 2; // +2 for header row and 0-based index

                try {
                    if (!array_filter($row, static fn($value) => trim((string) $value) !== '')) {
                        continue;
                    }
                    // The migration sheet is intentionally limited to learner
                    // creation, placement, identifiers, fee-paid figures and
                    // parent contact. Optional service arrangements are added
                    // later by the responsible school staff.
                    $row = $this->normalizeExistingStudentImportRow($row);
                    $requiredFields = [
                        'first_name', 'last_name', 'date_of_birth', 'gender',
                        'class_name', 'student_type', 'status', 'parent_relationship',
                        'parent_first_name', 'parent_last_name', 'parent_phone'
                    ];
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

                    $row['stream_name'] = trim((string) ($row['stream_name'] ?? '')) ?: 'A';
                    $row['class_id'] = $this->resolveExistingImportClassId((string) $row['class_name']);
                    if (!$row['class_id']) {
                        throw new \InvalidArgumentException("Class '{$row['class_name']}' is not configured for the current academic year");
                    }
                    $row['student_type_id'] = $this->resolveExistingImportStudentTypeId((string) $row['student_type']);
                    if (!$row['student_type_id']) {
                        throw new \InvalidArgumentException("Student type '{$row['student_type']}' is not configured");
                    }

                    // Check for duplicate admission number
                    $admissionNumbers = new AdmissionNumberService($this->db);
                    $submittedAdmissionNo = trim((string) ($row['admission_no'] ?? ''));
                    $candidate = $submittedAdmissionNo !== ''
                        ? $admissionNumbers->normalizeExisting($submittedAdmissionNo)
                        : null;
                    if ($candidate !== null && (isset($seenAdmissions[$candidate]) || !$admissionNumbers->isAvailable($candidate))) {
                        $results['skipped']++;
                        $results['duplicates'][] = [
                            'row' => $rowNum,
                            'admission_no' => $candidate,
                            'message' => isset($seenAdmissions[$candidate])
                                ? 'Admission number is repeated in this file'
                                : 'Admission number already exists in the student register'
                        ];
                        continue;
                    }
                    if ($candidate !== null) {
                        $row['admission_no'] = $candidate;
                    } else {
                        $row['admission_no'] = $admissionNumbers->generateNextExisting();
                        if ($submittedAdmissionNo !== '') {
                            $results['warnings'][] = [
                                'row' => $rowNum,
                                'message' => "Submitted admission number was unavailable or invalid; assigned {$row['admission_no']} using the next KPS number"
                            ];
                        }
                    }

                    // Set default admission date if not provided
                    // Prepare student data
                    $studentData = [
                        'admission_no' => $row['admission_no'] ?? null,
                        'first_name' => $row['first_name'],
                        'middle_name' => $row['middle_name'] ?? null,
                        'last_name' => $row['last_name'],
                        'date_of_birth' => $row['date_of_birth'],
                        'gender' => strtolower($row['gender']),
                        'class_id' => $row['class_id'],
                        'stream_name' => $row['stream_name'] ?? null,
                        'student_type_id' => !empty($row['student_type_id']) ? (int) $row['student_type_id'] : 1,
                        'status' => $row['status'] ?? 'active',
                        'assessment_number' => $row['assessment_number'] ?? null,
                        'nemis_number' => $row['nemis_number'] ?? null,
                        'assessment_status' => !empty($row['assessment_number']) ? 'assigned' : 'not_assigned',
                        'nemis_status' => !empty($row['nemis_number']) ? 'assigned' : 'not_assigned',
                    ];

                    if (empty($studentData['stream_name'])) {
                        throw new Exception('stream_name is required for existing-student migration');
                    }

                    $annualPaidRaw = $this->normalizeImportAmount($row['academic_year_paid_amount'] ?? '');
                    $termPaidRaw = $this->normalizeImportAmount($row['current_term_paid_amount'] ?? '');
                    $hasFinancialSnapshot = $annualPaidRaw !== '' || $termPaidRaw !== '';
                    if ($hasFinancialSnapshot) {
                        foreach (['academic_year_paid_amount'=>$annualPaidRaw, 'current_term_paid_amount'=>$termPaidRaw] as $field=>$value) {
                            if ($value !== '' && (!is_numeric($value) || (float) $value < 0)) {
                                throw new \InvalidArgumentException("{$field} must be a non-negative amount");
                            }
                        }
                        $annualPaid = $annualPaidRaw === '' ? 0.0 : round((float)$annualPaidRaw, 2);
                        $termPaid = $termPaidRaw === '' ? 0.0 : round((float)$termPaidRaw, 2);
                        if ($termPaid > $annualPaid) {
                            throw new \InvalidArgumentException('current_term_paid_amount cannot exceed academic_year_paid_amount');
                        }
                        $studentData['financial_migration'] = [
                            'academic_year_paid_amount' => $annualPaid,
                            'current_term_paid_amount' => $termPaid,
                            'fee_arrears_amount' => 0,
                            'advance_amount' => 0,
                            'calculate_from_schedule' => true,
                            'notes' => 'Financial position supplied during existing-student import'
                        ];
                        $studentData['migration_mode'] = true;
                    }

                    $studentData['parent'] = [
                        'first_name' => trim((string)$row['parent_first_name']),
                        'last_name' => trim((string)$row['parent_last_name']),
                        'gender' => $row['parent_gender'] ?? null,
                        'phone_1' => trim((string)$row['parent_phone']),
                        'phone_2' => $row['parent_secondary_phone'] ?? null,
                        'email' => $row['parent_email'] ?? null,
                        'relationship' => trim((string)$row['parent_relationship']),
                    ];

                    // Add the student
                    $response = $this->addExistingStudent($studentData);

                    if ($response['status'] === 'success') {
                        $results['successful']++;
                        $seenAdmissions[$row['admission_no']] = true;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $rowNum,
                            'error' => $response['message']
                        ];
                    }

                } catch (\InvalidArgumentException $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'error' => $e->getMessage()
                    ];
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'row' => $rowNum,
                        'error' => 'An internal error occurred.'
                    ];
                }
            }

            $this->logAction('create', null, "Imported {$results['successful']} existing students from file");

            return $this->response([
                'status' => $results['failed'] > 0 ? 'partial' : 'success',
                'message' => "Import completed: {$results['successful']} successful, {$results['failed']} failed, {$results['skipped']} skipped",
                'processed' => $results['successful'],
                'errors' => $results['errors'],
                'warnings' => $results['warnings'],
                'duplicates' => $results['duplicates'],
                'data' => $results
            ]);

        } catch (Exception $e) {
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
            'Admission Number','First Name *','Middle Name','Last Name *','Date of Birth *','Gender *',
            'Class *','Stream','Student Type *','Status *','KNEC Assessment No.','NEMIS Number',
            'Paid This Academic Year (KES)','Paid This Term (KES)','Parent Relationship *',
            'Parent First Name *','Parent Last Name *','Parent Gender','Primary Phone *',
            'Secondary Phone','Parent Email'
        ];

        $sampleData = [
            [
                'KWA/2026/001','John','','Kamau','2015-05-15','male','Grade 5','A',
                'Day Student','active','','','15000','3500','mother','Jane','Kamau','female','0712345678','',''
            ]
        ];

        return $this->response([
            'status' => 'success',
            'data' => [
                'headers' => $headers,
                'sample' => $sampleData,
                'instructions' => [
                    'Required: first name, last name, date of birth, gender, class, student type, status, parent relationship, parent names and primary parent phone',
                    'Date format: YYYY-MM-DD',
                    'Use the class and student type names shown in the dropdowns; do not enter database IDs',
                    'Stream is optional. A blank stream is placed in stream A',
                    'KNEC assessment number, NEMIS number and the two paid amounts are optional',
                    'Paid amounts are confirmed historical figures used to update the learner fee obligations; they do not create a duplicate payment',
                    'Transport, sponsorship, waivers, photo, blood group and other optional profile data are added later from the relevant staff screens',
                    'Admission Number is optional: enter digits such as 400 and the system stores KPS400; blank continues from the latest KPS number',
                    'This is for existing learners only; newly admitted learners must come through admissions'
                ]
            ]
        ]);
    }

    // ========================================================================
    // HELPER METHODS FOR EXISTING STUDENT IMPORT
    // ========================================================================

    /**
     * Convert the human-facing import headers into the internal import contract.
     * The workbook intentionally exposes labels, while CSV users may use either
     * those labels or the older snake_case names.
     */
    private function normalizeExistingStudentImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $key = strtolower(trim((string) $key));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key);
            $key = trim((string) $key, '_');
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }

        $aliases = [
            'admission_number' => 'admission_no',
            'first_name_' => 'first_name', 'last_name_' => 'last_name',
            'date_of_birth_' => 'date_of_birth', 'gender_' => 'gender',
            'class' => 'class_name', 'class_name_' => 'class_name',
            'stream' => 'stream_name', 'stream_name_' => 'stream_name',
            'student_type_name' => 'student_type', 'student_type_' => 'student_type',
            'status_' => 'status', 'knec_assessment_no' => 'assessment_number',
            'assessment_number_' => 'assessment_number', 'nemis_number_' => 'nemis_number',
            'paid_this_academic_year_kes' => 'academic_year_paid_amount',
            'academic_year_paid_amount_kes' => 'academic_year_paid_amount',
            'paid_this_term_kes' => 'current_term_paid_amount',
            'current_term_paid_amount_kes' => 'current_term_paid_amount',
            'parent_relationship_' => 'parent_relationship',
            'parent_first_name_' => 'parent_first_name', 'parent_last_name_' => 'parent_last_name',
            'primary_phone' => 'parent_phone', 'parent_phone_primary' => 'parent_phone',
            'secondary_phone' => 'parent_secondary_phone',
        ];
        foreach ($aliases as $from => $to) {
            if (array_key_exists($from, $normalized) && !array_key_exists($to, $normalized)) {
                $normalized[$to] = $normalized[$from];
            }
        }

        foreach (['date_of_birth'] as $field) {
            if (isset($normalized[$field]) && $normalized[$field] !== '') {
                $normalized[$field] = $this->normalizeImportDate($normalized[$field]);
            }
        }
        foreach (['gender', 'status', 'parent_relationship', 'parent_gender'] as $field) {
            if (isset($normalized[$field]) && is_string($normalized[$field])) {
                $normalized[$field] = strtolower(trim($normalized[$field]));
            }
        }
        return $normalized;
    }

    private function normalizeImportDate($value): string
    {
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException('Date of birth is not a valid date');
            }
        }
        $value = trim((string) $value);
        foreach (['!Y-m-d', '!d/m/Y', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }
        throw new \InvalidArgumentException('Date of birth must use YYYY-MM-DD or DD/MM/YYYY format');
    }

    private function normalizeImportAmount($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        $value = preg_replace('/^(?:KES|KSH)\\s*/i', '', $value);
        $value = preg_replace('/[\\s,\\x{00A0}]+/u', '', $value);
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException('Fee amounts must be numeric values, optionally formatted as KES/Ksh with commas');
        }
        return $value;
    }

    private function resolveExistingImportClassId(string $className): int
    {
        $year = $this->db->query(
            "SELECT id FROM academic_years
             WHERE is_current = 1 OR status = 'active'
             ORDER BY is_current DESC, start_date DESC, id DESC LIMIT 1"
        )->fetchColumn();
        if (!$year) return 0;

        $stmt = $this->db->prepare(
            "SELECT c.id
             FROM classes c
             JOIN academic_year_classes ayc ON ayc.class_id = c.id
             WHERE ayc.academic_year_id = ? AND LOWER(TRIM(c.name)) = LOWER(TRIM(?))
             LIMIT 1"
        );
        $stmt->execute([(int) $year, $className]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function resolveExistingImportStudentTypeId(string $studentType): int
    {
        $stmt = $this->db->query("SELECT id, code, name FROM student_types WHERE status = 'active' ORDER BY id");
        $needle = strtolower(trim($studentType));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $type) {
            $values = [strtolower(trim((string) $type['name'])), strtolower(trim((string) $type['code']))];
            if (in_array($needle, $values, true)) return (int) $type['id'];
            if (($needle === 'day' || $needle === 'day student') && in_array('day', $values, true)) return (int) $type['id'];
            if (($needle === 'board' || $needle === 'full boarder') && in_array('board', $values, true)) return (int) $type['id'];
            if (($needle === 'weekly' || $needle === 'weekly boarder') && in_array('weekly', $values, true)) return (int) $type['id'];
        }
        return 0;
    }

    /**
     * Rebuild the import figures from the active fee schedules on the server.
     * The browser may display the preview, but it is never the authority for
     * the amount due or for the migration snapshot.
     */
    private function deriveExistingImportFinancialPosition(array $data, int $studentId): array
    {
        $year = $this->db->query(
            "SELECT id, year_code FROM academic_years
             WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            throw new \InvalidArgumentException('No current academic year is configured');
        }

        $termStmt = $this->db->prepare(
            "SELECT ayt.id
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
               AND (ayt.status = 'current' OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date)
             ORDER BY CASE WHEN ayt.status = 'current' THEN 0 ELSE 1 END, t.id
             LIMIT 1"
        );
        $termStmt->execute([(int) $year['id']]);
        $currentTermId = (int) ($termStmt->fetchColumn() ?: 0);

        $scheduleStmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(afs.amount), 0) AS annual_due,
                COALESCE(SUM(CASE WHEN afs.academic_year_term_id = ? THEN afs.amount ELSE 0 END), 0) AS current_term_due
             FROM academic_year_fee_schedules afs
             JOIN academic_year_classes ayc ON ayc.id = afs.academic_year_class_id
             WHERE afs.academic_year_id = ?
               AND ayc.class_id = ?
               AND (afs.student_type_id = ? OR afs.student_type_id IS NULL)
               AND afs.status = 'active'"
        );
        $scheduleStmt->execute([
            $currentTermId,
            (int) $year['id'],
            (int) ($data['class_id'] ?? 0),
            (int) ($data['student_type_id'] ?? 0),
        ]);
        $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC) ?: ['annual_due' => 0, 'current_term_due' => 0];
        $annualDue = round((float) $schedule['annual_due'], 2);
        $currentTermDue = round((float) $schedule['current_term_due'], 2);
        $awardStmt = $this->db->prepare(
            "SELECT coverage_type, coverage_percentage, coverage_amount, starts_on, ends_on
             FROM student_scholarship_awards
             WHERE student_id = ? AND academic_year_id = ? AND status = 'active'"
        );
        $awardStmt->execute([$studentId, (int) $year['id']]);
        $annualRelief = 0.0;
        foreach ($awardStmt->fetchAll(PDO::FETCH_ASSOC) as $award) {
            $parts = $this->db->prepare(
                "SELECT afs.amount, ayt.opening_date, ayt.closing_date
                 FROM academic_year_fee_schedules afs
                 JOIN academic_year_classes ayc ON ayc.id = afs.academic_year_class_id
                 JOIN academic_year_terms ayt ON ayt.id = afs.academic_year_term_id
                 WHERE afs.academic_year_id = ? AND ayc.class_id = ?
                   AND (afs.student_type_id = ? OR afs.student_type_id IS NULL)
                   AND afs.status = 'active'
                   AND (? IS NULL OR ayt.closing_date >= ?)
                   AND (? IS NULL OR ayt.opening_date <= ?)"
            );
            $starts = $award['starts_on'] ?: null;
            $ends = $award['ends_on'] ?: null;
            $parts->execute([
                (int) $year['id'], (int) ($data['class_id'] ?? 0),
                (int) ($data['student_type_id'] ?? 0), $starts, $starts, $ends, $ends,
            ]);
            foreach ($parts->fetchAll(PDO::FETCH_ASSOC) as $part) {
                $amount = (float) $part['amount'];
                $coverage = match ($award['coverage_type']) {
                    'full' => $amount,
                    'percentage' => $amount * (float) ($award['coverage_percentage'] ?? 0) / 100,
                    default => (float) ($award['coverage_amount'] ?? 0),
                };
                $annualRelief += min($amount, max(0, $coverage));
            }
        }
        $annualNetDue = max(0, round($annualDue - $annualRelief, 2));
        $source = $data['financial_migration'] ?? [];
        if (array_key_exists('legacy_financial_amount', $data)) {
            $supplied = max(0, round((float) $data['legacy_financial_amount'], 2));
            $basis = ($data['legacy_financial_basis'] ?? 'paid') === 'balance' ? 'balance' : 'paid';
            $paid = $basis === 'balance' ? max(0, $annualNetDue - $supplied) : $supplied;
            $currentPaid = min($paid, $currentTermDue);
            $arrears = $basis === 'balance' ? $supplied : max(0, $annualNetDue - $paid);
            $advance = $basis === 'paid' ? max(0, $paid - $annualNetDue) : 0;
        } else {
            $paid = max(0, round((float) ($source['academic_year_paid_amount'] ?? 0), 2));
            $currentPaid = max(0, round((float) ($source['current_term_paid_amount'] ?? 0), 2));
            // Unpaid current-year fees remain on their normal obligations;
            // arrears_amount is reserved for a separately confirmed opening
            // debt and must not duplicate the ordinary outstanding balance.
            $arrears = 0.0;
            $advance = max(0, $paid - $annualNetDue);
        }
        return [
            'academic_year_code' => (string) $year['year_code'],
            'academic_year_paid_amount' => round($paid, 2),
            'current_term_paid_amount' => round($currentPaid, 2),
            'fee_arrears_amount' => round($arrears, 2),
            'advance_amount' => round($advance, 2),
            'reference' => $source['reference'] ?? null,
            'payment_date' => $source['payment_date'] ?? null,
            'payment_method' => $source['payment_method'] ?? 'other',
            'notes' => $source['notes'] ?? null,
        ];
    }

    /**
     * Store the financial position supplied during migration. The snapshot is
     * deliberately separate from payments: historical annual totals must not
     * be replayed as new payments, while the current-term amount, arrears and
     * advance remain available to the fee ledger.
     */
    private function saveFinancialMigrationSnapshot($studentId, array $financial)
    {
        if ($studentId <= 0) {
            throw new \InvalidArgumentException('Imported student could not be resolved for financial migration');
        }

        $yearCode = trim((string) ($financial['academic_year_code'] ?? ''));
        if ($yearCode === '') {
            $yearCode = (string) ($this->db->query(
                "SELECT year_code FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
            )->fetchColumn() ?: '');
        }
        if (!preg_match('/^(\d{4})\/(\d{4})$/', $yearCode, $matches)) {
            throw new \InvalidArgumentException('financial_academic_year_code must use YYYY/YYYY format');
        }

        $yearStmt = $this->db->prepare(
            "SELECT id FROM academic_years WHERE year_code = ? LIMIT 1"
        );
        $yearStmt->execute([$yearCode]);
        $academicYearId = (int) $yearStmt->fetchColumn();
        if ($academicYearId <= 0) {
            throw new \InvalidArgumentException("Academic year {$yearCode} was not found");
        }

        $termStmt = $this->db->prepare(
            "SELECT ayt.id
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
               AND (ayt.status = 'current' OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date)
             ORDER BY ayt.status = 'current' DESC, CAST(SUBSTRING(t.code, 2) AS UNSIGNED)
             LIMIT 1"
        );
        $termStmt->execute([$academicYearId]);
        $academicYearTermId = (int) ($termStmt->fetchColumn() ?: 0);

        $academicYearPaid = round((float) ($financial['academic_year_paid_amount'] ?? 0), 2);
        $currentTermPaid = round((float) ($financial['current_term_paid_amount'] ?? 0), 2);
        $arrears = round((float) ($financial['fee_arrears_amount'] ?? 0), 2);
        $advance = round((float) ($financial['advance_amount'] ?? 0), 2);
        if ($currentTermPaid > $academicYearPaid) {
            throw new \InvalidArgumentException('Current-term paid amount cannot exceed academic-year paid amount');
        }

        $sql = "INSERT INTO student_fee_migration_snapshots
            (student_id, academic_year_id, academic_year_term_id,
             academic_year_paid_amount, current_term_paid_amount,
             arrears_amount, advance_amount, reference, payment_date,
             payment_method, receipt_no, notes, imported_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                academic_year_term_id = VALUES(academic_year_term_id),
                academic_year_paid_amount = VALUES(academic_year_paid_amount),
                current_term_paid_amount = VALUES(current_term_paid_amount),
                arrears_amount = VALUES(arrears_amount),
                advance_amount = VALUES(advance_amount),
                reference = VALUES(reference), payment_date = VALUES(payment_date),
                payment_method = VALUES(payment_method), receipt_no = VALUES(receipt_no),
                notes = VALUES(notes), imported_by = VALUES(imported_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $studentId,
            $academicYearId,
            $academicYearTermId > 0 ? $academicYearTermId : null,
            $academicYearPaid,
            $currentTermPaid,
            $arrears,
            $advance,
            $financial['reference'] ?? null,
            !empty($financial['payment_date']) ? $financial['payment_date'] : null,
            !empty($financial['payment_method']) ? $this->normalizePaymentMethod($financial['payment_method']) : null,
            $financial['receipt_no'] ?? null,
            $financial['notes'] ?? null,
            $this->getCurrentUserId()
        ]);
    }

    /**
     * Convert verified historical fee figures into normal obligation credit.
     * The migration snapshot remains the audit source, while balances and
     * statuses are driven by student_fee_obligations like every later payment.
     */
    private function applyFinancialMigrationToObligations(int $studentId, array $financial): void
    {
        $yearStmt = $this->db->query("SELECT id FROM academic_years WHERE is_current=1 ORDER BY id DESC LIMIT 1");
        $academicYearId = (int) ($yearStmt->fetchColumn() ?: 0);
        if ($academicYearId <= 0) return;

        // A migration may happen in Term 2 or Term 3. Rebuild missing prior
        // term obligations so the annual paid figure can be applied to the
        // complete academic-year ledger.
        $this->generateStudentFeeObligationsForCurrentYear($studentId, $academicYearId);
        // Apply awards before allocating the migrated credit. This also
        // repairs records where the snapshot was created after enrollment.
        $this->applyExistingStudentSponsorshipToObligations($studentId);

        $enrollmentStmt = $this->db->prepare(
            "SELECT sae.id
             FROM student_academic_enrollments sae
             WHERE sae.student_id=? AND sae.academic_year_id=?
             ORDER BY sae.id DESC LIMIT 1"
        );
        $enrollmentStmt->execute([$studentId, $academicYearId]);
        $enrollmentId = (int) ($enrollmentStmt->fetchColumn() ?: 0);
        if ($enrollmentId <= 0) return;

        $obligationStmt = $this->db->prepare(
            "SELECT sfo.id, sfo.amount_due, COALESCE(sfo.sponsored_waiver_amount,0) AS sponsored_waiver_amount,
                    sfo.academic_year_term_id, ayt.opening_date, t.code AS term_code,
                    COALESCE((SELECT SUM(fdw.discount_value)
                              FROM fee_discounts_waivers fdw
                              WHERE fdw.student_fee_obligation_id=sfo.id AND fdw.status='active'),0) AS fee_waiver
             FROM student_fee_obligations sfo
             JOIN academic_year_terms ayt ON ayt.id=sfo.academic_year_term_id
             JOIN terms t ON t.id=ayt.term_id
             WHERE sfo.student_academic_enrollment_id=? AND sfo.academic_year_id=?
             ORDER BY ayt.opening_date, sfo.id"
        );
        $obligationStmt->execute([$enrollmentId, $academicYearId]);
        $obligations = $obligationStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$obligations) return;

        $this->db->prepare(
            "UPDATE student_fee_obligations
             SET migration_paid_amount=0, opening_arrears_amount=0, updated_at=NOW()
             WHERE student_academic_enrollment_id=? AND academic_year_id=?"
        )->execute([$enrollmentId, $academicYearId]);

        $openingArrears = max(0, round((float) ($financial['fee_arrears_amount'] ?? 0), 2));
        if ($openingArrears > 0) {
            $firstObligation = $this->db->prepare(
                "SELECT sfo.id FROM student_fee_obligations sfo
                 JOIN academic_year_terms ayt ON ayt.id=sfo.academic_year_term_id
                 WHERE sfo.student_academic_enrollment_id=? AND sfo.academic_year_id=?
                   AND (ayt.status='current' OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date)
                 ORDER BY ayt.opening_date, sfo.id LIMIT 1"
            );
            $firstObligation->execute([$enrollmentId, $academicYearId]);
            $firstId = (int) ($firstObligation->fetchColumn() ?: 0);
            if ($firstId > 0) {
                $this->db->prepare(
                    "UPDATE student_fee_obligations SET opening_arrears_amount=?, updated_at=NOW() WHERE id=?"
                )->execute([$openingArrears, $firstId]);
            }
        }

        $currentTermStmt = $this->db->prepare(
            "SELECT ayt.id
             FROM academic_year_terms ayt
             WHERE ayt.academic_year_id=?
               AND (ayt.status='current' OR CURDATE() BETWEEN ayt.opening_date AND ayt.closing_date)
             ORDER BY ayt.status='current' DESC, ayt.opening_date
             LIMIT 1"
        );
        $currentTermStmt->execute([$academicYearId]);
        $currentTermId = (int) ($currentTermStmt->fetchColumn() ?: 0);
        $annualPaid = max(0, round((float) ($financial['academic_year_paid_amount'] ?? 0), 2));
        $currentTermPaid = max(0, round((float) ($financial['current_term_paid_amount'] ?? 0), 2));
        $preCurrentPaid = max(0, round($annualPaid - $currentTermPaid, 2));
        $credits = [];

        // Historical annual payments settle earlier terms first. The amount
        // recorded for the current term then settles any carried balance
        // before it is applied to the current term itself.
        foreach ($obligations as $index => $obligation) {
            if ((int) $obligation['academic_year_term_id'] === $currentTermId) break;
            $netDue = max(0, (float) $obligation['amount_due']
                - (float) $obligation['sponsored_waiver_amount']
                - (float) $obligation['fee_waiver']);
            $credit = min($preCurrentPaid, $netDue);
            $credits[$index] = $credit;
            $preCurrentPaid = round($preCurrentPaid - $credit, 2);
        }

        $currentContextPaid = $currentTermPaid;
        foreach ($obligations as $index => $obligation) {
            if ((int) $obligation['academic_year_term_id'] > $currentTermId) continue;
            $netDue = max(0, (float) $obligation['amount_due']
                - (float) $obligation['sponsored_waiver_amount']
                - (float) $obligation['fee_waiver']);
            $already = (float) ($credits[$index] ?? 0);
            $credit = min($currentContextPaid, max(0, $netDue - $already));
            $credits[$index] = round($already + $credit, 2);
            $currentContextPaid = round($currentContextPaid - $credit, 2);
            if ($currentContextPaid <= 0) break;
        }

        if ($currentTermId <= 0) {
            $credits = [];
            $remainingPaid = $annualPaid;
            foreach ($obligations as $index => $obligation) {
                $netDue = max(0, (float) $obligation['amount_due']
                    - (float) $obligation['sponsored_waiver_amount']
                    - (float) $obligation['fee_waiver']);
                $credits[$index] = min($remainingPaid, $netDue);
                $remainingPaid = round($remainingPaid - $credits[$index], 2);
                if ($remainingPaid <= 0) break;
            }
        }

        $update = $this->db->prepare(
            "UPDATE student_fee_obligations
             SET migration_paid_amount=?,
                 status=CASE
                    WHEN amount_due + COALESCE(opening_arrears_amount,0)
                         - COALESCE(sponsored_waiver_amount,0) - COALESCE(?,0) - COALESCE(migration_paid_amount,0) <= 0 THEN 'paid'
                    WHEN COALESCE(?,0) + COALESCE(migration_paid_amount,0) > 0 THEN 'partial'
                    ELSE 'pending' END,
                 updated_at=NOW()
             WHERE id=?"
        );

        foreach ($obligations as $index => $obligation) {
            $credit = (float) ($credits[$index] ?? 0);
            $update->execute([$credit, $obligation['fee_waiver'], $credit, (int) $obligation['id']]);
        }
    }

    /**
     * Get or create academic_year_class_streams.id for a class
     * New schema: streams (master) bound to academic_year_class_streams (year-scoped)
     */
    private function getOrCreateStreamId($classId, $streamName = null)
    {
        $streamName = trim((string) $streamName);
        if ($streamName === '') {
            throw new Exception('A configured stream is required');
        }

        // Get the current academic year
        $ayStmt = $this->db->query(
            "SELECT id FROM academic_years
             WHERE is_current = 1 OR status = 'active'
             ORDER BY is_current DESC, start_date DESC, id DESC
             LIMIT 1"
        );
        $currentYearId = $ayStmt ? (int) $ayStmt->fetchColumn() : 0;
        if ($currentYearId === 0) {
            throw new Exception("No current academic year is configured");
        }

        // Resolve the academic_year_classes binding for (academic_year, class)
        $aycStmt = $this->db->prepare(
            "SELECT id FROM academic_year_classes
             WHERE academic_year_id = ? AND class_id = ?
             LIMIT 1"
        );
        $aycStmt->execute([$currentYearId, $classId]);
        $academicYearClassId = (int) $aycStmt->fetchColumn();

        if ($academicYearClassId === 0) {
            throw new Exception("Class {$classId} is not bound to the current academic year");
        }

        // Look up stream master by name (case-insensitive)
        $smStmt = $this->db->prepare("SELECT id FROM streams WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $smStmt->execute([$streamName]);
        $streamId = (int) $smStmt->fetchColumn();

        if ($streamId === 0) {
            throw new Exception("Configured stream '{$streamName}' was not found");
        }

        // Check existing academic_year_class_streams binding
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_year_class_streams
             WHERE academic_year_class_id = ? AND stream_id = ?
             LIMIT 1"
        );
        $stmt->execute([$academicYearClassId, $streamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return (int) $row['id'];
        }

        throw new Exception("Stream '{$streamName}' is not configured for class {$classId} in the current academic year");
    }

    /**
     * Add parent/guardian for student
     */
    private function addStudentParent($studentId, $parentData)
    {
        // Check if parent already exists by phone or email (live: persons holds contact info)
        $parentId = null;
        if (!empty($parentData['parent_id'])) {
            $parentStmt = $this->db->prepare("SELECT id FROM parents WHERE id = ? LIMIT 1");
            $parentStmt->execute([(int) $parentData['parent_id']]);
            $parentId = (int) ($parentStmt->fetchColumn() ?: 0);
            if (!$parentId) {
                throw new Exception('Selected parent record was not found');
            }
        }
        $phone = trim((string) ($parentData['phone_1'] ?? $parentData['phone'] ?? ''));
        $email = trim((string) ($parentData['email'] ?? ''));

        if ($phone !== '') {
            $stmt = $this->db->prepare("
                SELECT p.id
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                WHERE pp.phone = ?
                LIMIT 1
            ");
            $stmt->execute([$phone]);
            $parentId = (int) ($stmt->fetchColumn() ?: 0);
        }

        if (!$parentId && $email !== '') {
            $stmt = $this->db->prepare("
                SELECT p.id
                FROM parents p
                JOIN persons pp ON pp.id = p.person_id
                WHERE pp.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $parentId = (int) ($stmt->fetchColumn() ?: 0);
        }

        // Create new parent if not found
        if (!$parentId) {
            $firstName = trim((string) ($parentData['first_name'] ?? ''));
            $lastName = trim((string) ($parentData['last_name'] ?? ''));
            if ($firstName === '' || $lastName === '') {
                throw new Exception('Parent first_name and last_name are required when creating a new parent record');
            }
            $parentId = $this->createParentRecord($parentData);
        }

        $this->linkStudentParent($studentId, $parentId, $parentData);

        return $parentId;
    }

    // ========================================================================
    // STUDENT ID CARD & PHOTO MANAGEMENT
    // ========================================================================

    /**
     * Upload student photo
     */
    public function uploadPhoto($studentId, $fileData)
    {
        $workflow = new \App\API\Services\StudentPhotoWorkflowService($this->db);
        $result = $workflow->submit(
            (int) $studentId,
            $fileData,
            (int) ($this->user_id ?? 0),
            'staff_upload',
            true
        );
        $media = new \App\API\Modules\system\MediaManager($this->db);
        $result['photo_url'] = $media->getFileUrl((int) $result['media_id']);
        return $this->response(['status' => 'success', 'data' => $result, 'message' => 'Student photo approved and updated.']);
    }

    public function getPhotoHistory(int $studentId)
    {
        $rows = (new \App\API\Services\StudentPhotoWorkflowService($this->db))->list($studentId);
        $media = new \App\API\Modules\system\MediaManager($this->db);
        foreach ($rows as &$row) {
            $row['file_url'] = !empty($row['media_id']) ? $media->getFileUrl((int) $row['media_id']) : null;
        }
        unset($row);
        return $this->response(['status' => 'success', 'data' => ['photos' => $rows]]);
    }

    public function approvePhoto(int $versionId, string $reason = '')
    {
        $result = (new \App\API\Services\StudentPhotoWorkflowService($this->db))->approve(
            $versionId,
            (int) ($this->user_id ?? 0),
            $reason
        );
        return $this->response(['status' => 'success', 'data' => $result, 'message' => 'Student photo approved.']);
    }

    public function getPendingPhotos()
    {
        $rows = (new \App\API\Services\StudentPhotoWorkflowService($this->db))->listPending();
        $media = new \App\API\Modules\system\MediaManager($this->db);
        foreach ($rows as &$row) {
            $row['file_url'] = !empty($row['media_id']) ? $media->getFileUrl((int) $row['media_id']) : null;
        }
        unset($row);
        return $this->response(['status' => 'success', 'data' => ['photos' => $rows]]);
    }

    public function rejectPhoto(int $versionId, string $reason)
    {
        $result = (new \App\API\Services\StudentPhotoWorkflowService($this->db))->reject(
            $versionId,
            (int) ($this->user_id ?? 0),
            $reason
        );
        return $this->response(['status' => 'success', 'data' => $result, 'message' => 'Student photo rejected.']);
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
     * Generate bulk ID cards PDF for selected students
     */
    public function generateBulkIDCardsPDF($studentIds, $printMode = 'a4_sheet', $includeFront = true, $includeBack = true)
    {
        return $this->idCardGenerator->generateBulkIDCardsPDF($studentIds, $printMode, $includeFront, $includeBack);
    }

    /**
     * Generate print-ready single card HTML for browser/system printing.
     */
    public function generatePrintableSingle($studentId, $side = 'both', $printMode = 'direct_card', $format = 'html')
    {
        return $this->idCardGenerator->generatePrintableSingle($studentId, $side, $printMode, $format);
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
                $payload['photo_url'] = $this->normalizePublicAssetPath(
                    defined('STUDENT_AVATAR_DEFAULT') ? STUDENT_AVATAR_DEFAULT : $this->publicUploadAssetUrl('students', 'avatar.jpg')
                );
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
                $conditions[] = 'c.id = ?';
                $bindings[] = (int) $params['class_id'];
            }

            if (!empty($params['stream_id'])) {
                $conditions[] = 'sae.academic_year_class_stream_id = ?';
                $bindings[] = (int) $params['stream_id'];
            }

            if (!empty($params['search'])) {
                $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ?)";
                $search = '%' . trim((string) $params['search']) . '%';
                $bindings[] = $search;
                $bindings[] = $search;
                $bindings[] = $search;
            }

            $where = 'WHERE ' . implode(' AND ', $conditions);

            $sql = "
                SELECT
                    COUNT(*) AS total_students,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(per.photo_url), ''), '') <> '' THEN 1 ELSE 0 END) AS students_with_photos,
                    SUM(CASE WHEN COALESCE(NULLIF(TRIM(student_qr.qr_code_path), ''), '') <> '' THEN 1 ELSE 0 END) AS students_with_qr_codes,
                    SUM(
                        CASE
                            WHEN COALESCE(NULLIF(TRIM(per.photo_url), ''), '') <> ''
                             AND COALESCE(NULLIF(TRIM(student_qr.qr_code_path), ''), '') <> ''
                            THEN 1
                            ELSE 0
                        END
                    ) AS id_cards_ready
                FROM students s
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN (
                    SELECT sic.student_id, MAX(sic.qr_code_path) AS qr_code_path
                    FROM student_id_cards sic
                    WHERE sic.qr_code_path IS NOT NULL
                    GROUP BY sic.student_id
                ) student_qr ON student_qr.student_id = s.id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
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
        if ($value === '' || $value === 'NULL') {
            // No photo on record: fall back to the canonical default avatar so the
            // frontend never references a missing path.
            $value = $this->publicUploadAssetUrl('students', 'avatar.jpg');
        }

        if (str_starts_with($value, 'data:')) {
            return $value;
        }

        $base = rtrim((string) (defined('UPLOAD_URL') ? UPLOAD_URL : ''), '/');
        if ($base === '') {
            return $value;
        }

        $clean = preg_match('#^https?://#i', $value)
            ? (string) (parse_url($value, PHP_URL_PATH) ?: '')
            : $value;
        $clean = '/' . ltrim($clean, '/');
        if (preg_match('#(?:^|/)uploads/(.+)$#i', $clean, $match)) {
            $clean = '/' . ltrim($match[1], '/');
        }

        return $base . '/' . ltrim($clean, '/');
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

        try {
            $result = $this->promotionManager->promoteSingleStudent(
                (int) $data['student_id'],
                (int) $data['to_class_id'],
                (int) $data['to_stream_id'],
                (int) $data['from_year_id'],
                (int) $data['to_year_id'],
                (int) ($this->getCurrentUserId() ?? $this->resolvePromotionBatchCreatorId()),
                $data['remarks'] ?? null
            );

            return [
                'success' => true,
                'message' => 'Student promoted successfully',
                'data' => ['promotion' => $result]
            ];
        } catch (Exception $e) {
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

        try {
            $result = $this->promotionManager->promoteMultipleStudents(
                $studentIds,
                (int) $data['to_class_id'],
                (int) $data['to_stream_id'],
                (int) $data['from_year_id'],
                (int) $data['to_year_id'],
                (int) ($this->getCurrentUserId() ?? $this->resolvePromotionBatchCreatorId()),
                $data['remarks'] ?? null
            );

            if ($result['promoted'] === 0) {
                return [
                    'success' => false,
                    'message' => 'No students were promoted',
                    'data' => ['results' => $result]
                ];
            }

            $message = $result['failed'] > 0
                ? "Promotion completed with {$result['failed']} errors"
                : 'Students promoted successfully';

            return [
                'success' => true,
                'message' => $message,
                'data' => ['results' => $result]
            ];
        } catch (Exception $e) {
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
            SELECT sae.student_id
            FROM student_academic_enrollments sae
            JOIN students s ON s.id = sae.student_id
            JOIN academic_year_class_streams aycs
                ON aycs.id = sae.academic_year_class_stream_id
            JOIN academic_year_classes ayc
                ON ayc.id = aycs.academic_year_class_id
            WHERE ayc.class_id = ?
              AND sae.academic_year_class_stream_id = ?
              AND sae.academic_year_id = ?
              AND sae.enrollment_status IN ('pending', 'active')
              AND s.status != 'transferred'
            ORDER BY sae.student_id ASC
        ");
        $stmt->execute([$fromClassId, $fromStreamId, $fromYearId]);
        $studentIds = array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'student_id'));

        if (empty($studentIds)) {
            // Fallback: derive students via the aycs (academic_year_class_streams) id.
            $fallback = $this->db->prepare("
                SELECT sae.student_id
                FROM student_academic_enrollments sae
                JOIN students s ON s.id = sae.student_id
                WHERE sae.academic_year_class_stream_id = ?
                  AND sae.enrollment_status IN ('pending', 'active')
                  AND s.status = 'active'
                ORDER BY sae.student_id ASC
            ");
            $fallback->execute([$fromStreamId]);
            $studentIds = array_map('intval', array_column($fallback->fetchAll(PDO::FETCH_ASSOC), 'student_id'));
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
            $studentsStmt = $this->db->prepare("
                SELECT sae.id AS enrollment_id,
                       sae.student_id,
                       sae.academic_year_id
                FROM student_academic_enrollments sae
                JOIN students s ON s.id = sae.student_id
                JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                WHERE ayc.class_id = ?
                  AND aycs.stream_id = ?
                  AND sae.academic_year_id = ?
                  AND sae.enrollment_status IN ('pending', 'active')
                  AND s.status != 'transferred'
                ORDER BY sae.student_id ASC
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

                // Close out the source enrollment as graduated.
                $updateEnrollment = $this->db->prepare("
                    UPDATE student_academic_enrollments
                    SET enrollment_status = 'graduated'
                    WHERE id = ?
                ");
                $updateEnrollment->execute([$enrollmentId]);

                $updateStudent = $this->db->prepare("
                    UPDATE students
                    SET status = 'graduated', updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStudent->execute([$studentId]);

                // Append the graduation transition to student_transitions
                // (append-only history). Idempotent for the same
                // (student_id, academic_year_id, transition_type) — replays
                // just update executed_at/reason.
                $transitionExists = $this->db->prepare("
                    SELECT id FROM student_transitions
                    WHERE student_id = ?
                      AND academic_year_id = ?
                      AND transition_type = 'graduation'
                      AND from_student_academic_enrollment_id = ?
                    LIMIT 1
                ");
                $transitionExists->execute([$studentId, $yearId, $enrollmentId]);
                $existingTransitionId = $transitionExists->fetchColumn();

                $reason = trim((string) ($graduationData['reason'] ?? 'Completed Grade 9'));
                $awards = $graduationData['awards'][$studentId] ?? null;
                $achievements = $graduationData['achievements'][$studentId] ?? null;
                $nextSchool = $graduationData['next_school'][$studentId] ?? null;
                $graduationDate = $graduationData['graduation_date'] ?? date('Y-m-d');
                // Pack legacy alumni extras into the reason column so no audit info is lost.
                $extraMetadata = trim(($awards ? "awards={$awards}; " : '')
                    . ($achievements ? "achievements={$achievements}; " : '')
                    . ($nextSchool ? "next_school={$nextSchool}; " : '')
                    . "graduation_date={$graduationDate}");
                $fullReason = $reason . ($extraMetadata !== '' ? ' [' . $extraMetadata . ']' : '');

                if ($existingTransitionId) {
                    $updateTransition = $this->db->prepare("
                        UPDATE student_transitions
                        SET reason = ?, decided_by = ?, decided_at = NOW(),
                            executed_at = NOW()
                        WHERE id = ?
                    ");
                    $updateTransition->execute([$fullReason, $performedBy, $existingTransitionId]);
                } else {
                    $insertTransition = $this->db->prepare("
                        INSERT INTO student_transitions (
                            student_id,
                            from_student_academic_enrollment_id,
                            to_student_academic_enrollment_id,
                            academic_year_id,
                            transition_type,
                            reason,
                            decided_by,
                            decided_at,
                            executed_at
                        ) VALUES (?, ?, NULL, ?, 'graduation', ?, ?, NOW(), NOW())
                    ");
                    $insertTransition->execute([
                        $studentId,
                        $enrollmentId,
                        $yearId,
                        $fullReason,
                        $performedBy
                    ]);
                }

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
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Get alumni (graduated students)
     */
    public function getAlumni($filters = [])
    {
        try {
            // New schema: graduation events live in student_transitions (transition_type='graduation').
            // Class/stream/year are derived via the to_enrollment → academic_year_class_streams chain.
            $sql = "
                SELECT
                    st.id              AS transition_id,
                    st.student_id,
                    st.transition_type,
                    st.reason,
                    st.decided_by,
                    st.decided_at,
                    st.executed_at,
                    st.academic_year_id,
                    s.admission_no,
                    per.first_name,
                    per.middle_name,
                    per.last_name,
                    per.gender,
                    c.name   AS class_name,
                    sm.name AS stream_name,
                    ay.year_code AS graduation_year
                FROM student_transitions st
                JOIN students s ON s.id = st.student_id
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN student_academic_enrollments sae
                    ON sae.id = st.to_student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN streams sm
                    ON sm.id = aycs.stream_id
                LEFT JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c
                    ON c.id = ayc.class_id
                LEFT JOIN academic_years ay
                    ON ay.id = st.academic_year_id
                WHERE st.transition_type = 'graduation'
            ";
            $params = [];

            if (!empty($filters['academic_year_id'])) {
                $sql .= " AND st.academic_year_id = ?";
                $params[] = (int) $filters['academic_year_id'];
            } elseif (!empty($filters['graduation_year'])) {
                // Match the academic_year whose year_code starts with the graduation year
                $sql .= " AND CAST(SUBSTRING(ay.year_code, 1, 4) AS UNSIGNED) = ?";
                $params[] = (int) $filters['graduation_year'];
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND ayc.class_id = ?";
                $params[] = (int) $filters['class_id'];
            }

            $sql .= " ORDER BY st.executed_at DESC, per.last_name ASC, per.first_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $rows,
                'message' => 'Alumni fetched successfully'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Update alumni record.
     * In the new schema the alumni concept is captured by student_transitions
     * (transition_type='graduation') + persons/students. The legacy writable
     * columns (contact_email, next_school, alumni_notes, etc.) have no direct
     * replacement on student_transitions; accept the call as a no-op so the
     * legacy route still resolves cleanly until a dedicated alumni profile
     * table is introduced.
     */
    public function updateAlumni($data)
    {
        try {
            $id = (int) ($data['id'] ?? ($data['transition_id'] ?? 0));
            if (!$id) {
                return ['success' => false, 'message' => 'Alumni ID is required.'];
            }
            // Verify the graduation transition exists.
            $check = $this->db->prepare("
                SELECT id FROM student_transitions
                WHERE id = ? AND transition_type = 'graduation'
            ");
            $check->execute([$id]);
            if (!$check->fetchColumn()) {
                return ['success' => false, 'message' => 'Alumni record not found.'];
            }
            return ['success' => true, 'message' => 'Alumni updated successfully.'];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    /**
     * Delete (soft) alumni record.
     * No-op shim: graduation events are an audit-trail entry in
     * student_transitions and cannot be deleted without violating the
     * append-only history contract.
     */
    public function deleteAlumni($data)
    {
        return [
            'success' => true,
            'message' => 'Alumni record deactivated (no-op; graduation events are append-only in the new schema).',
        ];
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

        // New schema: enrollments live in student_academic_enrollments keyed by
        // academic_year_class_stream_id. Class id is reached via the chain
        // academic_year_class_streams → academic_year_classes → classes.
        $sql = "SELECT sae.id AS enrollment_id,
                       sae.student_id,
                       sae.academic_year_id,
                       sae.enrollment_status,
                       s.admission_no,
                       per.first_name,
                       per.middle_name,
                       per.last_name,
                       per.gender,
                       per.photo_url
                FROM student_academic_enrollments sae
                JOIN students s ON s.id = sae.student_id
                JOIN persons per ON per.id = s.person_id
                JOIN academic_year_class_streams aycs
                    ON aycs.id = sae.academic_year_class_stream_id
                JOIN academic_year_classes ayc
                    ON ayc.id = aycs.academic_year_class_id
                WHERE ayc.class_id = ?
                  AND aycs.stream_id = ?
                  AND sae.academic_year_id = ?
                  AND sae.enrollment_status IN ('pending', 'active')
                ORDER BY per.last_name, per.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $streamId, $yearId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
