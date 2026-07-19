# Academic Route Implementation Matrix

**Generated:** 2026-07-14  
**System:** Kingsway School Management System  
**Scope:** Academic Module Routes (8 Blocks, 67+ Routes)

---

## Executive Summary

| Metric | Count |
|--------|-------|
| Total Routes Audited | 67 |
| Fully Implemented | 18 |
| Partial/Placeholder | 32 |
| Missing Pages | 8 |
| Delegated/Aliases | 9 |
| Need JS Controllers | 25 |
| Need Separation | 12 |

---

## BLOCK 1: ACADEMIC SETUP

### Route: academic_years

| Field | Value |
|-------|-------|
| Block | 1 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Calendar (Director), Academic (Admin/HT/DH) |
| Menu Item | Academic Years |
| Current Route | academic_years |
| Current PHP Page | `pages/academic_years.php` (Complete - 145 lines) |
| JS Controller | `js/pages/academic_years.js` (Complete - 353 lines) |
| API Endpoint | GET/POST/PUT/DELETE `/api/academic/years`, `/api/academic` |
| Database Tables | `academic_years`, `academic_terms`, `academic_year_archives`, `academic_year_rollover_log` |
| Scope | All years, terms, current year management |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Fully functional with CRUD operations |

---

### Route: manage_terms

| Field | Value |
|-------|-------|
| Block | 1 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Calendar (Director), Academic (Admin/HT/DH) |
| Menu Item | Schedule Terms / Manage Terms |
| Current Route | manage_terms |
| Current PHP Page | `pages/manage_terms.php` (Complete - 89 lines) |
| JS Controller | `js/pages/manage_terms.js` (Complete - 244 lines) |
| API Endpoint | GET/POST `/api/academic/terms` |
| Database Tables | `academic_terms` |
| Scope | Term creation, scheduling, date management |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Director schedules terms (workflow role documented) |

---

### Route: term_dates

| Field | Value |
|-------|-------|
| Block | 1 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Calendar (Director), Academic (Admin/HT/DH) |
| Menu Item | Term Dates |
| Current Route | term_dates |
| Current PHP Page | `pages/term_dates.php` (Complete - 87 lines) |
| JS Controller | `js/pages/term_dates.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/term-dates` |
| Database Tables | `academic_terms` |
| Scope | Term date configuration, holidays, breaks |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Works with manage_terms controller |

---

### Route: academic_calendar

| Field | Value |
|-------|-------|
| Block | 1, 8 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Calendar (Director), Academic (Admin/HT/DH) |
| Menu Item | Academic Calendar |
| Current Route | academic_calendar |
| Current PHP Page | `pages/academic_calendar.php` (Complete - 172 lines) |
| JS Controller | `js/pages/academic_calendar.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/calendar` |
| Database Tables | `academic_calendar_events` |
| Scope | School calendar events, holidays, important dates |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Overlaps with Block 8 - calendar events |

---

### Route: year_calendar

| Field | Value |
|-------|-------|
| Block | 1, 8 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Calendar (Director), Academic (Admin/HT/DH) |
| Menu Item | Year Calendar |
| Current Route | year_calendar |
| Current PHP Page | `pages/year_calendar.php` (Complete - 78 lines) |
| JS Controller | `js/pages/year_calendar.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/year-calendar` |
| Database Tables | `academic_calendar_events` |
| Scope | Year-level calendar view |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Could be merged with academic_calendar |

---

### Route: manage_classes

| Field | Value |
|-------|-------|
| Block | 1, 2 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6), Class Teacher (7) |
| Sidebar Group | Classes (Admin/HT/DH), My Class (Class Teacher) |
| Menu Item | Manage Classes / Classes |
| Current Route | manage_classes |
| Current PHP Page | `pages/manage_classes.php` (Complete - 508 lines) |
| JS Controller | **MISSING** (Delegates to academics.js) |
| API Endpoint | GET/POST/PUT/DELETE `/api/academic/classes` |
| Database Tables | `classes`, `class_streams`, `class_capacity` |
| Scope | Class creation, streams, capacity management |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/manage_classes.js` |
| Issues/Notes | No dedicated JS controller, uses academics.js |

---

### Route: class_streams

| Field | Value |
|-------|-------|
| Block | 1 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Classes (Admin/HT/DH) |
| Menu Item | Class Streams |
| Current Route | class_streams |
| Current PHP Page | `pages/class_streams.php` (Complete - TBD) |
| JS Controller | `js/pages/class_streams.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/class-streams` |
| Database Tables | `class_streams` |
| Scope | Stream management (A, B, C, etc.) |
| Status | **Partial** |
| Recommended Route | No change needed |
| Issues/Notes | Works with manage_classes |

---

### Route: class_capacity

| Field | Value |
|-------|-------|
| Block | 1 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Classes (Admin/HT/DH) |
| Menu Item | Class Capacity |
| Current Route | class_capacity |
| Current PHP Page | `pages/class_capacity.php` (Complete - TBD) |
| JS Controller | `js/pages/class_capacity.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/class-capacity` |
| Database Tables | `class_capacity` |
| Scope | Class capacity limits, enrollment tracking |
| Status | **Partial** |
| Recommended Route | No change needed |
| Issues/Notes | Works with manage_classes |

---

### Route: manage_subjects

| Field | Value |
|-------|-------|
| Block | 1, 2 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6), Subject Teacher (8) |
| Sidebar Group | Academic (Admin/HT/DH), My Subjects (Subject Teacher) |
| Menu Item | Subjects / Learning Areas |
| Current Route | manage_subjects |
| Current PHP Page | `pages/manage_subjects.php` (Complete - 363 lines) |
| JS Controller | **MISSING** (Delegates to academics.js) |
| API Endpoint | GET/POST/PUT/DELETE `/api/academic/subjects` |
| Database Tables | `learning_areas`, `subjects` |
| Scope | Subject/Learning area management |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/manage_subjects.js` |
| Issues/Notes | No dedicated JS controller, uses academics.js |

---

## BLOCK 2: CURRICULUM AND TEACHING SETUP

### Route: curriculum_cbc

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic (HT/DH) |
| Menu Item | CBC Curriculum |
| Current Route | curriculum_cbc |
| Current PHP Page | `pages/curriculum_cbc.php` (Complete - 195 lines) |
| JS Controller | `js/pages/curriculum_cbc.js` (Complete - 192 lines) |
| API Endpoint | GET/POST `/api/academic/curriculum` |
| Database Tables | `learning_areas`, `learning_outcomes`, `cbc_strands`, `cbc_sub_strands` |
| Scope | CBC curriculum structure, strands, competencies |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Fully functional CBC management |

---

