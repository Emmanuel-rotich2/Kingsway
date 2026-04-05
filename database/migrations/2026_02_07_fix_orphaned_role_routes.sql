-- ============================================================
-- Migration: Fix 144 Orphaned Routes in role_routes
-- Date: 2026-02-07
-- Purpose: Populate role_routes for all routes that exist in
--          the routes table but have no role_routes entry.
--          The RouteAuthorization middleware uses DENY-BY-DEFAULT,
--          so missing entries = inaccessible pages.
-- Total: 189 INSERT IGNORE rows
-- Safety: INSERT IGNORE + UNIQUE KEY uk_role_route = idempotent
-- ============================================================

START TRANSACTION;

-- ============================================================
-- CATEGORY 1: SYSTEM DOMAIN ROUTES -> System Administrator (role_id=2)
-- 61 routes: Infrastructure/admin routes visible in System Admin sidebar
-- ============================================================

INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(2, 17, 1),   -- manage_routes
(2, 18, 1),   -- manage_menus
(2, 19, 1),   -- manage_dashboards
(2, 20, 1),   -- manage_policies
(2, 21, 1),   -- config_sync
(2, 88, 1),   -- system_uptime
(2, 89, 1),   -- active_users
(2, 90, 1),   -- error_rate
(2, 91, 1),   -- queue_health
(2, 92, 1),   -- db_health
(2, 94, 1),   -- account_status
(2, 95, 1),   -- role_definitions
(2, 96, 1),   -- role_scope
(2, 97, 1),   -- permission_registry
(2, 98, 1),   -- temporary_roles
(2, 99, 1),   -- expiry_based_access
(2, 101, 1),  -- route_registry
(2, 102, 1),  -- route_domains
(2, 103, 1),  -- sidebar_menus
(2, 104, 1),  -- submenus_management
(2, 105, 1),  -- icons_ordering
(2, 106, 1),  -- dashboard_registry
(2, 107, 1),  -- role_dashboard_mapping
(2, 108, 1),  -- domain_isolation_rules
(2, 109, 1),  -- readonly_enforcement
(2, 110, 1),  -- time_bound_access
(2, 111, 1),  -- location_device_rules
(2, 112, 1),  -- audit_requirements
(2, 113, 1),  -- retention_policies
(2, 114, 1),  -- authorization_logs
(2, 115, 1),  -- failed_login_attempts
(2, 116, 1),  -- active_sessions
(2, 117, 1),  -- force_logout
(2, 118, 1),  -- revoke_tokens
(2, 120, 1),  -- background_jobs
(2, 121, 1),  -- queue_monitor
(2, 122, 1),  -- api_metrics
(2, 127, 1),  -- feature_flags
(2, 128, 1),  -- module_enablement
(2, 131, 1),  -- schema_registry
(2, 132, 1),  -- migrations
(2, 133, 1),  -- backups
(2, 134, 1),  -- data_retention_rules
(2, 135, 1),  -- anonymization_rules
(2, 136, 1),  -- webhook_registry
(2, 137, 1),  -- job_inspector
(2, 138, 1),  -- system_diagnostics
(2, 140, 1),  -- permission_changes
(2, 141, 1),  -- policy_violations
(2, 142, 1),  -- security_incidents
(2, 144, 1),  -- role_permission_matrix
(2, 145, 1),  -- resource_based_permissions
(2, 146, 1),  -- widget_registry
(2, 147, 1),  -- role_navigation_config
(2, 148, 1),  -- route_access_rules
(2, 149, 1),  -- permission_policies
(2, 150, 1),  -- token_management
(2, 151, 1),  -- ip_whitelist_blacklist
(2, 152, 1),  -- rate_limiting_status
(2, 153, 1),  -- data_retention
(2, 154, 1);  -- data_purge_policies

-- ============================================================
-- CATEGORY 2: SCHOOL ROUTES WITH CONFIRMED SIDEBAR MAPPINGS
-- 75 rows: Derived from sidebar_menu_items + role_sidebar_menus
-- ============================================================

