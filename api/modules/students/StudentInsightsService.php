<?php
declare(strict_types=1);

namespace App\API\Modules\students;

use App\API\Services\ReadReplicaService;
use DomainException;
use PDO;
use RuntimeException;

class StudentInsightsService
{
    private PDO $db;
    private StudentService $studentService;

    public function __construct(PDO $db, StudentService $studentService)
    {
        $this->db = $db;
        $this->studentService = $studentService;
    }

    public function listHealthSpecialNeeds(array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(200, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = trim((string)($filters['search'] ?? ''));

        $where = ["(hr.disability_notes IS NOT NULL AND hr.disability_notes != ''
            OR hr.chronic_conditions IS NOT NULL AND hr.chronic_conditions != ''
            OR hr.allergies IS NOT NULL AND hr.allergies != '')"];
        $params = [];

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = "(per.first_name LIKE ? OR per.last_name LIKE ? OR s.admission_no LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT
                    s.id, s.admission_no,
                    CONCAT(per.first_name, ' ', COALESCE(per.middle_name,''), ' ', per.last_name) AS full_name,
                    per.first_name, per.last_name, per.gender, per.dob AS date_of_birth, s.status,
                    st2.name AS stream_name,
                    hr.disability_notes, hr.chronic_conditions, hr.allergies,
                    hr.special_diet, hr.blood_group, hr.notes AS health_notes
                FROM students s
                JOIN persons per ON per.id = s.person_id
                LEFT JOIN student_academic_enrollments sae ON s.id = sae.student_id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN streams st2 ON st2.id = aycs.stream_id
                LEFT JOIN student_health_records hr ON hr.student_id = s.id
                WHERE s.status = 'active' AND {$whereClause}
                ORDER BY per.first_name, per.last_name
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $countSql = "SELECT COUNT(*)
                     FROM students s
                     JOIN persons per ON per.id = s.person_id
                     LEFT JOIN student_health_records hr ON hr.student_id = s.id
                     WHERE s.status = 'active' AND {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $limit,
            'total_pages' => (int)ceil($total / max($limit, 1)),
        ];
    }

    public function getPerformanceMeta(): array
    {
        $academicCalendar = \App\API\Services\ReadReplicaService::qualifiedRef('academic_calendar');
        $academicTerm = \App\API\Services\ReadReplicaService::qualifiedRef('academic_term');
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT DISTINCT class_stream_id AS id, class_id, stream_name
                                          FROM {$academicCalendar}
                                          WHERE class_stream_status = 'active'
                                          ORDER BY stream_name ASC"),
            'academic_years' => $this->fetchAll("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"),
            'terms' => $this->fetchAll("SELECT academic_year_term_id AS id, academic_year_id,
                                               term_name AS name, term_code AS term_number,
                                               term_period_status AS status
                                        FROM {$academicTerm}
                                        ORDER BY term_code ASC"),
            'assessments' => $this->fetchAll("SELECT DISTINCT title AS name, id FROM assessments ORDER BY title ASC"),
        ];
    }

    public function getPerformanceOverview(array $user, string $context, array $filters = []): array
    {
        $scope = $this->studentService->scopeService->buildScope($context, $user);
        [$scopeConditions, $scopeBindings] = $this->studentService->scopeService->whereClause($scope);

        $viewMode = strtolower((string)($filters['view_mode'] ?? 'students'));
        $classId = !empty($filters['class_id']) ? (int)$filters['class_id'] : null;
        $streamId = !empty($filters['stream_id']) ? (int)$filters['stream_id'] : null;
        $gender = !empty($filters['gender']) ? $filters['gender'] : null;
        $academicYearVal = !empty($filters['academic_year']) ? $filters['academic_year'] : null;
        $termId = !empty($filters['term_id']) ? (int)$filters['term_id'] : null;
        $search = !empty($filters['search']) ? trim((string)$filters['search']) : '';
        [$yearId, $yearCode] = $this->resolveAcademicYear($academicYearVal);

        $conditions = ["s.status = 'active'"];
        $bindings = [];

        if ($classId !== null) {
            $conditions[] = "ayc.class_id = ?";
            $bindings[] = $classId;
        }
        if ($streamId !== null) {
            $conditions[] = "aycs.stream_id = ?";
            $bindings[] = $streamId;
        }
        if ($gender !== null) {
            $conditions[] = "per.gender = ?";
            $bindings[] = $gender;
        }
        if ($search !== '') {
            $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ? OR CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) LIKE ?)";
            $term = '%' . $search . '%';
            array_push($bindings, $term, $term, $term, $term);
        }
        foreach ($scopeConditions as $scopeCondition) {
            $conditions[] = $scopeCondition;
        }
        $bindings = array_merge($bindings, $scopeBindings);

