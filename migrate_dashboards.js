#!/usr/bin/env node

/**
 * Dashboard Migration Script
 * Generates BaseDashboardController-based controllers for all 23 dashboards
 */

const fs = require('fs');
const path = require('path');

const DASHBOARD_DIR = '/home/prof_angera/Projects/php_pages/Kingsway/js/dashboards';

const DASHBOARDS = [
    // Role dashboards
    {
        name: 'catering_manager_cook_lead_dashboard',
        entity: 'dashboard',
        view: 'catering_manager',
        displayName: 'Catering Manager / Cook Lead Dashboard',
        periodBarId: 'cateringManagerPeriodBar',
        periodBarLabelId: 'cateringManagerPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'mealsServed', path: 'cards.meals_served', format: 'number' },
            { id: 'foodCost', path: 'cards.food_cost', format: 'currency' },
            { id: 'wasteRate', path: 'cards.waste_rate', format: 'percent' },
            { id: 'inventoryLevel', path: 'cards.inventory_level', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'mealsTrendChart', label: 'Meals Served', type: 'line', path: 'charts.meals_trend' },
            { id: 'costBreakdownChart', label: 'Cost Breakdown', type: 'doughnut', path: 'charts.cost_breakdown' },
        ],
        tableDefinitions: [
            { bodyId: 'mealLogTableBody', path: 'tables.meal_log', columns: ['date', 'meal', 'served', 'waste'], emptyText: 'No meal logs' },
        ],
    },
    {
        name: 'class_teacher_dashboard',
        entity: 'dashboard',
        view: 'class_teacher',
        displayName: 'Class Teacher Dashboard',
        periodBarId: 'classTeacherPeriodBar',
        periodBarLabelId: 'classTeacherPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalStudents', path: 'cards.total_students', format: 'number' },
            { id: 'attendanceToday', path: 'cards.attendance_today', format: 'percent' },
            { id: 'pendingAssessments', path: 'cards.pending_assessments', format: 'number' },
            { id: 'homeworkAssigned', path: 'cards.homework_assigned', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'attendanceTrendChart', label: 'Attendance Trend', type: 'line', path: 'charts.attendance_trend' },
            { id: 'performanceChart', label: 'Performance', type: 'bar', path: 'charts.performance' },
        ],
        tableDefinitions: [
            { bodyId: 'studentListBody', path: 'tables.students', columns: ['name', 'attendance', 'performance'], emptyText: 'No students' },
        ],
    },
    {
        name: 'dashboard_base_controller',
        entity: 'dashboard',
        view: 'base',
        displayName: 'Base Dashboard',
        periodBarId: 'dashboardPeriodBar',
        periodBarLabelId: 'dashboardPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [],
        chartDefinitions: [],
        tableDefinitions: [],
    },
    {
        name: 'deputy_head_academic_dashboard',
        entity: 'dashboard',
        view: 'deputy_head_academic',
        displayName: 'Deputy Head Academic Dashboard',
        periodBarId: 'deputyHeadAcademicPeriodBar',
        periodBarLabelId: 'deputyHeadAcademicPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalStudents', path: 'cards.total_students', format: 'number' },
            { id: 'attendanceRate', path: 'cards.attendance_rate', format: 'percent' },
            { id: 'teacherWorkload', path: 'cards.teacher_workload', format: 'number' },
            { id: 'curriculumCoverage', path: 'cards.curriculum_coverage', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'attendanceTrendChart', label: 'Attendance Trend', type: 'line', path: 'charts.attendance_trend' },
            { id: 'performanceByClassChart', label: 'Performance by Class', type: 'bar', path: 'charts.performance_by_class' },
        ],
        tableDefinitions: [
            { bodyId: 'classPerformanceTableBody', path: 'tables.class_performance', columns: ['class', 'avgScore', 'topStudent'], emptyText: 'No data' },
        ],
    },
    {
        name: 'deputy_head_discipline_dashboard',
        entity: 'dashboard',
        view: 'deputy_head_discipline',
        displayName: 'Deputy Head Discipline Dashboard',
        periodBarId: 'deputyHeadDisciplinePeriodBar',
        periodBarLabelId: 'deputyHeadDisciplinePeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'openCases', path: 'cards.open_cases', format: 'number' },
            { id: 'resolvedThisMonth', path: 'cards.resolved_this_month', format: 'number' },
            { id: 'highSeverity', path: 'cards.high_severity', format: 'number' },
            { id: 'repeatOffenders', path: 'cards.repeat_offenders', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'casesByTypeChart', label: 'Cases by Type', type: 'doughnut', path: 'charts.cases_by_type' },
            { id: 'severityTrendChart', label: 'Severity Trend', type: 'line', path: 'charts.severity_trend' },
        ],
        tableDefinitions: [
            { bodyId: 'openCasesTableBody', path: 'tables.open_cases', columns: ['student', 'violation', 'severity', 'date'], emptyText: 'No open cases' },
        ],
    },
    {
        name: 'director_owner_dashboard',
        entity: 'dashboard',
        view: 'director_owner',
        displayName: 'Director / Owner Dashboard',
        periodBarId: 'directorOwnerPeriodBar',
        periodBarLabelId: 'directorOwnerPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalEnrollment', path: 'cards.total_enrollment', format: 'number' },
            { id: 'revenue', path: 'cards.revenue', format: 'currency' },
            { id: 'expenses', path: 'cards.expenses', format: 'currency' },
            { id: 'profitMargin', path: 'cards.profit_margin', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'revenueTrendChart', label: 'Revenue Trend', type: 'line', path: 'charts.revenue_trend' },
            { id: 'expenseBreakdownChart', label: 'Expense Breakdown', type: 'doughnut', path: 'charts.expense_breakdown' },
        ],
        tableDefinitions: [
            { bodyId: 'financialSummaryTableBody', path: 'tables.financial_summary', columns: ['month', 'revenue', 'expenses', 'profit'], emptyText: 'No financial data' },
        ],
    },
    {
        name: 'driver_dashboard',
        entity: 'dashboard',
        view: 'driver',
        displayName: 'Driver Dashboard',
        periodBarId: 'driverPeriodBar',
        periodBarLabelId: 'driverPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 60000,
        cards: [
            { id: 'assignedRoutes', path: 'cards.assigned_routes', format: 'number' },
            { id: 'studentsTransported', path: 'cards.students_transported', format: 'number' },
            { id: 'onTimeRate', path: 'cards.on_time_rate', format: 'percent' },
            { id: 'fuelConsumption', path: 'cards.fuel_consumption', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'routePerformanceChart', label: 'Route Performance', type: 'bar', path: 'charts.route_performance' },
        ],
        tableDefinitions: [
            { bodyId: 'routeScheduleBody', path: 'tables.route_schedule', columns: ['route', 'pickupTime', 'students', 'status'], emptyText: 'No scheduled routes' },
        ],
    },
    {
        name: 'headteacher_dashboard',
        entity: 'dashboard',
        view: 'headteacher',
        displayName: 'Headteacher Dashboard',
        periodBarId: 'headteacherDashboardPeriodBar',
        periodBarLabelId: 'headteacherDashboardPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalStudents', path: 'cards.total_students', format: 'number' },
            { id: 'attendanceToday', path: 'cards.attendance_today', format: 'percent' },
            { id: 'classSchedules', path: 'cards.class_schedules', format: 'number' },
            { id: 'pendingAdmissions', path: 'cards.pending_admissions', format: 'number' },
            { id: 'disciplineCases', path: 'cards.discipline_cases', format: 'number' },
            { id: 'parentCommunications', path: 'cards.parent_communications', format: 'number' },
            { id: 'studentAssessments', path: 'cards.student_assessments', format: 'number' },
            { id: 'classPerformance', path: 'cards.class_performance', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'attendanceChart', label: 'Attendance Trend', type: 'line', path: 'charts.attendance_trend' },
            { id: 'performanceChart', label: 'Class Performance', type: 'bar', path: 'charts.class_performance' },
        ],
        tableDefinitions: [
            { bodyId: 'admissionsTableBody', path: 'tables.pending_admissions', columns: ['student', 'class', 'submitted', 'status'], emptyText: 'No pending admissions' },
            { bodyId: 'disciplineTableBody', path: 'tables.discipline_cases', columns: ['student', 'class', 'violation', 'severity'], emptyText: 'No discipline cases' },
        ],
    },
    {
        name: 'hod_talent_development_dashboard',
        entity: 'dashboard',
        view: 'hod_talent',
        displayName: 'HOD Talent Development Dashboard',
        periodBarId: 'hodTalentPeriodBar',
        periodBarLabelId: 'hodTalentPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'departmentStrength', path: 'cards.department_strength', format: 'number' },
            { id: 'teacherWorkload', path: 'cards.teacher_workload', format: 'number' },
            { id: 'lessonPlanCompletion', path: 'cards.lesson_plan_completion', format: 'percent' },
            { id: 'studentEngagement', path: 'cards.student_engagement', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'workloadTrendChart', label: 'Workload Trend', type: 'line', path: 'charts.workload_trend' },
            { id: 'engagementByClassChart', label: 'Engagement by Class', type: 'bar', path: 'charts.engagement_by_class' },
        ],
        tableDefinitions: [
            { bodyId: 'teacherWorkloadTableBody', path: 'tables.teacher_workload', columns: ['teacher', 'subjects', 'classes', 'hours'], emptyText: 'No data' },
        ],
    },
    {
        name: 'intern_student_teacher_dashboard',
        entity: 'dashboard',
        view: 'intern_student_teacher',
        displayName: 'Intern / Student Teacher Dashboard',
        periodBarId: 'internPeriodBar',
        periodBarLabelId: 'internPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'assignedClasses', path: 'cards.assigned_classes', format: 'number' },
            { id: 'observationsDue', path: 'cards.observations_due', format: 'number' },
            { id: 'lessonPlansSubmitted', path: 'cards.lesson_plans_submitted', format: 'number' },
            { id: 'mentorFeedback', path: 'cards.mentor_feedback', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'observationScoresChart', label: 'Observation Scores', type: 'bar', path: 'charts.observation_scores' },
        ],
        tableDefinitions: [
            { bodyId: 'observationScheduleBody', path: 'tables.observation_schedule', columns: ['date', 'observer', 'class', 'status'], emptyText: 'No observations scheduled' },
        ],
    },
    {
        name: 'matron_housemother_dashboard',
        entity: 'dashboard',
        view: 'matron_housemother',
        displayName: 'Matron / Housemother Dashboard',
        periodBarId: 'matronPeriodBar',
        periodBarLabelId: 'matronPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'boardersCount', path: 'cards.boarders_count', format: 'number' },
            { id: 'attendanceRate', path: 'cards.attendance_rate', format: 'percent' },
            { id: 'healthAlerts', path: 'cards.health_alerts', format: 'number' },
            { id: 'maintenanceRequests', path: 'cards.maintenance_requests', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'occupancyTrendChart', label: 'Occupancy Trend', type: 'line', path: 'charts.occupancy_trend' },
        ],
        tableDefinitions: [
            { bodyId: 'boardersTableBody', path: 'tables.boarders', columns: ['student', 'dorm', 'bed', 'status'], emptyText: 'No boarders' },
        ],
    },
    {
        name: 'school_accountant_dashboard',
        entity: 'dashboard',
        view: 'school_accountant',
        displayName: 'School Accountant Dashboard',
        periodBarId: 'accountantPeriodBar',
        periodBarLabelId: 'accountantPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalRevenue', path: 'cards.total_revenue', format: 'currency' },
            { id: 'outstandingFees', path: 'cards.outstanding_fees', format: 'currency' },
            { id: 'collectionRate', path: 'cards.collection_rate', format: 'percent' },
            { id: 'pendingRefunds', path: 'cards.pending_refunds', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'revenueTrendChart', label: 'Revenue Trend', type: 'line', path: 'charts.revenue_trend' },
            { id: 'collectionByClassChart', label: 'Collection by Class', type: 'bar', path: 'charts.collection_by_class' },
        ],
        tableDefinitions: [
            { bodyId: 'feeArrearsTableBody', path: 'tables.fee_arrears', columns: ['student', 'class', 'amount', 'status'], emptyText: 'No fee arrears' },
        ],
    },
    {
        name: 'school_administrative_officer_dashboard',
        entity: 'dashboard',
        view: 'school_admin_officer',
        displayName: 'School Administrative Officer Dashboard',
        periodBarId: 'saoPeriodBar',
        periodBarLabelId: 'saoPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalStaff', path: 'cards.total_staff', format: 'number' },
            { id: 'pendingApprovals', path: 'cards.pending_approvals', format: 'number' },
            { id: 'activeLeaves', path: 'cards.active_leaves', format: 'number' },
            { id: 'complianceRate', path: 'cards.compliance_rate', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'staffTrendChart', label: 'Staff Trend', type: 'line', path: 'charts.staff_trend' },
            { id: 'approvalBreakdownChart', label: 'Approval Breakdown', type: 'doughnut', path: 'charts.approval_breakdown' },
        ],
        tableDefinitions: [
            { bodyId: 'pendingApprovalsTableBody', path: 'tables.pending_approvals', columns: ['type', 'requester', 'date', 'status'], emptyText: 'No pending approvals' },
        ],
    },
    {
        name: 'school_counselor_chaplain_dashboard',
        entity: 'dashboard',
        view: 'counselor_chaplain',
        displayName: 'School Counselor / Chaplain Dashboard',
        periodBarId: 'counselorPeriodBar',
        periodBarLabelId: 'counselorPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'counselingSessions', path: 'cards.counseling_sessions', format: 'number' },
            { id: 'studentsAtRisk', path: 'cards.students_at_risk', format: 'number' },
            { id: 'referralsMade', path: 'cards.referrals_made', format: 'number' },
            { id: 'pastoralVisits', path: 'cards.pastoral_visits', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'sessionsTrendChart', label: 'Sessions Trend', type: 'line', path: 'charts.sessions_trend' },
            { id: 'referralByTypeChart', label: 'Referrals by Type', type: 'doughnut', path: 'charts.referral_by_type' },
        ],
        tableDefinitions: [
            { bodyId: 'counselingCasesTableBody', path: 'tables.counseling_cases', columns: ['student', 'issue', 'priority', 'date'], emptyText: 'No cases' },
        ],
    },
    {
        name: 'store_manager_dashboard',
        entity: 'dashboard',
        view: 'store_manager',
        displayName: 'Store Manager Dashboard',
        periodBarId: 'storeManagerPeriodBar',
        periodBarLabelId: 'storeManagerPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'totalItems', path: 'cards.total_items', format: 'number' },
            { id: 'lowStock', path: 'cards.low_stock', format: 'number' },
            { id: 'totalValue', path: 'cards.total_value', format: 'currency' },
            { id: 'pendingOrders', path: 'cards.pending_orders', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'stockLevelChart', label: 'Stock Levels', type: 'bar', path: 'charts.stock_levels' },
            { id: 'valueByCategoryChart', label: 'Value by Category', type: 'doughnut', path: 'charts.value_by_category' },
        ],
        tableDefinitions: [
            { bodyId: 'lowStockTableBody', path: 'tables.low_stock', columns: ['item', 'category', 'qty', 'reorderLevel'], emptyText: 'No low stock items' },
        ],
    },
    {
        name: 'subject_teacher_dashboard',
        entity: 'dashboard',
        view: 'subject_teacher',
        displayName: 'Subject Teacher Dashboard',
        periodBarId: 'subjectTeacherPeriodBar',
        periodBarLabelId: 'subjectTeacherPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'assignedSubjects', path: 'cards.assigned_subjects', format: 'number' },
            { id: 'classesTaught', path: 'cards.classes_taught', format: 'number' },
            { id: 'assessmentsPending', path: 'cards.assessments_pending', format: 'number' },
            { id: 'averageScore', path: 'cards.average_score', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'subjectPerformanceChart', label: 'Subject Performance', type: 'bar', path: 'charts.subject_performance' },
            { id: 'assessmentTrendChart', label: 'Assessment Trend', type: 'line', path: 'charts.assessment_trend' },
        ],
        tableDefinitions: [
            { bodyId: 'classPerformanceTableBody', path: 'tables.class_performance', columns: ['class', 'avgScore', 'completed', 'pending'], emptyText: 'No data' },
        ],
    },
    {
        name: 'support_staff_dashboard',
        entity: 'dashboard',
        view: 'support_staff',
        displayName: 'Support Staff Dashboard',
        periodBarId: 'supportStaffPeriodBar',
        periodBarLabelId: 'supportStaffPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'assignedTasks', path: 'cards.assigned_tasks', format: 'number' },
            { id: 'completedTasks', path: 'cards.completed_tasks', format: 'number' },
            { id: 'overdueTasks', path: 'cards.overdue_tasks', format: 'number' },
            { id: 'efficiency', path: 'cards.efficiency', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'taskCompletionChart', label: 'Task Completion', type: 'bar', path: 'charts.task_completion' },
        ],
        tableDefinitions: [
            { bodyId: 'taskListBody', path: 'tables.tasks', columns: ['task', 'assigned', 'due', 'status'], emptyText: 'No tasks' },
        ],
    },
    {
        name: 'system_administrator_dashboard',
        entity: 'dashboard',
        view: 'system_administrator',
        displayName: 'System Administrator Dashboard',
        periodBarId: 'sysAdminPeriodBar',
        periodBarLabelId: 'sysAdminPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 60000,
        cards: [
            { id: 'systemHealth', path: 'cards.system_health', format: 'percent' },
            { id: 'activeUsers', path: 'cards.active_users', format: 'number' },
            { id: 'apiLatency', path: 'cards.api_latency', format: 'number' },
            { id: 'errorRate', path: 'cards.error_rate', format: 'percent' },
        ],
        chartDefinitions: [
            { id: 'healthTrendChart', label: 'Health Trend', type: 'line', path: 'charts.health_trend' },
            { id: 'latencyDistributionChart', label: 'Latency Distribution', type: 'bar', path: 'charts.latency_distribution' },
        ],
        tableDefinitions: [
            { bodyId: 'errorLogTableBody', path: 'tables.error_log', columns: ['time', 'endpoint', 'error', 'severity'], emptyText: 'No errors' },
        ],
    },
    {
        name: 'teacher_dashboard_ui',
        entity: 'dashboard',
        view: 'teacher',
        displayName: 'Teacher Dashboard',
        periodBarId: 'teacherPeriodBar',
        periodBarLabelId: 'teacherPeriodBarLabel',
        refreshButtonId: 'refreshDashboard',
        refreshInterval: 300000,
        cards: [
            { id: 'myClasses', path: 'cards.my_classes', format: 'number' },
            { id: 'attendanceToday', path: 'cards.attendance_today', format: 'percent' },
            { id: 'lessonsPrepared', path: 'cards.lessons_prepared', format: 'number' },
            { id: 'assignmentsDue', path: 'cards.assignments_due', format: 'number' },
        ],
        chartDefinitions: [
            { id: 'myClassesAttendanceChart', label: 'My Classes Attendance', type: 'bar', path: 'charts.my_classes_attendance' },
            { id: 'lessonProgressChart', label: 'Lesson Progress', type: 'line', path: 'charts.lesson_progress' },
        ],
        tableDefinitions: [
            { bodyId: 'myClassesTableBody', path: 'tables.my_classes', columns: ['class', 'subject', 'students', 'attendance'], emptyText: 'No classes' },
        ],
    },
];

