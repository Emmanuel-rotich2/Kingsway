-- Migration: 2026_07_20_repair_orphan_sidebar_routes
-- Links sidebar slugs (config/role_sidebars.php) that had NO route_permissions
-- row, so EnhancedRBACMiddleware::canAccessRoute() was denying them for every
-- role. Permissions already exist (or are created once); we only (a) ensure the
-- routes row exists, (b) create the single missing code, (c) link route->perm,
-- (d) grant the perm to displaying roles lacking it. Reversible via ROLLBACK.

-- (b) Create the (rare) missing permission code(s).
INSERT IGNORE INTO permissions (code, entity, action, module, description) VALUES
  ('students_overview_view', 'students_overview', 'view', 'Students', 'Students Overview view (auto-created by orphan-route repair)'),
  ('academic_students_view', 'academic_students', 'view', 'Academic', 'Academic Students view (auto-created by orphan-route repair)'),
  ('discipline_students_view', 'discipline_students', 'view', 'Discipline', 'Discipline Students view (auto-created by orphan-route repair)'),
  ('catering_boarding_students_view', 'catering_boarding_students', 'view', 'Catering', 'Catering Boarding Students view (auto-created by orphan-route repair)'),
  ('transport_passengers_view', 'transport_passengers', 'view', 'Transport', 'Transport Passengers view (auto-created by orphan-route repair)'),
  ('student_welfare_view', 'student_welfare', 'view', 'Student', 'Student Welfare view (auto-created by orphan-route repair)'),
  ('my_students_list_view', 'my_students_list', 'view', 'My', 'My Students List view (auto-created by orphan-route repair)'),
  ('subject_students_list_view', 'subject_students_list', 'view', 'Subject', 'Subject Students List view (auto-created by orphan-route repair)'),
  ('students_by_class_view', 'students_by_class', 'view', 'Students', 'Students By Class view (auto-created by orphan-route repair)'),
  ('view_class_lists_view', 'view_class_lists', 'view', 'View', 'View Class Lists view (auto-created by orphan-route repair)'),
  ('student_profiles_view', 'student_profiles', 'view', 'Student', 'Student Profiles view (auto-created by orphan-route repair)'),
  ('attendance_reports_view', 'attendance_reports', 'view', 'Attendance', 'Attendance Reports view (auto-created by orphan-route repair)');

