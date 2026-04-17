-- ============================================================
-- Migration: Register new CBC assessment pages in sidebar
-- Date: 2026-04-17
-- Pages: competencies_sheet, national_exams, sick_bay
-- Idempotent: INSERT IGNORE
-- ============================================================

-- Add national_exams page to sidebar for relevant roles
-- (headteacher, deputy academic, director, principal)

INSERT IGNORE INTO `sidebar_items`
  (`key`, `label`, `icon`, `page`, `url`, `parent_id`, `type`, `sort_order`, `scope`, `status`, `created_at`, `updated_at`)
VALUES
  -- National Exams — under Academics/Assessments group for headteacher
  ('ht_national_exams', 'National Exams (KPSEA/KJSEA)', NULL, 'national_exams', NULL,
    (SELECT id FROM (SELECT id FROM sidebar_items WHERE `key`='headteacher_formative' LIMIT 1) AS t),
    'sidebar', 2, 'SCHOOL', 1, NOW(), NOW()),

  -- Competencies Sheet
  ('ht_competencies_sheet', 'Competency Ratings', NULL, 'competencies_sheet', NULL,
    (SELECT id FROM (SELECT id FROM sidebar_items WHERE `key`='headteacher_formative' LIMIT 1) AS t2),
    'sidebar', 3, 'SCHOOL', 1, NOW(), NOW()),

  -- Sick Bay — under Health group
  ('ht_sick_bay', 'Sick Bay Log', NULL, 'sick_bay', NULL,
    (SELECT id FROM (SELECT id FROM sidebar_items WHERE `key`='ht_student_health' LIMIT 1) AS t3),
    'sidebar', 1, 'SCHOOL', 1, NOW(), NOW());

-- Add RBAC permissions for new pages
INSERT IGNORE INTO `permissions` (`name`, `display_name`, `module`, `created_at`) VALUES
  ('assessments.national.view',    'View National Exam Results',    'assessments', NOW()),
  ('assessments.national.enter',   'Enter National Exam Results',   'assessments', NOW()),
  ('assessments.competency.view',  'View Competency Ratings',       'assessments', NOW()),
  ('assessments.competency.rate',  'Rate Core Competencies',        'assessments', NOW()),
  ('health.sick_bay.view',         'View Sick Bay Records',         'health',      NOW()),
  ('health.sick_bay.manage',       'Manage Sick Bay Admissions',    'health',      NOW());

-- Register routes for new pages
INSERT IGNORE INTO `routes` (`path`, `method`, `controller`, `action`, `module`, `description`, `auth_required`, `status`)
VALUES
  ('/academic/formative-assessment-marks', 'GET',  'academic', 'getFormativeAssessmentMarks', 'assessments', 'Get marks for formative assessment',  1, 'active'),
  ('/academic/formative-assessment-marks', 'POST', 'academic', 'postFormativeAssessmentMarks','assessments', 'Save marks for formative assessment',  1, 'active'),
  ('/academic/national-exams',             'GET',  'academic', 'getNationalExams',             'assessments', 'List national exam results',           1, 'active'),
  ('/academic/national-exams',             'POST', 'academic', 'postNationalExams',            'assessments', 'Enter national exam results',          1, 'active'),
  ('/academic/competency-ratings',         'GET',  'academic', 'getCompetencyRatings',         'assessments', 'Get competency ratings',               1, 'active'),
  ('/academic/competency-ratings',         'POST', 'academic', 'postCompetencyRatings',        'assessments', 'Save competency ratings',              1, 'active'),
  ('/academic/assessment-types',           'GET',  'academic', 'getAssessmentTypes',           'assessments', 'List assessment types',                1, 'active'),
  ('/academic/formative-summary',          'GET',  'academic', 'getFormativeSummary',          'assessments', 'Formative summary per student/area',   1, 'active'),
  ('/health/sick-bay',                     'GET',  'health',   'getSickBay',                   'health',      'List sick bay visits',                 1, 'active'),
  ('/health/sick-bay',                     'POST', 'health',   'postSickBay',                  'health',      'Admit student to sick bay',            1, 'active'),
  ('/health/sick-bay',                     'PUT',  'health',   'putSickBay',                   'health',      'Update sick bay visit',                1, 'active'),
  ('/health/records',                      'GET',  'health',   'getRecords',                   'health',      'List student health records',          1, 'active'),
  ('/health/records',                      'POST', 'health',   'postRecords',                  'health',      'Save student health record',           1, 'active'),
  ('/health/vaccinations',                 'GET',  'health',   'getVaccinations',              'health',      'List vaccinations',                    1, 'active'),
  ('/health/vaccinations',                 'POST', 'health',   'postVaccinations',             'health',      'Record vaccination',                   1, 'active'),
  ('/health/summary',                      'GET',  'health',   'getSummary',                   'health',      'Health module summary stats',          1, 'active'),
  ('/library/books',                       'GET',  'library',  'getBooks',                     'library',     'List library books',                   1, 'active'),
  ('/library/books',                       'POST', 'library',  'postBooks',                    'library',     'Add library book',                     1, 'active'),
  ('/library/issues',                      'GET',  'library',  'getIssues',                    'library',     'List active loans',                    1, 'active'),
  ('/library/issues',                      'POST', 'library',  'postIssues',                   'library',     'Issue book to borrower',               1, 'active'),
  ('/library/overdue',                     'GET',  'library',  'getOverdue',                   'library',     'List overdue books',                   1, 'active'),
  ('/library/fines',                       'GET',  'library',  'getFines',                     'library',     'List library fines',                   1, 'active');
