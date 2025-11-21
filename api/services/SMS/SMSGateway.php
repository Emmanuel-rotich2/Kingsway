<?php
namespace App\API\Services\SMS;


class SMSGateway {
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
            'from' => defined('SMS_FROM_NUMBER') ? SMS_FROM_NUMBER : '',
            'wa_number' => defined('SMS_WHATSAPP_NUMBER') ? constant('SMS_WHATSAPP_NUMBER') : '',
        ];
        $this->config = array_merge($defaults, $config);
        $this->provider = $this->initializeProvider();
    }

    public function send($to, $message) {
        return $this->provider->sendMessage($to, $message);
    }

    private function initializeProvider() {
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

interface SMSProvider {
    public function sendMessage($to, $message);
}


class AfricasTalkingProvider implements SMSProvider {
    private $config;
    private $at; // Africa's Talking SDK instance

    public function __construct($config) {
        $this->config = $config;
        $this->at = null;
    }

    public function sendMessage($to, $message) {
        // Use Africa's Talking PHP SDK
        $this->initAfricasTalking();
        $sms = $this->at->sms();
        $from = $this->config['from'] ?? null;
        $options = [];
        if ($from) {
            $options['from'] = $from;
        }
        $result = $sms->send([
            'to' => $to,
            'message' => $message,
            'from' => $from
        ]);
        return $result;
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
            $this->at = new \AfricasTalking\SDK\AfricasTalking(
                $this->config['username'],
                $this->config['api_key']
            );
        }
    }
}

class TwilioProvider implements SMSProvider {
    private $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function sendMessage($to, $message) {
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