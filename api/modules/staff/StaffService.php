<?php
namespace App\API\Modules\Staff;

use App\API\Includes\BaseAPI;
use Exception;
use function App\API\Includes\formatResponse;

/**
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
        require_once __DIR__ . '/StaffPayrollManager.php';
        require_once __DIR__ . '/StaffOnboardingManager.php';
        require_once __DIR__ . '/StaffPerformanceManager.php';
        require_once __DIR__ . '/StaffLeaveManager.php';
        require_once __DIR__ . '/StaffAssignmentManager.php';

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
        require_once __DIR__ . '/OnboardingWorkflow.php';
        require_once __DIR__ . '/EvaluationWorkflow.php';
        require_once __DIR__ . '/LeaveWorkflow.php';
        require_once __DIR__ . '/AssignmentWorkflow.php';

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
