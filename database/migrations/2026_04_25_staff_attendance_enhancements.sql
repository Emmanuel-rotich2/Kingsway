-- =============================================================================
-- Staff Attendance System Enhancements
-- Date: 2026-04-25
-- Fixes:
--   1. staff_attendance → add academic_year_id, shift, expected_check_in
--   2. staff           → add work_start_time, work_end_time
--   3. Unique constraint per staff + date + shift
--   4. Backfill academic_year_id from date range
--   5. Views: daily register, monthly summary, anomaly detection
-- =============================================================================

-- 1. Extend staff table with expected work hours
ALTER TABLE staff
  ADD COLUMN IF NOT EXISTS work_start_time TIME DEFAULT '08:00:00' COMMENT 'Expected daily check-in',
  ADD COLUMN IF NOT EXISTS work_end_time   TIME DEFAULT '17:00:00' COMMENT 'Expected daily check-out',
  ADD COLUMN IF NOT EXISTS late_threshold_minutes INT DEFAULT 15   COMMENT 'Minutes after work_start_time = late';

-- 2. Extend staff_attendance with year + shift + expected_check_in
ALTER TABLE staff_attendance
  ADD COLUMN IF NOT EXISTS academic_year_id    INT UNSIGNED AFTER date,
  ADD COLUMN IF NOT EXISTS shift               ENUM('morning','afternoon','evening','night','full_day')
                                               NOT NULL DEFAULT 'full_day' AFTER academic_year_id,
  ADD COLUMN IF NOT EXISTS expected_check_in   TIME AFTER check_in_time;

-- 3. Unique key: one attendance record per staff per date per shift
ALTER TABLE staff_attendance
  DROP INDEX IF EXISTS uq_staff_date_shift,
  ADD UNIQUE KEY uq_staff_date_shift (staff_id, date, shift);

-- 4. Backfill academic_year_id from date ranges in academic_terms
UPDATE staff_attendance sa
JOIN academic_terms at2 ON sa.date BETWEEN at2.start_date AND at2.end_date
SET sa.academic_year_id = at2.academic_year_id
WHERE sa.academic_year_id IS NULL;

-- 5. Backfill academic_year_id for rows outside term dates (use year of date)
UPDATE staff_attendance sa
JOIN academic_years ay ON YEAR(sa.date) = ay.year_code
SET sa.academic_year_id = ay.id
WHERE sa.academic_year_id IS NULL;

