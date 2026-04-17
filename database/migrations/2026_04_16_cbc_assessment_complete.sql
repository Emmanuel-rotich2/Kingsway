-- ============================================================
-- Migration: Complete CBC Assessment System
-- Date: 2026-04-16
-- Idempotent: INSERT IGNORE, IF NOT EXISTS
-- ============================================================

-- ============================================================
-- 1. SEED assessment_types (Formative + Summative + National)
-- ============================================================

INSERT IGNORE INTO `assessment_types` (`name`, `description`, `is_formative`, `is_summative`, `status`) VALUES
-- Formative (CA) types
('Assignment',         'Take-home written task (Classroom Assessment)',              1, 0, 'active'),
('Homework',           'Short daily home task (Classroom Assessment)',               1, 0, 'active'),
('Quiz',               'Quick in-class knowledge check (Classroom Assessment)',      1, 0, 'active'),
('Short Test',         'Short in-class test on a topic (Classroom Assessment)',      1, 0, 'active'),
('Project',            'Group or individual extended project work',                  1, 0, 'active'),
('Oral Presentation',  'Spoken / verbal classroom assessment',                       1, 0, 'active'),
('Portfolio Task',     'Collected evidence of learning (Portfolio)',                  1, 0, 'active'),
('Observation',        'Teacher observation / checklist assessment',                 1, 0, 'active'),
('Practical Work',     'Hands-on lab, field, or applied assessment',                 1, 0, 'active'),
('Peer Assessment',    'Student-to-student assessment activity',                     1, 0, 'active'),
-- Summative (SBA) types
('End of Term Exam',   'School-based end-of-term examination paper',                 0, 1, 'active'),
('End of Year Exam',   'Annual combined end-of-year examination',                    0, 1, 'active'),
('Mid-Term Test',      'School-based mid-term assessment',                           0, 1, 'active'),
-- National Assessment (SA) types
('KNEC Grade 3 Assessment', 'National diagnostic assessment for Grade 3 learners',  0, 1, 'active'),
('KPSEA',              'Kenya Primary School Education Assessment (Grade 6 national)', 0, 1, 'active'),
('KJSEA',              'Kenya Junior School Education Assessment (Grade 9 national)', 0, 1, 'active');

-- ============================================================
-- 2. STRANDS (groupings within each learning area)
-- ============================================================

