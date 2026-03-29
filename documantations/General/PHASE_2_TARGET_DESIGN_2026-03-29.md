# Phase 2: Target RBAC & Workflow Synchronization Model Design

## OVERVIEW

This document maps the current state (from audit) to the target synchronized model (from design docs) and creates the blueprint for Phases 3-5.

---

## SECTION 1: TARGET ROLE MODEL

### 1.1 Active Role Assignments (Finalized)

All 11 operational roles will have:
- Clear module ownership
- Defined permission set per module
- Assigned dashboards + sidebar items
- Workflow responsibilities

| Role ID | Name | Primary Module | Secondary Modules | Dashboard | Sidebar Items | Workflows |
|---------|------|-----------------|-------------------|-----------|--------------|-----------|
| 2 | System Administrator | System | All (monitoring only) | System Admin | System config, roles, permissions, audit | Audit log |
| 3 | Director | Finance | Admissions, Students, Academic, Scheduling, HR, Reporting | Director | Finance approval, payroll, student promotions, reports | All approval workflows |
| 4 | School Administrator | Operations | Students, Communications, HR, Academics, Transport | School Admin | User management, communications, student admin, academic admin | Admission workflow, user onboarding |
| 5 | Headteacher | Academics | Admissions, Students, Discipline, Communications, Reporting | Headteacher | Classes, assessments, disciplines, communications | Promotion & results, discipline cases |
| 6 | Deputy Head - Academic | Academics | Admissions, Students, Scheduling | Deputy Academic | Admissions, classes, student promotion, timetable | Admissions, promotion & results |
| 7 | Class Teacher | Academics | Attendance, Assessments, Discipline | Class Teacher | My classes, attendance, assessments, lesson plans, discipline | Assessment entry, discipline notes |
| 8 | Subject Teacher | Academics | Attendance, Assessments | Subject Teacher | My classes, attendance, results, assessments | Assessment entry |
| 9 | Intern/Student Teacher | Academics | - (View only) | Teacher | Class observation, lesson plans | None (observer only) |
| 10 | Accountant | Finance | Students | Accountant | Payment recording, fee structure, approvals, reports | Payment processing, fee management |
| 14 | Inventory Manager | Inventory | - | Inventory | Store management, requisitions, stock | Inventory requisition/receipt |
| 16 | Cateress | Kitchen/Catering | Inventory | Cateress | Menu planning, food stock | Meal planning |
| 18 | Boarding Master | Boarding/Health | Discipline, Transport | Boarding | Boarding roll call, permissions/exeats, conduct | Boarding permissions |
| 21 | Talent Development | Activities/Sports | Communications | Talent Dev | Activities, clubs, competitions | Activity organization |
| 23 | Driver | Transport | - | Driver | My routes, vehicle management | Route management |
| 24 | Chaplain | Pastoral/Counseling | Communications | Chaplain | Student counseling, pastoral care | Counseling cases |

### 1.2 Tracking-Only Roles (NO System Access)

To be INACTIVE in system (payroll/DB records only):
- Role 32: Kitchen Staff
- Role 33: Security Staff
- Role 34: Janitor

**Action**: Mark inactive in roles table, remove from role_permissions and role_routes

### 1.3 Test Roles (To Be Purged)

Delete from database:
- Role 64: Staff (generic, not needed)
- Role 65-70: TeacherTest_* (temporary)

**Action**: Backup then delete completely

---

## SECTION 2: TARGET PERMISSION MODEL

### 2.1 Permission Reorganization

Instead of 583 flat codes, organize as:

```
{module}_{action}_{component}

Examples:
- students_view                    (module: students, action: view)
- students_create                  (module: students, action: create)
- students_promote                 (module: students, action: approve, component: promotion)
- academic_results_publish         (module: academic, action: publish, component: results)
- finance_approve                  (module: finance, action: approve)
- payments_record                  (module: finance, action: create, component: payments)
- communications_announcements_create  (module: communications, action: create, component: announcements)
```