-- (c) Link each route to its permission (idempotent).
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='boarding_students' AND p.code='boarding_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='term_dates' AND p.code='term_dates_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='payslips' AND p.code='payslips_ayslips_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admissions_class_placement' AND p.code='admissions_class_placement_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='placement_tests' AND p.code='placement_tests_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admissions_headteacher_applications' AND p.code='admissions_headteacher_applications_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admission_interviews' AND p.code='admission_interviews_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admissions_admission_decisions' AND p.code='admissions_admission_decisions_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admissions_pending_admission_approvals' AND p.code='admissions_pending_admission_approvals_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='admissions_academic_applications' AND p.code='admissions_academic_applications_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='assign_class_teachers' AND p.code='assign_class_teachers_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_students_performance' AND p.code='my_students_performance_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='special_needs_students' AND p.code='special_needs_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='class_attendance_history' AND p.code='class_attendance_history_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='today_absentees' AND p.code='today_absentees_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_schemes_of_work' AND p.code='my_schemes_of_work_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='create_assessment' AND p.code='create_assessment_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_cats' AND p.code='my_cats_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='enter_marks' AND p.code='enter_marks_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='class_results' AND p.code='class_results_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='grade_entry' AND p.code='grade_entry_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='log_discipline_incident' AND p.code='log_discipline_incident_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='student_behavior_notes' AND p.code='students_behavior_notes_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='class_conduct_grades' AND p.code='class_conduct_grades_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='class_parent_contacts' AND p.code='class_parent_contacts_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='send_class_message' AND p.code='send_class_message_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='parent_meeting_records' AND p.code='parent_meeting_records_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='generate_class_report' AND p.code='generate_class_report_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='student_progress_reports' AND p.code='students_progress_reports_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='class_report_cards' AND p.code='class_report_cards_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_subjects_overview' AND p.code='my_subjects_overview_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_classes_taught' AND p.code='my_classes_taught_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_subject_syllabus' AND p.code='my_subject_syllabus_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='student_subject_performance' AND p.code='students_subject_performance_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_schemes_of_work' AND p.code='subject_schemes_of_work_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='create_subject_cat' AND p.code='create_subject_cat_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_subject_cats' AND p.code='my_subject_cats_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_grade_entry' AND p.code='subject_grade_entry_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_grading_status' AND p.code='subject_grading_status_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_exam_schedule' AND p.code='subject_exam_schedule_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='enter_exam_results' AND p.code='enter_exam_results_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_results_summary' AND p.code='subject_results_summary_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='teaching_materials' AND p.code='teaching_materials_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='upload_teaching_resource' AND p.code='upload_teaching_resource_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='past_papers' AND p.code='past_papers_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='generate_subject_report' AND p.code='generate_subject_report_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_class_comparison' AND p.code='subject_class_comparison_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='intern_assigned_classes' AND p.code='intern_assigned_classes_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='intern_assigned_subjects' AND p.code='intern_assigned_subjects_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='intern_schedule' AND p.code='intern_schedule_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='view_student_info' AND p.code='view_student_info_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='observation_schedule' AND p.code='observation_schedule_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='observation_feedback' AND p.code='observation_feedback_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='improvement_areas' AND p.code='improvement_areas_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_mentor' AND p.code='my_mentor_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='mentor_meetings' AND p.code='mentor_meetings_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='mentor_notes' AND p.code='mentor_notes_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='competency_checklist' AND p.code='competency_checklist_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='development_progress' AND p.code='development_progress_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='learning_goals' AND p.code='learning_goals_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='reflection_journal' AND p.code='reflection_journal_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='view_teaching_materials' AND p.code='view_teaching_materials_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='view_syllabus' AND p.code='view_syllabus_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='view_past_papers' AND p.code='view_past_papers_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='mpesa_reconciliation' AND p.code='mpesa_reconciliation_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='cash_reconciliation' AND p.code='cash_reconciliation_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='transaction_approvals' AND p.code='transaction_approvals_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='audit_logs' AND p.code='audit_logs_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='adjustments' AND p.code='adjustments_djustments_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='exception_reports' AND p.code='exception_reports_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='asset_purchases' AND p.code='asset_purchases_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='depreciation' AND p.code='depreciation_epreciation_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='inventory_expenses' AND p.code='inventory_expenses_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='vendor_invoices' AND p.code='vendor_invoices_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='counseling_referrals' AND p.code='counseling_referrals_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='at_risk_students' AND p.code='at_risk_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='intervention_plans' AND p.code='intervention_plans_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='welfare_follow_ups' AND p.code='welfare_follow_ups_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='schedule_parent_meetings' AND p.code='schedule_parent_meetings_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='send_parent_notifications' AND p.code='send_parent_notifications_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='log_discipline_case' AND p.code='log_discipline_case_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='students_overview' AND p.code='students_overview_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='academic_students' AND p.code='academic_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='discipline_students' AND p.code='discipline_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='catering_boarding_students' AND p.code='catering_boarding_students_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='transport_passengers' AND p.code='transport_passengers_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='student_welfare' AND p.code='student_welfare_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='my_students_list' AND p.code='my_students_list_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='subject_students_list' AND p.code='subject_students_list_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='students_by_class' AND p.code='students_by_class_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='view_class_lists' AND p.code='view_class_lists_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='student_profiles' AND p.code='student_profiles_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;
INSERT IGNORE INTO route_permissions (route_id, permission_id)
  SELECT r.id, p.id FROM routes r JOIN permissions p WHERE r.name='attendance_reports' AND p.code='attendance_reports_view'
  ON DUPLICATE KEY UPDATE route_id = r.id;

