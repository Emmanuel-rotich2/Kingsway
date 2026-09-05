<?php
namespace App\API\Modules\counseling;

use App\API\Includes\BaseAPI;
use App\API\Services\DashboardPeriodService;
use PDO;
use Exception;

/**
 * CounselingAPI
 * Handles counseling business logic for both student and staff counselees.
 *
 * Schema (migration 016): counseling_cases + counseling_sessions
 * - counseling_cases.counselee_type = 'student' | 'staff'
 * - sessions belong to a case via case_id
 */
class CounselingAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('counseling');
    }

    /**
     * Get summary statistics for counseling cases and sessions.
     * @return array
     */
    public function getSummary(array $filters = [])
    {
        try {
            $range = DashboardPeriodService::resolve($this->db, $filters);
            $dateFrom = $range['date_from'];
            $dateTo = $range['date_to'];

            $summaryStmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_cases,
                        SUM(status IN ('open', 'in_progress')) AS open_cases,
                        SUM(priority = 'urgent' AND status IN ('open', 'in_progress')) AS urgent_cases,
                        SUM(next_follow_up_at IS NOT NULL
                            AND next_follow_up_at <= NOW()
                            AND status IN ('open', 'in_progress')) AS follow_ups_due,
                        SUM(status = 'resolved') AS resolved_cases,
                        SUM(status = 'closed') AS closed_cases
                 FROM counseling_cases
                 WHERE DATE(opened_at) BETWEEN ? AND ?"
            );
            $summaryStmt->execute([$dateFrom, $dateTo]);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $sessionStmt = $this->db->prepare(
                "SELECT COUNT(*) AS sessions_this_month
                 FROM counseling_sessions
                 WHERE session_date BETWEEN ? AND ?"
            );
            $sessionStmt->execute([$dateFrom, $dateTo]);
            $sessionsThisMonth = (int) ($sessionStmt->fetchColumn() ?: 0);

            $typeStmt = $this->db->prepare(
                "SELECT case_type, COUNT(*) AS case_count
                 FROM counseling_cases
                 WHERE status IN ('open', 'in_progress') AND DATE(opened_at) BETWEEN ? AND ?
                 GROUP BY case_type
                 ORDER BY case_count DESC, case_type"
            );
            $typeStmt->execute([$dateFrom, $dateTo]);

            $trendStmt = $this->db->prepare(
                "SELECT DATE_FORMAT(session_date, '%Y-%m') AS month,
                        COUNT(*) AS session_count
                 FROM counseling_sessions
                 WHERE session_date BETWEEN ? AND ?
                 GROUP BY DATE_FORMAT(session_date, '%Y-%m')
                 ORDER BY month"
            );
            $trendStmt->execute([$dateFrom, $dateTo]);

            $caseStmt = $this->db->prepare(
                "SELECT c.id, c.case_code, c.title, c.counselee_type, c.case_type,
                        c.priority, c.status, c.next_follow_up_at, c.opened_at,
                        COALESCE(
                            CONCAT_WS(' ', stp.first_name, stp.last_name),
                            CONCAT_WS(' ', sp.first_name, sp.last_name)
                        ) AS counselee_name,
                        s.admission_no, st.staff_no
                 FROM counseling_cases c
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 WHERE c.status IN ('open', 'in_progress') AND DATE(c.opened_at) BETWEEN ? AND ?
                 ORDER BY FIELD(c.priority, 'urgent', 'high', 'medium', 'low'),
                          COALESCE(c.next_follow_up_at, c.created_at), c.id DESC
                 LIMIT 20"
            );
            $caseStmt->execute([$dateFrom, $dateTo]);

            $followStmt = $this->db->prepare(
                "SELECT c.id, c.case_code, c.title, c.counselee_type, c.case_type,
                        c.priority, c.status, c.next_follow_up_at,
                        COALESCE(
                            CONCAT_WS(' ', stp.first_name, stp.last_name),
                            CONCAT_WS(' ', sp.first_name, sp.last_name)
                        ) AS counselee_name,
                        s.admission_no, st.staff_no
                 FROM counseling_cases c
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 WHERE c.next_follow_up_at IS NOT NULL
                   AND c.next_follow_up_at <= DATE_ADD(NOW(), INTERVAL 14 DAY)
                   AND c.status IN ('open', 'in_progress')
                   AND DATE(c.opened_at) BETWEEN ? AND ?
                 ORDER BY c.next_follow_up_at, FIELD(c.priority, 'urgent', 'high', 'medium', 'low')
                 LIMIT 20"
            );
            $followStmt->execute([$dateFrom, $dateTo]);

            $periodStmt = $this->db->prepare("SELECT COUNT(*) FROM counseling_sessions WHERE session_date BETWEEN ? AND ?");
            $periodStmt->execute([$dateFrom, $dateTo]);
            $totalSessions = (int) $periodStmt->fetchColumn();
            $scheduledStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM counseling_sessions
                 WHERE session_date BETWEEN ? AND ?"
            );
            $scheduledStmt->execute([$dateFrom, $dateTo]);
            $scheduled = (int) $scheduledStmt->fetchColumn();
            $referralStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM counseling_cases
                 WHERE referral_source IS NOT NULL AND referral_source <> ''
                   AND DATE(opened_at) BETWEEN ? AND ?"
            );
            $referralStmt->execute([$dateFrom, $dateTo]);
            $referrals = (int) $referralStmt->fetchColumn();
            $counseleeStmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT IF(counselee_type = 'staff', staff_id, student_id))
                 FROM counseling_cases
                 WHERE status IN ('open', 'in_progress') AND DATE(opened_at) BETWEEN ? AND ?"
            );
            $counseleeStmt->execute([$dateFrom, $dateTo]);
            $activeCounselees = (int) $counseleeStmt->fetchColumn();

            $referralSourceStmt = $this->db->prepare(
                "SELECT COALESCE(NULLIF(referral_source, ''), 'Not specified') AS source,
                        COUNT(*) AS case_count
                 FROM counseling_cases
                 WHERE DATE(opened_at) BETWEEN ? AND ?
                 GROUP BY referral_source
                 ORDER BY case_count DESC, source
                 LIMIT 10"
            );
            $referralSourceStmt->execute([$dateFrom, $dateTo]);

            $gradeStmt = $this->db->prepare(
                "SELECT COALESCE(cls.name, IF(c.counselee_type = 'staff', 'Staff', 'Unassigned')) AS grade,
                        COUNT(*) AS case_count
                 FROM counseling_cases c
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN student_academic_enrollments sae
                     ON sae.student_id = c.student_id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 WHERE DATE(c.opened_at) BETWEEN ? AND ?
                 GROUP BY grade
                 ORDER BY case_count DESC, grade
                 LIMIT 10"
            );
            $gradeStmt->execute([$dateFrom, $dateTo]);

            $statusStmt = $this->db->prepare(
                "SELECT status, COUNT(*) AS case_count
                 FROM counseling_cases
                 WHERE DATE(opened_at) BETWEEN ? AND ?
                 GROUP BY status
                 ORDER BY case_count DESC, status"
            );
            $statusStmt->execute([$dateFrom, $dateTo]);

            $recentSessionsStmt = $this->db->prepare(
                "SELECT cs.id, cs.case_id, cs.session_date, cs.session_type, cs.summary,
                        c.case_code, c.case_type, c.counselee_type, c.priority, c.status,
                        COALESCE(
                            CONCAT_WS(' ', stp.first_name, stp.last_name),
                            CONCAT_WS(' ', sp.first_name, sp.last_name)
                        ) AS counselee_name,
                        CONCAT_WS(' ', up.first_name, up.last_name) AS counselor_name
                 FROM counseling_sessions cs
                 JOIN counseling_cases c ON c.id = cs.case_id
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 LEFT JOIN users u ON u.id = c.assigned_to
                 LEFT JOIN persons up ON up.id = u.person_id
                 WHERE cs.session_date BETWEEN ? AND ?
                 ORDER BY cs.session_date DESC, cs.id DESC
                 LIMIT 8"
            );
            $recentSessionsStmt->execute([$dateFrom, $dateTo]);

            $recentReferralsStmt = $this->db->prepare(
                "SELECT c.id, c.case_code, c.case_type, c.title, c.referral_source,
                        c.priority, c.status, c.opened_at,
                        COALESCE(
                            CONCAT_WS(' ', stp.first_name, stp.last_name),
                            CONCAT_WS(' ', sp.first_name, sp.last_name)
                        ) AS counselee_name,
                        s.admission_no,
                        CONCAT_WS(' ', op.first_name, op.last_name) AS referred_by
                 FROM counseling_cases c
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 LEFT JOIN users uo ON uo.id = c.opened_by
                 LEFT JOIN persons op ON op.id = uo.person_id
                 WHERE c.referral_source IS NOT NULL AND c.referral_source <> ''
                   AND DATE(c.opened_at) BETWEEN ? AND ?
                 ORDER BY c.opened_at DESC
                 LIMIT 10"
            );
            $recentReferralsStmt->execute([$dateFrom, $dateTo]);

            $byType = $typeStmt->fetchAll(PDO::FETCH_ASSOC);
            $trend = $trendStmt->fetchAll(PDO::FETCH_ASSOC);
            $openCases = (int) ($summary['open_cases'] ?? 0);

            $this->logAction('read', null, 'Fetched counseling summary');
            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => (int) ($summary['total_cases'] ?? 0),
                    'scheduled' => $scheduled,
                    'completed' => (int) ($summary['resolved_cases'] ?? 0)
                        + (int) ($summary['closed_cases'] ?? 0),
                    'active' => $openCases,
                    'open_cases' => $openCases,
                    'urgent_cases' => (int) ($summary['urgent_cases'] ?? 0),
                    'follow_ups_due' => (int) ($summary['follow_ups_due'] ?? 0),
                    'sessions_this_month' => $sessionsThisMonth,
                    'total_sessions' => $totalSessions,
                    'active_students' => $activeCounselees,
                    'referrals' => $referrals,
                    'by_type' => $byType,
                    'session_trend' => $trend,
                    'referrals_by_source' => $referralSourceStmt->fetchAll(PDO::FETCH_ASSOC),
                    'cases_by_grade' => $gradeStmt->fetchAll(PDO::FETCH_ASSOC),
                    'by_status' => $statusStmt->fetchAll(PDO::FETCH_ASSOC),
                    'recent_sessions' => $recentSessionsStmt->fetchAll(PDO::FETCH_ASSOC),
                    'recent_referrals' => $recentReferralsStmt->fetchAll(PDO::FETCH_ASSOC),
                    'active_cases' => $caseStmt->fetchAll(PDO::FETCH_ASSOC),
                    'follow_ups' => $followStmt->fetchAll(PDO::FETCH_ASSOC),
                    'meta' => ['filters' => $range],
                ],
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Dashboard stats: sessions in the last 7 days, distinct counselees seen,
     * and open/in-progress cases (pending follow-ups/referrals).
     */
    public function getStats()
    {
        try {
            $stats = ['sessions_this_week' => 0, 'students_seen' => 0, 'pending_referrals' => 0];

            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM counseling_sessions WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $stats['sessions_this_week'] = (int) ($stmt->fetchColumn() ?: 0);

            $stmt = $this->db->query(
                "SELECT COUNT(DISTINCT IF(c.counselee_type = 'staff', c.staff_id, c.student_id))
                 FROM counseling_sessions cs
                 JOIN counseling_cases c ON c.id = cs.case_id
                 WHERE cs.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $stats['students_seen'] = (int) ($stmt->fetchColumn() ?: 0);

            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM counseling_cases WHERE status IN ('open', 'in_progress')"
            );
            $stats['pending_referrals'] = (int) ($stmt->fetchColumn() ?: 0);

            return $this->response([
                'status' => 'success',
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Recent sessions with counselee identity, ordered by session date.
     */
    public function getRecentSessions($limit = 20, $sort = 'DESC')
    {
        try {
            $limit = max(1, min(100, (int) $limit));
            $sort = $sort === 'ASC' ? 'ASC' : 'DESC';
            $stmt = $this->db->prepare(
                "SELECT cs.*, c.counselee_type,
                        CONCAT_WS(' ', p.first_name, p.last_name) AS student_name,
                        s.admission_no,
                        CONCAT_WS(' ', stp.first_name, stp.last_name) AS staff_name,
                        st.staff_no,
                        c.case_type, c.status AS case_status
                 FROM counseling_sessions cs
                 JOIN counseling_cases c ON c.id = cs.case_id
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons p ON p.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 ORDER BY cs.session_date {$sort} LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $rows ?: [],
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * List counseling sessions (students and staff) with pagination and filtering.
     * @param array $params
     * @return array
     */
    public function list($params = [])
    {
        try {
            $search = $params['search'] ?? '';
            $status = $params['status'] ?? '';
            $category = $params['category'] ?? '';
            $date = $params['date'] ?? '';
            $counseleeType = $params['counselee_type'] ?? $params['counseleeType'] ?? '';
            $page = max(1, intval($params['page'] ?? 1));
            $limit = max(1, min(100, intval($params['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            $joins = "FROM counseling_sessions cs
                      INNER JOIN counseling_cases c ON c.id = cs.case_id
                      LEFT JOIN students s ON s.id = c.student_id
                      LEFT JOIN persons sp ON sp.id = s.person_id
                      LEFT JOIN staff st ON st.id = c.staff_id
                      LEFT JOIN persons stp ON stp.id = st.person_id
                      LEFT JOIN student_academic_enrollments sae
                          ON sae.student_id = c.student_id AND sae.enrollment_status = 'active'
                      LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                      LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                      LEFT JOIN classes cls ON cls.id = ayc.class_id
                      LEFT JOIN users u ON u.id = c.assigned_to
                      LEFT JOIN persons up ON up.id = u.person_id";

            $where = " WHERE 1=1";
            $bindings = [];

            if (!empty($search)) {
                $where .= " AND (c.case_code LIKE ?
                               OR c.title LIKE ?
                               OR CONCAT_WS(' ', sp.first_name, sp.last_name) LIKE ?
                               OR CONCAT_WS(' ', stp.first_name, stp.last_name) LIKE ?)";
                $term = '%' . $search . '%';
                array_push($bindings, $term, $term, $term, $term);
            }
            if (!empty($status)) {
                $where .= " AND c.status = ?";
                $bindings[] = $status;
            }
            if (!empty($category)) {
                $where .= " AND c.case_type = ?";
                $bindings[] = $category;
            }
            if (!empty($counseleeType)) {
                $where .= " AND c.counselee_type = ?";
                $bindings[] = $counseleeType;
            }
            if (!empty($date)) {
                $where .= " AND DATE(cs.session_date) = ?";
                $bindings[] = $date;
            }

            $countStmt = $this->db->prepare("SELECT COUNT(*) AS total {$joins} {$where}");
            $countStmt->execute($bindings);
            $total = (int) $countStmt->fetchColumn();

            $select = "SELECT
                            cs.id, cs.case_id, cs.session_date, cs.session_type, cs.summary,
                            cs.action_plan, cs.confidential_notes, cs.follow_up_date,
                            c.case_code, c.counselee_type, c.case_type, c.priority, c.status,
                            c.assigned_to, c.next_follow_up_at, c.referral_source,
                            c.student_id, s.admission_no,
                            CONCAT_WS(' ', sp.first_name, sp.last_name) AS student_name,
                            c.staff_id, st.staff_no,
                            CONCAT_WS(' ', stp.first_name, stp.last_name) AS staff_name,
                            cls.name AS class_name,
                            COALESCE(
                                CONCAT_WS(' ', stp.first_name, stp.last_name),
                                CONCAT_WS(' ', sp.first_name, sp.last_name)
                            ) AS counselee_name,
                            CONCAT_WS(' ', up.first_name, up.last_name) AS counselor_name
                        {$joins}
                        {$where}
                        ORDER BY cs.session_date DESC, cs.id DESC
                        LIMIT ? OFFSET ?";

            $stmt = $this->db->prepare($select);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed counseling sessions');

            return $this->response([
                'status' => 'success',
                'data' => $sessions,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => (int) ceil($total / $limit)
                ]
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Get a single counseling session by ID.
     * @param int $id
     * @return array
     */
    public function get($id)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT
                    cs.*,
                    c.case_code, c.counselee_type, c.case_type, c.priority,
                    c.status AS case_status, c.referral_source, c.title,
                    c.student_id, c.staff_id, c.next_follow_up_at,
                    s.admission_no,
                    CONCAT_WS(' ', sp.first_name, sp.last_name) AS student_name,
                    st.staff_no,
                    CONCAT_WS(' ', stp.first_name, stp.last_name) AS staff_name,
                    COALESCE(
                        CONCAT_WS(' ', stp.first_name, stp.last_name),
                        CONCAT_WS(' ', sp.first_name, sp.last_name)
                    ) AS counselee_name,
                    cls.name AS class_name,
                    CONCAT_WS(' ', up.first_name, up.last_name) AS counselor_name
                 FROM counseling_sessions cs
                 INNER JOIN counseling_cases c ON c.id = cs.case_id
                 LEFT JOIN students s ON s.id = c.student_id
                 LEFT JOIN persons sp ON sp.id = s.person_id
                 LEFT JOIN staff st ON st.id = c.staff_id
                 LEFT JOIN persons stp ON stp.id = st.person_id
                 LEFT JOIN student_academic_enrollments sae
                     ON sae.student_id = c.student_id AND sae.enrollment_status = 'active'
                 LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                 LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 LEFT JOIN classes cls ON cls.id = ayc.class_id
                 LEFT JOIN users u ON u.id = c.assigned_to
                 LEFT JOIN persons up ON up.id = u.person_id
                 WHERE cs.id = ?"
            );
            $stmt->execute([$id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Counseling session not found'
                ], 404);
            }

            $this->logAction('read', $id, 'Fetched counseling session');

            return $this->response([
                'status' => 'success',
                'data' => $session
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Create a counseling session.
     * If case_id is provided the session is recorded on an existing case,
     * otherwise a new case (student or staff) plus its first session is created.
     * @param array $data
     * @return array
     */
    public function create($data)
    {
        try {
            $counseleeType = $data['counselee_type'] ?? ($data['counseleeType'] ?? 'student');
            if (!in_array($counseleeType, ['student', 'staff'])) {
                $counseleeType = 'student';
            }

            $studentId = $data['student_id'] ?? null;
            $staffId = $data['staff_id'] ?? null;

            if ($counseleeType === 'staff' && empty($staffId)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'staff_id is required for staff counseling sessions'
                ], 400);
            }
            if ($counseleeType === 'student' && empty($studentId)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'student_id is required for student counseling sessions'
                ], 400);
            }

            $sessionDate = $data['session_date'] ?? ($data['session_datetime'] ?? date('Y-m-d H:i:s'));
            $sessionType = $data['session_type'] ?? ($data['category'] ?? 'individual');
            $summary = $data['summary'] ?? ($data['session_notes'] ?? ($data['issue_summary'] ?? ''));
            $actionPlan = $data['action_plan'] ?? null;
            $followUpDate = $data['follow_up_date'] ?? null;
            $confidentialNotes = $data['confidential_notes'] ?? null;
            $userId = $this->user_id;

            $this->db->beginTransaction();
            try {
                if (!empty($data['case_id'])) {
                    $caseId = (int) $data['case_id'];
                    $caseCheck = $this->db->prepare("SELECT id FROM counseling_cases WHERE id = ?");
                    $caseCheck->execute([$caseId]);
                    if (!$caseCheck->fetch()) {
                        $this->db->rollBack();
                        return $this->response([
                            'status' => 'error',
                            'message' => 'Counseling case not found'
                        ], 404);
                    }
                } else {
                    $title = $data['title'] ?? 'Counseling session';
                    $caseType = $data['case_type'] ?? ($data['category'] ?? 'other');
                    $priority = $data['priority'] ?? 'medium';
                    $status = $data['status'] ?? 'open';
                    $description = $data['description'] ?? $summary;
                    $referralSource = $data['referral_source'] ?? null;
                    $assignedTo = $data['assigned_to'] ?? null;

                    $caseStmt = $this->db->prepare(
                        "INSERT INTO counseling_cases
                            (counselee_type, student_id, staff_id, case_code, title, case_type,
                             referral_source, priority, status, description, assigned_to, opened_by, opened_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
                    );
                    $caseStmt->execute([
                        $counseleeType,
                        $counseleeType === 'student' ? $studentId : null,
                        $counseleeType === 'staff' ? $staffId : null,
                        $this->generateCaseCode(),
                        $title,
                        $caseType,
                        $referralSource,
                        $priority,
                        $status,
                        $description,
                        $assignedTo,
                        $userId
                    ]);
                    $caseId = $this->db->lastInsertId();
                }

                $sessionStmt = $this->db->prepare(
                    "INSERT INTO counseling_sessions
                        (case_id, session_date, session_type, summary,
                         confidential_notes, action_plan, follow_up_date, recorded_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $sessionStmt->execute([
                    $caseId, $sessionDate, $sessionType, $summary,
                    $confidentialNotes, $actionPlan, $followUpDate, $userId
                ]);
                $sessionId = $this->db->lastInsertId();

                if ($followUpDate) {
                    $this->db->prepare("UPDATE counseling_cases SET next_follow_up_at = ? WHERE id = ?")
                        ->execute([$followUpDate, $caseId]);
                }

                $this->db->commit();
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

            $this->logAction('create', $sessionId, 'Created counseling session');

            return $this->response([
                'status' => 'success',
                'message' => 'Counseling session created successfully',
                'data' => ['id' => $sessionId, 'case_id' => $caseId]
            ], 201);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Update an existing counseling session.
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update($id, $data)
    {
        try {
            $checkStmt = $this->db->prepare("SELECT id, case_id FROM counseling_sessions WHERE id = ?");
            $checkStmt->execute([$id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Counseling session not found'
                ], 404);
            }

            $fields = [];
            $bindings = [];
            $allowed = [
                'session_date', 'session_type', 'summary', 'confidential_notes',
                'action_plan', 'follow_up_date'
            ];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $bindings[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'No fields to update'
                ], 400);
            }

            $fields[] = 'updated_at = NOW()';
            $bindings[] = $id;

            $stmt = $this->db->prepare("UPDATE counseling_sessions SET " . implode(', ', $fields) . " WHERE id = ?");
            $stmt->execute($bindings);

            if (array_key_exists('status', $data)) {
                $this->db->prepare("UPDATE counseling_cases SET status = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$data['status'], $existing['case_id']]);
            }
            if (!empty($data['follow_up_date'])) {
                $this->db->prepare("UPDATE counseling_cases SET next_follow_up_at = ? WHERE id = ?")
                    ->execute([$data['follow_up_date'], $existing['case_id']]);
            }

            $this->logAction('update', $id, 'Updated counseling session');

            return $this->response([
                'status' => 'success',
                'message' => 'Counseling session updated successfully'
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Delete a counseling session.
     * @param int $id
     * @return array
     */
    public function delete($id)
    {
        try {
            $checkStmt = $this->db->prepare("SELECT id FROM counseling_sessions WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Counseling session not found'
                ], 404);
            }

            $stmt = $this->db->prepare("DELETE FROM counseling_sessions WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, 'Deleted counseling session');

            return $this->response([
                'status' => 'success',
                'message' => 'Counseling session deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            \App\API\Services\Logger::legacyError('[CounselingAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->response(['status' => 'error', 'message' => 'An internal error occurred.'], 500);
        }
    }

    /**
     * Generate a unique counseling case code in the CC-<year>-<seq> format.
     */
    private function generateCaseCode(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $code = 'CC-' . date('Y') . '-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("SELECT id FROM counseling_cases WHERE case_code = ?");
            $stmt->execute([$code]);
            if (!$stmt->fetch()) {
                return $code;
            }
        }
        return 'CC-' . date('Y') . '-' . substr(uniqid('', true), -6);
    }
}
