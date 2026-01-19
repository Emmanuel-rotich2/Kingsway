<?php
// permissions.php - Permission and Role helper functions

/**
 * Role Category Definitions
 * Maps the 30+ system roles into 4 UI categories
 * 
 * admin    → Full access, all features, management controls
 * manager  → Department-level access, reporting, limited admin
 * operator → Task-focused access, data entry, daily operations
 * viewer   → Read-only access, minimal UI
 */
define('ROLE_CATEGORIES', [
    // ADMIN LEVEL - Full system access
    'admin' => [
        'System Administrator',
        'Director',
        'Director/Owner',
        'Headteacher',
        'School Administrator',
    ],
    
    // MANAGER LEVEL - Department/area management
    'manager' => [
        'Deputy Head - Academic',
        'Deputy Head - Discipline',
        'HOD - Talent Development',
        'HOD - Food & Nutrition',
        'Talent Development',
        'Accountant',
        'Inventory Manager',
        'Boarding Master',
    ],
    
    // OPERATOR LEVEL - Daily operations, data entry
    'operator' => [
        'Class Teacher',
        'Subject Teacher',
        'Chaplain',
        'Cateress',
        'Driver',
        'Kitchen Staff',
        'Security Staff',
        'Janitor',
        'Staff',
    ],
    
    // VIEWER LEVEL - Read-only, minimal access
    'viewer' => [
        'Intern/Student Teacher',
        'Student',
        'Parent',
        'Guardian',
    ],
]);

/**
 * Get the UI category for a given role name
 * 
 * @param string $roleName The role name from database
 * @return string One of: 'admin', 'manager', 'operator', 'viewer'
 */
function getRoleCategory($roleName)
{
    foreach (ROLE_CATEGORIES as $category => $roles) {
        if (in_array($roleName, $roles)) {
            return $category;
        }
    }
    
    // Default to viewer for unknown roles (safest)
    return 'viewer';
}

/**
 * Get the data access level for a role category
 * 
 * @param string $category The role category
 * @return string One of: 'full', 'standard', 'limited', 'minimal'
 */
function getDataLevel($category)
{
    $levels = [
        'admin' => 'full',
        'manager' => 'standard',
        'operator' => 'limited',
        'viewer' => 'minimal',
    ];
    
    return $levels[$category] ?? 'minimal';
}

/**
 * Get UI configuration for a role category
 * 
 * @param string $category The role category
 * @return array UI configuration settings
 */
function getUIConfig($category)
{
    $configs = [
        'admin' => [
            'layout' => 'full',
            'sidebar' => 'expanded',
            'showAnalytics' => true,
            'showCharts' => true,
            'gridColumns' => 4,
            'tableColumns' => 'all',
            'showExport' => true,
            'showBulkActions' => true,
            'showFilters' => 'advanced',
            'cardStyle' => 'detailed',
            'animations' => 'full',
        ],
        'manager' => [
            'layout' => 'standard',
            'sidebar' => 'compact',
            'showAnalytics' => true,
            'showCharts' => true,
            'gridColumns' => 3,
            'tableColumns' => 'standard',
            'showExport' => true,
            'showBulkActions' => false,
            'showFilters' => 'standard',
            'cardStyle' => 'standard',
            'animations' => 'moderate',
        ],
        'operator' => [
            'layout' => 'compact',
            'sidebar' => 'mini',
            'showAnalytics' => false,
            'showCharts' => false,
            'gridColumns' => 2,
            'tableColumns' => 'essential',
            'showExport' => false,
            'showBulkActions' => false,
            'showFilters' => 'simple',
            'cardStyle' => 'compact',
            'animations' => 'subtle',
        ],
        'viewer' => [
            'layout' => 'minimal',
            'sidebar' => 'hidden',
            'showAnalytics' => false,
            'showCharts' => false,
            'gridColumns' => 1,
            'tableColumns' => 'minimal',
            'showExport' => false,
            'showBulkActions' => false,
            'showFilters' => 'none',
            'cardStyle' => 'simple',
            'animations' => 'none',
        ],
    ];
    
    return $configs[$category] ?? $configs['viewer'];
}

/**
 * Check if user has a specific permission
 * 
 * @param array $user User object with permissions array
 * @param string $permission Permission code to check
 * @return bool
 */
function has_permission($user, $permission)
{
    if (!isset($user['permissions']) || !is_array($user['permissions'])) {
        return false;
    }
    return in_array($permission, $user['permissions']);
}

/**
 * Check if user can perform an action on an entity
 * 
 * @param array $user User object
 * @param string $entity Entity name (e.g., 'activities', 'students')
 * @param string $action Action name (e.g., 'view', 'create', 'edit', 'delete')
 * @return bool
 */
function can($user, $entity, $action)
{
    $permissionCode = "{$entity}_{$action}";
    return has_permission($user, $permissionCode);
}

/**
 * Get allowed actions for a user on an entity
 * 
 * @param array $user User object
 * @param string $entity Entity name
 * @return array List of allowed actions
 */
function getAllowedActions($user, $entity)
{
    $possibleActions = ['view', 'create', 'edit', 'update', 'delete', 'export', 'import', 'approve'];
    $allowed = [];
    
    foreach ($possibleActions as $action) {
        if (can($user, $entity, $action)) {
            $allowed[] = $action;
        }
    }
    
    return $allowed;
}

/**
 * Get the template path for a page based on user's role category
 * 
 * @param string $basePage Base page name (e.g., 'activities', 'communications')
 * @param string $roleCategory User's role category
 * @return string Template file path
 */
function getTemplatePath($basePage, $roleCategory)
{
    $templateDir = __DIR__ . "/../pages/{$basePage}/";
    $templateFile = "{$roleCategory}_{$basePage}.php";
    
    // Fall back to viewer template if specific template doesn't exist
    if (!file_exists($templateDir . $templateFile)) {
        $templateFile = "viewer_{$basePage}.php";
    }
    
    // Fall back to generic template if viewer doesn't exist either
    if (!file_exists($templateDir . $templateFile)) {
        return __DIR__ . "/../pages/manage_{$basePage}.php";
    }
    
    return $templateDir . $templateFile;
}
