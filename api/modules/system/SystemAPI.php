<?php


namespace App\API\Modules\system;
use App\API\Includes\BaseAPI;


use App\API\Modules\system\MediaManager;
use PDO;

class SystemAPI extends BaseAPI
{
    private $mediaManager;

    public function __construct()
    {
        parent::__construct();
        $this->mediaManager = $this->contract('App\API\Modules\system\MediaManager', $this->db);
    }
    // === Media Management ===
    public function uploadMedia($file, $context, $entityId = null, $albumId = null, $uploaderId = null, $description = '', $tags = '')
    {
        if (!is_array($file) || empty($file)) {
            throw new \InvalidArgumentException('A file is required.');
        }
        return ['success' => true, 'data' => $this->mediaManager->upload($file, $context, $entityId, $albumId, $uploaderId, $description, $tags)];
    }

    public function createAlbum($name, $description = '', $coverImage = null, $createdBy = null)
    {
        return ['success' => true, 'data' => $this->mediaManager->createAlbum($name, $description, $coverImage, $createdBy)];
    }

    public function listAlbums($filters = [])
    {
        return ['success' => true, 'data' => $this->mediaManager->listAlbums($filters)];
    }

    public function listMedia($filters = [])
    {
        return ['success' => true, 'data' => $this->mediaManager->listMedia($filters)];
    }

    public function updateMedia($mediaId, $fields)
    {
        if (empty($mediaId)) {
            throw new \InvalidArgumentException('media_id is required.');
        }
        return ['success' => $this->mediaManager->updateMedia($mediaId, $fields)];
    }

    public function deleteMedia($mediaId)
    {
        if (empty($mediaId)) {
            throw new \InvalidArgumentException('media_id is required.');
        }
        return ['success' => $this->mediaManager->deleteMedia($mediaId)];
    }

    public function deleteAlbum($albumId)
    {
        if (empty($albumId)) {
            throw new \InvalidArgumentException('album_id is required.');
        }
        return ['success' => $this->mediaManager->deleteAlbum($albumId)];
    }

    public function canAccessMedia($userId, $mediaId, $action = 'view')
    {
        return ['success' => $this->mediaManager->canAccess($userId, $mediaId, $action)];
    }

    public function trackMediaUsage($mediaId, $context)
    {
        return ['success' => $this->mediaManager->trackUsage($mediaId, $context)];
    }

    public function getMediaPreviewUrl($mediaId)
    {
        return ['success' => true, 'data' => $this->mediaManager->getPreviewUrl($mediaId)];
    }
    // Read all log files in the logs directory (flat + current environment subfolder)
    public function readLogs($filters = [])
    {
        $logDir = dirname(__DIR__, 3) . '/logs/';
        $logs = [];
        foreach (array_merge(glob($logDir . '*.log') ?: [], $this->envLogFiles($logDir)) as $file) {
            $key = basename($file);
            if (isset($logs[$key])) {
                continue;
            }
            $logs[$key] = $this->readManagedFile($file);
        }
        return ['success' => true, 'data' => $logs];
    }

    // Clear all log files
    public function clearLogs()
    {
        $logDir = dirname(__DIR__, 3) . '/logs/';
        foreach (array_merge(glob($logDir . '*.log') ?: [], $this->envLogFiles($logDir)) as $file) {
            $this->writeManagedFile($file, '');
        }
        return ['success' => true, 'message' => 'All logs cleared'];
    }

    // Archive all log files (move to logs/archive/ with timestamp)
    public function archiveLogs()
    {
        $logDir = dirname(__DIR__, 3) . '/logs/';
        $archiveDir = $logDir . 'archive/';
        $this->ensureManagedDirectory($archiveDir);
        foreach (array_merge(glob($logDir . '*.log') ?: [], $this->envLogFiles($logDir)) as $file) {
            $newName = $archiveDir . basename($file, '.log') . '_' . date('Ymd_His') . '.log';
            $this->moveManagedFile($file, $newName);
        }
        return ['success' => true, 'message' => 'All logs archived'];
    }

    /**
     * Log files under the current environment subfolder (e.g. logs/development/*.log).
     */
    private function envLogFiles(string $logDir): array
    {
        $env = \App\API\Includes\FileLogger::environment();
        return glob($logDir . $env . '/*.log') ?: [];
    }


