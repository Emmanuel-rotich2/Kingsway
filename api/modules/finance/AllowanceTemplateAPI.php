<?php

namespace App\API\Modules\finance;

use App\API\Services\ReadReplicaService;
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
 * Live home: `staff_allowances`. Templates are stored as rows with the
 * sentinel `staff_id = 0`; applying a template bulk-creates real
 * `staff_allowances` rows for matching staff.
 */
class AllowanceTemplateAPI extends BaseAPI
{
    private const TEMPLATE_STAFF_ID = 0;

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
            $where = "WHERE sa.staff_id = " . self::TEMPLATE_STAFF_ID;
            $bindings = [];

            if (!empty($params['status'])) {
                $where .= " AND sa.status = ?";
                $bindings[] = $params['status'];
            }
            if (!empty($params['allowance_type'])) {
                $where .= " AND sa.allowance_type = ?";
                $bindings[] = $params['allowance_type'];
            }

            $sql = "SELECT
                        sa.id, sa.name, sa.description, sa.allowance_type, sa.amount,
                        sa.is_taxable, sa.is_recurring, sa.effective_date, sa.start_date,
                        sa.end_date, sa.status, sa.created_at, sa.updated_at
                    FROM staff_allowances sa
                    $where
                    ORDER BY sa.created_at DESC";

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
            $sql = "SELECT id, name, description, allowance_type, amount,
                           is_taxable, is_recurring, effective_date, start_date,
                           end_date, status, created_at, updated_at
                    FROM staff_allowances
                    WHERE id = ? AND staff_id = " . self::TEMPLATE_STAFF_ID;
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

            $sql = "INSERT INTO staff_allowances
                        (staff_id, name, description, allowance_type, amount, is_taxable,
                         is_recurring, effective_date, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                self::TEMPLATE_STAFF_ID,
                $data['name'],
                $data['description'] ?? null,
                $this->mapAllowanceType($data['allowance_type']),
                $data['amount'],
                $data['is_taxable'] ?? 1,
                $data['is_recurring'] ?? 1,
                $data['effective_date'] ?? date('Y-m-d'),
                $data['start_date'] ?? null,
                $data['end_date'] ?? null,
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
                          'is_recurring', 'effective_date', 'start_date', 'end_date', 'status'];

            foreach ($updatable as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'allowance_type') {
                        $data[$field] = $this->mapAllowanceType($data[$field]);
                    }
                    $fields[] = "$field = ?";
                    $bindings[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $fields[] = "updated_at = NOW()";
            $bindings[] = $id;

            $sql = "UPDATE staff_allowances SET " . implode(', ', $fields) . " WHERE id = ? AND staff_id = " . self::TEMPLATE_STAFF_ID;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);

            $this->logAction('update', $id, "Updated allowance template");

            return formatResponse(true, ['id' => $id], 'Template updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Deactivate an allowance template (status has no 'inactive' — use 'cancelled').
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE staff_allowances SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND staff_id = " . self::TEMPLATE_STAFF_ID);
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
            $stmt = $this->db->prepare("SELECT * FROM staff_allowances WHERE id = ? AND staff_id = " . self::TEMPLATE_STAFF_ID);
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return formatResponse(false, null, 'Template not found', 404);
            }

            $sql = "SELECT
                        s.staff_id AS id, s.staff_no,
                        s.full_name,
                        s.contract_type, s.position
                    FROM " . ReadReplicaService::qualifiedRef('staff_directory') . " s
                    WHERE s.staff_status = 'active'
                    ORDER BY s.staff_no";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
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
            $stmt = $this->db->prepare("SELECT * FROM staff_allowances WHERE id = ? AND staff_id = " . self::TEMPLATE_STAFF_ID);
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
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
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
                    $template['is_recurring'],
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

    private function mapAllowanceType($type)
    {
        $valid = ['housing', 'transport', 'medical', 'hardship', 'responsibility', 'overtime', 'bonus', 'other'];
        $type = strtolower((string) $type);
        return in_array($type, $valid, true) ? $type : 'other';
    }
}
