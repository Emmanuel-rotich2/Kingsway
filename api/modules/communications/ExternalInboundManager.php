<?php
namespace App\API\Modules\communications;

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
        $sql = "INSERT INTO external_inbound_messages (source_type, source_address, subject, body, status, received_at, processing_notes) VALUES (:source_type, :source_address, :subject, :body, 'pending', :received_at, :processing_notes)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':source_type' => $data['source_type'] ?? $data['channel'] ?? 'sms',
            ':source_address' => $data['source_address'] ?? $data['phone'] ?? $data['sender'] ?? '',
            ':subject' => $data['subject'] ?? null,
            ':body' => $data['body'] ?? $data['message'] ?? '',
            ':received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
            ':processing_notes' => isset($data['processing_notes']) ? json_encode($data['processing_notes']) : null
        ]);
        return $this->db->lastInsertId();
    }

    public function getInbound($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM external_inbound_messages WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['processing_notes'])) {
            $row['processing_notes'] = json_decode($row['processing_notes'], true);
        }
        return $row;
    }

    public function updateInbound($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        if (isset($data['body'])) {
            $fields[] = "body = :body";
            $params[':body'] = $data['body'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        if (isset($data['processing_notes'])) {
            $fields[] = "processing_notes = :processing_notes";
            $params[':processing_notes'] = json_encode($data['processing_notes']);
        }
        if (!$fields) {
            return false;
        }
        $sql = "UPDATE external_inbound_messages SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
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
        if (isset($filters['source_type'])) {
            $sql .= " AND source_type = :source_type";
            $params[':source_type'] = $filters['source_type'];
        }
        $sql .= " ORDER BY received_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            if (isset($row['processing_notes'])) {
                $row['processing_notes'] = json_decode($row['processing_notes'], true);
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
