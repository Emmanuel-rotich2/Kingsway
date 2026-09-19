<?php

namespace App\API\Controllers;

use App\API\Modules\attendance\AttendanceAPI;
use App\API\Modules\attendance\AttendanceManager;
use App\API\Modules\attendance\AttendanceStudentService;
use App\API\Modules\attendance\AttendanceStaffService;
use App\API\Modules\attendance\AttendancePermissionService;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;
use RuntimeException;
use Exception;

class AttendanceController extends BaseController
{
    private $api;
    private $staffAccess;
    private AttendanceStudentService $studentAttendanceService;
    private AttendanceStaffService $staffAttendanceService;
    private AttendancePermissionService $permissionService;
    private AttendanceManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\attendance\AttendanceAPI');
        $this->staffAccess = $this->contract('App\API\Services\StaffDomainAccessService', $this->user);
        $this->studentAttendanceService = $this->contract('App\API\Modules\attendance\AttendanceStudentService', $this->api);
        $this->staffAttendanceService = $this->contract('App\API\Modules\attendance\AttendanceStaffService', $this->api);
        $this->permissionService = $this->contract('App\API\Modules\attendance\AttendancePermissionService', $this->api);
        $this->manager = $this->contract('App\API\Modules\attendance\AttendanceManager');
    }
    public function guardStaffAttendance(string $permission, array $roles = [])
    {
        try {
            $this->staffAccess->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->serverError('An internal error occurred.'); }
    }

    public function index()
    {
        return $this->success(['message' => 'Attendance API is running']);
    }

    /**
     * GET /api/attendance/today - Get today's attendance statistics for dashboard
     * Returns: present count, absent count, total, percentage
     */
    public function getToday($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getToday($data));
    }

    /**
     * GET /api/attendance/today-attendance - Get today's student attendance percentage for dashboard
     */
    public function getTodayAttendance($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getTodayAttendance($data));
    }

    public function getStudentHistory($studentId = null, $data = [], $segments = [])
    {
        return $this->studentAttendanceService->getStudentHistory($studentId, $data, $segments, $this);
    }

    public function getStudentSummary($studentId = null, $data = [], $segments = [])
    {
        return $this->studentAttendanceService->getStudentSummary($studentId, $data, $segments, $this);
    }

    public function getClassAttendance($classId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getClassAttendance($classId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStudentPercentage($studentId = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getStudentPercentage($studentId, $data));
    }

    /**
     * GET /api/attendance/trends - Return attendance trends for last 30 days
     * Returns: data (30-day trends), absent_students, absent_staff, summary
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        try {
            $service = $this->contract('App\API\Services\DirectorAnalyticsService');
            $trends = $service->getAttendanceTrends();
            if (!is_array($trends)) {
                return $this->serverError('Attendance trends not available');
            }
            // Return the full structured response (data, absent_students, absent_staff, summary)
            return $this->success($trends, 'Attendance trends retrieved');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    public function getChronicStudentAbsentees($classId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStudentAbsentees($classId, $termId, $yearId, $threshold);
        return $this->handleResponse($result);
    }


    public function getStaffHistory($staffId = null, $data = [], $segments = [])
    {
        return $this->staffAttendanceService->getStaffHistory($staffId, $data, $segments, $this);
    }

    public function getStaffSummary($staffId = null, $data = [], $segments = [])
    {
        if (!$this->staffAccess->authenticated()) return $this->unauthorized('Authentication required');
        $requested = (int)($staffId ?? $data['staff_id'] ?? $_GET['staff_id'] ?? 0);
        if (!$requested) $requested = (int)($this->staffAccess->staffId() ?? 0);
        try { $staffId = $this->staffAccess->requireSelfOr('staff.attendance.view', $requested, ['system administrator','school administrator','headteacher','director']); }
        catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }

        $staffId = $staffId ?? ($data['staffId'] ?? null);
        $scope = $this->getAccessibleStaffScope();
        if (!$this->isStaffInScope($staffId ? (int) $staffId : null, $scope)) {
            return $this->forbidden('You are not allowed to access this staff attendance summary');
        }
        $result = $this->api->getStaffAttendanceSummary($staffId);
        return $this->handleResponse($result);
    }

    public function getDepartmentAttendance($departmentId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getDepartmentAttendance($departmentId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getStaffPercentage($staffId = null, $data = [], $segments = [])
    {
        if (!$this->staffAccess->authenticated()) return $this->unauthorized('Authentication required');
        $requested = (int)($staffId ?? $data['staff_id'] ?? $_GET['staff_id'] ?? 0);
        if (!$requested) $requested = (int)($this->staffAccess->staffId() ?? 0);
        try { $staffId = $this->staffAccess->requireSelfOr('staff.attendance.view', $requested, ['system administrator','school administrator','headteacher','director']); }
        catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[AttendanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }

        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $result = $this->api->getStaffAttendancePercentage($staffId, $termId, $yearId);
        return $this->handleResponse($result);
    }

    public function getChronicStaffAbsentees($departmentId = null, $data = [], $segments = [])
    {
        $termId = $data['termId'] ?? $data['term_id'] ?? $_GET['termId'] ?? $_GET['term_id'] ?? null;
        $yearId = $data['yearId'] ?? $data['year_id'] ?? $_GET['yearId'] ?? $_GET['year_id'] ?? null;
        $threshold = $data['threshold'] ?? $_GET['threshold'] ?? 0.2;
        $result = $this->api->getChronicStaffAbsentees($departmentId, $termId, $yearId, $threshold);
        return $this->handleResponse($result);
    }

    // CRUD endpoints (list, get, create, update, delete)
    public function get($id = null, $data = [], $segments = [])
    {
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    public function post($id = null, $data = [], $segments = [])
    {
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    public function put($id = null, $data = [], $segments = [])
    {
        $id = $id ?? $data['id'] ?? null;
        if (!$id) {
            return $this->badRequest('Missing attendance record ID');
        }
        // Add type from data, query string, or default to 'student'
        $data['type'] = $data['type'] ?? $_GET['type'] ?? 'student';
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    public function delete($id = null, $data = [], $segments = [])
    {
        $id = $id ?? $data['id'] ?? null;
        if (!$id) {
            return $this->badRequest('Missing attendance record ID');
        }
        // Add type from data, query string, or default to 'student'
        $data['type'] = $data['type'] ?? $_GET['type'] ?? 'student';
        $result = $this->api->delete($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/attendance/classes - Get all classes for attendance marking dropdown
     */
    public function getClasses($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getClasses($data));
    }

    /**
     * GET /api/attendance/students-by-class/{stream_id} - Get students for a class
     */
    public function getStudentsByClass($streamId = null, $data = [], $segments = [])
    {
        if ($streamId !== null) {
            $data['stream_id'] = $streamId;
        }
        return $this->handleApiResponse($this->manager->getStudentsByClass($data));
    }

    /**
     * POST /api/attendance/mark-bulk - Mark attendance for multiple students at once
     * Expects: { stream_id, date, attendance: [ { student_id, status } ] }
     */
    public function postMarkBulk($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->postMarkBulk($data));
    }

    // ========================================================================
    // SESSION-BASED ATTENDANCE METHODS (NEW)
    // ========================================================================

    /**
     * GET /api/attendance/sessions - Get all attendance sessions
     * Optionally filter by type (academic, boarding, activity)
     */
    public function getSessions($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getSessions($data));
    }

    public function getSessionConfig($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getSessionConfig($data));
    }

    public function putSessionConfig($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->putSessionConfig((int) $id, $data));
    }

    /**
     * GET /api/attendance/session-attendance - Get attendance for a specific session
     */
    public function getSessionAttendance($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['session_id'] = $id;
        }
        return $this->handleApiResponse($this->manager->getSessionAttendance($data));
    }

    /**
     * POST /api/attendance/mark-session - Mark attendance for a specific session
     * Expects: { session_id, stream_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkSession($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->postMarkSession($data));
    }

    /**
     * GET /api/attendance/academic-summary
     * Aggregate learner attendance for the shared reports page.
     */
    public function getAcademicSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getAcademicSummary($data));
    }

    /**
     * GET /api/attendance/daily-register
     * Return raw attendance rows for the selected day/session.
     */
    public function getDailyRegister($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getDailyRegister($data));
    }

    /**
     * GET /api/attendance/register-range
     * Attendance register for a class stream over a date range (history view).
     */
    public function getRegisterRange($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getRegisterRange($data));
    }

    // ========================================================================
    // BOARDING ATTENDANCE METHODS
    // ========================================================================

    /**
     * GET /api/attendance/dormitories - Get all dormitories
     */
    public function getDormitories($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getDormitories($data));
    }

    /**
     * GET /api/attendance/dormitory-students - Get students in a dormitory for roll call
     */
    public function getDormitoryStudents($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['dormitory_id'] = $id;
        }
        return $this->handleApiResponse($this->manager->getDormitoryStudents($data));
    }

    /**
     * POST /api/attendance/mark-boarding - Mark boarding attendance (roll call)
     * Expects: { dormitory_id, session_id, date, attendance: [{ student_id, status, notes }] }
     */
    public function postMarkBoarding($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->postMarkBoarding($data));
    }

    /**
     * GET /api/attendance/boarding-summary - Get boarding attendance summary for a date
     */
    public function getBoardingSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getBoardingSummary($data));
    }

    // ========================================================================
    // STUDENT PERMISSION METHODS
    // ========================================================================

    /**
     * GET /api/attendance/permission-types - Get all student permission types
     */
    public function getPermissionTypes($id = null, $data = [], $segments = [])
    {
        return $this->permissionService->getPermissionTypes($id, $data, $segments, $this);
    }

    /**
     * GET /api/attendance/permissions - Get student permissions (optionally filtered)
     */
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance(
            'attendance_boarding_view',
            ['system administrator', 'school administrator', 'headteacher', 'director', 'boarding master']
        )) return $denied;

        return $this->handleApiResponse($this->manager->getPermissions($data));
    }

    /**
     * POST /api/attendance/permissions - Create a new student permission/exeat
     */
    public function postPermissions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance(
            'attendance_boarding_create',
            ['system administrator', 'school administrator', 'headteacher', 'boarding master']
        )) return $denied;

        return $this->handleApiResponse($this->manager->postPermissions($data));
    }

    /**
     * PUT /api/attendance/permissions/{id} - Approve/reject permission
     */
    public function putPermissions($id = null, $data = [], $segments = [])
    {
        $requestedStatus = $data['status'] ?? null;
        $approvalDecision = in_array($requestedStatus, ['approved', 'rejected'], true);
        $permission = $approvalDecision
            ? 'attendance_boarding_approve'
            : 'attendance_boarding_edit';
        $fallbackRoles = $approvalDecision
            ? ['system administrator', 'school administrator', 'headteacher', 'director']
            : ['system administrator', 'school administrator', 'headteacher', 'boarding master'];

        if ($denied = $this->guardStaffAttendance($permission, $fallbackRoles)) {
            return $denied;
        }

        return $this->handleApiResponse($this->manager->putPermissions($id, $data));
    }

    // ========================================================================
    // STAFF ATTENDANCE METHODS (ENHANCED)
    // ========================================================================

    /**
     * GET /api/attendance/staff-today - Get staff attendance for today with leave/off-day info
     */
    public function getStaffToday($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance('staff.attendance.view', ['system administrator','school administrator','headteacher','director'])) return $denied;
        return $this->handleApiResponse($this->manager->getStaffToday($data));
    }

    /**
     * POST /api/attendance/mark-staff - Mark staff attendance
     * Expects: { date, attendance: [{ staff_id, status, check_in_time, check_out_time, notes }] }
     */
    public function postMarkStaff($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance('staff.attendance.manage', ['system administrator','school administrator','headteacher'])) return $denied;
        return $this->handleApiResponse($this->manager->postMarkStaff($data));
    }

    /**
     * GET /api/attendance/staff-register-context?date=X&department_id=Y
     * Returns full pre-computed register for a date: who is on leave, off, duty, expected time.
     */
    public function getStaffRegisterContext($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance('staff.attendance.view', ['system administrator','school administrator','headteacher','director'])) return $denied;
        return $this->handleApiResponse($this->manager->getStaffRegisterContext($data));
    }

    // ========================================================================
    // STAFF DUTY AND REPORT METHODS
    // ========================================================================

    /**
     * GET /api/attendance/duty-types - Get all staff duty types
     */
    public function getDutyTypes($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance('staff.attendance.view', ['system administrator','school administrator','headteacher','director'])) return $denied;
        return $this->handleApiResponse($this->manager->getDutyTypes($data));
    }

    /**
     * GET /api/attendance/staff-report - Get staff attendance report with aggregates
     * Params: date_from, date_to, department_id, duty_type_id, status
     */
    public function getStaffReport($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->guardStaffAttendance('staff.attendance.view', ['system administrator','school administrator','headteacher','director'])) return $denied;
        return $this->handleApiResponse($this->manager->getStaffReport($data));
    }

    // ========================================================================
    // SCHOOL CALENDAR METHODS
    // ========================================================================

    /**
     * GET /api/attendance/calendar - Get school calendar for a date range
     */
    public function getCalendar($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getCalendar($data));
    }

    /**
     * GET /api/attendance/is-school-day - Check if a date is a school day
     */
    public function getIsSchoolDay($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getIsSchoolDay($data));
    }

    public function getCurrentStaffId(): ?int
    {
        return $this->manager->getCurrentStaffId();
    }

    public function userCanAccessBoardingAttendance(): bool
    {
        return $this->manager->userCanAccessBoardingAttendance();
    }

    public function getAccessibleClassScope(): array
    {
        return $this->manager->getAccessibleClassScope();
    }

    public function getAccessibleStaffScope(): array
    {
        return $this->manager->getAccessibleStaffScope();
    }

    public function isStaffInScope(?int $staffId, array $scope): bool
    {
        return $this->manager->isStaffInScope($staffId, $scope);
    }

    public function buildStreamScopeClause(?int $requestedStreamId, array $scope, string $column = 's.stream_id'): array
    {
        return $this->manager->buildStreamScopeClause($requestedStreamId, $scope, $column);
    }

    /**
     * Unified API response handler (matches other controllers)
     */
    public function handleResponse($result)
    {
        if (is_array($result)) {
            // Handle successResponse/errorResponse format: {status, message, type, code, data}
            if (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['message'] ?? 'Operation failed');
                }
            }
            // Handle legacy {success: true/false, data, message} format
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }
        return $this->success($result);
    }

    // ========================================================================
    // REGISTER CONTEXT — calendar + session awareness for a given date
    // ========================================================================

    /**
     * GET /api/attendance/register-context?date=2026-04-26&stream_id=3&session_id=1
     *
     * Returns everything the frontend needs to decide:
     * - Is today a school day? A boarding day?
     * - Which sessions apply today?
     * - How many students already marked?
     * - What is the current academic term + year?
     */
    public function getRegisterContext($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->manager->getRegisterContext($data));
    }

    /** GET /api/attendance/expected-registers?date=YYYY-MM-DD */
    public function getExpectedRegisters($id = null, $data = [], $segments = [])
    {
        try {
            $scope = $this->manager->getAccessibleClassScope();
            if (!empty($scope['restricted'])) {
                $data['stream_ids'] = $scope['stream_ids'];
            }
            $service = $this->contract('App\API\Services\AttendanceRegisterService', $this->getDb()->getConnection());
            return $this->success($service->list($data), 'Expected attendance registers retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] expected registers failed: ' . $e->getMessage());
            return $this->serverError('Expected attendance registers unavailable');
        }
    }

    /** POST /api/attendance/ai-exception-summary-queue */
    public function postAiExceptionSummaryQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiAttendance()) return $this->forbidden('Attendance AI assistance is not available for your account');
        $date = (string) ($data['date'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $this->badRequest('A valid attendance date is required');
        try {
            $scope = $this->manager->getAccessibleClassScope();
            $filters = ['date' => $date];
            if (!empty($scope['restricted'])) $filters['stream_ids'] = array_values(array_map('intval', (array) ($scope['stream_ids'] ?? [])));
            $result = $this->contract('App\API\Services\AttendanceRegisterService', $this->getDb()->getConnection())->list($filters);
            $registers = is_array($result['registers'] ?? null) ? $result['registers'] : [];
            $counts = ['completed' => 0, 'open' => 0, 'overdue' => 0, 'not_marked' => 0];
            $types = [];
            foreach ($registers as $register) {
                $status = (string) ($register['status'] ?? '');
                if (array_key_exists($status, $counts)) $counts[$status]++;
                if ($status !== '' && $status !== 'completed') $types[$status] = true;
            }
            $input = [
                'report_date' => $date,
                'register_count' => (string) count($registers),
                'completed_count' => (string) $counts['completed'],
                'open_count' => (string) $counts['open'],
                'overdue_count' => (string) $counts['overdue'],
                'not_marked_count' => (string) $counts['not_marked'],
                'scope_stream_count' => (string) count(array_unique(array_filter(array_map(
                    static fn(array $register): int => (int) ($register['stream_id'] ?? 0),
                    $registers
                )))),
                'exception_types' => array_keys($types),
                'follow_up_intent' => 'Prepare register-review follow-up for authorized staff; do not alter attendance records.',
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'attendance.exception_summary',
                $this->aiAttendanceContext(),
                $input,
                ['subject_type' => 'attendance_exception_summary', 'scope' => 'authorized_attendance_registers']
            );
            return $this->accepted($queued, 'Attendance exception summary queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] AI exception summary failed: ' . $e->getMessage());
            return $this->serverError('Attendance assistance is temporarily unavailable');
        }
    }

    /** POST /api/attendance/ai-lateness-pattern-queue */
    public function postAiLatenessPatternQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiAttendanceWorkflow('attendance.lateness_pattern_review')) return $this->forbidden('Attendance AI assistance is not available for your account');
        $from = (string) ($data['date_from'] ?? date('Y-m-d', strtotime('-30 days'))); $to = (string) ($data['date_to'] ?? date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) return $this->badRequest('A valid date range is required');
        try {
            $scope = $this->manager->getAccessibleClassScope(); $filters = ['date_from' => $from, 'date_to' => $to];
            if (!empty($scope['restricted'])) $filters['stream_ids'] = array_values(array_map('intval', (array) ($scope['stream_ids'] ?? [])));
            $raw = $this->manager->getAcademicSummary($filters); $summary = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
            $present = (int) ($summary['present'] ?? $summary['students']['present'] ?? 0); $absent = (int) ($summary['absent'] ?? $summary['students']['absent'] ?? 0); $late = (int) ($summary['late'] ?? $summary['students']['late'] ?? 0); $total = $present + $absent + $late;
            $input = ['date_from' => $from, 'date_to' => $to, 'present_count' => (string) $present, 'absent_count' => (string) $absent, 'late_count' => (string) $late, 'late_rate' => $total > 0 ? (string) round($late / $total * 100, 2) : '0', 'follow_up_intent' => 'Verify authorized register details and speak with responsible staff before action.'];
            return $this->accepted($this->contract(AiDraftService::class)->queue('attendance.lateness_pattern_review', $this->aiAttendanceContext(), $input, ['subject_type' => 'attendance_lateness_pattern', 'scope' => 'authorized_attendance_aggregate']), 'Attendance lateness review queued');
        } catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (\Throwable $e) { return $this->serverError('Attendance lateness review is temporarily unavailable'); }
    }

    /** GET /api/attendance/ai-exception-summaries?scope=own|review */
    public function getAiExceptionSummaries($id = null, $data = [], $segments = [])
    {
        $review = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own')) === 'review';
        if ($review && !$this->canApproveAiAttendance()) return $this->forbidden('Attendance review permission is required');
        try {
            return $this->success(['drafts' => $this->contract(AiDraftService::class)->listForReview(
                $this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'attendance'
            ), 'scope' => $review ? 'review' : 'own'], 'Attendance AI summaries retrieved');
        } catch (\Throwable $e) {
            return $this->serverError('Attendance assistance is temporarily unavailable');
        }
    }

    /** POST /api/attendance/ai-exception-summary-approve/{id} */
    public function postAiExceptionSummaryApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiAttendance()) return $this->forbidden('Attendance review permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $stmt = $this->getDb()->getConnection()->prepare('SELECT workflow_id FROM ai_workflow_drafts WHERE id = ? LIMIT 1');
            $stmt->execute([$draftId]);
            $workflow = (string) ($stmt->fetchColumn() ?: '');
            if (!in_array($workflow, ['attendance.exception_summary', 'attendance.lateness_pattern_review'], true)) {
                return $this->respond(null, 'This draft does not belong to an attendance review workflow.', 409, false);
            }
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), $workflow);
            return $this->success(['draft_id' => $draftId, 'status' => 'approved', 'review_only' => true, 'draft' => $approved['draft'] ?? []], 'Attendance summary approved for staff guidance');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        }
    }

    private function aiAttendanceContext(): array
    {
        return ['user_id' => (int) ($this->getUserId() ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($this->user['effective_permissions'] ?? []), (array) ($this->user['permissions'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId];
    }

    private function canUseAiAttendance(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('attendance.exception_summary', $this->aiAttendanceContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canUseAiAttendanceWorkflow(string $workflow): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize($workflow, $this->aiAttendanceContext()); return true; } catch (DomainException $e) { return false; }
    }

    private function canApproveAiAttendance(): bool
    {
        return $this->userHasAny(['attendance_manage', 'attendance_approve', 'attendance_update'], [3, 4, 5, 10], ['headteacher', 'deputy headteacher', 'school administrator', 'director']);
    }

    /** Internal worker endpoint for cron/systemd register reminders. */
    public function postProcessRegisterReminders($id = null, $data = [], $segments = [])
    {
        $expected = defined('ATTENDANCE_WORKER_SECRET') ? (string) ATTENDANCE_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->forbidden('Invalid worker credential');
        }
        try {
            $service = $this->contract('App\API\Services\AttendanceRegisterService', $this->getDb()->getConnection());
            return $this->success($service->process($data['date'] ?? null), 'Attendance registers reconciled');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] register worker failed: ' . $e->getMessage());
            return $this->serverError('Attendance register reconciliation failed');
        }
    }

    /** POST /api/attendance/gate-event — trusted gate gateway callback. */
    public function postGateEvent($id = null, $data = [], $segments = [])
    {
        $secret = defined('ATTENDANCE_GATE_SECRET') ? (string) ATTENDANCE_GATE_SECRET : '';
        $signature = $_SERVER['HTTP_X_KINGSWAY_GATE_SIGNATURE'] ?? '';
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($secret === '' || !is_string($signature) || !hash_equals(hash_hmac('sha256', $payload ?: '', $secret), $signature)) {
            return $this->forbidden('Invalid gate device signature');
        }
        try {
            $service = $this->contract('App\API\Services\StaffGateAttendanceService', $this->getDb()->getConnection());
            return $this->success($service->record($data), 'Gate attendance event processed');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] gate event failed: ' . $e->getMessage());
            return $this->serverError('Gate attendance event could not be processed');
        }
    }

    /**
     * GET /api/attendance/student-history-by-year/{student_id}
     * Returns attendance records grouped by academic year → term
     * with clear differentiation even if student repeated a class
     */
    public function getStudentHistoryByYear($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($data['student_id'] ?? $segments[0] ?? null);
        return $this->handleApiResponse($this->manager->getStudentHistoryByYear($studentId, $data));
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

    /**
     * Look up which term and academic year a given date belongs to.
     * Falls back to current active term if date not in any term range.
     */
    public function _resolveTermForDate(string $date): array
    {
        return $this->manager->resolveTermForDate($date);
    }

}
