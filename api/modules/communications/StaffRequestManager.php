<?php
namespace App\API\Modules\communications;

use PDO;

class StaffRequestManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a staff request and notify relevant staff (e.g. admin/IT/maintenance)
     */
    public function createRequest($data)
    {
        // Map fields: accept both 'title' and 'subject', both 'body' and 'details'
        $title = $data['title'] ?? $data['subject'] ?? '';
        $body = $data['body'] ?? $data['details'] ?? '';
        $createdBy = $data['staff_id'] ?? $data['requested_by'] ?? $data['created_by'] ?? 1;

        // Create conversation in internal_conversations table
        $sql = "INSERT INTO internal_conversations (created_by, title, conversation_type, created_at) VALUES (:created_by, :title, 'one_on_one', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':created_by' => $createdBy,
            ':title' => $title
        ]);
        $conversationId = $this->db->lastInsertId();

        // If body is provided, also create initial message in internal_messages
        if (!empty($body)) {
            $msgSql = "INSERT INTO internal_messages (conversation_id, sender_id, subject, message_body, message_type, priority, status, created_at) VALUES (:conversation_id, :sender_id, :subject, :message_body, 'personal', 'normal', 'sent', NOW())";
            $msgStmt = $this->db->prepare($msgSql);
            $msgStmt->execute([
                ':conversation_id' => $conversationId,
                ':sender_id' => $createdBy,
                ':subject' => $title,
                ':message_body' => $body
            ]);
        }

        return $conversationId;
    }

    public function getRequest($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_conversations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRequest($id, $data)
    {
        // Map fields: accept both 'title' and 'subject', both 'body' and 'details'
        $title = isset($data['title']) ? $data['title'] : (isset($data['subject']) ? $data['subject'] : null);

        // Update the conversation
        $updateFields = [];
        $params = [':id' => $id];

        if ($title !== null) {
            $updateFields[] = "title = :title";
            $params[':title'] = $title;
        }
        if (!empty($updateFields)) {
            $sql = "UPDATE internal_conversations SET " . implode(", ", $updateFields) . ", updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }

        // If body is provided, add as a new message to the conversation
        $body = isset($data['body']) ? $data['body'] : (isset($data['details']) ? $data['details'] : null);
        if ($body !== null && !empty($body)) {
            $createdBy = $data['staff_id'] ?? $data['requested_by'] ?? $data['created_by'] ?? 1;
            $msgSql = "INSERT INTO internal_messages (conversation_id, sender_id, subject, message_body, message_type, status, created_at) VALUES (:conversation_id, :sender_id, :subject, :message_body, 'personal', 'sent', NOW())";
            $msgStmt = $this->db->prepare($msgSql);
            $msgStmt->execute([
                ':conversation_id' => $id,
                ':sender_id' => $createdBy,
                ':subject' => $title ?? 'Update',
                ':message_body' => $body
            ]);
        }

        return true;
    }

    public function deleteRequest($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_conversations WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listRequests($filters = [])
    {
        $where = [];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'created_by = ?';
            $params[] = $filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        $sql = "SELECT * FROM internal_conversations";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function notifyRequestCreated($requestId, $data)
    {
        // Load mail and SMS service
        $mail = new \App\API\Services\MessageService($this->db);
        $sms = new \App\API\Services\SMS\SMSGateway();
        // Fetch recipients (e.g. admin/IT/maintenance)
        $recipients = $this->getNotificationRecipients($data['type']);
        $subject = "New Staff Request: " . $data['subject'];
        $body = "A new staff request has been submitted.\nType: {$data['type']}\nDetails: {$data['details']}\n";
        $mail->sendEmail($recipients['emails'], $subject, $body);
        $sms->send($recipients['phones'], $subject);
    }

    private function notifyRequestUpdated($id, $data)
    {
        $mail = new \App\API\Services\MessageService($this->db);
        $sms = new \App\API\Services\SMS\SMSGateway();
        // Fetch requester
        $stmt = $this->db->prepare("SELECT s.email, s.phone FROM staff_requests r JOIN staff s ON r.staff_id = s.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $subject = "Your Staff Request Status Updated";
            $body = "Your request status is now: {$data['status']}\nSubject: {$data['subject']}\nDetails: {$data['details']}\n";
            $mail->sendEmail([$row['email']], $subject, $body);
            $sms->send([$row['phone']], $subject);
        }
    }

    private function getNotificationRecipients($type)
    {
        // Example: fetch admin/IT/maintenance emails/phones from DB or config
        // For demo, return static
        return [
            'emails' => ['admin@kingsway.ac.ke'],
            'phones' => ['+254700000001']
        ];
    }
}