### 2.2 Module-Level Permission Groups

Organize 583 permissions into 12 modules:

#### System Module
- `system_settings_manage`
- `rbac_manage` (includes manage_roles, manage_permissions)
- `audit_view`
- `system_monitor`
- `developer_tool_execute`
- **Owner**: System Administrator (role 2)
- **Count**: ~40 permissions

#### Students Module
- `students_view`, `students_create`, `students_edit`, `students_delete`
- `students_promote`, `students_transfer`
- `students_attendance_view`, `students_attendance_edit`, `students_attendance_mark`
- `students_discipline_view`, `students_discipline_manage`
- `students_fees_view`, `students_fees_adjust`
- `students_generate` (ID cards, etc.)
- **Owners**: School Admin (full), Directors (approvals), Teachers (limited)
- **Count**: ~60 permissions

#### Admissions Module
- `admission_view`
- `admission_create` (applications)
- `admission_documents_verify`
- `admission_interviews_create`, `admission_interviews_schedule`
- `admission_applications_approve`, `admission_applications_approve_final` (Director)
- `admission_offer_create`
- **Owners**: Headteacher, Deputies (create/verify), Director (approve)
- **Count**: ~30 permissions

#### Academics Module
- `academic_view`, `academic_manage`
- `academic_terms_manage`
- `academic_assessments_create`, `academic_assessments_edit`, `academic_assessments_delete`, `academic_assessments_publish`
- `academic_results_view`, `academic_results_edit`, `academic_results_publish`
- `academic_lesson_plans_view`, `academic_lesson_plans_edit`
- `academic_classes_manage`
- **Owners**: Headteacher (manage), Teachers (create/edit), Deputies (manage)
- **Count**: ~80 permissions

#### Finance Module
- `finance_view`, `finance_approve`
- `fees_manage`, `fees_edit`, `fees_create`, `fees_delete`, `fees_view`
- `payments_record`, `payments_approve`
- `finance_reports_view`, `finance_reports_export`
- `finance_reconcile`
- **Owner**: Accountant (primary), Director (approvals)
- **Count**: ~50 permissions

#### Scheduling Module
- `schedules_view`, `schedules_manage`, `schedules_publish`
- `schedules_conflict_check`
- `term_holidays_manage`
- **Owner**: Headteacher, Deputies
- **Count**: ~20 permissions

#### Attendance Module
- `attendance_view`, `attendance_mark`, `attendance_edit`
- `attendance_staff_view`, `attendance_staff_manage`
- **Owner**: Teachers (mark), Headteacher (view/manage)
- **Count**: ~15 permissions

#### Discipline & Counseling Module
- `discipline_cases_view`, `discipline_cases_create`, `discipline_cases_manage`
- `counseling_records_view`, `counseling_records_create`
- `permissions_exeats_view`, `permissions_exeats_approve`
- **Owner**: Headteacher, Deputies (Discipline), Chaplain (Counseling)
- **Count**: ~25 permissions

#### Communications Module
- `communications_view`, `communications_announcements_create`
- `communications_email_view`, `communications_email_send`
- `communications_sms_view`, `communications_sms_send`
- `communications_outbound_approve`
- **Owner**: School Admin, Module Leads, with approvals by Director
- **Count**: ~30 permissions

#### Transport Module
- `transport_view`, `transport_routes_manage`
- `transport_payments_approve`
- **Owner**: Driver, Transport Manager
- **Count**: ~15 permissions

#### Inventory & Catering Module
- `inventory_view`, `inventory_adjust`, `inventory_reports_export`
- `catering_menu_plan`, `catering_food_view`
- **Owner**: Inventory Manager, Cateress
- **Count**: ~25 permissions

