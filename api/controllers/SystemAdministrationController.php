<?php
namespace App\API\Controllers;

use App\API\Services\SystemAdministrationService;
use Throwable;

final class SystemAdministrationController extends BaseController
{
    private SystemAdministrationService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new SystemAdministrationService($this->db->getConnection());
    }

    private function guard(array $permissions = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if ($this->userHasAnyRole(['System Administrator', 'Super Administrator', 'Super Admin'])) {
            return null;
        }

        foreach ($permissions as $permission) {
            if ($this->userHasPermission($permission)) {
                return null;
            }
        }

        return $this->forbidden('System Administrator permission required');
    }

    private function actorId(): ?int
    {
        $id = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $id === null ? null : (int) $id;
    }

    private function audit(string $action, string $entity, ?int $entityId, array $details = [], string $status = 'success'): void
    {
        try {
            $this->service->writeAudit(
                $this->actorId(),
                $action,
                $entity,
                $entityId,
                $details,
                $status,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            );
        } catch (Throwable $e) {
            error_log('[SystemAdministrationController] Audit failed: ' . $e->getMessage());
        }
    }

    public function getDashboard($id = null, $data = [], $segments = [])
    {
        if ($response = $this->guard(['system.dashboard.view'])) return $response;
        return $this->success($this->service->dashboard());
    }

    public function getResource($id = null, $data = [], $segments = [])
    {
        if ($response = $this->guard([
            'system.users.view', 'system.rbac.view', 'system.security.view',
            'system.configuration.view', 'system.navigation.view',
            'system.monitoring.view', 'system.data_governance.view',
            'system.audit.view', 'system.developer_tools.view'
        ])) return $response;

        try {
            $key = trim((string)($data['key'] ?? $_GET['key'] ?? ''));
            return $this->success($this->service->resource($key, $data));
        } catch (Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    public function postResource($id = null, $data = [], $segments = [])
    {
        if ($response = $this->guard([
            'system.users.manage', 'system.rbac.manage', 'system.security.manage',
            'system.configuration.manage', 'system.navigation.manage',
            'system.monitoring.manage', 'system.data_governance.manage',
            'system.audit.manage', 'system.developer_tools.manage'
        ])) return $response;

        try {
            $key = trim((string)($data['key'] ?? ''));
            $record = is_array($data['record'] ?? null) ? $data['record'] : [];
            $recordId = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;
            $result = $this->service->saveResource($key, $record, $recordId, $this->actorId());
            $this->audit($recordId ? 'update' : 'create', $key, (int)($result['id'] ?? 0), ['fields' => array_keys($record)]);
            return $recordId ? $this->success($result, 'Record updated') : $this->created($result, 'Record created');
        } catch (Throwable $e) {
            $this->audit('save_failed', (string)($data['key'] ?? 'system'), null, ['error' => $e->getMessage()], 'failure');
            return $this->unprocessable($e->getMessage());
        }
    }

    public function deleteResource($id = null, $data = [], $segments = [])
    {
        if ($response = $this->guard([
            'system.rbac.manage', 'system.security.manage', 'system.configuration.manage',
            'system.navigation.manage', 'system.monitoring.manage',
            'system.data_governance.manage', 'system.audit.manage',
            'system.developer_tools.manage'
        ])) return $response;

        try {
            $key = trim((string)($data['key'] ?? $_GET['key'] ?? ''));
            $recordId = (int)($data['id'] ?? $id ?? 0);
            $this->service->deleteResource($key, $recordId);
            $this->audit('delete', $key, $recordId);
            return $this->success(null, 'Record deleted');
        } catch (Throwable $e) {
            return $this->unprocessable($e->getMessage());
        }
    }

    public function postAction($id = null, $data = [], $segments = [])
    {
        if ($response = $this->guard([
            'system.users.manage', 'system.security.manage', 'system.monitoring.manage',
            'system.data_governance.manage', 'system.developer_tools.manage'
        ])) return $response;

        try {
            $resource = trim((string)($data['resource'] ?? ''));
            $action = trim((string)($data['action'] ?? ''));
            $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
            $result = $this->service->runAction($resource, $action, $payload, $this->actorId());
            $this->audit($action, $resource, isset($payload['id']) ? (int)$payload['id'] : null, $payload);
            return $this->success($result, 'Action completed');
        } catch (Throwable $e) {
            return $this->unprocessable($e->getMessage());
        }
    }
}
