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
 *   ❌ No authentication - anyone can create/modify activities
 *   ❌ No input validation or sanitization
 *   ❌ No audit trail (who created/modified what)
 *   ❌ Mixed database drivers (mysqli vs PDO used elsewhere)
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * The activities module ALREADY EXISTS with proper architecture!
 * 
 * 1. BUSINESS LOGIC → api/modules/activities/ActivitiesManager.php
 *    - createActivity($data, $userId) → With validation & logging
 *    - updateActivity($id, $data, $userId) → With permission checks
 * 
 * 2. HTTP HANDLING → api/controllers/ActivitiesController.php
 *    - postActivities() → POST /api/?route=activities
 *    - putActivities()  → PUT /api/?route=activities/{id}
 * 
 * 3. FRONTEND → js/api.js (window.API.activities)
 *    - window.API.activities.create(data)
 *    - window.API.activities.update(id, data)
 * 
 * CORRECT USAGE IN FRONTEND JS:
 * -----------------------------
 * 
 *   // ❌ OLD WAY (wrong)
 *   fetch('api/activities/save.php', {
 *     method: 'POST',
 *     body: formData
 *   });
 *   
 *   // ✅ NEW WAY (correct)
 *   // For CREATE:
 *   const response = await window.API.activities.create({
 *     name: 'Football Match',
 *     category: 'sports',
 *     activity_date: '2026-01-15',
 *     participants: 22,
 *     status: 'scheduled',
 *     description: 'Inter-house competition'
 *   });
 *   
 *   // For UPDATE:
 *   const response = await window.API.activities.update(activityId, {
 *     status: 'completed'
 *   });
 * 
 * ============================================================================
 */

header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use POST/PUT /api/?route=activities instead.',
    'new_endpoints' => [
        'create' => 'POST /api/?route=activities',
        'update' => 'PUT /api/?route=activities/{id}'
    ],
    'frontend_usage' => [
        'create' => 'window.API.activities.create(data)',
        'update' => 'window.API.activities.update(id, data)'
    ]
]);