-- (d) Grant the permission to each role that DISPLAYS the slug (skip if already granted).
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'boarding_students_view' AND rr.id IN (18)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'term_dates_view' AND rr.id IN (3)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'payslips_ayslips_view' AND rr.id IN (3,10,64)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admissions_class_placement_view' AND rr.id IN (4,6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'placement_tests_view' AND rr.id IN (4,6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admissions_headteacher_applications_view' AND rr.id IN (5)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admission_interviews_view' AND rr.id IN (5)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admissions_admission_decisions_view' AND rr.id IN (5)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admissions_pending_admission_approvals_view' AND rr.id IN (5)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'admissions_academic_applications_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'assign_class_teachers_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_students_performance_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'special_needs_students_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'class_attendance_history_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'today_absentees_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_schemes_of_work_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'create_assessment_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_cats_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'enter_marks_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'class_results_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'grade_entry_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'log_discipline_incident_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'students_behavior_notes_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'class_conduct_grades_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'class_parent_contacts_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'send_class_message_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'parent_meeting_records_view' AND rr.id IN (7,24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'generate_class_report_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'students_progress_reports_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'class_report_cards_view' AND rr.id IN (7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_subjects_overview_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_classes_taught_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_subject_syllabus_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'students_subject_performance_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_schemes_of_work_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'create_subject_cat_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_subject_cats_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_grade_entry_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_grading_status_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_exam_schedule_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'enter_exam_results_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_results_summary_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'teaching_materials_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'upload_teaching_resource_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'past_papers_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'generate_subject_report_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_class_comparison_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'intern_assigned_classes_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'intern_assigned_subjects_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'intern_schedule_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'view_student_info_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'observation_schedule_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'observation_feedback_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'improvement_areas_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_mentor_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'mentor_meetings_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'mentor_notes_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'competency_checklist_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'development_progress_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'learning_goals_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'reflection_journal_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'view_teaching_materials_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'view_syllabus_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'view_past_papers_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'mpesa_reconciliation_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'cash_reconciliation_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'transaction_approvals_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'audit_logs_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'adjustments_djustments_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'exception_reports_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'asset_purchases_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'depreciation_epreciation_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'inventory_expenses_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'vendor_invoices_view' AND rr.id IN (14)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'counseling_referrals_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'at_risk_students_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'intervention_plans_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'welfare_follow_ups_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'schedule_parent_meetings_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'send_parent_notifications_view' AND rr.id IN (24)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'log_discipline_case_view' AND rr.id IN (63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'students_overview_view' AND rr.id IN (3,5)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'academic_students_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'discipline_students_view' AND rr.id IN (63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'catering_boarding_students_view' AND rr.id IN (16)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'transport_passengers_view' AND rr.id IN (23)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'student_welfare_view' AND rr.id IN (24,63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'my_students_list_view' AND rr.id IN (6,7,63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'subject_students_list_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'students_by_class_view' AND rr.id IN (8)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'view_class_lists_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'student_profiles_view' AND rr.id IN (4,7,18,63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'attendance_reports_view' AND rr.id IN (3,4,5,6,18,63)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);

-- (e) Supplementary grants for slugs whose route_permissions link points at a
--     SIBLING permission code (not <slug>_view) already held by other roles but
--     missing for the role that actually DISPLAYS the link in role_sidebars.php.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'academic_schedules_edit' AND rr.id IN (3,4,5,6,7)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'dashboards_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'dashboards_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'dashboards_view' AND rr.id IN (6)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'reports_view' AND rr.id IN (9)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);
INSERT IGNORE INTO role_permissions (role_id, permission_id)
  SELECT rr.id AS role_id, p.id AS permission_id FROM roles rr JOIN permissions p
    WHERE p.code = 'dashboards_view' AND rr.id IN (10)
    AND NOT EXISTS (SELECT 1 FROM role_permissions rp WHERE rp.role_id=rr.id AND rp.permission_id=p.id);

