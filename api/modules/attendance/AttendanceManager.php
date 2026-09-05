<?php

namespace App\API\Modules\attendance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * AttendanceManager
 *
 * Owns every data read and write for the attendance module so
 * AttendanceController stays a thin endpoint exposer (no direct DB access,
 * no SQL text). Live-schema mapping is verified against KingsWayAcademy —
 * normalised targets only, never the legacy tables.
 *
 * Key normalised mappings:
 *   - `students.first_name / last_name`  → `persons`
 *   - `students.stream_id`               → `student_academic_enrollments`
 *     (`academic_year_class_stream_id` → `academic_year_class_streams`
 *     → `academic_year_classes` → `classes` + `streams`)
 *   - `student_attendance.student_id`    → `student_academic_enrollment_id`
 *   - `attendance_sessions.session_type` → `attendance_sessions.type`
 *   - `academic_terms`                   → `academic_year_terms` + `terms`
 *   - `school_calendar`                  → `academic_year_calendar_days` +
 *     `calendar_day_types`
 *   - `staff_class_assignments`          → `academic_year_class_streams.class_teacher_id`
 *   - `staff.user_id`                    → `users.person_id` ↔ `staff.person_id`
 *   - `staff.department_id`              → `staff_employment_profiles.department_id`
 *   - `staff_attendance.check_in_time`   → `check_in` (enum(leave,sick,off_day,...))
 *
 * Every endpoint returns a BaseAPI-style response array
 * (`successResponse` / `errorResponse`) that the controller converts via
 * `handleApiResponse()` so HTTP codes (400/403/404/500) are preserved.
 */
class AttendanceManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('attendance');
    }

    // ========================================================================
    // CURRENT USER / ROLE HELPERS
    // ========================================================================

    private function currentUserId(): int
    {
        $userId = $this->user_id ?: ($_SERVER['auth_user']['user_id'] ?? null);
        return $userId ? (int) $userId : 1;
    }

    public function getCurrentStaffId(): ?int
    {
        $userId = $this->user_id ?: ($_SERVER['auth_user']['user_id'] ?? null);
        if (!$userId) {
            $user = $this->getCurrentUser();
            $userId = $user['user_id'] ?? $user['id'] ?? null;
        }
        if (!$userId) {
            return null;
        }

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

    public function getCurrentAcademicYearId(): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM academic_years
             WHERE is_current = 1 OR status = 'active'
             ORDER BY is_current DESC, id DESC
             LIMIT 1"
        );
        $stmt->execute();
        $yearId = $stmt->fetchColumn();
        return $yearId ? (int) $yearId : null;
    }

    private function normalizeRoleName($roleName): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower((string) $roleName)), '_');
    }

    private function getCurrentRoleNames(): array
    {
        $roles = [];
        $user = $this->getCurrentUser() ?? [];

        if (!empty($user['role_names']) && is_array($user['role_names'])) {
            foreach ($user['role_names'] as $roleName) {
                if ($roleName) {
                    $roles[] = $this->normalizeRoleName($roleName);
                }
            }
        }

        if (!empty($user['roles']) && is_array($user['roles'])) {
            foreach ($user['roles'] as $role) {
                if (is_array($role) && !empty($role['name'])) {
                    $roles[] = $this->normalizeRoleName($role['name']);
                } elseif (is_string($role) && $role !== '') {
                    $roles[] = $this->normalizeRoleName($role);
                }
            }
        }

        return array_values(array_unique(array_filter($roles)));
    }

    private function userHasAnyRole(array $roleNames): bool
    {
        $currentRoles = $this->getCurrentRoleNames();
        if (empty($currentRoles)) {
            return false;
        }

        $normalizedTargets = array_map([$this, 'normalizeRoleName'], $roleNames);
        return count(array_intersect($currentRoles, $normalizedTargets)) > 0;
    }

    public function userCanManageAllAttendance(): bool
    {
        return $this->userHasAnyRole([
            'System Administrator',
            'Director',
            'School Administrator',
            'Headteacher',
            'Deputy Head - Academic',
            'Deputy Head - Discipline',
        ]);
    }

    public function userCanAccessBoardingAttendance(): bool
    {
        return $this->userCanManageAllAttendance()
            || $this->userHasAnyRole(['Boarding Master']);
    }

    private function canAccessDormitory(int $dormitoryId): bool
    {
        if ($this->userCanManageAllAttendance()) return true;
        $staffId = $this->getCurrentStaffId();
        if (!$staffId) return false;
        $stmt = $this->db->prepare("SELECT 1 FROM dormitories WHERE id = ? AND house_parent_id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$dormitoryId, $staffId]);
        return (bool) $stmt->fetchColumn();
    }

    // ========================================================================
    // SCOPE HELPERS
    // ========================================================================

    /**
     * Class scope for the current user.
     * Unrestricted for roles that can manage all attendance; otherwise the
     * class-teacher assignments for the current academic year.
     *
     * @return array{restricted:bool, staff_id:?int, class_ids:int[], stream_ids:int[]}
     */
    public function getAccessibleClassScope(): array
    {
        $scope = [
            'restricted' => !$this->userCanManageAllAttendance(),
            'staff_id' => $this->getCurrentStaffId(),
            'class_ids' => [],
            'stream_ids' => [],
        ];

        if (!$scope['restricted'] || !$scope['staff_id']) {
            return $scope;
        }

        $academicYearId = $this->getCurrentAcademicYearId();
        if (!$academicYearId) {
            return $scope;
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT v.academic_year_class_stream_id AS stream_id, ayc.class_id
             FROM vw_teacher_effective_stream_learning_areas v
             JOIN academic_year_class_streams aycs ON aycs.id = v.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE v.staff_id = ? AND v.academic_year_id = ?
               AND aycs.status IN ('planning','active')
               AND ayc.status IN ('planning','active')"
        );
        $stmt->execute([$scope['staff_id'], $academicYearId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scope['stream_ids'] = array_values(array_unique(array_map('intval', array_column($rows, 'stream_id'))));
        $scope['class_ids'] = array_values(array_unique(array_map('intval', array_column($rows, 'class_id'))));
        return $scope;
    }

    /**
     * Staff scope for the current user (staff-attribute based, no DB).
     *
     * @return array{restricted:bool, staff_id:?int, staff_ids:int[]}
     */
    public function getAccessibleStaffScope(): array
    {
        $canViewAll = $this->userHasAnyRole([
            'System Administrator',
            'Director',
            'School Administrator',
            'Headteacher',
            'Deputy Headteacher',
            'Human Resources Officer',
        ]);

        $staffId = $this->getCurrentStaffId();

        if ($canViewAll) {
            return ['restricted' => false, 'staff_id' => $staffId, 'staff_ids' => []];
        }

        return [
            'restricted' => true,
            'staff_id' => $staffId,
            'staff_ids' => $staffId ? [(int) $staffId] : [],
        ];
    }

    public function isStaffInScope(?int $staffId, array $scope): bool
    {
        if (!$staffId) {
            return false;
        }
        if (!$scope['restricted']) {
            return true;
        }
        return in_array((int) $staffId, $scope['staff_ids'], true);
    }

    /**
     * Build a stream-scope SQL fragment against the enrollments table alias.
     *
     * @return array{forbidden:bool, empty:bool, sql:string, params:int[]}
     */
    public function buildStreamScopeClause(?int $requestedStreamId, array $scope, string $column = 'en.academic_year_class_stream_id'): array
    {
        if ($requestedStreamId) {
            if ($scope['restricted'] && !in_array((int) $requestedStreamId, $scope['stream_ids'], true)) {
                return ['forbidden' => true, 'empty' => false, 'sql' => '', 'params' => []];
            }
            return [
                'forbidden' => false,
                'empty' => false,
                'sql' => " AND {$column} = ?",
                'params' => [(int) $requestedStreamId],
            ];
        }

        if (!$scope['restricted']) {
            return ['forbidden' => false, 'empty' => false, 'sql' => '', 'params' => []];
        }

        if (empty($scope['stream_ids'])) {
            return ['forbidden' => false, 'empty' => true, 'sql' => '', 'params' => []];
        }

        $placeholders = implode(',', array_fill(0, count($scope['stream_ids']), '?'));
        return [
            'forbidden' => false,
            'empty' => false,
            'sql' => " AND {$column} IN ({$placeholders})",
            'params' => array_map('intval', $scope['stream_ids']),
        ];
    }

    private function configurationTermId(array $data): ?int
    {
        $requested = $data['academic_year_term_id'] ?? $data['term_id'] ?? $_GET['academic_year_term_id'] ?? $_GET['term_id'] ?? null;
        if ($requested !== null && $requested !== '') {
            if (!is_numeric($requested) || (int) $requested < 1) return null;
            $stmt = $this->db->prepare('SELECT id FROM academic_year_terms WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $requested]);
            return $stmt->fetchColumn() ? (int) $requested : null;
        }
        $term = $this->resolveTermForDate((string) ($data['date'] ?? $_GET['date'] ?? date('Y-m-d')));
        return !empty($term['term_id']) ? (int) $term['term_id'] : null;
    }

    private function sessionAppliesToClassForTerm(int $sessionId, int $classId, ?int $termId): bool
    {
        if ($termId) {
            $termRules = $this->db->prepare(
                'SELECT class_id, enabled FROM attendance_session_term_class_rules WHERE academic_year_term_id = ? AND session_id = ?'
            );
            $termRules->execute([$termId, $sessionId]);
            $rules = $termRules->fetchAll(PDO::FETCH_ASSOC);
            if ($rules !== []) {
                foreach ($rules as $rule) {
                    if ((int) $rule['class_id'] === $classId) return (int) $rule['enabled'] === 1;
                }
                return false;
            }
        }
        $default = $this->db->prepare('SELECT enabled FROM attendance_session_class_rules WHERE session_id = ? AND class_id = ? LIMIT 1');
        $default->execute([$sessionId, $classId]);
        return (int) $default->fetchColumn() === 1;
    }

    /**
     * Stream scope expressed as an `en.student_id IN (subquery)` clause, used
     * where the query has no enrollments table in FROM (views, permission list).
     *
     * @return array{forbidden:bool, empty:bool, sql:string, params:int[]}
     */
    private function buildStudentStreamScope(?int $requestedStreamId, array $scope): array
    {
        $clause = $this->buildStreamScopeClause($requestedStreamId, $scope);
        if ($clause['forbidden'] || $clause['empty'] || $clause['sql'] === '') {
            return $clause;
        }

        $clause['sql'] = ' AND en.student_id IN (SELECT en.student_id FROM student_academic_enrollments en WHERE '
            . substr($clause['sql'], 5)
            . " AND en.enrollment_status = 'active')";
        return $clause;
    }

    // ========================================================================
    // CALENDAR / TERM HELPERS
    // ========================================================================

    private function calendarEntryForDate(string $date): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT cdt.code AS day_type, cdt.name AS day_type_name, acd.title,
                    cdt.affects_day_students, cdt.affects_boarders, cdt.requires_attendance
             FROM academic_year_calendar_days acd
             LEFT JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id
             WHERE acd.date = ?
             LIMIT 1"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function schoolWeekConfigForYear(?int $academicYearId): ?array
    {
        if (!$academicYearId) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT saturday_classes, sunday_boarding, class_days, boarding_days
             FROM school_week_config
             WHERE academic_year_id = ?
             LIMIT 1"
        );
        $stmt->execute([$academicYearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function termNumberFromCode(?string $code): ?int
    {
        if (!$code) {
            return null;
        }
        $num = (int) preg_replace('/\D+/', '', $code);
        return $num > 0 ? $num : null;
    }

    /**
     * Look up which academic_year_term a given date belongs to, falling back to
     * the current active term when the date is outside any term range.
     */
    public function resolveTermForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT ayt.id AS term_id, ayt.academic_year_id AS year_id, t.name AS term_name,
                    t.code AS term_code, ay.year_code
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE ayt.opening_date IS NOT NULL
               AND ayt.closing_date IS NOT NULL
               AND ? BETWEEN ayt.opening_date AND ayt.closing_date
             LIMIT 1"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['term_number'] = $this->termNumberFromCode($row['term_code']);
            return $row;
        }

        $stmt = $this->db->prepare(
            "SELECT ayt.id AS term_id, ayt.academic_year_id AS year_id, t.name AS term_name,
                    t.code AS term_code, ay.year_code
             FROM academic_year_terms ayt
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE ay.is_current = 1 AND ayt.status = 'current'
             LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $row['term_number'] = $this->termNumberFromCode($row['term_code']);
            return $row;
        }

        return [
            'term_id' => null,
            'year_id' => null,
            'term_name' => null,
            'term_number' => null,
            'year_code' => null,
        ];
    }

    /**
     * Teaching staff submit the live register only. Historical corrections
     * remain an administrative workflow; changing the browser date cannot
     * bypass this API guard.
     */
    private function validateTeachingAttendanceDate(string $date): ?array
    {
        $parsed = \DateTime::createFromFormat('!Y-m-d', $date);
        $valid = $parsed && $parsed->format('Y-m-d') === $date;
        if (!$valid) {
            return $this->errorResponse('Attendance date must be a valid calendar date.', 422);
        }

        $schoolToday = (new \DateTime('now', new \DateTimeZone('Africa/Nairobi')))->format('Y-m-d');
        if (!$this->userCanManageAllAttendance() && $date !== $schoolToday) {
            return $this->errorResponse('Teaching staff may mark attendance for today only.', 422);
        }
        return null;
    }

    // ========================================================================
    // DASHBOARD / STUDENT ENDPOINTS
    // ========================================================================

    /**
     * GET /api/attendance/today — today's combined student + staff counts.
     */
    public function getToday(array $data = [])
    {
        try {
            $today = date('Y-m-d');
            $stmt = $this->db->prepare(
                "(SELECT SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                         SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                         COUNT(*) AS total
                  FROM student_attendance
                  WHERE date = ?)
                 UNION ALL
                 (SELECT SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                         SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) AS absent,
                         COUNT(*) AS total
                  FROM staff_attendance
                  WHERE date = ?)"
            );
            $stmt->execute([$today, $today]);

            $studentRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['present' => 0, 'absent' => 0, 'total' => 0];
            $staffRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['present' => 0, 'absent' => 0, 'total' => 0];

            $present = (int) ($studentRow['present'] ?? 0) + (int) ($staffRow['present'] ?? 0);
            $absent = (int) ($studentRow['absent'] ?? 0) + (int) ($staffRow['absent'] ?? 0);
            $total = (int) ($studentRow['total'] ?? 0) + (int) ($staffRow['total'] ?? 0);
            $percentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

            return $this->successResponse([
                'present' => $present,
                'absent' => $absent,
                'total' => $total,
                'percentage' => (float) $percentage,
                'date' => $today,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Today attendance statistics');
        } catch (Exception $e) {
            $this->logError($e, 'getToday');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/today-attendance — today's student attendance percentage.
     */
    public function getTodayAttendance(array $data = [])
    {
        try {
            $today = date('Y-m-d');
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_students,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_students
                 FROM student_attendance
                 WHERE date = ?"
            );
            $stmt->execute([$today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalStudents = (int) ($row['total_students'] ?? 0);
            $presentStudents = (int) ($row['present_students'] ?? 0);
            $percentage = $totalStudents > 0 ? round(($presentStudents / $totalStudents) * 100, 1) : 0;

            return $this->successResponse([
                'total_students' => $totalStudents,
                'present_students' => $presentStudents,
                'attendance_percentage' => (float) $percentage,
                'date' => $today,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Student attendance statistics');
        } catch (Exception $e) {
            $this->logError($e, 'getTodayAttendance');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/student-percentage/{student_id}
     */
    public function getStudentPercentage($studentId = null, array $data = [])
    {
        try {
            $studentId = $studentId ?? $data['student_id'] ?? $_GET['student_id'] ?? null;
            if (!$studentId) {
                return $this->errorResponse('Student ID is required', 400);
            }

            $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;

            $sql = "SELECT COUNT(*) AS total_days,
                           SUM(CASE WHEN sa.status = 'present' THEN 1 ELSE 0 END) AS present_days
                    FROM student_attendance sa
                    JOIN student_academic_enrollments en ON en.id = sa.student_academic_enrollment_id
                    WHERE en.student_id = ? AND en.enrollment_status = 'active'";
            $params = [(int) $studentId];

            if ($termId) {
                $sql .= " AND sa.date BETWEEN (SELECT opening_date FROM academic_year_terms WHERE id = ?)
                                       AND (SELECT closing_date FROM academic_year_terms WHERE id = ?)";
                $params[] = (int) $termId;
                $params[] = (int) $termId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = (int) ($row['total_days'] ?? 0);
            $present = (int) ($row['present_days'] ?? 0);
            $percentage = $total > 0 ? round(100 * $present / $total, 2) : 0;

            return $this->successResponse([
                'student_id' => (int) $studentId,
                'total_days' => $total,
                'present_days' => $present,
                'percentage' => $percentage,
                'term_id' => $termId,
            ], 'Attendance percentage calculated');
        } catch (Exception $e) {
            $this->logError($e, 'getStudentPercentage');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/classes — classes (streams) for attendance dropdowns.
     */
    public function getClasses(array $data = [])
    {
        try {
            $scope = $this->userCanAccessBoardingAttendance()
                ? ['restricted' => false, 'staff_id' => $this->getCurrentStaffId(), 'class_ids' => [], 'stream_ids' => []]
                : $this->getAccessibleClassScope();

            if ($scope['restricted'] && empty($scope['stream_ids'])) {
                return $this->successResponse([], 'No classes assigned to the current user');
            }

            $academicYearId = $this->getCurrentAcademicYearId();
            $sql = "SELECT aycs.id AS stream_id, c.id, c.name, stm.name AS stream_name,
                           CONCAT(c.name,
                               CASE
                                   WHEN stm.name IS NULL OR stm.name = '' OR stm.name = c.name THEN ''
                                   ELSE CONCAT(' - ', stm.name)
                               END
                           ) AS display_name,
                           (SELECT COUNT(*) FROM student_academic_enrollments en2
                            WHERE en2.academic_year_class_stream_id = aycs.id
                              AND en2.enrollment_status = 'active') AS student_count
                    FROM academic_year_class_streams aycs
                    JOIN streams stm ON stm.id = aycs.stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    WHERE ayc.academic_year_id = ?
                      AND aycs.status IN ('planning','active')";
            $params = [$academicYearId];

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['stream_ids']), '?'));
                $sql .= " AND aycs.id IN ({$placeholders})";
                $params = array_merge($params, array_map('intval', $scope['stream_ids']));
            }

            $sql .= " ORDER BY c.id, stm.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Classes retrieved successfully');
        } catch (Exception $e) {
            $this->logError($e, 'getClasses');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/students-by-class/{stream_id}
     */
    public function getStudentsByClass(array $data = [])
    {
        try {
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            if (!$streamId) {
                return $this->errorResponse('Missing stream_id', 400);
            }

            $scope = $this->getAccessibleClassScope();
            if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) {
                return $this->errorResponse('You are not allowed to access this class attendance register', 403);
            }

            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;

            $sql = "SELECT s.id, s.admission_no, p.first_name, p.last_name,
                           st.name AS student_type, st.code AS student_type_code,
                           sa.id AS attendance_id, sa.status AS stored_status,
                           sa.absence_reason, sa.notes,
                           CASE
                               WHEN sa.absence_reason = 'permission' THEN 'permission'
                               ELSE sa.status
                           END AS attendance_status,
                           CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END AS has_permission,
                           spt.code AS permission_type_code,
                           spt.name AS permission_type,
                           sp.reason AS permission_reason
                    FROM student_academic_enrollments en
                    JOIN students s ON s.id = en.student_id
                    JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_types st ON st.id = s.student_type_id
                    LEFT JOIN student_attendance sa ON sa.student_academic_enrollment_id = en.id
                        AND sa.date = ? AND sa.register_type = 'class'
                        AND sa.session_id <=> ?
                    LEFT JOIN student_permissions sp ON sp.student_id = s.id
                        AND ? BETWEEN sp.start_date AND sp.end_date
                        AND sp.status = 'approved'
                    LEFT JOIN student_permission_types spt ON spt.id = sp.permission_type_id
                    WHERE en.academic_year_class_stream_id = ?
                      AND en.enrollment_status = 'active'
                    ORDER BY p.last_name, p.first_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$date, $sessionId ? (int) $sessionId : null, $date, (int) $streamId]);

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Students retrieved successfully');
        } catch (Exception $e) {
            $this->logError($e, 'getStudentsByClass');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/attendance/mark-bulk
     * Expects: { stream_id, date, session_id?, register_type?, attendance: [{ student_id, status }] }
     */
    public function postMarkBulk(array $data = [])
    {
        try {
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $sessionId = $data['session_id'] ?? null;
            $registerType = $data['register_type'] ?? 'class';

            if (!$streamId) {
                return $this->errorResponse('Missing stream_id', 400);
            }
            if (empty($attendance)) {
                return $this->errorResponse('No attendance data provided', 400);
            }
            if ($registerType === 'class' && empty($sessionId)) {
                return $this->errorResponse('A class attendance session is required.', 422);
            }
            if ($dateError = $this->validateTeachingAttendanceDate((string) $date)) {
                return $dateError;
            }

            // Class teachers may write only the active stream assigned to them.
            // Leadership roles remain unrestricted through the shared scope helper.
            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause((int) $streamId, $scope);
            if ($streamScope['forbidden'] || $streamScope['empty']) {
                return $this->errorResponse('You are not allowed to mark attendance for this class', 403);
            }

            $termRow = $this->resolveTermForDate($date);
            $termId = $termRow['term_id'] ?? null;
            $academicYearId = $termRow['year_id'] ?? null;

            $classStmt = $this->db->prepare(
                "SELECT ayc.class_id, c.name AS class_name
                 FROM academic_year_class_streams aycs
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 JOIN classes c ON c.id = ayc.class_id
                 WHERE aycs.id = ?
                 LIMIT 1"
            );
            $classStmt->execute([(int) $streamId]);
            $classRow = $classStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $classId = $classRow['class_id'] ?? null;

            if ($sessionId) {
                $sessionStmt = $this->db->prepare(
                    "SELECT ass.type,
                            COALESCE(cfg.status, ass.status) AS effective_status,
                            COALESCE(cfg.applicable_days, ass.applicable_days) AS effective_days
                     FROM attendance_sessions ass
                     LEFT JOIN attendance_session_term_configs cfg
                       ON cfg.session_id = ass.id AND cfg.academic_year_term_id = ?
                     WHERE ass.id = ?
                     LIMIT 1"
                );
                $dayName = date('l', strtotime((string) $date));
                $sessionStmt->execute([(int) $termId, (int) $sessionId]);
                $effectiveSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
                $effectiveDays = json_decode((string) ($effectiveSession['effective_days'] ?? '[]'), true) ?: [];
                if (!$effectiveSession
                    || ($effectiveSession['effective_status'] ?? 'inactive') !== 'active'
                    || !in_array($dayName, $effectiveDays, true)
                    || !$this->sessionAppliesToClassForTerm((int) $sessionId, (int) $classId, $termId ? (int) $termId : null)) {
                    return $this->errorResponse('This attendance session is not configured for the selected class and date.', 422);
                }
                $sessionType = $effectiveSession['type'];
                if ($registerType === 'class' && $sessionType !== 'academic') {
                    return $this->errorResponse('Class attendance requires an academic attendance session.', 422);
                }
                $registerType = $sessionType === 'boarding' ? 'boarding' : ($sessionType === 'activity' ? 'activity' : 'class');
            }

            $markedBy = $this->currentUserId();
            $created = 0;
            $updated = 0;

            foreach ($attendance as $record) {
                $status = strtolower(trim((string) ($record['status'] ?? 'not_marked')));
                if (!in_array($status, ['not_marked', 'present', 'absent', 'late'], true)) {
                    return $this->errorResponse('Invalid learner attendance status.', 422);
                }
            }

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                if (!$studentId) {
                    continue;
                }
                $status = strtolower(trim((string) ($record['status'] ?? 'not_marked')));
                // Not marked is intentionally represented by the absence of a
                // learner attendance row. It must never be persisted as Present.
                if ($status === 'not_marked') {
                    continue;
                }
                $notes = trim((string) ($record['note'] ?? $record['notes'] ?? ''));
                $notes = $notes === '' ? null : substr($notes, 0, 1000);

                $enrollStmt = $this->db->prepare(
                    "SELECT id FROM student_academic_enrollments
                     WHERE student_id = ? AND academic_year_class_stream_id = ? AND enrollment_status = 'active'
                     LIMIT 1"
                );
                $enrollStmt->execute([(int) $studentId, (int) $streamId]);
                $enrollmentId = $enrollStmt->fetchColumn();
                if (!$enrollmentId) {
                    continue;
                }

                $existStmt = $this->db->prepare(
                    "SELECT id FROM student_attendance
                     WHERE student_academic_enrollment_id = ? AND date = ? AND session_id <=> ? AND register_type = ?
                     LIMIT 1"
                );
                $existStmt->execute([(int) $enrollmentId, $date, $sessionId ? (int) $sessionId : null, $registerType]);
                $existing = $existStmt->fetchColumn();

                if ($existing) {
                    $upd = $this->db->prepare("UPDATE student_attendance SET status = ?, notes = ?, marked_by = ? WHERE id = ?");
                    $upd->execute([$status, $notes, $markedBy, (int) $existing]);
                    $updated++;
                } else {
                    $ins = $this->db->prepare(
                        "INSERT INTO student_attendance
                         (student_academic_enrollment_id, date, status, session_id, register_type, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $ins->execute([(int) $enrollmentId, $date, $status, $sessionId ? (int) $sessionId : null, $registerType, $notes, $markedBy]);
                    $created++;
                }
            }

            return $this->successResponse([
                'created' => $created,
                'updated' => $updated,
                'total' => $created + $updated,
                'date' => $date,
                'stream_id' => (int) $streamId,
                'term_id' => $termId,
                'academic_year_id' => $academicYearId,
                'register_type' => $registerType,
            ], 'Attendance marked successfully');
        } catch (Exception $e) {
            $this->logError($e, 'postMarkBulk');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/sessions — optionally filtered by type / applicable day.
     */
    public function getSessions(array $data = [])
    {
        try {
            $type = $data['type'] ?? $_GET['type'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $dayOfWeek = date('l', strtotime((string) $date));
            $term = $this->resolveTermForDate((string) $date);
            $termId = isset($term['term_id']) ? (int) $term['term_id'] : null;
            $classId = null;

            if ($streamId && $type === 'academic') {
                $scope = $this->getAccessibleClassScope();
                if ($scope['restricted'] && !in_array((int) $streamId, $scope['stream_ids'], true)) {
                    return $this->errorResponse('You are not allowed to access sessions for this class', 403);
                }
                $classStmt = $this->db->prepare(
                    "SELECT ayc.class_id
                     FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     WHERE aycs.id = ? LIMIT 1"
                );
                $classStmt->execute([(int) $streamId]);
                $classId = $classStmt->fetchColumn();
                if (!$classId) {
                    return $this->errorResponse('Class stream was not found', 404);
                }
            }

            $join = $termId
                ? 'LEFT JOIN attendance_session_term_configs cfg ON cfg.session_id = ass.id AND cfg.academic_year_term_id = ?'
                : 'LEFT JOIN attendance_session_term_configs cfg ON 1 = 0';
            $sql = "SELECT ass.*,
                           cfg.name AS term_name,
                           cfg.description AS term_description,
                           cfg.start_time AS term_start_time,
                           cfg.end_time AS term_end_time,
                           cfg.applicable_days AS term_applicable_days,
                           cfg.applies_to AS term_applies_to,
                           cfg.is_mandatory AS term_is_mandatory,
                           cfg.display_order AS term_display_order,
                           cfg.status AS term_status
                    FROM attendance_sessions ass
                    {$join}
                    ORDER BY COALESCE(cfg.display_order, ass.display_order), ass.id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($termId ? [$termId] : []);
            $sessions = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $session) {
                foreach (['name','description','start_time','end_time','applicable_days','applies_to','is_mandatory','display_order','status'] as $field) {
                    if ($session['term_' . $field] !== null) {
                        $session[$field] = $session['term_' . $field];
                    }
                    unset($session['term_' . $field]);
                }
                if (($session['status'] ?? 'inactive') !== 'active') continue;
                if ($type && ($session['type'] ?? null) !== $type) continue;
                $days = json_decode((string) ($session['applicable_days'] ?? '[]'), true) ?: [];
                if (!in_array($dayOfWeek, $days, true)) continue;
                if ($classId && !$this->sessionAppliesToClassForTerm((int) $session['id'], (int) $classId, $termId)) continue;
                $session['academic_year_term_id'] = $termId;
                $sessions[] = $session;
            }

            return $this->successResponse($sessions, 'Attendance sessions retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getSessions');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getSessionConfig(array $data = [])
    {
        try {
            if (!$this->userCanManageAllAttendance()) return $this->errorResponse('Only school leadership may configure attendance sessions', 403);
            $termId = $this->configurationTermId($data);
            if (!$termId) return $this->errorResponse('An academic year term is required for session configuration', 422);
            $stmt = $this->db->prepare(
                "SELECT ass.*, cfg.id AS term_config_id,
                        cfg.name AS term_name, cfg.description AS term_description,
                        cfg.start_time AS term_start_time, cfg.end_time AS term_end_time,
                        cfg.applicable_days AS term_applicable_days,
                        cfg.applies_to AS term_applies_to,
                        cfg.is_mandatory AS term_is_mandatory,
                        cfg.display_order AS term_display_order,
                        cfg.status AS term_status
                 FROM attendance_sessions ass
                 LEFT JOIN attendance_session_term_configs cfg
                   ON cfg.session_id = ass.id AND cfg.academic_year_term_id = ?
                 ORDER BY ass.type, COALESCE(cfg.display_order, ass.display_order), ass.id"
            );
            $stmt->execute([$termId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $termRules = $this->db->prepare(
                "SELECT class_id, enabled
                 FROM attendance_session_term_class_rules
                 WHERE academic_year_term_id = ? AND session_id = ?
                 ORDER BY class_id"
            );
            $defaultRules = $this->db->prepare(
                "SELECT class_id FROM attendance_session_class_rules
                 WHERE session_id = ? AND enabled = 1 ORDER BY class_id"
            );
            foreach ($rows as &$row) {
                foreach (['name','description','start_time','end_time','applicable_days','applies_to','is_mandatory','display_order','status'] as $field) {
                    if ($row['term_' . $field] !== null) $row[$field] = $row['term_' . $field];
                    unset($row['term_' . $field]);
                }
                $termRules->execute([$termId, (int) $row['id']]);
                $rules = $termRules->fetchAll(PDO::FETCH_ASSOC);
                if ($rules !== []) {
                    $row['class_ids'] = array_values(array_map('intval', array_column(array_filter($rules, static function (array $rule): bool {
                        return (int) $rule['enabled'] === 1;
                    }), 'class_id')));
                    $row['configuration_source'] = 'term_override';
                } else {
                    $defaultRules->execute([(int) $row['id']]);
                    $row['class_ids'] = array_map('intval', $defaultRules->fetchAll(PDO::FETCH_COLUMN));
                    $row['configuration_source'] = $row['term_config_id'] ? 'term_override' : 'school_default';
                }
                $row['academic_year_term_id'] = $termId;
                unset($row['term_config_id']);
            }
            return $this->successResponse(['academic_year_term_id' => $termId, 'sessions' => $rows], 'Attendance session configuration retrieved');
        } catch (Exception $e) { $this->logError($e, 'getSessionConfig'); return $this->errorResponse('An internal error occurred.', 500); }
    }

    public function putSessionConfig(int $sessionId, array $data = [])
    {
        try {
            if (!$this->userCanManageAllAttendance()) return $this->errorResponse('Only school leadership may configure attendance sessions', 403);
            if ($sessionId < 1) return $this->errorResponse('Session ID is required', 400);
            $termId = $this->configurationTermId($data);
            if (!$termId) return $this->errorResponse('An academic year term is required for session configuration', 422);
            $appliesTo = $data['applies_to'] ?? null;
            $days = array_values(array_intersect((array) ($data['applicable_days'] ?? []), ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']));
            if ($appliesTo !== null && !in_array($appliesTo, ['all','day_only','boarders_only'], true)) return $this->errorResponse('Invalid audience', 422);
            $existing = $this->db->prepare('SELECT type FROM attendance_sessions WHERE id=?'); $existing->execute([$sessionId]);
            $currentType = $existing->fetchColumn(); if (!$currentType) return $this->errorResponse('Attendance session not found', 404);
            if (isset($data['type']) && $data['type'] !== $currentType) return $this->errorResponse('Session type is a school definition and cannot change within one term', 422);

            $columns = ['academic_year_term_id', 'session_id', 'configured_by'];
            $values = [$termId, $sessionId, $this->currentUserId()];
            foreach (['name','description','start_time','end_time','applies_to','is_mandatory','display_order','status'] as $field) {
                if (array_key_exists($field, $data)) { $columns[] = $field; $values[] = $data[$field]; }
            }
            if (array_key_exists('applicable_days', $data)) { $columns[] = 'applicable_days'; $values[] = json_encode($days); }
            if (count($columns) === 3 && !array_key_exists('class_ids', $data)) return $this->errorResponse('No changes supplied', 400);

            $this->db->beginTransaction();
            if (count($columns) > 3) {
                $updates = array_map(static function (string $column): string {
                    return $column . '=VALUES(' . $column . ')';
                }, array_slice($columns, 3));
                $updates[] = 'configured_by=VALUES(configured_by)';
                $sql = 'INSERT INTO attendance_session_term_configs (' . implode(',', $columns) . ') VALUES ('
                    . implode(',', array_fill(0, count($columns), '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
                $this->db->prepare($sql)->execute($values);
            }
            if ($currentType === 'academic' && array_key_exists('class_ids', $data)) {
                $selected = array_values(array_unique(array_filter(array_map('intval', (array) $data['class_ids']))));
                $this->db->prepare('DELETE FROM attendance_session_term_class_rules WHERE academic_year_term_id=? AND session_id=?')->execute([$termId, $sessionId]);
                $insert = $this->db->prepare('INSERT INTO attendance_session_term_class_rules(academic_year_term_id,session_id,class_id,enabled,configured_by) VALUES(?,?,?,?,?)');
                $classIds = $this->db->query('SELECT id FROM classes ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($classIds as $classId) {
                    $insert->execute([$termId, $sessionId, (int) $classId, in_array((int) $classId, $selected, true) ? 1 : 0, $this->currentUserId()]);
                }
            }
            $this->db->commit(); return $this->successResponse(['id'=>$sessionId, 'academic_year_term_id'=>$termId], 'Term attendance session configuration saved');
        } catch (Exception $e) { if ($this->db->inTransaction()) $this->db->rollBack(); $this->logError($e, 'putSessionConfig'); return $this->errorResponse('Unable to save attendance session configuration', 500); }
    }

    /**
     * GET /api/attendance/session-attendance — register for one session + date.
     */
    public function getSessionAttendance(array $data = [])
    {
        try {
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;

            if (!$sessionId) {
                return $this->errorResponse('Session ID is required', 400);
            }
            if (!$streamId) {
                return $this->errorResponse('Stream ID is required for class attendance', 400);
            }

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->errorResponse('You are not allowed to access this class attendance register', 403);
            }
            if ($streamScope['empty']) {
                return $this->successResponse(['session' => null, 'date' => $date, 'students' => []], 'Session attendance retrieved');
            }

            $sql = "SELECT s.id, s.admission_no, p.first_name, p.last_name,
                           st.name AS student_type, st.code AS student_type_code,
                           sa.status AS stored_status,
                           sa.absence_reason,
                           CASE
                               WHEN sa.absence_reason = 'permission' THEN 'permission'
                               WHEN sa.status IS NOT NULL THEN sa.status
                               WHEN sp.id IS NOT NULL THEN 'permission'
                               ELSE NULL
                           END AS attendance_status,
                           sa.check_in_time, sa.notes,
                           CASE WHEN sp.id IS NULL THEN 0 ELSE 1 END AS has_permission,
                           sp.id AS permission_id, spt.name AS permission_type,
                           spt.code AS permission_type_code,
                           sp.reason AS permission_reason,
                           sp.start_date AS permission_start, sp.end_date AS permission_end
                    FROM student_academic_enrollments en
                    JOIN students s ON s.id = en.student_id
                    JOIN persons p ON p.id = s.person_id
                    JOIN student_types st ON st.id = s.student_type_id
                    LEFT JOIN student_attendance sa ON sa.student_academic_enrollment_id = en.id
                        AND sa.date = ? AND sa.session_id = ?
                    LEFT JOIN student_permissions sp ON s.id = sp.student_id
                        AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'
                    LEFT JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                    WHERE en.enrollment_status = 'active'";
            $params = [$date, (int) $sessionId, $date];

            $sql .= $streamScope['sql'];
            $params = array_merge($params, $streamScope['params']);

            $sql .= " ORDER BY p.last_name, p.first_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sessStmt = $this->db->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
            $sessStmt->execute([(int) $sessionId]);
            $session = $sessStmt->fetch(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'session' => $session,
                'date' => $date,
                'students' => $students,
            ], 'Session attendance retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getSessionAttendance');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/attendance/mark-session
     * Expects: { session_id, stream_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkSession(array $data = [])
    {
        try {
            $sessionId = $data['session_id'] ?? null;
            $streamId = $data['stream_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy = $this->currentUserId();

            if (!$sessionId) {
                return $this->errorResponse('Session ID is required', 400);
            }
            if (empty($attendance)) {
                return $this->errorResponse('No attendance data provided', 400);
            }
            if ($dateError = $this->validateTeachingAttendanceDate((string) $date)) {
                return $dateError;
            }

            $termRow = $this->resolveTermForDate($date);
            $termId = $termRow['term_id'] ?? null;
            $academicYearId = $termRow['year_id'] ?? null;

            $registerType = 'class';
            if ($sessionId) {
                $sessStmt = $this->db->prepare("SELECT type FROM attendance_sessions WHERE id = ? LIMIT 1");
                $sessStmt->execute([(int) $sessionId]);
                $sessType = $sessStmt->fetchColumn();
                if ($sessType) {
                    $registerType = $sessType === 'boarding' ? 'boarding' : ($sessType === 'activity' ? 'activity' : 'class');
                }
            }

            $scope = $this->getAccessibleClassScope();
            if ($streamId) {
                $streamScope = $this->buildStreamScopeClause((int) $streamId, $scope);
                if ($streamScope['forbidden'] || $streamScope['empty']) {
                    return $this->errorResponse('You are not allowed to mark attendance for this class', 403);
                }
            }

            $classId = null;
            if ($streamId) {
                $classStmt = $this->db->prepare(
                    "SELECT ayc.class_id
                     FROM academic_year_class_streams aycs
                     JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                     WHERE aycs.id = ?
                     LIMIT 1"
                );
                $classStmt->execute([(int) $streamId]);
                $classId = $classStmt->fetchColumn();
            }

            $created = 0;
            $updated = 0;
            $excused = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $requestedStatus = strtolower((string) ($record['status'] ?? 'present'));
                $notes = $record['notes'] ?? null;

                if (!$studentId) {
                    continue;
                }

                if (!in_array($requestedStatus, ['present', 'absent', 'late', 'permission'], true)) {
                    $requestedStatus = 'present';
                }

                $permStmt = $this->db->prepare(
                    "SELECT id FROM student_permissions
                     WHERE student_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'
                     LIMIT 1"
                );
                $permStmt->execute([(int) $studentId, $date]);
                $permissionId = $permStmt->fetchColumn() ?: null;

                $status = $requestedStatus === 'permission' ? 'absent' : $requestedStatus;
                $absenceReason = null;
                if ($status === 'absent') {
                    if ($permissionId || $requestedStatus === 'permission') {
                        $absenceReason = 'permission';
                        if ($permissionId) {
                            $excused++;
                        }
                    } else {
                        $absenceReason = 'unexcused';
                    }
                }

                $enrollStmt = $this->db->prepare(
                    "SELECT id FROM student_academic_enrollments
                     WHERE student_id = ? AND academic_year_class_stream_id = ? AND enrollment_status = 'active'
                     LIMIT 1"
                );
                $enrollStmt->execute([(int) $studentId, (int) $streamId]);
                $enrollmentId = $enrollStmt->fetchColumn();
                if (!$enrollmentId) {
                    continue;
                }

                $existStmt = $this->db->prepare(
                    "SELECT id FROM student_attendance
                     WHERE student_academic_enrollment_id = ? AND date = ? AND session_id = ? AND register_type = ?
                     LIMIT 1"
                );
                $existStmt->execute([(int) $enrollmentId, $date, (int) $sessionId, $registerType]);
                $existing = $existStmt->fetchColumn();

                if ($existing) {
                    $upd = $this->db->prepare(
                        "UPDATE student_attendance
                         SET status = ?, absence_reason = ?, permission_id = ?, notes = ?, check_in_time = CURTIME()
                         WHERE id = ?"
                    );
                    $upd->execute([$status, $absenceReason, $permissionId, $notes, (int) $existing]);
                    $updated++;
                } else {
                    $ins = $this->db->prepare(
                        "INSERT INTO student_attendance
                         (student_academic_enrollment_id, date, status, session_id, register_type, check_in_time,
                          absence_reason, permission_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, CURTIME(), ?, ?, ?, ?, NOW())"
                    );
                    $ins->execute([
                        (int) $enrollmentId, $date, $status, (int) $sessionId, $registerType,
                        $absenceReason, $permissionId, $notes, $markedBy,
                    ]);
                    $created++;
                }
            }

            // Keep the register state in sync for the teacher immediately.
            // Reminders/escalations are still handled by the scheduled worker.
            $registerService = new \App\API\Services\AttendanceRegisterService($this->db);
            $registerService->reconcileForMarking((int) $streamId, (int) $sessionId, $date);

            return $this->successResponse([
                'created' => $created,
                'updated' => $updated,
                'excused' => $excused,
                'total' => $created + $updated,
                'session_id' => (int) $sessionId,
                'date' => $date,
            ], 'Session attendance marked successfully');
        } catch (Exception $e) {
            $this->logError($e, 'postMarkSession');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/academic-summary — aggregated learner attendance report.
     */
    public function getAcademicSummary(array $data = [])
    {
        try {
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $statusFilter = $data['status'] ?? $_GET['status'] ?? null;

            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStudentStreamScope($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->errorResponse('You are not allowed to access attendance for this class', 403);
            }
            if ($streamScope['empty']) {
                return $this->successResponse(
                    $this->buildEmptyAcademicSummary($dateFrom, $dateTo, $streamId ? (int) $streamId : null),
                    'Academic attendance summary retrieved'
                );
            }

            $sql = "SELECT
                        student_id,
                        student_name,
                        admission_no,
                        class_name,
                        student_type,
                        COUNT(*) AS total_days,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                        SUM(CASE WHEN status = 'absent' AND COALESCE(absence_reason, 'unexcused') <> 'permission' THEN 1 ELSE 0 END) AS absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                        SUM(CASE WHEN absence_reason = 'permission' THEN 1 ELSE 0 END) AS permission,
                        MAX(CASE WHEN status = 'absent' OR absence_reason = 'permission' THEN date END) AS last_absent_date
                    FROM vw_student_attendance_summary
                    WHERE date BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo];

            $sql .= $streamScope['sql'];
            $params = array_merge($params, $streamScope['params']);

            if ($sessionId) {
                $sql .= " AND student_id IN (
                    SELECT DISTINCT en.student_id
                    FROM student_attendance sa2
                    JOIN student_academic_enrollments en ON en.id = sa2.student_academic_enrollment_id
                    WHERE sa2.date BETWEEN ? AND ? AND sa2.session_id = ?
                )";
                $params[] = $dateFrom;
                $params[] = $dateTo;
                $params[] = (int) $sessionId;
            }

            $sql .= " GROUP BY student_id, student_name, admission_no, class_name, student_type
                      ORDER BY class_name, student_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $students = array_map(static function (array $row): array {
                $row['student_id'] = (int) $row['student_id'];
                $row['total_days'] = (int) ($row['total_days'] ?? 0);
                $row['present'] = (int) ($row['present'] ?? 0);
                $row['absent'] = (int) ($row['absent'] ?? 0);
                $row['late'] = (int) ($row['late'] ?? 0);
                $row['permission'] = (int) ($row['permission'] ?? 0);
                $row['attendance_percentage'] = $row['total_days'] > 0
                    ? round(($row['present'] / $row['total_days']) * 100, 1)
                    : 0;
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            $students = $this->applyAcademicStatusFilter($students, $statusFilter);
            $summary = $this->summarizeAcademicRows($students);

            $trendSql = "SELECT
                            date,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present,
                            SUM(CASE WHEN status = 'absent' AND COALESCE(absence_reason, 'unexcused') <> 'permission' THEN 1 ELSE 0 END) AS absent,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) AS late,
                            SUM(CASE WHEN absence_reason = 'permission' THEN 1 ELSE 0 END) AS permission,
                            COUNT(*) AS total
                         FROM vw_student_attendance_summary
                         WHERE date BETWEEN ? AND ?";
            $trendParams = [$dateFrom, $dateTo];

            $trendSql .= $streamScope['sql'];
            $trendParams = array_merge($trendParams, $streamScope['params']);

            if ($sessionId) {
                $trendSql .= " AND student_id IN (
                    SELECT DISTINCT en.student_id
                    FROM student_attendance sa2
                    JOIN student_academic_enrollments en ON en.id = sa2.student_academic_enrollment_id
                    WHERE sa2.date BETWEEN ? AND ? AND sa2.session_id = ?
                )";
                $trendParams[] = $dateFrom;
                $trendParams[] = $dateTo;
                $trendParams[] = (int) $sessionId;
            }

            $trendSql .= " GROUP BY date ORDER BY date ASC";
            $trendStmt = $this->db->prepare($trendSql);
            $trendStmt->execute($trendParams);

            $trend = array_map(static function (array $row): array {
                return [
                    'date' => $row['date'],
                    'present' => (int) ($row['present'] ?? 0),
                    'absent' => (int) ($row['absent'] ?? 0),
                    'late' => (int) ($row['late'] ?? 0),
                    'permission' => (int) ($row['permission'] ?? 0),
                    'total' => (int) ($row['total'] ?? 0),
                ];
            }, $trendStmt->fetchAll(PDO::FETCH_ASSOC));

            $lowAttendance = array_values(array_map(static function (array $student): array {
                return [
                    'student_id' => $student['student_id'],
                    'student_name' => $student['student_name'],
                    'admission_no' => $student['admission_no'],
                    'attendance_percentage' => $student['attendance_percentage'],
                    'absent_days' => $student['absent'] + $student['permission'],
                    'last_absent_date' => $student['last_absent_date'] ?? null,
                ];
            }, array_filter($students, static function (array $student): bool {
                return ($student['total_days'] ?? 0) > 0 && ($student['attendance_percentage'] ?? 0) < 80;
            })));

            return $this->successResponse([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'stream_id' => $streamId ? (int) $streamId : null,
                'students' => $students,
                'summary' => $summary,
                'trend' => $trend,
                'low_attendance' => $lowAttendance,
            ], 'Academic attendance summary retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getAcademicSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/daily-register — raw attendance rows for a day/session.
     */
    public function getDailyRegister(array $data = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStreamScopeClause($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->errorResponse('You are not allowed to access attendance for this class', 403);
            }
            if ($streamScope['empty']) {
                return $this->successResponse([], 'Daily register retrieved');
            }

            $attendanceJoin = "LEFT JOIN student_attendance sa
                                  ON sa.student_academic_enrollment_id = en.id
                                 AND sa.date = ?
                                 AND sa.register_type = 'class'";
            $params = [$date];
            if ($sessionId) {
                $attendanceJoin .= " AND sa.session_id = ?";
                $params[] = (int) $sessionId;
            } else {
                // The class-history page normally supplies a session. The
                // fallback still returns one learner row, using the latest
                // class register entry for that day when one exists.
                $attendanceJoin .= " AND sa.id = (
                    SELECT MAX(sa_latest.id)
                    FROM student_attendance sa_latest
                    WHERE sa_latest.student_academic_enrollment_id = en.id
                      AND sa_latest.date = ?
                      AND sa_latest.register_type = 'class'
                )";
                $params[] = $date;
            }

            $sql = "SELECT
                        sa.id,
                        en.student_id,
                        ? AS date,
                        s.admission_no,
                        p.first_name,
                        p.last_name,
                        c.name AS class_name,
                        stm.name AS stream_name,
                        st.name AS student_type,
                        st.code AS student_type_code,
                        ass.name AS session_name,
                        CASE
                            WHEN sa.absence_reason = 'permission' THEN 'permission'
                            ELSE COALESCE(sa.status, 'not_marked')
                        END AS status,
                        sa.status AS stored_status,
                        sa.absence_reason,
                        CASE
                            WHEN sa.id IS NULL THEN NULL
                            ELSE COALESCE(sa.updated_at, sa.created_at)
                        END AS marked_at,
                        sa.notes
                    FROM student_academic_enrollments en
                    JOIN students s ON s.id = en.student_id
                    JOIN persons p ON p.id = s.person_id
                    JOIN academic_year_class_streams aycs ON aycs.id = en.academic_year_class_stream_id
                    JOIN streams stm ON stm.id = aycs.stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN student_types st ON st.id = s.student_type_id
                    {$attendanceJoin}
                    LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
                    WHERE s.status = 'active'
                      AND en.enrollment_status IN ('active','completed','transferred','graduated')
                      AND (en.enrolled_on IS NULL OR en.enrolled_on <= ?)";
            array_unshift($params, $date);
            $params[] = $date;

            $sql .= $streamScope['sql'];
            $params = array_merge($params, $streamScope['params']);

            $sql .= " ORDER BY c.id, stm.name, p.last_name, p.first_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Daily register retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getDailyRegister');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/register-range
     * Attendance register for one or more class streams over a date range.
     *
     * stream_id may be ommitted to cover every class stream the authenticated
     * user is allowed to access (the client's default "All My Classes" scope).
     * Returns the roster plus, for each learner, the effective class register
     * status per date. When multiple class register records exist for one
     * learner on one day (multiple class sessions), the latest entry wins — the
     * same rule getDailyRegister applies when it is called without a session.
     * Weekends/holidays carry no record and are shown by the client as
     * 'not_marked'.
     *
     * Params: stream_id? (one id, or omit for all accessible), from (Y-m-d),
     * to (Y-m-d), session_id?
     */
    public function getRegisterRange(array $data = [])
    {
        try {
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $from = $data['from'] ?? $_GET['from'] ?? null;
            $to   = $data['to'] ?? $_GET['to'] ?? null;
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;
            if ($streamId === '' || $streamId === '0') {
                $streamId = null;
            }
            if ($streamId !== null && (!is_numeric($streamId) || (int) $streamId < 1)) {
                return $this->errorResponse('Invalid stream_id', 400);
            }

            foreach (['from' => $from, 'to' => $to] as $label => $value) {
                if ($value !== null && $value !== '') {
                    $parsed = date_parse((string) $value);
                    if (!empty($parsed['error_count']) || !empty($parsed['warning_count'])) {
                        return $this->errorResponse("Invalid {$label} date", 400);
                    }
                }
            }

            $from = $from ? date('Y-m-d', strtotime((string) $from)) : date('Y-m-d', strtotime('-29 days'));
            $to   = $to   ? date('Y-m-d', strtotime((string) $to))   : date('Y-m-d');
            if ($from > $to) {
                return $this->errorResponse('The start date must be on or before the end date', 400);
            }

            $daySpan = (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1;
            if ($daySpan > 370) {
                return $this->errorResponse('The date range is too wide. Choose a range of 12 months or less.', 400);
            }

            $scope = $this->getAccessibleClassScope();
            $scopeStreamId = $streamId ? (int) $streamId : null;

            // Class scope: honours 'all accessible' (null) with the same rules
            // as the roster scope is valid (forbidden / empty).
            $classScope = $this->buildStreamScopeClause($scopeStreamId, $scope, 'aycs.id');
            if ($classScope['forbidden']) {
                return $this->errorResponse('You are not allowed to access attendance for this class', 403);
            }
            if ($classScope['empty']) {
                return $this->successResponse(['dates' => [], 'rows' => [], 'classes' => [], 'class' => null, 'from' => $from, 'to' => $to], 'Attendance register retrieved');
            }

            // Stream identities covered by the register (one row per stream).
            $classesSql = "SELECT aycs.id AS stream_id, c.name AS class_name, stm.name AS stream_name,
                          CONCAT(c.name,
                              CASE
                                  WHEN stm.name IS NULL OR stm.name = '' OR stm.name = c.name THEN ''
                                  ELSE CONCAT(' - ', stm.name)
                              END
                          ) AS display_name
                   FROM academic_year_class_streams aycs
                   JOIN streams stm ON stm.id = aycs.stream_id
                   JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                   JOIN classes c ON c.id = ayc.class_id
                   WHERE 1=1";
            $classesSql .= $classScope['sql'];
            $classesSql .= " ORDER BY c.name, stm.name";
            $classesStmt = $this->db->prepare($classesSql);
            $classesStmt->execute($classScope['params']);
            $classes = $classesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($classes === []) {
                return $this->successResponse(['dates' => [], 'rows' => [], 'classes' => [], 'class' => null, 'from' => $from, 'to' => $to], 'Attendance register retrieved');
            }

            $combinedName = count($classes) === 1
                ? $classes[0]['display_name']
                : 'All Classes (' . count($classes) . ')';

            // Calendar days in the range (every date; the client renders them all).
            $dates = [];
            $cursor = strtotime($from);
            $end = strtotime($to);
            while ($cursor <= $end) {
                $dates[] = [
                    'date' => date('Y-m-d', $cursor),
                    'weekday' => date('w', $cursor),
                    'day_name' => date('l', $cursor),
                    'label' => date('D j M', $cursor),
                ];
                $cursor = strtotime('+1 day', $cursor);
            }

            // Roster: learners who were part of the covered streams by the end of range.
            $rosterSql = "SELECT en.id AS enrollment_id, en.student_id,
                                 en.academic_year_class_stream_id AS stream_id,
                                 s.admission_no, p.first_name, p.last_name,
                                 c.name AS class_name, stm.name AS stream_name,
                                 CONCAT(c.name,
                                     CASE
                                         WHEN stm.name IS NULL OR stm.name = '' OR stm.name = c.name THEN ''
                                         ELSE CONCAT(' - ', stm.name)
                                     END
                                 ) AS display_name,
                                 st.name AS student_type, st.code AS student_type_code
                          FROM student_academic_enrollments en
                          JOIN students s ON s.id = en.student_id
                          JOIN persons p ON p.id = s.person_id
                          LEFT JOIN student_types st ON st.id = s.student_type_id
                          LEFT JOIN academic_year_class_streams aycs ON aycs.id = en.academic_year_class_stream_id
                          LEFT JOIN streams stm ON stm.id = aycs.stream_id
                          LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                          LEFT JOIN classes c ON c.id = ayc.class_id
                          WHERE s.status = 'active'
                            AND en.enrollment_status IN ('active','completed','transferred','graduated')
                            AND (en.enrolled_on IS NULL OR en.enrolled_on <= ?)";
            $rosterParams = [$to];
            $rosterScope = $this->buildStreamScopeClause($scopeStreamId, $scope);
            $rosterSql .= $rosterScope['sql'];
            $rosterParams = array_merge($rosterParams, $rosterScope['params']);
            $rosterSql .= " ORDER BY c.name, stm.name, p.last_name, p.first_name";

            $stmt = $this->db->prepare($rosterSql);
            $stmt->execute($rosterParams);
            $roster = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Attendance records for the roster within the range.
            $days = [];
            if ($roster !== []) {
                $enrollmentIds = array_map('intval', array_column($roster, 'enrollment_id'));
                $placeholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
                $attSql = "SELECT sa.id, sa.student_academic_enrollment_id, sa.date,
                                  sa.status, sa.absence_reason
                           FROM student_attendance sa
                           WHERE sa.register_type = 'class'
                             AND sa.date BETWEEN ? AND ?
                             AND sa.student_academic_enrollment_id IN ({$placeholders})";
                $attParams = array_merge([$from, $to], $enrollmentIds);
                if ($sessionId) {
                    $attSql .= " AND sa.session_id = ?";
                    $attParams[] = (int) $sessionId;
                }
                $attSql .= " ORDER BY sa.student_academic_enrollment_id, sa.date, sa.id ASC";
                $attStmt = $this->db->prepare($attSql);
                $attStmt->execute($attParams);
                $records = $attStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($records as $record) {
                    $enrollmentId = (int) $record['student_academic_enrollment_id'];
                    if (!isset($days[$enrollmentId])) {
                        $days[$enrollmentId] = [];
                    }
                    $statusKey = strcasecmp((string) $record['absence_reason'], 'permission') === 0
                        ? 'permission'
                        : ((string) $record['status'] !== '' ? (string) $record['status'] : 'not_marked');
                    // Latest entry wins (ORDER BY id ASC, so overwrite).
                    $days[$enrollmentId][(string) $record['date']] = $statusKey;
                }
            }

            $rows = [];
            foreach ($roster as $learner) {
                $enrollmentId = (int) $learner['enrollment_id'];
                $rows[] = [
                    'student_id' => (int) $learner['student_id'],
                    'stream_id' => isset($learner['stream_id']) ? (int) $learner['stream_id'] : null,
                    'admission_no' => $learner['admission_no'],
                    'first_name' => $learner['first_name'],
                    'last_name' => $learner['last_name'],
                    'learner_name' => trim(($learner['first_name'] ?? '') . ' ' . ($learner['last_name'] ?? '')),
                    'class_name' => $learner['class_name'],
                    'stream_name' => $learner['stream_name'],
                    'display_name' => $learner['display_name'],
                    'student_type' => $learner['student_type'],
                    'student_type_code' => $learner['student_type_code'],
                    'days' => $days[$enrollmentId] ?? [],
                ];
            }

            return $this->successResponse([
                'dates' => $dates,
                'rows' => $rows,
                'classes' => $classes,
                'class' => ['stream_id' => $streamId ? (int) $streamId : null, 'display_name' => $combinedName],
                'from' => $from,
                'to' => $to,
            ], 'Attendance register retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getRegisterRange');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // BOARDING ATTENDANCE ENDPOINTS
    // ========================================================================

    /**
     * GET /api/attendance/dormitories
     */
    public function getDormitories(array $data = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->errorResponse('You are not allowed to access boarding attendance', 403);
            }

            $sql = "SELECT d.*,
                           CONCAT(hp_person.first_name, ' ', hp_person.last_name) AS house_parent_name,
                           (SELECT COUNT(*) FROM dormitory_assignments da
                            WHERE da.dormitory_id = d.id AND da.status = 'active') AS student_count
                    FROM dormitories d
                    LEFT JOIN staff hp ON d.house_parent_id = hp.id
                    LEFT JOIN persons hp_person ON hp_person.id = hp.person_id
                    WHERE d.status = 'active'" . ($this->userCanManageAllAttendance() ? "" : " AND d.house_parent_id = " . (int) $this->getCurrentStaffId()) . "
                    ORDER BY d.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Dormitories retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getDormitories');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/dormitory-students — roll call roster for a dormitory.
     */
    public function getDormitoryStudents(array $data = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->errorResponse('You are not allowed to access boarding attendance', 403);
            }

            $dormitoryId = $data['dormitory_id'] ?? $_GET['dormitory_id'] ?? null;
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;

            if (!$dormitoryId) {
                return $this->errorResponse('Dormitory ID is required', 400);
            }
            if (!$this->canAccessDormitory((int) $dormitoryId)) {
                return $this->errorResponse('You are not assigned to this dormitory', 403);
            }

            $sql = "SELECT s.id, s.admission_no, p.first_name, p.last_name,
                           c.name AS class_name, da.bed_number,
                           ba.status AS current_status, ba.check_time, ba.notes,
                           sp.id AS permission_id, spt.name AS permission_type,
                           sp.end_date AS permission_until
                    FROM dormitory_assignments da
                    JOIN student_academic_enrollments en ON en.id = da.student_academic_enrollment_id
                    JOIN students s ON s.id = en.student_id
                    JOIN persons p ON p.id = s.person_id
                    JOIN academic_year_class_streams aycs ON aycs.id = en.academic_year_class_stream_id
                    JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN boarding_attendance ba ON s.id = ba.student_id
                        AND ba.date = ? AND ba.dormitory_id = ?";
            $params = [$date, (int) $dormitoryId];

            if ($sessionId) {
                $sql .= " AND ba.session_id = ?";
                $params[] = $sessionId;
            }

            $sql .= " LEFT JOIN student_permissions sp ON s.id = sp.student_id
                            AND ? BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'
                      LEFT JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                      WHERE da.dormitory_id = ? AND da.status = 'active' AND en.enrollment_status = 'active'
                      ORDER BY p.last_name, p.first_name";
            $params[] = $date;
            $params[] = (int) $dormitoryId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $dormStmt = $this->db->prepare(
                "SELECT d.*, CONCAT(hp_person.first_name, ' ', hp_person.last_name) AS house_parent_name
                 FROM dormitories d
                 LEFT JOIN staff hp ON d.house_parent_id = hp.id
                 LEFT JOIN persons hp_person ON hp_person.id = hp.person_id
                 WHERE d.id = ?"
            );
            $dormStmt->execute([(int) $dormitoryId]);
            $dormitory = $dormStmt->fetch(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'dormitory' => $dormitory,
                'date' => $date,
                'session_id' => $sessionId,
                'students' => $students,
            ], 'Dormitory students retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getDormitoryStudents');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/attendance/mark-boarding
     * Expects: { dormitory_id, session_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkBoarding(array $data = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->errorResponse('You are not allowed to mark boarding attendance', 403);
            }

            $dormitoryId = $data['dormitory_id'] ?? null;
            $sessionId = $data['session_id'] ?? null;
            $date = $data['date'] ?? date('Y-m-d');
            $attendance = $data['attendance'] ?? [];
            $markedBy = $this->currentUserId();

            if (!$dormitoryId || !$sessionId) {
                return $this->errorResponse('Dormitory ID and Session ID are required', 400);
            }
            if ($date !== date('Y-m-d')) {
                return $this->errorResponse('Boarding attendance can only be marked for today', 422);
            }
            if (!$this->canAccessDormitory((int) $dormitoryId)) {
                return $this->errorResponse('You are not assigned to this dormitory', 403);
            }
            if (empty($attendance)) {
                return $this->errorResponse('No attendance data provided', 400);
            }

            $created = 0;
            $updated = 0;
            $onPermission = 0;

            foreach ($attendance as $record) {
                $studentId = $record['student_id'] ?? null;
                $status = $record['status'] ?? 'present';
                $notes = $record['notes'] ?? null;

                if (!$studentId) {
                    continue;
                }

                if (!in_array($status, ['present', 'absent', 'permission', 'sick_bay', 'unknown'], true)) {
                    $status = 'present';
                }

                $permStmt = $this->db->prepare(
                    "SELECT id FROM student_permissions
                     WHERE student_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'
                     LIMIT 1"
                );
                $permStmt->execute([(int) $studentId, $date]);
                $permissionId = $permStmt->fetchColumn() ?: null;

                if ($permissionId && $status !== 'present') {
                    $status = 'permission';
                    $onPermission++;
                }

                $existStmt = $this->db->prepare(
                    "SELECT id FROM boarding_attendance
                     WHERE student_id = ? AND date = ? AND session_id = ?
                     LIMIT 1"
                );
                $existStmt->execute([(int) $studentId, $date, (int) $sessionId]);
                $existing = $existStmt->fetchColumn();

                if ($existing) {
                    $upd = $this->db->prepare(
                        "UPDATE boarding_attendance
                         SET status = ?, check_time = CURTIME(), permission_id = ?, notes = ?, marked_by = ?
                         WHERE id = ?"
                    );
                    $upd->execute([$status, $permissionId, $notes, $markedBy, (int) $existing]);
                    $updated++;
                } else {
                    $ins = $this->db->prepare(
                        "INSERT INTO boarding_attendance
                         (student_id, dormitory_id, date, session_id, status, check_time,
                          permission_id, notes, marked_by, created_at)
                         VALUES (?, ?, ?, ?, ?, CURTIME(), ?, ?, ?, NOW())"
                    );
                    $ins->execute([(int) $studentId, (int) $dormitoryId, $date, (int) $sessionId, $status, $permissionId, $notes, $markedBy]);
                    $created++;
                }
            }

            return $this->successResponse([
                'created' => $created,
                'updated' => $updated,
                'on_permission' => $onPermission,
                'total' => $created + $updated,
                'dormitory_id' => (int) $dormitoryId,
                'session_id' => (int) $sessionId,
                'date' => $date,
            ], 'Boarding attendance marked successfully');
        } catch (Exception $e) {
            $this->logError($e, 'postMarkBoarding');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/boarding-summary — per-dormitory roll call status for a date.
     */
    public function getBoardingSummary(array $data = [])
    {
        try {
            if (!$this->userCanAccessBoardingAttendance()) {
                return $this->errorResponse('You are not allowed to access boarding attendance', 403);
            }

            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $sql = "SELECT
                        d.id AS dormitory_id, d.name AS dormitory_name, d.code,
                        CONCAT_WS(' ', hp.first_name, hp.last_name) AS responsible_name,
                        ass.id AS session_id, ass.name AS session_name, ass.code AS session_code,
                        COUNT(DISTINCT da.student_academic_enrollment_id) AS total_students,
                        SUM(CASE WHEN ba.status = 'present' THEN 1 ELSE 0 END) AS present,
                        SUM(CASE WHEN ba.status = 'absent' THEN 1 ELSE 0 END) AS absent,
                        SUM(CASE WHEN ba.status = 'permission' THEN 1 ELSE 0 END) AS on_permission,
                        SUM(CASE WHEN ba.status = 'sick_bay' THEN 1 ELSE 0 END) AS sick_bay
                    FROM dormitories d
                    LEFT JOIN staff hp_staff ON hp_staff.id = d.house_parent_id
                    LEFT JOIN persons hp ON hp.id = hp_staff.person_id
                    LEFT JOIN dormitory_assignments da ON d.id = da.dormitory_id AND da.status = 'active'
                    CROSS JOIN attendance_sessions ass
                    LEFT JOIN student_academic_enrollments en ON en.id = da.student_academic_enrollment_id
                    LEFT JOIN boarding_attendance ba ON ba.student_id = en.student_id
                        AND ba.date = ? AND ba.session_id = ass.id AND ba.dormitory_id = d.id
                    WHERE d.status = 'active' AND ass.type = 'boarding' AND ass.status = 'active'" . ($this->userCanManageAllAttendance() ? "" : " AND d.house_parent_id = " . (int) $this->getCurrentStaffId()) . "
                    GROUP BY d.id, d.name, d.code, hp.first_name, hp.last_name, ass.id, ass.name, ass.code
                    ORDER BY d.name, ass.display_order";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$date]);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'date' => $date,
                'summary' => $summary,
            ], 'Boarding summary retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getBoardingSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // PERMISSIONS (guarded in the controller)
    // ========================================================================

    /**
     * GET /api/attendance/permissions — list student permissions (optionally filtered).
     */
    public function getPermissions(array $data = [])
    {
        try {
            $studentId = $data['student_id'] ?? $_GET['student_id'] ?? null;
            $status = $data['status'] ?? $_GET['status'] ?? null;
            $active = $data['active'] ?? $_GET['active'] ?? null;
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $search = trim((string) ($data['search'] ?? $_GET['search'] ?? ''));
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? null;
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? null;
            $permissionTypeId = $data['permission_type_id'] ?? $_GET['permission_type_id'] ?? null;

            $scope = $this->getAccessibleClassScope();
            $streamScope = $this->buildStudentStreamScope($streamId ? (int) $streamId : null, $scope);
            if ($streamScope['forbidden']) {
                return $this->errorResponse('You are not allowed to access permissions for this class', 403);
            }
            if ($streamScope['empty']) {
                return $this->successResponse([], 'Permissions retrieved');
            }

            $sql = "SELECT sp.*,
                           CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                           s.admission_no,
                           c.name AS class_name,
                           stm.name AS stream_name,
                           st.name AS student_type,
                           st.code AS student_type_code,
                           spt.name AS permission_type_name, spt.code AS permission_type_code,
                           spt.applies_to,
                           COALESCE(
                               CONCAT(approver_p.first_name, ' ', approver_p.last_name),
                               approver_user.username
                           ) AS approved_by_name
                    FROM student_permissions sp
                    JOIN students s ON sp.student_id = s.id
                    LEFT JOIN persons p ON p.id = s.person_id
                    LEFT JOIN student_academic_enrollments en ON en.student_id = s.id AND en.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON aycs.id = en.academic_year_class_stream_id
                    LEFT JOIN streams stm ON stm.id = aycs.stream_id
                    LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                    LEFT JOIN classes c ON c.id = ayc.class_id
                    LEFT JOIN student_types st ON st.id = s.student_type_id
                    JOIN student_permission_types spt ON sp.permission_type_id = spt.id
                    LEFT JOIN users approver_user ON sp.approved_by = approver_user.id
                    LEFT JOIN staff approver_staff ON approver_staff.person_id = approver_user.person_id
                    LEFT JOIN persons approver_p ON approver_p.id = approver_staff.person_id
                    WHERE 1=1";
            $params = [];

            $sql .= $streamScope['sql'];
            $params = array_merge($params, $streamScope['params']);

            if ($studentId) {
                $sql .= " AND sp.student_id = ?";
                $params[] = (int) $studentId;
            }
            if ($status) {
                $sql .= " AND sp.status = ?";
                $params[] = $status;
            }
            if ($active === 'true' || $active === '1') {
                $sql .= " AND CURDATE() BETWEEN sp.start_date AND sp.end_date AND sp.status = 'approved'";
            }
            if ($permissionTypeId) {
                $sql .= " AND sp.permission_type_id = ?";
                $params[] = (int) $permissionTypeId;
            }
            if ($dateFrom && $dateTo) {
                if ($dateFrom > $dateTo) {
                    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
                }
                $sql .= " AND sp.end_date >= ? AND sp.start_date <= ?";
                $params[] = $dateFrom;
                $params[] = $dateTo;
            } elseif ($dateFrom) {
                $sql .= " AND sp.end_date >= ?";
                $params[] = $dateFrom;
            } elseif ($dateTo) {
                $sql .= " AND sp.start_date <= ?";
                $params[] = $dateTo;
            }
            if ($search !== '') {
                $sql .= " AND (
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) LIKE ?
                    OR s.admission_no LIKE ?
                    OR sp.reason LIKE ?
                    OR spt.name LIKE ?
                )";
                $searchTerm = '%' . $search . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }

            $sql .= " ORDER BY sp.created_at DESC LIMIT 250";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Permissions retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceManager][getPermissions] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->logError($e, 'getPermissions');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/attendance/permissions — create a permission/exeat request.
     */
    public function postPermissions(array $data = [])
    {
        try {
            $studentId = isset($data['student_id']) ? (int) $data['student_id'] : null;
            $permissionTypeId = isset($data['permission_type_id']) ? (int) $data['permission_type_id'] : null;
            $startDate = $data['start_date'] ?? null;
            $startTime = $data['start_time'] ?? null;
            $endDate = $data['end_date'] ?? null;
            $endTime = $data['end_time'] ?? null;
            $reason = trim((string) ($data['reason'] ?? ''));
            $parentId = $data['parent_id'] ?? null;
            $requestedByParent = $data['requested_by_parent'] ?? false;
            $expectedReturn = $data['expected_return'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($expectedReturn) {
                $expectedReturn = str_replace('T', ' ', (string) $expectedReturn);
            }

            if (!$studentId || !$permissionTypeId || !$startDate || !$endDate || $reason === '') {
                return $this->errorResponse('Missing required fields', 400);
            }

            if ($startDate > $endDate) {
                [$startDate, $endDate] = [$endDate, $startDate];
            }

            $ptStmt = $this->db->prepare(
                "SELECT id, code, name, max_days, applies_to, status
                 FROM student_permission_types
                 WHERE id = ? AND status = 'active'
                 LIMIT 1"
            );
            $ptStmt->execute([$permissionTypeId]);
            $permissionType = $ptStmt->fetch(PDO::FETCH_ASSOC);
            if (!$permissionType) {
                return $this->errorResponse('Invalid permission type', 400);
            }

            $studentStmt = $this->db->prepare(
                "SELECT s.id, st.code AS student_type_code, st.name AS student_type
                 FROM students s
                 LEFT JOIN student_types st ON st.id = s.student_type_id
                 WHERE s.id = ?
                 LIMIT 1"
            );
            $studentStmt->execute([$studentId]);
            $student = $studentStmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->errorResponse('Invalid student', 400);
            }

            $studentTypeCode = strtoupper((string) ($student['student_type_code'] ?? ''));
            $isBoarder = strpos($studentTypeCode, 'BOARD') !== false;
            if (($permissionType['applies_to'] ?? 'all') === 'boarders_only' && !$isBoarder) {
                return $this->errorResponse('This permission type is only available for boarders', 400);
            }
            if (($permissionType['applies_to'] ?? 'all') === 'day_only' && $isBoarder) {
                return $this->errorResponse('This permission type is only available for day scholars', 400);
            }

            if (!empty($permissionType['max_days'])) {
                $daysRequested = (new \DateTime($startDate))->diff(new \DateTime($endDate))->days + 1;
                if ($daysRequested > (int) $permissionType['max_days']) {
                    return $this->errorResponse('Request exceeds the maximum allowed duration for this permission type', 400);
                }
            }

            $ins = $this->db->prepare(
                "INSERT INTO student_permissions
                 (student_id, permission_type_id, start_date, start_time, end_date, end_time, reason,
                  parent_id, requested_by_parent, expected_return, notes, status, requested_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
            );
            $ins->execute([
                $studentId,
                $permissionTypeId,
                $startDate,
                $startTime ?: null,
                $endDate,
                $endTime ?: null,
                $reason,
                $parentId ?: null,
                filter_var($requestedByParent, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                $expectedReturn ?: null,
                $notes ?: null,
            ]);

            $permissionId = (int) $this->db->lastInsertId();
            return $this->successResponse(['id' => $permissionId], 'Permission request created', 201);
        } catch (Exception $e) {
            $this->logError($e, 'postPermissions');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * PUT /api/attendance/permissions/{id} — approve/reject/edit a permission.
     */
    public function putPermissions($id = null, array $data = [])
    {
        try {
            if (!$id) {
                return $this->errorResponse('Permission ID is required', 400);
            }

            $existStmt = $this->db->prepare("SELECT * FROM student_permissions WHERE id = ? LIMIT 1");
            $existStmt->execute([(int) $id]);
            $existing = $existStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return $this->errorResponse('Permission request not found', 404);
            }

            $status = $data['status'] ?? null;
            $approvedBy = $this->currentUserId();
            $rejectionReason = trim((string) ($data['rejection_reason'] ?? $data['comments'] ?? ''));

            $editableFields = [
                'permission_type_id',
                'start_date',
                'start_time',
                'end_date',
                'end_time',
                'reason',
                'parent_id',
                'requested_by_parent',
                'expected_return',
                'notes',
            ];

            $hasEditPayload = false;
            foreach ($editableFields as $field) {
                if (array_key_exists($field, $data)) {
                    $hasEditPayload = true;
                    break;
                }
            }

            if ($hasEditPayload && !$status) {
                if (($existing['status'] ?? 'pending') !== 'pending') {
                    return $this->errorResponse('Only pending requests can be edited', 400);
                }

                $updates = [];
                $params = [];
                foreach ($editableFields as $field) {
                    if (!array_key_exists($field, $data)) {
                        continue;
                    }
                    $updates[] = "{$field} = ?";
                    if ($field === 'requested_by_parent') {
                        $params[] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                    } elseif ($field === 'expected_return' && !empty($data[$field])) {
                        $params[] = str_replace('T', ' ', (string) $data[$field]);
                    } else {
                        $params[] = $data[$field] === '' ? null : $data[$field];
                    }
                }

                if (empty($updates)) {
                    return $this->errorResponse('No editable fields supplied', 400);
                }

                $params[] = (int) $id;
                $upd = $this->db->prepare(
                    "UPDATE student_permissions SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?"
                );
                $upd->execute($params);

                return $this->successResponse(['id' => (int) $id], 'Permission request updated');
            }

            if (!in_array($status, ['approved', 'rejected', 'cancelled', 'completed'], true)) {
                return $this->errorResponse('Invalid status', 400);
            }

            $sql = "UPDATE student_permissions SET status = ?, updated_at = NOW()";
            $params = [$status];

            if (in_array($status, ['approved', 'rejected'], true)) {
                $sql .= ", approved_by = ?, approved_at = NOW()";
                $params[] = $approvedBy;
            }

            if ($status === 'rejected') {
                $sql .= ", rejection_reason = ?";
                $params[] = $rejectionReason !== '' ? $rejectionReason : null;
            }

            if (!empty($data['notes'])) {
                $sql .= ", notes = ?";
                $params[] = $data['notes'];
            }

            $sql .= " WHERE id = ?";
            $params[] = (int) $id;

            $upd = $this->db->prepare($sql);
            $upd->execute($params);

            return $this->successResponse(['id' => (int) $id, 'status' => $status], 'Permission updated');
        } catch (Exception $e) {
            $this->logError($e, 'putPermissions');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // STAFF ATTENDANCE ENDPOINTS (guarded in the controller)
    // ========================================================================

    /**
     * GET /api/attendance/staff-today — staff status for a date from the daily register view.
     */
    public function getStaffToday(array $data = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $scope = $this->getAccessibleStaffScope();

            if ($scope['restricted'] && empty($scope['staff_ids'])) {
                return $this->successResponse([
                    'date' => $date,
                    'summary' => [
                        'total' => 0,
                        'present' => 0,
                        'absent' => 0,
                        'late' => 0,
                        'on_leave' => 0,
                        'off_day' => 0,
                        'not_marked' => 0,
                    ],
                    'staff' => [],
                ], 'Staff attendance retrieved');
            }

            $where = ["1=1"];
            $params = [$date];

            if ($departmentId) {
                $where[] = "v.department_id = ?";
                $params[] = (int) $departmentId;
            }

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
                $where[] = "v.staff_id IN ({$placeholders})";
                $params = array_merge($params, array_map('intval', $scope['staff_ids']));
            }

            $sql = "SELECT
                        v.staff_id,
                        v.staff_id AS id,
                        v.staff_no,
                        v.first_name,
                        v.last_name,
                        v.position,
                        v.department_name,
                        v.department_name AS department,
                        v.marked_status AS attendance_status,
                        v.marked_status AS current_status,
                        v.check_in_time,
                        v.check_out_time,
                        v.leave_id, v.leave_type, v.leave_status,
                        v.duty_roster_id AS duty_id, v.duty_code AS duty_type_id,
                        v.duty_name AS duty_type, v.duty_code AS duty_type_code,
                        v.effective_status,
                        CASE WHEN v.leave_id IS NOT NULL AND v.leave_status = 'approved' THEN 1 ELSE 0 END AS is_on_leave,
                        CASE WHEN v.duty_code IN ('OFF', 'WEEKEND_OFF') THEN 1 ELSE 0 END AS is_off_day
                    FROM vw_staff_daily_register v
                    WHERE v.date = ?
                        AND " . implode(' AND ', $where) . "
                    ORDER BY v.department_name, v.last_name, v.first_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $summary = [
                'total' => count($staff),
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
            ];
            foreach ($staff as $s) {
                $status = $s['effective_status'] ?? 'not_marked';
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
            }

            return $this->successResponse([
                'date' => $date,
                'summary' => $summary,
                'staff' => $staff,
            ], 'Staff attendance retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getStaffToday');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * POST /api/attendance/mark-staff
     * Expects: { date, attendance: [{ staff_id, status, check_in_time, check_out_time, notes }] }
     */
    public function postMarkStaff(array $data = [])
    {
        try {
            $date = $data['date'] ?? date('Y-m-d');
            $shift = $data['shift'] ?? 'full_day';
            $attendance = $data['attendance'] ?? [];
            $markedBy = $this->currentUserId();
            $scope = $this->getAccessibleStaffScope();

            if (empty($attendance)) {
                return $this->errorResponse('No attendance data provided', 400);
            }
            if ($scope['restricted'] && empty($scope['staff_ids'])) {
                return $this->errorResponse('You are not allowed to mark staff attendance', 403);
            }

            $yearStmt = $this->db->prepare("SELECT id FROM academic_years WHERE YEAR(?) = year_code LIMIT 1");
            $yearStmt->execute([$date]);
            $academicYearId = $yearStmt->fetchColumn() ?: null;

            $dayName = date('l', strtotime($date));

            $created = 0;
            $updated = 0;
            $autoMarked = 0;

            foreach ($attendance as $record) {
                $staffId = $record['staff_id'] ?? null;
                $status = strtolower((string) ($record['status'] ?? 'present'));
                $checkIn = $record['check_in_time'] ?? null;
                $checkOut = $record['check_out_time'] ?? null;
                $notes = $record['notes'] ?? null;

                if (!$staffId) {
                    continue;
                }
                if (!$this->isStaffInScope((int) $staffId, $scope)) {
                    return $this->errorResponse('Not allowed to mark attendance for one or more staff members', 403);
                }
                if (!in_array($status, ['present', 'absent', 'late'], true)) {
                    $status = 'present';
                }

                $regStmt = $this->db->prepare(
                    "SELECT work_start_time, late_threshold_minutes
                     FROM vw_staff_daily_register
                     WHERE staff_id = ? AND date = ?
                     LIMIT 1"
                );
                $regStmt->execute([(int) $staffId, $date]);
                $staffRow = $regStmt->fetch(PDO::FETCH_ASSOC);
                $expectedCheckIn = $staffRow['work_start_time'] ?? null;
                $lateThresh = (int) ($staffRow['late_threshold_minutes'] ?? 15);

                if ($status === 'present' && $checkIn && $expectedCheckIn) {
                    $expectedPlus = date('H:i:s', strtotime($expectedCheckIn) + $lateThresh * 60);
                    if ($checkIn > $expectedPlus) {
                        $status = 'late';
                    }
                }

                $leaveStmt = $this->db->prepare(
                    "SELECT id FROM staff_leaves
                     WHERE staff_id = ? AND ? BETWEEN start_date AND end_date AND status = 'approved'
                     LIMIT 1"
                );
                $leaveStmt->execute([(int) $staffId, $date]);
                $leave = $leaveStmt->fetch(PDO::FETCH_ASSOC);

                $rosterStmt = $this->db->prepare(
                    "SELECT sdr.id
                     FROM staff_duty_roster sdr
                     JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id
                     WHERE sdr.staff_id = ? AND sdr.date = ? AND sdt.code IN ('OFF','WEEKEND_OFF')
                     LIMIT 1"
                );
                $rosterStmt->execute([(int) $staffId, $date]);
                $rosterOff = $rosterStmt->fetch(PDO::FETCH_ASSOC);

                $patternStmt = $this->db->prepare(
                    "SELECT id FROM staff_off_day_patterns
                     WHERE staff_id = ? AND day_of_week = ? AND is_off = 1
                       AND ? >= effective_from AND (effective_to IS NULL OR ? <= effective_to)
                     LIMIT 1"
                );
                $patternStmt->execute([(int) $staffId, $dayName, $date, $date]);
                $patternOff = $patternStmt->fetch(PDO::FETCH_ASSOC);

                $isOffDay = ($rosterOff || $patternOff);
                $isOnLeave = (bool) $leave;

                $absenceReason = null;
                if ($isOnLeave && $record['status'] !== 'present') {
                    $status = 'absent';
                    $absenceReason = 'leave';
                    $autoMarked++;
                } elseif ($isOffDay && $record['status'] !== 'present') {
                    $status = 'absent';
                    $absenceReason = 'off_day';
                    $autoMarked++;
                } elseif ($status === 'absent') {
                    $absenceReason = $record['absence_reason'] ?? 'unexcused';
                    if (!$absenceReason || !in_array($absenceReason, ['leave', 'sick', 'off_day', 'unauthorized', 'other'], true)) {
                        $absenceReason = 'unauthorized';
                    }
                }

                $existStmt = $this->db->prepare(
                    "SELECT id FROM staff_attendance WHERE staff_id = ? AND date = ? LIMIT 1"
                );
                $existStmt->execute([(int) $staffId, $date]);
                $existing = $existStmt->fetchColumn();

                if ($existing) {
                    $upd = $this->db->prepare(
                        "UPDATE staff_attendance
                         SET status = ?, check_in = ?, check_out = ?, absence_reason = ?,
                             leave_id = ?, notes = ?, marked_by = ?
                         WHERE id = ?"
                    );
                    $upd->execute([
                        $status, $checkIn, $checkOut, $absenceReason,
                        $isOnLeave ? $leave['id'] : null, $notes, $markedBy, (int) $existing,
                    ]);
                    $updated++;
                } else {
                    $ins = $this->db->prepare(
                        "INSERT INTO staff_attendance
                         (staff_id, date, academic_year_id, status, check_in, check_out,
                          absence_reason, leave_id, notes, marked_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $ins->execute([
                        (int) $staffId, $date, $academicYearId, $status, $checkIn, $checkOut,
                        $absenceReason, $isOnLeave ? $leave['id'] : null, $notes, $markedBy,
                    ]);
                    $created++;
                }
            }

            return $this->successResponse([
                'created' => $created,
                'updated' => $updated,
                'auto_marked' => $autoMarked,
                'total' => $created + $updated,
                'date' => $date,
                'shift' => $shift,
            ], 'Staff attendance marked successfully');
        } catch (Exception $e) {
            $this->logError($e, 'postMarkStaff');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/staff-register-context — full pre-computed register for a date.
     */
    public function getStaffRegisterContext(array $data = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $shift = $data['shift'] ?? $_GET['shift'] ?? 'full_day';

            $dayName = date('l', strtotime($date));
            $dayNumber = (int) date('N', strtotime($date));

            $calEntry = $this->calendarEntryForDate($date);
            $dayType = $calEntry['day_type'] ?? ($dayNumber >= 6 ? 'weekend' : 'school_day');
            $eventName = $calEntry['title'] ?? ($dayNumber === 7 ? 'Sunday' : ($dayNumber === 6 ? 'Saturday' : 'Working Day'));

            $isWorkingDay = !in_array($dayType, ['public_holiday', 'school_holiday']);
            $onlyRosterStaff = in_array($dayType, ['public_holiday']);

            $where = [];
            $params = [$date];

            if ($departmentId) {
                $where[] = "v.department_id = ?";
                $params[] = (int) $departmentId;
            }

            $sql = "SELECT
                        v.staff_id, v.staff_no, v.staff_name,
                        v.position, v.work_start_time, v.late_threshold_minutes,
                        v.department_id, v.department_name, v.staff_category,
                        v.attendance_id, v.marked_status, v.shift AS marked_shift,
                        v.check_in_time, v.expected_check_in, v.check_out_time,
                        v.absence_reason, v.attendance_notes,
                        v.leave_id, v.leave_type, v.leave_start, v.leave_end,
                        v.relief_staff_name,
                        v.duty_roster_id AS roster_id, v.duty_code, v.duty_name,
                        v.duty_shift, v.duty_start, v.duty_end, v.duty_location,
                        v.pattern_off_id,
                        v.effective_status,
                        v.can_mark
                    FROM vw_staff_daily_register v
                    WHERE v.date = ?" . ($where ? ' AND ' . implode(' AND ', $where) : '') . "
                    ORDER BY v.department_name, v.staff_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $summary = [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
                'on_duty' => 0,
            ];
            foreach ($staff as $s) {
                $summary['total']++;
                $st = $s['effective_status'] ?? 'not_marked';
                if (isset($summary[$st])) {
                    $summary[$st]++;
                }
                if ($s['duty_code'] && !in_array($s['duty_code'], ['OFF', 'WEEKEND_OFF'], true)) {
                    $summary['on_duty']++;
                }
            }

            $shifts = ['full_day' => 'Full Day (08:00–17:00)'];
            if ($dayNumber >= 6 || !$isWorkingDay) {
                $shifts = [
                    'morning' => 'Morning Shift (06:00–14:00)',
                    'afternoon' => 'Afternoon Shift (14:00–22:00)',
                    'night' => 'Night Shift (22:00–06:00)',
                    'full_day' => 'Full Day',
                ];
            }

            return $this->successResponse([
                'date' => $date,
                'day_name' => $dayName,
                'day_type' => $dayType,
                'event_name' => $eventName,
                'is_working_day' => $isWorkingDay,
                'only_roster' => $onlyRosterStaff,
                'available_shifts' => $shifts,
                'current_shift' => $shift,
                'staff' => $staff,
                'summary' => $summary,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'getStaffRegisterContext');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/duty-types
     */
    public function getDutyTypes(array $data = [])
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, code AS duty_code, name AS duty_name, description, color,
                        (status = 'active') AS is_active
                 FROM staff_duty_types
                 WHERE status = 'active'
                 ORDER BY name"
            );
            $stmt->execute();

            return $this->successResponse($stmt->fetchAll(PDO::FETCH_ASSOC), 'Duty types retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getDutyTypes');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/staff-report — per-staff daily statuses with aggregates.
     */
    public function getStaffReport(array $data = [])
    {
        try {
            $dateFrom = $data['date_from'] ?? $_GET['date_from'] ?? date('Y-m-01');
            $dateTo = $data['date_to'] ?? $_GET['date_to'] ?? date('Y-m-d');
            $departmentId = $data['department_id'] ?? $_GET['department_id'] ?? null;
            $dutyTypeId = $data['duty_type_id'] ?? $_GET['duty_type_id'] ?? null;
            $statusFilter = $data['status'] ?? $_GET['status'] ?? null;

            if ($dateFrom > $dateTo) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
            }

            $scope = $this->getAccessibleStaffScope();
            if ($scope['restricted'] && empty($scope['staff_ids'])) {
                return $this->successResponse($this->buildEmptyStaffReport($dateFrom, $dateTo), 'Staff report generated');
            }

            $where = ["s.status = 'active'"];
            $params = [];

            if ($departmentId) {
                $where[] = "sep.department_id = ?";
                $params[] = (int) $departmentId;
            }

            if ($scope['restricted']) {
                $placeholders = implode(',', array_fill(0, count($scope['staff_ids']), '?'));
                $where[] = "s.id IN ({$placeholders})";
                $params = array_merge($params, array_map('intval', $scope['staff_ids']));
            }

            if ($dutyTypeId) {
                $where[] = "EXISTS (
                    SELECT 1
                    FROM staff_duty_roster sdr_filter
                    WHERE sdr_filter.staff_id = s.id
                      AND sdr_filter.date BETWEEN ? AND ?
                      AND sdr_filter.duty_type_id = ?
                )";
                $params[] = $dateFrom;
                $params[] = $dateTo;
                $params[] = (int) $dutyTypeId;
            }

            $staffSql = "SELECT
                            s.id AS staff_id,
                            p.first_name,
                            p.last_name,
                            s.staff_no,
                            s.position,
                            d.name AS department_name
                         FROM staff s
                         LEFT JOIN persons p ON p.id = s.person_id
                         LEFT JOIN staff_employment_profiles sep ON sep.staff_id = s.id
                         LEFT JOIN departments d ON d.id = sep.department_id
                         WHERE " . implode(' AND ', $where) . "
                         ORDER BY p.last_name, p.first_name";
            $stmt = $this->db->prepare($staffSql);
            $stmt->execute($params);
            $staffRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($staffRows)) {
                return $this->successResponse($this->buildEmptyStaffReport($dateFrom, $dateTo), 'Staff report generated');
            }

            $staffIds = array_map('intval', array_column($staffRows, 'staff_id'));
            $dateKeys = $this->buildDateRangeArray($dateFrom, $dateTo);
            $staffPlaceholders = implode(',', array_fill(0, count($staffIds), '?'));

            $viewStmt = $this->db->prepare(
                "SELECT
                    v.staff_id,
                    v.date,
                    v.marked_status AS status,
                    v.check_in_time,
                    v.check_out_time,
                    v.attendance_notes AS notes,
                    v.absence_reason,
                    v.duty_code AS duty_type_code,
                    v.duty_name AS duty_type,
                    v.leave_id,
                    v.leave_type,
                    v.leave_start,
                    v.leave_end,
                    v.effective_status
                 FROM vw_staff_daily_register v
                 WHERE v.date BETWEEN ? AND ?
                   AND v.staff_id IN ({$staffPlaceholders})"
            );
            $viewStmt->execute(array_merge([$dateFrom, $dateTo], $staffIds));
            $viewRows = $viewStmt->fetchAll(PDO::FETCH_ASSOC);

            $attendanceMap = [];
            $leaveMap = [];
            $rosterMap = [];
            foreach ($viewRows as $row) {
                $sid = (int) $row['staff_id'];
                $d = $row['date'];
                $attendanceMap[$sid][$d] = $row;
                if (!isset($leaveMap[$sid]) && !empty($row['leave_id'])) {
                    $leaveMap[$sid][] = [
                        'start_date' => $row['leave_start'],
                        'end_date' => $row['leave_end'],
                        'leave_type' => $row['leave_type'],
                    ];
                }
                if (!empty($row['duty_type_code'])) {
                    $rosterMap[$sid][$d] = [
                        'duty_type_id' => null,
                        'duty_type' => $row['duty_type'],
                        'duty_type_code' => $row['duty_type_code'],
                    ];
                }
            }

            $staffData = [];
            foreach ($staffRows as $staff) {
                $staffId = (int) $staff['staff_id'];
                $dailyStatuses = [];
                $present = 0;
                $absent = 0;
                $late = 0;
                $onLeave = 0;
                $offDays = 0;
                $notMarked = 0;
                $primaryDutyType = null;

                foreach ($dateKeys as $date) {
                    $attendance = $attendanceMap[$staffId][$date] ?? null;
                    $roster = $rosterMap[$staffId][$date] ?? null;
                    $leave = $this->findActiveLeaveForDate($leaveMap[$staffId] ?? [], $date);

                    $effectiveStatus = 'not_marked';
                    $statusLabel = 'Not Marked';

                    if ($leave) {
                        $effectiveStatus = 'on_leave';
                        $statusLabel = 'On Leave';
                        $onLeave++;
                    } elseif ($roster && in_array($roster['duty_type_code'], ['OFF', 'WEEKEND_OFF'], true)) {
                        $effectiveStatus = 'off_day';
                        $statusLabel = 'Off Day';
                        $offDays++;
                    } elseif ($attendance) {
                        $effectiveStatus = $attendance['status'] ?: 'not_marked';
                        $statusLabel = ucfirst((string) $effectiveStatus);

                        if ($effectiveStatus === 'present') {
                            $present++;
                        } elseif ($effectiveStatus === 'absent') {
                            $absent++;
                        } elseif ($effectiveStatus === 'late') {
                            $late++;
                        } else {
                            $notMarked++;
                        }
                    } else {
                        $notMarked++;
                    }

                    if ($roster && !in_array($roster['duty_type_code'], ['OFF', 'WEEKEND_OFF'], true)) {
                        $primaryDutyType = $roster['duty_type'];
                    } elseif (!$primaryDutyType && !empty($attendance['duty_type'])) {
                        $primaryDutyType = $attendance['duty_type'];
                    }

                    $dailyStatuses[] = [
                        'date' => $date,
                        'status' => $effectiveStatus,
                        'label' => $statusLabel,
                        'duty_type' => $roster['duty_type'] ?? $attendance['duty_type'] ?? null,
                        'duty_type_code' => $roster['duty_type_code'] ?? $attendance['duty_type_code'] ?? null,
                        'leave_type' => $leave['leave_type'] ?? null,
                        'check_in_time' => $attendance['check_in_time'] ?? null,
                        'check_out_time' => $attendance['check_out_time'] ?? null,
                        'notes' => $attendance['notes'] ?? null,
                    ];
                }

                $staffRow = [
                    'staff_id' => $staffId,
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'staff_no' => $staff['staff_no'],
                    'position' => $staff['position'],
                    'department_name' => $staff['department_name'],
                    'duty_type' => $primaryDutyType ?: 'General',
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'on_leave' => $onLeave,
                    'off_days' => $offDays,
                    'not_marked' => $notMarked,
                    'daily_statuses' => $dailyStatuses,
                ];

                if ($this->matchesStaffStatusFilter($staffRow, $statusFilter)) {
                    $staffData[] = $staffRow;
                }
            }

            $reportMeta = $this->summarizeStaffReportRows($staffData, $dateKeys);

            return $this->successResponse([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'filters' => [
                    'department_id' => $departmentId ? (int) $departmentId : null,
                    'duty_type_id' => $dutyTypeId ? (int) $dutyTypeId : null,
                    'status' => $statusFilter ?: null,
                ],
                'staff' => $staffData,
                'summary' => $reportMeta['summary'],
                'trend' => $reportMeta['trend'],
                'daily_breakdown' => array_map(static function (array $row): array {
                    return [
                        'staff_id' => $row['staff_id'],
                        'staff_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                        'staff_no' => $row['staff_no'] ?? null,
                        'department_name' => $row['department_name'] ?? null,
                        'duty_type' => $row['duty_type'] ?? null,
                        'statuses' => $row['daily_statuses'] ?? [],
                    ];
                }, $staffData),
            ], 'Staff report generated');
        } catch (Exception $e) {
            $this->logError($e, 'getStaffReport');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // CALENDAR / CONTEXT ENDPOINTS
    // ========================================================================

    /**
     * GET /api/attendance/calendar — school calendar days for a range.
     */
    public function getCalendar(array $data = [])
    {
        try {
            $startDate = $data['start_date'] ?? $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $data['end_date'] ?? $_GET['end_date'] ?? date('Y-m-t');

            $stmt = $this->db->prepare(
                "SELECT acd.date, cdt.code AS day_type, acd.title,
                        cdt.affects_day_students, cdt.affects_boarders
                 FROM academic_year_calendar_days acd
                 LEFT JOIN calendar_day_types cdt ON cdt.id = acd.calendar_day_type_id
                 WHERE acd.date BETWEEN ? AND ?
                 ORDER BY acd.date"
            );
            $stmt->execute([$startDate, $endDate]);
            $calendar = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->successResponse([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'events' => $calendar,
            ], 'Calendar retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'getCalendar');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/is-school-day
     */
    public function getIsSchoolDay(array $data = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');

            $calEntry = $this->calendarEntryForDate($date);

            $isSchoolDay = true;
            $reason = 'Regular school day';

            if ($calEntry) {
                $dayType = $calEntry['day_type'];
                $isConfiguredSaturday = ((int) date('N', strtotime($date)) === 6)
                    && (($cfg = $this->schoolWeekConfigForYear($this->getCurrentAcademicYearId() ?: 0))
                        && !empty($cfg['saturday_classes']));
                if (in_array($dayType, ['public_holiday', 'school_holiday'], true)
                    || ($dayType === 'weekend' && !$isConfiguredSaturday)) {
                    $isSchoolDay = false;
                    $reason = $calEntry['title'] ?: $dayType;
                }
            } else {
                $dayOfWeek = (int) date('N', strtotime($date));
                if ($dayOfWeek == 7) {
                    $isSchoolDay = false;
                    $reason = 'Sunday';
                } elseif ($dayOfWeek == 6) {
                    $yearId = $this->getCurrentAcademicYearId();
                    $weekCfg = $yearId ? $this->schoolWeekConfigForYear($yearId) : null;
                    if (!$weekCfg || empty($weekCfg['saturday_classes'])) {
                        $isSchoolDay = false;
                        $reason = 'Saturday';
                    }
                }
            }

            return $this->successResponse([
                'date' => $date,
                'is_school_day' => $isSchoolDay,
                'day_type' => $calEntry['day_type'] ?? 'school_day',
                'reason' => $reason,
                'calendar_event' => $calEntry ? [
                    'event_type' => $calEntry['day_type'],
                    'event_name' => $calEntry['title'],
                ] : null,
            ], 'School day check completed');
        } catch (Exception $e) {
            $this->logError($e, 'getIsSchoolDay');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/register-context — calendar + sessions + term context for a date.
     */
    public function getRegisterContext(array $data = [])
    {
        try {
            $date = $data['date'] ?? $_GET['date'] ?? date('Y-m-d');
            $streamId = $data['stream_id'] ?? $_GET['stream_id'] ?? null;
            $sessionId = $data['session_id'] ?? $_GET['session_id'] ?? null;

            $dayName = date('l', strtotime($date));
            $dayNumber = (int) date('N', strtotime($date));

            $calEntry = $this->calendarEntryForDate($date);
            $dayType = $calEntry['day_type'] ?? ($dayNumber === 7 ? 'weekend' : ($dayNumber === 6 ? 'weekend' : 'school_day'));
            $eventName = $calEntry['title'] ?? ($dayNumber === 7 ? 'Sunday' : ($dayNumber === 6 ? 'Saturday' : 'Regular School Day'));

            $yearId = $this->getCurrentAcademicYearId();
            $weekCfg = $yearId ? $this->schoolWeekConfigForYear($yearId) : null;
            $classDays = json_decode($weekCfg['class_days'] ?? '[]', true) ?: [];
            $boardingDays = json_decode($weekCfg['boarding_days'] ?? '[]', true) ?: [];

            $isSaturdayClass = $dayNumber === 6
                && $weekCfg
                && !empty($weekCfg['saturday_classes']);
            $isClassDay = !in_array($dayType, ['public_holiday', 'school_holiday'], true)
                && (($dayNumber < 6 && in_array($dayName, $classDays, true)) || $isSaturdayClass);
            $isBoardingDay = $dayType !== 'school_holiday'
                && in_array($dayName, $boardingDays, true);

            $blockedReason = null;
            if (!$isClassDay) {
                if ($dayType === 'public_holiday') {
                    $blockedReason = "Public Holiday: {$eventName} — class register not required";
                } elseif ($dayType === 'school_holiday') {
                    $blockedReason = "School Holiday/Break: {$eventName} — class register closed";
                } elseif ($dayType === 'weekend') {
                    $blockedReason = $dayNumber === 7
                        ? 'Sunday — class register not required'
                        : ($isSaturdayClass ? null : 'Saturday — no scheduled classes');
                } else {
                    $blockedReason = $eventName;
                }
            }

            $termRow = $this->resolveTermForDate($date);
            $termId = !empty($termRow['term_id']) ? (int) $termRow['term_id'] : null;

            $sessionsStmt = $this->db->prepare(
                "SELECT ass.id, ass.code, ass.type,
                        COALESCE(cfg.name, ass.name) AS name,
                        COALESCE(cfg.applies_to, ass.applies_to) AS applies_to,
                        COALESCE(cfg.start_time, ass.start_time) AS start_time,
                        COALESCE(cfg.end_time, ass.end_time) AS end_time,
                        COALESCE(cfg.applicable_days, ass.applicable_days) AS applicable_days,
                        COALESCE(cfg.status, ass.status) AS status
                 FROM attendance_sessions ass
                 LEFT JOIN attendance_session_term_configs cfg
                   ON cfg.session_id=ass.id AND cfg.academic_year_term_id=?
                 WHERE COALESCE(cfg.status, ass.status)='active'
                 ORDER BY COALESCE(cfg.display_order, ass.display_order), ass.id"
            );
            $sessionsStmt->execute([$termId ?: 0]);
            $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

            $streamClassId = null;
            if ($streamId) {
                $classStmt = $this->db->prepare(
                    "SELECT ayc.class_id
                       FROM academic_year_class_streams aycs
                       JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                      WHERE aycs.id = ? LIMIT 1"
                );
                $classStmt->execute([(int) $streamId]);
                $streamClassId = $classStmt->fetchColumn();
            }

            $applicableSessions = array_values(array_filter($sessions, function ($s) use ($dayName, $isClassDay, $isBoardingDay, $streamClassId, $termId) {
                $days = json_decode($s['applicable_days'] ?? '[]', true) ?: [];
                if (!in_array($dayName, $days, true)) {
                    return false;
                }
                if ($s['type'] === 'academic' && !$isClassDay) {
                    return false;
                }
                if ($s['type'] === 'boarding' && !$isBoardingDay) {
                    return false;
                }
                if ($s['type'] === 'academic' && $streamClassId) {
                    if (!$this->sessionAppliesToClassForTerm((int) $s['id'], (int) $streamClassId, $termId)) return false;
                }
                return true;
            }));

            $existingCounts = ['class' => 0, 'boarding' => 0, 'activity' => 0];
            if ($streamId) {
                $markSql = "SELECT sa.register_type, COUNT(DISTINCT sa.student_academic_enrollment_id) AS cnt
                            FROM student_attendance sa
                            JOIN student_academic_enrollments en ON en.id = sa.student_academic_enrollment_id
                            WHERE sa.date = ? AND en.academic_year_class_stream_id = ?";
                $markParams = [$date, (int) $streamId];
                if ($sessionId) {
                    $markSql .= " AND sa.session_id = ?";
                    $markParams[] = (int) $sessionId;
                }
                $markSql .= " GROUP BY sa.register_type";
                $markStmt = $this->db->prepare($markSql);
                $markStmt->execute($markParams);
                foreach ($markStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $existingCounts[$r['register_type']] = (int) $r['cnt'];
                }
            }

            $totalStudents = 0;
            if ($streamId) {
                $tsStmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM student_academic_enrollments
                     WHERE academic_year_class_stream_id = ? AND enrollment_status = 'active'"
                );
                $tsStmt->execute([(int) $streamId]);
                $totalStudents = (int) $tsStmt->fetchColumn();
            }

            return $this->successResponse([
                'date' => $date,
                'day_name' => $dayName,
                'day_number' => $dayNumber,
                'day_type' => $dayType,
                'event_name' => $eventName,
                'is_class_day' => $isClassDay,
                'is_boarding_day' => $isBoardingDay,
                'blocked_reason' => $blockedReason,
                'affects_day_students' => (bool) ($calEntry['affects_day_students'] ?? 1),
                'affects_boarders' => (bool) ($calEntry['affects_boarders'] ?? 1),
                'applicable_sessions' => $applicableSessions,
                'current_term' => $termRow,
                'existing_marks' => $existingCounts,
                'total_students' => $totalStudents,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'getRegisterContext');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * GET /api/attendance/student-history-by-year/{student_id} — records grouped by year → term.
     */
    public function getStudentHistoryByYear($id = null, array $data = [])
    {
        $studentId = $id ?? $data['student_id'] ?? $_GET['student_id'] ?? null;
        if (!$studentId) {
            return $this->errorResponse('Student ID is required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT
                    sae.academic_year_id,
                    ay.year_code,
                    ay.year_name,
                    ayt.id AS term_id,
                    t.code AS term_code,
                    t.name AS term_name,
                    ayc.class_id,
                    c.name AS class_name,
                    sa.register_type,
                    sa.date,
                    sa.status,
                    sa.absence_reason,
                    sa.session_id,
                    ass.name AS session_name,
                    ass.type AS session_type
                 FROM student_attendance sa
                 JOIN student_academic_enrollments sae ON sae.id = sa.student_academic_enrollment_id
                 LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes c ON c.id = ayc.class_id
                 LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
                 LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = sae.academic_year_id
                     AND sa.date BETWEEN ayt.opening_date AND ayt.closing_date
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 WHERE sae.student_id = ?
                 ORDER BY sa.date ASC, sa.session_id ASC"
            );
            $stmt->execute([(int) $studentId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $r) {
                $yk = $r['year_code'] ?? 'unknown';
                $tk = $r['term_id'] ?? 0;
                $rt = $r['register_type'];

                if (!isset($grouped[$yk])) {
                    $grouped[$yk] = ['year_name' => $r['year_name'], 'year_code' => $r['year_code'], 'terms' => []];
                }
                if (!isset($grouped[$yk]['terms'][$tk])) {
                    $grouped[$yk]['terms'][$tk] = [
                        'term_name' => $r['term_name'],
                        'term_number' => $this->termNumberFromCode($r['term_code']),
                        'class_name' => $r['class_name'],
                        'records' => [],
                        'summary' => [
                            'class' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0],
                            'boarding' => ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0],
                        ],
                    ];
                }
                $grouped[$yk]['terms'][$tk]['records'][] = $r;
                if (isset($grouped[$yk]['terms'][$tk]['summary'][$rt])) {
                    $grouped[$yk]['terms'][$tk]['summary'][$rt][$r['status'] ?? 'absent']++;
                    $grouped[$yk]['terms'][$tk]['summary'][$rt]['total']++;
                }
            }

            foreach ($grouped as &$y) {
                ksort($y['terms']);
                $y['terms'] = array_values($y['terms']);
            }
            unset($y);

            return $this->successResponse([
                'student_id' => (int) $studentId,
                'by_year' => array_values($grouped),
                'total_rows' => count($rows),
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'getStudentHistoryByYear');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ========================================================================
    // PURE PHP HELPERS (no DB)
    // ========================================================================

    private function buildDateRangeArray(string $dateFrom, string $dateTo): array
    {
        $dates = [];
        $current = new \DateTime($dateFrom);
        $end = new \DateTime($dateTo);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        return $dates;
    }

    private function findActiveLeaveForDate(array $leaveRows, string $date): ?array
    {
        foreach ($leaveRows as $leave) {
            if (($leave['start_date'] ?? null) <= $date && ($leave['end_date'] ?? null) >= $date) {
                return $leave;
            }
        }

        return null;
    }

    private function matchesStaffStatusFilter(array $row, ?string $statusFilter): bool
    {
        if (!$statusFilter) {
            return true;
        }

        switch ($statusFilter) {
            case 'present':
                return (int) ($row['present'] ?? 0) > 0;
            case 'absent':
                return (int) ($row['absent'] ?? 0) > 0;
            case 'late':
                return (int) ($row['late'] ?? 0) > 0;
            case 'on_leave':
                return (int) ($row['on_leave'] ?? 0) > 0;
            case 'off_day':
                return (int) ($row['off_days'] ?? 0) > 0;
            case 'not_marked':
                return (int) ($row['not_marked'] ?? 0) > 0;
            default:
                return true;
        }
    }

    private function buildEmptyAcademicSummary(string $dateFrom, string $dateTo, ?int $streamId = null): array
    {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'stream_id' => $streamId,
            'students' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'permission' => 0,
                'total_days' => 0,
                'average_attendance' => 0,
                'student_count' => 0,
            ],
            'trend' => [],
            'low_attendance' => [],
        ];
    }

    private function buildEmptyStaffReport(string $dateFrom, string $dateTo): array
    {
        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filters' => [
                'department_id' => null,
                'duty_type_id' => null,
                'status' => null,
            ],
            'staff' => [],
            'summary' => [
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
                'total_days' => 0,
                'average_attendance' => 0,
                'staff_count' => 0,
            ],
            'trend' => [],
            'daily_breakdown' => [],
        ];
    }

    private function summarizeStaffReportRows(array $rows, array $dateKeys): array
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'on_leave' => 0,
            'off_day' => 0,
            'not_marked' => 0,
            'total_days' => 0,
            'average_attendance' => 0,
            'staff_count' => count($rows),
        ];

        $trendMap = [];
        foreach ($dateKeys as $date) {
            $trendMap[$date] = [
                'date' => $date,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'on_leave' => 0,
                'off_day' => 0,
                'not_marked' => 0,
            ];
        }

        foreach ($rows as $row) {
            $summary['present'] += (int) ($row['present'] ?? 0);
            $summary['absent'] += (int) ($row['absent'] ?? 0);
            $summary['late'] += (int) ($row['late'] ?? 0);
            $summary['on_leave'] += (int) ($row['on_leave'] ?? 0);
            $summary['off_day'] += (int) ($row['off_days'] ?? 0);
            $summary['not_marked'] += (int) ($row['not_marked'] ?? 0);

            foreach (($row['daily_statuses'] ?? []) as $daily) {
                $date = $daily['date'] ?? null;
                $status = $daily['status'] ?? 'not_marked';
                if ($date && isset($trendMap[$date]) && array_key_exists($status, $trendMap[$date])) {
                    $trendMap[$date][$status]++;
                }
            }
        }

        $summary['total_days'] = $summary['present'] + $summary['absent'] + $summary['late'];
        if ($summary['total_days'] > 0) {
            $summary['average_attendance'] = round((($summary['present'] + $summary['late']) / $summary['total_days']) * 100, 1);
        }

        return [
            'summary' => $summary,
            'trend' => array_values($trendMap),
        ];
    }

    private function applyAcademicStatusFilter(array $students, ?string $statusFilter): array
    {
        if (!$statusFilter) {
            return $students;
        }

        return array_values(array_filter($students, static function (array $student) use ($statusFilter) {
            switch ($statusFilter) {
                case 'present':
                    return ($student['present'] ?? 0) > 0;
                case 'absent':
                    return ($student['absent'] ?? 0) > 0;
                case 'late':
                    return ($student['late'] ?? 0) > 0;
                case 'permission':
                    return ($student['permission'] ?? 0) > 0;
                default:
                    return true;
            }
        }));
    }

    private function summarizeAcademicRows(array $students): array
    {
        $summary = [
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'permission' => 0,
            'total_days' => 0,
            'average_attendance' => 0,
            'student_count' => count($students),
        ];

        foreach ($students as $student) {
            $summary['present'] += (int) ($student['present'] ?? 0);
            $summary['absent'] += (int) ($student['absent'] ?? 0);
            $summary['late'] += (int) ($student['late'] ?? 0);
            $summary['permission'] += (int) ($student['permission'] ?? 0);
            $summary['total_days'] += (int) ($student['total_days'] ?? 0);
        }

        if ($summary['total_days'] > 0) {
            $summary['average_attendance'] = round(($summary['present'] / $summary['total_days']) * 100, 1);
        }

        return $summary;
    }
}
