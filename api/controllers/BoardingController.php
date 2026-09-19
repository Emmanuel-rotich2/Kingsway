<?php
namespace App\API\Controllers;

use App\API\Modules\boarding\BoardingManager;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;
use Exception;

/**
 * BoardingController
 * Handles boarding/hostel management endpoints.
 *
 * GET    /api/boarding                    → getStats()
 * GET    /api/boarding/stats              → getStats()
 * GET    /api/boarding/occupancy          → getOccupancy()
 * GET    /api/boarding/dormitories        → getDormitories()
 * POST   /api/boarding/dormitories        → postDormitories()
 * PUT    /api/boarding/dormitories/{id}   → putDormitories()
 * DELETE /api/boarding/dormitories/{id}   → deleteDormitories()
 * GET    /api/boarding/students           → getStudents()
 * GET    /api/boarding/roll-call          → getRollCall()
 * POST   /api/boarding/roll-call          → postRollCall()
 * GET    /api/boarding/exeats             → getExeats()
 * POST   /api/boarding/exeats             → postExeats()
 * PUT    /api/boarding/exeats/{id}        → putExeats()  (approve/reject)
 * GET    /api/boarding/activity           → getActivity()
 */
class BoardingController extends BaseController
{
    private BoardingManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = $this->contract('App\API\Modules\boarding\BoardingManager');
    }

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats();
    }
    public function getStats($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getStats());
    }
    public function getOccupancy($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getOccupancy());
    }

    /** POST /api/boarding/ai-exception-summary-queue */
    public function postAiExceptionSummaryQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiBoarding()) return $this->forbidden('Boarding AI assistance is not available for your account');
        $date = (string) ($data['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $this->badRequest('A valid boarding date is required');
        try {
            $statsResponse = $this->manager->getStats();
            $stats = is_array($statsResponse['data'] ?? null) ? $statsResponse['data'] : $statsResponse;
            $occupancyResponse = $this->manager->getOccupancy();
            $occupancy = is_array($occupancyResponse['data'] ?? null) ? $occupancyResponse['data'] : $occupancyResponse;
            $input = [
                'report_date' => $date,
                'dormitory_count' => (string) ($stats['dormitories'] ?? count(is_array($occupancy) ? $occupancy : [])),
                'capacity' => (string) ($stats['total_capacity'] ?? 0),
                'assigned_beds' => (string) ($stats['assigned_beds'] ?? 0),
                'available_beds' => (string) ($stats['available_beds'] ?? 0),
                'occupancy_rate' => (string) ($stats['occupancy_rate'] ?? 0),
                'present_tonight' => (string) ($stats['present_tonight'] ?? 0),
                'absent_or_unknown' => (string) ($stats['absent_or_unknown'] ?? 0),
                'on_leave' => (string) ($stats['on_leave'] ?? 0),
                'pending_leaves' => (string) ($stats['pending_leaves'] ?? 0),
                'urgent_notes' => (string) ($stats['urgent_notes'] ?? 0),
                'roll_call_rate' => (string) ($stats['roll_call_pct'] ?? 0),
                'follow_up_intent' => 'Prepare boarding operations follow-up; do not alter roll-call, occupancy, leave, or welfare records.',
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'boarding.exception_summary', $this->aiBoardingContext(), $input,
                ['subject_type' => 'boarding_exception_summary', 'scope' => 'authorized_boarding_operations']
            );
            return $this->accepted($queued, 'Boarding exception summary queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (\Throwable $e) {
            return $this->serverError('Boarding assistance is temporarily unavailable');
        }
    }

    public function getAiExceptionSummaries($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiBoarding()) return $this->forbidden('Boarding review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'boarding'), 'scope' => $review ? 'review' : 'own'], 'Boarding AI summaries retrieved');
    }

    public function postAiExceptionSummaryApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiBoarding()) return $this->forbidden('Boarding review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'boarding.exception_summary');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Boarding summary approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiBoardingContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }
    private function canUseAiBoarding(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('boarding.exception_summary', $this->aiBoardingContext()); return true; } catch (DomainException $e) { return false; }
    }
    private function canApproveAiBoarding(): bool
    {
        return $this->userHasAny(['attendance_boarding_approve', 'attendance_boarding_edit'], [3, 4, 5, 10], ['headteacher', 'school administrator', 'director', 'boarding master']);
    }

    public function getDormitories($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->listDormitories());
    }

    public function postDormitories($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->createDormitory($data));
    }

    public function putDormitories($id = null, $data = [], $segments = [])
    {
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->updateDormitory($dormId, $data));
    }

    public function deleteDormitories($id = null, $data = [], $segments = [])
    {
        $dormId = (int) ($id ?? $data['id'] ?? 0);
        return $this->handleApiResponse($this->manager->deleteDormitory($dormId));
    }

    public function getStudents($id = null, $data = [], $segments = [])
    {
        $dormId = $_GET['dormitory_id'] ?? $data['dormitory_id'] ?? null;
        $search = $_GET['search'] ?? $data['search'] ?? '';
        return $this->handleApiResponse($this->manager->getStudents($dormId, $search));
    }
    public function getRollCall($id = null, $data = [], $segments = [])
    {
        $date = $_GET['date'] ?? $data['date'] ?? date('Y-m-d');
        $dateFrom = $_GET['date_from'] ?? $data['date_from'] ?? $date;
        $dateTo = $_GET['date_to'] ?? $data['date_to'] ?? $date;
        return $this->handleApiResponse($this->manager->getRollCall($date, $dateFrom, $dateTo));
    }

    public function postRollCall($id = null, $data = [], $segments = [])
    {
        $markedBy = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $this->handleApiResponse($this->manager->markRollCall($data, $markedBy));
    }

    public function getExeats($id = null, $data = [], $segments = [])
    {
        $status = $_GET['status'] ?? $data['status'] ?? '';
        return $this->handleApiResponse($this->manager->getExeats(
            $status,
            $_GET['date_from'] ?? $data['date_from'] ?? null,
            $_GET['date_to'] ?? $data['date_to'] ?? null
        ));
    }

    public function postExeats($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->createExeat($data));
    }

    public function putExeats($id = null, $data = [], $segments = [])
    {
        $exeatId = (int) ($id ?? $data['id'] ?? 0);
        $action = $data['action'] ?? $segments[0] ?? 'approve';
        return $this->handleApiResponse($this->manager->updateExeat($exeatId, $action));
    }

    public function getActivity($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getActivity());
    }
}
