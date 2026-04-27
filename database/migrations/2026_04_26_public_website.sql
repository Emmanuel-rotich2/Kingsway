-- =============================================================================
-- Migration: 2026_04_26_public_website.sql
-- Description: Create and seed public-facing website database tables
-- Run: mysql -u root -p KingsWayAcademy < database/migrations/2026_04_26_public_website.sql
-- =============================================================================

USE KingsWayAcademy;

-- ── 1. news_articles ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news_articles` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `title`      VARCHAR(255) NOT NULL,
  `slug`       VARCHAR(255) NOT NULL,
  `excerpt`    VARCHAR(600) DEFAULT NULL,
  `content`    LONGTEXT     NOT NULL,
  `category`   VARCHAR(50)  NOT NULL DEFAULT 'Announcement',
  `image_url`  VARCHAR(500) DEFAULT NULL,
  `author`     VARCHAR(100) NOT NULL DEFAULT 'Kingsway Admin',
  `status`     ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  `views`      INT(11)      NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status_created` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. school_events ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `school_events` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `event_date`  DATE         NOT NULL,
  `event_time`  TIME         DEFAULT NULL,
  `end_date`    DATE         DEFAULT NULL,
  `location`    VARCHAR(200) DEFAULT NULL,
  `category`    VARCHAR(50)  NOT NULL DEFAULT 'Academic',
  `status`      ENUM('upcoming','ongoing','past','cancelled') NOT NULL DEFAULT 'upcoming',
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_date` (`event_date`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. job_vacancies ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_vacancies` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(200) NOT NULL,
  `department`       VARCHAR(100) NOT NULL,
  `job_type`         VARCHAR(50)  NOT NULL DEFAULT 'Full-Time',
  `location`         VARCHAR(100) NOT NULL DEFAULT 'Londiani Campus',
  `description`      TEXT         NOT NULL,
  `requirements`     TEXT         DEFAULT NULL COMMENT 'JSON array of requirement strings',
  `responsibilities` TEXT         DEFAULT NULL COMMENT 'JSON array of responsibility strings',
  `deadline`         DATE         NOT NULL,
  `color`            VARCHAR(20)  DEFAULT '#198754',
  `status`           ENUM('open','closed','filled') NOT NULL DEFAULT 'open',
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. contact_inquiries ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_inquiries` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`  VARCHAR(150) NOT NULL,
  `email`      VARCHAR(200) NOT NULL,
  `phone`      VARCHAR(30)  DEFAULT NULL,
  `subject`    VARCHAR(150) DEFAULT NULL,
  `message`    TEXT         NOT NULL,
  `status`     ENUM('new','read','replied') NOT NULL DEFAULT 'new',
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. admission_enquiries ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `admission_enquiries` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `parent_name`    VARCHAR(150) NOT NULL,
  `phone`          VARCHAR(30)  NOT NULL,
  `email`          VARCHAR(200) DEFAULT NULL,
  `child_name`     VARCHAR(150) NOT NULL,
  `grade_applying` VARCHAR(20)  NOT NULL,
  `notes`          TEXT         DEFAULT NULL,
  `status`         ENUM('new','contacted','enrolled','declined') NOT NULL DEFAULT 'new',
  `ip_address`     VARCHAR(45)  DEFAULT NULL,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. job_applications ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `job_applications` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `job_id`       INT(11)      DEFAULT NULL,
  `job_title`    VARCHAR(200) NOT NULL,
  `first_name`   VARCHAR(100) NOT NULL,
  `last_name`    VARCHAR(100) NOT NULL,
  `email`        VARCHAR(200) NOT NULL,
  `phone`        VARCHAR(30)  NOT NULL,
  `tsc_number`   VARCHAR(50)  DEFAULT NULL,
  `cv_filename`  VARCHAR(300) DEFAULT NULL,
  `cover_letter` TEXT         DEFAULT NULL,
  `status`       ENUM('received','shortlisted','interviewed','hired','rejected') NOT NULL DEFAULT 'received',
  `ip_address`   VARCHAR(45)  DEFAULT NULL,
  `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_job_id` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. newsletter_subscribers ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `newsletter_subscribers` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `email`            VARCHAR(200) NOT NULL,
  `name`             VARCHAR(150) DEFAULT NULL,
  `status`           ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
  `subscribed_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `unsubscribed_at`  TIMESTAMP    NULL     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- ── News Articles ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `news_articles`
  (`id`,`title`,`slug`,`excerpt`,`content`,`category`,`image_url`,`author`,`status`,`views`,`created_at`)
VALUES
(1,'Term 2 Sports Day — A Day of Champions','term-2-sports-day-a-day-of-champions',
'Over 400 students competed in track & field, football, and netball. Our Grade 6 relay team broke a school record standing since 2019.',
'<p>Kingsway Preparatory School hosted its much-anticipated Term 2 Sports Day on a sun-drenched Saturday that drew hundreds of parents, guardians and supporters from across Londiani. The day was a vibrant celebration of athletic talent, teamwork, and school spirit.</p><p>Over 400 students from PP1 through Grade 9 competed in events ranging from the 100m sprint to the 4×100m relay, high jump, long jump, shot put, and class football and netball tournaments.</p><h4>Record-Breaking Performance</h4><p>The highlight was the Grade 6 boys 4×100m relay team who shattered the school record standing since 2019. The team of Brian Mutai, Kevin Ochieng, Samuel Kipchoge, and Elijah Waweru clocked an impressive 48.3 seconds — nearly two full seconds faster than the previous record. The crowd erupted as they crossed the finish line.</p><p>In the girls category, Grade 8 student Mercy Achieng dominated the 200m and 400m events, winning both with convincing margins. Her performance has put her on the radar for county-level selection.</p><h4>Football and Netball Finals</h4><p>The football final between Grade 7 and Grade 8 went to penalties after a 1–1 draw. Grade 7 prevailed 4–3 on spot kicks, with goalkeeper Daniel Mwangi saving the decisive penalty. The netball final was equally competitive, with Grade 6 girls defeating Grade 9 by a single goal in the closing minute.</p><p>Head Teacher Mr. Odhiambo praised the students for their sportsmanship, noting that "the character our children showed today — whether winning or losing — is exactly what we aim to build at Kingsway." He also thanked the PE department and all parent volunteers who made the day possible.</p>',
'Sports','https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=800&q=80',
'Kingsway Sports Department','published',247,DATE_SUB(NOW(),INTERVAL 3 DAY)),

(2,'Grade 9 KJSEA Intensive Revision Workshop','grade-9-kjsea-intensive-revision-workshop',
'Our teaching staff hosted a full-day intensive revision workshop for all 87 Grade 9 students preparing for the Kenya Junior School Education Assessment.',
'<p>With the Kenya Junior School Education Assessment (KJSEA) approaching, the academic team at Kingsway Preparatory School organised an intensive one-day revision workshop for all 87 Grade 9 students. The workshop consolidated key concepts across all examinable learning areas and built student confidence ahead of the national assessment.</p><p>The day began at 7:30 AM with an encouraging address from the Head Teacher, who reminded students that the KJSEA is a gateway to the Senior Secondary pathway aligned with each learner''s strengths and interests. "Prepare thoroughly, trust your teachers, and most importantly, trust yourselves," he said.</p><h4>Learning Area Breakout Sessions</h4><p>Students rotated through focused sessions in Mathematics, English, Kiswahili, Integrated Science, Health Education, Social Studies, and Business Studies. Each session incorporated past paper practice, timed exercises, and immediate feedback.</p><p>The Mathematics session focused on algebra, geometry, and data interpretation — areas where students historically find the most difficulty. Using KNEC-released sample questions, students worked through problems under timed conditions before group discussion.</p><h4>KJSEA Pathway Guidance</h4><p>In the afternoon, Career Guidance teacher Mrs. Wambui hosted a session on the four KJSEA pathway options: Arts, Sports & Technical (AST); STEM; Social Sciences; and Humanities. Students completed a structured self-assessment to identify which pathway best matched their academic performance and personal interests.</p><p>Parents can download the Grade 9 revision timetable from the Downloads page or collect a copy from the school office.</p>',
'Academic','https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=800&q=80',
'Academic Office','published',183,DATE_SUB(NOW(),INTERVAL 7 DAY)),

(3,'New ICT Computer Lab Officially Commissioned','new-ict-computer-lab-officially-commissioned',
'The school proudly unveils its brand-new 40-station computer lab — a major milestone in our commitment to digital literacy and CBC-aligned learning.',
'<p>Kingsway Preparatory School has officially commissioned its new state-of-the-art ICT Computer Laboratory. The ribbon-cutting ceremony was attended by parents, area education officers, and members of the school board of management.</p><p>The new lab features 40 modern desktop workstations running Windows 11, high-speed fibre-optic internet connectivity, an interactive smart board, projector, and a dedicated server room. All machines have been loaded with CBC-aligned educational software including Scratch, KooBits, and Microsoft Education Suite.</p><h4>Impact on Learning</h4><p>The lab directly addresses CBC Digital Literacy requirements at all levels. Grade 3 through Grade 9 learners will now have scheduled computer lab sessions weekly. PP1 and PP2 learners will also benefit from introductory digital literacy activities.</p><p>ICT teacher Mr. Njoroge said: "This lab will transform how our learners engage with technology — not just as consumers, but as creators. We will be coding, designing, researching and problem-solving in this space."</p><h4>Community Partnership</h4><p>The lab was made possible through school development funds, a parent fundraising drive that raised over KSh 1.2 million, and a matching grant from a Londiani-based education foundation. All contributing parents were recognised at the ceremony.</p>',
'Infrastructure','https://images.unsplash.com/photo-1581472723648-909f4851d4ae?w=800&q=80',
'School Administration','published',312,DATE_SUB(NOW(),INTERVAL 10 DAY)),

(4,'Term 2 Parent-Teacher Feedback Day — You Are Invited','term-2-parent-teacher-feedback-day-invitation',
'Parents and guardians are warmly invited to the Term 2 Feedback Day. Report books will be handed directly to parents on the day.',
'<p>Kingsway Preparatory School will be hosting its Term 2 Parent-Teacher Feedback Day — an important event where parents, guardians and teachers come together to discuss each child''s academic progress, social development and wellbeing.</p><h4>How the Day Works</h4><p>Each class teacher will be stationed in their classroom from 8:00 AM to 2:00 PM. Parents can arrive at any time during this window. Each meeting covers:</p><ul><li>Academic performance across all learning areas</li><li>Core competency development (CBC framework)</li><li>Attendance and punctuality record</li><li>Behaviour and social interaction</li><li>Action plan for Term 3</li></ul><p>Parents of Grade 9 learners are particularly encouraged to attend as KJSEA preparation is the central agenda for Term 3. The Head Teacher will be available for individual meetings with Grade 9 parents from 2:00 PM.</p><h4>Report Books</h4><p>Signed Term 2 report books will be handed directly to parents and will not be sent home with students this term. Parents unable to attend should send an authorised representative with a signed letter and the learner''s name and class.</p><p>All report books must be signed and returned to class teachers by the end of the first week of Term 3. Tea and refreshments courtesy of the PTA. We look forward to seeing you.</p>',
'Announcement','https://images.unsplash.com/photo-1543269865-cbf427effbad?w=800&q=80',
'School Administration','published',95,DATE_SUB(NOW(),INTERVAL 14 DAY)),

(5,'Music & Drama Club Wins Gold at Sub-County Festival','music-drama-club-wins-gold-sub-county-festival',
'Kingsway''s Music and Drama Club brought home two gold trophies from the Sub-County Festival, excelling in choral verse and solo performance.',
'<p>It was a triumphant return from the Sub-County Music and Cultural Festival for Kingsway Preparatory School''s Music and Drama Club. The team of 32 students competed across four categories and came home with two gold trophies, one silver, and a special commendation for originality.</p><h4>Competition Results</h4><p>The choral verse group — 24 voices strong — delivered a stirring rendition of the Swahili poem "Mama Afrika" that judges described as "technically precise and emotionally moving." Their performance secured gold and automatic qualification for the County Festival in September.</p><p>In the solo verse category, Grade 7 student Brenda Owino delivered a powerful original composition that earned a standing ovation and the gold award. Brenda, who joined the drama club just eight months ago, was visibly moved as her name was announced.</p><p>The drama group performed a 15-minute original play on environmental conservation titled "The Last Tree," winning silver and a special commendation for quality of original script writing.</p><h4>Message from the Club Patron</h4><p>"These students worked tirelessly during lunchtimes, after school, and on weekends to reach this level," said Mrs. Auma, the club patron. "Today''s results are a testament to their dedication and the power of nurturing every kind of talent — not just academic." The school congratulates all participants and looks forward to the County Festival.</p>',
'Arts','https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=800&q=80',
'Arts Department','published',156,DATE_SUB(NOW(),INTERVAL 18 DAY)),

(6,'Library Expansion: 2,000 New Books Added','library-expansion-2000-new-books-added',
'Our school library now holds over 12,000 volumes after the largest single-term book acquisition in the library''s history.',
'<p>Kingsway Preparatory School''s library has undergone a major expansion with 2,047 new books added — the largest single-term addition in the library''s history. The total collection now stands at over 12,000 volumes, one of the largest primary school libraries in Kericho County.</p><h4>What''s New on the Shelves</h4><p>The collection was carefully curated by librarian Mrs. Cherono to align with the CBC curriculum at all levels:</p><ul><li><strong>CBC Reference Series (G1–G9):</strong> 320 subject-specific workbooks and teacher resource guides</li><li><strong>Fiction & Storybooks:</strong> 450 titles including African literature, Swahili novels, and graded readers for Lower Primary</li><li><strong>Science & Technology:</strong> 180 titles including coding, robotics, and general science for junior secondary</li><li><strong>History & Social Studies:</strong> 200 titles covering Kenya, Africa, and world history</li><li><strong>Encyclopaedias & References:</strong> 12 full encyclopaedia sets and 45 atlases</li></ul><h4>Digital Library Corner</h4><p>Alongside the physical acquisitions, the library has set up a dedicated Digital Resource Corner with 4 tablet stations loaded with e-books, National Geographic Kids, and CBC-aligned learning apps. Available during library hours for all students.</p><p>New library hours: Monday to Friday 7:00 AM – 6:00 PM, Saturday 9:00 AM – 1:00 PM.</p>',
'Announcement','https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=800&q=80',
'Library Department','published',88,DATE_SUB(NOW(),INTERVAL 22 DAY)),

(7,'Grade 8 Science Fair: Innovation at Its Best','grade-8-science-fair-innovation-at-its-best',
'Grade 8 students showcased remarkable projects from solar-powered water purifiers to biodegradable plastics — demonstrating real-world problem-solving at its finest.',
'<p>Kingsway Preparatory School hosted its annual Grade 8 Science Fair — this year''s most innovative edition yet, with 24 project entries covering themes from renewable energy to public health and food technology.</p><h4>Top Projects</h4><p><strong>First Place:</strong> "Solar-Powered Water Purifier Using Local Materials" by Peter Kariuki and Grace Njeri. The team designed and built a functional solar UV water purifier from locally sourced materials, addressing rural Kenya''s clean water challenges with low-cost technology.</p><p><strong>Second Place:</strong> "Biodegradable Plastics from Cassava Starch" by Faith Wanjiku and Aisha Mohamed. The project produced a workable bioplastic film from cassava starch, vinegar, and glycerol — a compelling alternative to petroleum-based plastic bags.</p><p><strong>Third Place:</strong> "Vertical Farming Using Recycled Bottles" by Daniel Mutua. A creative urban farming model showing how rooftops can be used to grow vegetables using hydroponics and recycled plastic bottles.</p><h4>Impact on Learning</h4><p>The Science Fair is a CBC-aligned School-Based Assessment (SBA) activity contributing to students'' formative grades. More importantly it develops critical competencies — problem-solving, creativity, collaboration, and real-world application of scientific knowledge.</p><p>The winning projects will be entered for the Sub-County Science Congress next term. We look forward to seeing Kingsway represented at county level.</p>',
'Academic','https://images.unsplash.com/photo-1532094349884-543559fee3af?w=800&q=80',
'Academic Office','published',134,DATE_SUB(NOW(),INTERVAL 28 DAY)),

(8,'Football Team Crowned Sub-County Champions','football-team-crowned-sub-county-champions',
'Our Grade 7–9 football team has been crowned champions of the Kericho Sub-County Inter-Schools League after an unbeaten season — 10 wins, 2 draws, 38 goals scored.',
'<p>In what supporters are calling the greatest footballing achievement in the school''s history, Kingsway Preparatory School''s junior football team has been crowned champions of the Kericho Sub-County Inter-Schools Football League. The team went through the 12-match season unbeaten — winning ten and drawing two — finishing four points clear of their nearest rivals.</p><h4>The Season in Review</h4><p>The team, coached by Mr. Otieno and captained by Grade 9 midfielder Kevin Sigei, showed remarkable consistency. They scored 38 goals and conceded only 9 — a defensive record that will be hard to beat.</p><p>The title-clinching match was a 3–1 victory over Londiani Academy, played before a packed home crowd. Goals from Elijah Waweru (2) and substitute Daniel Mwangi sealed a historic victory.</p><h4>County Finals Ahead</h4><p>As sub-county champions, the team qualifies for the Kericho County Schools Football Championship next term. The school is fundraising for travel, accommodation and kit.</p><p>"These boys have shown what is possible when you combine discipline, teamwork and hard work," said Coach Otieno. "We go to county as champions and have nothing to fear." Each player received a certificate of achievement at a special school assembly.</p>',
'Sports','https://images.unsplash.com/photo-1560272564-c83b66b1ad12?w=800&q=80',
'Sports Department','published',201,DATE_SUB(NOW(),INTERVAL 35 DAY));

-- ── School Events ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `school_events`
  (`id`,`title`,`description`,`event_date`,`event_time`,`location`,`category`)
VALUES
(1,'End of Term 2 Examinations Begin',
 'All classes from Grade 1 through Grade 9 sit their end-of-term written examinations. Examination timetables have been distributed to all learners. Students should arrive by 7:15 AM with all stationery.',
 DATE_ADD(CURDATE(),INTERVAL 14 DAY),'07:30:00','All Classrooms','Academic'),
(2,'Term 2 Parent-Teacher Feedback Day',
 'Parents and guardians are invited to meet class teachers to review Term 2 academic results and set Term 3 targets. Report books will be handed directly to parents. Tea and refreshments provided by the PTA.',
 DATE_ADD(CURDATE(),INTERVAL 21 DAY),'08:00:00','All Classrooms & School Hall','Meeting'),
(3,'Annual Prize-Giving & Awards Ceremony',
 'Celebrating excellence in academics, sports, arts, and character development. Parents and guardians warmly welcome. Smart casual dress code for students. Guest speaker to be announced.',
 DATE_ADD(CURDATE(),INTERVAL 28 DAY),'10:00:00','School Assembly Ground','Ceremony'),
(4,'Term 2 Closing Day',
 'Last day of Term 2. Boarding students to be collected by parents or guardians by 4:00 PM. All fees must be cleared before report books are released.',
 DATE_ADD(CURDATE(),INTERVAL 35 DAY),'12:00:00','School Campus','Academic'),
(5,'Term 3 Opening Day',
 'Students report back for Term 3. Day scholars arrive by 7:30 AM. Boarding students to report between 2:00 PM and 6:00 PM with all requirements. New students report with parents to the admissions office.',
 DATE_ADD(CURDATE(),INTERVAL 49 DAY),'07:30:00','School Gates & Dormitories','Academic'),
(6,'Sub-County Athletics Championship',
 'Kingsway hosts the Annual Sub-County Inter-Schools Athletics Championship. Schools across the sub-county compete in track and field events. Spectators are welcome.',
 DATE_ADD(CURDATE(),INTERVAL 63 DAY),'08:00:00','School Sports Ground','Sports'),
(7,'Grade 9 KJSEA Mock Examination',
 'A full simulation of the Kenya Junior School Education Assessment for all Grade 9 learners. Results guide final revision strategies for the main national assessment.',
 DATE_ADD(CURDATE(),INTERVAL 77 DAY),'08:00:00','Grade 9 Classrooms','Academic'),
(8,'Annual Cultural Day & Heritage Festival',
 'Students, teachers and parents celebrate Kenya''s rich cultural diversity. Students come dressed in traditional attire, perform cultural dances, songs, and present traditional foods. All families welcome.',
 DATE_ADD(CURDATE(),INTERVAL 91 DAY),'09:00:00','School Assembly Ground','Cultural'),
(9,'PTA Annual General Meeting',
 'The Parent-Teacher Association holds its AGM to review school progress, elect new committee members, and plan fundraising activities for the year.',
 DATE_ADD(CURDATE(),INTERVAL 105 DAY),'10:00:00','School Hall','Meeting'),
(10,'End of Year Prize-Giving Ceremony',
 'The most prestigious event in the school calendar — celebrating academic excellence, sporting achievement, character awards, and Grade 9 leavers. All parents and guardians warmly invited.',
 DATE_ADD(CURDATE(),INTERVAL 140 DAY),'10:00:00','School Assembly Ground','Ceremony');

-- ── Job Vacancies ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `job_vacancies`
  (`id`,`title`,`department`,`job_type`,`location`,`description`,`requirements`,`responsibilities`,`deadline`,`color`,`status`)
VALUES
(1,'Class Teacher — Grade 4','Teaching','Full-Time','Londiani Campus',
 'We are looking for a dedicated and passionate Grade 4 class teacher with strong CBC implementation skills. The ideal candidate is a patient, creative educator who builds strong relationships with learners and parents, and who can lead a class of 35–40 pupils through the CBC Lower Primary curriculum.',
 '["P1 or B.Ed (Primary Education)","TSC Registration (mandatory)","Minimum 2 years teaching experience","Strong CBC knowledge and implementation skills","Experience with CBC portfolio management preferred"]',
 '["Deliver engaging CBC-aligned lessons across all learning areas","Maintain up-to-date class registers, portfolios, and assessment records","Communicate regularly with parents on learner progress","Participate in lesson plan reviews and peer observations","Supervise learners during games, meals, and preps"]',
 DATE_ADD(CURDATE(),INTERVAL 30 DAY),'#198754','open'),

(2,'Mathematics & Science Teacher (Grade 7–9)','Teaching','Full-Time','Londiani Campus',
 'Seeking an experienced Junior Secondary Mathematics and Integrated Science teacher to prepare Grade 7–9 students for the KJSEA. The successful candidate will deliver high-quality lessons, set and mark SBA tasks, and provide targeted revision for national assessment candidates.',
 '["B.Ed (Science/Mathematics) or equivalent","TSC Registration","At least 3 years JSS teaching experience","Deep knowledge of CBC Junior Secondary curriculum","Experience with KJSEA preparation"]',
 '["Teach Mathematics and Integrated Science to Grade 7, 8 and 9","Design and administer SBA formative and summative assessments","Provide KJSEA targeted revision and exam practice","Mentor Grade 9 students on KJSEA pathway selection","Maintain detailed learner progress records"]',
 DATE_ADD(CURDATE(),INTERVAL 25 DAY),'#1976d2','open'),

(3,'School Nurse (Residential)','Health & Welfare','Full-Time','Londiani Campus',
 'Qualified nurse to manage the school sick bay, maintain student health records, administer first aid, and coordinate with medical facilities for student welfare. This is a residential position with on-campus accommodation provided. Boarding school or institutional nursing experience is a strong advantage.',
 '["Diploma or Degree in Nursing","Kenya Nursing Council (KNC) registration","Valid First Aid and BLS certification","Experience in school, paediatric or institutional health setting","Able and willing to reside on campus"]',
 '["Manage the school sick bay and student health records","Administer first aid and basic medication as prescribed","Coordinate referrals to hospitals and clinics","Maintain vaccination and medical history records","Provide health education sessions for students and staff","Respond to health emergencies 24/7 (residential role)"]',
 DATE_ADD(CURDATE(),INTERVAL 20 DAY),'#e91e63','open'),

(4,'ICT Technician & Lab Manager','Technology','Full-Time','Londiani Campus',
 'We are seeking a skilled ICT Technician to manage our newly-commissioned 40-station computer laboratory, maintain network infrastructure, and support teachers in integrating technology into CBC lessons.',
 '["Diploma or Degree in ICT, Computer Science or related field","Networking and hardware maintenance skills","Experience with Windows OS, Office 365 or Google Workspace","Ability to train staff and students in digital tools","Strong problem-solving and communication skills"]',
 '["Manage and maintain the computer lab (40 workstations)","Administer school network, internet, and server infrastructure","Support teachers in integrating EdTech tools into CBC lessons","Train staff on school systems and digital tools","Manage ICT inventory, warranties, and support contracts"]',
 DATE_ADD(CURDATE(),INTERVAL 35 DAY),'#ff9800','open'),

(5,'Accounts Clerk','Finance & Administration','Full-Time','Londiani Campus',
 'Support the school bursar in day-to-day fee collection, financial record-keeping, and accounting operations. The successful candidate should be detail-oriented with integrity, experience in school or institutional finance, and familiarity with accounting software.',
 '["CPA Part II or Diploma in Accounting","At least 2 years accounting experience (school environment preferred)","Proficiency in Excel and accounting software (QuickBooks/Sage)","Experience with M-Pesa business collection","High integrity and attention to detail"]',
 '["Receive and record daily fee payments (cash and M-Pesa)","Maintain fee collection registers and issue official receipts","Follow up on outstanding fee balances with parents","Prepare daily, weekly and monthly financial reports","Assist bursar with bank reconciliations and audits","Manage petty cash float and staff advance records"]',
 DATE_ADD(CURDATE(),INTERVAL 28 DAY),'#00695c','open'),

(6,'Games Teacher & Sports Coach','Co-Curricular','Full-Time','Londiani Campus',
 'Lead and grow the school''s sports programs including football, athletics, netball, and basketball. Prepare students and teams for inter-schools competitions at sub-county, county, and national levels.',
 '["Diploma or Degree in Physical Education or Sports Science","TSC Registration or KSCA coaching certificate","Minimum 2 years coaching or PE teaching experience","Proven track record in competitive school sports","Strong motivational and disciplinary skills"]',
 '["Teach Physical and Health Education to all CBC grade levels","Coach school teams in football, athletics, netball and basketball","Organise and manage inter-house sports events","Prepare teams for inter-schools competitions","Maintain sports equipment inventory and facilities","Coordinate SBA physical assessment records"]',
 DATE_ADD(CURDATE(),INTERVAL 15 DAY),'#9c27b0','open');

-- ─────────────────────────────────────────────────────────────────────────────
-- Create CV upload directory placeholder (run separately in shell if needed):
-- mkdir -p uploads/cvs && echo "" > uploads/cvs/index.html
-- ─────────────────────────────────────────────────────────────────────────────
