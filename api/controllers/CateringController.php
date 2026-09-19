<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\reports\MealReportManager;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * CateringController
 *
 * Exposes catering endpoints only. Business queries remain in the canonical
 * MealReportManager under api/modules/reports.
 */
class CateringController extends BaseController
{
    /** @var MealReportManager */
    private $reports;

    public function __construct()
    {
        parent::__construct();
        $this->reports = $this->contract('App\API\Modules\reports\MealReportManager');
    }

    private function guardCatering(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    public function index($id = null, $data = [], $segments = [])
    {
        return $this->success(['message' => 'Catering API is running']);
    }

    public function getStats($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getStats(
                $_GET['date'] ?? null,
                $_GET['date_from'] ?? null,
                $_GET['date_to'] ?? null
            );
        });
    }

    public function getMenu($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getMenu(
                $_GET['date'] ?? null,
                $_GET['date_from'] ?? null,
                $_GET['date_to'] ?? null
            );
        });
    }

    public function getFoodStock($id = null, $data = [], $segments = [])
    {
        return $this->delegate(function () {
            return $this->reports->getFoodStock(
                !empty($_GET['low_stock']),
                (int) ($_GET['limit'] ?? 50)
            );
        });
    }

    /** POST /api/catering/ai-consumption-review-queue */
    public function postAiConsumptionReviewQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiCatering()) return $this->forbidden('Catering AI assistance is not available for your account');
        try {
            $from = (string) ($data['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
            $to = (string) ($data['date_to'] ?? date('Y-m-d'));
            $stats = $this->reports->getStats($to, $from, $to);
            $stats = is_array($stats['data'] ?? null) ? $stats['data'] : $stats;
            $used = (float) ($stats['quantity_used'] ?? 0);
            $waste = (float) ($stats['waste_quantity'] ?? 0);
            $input = [
                'date_from' => $from, 'date_to' => $to,
                'meals_planned' => (string) ($stats['meals_planned'] ?? 0),
                'planned_servings' => (string) ($stats['planned_servings'] ?? 0),
                'prepared_meals' => (string) ($stats['prepared_meals'] ?? 0),
                'actual_servings' => (string) ($stats['actual_servings'] ?? 0),
                'food_items' => (string) ($stats['food_items'] ?? 0),
                'low_stock' => (string) ($stats['low_stock'] ?? 0),
                'quantity_used' => (string) $used,
                'waste_quantity' => (string) $waste,
                'waste_rate' => (string) ($used > 0 ? round(($waste / $used) * 100, 2) : 0),
                'follow_up_intent' => 'Prepare catering variance review; staff must verify records, food safety, nutrition, and purchasing decisions.',
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'catering.consumption_review', $this->aiCateringContext(), $input,
                ['subject_type' => 'catering_consumption_review', 'scope' => 'authorized_catering_aggregate']
            );
            return $this->accepted($queued, 'Catering consumption review queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Throwable $e) {
            return $this->serverError('Catering assistance is temporarily unavailable');
        }
    }

    public function getAiConsumptionReviews($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiCatering()) return $this->forbidden('Catering review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'catering'), 'scope' => $review ? 'review' : 'own'], 'Catering AI reviews retrieved');
    }

    public function postAiConsumptionReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiCatering()) return $this->forbidden('Catering review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'catering.consumption_review');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Catering review approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiCateringContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }
    private function canUseAiCatering(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('catering.consumption_review', $this->aiCateringContext()); return true; } catch (DomainException $e) { return false; }
    }
    private function canApproveAiCatering(): bool
    {
        return $this->userHasAny(['inventory_manage', 'inventory_approve', 'catering_manage'], [], ['director', 'school administrator', 'inventory manager', 'store manager', 'catering manager', 'cook', 'admin']);
    }

    private function delegate(callable $operation)
    {
        try {
            $result = $operation();
            if (($result['success'] ?? false) !== true) {
                return $this->badRequest(
                    $result['message'] ?? $result['error'] ?? 'Catering operation failed'
                );
            }
            return $this->success($result['data'] ?? null);
        } catch (InvalidArgumentException $error) {
            \App\API\Services\Logger::legacyError('[CateringController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->badRequest('An internal error occurred.');
        } catch (Throwable $error) {
            \App\API\Services\Logger::legacyError('[CateringController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }
}
