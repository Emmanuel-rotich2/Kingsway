<?php
namespace App\API\Controllers;

use App\API\Modules\schedules\SchedulesAPI;
use App\API\Services\TeacherScopeService;
use Exception;

/**
 * SchedulesController - REST endpoints for all scheduling operations
 * Handles timetables, exam schedules, events, activity schedules, rooms, and route schedules
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class SchedulesController extends BaseController
{
    private SchedulesAPI $api;


    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\schedules\SchedulesAPI');
    }

    private function guardSchedules(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    /** Exam drafts are an academic-leadership workspace, not a teacher workspace. */
    private function guardExamTimetable(bool $publishing = false): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $allowedRoleIds = $publishing ? [1, 4] : [1, 4, 5, 6];
        $allowedRoleNames = $publishing
            ? ['system administrator', 'school administrator']
            : ['system administrator', 'school administrator', 'headteacher', 'deputy head - academic'];
        if (!$this->userHasAny([], $allowedRoleIds, $allowedRoleNames)) {
            return $this->forbidden(
                $publishing
                    ? 'Only the school administrator can publish an approved exam timetable'
                    : 'Exam timetable drafts are restricted to academic leadership'
            );
        }
        return null;
    }

    private function currentUserId(): int
    {
        return (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
    }

    /** Resolve the primary role from the normalized login response. */
    private function currentRole(): string
    {
        $role = $this->user['role_name'] ?? $this->user['role'] ?? '';
        $roles = !empty($this->user['roles']) && is_array($this->user['roles']) ? $this->user['roles'] : [];
        $names = [];
        foreach ($roles as $candidate) {
            if (is_array($candidate)) $names[] = $candidate['name'] ?? $candidate['role_name'] ?? '';
            elseif (is_object($candidate)) $names[] = $candidate->name ?? $candidate->role_name ?? '';
            else $names[] = (string) $candidate;
        }
        // A blended teacher must receive the strongest timetable scope. A
        // Headteacher/Deputy/Admin who also teaches is still an administrator
        // on this workspace, not a class-teacher-only user.
        foreach (array_merge($names, [(string) $role]) as $candidate) {
            $candidate = strtolower(trim((string) $candidate));
            if ($candidate !== '' && preg_match('/admin|director|headteacher|deputy/', $candidate)) return $candidate;
        }
        return strtolower((string) ($names[0] ?? $role));
    }

    private function currentStaffId(): ?int
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        if (!$userId) return null;
        $stmt = $this->db->getConnection()->prepare(
            "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? AND s.status = 'active' LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** Restrict timetable reads to the streams the current teacher actually owns. */
    private function timetableScope(array $data): array
    {
        $role = $this->currentRole();
        $isManagement = strpos($role, 'admin') !== false || strpos($role, 'director') !== false
            || strpos($role, 'headteacher') !== false || strpos($role, 'deputy') !== false;
        if ($isManagement) return $data;

        $scope = ($this->contract('App\API\Services\TeacherScopeService', $this->db->getConnection()))->forUser(
            $this->user ?: [],
            !empty($data['academic_year_id']) ? (int)$data['academic_year_id'] : null,
            !empty($data['academic_year_term_id']) ? (int)$data['academic_year_term_id'] : null
        );
        // Page contexts may intentionally narrow the union. Lower-primary
        // class-teacher timetable work is about the owned class streams only;
        // subject-teacher visibility remains available in subject operations.
        if (($data['scope_context'] ?? '') === 'class_teacher') {
            $data['_scope_stream_ids'] = $scope['class_stream_ids'] ?: [-1];
        } else {
            $data['_scope_stream_ids'] = $scope['visible_stream_ids'] ?: [-1];
        }
        return $data;
    }

    public function index()
    {
        return $this->success(['message' => 'Schedules API is running']);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/schedules - List all schedules
     * GET /api/schedules/{id} - Get single schedule
     * No bare schedules resource exists (legacy `schedules` table was dropped in
     * the normalized schema); all schedules data is served via named sub-resources.
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }

        return $this->notFound('Schedules resource not found; use /schedules/{resource}');
    }

    /**
     * POST /api/schedules - Create new schedule
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        return $this->notFound('Schedules resource not found; use /schedules/{resource}');
    }

    /**
     * PUT /api/schedules/{id} - Update schedule
     */
    public function put($id = null, $data = [], $segments = [])
    {
        return $this->notFound('Schedules resource not found; use /schedules/{resource}');
    }

    /**
     * DELETE /api/schedules/{id} - Delete schedule
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        return $this->notFound('Schedules resource not found; use /schedules/{resource}');
    }

    // ========================================
    // SECTION 2: Timetable Operations
    // ========================================

    /**
     * GET /api/schedules/timetable/get
     */
    public function getTimetableGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getTimetable($this->timetableScope($data));
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/timetable/create
     */
    public function postTimetableCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->badRequest('Direct timetable entry creation is disabled. Create or resume a timetable draft, then submit it for approval.');
    }

    /**
     * PUT /api/schedules/timetable/update/{id}
     */
    public function putTimetableUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->badRequest('Direct timetable entry updates are disabled after the draft workflow was enabled.');
    }

    /**
     * DELETE /api/schedules/timetable/delete/{id}
     * POST /api/schedules/timetable/delete (with body data for day/time/class combo)
     */
    public function deleteTimetableDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->deleteTimetableEntry($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/timetable/delete (fallback for body-based delete)
     */
    public function postTimetableDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->deleteTimetableEntry($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/schedules/timetable/check-conflicts
     */
    public function getTimetableCheckConflicts($id = null, $data = [], $segments = [])
    {
        $result = $this->api->checkTimetableConflicts($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/timetable/report-conflict
     */
    public function postTimetableReportConflict($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        // Inject authenticated user if available
        if (!empty($this->user['id'])) {
            $data['reported_by'] = $this->user['id'];
        }
        $result = $this->api->reportTimetableConflict($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/schedules/timetable/time-slots
     */
    public function getTimetableTimeSlots($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getTimeSlots();
        return $this->handleResponse($result);
    }

    /** GET /api/schedules/timetable-drafts */
    public function getTimetableDrafts($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->handleResponse($this->api->listTimetableDrafts($this->timetableScope($data)));
    }

    public function getTimetableStreams($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->handleResponse($this->api->listTimetableStreams($this->timetableScope($data)));
    }

    /** GET /api/schedules/timetable-draft/{id} */
    public function getTimetableDraft($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $scope = $this->timetableScope([])['_scope_stream_ids'] ?? null;
        if ($scope !== null) {
            $check = $this->db->getConnection()->prepare("SELECT COUNT(*) FROM timetable_draft_entries WHERE draft_id = ? AND academic_year_class_stream_id IN (" . ($scope ? implode(',', array_fill(0, count($scope), '?')) : '0') . ")");
            $check->execute(array_merge([(int)($id ?? ($data['id'] ?? 0))], $scope ?: []));
            if (!(int)$check->fetchColumn()) return $this->forbidden('This timetable draft is outside your assigned streams.');
        }
        return $this->handleResponse($this->api->getTimetableDraft($id ?? ($data['id'] ?? 0)));
    }

    /** POST /api/schedules/timetable-draft */
    public function postTimetableDraft($id = null, $data = [], $segments = [])
    {
        try {
            if ($guard = $this->guardSchedules()) return $guard;
            $role = $this->currentRole();
            $scope = strtolower((string)($data['scope'] ?? ''));
            $isAdmin = strpos($role, 'admin') !== false || strpos($role, 'director') !== false || strpos($role, 'headteacher') !== false;
            $isDeputy = strpos($role, 'deputy') !== false;
            $isClassTeacher = strpos($role, 'class') !== false && strpos($role, 'teacher') !== false;
            if ($scope === 'lower_primary' && !$isAdmin && !$isDeputy && !$isClassTeacher) return $this->forbidden('Only a class teacher, deputy headteacher, headteacher, school admin, or director may draft this timetable.');
            if ($scope === 'upper_primary' && !$isAdmin && !$isDeputy) return $this->forbidden('Only the school admin, deputy headteacher, headteacher, or director may draft the Grade 4–9 timetable.');
            if ($scope === 'whole_school' && !$isAdmin && !$isDeputy) return $this->forbidden('Only academic leadership may draft the whole-school timetable.');
            $data['created_by'] = (int)($this->user['id'] ?? 0);
            $staffId = $this->currentStaffId();
            $data['_actor_staff_id'] = $staffId;
            $data['_class_teacher_mode'] = $isClassTeacher;
            $data['_academic_leadership_mode'] = $isAdmin || $isDeputy;
            $data['_scope_stream_ids'] = $this->timetableScope(['academic_year_id' => $data['academic_year_id']])['_scope_stream_ids'] ?? [];
            return $this->handleResponse($this->api->saveTimetableDraft($data));
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SchedulesController] timetable draft endpoint failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('Timetable draft could not be saved');
        }
    }

    /** POST /api/schedules/timetable-draft-transition */
    public function postTimetableDraftTransition($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $role = $this->currentRole();
        $action = strtolower((string)($data['action'] ?? ''));
        $isLeadership = strpos($role, 'admin') !== false || strpos($role, 'director') !== false || strpos($role, 'headteacher') !== false || strpos($role, 'deputy') !== false;
        $isSubjectTeacher = strpos($role, 'subject') !== false && strpos($role, 'teacher') !== false;
        $isClassTeacher = strpos($role, 'class') !== false && strpos($role, 'teacher') !== false;
        if ($action === 'publish' && !$isLeadership) return $this->forbidden('Only academic leadership may publish a timetable.');
        if ($action === 'approve' && !$isLeadership) return $this->forbidden('Only the deputy headteacher, headteacher, school admin, or director may approve a timetable.');
        if (in_array($action, ['review', 'request_changes'], true) && !$isLeadership && !$isSubjectTeacher) return $this->forbidden('Only an assigned subject teacher or academic leader may review this timetable.');
        if ($isClassTeacher && in_array($action, ['submit'], true)) {
            $scope = $this->timetableScope([])['_scope_stream_ids'] ?? [];
            if (!$scope) return $this->forbidden('No assigned class stream is available for timetable submission.');
            $q = $this->db->getConnection()->prepare("SELECT COUNT(*) FROM timetable_draft_entries WHERE draft_id = ? AND academic_year_class_stream_id IN (" . implode(',', array_fill(0, count($scope), '?')) . ")");
            $q->execute(array_merge([(int)($data['id'] ?? 0)], $scope));
            if (!(int)$q->fetchColumn()) return $this->forbidden('You may only submit a draft containing your assigned streams.');
        }
        $data['actor_id'] = (int)($this->user['id'] ?? 0);
        return $this->handleResponse($this->api->transitionTimetableDraft($data));
    }

    public function getDutyRosterDrafts($id = null, $data = [], $segments = []) { if ($guard=$this->guardSchedules()) return $guard; return $this->handleResponse($this->api->listDutyRosterDrafts($data)); }
    public function getDutyRosterDraft($id = null, $data = [], $segments = []) { if ($guard=$this->guardSchedules()) return $guard; return $this->handleResponse($this->api->getDutyRosterDraft($id ?? ($data['id']??0))); }
    public function postDutyRosterDraft($id = null, $data = [], $segments = []) { if ($guard=$this->guardSchedules()) return $guard; $data['created_by']=(int)($this->user['id']??0); return $this->handleResponse($this->api->saveDutyRosterDraft($data)); }
    public function postDutyRosterTransition($id = null, $data = [], $segments = []) { if ($guard=$this->guardSchedules()) return $guard; $data['actor_id']=(int)($this->user['id']??0); return $this->handleResponse($this->api->transitionDutyRosterDraft($data)); }
    public function getExamTimetableDrafts($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardExamTimetable()) return $guard;
        return $this->handleResponse($this->api->listExamTimetableDrafts($data));
    }

    public function getExamTimetableDraft($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardExamTimetable()) return $guard;
        return $this->handleResponse($this->api->getExamTimetableDraft($id ?? ($data['id'] ?? 0)));
    }

    public function postExamTimetableDraft($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardExamTimetable()) return $guard;
        $data['created_by'] = $this->currentUserId();
        return $this->handleResponse($this->api->saveExamTimetableDraft($data));
    }

    public function postExamTimetableTransition($id = null, $data = [], $segments = [])
    {
        $action = strtolower((string) ($data['action'] ?? ''));
        if ($guard = $this->guardExamTimetable($action === 'publish')) return $guard;
        $data['actor_id'] = $this->currentUserId();
        return $this->handleResponse($this->api->transitionExamTimetableDraft($data));
    }

    // ========================================
    // SECTION 3: Exam Schedules
    // ========================================

    /**
     * GET /api/schedules/exam/get
     */
    public function getExamGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getExamSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/exam/create
     */
    public function postExamCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->badRequest('Direct exam creation is disabled. Use the Exam Timetable Drafts workflow.');
    }

    /**
     * POST /api/schedules/exam/bulk-generate
     * Delegates to sp_create_exam_schedule for every active class stream x learning area.
     */
    public function postExamBulkGenerate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        return $this->badRequest('Direct exam generation is disabled. Generate and submit an exam timetable draft first.');
    }

    // ========================================
    // SECTION 4: Events
    // ========================================

    /**
     * GET /api/schedules/events/get
     */
    public function getEventsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getEvents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/events/create
     */
    public function postEventsCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->createEvent($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/schedules/events-update/{id}
     */
    public function putEventsUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->updateEvent($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/schedules/events-delete/{id}
     */
    public function deleteEventsDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->deleteEvent($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/events-sync
     * Full calendar <-> events reconciliation.
     */
    public function postEventsSync($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->syncEvents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/calendar-mark-exam-week
     * Mark the whole Mon-Fri week containing the given date as exam week.
     */
    public function postCalendarMarkExamWeek($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->markExamWeek($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4b: Holiday Registry (UI-managed)
    // ========================================

    /**
     * GET /api/schedules/holidays-get - list holidays from the registry
     */
    public function getHolidaysGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getHolidays($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/holidays-create - add a holiday
     */
    public function postHolidayCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->createHoliday($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/schedules/holidays-update/{id} - edit/re-date a holiday
     */
    public function putHolidayUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->updateHoliday($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/schedules/holidays-delete/{id} - remove a holiday
     */
    public function deleteHolidayDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->deleteHoliday($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/holidays-apply - re-apply the registry to the calendar
     */
    public function postHolidayApply($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->applyHolidays($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Activity Schedules
    // ========================================

    /**
     * GET /api/schedules/activity/get
     */
    public function getActivityGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getActivitySchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/activity/create
     */
    public function postActivityCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->createActivitySchedule($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Rooms Management
    // ========================================

    /**
     * GET /api/schedules/rooms/get
     */
    public function getRoomsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRooms($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/rooms/create
     */
    public function postRoomsCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->createRoom($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Route Schedules (Transport)
    // ========================================

    /**
     * GET /api/schedules/route/get
     */
    public function getRouteGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRouteSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/route/create
     */
    public function postRouteCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->createRouteSchedule($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (!is_array($result)) {
            return $this->success($result);
        }

        if (isset($result['status'])) {
            $status = strtolower((string) $result['status']);
            $code = (int) ($result['code'] ?? 0);
            $message = $result['message'] ?? ($status === 'success' ? 'Success' : 'Operation failed');
            $data = $result['data'] ?? null;

            if ($status === 'success') {
                return $this->success($data, $message);
            }

            if ($code === 401) {
                return $this->unauthorized($message);
            }
            if ($code === 403) {
                return $this->forbidden($message);
            }
            if ($code === 404) {
                return $this->notFound($message);
            }
            if ($code >= 500) {
                return $this->serverError($message, $data);
            }

            return $this->badRequest($message, is_array($data) ? $data : null);
        }

        if (isset($result['success'])) {
            if ($result['success']) {
                return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
            }
            return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
        }

        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }


    // =============================
    // ADVANCED SCHEDULE/WORKFLOW ENDPOINTS
    // =============================

    // TEACHING STAFF: Get timetable for a teacher
    public function getTeacherSchedule($id = null, $data = [], $segments = [])
    {
        $teacherId = $id ?? ($data['teacher_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getTeacherSchedule($teacherId, $termId);
        return $this->handleResponse($result);
    }

    // SUBJECT SPECIALIST: Get teaching load for a subject
    public function getSubjectTeachingLoad($id = null, $data = [], $segments = [])
    {
        $subjectId = $id ?? ($data['subject_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getSubjectTeachingLoad($subjectId, $termId);
        return $this->handleResponse($result);
    }

    // ACTIVITIES COORDINATOR: Get all activity schedules
    public function getAllActivitySchedules($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllActivitySchedules($data);
        return $this->handleResponse($result);
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($id = null, $data = [], $segments = [])
    {
        $driverId = $id ?? ($data['driver_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getDriverSchedule($driverId, $termId);
        return $this->handleResponse($result);
    }

    // NON-TEACHING STAFF: Get duty schedules
    public function getStaffDutySchedule($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($data['staff_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStaffDutySchedule($staffId, $termId);
        return $this->handleResponse($result);
    }

    // ADMIN: Get master schedule (all classes, activities, events, transport)
    public function getMasterSchedule($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getMasterSchedule($data);
        return $this->handleResponse($result);
    }

    // ANALYTICS: Get schedule analytics (utilization, conflicts, compliance)
    public function getScheduleAnalytics($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getScheduleAnalytics($data);
        return $this->handleResponse($result);
    }

    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)
    public function getStudentSchedules($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($data['student_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStudentSchedules($studentId, $termId);
        return $this->handleResponse($result);
    }

    public function getStaffSchedules($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($data['staff_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStaffSchedules($staffId, $termId);
        return $this->handleResponse($result);
    }

    public function getAdminTermOverview($id = null, $data = [], $segments = [])
    {
        $termId = $id ?? ($data['term_id'] ?? null);
        $result = $this->api->getAdminTermOverview($termId);
        return $this->handleResponse($result);
    }

    // Term & Holiday Workflow Endpoints
    public function postDefineTermDates($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->defineTermDates($data);
        return $this->handleResponse($result);
    }
    public function getReviewTermDates($id = null, $data = [], $segments = [])
    {
        $instanceId = $id ?? ($data['instance_id'] ?? null);
        $result = $this->api->reviewTermDates($instanceId);
        return $this->handleResponse($result);
    }

    // Resource/Slot/Conflict/Compliance/Workflow
    public function getCheckResourceAvailability($id = null, $data = [], $segments = [])
    {
        $resourceType = $data['resource_type'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $start = $data['start'] ?? null;
        $end = $data['end'] ?? null;
        if (!$resourceType || !$resourceId) {
            return $this->badRequest('resource_type and resource_id are required');
        }
        $result = $this->api->checkResourceAvailability($resourceType, $resourceId, $start, $end);
        return $this->handleResponse($result);
    }
    public function getFindOptimalSchedule($id = null, $data = [], $segments = [])
    {
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $constraints = $data['constraints'] ?? [];
        $result = $this->api->findOptimalSchedule($entityType, $entityId, $constraints);
        return $this->handleResponse($result);
    }
    public function postDetectScheduleConflicts($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $proposedSchedule = $data['proposed_schedule'] ?? [];
        $result = $this->api->detectScheduleConflicts($entityType, $entityId, $proposedSchedule);
        return $this->handleResponse($result);
    }
    public function getGenerateMasterSchedule($id = null, $data = [], $segments = [])
    {
        $scope = $data['scope'] ?? null;
        $filters = $data['filters'] ?? [];
        $result = $this->api->generateMasterSchedule($scope, $filters);
        return $this->handleResponse($result);
    }
    public function getValidateScheduleCompliance($id = null, $data = [], $segments = [])
    {
        $scheduleId = $id ?? ($data['schedule_id'] ?? null);
        $result = $this->api->validateScheduleCompliance($scheduleId);
        return $this->handleResponse($result);
    }

    // Scheduling Workflow Methods
    public function postStartSchedulingWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $result = $this->api->startSchedulingWorkflow($data);
        return $this->handleResponse($result);
    }
    public function postAdvanceSchedulingWorkflow($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardSchedules()) return $guard;
        $workflowId = $data['workflow_id'] ?? null;
        $action = $data['action'] ?? null;
        $payload = $data['data'] ?? [];
        $result = $this->api->advanceSchedulingWorkflow($workflowId, $action, $payload);
        return $this->handleResponse($result);
    }
    public function getSchedulingWorkflowStatus($id = null, $data = [], $segments = [])
    {
        $workflowId = $id ?? ($data['workflow_id'] ?? null);
        $result = $this->api->getSchedulingWorkflowStatus($workflowId);
        return $this->handleResponse($result);
    }
    public function getListSchedulingWorkflows($id = null, $data = [], $segments = [])
    {
        $filters = $data['filters'] ?? [];
        $result = $this->api->listSchedulingWorkflows($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/schedules/weekly - Get weekly lessons statistics for dashboard
     * Returns: days, data, total_weekly, daily_average
     */
    public function getWeekly($id = null, $data = [], $segments = [])
    {
        try {
            $result = $this->api->getWeeklyLessonStats();
            return $this->handleResponse($result);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SchedulesController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('An internal error occurred.');
        }
    }

}
