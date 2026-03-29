# PHASE 7: REMEDIATION GUIDE

**Date**: 2026-03-29
**Status**: READY FOR EXECUTION
**Purpose**: Complete database synchronization by remediating orphaned routes, sidebar items, and untagged permissions

---

## EXECUTIVE SUMMARY

Phase 5.5 identified 3 categories of remaining issues:

| Issue | Count | Priority | Fix Type |
|-------|-------|----------|----------|
| Unmapped Routes | 71 | CRITICAL | Manual + Scripted |
| Orphaned Sidebar Items | 122 | HIGH | Manual Decision |
| Untagged Permissions | 590 | MEDIUM | Semi-Automated |

This phase provides methodical step-by-step remediation for each category.

---

## ASSESSMENT STEP (Do This First)

### Run Remediation Assessment

Execute this query to understand the current state:

```bash
mysql -u root -padmin123 KingsWayAcademy < database/migrations/2026_03_29_phase7_remediation_assessment.sql
```

This produces 7 reports showing:
1. All unmapped routes with their modules
2. Orphaned sidebar items and invalid route references
3. Untagged permission patterns
4. Workflows without stage permission bindings
5. Route-role coverage matrix
6. Summary statistics

**Save results**: Document the output for reference during remediation.

---

## REMEDIATION CATEGORY 1: UNMAPPED ROUTES (71 total)

### Understanding Route Mapping Options

Each route needs **at least one** of:

**Option A**: Permission Mapping (via `route_permissions`)
```sql
INSERT INTO route_permissions (route_id, permission_id) VALUES (
    (SELECT id FROM routes WHERE name = 'manage_students'),
    (SELECT id FROM permissions WHERE code = 'students_view')
);
```

**Option B**: Role Mapping (via `role_routes`)
```sql
INSERT INTO role_routes (role_id, route_id) VALUES (
    5,  -- Headteacher
    (SELECT id FROM routes WHERE name = 'manage_students')
);
```

**Option C**: Inactive (if route is unused)
```sql
UPDATE routes SET is_active = 0 WHERE name = 'route_to_disable';
```

### Step 1: Classify Each Route

For each unmapped route identified by the assessment:

```sql
-- Template: Check what a route should do
SELECT
    r.id,
    r.name,
    r.path,
    r.description,
    r.module
FROM routes r
WHERE r.name = 'ROUTE_NAME_HERE'
LIMIT 1;

-- Check what permission typically guards this type of action
SELECT
    code,
    description
FROM permissions
WHERE code LIKE '%ENTITY%'
AND module = 'MODULE_NAME'
ORDER BY code;

-- Check what roles currently access similar routes
SELECT DISTINCT
    ro.name,
    rr.role_id
FROM role_routes rr
JOIN routes r ON r.id = rr.route_id
JOIN roles ro ON ro.id = rr.role_id
WHERE r.module = 'MODULE_NAME'
AND r.name LIKE '%PATTERN%'
LIMIT 5;
```

### Step 2: Create Targeted Mappings

**For System Admin Routes** (module = 'System'):
```sql
-- These should map to system management permissions
INSERT INTO route_permissions (route_id, permission_id)
SELECT r.id, p.id
FROM routes r
CROSS JOIN permissions p
WHERE r.module = 'System'
AND r.name IN ('system_config', 'system_logs', 'system_settings')
AND p.module = 'System'
AND p.code = 'system_manage'
ON DUPLICATE KEY UPDATE route_id = route_id;
```

**For Academic Routes** (module = 'Academics'):
```sql
-- Academic routes should map to class/subject/result permissions
INSERT INTO route_permissions (route_id, permission_id)
SELECT r.id, p.id
FROM routes r
CROSS JOIN permissions p
WHERE r.module = 'Academics'
AND r.name IN ('enter_results', 'view_results', 'mark_attendance')
AND p.module = 'Academics'
AND p.code IN ('academic_results_create', 'academic_results_view', 'academic_attendance_manage')
ON DUPLICATE KEY UPDATE route_id = route_id;
```

**For Finance Routes** (module = 'Finance'):
```sql
-- Finance routes should map to payment/invoice permissions
INSERT INTO route_permissions (route_id, permission_id)
SELECT r.id, p.id
FROM routes r
CROSS JOIN permissions p
WHERE r.module = 'Finance'
AND r.name IN ('manage_payments', 'manage_fees', 'financial_reports')
AND p.module = 'Finance'
AND p.code IN ('finance_payments_manage', 'finance_fees_view', 'finance_reports_view')
ON DUPLICATE KEY UPDATE route_id = route_id;
```

### Step 3: Identify & Handle Special Cases

