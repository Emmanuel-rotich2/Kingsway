<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Modules\chaplaincy\ChaplaincyAPI;
use Exception;

/**
 * ChaplaincyController
 * Handles all Chaplaincy Department API endpoints (Phase A: team & volunteers).
 *
 * ROUTES:
 * GET    /api/chaplaincy/team-roles        → getTeamRoles()
 * GET    /api/chaplaincy/team              → getTeam()
 * POST   /api/chaplaincy/team/member       → postTeamMember()   (add borrowed staff)
 * POST   /api/chaplaincy/team/bulk-assign  → postTeamBulkAssign() (staff+parents+students)
 * PUT    /api/chaplaincy/team/member/{id}  → putTeamMember($id) (update role)
 * GET    /api/chaplaincy/volunteers        → getVolunteers()
 * POST   /api/chaplaincy/volunteers        → postVolunteers()   (register volunteer)
 * DELETE /api/chaplaincy/volunteers/{id}   → deleteVolunteer($id)
 */
class ChaplaincyController extends BaseController
{
    private ChaplaincyAPI $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new ChaplaincyAPI();
    }

    /**
     * GET /api/chaplaincy/index
     */
    public function getIndex()
    {
        return $this->success(['message' => 'Chaplaincy API is running']);
    }

    /**
     * GET /api/chaplaincy/team-roles
     */
    public function getTeamRoles($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listTeamRoles());
    }

    /**
     * GET /api/chaplaincy/team
     */
    public function getTeam($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getTeam());
    }

    /**
     * POST /api/chaplaincy/team/member — add a borrowed staff member.
     */
    public function postTeamMember($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->addMember($data));
    }

    /**
     * POST /api/chaplaincy/team/bulk-assign — one submit that assigns staff,
     * parents and/or students across all three registries (staff_department_
     * assignments, chaplaincy_volunteers, school_leadership).
     */
    public function postTeamBulkAssign($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->bulkAssign($data));
    }

    /**
     * PUT /api/chaplaincy/team/member/{assignment_id} — update a member role.
     */
    public function putTeamMember($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('assignment_id is required');
        $data['assignment_id'] = (int) $id;
        return $this->handleResponse($this->api->updateMember($data));
    }

    /**
     * GET /api/chaplaincy/volunteers
     */
    public function getVolunteers($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listVolunteers($_GET ?? []));
    }

    /**
     * POST /api/chaplaincy/volunteers — register a volunteer.
     */
    public function postVolunteers($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->addVolunteer($data));
    }

    /**
     * DELETE /api/chaplaincy/volunteers/{id} — deactivate a volunteer.
     */
    public function deleteVolunteer($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Volunteer ID is required');
        return $this->handleResponse($this->api->deactivateVolunteer((int) $id));
    }

    /* ===== Phase B — spiritual programs, sessions, attendance ===== */

    /**
     * GET /api/chaplaincy/programs?include_inactive=1
     */
    public function getPrograms($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listPrograms($_GET ?? []));
    }

    /**
     * POST /api/chaplaincy/programs — create a catalog program.
     */
    public function postPrograms($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->createProgram($data));
    }

    /**
     * PUT /api/chaplaincy/programs/{id} — update a catalog program.
     */
    public function putPrograms($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Program ID is required');
        return $this->handleResponse($this->api->updateProgram((int) $id, $data));
    }

    /**
     * DELETE /api/chaplaincy/programs/{id} — delete (or deactivate if referenced).
     */
    public function deletePrograms($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Program ID is required');
        return $this->handleResponse($this->api->deleteProgram((int) $id));
    }

    /**
     * GET /api/chaplaincy/programs/next-date?program_id=N
     */
    public function getNextDate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->resolveNextDate($_GET ?? []));
    }

    /**
     * GET /api/chaplaincy/sessions
     */
    public function getSessions($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listSessions($_GET ?? []));
    }

    /**
     * POST /api/chaplaincy/sessions — schedule a program session.
     */
    public function postSessions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->createSession($data));
    }

    /**
     * PUT /api/chaplaincy/sessions/{id}

     */
    public function putSession($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Session ID is required');
        return $this->handleResponse($this->api->updateSession((int) $id, $data));
    }

    /**
     * DELETE /api/chaplaincy/sessions/{id}

     */
    public function deleteSession($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Session ID is required');
        return $this->handleResponse($this->api->deleteSession((int) $id));
    }

    /**
     * GET /api/chaplaincy/sessions/{id}/attendance
     */
    public function getSessionAttendance($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Session ID is required');
        return $this->handleResponse($this->api->getSessionAttendance((int) $id));
    }

    /**
     * PUT /api/chaplaincy/sessions/{id}/attendance — save per-person attendance.
     */
    public function putSessionAttendance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Session ID is required');
        return $this->handleResponse($this->api->saveAttendance((int) $id, $data));
    }

    /* ===== Phase C — recurring groups, spiritual profiles & pastoral care ===== */

    /**
     * GET /api/chaplaincy/groups — recurring spiritual groups (incl. {id} detail).
     */
    public function getGroups($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $list = $this->api->listGroups([]);
            foreach (($list['data'] ?? []) as $g) {
                if ((int) $g['id'] === (int) $id) {
                    return $this->success($g);
                }
            }
            return $this->badRequest('Group not found');
        }
        return $this->handleResponse($this->api->listGroups($_GET ?? []));
    }

    /**
     * POST /api/chaplaincy/groups — create a spiritual group.
     */
    public function postGroups($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->createGroup($data));
    }

    /**
     * GET /api/chaplaincy/groups/{id}/members — list a group's members.
     */
    public function getGroupsMembers($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Group ID is required');
        return $this->handleResponse($this->api->listGroupMembers((int) $id));
    }

    /**
     * POST /api/chaplaincy/groups/{id}/members — add a member to a group.
     */
    public function postGroupsMembers($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Group ID is required');
        return $this->handleResponse($this->api->addGroupMember((int) $id, $data));
    }

    /**
     * DELETE /api/chaplaincy/group-members/{memberId} — remove a member.
     */
    public function deleteGroupMembers($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Member ID is required');
        return $this->handleResponse($this->api->removeGroupMember((int) $id));
    }

    /**
     * GET /api/chaplaincy/groups/{id}/attendance?meeting_date=YYYY-MM-DD
     */
    public function getGroupsAttendance($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Group ID is required');
        $date = (string) ($_GET['meeting_date'] ?? date('Y-m-d'));
        return $this->handleResponse($this->api->getGroupAttendance((int) $id, $date));
    }

    /**
     * PUT /api/chaplaincy/groups/{id}/attendance — save group meeting attendance.
     */
    public function putGroupsAttendance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Group ID is required');
        return $this->handleResponse($this->api->saveGroupAttendance((int) $id, $data));
    }

    /**
     * GET /api/chaplaincy/spiritual-profiles/{studentId} — confidential profile.
     */
    public function getSpiritualProfiles($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Student ID is required');
        return $this->handleResponse($this->api->getSpiritualProfile((int) $id));
    }

    /**
     * PUT /api/chaplaincy/spiritual-profiles/{studentId} — update confidential profile.
     */
    public function putSpiritualProfiles($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Student ID is required');
        return $this->handleResponse($this->api->updateSpiritualProfile((int) $id, $data));
    }

    /**
     * POST /api/chaplaincy/spiritual-profiles/{studentId}/milestones — add milestone.
     */
    public function postSpiritualProfilesMilestones($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Student ID is required');
        return $this->handleResponse($this->api->addSpiritualMilestone((int) $id, $data));
    }

    /**
     * GET /api/chaplaincy/pastoral-visits — pastoral care log.
     */
    public function getPastoralVisits($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->listPastoralVisits($_GET ?? []));
    }

    /**
     * POST /api/chaplaincy/pastoral-visits — record a pastoral visit.
     */
    public function postPastoralVisits($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->createPastoralVisit($data));
    }

    /**
     * PUT /api/chaplaincy/pastoral-visits/{id} — update a pastoral visit.
     */
    public function putPastoralVisits($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        if (!$id) return $this->badRequest('Visit ID is required');
        return $this->handleResponse($this->api->updatePastoralVisit((int) $id, $data));
    }

    /**
     * GET /api/chaplaincy/dashboard-summary — aggregate spiritual KPIs.
     */
    public function getDashboardSummary($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardChaplaincy()) return $guard;
        return $this->handleResponse($this->api->getDashboardSummary());
    }

    private function guardChaplaincy(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['status'])) {
                $status = $result['status'];
                $code = $result['status_code'] ?? ($status === 'success' ? 200 : 400);
                $message = $result['message'] ?? ($status === 'success' ? 'Success' : 'Error');
                $data = $result['data'] ?? null;
                if ($status === 'success') {
                    return $this->success($data, $message);
                }
                return $this->badRequest($message, $data);
            }
            return $this->success($result);
        }
        return $this->success($result);
    }
}
