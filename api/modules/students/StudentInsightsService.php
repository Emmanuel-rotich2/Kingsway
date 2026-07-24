<?php
declare(strict_types=1);

namespace App\API\Modules\students;

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
            $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT
                    s.id, s.admission_no,
                    CONCAT(s.first_name, ' ', COALESCE(s.middle_name,''), ' ', s.last_name) AS full_name,
                    s.first_name, s.last_name, s.gender, s.date_of_birth, s.status,
                    st.name AS stream_name,
                    hr.disability_notes, hr.chronic_conditions, hr.allergies,
                    hr.special_diet, hr.blood_group, hr.notes AS health_notes
                FROM students s
                LEFT JOIN streams st ON st.id = s.stream_id
                LEFT JOIN student_health_records hr ON hr.student_id = s.id
                WHERE s.status = 'active' AND {$whereClause}
                ORDER BY s.first_name, s.last_name
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($params, [$limit, $offset]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $countSql = "SELECT COUNT(*) FROM students s
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
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC"),
            'academic_years' => $this->fetchAll("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"),
            'terms' => $this->fetchAll("SELECT id, academic_year_id, name, term_number, status FROM academic_terms ORDER BY term_number ASC"),
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
            $conditions[] = "cs.class_id = ?";
            $bindings[] = $classId;
        }
        if ($streamId !== null) {
            $conditions[] = "s.stream_id = ?";
            $bindings[] = $streamId;
        }
        if ($gender !== null) {
            $conditions[] = "s.gender = ?";
            $bindings[] = $gender;
        }
        if ($search !== '') {
            $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) LIKE ?)";
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
                CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                c.name AS class_name,
                cs.stream_name,
                s.gender,
                COALESCE((
                    SELECT ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2)
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    WHERE ar.student_id = s.id AND (? IS NULL OR a.term_id = ?)
                ), 0.00) AS average_score,
                COALESCE((
                    SELECT ROUND(COUNT(CASE WHEN status IN ('present', 'late') THEN 1 END) / COUNT(*) * 100, 2)
                    FROM student_attendance
                    WHERE student_id = s.id AND (? IS NULL OR academic_year_id = ?)
                ), 100.00) AS attendance_rate,
                COALESCE((
                    SELECT SUM(balance)
                    FROM student_fee_obligations
                    WHERE student_id = s.id AND (? IS NULL OR academic_year = ?)
                ), 0.00) AS fee_balance,
                (SELECT COUNT(*) FROM student_discipline WHERE student_id = s.id) AS discipline_cases,
                (SELECT COUNT(*) FROM activity_participants WHERE student_id = s.id) AS activities_count,
                '-' AS position
            FROM students s
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN classes c ON c.id = cs.class_id
            WHERE " . implode(' AND ', $conditions);

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$termId, $termId, $yearId, $yearId, $yearCode, $yearCode], $bindings));
        $studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($studentRows as &$row) {
            $row['grade'] = $this->deriveGradeFromPercentage($row['average_score']);
        }
        unset($row);

        return $this->aggregatePerformanceRows($studentRows, $viewMode);
    }

    public function getPerformanceFull(int $studentId, array $user, string $context, array $filters = []): ?array
    {
        $scope = $this->studentService->scopeService->buildScope($context, $user);
        if (!$this->studentService->scopeService->canAccessStudent($studentId, $scope)) {
            throw new RuntimeException('forbidden:You do not have permission to view this student.');
        }

        $student = $this->fetchOne("
            SELECT s.id, s.admission_no, s.first_name, s.middle_name, s.last_name,
                   CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                   s.gender, s.photo_url, c.name AS class_name, cs.stream_name
            FROM students s
            LEFT JOIN class_streams cs ON cs.id = s.stream_id
            LEFT JOIN classes c ON c.id = cs.class_id
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
            "SELECT id, incident_date AS date, description AS case_title, severity, status, action_taken
             FROM student_discipline WHERE student_id = ? ORDER BY incident_date DESC",
            [$studentId]
        );
        $activities = $this->fetchAll(
            "SELECT ap.activity_id as id, ac.name as title, ap.joined_at
             FROM activity_participants ap
             LEFT JOIN activity_categories ac ON ac.id = ap.activity_id
             WHERE ap.student_id = ?
             ORDER BY ap.joined_at DESC",
            [$studentId]
        );
        $finance = $this->fetchOne(
            "SELECT COALESCE(SUM(amount_due), 0) as total_due,
                    COALESCE(SUM(amount_paid), 0) as total_paid,
                    COALESCE(SUM(amount_waived), 0) as total_waived,
                    COALESCE(SUM(balance), 0) as balance
             FROM student_fee_obligations
             WHERE student_id = ?",
            [$studentId]
        ) ?: ['total_due' => 0, 'total_paid' => 0, 'total_waived' => 0, 'balance' => 0];
        $enrollment = $this->fetchOne(
            "SELECT teacher_comments, head_teacher_comments, special_notes
             FROM class_enrollments
             WHERE student_id = ?
             ORDER BY id DESC
             LIMIT 1",
            [$studentId]
        ) ?: [];

        $comments = [];
        if (!empty($enrollment['teacher_comments'])) {
            $comments[] = ['teacher' => 'Class Teacher', 'comment' => $enrollment['teacher_comments']];
        }
        if (!empty($enrollment['head_teacher_comments'])) {
            $comments[] = ['teacher' => 'Head Teacher', 'comment' => $enrollment['head_teacher_comments']];
        }

        return [
            'student' => $student,
            'performance' => $subjects,
            'attendance_summary' => $attendance,
            'discipline_summary' => ['count' => count($disciplineRecords), 'records' => $disciplineRecords],
            'activities' => $activities,
            'finance_summary' => $finance,
            'teacher_comments' => $comments,
            'recommendations' => !empty($enrollment['special_notes']) ? [$enrollment['special_notes']] : [],
        ];
    }

    public function getDisciplineMeta(): array
    {
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC"),
            'academic_years' => $this->fetchAll("SELECT id, year_code, year_name, is_current FROM academic_years ORDER BY is_current DESC, year_code DESC"),
            'terms' => $this->fetchAll("SELECT id, academic_year_id, name, term_number, status FROM academic_terms ORDER BY term_number ASC"),
            'statuses' => ['pending', 'resolved', 'escalated'],
            'severities' => ['low', 'medium', 'high'],
        ];
    }

    public function listDisciplineCases(array $filters = []): array
    {
        $conditions = ["s.status = 'active'"];
        $bindings = [];
        $map = [
            'status' => 'sd.status = ?',
            'severity' => 'sd.severity = ?',
            'class_id' => 'cs.class_id = ?',
            'stream_id' => 's.stream_id = ?',
        ];

        foreach ($map as $field => $condition) {
            if (!empty($filters[$field])) {
                $conditions[] = $condition;
                $bindings[] = $filters[$field];
            }
        }
        if (!empty($filters['search'])) {
            $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR sd.description LIKE ?)";
            $term = '%' . trim((string)$filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term);
        }

        $sql = "SELECT sd.id, sd.student_id, sd.incident_date, sd.description, sd.severity,
                       sd.status, sd.action_taken, sd.resolution_date, sd.created_at,
                       s.admission_no,
                       CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                       c.name AS class_name, cs.stream_name, s.photo_url
                FROM student_discipline sd
                JOIN students s ON s.id = sd.student_id
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY sd.incident_date DESC, sd.created_at DESC";

        return $this->fetchAll($sql, $bindings);
    }

    public function getDisciplineCase(int $caseId): ?array
    {
        $case = $this->fetchOne(
            "SELECT sd.*,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.admission_no, s.photo_url, c.name AS class_name, cs.stream_name,
                    CONCAT_WS(' ', res.first_name, res.last_name) AS resolved_by_name
             FROM student_discipline sd
             JOIN students s ON s.id = sd.student_id
             LEFT JOIN class_streams cs ON cs.id = s.stream_id
             LEFT JOIN classes c ON c.id = cs.class_id
             LEFT JOIN users res ON res.id = sd.resolved_by
             WHERE sd.id = ?
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
            'reported_by_name' => $case['reported_by_name'] ?? 'System',
            'resolved_by_name' => $case['resolved_by_name'] ?? '',
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
        if (($data['status'] ?? null) === 'resolved') {
            $updates[] = "resolution_date = CURDATE()";
            $updates[] = "resolved_by = ?";
            $bindings[] = $userId;
        }
        if (empty($updates)) {
            throw new RuntimeException('bad_request:No valid fields to update');
        }

        $bindings[] = $caseId;
        $stmt = $this->db->prepare("UPDATE student_discipline SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($bindings);
    }

    public function getSpecialNeedsMeta(): array
    {
        return [
            'classes' => $this->fetchAll("SELECT id, name FROM classes ORDER BY name ASC"),
            'streams' => $this->fetchAll("SELECT id, class_id, stream_name FROM class_streams ORDER BY stream_name ASC"),
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
        $map = [
            'status' => 'i.status = ?',
            'class_id' => 'cs.class_id = ?',
            'stream_id' => 's.stream_id = ?',
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
            $conditions[] = "(s.admission_no LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR i.iep_type LIKE ? OR i.special_needs_category LIKE ?)";
            $term = '%' . trim((string)$filters['search']) . '%';
            array_push($bindings, $term, $term, $term, $term, $term);
        }

        return $this->fetchAll(
            "SELECT i.id, i.student_id, i.academic_year, i.iep_type, i.special_needs_category,
                    i.goals_summary, i.strategies, i.accommodations, i.progress_monitoring_plan,
                    i.status, i.approved_date, i.created_at,
                    s.admission_no,
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS full_name,
                    c.name AS class_name, cs.stream_name, d.name AS dormitory_name, s.photo_url
             FROM ieps i
             JOIN students s ON s.id = i.student_id
             LEFT JOIN class_streams cs ON cs.id = s.stream_id
             LEFT JOIN classes c ON c.id = cs.class_id
             LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
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
                    CONCAT_WS(' ', s.first_name, s.middle_name, s.last_name) AS student_name,
                    s.admission_no, s.photo_url, c.name AS class_name, cs.stream_name, d.name AS dormitory_name,
                    CONCAT_WS(' ', cb.first_name, cb.last_name) AS created_by_name,
                    CONCAT_WS(' ', ab.first_name, ab.last_name) AS approved_by_name
             FROM ieps i
             JOIN students s ON s.id = i.student_id
             LEFT JOIN class_streams cs ON cs.id = s.stream_id
             LEFT JOIN classes c ON c.id = cs.class_id
             LEFT JOIN dormitory_assignments da ON da.student_id = s.id AND da.status = 'active' AND (da.end_date IS NULL OR da.end_date >= CURDATE())
             LEFT JOIN dormitories d ON d.id = da.dormitory_id
             LEFT JOIN users cb ON cb.id = i.created_by
             LEFT JOIN users ab ON ab.id = i.approved_by
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

        $row = $this->fetchOne("SELECT id, year_code FROM academic_years WHERE is_current = 1 OR status = 'active' ORDER BY is_current DESC, id DESC LIMIT 1");
        return $row ? [(int)$row['id'], $row['year_code']] : [null, null];
    }

    private function fetchSubjectPerformance(int $studentId, ?int $termId, ?int $yearId): array
    {
        $subjects = [];
        if ($termId !== null) {
            $subjects = $this->fetchAll(
                "SELECT tss.subject_id,
                        COALESCE(la.name, cu.name, CONCAT('Subject ', tss.subject_id)) AS subject,
                        tss.overall_percentage AS score, tss.overall_grade AS grade,
                        class_subject_avg.class_average AS classAverage,
                        NULL AS position, NULL AS teacher, NULL AS remarks
                 FROM term_subject_scores tss
                 LEFT JOIN learning_areas la ON la.id = tss.subject_id
                 LEFT JOIN curriculum_units cu ON cu.id = tss.subject_id
                 LEFT JOIN (
                    SELECT subject_id, ROUND(AVG(overall_percentage), 2) AS class_average
                    FROM term_subject_scores
                    WHERE term_id = ?
                    GROUP BY subject_id
                 ) class_subject_avg ON class_subject_avg.subject_id = tss.subject_id
                 WHERE tss.student_id = ? AND tss.term_id = ?
                 ORDER BY subject ASC",
                [$termId, $studentId, $termId]
            );
        }

        if (empty($subjects)) {
            $sql = "SELECT a.subject_id,
                           COALESCE(la.name, cu.name, CONCAT('Subject ', a.subject_id)) AS subject,
                           ROUND(AVG(ar.marks_obtained / a.max_marks * 100), 2) AS score,
                           NULL AS grade, NULL AS classAverage, NULL AS position, NULL AS teacher,
                           MIN(ar.remarks) AS remarks
                    FROM assessment_results ar
                    JOIN assessments a ON a.id = ar.assessment_id
                    LEFT JOIN learning_areas la ON la.id = a.subject_id
                    LEFT JOIN curriculum_units cu ON cu.id = a.subject_id
                    WHERE ar.student_id = ?";
            $bindings = [$studentId];
            if ($termId !== null) {
                $sql .= " AND a.term_id = ?";
                $bindings[] = $termId;
            }
            if ($yearId !== null) {
                $sql .= " AND a.academic_year_id = ?";
                $bindings[] = $yearId;
            }
            $sql .= " GROUP BY a.subject_id ORDER BY subject ASC";
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
        $conditions = ["student_id = ?"];
        $bindings = [$studentId];
        if ($termId !== null) {
            $conditions[] = "term_id = ?";
            $bindings[] = $termId;
        }
        if ($yearId !== null) {
            $conditions[] = "academic_year_id = ?";
            $bindings[] = $yearId;
        }

        return $this->fetchOne(
            "SELECT COUNT(CASE WHEN status = 'present' THEN 1 END) as days_present,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as days_absent,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as days_late,
                    ROUND((COUNT(CASE WHEN status = 'present' OR status = 'late' THEN 1 END) / COUNT(*)) * 100, 2) as attendance_rate
             FROM student_attendance
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
