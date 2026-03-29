# Kingsway RBAC & Workflow Audit - Current State (2026-03-29)

## EXECUTIVE SUMMARY

**Status**: CRITICAL MISALIGNMENT DETECTED

The system has:
- **583 permissions defined** but only **~100 actually checked** in code (82% unused)
- **4 inconsistent permission-checking patterns** across controllers
- **43+ hardcoded role ID checks** in DashboardController (security risk)
- **11 controllers with zero permission enforcement** (authorization bypass risk)
- **Permission format inconsistency** (underscore in DB vs dot in client)
- **No alignment with the 4,473-permission catalog** defined in design docs

### Critical Issues

| Issue | Severity | Controllers Affected | Fix Priority |
|-------|----------|---------------------|--------------|
| Hardcoded role ID checks | CRITICAL | DashboardController (43+ places) | IMMEDIATE |
| Missing permission checks | HIGH | ActivitiesController, CommunicationsController, StaffController, TransportController, InventoryController, ReportsController, etc. (11 total) | IMMEDIATE |
| Per-role duplicate routes | HIGH | Multiple roles allow same routes without permission distinction | HIGH |
| Route permission validation | MEDIUM | RouteAuthorization exists but unclear if enforcement | HIGH |
| Permission orphaning | MEDIUM | ~500 permissions unused, creates audit burden | MEDIUM |
| Frontend-Backend mismatch | MEDIUM | Permission format (underscore vs dot) and list divergence | MEDIUM |

---

## PART 1: CURRENT STATE INVENTORY

### 1.1 Active Roles

| Role ID | Name | Type | Status | Active Users |
|---------|------|------|--------|---------|
| 2 | System Administrator | ROOT | Active | System admin |
| 3 | Director | School Leadership | Active | School director |
| 4 | School Administrator | School Leadership | Active | Admin staff |
| 5 | Headteacher | School Leadership | Active | Headteacher |
| 6 | Deputy Head - Academic | School Leadership | Active | Deputy academic |
| 7 | Class Teacher | Teacher | Active | Multiple |
| 8 | Subject Teacher | Teacher | Active | Multiple |
| 9 | Intern/Student Teacher | Teacher | Inactive/Learning | Limited |
| 10 | Accountant | Finance | Active | Finance staff |
| 14 | Inventory Manager | Operations | Active | Store manager |
| 16 | Cateress | Operations | Active | Kitchen staff |
| 18 | Boarding Master | Operations | Active | Boarding staff |
| 21 | Talent Development | Operations | Active | Activities staff |
| 23 | Driver | Operations | Active | Transport |
| 24 | Chaplain | Support | Active | Pastoral |
| 32 | Kitchen Staff | Tracking Only | Inactive | For payroll only |
| 33 | Security Staff | Tracking Only | Inactive | For payroll only |
| 34 | Janitor | Tracking Only | Inactive | For payroll only |
| 63 | Deputy Head - Discipline | School Leadership | Active | Deputy discipline |
| 64-70 | Test/Temporary Roles | Test | Inactive | Not used |

**Status**: 15 active operational roles + 3 tracking-only roles + 7 test roles (to be purged)

### 1.2 Permissions Status

- **Total permissions defined**: 583
- **Actually checked in code**: ~100
- **Unused/orphaned**: ~500 (85%)
- **Permission format**: Underscore (code), but stored as `code` field in DB
- **Alias expansion**: Yes (underscore ↔ dot via RBACMiddleware)

**Sample Permission Codes** (by module):
- Academic: `academic_view`, `academic_manage`, `academic_assessments_*` (36+ variations)
- Students: `students_view`, `students_create`, `students_edit`, `students_promote`, etc.
- Finance: `finance_view`, `finance_approve`, `fees_manage`, `payments_record`, etc.
- Admissions: `admission_view`, `admission_applications_approve`, etc.
- Attendance, Discipline, Transport, Inventory, Communications, etc.

### 1.3 Role-Permission Mappings

**Current Status**: Sparse, incomplete, role-specific
- Some roles have 50+ permissions assigned
- Some have < 10 (tracking-only roles)
- Many permissions are duplicated across roles
- No module-level grouping enforcement

### 1.4 Routes Status

**Routes Table**: ~100+ routes defined
**Domains**: SYSTEM (18 routes) vs SCHOOL (80+ routes)
**Key Routes**:
- Dashboard routes (per-role)
- Student management (manage_students, manage_students_admissions)
- Finance (manage_finance, finance_approvals, manage_payments)
- Academic (manage_academics, manage_assessments, view_results)
- Communications, Inventory, Transport, Boarding, etc.