**Routes with No Clear Permission** (usage unclear):
```sql
-- These should either be disabled or assigned to roles directly
UPDATE routes SET is_active = 0
WHERE name IN (
    'legacy_route',
    'deprecated_endpoint',
    'test_route'
)
AND NOT EXISTS (
    SELECT 1 FROM route_permissions WHERE route_id = routes.id
    UNION
    SELECT 1 FROM role_routes WHERE route_id = routes.id
);
```

**Routes Needing Role-Based Access** (no permission available):
```sql
-- Map directly to roles instead
INSERT INTO role_routes (role_id, route_id)
VALUES
    (5, (SELECT id FROM routes WHERE name = 'headteacher_reports')),  -- Headteacher
    (4, (SELECT id FROM routes WHERE name = 'school_admin_panel')),   -- School Admin
    (3, (SELECT id FROM routes WHERE name = 'director_dashboard'));   -- Director
```

---

## REMEDIATION CATEGORY 2: ORPHANED SIDEBAR ITEMS (122 total)

### Decision Matrix

Each orphaned sidebar item needs one of:

| Situation | Decision | Action |
|-----------|----------|--------|
| Item is still needed by users | ADD ROLE ASSIGNMENTS | Create role_sidebar_menus entries |
| Item is outdated/unused | DEACTIVATE | Set is_active = 0 |
| Item references broken route | FIX OR DELETE | Update route_name or deactivate |
| Item is temporary/test | DELETE | Remove completely |

### Step 1: Audit Orphaned Items

```sql
-- For each orphaned sidebar item, understand its purpose
SELECT
    smi.id,
    smi.label,
    smi.route_name,
    smi.module,
    r.name as valid_route_name,
    r.id as route_id,
    CASE
        WHEN r.id IS NULL THEN 'BROKEN_ROUTE'
        WHEN smi.module = 'System' THEN 'ADMIN_MENU'
        WHEN smi.module = 'Students' THEN 'STUDENT_MENU'
        ELSE 'FUNCTIONAL_MENU'
    END as item_type
FROM sidebar_menu_items smi
LEFT JOIN routes r ON r.name = smi.route_name AND r.is_active = 1
WHERE NOT EXISTS (
    SELECT 1 FROM role_sidebar_menus WHERE sidebar_menu_id = smi.id
)
AND smi.is_active = 1;
```

### Step 2: Fix Broken Route References

```sql
-- Update sidebar items with broken routes
UPDATE sidebar_menu_items smi
SET route_name = (
    SELECT name FROM routes r
    WHERE r.module = smi.module
    AND r.is_active = 1
    LIMIT 1
)
WHERE route_name NOT IN (
    SELECT name FROM routes WHERE is_active = 1
)
AND smi.is_active = 1;
```

### Step 3: Assign to Appropriate Roles

For items that should be visible to users:

```sql
-- Template: Assign sidebar item to roles that need it
INSERT INTO role_sidebar_menus (role_id, sidebar_menu_id, position)
SELECT
    r.id,
    smi.id,
    ROW_NUMBER() OVER (PARTITION BY r.id ORDER BY smi.sort_order)
FROM roles r
CROSS JOIN sidebar_menu_items smi
WHERE smi.label = 'ITEM_LABEL_HERE'
AND r.name IN ('School Accountant', 'Headteacher', 'Deputy Academic')
ON DUPLICATE KEY UPDATE position = VALUES(position);
```

**By Module**:

```sql
-- Sidebar items for System Administration (assign to admin roles)
INSERT INTO role_sidebar_menus (role_id, sidebar_menu_id)
SELECT r.id, smi.id
FROM roles r
CROSS JOIN sidebar_menu_items smi
WHERE smi.module = 'System'
AND r.id IN (2, 3, 4)  -- System Admin, Director, School Admin
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Sidebar items for Finance (assign to finance roles)
INSERT INTO role_sidebar_menus (role_id, sidebar_menu_id)
SELECT r.id, smi.id
FROM roles r
CROSS JOIN sidebar_menu_items smi
WHERE smi.module = 'Finance'
AND r.name IN ('School Accountant', 'School Admin', 'Director')
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Sidebar items for Academics (assign to academic staff)
INSERT INTO role_sidebar_menus (role_id, sidebar_menu_id)
SELECT r.id, smi.id
FROM roles r
CROSS JOIN sidebar_menu_items smi
WHERE smi.module = 'Academics'
AND r.name IN ('Headteacher', 'Deputy Academic', 'Class Teacher', 'Subject Teacher')
ON DUPLICATE KEY UPDATE role_id = role_id;
```

### Step 4: Deactivate Unused Items

