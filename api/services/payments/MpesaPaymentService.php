<?php

namespace App\API\Services\payments;

use App\Database\Database;
use App\API\Services\ReadReplicaService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * MpesaPaymentService
 *
 * Rebuilt from scratch against the Safaricom Daraja 3.0 official
 * specification (https://developer.safaricom.co.ke/apis) and the live
 * KingsWayAcademy schema (mpesa_transactions / payment_webhooks_log).
 *
 * Covers the incoming-money surface:
 *  - STK Push (Lipa Na M-Pesa)          POST /mpesa/stkpush/v1/processrequest
 *  - STK Query                          POST /mpesa/stkpushquery/v1/query
 *  - STK callback processing            (Body.stkCallback)
 *  - C2B register URLs                  POST /mpesa/c2b/v1/registerurl
 *  - C2B simulate (sandbox only)        POST /mpesa/c2b/v1/simulate
 *  - C2B validation / confirmation      (callback processing)
 *  - Pull Transactions                  POST /pulltransactions/v1/query
 *  - Transaction Status                 POST /mpesa/transactionstatus/v1/query
 *  - Account Balance                    POST /mpesa/accountbalance/v1/query
 *  - Reversal                           POST /mpesa/reversal/v1/request
 *  - Dynamic QR                         POST /mpesa/qrcode/v1/generate
 *  - B2B (remittax + paymentrequest)    POST /mpesa/b2b/v1/remittax | paymentrequest
 *
 * All HTTP goes through MpesaApiClient (shared token cache). Database access
 * is lazy so pure-API callers (e.g. the CLI sandbox harness) never require a
 * live PDO connection just to talk to M-Pesa.
 */
class MpesaPaymentService
{
    /** @var PDO|null Lazily-initialised live schema connection. */
    private $db;

    /** @var MpesaApiClient */
    private $client;

    public function __construct()
    {
        $this->client = new MpesaApiClient();
    }

    private function getDb(): PDO
    {
        if ($this->db === null) {
            $this->db = Database::getInstance()->getConnection();
        }
        return $this->db;
    }

    /**
     * Build a response compatible with both formatResponse consumers
     * (['status'] string) and legacy callers checking ['success'] (bool).
     */
    private function respond(bool $success, $data = null, string $message = '', int $code = 0): array
    {
        $code = $code ?: ($success ? 200 : 400);
        return array_merge(
            formatResponse($success, $data, $message),
            ['success' => $success, 'code' => $code]
        );
    }

    /**
     * The shared client returns non-JSON provider/WAF bodies as `raw` and
     * some Daraja APIs return business failures in an HTTP 200 response.
     * Never expose either shape as a successful operation.
     */
    private function providerResponseFailed(array $response): bool
    {
        if (isset($response['raw']) || isset($response['fault'])) {
            return true;
        }
        if (isset($response['errorCode']) && (string) $response['errorCode'] !== '0') {
            return true;
        }
        return false;
    }

    private function callbackUrl(string $endpoint): string
    {
        $base = defined('MPESA_CALLBACK_BASE_URL') && MPESA_CALLBACK_BASE_URL !== ''
            ? MPESA_CALLBACK_BASE_URL
            : (defined('BASE_URL') ? BASE_URL : '');
        return $base !== '' ? $base . $endpoint : $endpoint;
    }

    /**
     * Normalise a phone number to 254XXXXXXXXX.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if (strlen($phone) === 9) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) === 12 && strpos($phone, '254') === 0) {
            // already correct
        } elseif (strlen($phone) === 13 && strpos($phone, '+254') === 0) {
            $phone = substr($phone, 1);
        }
        return $phone;
    }

    // =========================================================================
    // STK PUSH
    // =========================================================================

    /**
     * Initiate an STK Push (CustomerPayBillOnline) for a student fee payment.
     *
     * @param string $admissionNumber AccountReference / bill reference
     * @param string $phoneNumber     254XXXXXXXXX (or 07XXXXXXXXX)
     * @param float  $amount          KES amount
     * @param string $description     TransactionDesc
     * @return array
     */
    public function initiateSTKPush($admissionNumber, $phoneNumber, $amount, $description = 'School Fees Payment', int $financialAccountId = 0, int $collectionRouteId = 0)
    {
        try {
            $rawReference = trim((string) $admissionNumber);
            $canonicalReference = (new ReferenceNormalizer())->reference($rawReference);
            $phone = $this->normalizePhone((string) $phoneNumber);
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                return $this->respond(false, null, 'Invalid phone number format. Use 254XXXXXXXXX');
            }
            $amount = (float) $amount;
            if ($amount <= 0) {
                return $this->respond(false, null, 'Amount must be greater than zero');
            }

            // Safaricom/Buni must never receive an STK request for an unknown
            // account. The account may be either an existing learner's
            // admission number or an applicant's application number.
            $account = $this->resolvePaymentAccount($rawReference);
            if (!$account) {
                return $this->respond(false, null, 'The application or admission account reference was not found');
            }

            $studentId = (int) ($account['student_id'] ?? 0) ?: null;

            $purpose = preg_match('/^(TRN|T)-/i', $canonicalReference) ? 'transport' : (preg_match('/^(U|UC|UNIFORM)-/i', $canonicalReference) ? 'uniforms' : 'fees');
            $settlementAccountId = 0;
            $shortcode = '';

            // STK is a collection-point operation. Resolve the shortcode from
            // the selected route, then post the money to that route's real
            // settlement account. A bank account must never be treated as a
            // Daraja shortcode.
            if ($collectionRouteId > 0) {
                $routeStmt = $this->getDb()->prepare(
                    "SELECT r.account_identifier, r.settlement_financial_account_id,
                            r.financial_account_id, p.code AS provider_code
                     FROM payment_collection_routes r
                     JOIN payment_providers p ON p.id = r.provider_id
                     JOIN payment_collection_route_channels rc ON rc.route_id = r.id
                     JOIN financial_channels ch ON ch.id = rc.channel_id
                     WHERE r.id = :route_id AND r.purpose = :purpose
                       AND r.active = 1 AND p.code = 'mpesa_daraja'
                       AND r.collection_product = 'paybill' AND ch.code = 'mpesa_stk'
                     LIMIT 1"
                );
                $routeStmt->execute(['route_id' => $collectionRouteId, 'purpose' => $purpose]);
                $route = $routeStmt->fetch(PDO::FETCH_ASSOC);
                if (!$route) {
                    return $this->respond(false, null, 'The selected M-Pesa STK collection point is not active or is not configured for this purpose.');
                }
                $shortcode = (string) $route['account_identifier'];
                $settlementAccountId = (int) ($route['settlement_financial_account_id'] ?: $route['financial_account_id']);
            } else {
                $schoolAccount = (new FinancialAccountService($this->getDb()))->requireFor($financialAccountId, $purpose, 'mpesa_stk');
                if (($schoolAccount['provider_code'] ?? '') !== 'mpesa_daraja') {
                    return $this->respond(false, null, 'Select an M-Pesa STK collection point, not a bank account.');
                }
                $shortcode = (string) $schoolAccount['account_identifier'];
                $settlementAccountId = (int)($schoolAccount['settlement_financial_account_id'] ?? 0) ?: (int)$schoolAccount['id'];
            }

            $timestamp = $this->client->timestamp();

            $requestData = [
                'BusinessShortCode' => $this->client->getShortcode($shortcode),
                'Password'          => $this->client->lipaNaMpesaPassword($timestamp, $shortcode),
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => (int) $amount,
                'PartyA'            => $phone,
                'PartyB'            => $this->client->getShortcode($shortcode),
                'PhoneNumber'       => $phone,
                'CallBackURL'       => $this->callbackUrl('/api/payments/mpesa-stk-callback'),
                'AccountReference'  => $canonicalReference,
                'TransactionDesc'   => $description,
            ];

            $response = $this->client->post('/mpesa/stkpush/v1/processrequest', $requestData);

            $checkoutId  = $response['CheckoutRequestID'] ?? null;
            $merchantId  = $response['MerchantRequestID'] ?? null;
            $responseCode = $response['ResponseCode'] ?? '1';

            $this->logStkRequest($studentId, $canonicalReference, $phone, $amount, $requestData, $response, $settlementAccountId);

            if ($responseCode === '0') {
                return $this->respond(true, [
                    'checkout_request_id' => $checkoutId,
                    'merchant_request_id' => $merchantId,
                    'message' => 'STK Push sent successfully. Please enter M-Pesa PIN on your phone.',
                ], 'STK Push initiated');
            }

            $message = $response['ResponseDescription'] ?? 'Failed to initiate M-Pesa payment';
            return $this->respond(false, $response, $message, 400);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] STK Push error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Record a pending STK Push against the live mpesa_transactions table.
     * No M-Pesa receipt exists yet, so a placeholder code is used and later
     * replaced by the real MpesaReceiptNumber when the callback/query lands.
     */
    private function logStkRequest($studentId, string $admissionNumber, string $phone, float $amount, array $request, array $response, int $financialAccountId): void
    {
        try {
            $checkoutId = $response['CheckoutRequestID'] ?? null;
            $code = 'STK-' . ($checkoutId ?: bin2hex(random_bytes(8)));
            $stmt = $this->getDb()->prepare(
                "INSERT INTO mpesa_transactions
                    (mpesa_code, student_id, amount, transaction_date, phone_number,
                    bill_ref_number, financial_account_id, collection_account_identifier, status, transaction_type, checkout_request_id,
                     raw_callback, webhook_data, created_at)
                 VALUES (:code, :sid, :amount, NOW(), :phone, :bill_ref,
                         :financial_account_id, :collection_account_identifier, 'pending', 'STK_PUSH', :checkout, :raw, :webhook, NOW())
                 ON DUPLICATE KEY UPDATE checkout_request_id = VALUES(checkout_request_id)"
            );
            $stmt->execute([
                'code'     => $code,
                'sid'      => $studentId,
                'amount'   => $amount,
                'phone'    => $phone,
                'bill_ref' => $admissionNumber,
                'financial_account_id' => $financialAccountId,
                'collection_account_identifier' => $request['BusinessShortCode'] ?? null,
                'checkout' => $checkoutId,
                'raw'      => json_encode($request),
                'webhook'  => json_encode($response),
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] Failed to log STK request: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // STK QUERY
    // =========================================================================

    /**
     * Poll the STK push status for a CheckoutRequestID
     * (POST /mpesa/stkpushquery/v1/query).
     *
     * This is what the parent portal uses to confirm a payment after the
     * customer enters their PIN.
     */
    public function queryTransactionStatus($checkoutRequestId, $phone = null)
    {
        try {
            if (!$checkoutRequestId) {
                return $this->respond(false, null, 'CheckoutRequestID is required');
            }
            $timestamp = $this->client->timestamp();
            $payload = [
                'BusinessShortCode' => $this->client->getShortcode(),
                'Password'          => $this->client->lipaNaMpesaPassword($timestamp),
                'Timestamp'         => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ];

            $response = $this->client->post('/mpesa/stkpushquery/v1/query', $payload);

            if (($response['ResultCode'] ?? '1') === '0') {
                $this->recordStkSuccess($checkoutRequestId, $response);
            }

            return $this->respond(
                !$this->providerResponseFailed($response),
                $response,
                $this->providerResponseFailed($response) ? 'STK status query failed' : 'STK status retrieved',
                $this->providerResponseFailed($response) ? 502 : 200
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] STK query error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Promote a pending STK_PUSH mpesa_transaction to processed once Safaricom
     * confirms ResultCode 0 (via callback or query).
     */
    private function recordStkSuccess(string $checkoutRequestId, array $callback): void
    {
        try {
            $stmt = $this->getDb()->prepare(
                "SELECT id, status, bill_ref_number, amount, collection_account_identifier
                 FROM mpesa_transactions WHERE checkout_request_id = :checkout LIMIT 1"
            );
            $stmt->execute(['checkout' => $checkoutRequestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $wasAlreadyProcessed = ($row['status'] ?? '') === 'processed';

            $meta = $callback['CallbackMetadata']['Item'] ?? [];
            $kv = [];
            foreach ($meta as $item) {
                if (isset($item['Name'])) {
                    $kv[$item['Name']] = $item['Value'] ?? null;
                }
            }
            $mpesaReceipt = $kv['MpesaReceiptNumber'] ?? null;
            $phone = $kv['PhoneNumber'] ?? null;
            $amount = $kv['Amount'] ?? null;
            $normalizedBillRef = (new ReferenceNormalizer())->reference((string) ($row['bill_ref_number'] ?? ''));

            $update = $this->getDb()->prepare(
                "UPDATE mpesa_transactions
                 SET status = 'processed',
                     mpesa_code = COALESCE(:receipt, mpesa_code),
                     phone_number = COALESCE(:phone, phone_number),
                     amount = COALESCE(:amount, amount),
                     normalized_reference = NULLIF(:normalized_reference, ''),
                     raw_callback = :raw,
                     webhook_data = :webhook
                 WHERE id = :id"
            );
            $update->execute([
                'receipt' => $mpesaReceipt,
                'phone'   => $phone,
                'amount'  => $amount,
                'normalized_reference' => $normalizedBillRef,
                'raw'     => json_encode($callback),
                'webhook' => json_encode($callback),
                'id'      => $row['id'],
            ]);

            // STK pushes made before a student exists are keyed by the
            // application reference. Preserve them in the admission ledger;
            // placement later posts this ledger to the student's obligations.
            $billRef = trim((string) ($row['bill_ref_number'] ?? ''));
            if ($billRef !== '') {
                $applicationStmt = $this->getDb()->prepare(
                    "SELECT aa.id, aa.parent_id,
                            CASE WHEN EXISTS (
                                SELECT 1 FROM student_academic_enrollments sae
                                WHERE sae.student_id = aa.enrolled_student_id
                                  AND sae.enrollment_status = 'active'
                            ) THEN aa.enrolled_student_id ELSE NULL END AS enrolled_student_id
                     FROM admission_applications aa
                     LEFT JOIN students sx ON sx.id = aa.enrolled_student_id
                     WHERE aa.application_no = :application_reference OR sx.admission_no = :admission_reference
                     LIMIT 1"
                );
                $applicationStmt->execute([
                    'application_reference' => $billRef,
                    'admission_reference' => $billRef,
                ]);
                $application = $applicationStmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $applicationId = (int) ($application['id'] ?? 0);
                $admissionPaymentId = 0;
                if ($applicationId > 0 && !$wasAlreadyProcessed && $mpesaReceipt) {
                    $insert = $this->getDb()->prepare(
                        "INSERT INTO admission_payments
                            (application_id, amount, payment_method, reference_no, receipt_no,
                             payment_date, notes, status, recorded_by, created_at)
                         VALUES (:application_id, :amount, 'mpesa', :reference_no, :receipt_no,
                                 NOW(), :notes, 'recorded', 1, NOW())"
                    );
                    $insert->execute([
                        'application_id' => $applicationId,
                        'amount' => (float) ($amount ?: $row['amount'] ?: 0),
                        'reference_no' => $mpesaReceipt,
                        'receipt_no' => 'MPESA-' . $mpesaReceipt,
                        'notes' => 'STK payment received using application reference ' . $billRef,
                    ]);
                    $admissionPaymentId = (int) $this->getDb()->lastInsertId();
                }

                if ($applicationId > 0 && !$wasAlreadyProcessed) {
                    $studentId = (int) ($application['enrolled_student_id'] ?? 0);
                    if ($studentId > 0) {
                        $paymentService = new \App\API\Modules\admission\AdmissionPaymentService($this->getDb());
                        $paymentService->postApplicationPaymentsToStudent(
                            $applicationId,
                            $studentId,
                            !empty($application['parent_id']) ? (int) $application['parent_id'] : null,
                            1,
                            $billRef
                        );
                    }
                }

                if ($applicationId > 0) {
                    try {
                        (new \App\API\Modules\admission\StudentAdmissionWorkflow())->advanceAfterConfirmedPayment($applicationId);
                    } catch (\Throwable $workflowError) {
                        \App\API\Services\Logger::legacyError('[MpesaPaymentService] payment workflow advancement deferred: ' . $workflowError->getMessage());
                    }
                }
            }

            if (!$wasAlreadyProcessed) {
                $this->sendPaymentConfirmationSms((int) $row['id']);
            }

            // Fees STK callbacks enter the same normalized fee ledger as C2B
            // and bank collections. Transport and uniforms reconcile below.
            if (!$wasAlreadyProcessed) {
                $stkBillRef = trim((string) ($row['bill_ref_number'] ?? ''));
                if ($stkBillRef !== '' && !preg_match('/^(T|TRN|U|UC|UNIFORM)-/i', $stkBillRef)) {
                    try {
                        (new PaymentRoutingService($this->getDb()))->routeIncoming(
                            'mpesa_daraja',
                            [
                                'BusinessShortCode' => $row['collection_account_identifier'] ?? null,
                                'BillRefNumber' => $stkBillRef,
                                'channel' => 'mpesa_stk',
                            ],
                            (string) ($mpesaReceipt ?: $checkoutRequestId),
                            (float) ($amount ?: $row['amount'] ?: 0),
                            (string) ($row['collection_account_identifier'] ?? ''),
                            $stkBillRef
                        );
                    } catch (\Throwable $feeRoutingError) {
                        \App\API\Services\Logger::legacyError('[MpesaPaymentService] STK fee routing failed: ' . $feeRoutingError->getMessage());
                    }
                }
            }
            // Transport has its own entitlement ledger. A successful STK
            // callback is the only point at which that ledger may be credited.
            if (!$wasAlreadyProcessed && !empty($checkoutRequestId)) {
                try {
                    $billRef = (string)($row['bill_ref_number'] ?? '');
                    if (preg_match('/^(U|UC|UNIFORM)-/i', $billRef)) {
                        (new UniformPaymentService($this->getDb()))->reconcileReference($billRef, (float)($amount ?: 0), 'mpesa_daraja', $mpesaReceipt ?: $checkoutRequestId);
                    } else {
                        (new TransportPaymentService($this->getDb()))->reconcileDaraja($checkoutRequestId, $mpesaReceipt ?: $checkoutRequestId, (float)($amount ?: 0));
                    }
                } catch (\Throwable $transportError) {
                    \App\API\Services\Logger::legacyError('[MpesaPaymentService] transport reconciliation failed: ' . $transportError->getMessage());
                }
            }
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] Failed to record STK success: ' . $e->getMessage());
        }
    }

    /**
     * Send an SMS to the paying phone confirming the amount received, the
     * account reference, the current term balance, and the annual balance.
     * Best-effort: any failure is logged, never thrown.
     */
    public function sendPaymentConfirmationSms(int $transactionId): void
    {
        try {
            $stmt = $this->getDb()->prepare(
                "SELECT mt.id, mt.phone_number, mt.amount, mt.bill_ref_number, mt.student_id,
                        CONCAT(p.first_name, ' ', COALESCE(NULLIF(p.middle_name, ''), ''), ' ', p.last_name) AS student_name
                 FROM mpesa_transactions mt
                 LEFT JOIN students s ON s.id = mt.student_id
                 LEFT JOIN persons p ON p.id = s.person_id
                 WHERE mt.id = :id LIMIT 1"
            );
            $stmt->execute(['id' => $transactionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $phone = $this->normalizePhone((string) ($row['phone_number'] ?? ''));
            if (!preg_match('/^254[0-9]{9}$/', $phone)) {
                \App\API\Services\Logger::legacyError('[MpesaPaymentService] payment SMS skipped: no valid payer phone on tx ' . $transactionId);
                return;
            }

            $amount = (float) $row['amount'];
            $ref    = trim((string) ($row['bill_ref_number'] ?? ''));
            $name   = trim((string) ($row['student_name'] ?? ''));
            [$termBalance, $annualBalance] = $this->getStudentFeeBalances($row['student_id'] ? (int) $row['student_id'] : null);

            $money = function ($v) {
                return 'Ksh ' . number_format((float) ($v ?? 0), 2);
            };

            $msg = 'Thank you for paying ' . $money($amount) . ' for account ' . $ref . '.';
            if ($name !== '') {
                $msg .= ' Student: ' . $name . '.';
            }
            if ($termBalance !== null) {
                $msg .= ' Current term balance: ' . $money($termBalance) . '.';
            }
            if ($annualBalance !== null) {
                $msg .= ' Annual balance: ' . $money($annualBalance) . '.';
            }
            $portalUrl = rtrim((string) (defined('BASE_URL') ? BASE_URL : ''), '/') . '/parents/';
            $msg .= ' Receipt: ' . ($ref !== '' ? 'MPESA-' . $ref : 'available in portal') . '.';
            if ($portalUrl !== '/parents/') {
                $msg .= ' View statement: ' . $portalUrl;
            }
            $msg .= ' - Kingsway Preparatory School';

            // Payment notifications use the durable communications outbox.
            // The worker performs provider delivery and records retries/status;
            // a webhook must never block on an SMS provider call.
            $communication = (new \App\API\Modules\communications\CommunicationsManager($this->getDb()))
                ->createCommunication([
                    'sender_id' => 1,
                    'subject' => 'Fee payment received',
                    'body' => mb_substr($msg, 0, 160),
                    'type' => 'sms',
                    'status' => 'sent',
                    'priority' => 'high',
                    'recipients' => [$phone],
                ]);
            $sent = !empty($communication['id']);

            $this->logWebhook(
                'payment_sms',
                [
                    'to'             => $phone,
                    'transaction_id' => $transactionId,
                    'amount'         => $amount,
                    'bill_ref'       => $ref,
                    'message'        => $msg,
                ],
                $sent ? 'queued' : 'failed'
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] payment SMS failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve the current term balance and the current academic year balance
     * for a student from the vw_student_fee_balances view.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function getStudentFeeBalances(?int $studentId): array
    {
        if (!$studentId) {
            return [null, null];
        }
        try {
            $db = $this->getDb();
            $current = $db->query(
                "SELECT ayt.id AS ayt_id, ayt.academic_year_id AS ay_id,
                        ay.year_code AS ay_code
                 FROM academic_year_terms ayt
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE ay.is_current = 1 AND ayt.status = 'current'
                 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                return [null, null];
            }

            $term = $db->prepare(
                "SELECT balance FROM " . ReadReplicaService::qualifiedRef('student_fee_balances') . "
                 WHERE student_id = :sid AND academic_year_term_id = :ayt LIMIT 1"
            );
            $term->execute(['sid' => $studentId, 'ayt' => $current['ayt_id']]);
            $termBalance = $term->fetchColumn();

            $annual = $db->prepare(
                "SELECT SUM(balance) FROM " . ReadReplicaService::qualifiedRef('student_fee_balances') . "
                 WHERE student_id = :sid AND academic_year = :ay_code"
            );
            $annual->execute(['sid' => $studentId, 'ay_code' => $current['ay_code']]);
            $annualBalance = $annual->fetchColumn();

            return [
                $termBalance !== false ? (float) $termBalance : null,
                $annualBalance !== false ? (float) $annualBalance : null,
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] fee balance lookup failed: ' . $e->getMessage());
            return [null, null];
        }
    }

    // =========================================================================
    // STK / C2B CALLBACK PROCESSING
    // =========================================================================

    /**
     * Process an STK Push callback (Body.stkCallback). Used by the
     * /api/payments/mpesa-stk-callback webhook endpoint.
     */
    public function processCallback($callbackData)
    {
        try {
            $body = $callbackData['Body'] ?? $callbackData;
            $stk = $body['stkCallback'] ?? null;
            if (!$stk) {
                return $this->respond(false, null, 'Invalid STK callback payload');
            }

            $checkoutId = $stk['CheckoutRequestID'] ?? null;
            $resultCode = $stk['ResultCode'] ?? '1';
            $resultDesc = $stk['ResultDesc'] ?? '';

            if ((string) $resultCode === '0') {
                $this->recordStkSuccess($checkoutId, $stk);
                $this->logWebhook('mpesa_stk', $callbackData, 'processed');
            } else {
                try {
                    $this->getDb()->prepare(
                        "UPDATE mpesa_transactions
                         SET status = 'failed', webhook_data = :webhook
                         WHERE checkout_request_id = :checkout"
                    )->execute([
                        'webhook' => json_encode($callbackData),
                        'checkout' => $checkoutId,
                    ]);
                } catch (Exception $e) {
                    \App\API\Services\Logger::legacyError('[MpesaPaymentService] failed-status update: ' . $e->getMessage());
                }
                $this->logWebhook('mpesa_stk', $callbackData, 'failed');
            }

            return $this->respond(true, [
                'checkout_request_id' => $checkoutId,
                'result_code' => $resultCode,
                'result_desc' => $resultDesc,
            ], 'STK callback processed');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] processCallback error: ' . $e->getMessage());
            return $this->respond(false, null, 'An internal error occurred.', 500);
        }
    }

    /**
     * C2B Validation URL handler — returns the expected validation result.
     */
    public function validateC2BPayment($callbackData)
    {
        try {
            $this->logWebhook('mpesa_c2b_validation', $callbackData, 'validated');
            $transId = $callbackData['TransID'] ?? $callbackData['TransactionID'] ?? null;
            $accountReference = trim((string) ($callbackData['BillRefNumber'] ?? $callbackData['AccountReference'] ?? ''));
            $amount = (float) ($callbackData['TransAmount'] ?? $callbackData['Amount'] ?? 0);

            if (empty($transId) && empty($callbackData)) {
                return ['ResultCode' => 'C2B00011', 'ResultDesc' => 'Invalid validation request'];
            }

            if ($accountReference === '' || $amount <= 0 || !$this->resolvePaymentAccount($accountReference)) {
                return ['ResultCode' => 'C2B00016', 'ResultDesc' => 'Invalid account reference'];
            }

            return ['ResultCode' => '0', 'ResultDesc' => 'Success'];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] validateC2BPayment error: ' . $e->getMessage());
            return ['ResultCode' => 'C2B00011', 'ResultDesc' => 'System error'];
        }
    }

    /** Resolve both official learner accounts and pre-placement applicant accounts. */
    private function resolvePaymentAccount(string $reference): ?array
    {
        $rawReference = trim($reference);
        $reference = (new ReferenceNormalizer())->reference($rawReference);
        if ($reference === '') return null;

        $db = $this->getDb();
        $student = $db->prepare(
            "SELECT s.id AS student_id, sp.parent_id, s.admission_no
             FROM students s
             LEFT JOIN student_parents sp ON sp.student_id = s.id
            WHERE s.admission_no IN (:raw_reference, :reference)
             LIMIT 1"
        );
        $student->execute(['raw_reference' => $rawReference, 'reference' => $reference]);
        $studentRow = $student->fetch(PDO::FETCH_ASSOC);
        if ($studentRow) {
            return ['type' => 'student'] + $studentRow;
        }

        // Transport and uniform references are valid payment accounts even
        // though they are not admission numbers. Resolve them from the
        // central routing table so Daraja STK/C2B and callback reconciliation
        // use the same verified reference.
        $routing = $db->prepare(
            "SELECT r.purpose, r.student_id, r.uniform_sale_id
             FROM payment_routing_references r
             WHERE (r.reference = :reference OR r.normalized_reference = :normalized)
               AND r.status = 'active'
               AND (r.expires_at IS NULL OR r.expires_at >= NOW())
             LIMIT 1"
        );
        $normalizer = new ReferenceNormalizer();
        $routing->execute([
            'reference' => $reference,
            'normalized' => $normalizer->reference($reference),
        ]);
        $routingRow = $routing->fetch(PDO::FETCH_ASSOC);
        if ($routingRow) {
            return [
                'type' => (string) $routingRow['purpose'],
                'student_id' => (int) $routingRow['student_id'],
                'uniform_sale_id' => !empty($routingRow['uniform_sale_id']) ? (int) $routingRow['uniform_sale_id'] : null,
            ];
        }

        $application = $db->prepare(
            "SELECT id AS application_id, parent_id, application_no, status, enrolled_student_id
             FROM admission_applications
            WHERE application_no IN (:raw_reference, :reference)
               AND status NOT IN ('cancelled', 'rejected')
             LIMIT 1"
        );
        $application->execute(['raw_reference' => $rawReference, 'reference' => $reference]);
        $applicationRow = $application->fetch(PDO::FETCH_ASSOC);
        return $applicationRow ? ['type' => 'application'] + $applicationRow : null;
    }

    /**
     * C2B Confirmation URL handler — records a fully-credited incoming C2B
     * payment against the live schema.
     */
    public function processC2BConfirmation($callbackData)
    {
        try {
            $transId = $callbackData['TransID'] ?? '';
            $admissionNo = $callbackData['BillRefNumber'] ?? '';
            $amount = (float) ($callbackData['TransAmount'] ?? 0);

            if ($transId === '' || $amount <= 0) {
                $this->logWebhook('mpesa_c2b_confirmation', $callbackData, 'failed');
                return $this->respond(false, null, 'Missing required fields', 400);
            }

            $this->recordC2BConfirmation($callbackData, $transId, $admissionNo, $amount);
            $this->logWebhook('mpesa_c2b_confirmation', $callbackData, 'processed');

            return $this->respond(true, [
                'mpesa_code' => $transId,
                'amount' => $amount,
            ], 'C2B confirmation processed');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] processC2BConfirmation error: ' . $e->getMessage());
            return $this->respond(false, null, 'An internal error occurred.', 500);
        }
    }

    /**
     * Record a C2B confirmation into mpesa_transactions (idempotent on
     * mpesa_code). Actual fee allocation lives in PaymentsAPI which calls
     * sp_process_student_payment — the row must exist first.
     */
    public function recordC2BConfirmation(array $callbackData, string $transId, string $admissionNo, float $amount): int
    {
        $db = $this->getDb();
        $studentId = null;
        try {
            $stmt = $db->prepare("SELECT id FROM students WHERE admission_no = :adm LIMIT 1");
            $stmt->execute(['adm' => $admissionNo]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            $studentId = $student ? (int) $student['id'] : null;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] C2B student lookup: ' . $e->getMessage());
        }

        $date = $this->client->formatTransactionDate((string) ($callbackData['TransTime'] ?? ''));

        $normalizedAdmissionNo = (new ReferenceNormalizer())->reference($admissionNo);
        $stmt = $db->prepare(
            "INSERT INTO mpesa_transactions
                (mpesa_code, student_id, amount, transaction_date, phone_number,
                 first_name, middle_name, last_name, org_account_balance,
                 third_party_trans_id, bill_ref_number, normalized_reference, status, transaction_type,
                 raw_callback, webhook_data, created_at)
             VALUES (:code, :sid, :amount, :tdate, :phone, :fname, :mname, :lname,
                     :orgbal, :thirdparty, :billref, :normalized_reference, 'processed', 'C2B',
                     :raw, :webhook, NOW())
             ON DUPLICATE KEY UPDATE status = 'processed',
                 webhook_data = VALUES(webhook_data)"
        );
        $stmt->execute([
            'code'       => $transId,
            'sid'        => $studentId,
            'amount'     => $amount,
            'tdate'      => $date,
            'phone'      => $callbackData['MSISDN'] ?? null,
            'fname'      => $callbackData['FirstName'] ?? null,
            'mname'      => $callbackData['MiddleName'] ?? null,
            'lname'      => $callbackData['LastName'] ?? null,
            'orgbal'     => isset($callbackData['OrgAccountBalance']) ? (float) $callbackData['OrgAccountBalance'] : null,
            'thirdparty' => $callbackData['ThirdPartyTransID'] ?? null,
            'billref'    => $admissionNo,
            'normalized_reference' => $normalizedAdmissionNo,
            'raw'        => json_encode($callbackData),
            'webhook'    => json_encode($callbackData),
        ]);

        $newId = (int) $db->lastInsertId();

        // rowCount() is 1 on fresh insert, 2 on duplicate-key update; only
        // notify the payer the first time the money is credited.
        if ($stmt->rowCount() === 1) {
            $this->sendPaymentConfirmationSms($newId);
        }

        return $newId;
    }

    // =========================================================================
    // C2B REGISTER + SIMULATE
    // =========================================================================

    /**
     * Register C2B validation/confirmation URLs.
     */
    public function registerC2BUrls($validationURL, $confirmationURL, $responseType = 'Completed', $shortcode = null)
    {
        try {
            $payload = [
                'ShortCode'         => $this->client->getShortcode($shortcode),
                'ResponseType'      => $responseType,
                'ConfirmationURL'   => $confirmationURL,
                'ValidationURL'     => $validationURL,
            ];

            // Daraja C2B URL registration is v2. The simulator remains v1;
            // keeping those versions separate is required by the API.
            $response = $this->client->post('/mpesa/c2b/v2/registerurl', $payload);
            $this->logWebhook('mpesa_c2b_confirmation', [
                'operation' => 'register_urls',
                'request' => $payload,
                'response' => $response,
            ], 'received');

            if (($response['ResponseDescription'] ?? '') === 'Success'
                || (string) ($response['ResponseCode'] ?? '') === '0') {
                return $this->respond(true, $response, 'C2B URLs registered');
            }
            return $this->respond(false, $response, $response['ResponseDescription'] ?? 'Registration failed');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] registerC2BUrls error: ' . $e->getMessage());
            return $this->respond(false, null, 'An internal error occurred.', 500);
        }
    }

    /**
     * C2B simulate (sandbox only) — fires a fake C2B transaction so the
     * confirmation callback fires with a realistic payload.
     */
    public function simulateC2B($amount, $msisdn, $billRefNumber, $commandId = 'CustomerPayBillOnline', $shortcode = null)
    {
        try {
            $payload = [
                'ShortCode'     => $this->client->getShortcode($shortcode),
                'CommandID'     => $commandId,
                'Amount'        => (int) $amount,
                'Msisdn'        => $msisdn,
                'BillRefNumber' => $billRefNumber,
            ];
            $response = $this->client->post('/mpesa/c2b/v1/simulate', $payload);

            if (($response['ResponseCode'] ?? '1') === '0') {
                try {
                    $code = 'C2B-' . bin2hex(random_bytes(6));
                    $this->getDb()->prepare(
                        "INSERT INTO mpesa_transactions
                            (mpesa_code, amount, transaction_date, phone_number,
                             bill_ref_number, status, transaction_type, webhook_data, created_at)
                         VALUES (:code, :amount, NOW(), :phone, :billref, 'pending', 'C2B', :webhook, NOW())
                         ON DUPLICATE KEY UPDATE webhook_data = VALUES(webhook_data)"
                    )->execute([
                        'code' => $code,
                        'amount' => (float) $amount,
                        'phone' => $msisdn,
                        'billref' => $billRefNumber,
                        'webhook' => json_encode($response),
                    ]);
                } catch (Exception $e) {
                    \App\API\Services\Logger::legacyError('[MpesaPaymentService] simulate log: ' . $e->getMessage());
                }
            }

            return $this->respond(($response['ResponseCode'] ?? '1') === '0', $response, $response['ResponseDescription'] ?? 'C2B simulate failed');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] simulateC2B error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Pull C2B transactions missed by a failed/unreachable confirmation
     * notification. Safaricom limits this query to a maximum 48-hour window.
     */
    public function pullTransactions(?string $startDate = null, ?string $endDate = null, int $offset = 0, ?string $shortcode = null): array
    {
        try {
            $end = $endDate ? new \DateTime($endDate, new \DateTimeZone('Africa/Nairobi')) : new \DateTime('now', new \DateTimeZone('Africa/Nairobi'));
            $start = $startDate ? new \DateTime($startDate, new \DateTimeZone('Africa/Nairobi')) : clone $end;
            if (!$startDate) {
                $start->modify('-24 hours');
            }
            if ($start >= $end || ($end->getTimestamp() - $start->getTimestamp()) > 172800) {
                return $this->respond(false, null, 'Pull transaction window must be positive and no more than 48 hours');
            }
            $offset = max(0, $offset);
            $payload = [
                'ShortCode' => $this->client->getShortcode($shortcode),
                'StartDate' => $start->format('Y-m-d H:i:s'),
                'EndDate' => $end->format('Y-m-d H:i:s'),
                'OffSetValue' => (string) $offset,
            ];
            $response = $this->client->post('/pulltransactions/v1/query', $payload);
            $failed = $this->providerResponseFailed($response)
                || (isset($response['ResponseCode']) && (string) $response['ResponseCode'] !== '0');
            return $this->respond($failed ? false : true, $response, $failed ? ($response['ResponseMessage'] ?? 'Pull transaction query failed') : 'Pulled C2B transactions', $failed ? 502 : 200);
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] pullTransactions error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    // =========================================================================
    // TRANSACTION STATUS, BALANCE, REVERSAL, QR, B2B
    // =========================================================================

    /**
     * Official Transaction Status API — query a completed transaction by its
     * M-Pesa receipt/transaction ID.
     */
    public function queryOfficialTransactionStatus($transactionId, string $remarks = 'status query', string $occasion = 'status')
    {
        try {
            if (!$transactionId) {
                return $this->respond(false, null, 'TransactionID is required');
            }
            $payload = [
                'Initiator'          => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'          => 'TransactionStatusQuery',
                'TransactionID'      => $transactionId,
                'PartyA'             => $this->client->getShortcode(),
                'IdentifierType'     => 4,
                'ResultURL'          => $this->callbackUrl('/api/payments/mpesa-result'),
                'QueueTimeOutURL'    => $this->callbackUrl('/api/payments/mpesa-result'),
                'Remarks'            => $remarks,
                'Occasion'           => $occasion,
            ];
            $response = $this->client->post('/mpesa/transactionstatus/v1/query', $payload);
            $failed = $this->providerResponseFailed($response);
            return $this->respond(!$failed, $response, $failed ? 'Transaction status query failed' : 'Transaction status queried', $failed ? 502 : 200);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] transaction status error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Account Balance API.
     */
    public function queryAccountBalance(string $remarks = 'balance query', string $occasion = 'balance')
    {
        try {
            $payload = [
                'Initiator'          => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'          => 'AccountBalance',
                'PartyA'             => $this->client->getShortcode(),
                'IdentifierType'     => 4,
                'Remarks'            => $remarks,
                'QueueTimeOutURL'    => $this->callbackUrl('/api/payments/mpesa-result'),
                'ResultURL'          => $this->callbackUrl('/api/payments/mpesa-result'),
            ];
            $response = $this->client->post('/mpesa/accountbalance/v1/query', $payload);
            $failed = $this->providerResponseFailed($response);
            return $this->respond(!$failed, $response, $failed ? 'Account balance query failed' : 'Account balance queried', $failed ? 502 : 200);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] account balance error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Transaction Reversal API.
     */
    public function requestReversal(string $transactionId, float $amount, string $receiverParty, string $remarks = 'reversal', string $occasion = 'reversal')
    {
        try {
            if (!$transactionId || $amount <= 0) {
                return $this->respond(false, null, 'TransactionID and positive amount are required');
            }
            $payload = [
                'Initiator'          => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'          => 'TransactionReversal',
                'TransactionID'      => $transactionId,
                'Amount'             => (int) $amount,
                'ReceiverParty'      => $receiverParty,
                'RecieverIdentifierType' => 11,
                'ResultURL'          => $this->callbackUrl('/api/payments/mpesa-result'),
                'QueueTimeOutURL'    => $this->callbackUrl('/api/payments/mpesa-result'),
                'Remarks'            => $remarks,
                'Occasion'           => $occasion,
            ];
            $response = $this->client->post('/mpesa/reversal/v1/request', $payload);
            $failed = $this->providerResponseFailed($response);
            return $this->respond(!$failed, $response, $failed ? 'Reversal request failed' : 'Reversal request submitted', $failed ? 502 : 200);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] reversal error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * Dynamic QR API. Defaults to a pay-bill QR (TrxCode "PB").
     */
    public function generateDynamicQR(string $merchantName, string $refNo, float $amount, string $cpi, string $trxCode = 'PB', string $merchantId = '', string $size = '200')
    {
        try {
            $payload = [
                'MerchantName' => $merchantName,
                'RefNo'        => $refNo,
                'Amount'       => (int) $amount,
                'TrxCode'      => $trxCode,
                'CPI'          => $cpi,
                'Size'         => $size,
                'MerchantID'   => $merchantId,
                'Type'         => 'dynamic',
            ];
            $response = $this->client->post('/mpesa/qrcode/v1/generate', $payload);
            $failed = $this->providerResponseFailed($response);
            return $this->respond(!$failed, $response, $failed ? 'QR generation failed' : 'QR generated', $failed ? 502 : 200);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] QR error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    /**
     * B2B remittance (business-to-business funds transfer), official
     * CommandID "BusinessPayBill".
     */
    public function b2bRemitTax(float $amount, string $receiverShortcode, string $accountReference, string $remarks = 'B2B payment')
    {
        try {
            $payload = [
                'Initiator'          => $this->client->getInitiatorName(),
                'SecurityCredential' => $this->client->securityCredential(),
                'CommandID'          => 'BusinessPayBill',
                'SenderIdentifierType'   => 4,
                'RecieverIdentifierType' => 4,
                'Amount'             => (int) $amount,
                'PartyA'             => $this->client->getShortcode(),
                'PartyB'             => $receiverShortcode,
                'AccountReference'   => $accountReference,
                'Remarks'            => $remarks,
                'QueueTimeOutURL'    => $this->callbackUrl('/api/payments/mpesa-result'),
                'ResultURL'          => $this->callbackUrl('/api/payments/mpesa-result'),
            ];
            $response = $this->client->post('/mpesa/b2b/v1/paymentrequest', $payload);
            $failed = $this->providerResponseFailed($response);
            return $this->respond(!$failed, $response, $failed ? ($response['errorMessage'] ?? 'B2B payment failed') : 'B2B payment submitted', $failed ? 502 : 200);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] B2B error: ' . $e->getMessage());
            return $this->respond(false, null, 'M-Pesa provider unavailable', 502);
        }
    }

    // =========================================================================
    // DB QUERIES (live schema)
    // =========================================================================

    /**
     * Look up a student by admission number.
     */
    public function validateAdmissionNumber($admissionNumber)
    {
        try {
            $stmt = $this->getDb()->prepare(
                "SELECT s.id, s.admission_no, s.status,
                        CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) AS full_name
                 FROM students s
                 LEFT JOIN persons p ON p.id = s.person_id
                 WHERE s.admission_no = :adm LIMIT 1"
            );
            $stmt->execute(['adm' => $admissionNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                return $this->respond(false, null, 'Invalid admission number');
            }
            return $this->respond(true, $student, 'Student found');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] validateAdmissionNumber error: ' . $e->getMessage());
            return $this->respond(false, null, 'An internal error occurred.', 500);
        }
    }

    /**
     * Recent M-Pesa transactions for an admission number.
     */
    public function getPaymentsByAdmission($admissionNumber, $limit = 10)
    {
        try {
            $stmt = $this->getDb()->prepare(
                "SELECT mpesa_code, amount, transaction_date, phone_number,
                        bill_ref_number, status, transaction_type, checkout_request_id
                 FROM mpesa_transactions
                 WHERE bill_ref_number = :bill_ref OR student_id IN (
                     SELECT id FROM students WHERE admission_no = :adm
                 )
                 ORDER BY transaction_date DESC
                 LIMIT " . (int) $limit
            );
            $stmt->execute([
                'bill_ref' => $admissionNumber,
                'adm'      => $admissionNumber,
            ]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return $this->respond(true, ['transactions' => $rows], 'Transactions retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] getPaymentsByAdmission error: ' . $e->getMessage());
            return $this->respond(false, null, 'An internal error occurred.', 500);
        }
    }

    /**
     * Append a webhook entry to payment_webhooks_log (audit trail).
     */
    public function logWebhook(string $source, array $data, string $status = 'received', bool $signatureVerified = false, ?string $ip = null): void
    {
        try {
            $allowed = ['mpesa_stk', 'mpesa_c2b_validation', 'mpesa_c2b_confirmation', 'mpesa_b2c', 'mpesa_result', 'kcb_bank', 'generic_bank', 'payment_sms'];
            if (!in_array($source, $allowed, true)) {
                $source = 'generic_bank';
            }
            \App\API\Includes\FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => $source,
                'webhook_data' => $data,
                'status' => $status,
                'signature_verified' => $signatureVerified ? 1 : 0,
                'ip' => $ip,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[MpesaPaymentService] logWebhook error: ' . $e->getMessage());
        }
    }
}
