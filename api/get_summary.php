<?php
/**
 * ============================================================================
 * ⚠️  DEPRECATED - DO NOT USE THIS FILE
 * ============================================================================
 * 
 * This file has been migrated to the proper architecture.
 * 
 * WHAT CHANGED:
 * -------------
 * Your original code here bypassed our architecture pattern by:
 *   ❌ Direct database access without the Database singleton
 *   ❌ No authentication/authorization checks
 *   ❌ No error handling (try/catch)
 *   ❌ No audit logging for tracking who accessed data
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * 1. BUSINESS LOGIC → api/modules/counseling/CounselingAPI.php
 *    - getSummary() method
 *    - Returns: { total, scheduled, completed, active }
 * 
 * 2. HTTP HANDLING → api/controllers/CounselingController.php
 *    - getSummary() → Handles GET /api/?route=counseling/summary
 * 
 * 3. FRONTEND → js/api.js (window.API.counseling)
 *    - window.API.counseling.getSummary()
 * 
 * CORRECT USAGE IN FRONTEND JS:
 * -----------------------------
 * 
 *   // ❌ OLD WAY (wrong)
 *   fetch('api/get_summary.php')
 *     .then(r => r.json())
 *     .then(data => { ... });
 *   
 *   // ✅ NEW WAY (correct)
 *   const response = await window.API.counseling.getSummary();
 *   if (response.success) {
 *     const { total, scheduled, completed, active } = response.data;
 *   }
 * 
 * WHY THIS MATTERS:
 * -----------------
 * - Centralized authentication (JWT tokens handled automatically)
 * - Consistent error handling across all endpoints
 * - Audit trail for compliance requirements
 * - Easier testing and maintenance
 * - Single source of truth for API calls
 * 
 * ============================================================================
 */

header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use GET /api/?route=counseling/summary instead.',
    'new_endpoint' => '/api/?route=counseling/summary',
    'frontend_usage' => 'window.API.counseling.getSummary()'
]);