```sql
-- Deactivate items that are:
-- 1. Truly unused (no matching route)
-- 2. Administrative/test items
UPDATE sidebar_menu_items
SET is_active = 0
WHERE (
    route_name NOT IN (SELECT name FROM routes WHERE is_active = 1)
    OR label LIKE '%test%'
    OR label LIKE '%temp%'
    OR label LIKE '%draft%'
)
AND NOT EXISTS (
    SELECT 1 FROM role_sidebar_menus WHERE sidebar_menu_id = sidebar_menu_items.id
);
```

---

## REMEDIATION CATEGORY 3: UNTAGGED PERMISSIONS (590 total)

### Pattern-Based Tagging

The assessment identifies permission patterns. Auto-tag based on naming:

```sql
-- Pattern 1: Permissions with entity prefix (e.g., 'student_*')
UPDATE permissions
SET module = 'Students'
WHERE module IS NULL
AND code LIKE 'student%'
AND code NOT LIKE '%system%';

-- Pattern 2: Class/Form permissions → Academics
UPDATE permissions
SET module = 'Academics'
WHERE module IS NULL
AND (code LIKE 'class%' OR code LIKE 'form%' OR code LIKE 'grade%' OR code LIKE 'subject%');

-- Pattern 3: Result/Exam permissions → Academics
UPDATE permissions
SET module = 'Academics'
WHERE module IS NULL
AND (code LIKE 'result%' OR code LIKE 'exam%' OR code LIKE 'mark%' OR code LIKE 'assessment%');

-- Pattern 4: Fee/Payment permissions → Finance
UPDATE permissions
SET module = 'Finance'
WHERE module IS NULL
AND (code LIKE 'fee%' OR code LIKE 'payment%' OR code LIKE 'invoice%' OR code LIKE 'receipt%');

-- Pattern 5: Payroll permissions → Finance
UPDATE permissions
SET module = 'Finance'
WHERE module IS NULL
AND code LIKE 'payroll%';

-- Pattern 6: Attendance permissions → Attendance
UPDATE permissions
SET module = 'Attendance'
WHERE module IS NULL
AND code LIKE 'attendance%';

-- Pattern 7: Transport permissions → Transport
UPDATE permissions
SET module = 'Transport'
WHERE module IS NULL
AND code LIKE 'transport%';

-- Pattern 8: Boarding/Health permissions → Boarding
UPDATE permissions
SET module = 'Boarding'
WHERE module IS NULL
AND (code LIKE 'boarding%' OR code LIKE 'health%' OR code LIKE 'dormitory%');

-- Pattern 9: Discipline permissions → Discipline
UPDATE permissions
SET module = 'Discipline'
WHERE module IS NULL
AND code LIKE 'discipline%;

-- Pattern 10: Report permissions → Reporting
UPDATE permissions
SET module = 'Reporting'
WHERE module IS NULL
AND code LIKE 'report%';

-- Pattern 11: Dashboard/System permissions → System
UPDATE permissions
SET module = 'System'
WHERE module IS NULL
AND (code LIKE 'dashboard%' OR code LIKE 'system%' OR code LIKE 'config%' OR code LIKE 'settings%');

-- Pattern 12: Inventory permissions → Inventory
UPDATE permissions
SET module = 'Inventory'
WHERE module IS NULL
AND (code LIKE 'inventory%' OR code LIKE 'purchase%' OR code LIKE 'supplier%' OR code LIKE 'stock%');

-- Pattern 13: Communication permissions → Communications
UPDATE permissions
SET module = 'Communications'
WHERE module IS NULL
AND (code LIKE 'communication%' OR code LIKE 'message%' OR code LIKE 'notification%' OR code LIKE 'sms%' OR code LIKE 'email%');

-- Pattern 14: Activity permissions → Activities
UPDATE permissions
SET module = 'Activities'
WHERE module IS NULL
AND (code LIKE 'activity%' OR code LIKE 'event%' OR code LIKE 'club%');

-- Pattern 15: Admission permissions → Admissions
UPDATE permissions
SET module = 'Admissions'
WHERE module IS NULL
AND (code LIKE 'admission%' OR code LIKE 'application%' OR code LIKE 'enrolment%');
```

### Manual Review for Remaining Untagged

After pattern-based tagging, manually review remaining:

```sql
-- See what's left
SELECT code, description, COUNT(*) as count
FROM permissions
WHERE module IS NULL
GROUP BY code
ORDER BY code;
```

For each remaining permission, determine correct module and:

```sql
UPDATE permissions
SET module = 'MODULE_NAME'
WHERE code = 'PERMISSION_CODE';
```

---

## WORKFLOW STAGE PERMISSION BINDING

### Step 1: Identify Required Workflow Permissions

For each workflow stage that needs permission binding:

