<?php
namespace App\API\Modules\Finance;

use PDO;
use Exception;

class DepartmentBudgetManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Submit a new budget proposal
    public function submitProposal($data)
    {
        $sql = "INSERT INTO department_budget_proposals (department_id, title, description, amount_requested, status, created_by) VALUES (?, ?, ?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['department_id'],
            $data['title'],
            $data['description'],
            $data['amount_requested'],
            $data['created_by']
        ]);
        return $this->db->lastInsertId();
    }

    // List proposals (optionally filter by department or status)
    public function listProposals($filters = [])
    {
        $sql = "SELECT * FROM department_budget_proposals WHERE 1=1";
        $params = [];
        if (!empty($filters['department_id'])) {
            $sql .= " AND department_id = ?";
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve or reject a proposal
    public function updateProposalStatus($proposalId, $status, $reviewedBy)
    {
        $sql = "UPDATE department_budget_proposals SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $reviewedBy, $proposalId]);
        return $stmt->rowCount();
    }

    // Allocate funds to a department account
    public function allocateFunds($departmentId, $amount, $allocatedBy)
    {
        $sql = "INSERT INTO department_accounts (department_id, amount, allocated_by) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$departmentId, $amount, $allocatedBy]);
        return $this->db->lastInsertId();
    }

    // Request funds (loan/overdraft)
    public function requestFund($data)
    {
        $sql = "INSERT INTO department_fund_requests (department_id, amount, reason, status, requested_by) VALUES (?, ?, ?, 'pending', ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['department_id'],
            $data['amount'],
            $data['reason'],
            $data['requested_by']
        ]);
        return $this->db->lastInsertId();
    }

    // List fund requests
    public function listFundRequests($filters = [])
    {
        $sql = "SELECT * FROM department_fund_requests WHERE 1=1";
        $params = [];
        if (!empty($filters['department_id'])) {
            $sql .= " AND department_id = ?";
            $params[] = $filters['department_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        $sql .= " ORDER BY requested_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Approve/reject fund request
    public function updateFundRequestStatus($requestId, $status, $reviewedBy)
    {
        $sql = "UPDATE department_fund_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $reviewedBy, $requestId]);
        return $stmt->rowCount();
    }
}
