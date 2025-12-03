<?php
namespace App\API\Controllers;

use App\API\Modules\activities\ActivitiesAPI;
use Exception;

/**
 * ActivitiesController - REST endpoints for all activity operations
 * Handles activities, categories, participants, resources, schedules, and workflows
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class ActivitiesController extends BaseController
{
    private ActivitiesAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new ActivitiesAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Activities API is running']);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/activities - List all activities
     * GET /api/activities/{id} - Get single activity
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->getActivity($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->listActivities($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities - Create new activity
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
        
        $result = $this->api->createActivity($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/{id} - Update activity
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Activity ID is required for update');
        }
        
        $result = $this->api->updateActivity($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/activities/{id} - Delete activity
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Activity ID is required for deletion');
        }
        
        $result = $this->api->deleteActivity($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Activity Operations
    // ========================================

    /**
     * GET /api/activities/upcoming/list
     */
    public function getUpcomingList($id = null, $data = [], $segments = [])
    {
        $limit = $data['limit'] ?? 10;
        $result = $this->api->getUpcomingActivities($limit);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/statistics/get
     */
    public function getStatisticsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getActivityStatistics($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Category Management
    // ========================================

    /**
     * GET /api/activities/categories/list
     */
    public function getCategoriesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listCategories($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/categories/get/{id}
     */
    public function getCategoriesGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->getCategory($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/categories/create
     */
    public function postCategoriesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createCategory($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/categories/update/{id}
     */
    public function putCategoriesUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->updateCategory($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/activities/categories/delete/{id}
     */
    public function deleteCategoriesDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->deleteCategory($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/categories/statistics
     */
    public function getCategoriesStatistics($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCategoryStatistics();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/categories/toggle-status
     */
    public function postCategoriesToggleStatus($id = null, $data = [], $segments = [])
    {
        $categoryId = $data['category_id'] ?? $id ?? null;
        
        if ($categoryId === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->toggleCategoryStatus($categoryId, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Participant Management
    // ========================================

    /**
     * GET /api/activities/participants/list
     */
    public function getParticipantsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listParticipants($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/participants/get/{id}
     */
    public function getParticipantsGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Participant ID is required');
        }
        
        $result = $this->api->getParticipant($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/participants/register
     */
    public function postParticipantsRegister($id = null, $data = [], $segments = [])
    {
        $result = $this->api->registerParticipant($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/participants/update-status
     */
    public function putParticipantsUpdateStatus($id = null, $data = [], $segments = [])
    {
        $participantId = $data['participant_id'] ?? $id ?? null;
        
        if ($participantId === null) {
            return $this->badRequest('Participant ID is required');
        }
        
        $result = $this->api->updateParticipantStatus($participantId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/participants/withdraw
     */
    public function postParticipantsWithdraw($id = null, $data = [], $segments = [])
    {
        $participantId = $data['participant_id'] ?? $id ?? null;
        $reason = $data['reason'] ?? null;
        
        if ($participantId === null) {
            return $this->badRequest('Participant ID is required');
        }
        
        $result = $this->api->withdrawParticipant($participantId, $reason, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/participants/student-history
     */
    public function getParticipantsStudentHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentActivityHistory($studentId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/participants/participation-stats
     */
    public function getParticipantsParticipationStats($id = null, $data = [], $segments = [])
    {
        $activityId = $data['activity_id'] ?? $id ?? null;
        
        if ($activityId === null) {
            return $this->badRequest('Activity ID is required');
        }
        
        $result = $this->api->getActivityParticipationStats($activityId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/participants/bulk-register
     */
    public function postParticipantsBulkRegister($id = null, $data = [], $segments = [])
    {
        $activityId = $data['activity_id'] ?? null;
        $studentIds = $data['student_ids'] ?? [];
        
        if ($activityId === null || empty($studentIds)) {
            return $this->badRequest('Activity ID and student IDs are required');
        }
        
        $result = $this->api->bulkRegisterParticipants($activityId, $studentIds, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Resource Management
    // ========================================

    /**
     * GET /api/activities/resources/list
     */
    public function getResourcesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listResources($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/resources/get/{id}
     */
    public function getResourcesGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Resource ID is required');
        }
        
        $result = $this->api->getResource($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/resources/add
     */
    public function postResourcesAdd($id = null, $data = [], $segments = [])
    {
        $result = $this->api->addResource($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/resources/update/{id}
     */
    public function putResourcesUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Resource ID is required');
        }
        
        $result = $this->api->updateResource($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/activities/resources/delete/{id}
     */
    public function deleteResourcesDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Resource ID is required');
        }
        
        $result = $this->api->deleteResource($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/resources/by-activity
     */
    public function getResourcesByActivity($id = null, $data = [], $segments = [])
    {
        $activityId = $data['activity_id'] ?? $id ?? null;
        
        if ($activityId === null) {
            return $this->badRequest('Activity ID is required');
        }
        
        $result = $this->api->getActivityResources($activityId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/resources/check-availability
     */
    public function getResourcesCheckAvailability($id = null, $data = [], $segments = [])
    {
        $resourceType = $data['resource_type'] ?? null;
        $startDate = $data['start_date'] ?? null;
        $endDate = $data['end_date'] ?? null;
        
        if (!$resourceType || !$startDate || !$endDate) {
            return $this->badRequest('Resource type, start date, and end date are required');
        }
        
        $result = $this->api->checkResourceAvailability($resourceType, $startDate, $endDate);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/resources/statistics
     */
    public function getResourcesStatistics($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getResourceStatistics($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/resources/update-status
     */
    public function putResourcesUpdateStatus($id = null, $data = [], $segments = [])
    {
        $resourceId = $data['resource_id'] ?? $id ?? null;
        $status = $data['status'] ?? null;
        
        if ($resourceId === null || $status === null) {
            return $this->badRequest('Resource ID and status are required');
        }
        
        $result = $this->api->updateResourceStatus($resourceId, $status, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Schedule Management
    // ========================================

    /**
     * GET /api/activities/schedules/list
     */
    public function getSchedulesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listSchedules($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/schedules/get/{id}
     */
    public function getSchedulesGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Schedule ID is required');
        }
        
        $result = $this->api->getSchedule($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/schedules/create
     */
    public function postSchedulesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createSchedule($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/activities/schedules/update/{id}
     */
    public function putSchedulesUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Schedule ID is required');
        }
        
        $result = $this->api->updateSchedule($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/activities/schedules/delete/{id}
     */
    public function deleteSchedulesDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Schedule ID is required');
        }
        
        $result = $this->api->deleteSchedule($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/schedules/by-activity
     */
    public function getSchedulesByActivity($id = null, $data = [], $segments = [])
    {
        $activityId = $data['activity_id'] ?? $id ?? null;
        
        if ($activityId === null) {
            return $this->badRequest('Activity ID is required');
        }
        
        $result = $this->api->getActivitySchedules($activityId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/schedules/weekly-timetable
     */
    public function getSchedulesWeeklyTimetable($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getWeeklyTimetable($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/activities/schedules/venue-availability
     */
    public function getSchedulesVenueAvailability($id = null, $data = [], $segments = [])
    {
        $dayOfWeek = $data['day_of_week'] ?? null;
        $startTime = $data['start_time'] ?? null;
        $endTime = $data['end_time'] ?? null;
        
        if ($dayOfWeek === null || !$startTime || !$endTime) {
            return $this->badRequest('Day of week, start time, and end time are required');
        }
        
        $result = $this->api->getVenueAvailability($dayOfWeek, $startTime, $endTime);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/schedules/bulk-create
     */
    public function postSchedulesBulkCreate($id = null, $data = [], $segments = [])
    {
        $activityId = $data['activity_id'] ?? null;
        $schedules = $data['schedules'] ?? [];
        
        if ($activityId === null || empty($schedules)) {
            return $this->badRequest('Activity ID and schedules array are required');
        }
        
        $result = $this->api->bulkCreateSchedules($activityId, $schedules, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Registration Workflow
    // ========================================

    /**
     * POST /api/activities/registration/initiate
     */
    public function postRegistrationInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateRegistration($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/registration/review
     */
    public function postRegistrationReview($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->reviewApplication($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/registration/approve
     */
    public function postRegistrationApprove($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveRegistration($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/registration/reject
     */
    public function postRegistrationReject($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        $reason = $data['reason'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->rejectRegistration($workflowId, $reason, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/registration/confirm
     */
    public function postRegistrationConfirm($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->confirmParticipation($workflowId, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/registration/complete
     */
    public function postRegistrationComplete($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->completeParticipation($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Planning Workflow
    // ========================================

    /**
     * POST /api/activities/planning/propose
     */
    public function postPlanningPropose($id = null, $data = [], $segments = [])
    {
        $result = $this->api->proposeActivity($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/planning/approve-budget
     */
    public function postPlanningApproveBudget($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveBudget($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/planning/schedule
     */
    public function postPlanningSchedule($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        $schedules = $data['schedules'] ?? [];
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->scheduleActivity($workflowId, $schedules, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/planning/prepare-resources
     */
    public function postPlanningPrepareResources($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        $resources = $data['resources'] ?? [];
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->prepareResources($workflowId, $resources, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/planning/execute
     */
    public function postPlanningExecute($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->executeActivity($workflowId, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/planning/review
     */
    public function postPlanningReview($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->reviewActivity($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Competition Workflow
    // ========================================

    /**
     * POST /api/activities/competition/register
     */
    public function postCompetitionRegister($id = null, $data = [], $segments = [])
    {
        $result = $this->api->registerForCompetition($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/competition/prepare-team
     */
    public function postCompetitionPrepareTeam($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->prepareTeam($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/competition/record-participation
     */
    public function postCompetitionRecordParticipation($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->recordParticipation($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/competition/report-results
     */
    public function postCompetitionReportResults($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->reportResults($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/competition/recognize-achievements
     */
    public function postCompetitionRecognizeAchievements($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->recognizeAchievements($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 10: Evaluation Workflow
    // ========================================

    /**
     * POST /api/activities/evaluation/initiate
     */
    public function postEvaluationInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateEvaluation($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/evaluation/submit-assessment
     */
    public function postEvaluationSubmitAssessment($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->submitAssessment($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/evaluation/verify-assessment
     */
    public function postEvaluationVerifyAssessment($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->verifyAssessment($workflowId, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/evaluation/approve
     */
    public function postEvaluationApprove($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveEvaluation($workflowId, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/activities/evaluation/publish-results
     */
    public function postEvaluationPublishResults($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        
        if ($workflowId === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->publishEvaluationResults($workflowId, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 11: Helper Methods
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
        if (is_array($result)) {
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

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        if (isset($this->user['id']) && $this->user['id']) {
            return $this->user['id'];
        }
        // Fallback for tests or missing user context
        error_log('[ActivitiesController] No user context found, using default user ID 1');
        return 1;
    }
}
