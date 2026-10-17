<?php

namespace App\API\Services\sms;

/**
 * SMSGateway — provider-agnostic messaging capability surface.
 *
 * Owns transport only: SMS, WhatsApp, Voice, MMS, Hashed SMS, Campaigns,
 * account/profile, contact groups, contacts and message history. Business
 * orchestration and database writes live in SMSService.
 *
 * To switch messaging provider, update the provider block in configuration
 * and this file's HttpSmsProvider endpoint/payload/response mapping. No other
 * file changes are required — the rest of the system talks to this surface.
 */
class SMSGateway
{
    private $config;
    private $provider;

    public function __construct($config = [])
    {
        $defaults = [
            'provider'         => defined('SMS_PROVIDER') ? constant('SMS_PROVIDER') : 'talksasa',
            'api_key'          => defined('SMS_API_KEY') ? constant('SMS_API_KEY') : '',
            'username'         => defined('SMS_USERNAME') ? constant('SMS_USERNAME') : '',
            'appname'          => defined('SMS_APPNAME') ? constant('SMS_APPNAME') : '',
            'sender_id'        => defined('SMS_SENDER_ID') ? constant('SMS_SENDER_ID') : '',
            'shortcode'        => defined('SMS_SHORTCODE') ? constant('SMS_SHORTCODE') : '',
            'api_url'          => defined('SMS_API_URL') ? constant('SMS_API_URL') : 'https://bulksms.talksasa.com/api/v3/',
            'wa_number'        => defined('SMS_WHATSAPP_NUMBER') ? constant('SMS_WHATSAPP_NUMBER') : '',
            'voice_language'   => defined('SMS_VOICE_LANGUAGE') ? constant('SMS_VOICE_LANGUAGE') : 'en-gb',
            'voice_gender'     => defined('SMS_VOICE_GENDER') ? constant('SMS_VOICE_GENDER') : 'female',
        ];
        $this->config = array_merge($defaults, $config);
        $this->provider = $this->initializeProvider();
    }

    public function providerName(): string
    {
        return (string) ($this->config['provider'] ?? '');
    }

    public function senderId(): string
    {
        $sender = trim((string) ($this->config['sender_id'] ?? ''));
        if ($sender !== '') {
            return $sender;
        }
        $shortcode = trim((string) ($this->config['shortcode'] ?? ''));
        if ($shortcode !== '') {
            return $shortcode;
        }
        return (string) ($this->config['username'] ?? '');
    }

    // ── Outbound messaging ──────────────────────────────────────────────────

    public function send($to, $message)
    {
        return $this->providerCall('sendMessage', [$to, (string) $message]);
    }

    public function sendBulk($to, $message, $scheduleTime = null, $dltTemplateId = null)
    {
        if (method_exists($this->provider, 'sendBulk')) {
            return $this->provider->sendBulk($to, (string) $message, $scheduleTime, $dltTemplateId);
        }
        return $this->providerCall('sendMessage', [$to, (string) $message]);
    }

    public function sendWhatsApp($to, $message, $media = null)
    {
        return $this->providerCall('sendWhatsApp', [$to, (string) $message, $media]);
    }

    public function sendVoice($to, $message, $language = null, $gender = null)
    {
        $language = $language ?: (string) ($this->config['voice_language'] ?? 'en-gb');
        $gender = $gender ?: (string) ($this->config['voice_gender'] ?? 'female');
        return $this->providerCall('sendVoice', [$to, (string) $message, $language, $gender]);
    }

    public function sendMMS($to, $message, $mediaUrl)
    {
        return $this->providerCall('sendMMS', [$to, (string) $message, $mediaUrl]);
    }

    public function sendHashed($recipient, $message, $recipientFormat = 'hashed')
    {
        return $this->providerCall('sendHashed', [$recipient, (string) $message, $recipientFormat]);
    }

    public function sendCampaign($contactListIds, $message, $scheduleTime = null, $dltTemplateId = null)
    {
        return $this->providerCall('sendCampaign', [$contactListIds, (string) $message, $scheduleTime, $dltTemplateId]);
    }

    public function queueStatus($queueUid)
    {
        return $this->providerCall('queueStatus', [$queueUid]);
    }

    // ── Account ─────────────────────────────────────────────────────────────

    public function balance()
    {
        return $this->providerCall('balance', []);
    }

    public function profile()
    {
        return $this->providerCall('profile', []);
    }

