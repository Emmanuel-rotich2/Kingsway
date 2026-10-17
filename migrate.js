#!/usr/bin/env node

/**
 * Batch Migration Script for NodeController Migration
 * 
 * Usage: node migrate.js --page manage_students
 *        node migrate.js --module students
 *        node migrate.js --all
 */

const fs = require('fs');
const path = require('path');

const PAGES_DIR = '/home/prof_angera/Projects/php_pages/Kingsway/js/pages';
const PHP_PAGES_DIR = '/home/prof_angera/Projects/php_pages/Kingsway/pages';

const ENTITY_VIEW_MAP = {
    // Students
    'manage_students': { entity: 'students', view: 'manage' },
    'student_timeline': { entity: 'students', view: 'timeline' },
    'student_profile_context': { entity: 'students', view: 'profile' },
    'student_progress_reports': { entity: 'students', view: 'progress' },
    'student_portfolio': { entity: 'students', view: 'portfolio' },
    
    // Academic
    'manage_terms': { entity: 'academic', view: 'terms' },
    'manage_subjects': { entity: 'academic', view: 'subjects' },
    'class_streams': { entity: 'academic', view: 'streams' },
    'learning_areas': { entity: 'academic', view: 'learning_areas' },
    'academic_years': { entity: 'academic', view: 'years' },
    'timetable': { entity: 'academic', view: 'timetable' },
    'schemes_of_work': { entity: 'academic', view: 'schemes' },
    'lesson_plans_by_class': { entity: 'academic', view: 'lesson_plans' },
    'all_lesson_plans': { entity: 'academic', view: 'all_lesson_plans' },
    'approved_lesson_plans': { entity: 'academic', view: 'approved_lesson_plans' },
    'my_classes_taught': { entity: 'academic', view: 'my_classes' },
    'my_subject_cats': { entity: 'academic', view: 'my_cats' },
    'cbc_curriculum': { entity: 'academic', view: 'cbc' },
    'academic_reports': { entity: 'academic', view: 'reports' },
    'academic_planning_oversight': { entity: 'academic', view: 'planning' },
    'academic_content_reconciliation': { entity: 'academic', view: 'reconciliation' },
    'cbc_curriculum': { entity: 'academic', view: 'cbc' },
    
    // Finance
    'fee_structure': { entity: 'finance', view: 'fee_structure' },
    'payment_records': { entity: 'finance', view: 'payments' },
    'finance_approvals': { entity: 'finance', view: 'approvals' },
    'petty_cash': { entity: 'finance', view: 'petty_cash' },
    'bank_accounts': { entity: 'finance', view: 'bank_accounts' },
    'vendor_invoices': { entity: 'finance', view: 'vendor_invoices' },
    'supplier_payments': { entity: 'finance', view: 'supplier_payments' },
    'statutory_remittances': { entity: 'finance', view: 'remittances' },
    'balances_by_class': { entity: 'finance', view: 'balances' },
    'report_fee_arrears_by_class': { entity: 'finance', view: 'arrears' },
    'mpesa_reconciliation': { entity: 'finance', view: 'mpesa' },
    'kcb_disbursement_reconciliation': { entity: 'finance', view: 'kcb' },
    'payment_reconciliation_ai': { entity: 'finance', view: 'ai_reconciliation' },
    'student_fund_transfers': { entity: 'finance', view: 'transfers' },
    'budget_overview': { entity: 'finance', view: 'budget' },
    'manage_transport_fee_structure': { entity: 'finance', view: 'transport_fees' },
    'draft_fee_structure': { entity: 'finance', view: 'draft_fees' },
    'fee_structure_approvals': { entity: 'finance', view: 'fee_approvals' },
    
    // Discipline
    'discipline_cases': { entity: 'discipline', view: 'cases' },
    'sanctions': { entity: 'discipline', view: 'sanctions' },
    'discipline_reports': { entity: 'discipline', view: 'reports' },
    'weekly_discipline_report': { entity: 'discipline', view: 'weekly' },
    'truancy_cases': { entity: 'discipline', view: 'truancy' },
    'suspensions_expulsions': { entity: 'discipline', view: 'suspensions' },
    'conduct_reports': { entity: 'discipline', view: 'conduct' },
    'counseling_reports': { entity: 'discipline', view: 'counseling' },
    'ai_counseling_review': { entity: 'discipline', view: 'ai_counseling' },
    'reflection_journal': { entity: 'discipline', view: 'reflection' },
    
    // Attendance
    'class_mark_attendance': { entity: 'attendance', view: 'class_mark' },
    'staff_attendance': { entity: 'attendance', view: 'staff' },
    'my_attendance': { entity: 'attendance', view: 'my' },
    'attendance_trends': { entity: 'attendance', view: 'trends' },
    'class_attendance_history': { entity: 'attendance', view: 'history' },
    
    // Admissions
    'admissions_workspace': { entity: 'admissions', view: 'workspace' },
    'admissions_admission_decisions': { entity: 'admissions', view: 'decisions' },
    'admissions_academic_applications': { entity: 'admissions', view: 'applications' },
    'admissions_headteacher_applications': { entity: 'admissions', view: 'headteacher' },
    'admissions_class_placement': { entity: 'admissions', view: 'placement' },
    'admissions_pending_admission_approvals': { entity: 'admissions', view: 'pending' },
    'manage_students_admissions': { entity: 'admissions', view: 'manage' },
    'admission_interviews': { entity: 'admissions', view: 'interviews' },
    'enrollment_reports': { entity: 'admissions', view: 'enrollment' },
    'enrollment_trends': { entity: 'admissions', view: 'trends' },
    
    // HR/Staff
    'manage_teachers': { entity: 'hr', view: 'teachers' },
    'all_teachers': { entity: 'hr', view: 'all_teachers' },
    'all_staff': { entity: 'hr', view: 'all_staff' },
    'manage_roles': { entity: 'hr', view: 'roles' },
    'manage_users': { entity: 'hr', view: 'users' },
    'staff_onboarding': { entity: 'hr', view: 'onboarding' },
    'staff_production_ui': { entity: 'hr', view: 'production' },
    'staff_appointments': { entity: 'hr', view: 'appointments' },
    'teacher_workload': { entity: 'hr', view: 'workload' },
    'payslips': { entity: 'hr', view: 'payslips' },
    'salary_advances': { entity: 'hr', view: 'advances' },
    'active_sessions': { entity: 'hr', view: 'sessions' },
    
    // Inventory/Transport/Boarding/Health/Activities
    'products_catalog_management': { entity: 'inventory', view: 'catalog' },
    'internal_products_catalog': { entity: 'inventory', view: 'internal' },
    'internal_product_details': { entity: 'inventory', view: 'details' },
    'food_consumption': { entity: 'inventory', view: 'food' },
    'food_low_stock': { entity: 'inventory', view: 'low_stock' },
    'meal_statistics': { entity: 'inventory', view: 'meal_stats' },
    'menu_planning': { entity: 'inventory', view: 'menu' },
    'manage_menus': { entity: 'inventory', view: 'menus' },
    'manage_activities': { entity: 'activities', view: 'manage' },
    'activity_participants': { entity: 'activities', view: 'participants' },
    'activity_achievements': { entity: 'activities', view: 'achievements' },
    'activity_reports': { entity: 'activities', view: 'reports' },
    'competitions': { entity: 'activities', view: 'competitions' },
    'sports': { entity: 'activities', view: 'sports' },
    'assemblies': { entity: 'activities', view: 'assemblies' },
    'manage_transport_fee_structure': { entity: 'transport', view: 'fees' },
    'transport_operator': { entity: 'transport', view: 'operator' },
    'route_registry': { entity: 'transport', view: 'routes' },
    'boarding_reports': { entity: 'boarding', view: 'reports' },
    'boarding_students': { entity: 'boarding', view: 'students' },
    'boarding_occupancy_summary': { entity: 'boarding', view: 'occupancy' },
    'exeat_reports': { entity: 'boarding', view: 'exeat' },
    'exeat_history': { entity: 'boarding', view: 'exeat_history' },
    'health_reports': { entity: 'health', view: 'reports' },
    'medical_alerts': { entity: 'health', view: 'alerts' },
    'sick_bay': { entity: 'health', view: 'sick_bay' },
    'special_needs': { entity: 'health', view: 'special_needs' },
    'development_progress': { entity: 'health', view: 'progress' },
    'student_leadership': { entity: 'health', view: 'leadership' },
    
    // Communications/Announcements
    'manage_announcements': { entity: 'communications', view: 'announcements' },
    'manage_communications': { entity: 'communications', view: 'manage' },
    'manage_page_content': { entity: 'communications', view: 'page_content' },
    'manage_articles': { entity: 'communications', view: 'articles' },
    'announcements': { entity: 'communications', view: 'list' },
    
    // Library
    'library_issue_return': { entity: 'library', view: 'issue_return' },
    'library_overdue': { entity: 'library', view: 'overdue' },
    'past_papers': { entity: 'library', view: 'past_papers' },
    'view_past_papers': { entity: 'library', view: 'view_papers' },
    
    // Exams/Assessments
    'assessments_exams': { entity: 'exams', view: 'assessments' },
    'exam_setup': { entity: 'exams', view: 'setup' },
    'exam_timetable_drafts': { entity: 'exams', view: 'timetable' },
    'enter_exam_results': { entity: 'exams', view: 'results' },
    'add_results': { entity: 'exams', view: 'add_results' },
    'enter_results': { entity: 'exams', view: 'enter' },
    'subject_grade_entry': { entity: 'exams', view: 'grade_entry' },
    'grade_entry': { entity: 'exams', view: 'grade_entry' },
    'subject_exam_schedule': { entity: 'exams', view: 'schedule' },
    'class_results': { entity: 'exams', view: 'class_results' },
    'student_subject_performance': { entity: 'exams', view: 'performance' },
    'performance_analysis': { entity: 'exams', view: 'analysis' },
    'comparative_reports': { entity: 'exams', view: 'comparative' },
    'competencies_sheet': { entity: 'exams', view: 'competencies' },
    'term_reports': { entity: 'exams', view: 'term_reports' },
    'national_exams': { entity: 'exams', view: 'national' },
    
    // AI
    'ai_academic_reviews': { entity: 'ai', view: 'academic' },
    'ai_activities_review': { entity: 'ai', view: 'activities' },
    'ai_assessment_draft': { entity: 'ai', view: 'assessment' },
    'ai_attendance_summary': { entity: 'ai', view: 'attendance' },
    'ai_boarding_summary': { entity: 'ai', view: 'boarding' },
    'ai_catering_review': { entity: 'ai', view: 'catering' },
    'ai_counseling_review': { entity: 'ai', view: 'counseling' },
    'ai_facilities_review': { entity: 'ai', view: 'facilities' },
    'ai_health_welfare': { entity: 'ai', view: 'health' },
    'ai_inventory_review': { entity: 'ai', view: 'inventory' },
    'ai_kpi_brief': { entity: 'ai', view: 'kpi' },
    'ai_lesson_plan_draft': { entity: 'ai', view: 'lesson_plan' },
    'ai_scheme_draft': { entity: 'ai', view: 'scheme' },
    'ai_staff_hr': { entity: 'ai', view: 'staff' },
    'ai_transport_summary': { entity: 'ai', view: 'transport' },
    
    // Other
    'analytics_catalogue': { entity: 'analytics', view: 'catalogue' },
    'data_import': { entity: 'data', view: 'import' },
    'manage_requisitions': { entity: 'procurement', view: 'requisitions' },
    'manage_expenses': { entity: 'procurement', view: 'expenses' },
    'vendor_invoices': { entity: 'procurement', view: 'vendor_invoices' },
    'curriculum_cbc': { entity: 'curriculum', view: 'cbc' },
    'curriculum_proposals': { entity: 'curriculum', view: 'proposals' },
    'teaching_materials': { entity: 'teaching', view: 'materials' },
    'upload_teaching_resource': { entity: 'teaching', view: 'upload' },
    'observation_feedback': { entity: 'teaching', view: 'feedback' },
    'mentor_feedback': { entity: 'teaching', view: 'mentor' },
    'mentor_meetings': { entity: 'teaching', view: 'meetings' },
    'intern_assigned_subjects': { entity: 'teaching', view: 'intern_subjects' },
    'intern_assigned_classes': { entity: 'teaching', view: 'intern_classes' },
    'all_lesson_plans': { entity: 'teaching', view: 'lesson_plans' },
    'approved_lesson_plans': { entity: 'teaching', view: 'approved' },
    'year_calendar': { entity: 'calendar', view: 'year' },
    'year_rollover': { entity: 'calendar', view: 'rollover' },
    'manage_holidays': { entity: 'calendar', view: 'holidays' },
    'academic_calendar': { entity: 'calendar', view: 'academic' },
    'view_calendar': { entity: 'calendar', view: 'view' },
    'class_capacity': { entity: 'capacity', view: 'class' },
    'class_parent_contacts': { entity: 'parents', view: 'contacts' },
    'parents/children': { entity: 'parents', view: 'children' },
    'all_parents': { entity: 'parents', view: 'all' },
    'enrollment_confirmations': { entity: 'enrollment', view: 'confirmations' },
    'parent_refunds': { entity: 'finance', view: 'refunds' },
    'adjustments': { entity: 'finance', view: 'adjustments' },
    'my_cats': { entity: 'academic', view: 'my_cats' },
    'my_vehicle': { entity: 'transport', view: 'my_vehicle' },
    'system_settings': { entity: 'system', view: 'settings' },
    'module_enablement': { entity: 'system', view: 'modules' },
    'system_health': { entity: 'system', view: 'health' },
    'data_retention': { entity: 'system', view: 'retention' },
    'failed_login_attempts': { entity: 'security', view: 'login_attempts' },
    'reset_default_password': { entity: 'security', view: 'password' },
    'bootstrap_school_administrator': { entity: 'system', view: 'bootstrap' },
    'migrations': { entity: 'system', view: 'migrations' },
    'report_system_audit_summary': { entity: 'system', view: 'audit' },
    'alumni_management': { entity: 'alumni', view: 'management' },
    'chaplaincy_pastoral': { entity: 'chaplaincy', view: 'pastoral' },
    'reflection_journal': { entity: 'discipline', view: 'journal' },
    'sign_in': { entity: 'auth', view: 'signin' },
    'account_settings': { entity: 'auth', view: 'account' },
    'account_status': { entity: 'auth', view: 'status' },
    'me': { entity: 'auth', view: 'profile' },
};