        $sql = "
            SELECT
                s.id AS student_id,
                s.admission_no,
                CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                lp.class_name,
                lp.stream_name,
                per.gender,
                COALESCE((
                    SELECT ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2)
                    FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
                    JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
                    JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = ar.student_academic_enrollment_id
                    WHERE ae.student_id = s.id AND (? IS NULL OR a.academic_year_term_id = ?)
                ), 0.00) AS average_score,
                COALESCE((
                    SELECT ROUND(COUNT(CASE WHEN sa.status IN ('present', 'late') THEN 1 END) / COUNT(*) * 100, 2)
                    FROM " . ReadReplicaService::qualifiedRef("student_attendance") . " sa
                    JOIN student_academic_enrollments ae ON ae.id = sa.student_academic_enrollment_id
                    WHERE ae.student_id = s.id AND (? IS NULL OR ae.academic_year_id = ?)
                ), 100.00) AS attendance_rate,
                COALESCE((
                    SELECT SUM(sfo.amount_due)
                    FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " sfo
                    JOIN student_academic_enrollments ae ON ae.id = sfo.student_academic_enrollment_id
                    WHERE ae.student_id = s.id AND (? IS NULL OR sfo.academic_year_id = ?)
                ), 0.00) AS fee_balance,
                (SELECT COUNT(*) FROM discipline_incidents di
                 JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = di.student_academic_enrollment_id
                 WHERE ae.student_id = s.id) AS discipline_cases,
                (SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("activity_participants") . " ap
                 JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = ap.student_academic_enrollment_id
                 WHERE ae.student_id = s.id) AS activities_count,
                '-' AS position
            FROM " . ReadReplicaService::qualifiedRef("students") . " s
            JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
            -- The class in the year whose scores are shown (default: current).
            LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement_history') . " lp ON lp.student_id = s.id
                 AND lp.academic_year_id = COALESCE(?, (SELECT ay.id FROM " . ReadReplicaService::qualifiedRef('academic_years') . " ay WHERE ay.is_current = 1 ORDER BY ay.id DESC LIMIT 1))
            WHERE " . implode(' AND ', $conditions);

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$termId, $termId, $yearId, $yearId, $yearId, $yearId, $yearId], $bindings));
        $studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($studentRows as &$row) {
            $row['grade'] = $this->deriveGradeFromPercentage($row['average_score']);
        }
        unset($row);

        return $this->aggregatePerformanceRows($studentRows, $viewMode);
    }

    /** Build a minimized, aggregate-only support-planning input from authorized performance rows. */
    public function buildSupportPlanningInput(array $user, string $context, array $filters = []): array
    {
        $scope = $this->studentService->scopeService->buildScope($context, $user);
        [$scopeConditions, $scopeBindings] = $this->studentService->scopeService->whereClause($scope);

        $yearValue = $filters['academic_year_id'] ?? $filters['academic_year'] ?? $filters['year_id'] ?? null;
        [$yearId, $yearCode] = $this->resolveAcademicYear($yearValue);
        if ($yearId === null) {
            throw new DomainException('Choose a valid academic year before preparing this review.', 422);
        }

        $termId = !empty($filters['term_id']) ? (int) $filters['term_id'] : null;
        $termDates = null;
        if ($termId !== null) {
            $termDates = $this->fetchOne(
                'SELECT opening_date, closing_date FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' WHERE id = ? AND academic_year_id = ? LIMIT 1',
                [$termId, $yearId]
            );
            if (!$termDates) {
                throw new DomainException('The selected term does not belong to this academic year.', 422);
            }
        }

        $where = ["s.status = 'active'", "sae.academic_year_id = ?", "sae.enrollment_status IN ('active', 'completed', 'graduated')"];
        $bindings = [$yearId];
        $classId = !empty($filters['class_id']) ? (int) $filters['class_id'] : null;
        $streamId = !empty($filters['stream_id']) ? (int) $filters['stream_id'] : null;
        $gender = trim((string) ($filters['gender'] ?? ''));
        if ($classId !== null) { $where[] = 'lp.class_id = ?'; $bindings[] = $classId; }
        if ($streamId !== null) { $where[] = 'lp.stream_id = ?'; $bindings[] = $streamId; }
        if ($gender !== '') { $where[] = 'per.gender = ?'; $bindings[] = $gender; }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ? OR CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) LIKE ?)";
            $like = '%' . mb_substr($search, 0, 100) . '%';
            array_push($bindings, $like, $like, $like, $like);
        }
        array_push($where, ...$scopeConditions);
        array_push($bindings, ...$scopeBindings);

        $attendanceDateFilter = '';
        $attendanceBindings = [];
        if ($termDates) {
            $attendanceDateFilter = ' AND sa.date BETWEEN ? AND ?';
            $attendanceBindings[] = $termDates['opening_date'];
            $attendanceBindings[] = $termDates['closing_date'];
        }
        $assessmentTermFilter = $termId === null ? '' : ' AND a.academic_year_term_id = ?';
        $assessmentBindings = [$yearId];
        if ($termId !== null) $assessmentBindings[] = $termId;

        // The inner query computes only per-learner signals; the outer query
        // emits cohort counts and averages, never learner identifiers or records.
        $transportJoin = !empty($scope['transport_route_ids']) ?
            ' LEFT JOIN ' . ReadReplicaService::qualifiedRef('student_transport_assignments') . " transport_scope ON transport_scope.student_id = s.id AND transport_scope.status = 'active'" : '';
        $sql = "SELECT COUNT(*) AS learner_count,
                       SUM(CASE WHEN attendance_rate IS NOT NULL THEN 1 ELSE 0 END) AS attendance_known,
                       SUM(CASE WHEN attendance_rate < 75 THEN 1 ELSE 0 END) AS attendance_below,
                       AVG(average_score) AS average_score,
                       SUM(CASE WHEN average_score IS NOT NULL THEN 1 ELSE 0 END) AS score_known
                FROM (
                    SELECT s.id,
                           (SELECT ROUND(100.0 * SUM(CASE WHEN sa.status IN ('present','late') THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 2)
                            FROM " . ReadReplicaService::qualifiedRef('student_attendance') . " sa
                            WHERE sa.student_academic_enrollment_id = sae.id
                              AND sa.register_type = 'class'{$attendanceDateFilter}) AS attendance_rate,
                           (SELECT ROUND(AVG(ar.marks_obtained / NULLIF(a.max_marks, 0) * 100), 2)
                            FROM " . ReadReplicaService::qualifiedRef('assessment_results') . " ar
                            JOIN " . ReadReplicaService::qualifiedRef('assessments') . " a ON a.id = ar.assessment_id
                            WHERE ar.student_academic_enrollment_id = sae.id
                              AND a.academic_year_id = ?
                              AND a.max_marks > 0
                              AND ar.marks_obtained IS NOT NULL
                              AND ar.marks_obtained BETWEEN 0 AND a.max_marks
                              AND ar.entry_status = 'present'
                              AND ar.responder_type = 'teacher'
                              AND ar.is_submitted = 1
                              AND ar.is_approved = 1
                              AND ar.deleted_at IS NULL{$assessmentTermFilter}) AS average_score
                    FROM " . ReadReplicaService::qualifiedRef('students') . " s
                    JOIN " . ReadReplicaService::qualifiedRef('persons') . " per ON per.id = s.person_id
                    JOIN " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae ON sae.student_id = s.id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef('student_types') . " st ON st.id = s.student_type_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement_history') . " lp ON lp.student_id = s.id AND lp.academic_year_id = sae.academic_year_id
                    {$transportJoin}
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY s.id, sae.id
                ) cohort";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($attendanceBindings, $assessmentBindings, $bindings));
        $aggregate = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $aggregate['academic_year'] = $yearCode;
        $aggregate['term_selected'] = $termId !== null;
        return self::normalizeSupportPlanningAggregate($aggregate);
    }

    /** @param list<array<string,mixed>> $rows */
    public static function normalizeSupportPlanningRows(array $rows): array
    {
        $count = count($rows);
        $attendanceKnown = 0;
        $attendanceBelow = 0;
        $scoreTotal = 0.0;
        $scoreCount = 0;
        foreach ($rows as $row) {
            if (isset($row['attendance_rate']) && is_numeric($row['attendance_rate'])
                && (float) $row['attendance_rate'] >= 0 && (float) $row['attendance_rate'] <= 100) {
                $attendanceKnown++;
                if ((float) $row['attendance_rate'] < 75) $attendanceBelow++;
            }
            if (isset($row['average_score']) && is_numeric($row['average_score'])
                && (float) $row['average_score'] >= 0 && (float) $row['average_score'] <= 100) {
                $scoreTotal += (float) $row['average_score'];
                $scoreCount++;
            }
        }
        return self::normalizeSupportPlanningAggregate([
            'learner_count' => $count,
            'attendance_known' => $attendanceKnown,
            'attendance_below' => $attendanceBelow,
            'average_score' => $scoreCount > 0 ? $scoreTotal / $scoreCount : null,
            'score_known' => $scoreCount,
        ]);
    }

    /** Convert database cohort aggregates to privacy-safe, data-quality-aware bands. */
    public static function normalizeSupportPlanningAggregate(array $aggregate): array
    {
        $count = max(0, (int) ($aggregate['learner_count'] ?? 0));
        if ($count < 5) {
            throw new DomainException('At least five learners are required for an aggregate support review.', 422);
        }
        $attendanceKnown = max(0, min($count, (int) ($aggregate['attendance_known'] ?? 0)));
        $attendanceBelow = max(0, min($attendanceKnown, (int) ($aggregate['attendance_below'] ?? 0)));
        $scoreKnown = max(0, min($count, (int) ($aggregate['score_known'] ?? 0)));
        $averageValue = $aggregate['average_score'] ?? null;
        $average = $scoreKnown > 0 && is_numeric($averageValue)
            && (float) $averageValue >= 0 && (float) $averageValue <= 100
            ? (float) $averageValue
            : null;

        return [
            'report_date' => date('Y-m-d'),
            'scope' => 'authorized_aggregate_performance',
            'academic_year' => mb_substr((string) ($aggregate['academic_year'] ?? ''), 0, 20),
            'term_selected' => !empty($aggregate['term_selected']) ? 'yes' : 'no',
            'learner_count_band' => $count < 10 ? '5_to_9' : ($count < 25 ? '10_to_24' : '25_plus'),
            'attendance_data_coverage' => $attendanceKnown === 0 ? 'unavailable' : ($attendanceKnown === $count ? 'complete' : 'some_missing'),
            'attendance_below_threshold_band' => $attendanceKnown === 0 ? 'unavailable' : ($attendanceBelow === 0 ? 'none' : ($attendanceBelow < 5 ? '1_to_4' : '5_plus')),
            'average_score_band' => $average === null ? 'unavailable' : ($average >= 75 ? '75_plus' : ($average >= 50 ? '50_to_74' : 'below_50')),
            'assessment_data_coverage' => $scoreKnown === 0 ? 'unavailable' : ($scoreKnown === $count ? 'complete' : 'some_missing'),
            'support_follow_up_intent' => 'Verify relevant records with responsible staff before action.',
        ];
    }

    public function getPerformanceFull(int $studentId, array $user, string $context, array $filters = []): ?array
    {
        $scope = $this->studentService->scopeService->buildScope($context, $user);
        if (!$this->studentService->scopeService->canAccessStudent($studentId, $scope)) {
            throw new RuntimeException('forbidden:You do not have permission to view this student.');
        }

        $student = $this->fetchOne("
            SELECT s.id, s.admission_no, per.first_name, per.middle_name, per.last_name,
                   CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                   per.gender, per.photo_url, c.name AS class_name, st2.name AS stream_name
            FROM students s
            JOIN persons per ON per.id = s.person_id
            LEFT JOIN student_academic_enrollments sae ON s.id = sae.student_id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN streams st2 ON st2.id = aycs.stream_id
            WHERE s.id = ?
            LIMIT 1
        ", [$studentId]);
        if (!$student) {
            return null;
        }

        $academicYearVal = $filters['academic_year'] ?? $filters['academic_year_id'] ?? null;
        $termId = !empty($filters['term_id']) ? (int)$filters['term_id'] : null;
        [$yearId] = $this->resolveAcademicYear($academicYearVal);

        $subjects = $this->fetchSubjectPerformance($studentId, $termId, $yearId);
        $attendance = $this->fetchAttendanceSummary($studentId, $termId, $yearId);
        $disciplineRecords = $this->fetchAll(
            "SELECT di.id, di.incident_date AS date, di.description AS case_title, di.severity, di.status, di.action_taken
             FROM " . ReadReplicaService::masterRef("discipline_incidents") . " di
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = di.student_academic_enrollment_id
             WHERE ae.student_id = ? ORDER BY di.incident_date DESC",
            [$studentId]
        );
        $activities = $this->fetchAll(
            "SELECT ap.activity_id as id, ac.name as title, ap.joined_at
             FROM " . ReadReplicaService::qualifiedRef("activity_participants") . " ap
             LEFT JOIN " . ReadReplicaService::qualifiedRef("activity_categories") . " ac ON ac.id = ap.activity_id
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = ap.student_academic_enrollment_id
             WHERE ae.student_id = ?
             ORDER BY ap.joined_at DESC",
            [$studentId]
        );
        $finance = $this->fetchOne(
            "SELECT COALESCE(SUM(amount_due), 0) as total_due,
                    COALESCE(SUM(amount_paid), 0) as total_paid,
                    COALESCE(SUM(amount_waived), 0) as total_waived,
                    COALESCE(SUM(balance), 0) as balance
             FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . "
             WHERE student_id = ?",
            [$studentId]
        ) ?: ['total_due' => 0, 'total_paid' => 0, 'total_waived' => 0, 'balance' => 0];

        $interview = $this->fetchOne(
            "SELECT aa.application_no, ai.scheduled_date, ai.conducted_at, ai.status,
                    ai.academic_readiness_score, ai.behavior_score, ai.communication_score,
                    ai.overall_score, ai.recommendation, ai.remarks
               FROM admission_applications aa
               JOIN admission_interviews ai ON ai.application_id = aa.id
              WHERE aa.enrolled_student_id = ? AND ai.status = 'completed'
              ORDER BY ai.id DESC LIMIT 1",
            [$studentId]
        );
        $finance['financial_relief'] = (new \App\API\Services\ExtraChargeService($this->db))->studentFinancialRelief($studentId);

        $comments = [];

        return [
            'student' => $student,
            'performance' => $subjects,
            'attendance_summary' => $attendance,
            'discipline_summary' => ['count' => count($disciplineRecords), 'records' => $disciplineRecords],
            'activities' => $activities,
            'finance_summary' => $finance,
            'admission_interview' => $interview,
            'teacher_comments' => $comments,
            'recommendations' => [],
        ];
    }

    public function getDisciplineMeta(): array
    {
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT DISTINCT class_stream_id AS id, class_id, stream_name
                                          FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('academic_calendar') . "
                                          WHERE class_stream_status = 'active'
                                          ORDER BY stream_name ASC"),
            'academic_years' => $this->fetchAll("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"),
            'terms' => $this->fetchAll("SELECT academic_year_term_id AS id, academic_year_id, term_name AS name,
                                               term_code AS term_number, term_period_status AS status
                                        FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('academic_term') . "
                                        ORDER BY term_code ASC"),
            'statuses' => ['pending', 'resolved', 'escalated'],
            'severities' => ['low', 'medium', 'high'],
        ];
    }

    public function listDisciplineCases(array $filters = []): array
    {
        // Year-aware (2026-11-15): incidents of the selected year's enrolments
        // (the current year by default). A past year keeps graduated and
        // transferred learners' incidents, so only the current year limits
        // the list to active learners.
        $year = \App\API\Services\YearScope::listYear($this->db, $filters);
        $conditions = ['sae.academic_year_id = ?'];
        $bindings = [$year['year_id']];
        if ($year['is_current']) {
            $conditions[] = "s.status = 'active'";
        }
        $map = [
            'status' => 'di.status = ?',
            'severity' => 'di.severity = ?',
            'class_id' => 'ayc.class_id = ?',
            'stream_id' => 'aycs.stream_id = ?',
        ];

        foreach ($map as $field => $condition) {
            if (!empty($filters[$field])) {
                $conditions[] = $condition;
                $bindings[] = $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ? OR di.description LIKE ?)";
            $term = '%' . trim((string)$filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term);
        }

        $sql = "SELECT di.id, s.id AS student_id, di.incident_date, di.description, di.severity,
                       di.status, di.action_taken, di.created_at,
                       s.admission_no,
                       CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                       c.name AS class_name, st2.name AS stream_name, per.photo_url
                FROM discipline_incidents di
                JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = di.student_academic_enrollment_id
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = sae.student_id
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st2 ON st2.id = aycs.stream_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY di.incident_date DESC, di.created_at DESC";

        return $this->fetchAll($sql, $bindings);
    }

    public function getDisciplineCase(int $caseId): ?array
    {
        $case = $this->fetchOne(
            "SELECT di.*,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS student_name,
                    s.admission_no, per.photo_url, c.name AS class_name, st2.name AS stream_name
             FROM discipline_incidents di
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = di.student_academic_enrollment_id
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("streams") . " st2 ON st2.id = aycs.stream_id
             WHERE di.id = ?
             LIMIT 1",
            [$caseId]
        );
        if (!$case) {
            return null;
        }

        return [
            'case' => $case,
            'student' => [
                'first_name' => $case['student_name'] ?? '',
                'last_name' => '',
                'admission_no' => $case['admission_no'] ?? '',
                'photo_url' => $case['photo_url'] ?? '',
            ],
            'class_name' => $case['class_name'] ?? '',
            'stream_name' => $case['stream_name'] ?? '',
            'reported_by_name' => 'System',
            'resolved_by_name' => '',
        ];
    }

    public function updateDisciplineCase(int $caseId, array $data, int $userId): void
    {
        $updates = [];
        $bindings = [];

        if (!empty($data['status'])) {
            $updates[] = "status = ?";
            $bindings[] = $data['status'];
        }
        if (!empty($data['action_taken'])) {
            $updates[] = "action_taken = ?";
            $bindings[] = $data['action_taken'];
        }
        if (empty($updates)) {
            throw new RuntimeException('bad_request:No valid fields to update');
        }

        $bindings[] = $caseId;
        $stmt = $this->db->prepare("UPDATE discipline_incidents SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($bindings);
    }

    public function getSpecialNeedsMeta(): array
    {
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT class_stream_id AS id, class_id, stream_name FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . " ORDER BY stream_name ASC"),
            'academic_years' => $this->fetchAll("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"),
            'dormitories' => $this->fetchAll("SELECT id, name AS dormitory_name, gender FROM dormitories WHERE status = 'active' ORDER BY name ASC"),
            'statuses' => ['draft', 'active', 'completed', 'archived'],
            'iep_types' => ['learning', 'behavioral', 'physical', 'medical', 'other'],
        ];
    }

    public function listSpecialNeedsIEPs(array $filters = []): array
    {
        $conditions = ["s.status = 'active'"];
        $bindings = [];
        if (!empty($filters['_class_teacher_user_id'])) {
            $conditions[] = "EXISTS (
                SELECT 1
                FROM student_academic_enrollments scoped_sae
                JOIN academic_year_class_streams scoped_aycs ON scoped_aycs.id = scoped_sae.academic_year_class_stream_id
                JOIN vw_teacher_effective_stream_learning_areas ts
                  ON ts.academic_year_class_stream_id = scoped_aycs.id
                 AND ts.scope_type = 'class_teacher'
                JOIN staff scoped_staff ON scoped_staff.id = ts.staff_id
                JOIN users scoped_user ON scoped_user.person_id = scoped_staff.person_id
                WHERE scoped_sae.student_id = s.id
                  AND scoped_sae.enrollment_status = 'active'
                  AND scoped_aycs.status = 'active'
                  AND scoped_user.id = ?
            )";
            $bindings[] = (int) $filters['_class_teacher_user_id'];
        }
        $map = [
            'status' => 'i.status = ?',
            'class_id' => 'ayc.class_id = ?',
            'stream_id' => 'aycs.stream_id = ?',
            'dormitory_id' => 'd.id = ?',
            'academic_year' => 'i.academic_year = ?',
        ];
        foreach ($map as $field => $condition) {
            if (!empty($filters[$field])) {
                $conditions[] = $condition;
                $bindings[] = $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = "(s.admission_no LIKE ? OR per.first_name LIKE ? OR per.last_name LIKE ? OR i.iep_type LIKE ? OR i.special_needs_category LIKE ?)";
            $term = '%' . trim((string)$filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term, $term);
        }

        return $this->fetchAll(
            "SELECT i.id, i.student_id, i.academic_year, i.iep_type, i.special_needs_category,
                    i.goals_summary, i.strategies, i.accommodations, i.progress_monitoring_plan,
                    i.status, i.approved_date, i.created_at,
                    s.admission_no,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS full_name,
                    lp.class_name, lp.stream_name, d.name AS dormitory_name, per.photo_url
             FROM ieps i
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = i.student_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement') . " lp ON lp.student_id = s.id
             LEFT JOIN dormitory_assignments da ON da.student_academic_enrollment_id = lp.enrollment_id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
             LEFT JOIN dormitories d ON d.id = da.dormitory_id
             WHERE " . implode(' AND ', $conditions) . "
             ORDER BY i.created_at DESC",
            $bindings
        );
    }

    public function getSpecialNeedsIepDetail(int $iepId): ?array
    {
        $iep = $this->fetchOne(
            "SELECT i.*,
                    CONCAT_WS(' ', per.first_name, per.middle_name, per.last_name) AS student_name,
                    s.admission_no, per.photo_url, lp.class_name, lp.stream_name, d.name AS dormitory_name,
                    CONCAT_WS(' ', cb.first_name, cb.last_name) AS created_by_name,
                    CONCAT_WS(' ', ab.first_name, ab.last_name) AS approved_by_name
             FROM ieps i
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = i.student_id
             JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('learner_placement') . " lp ON lp.student_id = s.id
             LEFT JOIN dormitory_assignments da ON da.student_academic_enrollment_id = lp.enrollment_id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
             LEFT JOIN dormitories d ON d.id = da.dormitory_id
             LEFT JOIN users cbu ON cbu.id = i.created_by
             LEFT JOIN persons cb ON cb.id = cbu.person_id
             LEFT JOIN users abu ON abu.id = i.approved_by
             LEFT JOIN persons ab ON ab.id = abu.person_id
             WHERE i.id = ?
             LIMIT 1",
            [$iepId]
        );
        if (!$iep) {
            return null;
        }

        return [
            'iep' => $iep,
            'student' => [
                'first_name' => $iep['student_name'] ?? '',
                'last_name' => '',
                'admission_no' => $iep['admission_no'] ?? '',
                'photo_url' => $iep['photo_url'] ?? '',
            ],
            'class_name' => $iep['class_name'] ?? '',
            'stream_name' => $iep['stream_name'] ?? '',
            'created_by_name' => $iep['created_by_name'] ?? 'System',
            'approved_by_name' => $iep['approved_by_name'] ?? '',
        ];
    }

    public function createSpecialNeedsIep(array $data, int $createdByUserId): array
    {
        $studentId = (int)($data['student_id'] ?? 0);
        if ($studentId <= 0) {
            throw new RuntimeException('A student must be selected.');
        }

        $student = $this->fetchOne(
            "SELECT s.id, s.admission_no FROM students s WHERE s.id = ? AND s.status = 'active' LIMIT 1",
            [$studentId]
        );
        if (!$student) {
            throw new RuntimeException('The selected student is not an active learner.');
        }

        $goalsSummary = trim((string)($data['goals_summary'] ?? ''));
        if ($goalsSummary === '') {
            throw new RuntimeException('Goals summary is required.');
        }

        $academicYear = trim((string)($data['academic_year'] ?? ''));
        $yearCode = $academicYear !== '' ? $academicYear : null;
        if ($yearCode === null) {
            $row = $this->fetchOne(
                "SELECT year_code FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
            );
            $yearCode = $row['year_code'] ?? null;
        }

        $iepType = trim((string)($data['iep_type'] ?? ''));
        $category = trim((string)($data['special_needs_category'] ?? ''));
        $status = in_array(($data['status'] ?? ''), ['draft', 'active', 'completed', 'archived'], true)
            ? $data['status']
            : 'draft';

        $stmt = $this->db->prepare(
            "INSERT INTO ieps
                (student_id, academic_year, iep_type, special_needs_category,
                 goals_summary, strategies, accommodations, progress_monitoring_plan,
                 created_by, approved_by, approved_date, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?)"
        );
        $stmt->execute([
            $studentId,
            $yearCode,
            $iepType !== '' ? $iepType : null,
            $category !== '' ? $category : null,
            $goalsSummary,
            trim((string)($data['strategies'] ?? '')),
            trim((string)($data['accommodations'] ?? '')),
            trim((string)($data['progress_monitoring_plan'] ?? '')),
            $createdByUserId,
            $status,
        ]);

        return [
            'id' => (int)$this->db->lastInsertId(),
            'student_id' => $studentId,
            'admission_no' => $student['admission_no'] ?? '',
            'academic_year' => $yearCode,
            'iep_type' => $iepType,
            'special_needs_category' => $category,
            'status' => $status,
        ];
    }

    private function resolveAcademicYear($academicYearVal): array
    {
        if ($academicYearVal !== null && $academicYearVal !== '') {
            if (is_numeric($academicYearVal)) {
                if ((int)$academicYearVal > 2000 && (int)$academicYearVal < 2100) {
                    $yearCode = (string)$academicYearVal;
                    $yearId = $this->fetchValue("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1", [$yearCode]);
                    return [$yearId ? (int)$yearId : null, $yearCode];
                }

                $yearId = (int)$academicYearVal;
                $yearCode = $this->fetchValue("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1", [$yearId]);
                return [$yearId, $yearCode ?: null];
            }

            $yearCode = (string)$academicYearVal;
            $yearId = $this->fetchValue("SELECT id FROM academic_years WHERE year_code = ? LIMIT 1", [$yearCode]);
            return [$yearId ? (int)$yearId : null, $yearCode];
        }

        $row = $this->fetchOne("SELECT id, year_code FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1");
        return $row ? [(int)$row['id'], $row['year_code']] : [null, null];
    }

    private function fetchSubjectPerformance(int $studentId, ?int $termId, ?int $yearId): array
    {
        $subjects = [];
        if ($termId !== null) {
            $subjects = $this->fetchAll(
                "SELECT tss.subject_id,
                        COALESCE(la.name, CONCAT('Subject ', tss.subject_id)) AS subject,
                        tss.overall_percentage AS score, tss.overall_grade AS grade,
                        class_subject_avg.class_average AS classAverage,
                        NULL AS position, NULL AS teacher, NULL AS remarks
                 FROM " . ReadReplicaService::qualifiedRef("term_subject_scores") . " tss
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("learning_areas") . " la ON la.id = tss.subject_id
                 LEFT JOIN (
                    SELECT subject_id, ROUND(AVG(overall_percentage), 2) AS class_average
                    FROM " . ReadReplicaService::qualifiedRef("term_subject_scores") . "
                    WHERE term_id = ?
                    GROUP BY subject_id
                 ) class_subject_avg ON class_subject_avg.subject_id = tss.subject_id
                 WHERE tss.student_id = ? AND tss.term_id = ?
                 ORDER BY subject ASC",
                [$termId, $studentId, $termId]
            );
        }

        if (empty($subjects)) {
            $sql = "SELECT a.learning_area_id AS subject_id,
                           COALESCE(la.name, CONCAT('Subject ', a.learning_area_id)) AS subject,
                           ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2) AS score,
                           NULL AS grade, NULL AS classAverage, NULL AS position, NULL AS teacher,
                           MIN(ar.remarks) AS remarks
                    FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
                    JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
                    JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = ar.student_academic_enrollment_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("learning_areas") . " la ON la.id = a.learning_area_id
                    WHERE ae.student_id = ?";
            $bindings = [$studentId];
            if ($termId !== null) {
                $sql .= " AND a.academic_year_term_id = ?";
                $bindings[] = $termId;
            }
            if ($yearId !== null) {
                $sql .= " AND ae.academic_year_id = ?";
                $bindings[] = $yearId;
            }
            $sql .= " GROUP BY a.learning_area_id ORDER BY subject ASC";
            $subjects = $this->fetchAll($sql, $bindings);
        }

        foreach ($subjects as &$subject) {
            if ($subject['grade'] === null && $subject['score'] !== null) {
                $subject['grade'] = $this->deriveGradeFromPercentage($subject['score']);
            }
        }
        unset($subject);

        return $subjects;
    }

    private function fetchAttendanceSummary(int $studentId, ?int $termId, ?int $yearId): array
    {
        $conditions = ["ae.student_id = ?"];
        $bindings = [$studentId];
        if ($termId !== null) {
            $conditions[] = "sa.date BETWEEN (SELECT opening_date FROM academic_year_terms WHERE id = ?) AND (SELECT closing_date FROM academic_year_terms WHERE id = ?)";
            array_push($bindings, $termId, $termId);
        }
        if ($yearId !== null) {
            $conditions[] = "ae.academic_year_id = ?";
            $bindings[] = $yearId;
        }

        return $this->fetchOne(
            "SELECT COUNT(CASE WHEN sa.status = 'present' THEN 1 END) as days_present,
                    COUNT(CASE WHEN sa.status = 'absent' THEN 1 END) as days_absent,
                    COUNT(CASE WHEN sa.status = 'late' THEN 1 END) as days_late,
                    ROUND((COUNT(CASE WHEN sa.status = 'present' OR sa.status = 'late' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
             FROM " . ReadReplicaService::qualifiedRef("student_attendance") . " sa
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " ae ON ae.id = sa.student_academic_enrollment_id
             WHERE " . implode(' AND ', $conditions),
            $bindings
        ) ?: ['days_present' => 0, 'days_absent' => 0, 'days_late' => 0, 'attendance_rate' => 100.00];
    }

    private function aggregatePerformanceRows(array $studentRows, string $viewMode): array
    {
        if (!in_array($viewMode, ['class', 'stream', 'school'], true)) {
            return $studentRows;
        }

        if ($viewMode === 'school') {
            return [$this->summarizePerformanceGroup($studentRows) + ['scope' => 'Whole School']];
        }

        $groups = [];
        foreach ($studentRows as $row) {
            $key = $viewMode === 'class'
                ? ($row['class_name'] ?? 'Unassigned')
                : (($row['class_name'] ?? 'Unassigned') . ' - ' . ($row['stream_name'] ?? 'Unassigned'));
            if (!isset($groups[$key])) {
                $groups[$key] = [];
            }
            $groups[$key][] = $row;
        }

        $result = [];
        foreach ($groups as $rows) {
            $summary = $this->summarizePerformanceGroup($rows);
            $summary['class_name'] = $rows[0]['class_name'] ?? 'Unassigned';
            if ($viewMode === 'stream') {
                $summary['stream_name'] = $rows[0]['stream_name'] ?? 'Unassigned';
            }
            $result[] = $summary;
        }

        return $result;
    }

    private function summarizePerformanceGroup(array $rows): array
    {
        $summary = [
            'total_students' => count($rows),
            'average_score' => 0,
            'grade' => 'E',
            'attendance_rate' => 100,
            'fee_balance' => 0,
            'discipline_cases' => 0,
            'activities_count' => 0,
        ];
        if (empty($rows)) {
            return $summary;
        }

        $scoreCount = 0;
        $scoreSum = 0.0;
        $attendanceCount = 0;
        $attendanceSum = 0.0;
        foreach ($rows as $row) {
            if ($row['average_score'] !== null) {
                $scoreSum += (float)$row['average_score'];
                $scoreCount++;
            }
            if ($row['attendance_rate'] !== null) {
                $attendanceSum += (float)$row['attendance_rate'];
                $attendanceCount++;
            }
            $summary['fee_balance'] += (float)$row['fee_balance'];
            $summary['discipline_cases'] += (int)$row['discipline_cases'];
            $summary['activities_count'] += (int)$row['activities_count'];
        }

        $summary['average_score'] = $scoreCount > 0 ? round($scoreSum / $scoreCount, 2) : 0;
        $summary['grade'] = $this->deriveGradeFromPercentage($summary['average_score']);
        $summary['attendance_rate'] = $attendanceCount > 0 ? round($attendanceSum / $attendanceCount, 2) : 100;

        return $summary;
    }

    private function deriveGradeFromPercentage($score): string
    {
        if ($score === null) {
            return '-';
        }
        $score = (float)$score;
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'E';
    }

    private function fetchAll(string $sql, array $bindings = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchOne(string $sql, array $bindings = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function fetchValue(string $sql, array $bindings = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->fetchColumn();
    }
}
