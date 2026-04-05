-- ============================================================
-- Timetable & Scheduling System Complete Migration
-- Date: 2026-02-15
-- Purpose: Fix all scheduling schema gaps:
--   1. Add academic_year_id/term_id/period_number to class_schedules
--   2. Create time_slots reference table
--   3. Create timetable_conflicts table
--   4. Fix exam_schedules schema (add missing columns)
--   5. Add term scoping to lesson_plans
--   6. Fix sp_create_exam_schedule stored procedure
--   7. Seed default rooms and time slots
--   8. Refresh views
-- ============================================================

-- ============================================================
-- SECTION 1: class_schedules - Add term/year scoping
-- ============================================================

-- Add academic_year_id, term_id, period_number columns (idempotent)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND COLUMN_NAME = 'academic_year_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE class_schedules
        ADD COLUMN academic_year_id INT(10) UNSIGNED NULL AFTER room_id,
        ADD COLUMN term_id INT(10) UNSIGNED NULL AFTER academic_year_id,
        ADD COLUMN period_number TINYINT(3) UNSIGNED NULL AFTER term_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add updated_at column (idempotent)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE class_schedules ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes for year/term filtering (idempotent via IF NOT EXISTS pattern)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND INDEX_NAME = 'idx_academic_year');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_schedules ADD INDEX idx_academic_year (academic_year_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND INDEX_NAME = 'idx_term');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_schedules ADD INDEX idx_term (term_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND INDEX_NAME = 'idx_year_term');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_schedules ADD INDEX idx_year_term (academic_year_id, term_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add unique constraint to prevent double-booking (idempotent)
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'class_schedules' AND INDEX_NAME = 'idx_no_class_overlap');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE class_schedules ADD UNIQUE INDEX idx_no_class_overlap (class_id, day_of_week, start_time, academic_year_id, term_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add FK constraints (optional, won't error if already present)
-- ALTER TABLE class_schedules ADD CONSTRAINT fk_cs_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL;
-- ALTER TABLE class_schedules ADD CONSTRAINT fk_cs_term FOREIGN KEY (term_id) REFERENCES academic_terms(id) ON DELETE SET NULL;


-- ============================================================
-- SECTION 2: time_slots reference table
-- ============================================================

CREATE TABLE IF NOT EXISTS time_slots (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_number TINYINT(3) UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_type ENUM('lesson', 'break', 'lunch', 'assembly', 'games') NOT NULL DEFAULT 'lesson',
    label VARCHAR(50) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_period_time (period_number, start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default time slots (Kenyan school standard) if table is empty
INSERT INTO time_slots (period_number, start_time, end_time, slot_type, label)
SELECT * FROM (
    SELECT 1 AS period_number, '08:00:00' AS start_time, '08:40:00' AS end_time, 'lesson' AS slot_type, 'Period 1' AS label UNION ALL
    SELECT 2,  '08:40:00', '09:20:00', 'lesson',   'Period 2' UNION ALL
    SELECT 3,  '09:20:00', '10:00:00', 'lesson',   'Period 3' UNION ALL
    SELECT 4,  '10:00:00', '10:30:00', 'break',    'Morning Break' UNION ALL
    SELECT 5,  '10:30:00', '11:10:00', 'lesson',   'Period 4' UNION ALL
    SELECT 6,  '11:10:00', '11:50:00', 'lesson',   'Period 5' UNION ALL
    SELECT 7,  '11:50:00', '12:30:00', 'lesson',   'Period 6' UNION ALL
    SELECT 8,  '12:30:00', '13:30:00', 'lunch',    'Lunch Break' UNION ALL
    SELECT 9,  '13:30:00', '14:10:00', 'lesson',   'Period 7' UNION ALL
    SELECT 10, '14:10:00', '14:50:00', 'lesson',   'Period 8' UNION ALL
    SELECT 11, '14:50:00', '15:30:00', 'games',    'Games / Sports'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM time_slots LIMIT 1);


-- ============================================================
-- SECTION 3: timetable_conflicts table
-- ============================================================

CREATE TABLE IF NOT EXISTS timetable_conflicts (
    id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reported_by INT(10) UNSIGNED NOT NULL,
    conflict_type ENUM('teacher_overlap', 'room_overlap', 'class_overlap', 'other') NOT NULL DEFAULT 'other',
    description TEXT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NULL,
    time_slot VARCHAR(50) NULL,
    schedule_id_1 INT(10) UNSIGNED NULL,
    schedule_id_2 INT(10) UNSIGNED NULL,
    status ENUM('reported', 'acknowledged', 'resolved', 'dismissed') NOT NULL DEFAULT 'reported',
    resolved_by INT(10) UNSIGNED NULL,
    resolution_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_reported_by (reported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SECTION 4: exam_schedules - Add missing columns for API compatibility
-- ============================================================

-- Add term_id for term scoping
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'term_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN term_id INT(10) UNSIGNED NULL AFTER id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add academic_year_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'academic_year_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN academic_year_id INT(10) UNSIGNED NULL AFTER term_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add exam_name for display
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'exam_name');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN exam_name VARCHAR(255) NULL AFTER subject_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add exam_type (midterm, end-term, CAT, etc.)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'exam_type');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN exam_type VARCHAR(50) NULL AFTER exam_name',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add duration_minutes
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'duration_minutes');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN duration_minutes INT NULL AFTER end_time',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add venue field
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'venue');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN venue VARCHAR(100) NULL AFTER room_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add supervisor_id (alias for invigilator, matches frontend naming)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'supervisor_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN supervisor_id INT(10) UNSIGNED NULL AFTER invigilator_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add notes field
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'notes');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN notes TEXT NULL AFTER supervisor_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add created_by
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'created_by');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN created_by INT(10) UNSIGNED NULL AFTER notes',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add updated_at
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND COLUMN_NAME = 'updated_at');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE exam_schedules ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Expand status ENUM to include all frontend-expected values
ALTER TABLE exam_schedules MODIFY COLUMN status ENUM('scheduled','upcoming','in_progress','completed','postponed','cancelled') NOT NULL DEFAULT 'scheduled';

