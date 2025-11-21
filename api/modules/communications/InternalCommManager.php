<?php
namespace App\API\Modules\Communications;

use PDO;

/**
 * Internal staff communication manager: handles requests, announcements, and forum discussions for staff.
 */
class InternalCommManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Staff requests (leave, IT, maintenance, etc.)
    public function createRequest($data)
    {
        $sql = "INSERT INTO internal_requests (type, subject, details, requested_by, status, created_at) VALUES (:type, :subject, :details, :requested_by, 'pending', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':type' => $data['type'],
            ':subject' => $data['subject'],
            ':details' => $data['details'],
            ':requested_by' => $data['requested_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getRequest($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateRequest($id, $data)
    {
        $sql = "UPDATE internal_requests SET type = :type, subject = :subject, details = :details, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':type' => $data['type'],
            ':subject' => $data['subject'],
            ':details' => $data['details'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteRequest($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listRequests($filters = [])
    {
        $sql = "SELECT * FROM internal_requests WHERE 1=1";
        $params = [];
        if (isset($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Internal announcements
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

    // Forum topics/discussions
    public function createForumTopic($data)
    {
        $sql = "INSERT INTO internal_forum_topics (title, created_by, status, created_at) VALUES (:title, :created_by, 'open', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumTopic($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_forum_topics WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumTopic($id, $data)
    {
        $sql = "UPDATE internal_forum_topics SET title = :title, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':title' => $data['title'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumTopic($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_forum_topics WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumTopics($filters = [])
    {
        $sql = "SELECT * FROM internal_forum_topics WHERE 1=1";
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
        $sql = "INSERT INTO internal_forum_posts (topic_id, content, created_by, status, created_at) VALUES (:topic_id, :content, :created_by, 'visible', NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':topic_id' => $topicId,
            ':content' => $data['content'],
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getForumPost($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateForumPost($id, $data)
    {
        $sql = "UPDATE internal_forum_posts SET content = :content, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':content' => $data['content'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForumPost($id)
    {
        $stmt = $this->db->prepare("DELETE FROM internal_forum_posts WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listForumPosts($topicId)
    {
        $stmt = $this->db->prepare("SELECT * FROM internal_forum_posts WHERE topic_id = ? ORDER BY created_at ASC");
        $stmt->execute([$topicId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
