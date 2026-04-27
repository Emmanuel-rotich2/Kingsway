-- =============================================================================
-- Migration: 2026_04_27_public_content_tables.sql
-- Description: Full DB-driven public website — NO hardcoded content.
--              Every piece of text/data editable from the admin dashboard.
-- Run: /opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/2026_04_27_public_content_tables.sql
-- =============================================================================

USE KingsWayAcademy;

-- ── Extend school_settings with contact/social/hero info ─────────────────────
INSERT IGNORE INTO `school_settings` (`setting_key`, `setting_value`, `label`) VALUES
('school_name',             'Kingsway Preparatory School',                              'School Name'),
('school_tagline',          'Excellence, Character & Leadership since 2005',             'School Tagline'),
('school_founded_year',     '2005',                                                     'Year Founded'),
('school_motto',            'In God We Soar',                                           'School Motto'),
('school_address_physical', 'Londiani – Kericho Road, Londiani Town, Kenya',            'Physical Address'),
('school_address_postal',   'P.O BOX 203-20203, Londiani, Kericho County',             'Postal Address'),
('school_phone_main',       '+254 720 113 030',                                         'Main Phone'),
('school_phone_alt',        '+254 720 113 031',                                         'Alternative Phone'),
('school_email_main',       'info@kingswaypreparatoryschool.sc.ke',                     'Main Email'),
('school_email_admissions', 'admissions@kingswaypreparatoryschool.sc.ke',               'Admissions Email'),
('school_email_finance',    'finance@kingswaypreparatoryschool.sc.ke',                  'Finance Email'),
('school_email_academic',   'academic@kingswaypreparatoryschool.sc.ke',                 'Academic Email'),
('school_email_boarding',   'boarding@kingswaypreparatoryschool.sc.ke',                 'Boarding Email'),
('office_hours_weekday',    'Monday – Friday: 7:30 AM – 5:00 PM',                      'Weekday Office Hours'),
('office_hours_saturday',   'Saturday: 9:00 AM – 1:00 PM',                             'Saturday Office Hours'),
('social_facebook',         'https://www.facebook.com/kingswayprepschool',              'Facebook URL'),
('social_twitter',          'https://twitter.com/kingswayprepschool',                   'Twitter/X URL'),
('social_instagram',        'https://www.instagram.com/kingswayprepschool',             'Instagram URL'),
('social_whatsapp',         '254720113030',                                             'WhatsApp Number (digits only)'),
('social_youtube',          'https://www.youtube.com/@kingswayprepschool',              'YouTube URL'),
('google_maps_url',         'https://www.google.com/maps/search/Kingsway+Preparatory+School+Londiani', 'Google Maps URL'),
('fees_day_label',          'Day Scholar',                                              'Day Scholar Label'),
('fees_day_grades',         'PP1 – Grade 9',                                            'Day Scholar Grade Range'),
('fees_boarding_label',     'Full Boarding',                                            'Boarding Label'),
('fees_boarding_grades',    'Grade 1 – Grade 9',                                        'Boarding Grade Range'),
('hero_badge',              'CBC-Aligned Curriculum',                                   'Hero Section Badge Text'),
('hero_stat_1_value',       '1,200+',  'Hero Stat 1 Value'),
('hero_stat_1_label',       'Students Enrolled', 'Hero Stat 1 Label'),
('hero_stat_2_value',       '98%',     'Hero Stat 2 Value'),
('hero_stat_2_label',       'KJSEA / KCPE Pass Rate', 'Hero Stat 2 Label'),
('hero_stat_3_value',       '30+',     'Hero Stat 3 Value'),
('hero_stat_3_label',       'Regional Awards', 'Hero Stat 3 Label'),
('hero_stat_4_value',       'Est. 2005','Hero Stat 4 Value'),
('hero_stat_4_label',       'Years of Excellence','Hero Stat 4 Label'),
('careers_stat_staff',      '80+',     'Careers - Staff Count'),
('careers_stat_experience', '15+',     'Careers - Years Experience'),
('careers_stat_retention',  '98%',     'Careers - Staff Retention'),
('careers_stat_cpd',        '100%',    'Careers - CPD Participation');

