<?php
namespace App\API\Modules\communications;

use App\API\Core\FileLifecycleBase;
use PDO;

class CommunicationsManager extends FileLifecycleBase
{
    /**
     * Update delivery status for a recipient (used for SMS/MMS/WhatsApp/Email delivery callbacks)
     * @param int $recipientId
     * @param string $status (delivered, failed, pending, etc)
     * @param string|null $deliveredAt (optional, timestamp)
     * @param string|null $errorMessage (optional)
     * @return bool
     */
    public function updateDeliveryStatus($recipientId, $status, $deliveredAt = null, $errorMessage = null)
    {
        $fields = ["status = :status"];
        $params = [":status" => $status, ":id" => $recipientId];
        if ($deliveredAt) {
            $fields[] = "delivered_at = :delivered_at";
            $params[":delivered_at"] = $deliveredAt;
        }
        if ($errorMessage) {
            $fields[] = "error_message = :error_message";
            $params[":error_message"] = $errorMessage;
        }
        $fields[] = "last_attempt_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE communication_recipients SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function updateDeliveryStatusByProvider($providerMessageId, $status, $deliveredAt = null, $errorMessage = null)
    {
        $normalized = strtolower((string) $status);
        $recipientStatus = in_array($normalized, ['delivered', 'success'], true) ? 'delivered' : ($normalized === 'failed' ? 'failed' : 'sent');
        $endpoint = $this->db->prepare("UPDATE communication_recipient_endpoints SET status = ?, provider_status = ?, delivered_at = ?, last_error = ? WHERE provider_message_id = ?");
        $endpoint->execute([$recipientStatus, $normalized, $deliveredAt, $errorMessage, (string) $providerMessageId]);
        if ($endpoint->rowCount() < 1) return false;
        $stmt = $this->db->prepare("UPDATE communication_recipients r JOIN communication_recipient_endpoints e ON e.communication_recipient_id = r.id SET r.status = ?, r.delivered_at = ?, r.error_message = ?, r.last_attempt_at = CURRENT_TIMESTAMP WHERE e.provider_message_id = ?");
        $result = $stmt->execute([$recipientStatus, $deliveredAt, $errorMessage, (string) $providerMessageId]);
        $aggregate = $this->db->prepare(
            "UPDATE communications c
                JOIN communication_recipients r ON r.communication_id = c.id
                JOIN communication_recipient_endpoints e ON e.communication_recipient_id = r.id
               SET c.status = CASE
                    WHEN EXISTS (SELECT 1 FROM communication_recipient_endpoints ef JOIN communication_recipients rf ON rf.id = ef.communication_recipient_id WHERE rf.communication_id = c.id AND ef.status = 'failed') THEN 'failed'
                    WHEN NOT EXISTS (SELECT 1 FROM communication_recipient_endpoints ep JOIN communication_recipients rp ON rp.id = ep.communication_recipient_id WHERE rp.communication_id = c.id AND ep.status NOT IN ('sent','delivered')) THEN 'delivered'
                    ELSE c.status END,
                   c.processed_at = CASE WHEN c.status = 'delivered' THEN NOW() ELSE c.processed_at END
             WHERE e.provider_message_id = ?"
        );
        $aggregate->execute([(string) $providerMessageId]);
        return $result;
    }

    /**
     * Mark a recipient as opted out (used for opt-out callbacks)
     * @param string $recipientIdentifier (phone/email/user id)
     * @param string $channel (sms, email, whatsapp, etc)
     * @return bool
     */
    public function markOptOut($recipientIdentifier, $channel)
    {
        // Mark a recipient as opted out by adding a note to their contact record
        // Try to find and update the contact record by phone number
        $sql = "UPDATE contact_directory SET notes = CONCAT(IFNULL(notes, ''), '\n[Opted out from SMS: ' , NOW(), ']') WHERE phone = :phone";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':phone' => $recipientIdentifier]);

