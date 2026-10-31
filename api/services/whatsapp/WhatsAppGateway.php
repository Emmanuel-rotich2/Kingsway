<?php
namespace App\API\Services\whatsapp;

/**
 * WhatsAppGateway — provider-agnostic WhatsApp transport facade.
 *
 * WhatsApp delivery is a messaging-provider capability. The endpoint, payload
 * and response mapping for the configured provider live in HttpWhatsAppProvider
 * so switching provider only touches this file plus configuration.
 */
class WhatsAppGateway
{
    private $config;
    private $provider;

    public function __construct($config = [])
    {
        $defaults = [
            'provider'  => defined('SMS_PROVIDER') ? constant('SMS_PROVIDER') : 'talksasa',
            'api_key'   => defined('SMS_API_KEY') ? constant('SMS_API_KEY') : '',
            'username'  => defined('SMS_USERNAME') ? constant('SMS_USERNAME') : '',
            'sender_id' => defined('SMS_SENDER_ID') ? constant('SMS_SENDER_ID') : '',
            'api_url'   => defined('SMS_API_URL') ? constant('SMS_API_URL') : 'https://bulksms.talksasa.com/api/v3/',
        ];
        $this->config = array_merge($defaults, $config);
        $this->provider = new HttpWhatsAppProvider($this->config);
    }

    public function sendMessage($to, $message)
    {
        self::assertEnabled();
        return $this->provider->sendMessage($to, (string) $message);
    }

    public function sendMedia($to, $message, $mediaType, $url, $caption = null)
    {
        self::assertEnabled();
        return $this->provider->sendMedia($to, (string) $message, $mediaType, $url, $caption);
    }

    public function sendTemplate($to, $templateId, $variables = [])
    {
        self::assertEnabled();
        return $this->provider->sendTemplate($to, $templateId, $variables);
    }

    public static function isEnabled(): bool
    {
        return filter_var(\App\Config\Config::get('WHATSAPP_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function assertEnabled(): void
    {
        if (!self::isEnabled()) {
            throw new \RuntimeException('WhatsApp sending is disabled. Use SMS or email until WhatsApp is configured.');
        }
    }

    public function createTemplate($templateConfig)
    {
        return $this->provider->createTemplate($templateConfig);
    }
}

interface WhatsAppProvider
{
    public function sendMessage($to, $message);

    public function sendMedia($to, $message, $mediaType, $url, $caption = null);

    public function sendTemplate($to, $templateId, $variables = []);

    public function createTemplate($templateConfig);
}

/**
 * HTTP WhatsApp provider — sends through the messaging provider's WhatsApp
 * channel (a `type: whatsapp` message on the shared send endpoint).
 */
class HttpWhatsAppProvider implements WhatsAppProvider
{
    use \App\API\Services\sms\SmsProviderTransport;

    private $config;
    private $baseUrl;
    private $apiKey;
    private $senderId;

    public function __construct($config)
    {
        $this->config = $config;
        $this->baseUrl = rtrim((string) ($config['api_url'] ?? 'https://bulksms.talksasa.com/api/v3/'), '/');
        $this->apiKey = (string) ($config['api_key'] ?? '');
        $sender = trim((string) ($config['sender_id'] ?? ''));
        $this->senderId = $sender;
    }

    public function sendMessage($to, $message)
    {
        $this->assertConfigured();
        return $this->wrapSend($this->request('POST', '/sms/send', [
            'recipient' => $to,
            'sender_id' => $this->senderId,
            'type' => 'whatsapp',
            'message' => (string) $message,
        ]));
    }

    public function sendMedia($to, $message, $mediaType, $url, $caption = null)
    {
        $this->assertConfigured();
        $url = trim((string) $url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || stripos($url, 'https://') !== 0) {
            throw new \InvalidArgumentException('WhatsApp media URL must be a public HTTPS URL');
        }
        return $this->wrapSend($this->request('POST', '/sms/send', [
            'recipient' => $to,
            'sender_id' => $this->senderId,
            'type' => 'whatsapp',
            'message' => (string) ($caption ?? $message),
            'media_url' => $url,
        ]));
    }

    /**
     * The configured provider (TALKSASA) has no provider-side template
     * surface: templates are a local rendering concept here. When the caller
     * supplies an already-rendered body the message is sent as a normal
     * WhatsApp message; otherwise the failure is explicit instead of a
     * silent no-op, so callers can fall back (see OTPDeliveryService) or
     * fail the endpoint with an actionable last_error.
     */
    public function sendTemplate($to, $templateId, $variables = [])
    {
        $this->assertConfigured();
        foreach (['body', 'message', 'text'] as $key) {
            $rendered = trim((string) (is_array($variables) ? ($variables[$key] ?? '') : ''));
            if ($rendered !== '') {
                return $this->wrapSend($this->request('POST', '/sms/send', [
                    'recipient' => $to,
                    'sender_id' => $this->senderId,
                    'type' => 'whatsapp',
                    'message' => $rendered,
                ]));
            }
        }
        throw new \RuntimeException(
            'The configured messaging provider does not support provider-side WhatsApp templates; send a rendered message instead.'
        );
    }

    /**
     * Provider-side template creation is not available. Returns a structured
     * error (instead of throwing) so the management endpoint reports the
     * limitation to the operator without an unhandled failure.
     */
    public function createTemplate($templateConfig)
    {
        return [
            'status' => 'error',
            'message' => 'The configured messaging provider does not support creating WhatsApp templates.',
            'data' => null,
        ];
    }

    private function wrapSend(array $response): array
    {
        if (!($response['ok'] ?? false)) {
            throw new \Exception('WhatsApp send failed: ' . ($response['message'] ?? 'unknown provider error'));
        }
        return [
            'status' => $response['status'] !== '' ? $response['status'] : 'success',
            'data' => $response['data'],
        ];
    }

    /**
     * Everything executes through the Python platform relay first, with the
     * shared stream transport as the resilience path (never curl — this host
     * does not load the curl extension). See SmsProviderTransport.
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        return $this->providerTransport($this->config, strtoupper($method), $path, $body);
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('WhatsApp API credentials are not configured.');
        }
        if ($this->senderId === '') {
            throw new \RuntimeException('TalkSasa sender ID is not configured. Set the shared SMS Sender ID for WhatsApp messages.');
        }
    }
}
