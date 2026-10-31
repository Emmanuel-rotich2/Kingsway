<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Mailer - Laravel-style outbound channel facade (SMS / Email / WhatsApp).
 *
 *   Mailer::to(['254712345678'])->subject('Fee reminder')
 *       ->body('Please clear your balance.').channel('sms')->send();
 *
 *   Mailer::via('whatsapp')->to(...)->send();
 *
 * Channels:
 *   - 'sms' / 'whatsapp':  recipients are phone numbers; body is the message.
 *   - 'email':             recipients are ['email' => 'Name'] or a list of emails;
 *                          uses subject/body.
 *
 * Everything is delegated to App\API\Modules\Communications\CommunicationsManager
 * (the shared outbound pipeline), so this facade only reshapes the fluent API.
 * If the module is unavailable it logs via FileLogger and returns a "failed"
 * result rather than throwing, keeping controllers robust.
 *
 * Set the class name of the send manager to allow tests to substitute a fake:
 *   Mailer::useManager(FakeManager::class);
 */
class Mailer
{
    private const MANAGER_CLASS = 'App\API\Modules\Communications\CommunicationsManager';

    private static $managerClass = null;

    private $recipients = [];
    private $subject = '';
    private $body = '';
    private $channel = 'sms';
    private $attachments = [];
    private $variables = [];
    private $category = '';

    private function __construct()
    {
    }

    public static function to($recipients): self
    {
        $m = new self();
        $m->recipients = $recipients;
        return $m;
    }

    /**
     * Explicit channel ("sms"|"email"|"whatsapp"). Protected name to avoid
     * clashing with imperative calls.
     */
    public function via(string $channel): self
    {
        $this->channel = strtolower(trim($channel));
        return $this;
    }

    public function channel(string $channel): self
    {
        return $this->via($channel);
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function attachments(array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    public function variables(array $variables): self
    {
        $this->variables = $variables;
        return $this;
    }

    public function category(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Send the message through the selected channel.
     *
     * @return array ['status'=>'success'|'error'|'unavailable','sent_count'=>int,'failed'=>array]
     */
    public function send(): array
    {
        if ($this->channel === 'email') {
            return $this->sendEmail();
        }
        if (!in_array($this->channel, ['sms', 'whatsapp'], true)) {
            return ['status' => 'error', 'message' => 'Unsupported communication channel', 'sent_count' => 0, 'failed' => $this->recipients];
        }
        return $this->sendSms();
    }

    /**
     * Allow tests / alternate wiring to substitute the manager class.
     */
    public static function useManager(?string $class): void
    {
        self::$managerClass = $class;
    }

    private function sendEmail(): array
    {
        $manager = $this->manager();
        if ($manager === null) {
            $this->log('unavailable', ['channel' => 'email', 'msg' => 'CommunicationsManager unavailable']);
            return ['status' => 'unavailable', 'sent_count' => 0, 'failed' => $this->recipients];
        }
        try {
            $result = $manager->sendEmailToRecipients($this->recipients, $this->subject, $this->body, $this->attachments);
            return is_array($result) ? $result : ['status' => 'success', 'sent_count' => 0, 'failed' => []];
        } catch (\Throwable $e) {
            $this->log('error', ['channel' => 'email', 'message' => $e->getMessage()]);
            return ['status' => 'error', 'sent_count' => 0, 'failed' => $this->recipients];
        }
    }

    private function sendSms(): array
    {
        $manager = $this->manager();
        if ($manager === null) {
            $this->log('unavailable', ['channel' => $this->channel, 'msg' => 'CommunicationsManager unavailable']);
            return ['status' => 'unavailable', 'sent_count' => 0, 'failed' => $this->recipients];
        }
        try {
            $type = $this->channel === 'whatsapp' ? 'whatsapp' : 'sms';
            $result = $manager->sendSMSToRecipients($this->recipients, $this->body, $type, $this->category);
            return is_array($result) ? $result : ['status' => 'success', 'sent_count' => 0, 'failed' => []];
        } catch (\Throwable $e) {
            $this->log('error', ['channel' => $this->channel, 'message' => $e->getMessage()]);
            return ['status' => 'error', 'sent_count' => 0, 'failed' => $this->recipients];
        }
    }

    private function manager(): ?object
    {
        $class = self::$managerClass ?: self::MANAGER_CLASS;
        if (!class_exists($class)) {
            return null;
        }
        try {
            return new $class($this->pdo());
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function pdo(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    private function log(string $level, array $data): void
    {
        try {
            \App\API\Includes\FileLogger::write('mailer', array_merge(['sent_at' => date('Y-m-d H:i:s')], $data), $level);
        } catch (\Throwable $ignored) {
            \App\API\Services\Logger::legacyError('Mailer: ' . json_encode($data));
        }
    }
}
