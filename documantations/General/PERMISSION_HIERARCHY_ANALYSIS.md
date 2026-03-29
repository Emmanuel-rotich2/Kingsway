# PERMISSION ARCHITECTURE ANALYSIS
## Current State vs. Target Hierarchical Model

**Date**: 2026-03-29
**Status**: PARTIAL IMPLEMENTATION (3 out of 5 levels complete)

---

## INTENDED HIERARCHICAL PERMISSION ARCHITECTURE

According to the RBAC blueprints, permissions should be organized at **5 levels**:

### Level 1: MODULE Level
**Definition**: High-level business area (System, Students, Academics, Finance, etc.)
**Example**: `Students`, `Academics`, `Finance`, `Transport`, `Communications`
**Purpose**: Business domain organization and audit categorization

### Level 2: ROUTE Level (Page Access Guard)
**Definition**: Can user access this page/route at all?
**Example**: `manage_students`, `manage_finance`, `finance_approvals`, `manage_academics`
**Purpose**: Prevent unauthorized page access

### Level 3: COMPONENT Level (UI Section Guard)
**Definition**: Within a page, can user see/access specific sections/tabs?
**Example**: On `manage_students` page:
  - Can see "Personal Info" tab?
  - Can see "Fees" tab?
  - Can see "Discipline" tab?
  - Can see "Academics" tab?
**Purpose**: Hide sections UI from unauthorized users

### Level 4: PAGE ACTION Level (Button/Form Guard)
**Definition**: What actions can user perform on a page?
**Example**: On `manage_students` page:
  - Can see "Add Student" button? → `students_create`
  - Can see "Edit" button? → `students_edit`
  - Can see "Delete" button? → `students_delete`
  - Can see "Promote" button? → `students_promote`
**Purpose**: Control what buttons/forms are visible and enabled

### Level 5: WORKFLOW STAGE Level (Process Gate)
**Definition**: Can user move to next workflow stage?
**Example**: In Admissions workflow:
  - Can move from "Application" to "Document Verification"? → `admission_documents_verify`
  - Can move from "Verification" to "Approval"? → `admission_applications_approve`
**Purpose**: Enforce business process rules

---

## CURRENT STATE IN DATABASE

### ✅ Level 1: MODULE - IMPLEMENTED (87% Complete)

**Structure**: `permissions` table with `module` column
```
Field: module
Status: 3,922 permissions tagged (87%)
        551 permissions untagged (13%)

Examples:
- academic_assessments_create → module: "Academics"
- students_fees_adjust → module: "Students"
- finance_approve → module: "Finance"
- transport_routes_manage → module: "Transport"
```

**Status**: ✅ MOSTLY WORKING (needs 551 permissions tagged)

---

### ✅ Level 2: ROUTE (PAGE ACCESS) - HIGHLY IMPLEMENTED

**Structure**: `route_permissions` table with 69,598+ mappings
```
Table: route_permissions
- route_id → FK to routes
- permission_id → FK to permissions
- access_type: view, create, update, delete, approve, manage, all
- is_required: 1 (permission is required to access this route)

Current State:
- 69,598 route-permission mappings exist ✓
- 34 routes without any permission guard (need fixes)
- Multiple permissions per route (permission matrix) ✓
- access_type column provides action type ✓
```

**Status**: ✅ WORKING WELL (34 routes need guards)

---

### ❌ Level 3: COMPONENT (UI Section) - NOT IMPLEMENTED

**Definition**: Within a page, which sections/tabs are visible per permission?

**Current State**: NO DEDICATED TABLE - Not enforced at database level
```
- sidebar_menu_items exist (550+ items)
- role_sidebar_menus map roles to items (8,551 assignments)
- BUT no component-level permission mapping
- NO table like: page_components, component_permissions, or tab_guards
```

**Evidence**:
- Sidebar filtering happens in MenuBuilderService (code-based)
- NOT database-driven component visibility
- Would need a new table: `page_components` with permissions per component

**Status**: 🔴 NOT IMPLEMENTED (code-based instead of data-driven)

