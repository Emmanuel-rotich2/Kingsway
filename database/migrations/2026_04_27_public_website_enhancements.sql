-- =============================================================================
-- Migration: 2026_04_27_public_website_enhancements.sql
-- Description: Add school_settings (DB-driven stats/config) and full
--              admission_applications table with all applicant fields.
-- Run: mysql -u root -p KingsWayAcademy < database/migrations/2026_04_27_public_website_enhancements.sql
-- =============================================================================

USE KingsWayAcademy;

-- ── 1. school_settings (key-value store for public website config) ────────────
CREATE TABLE IF NOT EXISTS `school_settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         DEFAULT NULL,
  `label`         VARCHAR(200) DEFAULT NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_settings` (`setting_key`, `setting_value`, `label`) VALUES
-- Homepage stat counters
('stat_students',       '1200',  'Total Students Enrolled'),
('stat_students_suffix','+'  ,   'Students count suffix'),
('stat_teachers',       '80',    'Qualified Teachers'),
('stat_teachers_suffix','+'  ,   'Teachers count suffix'),
('stat_pass_rate',      '98',    'Exam Pass Rate (%)'),
('stat_awards',         '30',    'Awards & Honours'),
('stat_years',          '20',    'Years of Excellence'),
-- Contact info (used across pages)
('school_phone',        '0720 113 030',                             'Main Phone Number'),
('school_email',        'info@kingswaypreparatoryschool.sc.ke',     'Email Address'),
('school_address',      'Londiani, Kericho County, Kenya',          'Physical Address'),
('school_name',         'Kingsway Preparatory School',              'Full School Name'),
-- Admissions page info
('admissions_response', 'Within 24 working hours',  'Admissions Response Time'),
('admissions_age_range','4 – 15 years (PP1 – Grade 9)', 'Admissions Age Range'),
-- Grade spaces (Available / Limited / Full / Closed)
('spaces_PP1',       'Limited',   'PP1 Spaces Status'),
('spaces_PP2',       'Available', 'PP2 Spaces Status'),
('spaces_Grade1',    'Available', 'Grade 1 Spaces Status'),
('spaces_Grade2_3',  'Available', 'Grade 2–3 Spaces Status'),
('spaces_Grade4_6',  'Limited',   'Grade 4–6 Spaces Status'),
('spaces_Grade7_9',  'Limited',   'Grade 7–9 Spaces Status'),
-- Fee overview
('fees_day_from',     'KSh 18,000 / term', 'Day Scholar fee from'),
('fees_boarding_from','KSh 42,000 / term', 'Full Boarding fee from');

-- ── 2. admission_applications (full public application form) ──────────────────
CREATE TABLE IF NOT EXISTS `admission_applications` (
  `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
  -- Child / Student information
  `child_full_name`     VARCHAR(200) NOT NULL,
  `child_dob`           DATE         DEFAULT NULL,
  `child_gender`        ENUM('male','female','other') DEFAULT NULL,
  `child_nationality`   VARCHAR(100) NOT NULL DEFAULT 'Kenyan',
  `child_prev_school`   VARCHAR(200) DEFAULT NULL,
  `child_prev_grade`    VARCHAR(50)  DEFAULT NULL,
  -- Parent / Guardian information
  `parent_name`         VARCHAR(200) NOT NULL,
  `parent_relationship` VARCHAR(50)  DEFAULT NULL,
  `parent_id_number`    VARCHAR(50)  DEFAULT NULL,
  `parent_phone`        VARCHAR(30)  NOT NULL,
  `parent_alt_phone`    VARCHAR(30)  DEFAULT NULL,
  `parent_email`        VARCHAR(200) DEFAULT NULL,
  `parent_address`      TEXT         DEFAULT NULL,
  -- Application preferences
  `grade_applying`      VARCHAR(30)  NOT NULL,
  `boarding_preference` ENUM('day','full_boarding','weekly_boarding') NOT NULL DEFAULT 'day',
  `preferred_start`     VARCHAR(50)  DEFAULT NULL,
  `referral_source`     VARCHAR(100) DEFAULT NULL,
  `special_needs`       TEXT         DEFAULT NULL,
  -- System fields
  `application_ref`     VARCHAR(20)  DEFAULT NULL,
  `status`              ENUM('received','reviewing','assessment_scheduled','offer_sent','enrolled','declined','waitlisted') NOT NULL DEFAULT 'received',
  `ip_address`          VARCHAR(45)  DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ref` (`application_ref`),
  KEY `idx_status`  (`status`),
  KEY `idx_grade`   (`grade_applying`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
