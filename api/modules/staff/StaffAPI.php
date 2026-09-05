<?php

namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use App\API\Modules\staff\StaffService;
use App\API\Modules\system\MediaManager;
use App\API\Services\StaffMigrationService;
use App\API\Services\DataScopeService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use \App\API\Modules\users\UsersAPI;
class StaffAPI extends BaseAPI {
    private $service;
    private $mediaManager;

    public function __construct() {
        parent::__construct('staff');
        $this->service = new StaffService();
        $this->mediaManager = new MediaManager($this->db);
    }

    // --- Media Operations ---
    // Upload staff document or photo.
    // Documents -> uploads/staff/documents/{staff_no}/
    // Photos    -> uploads/staff/profile_pictures/{staff_no}/
    public function uploadStaffMedia($staffId, $file, $type = 'document', $uploaderId = null, $description = '', $tags = '')
    {
        // Resolve the staff_no so uploads nest under the staff's own folder.
        $entityId = $staffId;
        $stmt = $this->db->prepare("SELECT staff_no FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['staff_no'])) {
            $entityId = $row['staff_no'];
        }

        $context = ($type === 'photo') ? 'staff/profile_pictures' : 'staff/documents';
        $albumId = null;
        return $this->mediaManager->upload($file, $context, $entityId, $albumId, $uploaderId, $description, $tags);
    }

