<?php
namespace App\API\Controllers;

use App\API\Modules\students\StudentsAPI;
use App\API\Modules\system\SystemAPI;
use Exception;
use App\API\Services\DirectorAnalyticsService;
use App\API\Services\HeadteacherAnalyticsService;
use App\API\Services\SubjectTeacherAnalyticsService;
use App\API\Services\TeacherAnalyticsService;
use App\API\Services\SystemAdminAnalyticsService;

/**
 * DashboardController - Role-specific dashboard endpoints
 * 
 * Provides aggregated, RBAC-filtered data for each dashboard
 * Ensures strict data isolation - each role sees ONLY its domain
 * 
 * CRITICAL: Every method must enforce Principle of Least Privilege
 * - System Admin: Infrastructure only, NO business data
 * - Director: Executive overview, business data only
 * - Teachers: My class only, not other classes
 * - Finance: Finance data only
 */

class DashboardController extends BaseController
{


    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->success(['message' => 'Dashboard API is running']);
    }
    /**
     * GET /api/dashboard/director/announcements
     * Director-only: Latest published announcements/news for dashboard
     */
    public function getDirectorAnnouncements($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $announcements = $service->getLatestAnnouncements();
            return $this->success([
                'data' => $announcements
            ], 'Latest announcements retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch announcements: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/payroll-summary
     * Director-only: Monthly payroll summary
     */
    public function getDirectorPayrollSummary($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $total = $service->getMonthlyPayrollSummary();
            return $this->success([
                'total_payroll' => $total
            ], 'Payroll summary retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch payroll summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/system-status
     * Director-only: System health status
     */
    public function getDirectorSystemStatus($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $status = $service->getSystemHealthStatus();
            return $this->success([
                'status' => $status
            ], 'System status retrieved');
        } catch (Exception $e) {
            return $this->success([
                'status' => 'Unhealthy'
            ], 'System status retrieved');
        }
    }

    /**
     * GET /api/dashboard/director/summary
     * Director-only: Comprehensive executive summary KPIs
     */
    public function getDirectorSummary($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $kpis = $analytics->getSummaryKPIs();

            return $this->success([
                'kpis' => $kpis,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Director summary retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch Director summary: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/trends
     * CEO-only: Financial trends data
     */
    public function getPaymentsTrends($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $trends = $analytics->getFinancialTrends();

            return $this->success([
                'data' => $trends
            ], 'Financial trends retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch financial trends: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/revenue-sources
     * CEO-only: Revenue breakdown by source
     */
    public function getPaymentsRevenueSources($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $sources = $analytics->getRevenueSources();

            return $this->success([
                'data' => $sources
            ], 'Revenue sources retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch revenue sources: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/academics/kpis
     * CEO-only: Academic performance KPIs
     */
    public function getAcademicsKpis($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $kpis = $analytics->getAcademicKPIs();

            return $this->success([
                'kpis' => $kpis
            ], 'Academic KPIs retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch academic KPIs: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/academics/performance-matrix
     * CEO-only: Performance heatmap data
     */
    public function getAcademicsPerformanceMatrix($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $matrix = $analytics->getPerformanceMatrix();

            return $this->success([
                'data' => $matrix
            ], 'Performance matrix retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch performance matrix: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/attendance/trends
     * CEO-only: Attendance trends data
     */
    public function getAttendanceTrends($id = null, $data = [], $segments = [])
    {

        try {
            $analytics = new DirectorAnalyticsService();
            $trends = $analytics->getAttendanceTrends();

            // Ensure we return an array for the frontend
            if (!is_array($trends)) {
                return $this->serverError('Attendance trends not available');
            }

            return $this->success([
                'data' => $trends
            ], 'Attendance trends retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch attendance trends: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/fees-by-class-term
     * Returns fees collected and outstanding grouped by class and term (minimal implementation)
     */
    public function getFeesByClassTerm($id = null, $data = [], $segments = [])
    {
        try {
            $analytics = new DirectorAnalyticsService();
            $report = $analytics->getFeesByClassTerm();

            return $this->success([
                'data' => $report
            ], 'Fees by class × term retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch fees by class × term: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/academic-kpis-table
     * Returns academic KPIs as table rows
     */
    public function getAcademicKpisTable($id = null, $data = [], $segments = [])
    {
        try {
            $analytics = new DirectorAnalyticsService();
            $rows = $analytics->getAcademicKPIsTable();

            return $this->success([
                'data' => $rows
            ], 'Academic KPIs table retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch academic KPIs table: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/student-distribution
     * Returns students by class (male/female/total)
     */
    public function getStudentDistribution($id = null, $data = [], $segments = [])
    {
        try {
            $analytics = new DirectorAnalyticsService();
            $rows = $analytics->getStudentDistribution();
            return $this->success(['data' => $rows], 'Student distribution retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch student distribution: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/staff-deployment
     * Returns staff counts by department
     */
    public function getStaffDeployment($id = null, $data = [], $segments = [])
    {
        try {
            $analytics = new DirectorAnalyticsService();
            $rows = $analytics->getStaffDeployment();
            return $this->success(['data' => $rows], 'Staff deployment retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch staff deployment: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/risks
     * Director-only: Operational risks and audit data
     */
    public function getDirectorRisks($id = null, $data = [], $segments = [])
    {


        try {
            $analytics = new DirectorAnalyticsService();
            $risks = $analytics->getOperationalRisks();

            return $this->success([
                'data' => $risks
            ], 'Operational risks retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to fetch operational risks: ' . $e->getMessage());
        }
    }


    // ============= SYSTEM ADMIN ENDPOINTS (SYSTEM ONLY) =============

    /**
     * GET /api/dashboard/system-admin/auth-events
     * System-only: Authentication audit trail
     */
    public function getSystemAdminAuthEvents($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getAuthEvents();
            return $this->success($result, 'Auth events retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch auth events: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/system-admin/active-sessions
     * System-only: Currently logged-in users
     */
    public function getSystemAdminActiveSessions($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getActiveSessions();
            return $this->success($result, 'Active sessions retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch active sessions: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/system-admin/uptime
     * System-only: Infrastructure uptime percentage
     */
    public function getSystemAdminUptime($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getUptime();
            return $this->success(['data' => $result], 'System uptime retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch uptime: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/system-admin/health-errors
     * System-only: Critical and high-severity errors
     */
    public function getSystemAdminHealthErrors($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getHealthErrors();
            return $this->success(['data' => $result], 'Health errors retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch health errors: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/system-admin/health-warnings
     * System-only: System warnings
     */
    public function getSystemAdminHealthWarnings($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getHealthWarnings();
            return $this->success(['data' => $result], 'Health warnings retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch health warnings: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/system-admin/api-load
     * System-only: API request metrics
     */
    public function getSystemAdminAPILoad($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 2) {
            return $this->forbidden('System Admin access only');
        }
        try {
            $service = new SystemAdminAnalyticsService();
            $result = $service->getApiLoad();
            return $this->success($result, 'API load retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch API load: ' . $e->getMessage());
        }
    }

    // ============= DIRECTOR ENDPOINTS (BUSINESS OVERVIEW) =============

    /**
     * GET /api/dashboard/director/enrollment
     * Director-only: Student enrollment statistics
     */
    public function getDirectorEnrollment($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $enrollment = $service->getEnrollmentStats();
            return $this->success([
                'data' => $enrollment
            ], 'Enrollment stats retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch enrollment: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/staff
     * Director-only: Staff statistics
     */
    public function getDirectorStaff($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $staffStats = $service->getStaffStats();
            return $this->success([
                'data' => $staffStats
            ], 'Staff stats retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch staff stats: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/finance
     * Director-only: Financial overview (from PaymentsController)
     */
    public function getDirectorFinance($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $financeStats = $service->getFinanceStats();
            return $this->success([
                'data' => $financeStats
            ], 'Finance stats retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch finance stats: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/director/attendance
     * Director-only: Today's attendance summary
     */
    public function getDirectorAttendance($id = null, $data = [], $segments = [])
    {

        try {
            $service = new DirectorAnalyticsService();
            $attendanceStats = $service->getAttendanceStats();
            return $this->success([
                'data' => $attendanceStats
            ], 'Attendance stats retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch attendance stats: ' . $e->getMessage());
        }
    }

    // ============= HEADTEACHER ENDPOINTS (ROLE 5) =============

    /**
     * GET /api/dashboard/headteacher/overview
     * Headteacher-only: Overall school statistics
     */
    public function getHeadteacherOverview($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $overview = $service->getOverview();
            return $this->success([
                'data' => $overview
            ], 'Headteacher overview retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch overview: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/attendance-today
     * Headteacher-only: Today's school attendance
     */
    public function getHeadteacherAttendanceToday($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $attendance = $service->getAttendanceToday();
            return $this->success([
                'data' => $attendance
            ], 'Attendance data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch attendance: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/schedules
     * Headteacher-only: Today's class schedules
     */
    public function getHeadteacherSchedules($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $schedules = $service->getSchedules();
            return $this->success([
                'data' => $schedules
            ], 'Schedules retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch schedules: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/admissions
     * Headteacher-only: Pending student admissions
     */
    public function getHeadteacherAdmissions($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $admissions = $service->getAdmissionsStats();
            return $this->success([
                'data' => $admissions
            ], 'Admissions data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch admissions: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/discipline
     * Headteacher-only: Discipline cases
     */
    public function getHeadteacherDiscipline($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $discipline = $service->getDisciplineStats();
            return $this->success([
                'data' => $discipline
            ], 'Discipline data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch discipline: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/communications
     * Headteacher-only: Parent communications
     */
    public function getHeadteacherCommunications($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $communications = $service->getCommunicationsStats();
            return $this->success([
                'data' => $communications
            ], 'Communications data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch communications: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/assessments
     * Headteacher-only: Student assessments
     */
    public function getHeadteacherAssessments($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $assessments = $service->getAssessmentsStats();
            return $this->success([
                'data' => $assessments
            ], 'Assessments data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch assessments: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/performance
     * Headteacher-only: Overall academic performance
     */
    public function getHeadteacherPerformance($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $performance = $service->getPerformanceStats();
            return $this->success([
                'data' => $performance
            ], 'Performance data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch performance: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/pending-admissions
     * Headteacher-only: List of pending admission applications
     */
    public function getHeadteacherPendingAdmissions($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $pendingAdmissions = $service->getPendingAdmissions();
            return $this->success([
                'data' => $pendingAdmissions['data'],
                'total' => $pendingAdmissions['total']
            ], 'Pending admissions retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch pending admissions: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/headteacher/discipline-cases
     * Headteacher-only: List of open discipline cases
     */
    public function getHeadteacherDisciplineCases($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 5) {
            return $this->forbidden('Headteacher access only');
        }
        try {
            $service = new HeadteacherAnalyticsService();
            $disciplineCases = $service->getDisciplineCases();
            return $this->success([
                'data' => $disciplineCases['data'],
                'total' => $disciplineCases['total']
            ], 'Discipline cases retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch discipline cases: ' . $e->getMessage());
        }
    }


    // ============= SUBJECT TEACHER ENDPOINTS (ROLE 8) =============

    /**
     * GET /api/dashboard/subject-teacher/classes
     * Subject Teacher-only: Classes assigned
     */
    public function getSubjectTeacherClasses($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getClassesStats();
            return $this->success([
                'data' => $result
            ], 'Classes data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch classes: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/sections
     * Subject Teacher-only: Sections/streams taught
     */
    public function getSubjectTeacherSections($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getSectionsStats();
            return $this->success([
                'data' => $result
            ], 'Sections data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch sections: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/assessments-due
     * Subject Teacher-only: Pending assessments to mark
     */
    public function getSubjectTeacherAssessmentsDue($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getAssessmentsDueStats();
            return $this->success([
                'data' => $result
            ], 'Assessments due retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch assessments due: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/graded
     * Subject Teacher-only: Assessments graded this week
     */
    public function getSubjectTeacherGraded($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getGradedStats();
            return $this->success([
                'data' => $result
            ], 'Graded data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch graded data: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/exams
     * Subject Teacher-only: Upcoming exam schedule
     */
    public function getSubjectTeacherExams($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getExamsStats();
            return $this->success([
                'data' => $result
            ], 'Exams data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch exams: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/lesson-plans
     * Subject Teacher-only: Lesson plans created
     */
    public function getSubjectTeacherLessonPlans($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getLessonPlansStats();
            return $this->success([
                'data' => $result
            ], 'Lesson plans data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch lesson plans: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/pending-assessments
     * Subject Teacher-only: List of pending assessments to mark
     */
    public function getSubjectTeacherPendingAssessments($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getPendingAssessments();
            return $this->success([
                'data' => $result['data'],
                'total' => $result['total']
            ], 'Pending assessments retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch pending assessments: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/subject-teacher/exam-schedule
     * Subject Teacher-only: List of upcoming exams
     */
    public function getSubjectTeacherExamSchedule($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 8) {
            return $this->forbidden('Subject Teacher access only');
        }
        try {
            $service = new SubjectTeacherAnalyticsService($this->getUserId());
            $result = $service->getExamSchedule();
            return $this->success([
                'data' => $result['data'],
                'total' => $result['total']
            ], 'Exam schedule retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch exam schedule: ' . $e->getMessage());
        }
    }


    // ============= TEACHER ENDPOINTS (MY CLASS ONLY) =============

    /**
     * GET /api/dashboard/teacher/my-class
     * Teacher-only: My assigned class and students
     */
    public function getTeacherMyClass($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 7) {
            return $this->forbidden('Class Teacher access only');
        }
        try {
            $service = new TeacherAnalyticsService($this->getUserId());
            $result = $service->getMyClass();
            if (!$result) {
                return $this->notFound('No class assigned');
            }
            return $this->success([
                'data' => $result
            ], 'Class data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch class data: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/dashboard/teacher/my-attendance-today
     * Teacher-only: Today's attendance for my class
     */
    public function getTeacherAttendanceToday($id = null, $data = [], $segments = [])
    {
        if ($this->getUserRole() !== 7) {
            return $this->forbidden('Class Teacher access only');
        }
        try {
            $service = new TeacherAnalyticsService($this->getUserId());
            $result = $service->getMyAttendanceToday();
            return $this->success([
                'data' => $result
            ], 'Attendance data retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to fetch attendance: ' . $e->getMessage());
        }
    }

    // ============= HELPER METHODS =============

    /**
     * Get current authenticated user's role
     * Overrides parent method to use $this->user
     */
    protected function getUserRole()
    {
        // Handle both single role and role_ids array
        if (isset($this->user['role_ids']) && is_array($this->user['role_ids'])) {
            return $this->user['role_ids'][0] ?? null; // Return primary role
        }
        return $this->user['role'] ?? $this->user['role_id'] ?? null;
    }
}
