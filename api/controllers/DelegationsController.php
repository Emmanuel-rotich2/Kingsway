<?php
namespace App\API\Controllers;

use App\API\Services\DelegationService;
use App\API\Modules\users\UserPermissionManager;

class DelegationsController extends BaseController
{
    private DelegationService $delegationService;
    private UserPermissionManager $permissionManager;

    public function __construct()
    {
        parent::__construct();
        $this->delegationService = $this->contract('App\API\Services\DelegationService');
        $this->permissionManager = $this->contract('App\API\Modules\users\UserPermissionManager', $this->db->getConnection());
    }

    private function canManageDelegations(): bool
    {
        $userId = $this->user['id'] ?? $this->user['user_id'] ?? null;
        if (!$userId) {
            return false;
        }
        $permCheck = $this->permissionManager->hasPermission($userId, 'manage_delegations');
        return (bool) ($permCheck['success'] && $permCheck['has_permission']);
    }

    // GET /api/delegations or GET /api/delegations/{id}
    public function get($id = null, $data = [], $segments = [])
    {
        if (!$this->canManageDelegations()) {
            return $this->forbidden('Insufficient permissions');
        }

        if ($id) {
            $row = $this->delegationService->getDelegation((int)$id);
            if (!$row) {
                return $this->notFound('Delegation not found');
            }
            return $this->success($row);
        }

        list($page, $limit, $offset) = $this->getPaginationParams();
        list($search, $sort, $order) = $this->getSearchParams();

        $result = $this->delegationService->listDelegations(
            [
                'delegator_user_id' => $_GET['delegator_user_id'] ?? null,
                'delegate_user_id'  => $_GET['delegate_user_id'] ?? null,
                'active'            => isset($_GET['active']) ? (int)$_GET['active'] : null,
            ],
            $search,
            $sort,
            $order,
            (int)$limit,
            (int)$offset
        );

        return $this->success([
            'items' => $result['items'],
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
        ]);
    }

    // POST /api/delegations
    public function post($id = null, $data = [], $segments = [])
    {
        if (!$this->canManageDelegations()) {
            return $this->forbidden('Insufficient permissions');
        }

        $delegator = (int)($data['delegator_user_id'] ?? 0);
        $delegate = (int)($data['delegate_user_id'] ?? 0);
        $menuItem = (int)($data['menu_item_id'] ?? 0);
        $expiresAt = $data['expires_at'] ?? null;

        if (!$delegator || !$delegate || !$menuItem) {
            return $this->badRequest('delegator_user_id, delegate_user_id and menu_item_id are required');
        }

        try {
            $granted = $this->delegationService->delegateMenuItemToUser($delegator, $delegate, $menuItem, true, $expiresAt);
            $row = $this->delegationService->findDelegation($delegator, $delegate, $menuItem);
            return $this->created(['row' => $row, 'granted_permissions' => $granted]);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[DelegationsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('Failed to create delegation', 'An internal error occurred.');
        }
    }

    // PUT /api/delegations/{id}
    public function put($id = null, $data = [], $segments = [])
    {
        if (!$this->canManageDelegations()) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Delegation id required');
        }

        $fields = [];
        if (isset($data['active'])) {
            $fields['active'] = (int)$data['active'];
        }
        if (array_key_exists('expires_at', $data)) {
            $fields['expires_at'] = $data['expires_at'];
        }
        if (empty($fields)) {
            return $this->badRequest('No fields to update');
        }

        $ok = $this->delegationService->updateDelegation((int)$id, $fields);

        if (isset($fields['active']) && $fields['active'] === 0) {
            $this->delegationService->revokeDelegationPermissionsById((int)$id);
        }

        return $ok ? $this->success(['updated' => $ok]) : $this->serverError('Update failed');
    }

    // DELETE /api/delegations/{id}
    public function delete($id = null, $data = [], $segments = [])
    {
        if (!$this->canManageDelegations()) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Delegation id required');
        }

        $this->delegationService->revokeDelegationPermissionsById((int)$id);

        $ok = $this->delegationService->deleteDelegation((int)$id);

        return $ok ? $this->noContent() : $this->serverError('Delete failed');
    }
}
