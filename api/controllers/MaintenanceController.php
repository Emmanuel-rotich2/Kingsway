<?php
namespace App\API\Controllers;
use Illuminate\Http\Request;

use Exception;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

use App\API\Modules\system\SystemAPI;
use App\API\Modules\maintenance\MaintenanceAPI;

class MaintenanceController extends BaseController
{
    private $api;
    private $systemApi;

    public function __construct() {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\maintenance\MaintenanceAPI');
        $this->systemApi = $this->contract('App\API\Modules\system\SystemAPI');
    }

    private function guardMaintenance(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    public function index()
    {
        return $this->success(['message' => 'Maintenance API is running']);
    }

    /** POST /api/maintenance/ai-facilities-review-queue */
    public function postAiFacilitiesReviewQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiFacilities()) return $this->forbidden('Maintenance AI assistance is not available for your account');
        try {
            $equipment = $this->api->listEquipment([]);
            $vehicles = $this->api->listVehicles([]);
            $equipmentRows = is_array($equipment['data'] ?? null) ? $equipment['data'] : [];
            $vehicleRows = is_array($vehicles['data'] ?? null) ? $vehicles['data'] : [];
            $equipmentStatuses = array_count_values(array_map(static fn(array $row): string => strtolower((string) ($row['status'] ?? 'unknown')), $equipmentRows));
            $vehicleTypes = array_count_values(array_map(static fn(array $row): string => strtolower((string) ($row['maintenance_type'] ?? 'unknown')), $vehicleRows));
            $input = [
                'report_date' => date('Y-m-d'),
                'equipment_total' => (string) count($equipmentRows),
                'equipment_overdue' => (string) ($equipmentStatuses['overdue'] ?? 0),
                'equipment_pending' => (string) ($equipmentStatuses['pending'] ?? 0),
                'equipment_scheduled' => (string) ($equipmentStatuses['scheduled'] ?? 0),
                'equipment_in_progress' => (string) ($equipmentStatuses['in_progress'] ?? 0),
                'vehicle_total' => (string) count($vehicleRows),
                'vehicle_repairs' => (string) ($vehicleTypes['repair'] ?? 0),
                'vehicle_routine' => (string) ($vehicleTypes['routine'] ?? 0),
                'vehicle_inspections' => (string) ($vehicleTypes['inspection'] ?? 0),
                'vehicle_emergencies' => (string) ($vehicleTypes['emergency'] ?? 0),
                'recurring_type_count' => (string) count(array_filter($vehicleTypes, static fn(int $count): bool => $count > 1)),
                'follow_up_intent' => 'Prepare a facilities maintenance review; staff must verify safety, urgency, vendor, cost, and work-order decisions.',
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'maintenance.facilities_review', $this->aiFacilitiesContext(), $input,
                ['subject_type' => 'maintenance_facilities_review', 'scope' => 'authorized_maintenance_aggregate']
            );
            return $this->accepted($queued, 'Facilities maintenance review queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (\Throwable $e) {
            return $this->serverError('Maintenance assistance is temporarily unavailable');
        }
    }

    public function getAiFacilitiesReviews($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiFacilities()) return $this->forbidden('Maintenance review permission is required');
        return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview($this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'maintenance'), 'scope' => $review ? 'review' : 'own'], 'Facilities maintenance reviews retrieved');
    }

    public function postAiFacilitiesReviewApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiFacilities()) return $this->forbidden('Maintenance review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'maintenance.facilities_review');
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Facilities maintenance review approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiFacilitiesContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }

    private function canUseAiFacilities(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('maintenance.facilities_review', $this->aiFacilitiesContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canApproveAiFacilities(): bool
    {
        return $this->userHasAny(['maintenance_manage', 'maintenance_approve', 'maintenance_view'], [], ['director', 'school administrator', 'facilities manager', 'maintenance manager', 'system administrator', 'admin']);
    }

    // GET /api/maintenance - List all maintenance records (equipment by default)
    public function getMaintenance($id = null, $data = [], $segments = [])
    {
        // If ID provided, get specific record
        if ($id) {
            $result = $this->api->getEquipment($id);
        } else {
            // List all equipment maintenance with optional filters
            $filters = $data;
            $result = $this->api->listEquipment($filters);
        }
        return $this->handleResponse($result);
    }

    // POST /api/maintenance - Create new maintenance record (equipment by default)
    public function postMaintenance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardMaintenance()) return $guard;

        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->createVehicle($data);
        } else {
            $result = $this->api->createEquipment($data);
        }
        return $this->handleResponse($result);
    }

    // PUT /api/maintenance/{id} - Update maintenance record
    public function putMaintenance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardMaintenance()) return $guard;

        if (!$id) {
            return $this->badRequest('ID is required for update');
        }

        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->updateVehicle($id, $data);
        } else {
            $result = $this->api->updateEquipment($id, $data);
        }
        return $this->handleResponse($result);
    }

    // DELETE /api/maintenance/{id} - Delete maintenance record
    public function deleteMaintenance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardMaintenance()) return $guard;

        if (!$id) {
            return $this->badRequest('ID is required for deletion');
        }

        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->deleteVehicle($id);
        } else {
            $result = $this->api->deleteEquipment($id);
        }
        return $this->handleResponse($result);
    }

    // GET /api/maintenance/dashboard-summary
    public function getDashboardSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getDashboardSummary());
    }

    // GET /api/maintenance/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->readLogs($data);
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasRole('System Administrator') && !$this->userHasPermission('*')) {
            return $this->forbidden('System Administrator access required');
        }
        $result = $this->systemApi->clearLogs();
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasRole('System Administrator') && !$this->userHasPermission('*')) {
            return $this->forbidden('System Administrator access required');
        }
        $result = $this->systemApi->archiveLogs();
        return $this->handleResponse($result);
    }

    // GET /api/maintenance/config
    public function getConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->getSchoolConfig($id);
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/config
    public function postConfig($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardMaintenance()) return $guard;

        $result = $this->systemApi->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    /**
     * Unified API response handler (matches StudentsController)
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }
        return $this->success($result);
    }
}
