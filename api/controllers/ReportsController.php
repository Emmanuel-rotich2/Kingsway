<?php
namespace App\API\Controllers;

use App\API\Modules\reports\ReportsAPI;
use Exception;

/**
 * ReportsController - REST endpoints for all reporting operations
 * Handles academic reports, attendance reports, fee reports, transport reports,
 * dashboard statistics, audit reports, and custom report generation
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class ReportsController extends BaseController
{
    private ReportsAPI $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\reports\ReportsAPI');
    }

    public function index()
    {
        return $this->success(['message' => 'Reports API is running']);
    }

    // --- Governed enterprise analytics ---
    public function getCatalogue($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardAnalytics('analytics_catalogue_view')) return $guard;
        try {
            return $this->success(
                $this->api->governedCatalogue(array_merge($_GET, $data ?? []), $this->user),
                'Governed report catalogue loaded'
            );
        } catch (\Throwable $e) {
            return $this->analyticsError($e);
        }
    }

    public function getDefinition($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardAnalytics('analytics_catalogue_view')) return $guard;
        $code = $id ?? ($data['code'] ?? null);
        if (!$code) return $this->badRequest('Report code is required');
        try {
            return $this->success(
                $this->api->governedDefinition((string) $code, $this->user),
                'Governed report definition loaded'
            );
        } catch (\Throwable $e) {
            return $this->analyticsError($e);
        }
    }

    public function getMetrics($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardAnalytics('analytics_catalogue_view')) return $guard;
        try {
            return $this->success(
                $this->api->governedMetrics(array_merge($_GET, $data ?? []), $this->user),
                'Governed metric definitions loaded'
            );
        } catch (\Throwable $e) {
            return $this->analyticsError($e);
        }
    }

    public function postExecute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardAnalytics('analytics_report_execute')) return $guard;
        $code = $id ?? ($data['report_code'] ?? null);
        if (!$code) return $this->badRequest('Report code is required');
        $params = isset($data['filters']) && is_array($data['filters'])
            ? $data['filters']
            : $data;
        unset($params['report_code']);
        try {
            return $this->success(
                $this->api->executeGoverned(
                    (string) $code,
                    $params,
                    $this->user,
                    (string) ($_SERVER['REQUEST_ID'] ?? $this->requestId)
                ),
                'Governed report generated'
            );
        } catch (\Throwable $e) {
            return $this->analyticsError($e);
        }
    }

    public function getRunStatus($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardAnalytics('analytics_catalogue_view')) return $guard;
        if (!$id || !is_numeric($id) || (int) $id < 1) {
            return $this->badRequest('Valid report run ID is required');
        }
        try {
            return $this->success(
                $this->api->governedRunStatus((int) $id, $this->user),
                'Report run status loaded'
            );
        } catch (\Throwable $e) {
            return $this->analyticsError($e);
        }
    }
    // --- Enrollment Summary (alias for Director dashboard) ---
    public function getEnrollmentSummary($id = null, $data = [], $segments = [])
    {
        $params = array_merge($_GET, $data ?? []);
        $params['class_id'] = isset($params['class_id']) ? (int)$params['class_id'] : null;
        $params['stream_id'] = isset($params['stream_id']) ? (int)$params['stream_id'] : null;
        $params['year'] = isset($params['year']) && preg_match('/^\d{4}$/', $params['year']) ? $params['year'] : null;
        return $this->handleResponse([
            'by_class' => $this->api->totalStudents($params),
            'trends'   => $this->api->enrollmentTrends($params),
        ]);
    }

    // --- Academic Performance (alias for Director dashboard) ---
    public function getAcademicPerformance($id = null, $data = [], $segments = [])
    {
        $params = array_merge($_GET, $data ?? []);
        $params['class_id'] = isset($params['class_id']) ? (int)$params['class_id'] : null;
        $params['term_id'] = isset($params['term_id']) ? (int)$params['term_id'] : null;
        return $this->handleResponse($this->api->examReports($params));
    }

    // --- Admissions Reports ---
    public function getAdmissionStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->admissionStats($data));
    }
    public function getConversionRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->conversionRates($data));
    }
    public function getAlumniStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->alumniStats($data));
    }

    // --- Student Reports ---
    public function getTotalStudents($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->totalStudents($data));
    }
    public function getEnrollmentTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->enrollmentTrends($data));
    }
    public function getAttendanceRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->attendanceRates($data));
    }
    public function getPromotionRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->promotionRates($data));
    }
    public function getDropoutRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->dropoutRates($data));
    }
    public function getScoreDistributions($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->scoreDistributions($data));
    }
    public function getStudentProgressionRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->studentProgressionRates($data));
    }
    public function getExamReports($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->examReports($data));
    }
    public function getAcademicYearReports($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->academicYearReports($data));
    }

    // --- Staff Reports ---
    public function getTotalStaff($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->totalStaff($data));
    }
    public function getStaffAttendanceRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->staffAttendanceRates($data));
    }
    public function getActiveStaffCount($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->activeStaffCount($data));
    }
    public function getStaffLoanStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->staffLoanStats($data));
    }
    public function getPayrollSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->payrollSummary($data));
    }

    // --- Finance Reports ---
    public function getFeeSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feeSummary($data));
    }
    public function getFeePaymentTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feePaymentTrends($data));
    }
    public function getDiscountStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->discountStats($data));
    }
    public function getArrearsStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->arrearsStats($data));
    }
    public function getFinancialTransactionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->financialTransactionsSummary($data));
    }
    public function getBankTransactionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->bankTransactionsSummary($data));
    }
    public function getFeeStructureChangeLog($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feeStructureChangeLog($data));
    }

    // --- Inventory Reports ---
    public function getTransportReport($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->transportReport($data));
    }
    public function getInventoryStockLevels($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryStockLevels($data));
    }
    public function getInventoryUsageRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryUsageRates($data));
    }
    public function getRequisitionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->requisitionsSummary($data));
    }
    public function getAssetMaintenanceStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->assetMaintenanceStats($data));
    }
    public function getInventoryAdjustmentLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryAdjustmentLogs($data));
    }

    // --- Meal Reports ---
    public function getMealAllocations($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->mealAllocations($data));
    }
    public function getFoodConsumptionTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->foodConsumptionTrends($data));
    }

    // --- Logs Reports ---
    public function getCommunicationLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->communicationLogs($data));
    }
    public function getFeeStructureLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feeStructureLogs($data));
    }
    public function getInventoryLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryLogs($data));
    }
    public function getSystemLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->systemLogs($data));
    }

    // --- System Reports ---
    public function getLoginActivity($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->loginActivity($data));
    }
    public function getAccountUnlocks($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->accountUnlocks($data));
    }
    public function getAuditTrailSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->auditTrailSummary($data));
    }
    public function getBlockedDevicesStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->blockedDevicesStats($data));
    }

    // --- Workflow Reports ---
    public function getWorkflowInstanceStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowInstanceStats($data));
    }
    public function getWorkflowStageTimes($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowStageTimes($data));
    }
    public function getWorkflowTransitionFrequencies($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowTransitionFrequencies($data));
    }

    // --- Discipline Reports ---
    public function getConductCasesStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->conductCasesStats($data));
    }
    public function getDisciplinaryTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->disciplinaryTrends($data));
    }

    // --- Communication Reports ---
    public function getCommunicationsStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->communicationsStats($data));
    }
    public function getParentPortalStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->parentPortalStats($data));
    }
    public function getForumActivityStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->forumActivityStats($data));
    }
    public function getAnnouncementReach($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->announcementReach($data));
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

    private function guardAnalytics(string $permission): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny([$permission])) {
            return $this->forbidden('You do not have permission to perform this reporting action');
        }
        return null;
    }

    private function analyticsError(\Throwable $e): array
    {
        $code = (int) $e->getCode();
        \App\API\Services\Logger::legacyError('[ReportsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if ($code === 401) return $this->unauthorized($e->getMessage());
        if ($code === 403) return $this->forbidden($e->getMessage());
        if ($code === 404) return $this->notFound($e->getMessage());
        if ($code === 409) return $this->conflict($e->getMessage());
        if ($code === 422) return $this->unprocessable($e->getMessage());
        return $this->serverError('The report could not be generated.');
    }

}
