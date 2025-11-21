<?php


namespace App\API\Modules\System;
use App\API\Includes\BaseAPI;


class SystemAPI extends BaseAPI
{
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
            $stmt = $this->db->prepare('SELECT * FROM school_config WHERE id = ?');
            $stmt->execute([$id]);
            $config = $stmt->fetch();
            if ($config) {
                return ['success' => true, 'data' => $config];
            } else {
                return ['success' => false, 'message' => 'Config not found'];
            }
        } else {
            $stmt = $this->db->query('SELECT * FROM school_config');
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
                $sql = 'UPDATE school_config SET ' . implode(', ', $fields) . ' WHERE id = ?';
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
            $sql = 'INSERT INTO school_config (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $placeholders) . ')';
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
