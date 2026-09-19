<?php
namespace App\API\Controllers;

use App\API\Modules\finance\FinanceAPI;
use App\API\Modules\finance\PaymentReconciliationAPI;
use App\API\Modules\finance\ExpenseManager;
use App\API\Modules\finance\AllowanceTemplateAPI;
use App\API\Services\StaffDomainAccessService;
use App\API\Services\FinanceCrudService;
use RuntimeException;
use Exception;
use App\Database\Database;
use App\API\Services\payments\SupplierDisbursementService;
use App\API\Services\payments\ParentRefundService;
use App\API\Services\payments\StudentFundTransferService;
use App\API\Services\payments\PaymentRoutingService;
use App\API\Services\payments\KcbFundsTransferService;
use App\API\Services\payments\KcbTransferReconciliationService;
use App\API\Services\FinancialReconciliationService;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use DomainException;

/**
 * FinanceController - REST endpoints for all finance operations
 * Handles fees, payments, payrolls, budgets, expenses, and financial reporting
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */

class FinanceController extends BaseController
{
    private FinanceAPI $api;
    private ExpenseManager $expenseManager;
    private AllowanceTemplateAPI $allowanceTemplateApi;
    private $staffAccess;
    private FinanceCrudService $crud;

    public function __construct() {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\finance\FinanceAPI');
        $this->expenseManager = $this->contract('App\API\Modules\finance\ExpenseManager');
        $this->allowanceTemplateApi = $this->contract('App\API\Modules\finance\AllowanceTemplateAPI');
        $this->staffAccess = $this->contract('App\API\Services\StaffDomainAccessService', $this->user);
        $this->crud = $this->contract('App\API\Services\FinanceCrudService', Database::getInstance()->getConnection());
    }

    public function index()
    {
        return $this->success(['message' => 'Finance API is running']);
    }

