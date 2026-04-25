-- ============================================================
-- CBC Strands & Sub-strands Seed — Kenya CBC Curriculum
-- 2026-04-24
-- Seeds strands for all 49 learning areas
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Clear existing empty entries first (safe since we have 0 rows)
TRUNCATE TABLE sub_strands;
TRUNCATE TABLE strands;

-- ── PLAYGROUP (LA IDs 1-7) ────────────────────────────────────────────────

INSERT INTO strands (learning_area_id, code, name, level_range, sort_order) VALUES
-- 1: Psychosocial & Emotional Development
(1,'PSED-S1','Emotional Development','Playgroup',1),
(1,'PSED-S2','Social Development','Playgroup',2),
(1,'PSED-S3','Behavioural Development','Playgroup',3),

-- 2: Language & Communication
(2,'LAC-S1','Listening Skills','Playgroup',1),
(2,'LAC-S2','Speaking Skills','Playgroup',2),
(2,'LAC-S3','Pre-Reading Activities','Playgroup',3),

-- 3: Numeracy Foundations
(3,'NUMF-S1','Counting & Number Recognition','Playgroup',1),
(3,'NUMF-S2','Patterns & Sorting','Playgroup',2),
(3,'NUMF-S3','Basic Measurement Concepts','Playgroup',3),

-- 4: Environmental Exploration
(4,'ENVE-S1','Exploring Nature','Playgroup',1),
(4,'ENVE-S2','Exploring Objects','Playgroup',2),
(4,'ENVE-S3','Health & Safety','Playgroup',3),

-- 5: Self-Help Skills
(5,'SELF-S1','Personal Hygiene','Playgroup',1),
(5,'SELF-S2','Feeding & Self-Care','Playgroup',2),
(5,'SELF-S3','Dressing & Fine Motor Skills','Playgroup',3),

-- 6: Creative Arts (Playgroup)
(6,'CART-S1','Drawing & Colouring','Playgroup',1),
(6,'CART-S2','Modelling & Craft','Playgroup',2),
(6,'CART-S3','Creative Expression','Playgroup',3),

-- 7: Music & Movement
(7,'MUMO-S1','Songs & Rhymes','Playgroup',1),
(7,'MUMO-S2','Rhythm & Movement','Playgroup',2),
(7,'MUMO-S3','Creative Dance','Playgroup',3);

-- ── PRE-PRIMARY PP1, PP2 (LA IDs 8-12) ───────────────────────────────────

INSERT INTO strands (learning_area_id, code, name, level_range, sort_order) VALUES
-- 8: Language Activities
(8,'LAGA-S1','Listening & Speaking','PP1, PP2',1),
(8,'LAGA-S2','Pre-Reading Skills','PP1, PP2',2),
(8,'LAGA-S3','Pre-Writing Skills','PP1, PP2',3),
(8,'LAGA-S4','Vocabulary Building','PP1, PP2',4),

-- 9: Mathematical Activities
(9,'MATA-S1','Numbers 1-100','PP1, PP2',1),
(9,'MATA-S2','Measurement','PP1, PP2',2),
(9,'MATA-S3','Geometry','PP1, PP2',3),
(9,'MATA-S4','Patterns','PP1, PP2',4),

-- 10: Environmental Activities
(10,'ENVA-S1','Living & Non-Living Things','PP1, PP2',1),
(10,'ENVA-S2','Weather & Seasons','PP1, PP2',2),
(10,'ENVA-S3','Health & Safety','PP1, PP2',3),

-- 11: Psychomotor & Creative Activities
(11,'PSCA-S1','Gross Motor Skills','PP1, PP2',1),
(11,'PSCA-S2','Fine Motor Skills','PP1, PP2',2),
(11,'PSCA-S3','Creative Arts','PP1, PP2',3),

-- 12: Religious Education Activities
(12,'REAM-S1','God as Creator','PP1, PP2',1),
(12,'REAM-S2','Religious Values','PP1, PP2',2),
(12,'REAM-S3','Prayer & Worship','PP1, PP2',3);

-- ── LOWER PRIMARY Grade 1-3 (LA IDs 13-19) ───────────────────────────────

