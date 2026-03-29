# Phase 4: Code Synchronization Plan

## Summary of Backend Changes Required

All backend authorization code must be updated to use the standardized permission checking pattern and eliminate hardcoded role ID checks.

---

## CRITICAL CHANGE 1: DashboardController (43 hardcoded role ID checks)

**File**: `api/controllers/DashboardController.php`

**Issue**: 43+ methods checking role IDs directly with `===` comparisons:
```php
// CURRENT (ANTI-PATTERN):
if ($this->getUserRole() !== 2) {
    return $this->forbidden('System Admin only');
}
```

**Required Changes**: Replace ALL hardcoded role ID checks with permission checks

### Example Fix #1: System Admin Dashboard
```php
// BEFORE:
public function getSystemAdministratorDashboard()
{
    if ($this->getUserRole() !== 2) {
        return $this->forbidden('System Administrator access required');
    }
    // ...
}

// AFTER:
public function getSystemAdministratorDashboard()
{
    if ($auth = $this->authorize('system_settings_manage')) {
        return $auth;
    }
    // ...
}
```

### Example Fix #2: Director Dashboard
```php
// BEFORE:
public function getDirectorDashboard()
{
    if ($this->getUserRole() !== 3) {
        return $this->forbidden('Director access required');
    }
    // ...
}

// AFTER:
public function getDirectorDashboard()
{
    if ($auth = $this->authorize('finance_approve')) {
        return $auth;
    }
    // ...
}
```

### Example Fix #3: Role-Specific Dashboard (multiple roles allowed)
```php
// BEFORE:
public function getHeadteacherDashboard()
{
    $userRole = $this->getUserRole();
    if ($userRole !== 5 && $userRole !== 6) {
        return $this->forbidden('Headteacher/Deputy access required');
    }
    // ...
}

// AFTER:
public function getHeadteacherDashboard()
{
    if ($auth = $this->authorize(['academic_manage', 'academic_view'])) {
        return $auth;
    }
    // ...
}
```

### Mapping Table: DashboardController Methods

| Method | Role ID(s) | Permission Guard | Context |
|--------|-----------|------------------|---------|
| getSystemAdministratorDashboard | 2 | `system_settings_manage` | System Admin only |
| getDirectorDashboard | 3 | `finance_approve` | Director financial approvals |
| getSchoolAdministratorDashboard | 4 | `users_manage` | School admin user management |
| getHeadteacherDashboard | 5, 6 | `academic_manage` | Academics leadership |
| getDeputyAcademicDashboard | 6 | `academic_manage` | Deputy academic |
| getDeputyDisciplineDashboard | 63 | `discipline_cases_manage` | Discipline management |
| getClassTeacherDashboard | 7 | `academic_view` | Class teacher view |
| getSubjectTeacherDashboard | 8 | `academic_view` | Subject teacher view |
| getAccountantDashboard | 10 | `finance_view` | Finance operations |
| getInventoryManagerDashboard | 14 | `inventory_view` | Inventory management |
| getCaterersDashboard | 16 | `catering_food_view` | Catering operations |
| getBoardingMasterDashboard | 18 | `boarding_view` | Boarding management |
| getDriverDashboard | 23 | `transport_view` | Transport/Driver |
| getTalentDevelopmentDashboard | 21 | `activities_manage` | Activities management |
| getChaplainDashboard | 24 | `counseling_records_view` | Pastoral care |

**Action Required**: Replace all 43+ `if ($this->getUserRole() !== N)` checks with `if ($auth = $this->authorize(...))` pattern

**Estimated Time**: 1-2 hours

**Testing**: Each controller method must return dashboard data after permission check passes

---

## CRITICAL CHANGE 2: Add Permission Guards to Gap Controllers (11 controllers)

**Controllers with ZERO permission checks** (Authorization bypass risk):

1. **ActivitiesController**
   - Add to all public API methods
   - Guard: `activities_manage`

2. **CommunicationsController**
   - Add to create/edit/delete/approve methods
   - Guard: `communications_view` for view, `communications_announcements_create` for create

3. **StaffController**
   - Add to all CRUD methods
   - Guard: `users_manage` or `staff_*` permissions

4. **TransportController**
   - Add to route/vehicle management methods
   - Guard: `transport_manage`, `transport_routes_manage`

5. **InventoryController**
   - Add to all methods
   - Guard: `inventory_view`, `inventory_adjust`, `inventory_reports_export`

6. **ReportsController**
   - Add to all report generation methods
   - Guard: `reports_view`, `reports_export`

7. **CounselingController**
   - Add to record creation/editing
   - Guard: `counseling_records_create`, `counseling_records_view`

8. **MaintenanceController**
   - Add to all maintenance ticket methods
   - Guard: `maintenance_manage`

9. **SchoolConfigController**
   - Add to config update methods
   - Guard: `system_settings_manage`

