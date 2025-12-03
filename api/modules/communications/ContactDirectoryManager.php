<?php
namespace App\API\Modules\communications;

use PDO;

class ContactDirectoryManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for contact_directory
    public function createContact($data)
    {
        $sql = "INSERT INTO contact_directory (name, phone, email, contact_type, department, role, notes, created_at) VALUES (:name, :phone, :email, :contact_type, :department, :role, :notes, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':contact_type' => $data['type'] ?? $data['contact_type'] ?? 'external',
            ':department' => $data['department'] ?? null,
            ':role' => $data['role'] ?? null,
            ':notes' => $data['notes'] ?? null
        ]);
        return $this->db->lastInsertId();
    }

    public function getContact($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM contact_directory WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateContact($id, $data)
    {
        $fields = [];
        $params = [':id' => $id];
        foreach (['name', 'phone', 'email', 'contact_type', 'department', 'role', 'notes'] as $col) {
            if (isset($data[$col]) || (isset($data['type']) && $col === 'contact_type')) {
                $fields[] = "$col = :$col";
                if ($col === 'contact_type') {
                    $params[":$col"] = $data['type'] ?? $data['contact_type'] ?? null;
                } else {
                    $params[":$col"] = $data[$col] ?? null;
                }
            }
        }
        if (!$fields) {
            return false;
        }
        $sql = "UPDATE contact_directory SET " . implode(", ", $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteContact($id)
    {
        $stmt = $this->db->prepare("DELETE FROM contact_directory WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function listContacts($filters = [])
    {
        $sql = "SELECT * FROM contact_directory WHERE 1=1";
        $params = [];
        if (isset($filters['type']) || isset($filters['contact_type'])) {
            $sql .= " AND contact_type = :contact_type";
            $params[':contact_type'] = $filters['type'] ?? $filters['contact_type'];
        }
        if (isset($filters['department'])) {
            $sql .= " AND department = :department";
            $params[':department'] = $filters['department'];
        }
        $sql .= " ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approval workflow
    public function submitForReview($id)
    {
        $sql = "UPDATE contact_directory SET status = 'pending_review', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        // Optionally: notify reviewers
        return $stmt->rowCount() > 0;
    }

    public function approveContact($id, $adminId)
    {
        $sql = "UPDATE contact_directory SET status = 'approved', approved_by = :adminId, approved_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':adminId' => $adminId, ':id' => $id]);
        // Optionally: notify contact owner
        return $stmt->rowCount() > 0;
    }

    public function rejectContact($id, $adminId)
    {
        $sql = "UPDATE contact_directory SET status = 'rejected', approved_by = :adminId, approved_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':adminId' => $adminId, ':id' => $id]);
        // Optionally: notify contact owner
        return $stmt->rowCount() > 0;
    }
}