```sql
-- Which stages are ready for permission binding?
SELECT
    ws.id,
    ws.name,
    w.name as workflow,
    ws.order,
    COUNT(wsp.id) as permission_count
FROM workflow_stages ws
JOIN workflow_definitions w ON w.id = ws.workflow_id
LEFT JOIN workflow_stage_permissions wsp ON wsp.workflow_stage_id = ws.id
WHERE ws.is_active = 1
GROUP BY ws.id
ORDER BY w.name, ws.order;
```

### Step 2: Bind Stage Permissions

Example: Admission Pipeline workflow

```sql
-- Stage: Initial Review
-- Required permission: admissions_application_view + admissions_application_assign
-- Responsible roles: School Admin, Admissions Officer

INSERT INTO workflow_stage_permissions (
    workflow_stage_id,
    permission_id,
    role_id,
    is_responsible,
    required_count
)
SELECT
    (SELECT id FROM workflow_stages WHERE name = 'Initial Review' AND workflow_id = (SELECT id FROM workflow_definitions WHERE code = 'admission_pipeline')),
    p.id,
    r.id,
    TRUE,
    1
FROM permissions p
CROSS JOIN roles r
WHERE p.code IN ('admissions_application_view', 'admissions_application_assign')
AND p.module = 'Admissions'
AND r.name IN ('School Admin', 'Admissions Officer')
ON DUPLICATE KEY UPDATE workflow_stage_id = workflow_stage_id;
```

---

## REMEDIATION EXECUTION CHECKLIST

### Phase 7a: Routes (2-3 hours)
- [ ] Run assessment script, save results
- [ ] Classify each of 71 unmapped routes
- [ ] Create targeted mapping SQLs by module
- [ ] Execute route_permissions mappings
- [ ] Execute role_routes assignments for special cases
- [ ] Deactivate truly unused routes
- [ ] Verify all active routes now have mappings

### Phase 7b: Sidebar Items (1-2 hours)
- [ ] Audit orphaned sidebar items
- [ ] Fix broken route references
- [ ] Assign items to appropriate roles by module
- [ ] Deactivate truly unused items
- [ ] Verify sidebar displays correctly for each role

### Phase 7c: Permissions (30 minutes)
- [ ] Apply pattern-based tagging updates (15 UPDATE statements)
- [ ] Manually review remaining ~20-50 untagged permissions
- [ ] Tag remaining permissions
- [ ] Verify all permissions now have module tags

### Phase 7d: Workflows (1 hour)
- [ ] Identify which workflow stages need permission binding
- [ ] Create workflow_stage_permissions entries
- [ ] Assign responsible roles for each stage
- [ ] Test workflow transitions with enhanced middleware

---

## VALIDATION QUERIES (Run After Each Step)

```sql
-- After Route Remediation
SELECT COUNT(*) as unmapped_routes FROM routes r
WHERE r.is_active = 1
AND NOT EXISTS (SELECT 1 FROM route_permissions WHERE route_id = r.id)
AND NOT EXISTS (SELECT 1 FROM role_routes WHERE route_id = r.id);
-- Expected: 0

-- After Sidebar Remediation
SELECT COUNT(*) as orphaned_sidebar_items FROM sidebar_menu_items smi
WHERE smi.is_active = 1
AND NOT EXISTS (SELECT 1 FROM role_sidebar_menus WHERE sidebar_menu_id = smi.id);
-- Expected: 0

-- After Permission Tagging
SELECT COUNT(*) as untagged_permissions FROM permissions WHERE module IS NULL;
-- Expected: 0 (or very close)

-- After Workflow Binding
SELECT COUNT(*) as stages_without_permissions FROM workflow_stages ws
WHERE ws.is_active = 1
AND NOT EXISTS (SELECT 1 FROM workflow_stage_permissions WHERE workflow_stage_id = ws.id);
-- Expected: 0 or identified acceptable exceptions
```

---

## ROLLBACK PROCEDURE

If remediation causes issues:

```sql
-- Restore from Phase 5.5 backup tables
RESTORE TABLE routes FROM backup_routes_20260329;
RESTORE TABLE route_permissions FROM backup_route_permissions_20260329;
RESTORE TABLE sidebar_menu_items FROM backup_sidebar_menu_items_20260329;
RESTORE TABLE role_sidebar_menus FROM backup_role_sidebar_menus_20260329;
RESTORE TABLE permissions FROM backup_permissions_20260329;
```

Or restore full database:
```bash
mysql -u root -padmin123 KingsWayAcademy < KingsWayAcademy_20260329_PRODUCTION_BACKUP.sql
```

---

## NEXT STEPS

**After Phase 7 Completion**:
1. Phase 8: User Acceptance Testing
2. Phase 9: Production Monitoring & Tuning
3. Phase 10: Documentation & Handoff

---

**Created**: 2026-03-29 by Claude Agent
**Ready for Execution**: YES
**Estimated Duration**: 4-6 hours total remediation work
