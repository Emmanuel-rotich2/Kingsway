<?php
namespace App\API\Modules\communications;

use PDO;

class StaffForumManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createForumTopic($data)
    {
        $sql = "INSERT INTO forum_threads (title, created_by, status, forum_type, created_at) VALUES (:title, :created_by, 'open', 'staff', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'] ?? 'Untitled Topic',
            ':created_by' => $data['created_by'] ?? 1
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumTopic($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_threads WHERE id = ? AND forum_type = 'staff'");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumTopic($id, $data)
    {
        $sql = "UPDATE forum_threads SET title = :title, updated_at = NOW() WHERE id = :id AND forum_type = 'staff'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumTopic($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_threads WHERE id = ? AND forum_type = 'staff'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumTopics($filters = [])
    {
        $sql = "SELECT * FROM forum_threads WHERE forum_type = 'staff'";
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

    public function createForumPost($topicId, $data)
    {
        $sql = "INSERT INTO forum_posts (thread_id, content, created_by, status, created_at) VALUES (:thread_id, :content, :created_by, 'visible', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':thread_id' => $topicId,
            ':content' => $data['content'],
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumPost($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumPost($id, $data)
    {
        $sql = "UPDATE forum_posts SET content = :content, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':content' => $data['content'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumPost($id)
    {
        $stmt = $this->db->prepare("DELETE FROM forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumPosts($topicId)
    {
        $stmt = $this->db->prepare("SELECT * FROM forum_posts WHERE thread_id = ? ORDER BY created_at ASC");
        $stmt->execute([$topicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