-- ── school_content: rich text blocks (mission, vision, about paragraphs) ─────
CREATE TABLE IF NOT EXISTS `school_content` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `content_key`   VARCHAR(100) NOT NULL,
  `content_value` LONGTEXT     DEFAULT NULL,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key` (`content_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_content` (`content_key`, `content_value`) VALUES
('mission',  'To provide a nurturing, inclusive, and academically rigorous environment that develops confident, virtuous, and globally-competitive learners through the Kenya Competency-Based Curriculum.'),
('vision',   'To be the most preferred school of excellence in the East African region, producing well-rounded, morally upright, and intellectually superior graduates.'),
('about_intro', 'Founded with a vision to provide holistic education, Kingsway Preparatory School has grown into one of the leading schools in the Rift Valley region. We nurture academic excellence, strong values, and practical life skills in every learner from Pre-Primary through Junior Secondary School.'),
('hero_subtitle', 'Kingsway Preparatory School provides world-class education combining the Kenya Competency-Based Curriculum with holistic character development, sports, and co-curricular excellence — in the heart of Londiani, Kenya.');

-- ── school_values: core values (CBC + school values) ────────────────────────
CREATE TABLE IF NOT EXISTS `school_values` (
  `id`            INT(11)     NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(80) NOT NULL,
  `description`   TEXT        DEFAULT NULL,
  `icon`          VARCHAR(50) NOT NULL DEFAULT 'bi-heart-fill',
  `color`         VARCHAR(20) NOT NULL DEFAULT '#198754',
  `display_order` INT(11)     NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_values` (`name`,`description`,`icon`,`color`,`display_order`) VALUES
('Love',        'Compassion and empathy in every interaction',      'bi-heart-fill',           '#e91e63', 1),
('Responsibility','Accountability for our actions and learning',    'bi-person-check-fill',    '#198754', 2),
('Respect',     'Honouring every person''s dignity and worth',      'bi-hand-thumbs-up-fill',  '#1976d2', 3),
('Unity',       'Together we achieve more, divided we fall',        'bi-people-fill',          '#ff9800', 4),
('Peace',       'Harmony in our diverse school community',          'bi-peace',                '#9c27b0', 5),
('Patriotism',  'Pride in our Kenyan heritage and culture',         'bi-flag-fill',            '#f44336', 6),
('Integrity',   'Honesty and transparency in all we do',            'bi-shield-check-fill',    '#00695c', 7),
('Excellence',  'Striving for the highest standard in all things',  'bi-star-fill',            '#f9c80e', 8);

-- ── school_history: timeline events ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_history` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `year`          VARCHAR(10)  NOT NULL,
  `event_title`   VARCHAR(200) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_history` (`year`,`event_title`,`description`,`display_order`) VALUES
('2005','Foundation',           'Kingsway Preparatory School was founded by a group of visionary educators committed to quality education in Londiani. The school started with 3 streams and 120 pupils.',                                                         10),
('2010','Growth & Recognition', 'Enrolment surpassed 400 students. The school received its first regional award for academic excellence. New classrooms and a modern library were constructed.',                                                                  20),
('2015','Boarding Programme',   'Introduction of the full boarding programme, enabling students from across the region to benefit from Kingsway''s quality education. Dormitory facilities expanded.',                                                            30),
('2019','CBC Transition',       'Seamless transition to Kenya''s Competency-Based Curriculum. Teacher training and infrastructure upgrades positioned Kingsway as a model CBC school in the Rift Valley region.',                                                40),
('2022','Digital Transformation','Launch of the new 40-workstation ICT Computer Lab. Introduction of smart classrooms and the school management ERP system for modern operations.',                                                                               50),
(YEAR(NOW()),'Today',           'Over 1,200 students enrolled, 80+ qualified staff, and a track record of 98% KJSEA pass rates. Kingsway continues to grow in excellence, character, and faith.',                                                                60);

-- ── leadership_team ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `leadership_team` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `title`         VARCHAR(200) NOT NULL,
  `bio`           TEXT         DEFAULT NULL,
  `avatar_url`    VARCHAR(500) DEFAULT NULL,
  `avatar_color`  VARCHAR(20)  NOT NULL DEFAULT '#198754',
  `email`         VARCHAR(200) DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order_active` (`display_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `leadership_team` (`name`,`title`,`bio`,`avatar_color`,`display_order`) VALUES
('School Director',    'School Founder & Director',     '20+ years in education leadership. Holds a Masters in Educational Management from University of Nairobi.',    '#0d4f2a', 10),
('Head Teacher',       'Head Teacher',                  'B.Ed (Hons), experienced in CBC implementation and school administration. Sets the academic tone for the school.',           '#198754', 20),
('Deputy (Academic)',  'Deputy Head — Academic',        'Oversees curriculum delivery, lesson plans, timetabling, teacher development, and academic performance monitoring.',         '#1976d2', 30),
('Deputy (Discipline)','Deputy Head — Discipline',      'Manages student conduct, welfare, community relations, and the boarding programme pastoral care.',                           '#7b1fa2', 40),
('The Bursar',         'School Bursar / Accountant',    'CPA-K certified. Manages school finances, fee collection, budgets, payroll, and financial reporting.',                      '#e65100', 50),
('Admissions Officer', 'Admissions Officer',            'Handles student intake, placement assessments, records management, and parent liaison throughout the admission process.',   '#00695c', 60);