-- Add indexes
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND INDEX_NAME = 'idx_exam_term');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE exam_schedules ADD INDEX idx_exam_term (term_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'exam_schedules' AND INDEX_NAME = 'idx_exam_academic_year');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE exam_schedules ADD INDEX idx_exam_academic_year (academic_year_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============================================================
-- SECTION 5: lesson_plans - Add term/year scoping
-- ============================================================

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = 'term_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE lesson_plans ADD COLUMN term_id INT(10) UNSIGNED NULL AFTER class_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lesson_plans' AND COLUMN_NAME = 'academic_year_id');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE lesson_plans ADD COLUMN academic_year_id INT(10) UNSIGNED NULL AFTER term_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add indexes for term/year filtering
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lesson_plans' AND INDEX_NAME = 'idx_lp_term');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE lesson_plans ADD INDEX idx_lp_term (term_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lesson_plans' AND INDEX_NAME = 'idx_lp_academic_year');
SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE lesson_plans ADD INDEX idx_lp_academic_year (academic_year_id)',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;


-- ============================================================
-- SECTION 6: Fix sp_create_exam_schedule stored procedure
-- ============================================================

DROP PROCEDURE IF EXISTS `sp_create_exam_schedule`;
DELIMITER $$
CREATE PROCEDURE `sp_create_exam_schedule` (
    IN `p_term_id` INT UNSIGNED,
    IN `p_exam_type` VARCHAR(50),
    IN `p_start_date` DATE,
    IN `p_end_date` DATE,
    IN `p_created_by` INT UNSIGNED
)
BEGIN
    DECLARE v_current_date DATE;
    DECLARE v_class_id INT UNSIGNED;
    DECLARE v_subject_id INT UNSIGNED;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_academic_year_id INT UNSIGNED;

    DECLARE subject_cursor CURSOR FOR
        SELECT DISTINCT c.id AS class_id, cu.id AS subject_id
        FROM classes c
        CROSS JOIN curriculum_units cu
        WHERE cu.grade_level_id = c.level_id
        AND cu.status = 'active'
        ORDER BY c.id, cu.id;

    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    -- Get academic year from term
    SELECT ay.id INTO v_academic_year_id
    FROM academic_years ay
    JOIN academic_terms at2 ON at2.year = ay.year_code
    WHERE at2.id = p_term_id
    LIMIT 1;

    START TRANSACTION;

    SET v_current_date = p_start_date;

    OPEN subject_cursor;

    schedule_loop: LOOP
        FETCH subject_cursor INTO v_class_id, v_subject_id;

        IF done THEN
            LEAVE schedule_loop;
        END IF;

        INSERT INTO exam_schedules (
            term_id,
            academic_year_id,
            class_id,
            subject_id,
            exam_name,
            exam_type,
            exam_date,
            start_time,
            end_time,
            duration_minutes,
            created_by,
            status
        ) VALUES (
            p_term_id,
            v_academic_year_id,
            v_class_id,
            v_subject_id,
            CONCAT(p_exam_type, ' - ', (SELECT name FROM curriculum_units WHERE id = v_subject_id)),
            p_exam_type,
            v_current_date,
            '08:00:00',
            '11:00:00',
            180,
            p_created_by,
            'scheduled'
        );

        -- Cycle exam dates within the range
        SET v_current_date = DATE_ADD(v_current_date, INTERVAL 1 DAY);
        IF v_current_date > p_end_date THEN
            SET v_current_date = p_start_date;
        END IF;

    END LOOP schedule_loop;

    CLOSE subject_cursor;

    COMMIT;

    SELECT 'Exam schedule created successfully' AS status, ROW_COUNT() AS entries_created;
