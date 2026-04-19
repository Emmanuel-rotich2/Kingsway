-- Migration: 2026_04_19_dormitories_tables.sql
-- Creates dormitories and dormitory_assignments tables needed by BoardingController

CREATE TABLE IF NOT EXISTS `dormitories` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `gender`      ENUM('male','female','mixed') NOT NULL DEFAULT 'male',
  `capacity`    INT NOT NULL DEFAULT 0,
  `patron_id`   INT NULL COMMENT 'staff.id of matron/patron',
  `location`    VARCHAR(200) NULL,
  `description` TEXT NULL,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dormitory_assignments` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `student_id`    INT NOT NULL,
  `dormitory_id`  INT NOT NULL,
  `bed_number`    VARCHAR(20) NULL,
  `room_number`   VARCHAR(20) NULL,
  `assigned_date` DATE NOT NULL DEFAULT (CURDATE()),
  `end_date`      DATE NULL,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `notes`         TEXT NULL,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student` (`student_id`),
  KEY `idx_dormitory` (`dormitory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `boarding_attendance` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `student_id`   INT NOT NULL,
  `dormitory_id` INT NULL,
  `date`         DATE NOT NULL,
  `session_id`   INT NULL,
  `status`       ENUM('present','absent','on_leave','sick') NOT NULL DEFAULT 'present',
  `check_time`   TIME NULL,
  `permission_id` INT NULL,
  `marked_by`    INT NULL,
  `notes`        TEXT NULL,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_student_date_session` (`student_id`, `date`, `session_id`),
  KEY `idx_date` (`date`),
  KEY `idx_dormitory_date` (`dormitory_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
