# Frontend Actualisation Plan

Generated: 2026-02-12

Sources: route_analysis.txt, shared_routes_analysis.txt, missing_files_analysis.txt, ENDPOINTS_FOR_FRONTEND.txt

Status legend: Working = page/dashboard exists and is wired to API; Partially working = page exists but wiring not fully validated; Blank = page exists with placeholder-only UI; Broken = route has no resolvable file.

## A) Route Inventory

### System Domain

| Route | URL | Page/View | Module | Roles | Permissions | Shared | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| system_administrator_dashboard | home.php?route=system_administrator_dashboard | components/dashboards/system_administrator_dashboard.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| system_health | home.php?route=system_health | pages/system_health.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| error_logs | home.php?route=error_logs | pages/error_logs.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| authentication_logs | home.php?route=authentication_logs | pages/authentication_logs.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| activity_audit_logs | home.php?route=activity_audit_logs | pages/activity_audit_logs.php | Activities | System Administrator | DB route_permissions | 1 | Partially working |
| system_settings | home.php?route=system_settings | pages/system_settings.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| module_management | home.php?route=module_management | pages/module_management.php | General | System Administrator | DB route_permissions | 1 | Partially working |
| maintenance_mode | home.php?route=maintenance_mode | pages/maintenance_mode.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| api_explorer | home.php?route=api_explorer | pages/api_explorer.php | General | System Administrator | DB route_permissions | 1 | Partially working |
| job_queue_monitor | home.php?route=job_queue_monitor | pages/job_queue_monitor.php | General | System Administrator | DB route_permissions | 1 | Partially working |
| cache_monitor | home.php?route=cache_monitor | pages/cache_monitor.php | General | System Administrator | DB route_permissions | 1 | Partially working |
| db_health_monitor | home.php?route=db_health_monitor | pages/db_health_monitor.php | General | System Administrator | DB route_permissions | 1 | Partially working |
| manage_users | home.php?route=manage_users | pages/manage_users.php | System | School Administrator, System Administrator | DB route_permissions | 2 | Partially working |
| manage_roles | home.php?route=manage_roles | pages/manage_roles.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| manage_permissions | home.php?route=manage_permissions | pages/manage_permissions.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| delegated_permissions | home.php?route=delegated_permissions | pages/delegated_permissions.php | System | System Administrator | DB route_permissions | 1 | Partially working |
| manage_routes | home.php?route=manage_routes | pages/manage_routes.php | Transport | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_menus | home.php?route=manage_menus | pages/manage_menus.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_dashboards | home.php?route=manage_dashboards | pages/manage_dashboards.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_policies | home.php?route=manage_policies | pages/manage_policies.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| config_sync | home.php?route=config_sync | pages/config_sync.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| system_uptime | home.php?route=system_uptime | pages/system_uptime.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| active_users | home.php?route=active_users | pages/active_users.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| error_rate | home.php?route=error_rate | pages/error_rate.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| queue_health | home.php?route=queue_health | pages/queue_health.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| db_health | home.php?route=db_health | pages/db_health.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| account_status | home.php?route=account_status | pages/account_status.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| role_definitions | home.php?route=role_definitions | pages/role_definitions.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| role_scope | home.php?route=role_scope | pages/role_scope.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| permission_registry | home.php?route=permission_registry | pages/permission_registry.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| temporary_roles | home.php?route=temporary_roles | pages/temporary_roles.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| expiry_based_access | home.php?route=expiry_based_access | pages/expiry_based_access.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| route_registry | home.php?route=route_registry | pages/route_registry.php | Transport | (no role assignment) | DB route_permissions | 0 | Partially working |
| route_domains | home.php?route=route_domains | pages/route_domains.php | Transport | (no role assignment) | DB route_permissions | 0 | Partially working |
| sidebar_menus | home.php?route=sidebar_menus | pages/sidebar_menus.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| submenus_management | home.php?route=submenus_management | pages/submenus_management.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| icons_ordering | home.php?route=icons_ordering | pages/icons_ordering.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| dashboard_registry | home.php?route=dashboard_registry | pages/dashboard_registry.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| role_dashboard_mapping | home.php?route=role_dashboard_mapping | pages/role_dashboard_mapping.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| domain_isolation_rules | home.php?route=domain_isolation_rules | pages/domain_isolation_rules.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| readonly_enforcement | home.php?route=readonly_enforcement | pages/readonly_enforcement.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| time_bound_access | home.php?route=time_bound_access | pages/time_bound_access.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| location_device_rules | home.php?route=location_device_rules | pages/location_device_rules.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| audit_requirements | home.php?route=audit_requirements | pages/audit_requirements.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| retention_policies | home.php?route=retention_policies | pages/retention_policies.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| authorization_logs | home.php?route=authorization_logs | pages/authorization_logs.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| failed_login_attempts | home.php?route=failed_login_attempts | pages/failed_login_attempts.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| active_sessions | home.php?route=active_sessions | pages/active_sessions.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| force_logout | home.php?route=force_logout | pages/force_logout.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| revoke_tokens | home.php?route=revoke_tokens | pages/revoke_tokens.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| background_jobs | home.php?route=background_jobs | pages/background_jobs.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| queue_monitor | home.php?route=queue_monitor | pages/queue_monitor.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| api_metrics | home.php?route=api_metrics | pages/api_metrics.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| feature_flags | home.php?route=feature_flags | pages/feature_flags.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| module_enablement | home.php?route=module_enablement | pages/module_enablement.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| schema_registry | home.php?route=schema_registry | pages/schema_registry.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| migrations | home.php?route=migrations | pages/migrations.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| backups | home.php?route=backups | pages/backups.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| data_retention_rules | home.php?route=data_retention_rules | pages/data_retention_rules.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| anonymization_rules | home.php?route=anonymization_rules | pages/anonymization_rules.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| webhook_registry | home.php?route=webhook_registry | pages/webhook_registry.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| job_inspector | home.php?route=job_inspector | pages/job_inspector.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| system_diagnostics | home.php?route=system_diagnostics | pages/system_diagnostics.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| permission_changes | home.php?route=permission_changes | pages/permission_changes.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| policy_violations | home.php?route=policy_violations | pages/policy_violations.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| security_incidents | home.php?route=security_incidents | pages/security_incidents.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| role_permission_matrix | home.php?route=role_permission_matrix | pages/role_permission_matrix.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| resource_based_permissions | home.php?route=resource_based_permissions | pages/resource_based_permissions.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| widget_registry | home.php?route=widget_registry | pages/widget_registry.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| role_navigation_config | home.php?route=role_navigation_config | pages/role_navigation_config.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| route_access_rules | home.php?route=route_access_rules | pages/route_access_rules.php | Transport | (no role assignment) | DB route_permissions | 0 | Partially working |
| permission_policies | home.php?route=permission_policies | pages/permission_policies.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| token_management | home.php?route=token_management | pages/token_management.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| ip_whitelist_blacklist | home.php?route=ip_whitelist_blacklist | pages/ip_whitelist_blacklist.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| rate_limiting_status | home.php?route=rate_limiting_status | pages/rate_limiting_status.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| data_retention | home.php?route=data_retention | pages/data_retention.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| data_purge_policies | home.php?route=data_purge_policies | pages/data_purge_policies.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |

### School Domain

| Route | URL | Page/View | Module | Roles | Permissions | Shared | Status |
| --- | --- | --- | --- | --- | --- | --- | --- |
| director_owner_dashboard | home.php?route=director_owner_dashboard | components/dashboards/director_owner_dashboard.php | System | Director | DB route_permissions | 1 | Partially working |
| school_administrative_officer_dashboard | home.php?route=school_administrative_officer_dashboard | components/dashboards/school_administrative_officer_dashboard.php | System | School Administrator | DB route_permissions | 1 | Partially working |
| headteacher_dashboard | home.php?route=headteacher_dashboard | components/dashboards/headteacher_dashboard.php | Staff/HR | Headteacher | DB route_permissions | 1 | Partially working |
| class_teacher_dashboard | home.php?route=class_teacher_dashboard | components/dashboards/class_teacher_dashboard.php | Academics | Class Teacher | DB route_permissions | 1 | Partially working |
| subject_teacher_dashboard | home.php?route=subject_teacher_dashboard | components/dashboards/subject_teacher_dashboard.php | Academics | Subject Teacher | DB route_permissions | 1 | Partially working |
| intern_student_teacher_dashboard | home.php?route=intern_student_teacher_dashboard | components/dashboards/intern_student_teacher_dashboard.php | Students | Intern/Student Teacher | DB route_permissions | 1 | Partially working |
| school_accountant_dashboard | home.php?route=school_accountant_dashboard | components/dashboards/school_accountant_dashboard.php | System | Accountant | DB route_permissions | 1 | Partially working |
| store_manager_dashboard | home.php?route=store_manager_dashboard | components/dashboards/store_manager_dashboard.php | System | Inventory Manager | DB route_permissions | 1 | Partially working |
| catering_manager_cook_lead_dashboard | home.php?route=catering_manager_cook_lead_dashboard | components/dashboards/catering_manager_cook_lead_dashboard.php | System | Cateress | DB route_permissions | 1 | Partially working |
| matron_housemother_dashboard | home.php?route=matron_housemother_dashboard | components/dashboards/matron_housemother_dashboard.php | Boarding | Boarding Master | DB route_permissions | 1 | Partially working |
| hod_talent_development_dashboard | home.php?route=hod_talent_development_dashboard | components/dashboards/hod_talent_development_dashboard.php | Activities | Talent Development | DB route_permissions | 1 | Partially working |
| driver_dashboard | home.php?route=driver_dashboard | components/dashboards/driver_dashboard.php | Transport | Driver | DB route_permissions | 1 | Partially working |
| school_counselor_chaplain_dashboard | home.php?route=school_counselor_chaplain_dashboard | components/dashboards/school_counselor_chaplain_dashboard.php | System | Chaplain | DB route_permissions | 1 | Partially working |
| manage_students | home.php?route=manage_students | pages/manage_students.php | Students | Deputy Head - Academic, Deputy Head - Discipline, Headteacher, School Administrator | DB route_permissions | 4 | Working |
| manage_students_admissions | home.php?route=manage_students_admissions | pages/manage_students_admissions.php | Students | Deputy Head - Discipline, Headteacher, School Administrator | DB route_permissions | 3 | Working |
| student_performance | home.php?route=student_performance | pages/student_performance.php | Students | Headteacher | DB route_permissions | 1 | Partially working |
| student_discipline | home.php?route=student_discipline | pages/student_discipline.php | Students | Deputy Head - Discipline | DB route_permissions | 1 | Partially working |
| student_counseling | home.php?route=student_counseling | pages/student_counseling.php | Students | Chaplain | DB route_permissions | 1 | Partially working |
| import_existing_students | home.php?route=import_existing_students | pages/import_existing_students.php | Students | (no role assignment) | DB route_permissions | 0 | Working |
| manage_academics | home.php?route=manage_academics | pages/manage_academics.php | Academics | Deputy Head - Academic, Headteacher, School Administrator | DB route_permissions | 3 | Partially working |
| manage_classes | home.php?route=manage_classes | pages/manage_classes.php | Academics | Deputy Head - Academic, Headteacher, School Administrator | DB route_permissions | 3 | Partially working |
| manage_subjects | home.php?route=manage_subjects | pages/manage_subjects.php | Academics | Headteacher, School Administrator | DB route_permissions | 2 | Partially working |
| manage_timetable | home.php?route=manage_timetable | pages/manage_timetable.php | Academics | Deputy Head - Academic, Headteacher, School Administrator | DB route_permissions | 3 | Partially working |
| manage_assessments | home.php?route=manage_assessments | pages/manage_assessments.php | Academics | Class Teacher, Deputy Head - Academic, Headteacher | DB route_permissions | 3 | Partially working |
| manage_lesson_plans | home.php?route=manage_lesson_plans | pages/manage_lesson_plans.php | Academics | Class Teacher, Headteacher, Intern/Student Teacher, Subject Teacher | DB route_permissions | 4 | Partially working |
| myclasses | home.php?route=myclasses | pages/myclasses.php | Academics | Class Teacher, Intern/Student Teacher, Subject Teacher | DB route_permissions | 3 | Partially working |
| add_results | home.php?route=add_results | pages/add_results.php | Academics | Deputy Head - Academic, Headteacher | DB route_permissions | 2 | Partially working |
| submit_results | home.php?route=submit_results | pages/submit_results.php | Academics | Class Teacher, Subject Teacher | DB route_permissions | 2 | Partially working |
| view_results | home.php?route=view_results | pages/view_results.php | Academics | Class Teacher, Deputy Head - Academic, Headteacher, School Administrator, Subject Teacher | DB route_permissions | 5 | Partially working |
| enter_results | home.php?route=enter_results | pages/enter_results.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_finance | home.php?route=manage_finance | pages/manage_finance.php | Finance | Director, School Administrator | DB route_permissions | 2 | Partially working |
| manage_fees | home.php?route=manage_fees | pages/manage_fees.php | Finance | Accountant | DB route_permissions | 1 | Partially working |
| student_fees | home.php?route=student_fees | pages/student_fees.php | Students | Accountant | DB route_permissions | 1 | Partially working |
| manage_payments | home.php?route=manage_payments | pages/manage_payments.php | Finance | Accountant | DB route_permissions | 1 | Partially working |
| manage_payrolls | home.php?route=manage_payrolls | pages/manage_payrolls.php | Finance | Accountant | DB route_permissions | 1 | Partially working |
| payroll | home.php?route=payroll | pages/payroll.php | Finance | Accountant | DB route_permissions | 1 | Partially working |
| manage_expenses | home.php?route=manage_expenses | pages/manage_expenses.php | Finance | Accountant | DB route_permissions | 1 | Partially working |
| finance_reports | home.php?route=finance_reports | pages/finance_reports.php | Finance | Accountant, Director | DB route_permissions | 2 | Partially working |
| financial_reports | home.php?route=financial_reports | pages/financial_reports.php | General | Director | DB route_permissions | 1 | Partially working |
| budget_overview | home.php?route=budget_overview | pages/budget_overview.php | Finance | Director | DB route_permissions | 1 | Partially working |
| finance_approvals | home.php?route=finance_approvals | pages/finance_approvals.php | Finance | Director | DB route_permissions | 1 | Partially working |
| manage_staff | home.php?route=manage_staff | pages/manage_staff.php | Staff/HR | Headteacher, School Administrator | DB route_permissions | 2 | Partially working |
| manage_non_teaching_staff | home.php?route=manage_non_teaching_staff | pages/manage_non_teaching_staff.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| staff_attendance | home.php?route=staff_attendance | pages/staff_attendance.php | Attendance | Class Teacher, Deputy Head - Discipline, Director, Headteacher, School Administrator, Subject Teacher | DB route_permissions | 6 | Partially working |
| staff_performance | home.php?route=staff_performance | pages/staff_performance.php | Staff/HR | Headteacher | DB route_permissions | 1 | Partially working |
| mark_attendance | home.php?route=mark_attendance | pages/mark_attendance.php | Attendance | Class Teacher, Deputy Head - Discipline, Director, Headteacher, School Administrator, Subject Teacher | DB route_permissions | 6 | Partially working |
| view_attendance | home.php?route=view_attendance | pages/view_attendance.php | Attendance | Class Teacher, Deputy Head - Discipline, Director, Headteacher, Subject Teacher | DB route_permissions | 5 | Partially working |
| manage_inventory | home.php?route=manage_inventory | pages/manage_inventory.php | Inventory | Inventory Manager | DB route_permissions | 1 | Partially working |
| manage_stock | home.php?route=manage_stock | pages/manage_stock.php | Inventory | Inventory Manager | DB route_permissions | 1 | Partially working |
| manage_requisitions | home.php?route=manage_requisitions | pages/manage_requisitions.php | Inventory | Inventory Manager | DB route_permissions | 1 | Partially working |
| food_store | home.php?route=food_store | pages/food_store.php | General | Cateress | DB route_permissions | 1 | Partially working |
| menu_planning | home.php?route=menu_planning | pages/menu_planning.php | General | Cateress | DB route_permissions | 1 | Partially working |
| manage_boarding | home.php?route=manage_boarding | pages/manage_boarding.php | Boarding | Boarding Master | DB route_permissions | 1 | Partially working |
| manage_activities | home.php?route=manage_activities | pages/manage_activities.php | General | Deputy Head - Discipline, Talent Development | DB route_permissions | 2 | Partially working |
| chapel_services | home.php?route=chapel_services | pages/chapel_services.php | General | Chaplain | DB route_permissions | 1 | Partially working |
| manage_communications | home.php?route=manage_communications | pages/manage_communications.php | Communications | Accountant, Boarding Master, Cateress, Chaplain, Class Teacher, Deputy Head - Academic, Deputy Head - Discipline, Director, Driver, Headteacher, Intern/Student Teacher, Inventory Manager, School Administrator, Subject Teacher, System Administrator (via home only), Talent Development | DB route_permissions | 16 | Partially working |
| manage_announcements | home.php?route=manage_announcements | pages/manage_announcements.php | Communications | Deputy Head - Discipline, Driver, School Administrator | DB route_permissions | 3 | Partially working |
| manage_email | home.php?route=manage_email | pages/manage_email.php | Communications | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_sms | home.php?route=manage_sms | pages/manage_sms.php | Communications | (no role assignment) | DB route_permissions | 0 | Partially working |
| enrollment_reports | home.php?route=enrollment_reports | pages/enrollment_reports.php | General | Director | DB route_permissions | 1 | Partially working |
| performance_reports | home.php?route=performance_reports | pages/performance_reports.php | General | Director | DB route_permissions | 1 | Partially working |
| my_routes | home.php?route=my_routes | pages/my_routes.php | Transport | Driver | DB route_permissions | 1 | Partially working |
| my_vehicle | home.php?route=my_vehicle | pages/my_vehicle.php | Transport | Driver | DB route_permissions | 1 | Partially working |
| home | home.php | home.php | General | Accountant, Boarding Master, Cateress, Chaplain, Class Teacher, Deputy Head - Academic, Deputy Head - Discipline, Director, Driver, Headteacher, Intern/Student Teacher, Inventory Manager, School Administrator, Subject Teacher, System Administrator, Talent Development | DB route_permissions | 16 | Working |
| me | home.php?route=me | pages/me.php | General | Accountant, Boarding Master, Cateress, Chaplain, Class Teacher, Deputy Head - Academic, Deputy Head - Discipline, Director, Driver, Headteacher, Intern/Student Teacher, Inventory Manager, School Administrator, Subject Teacher, System Administrator, Talent Development | DB route_permissions | 16 | Partially working |
| manage_transport | home.php?route=manage_transport | pages/manage_transport.php | Transport | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_fee_structure | home.php?route=manage_fee_structure | pages/manage_fee_structure.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_uniform_sales | manage_uniform_sales | pages/manage_uniform_sales.php | Inventory | (no role assignment) | DB route_permissions | 0 | Partially working |
| boarding_roll_call | home.php?route=boarding_roll_call | pages/boarding_roll_call.php | Attendance | Boarding Master, Class Teacher, Director, Headteacher, Subject Teacher | DB route_permissions | 5 | Partially working |
| academic_years | home.php?route=academic_years | pages/academic_years.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| current_academic_year | home.php?route=current_academic_year | pages/current_academic_year.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_terms | home.php?route=manage_terms | pages/manage_terms.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| year_calendar | home.php?route=year_calendar | pages/year_calendar.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| year_history | home.php?route=year_history | pages/year_history.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| learning_areas | home.php?route=learning_areas | pages/learning_areas.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_subjects | home.php?route=all_subjects | pages/all_subjects.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| assign_subjects_to_teachers | home.php?route=assign_subjects_to_teachers | pages/assign_subjects_to_teachers.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| curriculum_cbc | home.php?route=curriculum_cbc | pages/curriculum_cbc.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| schemes_of_work | home.php?route=schemes_of_work | pages/schemes_of_work.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_lesson_plans | home.php?route=all_lesson_plans | pages/all_lesson_plans.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| lesson_plans_by_class | home.php?route=lesson_plans_by_class | pages/lesson_plans_by_class.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| lesson_plans_by_teacher | home.php?route=lesson_plans_by_teacher | pages/lesson_plans_by_teacher.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| lesson_plan_approval | home.php?route=lesson_plan_approval | pages/lesson_plan_approval.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| academic_calendar | home.php?route=academic_calendar | pages/academic_calendar.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| view_calendar | home.php?route=view_calendar | pages/view_calendar.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| manage_calendar_events | home.php?route=manage_calendar_events | pages/manage_calendar_events.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| assessments_exams | home.php?route=assessments_exams | pages/assessments_exams.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| exam_schedule | home.php?route=exam_schedule | pages/exam_schedule.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| exam_setup | home.php?route=exam_setup | pages/exam_setup.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| supervision_roster | home.php?route=supervision_roster | pages/supervision_roster.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| grading_status | home.php?route=grading_status | pages/grading_status.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| results_analysis | home.php?route=results_analysis | pages/results_analysis.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| report_cards | home.php?route=report_cards | pages/report_cards.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_students | home.php?route=all_students | pages/all_students.php | Students | (no role assignment) | DB route_permissions | 0 | Working |
| all_staff | home.php?route=all_staff | pages/all_staff.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| staff_performance_overview | home.php?route=staff_performance_overview | pages/staff_performance_overview.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_teachers | home.php?route=all_teachers | pages/all_teachers.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| class_teachers | home.php?route=class_teachers | pages/class_teachers.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| subject_teachers | home.php?route=subject_teachers | pages/subject_teachers.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| teacher_workload | home.php?route=teacher_workload | pages/teacher_workload.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| teacher_performance_reviews | home.php?route=teacher_performance_reviews | pages/teacher_performance_reviews.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_parents | home.php?route=all_parents | pages/all_parents.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| parent_meetings | home.php?route=parent_meetings | pages/parent_meetings.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| parent_feedback | home.php?route=parent_feedback | pages/parent_feedback.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| pta_management | home.php?route=pta_management | pages/pta_management.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| all_classes | home.php?route=all_classes | pages/all_classes.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| class_streams | home.php?route=class_streams | pages/class_streams.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| class_capacity | home.php?route=class_capacity | pages/class_capacity.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| student_promotion | home.php?route=student_promotion | pages/student_promotion.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| new_applications | home.php?route=new_applications | pages/new_applications.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| admission_status | home.php?route=admission_status | pages/admission_status.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| discipline_cases | home.php?route=discipline_cases | pages/discipline_cases.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| conduct_reports | home.php?route=conduct_reports | pages/conduct_reports.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| academic_reports | home.php?route=academic_reports | pages/academic_reports.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| performance_analysis | home.php?route=performance_analysis | pages/performance_analysis.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| term_reports | home.php?route=term_reports | pages/term_reports.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| clubs_societies | home.php?route=clubs_societies | pages/clubs_societies.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| sports | home.php?route=sports | pages/sports.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| competitions | home.php?route=competitions | pages/competitions.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| school_events | home.php?route=school_events | pages/school_events.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| assemblies | home.php?route=assemblies | pages/assemblies.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| special_needs | home.php?route=special_needs | pages/special_needs.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| counseling_records | home.php?route=counseling_records | pages/counseling_records.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| permissions_exeats | home.php?route=permissions_exeats | pages/permissions_exeats.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| dormitory_management | home.php?route=dormitory_management | pages/dormitory_management.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| enrollment_trends | home.php?route=enrollment_trends | pages/enrollment_trends.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| performance_trends | home.php?route=performance_trends | pages/performance_trends.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| attendance_trends | home.php?route=attendance_trends | pages/attendance_trends.php | Attendance | (no role assignment) | DB route_permissions | 0 | Partially working |
| comparative_reports | home.php?route=comparative_reports | pages/comparative_reports.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| students_with_balance | home.php?route=students_with_balance | pages/students_with_balance.php | Students | (no role assignment) | DB route_permissions | 0 | Partially working |
| balances_by_class | home.php?route=balances_by_class | pages/balances_by_class.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| fee_defaulters | home.php?route=fee_defaulters | pages/fee_defaulters.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| deputy_head_academic_dashboard | home.php?route=deputy_head_academic_dashboard | components/dashboards/deputy_head_academic_dashboard.php | Academics | (no role assignment) | DB route_permissions | 0 | Partially working |
| deputy_head_discipline_dashboard | home.php?route=deputy_head_discipline_dashboard | components/dashboards/deputy_head_discipline_dashboard.php | System | (no role assignment) | DB route_permissions | 0 | Partially working |
| teacher_dashboard | home.php?route=teacher_dashboard | components/dashboards/teacher_dashboard.php | Staff/HR | (no role assignment) | DB route_permissions | 0 | Partially working |
| unmatched_payments | home.php?route=unmatched_payments | pages/unmatched_payments.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| vendors | home.php?route=vendors | pages/vendors.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| purchase_orders | home.php?route=purchase_orders | pages/purchase_orders.php | Inventory | (no role assignment) | DB route_permissions | 0 | Partially working |
| petty_cash | home.php?route=petty_cash | pages/petty_cash.php | General | (no role assignment) | DB route_permissions | 0 | Partially working |
| bank_accounts | home.php?route=bank_accounts | pages/bank_accounts.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| bank_transactions | home.php?route=bank_transactions | pages/bank_transactions.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |
| mpesa_settlements | home.php?route=mpesa_settlements | pages/mpesa_settlements.php | Finance | (no role assignment) | DB route_permissions | 0 | Partially working |