-- ── school_programs ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_programs` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(150) NOT NULL,
  `level_range`   VARCHAR(100) DEFAULT NULL,
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-book',
  `color`         VARCHAR(20)  NOT NULL DEFAULT '#198754',
  `description`   TEXT         DEFAULT NULL,
  `anchor`        VARCHAR(50)  DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_programs` (`name`,`level_range`,`icon`,`color`,`description`,`anchor`,`display_order`) VALUES
('Pre-Primary (ECD)',    'PP1 – PP2 (Ages 4–5)',  'bi-emoji-smile-fill','#198754','Play-based learning, phonics, number recognition, social skills, and spiritual development.','early-years',10),
('Lower Primary',        'Grade 1–3 (Ages 6–8)',  'bi-book-open-fill',  '#1976d2','Literacy, Mathematical Activities, Environmental Activities, Creative Arts, and Physical & Health Education.','academics',20),
('Upper Primary',        'Grade 4–6 (Ages 9–11)', 'bi-pencil-fill',     '#f9c80e','English, Kiswahili, Mathematics, Science & Technology, Social Studies, CRE, and Agriculture/Home Science.','academics',30),
('Junior Secondary',     'Grade 7–9 (Ages 12–14)','bi-mortarboard-fill','#e91e63','Integrated Science, Health Education, Pre-Technical, Business Studies, Social Studies, and KJSEA preparation.','academics',40),
('Boarding Programme',   'All Grades',            'bi-house-heart-fill','#9c27b0','Full boarding with trained houseparents, nutritious meals, evening preps, pastoral care, and 24/7 supervision.','boarding',50),
('Sports & Co-Curricular','All Grades',           'bi-trophy-fill',     '#ff9800','Football, Athletics, Music, Drama, Scouts, Debate, Environmental Club, and many more enrichment activities.','co-curricular',60),
('STEM & ICT',           'Grade 3–9',             'bi-laptop-fill',     '#3f51b5','Computer science, robotics, coding, and digital literacy integrated across all CBC grade levels.','facilities',70);

