<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\DashboardRouter;
use App\API\Includes\FileLogger;
use PDO;

/**
 * System context for the AI agents — the agents' map of their own environment.
 *
 * The agents run inside this system, so they must know what is in it: every
 * page/route, what it is for, which roles may open it, which components it
 * contains (tables, forms, modals, charts), and — from the journals — who
 * actually uses it and what they do there. This service is the single
 * governed source of that context.
 *
 * Three surfaces consume it:
 *   - assistant.page_catalog  (tool): routes this operator's roles can open
 *   - assistant.page_activity (tool): who uses a page and what they do on it
 *   - page_context enrichment (stream/buffered assist): the current route's
 *     catalog entry rides along with every question
 *
 * Static facts (routes, role access, components) come from the codebase and
 * are cached briefly; activity facts come from the file journals, so they
 * reflect real usage without any new instrumentation.
 */
final class SystemContextService
{
    private const CACHE_TTL_SECONDS = 300;
    private const MAX_CATALOG_ROUTES = 200;
    private const MAX_ACTIVITY_ENTRIES = 4000;

    /** Component markers detected in a page template. */
    private const COMPONENT_PATTERNS = [
        'table' => '/<table[\s>]/i',
        'form' => '/<form[\s>]/i',
        'modal' => '/data-bs-toggle=["\']modal|class=["\'][^"\']*\bmodal\b/i',
        'chart' => '/<canvas[\s>]|chart\.js|Chart\s*\(/i',
        'filters' => '/filter|data-filter/i',
        'export' => '/export|csv|print/i',
        'kpi_cards' => '/kpi|stat-card|metric-card/i',
        'tabs' => '/nav-tabs|data-bs-toggle=["\']tab/i',
        'search' => '/type=["\']search|search-input|global-search/i',
        'wizard' => '/step|wizard|data-step/i',
        'approval' => '/approve|approval|review/i',
    ];