function generateController(pageName) {
    const mapping = ENTITY_VIEW_MAP[pageName];
    if (!mapping) {
        console.error(`No entity/view mapping for ${pageName}`);
        return null;
    }
    
    const className = pageName.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('') + 'Controller';
    const varName = pageName.replace(/_([a-z])/g, (_, l) => l.toUpperCase()) + 'Controller';
    
    return `/**
 * ${className} - NodeController Architecture
 * Data loading: NodeController (instant view engine)
 * Mutations: Direct PHP API
 */

class ${className} extends BaseNodeController {
    constructor() {
        super({
            entity: '${mapping.entity}',
            view: '${mapping.view}',
            tableContainerId: '${pageName}TableContainer',
            summaryCardIds: {
                total: 'statTotal',
                active: 'statActive',
            },
            pagination: { page: 1, limit: 25, total: 0, pages: 0 }
        });
    }

    async init() {
        if (this.initialized) return;
        this.initialized = true;

        try {
            await window.AuthContext?.ready();
            if (window.AuthContext && !window.AuthContext.isAuthenticated()) {
                window.location.href = \`\${window.APP_BASE || ""}/index.php\`;
                return;
            }

            this.cacheDom();
            this.bindEvents();
            await this.loadMetadata();
            this.bindNodeControllerEvents();
        } catch (error) {
            console.error("Failed to initialize ${className}:", error);
            this.showError(error.message || "Failed to initialize.");
        }
    }

    cacheDom() {
        this.dom = {
            tableContainer: document.getElementById('${pageName}TableContainer'),
            tableBody: document.querySelector('#${pageName}Table tbody'),

            statTotal: document.getElementById('statTotal'),
            statActive: document.getElementById('statActive'),

            searchInput: document.getElementById('searchInput'),
            classFilter: document.getElementById('classFilter'),
            statusFilter: document.getElementById('statusFilter'),
            pageSize: document.getElementById('pageSize'),
            pagination: document.getElementById('pagination'),

            myModal: document.getElementById('myModal'),
            myForm: document.getElementById('myForm'),
        };
    }

    bindEvents() {
        this.dom.searchInput?.addEventListener('input', this.debounce(() => this.refresh(), 300));
        ['classFilter', 'statusFilter'].forEach(id => {
            this.dom[id]?.addEventListener('change', () => this.refresh());
        });
        this.dom.pageSize?.addEventListener('change', (e) => {
            this.pagination.limit = parseInt(e.target.value, 10) || 25;
            this.pagination.page = 1;
            this.refresh();
        });
        this.dom.pagination?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-page-delta]');
            if (btn && !btn.disabled) this.changePage(Number(btn.dataset.pageDelta));
        });
        this.dom.myForm?.addEventListener('submit', (e) => { e.preventDefault(); this.saveRecord(); });
    }

    async loadMetadata() {
        // Load dropdown data
    }

    onTableRendered(rows) {
        super.onTableRendered(rows);
        this.currentData = rows;
    }

    updateSummaryCards(summary) {
        super.updateSummaryCards(summary);
    }

    async saveRecord() {
        // POST/PUT to PHP API
        this.refresh();
    }

    async deleteRecord(id) {
        if (!confirm('Delete?')) return;
        await this.apiCall('/entity/' + id, 'DELETE');
        this.refresh();
    }
}

const ${varName} = new ${className}();
window.${varName} = ${varName};

document.addEventListener('DOMContentLoaded', () => ${varName}.init());
`;
}

