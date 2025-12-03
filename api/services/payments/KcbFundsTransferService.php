<?php
namespace App\API\Services\payments;

use Exception;

/**
 * KcbFundsTransferService
 * 
 * Handles KCB Bank funds transfer (B2C)
 * Used for: Staff salaries, supplier payments via bank transfer
 */
class KcbFundsTransferService
{
    private $consumerKey;
    private $consumerSecret;
    private $apiKey;
    private $baseUrl;
    private $debitAccount; // School's KCB account

    public function __construct()
    {
        $this->consumerKey = KCB_CONSUMER_KEY;
        $this->consumerSecret = KCB_CONSUMER_SECRET;
        $this->apiKey = KCB_API_KEY;
        $this->baseUrl = KCB_BASE_URL;
        $this->debitAccount = KCB_CREDIT_ACCOUNT; // School's account
    }

    /**
     * Transfer funds to staff/supplier
     */
    public function transferFunds($data)
    {
        try {
            $accountNumber = $data['account_number'];
            $bankName = $data['bank_name'];
            $amount = $data['amount'];
            $narration = $data['narration'] ?? 'Payment';
            $beneficiaryName = $data['beneficiary_name'] ?? '';

            // Get access token
            $accessToken = $this->getAccessToken();

            // KCB Funds Transfer API endpoint
            $url = $this->baseUrl . '/fundstransfer/1.0.0/transfer';

            $payload = [
                'debitAccount' => $this->debitAccount,
                'creditAccount' => $accountNumber,
                'amount' => $amount,
                'currency' => 'KES',
                'narration' => $narration,
                'beneficiaryName' => $beneficiaryName,
                'transactionReference' => $this->generateReference(),
                'callbackUrl' => BASE_URL . '/api/payments/kcb-transfer-notification.php'
            ];

            // If transferring to another bank (RTGS/EFT), add bank code
            if (strtoupper($bankName) !== 'KCB') {
                $payload['bankCode'] = $this->getBankCode($bankName);
            }

            $response = $this->makeRequest($url, $payload, $accessToken);

            // Log the transaction
            $this->logTransaction($accountNumber, $amount, $response);

            if (isset($response['status']) && $response['status'] === 'SUCCESS') {
                return [
                    'status' => 'success',
                    'message' => 'Transfer initiated successfully',
                    'transaction_ref' => $response['transactionReference'] ?? $payload['transactionReference'],
                    'response' => $response
                ];
            } else {
                throw new Exception($response['message'] ?? 'Transfer failed');
            }

        } catch (Exception $e) {
            $this->logError("KCB Transfer failed: " . $e->getMessage());
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check KCB account balance
     */
    public function checkAccountBalance()
    {
        try {
            $accessToken = $this->getAccessToken();

            // KCB Account Balance API
            $url = $this->baseUrl . '/fundstransfer/1.0.0/balance';

            $payload = [
                'accountNumber' => $this->debitAccount
            ];

            $response = $this->makeRequest($url, $payload, $accessToken);

            if (isset($response['balance'])) {
                return (float) $response['balance'];
            }

            return 0;

        } catch (Exception $e) {
            $this->logError("Balance check failed: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get OAuth access token from KCB
     */
    private function getAccessToken()
    {
        $url = KCB_TOKEN_ENDPOINT;

        $payload = [
            'grant_type' => 'client_credentials'
        ];

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("Failed to get access token. HTTP Code: $httpCode");
        }

        $result = json_decode($response, true);

        if (!isset($result['access_token'])) {
            throw new Exception("Access token not found in response");
        }

        return $result['access_token'];
    }

    /**
     * Make API request to KCB
     */
    private function makeRequest($url, $payload, $accessToken)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'X-Api-Key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 && $httpCode !== 201) {
            throw new Exception("API request failed. HTTP Code: $httpCode. Response: " . ($result['message'] ?? $response));
        }

        return $result;
    }

    /**
     * Generate unique transaction reference
     */
    private function generateReference()
    {
        return 'TXN' . date('YmdHis') . rand(1000, 9999);
    }

    /**
     * Get bank code for RTGS/EFT transfers
     */
    private function getBankCode($bankName)
    {
        $bankCodes = [
            'EQUITY' => '68',
            'EQUITY BANK' => '68',
            'CO-OPERATIVE' => '11',
            'COOP' => '11',
            'COOPERATIVE BANK' => '11',
            'ABSA' => '03',
            'BARCLAYS' => '03',
            'NCBA' => '07',
            'STANBIC' => '31',
            'STANDARD CHARTERED' => '02',
            'I&M' => '57',
            'FAMILY BANK' => '70',
            'DTB' => '63',
            'DIAMOND TRUST' => '63'
        ];

        $bankName = strtoupper($bankName);

        return $bankCodes[$bankName] ?? '01'; // Default to KCB if unknown
    }

    /**
     * Log transaction
     */
    private function logTransaction($account, $amount, $response)
    {
        $logFile = __DIR__ . '/../../../logs/kcb_transfers.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] KCB Transfer - Account: $account, Amount: $amount, Response: " . json_encode($response) . "\n";
        error_log($message, 3, $logFile);
    }

    /**
     * Log errors
     */
    private function logError($message)
    {
        $logFile = __DIR__ . '/../../../logs/kcb_transfer_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $logFile);
    }
}