-- Headteacher (role_id=5) -- 41 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 80, 1),     -- manage_email
(5, 81, 1),     -- manage_sms
(5, 161, 1),    -- current_academic_year
(5, 162, 1),    -- manage_terms
(5, 164, 1),    -- year_history
(5, 169, 1),    -- schemes_of_work
(5, 170, 1),    -- all_lesson_plans
(5, 171, 1),    -- lesson_plans_by_class
(5, 172, 1),    -- lesson_plans_by_teacher
(5, 173, 1),    -- lesson_plan_approval
(5, 174, 1),    -- academic_calendar
(5, 178, 1),    -- exam_schedule
(5, 179, 1),    -- exam_setup
(5, 181, 1),    -- grading_status
(5, 182, 1),    -- results_analysis
(5, 183, 1),    -- report_cards
(5, 204, 1),    -- all_teachers
(5, 205, 1),    -- class_teachers
(5, 206, 1),    -- subject_teachers
(5, 207, 1),    -- teacher_workload
(5, 209, 1),    -- all_parents
(5, 210, 1),    -- parent_meetings
(5, 211, 1),    -- parent_feedback
(5, 212, 1),    -- pta_management
(5, 213, 1),    -- all_classes
(5, 214, 1),    -- class_streams
(5, 215, 1),    -- class_capacity
(5, 217, 1),    -- new_applications
(5, 218, 1),    -- admission_status
(5, 220, 1),    -- conduct_reports
(5, 221, 1),    -- academic_reports
(5, 222, 1),    -- performance_analysis
(5, 223, 1),    -- term_reports
(5, 225, 1),    -- sports
(5, 226, 1),    -- competitions
(5, 227, 1),    -- school_events
(5, 228, 1),    -- assemblies
(5, 229, 1),    -- special_needs
(5, 230, 1),    -- counseling_records
(5, 236, 1),    -- comparative_reports
(5, 239, 1);    -- fee_defaulters

-- Accountant (role_id=10) -- 9 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(10, 156, 1),     -- manage_fee_structure
(10, 201, 1),     -- all_students
(10, 100000, 1),  -- unmatched_payments
(10, 100001, 1),  -- vendors
(10, 100002, 1),  -- purchase_orders
(10, 100003, 1),  -- petty_cash
(10, 100004, 1),  -- bank_accounts
(10, 100005, 1),  -- bank_transactions
(10, 100006, 1);  -- mpesa_settlements

-- Deputy Head - Discipline (role_id=63) -- 5 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(63, 210, 1),  -- parent_meetings
(63, 219, 1),  -- discipline_cases
(63, 220, 1),  -- conduct_reports
(63, 230, 1),  -- counseling_records
(63, 241, 1);  -- deputy_head_discipline_dashboard

-- Deputy Head - Academic (role_id=6) -- 3 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(6, 178, 1),   -- exam_schedule
(6, 205, 1),   -- class_teachers
(6, 240, 1);   -- deputy_head_academic_dashboard

-- Director (role_id=3) -- 5 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(3, 80, 1),    -- manage_email
(3, 81, 1),    -- manage_sms
(3, 155, 1),   -- manage_transport
(3, 156, 1),   -- manage_fee_structure
(3, 157, 1);   -- manage_uniform_sales

-- School Administrator (role_id=4) -- 3 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(4, 65, 1),    -- manage_non_teaching_staff
(4, 80, 1),    -- manage_email
(4, 81, 1);    -- manage_sms

-- Class Teacher (role_id=7) -- 4 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(7, 169, 1),   -- schemes_of_work
(7, 183, 1),   -- report_cards
(7, 223, 1),   -- term_reports
(7, 229, 1);   -- special_needs

-- Subject Teacher (role_id=8) -- 3 rows
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(8, 169, 1),   -- schemes_of_work
(8, 178, 1),   -- exam_schedule
(8, 181, 1);   -- grading_status

-- Intern/Student Teacher (role_id=9) -- 1 row
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(9, 169, 1);   -- schemes_of_work

-- Driver (role_id=23) -- 1 row
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(23, 155, 1);  -- manage_transport

-- ============================================================
-- CATEGORY 3: SCHOOL ROUTES WITHOUT SIDEBAR MAPPINGS
-- 45 rows: Logical role assignment based on responsibilities
-- ============================================================

-- academic_years -> HT, DH-Acad, SchAdmin
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 160, 1),   -- Headteacher
(6, 160, 1),   -- Deputy Head - Academic
(4, 160, 1);   -- School Administrator

-- all_staff -> HT, Director, SchAdmin
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 202, 1),   -- Headteacher
(3, 202, 1),   -- Director
(4, 202, 1);   -- School Administrator

-- all_subjects -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 166, 1),   -- Headteacher
(6, 166, 1);   -- Deputy Head - Academic

-- assessments_exams -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 177, 1),   -- Headteacher
(6, 177, 1);   -- Deputy Head - Academic

-- assign_subjects_to_teachers -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 167, 1),   -- Headteacher
(6, 167, 1);   -- Deputy Head - Academic

-- attendance_trends -> HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 235, 1);   -- Headteacher

-- balances_by_class -> HT, Accountant
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 238, 1),   -- Headteacher
(10, 238, 1);  -- Accountant

-- clubs_societies -> HT, Talent Development
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 224, 1),   -- Headteacher
(21, 224, 1);  -- Talent Development

-- curriculum_cbc -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 168, 1),   -- Headteacher
(6, 168, 1);   -- Deputy Head - Academic