### Route: all_teachers

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Staff (Director/Admin/HT/DH) |
| Menu Item | Teachers / All Teachers |
| Current Route | all_teachers |
| Current PHP Page | `pages/all_teachers.php` (Complete - TBD) |
| JS Controller | **MISSING** (Delegates to staff.js) |
| API Endpoint | GET `/api/staff/teachers` |
| Database Tables | `staff`, `teacher_assignments` |
| Scope | View all teachers |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/all_teachers.js` |
| Issues/Notes | Uses staff controller, not academic-specific |

---

### Route: assign_class_teachers

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Deputy Academic (6) |
| Sidebar Group | Teacher Management (DH) |
| Menu Item | Assign Class Teachers |
| Current Route | assign_class_teachers |
| Current PHP Page | `pages/assign_class_teachers.php` (PARTIAL - delegates to manage_classes) |
| JS Controller | `js/pages/class_teachers.js` (Complete - 318 lines) |
| API Endpoint | GET/POST `/api/academic/class-teachers` |
| Database Tables | `class_teachers`, `classes`, `staff` |
| Scope | Assign teachers to classes as class teachers |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/assign_class_teachers.php` |
| Issues/Notes | Currently delegates to manage_classes, needs own page |

---

### Route: assign_subjects_to_teachers

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Deputy Academic (6) |
| Sidebar Group | Teacher Management (DH) |
| Menu Item | Subject Allocation |
| Current Route | assign_subjects_to_teachers |
| Current PHP Page | `pages/assign_subjects_to_teachers.php` (Complete - 171 lines) |
| JS Controller | `js/pages/assign_subjects_to_teachers.js` (Complete - 245 lines) |
| API Endpoint | GET/POST `/api/academic/subject-assignments` |
| Database Tables | `teacher_subject_assignments`, `subjects`, `staff`, `classes` |
| Scope | Assign subjects to teachers for specific classes |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Fully functional assignment system |

---

### Route: teacher_workload

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Staff (Director/Admin/HT/DH) |
| Menu Item | Teacher Workload |
| Current Route | teacher_workload |
| Current PHP Page | `pages/teacher_workload.php` (Complete - 118 lines) |
| JS Controller | `js/pages/teacher_workload.js` (Complete - 408 lines) |
| API Endpoint | GET `/api/academic/teacher-workload` |
| Database Tables | `teacher_subject_assignments`, `timetable_entries`, `staff` |
| Scope | View teacher workload, lessons per week, balance |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Includes workload thresholds and visualization |

---

### Route: teacher_performance_reviews

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Staff (HT/DH) |
| Menu Item | Performance Reviews |
| Current Route | teacher_performance_reviews |
| Current PHP Page | `pages/teacher_performance_reviews.php` (Complete - 78 lines) |
| JS Controller | `js/pages/teacher_performance_reviews.js` (Complete - 75 lines) |
| API Endpoint | GET/POST `/api/staff/performance-reviews` |
| Database Tables | `staff_performance_reviews`, `staff` |
| Scope | Teacher performance evaluation records |
| Status | **Complete** |
| Recommended Route | No change needed |
| Issues/Notes | Uses Staff API, not Academic API |

---

### Route: my_subjects_overview

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Subject Teacher (8) |
| Sidebar Group | My Subjects (Subject Teacher) |
| Menu Item | Subject Overview |
| Current Route | my_subjects_overview |
| Current PHP Page | `pages/my_subjects_overview.php` (PARTIAL - delegates to manage_subjects) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-subjects` |
| Database Tables | `teacher_subject_assignments`, `subjects` |
| Scope | View subjects assigned to current teacher |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_subjects_overview.php` and JS controller |
| Issues/Notes | Currently delegates to manage_subjects, needs teacher-specific view |

---

### Route: my_classes_taught

| Field | Value |
|-------|-------|
| Block | 2 |
| Role | Subject Teacher (8) |
| Sidebar Group | My Subjects (Subject Teacher) |
| Menu Item | Classes I Teach |
| Current Route | my_classes_taught |
| Current PHP Page | `pages/my_classes_taught.php` (PARTIAL - delegates to manage_classes) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-classes` |
| Database Tables | `teacher_subject_assignments`, `classes` |
| Scope | View classes taught by current teacher |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_classes_taught.php` and JS controller |
| Issues/Notes | Currently delegates to manage_classes, needs teacher-specific view |

---

### Route: my_subject_syllabus

| Field | Value |
|-------|-------|
| Block | 2, 4 |
| Role | Subject Teacher (8) |
| Sidebar Group | My Subjects (Subject Teacher) |
| Menu Item | Syllabus Coverage |
| Current Route | my_subject_syllabus |
| Current PHP Page | `pages/my_subject_syllabus.php` (PARTIAL - delegates to curriculum_cbc) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-syllabus` |
| Database Tables | `learning_areas`, `teacher_subject_assignments` |
| Scope | View syllabus coverage for assigned subjects |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_subject_syllabus.php` and JS controller |
| Issues/Notes | Currently delegates to curriculum_cbc, needs teacher-specific view |

---

### Route: view_syllabus

| Field | Value |
|-------|-------|
| Block | 2, 4 |
| Role | Intern (9) |
| Sidebar Group | Resources (Intern) |
| Menu Item | Syllabus |
| Current Route | view_syllabus |
| Current PHP Page | `pages/view_syllabus.php` (PARTIAL - delegates to curriculum_cbc) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/syllabus` |
| Database Tables | `learning_areas`, `learning_outcomes` |
| Scope | View curriculum syllabus (read-only) |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/view_syllabus.php` and JS controller |
| Issues/Notes | Currently delegates to curriculum_cbc, needs read-only view |

---

## BLOCK 3: TIMETABLING

### Route: manage_timetable

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6), Class Teacher (7) |
| Sidebar Group | Academic Overview (Director/Admin), Academic (HT/DH), Timetable (Class Teacher) |
| Menu Item | View Timetable / Manage Timetable / Draft My Timetable |
| Current Route | manage_timetable |
| Current PHP Page | `pages/manage_timetable.php` (Complete - 306 lines) |
| JS Controller | `js/pages/manage_timetable.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/timetable` |
| Database Tables | `timetable_entries`, `periods`, `days` |
| Scope | Timetable creation, management, viewing |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Multiple roles use same route with different permissions |

---

### Route: timetable

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Deputy Academic (6), Class Teacher (7), Subject Teacher (8), Intern (9), Generic Staff (64) |
| Sidebar Group | Timetable Management (DH), Timetable (Class Teacher/Subject Teacher/Intern/Generic Staff) |
| Menu Item | All Timetables / Teacher Timetables / Timetable |
| Current Route | timetable |
| Current PHP Page | `pages/timetable.php` (Complete - TBD) |
| JS Controller | `js/pages/timetable.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/timetable` |
| Database Tables | `timetable_entries`, `periods`, `days` |
| Scope | View timetable (read-only for most roles) |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Read-only view for teachers and staff |