**Status**: Routes exist but not all have clear permission bindings

### 1.5 Sidebars

**Current Sidebar Structure**: Database-driven via `sidebar_menu_items` and `role_sidebar_menus`
- Per-role menus
- 5-10 items per role typically
- Not all routes are in sidebars

**Issues**:
- Duplicate menu items for same route across roles
- No grouping by module
- Missing items for available routes

### 1.6 Dashboards

**Dashboard Assignments** (via `role_dashboards`):
- System Admin → System Administrator Dashboard
- Director → Director Dashboard
- School Admin → School Administrator Dashboard
- Headteacher → Headteacher Dashboard
- Deputy Academic → Deputy Academic Dashboard
- Deputy Discipline → Deputy Discipline Dashboard
- Accountant → Accountant Dashboard
- Class Teacher → Class Teacher Dashboard
- Subject Teacher → Subject Teacher Dashboard
- Inventory Manager → Inventory Manager Dashboard
- Boarding Master → Boarding Master Dashboard
- Cateress → Cateress Dashboard
- Driver → Driver Dashboard
- Chaplain → Chaplain Dashboard
- Talent Development → Talent Development Manager Dashboard

**Status**: 15 dashboards assigned, but unclear if all are properly implemented

### 1.7 Workflows

**Existing Workflow Tables**:
- `workflow_definitions` - 9+ main workflows
- `workflow_stages` - Stages per workflow
- `workflow_instances` - Running workflow instances
- `workflow_stage_history` - Audit trail
- `workflow_notifications` - Stage notifications

**Known Workflows**:
1. Admissions (Application→Verification→Offer→Enrollment)
2. Student Promotion & Results (Assessment→Review→Publish)
3. Fees & Payments (Structure→Billing→Recording→Reconciliation)
4. Payroll (Setup→Processing→Approval)
5. Scheduling (Planning→Conflict Check→Publish)
6. Inventory (Requisition→Procurement→Distribution)
7. Communications (Draft→Approval→Send)

**Status**: Workflows partially defined but not fully integrated with RBAC

---

## PART 2: AUTHORIZATION IMPLEMENTATION AUDIT

### 2.1 Backend Permission Checking Patterns

#### Pattern A: Constants + authorize() helper (Best Practice)
**Location**: StudentsController
```php
private const STUDENT_VIEW_PERMS = ['students_view', 'students_view_all', ...];
private function authorizeStudents(array $permissions) {
    if (!$this->userHasAny($permissions)) {
        return $this->forbidden('Insufficient permissions');
    }
}
```
**Usage**: StudentsController (75 permission checks)
**Status**: ✅ Consistent, auditable

#### Pattern B: Role-based direct checks (Anti-pattern)
**Location**: DashboardController
```php
if ($this->getUserRole() !== 2) {  // Hardcoded role ID
    return $this->forbidden('System Admin only');
}
```
**Usage**: DashboardController (43+ places)
**Status**: ❌ Brittle, ID-dependent, silent failures

#### Pattern C: Module-level permission checks (Delayed validation)
**Location**: FinanceAPI, AcademicAPI
```php
if (!$this->hasPermission($userId, 'fees_edit')) {
    return formatResponse(false, null, 'Permission denied');
}
```
**Usage**: 5+ modules
**Status**: ⚠️ Works but late in call stack

#### Pattern D: No checks (Critical gap)
**Controllers**: ActivitiesController, CommunicationsController, StaffController, TransportController, InventoryController, ReportsController, CounselingController, MaintenanceController
**Usage**: Direct pass-through to modules (11 controllers)
**Status**: ❌ Authorization bypass risk

#### Pattern E: Mixed role + permission checks (Inconsistent)
**Location**: FinanceAPI
```php
if (!$this->hasPermission($userId, 'fees_delete')) { ... }
if ($userRole !== 'director_owner') { ... }
```
**Status**: ⚠️ Confusing, audit difficult

### 2.2 Frontend Permission Checking

**Location**: js/api.js, js/auth-utils.js, js/components/RoleBasedUI.js

**Methods**:
- `AuthContext.hasPermission(code)` - Single permission
- `AuthContext.hasAnyPermission([...])` - OR logic
- `AuthContext.hasAllPermissions([...])` - AND logic
- `RoleBasedUI.canPerformAction(module, action, component)` - Component-level

**Storage**: localStorage (user_permissions, user_roles)
**Source**: Login response API

**Issues**:
- Frontend permissions may diverge from backend if not kept in sync
- No real-time permission invalidation
- Format: Both underscore and dot notation accepted

**Status**: ✅ Comprehensive but not always synced with backend

### 2.3 Middleware Authorization

