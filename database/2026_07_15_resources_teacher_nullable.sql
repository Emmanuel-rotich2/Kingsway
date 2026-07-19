-- ============================================================================
-- Fix: make teaching_materials.teacher_id nullable
-- ============================================================================
-- POST /api/academic/resources (teaching-materials + past-papers upload) inserts
-- a row keyed on the uploading STAFF member via `staff.user_id = <auth user id>`.
-- The column is `teacher_id int unsigned NOT NULL` with an FK to staff.
--
-- Problem: not every uploader is a teacher. Admin / system / test accounts
-- (e.g. test_sysadmin) have NO linked staff row, so $teacherId resolves to NULL
-- and the insert dies with SQLSTATE 23000 / 1048 "Column 'teacher_id' cannot be
-- null" -> HTTP 500. Resource uploads are not exclusively teacher-authored, so a
-- missing staff link is a legitimate state (not an error).
--
-- Fix: relax the column + FK-dependent column to NULL. The FK itself stays intact
-- (it only constrains non-null values); null rows simply skip the FK check.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE teaching_materials
    MODIFY COLUMN teacher_id INT UNSIGNED NULL COMMENT 'Uploading staff member; NULL when uploaded by a non-staff account (admin/system)';

SET FOREIGN_KEY_CHECKS = 1;
