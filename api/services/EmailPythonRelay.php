<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * Relay one already-rendered email to the Python platform's SMTP fallback.
 *
 * PHP's PHPMailer owns normal email delivery. When a message has exhausted
 * its PHP SMTP retries (or the PHP SMTP transport itself is unusable on this
 * shared host), the caller may hand the rendered message to the Python
 * platform, which delivers it with the standard library smtplib.
 *
 * Response contract: {ok, sent, failed, message, transport}. A null return
 * means the relay is not configured or could not be reached at the
 * connection level — the caller keeps its PHP retry loop as the final path.
 * A real HTTP reply (even a refusal) is authoritative.
 */
final class EmailPythonRelay
{
    private const ENDPOINT = '/api/email/send';
    private const TIMEOUT_SECONDS = 60;
    private const MAX_BODY_BYTES = 262144;

    private InterServiceClient $client;
    private string $secret;

    public function __construct(?InterServiceClient $client = null, ?string $secret = null)
    {
        $this->client = $client ?? new InterServiceClient();
        $this->secret = $secret ?? (string) Config::get(
            'AI_PYTHON_SECRET',
            (string) Config::get('AI_API_KEY', '')
        );
    }

    /** Whether the platform is configured well enough to attempt a relay. */
    public function available(): bool
    {
        try {
            return $this->secret !== '' && $this->client->isConfigured('python_ai');
        } catch (\Throwable $error) {
            return false;
        }
    }

    /**
     * Deliver one rendered message through the Python SMTP fallback.
     *
     * The envelope carries the effective SMTP transport settings (DB overlay
     * or environment constants) for this single call; nothing is persisted
     * on the Python side.
     *
     * @param array{host:string,port:int,security:string,username:string,password:string,from_email:string,from_name:string,subject:string,html:string,text?:string,reply_to?:string,to:array<int,array{email:string,name?:string}>,attachments?:array<int,array{path:string,file_name?:string,mime_type?:string}>} $envelope
     * @return array{ok:bool,sent:int,failed:int,message:string,transport:string}|null
     */
    public function send(array $envelope): ?array
    {
        if ($this->secret === '') {
            return null;
        }
        $encoded = json_encode($envelope, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BODY_BYTES) {
            return null;
        }

        try {
            [$raw, $status, $error] = $this->client->post('python_ai', self::ENDPOINT, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->secret,
            ], $encoded, self::TIMEOUT_SECONDS);
        } catch (\Throwable $error) {
            // Connection-level failure: the caller keeps its PHP path.
            return null;
        }
        if ($status <= 0) {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (is_array($data) && array_key_exists('ok', $data)) {
            return [
                'ok' => (bool) ($data['ok'] ?? false),
                'sent' => (int) ($data['sent'] ?? 0),
                'failed' => (int) ($data['failed'] ?? 0),
                'message' => (string) ($data['message'] ?? ''),
                'transport' => (string) ($data['transport'] ?? 'python-smtplib'),
            ];
        }

        // A real HTTP reply without a usable body is authoritative too.
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        if ($message === '') {
            $message = is_string($raw) && $raw !== ''
                ? mb_substr($raw, 0, 300)
                : 'Python SMTP fallback rejected the request.';
        }
        return [
            'ok' => false,
            'sent' => 0,
            'failed' => 0,
            'message' => $message,
            'transport' => 'python-smtplib',
        ];
    }
}
