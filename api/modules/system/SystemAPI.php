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
        $this->mediaManager = new MediaManager($this->db);
    }
    // === Media Management ===
    public function uploadMedia($file, $context, $entityId = null, $albumId = null, $uploaderId = null, $description = '', $tags = '')
    {
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
        return ['success' => $this->mediaManager->updateMedia($mediaId, $fields)];
    }

    public function deleteMedia($mediaId)
    {
        return ['success' => $this->mediaManager->deleteMedia($mediaId)];
    }

    public function deleteAlbum($albumId)
    {
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
    // Read all log files in the logs directory
    public function readLogs($filters = [])
    {
        $logDir = __DIR__ . '/../../../logs/';
        $logs = [];
        foreach (glob($logDir . '*.log') as $file) {
            $logs[basename($file)] = file_get_contents($file);
        }
        return ['success' => true, 'data' => $logs];
    }

    // Clear all log files
    public function clearLogs()
    {
        $logDir = __DIR__ . '/../../../logs/';
        foreach (glob($logDir . '*.log') as $file) {
            file_put_contents($file, '');
        }
        return ['success' => true, 'message' => 'All logs cleared'];
    }

    // Archive all log files (move to logs/archive/ with timestamp)
    public function archiveLogs()
    {
        $logDir = __DIR__ . '/../../../logs/';
        $archiveDir = $logDir . 'archive/';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0777, true);
        }
        foreach (glob($logDir . '*.log') as $file) {
            $newName = $archiveDir . basename($file, '.log') . '_' . date('Ymd_His') . '.log';
            rename($file, $newName);
        }
        return ['success' => true, 'message' => 'All logs archived'];
    }


    // Read school configuration (direct DB access)
    public function getSchoolConfig($id = null)
    {
        if ($id) {
            $stmt = $this->db->prepare('SELECT * FROM school_configuration WHERE id = ?');
            $stmt->execute([$id]);
            $config = $stmt->fetch();
            if ($config) {
                return ['success' => true, 'data' => $config];
            } else {
                return ['success' => false, 'message' => 'Config not found'];
            }
        } else {
            $stmt = $this->db->query('SELECT * FROM school_configuration');
            $configs = $stmt->fetchAll();
            return ['success' => true, 'data' => $configs];
        }
    }


    // Set school configuration (direct DB access)
    public function setSchoolConfig($data)
    {
        $allowedFields = [
            'school_name', 'school_code', 'logo_url', 'favicon_url', 'motto', 'vision',
            'mission', 'core_values', 'about_us', 'email', 'phone', 'alternative_phone',
            'address', 'city', 'state', 'country', 'postal_code', 'website',
            'facebook_url', 'twitter_url', 'instagram_url', 'linkedin_url', 'youtube_url',
            'established_year', 'principal_name', 'principal_message', 'academic_calendar_url',
            'prospectus_url', 'student_handbook_url', 'timezone', 'currency', 'language',
            'date_format', 'time_format', 'is_active', 'created_by', 'updated_by'
        ];
        $allowedMap = array_flip($allowedFields);
        $filteredData = array_intersect_key($data, $allowedMap);

        if (isset($data['id'])) {
            // Update existing config
            $fields = [];
            $params = [];
            foreach ($filteredData as $key => $value) {
                $fields[] = "`$key` = ?";
                $params[] = $value;
            }
            if (empty($fields)) {
                $result = ['success' => false, 'message' => 'No valid fields to update'];
            } else {
                $params[] = $data['id'];
                $sql = 'UPDATE school_configuration SET ' . implode(', ', $fields) . ' WHERE id = ?';
                $stmt = $this->db->prepare($sql);
                $success = $stmt->execute($params);
                if ($success) {
                    $result = ['success' => true, 'message' => 'Config updated'];
                } else {
                    $result = ['success' => false, 'message' => 'Update failed'];
                }
            }
        } else {
            // Create new config
            if (empty($filteredData)) {
                return ['success' => false, 'message' => 'No valid fields to create'];
            }

            $fields = array_keys($filteredData);
            $quotedFields = array_map(fn($field) => "`$field`", $fields);
            $placeholders = array_fill(0, count($fields), '?');
            $params = array_values($filteredData);
            $sql = 'INSERT INTO school_configuration (' . implode(', ', $quotedFields) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($params);
            if ($success) {
                $result = ['success' => true, 'message' => 'Config created', 'id' => $this->db->lastInsertId()];
            } else {
                $result = ['success' => false, 'message' => 'Create failed'];
            }
        }
        return $result;
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
}
