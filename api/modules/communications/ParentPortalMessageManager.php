<?php
namespace App\API\Modules\Communications;

use PDO;

class ParentPortalMessageManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for parent_portal_messages
    public function createMessage($data)
    {
        $sql = "INSERT INTO parent_portal_messages (parent_id, subject, message, status, created_by, created_at) VALUES (:parent_id, :subject, :message, 'draft', :created_by, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':parent_id' => $data['parent_id'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':created_by' => $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getMessage($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM parent_portal_messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateMessage($id, $data)
    {
        $sql = "UPDATE parent_portal_messages SET subject = :subject, message = :message, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteMessage($id)
    {
        $stmt = $this->db->prepare("DELETE FROM parent_portal_messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listMessages($filters = [])
    {
        $sql = "SELECT * FROM parent_portal_messages WHERE 1=1";
        $params = [];
        if (isset($filters['parent_id'])) {
            $sql .= " AND parent_id = :parent_id";
            $params[':parent_id'] = $filters['parent_id'];
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

    // Workflow methods
    public function submitForReview($id)
    {
        $sql = "UPDATE parent_portal_messages SET status = 'pending_review', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        // Optionally: notify reviewers
        return $stmt->rowCount() > 0;
    }

    public function approveMessage($id, $reviewerId)
    {
        $sql = "UPDATE parent_portal_messages SET status = 'approved', reviewed_by = :reviewerId, reviewed_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':reviewerId' => $reviewerId, ':id' => $id]);
        // Optionally: notify parent
        return $stmt->rowCount() > 0;
    }

    public function sendMessage($id)
    {
        $sql = "UPDATE parent_portal_messages SET status = 'sent', sent_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        // Optionally: trigger delivery via mail/SMS
        return $stmt->rowCount() > 0;
    }

    public function archiveMessage($id)
    {
        $sql = "UPDATE parent_portal_messages SET status = 'archived', archived_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function replyToMessage($id, $replyData)
    {
        $sql = "INSERT INTO parent_portal_message_replies (message_id, reply, replied_by, created_at) VALUES (:message_id, :reply, :replied_by, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':message_id' => $id,
            ':reply' => $replyData['reply'],
            ':replied_by' => $replyData['replied_by']
        ]);
        return $this->db->lastInsertId();
    }

    public function getThread($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM parent_portal_messages WHERE id = ?");
        $stmt->execute([$id]);
        $message = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$message) {
            return null;
        }
        $stmt2 = $this->db->prepare("SELECT * FROM parent_portal_message_replies WHERE message_id = ? ORDER BY created_at ASC");
        $stmt2->execute([$id]);
        $replies = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $message['replies'] = $replies;
        return $message;
    }
}
