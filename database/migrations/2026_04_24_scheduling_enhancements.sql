-- ============================================================
-- Scheduling Enhancements Migration — 2026-04-24
-- Fixes schema gaps for timetabling, lesson planning, term flow
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ── 1. academic_year_id on academic_terms ──────────────────────────────────
ALTER TABLE academic_terms
    ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED DEFAULT NULL AFTER id,
    ADD COLUMN IF NOT EXISTS midterm_break_start DATE DEFAULT NULL AFTER end_date,
    ADD COLUMN IF NOT EXISTS midterm_break_end   DATE DEFAULT NULL AFTER midterm_break_start,
    ADD COLUMN IF NOT EXISTS opening_date        DATE DEFAULT NULL AFTER midterm_break_end,
    ADD COLUMN IF NOT EXISTS closing_date        DATE DEFAULT NULL AFTER opening_date;

-- Backfill academic_year_id from year column (COLLATE resolves utf8mb4 mismatch)
UPDATE academic_terms t
JOIN   academic_years y ON y.year_code COLLATE utf8mb4_general_ci = CAST(t.year AS CHAR)
SET    t.academic_year_id = y.id
WHERE  t.academic_year_id IS NULL;

-- Add FK if not already there
-- (use IF NOT EXISTS pattern via procedure)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'academic_terms'
    AND CONSTRAINT_NAME = 'fk_terms_academic_year'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE academic_terms ADD CONSTRAINT fk_terms_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. subject_time_allocations ────────────────────────────────────────────
