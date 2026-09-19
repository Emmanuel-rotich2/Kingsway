<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\counseling\CounselingAPI;
use Exception;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

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
        $this->api = $this->contract('App\API\Modules\counseling\CounselingAPI');
    }

    private function guardCounseling(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
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
        return $this->handleResponse($this->api->getSummary($_GET ?? []));
    }

    public function postAiWelfareReviewQueue($id = null, $data = [], $segments = [])
    {
        $context = $this->aiWelfareContext();
        try { $this->contract(AiWorkflowService::class)->authorize('counseling.welfare_review', $context); } catch (DomainException $e) { return $this->forbidden('Counselling AI assistance is not available for your account'); }
        try {
            $raw = $this->api->getSummary($data); $summary = is_array($raw['data'] ?? null) ? $raw['data'] : (is_array($raw) ? $raw : []);
            $input = ['report_date' => date('Y-m-d'), 'total_cases' => (string) ($summary['total_cases'] ?? $summary['total'] ?? 0), 'open_cases' => (string) ($summary['open_cases'] ?? $summary['open'] ?? 0), 'urgent_cases' => (string) ($summary['urgent_cases'] ?? 0), 'follow_ups_due' => (string) ($summary['follow_ups_due'] ?? 0), 'sessions_count' => (string) ($summary['total_sessions'] ?? $summary['sessions'] ?? 0), 'student_case_count' => (string) ($summary['student_cases'] ?? 0), 'staff_case_count' => (string) ($summary['staff_cases'] ?? 0), 'follow_up_intent' => 'Prepare aggregate counselling follow-up; never expose identities, diagnoses, confidential notes, or make safeguarding decisions.'];
            return $this->accepted($this->contract(AiDraftService::class)->queue('counseling.welfare_review', $context, $input, ['subject_type' => 'counseling_welfare_review', 'scope' => 'authorized_counseling_aggregates']), 'Counselling welfare review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); } catch (Exception $e) { return $this->serverError('Counselling assistance is temporarily unavailable'); }
    }

    public function getAiWelfareReviews($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiWelfare()) return $this->forbidden('Counselling review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'counseling'), 'scope' => $review ? 'review' : 'own'], 'Counselling AI reviews retrieved');
    }

    public function postAiWelfareReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiWelfare()) return $this->forbidden('Counselling review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0); if ($draftId < 1) return $this->badRequest('draft_id is required');
        try { $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'counseling.welfare_review'); return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Counselling review approved for guidance'); } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false); }
    }

    private function aiWelfareContext(): array { return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? '']; }
    private function canApproveAiWelfare(): bool { return $this->userHasAny(['health_manage', 'health_view', 'counseling_manage'], [3, 4, 5, 10], ['school administrator', 'headteacher', 'counselor', 'chaplain', 'admin']); }

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
        if ($guard = $this->guardCounseling()) return $guard;
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
        if ($guard = $this->guardCounseling()) return $guard;
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
        if ($guard = $this->guardCounseling()) return $guard;
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
            'staffId' => 'staff_id',
            'counseleeType' => 'counselee_type',
            'caseId' => 'case_id',
            'caseType' => 'case_type',
            'sessionDate' => 'session_date',
            'sessionDateTime' => 'session_datetime',
            'sessionType' => 'session_type',
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
        return $this->handleResponse($this->api->getStats());
    }

    /**
     * GET /api/counseling/sessions?limit=N&sort=recent
     * Returns a list of counseling sessions
     */
    public function getSessions($id = null, $data = [], $segments = [])
    {
        $limit = min((int)($_GET['limit'] ?? 20), 100);
        $sort  = ($_GET['sort'] ?? 'recent') === 'recent' ? 'DESC' : 'ASC';
        return $this->handleResponse($this->api->getRecentSessions($limit, $sort));
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