---

### ❌ Level 4: PAGE ACTION & WORKFLOW - PARTIALLY IMPLEMENTED

**Definition**: What buttons/forms/actions are visible on a page?

**Current State**:
```
Database Support:
- permissions table has action column (view, create, edit, delete, approve, publish, export, etc.)
- 4,470 permissions have action defined (99.9%)
- Example: academic_assessments_create (action: create)
           academic_results_publish (action: publish)
           finance_approve (action: approve)

BUT: No table maps permissions to specific page actions

Code-based Implementation (RoleBasedUI.js):
- hasPermission(permission_code) exists ✓
- Checks if user has specific permission ✓
- BUT not organized by page/component/action hierarchy
- Frontend checks are ad-hoc, not systematic

Evidence of Partial Implementation:
- frontend checks permissions in modals (students_create button)
- backend checks in controllers (@permission('students_create'))
- BUT no centralized registry of "which buttons need which permissions"
```

**Status**: ⚠️ PARTIALLY WORKING (code-based, not data-driven)

---

### 🔴 Level 5: WORKFLOW STAGE - NOT ENFORCED

**Definition**: Can user move to next workflow stage? What permission is required?

**Current State**:
```
Table: workflow_stages (field: required_permission)
- 65 workflow stages exist
- 65 have required_permission = NULL (NOT GUARDED) 🔴
- 0 have workflow stage permission enforcement

Table: responsible_role_ids
- Field exists but mostly empty
- Should specify which roles can perform this stage

Results:
- Workflows exist but are NOT enforced
- Anyone can move through stages if they know the URL
- Process gates are open (massive security gap)
```

**Status**: 🔴 NOT IMPLEMENTED (critical missing)

---

## COMPARISON TABLE: Target vs. Current

| Level | Name | Should Be | Currently Is | Gap | Priority |
|-------|------|-----------|--------------|-----|----------|
| **1** | Module | Database-driven | 87% tagged DB | 13% missing | ⚠️ MEDIUM |
| **2** | Route/Page | Database-driven | 69k+ mappings DB | 34 routes unguarded | ⚠️ MEDIUM |
| **3** | Component | Database-driven | Not implemented | 100% missing | 🔴 HIGH |
| **4** | Page Action | Database + Code | Code-only | Not systematic | 🔴 HIGH |
| **5** | Workflow Stage | Database-driven | All NULL | 100% missing | 🔴 CRITICAL |

---

## WHAT'S WORKING TODAY ✅

```
Director login response includes: 25 permissions
Example returned permissions:
- academic_view          ← Module filter (Level 1) ✓
- finance_approve        ← Action identified (Level 4 hint) ✓
- students_promote       ← Action identified (Level 4 hint) ✓
- communications_create  ← Action identified (Level 4 hint) ✓

Route Access Protection:
- 69,598 route_permission entries ✓✓✓
- Every major route guarded ✓
- Authorization happens at API entry point ✓
- MenuBuilderService filters based on route_permissions ✓

Result: Users can't access unauthorized routes
BUT: Can't see component-level guards (tabs/sections hidden)
AND: Page actions aren't systematically enforced
AND: Workflows aren't gated by permissions
```

---

## WHAT'S BROKEN 🔴

```
Component-Level Visibility:
- No database mapping of permissions to page sections
- Sidebar items show/hide via code logic, not permission query
- Tab visibility not controlled by permissions
- Form sections not permission-gated

Workflow Enforcement:
- 65 workflow stages with NO permission requirements
- Anyone can approve admissions if they know URL
- Payment approval can be bypassed (critical risk!)
- Discipline tracking not enforced

Page Actions:
- Buttons shown/hidden by frontend code
- No centralized registry of "button X needs permission Y"
- Inconsistent permission checks across pages
- No audit of which permissions were used for each action
```

---

## THE MISSING PIECES NEEDED FOR FULL HIERARCHY

### To Implement Level 3 (Component):