#### Boarding & Health Module
- `boarding_view`, `boarding_discipline_manage`
- `boarding_permissions_manage`
- **Owner**: Boarding Master
- **Count**: ~15 permissions

#### Activities & Talent Development Module
- `activities_manage`, `competitions_manage`
- `activities_view`
- **Owner**: Talent Development Manager
- **Count**: ~15 permissions

#### Reporting & Analytics Module
- `reports_view`, `reports_export`
- `dashboard_configure`
- **Owner**: Director, Headteacher, Accountant
- **Count**: ~15 permissions

**Total**: ~415 core permissions (much tighter than 583)

### 2.3 Permission Assignment by Role

#### System Administrator (Role 2)
- `system_*` (all system permissions)
- `audit_view`
- **Total**: ~40 permissions

#### Director (Role 3)
- `finance_*` (all finance operations)
- `finance_approve` (final approval for payments, payroll)
- `admission_applications_approve_final`
- `students_promote` (approve promotions)
- `payroll_view`, `payroll_approve`
- `reports_view`, `reports_export`
- `audit_view` (school-level)
- `communications_outbound_approve`
- `users_manage` (staff management)
- **Total**: ~80 permissions

#### School Administrator (Role 4)
- `students_view/create/edit`
- `admission_view/create/documents_verify`
- `communications_*`
- `users_manage` (user creation/modification)
- `staff_create/edit/delete`
- `audit_view` (school-level)
- `academic_manage`
- `attendance_view`
- **Total**: ~60 permissions

#### Headteacher (Role 5)
- `academic_manage/view`
- `academic_assessments_create/publish`
- `academic_results_view/publish`
- `students_view/promote`
- `admission_view/create`
- `attendance_view/edit` (mark and edit)
- `discipline_cases_view/manage`
- `communications_*`
- `reports_view`
- `schedules_view/manage/publish`
- **Total**: ~60 permissions

#### Deputy Head - Academic (Role 6)
- `academic_manage/view`
- `academic_assessments_create`
- `admission_view/create/documents_verify`
- `students_view/promote`
- `schedules_manage/view`
- `attendance_view`
- `communications_*`
- **Total**: ~40 permissions

#### Deputy Head - Discipline (Role 63)
- `discipline_cases_view/manage`
- `students_discipline_view/manage`
- `permissions_exeats_view/approve`
- `attendance_view/mark`
- `admission_view`
- `communications_*`
- **Total**: ~30 permissions

#### Class Teacher (Role 7)
- `academic_view` (view classes/assessments)
- `academic_assessments_create`
- `academic_results_edit` (enter results)
- `attendance_mark` (mark attendance)
- `students_view`
- `students_discipline_notes` (add notes, not manage)
- `communications_view`
- `lesson_plans_view/edit`
- **Total**: ~20 permissions

#### Subject Teacher (Role 8)
- `academic_view` (subject-scoped)
- `academic_assessments_create/edit`
- `academic_results_edit`
- `attendance_view/edit` (scoped to subject)
- `communications_view`
- **Total**: ~15 permissions

#### Intern/Student Teacher (Role 9)
- `academic_view` (observation)
- `attendance_view`
- `communications_view`
- **Total**: ~5 permissions (read-only)

#### Accountant (Role 10)
- `finance_view/create/export`
- `payments_record/view`
- `fees_manage/edit/view`
- `finance_reports_view`
- `students_fees_view`
- `communications_view`
- **Total**: ~20 permissions

#### Inventory Manager (Role 14)
- `inventory_view/adjust`
- `inventory_reports_export`
- `communications_view`
- **Total**: ~10 permissions

#### Cateress (Role 16)
- `catering_food_view`
- `catering_menu_plan`
- `inventory_view`
- `communications_view`
- **Total**: ~8 permissions

#### Boarding Master (Role 18)
- `boarding_view`
- `boarding_discipline_manage`
- `boarding_permissions_manage`
- `discipline_cases_manage` (boarding-scoped)
- `attendance_view`
- `communications_view`
- **Total**: ~15 permissions