    // ── Contact groups ──────────────────────────────────────────────────────

    public function listGroups()
    {
        return $this->providerCall('listGroups', []);
    }

    public function createGroup($name)
    {
        return $this->providerCall('createGroup', [$name]);
    }

    public function showGroup($groupId)
    {
        return $this->providerCall('showGroup', [$groupId]);
    }

    public function updateGroup($groupId, $name)
    {
        return $this->providerCall('updateGroup', [$groupId, $name]);
    }

    public function deleteGroup($groupId)
    {
        return $this->providerCall('deleteGroup', [$groupId]);
    }

    // ── Contacts ────────────────────────────────────────────────────────────

    public function listContacts($groupId)
    {
        return $this->providerCall('listContacts', [$groupId]);
    }

    public function storeContact($groupId, $phone, $firstName = '', $lastName = '')
    {
        return $this->providerCall('storeContact', [$groupId, $phone, $firstName, $lastName]);
    }

    public function showContact($groupId, $uid)
    {
        return $this->providerCall('showContact', [$groupId, $uid]);
    }

    public function updateContact($groupId, $uid, $phone, $firstName = '', $lastName = '')
    {
        return $this->providerCall('updateContact', [$groupId, $uid, $phone, $firstName, $lastName]);
    }

    public function deleteContact($groupId, $uid)
    {
        return $this->providerCall('deleteContact', [$groupId, $uid]);
    }

    // ── Messages ────────────────────────────────────────────────────────────

    public function listMessages()
    {
        return $this->providerCall('listMessages', []);
    }

    public function getMessage($uid)
    {
        return $this->providerCall('getMessage', [$uid]);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function providerCall(string $method, array $args)
    {
        if (!method_exists($this->provider, $method)) {
            throw new \Exception('Messaging provider does not support ' . $method);
        }
        return $this->provider->$method(...$args);
    }

    private function initializeProvider()
    {
        return new HttpSmsProvider($this->config);
    }
}

interface SMSProvider
{
    public function sendMessage($to, $message);
}

/**
 * HTTP messaging provider. Endpoint paths, request payload and response
 * parsing for the configured provider live entirely in this class so the
 * school can switch provider by editing this one file plus configuration.
 */
class HttpSmsProvider implements SMSProvider
{
    use SmsProviderTransport;

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
        if ($sender === '') {
            $sender = trim((string) ($config['shortcode'] ?? ''));
        }
        if ($sender === '') {
            $sender = trim((string) ($config['username'] ?? ''));
        }
        $this->senderId = $sender;
    }

    public function sendMessage($to, $message)
    {
        return $this->wrapSend($this->request('POST', '/sms/send', $this->sendBody($to, $message, 'plain')));
    }

    public function sendBulk($to, $message, $scheduleTime = null, $dltTemplateId = null)
    {
        return $this->wrapSend($this->request('POST', '/sms/send', $this->sendBody($to, $message, 'plain', $scheduleTime, $dltTemplateId)));
    }

    public function sendWhatsApp($to, $message, $media = null)
    {
        $body = $this->sendBody($to, $message, 'whatsapp');
        if (is_array($media) && !empty($media['url'])) {
            $body['media_url'] = (string) $media['url'];
        } elseif (is_string($media) && $media !== '') {
            $body['media_url'] = $media;
        }
        return $this->wrapSend($this->request('POST', '/sms/send', $body));
    }

    public function sendVoice($to, $message, $language = 'en-gb', $gender = 'female')
    {
        $body = $this->sendBody($to, $message, 'voice');
        $body['language'] = $language;
        $body['gender'] = $gender;
        return $this->wrapSend($this->request('POST', '/sms/send', $body));
    }

    public function sendMMS($to, $message, $mediaUrl)
    {
        $body = $this->sendBody($to, $message, 'mms');
        $body['media_url'] = $mediaUrl;
        return $this->wrapSend($this->request('POST', '/sms/send', $body));
    }

    public function sendHashed($recipient, $message, $recipientFormat = 'hashed')
    {
        return $this->wrapSend($this->request('POST', '/sms/send-hashed', [
            'recipient' => $recipient,
            'recipient_format' => $recipientFormat,
            'sender_id' => $this->senderId,
            'message' => $message,
        ]));
    }