function generateDashboardController(dashboard) {
    const className = dashboard.name.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('') + 'Controller';
    const varName = dashboard.name.replace(/_([a-z])/g, (_, l) => l.toUpperCase()) + 'Controller';
    
    const cardDefs = dashboard.cards.map(c => `            {
                id: '${c.id}',
                path: '${c.path}',
                format: '${c.format}'
            }`).join(',\n');
    
    const chartDefs = dashboard.chartDefinitions.map(c => `            {
                id: '${c.id}',
                label: '${c.label}',
                type: '${c.type}',
                path: '${c.path}',
                emptyText: '${c.emptyText || 'No chart data for this period.'}'
            }`).join(',\n');
    
    const tableDefs = dashboard.tableDefinitions.map(t => `            {
                bodyId: '${t.bodyId}',
                path: '${t.path}',
                columns: [${t.columns.map(c => `'${c}'`).join(', ')}],
                emptyText: '${t.emptyText || 'No records found.'}'
            }`).join(',\n');

    return `/**
 * ${dashboard.displayName} - NodeController Architecture
 * Data loading: NodeController (instant view engine)
 * Mutations: Direct PHP API
 * Charts: Chart.js
 * Tables: NodeController composite tables
 */

class ${className} extends BaseDashboardController {
    constructor() {
        super({
            entity: '${dashboard.entity}',
            view: '${dashboard.view}',
            tableContainerId: '${dashboard.name}TableContainer',
            summaryCardIds: ${JSON.stringify(dashboard.cards.reduce((acc, c) => { acc[c.id] = c.id; return acc; }, {}), null, 2)},
            refreshInterval: ${dashboard.refreshInterval},
            periodBarId: '${dashboard.periodBarId}',
            periodBarLabelId: '${dashboard.periodBarLabelId}',
            refreshButtonId: '${dashboard.refreshButtonId}',
            defaultPeriod: 'today',
            cardDefinitions: [
${cardDefs}
            ],
            chartDefinitions: [
${chartDefs}
            ],
            tableDefinitions: [
${tableDefs}
            ],
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
            this.setupPeriodListeners();
            this.startLiveSync();
        } catch (error) {
            console.error("Failed to initialize ${className}:", error);
            this.showError(error.message || "Failed to initialize dashboard.");
        }
    }

    cacheDom() {
        super.cacheDom();
        
        // Dashboard-specific DOM elements will be bound by BaseDashboardController
        this.dom = {
            ...this.dom,
            periodBar: document.getElementById('${dashboard.periodBarId}'),
            periodBarLabel: document.getElementById('${dashboard.periodBarLabelId}'),
            refreshBtn: document.getElementById('${dashboard.refreshButtonId}'),
            errorState: document.getElementById('dashboardError'),
            loadingState: document.getElementById('dashboardLoading'),
            lastRefreshTime: document.getElementById('lastRefreshTime'),
        };
    }

    async loadMetadata() {
        // Load any metadata needed (dropdowns, etc.)
    }

    // Chart and table definitions are provided in constructor
    // BaseDashboardController handles rendering
}

const ${varName} = new ${className}();
window.${varName} = ${varName};

document.addEventListener('DOMContentLoaded', () => ${varName}.init());
`;
}

function main() {
    const args = process.argv.slice(2);
    const name = args.find(a => a.startsWith('--name='))?.split('=')[1];
    const all = args.includes('--all');

    if (name) {
        const dashboard = DASHBOARDS.find(d => d.name === name);
        if (!dashboard) {
            console.error(`Dashboard '${name}' not found`);
            process.exit(1);
        }
        const code = generateDashboardController(dashboard);
        const outPath = path.join(DASHBOARD_DIR, `${name}.js`);
        fs.writeFileSync(outPath, code);
        console.log(`Generated ${outPath}`);
    } else if (all) {
        DASHBOARDS.forEach(dashboard => {
            const code = generateDashboardController(dashboard);
            const outPath = path.join(DASHBOARD_DIR, `${dashboard.name}.js`);
            fs.writeFileSync(outPath, code);
            console.log(`Generated ${outPath}`);
        });
    } else {
        console.log('Usage:');
        console.log('  node migrate_dashboards.js --name=headteacher_dashboard');
        console.log('  node migrate_dashboards.js --all');
    }
}

main();