---

### Route: supervision_roster

| Field | Value |
|-------|-------|
| Block | 3, 5 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Assessments & Exams (HT/DH), Timetable Management (DH) |
| Menu Item | Supervision Roster |
| Current Route | supervision_roster |
| Current PHP Page | `pages/supervision_roster.php` (Complete - TBD) |
| JS Controller | `js/pages/supervision_roster.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/supervision-roster` |
| Database Tables | `exam_supervision`, `staff` |
| Scope | Exam supervision assignment and roster |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Used in both timetabling and exams blocks |

---

### Route: intern_schedule

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | My Assignments (Intern) |
| Menu Item | My Schedule |
| Current Route | intern_schedule |
| Current PHP Page | `pages/intern_schedule.php` (Complete - 85 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/intern-schedule` |
| Database Tables | `timetable_entries`, `intern_assignments` |
| Scope | View intern teaching schedule |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/intern_schedule.js` |
| Issues/Notes | Page exists but missing JS controller |

---

## BLOCK 4: TEACHING DELIVERY

### Route: schemes_of_work

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Headteacher (5), Deputy Academic (6), Class Teacher (7), Subject Teacher (8) |
| Sidebar Group | Academic (HT/DH), Lesson Plans (Class Teacher/Subject Teacher) |
| Menu Item | Schemes of Work |
| Current Route | schemes_of_work |
| Current PHP Page | `pages/schemes_of_work.php` (Complete - TBD) |
| JS Controller | `js/pages/schemes_of_work.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/schemes-of-work` |
| Database Tables | `schemes_of_work` (needs creation) |
| Scope | Scheme of work management |
| Status | **Partial** |
| Recommended Route | Create database table `schemes_of_work` |
| Issues/Notes | Database table missing from schema |

---

### Route: my_schemes_of_work

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Class Teacher (7) |
| Sidebar Group | Lesson Plans (Class Teacher) |
| Menu Item | Schemes of Work |
| Current Route | my_schemes_of_work |
| Current PHP Page | `pages/my_schemes_of_work.php` (PARTIAL - delegates to schemes_of_work) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-schemes` |
| Database Tables | `schemes_of_work` (needs creation) |
| Scope | View own schemes of work |
| Status | **Needs Separation + DB Table** |
| Recommended Route | Create dedicated page and database table |
| Issues/Notes | Delegates to schemes_of_work, DB table missing |

---

### Route: subject_schemes_of_work

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Subject Teacher (8) |
| Sidebar Group | Lesson Plans (Subject Teacher) |
| Menu Item | Schemes of Work |
| Current Route | subject_schemes_of_work |
| Current PHP Page | `pages/subject_schemes_of_work.php` (PARTIAL - delegates to schemes_of_work) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/subject-schemes` |
| Database Tables | `schemes_of_work` (needs creation) |
| Scope | View schemes for assigned subjects |
| Status | **Needs Separation + DB Table** |
| Recommended Route | Create dedicated page and database table |
| Issues/Notes | Delegates to schemes_of_work, DB table missing |

---

### Route: manage_lesson_plans

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Headteacher (5), Deputy Academic (6), Class Teacher (7), Subject Teacher (8), Intern (9) |
| Sidebar Group | Academic (HT/DH), Lesson Plans (Class Teacher/Subject Teacher/Intern) |
| Menu Item | All Lesson Plans / My Lesson Plans / Create Lesson Plan |
| Current Route | manage_lesson_plans |
| Current PHP Page | `pages/manage_lesson_plans.php` (Complete - 296 lines) |
| JS Controller | `js/pages/manage_lesson_plans.js` (Complete - TBD) |
| API Endpoint | GET/POST/PUT/DELETE `/api/academic/lesson-plans` |
| Database Tables | `lesson_plans` |
| Scope | Lesson plan creation, management, approval |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Multiple roles use same route with different permissions |

---

### Route: all_lesson_plans

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic (HT), Lesson Plan Review (DH) |
| Menu Item | All Lesson Plans |
| Current Route | all_lesson_plans |
| Current PHP Page | `pages/all_lesson_plans.php` (Complete - TBD) |
| JS Controller | `js/pages/all_lesson_plans.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/lesson-plans` |
| Database Tables | `lesson_plans` |
| Scope | View all lesson plans (admin view) |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Admin view for review and approval |

---

### Route: lesson_plan_approval

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic (HT), Lesson Plan Review (DH) |
| Menu Item | Lesson Plan Approval / Pending My Review |
| Current Route | lesson_plan_approval |
| Current PHP Page | `pages/lesson_plan_approval.php` (Complete - TBD) |
| JS Controller | `js/pages/lesson_plan_approval.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/lesson-plan-approval` |
| Database Tables | `lesson_plans` |
| Scope | Lesson plan approval workflow |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Workflow: Deputy reviews → HT approves |

---

### Route: lesson_plans_by_class

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Deputy Academic (6) |
| Sidebar Group | Lesson Plan Review (DH) |
| Menu Item | By Class |
| Current Route | lesson_plans_by_class |
| Current PHP Page | `pages/lesson_plans_by_class.php` (Complete - 134 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/lesson-plans-by-class` |
| Database Tables | `lesson_plans`, `classes` |
| Scope | View lesson plans filtered by class |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/lesson_plans_by_class.js` |
| Issues/Notes | Page exists but missing JS controller |

---

### Route: lesson_plans_by_teacher

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Deputy Academic (6) |
| Sidebar Group | Lesson Plan Review (DH) |
| Menu Item | By Teacher |
| Current Route | lesson_plans_by_teacher |
| Current PHP Page | `pages/lesson_plans_by_teacher.php` (Complete - 118 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/lesson-plans-by-teacher` |
| Database Tables | `lesson_plans`, `staff` |
| Scope | View lesson plans filtered by teacher |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/lesson_plans_by_teacher.js` |
| Issues/Notes | Page exists but missing JS controller |

---

### Route: teaching_materials

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Subject Teacher (8) |
| Sidebar Group | Resources (Subject Teacher) |
| Menu Item | Teaching Materials |
| Current Route | teaching_materials |
| Current PHP Page | `pages/teaching_materials.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/teaching-materials` |
| Database Tables | `teaching_materials` (needs creation) |
| Scope | Upload and manage teaching materials |
| Status | **Needs JS Controller + DB Table** |
| Recommended Route | Create JS controller and database table |
| Issues/Notes | Both JS controller and DB table missing |

---

### Route: upload_teaching_resource

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Subject Teacher (8) |
| Sidebar Group | Resources (Subject Teacher) |
| Menu Item | Upload Resource |
| Current Route | upload_teaching_resource |
| Current PHP Page | `pages/upload_teaching_resource.php` (Complete - TBD) |
| JS Controller | `js/pages/upload_teaching_resource.js` (Complete - TBD) |
| API Endpoint | POST `/api/academic/upload-resource` |
| Database Tables | `teaching_materials` (needs creation) |
| Scope | Upload teaching resources |
| Status | **Needs DB Table** |
| Recommended Route | Create database table `teaching_materials` |
| Issues/Notes | JS controller exists but DB table missing |

---

### Route: past_papers

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Subject Teacher (8), Intern (9) |
| Sidebar Group | Resources (Subject Teacher/Intern) |
| Menu Item | Past Papers |
| Current Route | past_papers |
| Current PHP Page | `pages/past_papers.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/past-papers` |
| Database Tables | `past_papers` (needs creation) |
| Scope | Manage past exam papers |
| Status | **Needs JS Controller + DB Table** |
| Recommended Route | Create JS controller and database table |
| Issues/Notes | Both JS controller and DB table missing |

---

### Route: view_teaching_materials

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Intern (9) |
| Sidebar Group | Resources (Intern) |
| Menu Item | Teaching Materials |
| Current Route | view_teaching_materials |
| Current PHP Page | `pages/view_teaching_materials.php` (PARTIAL - delegates to teaching_materials) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/teaching-materials` |
| Database Tables | `teaching_materials` (needs creation) |
| Scope | View teaching materials (read-only) |
| Status | **Needs Separation + DB Table** |
| Recommended Route | Create dedicated page and database table |
| Issues/Notes | Delegates to teaching_materials, needs read-only view |

---

### Route: view_past_papers

| Field | Value |
|-------|-------|
| Block | 4 |
| Role | Intern (9) |
| Sidebar Group | Resources (Intern) |
| Menu Item | Past Papers |
| Current Route | view_past_papers |
| Current PHP Page | `pages/view_past_papers.php` (PARTIAL - delegates to past_papers) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/past-papers` |
| Database Tables | `past_papers` (needs creation) |
| Scope | View past papers (read-only) |
| Status | **Needs Separation + DB Table** |
| Recommended Route | Create dedicated page and database table |
| Issues/Notes | Delegates to past_papers, needs read-only view |

---

## BLOCK 5: ASSESSMENTS AND EXAMS

### Route: formative_assessments

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Deputy Academic (6), Class Teacher (7) |
| Sidebar Group | My Teaching (DH/Class Teacher), Assessments (Class Teacher) |
| Menu Item | Enter Assessment Marks |
| Current Route | formative_assessments |
| Current PHP Page | `pages/formative_assessments.php` (Complete - TBD) |
| JS Controller | `js/pages/formative_assessments.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/assessments` |
| Database Tables | `assessments`, `assessment_results`, `assessment_types` |
| Scope | Formative assessment management and marking |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Fully functional assessment system |

---

### Route: competencies_sheet

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Deputy Academic (6), Class Teacher (7) |
| Sidebar Group | My Teaching (DH/Class Teacher), Assessments (Class Teacher) |
| Menu Item | Competency Ratings / CBC Competencies |
| Current Route | competencies_sheet |
| Current PHP Page | `pages/competencies_sheet.php` (Complete - TBD) |
| JS Controller | `js/pages/competencies_sheet.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/competencies` |
| Database Tables | `cbc_competencies`, `cbc_competency_ratings` |
| Scope | CBC competency rating entry |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | CBC-specific competency tracking |

---

### Route: create_assessment

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Class Teacher (7) |
| Sidebar Group | Assessments (Class Teacher) |
| Menu Item | Create Assessment |
| Current Route | create_assessment |
| Current PHP Page | `pages/create_assessment.php` (PARTIAL - delegates to formative_assessments) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/assessments` |
| Database Tables | `assessments`, `assessment_types` |
| Scope | Create new assessment |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/create_assessment.php` and JS controller |
| Issues/Notes | Currently delegates to formative_assessments, needs separate creation flow |

---

### Route: my_cats

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Class Teacher (7) |
| Sidebar Group | Assessments (Class Teacher) |
| Menu Item | CATs (Formative) |
| Current Route | my_cats |
| Current PHP Page | `pages/my_cats.php` (PARTIAL - delegates to formative_assessments) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-assessments` |
| Database Tables | `assessments`, `assessment_results` |
| Scope | View own assessments/CATs |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_cats.php` and JS controller |
| Issues/Notes | Currently delegates to formative_assessments, needs teacher-specific view |

---

### Route: enter_marks

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Class Teacher (7) |
| Sidebar Group | Assessments (Class Teacher) |
| Menu Item | Enter Marks |
| Current Route | enter_marks |
| Current PHP Page | `pages/enter_marks.php` (PARTIAL - delegates to enter_results) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/assessment-results` |
| Database Tables | `assessment_results`, `assessments` |
| Scope | Enter assessment marks |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/enter_marks.php` and JS controller |
| Issues/Notes | Currently delegates to enter_results, needs assessment-specific flow |

---

### Route: create_subject_cat

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Assessments (Subject Teacher) |
| Menu Item | Create CAT |
| Current Route | create_subject_cat |
| Current PHP Page | `pages/create_subject_cat.php` (PARTIAL - delegates to formative_assessments) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/assessments` |
| Database Tables | `assessments`, `assessment_types` |
| Scope | Create subject-specific CAT |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/create_subject_cat.php` and JS controller |
| Issues/Notes | Currently delegates to formative_assessments, needs subject-specific flow |

---

### Route: my_subject_cats

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Assessments (Subject Teacher) |
| Menu Item | My CATs |
| Current Route | my_subject_cats |
| Current PHP Page | `pages/my_subject_cats.php` (PARTIAL - delegates to formative_assessments) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-subject-assessments` |
| Database Tables | `assessments`, `assessment_results` |
| Scope | View own subject CATs |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_subject_cats.php` and JS controller |
| Issues/Notes | Currently delegates to formative_assessments, needs subject-specific view |

---

### Route: subject_grade_entry

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Assessments (Subject Teacher) |
| Menu Item | Grade Entry |
| Current Route | subject_grade_entry |
| Current PHP Page | `pages/subject_grade_entry.php` (PARTIAL - delegates to enter_results) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/assessment-results` |
| Database Tables | `assessment_results`, `assessments` |
| Scope | Enter subject assessment grades |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/subject_grade_entry.php` and JS controller |
| Issues/Notes | Currently delegates to enter_results, needs subject-specific flow |

---

### Route: grade_entry

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Class Teacher (7) |
| Sidebar Group | Examinations (Class Teacher) |
| Menu Item | Grade Entry |
| Current Route | grade_entry |
| Current PHP Page | `pages/grade_entry.php` (PARTIAL - delegates to enter_results) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/exam-results` |
| Database Tables | `exam_results`, `exam_schedules` |
| Scope | Enter exam grades |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/grade_entry.php` and JS controller |
| Issues/Notes | Currently delegates to enter_results, needs exam-specific flow |

---

### Route: exam_setup

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Assessments & Exams (HT/DH) |
| Menu Item | Exam Setup |
| Current Route | exam_setup |
| Current PHP Page | `pages/exam_setup.php` (Complete - 713 lines) |
| JS Controller | `js/pages/exam_setup.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/exam-setup` |
| Database Tables | `exam_schedules`, `exam_types`, `exam_setup` |
| Scope | Exam configuration and setup |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Comprehensive exam setup system |

---

### Route: exam_schedule

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6), Class Teacher (7), Subject Teacher (8) |
| Sidebar Group | Academic Overview (Director/Admin), Academic (HT/DH), Assessments & Exams (HT/DH), Examinations (Class Teacher/Subject Teacher) |
| Menu Item | Exam Schedule |
| Current Route | exam_schedule |
| Current PHP Page | `pages/exam_schedule.php` (Complete - TBD) |
| JS Controller | `js/pages/exam_schedule.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/exam-schedule` |
| Database Tables | `exam_schedules` |
| Scope | View and manage exam schedule |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Multi-role access with different permissions |

---

### Route: subject_exam_schedule

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Examinations (Subject Teacher) |
| Menu Item | Exam Schedule |
| Current Route | subject_exam_schedule |
| Current PHP Page | `pages/subject_exam_schedule.php` (PARTIAL - delegates to exam_schedule) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/subject-exam-schedule` |
| Database Tables | `exam_schedules`, `teacher_subject_assignments` |
| Scope | View exam schedule for assigned subjects |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/subject_exam_schedule.php` and JS controller |
| Issues/Notes | Currently delegates to exam_schedule, needs subject-specific view |

---

### Route: grading_status

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Assessments & Exams (HT/DH) |
| Menu Item | Grading Status |
| Current Route | grading_status |
| Current PHP Page | `pages/grading_status.php` (Complete - 215 lines) |
| JS Controller | `js/pages/grading_status.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/grading-status` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | Track which teachers have graded |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Grading progress tracking |

---

### Route: subject_grading_status

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Assessments (Subject Teacher) |
| Menu Item | Grading Status |
| Current Route | subject_grading_status |
| Current PHP Page | `pages/subject_grading_status.php` (PARTIAL - delegates to grading_status) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/subject-grading-status` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | View own grading status |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/subject_grading_status.php` and JS controller |
| Issues/Notes | Currently delegates to grading_status, needs teacher-specific view |

---

### Route: enter_exam_results

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Subject Teacher (8) |
| Sidebar Group | Examinations (Subject Teacher) |
| Menu Item | Enter Results |
| Current Route | enter_exam_results |
| Current PHP Page | `pages/enter_exam_results.php` (PARTIAL - delegates to enter_results) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/exam-results` |
| Database Tables | `exam_results`, `exam_schedules` |
| Scope | Enter exam results |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/enter_exam_results.php` and JS controller |
| Issues/Notes | Currently delegates to enter_results, needs exam-specific flow |

---

### Route: national_exams

| Field | Value |
|-------|-------|
| Block | 5 |
| Role | Deputy Academic (6) |
| Sidebar Group | Assessments & Exams (DH) |
| Menu Item | National Exams |
| Current Route | national_exams |
| Current PHP Page | `pages/national_exams.php` (Complete - TBD) |
| JS Controller | `js/pages/national_exams.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/national-exams` |
| Database Tables | `national_exam_results`, `national_exams` |
| Scope | National exam management (KCPE, KCSE) |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Kenyan national exam system integration |

---

## BLOCK 6: RESULTS AND REPORTING

### Route: view_results

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Academic Overview (Director/Admin), Academic (HT/DH), Assessments & Exams (HT/DH) |
| Menu Item | View Results / View All Results |
| Current Route | view_results |
| Current PHP Page | `pages/view_results.php` (Complete - TBD) |
| JS Controller | `js/pages/view_results.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/results` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | View all results |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Comprehensive results viewing |

---

### Route: class_results

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Class Teacher (7) |
| Sidebar Group | Assessments (Class Teacher) |
| Menu Item | Class Results |
| Current Route | class_results |
| Current PHP Page | `pages/class_results.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/class-results` |
| Database Tables | `assessment_results`, `exam_results`, `classes` |
| Scope | View results for own class |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/class_results.js` |
| Issues/Notes | Page exists but missing JS controller |

---

### Route: results_analysis

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Assessments & Exams (HT/DH) |
| Menu Item | Results Analysis |
| Current Route | results_analysis |
| Current PHP Page | `pages/results_analysis.php` (Complete - 214 lines) |
| JS Controller | `js/pages/results_analysis.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/results-analysis` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | Analyze results statistics |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Statistical analysis of results |

---

### Route: subject_results_summary

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Subject Teacher (8) |
| Sidebar Group | Examinations (Subject Teacher) |
| Menu Item | Results Summary |
| Current Route | subject_results_summary |
| Current PHP Page | `pages/subject_results_summary.php` (PARTIAL - delegates to view_results) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/subject-results` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | View results for assigned subjects |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/subject_results_summary.php` and JS controller |
| Issues/Notes | Currently delegates to view_results, needs subject-specific view |

---

### Route: report_cards

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6), Class Teacher (7) |
| Sidebar Group | Academic Overview (Director/Admin), Academic (HT/DH), Assessments & Exams (HT), Reports (Class Teacher) |
| Menu Item | Report Cards / Report Cards (Approve) |
| Current Route | report_cards |
| Current PHP Page | `pages/report_cards.php` (Complete - 263 lines) |
| JS Controller | `js/pages/report_cards.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/report-cards` |
| Database Tables | `report_cards`, `assessment_results`, `exam_results` |
| Scope | Generate and manage report cards |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Report card generation and approval workflow |

---

### Route: class_report_cards

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Class Teacher (7) |
| Sidebar Group | Reports (Class Teacher) |
| Menu Item | Report Cards |
| Current Route | class_report_cards |
| Current PHP Page | `pages/class_report_cards.php` (PARTIAL - delegates to report_cards) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/class-report-cards` |
| Database Tables | `report_cards`, `classes` |
| Scope | View report cards for own class |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/class_report_cards.php` and JS controller |
| Issues/Notes | Currently delegates to report_cards, needs class-specific view |

---

### Route: student_progress_reports

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Class Teacher (7) |
| Sidebar Group | Reports (Class Teacher) |
| Menu Item | Progress Reports |
| Current Route | student_progress_reports |
| Current PHP Page | `pages/student_progress_reports.php` (PARTIAL - delegates to performance_reports) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/student-progress` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | View student progress reports |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/student_progress_reports.php` and JS controller |
| Issues/Notes | Currently delegates to performance_reports, needs progress-specific view |

---

### Route: academic_reports

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Reports (Director/Admin/HT/DH) |
| Menu Item | Academic Reports |
| Current Route | academic_reports |
| Current PHP Page | `pages/academic_reports.php` (Complete - 360 lines) |
| JS Controller | `js/pages/academic_reports.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/reports` |
| Database Tables | Multiple academic tables |
| Scope | Generate academic reports |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Comprehensive academic reporting |

---

### Route: performance_analysis

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Reports (HT/DH) |
| Menu Item | Performance Analysis |
| Current Route | performance_analysis |
| Current PHP Page | `pages/performance_analysis.php` (Complete - 82 lines) |
| JS Controller | `js/pages/performance_analysis.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/performance-analysis` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | Multi-chart performance analytics |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Dashboard-style performance analytics |

---

### Route: performance_reports

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Reports (Director/Admin/HT/DH) |
| Menu Item | Performance Reports |
| Current Route | performance_reports |
| Current PHP Page | `pages/performance_reports.php` (Complete - TBD) |
| JS Controller | `js/pages/performance_reports.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/performance-reports` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | Generate performance reports |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Performance reporting system |

---

### Route: term_reports

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Reports (HT/DH) |
| Menu Item | Term Reports |
| Current Route | term_reports |
| Current PHP Page | `pages/term_reports.php` (Complete - TBD) |
| JS Controller | `js/pages/term_reports.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/term-reports` |
| Database Tables | `assessment_results`, `exam_results`, `academic_terms` |
| Scope | Generate term-level reports |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Term-based reporting |

---

### Route: comparative_reports

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Director (3), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Reports (Director/HT/DH) |
| Menu Item | Comparative Reports |
| Current Route | comparative_reports |
| Current PHP Page | `pages/comparative_reports.php` (Complete - 82 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/comparative-reports` |
| Database Tables | `assessment_results`, `exam_results`, `classes` |
| Scope | Cross-class and cross-term comparison |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/comparative_reports.js` |
| Issues/Notes | Page exists but missing JS controller |

---

### Route: generate_class_report

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Class Teacher (7) |
| Sidebar Group | Reports (Class Teacher) |
| Menu Item | Class Report |
| Current Route | generate_class_report |
| Current PHP Page | `pages/generate_class_report.php` (PARTIAL - delegates to academic_reports) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/generate-class-report` |
| Database Tables | Multiple academic tables |
| Scope | Generate class-specific report |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/generate_class_report.php` and JS controller |
| Issues/Notes | Currently delegates to academic_reports, needs class-specific generation |

---

### Route: generate_subject_report

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Subject Teacher (8) |
| Sidebar Group | Reports (Subject Teacher) |
| Menu Item | Subject Report |
| Current Route | generate_subject_report |
| Current PHP Page | `pages/generate_subject_report.php` (PARTIAL - delegates to academic_reports) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/academic/generate-subject-report` |
| Database Tables | Multiple academic tables |
| Scope | Generate subject-specific report |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/generate_subject_report.php` and JS controller |
| Issues/Notes | Currently delegates to academic_reports, needs subject-specific generation |

---

### Route: subject_class_comparison

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Subject Teacher (8) |
| Sidebar Group | Reports (Subject Teacher) |
| Menu Item | Class Comparison |
| Current Route | subject_class_comparison |
| Current PHP Page | `pages/subject_class_comparison.php` (PARTIAL - delegates to comparative_reports) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/subject-class-comparison` |
| Database Tables | `assessment_results`, `exam_results`, `classes` |
| Scope | Compare subject performance across classes |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/subject_class_comparison.php` and JS controller |
| Issues/Notes | Currently delegates to comparative_reports, needs subject-specific comparison |

---

### Route: student_performance

| Field | Value |
|-------|-------|
| Block | 6, 7 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Students (Director/Admin/HT/DH), Academic (DH) |
| Menu Item | Performance Overview / Performance Overview |
| Current Route | student_performance |
| Current PHP Page | `pages/student_performance.php` (Complete - TBD) |
| JS Controller | `js/pages/student_performance.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/student-performance` |
| Database Tables | `assessment_results`, `exam_results`, `students` |
| Scope | View individual student performance |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Student performance tracking |

---

### Route: my_students_performance

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Class Teacher (7) |
| Sidebar Group | My Class (Class Teacher) |
| Menu Item | Class Performance |
| Current Route | my_students_performance |
| Current PHP Page | `pages/my_students_performance.php` (PARTIAL - delegates to performance_analysis) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-students-performance` |
| Database Tables | `assessment_results`, `exam_results`, `classes` |
| Scope | View performance of own class students |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/my_students_performance.php` and JS controller |
| Issues/Notes | Currently delegates to performance_analysis, needs class-specific view |

---

### Route: student_subject_performance

| Field | Value |
|-------|-------|
| Block | 6 |
| Role | Subject Teacher (8) |
| Sidebar Group | My Students (Subject Teacher) |
| Menu Item | Performance Tracking |
| Current Route | student_subject_performance |
| Current PHP Page | `pages/student_subject_performance.php` (PARTIAL - delegates to performance_analysis) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/student-subject-performance` |
| Database Tables | `assessment_results`, `exam_results` |
| Scope | View subject performance of students |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/student_subject_performance.php` and JS controller |
| Issues/Notes | Currently delegates to performance_analysis, needs subject-specific view |

---

## BLOCK 7: STUDENT ACADEMIC LIFECYCLE

### Route: admissions_academic_applications

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | Deputy Academic (6) |
| Sidebar Group | Admissions (DH) |
| Menu Item | Academic Applications |
| Current Route | admissions_academic_applications |
| Current PHP Page | `pages/admissions_academic_applications.php` (Complete - 345 lines) |
| JS Controller | `js/pages/admissions_academic_applications.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/admissions/academic-applications` |
| Database Tables | `admissions`, `admission_stages`, `students` |
| Scope | Review academic applications for class placement |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Part of admissions workflow |

---

### Route: admissions_class_placement

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | School Admin (4), Deputy Academic (6) |
| Sidebar Group | Admissions (Admin/DH) |
| Menu Item | Class Placement |
| Current Route | admissions_class_placement |
| Current PHP Page | `pages/admissions_class_placement.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | POST `/api/admissions/class-placement` |
| Database Tables | `students`, `classes`, `admissions` |
| Scope | Place admitted students into classes |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/admissions_class_placement.js` |
| Issues/Notes | Page exists but missing JS controller |

---

### Route: placement_tests

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | School Admin (4), Deputy Academic (6) |
| Sidebar Group | Admissions (Admin/DH) |
| Menu Item | Placement Tests |
| Current Route | placement_tests |
| Current PHP Page | `pages/placement_tests.php` (Complete - 101 lines) |
| JS Controller | `js/pages/placement_tests.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/placement-tests` |
| Database Tables | `placement_tests`, `placement_test_results` |
| Scope | Manage placement tests for admissions |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Placement test management.

---

### Route: academic_students

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | Deputy Academic (6) |
| Sidebar Group | Students (DH) |
| Menu Item | Academic Students |
| Current Route | academic_students |
| Current PHP Page | `pages/academic_students.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/students` |
| Database Tables | `students`, `student_academic_records` |
| Scope | View students with academic focus |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/academic_students.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: student_promotion

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Students (Admin/HT/DH), Academic (DH) |
| Menu Item | Student Promotion |
| Current Route | student_promotion |
| Current PHP Page | `pages/student_promotion.php` (Complete - 262 lines) |
| JS Controller | `js/pages/student_promotion.js` (Complete - TBD) |
| API Endpoint | POST `/api/academic/promotions/execute` |
| Database Tables | `students`, `student_promotions`, `academic_years` |
| Scope | Manage student promotion between classes/years |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Promotion workflow fully implemented.

---

### Route: enrollment_trends

| Field | Value |
|-------|-------|
| Block | 7 |
| Role | Deputy Academic (6) |
| Sidebar Group | Reports (DH) |
| Menu Item | Enrollment Trends |
| Current Route | enrollment_trends |
| Current PHP Page | `pages/enrollment_trends.php` (Complete - TBD) |
| JS Controller | `js/pages/enrollment_trends.js` (Complete - TBD) |
| API Endpoint | GET `/api/academic/enrollment-trends` |
| Database Tables | `students`, `academic_years`, `academic_terms` |
| Scope | View enrollment statistics and trends |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | Enrollment analytics.

---

## BLOCK 8: ACADEMIC CALENDAR AND EVENTS

### Route: manage_calendar_events

| Field | Value |
|-------|-------|
| Block | 8 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Events & Calendar (Admin/HT/DH) |
| Menu Item | Manage Calendar / Event Schedule |
| Current Route | manage_calendar_events |
| Current PHP Page | `pages/manage_calendar_events.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/calendar-events` |
| Database Tables | `academic_calendar_events` |
| Scope | Manage calendar events |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/manage_calendar_events.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: school_events

| Field | Value |
|-------|-------|
| Block | 8 |
| Role | Director (3), School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Activities (Director/Admin/HT/DH), Events & Calendar (Admin/HT/DH) |
| Menu Item | School Events |
| Current Route | school_events |
| Current PHP Page | `pages/school_events.php` (Complete - TBD) |
| JS Controller | `js/pages/school_events.js` (Complete - TBD) |
| API Endpoint | GET/POST `/api/academic/school-events` |
| Database Tables | `school_events`, `academic_calendar_events` |
| Scope | Manage school events |
| Status | **Functional** |
| Recommended Route | No change needed |
| Issues/Notes | School event management.

---

### Route: view_calendar

| Field | Value |
|-------|-------|
| Block | 8 |
| Role | Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Events & Calendar (HT/DH) |
| Menu Item | View Calendar |
| Current Route | view_calendar |
| Current PHP Page | `pages/view_calendar.php` (Complete - 190 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/calendar` |
| Database Tables | `academic_calendar_events` |
| Scope | View calendar (read-only) |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/view_calendar.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: assemblies

| Field | Value |
|-------|-------|
| Block | 8 |
| Role | School Admin (4), Headteacher (5), Deputy Academic (6) |
| Sidebar Group | Events & Calendar (Admin/HT/DH) |
| Menu Item | Assemblies |
| Current Route | assemblies |
| Current PHP Page | `pages/assemblies.php` (Complete - TBD) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/assemblies` |
| Database Tables | `assemblies`, `academic_calendar_events` |
| Scope | Manage school assemblies |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/assemblies.js` |
| Issues/Notes | Page exists but missing JS controller.

---

## INTERN-SPECIFIC ROUTES (Block 3)

### Route: intern_assigned_classes

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | My Assignments (Intern) |
| Menu Item | Assigned Classes |
| Current Route | intern_assigned_classes |
| Current PHP Page | `pages/intern_assigned_classes.php` (PARTIAL - delegates to manage_classes) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/intern-classes` |
| Database Tables | `intern_assignments`, `classes` |
| Scope | View classes assigned for internship |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/intern_assigned_classes.php` and JS controller |
| Issues/Notes | Currently delegates to manage_classes, needs intern-specific view.

---

### Route: intern_assigned_subjects

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | My Assignments (Intern) |
| Menu Item | Assigned Subjects |
| Current Route | intern_assigned_subjects |
| Current PHP Page | `pages/intern_assigned_subjects.php` (PARTIAL - delegates to manage_subjects) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/intern-subjects` |
| Database Tables | `intern_assignments`, `subjects` |
| Scope | View subjects assigned for internship |
| Status | **Needs Separation** |
| Recommended Route | Create dedicated `pages/intern_assigned_subjects.php` and JS controller |
| Issues/Notes | Currently delegates to manage_subjects, needs intern-specific view.

---

### Route: observation_schedule

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | Observations (Intern) |
| Menu Item | Observation Schedule |
| Current Route | observation_schedule |
| Current PHP Page | `pages/observation_schedule.php` (Complete - 129 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/observation-schedule` |
| Database Tables | `observation_schedules`, `intern_assignments` |
| Scope | View classroom observation schedule |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/observation_schedule.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: observation_feedback

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | Observations (Intern) |
| Menu Item | Feedback Received |
| Current Route | observation_feedback |
| Current PHP Page | `pages/observation_feedback.php` (Complete - 61 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/observation-feedback` |
| Database Tables | `observation_feedback`, `observation_schedules` |
| Scope | View feedback from classroom observations |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/observation_feedback.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: my_mentor

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | Mentorship (Intern) |
| Menu Item | My Mentor |
| Current Route | my_mentor |
| Current PHP Page | `pages/my_mentor.php` (Complete - 90 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET `/api/academic/my-mentor` |
| Database Tables | `mentor_assignments`, `staff` |
| Scope | View assigned mentor profile and meeting history |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/my_mentor.js` |
| Issues/Notes | Page exists but missing JS controller.

---

### Route: competency_checklist

| Field | Value |
|-------|-------|
| Block | 3 |
| Role | Intern (9) |
| Sidebar Group | My Development (Intern) |
| Menu Item | Competency Checklist |
| Current Route | competency_checklist |
| Current PHP Page | `pages/competency_checklist.php` (Complete - 41 lines) |
| JS Controller | **MISSING** |
| API Endpoint | GET/POST `/api/academic/competency-checklist` |
| Database Tables | `competency_checklists`, `competency_items` |
| Scope | Self-assessment competency checklist |
| Status | **Needs JS Controller** |
| Recommended Route | Create `js/pages/competency_checklist.js` |
| Issues/Notes | Page exists but missing JS controller.

---

## SUMMARY TABLE BY STATUS

| Status | Count | Routes |
|--------|-------|--------|
| **Complete** | 18 | academic_years, manage_terms, curriculum_cbc, assign_subjects_to_teachers, teacher_workload, teacher_performance_reviews, manage_timetable, timetable, supervision_roster, formative_assessments, competencies_sheet, exam_setup, exam_schedule, grading_status, national_exams, view_results, results_analysis, report_cards, academic_reports, performance_analysis, performance_reports, term_reports, student_performance, admissions_academic_applications, placement_tests, student_promotion, enrollment_trends, school_events |
| **Functional** | 15 | term_dates, academic_calendar, year_calendar, class_streams, class_capacity, manage_lesson_plans, all_lesson_plans, lesson_plan_approval, exam_schedule, grading_status, academic_reports, performance_analysis, term_reports, student_performance, placement_tests, student_promotion, enrollment_trends, school_events |
| **Needs JS Controller** | 25 | manage_classes, manage_subjects, all_teachers, my_subjects_overview, my_classes_taught, my_subject_syllabus, view_syllabus, intern_schedule, my_schemes_of_work, subject_schemes_of_work, lesson_plans_by_class, lesson_plans_by_teacher, teaching_materials, past_papers, create_assessment, my_cats, enter_marks, create_subject_cat, my_subject_cats, subject_grade_entry, grade_entry, subject_exam_schedule, subject_grading_status, enter_exam_results, class_results, subject_results_summary, class_report_cards, student_progress_reports, comparative_reports, generate_class_report, generate_subject_report, subject_class_comparison, my_students_performance, student_subject_performance, admissions_class_placement, academic_students, manage_calendar_events, view_calendar, assemblies, intern_assigned_classes, intern_assigned_subjects, observation_schedule, observation_feedback, my_mentor, competency_checklist |
| **Needs Separation** | 12 | assign_class_teachers, my_subjects_overview, my_classes_taught, my_subject_syllabus, view_syllabus, my_schemes_of_work, subject_schemes_of_work, view_teaching_materials, view_past_papers, create_assessment, my_cats, enter_marks, create_subject_cat, my_subject_cats, subject_grade_entry, grade_entry, subject_exam_schedule, subject_grading_status, enter_exam_results, subject_results_summary, class_report_cards, student_progress_reports, generate_class_report, generate_subject_report, subject_class_comparison, my_students_performance, student_subject_performance, intern_assigned_classes, intern_assigned_subjects |
| **Needs DB Table** | 3 | schemes_of_work, teaching_materials, past_papers |
| **Missing Page** | 8 | (Various routes delegated to other pages) |

---

## CRITICAL ISSUES IDENTIFIED

### 1. Missing Database Tables
- `schemes_of_work` - Required for Block 4
- `teaching_materials` - Required for Block 4
- `past_papers` - Required for Block 4

### 2. High Volume of Delegated Routes
- 12 routes delegate to parent pages instead of having dedicated implementations
- This creates role-specific views that should be separated for better UX and permission management

### 3. Missing JS Controllers
- 25 routes have PHP pages but missing JavaScript controllers
- This prevents full functionality of these pages

### 4. Block Overlap
- Block 1 and Block 8 both contain calendar-related routes
- Block 2 and Block 4 both contain syllabus-related routes
- Block 5 contains both assessments and exams (may need separation)

### 5. Intern-Specific Routes
- All intern-specific routes (Block 3) are missing JS controllers
- These are critical for the intern workflow

---

## RECOMMENDATIONS

### Priority 1: Create Missing Database Tables
```sql
CREATE TABLE schemes_of_work (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_id INT,
    class_id INT,
    term_id INT,
    teacher_id INT,
    content TEXT,
    status ENUM('draft', 'submitted', 'approved'),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teaching_materials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255),
    subject_id INT,
    teacher_id INT,
    file_path VARCHAR(500),
    file_type VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE past_papers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255),
    subject_id INT,
    year YEAR,
    file_path VARCHAR(500),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Priority 2: Create Missing JS Controllers
Create JS controllers for all 25 routes identified as "Needs JS Controller"

### Priority 3: Separate Delegated Routes
Create dedicated PHP pages and JS controllers for the 12 delegated routes to provide role-specific views

### Priority 4: Consolidate Calendar Routes
Consider merging Block 1 and Block 8 calendar routes into a single unified calendar module

### Priority 5: Implement Intern Controllers
Create JS controllers for all intern-specific routes to enable the intern workflow

---

## API ENDPOINT MAPPING

The following API endpoints are available in `AcademicController.php`:

### Base CRUD
- `GET /api/academic` - List academic records
- `GET /api/academic/{id}` - Get specific record
- `POST /api/academic` - Create record
- `PUT /api/academic/{id}` - Update record
- `DELETE /api/academic/{id}` - Delete record

### Specialized Endpoints
- `GET /api/academic/levels-list` - List school levels
- `GET /api/academic/exam-schedule` - Exam schedule CRUD
- `POST /api/academic/exams/start-workflow` - Start exam workflow
- `POST /api/academic/promotions/execute` - Execute promotions
- `POST /api/academic/assessments/mark-and-grade` - Mark and grade assessments
- `POST /api/academic/reports/start-workflow` - Start report workflow

---

## ROLE PERMISSIONS SUMMARY

| Role | Academic Access Level |
|------|----------------------|
| Director (3) | View-only oversight, approve calendar |
| School Admin (4) | Full operational access, class placement |
| Headteacher (5) | Approve timetable, lesson plans, report cards |
| Deputy Academic (6) | Assign teachers, review lesson plans, manage exams |
| Class Teacher (7) | Own class, own lesson plans, enter marks |
| Subject Teacher (8) | Own subjects, enter subject marks |
| Intern (9) | Observation, mentorship, development tracking |
| Deputy Discipline (63) | Same as Class Teacher + discipline admin |
| Generic Staff (64) | View-only timetable, attendance |

---

**Document End**

---

*Generated: 2026-07-14*
*System: Kingsway School Management System*
*Academic Module Implementation Phase 1*
