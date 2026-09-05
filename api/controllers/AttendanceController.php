<?php

namespace App\API\Controllers;

use App\API\Modules\attendance\AttendanceAPI;
use App\API\Modules\attendance\AttendanceManager;
use App\API\Modules\attendance\AttendanceStudentService;
use App\API\Modules\attendance\AttendanceStaffService;
use App\API\Modules\attendance\AttendancePermissionService;
use App\API\Services\StaffDomainAccessService;
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
        $this->api = new AttendanceAPI();
        $this->staffAccess = new StaffDomainAccessService($this->user);
        $this->studentAttendanceService = new AttendanceStudentService($this->api);
        $this->staffAttendanceService = new AttendanceStaffService($this->api);
        $this->permissionService = new AttendancePermissionService($this->api);
        $this->manager = new AttendanceManager();
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
            $service = new \App\API\Services\DirectorAnalyticsService();
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
            $service = new \App\API\Services\AttendanceRegisterService($this->getDb()->getConnection());
            return $this->success($service->list($data), 'Expected attendance registers retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AttendanceController] expected registers failed: ' . $e->getMessage());
            return $this->serverError('Expected attendance registers unavailable');
        }
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
            $service = new \App\API\Services\AttendanceRegisterService($this->getDb()->getConnection());
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
            $service = new \App\API\Services\StaffGateAttendanceService($this->getDb()->getConnection());
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
