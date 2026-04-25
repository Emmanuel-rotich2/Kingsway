-- =============================================================================
-- Attendance Register & Calendar System
-- Date: 2026-04-25
-- Fixes:
--   1. student_attendance → add academic_year_id, register_type
--   2. school_calendar   → seed 2026 school term dates and term breaks
--   3. Unique constraint  → prevent duplicate marks per student+date+session+register
--   4. Views             → calendar context, year-differentiated attendance summaries
-- =============================================================================

-- 1. Add missing columns to student_attendance
ALTER TABLE student_attendance
  ADD COLUMN IF NOT EXISTS academic_year_id INT UNSIGNED AFTER term_id,
  ADD COLUMN IF NOT EXISTS register_type    ENUM('class','boarding','activity') NOT NULL DEFAULT 'class' AFTER academic_year_id;

-- 2. Backfill academic_year_id from existing term_id (safe, term_id is unique per term)
UPDATE student_attendance sa
JOIN academic_terms at2 ON at2.id = sa.term_id
SET sa.academic_year_id = at2.academic_year_id
WHERE sa.academic_year_id IS NULL AND sa.term_id IS NOT NULL;

-- 3. Unique key: one mark per student, per date, per session, per register type
--    (prevents double-marking; ON DUPLICATE KEY UPDATE used during bulk mark)
ALTER TABLE student_attendance
  DROP INDEX IF EXISTS uq_attendance_mark,
  ADD UNIQUE KEY uq_attendance_mark (student_id, date, session_id, register_type);

-- 4. Seed school_calendar for 2026 term dates and breaks
--    Term 1:  06 Jan – 04 Apr 2026   (Break: 05 Apr – 27 Apr)
--    Term 2:  28 Apr – 01 Aug 2026   (Break: 02 Aug – 24 Aug)
--    Term 3:  25 Aug – 28 Nov 2026

-- Term dates → school_day type (affects all)
INSERT IGNORE INTO school_calendar
  (date, day_type, title, academic_year_id, term_id, affects_day_students, affects_boarders, requires_attendance)
VALUES
  ('2026-01-06', 'school_day', 'First Day – Term 1',       5, 7, 1, 1, 1),
  ('2026-04-04', 'school_day', 'Last Day – Term 1',        5, 7, 1, 1, 1),
  ('2026-04-28', 'school_day', 'First Day – Term 2',       5, 8, 1, 1, 1),
  ('2026-08-01', 'school_day', 'Last Day – Term 2',        5, 8, 1, 1, 1),
  ('2026-08-25', 'school_day', 'First Day – Term 3',       5, 9, 1, 1, 1),
  ('2026-11-28', 'school_day', 'Last Day – Term 3',        5, 9, 1, 1, 1);

