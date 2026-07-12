-- Admissions Interviews and Placement Tables Migration
-- This migration adds dedicated tables for interview scheduling and placement tests
-- to support the enhanced admissions workflow

-- Table: admission_interviews
-- Stores interview schedules and results for admission applications
CREATE TABLE IF NOT EXISTS `admission_interviews` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `venue` varchar(255) DEFAULT 'Main Office',
  `interviewer_id` int(10) unsigned DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled','rescheduled') NOT NULL DEFAULT 'scheduled',
  `academic_readiness_score` int(11) DEFAULT NULL,
  `behavior_score` int(11) DEFAULT NULL,
  `communication_score` int(11) DEFAULT NULL,
  `overall_score` int(11) DEFAULT NULL,
  `recommendation` enum('recommended','not_recommended','conditional') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `conducted_at` datetime DEFAULT NULL,
  `conducted_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_interviewer_id` (`interviewer_id`),
  KEY `idx_scheduled_date` (`scheduled_date`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_interview_application` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_interview_interviewer` FOREIGN KEY (`interviewer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_interview_conducted_by` FOREIGN KEY (`conducted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admission interview schedules and results';

-- Table: admission_placement_tests
-- Stores placement test records for applicants requiring academic assessment
CREATE TABLE IF NOT EXISTS `admission_placement_tests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `test_code` varchar(50) NOT NULL,
  `test_date` date NOT NULL,
  `subject_area` varchar(100) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `max_score` decimal(5,2) DEFAULT NULL,
  `percentage` int(11) DEFAULT NULL,
  `recommendation` enum('promote','retain','conditional') DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_test_code` (`test_code`),
  KEY `idx_test_date` (`test_date`),
  CONSTRAINT `fk_placement_test_application` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_placement_test_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admission placement test records';

-- Table: admission_placements
-- Stores class placement recommendations and final assignments
CREATE TABLE IF NOT EXISTS `admission_placements` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `recommended_class_id` int(10) unsigned DEFAULT NULL,
  `recommended_stream_id` int(10) unsigned DEFAULT NULL,
  `final_class_id` int(10) unsigned DEFAULT NULL,
  `final_stream_id` int(10) unsigned DEFAULT NULL,
  `placement_status` enum('pending','recommended','approved','assigned') NOT NULL DEFAULT 'pending',
  `placement_type` enum('automatic','test_based','interview_based') DEFAULT 'automatic',
  `recommendation_notes` text DEFAULT NULL,
  `recommended_by` int(10) unsigned DEFAULT NULL,
  `recommended_at` datetime DEFAULT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_placement` (`application_id`),
  KEY `idx_recommended_class` (`recommended_class_id`),
  KEY `idx_final_class` (`final_class_id`),
  KEY `idx_placement_status` (`placement_status`),
  CONSTRAINT `fk_placement_application` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_placement_recommended_class` FOREIGN KEY (`recommended_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_placement_final_class` FOREIGN KEY (`final_class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_placement_recommended_by` FOREIGN KEY (`recommended_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_placement_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admission class placement records';

-- Table: admission_decisions
-- Stores headteacher admission decisions and recommendations
CREATE TABLE IF NOT EXISTS `admission_decisions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `decision` enum('approved','rejected','waitlisted','more_info_required','placement_test_required') NOT NULL,
  `decision_by` int(10) unsigned NOT NULL,
  `decision_at` datetime NOT NULL,
  `remarks` text DEFAULT NULL,
  `conditions` text DEFAULT NULL,
  `interview_score` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_decision` (`decision`),
  KEY `idx_decision_by` (`decision_by`),
  KEY `idx_decision_at` (`decision_at`),
  CONSTRAINT `fk_decision_application` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_decision_by` FOREIGN KEY (`decision_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Headteacher admission decisions';

-- Table: admission_workflow_history
-- Detailed audit trail of admission workflow transitions
CREATE TABLE IF NOT EXISTS `admission_workflow_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `application_id` int(10) unsigned NOT NULL,
  `from_stage` varchar(50) DEFAULT NULL,
  `to_stage` varchar(50) NOT NULL,
  `action` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `performed_by` int(10) unsigned DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_application_id` (`application_id`),
  KEY `idx_to_stage` (`to_stage`),
  KEY `idx_performed_at` (`performed_at`),
  CONSTRAINT `fk_workflow_history_application` FOREIGN KEY (`application_id`) REFERENCES `admission_applications` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_workflow_history_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admission workflow transition history';

-- Update admission_applications table to include links to new tables
-- Note: These ALTER statements should be run manually if needed
-- to avoid compatibility issues with different MariaDB versions
