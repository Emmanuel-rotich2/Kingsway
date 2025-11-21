<?php
namespace App\API\Modules\Communications;

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
        $sql = "INSERT INTO contact_directory (name, phone, email, type, status, created_by, created_at) VALUES (:name, :phone, :email, :type, 'draft', :created_by, NOW())";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':type' => $data['type'],
            ':created_by' => $data['created_by']
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
        $sql = "UPDATE contact_directory SET name = :name, phone = :phone, email = :email, type = :type, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':type' => $data['type'],
            ':id' => $id
        ]);
        return $stmt->rowCount() > 0;
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
        if (isset($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }
        if (isset($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
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