INSERT INTO strands (learning_area_id, code, name, level_range, sort_order) VALUES
-- 13: Literacy
(13,'LITE-S1','Listening & Speaking','Grade 1, Grade 2, Grade 3',1),
(13,'LITE-S2','Reading','Grade 1, Grade 2, Grade 3',2),
(13,'LITE-S3','Writing','Grade 1, Grade 2, Grade 3',3),
(13,'LITE-S4','Grammar','Grade 1, Grade 2, Grade 3',4),

-- 14: Kiswahili / Indigenous Language
(14,'KILI-S1','Kusikiliza na Kuzungumza','Grade 1, Grade 2, Grade 3',1),
(14,'KILI-S2','Kusoma','Grade 1, Grade 2, Grade 3',2),
(14,'KILI-S3','Kuandika','Grade 1, Grade 2, Grade 3',3),
(14,'KILI-S4','Sarufi na Msamiati','Grade 1, Grade 2, Grade 3',4),

-- 15: Mathematics
(15,'MATH-S1','Numbers','Grade 1, Grade 2, Grade 3',1),
(15,'MATH-S2','Measurement','Grade 1, Grade 2, Grade 3',2),
(15,'MATH-S3','Geometry','Grade 1, Grade 2, Grade 3',3),
(15,'MATH-S4','Data Handling','Grade 1, Grade 2, Grade 3',4),

-- 16: Environmental Activities (Lower Primary)
(16,'ENVP-S1','Living Things','Grade 1, Grade 2, Grade 3',1),
(16,'ENVP-S2','Non-Living Things','Grade 1, Grade 2, Grade 3',2),
(16,'ENVP-S3','Our Environment','Grade 1, Grade 2, Grade 3',3),
(16,'ENVP-S4','Science & Technology','Grade 1, Grade 2, Grade 3',4),

-- 17: Hygiene & Nutrition
(17,'HYNU-S1','Personal Hygiene','Grade 1, Grade 2, Grade 3',1),
(17,'HYNU-S2','Food & Nutrition','Grade 1, Grade 2, Grade 3',2),
(17,'HYNU-S3','Disease Prevention','Grade 1, Grade 2, Grade 3',3),

-- 18: Movement & Creative Activities
(18,'MOCA-S1','Physical Education','Grade 1, Grade 2, Grade 3',1),
(18,'MOCA-S2','Visual Arts','Grade 1, Grade 2, Grade 3',2),
(18,'MOCA-S3','Music & Dance','Grade 1, Grade 2, Grade 3',3),

-- 19: Religious Education Activities (LP)
(19,'RELP-S1','God as Creator','Grade 1, Grade 2, Grade 3',1),
(19,'RELP-S2','Values & Character','Grade 1, Grade 2, Grade 3',2),
(19,'RELP-S3','Holy Scriptures','Grade 1, Grade 2, Grade 3',3);

-- ── UPPER PRIMARY Grade 4-6 (LA IDs 20-31) ───────────────────────────────

INSERT INTO strands (learning_area_id, code, name, level_range, sort_order) VALUES
-- 20: English
(20,'ENG-S1','Listening & Speaking','Grade 4, Grade 5, Grade 6',1),
(20,'ENG-S2','Reading','Grade 4, Grade 5, Grade 6',2),
(20,'ENG-S3','Writing','Grade 4, Grade 5, Grade 6',3),
(20,'ENG-S4','Language Use','Grade 4, Grade 5, Grade 6',4),

-- 21: Kiswahili (UP)
(21,'KISU-S1','Kusikiliza na Kuzungumza','Grade 4, Grade 5, Grade 6',1),
(21,'KISU-S2','Kusoma','Grade 4, Grade 5, Grade 6',2),
(21,'KISU-S3','Kuandika','Grade 4, Grade 5, Grade 6',3),
(21,'KISU-S4','Sarufi','Grade 4, Grade 5, Grade 6',4),

-- 22: Mathematics (UP)
(22,'MATU-S1','Numbers','Grade 4, Grade 5, Grade 6',1),
(22,'MATU-S2','Measurement','Grade 4, Grade 5, Grade 6',2),
(22,'MATU-S3','Geometry','Grade 4, Grade 5, Grade 6',3),
(22,'MATU-S4','Statistics & Data Handling','Grade 4, Grade 5, Grade 6',4),