function main() {
    const args = process.argv.slice(2);
    const pageName = args.find(a => a.startsWith('--page='))?.split('=')[1];
    const module = args.find(a => a.startsWith('--module='))?.split('=')[1];
    const all = args.includes('--all');

    if (pageName) {
        const code = generateController(pageName);
        if (code) {
            const outPath = path.join(PAGES_DIR, `${pageName}.js`);
            fs.writeFileSync(outPath, code);
            console.log(`Generated ${outPath}`);
        }
    } else if (module) {
        // Filter by module prefix
        const pages = Object.keys(ENTITY_VIEW_MAP).filter(p => 
            p.startsWith(module + '_') || p === module
        );
        pages.forEach(pageName => {
            const code = generateController(pageName);
            if (code) {
                const outPath = path.join(PAGES_DIR, `${pageName}.js`);
                fs.writeFileSync(outPath, code);
                console.log(`Generated ${outPath}`);
            }
        });
    } else if (all) {
        Object.keys(ENTITY_VIEW_MAP).forEach(pageName => {
            const code = generateController(pageName);
            if (code) {
                const outPath = path.join(PAGES_DIR, `${pageName}.js`);
                fs.writeFileSync(outPath, code);
                console.log(`Generated ${outPath}`);
            }
        });
    } else {
        console.log('Usage:');
        console.log('  node migrate.js --page=manage_students');
        console.log('  node migrate.js --module=students');
        console.log('  node migrate.js --all');
    }
}

main();