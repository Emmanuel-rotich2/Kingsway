<?php

namespace App\API\Modules\finance;

require_once __DIR__ . '/../../includes/BaseAPI.php';

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Allowance Template API
 *
 * Manages predefined allowance templates that can be bulk-applied to staff
 * based on department, staff type, role, or contract type criteria.
 *
 * Flow: Template created → Preview matching staff → Apply → Creates staff_allowances rows
 *       → Payroll processing reads them automatically via existing allowance queries.
 */
class AllowanceTemplateAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('finance');
    }

    /**
     * List all allowance templates with optional filters.
     */
    public function list($params = [])
    {
        try {
            $where = "WHERE 1=1";
            $bindings = [];

            if (!empty($params['status'])) {
                $where .= " AND at.status = ?";
                $bindings[] = $params['status'];
            }
            if (!empty($params['allowance_type'])) {
                $where .= " AND at.allowance_type = ?";
                $bindings[] = $params['allowance_type'];
            }
            if (!empty($params['department_id'])) {
                $where .= " AND at.department_id = ?";
                $bindings[] = $params['department_id'];
            }

            $sql = "SELECT
                        at.*,
                        d.name AS department_name,
                        st.name AS staff_type_name,
                        r.name AS role_name,
                        -- Count how many staff currently match this template
                        (SELECT COUNT(*) FROM staff s
                         WHERE s.status = 'active'
                           AND (at.department_id IS NULL OR s.department_id = at.department_id)
                           AND (at.staff_type_id IS NULL OR s.staff_type_id = at.staff_type_id)
                           AND (at.contract_type IS NULL OR s.contract_type = at.contract_type)
                           AND (at.role_id IS NULL OR EXISTS (
                               SELECT 1 FROM user_roles ur WHERE ur.user_id = s.user_id AND ur.role_id = at.role_id
                           ))
                        ) AS matching_staff_count
                    FROM allowance_templates at
                    LEFT JOIN departments d ON at.department_id = d.id
                    LEFT JOIN staff_types st ON at.staff_type_id = st.id
                    LEFT JOIN roles r ON at.role_id = r.id
                    $where
                    ORDER BY at.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $templates);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get a single allowance template by ID.
     */
    public function get($id)
    {
        try {
            $sql = "SELECT
                        at.*,
                        d.name AS department_name,
                        st.name AS staff_type_name,
                        r.name AS role_name
                    FROM allowance_templates at
                    LEFT JOIN departments d ON at.department_id = d.id
                    LEFT JOIN staff_types st ON at.staff_type_id = st.id
                    LEFT JOIN roles r ON at.role_id = r.id
                    WHERE at.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return formatResponse(false, null, 'Template not found', 404);
            }

            return formatResponse(true, $template);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new allowance template.
     */
    public function create($data)
    {
        try {
            $required = ['name', 'allowance_type', 'amount'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $sql = "INSERT INTO allowance_templates
                        (name, description, allowance_type, amount, is_taxable,
                         department_id, staff_type_id, role_id, contract_type, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['allowance_type'],
                $data['amount'],
                $data['is_taxable'] ?? 1,
                $data['department_id'] ?? null,
                $data['staff_type_id'] ?? null,
                $data['role_id'] ?? null,
                $data['contract_type'] ?? null,
                $data['status'] ?? 'active',
            ]);

            $templateId = (int) $this->db->lastInsertId();

            $this->logAction('create', $templateId, "Created allowance template: {$data['name']}");

            return formatResponse(true, ['id' => $templateId], 'Allowance template created');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update an existing allowance template.
     */
    public function update($id, $data)
    {
        try {
            $fields = [];
            $bindings = [];

            $updatable = ['name', 'description', 'allowance_type', 'amount', 'is_taxable',
                          'department_id', 'staff_type_id', 'role_id', 'contract_type', 'status'];

            foreach ($updatable as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $bindings[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $fields[] = "updated_at = NOW()";
            $bindings[] = $id;

            $sql = "UPDATE allowance_templates SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $this->logAction('update', $id, "Updated allowance template");

            return formatResponse(true, ['id' => $id], 'Template updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Soft-delete an allowance template (set status to inactive).
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE allowance_templates SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Deactivated allowance template");

            return formatResponse(true, null, 'Template deactivated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Preview which staff members match a template's criteria.
     */
    public function getApplicableStaff($templateId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM allowance_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return formatResponse(false, null, 'Template not found', 404);
            }

            $sql = "SELECT
                        s.id, s.staff_no, s.first_name, s.last_name,
                        d.name AS department_name,
                        st.name AS staff_type_name,
                        s.contract_type
                    FROM staff s
                    LEFT JOIN departments d ON s.department_id = d.id
                    LEFT JOIN staff_types st ON s.staff_type_id = st.id
                    WHERE s.status = 'active'";

            $bindings = [];

            if ($template['department_id']) {
                $sql .= " AND s.department_id = ?";
                $bindings[] = $template['department_id'];
            }
            if ($template['staff_type_id']) {
                $sql .= " AND s.staff_type_id = ?";
                $bindings[] = $template['staff_type_id'];
            }
            if ($template['contract_type']) {
                $sql .= " AND s.contract_type = ?";
                $bindings[] = $template['contract_type'];
            }
            if ($template['role_id']) {
                $sql .= " AND EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = s.user_id AND ur.role_id = ?)";
                $bindings[] = $template['role_id'];
            }

            $sql .= " ORDER BY s.staff_no";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'template' => $template,
                'matching_staff' => $staff,
                'count' => count($staff),
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Apply a template to matching staff — bulk-creates staff_allowances rows.
     * Optionally pass specific staff_ids to apply only to a subset.
     */
    public function applyToStaff($templateId, $staffIds = null)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM allowance_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return formatResponse(false, null, 'Template not found', 404);
            }
            if ($template['status'] !== 'active') {
                return formatResponse(false, null, 'Cannot apply an inactive template');
            }

            // Find matching staff
            if ($staffIds && is_array($staffIds)) {
                $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
                $sql = "SELECT id FROM staff WHERE status = 'active' AND id IN ($placeholders)";
                $bindings = $staffIds;
            } else {
                $sql = "SELECT id FROM staff WHERE status = 'active'";
                $bindings = [];

                if ($template['department_id']) {
                    $sql .= " AND department_id = ?";
                    $bindings[] = $template['department_id'];
                }
                if ($template['staff_type_id']) {
                    $sql .= " AND staff_type_id = ?";
                    $bindings[] = $template['staff_type_id'];
                }
                if ($template['contract_type']) {
                    $sql .= " AND contract_type = ?";
                    $bindings[] = $template['contract_type'];
                }
                if ($template['role_id']) {
                    $sql .= " AND EXISTS (SELECT 1 FROM user_roles ur WHERE ur.user_id = staff.user_id AND ur.role_id = ?)";
                    $bindings[] = $template['role_id'];
                }
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $matchedStaff = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($matchedStaff)) {
                return formatResponse(false, null, 'No matching staff found for this template');
            }

            $this->db->beginTransaction();

            $inserted = 0;
            $skipped = 0;
            $today = date('Y-m-d');

            $insertStmt = $this->db->prepare("
                INSERT INTO staff_allowances
                    (staff_id, name, description, allowance_type, amount, is_taxable, is_recurring, effective_date, status)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?, 'active')
            ");

            foreach ($matchedStaff as $staffId) {
                // Skip if this staff already has the same active allowance from this template
                $checkStmt = $this->db->prepare("
                    SELECT id FROM staff_allowances
                    WHERE staff_id = ? AND name = ? AND allowance_type = ? AND status = 'active'
                    LIMIT 1
                ");
                $checkStmt->execute([$staffId, $template['name'], $template['allowance_type']]);
                if ($checkStmt->fetch()) {
                    $skipped++;
                    continue;
                }

                $insertStmt->execute([
                    $staffId,
                    $template['name'],
                    $template['description'],
                    $template['allowance_type'],
                    $template['amount'],
                    $template['is_taxable'],
                    $today,
                ]);
                $inserted++;
            }

            $this->db->commit();

            $this->logAction('apply', $templateId, "Applied template '{$template['name']}' to $inserted staff (skipped $skipped duplicates)");

            return formatResponse(true, [
                'template_id' => $templateId,
                'matching_staff' => count($matchedStaff),
                'inserted' => $inserted,
                'skipped_duplicates' => $skipped,
            ], "Applied to $inserted staff members" . ($skipped > 0 ? " ($skipped already had this allowance)" : ''));
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }
}
