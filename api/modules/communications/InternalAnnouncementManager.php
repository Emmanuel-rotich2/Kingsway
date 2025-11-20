<?php
namespace App\API\Modules\Communications;

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
        $sql = "INSERT INTO internal_announcements (title, message, audience, created_by, status, scheduled_at, created_at) VALUES (:title, :message, :audience, :created_by, 'scheduled', :scheduled_at, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':audience' => $data['audience'],
            ':created_by' => $data['created_by'],
            ':scheduled_at' => $data['scheduled_at'] ?? null
        ]);
        // Optionally: trigger notification to audience
        return $this->db->lastInsertId();
    }

    public function getAnnouncement($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_announcements WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateAnnouncement($id, $data)
    {
        $sql = "UPDATE internal_announcements SET title = :title, message = :message, audience = :audience, scheduled_at = :scheduled_at, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':message' => $data['message'],
            ':audience' => $data['audience'],
            ':scheduled_at' => $data['scheduled_at'] ?? null,
            ':id' => $id
        ]);
        // Optionally: trigger update notification
        return $stmt->rowCount() > 0;
    }

    public function deleteAnnouncement($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_announcements WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listAnnouncements($filters = [])
    {
        $sql = "SELECT * FROM internal_announcements WHERE 1=1";
        $params = [];
        if (isset($filters['audience'])) {
            $sql .= " AND audience = :audience";
            $params[':audience'] = $filters['audience'];
        }
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY scheduled_at DESC, created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
