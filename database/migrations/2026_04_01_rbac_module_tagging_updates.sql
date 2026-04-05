-- =========================================================================
-- RBAC module tagging + route module tagging (from 2026_03_29_rbac_workflow_sync §3-5)
-- Does NOT run §6 route_permissions auto-join (superseded by 2026_03_30_rebuild_*.sql)
-- =========================================================================

SET NAMES utf8mb4;

-- Dedupe role_permissions (safe)
DELETE rp FROM role_permissions rp
INNER JOIN (
  SELECT role_id, permission_id, MIN(id) AS keep_id
  FROM role_permissions
  GROUP BY role_id, permission_id
  HAVING COUNT(*) > 1
) d ON rp.role_id = d.role_id AND rp.permission_id = d.permission_id AND rp.id <> d.keep_id;

-- Admissions module
UPDATE `permissions` SET `module` = 'Admissions'
WHERE `entity` LIKE '%admission%' OR `code` LIKE 'admission_%';

-- Students module
UPDATE `permissions` SET `module` = 'Students'
WHERE `entity` LIKE 'students%' OR `code` LIKE 'students_%'
OR `entity` LIKE 'students_enrollment%'
OR `entity` LIKE 'students_admission%'
OR `entity` LIKE 'students_documents%'
OR `entity` LIKE 'students_medical%'
OR `entity` LIKE 'students_qr%';

-- Academics module
UPDATE `permissions` SET `module` = 'Academics'
WHERE `entity` LIKE 'academic%' AND `entity` NOT LIKE '%assessment%'
OR `code` LIKE 'academic_%' AND `code` NOT LIKE '%assessment%'
OR `entity` LIKE '%curriculum%'
OR `entity` LIKE '%lesson_plan%'
OR `entity` LIKE '%timetable%'
OR `entity` LIKE '%schedule%' AND `entity` NOT LIKE '%transport%';

-- Assessments & Results
UPDATE `permissions` SET `module` = 'Assessments'
WHERE `entity` LIKE '%assessment%'
OR `entity` LIKE 'academic_results%'
OR `entity` LIKE 'academic_exams%'
OR `code` LIKE '%assessment%';

-- Attendance module
UPDATE `permissions` SET `module` = 'Attendance'
WHERE `entity` LIKE 'attendance%'
OR `code` LIKE 'attendance_%';

-- Discipline & Counseling
UPDATE `permissions` SET `module` = 'Discipline'
WHERE `entity` LIKE '%discipline%'
OR `code` LIKE '%discipline_%';

-- Finance & Payments module
UPDATE `permissions` SET `module` = 'Finance'
WHERE `entity` LIKE 'finance%'
OR `entity` LIKE 'payment%'
OR `entity` LIKE '%fee%'
OR `code` LIKE 'finance_%'
OR `code` LIKE 'fees_%'
OR `code` LIKE 'payments_%'
OR `entity` LIKE '%budget%'
OR `entity` LIKE '%expense%'
OR `entity` LIKE '%invoice%'
OR `entity` LIKE '%receipt%'
OR `entity` LIKE '%bank%';

-- Payroll & HR module
UPDATE `permissions` SET `module` = 'Payroll'
WHERE `entity` LIKE 'staff%'
OR `entity` LIKE 'staff_leave%'
OR `entity` LIKE 'staff_profile%'
OR `entity` LIKE 'staff_timesheet%'
OR `entity` LIKE 'staff_performance%'
OR `entity` LIKE 'staff_role%'
OR `entity` LIKE '%payroll%'
OR `code` LIKE 'staff_%'
OR `code` LIKE 'payroll_%';

-- Scheduling & Timetabling
UPDATE `permissions` SET `module` = 'Scheduling'
WHERE `entity` LIKE 'academic_schedule%'
OR `entity` LIKE 'academic_timetable%'
OR `entity` LIKE 'term_holiday%'
OR `entity` LIKE 'class_timetabling%'
OR `code` LIKE 'schedule%'
OR `code` LIKE 'timetable%';

-- Transport module
UPDATE `permissions` SET `module` = 'Transport'
WHERE `entity` LIKE 'transport%'
OR `code` LIKE 'transport_%';

-- Communications module
UPDATE `permissions` SET `module` = 'Communications'
WHERE `entity` LIKE 'communication%'
OR `code` LIKE 'communication%';

-- Boarding & Health
UPDATE `permissions` SET `module` = 'Boarding'
WHERE `entity` LIKE 'boarding%'
OR `code` LIKE 'boarding_%';

-- Inventory & Catering
UPDATE `permissions` SET `module` = 'Inventory'
WHERE `entity` LIKE 'inventory%'
OR `entity` LIKE 'catering%'
OR `entity` LIKE 'stock%'
OR `code` LIKE 'inventory_%'
OR `code` LIKE 'catering_%';