    private string $rootDir;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__, 2);
    }

    /**
     * Every route this operator's roles can open, with its catalog facts.
     *
     * @param list<int> $roleIds
     * @return array{status:string, routes:list<array<string,mixed>>, total:int}
     */
    public function catalogFor(array $roleIds): array
    {
        $catalog = $this->staticCatalog();
        $routes = [];
        foreach ($catalog as $route => $entry) {
            if (!$this->anyRoleAllowed($route, $roleIds)) {
                continue;
            }
            $routes[] = [
                'route' => $route,
                'label' => $entry['label'],
                'domain' => $entry['domain'],
                'components' => $entry['components'],
                'staff_roles' => count($entry['role_ids']),
            ];
        }
        usort($routes, static fn(array $a, array $b): int => strnatcasecmp($a['route'], $b['route']));
        return [
            'status' => 'ok',
            'total' => count($routes),
            'routes' => array_slice($routes, 0, self::MAX_CATALOG_ROUTES),
            'note' => 'Routes your roles can open. Ask assistant.page_activity for who uses a page and what they do there.',
        ];
    }

    /**
     * One route's full context: what it is, who may open it, what it
     * contains, and how it is actually being used.
     *
     * @param list<int> $roleIds
     * @return array<string,mixed>
     */
    public function pageEntry(string $route, array $roleIds): array
    {
        $route = trim($route);
        $catalog = $this->staticCatalog();
        $entry = $catalog[$route] ?? null;
        if ($entry === null) {
            // Route aliases resolve to their replacement.
            $alias = DashboardRouter::renderedRoute($route);
            if ($alias !== $route) {
                $route = $alias;
                $entry = $catalog[$route] ?? null;
            }
        }
        if ($entry === null) {
            return ['status' => 'unknown_page', 'route' => $route];
        }
        $allowed = $this->anyRoleAllowed($route, $roleIds);
        return [
            'status' => 'ok',
            'route' => $route,
            'label' => $entry['label'],
            'domain' => $entry['domain'],
            'components' => $entry['components'],
            'accessible_to_your_roles' => $allowed,
            'staff_roles_with_access' => count($entry['role_ids']),
            'activity' => $this->pageActivity($route),
        ];
    }

    /**
     * Real usage of one page (or all pages when route is empty): views,
     * unique users and the actions users take there, from the journals.
     *
     * @return array<string,mixed>
     */
    public function pageActivity(string $route = '', int $limit = 10): array
    {
        $route = trim($route);
        $limit = max(1, min(25, $limit));
        $views = [];   // route => ['views'=>n,'users'=>[uid=>n],'last'=>ts]
        foreach (FileLogger::recent('client', self::MAX_ACTIVITY_ENTRIES) as $entry) {
            if (!is_array($entry) || (string) ($entry['event'] ?? '') !== 'page_view') {
                continue;
            }
            $page = trim((string) ($entry['client_route'] ?? $entry['route'] ?? ''));
            if ($page === '' || ($route !== '' && $page !== $route)) {
                continue;
            }
            $uid = (int) ($entry['user_id'] ?? 0);
            $slot = &$views[$page];
            $slot['views'] = ($slot['views'] ?? 0) + 1;
            if ($uid > 0) {
                $slot['users'][$uid] = ($slot['users'][$uid] ?? 0) + 1;
            }
            $ts = (string) ($entry['timestamp'] ?? '');
            if ($ts > (string) ($slot['last'] ?? '')) {
                $slot['last'] = $ts;
            }
            unset($slot);
        }

        if ($route !== '') {
            $slot = $views[$route] ?? null;
            return [
                'status' => 'ok',
                'route' => $route,
                'page_views' => $slot['views'] ?? 0,
                'unique_users' => isset($slot['users']) ? count($slot['users']) : 0,
                'top_users' => $this->topUsers($slot['users'] ?? [], $limit),
                'last_activity' => $slot['last'] ?? null,
                'audit_actions' => $this->auditActionsFor($route, $limit),
            ];
        }

        // All pages: rank by views.
        $rows = [];
        foreach ($views as $page => $slot) {
            $rows[] = [
                'route' => $page,
                'views' => (int) $slot['views'],
                'unique_users' => isset($slot['users']) ? count($slot['users']) : 0,
                'last_activity' => $slot['last'] ?? null,
            ];
        }
        usort($rows, static fn(array $a, array $b): int => $b['views'] <=> $a['views']);
        return ['status' => 'ok', 'pages' => array_slice($rows, 0, $limit)];
    }

    /** Catalog entry for the current route, for page_context enrichment. */
    public function entryForRoute(string $route): ?array
    {
        $route = trim($route);
        if ($route === '') {
            return null;
        }
        $catalog = $this->staticCatalog();
        $entry = $catalog[$route] ?? null;
        if ($entry === null) {
            return null;
        }
        return [
            'route' => $route,
            'label' => $entry['label'],
            'domain' => $entry['domain'],
            'components' => $entry['components'],
        ];
    }

    // ------------------------------------------------------------------ internals

    /** @return array<string,array{label:string,domain:string,components:list<string>,role_ids:list<int>,file:string}> */
    private function staticCatalog(): array
    {
        $cacheFile = sys_get_temp_dir() . '/kingsway_page_catalog_' . md5($this->rootDir) . '.json';
        if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < self::CACHE_TTL_SECONDS) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && $cached !== []) {
                return $cached;
            }
        }

        $catalog = [];
        // Authoritative route list: the router's role map plus dashboards.
        $ref = new \ReflectionClass(DashboardRouter::class);
        $routeRoles = $ref->getConstant('ROUTE_ROLES');
        $dashboards = $ref->getConstant('ROLE_DASHBOARDS');
        $routes = [];
        if (is_array($routeRoles)) {
            foreach (array_keys($routeRoles) as $route) {
                $routes[$route] = true;
            }
        }
        if (is_array($dashboards)) {
            foreach ($dashboards as $route) {
                $routes[(string) $route] = true;
            }
        }
        // Routes present as page files but not yet in the role map (open
        // routes) still belong in the catalog.
        foreach ($this->pageFiles() as $route) {
            $routes[$route] = true;
        }

        foreach (array_keys($routes) as $route) {
            $route = (string) $route;
            if ($route === '' || str_contains($route, '..')) {
                continue;
            }
            $file = $this->pageFile($route);
            $components = $file !== null ? $this->detectComponents($file) : [];
            $roleIds = is_array($routeRoles) && isset($routeRoles[$route]) && is_array($routeRoles[$route])
                ? array_values(array_map('intval', $routeRoles[$route]))
                : [];
            $catalog[$route] = [
                'label' => $this->labelFor($route),
                'domain' => $this->domainFor($route),
                'components' => $components,
                'role_ids' => $roleIds,
                'file' => $file !== null,
            ];
        }

        @file_put_contents($cacheFile, json_encode($catalog), LOCK_EX);
        return $catalog;
    }

    /** @return list<string> */
    private function pageFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->rootDir . '/pages', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($this->rootDir . '/pages/'));
            $relative = str_replace('\\', '/', $relative);
            $relative = substr($relative, 0, -4); // strip .php
            $base = basename($relative);
            // Skip partials and role-variant includes: they are loaded by a
            // route page, never routed themselves.
            if (str_starts_with($base, '_')
                || preg_match('/_(snippet|partial)$/', $base) === 1
                || preg_match('/^(admin|manager|operator|viewer)_/', $base) === 1) {
                continue;
            }
            $files[] = $relative;
        }
        return $files;
    }

    private function pageFile(string $route): ?string
    {
        $candidate = $this->rootDir . '/pages/' . $route . '.php';
        if (is_file($candidate) && !str_contains($route, '..')) {
            return $candidate;
        }
        return null;
    }

    /** @return list<string> */
    private function detectComponents(string $file): array
    {
        $contents = @file_get_contents($file, false, null, 0, 400000);
        if (!is_string($contents) || $contents === '') {
            return [];
        }
        $found = [];
        foreach (self::COMPONENT_PATTERNS as $name => $pattern) {
            if (preg_match($pattern, $contents) === 1) {
                $found[] = $name;
            }
        }
        return $found;
    }

    private function labelFor(string $route): string
    {
        return ucwords(str_replace(['_', '/'], ' ', $route));
    }

    private function domainFor(string $route): string
    {
        if (str_contains($route, '/')) {
            return explode('/', $route, 2)[0];
        }
        foreach (['academic', 'admission', 'attendance', 'boarding', 'transport', 'inventory', 'catering', 'health', 'counseling', 'finance', 'staff', 'learner', 'student', 'parent', 'report', 'system', 'communicat', 'activit', 'maintenan', 'lesson', 'timetable', 'exam', 'payroll', 'fee', 'library', 'hostel', 'store', 'cafe', 'canteen', 'farm', 'security', 'user', 'role', 'permission', 'audit', 'log', 'backup', 'migration', 'dashboard'] as $token) {
            if (str_starts_with($route, $token)) {
                return $token;
            }
        }
        return 'general';
    }

    /** @param list<int> $roleIds */
    private function anyRoleAllowed(string $route, array $roleIds): bool
    {
        foreach ($roleIds as $roleId) {
            if (DashboardRouter::isRouteAllowedForRole($route, (int) $roleId)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,int> $users */
    private function topUsers(array $users, int $limit): array
    {
        arsort($users);
        $out = [];
        foreach (array_slice($users, 0, $limit, true) as $uid => $views) {
            // Opaque label: the engine learns usage patterns, never names.
            $out[] = ['user' => 'user_' . (int) $uid, 'page_views' => (int) $views];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function auditActionsFor(string $route, int $limit): array
    {
        $actions = [];
        foreach (FileLogger::recent('audit', self::MAX_ACTIVITY_ENTRIES) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entryRoute = (string) ($entry['route'] ?? $entry['client_route'] ?? '');
            if ($entryRoute !== $route) {
                continue;
            }
            $action = (string) ($entry['action'] ?? $entry['event'] ?? $entry['type'] ?? 'activity');
            $actions[$action] = ($actions[$action] ?? 0) + 1;
        }
        arsort($actions);
        $out = [];
        foreach (array_slice($actions, 0, $limit, true) as $action => $count) {
            $out[] = ['action' => mb_substr((string) $action, 0, 80), 'count' => (int) $count];
        }
        return $out;
    }
}
