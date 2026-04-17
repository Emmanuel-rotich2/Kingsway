-- ============================================================
-- Migration: Library, Health, and Assignments Modules
-- Date: 2026-04-16
-- Idempotent: uses IF NOT EXISTS / INSERT IGNORE
-- ============================================================

-- ============================================================
-- 1. LIBRARY MODULE
-- ============================================================

CREATE TABLE IF NOT EXISTS `library_categories` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_lib_cat_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_books` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `isbn`            VARCHAR(20)  DEFAULT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `author`          VARCHAR(255) NOT NULL,
  `publisher`       VARCHAR(255) DEFAULT NULL,
  `edition`         VARCHAR(50)  DEFAULT NULL,
  `publication_year` SMALLINT UNSIGNED DEFAULT NULL,
  `category_id`     INT UNSIGNED DEFAULT NULL,
  `location_shelf`  VARCHAR(50)  DEFAULT NULL,
  `total_copies`    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `available_copies` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `description`     TEXT,
  `cover_image_url` VARCHAR(512) DEFAULT NULL,
  `status`          ENUM('active','inactive','lost','damaged') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`      DATETIME DEFAULT NULL,
  KEY `idx_lib_books_category` (`category_id`),
  KEY `idx_lib_books_status` (`status`),
  KEY `idx_lib_books_isbn` (`isbn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_issues` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `book_id`       INT UNSIGNED NOT NULL,
  `borrower_type` ENUM('student','staff') NOT NULL DEFAULT 'student',
  `borrower_id`   INT UNSIGNED NOT NULL,
  `issued_by`     INT UNSIGNED NOT NULL COMMENT 'staff user_id who issued the book',
  `issued_date`   DATE NOT NULL,
  `due_date`      DATE NOT NULL,
  `returned_date` DATE DEFAULT NULL,
  `returned_by`   INT UNSIGNED DEFAULT NULL,
  `status`        ENUM('issued','returned','overdue','lost') NOT NULL DEFAULT 'issued',
  `notes`         TEXT,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_lib_issues_book`     (`book_id`),
  KEY `idx_lib_issues_borrower` (`borrower_type`, `borrower_id`),
  KEY `idx_lib_issues_status`   (`status`),
  KEY `idx_lib_issues_due`      (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_fines` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `issue_id`    INT UNSIGNED NOT NULL,
  `fine_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `days_overdue` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `fine_status` ENUM('pending','paid','waived') NOT NULL DEFAULT 'pending',
  `paid_date`   DATE DEFAULT NULL,
  `waived_by`   INT UNSIGNED DEFAULT NULL,
  `waived_reason` TEXT,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_lib_fines_issue`  (`issue_id`),
  KEY `idx_lib_fines_status` (`fine_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed some default categories
INSERT IGNORE INTO `library_categories` (`name`, `description`) VALUES
  ('Fiction',           'Novels and fictional works'),
  ('Non-Fiction',       'Factual and educational content'),
  ('Science',           'Natural science, biology, chemistry, physics'),
  ('Mathematics',       'Mathematics textbooks and references'),
  ('History',           'Historical literature and social studies'),
  ('Geography',         'Geography and environment studies'),
  ('Language Arts',     'English language, grammar, and literature'),
  ('Religious Studies', 'Bible studies and religious education'),
  ('Arts & Crafts',     'Art, music, and creative subjects'),
  ('Reference',         'Encyclopaedias, dictionaries, atlases'),
  ('Set Books',         'Prescribed CBC/national curriculum texts'),
  ('General',           'Miscellaneous books');

-- ============================================================
-- 2. STUDENT HEALTH MODULE
-- ============================================================

CREATE TABLE IF NOT EXISTS `student_health_records` (
  `id`                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`           INT UNSIGNED NOT NULL,
  `blood_group`          ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
  `allergies`            TEXT,
  `chronic_conditions`   TEXT,
  `disability_notes`     TEXT,
  `special_diet`         TEXT,
  `emergency_contact_name`  VARCHAR(150),
  `emergency_contact_phone` VARCHAR(30),
  `medical_aid_provider`    VARCHAR(100),
  `medical_aid_number`      VARCHAR(50),
  `doctor_name`          VARCHAR(100),
  `doctor_phone`         VARCHAR(30),
  `notes`                TEXT,
  `created_by`           INT UNSIGNED DEFAULT NULL,
  `updated_by`           INT UNSIGNED DEFAULT NULL,
  `created_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_health` (`student_id`),
  KEY `idx_health_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sick_bay_visits` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`      INT UNSIGNED NOT NULL,
  `visit_date`      DATE NOT NULL,
  `visit_time`      TIME NOT NULL,
  `complaint`       VARCHAR(255) NOT NULL,
  `symptoms`        TEXT,
  `diagnosis`       TEXT,
  `treatment_given` TEXT,
  `temperature`     DECIMAL(4,1) DEFAULT NULL COMMENT 'degrees Celsius',
  `weight_kg`       DECIMAL(5,2) DEFAULT NULL,
  `medication_given` TEXT,
  `referred_to_hospital` TINYINT(1) NOT NULL DEFAULT 0,
  `referral_hospital`    VARCHAR(150) DEFAULT NULL,
  `parent_notified`      TINYINT(1) NOT NULL DEFAULT 0,
  `parent_notified_at`   DATETIME DEFAULT NULL,
  `dismissed_at`    DATETIME DEFAULT NULL,
  `attended_by`     INT UNSIGNED DEFAULT NULL COMMENT 'staff user_id',
  `status`          ENUM('active','dismissed','referred') NOT NULL DEFAULT 'active',
  `notes`           TEXT,
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_sb_student`    (`student_id`),
  KEY `idx_sb_visit_date` (`visit_date`),
  KEY `idx_sb_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_vaccinations` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`   INT UNSIGNED NOT NULL,
  `vaccine_name` VARCHAR(150) NOT NULL,
  `dose_number`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `date_given`   DATE NOT NULL,
  `next_due_date` DATE DEFAULT NULL,
  `given_by`     VARCHAR(100) DEFAULT NULL COMMENT 'nurse/doctor name',
  `batch_number` VARCHAR(50)  DEFAULT NULL,
  `notes`        TEXT,
  `created_by`   INT UNSIGNED DEFAULT NULL,
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_vax_student` (`student_id`),
  KEY `idx_vax_date`    (`date_given`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ASSIGNMENTS MODULE
-- ============================================================

CREATE TABLE IF NOT EXISTS `assignments` (
  `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`            VARCHAR(255) NOT NULL,
  `description`      TEXT,
  `subject_id`       INT UNSIGNED DEFAULT NULL,
  `class_id`         INT UNSIGNED DEFAULT NULL,
  `teacher_id`       INT UNSIGNED NOT NULL COMMENT 'staff.id of assigning teacher',
  `academic_year_id` INT UNSIGNED DEFAULT NULL,
  `term_id`          INT UNSIGNED DEFAULT NULL,
  `due_date`         DATETIME NOT NULL,
  `total_marks`      DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  `attachment_url`   VARCHAR(512) DEFAULT NULL,
  `status`           ENUM('draft','published','closed','archived') NOT NULL DEFAULT 'draft',
  `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`       DATETIME DEFAULT NULL,
  KEY `idx_asgn_class`   (`class_id`),
  KEY `idx_asgn_teacher` (`teacher_id`),
  KEY `idx_asgn_subject` (`subject_id`),
  KEY `idx_asgn_status`  (`status`),
  KEY `idx_asgn_due`     (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assignment_submissions` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT UNSIGNED NOT NULL,
  `student_id`    INT UNSIGNED NOT NULL,
  `submitted_at`  DATETIME DEFAULT NULL,
  `submission_text` TEXT,
  `attachment_url`  VARCHAR(512) DEFAULT NULL,
  `marks_awarded`   DECIMAL(6,2) DEFAULT NULL,
  `grade`           VARCHAR(5)   DEFAULT NULL,
  `feedback`        TEXT,
  `graded_by`       INT UNSIGNED DEFAULT NULL,
  `graded_at`       DATETIME DEFAULT NULL,
  `status`          ENUM('pending','submitted','late','graded','excused') NOT NULL DEFAULT 'pending',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_submission` (`assignment_id`, `student_id`),
  KEY `idx_sub_assignment` (`assignment_id`),
  KEY `idx_sub_student`    (`student_id`),
  KEY `idx_sub_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. RBAC PERMISSIONS for new modules
-- ============================================================

INSERT IGNORE INTO `permissions` (`name`, `display_name`, `module`, `created_at`) VALUES
  -- Library
  ('library.view',   'View Library',         'library',     NOW()),
  ('library.create', 'Add Books',            'library',     NOW()),
  ('library.edit',   'Edit Books',           'library',     NOW()),
  ('library.delete', 'Delete Books',         'library',     NOW()),
  ('library.issue',  'Issue/Return Books',   'library',     NOW()),
  ('library.manage', 'Manage Library',       'library',     NOW()),
  -- Health
  ('health.view',    'View Health Records',  'health',      NOW()),
  ('health.create',  'Create Health Records','health',      NOW()),
  ('health.edit',    'Edit Health Records',  'health',      NOW()),
  ('health.manage',  'Manage Health Module', 'health',      NOW()),
  -- Assignments
  ('assignments.view',   'View Assignments',   'assignments', NOW()),
  ('assignments.create', 'Create Assignments', 'assignments', NOW()),
  ('assignments.edit',   'Edit Assignments',   'assignments', NOW()),
  ('assignments.grade',  'Grade Assignments',  'assignments', NOW()),
  ('assignments.manage', 'Manage Assignments', 'assignments', NOW());
