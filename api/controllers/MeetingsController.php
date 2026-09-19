<?php
namespace App\API\Controllers;

use App\API\Modules\communications\StaffMeetingManager;
use Exception;

/**
 * MeetingsController - internal staff meetings (heads/HODs/deputies/class
 * teachers) integrated with the academic calendar.
 *
 * ROUTES (all require authentication):
 *   GET    /api/meetings/meetings-get            - list (filters: type, status, department_id, search, mine)
 *   GET    /api/meetings/meetings-get/{id}       - one meeting + attendees
 *   GET    /api/meetings/staff-list              - active staff for the attendee picker
 *   POST   /api/meetings/meetings-create         - schedule a meeting (+ attendees + calendar event + notifications)
 *   PUT    /api/meetings/meetings-update/{id}    - edit / re-schedule
 *   DELETE /api/meetings/meetings-delete/{id}    - delete meeting + its calendar event
 *   POST   /api/meetings/meetings-respond        - RSVP { meeting_id, status: accepted|declined|maybe }
 *   POST   /api/meetings/meetings-remind/{id}    - re-notify attendees
 */
class MeetingsController extends BaseController
{
    private StaffMeetingManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = $this->contract('App\API\Modules\communications\StaffMeetingManager', $this->db->getConnection());
    }

    private function guard(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    private function currentStaffId(): ?int
    {
        $userId = $this->currentUserId();
        if (!$userId) {
            return null;
        }
        $stmt = $this->db->getConnection()->prepare(
            "SELECT s.id FROM staff s JOIN users u ON u.person_id = s.person_id WHERE u.id = ? LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function currentUserId(): ?int
    {
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        return $userId ? (int) $userId : null;
    }

    public function getMeetingsGet($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            if ($id) {
                $meeting = $this->manager->getMeeting((int) $id, $this->currentStaffId());
                if (!$meeting) {
                    return $this->notFound('Meeting not found');
                }
                return $this->success($meeting);
            }
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            $meetings = $this->manager->listMeetings($filters, $this->currentStaffId());
            return $this->success($meetings);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('Failed to load meetings', null, 500);
        }
    }

    public function getStaffList($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            return $this->success($this->manager->listStaffForPicker());
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to load staff', null, 500);
        }
    }

    public function postMeetingCreate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            $staffId = $this->currentStaffId();
            if (!$staffId) {
                return $this->error('Staff account required to schedule meetings', null, 403);
            }
            $result = $this->manager->createMeeting($staffId, is_array($data) ? $data : []);
            if (!empty($result['error'])) {
                return $this->error($result['error'], null, $result['code'] ?? 400);
            }
            return $this->success($result, 'Meeting scheduled');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to schedule meeting', null, 500);
        }
    }

    public function putMeetingUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            if (!$id) {
                return $this->error('Meeting ID is required', null, 400);
            }
            $result = $this->manager->updateMeeting((int) $id, is_array($data) ? $data : []);
            if (!empty($result['error'])) {
                return $this->error($result['error'], null, $result['code'] ?? 400);
            }
            return $this->success($result, 'Meeting updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to update meeting', null, 500);
        }
    }

    public function deleteMeetingDelete($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            if (!$id) {
                return $this->error('Meeting ID is required', null, 400);
            }
            $result = $this->manager->deleteMeeting((int) $id);
            if (!empty($result['error'])) {
                return $this->error($result['error'], null, $result['code'] ?? 400);
            }
            return $this->success(null, 'Meeting deleted');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to delete meeting', null, 500);
        }
    }

    public function postMeetingRespond($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            $staffId = $this->currentStaffId();
            if (!$staffId) {
                return $this->error('Staff account required', null, 403);
            }
            $result = $this->manager->respondToMeeting(
                (int) ($data['meeting_id'] ?? $id ?? 0),
                $staffId,
                (string) ($data['status'] ?? '')
            );
            if (!empty($result['error'])) {
                return $this->error($result['error'], null, $result['code'] ?? 400);
            }
            return $this->success(null, 'Response saved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to save response', null, 500);
        }
    }

    public function postMeetingRemind($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guard()) return $guard;
        try {
            if (!$id) {
                return $this->error('Meeting ID is required', null, 400);
            }
            $result = $this->manager->remindAttendees((int) $id);
            if (!empty($result['error'])) {
                return $this->error($result['error'], null, $result['code'] ?? 400);
            }
            return $this->success(null, $result['message']);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MeetingsController] ' . $e->getMessage());
            return $this->error('Failed to send reminder', null, 500);
        }
    }
}
