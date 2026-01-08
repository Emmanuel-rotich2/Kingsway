<?php
namespace App\API\Modules\counseling;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * CounselingAPI
 * Handles all counseling session-related business logic
 * 
 * Migrated from standalone files:
 * - api/save_session.php → saveSession(), updateSession()
 * - api/get_summary.php → getSummary()
 * - api/get_sessions.php → list()
 */
class CounselingAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('counseling');
    }

    /**
     * Get summary statistics for counseling sessions
     * @return array
     */
    public function getSummary()
    {
        try {
            $total = $this->db->query("SELECT COUNT(*) FROM counseling_sessions")->fetchColumn();
            $scheduled = $this->db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='scheduled'")->fetchColumn();
            $completed = $this->db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='completed'")->fetchColumn();
            $inProgress = $this->db->query("SELECT COUNT(*) FROM counseling_sessions WHERE status='in_progress'")->fetchColumn();

            $this->logAction('read', null, 'Fetched counseling summary');

            return $this->response([
                'status' => 'success',
                'data' => [
                    'total' => (int) $total,
                    'scheduled' => (int) $scheduled,
                    'completed' => (int) $completed,
                    'active' => (int) $inProgress + (int) $scheduled
                ]
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to fetch summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List counseling sessions with pagination and filtering
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
            $page = max(1, intval($params['page'] ?? 1));
            $limit = max(1, min(100, intval($params['limit'] ?? 10)));
            $offset = ($page - 1) * $limit;

            // Base query
            $query = "SELECT cs.*, s.first_name, s.last_name, c.name as class_name
                      FROM counseling_sessions cs
                      JOIN students s ON cs.student_id = s.id
                      LEFT JOIN class_streams stm ON s.stream_id = stm.id
                      LEFT JOIN classes c ON stm.class_id = c.id
                      WHERE 1=1";
            $bindings = [];

            if (!empty($search)) {
                $query .= " AND CONCAT(s.first_name, ' ', s.last_name) LIKE ?";
                $bindings[] = "%$search%";
            }
            if (!empty($status)) {
                $query .= " AND cs.status = ?";
                $bindings[] = $status;
            }
            if (!empty($category)) {
                $query .= " AND cs.category = ?";
                $bindings[] = $category;
            }
            if (!empty($date)) {
                $query .= " AND DATE(cs.session_datetime) = ?";
                $bindings[] = $date;
            }

            // Count total
            $countQuery = str_replace(
                'SELECT cs.*, s.first_name, s.last_name, c.name as class_name',
                'SELECT COUNT(*) as total',
                $query
            );
            $stmtCount = $this->db->prepare($countQuery);
            $stmtCount->execute($bindings);
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Fetch with pagination
            $query .= " ORDER BY cs.session_datetime DESC LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->logAction('read', null, 'Listed counseling sessions');

            return $this->response([
                'status' => 'success',
                'data' => [
                    'sessions' => $sessions,
                    'pagination' => [
                        'total' => (int) $total,
                        'page' => $page,
                        'limit' => $limit,
                        'pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to list sessions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a single counseling session by ID
     * @param int $id
     * @return array
     */
    public function get($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT cs.*, s.first_name, s.last_name, c.name as class_name
                FROM counseling_sessions cs
                JOIN students s ON cs.student_id = s.id
                LEFT JOIN class_streams stm ON s.stream_id = stm.id
                LEFT JOIN classes c ON stm.class_id = c.id
                WHERE cs.id = ?
            ");
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
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to fetch session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new counseling session
     * @param array $data
     * @return array
     */
    public function create($data)
    {
        try {
            // Validate required fields
            $required = ['student_id', 'session_datetime', 'category', 'priority', 'issue_summary'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return $this->response([
                        'status' => 'error',
                        'message' => "Field '$field' is required"
                    ], 400);
                }
            }

            $stmt = $this->db->prepare("
                INSERT INTO counseling_sessions 
                (student_id, session_datetime, category, priority, issue_summary, session_notes, 
                 action_plan, status, follow_up, follow_up_date, notify_parent, confidential, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['student_id'],
                $data['session_datetime'],
                $data['category'],
                $data['priority'],
                $data['issue_summary'],
                $data['session_notes'] ?? null,
                $data['action_plan'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['follow_up'] ?? null,
                !empty($data['follow_up_date']) ? $data['follow_up_date'] : null,
                isset($data['notify_parent']) ? 1 : 0,
                isset($data['confidential']) ? 1 : 0,
                $this->user_id
            ]);

            $newId = $this->db->lastInsertId();

            $this->logAction('create', $newId, 'Created counseling session');

            return $this->response([
                'status' => 'success',
                'message' => 'Counseling session created successfully',
                'data' => ['id' => $newId]
            ], 201);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to create session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing counseling session
     * @param int $id
     * @param array $data
     * @return array
     */
    public function update($id, $data)
    {
        try {
            // Check if session exists
            $checkStmt = $this->db->prepare("SELECT id FROM counseling_sessions WHERE id = ?");
            $checkStmt->execute([$id]);
            if (!$checkStmt->fetch()) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Counseling session not found'
                ], 404);
            }

            $stmt = $this->db->prepare("
                UPDATE counseling_sessions SET
                    student_id = ?,
                    session_datetime = ?,
                    category = ?,
                    priority = ?,
                    issue_summary = ?,
                    session_notes = ?,
                    action_plan = ?,
                    status = ?,
                    follow_up = ?,
                    follow_up_date = ?,
                    notify_parent = ?,
                    confidential = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['student_id'],
                $data['session_datetime'],
                $data['category'],
                $data['priority'],
                $data['issue_summary'],
                $data['session_notes'] ?? null,
                $data['action_plan'] ?? null,
                $data['status'] ?? 'scheduled',
                $data['follow_up'] ?? null,
                !empty($data['follow_up_date']) ? $data['follow_up_date'] : null,
                isset($data['notify_parent']) ? 1 : 0,
                isset($data['confidential']) ? 1 : 0,
                $id
            ]);

            $this->logAction('update', $id, 'Updated counseling session');

            return $this->response([
                'status' => 'success',
                'message' => 'Counseling session updated successfully'
            ]);
        } catch (Exception $e) {
            $this->handleException($e);
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to update session: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a counseling session
     * @param int $id
     * @return array
     */
    public function delete($id)
    {
        try {
            // Check if session exists
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
            return $this->response([
                'status' => 'error',
                'message' => 'Failed to delete session: ' . $e->getMessage()
            ], 500);
        }
    }
}
