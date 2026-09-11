<?php
/**
 * PaymentsController - Exposes RESTful endpoints for all payment webhooks
 */
namespace App\API\Controllers;

use App\API\Modules\payments\PaymentsAPI;

class PaymentsController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\payments\PaymentsAPI');
    }

    public function index()
    {
        return $this->success(['message' => 'Payments API is running']);
    }

    /**
     * GET /api/payments/trends - Alias for collection trends
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        return $this->getCollectionTrends($id, $data, $segments);
    }

    /**
     * GET /api/payments/collections - Cash collections for the cash reconciliation screen
     */
    public function getCollections($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getCollections($data));
    }

    /**
     * GET /api/payments/revenue-sources - Returns revenue sources breakdown
     */
    public function getRevenueSources($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getRevenueSources(array_merge($_GET ?? [], $data ?? [])));
    }

    /**
     * GET /api/payments/stats - Get fees collection statistics for dashboard
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getFeeStats(array_merge($_GET ?? [], $data ?? [])));
    }

    /**
     * GET /api/payments/collection-trends - Get fee collection trends over time
     * SECURITY: Director and Finance roles only
     */
    public function getCollectionTrends($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getCollectionTrends(array_merge($_GET ?? [], $data ?? [])));
    }

    /**
     * Standard API response handler
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            $code = (int) ($result['code'] ?? 200);
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                // Provider-accepted asynchronous operations are intentionally
                // returned as pending (for example Buni STK). They are still
                // successful API submissions; the final state arrives via IPN.
                $responseData = array_key_exists('data', $result) ? $result['data'] : $result;
                return in_array($result['status'], ['success', 'pending'], true)
                    ? $this->success($responseData, $result['message'] ?? 'Operation successful')
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    private function mapError($code, $message, $data = [])
    {
        if ($code === 502) {
            return $this->respond($data, $message, 502, false);
        }
        if ($code === 503) {
            return $this->respond($data, $message, 503, false);
        }
        if ($code >= 500) {
            return $this->serverError($message, $data);
        }
        if ($code === 404) {
            return $this->notFound($message);
        }
        if ($code === 401) {
            return $this->unauthorized($message);
        }
        if ($code === 403) {
            return $this->forbidden($message);
        }
        return $this->badRequest($message, $data);
    }

    /**
     * POST /api/payments/mpesa-b2c-callback
     */
    public function postMpesaB2cCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-b2c-timeout
     */
    public function postMpesaB2cTimeout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CTimeout($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/c2b-validation
     * M-Pesa-free alias for POST /api/payments/mpesa-c2b-validation.
     * Safaricom's C2B URL registration rejects URLs containing the word
     * "mpesa", so the registered callback must use this clean path.
     */
    public function postC2bValidation($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaC2BValidation($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/c2b-confirmation
     * M-Pesa-free alias for POST /api/payments/mpesa-c2b-confirmation.
     */
    public function postC2bConfirmation($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaC2BConfirmation($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-c2b-validation
     */
    public function postMpesaC2bValidation($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaC2BValidation($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-c2b-confirmation
     * FIX: Pass actual request headers for webhook signature validation
     */
    public function postMpesaC2bConfirmation($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaC2BConfirmation($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-stk-callback
     */
    public function postMpesaStkCallback($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaStkCallback($data, $headers);
        return $this->handleResponse($result);
    }

    /** POST /api/payments/kcb-mpesa-express-callback */
    public function postKcbMpesaExpressCallback($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers['__raw_body'] = (string)(file_get_contents('php://input') ?: json_encode($data));
        return $this->handleResponse($this->api->processKcbMpesaExpressCallback($data, $headers));
    }

    /**
     * POST /api/payments/mpesa-result
     * Generic M-Pesa result sink (transaction status, account balance,
     * reversal, B2B).
     */
    public function postMpesaResult($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processMpesaResult($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-validation
     * FIX: Pass actual request headers for webhook signature validation
     */
    public function postKcbValidation($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processKcbValidation($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-transfer-callback
     * FIX: Pass actual request headers for webhook signature validation
     */
    public function postKcbTransferCallback($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers['__raw_body'] = (string) (file_get_contents('php://input') ?: json_encode($data));
        $result = $this->api->processKcbTransferCallback($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-notification
     * FIX: Pass actual request headers for webhook signature validation
     */
    public function postKcbNotification($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $headers['__raw_body'] = (string) (file_get_contents('php://input') ?: json_encode($data));
        $result = $this->api->processKcbNotification($data, $headers);
        return $this->handleResponse($result);
    }

    /** POST /api/payments/kcb-account-notification */
    public function postKcbAccountNotification($id = null, $data = [], $segments = [])
    {
        return $this->postKcbNotification($id, $data, $segments);
    }

    /** POST /api/payments/kcb-till-notification */
    public function postKcbTillNotification($id = null, $data = [], $segments = [])
    {
        return $this->postKcbNotification($id, $data, $segments);
    }

    /**
     * POST /api/payments/bank-webhook
     * FIX: Pass actual request headers for webhook signature validation
     */
    public function postBankWebhook($id = null, $data = [], $segments = [])
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $result = $this->api->processBankWebhook($data, $headers);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/payments/unmatched-mpesa - List mpesa transactions not matched to payments
     */
    public function getUnmatchedMpesa($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $allowed = $this->userHasAny(
            ['finance.view', 'finance.reconcile'],
            [10],
            ['accountant', 'finance', 'admin', 'director']
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        return $this->handleResponse($this->api->getUnmatchedMpesa($data));
    }

    /**
     * POST /api/payments/import-mpesa - Import MPESA transactions (stub)
     * Accepts: { transactions: [ { mpesa_code, amount, msisdn, transaction_date, note } ] }
     */
    public function postImportMpesa($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }
        if (
            !$this->userHasAny(
                ['finance.import', 'finance_import'],
                [10],
                ['accountant', 'finance', 'admin']
            )
        ) {
            return $this->forbidden('Insufficient permissions');
        }

        $txns = $data['transactions'] ?? [];
        if (!is_array($txns) || count($txns) === 0) {
            return $this->badRequest('No transactions provided for import');
        }

        return $this->handleResponse($this->api->importMpesa($txns));
    }

    /**
     * POST /api/payments/reconcile-mpesa
     * Reconcile an MPESA transaction by allocating to student fees (if student_id provided)
     * or creating a school_transaction record (for tracking)
     */
    public function postReconcileMpesa($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }
        $perms = $user['effective_permissions'] ?? [];
        $roles = $user['roles'] ?? [];
        $role = $user['role'] ?? '';
        $allowed = false;

        if (in_array('finance.reconcile', $perms)) {
            $allowed = true;
        }

        foreach ($roles as $r) {
            $roleId = is_array($r) ? ($r['id'] ?? null) : (is_object($r) ? ($r->id ?? null) : $r);
            $roleName = is_array($r) ? ($r['name'] ?? '') : (is_object($r) ? ($r->name ?? '') : '');
            if ($roleId == 10 || strtolower($roleName) === 'accountant' || strtolower($roleName) === 'finance' || strtolower($roleName) === 'admin') {
                $allowed = true;
                break;
            }
        }

        if ($role === 'accountant' || $role === 'finance' || $role === 'admin') {
            $allowed = true;
        }

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        $mpesaId = $data['mpesa_id'] ?? $id ?? null;
        if (!$mpesaId) {
            return $this->badRequest('mpesa_id is required');
        }

        $receivedBy = $user['user_id'] ?? $user['id'] ?? null;

        return $this->handleResponse($this->api->reconcileMpesa($mpesaId, $data, $receivedBy));
    }

    /**
     * GET /api/payments/mpesa-reconcile-history?mpesa_id=ID
     */
    public function getMpesaReconcileHistory($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $mpesaId = $_GET['mpesa_id'] ?? $data['mpesa_id'] ?? $id ?? null;
        if (!$mpesaId) {
            return $this->badRequest('mpesa_id is required');
        }

        return $this->handleResponse($this->api->getMpesaReconcileHistory($mpesaId));
    }

    /**
     * GET /api/payments/lookup-by-phone?phone=07XXXXXXXX
     */
    public function getLookupByPhone($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $phone = $_GET['phone'] ?? $data['phone'] ?? $id ?? null;
        if (!$phone) {
            return $this->badRequest('phone is required');
        }

        return $this->handleResponse($this->api->lookupByPhone($phone));
    }

    /**
     * POST /api/payments/link-student
     */
    public function postLinkStudent($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        if (
            !$this->userHasAny(
                ['finance.reconcile', 'finance_reconcile', 'payments.reconcile', 'payments_reconcile'],
                [10],
                ['accountant', 'finance', 'admin']
            )
        ) {
            return $this->forbidden('Insufficient permissions');
        }

        $mpesaId = $data['mpesa_id'] ?? null;
        $studentId = $data['student_id'] ?? null;

        if (!$mpesaId || !$studentId) {
            return $this->badRequest('mpesa_id and student_id are required');
        }

        $userId = $user['user_id'] ?? $user['id'] ?? null;

        return $this->handleResponse($this->api->linkStudent($mpesaId, $studentId, $userId));
    }

    // =========================================================================
    // OUTBOUND M-PESA API TRIGGERS
    // Every Daraja API is now drivable from the app. All require auth + RBAC.
    // Async results (transaction status, balance, reversal, B2B, B2C) land on
    // the webhook sinks above and can be read back via GET mpesa-results.
    // =========================================================================

    private function authorizePaymentsAction()
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return 'unauthorized';
        }
        if (
            !$this->userHasAny(
                ['finance.manage', 'finance_manage', 'payments.manage', 'payments_manage', 'finance.create', 'finance_create'],
                [10, 4, 3, 2],
                ['accountant', 'school administrator', 'director', 'system administrator']
            )
        ) {
            return 'forbidden';
        }
        return 'allowed';
    }

    private function mpesaActionResult($guard, $result)
    {
        if ($guard === 'unauthorized') {
            return $this->unauthorized('Authentication required');
        }
        if ($guard === 'forbidden') {
            return $this->forbidden('Insufficient permissions');
        }
        return $this->handleResponse($result);
    }

    public function postMpesaStkPush($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerStkPush($data));
    }

    /** POST /api/payments/kcb-mpesa-express */
    public function postKcbMpesaExpress($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerKcbMpesaExpress($data));
    }

    public function postMpesaStkQuery($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerStkQuery($data));
    }

    public function postMpesaC2bRegister($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerC2BRegister($data));
    }

    public function postMpesaC2bSimulate($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerC2BSimulate($data));
    }

    /** POST /api/payments/mpesa-pull-transactions */
    public function postMpesaPullTransactions($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerPullTransactions($data));
    }

    public function postMpesaTransactionStatus($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerTransactionStatus($data));
    }

    public function postMpesaAccountBalance($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerAccountBalance());
    }

    public function postMpesaReversal($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerReversal($data));
    }

    public function postMpesaQr($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerQR($data));
    }

    public function postMpesaB2b($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerB2B($data));
    }

    public function postMpesaB2c($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard !== 'allowed') {
            return $this->mpesaActionResult($guard, null);
        }
        return $this->mpesaActionResult($guard, $this->api->triggerB2C($data));
    }

    public function getMpesaResults($id = null, $data = [], $segments = [])
    {
        $guard = $this->authorizePaymentsAction();
        if ($guard === 'unauthorized') {
            return $this->unauthorized('Authentication required');
        }
        if ($guard === 'forbidden') {
            return $this->forbidden('Insufficient permissions');
        }
        $filters = $_GET;
        if (is_array($data) && $data) {
            $filters = array_merge($filters, $data);
        }
        return $this->handleResponse($this->api->getMpesaResults($filters));
    }
}