    // Read school configuration from school_profile
    public function getSchoolConfig($id = null)
    {
        $stmt = $this->db->query('SELECT * FROM school_profile LIMIT 1');
        $config = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($config) {
            return ['success' => true, 'data' => $config];
        }
        return ['success' => true, 'data' => []];
    }


    // Set school configuration in school_profile
    public function setSchoolConfig($data)
    {
        $allowedFields = [
            'school_name', 'school_code', 'logo_url', 'favicon_url', 'motto', 'vision',
            'mission', 'about_us', 'email', 'phone', 'alternative_phone',
            'address', 'city', 'country', 'postal_code', 'website',
            'social_facebook', 'social_twitter', 'social_instagram',
            'social_youtube', 'social_whatsapp', 'google_maps_url',
            'established_year', 'office_hours_weekday',
            'office_hours_saturday', 'timezone', 'currency'
        ];
        $allowedMap = array_flip($allowedFields);
        $filteredData = array_intersect_key($data, $allowedMap);

        if (empty($filteredData)) {
            return ['success' => false, 'message' => 'No valid fields to update'];
        }

        // Upsert: update if row exists, insert if not
        $existing = $this->db->query('SELECT id FROM school_profile LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        if ($existing) {
            $fields = [];
            $params = [];
            foreach ($filteredData as $key => $value) {
                $fields[] = "`$key` = ?";
                $params[] = $value;
            }
            $params[] = $existing['id'];
            $sql = 'UPDATE school_profile SET ' . implode(', ', $fields) . ' WHERE id = ?';
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
        } else {
            $fields = array_keys($filteredData);
            $quotedFields = array_map(fn($field) => "`$field`", $fields);
            $placeholders = array_fill(0, count($fields), '?');
            $params = array_values($filteredData);
            $sql = 'INSERT INTO school_profile (' . implode(', ', $quotedFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
        }

        return $success
            ? ['success' => true, 'message' => 'Config saved']
            : ['success' => false, 'message' => 'Save failed'];
    }

    // Active system alerts (unresolved)
    public function getActiveAlerts($limit = 50)
    {
        $limit = max(1, min(100, (int) $limit));
        $stmt = $this->db->query(
            "SELECT id, severity, title, message, created_at FROM system_alerts
             WHERE resolved = 0
             ORDER BY FIELD(severity, 'critical','warning','info') ASC, created_at DESC
             LIMIT " . (int) $limit
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        return ['success' => true, 'data' => ['alerts' => $rows]];
    }

    // Audit log listing (with username) — read from the file audit log
    public function getAuditLogs($limit = 50)
    {
        $limit = max(1, min(100, (int) $limit));
        $rows = [];
        foreach (\App\API\Includes\FileLogger::recent('audit', $limit) as $e) {
            $rows[] = [
                'id' => null,
                'username' => $e['username'] ?? null,
                'user_id' => $e['user_id'] ?? null,
                'action' => $e['action'] ?? null,
                'entity' => $e['entity'] ?? null,
                'entity_id' => $e['entity_id'] ?? null,
                'details' => isset($e['details']) ? json_encode($e['details']) : null,
                'status' => $e['status'] ?? null,
                'ip_address' => $e['ip'] ?? $e['ip_address'] ?? null,
                'created_at' => $e['timestamp'] ?? null,
            ];
        }
        return ['success' => true, 'data' => ['logs' => $rows]];
    }

    // Record an audit approval/rejection and mirror the status onto the transaction
    public function approveTransaction($transactionId, $approved, $notes = null, $userId = null)
    {
        $action = $approved ? 'approve_transaction' : 'reject_transaction';
        $status = $approved ? 'confirmed' : 'failed';
        $details = ['notes' => $notes];

        \App\API\Includes\FileLogger::write('audit', [
            'type' => 'audit',
            'action' => $action,
            'entity' => 'school_transaction',
            'entity_id' => (int) $transactionId,
            'user_id' => $userId,
            'details' => $details,
            'status' => $status,
        ]);

        $stmt = $this->db->prepare('UPDATE school_transactions SET status = ? WHERE id = ?');
        $stmt->execute([$status, (int) $transactionId]);

        return ['success' => true, 'data' => ['audit_id' => null]];
    }

    // List school/calendar events. Tries known event tables and returns the
    // first one that exists (live schema: school_events).
    public function listSchoolEvents($type = null, $upcoming = false, $limit = 20)
    {
        $limit = max(1, min(100, (int) $limit));
        $tables = ['school_events', 'calendar_events', 'events'];
        foreach ($tables as $table) {
            try {
                $where = [];
                $bindings = [];
                if ($upcoming) {
                    $where[] = 'date >= CURDATE()';
                }
                if ($type !== null && $type !== '') {
                    $where[] = 'type = ?';
                    $bindings[] = $type;
                }
                $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
                $sql = "SELECT id, title, date, type, description FROM {$table}
                        {$whereSql} ORDER BY date ASC LIMIT " . (int) $limit;
                $stmt = $this->db->prepare($sql);
                $stmt->execute($bindings);
                return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
            } catch (\Exception $e) {
                continue;
            }
        }
        return ['success' => true, 'data' => []];
    }

    // Chapel services (school_events typed as chapel)
    public function listChapelServices($limit = 10, $upcoming = false)
    {
        $limit = max(1, min(50, (int) $limit));
        $tables = ['school_events', 'calendar_events', 'events'];
        foreach ($tables as $table) {
            try {
                $dateClause = $upcoming ? 'AND date >= CURDATE()' : '';
                $sql = "SELECT id, title, date, type, description FROM {$table}
                        WHERE type IN ('chapel','Chapel','CHAPEL') {$dateClause}
                        ORDER BY date ASC LIMIT " . (int) $limit;
                $stmt = $this->db->query($sql);
                return ['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []];
            } catch (\Exception $e) {
                continue;
            }
        }
        return ['success' => true, 'data' => []];
    }

    // System health check
    public function healthCheck()
    {
        $database = 'unknown';
        try {
            $this->db->query('SELECT 1');
            $database = 'online';
        } catch (\Throwable $e) {
            $database = 'offline';
        }

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $memoryLimit = ini_get('memory_limit');
        $memoryUsageMb = round(memory_get_usage(true) / 1048576, 2);
        $uptime = null;

        if (is_readable('/proc/uptime')) {
            $contents = file_get_contents('/proc/uptime');
            $seconds = (int) floor((float) explode(' ', trim((string) $contents))[0]);
            $days = intdiv($seconds, 86400);
            $hours = intdiv($seconds % 86400, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $uptime = sprintf('%dd %dh %dm', $days, $hours, $minutes);
        }

        $status = $database === 'online' ? 'healthy' : 'degraded';

        return [
            'success' => true,
            'message' => 'System health retrieved',
            'data' => [
                'status' => $status,
                'uptime' => $uptime ?? 'unknown',
                'database' => $database,
                'php_version' => PHP_VERSION,
                'memory_usage' => $memoryUsageMb . ' MB',
                'memory_limit' => $memoryLimit,
                'cpu_usage' => is_array($load) ? round((float) $load[0], 2) : null,
                'value1' => is_array($load) ? round((float) $load[0], 2) : 0,
                'value2' => $memoryUsageMb,
                'timestamp' => date('c'),
            ],
        ];
    }

    /**
     * Create a best-effort mysqldump into storage/backups. Never fatal to the UI.
     */
    public function createDatabaseBackup()
    {
        $stmt = $this->db->query('SELECT DATABASE() AS db');
        $dbName = $stmt->fetch(PDO::FETCH_ASSOC)['db'] ?? 'kingsway';

        $backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true)) {
            return ['success' => false, 'message' => 'Backup directory not writable'];
        }

        $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.sql';
        $errorFile = $backupDir . '/.last_backup_error';

        $mysqldump = $this->findMysqldump();
        if (!$mysqldump) {
            @file_put_contents($errorFile, date('c') . " mysqldump not found\n");
            return ['success' => true, 'message' => 'Backup skipped', 'data' => ['backup_file' => null, 'note' => 'mysqldump unavailable on this host']];
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
            return ['success' => true, 'message' => 'Backup not created', 'data' => ['backup_file' => null, 'note' => 'Backup command failed']];
        }

        return ['success' => true, 'message' => 'Backup created', 'data' => ['backup_file' => basename($backupFile), 'path' => $backupFile]];
    }

    private function dbUser(): string
    {
        $dsn = $this->db->query('SELECT USER() AS u')->fetch(PDO::FETCH_ASSOC)['u'] ?? '';
        $user = explode('@', $dsn)[0] ?? 'root';
        return $user;
    }

    private function dbPasswordFlag(): string
    {
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
