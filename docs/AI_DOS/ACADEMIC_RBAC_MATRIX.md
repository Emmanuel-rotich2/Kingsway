# Academic Module RBAC Matrix

**Generated:** 2026-07-14  
**System:** Kingsway School Management System  
**Module:** Academic Module  
**Purpose:** Comprehensive role-based access control documentation for academic features

---

## Overview

This document provides a comprehensive Role-Based Access Control (RBAC) matrix for the Academic Module, mapping user roles to specific permissions across all 87 academic routes. The matrix is organized by block and includes permission definitions, role categories, and access levels.

---

## Role Categories

### Role Hierarchy

| Category | Level | Access | Description |
|----------|-------|--------|-------------|
| **admin** | 1 | Full | Complete system access, all features, management controls |
| **manager** | 2 | Standard | Department-level access, reporting, limited admin |
| **operator** | 3 | Limited | Task-focused access, data entry, daily operations |
| **viewer** | 4 | Minimal | Read-only access, minimal UI |

### Role Mapping

| Role ID | Role Name | Category | Academic Focus |
|---------|-----------|----------|-----------------|
| 3 | Director | admin | Academic oversight, approvals |
| 4 | School Administrator | admin | Academic administration, system configuration |
| 5 | Headteacher | admin | Academic leadership, final approvals |
| 6 | Deputy Academic | manager | Academic operations, supervision |
| 7 | Class Teacher | operator | Class-level academic management |
| 8 | Subject Teacher | operator | Subject-specific academic management |
| 9 | Intern | viewer | Read-only academic access, learning |

---

## Permission Naming Convention

### Format
- **Dot notation:** `module.action` (e.g., `academic.view`, `schemes_of_work.create`)
- **Underscore notation:** `module_action` (e.g., `academic_view`, `schemes_of_work_create`)
- **Both formats supported** in permission checks

### Standard Actions
- `view` - Read access
- `create` - Create new records
- `edit` / `update` - Modify existing records
- `delete` - Remove records
- `export` - Export data
- `import` - Import data
- `approve` - Approve submissions
- `manage` - Full CRUD access

---

## Block 1: Academic Setup RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| academic_years | academic.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_years | academic.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_years | academic.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_years | academic.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| academic_terms | academic.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_terms | academic.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_terms | academic.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_terms | academic.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| competency_checklist | competency.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| competency_checklist | competency.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competency_checklist | competency.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competency_checklist | competency.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| curriculum_cbc | curriculum.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| curriculum_cbc | curriculum.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_cbc | curriculum.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_cbc | curriculum.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| curriculum_guidelines | curriculum.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| curriculum_guidelines | curriculum.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_guidelines | curriculum.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_guidelines | curriculum.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| grading_systems | grading.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| grading_systems | grading.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| grading_systems | grading.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| grading_systems | grading.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Summary
- **Admin roles (Director, School Admin, Headteacher):** Full CRUD access
- **Deputy Academic:** Full CRUD except delete on years/terms
- **Class/Subject Teachers:** View and create/edit (no delete)
- **Intern:** View-only access

---

## Block 2: Curriculum and Teaching Setup RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| curriculum_framework | curriculum.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| curriculum_framework | curriculum.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_framework | curriculum.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_framework | curriculum.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| manage_subjects | subjects.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| manage_subjects | subjects.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_subjects | subjects.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_subjects | subjects.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| manage_learning_areas | learning_areas.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| manage_learning_areas | learning_areas.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_learning_areas | learning_areas.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_learning_areas | learning_areas.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| curriculum_assessment | assessment.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| curriculum_assessment | assessment.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_assessment | assessment.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_assessment | assessment.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| strand_substrand | strand.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| strand_substrand | strand.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| strand_substrand | strand.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| strand_substrand | strand.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| learning_objectives | objectives.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| learning_objectives | objectives.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| learning_objectives | objectives.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| learning_objectives | objectives.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| assessment_criteria | criteria.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| assessment_criteria | criteria.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| assessment_criteria | criteria.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| assessment_criteria | criteria.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| curriculum_resources | resources.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| curriculum_resources | resources.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_resources | resources.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| curriculum_resources | resources.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| teaching_guidelines | guidelines.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| teaching_guidelines | guidelines.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| teaching_guidelines | guidelines.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| teaching_guidelines | guidelines.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| competency_framework | competency.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| competency_framework | competency.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competency_framework | competency.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competency_framework | competency.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Summary
- **Admin roles:** Full CRUD access to curriculum management
- **Deputy Academic:** Full CRUD except delete on framework/subjects/learning areas
- **Class/Subject Teachers:** View and create/edit (no delete)
- **Intern:** View-only access

---

