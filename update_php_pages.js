#!/usr/bin/env node

/**
 * Batch update PHP pages with NodeController attributes
 */

const fs = require('fs');
const path = require('path');
const PHP_PAGES_DIR = '/home/prof_angera/Projects/php_pages/Kingsway/pages';

function updatePhpPage(pageName, entityView) {
    // Search in pages/ and all subdirectories
    const searchDirs = [PHP_PAGES_DIR];
    const dirs = fs.readdirSync(PHP_PAGES_DIR, { withFileTypes: true })
        .filter(d => d.isDirectory())
        .map(d => path.join(PHP_PAGES_DIR, d.name));
    searchDirs.push(...dirs);
    
    for (const dir of searchDirs) {
        const phpPath = path.join(dir, `${pageName}.php`);
        if (fs.existsSync(phpPath)) {
            return updatePhpPageAtPath(phpPath, entityView, pageName);
        }
    }
    return false;
}

function updatePhpPageAtPath(phpPath, entityView, pageName) {
    let content = fs.readFileSync(phpPath, 'utf8');
    let modified = false;
    
    // 1. Add data-node-view to table container
    // Find table-responsive div that contains a table with tbody
    const tableContainerRegex = /<div class="table-responsive"([^>]*)>\s*<table[^>]*id="([^"]+)"[^>]*>/g;
    content = content.replace(tableContainerRegex, (match, attrs, tableId) => {
        if (attrs.includes('data-node-view')) return match;
        modified = true;
        const tbodyId = tableId.replace('Table', 'TableBody') || `${tableId}Body`;
        return `<div class="table-responsive"${attrs} id="${pageName}TableContainer" data-node-view="${entityView}" data-target="#${tbodyId}" data-filters='{}'>\n<table${attrs.includes('id=') ? '' : ` id="${tableId}"`}>`;
    });
    
    // 2. Add data-filter to filter inputs
    // Search inputs
    content = content.replace(/<input([^>]*)(id="searchInput"|id="searchStudents")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<input${before}${id}${after} data-filter="search">`;
    });
    
    // Class filter
    content = content.replace(/<select([^>]*)(id="classFilter"|id="filterClass")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="class_id">`;
    });
    
    // Stream filter
    content = content.replace(/<select([^>]*)(id="streamFilter"|id="filterStream")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="stream_id">`;
    });
    
    // Gender filter
    content = content.replace(/<select([^>]*)(id="genderFilter"|id="filterGender")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="gender">`;
    });
    
    // Status filter
    content = content.replace(/<select([^>]*)(id="statusFilter"|id="filterStudentStatus"|id="filterCardStatus"|id="filterSelect")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="status">`;
    });
    
    // Year filter
    content = content.replace(/<select([^>]*)(id="yearFilter"|id="filterAcademicYear")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="year">`;
    });
    
    // Date filter
    content = content.replace(/<input([^>]*)(id="dateFilter")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<input${before}${id}${after} data-filter="date">`;
    });
    
    // Page size
    content = content.replace(/<select([^>]*)(id="studentsPageSize"|id="idCardPageSize"|id="pageSize")([^>]*)>/g, (match, before, id, after) => {
        if (match.includes('data-filter')) return match;
        modified = true;
        return `<select${before}${id}${after} data-filter="limit">`;
    });
    
    // 3. Add data-page-delta to pagination buttons
    content = content.replace(/<button([^>]*onclick="[^"]*loadStudents\(([^)]+)\)[^"]*")([^>]*)>/g, (match, before, pageDelta, after) => {
        if (match.includes('data-page-delta')) return match;
        modified = true;
        return `<button${before} data-page-delta="${pageDelta}"${after}>`;
    });
    
    content = content.replace(/<button([^>]*onclick="[^"]*changePage\(([^)]+)\)[^"]*")([^>]*)>/g, (match, before, pageDelta, after) => {
        if (match.includes('data-page-delta')) return match;
        modified = true;
        return `<button${before} data-page-delta="${pageDelta}"${after}>`;
    });
    
    // 4. Remove inline onclick handlers that are now handled by NodeController
    // onchange on filters
    content = content.replace(/onchange="[^"]*filterBy[A-Z][^"]*"/g, '');
    content = content.replace(/onchange="[^"]*loadStudents\([^"]*\)"/g, '');
    content = content.replace(/onchange="[^"]*changePageSize\([^"]*\)"/g, '');
    content = content.replace(/onchange="[^"]*refresh\(\)"/g, '');
    content = content.replace(/onkeyup="[^"]*searchStudents\([^"]*\)"/g, '');
    content = content.replace(/oninput="[^"]*filterData\(\)"/g, '');
    
    if (modified) {
        fs.writeFileSync(phpPath, content);
        console.log(`Updated: ${phpPath}`);
        return true;
    }
    return false;
}

// Main
const ENTITY_VIEW_MAP = {
    'manage_students': 'students/manage',
    'student_timeline': 'students/timeline',
    'student_profile_context': 'students/profile',
    'student_progress_reports': 'students/progress',
    'student_portfolio': 'students/portfolio',
    'manage_terms': 'academic/terms',
    'manage_subjects': 'academic/subjects',
    'class_streams': 'academic/streams',
    'learning_areas': 'academic/learning_areas',
    'academic_years': 'academic/years',
    'timetable': 'academic/timetable',
    'schemes_of_work': 'academic/schemes',
    'lesson_plans_by_class': 'academic/lesson_plans',
    'all_lesson_plans': 'academic/all_lesson_plans',
    'approved_lesson_plans': 'academic/approved_lesson_plans',
    'my_classes_taught': 'academic/my_classes',
    'my_subject_cats': 'academic/my_cats',
    'cbc_curriculum': 'academic/cbc',
    'academic_reports': 'academic/reports',
    'academic_planning_oversight': 'academic/planning',
    'academic_content_reconciliation': 'academic/reconciliation',
    'fee_structure': 'finance/fee_structure',
    'payment_records': 'finance/payments',
    'finance_approvals': 'finance/approvals',
    'petty_cash': 'finance/petty_cash',
    'bank_accounts': 'finance/bank_accounts',
    'vendor_invoices': 'finance/vendor_invoices',
    'supplier_payments': 'finance/supplier_payments',
    'statutory_remittances': 'finance/remittances',
    'balances_by_class': 'finance/balances',
    'report_fee_arrears_by_class': 'finance/arrears',
    'mpesa_reconciliation': 'finance/mpesa',
    'kcb_disbursement_reconciliation': 'finance/kcb',
    'payment_reconciliation_ai': 'finance/ai_reconciliation',
    'student_fund_transfers': 'finance/transfers',
    'budget_overview': 'finance/budget',
    'manage_transport_fee_structure': 'finance/transport_fees',
    'draft_fee_structure': 'finance/draft_fees',
    'fee_structure_approvals': 'finance/fee_approvals',
    'discipline_cases': 'discipline/cases',
    'sanctions': 'discipline/sanctions',
    'discipline_reports': 'discipline/reports',
    'weekly_discipline_report': 'discipline/weekly',
    'truancy_cases': 'discipline/truancy',
    'suspensions_expulsions': 'discipline/suspensions',
    'conduct_reports': 'discipline/conduct',
    'counseling_reports': 'discipline/counseling',
    'ai_counseling_review': 'discipline/ai_counseling',
    'reflection_journal': 'discipline/journal',
    'class_mark_attendance': 'attendance/class_mark',
    'staff_attendance': 'attendance/staff',
    'my_attendance': 'attendance/my',
    'attendance_trends': 'attendance/trends',
    'class_attendance_history': 'attendance/history',
    'admissions_workspace': 'admissions/workspace',
    'admissions_admission_decisions': 'admissions/decisions',
    'admissions_academic_applications': 'admissions/applications',
    'admissions_headteacher_applications': 'admissions/headteacher',
    'admissions_class_placement': 'admissions/placement',
    'admissions_pending_admission_approvals': 'admissions/pending',
    'manage_students_admissions': 'admissions/manage',
    'admission_interviews': 'admissions/interviews',
    'enrollment_reports': 'admissions/enrollment',
    'enrollment_trends': 'admissions/trends',
    'manage_teachers': 'hr/teachers',
    'all_teachers': 'hr/all_teachers',
    'all_staff': 'hr/all_staff',
    'manage_roles': 'hr/roles',
    'manage_users': 'hr/users',
    'staff_onboarding': 'hr/onboarding',
    'staff_production_ui': 'hr/production',
    'staff_appointments': 'hr/appointments',
    'teacher_workload': 'hr/workload',
    'payslips': 'hr/payslips',
    'salary_advances': 'hr/advances',
    'active_sessions': 'hr/sessions',
    'products_catalog_management': 'inventory/catalog',
    'internal_products_catalog': 'inventory/internal',
    'internal_product_details': 'inventory/details',
    'food_consumption': 'inventory/food',
    'food_low_stock': 'inventory/low_stock',
    'meal_statistics': 'inventory/meal_stats',
    'menu_planning': 'inventory/menu',
    'manage_menus': 'inventory/menus',
    'manage_activities': 'activities/manage',
    'activity_participants': 'activities/participants',
    'activity_achievements': 'activities/achievements',
    'activity_reports': 'activities/reports',
    'competitions': 'activities/competitions',
    'sports': 'activities/sports',
    'assemblies': 'activities/assemblies',
    'transport_operator': 'transport/operator',
    'route_registry': 'transport/routes',
    'boarding_reports': 'boarding/reports',
    'boarding_students': 'boarding/students',
    'boarding_occupancy_summary': 'boarding/occupancy',
    'exeat_reports': 'boarding/exeat',
    'exeat_history': 'boarding/exeat_history',
    'health_reports': 'health/reports',
    'medical_alerts': 'health/alerts',
    'sick_bay': 'health/sick_bay',
    'special_needs': 'health/special_needs',
    'development_progress': 'health/progress',
    'student_leadership': 'health/leadership',
    'manage_announcements': 'communications/announcements',
    'manage_communications': 'communications/manage',
    'manage_page_content': 'communications/page_content',
    'manage_articles': 'communications/articles',
    'announcements': 'communications/list',
    'library_issue_return': 'library/issue_return',
    'library_overdue': 'library/overdue',
    'past_papers': 'library/past_papers',
    'view_past_papers': 'library/view_papers',
    'assessments_exams': 'exams/assessments',
    'exam_setup': 'exams/setup',
    'exam_timetable_drafts': 'exams/timetable',
    'enter_exam_results': 'exams/results',
    'add_results': 'exams/add_results',
    'enter_results': 'exams/enter',
    'subject_grade_entry': 'exams/grade_entry',
    'grade_entry': 'exams/grade_entry',
    'subject_exam_schedule': 'exams/schedule',
    'class_results': 'exams/class_results',
    'student_subject_performance': 'exams/performance',
    'performance_analysis': 'exams/analysis',
    'comparative_reports': 'exams/comparative',
    'competencies_sheet': 'exams/competencies',
    'term_reports': 'exams/term_reports',
    'national_exams': 'exams/national',
    'ai_academic_reviews': 'ai/academic',
    'ai_activities_review': 'ai/activities',
    'ai_assessment_draft': 'ai/assessment',
    'ai_attendance_summary': 'ai/attendance',
    'ai_boarding_summary': 'ai/boarding',
    'ai_catering_review': 'ai/catering',
    'ai_facilities_review': 'ai/facilities',
    'ai_health_welfare': 'ai/health',
    'ai_inventory_review': 'ai/inventory',
    'ai_kpi_brief': 'ai/kpi',
    'ai_lesson_plan_draft': 'ai/lesson_plan',
    'ai_scheme_draft': 'ai/scheme',
    'ai_staff_hr': 'ai/staff',
    'ai_transport_summary': 'ai/transport',
    'analytics_catalogue': 'analytics/catalogue',
    'data_import': 'data/import',
    'manage_requisitions': 'procurement/requisitions',
    'manage_expenses': 'procurement/expenses',
    'curriculum_cbc': 'curriculum/cbc',
    'curriculum_proposals': 'curriculum/proposals',
    'teaching_materials': 'teaching/materials',
    'upload_teaching_resource': 'teaching/upload',
    'observation_feedback': 'teaching/feedback',
    'mentor_feedback': 'teaching/mentor',
    'mentor_meetings': 'teaching/meetings',
    'intern_assigned_subjects': 'teaching/intern_subjects',
    'intern_assigned_classes': 'teaching/intern_classes',
    'year_calendar': 'calendar/year',
    'year_rollover': 'calendar/rollover',
    'manage_holidays': 'calendar/holidays',
    'academic_calendar': 'calendar/academic',
    'view_calendar': 'calendar/view',
    'class_capacity': 'capacity/class',
    'class_parent_contacts': 'parents/contacts',
    'enrollment_confirmations': 'enrollment/confirmations',
    'parent_refunds': 'finance/refunds',
    'adjustments': 'finance/adjustments',
    'my_cats': 'academic/my_cats',
    'my_vehicle': 'transport/my_vehicle',
    'system_settings': 'system/settings',
    'module_enablement': 'system/modules',
    'system_health': 'system/health',
    'data_retention': 'system/retention',
    'failed_login_attempts': 'security/login_attempts',
    'reset_default_password': 'security/password',
    'bootstrap_school_administrator': 'system/bootstrap',
    'migrations': 'system/migrations',
    'report_system_audit_summary': 'system/audit',
    'alumni_management': 'alumni/management',
    'chaplaincy_pastoral': 'chaplaincy/pastoral',
    'reflection_journal': 'discipline/journal',
    'sign_in': 'auth/signin',
    'account_settings': 'auth/account',
    'account_status': 'auth/status',
    'me': 'auth/profile',
};

let updated = 0;
Object.entries(ENTITY_VIEW_MAP).forEach(([pageName, entityView]) => {
    if (updatePhpPage(pageName, entityView)) updated++;
});

console.log(`\nTotal pages updated: ${updated}`);