-- 23: Home Science
(23,'HSCI-S1','Food & Nutrition','Grade 4, Grade 5, Grade 6',1),
(23,'HSCI-S2','Clothing & Textiles','Grade 4, Grade 5, Grade 6',2),
(23,'HSCI-S3','Home Management','Grade 4, Grade 5, Grade 6',3),

-- 24: Agriculture
(24,'AGRI-S1','Soil & Crops','Grade 4, Grade 5, Grade 6',1),
(24,'AGRI-S2','Animals & Livestock','Grade 4, Grade 5, Grade 6',2),
(24,'AGRI-S3','Farm Business','Grade 4, Grade 5, Grade 6',3),

-- 25: Science & Technology
(25,'SCIT-S1','Living Things & Environment','Grade 4, Grade 5, Grade 6',1),
(25,'SCIT-S2','Matter & Materials','Grade 4, Grade 5, Grade 6',2),
(25,'SCIT-S3','Energy','Grade 4, Grade 5, Grade 6',3),
(25,'SCIT-S4','Technology & Innovation','Grade 4, Grade 5, Grade 6',4),

-- 26: Social Studies
(26,'SOST-S1','Place (Geography)','Grade 4, Grade 5, Grade 6',1),
(26,'SOST-S2','People & Civics','Grade 4, Grade 5, Grade 6',2),
(26,'SOST-S3','Resources & Economy','Grade 4, Grade 5, Grade 6',3),
(26,'SOST-S4','Change over Time (History)','Grade 4, Grade 5, Grade 6',4),

-- 27: Religious Education (UP)
(27,'REUP-S1','God & Humanity','Grade 4, Grade 5, Grade 6',1),
(27,'REUP-S2','Values & Ethics','Grade 4, Grade 5, Grade 6',2),
(27,'REUP-S3','Service & Justice','Grade 4, Grade 5, Grade 6',3),

-- 28: Visual Arts
(28,'VART-S1','Drawing & Design','Grade 4, Grade 5, Grade 6',1),
(28,'VART-S2','Painting & Print','Grade 4, Grade 5, Grade 6',2),
(28,'VART-S3','Sculpture & Craft','Grade 4, Grade 5, Grade 6',3),

-- 29: Performing Arts
(29,'PART-S1','Music','Grade 4, Grade 5, Grade 6',1),
(29,'PART-S2','Dance','Grade 4, Grade 5, Grade 6',2),
(29,'PART-S3','Drama','Grade 4, Grade 5, Grade 6',3),

-- 30: Physical & Health Education
(30,'PHE-S1','Physical Fitness','Grade 4, Grade 5, Grade 6',1),
(30,'PHE-S2','Sports Skills','Grade 4, Grade 5, Grade 6',2),
(30,'PHE-S3','Health Education','Grade 4, Grade 5, Grade 6',3);

-- ── JUNIOR SECONDARY Grade 7-9 (LA IDs 32-49) ────────────────────────────

INSERT INTO strands (learning_area_id, code, name, level_range, sort_order) VALUES
-- 32: English (JSS)
(32,'ENGJ-S1','Listening & Speaking','Grade 7, Grade 8, Grade 9',1),
(32,'ENGJ-S2','Reading Comprehension','Grade 7, Grade 8, Grade 9',2),
(32,'ENGJ-S3','Writing','Grade 7, Grade 8, Grade 9',3),
(32,'ENGJ-S4','Literature','Grade 7, Grade 8, Grade 9',4),

-- 33: Kiswahili (JSS)
(33,'KISJ-S1','Kusikiliza na Kuzungumza','Grade 7, Grade 8, Grade 9',1),
(33,'KISJ-S2','Kusoma','Grade 7, Grade 8, Grade 9',2),
(33,'KISJ-S3','Kuandika','Grade 7, Grade 8, Grade 9',3),
(33,'KISJ-S4','Fasihi','Grade 7, Grade 8, Grade 9',4),

-- 34: Mathematics (JSS)
(34,'MATJ-S1','Numbers & Algebra','Grade 7, Grade 8, Grade 9',1),
(34,'MATJ-S2','Measurement','Grade 7, Grade 8, Grade 9',2),
(34,'MATJ-S3','Geometry','Grade 7, Grade 8, Grade 9',3),
(34,'MATJ-S4','Statistics & Probability','Grade 7, Grade 8, Grade 9',4),

