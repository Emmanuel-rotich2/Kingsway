<?php
/**
 * Legacy Students Directory.
 *
 * Kept for old links. It no longer loads role templates dynamically; the shared
 * context API chooses the safest allowed student view for the current user.
 */
$studentContext = '';
$studentPageTitle = 'Students Directory';
$studentPageDescription = 'Safe role-scoped student directory for legacy all_students links.';
$studentReadOnly = true;
include __DIR__ . '/students/student_context_view.php';