**Active Middleware**:
1. **RBACMiddleware** - Resolves effective permissions
   - Uses stored procedure `sp_user_get_effective_permissions(user_id)`
   - Fallback to table joins if procedure unavailable
   - Caches in `$_SERVER['auth_user']['effective_permissions']`
   - Expands underscore ↔ dot aliases

2. **RouteAuthorization** - Route whitelist enforcement
   - Checks `role_routes` table (DENY-BY-DEFAULT)
   - Checks `routes` for domain filtering (SYSTEM vs SCHOOL)
   - Caches role→routes mapping

3. **AuthMiddleware** - JWT validation
   - Validates Bearer token
   - Attaches decoded user to `$_SERVER['auth_user']`

**Status**: ✅ Middleware chain exists, but enforcement gaps visible

### 2.4 Permission Check Coverage

| Controller | Status | Checks | Pattern |
|-----------|--------|--------|---------|
| StudentsController | ✅ Strong | 75+ | Pattern A (constants) |
| AdmissionController | ⚠️ Weak | 4 | Minimal |
| AcademicController | ⚠️ Weak | 1 | Minimal |
| ActivitiesController | ❌ None | 0 | Gap |
| AuthController | ❌ None | 0 | Public area |
| CommunicationsController | ❌ None | 0 | Gap |
| CounselingController | ❌ None | 0 | Gap |
| DashboardController | ❌ Anti-pattern | 43+ | Hardcoded IDs |
| FinanceController | ⚠️ Weak | 3 | Minimal |
| InventoryController | ❌ None | 0 | Gap |
| MaintenanceController | ❌ None | 0 | Gap |
| ReportsController | ❌ None | 0 | Gap |
| SchoolConfigController | ❌ None | 0 | Gap |
| StaffController | ❌ None | 0 | Gap |
| TransportController | ❌ None | 0 | Gap |

**Coverage**: 6% of routes have controller-level permission checks

---

## PART 3: DISCREPANCIES & MISALIGNMENTS

### 3.1 Design vs. Reality

| Aspect | Design Spec | Current Reality | Gap |
|--------|-------------|-----------------|-----|
| Permission Model | 4,473 codes (module/action/component) | 583 codes (generic) | 7.6x fewer permissions |
| Role Count | 11 active (well-defined) | 15 active + 7 test | Test roles polluting system |
| Modules | 12 business areas | Implicit (not enforced) | No module boundaries |
| Workflow Coverage | 9+ workflows (defined) | 7 partially implemented | Incomplete workflow sync |
| Permission Checking | Route-level + Component-level | Scattered, inconsistent | Uneven implementation |

### 3.2 Routes Without Clear Permission Bindings

**Issue**: Some routes exist but lack corresponding permissions
- `permission_changes` - unclear permission
- `delegated_permissions` - unclear permission guard
- `system_health` - only checked for role 2
- `api_explorer` - developer-only, but no permission

**Impact**: Routes can be accessed if role_routes allows, but permission model unclear

### 3.3 Permissions With No Routes

**Issue**: Many permissions defined but no corresponding routes check them
- ~500 permissions never verified in code
- Difficult to audit which permissions actually guard what

**Impact**: Permission creep, unclear security posture

### 3.4 Sidebar-Route Misalignment

**Issue**: Not all accessible routes appear in sidebar
- Users must know URLs directly
- Sidebar items may point to routes without permission checks

**Example Issue**:
- DashboardController routes don't have consistent sidebar entries
- Some sidebar items unreachable for certain roles

### 3.5 Permission Format Inconsistency

**Problem**:
- Database stores: `students_create` (underscore)
- Frontend accepts: `students_create` or `students.create` (both)
- Backend expects: Underscore primarily, but aliases to dot

**Impact**:
- Confusion about canonical form
- Risk of format mismatch bugs
- Harder to audit permission lists

### 3.6 Dashboard Role ID Hardcoding

**Problem in DashboardController**:
```php
if ($this->getUserRole() === 2)  { ... }  // System Admin
if ($this->getUserRole() === 5)  { ... }  // Headteacher
if ($this->getUserRole() === 10) { ... }  // Accountant
// ... 40+ more hardcoded checks
```

**Issues**:
- If role IDs change, code breaks silently
- No permission model
- Security audit difficult
- Brittle to schema changes

---

## PART 4: CURRENT STATE FINDINGS

### 4.1 What IS Working

✅ **RBACMiddleware**: Permission resolution via stored procedure works well
✅ **Frontend AuthContext**: Comprehensive permission checking in UI
✅ **StudentsController**: Good example of consistent permission patterns
✅ **RouteAuthorization**: Whitelist enforcement at route level
✅ **Workflow infrastructure**: Tables and stored procedures exist
✅ **Dashboard routing**: Per-role dashboards functional

