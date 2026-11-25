<?php
/**
 * System Administrator console route registry.
 *
 * Single source of truth for the UI shell applied to every destination in the
 * role-2 sidebar, plus the account/report routes the role can reach.
 */

if (!function_exists('system_admin_console_routes')) {
    function system_admin_console_routes(): array
    {
        static $routes = null;

        if ($routes === null) {
            $routes = [
                'system_administrator_dashboard',
                'manage_users',
                'bootstrap_school_administrator',
                'account_status',
                'manage_roles',
                'role_permission_matrix',
                'resource_based_permissions',
                'authentication_logs',
                'failed_login_attempts',
                'active_sessions',
                'token_management',
                'ip_whitelist_blacklist',
                'domain_isolation_rules',
                'time_bound_access',
                'permission_policies',
                'route_access_rules',
                'system_settings',
                'payment_integration_settings',
                'feature_flags',
                'module_enablement',
                'maintenance_mode',
                'route_registry',
                'sidebar_menus',
                'role_navigation_config',
                'analytics_catalogue',
                'system_health',
                'log_viewer',
                'error_logs',
                'background_jobs',
                'api_metrics',
                'rate_limiting_status',
                'migrations',
                'backups',
                'data_retention',
                'activity_audit_logs',
                'permission_changes',
                'policy_violations',
                'security_incidents',
                'api_explorer',
                'webhook_registry',
                'system_diagnostics',
                'job_inspector',
                'communications/messages_inbox',
                'manage_system_announcements',
                'manage_sms_configurations',
                'manage_email_configurations',
                'manage_whatsapp_configurations',
                'account_settings',
                'profile',
                'report_system_audit_summary',
                'internal_products_catalog',
                'my_family',
                'staff_my_admission',
            ];
        }

        return $routes;
    }
}

if (!function_exists('system_admin_is_console_route')) {
    function system_admin_is_console_route(?string $route): bool
    {
        if (!is_string($route) || $route === '') {
            return false;
        }

        return in_array($route, system_admin_console_routes(), true);
    }
}