-- 6. Staff shift schedule config (which shifts which staff work — for boarding)
CREATE TABLE IF NOT EXISTS staff_shift_assignments (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  staff_id     INT UNSIGNED NOT NULL,
  shift        ENUM('morning','afternoon','evening','night','full_day') NOT NULL DEFAULT 'full_day',
  day_of_week  ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  effective_from DATE NOT NULL,
  effective_to   DATE,
  academic_year_id INT UNSIGNED,
  assigned_by  INT UNSIGNED,
  notes        TEXT,
  status       ENUM('active','inactive') DEFAULT 'active',
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_staff (staff_id),
  INDEX idx_year  (academic_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Late arrival threshold overrides per department
CREATE TABLE IF NOT EXISTS department_attendance_rules (
  id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id            INT UNSIGNED NOT NULL UNIQUE,
  work_start_time          TIME DEFAULT '08:00:00',
  work_end_time            TIME DEFAULT '17:00:00',
  late_threshold_minutes   INT  DEFAULT 15,
  half_day_mark_time       TIME DEFAULT '13:00:00' COMMENT 'If check_in > this → half day',
  weekend_duty_required    TINYINT(1) DEFAULT 0,
  notes                    TEXT,
  updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (department_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- VIEWS
-- =============================================================================

-- V1: Staff daily register — full picture for any given date
--     Includes: leave status, off-day (both roster + recurring pattern),
--               duty assignment, expected vs actual check-in, effective status
CREATE OR REPLACE VIEW vw_staff_daily_register AS
SELECT
  s.id                AS staff_id,
  s.staff_no,
  CONCAT(s.first_name,' ',s.last_name)   AS staff_name,
  s.first_name,
  s.last_name,
  s.position,
  s.work_start_time,
  s.work_end_time,
  s.late_threshold_minutes,
  d.id                AS department_id,
  d.name              AS department_name,
  sc.category_name    AS staff_category,
  -- Attendance record (if marked)
  sa.id               AS attendance_id,
  sa.date,
  sa.status           AS marked_status,
  sa.shift,
  sa.check_in_time,
  sa.check_out_time,
  sa.expected_check_in,
  sa.absence_reason,
  sa.notes            AS attendance_notes,
  sa.academic_year_id,
  -- Leave info
  sl.id               AS leave_id,
  lt.name             AS leave_type,
  sl.status           AS leave_status,
  sl.start_date       AS leave_start,
  sl.end_date         AS leave_end,
  -- Relief staff when on leave
  CONCAT(rs.first_name,' ',rs.last_name) AS relief_staff_name,
  -- Duty roster for this date
  sdr.id              AS duty_roster_id,
  sdt.code            AS duty_code,
  sdt.name            AS duty_name,
  sdr.shift           AS duty_shift,
  sdr.start_time      AS duty_start,
  sdr.end_time        AS duty_end,
  sdr.location        AS duty_location,
  -- Effective status (combines all signals)
  CASE
    WHEN sl.id IS NOT NULL AND sl.status = 'approved'      THEN 'on_leave'
    WHEN sdt.code IN ('OFF','WEEKEND_OFF')                  THEN 'off_day'
    WHEN sa.status = 'present'                              THEN 'present'
    WHEN sa.status = 'absent'  AND sa.absence_reason='leave' THEN 'on_leave'
    WHEN sa.status = 'absent'  AND sa.absence_reason='off_day' THEN 'off_day'
    WHEN sa.status = 'absent'                               THEN 'absent'
    WHEN sa.status = 'late'                                 THEN 'late'
    WHEN sa.id IS NULL                                      THEN 'not_marked'
    ELSE sa.status
  END                 AS effective_status,
  -- Late detection: was check_in after expected start + threshold?
  CASE
    WHEN sa.check_in_time IS NOT NULL AND s.work_start_time IS NOT NULL
         AND sa.check_in_time > ADDTIME(s.work_start_time, SEC_TO_TIME(COALESCE(s.late_threshold_minutes,15)*60))
    THEN 1 ELSE 0
  END                 AS is_late,
  -- Minutes late
  CASE
    WHEN sa.check_in_time IS NOT NULL AND s.work_start_time IS NOT NULL
         AND sa.check_in_time > s.work_start_time
    THEN TIME_TO_SEC(TIMEDIFF(sa.check_in_time, s.work_start_time)) / 60
    ELSE 0
  END                 AS minutes_late
FROM staff s
LEFT JOIN departments     d   ON d.id   = s.department_id
LEFT JOIN staff_categories sc ON sc.id  = s.staff_category_id
-- attendance for this date: joined dynamically — this view is date-independent
-- (join date externally: WHERE sa.date = ? OR use a subquery)
LEFT JOIN staff_attendance sa ON sa.staff_id = s.id
LEFT JOIN staff_leaves     sl ON sl.staff_id = s.id
  AND sa.date BETWEEN sl.start_date AND sl.end_date
  AND sl.status = 'approved'
LEFT JOIN leave_types      lt ON lt.id = sl.leave_type_id
LEFT JOIN staff            rs ON rs.id = sl.relief_staff_id
LEFT JOIN staff_duty_roster sdr ON sdr.staff_id = s.id AND sdr.date = sa.date
LEFT JOIN staff_duty_types sdt  ON sdt.id = sdr.duty_type_id
WHERE s.status = 'active';

-- V2: Staff attendance by year + month — for payroll and HR analytics
CREATE OR REPLACE VIEW vw_staff_monthly_summary AS
SELECT
  sa.staff_id,
  sa.academic_year_id,
  ay.year_code,
  YEAR(sa.date)   AS attendance_year,
  MONTH(sa.date)  AS attendance_month,
  MONTHNAME(sa.date) AS month_name,
  COUNT(CASE WHEN sa.status = 'present' THEN 1 END)  AS days_present,
  COUNT(CASE WHEN sa.status = 'absent' AND sa.absence_reason NOT IN ('leave','off_day') THEN 1 END) AS days_unauthorized_absent,
  COUNT(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'leave' THEN 1 END) AS days_on_leave,
  COUNT(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'off_day' THEN 1 END) AS days_off,
  COUNT(CASE WHEN sa.status = 'late'   THEN 1 END)  AS days_late,
  COUNT(sa.id)    AS total_days_marked,
  ROUND(
    COUNT(CASE WHEN sa.status = 'present' OR sa.status = 'late' THEN 1 END) * 100.0
    / NULLIF(COUNT(CASE WHEN sa.absence_reason NOT IN ('off_day') OR sa.absence_reason IS NULL THEN 1 END), 0),
    1
  )               AS attendance_pct,
  SUM(CASE WHEN sa.check_in_time IS NOT NULL AND s.work_start_time IS NOT NULL
                AND sa.check_in_time > s.work_start_time
           THEN TIME_TO_SEC(TIMEDIFF(sa.check_in_time, s.work_start_time)) / 60
           ELSE 0 END) AS total_minutes_late
FROM staff_attendance sa
JOIN staff s ON s.id = sa.staff_id
LEFT JOIN academic_years ay ON ay.id = sa.academic_year_id
GROUP BY sa.staff_id, sa.academic_year_id, YEAR(sa.date), MONTH(sa.date);

-- V3: Off-day matrix per staff for current week
--     Combines both staff_off_day_patterns AND staff_duty_roster (OFF/WEEKEND_OFF)
CREATE OR REPLACE VIEW vw_staff_off_day_schedule AS
SELECT
  s.id          AS staff_id,
  CONCAT(s.first_name,' ',s.last_name) AS staff_name,
  s.department_id,
  'pattern'     AS source,
  sop.day_of_week,
  sop.effective_from,
  sop.effective_to,
  sop.reason
FROM staff s
JOIN staff_off_day_patterns sop ON sop.staff_id = s.id
  AND sop.is_off = 1
  AND CURDATE() BETWEEN sop.effective_from AND COALESCE(sop.effective_to, '2099-12-31')
UNION ALL
SELECT
  s.id,
  CONCAT(s.first_name,' ',s.last_name),
  s.department_id,
  'roster',
  DAYNAME(sdr.date),
  sdr.date,
  sdr.date,
  sdt.name
FROM staff s
JOIN staff_duty_roster sdr ON sdr.staff_id = s.id
  AND sdr.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
JOIN staff_duty_types sdt ON sdt.id = sdr.duty_type_id AND sdt.code IN ('OFF','WEEKEND_OFF');

-- V4: Chronic absentees + late arrivals (rolling 30 days)
CREATE OR REPLACE VIEW vw_staff_attendance_anomalies AS
SELECT
  sa.staff_id,
  CONCAT(s.first_name,' ',s.last_name) AS staff_name,
  s.staff_no,
  d.name   AS department,
  COUNT(CASE WHEN sa.status = 'absent' AND sa.absence_reason = 'unauthorized' THEN 1 END) AS unauthorized_absences,
  COUNT(CASE WHEN sa.status = 'late'   THEN 1 END) AS late_arrivals,
  COUNT(sa.id) AS total_days,
  ROUND(COUNT(CASE WHEN sa.status='present' OR sa.status='late' THEN 1 END)*100.0/NULLIF(COUNT(sa.id),0),1) AS pct,
  MAX(CASE WHEN sa.check_in_time IS NOT NULL AND s.work_start_time IS NOT NULL
                AND sa.check_in_time > s.work_start_time
           THEN TIME_TO_SEC(TIMEDIFF(sa.check_in_time, s.work_start_time))/60
      END) AS max_minutes_late
FROM staff_attendance sa
JOIN staff       s ON s.id = sa.staff_id
LEFT JOIN departments d ON d.id = s.department_id
WHERE sa.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY sa.staff_id
HAVING unauthorized_absences >= 2 OR late_arrivals >= 3;
