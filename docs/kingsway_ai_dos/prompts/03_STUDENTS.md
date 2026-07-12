You are working inside the Kingsway School Management System.

Task:
Rescue and rebuild the Students module by replacing the current confused shared-page design with a clean role-context architecture.

This is an implementation task, not a general audit.

Problem:
The Students module is currently accessed by many roles for different reasons. The same pages such as manage_students, all_students, and student_profiles are being reused for incompatible workflows. This causes errors, permission confusion, broken UI, and incomplete workflows.

Goal:
Create a clean Students MVP where each role sees the exact student view they need, while all views share one backend student domain.

Do not create duplicate backend logic.

Architecture required:

1. Backend domain:
Create or repair:

- StudentsController
- StudentService
- StudentRepository
- StudentScopeService
- StudentPermissionService

The backend must expose scoped student reads:

- full_management
- oversight
- academic
- discipline
- boarding
- transport
- catering
- welfare
- teacher_class
- subject_teacher
- parent_children

1. Frontend page strategy:
Do not force all roles into one all_students page.

Create or normalize these page contexts:

- manage_students: School Admin full CRUD
- students_overview: Director/Headteacher read-only oversight
- my_students_list: Class teacher assigned class
- academic_students: Deputy Academic academic view
- discipline_students: Deputy Discipline discipline view
- boarding_students: Boarding Master/Matron boarders only
- catering_boarding_students: Cateress meal-planning boarders only
- transport_passengers: Driver assigned route passengers only
- student_welfare: Counselor/Chaplain welfare view
- student_profiles: profile view with role-aware tabs/actions

1. Legacy route handling:
Find current references to:

- all_students
- manage_students
- student_profiles

For each one:

- decide whether to keep, redirect, or replace
- do not delete blindly
- if all_students is too generic, convert it into a safe read-only directory or redirect based on role

1. Permission behavior:
Server-side permissions are mandatory.

Implement role/action rules:

- Admin can create/edit/archive students
- Director can view oversight, not operational edit
- Headteacher can view academic/discipline/health overview
- Deputy Academic can view academic data and promotion tools
- Deputy Discipline can view discipline-related student data
- Class Teacher can only view assigned class students
- Subject Teacher can only view students in assigned classes/subjects
- Boarding Master can only view boarders and boarding fields
- Cateress can only view boarding meal-planning fields, no private student details
- Driver can only view assigned passengers and transport attendance
- Counselor can view welfare/counseling-relevant profile data
- Parent can only view own children

1. Data privacy:
Different roles must not see unnecessary sensitive fields.

Examples:

- Driver must not see medical, fee, discipline, or family financial details
- Cateress must not see fees or discipline details
- Counselor may see welfare notes but not finance
- Accountant may see billing identity but not counseling notes
- Director can see summaries and reports, not necessarily every private note

1. Frontend behavior:
Every student page must have:

- loading state
- empty state
- forbidden state
- error state
- role-specific page title
- role-specific filters
- role-specific actions
- no mock data
- no placeholder fallback data

1. API contract:
All Students APIs must return a consistent shape:
{
  success: boolean,
  data: any,
  message: string,
  errors?: any,
  meta?: any
}

2. Implementation order:
A. Map current student pages and JS files.
B. Pick canonical backend files.
C. Implement StudentScopeService.
D. Implement StudentPermissionService.
E. Repair StudentsController endpoints.
F. Repair manage_students for Admin.
G. Repair student_profiles with role-aware tabs.
H. Replace or redirect all_students safely.
I. Create role-context pages only where needed.
J. Update sidebars to point to correct role-context pages.
K. Add testing checklist.

3. Do not:

- create separate student databases per role
- duplicate CRUD logic
- hardcode role names inside many pages
- expose all student fields to all roles
- leave all_students as a broken shared dumping page
- use mock data
- remove legacy files without documenting redirects

1. Deliverables:
Create/update:

- docs/AI_DOS/STUDENTS_ROLE_CONTEXT_ARCHITECTURE.md
- docs/AI_DOS/STUDENTS_IMPLEMENTATION_NOTES.md
- docs/AI_DOS/STUDENTS_TESTING_CHECKLIST.md

At the end report:

- pages created
- pages repaired
- pages deprecated
- sidebar routes changed
- APIs created/repaired
- permissions enforced
- remaining risks
- exact manual tests per role

Currently this is the situation at config/role_sidebars.php

Director......// Students — oversight, not operational ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'manage_students'], ['label' => 'Admissions Overview', 'url' => 'manage_students_admissions'], ['label' => 'Performance Overview', 'url' => 'student_performance'], ['label' => 'Discipline Overview', 'url' => 'discipline_cases'], ['label' => 'Special Needs', 'url' => 'special_needs'], ]], school admin.........// STUDENTS — manage all student records ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'manage_students'], ['label' => 'Student Profiles', 'url' => 'student_profiles'], ['label' => 'ID Cards', 'url' => 'student_id_cards'], ['label' => 'Family Groups', 'url' => 'manage_family_groups'], ['label' => 'Special Needs', 'url' => 'special_needs'], ['label' => 'Student Promotion', 'url' => 'student_promotion'], // end-of-year promotion ]], Headteacher - // STUDENTS ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'manage_students'], ['label' => 'Performance Overview', 'url' => 'student_performance'], ['label' => 'Discipline Cases', 'url' => 'discipline_cases'], ['label' => 'Counseling', 'url' => 'student_counseling'], ['label' => 'Special Needs', 'url' => 'special_needs'], ['label' => 'Health Records', 'url' => 'student_health'], ]], Deputy Head (Academic) - // ── STUDENTS ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'all_students'], ['label' => 'Performance Overview', 'url' => 'student_performance'], ['label' => 'Student Promotion', 'url' => 'student_promotion'], ['label' => 'Special Needs', 'url' => 'special_needs'], ]], Cateress / Catering Manager - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'Boarding Students', 'url' => 'manage_students'], // to plan meal quantities ]], Boarding Master / Matron / Housemother - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Boarding Students', 'url' => 'manage_students'], ['label' => 'Student Profiles', 'url' => 'student_profiles'], ['label' => 'Special Needs', 'url' => 'special_needs'], ]], Talent Development / HoD Activities - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'manage_students'], ['label' => 'Participant Registration','url' => 'manage_activities'], ['label' => 'Achievement Records', 'url' => 'manage_activities'], ]], Driver - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-users', 'subitems' => [ ['label' => 'My Passengers', 'url' => 'manage_students'], ['label' => 'Passenger Attendance', 'url' => 'mark_attendance'], ]], Chaplain / School Counselor - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'Student Profiles', 'url' => 'student_profiles'], ['label' => 'Welfare Records', 'url' => 'student_counseling'], ]], Deputy Head (Discipline) - ['label' => 'Students', 'url' => null, 'icon' => 'fas fa-user-graduate', 'subitems' => [ ['label' => 'All Students', 'url' => 'all_students'], ['label' => 'Student Profiles', 'url' => 'all_students'], ['label' => 'Special Needs', 'url' => 'special_needs'], ]],


and am getting this error on the browser

Authentication system not loaded. Please refresh the page.
