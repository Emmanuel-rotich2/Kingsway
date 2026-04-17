<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\counseling\CounselingAPI;
use Exception;

/**
 * CounselingController
 * Handles all counseling session-related API endpoints
 * 
 * ROUTES:
 * GET  /api/counseling/index              → getIndex()
 * GET  /api/counseling/summary            → getSummary()
 * GET  /api/counseling/session            → getSession() - list all
 * GET  /api/counseling/session/{id}       → getSession($id) - get one
 * POST /api/counseling/session            → postSession() - create
 * PUT  /api/counseling/session/{id}       → putSession($id) - update
 * DELETE /api/counseling/session/{id}     → deleteSession($id) - delete
 */
class CounselingController extends BaseController
{
    private CounselingAPI $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new CounselingAPI();
    }

    /**
     * GET /api/counseling/index
     */
    public function getIndex()
    {
        return $this->success(['message' => 'Counseling API is running']);
    }

    /**
     * GET /api/counseling/summary
     * Returns summary statistics for counseling sessions
     */
    public function getSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getSummary());
    }

    /**
     * GET /api/counseling/session
     * GET /api/counseling/session/{id}
     */
    public function getSession($id = null, $data = [], $segments = [])
    {
        if ($id) {
            return $this->handleResponse($this->api->get($id));
        }

        // Get query parameters for filtering
        $params = [
            'search' => $_GET['search'] ?? '',
            'status' => $_GET['status'] ?? '',
            'category' => $_GET['category'] ?? '',
            'date' => $_GET['date'] ?? '',
            'page' => $_GET['page'] ?? 1,
            'limit' => $_GET['limit'] ?? 10
        ];

        return $this->handleResponse($this->api->list($params));
    }

    /**
     * POST /api/counseling/session
     * Create a new counseling session
     */
    public function postSession($id = null, $data = [], $segments = [])
    {
        // Map frontend field names to API field names if needed
        $mappedData = $this->mapRequestData($data);
        return $this->handleResponse($this->api->create($mappedData));
    }

    /**
     * PUT /api/counseling/session/{id}
     * Update an existing counseling session
     */
    public function putSession($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Session ID is required');
        }

        $mappedData = $this->mapRequestData($data);
        return $this->handleResponse($this->api->update($id, $mappedData));
    }

    /**
     * DELETE /api/counseling/session/{id}
     */
    public function deleteSession($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Session ID is required');
        }

        return $this->handleResponse($this->api->delete($id));
    }

    /**
     * Map frontend request data to API field names
     * Handles both camelCase (frontend) and snake_case (API) formats
     */
    private function mapRequestData(array $data): array
    {
        $fieldMap = [
            'student' => 'student_id',
            'studentId' => 'student_id',
            'sessionDateTime' => 'session_datetime',
            'issue' => 'issue_summary',
            'issueSummary' => 'issue_summary',
            'sessionNotes' => 'session_notes',
            'actionPlan' => 'action_plan',
            'followUp' => 'follow_up',
            'followUpDate' => 'follow_up_date',
            'notifyParent' => 'notify_parent',
        ];

        $mapped = [];
        foreach ($data as $key => $value) {
            $mappedKey = $fieldMap[$key] ?? $key;
            $mapped[$mappedKey] = $value;
        }

        return $mapped;
    }

    /**
     * GET /api/counseling/stats
     * Returns counseling statistics for dashboards
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        $db    = \App\Database\Database::getInstance();
        $stats = ['sessions_this_week' => 0, 'students_seen' => 0, 'pending_referrals' => 0];
        try {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM counseling_sessions WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $stats['sessions_this_week'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        try {
            $stmt = $db->query(
                "SELECT COUNT(DISTINCT student_id) FROM counseling_sessions WHERE session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $stats['students_seen'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        try {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM counseling_referrals WHERE status = 'pending'"
            );
            $stats['pending_referrals'] = (int)($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {}
        return $this->success($stats);
    }

    /**
     * GET /api/counseling/sessions?limit=N&sort=recent
     * Returns a list of counseling sessions
     */
    public function getSessions($id = null, $data = [], $segments = [])
    {
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $sort  = ($_GET['sort'] ?? 'recent') === 'recent' ? 'DESC' : 'ASC';
        try {
            $db   = \App\Database\Database::getInstance();
            $stmt = $db->prepare(
                "SELECT cs.*, s.first_name, s.last_name FROM counseling_sessions cs
                 LEFT JOIN students s ON s.id = cs.student_id
                 ORDER BY cs.session_date {$sort} LIMIT :lim"
            );
            $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->success($rows ?: []);
        } catch (\Exception $e) {
            return $this->success([]);
        }
    }

    /**
     * Handle API response and convert to controller response format
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['status'])) {
                $status = $result['status'];
                $code = $result['status_code'] ?? ($status === 'success' ? 200 : 400);
                $message = $result['message'] ?? ($status === 'success' ? 'Success' : 'Error');
                $data = $result['data'] ?? null;

                if ($status === 'success') {
                    return $this->success($data, $message);
                } else {
                    return $this->badRequest($message, $data);
                }
            }
            return $this->success($result);
        }
        return $this->success($result);
    }
}