END$$
DELIMITER ;


-- ============================================================
-- SECTION 7: Seed default rooms if empty
-- ============================================================

INSERT INTO rooms (name, code, type, capacity, building, floor, status)
SELECT * FROM (
    SELECT 'Room 1' as name, 'R001' as code, 'classroom' as type, 40 as capacity, 'Main Block' as building, 'Ground' as floor, 'active' as status UNION ALL
    SELECT 'Room 2', 'R002', 'classroom', 40, 'Main Block', 'Ground', 'active' UNION ALL
    SELECT 'Room 3', 'R003', 'classroom', 40, 'Main Block', 'First', 'active' UNION ALL
    SELECT 'Room 4', 'R004', 'classroom', 40, 'Main Block', 'First', 'active' UNION ALL
    SELECT 'Room 5', 'R005', 'classroom', 40, 'New Block', 'Ground', 'active' UNION ALL
    SELECT 'Room 6', 'R006', 'classroom', 40, 'New Block', 'Ground', 'active' UNION ALL
    SELECT 'Room 7', 'R007', 'classroom', 40, 'New Block', 'First', 'active' UNION ALL
    SELECT 'Room 8', 'R008', 'classroom', 40, 'New Block', 'First', 'active' UNION ALL
    SELECT 'Science Lab', 'LAB01', 'lab', 35, 'Science Block', 'Ground', 'active' UNION ALL
    SELECT 'Computer Lab', 'LAB02', 'lab', 30, 'Science Block', 'First', 'active' UNION ALL
    SELECT 'Library', 'LIB01', 'other', 60, 'Main Block', 'Second', 'active' UNION ALL
    SELECT 'Hall', 'HALL01', 'other', 200, 'Main Block', 'Ground', 'active'
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM rooms LIMIT 1);


-- ============================================================
-- SECTION 8: Refresh views for updated schemas
-- ============================================================

-- Replace legacy tables with proper views
DROP TABLE IF EXISTS vw_upcoming_class_schedules;
CREATE OR REPLACE VIEW vw_upcoming_class_schedules AS
SELECT
    cs.id,
    cs.class_id,
    c.name AS class_name,
    cs.day_of_week,
    cs.start_time,
    cs.end_time,
    cs.period_number,
    cs.subject_id,
    COALESCE(cu.name, '') AS subject_name,
    cs.teacher_id,
    CONCAT(s.first_name, ' ', s.last_name) AS teacher_name,
    cs.room_id,
    r.name AS room_name,
    cs.academic_year_id,
    cs.term_id,
    cs.status
FROM class_schedules cs
JOIN classes c ON cs.class_id = c.id
LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
LEFT JOIN staff s ON cs.teacher_id = s.id
LEFT JOIN rooms r ON cs.room_id = r.id
WHERE cs.status = 'active';

DROP TABLE IF EXISTS vw_upcoming_exam_schedules;
CREATE OR REPLACE VIEW vw_upcoming_exam_schedules AS
SELECT
    es.id,
    es.term_id,
    es.academic_year_id,
    es.class_id,
    c.name AS class_name,
    es.subject_id,
    COALESCE(cu.name, '') AS subject_name,
    es.exam_name,
    es.exam_type,
    es.exam_date,
    es.start_time,
    es.end_time,
    es.duration_minutes,
    es.room_id,
    r.name AS room_name,
    es.venue,
    es.invigilator_id,
    CONCAT(inv.first_name, ' ', inv.last_name) AS invigilator_name,
    es.supervisor_id,
    CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
    es.notes,
    es.status
FROM exam_schedules es
JOIN classes c ON es.class_id = c.id
LEFT JOIN curriculum_units cu ON es.subject_id = cu.id
LEFT JOIN rooms r ON es.room_id = r.id
LEFT JOIN staff inv ON es.invigilator_id = inv.id
LEFT JOIN staff sup ON es.supervisor_id = sup.id
WHERE es.status IN ('scheduled', 'upcoming', 'in_progress');


-- Done
SELECT 'Timetable & Scheduling system migration completed successfully' AS status;
