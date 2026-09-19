<?php

namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use Exception;

class AuditController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = $this->contract('App\API\Modules\system\SystemAPI');
    }

    // GET /api/audit/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['system.view', 'system_view', 'audit.view', 'audit_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $limit = min(100, intval($_GET['limit'] ?? 50));
            $result = $this->api->getAuditLogs($limit);
            if (!empty($result['success'])) {
                return $this->success($result['data']);
            }
            return $this->error('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AuditController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('An internal error occurred.');
        }
    }

    // POST /api/audit/approve-transaction
    // body: { transaction_id, approved: true/false, notes }
    public function postApproveTransaction($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.approve', 'finance_approve'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $txId = $data['transaction_id'] ?? null;
        $approved = isset($data['approved']) ? (bool) $data['approved'] : null;
        $notes = $data['notes'] ?? null;
        if (!$txId || $approved === null) {
            return $this->badRequest('Missing transaction_id or approved flag');
        }

        try {
            $result = $this->api->approveTransaction($txId, $approved, $notes, $this->getUserId());
            if (!empty($result['success'])) {
                return $this->success($result['data'] ?? [], 'Transaction approval recorded');
            }
            return $this->error('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AuditController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('An internal error occurred.');
        }
    }
}
