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
 *   ❌ No input validation or sanitization
 *   ❌ No audit logging
 *   ❌ Mixed business logic with HTTP handling
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * 1. BUSINESS LOGIC → api/modules/counseling/CounselingAPI.php
 *    - create($data)  → Creates new counseling session
 *    - update($id, $data) → Updates existing session
 *    - Includes validation, error handling, audit logging
 * 
 * 2. HTTP HANDLING → api/controllers/CounselingController.php
 *    - postSession()  → Handles POST /api/?route=counseling/session
 *    - putSession()   → Handles PUT /api/?route=counseling/session/{id}
 *    - Handles authentication, request parsing, response formatting
 * 
 * 3. FRONTEND → js/api.js (window.API.counseling)
 *    - window.API.counseling.create(data)
 *    - window.API.counseling.update(id, data)
 *    - window.API.counseling.saveSession(data) → auto-detects create/update
 * 
 * ARCHITECTURE PATTERN TO FOLLOW:
 * --------------------------------
 * 
 *   Frontend JS           API Layer              Business Logic
 *   ──────────────────────────────────────────────────────────────
 *   js/api.js        →    api/controllers/    →  api/modules/
 *   window.API.*          *Controller.php        *API.php + managers
 *   
 *   js/pages/*.js         Uses BaseController    Uses BaseAPI
 *   Uses window.API       for auth, routing      for DB, logging
 * 
 * HOW TO ADD NEW FEATURES:
 * ------------------------
 * 1. Add business logic method in api/modules/{module}/{Module}API.php
 * 2. Add route handler in api/controllers/{Module}Controller.php
 * 3. Add endpoint to js/api.js under window.API.{module}
 * 4. Call from page JS: window.API.{module}.{method}(params)
 * 
 * QUESTIONS? See:
 * - .github/copilot-instructions.md for full architecture docs
 * - api/modules/counseling/CounselingAPI.php for implementation example
 * 
 * ============================================================================
 */

// Redirect to proper endpoint for backwards compatibility
header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use POST /api/?route=counseling/session instead.',
    'documentation' => 'See .github/copilot-instructions.md for architecture guide'
]);
