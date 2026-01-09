<?php
/**
 * Manage Admissions Page - Router
 * Routes to role-specific templates based on user's role category
 * 
 * 7-Stage Workflow:
 * 1. Application Submission
 * 2. Document Upload & Verification  
 * 3. Interview Scheduling (skipped for ECD/PP1/PP2/Grade1/Grade7)
 * 4. Interview Assessment
 * 5. Placement Offer
 * 6. Fee Payment
 * 7. Enrollment Completion
 * 
 * Role-based templates:
 * - admin: Full workflow access (Director, Headteacher, System Admin)
 * - manager: Department-specific access (Registrar, Deputy, Accountant)
 * - operator: View incoming students (Teachers)
 * - viewer: Application status check (Parents)
 */

// Include permissions helper
require_once __DIR__ . '/../config/permissions.php';

// Get user's role category
session_start();
$userRole = $_SESSION['role'] ?? 'guest';
$roleCategory = getRoleCategory($userRole);

// Include role-specific template
$templatePath = __DIR__ . "/admissions/{$roleCategory}_admissions.php";

if (file_exists($templatePath)) {
    include $templatePath;
} else {
    // Fallback to viewer template
    include __DIR__ . '/admissions/viewer_admissions.php';
}