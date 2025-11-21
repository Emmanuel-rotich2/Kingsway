<?php
namespace App\API\Modules\Communications;

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
        // Assume recipient_id is phone/email/user id, channel is stored in communication_recipients
        $sql = "UPDATE communication_recipients SET status = 'opted_out' WHERE recipient_id = :recipient_id AND channel = :channel";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':recipient_id' => $recipientIdentifier, ':channel' => $channel]);
    }

    /**
     * Store incoming message (used for subscription/inbound callbacks)
     * @param array $data (should include sender, message, channel, received_at, etc)
     * @return bool
     */
    public function storeIncomingMessage($data)
    {
        $sql = "INSERT INTO communication_inbound (sender, message, channel, received_at, raw_data) VALUES (:sender, :message, :channel, :received_at, :raw_data)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':sender' => $data['sender'] ?? null,
            ':message' => $data['message'] ?? null,
            ':channel' => $data['channel'] ?? null,
            ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            ':raw_data' => isset($data['raw_data']) ? json_encode($data['raw_data']) : null
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
            $htmlBody = $service->renderEmail($subject, $body, $signature, $footer, '', $schoolDetails);
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

    // Communications CRUD
    public function createCommunication($data)
    {
        $sql = "INSERT INTO communications (sender_id, subject, content, type, status, priority, template_id, scheduled_at) VALUES (:sender_id, :subject, :content, :type, :status, :priority, :template_id, :scheduled_at)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $data['sender_id'] ?? null,
            ':subject' => $data['subject'],
            ':content' => $data['content'],
            ':type' => $data['type'],
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
        $sql = "INSERT INTO communication_attachments (communication_id, file_name, file_path) VALUES (:communication_id, :file_name, :file_path)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $communicationId,
            ':file_name' => $fileData['file_name'],
            ':file_path' => $fileData['file_path']
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
        $sql = "INSERT INTO communication_groups (name, description, type, created_by) VALUES (:name, :description, :type, :created_by)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':type' => $data['type'],
            ':created_by' => $data['created_by']
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
        $sql = "INSERT INTO communication_logs (communication_id, recipient_id, event_type, details) VALUES (:communication_id, :recipient_id, :event_type, :details)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $data['communication_id'],
            ':recipient_id' => $data['recipient_id'],
            ':event_type' => $data['event_type'],
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
        $sql = "INSERT INTO communication_recipients (communication_id, recipient_id, status, delivered_at, delivery_attempts, last_attempt_at, error_message, opened_at, clicked_at, device_info) VALUES (:communication_id, :recipient_id, :status, :delivered_at, :delivery_attempts, :last_attempt_at, :error_message, :opened_at, :clicked_at, :device_info)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':communication_id' => $data['communication_id'],
            ':recipient_id' => $data['recipient_id'],
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
        $sql = "INSERT INTO communication_templates (name, template_type, category, subject, template_body, variables_json, example_output, created_by, status, usage_count) VALUES (:name, :template_type, :category, :subject, :template_body, :variables_json, :example_output, :created_by, :status, :usage_count)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':template_type' => $data['template_type'],
            ':category' => $data['category'] ?? null,
            ':subject' => $data['subject'] ?? null,
            ':template_body' => $data['template_body'],
            ':variables_json' => isset($data['variables_json']) ? json_encode($data['variables_json']) : null,
            ':example_output' => $data['example_output'] ?? null,
            ':created_by' => $data['created_by'],
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