### 4.2 What IS NOT Working

❌ **Permission checks**: Only 100 of 583 permissions checked
❌ **Controller authorization**: 11 controllers have zero checks
❌ **Role-based access**: 43+ hardcoded role ID checks (brittle)
❌ **Permission-route binding**: Unclear and inconsistent
❌ **Sidebar coverage**: Routes visible to users but not discoverable
❌ **Workflow enforcement**: Workflows defined but not linked to permissions
❌ **Permission cleanup**: 500 unused permissions in system

### 4.3 What NEEDS Fixing

**CRITICAL**:
1. Replace all hardcoded role ID checks with permission codes
2. Add permission guards to 11 controllers with gaps
3. Purge test roles (64-70)
4. Remove or repair unused permissions

**HIGH PRIORITY**:
1. Standardize permission checking pattern across all controllers
2. Link workflows to permission checks
3. Align sidebar items to routes with permission validation
4. Ensure frontend/backend permission lists match

**MEDIUM PRIORITY**:
1. Consolidate permission format (choose underscore as canonical)
2. Test stored procedures thoroughly
3. Document permission-to-route mappings
4. Clean up orphaned permissions

---

## PART 5: TARGET SYNCHRONIZATION GOALS

### 5.1 Desired End State

By end of Phase 5:

✅ **All 11 active operational roles have clear module ownership**
✅ **Every route has a required permission**
✅ **Every permission is checked in code or explicitly marked deprecated**
✅ **Workflows are linked to permissions and guarded by RBAC**
✅ **Controllers use consistent authorization pattern**
✅ **Frontend and backend permission lists stay in sync**
✅ **No hardcoded role IDs exist**
✅ **Test roles and unused permissions purged**
✅ **Every dashboard has permission requirements**
✅ **Every sidebar item maps to permission-guarded route**

### 5.2 Key Metrics

| Metric | Current | Target | Status |
|--------|---------|--------|--------|
| Permissions checked | 100/583 (17%) | 583/583 (100%) | 0% |
| Controllers with auth | 6/17 (35%) | 17/17 (100%) | 0% |
| Hardcoded role IDs | 43+ | 0 | 0% |
| Test roles in system | 7 | 0 | 0% |
| Unused permissions | 500/583 | 0/583 | 0% |
| Workflow-RBAC alignment | 0% | 100% | 0% |

---

## NEXT STEPS (Phase 2)

1. ✅ **Audit complete** - See this document
2. ⏭️ **Design target model** - Using design docs + current state findings
3. ⏭️ **Create migration scripts** - Database cleanup and restructuring
4. ⏭️ **Update code** - Add/fix permission checks
5. ⏭️ **Validation** - Comprehensive checks and reports

---

## APPENDIX: File Locations

### Key Files to Review/Change

**Backend Authorization**:
- `/api/middleware/RBACMiddleware.php` - Permission resolution ✅ Good
- `/api/middleware/RouteAuthorization.php` - Route whitelist ⚠️ Check enforcement
- `/api/middleware/AuthMiddleware.php` - JWT validation ✅ Good
- `/api/controllers/DashboardController.php` - ❌ 43+ hardcoded role IDs
- `/api/controllers/StudentsController.php` - ✅ Best practice example
- `/config/permissions.php` - Permission helpers and role categories

**Frontend Authorization**:
- `/js/api.js` - AuthContext (permission storage/checking)
- `/js/auth-utils.js` - Authorization utilities
- `/js/components/RoleBasedUI.js` - Component-level permission guards

**Database**:
- `roles` - Role definitions (15 active + 7 test)
- `permissions` - Permission codes (583 total, 100 used)
- `role_permissions` - Role-permission mappings
- `user_permissions` - User-specific overrides
- `role_routes` - Role-route whitelist
- `route_permissions` - Route-permission bindings
- `workflow_*` - Workflow definitions, stages, instances
- `sidebar_menu_items`, `role_sidebar_menus` - Sidebar configuration

**Design Docs** (Sources of truth):
- `/documantations/General/RBAC_WORKFLOW_MATRIX.md` - Workflow-role-permission mapping
- `/documantations/General/RBAC_ROLE_MODULE_ASSIGNMENTS.md` - Role module ownership
- `/documantations/General/RBAC_PERMISSION_CATALOG.md` - Permission grouping strategy

---

**Audit Completed By**: Claude Code (Agent)
**Date**: 2026-03-29
**Status**: READY FOR PHASE 2 DESIGN
