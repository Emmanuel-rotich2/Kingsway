<?php

namespace App\API\Services;

/**
 * Single source of truth for sidebar menus.
 *
 * Reads config/role_sidebars.php (keyed by numeric role_id) and normalises it
 * into the flat parent/child item shape used by both the login/refresh path
 * (AuthAPI) and the profile path (UsersAPI::getSidebarItems). Centralising the
 * normalisation here prevents the two code-paths from drifting apart (the old
 * split-brain where login read the file but the profile path read the DB menu).
 */
class SidebarConfigReader
{
    /**
     * Return the effective, fully-normalised menu for every configured role.
     * Administrative previews and dashboard APIs must use this method instead
     * of exposing the raw PHP array or the obsolete database menu tables.
     */
    public static function forAllRoles(): array
    {
        $path = dirname(__DIR__, 2) . '/config/role_sidebars.php';
        $config = file_exists($path) ? (include $path) : [];
        $menus = [];
        foreach (array_keys($config) as $roleId) {
            $roleId = (int) $roleId;
            $menus[$roleId] = self::forRoles([$roleId]);
        }
        return $menus;
    }

    /**
     * Build one blended sidebar for several roles.
     *
     * A user's roles are additive, but a route must only appear once. This is
     * especially important for teachers who also hold a leadership role: the
     * same lesson-plan or timetable route may be contributed by both roles.
     */
    public static function forRoles(array $roleIds): array
    {
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (!$roleIds) return [];

        // SYSTEM and SCHOOL are separate trust domains. A System Administrator
        // may have stale or accidental secondary role assignments, but those
        // roles must never expand the infrastructure account into school data.
        if (in_array(2, $roleIds, true)) {
            // Preserve the explicitly curated system groups. The school-domain
            // module organizer would otherwise reclassify them as school work.
            return self::attachModuleReports(self::forRole(2), [2]);
        }

        // School-domain menus are semantically deduplicated only after every
        // role has contributed its context. If duplicate routes are removed
        // here, the first role can incorrectly force a route into Operations
        // before a later role identifies it as Academic, Discipline, Reports,
        // or another specific module. The Accountant retains its deliberately
        // curated finance workflow and therefore keeps immediate deduplication.
        $deferRouteDeduplication = $roleIds[0] !== 10;

        // The first assigned role supplies the primary navigation. Additional
        // roles contribute capabilities, not a second copy of their entire
        // menu. This is what makes Headteacher + Subject Teacher, Deputy +
        // Subject Teacher, and Class Teacher + Subject Teacher feel like one
        // coherent workspace.
        $merged = [];
        $parentIndexes = [];
        $routeOwners = [];
        foreach (self::forRole($roleIds[0]) as $item) {
            if (!is_array($item)) continue;
            if (!empty($item['url'])) {
                $parentRoute = self::routeKey($item);
                if (!$deferRouteDeduplication && isset($routeOwners[$parentRoute])) continue;
                $routeOwners[$parentRoute] = true;
            }
            $children = [];
            foreach (($item['subitems'] ?? []) as $child) {
                if (!is_array($child)) continue;
                $routeKey = self::childCapabilityKey($child);
                if (!$deferRouteDeduplication && isset($routeOwners[$routeKey])) continue;
                $routeOwners[$routeKey] = true;
                $children[] = $child;
            }
            $item['subitems'] = $children;
            $index = count($merged);
            $merged[] = $item;
            $parentIndexes[self::labelKey($item)] = $index;
        }

        foreach (array_slice($roleIds, 1) as $roleId) {
            foreach (self::forRole($roleId) as $item) {
                if (!is_array($item)) continue;

                // A user has one dashboard: the primary role's dashboard.
                if (self::labelKey($item) === 'dashboard') continue;

                $label = self::labelKey($item);
                $targetIndex = $parentIndexes[$label] ?? null;

                // Teacher capabilities inherited by a leadership/class role
                // belong under one role-neutral Teaching section. This avoids
                // a second "Subject Teacher" menu while preserving its links.
                if (in_array($label, ['my subjects', 'my students', 'timetable', 'my teaching timetable', 'lesson plans', 'assessments', 'examinations'], true)) {
                    // Leadership roles already have an Academic workspace;
                    // subject-teacher capabilities belong there instead of
                    // appearing as a second Teaching menu. Deputy Academic
                    // still retains My Teaching for daily class duties. Roles
                    // without either workspace receive the shared Teaching
                    // section.
                    $targetIndex = $parentIndexes['academic']
                        ?? $parentIndexes['my teaching']
                        ?? self::ensureParent($merged, $parentIndexes, 'Teaching', 'fas fa-chalkboard-teacher');
                    $item['subitems'] = !empty($item['subitems'])
                        ? $item['subitems']
                        : [$item];
                } elseif ($label === 'resources') {
                    $targetIndex = self::ensureParent($merged, $parentIndexes, 'Resources', 'fas fa-book-open');
                } elseif ($targetIndex === null) {
                    // Keep genuinely distinct personal/operational areas such
                    // as My HR, Communications, and Family as their own item.
                    if (!$deferRouteDeduplication
                        && !empty($item['url'])
                        && isset($routeOwners[self::routeKey($item)])) {
                        continue;
                    }
                    $filteredChildren = [];
                    foreach (self::uniqueChildren($item['subitems'] ?? []) as $child) {
                        $childRoute = self::childCapabilityKey($child);
                        if (!$deferRouteDeduplication && isset($routeOwners[$childRoute])) continue;
                        $routeOwners[$childRoute] = true;
                        $filteredChildren[] = $child;
                    }
                    $item['subitems'] = $filteredChildren;
                    $targetIndex = count($merged);
                    $merged[] = $item;
                    $parentIndexes[$label] = $targetIndex;
                    if (!empty($item['url'])) $routeOwners[self::routeKey($item)] = true;
                    // The complete new parent has already been appended; its
                    // children must not be appended a second time below.
                    continue;
                }

                $children = $item['subitems'] ?? [];
                foreach ($children as $child) {
                    if (!is_array($child)) continue;
                    $routeKey = self::childCapabilityKey($child);
                    if (!$deferRouteDeduplication && isset($routeOwners[$routeKey])) continue;
                    $routeOwners[$routeKey] = true;
                    $merged[$targetIndex]['subitems'][] = $child;
                }
            }
        }

        // The accountant menu is already deliberately arranged around finance
        // workflows (Fee Structure, Student Billing, Payroll, Expenditure,
        // Accounts & Balances, and so on). Reclassifying its individual routes
        // by generic prefixes destroys those workflow groups and sends unknown
        // finance routes to the Operations fallback.
        if ($roleIds[0] === 10) {
            return self::attachModuleReports(
                self::relocatePersonalHrRoutes($merged, $roleIds),
                $roleIds
            );
        }

        return self::attachModuleReports(
            self::relocatePersonalHrRoutes(self::organizeIntoModules($merged), $roleIds),
            $roleIds
        );
    }

