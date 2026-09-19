<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\health\HealthAPI;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

/**
 * HealthController
 * Student health records, sick bay visits, and vaccinations.
 *
 * ROUTES:
 * GET  /api/health/summary                  → getSummary()
 * GET  /api/health/records                  → getRecords()
 * GET  /api/health/records/{id}             → getRecords($id)  — by student_id
 * POST /api/health/records                  → postRecords()
 * PUT  /api/health/records/{id}             → putRecords($id)
 * GET  /api/health/sick-bay                 → getSickBay()
 * POST /api/health/sick-bay                 → postSickBay()
 * PUT  /api/health/sick-bay/{id}            → putSickBay($id)  — update / dismiss
 * GET  /api/health/vaccinations             → getVaccinations()
 * GET  /api/health/vaccinations/{id}        → getVaccinations($id)  — by student_id
 * POST /api/health/vaccinations             → postVaccinations()
 */
class HealthController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\health\HealthAPI');
    }

    private function userId()
    {
        return $this->user['user_id'] ?? $this->user['id'] ?? null;
    }

    // ----------------------------------------------------------------
    // SUMMARY
    // ----------------------------------------------------------------

    public function getSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSummary();
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    /** POST /api/health/ai-welfare-review-queue — aggregate-only, reviewable guidance. */
    public function postAiWelfareReviewQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiWelfare()) return $this->forbidden('Health AI assistance is not available for your account');
        try {
            $result = $this->api->getSummary();
            $summary = is_array($result['data'] ?? null) ? $result['data'] : (is_array($result) ? $result : []);
            $input = ['report_date' => date('Y-m-d'), 'record_count' => (string) ($summary['total_records'] ?? $summary['total'] ?? 0), 'active_visit_count' => (string) ($summary['active_visits'] ?? $summary['active'] ?? 0), 'referral_count' => (string) ($summary['referrals'] ?? 0), 'vaccination_due_count' => (string) ($summary['vaccinations_due'] ?? $summary['due_vaccinations'] ?? 0), 'follow_up_intent' => 'Prepare aggregate welfare administration follow-up; do not diagnose, identify, or alter health records.'];
            return $this->accepted($this->contract(AiDraftService::class)->queue('health.welfare_review', $this->aiWelfareContext(), $input, ['subject_type' => 'health_welfare_review', 'scope' => 'authorized_health_aggregates']), 'Health and welfare review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (\Throwable $e) { return $this->serverError('Health assistance is temporarily unavailable'); }
    }

    public function getAiWelfareReviews($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiWelfare()) return $this->forbidden('Health review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'health'), 'scope' => $review ? 'review' : 'own'], 'Health AI reviews retrieved');
    }

    public function postAiWelfareReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiWelfare()) return $this->forbidden('Health review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try { $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'health.welfare_review'); return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Health review approved for staff guidance'); }
        catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false); }
    }

    private function aiWelfareContext(): array { return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? '']; }
    private function canUseAiWelfare(): bool { try { $this->contract(AiWorkflowService::class)->authorize('health.welfare_review', $this->aiWelfareContext()); return true; } catch (DomainException $e) { return false; } }
    private function canApproveAiWelfare(): bool { return $this->userHasAny(['health_manage', 'health_view'], [3, 4, 5, 10], ['school administrator', 'headteacher', 'school nurse', 'admin']); }

    // ----------------------------------------------------------------
    // HEALTH RECORDS
    // ----------------------------------------------------------------

    public function getRecords($id = null, $data = [], $segments = [])
    {
        $studentId = $id ? (int)$id : null;
        $result = $this->api->listRecords(
            $studentId,
            $_GET['search'] ?? '',
            $_GET['class_id'] ?? null
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postRecords($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }

        $result = $this->api->upsertRecord($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? ['message' => 'Health record saved']);
    }

    public function putRecords($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Student ID required');
        $data['student_id'] = $id;
        return $this->postRecords(null, $data, $segments);
    }

    // ----------------------------------------------------------------
    // SICK BAY
    // ----------------------------------------------------------------

    public function getSickBay($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listVisits(
            $_GET['status'] ?? '',
            $_GET['date'] ?? ''
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postSickBay($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || trim($data['complaint'] ?? '') === '') {
            return $this->badRequest('student_id and complaint are required');
        }

        $result = $this->api->createVisit($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->created(['id' => $result['data']['id'] ?? null], 'Visit recorded');
    }

    public function putSickBay($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Visit ID required');
        $dismiss = ($segments[0] ?? '') === 'dismiss';

        $result = $this->api->updateVisit($id, $data, $dismiss);
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? ['message' => 'Visit updated']);
    }

    // ----------------------------------------------------------------
    // VACCINATIONS
    // ----------------------------------------------------------------

    public function getVaccinations($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listVaccinations(
            $id ? (int)$id : null,
            !empty($_GET['due_only'])
        );
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->success($result['data'] ?? []);
    }

    public function postVaccinations($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || trim($data['vaccine_name'] ?? '') === '') {
            return $this->badRequest('student_id and vaccine_name are required');
        }

        $result = $this->api->createVaccination($data, $this->userId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->serverError('An internal error occurred.');
        }
        return $this->created(['id' => $result['data']['id'] ?? null], 'Vaccination recorded');
    }
}
