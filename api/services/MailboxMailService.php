<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Sending side of the in-system mail client.
 *
 * A send is synchronous: the message goes out through the mailbox profile's
 * own SMTP credentials, a copy is appended to the account's Sent folder over
 * IMAP, and every attempt is recorded in comms_email_outbox so the Outbox
 * view and Retry survive independently of IMAP availability.
 */
final class MailboxMailService
{
    public const MAX_ATTACHMENTS = 10;

    /** 25MB total mirrors the UploadService communication_attachment policy. */
    public const MAX_ATTACHMENT_BYTES = 26214400;

    private \PDO $db;
    private EmailProfileService $profileService;
    private EmailInboxService $inboxService;
    private UploadService $uploadService;

    public function __construct(
        \PDO $db,
        EmailProfileService $profileService,
        EmailInboxService $inboxService,
        UploadService $uploadService
    ) {
        $this->db = $db;
        $this->profileService = $profileService;
        $this->inboxService = $inboxService;
        $this->uploadService = $uploadService;
    }

    /**
     * Send a message from a mailbox profile.
     *
     * @param array $credentials decrypted profile row (EmailProfileService::getCredentials)
     * @param array $input to, cc, bcc, subject, body_html, body_text, attachments, in_reply_to, references, draft_uid
     */
    public function send(array $credentials, int $userId, array $input): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profileId = (int) $credentials['id'];
        $to = $this->normalizeRecipients($input['to'] ?? [], 'to');
        $cc = $this->normalizeRecipients($input['cc'] ?? [], 'cc');
        $bcc = $this->normalizeRecipients($input['bcc'] ?? [], 'bcc');

        if ($to === [] && $cc === [] && $bcc === []) {
            throw new \InvalidArgumentException('At least one recipient is required.');
        }

        $subject = mb_substr(trim((string) ($input['subject'] ?? '')), 0, 400);
        $bodyHtml = (string) ($input['body_html'] ?? '');
        $bodyText = (string) ($input['body_text'] ?? '');
        if (trim($bodyHtml) === '' && trim($bodyText) === '') {
            throw new \InvalidArgumentException('The message body cannot be empty.');
        }

        $attachments = $this->normalizeAttachments($input['attachments'] ?? []);
        $outboxId = $this->createOutboxRow($credentials, $userId, $to, $cc, $bcc, $subject, $bodyHtml, $bodyText, $attachments);