#### Talent Development (Role 21)
- `activities_manage`
- `competitions_manage`
- `communications_announcements_create`
- **Total**: ~8 permissions

#### Driver (Role 23)
- `transport_view/manage_routes` (own routes)
- `communications_view`
- **Total**: ~5 permissions

#### Chaplain (Role 24)
- `counseling_records_view/create`
- `communications_view`
- **Total**: ~8 permissions

---

## SECTION 3: TARGET ROUTE & SIDEBAR MODEL

### 3.1 Routes Reorganized by Module

Each route gets:
- **route_permission**: Required permission to access
- **sidebar_visibility**: Which roles see it (may differ from access)
- **module**: Owning module

#### Students Module Routes
| Route | Permission | Access Roles | Module |
|-------|-----------|--------------|--------|
| manage_students | students_view | School Admin, Teachers, Headteacher, Directors | Students |
| manage_students_admissions | admission_view | School Admin, Headteacher, Deputies | Admissions |
| all_students | students_view | All staff | Students |
| student_fees | students_fees_view | Accountant, Director | Students |

#### Academic Module Routes
| Route | Permission | Access Roles | Module |
|-------|-----------|--------------|--------|
| manage_academics | academic_manage | Headteacher, Deputies | Academics |
| manage_assessments | academic_assessments_create | Teachers, Headteacher, Deputies | Academics |
| view_results | academic_results_view | Teachers, Headteacher, Accountant | Academics |
| report_cards | academic_results_publish | Headteacher, Director | Academics |

#### Finance Module Routes
| Route | Permission | Access Roles | Module |
|-------|-----------|--------------|--------|
| manage_finance | finance_view | Accountant, Director | Finance |
| manage_fee_structure | fees_manage | Accountant, Director | Finance |
| manage_payments | payments_record | Accountant, Director | Finance |
| finance_approvals | finance_approve | Director | Finance |

#### (Continue for all 12 modules...)

### 3.2 Sidebar Reorganization

**Current Issue**: Duplicate items, module mixing
**Target**: Organized by module, consistent naming

Example sidebar for Headteacher:
```
Academic
  ├── Manage Classes
  ├── Manage Assessments
  ├── View Results
  ├── Report Cards
Students
  ├── Student Management
  ├── Student Admissions
  ├── Student Promotions
  ├── Discipline Cases
Scheduling
  ├── Manage Timetable
  ├── Academic Calendar
Attendance
  ├── Mark Attendance
  ├── View Attendance
Communications
  ├── Announcements
  ├── Email
  ├── SMS
Reporting
  ├── Performance Reports
```

---

## SECTION 4: TARGET WORKFLOW MODEL

### 4.1 Workflows Linked to RBAC

Every workflow stage gets:
- **Required permission** (who can execute)
- **Assigned roles** (default actors)
- **Audit trail** (via workflow_stage_history)
- **Notification rules** (to next roles)

#### Example: Admissions Workflow

| Stage | Permission | Roles | Action | Next Roles |
|-------|-----------|-------|--------|-----------|
| application_intake | admission_create | Headteacher, Deputies | Create application | Verification team |
| document_verify | admission_documents_verify | Headteacher, Deputies | Verify documents | Headteacher |
| interviews | admission_interviews_schedule | Headteacher, Deputies | Schedule interview | Interview panel |
| offer | admission_offer_create | Headteacher, Deputies (create), Director (approve) | Create offer | Director |
| enrollment_approve | admission_applications_approve_final | Director | Final approval | Accountant (fees setup) |
| cleanup | admission_cleanup | School Admin | Archive/cleanup | System Admin |

**Workflow-RBAC Contract**:
- User can only execute stage if they have required permission
- Notification sent to all users with next stage permission + role assignment
- History logged with actor, timestamp, remarks
- Workflow cannot proceed without required permission

