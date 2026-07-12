# Students Testing Checklist

## Static Checks

Run:

```bash
php -l api/controllers/StudentsController.php
php -l api/modules/students/StudentPermissionService.php
php -l api/modules/students/StudentScopeService.php
php -l api/modules/students/StudentRepository.php
php -l api/modules/students/StudentService.php
php -l pages/all_students.php
php -l pages/manage_students.php
php -l pages/student_profiles.php
php -l pages/students/student_context_view.php
node --check js/pages/student_context.js
node --check js/pages/student_profile_context.js
node --check js/api.js
```

## Manual Role Tests

Use each test account and confirm the page loads without mock or fallback data.

| Role | Route | Expected |
| --- | --- | --- |
| School Admin | `home.php?route=manage_students` | Full CRUD page loads; Add/Edit/Archive actions are visible only if permissions allow. |
| Director | `home.php?route=students_overview` | Read-only overview; no operational edit controls. |
| Headteacher | `home.php?route=students_overview` | Read-only overview with academic/discipline summary fields. |
| Deputy Academic | `home.php?route=academic_students` | Academic student list; promotion action may link to promotion tools; no finance/counseling fields. |
| Deputy Discipline | `home.php?route=discipline_students` | Discipline counts/open cases visible; no fee or counseling details. |
| Class Teacher | `home.php?route=my_students_list` | Only assigned class students show. |
| Subject Teacher | `home.php?route=subject_students_list` | Existing route should be migrated next; API context `subject_teacher` should only return assigned students. |
| Boarding Master/Matron | `home.php?route=boarding_students` | Boarders only; guardian contact allowed; no fee/counseling fields. |
| Cateress | `home.php?route=catering_boarding_students` | Boarders only; meal-planning fields only; no private profile details. |
| Driver | `home.php?route=transport_passengers` | Assigned passengers only; no medical, fee, discipline, or family financial data. |
| Counselor/Chaplain | `home.php?route=student_welfare` | Welfare-relevant fields; no finance fields. |
| Parent | `home.php?route=all_students` | Own children only when linked; no other students. |

## Legacy Route Tests

| Route | Expected |
| --- | --- |
| `home.php?route=all_students` | Safe read-only scoped directory; no AuthContext startup error. |
| `home.php?route=manage_students` | Admin CRUD page; non-admin users should be blocked by route/API permissions. |
| `home.php?route=student_profiles` | Search view loads with scoped results. |
| `home.php?route=student_profiles&id=101&context=discipline` | Profile tabs match discipline context. |

## API Tests

Authenticated requests:

```bash
GET /api/students/context-list?context=oversight
GET /api/students/context-list?context=teacher_class
GET /api/students/context-list?context=transport
GET /api/students/context-profile/101?context=welfare
```

Expected:

- Response has `success`, `data`, `message`, `errors`, and `meta`.
- Forbidden contexts return 403.
- Scoped contexts never fall back to all students.
- Returned fields match the context allowlist.

## Browser State Tests

For every role-context page:

- Loading state appears before data returns.
- Empty state appears when scope has no students.
- Forbidden state appears for a disallowed context.
- Error state appears if the API fails.
- Page title and filters match the role context.
- No mock data appears.
