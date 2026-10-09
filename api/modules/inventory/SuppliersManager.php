<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

/**
 * Suppliers Manager
 * 
 * Manages supplier operations and relationships
 */
class SuppliersManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listSuppliers($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE supplier_name LIKE ? OR email LIKE ? OR phone LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm, $searchTerm];
            }

            $sql = "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("suppliers") . " $where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    s.*,
                    COUNT(DISTINCT po.id) as total_orders,
                    SUM(po.total_amount) as total_purchase_value
                FROM " . ReadReplicaService::qualifiedRef("suppliers") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("purchase_orders") . " po ON s.id = po.supplier_id
                $where
                GROUP BY s.id
                ORDER BY s.$sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'suppliers' => $suppliers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSupplier($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return formatResponse(false, null, 'Supplier not found', 404);
            }

            // Get recent purchase orders
            $stmt = $this->db->prepare("
                SELECT * FROM purchase_orders 
                WHERE supplier_id = ? 
                ORDER BY order_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $supplier['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $supplier);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSupplier($data)
    {
        try {
            $required = ['supplier_name', 'phone'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $sql = "
                INSERT INTO suppliers (
                    name, contact_person, email, phone, address, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['supplier_name'] ?? $data['name'],
                $data['contact_person'] ?? null,
                $data['email'] ?? null,
                $data['phone'],
                $data['address'] ?? null
            ]);

            $supplierId = $this->db->lastInsertId();
            $this->logAction('create', $supplierId, "Created supplier: {$data['supplier_name']}");

            return formatResponse(true, ['id' => $supplierId], 'Supplier created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateSupplier($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, supplier_name FROM " . ReadReplicaService::qualifiedRef("suppliers") . " id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return formatResponse(false, null, 'Supplier not found', 404);
            }

            $updates = [];
            $params = [];

            $fieldMap = [
                'supplier_name' => 'name',
                'name' => 'name',
                'contact_person' => 'contact_person',
                'email' => 'email',
                'phone' => 'phone',
                'address' => 'address',
                'status' => 'status'
            ];

            foreach ($fieldMap as $key => $column) {
                if (isset($data[$key]) && !in_array("$column = ?", $updates, true)) {
                    $updates[] = "$column = ?";
                    $params[] = $data[$key];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated supplier: {$supplier['supplier_name']}");

            return formatResponse(true, null, 'Supplier updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Soft-delete (deactivate) a supplier.
     */
    public function deleteSupplier($id, $userId)
    {
        try {
            $stmt = $this->db->prepare("UPDATE suppliers SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('deactivate', $id, "Deactivated supplier #{$id}");

            return formatResponse(true, ['id' => $id], 'Supplier deactivated successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Aggregate outstanding liabilities per supplier from open purchase orders.
     */
    public function getOutstandingLiabilities()
    {
        try {
            $sql = "
                SELECT
                    s.id              AS vendor_id,
                    s.supplier_name   AS vendor,
                    COALESCE(SUM(CASE WHEN po.status NOT IN ('received','cancelled') THEN po.total_amount ELSE 0 END), 0) AS outstanding
                FROM " . ReadReplicaService::qualifiedRef("suppliers") . " s
                LEFT JOIN " . ReadReplicaService::qualifiedRef("purchase_orders") . " po ON po.supplier_id = s.id
                GROUP BY s.id, s.supplier_name
                ORDER BY outstanding DESC
            ";
            $stmt = $this->db->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return formatResponse(true, ['outstanding' => $rows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    /** Approved supplier expenses with an outstanding payable amount (+ verified payout accounts). */
    public function supplierPayables(): array
    {
        $stmt = $this->db->query(
            "SELECT e.id AS expense_id, e.vendor_id AS supplier_id,
                    s.name AS supplier_name, e.description, e.reference_number,
                    e.amount AS expense_amount, e.status, e.created_at,
                    COALESCE(SUM(CASE WHEN spr.status IN ('payment_pending','paid') THEN spr.amount ELSE 0 END), 0) AS paid_or_pending,
                    e.amount - COALESCE(SUM(CASE WHEN spr.status IN ('payment_pending','paid') THEN spr.amount ELSE 0 END), 0) AS outstanding_amount
             FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
             JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON s.id = e.vendor_id
             LEFT JOIN supplier_payment_requests spr ON spr.expense_id = e.id
             WHERE e.vendor_id IS NOT NULL AND e.status IN ('approved','payment_pending')
             GROUP BY e.id, e.vendor_id, s.name, e.description, e.reference_number, e.amount, e.status, e.created_at
             HAVING outstanding_amount > 0.009
             ORDER BY e.created_at ASC, e.id ASC"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $bank = $this->db->query("SELECT id, supplier_id, bank_name, bank_code, account_name, account_number, currency, is_primary FROM supplier_bank_accounts WHERE active = 1 AND verification_status = 'verified' ORDER BY is_primary DESC, id DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $mobile = $this->db->query("SELECT id, supplier_id, provider, phone_number, account_name, is_primary FROM supplier_mobile_accounts WHERE active = 1 AND verification_status = 'verified' ORDER BY is_primary DESC, id DESC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $banks = $mobiles = [];
        foreach ($bank as $account) $banks[(int) $account['supplier_id']][] = $account;
        foreach ($mobile as $account) $mobiles[(int) $account['supplier_id']][] = $account;
        foreach ($rows as &$row) {
            $supplierId = (int) $row['supplier_id'];
            $row['expense_id'] = (int) $row['expense_id'];
            $row['outstanding_amount'] = (float) $row['outstanding_amount'];
            $row['bank_accounts'] = $banks[$supplierId] ?? [];
            $row['mobile_accounts'] = $mobiles[$supplierId] ?? [];
        }
        unset($row);
        return $rows;
    }

    /** Verified bank accounts for a supplier (payout account pickers). */
    public function bankAccountsFor(int $supplierId): array
    {
        $stmt = $this->db->prepare("SELECT id, bank_name, bank_code, account_name, account_number, currency, is_primary, verification_status, active FROM supplier_bank_accounts WHERE supplier_id = ? ORDER BY is_primary DESC, id DESC");
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Verified mobile (M-Pesa) accounts for a supplier. */
    public function mobileAccountsFor(int $supplierId): array
    {
        $stmt = $this->db->prepare("SELECT id, provider, phone_number, account_name, is_primary, verification_status, active FROM supplier_mobile_accounts WHERE supplier_id = ? ORDER BY is_primary DESC, id DESC");
        $stmt->execute([$supplierId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Save a new bank account pending verification. Returns the new id. */
    public function createBankAccount(int $supplierId, array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO supplier_bank_accounts (supplier_id, bank_name, bank_code, account_name, account_number, currency, is_primary, verification_status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$supplierId, $data['bank_name'] ?? '', $data['bank_code'] ?? null, $data['account_name'] ?? '', $data['account_number'] ?? '', $data['currency'] ?? 'KES', !empty($data['is_primary']) ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    /** Save a new M-Pesa account pending verification. Returns the new id. */
    public function createMobileAccount(int $supplierId, array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO supplier_mobile_accounts (supplier_id, provider, phone_number, account_name, is_primary, verification_status) VALUES (?, 'mpesa', ?, ?, ?, 'pending')");
        $stmt->execute([$supplierId, $data['phone_number'] ?? '', $data['account_name'] ?? '', !empty($data['is_primary']) ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    /** Update verification/primary/active flags on a supplier payout account. */
    public function updatePaymentAccount(string $table, int $id, array $fields): void
    {
        if (!in_array($table, ['supplier_bank_accounts', 'supplier_mobile_accounts'], true)) {
            throw new \InvalidArgumentException('Payment account ID required');
        }
        $clauses = [];
        $params = [];
        if (isset($fields['verification_status']) && in_array($fields['verification_status'], ['unverified', 'pending', 'verified', 'rejected'], true)) { $clauses[] = 'verification_status = ?'; $params[] = $fields['verification_status']; }
        if (array_key_exists('active', $fields)) { $clauses[] = 'active = ?'; $params[] = !empty($fields['active']) ? 1 : 0; }
        if (array_key_exists('is_primary', $fields)) { $clauses[] = 'is_primary = ?'; $params[] = !empty($fields['is_primary']) ? 1 : 0; }
        if ($clauses === []) { throw new \InvalidArgumentException('No supported account fields supplied.'); }
        $params[] = $id;
        $this->db->prepare("UPDATE {$table} SET " . implode(', ', $clauses) . ", updated_at = NOW() WHERE id = ?")->execute($params);
    }

}
