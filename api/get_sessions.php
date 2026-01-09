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
 * Your original code here had several issues:
 *   ❌ SQL injection risk (limit/offset not parameterized)
 *   ❌ No authentication - anyone could access all session data
 *   ❌ No pagination validation (negative pages, huge limits)
 *   ❌ Direct database access bypassing Database singleton
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * 1. BUSINESS LOGIC → api/modules/counseling/CounselingAPI.php
 *    - list($params) method
 *    - Supports: search, status, category, date, page, limit
 *    - Returns paginated results with proper validation
 * 
 * 2. HTTP HANDLING → api/controllers/CounselingController.php
 *    - getSession() → Handles GET /api/?route=counseling/session
 *    - Also handles single session: GET /api/?route=counseling/session/{id}
 * 
 * 3. FRONTEND → js/api.js (window.API.counseling)
 *    - window.API.counseling.list({ search, status, page, limit })
 *    - window.API.counseling.get(id)
 * 
 * CORRECT USAGE IN FRONTEND JS:
 * -----------------------------
 * 
 *   // ❌ OLD WAY (wrong)
 *   fetch(`api/get_sessions.php?search=${search}&page=${page}`)
 *     .then(r => r.json())
 *     .then(data => { ... });
 *   
 *   // ✅ NEW WAY (correct)
 *   const response = await window.API.counseling.list({
 *     search: 'John',
 *     status: 'scheduled',
 *     page: 1,
 *     limit: 10
 *   });
 *   if (response.success) {
 *     const { sessions, pagination } = response.data;
 *   }
 * 
 * RESPONSE FORMAT:
 * ----------------
 * {
 *   "success": true,
 *   "data": {
 *     "sessions": [...],
 *     "pagination": { "total": 50, "page": 1, "limit": 10, "pages": 5 }
 *   }
 * }
 * 
 * ============================================================================
 */

header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use GET /api/?route=counseling/session instead.',
    'new_endpoint' => '/api/?route=counseling/session',
    'frontend_usage' => 'window.API.counseling.list({ search, status, page, limit })'
]);
