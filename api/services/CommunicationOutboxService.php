<?php

namespace App\API\Services;

use PDO;
use Throwable;
use App\API\Includes\FileLogger;
use App\API\Services\sms\SMSGateway;
use App\API\Services\whatsapp\WhatsAppGateway;

/** Durable dispatcher for communications and per-recipient delivery state. */
class CommunicationOutboxService
{
    private PDO $db;
    private string $workerId;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->workerId = 'php-' . getmypid();
    }

    public function processPending(int $limit = 25): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM communications
              WHERE (status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()))
                 OR (status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW())
              ORDER BY COALESCE(scheduled_at, created_at), id LIMIT ?"
        );
        $stmt->bindValue(1, max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $outcome = $this->processOne((int) $id);
            $result['processed']++;
            $result[$outcome] = ($result[$outcome] ?? 0) + 1;
        }
        return $result;
    }

    public function processOne(int $communicationId): string
    {
        $claim = $this->db->prepare(
            "UPDATE communications SET status = 'processing', locked_at = NOW(), locked_by = ?, attempt_count = attempt_count + 1
              WHERE id = ? AND ((status = 'queued' AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())) OR (status = 'scheduled' AND scheduled_at <= NOW()))"
        );
        $claim->execute([$this->workerId, $communicationId]);
        if ($claim->rowCount() !== 1) return 'skipped';

        $commStmt = $this->db->prepare("SELECT * FROM communications WHERE id = ?");
        $commStmt->execute([$communicationId]);
        $communication = $commStmt->fetch(PDO::FETCH_ASSOC);
        if (!$communication) return 'skipped';

        $endpointStmt = $this->db->prepare(
            "SELECT e.*, r.id AS recipient_row_id, r.recipient_id
               FROM " . ReadReplicaService::qualifiedRef("communication_recipient_endpoints") . "
               JOIN communication_recipients r ON r.id = e.communication_recipient_id
              WHERE r.communication_id = ? AND e.status IN ('pending','retry')
                AND (e.next_attempt_at IS NULL OR e.next_attempt_at <= NOW())
              ORDER BY e.id"
        );
        $endpointStmt->execute([$communicationId]);
        $endpoints = $endpointStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$endpoints) {
            $this->db->prepare("UPDATE communications SET status = 'failed', processed_at = NOW(), locked_at = NULL, locked_by = NULL, last_error = ? WHERE id = ?")
                ->execute(['No communication recipients are configured.', $communicationId]);
            return 'failed';
        }
        $hadFailure = false;
        foreach ($endpoints as $endpoint) {
            $this->markEndpointProcessing((int) $endpoint['id']);
            try {
                $response = $this->deliver($communication, $endpoint);
                $this->recordAttempt($endpoint, 'accepted', $response, null);
                $this->markEndpointSuccess($endpoint, $response);
            } catch (Throwable $e) {
                $hadFailure = true;
                $this->recordAttempt($endpoint, 'error', null, $e->getMessage());
                $this->markEndpointFailure($endpoint, $e->getMessage());
            }
        }

        $remaining = $this->db->prepare("SELECT COUNT(*) FROM communication_recipient_endpoints WHERE communication_recipient_id IN (SELECT id FROM communication_recipients WHERE communication_id = ?) AND status IN ('pending','processing','retry')");
        $remaining->execute([$communicationId]);
        $failed = $this->db->prepare("SELECT COUNT(*) FROM communication_recipient_endpoints WHERE communication_recipient_id IN (SELECT id FROM communication_recipients WHERE communication_id = ?) AND status = 'failed'");
        $failed->execute([$communicationId]);
        $remainingCount = (int) $remaining->fetchColumn();
        $failedCount = (int) $failed->fetchColumn();

        if ($remainingCount > 0) {
            $this->db->prepare("UPDATE communications SET status = 'queued', next_attempt_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE), locked_at = NULL, locked_by = NULL, last_error = ? WHERE id = ?")
                ->execute([$hadFailure ? 'One or more recipients failed; retry scheduled.' : null, $communicationId]);
            return 'failed';
        }
        $status = $failedCount > 0 ? 'failed' : 'sent';
        $this->db->prepare("UPDATE communications SET status = ?, processed_at = NOW(), locked_at = NULL, locked_by = NULL, next_attempt_at = NULL WHERE id = ?")
            ->execute([$status, $communicationId]);
        return $failedCount > 0 ? 'failed' : 'sent';
    }

    private function deliver(array $communication, array $endpoint)
    {
        $type = $communication['type'];
        $body = $this->renderBody((string) ($communication['body'] ?? ''), $endpoint);
        // Apply DB-stored provider configuration (System Admin pages) as an
        // overlay over the environment-config defaults.
        $commsConfig = new SystemCommsConfigService($this->db);
        $whatsappGateway = new WhatsAppGateway($commsConfig->effectiveWhatsAppConfig());
        $smsGateway = new SMSGateway($commsConfig->effectiveSmsConfig());
        if ($type === 'email') {
            $service = new MessageService($this->db);
            $service->applySmtpOverlay($commsConfig->effectiveSmtp());
            $body = $service->renderEmail($communication['subject'], $body, $communication['sender_signature'] ?? '', '');
            $attachmentStmt = $this->db->prepare("SELECT file_path FROM communication_attachments WHERE communication_id = ? AND file_path IS NOT NULL");
            $attachmentStmt->execute([(int) $communication['id']]);
            $attachments = array_values(array_filter($attachmentStmt->fetchAll(PDO::FETCH_COLUMN)));
            $attempts = ((int) ($endpoint['attempt_count'] ?? 0)) + 1;
            if (!$service->sendEmail([$endpoint['address'] => ''], $communication['subject'], $body, $attachments, (bool) ($communication['audit_bcc'] ?? 0))) {
                // After the PHP SMTP attempts are exhausted, one Python
                // smtplib fallback is attempted before the endpoint is
                // marked permanently failed by markEndpointFailure().
                if ($attempts >= MessageService::SMTP_MAX_ATTEMPTS) {
                    $fallback = $service->sendViaPythonFallback([$endpoint['address'] => ''], (string) $communication['subject'], $body, $attachments);
                    if ($fallback !== null && !empty($fallback['ok'])) {
                        FileLogger::write('communications', [
                            'type' => 'outbox_email_python_fallback',
                            'communication_id' => (int) $communication['id'],
                            'endpoint_id' => (int) $endpoint['id'],
                            'attempts' => $attempts,
                            'transport' => $fallback['transport'],
                        ]);
                        return ['status' => 'sent', 'transport' => $fallback['transport']];
                    }
                    if ($fallback !== null) {
                        FileLogger::write('communications', [
                            'type' => 'outbox_email_python_fallback_failed',
                            'communication_id' => (int) $communication['id'],
                            'endpoint_id' => (int) $endpoint['id'],
                            'attempts' => $attempts,
                            'error' => $fallback['message'],
                        ], 'error');
                        throw new \RuntimeException('Email provider rejected the message | Python fallback: ' . $fallback['message']);
                    }
                }
                throw new \RuntimeException('Email provider rejected the message');
            }
            return ['status' => 'sent'];
        }
        if ($type === 'whatsapp' && !empty($communication['template_channel_id'])) {
            $templateStmt = $this->db->prepare("SELECT provider_template_id FROM communication_template_channels WHERE id = ? LIMIT 1");
            $templateStmt->execute([(int) $communication['template_channel_id']]);
            $providerTemplateId = $templateStmt->fetchColumn();
            if ($providerTemplateId) {
                $valuesStmt = $this->db->prepare("SELECT variable_name, variable_value FROM communication_template_values WHERE communication_id = ? ORDER BY ordinal_no");
                $valuesStmt->execute([(int) $communication['id']]);
                $values = [];
                $headerValue = null;
                foreach ($valuesStmt->fetchAll(PDO::FETCH_ASSOC) as $valueRow) {
                    if (in_array(strtolower((string) ($valueRow['variable_name'] ?? '')), ['headervalue', 'header_value'], true)) {
                        $headerValue = (string) $valueRow['variable_value'];
                    } else {
                        $values[] = (string) $valueRow['variable_value'];
                    }
                }
                $templateVariables = ['bodyValues' => $values];
                if ($headerValue !== null) $templateVariables['headerValue'] = $headerValue;
                $response = $whatsappGateway->sendTemplate($endpoint['address'], (string) $providerTemplateId, $templateVariables);
                if (!$this->successful($response)) throw new \RuntimeException('WhatsApp provider template rejected the message');
                return $response;
            }
        }
        if ($type === 'whatsapp') {
            $mediaStmt = $this->db->prepare(
                "SELECT a.public_url, a.file_path, a.mime_type
                   FROM " . ReadReplicaService::qualifiedRef("communication_attachments") . "
                   JOIN communication_attachment_channels ac ON ac.attachment_id = a.id
                  WHERE a.communication_id = ? AND ac.channel = 'whatsapp' AND ac.status IN ('ready','pending')
                  ORDER BY a.id"
            );
            $mediaStmt->execute([(int) $communication['id']]);
            $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
            if ($media && !empty($media['public_url'])) {
                $mime = strtolower((string) ($media['mime_type'] ?? ''));
                $mediaType = strpos($mime, 'image/') === 0 ? 'image' : (strpos($mime, 'video/') === 0 ? 'video' : (strpos($mime, 'audio/') === 0 ? 'audio' : 'document'));
                $response = $whatsappGateway->sendMedia($endpoint['address'], $body, $mediaType, $media['public_url']);
            } else {
                $response = $whatsappGateway->sendMessage($endpoint['address'], $body);
            }
        } else {
            $response = $smsGateway->send($endpoint['address'], $body);
        }
        if (!$this->successful($response)) throw new \RuntimeException('Message provider rejected the message');
        return $response;
    }

    /**
     * Resolve @variable tokens for the recipient behind this endpoint. Falls
     * back to school-wide tokens when no person record can be matched.
     */
    private function renderBody(string $body, array $endpoint): string
    {
        return (new CommunicationVariableService())->render($body, $this->recipientContext($endpoint));
    }

    /** @return array<string,string> */
    private function recipientContext(array $endpoint): array
    {
        $context = [
            'school_name' => defined('SCHOOL_NAME') ? (string) SCHOOL_NAME : 'Kingsway Preparatory School',
            'today' => date('d M Y'),
            'year' => date('Y'),
        ];

        $person = $this->lookupRecipientPerson((int) ($endpoint['recipient_id'] ?? 0), (string) ($endpoint['address'] ?? ''));
        if ($person) {
            $context['first_name'] = $person['first_name'] ?? '';
            $context['last_name'] = $person['last_name'] ?? '';
            $context['full_name'] = $person['full_name'] ?? trim(($person['first_name'] ?? '') . ' ' . ($person['last_name'] ?? ''));
            $context['email'] = $person['email'] ?? '';
            $context['phone'] = $person['phone'] ?? '';
        }

        return $context;
    }

    private function lookupRecipientPerson(int $userId, string $address): ?array
    {
        if ($userId > 0) {
            try {
                $stmt = $this->db->prepare(
                    "SELECT first_name, last_name, full_name, email, phone
                       FROM " . ReadReplicaService::qualifiedRef("person_directory") . "
                      WHERE user_id = ? LIMIT 1"
                );
                $stmt->execute([$userId]);
                $person = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($person) {
                    return $person;
                }
            } catch (Throwable $e) {
                // Fall through to contact-directory lookup.
            }
        }

        if ($address !== '') {
            try {
                $stmt = $this->db->prepare("SELECT name, email, phone FROM contact_directory WHERE (phone = ? OR email = ?) LIMIT 1");
                $stmt->execute([$address, $address]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($contact) {
                    $parts = preg_split('/\s+/', trim((string) ($contact['name'] ?? '')), 2);
                    return [
                        'first_name' => $parts[0] ?? '',
                        'last_name' => $parts[1] ?? '',
                        'full_name' => $contact['name'] ?? '',
                        'email' => $contact['email'] ?? '',
                        'phone' => $contact['phone'] ?? '',
                    ];
                }
            } catch (Throwable $e) {
                // No contact record; recipient-level variables stay unresolved.
            }
        }

        return null;
    }

    private function successful($response): bool
    {
        if ($response === true) return true;
        if (!is_array($response)) return false;
        return in_array(strtolower((string) ($response['status'] ?? '')), ['success','sent','queued','accepted'], true);
    }

    private function providerId($response): ?string
    {
        if (!is_array($response)) return null;
        foreach (['messageId', 'message_id', 'provider_message_id', 'id'] as $key) if (!empty($response[$key])) return (string) $response[$key];
        if (isset($response['data']) && is_array($response['data'])) return $this->providerId($response['data']);
        return null;
    }

    private function recordAttempt(array $endpoint, string $status, $response, ?string $error): void
    {
        $attemptNo = ((int) ($endpoint['attempt_count'] ?? 0)) + 1;
        $stmt = $this->db->prepare(
            "INSERT INTO communication_delivery_attempts
                (endpoint_id, attempt_no, request_status, provider_message_id, provider_status, error_message, request_started_at, request_finished_at, raw_response)
             VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL 1 SECOND), NOW(), ?)"
        );
        $stmt->execute([
            (int) $endpoint['id'],
            $attemptNo,
            $status,
            $this->providerId($response),
            is_array($response) ? ($response['status'] ?? null) : ($status === 'accepted' ? 'accepted' : 'error'),
            $error,
            is_string($response) ? $response : json_encode($response, JSON_UNESCAPED_SLASHES),
        ]);
        $audit = $this->db->prepare(
            "INSERT INTO communication_audit_events (communication_id, endpoint_id, event_type, provider_message_id, raw_payload)
             SELECT communication_id, ?, ?, ?, ? FROM communication_recipients WHERE id = ?"
        );
        $audit->execute([(int) $endpoint['id'], 'delivery_attempt_' . $status, $this->providerId($response), is_string($response) ? $response : json_encode($response), (int) $endpoint['recipient_row_id']]);
    }

    private function markEndpointProcessing(int $id): void
    {
        $this->db->prepare("UPDATE communication_recipient_endpoints SET status = 'processing', attempt_count = attempt_count + 1, last_attempt_at = NOW() WHERE id = ?")->execute([$id]);
    }

    private function markEndpointSuccess(array $endpoint, $response): void
    {
        $providerId = $this->providerId($response);
        $this->db->prepare("UPDATE communication_recipient_endpoints SET status = 'sent', provider_message_id = ?, provider_status = ?, delivered_at = NULL, last_error = NULL WHERE id = ?")
            ->execute([$providerId, is_array($response) ? ($response['status'] ?? null) : 'sent', $endpoint['id']]);
        $this->db->prepare("UPDATE communication_recipients SET status = 'sent', delivered_at = NULL, error_message = NULL WHERE id = ?")->execute([$endpoint['recipient_row_id']]);
    }

    private function markEndpointFailure(array $endpoint, string $error): void
    {
        $attempts = ((int) $endpoint['attempt_count']) + 1;
        $status = $attempts < 3 ? 'retry' : 'failed';
        $next = $status === 'retry' ? date('Y-m-d H:i:s', time() + 300) : null;
        $this->db->prepare("UPDATE communication_recipient_endpoints SET status = ?, next_attempt_at = ?, last_error = ?, provider_status = 'failed' WHERE id = ?")
            ->execute([$status, $next, substr($error, 0, 1000), $endpoint['id']]);
        $this->db->prepare("UPDATE communication_recipients SET status = ?, error_message = ? WHERE id = ?")->execute([$status === 'retry' ? 'retry' : 'failed', substr($error, 0, 1000), $endpoint['recipient_row_id']]);
    }
}
