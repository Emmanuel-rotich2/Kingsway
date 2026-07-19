<?php

namespace App\API\Controllers;

use Exception;

/**
 * Settings Controller
 *
 * Exposes role and permission administration plus a database-backup trigger,
 * consumed by the System Settings page (js/pages/settings.js) via the DataStore
 * / DataTable pipeline. Routes (router maps GET /api/settings/roles ->
 * getRoles(), etc.):
 *   GET  /settings/roles        -> getRoles()
 *   POST /settings/roles        -> postRoles()        (create)
 *   PUT  /settings/roles/{id}   -> putRoles()         (update)
 *   DELETE /settings/roles/{id} -> deleteRoles()      (delete)
 *   GET  /settings/permissions  -> getPermissions()
 *   POST /settings/backup       -> postBackup()
 *
 * Column aliases are chosen to match the DataTable column config in settings.js
 * (e.g. roles.name -> role_name, roles.is_active -> status).
 */
class SettingsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * GET /api/settings/roles
     */
    public function getRoles($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $row = $this->db->query(
                "SELECT id, name AS role_name, description,
                        is_active AS status, user_count,
                        (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS permission_count
                 FROM roles WHERE id = ?",
                [$id]
            )->fetch(\PDO::FETCH_ASSOC);
            return $this->success($row ?: []);
        }

        $rows = $this->db->query(
            "SELECT id, name AS role_name, description,
                    is_active AS status, user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS permission_count
             FROM roles ORDER BY name ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

    /**
     * POST /api/settings/roles  (create a role)
     */
    public function postRoles($id = null, $data = [])
    {
        $name = trim($data['role_name'] ?? $data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        if ($name === '') {
            return $this->badRequest('Role name is required');
        }

        $check = $this->db->query("SELECT id FROM roles WHERE name = ?", [$name]);
        if ($check->fetch()) {
            return $this->badRequest('A role with that name already exists');
        }

        $this->db->query(
            "INSERT INTO roles (name, description, scope, is_active) VALUES (?, ?, 'school', 1)",
            [$name, $description]
        );
        $newId = $this->db->lastInsertId();
        return $this->success(['id' => $newId, 'role_name' => $name], 'Role created');
    }

    /**
     * PUT /api/settings/roles/{id}  (update a role)
     */
    public function putRoles($id = null, $data = [])
    {
        if (!$id) {
            return $this->badRequest('Role id is required');
        }
        $existing = $this->db->query("SELECT id, is_system FROM roles WHERE id = ?", [$id])
            ->fetch(\PDO::FETCH_ASSOC);
        if (!$existing) {
            return $this->notFound('Role not found');
        }
        if (!empty($existing['is_system'])) {
            return $this->forbidden('System roles cannot be modified');
        }

        $name = trim($data['role_name'] ?? $data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        if ($name !== '') {
            $check = $this->db->query("SELECT id FROM roles WHERE name = ? AND id <> ?", [$name, $id]);
            if ($check->fetch()) {
                return $this->badRequest('A role with that name already exists');
            }
        }

        $this->db->query(
            "UPDATE roles SET name = COALESCE(NULLIF(?, ''), name),
                                description = ?
             WHERE id = ?",
            [$name, $description, $id]
        );
        return $this->success(['id' => $id], 'Role updated');
    }

    /**
     * DELETE /api/settings/roles/{id}
     */
    public function deleteRoles($id = null, $data = [])
    {
        if (!$id) {
            return $this->badRequest('Role id is required');
        }
        $existing = $this->db->query("SELECT id, is_system FROM roles WHERE id = ?", [$id])
            ->fetch(\PDO::FETCH_ASSOC);
        if (!$existing) {
            return $this->notFound('Role not found');
        }
        if (!empty($existing['is_system'])) {
            return $this->forbidden('System roles cannot be deleted');
        }

        // Drop role links first (junction tables cascade, but be explicit).
        $this->db->query("DELETE FROM role_permissions WHERE role_id = ?", [$id]);
        $this->db->query("DELETE FROM user_roles WHERE role_id = ?", [$id]);
        $this->db->query("DELETE FROM roles WHERE id = ?", [$id]);
        return $this->success(['id' => $id], 'Role deleted');
    }

    /**
     * GET /api/settings/permissions
     */
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        $rows = $this->db->query(
            "SELECT p.id, p.code AS permission_key, p.description AS permission_label,
                   p.module, p.entity, p.action,
                   (SELECT COUNT(*) FROM role_permissions rp WHERE rp.permission_id = p.id) AS role_count
             FROM permissions p
             ORDER BY p.module ASC, p.code ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

    /**
     * POST /api/settings/backup
     * Triggers a database dump into the storage/backups directory. This is a
     * best-effort operation; failures are reported but never fatal to the UI.
     */
    public function postBackup($id = null, $data = [])
    {
        $dbName = $this->db->query("SELECT DATABASE() AS db")->fetch(\PDO::FETCH_ASSOC)['db'] ?? 'kingsway';
        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.sql';
        $errorFile = $backupDir . '/.last_backup_error';

        // Locate mysqldump; fall back gracefully if unavailable.
        $mysqldump = $this->findMysqldump();
        if (!$mysqldump) {
            @file_put_contents($errorFile, date('c') . " mysqldump not found\n");
            return $this->success(
                ['backup_file' => null, 'note' => 'mysqldump unavailable on this host'],
                'Backup skipped'
            );
        }

        $cmd = sprintf(
            '%s --single-transaction -u%s %s %s > %s 2>/dev/null',
            escapeshellcmd($mysqldump),
            escapeshellarg($this->dbUser()),
            $this->dbPasswordFlag(),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        exec($cmd, $out, $code);

        if ($code !== 0 || !file_exists($backupFile) || filesize($backupFile) === 0) {
            @file_put_contents($errorFile, date('c') . " backup exit code $code\n");
            return $this->success(
                ['backup_file' => null, 'note' => 'Backup command failed'],
                'Backup not created'
            );
        }

        return $this->success(
            ['backup_file' => basename($backupFile), 'path' => $backupFile],
            'Backup created'
        );
    }

    /**
     * Resolve the credentials used by the active connection for mysqldump.
     */
    private function dbUser(): string
    {
        // The connected user is available from the server via USER().
        $dsn = $this->db->query("SELECT USER() AS u")->fetch(\PDO::FETCH_ASSOC)['u'] ?? '';
        $user = explode('@', $dsn)[0] ?? 'root';
        return $user;
    }

    private function dbPasswordFlag(): string
    {
        // We cannot safely retrieve the configured password from the connection.
        // If a dump password is configured in the environment, use it; otherwise omit.
        $pw = getenv('DB_DUMP_PASSWORD');
        return $pw !== false ? ('-p' . escapeshellarg($pw)) : '';
    }

    private function findMysqldump(): ?string
    {
        $candidates = ['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', '/opt/lampp/bin/mysqldump'];
        foreach ($candidates as $c) {
            if (is_executable($c)) {
                return $c;
            }
        }
        $which = @shell_exec('command -v mysqldump');
        return $which ? trim($which) : null;
    }
}
