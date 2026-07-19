# Academic Module Database Map

This document provides a comprehensive mapping of all academic-related database tables in the KingsWayAcademy database, organized by the 8 implementation blocks of the Academic Module.

## Overview

The database contains **44 academic-related tables** organized across the following blocks:

1. **Academic Setup** (8 tables)
2. **Curriculum and Teaching Setup** (9 tables)
3. **Timetabling** (3 tables)
4. **Teaching Delivery** (4 tables)
5. **Assessments and Exams** (8 tables)
6. **Results and Reporting** (3 tables)
7. **Student Academic Lifecycle** (3 tables)
8. **Academic Calendar and Events** (1 table)
9. **CBC Learner Assessment** (5 tables)

---

## BLOCK 1 — ACADEMIC SETUP

### Table: `academic_years`
**Purpose:** Core academic year management and lifecycle tracking

**Columns:**
- `id` (INT, PK, Auto-increment)
- `year_code` (VARCHAR) - Unique year identifier
- `year_name` (VARCHAR) - Display name (e.g., "2026")
- `start_date` (DATE) - Academic year start
- `end_date` (DATE) - Academic year end
- `registration_start` (DATE) - Student registration opens
- `registration_end` (DATE) - Student registration closes
- `status` (ENUM) - upcoming, active, closed, archived
- `is_current` (BOOLEAN) - Flag for current active year
- `total_students` (INT) - Denormalized count
- `total_classes` (INT) - Denormalized count
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `created_by` → `users(id)`

**Indexes:**
- `year_code` (UNIQUE)
- `status`
- `is_current`
- Date range indexes

**Triggers:**
- `trg_complete_staff_assignments_on_year_end` - Finalizes staff assignments when year closes

---

### Table: `academic_terms`
**Purpose:** Term management within academic years

**Columns:**
- `id` (INT, PK, Auto-increment)
- `academic_year_id` (INT, FK → academic_years.id)
- `name` (VARCHAR) - Term name (e.g., "Term 1")
- `start_date` (DATE)
- `end_date` (DATE)
- `midterm_break_start` (DATE)
- `midterm_break_end` (DATE)
- `opening_date` (DATE)
- `closing_date` (DATE)
- `year` (YEAR)
- `term_number` (INT) - 1, 2, 3, etc.
- `status` (ENUM) - upcoming, active, closed
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `academic_year_id` → `academic_years(id)`

**Indexes:**
- `uk_year_term` (UNIQUE on academic_year_id, term_number)
- `status_date`
- `fk_terms_academic_year`

**Triggers:**
- `trg_validate_academic_term` - Validates term dates fall within academic year

---

### Table: `academic_year_archives`
**Purpose:** Historical record of closed academic years

**Columns:**
- `id` (INT, PK, Auto-increment)
- `academic_year` (YEAR, UNIQUE)
- `status` (ENUM)
- `total_students` (INT)
- `promoted_count` (INT)
- `retained_count` (INT)
- `transferred_count` (INT)
- `graduated_count` (INT)
- `suspended_count` (INT)
- `closure_initiated_by` (INT, FK → users.id)
- `closure_date` (DATE)
- `closure_notes` (TEXT)
- `created_at` (TIMESTAMP)
- `archived_at` (TIMESTAMP)

**Foreign Keys:**
- `closure_initiated_by` → `users(id)`

**Indexes:**
- `uk_academic_year` (UNIQUE)
- `status`
- `academic_year_archives_ibfk_1`

---

### Table: `academic_year_rollover_log`
**Purpose:** Audit trail for year-to-year transitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `from_year` (YEAR)
- `to_year` (YEAR)
- `rollover_date` (DATE)
- `step` (VARCHAR) - Rollover phase identifier
- `students_reassigned` (INT)
- `staff_reassigned` (INT)
- `classes_rolled` (INT)
- `timetables_rolled` (INT)
- `fee_structures_rolled` (INT)
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `created_by` → `users(id)`

**Indexes:**
- `idx_from_to_years`
- `idx_step`
- `created_by`

---

### Table: `classes`
**Purpose:** Core class/grade definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR) - Class name (e.g., "Grade 5")
- `level_id` (INT, FK → school_levels.id)
- `teacher_id` (INT, FK → staff.id)
- `capacity` (INT) - Default capacity
- `room_number` (VARCHAR)
- `academic_year` (YEAR)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `level_id` → `school_levels(id)`
- `teacher_id` → `staff(id)`

**Indexes:**
- `uk_name_year` (UNIQUE on name, academic_year)
- `idx_level`
- `idx_teacher`
- `idx_status_year`