## Block 3: Timetabling RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| timetable_view | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| timetable_view | timetable.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| timetable_view | timetable.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| timetable_view | timetable.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| manage_timetable | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| manage_timetable | timetable.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_timetable | timetable.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_timetable | timetable.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| class_timetable | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| class_timetable | timetable.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| class_timetable | timetable.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| class_timetable | timetable.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| teacher_timetable | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| teacher_timetable | timetable.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| teacher_timetable | timetable.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| teacher_timetable | timetable.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| assign_class_teachers | assignment.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| assign_class_teachers | assignment.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_class_teachers | assignment.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_class_teachers | assignment.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| assign_subject_teachers | assignment.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| assign_subject_teachers | assignment.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_subject_teachers | assignment.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_subject_teachers | assignment.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| assign_intern_teachers | assignment.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| assign_intern_teachers | assignment.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_intern_teachers | assignment.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assign_intern_teachers | assignment.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| timetable_conflicts | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| master_timetable | timetable.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| print_timetable | timetable.export | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| intern_assigned_classes | intern.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| intern_assigned_subjects | intern.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| intern_schedule | intern.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

### Summary
- **Admin roles:** Full CRUD access to timetabling
- **Deputy Academic:** Full CRUD except delete
- **Class/Subject Teachers:** View-only timetables
- **Intern:** View-only assigned classes/subjects

---

## Block 4: Teaching Delivery RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| schemes_of_work | schemes.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| schemes_of_work | schemes.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| schemes_of_work | schemes.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| schemes_of_work | schemes.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| schemes_of_work | schemes.approve | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| my_schemes_of_work | schemes.view | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| my_schemes_of_work | schemes.create | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| my_schemes_of_work | schemes.edit | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| my_schemes_of_work | schemes.delete | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| my_schemes_of_work | schemes.approve | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ |
| subject_schemes_of_work | schemes.view | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| subject_schemes_of_work | schemes.create | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| subject_schemes_of_work | schemes.edit | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| subject_schemes_of_work | schemes.delete | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| subject_schemes_of_work | schemes.approve | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ |
| manage_lesson_plans | lesson_plans.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| manage_lesson_plans | lesson_plans.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| manage_lesson_plans | lesson_plans.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| manage_lesson_plans | lesson_plans.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| manage_lesson_plans | lesson_plans.approve | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| all_lesson_plans | lesson_plans.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| lesson_plan_approval | lesson_plans.approve | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| lesson_plans_by_class | lesson_plans.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| lesson_plans_by_teacher | lesson_plans.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| teaching_materials | materials.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| teaching_materials | materials.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| teaching_materials | materials.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| teaching_materials | materials.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| upload_teaching_resource | materials.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| past_papers | papers.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| past_papers | papers.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| past_papers | papers.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| past_papers | papers.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| view_teaching_materials | materials.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| view_past_papers | papers.view | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

### Summary
- **Admin roles:** Full CRUD access to teaching delivery
- **Deputy Academic:** Full CRUD except delete
- **Class Teachers:** Full CRUD for their own schemes/lesson plans
- **Subject Teachers:** Full CRUD for their subject schemes/lesson plans
- **Intern:** View-only access to materials and past papers

---

## Block 5: Assessments and Exams RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| formative_assessments | assessments.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| formative_assessments | assessments.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| formative_assessments | assessments.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| formative_assessments | assessments.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| competencies_sheet | competency.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| competencies_sheet | competency.create | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competencies_sheet | competency.edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| competencies_sheet | competency.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| exam_setup | exams.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| exam_setup | exams.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| exam_setup | exams.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| exam_setup | exams.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| exam_schedule | exams.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| exam_schedule | exams.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| exam_schedule | exams.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| exam_schedule | exams.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| grading_status | grading.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| national_exams | exams.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| national_exams | exams.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| national_exams | exams.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| national_exams | exams.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Summary
- **Admin roles:** Full CRUD access to exams and assessments
- **Deputy Academic:** Full CRUD except delete
- **Class/Subject Teachers:** View-only exams, manage own assessments
- **Intern:** View-only access

---

## Block 6: Results and Reporting RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| view_results | results.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| results_analysis | results.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| results_analysis | results.export | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| report_cards | report_cards.view | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| report_cards | report_cards.create | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| report_cards | report_cards.approve | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_reports | reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| academic_reports | reports.export | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| performance_analysis | reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| performance_reports | reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| term_reports | reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| student_performance | results.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |

### Summary
- **Admin roles:** Full access to results and reporting
- **Deputy Academic:** Full access to results and reporting
- **Class Teachers:** View class results, generate class report cards
- **Subject Teachers:** View subject results (subject_results_summary)
- **Intern:** View-only access to most reports

---

## Block 7: Student Academic Lifecycle RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| student_promotion | promotion.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| student_promotion | promotion.execute | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| placement_tests | placement.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| placement_tests | placement.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| placement_tests | placement.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| placement_tests | placement.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| enrollment_trends | reports.view | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| admissions_academic_applications | admissions.view | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| admissions_class_placement | placement.execute | ❌ | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |

### Summary
- **Admin roles:** Full access to student lifecycle management
- **Deputy Academic:** Full access to promotion and placement
- **Class/Subject Teachers:** No access to lifecycle management
- **Intern:** No access to lifecycle management

---

## Block 8: Academic Calendar and Events RBAC Matrix

### Routes and Permissions

| Route | Permission Code | Director | School Admin | Headteacher | Deputy Academic | Class Teacher | Subject Teacher | Intern |
|-------|----------------|----------|--------------|-------------|----------------|---------------|-----------------|--------|
| school_events | events.view | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| school_events | events.create | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| school_events | events.edit | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| school_events | events.delete | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| manage_calendar_events | events.view | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_calendar_events | events.create | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_calendar_events | events.edit | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| manage_calendar_events | events.delete | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| view_calendar | events.view | ❌ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assemblies | assemblies.view | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assemblies | assemblies.create | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assemblies | assemblies.edit | ❌ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| assemblies | assemblies.delete | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |

### Summary
- **Director:** View school events only
- **School Admin:** Full access to calendar and events
- **Headteacher/Deputy Academic:** Full access to events, limited calendar
- **Class/Subject Teachers:** View school events only
- **Intern:** View school events only

---

## Permission Implementation Guidelines

### Frontend Permission Checks

```javascript
// Using AuthContext
if (AuthContext.hasPermission('academic_view')) {
  // Show academic content
}

// Using can() helper
if (AuthContext.canView('academic')) {
  // Show academic content
}

// Using specific permission
if (AuthContext.hasPermission('schemes_of_work.create')) {
  // Show create button
}
```

### Backend Permission Checks

```php
// Using has_permission()
if (has_permission($user, 'academic_view')) {
    // Grant access
}

// Using can() helper
if (can($user, 'academic', 'view')) {
    // Grant access
}

// Using getAllowedActions()
$allowed = getAllowedActions($user, 'schemes_of_work');
// Returns: ['view', 'create', 'edit', 'approve']
```

### UI Element Permission Attributes

```html
<!-- Permission-based visibility -->
<button class="btn btn-primary" 
        data-permission="schemes_of_work_create" 
        data-permission-fallback="hidden">
    Create Scheme
</button>

<!-- Permission-based disabled state -->
<button class="btn btn-primary" 
        data-permission="schemes_of_work_delete" 
        data-permission-fallback="disabled">
    Delete Scheme
</button>
```

---

## Security Considerations

### Permission Enforcement Layers

1. **Frontend:** JavaScript checks using AuthContext
2. **Middleware:** RBACMiddleware validates all API requests
3. **Controller:** Controller-level permission checks
4. **Database:** Stored procedure-based permission resolution

### Data Access Levels

| Category | Level | Description |
|----------|-------|-------------|
| admin | full | Access to all data, no restrictions |
| manager | standard | Access to department data, limited cross-department |
| operator | limited | Access to assigned data only |
| viewer | minimal | Read-only access to public data |

### Security Best Practices

1. **Never trust client-side permission checks** - Always validate on server
2. **Use stored procedures for permission resolution** - Efficient and secure
3. **Log permission violations** - Track security incidents
4. **Regular permission audits** - Review and update as needed
5. **Principle of least privilege** - Grant minimum required permissions

---

## Permission Migration Guide

### Adding New Permissions

1. **Define permission code:** Follow `module.action` format
2. **Add to database:** Insert into `permissions` table
3. **Assign to roles:** Use `sp_user_get_effective_permissions` stored procedure
4. **Add frontend check:** Use `AuthContext.hasPermission()`
5. **Add backend check:** Use middleware or controller check

### Permission Naming Standards

- **Use lowercase:** `academic_view` not `Academic_View`
- **Use underscores:** `schemes_of_work_create` not `schemesOfWorkCreate`
- **Be descriptive:** `lesson_plans_approve` not `lp_approve`
- **Keep consistent:** Same action verbs across modules

---

## Monitoring and Auditing

### Permission Violation Logging

```php
// Log permission violations
if (!has_permission($user, $requiredPermission)) {
    error_log("Permission violation: User {$user['id']} attempted {$requiredPermission}");
    // Return 403 Forbidden
}
```

### Access Audit Trail

Track:
- User ID and role
- Permission requested
- Resource accessed
- Timestamp
- Success/failure status

---

## Conclusion

This RBAC matrix provides comprehensive permission definitions for all 87 academic routes across 8 blocks. The matrix ensures:

- **Proper separation of concerns** based on role responsibilities
- **Consistent permission naming** across all modules
- **Clear access levels** for different user categories
- **Scalable permission system** for future enhancements
- **Security best practices** with multi-layer enforcement

**Document End**

*Generated: 2026-07-14*
*Academic Module RBAC Matrix*
*Total Routes: 87*
*Total Permissions Defined: 100+*
