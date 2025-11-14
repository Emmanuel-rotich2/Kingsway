<?php
namespace App\API\Services\Payments;

use Exception;

/**
 * MpesaB2CService
 * 
 * Handles M-Pesa Business to Customer (B2C) payments
 * Used for: Staff salaries, supplier payments, refunds
 */
class MpesaB2CService
{
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $initiatorName;
    private $initiatorPassword;
    private $securityCredential;
    private $resultUrl;
    private $queueTimeoutUrl;
    private $baseUrl;

    public function __construct()
    {
        $this->consumerKey = MPESA_CONSUMER_KEY;
        $this->consumerSecret = MPESA_CONSUMER_SECRET;
        $this->shortcode = MPESA_SHORTCODE;
        $this->initiatorName = MPESA_INITIATOR_NAME;
        $this->initiatorPassword = MPESA_INITIATOR_PASSWORD;
        $this->baseUrl = MPESA_BASE_URL;

        $this->resultUrl = BASE_URL . '/api/payments/b2c-callback.php';
        $this->queueTimeoutUrl = BASE_URL . '/api/payments/b2c-timeout.php';

        // Generate security credential (initiator password encrypted with M-Pesa cert)
        $this->securityCredential = $this->generateSecurityCredential();
    }

    /**
     * Send B2C payment
     */
    public function sendPayment($data)
    {
        try {
            $phone = $data['phone'];
            $amount = $data['amount'];
            $remarks = $data['remarks'] ?? 'Payment';
            $occasion = $data['occasion'] ?? '';

            // Get access token
            $accessToken = $this->getAccessToken();

            // Prepare B2C request
            $url = $this->baseUrl . '/mpesa/b2c/v1/paymentrequest';

            $payload = [
                'InitiatorName' => $this->initiatorName,
                'SecurityCredential' => $this->securityCredential,
                'CommandID' => 'BusinessPayment', // or 'SalaryPayment', 'PromotionPayment'
                'Amount' => $amount,
                'PartyA' => $this->shortcode,
                'PartyB' => $phone,
                'Remarks' => $remarks,
                'QueueTimeOutURL' => $this->queueTimeoutUrl,
                'ResultURL' => $this->resultUrl,
                'Occasion' => $occasion
            ];

            $response = $this->makeRequest($url, $payload, $accessToken);

            // Log the request
            $this->logTransaction($phone, $amount, $response);

            if (isset($response['ResponseCode']) && $response['ResponseCode'] === '0') {
                return [
                    'status' => 'success',
                    'message' => 'Payment request sent successfully',
                    'transaction_ref' => $response['ConversationID'],
                    'originator_conversation_id' => $response['OriginatorConversationID'],
                    'response' => $response
                ];
            } else {
                throw new Exception($response['ResponseDescription'] ?? 'Payment request failed');
            }

        } catch (Exception $e) {
            $this->logError("B2C Payment failed: " . $e->getMessage());
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check M-Pesa account balance
     */
    public function checkAccountBalance()
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = $this->baseUrl . '/mpesa/accountbalance/v1/query';

            $payload = [
                'Initiator' => $this->initiatorName,
                'SecurityCredential' => $this->securityCredential,
                'CommandID' => 'AccountBalance',
                'PartyA' => $this->shortcode,
                'IdentifierType' => '4', // 4 for organization shortcode
                'Remarks' => 'Balance query',
                'QueueTimeOutURL' => $this->queueTimeoutUrl,
                'ResultURL' => BASE_URL . '/api/payments/balance-callback.php'
            ];

            $response = $this->makeRequest($url, $payload, $accessToken);

            // Balance will come via callback, return pending status
            return [
                'status' => 'pending',
                'message' => 'Balance query initiated',
                'conversation_id' => $response['ConversationID'] ?? null
            ];

        } catch (Exception $e) {
            $this->logError("Balance check failed: " . $e->getMessage());
            return 0; // Return 0 if can't check balance
        }
    }

    /**
     * Get M-Pesa access token
     */
    private function getAccessToken()
    {
        $url = $this->baseUrl . '/oauth/v1/generate?grant_type=client_credentials';

        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
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
     * Make API request to M-Pesa
     */
    private function makeRequest($url, $payload, $accessToken)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            throw new Exception("API request failed. HTTP Code: $httpCode. Response: " . ($result['errorMessage'] ?? $response));
        }

        return $result;
    }

    /**
     * Generate security credential (encrypt initiator password)
     */
    private function generateSecurityCredential()
    {
        // In production, you need M-Pesa public certificate
        // Download from: https://developer.safaricom.co.ke/Documentation

        $certPath = __DIR__ . '/../../../config/mpesa_production_cert.cer';

        if (!file_exists($certPath)) {
            // For sandbox, return base64 encoded password
            return base64_encode($this->initiatorPassword);
        }

        $publicKey = file_get_contents($certPath);
        openssl_public_encrypt($this->initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Log transaction
     */
    private function logTransaction($phone, $amount, $response)
    {
        $logFile = __DIR__ . '/../../../logs/mpesa_b2c.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] B2C Payment - Phone: $phone, Amount: $amount, Response: " . json_encode($response) . "\n";
        error_log($message, 3, $logFile);
    }

    /**
     * Log errors
     */
    private function logError($message)
    {
        $logFile = __DIR__ . '/../../../logs/mpesa_b2c_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $logFile);
    }
}