**Triggers:**
- `trg_auto_create_default_stream` - Creates default stream when class is created

---

### Table: `class_streams`
**Purpose:** Stream subdivisions within classes

**Columns:**
- `id` (INT, PK, Auto-increment)
- `class_id` (INT, FK → classes.id)
- `stream_name` (VARCHAR) - Stream name (e.g., "A", "B")
- `capacity` (INT) - Stream capacity
- `teacher_id` (INT, FK → staff.id)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)
- `current_students` (INT) - Denormalized count

**Foreign Keys:**
- `class_id` → `classes(id)`
- `teacher_id` → `staff(id)`

**Indexes:**
- `uk_class_stream` (UNIQUE on class_id, stream_name)
- `idx_teacher`
- `idx_status`

**Triggers:**
- `trg_validate_class_capacity` - Validates capacity constraints

---

### Table: `class_year_assignments`
**Purpose:** Annual class/stream assignments with teacher allocations

**Columns:**
- `id` (INT, PK, Auto-increment)
- `class_id` (INT, FK → classes.id)
- `stream_id` (INT, FK → class_streams.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `class_teacher_id` (INT, FK → staff.id)
- `assistant_teacher_id` (INT, FK → staff.id)
- `subject_teacher_id` (INT, FK → staff.id)
- `capacity` (INT)
- `current_enrollment` (INT)
- `room_id` (INT, FK → rooms.id)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `class_id` → `classes(id)`
- `stream_id` → `class_streams(id)`
- `academic_year_id` → `academic_years(id)`
- `class_teacher_id` → `staff(id)`
- `assistant_teacher_id` → `staff(id)`
- `subject_teacher_id` → `staff(id)`
- `room_id` → `rooms(id)`

**Indexes:**
- `uk_class_year_stream` (UNIQUE on class_id, academic_year_id, stream_id)
- `idx_academic_year`
- `idx_class_teacher`
- `idx_status`

---

### Table: `class_enrollments`
**Purpose:** Student enrollment records by academic year

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `class_id` (INT, FK → classes.id)
- `stream_id` (INT, FK → class_streams.id)
- `class_assignment_id` (INT, FK → class_year_assignments.id)
- `enrollment_date` (DATE)
- `enrollment_status` (ENUM) - active, transferred, withdrawn, graduated
- `term1_average` (DECIMAL)
- `term2_average` (DECIMAL)
- `term3_average` (DECIMAL)
- `year_average` (DECIMAL)
- `overall_grade` (VARCHAR)
- `class_rank` (INT)
- `stream_rank` (INT)
- `days_present` (INT)
- `days_absent` (INT)
- `days_late` (INT)
- `attendance_percentage` (DECIMAL)
- `teacher_comments` (TEXT)
- `head_teacher_comments` (TEXT)
- `special_notes` (TEXT)
- `promoted_to_class_id` (INT, FK → classes.id)
- `promoted_to_stream_id` (INT, FK → class_streams.id)
- `promotion_status` (ENUM) - pending, promoted, retained, transferred
- `promotion_date` (DATE)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)
- `completed_at` (TIMESTAMP)

**Foreign Keys:**
- `student_id` → `students(id)`
- `academic_year_id` → `academic_years(id)`
- `class_id` → `classes(id)`
- `stream_id` → `class_streams(id)`
- `class_assignment_id` → `class_year_assignments(id)`

**Indexes:**
- `unique_student_year` (UNIQUE on student_id, academic_year_id)
- `idx_student`
- `idx_academic_year`
- `idx_class_stream`
- `idx_assignment`
- `idx_enrollment_status`
- `idx_promotion_status`
- `idx_year_student`

**Triggers:**
- `after_enrollment_insert` - Updates class enrollment counts
- `after_enrollment_update` - Updates class enrollment counts on changes

---

## BLOCK 2 — CURRICULUM AND TEACHING SETUP

### Table: `learning_areas`
**Purpose:** Core learning areas/subjects definition

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR) - Learning area name
- `code` (VARCHAR) - Subject code
- `description` (TEXT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)
- `levels` (VARCHAR) - Applicable grade levels
- `is_optional` (BOOLEAN)

**Indexes:**
- `uk_code` (UNIQUE)
- `idx_status`

---

### Table: `curriculum_units`
**Purpose:** Detailed curriculum units within learning areas

