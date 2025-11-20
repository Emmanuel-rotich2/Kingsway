<?php
namespace App\API\Modules\Reports;

use App\API\Includes\BaseAPI;
use App\API\Modules\Reports\StudentReportManager;
use App\API\Modules\Reports\StaffReportManager;
use App\API\Modules\Reports\FinanceReportManager;
use App\API\Modules\Reports\AdmissionsReportManager;
use App\API\Modules\Reports\InventoryReportManager;
use App\API\Modules\Reports\MealReportManager;
use App\API\Modules\Reports\LogsReportManager;
use App\API\Modules\Reports\SystemReportManager;
use App\API\Modules\Reports\WorkflowReportManager;
use App\API\Modules\Reports\DisciplineReportManager;
use App\API\Modules\Reports\CommunicationReportManager;

class ReportsAPI extends BaseAPI
{
    private $studentReportManager;
    private $staffReportManager;
    private $financeReportManager;
    private $admissionsReportManager;
    private $inventoryReportManager;
    private $mealReportManager;
    private $logsReportManager;
    private $systemReportManager;
    private $workflowReportManager;
    private $disciplineReportManager;
    private $communicationReportManager;

    public function __construct()
    {
        parent::__construct('reports');
        $this->studentReportManager = new StudentReportManager();
        $this->staffReportManager = new StaffReportManager();
        $this->financeReportManager = new FinanceReportManager();
        $this->admissionsReportManager = new AdmissionsReportManager();
        $this->inventoryReportManager = new InventoryReportManager();
        $this->mealReportManager = new MealReportManager();
        $this->logsReportManager = new LogsReportManager();
        $this->systemReportManager = new SystemReportManager();
        $this->workflowReportManager = new WorkflowReportManager();
        $this->disciplineReportManager = new DisciplineReportManager();
        $this->communicationReportManager = new CommunicationReportManager();
    }

    // --- Admissions Reports ---
    public function admissionStats($params)
    {
        return $this->admissionsReportManager->getAdmissionStats($params);
    }
    public function conversionRates($params)
    {
        return $this->admissionsReportManager->getConversionRates($params);
    }
    public function alumniStats($params)
    {
        return $this->admissionsReportManager->getAlumniStats($params);
    }

    // --- Student Reports ---
    public function totalStudents($params)
    {
        return $this->studentReportManager->getTotalStudents($params);
    }
    public function enrollmentTrends($params)
    {
        return $this->studentReportManager->getEnrollmentTrends($params);
    }
    public function attendanceRates($params)
    {
        return $this->studentReportManager->getAttendanceRates($params);
    }
    public function promotionRates($params)
    {
        return $this->studentReportManager->getPromotionRates($params);
    }
    public function dropoutRates($params)
    {
        return $this->studentReportManager->getDropoutRates($params);
    }
    public function scoreDistributions($params)
    {
        return $this->studentReportManager->getScoreDistributions($params);
    }
    public function studentProgressionRates($params)
    {
        return $this->studentReportManager->getStudentProgressionRates($params);
    }
    public function examReports($params)
    {
        return $this->studentReportManager->getExamReports($params);
    }
    public function academicYearReports($params)
    {
        return $this->studentReportManager->getAcademicYearReports($params);
    }

    // --- Staff Reports ---
    public function totalStaff($params)
    {
        return $this->staffReportManager->getTotalStaff($params);
    }
    public function staffAttendanceRates($params)
    {
        return $this->staffReportManager->getStaffAttendanceRates($params);
    }
    public function activeStaffCount($params)
    {
        return $this->staffReportManager->getActiveStaffCount($params);
    }
    public function staffLoanStats($params)
    {
        return $this->staffReportManager->getStaffLoanStats($params);
    }
    public function payrollSummary($params)
    {
        return $this->staffReportManager->getPayrollSummary($params);
    }

