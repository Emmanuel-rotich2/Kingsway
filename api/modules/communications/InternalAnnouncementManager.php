<?php
namespace App\API\Modules\communications;

use PDO;

class InternalAnnouncementManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createAnnouncement($data)
    {
        $sql = "INSERT INTO internal_messages (sender_id, subject, message_body, message_type, status, created_at) VALUES (:sender_id, :subject, :message_body, 'announcement', 'sent', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':sender_id' => $data['sender_id'] ?? $data['created_by'] ?? 1,
            ':subject' => $data['title'] ?? $data['subject'] ?? '',
            ':message_body' => $data['message'] ?? '',
        ]);
        return $this->db->lastInsertId();
    }

    public function getAnnouncement($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_messages WHERE id = ? AND message_type = 'announcement'");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAnnouncement($id, $data)
    {
        $sql = "UPDATE internal_messages SET subject = :subject, message_body = :message_body, updated_at = NOW() WHERE id = :id AND message_type = 'announcement'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':subject' => $data['title'] ?? $data['subject'] ?? null,
            ':message_body' => $data['message'] ?? null,
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteAnnouncement($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_messages WHERE id = ? AND message_type = 'announcement'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listAnnouncements($filters = [])
    {
        $sql = "SELECT * FROM internal_messages WHERE message_type = 'announcement'";
        $params = [];
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
