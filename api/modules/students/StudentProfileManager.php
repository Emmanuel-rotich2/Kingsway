<?php

namespace App\API\Modules\students;

use App\API\Includes\BaseAPI;
use PDO;
use PDOStatement;
use Exception;

/**
 * StudentProfileManager - owns all student enrichment SQL (counseling, health,
 * catering/boarding, transport, welfare) against the live normalised schema.
 *
 * Legacy refs fixed:
 *   - students.first_name/middle_name/last_name/gender -> persons (s.person_id)
 *   - users.first_name/last_name                       -> persons (u.person_id)
 *   - class_streams (class_id, stream_name)            -> academic_year_class_streams
 *                                                         -> academic_year_classes
 *                                                         -> classes / streams
 *   - students.stream_id                               -> active enrollment's aycs.stream_id
 *   - academic_terms                                   -> terms
 *   - parents.phone_1 / email / names                  -> persons (parents.person_id)
 *   - dormitory_assignments.student_id                 -> student_academic_enrollment_id
 *   - dormitory_assignments.updated_at                 -> dropped (column does not exist)
 *   - student_transport_assignments.vehicle_id         -> via transport_vehicle_routes
 *   - transport_vehicles.driver_id vs users.id         -> resolve staff via users.person_id
 *   - student_transport_incidents.notes                -> dropped (column does not exist)
 *   - student_welfare_cases.escalated/to/at            -> dropped (columns do not exist)
 *   - student_welfare_notes.recorded_at                -> dropped (created_at default)
 *   - student_welfare_notes note_type enums            -> mapped to live enum
 *   - counseling_sessions.counseling_case_id   -> case_id; notes -> summary;
 *                                                         created_by -> recorded_by
 *   - student_permissions.exeat_type                   -> permission_type name
 *   - student_permissions.status 'out'                 -> 'approved'
 *   - meal_plans meal_type 'supper'                    -> 'dinner'
 */
class StudentProfileManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('students');
    }

    private function allRows(PDOStatement $stmt)
    {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function firstRow(PDOStatement $stmt)
    {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private function fetch(PDOStatement $stmt)
    {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchColumnInt(PDOStatement $stmt)
    {
        $value = $stmt->fetchColumn();
        return $value === false ? 0 : (int) $value;
    }

    /**
     * Resolve the authenticated user to student id(s).
     */
    public function resolveStudentIds(array $user): array
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
        if ($username !== '') {
            $stmt = $this->dbQuery(
                "SELECT id FROM students WHERE admission_no = ? LIMIT 1",
                [$username]
            );
            $studentId = $stmt->fetchColumn();
            if ($studentId) {
                return [(int) $studentId];
            }
        }

        return [];
    }

    /**
     * Resolve the authenticated user to parent id(s).
     */
    public function resolveParentIds(array $user): array
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
            $conditions[] = 'LOWER(pg.email) = ?';
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
            $conditions[] = '(pg.phone = ?)';
            $bindings[] = $phone;
        }

        if (empty($conditions)) {
            $firstName = strtolower(trim((string) ($user['first_name'] ?? '')));
            $lastName = strtolower(trim((string) ($user['last_name'] ?? '')));

            if ($firstName !== '' && $lastName !== '') {
                $conditions[] = '(LOWER(pg.first_name) = ? AND LOWER(pg.last_name) = ?)';
                $bindings[] = $firstName;
                $bindings[] = $lastName;
            }
        }

        if (empty($conditions)) {
            return [];
        }

        $sql = "SELECT DISTINCT p.id
                FROM parents p
                INNER JOIN persons pg ON pg.id = p.person_id
                WHERE " . implode(' OR ', array_map(static fn($condition) => "({$condition})", $conditions)) . "
                ORDER BY p.id ASC";

        $stmt = $this->dbQuery($sql, $bindings);
        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    private function staffList()
    {
        $stmt = $this->db->query(
            "SELECT u.id, CONCAT_WS(' ', p.first_name, p.last_name) AS full_name
             FROM users u
             INNER JOIN persons p ON p.id = u.person_id
             WHERE u.status = 'active'
             ORDER BY full_name ASC"
        );
        return $this->allRows($stmt);
    }

    private function studentList()
    {
        $stmt = $this->db->query(
            "SELECT s.id, s.admission_no, CONCAT_WS(' ', p.first_name, p.last_name) AS full_name
             FROM students s
             INNER JOIN persons p ON p.id = s.person_id
             WHERE s.status = 'active'
             ORDER BY full_name ASC"
        );
        return $this->allRows($stmt);
    }

    private function currentStreams()
    {
        $stmt = $this->db->query(
            "SELECT aycs.id, ayc.class_id, st.name AS stream_name
             FROM academic_year_class_streams aycs
             INNER JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             INNER JOIN streams st ON st.id = aycs.stream_id
             INNER JOIN academic_years ay ON ay.id = ayc.academic_year_id AND ay.is_current = 1
             ORDER BY st.name ASC"
        );
        return $this->allRows($stmt);
    }

    private function classStreamJoins(string $alias)
    {
        return "LEFT JOIN student_academic_enrollments sae_{$alias}
                     ON sae_{$alias}.student_id = s.id AND sae_{$alias}.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs_{$alias} ON aycs_{$alias}.id = sae_{$alias}.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc_{$alias} ON ayc_{$alias}.id = aycs_{$alias}.academic_year_class_id
                LEFT JOIN classes cls_{$alias} ON cls_{$alias}.id = ayc_{$alias}.class_id
                LEFT JOIN streams st_{$alias} ON st_{$alias}.id = aycs_{$alias}.stream_id";
    }

    private function studentBase()
    {
        return "FROM students s
                INNER JOIN persons p ON p.id = s.person_id
                INNER JOIN student_academic_enrollments sae
                     ON sae.student_id = s.id AND sae.enrollment_status = 'active'";
    }

    public function getCounselingMeta()
    {
        try {
            $years = $this->allRows($this->db->query(
                "SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"
            ));
            $terms = $this->allRows($this->db->query("SELECT id, name FROM terms ORDER BY id ASC"));

            return $this->successResponse([
                'academic_years' => $years,
                'terms' => $terms,
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'staff' => $this->staffList(),
                'students' => $this->studentList(),
                'case_types' => ['academic', 'behavioral', 'personal', 'family', 'career', 'disciplinary', 'other'],
                'priorities' => ['low', 'medium', 'high', 'urgent'],
                'statuses' => ['open', 'in_progress', 'resolved', 'closed', 'cancelled'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCounselingMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCounselingCases(array $filters, array $user)
    {
        try {
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $caseType = !empty($filters['case_type']) ? trim($filters['case_type']) : null;
            $priority = !empty($filters['priority']) ? trim($filters['priority']) : null;
            $status = !empty($filters['status']) ? trim($filters['status']) : null;
            $gender = !empty($filters['gender']) ? trim($filters['gender']) : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        c.id,
                        c.case_code,
                        c.title,
                        c.case_type,
                        c.priority,
                        c.status,
                        c.referral_source,
                        c.opened_at,
                        c.next_follow_up_at,
                        s.id AS student_id,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                        p.gender,
                        ayc_c.class_id AS class_id,
                        cls_c.name AS class_name,
                        st_c.name AS stream_name,
                        CONCAT_WS(' ', up.first_name, up.last_name) AS counselor_name
                    FROM counseling_cases c
                    INNER JOIN students s ON s.id = c.student_id
                    INNER JOIN persons p ON p.id = s.person_id
                    " . $this->classStreamJoins('c') . "
                    LEFT JOIN users u ON u.id = c.assigned_to
                    LEFT JOIN persons up ON up.id = u.person_id
                    WHERE c.status != 'cancelled'";

            $bindings = [];

            if (($user['role'] ?? '') === 'chaplain' && !empty($user['id'])) {
                $sql .= " AND c.assigned_to = ?";
                $bindings[] = (int) $user['id'];
            }

            if ($classId) {
                $sql .= " AND ayc_c.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs_c.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($caseType) {
                $sql .= " AND c.case_type = ?";
                $bindings[] = $caseType;
            }

            if ($priority) {
                $sql .= " AND c.priority = ?";
                $bindings[] = $priority;
            }

            if ($status) {
                $sql .= " AND c.status = ?";
                $bindings[] = $status;
            }

            if ($gender) {
                $sql .= " AND p.gender = ?";
                $bindings[] = $gender;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                             OR c.title LIKE ? OR CONCAT_WS(' ', up.first_name, up.last_name) LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY c.opened_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $cases = $this->allRows($stmt);

            $sessionStmt = $this->db->prepare(
                "SELECT MAX(session_date) AS last_session
                 FROM counseling_sessions
                 WHERE case_id = ?"
            );
            foreach ($cases as &$case) {
                $sessionStmt->execute([$case['id']]);
                $sessionData = $this->fetch($sessionStmt);
                $case['last_session'] = $sessionData['last_session'] ?? null;
            }
            unset($case);

            return $this->successResponse($cases);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCounselingCases');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCounselingCase(int $caseId, string $userRole = '')
    {
        try {
            $caseStmt = $this->db->prepare(
                "SELECT
                    c.*,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                    p.gender,
                    ayc_c.class_id AS class_id,
                    cls_c.name AS class_name,
                    st_c.name AS stream_name,
                    CONCAT_WS(' ', up.first_name, up.last_name) AS counselor_name
                 FROM counseling_cases c
                 INNER JOIN students s ON s.id = c.student_id
                 INNER JOIN persons p ON p.id = s.person_id
                 " . $this->classStreamJoins('c') . "
                 LEFT JOIN users u ON u.id = c.assigned_to
                 LEFT JOIN persons up ON up.id = u.person_id
                 WHERE c.id = ?"
            );
            $caseStmt->execute([$caseId]);
            $case = $this->fetch($caseStmt);

            if (!$case) {
                return $this->errorResponse('Case not found', 404);
            }

            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, s.status, s.blood_group,
                        p.first_name, p.middle_name, p.last_name, p.gender
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$case['student_id']]);
            $student = $this->fetch($studentStmt);

            $sessionsStmt = $this->db->prepare(
                "SELECT * FROM counseling_sessions
                 WHERE case_id = ?
                 ORDER BY session_date DESC"
            );
            $sessionsStmt->execute([$caseId]);
            $sessions = $this->allRows($sessionsStmt);

            if (!in_array($userRole, ['counselor', 'chaplain', 'headteacher', 'admin'])) {
                foreach ($sessions as &$session) {
                    unset($session['confidential_notes']);
                }
                unset($session);
            }

            return $this->successResponse([
                'case' => $case,
                'student' => $student,
                'sessions' => $sessions,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCounselingCase');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getHealthMeta()
    {
        try {
            return $this->successResponse([
                'academic_years' => $this->allRows($this->db->query(
                    "SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"
                )),
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'health_categories' => ['general', 'allergy', 'condition', 'medication', 'injury', 'incident', 'other'],
                'severities' => ['low', 'medium', 'high', 'critical'],
                'statuses' => ['active', 'inactive', 'resolved', 'monitoring'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getHealthMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getHealthRecords(array $filters, string $userRole = '')
    {
        try {
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $healthCategory = !empty($filters['health_category']) ? trim($filters['health_category']) : null;
            $alertStatus = !empty($filters['alert_status']) ? trim($filters['alert_status']) : null;
            $severity = !empty($filters['severity']) ? trim($filters['severity']) : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        h.id,
                        h.record_code,
                        h.health_category,
                        h.alert_type,
                        h.condition_name,
                        h.allergy_name,
                        h.medication_name,
                        h.severity,
                        h.status,
                        h.emergency_flag,
                        h.description,
                        h.action_instructions,
                        h.next_review_date,
                        h.emergency_contact_name,
                        h.emergency_contact_phone,
                        s.id AS student_id,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                        p.gender,
                        ayc_h.class_id AS class_id,
                        cls_h.name AS class_name,
                        st_h.name AS stream_name
                    FROM student_health_records h
                    INNER JOIN students s ON s.id = h.student_id
                    INNER JOIN persons p ON p.id = s.person_id
                    " . $this->classStreamJoins('h') . "
                    WHERE 1=1";

            $bindings = [];

            if ($classId) {
                $sql .= " AND ayc_h.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs_h.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($healthCategory) {
                $sql .= " AND h.health_category = ?";
                $bindings[] = $healthCategory;
            }

            if ($alertStatus) {
                $sql .= " AND h.status = ?";
                $bindings[] = $alertStatus;
            }

            if ($severity) {
                $sql .= " AND h.severity = ?";
                $bindings[] = $severity;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                             OR h.condition_name LIKE ? OR h.allergy_name LIKE ? OR h.medication_name LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY h.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $records = $this->allRows($stmt);

            $visitStmt = $this->db->prepare(
                "SELECT COUNT(*) AS visits_count, MAX(visit_date) AS last_visit
                 FROM student_health_visits
                 WHERE student_id = ?"
            );
            foreach ($records as &$record) {
                $visitStmt->execute([$record['student_id']]);
                $visitData = $this->fetch($visitStmt);
                $record['clinic_visits_count'] = $visitData['visits_count'] ?? 0;
                $record['last_visit'] = $visitData['last_visit'] ?? null;
            }
            unset($record);

            if (!in_array($userRole, ['headteacher', 'director', 'admin', 'nurse'])) {
                foreach ($records as &$record) {
                    unset($record['sensitive_notes']);
                }
                unset($record);
            }

            return $this->successResponse($records);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getHealthRecords');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getHealthRecord(int $recordId, string $userRole = '')
    {
        try {
            $recordStmt = $this->db->prepare(
                "SELECT
                    h.*,
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                    p.gender,
                    s.blood_group,
                    ayc_h.class_id AS class_id,
                    cls_h.name AS class_name,
                    st_h.name AS stream_name
                 FROM student_health_records h
                 INNER JOIN students s ON s.id = h.student_id
                 INNER JOIN persons p ON p.id = s.person_id
                 " . $this->classStreamJoins('h') . "
                 WHERE h.id = ?"
            );
            $recordStmt->execute([$recordId]);
            $record = $this->fetch($recordStmt);

            if (!$record) {
                return $this->errorResponse('Health record not found', 404);
            }

            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, s.status, s.blood_group,
                        p.first_name, p.middle_name, p.last_name, p.gender,
                        h.allergies, h.chronic_conditions
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_health_records h ON h.student_id = s.id AND h.id = ?
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$recordId, $record['student_id']]);
            $student = $this->fetch($studentStmt);

            $visitsStmt = $this->db->prepare(
                "SELECT * FROM student_health_visits
                 WHERE student_id = ?
                 ORDER BY visit_date DESC"
            );
            $visitsStmt->execute([$record['student_id']]);
            $visits = $this->allRows($visitsStmt);

            $reviewsStmt = $this->db->prepare(
                "SELECT * FROM student_health_reviews
                 WHERE health_record_id = ?
                 ORDER BY review_date DESC"
            );
            $reviewsStmt->execute([$recordId]);
            $reviews = $this->allRows($reviewsStmt);

            if (!in_array($userRole, ['headteacher', 'director', 'admin', 'nurse'])) {
                unset($record['sensitive_notes']);
            }

            return $this->successResponse([
                'record' => $record,
                'student' => $student,
                'visits' => $visits,
                'reviews' => $reviews,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getHealthRecord');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCateringBoardingMeta()
    {
        try {
            return $this->successResponse([
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'dormitories' => $this->allRows($this->db->query(
                    "SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC"
                )),
                'diet_types' => ['normal', 'vegetarian', 'diabetic', 'allergy', 'medical', 'religious', 'other'],
                'meal_types' => ['breakfast', 'lunch', 'supper', 'snack'],
                'boarding_statuses' => ['active', 'on_leave', 'sick', 'suspended', 'checked_out'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCateringBoardingMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCateringBoardingStudents(array $filters)
    {
        try {
            $date = !empty($filters['date']) ? trim($filters['date']) : date('Y-m-d');
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $gender = !empty($filters['gender']) ? trim($filters['gender']) : null;
            $dormitoryId = !empty($filters['dormitory_id']) ? (int) $filters['dormitory_id'] : null;
            $dietType = !empty($filters['diet_type']) ? trim($filters['diet_type']) : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        s.id AS student_id,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                        p.gender,
                        ayc.class_id,
                        cls.name AS class_name,
                        st.name AS stream_name,
                        d.id AS dormitory_id,
                        d.name AS dormitory_name,
                        da.status AS boarding_status,
                        smp.diet_type,
                        (smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL) AS has_food_restriction
                    " . $this->studentBase() . "
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes cls ON cls.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN dormitory_assignments da
                         ON da.student_academic_enrollment_id = sae.id
                         AND da.status = 'active'
                         AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                    LEFT JOIN dormitories d ON d.id = da.dormitory_id
                    LEFT JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                    WHERE da.id IS NOT NULL";

            $bindings = [];

            if ($classId) {
                $sql .= " AND ayc.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND p.gender = ?";
                $bindings[] = $gender;
            }

            if ($dormitoryId) {
                $sql .= " AND d.id = ?";
                $bindings[] = $dormitoryId;
            }

            if ($dietType) {
                $sql .= " AND smp.diet_type = ?";
                $bindings[] = $dietType;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term);
            }

            $sql .= " ORDER BY p.first_name, p.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $students = $this->allRows($stmt);

            $mealStatusStmt = $this->db->prepare(
                "SELECT meal_type, status
                 FROM catering_meal_statuses
                 WHERE student_id = ? AND meal_date = ?"
            );
            $attendanceStmt = $this->db->prepare(
                "SELECT status
                 FROM boarding_attendance
                 WHERE student_id = ? AND date = ?
                 ORDER BY session_id ASC LIMIT 1"
            );

            foreach ($students as &$student) {
                $student['breakfast'] = true;
                $student['lunch'] = true;
                $student['supper'] = true;
                $student['meal_status_today'] = 'eating';

                $mealStatusStmt->execute([$student['student_id'], $date]);
                $mealStatuses = $mealStatusStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($mealStatuses as $ms) {
                    if ($ms['meal_type'] === 'breakfast') {
                        $student['breakfast'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    } elseif ($ms['meal_type'] === 'lunch') {
                        $student['lunch'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    } elseif ($ms['meal_type'] === 'supper') {
                        $student['supper'] = $ms['status'] === 'eating';
                        $student['meal_status_today'] = $ms['status'];
                    }
                }

                $attendanceStmt->execute([$student['student_id'], $date]);
                $attendance = $this->fetch($attendanceStmt);

                if ($attendance) {
                    if ($attendance['status'] === 'sick_bay') {
                        $student['meal_status_today'] = 'sick_meal';
                    } elseif ($attendance['status'] === 'absent' || $attendance['status'] === 'permission') {
                        $student['meal_status_today'] = 'on_leave';
                    }
                }
            }
            unset($student);

            return $this->successResponse($students);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCateringBoardingStudents');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCateringBoardingSummary(array $filters)
    {
        try {
            $date = !empty($filters['date']) ? trim($filters['date']) : date('Y-m-d');

            $totalBoarders = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS total
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())"
            ));

            $notEatingStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 LEFT JOIN catering_meal_statuses cms
                      ON cms.student_id = s.id AND cms.meal_date = ? AND cms.status IN ('not_eating', 'on_leave', 'sick_meal')
                 WHERE cms.id IS NOT NULL"
            );
            $notEatingStmt->execute([$date]);
            $notEating = $this->fetchColumnInt($notEatingStmt);

            $specialDiet = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 INNER JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1 AND smp.diet_type != 'normal'"
            ));

            $sickBayStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 INNER JOIN boarding_attendance ba ON ba.student_id = s.id AND ba.date = ? AND ba.status = 'sick_bay'"
            );
            $sickBayStmt->execute([$date]);
            $sickBay = $this->fetchColumnInt($sickBayStmt);

            $breakdownByClass = $this->allRows($this->db->query(
                "SELECT cls.name AS class_name, COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 GROUP BY cls.id, cls.name
                 ORDER BY cls.name"
            ));

            $breakdownByDiet = $this->allRows($this->db->query(
                "SELECT smp.diet_type, COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 LEFT JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                 GROUP BY smp.diet_type
                 ORDER BY smp.diet_type"
            ));

            return $this->successResponse([
                'total_boarders' => $totalBoarders,
                'breakfast_count' => $totalBoarders - $notEating,
                'lunch_count' => $totalBoarders - $notEating,
                'supper_count' => $totalBoarders - $notEating,
                'special_diet_count' => $specialDiet,
                'absent_or_leave_count' => $notEating,
                'sick_meal_count' => $sickBay,
                'food_store_items_required' => 0,
                'breakdown_by_class' => $breakdownByClass,
                'breakdown_by_diet' => $breakdownByDiet,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCateringBoardingSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCateringBoardingStudent(int $studentId)
    {
        try {
            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, p.first_name, p.last_name, p.gender
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$studentId]);
            $student = $this->fetch($studentStmt);

            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $boardingStmt = $this->db->prepare(
                "SELECT da.*, d.name AS dormitory_name, d.gender AS dormitory_gender
                 FROM dormitory_assignments da
                 INNER JOIN student_academic_enrollments sae ON sae.id = da.student_academic_enrollment_id
                 INNER JOIN dormitories d ON d.id = da.dormitory_id
                 WHERE sae.student_id = ? AND da.status = 'active'
                 ORDER BY da.assigned_date DESC
                 LIMIT 1"
            );
            $boardingStmt->execute([$studentId]);
            $boarding = $this->fetch($boardingStmt);

            $classInfoStmt = $this->db->prepare(
                "SELECT cls.name AS class_name, st.name AS stream_name
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 WHERE s.id = ?"
            );
            $classInfoStmt->execute([$studentId]);
            $classInfo = $this->fetch($classInfoStmt);

            $dietStmt = $this->db->prepare(
                "SELECT * FROM student_meal_profiles WHERE student_id = ? AND active = 1"
            );
            $dietStmt->execute([$studentId]);
            $diet = $this->fetch($dietStmt);

            $mealHistoryStmt = $this->db->prepare(
                "SELECT meal_date, meal_type, status, notes
                 FROM catering_meal_statuses
                 WHERE student_id = ?
                 ORDER BY meal_date DESC, meal_type DESC
                 LIMIT 10"
            );
            $mealHistoryStmt->execute([$studentId]);
            $mealHistory = $this->allRows($mealHistoryStmt);

            $todayStatusStmt = $this->db->prepare(
                "SELECT * FROM catering_meal_statuses
                 WHERE student_id = ? AND meal_date = CURDATE()"
            );
            $todayStatusStmt->execute([$studentId]);
            $todayStatus = $this->fetch($todayStatusStmt);

            return $this->successResponse([
                'student' => $student,
                'boarding' => $boarding,
                'diet' => $diet,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'dormitory_name' => $boarding['dormitory_name'] ?? null,
                'meal_restrictions' => [],
                'meal_history' => $mealHistory,
                'today_status' => $todayStatus,
                'catering_notes' => [],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCateringBoardingStudent');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postCateringMenuPlan(array $data, int $userId)
    {
        try {
            $planDate = !empty($data['date']) ? $data['date'] : null;
            $mealType = !empty($data['meal_type']) ? $data['meal_type'] : null;
            $expectedCount = !empty($data['expected_count']) ? (int) $data['expected_count'] : 0;
            $notes = $data['notes'] ?? null;

            if (!$planDate || !$mealType) {
                return $this->errorResponse('Date and meal type are required', 400);
            }

            if ($mealType === 'supper') {
                $mealType = 'dinner';
            }

            $checkStmt = $this->db->prepare(
                "SELECT id FROM meal_plans
                 WHERE plan_date = ? AND meal_type = ?"
            );
            $checkStmt->execute([$planDate, $mealType]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $updateStmt = $this->db->prepare(
                    "UPDATE meal_plans
                     SET menu_item_id = ?, planned_servings = ?, prepared_quantity = ?, actual_servings = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ?"
                );
                $updateStmt->execute([null, $expectedCount, 0, 0, $notes, $existing['id']]);
            } else {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO meal_plans (plan_date, meal_type, menu_item_id, planned_servings, prepared_quantity, actual_servings, status, created_by, notes)
                     VALUES (?, ?, ?, ?, 0, 0, 'planned', ?, ?)"
                );
                $insertStmt->execute([$planDate, $mealType, null, $expectedCount, $userId, $notes]);
            }

            return $this->successResponse([], 'Meal plan saved successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postCateringMenuPlan');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getCateringFoodRequisition()
    {
        try {
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'inventory_items'");
            $inventoryExists = $tableCheck->fetchAll();

            if (!$inventoryExists) {
                return $this->successResponse(['available' => false, 'message' => 'Inventory tables not found']);
            }

            return $this->successResponse([
                'available' => true,
                'message' => 'Food requisition calculation requires recipe integration',
                'items' => [],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getCateringFoodRequisition');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getBoardingMeta()
    {
        try {
            return $this->successResponse([
                'academic_years' => $this->allRows($this->db->query(
                    "SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"
                )),
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'dormitories' => $this->allRows($this->db->query(
                    "SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC"
                )),
                'boarding_statuses' => ['active', 'on_leave', 'sick', 'checked_out', 'suspended'],
                'roll_call_statuses' => ['present', 'absent', 'late', 'excused', 'sick_bay', 'on_exeat'],
                'exeat_statuses' => ['requested', 'approved', 'out', 'returned', 'overdue', 'cancelled'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getBoardingMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getBoardingStudents(array $filters)
    {
        try {
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $gender = !empty($filters['gender']) ? trim($filters['gender']) : null;
            $dormitoryId = !empty($filters['dormitory_id']) ? (int) $filters['dormitory_id'] : null;
            $bedStatus = !empty($filters['bed_status']) ? trim($filters['bed_status']) : null;
            $boardingStatus = !empty($filters['boarding_status']) ? trim($filters['boarding_status']) : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        s.id AS student_id,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                        p.gender,
                        ayc.class_id,
                        cls.name AS class_name,
                        st.name AS stream_name,
                        d.id AS dormitory_id,
                        d.name AS dormitory_name,
                        da.bed_number,
                        da.status AS boarding_status
                    " . $this->studentBase() . "
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes cls ON cls.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN dormitory_assignments da
                         ON da.student_academic_enrollment_id = sae.id
                         AND da.status = 'active'
                         AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                    LEFT JOIN dormitories d ON d.id = da.dormitory_id
                    WHERE da.id IS NOT NULL";

            $bindings = [];

            if ($classId) {
                $sql .= " AND ayc.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND p.gender = ?";
                $bindings[] = $gender;
            }

            if ($dormitoryId) {
                $sql .= " AND d.id = ?";
                $bindings[] = $dormitoryId;
            }

            if ($bedStatus === 'assigned') {
                $sql .= " AND da.bed_number IS NOT NULL AND da.bed_number != ''";
            } elseif ($bedStatus === 'unassigned') {
                $sql .= " AND (da.bed_number IS NULL OR da.bed_number = '')";
            }

            if ($boardingStatus) {
                $sql .= " AND da.status = ?";
                $bindings[] = $boardingStatus;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                             OR d.name LIKE ? OR da.bed_number LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY p.first_name, p.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $students = $this->allRows($stmt);

            $today = date('Y-m-d');

            $rollCallStmt = $this->db->prepare(
                "SELECT status FROM boarding_attendance
                 WHERE student_id = ? AND date = ?
                 ORDER BY created_at DESC LIMIT 1"
            );
            $exeatStmt = $this->db->prepare(
                "SELECT status FROM student_permissions
                 WHERE student_id = ? AND permission_type_id = 1
                 AND start_date <= ? AND (end_date >= ? OR end_date IS NULL)
                 AND status = 'approved'
                 ORDER BY start_date DESC
                 LIMIT 1"
            );
            $alertStmt = $this->db->prepare(
                "SELECT COUNT(*) AS alert_count
                 FROM student_meal_profiles smp
                 WHERE smp.student_id = ? AND smp.active = 1
                 AND (smp.diet_type != 'normal' OR smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL)"
            );

            foreach ($students as &$student) {
                $student['roll_call_status_today'] = 'present';
                $student['exeat_status'] = 'none';
                $student['has_special_alert'] = false;
                $student['special_alert_summary'] = '';

                $rollCallStmt->execute([$student['student_id'], $today]);
                $rollCall = $this->fetch($rollCallStmt);
                if ($rollCall) {
                    $student['roll_call_status_today'] = $rollCall['status'];
                }

                $exeatStmt->execute([$student['student_id'], $today, $today]);
                $exeat = $this->fetch($exeatStmt);
                if ($exeat) {
                    $student['exeat_status'] = $exeat['status'];
                }

                $alertStmt->execute([$student['student_id']]);
                $alertCount = $this->fetch($alertStmt)['alert_count'] ?? 0;
                $student['has_special_alert'] = $alertCount > 0;
            }
            unset($student);

            return $this->successResponse($students);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getBoardingStudents');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getBoardingSummary()
    {
        try {
            $totalBoarders = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS total
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())"
            ));

            $boys = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 WHERE p.gender = 'male'"
            ));

            $girls = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 WHERE p.gender = 'female'"
            ));

            $today = date('Y-m-d');

            $onExeatStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 INNER JOIN student_permissions sp ON sp.student_id = s.id AND sp.permission_type_id = 1
                 WHERE sp.status = 'approved'
                 AND sp.start_date <= ? AND (sp.end_date >= ? OR sp.end_date IS NULL)"
            );
            $onExeatStmt->execute([$today, $today]);
            $onExeat = $this->fetchColumnInt($onExeatStmt);

            $absentStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 INNER JOIN boarding_attendance ba ON ba.student_id = s.id AND ba.date = ?
                 WHERE ba.status IN ('absent', 'sick_bay')"
            );
            $absentStmt->execute([$today]);
            $absent = $this->fetchColumnInt($absentStmt);

            $specialAlerts = $this->fetchColumnInt($this->db->query(
                "SELECT COUNT(DISTINCT s.id) AS count
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 INNER JOIN dormitory_assignments da
                      ON da.student_academic_enrollment_id = sae.id
                      AND da.status = 'active'
                      AND (da.end_date IS NULL OR da.end_date >= CURDATE())
                 INNER JOIN student_meal_profiles smp ON smp.student_id = s.id AND smp.active = 1
                 WHERE smp.diet_type != 'normal' OR smp.food_restrictions IS NOT NULL OR smp.allergy_notes IS NOT NULL"
            ));

            return $this->successResponse([
                'total_boarders' => $totalBoarders,
                'boys_boarders' => $boys,
                'girls_boarders' => $girls,
                'on_exeat_count' => $onExeat,
                'absent_count' => $absent,
                'special_alerts_count' => $specialAlerts,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getBoardingSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getBoardingStudent(int $studentId)
    {
        try {
            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, p.first_name, p.last_name, p.gender
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$studentId]);
            $student = $this->fetch($studentStmt);

            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $boardingStmt = $this->db->prepare(
                "SELECT da.*, d.name AS dormitory_name, d.gender AS dormitory_gender
                 FROM dormitory_assignments da
                 INNER JOIN student_academic_enrollments sae ON sae.id = da.student_academic_enrollment_id
                 INNER JOIN dormitories d ON d.id = da.dormitory_id
                 WHERE sae.student_id = ? AND da.status = 'active'
                 ORDER BY da.assigned_date DESC
                 LIMIT 1"
            );
            $boardingStmt->execute([$studentId]);
            $boarding = $this->fetch($boardingStmt);

            $classInfoStmt = $this->db->prepare(
                "SELECT cls.name AS class_name, st.name AS stream_name
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 WHERE s.id = ?"
            );
            $classInfoStmt->execute([$studentId]);
            $classInfo = $this->fetch($classInfoStmt);

            $rollCallStmt = $this->db->prepare(
                "SELECT date AS roll_call_date, session_id, status
                 FROM boarding_attendance
                 WHERE student_id = ?
                 ORDER BY date DESC
                 LIMIT 10"
            );
            $rollCallStmt->execute([$studentId]);
            $rollCallHistory = $this->allRows($rollCallStmt);

            $exeatStmt = $this->db->prepare(
                "SELECT pt.name AS exeat_type, sp.start_date AS leave_at, sp.end_date AS expected_return_at, sp.status
                 FROM student_permissions sp
                 LEFT JOIN student_permission_types pt ON pt.id = sp.permission_type_id
                 WHERE sp.student_id = ? AND sp.permission_type_id = 1
                 ORDER BY sp.start_date DESC
                 LIMIT 10"
            );
            $exeatStmt->execute([$studentId]);
            $exeatHistory = $this->allRows($exeatStmt);

            $notesStmt = $this->db->prepare(
                "SELECT note_type, note, created_at
                 FROM student_boarding_notes
                 WHERE student_id = ?
                 ORDER BY created_at DESC
                 LIMIT 10"
            );
            $notesStmt->execute([$studentId]);
            $boardingNotes = $this->allRows($notesStmt);

            return $this->successResponse([
                'student' => $student,
                'boarding' => $boarding,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'dormitory_name' => $boarding['dormitory_name'] ?? null,
                'roll_call_history' => $rollCallHistory,
                'exeat_history' => $exeatHistory,
                'boarding_notes' => $boardingNotes,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getBoardingStudent');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postBoardingAssignDorm(array $data, int $userId)
    {
        try {
            $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
            $dormitoryId = !empty($data['dormitory_id']) ? (int) $data['dormitory_id'] : null;
            $bedNumber = $data['bed_number'] ?? null;
            $allocationDate = !empty($data['allocation_date']) ? $data['allocation_date'] : date('Y-m-d');
            $notes = $data['notes'] ?? null;

            if (!$studentId || !$dormitoryId) {
                return $this->errorResponse('Student ID and Dormitory ID are required', 400);
            }

            $enrollmentStmt = $this->db->prepare(
                "SELECT id FROM student_academic_enrollments
                 WHERE student_id = ? AND enrollment_status = 'active'
                 ORDER BY id DESC LIMIT 1"
            );
            $enrollmentStmt->execute([$studentId]);
            $enrollmentId = $enrollmentStmt->fetchColumn();

            if (!$enrollmentId) {
                return $this->errorResponse('No active enrollment found for the student', 400);
            }

            $this->db->prepare(
                "UPDATE dormitory_assignments
                 SET status = 'transferred', end_date = ?
                 WHERE student_academic_enrollment_id = ? AND status = 'active'"
            )->execute([$allocationDate, $enrollmentId]);

            $insertStmt = $this->db->prepare(
                "INSERT INTO dormitory_assignments (student_academic_enrollment_id, dormitory_id, bed_number, assigned_date, status, assigned_by, notes)
                 VALUES (?, ?, ?, ?, 'active', ?, ?)"
            );
            $insertStmt->execute([$enrollmentId, $dormitoryId, $bedNumber, $allocationDate, $userId, $notes]);

            return $this->successResponse([], 'Dormitory assigned successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postBoardingAssignDorm');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getTransportMeta()
    {
        try {
            return $this->successResponse([
                'routes' => $this->allRows($this->db->query(
                    "SELECT id, name AS route_name, code FROM transport_routes WHERE status = 'active' ORDER BY name ASC"
                )),
                'vehicles' => $this->allRows($this->db->query(
                    "SELECT id, registration_number AS vehicle_number, type, make, model, capacity FROM transport_vehicles WHERE status = 'active' ORDER BY registration_number ASC"
                )),
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'trip_sessions' => ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'],
                'transport_statuses' => ['active', 'suspended', 'not_riding', 'transferred'],
                'attendance_statuses' => ['pending', 'picked_up', 'dropped_off', 'absent', 'excused', 'not_riding'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getTransportMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getTransportPassengers(array $filters, array $user)
    {
        try {
            $date = !empty($filters['date']) ? trim($filters['date']) : date('Y-m-d');
            $routeId = !empty($filters['route_id']) ? (int) $filters['route_id'] : null;
            $vehicleId = !empty($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null;
            $tripSession = !empty($filters['trip_session']) ? trim($filters['trip_session']) : null;
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $gender = !empty($filters['gender']) ? trim($filters['gender']) : null;
            $transportStatus = !empty($filters['transport_status']) ? trim($filters['transport_status']) : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        s.id AS student_id,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                        p.gender,
                        ayc.class_id,
                        cls.name AS class_name,
                        st.name AS stream_name,
                        tr.id AS route_id,
                        tr.name AS route_name,
                        tv.id AS vehicle_id,
                        tv.registration_number AS vehicle_name,
                        ts.name AS pickup_point,
                        ts_drop.name AS dropoff_point,
                        pg.phone AS guardian_phone
                    FROM students s
                    INNER JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes cls ON cls.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                    LEFT JOIN transport_routes tr ON tr.id = sta.route_id
                    LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = sta.route_id AND tvr.status = 'active'
                    LEFT JOIN transport_vehicles tv ON tv.id = tvr.vehicle_id
                    LEFT JOIN transport_stops ts ON ts.id = sta.pickup_stop_id
                    LEFT JOIN transport_stops ts_drop ON ts_drop.id = sta.dropoff_stop_id
                    LEFT JOIN student_parents sp ON sp.student_id = s.id AND sp.is_primary_contact = 1
                    LEFT JOIN parents pp ON pp.id = sp.parent_id
                    LEFT JOIN persons pg ON pg.id = pp.person_id
                    WHERE sta.id IS NOT NULL";

            $bindings = [];

            if (($user['role'] ?? '') === 'driver' && !empty($user['id'])) {
                $sql .= " AND tv.driver_id = (SELECT st.id FROM staff st
                             INNER JOIN users u ON u.person_id = st.person_id WHERE u.id = ?)";
                $bindings[] = (int) $user['id'];
            }

            if ($routeId) {
                $sql .= " AND tr.id = ?";
                $bindings[] = $routeId;
            }

            if ($vehicleId) {
                $sql .= " AND tv.id = ?";
                $bindings[] = $vehicleId;
            }

            if ($classId) {
                $sql .= " AND ayc.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND p.gender = ?";
                $bindings[] = $gender;
            }

            if ($transportStatus) {
                $sql .= " AND sta.status = ?";
                $bindings[] = $transportStatus;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                             OR ts.name LIKE ? OR pg.phone LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " ORDER BY p.first_name, p.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $passengers = $this->allRows($stmt);

            $session = $tripSession ?: 'morning_pickup';

            $attendanceStmt = $this->db->prepare(
                "SELECT status FROM student_transport_attendance
                 WHERE student_id = ? AND attendance_date = ?
                 AND trip_session = ?
                 ORDER BY created_at DESC
                 LIMIT 1"
            );
            $alertStmt = $this->db->prepare(
                "SELECT COUNT(*) AS alert_count
                 FROM student_transport_notes
                 WHERE student_id = ? AND visibility = 'public' AND resolved = 0"
            );

            foreach ($passengers as &$passenger) {
                $passenger['today_status'] = 'pending';
                $passenger['has_transport_alert'] = false;
                $passenger['transport_alert_summary'] = '';

                $attendanceStmt->execute([$passenger['student_id'], $date, $session]);
                $attendance = $this->fetch($attendanceStmt);
                if ($attendance) {
                    $passenger['today_status'] = $attendance['status'];
                }

                $alertStmt->execute([$passenger['student_id']]);
                $alertCount = $this->fetch($alertStmt)['alert_count'] ?? 0;
                $passenger['has_transport_alert'] = $alertCount > 0;
            }
            unset($passenger);

            return $this->successResponse($passengers);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getTransportPassengers');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getTransportSummary(array $filters)
    {
        try {
            $date = !empty($filters['date']) ? trim($filters['date']) : date('Y-m-d');
            $routeId = !empty($filters['route_id']) ? (int) $filters['route_id'] : null;
            $vehicleId = !empty($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null;

            $sql = "SELECT COUNT(DISTINCT s.id) AS total
                    FROM students s
                    INNER JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                    LEFT JOIN transport_routes tr ON tr.id = sta.route_id
                    LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = sta.route_id AND tvr.status = 'active'
                    LEFT JOIN transport_vehicles tv ON tv.id = tvr.vehicle_id
                    WHERE 1=1";

            $bindings = [];
            if ($routeId) {
                $sql .= " AND tr.id = ?";
                $bindings[] = $routeId;
            }
            if ($vehicleId) {
                $sql .= " AND tv.id = ?";
                $bindings[] = $vehicleId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $totalPassengers = $this->fetchColumnInt($stmt);

            $attendanceSql = "SELECT
                        COUNT(DISTINCT CASE WHEN status = 'picked_up' THEN s.id END) AS picked_up,
                        COUNT(DISTINCT CASE WHEN status = 'dropped_off' THEN s.id END) AS dropped_off,
                        COUNT(DISTINCT CASE WHEN status = 'absent' THEN s.id END) AS absent,
                        COUNT(DISTINCT CASE WHEN status = 'not_riding' THEN s.id END) AS not_riding,
                        COUNT(DISTINCT CASE WHEN status = 'pending' THEN s.id END) AS pending
                    FROM student_transport_attendance sta
                    INNER JOIN students s ON s.id = sta.student_id
                    WHERE sta.attendance_date = ?";

            $attendanceBindings = [$date];
            if ($routeId) {
                $attendanceSql .= " AND sta.route_id = ?";
                $attendanceBindings[] = $routeId;
            }
            if ($vehicleId) {
                $attendanceSql .= " AND sta.vehicle_id = ?";
                $attendanceBindings[] = $vehicleId;
            }

            $attendanceStmt = $this->db->prepare($attendanceSql);
            $attendanceStmt->execute($attendanceBindings);
            $attendance = $this->fetch($attendanceStmt);

            $alertSql = "SELECT COUNT(DISTINCT s.id) AS count
                    FROM student_transport_notes stn
                    INNER JOIN students s ON s.id = stn.student_id
                    LEFT JOIN student_transport_assignments sta ON sta.student_id = s.id AND sta.status = 'active'
                    LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = sta.route_id AND tvr.status = 'active'
                    WHERE stn.visibility = 'public' AND stn.resolved = 0";

            $alertBindings = [];
            if ($routeId) {
                $alertSql .= " AND sta.route_id = ?";
                $alertBindings[] = $routeId;
            }
            if ($vehicleId) {
                $alertSql .= " AND tvr.vehicle_id = ?";
                $alertBindings[] = $vehicleId;
            }

            $alertStmt = $this->db->prepare($alertSql);
            $alertStmt->execute($alertBindings);
            $alerts = $this->fetchColumnInt($alertStmt);

            $routeName = '';
            $vehicleName = '';
            if ($routeId) {
                $routeStmt = $this->db->prepare("SELECT name FROM transport_routes WHERE id = ?");
                $routeStmt->execute([$routeId]);
                $routeName = $this->fetch($routeStmt)['name'] ?? '';
            }
            if ($vehicleId) {
                $vehicleStmt = $this->db->prepare("SELECT registration_number FROM transport_vehicles WHERE id = ?");
                $vehicleStmt->execute([$vehicleId]);
                $vehicleName = $this->fetch($vehicleStmt)['registration_number'] ?? '';
            }

            return $this->successResponse([
                'total_passengers' => $totalPassengers,
                'expected_today' => $totalPassengers,
                'picked_up' => $attendance['picked_up'] ?? 0,
                'dropped_off' => $attendance['dropped_off'] ?? 0,
                'absent' => $attendance['absent'] ?? 0,
                'not_riding' => $attendance['not_riding'] ?? 0,
                'pending' => $attendance['pending'] ?? $totalPassengers,
                'emergency_alerts' => $alerts,
                'route_name' => $routeName,
                'vehicle_name' => $vehicleName,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getTransportSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getTransportPassenger(int $studentId)
    {
        try {
            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, p.first_name, p.last_name, p.gender
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$studentId]);
            $student = $this->fetch($studentStmt);

            if (!$student) {
                return $this->errorResponse('Student not found', 404);
            }

            $transportStmt = $this->db->prepare(
                "SELECT sta.*, tr.name AS route_name,
                        tv.registration_number AS vehicle_name,
                        ts.name AS pickup_point, ts_drop.name AS dropoff_point,
                        (SELECT e.allocated_school_days FROM student_transport_entitlements e
                          JOIN transport_entitlement_periods ep ON ep.id=e.period_id
                         WHERE e.student_id=sta.student_id AND e.route_id=sta.route_id AND e.entitlement_status='active'
                         ORDER BY ep.period_end DESC, e.id DESC LIMIT 1) AS paid_school_days,
                        (SELECT COUNT(*) FROM student_transport_day_usage u
                          JOIN student_transport_entitlements e ON e.id=u.entitlement_id
                         WHERE e.student_id=sta.student_id AND e.route_id=sta.route_id AND e.entitlement_status='active') AS used_school_days
                 FROM student_transport_assignments sta
                 INNER JOIN transport_routes tr ON tr.id = sta.route_id
                 LEFT JOIN transport_vehicle_routes tvr ON tvr.route_id = sta.route_id AND tvr.status = 'active'
                 LEFT JOIN transport_vehicles tv ON tv.id = tvr.vehicle_id
                 LEFT JOIN transport_stops ts ON ts.id = sta.pickup_stop_id
                 LEFT JOIN transport_stops ts_drop ON ts_drop.id = sta.dropoff_stop_id
                 WHERE sta.student_id = ? AND sta.status = 'active'
                 ORDER BY sta.created_at DESC
                 LIMIT 1"
            );
            $transportStmt->execute([$studentId]);
            $transport = $this->fetch($transportStmt);
            if ($transport) {
                $transport['remaining_school_days'] = max(0, (int)($transport['paid_school_days'] ?? 0) - (int)($transport['used_school_days'] ?? 0));
            }

            $classInfoStmt = $this->db->prepare(
                "SELECT cls.name AS class_name, st.name AS stream_name
                 FROM students s
                 INNER JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 WHERE s.id = ?"
            );
            $classInfoStmt->execute([$studentId]);
            $classInfo = $this->fetch($classInfoStmt);

            $guardianStmt = $this->db->prepare(
                "SELECT pg.phone AS phone_1 FROM student_parents sp
                 INNER JOIN parents p ON p.id = sp.parent_id
                 INNER JOIN persons pg ON pg.id = p.person_id
                 WHERE sp.student_id = ? AND sp.is_primary_contact = 1 LIMIT 1"
            );
            $guardianStmt->execute([$studentId]);
            $guardian = $this->fetch($guardianStmt);

            $attendanceStmt = $this->db->prepare(
                "SELECT attendance_date AS date, trip_session AS session, status, marked_time AS time
                 FROM student_transport_attendance
                 WHERE student_id = ?
                 ORDER BY attendance_date DESC
                 LIMIT 10"
            );
            $attendanceStmt->execute([$studentId]);
            $attendance = $this->allRows($attendanceStmt);

            $notesStmt = $this->db->prepare(
                "SELECT note_type, note, created_at
                 FROM student_transport_notes
                 WHERE student_id = ? AND visibility = 'public'
                 ORDER BY created_at DESC
                 LIMIT 10"
            );
            $notesStmt->execute([$studentId]);
            $notes = $this->allRows($notesStmt);

            return $this->successResponse([
                'student' => $student,
                'transport' => $transport,
                'class_name' => $classInfo['class_name'] ?? null,
                'stream_name' => $classInfo['stream_name'] ?? null,
                'route_name' => $transport['route_name'] ?? null,
                'vehicle_name' => $transport['vehicle_name'] ?? null,
                'guardian_phone' => $guardian['phone_1'] ?? null,
                'attendance' => $attendance,
                'notes' => $notes,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getTransportPassenger');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postTransportAttendance(array $data, int $userId)
    {
        try {
            $attendanceDate = !empty($data['attendance_date']) ? $data['attendance_date'] : date('Y-m-d');
            $routeId = !empty($data['route_id']) ? (int) $data['route_id'] : null;
            $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
            $tripSession = !empty($data['trip_session']) ? $data['trip_session'] : 'morning_pickup';
            $records = !empty($data['records']) ? $data['records'] : [];

            if (empty($records)) {
                return $this->errorResponse('Attendance records are required', 400);
            }

            $checkStmt = $this->db->prepare(
                "SELECT id FROM student_transport_attendance
                 WHERE student_id = ? AND attendance_date = ? AND trip_session = ?"
            );
            $updateStmt = $this->db->prepare(
                "UPDATE student_transport_attendance
                 SET status = ?, marked_time = ?, notes = ?, marked_by = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $insertStmt = $this->db->prepare(
                "INSERT INTO student_transport_attendance (student_id, route_id, vehicle_id, attendance_date, trip_session, status, marked_time, notes, marked_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            foreach ($records as $record) {
                $studentId = !empty($record['student_id']) ? (int) $record['student_id'] : null;
                $status = !empty($record['status']) ? $record['status'] : 'pending';
                $markedTime = !empty($record['marked_time']) ? $record['marked_time'] : null;
                $notes = !empty($record['notes']) ? $record['notes'] : null;

                if (!$studentId) continue;

                $checkStmt->execute([$studentId, $attendanceDate, $tripSession]);
                $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $updateStmt->execute([$status, $markedTime, $notes, $userId, $existing['id']]);
                } else {
                    $insertStmt->execute([$studentId, $routeId, $vehicleId, $attendanceDate, $tripSession, $status, $markedTime, $notes, $userId]);
                }
            }

            return $this->successResponse([], 'Attendance saved successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postTransportAttendance');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postTransportIncident(array $data, int $userId)
    {
        try {
            $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
            $routeId = !empty($data['route_id']) ? (int) $data['route_id'] : null;
            $vehicleId = !empty($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
            $incidentDateTime = !empty($data['incident_datetime']) ? $data['incident_datetime'] : date('Y-m-d H:i:s');
            $incidentType = !empty($data['incident_type']) ? $data['incident_type'] : 'other';
            $description = !empty($data['description']) ? $data['description'] : '';
            $actionTaken = !empty($data['action_taken']) ? $data['action_taken'] : null;
            $escalated = !empty($data['escalated']) ? (int) $data['escalated'] : 0;

            if (!$description) {
                return $this->errorResponse('Description is required', 400);
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO student_transport_incidents (student_id, route_id, vehicle_id, incident_datetime, incident_type, description, action_taken, escalated, reported_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([$studentId, $routeId, $vehicleId, $incidentDateTime, $incidentType, $description, $actionTaken, $escalated, $userId]);

            return $this->successResponse([], 'Incident reported successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postTransportIncident');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getWelfareMeta()
    {
        try {
            return $this->successResponse([
                'academic_years' => $this->allRows($this->db->query(
                    "SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"
                )),
                'terms' => $this->allRows($this->db->query("SELECT id, name FROM terms ORDER BY name ASC")),
                'classes' => $this->allRows($this->db->query("SELECT id, name FROM classes ORDER BY name ASC")),
                'streams' => $this->currentStreams(),
                'staff' => $this->staffList(),
                'students' => $this->studentList(),
                'welfare_categories' => ['emotional', 'social', 'behavioral', 'family', 'chapel', 'pastoral', 'referral', 'other'],
                'referral_sources' => ['self', 'teacher', 'parent', 'discipline', 'health', 'other'],
                'priorities' => ['low', 'medium', 'high', 'urgent'],
                'statuses' => ['open', 'in_progress', 'resolved', 'closed', 'cancelled'],
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getWelfareMeta');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getWelfareCases(array $filters, array $user)
    {
        try {
            $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
            $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
            $gender = !empty($filters['gender']) ? trim($filters['gender']) : null;
            $welfareCategory = !empty($filters['welfare_category']) ? trim($filters['welfare_category']) : null;
            $referralSource = !empty($filters['referral_source']) ? trim($filters['referral_source']) : null;
            $priority = !empty($filters['priority']) ? trim($filters['priority']) : null;
            $status = !empty($filters['status']) ? trim($filters['status']) : null;
            $assignedTo = !empty($filters['assigned_to']) ? (int) $filters['assigned_to'] : null;
            $search = !empty($filters['search']) ? trim($filters['search']) : '';

            $sql = "SELECT
                        swc.id,
                        swc.case_code,
                        swc.student_id,
                        swc.title,
                        swc.welfare_category,
                        swc.referral_source,
                        swc.priority,
                        swc.status,
                        swc.opened_at,
                        swc.next_follow_up_at,
                        s.admission_no,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                        p.gender,
                        cls.name AS class_name,
                        st.name AS stream_name,
                        CONCAT_WS(' ', up.first_name, up.last_name) AS assigned_to_name,
                        MAX(swn.created_at) AS last_interaction
                    FROM student_welfare_cases swc
                    INNER JOIN students s ON s.id = swc.student_id
                    INNER JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes cls ON cls.id = ayc.class_id
                    LEFT JOIN streams st ON st.id = aycs.stream_id
                    LEFT JOIN users u ON u.id = swc.assigned_to
                    LEFT JOIN persons up ON up.id = u.person_id
                    LEFT JOIN student_welfare_notes swn ON swn.welfare_case_id = swc.id
                    WHERE s.status = 'active'";

            $bindings = [];

            if (($user['role'] ?? '') === 'chaplain' && !empty($user['id'])) {
                $sql .= " AND swc.assigned_to = ?";
                $bindings[] = (int) $user['id'];
            }

            if ($welfareCategory) {
                $sql .= " AND swc.welfare_category = ?";
                $bindings[] = $welfareCategory;
            }

            if ($referralSource) {
                $sql .= " AND swc.referral_source = ?";
                $bindings[] = $referralSource;
            }

            if ($priority) {
                $sql .= " AND swc.priority = ?";
                $bindings[] = $priority;
            }

            if ($status) {
                $sql .= " AND swc.status = ?";
                $bindings[] = $status;
            }

            if ($assignedTo) {
                $sql .= " AND swc.assigned_to = ?";
                $bindings[] = $assignedTo;
            }

            if ($classId) {
                $sql .= " AND ayc.class_id = ?";
                $bindings[] = $classId;
            }

            if ($streamId) {
                $sql .= " AND aycs.stream_id = ?";
                $bindings[] = $streamId;
            }

            if ($gender) {
                $sql .= " AND p.gender = ?";
                $bindings[] = $gender;
            }

            if ($search) {
                $sql .= " AND (s.admission_no LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?
                             OR swc.title LIKE ? OR swc.referral_source LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term, $term);
            }

            $sql .= " GROUP BY swc.id ORDER BY swc.opened_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $cases = $this->allRows($stmt);

            return $this->successResponse($cases);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getWelfareCases');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getWelfareCase(int $caseId)
    {
        try {
            $caseStmt = $this->db->prepare(
                "SELECT swc.*,
                        CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                        s.admission_no,
                        cls.name AS class_name,
                        st.name AS stream_name,
                        CONCAT_WS(' ', up.first_name, up.last_name) AS assigned_to_name,
                        CONCAT_WS(' ', ob.first_name, ob.last_name) AS opened_by_name,
                        CONCAT_WS(' ', rb.first_name, rb.last_name) AS resolved_by_name
                 FROM student_welfare_cases swc
                 INNER JOIN students s ON s.id = swc.student_id
                 INNER JOIN persons p ON p.id = s.person_id
                 LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 LEFT JOIN streams st ON st.id = aycs.stream_id
                 LEFT JOIN users u ON u.id = swc.assigned_to
                 LEFT JOIN persons up ON up.id = u.person_id
                 LEFT JOIN users uo ON uo.id = swc.opened_by
                 LEFT JOIN persons ob ON ob.id = uo.person_id
                 LEFT JOIN users ur ON ur.id = swc.resolved_by
                 LEFT JOIN persons rb ON rb.id = ur.person_id
                 WHERE swc.id = ?
                 LIMIT 1"
            );
            $caseStmt->execute([$caseId]);
            $case = $this->fetch($caseStmt);

            if (!$case) {
                return $this->errorResponse('Welfare case not found', 404);
            }

            $studentStmt = $this->db->prepare(
                "SELECT s.id, s.admission_no, p.first_name, p.last_name, p.gender
                 FROM students s
                 INNER JOIN persons p ON p.id = s.person_id
                 WHERE s.id = ?"
            );
            $studentStmt->execute([$case['student_id']]);
            $student = $this->fetch($studentStmt);

            $notesStmt = $this->db->prepare(
                "SELECT note_type, note, created_at
                 FROM student_welfare_notes
                 WHERE welfare_case_id = ?
                 ORDER BY created_at DESC
                 LIMIT 10"
            );
            $notesStmt->execute([$caseId]);
            $notes = $this->allRows($notesStmt);

            return $this->successResponse([
                'case' => $case,
                'student' => $student,
                'class_name' => $case['class_name'] ?? null,
                'stream_name' => $case['stream_name'] ?? null,
                'assigned_to_name' => $case['assigned_to_name'] ?? null,
                'notes' => $notes,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::getWelfareCase');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postWelfareCase(array $data, int $userId)
    {
        try {
            $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
            $title = !empty($data['title']) ? $data['title'] : '';
            $welfareCategory = !empty($data['welfare_category']) ? $data['welfare_category'] : 'other';
            $referralSource = !empty($data['referral_source']) ? $data['referral_source'] : null;
            $priority = !empty($data['priority']) ? $data['priority'] : 'medium';
            $description = !empty($data['description']) ? $data['description'] : null;
            $assignedTo = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
            $nextFollowUpAt = !empty($data['next_follow_up_at']) ? $data['next_follow_up_at'] : null;

            if (!$studentId || !$title) {
                return $this->errorResponse('Student and title are required', 400);
            }

            $caseCode = 'WEL-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

            $insertStmt = $this->db->prepare(
                "INSERT INTO student_welfare_cases (student_id, case_code, title, welfare_category, referral_source, priority, status, description, assigned_to, opened_by, opened_at, next_follow_up_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'open', ?, ?, ?, CURRENT_TIMESTAMP, ?)"
            );
            $insertStmt->execute([$studentId, $caseCode, $title, $welfareCategory, $referralSource, $priority, $description, $assignedTo, $userId, $nextFollowUpAt]);

            return $this->successResponse([], 'Welfare case created successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postWelfareCase');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postWelfareCaseNote(int $caseId, array $data, int $userId)
    {
        try {
            $noteType = !empty($data['note_type']) ? $data['note_type'] : 'other';
            if (!in_array($noteType, ['assessment', 'intervention', 'observation', 'guardian_contact', 'follow_up', 'referral', 'other'])) {
                $noteType = 'other';
            }
            $note = !empty($data['note']) ? $data['note'] : '';
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;

            if (!$note) {
                return $this->errorResponse('Note content is required', 400);
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, follow_up_date, recorded_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([$caseId, $noteType, $note, $followUpDate, $userId]);

            if ($followUpDate) {
                $updateStmt = $this->db->prepare(
                    "UPDATE student_welfare_cases SET next_follow_up_at = ? WHERE id = ?"
                );
                $updateStmt->execute([$followUpDate, $caseId]);
            }

            return $this->successResponse([], 'Note added successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postWelfareCaseNote');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postWelfareCaseFollowUp(int $caseId, array $data, int $userId)
    {
        try {
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
            $note = !empty($data['note']) ? $data['note'] : null;

            if (!$followUpDate) {
                return $this->errorResponse('Follow-up date is required', 400);
            }

            $updateStmt = $this->db->prepare(
                "UPDATE student_welfare_cases SET next_follow_up_at = ? WHERE id = ?"
            );
            $updateStmt->execute([$followUpDate, $caseId]);

            if ($note) {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, follow_up_date, recorded_by)
                     VALUES (?, 'follow_up', ?, ?, ?)"
                );
                $insertStmt->execute([$caseId, $note, $followUpDate, $userId]);
            }

            return $this->successResponse([], 'Follow-up scheduled successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postWelfareCaseFollowUp');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postWelfareCaseResolve(int $caseId, array $data, int $userId)
    {
        try {
            $resolutionNote = !empty($data['resolution_note']) ? $data['resolution_note'] : null;

            $updateStmt = $this->db->prepare(
                "UPDATE student_welfare_cases
                 SET status = 'resolved', resolved_by = ?, resolved_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $updateStmt->execute([$userId, $caseId]);

            if ($resolutionNote) {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, recorded_by)
                     VALUES (?, 'other', ?, ?)"
                );
                $insertStmt->execute([$caseId, $resolutionNote, $userId]);
            }

            return $this->successResponse([], 'Case resolved successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postWelfareCaseResolve');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postWelfareCaseEscalate(int $caseId, array $data, int $userId)
    {
        try {
            $escalationNote = !empty($data['escalation_note']) ? $data['escalation_note'] : null;

            $updateStmt = $this->db->prepare(
                "UPDATE student_welfare_cases
                 SET status = 'in_progress'
                 WHERE id = ?"
            );
            $updateStmt->execute([$caseId]);

            if ($escalationNote) {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO student_welfare_notes (welfare_case_id, note_type, note, recorded_by)
                     VALUES (?, 'referral', ?, ?)"
                );
                $insertStmt->execute([$caseId, $escalationNote, $userId]);
            }

            return $this->successResponse([], 'Case escalated successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postWelfareCaseEscalate');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postCounselingCaseSessionNote(int $caseId, array $data, int $userId)
    {
        try {
            $sessionDate = !empty($data['session_date']) ? $data['session_date'] : date('Y-m-d');
            $sessionType = !empty($data['session_type']) ? $data['session_type'] : 'individual';
            $sessionNotes = !empty($data['session_notes']) ? $data['session_notes'] : '';
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;

            if (!$sessionNotes) {
                return $this->errorResponse('Session notes are required', 400);
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO counseling_sessions (case_id, session_date, session_type, summary, recorded_by)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([$caseId, $sessionDate, $sessionType, $sessionNotes, $userId]);

            if ($followUpDate) {
                $updateStmt = $this->db->prepare(
                    "UPDATE counseling_cases SET next_follow_up_at = ? WHERE id = ?"
                );
                $updateStmt->execute([$followUpDate, $caseId]);
            }

            return $this->successResponse([], 'Session note added successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postCounselingCaseSessionNote');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postCounselingCaseFollowUp(int $caseId, array $data, int $userId)
    {
        try {
            $followUpDate = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
            $note = !empty($data['note']) ? $data['note'] : null;

            if (!$followUpDate) {
                return $this->errorResponse('Follow-up date is required', 400);
            }

            $updateStmt = $this->db->prepare(
                "UPDATE counseling_cases SET next_follow_up_at = ? WHERE id = ?"
            );
            $updateStmt->execute([$followUpDate, $caseId]);

            if ($note) {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO counseling_sessions (case_id, session_date, session_type, summary, recorded_by)
                     VALUES (?, ?, 'follow_up', ?, ?)"
                );
                $insertStmt->execute([$caseId, date('Y-m-d'), $note, $userId]);
            }

            return $this->successResponse([], 'Follow-up scheduled successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postCounselingCaseFollowUp');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postCounselingCaseClose(int $caseId, array $data, int $userId)
    {
        try {
            $closureNote = !empty($data['closure_note']) ? $data['closure_note'] : null;

            $updateStmt = $this->db->prepare(
                "UPDATE counseling_cases
                 SET status = 'closed', closed_by = ?, closed_at = CURRENT_TIMESTAMP
                 WHERE id = ?"
            );
            $updateStmt->execute([$userId, $caseId]);

            if ($closureNote) {
                $insertStmt = $this->db->prepare(
                    "INSERT INTO counseling_sessions (case_id, session_date, session_type, summary, recorded_by)
                     VALUES (?, ?, 'closure', ?, ?)"
                );
                $insertStmt->execute([$caseId, date('Y-m-d'), $closureNote, $userId]);
            }

            return $this->successResponse([], 'Case closed successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postCounselingCaseClose');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function postBoardingNote(array $data, int $userId)
    {
        try {
            $studentId = !empty($data['student_id']) ? (int) $data['student_id'] : null;
            $noteType = !empty($data['note_type']) ? $data['note_type'] : 'general';
            $note = !empty($data['note']) ? $data['note'] : '';
            $visibility = !empty($data['visibility']) ? $data['visibility'] : 'boarding';
            $priority = !empty($data['priority']) ? $data['priority'] : 'medium';

            if (!$studentId || !$note) {
                return $this->errorResponse('Student ID and note content are required', 400);
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO student_boarding_notes (student_id, note_type, note, visibility, priority, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([$studentId, $noteType, $note, $visibility, $priority, $userId]);

            return $this->successResponse([], 'Boarding note added successfully');
        } catch (Exception $e) {
            $this->logError($e, 'StudentProfileManager::postBoardingNote');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }
}