        // Also log the opt-out action for audit trail if there's a communication log table
        // This is optional but good for tracking
        return $result;
    }

    /**
     * Store incoming message (used for subscription/inbound callbacks)
     * @param array $data (should include sender, message, channel, received_at, etc)
     * @return bool
     */
    public function storeIncomingMessage($data)
    {
        $sql = "INSERT INTO external_inbound_messages (source_address, body, source_type, received_at, subject) VALUES (:source_address, :body, :source_type, :received_at, :subject)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':source_address' => $data['sender'] ?? $data['phone'] ?? null,
            ':body' => $data['message'] ?? null,
            ':source_type' => $data['channel'] ?? 'sms',
            ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            ':subject' => $data['subject'] ?? null
        ]);
    }

    /** Store and route an Africa's Talking WhatsApp inbound event. */
    public function storeIncomingWhatsappMessage(array $data): array
    {
        $sender = trim((string) ($data['phone'] ?? $data['sender'] ?? $data['phoneNumber'] ?? $data['from'] ?? ''));
        $message = (string) ($data['message'] ?? $data['text'] ?? $data['body'] ?? '');
        $providerId = trim((string) ($data['message_id'] ?? $data['messageId'] ?? $data['provider_message_id'] ?? ''));
        if ($sender === '') throw new \InvalidArgumentException('WhatsApp sender is required');
        $normalized = preg_replace('/[^0-9]/', '', $sender);
        if (substr($normalized, 0, 1) === '0') $normalized = '254' . substr($normalized, 1);

        $parentId = null;
        $userId = null;
        $contactStmt = $this->db->query("SELECT pr.id AS parent_id, u.id AS user_id, p.phone FROM parents pr JOIN persons p ON p.id = pr.person_id LEFT JOIN users u ON u.person_id = p.id AND u.status = 'active' WHERE pr.status = 'active'");
        foreach ($contactStmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
            $candidate = preg_replace('/[^0-9]/', '', (string) ($contact['phone'] ?? ''));
            if (substr($candidate, 0, 1) === '0') $candidate = '254' . substr($candidate, 1);
            if ($candidate !== '' && $candidate === $normalized) {
                $parentId = (int) $contact['parent_id'];
                $userId = $contact['user_id'] !== null ? (int) $contact['user_id'] : null;
                break;
            }
        }

        $thread = $this->db->prepare("SELECT ct.id FROM communication_threads ct JOIN communication_thread_messages tm ON tm.thread_id = ct.id WHERE ct.thread_type = 'parent_portal' AND tm.sender_address = ? AND ct.status IN ('open','pending') ORDER BY ct.id DESC LIMIT 1");
        $thread->execute([$sender]);
        $threadId = (int) ($thread->fetchColumn() ?: 0);
        if (!$threadId) {
            $this->db->prepare("INSERT INTO communication_threads (thread_type, subject, created_by, status) VALUES ('parent_portal', 'WhatsApp conversation', NULL, 'open')")->execute();
            $threadId = (int) $this->db->lastInsertId();
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO external_inbound_messages (source_type, source_address, provider_message_id, received_at, linked_user_id, linked_parent_id, subject, body, status, processing_notes, raw_payload, thread_id) VALUES ('whatsapp', ?, ?, ?, ?, ?, 'WhatsApp inbound message', ?, 'processed', ?, ?, ?)");
            $stmt->execute([$sender, $providerId !== '' ? $providerId : null, $data['received_at'] ?? date('Y-m-d H:i:s'), $userId, $parentId, $message, $parentId ? 'Matched to parent account' : 'Unverified sender; staff review required', json_encode($data, JSON_UNESCAPED_SLASHES), $threadId]);
            $inboundId = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO communication_thread_messages (thread_id, sender_user_id, sender_address, direction, subject, body) VALUES (?, ?, ?, 'inbound', 'WhatsApp message', ?)")->execute([$threadId, $userId, $sender, $message]);
            $mediaUrl = $data['media_url'] ?? $data['url'] ?? null;
            if ($mediaUrl) {
                $type = strtolower((string) ($data['media_type'] ?? $data['mediaType'] ?? 'other'));
                if (!in_array($type, ['image','video','audio','sticker','document','voice'], true)) $type = 'other';
                $this->db->prepare("INSERT INTO external_inbound_media (inbound_message_id, media_type, media_url, caption) VALUES (?, ?, ?, ?)")->execute([$inboundId, $type, $mediaUrl, $data['caption'] ?? null]);
            }
            $this->db->commit();
            return ['status' => 'success', 'inbound_id' => $inboundId, 'thread_id' => $threadId, 'matched_parent' => $parentId !== null];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if (strpos(strtolower($e->getMessage()), 'duplicate') !== false && $providerId !== '') return ['status' => 'duplicate', 'message' => 'Inbound WhatsApp event already recorded'];
            throw $e;
        }
    }

    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Send SMS to selected recipients with given message.
     * @param array $recipients Array of phone numbers
     * @param string $message
     * @return array [status, message, sent_count, failed]
     */
    /**
     * Send SMS, MMS, or WhatsApp to selected recipients with given message/media.
     * @param array $recipients Array of phone numbers
     * @param string $message
     * @param string $type 'sms', 'mms', or 'whatsapp'
     * @param string|array|null $media (for MMS/WhatsApp)
     * @return array [status, message, sent_count, failed]
     */
    public function sendSMSToRecipients($recipients, $variables, $type = 'sms', $category = '', $media = null)
    {
        // Use TemplateLoader for template selection and rendering
        $templateLoader = new \App\API\Modules\Communications\Templates\TemplateLoader();
        $template = $templateLoader->getTemplate($type, $category);
        if (!$template) {
            return [
                'status' => 'error',
                'message' => 'No template found for type/category',
                'sent_count' => 0,
                'failed' => $recipients
            ];
        }
        $gateway = new \App\API\Services\SMS\SMSGateway();
        $sent = 0;
        $failed = [];
        foreach ($recipients as $phone) {
            try {
                $rendered = $templateLoader->renderTemplate($template, $variables);
                if ($type === 'whatsapp') {
                    $mediaUrls = $templateLoader->getMedia($template);
                    $result = $gateway->sendWhatsApp($phone, $rendered, $mediaUrls);
                } else {
                    $result = $gateway->send($phone, $rendered);
                }
                if ($result) {
                    $sent++;
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent messages" : 'Failed to send messages',
            'sent_count' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Send email to selected recipients with given subject, body, and attachments.
     * @param array $recipients Array of [email => name] or just emails
     * @param string $subject
     * @param string $body
     * @param array $attachments
     * @param string $signature
     * @param string $footer
     * @param array $schoolDetails
     * @return array [status, message, sent_count, failed]
     */
    public function sendEmailToRecipients($recipients, $subject, $body, $attachments = [], $signature = '', $footer = '', $schoolDetails = [])
    {
        // Lazy load MessageService
        $service = new \App\API\Services\MessageService($this->db);
        $sent = 0;
        $failed = [];
        foreach ($recipients as $email => $name) {
            // If $recipients is a list, not assoc array
            if (is_int($email)) {
                $email = $name;
                $name = '';
            }

            // Determine if this should use formal layout (when body is array or signature/footer provided)
            $isFormal = is_array($body) || !empty($signature) || !empty($footer);

            if ($isFormal) {
                // Use formal email template with proper placeholder population
                $htmlBody = $service->renderFormalEmail($subject, $body, $signature, $footer, '', $schoolDetails);
            } else {
                // Use standard email rendering
                $htmlBody = $service->renderEmail($subject, $body, $signature, $footer, '', $schoolDetails);
            }

            $result = $service->sendEmail([$email => $name], $subject, $htmlBody, $attachments);
            if ($result) {
                $sent++;
            } else {
                $failed[] = $email;
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent emails" : 'Failed to send emails',
            'sent_count' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Format email body sections into formal letter format
     * Supports structured body with sections: salutation, intro, main_content, closing, sign_off
     */
    public function formatFormalEmailBody($sections)
    {
        if (is_string($sections)) {
            return $sections; // If already string, return as-is
        }

        $formatted = '';

        // Salutation
        if (isset($sections['salutation']) && !empty($sections['salutation'])) {
            $formatted .= '<p style="margin-bottom: 20px;">' . htmlspecialchars($sections['salutation']) . '</p>';
        }

        // Introduction paragraph
        if (isset($sections['intro']) && !empty($sections['intro'])) {
            $formatted .= '<p style="margin-bottom: 16px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['intro'])) . '</p>';
        }

        // Main content section with formatting
        if (isset($sections['main_content'])) {
            if (is_array($sections['main_content'])) {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.8;">';
                foreach ($sections['main_content'] as $line) {
                    if (substr($line, 0, 1) === '-' || substr($line, 0, 1) === '•') {
                        $formatted .= '<div style="margin-left: 20px; margin-bottom: 8px;">' . htmlspecialchars($line) . '</div>';
                    } else if (substr($line, -1) === ':') {
                        $formatted .= '<div style="margin-top: 16px; margin-bottom: 8px; font-weight: bold;">' . htmlspecialchars($line) . '</div>';
                    } else {
                        $formatted .= '<div style="margin-bottom: 8px;">' . htmlspecialchars($line) . '</div>';
                    }
                }
                $formatted .= '</div>';
            } else {
                $formatted .= '<div style="margin: 24px 0; line-height: 1.6;">'
                    . nl2br(htmlspecialchars($sections['main_content'])) . '</div>';
            }
        }

        // Closing paragraph
        if (isset($sections['closing']) && !empty($sections['closing'])) {
            $formatted .= '<p style="margin-bottom: 16px; margin-top: 24px; line-height: 1.6;">'
                . nl2br(htmlspecialchars($sections['closing'])) . '</p>';
        }

        // Sign-off
        if (isset($sections['sign_off'])) {
            $formatted .= '<div style="margin-top: 32px; margin-bottom: 8px;">'
                . htmlspecialchars($sections['sign_off']) . '</div>';
        }

        return $formatted;
    }

    // Communications CRUD
    public function createCommunication($data)
    {
        // Map channel to type
        $type = $data['type'] ?? $data['channel'] ?? 'email';
        $typeMap = [
            'sms' => 'sms',
            'email' => 'email',
            'notification' => 'notification',
            'internal' => 'internal',
            'whatsapp' => 'whatsapp',
            'message' => 'email'  // Default to email if type is 'message'
        ];
        $type = $typeMap[$type] ?? 'email';

        // Get content from message or content field
        $body = $data['body'] ?? $data['content'] ?? $data['message'] ?? 'No content';
        // If body is array, convert to JSON for storage
        if (is_array($body)) {
            $content = json_encode($body);
        } else {
            $content = $body;
        }

        $sql = "INSERT INTO communications (sender_id, subject, body, type, status, priority, template_id, scheduled_at, reminder_at, sender_signature) VALUES (:sender_id, :subject, :body, :type, :status, :priority, :template_id, :scheduled_at, :reminder_at, :sender_signature)";
        $stmt = $this->db->prepare($sql);
        $requestedStatus = $this->normalizeStatus($data['status'] ?? 'draft');
        $recordOnly = !empty($data['record_only']);
        $storedStatus = (!$recordOnly && $requestedStatus === 'sent') ? 'queued' : ($recordOnly && $requestedStatus === 'sent' ? 'delivered' : $requestedStatus);
        if (!$recordOnly && in_array($storedStatus, ['queued', 'scheduled'], true) && empty($data['recipients'])) {
            throw new \InvalidArgumentException('At least one recipient is required for delivery');
        }
        $startedTransaction = !$this->db->inTransaction();
        if ($startedTransaction) $this->db->beginTransaction();
        try {
        $stmt->execute([
            ':sender_id' => $data['sender_id'] ?? 1,
            ':subject' => $data['subject'] ?? 'No subject',
            ':body' => $content,
            ':type' => $type,
            ':status' => $storedStatus,
            ':priority' => $this->normalizePriority($data['priority'] ?? 'medium'),
            ':template_id' => $data['template_id'] ?? null,
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':reminder_at' => $data['reminder_at'] ?? null,
            ':sender_signature' => $data['sender_signature'] ?? $data['signature'] ?? null,
        ]);
        $communicationId = (int) $this->db->lastInsertId();
        if (!empty($data['recipients'])) {
            $this->createCommunicationRecipients($communicationId, $type, (array) $data['recipients'], $data['recipient_type'] ?? null, $recordOnly, $requestedStatus, (array) ($data['target_ids'] ?? []));
        }
        $communication = $this->getCommunication($communicationId);
        if ($startedTransaction) $this->db->commit();
        return $communication;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Create one durable delivery row per resolved email/phone recipient. */
    private function createCommunicationRecipients(int $communicationId, string $type, array $recipients, ?string $recipientType, bool $recordOnly = false, string $recordOnlyStatus = 'sent', array $targetIds = []): void
    {
        $rows = [];
        $targetIds = array_values(array_filter(array_map('intval', $targetIds)));
        if ($recipientType === 'selected_parents') {
            $ids = $targetIds ?: array_values(array_filter(array_map('intval', $recipients)));
            if (!$ids) throw new \InvalidArgumentException('At least one parent is required');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare("SELECT DISTINCT u.id AS user_id, p.phone, p.email FROM parents pr JOIN persons p ON p.id = pr.person_id LEFT JOIN users u ON u.person_id = pr.person_id AND u.status = 'active' WHERE pr.status = 'active' AND pr.id IN ($marks)");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) { $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']); if ($address !== '') $rows[] = [$contact['user_id'] !== null ? (int) $contact['user_id'] : null, $address]; }
        } elseif (in_array($recipientType, ['selected_students','selected_class','student_type','school_level'], true)) {
            $ids = $targetIds ?: array_values(array_filter(array_map('intval', $recipients)));
            if (!$ids) throw new \InvalidArgumentException('At least one audience item is required');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $where = $recipientType === 'selected_students' ? "s.id IN ($marks)" : ($recipientType === 'selected_class' ? "c.id IN ($marks)" : ($recipientType === 'student_type' ? "s.student_type_id IN ($marks)" : "c.level_id IN ($marks)"));
            $stmt = $this->db->prepare("SELECT DISTINCT u.id AS user_id, p.phone, p.email FROM students s JOIN persons sperson ON sperson.id = s.person_id JOIN student_parents sx ON sx.student_id = s.id JOIN parents pr ON pr.id = sx.parent_id AND pr.status = 'active' JOIN users u ON u.person_id = pr.person_id AND u.status = 'active' JOIN persons p ON p.id = u.person_id JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active' JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id AND aycs.status = 'active' JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id AND ayc.status = 'active' JOIN classes c ON c.id = ayc.class_id WHERE s.status = 'active' AND {$where}");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) { $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']); if ($address !== '') $rows[] = [(int) $contact['user_id'], $address]; }
        } elseif ($recipientType === 'selected_staff') {
            $ids = $targetIds ?: array_values(array_filter(array_map('intval', $recipients)));
            if (!$ids) throw new \InvalidArgumentException('At least one staff member is required');
            $stmt = $this->db->prepare("SELECT DISTINCT u.id AS user_id, p.email, p.phone FROM staff s JOIN users u ON u.person_id = s.person_id AND u.status = 'active' JOIN persons p ON p.id = u.person_id WHERE s.status = 'active' AND s.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) { $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']); if ($address !== '') $rows[] = [(int) $contact['user_id'], $address]; }
        } elseif (in_array($recipientType, ['selected_vendors','all_vendors'], true)) {
            $ids = $targetIds ?: array_values(array_filter(array_map('intval', $recipients)));
            $sql = "SELECT id, phone, email FROM suppliers WHERE status = 'active'";
            $params = [];
            if ($recipientType === 'selected_vendors') {
                if (!$ids) throw new \InvalidArgumentException('At least one vendor is required');
                $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = $ids;
            }
            $stmt = $this->db->prepare($sql); $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $supplier) { $address = $type === 'email' ? trim((string) $supplier['email']) : trim((string) $supplier['phone']); if ($address !== '') $rows[] = [null, $address]; }
        } elseif ($recipientType === 'all_parents') {
            $stmt = $this->db->query(
                "SELECT DISTINCT u.id AS user_id, p.email, p.phone
                   FROM parents pr JOIN users u ON u.person_id = pr.person_id AND u.status = 'active'
                   JOIN persons p ON p.id = u.person_id WHERE pr.status = 'active'"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
                $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']);
                if ($address !== '') $rows[] = [(int) $contact['user_id'], $address];
            }
        } elseif ($recipientType === 'all_staff') {
            $stmt = $this->db->query(
                "SELECT DISTINCT u.id AS user_id, p.email, p.phone
                   FROM staff s JOIN users u ON u.person_id = s.person_id AND u.status = 'active'
                   JOIN persons p ON p.id = u.person_id WHERE s.status = 'active'"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
                $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']);
                if ($address !== '') $rows[] = [(int) $contact['user_id'], $address];
            }
        } elseif ($recipientType === 'contact_group') {
            $groupIds = array_values(array_filter(array_map('intval', $recipients)));
            if ($groupIds) {
                $marks = implode(',', array_fill(0, count($groupIds), '?'));
                $sql = "SELECT DISTINCT gm.user_id, p.email, p.phone
                          FROM group_members gm
                          JOIN users u ON u.id = gm.user_id
                          JOIN persons p ON p.id = u.person_id
                         WHERE gm.group_id IN ($marks)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($groupIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $contact) {
                    $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']);
                    if ($address !== '') $rows[] = [(int) $contact['user_id'], $address];
                }
            }
        }
        foreach ($recipients as $recipient) {
            if (in_array($recipientType, ['contact_group', 'all_parents', 'all_staff', 'all_students', 'selected_parents','selected_students','selected_class','student_type','school_level','selected_staff','selected_vendors','all_vendors'], true)) continue;
            $value = trim((string) $recipient);
            if ($value === '') continue;
            if (ctype_digit($value)) {
                $stmt = $this->db->prepare("SELECT id, name, email, phone FROM contact_directory WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $value]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($contact) {
                    $address = $type === 'email' ? trim((string) $contact['email']) : trim((string) $contact['phone']);
                    if ($address !== '') $rows[] = [(int) $contact['id'], $address];
                    continue;
                }
            }
            if (ctype_digit($value)) {
                throw new \InvalidArgumentException('Recipient contact could not be resolved');
            }
            $rows[] = [null, $value];
        }
        if (!$rows) throw new \InvalidArgumentException('At least one valid recipient is required');
        $recipientStatus = $recordOnly ? ($recordOnlyStatus === 'failed' ? 'failed' : 'sent') : 'queued';
        $endpointStatus = $recordOnly ? ($recordOnlyStatus === 'failed' ? 'failed' : 'sent') : 'pending';
        $recipientStmt = $this->db->prepare(
            "INSERT INTO communication_recipients (communication_id, recipient_id, status)
             VALUES (?, ?, '{$recipientStatus}')"
        );
        $endpointStmt = $this->db->prepare(
            "INSERT INTO communication_recipient_endpoints
                (communication_recipient_id, channel, address, status)
             VALUES (?, ?, ?, '{$endpointStatus}')"
        );
        foreach ($rows as [$recipientId, $address]) {
            $recipientStmt->execute([$communicationId, $recipientId]);
            $endpointStmt->execute([(int) $this->db->lastInsertId(), $type, $address]);
        }
    }
    public function getCommunication($id, array $filters = [])
    {
        $sql = "SELECT * FROM communications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && !$this->communicationVisibleTo($row, $filters)) {
            return null;
        }
        return $row === false ? null : $this->decorateRow($row);
    }

    private function communicationVisibleTo(array $row, array $filters): bool
    {
        $visibility = $filters['visibility'] ?? 'all';
        if ($visibility === 'all') return true;
        $userId = (int) ($filters['visibility_user_id'] ?? 0);
        if ($visibility === 'self_or_tagged') {
            if ($userId < 1) return false;
            $stmt = $this->db->prepare('SELECT 1 FROM communication_recipients WHERE communication_id = ? AND recipient_id = ? LIMIT 1');
            $stmt->execute([(int) $row['id'], $userId]);
            return (int) $row['sender_id'] === $userId || (bool) $stmt->fetchColumn();
        }
        if ($visibility === 'teacher_parent') {
            $stmt = $this->db->prepare("SELECT 1 FROM user_roles WHERE user_id = ? AND role_id IN (7,8,9) LIMIT 1");
            $stmt->execute([(int) ($row['sender_id'] ?? 0)]);
            $signature = strtolower((string) ($row['sender_signature'] ?? ''));
            if (!(bool) $stmt->fetchColumn()) return false;
            if (strpos($signature, 'parent') !== false || strpos($signature, 'selected_students') !== false || strpos($signature, 'selected_class') !== false) return true;
            $recipientStmt = $this->db->prepare('SELECT 1 FROM communication_recipients cr JOIN users parent_user ON parent_user.id = cr.recipient_id JOIN parents parent_record ON parent_record.person_id = parent_user.person_id WHERE cr.communication_id = ? LIMIT 1');
            $recipientStmt->execute([(int) $row['id']]);
            return (bool) $recipientStmt->fetchColumn();
        }
        return false;
    }
    public function updateCommunication($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["sender_id", "subject", "body", "type", "template_id", "scheduled_at", "reminder_at", "sender_signature"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $this->normalizeStatus($data['status']);
        }
        if (isset($data['priority'])) {
            $fields[] = "priority = :priority";
            $params[':priority'] = $this->normalizePriority($data['priority']);
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE communications SET " . implode(",", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getCommunication($id);
    }
    public function deleteCommunication($id)
    {
        $sql = "DELETE FROM communications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listCommunications($filters = [])
    {
        $sql = "SELECT * FROM communications WHERE 1=1";
        $params = [];
        foreach (["sender_id", "type", "status", "priority", "template_id"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $visibility = $filters['visibility'] ?? 'all';
        $visibilityUserId = (int) ($filters['visibility_user_id'] ?? 0);
        if ($visibility === 'self_or_tagged' && $visibilityUserId > 0) {
            $sql .= " AND (sender_id = :visibility_user OR EXISTS (SELECT 1 FROM communication_recipients cr_scope WHERE cr_scope.communication_id = communications.id AND cr_scope.recipient_id = :visibility_recipient))";
            $params[':visibility_user'] = $visibilityUserId;
            $params[':visibility_recipient'] = $visibilityUserId;
        } elseif ($visibility === 'teacher_parent') {
            $sql .= " AND EXISTS (SELECT 1 FROM user_roles sender_role WHERE sender_role.user_id = communications.sender_id AND sender_role.role_id IN (7, 8, 9)) AND (sender_signature LIKE '%parent%' OR sender_signature LIKE '%selected_students%' OR sender_signature LIKE '%selected_class%' OR EXISTS (SELECT 1 FROM communication_recipients cr_parent JOIN users parent_user ON parent_user.id = cr_parent.recipient_id JOIN parents parent_record ON parent_record.person_id = parent_user.person_id WHERE cr_parent.communication_id = communications.id))";
        }
        if (isset($filters['from_date'])) {
            $sql .= " AND created_at >= :from_date";
            $params[':from_date'] = $filters['from_date'];
        }
        if (isset($filters['to_date'])) {
            $sql .= " AND created_at <= :to_date";
            $params[':to_date'] = $filters['to_date'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(function ($row) {
            return $this->decorateRow($row);
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Add frontend-friendly aliases to a communications row. The JS page
     * controllers read `content`/`title` while the DB column is `body`/`subject`.
     */
    private function decorateRow(array $row): array
    {
        if (!array_key_exists('content', $row)) {
            $row['content'] = $row['body'] ?? null;
        }
        if (!array_key_exists('title', $row)) {
            $row['title'] = $row['subject'] ?? null;
        }
        return $row;
    }

    /**
     * Normalize a priority value to the communications.priority enum
     * ('low'|'medium'|'high'). Frontends send UI aliases such as 'normal'
     * (→ medium) and 'urgent' (→ high), which would otherwise be rejected
     * by the strict enum column.
     *
     * @param mixed $priority
     * @return string
     */
    private function normalizePriority($priority): string
    {
        $map = [
            'low' => 'low',
            'normal' => 'medium',
            'medium' => 'medium',
            'high' => 'high',
            'urgent' => 'high',
        ];
        $key = strtolower(trim((string)$priority));
        return $map[$key] ?? 'medium';
    }

    /**
     * Normalize a status value to the communications.status enum
     * ('draft'|'sent'|'scheduled'|'failed'). Frontends use 'published'
     * as an alias for 'sent'.
     *
     * @param mixed $status
     * @return string
     */
    private function normalizeStatus($status): string
    {
        $map = [
            'draft' => 'draft',
            'published' => 'sent',
            'sent' => 'sent',
            'scheduled' => 'scheduled',
            'pending' => 'draft',
            'failed' => 'failed',
        ];
        $key = strtolower(trim((string)$status));
        return $map[$key] ?? 'draft';
    }

    // Attachments CRUD
    public function addAttachment($communicationId, $fileData)
    {
        // If no communication_id provided, use default or file-based storage
        if (!$communicationId) {
            $communicationId = 1; // Default to system communication
        }

        // If an uploaded file is provided in $fileData (or in $_FILES), use MediaManager to store it
        $filePath = $fileData['file_path'] ?? null;
        $fileName = $fileData['file_name'] ?? null;
        $mimeType = $fileData['mime_type'] ?? null;
        $fileSize = $fileData['file_size'] ?? null;
        $mediaId = null;
        try {
            $mediaManager = $this->contract('App\API\Modules\system\MediaManager', $this->db);
            $uploadFile = $fileData['file'] ?? ($_FILES['file'] ?? null);
            $uploader = $fileData['uploaded_by'] ?? $fileData['uploader_id'] ?? $fileData['user_id'] ?? null;
            if ($uploadFile) {
                $mediaId = $mediaManager->upload($uploadFile, 'communications', $communicationId, null, $uploader, $fileData['description'] ?? 'communication attachment');
                $preview = $mediaManager->getPreviewUrl($mediaId);
                $filePath = $preview ?: $filePath;
                $fileName = $fileName ?: ($uploadFile['name'] ?? basename($filePath));
                $mimeType = $mimeType ?: ($uploadFile['type'] ?? null);
                $fileSize = $fileSize ?: ($uploadFile['size'] ?? null);
            }
        } catch (\Exception $e) {
            // fallback to provided file_path/file_name if media upload fails
        }

        $publicUrl = filter_var($filePath, FILTER_VALIDATE_URL) ? $filePath : $this->publicUploadAssetUrl('communications', (string) ($fileName ?? 'unnamed_file'));
        $sql = "INSERT INTO communication_attachments (communication_id, file_name, file_path, mime_type, file_size, public_url) VALUES (:communication_id, :file_name, :file_path, :mime_type, :file_size, :public_url)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':file_name' => $fileName ?? 'unnamed_file',
            ':file_path' => $filePath ?? $publicUrl,
            ':mime_type' => $mimeType,
            ':file_size' => $fileSize,
            ':public_url' => $publicUrl,
        ]);
        $attachmentId = (int) $this->db->lastInsertId();
        $channels = $fileData['channels'] ?? ($fileData['channel'] ?? 'email');
        if (!is_array($channels)) $channels = [$channels];
        $channelStmt = $this->db->prepare(
            "INSERT INTO communication_attachment_channels (attachment_id, channel, provider_media_url, status) VALUES (?, ?, ?, 'ready') ON DUPLICATE KEY UPDATE provider_media_url = VALUES(provider_media_url), status = 'ready'"
        );
        foreach ($channels as $channel) {
            if (in_array($channel, ['email', 'whatsapp', 'portal'], true)) {
                $channelStmt->execute([$attachmentId, $channel, $publicUrl]);
            }
        }
        return $this->getAttachment($attachmentId);
    }
    public function getAttachment($id)
    {
        $sql = "SELECT * FROM communication_attachments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function deleteAttachment($id)
    {
        $sql = "DELETE FROM communication_attachments WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listAttachments($communicationId)
    {
        $sql = "SELECT * FROM communication_attachments WHERE communication_id = :communication_id ORDER BY uploaded_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':communication_id' => $communicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Groups CRUD
    public function createGroup($data)
    {
        // Map type values to valid enum: 'staff','students','parents','custom'
        $type = $data['type'] ?? 'custom';
        $typeMap = [
            'class' => 'students',
            'department' => 'staff',
            'parent_forum' => 'parents'
        ];
        $type = $typeMap[$type] ?? $type;
        // Validate against enum
        if (!in_array($type, ['staff', 'students', 'parents', 'custom'])) {
            $type = 'custom';
        }

        if (empty($data['name'])) {
            throw new \InvalidArgumentException('name is required');
        }
        $sql = "INSERT INTO communication_groups (name, description, type, created_by) VALUES (:name, :description, :type, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':type' => $type,
            ':created_by' => $data['created_by'] ?? 1
        ]);
        return $this->getGroup($this->db->lastInsertId());
    }
    public function getGroup($id)
    {
        $sql = "SELECT * FROM communication_groups WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function updateGroup($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["name", "description", "type"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE communication_groups SET " . implode(", ", $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $sql = preg_replace('/,\s*,/', ',', $sql); // Remove accidental double commas
        $sql = str_replace('SET ,', 'SET ', $sql); // Remove accidental leading comma
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getGroup($id);
    }
    public function deleteGroup($id)
    {
        $sql = "DELETE FROM communication_groups WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listGroups($filters = [])
    {
        $sql = "SELECT * FROM communication_groups WHERE 1=1";
        $params = [];
        foreach (["type", "created_by"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Logs CRUD - stored in the file-based communication log
    public function addLog($data)
    {
        // If communication_id is not provided, create a placeholder or use a system entry
        $communicationId = $data['communication_id'] ?? 0;
        $recipientId = $data['recipient_id'] ?? 0;

        $eventType = $data['event_type'] ?? $data['action'] ?? 'log';
        $details = $data['details'] ?? null;
        $payload = ['event_type' => $eventType];
        if ($details !== null) {
            $payload['details'] = is_array($details) ? $details : json_decode((string) $details, true);
        }

        \App\API\Includes\FileLogger::write('audit', [
            'type' => 'audit',
            'action' => 'communication_log',
            'entity' => 'communication',
            'entity_id' => (int) $communicationId,
            'user_id' => (int) $recipientId,
            'details' => $payload,
            'status' => 'success',
        ]);
        return [
            'id' => null,
            'communication_id' => (int) $communicationId,
            'recipient_id' => (int) $recipientId,
            'event_type' => $eventType,
            'details' => $payload
        ];
    }
    public function getLog($id)
    {
        $entries = \App\API\Includes\FileLogger::recent('audit', 100, ['action' => 'communication_log']);
        foreach ($entries as $row) {
            $row['communication_id'] = $row['entity_id'] ?? null;
            $row['recipient_id'] = $row['user_id'] ?? null;
            $decoded = isset($row['details']) && is_array($row['details']) ? $row['details'] : [];
            $row['event_type'] = $decoded['event_type'] ?? 'log';
            $row['details'] = $decoded['details'] ?? $decoded;
            $row['recipient'] = $row['recipient_id'];
            $row['status'] = 'logged';
            $row['created_at'] = $row['timestamp'] ?? null;
            return $row;
        }
        return null;
    }
    public function listLogs($filters = [])
    {
        $entries = \App\API\Includes\FileLogger::recent('audit', 500, ['action' => 'communication_log']);
        $rows = [];
        foreach ($entries as $row) {
            if (!empty($filters['communication_id']) && (int) ($row['entity_id'] ?? 0) !== (int) $filters['communication_id']) {
                continue;
            }
            if (!empty($filters['recipient_id']) && (int) ($row['user_id'] ?? 0) !== (int) $filters['recipient_id']) {
                continue;
            }
            $decoded = isset($row['details']) && is_array($row['details']) ? $row['details'] : [];
            $rows[] = [
                'id' => null,
                'communication_id' => $row['entity_id'] ?? null,
                'recipient_id' => $row['user_id'] ?? null,
                'event_type' => $decoded['event_type'] ?? 'log',
                'details' => $decoded['details'] ?? $decoded,
                'created_at' => $row['timestamp'] ?? null,
            ];
        }
        return $rows;
    }

    // Recipients CRUD
    public function addRecipient($data)
    {
        // If communication_id or recipient_id is missing, use default placeholders
        $communicationId = $data['communication_id'] ?? 0;
        $recipientId = $data['recipient_id'] ?? 0;

        // Convert various recipient formats
        if (!$recipientId) {
            // Try to extract from recipient data
            if (isset($data['recipient'])) {
                // Could be phone, email, or name
                $recipientId = isset($data['recipient_id']) ? $data['recipient_id'] : 1;
            } else {
                $recipientId = 1; // Default to system
            }
        }

        if (!$communicationId) {
            $communicationId = 1; // Default communication
        }

        $sql = "INSERT INTO communication_recipients (communication_id, recipient_id, status, delivered_at, delivery_attempts, last_attempt_at, error_message, opened_at, clicked_at, device_info) VALUES (:communication_id, :recipient_id, :status, :delivered_at, :delivery_attempts, :last_attempt_at, :error_message, :opened_at, :clicked_at, :device_info)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':recipient_id' => $recipientId,
            ':status' => $data['status'] ?? 'pending',
            ':delivered_at' => $data['delivered_at'] ?? null,
            ':delivery_attempts' => $data['delivery_attempts'] ?? 0,
            ':last_attempt_at' => $data['last_attempt_at'] ?? null,
            ':error_message' => $data['error_message'] ?? null,
            ':opened_at' => $data['opened_at'] ?? null,
            ':clicked_at' => $data['clicked_at'] ?? null,
            ':device_info' => $data['device_info'] ?? null
        ]);
        return $this->getRecipient($this->db->lastInsertId());
    }
    public function getRecipient($id)
    {
        $sql = "SELECT * FROM communication_recipients WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function deleteRecipient($id)
    {
        $sql = "DELETE FROM communication_recipients WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listRecipients($communicationId)
    {
        $sql = "SELECT * FROM communication_recipients WHERE communication_id = :communication_id ORDER BY id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':communication_id' => $communicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Templates CRUD - mapped to live message_templates (legacy communication_templates retired)
    public function createTemplate($data)
    {
        // Map channel to message_templates.type (enum: email, sms, notification)
        $canonicalChannel = strtolower((string) ($data['template_type'] ?? $data['channel'] ?? 'sms'));
        $templateType = $canonicalChannel;
        $typeMap = [
            'sms' => 'sms',
            'email' => 'email',
            'whatsapp' => 'whatsapp',
            'notification' => 'notification',
            'announcement' => 'notification',
            'internal' => 'notification',
            'internal_message' => 'notification',
            'message' => 'email'
        ];
        $templateType = $typeMap[$templateType] ?? 'sms';

        // Get template body from content or template_body or message field
        $templateBody = $data['template_body'] ?? $data['content'] ?? $data['message'] ?? 'No template body';

        $sql = "INSERT INTO message_templates (name, type, category, subject, body, variables, created_by, status, use_count) VALUES (:name, :type, :category, :subject, :body, :variables, :created_by, :status, :use_count)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'] ?? 'Unnamed Template',
            ':type' => $templateType,
            ':category' => $data['category'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':body' => $templateBody,
            ':variables' => isset($data['variables_json']) ? json_encode($data['variables_json']) : ($data['variables'] ?? null),
            ':created_by' => $data['created_by'] ?? 1,
            ':status' => $data['status'] ?? 'active',
            ':use_count' => $data['usage_count'] ?? 0
        ]);
        $legacyId = (int) $this->db->lastInsertId();
        $this->syncCanonicalTemplateVersion([
            'code' => ($canonicalChannel ?: $templateType) . '.' . (($data['category'] ?? '') ?: 'custom_' . $legacyId),
            'name' => $data['name'] ?? 'Unnamed Template',
            'purpose' => $data['category'] ?? 'custom',
            'channel' => in_array($canonicalChannel, ['email','sms','whatsapp','portal','in_app'], true) ? $canonicalChannel : $templateType,
            'subject' => $data['subject'] ?? null,
            'body' => $templateBody,
            'variables' => $data['variables_json'] ?? $data['variables'] ?? [],
            'created_by' => $data['created_by'] ?? 1,
        ]);
        return $this->getTemplate($legacyId);
    }
    public function getTemplate($id)
    {
        $sql = "SELECT * FROM message_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row = $this->decorateTemplate($row);
        }
        return $row;
    }
    public function updateTemplate($id, $data)
    {
        $existing = $this->getTemplate($id);
        $colMap = [
            'name' => 'name',
            'template_type' => 'type',
            'category' => 'category',
            'subject' => 'subject',
            'template_body' => 'body',
            'variables_json' => 'variables',
            'status' => 'status',
            'usage_count' => 'use_count',
        ];
        $fields = [];
        $params = [':id' => $id];
        foreach ($colMap as $inputKey => $col) {
            if (isset($data[$inputKey])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = ($inputKey === 'variables_json') ? json_encode($data[$inputKey]) : $data[$inputKey];
            }
        }
        if (!$fields) {
            throw new \InvalidArgumentException('No fields to update');
        }
        $sql = "UPDATE message_templates SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $fresh = $this->getTemplate($id);
        if ($fresh) {
            $this->syncCanonicalTemplateVersion([
                'code' => ($fresh['template_type'] ?? 'sms') . '.' . (($fresh['category'] ?? '') ?: 'custom_' . $id),
                'name' => $fresh['name'],
                'purpose' => $fresh['category'] ?? 'custom',
                'channel' => in_array(($fresh['template_type'] ?? ''), ['email','sms','whatsapp','portal','in_app'], true) ? $fresh['template_type'] : 'sms',
                'subject' => $fresh['subject'],
                'body' => $fresh['template_body'],
                'variables' => $fresh['variables_json'] ?? [],
                'created_by' => $fresh['created_by'] ?? 1,
            ]);
        }
        return $fresh ?: $existing;
    }
    public function deleteTemplate($id)
    {
        $sql = "DELETE FROM message_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([':id' => $id]);
        $this->db->prepare("UPDATE communication_template_catalog SET status = 'inactive' WHERE code LIKE CONCAT('%custom_', ?, '%')")->execute([(int) $id]);
        return $result;
    }
    public function listTemplates($filters = [])
    {
        $sql = "SELECT * FROM message_templates WHERE 1=1";
        $params = [];
        if (isset($filters['template_type']) && $filters['template_type'] !== '') {
            $filters['type'] = $filters['template_type'];
        }
        foreach (["type", "category", "status", "created_by"] as $col) {
            if (isset($filters[$col])) {
                $sql .= " AND $col = :$col";
                $params[":$col"] = $filters[$col];
            }
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row = $this->decorateTemplate($row);
        }
        return $rows;
    }

    private function decorateTemplate(array $row): array
    {
        $row['template_type'] = $row['type'] ?? null;
        $row['template_body'] = $row['body'] ?? null;
        $row['usage_count'] = $row['use_count'] ?? 0;
        $row['example_output'] = null;
        $row['variables_json'] = isset($row['variables']) ? json_decode($row['variables'], true) : null;
        return $row;
    }

    private function syncCanonicalTemplateVersion(array $data): void
    {
        $channel = $data['channel'];
        $code = $data['code'];
        if (!in_array($channel, ['email','sms','whatsapp','portal','in_app'], true)) return;
        // Version numbering is serialized per template: the FOR UPDATE gap-lock
        // must hold through the INSERT, which requires a wrapping transaction.
        $this->db->beginTransaction();
        try {
            $this->syncCanonicalTemplateVersionLocked($data, $code);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function syncCanonicalTemplateVersionLocked(array $data, string $code): void
    {
        $channel = $data['channel'];
        $this->db->prepare(
            "INSERT INTO communication_template_catalog (code, name, purpose, status, created_by)
             VALUES (?, ?, ?, 'active', ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), purpose = VALUES(purpose), status = 'active'"
        )->execute([$code, $data['name'], $data['purpose'], $data['created_by']]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_catalog WHERE code = ?");
        $stmt->execute([$code]);
        $catalogId = (int) $stmt->fetchColumn();
        $next = $this->db->prepare("SELECT COALESCE(MAX(version_no), 0) FROM communication_template_versions WHERE template_id = ? FOR UPDATE");
        $next->execute([$catalogId]);
        $version = (int) $next->fetchColumn();
        $this->db->prepare("UPDATE communication_template_versions SET status = 'retired' WHERE template_id = ? AND status = 'active'")->execute([$catalogId]);
        $this->db->prepare("INSERT INTO communication_template_versions (template_id, version_no, status, created_by) VALUES (?, ?, 'active', ?)")
            ->execute([$catalogId, $version, $data['created_by']]);
        $versionId = (int) $this->db->lastInsertId();
        $this->db->prepare("INSERT INTO communication_template_channels (template_version_id, channel, subject, body) VALUES (?, ?, ?, ?)")
            ->execute([$versionId, $channel, $data['subject'], $data['body']]);
        $channelId = (int) $this->db->lastInsertId();
        $variables = is_string($data['variables']) ? (json_decode($data['variables'], true) ?: []) : (array) $data['variables'];
        foreach ($variables as $name => $type) {
            $this->db->prepare("INSERT INTO communication_template_variables (template_channel_id, variable_name, data_type) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE data_type = VALUES(data_type)")
                ->execute([$channelId, (string) $name, in_array($type, ['integer','decimal','date','datetime','url','boolean'], true) ? $type : 'string']);
        }
    }

    public function registerProviderTemplate(array $data): void
    {
        $providerId = trim((string) ($data['provider_template_id'] ?? ''));
        if ($providerId === '') return;
        $code = 'whatsapp.provider.' . sha1($providerId);
        $this->db->prepare("INSERT INTO communication_template_catalog (code, name, purpose, status) VALUES (?, ?, 'provider_template', 'active') ON DUPLICATE KEY UPDATE name = VALUES(name), status = 'active'")
            ->execute([$code, (string) ($data['name'] ?? $providerId)]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_catalog WHERE code = ?");
        $stmt->execute([$code]);
        $catalogId = (int) $stmt->fetchColumn();
        $this->db->prepare("INSERT INTO communication_template_versions (template_id, version_no, status) VALUES (?, 1, 'active') ON DUPLICATE KEY UPDATE status = 'active'")->execute([$catalogId]);
        $stmt = $this->db->prepare("SELECT id FROM communication_template_versions WHERE template_id = ? AND version_no = 1");
        $stmt->execute([$catalogId]);
        $versionId = (int) $stmt->fetchColumn();
        $this->db->prepare("INSERT INTO communication_template_channels (template_version_id, channel, language_code, body, provider_name, provider_template_id, provider_template_name, provider_template_status) VALUES (?, 'whatsapp', ?, ?, 'africastalking', ?, ?, ?) ON DUPLICATE KEY UPDATE body = VALUES(body), provider_template_id = VALUES(provider_template_id), provider_template_name = VALUES(provider_template_name), provider_template_status = VALUES(provider_template_status)")
            ->execute([$versionId, $data['language'] ?? 'en', $data['body'] ?? '', $providerId, $data['name'] ?? null, $data['status'] ?? null]);
    }

}
