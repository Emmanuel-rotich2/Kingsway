<?php
namespace App\API\Modules\activities;

use App\API\Modules\activities\ActivitiesManager;
use App\API\Modules\activities\CategoriesManager;
use App\API\Modules\activities\ParticipantsManager;
use App\API\Modules\activities\ResourcesManager;
use App\API\Modules\activities\SchedulesManager;

use App\API\Includes\BaseAPI;
use App\API\Modules\activities\workflows\ActivityRegistrationWorkflow;
use App\API\Modules\activities\workflows\ActivityPlanningWorkflow;
use App\API\Modules\activities\workflows\CompetitionWorkflow;
use App\API\Modules\activities\workflows\PerformanceEvaluationWorkflow;
use PDO;
use Exception;

/**
 * ActivitiesAPI - Central coordinator for all activity operations
 * Orchestrates managers and workflows for comprehensive activity management
 */
class ActivitiesAPI extends BaseAPI
{
    private $activitiesManager;
    private $categoriesManager;
    private $participantsManager;
    private $resourcesManager;
    private $schedulesManager;
    private $registrationWorkflow;
    private $planningWorkflow;
    private $competitionWorkflow;
    private $evaluationWorkflow;

    public function __construct()
    {
        parent::__construct('activities');
        
        $this->activitiesManager = new ActivitiesManager();
        $this->categoriesManager = new CategoriesManager();
        $this->participantsManager = new ParticipantsManager();
        $this->resourcesManager = new ResourcesManager();
        $this->schedulesManager = new SchedulesManager();
        $this->registrationWorkflow = new ActivityRegistrationWorkflow();
        $this->planningWorkflow = new ActivityPlanningWorkflow();
        $this->competitionWorkflow = new CompetitionWorkflow();
        $this->evaluationWorkflow = new PerformanceEvaluationWorkflow();
    }

    public function listActivities($params = [])
    {
        return $this->activitiesManager->listActivities($params);
    }

    public function getActivity($id)
    {
        return $this->activitiesManager->getActivity($id);
    }

    public function createActivity($data, $userId)
    {
        return $this->activitiesManager->createActivity($data, $userId);
    }

    public function updateActivity($id, $data, $userId)
    {
        return $this->activitiesManager->updateActivity($id, $data, $userId);
    }

    public function deleteActivity($id, $userId)
    {
        return $this->activitiesManager->deleteActivity($id, $userId);
    }

    public function getUpcomingActivities($limit = 10)
    {
        return $this->activitiesManager->getUpcomingActivities($limit);
    }

    public function getActivityStatistics($params = [])
    {
        return $this->activitiesManager->getActivityStatistics($params);
    }

    public function listCategories($params = [])
    {
        return $this->categoriesManager->listCategories($params);
    }

    public function getCategory($id)
    {
        return $this->categoriesManager->getCategory($id);
    }

    public function createCategory($data, $userId)
    {
        return $this->categoriesManager->createCategory($data, $userId);
    }

    public function updateCategory($id, $data, $userId)
    {
        return $this->categoriesManager->updateCategory($id, $data, $userId);
    }

    public function deleteCategory($id, $userId)
    {
        return $this->categoriesManager->deleteCategory($id, $userId);
    }

    public function getCategoryStatistics()
    {
        return $this->categoriesManager->getCategoryStatistics();
    }

    public function toggleCategoryStatus($id, $userId)
    {
        return $this->categoriesManager->toggleCategoryStatus($id, $userId);
    }

    public function listParticipants($params = [])
    {
        return $this->participantsManager->listParticipants($params);
    }

    public function getParticipant($id)
    {
        return $this->participantsManager->getParticipant($id);
    }

    public function registerParticipant($data, $userId)
    {
        return $this->participantsManager->registerParticipant($data, $userId);
    }

    public function updateParticipantStatus($id, $data, $userId)
    {
        return $this->participantsManager->updateParticipantStatus($id, $data, $userId);
    }

    public function withdrawParticipant($id, $reason, $userId)
    {
        return $this->participantsManager->withdrawParticipant($id, $reason, $userId);
    }

    public function getStudentActivityHistory($studentId)
    {
        return $this->participantsManager->getStudentActivityHistory($studentId);
    }

    public function getActivityParticipationStats($activityId)
    {
        return $this->participantsManager->getActivityParticipationStats($activityId);
    }

    public function bulkRegisterParticipants($activityId, $studentIds, $userId)
    {
        return $this->participantsManager->bulkRegisterParticipants($activityId, $studentIds, $userId);
    }

    public function listResources($params = [])
    {
        return $this->resourcesManager->listResources($params);
    }

    public function getResource($id)
    {
        return $this->resourcesManager->getResource($id);
    }

    public function addResource($data, $userId)
    {
        return $this->resourcesManager->addResource($data, $userId);
    }

    public function updateResource($id, $data, $userId)
    {
        return $this->resourcesManager->updateResource($id, $data, $userId);
    }

    public function deleteResource($id, $userId)
    {
        return $this->resourcesManager->deleteResource($id, $userId);
    }

    public function getActivityResources($activityId)
    {
        return $this->resourcesManager->getActivityResources($activityId);
    }

    public function checkResourceAvailability($resourceType, $startDate, $endDate)
    {
        return $this->resourcesManager->checkResourceAvailability($resourceType, $startDate, $endDate);
    }

    public function getResourceStatistics($params = [])
    {
        return $this->resourcesManager->getResourceStatistics($params);
    }

