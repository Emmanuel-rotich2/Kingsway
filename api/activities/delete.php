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
 *   ❌ No authentication - anyone can delete activities!
 *   ❌ No authorization - no check if user has permission
 *   ❌ No soft delete - hard deletes lose audit history
 *   ❌ No confirmation or validation of the ID
 * 
 * WHERE THE CODE NOW LIVES:
 * -------------------------
 * 
 * The activities module ALREADY EXISTS with proper architecture!
 * 
 * 1. BUSINESS LOGIC → api/modules/activities/ActivitiesManager.php
 *    - deleteActivity($id, $userId)
 *    - Includes permission checks and audit logging
 * 
 * 2. HTTP HANDLING → api/controllers/ActivitiesController.php
 *    - deleteActivities() → DELETE /api/?route=activities/{id}
 * 
 * 3. FRONTEND → js/api.js (window.API.activities)
 *    - window.API.activities.delete(id)
 * 
 * CORRECT USAGE IN FRONTEND JS:
 * -----------------------------
 * 
 *   // ❌ OLD WAY (wrong)
 *   fetch('api/activities/delete.php', {
 *     method: 'POST',
 *     body: new FormData().append('id', activityId)
 *   });
 *   
 *   // ✅ NEW WAY (correct)
 *   if (confirm('Are you sure you want to delete this activity?')) {
 *     const response = await window.API.activities.delete(activityId);
 *     if (response.success) {
 *       showToast('Activity deleted successfully');
 *       refreshActivityList();
 *     }
 *   }
 * 
 * SECURITY NOTE:
 * --------------
 * The new architecture automatically:
 * - Validates JWT token before any action
 * - Checks user permissions (RBAC)
 * - Logs who deleted what and when
 * - Returns proper HTTP status codes
 * 
 * ============================================================================
 */

header('HTTP/1.1 410 Gone');
header('Content-Type: application/json');
echo json_encode([
    'status' => 'error',
    'message' => 'This endpoint is deprecated. Use DELETE /api/?route=activities/{id} instead.',
    'new_endpoint' => 'DELETE /api/?route=activities/{id}',
    'frontend_usage' => 'window.API.activities.delete(id)'
]);
