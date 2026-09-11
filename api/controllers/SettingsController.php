<?php

namespace App\API\Controllers;

use App\API\Modules\users\RoleManager;
use App\API\Modules\system\SystemAPI;

/**
 * Settings Controller
 *
 * Exposes role and permission administration plus a database-backup trigger,
 * consumed by the System Settings page (js/pages/settings.js) via the DataStore
 * / DataTable pipeline. All business logic and DB access live in RoleManager
 * and SystemAPI; this controller only validates auth/RBAC, reads input, and
 * delegates. Routes (router maps GET /api/settings/roles -> getRoles(), etc.):
 *   GET  /settings/roles        -> getRoles()
 *   POST /settings/roles        -> postRoles()        (create)
 *   PUT  /settings/roles/{id}   -> putRoles()         (update)
 *   DELETE /settings/roles/{id} -> deleteRoles()      (delete)
 *   GET  /settings/permissions  -> getPermissions()
 *   POST /settings/backup       -> postBackup()
 *
 * Column aliases are chosen to match the DataTable column config in settings.js
 * (e.g. roles.name -> role_name, roles.is_active -> status).
 */
class SettingsController extends BaseController
{
    private $roleManager;
    private $systemApi;

    public function __construct()
    {
        parent::__construct();
        $this->roleManager = $this->contract('App\API\Modules\users\RoleManager', $this->db->getConnection());
        $this->systemApi = $this->contract('App\API\Modules\system\SystemAPI');
    }

    /**
     * GET /api/settings/roles
     */
    public function getRoles($id = null, $data = [], $segments = [])
    {
        $result = $id
            ? $this->roleManager->getRoleForSettings((int)$id)
            : $this->roleManager->listRolesForSettings();
        return $this->settingsResponse($result);
    }

    /**
     * POST /api/settings/roles  (create a role)
     */
    public function postRoles($id = null, $data = [])
    {
        $name = trim($data['role_name'] ?? $data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        if ($name === '') {
            return $this->badRequest('Role name is required');
        }
        return $this->settingsResponse($this->roleManager->createRoleForSettings($name, $description));
    }

    /**
     * PUT /api/settings/roles/{id}  (update a role)
     */
    public function putRoles($id = null, $data = [])
    {
        if (!$id) {
            return $this->badRequest('Role id is required');
        }
        $name = trim($data['role_name'] ?? $data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        return $this->settingsResponse($this->roleManager->updateRoleForSettings((int)$id, $name, $description));
    }

    /**
     * DELETE /api/settings/roles/{id}
     */
    public function deleteRoles($id = null, $data = [])
    {
        if (!$id) {
            return $this->badRequest('Role id is required');
        }
        return $this->settingsResponse($this->roleManager->deleteRoleForSettings((int)$id));
    }

    /**
     * GET /api/settings/permissions
     */
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        return $this->settingsResponse($this->roleManager->listPermissionsForSettings());
    }

    /**
     * POST /api/settings/backup
     * Delegates the dump to SystemAPI (best-effort; failures are reported,
     * never fatal to the UI).
     */
    public function postBackup($id = null, $data = [])
    {
        $result = $this->systemApi->createDatabaseBackup();
        if (isset($result['success']) && !$result['success']) {
            return $this->serverError($result['message'] ?? 'Backup failed');
        }
        return $this->success(
            $result['data'] ?? [],
            $result['message'] ?? 'Backup created'
        );
    }

    private function settingsResponse($result)
    {
        if (($result['code'] ?? 200) >= 400) {
            $code = $result['code'] ?? 500;
            $message = $result['message'] ?? 'Operation failed';
            if ($code === 404) return $this->notFound($message);
            if ($code === 403) return $this->forbidden($message);
            if ($code === 409) return $this->badRequest($message);
            return $this->error($message, $code);
        }
        return $this->success($result['data'] ?? []);
    }
}
