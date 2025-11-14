<?php
namespace App\API\Controllers;

use App\API\Modules\Reports\ReportsAPI;
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

    public function __construct() {
        parent::__construct();
        $this->api = new ReportsAPI();
    }

    // ========================================
    // SECTION 1: Academic Reports
    // ========================================

    /**
     * GET /api/reports/academic
     */
    public function getAcademic($id = null, $data = [], $segments = [])
    {
        $result = $this->api->academicReport($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/reports/academic-report
     */
    public function getAcademicReport($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAcademicReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Attendance Reports
    // ========================================

    /**
     * GET /api/reports/attendance
     */
    public function getAttendance($id = null, $data = [], $segments = [])
    {
        $result = $this->api->attendanceReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Fee Reports
    // ========================================

    /**
     * GET /api/reports/fee
     */
    public function getFee($id = null, $data = [], $segments = [])
    {
        $result = $this->api->feeReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Transport Reports
    // ========================================

    /**
     * GET /api/reports/transport
     */
    public function getTransport($id = null, $data = [], $segments = [])
    {
        $result = $this->api->transportReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Dashboard & Statistics
    // ========================================

    /**
     * GET /api/reports/dashboard-stats
     */
    public function getDashboardStats($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDashboardStats($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: System & Audit Reports
    // ========================================

    /**
     * GET /api/reports/system
     */
    public function getSystem($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSystemReports($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/reports/audit
     */
    public function getAudit($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAuditReports($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Custom Report Generation
    // ========================================

    /**
     * POST /api/reports/custom/generate
     */
    public function postCustomGenerate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateCustomReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Helper Methods
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
        return $this->user['id'] ?? null;
    }
}