**Columns:**
- `id` (INT, PK, Auto-increment)
- `learning_area_id` (INT, FK → learning_areas.id)
- `name` (VARCHAR) - Unit name
- `description` (TEXT)
- `learning_outcomes` (TEXT)
- `suggested_resources` (TEXT)
- `duration` (INT) - Duration in hours
- `order_sequence` (INT) - Teaching order
- `status` (ENUM) - draft, active, archived
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `learning_area_id` → `learning_areas(id)`

**Indexes:**
- `idx_learning_area_order`
- `idx_status`

---

### Table: `learning_outcomes`
**Purpose:** Specific learning outcomes per learning area

**Columns:**
- `id` (INT, PK, Auto-increment)
- `learning_area_id` (INT, FK → learning_areas.id)
- `outcome` (TEXT)
- `grade_level` (VARCHAR)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `learning_area_id` → `learning_areas(id)`

**Indexes:**
- `learning_area_id`

---

### Table: `core_competencies`
**Purpose:** CBC core competencies definition

**Columns:**
- `id` (INT, PK, Auto-increment)
- `code` (VARCHAR) - Competency code
- `name` (VARCHAR) - Competency name
- `description` (TEXT)
- `grade_range` (VARCHAR) - Applicable grades
- `learning_outcomes` (TEXT)
- `assessment_criteria` (TEXT)
- `sort_order` (INT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)

**Indexes:**
- `uk_code` (UNIQUE)

---

### Table: `core_values`
**Purpose:** CBC core values definition

**Columns:**
- `id` (INT, PK, Auto-increment)
- `code` (VARCHAR) - Value code
- `name` (VARCHAR) - Value name
- `description` (TEXT)
- `behavioral_indicators` (TEXT)
- `grade_range` (VARCHAR) - Applicable grades
- `sort_order` (INT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)

**Indexes:**
- `uk_code` (UNIQUE)

---

### Table: `performance_levels_cbc`
**Purpose:** CBC performance level definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `level` (VARCHAR) - EE, ME, AE, BE
- `code` (VARCHAR)
- `name` (VARCHAR) - Exceeding Expectations, etc.
- `description` (TEXT)
- `mark_range_min` (INT)
- `mark_range_max` (INT)
- `feedback_template` (TEXT)
- `created_at` (TIMESTAMP)

**Indexes:**
- `uk_level` (UNIQUE)

---