## B) Shared Pages Report

Role-aware UI contracts are derived from role access in shared_routes_analysis.txt and UI gating patterns in js/components/RoleBasedUI.js.

| Route | Roles | Data visibility | Actions | UI differences | Adaptation pattern |
| --- | --- | --- | --- | --- | --- |
| home | All active roles | Role-specific dashboard summaries only | Navigate modules, open dashboard widgets | Dashboard cards/charts vary by role | DashboardRouter + role-specific dashboards |
| me | All active roles | Own profile only | Update profile, change password | Admin roles see role/permission summary | Single profile page with conditional sections |
| manage_communications | 15 roles | Scope by role (class, department, or school-wide) | Compose/send/approve based on permissions | Extra channels and moderation for senior roles | Permission-driven toolbar + scoped queries |
| mark_attendance | 6 roles | Class teachers see own class; admins see all | Mark/edit attendance, export for admins | Class filter locked for teachers | Role-scoped query + conditional filters |
| staff_attendance | 6 roles | Own department for teachers; school-wide for admins | Mark/edit attendance, export | Summary cards for senior roles | Role-scoped query + conditional actions |
| boarding_roll_call | 5 roles | Boarding master full view; teachers limited | Mark roll call | Boarding-only filters for boarding staff | Role-scoped query + conditional actions |
| view_attendance | 5 roles | Read-only; class-scoped for teachers | Export for senior roles | Summary analytics for headteacher | Read-only mode + role-specific charts |
| view_results | 5 roles | Class/subject scoped for teachers | View/print/export for senior roles | Headteacher gets analytics panels | Scoped queries + permission-based buttons |
| manage_lesson_plans | 4 roles | Teachers see own plans; headteacher all | Create/edit for teachers; approve for headteacher | Approval queue for headteacher | Role-based tabs + approval workflow |
| manage_students | 4 roles | Full list, sensitive fields gated | Create/edit/delete based on permissions | Fee stats only for finance perms | Single list with permission-driven columns |
| manage_academics | 3 roles | School-wide academic setup | Create/update classes/streams | Extra setup panels for school admin | Conditional panels by role |
| manage_assessments | 3 roles | Teacher-scoped assessments | Create/edit vs approve | Approval controls for headteacher | Workflow buttons by permission |
| manage_classes | 3 roles | School-wide classes/streams | Create/update | Bulk actions for admin roles | Role-based bulk actions |
| manage_students_admissions | 3 roles | Admissions pipeline, sensitive data gated | Verify docs, interview, placement, enrollment | Workflow tabs/actions vary by role | Single workflow UI + permission gating |
| manage_timetable | 3 roles | School-wide timetables | Create/update/approve | Scheduling tools for admin roles | Role-based action set |
| myclasses | 3 roles | Assigned classes only | View class data | Class-specific KPIs for class teachers | Scoped queries per assignment |
| add_results | 2 roles | School-wide results aggregation | Approve/publish | Headteacher approval queue | Approval workflow tabs |
| finance_reports | 2 roles | Finance-wide reporting | Export/report generation | Director read-only view | Permission-based export tools |
| manage_activities | 2 roles | Activities by department | Create/approve activities | Talent Dev gets planning tools | Role-based workflow actions |
| manage_finance | 2 roles | Finance hub by role | Create requests vs approve | Director approval panels | Role-based cards/actions |
| manage_staff | 2 roles | Staff records, sensitive fields gated | Create/edit for school admin | Headteacher read-only analytics | Permission-gated edit controls |
| manage_subjects | 2 roles | School-wide subject catalog | Create/update | Approval panel for headteacher | Role-based actions |
| manage_users | 2 roles | User/account data (system vs school scope) | Full CRUD for system admin | School admin limited to school users | Domain-isolated views |
| submit_results | 2 roles | Own class/subject only | Submit results | Class vs subject scoped views | Scoped queries + submit-only actions |

