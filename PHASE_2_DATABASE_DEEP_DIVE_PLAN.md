# PHASE 2: DATABASE DEEP DIVE & SYNCHRONIZATION PLAN

**Date**: 2026-03-29
**Status**: Design & Planning Phase
**Objective**: Create detailed database synchronization strategy

---

## 1. TARGET MODEL SUMMARY (From Blueprints)

### 1.1 Permission Structure: `module_action_component`

**Current** (Broken):
```
academic_view
finance_view
students_view
```

**Target** (Complete):
```
students_view
students_create
students_edit
students_delete
students_promote
students_discipline_manage
students_fees_view
students_fees_adjust
academic_view
academic_manage
academic_results_view
academic_results_publish
academic_lesson_plans_edit
academic_assessments_create
finance_view
finance_create
finance_approve
finance_export
...
```

### 1.2 Role-Module Assignments (From Blueprint)

**Director** should own:
- Finance (view, create, approve, export)
- Reporting  (view, export)
- Students (view, promote)
- Admission (all actions)
- Academics (manage)
- Scheduling (view, publish)
- Transport (manage, view)
- Inventory (view, adjust)
- Communications (all)
- HR/Staff (manage, create, edit, delete)
- Payroll (view, approve)
- Audit (view)
- **Total expected permissions**: 60-100+ (currently only 25)

**Headteacher** should own:
- Academics (full oversight: manage, view, results publish, assessments create)
- Students (view, promote)
- Admission (review, approve)
- Attendance (mark, view)
- Discipline (manage)
- Reporting (view)
- Communications (all)
- **Total expected permissions**: 40-60 (currently only 24)

**Accountant** should own:
- Finance (view, create, export, approve for payments)
- Students - Fees component (view fees, record payments)
- Communications (view for notifications)
- **Total expected permissions**: 20-30 (currently 18) - relatively correct

**Class Teacher** should own:
- Academics (view, update within their classes)
- Attendance (mark, edit)
- Assessments (create, enter)
- Discipline (notes only)
- Communications (all)
- **Total expected permissions**: 15-25 (currently 10)

### 1.3 Sidebar Structure

**Director** should see (per blueprint):
```
├─ Director Dashboard
├─ Finance
│  ├─ Manage Finance
│  ├─ Finance Approvals
│  ├─ Manage Payrolls
│  └─ Financial Reports
├─ Students
│  ├─ Manage Students
│  ├─ Student Admissions
│  └─ Enrollment Reports
├─ Academics
│  ├─ Manage Academics
│  ├─ Manage Timetable
│  └─ Academic Reports
├─ Staff
│  ├─ Manage Staff
│  ├─ Staff Attendance
│  └─ Performance Reports
├─ Communications
│  ├─ Manage Communications
│  ├─ Manage SMS
│  └─ Announcements
├─ Transport
│  └─ Manage Transport
├─ Inventory
│  └─ Manage Inventory
├─ Audit & Logs
│  └─ Activity Dashboard
└─ System (if allowed)
   └─ Account Status
```

**Currently Director sees** (only):
```
├─ Director Dashboard
```

**Difference**: 1 item vs ~20-30 items expected

---

## 2. PHASE 2 TASKS - DATABASE AUDIT & DESIGN

### 2.1 Complete Database Audit

**Tables to audit** (in order):

1. **permissions**
   - Current count: ?
   - Check: How many are "view" only vs full action tier
   - Check: Are they classified by module?
   - Check: Any orphaned permissions?

2. **role_permissions**
   - Current: Director has ~25 entries
   - Expected: Director should have 60-100+ entries
   - Check: Which modules are covered?
   - Check: Which modules are missing?

3. **routes**
   - Current count: ?
   - Check: How many routes exist?
   - Check: All have route_permissions entries?
   - Check: All have sidebar_menu_items entries?

4. **route_permissions**
   - Current: Likely sparse
   - Expected: Every route should have at least one permission guard
   - Check: How many routes lack permission guards?

5. **role_routes**
   - Current: Nearly empty
   - Expected: Should reflect all routes accessible by each role
   - Check: Correlation with role_sidebar_menus

6. **sidebar_menu_items**
   - Current: 567+ items exist in DB
   - Expected: Properly hierarchical with parent/child structure
   - Check: How many have null route_id?
   - Check: Menu type distribution (sidebar vs dropdown)

7. **role_sidebar_menus**
   - Current: 8,551 assignments exist
   - Expected: Each assignment should align with role permissions
   - Check: Are all 567 items assigned to Director?
   - Check: Are assignments being filtered by authorization?

8. **dashboards**
   - Current: Dashboards exist and assigned correctly
   - Status: GOOD

9. **workflow_definitions**
   - Current: Partially defined
   - Expected: All processes should have workflow definitions
   - Check: What workflows exist?
   - Check: What workflows are missing? (Admissions, Discipline, Fees, Payroll, etc.)

10. **workflow_stages**
    - Current: Partial
    - Expected: Each workflow should have 3-5 stages with permission guards
    - Check: Do stages have permission guards?
    - Check: Do stages have responsible role_ids?