-- Term breaks → school_holiday (day students don't attend; boarders may stay)
INSERT IGNORE INTO school_calendar
  (date, day_type, title, academic_year_id, term_id, affects_day_students, affects_boarders, requires_attendance)
VALUES
  ('2026-04-05', 'school_holiday', 'Term 1 Break',  5, NULL, 1, 0, 0),
  ('2026-04-27', 'school_holiday', 'Term 1 Break',  5, NULL, 1, 0, 0),
  ('2026-08-02', 'school_holiday', 'Term 2 Break',  5, NULL, 1, 0, 0),
  ('2026-08-24', 'school_holiday', 'Term 2 Break',  5, NULL, 1, 0, 0),
  ('2026-11-29', 'school_holiday', 'End of Year',   5, NULL, 1, 0, 0),
  ('2026-12-31', 'school_holiday', 'End of Year',   5, NULL, 1, 0, 0);

-- 5. Add a school_calendar_config table for week pattern rules
--    (eliminates need to seed every weekend; rules evaluated in code)
CREATE TABLE IF NOT EXISTS school_week_config (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_year_id INT UNSIGNED NOT NULL,
  saturday_classes TINYINT(1)  DEFAULT 0 COMMENT '1 = Saturdays are school days (SATURDAY_CLASS session)',
  sunday_boarding  TINYINT(1)  DEFAULT 1 COMMENT '1 = Boarding roll call required on Sundays',
  class_days       JSON        DEFAULT '["Monday","Tuesday","Wednesday","Thursday","Friday"]',
  boarding_days    JSON        DEFAULT '["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"]',
  notes            TEXT,
  created_at       TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_year (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO school_week_config (academic_year_id, saturday_classes, sunday_boarding)
VALUES (5, 0, 1); -- 2026: No Saturday classes, boarding marked on Sundays

-- 6. Add an index to speed up date-range attendance queries
ALTER TABLE student_attendance
  ADD INDEX IF NOT EXISTS idx_year_term   (academic_year_id, term_id),
  ADD INDEX IF NOT EXISTS idx_date_class  (date, class_id),
  ADD INDEX IF NOT EXISTS idx_reg_type    (register_type, date);

-- =============================================================================
-- VIEWS
-- =============================================================================

-- V1: School day context for any date (answers: is it a class day? a boarding day?)
CREATE OR REPLACE VIEW vw_school_day_context AS
SELECT
  sc.date,
  sc.day_type,
  sc.title              AS event_name,
  sc.affects_day_students,
  sc.affects_boarders,
  sc.requires_attendance,
  sc.academic_year_id,
  sc.term_id,
  DAYNAME(sc.date)      AS day_name,
  DAYOFWEEK(sc.date)    AS day_number,
  CASE
    WHEN sc.day_type IN ('school_day','half_day','exam_day','special_event') THEN 1
    ELSE 0
  END                   AS is_class_day,
  CASE
    WHEN sc.day_type NOT IN ('school_holiday') THEN 1    -- boarding runs except during school holidays/breaks
    ELSE 0
  END                   AS is_boarding_day
FROM school_calendar sc;

-- V2: Attendance register differentiated by year + term + class + register type
--     This is the key view for "same student, different year/class" queries
CREATE OR REPLACE VIEW vw_attendance_by_context AS
SELECT
  sa.id,
  sa.student_id,
  sa.date,
  sa.status,
  sa.register_type,
  sa.absence_reason,
  sa.check_in_time,
  sa.notes,
  sa.marked_by,
  -- Academic context (where the student was at time of attendance)
  sa.academic_year_id,
  sa.term_id,
  sa.class_id,
  sa.session_id,
  ay.year_code          AS academic_year_code,
  ay.year_name,
  at2.term_number,
  at2.name              AS term_name,
  c.name                AS class_name,
  cs.stream_name,
  -- Session context
  ass.code              AS session_code,
  ass.name              AS session_name,
  ass.session_type,
  ass.applies_to,
  -- Student info
  s.admission_no,
  CONCAT(s.first_name,' ',s.last_name) AS student_name,
  st.name               AS student_type,
  st.code               AS student_type_code
FROM student_attendance sa
JOIN students          s   ON s.id   = sa.student_id
LEFT JOIN academic_years   ay  ON ay.id  = sa.academic_year_id
LEFT JOIN academic_terms   at2 ON at2.id = sa.term_id
LEFT JOIN classes          c   ON c.id   = sa.class_id
LEFT JOIN class_streams    cs  ON cs.id  = s.stream_id
LEFT JOIN attendance_sessions ass ON ass.id = sa.session_id
LEFT JOIN student_types    st  ON st.id  = s.student_type_id;

-- V3: Attendance summary per student per term per year (for report cards, promotions)
CREATE OR REPLACE VIEW vw_student_term_attendance_summary AS
SELECT
  sa.student_id,
  sa.academic_year_id,
  sa.term_id,
  sa.class_id,
  sa.register_type,
  ay.year_code,
  at2.term_number,
  at2.name              AS term_name,
  c.name                AS class_name,
  COUNT(CASE WHEN sa.register_type = 'class' THEN sa.id END)   AS class_days_marked,
  COUNT(CASE WHEN sa.register_type = 'class' AND sa.status = 'present' THEN 1 END) AS class_days_present,
  COUNT(CASE WHEN sa.register_type = 'class' AND sa.status = 'absent'  THEN 1 END) AS class_days_absent,
  COUNT(CASE WHEN sa.register_type = 'class' AND sa.status = 'late'    THEN 1 END) AS class_days_late,
  COUNT(CASE WHEN sa.register_type = 'boarding' THEN sa.id END) AS boarding_nights_marked,
  COUNT(CASE WHEN sa.register_type = 'boarding' AND sa.status = 'present' THEN 1 END) AS boarding_nights_present,
  COUNT(CASE WHEN sa.register_type = 'boarding' AND sa.status = 'absent'  THEN 1 END) AS boarding_nights_absent,
  ROUND(
    COUNT(CASE WHEN sa.register_type='class' AND sa.status='present' THEN 1 END) * 100.0 /
    NULLIF(COUNT(CASE WHEN sa.register_type='class' THEN 1 END), 0),
    1
  )                     AS class_attendance_pct,
  ROUND(
    COUNT(CASE WHEN sa.register_type='boarding' AND sa.status='present' THEN 1 END) * 100.0 /
    NULLIF(COUNT(CASE WHEN sa.register_type='boarding' THEN 1 END), 0),
    1
  )                     AS boarding_attendance_pct
FROM student_attendance sa
JOIN academic_years  ay  ON ay.id = sa.academic_year_id
JOIN academic_terms  at2 ON at2.id = sa.term_id
LEFT JOIN classes    c   ON c.id  = sa.class_id
GROUP BY sa.student_id, sa.academic_year_id, sa.term_id, sa.class_id, sa.register_type;

-- V4: Expected school days per term (used for % calculation denominator)
--     Counts calendar days where class is expected, per term
CREATE OR REPLACE VIEW vw_term_expected_days AS
SELECT
  sc.term_id,
  sc.academic_year_id,
  COUNT(*) AS expected_class_days
FROM school_calendar sc
WHERE sc.day_type IN ('school_day','half_day','exam_day')
  AND sc.affects_day_students = 1
  AND sc.term_id IS NOT NULL
GROUP BY sc.term_id, sc.academic_year_id;

-- V5: Today's boarding students who need roll call
CREATE OR REPLACE VIEW vw_boarding_roll_call_today AS
SELECT
  s.id AS student_id,
  s.admission_no,
  CONCAT(s.first_name,' ',s.last_name) AS student_name,
  cs.stream_name,
  c.name AS class_name,
  d.name AS dormitory_name,
  ds.bed_number,
  st.name AS student_type,
  -- Today's class attendance status
  ca.status AS class_status,
  -- Today's boarding attendance status (per session, latest)
  ba.status AS boarding_status,
  ba.session_id AS boarding_session_id
FROM students s
JOIN class_streams cs ON cs.id = s.stream_id
JOIN classes c ON c.id = cs.class_id
JOIN student_types st ON st.id = s.student_type_id
LEFT JOIN dormitory_assignments ds ON ds.student_id = s.id AND ds.status = 'active'
LEFT JOIN dormitories d ON d.id = ds.dormitory_id
LEFT JOIN student_attendance ca ON ca.student_id = s.id
  AND ca.date = CURDATE() AND ca.register_type = 'class'
LEFT JOIN boarding_attendance ba ON ba.student_id = s.id
  AND ba.date = CURDATE()
WHERE s.status = 'active'
  AND st.code IN ('full_boarder','weekly_boarder');
