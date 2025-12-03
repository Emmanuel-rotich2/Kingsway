<?php
namespace App\API\Services\sms;


class SMSGateway
{
    // Public method to send WhatsApp message using the provider
    public function sendWhatsApp($to, $message, $media = null)
    {
        if (method_exists($this->provider, 'sendWhatsApp')) {
            return $this->provider->sendWhatsApp($to, $message, $media);
        }
        throw new \Exception('WhatsApp sending not supported by this provider');
    }
    private $config;
    private $provider;

    public function __construct($config = [])
    {
        // Always load from config.php if not provided
        $defaults = [
            'provider' => defined('SMS_PROVIDER') ? SMS_PROVIDER : 'africastalking',
            'api_key' => defined('SMS_API_KEY') ? SMS_API_KEY : '',
            'username' => defined('SMS_USERNAME') ? SMS_USERNAME : '',
            'appname' => defined('SMS_APPNAME') ? SMS_APPNAME : '',
            'sender_id' => defined('SMS_SENDER_ID') ? SMS_SENDER_ID : '',
            'shortcode' => defined('SMS_SHORTCODE') ? SMS_SHORTCODE : '',
            'wa_number' => defined('SMS_WHATSAPP_NUMBER') ? constant('SMS_WHATSAPP_NUMBER') : '',
        ];
        $this->config = array_merge($defaults, $config);
        $this->provider = $this->initializeProvider();
    }

    public function send($to, $message)
    {
        return $this->provider->sendMessage($to, $message);
    }

    private function initializeProvider()
    {
        switch ($this->config['provider']) {
            case 'africastalking':
                return new AfricasTalkingProvider($this->config);
            case 'twilio':
                return new TwilioProvider($this->config);
            default:
                throw new \Exception('Unsupported SMS provider');
        }
    }
}

interface SMSProvider
{
    public function sendMessage($to, $message);
}


class AfricasTalkingProvider implements SMSProvider
{
    private $config;
    private $at; // Africa's Talking SDK instance

    public function __construct($config)
    {
        $this->config = $config;
        $this->at = null;
    }

    public function sendMessage($to, $message)
    {
        // Use Africa's Talking PHP SDK
        $this->initAfricasTalking();

        $options = [
            'to' => $to,
            'message' => $message
        ];

        // Get configured sender IDs - try both alphanumeric and shortcode
        $senderId = $this->config['sender_id'] ?? null;
        $shortCode = $this->config['shortcode'] ?? null;

        // Build list of sender IDs to try (alphanumeric first, then shortcode)
        $senderIds = [];
        if (!empty($senderId)) {
            $senderIds[] = $senderId;
        }
        if (!empty($shortCode) && $shortCode !== $senderId) {
            $senderIds[] = $shortCode;
        }

        // Track overall success across all attempts
        $anySucceeded = false;
        $attemptedIds = [];

        // If no sender IDs configured, try without 'from' parameter
        if (empty($senderIds)) {
            $result = $this->attemptSend($to, $message, $options);
            return $result === true;
        }

        // Try each sender ID with fallback
        foreach ($senderIds as $index => $from) {
            $optionsWithFrom = $options;
            $optionsWithFrom['from'] = $from;
            $attemptedIds[] = $from;

            // Log the request
            $this->logSmsRequest($to, $message, $optionsWithFrom, "Attempt " . ($index + 1) . " with '$from'");

            $result = $this->attemptSend($to, $message, $optionsWithFrom);
            $this->logSmsResponse($to, "Attempt " . ($index + 1) . " result: " . ($result ? "TRUE" : "FALSE"));

            // If this attempt succeeded, mark it
            if ($result === true) {
                $anySucceeded = true;
                $this->logSmsResponse($to, "✓ SUCCESS on attempt " . ($index + 1) . " with sender: $from");
                break;
            }
        }

        // Log final result
        if (!$anySucceeded) {
            $this->logSmsResponse($to, "✗ All sender IDs failed. Tried: " . implode(", ", $attemptedIds));
        } else {
            $this->logSmsResponse($to, "✓ Message sent successfully");
        }

        return $anySucceeded;
    }