10. **Any others found without checks**

### Template for Adding Permission Guards

```php
<?php
// In controller class:

private const ACTION_PERMISSIONS = [
    'view' => ['communications_view'],
    'create' => ['communications_announcements_create'],
    'edit' => ['communications_announcements_create'],
    'delete' => ['communications_announcements_create'],
    'approve' => ['communications_outbound_approve'],
];

private function authorize(string $action, string $message = 'Insufficient permissions'): ?Response
{
    $permissions = self::ACTION_PERMISSIONS[$action] ?? [];
    if (empty($permissions) || !$this->userHasAny($permissions)) {
        return $this->forbidden($message);
    }
    return null;
}

// In each API method:
public function create(int $id, array $data, array $segments): Response
{
    if ($auth = $this->authorize('create', 'You cannot create communications items')) {
        return $auth;
    }

    // ... rest of logic
}

public function delete(int $id, array $data, array $segments): Response
{
    if ($auth = $this->authorize('delete', 'You cannot delete communications items')) {
        return $auth;
    }

    // ... rest of logic
}
```

**Action Required**: Add similar permission guards to all 11 gap controllers

**Estimated Time**: 2-3 hours (depends on number of methods per controller)

**Testing**: Attempt API calls as user without permission, should get 403 Forbidden

---

## IMPORTANT CHANGE 3: Standardize Permission Checking Across All Controllers

**Goal**: Eliminate permission check pattern inconsistency

### Current Patterns (To Be Consolidated)

```php
// PATTERN A: Constants + authorize() helper (PREFERRED - StudentsController)
private const STUDENT_VIEW_PERMS = ['students_view', ...];
private function authorizeStudents(array $permissions) {
    if (!$this->userHasAny($permissions)) {
        return $this->forbidden('...');
    }
}

// PATTERN B: Per-method permission checks (ACCEPTABLE - FinanceAPI)
if (!$this->hasPermission($userId, 'fees_edit')) {
    return formatResponse(false, null, 'Permission denied');
}

// PATTERN C: Role-based direct checks (ANTI-PATTERN - DashboardController)
if ($this->getUserRole() !== 2) { ... }  // DELETE THIS

// PATTERN D: No checks (CRITICAL GAP - 11 controllers)
// Just call module directly with no guard

// PATTERN E: Mixed role + permission checks (CONFUSING - FinanceAPI)
// Both role checks AND permission checks
```

### Target Pattern (All Controllers):

```php
class XyzController extends BaseController
{
    // Define permissions per action group
    private const PERMISSIONS = [
        'view' => ['xyz_view'],
        'create' => ['xyz_create'],
        'edit' => ['xyz_edit'],
        'delete' => ['xyz_delete'],
        'approve' => ['xyz_approve'],
    ];

    // Central authorization helper
    protected function authorize(string $action, string $message = 'Insufficient permissions'): ?Response
    {
        if (!isset(self::PERMISSIONS[$action])) {
            return $this->forbidden('Unknown action');
        }

        if (!$this->userHasAny(self::PERMISSIONS[$action])) {
            return $this->forbidden($message);
        }

        return null;
    }

    // Usage in methods
    public function create(int $id, array $data, array $segments)
    {
        if ($auth = $this->authorize('create')) {
            return $auth;
        }
        // ... business logic
    }
}
```

**Action Required**: Review all controllers and ensure they follow this pattern

**Estimated Time**: 1-2 hours for code review/refactoring

---

## MEDIUM CHANGE 4: Frontend Permission Synchronization

**File**: `js/api.js` (AuthContext)

**Issue**: Frontend permission list may diverge from backend

### Current Implementation:
```javascript
const AuthContext = {
    permissions: [],  // From login response
    hasPermission(code) {
        // Accepts both underscore and dot notation
        const normalized = code.replace(/\./g, '_');
        return this.permissions.includes(code) || this.permissions.includes(normalized);
    }
}
```

### Required Changes:

1. **Standardize permission format to underscore only**
   ```javascript
   hasPermission(code) {
       // All permissions must be underscore format
       const normalized = code.replace(/\./g, '_');
       return this.permissions.includes(normalized);
   }
   ```

2. **Validate permission list matches backend on login**
   ```javascript
   // In login response handler:
   // Verify permissions list is complete and in current format
   if (!validatePermissionFormat(response.permissions)) {
       console.warn('Permission format mismatch detected');
   }
   ```

**Action Required**: Update AuthContext to standardize on underscore format

**Estimated Time**: 30 minutes

---

## MEDIUM CHANGE 5: Frontend RoleBasedUI Component Guards

**File**: `js/components/RoleBasedUI.js`

**Current**: Comprehensive data-attribute-based guards exist

**Verify**:
- [ ] All button.permission attributes use underscore format
- [ ] All data-permission attributes match new permission codes
- [ ] No hardcoded role ID checks in frontend
- [ ] Test suite covers permission changes

