<?php
namespace App\API\Modules\Communications;

use PDO;

class ExternalInboundManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for external_inbound_messages
    public function createInbound($data)
    {
        $sql = "INSERT INTO external_inbound_messages (source, sender, message, status, received_at, data) VALUES (:source, :sender, :message, 'received', NOW(), :data)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':source' => $data['source'],
            ':sender' => $data['sender'],
            ':message' => $data['message'],
            ':data' => json_encode($data['data'] ?? [])
        ]);
        return $this->db->lastInsertId();
    }

    public function getInbound($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM external_inbound_messages WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['data'])) {
            $row['data'] = json_decode($row['data'], true);
        }
        return $row;
    }

    public function updateInbound($id, $data)
    {
        $sql = "UPDATE external_inbound_messages SET message = :message, data = :data, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':message' => $data['message'],
            ':data' => json_encode($data['data'] ?? []),
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteInbound($id)
    {
        $stmt = $this->db->prepare("DELETE FROM external_inbound_messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listInbounds($filters = [])
    {
        $sql = "SELECT * FROM external_inbound_messages WHERE 1=1";
        $params = [];
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['source'])) {
            $sql .= " AND source = :source";
            $params[':source'] = $filters['source'];
        }
        $sql .= " ORDER BY received_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (isset($row['data'])) {
                $row['data'] = json_decode($row['data'], true);
            }
        }
        return $rows;
    }

    // Workflow methods
    public function classifyInbound($id, $classification)
    {
        $sql = "UPDATE external_inbound_messages SET classification = :classification, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':classification' => $classification, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function processInbound($id, $action)
    {
        $sql = "UPDATE external_inbound_messages SET status = :action, processed_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':action' => $action, ':id' => $id]);
        // Optionally: trigger notification
        return $stmt->rowCount() > 0;
    }

    public function escalateInbound($id, $notes)
    {
        $sql = "UPDATE external_inbound_messages SET status = 'escalated', escalation_notes = :notes, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':notes' => $notes, ':id' => $id]);
        // Optionally: trigger escalation notification
        return $stmt->rowCount() > 0;
    }

    public function archiveInbound($id)
    {
        $sql = "UPDATE external_inbound_messages SET status = 'archived', archived_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
