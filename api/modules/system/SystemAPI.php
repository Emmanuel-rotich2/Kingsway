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
        
        if (isset($data['id'])) {
            // Update existing config
            $fields = [];
            $params = [];
            foreach ($data as $key => $value) {
                if ($key !== 'id') {
                    $fields[] = "$key = ?";
                    $params[] = $value;
                }
            }
            if (empty($fields)) {
                $result = ['success' => false, 'message' => 'No fields to update'];
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
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $params = array_values($data);
            $sql = 'INSERT INTO school_configuration (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
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
        return ['success' => true, 'message' => 'System is healthy', 'timestamp' => date('c')];
    }
}
