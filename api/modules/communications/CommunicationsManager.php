<?php
namespace App\API\Modules\communications;

use PDO;

class CommunicationsManager
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

        $sql = "INSERT INTO communications (sender_id, subject, content, type, status, priority, template_id, scheduled_at) VALUES (:sender_id, :subject, :content, :type, :status, :priority, :template_id, :scheduled_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $data['sender_id'] ?? 1,
            ':subject' => $data['subject'] ?? 'No subject',
            ':content' => $content,
            ':type' => $type,
            ':status' => $data['status'] ?? 'draft',
            ':priority' => $data['priority'] ?? 'medium',
            ':template_id' => $data['template_id'] ?? null,
            ':scheduled_at' => $data['scheduled_at'] ?? null
        ]);
        return $this->getCommunication($this->db->lastInsertId());
    }
    public function getCommunication($id)
    {
        $sql = "SELECT * FROM communications WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function updateCommunication($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["sender_id", "subject", "content", "type", "status", "priority", "template_id", "scheduled_at"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $data[$col];
            }
        }
        if (!$fields) {
            throw new \Exception("No fields to update");
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Attachments CRUD
    public function addAttachment($communicationId, $fileData)
    {
        // If no communication_id provided, use default or file-based storage
        if (!$communicationId) {
            $communicationId = 1; // Default to system communication
        }

        $sql = "INSERT INTO communication_attachments (communication_id, file_name, file_path) VALUES (:communication_id, :file_name, :file_path)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':file_name' => $fileData['file_name'] ?? 'unnamed_file',
            ':file_path' => $fileData['file_path'] ?? '/uploads/communications/unnamed_file'
        ]);
        return $this->getAttachment($this->db->lastInsertId());
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
            throw new \Exception("No fields to update");
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

    // Logs CRUD - use log files
    public function addLog($data)
    {
        // If communication_id is not provided, create a placeholder or use a system entry
        $communicationId = $data['communication_id'] ?? 0;
        $recipientId = $data['recipient_id'] ?? 0;

        // If no recipient, use a generic system log entry
        if (!$recipientId && !$communicationId) {
            // Store in file-based log instead
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'action' => $data['action'] ?? 'log',
                'recipient' => $data['recipient'] ?? 'system',
                'channel' => $data['channel'] ?? 'system',
                'status' => $data['status'] ?? 'pending',
                'details' => $data['details'] ?? null
            ];
            $logFile = dirname(__DIR__) . '/logs/communications.log';
            @file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
            return ['status' => 'logged', 'type' => 'file'];
        }

        $sql = "INSERT INTO communication_logs (communication_id, recipient_id, event_type, details) VALUES (:communication_id, :recipient_id, :event_type, :details)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':recipient_id' => $recipientId,
            ':event_type' => $data['event_type'] ?? $data['action'] ?? 'log',
            ':details' => isset($data['details']) ? json_encode($data['details']) : null
        ]);
        return $this->getLog($this->db->lastInsertId());
    }
    public function getLog($id)
    {
        $sql = "SELECT * FROM communication_logs WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['details'])) {
            $row['details'] = json_decode($row['details'], true);
        }
        return $row;
    }
    public function listLogs($filters = [])
    {
        $sql = "SELECT * FROM communication_logs WHERE 1=1";
        $params = [];
        foreach (["communication_id", "recipient_id", "event_type"] as $col) {
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
            if (isset($row['details'])) {
                $row['details'] = json_decode($row['details'], true);
            }
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

    // Templates CRUD
    public function createTemplate($data)
    {
        // Map channel to template_type
        $templateType = $data['template_type'] ?? $data['channel'] ?? 'sms';
        $typeMap = [
            'sms' => 'sms',
            'email' => 'email',
            'announcement' => 'announcement',
            'internal' => 'internal_message',
            'internal_message' => 'internal_message',
            'message' => 'email'
        ];
        $templateType = $typeMap[$templateType] ?? 'sms';

        // Get template body from content or template_body or message field
        $templateBody = $data['template_body'] ?? $data['content'] ?? $data['message'] ?? 'No template body';

        $sql = "INSERT INTO communication_templates (name, template_type, category, subject, template_body, variables_json, example_output, created_by, status, usage_count) VALUES (:name, :template_type, :category, :subject, :template_body, :variables_json, :example_output, :created_by, :status, :usage_count)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'] ?? 'Unnamed Template',
            ':template_type' => $templateType,
            ':category' => $data['category'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':template_body' => $templateBody,
            ':variables_json' => isset($data['variables_json']) ? json_encode($data['variables_json']) : null,
            ':example_output' => $data['example_output'] ?? null,
            ':created_by' => $data['created_by'] ?? 1,
            ':status' => $data['status'] ?? 'active',
            ':usage_count' => $data['usage_count'] ?? 0
        ]);
        return $this->getTemplate($this->db->lastInsertId());
    }
    public function getTemplate($id)
    {
        $sql = "SELECT * FROM communication_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['variables_json'])) {
            $row['variables_json'] = json_decode($row['variables_json'], true);
        }
        return $row;
    }
    public function updateTemplate($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (["name", "template_type", "category", "subject", "template_body", "variables_json", "example_output", "status", "usage_count"] as $col) {
            if (isset($data[$col])) {
                $fields[] = "$col = :$col";
                $params[":$col"] = $col === 'variables_json' ? json_encode($data[$col]) : $data[$col];
            }
        }
        if (!$fields) {
            throw new \Exception("No fields to update");
        }
        $sql = "UPDATE communication_templates SET " . implode(", ", $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $sql = preg_replace('/,\s*,/', ',', $sql);
        $sql = str_replace('SET ,', 'SET ', $sql);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $this->getTemplate($id);
    }
    public function deleteTemplate($id)
    {
        $sql = "DELETE FROM communication_templates WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    public function listTemplates($filters = [])
    {
        $sql = "SELECT * FROM communication_templates WHERE 1=1";
        $params = [];
        foreach (["template_type", "category", "status", "created_by"] as $col) {
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
            if (isset($row['variables_json'])) {
                $row['variables_json'] = json_decode($row['variables_json'], true);
            }
        }
        return $rows;
    }

}