### 4.2 New Workflows to Define

Based on permission catalog:
1. **Student Discipline** - Creation → Investigation → Action → Appeal
2. **Staff Onboarding** - Application → Verification → Hiring → System Setup
3. **Leave Approval** - Request → Manager Review → Finance Check → Approval
4. **Inventory Requisition** - Request → Approval → Receipt → Consumption
5. **Transport Route Planning** - Route Definition → Driver Assignment → Publication
6. **Student Result Publication** - Entry → Review → Moderation → Publication → Parent Notification
7. **Fee Waiver Request** - Application → Documentation → Finance Review → Director Approval

---

## SECTION 5: CODE CHANGES REQUIRED

### 5.1 Backend Authorization Consolidation

**Target Pattern** (replace all 4 existing patterns with this):

```php
<?php
// In BaseController or similar
protected function authorize(string|array $permissions, string $message = 'Insufficient permissions')
{
    if (!is_array($permissions)) {
        $permissions = [$permissions];
    }

    if (!$this->userHasAnyPermission($permissions)) {
        return $this->forbidden($message);
    }

    return null;
}

// Usage:
public function update(int $id, array $data)
{
    if ($auth = $this->authorize('students_edit', 'You cannot edit students')) {
        return $auth;
    }

    // ... rest of logic
}
```

### 5.2 DashboardController Refactoring

**Replace all 43 hardcoded role checks**:

```php
// BEFORE:
if ($this->getUserRole() !== 2) {
    return $this->forbidden('System Admin only');
}

// AFTER:
if ($auth = $this->authorize('system_settings_manage')) {
    return $auth;
}
```

### 5.3 Permission Check Coverage

Add authorization to all 11 gap controllers:
- ActivitiesController
- CommunicationsController
- StaffController
- TransportController
- InventoryController
- ReportsController
- CounselingController
- MaintenanceController
- SchoolConfigController
- And any others found

### 5.4 Frontend Synchronization

Ensure `/js/api.js` login response includes:
- All 415 new permission codes (not 583)
- Permission format must be consistent (underscore as canonical)
- No format conversion needed on client

---

## SECTION 6: DATABASE MIGRATION STRATEGY

### 6.1 Backup Strategy

Before making changes:
```sql
-- Backup existing RBAC tables
CREATE TABLE backup_roles_2026_03_29 LIKE roles;
INSERT INTO backup_roles_2026_03_29 SELECT * FROM roles;

-- Backup permissions
CREATE TABLE backup_permissions_2026_03_29 LIKE permissions;
INSERT INTO backup_permissions_2026_03_29 SELECT * FROM permissions;

-- Backup mappings
CREATE TABLE backup_role_permissions_2026_03_29 LIKE role_permissions;
INSERT INTO backup_role_permissions_2026_03_29 SELECT * FROM role_permissions;

-- etc for all RBAC tables
```

### 6.2 Migration Phases

**Phase 3a: Cleanup**
1. Delete test roles (64-70)
2. Mark tracking roles inactive (32, 33, 34)
3. Remove unused permissions (identify in next step)
4. Remove orphaned role_permissions

**Phase 3b: Normalize Permissions**
1. Create new permission codes (~415 core + ~168 workflow)
2. Map old codes to new codes (data migration)
3. Remove old codes

**Phase 3c: Rebuild Role Permissions**
1. Delete all role_permissions rows
2. Insert target permissions for each role (from RBAC_ROLE_MODULE_ASSIGNMENTS)
3. Verify each role has expected count

**Phase 3d: Rebuild Routes & Route Permissions**
1. Validate all routes exist
2. Add missing route_permission mapping
3. Remove routes with no permission context

**Phase 3e: Rebuild Sidebars**
1. Delete role_sidebar_menus entries
2. Rebuild sidebar for each role (module-grouped)
3. Ensure every sidebar item has valid route

