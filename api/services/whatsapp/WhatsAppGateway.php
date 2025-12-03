<?php
namespace App\API\Services\whatsapp;

class WhatsAppGateway
{
    private $config;
    private $provider;

    public function __construct($config = [])
    {
        // Load from config.php if not provided
        $defaults = [
            'provider' => defined('SMS_PROVIDER') ? SMS_PROVIDER : 'africastalking',
            'api_key' => defined('SMS_API_KEY') ? SMS_API_KEY : '',
            'username' => defined('SMS_USERNAME') ? SMS_USERNAME : '',
            'wa_number' => defined('SMS_WHATSAPP_NUMBER') ? constant('SMS_WHATSAPP_NUMBER') : '',
        ];
        $this->config = array_merge($defaults, $config);
        $this->provider = $this->initializeProvider();
    }

    public function sendMessage($to, $message)
    {
        return $this->provider->sendMessage($to, $message);
    }

    public function sendTemplate($to, $templateId, $variables = [])
    {
        if (method_exists($this->provider, 'sendTemplate')) {
            return $this->provider->sendTemplate($to, $templateId, $variables);
        }
        throw new \Exception('Template sending not supported by this provider');
    }

    public function createTemplate($templateConfig)
    {
        if (method_exists($this->provider, 'createTemplate')) {
            return $this->provider->createTemplate($templateConfig);
        }
        throw new \Exception('Template creation not supported by this provider');
    }

    private function initializeProvider()
    {
        switch ($this->config['provider']) {
            case 'africastalking':
                return new AfricasTalkingWhatsAppProvider($this->config);
            default:
                throw new \Exception('Unsupported WhatsApp provider');
        }
    }
}

interface WhatsAppProvider
{
    public function sendMessage($to, $message);
}

class AfricasTalkingWhatsAppProvider implements WhatsAppProvider
{
    private $config;
    private $apiKey;
    private $username;
    private $waNumber;
    private $isSandbox;

    public function __construct($config)
    {
        $this->config = $config;
        $this->apiKey = $config['api_key'] ?? '';
        $this->username = $config['username'] ?? 'sandbox';
        $this->waNumber = $config['wa_number'] ?? '';
        $this->isSandbox = ($this->username === 'sandbox');

        if (empty($this->apiKey)) {
            throw new \Exception("WhatsApp API Key is not configured");
        }
        if (empty($this->waNumber)) {
            throw new \Exception("WhatsApp Number is not configured");
        }
    }

    public function sendMessage($to, $message)
    {
        $url = $this->isSandbox 
            ? 'https://chat.sandbox.africastalking.com/whatsapp/message/send'
            : 'https://chat.africastalking.com/whatsapp/message/send';

        $body = [
            'username' => $this->username,
            'waNumber' => $this->waNumber,
            'phoneNumber' => $to,
            'body' => $message
        ];

        return $this->sendRequest($url, $body, "Send Message");
    }

    public function sendTemplate($to, $templateId, $variables = [])
    {
        $url = $this->isSandbox 
            ? 'https://chat.sandbox.africastalking.com/whatsapp/template/send'
            : 'https://chat.africastalking.com/whatsapp/template/send';

        $body = [
            'username' => $this->username,
            'waNumber' => $this->waNumber,
            'phoneNumber' => $to,
            'templateId' => $templateId
        ];

        if (!empty($variables)) {
            $body['parameters'] = $variables;
        }

        return $this->sendRequest($url, $body, "Send Template");
    }

    public function createTemplate($templateConfig)
    {
        // Templates can only be created in production, not sandbox
        if ($this->isSandbox) {
            throw new \Exception("Template creation is only available in production environment");
        }

        $url = 'https://chat.africastalking.com/whatsapp/template/send';

        $body = [
            'username' => $this->username,
            'waNumber' => $this->waNumber,
            'name' => $templateConfig['name'] ?? '',
            'language' => $templateConfig['language'] ?? 'en',
            'category' => $templateConfig['category'] ?? 'UTILITY',
            'components' => $templateConfig['components'] ?? []
        ];

        return $this->sendRequest($url, $body, "Create Template");
    }

    private function sendRequest($url, $body, $action = "Request")
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Content-Type: application/json',
                'apiKey: ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                $this->logWhatsAppRequest($action, $body, "CURL ERROR: $error");
                return [
                    'status' => 'error',
                    'message' => "cURL error: $error",
                    'data' => null
                ];
            }

            $result = json_decode($response, true);
            $this->logWhatsAppRequest($action, $body, $response, $httpCode);

            if ($httpCode !== 200) {
                return [
                    'status' => 'error',
                    'message' => $result['error'] ?? "HTTP $httpCode",
                    'data' => $result
                ];
            }

            return [
                'status' => 'success',
                'message' => $result['message'] ?? 'Request processed',
                'data' => $result
            ];
        } catch (\Exception $e) {
            $this->logWhatsAppRequest($action, $body, "EXCEPTION: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    private function logWhatsAppRequest($action, $body, $response = "", $httpCode = null)
    {
        $logFile = __DIR__ . '/../../../logs/whatsapp_requests.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $bodyStr = isset($body['phoneNumber']) ? $body['phoneNumber'] : 'N/A';
        $httpStr = $httpCode ? " | HTTP: $httpCode" : "";
        
        $logMessage = "[$timestamp] $action - To: $bodyStr$httpStr\n";
        $logMessage .= "Request Body: " . json_encode($body) . "\n";
        $logMessage .= "Response: " . substr($response, 0, 500) . "\n";
        $logMessage .= "---\n";

        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>
