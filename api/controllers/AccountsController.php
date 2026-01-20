<?php

namespace App\API\Controllers;
use Exception;
use PDO;
use App\API\Modules\finance\FinanceAPI;
use App\API\Modules\finance\PaymentReconciliationAPI;

class AccountsController extends BaseController
{
    protected $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = new FinanceAPI();
    }

    // GET /api/accounts/bank-accounts
    public function getBankAccounts($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        // Use BaseController helper methods for role/permission checking
        // Allows: finance.view permission, OR role ID 10 (Accountant)
        $allowed = $this->userHasAny(
            ['finance.view'],                            // permissions
            [10],                                        // role IDs (10 = Accountant)
            ['accountant', 'finance', 'admin', 'director']  // role names
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            // First try the dedicated bank_accounts table
            $stmt = $this->db->query('SELECT id, name, account_no, bank_name, is_active FROM bank_accounts WHERE is_active = 1 ORDER BY name');
            $rows = $stmt ? $stmt->fetchAll() : [];

            // If no bank accounts defined, derive from bank_transactions as fallback
            if (empty($rows)) {
                $stmt = $this->db->query('SELECT DISTINCT bank_name AS name, account_number AS account_no FROM bank_transactions WHERE bank_name IS NOT NULL ORDER BY bank_name');
                $rows = $stmt ? $stmt->fetchAll() : [];
            }

            return $this->success(['bank_accounts' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch bank accounts: ' . $e->getMessage());
        }
    }

    // POST /api/accounts/bank-accounts - create/update
    public function postBankAccounts($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        // Use BaseController helper methods for role/permission checking
        $allowed = $this->userHasAny(
            ['finance.manage'],                          // permissions
            [10],                                        // role IDs (10 = Accountant)
            ['accountant', 'finance', 'admin', 'director']  // role names
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        $name = $data['name'] ?? null;
        $account_no = $data['account_no'] ?? null;
        $bank = $data['bank'] ?? null;
        if (!$name || !$account_no)
            return $this->badRequest('Missing required fields');

        try {
            // If a bank_accounts table exists this will work; otherwise instruct admin to run migration.
            // Use query() method which handles prepare internally
            $this->db->query(
                'INSERT INTO bank_accounts (name, account_no, bank_name, is_active, created_at) VALUES (?, ?, ?, 1, NOW())',
                [$name, $account_no, $bank]
            );
            return $this->success(['id' => $this->db->getConnection()->lastInsertId()], 'Bank account created');
        } catch (Exception $e) {
            return $this->error('Failed to create bank account (ensure bank_accounts table exists): ' . $e->getMessage());
        }
    }

    // GET /api/accounts/bank-transactions
    public function getBankTransactions($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        // Use BaseController helper methods for role/permission checking
        $allowed = $this->userHasAny(
            ['finance.view'],                            // permissions
            [10],                                        // role IDs (10 = Accountant)
            ['accountant', 'finance', 'admin', 'director']  // role names
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        $bankId = $_GET['bank_id'] ?? $data['bank_id'] ?? null;
        try {
            if ($bankId) {
                // bank_transactions stores account_number and bank_name; accept either
                // Use query() method which handles prepare internally
                $stmt = $this->db->query(
                    'SELECT * FROM bank_transactions WHERE account_number = ? OR bank_name = ? ORDER BY transaction_date DESC LIMIT 500',
                    [$bankId, $bankId]
                );
            } else {
                $stmt = $this->db->query('SELECT * FROM bank_transactions ORDER BY transaction_date DESC LIMIT 500');
            }
            $rows = $stmt ? $stmt->fetchAll() : [];
            return $this->success(['transactions' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch bank transactions: ' . $e->getMessage());
        }
    }

    // POST /api/accounts/petty-cash - create petty cash entry
    public function postPettyCash($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        // Use BaseController helper methods for role/permission checking
        $allowed = $this->userHasAny(
            ['finance.manage'],                          // permissions
            [10],                                        // role IDs (10 = Accountant)
            ['accountant', 'finance', 'admin', 'director']  // role names
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        $amount = $data['amount'] ?? null;
        $reason = $data['reason'] ?? null;
        if (!$amount || !$reason)
            return $this->badRequest('Missing amount or reason');

        try {
            // Use existing `expenses` table as petty cash recording to match schema
            // Use query() method which handles prepare internally
            $this->db->query(
                'INSERT INTO expenses (expense_category, description, amount, expense_date, created_by, status, created_at) VALUES (?, ?, ?, CURDATE(), ?, "pending", NOW())',
                ['petty_cash', $reason, $amount, $user['id'] ?? null]
            );
            return $this->success(['id' => $this->db->getConnection()->lastInsertId()], 'Petty cash recorded (as expense)');
        } catch (Exception $e) {
            return $this->error('Failed to record petty cash: ' . $e->getMessage());
        }
    }
}
