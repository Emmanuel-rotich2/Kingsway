<?php
/**
 * All Staff Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * Role-based templates:
 * - admin: Full access (Director, Headteacher, System Admin)
 * - manager: Department view (Deputy Heads, HODs)
 * - operator: Directory view (Teachers)
 * - viewer: Key contacts only (Students, Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Default template (will be overridden by JavaScript)
$templatePath = __DIR__ . '/staff/manager_staff.php'; // Default fallback

// Include the template (JavaScript will replace content based on role)
include $templatePath;
exit;

?>