-- 35: Integrated Science
(35,'INSC-S1','Scientific Investigation','Grade 7, Grade 8, Grade 9',1),
(35,'INSC-S2','Living Things & Environment','Grade 7, Grade 8, Grade 9',2),
(35,'INSC-S3','Matter & Energy','Grade 7, Grade 8, Grade 9',3),
(35,'INSC-S4','Earth & Space','Grade 7, Grade 8, Grade 9',4),

-- 36: Social Studies (JSS)
(36,'SSJ-S1','Geography of Kenya & the World','Grade 7, Grade 8, Grade 9',1),
(36,'SSJ-S2','History & Government','Grade 7, Grade 8, Grade 9',2),
(36,'SSJ-S3','Citizenship & Governance','Grade 7, Grade 8, Grade 9',3),

-- 37: Religious Education (JSS)
(37,'REJS-S1','God & Creation','Grade 7, Grade 8, Grade 9',1),
(37,'REJS-S2','Values & Morals','Grade 7, Grade 8, Grade 9',2),
(37,'REJS-S3','Social Justice & Service','Grade 7, Grade 8, Grade 9',3),

-- 38: Pre-Technical & Pre-Vocational
(38,'PTPV-S1','Technical Drawing','Grade 7, Grade 8, Grade 9',1),
(38,'PTPV-S2','Workshop Practice','Grade 7, Grade 8, Grade 9',2),
(38,'PTPV-S3','Textile & Crafts','Grade 7, Grade 8, Grade 9',3),

-- 39: Health Education
(39,'HLTH-S1','Personal Health','Grade 7, Grade 8, Grade 9',1),
(39,'HLTH-S2','Community Health','Grade 7, Grade 8, Grade 9',2),
(39,'HLTH-S3','Reproductive Health','Grade 7, Grade 8, Grade 9',3),

-- 40: Business Studies
(40,'BSTD-S1','Business Concepts','Grade 7, Grade 8, Grade 9',1),
(40,'BSTD-S2','Financial Literacy','Grade 7, Grade 8, Grade 9',2),
(40,'BSTD-S3','Entrepreneurship','Grade 7, Grade 8, Grade 9',3),

-- 41: Agriculture (JSS)
(41,'AGRJ-S1','Crop Production','Grade 7, Grade 8, Grade 9',1),
(41,'AGRJ-S2','Animal Production','Grade 7, Grade 8, Grade 9',2),
(41,'AGRJ-S3','Agribusiness','Grade 7, Grade 8, Grade 9',3),

-- 44: Computer Science
(44,'COMP-S1','Digital Literacy','Grade 7, Grade 8, Grade 9',1),
(44,'COMP-S2','Programming Basics','Grade 7, Grade 8, Grade 9',2),
(44,'COMP-S3','Networking & Internet','Grade 7, Grade 8, Grade 9',3),
(44,'COMP-S4','Data Management','Grade 7, Grade 8, Grade 9',4),

-- 45: Visual & Performing Arts (JSS)
(45,'VPAJ-S1','Visual Arts','Grade 7, Grade 8, Grade 9',1),
(45,'VPAJ-S2','Music','Grade 7, Grade 8, Grade 9',2),
(45,'VPAJ-S3','Drama & Theatre','Grade 7, Grade 8, Grade 9',3),

-- 43: Sports & Physical Education (JSS)
(43,'SPHE-S1','Physical Fitness','Grade 7, Grade 8, Grade 9',1),
(43,'SPHE-S2','Athletics','Grade 7, Grade 8, Grade 9',2),
(43,'SPHE-S3','Team Sports','Grade 7, Grade 8, Grade 9',3),
(43,'SPHE-S4','Health & Lifestyle','Grade 7, Grade 8, Grade 9',4),

-- 42: Life Skills
(42,'LFSL-S1','Self-Awareness','Grade 7, Grade 8, Grade 9',1),
(42,'LFSL-S2','Interpersonal Skills','Grade 7, Grade 8, Grade 9',2),
(42,'LFSL-S3','Decision Making','Grade 7, Grade 8, Grade 9',3);

