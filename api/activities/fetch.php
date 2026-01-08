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
 *   ❌ Used mysqli ($conn) instead of PDO (Database singleton)
 *   ❌ No authentication - public access to all activities
 *   ❌ No pagination - returns ALL records (performance issue)
 *   ❌ No filtering capabilities
 *   ❌ No error handling
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * The activities module ALREADY EXISTS with proper architecture!
 * 
 * 1. BUSINESS LOGIC → api/modules/activities/ActivitiesAPI.php
 *    - listActivities($params) → Paginated, filterable list
 *    - getActivity($id) → Single activity details
 *    - Plus: categories, participants, resources, schedules managers
 * 
 * 2. HTTP HANDLING → api/controllers/ActivitiesController.php
 *    - getActivities() → GET /api/?route=activities
 *    - 1000+ lines of comprehensive CRUD handling
 * 
 * 3. FRONTEND → js/api.js (window.API.activities)
 *    - window.API.activities.list({ page, limit, category, status })
 *    - window.API.activities.get(id)
 *    - window.API.activities.create(data)
 *    - window.API.activities.update(id, data)
 *    - window.API.activities.delete(id)
 * 
 * CORRECT USAGE IN FRONTEND JS:
 * -----------------------------
 * 
 *   // ❌ OLD WAY (wrong)
 *   fetch('api/activities/fetch.php')
 *     .then(r => r.json())
 *     .then(activities => { ... });
 *   
 *   // ✅ NEW WAY (correct)
 *   const response = await window.API.activities.list({
 *     page: 1,
 *     limit: 20,
 *     category: 'sports'
 *   });
 *   if (response.success) {
 *     const activities = response.data;
 *   }
 * 
 * ============================================================================
 */

header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use GET /api/?route=activities instead.',
    'new_endpoint' => '/api/?route=activities',
    'frontend_usage' => 'window.API.activities.list(params)'
]);
