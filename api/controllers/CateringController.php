<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\reports\MealReportManager;
use App\API\Services\DraftRepository;
use App\API\Services\WorkflowAuthorization;
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
            $queued = $this->contract(DraftRepository::class)->queue(
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
        return $this->success(['drafts' => $this->contract(DraftRepository::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'catering'), 'scope' => $review ? 'review' : 'own'], 'Catering AI reviews retrieved');
    }

    public function postAiConsumptionReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiCatering()) return $this->forbidden('Catering review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(DraftRepository::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'catering.consumption_review');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Catering review approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiCateringContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique((array) ($this->user['effective_permissions'] ?? $this->user['permissions'] ?? []))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }
    private function canUseAiCatering(): bool
    {
        try { $this->contract(WorkflowAuthorization::class)->authorize('catering.consumption_review', $this->aiCateringContext()); return true; } catch (DomainException $e) { return false; }
    }
    private function canApproveAiCatering(): bool
    {
        return $this->userHasAny(['inventory_manage', 'inventory_approve', 'catering_manage'], [], ['director', 'school administrator', 'inventory manager', 'store manager', 'catering manager', 'cook', 'admin']);
    }

    // ================================================================
    // KITCHEN MENU: dishes, the meal plan, and what was cooked and served
    // ================================================================

    private ?\App\API\Modules\catering\KitchenService $kitchen = null;

    /** Run a kitchen action; $write limits it to the Cateress and leadership. */
    private function kitchen(callable $fn, bool $write = false)
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if ($write && !\App\API\Modules\catering\KitchenService::canWrite((array) $this->getUserRoleIds())) {
            return $this->forbidden('The menu is planned by the kitchen (the Cateress) and the school office.');
        }
        $this->kitchen ??= new \App\API\Modules\catering\KitchenService($this->db->getConnection());
        try {
            return $this->success($fn($this->kitchen));
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->respond(null, $e->getMessage(), in_array($code, [400, 404, 409], true) ? $code : 400, false);
        } catch (Throwable $e) {
            \App\API\Includes\FileLogger::write('catering', ['type' => 'kitchen_menu_error', 'reason' => mb_substr($e->getMessage(), 0, 200)], 'error');
            return $this->serverError('The menu could not be updated.');
        }
    }

    private function q(array $data): array
    {
        return array_merge($_GET, is_array($data) ? $data : []);
    }

    /** GET /api/catering/dishes?active=1 */
    public function getDishes($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(fn ($k) => [
            'dishes' => $k->dishes(!empty($this->q((array) $data)['active'])),
            'can_plan' => \App\API\Modules\catering\KitchenService::canWrite((array) $this->getUserRoleIds()),
        ]);
    }

    /** POST /api/catering/dishes — add or edit a dish. */
    public function postDishes($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(fn ($k) => $k->saveDish((array) $data), true);
    }

    /** GET /api/catering/plans?from=Y-m-d&to=Y-m-d */
    public function getPlans($id = null, $data = [], $segments = [])
    {
        $q = $this->q((array) $data);
        return $this->kitchen(fn ($k) => $k->plans((string) ($q['from'] ?? date('Y-m-d')), (string) ($q['to'] ?? $q['from'] ?? date('Y-m-d')))
            + ['can_plan' => \App\API\Modules\catering\KitchenService::canWrite((array) $this->getUserRoleIds())]);
    }

    /** POST /api/catering/plans — add a dish to a meal, or edit it (id). */
    public function postPlans($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(fn ($k) => $k->savePlan((array) $data, (int) $this->getUserId()), true);
    }

    /** POST /api/catering/plans-remove/{id} */
    public function postPlansRemove($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(function ($k) use ($id) { $k->removePlan((int) $id); return ['removed' => (int) $id]; }, true);
    }

    /** POST /api/catering/plans-record/{id} — prepared / served / cancelled, with counts. */
    public function postPlansRecord($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(fn ($k) => $k->record((int) $id, (array) $data, (int) $this->getUserId()), true);
    }

    /** POST /api/catering/plans-copy-week {from, to} (Mondays) */
    public function postPlansCopyWeek($id = null, $data = [], $segments = [])
    {
        return $this->kitchen(fn ($k) => $k->copyWeek((string) ($data['from'] ?? ''), (string) ($data['to'] ?? ''), (int) $this->getUserId()), true);
    }

    // ------------------------------------------------------------------
    // Food store (FoodStoreService): items, deliveries, issues to the
    // kitchen, spoilage, stock counts and the bin card ledger.
    // ------------------------------------------------------------------

    private ?\App\API\Modules\inventory\FoodStoreService $foodStore = null;

    private function store(callable $fn, bool $write = false)
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $roles = (array) $this->getUserRoleIds();
        if (!\App\API\Modules\inventory\FoodStoreService::canView($roles)) {
            return $this->forbidden('The food store is kept by the food store manager.');
        }
        if ($write && !\App\API\Modules\inventory\FoodStoreService::canManage($roles)) {
            return $this->forbidden('Only the food store manager and the school office change the food store.');
        }
        $this->foodStore ??= new \App\API\Modules\inventory\FoodStoreService($this->db->getConnection());
        try {
            return $this->success($fn($this->foodStore, (int) $this->getUserId()));
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            return $this->respond(null, $e->getMessage(), in_array($code, [400, 404, 409], true) ? $code : 400, false);
        } catch (Throwable $e) {
            \App\API\Includes\FileLogger::write('catering', ['type' => 'food_store_error', 'reason' => mb_substr($e->getMessage(), 0, 200)], 'error');
            return $this->serverError('The food store could not be updated.');
        }
    }

    /** GET /api/catering/store — the food items, their levels and the lists the forms need. */
    public function getStore($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s) => $s->store(!empty($this->q((array) $data)['all']))
            + ['can_manage' => \App\API\Modules\inventory\FoodStoreService::canManage((array) $this->getUserRoleIds())]);
    }

    /** GET /api/catering/store-ledger?from&to&movement&item_id — deliveries, issues, spoilage and counts. */
    public function getStoreLedger($id = null, $data = [], $segments = [])
    {
        $q = $this->q((array) $data);
        return $this->store(fn ($s) => $s->ledger((string) ($q['from'] ?? ''), (string) ($q['to'] ?? ''),
            !empty($q['item_id']) ? (int) $q['item_id'] : ($id ? (int) $id : null), isset($q['movement']) ? (string) $q['movement'] : null));
    }

    /** POST /api/catering/store-item — add or edit a food item. */
    public function postStoreItem($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s, $u) => $s->saveItem((array) $data, $u), true);
    }

    /** POST /api/catering/store-receive — a delivery. */
    public function postStoreReceive($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s, $u) => $s->receive((array) $data, $u), true);
    }

    /** POST /api/catering/store-issue — food handed to the kitchen. */
    public function postStoreIssue($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s, $u) => $s->issue((array) $data, $u), true);
    }

    /** POST /api/catering/store-spoil — food thrown away. */
    public function postStoreSpoil($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s, $u) => $s->spoil((array) $data, $u), true);
    }

    /** POST /api/catering/store-count — a stock count. */
    public function postStoreCount($id = null, $data = [], $segments = [])
    {
        return $this->store(fn ($s, $u) => $s->count((array) $data, $u), true);
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
