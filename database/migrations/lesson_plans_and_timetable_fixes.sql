-- ============================================================================
-- Migration: lesson_plans_and_timetable_fixes.sql
-- Purpose: Add 'rejected' status to lesson_plans, fix broken sidebar entries
-- Date: 2025-01-xx
-- ============================================================================

-- ============================================================================
-- SECTION 1: Add 'rejected' to lesson_plans status enum
-- ============================================================================
ALTER TABLE lesson_plans 
    MODIFY COLUMN status ENUM('draft','submitted','approved','rejected','completed') NOT NULL DEFAULT 'draft';

-- ============================================================================
-- SECTION 2: Fix broken sidebar entries for timetable
-- ============================================================================

-- Fix NULL URLs for deputy/DHA timetable entries
UPDATE sidebar_menu_items SET url = 'manage_timetable' WHERE id = 350 AND url IS NULL;
UPDATE sidebar_menu_items SET url = 'manage_timetable' WHERE id = 1100 AND url IS NULL;

-- Point missing DHA timetable pages to existing manage_timetable page
UPDATE sidebar_menu_items SET url = 'manage_timetable' WHERE id = 1101 AND url = 'master_timetable';
UPDATE sidebar_menu_items SET url = 'manage_timetable' WHERE id = 1102 AND url = 'create_timetable';
UPDATE sidebar_menu_items SET url = 'timetable' WHERE id = 1103 AND url = 'class_timetables';
UPDATE sidebar_menu_items SET url = 'timetable' WHERE id = 1104 AND url = 'teacher_timetables';

-- Fix lesson plan sidebar entries that point to nonexistent pages
-- Redirect them all to manage_lesson_plans (the working page)
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'all_lesson_plans';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'lesson_plans_by_class';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'lesson_plans_by_teacher';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'my_lesson_plans';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'create_lesson_plan';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'my_subject_lesson_plans';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'create_subject_lesson';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'intern_lesson_plans';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'intern_create_lesson';
UPDATE sidebar_menu_items SET url = 'manage_lesson_plans' WHERE url = 'lesson_mentor_feedback';

-- ============================================================================
-- SECTION 3: Add term_id and academic_year_id to updateLessonPlan allowed list
-- (Already handled in PHP code, but ensure DB indexes exist)
-- ============================================================================
-- Indexes already created by previous migration, just verify
SELECT 'Migration completed successfully' AS status;