-- ===========================================================================
-- ROLLBACK:
--   DELETE rp FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE p.code IN ('boarding_students_view','term_dates_view','payslips_ayslips_view','admissions_class_placement_view','placement_tests_view','admissions_headteacher_applications_view','admission_interviews_view','admissions_admission_decisions_view','admissions_pending_admission_approvals_view','admissions_academic_applications_view','assign_class_teachers_view','my_students_performance_view','special_needs_students_view','class_attendance_history_view','today_absentees_view','my_schemes_of_work_view','create_assessment_view','my_cats_view','enter_marks_view','class_results_view','grade_entry_view','log_discipline_incident_view','students_behavior_notes_view','class_conduct_grades_view','class_parent_contacts_view','send_class_message_view','parent_meeting_records_view','generate_class_report_view','students_progress_reports_view','class_report_cards_view','my_subjects_overview_view','my_classes_taught_view','my_subject_syllabus_view','students_subject_performance_view','subject_schemes_of_work_view','create_subject_cat_view','my_subject_cats_view','subject_grade_entry_view','subject_grading_status_view','subject_exam_schedule_view','enter_exam_results_view','subject_results_summary_view','teaching_materials_view','upload_teaching_resource_view','past_papers_view','generate_subject_report_view','subject_class_comparison_view','intern_assigned_classes_view','intern_assigned_subjects_view','intern_schedule_view','view_student_info_view','observation_schedule_view','observation_feedback_view','improvement_areas_view','my_mentor_view','mentor_meetings_view','mentor_notes_view','competency_checklist_view','development_progress_view','learning_goals_view','reflection_journal_view','view_teaching_materials_view','view_syllabus_view','view_past_papers_view','mpesa_reconciliation_view','cash_reconciliation_view','transaction_approvals_view','audit_logs_view','adjustments_djustments_view','exception_reports_view','asset_purchases_view','depreciation_epreciation_view','inventory_expenses_view','vendor_invoices_view','counseling_referrals_view','at_risk_students_view','intervention_plans_view','welfare_follow_ups_view','schedule_parent_meetings_view','send_parent_notifications_view','log_discipline_case_view','students_overview_view','academic_students_view','discipline_students_view','catering_boarding_students_view','transport_passengers_view','student_welfare_view','my_students_list_view','subject_students_list_view','students_by_class_view','view_class_lists_view','student_profiles_view','attendance_reports_view');
--   DELETE rpl FROM route_permissions rpl JOIN routes r ON r.id=rpl.route_id WHERE r.name IN ('boarding_students','term_dates','payslips','admissions_class_placement','placement_tests','admissions_headteacher_applications','admission_interviews','admissions_admission_decisions','admissions_pending_admission_approvals','admissions_academic_applications','assign_class_teachers','my_students_performance','special_needs_students','class_attendance_history','today_absentees','my_schemes_of_work','create_assessment','my_cats','enter_marks','class_results','grade_entry','log_discipline_incident','student_behavior_notes','class_conduct_grades','class_parent_contacts','send_class_message','parent_meeting_records','generate_class_report','student_progress_reports','class_report_cards','my_subjects_overview','my_classes_taught','my_subject_syllabus','student_subject_performance','subject_schemes_of_work','create_subject_cat','my_subject_cats','subject_grade_entry','subject_grading_status','subject_exam_schedule','enter_exam_results','subject_results_summary','teaching_materials','upload_teaching_resource','past_papers','generate_subject_report','subject_class_comparison','intern_assigned_classes','intern_assigned_subjects','intern_schedule','view_student_info','observation_schedule','observation_feedback','improvement_areas','my_mentor','mentor_meetings','mentor_notes','competency_checklist','development_progress','learning_goals','reflection_journal','view_teaching_materials','view_syllabus','view_past_papers','mpesa_reconciliation','cash_reconciliation','transaction_approvals','audit_logs','adjustments','exception_reports','asset_purchases','depreciation','inventory_expenses','vendor_invoices','counseling_referrals','at_risk_students','intervention_plans','welfare_follow_ups','schedule_parent_meetings','send_parent_notifications','log_discipline_case','students_overview','academic_students','discipline_students','catering_boarding_students','transport_passengers','student_welfare','my_students_list','subject_students_list','students_by_class','view_class_lists','student_profiles','attendance_reports');
--   DELETE FROM routes WHERE name IN ('boarding_students','term_dates','payslips','admissions_class_placement','placement_tests','admissions_headteacher_applications','admission_interviews','admissions_admission_decisions','admissions_pending_admission_approvals','admissions_academic_applications','assign_class_teachers','my_students_performance','special_needs_students','class_attendance_history','today_absentees','my_schemes_of_work','create_assessment','my_cats','enter_marks','class_results','grade_entry','log_discipline_incident','student_behavior_notes','class_conduct_grades','class_parent_contacts','send_class_message','parent_meeting_records','generate_class_report','student_progress_reports','class_report_cards','my_subjects_overview','my_classes_taught','my_subject_syllabus','student_subject_performance','subject_schemes_of_work','create_subject_cat','my_subject_cats','subject_grade_entry','subject_grading_status','subject_exam_schedule','enter_exam_results','subject_results_summary','teaching_materials','upload_teaching_resource','past_papers','generate_subject_report','subject_class_comparison','intern_assigned_classes','intern_assigned_subjects','intern_schedule','view_student_info','observation_schedule','observation_feedback','improvement_areas','my_mentor','mentor_meetings','mentor_notes','competency_checklist','development_progress','learning_goals','reflection_journal','view_teaching_materials','view_syllabus','view_past_papers','mpesa_reconciliation','cash_reconciliation','transaction_approvals','audit_logs','adjustments','exception_reports','asset_purchases','depreciation','inventory_expenses','vendor_invoices','counseling_referrals','at_risk_students','intervention_plans','welfare_follow_ups','schedule_parent_meetings','send_parent_notifications','log_discipline_case','students_overview','academic_students','discipline_students','catering_boarding_students','transport_passengers','student_welfare','my_students_list','subject_students_list','students_by_class','view_class_lists','student_profiles','attendance_reports');
--   DELETE FROM permissions WHERE code IN ('students_overview_view','academic_students_view','discipline_students_view','catering_boarding_students_view','transport_passengers_view','student_welfare_view','my_students_list_view','subject_students_list_view','students_by_class_view','view_class_lists_view','student_profiles_view','attendance_reports_view');
-- ===========================================================================