    // List staff media
    public function listStaffMedia($staffId, $filters = [])
    {
        // Resolve staff_no so we match media stored under staff/documents & staff/profile_pictures.
        $entityId = $staffId;
        $stmt = $this->db->prepare("SELECT staff_no FROM staff WHERE id = ?");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['staff_no'])) {
            $entityId = $row['staff_no'];
        }
        $filters['entity_id'] = $entityId;
        return $this->mediaManager->listMedia($filters);
    }

    // Delete staff media
    public function deleteStaffMedia($mediaId)
    {
        return $this->mediaManager->deleteMedia($mediaId);
    }

    // Return the served URL for an uploaded media item (original, falling back to thumbnail).
    public function getMediaFileUrl($mediaId)
    {
        return $this->mediaManager->getFileUrl($mediaId) ?: $this->mediaManager->getPreviewUrl($mediaId);
    }

    // Persist an uploaded photo URL. Identity (incl. photo) lives on `persons` in the 4NF schema,
    // so the write targets persons.photo_url via the staff→person link (staff.profile_pic_url dropped).
    public function setProfilePicUrl($staffId, $url)
    {
        $stmt = $this->db->prepare("
            UPDATE persons p
            JOIN staff s ON s.person_id = p.id
            SET p.photo_url = ?
            WHERE s.id = ?
        ");
        $stmt->execute([$url, $staffId]);
        return true;
    }

    // List all staff members with pagination and search
    public function list($params = []) {
        try {
            $request = array_merge($_GET ?? [], $params);
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();
            $maxPageSize = defined('MAX_PAGE_SIZE') ? \MAX_PAGE_SIZE : 100;
            if (isset($request['page'])) {
                $page = max(1, (int) $request['page']);
            }
            if (isset($request['limit'])) {
                $limit = min(max(1, (int) $request['limit']), $maxPageSize);
            }
            $offset = ($page - 1) * $limit;
            $search = isset($request['search']) ? $this->sanitizeInput($request['search']) : $search;
            $sort = isset($request['sort']) ? $this->sanitizeInput($request['sort']) : $sort;
            $order = isset($request['order']) ? strtoupper($this->sanitizeInput($request['order'])) : $order;
            $order = in_array($order, ['ASC', 'DESC'], true) ? $order : 'ASC';

            $sortMap = [
                'id' => 's.id',
                'staff_no' => 's.staff_no',
                'first_name' => 'p.first_name',
                'last_name' => 'p.last_name',
                'department' => 'd.name',
                'position' => 'display_position',
                'status' => 's.status',
            ];
            $sort = $sortMap[$sort] ?? 's.id';

            $where = ['s.data_scope = ?'];
            $bindings = [DataScopeService::current()];
            if (!empty($search)) {
                $where[] = "(
                    s.staff_no LIKE ?
                    OR p.first_name LIKE ?
                    OR p.last_name LIKE ?
                    OR p.email LIKE ?
                    OR d.name LIKE ?
                    OR sc.category_name LIKE ?
                    OR st.name LIKE ?
                )";
                $searchTerm = "%$search%";
                $bindings = array_merge($bindings, [
                    $searchTerm,
                    $searchTerm,
                    $searchTerm,
                    $searchTerm,
                    $searchTerm,
                    $searchTerm,
                    $searchTerm,
                ]);
            }
            if (!empty($request['department_id'])) {
                $where[] = 'sda.department_id = ?';
                $bindings[] = (int) $request['department_id'];
            }
            if (!empty($request['staff_type_id'])) {
                $where[] = 's.staff_type_id = ?';
                $bindings[] = (int) $request['staff_type_id'];
            }
            if (!empty($request['status'])) {
                $where[] = 's.status = ?';
                $bindings[] = $request['status'];
            }
            $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            // Get total count
            $sql = "
                SELECT COUNT(DISTINCT s.id)
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN users u ON u.person_id = s.person_id
                LEFT JOIN staff_department_assignments sda ON sda.id = (
                    SELECT latest_sda.id FROM staff_department_assignments latest_sda
                    WHERE latest_sda.staff_id = s.id
                      AND (latest_sda.effective_to IS NULL OR latest_sda.effective_to >= CURDATE())
                    ORDER BY latest_sda.effective_from DESC, latest_sda.id DESC LIMIT 1
                )
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                $whereSql
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results with user data and role count (subquery avoids GROUP BY issues)
            $sql = "
                SELECT
                    s.*,
                    COALESCE(spp.basic_salary, s.salary) AS salary,
                    COALESCE(spp.bank_name, s.bank_name) AS bank_name,
                    COALESCE(spp.bank_account, s.bank_account) AS bank_account,
                    s.position AS raw_position,
                    p.first_name AS first_name,
                    p.last_name AS last_name,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS full_name,
                    p.first_name AS user_first_name,
                    p.last_name AS user_last_name,
                    p.email AS email,
                    p.phone AS phone,
                    p.gender AS gender,
                    u.status as user_status,
                    r.id as role_id,
                    r.name as role_name,
                    d.name as department_name,
                    d.code as department_code,
                    d.name as department,
                    sc.category_name AS staff_category_name,
                    st.name as staff_type_name,
                    COALESCE(
                        NULLIF((
                            SELECT GROUP_CONCAT(DISTINCT ur_roles.name ORDER BY ur_roles.name SEPARATOR ', ')
                            FROM user_roles ur
                            INNER JOIN roles ur_roles ON ur_roles.id = ur.role_id
                            WHERE ur.user_id = (SELECT id FROM users WHERE person_id = s.person_id)
                        ), ''),
                        r.name
                    ) AS role_names,
                    CASE
                        WHEN NULLIF(TRIM(s.position), '') IS NOT NULL
                             AND LOWER(TRIM(s.position)) <> 'staff'
                            THEN s.position
                        WHEN r.name IS NOT NULL AND TRIM(r.name) <> ''
                            THEN r.name
                        WHEN sc.category_name IS NOT NULL AND TRIM(sc.category_name) <> ''
                            THEN sc.category_name
                        WHEN st.name IS NOT NULL AND TRIM(st.name) <> ''
                            THEN st.name
                        ELSE 'Staff'
                    END AS position,
                    CASE
                        WHEN r.name IS NOT NULL AND TRIM(r.name) <> ''
                            THEN r.name
                        WHEN sc.category_name IS NOT NULL AND TRIM(sc.category_name) <> ''
                            THEN sc.category_name
                        WHEN st.name IS NOT NULL AND TRIM(st.name) <> ''
                            THEN st.name
                        ELSE 'Staff'
                    END AS display_position,
                    CASE s.staff_type_id
                        WHEN 1 THEN 'teaching'
                        WHEN 2 THEN 'non-teaching'
                        WHEN 3 THEN 'admin'
                        ELSE NULL
                    END as staff_type,
                    (SELECT COUNT(*) FROM user_roles ur WHERE ur.user_id = (SELECT id FROM users WHERE person_id = s.person_id)) AS role_count,
                    sda.department_id AS department_id,
                    spp.kra_pin AS kra_pin,
                    spp.nssf_no AS nssf_no,
                    spp.nhif_no AS nhif_no
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN users u ON u.person_id = s.person_id
                LEFT JOIN roles r ON r.id = (
                    SELECT ur2.role_id FROM user_roles ur2
                    WHERE ur2.user_id = u.id
                    ORDER BY ur2.id ASC LIMIT 1
                )
                LEFT JOIN staff_department_assignments sda ON sda.id = (
                    SELECT latest_sda.id FROM staff_department_assignments latest_sda
                    WHERE latest_sda.staff_id = s.id
                      AND (latest_sda.effective_to IS NULL OR latest_sda.effective_to >= CURDATE())
                    ORDER BY latest_sda.effective_from DESC, latest_sda.id DESC LIMIT 1
                )
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                $whereSql
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute payroll eligibility for each staff member
            $eligibilityChecks = [
                'staff_no' => 'Staff number',
                'department_id' => 'Department',
                'role_count' => 'Assigned role',
                'salary' => 'Basic salary',
                'kra_pin' => 'KRA PIN',
                'nssf_no' => 'NSSF number',
                'nhif_no' => 'NHIF/SHIF number',
                'phone' => 'Phone number',
                'bank_name' => 'Bank name',
                'bank_account' => 'Bank account',
            ];

            foreach ($staff as &$member) {
                $missing = [];
                foreach ($eligibilityChecks as $field => $label) {
                    $val = $member[$field] ?? null;
                    if ($field === 'role_count' && (int) $val < 1) { $missing[] = $label; continue; }
                    if ($field === 'salary' && (float) $val <= 0) { $missing[] = $label; continue; }
                    if ($val === null || trim((string) $val) === '') { $missing[] = $label; }
                }
                $member['payroll_eligible'] = empty($missing);
                $member['payroll_missing_fields'] = $missing;
                $member['profile_completeness'] = round((10 - count($missing)) / 10 * 100);
            }
            unset($member);

            $this->logAction('read', null, 'Listed staff members');

            return $this->response([
                'status' => 'success',
                'data' => [
                    'staff' => $staff,
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

    public function stats(): array
    {
        try {
            $today = date('Y-m-d');

            $scope = DataScopeService::current();
            $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM staff WHERE status = 'active' AND data_scope=?");
            $totalStmt->execute([$scope]);
            $totalStaff = (int)$totalStmt->fetchColumn();

            $teacherStmt = $this->db->prepare("SELECT COUNT(*) FROM staff WHERE status = 'active' AND staff_type_id = 1 AND data_scope=?");
            $teacherStmt->execute([$scope]);
            $teacherCount = (int)$teacherStmt->fetchColumn();

            $presentStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT sa.staff_id)
                FROM staff_attendance sa
                JOIN staff s ON s.id=sa.staff_id
                WHERE sa.date = ? AND sa.status = 'present' AND s.data_scope=?
            ");
            $presentStmt->execute([$today, $scope]);
            $staffPresentToday = (int)$presentStmt->fetchColumn();

            $deptStmt = $this->db->query("
                SELECT d.name AS department, COUNT(DISTINCT s.id) AS count
                FROM staff s
                LEFT JOIN staff_department_assignments sda
                    ON sda.staff_id = s.id
                   AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
                WHERE s.status = 'active' AND s.data_scope = " . $this->db->quote($scope) . "
                GROUP BY sda.department_id, d.name
                ORDER BY count DESC
            ");

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total_staff' => $totalStaff,
                    'teacher_count' => $teacherCount,
                    'staff_present_today' => $staffPresentToday,
                    'attendance_percentage' => $totalStaff > 0 ? round(($staffPresentToday / $totalStaff) * 100, 2) : 100,
                    'department_distribution' => $deptStmt->fetchAll(PDO::FETCH_ASSOC),
                    'date' => $today,
                    'timestamp' => date('Y-m-d H:i:s'),
                ],
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Key contacts for students/parents (viewer_staff page).
     *
     * Returns only school leadership / administration staff (name, role, phone,
     * email) — deliberately curated, no personal details. Consumers: the
     * parent/student "Staff" page. RBAC is enforced at the controller layer.
     */
    public function keyContacts(): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT CONCAT_WS(' ', p.first_name, p.last_name) AS name,
                        COALESCE(ur.name, s.position, st.name, 'Administration') AS role,
                        p.phone AS phone,
                        p.email AS email,
                        s.id AS staff_id
                   FROM staff s
                   INNER JOIN persons p ON p.id = s.person_id
                   LEFT JOIN staff_types st ON st.id = s.staff_type_id
                   LEFT JOIN users u ON u.person_id = s.person_id
                   LEFT JOIN user_roles ul ON ul.user_id = u.id
                   LEFT JOIN roles ur ON ur.id = ul.role_id
                  WHERE s.status = 'active' AND s.data_scope = ?
                    AND (
                           LOWER(COALESCE(ur.name, '')) IN (
                             'director', 'headteacher', 'school administrator',
                             'system administrator', 'deputy head - academic',
                             'deputy head academic', 'deputy head - discipline',
                             'deputy head discipline'
                           )
                           OR (
                             s.staff_type_id = 3
                             AND LOWER(COALESCE(s.position, '')) IN (
                               'director', 'headteacher', 'school administrator',
                               'deputy head - academic', 'deputy head - discipline'
                             )
                           )
                         )
                  ORDER BY FIELD(
                             LOWER(COALESCE(ur.name, s.position, '')),
                             'director', 'headteacher', 'school administrator',
                             'deputy head - academic', 'deputy head - discipline'
                           ),
                           p.first_name, p.last_name"
            );
            $stmt->execute([DataScopeService::current()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['icon'] = '👤';
            }
            unset($row);

            return $this->response(['status' => 'success', 'data' => $rows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listTeachers(array $filters = []): array
    {
        try {
            $teachingRoleNames = [
                'Class Teacher',
                'Subject Teacher',
                'Intern/Student Teacher',
                'Headteacher',
                'Deputy Head - Academic',
                'Deputy Head - Discipline',
            ];
            $teachingRoleSql = implode(', ', array_map([$this->db, 'quote'], $teachingRoleNames));

            $where = [
                "s.data_scope = ?",
                "s.status <> 'inactive'",
                "(
                    LOWER(COALESCE(st.name, '')) = 'teaching staff'
                    OR COALESCE(assign.assignment_count, 0) > 0
                    OR COALESCE(role_summary.teaching_role_count, 0) > 0
                )",
            ];
            $params = [DataScopeService::current()];
            if (!empty($filters['department_id'])) {
                // Department membership now lives in staff_department_assignments (a staff may
                // belong to several departments over time); match any active assignment.
                $where[] = "EXISTS (
                    SELECT 1 FROM staff_department_assignments sda
                    WHERE sda.staff_id = s.id AND sda.department_id = ?
                      AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                )";
                $params[] = (int)$filters['department_id'];
            }
            $subjectId = $filters['subject_id'] ?? $filters['learning_area_id'] ?? null;
            if (!empty($subjectId)) {
                // Teaching load is now academic_year_class_learning_area_teachers → learning-area context.
                $where[] = "EXISTS (
                    SELECT 1
                    FROM academic_year_class_learning_area_teachers subject_filter
                    JOIN academic_year_class_learning_areas sf_area
                        ON sf_area.id = subject_filter.academic_year_class_learning_area_id
                    JOIN academic_year_classes sf_ayc ON sf_ayc.id = sf_area.academic_year_class_id
                    JOIN academic_years subject_year ON subject_year.id = sf_ayc.academic_year_id
                    WHERE subject_filter.staff_id = s.id
                      AND subject_year.status = 'active'
                      AND sf_area.learning_area_id = ?
                )";
                $params[] = (int) $subjectId;
            }
            if (!empty($filters['role'])) {
                $where[] = "(
                    FIND_IN_SET(?, REPLACE(COALESCE(assign.assignment_roles, ''), ', ', ','))
                    OR FIND_IN_SET(?, REPLACE(COALESCE(role_summary.teaching_role_names, ''), ', ', ','))
                )";
                $params[] = (string) $filters['role'];
                $params[] = (string) $filters['role'];
            }

            $stmt = $this->db->prepare("
                SELECT
                    s.id,
                    s.staff_no AS employee_id,
                    s.staff_no,
                    p.first_name,
                    p.last_name,
                    p.phone,
                    p.gender,
                    s.employment_date,
                    s.contract_type,
                    dar.work_start_time,
                    dar.work_end_time,
                    p.email,
                    p.photo_url,
                    sda.department_id,
                    d.name AS department_name,
                    s.position,
                    s.status,
                    u.id AS user_id,
                    st.name AS staff_type_name,
                    sc.category_name AS staff_category_name,
                    COALESCE(role_summary.teaching_role_names, '') AS role_name,
                    COALESCE(role_summary.teaching_role_names, '') AS role_names,
                    COALESCE(assign.assignment_roles, '') AS assignment_roles,
                    COALESCE(assign.subject_ids, '') AS subject_ids,
                    COALESCE(assign.learning_areas, '') AS learning_areas,
                    COALESCE(assign.learning_areas, '') AS subjects,
                    COALESCE(assign.subjects_count, 0) AS subjects_count,
                    COALESCE(assign.class_ids, '') AS class_ids,
                    COALESCE(assign.classes, '') AS classes,
                    COALESCE(assign.school_level_ids, '') AS school_level_ids,
                    COALESCE(assign.school_levels, '') AS school_levels,
                    COALESCE(assign.class_teacher_count, 0) AS class_teacher_count,
                    COALESCE(assign.subject_teacher_count, 0) AS subject_teacher_count,
                    COALESCE(assign.assistant_teacher_count, 0) AS assistant_teacher_count,
                    COALESCE(assign.hod_count, 0) AS hod_count,
                    COALESCE(assign.assignment_count, 0) AS assignment_count,
                    COALESCE(assign.periods_per_week, 0) AS periods_per_week,
                    CASE WHEN COALESCE(assign.class_teacher_count, 0) > 0
                              OR FIND_IN_SET('Class Teacher', REPLACE(COALESCE(role_summary.teaching_role_names, ''), ', ', ','))
                         THEN 1 ELSE 0 END AS is_class_teacher,
                    CASE WHEN COALESCE(assign.hod_count, 0) > 0
                         THEN 1 ELSE 0 END AS is_hod
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN users u ON u.person_id = s.person_id
                LEFT JOIN staff_types st ON st.id = s.staff_type_id
                LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                LEFT JOIN staff_department_assignments sda
                    ON sda.staff_id = s.id
                   AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN department_attendance_rules dar ON dar.department_id = sda.department_id
                LEFT JOIN (
                    SELECT
                        ur.user_id,
                        GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS teaching_role_names,
                        COUNT(DISTINCT r.id) AS teaching_role_count
                    FROM user_roles ur
                    INNER JOIN roles r ON r.id = ur.role_id
                    WHERE r.scope = 'school'
                      AND r.is_system = 0
                      AND r.is_active = 1
                      AND r.name IN ($teachingRoleSql)
                    GROUP BY ur.user_id
                ) role_summary ON role_summary.user_id = u.id
                LEFT JOIN (
                    SELECT
                        t.staff_id,
                        GROUP_CONCAT(DISTINCT t.role ORDER BY t.role SEPARATOR ', ') AS assignment_roles,
                        GROUP_CONCAT(DISTINCT aycla.learning_area_id ORDER BY la.name SEPARATOR ',') AS subject_ids,
                        GROUP_CONCAT(DISTINCT la.name ORDER BY la.name SEPARATOR ', ') AS learning_areas,
                        COUNT(DISTINCT aycla.learning_area_id) AS subjects_count,
                        GROUP_CONCAT(DISTINCT ayc.class_id ORDER BY c.name SEPARATOR ',') AS class_ids,
                        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS classes,
                        GROUP_CONCAT(DISTINCT sl.id ORDER BY sl.id SEPARATOR ',') AS school_level_ids,
                        GROUP_CONCAT(DISTINCT sl.name ORDER BY sl.id SEPARATOR ', ') AS school_levels,
                        SUM(CASE WHEN t.role = 'class_teacher' THEN 1 ELSE 0 END) AS class_teacher_count,
                        SUM(CASE WHEN t.role = 'subject_teacher' THEN 1 ELSE 0 END) AS subject_teacher_count,
                        SUM(CASE WHEN t.role = 'assistant' THEN 1 ELSE 0 END) AS assistant_teacher_count,
                        SUM(CASE WHEN t.role = 'hod' THEN 1 ELSE 0 END) AS hod_count,
                        COUNT(DISTINCT t.id) AS assignment_count,
                        SUM(COALESCE(aycla.planned_weeks, 0)) AS periods_per_week
                    FROM academic_year_class_learning_area_teachers t
                    JOIN academic_year_class_learning_areas aycla
                        ON aycla.id = t.academic_year_class_learning_area_id
                    JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                    JOIN academic_years ay ON ay.id = ayc.academic_year_id
                    LEFT JOIN learning_areas la ON la.id = aycla.learning_area_id
                    INNER JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN school_levels sl ON sl.id = c.level_id
                    WHERE ay.status = 'active'
                    GROUP BY t.staff_id
                ) assign ON assign.staff_id = s.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY p.first_name, p.last_name
            ");
            $stmt->execute($params);

            $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($teachers as &$teacher) {
                $teacherId = (int) ($teacher['id'] ?? 0);
                $assignmentRoles = $this->splitCsv($teacher['assignment_roles'] ?? '');
                $systemRoles = $this->splitCsv($teacher['role_name'] ?? '');
                $teacher['teaching_roles'] = array_values(array_unique(array_merge(
                    $systemRoles,
                    array_map([$this, 'formatAssignmentRole'], $assignmentRoles)
                )));
                $teacher['subject_ids'] = array_map('intval', $this->splitCsv($teacher['subject_ids'] ?? ''));
                $teacher['learning_area_names'] = $this->splitCsv($teacher['learning_areas'] ?? '');
                $teacher['subject_names'] = $teacher['learning_area_names'];
                $teacher['class_ids'] = array_map('intval', $this->splitCsv($teacher['class_ids'] ?? ''));
                $teacher['class_names'] = $this->splitCsv($teacher['classes'] ?? '');
                $teacher['school_level_ids'] = array_map('intval', $this->splitCsv($teacher['school_level_ids'] ?? ''));
                $teacher['school_level_names'] = $this->splitCsv($teacher['school_levels'] ?? '');
                $teacher['is_class_teacher'] = (int) ($teacher['is_class_teacher'] ?? 0);
                $teacher['is_hod'] = (int) ($teacher['is_hod'] ?? 0);
                if ($teacherId > 0) {
                    $teacher['insights'] = $this->getTeacherInsightBundle($teacherId);
                }
            }
            unset($teacher);

            return $this->response(['status' => 'success', 'data' => $teachers]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function splitCsv($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, static function ($item) {
                return trim((string) $item) !== '';
            }));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), static function ($item) {
            return $item !== '';
        }));
    }

    private function formatAssignmentRole($role): string
    {
        $labels = [
            'class_teacher' => 'Class Teacher',
            'subject_teacher' => 'Subject Teacher',
            'assistant_teacher' => 'Assistant Teacher',
            'head_of_department' => 'Head of Department',
        ];

        return $labels[$role] ?? ucwords(str_replace('_', ' ', (string) $role));
    }

    private function ensureSubjectTeacherRoleForTeachingStaff(array $roleIds, array $staffInfo): array
    {
        $roleIds = array_values(array_unique(array_map('intval', array_filter($roleIds, 'is_numeric'))));
        $subjectTeacherRoleId = $this->getSchoolRoleIdByName('Subject Teacher');

        if (!$subjectTeacherRoleId || in_array($subjectTeacherRoleId, $roleIds, true)) {
            return $roleIds;
        }

        if ((int) ($staffInfo['staff_type_id'] ?? 0) === 1 || $this->roleIdsRepresentTeachingDuty($roleIds)) {
            $roleIds[] = $subjectTeacherRoleId;
        }

        return array_values(array_unique($roleIds));
    }

    private function roleIdsRepresentTeachingDuty(array $roleIds): bool
    {
        if (!$roleIds) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM roles
            WHERE id IN ($placeholders)
              AND scope = 'school'
              AND is_active = 1
              AND name IN (
                  'Subject Teacher',
                  'Class Teacher',
                  'Intern/Student Teacher',
                  'Headteacher',
                  'Deputy Head - Academic',
                  'Deputy Head - Discipline'
              )
        ");
        $stmt->execute($roleIds);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function getSchoolRoleIdByName(string $name): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM roles
            WHERE name = ?
              AND scope = 'school'
              AND is_system = 0
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$name]);
        $roleId = $stmt->fetchColumn();

        return $roleId ? (int) $roleId : null;
    }

    private function getTeacherInsightBundle(int $staffId): array
    {
        return [
            'assignments' => $this->getTeacherAssignmentRows($staffId),
            'workload' => $this->getTeacherWorkloadSnapshot($staffId),
            'qualifications' => $this->getTeacherQualifications($staffId),
            'experience' => $this->getTeacherExperience($staffId),
            'attendance' => $this->getTeacherAttendanceSnapshot($staffId),
            'performance' => $this->getTeacherPerformanceSnapshot($staffId),
            'activities' => $this->getTeacherActivities($staffId),
            'lesson_plans' => $this->getTeacherLessonPlanSnapshot($staffId),
            'observations' => $this->getTeacherObservationSnapshot($staffId),
        ];
    }

    private function getTeacherAssignmentRows(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.id,
                t.role,
                aycla.learning_area_id AS learning_area_id,
                la.name AS learning_area_name,
                c.id AS class_id,
                c.name AS class_name,
                sl.name AS school_level,
                str.name AS stream_name,
                ay.year_name AS academic_year,
                aycla.planned_weeks AS periods_per_week,
                ayt.opening_date AS start_date,
                ayt.closing_date AS end_date,
                aycla.status,
                aycla.notes
            FROM academic_year_class_learning_area_teachers t
            JOIN academic_year_class_learning_areas aycla
                ON aycla.id = t.academic_year_class_learning_area_id
            JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
            JOIN academic_years ay ON ay.id = ayc.academic_year_id
            LEFT JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
            LEFT JOIN learning_areas la ON la.id = aycla.learning_area_id
            INNER JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN school_levels sl ON sl.id = c.level_id
            LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
            LEFT JOIN streams str ON str.id = aycs.stream_id
            WHERE t.staff_id = ?
              AND ay.status = 'active'
            ORDER BY c.name, la.name, t.role
        ");
        $stmt->execute([$staffId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['role_label'] = $this->formatAssignmentRole($row['role'] ?? '');
        }
        unset($row);
        return $rows;
    }

    private function getTeacherWorkloadSnapshot(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT t.id) AS active_assignments,
                (SELECT COUNT(*) FROM academic_year_class_streams cs
                 JOIN academic_year_classes ayc ON ayc.id = cs.academic_year_class_id
                 JOIN academic_years ay ON ay.id = ayc.academic_year_id
                 WHERE cs.class_teacher_id = ? AND ay.status = 'active') AS class_teacher_classes,
                COUNT(DISTINCT aycla.learning_area_id) AS learning_areas_count,
                COUNT(DISTINCT ayc.class_id) AS classes_count,
                (SELECT COUNT(*) FROM timetable_entries te WHERE te.teacher_id = ? AND te.status = 'scheduled') AS assignment_periods_per_week
            FROM academic_year_class_learning_area_teachers t
            JOIN academic_year_class_learning_areas aycla ON aycla.id = t.academic_year_class_learning_area_id
            JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
            JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
            JOIN academic_years ay ON ay.id = ayt.academic_year_id
            WHERE t.staff_id = ?
              AND ay.status = 'active'
        ");
        $stmt->execute([$staffId, $staffId, $staffId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS scheduled_periods,
                COUNT(DISTINCT subject_id) AS scheduled_learning_areas,
                COUNT(DISTINCT class_id) AS scheduled_classes
            FROM vw_timetable_entries
            WHERE teacher_id = ?
              AND status = 'scheduled'
        ");
        $stmt->execute([$staffId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $periods = (int) ($schedule['scheduled_periods'] ?? 0);
        if ($periods === 0) {
            $periods = (int) ($assignment['assignment_periods_per_week'] ?? 0);
        }

        return [
            'active_assignments' => (int) ($assignment['active_assignments'] ?? 0),
            'class_teacher_classes' => (int) ($assignment['class_teacher_classes'] ?? 0),
            'learning_areas_count' => (int) ($assignment['learning_areas_count'] ?? 0),
            'classes_count' => (int) max((int) ($assignment['classes_count'] ?? 0), (int) ($schedule['scheduled_classes'] ?? 0)),
            'periods_per_week' => $periods,
            'scheduled_periods' => (int) ($schedule['scheduled_periods'] ?? 0),
            'status' => $this->classifyTeachingLoad($periods),
        ];
    }

    private function classifyTeachingLoad(int $periods): string
    {
        if ($periods === 0) {
            return 'not scheduled';
        }
        if ($periods < 18) {
            return 'underloaded';
        }
        if ($periods > 32) {
            return 'overloaded';
        }
        return 'balanced';
    }

    private function getTeacherQualifications(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT qualification_type, title, institution, year_obtained, description
            FROM staff_qualifications
            WHERE staff_id = ?
            ORDER BY year_obtained DESC, id DESC
            LIMIT 5
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTeacherExperience(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT organization, position, start_date, end_date, responsibilities
            FROM staff_experience
            WHERE staff_id = ?
            ORDER BY start_date DESC, id DESC
            LIMIT 5
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTeacherAttendanceSnapshot(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS marked_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent_days,
                MAX(date) AS last_marked_date
            FROM staff_attendance
            WHERE staff_id = ?
              AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$staffId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $marked = (int) ($summary['marked_days'] ?? 0);
        $present = (int) ($summary['present_days'] ?? 0);
        $late = (int) ($summary['late_days'] ?? 0);

        return [
            'marked_days' => $marked,
            'present_days' => $present,
            'late_days' => $late,
            'absent_days' => (int) ($summary['absent_days'] ?? 0),
            'last_marked_date' => $summary['last_marked_date'] ?? null,
            'attendance_rate' => $marked > 0 ? round((($present + $late) / $marked) * 100, 1) : null,
        ];
    }

    private function getTeacherPerformanceSnapshot(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pr.period AS review_period,
                pr.review_date,
                pr.rating AS overall_rating,
                pr.status,
                pr.notes,
                (SELECT COALESCE(ROUND(AVG(prk.score), 1), 0)
                   FROM performance_review_kpis prk
                  WHERE prk.review_id = pr.id) AS overall_score
            FROM performance_reviews pr
            WHERE pr.staff_id = ?
            ORDER BY pr.review_date DESC, pr.id DESC
            LIMIT 3
        ");
        $stmt->execute([$staffId]);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'latest' => $reviews[0] ?? null,
            'recent_reviews' => $reviews,
        ];
    }

    private function getTeacherActivities(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT a.title, ac.name AS category, a.status, asp.joined_at
            FROM activity_staff_participants asp
            INNER JOIN activities a ON a.id = asp.activity_id
            LEFT JOIN activity_categories ac ON ac.id = a.category_id
            WHERE asp.staff_id = ?
              AND asp.status = 'active'
            ORDER BY a.title
            LIMIT 5
        ");
        $stmt->execute([$staffId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getTeacherLessonPlanSnapshot(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN lp.status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN lp.status = 'delivered' THEN 1 ELSE 0 END) AS submitted,
                SUM(CASE WHEN lp.status = 'draft' THEN 1 ELSE 0 END) AS drafts,
                MAX(acd.date) AS latest_lesson_date
            FROM lesson_plans lp
            LEFT JOIN academic_year_calendar_days acd ON acd.id = lp.academic_year_calendar_day_id
            WHERE lp.teacher_id = ?
        ");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'drafts' => (int) ($row['drafts'] ?? 0),
            'latest_lesson_date' => $row['latest_lesson_date'] ?? null,
        ];
    }

    private function getTeacherObservationSnapshot(int $staffId): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total, AVG(rating) AS average_rating, MAX(observation_date) AS latest_observation_date
            FROM lesson_observations
            WHERE teacher_id = ?
        ");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'average_rating' => $row['average_rating'] !== null ? round((float) $row['average_rating'], 2) : null,
            'latest_observation_date' => $row['latest_observation_date'] ?? null,
        ];
    }

    public function listNonTeaching(array $filters = []): array
    {
        try {
            $where = ["s.data_scope = ?", "s.status <> 'inactive'", "LOWER(COALESCE(st.name,'')) NOT LIKE '%teach%'"];
            $params = [DataScopeService::current()];
            if (!empty($filters['department_id'])) {
                $where[] = 'sda.department_id = ?';
                $params[] = (int)$filters['department_id'];
            }

            $stmt = $this->db->prepare("
                SELECT s.*, p.first_name, p.last_name, p.email, p.phone, p.gender,
                       d.name AS department_name, st.name AS staff_type_name,
                       CONCAT(sp.first_name, ' ', sp.last_name) AS supervisor_name,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_names
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_types st ON st.id = s.staff_type_id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
                LEFT JOIN staff supervisor ON supervisor.id = s.supervisor_id
                LEFT JOIN persons sp ON sp.id = supervisor.person_id
                LEFT JOIN user_roles ur ON ur.user_id = (SELECT id FROM users WHERE person_id = s.person_id)
                LEFT JOIN roles r ON r.id = ur.role_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY s.id
                ORDER BY d.name, p.first_name, p.last_name
            ");
            $stmt->execute($params);

            return $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single staff member
    public function get($id) {
        try {
            $staff = $this->getStaffWithUserData($id);

            if (!$staff) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            // Get staff qualifications
            $sql = "
                SELECT 
                    qualification_type,
                    title,
                    institution,
                    year_obtained,
                    description,
                    document_url
                FROM staff_qualifications
                WHERE staff_id = ?
                ORDER BY year_obtained DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $staff['qualifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get staff experience
            $sql = "
                SELECT 
                    organization,
                    position,
                    start_date,
                    end_date,
                    responsibilities,
                    document_url
                FROM staff_experience
                WHERE staff_id = ?
                ORDER BY start_date DESC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $staff['experience'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', $id, "Retrieved staff member: {$staff['first_name']} {$staff['last_name']}");
            
            return $this->response(['status' => 'success', 'data' => $staff]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new staff member
    public function create($data) {
        try {
            $required = [
                'first_name',
                'last_name',
                'email',
                'department_id',
                'position',
                'employment_date',
                'phone',
                'kra_pin',
                'nssf_no',
                'nhif_no',
                'bank_name',
                'bank_account',
                'salary'
            ];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Delegate user+staff creation to UsersAPI (do not duplicate staff insert here)

            // Create user account via UsersAPI using canonical payload (role_ids + staff_info)
            $usersApi = new UsersAPI();
            $roleIds = [];
            if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
                $roleIds = $data['role_ids'];
            } elseif (isset($data['role_id'])) {
                $roleIds = [$data['role_id']];
            } else {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => ['role_ids']
                ], 400);
            }

            // Map staff_type string to staff_type_id if provided
            $staffTypeId = null;
            if (!empty($data['staff_type']) && empty($data['staff_type_id'])) {
                $map = [
                    'teaching' => 1,
                    'non-teaching' => 2,
                    'non_teaching' => 2,
                    'admin' => 3,
                    'administration' => 3
                ];
                $key = strtolower(trim((string) $data['staff_type']));
                $staffTypeId = $map[$key] ?? null;
            } elseif (!empty($data['staff_type_id'])) {
                $staffTypeId = (int) $data['staff_type_id'];
            }

            $staffInfo = array_filter([
                'position' => $data['position'] ?? 'Staff',
                'employment_date' => $data['employment_date'] ?? date('Y-m-d'),
                'department_id' => $data['department_id'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'phone' => $data['phone'] ?? $data['phone_number'] ?? null,
                'nssf_no' => $data['nssf_no'] ?? null,
                'kra_pin' => $data['kra_pin'] ?? null,
                'nhif_no' => $data['nhif_no'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account' => $data['bank_account'] ?? null,
                'salary' => $data['salary'] ?? null,
                'gender' => $data['gender'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'address' => $data['address'] ?? null,
                // tsc_no / profile_pic_url / documents_folder are dropped in the 4NF schema.
                // Identity photo lives on persons.photo_url (set via setProfilePicUrl / the
                // post-create placeholder block); TSC number and the documents folder have no
                // column in the normalized model, so they are not passed downstream.
                'staff_type_id' => $staffTypeId
            ], function ($v) {
                return $v !== null && $v !== '';
            });

            // If caller provided nested staff_info, merge and prefer those values
            if (!empty($data['staff_info']) && is_array($data['staff_info'])) {
                $staffInfo = array_merge($staffInfo, array_filter($data['staff_info'], function ($v) {
                    return $v !== null && $v !== '';
                }));
            }
            $roleIds = $this->ensureSubjectTeacherRoleForTeachingStaff($roleIds, $staffInfo);

            $temporaryPassword = $data['password'] ?? $this->generateTemporaryPassword();

            $userPayload = [
                'email' => $data['email'],
                'password' => $temporaryPassword,
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'role_ids' => $roleIds,
                'status' => 'active',
                'force_password_change' => 1,
                'staff_info' => $staffInfo
            ];

            // Only email identifies an existing person. A username collision belongs
            // to another account and is resolved by the central username service.
            // Email now lives on persons (4NF), so match through the persons join.
            $existingUserStmt = $this->db->prepare('
                SELECT u.id, u.username
                FROM users u
                JOIN persons p ON p.id = u.person_id
                WHERE p.email = ?
                LIMIT 1
            ');
            $existingUserStmt->execute([$data['email']]);
            $existingUser = $existingUserStmt->fetch(PDO::FETCH_ASSOC);
            if ($existingUser) {
                $userId = $existingUser['id'];
                $username = $existingUser['username'];
                $addResult = $usersApi->addStaffForUser($userId, $staffInfo, $roleIds);
                if (!isset($addResult['success']) || !$addResult['success']) {
                    throw new Exception('Failed to create staff for existing user: ' . ($addResult['error'] ?? json_encode($addResult)));
                }
                $stmt = $this->db->prepare("
                    UPDATE users
                    SET password_hash = ?,
                        status = 'active',
                        force_password_change = 1,
                        password_changed_at = NULL,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $userId]);
            } else {
                $userResult = $usersApi->create($userPayload);
                if (!isset($userResult['success']) || !$userResult['success']) {
                    throw new Exception('Failed to create user: ' . ($userResult['error'] ?? json_encode($userResult)));
                }
                $username = $userResult['data']['username'] ?? '';

                // Determine created user ID (returned in data or fetch by email as fallback)
                $userId = $userResult['data']['id'] ?? null;
                if (!$userId) {
                    $stmt = $this->db->prepare("
                        SELECT u.id
                        FROM users u
                        JOIN persons p ON p.id = u.person_id
                        WHERE p.email = ?
                    ");
                    $stmt->execute([$data['email']]);
                    $row = $stmt->fetch();
                    if ($row) {
                        $userId = $row['id'];
                    }
                }
                if (!$userId) {
                    throw new Exception('Unable to determine created user id');
                }
            }

            // Expect UsersAPI.create to have created the staff row. staff links to users only
            // through persons (staff.person_id = users.person_id); the photo lives on persons.photo_url.
            $stmt = $this->db->prepare("
                SELECT s.id, s.staff_no, p.photo_url
                FROM staff s
                JOIN users u ON u.person_id = s.person_id
                JOIN persons p ON p.id = s.person_id
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $staffId = $existing['id'];
                $staffNo = $existing['staff_no'];
                $profilePic = $existing['photo_url'] ?? null;
            } else {
                throw new Exception('Staff record was not created by UsersAPI');
            }

            $this->ensureStaffOnboardingArtifacts(
                (int) $staffId,
                (int) $userId,
                $staffInfo,
                $data
            );

            $invitationToken = $this->createStaffInvitation(
                (int) $userId,
                (int) $staffId,
                $data['email']
            );
            $this->queueStaffInvitationEmail(
                (int) $userId,
                $data['email'],
                trim($data['first_name'] . ' ' . $data['last_name']),
                $username,
                $temporaryPassword,
                $invitationToken
            );
            if (empty($data['defer_invitation_delivery'])) {
                try {
                    (new StaffMigrationService($this->db))->processEmailQueue(1);
                } catch (Exception $mailError) {
                    \App\API\Services\Logger::legacyError('Manual staff invitation delivery failed: ' . $mailError->getMessage());
                }
            }

            // Ensure a placeholder profile picture. In the normalized schema the photo is a
            // person attribute (persons.photo_url); staff no longer carries profile_pic_url or a
            // documents_folder column (document storage is handled by the upload service).
            if (empty($profilePic)) {
                $placeholderPic = $this->managedPublicUrl(
                    'staff_photo',
                    'staff_avatar.jpeg'
                );
                $stmt = $this->db->prepare("
                    UPDATE persons p
                    JOIN staff s ON s.person_id = p.id
                    SET p.photo_url = ?
                    WHERE s.id = ?
                ");
                $stmt->execute([$placeholderPic, $staffId]);
            }

            // Physical directories are created lazily by UploadService when a
            // staff document or photo is actually uploaded. No controller/module
            // constructs or copies upload paths.

            // Ensure at least one placeholder qualification and experience row exist
            $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM staff_qualifications WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $qcount = (int) $stmt->fetchColumn();
            if ($qcount === 0) {
                $sql = "INSERT INTO staff_qualifications (staff_id, qualification_type, title, institution, year_obtained, description, document_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                // Use current year as a safe default for placeholder qualifications to satisfy NOT NULL constraint
                $stmt->execute([$staffId, 'other', 'To be uploaded', 'N/A', date('Y'), null, null]);
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) as cnt FROM staff_experience WHERE staff_id = ?");
            $stmt->execute([$staffId]);
            $ecount = (int) $stmt->fetchColumn();
            if ($ecount === 0) {
                $sql = "INSERT INTO staff_experience (staff_id, organization, position, start_date, end_date, responsibilities, document_url) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                // Use employment_date or today as a safe default for start_date (NOT NULL constraint)
                $safeStart = $data['employment_date'] ?? ($staffInfo['employment_date'] ?? date('Y-m-d'));
                $stmt->execute([$staffId, 'placeholder', 'To be updated', $safeStart, null, null, null]);
            }

            // Add qualifications if provided
            if (!empty($data['qualifications'])) {
                    $sql = "
                        INSERT INTO staff_qualifications (
                            staff_id,
                        qualification_type,
                        title,
                            institution,
                        year_obtained,
                        description,
                        document_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                foreach ($data['qualifications'] as $qual) {
                    $stmt->execute([
                        $staffId,
                        $qual['type'],
                        $qual['title'],
                        $qual['institution'],
                        $qual['year'],
                        $qual['description'] ?? null,
                        $qual['document_url'] ?? null
                    ]);
                }
            }

            // Add experience if provided
            if (!empty($data['experience'])) {
                    $sql = "
                        INSERT INTO staff_experience (
                            staff_id,
                            organization,
                            position,
                            start_date,
                            end_date,
                        responsibilities,
                        document_url
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                foreach ($data['experience'] as $exp) {
                    $stmt->execute([
                        $staffId,
                        $exp['organization'],
                        $exp['position'],
                        $exp['start_date'],
                        $exp['end_date'] ?? null,
                        $exp['responsibilities'] ?? null,
                        $exp['document_url'] ?? null
                    ]);
                }
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Staff member created successfully',
                'data' => ['id' => $staffId, 'staff_no' => $staffNo]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update staff member
    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM staff WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            // Resolve the person + user behind this staff row (4NF: staff→persons; users→persons).
            $link = $this->db->prepare("
                SELECT s.person_id, u.id AS user_id
                FROM staff s
                LEFT JOIN users u ON u.person_id = s.person_id
                WHERE s.id = ?
            ");
            $link->execute([$id]);
            $linkRow = $link->fetch(PDO::FETCH_ASSOC) ?: [];
            $personId = (int)($linkRow['person_id'] ?? 0) ?: null;
            $userId   = (int)($linkRow['user_id'] ?? 0) ?: null;

            // Identity fields live on `persons` (shared by staff + users). Route them there.
            if ($personId) {
                $personUpdates = [];
                $personParams = [];
                $personMap = [
                    'first_name'    => 'first_name',
                    'middle_name'   => 'middle_name',
                    'last_name'     => 'last_name',
                    'email'         => 'email',
                    'phone'         => 'phone',
                    'gender'        => 'gender',
                    'national_id_no'=> 'national_id_no',
                    'date_of_birth' => 'dob',
                    'dob'           => 'dob',
                ];
                foreach ($personMap as $in => $col) {
                    if (array_key_exists($in, $data)) {
                        $personUpdates[] = "$col = ?";
                        $personParams[] = $data[$in];
                    }
                }
                if ($personUpdates) {
                    $personParams[] = $personId;
                    $this->db->prepare("UPDATE persons SET " . implode(', ', $personUpdates) . " WHERE id = ?")
                             ->execute($personParams);
                }
            }

            // Account-level fields on `users`. Identity/role columns were dropped from users:
            // status is the only writable column here; role changes go through the user_roles junction.
            if ($userId) {
                if (isset($data['user_status'])) {
                    $this->db->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?")
                             ->execute([$data['user_status'], $userId]);
                }
                if (!empty($data['role_id'])) {
                    (new UsersAPI())->assignRoleToUser($userId, (int)$data['role_id']);
                }
            }

            if (!empty($data['staff_type']) && empty($data['staff_type_id'])) {
                $map = [
                    'teaching' => 1,
                    'non-teaching' => 2,
                    'non_teaching' => 2,
                    'admin' => 3,
                    'administration' => 3
                ];
                $key = strtolower(trim((string) $data['staff_type']));
                $data['staff_type_id'] = $map[$key] ?? null;
            }

            // Employment fields that remain on the `staff` table (marital_status/address/tsc_no/
            // profile_pic_url/documents_folder are dropped; department_id moved to a junction).
            $updates = [];
            $params = [];
            $allowedFields = [
                'staff_type_id',
                'staff_category_id',
                'supervisor_id',
                'position',
                'employment_date',
                'contract_type',
                'salary',
                'bank_name',
                'bank_account',
                'status',
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $updates[] = "updated_at = NOW()";
                $params[] = $id;
                $sql = "UPDATE staff SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Statutory/bank details normalise to staff_payroll_profiles (upsert the active row).
            $payrollCols = [];
            $payrollVals = [];
            $payrollMap = ['kra_pin' => 'kra_pin', 'nssf_no' => 'nssf_no', 'nhif_no' => 'nhif_no',
                           'bank_name' => 'bank_name', 'bank_account' => 'bank_account', 'salary' => 'basic_salary'];
            foreach ($payrollMap as $in => $col) {
                if (array_key_exists($in, $data)) {
                    $payrollCols[$col] = $data[$in];
                }
            }
            if ($payrollCols) {
                $has = $this->db->prepare("SELECT id FROM staff_payroll_profiles WHERE staff_id = ? LIMIT 1");
                $has->execute([$id]);
                if ($has->fetchColumn()) {
                    $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($payrollCols)));
                    $vals = array_values($payrollCols);
                    $vals[] = $id;
                    $this->db->prepare("UPDATE staff_payroll_profiles SET $set, updated_at = NOW() WHERE staff_id = ?")
                             ->execute($vals);
                } else {
                    $cols = array_keys($payrollCols);
                    $ph = implode(', ', array_fill(0, count($cols), '?'));
                    $this->db->prepare(
                        "INSERT INTO staff_payroll_profiles (staff_id, " . implode(', ', $cols) . ", status, created_at, updated_at)
                         VALUES (?, $ph, 'active', NOW(), NOW())"
                    )->execute(array_merge([$id], array_values($payrollCols)));
                }
            }

            // Personal facts are normalized temporal/contact records, not staff columns.
            if ($personId && array_key_exists('address', $data) && trim((string)$data['address']) !== '') {
                $this->db->prepare(
                    "UPDATE person_addresses SET valid_to = CURDATE(), updated_at = NOW()
                     WHERE person_id = ? AND address_type = 'residential'
                       AND valid_to IS NULL AND valid_from <> CURDATE()"
                )->execute([$personId]);
                $this->db->prepare(
                    "INSERT INTO person_addresses
                        (person_id, address_type, address_line, is_primary, valid_from, created_at, updated_at)
                     VALUES (?, 'residential', ?, 1, CURDATE(), NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        address_line = VALUES(address_line), is_primary = 1,
                        valid_to = NULL, updated_at = NOW()"
                )->execute([$personId, trim((string)$data['address'])]);
            }

            if ($personId && array_key_exists('marital_status', $data)) {
                $maritalStatus = strtolower(trim((string)$data['marital_status']));
                $allowedMaritalStatuses = ['single', 'married', 'divorced', 'widowed', 'separated', 'unknown'];
                if (!in_array($maritalStatus, $allowedMaritalStatuses, true)) {
                    throw new Exception('Invalid marital_status');
                }
                $this->db->prepare(
                    'UPDATE person_marital_statuses SET valid_to = CURDATE()
                     WHERE person_id = ? AND valid_to IS NULL AND valid_from <> CURDATE()'
                )->execute([$personId]);
                $this->db->prepare(
                    'INSERT INTO person_marital_statuses
                        (person_id, marital_status, valid_from, created_at)
                     VALUES (?, ?, CURDATE(), NOW())
                     ON DUPLICATE KEY UPDATE marital_status = VALUES(marital_status), valid_to = NULL'
                )->execute([$personId, $maritalStatus]);
            }

            if ($personId && (!empty($data['emergency_contact_name']) || !empty($data['emergency_contact_phone']))) {
                $contactName = trim((string)($data['emergency_contact_name'] ?? 'Emergency Contact'));
                $contactName = $contactName !== '' ? $contactName : 'Emergency Contact';
                $exists = $this->db->prepare(
                    'SELECT id FROM emergency_contacts WHERE person_id = ? AND name = ? LIMIT 1'
                );
                $exists->execute([$personId, $contactName]);
                if (!$exists->fetchColumn()) {
                    $this->db->prepare(
                        'INSERT INTO emergency_contacts (id, person_id, name, phone, relationship, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())'
                    )->execute([
                        (int)$this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM emergency_contacts')->fetchColumn(),
                        $personId,
                        $contactName,
                        $data['emergency_contact_phone'] ?? null,
                        $data['emergency_contact_relationship'] ?? null,
                    ]);
                }
            }

            // Department membership normalises to staff_department_assignments (current = effective_to IS NULL).
            if (array_key_exists('department_id', $data) && $data['department_id']) {
                $newDept = (int)$data['department_id'];
                $curr = $this->db->prepare(
                    "SELECT department_id FROM staff_department_assignments
                     WHERE staff_id = ? AND effective_to IS NULL ORDER BY id DESC LIMIT 1"
                );
                $curr->execute([$id]);
                $currentDept = (int)$curr->fetchColumn();
                if ($currentDept !== $newDept) {
                    // Close the old membership (history) and append the new one — never overwrite.
                    $this->db->prepare(
                        "UPDATE staff_department_assignments SET effective_to = CURDATE()
                         WHERE staff_id = ? AND effective_to IS NULL"
                    )->execute([$id]);
                    $deptNextId = (int) $this->db->query("SELECT COALESCE(MAX(id),0)+1 FROM staff_department_assignments")->fetchColumn();
                    $this->db->prepare(
                        "INSERT INTO staff_department_assignments (id, staff_id, department_id, role, effective_from, created_at)
                         VALUES (?, ?, ?, ?, CURDATE(), NOW())"
                    )->execute([$deptNextId, $id, $newDept, $data['department_role'] ?? 'member']);
                }
            }

            // Update qualifications if provided
            if (!empty($data['qualification_details'])) {
                // Remove existing qualifications
                $stmt = $this->db->prepare("DELETE FROM staff_qualifications WHERE staff_id = ?");
                $stmt->execute([$id]);

                // Add new qualifications (4NF columns: qualification_type/title/year_obtained/description)
                foreach ($data['qualification_details'] as $qual) {
                    $sql = "
                        INSERT INTO staff_qualifications (
                            staff_id,
                            qualification_type,
                            title,
                            institution,
                            year_obtained,
                            description
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $id,
                        $qual['qualification_type'] ?? 'degree',
                        $qual['title'] ?? $qual['degree'] ?? '',
                        $qual['institution'],
                        $qual['year_obtained'] ?? $qual['year'] ?? null,
                        $qual['description'] ?? $qual['details'] ?? null
                    ]);
                }
            }

            // Update experience if provided
            if (!empty($data['experience_details'])) {
                // Remove existing experience
                $stmt = $this->db->prepare("DELETE FROM staff_experience WHERE staff_id = ?");
                $stmt->execute([$id]);

                // Add new experience (4NF column: responsibilities, not description)
                foreach ($data['experience_details'] as $exp) {
                    $sql = "
                        INSERT INTO staff_experience (
                            staff_id,
                            organization,
                            position,
                            start_date,
                            end_date,
                            responsibilities
                        ) VALUES (?, ?, ?, ?, ?, ?)
                    ";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        $id,
                        $exp['organization'],
                        $exp['position'],
                        $exp['start_date'],
                        $exp['end_date'] ?? null,
                        $exp['responsibilities'] ?? $exp['description'] ?? null
                    ]);
                }
            }

            $this->logAction('update', $id, "Updated staff member details");

            return $this->response([
                'status' => 'success',
                'message' => 'Staff updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete staff member (soft delete)
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE staff SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            $this->logAction('delete', $id, "Deactivated staff member");
            
            return $this->response([
                'status' => 'success',
                'message' => 'Staff deleted successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Custom GET endpoints
    public function handleCustomGet($id, $action, $params) {
        switch ($action) {
            case 'schedule':
                return $this->getTeachingSchedule($id);
            case 'attendance':
                return $this->getAttendanceRecord($id, $params);
            case 'leave':
                return $this->getLeaveHistory($id);
            case 'departments':
                return $this->getDepartmentAssignments($id);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Custom POST endpoints
    public function handleCustomPost($id, $action, $data) {
        switch ($action) {
            case 'leave':
                return $this->submitLeaveRequest($id, $data);
            case 'attendance':
                return $this->markAttendance($id, $data);
            default:
                return $this->response(['status' => 'error', 'message' => 'Invalid action'], 400);
        }
    }

    // Staff numbers are now generated by StaffNumberService — this method is intentionally removed.

    // Implementation of custom endpoint methods
    private function getTeachingSchedule($id) {
        try {
            $sql = "
                SELECT
                    t.id,
                    t.staff_id AS teacher_id,
                    t.role,
                    aycla.learning_area_id AS subject_id,
                    la.name as subject_name,
                    ayc.class_id,
                    c.name as class_name,
                    str.name AS stream_name,
                    ay.year_name AS academic_year
                FROM academic_year_class_learning_area_teachers t
                JOIN academic_year_class_learning_areas aycla
                    ON aycla.id = t.academic_year_class_learning_area_id
                JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                JOIN learning_areas la ON la.id = aycla.learning_area_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN streams str ON str.id = aycs.stream_id
                WHERE t.staff_id = ? AND ay.status = 'active'
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $schedule
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getAttendanceRecord($id, $params) {
        try {
            $month = isset($params['month']) ? $params['month'] : date('m');
            $year = isset($params['year']) ? $params['year'] : date('Y');

            $sql = "
                SELECT *
                FROM vw_staff_monthly_summary
                WHERE staff_id = ? AND attendance_month = ? AND attendance_year = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $month, $year]);
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $attendance
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getLeaveHistory($id) {
        try {
            $sql = "
                SELECT
                    sl.*,
                    lt.name as leave_type,
                    lt.days_allowed,
                    CONCAT(p.first_name, ' ', p.last_name) as approved_by_name
                FROM staff_leaves sl
                JOIN leave_types lt ON sl.leave_type_id = lt.id
                LEFT JOIN staff s ON sl.approved_by = s.id
                LEFT JOIN persons p ON p.id = s.person_id
                WHERE sl.staff_id = ?
                ORDER BY sl.start_date DESC
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $leaveHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $leaveHistory
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getDepartmentAssignments($id) {
        try {
            $sql = "
                SELECT 
                    sd.*,
                    d.name as department_name,
                    CASE WHEN d.head_id = ? THEN true ELSE false END as is_hod
                FROM staff_department_assignments sd
                JOIN departments d ON sd.department_id = d.id
                WHERE sd.staff_id = ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $id]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $departments
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function submitLeaveRequest($id, $data) {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['leave_type_id', 'start_date', 'end_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO staff_leaves (staff_id, leave_type_id, start_date, end_date, days_requested, reason)
                VALUES (?, ?, ?, ?, DATEDIFF(?, ?) + 1, ?)
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $id,
                $data['leave_type_id'],
                $data['start_date'],
                $data['end_date'],
                $data['end_date'],
                $data['start_date'],
                $data['reason']
            ]);

            $leaveId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction('create', $leaveId, "Submitted leave request for staff ID: $id");

            return $this->response([
                'status' => 'success',
                'message' => 'Leave request submitted successfully',
                'data' => ['id' => $leaveId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }


    public function getProfile($id) {
        try {
            $sql = "
                SELECT
                    s.*,
                    p.email,
                    p.middle_name,
                    p.phone,
                    p.gender,
                    p.dob AS date_of_birth,
                    p.photo_url,
                    sc.category_name,
                    d.name AS department_name,
                    spp.kra_pin,
                    spp.nssf_no,
                    spp.nhif_no,
                    CONCAT_WS(' ', sp.first_name, sp.last_name) AS supervisor_name,
                    (
                        SELECT COUNT(DISTINCT ayc.class_id)
                        FROM academic_year_class_learning_area_teachers t
                        JOIN academic_year_class_learning_areas aycla ON aycla.id = t.academic_year_class_learning_area_id
                        JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                        JOIN academic_year_terms ayt ON ayt.id = t.academic_year_term_id
                        JOIN academic_years ay ON ay.id = ayt.academic_year_id
                        WHERE t.staff_id = s.id AND ay.status = 'active'
                    ) + (
                        SELECT COUNT(DISTINCT ayc.class_id)
                        FROM academic_year_class_streams cs
                        JOIN academic_year_classes ayc ON ayc.id = cs.academic_year_class_id
                        JOIN academic_years ay ON ay.id = ayc.academic_year_id
                        WHERE cs.class_teacher_id = s.id AND ay.status = 'active'
                    ) AS assigned_classes,
                    (
                        SELECT COUNT(DISTINCT cs.subject_id)
                        FROM vw_timetable_entries cs
                        WHERE cs.teacher_id = s.id
                          AND cs.status = 'scheduled'
                    ) AS assigned_subjects
                FROM staff s
                INNER JOIN persons p ON p.id = s.person_id
                LEFT JOIN users u ON u.person_id = p.id
                LEFT JOIN staff supervisor ON supervisor.id = s.supervisor_id
                LEFT JOIN persons sp ON sp.id = supervisor.person_id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id
                LEFT JOIN departments d ON d.id = sda.department_id
                WHERE s.id = ?
                LIMIT 1
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return $this->response(['status' => 'error', 'message' => 'Staff not found'], 404);
            }

            return $this->response(['status' => 'success', 'data' => $profile]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchedule($id) {
        try {
            $sql = "
                SELECT 
                    cs.*
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = ?
                ORDER BY 
                    cs.day_of_week,
                    cs.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $schedule]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignClass($id, $data) {
        try {
            if (empty($data['class_id']) || empty($data['stream_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Class ID and stream ID are required'
                ], 400);
            }

            $sql = "UPDATE academic_year_class_streams aycs
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN academic_years ay ON ay.id = ayc.academic_year_id
                    SET aycs.class_teacher_id = ?
                    WHERE ayc.class_id = ? AND aycs.stream_id = ? AND ay.status = 'active'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id, $data['class_id'], $data['stream_id']]);

            return $this->response([
                'status' => 'success',
                'message' => 'Class assigned successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function assignSubject($id, $data) {
        try {
            if (empty($data['subject_id']) || empty($data['class_id']) || empty($data['stream_id'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Subject ID, Class ID and Stream ID are required'
                ], 400);
            }

            // Subjects are now assigned via timetable_entries (a scheduled Monday 08:00 lesson)
            $ayId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn();
            if ($ayId <= 0) {
                $ayId = (int) $this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
            }
            if ($ayId <= 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No active academic year found'
                ], 400);
            }
            $stmt = $this->db->prepare(
                "SELECT aycs.id FROM academic_year_class_streams aycs
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 WHERE ayc.academic_year_id = ? AND ayc.class_id = ? AND aycs.stream_id = ?
                 ORDER BY aycs.id LIMIT 1"
            );
            $stmt->execute([$ayId, $data['class_id'], $data['stream_id']]);
            $classStreamId = (int) $stmt->fetchColumn();
            if ($classStreamId <= 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No active class-stream found for the given class'
                ], 400);
            }
            $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'current' LIMIT 1");
            $stmt->execute([$ayId]);
            $termId = (int) $stmt->fetchColumn();
            if ($termId <= 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No current term found for the academic year'
                ], 400);
            }
            $stmt = $this->db->prepare("SELECT id FROM time_slots WHERE is_active = 1 AND start_time = '08:00:00' ORDER BY period_number LIMIT 1");
            $stmt->execute();
            $timeSlotId = (int) $stmt->fetchColumn();
            if ($timeSlotId <= 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No matching time slot found'
                ], 400);
            }
            $stmt = $this->db->prepare("SELECT learning_area_id FROM strands WHERE id = ? LIMIT 1");
            $stmt->execute([$data['subject_id']]);
            $learningAreaId = (int) $stmt->fetchColumn();
            if ($learningAreaId <= 0) {
                $learningAreaId = (int) $data['subject_id'];
            }
            $contextId = (int)$this->db->query(
                "SELECT sla.id
                 FROM academic_year_class_stream_learning_areas sla
                 JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id
                 WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ? LIMIT 1",
                [$classStreamId, $learningAreaId]
            )->fetchColumn();
            if ($contextId <= 0) {
                return $this->response(['status' => 'error', 'message' => 'Learning area is not configured for the selected class stream'], 400);
            }
            $entryId = (int) $this->db->query("SELECT COALESCE(MAX(id),0)+1 FROM timetable_entries")->fetchColumn();
            $sql = "INSERT INTO timetable_entries (id, academic_year_class_stream_id, academic_year_class_stream_learning_area_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id, status)
                    VALUES (?, ?, ?, ?, 1, ?, ?, ?, 'scheduled')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$entryId, $classStreamId, $contextId, $termId, $timeSlotId, $learningAreaId, $id]);

            return $this->response([
                'status' => 'success',
                'message' => 'Subject assigned successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDepartments() {
        try {
            $sql = "SELECT * FROM departments WHERE status = 'active' ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $departments]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAttendance($params) {
        try {
            $params = is_array($params) ? $params : [];
            $sql = "
                SELECT
                    sa.*,
                    p.first_name,
                    p.last_name,
                    s.staff_no,
                    s.id AS staff_id,
                    d.name AS department_name
                FROM staff_attendance sa
                JOIN staff s ON sa.staff_id = s.id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
                WHERE sa.date BETWEEN ? AND ?
            ";
            $bindings = [
                $params['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $params['end_date'] ?? date('Y-m-d')
            ];
            if (!empty($params['staff_id'])) {
                $sql .= " AND sa.staff_id = ?";
                $bindings[] = (int) $params['staff_id'];
            }
            $sql .= " ORDER BY sa.date DESC, p.first_name, p.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $attendance]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function markAttendance($data) {
        try {
            $required = ['staff_id', 'date', 'status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $attendanceId = (int) $this->db->query("SELECT COALESCE(MAX(id),0)+1 FROM staff_attendance")->fetchColumn();
            $sql = "
                INSERT INTO staff_attendance (
                    id,
                    staff_id,
                    date,
                    status,
                    check_in,
                    check_out,
                    notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    check_in = VALUES(check_in),
                    check_out = VALUES(check_out),
                    notes = VALUES(notes)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $attendanceId,
                $data['staff_id'],
                $data['date'],
                $data['status'],
                $data['check_in_time'] ?? $data['check_in'] ?? null,
                $data['check_out_time'] ?? $data['check_out'] ?? null,
                $data['notes'] ?? $data['remarks'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Attendance marked successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getLeaves($params) {
        try {
            $sql = "
                SELECT
                    sl.*,
                    p.first_name,
                    p.last_name,
                    s.staff_no,
                    s.id as staff_id,
                    d.name as department_name
                FROM staff_leaves sl
                JOIN staff s ON sl.staff_id = s.id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
                WHERE sl.start_date >= ? AND sl.end_date <= ?
                ORDER BY sl.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $params['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                $params['end_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ]);

            $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $leaves]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function applyLeave($data) {
        try {
            $required = ['staff_id', 'start_date', 'end_date', 'reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }
            $leaveTypeId = $data['leave_type_id'] ?? null;
            $leaveType = $data['leave_type'] ?? $data['type'] ?? null;
            if (!$leaveTypeId && !$leaveType) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'leave_type_id or leave_type is required'
                ], 400);
            }
            if (!$leaveTypeId && $leaveType) {
                $lookup = $this->db->prepare("SELECT id FROM leave_types WHERE code = ? OR name = ? LIMIT 1");
                $lookup->execute([$leaveType, $leaveType]);
                $leaveTypeId = $lookup->fetchColumn();
            }
            if (!$leaveTypeId) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Leave type was not found'
                ], 400);
            }

            $sql = "
                INSERT INTO staff_leaves (
                    staff_id,
                    leave_type_id,
                    leave_type,
                    start_date,
                    end_date,
                    days_requested,
                    reason,
                    status,
                    attachments_folder
                ) VALUES (?, ?, ?, ?, ?, DATEDIFF(?, ?) + 1, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $leaveTypeId,
                $leaveType,
                $data['start_date'],
                $data['end_date'],
                $data['end_date'],
                $data['start_date'],
                $data['reason'],
                $data['status'] ?? 'pending',
                $data['documents'] ?? $data['attachments_folder'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Leave application submitted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateLeaveStatus($id, $data) {
        try {
            if (empty($data['status'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Status is required'
                ], 400);
            }

            $sql = "
                UPDATE staff_leaves
                SET status = ?,
                    approved_by = CASE WHEN ? = 'approved' THEN ? ELSE approved_by END,
                    approved_at = CASE WHEN ? = 'approved' THEN NOW() ELSE approved_at END,
                    rejection_reason = CASE WHEN ? = 'rejected' THEN ? ELSE rejection_reason END
                WHERE id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['status'],
                $data['status'],
                $data['approved_by'] ?? null,
                $data['status'],
                $data['status'],
                $data['remarks'] ?? $data['rejection_reason'] ?? null,
                $id
            ]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Leave not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Leave status updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function getStaffWithUserData($id) {
        $sql = "
            SELECT
                s.*,
                s.position AS raw_position,
                p.first_name AS first_name,
                p.last_name AS last_name,
                CONCAT_WS(' ', p.first_name, p.last_name) AS full_name,
                p.first_name AS user_first_name,
                p.last_name AS user_last_name,
                p.email AS email,
                p.phone AS phone,
                p.gender AS gender,
                u.status as user_status,
                r.id as role_id,
                r.name as role_name,
                d.name as department_name,
                d.code as department_code,
                d.name as department,
                sc.category_name AS staff_category_name,
                st.name as staff_type_name,
                COALESCE(
                    NULLIF((
                        SELECT GROUP_CONCAT(DISTINCT ur_roles.name ORDER BY ur_roles.name SEPARATOR ', ')
                        FROM user_roles ur
                        INNER JOIN roles ur_roles ON ur_roles.id = ur.role_id
                        WHERE ur.user_id = (SELECT id FROM users WHERE person_id = s.person_id)
                    ), ''),
                    r.name
                ) AS role_names,
                CASE
                    WHEN NULLIF(TRIM(s.position), '') IS NOT NULL
                         AND LOWER(TRIM(s.position)) <> 'staff'
                        THEN s.position
                    WHEN r.name IS NOT NULL AND TRIM(r.name) <> ''
                        THEN r.name
                    WHEN sc.category_name IS NOT NULL AND TRIM(sc.category_name) <> ''
                        THEN sc.category_name
                    WHEN st.name IS NOT NULL AND TRIM(st.name) <> ''
                        THEN st.name
                    ELSE 'Staff'
                END AS position,
                CASE
                    WHEN r.name IS NOT NULL AND TRIM(r.name) <> ''
                        THEN r.name
                    WHEN sc.category_name IS NOT NULL AND TRIM(sc.category_name) <> ''
                        THEN sc.category_name
                    WHEN st.name IS NOT NULL AND TRIM(st.name) <> ''
                        THEN st.name
                    ELSE 'Staff'
                END AS display_position,
                CASE s.staff_type_id
                    WHEN 1 THEN 'teaching'
                    WHEN 2 THEN 'non-teaching'
                    WHEN 3 THEN 'admin'
                    ELSE NULL
                END as staff_type
            FROM staff s
            JOIN persons p ON p.id = s.person_id
            LEFT JOIN users u ON u.person_id = s.person_id
            LEFT JOIN roles r ON r.id = (
                SELECT ur2.role_id FROM user_roles ur2
                WHERE ur2.user_id = u.id
                ORDER BY ur2.id ASC LIMIT 1
            )
            LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
            LEFT JOIN departments d ON d.id = sda.department_id
            LEFT JOIN staff_types st ON s.staff_type_id = st.id
            LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
            WHERE s.id = ? AND s.data_scope = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, DataScopeService::current()]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function generateTemporaryPassword(): string
    {
        return 'Kwps-' . substr(bin2hex(random_bytes(4)), 0, 8) . '!';
    }

    /**
     * Materialise the operational artefacts a newly-created staff member needs, under the
     * normalized 3NF/4NF schema. Idempotent: safe to call repeatedly (each block guards on
     * existence). Three artefacts:
     *   1. staff_employment_profiles — the employment context row (department/position/contract).
     *   2. Onboarding = a workflow_instances header (reference_type='staff_onboarding',
     *      reference_id = staff.id) + onboarding_tasks seeded from onboarding_task_templates.
     *      The flat staff_onboarding_progress table is gone; progress is DERIVED from task
     *      statuses by vw_staff_onboarding_progress, never stored.
     *   3. emergency_contacts (person-keyed) — only when a distinct emergency contact was
     *      supplied. The staff's own email/phone already live on `persons`, so they are NOT
     *      mirrored here (that was the old staff_communication_profiles concern, now dropped).
     */
    private function ensureStaffOnboardingArtifacts(
        int $staffId,
        int $userId,
        array $staffInfo,
        array $data
    ): void {
        // Resolve identity/employment facts we need for both the profile and the task due dates.
        $ctx = $this->db->prepare('SELECT person_id, staff_type_id FROM staff WHERE id = ? LIMIT 1');
        $ctx->execute([$staffId]);
        $ctxRow = $ctx->fetch(PDO::FETCH_ASSOC) ?: [];
        $personId = (int)($ctxRow['person_id'] ?? 0) ?: null;
        $staffTypeId = (int)($ctxRow['staff_type_id'] ?? 0) ?: null;
        $employmentDate = $staffInfo['employment_date'] ?? date('Y-m-d');

        // 1. Employment context row (staff.department_id is dropped; membership lives here).
        $stmt = $this->db->prepare('SELECT 1 FROM staff_employment_profiles WHERE staff_id = ? LIMIT 1');
        $stmt->execute([$staffId]);
        if (!$stmt->fetchColumn()) {
            $this->db->prepare("
                INSERT INTO staff_employment_profiles
                    (staff_id, department_id, position, employment_date, contract_type, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ")->execute([
                $staffId,
                $staffInfo['department_id'] ?? null,
                $staffInfo['position'] ?? 'Staff',
                $employmentDate,
                $staffInfo['contract_type'] ?? 'permanent',
            ]);
        }

        // 2. Onboarding workflow instance + seeded tasks (skip if one already exists for this staff).
        $stmt = $this->db->prepare(
            "SELECT id FROM workflow_instances
             WHERE reference_type = 'staff_onboarding' AND reference_id = ? LIMIT 1"
        );
        $stmt->execute([$staffId]);
        $onboardingId = (int)$stmt->fetchColumn();

        if (!$onboardingId) {
            $workflowDefId = $this->resolveStaffOnboardingWorkflowId();
            $this->db->prepare("
                INSERT INTO workflow_instances
                    (workflow_id, reference_type, reference_id, current_stage, status, started_by, started_at, data_json)
                VALUES (?, 'staff_onboarding', ?, 'onboarding', 'in_progress', ?, NOW(), ?)
            ")->execute([
                $workflowDefId,
                $staffId,
                $userId,
                json_encode([
                    'staff_no'   => $staffInfo['staff_no'] ?? null,
                    'position'   => $staffInfo['position'] ?? null,
                    'invited_at' => date('Y-m-d H:i:s'),
                ]),
            ]);
            $onboardingId = (int)$this->db->lastInsertId();
            $this->seedOnboardingTasks($onboardingId, $staffTypeId, $employmentDate, $staffInfo['department_id'] ?? null);
        }

        // 3. Normalized personal profile facts supplied during onboarding.
        // These values do not belong on staff or persons directly; persist their
        // current temporal rows so a fully supplied staff form is genuinely complete.
        $address = trim((string)($data['address'] ?? $staffInfo['address'] ?? ''));
        if ($personId && $address !== '') {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM person_addresses
                 WHERE person_id = ? AND address_type = 'residential'
                   AND valid_to IS NULL LIMIT 1"
            );
            $stmt->execute([$personId]);
            if (!$stmt->fetchColumn()) {
                $this->db->prepare(
                    "INSERT INTO person_addresses
                        (person_id, address_type, address_line, is_primary, valid_from, created_at, updated_at)
                     VALUES (?, 'residential', ?, 1, ?, NOW(), NOW())"
                )->execute([$personId, $address, $employmentDate]);
            }
        }

        $maritalStatus = strtolower(trim((string)($data['marital_status'] ?? $staffInfo['marital_status'] ?? '')));
        $allowedMaritalStatuses = ['single', 'married', 'divorced', 'widowed', 'separated', 'unknown'];
        if ($personId && in_array($maritalStatus, $allowedMaritalStatuses, true)) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM person_marital_statuses
                 WHERE person_id = ? AND valid_to IS NULL LIMIT 1'
            );
            $stmt->execute([$personId]);
            if (!$stmt->fetchColumn()) {
                $this->db->prepare(
                    'INSERT INTO person_marital_statuses
                        (person_id, marital_status, valid_from, created_at)
                     VALUES (?, ?, ?, NOW())'
                )->execute([$personId, $maritalStatus, $employmentDate]);
            }
        }

        // 4. Emergency contact — only when a real third-party contact is supplied.
        $ecName  = $data['emergency_contact_name'] ?? null;
        $ecPhone = $data['emergency_contact_phone'] ?? null;
        if ($personId && ($ecName || $ecPhone)) {
            $exists = $this->db->prepare(
                'SELECT 1 FROM emergency_contacts WHERE person_id = ? AND name = ? LIMIT 1'
            );
            $exists->execute([$personId, (string)$ecName]);
            if (!$exists->fetchColumn()) {
                $this->db->prepare("
                    INSERT INTO emergency_contacts (id, person_id, name, phone, relationship, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ")->execute([
                    (int)$this->db->query('SELECT COALESCE(MAX(id), 0) + 1 FROM emergency_contacts')->fetchColumn(),
                    $personId,
                    $ecName ?: 'Emergency Contact',
                    $ecPhone,
                    $data['emergency_contact_relationship'] ?? null,
                ]);
            }
        }

        // 5. Individual attendance/work schedule supplied during onboarding.
        if (!empty($data['work_start_time']) && !empty($data['work_end_time'])) {
            $lateThreshold = max(0, (int)($data['late_threshold_minutes'] ?? 15));
            $this->db->prepare(
                'INSERT INTO staff_attendance_profiles
                    (staff_id, work_start_time, work_end_time, late_threshold_minutes, is_active)
                 VALUES (?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE
                    work_start_time = VALUES(work_start_time),
                    work_end_time = VALUES(work_end_time),
                    late_threshold_minutes = VALUES(late_threshold_minutes),
                    is_active = 1'
            )->execute([$staffId, $data['work_start_time'], $data['work_end_time'], $lateThreshold]);
        }
    }

    /**
     * Resolve the workflow_definitions.id for the 'staff_onboarding' workflow, creating a
     * minimal definition if none is seeded. workflow_instances.workflow_id is NOT NULL, so a
     * definition must exist before an onboarding instance can be written.
     */
    private function resolveStaffOnboardingWorkflowId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = 'staff_onboarding' LIMIT 1");
        $stmt->execute();
        $id = (int)$stmt->fetchColumn();
        if ($id) {
            return $id;
        }
        $this->db->prepare("
            INSERT INTO workflow_definitions (code, name, description, category, handler_class, is_active, created_at, updated_at)
            VALUES ('staff_onboarding', 'Staff Onboarding', 'New staff onboarding task checklist', 'staff_affairs', 'App\\\\API\\\\Modules\\\\staff\\\\OnboardingWorkflow', 1, NOW(), NOW())
        ")->execute();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Seed onboarding_tasks for a new onboarding instance from the active task templates.
     * Templates whose applies_to_type_ids is set are filtered to the staff's type; NULL/empty
     * applies-to means the template applies to everyone. Due dates are derived from the
     * employment date + the template's days_from_start.
     */
    private function seedOnboardingTasks(int $onboardingId, ?int $staffTypeId, string $employmentDate, ?int $departmentId): void
    {
        $tpl = $this->db->prepare("
            SELECT task_name, description, category, days_from_start, priority
            FROM onboarding_task_templates
            WHERE status = 'active'
              AND (
                    applies_to_type_ids IS NULL
                 OR applies_to_type_ids = ''
                 OR JSON_CONTAINS(applies_to_type_ids, JSON_ARRAY(?))
              )
            ORDER BY display_order, id
        ");
        $tpl->execute([$staffTypeId ?? 0]);
        $templates = $tpl->fetchAll(PDO::FETCH_ASSOC);
        if (!$templates) {
            return;
        }

        $insert = $this->db->prepare("
            INSERT INTO onboarding_tasks
                (onboarding_id, task_name, description, category, department_id, due_date, priority, sequence, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL ? DAY), ?, ?, 'pending', NOW(), NOW())
        ");
        $seq = 0;
        foreach ($templates as $t) {
            $insert->execute([
                $onboardingId,
                $t['task_name'],
                $t['description'] ?? null,
                $t['category'] ?? null,
                $departmentId,
                $employmentDate,
                (int)($t['days_from_start'] ?? 0),
                $t['priority'] ?? 'medium',
                ++$seq,
            ]);
        }
    }

    private function createStaffInvitation(int $userId, int $staffId, string $email): string
    {
        $this->db->prepare("
            UPDATE user_invitations
            SET status = 'revoked', revoked_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND status = 'pending'
        ")->execute([$userId]);

        $token = bin2hex(random_bytes(32));
        $actorId = (int)($this->user_id ?? 0);
        $this->db->prepare("
            INSERT INTO user_invitations
                (user_id, staff_id, email, token_hash, status, expires_at, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 72 HOUR), ?, NOW(), NOW())
        ")->execute([
            $userId,
            $staffId,
            strtolower($email),
            hash('sha256', $token),
            $actorId,
        ]);

        return $token;
    }

    private function queueStaffInvitationEmail(
        int $userId,
        string $email,
        string $name,
        string $username,
        string $temporaryPassword,
        string $token
    ): void {
        $setupUrl = $this->staffSetupUrl($token);
        $loginUrl = $this->appBaseUrl() . '/index.php';
        $payload = [
            'name' => $name,
            'username' => $username,
            'default_password' => $temporaryPassword,
            'temporary_password' => $temporaryPassword,
            'activation_url' => $setupUrl,
            'setup_url' => $setupUrl,
            'login_url' => $loginUrl,
            'expires_hours' => 72,
        ];

        $this->db->prepare("
            INSERT INTO outbound_messages
                (user_id, channel, recipient, template_key, subject, payload_json, status, attempts, next_attempt_at, created_at, updated_at)
            VALUES (?, 'email', ?, 'staff_account_invitation', 'Your Kingsway staff account is ready', ?, 'queued', 0, NOW(), NOW(), NOW())
        ")->execute([
            $userId,
            strtolower($email),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function staffSetupUrl(string $token): string
    {
        return $this->appBaseUrl() . '/reset_default_password.php?token=' . rawurlencode($token);
    }

    private function appBaseUrl(): string
    {
        if (defined('BASE_URL')) {
            return rtrim(BASE_URL, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . $appBase;
    }

    // ===============================================================
    // CONTRACT MANAGEMENT
    // ===============================================================

    public function listContracts($filters = [])
    {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['staff_id'])) {
                $where[] = 'sc.staff_id = ?';
                $params[] = $filters['staff_id'];
            }
            if (!empty($filters['status'])) {
                $where[] = 'sc.status = ?';
                $params[] = $filters['status'];
            }
            if (!empty($filters['start_date'])) {
                $where[] = 'sc.start_date >= ?';
                $params[] = $filters['start_date'];
            }
            if (!empty($filters['end_date'])) {
                $where[] = '(sc.end_date <= ? OR sc.end_date IS NULL)';
                $params[] = $filters['end_date'];
            }

            $sql = "
                SELECT
                    sc.*,
                    s.staff_no,
                    p.first_name,
                    p.last_name,
                    d.name as department_name
                FROM staff_contracts sc
                JOIN staff s ON sc.staff_id = s.id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                LEFT JOIN departments d ON d.id = sda.department_id
            ";

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= ' ORDER BY sc.start_date DESC, sc.created_at DESC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'contracts' => $contracts,
                    'count' => count($contracts)
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getContract($contractId)
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM staff_contracts WHERE id = ?');
            $stmt->execute([$contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contract) {
                return $this->response(['status' => 'error', 'message' => 'Contract not found'], 404);
            }

            return $this->response(['status' => 'success', 'data' => $contract]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createContract($data)
    {
        try {
            $required = ['staff_id', 'contract_type', 'start_date', 'salary'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO staff_contracts
                    (staff_id, contract_type, start_date, end_date, salary, allowances, terms, contract_document_url, status, created_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['contract_type'],
                $data['start_date'],
                $data['end_date'] ?? null,
                $data['salary'],
                $data['allowances'] ?? 0,
                $data['terms'] ?? null,
                $data['contract_document_url'] ?? null,
                $data['status'] ?? 'active',
                $data['created_by'] ?? null
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Contract created successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateContract($id, $data)
    {
        try {
            $updates = [];
            $params = [];
            $allowed = [
                'contract_type',
                'start_date',
                'end_date',
                'salary',
                'allowances',
                'terms',
                'contract_document_url',
                'status',
                'termination_reason'
            ];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return $this->response(['status' => 'error', 'message' => 'No fields to update'], 400);
            }

            $params[] = $id;
            $sql = 'UPDATE staff_contracts SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->response([
                'status' => 'success',
                'message' => 'Contract updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ===============================================================
    // PAYROLL LISTING (SUMMARY VIEW FOR HR/FINANCE)
    // ===============================================================

    public function listPayroll($filters = [])
    {
        try {
            $month = (int) ($filters['month'] ?? date('n'));
            $year = (int) ($filters['year'] ?? date('Y'));
            $period = $filters['payroll_period'] ?? sprintf('%04d-%02d', $year, $month);

            // Reads the shipped vw_payslip_detailed (flattens payslips/payslip_items/payroll_runs
            // back to the legacy per-staff payroll row shape). payroll_period is derived in the view
            // as YYYY-MM, so it filters directly.
            $sql = "
                SELECT *
                FROM vw_scoped_payslip_detailed
                WHERE payroll_period = ? AND data_scope = ?
                ORDER BY staff_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$period, DataScopeService::current()]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'payroll' => $rows,
                    'period' => $period,
                    'count' => count($rows)
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPayrollSummary($filters = [])
    {
        try {
            $month = (int) ($filters['month'] ?? date('n'));
            $year = (int) ($filters['year'] ?? date('Y'));
            $period = $filters['payroll_period'] ?? sprintf('%04d-%02d', $year, $month);

            $sql = "
                SELECT
                    COUNT(*) as total_records,
                    COALESCE(SUM(gross_salary), 0) as gross_payroll,
                    COALESCE(SUM(total_deductions), 0) as total_deductions,
                    COALESCE(SUM(net_salary), 0) as net_payroll,
                    SUM(CASE WHEN payment_status <> 'paid' THEN 1 ELSE 0 END) as pending_approval
                FROM vw_scoped_payslip_detailed
                WHERE payroll_period = ? AND data_scope = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$period, DataScopeService::current()]);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'period' => $period,
                    'summary' => $summary
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ===============================================================
    // PAYROLL OPERATIONS (using StaffPayrollManager)
    // ===============================================================

    /**
     * View staff payslip details
     */
    public function viewPayslip($staffId, $month, $year) {
        try {
            $result = $this->service->getPayrollManager()->viewPayslip($staffId, $month, $year);
            return formatResponse(true, $result, 'Payslip retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get staff payroll history
     */
    public function getPayrollHistory($staffId, array $filters = []) {
        try {
            return $this->service->getPayrollManager()->getPayrollHistory(
                (int) $staffId,
                $filters
            );
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * View staff allowances
     */
    public function viewAllowances($staffId) {
        try {
            $result = $this->service->getPayrollManager()->viewAllowances($staffId);
            return formatResponse(true, $result, 'Allowances retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * View staff deductions
     */
    public function viewDeductions($staffId) {
        try {
            $result = $this->service->getPayrollManager()->viewDeductions($staffId);
            return formatResponse(true, $result, 'Deductions retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get loan details
     */
    public function getLoanDetails($staffId, $loanId = null) {
        try {
            $result = $this->service->getPayrollManager()->getLoanDetails($staffId, $loanId);
            return formatResponse(true, $result, 'Loan details retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Request salary advance
     */
    public function requestAdvance($staffId, $userId, $data) {
        try {
            $result = $this->service->getPayrollManager()->requestAdvance($staffId, $userId, $data);
            return formatResponse(true, $result, 'Advance request submitted successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Apply for loan
     */
    public function applyForLoan($staffId, $userId, $data) {
        try {
            $result = $this->service->getPayrollManager()->applyForLoan($staffId, $userId, $data);
            return formatResponse(true, $result, 'Loan application submitted successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Download P9 form
     */
    public function downloadP9Form($staffId, $year) {
        try {
            $result = $this->service->getPayrollManager()->downloadP9Form($staffId, $year);
            return formatResponse(true, $result, 'P9 form generated successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Download payslip PDF
     */
    public function downloadPayslip($staffId, $month, $year) {
        try {
            $result = $this->service->getPayrollManager()->downloadPayslip($staffId, $month, $year);
            return formatResponse(true, $result, 'Payslip downloaded successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Export payroll history to Excel
     */
    public function exportPayrollHistory($staffId, $startDate = null, $endDate = null) {
        try {
            $result = $this->service->getPayrollManager()->exportPayrollHistory($staffId, $startDate, $endDate);
            return formatResponse(true, $result, 'Payroll history exported successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // STAFF CHILDREN OPERATIONS (Child Fee Deductions from Payroll)
    // ===============================================================

    /**
     * Get staff children (students enrolled in school)
     */
    public function getStaffChildren($staffId)
    {
        try {
            $result = $this->service->getPayrollManager()->getStaffChildren($staffId);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Add a child to staff member
     */
    public function addStaffChild($staffId, $data)
    {
        try {
            $result = $this->service->getPayrollManager()->addStaffChild($staffId, $data);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Update staff child settings
     */
    public function updateStaffChild($staffId, $childId, $data)
    {
        try {
            $result = $this->service->getPayrollManager()->updateStaffChild($staffId, $childId, $data);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Remove staff child link
     */
    public function removeStaffChild($staffId, $childId)
    {
        try {
            $result = $this->service->getPayrollManager()->removeStaffChild($staffId, $childId);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get child fee configuration
     */
    public function getChildFeeConfig()
    {
        try {
            $result = $this->service->getPayrollManager()->getChildFeeConfig();
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Calculate child fee deductions for staff
     */
    public function calculateChildFeeDeductions($staffId, $month, $year)
    {
        try {
            $result = $this->service->getPayrollManager()->calculateChildFeeDeductions($staffId, $month, $year);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Generate detailed payslip with all breakdowns
     */
    public function generateDetailedPayslip($staffId, $month, $year, $generatedBy = null)
    {
        try {
            $result = $this->service->getPayrollManager()->generateDetailedPayslip($staffId, $month, $year, $generatedBy);
            return $result;
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // PERFORMANCE OPERATIONS (using StaffPerformanceManager)
    // ===============================================================

    /**
     * Get staff performance review history
     */
    public function getReviewHistory($staffId) {
        try {
            $result = $this->service->getPerformanceManager()->getReviewHistory($staffId);
            return formatResponse(true, $result, 'Review history retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport($reviewId) {
        try {
            $result = $this->service->getPerformanceManager()->generatePerformanceReport($reviewId);
            return formatResponse(true, $result, 'Performance report generated successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get academic KPI summary
     */
    public function getAcademicKPISummary($staffId, $academicYearId = null) {
        try {
            $result = $this->service->getPerformanceManager()->getAcademicKPISummary($staffId, $academicYearId);
            return formatResponse(true, $result, 'Academic KPI summary retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // ASSIGNMENT OPERATIONS (using StaffAssignmentManager)
    // ===============================================================

    /**
     * Get staff assignments
     */
    public function getStaffAssignments($staffId, $academicYearId = null, $includeHistory = false) {
        try {
            $filters = ['staff_id' => $staffId];
            if ($academicYearId) { $filters['academic_year_id'] = $academicYearId; }
            $result = $this->service->getAssignmentManager()->getStaffAssignments($filters);
            return formatResponse(true, $result, 'Assignments retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get staff workload summary
     */
    public function getStaffWorkload($staffId, $academicYearId = null) {
        try {
            $result = $this->service->getAssignmentManager()->getStaffWorkload($staffId, $academicYearId);
            return formatResponse(true, $result, 'Workload summary retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Get current staff assignments
     */
    public function getCurrentAssignments($staffId) {
        try {
            $result = $this->service->getAssignmentManager()->getCurrentAssignments(['staff_id' => $staffId]);
            return formatResponse(true, $result, 'Current assignments retrieved successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // WORKFLOW OPERATIONS
    // ===============================================================

    /**
     * Initiate leave request workflow
     */
    public function initiateLeaveRequest($staffId, $userId, $data) {
        try {
            $result = $this->service->getLeaveWorkflow()->initiateLeaveRequest($staffId, $userId, $data);
            return formatResponse(true, $result, 'Leave request submitted successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Initiate assignment workflow
     */
    public function initiateAssignment($staffId, $classStreamId, $academicYearId, $userId, $data) {
        try {
            $result = $this->service->getAssignmentWorkflow()->initiateAssignment(
                $staffId, $classStreamId, $academicYearId, $userId, $data
            );
            return formatResponse(true, $result, 'Assignment request submitted successfully');
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    // ===============================================================
    // STAFF SELF-SERVICE: INTERNAL OPPORTUNITIES AND INCIDENTS
    // ===============================================================

    public function listInternalOpportunities($staffId)
    {
        $stmt = $this->db->prepare(
            "SELECT j.id, j.title, d.name AS department, j.job_type, j.location,
                    j.description, j.requirements, j.responsibilities,
                    j.deadline, j.status,
                    a.id AS application_id,
                    a.status AS application_status,
                    a.created_at AS applied_at
             FROM job_vacancies j
             LEFT JOIN departments d ON d.id = j.department_id
             LEFT JOIN job_applications a
                    ON a.job_id = j.id
                   AND a.applicant_type = 'internal'
                   AND a.staff_id = ?
             WHERE j.status = 'open'
               AND j.deadline >= CURDATE()
             ORDER BY j.deadline, j.title"
        );
        $stmt->execute([(int) $staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function applyForInternalOpportunity($staffId, $userId, array $data)
    {
        $jobId = (int) ($data['job_id'] ?? 0);
        if ($jobId <= 0) {
            throw new \RuntimeException('job_id is required', 422);
        }

        try {
            $this->db->beginTransaction();

            $jobStmt = $this->db->prepare(
                "SELECT * FROM job_vacancies
                 WHERE id = ? AND status = 'open' AND deadline >= CURDATE()
                 FOR UPDATE"
            );
            $jobStmt->execute([$jobId]);
            $job = $jobStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$job) {
                throw new \RuntimeException('This internal opportunity is no longer open', 409);
            }

            $profileStmt = $this->db->prepare(
                "SELECT s.id, p.first_name, p.last_name, p.phone,
                        s.position, sda.department_id, p.email
                 FROM staff s
                 INNER JOIN persons p ON p.id = s.person_id
                 LEFT JOIN staff_department_assignments sda
                        ON sda.staff_id = s.id
                       AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
                 WHERE s.id = ? AND s.status IN ('active', 'on_leave')
                 LIMIT 1"
            );
            $profileStmt->execute([(int) $staffId]);
            $profile = $profileStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$profile) {
                throw new \RuntimeException('Staff profile is unavailable', 403);
            }

            $existingStmt = $this->db->prepare(
                "SELECT id FROM job_applications
                 WHERE job_id = ?
                   AND applicant_type = 'internal'
                   AND staff_id = ?
                 LIMIT 1"
            );
            $existingStmt->execute([$jobId, (int) $staffId]);
            if ($existingStmt->fetchColumn()) {
                throw new \RuntimeException('You have already applied for this opportunity', 409);
            }

            $statement = trim((string) ($data['cover_letter'] ?? $data['statement'] ?? ''));
            $insertStmt = $this->db->prepare(
                "INSERT INTO job_applications
                    (job_id, job_title, first_name, last_name, email, phone,
                     tsc_number, cover_letter, status, applicant_type, staff_id,
                     current_position, current_department_id, ip_address,
                     created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'received', 'internal', ?, ?, ?, ?, NOW(), NOW())"
            );
            $insertStmt->execute([
                $jobId,
                $job['title'],
                $profile['first_name'],
                $profile['last_name'],
                $profile['email'],
                $profile['phone'] ?: 'Not provided',
                null, // tsc_number: TSC no. dropped from staff in the normalized schema

                $statement !== ''
                    ? $statement
                    : 'Internal application submitted through staff self-service.',
                (int) $staffId,
                $profile['position'],
                $profile['department_id'],
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $applicationId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return [
                'id' => $applicationId,
                'job_id' => $jobId,
                'status' => 'received',
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function listIncidentReports($staffId)
    {
        $stmt = $this->db->prepare(
            "SELECT id, reference_no, category, occurred_at, location,
                    severity, status, resolution, resolved_at, created_at
             FROM staff_incident_reports
             WHERE staff_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT 50"
        );
        $stmt->execute([(int) $staffId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createIncidentReport($staffId, $userId, array $data)
    {
        $category = (string) ($data['category'] ?? '');
        $severity = (string) ($data['severity'] ?? 'medium');
        $occurredAt = trim((string) ($data['occurred_at'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $allowedCategories = [
            'workplace_accident', 'property_damage', 'safety_hazard',
            'security_concern', 'harassment', 'maintenance',
            'student_welfare', 'transport', 'kitchen', 'other',
        ];

        if (!in_array($category, $allowedCategories, true)) {
            throw new \RuntimeException('Invalid incident category', 422);
        }
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            throw new \RuntimeException('Invalid incident severity', 422);
        }
        if ($occurredAt === '' || strtotime($occurredAt) === false
            || $location === '' || $description === '') {
            throw new \RuntimeException(
                'Incident date, location and description are required',
                422
            );
        }

        $profileStmt = $this->db->prepare(
            'SELECT department_id FROM staff WHERE id = ? LIMIT 1'
        );
        $profileStmt->execute([(int) $staffId]);
        $profile = $profileStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$profile) {
            throw new \RuntimeException('Staff profile is unavailable', 403);
        }

        try {
            $this->db->beginTransaction();
            $reference = $this->nextStaffIncidentReference();
            $stmt = $this->db->prepare(
                "INSERT INTO staff_incident_reports
                    (reference_no, staff_id, department_id, category,
                     occurred_at, location, description, immediate_action,
                     severity, status, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'reported', ?, NOW(), NOW())"
            );
            $stmt->execute([
                $reference,
                (int) $staffId,
                $profile['department_id'],
                $category,
                date('Y-m-d H:i:s', strtotime($occurredAt)),
                $location,
                $description,
                trim((string) ($data['immediate_action'] ?? '')) ?: null,
                $severity,
                (int) $userId,
            ]);
            $incidentId = (int) $this->db->lastInsertId();
            $this->db->commit();
            return [
                'id' => $incidentId,
                'reference_no' => $reference,
                'status' => 'reported',
            ];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private function nextStaffIncidentReference()
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $reference = sprintf(
                'KSI-%s-%06d',
                date('Ymd'),
                random_int(0, 999999)
            );
            $stmt = $this->db->prepare(
                'SELECT 1 FROM staff_incident_reports WHERE reference_no = ? LIMIT 1'
            );
            $stmt->execute([$reference]);
            if (!$stmt->fetchColumn()) {
                return $reference;
            }
        }
        throw new \RuntimeException('Unable to generate incident reference', 500);
    }

}
     
