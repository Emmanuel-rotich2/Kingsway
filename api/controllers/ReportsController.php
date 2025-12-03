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
        $this->api = new ReportsAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Reports API is running']);
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
    public function discountStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->discountStats($data));
    }
    public function arrearsStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->arrearsStats($data));
    }
    public function financialTransactionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->financialTransactionsSummary($data));
    }
    public function bankTransactionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->bankTransactionsSummary($data));
    }
    public function feeStructureChangeLog($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feeStructureChangeLog($data));
    }

    // --- Inventory Reports ---
    public function transportReport($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->transportReport($data));
    }
    public function inventoryStockLevels($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryStockLevels($data));
    }
    public function inventoryUsageRates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryUsageRates($data));
    }
    public function requisitionsSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->requisitionsSummary($data));
    }
    public function assetMaintenanceStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->assetMaintenanceStats($data));
    }
    public function inventoryAdjustmentLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryAdjustmentLogs($data));
    }

    // --- Meal Reports ---
    public function mealAllocations($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->mealAllocations($data));
    }
    public function foodConsumptionTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->foodConsumptionTrends($data));
    }

    // --- Logs Reports ---
    public function communicationLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->communicationLogs($data));
    }
    public function feeStructureLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->feeStructureLogs($data));
    }
    public function inventoryLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->inventoryLogs($data));
    }
    public function systemLogs($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->systemLogs($data));
    }

    // --- System Reports ---
    public function loginActivity($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->loginActivity($data));
    }
    public function accountUnlocks($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->accountUnlocks($data));
    }
    public function auditTrailSummary($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->auditTrailSummary($data));
    }
    public function blockedDevicesStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->blockedDevicesStats($data));
    }

    // --- Workflow Reports ---
    public function workflowInstanceStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowInstanceStats($data));
    }
    public function workflowStageTimes($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowStageTimes($data));
    }
    public function workflowTransitionFrequencies($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->workflowTransitionFrequencies($data));
    }

    // --- Discipline Reports ---
    public function conductCasesStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->conductCasesStats($data));
    }
    public function disciplinaryTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->disciplinaryTrends($data));
    }

    // --- Communication Reports ---
    public function communicationsStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->communicationsStats($data));
    }
    public function parentPortalStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->parentPortalStats($data));
    }
    public function forumActivityStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->forumActivityStats($data));
    }
    public function announcementReach($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->announcementReach($data));
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
        return $this->user['id'] ?? null;
    }
}