        return $this->attempt($outboxId, $credentials, $userId, [
            'to'          => $to,
            'cc'          => $cc,
            'bcc'         => $bcc,
            'subject'     => $subject,
            'body_html'   => $bodyHtml,
            'body_text'   => $bodyText,
            'attachments' => $attachments,
            'in_reply_to' => (string) ($input['in_reply_to'] ?? ''),
            'references'  => (string) ($input['references'] ?? ''),
            'draft_uid'   => (string) ($input['draft_uid'] ?? ''),
        ]);
    }

    /**
     * Re-attempt a failed outbox message.
     */
    public function retry(int $outboxId, array $credentials, int $userId): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $row = $this->getOutboxRow($outboxId);
        if ($row === null) {
            throw new \RuntimeException('Outbox message not found.');
        }
        if ((int) $row['profile_id'] !== (int) $credentials['id']) {
            throw new \RuntimeException('Outbox message does not belong to this mailbox.');
        }
        if (!in_array((string) $row['status'], ['failed', 'queued'], true)) {
            throw new \RuntimeException('Only failed messages can be retried.');
        }

        return $this->attempt($outboxId, $credentials, $userId, [
            'to'          => $this->decodeList($row['to_json']),
            'cc'          => $this->decodeList($row['cc_json']),
            'bcc'         => $this->decodeList($row['bcc_json']),
            'subject'     => (string) $row['subject'],
            'body_html'   => (string) ($row['body_html'] ?? ''),
            'body_text'   => (string) ($row['body_text'] ?? ''),
            'attachments' => $this->normalizeAttachments(json_decode((string) ($row['attachments_json'] ?? '[]'), true) ?: []),
            'in_reply_to' => '',
            'references'  => '',
            'draft_uid'   => '',
        ]);
    }

    /**
     * Save a draft to the mailbox Drafts folder (IMAP APPEND). When replacing
     * an existing draft, the old UID is permanently deleted after the append
     * succeeds.
     */
    public function saveDraft(array $credentials, int $userId, array $input, ?string $replaceUid = null): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profileId = (int) $credentials['id'];
        $attachments = $this->normalizeAttachments($input['attachments'] ?? []);

        $mail = $this->buildMailer($credentials, [
            'to'          => $this->normalizeRecipients($input['to'] ?? [], 'to', false),
            'cc'          => $this->normalizeRecipients($input['cc'] ?? [], 'cc', false),
            'bcc'         => $this->normalizeRecipients($input['bcc'] ?? [], 'bcc', false),
            'subject'     => mb_substr(trim((string) ($input['subject'] ?? '')), 0, 400),
            'body_html'   => (string) ($input['body_html'] ?? ''),
            'body_text'   => (string) ($input['body_text'] ?? ''),
            'in_reply_to' => (string) ($input['in_reply_to'] ?? ''),
            'references'  => (string) ($input['references'] ?? ''),
        ], $attachments, true);

        $raw = $mail->getSentMIMEMessage();

        $draftsFolder = $this->inboxService->resolveSpecialFolder($profileId, 'drafts');
        if ($draftsFolder === null) {
            throw new \RuntimeException('This mailbox has no Drafts folder.');
        }

        $this->inboxService->appendMessage($profileId, $draftsFolder, $raw, ['\\Draft'], date('d-M-Y H:i:s O'));

        if ($replaceUid !== null && $replaceUid !== '') {
            try {
                $this->inboxService->deleteMessage($profileId, $draftsFolder, $replaceUid, true);
            } catch (\Throwable $e) {
                FileLogger::warning('communications', [
                    'event'     => 'mail_draft_replace_failed',
                    'user_id'   => $userId,
                    'profile'   => $profileId,
                    'draft_uid' => $replaceUid,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        FileLogger::write('communications', [
            'event'   => 'mail_draft_saved',
            'user_id' => $userId,
            'profile' => $profileId,
            'folder'  => $draftsFolder,
        ]);

        return ['success' => true, 'folder' => $draftsFolder, 'replaced_uid' => $replaceUid];
    }

    /**
     * Outbox rows for the given mailbox ids (the caller has already resolved
     * which mailboxes the user may see).
     */
    public function listOutbox(array $profileIds, array $filters = [], int $page = 1, int $limit = 25): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $profileIds = array_values(array_unique(array_map('intval', $profileIds)));

        if ($profileIds === []) {
            return ['messages' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        }

        $where = ['profile_id IN (' . implode(',', array_fill(0, count($profileIds), '?')) . ')'];
        $params = $profileIds;

        if (isset($filters['profile_id']) && (int) $filters['profile_id'] > 0) {
            $where[] = 'profile_id = ?';
            $params[] = (int) $filters['profile_id'];
        }
        if (isset($filters['status']) && in_array((string) $filters['status'], ['queued', 'sending', 'sent', 'failed'], true)) {
            $where[] = 'status = ?';
            $params[] = (string) $filters['status'];
        }
        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $where[] = '(subject LIKE ? OR from_email LIKE ?)';
            $needle = '%' . trim((string) $filters['q']) . '%';
            $params[] = $needle;
            $params[] = $needle;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM comms_email_outbox WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $limit;
        $stmt = $this->db->prepare(
            "SELECT id, profile_id, user_id, from_email, to_json, cc_json, subject, status, attempts,
                    smtp_message_id, sent_folder, error_message, created_at, sent_at, attachments_json
             FROM comms_email_outbox
             WHERE {$whereSql}
             ORDER BY id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = $this->hydrateOutboxRow($row);
        }

        return [
            'messages'   => $rows,
            'pagination' => [
                'total' => $total,
                'page'  => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public function getOutbox(int $outboxId, array $profileIds): ?array
    {
        $row = $this->getOutboxRow($outboxId);
        if ($row === null) {
            return null;
        }
        if ($profileIds !== [] && !in_array((int) $row['profile_id'], array_map('intval', $profileIds), true)) {
            return null;
        }

        return $this->hydrateOutboxRow($row);
    }

    /**
     * Profile that owns an outbox entry, or null when the entry no longer
     * exists. Used by callers to enforce per-mailbox capability checks before
     * mutating or removing a queued message.
     */
    public function outboxProfileId(int $outboxId): ?int
    {
        $row = $this->getOutboxRow($outboxId);

        return $row === null ? null : (int) $row['profile_id'];
    }

    public function deleteOutbox(int $outboxId, array $profileIds): bool
    {
        $row = $this->getOutboxRow($outboxId);
        if ($row === null) {
            return true;
        }
        if ($profileIds !== [] && !in_array((int) $row['profile_id'], array_map('intval', $profileIds), true)) {
            throw new \RuntimeException('Outbox message not found.');
        }
        if ((string) $row['status'] === 'sending') {
            throw new \RuntimeException('A message that is currently sending cannot be removed.');
        }

        $stmt = $this->db->prepare('DELETE FROM comms_email_outbox WHERE id = ?');
        $stmt->execute([$outboxId]);

        return true;
    }

    private function attempt(int $outboxId, array $credentials, int $userId, array $input): array
    {
        $profileId = (int) $credentials['id'];

        $this->updateOutbox($outboxId, [
            'status'        => 'sending',
            'attempts'      => $this->nextAttemptCount($outboxId),
            'error_message' => null,
        ]);

        try {
            $mail = $this->buildMailer($credentials, $input, $input['attachments']);
            $mail->send();
            $messageId = (string) $mail->getLastMessageID();

            $sentFolder = null;
            $appendWarning = null;
            try {
                $sentFolder = $this->inboxService->resolveSpecialFolder($profileId, 'sent');
                if ($sentFolder !== null) {
                    $this->inboxService->appendMessage($profileId, $sentFolder, $mail->getSentMIMEMessage(), ['\\Seen'], date('d-M-Y H:i:s O'));
                }
            } catch (\Throwable $e) {
                $appendWarning = 'The message was sent but the Sent-folder copy could not be stored: ' . $e->getMessage();
                FileLogger::warning('communications', [
                    'event'   => 'mail_sent_copy_failed',
                    'profile' => $profileId,
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
            }

            if (!empty($input['draft_uid'])) {
                try {
                    $draftsFolder = $this->inboxService->resolveSpecialFolder($profileId, 'drafts');
                    if ($draftsFolder !== null) {
                        $this->inboxService->deleteMessage($profileId, $draftsFolder, (string) $input['draft_uid'], true);
                    }
                } catch (\Throwable $ignored) {
                }
            }

            $this->updateOutbox($outboxId, [
                'status'         => 'sent',
                'smtp_message_id' => $messageId,
                'sent_folder'    => $sentFolder,
                'error_message'  => null,
                'sent_at'        => date('Y-m-d H:i:s'),
            ]);

            FileLogger::write('communications', [
                'event'         => 'mail_sent',
                'user_id'       => $userId,
                'profile'       => $profileId,
                'outbox_id'     => $outboxId,
                'to_count'      => count($input['to']),
                'sent_folder'   => $sentFolder,
            ]);

            $this->publishOutboxEvent($userId, $profileId, $outboxId, 'sent');

            return [
                'success'  => true,
                'outbox'   => $this->getOutboxRow($outboxId) !== null
                    ? $this->hydrateOutboxRow($this->getOutboxRow($outboxId))
                    : ['id' => $outboxId, 'status' => 'sent'],
                'warning'  => $appendWarning,
            ];
        } catch (\Throwable $e) {
            $this->updateOutbox($outboxId, [
                'status'        => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 1000),
            ]);

            FileLogger::warning('communications', [
                'event'     => 'mail_send_failed',
                'user_id'   => $userId,
                'profile'   => $profileId,
                'outbox_id' => $outboxId,
                'error'     => $e->getMessage(),
            ]);

            $this->publishOutboxEvent($userId, $profileId, $outboxId, 'failed');

            throw new \RuntimeException('The email could not be sent: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Fire-and-forget realtime nudge so the sender's other tabs (and the
     * header bell) reflect an outbox status change without a manual refresh.
     * Scoped to the acting user only: a mail send is private activity and must
     * never be broadcast to other users' connections.
     */
    private function publishOutboxEvent(int $userId, int $profileId, int $outboxId, string $status): void
    {
        try {
            RealtimeGatewayPublisher::publish('MAIL_OUTBOX_UPDATED', 'users:' . $userId, [
                'profile_id' => $profileId,
                'outbox_id'  => $outboxId,
                'status'     => $status,
                'targets'    => ['communications'],
            ]);
        } catch (\Throwable $ignored) {
            // Realtime is an optimisation; a failed publish never affects send.
        }
    }

    private function buildMailer(array $credentials, array $input, array $attachments, bool $forDraft = false): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $mail->isSMTP();
        $mail->Host = (string) $credentials['smtp_host'];
        $mail->Port = (int) $credentials['smtp_port'];
        $mail->SMTPAuth = (string) $credentials['smtp_username'] !== '';
        $mail->Username = (string) $credentials['smtp_username'];
        $mail->Password = (string) $credentials['smtp_password'];
        $mail->SMTPSecure = ((int) $credentials['smtp_port'] === 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Timeout = 45;

        $mail->setFrom((string) $credentials['email_address'], (string) $credentials['display_name']);
        $replyTo = trim((string) ($input['reply_to'] ?? ''));
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($replyTo);
        }

        foreach ($input['to'] as $address) {
            $mail->addAddress($address);
        }
        foreach ($input['cc'] as $address) {
            $mail->addCC($address);
        }
        foreach ($input['bcc'] as $address) {
            $mail->addBCC($address);
        }

        $mail->Subject = (string) $input['subject'];

        $bodyHtml = (string) $input['body_html'];
        $signature = trim((string) ($credentials['signature'] ?? ''));
        if (!$forDraft && $signature !== '' && stripos($bodyHtml, $signature) === false) {
            $bodyHtml .= '<br><br><div class="kingsway-signature">' . nl2br(htmlspecialchars($signature, ENT_QUOTES)) . '</div>';
        }

        $bodyText = (string) $input['body_text'];
        if ($bodyHtml !== '') {
            $mail->isHTML(true);
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyText !== '' ? $bodyText : trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES, 'UTF-8'));
        } else {
            $mail->isHTML(false);
            $mail->Body = $bodyText;
        }

        $inReplyTo = trim((string) ($input['in_reply_to'] ?? ''));
        if ($inReplyTo !== '') {
            $inReplyTo = '<' . trim($inReplyTo, '<>') . '>';
            $mail->addCustomHeader('In-Reply-To', $inReplyTo);
            $references = trim((string) ($input['references'] ?? ''));
            $mail->addCustomHeader('References', $references !== '' ? $references : $inReplyTo);
        }

        foreach ($attachments as $attachment) {
            $mail->addAttachment($attachment['absolute_path'], $attachment['name'], 'base64', $attachment['mime']);
        }

        return $mail;
    }

    private function normalizeRecipients(mixed $value, string $field, bool $required = true): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $items = is_array($value) ? $value : preg_split('/[,;]+/', (string) $value);
        $clean = [];
        foreach ($items ?: [] as $item) {
            $address = trim((string) $item);
            if ($address === '') {
                continue;
            }
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid ' . $field . ' address: ' . $address);
            }
            $clean[strtolower($address)] = $address;
        }

        return array_values($clean);
    }

    /**
     * @param array $value attachment descriptors {path|relative_path, name, mime}
     * @return array<int, array{path:string, absolute_path:string, name:string, mime:string, size:int}>
     */
    private function normalizeAttachments(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException('attachments must be an array.');
        }
        if (count($value) > self::MAX_ATTACHMENTS) {
            throw new \InvalidArgumentException('At most ' . self::MAX_ATTACHMENTS . ' attachments are allowed.');
        }

        $clean = [];
        $totalBytes = 0;
        foreach ($value as $descriptor) {
            if (!is_array($descriptor)) {
                throw new \InvalidArgumentException('Each attachment must be a {path, name, mime} object.');
            }

            $path = trim((string) ($descriptor['path'] ?? $descriptor['relative_path'] ?? ''));
            if ($path === '') {
                throw new \InvalidArgumentException('Attachment path is required.');
            }

            $absolute = $this->uploadService->absolutePath($path);
            $normalized = $this->uploadService->normaliseStoredReference($path);
            if (!str_starts_with($normalized, 'communications/')) {
                throw new \InvalidArgumentException('Attachments must be uploaded through the mail composer.');
            }
            if (!is_file($absolute)) {
                throw new \InvalidArgumentException('Attachment not found on disk: ' . ($descriptor['name'] ?? $path));
            }

            $size = (int) (filesize($absolute) ?: 0);
            $totalBytes += $size;
            if ($totalBytes > self::MAX_ATTACHMENT_BYTES) {
                throw new \InvalidArgumentException('Attachments exceed the 25MB total limit.');
            }

            $name = trim((string) ($descriptor['name'] ?? basename($absolute)));
            $mime = trim((string) ($descriptor['mime'] ?? ''));
            if ($mime === '') {
                $mime = function_exists('mime_content_type') ? (string) (mime_content_type($absolute) ?: 'application/octet-stream') : 'application/octet-stream';
            }

            $clean[] = [
                'path'          => $normalized,
                'absolute_path' => $absolute,
                'name'          => $name !== '' ? $name : basename($absolute),
                'mime'          => $mime,
                'size'          => $size,
            ];
        }

        return $clean;
    }

    private function createOutboxRow(
        array $credentials,
        int $userId,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        array $attachments
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO comms_email_outbox
             (profile_id, user_id, from_email, to_json, cc_json, bcc_json, subject, body_html, body_text, attachments_json, status, attempts, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'queued\', 0, NOW(), NOW())'
        );
        $stmt->execute([
            (int) $credentials['id'],
            $userId > 0 ? $userId : null,
            (string) $credentials['email_address'],
            json_encode($to, JSON_UNESCAPED_UNICODE),
            $cc === [] ? null : json_encode($cc, JSON_UNESCAPED_UNICODE),
            $bcc === [] ? null : json_encode($bcc, JSON_UNESCAPED_UNICODE),
            $subject === '' ? '(No Subject)' : $subject,
            $bodyHtml === '' ? null : $bodyHtml,
            $bodyText === '' ? null : $bodyText,
            $attachments === [] ? null : json_encode(array_map(static fn (array $a): array => [
                'path' => $a['path'], 'name' => $a['name'], 'mime' => $a['mime'], 'size' => $a['size'],
            ], $attachments), JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function updateOutbox(int $outboxId, array $values): void
    {
        $sets = [];
        $params = [];
        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $params[] = $value;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $outboxId;

        $stmt = $this->db->prepare('UPDATE comms_email_outbox SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    private function nextAttemptCount(int $outboxId): int
    {
        $stmt = $this->db->prepare('SELECT attempts FROM comms_email_outbox WHERE id = ?');
        $stmt->execute([$outboxId]);

        return ((int) $stmt->fetchColumn()) + 1;
    }

    private function getOutboxRow(int $outboxId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, profile_id, user_id, from_email, to_json, cc_json, bcc_json, subject, body_html, body_text,
                    attachments_json, status, attempts, smtp_message_id, sent_folder, error_message, created_at, sent_at
             FROM comms_email_outbox WHERE id = ?'
        );
        $stmt->execute([$outboxId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function hydrateOutboxRow(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'profile_id'  => (int) $row['profile_id'],
            'user_id'     => $row['user_id'] === null ? null : (int) $row['user_id'],
            'from_email'  => (string) $row['from_email'],
            'to'          => $this->decodeList($row['to_json'] ?? null),
            'cc'          => $this->decodeList($row['cc_json'] ?? null),
            'bcc'         => $this->decodeList($row['bcc_json'] ?? null),
            'subject'     => (string) $row['subject'],
            'attachments' => json_decode((string) ($row['attachments_json'] ?? '[]'), true) ?: [],
            'status'      => (string) $row['status'],
            'attempts'    => (int) $row['attempts'],
            'message_id'  => $row['smtp_message_id'] === null ? null : (string) $row['smtp_message_id'],
            'sent_folder' => $row['sent_folder'] === null ? null : (string) $row['sent_folder'],
            'error'       => $row['error_message'] === null ? null : (string) $row['error_message'],
            'created_at'  => (string) $row['created_at'],
            'sent_at'     => $row['sent_at'] === null ? null : (string) $row['sent_at'],
        ];
    }

    private function decodeList(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
