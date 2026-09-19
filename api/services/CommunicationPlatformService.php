<?php

namespace App\API\Services;

// The repository directory is lowercase `communications`; keep the PSR-4
// namespace reference filesystem-safe on Linux.
use App\API\Modules\communications\templates\TemplateLoader;
use PDO;

/**
 * Canonical business-to-channel communication composer.
 *
 * It creates the outbox, logical recipient and endpoint rows together. The
 * endpoint is the only place where a channel address is stored; this keeps
 * recipient identity, channel identity and provider delivery state separate.
 */
class CommunicationPlatformService
{
    private PDO $db;
    private TemplateLoader $legacyTemplates;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->legacyTemplates = ServiceContractBroker::contract('App\API\Modules\communications\templates\TemplateLoader');
    }

    public function queueForStudentParents(
        int $studentId,
        string $channel,
        string $templateCode,
        array $variables,
        array $options = []
    ): array {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT pr.id AS parent_id, u.id AS user_id, p.email, p.phone
               FROM student_parents sp
               JOIN parents pr ON pr.id = sp.parent_id AND pr.status = 'active'
               JOIN users u ON u.person_id = pr.person_id AND u.status = 'active'
               JOIN persons p ON p.id = u.person_id
              WHERE sp.student_id = ?"
        );
        $stmt->execute([$studentId]);
        return $this->queueForContacts($stmt->fetchAll(PDO::FETCH_ASSOC), $channel, $templateCode, $variables, $options);
    }

    public function queueForParent(
        int $parentId,
        string $channel,
        string $templateCode,
        array $variables,
        array $options = []
    ): array {
        $stmt = $this->db->prepare(
            "SELECT u.id AS user_id, p.email, p.phone
               FROM parents pr
               JOIN users u ON u.person_id = pr.person_id AND u.status = 'active'
               JOIN persons p ON p.id = u.person_id
              WHERE pr.id = ? AND pr.status = 'active'"
        );
        $stmt->execute([$parentId]);
        return $this->queueForContacts($stmt->fetchAll(PDO::FETCH_ASSOC), $channel, $templateCode, $variables, $options);
    }

    public function queueForContacts(
        array $contacts,
        string $channel,
        string $templateCode,
        array $variables,
        array $options = []
    ): array {
        $channel = strtolower(trim($channel));
        if (!in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            throw new \InvalidArgumentException('Unsupported communication channel');
        }

        $template = $this->resolveTemplate($templateCode, $channel, $variables);
        return $this->queueResolvedContacts($contacts, $channel, $template, $options);
    }

    public function queueRenderedForContacts(
        array $contacts,
        string $channel,
        string $subject,
        string $body,
        array $options = []
    ): array {
        return $this->queueResolvedContacts($contacts, strtolower(trim($channel)), [
            'template_channel_id' => null,
            'subject' => $subject,
            'body' => $body,
        ], $options);
    }

    public function queueRenderedForStudentParents(
        int $studentId,
        string $channel,
        string $subject,
        string $body,
        array $options = []
    ): array {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT pr.id AS parent_id, u.id AS user_id, p.email, p.phone
               FROM student_parents sp
               JOIN parents pr ON pr.id = sp.parent_id AND pr.status = 'active'
               LEFT JOIN users u ON u.person_id = pr.person_id AND u.status = 'active'
               JOIN persons p ON p.id = pr.person_id
              WHERE sp.student_id = ?"
        );
        $stmt->execute([$studentId]);
        return $this->queueRenderedForContacts(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $channel,
            $subject,
            $body,
            $options
        );
    }

    public function queueProviderTemplateForContacts(array $contacts, string $providerTemplateId, array $variables, array $options = []): array
    {
        $code = 'whatsapp.provider.' . sha1($providerTemplateId);
        $this->db->prepare("INSERT INTO communication_template_catalog (code, name, purpose, status) VALUES (?, ?, 'provider_template', 'active') ON DUPLICATE KEY UPDATE status = 'active'")
            ->execute([$code, 'WhatsApp provider template ' . $providerTemplateId]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_catalog WHERE code = ?");
        $stmt->execute([$code]);
        $catalogId = (int) $stmt->fetchColumn();
        $this->db->prepare("INSERT INTO communication_template_versions (template_id, version_no, status) VALUES (?, 1, 'active') ON DUPLICATE KEY UPDATE status = 'active'")->execute([$catalogId]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_versions WHERE template_id = ? AND version_no = 1");
        $stmt->execute([$catalogId]);
        $versionId = (int) $stmt->fetchColumn();
        $this->db->prepare("INSERT INTO communication_template_channels (template_version_id, channel, body, provider_name, provider_template_id) VALUES (?, 'whatsapp', '', 'africastalking', ?) ON DUPLICATE KEY UPDATE provider_template_id = VALUES(provider_template_id)")->execute([$versionId, $providerTemplateId]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_channels WHERE template_version_id = ? AND channel = 'whatsapp'");
        $stmt->execute([$versionId]);
        $channelId = (int) $stmt->fetchColumn();
        $queued = $this->queueResolvedContacts($contacts, 'whatsapp', ['template_channel_id' => $channelId, 'subject' => 'WhatsApp template', 'body' => ''], $options);
        if (!empty($queued['communication_id'])) {
            $valueStmt = $this->db->prepare("INSERT INTO communication_template_values (communication_id, ordinal_no, variable_name, variable_value) VALUES (?, ?, ?, ?)");
            $ordinal = 0;
            if (isset($variables['bodyValues']) || isset($variables['body_values'])) {
                $bodyValues = $variables['bodyValues'] ?? $variables['body_values'];
                foreach ((array) $bodyValues as $value) {
                    $valueStmt->execute([(int) $queued['communication_id'], $ordinal++, null, (string) $value]);
                }
                if (isset($variables['headerValue']) || isset($variables['header_value'])) {
                    $valueStmt->execute([(int) $queued['communication_id'], $ordinal, 'headerValue', (string) ($variables['headerValue'] ?? $variables['header_value'])]);
                }
            } else foreach ($variables as $name => $value) {
                $valueStmt->execute([(int) $queued['communication_id'], $ordinal++, is_string($name) ? $name : null, (string) $value]);
            }
        }
        return $queued;
    }

    private function queueResolvedContacts(array $contacts, string $channel, array $template, array $options): array
    {
        $scheduledAt = $options['scheduled_at'] ?? null;
        $status = $scheduledAt ? 'scheduled' : 'queued';
        $communicationId = $this->insertCommunication($channel, $template, $status, $scheduledAt, $options);
        $this->attachFiles($communicationId, $channel, (array) ($options['attachments'] ?? []));
        $created = 0;
        $recipientOutcomes = [];

        foreach ($contacts as $contact) {
            $userId = !empty($contact['user_id']) ? (int) $contact['user_id'] : null;
            $address = $channel === 'email' ? trim((string) ($contact['email'] ?? '')) : trim((string) ($contact['phone'] ?? ''));
            if ($address === '' || !$this->isAllowed($userId, $channel, (string) ($options['purpose'] ?? 'transactional'))) {
                $reason = $address === '' ? 'missing_address' : 'consent_or_preference';
                $this->audit($communicationId, null, 'recipient_skipped', $template, ['user_id' => $userId, 'reason' => $reason]);
                $recipientOutcomes[] = ['parent_id' => $contact['parent_id'] ?? null, 'user_id' => $userId, 'status' => 'skipped', 'reason' => $reason];
                continue;
            }

            $recipient = $this->db->prepare("INSERT INTO communication_recipients (communication_id, recipient_id, status) VALUES (?, ?, 'queued')");
            $recipient->execute([$communicationId, $userId]);
            $recipientId = (int) $this->db->lastInsertId();
            $endpoint = $this->db->prepare(
                "INSERT INTO communication_recipient_endpoints (communication_recipient_id, channel, address, status) VALUES (?, ?, ?, 'pending')"
            );
            $endpoint->execute([$recipientId, $channel, $address]);
            $endpointId = (int) $this->db->lastInsertId();
            $this->audit($communicationId, $endpointId, 'queued', $template, ['user_id' => $userId, 'address' => $address]);
            $recipientOutcomes[] = ['parent_id' => $contact['parent_id'] ?? null, 'user_id' => $userId, 'status' => 'queued', 'reason' => null];
            $created++;
        }

        if ($created === 0) {
            $this->db->prepare("UPDATE communications SET status = 'failed', processed_at = NOW(), last_error = ? WHERE id = ?")
                ->execute(['No eligible recipient endpoint was found.', $communicationId]);
        }

        return [
            'communication_id' => $communicationId,
            'recipient_count' => $created,
            'status' => $created ? $status : 'failed',
            'recipients' => $recipientOutcomes,
        ];
    }

    private function resolveTemplate(string $code, string $channel, array $variables): array
    {
        $stmt = $this->db->prepare(
            "SELECT tc.id AS template_channel_id, tc.subject, tc.body
               FROM communication_template_catalog c
               JOIN communication_template_versions tv ON tv.template_id = c.id AND tv.status = 'active'
               JOIN communication_template_channels tc ON tc.template_version_id = tv.id AND tc.channel = ?
              WHERE c.code = ? AND c.status = 'active'
              ORDER BY tv.version_no DESC LIMIT 1"
        );
        $stmt->execute([$channel, $channel . '.' . $code]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) {
            $stmt->execute([$channel, $code]);
        }
        if (!$template) {
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$template) {
            $legacy = $this->legacyTemplates->getTemplate($channel, $code);
            if ($legacy) {
                $template = [
                    'template_channel_id' => null,
                    'subject' => $legacy['subject'] ?? null,
                    'body' => $legacy['template_body'] ?? '',
                ];
            }
        }
        if (!$template) {
            throw new \InvalidArgumentException("Communication template not found: {$code}/{$channel}");
        }
        foreach ($variables as $key => $value) {
            $template['body'] = str_replace('{{' . $key . '}}', (string) $value, $template['body']);
            if ($template['subject'] !== null) {
                $template['subject'] = str_replace('{{' . $key . '}}', (string) $value, $template['subject']);
            }
        }
        return $template;
    }

    private function insertCommunication(string $channel, array $template, string $status, ?string $scheduledAt, array $options): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO communications
                (sender_id, subject, body, type, status, priority, template_id, template_channel_id,
                 academic_year_id, academic_year_term_id, scheduled_at, reminder_at,
                 sender_signature, thread_id, business_event_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $options['sender_id'] ?? 1,
            $options['subject'] ?? ($template['subject'] ?? 'Kingsway Preparatory School'),
            $template['body'],
            $channel,
            $status,
            $options['priority'] ?? 'medium',
            $options['legacy_template_id'] ?? null,
            $template['template_channel_id'] ?? null,
            $options['academic_year_id'] ?? null,
            $options['academic_year_term_id'] ?? null,
            $scheduledAt,
            $options['reminder_at'] ?? null,
            $options['sender_signature'] ?? null,
            $options['thread_id'] ?? null,
            $options['business_event_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function attachFiles(int $communicationId, string $channel, array $attachments): void
    {
        if (!in_array($channel, ['email', 'whatsapp'], true)) return;
        $insert = $this->db->prepare(
            'INSERT INTO communication_attachments
                (communication_id, file_name, file_path, mime_type, file_size, public_url)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $link = $this->db->prepare(
            "INSERT INTO communication_attachment_channels (attachment_id, channel, status)
             VALUES (?, ?, 'ready')"
        );
        foreach ($attachments as $attachment) {
            $path = (string) ($attachment['file_path'] ?? '');
            if ($path === '' || !is_file($path)) {
                throw new \InvalidArgumentException('A communication attachment file is missing');
            }
            $publicUrl = $attachment['public_url'] ?? null;
            if ($channel === 'whatsapp' && (!$publicUrl || strpos((string) $publicUrl, 'https://') !== 0)) {
                throw new \InvalidArgumentException('WhatsApp report attachments require a public HTTPS URL');
            }
            $insert->execute([
                $communicationId,
                $attachment['file_name'] ?? basename($path),
                $path,
                $attachment['mime_type'] ?? 'application/pdf',
                filesize($path) ?: null,
                $publicUrl,
            ]);
            $link->execute([(int) $this->db->lastInsertId(), $channel]);
        }
    }

    private function isAllowed(?int $userId, string $channel, string $purpose): bool
    {
        if (!$userId) return true;
        $stmt = $this->db->prepare(
            "SELECT decision FROM communication_consents
              WHERE user_id = ? AND channel = ? AND purpose = ?
              ORDER BY captured_at DESC, id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $channel, $purpose]);
        $consent = $stmt->fetchColumn();
        if (in_array($consent, ['denied', 'withdrawn'], true)) return false;

        $preference = $this->db->prepare(
            "SELECT is_enabled FROM communication_preferences
              WHERE user_id = ? AND channel = ? AND purpose = ? LIMIT 1"
        );
        $preference->execute([$userId, $channel, $purpose]);
        $enabled = $preference->fetchColumn();
        return $enabled === false || $enabled === null || (int) $enabled === 1;
    }

    private function audit(int $communicationId, ?int $endpointId, string $eventType, array $template, array $payload): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO communication_audit_events
                (communication_id, endpoint_id, template_channel_id, event_type, rendered_subject, rendered_body, raw_payload)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $communicationId,
            $endpointId,
            $template['template_channel_id'] ?? null,
            $eventType,
            $template['subject'] ?? null,
            $template['body'] ?? null,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