    /** GET /api/finance/accounting/trial-balance */
    public function getAccountingTrialBalance($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Insufficient permissions');
        try {
            $stmt = $this->db->query('SELECT * FROM vw_accounting_trial_balance ORDER BY account_code');
            return $this->success(['accounts' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] trial balance: ' . $e->getMessage());
            return $this->badRequest('Accounting trial balance is not available.');
        }
    }

    /** GET /api/finance/accounting/source-trace */
    public function getAccountingSourceTrace($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try {
            $limit = min(500, max(1, (int)($data['limit'] ?? 100)));
            $stmt = $this->db->query('SELECT * FROM vw_financial_source_trace ORDER BY created_at DESC LIMIT ' . $limit);
            return $this->success(['transactions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] source trace: ' . $e->getMessage());
            return $this->badRequest('Accounting source trace is not available.');
        }
    }

    /** GET /api/finance/financial-accounts */
    public function getFinancialAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try {
            $stmt = $this->db->query("SELECT a.*,k.code account_kind,p.code provider_code,c.account_code ledger_code,
                sa.account_name settlement_account_name, sa.account_identifier settlement_account_identifier,
                GROUP_CONCAT(DISTINCT r.collection_product ORDER BY r.collection_product SEPARATOR ',') collection_products,
                GROUP_CONCAT(DISTINCT r.reference_policy ORDER BY r.reference_policy SEPARATOR ',') reference_policies,
                GROUP_CONCAT(DISTINCT fp.code ORDER BY fp.code SEPARATOR ',') purposes
                FROM school_financial_accounts a
                JOIN financial_account_kinds k ON k.id=a.account_kind_id
                LEFT JOIN payment_providers p ON p.id=a.provider_id
                LEFT JOIN chart_of_accounts c ON c.id=a.ledger_account_id
                LEFT JOIN school_financial_accounts sa ON sa.id=a.settlement_financial_account_id
                LEFT JOIN payment_collection_routes r ON r.financial_account_id=a.id AND r.active=1
                LEFT JOIN school_financial_account_purposes ap ON ap.financial_account_id=a.id
                LEFT JOIN financial_account_purposes fp ON fp.id=ap.purpose_id
                GROUP BY a.id ORDER BY a.account_name");
            return $this->success(['accounts' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] financial accounts: ' . $e->getMessage());
            return $this->badRequest('Financial accounts are not available.');
        }
    }

    private function canConfigurePaymentIntegrations(): bool
    {
        return $this->userHasAny(['system.payment_integrations.configure'], [2], ['System Administrator']);
    }

    public function getFinancialAccountSetupOptions($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->financialAccountSetupOptions();
    }

    public function getPaymentPosTerminals($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->listPaymentPosTerminals();
    }

    public function postPaymentPosTerminals($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->createPaymentPosTerminal($data, (int)$this->getUserId());
    }

    public function putPaymentPosTerminals($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->updatePaymentPosTerminal((int)$id, $data, (int)$this->getUserId());
    }

    public function putPaymentPosTerminalsVerify($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->verifyPaymentPosTerminal((int)$id, (int)$this->getUserId());
    }

    public function postPaymentPosTransactions($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Finance payment permission required');
        return $this->api->recordPaymentPosTransaction($data, (int)$this->getUserId());
    }

    public function putFinancialAccount($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->updateFinancialAccount((int)$id, $data, (int)$this->getUserId());
    }

    // Router plural aliases for /finance/financial-accounts/*.
    public function putFinancialAccounts($id = null, $data = [], $segments = [])
    {
        return $this->putFinancialAccount($id, $data, $segments);
    }

    public function getFinancialAccountPermissions($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        return $this->api->financialAccountPermissions((int)$id);
    }

    /** GET /api/finance/reconciliation/statement-lines */
    public function getReconciliationStatementLines($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile', 'finance.view', 'finance_view'], [10])) return $this->forbidden('Insufficient permissions');
        try {
            return $this->success(['lines' => ($this->contract('App\API\Services\FinancialReconciliationService', $this->db))->unresolved((int)($data['limit'] ?? 200))]);
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] statement lines: '.$e->getMessage()); return $this->badRequest('Statement reconciliation is not available.'); }
    }

    /** POST /api/finance/reconciliation/statement-imports */
    public function postReconciliationStatementImports($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile', 'finance.manage', 'finance_manage'], [10])) return $this->forbidden('Insufficient permissions');
        try {
            $result = ($this->contract('App\API\Services\FinancialReconciliationService', $this->db))->import((string)($data['provider'] ?? ''), (int)($data['financial_account_id'] ?? 0), (array)($data['rows'] ?? []), (int)$this->getUserId());
            return $this->success($result, 'Statement imported and matching attempted.');
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] statement import: '.$e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** POST /api/finance/reconciliation/statement-lines/{id}/resolve */
    public function postReconciliationStatementLinesResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile'], [10])) return $this->forbidden('Insufficient permissions');
        try {
            $result = ($this->contract('App\API\Services\FinancialReconciliationService', $this->db))->resolve((int)$id, (string)($data['matching_status'] ?? ''), (int)$this->getUserId(), (string)($data['reason'] ?? ''), $data['matched_reference'] ?? null);
            return $this->success($result, 'Statement line resolution recorded.');
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] statement resolve: '.$e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** GET /api/finance/kcb-disbursements — callback/status reconciliation queue. */
    public function getKcbDisbursements($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view', 'finance.reconcile', 'finance_reconcile'], [3, 4, 10])) {
            return $this->forbidden('Finance reconciliation permission required');
        }
        try {
            $filters = array_merge($data, [
                'status' => $_GET['status'] ?? ($data['status'] ?? ''),
                'exceptions_only' => $_GET['exceptions_only'] ?? ($data['exceptions_only'] ?? false),
                'limit' => $_GET['limit'] ?? ($data['limit'] ?? 100),
            ]);
            return $this->success(['disbursements' => ($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->list($filters)]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB reconciliation list: ' . $e->getMessage());
            return $this->badRequest('KCB reconciliation queue is not available. Apply the latest database migrations.');
        }
    }

    /** POST /api/finance/kcb-disbursements/{id}/status-inquiry */
    public function postKcbDisbursementsStatusInquiry($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Finance reconciliation permission required');
        }
        try {
            return $this->success(
                ($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->inquire((int) $id, (int) $this->getUserId(), 'manual'),
                'KCB transfer status checked and reconciled.'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB status inquiry: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/finance/kcb-disbursements/poll — authorized staff batch action. */
    public function postKcbDisbursementsPoll($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile'], [3, 4, 10])) {
            return $this->forbidden('Finance reconciliation permission required');
        }
        try {
            return $this->success(
                ($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->pollDue((int) ($data['limit'] ?? 25)),
                'Due KCB transfers checked.'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB status poll: ' . $e->getMessage());
            return $this->badRequest('KCB status polling failed.');
        }
    }

    /** POST /api/finance/kcb-reconciliation-worker — cron/systemd worker. */
    public function postKcbReconciliationWorker($id = null, $data = [], $segments = [])
    {
        $expected = defined('KCB_RECONCILIATION_WORKER_SECRET') ? (string) KCB_RECONCILIATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->forbidden('Invalid worker credential');
        }
        try {
            return $this->success(
                ($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->pollDue((int) ($data['limit'] ?? 25)),
                'KCB reconciliation worker completed.'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB reconciliation worker: ' . $e->getMessage());
            return $this->serverError('KCB reconciliation worker failed.');
        }
    }

    /** POST /api/finance/kcb-disbursements/{id}/retry */
    public function postKcbDisbursementsRetry($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.approve', 'finance_approve', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Finance payment permission required');
        }
        try {
            return $this->accepted(
                ($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->retry((int) $id, (int) $this->getUserId()),
                'A linked, idempotent KCB retry was submitted.'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB safe retry: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/finance/kcb-disbursements/{id}/resolve */
    public function postKcbDisbursementsResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_reconcile'], [3, 4])) {
            return $this->forbidden('Senior finance reconciliation permission required');
        }
        try {
            return $this->success(($this->contract('App\API\Services\payments\KcbTransferReconciliationService', $this->db->getConnection()))->resolveManually(
                (int) $id,
                (string) ($data['outcome'] ?? ''),
                (string) ($data['evidence'] ?? ''),
                (int) $this->getUserId()
            ), 'Manual reconciliation recorded with evidence.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB manual reconciliation: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/finance/kcb-oauth-revoke — security incident/key rotation only. */
    public function postKcbOauthRevoke($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) {
            return $this->forbidden('Payment integration configuration access required');
        }
        try {
            $result = ($this->contract('App\API\Services\payments\KcbFundsTransferService'))->revokeAccessToken();
            \App\API\Includes\FileLogger::write('payments', [
                'type' => 'security', 'source' => 'kcb_oauth', 'user_id' => (int) $this->getUserId(),
                'action' => 'access_token_revoked', 'status' => 'success',
            ]);
            return $this->success($result, 'The current KCB access token was revoked.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] KCB token revocation: ' . $e->getMessage());
            return $this->badRequest('KCB token revocation failed.');
        }
    }

    /** GET /api/finance/accounting/report?type=income|balance|cashflow */
    public function getAccountingReport($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $type = strtolower((string)($data['type'] ?? 'income'));
        $where = $type === 'balance' ? "t.code IN ('asset','liability','equity')" : ($type === 'cashflow' ? "t.code='asset' AND c.account_code LIKE '110%'" : "t.code IN ('revenue','expense')");
        try {
            $sql = "SELECT c.account_code,c.account_name,t.code AS account_type,
                ROUND(COALESCE(SUM(CASE WHEN j.status='posted' THEN l.debit_amount-l.credit_amount ELSE 0 END),0),2) AS balance
                FROM chart_of_accounts c JOIN accounting_account_types t ON t.id=c.account_type_id
                LEFT JOIN accounting_journal_lines l ON l.chart_account_id=c.id LEFT JOIN accounting_journal_batches j ON j.id=l.journal_batch_id
                WHERE {$where} GROUP BY c.id,c.account_code,c.account_name,t.code ORDER BY c.account_code";
            return $this->success(['type' => $type, 'rows' => $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC)]);
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] accounting report: '.$e->getMessage()); return $this->badRequest('Ledger report is not available.'); }
    }

    /** POST /api/finance/financial-accounts */
    public function postFinancialAccount($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Only authorized integration administrators may configure school accounts');
        return $this->api->createFinancialAccount($data, (int)$this->getUserId());
    }

    public function postFinancialAccounts($id = null, $data = [], $segments = [])
    {
        return $this->postFinancialAccount($id, $data, $segments);
    }

    /** PUT /api/finance/financial-accounts/{id}/verify */
    public function putFinancialAccountVerify($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Only authorized integration administrators may verify school accounts');
        return $this->api->verifyFinancialAccount((int)$id, (int)$this->getUserId(), (string)($data['status'] ?? 'active'));
    }

    public function putFinancialAccountsVerify($id = null, $data = [], $segments = [])
    {
        return $this->putFinancialAccountVerify($id, $data, $segments);
    }

    /** POST /api/finance/financial-accounts/{id}/permissions */
    public function postFinancialAccountPermissions($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Only authorized integration administrators may assign account permissions');
        return $this->api->setFinancialAccountPermissions((int)$id, (array)($data['permissions'] ?? []), (int)$this->getUserId());
    }

    public function postFinancialAccountsPermissions($id = null, $data = [], $segments = [])
    {
        return $this->postFinancialAccountPermissions($id, $data, $segments);
    }

    /**
     * GET /api/finance/supplier-payables
     * Returns approved supplier expenses with outstanding balances and verified
     * payout accounts so the finance UI never asks users to type IDs.
     */
    public function getSupplierPayables($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query(
                "SELECT e.id AS expense_id, e.vendor_id AS supplier_id,
                        s.name AS supplier_name, e.description, e.reference_number,
                        e.amount AS expense_amount, e.status, e.created_at,
                        COALESCE(SUM(CASE WHEN spr.status IN ('payment_pending','paid') THEN spr.amount ELSE 0 END), 0) AS paid_or_pending,
                        e.amount - COALESCE(SUM(CASE WHEN spr.status IN ('payment_pending','paid') THEN spr.amount ELSE 0 END), 0) AS outstanding_amount
                 FROM expenses e
                 JOIN suppliers s ON s.id = e.vendor_id
                 LEFT JOIN supplier_payment_requests spr ON spr.expense_id = e.id
                 WHERE e.vendor_id IS NOT NULL AND e.status IN ('approved','payment_pending')
                 GROUP BY e.id, e.vendor_id, s.name, e.description, e.reference_number, e.amount, e.status, e.created_at
                 HAVING outstanding_amount > 0.009
                 ORDER BY e.created_at ASC, e.id ASC"
            );
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $bank = $pdo->query("SELECT id, supplier_id, bank_name, bank_code, account_name, account_number, currency, is_primary FROM supplier_bank_accounts WHERE active = 1 AND verification_status = 'verified' ORDER BY is_primary DESC, id DESC")->fetchAll(\PDO::FETCH_ASSOC);
            $mobile = $pdo->query("SELECT id, supplier_id, provider, phone_number, account_name, is_primary FROM supplier_mobile_accounts WHERE active = 1 AND verification_status = 'verified' ORDER BY is_primary DESC, id DESC")->fetchAll(\PDO::FETCH_ASSOC);
            $banks = $mobiles = [];
            foreach ($bank as $account) $banks[(int) $account['supplier_id']][] = $account;
            foreach ($mobile as $account) $mobiles[(int) $account['supplier_id']][] = $account;
            foreach ($rows as &$row) {
                $supplierId = (int) $row['supplier_id'];
                $row['expense_id'] = (int) $row['expense_id'];
                $row['outstanding_amount'] = (float) $row['outstanding_amount'];
                $row['bank_accounts'] = $banks[$supplierId] ?? [];
                $row['mobile_accounts'] = $mobiles[$supplierId] ?? [];
            }
            unset($row);
            return $this->success(['payables' => $rows]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] supplier payables: ' . $e->getMessage());
            return $this->badRequest('Failed to load supplier payables.');
        }
    }

    /** POST /api/finance/supplier-payments — submit one or many supplier payouts. */
    public function postSupplierPayments($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        $items = $data['items'] ?? [];
        if (!is_array($items) || !$items) return $this->badRequest('At least one supplier payment is required.');
        $service = $this->contract('App\API\Services\payments\SupplierDisbursementService', Database::getInstance()->getConnection());
        $results = [];
        foreach ($items as $item) {
            $expenseId = (int) ($item['expense_id'] ?? 0);
            if (!$expenseId) {
                $results[] = ['expense_id' => null, 'status' => 'failed', 'message' => 'Expense ID is required.'];
                continue;
            }
            try {
                $result = $service->initiateExpensePayment($expenseId, (int) $this->getUserId(), $item);
                $results[] = array_merge(['expense_id' => $expenseId], $result);
            } catch (\Throwable $e) {
                \App\API\Services\Logger::legacyError('[FinanceController] supplier payment #' . $expenseId . ': ' . $e->getMessage());
                $results[] = ['expense_id' => $expenseId, 'status' => 'failed', 'message' => $e->getMessage()];
            }
        }
        return $this->success(['results' => $results], 'Supplier payment batch submitted.');
    }

    /** GET /api/finance/parent-refund-requests */
    public function getParentRefundRequests($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT r.*, c.credit_number, c.student_id, a.provider, a.phone_number, a.bank_name, a.account_number, a.account_name FROM parent_refund_requests r JOIN fee_credit_notes c ON c.id = r.fee_credit_note_id JOIN parent_payment_accounts a ON a.id = r.parent_payment_account_id ORDER BY r.created_at DESC");
        return $this->success(['refunds' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** GET /api/finance/refundable-credits */
    public function getRefundableCredits($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->query("SELECT c.id AS fee_credit_note_id, c.credit_number, c.student_id, c.remaining_amount, CONCAT(ps.first_name, ' ', ps.last_name) AS student_name, sp.parent_id, CONCAT(pp.first_name, ' ', pp.last_name) AS parent_name FROM fee_credit_notes c JOIN students s ON s.id = c.student_id JOIN persons ps ON ps.id = s.person_id JOIN student_parents sp ON sp.student_id = c.student_id LEFT JOIN parents pr ON pr.id = sp.parent_id LEFT JOIN persons pp ON pp.id = pr.person_id WHERE c.status IN ('available','partially_applied') AND c.remaining_amount > 0 AND sp.is_primary_contact = 1 ORDER BY c.created_at ASC");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $accounts = $pdo->query("SELECT id, parent_id, provider, phone_number, bank_name, account_name, account_number, is_primary FROM parent_payment_accounts WHERE active = 1 AND verification_status = 'verified' ORDER BY is_primary DESC, id DESC")->fetchAll(\PDO::FETCH_ASSOC);
        $byParent = []; foreach ($accounts as $account) $byParent[(int) $account['parent_id']][] = $account;
        foreach ($rows as &$row) { $row['remaining_amount'] = (float) $row['remaining_amount']; $row['accounts'] = $byParent[(int) $row['parent_id']] ?? []; } unset($row);
        return $this->success(['credits' => $rows]);
    }

    /** POST /api/finance/parent-refund-requests */
    public function postParentRefundRequests($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try { $service = $this->contract('App\API\Services\payments\ParentRefundService', Database::getInstance()->getConnection()); $items = $data['items'] ?? [$data]; $results = []; foreach ($items as $item) { $results[] = $service->createRequest((int) ($item['fee_credit_note_id'] ?? 0), (int) $this->getUserId(), $item); } return $this->success(['results' => $results], 'Refund submitted for approval.'); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] parent refund request: ' . $e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** GET /api/finance/student-fund-transfers */
    public function getStudentFundTransfers($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        return $this->success(['transfers' => ($this->contract('App\API\Services\payments\StudentFundTransferService', Database::getInstance()->getConnection()))->list(['status' => $_GET['status'] ?? null])]);
    }

    /** GET /api/finance/student-fund-sources */
    public function getStudentFundSources($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        return $this->success(($this->contract('App\API\Services\payments\StudentFundTransferService', Database::getInstance()->getConnection()))->sources());
    }

    /** GET /api/finance/payment-routing-cases */
    public function getPaymentRoutingCases($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        return $this->success(['cases' => ($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->listUnmatchedCases(['status' => $_GET['status'] ?? 'unmatched'])]);
    }

    /** POST /api/finance/ai-reconciliation-review-queue */
    public function postAiReconciliationReviewQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->canUseAiReconciliation()) return $this->forbidden('Finance reconciliation permission is required');
        try {
            $cases = ($this->contract('App\\API\\Services\\payments\\PaymentRoutingService', Database::getInstance()->getConnection()))
                ->listUnmatchedCases(['status' => 'unmatched']);
            if ($cases === []) return $this->badRequest('There are no unmatched payment cases to review.');

            $createdAt = array_filter(array_map(static fn(array $case): int => strtotime((string) ($case['created_at'] ?? '')) ?: 0, $cases));
            $amounts = array_map(static fn(array $case): float => (float) ($case['amount'] ?? 0), $cases);
            $maxAmount = $amounts ? max($amounts) : 0.0;
            $amountBand = $maxAmount <= 10000 ? '0-10,000' : ($maxAmount <= 100000 ? '10,001-100,000' : '100,001+');
            $providers = array_values(array_unique(array_filter(array_map(static fn(array $case): string => (string) ($case['provider_code'] ?? ''), $cases))));
            $input = [
                'unmatched_count' => (string) count($cases),
                'days_open' => (string) max(0, (int) floor((time() - ($createdAt ? min($createdAt) : time())) / 86400)),
                'amount_band' => 'KES ' . $amountBand,
                'currency' => 'KES',
                'provider' => implode(', ', array_slice($providers, 0, 10)) ?: 'mixed providers',
                'reconciliation_rule' => 'Review reference, account routing, duplicate risk, and missing evidence before deterministic resolution.',
            ];
            $queued = $this->contract(AiDraftService::class)->queue(
                'finance.reconciliation_review',
                $this->aiFinanceContext(),
                $input,
                [
                    'subject_type' => 'finance_reconciliation_review',
                    'subject_id' => 0,
                    'scope' => 'unmatched_payment_cases',
                    'provider' => $input['provider'],
                    'amount_band' => $input['amount_band'],
                    'currency' => 'KES',
                    'reconciliation_rule' => 'deterministic_match_and_human_resolution',
                ]
            );
            return $this->accepted($queued, 'Finance reconciliation review queued');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] AI reconciliation queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue finance reconciliation assistance');
        }
    }

    /** GET /api/finance/ai-reconciliation-drafts?scope=own|review */
    public function getAiReconciliationDrafts($id = null, $data = [], $segments = [])
    {
        $scope = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own'));
        $review = $scope === 'review';
        if ($review && !$this->canApproveAiReconciliation()) return $this->forbidden('Senior finance reconciliation permission is required');
        try {
            return $this->success([
                'drafts' => $this->contract(AiDraftService::class)->listForReview(
                    $this->getDb()->getConnection(), (int) ($this->getUserId() ?? 0), $review, 'finance'
                ),
                'scope' => $review ? 'review' : 'own',
            ], 'Finance reconciliation AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] AI reconciliation list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load finance reconciliation assistance');
        }
    }

    /** POST /api/finance/ai-reconciliation-draft-approve/{id} */
    public function postAiReconciliationDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiReconciliation()) return $this->forbidden('Senior finance reconciliation permission is required');
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        try {
            $approved = $this->contract(AiDraftService::class)->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'finance.reconciliation_review');
            return $this->success([
                'draft_id' => $draftId,
                'status' => 'approved',
                'review_only' => true,
                'draft' => $approved['draft'] ?? [],
            ], 'AI review approved; reconcile or resolve through the normal finance workflow');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] AI reconciliation approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve finance reconciliation assistance');
        }
    }

    private function aiFinanceContext(): array
    {
        return [
            'user_id' => (int) ($this->getUserId() ?? 0),
            'permissions' => array_values(array_unique(array_merge(
                (array) ($this->user['effective_permissions'] ?? []),
                (array) ($this->user['permissions'] ?? [])
            ))),
            'request_id' => $_SERVER['REQUEST_ID'] ?? '',
        ];
    }

    private function canUseAiReconciliation(): bool
    {
        try { $this->contract(AiWorkflowService::class)->authorize('finance.reconciliation_review', $this->aiFinanceContext()); return true; }
        catch (DomainException $e) { return false; }
    }

    private function canApproveAiReconciliation(): bool
    {
        return $this->userHasAny(['finance.reconcile', 'finance_reconcile', 'finance.manage', 'finance_manage'], [3, 4, 10], ['accountant', 'finance', 'admin']);
    }

    /** POST /api/finance/payment-references */
    public function postPaymentReferences($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try { return $this->created(($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->generateReference((string)($data['purpose'] ?? ''), (int)($data['student_id'] ?? 0), !empty($data['transport_intent_id']) ? (int)$data['transport_intent_id'] : null, !empty($data['uniform_sale_id']) ? (int)$data['uniform_sale_id'] : null), 'Payment reference generated'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** GET /api/finance/payment-collection-routes */
    public function getPaymentCollectionRoutes($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10]) && !$this->canConfigurePaymentIntegrations()) return $this->forbidden('Insufficient permissions');
        return $this->success(['routes' => ($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->listRoutes()]);
    }

    /** POST /api/finance/payment-collection-routes */
    public function postPaymentCollectionRoutes($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        try { return $this->created(($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->saveRoute($data), 'Collection route saved'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** PUT /api/finance/payment-collection-routes/{id} */
    public function putPaymentCollectionRoutes($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        try { return $this->success(($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->updateRoute((int)$id, $data), 'Collection route updated'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** DELETE /api/finance/payment-collection-routes/{id} */
    public function deletePaymentCollectionRoutes($id = null, $data = [], $segments = [])
    {
        if (!$this->canConfigurePaymentIntegrations()) return $this->forbidden('Payment integration configuration access required');
        try { return $this->success(($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->deleteRoute((int)$id), 'Collection route deactivated'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** POST /api/finance/payment-routing-cases/{id}/resolve */
    public function postPaymentRoutingCasesResolve($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.reconcile', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        if (!$id) return $this->badRequest('Case ID is required');
        try { return $this->success(($this->contract('App\API\Services\payments\PaymentRoutingService', Database::getInstance()->getConnection()))->resolveCase((int)$id, $data, (int)$this->getUserId()), 'Payment case resolved and allocated'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** POST /api/finance/student-fund-transfers */
    public function postStudentFundTransfers($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try { return $this->created(($this->contract('App\API\Services\payments\StudentFundTransferService', Database::getInstance()->getConnection()))->create($data, (int)$this->getUserId()), 'Fund transfer submitted for approval'); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] fund transfer create: '.$e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** PUT /api/finance/student-fund-transfers/{id} */
    public function putStudentFundTransfers($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.approve', 'finance_approve'], [3, 4, 10])) return $this->forbidden('Only authorized finance approvers may decide fund transfers');
        if (!$id) return $this->badRequest('Transfer ID is required');
        try { return $this->success(($this->contract('App\API\Services\payments\StudentFundTransferService', Database::getInstance()->getConnection()))->decide((int)$id, strtolower((string)($data['decision'] ?? $data['status'] ?? '')), (int)$this->getUserId()), 'Transfer decision recorded'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** POST /api/finance/student-fund-transfer-post/{id} */
    public function postStudentFundTransferPost($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.approve', 'finance_approve'], [3, 4, 10])) return $this->forbidden('Only authorized finance approvers may post fund transfers');
        if (!$id) return $this->badRequest('Transfer ID is required');
        try { return $this->success(($this->contract('App\API\Services\payments\StudentFundTransferService', Database::getInstance()->getConnection()))->post((int)$id, (int)$this->getUserId()), 'Fund transfer posted'); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] fund transfer post: '.$e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** PUT /api/finance/parent-refund-requests/{id} — approve or reject. */
    public function putParentRefundRequests($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.approve', 'finance_approve'], [3])) return $this->forbidden('Only an authorized approver may approve refunds');
        $status = ($data['status'] ?? $data['action'] ?? '') === 'approve' ? 'approved' : (($data['status'] ?? '') === 'rejected' ? 'rejected' : null);
        if (!$id || !$status) return $this->badRequest('Refund ID and approve/reject action are required');
        $stmt = Database::getInstance()->getConnection()->prepare("UPDATE parent_refund_requests SET status = ?, approved_by = ? WHERE id = ? AND status = 'pending_approval'");
        $stmt->execute([$status, $this->getUserId(), (int) $id]);
        return $stmt->rowCount() ? $this->success(['id' => (int) $id, 'status' => $status]) : $this->badRequest('Refund is not awaiting approval.');
    }

    /** POST /api/finance/parent-refund-requests/{id}/submit */
    public function postParentRefundRequestsSubmit($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        try { $result = ($this->contract('App\API\Services\payments\ParentRefundService', Database::getInstance()->getConnection()))->submit((int) $id, (int) $this->getUserId()); return $this->success($result, 'Parent refund submitted for provider processing.'); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] parent refund submit: ' . $e->getMessage()); return $this->badRequest($e->getMessage()); }
    }

    /** GET /api/finance/parent-payment-accounts?parent_id=... */
    public function getParentPaymentAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $parentId = (int) ($_GET['parent_id'] ?? $data['parent_id'] ?? 0);
        if (!$parentId) return $this->badRequest('parent_id is required');
        $stmt = Database::getInstance()->getConnection()->prepare("SELECT id, provider, phone_number, bank_name, bank_code, account_name, account_number, verification_status, is_primary, active FROM parent_payment_accounts WHERE parent_id = ? ORDER BY is_primary DESC, id DESC");
        $stmt->execute([$parentId]);
        return $this->success(['accounts' => $stmt->fetchAll(\PDO::FETCH_ASSOC)]);
    }

    /** POST /api/finance/parent-payment-accounts */
    public function postParentPaymentAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $parentId = (int) ($data['parent_id'] ?? 0); $provider = ($data['provider'] ?? '') === 'mpesa' ? 'mpesa' : 'bank';
        if (!$parentId || empty($data['account_name'])) return $this->badRequest('parent_id and account_name are required');
        if ($provider === 'mpesa' && empty($data['phone_number'])) return $this->badRequest('phone_number is required for M-Pesa');
        if ($provider === 'bank' && (empty($data['account_number']) || empty($data['bank_name']))) return $this->badRequest('bank_name and account_number are required for bank refunds');
        try { $pdo = Database::getInstance()->getConnection(); $stmt = $pdo->prepare("INSERT INTO parent_payment_accounts (parent_id, provider, phone_number, bank_name, bank_code, account_name, account_number, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"); $stmt->execute([$parentId, $provider, $data['phone_number'] ?? null, $data['bank_name'] ?? null, $data['bank_code'] ?? null, $data['account_name'], $data['account_number'] ?? null, !empty($data['is_primary']) ? 1 : 0]); return $this->created(['id' => (int) $pdo->lastInsertId()], 'Parent payment account saved for verification.'); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[FinanceController] parent payment account: ' . $e->getMessage()); return $this->badRequest('Unable to save parent payment account.'); }
    }

    private function requirePayrollPermission(string $permission, array $roles = []): ?array
    {
        try {
            $this->staffAccess->require($permission, $roles);
            return null;
        } catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->serverError('An internal error occurred.'); }
    }

    private function validatePayrollPayloadEligibility(array $payload): ?array
    {
        $staffIds = [];
        if (!empty($payload['staff_id'])) $staffIds[] = (int)$payload['staff_id'];
        foreach ((array)($payload['staff_ids'] ?? []) as $sid) $staffIds[] = (int)$sid;
        foreach ((array)($payload['staff'] ?? $payload['records'] ?? $payload['payroll_items'] ?? []) as $row) {
            if (is_array($row) && !empty($row['staff_id'])) $staffIds[] = (int)$row['staff_id'];
        }
        foreach (array_unique(array_filter($staffIds)) as $staffId) {
            try { $this->staffAccess->assertPayrollEligible($staffId); }
            catch (RuntimeException $e) { \App\API\Services\Logger::legacyError('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()); return ($e->getCode() === 403) ? $this->forbidden($e->getMessage()) : $this->badRequest($e->getMessage()); }
        }
        return null;
    }

    /**
     * Guard: Director role (3) or any finance approval permission required.
     * Returns a forbidden response when access is denied, null when granted.
     */
    private function requireApprovalAccess(string $action = 'perform this approval'): ?array
    {
        if (in_array('accountant', $this->staffAccess->roles(), true)) {
            return $this->forbidden("Accountants prepare payroll; they cannot $action");
        }
        if ($this->staffAccess->allows('staff.payroll.approve', ['director', 'school administrator', 'headteacher', 'system administrator']) ||
            $this->userHasAny(['finance_approve', 'payroll_approve', 'budget_approve',
                               'fee_structure_approve', 'expense_approve', 'finance.approve'],
                              [2, 3, 4, 5], ['director', 'school administrator', 'headteacher', 'system administrator'])) {
            return null;
        }
        return $this->forbidden("Insufficient permissions to $action");
    }

    /** Only the director/school-administration control layer may alter fee relief. */
    private function requireFeeReliefAccess(string $action): ?array
    {
        $roles = $this->staffAccess->roles();
        if (in_array('accountant', $roles, true)) return $this->forbidden("Accountants cannot $action");
        if (array_intersect($roles, ['director', 'school administrator', 'system administrator'])) {
            return null;
        }
        return $this->forbidden("Only the director or school administrator can $action");
    }

    // ========================================
    // SECTION X: Department Budget Workflows
    // ========================================

    /**
     * POST /api/finance/department-budgets/propose
     * Department submits a budget proposal
     */
    public function postDepartmentBudgetsPropose($id = null, $data = [], $segments = [])
    {
        $result = $this->api->proposeDepartmentBudget($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/proposals
     * View all department budget proposals (optionally filter by department/status)
     */
    public function getDepartmentBudgetsProposals($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listDepartmentBudgetProposals($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/approve
     * Approve or reject a department budget proposal.
     * Accepts: proposal_id (or budget_id alias), status (default: approved), reviewed_by
     */
    public function postDepartmentBudgetsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve department budgets')) return $denied;
        $proposalId = $data['proposal_id'] ?? $data['budget_id'] ?? null;
        if (!$proposalId) {
            return $this->badRequest('proposal_id (or budget_id) is required');
        }
        $status     = $data['status']      ?? 'approved';
        $reviewedBy = $data['reviewed_by'] ?? $this->getUserId();
        $result = $this->api->updateDepartmentBudgetProposalStatus($proposalId, $status, $reviewedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/allocate
     * Allocate funds to a department budget
     */
    public function postDepartmentBudgetsAllocate($id = null, $data = [], $segments = [])
    {
        // Expecting: $data['department_id'], $data['amount'], $data['allocated_by']
        $departmentId = $data['department_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $allocatedBy = $data['allocated_by'] ?? $this->getUserId();
        if (!$departmentId || !$amount) {
            return $this->badRequest('department_id and amount are required');
        }
        $result = $this->api->allocateDepartmentBudget($departmentId, $amount, $allocatedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/request-funds
     * Department requests funds from allocated budget
     */
    public function postDepartmentBudgetsRequestFunds($id = null, $data = [], $segments = [])
    {
        $result = $this->api->requestDepartmentFunds($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/summary
     * Quick summary of department budget utilization
     */
    public function getDepartmentBudgetsSummary($id = null, $data = [], $segments = [])
    {
        $departmentId = $_GET['department_id'] ?? $data['department_id'] ?? $id ?? null;
        // department_id is optional — null returns all departments
        $result = $this->api->getDepartmentBudgetSummary($departmentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/approve
     */
    public function postExpensesApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        $result = $this->expenseManager->approveExpense(
            $expenseId,
            $this->getUserId(),
            $data['notes'] ?? $data['comments'] ?? null
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/reject
     */
    public function postExpensesReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        if (empty($data['reason'])) {
            return $this->badRequest('reason is required when rejecting an expense');
        }
        $result = $this->expenseManager->rejectExpense($expenseId, $this->getUserId(), $data['reason']);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/finance - List all finance records
     * GET /api/finance/{id} - Get single finance record
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance - Create new finance record
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/{id} - Update finance record
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for update');
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/{id} - Delete finance record
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Payroll Operations
    // ========================================

    /**
     * GET /api/finance/payrolls — alias for list
     */
    public function getPayrolls($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        return $this->getPayrollsList($id, $data, $segments);
    }

    /**
     * GET /api/finance/payrolls/list
     */
    public function getPayrollsList($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->listPayrolls($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/get
     */
    public function getPayrollsGet($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/staff-payments
     */
    public function getPayrollsStaffPayments($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->listStaffPayments($id);
        return $this->handleResponse($result);
    }

    /** GET /api/finance/payrolls/{id}/source-allocations */
    public function getPayrollsSourceAllocations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['director','school administrator','accountant','system administrator'])) return $denied;
        $payrollId = (int)($id ?: ($data['payroll_id'] ?? 0));
        if (!$payrollId) return $this->badRequest('Payroll ID is required.');
        return $this->handleResponse($this->api->payrollSourceAllocationRows($payrollId));
    }

    /** POST /api/finance/payrolls/{id}/source-allocations */
    public function postPayrollsSourceAllocations($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.approve', ['director','school administrator','system administrator'])) return $denied;
        $payload = $data ?: $this->getRequestData();
        $payrollId = (int)($id ?: ($payload['payroll_id'] ?? 0));
        if (!$payrollId) return $this->badRequest('Payroll ID is required.');
        return $this->handleResponse($this->api->assignPayrollSourceAccounts($payrollId, (array)($payload['allocations'] ?? []), (int)$this->getUserId()));
    }

    /**
     * POST /api/finance/payrolls/create-draft
     */
    public function postPayrollsCreateDraft($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->createPayrollDraft($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/calculate
     */
    public function postPayrollsCalculate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->calculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/recalculate
     */
    public function postPayrollsRecalculate($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->recalculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/verify
     */
    public function postPayrollsVerify($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $result = $this->api->verifyPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/approve
     */
    public function postPayrollsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve payroll')) return $denied;
        $payrollId = $data['payroll_id'] ?? $data['id'] ?? $id;
        $approvedBy = $data['user_id'] ?? $this->getUserId();
        $result = $this->api->approvePayroll($payrollId, $approvedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/reject
     */
    public function postPayrollsReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject payroll')) return $denied;
        $data['user_id'] = $data['user_id'] ?? $this->getUserId();
        $result = $this->api->rejectPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/process
     */
    public function postPayrollsProcess($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','director','school administrator'])) return $denied;
        $result = $this->api->processPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/disburse
     */
    public function postPayrollsDisburse($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','director','school administrator'])) return $denied;
        $result = $this->api->disbursePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/{id}/cancel
     */
    public function postPayrollsCancel($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->cancelPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/status
     */
    public function getPayrollsStatus($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayrollStatus($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/staff-payments/get
     */
    public function getPayrollsStaffPaymentsGet($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getStaffPayments($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/summary
     */
    public function getPayrollsSummary($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getPayrollSummary($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/history
     */
    public function getPayrollsHistory($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getPayrollHistory($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2B: Enhanced Payroll with Children Fees
    // ========================================

    /**
     * GET /api/finance/staff-for-payroll
     * Get list of staff available for payroll processing with children count
     */
    public function getStaffForPayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $result = $this->api->getStaffForPayroll();
        if (!$this->isPayrollReviewer() && ($result['status'] ?? '') === 'success') {
            foreach ((array)($result['data'] ?? []) as &$staff) {
                $staff['children_count'] = 0;
            }
            unset($staff);
        }
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/staff-payroll-details?staff_id=X
     * Get detailed staff info including children and fee balances
     */
    public function getStaffPayrollDetails($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? $id ?? null;

        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $result = $this->api->getStaffPayrollDetails($staffId);
        if (!$this->isPayrollReviewer() && ($result['status'] ?? '') === 'success' && is_array($result['data'] ?? null)) {
            $result['data']['children'] = [];
            $result['data']['has_children'] = false;
            $result['data']['total_children_fees'] = 0;
            $result['data']['invoice_warnings'] = [];
        }
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/bulk-payroll-preview?month=X&year=Y
     * Prepare bulk payroll preview using configured salary, allowances and deductions
     */
    public function getBulkPayrollPreview($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');
        $result = $this->api->getBulkPayrollPreview($month, $year, !$this->isPayrollReviewer());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/process-bulk-payroll
     * Process multiple staff payroll records as one backend request
     */
    public function postProcessBulkPayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $payload = $data ?: $this->getRequestData();
        if (!$this->isPayrollReviewer()) {
            $payload['preparation_only'] = true;
        }
        $result = $this->api->processBulkPayroll($payload);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/approve-payroll
     * Executive approval before director/school-admin payment release
     */
    public function postApprovePayroll($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve payroll')) return $denied;
        $payload = $data ?: $this->getRequestData();
        $payrollId = $payload['payroll_id'] ?? null;
        if (!$payrollId) {
            return $this->badRequest('Payroll ID required');
        }
        $approvedBy = $payload['approved_by'] ?? null;
        $result = $this->api->approvePayroll($payrollId, $approvedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/process-payroll-with-deductions
     * Process payroll including children school fee deductions
     */
    public function postProcessPayrollWithDeductions($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant'])) return $denied;
        $eligibilityPayload = $data ?: $this->getRequestData();
        if ($invalid = $this->validatePayrollPayloadEligibility($eligibilityPayload)) return $invalid;
        $payload = $eligibilityPayload;
        if (!$this->isPayrollReviewer()) {
            // Accountants prepare the draft from the approved salary profile and
            // statutory configuration. Review-stage compensation and deductions
            // belong to the director/school administrator.
            $payload['allowances'] = [];
            $payload['other_deductions'] = 0;
            $payload['children_deductions'] = [];
            $payload['preparation_only'] = true;
            unset($payload['source_financial_account_id'], $payload['salary_advance_id']);
        }
        $result = $this->api->processPayrollWithDeductions($payload);
        return $this->handleResponse($result);
    }

    private function isPayrollReviewer(): bool
    {
        $roles = $this->staffAccess->roles();
        if (in_array('accountant', $roles, true)) {
            return false;
        }
        return $this->staffAccess->allows('staff.payroll.approve', ['director', 'school administrator', 'system administrator'])
            || $this->userHasAny(['finance.approve', 'payroll_approve'], [3], ['director', 'school administrator', 'system administrator']);
    }

    /**
     * GET /api/finance/detailed-payslip?payroll_id=X
     * Get detailed payslip with children fee deductions breakdown
     */
    public function getDetailedPayslip($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $payrollId = $_GET['payroll_id'] ?? $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $result = $this->api->getDetailedPayslip($payrollId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-stats?month=X&year=Y
     * Get payroll statistics for dashboard
     */
    public function getPayrollStats($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');

        $result = $this->api->getPayrollStats($month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-list
     * Get filtered payroll records
     */
    public function getPayrollList($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.manage', ['system administrator','school administrator','accountant','director'])) return $denied;
        $filters = array_merge($_GET, $data);
        $result = $this->api->getPayrollList($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/mark-payroll-paid
     * Mark payroll as paid and record children fee payments
     */
    public function postMarkPayrollPaid($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requirePayrollPermission('staff.payroll.process', ['system administrator','director','school administrator'])) return $denied;
        $payrollId = $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $paymentRef = $data['payment_reference'] ?? '';
        $paymentMode = $data['payment_mode'] ?? 'bank';
        $data['user_id'] = $this->getUserId();
        $sourceAccountId = $data['source_financial_account_id'] ?? null;
        $selectedPayslipIds = array_values(array_filter(array_map('intval', (array)($data['payslip_ids'] ?? []))));
        $result = $this->api->markPayrollPaid($payrollId, $paymentRef, $paymentMode, $sourceAccountId, $data['user_id'], $selectedPayslipIds);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2C: Allowance Templates
    // ========================================

    /**
     * GET /api/finance/allowance-templates
     * List all allowance templates
     */
    public function getAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->allowanceTemplateApi->list($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/allowance-templates/{id}
     * Get a single allowance template
     */
    public function getAllowanceTemplatesGet($id = null, $data = [], $segments = [])
    {
        $templateId = $id ?? $data['id'] ?? null;
        if (!$templateId) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->get($templateId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/allowance-templates
     * Create a new allowance template
     */
    public function postAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        $result = $this->allowanceTemplateApi->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/allowance-templates/{id}
     * Update an allowance template
     */
    public function putAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/allowance-templates/{id}
     * Deactivate an allowance template
     */
    public function deleteAllowanceTemplates($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->delete($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/allowance-templates/{id}/applicable-staff
     * Preview which staff match a template's criteria
     */
    public function getAllowanceTemplatesApplicableStaff($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $result = $this->allowanceTemplateApi->getApplicableStaff($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/allowance-templates/{id}/apply
     * Apply a template to matching staff, bulk-creating staff_allowances rows
     */
    public function postAllowanceTemplatesApply($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Template ID is required');
        }
        $staffIds = $data['staff_ids'] ?? null;
        $result = $this->allowanceTemplateApi->applyToStaff($id, $staffIds);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2D: Fee Invoice Generation
    // ========================================

    /**
     * POST /api/finance/fee-invoices/generate
     */
    public function postFeeInvoicesGenerate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoice($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-invoices/generate-batch
     */
    public function postFeeInvoicesGenerateBatch($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoicesBatch($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-invoices/get?student_id=X
     */
    public function getFeeInvoicesGet($id = null, $data = [], $segments = [])
    {
        $params = array_merge($_GET, $data);
        $result = $this->api->getFeeInvoice($params);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Payment & Receipt Operations
    // ========================================

    /**
     * POST /api/finance/payments/generate-receipt
     */
    public function postPaymentsGenerateReceipt($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? $id ?? null;
        
        if ($paymentId === null) {
            return $this->badRequest('Payment ID is required');
        }
        
        $result = $this->api->generateReceipt($paymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/generate-payslip
     */
    public function postPaymentsGeneratePayslip($id = null, $data = [], $segments = [])
    {
        $staffPaymentId = $data['staff_payment_id'] ?? $id ?? null;
        
        if ($staffPaymentId === null) {
            return $this->badRequest('Staff payment ID is required');
        }
        
        $result = $this->api->generatePayslip($staffPaymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/send-notification
     */
    public function postPaymentsSendNotification($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? null;
        $recipient = $data['recipient'] ?? null;
        $method = $data['method'] ?? 'email';

        if ($paymentId === null || $recipient === null) {
            return $this->badRequest('Payment ID and recipient are required');
        }

        $result = $this->api->sendPaymentNotification($paymentId, $recipient, $method);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Fee Structure Operations
    // ========================================

    /**
     * GET /api/finance/student-types-list
     */
    public function getStudentTypesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listStudentTypes();
        return $this->handleResponse($result);
    }

    /**
        
     * POST /api/finance/fees/create-annual-structure
     */
    public function postFeesCreateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/review-structure
     */
    public function postFeesReviewStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/approve-structure
     */
    public function postFeesApproveStructure($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve fee structures')) return $denied;
        $data['approved_by'] = $data['approved_by'] ?? $this->getUserId();
        $result = $this->api->approveFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/activate-structure
     */
    public function postFeesActivateStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->activateFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/deactivate-structure
     */
    public function postFeesDeactivateStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deactivateFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/rollover-structure
     */
    public function postFeesRolloverStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->rolloverFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/update-annual-structure
     */
    public function postFeesUpdateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/delete-annual-structure
     */
    public function postFeesDeleteAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/term-breakdown
     */
    public function getFeesTermBreakdown($id = null, $data = [], $segments = [])
    {
        $academicYear = $_GET['academic_year'] ?? $data['academic_year'] ?? null;
        $term = $_GET['term'] ?? $data['term'] ?? null;

        if ($academicYear === null || $term === null) {
            return $this->badRequest('Academic year and term are required');
        }

        $result = $this->api->getTermBreakdown($academicYear, $term);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/pending-reviews
     */
    public function getFeesPendingReviews($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        $result = $this->api->getPendingReviews();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/annual-summary
     */
    public function getFeesAnnualSummary($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        $academicYear = $_GET['academic_year'] ?? $data['academic_year'] ?? null;

        if ($academicYear === null) {
            return $this->badRequest('Academic year is required');
        }

        $result = $this->api->getAnnualFeeSummary($academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/list
     * Get fee structures with permission-aware filtering
     */
    public function getFeeStructuresList($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view', 'fee_structure_view', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        $filters = array_merge($_GET, $data);
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 20;

        $result = $this->api->listFeeStructures($filters, $page, $limit);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/{id}
     * Get a specific fee structure with details
     */
    public function getFeeStructuresGet($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['finance.view', 'finance_view', 'fee_structure_view', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions');
        }
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->getFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures
     * Create a new fee structure
     */
    public function postFeesStructures($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_manage', 'fee_structure_edit', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions to manage fee structures');
        }
        $result = $this->api->createFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/fee-structures/{id}
     * Update a fee structure
     */
    public function putFeeStructures($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_manage', 'fee_structure_edit', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions to manage fee structures');
        }
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->updateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/fee-structures/{id}
     * Delete a fee structure
     */
    public function deleteFeeStructures($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_manage', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions to manage fee structures');
        }
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->deleteFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures/{id}/duplicate
     * Duplicate a fee structure for a new academic year
     */
    public function postFeeStructuresDuplicate($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_manage', 'finance.manage', 'finance_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions to manage fee structures');
        }
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->duplicateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Student Payment History & Fee Statement
    // ========================================

    /**
     * GET /api/finance/students/payment-history
     */
    public function getStudentsPaymentHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        $academicYear = $data['academic_year'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentPaymentHistory($studentId, $academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/payment-status
     * List student payment status with filters
     */
    public function getStudentsPaymentStatus($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET ?? [], $data ?? []);

        if ($id !== null) {
            $filters['student_id'] = $id;
        }

        $result = $this->api->listStudentPaymentStatus($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/fee-statement/{id}
     * Get complete fee statement for a student
     */
    public function getStudentsFeeStatement($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        $academicYear = $data['academic_year'] ?? $_GET['academic_year'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        // If no academic year provided, get current one
        if ($academicYear === null) {
            $academicYear = $this->getCurrentAcademicYear();
        }

        $result = $this->api->handleCustomGet($studentId, 'statement', ['academic_year' => $academicYear]);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/balance/{id}
     * Get current fee balance for a student
     */
    public function getStudentsBalance($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->handleCustomGet($studentId, 'balance', []);
        return $this->handleResponse($result);
    }

    /**
     * Helper: Get current academic year
     */
    private function getCurrentAcademicYear()
    {
        return $this->api->getCurrentAcademicYearCode();
    }

    // ========================================
    // SECTION 6: Reporting Operations
    // ========================================

    /**
     * GET /api/finance/reports — summary of available reports + recent totals
     */
    public function getReports($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getFinancialSummaryReport();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/reports/generate-payroll
     */
    public function postReportsGeneratePayroll($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generatePayrollReport($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/reports/compare-yearly-collections
     */
    public function getReportsCompareYearlyCollections($id = null, $data = [], $segments = [])
    {
        $year1 = $data['year1'] ?? null;
        $year2 = $data['year2'] ?? null;
        
        if ($year1 === null || $year2 === null) {
            return $this->badRequest('Both years are required for comparison');
        }
        
        $result = $this->api->compareYearlyCollections($year1, $year2);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Helper Methods
    // ========================================

    /**
     * GET /api/finance/reconciliation/unreconciled
     * Wrapper to list unreconciled transactions for accountant dashboard
     */
    public function getReconciliationUnreconciled($id = null, $data = [], $segments = [])
    {
        // Require authentication + permission
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (
            !$this->userHasAny(
                ['finance.reconcile', 'finance_reconcile', 'finance.view', 'finance_view'],
                [10],
                ['accountant', 'finance', 'admin']
            )
        ) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $recon = $this->contract('App\API\Modules\finance\PaymentReconciliationAPI');
            $result = $recon->listUnreconciled($data);
            return $this->handleResponse($result);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

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
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
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
            // Check if result is from formatResponse (has 'code' and 'status' keys)
            if (isset($result['code']) && isset($result['status'])) {
                $code = $result['code'];
                $message = $result['message'] ?? 'Operation completed';
                $data = $result['data'] ?? null;

                // Route based on HTTP status code
                if ($code >= 200 && $code < 300) {
                    return $this->success($data, $message);
                } elseif ($code === 404) {
                    return $this->notFound($message);
                } elseif ($code === 401) {
                    return $this->unauthorized($message);
                } elseif ($code === 403) {
                    return $this->forbidden($message);
                } elseif ($code >= 500) {
                    return $this->serverError($message);
                } else {
                    return $this->badRequest($message);
                }
            }

            // Legacy format with 'success' key
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $message = $result['error'] ?? $result['message'] ?? 'Operation failed';
                    if (stripos($message, 'not found') !== false) {
                        return $this->notFound($message);
                    }
                    return $this->badRequest($message);
                }
            }
            
            return $this->success($result);
        }

        return $this->success($result);
    }

    // ========================================
    // SECTION 8: Fee Bundle Workflow
    // ========================================

    /**
     * POST /api/finance/fees-bundle-submit
     * Accountant submits a fee structure bundle for director review
     */
    public function postFeesBundleSubmit($id = null, $data = [], $segments = [])
    {
        if (!empty($data['student_type_ids']) && !empty($data['academic_year'])) {
            $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
            $data['submitted_by'] = $userId;
            $result = $this->api->submitFeeStructureBundleBatch($data);
            return $this->handleResponse($result);
        }
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['submitted_by'] = $userId;
        $result = $this->api->submitFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-review/{id}
     * Finance manager reviews a submitted bundle
     */
    public function postFeesBundleReview($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        // Authenticated users may carry one or more roles in the JWT. Do not
        // rely only on the legacy singular role_id field; BaseController
        // normalizes role objects and role_ids for multi-role accounts.
        if (!$this->userHasAny([], [3, 4, 5], [])) {
            return $this->forbidden('Only the Headteacher, School Administrator, or Director may review fee structures');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['reviewed_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->reviewFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-approve/{id}
     * Director approves or rejects a fee structure bundle.
     * On approval, automatically generates student_fee_obligations for all affected students.
     */
    public function postFeesBundleApprove($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        if (!$this->userHasAny([], [3, 4], [])) {
            return $this->forbidden('Only the School Administrator or Director may final-approve fee structures');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['approved_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->approveFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees-bundle-list
     * List all fee structure bundles with status, for director review queue
     * Query params: status, academic_year, term_id, level_id, page, limit
     */
    public function getFeesBundleList($id = null, $data = [], $segments = [])
    {
        $filters = [
            'status'        => $data['status'] ?? $_GET['status'] ?? null,
            'academic_year' => $data['academic_year'] ?? $_GET['academic_year'] ?? null,
            'term_id'       => $data['term_id'] ?? $_GET['term_id'] ?? null,
            'level_id'      => $data['level_id'] ?? $_GET['level_id'] ?? null,
            'student_type_id' => $data['student_type_id'] ?? $_GET['student_type_id'] ?? null,
            'page'          => (int)($data['page'] ?? $_GET['page'] ?? 1),
            'limit'         => (int)($data['limit'] ?? $_GET['limit'] ?? 20),
        ];
        $result = $this->api->getFeeStructureBundles($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-activate-generate-obligations
     * Manually trigger obligation generation for an approved bundle
     */
    public function postFeesActivateGenerateObligations($id = null, $data = [], $segments = [])
    {
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->activateAndGenerateObligations(
            $data['level_id'], $data['academic_year'], $data['term_id'], $data['student_type_id'], $userId
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-create-bundle
     * Create (or re-create) a grade-range fee structure bundle.
     * Body: academic_year, grade_range {from_id,to_id}, student_type_ids[],
     *       items { CODE: { termN: { studentTypeId: amount } } }
     */
    public function postFeesCreateBundle($id = null, $data = [], $segments = [])
    {
        if (empty($data['academic_year']) || empty($data['grade_range']) || empty($data['items'])) {
            return $this->badRequest('academic_year, grade_range and items are required');
        }
        if (empty($data['student_type_ids'])) {
            return $this->badRequest('At least one student_type_id is required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['created_by'] = $userId;
        $result = $this->api->createFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees-bundle-grid
     * Read an existing grade-range bundle as a tabular grid (for edit/view).
     * Query: academic_year, from_id, to_id, student_type_ids[]
     */
    public function getFeesBundleGrid($id = null, $data = [], $segments = [])
    {
        $data = array_merge($_GET, $data);
        $gradeRange = [
            'from_id' => $data['from_id'] ?? $data['grade_range']['from_id'] ?? null,
            'to_id' => $data['to_id'] ?? $data['grade_range']['to_id'] ?? null,
        ];
        if (empty($data['academic_year']) || empty($gradeRange['from_id']) || empty($gradeRange['to_id'])) {
            return $this->badRequest('academic_year, from_id and to_id are required');
        }
        $result = $this->api->getFeeStructureBundleGrid([
            'academic_year' => $data['academic_year'],
            'grade_range' => $gradeRange,
            'student_type_ids' => $data['student_type_ids'] ?? null,
        ]);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Student Billing History
    // ========================================

    /**
     * GET /api/finance/students-billing-history/{id}
     * Full billing history for a student across all years and terms
     */
    public function getStudentsBillingHistory($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        $result = $this->api->getStudentBillingHistory((int)$id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/class-billing-report/{id}
     * Class-level billing report — all students, their balances and payment status
     * Query params: academic_year_id (required), term_id (optional)
     */
    public function getClassBillingReport($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('class_id required');
        $academicYearId = $data['academic_year_id'] ?? $_GET['academic_year_id'] ?? null;
        if (!$academicYearId) return $this->badRequest('academic_year_id required');
        $termId = $data['term_id'] ?? $_GET['term_id'] ?? null;
        $result = $this->api->getClassBillingReport((int)$id, (int)$academicYearId, $termId ? (int)$termId : null);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 10: Expense Management
    // ========================================

    /** GET /api/finance/expenses — list all expenses with filters */
    public function getExpenses($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $result = $this->api->getExpenseDetailed((int)$id);
            if (($result['code'] ?? 200) >= 400) {
                return $this->notFound('Expense not found');
            }
            $row = $result['data'] ?? null;
            return $row ? $this->success($row) : $this->notFound('Expense not found');
        }

        $result = $this->api->listExpensesWithStats($data);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/expenses — create expense */
    public function postExpenses($id = null, $data = [], $segments = [])
    {
        if (empty($data['description']) || empty($data['amount']) || empty($data['expense_date'])) {
            return $this->badRequest('description, amount, expense_date are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->createExpense($data, $userId);
        return $this->success($result, 'Expense recorded successfully');
    }

    /** PUT /api/finance/expenses/{id} — update or change status */
    public function putExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');

        if (isset($data['status'])) {
            if ($data['status'] === 'approved') return $this->postExpensesApprove($id, $data, $segments);
            if ($data['status'] === 'rejected')  return $this->postExpensesReject($id, $data, $segments);
            if ($data['status'] === 'pending_approval') {
                $this->crud->setExpenseStatus($id, 'pending_approval');
                return $this->success(null, 'Expense submitted for approval');
            }
        }

        if (empty($data)) return $this->badRequest('Nothing to update');
        $this->crud->updateExpense((int)$id, $data);
        return $this->success(null, 'Expense updated');
    }

    /** DELETE /api/finance/expenses/{id} — soft delete */
    public function deleteExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');
        $this->crud->softDeleteExpense((int)$id);
        return $this->success(null, 'Expense deleted');
    }

    /** GET /api/finance/expense-categories — list all expense categories */
    public function getExpenseCategories($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listExpenseCategories());
    }

    // ========================================
    // SECTION 11: Petty Cash
    // ========================================

    /** GET /api/finance/petty-cash — list transactions + fund summary */
    public function getPettyCash($id = null, $data = [], $segments = [])
    {
        $fundId = $data['fund_id'] ?? 1;
        $fund = $this->crud->getPettyCashFund($fundId);
        if (!$fund) return $this->notFound('Petty cash fund not found');
        $result = $this->crud->listPettyCashTransactions($fundId, $data);
        return $this->success(['fund' => $fund, 'transactions' => $result['transactions'], 'stats' => $result['stats']]);
    }

    /** POST /api/finance/petty-cash — record a petty cash transaction */
    public function postPettyCash($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['description'])) {
            return $this->badRequest('type, amount, description are required');
        }
        $fundId = $data['fund_id'] ?? 1;
        $fund = $this->crud->getPettyCashFund($fundId);
        if (!$fund) return $this->notFound('Petty cash fund not found');
        if ($data['type'] === 'expense' && $fund['current_balance'] - $data['amount'] < 0) {
            return $this->badRequest('Insufficient petty cash balance');
        }
        $userId = $this->getUserId();
        $balanceAfter = $this->crud->createPettyCashTransaction($data, $fundId, $userId);
        return $this->success(['balance_after' => $balanceAfter], 'Petty cash transaction recorded');
    }

    // ========================================
    // SECTION 12: Cash Reconciliation
    // ========================================

    /** GET /api/finance/cash-reconciliation — list sessions or get one by date or id */
    public function getCashReconciliation($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->success($this->crud->getCashReconciliationById($id));
        }
        if (!empty($data['date'])) {
            $session = $this->crud->getCashReconciliationByDate($data['date']);
            return $this->success($session ?: null);
        }
        return $this->success($this->crud->listCashReconciliationSessions());
    }

    /** POST /api/finance/cash-reconciliation — submit a daily cash count */
    public function postCashReconciliation($id = null, $data = [], $segments = [])
    {
        if (empty($data['reconciliation_date']) || !isset($data['system_cash_total']) || !isset($data['physical_cash_count'])) {
            return $this->badRequest('reconciliation_date, system_cash_total, physical_cash_count are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->upsertCashReconciliation($data, $userId);
        return $this->success($result, isset($result['id']) ? 'Cash reconciliation submitted' : 'Reconciliation updated');
    }

    // ========================================
    // SECTION 13: Financial Adjustments
    // ========================================

    /** GET /api/finance/adjustments — list all adjustments */
    public function getAdjustments($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listAdjustments($data));
    }

    /** POST /api/finance/adjustments — create adjustment */
    public function postAdjustments($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['reason'])) {
            return $this->badRequest('type, amount, reason are required');
        }
        $userId = $this->getUserId();
        $result = $this->crud->createAdjustment($data, $userId);
        return $this->success($result, 'Adjustment submitted');
    }

    /** PUT /api/finance/adjustments/{id} — approve/reject/apply */
    public function putAdjustments($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Adjustment ID required');
        $userId = $this->getUserId();
        $status = $data['status'] ?? null;
        if ($status === 'approved') {
            $this->crud->setAdjustmentStatus((int)$id, 'approved', $userId);
            return $this->success(null, 'Adjustment approved');
        }
        if ($status === 'rejected') {
            if (empty($data['rejection_reason'])) return $this->badRequest('rejection_reason required');
            $this->crud->setAdjustmentStatus((int)$id, 'rejected', $userId, $data['rejection_reason']);
            return $this->success(null, 'Adjustment rejected');
        }
        return $this->badRequest('Unknown status: '.$status);
    }

    // ========================================
    // SECTION 14: Exception Reports
    // ========================================

    /** GET /api/finance/exception-reports — list flagged exceptions */
    public function getExceptionReports($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listExceptionReports($data));
    }

    /** PUT /api/finance/exception-reports/{id} — update status */
    public function putExceptionReports($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Exception ID required');
        $userId = $this->getUserId();
        $this->crud->updateExceptionStatus((int)$id, $data['status'] ?? 'under_review', $userId, $data['resolution_notes'] ?? null);
        return $this->success(null, 'Exception status updated');
    }

    // ========================================
    // SECTION 15: Budgets CRUD
    // ========================================

    /** GET /api/finance/budgets — list all budgets */
    public function getBudgets($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $result = $this->crud->getBudget((int)$id);
            if (!$result) return $this->notFound('Budget not found');
            return $this->success($result);
        }
        return $this->success($this->crud->listBudgets());
    }

    /** POST /api/finance/budgets — create budget */
    public function postBudgets($id = null, $data = [], $segments = [])
    {
        if (empty($data['name']) || empty($data['academic_year'])) {
            return $this->badRequest('name and academic_year are required');
        }
        $userId = $this->getUserId();
        $budgetId = $this->crud->createBudget($data, $userId);
        return $this->success(['id' => $budgetId], 'Budget created');
    }

    /** PUT /api/finance/budgets/{id} — update or approve/submit budget */
    public function putBudgets($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Budget ID required');
        $userId = $this->getUserId();
        $status = $data['status'] ?? null;
        if ($status) {
            $this->crud->updateBudgetStatus((int)$id, $status, $userId);
            return $this->success(null, 'Budget status updated to '.$status);
        }
        $this->crud->updateBudget((int)$id, $data);
        return $this->success(null, 'Budget updated');
    }

    // ========================================
    // SECTION 16: Fee Waivers / Discounts
    // ========================================

    /** GET /api/finance/fee-waivers — list all discounts/waivers */
    public function getFeeWaivers($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listFeeWaivers($data));
    }

    /** POST /api/finance/fee-waivers — create waiver/discount */
    public function postFeeWaivers($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireFeeReliefAccess('create fee waivers')) return $denied;
        if (empty($data['student_id']) || empty($data['discount_type']) || !isset($data['discount_value'])) {
            return $this->badRequest('student_id, discount_type, discount_value are required');
        }
        if (empty($data['reason'])) return $this->badRequest('reason is required');
        $userId = $this->getUserId();
        $id = $this->crud->createFeeWaiver($data, $userId);
        return $this->success(['id' => $id], 'Waiver created successfully');
    }

    // ========================================
    // SECTION 17: Sponsored Students
    // ========================================

    /** GET /api/finance/sponsored-students — list sponsored students */
    public function getSponsoredStudents($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listSponsoredStudents());
    }

    /** GET /api/finance/scholarship-programs */
    public function getScholarshipPrograms($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listScholarshipPrograms());
    }

    /** GET /api/finance/student-scholarships */
    public function getStudentScholarships($id = null, $data = [], $segments = [])
    {
        return $this->success($this->crud->listStudentScholarships($data ?: $_GET));
    }

    /** POST /api/finance/student-scholarships */
    public function postStudentScholarships($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireFeeReliefAccess('manage student scholarships')) return $denied;
        try {
            $awardId = $this->crud->createStudentScholarship($data, $this->getUserId());
            return $this->created(['id' => $awardId], 'Student scholarship saved and applied to this academic year.');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] scholarship create: ' . $e->getMessage());
            return $this->serverError('Unable to save student scholarship.');
        }
    }

    /** DELETE /api/finance/student-scholarships/{id} */
    public function deleteStudentScholarships($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireFeeReliefAccess('revoke student scholarships')) return $denied;
        if (!$id) return $this->badRequest('Scholarship award id is required');
        try {
            $this->crud->revokeStudentScholarship((int)$id, $this->getUserId());
            return $this->success(null, 'Student scholarship revoked.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[FinanceController] scholarship revoke: ' . $e->getMessage());
            return $this->serverError('Unable to revoke student scholarship.');
        }
    }

    // ==================== FEE CREDIT NOTES ====================

    public function getFeeCredits($id = null, $data = [], $segments = [])
    {
        $studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
        $allowedStatuses = ['available', 'partially_applied', 'applied', 'expired'];
        $status = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : null;
        $filters = ['student_id' => $studentId, 'status' => $status];
        return $this->success($this->crud->listFeeCredits($filters));
    }

    public function postFeeCredits($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || empty($data['credit_amount'])) {
            return $this->badRequest('student_id and credit_amount are required');
        }
        $userId = $this->user['id'] ?? null;
        $result = $this->crud->createFeeCredit($data, $userId);
        return $this->success($result, 201);
    }

    public function putFeeCredits($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Credit note id is required');
        }
        $action = $data['action'] ?? 'apply';
        $credit = $this->crud->getFeeCredit((int)$id);
        if (!$credit) return $this->error('Credit note not found', 404);

        if ($action === 'refund') {
            try {
                $request = ($this->contract('App\API\Services\payments\ParentRefundService', Database::getInstance()->getConnection()))->createRequest((int) $id, (int) $this->getUserId(), $data);
                return $this->success($request, 'Refund submitted for approval; no money has been sent yet.');
            } catch (\Throwable $e) {
                return $this->badRequest($e->getMessage());
            }
        }

        $applyAmount = min((float)($data['apply_amount'] ?? 0), (float)$credit['remaining_amount']);
        if ($applyAmount <= 0) {
            return $this->badRequest('apply_amount must be greater than zero');
        }
        $this->crud->applyFeeCredit((int)$id, $applyAmount, $data);
        return $this->success(['applied' => $applyAmount]);
    }

    // ==================== SALARY ADVANCES ====================

    public function getSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : null;
        $allowedStatuses = ['pending', 'approved', 'rejected', 'active', 'fully_deducted'];
        $status = isset($_GET['status']) && in_array($_GET['status'], $allowedStatuses, true) ? $_GET['status'] : null;
        $filters = ['staff_id' => $staffId, 'status' => $status];
        return $this->success($this->crud->listSalaryAdvances($filters));
    }

    public function postSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $amount  = $data['requested_amount'] ?? null;
        if (!$staffId || !$amount) {
            return $this->badRequest('staff_id and requested_amount are required');
        }

        $existing = (float)$this->crud->getActiveAdvanceBalance((int)$staffId);
        $salary = (float)$this->crud->getStaffBasicSalary((int)$staffId);
        if ($salary > 0 && ($existing + (float)$amount) > $salary) {
            return $this->error(
                "Advance exceeds limit. Active balance: KES " . number_format($existing, 2) .
                ". Max (1 month salary): KES " . number_format($salary, 2), 422
            );
        }

        $advId = $this->crud->createSalaryAdvance($data);
        return $this->success(['id' => $advId], 201);
    }

    public function putSalaryAdvances($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Advance id is required');
        }
        $action = $data['action'] ?? null;
        $userId = $this->user['id'] ?? null;
        $advance = $this->crud->getSalaryAdvance((int)$id);
        if (!$advance) return $this->error('Advance not found', 404);

        if ($action === 'approve') {
            $approved = $data['approved_amount'] ?? $advance['requested_amount'];
            $months   = ['single_month' => 1, 'two_months' => 2, 'three_months' => 3][$advance['deduction_schedule']] ?? 1;
            $perDed   = round($approved / $months, 2);
            $start    = $data['deduction_start_month'] ?? date('Y-m-01', strtotime('first day of next month'));
            $this->crud->approveSalaryAdvance((int)$id, $approved, $perDed, $start, $userId);
            return $this->success(['approved' => true, 'per_deduction' => $perDed]);
        }
        if ($action === 'reject') {
            $this->crud->rejectSalaryAdvance((int)$id, $data['reason'] ?? null);
            return $this->success(['rejected' => true]);
        }
        if ($action === 'record_deduction') {
            $amt        = min((float)($data['amount'] ?? $advance['amount_per_deduction']), (float)$advance['balance_remaining']);
            $newBalance = max(0, (float)$advance['balance_remaining'] - $amt);
            $newStatus  = $newBalance <= 0 ? 'fully_deducted' : 'active';
            $this->crud->recordSalaryAdvanceDeduction((int)$id, $amt, $newBalance, $newStatus);
            return $this->success(['deducted' => $amt, 'remaining' => $newBalance]);
        }
        return $this->badRequest('Unknown action');
    }

    /**
     * GET /api/finance/unmatched-payments
     */
    public function getUnmatchedPayments($id = null, $data = [], $segments = [])
    {
        try {
            $page  = max(1, (int) ($_GET['page']  ?? $data['page']  ?? 1));
            $limit = max(1, min(200, (int) ($_GET['limit'] ?? $data['limit'] ?? 50)));
            $result = $this->crud->listUnmatchedPayments($page, $limit);
            return $this->success([
                'data'        => $result['data'],
                'total'       => $result['total'],
                'page'        => $page,
                'per_page'    => $limit,
                'total_pages' => (int) ceil($result['total'] / $limit),
            ]);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('getUnmatchedPayments: ' . $e->getMessage());
            \App\API\Services\Logger::legacyError('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    /**
     * POST /api/finance/unmatched-payments/match
     */
    public function postUnmatchedPaymentsMatch($id = null, $data = [], $segments = [])
    {
        $paymentId  = $data['payment_id']  ?? null;
        $studentId  = $data['student_id']  ?? null;
        $obligationId = $data['obligation_id'] ?? null;

        if (!$paymentId) {
            return $this->badRequest('payment_id is required');
        }

        try {
            $this->crud->matchPayment((int)$paymentId, $studentId ? (int)$studentId : null, $obligationId ? (int)$obligationId : null);
            return $this->success(null, 'Payment matched successfully');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('postUnmatchedPaymentsMatch: ' . $e->getMessage());
            \App\API\Services\Logger::legacyError('[FinanceController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->error('An internal error occurred.');
        }
    }

    // =========================================================================
    // EXTRA CHARGES — flexible charges on the fee structure
    // =========================================================================

    /** GET /api/finance/extra-charges[/{id}] */
    public function getExtraCharges($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_view', 'fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        // Router resolves GET /extra-charges/{id} to this list method with the
        // numeric id as first argument — delegate to the single-charge handler.
        if ($id !== null && $id !== '' && is_numeric($id)) {
            return $this->getExtraChargesGet($id, $data, $segments);
        }
        $filters = $_GET;
        $result = $this->api->getExtraCharges($filters);
        return $this->handleResponse($result);
    }

    /** GET /api/finance/extra-charges/{id} */
    public function getExtraChargesGet($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_view', 'fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $result = $this->api->getExtraCharge((int) $id);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/extra-charges */
    public function postExtraCharges($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_edit', 'fee_structure_manage', 'fee_structure_create'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->createExtraCharge($data, (int) $userId);
        return $this->handleResponse($result);
    }

    /** PUT /api/finance/extra-charges/{id} */
    public function putExtraCharges($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->updateExtraCharge((int) $id, $data, (int) $userId);
        return $this->handleResponse($result);
    }

    /** DELETE /api/finance/extra-charges/{id} */
    public function deleteExtraCharges($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_manage', 'fee_structure_delete'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->deleteExtraCharge((int) $id, (int) $userId);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/extra-charges/{id}/submit */
    public function postExtraChargesSubmit($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->submitExtraCharge((int) $id, (int) $userId);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/extra-charges/approve/{id} */
    public function postExtraChargesApprove($id = null, $data = [], $segments = [])
    {
        // Approval authority: Director, School Administrator (and System Administrator) only.
        if (!$this->userHasAny(['fee_structure_approve', 'fee_structure_manage'], [2, 3, 4])) {
            return $this->forbidden('Only the School Administrator or Director can approve fee structure items.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $notes = $data['notes'] ?? '';
        $result = $this->api->approveExtraCharge((int) $id, (int) $userId, $notes);
        return $this->handleResponse($result);
    }

    /** POST /api/finance/extra-charges/reject/{id} */
    public function postExtraChargesReject($id = null, $data = [], $segments = [])
    {
        // Rejection authority mirrors approval: Director / School Administrator only.
        if (!$this->userHasAny(['fee_structure_approve', 'fee_structure_manage'], [2, 3, 4])) {
            return $this->forbidden('Only the School Administrator or Director can reject fee structure items.');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $notes = $data['notes'] ?? '';
        $result = $this->api->rejectExtraCharge((int) $id, (int) $userId, $notes);
        return $this->handleResponse($result);
    }

    /** GET /api/finance/extra-charges/academic-years */
    public function getExtraChargesAcademicYears($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_view', 'fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $result = $this->api->getAcademicYearsList();
        return $this->handleResponse($result);
    }

    /** GET /api/finance/extra-charges/gl-accounts */
    public function getExtraChargesGlAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->userHasAny(['fee_structure_view', 'fee_structure_edit', 'fee_structure_manage'], [3, 4, 10])) {
            return $this->forbidden('Insufficient permissions.');
        }
        $result = $this->api->getGLAccounts();
        return $this->handleResponse($result);
    }
}
