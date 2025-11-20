<?php
namespace App\API\Modules\Communications;

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
        $sql = "INSERT INTO staff_requests (staff_id, type, subject, details, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['staff_id'],
            $data['type'],
            $data['subject'],
            $data['details']
        ]);
        $requestId = $this->db->lastInsertId();
        // Notify admin/IT/maintenance by email and SMS
        $this->notifyRequestCreated($requestId, $data);
        return $requestId;
    }

    public function getRequest($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM staff_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRequest($id, $data)
    {
        $sql = "UPDATE staff_requests SET type=?, subject=?, details=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['type'],
            $data['subject'],
            $data['details'],
            $data['status'],
            $id
        ]);
        // Notify requester of status change
        $this->notifyRequestUpdated($id, $data);
        return true;
    }

    public function deleteRequest($id)
    {
        $stmt = $this->db->prepare("DELETE FROM staff_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listRequests($filters = [])
    {
        $where = [];
        $params = [];
        if (!empty($filters['staff_id'])) {
            $where[] = 'staff_id = ?';
            $params[] = $filters['staff_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        $sql = "SELECT * FROM staff_requests";
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