-- ── SUB-STRANDS for key learning areas ───────────────────────────────────
-- Maths Grade 1-3
INSERT INTO sub_strands (strand_id, code, name, sort_order)
SELECT s.id, CONCAT(s.code,'-SS',ROW_NUMBER() OVER (PARTITION BY s.id ORDER BY v.n)), v.name, v.n
FROM strands s
JOIN (
  SELECT id, 1 n,'Whole Numbers' name FROM strands WHERE code='MATH-S1'
  UNION ALL SELECT id,2,'Addition & Subtraction' FROM strands WHERE code='MATH-S1'
  UNION ALL SELECT id,3,'Multiplication & Division' FROM strands WHERE code='MATH-S1'
  UNION ALL SELECT id,4,'Fractions' FROM strands WHERE code='MATH-S1'
  UNION ALL SELECT id,1,'Length' FROM strands WHERE code='MATH-S2'
  UNION ALL SELECT id,2,'Mass' FROM strands WHERE code='MATH-S2'
  UNION ALL SELECT id,3,'Capacity & Volume' FROM strands WHERE code='MATH-S2'
  UNION ALL SELECT id,4,'Time' FROM strands WHERE code='MATH-S2'
  UNION ALL SELECT id,1,'Lines & Shapes' FROM strands WHERE code='MATH-S3'
  UNION ALL SELECT id,2,'3D Objects' FROM strands WHERE code='MATH-S3'
  UNION ALL SELECT id,1,'Collecting Data' FROM strands WHERE code='MATH-S4'
  UNION ALL SELECT id,2,'Representing Data' FROM strands WHERE code='MATH-S4'
  -- English Literacy
  UNION ALL SELECT id,1,'Phonological Awareness' FROM strands WHERE code='LITE-S1'
  UNION ALL SELECT id,2,'Speaking Activities' FROM strands WHERE code='LITE-S1'
  UNION ALL SELECT id,1,'Decoding Skills' FROM strands WHERE code='LITE-S2'
  UNION ALL SELECT id,2,'Comprehension' FROM strands WHERE code='LITE-S2'
  UNION ALL SELECT id,3,'Fluency' FROM strands WHERE code='LITE-S2'
  UNION ALL SELECT id,1,'Handwriting' FROM strands WHERE code='LITE-S3'
  UNION ALL SELECT id,2,'Sentence Construction' FROM strands WHERE code='LITE-S3'
  UNION ALL SELECT id,3,'Creative Writing' FROM strands WHERE code='LITE-S3'
  -- Maths UP
  UNION ALL SELECT id,1,'Whole Numbers & Place Value' FROM strands WHERE code='MATU-S1'
  UNION ALL SELECT id,2,'Fractions & Decimals' FROM strands WHERE code='MATU-S1'
  UNION ALL SELECT id,3,'Percentages & Ratios' FROM strands WHERE code='MATU-S1'
  UNION ALL SELECT id,4,'Algebra Basics' FROM strands WHERE code='MATU-S1'
  -- Science UP
  UNION ALL SELECT id,1,'Plants' FROM strands WHERE code='SCIT-S1'
  UNION ALL SELECT id,2,'Animals' FROM strands WHERE code='SCIT-S1'
  UNION ALL SELECT id,3,'Environment Conservation' FROM strands WHERE code='SCIT-S1'
  UNION ALL SELECT id,1,'States of Matter' FROM strands WHERE code='SCIT-S2'
  UNION ALL SELECT id,2,'Mixtures & Separation' FROM strands WHERE code='SCIT-S2'
  UNION ALL SELECT id,1,'Heat Energy' FROM strands WHERE code='SCIT-S3'
  UNION ALL SELECT id,2,'Light Energy' FROM strands WHERE code='SCIT-S3'
  UNION ALL SELECT id,3,'Sound Energy' FROM strands WHERE code='SCIT-S3'
  -- Maths JSS
  UNION ALL SELECT id,1,'Integers' FROM strands WHERE code='MATJ-S1'
  UNION ALL SELECT id,2,'Rational Numbers' FROM strands WHERE code='MATJ-S1'
  UNION ALL SELECT id,3,'Algebra' FROM strands WHERE code='MATJ-S1'
  UNION ALL SELECT id,4,'Sequences & Series' FROM strands WHERE code='MATJ-S1'
) v ON s.id = v.id;

SET FOREIGN_KEY_CHECKS = 1;

SELECT CONCAT(COUNT(*), ' strands seeded') AS status FROM strands;
SELECT CONCAT(COUNT(*), ' sub-strands seeded') AS status FROM sub_strands;