    public function sendCampaign($contactListIds, $message, $scheduleTime = null, $dltTemplateId = null)
    {
        $body = [
            'recipient' => $contactListIds,
            'sender_id' => $this->senderId,
            'type' => 'plain',
            'message' => (string) $message,
        ];
        if ($scheduleTime !== null && $scheduleTime !== '') {
            $body['schedule_time'] = $scheduleTime;
        }
        if ($dltTemplateId !== null && $dltTemplateId !== '') {
            $body['dlt_template_id'] = $dltTemplateId;
        }
        return $this->wrapSend($this->request('POST', '/sms/campaign', $body));
    }

    public function queueStatus($queueUid)
    {
        return $this->request('GET', '/sms/queue/' . rawurlencode((string) $queueUid));
    }

    public function balance()
    {
        return $this->request('GET', '/balance');
    }

    public function profile()
    {
        return $this->request('GET', '/me');
    }

    public function listGroups()
    {
        return $this->request('GET', '/contacts');
    }

    public function createGroup($name)
    {
        return $this->request('POST', '/contacts', ['name' => $name]);
    }

    public function showGroup($groupId)
    {
        return $this->request('POST', '/contacts/' . rawurlencode((string) $groupId) . '/show');
    }

    public function updateGroup($groupId, $name)
    {
        return $this->request('PATCH', '/contacts/' . rawurlencode((string) $groupId), ['name' => $name]);
    }

    public function deleteGroup($groupId)
    {
        return $this->request('DELETE', '/contacts/' . rawurlencode((string) $groupId));
    }

    public function listContacts($groupId)
    {
        return $this->request('POST', '/contacts/' . rawurlencode((string) $groupId) . '/all');
    }

    public function storeContact($groupId, $phone, $firstName = '', $lastName = '')
    {
        $body = ['phone' => $phone];
        if ($firstName !== '') {
            $body['first_name'] = $firstName;
        }
        if ($lastName !== '') {
            $body['last_name'] = $lastName;
        }
        return $this->request('POST', '/contacts/' . rawurlencode((string) $groupId) . '/store', $body);
    }

    public function showContact($groupId, $uid)
    {
        return $this->request('POST', '/contacts/' . rawurlencode((string) $groupId) . '/search/' . rawurlencode((string) $uid));
    }

    public function updateContact($groupId, $uid, $phone, $firstName = '', $lastName = '')
    {
        $body = ['phone' => $phone];
        if ($firstName !== '') {
            $body['first_name'] = $firstName;
        }
        if ($lastName !== '') {
            $body['last_name'] = $lastName;
        }
        return $this->request('PATCH', '/contacts/' . rawurlencode((string) $groupId) . '/update/' . rawurlencode((string) $uid), $body);
    }

    public function deleteContact($groupId, $uid)
    {
        return $this->request('DELETE', '/contacts/' . rawurlencode((string) $groupId) . '/delete/' . rawurlencode((string) $uid));
    }

    public function listMessages()
    {
        return $this->request('GET', '/sms');
    }

    public function getMessage($uid)
    {
        return $this->request('GET', '/sms/' . rawurlencode((string) $uid));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function sendBody($to, $message, string $type, $scheduleTime = null, $dltTemplateId = null): array
    {
        $body = [
            'recipient' => $to,
            'sender_id' => $this->senderId,
            'type' => $type,
            'message' => (string) $message,
        ];
        if ($scheduleTime !== null && $scheduleTime !== '') {
            $body['schedule_time'] = $scheduleTime;
        }
        if ($dltTemplateId !== null && $dltTemplateId !== '') {
            $body['dlt_template_id'] = $dltTemplateId;
        }
        return $body;
    }

    /**
     * Convert a normalised transport response into the legacy send contract
     * (['status','data']) used across the communications stack, throwing on
     * failure so callers keep their existing error handling.
     */
    private function wrapSend(array $response): array
    {
        if (!($response['ok'] ?? false)) {
            throw new \Exception('Message send failed: ' . ($response['message'] ?? 'unknown provider error'));
        }
        return [
            'status' => $response['status'] !== '' ? $response['status'] : 'success',
            'data' => $response['data'],
        ];
    }

    /**
     * Single transport primitive for all provider calls.
     *
     * Everything executes through the Python platform relay first, with the
     * shared stream transport as the resilience path (never curl — this host
     * does not load the curl extension). See SmsProviderTransport.
     *
     * @return array{ok: bool, http: int, status: string, data: mixed, message: string, raw: string}
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        return $this->providerTransport($this->config, strtoupper($method), $path, $body);
    }
}