### Table: `grading_scales`
**Purpose:** Grading scale definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR) - Scale name
- `description` (TEXT)
- `min_mark` (INT)
- `max_mark` (INT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `uk_name` (UNIQUE)

---

### Table: `grade_rules`
**Purpose:** Grade rules within grading scales

**Columns:**
- `id` (INT, PK, Auto-increment)
- `scale_id` (INT, FK → grading_scales.id)
- `grade_code` (VARCHAR) - A, B, C, etc.
- `grade_name` (VARCHAR)
- `min_mark` (INT)
- `max_mark` (INT)
- `grade_points` (DECIMAL)
- `performance_level` (VARCHAR)
- `description` (TEXT)
- `sort_order` (INT)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `scale_id` → `grading_scales(id)`

**Indexes:**
- `uk_scale_grade` (UNIQUE on scale_id, grade_code)
- `idx_scale_marks`

---

### Table: `grading_comments`
**Purpose:** Predefined comments for grades

**Columns:**
- `id` (INT, PK, Auto-increment)
- `grade_code` (VARCHAR)
- `comment` (TEXT)
- `created_at` (TIMESTAMP)

**Indexes:**
- `uk_grade_code` (UNIQUE)

---

## BLOCK 3 — TIMETABLING

### Table: `class_schedules`
**Purpose:** Weekly class timetable entries

**Columns:**
- `id` (INT, PK, Auto-increment)
- `class_id` (INT, FK → classes.id)
- `day_of_week` (ENUM) - Monday, Tuesday, etc.
- `start_time` (TIME)
- `end_time` (TIME)
- `subject_id` (INT, FK → curriculum_units.id)
- `teacher_id` (INT, FK → staff.id)
- `room_id` (INT, FK → rooms.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `term_id` (INT, FK → academic_terms.id)
- `period_number` (INT)
- `status` (ENUM) - active, cancelled, moved
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `class_id` → `classes(id)`
- `subject_id` → `curriculum_units(id)`
- `teacher_id` → `staff(id)`
- `room_id` → `rooms(id)`

**Indexes:**
- `idx_no_class_overlap` - Prevents class double-booking
- `class_id`
- `subject_id`
- `teacher_id`
- `room_id`
- `idx_schedule_datetime`
- `idx_academic_year`
- `idx_term`
- `idx_year_term`

---

### Table: `exam_schedules`
**Purpose:** Examination schedule management

**Columns:**
- `id` (INT, PK, Auto-increment)
- `term_id` (INT, FK → academic_terms.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `class_id` (INT, FK → classes.id)
- `subject_id` (INT, FK → curriculum_units.id)
- `exam_name` (VARCHAR)
- `exam_type` (ENUM) - mid-term, end-term, mock, national
- `exam_date` (DATE)
- `start_time` (TIME)
- `end_time` (TIME)
- `duration_minutes` (INT)
- `room_id` (INT, FK → rooms.id)
- `venue` (VARCHAR)
- `invigilator_id` (INT, FK → staff.id)
- `supervisor_id` (INT, FK → staff.id)
- `notes` (TEXT)
- `created_by` (INT, FK → users.id)
- `status` (ENUM) - scheduled, in_progress, completed, cancelled
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `class_id` → `classes(id)`
- `subject_id` → `curriculum_units(id)`
- `room_id` → `rooms(id)`
- `invigilator_id` → `staff(id)`

**Indexes:**
- `class_id`
- `subject_id`
- `room_id`
- `invigilator_id`
- `idx_exam_schedule_datetime`
- `idx_exam_term`
- `idx_exam_academic_year`

---

### Table: `schedule_changes`
**Purpose:** Audit trail for timetable changes

**Columns:**
- `id` (INT, PK, Auto-increment)
- `schedule_type` (ENUM) - class_schedule, exam_schedule
- `schedule_id` (INT)
- `change_type` (ENUM) - create, update, delete, cancel
- `old_value` (JSON)
- `new_value` (JSON)
- `changed_by` (INT, FK → users.id)
- `changed_at` (TIMESTAMP)

**Foreign Keys:**
- `changed_by` → `users(id)`

**Indexes:**
- `changed_by`

---

## BLOCK 4 — TEACHING DELIVERY

### Table: `schemes_of_work`
**Purpose:** Teacher scheme of work records

**Columns:**
- `id` (INT, PK, Auto-increment)
- `teacher_id` (INT, FK → staff.id)
- `class_id` (INT, FK → classes.id)
- `subject_id` (INT, FK → curriculum_units.id)
- `subject_name` (VARCHAR)
- `learning_area_id` (INT, FK → learning_areas.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `term_id` (INT, FK → academic_terms.id)
- `term_number` (INT)
- `title` (VARCHAR)
- `description` (TEXT)
- `week_number` (INT)
- `strand` (VARCHAR)
- `sub_strand` (VARCHAR)
- `learning_outcomes` (TEXT)
- `key_vocabulary` (TEXT)
- `resources` (TEXT)
- `activities` (TEXT)
- `assessment_methods` (TEXT)
- `status` (ENUM) - draft, submitted, approved, completed
- `approved_by` (INT, FK → staff.id)
- `approved_at` (TIMESTAMP)
- `rejection_reason` (TEXT)
- `file_path` (VARCHAR)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `idx_teacher_id`
- `idx_class_id`
- `idx_term_id`
- `idx_academic_year`
- `idx_status`
- `idx_week_number`

---

### Table: `lesson_plans`
**Purpose:** Individual lesson plan records

**Columns:**
- `id` (INT, PK, Auto-increment)
- `teacher_id` (INT, FK → staff.id)
- `learning_area_id` (INT, FK → learning_areas.id)
- `class_id` (INT, FK → classes.id)
- `term_id` (INT, FK → academic_terms.id)
- `academic_year_id` (INT, FK → academic_years.id)
- `unit_id` (INT, FK → curriculum_units.id)
- `topic` (VARCHAR)
- `subtopic` (VARCHAR)
- `objectives` (TEXT)
- `resources` (TEXT)
- `activities` (TEXT)
- `assessment` (TEXT)
- `homework` (TEXT)
- `lesson_date` (DATE)
- `duration` (INT) - Duration in minutes
- `status` (ENUM) - draft, submitted, approved, delivered
- `remarks` (TEXT)
- `approved_by` (INT, FK → staff.id)
- `approved_at` (TIMESTAMP)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `teacher_id` → `staff(id)`
- `learning_area_id` → `learning_areas(id)`
- `class_id` → `classes(id)`
- `unit_id` → `curriculum_units(id)`
- `approved_by` → `staff(id)`

**Indexes:**
- `idx_teacher_learning_area`
- `idx_class_date`
- `idx_unit_status`
- `idx_approval`
- `learning_area_id`
- `idx_lesson_plan_dates`
- `idx_lp_term`
- `idx_lp_academic_year`

---

### Table: `assignments`
**Purpose:** Assignment definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `class_id` (INT, FK → classes.id)
- `subject_id` (INT, FK → curriculum_units.id)
- `term_id` (INT, FK → academic_terms.id)
- `title` (VARCHAR)
- `description` (TEXT)
- `due_date` (DATE)
- `assigned_date` (DATE)
- `max_marks` (INT)
- `teacher_id` (INT, FK → staff.id)
- `status` (ENUM) - draft, assigned, closed
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `class_id` → `classes(id)`
- `subject_id` → `curriculum_units(id)`
- `teacher_id` → `staff(id)`

**Indexes:**
- `idx_class_term`
- `idx_subject`
- `idx_teacher`
- `idx_due_date`
- `idx_status`

---

### Table: `assignment_submissions`
**Purpose:** Student assignment submissions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `assignment_id` (INT, FK → assignments.id)
- `student_id` (INT, FK → students.id)
- `submission_date` (DATE)
- `marks_obtained` (DECIMAL)
- `grade` (VARCHAR)
- `feedback` (TEXT)
- `file_path` (VARCHAR)
- `status` (ENUM) - pending, submitted, graded
- `submitted_at` (TIMESTAMP)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `assignment_id` → `assignments(id)`
- `student_id` → `students(id)`

**Indexes:**
- `idx_assignment_student`
- `idx_student`
- `idx_status`

---

## BLOCK 5 — ASSESSMENTS AND EXAMS

### Table: `assessment_types`
**Purpose:** Assessment type definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR) - CAT, Assignment, Project, etc.
- `code` (VARCHAR)
- `description` (TEXT)
- `weight_percentage` (DECIMAL)
- `default_max_marks` (INT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `uk_code` (UNIQUE)
- `idx_status`

---

### Table: `assessment_type_classifications`
**Purpose:** Assessment classification categories

**Columns:**
- `id` (INT, PK, Auto-increment)
- `classification_name` (VARCHAR)
- `code` (VARCHAR)
- `description` (TEXT)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `uk_code` (UNIQUE)
- `idx_status`

---

### Table: `assessment_tools`
**Purpose:** Assessment tool definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR)
- `code` (VARCHAR)
- `description` (TEXT)
- `tool_type` (ENUM)
- `status` (ENUM) - active, inactive
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- `uk_code` (UNIQUE)
- `idx_status`

---

### Table: `assessment_rubrics`
**Purpose:** Assessment rubric definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `name` (VARCHAR)
- `description` (TEXT)
- `assessment_type_id` (INT, FK → assessment_types.id)
- `criteria_json` (JSON)
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `assessment_type_id` → `assessment_types(id)`
- `created_by` → `users(id)`

**Indexes:**
- `idx_assessment_type`
- `idx_created_by`

---

### Table: `assessment_benchmarks`
**Purpose:** Performance benchmark targets

**Columns:**
- `id` (INT, PK, Auto-increment)
- `academic_year` (YEAR)
- `grade_level_id` (INT, FK → school_levels.id)
- `subject_id` (INT, FK → curriculum_units.id)
- `benchmark_type` (ENUM)
- `target_percentage` (DECIMAL)
- `acceptable_range_min` (DECIMAL)
- `acceptable_range_max` (DECIMAL)
- `description` (TEXT)
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `created_by` → `users(id)`
- `grade_level_id` → `school_levels(id)`
- `subject_id` → `curriculum_units(id)`

**Indexes:**
- `uk_benchmark` (UNIQUE)
- `idx_benchmark_year_grade`
- `fk_ab_created_by`
- `fk_ab_grade_level`
- `fk_ab_subject`

---

### Table: `assessments`
**Purpose:** Main assessment records

**Columns:**
- `id` (INT, PK, Auto-increment)
- `class_id` (INT, FK → classes.id)
- `subject_id` (INT, FK → curriculum_units.id)
- `term_id` (INT, FK → academic_terms.id)
- `title` (VARCHAR)
- `max_marks` (INT)
- `assessment_date` (DATE)
- `assigned_by` (INT, FK → users.id)
- `status` (ENUM) - draft, open, closed, published
- `approved_by` (INT, FK → users.id)
- `approved_at` (TIMESTAMP)
- `created_at` (TIMESTAMP)
- `assessment_type_id` (INT, FK → assessment_types.id)
- `learning_outcome_id` (INT, FK → learning_outcomes.id)

**Foreign Keys:**
- `learning_outcome_id` → `learning_outcomes(id)`
- `assessment_type_id` → `assessment_types(id)`

**Indexes:**
- `class_id`
- `subject_id`
- `term_id`
- `assessment_date`
- `assigned_by`
- `approved_by`
- `status`
- `assessment_type_id`
- `learning_outcome_id`

---

### Table: `assessment_results`
**Purpose:** Student assessment results

**Columns:**
- `id` (INT, PK, Auto-increment)
- `assessment_id` (INT, FK → assessments.id)
- `student_id` (INT, FK → students.id)
- `marks_obtained` (DECIMAL)
- `grade` (VARCHAR)
- `points` (DECIMAL)
- `remarks` (TEXT)
- `peer_feedback` (TEXT)
- `submitted_at` (TIMESTAMP)
- `is_submitted` (BOOLEAN)
- `is_approved` (BOOLEAN)
- `responder_type` (ENUM)
- `responder_id` (INT)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `assessment_id` → `assessments(id)`
- `student_id` → `students(id)`

**Indexes:**
- `uk_assessment_student` (UNIQUE on assessment_id, student_id)
- `assessment_id`
- `student_id`
- `is_submitted`
- `is_approved`
- `idx_responder`

---

### Table: `assessment_history`
**Purpose:** Audit trail for assessment result changes

**Columns:**
- `id` (INT, PK, Auto-increment)
- `assessment_result_id` (INT, FK → assessment_results.id)
- `student_id` (INT, FK → students.id)
- `assessment_id` (INT, FK → assessments.id)
- `old_marks` (DECIMAL)
- `new_marks` (DECIMAL)
- `old_grade` (VARCHAR)
- `new_grade` (VARCHAR)
- `change_reason` (TEXT)
- `changed_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `assessment_id` → `assessments(id)`
- `student_id` → `students(id)`

**Indexes:**
- `idx_student_history`
- `idx_assessment_history`

---

## BLOCK 6 — RESULTS AND REPORTING

### Table: `annual_scores`
**Purpose:** Annual student performance summary

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `academic_year` (YEAR)
- `term1_weight` (DECIMAL)
- `term1_score` (DECIMAL)
- `term1_grade` (VARCHAR)
- `term2_weight` (DECIMAL)
- `term2_score` (DECIMAL)
- `term2_grade` (VARCHAR)
- `term3_weight` (DECIMAL)
- `term3_score` (DECIMAL)
- `term3_grade` (VARCHAR)
- `annual_score` (DECIMAL)
- `annual_percentage` (DECIMAL)
- `annual_grade` (VARCHAR)
- `annual_points` (DECIMAL)
- `annual_rank` (INT)
- `grade_total_students` (INT)
- `grade_percentile` (DECIMAL)
- `strengths` (TEXT)
- `weaknesses` (TEXT)
- `avg_formative_percentage` (DECIMAL)
- `avg_summative_percentage` (DECIMAL)
- `pathway_classification` (VARCHAR)
- `insights_summary` (TEXT)
- `calculated_at` (TIMESTAMP)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `student_id` → `students(id)`

**Indexes:**
- `uk_student_year` (UNIQUE on student_id, academic_year)
- `idx_academic_year`
- `idx_annual_grade`
- `idx_pathway_classification`

---

### Table: `formative_scores`
**Purpose:** Formative assessment scores by term

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `term_id` (INT, FK → academic_terms.id)
- `academic_year` (YEAR)
- `learning_area_id` (INT, FK → learning_areas.id)
- `score` (DECIMAL)
- `max_score` (DECIMAL)
- `cbc_grade` (VARCHAR)
- `remarks` (TEXT)
- `assessed_by` (INT, FK → users.id)
- `assessed_date` (DATE)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `student_id` → `students(id)`
- `term_id` → `academic_terms(id)`
- `learning_area_id` → `learning_areas(id)`
- `assessed_by` → `users(id)`

**Indexes:**
- `idx_student_term_area`
- `idx_term_year`
- `idx_assessed_by`

---

### Table: `national_exam_results`
**Purpose:** National examination results

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `exam_type` (ENUM) - KCPE, KCSE, etc.
- `academic_year_id` (INT, FK → academic_years.id)
- `exam_year` (YEAR)
- `learning_area_id` (INT, FK → learning_areas.id)
- `score` (DECIMAL)
- `max_score` (DECIMAL)
- `percentage` (DECIMAL)
- `cbc_grade` (VARCHAR)
- `raw_grade` (VARCHAR)
- `points` (INT)
- `pathway` (VARCHAR)
- `remarks` (TEXT)
- `entered_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `student_id` → `students(id)`
- `learning_area_id` → `learning_areas.id)`

**Indexes:**
- `uq_natl_exam` (UNIQUE)
- `idx_natl_student`
- `idx_natl_type`
- `idx_natl_year`

---

## BLOCK 7 — STUDENT ACADEMIC LIFECYCLE

### Table: `promotion_batches`
**Purpose:** Batch promotion process management

**Columns:**
- `id` (INT, PK, Auto-increment)
- `from_academic_year` (YEAR)
- `to_academic_year` (YEAR)
- `batch_type` (ENUM) - automatic, manual
- `batch_scope` (ENUM) - all_class, selected_classes
- `status` (ENUM) - pending, in_progress, completed, cancelled
- `total_students_processed` (INT)
- `total_promoted` (INT)
- `total_pending_approval` (INT)
- `total_rejected` (INT)
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)
- `completed_at` (TIMESTAMP)
- `notes` (TEXT)

**Foreign Keys:**
- `created_by` → `users(id)`

**Indexes:**
- `idx_status`
- `idx_academic_years`
- `idx_batch_type`
- `promotion_batches_ibfk_1`

---

### Table: `promotion_rules`
**Purpose:** Promotion eligibility rules

**Columns:**
- `id` (INT, PK, Auto-increment)
- `level_name` (VARCHAR)
- `min_score_promote` (DECIMAL)
- `min_score_review` (DECIMAL)
- `attendance_min_pct` (DECIMAL)
- `auto_promote` (BOOLEAN)
- `require_approval` (BOOLEAN)
- `notes` (TEXT)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Indexes:**
- PRIMARY KEY only

---

### Table: `class_promotion_queue`
**Purpose:** Queue for class-by-class promotion review

**Columns:**
- `id` (INT, PK, Auto-increment)
- `batch_id` (INT, FK → promotion_batches.id)
- `class_id` (INT, FK → classes.id)
- `stream_id` (INT, FK → class_streams.id)
- `approval_status` (ENUM) - pending, approved, rejected
- `total_in_class` (INT)
- `approved_count` (INT)
- `rejected_count` (INT)
- `pending_count` (INT)
- `assigned_to_user_id` (INT, FK → users.id)
- `notes` (TEXT)
- `reviewed_at` (TIMESTAMP)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `batch_id` → `promotion_batches(id)`
- `class_id` → `classes(id)`
- `stream_id` → `class_streams(id)`
- `assigned_to_user_id` → `users(id)`

**Indexes:**
- `uk_batch_class_stream` (UNIQUE)
- `idx_approval_status`
- `idx_class_id`
- `class_promotion_queue_ibfk_3`
- `class_promotion_queue_ibfk_4`

---

## BLOCK 8 — ACADEMIC CALENDAR AND EVENTS

### Table: `school_calendar`
**Purpose:** School calendar events

**Columns:**
- `id` (INT, PK, Auto-increment)
- `date` (DATE, UNIQUE)
- `day_type` (ENUM) - holiday, exam, event, assembly, regular
- `title` (VARCHAR)
- `description` (TEXT)
- `academic_year_id` (INT, FK → academic_years.id)
- `term_id` (INT, FK → academic_terms.id)
- `affects_day_students` (BOOLEAN)
- `affects_boarders` (BOOLEAN)
- `requires_attendance` (BOOLEAN)
- `created_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `academic_year_id` → `academic_years(id)`

**Indexes:**
- `unique_date` (UNIQUE)
- `term_id`
- `idx_date`
- `idx_day_type`
- `school_calendar_ibfk_1`

---

## CBC LEARNER ASSESSMENT BLOCK

### Table: `learner_competencies`
**Purpose:** Student competency assessment records

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `competency_id` (INT, FK → core_competencies.id)
- `academic_year` (YEAR)
- `term_id` (INT, FK → academic_terms.id)
- `performance_level_id` (INT, FK → performance_levels_cbc.id)
- `evidence` (TEXT)
- `teacher_notes` (TEXT)
- `assessed_by` (INT, FK → users.id)
- `assessed_date` (DATE)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `competency_id` → `core_competencies(id)`
- `performance_level_id` → `performance_levels_cbc(id)`
- `assessed_by` → `users(id)`
- `student_id` → `students(id)`
- `term_id` → `academic_terms(id)`

**Indexes:**
- `uk_learner_competency` (UNIQUE)
- `idx_student_year`
- `idx_competency`
- `idx_performance_level`
- `fk_lc_assessed_by`
- `fk_lc_term`

**Triggers:**
- `trg_log_competency_assessment` - Logs competency assessment changes

---

### Table: `learner_values_acquisition`
**Purpose:** Student core values demonstration records

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `value_id` (INT, FK → core_values.id)
- `academic_year` (YEAR)
- `term_id` (INT, FK → academic_terms.id)
- `evidence` (TEXT)
- `incident_date` (DATE)
- `recorded_by` (INT, FK → users.id)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `value_id` → `core_values(id)`
- `recorded_by` → `users(id)`
- `student_id` → `students(id)`
- `term_id` → `academic_terms(id)`

**Indexes:**
- `idx_student_value`
- `idx_term`
- `idx_value`
- `fk_lva_recorded_by`

---

### Table: `learner_csl_participation`
**Purpose:** Community Service Learning participation

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `csl_activity_id` (INT, FK → csl_activities.id)
- `academic_year` (YEAR)
- `hours_contributed` (DECIMAL)
- `role` (VARCHAR)
- `reflection` (TEXT)
- `teacher_feedback` (TEXT)
- `participation_status` (ENUM)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `csl_activity_id` → `csl_activities(id)`
- `student_id` → `students(id)`

**Indexes:**
- `uk_student_activity` (UNIQUE)
- `idx_student_year`
- `idx_activity`

---

### Table: `learner_pci_awareness`
**Purpose:** Pertinent and Contemporary Issues awareness

**Columns:**
- `id` (INT, PK, Auto-increment)
- `student_id` (INT, FK → students.id)
- `pci_id` (INT, FK → pcis.id)
- `academic_year` (YEAR)
- `term_id` (INT, FK → academic_terms.id)
- `awareness_level` (ENUM)
- `evidence` (TEXT)
- `learning_activity` (TEXT)
- `assessed_by` (INT, FK → users.id)
- `assessed_date` (DATE)
- `created_at` (TIMESTAMP)

**Foreign Keys:**
- `pci_id` → `pcis(id)`
- `assessed_by` → `users(id)`
- `student_id` → `students(id)`
- `term_id` → `academic_terms(id)`

**Indexes:**
- `uk_student_pci` (UNIQUE)
- `idx_student_year`
- `idx_awareness`
- `fk_lpa_assessed_by`
- `fk_lpa_pci`
- `fk_lpa_term`

**Triggers:**
- `trg_log_pci_awareness` - Logs PCI awareness changes

---

### Table: `csl_activities`
**Purpose:** Community Service Learning activity definitions

**Columns:**
- `id` (INT, PK, Auto-increment)
- `activity_name` (VARCHAR)
- `description` (TEXT)
- `activity_date` (DATE)
- `location` (VARCHAR)
- `beneficiary` (VARCHAR)
- `impact_area` (VARCHAR)
- `total_hours` (DECIMAL)
- `organized_by` (INT, FK → users.id)
- `status` (ENUM) - planned, completed, cancelled
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

**Foreign Keys:**
- `organized_by` → `users(id)`

**Indexes:**
- `idx_activity_date`
- `idx_status`
- `fk_csl_organized_by`

---

## MISSING STRUCTURES

The following tables are referenced by foreign keys but were not found in the main schema file:

1. **`staff`** table - Referenced by 37 foreign keys across the database. This is a critical table that must exist in a separate migration or be imported from another source.

2. **`staff_class_assignments`** - Referenced in triggers but table definition not found.

3. **`report_cards`** - No dedicated report card table found. Report card data may be generated dynamically from other tables.

4. **Subject-specific teacher allocation tables** - Only general `class_schedules` and `class_year_assignments` found. May need dedicated tables for better tracking of subject-specific teacher assignments.

---

## RECOMMENDATIONS

1. **Investigate `staff` table structure** - This table is critical and extensively referenced but not defined in the main schema file.

2. **Consider dedicated teacher allocation tables** - Current structure uses class_schedules for allocation, but a dedicated `teacher_subject_allocations` table might provide better tracking.

3. **Report card storage strategy** - Determine if report cards should be stored as generated documents or generated dynamically from existing assessment and enrollment data.

4. **Audit trigger completeness** - Review all triggers to ensure they handle all necessary data consistency checks.

5. **Index optimization** - Review query patterns and add composite indexes for common multi-column queries.

---

*Generated: 2026-07-14*
*Database: KingsWayAcademy*
*Schema Version: Based on KingsWayAcademy_20260429.sql*