-- ── school_facilities ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_facilities` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-building',
  `name`          VARCHAR(150) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `school_facilities` (`icon`,`name`,`description`,`display_order`) VALUES
('bi-building',        'Modern Classrooms',     '32 well-ventilated, fully-furnished classrooms equipped for CBC project-based learning.',                  10),
('bi-laptop',          'ICT Computer Lab',      '40-station computer laboratory with high-speed fibre internet and CBC educational software.',               20),
('bi-book',            'School Library',        'Over 12,000 books including CBC-aligned reference materials, fiction, and digital resource tablets.',      30),
('bi-heart-pulse',     'Sick Bay',              'Fully equipped sick bay managed by a qualified residential nurse available 24/7 for boarding students.',   40),
('bi-house-door',      'Dormitories',           'Separate boys and girls dormitories with houseparents on duty around the clock.',                          50),
('bi-cup-hot',         'Dining Hall',           'Spacious dining hall serving three balanced, nutritious meals daily for all boarding students.',            60),
('bi-flag',            'Sports Grounds',        'Full-size football pitch, basketball and netball courts, and athletics track.',                            70),
('bi-music-note',      'Music & Arts Room',     'Dedicated music room with instruments for lessons, choir practice, and the drama club.',                   80),
('bi-flask',           'Science Laboratory',    'Equipped laboratory for Grade 7–9 integrated science practicals and experiments.',                         90),
('bi-wifi',            'Smart Classrooms',      'Interactive smart boards and projectors integrated into the Grade 6–9 classroom experience.',              100),
('bi-person-hearts',   'Counselling Suite',     'Private counselling rooms for student welfare, peer mentoring, and pastoral care sessions.',               110),
('bi-shop',            'School Canteen',        'Tuck shop stocking approved snacks and scholastic materials. Cashless payment system for boarders.',       120);

-- ── department_contacts ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `department_contacts` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-building',
  `color`         VARCHAR(20)  NOT NULL DEFAULT '#198754',
  `name`          VARCHAR(150) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `email`         VARCHAR(200) DEFAULT NULL,
  `phone`         VARCHAR(30)  DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `department_contacts` (`icon`,`color`,`name`,`description`,`email`,`phone`,`display_order`) VALUES
('bi-person-check-fill','#198754','Admissions Office',     'New applications, transfers, placement tests, and enrolment enquiries.',       'admissions@kingswaypreparatoryschool.sc.ke','+254 720 113 030', 10),
('bi-cash-coin',         '#1976d2','Finance & Fees',       'Fee structure, M-Pesa payments, fee balances, receipts, and bursaries.',       'finance@kingswaypreparatoryschool.sc.ke',   '+254 720 113 031', 20),
('bi-book-fill',         '#9c27b0','Academic Office',      'Results, report cards, CBC curriculum, timetables, and assessments.',          'academic@kingswaypreparatoryschool.sc.ke',  '+254 720 113 030', 30),
('bi-house-fill',        '#e65100','Boarding Office',      'Dormitory bookings, exeats, student welfare, health, and pastoral matters.',   'boarding@kingswaypreparatoryschool.sc.ke',  '+254 720 113 031', 40);

-- ── gallery_items ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `gallery_items` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `image_url`     VARCHAR(500) NOT NULL,
  `caption`       VARCHAR(200) DEFAULT NULL,
  `category`      VARCHAR(50)  NOT NULL DEFAULT 'General',
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_order` (`is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `gallery_items` (`image_url`,`caption`,`category`,`display_order`) VALUES
('https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=600&q=80','Students in classroom', 'Academic',    10),
('https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=80','Sports day activities', 'Sports',      20),
('https://images.unsplash.com/photo-1581472723648-909f4851d4ae?w=600&q=80','Modern computer lab',   'Facilities',  30),
('https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=600&q=80','School library',        'Facilities',  40),
('https://images.unsplash.com/photo-1543269865-cbf427effbad?w=600&q=80','Parent-teacher meeting',  'Community',   50),
('https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=600&q=80','Music & drama',         'Arts',        60);

-- ── page_downloads ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `page_downloads` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `title`         VARCHAR(200) NOT NULL,
  `description`   VARCHAR(400) DEFAULT NULL,
  `file_url`      VARCHAR(500) NOT NULL,
  `file_type`     VARCHAR(10)  NOT NULL DEFAULT 'PDF',
  `file_size`     VARCHAR(30)  DEFAULT NULL,
  `category`      VARCHAR(50)  NOT NULL DEFAULT 'General',
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-file-earmark-pdf-fill',
  `color`         VARCHAR(20)  NOT NULL DEFAULT '#198754',
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cat_active` (`category`, `is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `page_downloads` (`title`,`file_url`,`file_type`,`file_size`,`category`,`icon`,`color`,`display_order`) VALUES
('Admission Application Form',    'downloads/admission_form.pdf',       'PDF','245 KB','Admissions','bi-file-earmark-pdf-fill', '#e91e63', 10),
('School Prospectus',             'downloads/prospectus.pdf',           'PDF','2.4 MB','Admissions','bi-file-earmark-pdf-fill', '#e91e63', 20),
('Admission Requirements',        'downloads/admission_requirements.pdf','PDF','120 KB','Admissions','bi-file-earmark-pdf-fill', '#e91e63', 30),
('Transfer Request Form',         'downloads/transfer_form.pdf',        'PDF','85 KB', 'Admissions','bi-file-earmark-pdf-fill', '#e91e63', 40),
('School Calendar',               'downloads/calendar.pdf',             'PDF','310 KB','Academic',  'bi-file-earmark-pdf-fill', '#1976d2', 50),
('Term Dates',                    'downloads/term_dates.pdf',           'PDF','95 KB', 'Academic',  'bi-file-earmark-pdf-fill', '#1976d2', 60),
('CBC Curriculum Guide',          'downloads/cbc_guide.pdf',            'PDF','890 KB','Academic',  'bi-file-earmark-pdf-fill', '#1976d2', 70),
('Exam Timetable Template',       'downloads/exam_timetable.docx',      'DOCX','45 KB','Academic',  'bi-file-earmark-word-fill','#1976d2', 80),
('Fee Structure',                 'downloads/fee_structure.pdf',        'PDF','180 KB','Finance',   'bi-file-earmark-pdf-fill', '#198754', 90),
('Fee Payment Guide',             'downloads/payment_guide.pdf',        'PDF','95 KB', 'Finance',   'bi-file-earmark-pdf-fill', '#198754', 100),
('Bursary Application Form',      'downloads/bursary_form.pdf',         'PDF','210 KB','Finance',   'bi-file-earmark-pdf-fill', '#198754', 110),
('Boarding Requirements List',    'downloads/boarding_list.pdf',        'PDF','130 KB','Boarding',  'bi-file-earmark-pdf-fill', '#ff9800', 120),
('Exeat Request Form',            'downloads/exeat_form.pdf',           'PDF','65 KB', 'Boarding',  'bi-file-earmark-pdf-fill', '#ff9800', 130),
('Boarding Rules & Guidelines',   'downloads/boarding_rules.pdf',       'PDF','205 KB','Boarding',  'bi-file-earmark-pdf-fill', '#ff9800', 140),
('School Rules & Code of Conduct','downloads/school_rules.pdf',         'PDF','340 KB','Policies',  'bi-file-earmark-pdf-fill', '#9c27b0', 150),
('Anti-Bullying Policy',          'downloads/anti_bullying.pdf',        'PDF','155 KB','Policies',  'bi-file-earmark-pdf-fill', '#9c27b0', 160),
('Child Safeguarding Policy',     'downloads/safeguarding.pdf',         'PDF','420 KB','Policies',  'bi-file-earmark-pdf-fill', '#9c27b0', 170),
('Data Protection Policy',        'downloads/data_protection.pdf',      'PDF','280 KB','Policies',  'bi-file-earmark-pdf-fill', '#9c27b0', 180);

-- ── news_categories ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news_categories` (
  `id`            INT(11)     NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(50) NOT NULL,
  `slug`          VARCHAR(50) NOT NULL,
  `color`         VARCHAR(20) NOT NULL DEFAULT '#198754',
  `display_order` INT(11)     NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `news_categories` (`name`,`slug`,`color`,`display_order`) VALUES
('Sports',         'sports',        '#198754', 10),
('Academic',       'academic',      '#1976d2', 20),
('Infrastructure', 'infrastructure','#e91e63', 30),
('Announcement',   'announcement',  '#f9a825', 40),
('Arts',           'arts',          '#9c27b0', 50),
('Community',      'community',     '#00695c', 60);

-- ── admission_process_steps ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admission_process_steps` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `step_number`   INT(11)      NOT NULL,
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-circle-fill',
  `color`         VARCHAR(20)  NOT NULL DEFAULT '#198754',
  `title`         VARCHAR(150) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admission_process_steps` (`step_number`,`icon`,`color`,`title`,`description`,`display_order`) VALUES
(1,'bi-file-earmark-plus-fill','#198754','Submit Application',  'Complete and submit the application form online or collect a physical copy from the admissions office.', 10),
(2,'bi-file-check-fill',       '#1976d2','Document Review',     'Our admissions team reviews the application and verifies all submitted documents within 2 working days.',  20),
(3,'bi-chat-dots-fill',        '#f9c80e','Placement Assessment','The applicant sits a short placement test and meets with the Head Teacher for an informal interview.',      30),
(4,'bi-envelope-check-fill',   '#9c27b0','Offer Letter',        'Successful applicants receive an official offer letter within 5 working days of the assessment.',           40),
(5,'bi-cash-coin',             '#e65100','Fee Payment',         'A non-refundable admission fee secures the placement. Full term fees are due before the start date.',      50),
(6,'bi-mortarboard-fill',      '#00695c','Orientation & Enrolment','The student attends new-student orientation before joining class on the agreed start date.',           60);

-- ── careers_benefits ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `careers_benefits` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `icon`          VARCHAR(50)  NOT NULL DEFAULT 'bi-check-circle',
  `title`         VARCHAR(150) NOT NULL,
  `description`   TEXT         DEFAULT NULL,
  `display_order` INT(11)      NOT NULL DEFAULT 0,
  `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `careers_benefits` (`icon`,`title`,`description`,`display_order`) VALUES
('bi-cash-coin',        'Competitive Salary',   'TSC-scale pay with timely monthly disbursement and annual review.',               10),
('bi-graph-up-arrow',   'Career Growth',        'Funded professional development, promotion pathways, and CPD programmes.',       20),
('bi-house-fill',       'Staff Housing',        'On-campus accommodation available for full-time teaching and boarding staff.',    30),
('bi-heart-pulse',      'Medical Cover',        'Staff and immediate dependants medical insurance scheme.',                       40),
('bi-calendar2-check',  'Work-Life Balance',    'Generous leave entitlement, supportive management, and a collegial environment.',50),
('bi-mortarboard-fill', 'Professional Dev.',    'Termly in-house CPD days, external workshops, and conference sponsorship.',      60);