-- Defines how many periods/week each subject gets in each class for a term
CREATE TABLE IF NOT EXISTS subject_time_allocations (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    class_id        INT UNSIGNED NOT NULL,
    subject_id      INT UNSIGNED DEFAULT NULL,
    subject_name    VARCHAR(100) DEFAULT NULL COMMENT 'Fallback if no subjects table FK',
    academic_year_id INT UNSIGNED DEFAULT NULL,
    term_id         INT UNSIGNED DEFAULT NULL,
    periods_per_week TINYINT UNSIGNED NOT NULL DEFAULT 5,
    teacher_id      INT UNSIGNED DEFAULT NULL,
    is_active       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_class_subject_term (class_id, subject_name, term_id),
    KEY idx_class_id   (class_id),
    KEY idx_teacher_id (teacher_id),
    KEY idx_term_id    (term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Periods per week per subject per class — basis for timetable generation';

-- ── 3. schemes_of_work ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS schemes_of_work (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    teacher_id      INT UNSIGNED NOT NULL,
    class_id        INT UNSIGNED DEFAULT NULL,
    subject_id      INT UNSIGNED DEFAULT NULL,
    subject_name    VARCHAR(100) DEFAULT NULL,
    learning_area_id INT UNSIGNED DEFAULT NULL,
    academic_year_id INT UNSIGNED DEFAULT NULL,
    term_id         INT UNSIGNED DEFAULT NULL,
    term_number     TINYINT UNSIGNED DEFAULT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT DEFAULT NULL,
    week_number     TINYINT UNSIGNED DEFAULT NULL,
    strand          VARCHAR(255) DEFAULT NULL,
    sub_strand      VARCHAR(255) DEFAULT NULL,
    learning_outcomes TEXT DEFAULT NULL,
    key_vocabulary  TEXT DEFAULT NULL,
    resources       TEXT DEFAULT NULL,
    activities      TEXT DEFAULT NULL,
    assessment_methods VARCHAR(255) DEFAULT NULL,
    status          ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    approved_by     INT UNSIGNED DEFAULT NULL,
    approved_at     DATETIME DEFAULT NULL,
    rejection_reason TEXT DEFAULT NULL,
    file_path       VARCHAR(500) DEFAULT NULL COMMENT 'Uploaded scheme document',
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_teacher_id      (teacher_id),
    KEY idx_class_id        (class_id),
    KEY idx_term_id         (term_id),
    KEY idx_academic_year   (academic_year_id),
    KEY idx_status          (status),
    KEY idx_week_number     (week_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Term-by-term scheme of work per teacher/subject/class';

-- ── 4. term_transition_log ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS term_transition_log (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    from_term_id    INT UNSIGNED DEFAULT NULL,
    to_term_id      INT UNSIGNED DEFAULT NULL,
    academic_year_id INT UNSIGNED DEFAULT NULL,
    action          ENUM('close_term','activate_term','rollover_timetable',
                         'rollover_schemes','generate_exam_schedule',
                         'notify_staff','full_transition') NOT NULL,
    status          ENUM('pending','in_progress','completed','failed') NOT NULL DEFAULT 'pending',
    details         JSON DEFAULT NULL,
    error_message   TEXT DEFAULT NULL,
    performed_by    INT UNSIGNED DEFAULT NULL,
    performed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_from_term (from_term_id),
    KEY idx_to_term   (to_term_id),
    KEY idx_action    (action),
    KEY idx_status    (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. timetable_templates ─────────────────────────────────────────────────
-- Saved timetable patterns that can be rolled over between terms
CREATE TABLE IF NOT EXISTS timetable_templates (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name            VARCHAR(100) NOT NULL,
    description     TEXT DEFAULT NULL,
    template_data   JSON NOT NULL COMMENT 'Full class_schedule array for the template',
    applies_to      ENUM('school','class','level') DEFAULT 'school',
    class_id        INT UNSIGNED DEFAULT NULL,
    created_by      INT UNSIGNED DEFAULT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_applies_to (applies_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. rooms table already exists — skip creation ─────────────────────────

-- ── 7. Seed rooms if empty (use existing schema: type, building) ──────────
INSERT IGNORE INTO rooms (name, code, type, capacity, building, status)
SELECT * FROM (
    SELECT 'Room 1A','R1A','classroom',40,'Block A','active' UNION ALL
    SELECT 'Room 1B','R1B','classroom',40,'Block A','active' UNION ALL
    SELECT 'Room 2A','R2A','classroom',40,'Block A','active' UNION ALL
    SELECT 'Room 2B','R2B','classroom',40,'Block A','active' UNION ALL
    SELECT 'Room 3A','R3A','classroom',40,'Block B','active' UNION ALL
    SELECT 'Room 4A','R4A','classroom',40,'Block B','active' UNION ALL
    SELECT 'Room 5A','R5A','classroom',42,'Block B','active' UNION ALL
    SELECT 'Science Lab','LAB1','lab',35,'Block C','active' UNION ALL
    SELECT 'Computer Lab','CLAB','lab',30,'Block C','active' UNION ALL
    SELECT 'Assembly Hall','HALL','other',500,'Main Building','active' UNION ALL
    SELECT 'Library','LIB','other',60,'Main Building','active'
) t WHERE (SELECT COUNT(*) FROM rooms) = 0;

-- ── 8. Useful views ────────────────────────────────────────────────────────

-- Staff weekly teaching load (periods taught per teacher per term)
CREATE OR REPLACE VIEW vw_teacher_weekly_load AS
SELECT
    s.id              AS teacher_id,
    CONCAT(s.first_name,' ',s.last_name) AS teacher_name,
    s.position AS designation,
    cs.term_id,
    cs.academic_year_id,
    COUNT(*)          AS total_periods,
    COUNT(DISTINCT cs.class_id) AS classes_taught,
    COUNT(DISTINCT cs.subject_id) AS subjects_taught,
    SUM(CASE WHEN cs.day_of_week='Monday'    THEN 1 ELSE 0 END) AS mon_periods,
    SUM(CASE WHEN cs.day_of_week='Tuesday'   THEN 1 ELSE 0 END) AS tue_periods,
    SUM(CASE WHEN cs.day_of_week='Wednesday' THEN 1 ELSE 0 END) AS wed_periods,
    SUM(CASE WHEN cs.day_of_week='Thursday'  THEN 1 ELSE 0 END) AS thu_periods,
    SUM(CASE WHEN cs.day_of_week='Friday'    THEN 1 ELSE 0 END) AS fri_periods
FROM staff s
JOIN class_schedules cs ON cs.teacher_id = s.id AND cs.status = 'active'
GROUP BY s.id, cs.term_id, cs.academic_year_id;

-- Class timetable coverage (% of lesson slots filled)
CREATE OR REPLACE VIEW vw_class_timetable_coverage AS
SELECT
    c.id              AS class_id,
    c.name AS class_name,
    NULL AS stream_name,
    cs.term_id,
    cs.academic_year_id,
    COUNT(cs.id)      AS slots_filled,
    (SELECT COUNT(*) * 5
     FROM time_slots ts
     WHERE ts.slot_type = 'lesson' AND ts.is_active = 1
    )                 AS total_lesson_slots,
    ROUND(
        COUNT(cs.id) * 100.0 /
        NULLIF((SELECT COUNT(*) * 5 FROM time_slots WHERE slot_type='lesson' AND is_active=1), 0)
    , 1)              AS coverage_pct
FROM classes c
LEFT JOIN class_schedules cs ON cs.class_id = c.id AND cs.status = 'active'
GROUP BY c.id, cs.term_id, cs.academic_year_id;

-- Scheme of work completion per teacher per term
CREATE OR REPLACE VIEW vw_scheme_completion AS
SELECT
    s.id          AS teacher_id,
    CONCAT(s.first_name,' ',s.last_name) AS teacher_name,
    sw.term_id,
    sw.subject_name,
    COUNT(sw.id)  AS total_schemes,
    SUM(CASE WHEN sw.status='approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN sw.status='submitted' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN sw.status='draft' THEN 1 ELSE 0 END)     AS drafts
FROM staff s
JOIN schemes_of_work sw ON sw.teacher_id = s.id
GROUP BY s.id, sw.term_id, sw.subject_name;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Summary ────────────────────────────────────────────────────────────────
SELECT 'Migration 2026_04_24_scheduling_enhancements applied' AS status;