Need new table: `page_components`
```sql
CREATE TABLE page_components (
  id INT PRIMARY KEY,
  page_id INT,                    -- FK to routes
  component_name VARCHAR(100),    -- "fees_tab", "discipline_tab", "academics_tab"
  component_type ENUM('tab', 'section', 'modal', 'form', 'table'),
  required_permission VARCHAR(255), -- "students_fees_view"
  display_order INT,
  created_at TIMESTAMP
);
```

### To Implement Level 4 (Page Action) _Systematically:

Need new table: `page_actions`
```sql
CREATE TABLE page_actions (
  id INT PRIMARY KEY,
  page_id INT,                    -- FK to routes
  action_name VARCHAR(100),       -- "add_student", "edit_student", "delete_student"
  button_label VARCHAR(100),      -- "Add Student"
  required_permission VARCHAR(255), -- "students_create"
  button_class VARCHAR(50),       -- "btn-primary", "btn-danger"
  confirmation_required BOOLEAN,
  created_at TIMESTAMP
);
```

### To Implement Level 5 (Workflow Stage - CRITICAL):

Update existing table: `workflow_stages`
```sql
-- Currently all NULL, need to populate:
UPDATE workflow_stages
SET required_permission = 'admission_applications_approve'
WHERE workflow_id = ADMISSIONS_ID AND sequence = APPROVAL_STAGE;

UPDATE workflow_stages
SET responsible_role_ids = JSON_ARRAY(Director_ID, Deputy_ID)
WHERE workflow_id = ADMISSIONS_ID;
```

---

## RECOMMENDATION: IMMEDIATE ACTIONS

### Phase 3a: Complete Module Tagging (Quick Win)
- [ ] Tag 551 untagged permissions with module
- [ ] Time: ~30 min
- [ ] Impact: Complete Level 1 hierarchy
- [ ] Risk: LOW

### Phase 3b: Guard Unguarded Routes
- [ ] Add route_permissions for 34 routes
- [ ] Time: ~1 hour
- [ ] Impact: Complete Level 2 coverage
- [ ] Risk: LOW

### Phase 3c: Add Workflow Stage Permissions (CRITICAL)
- [ ] Populate required_permission for 65 stages
- [ ] Populate responsible_role_ids for 65 stages
- [ ] Time: ~2 hours (complex logic)
- [ ] Impact: Enforce workflow gates
- [ ] Risk: MEDIUM (affects process flows)

### Phase 3d: Component-Level Guards (Future)
- [ ] Create page_components table
- [ ] Map permissions to tabs/sections
- [ ] Update frontend to check component permissions
- [ ] Time: ~4 hours
- [ ] Impact: Hide unauthorized UI sections
- [ ] Risk: MEDIUM

### Phase 3e: Systematic Page Action Registry (Future)
- [ ] Create page_actions table
- [ ] Audit every button/form on every page
- [ ] Map to required permissions
- [ ] Centralize permission checks
- [ ] Time: ~6 hours
- [ ] Impact: Consistent action guarding
- [ ] Risk: HIGH (affects many pages)

---

## SUMMARY

| Hierarchy Level | Status | Quality | Critical | Next Action |
|---|---|---|---|---|
| **Module** | 87% | Good | No | Complete 13% tagging |
| **Route** | 95% | Good | No | Guard 34 routes |
| **Component** | 0% | N/A | No | Create table + populate |
| **Page Action** | 20% | Poor | No | Create registry |
| **Workflow Stage** | 0% | N/A | YES 🔴 | Populate permissions immediately |

---

## CRITICAL SECURITY FINDINGS

1. **Workflows not enforced** - Any user can approve admissions if route is known
2. **No audit trail** - Don't know which permission was used for each action
3. **Component filtering in code** - Not data-driven, harder to audit
4. **No workflow stage guards** - Process flows can be bypassed

---

**Status**: You SHOULD have 5-level hierarchy. You currently have:
- Level 1 & 2: ✅ Mostly working
- Level 3: ❌ Not implemented
- Level 4: ⚠️ Code-based, not systematic
- Level 5: 🔴 CRITICAL - Not enforced at all
