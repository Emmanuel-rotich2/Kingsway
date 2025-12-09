<?php
// generate_subpages.php - Auto-create sub-page directories and files based on dashboards.php config

$dashboardConfig = include __DIR__ . '/../api/includes/dashboards.php';
$subpagesBase = __DIR__ . '/../pages/subpages/';

// Map permission suffixes to sub-page actions
$actionMap = [
    'view' => 'view.php',
    'edit' => 'edit.php',
    'approve' => 'approve.php',
    'create' => 'create.php',
    'delete' => 'delete.php',
];

foreach ($dashboardConfig as $role => $roleData) {
    if (!isset($roleData['menu_items']))
        continue;
    foreach ($roleData['menu_items'] as $item) {
        $mainUrl = $item['url'];
        $dir = $subpagesBase . $mainUrl;
        if (!is_dir($dir))
            mkdir($dir, 0777, true);
        if (!isset($item['permissions']))
            continue;
        foreach ($item['permissions'] as $perm) {
            // Extract action from permission string (e.g., students_view)
            if (preg_match('/_([a-z]+)$/', $perm, $matches)) {
                $action = $matches[1];
                if (isset($actionMap[$action])) {
                    $filename = $dir . '/' . $actionMap[$action];
                    if (!file_exists($filename)) {
                        $pageLabel = ucfirst($action) . ' ' . str_replace('_', ' ', ucfirst($mainUrl));
                        $permCheck = $perm;
                        $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="../../home.php">Dashboard</a></li><li class="breadcrumb-item"><a href="../../' . $mainUrl . '.php">' . $mainUrl . '</a></li><li class="breadcrumb-item active" aria-current="page">' . $action . '</li></ol></nav>';
                        $content = '<?php\nsession_start();\nrequire_once "../../config/config.php";\nrequire_once "../../config/permissions.php";\nif (!isset($_SESSION["user"]) || !has_permission($_SESSION["user"], "' . $permCheck . '")) {\n    echo \'<div class="alert alert-danger">Access denied: insufficient permissions.</div>\';\n    exit;\n}\n?>\n<h2>' . $pageLabel . '</h2>\n' . $breadcrumb . '\n<!-- ' . $pageLabel . ' content goes here -->\n';
                        file_put_contents($filename, $content);
                    }
                }
            }
        }
    }
}
echo "Sub-page directories and files generated successfully.\n";