    /**
     * staff_leave is a personal self-service page for ordinary staff. Director
     * and School Administrator use the same route as an approval/management
     * workspace, so only those two roles retain it under Staff.
     */
    private static function relocatePersonalHrRoutes(array $menu, array $roleIds): array
    {
        if (array_intersect([3, 4], array_map('intval', $roleIds))) return $menu;

        $leaveItem = null;
        foreach ($menu as $parentIndex => &$parent) {
            if (($parent['url'] ?? null) === 'staff_leave') {
                $leaveItem ??= $parent;
                unset($menu[$parentIndex]);
                continue;
            }
            $children = [];
            foreach (($parent['subitems'] ?? []) as $child) {
                if (($child['url'] ?? null) === 'staff_leave') {
                    $leaveItem ??= $child;
                    continue;
                }
                $children[] = $child;
            }
            $parent['subitems'] = $children;
        }
        unset($parent);
        if ($leaveItem === null) return array_values($menu);

        $menu = array_values(array_filter($menu, static function (array $parent): bool {
            return !empty($parent['url']) || !empty($parent['subitems']);
        }));
        $targetIndex = null;
        foreach ($menu as $index => $parent) {
            if (strtolower((string) ($parent['label'] ?? '')) === 'my hr') {
                $targetIndex = $index;
                break;
            }
        }
        if ($targetIndex === null) {
            $targetIndex = count($menu);
            $menu[] = self::item(970000, null, [
                'label' => 'My HR', 'url' => null, 'icon' => 'fas fa-id-badge'
            ], $targetIndex, []);
        }

        $leaveItem['label'] = 'Leave Requests';
        $leaveItem['tooltip'] = $leaveItem['tooltip'] ?? 'Request and track my leave';
        $leaveItem['parent_id'] = $menu[$targetIndex]['id'];
        $leaveItem['display_order'] = count($menu[$targetIndex]['subitems'] ?? []);
        $menu[$targetIndex]['subitems'][] = $leaveItem;
        return $menu;
    }

    private static function attachModuleReports(array $menu, array $roleIds): array
    {
        // One reporting destination. The previous implementation converted a
        // single Reports menu into many top-level "... Reports" groups, which
        // scattered related analysis throughout the sidebar and made labels
        // overflow. Legacy report links and governed registry reports are now
        // merged into one deduplicated Reports & Analytics section.
        $reportChildren = [];
        $cleanMenu = [];
        foreach ($menu as $parent) {
            $label = strtolower(trim((string)($parent['label'] ?? '')));
            $isReportParent = $label === 'reports'
                || $label === 'reports & analytics'
                || str_ends_with($label, ' reports');
            if ($isReportParent) {
                $reportChildren = array_merge($reportChildren, $parent['subitems'] ?? []);
                continue;
            }
            $children = [];
            foreach (($parent['subitems'] ?? []) as $child) {
                if (self::isReportRoute(
                    (string) ($child['url'] ?? ''),
                    (string) ($child['label'] ?? '')
                )) {
                    $reportChildren[] = $child;
                    continue;
                }
                $children[] = $child;
            }
            $parent['subitems'] = $children;
            if (self::isReportRoute(
                (string) ($parent['url'] ?? ''),
                (string) ($parent['label'] ?? '')
            )) {
                $reportChildren[] = $parent;
                continue;
            }
            if ($children || !empty($parent['url'])) $cleanMenu[] = $parent;
        }

        $parentId = (count($roleIds) === 1 && (int)$roleIds[0] === 2) ? 29800 : 960000;
        foreach (ReportRegistry::forRoles($roleIds) as $report) {
            $reportChildren[] = ['label'=>$report['title'],'url'=>$report['route']];
        }
        $children = [];$seen = [];
        foreach ($reportChildren as $child) {
            $route = strtolower(trim((string)($child['url'] ?? '')));
            if ($route === '' || isset($seen[$route])) continue;
            $seen[$route] = true;
            $fullLabel = (string)($child['label'] ?? 'Report');
            $child['label'] = self::compactLabel($fullLabel, true);
            $child['tooltip'] = $fullLabel;
            $child['id'] = $parentId + count($children) + 1;
            $child['parent_id'] = $parentId;
            $children[] = self::item($child['id'], $parentId, $child, count($children));
        }
        if ($children) {
            $cleanMenu[] = self::item($parentId, null, [
                'label'=>'Reports & Analytics','url'=>null,'icon'=>'fas fa-chart-column'
            ], count($cleanMenu), $children);
        }
        return self::compactMenuLabels($cleanMenu);
    }

