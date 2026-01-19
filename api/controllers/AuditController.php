<?php

namespace App\API\Controllers;
use Exception;
use PDO;
class AuditController extends BaseController
{
    public function __construct($request = null)
    {
        parent::__construct($request);
    }

    // GET /api/audit/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('system.view', (array) $perms) && !in_array('audit.view', (array) $perms) && !in_array(10, (array) $user['roles'])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $limit = min(100, intval($_GET['limit'] ?? 50));
            // Use query() method - it handles prepare internally
            $stmt = $this->db->query(
                'SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ?',
                [$limit]
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            return $this->success(['logs' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch audit logs: ' . $e->getMessage());
        }
    }

    // POST /api/audit/approve-transaction
    // body: { transaction_id, approved: true/false, notes }
    public function postApproveTransaction($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.approve', (array) $perms) && !in_array(10, (array) $user['roles'])) {
            return $this->forbidden('Insufficient permissions');
        }

        $txId = $data['transaction_id'] ?? null;
        $approved = isset($data['approved']) ? (bool) $data['approved'] : null;
        $notes = $data['notes'] ?? null;
        if (!$txId || $approved === null)
            return $this->badRequest('Missing transaction_id or approved flag');

        try {
            // insert approval record into audit_logs using query() method
            $action = $approved ? 'approve_transaction' : 'reject_transaction';
            $details = json_encode(['notes' => $notes]);
            // normalize to existing school_transactions.status values
            $status = $approved ? 'confirmed' : 'failed';
            $this->db->query(
                'INSERT INTO audit_logs (action, entity, entity_id, user_id, details, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
                [$action, 'school_transaction', $txId, $user['id'] ?? null, $details, $status]
            );

            // Optionally update transaction status using query() method
            $newStatus = $approved ? 'confirmed' : 'failed';
            $this->db->query('UPDATE school_transactions SET status = ? WHERE id = ?', [$newStatus, $txId]);

            return $this->success(['audit_id' => $this->db->lastInsertId()], 'Transaction approval recorded');
        } catch (Exception $e) {
            return $this->error('Failed to record approval: ' . $e->getMessage());
        }
    }
}
