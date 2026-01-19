<?php
namespace App\API\Controllers;

use Exception;

class AlertsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /api/alerts - Return active system alerts
     */
    public function get($id = null, $data = [], $segments = [])
    {
        // Require authentication
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        try {
            // Note: 'link' column doesn't exist in system_alerts table - removed from query
            // Severity enum values: 'info', 'warning', 'critical'
            $query = "SELECT id, severity, title, message, created_at FROM system_alerts WHERE resolved = 0 ORDER BY FIELD(severity, 'critical','warning','info') ASC, created_at DESC LIMIT 50";
            $stmt = $this->db->query($query);
            $rows = $stmt ? $stmt->fetchAll() : [];

            return $this->success(['alerts' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch alerts: ' . $e->getMessage());
        }
    }
}