    private function attemptSend($to, $message, $options)
    {
        try {
            // Fresh SDK instance for each send to avoid connection state issues
            $sms = $this->at->sms();
            $result = $sms->send($options);

            // Log the response for debugging
            $this->logSmsResponse($to, $result);

            // Check the actual response for errors
            // Africa's Talking SDK returns an array with 'status' and 'data' keys
            $msgData = null;

            // Handle array response (most common from SDK)
            if (is_array($result)) {
                if (!isset($result['data']) || !isset($result['data']->SMSMessageData)) {
                    return false;
                }
                $msgData = $result['data']->SMSMessageData;
            }
            // Handle object response (fallback)
            elseif (is_object($result)) {
                if (!property_exists($result, 'SMSMessageData')) {
                    return false;
                }
                $msgData = $result->SMSMessageData;
            } else {
                return false;
            }

            // Now we have SMSMessageData, check for errors
            if ($msgData) {
                // Check for error messages
                if (property_exists($msgData, 'Message')) {
                    $errorMsg = $msgData->Message;
                    // Only treat InvalidSenderId as error, not success messages
                    if ($errorMsg === 'InvalidSenderId') {
                        $this->logSmsResponse($to, "ERROR: InvalidSenderId");
                        return false;
                    }
                }

                // Check if recipients array exists and has items
                if (property_exists($msgData, 'Recipients') && is_array($msgData->Recipients)) {
                    $recipients = $msgData->Recipients;

                    if (empty($recipients)) {
                        $this->logSmsResponse($to, "ERROR: No valid recipients");
                        return false;
                    }

                    // Check each recipient's status code
                    $foundSuccess = false;
                    foreach ($recipients as $recipient) {
                        if (property_exists($recipient, 'statusCode')) {
                            $statusCode = $recipient->statusCode;
                            // Success codes: 100 (Processed), 101 (Sent), 102 (Queued)
                            if ($statusCode >= 100 && $statusCode <= 102) {
                                $foundSuccess = true;
                                $this->logSmsResponse($to, "✓ Recipient {$recipient->number} statusCode: $statusCode");
                                break;
                            }
                        }
                    }

                    if ($foundSuccess) {
                        return true;
                    } else {
                        $this->logSmsResponse($to, "ERROR: No valid status codes in recipients");
                        return false;
                    }
                }
            }

            return false;
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $this->logSmsResponse($to, "EXCEPTION: " . $errorMsg);

            // Don't retry on SSL errors - they indicate temporary connection issues
            // SSL error 35 = SSL handshake error (likely temporary)
            if (stripos($errorMsg, 'SSL') !== false || stripos($errorMsg, 'ssl3_get_record') !== false) {
                $this->logSmsResponse($to, "SSL error detected - retrying with different sender ID may help");
            }

            return false;
        }
    }
    private function logSmsResponse($to, $result)
    {
        $logFile = __DIR__ . '/../../../logs/sms_responses.log';
        $timestamp = date('Y-m-d H:i:s');

        // Handle both objects and arrays
        if (is_object($result)) {
            $resultStr = json_encode((array) $result);
        } else {
            $resultStr = json_encode($result);
        }

        $logMessage = "[$timestamp] To: $to | Response: " . $resultStr . "\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    private function logSmsRequest($to, $message, $options, $attempt = "")
    {
        $logFile = __DIR__ . '/../../../logs/sms_responses.log';
        $timestamp = date('Y-m-d H:i:s');
        $attemptStr = !empty($attempt) ? " | $attempt" : "";
        $logMessage = "[$timestamp] REQUEST - To: $to | Options: " . json_encode($options) . " | Message: " . substr($message, 0, 50) . "...$attemptStr\n";
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Send MMS using Africa's Talking
     * @param string $to
     * @param string $message
     * @param string $mediaUrl
     * @return mixed
     */
    public function sendMMS($to, $message, $mediaUrl)
    {
        $this->initAfricasTalking();
        $sms = $this->at->sms();
        $from = $this->config['from'] ?? null;
        $result = $sms->send([
            'to' => $to,
            'message' => $message,
            'from' => $from,
            'mediaUrl' => $mediaUrl
        ]);
        return $result;
    }

    /**
     * Send WhatsApp message using Africa's Talking
     * @param string $to
     * @param string $message
     * @return mixed
     */
    /**
     * Send WhatsApp message using Africa's Talking HTTP API
     * @param string $to
     * @param string $message
     * @param array|null $media (optional)
     * @return mixed
     */
    public function sendWhatsApp($to, $message, $media = null)
    {
        $url = 'https://chat.africastalking.com/whatsapp/message/send';
        $apiKey = $this->config['api_key'];
        $username = $this->config['username'];
        $waNumber = $this->config['wa_number'] ?? null;
        $body = [
            'username' => $username,
            'waNumber' => $waNumber,
            'phoneNumber' => $to,
            'body' => ['message' => $message]
        ];
        if ($media && is_array($media)) {
            $body['body'] = array_merge($body['body'], $media);
        }
        $headers = [
            'apiKey: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error) {
            throw new \Exception("WhatsApp sending failed: $error");
        }
        return json_decode($response, true);
    }

    private function initAfricasTalking()
    {
        if ($this->at === null) {
            // Use Composer autoload
            require_once __DIR__ . '/../../../vendor/autoload.php';

            $username = $this->config['username'] ?? 'sandbox';
            $apiKey = $this->config['api_key'] ?? '';

            // Validate credentials
            if (empty($apiKey)) {
                throw new \Exception("SMS API Key is not configured. Check SMS_API_KEY in config.php");
            }
            if (empty($username)) {
                throw new \Exception("SMS Username is not configured. Check SMS_USERNAME in config.php");
            }

            // Initialize Africa's Talking SDK
            $this->at = new \AfricasTalking\SDK\AfricasTalking($username, $apiKey);

            // Log initialization details
            $isSandbox = ($username === 'sandbox');
            $endpoint = $isSandbox ? 'https://api.sandbox.africastalking.com' : 'https://api.africastalking.com';
            $apiKeyMasked = substr($apiKey, 0, 10) . '...' . substr($apiKey, -10);
            $this->logSmsRequest('', "SDK initialized", [
                'username' => $username,
                'api_key' => $apiKeyMasked,
                'environment' => $isSandbox ? 'SANDBOX' : 'PRODUCTION',
                'endpoint' => $endpoint,
                'sender_id' => $this->config['sender_id'] ?? 'NOT_SET',
                'shortcode' => $this->config['shortcode'] ?? 'NOT_SET'
            ], "Initialization");
        }
    }
}

class TwilioProvider implements SMSProvider
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function sendMessage($to, $message)
    {
        // Implementation for Twilio API
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->config['account_sid']}/Messages.json";
        $auth = base64_encode("{$this->config['account_sid']}:{$this->config['auth_token']}");

        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $data = [
            'To' => $to,
            'From' => $this->config['from'],
            'Body' => $message
        ];

        // Send request using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("SMS sending failed: $error");
        }

        return json_decode($response, true);
    }
}