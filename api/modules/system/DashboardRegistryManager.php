<?php
namespace App\API\Modules\system;

use App\API\Services\ReadReplicaService;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * DashboardRegistryManager - CRUD for the System Administrator dashboard and
 * widget registries.
 *
 * Dashboards are stored in the existing `dashboards` table (route key in
 * `name`, display name in `display_name`) with role links in `role_dashboards`
 * and landing routes in `routes_registry`. Widgets live in the `widgets` table
 * (see database/migrations/023_create_widgets_table.sql).
 */
class DashboardRegistryManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('dashboard_registry');
    }

    /**
     * List dashboards mapped to the registry UI shape:
     * id, key, name, description, component, status, role_name.
     */
    public function listDashboards($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['search'])) {
                $where[] = '(d.name LIKE ? OR d.display_name LIKE ? OR d.description LIKE ?)';
                $term = '%' . $params['search'] . '%';
                $bindings[] = $term;
                $bindings[] = $term;
                $bindings[] = $term;
            }
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = 'd.is_active = ?';
                $bindings[] = ($params['status'] === 'active') ? 1 : 0;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT d.id,
                       d.name AS `key`,
                       d.display_name AS name,
                       d.description,
                       d.domain,
                       d.route_id,
                       rr.url AS component,
                       d.is_active,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role_name
                FROM " . ReadReplicaService::qualifiedRef("dashboards") . " d
                LEFT JOIN " . ReadReplicaService::qualifiedRef("routes_registry") . " rr ON rr.id = d.route_id
                LEFT JOIN role_dashboards rd ON rd.dashboard_id = d.id
                LEFT JOIN roles r ON r.id = rd.role_id
                WHERE $whereClause
                GROUP BY d.id, rr.url
                ORDER BY d.display_name
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['status'] = $row['is_active'] ? 'active' : 'inactive';
                $row['role_name'] = $row['role_name'] ?: null;
                $row['component'] = $row['component'] ?: ($row['domain'] ?? null);
            }
            unset($row);

            return $this->successResponse($rows, 'Dashboards retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'Failed to list dashboards');
            return $this->errorResponse('Failed to load dashboards');
        }
    }

    public function createDashboard($data, $userId)
    {
        try {
            $key = trim($data['key'] ?? '');
            $name = trim($data['name'] ?? '');
            if ($key === '' || $name === '') {
                return $this->errorResponse('Dashboard key and name are required');
            }

            $exists = $this->db->prepare('SELECT id FROM dashboards WHERE name = ?');
            $exists->execute([$key]);
            if ($exists->fetch()) {
                return $this->errorResponse('A dashboard with this key already exists', 409);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO dashboards (name, display_name, description, domain, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $key,
                $name,
                $data['description'] ?? null,
                strtoupper($data['domain'] ?? 'SCHOOL') === 'SYSTEM' ? 'SYSTEM' : 'SCHOOL',
                ($data['status'] ?? 'active') === 'active' ? 1 : 0,
            ]);

            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Dashboard created', 201);
        } catch (Exception $e) {
            $this->logError($e, 'Failed to create dashboard');
            return $this->errorResponse('Failed to create dashboard');
        }
    }

    public function updateDashboard($id, $data)
    {
        try {
            $id = (int) ($data['id'] ?? $id);
            if ($id <= 0) {
                return $this->errorResponse('Dashboard ID is required');
            }

            $stmt = $this->db->prepare('SELECT id FROM dashboards WHERE id = ?');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->errorResponse('Dashboard not found', 404);
            }

            $updates = ['updated_at = NOW()'];
            $params = [];

            if (array_key_exists('key', $data)) {
                $updates[] = 'name = ?';
                $params[] = trim($data['key']);
            }
            if (array_key_exists('name', $data)) {
                $updates[] = 'display_name = ?';
                $params[] = trim($data['name']);
            }
            if (array_key_exists('description', $data)) {
                $updates[] = 'description = ?';
                $params[] = $data['description'];
            }
            if (array_key_exists('status', $data)) {
                $updates[] = 'is_active = ?';
                $params[] = ($data['status'] === 'active') ? 1 : 0;
            }

            if (count($updates) === 1) {
                return $this->errorResponse('No fields to update');
            }

            $params[] = $id;
            $stmt = $this->db->prepare('UPDATE dashboards SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);

            return $this->successResponse(['id' => $id], 'Dashboard updated');
        } catch (Exception $e) {
            $this->logError($e, 'Failed to update dashboard');
            return $this->errorResponse('Failed to update dashboard');
        }
    }

    public function deleteDashboard($id)
    {
        try {
            $id = (int) $id;
            if ($id <= 0) {
                return $this->errorResponse('Dashboard ID is required');
            }

            $stmt = $this->db->prepare('SELECT id FROM dashboards WHERE id = ?');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->errorResponse('Dashboard not found', 404);
            }

            $this->db->beginTransaction();
            $link = $this->db->prepare('DELETE FROM role_dashboards WHERE dashboard_id = ?');
            $link->execute([$id]);
            $del = $this->db->prepare('DELETE FROM dashboards WHERE id = ?');
            $del->execute([$id]);
            $this->db->commit();

            return $this->successResponse(['id' => $id], 'Dashboard deleted');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError($e, 'Failed to delete dashboard');
            return $this->errorResponse('Failed to delete dashboard');
        }
    }

    /**
     * List widgets mapped to the registry UI shape:
     * id, key, name, type, permission, description, status.
     */
    public function listWidgets($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['search'])) {
                $where[] = '(widget_key LIKE ? OR name LIKE ? OR type LIKE ? OR description LIKE ?)';
                $term = '%' . $params['search'] . '%';
                $bindings[] = $term;
                $bindings[] = $term;
                $bindings[] = $term;
                $bindings[] = $term;
            }
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = 'is_active = ?';
                $bindings[] = ($params['status'] === 'active') ? 1 : 0;
            }
            if (!empty($params['type'])) {
                $where[] = 'type = ?';
                $bindings[] = $params['type'];
            }

            $whereClause = implode(' AND ', $where);
            $sql = "SELECT id, widget_key AS `key`, name, type, permission, description, is_active
                    FROM widgets WHERE $whereClause ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['status'] = $row['is_active'] ? 'active' : 'inactive';
            }
            unset($row);

            return $this->successResponse($rows, 'Widgets retrieved');
        } catch (Exception $e) {
            $this->logError($e, 'Failed to list widgets');
            return $this->errorResponse('Failed to load widgets');
        }
    }

    public function createWidget($data, $userId)
    {
        try {
            $key = trim($data['key'] ?? '');
            $name = trim($data['name'] ?? '');
            if ($key === '' || $name === '') {
                return $this->errorResponse('Widget key and name are required');
            }

            $allowedTypes = ['chart', 'stat', 'table', 'list', 'custom'];
            $type = in_array($data['type'] ?? '', $allowedTypes, true) ? $data['type'] : 'chart';

            $exists = $this->db->prepare('SELECT id FROM widgets WHERE widget_key = ?');
            $exists->execute([$key]);
            if ($exists->fetch()) {
                return $this->errorResponse('A widget with this key already exists', 409);
            }

            $stmt = $this->db->prepare(
                'INSERT INTO widgets (widget_key, name, type, permission, description, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $key,
                $name,
                $type,
                $data['permission'] ?? null,
                $data['description'] ?? null,
                ($data['status'] ?? 'active') === 'active' ? 1 : 0,
            ]);

            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Widget created', 201);
        } catch (Exception $e) {
            $this->logError($e, 'Failed to create widget');
            return $this->errorResponse('Failed to create widget');
        }
    }

    public function updateWidget($id, $data)
    {
        try {
            $id = (int) ($data['id'] ?? $id);
            if ($id <= 0) {
                return $this->errorResponse('Widget ID is required');
            }

            $stmt = $this->db->prepare('SELECT id FROM widgets WHERE id = ?');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->errorResponse('Widget not found', 404);
            }

            $allowedTypes = ['chart', 'stat', 'table', 'list', 'custom'];
            $updates = ['updated_at = NOW()'];
            $params = [];

            if (array_key_exists('key', $data)) {
                $updates[] = 'widget_key = ?';
                $params[] = trim($data['key']);
            }
            if (array_key_exists('name', $data)) {
                $updates[] = 'name = ?';
                $params[] = trim($data['name']);
            }
            if (array_key_exists('type', $data)) {
                $updates[] = 'type = ?';
                $params[] = in_array($data['type'], $allowedTypes, true) ? $data['type'] : 'chart';
            }
            if (array_key_exists('permission', $data)) {
                $updates[] = 'permission = ?';
                $params[] = $data['permission'] ?: null;
            }
            if (array_key_exists('description', $data)) {
                $updates[] = 'description = ?';
                $params[] = $data['description'];
            }
            if (array_key_exists('status', $data)) {
                $updates[] = 'is_active = ?';
                $params[] = ($data['status'] === 'active') ? 1 : 0;
            }

            if (count($updates) === 1) {
                return $this->errorResponse('No fields to update');
            }

            $params[] = $id;
            $stmt = $this->db->prepare('UPDATE widgets SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);

            return $this->successResponse(['id' => $id], 'Widget updated');
        } catch (Exception $e) {
            $this->logError($e, 'Failed to update widget');
            return $this->errorResponse('Failed to update widget');
        }
    }

    public function deleteWidget($id)
    {
        try {
            $id = (int) $id;
            if ($id <= 0) {
                return $this->errorResponse('Widget ID is required');
            }

            $stmt = $this->db->prepare('DELETE FROM widgets WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                return $this->errorResponse('Widget not found', 404);
            }

            return $this->successResponse(['id' => $id], 'Widget deleted');
        } catch (Exception $e) {
            $this->logError($e, 'Failed to delete widget');
            return $this->errorResponse('Failed to delete widget');
        }
    }
}
