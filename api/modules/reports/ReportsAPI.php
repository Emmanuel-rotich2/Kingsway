<?php
namespace App\API\Modules\reports;

use App\API\Includes\BaseAPI;
use App\API\Modules\reports\StudentReportManager;
use App\API\Modules\reports\StaffReportManager;
use App\API\Modules\reports\FinanceReportManager;
use App\API\Modules\reports\AdmissionsReportManager;
use App\API\Modules\reports\InventoryReportManager;
use App\API\Modules\reports\MealReportManager;
use App\API\Modules\reports\LogsReportManager;
use App\API\Modules\reports\SystemReportManager;
use App\API\Modules\reports\WorkflowReportManager;
use App\API\Modules\reports\DisciplineReportManager;
use App\API\Modules\reports\CommunicationReportManager;
use App\API\Services\AnalyticsReportRegistryService;
use App\API\Services\AnalyticsReportScopeService;
use App\API\Services\GovernedReportExecutor;
use App\API\Services\GovernedReportResponseBuilder;
use RuntimeException;

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
    private AnalyticsReportRegistryService $analyticsRegistry;
    private AnalyticsReportScopeService $analyticsScope;
    private GovernedReportExecutor $governedExecutor;
    private GovernedReportResponseBuilder $governedResponse;

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
        $this->analyticsRegistry = new AnalyticsReportRegistryService($this->db);
        $this->analyticsScope = new AnalyticsReportScopeService($this->db);
        $this->governedResponse = new GovernedReportResponseBuilder();
        $this->governedExecutor = new GovernedReportExecutor([
            'student.total_students' => [$this->studentReportManager, 'getTotalStudents'],
            'student.enrollment_trends' => [$this->studentReportManager, 'getEnrollmentTrends'],
            'student.exam_reports' => [$this->studentReportManager, 'getExamReports'],
            'student.score_distributions' => [$this->studentReportManager, 'getScoreDistributions'],
            'student.attendance_rates' => [$this->studentReportManager, 'getAttendanceRates'],
            'admissions.stats' => [$this->admissionsReportManager, 'getAdmissionStats'],
            'admissions.conversion_rates' => [$this->admissionsReportManager, 'getConversionRates'],
            'finance.fee_summary' => [$this->financeReportManager, 'getFeeSummary'],
            'finance.arrears' => [$this->financeReportManager, 'getArrearsStats'],
            'staff.attendance_rates' => [$this->staffReportManager, 'getStaffAttendanceRates'],
            'inventory.stock_levels' => [$this->inventoryReportManager, 'getInventoryStockLevels'],
            'meal.food_consumption_trends' => [$this->mealReportManager, 'getFoodConsumptionTrends'],
            'discipline.trends' => [$this->disciplineReportManager, 'getDisciplinaryTrends'],
            'communication.stats' => [$this->communicationReportManager, 'getCommunicationsStats'],
            'system.audit_trail' => [$this->systemReportManager, 'getAuditTrailSummary'],
        ]);
    }

    // --- Governed enterprise analytics ---
    public function governedCatalogue(array $params, array $user): array
    {
        return array_map(
            fn (array $definition): array => $this->analyticsRegistry->publicDefinition($definition),
            $this->analyticsRegistry->listCatalogue($user, $params)
        );
    }

    public function governedDefinition(string $code, array $user): array
    {
        $definition = $this->analyticsRegistry->accessibleDefinition($code, $user, 'view');
        // Sanitize the definition first, then attach the sanitized metric
        // contract: publicDefinition() is an allowlist and would otherwise
        // drop the nested metrics list.
        $public = $this->analyticsRegistry->publicDefinition($definition);
        $public['metrics'] = array_map(
            fn (array $metric): array => $this->analyticsRegistry->publicMetric($metric),
            $this->analyticsRegistry->metricsForReport((int) $definition['id'])
        );

        return $public;
    }

    public function governedMetrics(array $params, array $user): array
    {
        $domain = isset($params['domain']) ? (string) $params['domain'] : null;

        return array_map(
            fn (array $metric): array => $this->analyticsRegistry->publicMetric($metric),
            $this->analyticsRegistry->listMetrics($user, $domain)
        );
    }

    public function executeGoverned(
        string $code,
        array $params,
        array $user,
        string $requestId
    ): array {
        $started = microtime(true);
        $definition = $this->analyticsRegistry->accessibleDefinition($code, $user, 'execute');
        if (!$this->governedExecutor->supports((string) $definition['execution_key'])) {
            throw new RuntimeException('This report definition has no approved executor.', 409);
        }

        $scopeResult = $this->analyticsScope->apply($definition, $user, $params);
        $metrics = $this->analyticsRegistry->metricsForReport((int) $definition['id']);
        $metricVersions = array_map(static function (array $metric): array {
            return ['code' => $metric['code'], 'version' => (int) $metric['version']];
        }, $metrics);
        $run = $this->analyticsRegistry->startRun(
            $definition,
            $user,
            $scopeResult['parameters'],
            $scopeResult['scope'],
            $metricVersions,
            $requestId
        );

        try {
            $payload = $this->governedExecutor->execute(
                (string) $definition['execution_key'],
                $scopeResult['parameters']
            );
            $response = $this->governedResponse->build(
                $definition,
                $run,
                $scopeResult['parameters'],
                $scopeResult['scope'],
                $metrics,
                $payload,
                $scopeResult['warnings'] ?? []
            );
            $duration = (int) round((microtime(true) - $started) * 1000);
            $this->analyticsRegistry->completeRun(
                (int) $run['id'],
                (int) $response['row_count'],
                (array) $response['summary'],
                (array) $response['warnings'],
                $duration
            );
            $response['run']['status'] = 'completed';
            $response['run']['row_count'] = (int) $response['row_count'];
            $response['run']['duration_ms'] = $duration;
            $response['run']['completed_at'] = gmdate('c');
            return $response;
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $started) * 1000);
            $failureCode = $e instanceof RuntimeException && $e->getCode() > 0
                ? 'REPORT_' . (string) $e->getCode()
                : 'REPORT_EXECUTION_FAILED';
            $this->analyticsRegistry->failRun(
                (int) $run['id'],
                $failureCode,
                'The report could not be generated.',
                $duration
            );
            throw $e;
        }
    }

    public function governedRunStatus(int $runId, array $user): array
    {
        return $this->analyticsRegistry->runStatus($runId, $user);
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
