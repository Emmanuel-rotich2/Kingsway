<?php
namespace App\API\Services\SMS;

class SMSGateway {
    private $config;
    private $provider;

    public function __construct($config) {
        $this->config = $config;
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

    public function __construct($config) {
        $this->config = $config;
    }

    public function sendMessage($to, $message) {
        // Implementation for Africa's Talking API
        $url = 'https://api.africastalking.com/version1/messaging';
        $headers = [
            'ApiKey' => $this->config['api_key'],
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        ];
        
        $data = [
            'username' => $this->config['username'],
            'to' => $to,
            'message' => $message
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