11. **workflow_instances & tracking**
    - Current: Minimal usage
    - Expected: Track every workflow transition
    - Status: LOW PRIORITY for Phase 2

### 2.2 Create Synchronization Design Document

For each role, document:
- [ ] **Modules owned** (from blueprint)
- [ ] **Actions needed** (from permission catalog)
- [ ] **Routes/pages to access** (from RBAC_REDESIGN_PLAN.md)
- [ ] **Sidebar items needed** (count and names)
- [ ] **Permissions needed** (module_action_component format)

**Example: Director**
```
Modules:
- Finance
- Reporting
- Students
- Academics
- Scheduling
- Transport
- Inventory
- Communications
- HR/Staff
- Audit

Actions:
- finance (view, create, approve, export)
- reports (view, export)
- students (view, promote)
- admission (all)
- academic (manage)
... etc

Routes to provide:
- manage_finance
- finance_approvals
- manage_payrolls
- manage_staff
- staff_attendance
- manage_students
- manage_students_admissions
- manage_accounts
- manage_academics
- manage_timetable
- manage_transport
- manage_inventory
- enrollment_reports
- performance_reports
... (~35 routes expected)

Sidebar items needed:
- Finance (3-4 items)
- Students (3-4 items)
- Academics (3-4 items)
- Staff (3-4 items)
- Communications (3-4 items)
- Transport (2-3 items)
- Inventory (2-3 items)
- Audit (1-2 items)
... (~25-30 items total)

Permissions needed:
- finance_view
- finance_create
- finance_approve
- finance_export
- reports_view
- reports_export
- students_view
- students_promote
- admission_view
- admission_approve
- academic_manage
- academic_results_publish
... (~60-80 permissions total)
```

---

## 3. MIGRATION & IMPLEMENTATION STRATEGY

### 3.1 Phase Structure

**Phase 2A**: Database Audit (THIS SESSION)
- [ ] Query each RBAC table
- [ ] Record current vs expected state
- [ ] Identify exact gaps

**Phase 2B**: Design Migration Scripts (THIS SESSION)
- [ ] Create backup scripts
- [ ] Design permission insertion logic
- [ ] Design role_routes population
- [ ] Design role_sidebar_menus assignments

**Phase 3**: Execute Migrations (NEXT SESSION)
- [ ] Backup all tables
- [ ] Execute population scripts
- [ ] Validate with audit queries

**Phase 4**: Re-test & Verify (NEXT SESSION)
- [ ] Test all 31 users again
- [ ] Verify sidebar items increased
- [ ] Verify permissions increased
- [ ] Verify workflows protected

### 3.2 Safe Execution Plan

**Before any changes**:
1. Create timestamped backups
   ```sql
   CREATE TABLE permissions_backup_20260329 AS SELECT * FROM permissions;
   CREATE TABLE role_permissions_backup_20260329 AS SELECT * FROM role_permissions;
   CREATE TABLE role_routes_backup_20260329 AS SELECT * FROM role_routes;
   ... etc
   ```

2. Document current state
   ```sql
   SELECT 'Before Migration', COUNT(*) as permission_count FROM permissions;
   SELECT 'Before Migration', COUNT(*) as role_perm_count FROM role_permissions;
   ... etc
   ```

3. Execute migration scripts in order
   - Script 1: Audit & backup
   - Script 2: Permission normalization (IF needed)
   - Script 3: role_permissions population
   - Script 4: role_routes population
   - Script 5: role_sidebar_menus alignment
   - Script 6: route_permissions population

4. Validate after each script
   ```sql
   SELECT COUNT(*) FROM role_permissions WHERE role_id = 3;  -- Should increase
   SELECT COUNT(*) FROM role_routes WHERE role_id = 3;      -- Should increase
   ```

5. Rollback procedure
   ```sql
   TRUNCATE permissions;
   INSERT INTO permissions SELECT * FROM permissions_backup_20260329;
   TRUNCATE role_permissions;
   INSERT INTO role_permissions SELECT * FROM role_permissions_backup_20260329;
   ... etc
   ```

---

## 4. WHAT WILL CHANGE (Preview)

### 4.1 Database Tables Modified

| Table | Current| Action | Expected After |
|-------|--------|--------|-----------------|
| permissions | ~4,473 | Verify/classify by module | ~4,473 (with module tags) |
| role_permissions | SPARSE | POPULATE | 3,000-5,000+ entries |
| route_permissions | SPARSE | POPULATE | 200+ entries (1 per route) |
| role_routes | SPARSE | POPULATE | 3,000-5,000+ entries |
| role_sidebar_menus | 8,551 | ALIGN | 8,551 (with proper filtering) |
| workflow_definitions | PARTIAL | EXPAND | 20-25 workflows |
| workflow_stages | PARTIAL | COMPLETE | 60-80 stages across workflows |

### 4.2 User-Facing Changes After Sync

**Director before**:
- Sidebar: 1 item
- Permissions: 25
- Pages accessible: 2-3
- Actions available: 5-10

