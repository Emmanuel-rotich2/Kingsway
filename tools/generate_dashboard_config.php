<?php
/**
 * Generate dashboards.php config from actual database permissions
 * This creates menu items based on real role-permission mappings
 */

$host = 'localhost';
$dbname = 'KingsWayAcademy';
$user = 'root';
$pass = 'admin123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Map entity names to menu labels and icons
    $entityMap = [
        'students' => ['label' => 'Students', 'icon' => 'bi-people', 'route' => 'manage_students'],
        'students_all' => ['label' => 'Students', 'icon' => 'bi-people', 'route' => 'manage_students'],
        'academic_classes' => ['label' => 'Classes', 'icon' => 'bi-journal', 'route' => 'manage_classes'],
        'academic_subjects' => ['label' => 'Subjects', 'icon' => 'bi-book', 'route' => 'manage_subjects'],
        'academic_results' => ['label' => 'Results', 'icon' => 'bi-bar-chart', 'route' => 'view_results'],
        'academic_assessments' => ['label' => 'Assessments', 'icon' => 'bi-clipboard-check', 'route' => 'manage_assessments'],
        'academic_lesson_plans' => ['label' => 'Lesson Plans', 'icon' => 'bi-file-text', 'route' => 'manage_lesson_plans'],
        'academic_timetable' => ['label' => 'Timetable', 'icon' => 'bi-calendar3', 'route' => 'manage_timetable'],
        'academics_all' => ['label' => 'Academics', 'icon' => 'bi-mortarboard', 'route' => 'manage_academics'],
        'attendance' => ['label' => 'Attendance', 'icon' => 'bi-pencil-square', 'route' => 'mark_attendance'],
        'attendance_all' => ['label' => 'Attendance', 'icon' => 'bi-pencil-square', 'route' => 'mark_attendance'],
        'staff' => ['label' => 'Staff', 'icon' => 'bi-person-workspace', 'route' => 'manage_staff'],
        'staff_all' => ['label' => 'Staff', 'icon' => 'bi-person-workspace', 'route' => 'manage_staff'],
        'finance' => ['label' => 'Finance', 'icon' => 'bi-wallet2', 'route' => 'manage_finance'],
        'fees' => ['label' => 'Fee Management', 'icon' => 'bi-credit-card', 'route' => 'manage_fees'],
        'payroll' => ['label' => 'Payroll', 'icon' => 'bi-cash-stack', 'route' => 'manage_payrolls'],
        'inventory' => ['label' => 'Inventory', 'icon' => 'bi-box-seam', 'route' => 'manage_inventory'],
        'library' => ['label' => 'Library', 'icon' => 'bi-book-half', 'route' => 'manage_library'],
        'activities' => ['label' => 'Activities', 'icon' => 'bi-trophy', 'route' => 'manage_activities'],
        'transport' => ['label' => 'Transport', 'icon' => 'bi-bus-front', 'route' => 'manage_transport'],
        'transport_all' => ['label' => 'Transport', 'icon' => 'bi-bus-front', 'route' => 'manage_transport'],
        'communications' => ['label' => 'Communications', 'icon' => 'bi-chat-dots', 'route' => 'manage_communications'],
        'communications_all' => ['label' => 'Communications', 'icon' => 'bi-chat-dots', 'route' => 'manage_communications'],
        'reports' => ['label' => 'Reports', 'icon' => 'bi-file-earmark-pdf', 'route' => 'reports'],
        'users' => ['label' => 'Users', 'icon' => 'bi-person-gear', 'route' => 'manage_users'],
        'users_all' => ['label' => 'Users & Access', 'icon' => 'bi-person-gear', 'route' => 'manage_users'],
        'roles' => ['label' => 'Roles & Permissions', 'icon' => 'bi-shield-lock', 'route' => 'manage_roles'],
        'system' => ['label' => 'System Settings', 'icon' => 'bi-gear', 'route' => 'system_settings'],
        'system_all' => ['label' => 'System Settings', 'icon' => 'bi-gear', 'route' => 'system_settings'],
        'workflow' => ['label' => 'Workflows', 'icon' => 'bi-diagram-3', 'route' => 'manage_workflows'],
        'workflow_all' => ['label' => 'Workflows', 'icon' => 'bi-diagram-3', 'route' => 'manage_workflows'],
        'admissions' => ['label' => 'Admissions', 'icon' => 'bi-person-plus', 'route' => 'manage_admissions'],
        'catering' => ['label' => 'Catering', 'icon' => 'bi-egg-fried', 'route' => 'manage_catering'],
        'boarding' => ['label' => 'Boarding', 'icon' => 'bi-house', 'route' => 'manage_boarding'],
        'boarding_all' => ['label' => 'Boarding', 'icon' => 'bi-house', 'route' => 'manage_boarding'],
        'wellness' => ['label' => 'Wellness', 'icon' => 'bi-heart-pulse', 'route' => 'manage_wellness'],
        'security' => ['label' => 'Security', 'icon' => 'bi-shield-check', 'route' => 'manage_security'],
        'maintenance' => ['label' => 'Maintenance', 'icon' => 'bi-tools', 'route' => 'manage_maintenance'],
    ];

    // Get all roles
    $stmt = $pdo->query("SELECT id, name FROM roles WHERE id >= 2 ORDER BY id");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dashboards = [];

    foreach ($roles as $role) {
        $roleKey = strtolower(str_replace(['/', ' ', '-'], '_', $role['name']));

        // Get top-level entities this role has access to
        $entitiesStmt = $pdo->prepare("
            SELECT DISTINCT 
                SUBSTRING_INDEX(p.entity, '_', 1) as top_entity,
                p.entity,
                p.action
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = :role_id
            AND (p.action IN ('view', 'create', 'edit', 'delete', 'approve', 'permissions') 
                 OR p.entity LIKE '%_all')
            ORDER BY p.entity, p.action
        ");
        $entitiesStmt->execute(['role_id' => $role['id']]);
        $permissions = $entitiesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Group permissions by entity
        $entityGroups = [];
        foreach ($permissions as $perm) {
            $entity = $perm['entity'];
            if (!isset($entityGroups[$entity])) {
                $entityGroups[$entity] = [];
            }
            $entityGroups[$entity][] = $perm['entity'] . '_' . $perm['action'];
        }

        // Build menu items
        $menuItems = [];
        foreach ($entityGroups as $entity => $permCodes) {
            // Try to find a mapping
            $menuInfo = $entityMap[$entity] ?? null;

            if (!$menuInfo) {
                // Try base entity (remove _view, _edit suffixes)
                $baseEntity = preg_replace('/(_(view|edit|approve))$/', '', $entity);
                $menuInfo = $entityMap[$baseEntity] ?? null;
            }

            if ($menuInfo && count($permCodes) > 0) {
                $menuItems[] = [
                    'label' => $menuInfo['label'],
                    'route' => $menuInfo['route'],
                    'icon' => $menuInfo['icon'],
                    'permissions' => $permCodes,
                ];
            }
        }

        // Limit to top 10 most relevant menu items
        $menuItems = array_slice($menuItems, 0, 10);

        $dashboards[$roleKey] = [
            'label' => $role['name'] . ' Dashboard',
            'permissions' => ['dashboard_' . $roleKey . '_access'],
            'menu_items' => $menuItems,
        ];
    }

    // Generate PHP config file
    $output = "<?php\n";
    $output .= "// dashboards.php - Auto-generated dashboard config from database\n";
    $output .= "// Generated: " . date('Y-m-d H:i:s') . "\n";
    $output .= "// DO NOT EDIT MANUALLY - regenerate using tools/generate_dashboard_config.php\n\n";
    $output .= "return [\n";

    foreach ($dashboards as $key => $config) {
        $output .= "    // Role: {$config['label']}\n";
        $output .= "    '{$key}' => [\n";
        $output .= "        'label' => " . var_export($config['label'], true) . ",\n";
        $output .= "        'permissions' => " . var_export($config['permissions'], true) . ",\n";
        $output .= "        'menu_items' => [\n";

        foreach ($config['menu_items'] as $item) {
            $output .= "            [\n";
            $output .= "                'label' => " . var_export($item['label'], true) . ",\n";
            $output .= "                'route' => " . var_export($item['route'], true) . ",\n";
            $output .= "                'icon' => " . var_export($item['icon'], true) . ",\n";
            $output .= "                'permissions' => " . var_export($item['permissions'], true) . ",\n";
            $output .= "            ],\n";
        }

        $output .= "        ],\n";
        $output .= "    ],\n\n";
    }

    $output .= "];\n";

    // Save to file
    file_put_contents(__DIR__ . '/../api/includes/dashboards.php', $output);

    echo "✅ Dashboard config generated successfully!\n";
    echo "📝 File: api/includes/dashboards.php\n";
    echo "📊 Total roles: " . count($dashboards) . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