-- Activities & Talent
UPDATE `permissions` SET `module` = 'Activities'
WHERE `entity` LIKE 'activities%'
OR `entity` LIKE 'competition%'
OR `code` LIKE 'activities_%'
OR `code` LIKE 'competition%';

-- Reporting & Analytics
UPDATE `permissions` SET `module` = 'Reporting'
WHERE `entity` LIKE 'reports%'
OR `entity` LIKE 'dashboard%'
OR `code` LIKE 'reports_%'
OR `code` LIKE 'dashboard%';

-- System module (auth, users, roles, permissions, logs)
UPDATE `permissions` SET `module` = 'System'
WHERE `entity` LIKE 'system%'
OR `entity` LIKE 'authentication%'
OR `entity` LIKE 'authorization%'
OR `entity` LIKE 'users%'
OR `entity` LIKE 'roles%'
OR `entity` LIKE '%settings%'
OR `code` LIKE 'system_%'
OR `code` LIKE 'auth%'
OR `code` LIKE 'rbac_%'
OR `code` LIKE 'user%'
OR `code` LIKE 'role%' AND `code` NOT LIKE '%permission%';

-- Mark untagged permissions (should be minimal)
-- SELECT * FROM permissions WHERE module IS NULL;

-- SECTION 4: DEDUPLICATE ROLE_PERMISSIONS
-- =========================================================================

-- Find duplicate entries (same role_id, permission_id pairs)
-- These shouldn't exist but may have been added by migration errors
CREATE TEMPORARY TABLE `tmp_dup_role_perms` AS
SELECT role_id, permission_id, MIN(id) as keep_id, COUNT(*) as cnt
FROM `role_permissions`
GROUP BY role_id, permission_id
HAVING cnt > 1;

-- Remove duplicates, keeping the oldest entry
DELETE FROM `role_permissions`
WHERE id NOT IN (
  SELECT keep_id FROM `tmp_dup_role_perms`
);

-- SECTION 5: TAG ROUTES WITH MODULES
-- =========================================================================

-- Tag routes based on route names and domain

-- System domain routes
UPDATE `routes` SET `module` = 'System' WHERE `domain` = 'SYSTEM';

-- School domain - infer module from route name
UPDATE `routes` SET `module` = 'Academics'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%academic%' OR
  `name` LIKE '%class%' OR
  `name` LIKE '%assess%' OR
  `name` LIKE '%result%' OR
  `name` LIKE '%timetable%'
);

UPDATE `routes` SET `module` = 'Students'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%student%' OR
  `name` LIKE '%profile%' OR
  `name` LIKE '%enroll%' OR
  `name` LIKE 'all_students%'
);

UPDATE `routes` SET `module` = 'Admissions'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%admission%' OR
  `name` LIKE '%application%'
);

UPDATE `routes` SET `module` = 'Finance'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%finance%' OR
  `name` LIKE '%payment%' OR
  `name` LIKE '%fee%' OR
  `name` LIKE '%account%' OR
  `name` LIKE '%payroll%'
);

UPDATE `routes` SET `module` = 'Attendance'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%attendance%'
);

UPDATE `routes` SET `module` = 'Discipline'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%discipline%' OR
  `name` LIKE '%conduct%'
);

UPDATE `routes` SET `module` = 'Communications'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%communication%' OR
  `name` LIKE '%announcement%' OR
  `name` LIKE '%sms%' OR
  `name` LIKE '%email%'
);

UPDATE `routes` SET `module` = 'Transport'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%transport%'
);

UPDATE `routes` SET `module` = 'Boarding'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%boarding%' OR
  `name` LIKE '%roll_call%' OR
  `name` LIKE '%exeat%'
);

UPDATE `routes` SET `module` = 'Inventory'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%inventory%' OR
  `name` LIKE '%stock%' OR
  `name` LIKE '%uniform%' OR
  `name` LIKE '%catering%' OR
  `name` LIKE '%food_store%' OR
  `name` LIKE '%menu%'
);

UPDATE `routes` SET `module` = 'Activities'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%activit%' OR
  `name` LIKE '%competition%' OR
  `name` LIKE '%talent%'
);

UPDATE `routes` SET `module` = 'Reporting'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%report%' OR
  `name` LIKE '%dashboard%'
);

UPDATE `routes` SET `module` = 'Payroll'
WHERE `domain` = 'SCHOOL' AND (
  `name` LIKE '%staff%' OR
  `name` LIKE '%payroll%' OR
  `name` LIKE '%user%'
);

SELECT '2026_04_01_rbac_module_tagging_updates' AS migration, 'ok' AS status;
