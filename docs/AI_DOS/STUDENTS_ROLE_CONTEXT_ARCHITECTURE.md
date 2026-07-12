# Students Role-Context Architecture

## Goal

The Students module now uses explicit role-context pages backed by one shared student domain. Roles no longer share one operational `all_students` page for incompatible workflows.

## Backend Domain

Canonical backend files:

- `api/controllers/StudentsController.php`
- `api/modules/students/StudentService.php`
- `api/modules/students/StudentRepository.php`
- `api/modules/students/StudentScopeService.php`
- `api/modules/students/StudentPermissionService.php`

Existing `StudentsAPI` remains for legacy CRUD and specialized student workflows. New scoped reads use:

- `GET /api/students/context-list?context={context}`
- `GET /api/students/context-profile/{id}?context={context}`
- `GET /api/students/context-meta?context={context}`

All responses pass through the global API normalizer and return:

```json
{
  "success": true,
  "data": {},
  "message": "Success",
  "errors": [],
  "meta": {}
}
```

## Contexts

- `full_management`: School Admin CRUD surface.
- `oversight`: Director/Headteacher read-only overview.
- `academic`: Deputy Academic academic/promotion view.
- `discipline`: Deputy Discipline discipline summaries.
- `boarding`: Boarding Master/Matron boarders only.
- `catering`: Cateress boarders only, meal-planning fields only.
- `transport`: Driver assigned passengers only.
- `welfare`: Counselor/Chaplain welfare view.
- `teacher_class`: Class teacher assigned class students.
- `subject_teacher`: Subject teacher assigned class/subject students.
- `parent_children`: Parent linked children only.

## Frontend Routes

- `manage_students`: Admin CRUD using existing student management template.
- `students_overview`: Leadership oversight.
- `my_students_list`: Class teacher assigned class.
- `academic_students`: Deputy Academic view.
- `discipline_students`: Deputy Discipline view.
- `boarding_students`: Boarding view.
- `catering_boarding_students`: Catering meal-planning view.
- `transport_passengers`: Driver passenger view.
- `student_welfare`: Counselor/Chaplain view.
- `student_profiles`: Role-aware profile with server-provided tabs.
- `all_students`: Deprecated legacy route, now a safe read-only scoped directory.

Shared frontend files:

- `pages/students/student_context_view.php`
- `js/pages/student_context.js`
- `js/pages/student_profile_context.js`

## Privacy Rules

Field exposure is controlled by `StudentPermissionService`.

- Driver: identity, class/stream, route/stop only.
- Cateress: identity, class/stream, boarding status/type only.
- Counselor/Chaplain: welfare-relevant identity, DOB, blood group, guardian contact; no finance.
- Boarding: boarder identity, boarding status/type, guardian contact.
- Discipline: discipline counts/status summaries, not private finance/counseling notes.
- Academic/class/subject teacher: academic identity and assignment-scoped records.
- Admin: management fields and CRUD actions.

## Legacy Route Decisions

- `manage_students`: kept as School Admin operational CRUD.
- `student_profiles`: repaired as role-aware profile view.
- `all_students`: deprecated as a shared workflow page; kept as read-only scoped directory for old links.
- `pages/students/manage_students_*`: kept for legacy compatibility, not used as the role-context architecture.