### Example Check:
```javascript
// In RoleBasedUI.js, verify permissions array includes:
const REQUIRED_PERMISSIONS = [
    'students_view', 'students_create', 'students_edit',
    'admissions_view', 'admissions_create',
    'finance_view', 'finance_approve',
    // ... all new permission codes
];
```

**Action Required**: Verify frontend permission list matches backend exports

**Estimated Time**: 30 minutes (mostly verification)

---

## VERIFICATION CHANGE 6: Add Audit Logging

**Purpose**: Track all permission checks for audit/compliance

**File**: Create `api/services/PermissionAuditService.php`

```php
<?php
namespace App\API\Services;

class PermissionAuditService
{
    /**
     * Log a permission check result
     */
    public static function logCheck(
        int $userId,
        string $permission,
        bool $granted,
        string $controller,
        string $method
    ): void {
        // Log to audit table
        // Log to error log if denied
        // Include timestamp, IP, user agent
    }

    /**
     * Log permission changes
     */
    public static function logChange(
        int $changedBy,
        int $affectedUserId,
        string $type,  // 'grant', 'deny', 'revoke'
        string $permission,
        string $reason
    ): void {
        // Log to permission_audit table
    }
}
```

**Usage in Controllers**:
```php
public function create(int $id, array $data)
{
    $auth = $this->authorize('students_create');
    if ($auth) {
        PermissionAuditService::logCheck($userId, 'students_create', false, 'StudentsController', 'create');
        return $auth;
    }

    PermissionAuditService::logCheck($userId, 'students_create', true, 'StudentsController', 'create');
    // ... proceed with creation
}
```

**Action Required**: Optional but recommended for compliance

**Estimated Time**: 1-2 hours

---

## ACTION CHECKLIST FOR PHASE 4

- [ ] **DashboardController**: Replace all 43 hardcoded role ID checks (PRIMARY)
- [ ] **Gap Controllers**: Add permission guards to 11 controllers (PRIMARY)
- [ ] **Pattern Review**: Ensure all controllers follow standardized pattern
- [ ] **Frontend Sync**: Standardize permission format to underscore
- [ ] **RoleBasedUI**: Verify component guards match backend permissions
- [ ] **Audit Logger**: Implement optional permission audit logging
- [ ] **Integration Tests**: Test user with role → loads correct routes, sees correct sidebars
- [ ] **Smoke Tests**: Test each role's dashboard loads
- [ ] **Permission-Denied Tests**: Test users denied permissions get 403
- [ ] **Workflow Tests**: Test workflows enforce permission checks at each stage

---

## Implementation Timeline

| Task | Est. Time | Priority |
|------|-----------|----------|
| DashboardController refactoring | 1-2 hrs | CRITICAL |
| Gap controller guards | 2-3 hrs | CRITICAL |
| Pattern standardization | 1-2 hrs | HIGH |
| Frontend sync | 0.5 hrs | HIGH |
| Audit logging | 1-2 hrs | MEDIUM |
| Testing & verification | 2-3 hrs | HIGH |
| **Total** | **8-13 hours** | - |

---

## File Changes Summary

### Files to MODIFY:
- `api/controllers/DashboardController.php` (43 role checks → permissions)
- `api/controllers/ActivitiesController.php` (add guards to all methods)
- `api/controllers/CommunicationsController.php` (add guards)
- `api/controllers/StaffController.php` (add guards)
- `api/controllers/TransportController.php` (add guards)
- `api/controllers/InventoryController.php` (add guards)
- `api/controllers/ReportsController.php` (add guards)
- `api/controllers/CounselingController.php` (add guards)
- `api/controllers/MaintenanceController.php` (add guards)
- `api/controllers/SchoolConfigController.php` (add guards)
- `js/api.js` (AuthContext permission format)
- `js/components/RoleBasedUI.js` (verify permission codes)

### Files to CREATE:
- `api/services/PermissionAuditService.php` (optional, for audit logging)

### Files to REFERENCE (no changes):
- `api/middleware/RBACMiddleware.php` (already good)
- `api/middleware/RouteAuthorization.php` (keep as-is)
- `api/middleware/AuthMiddleware.php` (keep as-is)
- `api/controllers/StudentsController.php` (best-practice example)
- Other controllers with good patterns (keep as reference)

---

## Next Steps (after Phase 4)

1. Deploy code changes
2. Run integration tests with all roles
3. Verify dashboard loads for each role
4. Check sidebar shows appropriate items
5. Test permission-denied scenarios
6. Monitor audit logs for any unexpected denials
7. Proceed to Phase 5: Validation & Reports

---

**Prepared By**: Claude Code Agent
**Date**: 2026-03-29
**Status**: READY FOR IMPLEMENTATION