**Director after**:
- Sidebar: 25-30 items
- Permissions: 60-80+
- Pages accessible: 30-35
- Actions available: 50+

---

## 5. CRITICAL DESIGN DECISIONS

### 5.1 Permission Model

**Decision**: Use `module_action_component` format
- Ensures consistency
- Enables audit by module
- Supports fine-grained controls
- Aligns with blueprint

**Implementation**:
- Every permission gets module tag (15-16 modules)
- Every permission gets action tier (view, create, edit, delete, approve, publish, export, manage, etc.)
- Optional component tag for UI precision

### 5.2 Route Guards

**Decision**: Every route must have at least one permission

**Implementation**:
```sql
INSERT INTO route_permissions (route_id, permission_id)
SELECT r.id, p.id FROM routes r
JOIN permissions p ON p.code LIKE CONCAT(r.module, '_%')
WHERE r.module = p.module LIMIT NEEDED_PERMISSIONS
```

### 5.3 Authorization Filter

**Decision**: Keep MenuBuilderService filter STRICT (as required)

**Implementation**:
- Only return sidebar items user is authorized for
- Authorization check: Does user have permission for this route?
- Does role have entry in role_routes for this route?
- Fix: Populate role_routes completely (not bypass filter)

### 5.4 Workflow Permissions

**Decision**: Each workflow stage should be guarded by permission

**Implementation**:
```sql
UPDATE workflow_stages
SET required_permission = CONCAT(MODULE, '_', ACTION)
WHERE workflow_id IN (SELECT id FROM workflow_definitions)
```

---

## 6. AUDIT QUERIES TO RUN (Phase 2 execution)

```sql
-- Count permissions by module
SELECT module, COUNT(*) FROM permissions GROUP BY module ORDER BY count DESC;

-- Count role_permissions by role
SELECT r.name, COUNT(rp.id) FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
WHERE r.id > 0
GROUP BY r.id, r.name
ORDER BY r.id;

-- Count role_routes by role
SELECT r.name, COUNT(rr.id) FROM roles r
LEFT JOIN role_routes rr ON r.id = rr.role_id
WHERE r.id > 0
GROUP BY r.id, r.name
ORDER BY r.id;

-- Routes without route_permissions
SELECT COUNT(*) FROM routes r
WHERE NOT EXISTS (SELECT 1 FROM route_permissions rp WHERE rp.route_id = r.id);

-- Sidebar items without routes
SELECT COUNT(*) FROM sidebar_menu_items WHERE route_id IS NULL AND menu_type != 'dropdown';

-- role_sidebar_menus assignments by role
SELECT r.name, COUNT(rsm.id) FROM roles r
LEFT JOIN role_sidebar_menus rsm ON r.id = rsm.role_id
WHERE r.id > 0
GROUP BY r.id, r.name
ORDER BY r.id;

-- Workflows without stages
SELECT COUNT(*) FROM workflow_definitions wd
WHERE NOT EXISTS (SELECT 1 FROM workflow_stages ws WHERE ws.workflow_id = wd.id);

-- Workflow stages without permission guards
SELECT COUNT(*) FROM workflow_stages WHERE required_permission IS NULL;

-- Workflow stages without responsible roles
SELECT COUNT(*) FROM workflow_stages WHERE responsible_role_ids IS NULL OR responsible_role_ids = '';
```

---

## 7. SUCCESS CRITERIA FOR PHASE 2 COMPLETION

- [ ] Database audit complete (all queries run, current state documented)
- [ ] Discrepancies identified (exact counts vs expected)
- [ ] Migration scripts designed (not yet executed)
- [ ] Synchronization strategy documented (step-by-step)
- [ ] Rollback procedures documented
- [ ] Each role's required permissions documented
- [ ] Safety procedures in place
- [ ] Ready for Phase 3 execution

---

## 8. DELIVERABLES

### 8.1 Phase 2 Outputs

1. **Phase 2 Database Audit Report**
   - Current state of all RBAC tables
   - Exact discrepancies by role and module
   - Missing entries quantified

2. **Synchronization Design Document**
   - For each role: modules, actions, routes, sidebar items, permissions
   - Migration script specifications
   - Validation queries

3. **Migration Scripts Ready to Execute**
   - SQL backup script
   - SQL audit script
   - SQL population scripts (one per table/operation)
   - SQL validation script
   - SQL rollback script

4. **Re-test Plan**
   - How to verify sidebars increased after sync
   - How to verify permissions increased
   - Expected counts for each role (Director: 60-80+ permissions, 25-30 sidebar items)

5. **Deployment Guide**
   - Step-by-step execution
   - Validation checks after each step
   - Troubleshooting guide

---

## NEXT: Execute Phase 2 Database Audit

Ready to proceed to deep database queries and generate the synchronization strategy?

The system is clean, we have full JSON responses for all 31 users, we have the blueprint documents, and we know exactly what needs to be fixed.

**Next action**: Run comprehensive audit queries against all RBAC tables to document exact current vs target state.