    public function updateResourceStatus($id, $status, $userId)
    {
        return $this->resourcesManager->updateResourceStatus($id, $status, $userId);
    }

    public function listSchedules($params = [])
    {
        return $this->schedulesManager->listSchedules($params);
    }

    public function getSchedule($id)
    {
        return $this->schedulesManager->getSchedule($id);
    }

    public function createSchedule($data, $userId)
    {
        return $this->schedulesManager->createSchedule($data, $userId);
    }

    public function updateSchedule($id, $data, $userId)
    {
        return $this->schedulesManager->updateSchedule($id, $data, $userId);
    }

    public function deleteSchedule($id, $userId)
    {
        return $this->schedulesManager->deleteSchedule($id, $userId);
    }

    public function getActivitySchedules($activityId)
    {
        return $this->schedulesManager->getActivitySchedules($activityId);
    }

    public function getWeeklyTimetable($params = [])
    {
        return $this->schedulesManager->getWeeklyTimetable($params);
    }

    public function getVenueAvailability($dayOfWeek, $startTime, $endTime)
    {
        return $this->schedulesManager->getVenueAvailability($dayOfWeek, $startTime, $endTime);
    }

    public function bulkCreateSchedules($activityId, $schedules, $userId)
    {
        return $this->schedulesManager->bulkCreateSchedules($activityId, $schedules, $userId);
    }

    public function initiateRegistration($data, $userId)
    {
        return $this->registrationWorkflow->initiateRegistration($data, $userId);
    }

    public function reviewApplication($workflowId, $data, $userId)
    {
        return $this->registrationWorkflow->reviewApplication($workflowId, $data, $userId);
    }

    public function approveRegistration($workflowId, $data, $userId)
    {
        return $this->registrationWorkflow->approveRegistration($workflowId, $data, $userId);
    }

    public function rejectRegistration($workflowId, $reason, $userId)
    {
        return $this->registrationWorkflow->rejectRegistration($workflowId, $reason, $userId);
    }

    public function confirmParticipation($workflowId, $userId)
    {
        return $this->registrationWorkflow->confirmParticipation($workflowId, $userId);
    }

    public function completeParticipation($workflowId, $data, $userId)
    {
        return $this->registrationWorkflow->completeParticipation($workflowId, $data, $userId);
    }

    public function proposeActivity($data, $userId)
    {
        return $this->planningWorkflow->proposeActivity($data, $userId);
    }

    public function approveBudget($workflowId, $data, $userId)
    {
        return $this->planningWorkflow->approveBudget($workflowId, $data, $userId);
    }

    public function scheduleActivity($workflowId, $schedules, $userId)
    {
        return $this->planningWorkflow->scheduleActivity($workflowId, $schedules, $userId);
    }

    public function prepareResources($workflowId, $resources, $userId)
    {
        return $this->planningWorkflow->prepareResources($workflowId, $resources, $userId);
    }

    public function executeActivity($workflowId, $userId)
    {
        return $this->planningWorkflow->executeActivity($workflowId, $userId);
    }

    public function reviewActivity($workflowId, $data, $userId)
    {
        return $this->planningWorkflow->reviewActivity($workflowId, $data, $userId);
    }

    public function registerForCompetition($data, $userId)
    {
        return $this->competitionWorkflow->registerForCompetition($data, $userId);
    }

    public function prepareTeam($workflowId, $data, $userId)
    {
        return $this->competitionWorkflow->prepareTeam($workflowId, $data, $userId);
    }

    public function recordParticipation($workflowId, $data, $userId)
    {
        return $this->competitionWorkflow->recordParticipation($workflowId, $data, $userId);
    }

    public function reportResults($workflowId, $data, $userId)
    {
        return $this->competitionWorkflow->reportResults($workflowId, $data, $userId);
    }

    public function recognizeAchievements($workflowId, $data, $userId)
    {
        return $this->competitionWorkflow->recognizeAchievements($workflowId, $data, $userId);
    }

    public function initiateEvaluation($data, $userId)
    {
        return $this->evaluationWorkflow->initiateEvaluation($data, $userId);
    }

    public function submitAssessment($workflowId, $data, $userId)
    {
        return $this->evaluationWorkflow->submitAssessment($workflowId, $data, $userId);
    }

    public function verifyAssessment($workflowId, $data, $userId)
    {
        return $this->evaluationWorkflow->verifyAssessment($workflowId, $data, $userId);
    }

    public function approveEvaluation($workflowId, $userId)
    {
        return $this->evaluationWorkflow->approveEvaluation($workflowId, $userId);
    }

    public function publishEvaluationResults($workflowId, $userId)
    {
        return $this->evaluationWorkflow->publishResults($workflowId, $userId);
    }

    public function list($params = [])
    {
        return $this->listActivities($params);
    }

    public function get($id)
    {
        return $this->getActivity($id);
    }

    public function create($data)
    {
        $userId = $this->getCurrentUserId();
        return $this->createActivity($data, $userId);
    }

    public function update($id, $data)
    {
        $userId = $this->getCurrentUserId();
        return $this->updateActivity($id, $data, $userId);
    }

    public function delete($id)
    {
        $userId = $this->getCurrentUserId();
        return $this->deleteActivity($id, $userId);
    }

    public function getUpcoming()
    {
        return $this->getUpcomingActivities(10);
    }

    public function getStudentActivities($studentId)
    {
        return $this->getStudentActivityHistory($studentId);
    }
}