    private static function isReportRoute(string $route, string $label = ''): bool
    {
        $route = strtolower(trim($route));
        $label = strtolower(trim($label));
        if ($route === 'analytics_catalogue'
            || str_contains($route, 'report')
            || str_ends_with($route, '_trends')
            || str_contains($label, 'report')) {
            return true;
        }
        foreach (ReportRegistry::all() as $report) {
            if ($route !== '' && $route === strtolower((string) ($report['route'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    private static function compactMenuLabels(array $menu): array
    {
        foreach ($menu as &$parent) {
            $parent['tooltip'] = $parent['tooltip'] ?? $parent['label'];
            $parent['label'] = self::compactLabel((string)$parent['label']);
            foreach (($parent['subitems'] ?? []) as &$child) {
                $child['tooltip'] = $child['tooltip'] ?? $child['label'];
                $child['label'] = self::compactLabel((string)$child['label']);
            }
            unset($child);
        }
        unset($parent);
        return $menu;
    }

    private static function compactLabel(string $label, bool $report=false): string
    {
        $map = [
            'KCB Disbursement Reconciliation'=>'KCB Reconciliation',
            'Payment Integration Accounts'=>'Payment Accounts',
            'First School Administrator'=>'First School Admin',
            'Academic Planning Oversight'=>'Academic Oversight',
            'School-wide Grading Status'=>'Grading Status',
            'Boarding Student Profiles'=>'Boarding Profiles',
            'Assign Students to Routes'=>'Route Assignments',
            'All Applications & Stages'=>'Applications',
            'Subjects / Learning Areas'=>'Learning Areas',
            'Daily Attendance Overview'=>'Daily Attendance',
            'Subject Students by Class'=>'Subject Learners',
            'Current Enrollment by Class & Stream'=>'Class Enrollment',
            'Communication Delivery Summary'=>'Message Delivery',
            'Enrolled Admissions by Cohort'=>'Admissions by Cohort',
            'CBC Class Assessment Summary'=>'CBC Assessment',
            'System Audit Trail Summary'=>'System Audit Trail',
            'Discipline Incident Trend'=>'Discipline Trends',
        ];
        $label = $map[$label] ?? $label;
        if ($report) $label = preg_replace('/\s+Reports?$/i', '', $label) ?: $label;
        return $label;
    }

    /**
     * Build the normalised sidebar for a role, or [] if the role is undefined.
     */
    public static function forRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        static $config = null;
        if ($config === null) {
            $path = dirname(__DIR__, 2) . '/config/role_sidebars.php';
            $config = file_exists($path) ? (include $path) : [];
        }

        if (!isset($config[$roleId])) {
            return [];
        }

        // IDs: parent = roleId*10000 + groupIndex*100; child = parentId + childIndex + 1
        $items      = [];
        $groupIndex = 0;
        foreach ($config[$roleId] as $item) {
            $parentId = $roleId * 10000 + $groupIndex * 100;
            $subitems = [];
            $subIndex = 1;
            foreach ($item['subitems'] ?? [] as $sub) {
                $subitems[] = self::item($parentId + $subIndex, $parentId, $sub);
                $subIndex++;
            }
            $items[] = self::item($parentId, null, $item, $groupIndex, $subitems);
            $groupIndex++;
        }

        // Every authenticated school-domain staff member may browse the
        // internal merchandise catalogue (view-only). Sell + management
        // actions are gated by inventory write permissions in the controller
        // and the page controller. The family workspace is resolved from the
        // staff JWT and linked parents record; non-parent staff see a clear
        // access message instead of receiving another login flow.
        if ($roleId >= 2) {
            $catalogParent = $roleId * 10000 + 9800;
            $items[] = self::item($catalogParent, null, [
                'label' => 'Products Catalog', 'url' => 'internal_products_catalog',
                'icon' => 'fas fa-store', 'subitems' => []
            ], $groupIndex++);
            if (in_array($roleId, [4, 14], true)) {
                $managementParent = $roleId * 10000 + 9850;
                $items[] = self::item($managementParent, null, [
                    'label' => 'Manage Products', 'url' => 'products_catalog_management',
                    'icon' => 'fas fa-box-open', 'subitems' => []
                ], $groupIndex++);
            }
            $familyParent = $roleId * 10000 + 9900;
            $items[] = self::item($familyParent, null, [
                'label' => 'My Family', 'url' => 'my_family',
                'icon' => 'fas fa-house-user', 'subitems' => []
            ], $groupIndex);
        }

        return $items;
    }

    /**
     * Normalise a single sidebar node (group or subitem) to the shared shape.
     */
    private static function item(
        int $id,
        ?int $parentId,
        array $node,
        int $displayOrder = 0,
        array $subitems = []
    ): array {
        $url = $node['url'] ?? null;
        // Generated IDs reserve 20000-29999 for role 2 and all its children.
        $domain = ($id >= 20000 && $id < 30000) ? 'SYSTEM' : 'SCHOOL';
        return [
            'id'                    => $id,
            'parent_id'             => $parentId,
            'label'                 => $node['label'],
            'icon'                  => $node['icon'] ?? null,
            'url'                   => $url,
            'route_url'             => $url,
            'domain'                => $domain,
            'display_order'         => $displayOrder,
            'subitems'              => $subitems,
            'show_badge'            => false,
            'badge_source'          => null,
            'badge_color'           => 'danger',
            'open_in_new_tab'       => false,
            'requires_confirmation' => false,
            'confirmation_message'  => null,
            'css_class'             => null,
            'tooltip'               => $node['tooltip'] ?? null,
        ];
    }

    private static function uniqueChildren(array $children): array
    {
        $unique = [];
        $seen = [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $key = self::childCapabilityKey($child);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $child;
        }
        return $unique;
    }

    private static function labelKey(array $item): string
    {
        return strtolower(trim((string) ($item['label'] ?? '')));
    }

    private static function ensureParent(array &$items, array &$indexes, string $label, string $icon): int
    {
        $key = strtolower($label);
        if (isset($indexes[$key])) return $indexes[$key];
        $index = count($items);
        $items[] = self::item(900000 + $index, null, [
            'label' => $label,
            'url' => null,
            'icon' => $icon,
            'subitems' => [],
        ], $index);
        $indexes[$key] = $index;
        return $index;
    }

    private static function routeKey(array $item): string
    {
        $url = (string) ($item['url'] ?? $item['route_url'] ?? '');
        if ($url === '') {
            return 'label:' . strtolower(trim((string) ($item['label'] ?? '')));
        }
        if (strpos($url, 'route=') !== false) {
            $parts = parse_url($url);
            if (isset($parts['query'])) {
                parse_str($parts['query'], $query);
                $url = (string) ($query['route'] ?? $url);
            }
        }
        return 'route:' . strtolower(trim($url));
    }

    /**
     * Collapse technical routes which represent one user-facing capability.
     * The first route encountered remains the destination shown to the user.
     */
    private static function childCapabilityKey(array $item): string
    {
        $route = self::routeKey($item);
        $aliases = [
            // The manager contains the complete CBC structure (outcomes,
            // crosswalks and tree). Keep one curriculum entry point instead
            // of exposing the older overview page beside it.
            'route:curriculum_cbc' => 'capability:cbc_curriculum',
            'route:cbc_curriculum' => 'capability:cbc_curriculum',
            'route:schemes_of_work' => 'capability:schemes_of_work',
            'route:my_schemes_of_work' => 'capability:schemes_of_work',
            'route:view_teaching_materials' => 'capability:teaching_materials',
            'route:teaching_materials' => 'capability:teaching_materials',
            'route:view_past_papers' => 'capability:past_papers',
            'route:past_papers' => 'capability:past_papers',
            // manage_timetable now includes the teacher timetable views;
            // do not expose the old standalone page beside it.
            'route:manage_timetable' => 'capability:timetable_management',
            'route:teacher_timetables' => 'capability:timetable_management',
            // One teacher gradebook. The class-teacher and subject-teacher
            // pages are scope variants of the same marks-entry capability.
            'route:enter_marks' => 'capability:assessment_gradebook',
            'route:subject_grade_entry' => 'capability:assessment_gradebook',
            'route:grade_entry' => 'capability:assessment_gradebook',
            // CAT is a legacy label for formative assessment. Creation and
            // management now live in one workspace for blended teacher roles.
            'route:create_assessment' => 'capability:formative_assessments',
            'route:create_subject_cat' => 'capability:formative_assessments',
            'route:my_cats' => 'capability:formative_assessments',
            'route:my_subject_cats' => 'capability:formative_assessments',
            // One teacher-facing examination calendar. The subject page is a
            // filtered view of the school schedule, not a second function.
            'route:exam_schedule' => 'capability:exam_schedule',
            'route:subject_exam_schedule' => 'capability:exam_schedule',
            // Class and subject attendance history use the same history
            // workspace; the backend applies the teacher's blended scope.
            'route:class_attendance_history' => 'capability:attendance_history',
            'route:view_attendance' => 'capability:attendance_history',
        ];
        return $aliases[$route] ?? $route;
    }

    /**
     * Convert role menus into school-work modules. Role menus describe where
     * a route came from; this method describes what the route does. That
     * distinction prevents a Headteacher + Subject Teacher from seeing two
     * copies of academic, teaching, assessment, or timetable work.
     */
    private static function organizeIntoModules(array $items): array
    {
        $modules = [
            'Admissions' => ['icon' => 'fas fa-user-plus', 'children' => []],
            'Academic' => ['icon' => 'fas fa-graduation-cap', 'children' => []],
            'Timetables' => ['icon' => 'fas fa-calendar-alt', 'children' => []],
            'Teaching & Learning' => ['icon' => 'fas fa-chalkboard-teacher', 'children' => []],
            'Assessment & Exams' => ['icon' => 'fas fa-file-alt', 'children' => []],
            'Learners' => ['icon' => 'fas fa-users', 'children' => []],
            'Attendance' => ['icon' => 'fas fa-clipboard-check', 'children' => []],
            'Health & Welfare' => ['icon' => 'fas fa-heartbeat', 'children' => []],
            'Discipline' => ['icon' => 'fas fa-gavel', 'children' => []],
            'Staff' => ['icon' => 'fas fa-user-tie', 'children' => []],
            'Finance' => ['icon' => 'fas fa-coins', 'children' => []],
            'Controls & Audit' => ['icon' => 'fas fa-shield-halved', 'children' => []],
            'Transport' => ['icon' => 'fas fa-bus', 'children' => []],
            'Boarding' => ['icon' => 'fas fa-bed', 'children' => []],
            'Events & Calendar' => ['icon' => 'fas fa-calendar-alt', 'children' => []],
            'Families & Communication' => ['icon' => 'fas fa-comments', 'children' => []],
            'Library' => ['icon' => 'fas fa-book', 'children' => []],
            'Inventory & Store' => ['icon' => 'fas fa-boxes', 'children' => []],
            'Catering & Food' => ['icon' => 'fas fa-utensils', 'children' => []],
            'Activities & Clubs' => ['icon' => 'fas fa-person-running', 'children' => []],
            'Mentorship & Growth' => ['icon' => 'fas fa-seedling', 'children' => []],
            'Spiritual Life' => ['icon' => 'fas fa-church', 'children' => []],
            'Quick Tools' => ['icon' => 'fas fa-toolbox', 'children' => []],
            'Reports & Analytics' => ['icon' => 'fas fa-chart-bar', 'children' => []],
            'Resources' => ['icon' => 'fas fa-folder-open', 'children' => []],
            'Web Management' => ['icon' => 'fas fa-globe', 'children' => []],
            'Operations' => ['icon' => 'fas fa-cogs', 'children' => []],
            'My HR' => ['icon' => 'fas fa-id-badge', 'children' => []],
        ];
        $standalone = [];
        $candidates = [];
        $candidateOrder = [];

        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $parentLabel = self::labelKey($item);

            // Keep the dashboard as the first standalone destination.
            if ($parentLabel === 'dashboard') {
                $standalone[] = $item;
                continue;
            }

            $children = $item['subitems'] ?? [];
            if (!$children && !empty($item['url'])) {
                $children = [$item];
            }
            foreach ($children as $child) {
                if (!is_array($child)) continue;
                $capability = self::childCapabilityKey($child);
                $module = self::moduleForRoute($child['url'] ?? '', $parentLabel);
                if (!isset($modules[$module])) {
                    $module = 'Operations';
                }
                $child['parent_id'] = null;
                if (!isset($candidates[$capability])) {
                    $candidates[$capability] = ['child' => $child, 'module' => $module];
                    $candidateOrder[] = $capability;
                    continue;
                }

                // A route may be contributed by several roles/sections. A
                // specific classification must replace a generic one, and a
                // governed report classification always wins so reports stay
                // under the single Reports & Analytics section.
                $currentModule = $candidates[$capability]['module'];
                if (self::preferModule($module, $currentModule)) {
                    $candidates[$capability]['module'] = $module;
                    // Preserve the first user-facing label, but retain the
                    // better source context for diagnostics/tooltips.
                    $candidates[$capability]['child']['source_section'] = $parentLabel;
                }
            }
        }

        foreach ($candidateOrder as $capability) {
            $candidate = $candidates[$capability];
            $modules[$candidate['module']]['children'][] = $candidate['child'];
        }

        $result = $standalone;
        $moduleIndex = 0;
        foreach ($modules as $label => $module) {
            if (!$module['children']) continue;
            $parentId = 910000 + $moduleIndex++;
            $children = [];
            foreach ($module['children'] as $childIndex => $child) {
                $child['id'] = $parentId + $childIndex + 1;
                $child['parent_id'] = $parentId;
                $child['display_order'] = $childIndex;
                $children[] = $child;
            }
            $result[] = self::item($parentId, null, [
                'label' => $label,
                'url' => null,
                'icon' => $module['icon'],
                'subitems' => $children,
            ], count($result), $children);
        }
        return $result;
    }

    private static function preferModule(string $candidate, string $current): bool
    {
        if ($candidate === $current) return false;
        if ($candidate === 'Reports & Analytics') return true;
        if ($current === 'Reports & Analytics') return false;
        if ($current === 'Operations' && $candidate !== 'Operations') return true;
        return false;
    }

    private static function moduleForRoute(string $url, string $parentLabel): string
    {
        $route = strtolower(trim($url));
        if ($parentLabel === 'reports'
            || $parentLabel === 'reports & analytics'
            || str_ends_with($parentLabel, ' reports')
            || $route === 'analytics_catalogue'
            || str_contains($route, 'report')
            || str_ends_with($route, '_trends')
            || ReportRegistry::route($route) !== null) {
            return 'Reports & Analytics';
        }
        $routeModules = [
            'academic_reports' => 'Academic & CBC Reports',
            'performance_reports' => 'Academic & CBC Reports',
            'performance_analysis' => 'Academic & CBC Reports',
            'term_reports' => 'Academic & CBC Reports',
            'comparative_reports' => 'Academic & CBC Reports',
            'student_progress_reports' => 'Academic & CBC Reports',
            'generate_class_report' => 'Academic & CBC Reports',
            'generate_subject_report' => 'Academic & CBC Reports',
            'report_cards' => 'Academic & CBC Reports',
            'class_report_cards' => 'Academic & CBC Reports',
            'enrollment_reports' => 'Enrollment & Admissions Reports',
            'enrollment_trends' => 'Enrollment & Admissions Reports',
            'attendance_reports' => 'Attendance Reports',
            'attendance_trends' => 'Attendance Reports',
            'finance_reports' => 'Finance Reports',
            'payment_reports' => 'Finance Reports',
            'inventory_reports' => 'Inventory & Procurement Reports',
            'purchase_reports' => 'Inventory & Procurement Reports',
            'stock_reports' => 'Inventory & Procurement Reports',
            // These are operational catering views unless their source parent
            // is explicitly Reports & Analytics (handled above).
            'food_consumption' => 'Catering & Food',
            'meal_statistics' => 'Catering & Food',
            'discipline_reports' => 'Discipline & Safeguarding Reports',
            'conduct_reports' => 'Discipline & Safeguarding Reports',
            'monthly_discipline_report' => 'Discipline & Safeguarding Reports',
            'weekly_discipline_report' => 'Discipline & Safeguarding Reports',
            'health_reports' => 'Health & Welfare Reports',
            'counseling_reports' => 'Health & Welfare Reports',
            'boarding_reports' => 'Boarding Reports',
            'exeat_reports' => 'Boarding Reports',
            'communications_reports' => 'Communication Reports',
            'admissions_' => 'Admissions',
            'admission_' => 'Admissions',
            'enrollment_reports' => 'Enrollment & Admissions Reports',
            'manage_subjects' => 'Academic',
            'academic_reports' => 'Academic & CBC Reports',
            'finance_reports' => 'Finance Reports',
            'academic_' => 'Academic',
            'my_schemes_of_work' => 'Academic',
            'schemes_of_work' => 'Academic',
            'manage_lesson_plans' => 'Academic',
            'create_lesson_plan' => 'Academic',
            'curriculum_' => 'Academic',
            'cbc_curriculum' => 'Academic',
            'assessment_rubrics' => 'Academic',
            'grading_scales' => 'Academic',
            'manage_timetable' => 'Timetables',
            'timetable' => 'Timetables',
            'teacher_timetables' => 'Timetables',
            'teacher_subject_assignment' => 'Timetables',
            'supervision_roster' => 'Timetables',
            'my_subjects_overview' => 'Teaching & Learning',
            'my_subject_syllabus' => 'Teaching & Learning',
            'my_subject_' => 'Teaching & Learning',
            'my_classes_taught' => 'Teaching & Learning',
            'subject_students_list' => 'Teaching & Learning',
            'students_by_class' => 'Teaching & Learning',
            'student_subject_' => 'Teaching & Learning',
            'student_portfolio' => 'Teaching & Learning',
            'create_subject_cat' => 'Assessment & Exams',
            'my_subject_cats' => 'Assessment & Exams',
            'subject_grade_' => 'Assessment & Exams',
            'subject_grading_' => 'Assessment & Exams',
            'subject_exam_' => 'Assessment & Exams',
            'enter_exam_' => 'Assessment & Exams',
            'subject_results_' => 'Assessment & Exams',
            'create_assessment' => 'Assessment & Exams',
            'enter_marks' => 'Assessment & Exams',
            'my_cats' => 'Assessment & Exams',
            'class_results' => 'Assessment & Exams',
            'competencies_sheet' => 'Assessment & Exams',
            'exam_' => 'Assessment & Exams',
            'grading_status' => 'Assessment & Exams',
            'view_results' => 'Assessment & Exams',
            'results_' => 'Assessment & Exams',
            'report_cards' => 'Assessment & Exams',
            'all_lesson_plans' => 'Academic',
            'lesson_plan_' => 'Academic',
            'lesson_plans_' => 'Academic',
            'formative_assessments' => 'Assessment & Exams',
            'national_exams' => 'Assessment & Exams',
            // Financial ownership must be resolved before broad learner,
            // parent, staff, or transport prefixes. These routes move money,
            // establish charges, or reconcile a financial ledger.
            'manage_fee_structure' => 'Finance',
            'manage_extra_charges' => 'Finance',
            'student_fees' => 'Finance',
            'manage_payments' => 'Finance',
            'payment_' => 'Finance',
            'supplier_payments' => 'Finance',
            'parent_refunds' => 'Finance',
            'student_fund_transfers' => 'Finance',
            'unmatched_payments' => 'Finance',
            'students_with_balance' => 'Finance',
            'manage_uniform_sales' => 'Finance',
            'manage_payrolls' => 'Finance',
            'payroll' => 'Finance',
            'payslips' => 'Finance',
            'statutory_remittances' => 'Finance',
            'salary_advances' => 'Finance',
            'petty_cash' => 'Finance',
            'bank_' => 'Finance',
            'mpesa_' => 'Finance',
            'vendors' => 'Finance',
            'purchase_orders' => 'Finance',
            'transport_fees' => 'Finance',
            'manage_transport_fee_structure' => 'Finance',
            'manage_fee_' => 'Finance',
            'finance_' => 'Finance',
            'budget_' => 'Finance',
            'manage_expenses' => 'Finance',
            'transaction_approvals' => 'Controls & Audit',
            'audit_logs' => 'Controls & Audit',
            'adjustments' => 'Controls & Audit',
            'kcb_disbursement_reconciliation' => 'Controls & Audit',
            // Transport is an operational module in its own right. Only its
            // billing/rate routes above belong to Finance.
            'manage_transport' => 'Transport',
            'my_routes' => 'Transport',
            'student_route_assignment' => 'Transport',
            'transport_' => 'Transport',
            // Other first-class school domains must not collapse into the
            // generic Operations utility bucket.
            'student_health' => 'Health & Welfare',
            'sick_bay' => 'Health & Welfare',
            'discipline_' => 'Discipline',
            'manage_boarding' => 'Boarding',
            'boarding_' => 'Boarding',
            'permissions_exeats' => 'Boarding',
            'pending_exeat_' => 'Boarding',
            'exeat_history' => 'Boarding',
            'end_of_term_travel' => 'Boarding',
            'room_assignments' => 'Boarding',
            'school_events' => 'Events & Calendar',
            'manage_holidays' => 'Events & Calendar',
            'assemblies' => 'Events & Calendar',
            'send_parent_notifications' => 'Families & Communication',
            'my_family' => 'Families & Communication',
            'manage_library' => 'Library',
            'library_' => 'Library',
            'uniform_catalog' => 'Inventory & Store',
            'internal_products_catalog' => 'Inventory & Store',
            'products_catalog_management' => 'Inventory & Store',
            'catalog_orders_management' => 'Inventory & Store',
            'uniform_point_of_sale' => 'Inventory & Store',
            'uniform_stock_intake' => 'Inventory & Store',
            'uniform_unit_tracking' => 'Inventory & Store',
            'uniform_discounts' => 'Inventory & Store',
            'goods_received' => 'Inventory & Store',
            'vendor_invoices' => 'Inventory & Store',
            'manage_inventory' => 'Inventory & Store',
            'manage_classes' => 'Learners',
            'class_streams' => 'Learners',
            'class_capacity' => 'Learners',
            'students_' => 'Learners',
            'student_' => 'Learners',
            'student_counseling' => 'Learners',
            'special_needs' => 'Learners',
            'my_students_' => 'Learners',
            'view_class_lists' => 'Learners',
            'view_student_info' => 'Learners',
            'alumni_' => 'Learners',
            'view_attendance' => 'Attendance',
            'attendance_reports' => 'Attendance Reports',
            'attendance_' => 'Attendance',
            'class_mark_attendance' => 'Attendance',
            'manage_staff' => 'Staff',
            'staff_' => 'Staff',
            'all_teachers' => 'Staff',
            'teacher_' => 'Staff',
            'assign_class_teachers' => 'Staff',
            'assign_subjects_to_teachers' => 'Staff',
            'all_parents' => 'Families & Communication',
            'parent_' => 'Families & Communication',
            'pta_' => 'Families & Communication',
            'communications/' => 'Families & Communication',
            'manage_announcements' => 'Families & Communication',
            'manage_sms' => 'Families & Communication',
            'manage_communications' => 'Families & Communication',
            'schedule_parent_meetings' => 'Families & Communication',
            'performance_analysis' => 'Reports & Analytics',
            'term_reports' => 'Reports & Analytics',
            'comparative_reports' => 'Reports & Analytics',
            'generate_subject_report' => 'Reports & Analytics',
            'subject_class_comparison' => 'Reports & Analytics',
            'teaching_materials' => 'Resources',
            'upload_teaching_resource' => 'Resources',
            'past_papers' => 'Resources',
            'view_teaching_materials' => 'Resources',
            'view_past_papers' => 'Resources',
            'manage_activities' => 'Activities & Clubs',
            'create_activity' => 'Activities & Clubs',
            'clubs_societies' => 'Activities & Clubs',
            'competitions' => 'Activities & Clubs',
            'sports' => 'Activities & Clubs',
            'chapel_' => 'Spiritual Life',
            'chaplaincy_' => 'Spiritual Life',
            'food_' => 'Catering & Food',
            'menu_' => 'Catering & Food',
            'manage_menus' => 'Catering & Food',
            'todays_menu' => 'Catering & Food',
            'weekly_menu' => 'Catering & Food',
            'catering_' => 'Catering & Food',
            'my_mentor' => 'Mentorship & Growth',
            'mentor_' => 'Mentorship & Growth',
            'observation_' => 'Mentorship & Growth',
            'improvement_areas' => 'Mentorship & Growth',
            'competency_checklist' => 'Mentorship & Growth',
            'development_progress' => 'Mentorship & Growth',
            'learning_goals' => 'Mentorship & Growth',
            'reflection_journal' => 'Mentorship & Growth',
            'intern_assigned_' => 'Teaching & Learning',
            'intern_schedule' => 'Timetables',
            'at_risk_students' => 'Health & Welfare',
            'intervention_plans' => 'Health & Welfare',
            'welfare_follow_ups' => 'Health & Welfare',
            'counseling_' => 'Health & Welfare',
            'medical_alerts' => 'Health & Welfare',
            'vaccination_records' => 'Health & Welfare',
            'behavior_logs' => 'Discipline',
            'conduct_grades' => 'Discipline',
            'rewards_recognition' => 'Discipline',
            'qr-scanner' => 'Quick Tools',
            'manage_terms' => 'Academic',
            'year_calendar' => 'Events & Calendar',
            'fee_structure_approvals' => 'Finance',
            'manage_articles' => 'Web Management',
            'manage_public_' => 'Web Management',
            'manage_school_gallery' => 'Web Management',
            'manage_job_' => 'Web Management',
            'manage_inquiries' => 'Web Management',
            'manage_page_content' => 'Web Management',
            'manage_website' => 'Web Management',
            'detailed_payslip' => 'My HR',
            'my_attendance' => 'My HR',
        ];
        foreach ($routeModules as $prefix => $module) {
            if ($route === $prefix || strpos($route, $prefix) === 0) return $module;
        }

        $parentModules = [
            'admissions' => 'Admissions', 'academic' => 'Academic',
            'timetable' => 'Timetables', 'teaching' => 'Teaching & Learning',
            'lesson plans' => 'Academic', 'assessments' => 'Assessment & Exams',
            'examinations' => 'Assessment & Exams', 'students' => 'Learners',
            'attendance' => 'Attendance', 'staff' => 'Staff', 'my hr' => 'My HR',
            'fees' => 'Finance', 'finance' => 'Finance', 'finance review' => 'Finance',
            'payroll' => 'Finance', 'payments & vendors' => 'Finance',
            'transport' => 'Transport', 'boarding' => 'Boarding',
            'events & calendar' => 'Events & Calendar', 'discipline' => 'Discipline',
            'health records' => 'Health & Welfare', 'library' => 'Library',
            'inventory & store' => 'Inventory & Store',
            'parents' => 'Families & Communication',
            'communications' => 'Families & Communication', 'reports' => 'Reports & Analytics',
            'resources' => 'Resources',
        ];
        if (isset($parentModules[$parentLabel])) return $parentModules[$parentLabel];

        // Parent names vary by role (for example "Lesson Plan Review", "My
        // Students", and "Student Conduct"). Keyword classification retains
        // that useful context instead of discarding unknown parents into the
        // generic Operations group.
        $parentKeywords = [
            'report' => 'Reports & Analytics',
            'admission' => 'Admissions',
            'lesson' => 'Academic',
            'curriculum' => 'Academic',
            'academic' => 'Academic',
            'timetable' => 'Timetables',
            'schedule' => 'Timetables',
            'assessment' => 'Assessment & Exams',
            'exam' => 'Assessment & Exams',
            'grading' => 'Assessment & Exams',
            'student conduct' => 'Discipline',
            'discipline' => 'Discipline',
            'counsel' => 'Health & Welfare',
            'welfare' => 'Health & Welfare',
            'health' => 'Health & Welfare',
            'medical' => 'Health & Welfare',
            'my student' => 'Learners',
            'my class' => 'Learners',
            'student' => 'Learners',
            'learner' => 'Learners',
            'teacher management' => 'Staff',
            'staff' => 'Staff',
            'my teaching' => 'Teaching & Learning',
            'my assignment' => 'Teaching & Learning',
            'teaching' => 'Teaching & Learning',
            'fee' => 'Finance',
            'payment' => 'Finance',
            'finance' => 'Finance',
            'expenditure' => 'Finance',
            'control' => 'Controls & Audit',
            'audit' => 'Controls & Audit',
            'transport' => 'Transport',
            'exeat' => 'Boarding',
            'boarder' => 'Boarding',
            'boarding' => 'Boarding',
            'parent' => 'Families & Communication',
            'communication' => 'Families & Communication',
            'library' => 'Library',
            'uniform' => 'Inventory & Store',
            'inventory' => 'Inventory & Store',
            'supplier' => 'Inventory & Store',
            'vendor' => 'Inventory & Store',
            'food' => 'Catering & Food',
            'menu' => 'Catering & Food',
            'kitchen' => 'Catering & Food',
            'catering' => 'Catering & Food',
            'activit' => 'Activities & Clubs',
            'club' => 'Activities & Clubs',
            'sport' => 'Activities & Clubs',
            'mentor' => 'Mentorship & Growth',
            'observation' => 'Mentorship & Growth',
            'development' => 'Mentorship & Growth',
            'chapel' => 'Spiritual Life',
            'resource' => 'Resources',
            'website' => 'Web Management',
        ];
        foreach ($parentKeywords as $keyword => $module) {
            if (str_contains($parentLabel, $keyword)) return $module;
        }
        return 'Operations';
    }
}
