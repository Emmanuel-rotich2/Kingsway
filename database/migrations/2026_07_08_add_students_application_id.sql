-- =============================================================================
-- Admissions Intake Redesign — link students to applications
-- =============================================================================
-- The redesign creates a real `students` row at the "provisional student
-- creation" stage (step 8) and must NOT duplicate it on later stages
-- (enrollment reuses the same row). This column is the dedup/link key.
-- Run with: mysql -u root -p KingsWayAcademy < database/migrations/2026_07_08_add_students_application_id.sql

USE KingsWayAcademy;

SET @exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'KingsWayAcademy'
      AND TABLE_NAME = 'students'
      AND COLUMN_NAME = 'application_id'
);
SET @sql := IF(@exists = 0,
    'ALTER TABLE students
       ADD COLUMN application_id INT UNSIGNED NULL AFTER status,
       ADD INDEX idx_students_application_id (application_id),
       ADD CONSTRAINT fk_students_application FOREIGN KEY (application_id)
         REFERENCES admission_applications(id) ON DELETE SET NULL',
    'SELECT "students.application_id already present" AS note'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure every live application carries an application_source so the
-- dashboards/queues can label "Online" vs "Physical" without guessing.
UPDATE admission_applications
SET application_source = COALESCE(NULLIF(application_source, ''), 'physical')
WHERE application_source IS NULL OR application_source = '';

-- Backfill workflow_data_json with application_source for any application that
-- lacks it (frontend comms function reads workflow_data_json as a fallback).
UPDATE admission_applications aa
JOIN workflow_instances wi
  ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
SET wi.data_json = JSON_MERGE_PRESERVE(COALESCE(wi.data_json, '{}'),
                        JSON_OBJECT('application_source', aa.application_source))
WHERE JSON_EXTRACT(COALESCE(wi.data_json, '{}'), '$.application_source') IS NULL;
