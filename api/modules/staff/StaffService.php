<?php
namespace App\API\Modules\staff;

use App\API\Includes\BaseAPI;
use App\API\Modules\staff\StaffPayrollManager;
use App\API\Modules\staff\StaffOnboardingManager;
use App\API\Modules\staff\StaffPerformanceManager;
use App\API\Modules\staff\StaffLeaveManager;
use App\API\Modules\staff\StaffAssignmentManager;
use App\API\Modules\staff\OnboardingWorkflow;
use App\API\Modules\staff\EvaluationWorkflow;
use App\API\Modules\staff\LeaveWorkflow;
use App\API\Modules\staff\AssignmentWorkflow;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Service Integration Class
 * Staff Service Integration Class
 * 
 * Central integration point for all staff-related operations
 * Instantiates and coordinates all staff managers and workflows
 */
class StaffService extends BaseAPI
{
    private $payrollManager;
    private $onboardingManager;
    private $performanceManager;
    private $leaveManager;
    private $assignmentManager;

    private $onboardingWorkflow;
    private $evaluationWorkflow;
    private $leaveWorkflow;
    private $assignmentWorkflow;

    public function __construct()
    {
        parent::__construct();
        $this->initializeManagers();
        $this->initializeWorkflows();
    }

    /**
     * Initialize all managers
     */
    private function initializeManagers()
    {
        $this->payrollManager = new StaffPayrollManager();
        $this->onboardingManager = new StaffOnboardingManager();
        $this->performanceManager = new StaffPerformanceManager();
        $this->leaveManager = new StaffLeaveManager();
        $this->assignmentManager = new StaffAssignmentManager();
    }

    /**
     * Initialize all workflows
     */
    private function initializeWorkflows()
    {
        $this->onboardingWorkflow = new OnboardingWorkflow('staff_onboarding');
        $this->evaluationWorkflow = new EvaluationWorkflow('staff_evaluation');
        $this->leaveWorkflow = new LeaveWorkflow('staff_leave');
        $this->assignmentWorkflow = new AssignmentWorkflow('staff_assignment');
    }

    // Get manager instance
    public function getPayrollManager()
    {
        return $this->payrollManager;
    }
    public function getOnboardingManager()
    {
        return $this->onboardingManager;
    }
    public function getPerformanceManager()
    {
        return $this->performanceManager;
    }
    public function getLeaveManager()
    {
        return $this->leaveManager;
    }
    public function getAssignmentManager()
    {
        return $this->assignmentManager;
    }

    // Get workflow instance
    public function getOnboardingWorkflow()
    {
        return $this->onboardingWorkflow;
    }
    public function getEvaluationWorkflow()
    {
        return $this->evaluationWorkflow;
    }
    public function getLeaveWorkflow()
    {
        return $this->leaveWorkflow;
    }
    public function getAssignmentWorkflow()
    {
        return $this->assignmentWorkflow;
    }
}
