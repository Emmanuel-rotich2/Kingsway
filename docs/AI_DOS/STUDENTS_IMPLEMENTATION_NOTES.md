# Students Implementation Notes

## Summary

The Students module has been rebuilt around role-context reads while preserving existing CRUD logic for admin workflows.

## Backend Changes

Created:

- `StudentPermissionService`: maps roles to contexts, allowed actions, and field allowlists.
- `StudentScopeService`: builds row-level scopes for class teachers, subject teachers, parents, boarders, and transport passengers.
- `StudentRepository`: central scoped SQL reads for lists and profiles.
- `StudentService`: coordinates permissions, scopes, repository reads, field filtering, and profile tabs.

Updated:

- `StudentsController` now exposes `context-list`, `context-profile`, and `context-meta`.
- Existing CRUD endpoints remain in `StudentsController`/`StudentsAPI`; no separate student databases or duplicate CRUD logic were added.
- `js/api.js` exposes `API.students.contextList`, `contextProfile`, and `contextMeta`.

## Frontend Changes

Created:

- `pages/students/student_context_view.php`
- `pages/students_overview.php`
- `pages/academic_students.php`
- `pages/discipline_students.php`
- `pages/boarding_students.php`
- `pages/catering_boarding_students.php`
- `pages/transport_passengers.php`
- `pages/student_welfare.php`
- `js/pages/student_context.js`
- `js/pages/student_profile_context.js`

Repaired:

- `pages/manage_students.php` now directly loads admin CRUD instead of routing through `all_students`.
- `pages/my_students_list.php` now loads `teacher_class`.
- `pages/student_profiles.php` now uses scoped profile APIs and server-provided tabs.
- `pages/all_students.php` no longer uses the fragile AuthContext-dependent template fetcher; it is a safe read-only legacy directory.

## Sidebar Changes

Updated `config/role_sidebars.php`:

- Director: `students_overview`
- Headteacher: `students_overview`
- Deputy Academic: `academic_students`
- Deputy Discipline: `discipline_students`
- Cateress: `catering_boarding_students`
- Boarding Master/Matron: `boarding_students`
- Driver: `transport_passengers`
- Chaplain/Counselor: `student_welfare`
- Accountant student fee account link: `student_fees`

Updated dashboard quick links:

- Headteacher dashboard: `students_overview`
- Class teacher dashboard: `my_students_list`

## Important Behavior

- Server-side context checks are mandatory and happen before data is returned.
- Field filtering happens server-side after scoped SQL reads.
- Driver transport scope fails closed if the database cannot resolve assigned routes.
- `all_students` is intentionally retained as a deprecated compatibility route.

## Remaining Risks

- Some DB route registry records may still point to legacy route names. If route authorization is stricter than sidebar fallback, add the new route keys to the route registry.
- Driver-to-route mapping depends on a `transport_routes.driver_id` or equivalent deployment schema. In the provided dump this column is absent, so driver passenger lists return empty rather than broad data.
- Existing specialized pages such as `special_needs`, `student_health`, and finance-specific student pages were not rebuilt in this pass.