### 6.3 Rollback Plan

If issues detected:
```sql
TRUNCATE TABLE roles;
INSERT INTO roles SELECT * FROM backup_roles_2026_03_29;

TRUNCATE TABLE permissions;
INSERT INTO permissions SELECT * FROM backup_permissions_2026_03_29;

... (repeat for all tables)
```

---

## SECTION 7: VALIDATION CHECKPOINTS

### 7.1 After Cleanup (Phase 3a)

Verify:
- [ ] No more test roles (64-70 deleted)
- [ ] Tracking roles (32, 33, 34) marked inactive
- [ ] No user_roles entries for test/tracking roles
- [ ] 15 active roles remain

### 7.2 After Permission Normalization (Phase 3b)

Verify:
- [ ] ~415 core permissions exist
- [ ] Old permissions (583) cleaned up
- [ ] No duplicate permission codes
- [ ] All permission codes follow module_action[_component] pattern
- [ ] Stored procedure `sp_user_get_effective_permissions()` still works

### 7.3 After Role-Permission Rebuild (Phase 3c)

Verify:
- [ ] Each active role has expected permission count (verify against RBAC_ROLE_MODULE_ASSIGNMENTS.md)
- [ ] No orphan role_permissions (all reference valid roles + permissions)
- [ ] Director has ~80 permissions
- [ ] System Admin has ~40 permissions
- [ ] Teachers have 15-20 permissions
- [ ] No duplicate role_permissions entries

### 7.4 After Route-Permission Rebuild (Phase 3d)

Verify:
- [ ] Every route_permission entry has valid route_id + permission_id
- [ ] No orphan route_permissions
- [ ] Key routes have permissions:
  - manage_students → students_view
  - manage_finance → finance_view
  - manage_academics → academic_manage
  - etc for all 12 modules

### 7.5 After Sidebar Rebuild (Phase 3e)

Verify:
- [ ] Every role has 5-15 sidebar items (appropriate for role)
- [ ] No duplicate sidebar items per role
- [ ] Every sidebar item has valid route_id
- [ ] Routes in sidebar are group by module
- [ ] Permission guards match route permissions

### 7.6 Code Synchronization (Phase 4)

Verify:
- [ ] DashboardController: All 43 hardcoded role checks replaced with permission checks
- [ ] Gap controllers: All 11 now have permission guards
- [ ] StudentsController: Pattern remains consistent
- [ ] No hardcoded role IDs remain in non-test code
- [ ] Frontend AuthContext stays synchronized with backend permissions

### 7.7 Complete System Check (Phase 5)

Verify:
- [ ] User can login with any active role
- [ ] User sees only routes they have permission for
- [ ] User sidebar only shows items for accessible routes
- [ ] Dashboard loads for their assigned dashboard
- [ ] Workflows enforce permission checks at each stage
- [ ] Audit logs show every permission check
- [ ] No permission-related errors in logs

---

## SECTION 8: GO/NO-GO CRITERIA

**Safe to proceed to Phase 3 if**:
✅ Audit document complete and reviewed
✅ This design document has stakeholder approval
✅ Backup strategy validated
✅ Rollback scripts tested (on backup, not live)
✅ Key stakeholders briefed

---

## SECTION 9: Timeline & Dependencies

| Phase | Duration | Depends On | Output |
|-------|----------|-----------|--------|
| Phase 2 (Design) | 1 hour | Audit complete | This document |
| Phase 3 (DB Sync) | 2-3 hours | Design approved | Migration scripts, backup, verification queries |
| Phase 4 (Code Sync) | 3-4 hours | DB normalized | Updated controllers, middleware, frontend |
| Phase 5 (Validation) | 2 hours | Code deployed | Audit report, validation script, go-live checklist |

---

**Design Completion**: 2026-03-29
**Next Phase**: Code migration scripts (Phase 3)
**Status**: READY FOR IMPLEMENTATION