    // --- Finance Reports ---
    public function feeSummary($params)
    {
        return $this->financeReportManager->getFeeSummary($params);
    }
    public function feePaymentTrends($params)
    {
        return $this->financeReportManager->getFeePaymentTrends($params);
    }
    public function discountStats($params)
    {
        return $this->financeReportManager->getDiscountStats($params);
    }
    public function arrearsStats($params)
    {
        return $this->financeReportManager->getArrearsStats($params);
    }
    public function financialTransactionsSummary($params)
    {
        return $this->financeReportManager->getFinancialTransactionsSummary($params);
    }
    public function bankTransactionsSummary($params)
    {
        return $this->financeReportManager->getBankTransactionsSummary($params);
    }
    public function feeStructureChangeLog($params)
    {
        return $this->financeReportManager->getFeeStructureChangeLog($params);
    }

    // --- Inventory Reports ---
    public function transportReport($params)
    {
        return $this->inventoryReportManager->getTransportReport($params);
    }
    public function inventoryStockLevels($params)
    {
        return $this->inventoryReportManager->getInventoryStockLevels($params);
    }
    public function inventoryUsageRates($params)
    {
        return $this->inventoryReportManager->getInventoryUsageRates($params);
    }
    public function requisitionsSummary($params)
    {
        return $this->inventoryReportManager->getRequisitionsSummary($params);
    }
    public function assetMaintenanceStats($params)
    {
        return $this->inventoryReportManager->getAssetMaintenanceStats($params);
    }
    public function inventoryAdjustmentLogs($params)
    {
        return $this->inventoryReportManager->getInventoryAdjustmentLogs($params);
    }

    // --- Meal Reports ---
    public function mealAllocations($params)
    {
        return $this->mealReportManager->getMealAllocations($params);
    }
    public function foodConsumptionTrends($params)
    {
        return $this->mealReportManager->getFoodConsumptionTrends($params);
    }

    // --- Logs Reports ---
    public function communicationLogs($params)
    {
        return $this->logsReportManager->getCommunicationLogs($params);
    }
    public function feeStructureLogs($params)
    {
        return $this->logsReportManager->getFeeStructureLogs($params);
    }
    public function inventoryLogs($params)
    {
        return $this->logsReportManager->getInventoryLogs($params);
    }
    public function systemLogs($params)
    {
        return $this->logsReportManager->getSystemLogs($params);
    }

    // --- System Reports ---
    public function loginActivity($params)
    {
        return $this->systemReportManager->getLoginActivity($params);
    }
    public function accountUnlocks($params)
    {
        return $this->systemReportManager->getAccountUnlocks($params);
    }
    public function auditTrailSummary($params)
    {
        return $this->systemReportManager->getAuditTrailSummary($params);
    }
    public function blockedDevicesStats($params)
    {
        return $this->systemReportManager->getBlockedDevicesStats($params);
    }

    // --- Workflow Reports ---
    public function workflowInstanceStats($params)
    {
        return $this->workflowReportManager->getWorkflowInstanceStats($params);
    }
    public function workflowStageTimes($params)
    {
        return $this->workflowReportManager->getWorkflowStageTimes($params);
    }
    public function workflowTransitionFrequencies($params)
    {
        return $this->workflowReportManager->getWorkflowTransitionFrequencies($params);
    }

    // --- Discipline Reports ---
    public function conductCasesStats($params)
    {
        return $this->disciplineReportManager->getConductCasesStats($params);
    }
    public function disciplinaryTrends($params)
    {
        return $this->disciplineReportManager->getDisciplinaryTrends($params);
    }

    // --- Communication Reports ---
    public function communicationsStats($params)
    {
        return $this->communicationReportManager->getCommunicationsStats($params);
    }
    public function parentPortalStats($params)
    {
        return $this->communicationReportManager->getParentPortalStats($params);
    }
    public function forumActivityStats($params)
    {
        return $this->communicationReportManager->getForumActivityStats($params);
    }
    public function announcementReach($params)
    {
        return $this->communicationReportManager->getAnnouncementReach($params);
    }
}