## C) API Coverage Matrix

The matrix below enumerates confirmed frontend usage. For routes not listed, add entries once the page wiring is verified.

| Page/Route | Endpoints used | Query params | Response shape | Error shape | Notes |
| --- | --- | --- | --- | --- | --- |
| manage_students | GET /api/students/student; GET /api/academic/classes-list; GET /api/academic/streams-list; GET /api/finance/student-types-list; GET /api/students/parents/list; POST /api/students/student; PUT /api/students/student/{id}; DELETE /api/students/student/{id}; POST /api/students/bulk-create | page, limit, search, class_id, stream_id, status, gender, student_type_id | students list: {status,message,data:{students[],pagination{page,limit,total,total_pages}}} | {status:"error",message,code} | fee_status filter is client-only; export endpoint /api/students/export not found (TODO) |
| manage_students_admissions | GET /api/admission/queues; GET /api/admission/application/{id}; POST /api/admission/submit-application; POST /api/admission/verify-document; POST /api/admission/schedule-interview; POST /api/admission/record-interview-results; POST /api/admission/generate-placement-offer; POST /api/admission/record-fee-payment; POST /api/admission/complete-enrollment; GET /api/students/parents/list; GET /api/students/academic-year-all | application_id, parent_id, academic_year | queues: {status,message,data:{queues,summary,timestamp}}; application: {status,message,data:{application,documents,workflow_data,available_actions}} | {status:"error",message,code} | Document upload UI not wired (TODO) |
| import_existing_students | POST /api/students/bulk-create (multipart file) | update_existing, skip_header | {status,message,data:{processed,errors,warnings,duplicates}} | {status:"error",message,code} | Uses bulk-create with file upload |
| all_students | Role-based router to pages/students/* templates | TBD | TBD | TBD | TODO: confirm templates + API usage |

TODO: Add remaining routes once each page is wired to API endpoints and response shapes are confirmed.