-- dormitory_management -> Boarding Master, Director, HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(18, 232, 1),  -- Boarding Master
(3, 232, 1),   -- Director
(5, 232, 1);   -- Headteacher

-- enrollment_trends -> HT, Director
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 233, 1),   -- Headteacher
(3, 233, 1);   -- Director

-- enter_results -> Class Teacher, Subject Teacher, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(7, 52, 1),    -- Class Teacher
(8, 52, 1),    -- Subject Teacher
(6, 52, 1);    -- Deputy Head - Academic

-- import_existing_students -> School Administrator
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(4, 41, 1);    -- School Administrator

-- learning_areas -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 165, 1),   -- Headteacher
(6, 165, 1);   -- Deputy Head - Academic

-- manage_calendar_events -> HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 176, 1);   -- Headteacher

-- performance_trends -> HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 234, 1);   -- Headteacher

-- permissions_exeats -> Boarding Master, Director, HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(18, 231, 1),  -- Boarding Master
(3, 231, 1),   -- Director
(5, 231, 1);   -- Headteacher

-- staff_performance_overview -> HT, Director
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 203, 1),   -- Headteacher
(3, 203, 1);   -- Director

-- student_promotion -> HT, DH-Acad, SchAdmin
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 216, 1),   -- Headteacher
(6, 216, 1),   -- Deputy Head - Academic
(4, 216, 1);   -- School Administrator

-- students_with_balance -> HT, Accountant
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 237, 1),   -- Headteacher
(10, 237, 1);  -- Accountant

-- supervision_roster -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 180, 1),   -- Headteacher
(6, 180, 1);   -- Deputy Head - Academic

-- teacher_performance_reviews -> HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 208, 1);   -- Headteacher

-- view_calendar -> HT, DH-Acad
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 175, 1),   -- Headteacher
(6, 175, 1);   -- Deputy Head - Academic

-- year_calendar -> HT
INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 163, 1);   -- Headteacher

-- ============================================================
-- CATEGORY 4: UNIFIED TEACHER DASHBOARD (route_id=99999)
-- Shared dashboard for all teacher sub-roles
-- ============================================================

INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(7, 99999, 1),   -- Class Teacher
(8, 99999, 1),   -- Subject Teacher
(9, 99999, 1);   -- Intern/Student Teacher

-- ============================================================
-- CATEGORY 5: TEST ITEMS (for development/testing only)
-- ============================================================

INSERT IGNORE INTO `role_routes` (`role_id`, `route_id`, `is_allowed`) VALUES
(5, 99998, 1),   -- Headteacher: headteacher_test_item
(2, 88887, 1);   -- System Administrator: admin_delegation_test_item

COMMIT;

-- ============================================================
-- VERIFICATION QUERIES (run after migration)
-- ============================================================

-- 1. Count remaining orphaned routes (should be 0)
SELECT COUNT(*) AS orphaned_after
FROM routes r
LEFT JOIN role_routes rr ON r.id = rr.route_id
WHERE rr.id IS NULL AND r.is_active = 1;

-- 2. List any remaining orphans
SELECT r.id, r.name, r.domain, r.description
FROM routes r
LEFT JOIN role_routes rr ON r.id = rr.route_id
WHERE rr.id IS NULL AND r.is_active = 1
ORDER BY r.domain, r.id;

-- 3. Routes per role after migration
SELECT
    ro.id AS role_id,
    ro.name AS role_name,
    COUNT(rr.id) AS total_routes,
    SUM(CASE WHEN rt.domain = 'SYSTEM' THEN 1 ELSE 0 END) AS system_routes,
    SUM(CASE WHEN rt.domain = 'SCHOOL' THEN 1 ELSE 0 END) AS school_routes
FROM roles ro
LEFT JOIN role_routes rr ON ro.id = rr.role_id AND rr.is_allowed = 1
LEFT JOIN routes rt ON rr.route_id = rt.id AND rt.is_active = 1
WHERE ro.id NOT IN (32, 33, 34, 64, 65, 66, 67, 68, 69, 70)
GROUP BY ro.id, ro.name
ORDER BY total_routes DESC;

-- 4. Verify no SYSTEM routes leaked to non-SysAdmin roles
SELECT
    ro.name AS role_name,
    rt.name AS route_name,
    rt.domain
FROM role_routes rr
JOIN roles ro ON rr.role_id = ro.id
JOIN routes rt ON rr.route_id = rt.id
WHERE rt.domain = 'SYSTEM' AND ro.id != 2
ORDER BY ro.name, rt.name;

-- 5. Verify no duplicate entries
SELECT role_id, route_id, COUNT(*) AS cnt
FROM role_routes
GROUP BY role_id, route_id
HAVING cnt > 1;
