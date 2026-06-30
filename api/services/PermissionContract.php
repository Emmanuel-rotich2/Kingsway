<?php

namespace App\API\Services;

class PermissionContract
{
    public const ACTIONS = [
        'view',
        'create',
        'edit',
        'approve',
        'reject',
        'delete',
        'export',
        'print',
    ];

    public static function permissionFor(string $module, string $action): string
    {
        return self::normalizeModule($module) . '_' . self::normalizeAction($action);
    }

    public static function aliasesFor(string $module, string $action): array
    {
        $module = self::normalizeModule($module);
        $action = self::normalizeAction($action);

        $aliases = [
            "{$module}_{$action}",
            "{$module}.{$action}",
        ];

        if ($action === 'edit') {
            $aliases[] = "{$module}_update";
            $aliases[] = "{$module}.update";
        } elseif ($action === 'approve') {
            $aliases[] = "{$module}_approve_final";
            $aliases[] = "{$module}.approve_final";
        } elseif ($action === 'view') {
            $aliases[] = "{$module}_view_all";
            $aliases[] = "{$module}_view_own";
            $aliases[] = "{$module}.view_all";
            $aliases[] = "{$module}.view_own";
        }

        return array_values(array_unique($aliases));
    }

    public static function userCan(array $user, string $module, string $action): bool
    {
        if (!empty($user['has_all_permissions'])) {
            return true;
        }

        $effective = $user['effective_permissions'] ?? $user['permissions'] ?? [];
        if (!is_array($effective)) {
            return false;
        }

        $lookup = [];
        foreach ($effective as $permission) {
            if (is_array($permission)) {
                $code = $permission['permission_code'] ?? $permission['code'] ?? $permission['name'] ?? null;
            } elseif (is_object($permission)) {
                $code = $permission->permission_code ?? $permission->code ?? $permission->name ?? null;
            } else {
                $code = $permission;
            }

            if (is_string($code) && $code !== '') {
                $lookup[$code] = true;
            }
        }

        foreach (self::aliasesFor($module, $action) as $alias) {
            if (isset($lookup[$alias])) {
                return true;
            }
        }

        return false;
    }

    public static function allowedActions(array $user, string $module): array
    {
        $allowed = [];
        foreach (self::ACTIONS as $action) {
            $allowed[$action] = self::userCan($user, $module, $action);
        }
        return $allowed;
    }

    private static function normalizeModule(string $module): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $module)), '_');
    }

    private static function normalizeAction(string $action): string
    {
        $action = trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $action)), '_');
        return $action === 'update' ? 'edit' : $action;
    }
}