CREATE TABLE IF NOT EXISTS `strands` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `learning_area_id` INT UNSIGNED NOT NULL,
  `code`            VARCHAR(20)  NOT NULL,
  `name`            VARCHAR(150) NOT NULL,
  `description`     TEXT,
  `level_range`     VARCHAR(50)  DEFAULT NULL COMMENT 'e.g. G1-G3, G4-G6, G7-G9',
  `sort_order`      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status`          ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_strand` (`learning_area_id`, `code`),
  KEY `idx_strand_la` (`learning_area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. SUB-STRANDS (topics within a strand)
-- ============================================================

CREATE TABLE IF NOT EXISTS `sub_strands` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `strand_id`   INT UNSIGNED NOT NULL,
  `code`        VARCHAR(20)  NOT NULL,
  `name`        VARCHAR(200) NOT NULL,
  `description` TEXT,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_sub_strand` (`strand_id`, `code`),
  KEY `idx_sub_strand_strand` (`strand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. FORMATIVE ASSESSMENT SCORES (fast per-student entry)
--    Separate from the full assessment_results workflow.
--    Used for quick assignment/homework/quiz mark entry.
-- ============================================================

CREATE TABLE IF NOT EXISTS `formative_scores` (
  `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `assessment_id`     INT UNSIGNED NOT NULL COMMENT 'FK → assessments.id',
  `student_id`        INT UNSIGNED NOT NULL,
  `score`             DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `max_score`         DECIMAL(6,2) NOT NULL DEFAULT 100.00,
  `percentage`        DECIMAL(5,2) GENERATED ALWAYS AS (ROUND((`score` / NULLIF(`max_score`,0)) * 100, 2)) STORED,
  `cbc_grade`         ENUM('EE','ME','AE','BE') GENERATED ALWAYS AS (
                          CASE
                            WHEN (`score` / NULLIF(`max_score`,0)) * 100 >= 75 THEN 'EE'
                            WHEN (`score` / NULLIF(`max_score`,0)) * 100 >= 60 THEN 'ME'
                            WHEN (`score` / NULLIF(`max_score`,0)) * 100 >= 40 THEN 'AE'
                            ELSE 'BE'
                          END
                        ) STORED,
  `remarks`           VARCHAR(255) DEFAULT NULL,
  `entered_by`        INT UNSIGNED DEFAULT NULL,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_formative_score` (`assessment_id`, `student_id`),
  KEY `idx_fs_student`    (`student_id`),
  KEY `idx_fs_assessment` (`assessment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. NATIONAL EXAM RESULTS (Grade 3 KNEC, Grade 6 KPSEA, Grade 9 KJSEA)
-- ============================================================

CREATE TABLE IF NOT EXISTS `national_exam_results` (
  `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`            INT UNSIGNED NOT NULL,
  `exam_type`             ENUM('KNEC_G3','KPSEA_G6','KJSEA_G9') NOT NULL,
  `academic_year_id`      INT UNSIGNED DEFAULT NULL,
  `exam_year`             SMALLINT UNSIGNED NOT NULL,
  `learning_area_id`      INT UNSIGNED NOT NULL,
  `score`                 DECIMAL(6,2) DEFAULT NULL,
  `max_score`             DECIMAL(6,2) DEFAULT NULL,
  `percentage`            DECIMAL(5,2) DEFAULT NULL,
  `cbc_grade`             ENUM('EE','ME','AE','BE') DEFAULT NULL,
  `raw_grade`             VARCHAR(10)  DEFAULT NULL COMMENT 'Raw KNEC grade (1–6 for KPSEA)',
  `points`                DECIMAL(4,1) DEFAULT NULL COMMENT 'KPSEA/KJSEA aggregate points',
  `pathway`               ENUM('AST','STEM','Social_Sciences','Humanities') DEFAULT NULL COMMENT 'KJSEA pathway',
  `remarks`               TEXT,
  `entered_by`            INT UNSIGNED DEFAULT NULL,
  `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_natl_exam` (`student_id`, `exam_type`, `exam_year`, `learning_area_id`),
  KEY `idx_natl_student` (`student_id`),
  KEY `idx_natl_type`    (`exam_type`),
  KEY `idx_natl_year`    (`exam_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. STUDENT CORE VALUES (per student per term)
-- ============================================================

CREATE TABLE IF NOT EXISTS `student_core_values` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `student_id`  INT UNSIGNED NOT NULL,
  `term_id`     INT UNSIGNED NOT NULL,
  `value_id`    INT UNSIGNED NOT NULL COMMENT 'FK → core_values.id',
  `rating`      ENUM('consistently','sometimes','rarely') NOT NULL DEFAULT 'sometimes',
  `evidence`    TEXT,
  `assessed_by` INT UNSIGNED DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_student_value` (`student_id`, `term_id`, `value_id`),
  KEY `idx_scv_student` (`student_id`),
  KEY `idx_scv_term`    (`term_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. Ensure core_values is seeded (7 CBC values)
-- ============================================================

INSERT IGNORE INTO `core_values` (`name`, `description`, `status`) VALUES
  ('Love',           'Showing care, affection and empathy towards others',                         'active'),
  ('Responsibility', 'Being accountable for one\'s actions and fulfilling duties',                  'active'),
  ('Respect',        'Treating others with dignity and honouring diversity',                        'active'),
  ('Unity',          'Working together and appreciating shared identity as Kenyans',                'active'),
  ('Peace',          'Promoting harmony and managing conflicts constructively',                     'active'),
  ('Patriotism',     'Showing loyalty and commitment to Kenya and its development',                 'active'),
  ('Social Justice', 'Upholding fairness, equity and the rights of all people',                    'active'),
  ('Integrity',      'Being honest, transparent and consistent in all actions',                    'active');

-- ============================================================
-- 8. RBAC permissions for CBC assessment module
-- ============================================================

INSERT IGNORE INTO `permissions` (`name`, `display_name`, `module`, `created_at`) VALUES
  ('assessments.formative.view',    'View Formative Assessments',    'assessments', NOW()),
  ('assessments.formative.create',  'Create Formative Assessments',  'assessments', NOW()),
  ('assessments.formative.enter',   'Enter Formative Marks',         'assessments', NOW()),
  ('assessments.competency.rate',   'Rate Core Competencies',        'assessments', NOW()),
  ('assessments.national.view',     'View National Exam Results',    'assessments', NOW()),
  ('assessments.national.enter',    'Enter National Exam Results',   'assessments', NOW()),
  ('assessments.rubric.manage',     'Manage Assessment Rubrics',     'assessments', NOW());
