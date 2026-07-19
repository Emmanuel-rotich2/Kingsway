<?php
/**
 * Device-Bound Session Controller
 * 
 * Manages device-bound sessions for enhanced security.
 * Sessions are bound to specific devices using device fingerprints.
 */

class DeviceSessionController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Register device for user
     */
    public function registerDevice($userId, $deviceFingerprint, $deviceInfo) {
        try {
            // Check if device already registered
            $existing = $this->db->query(
                "SELECT id FROM user_devices WHERE user_id = ? AND device_fingerprint = ?",
                [$userId, $deviceFingerprint]
            )->fetch();
            
            if ($existing) {
                // Update last used timestamp
                $this->db->query(
                    "UPDATE user_devices SET last_used_at = NOW(), device_info = ? WHERE id = ?",
                    [json_encode($deviceInfo), $existing['id']]
                );
                return $existing['id'];
            }
            
            // Register new device
            $this->db->query(
                "INSERT INTO user_devices (user_id, device_fingerprint, device_info, registered_at, last_used_at) VALUES (?, ?, ?, NOW(), NOW())",
                [$userId, $deviceFingerprint, json_encode($deviceInfo)]
            );
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Device registration failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate device for user session
     */
    public function validateDevice($userId, $deviceFingerprint) {
        try {
            $device = $this->db->query(
                "SELECT * FROM user_devices WHERE user_id = ? AND device_fingerprint = ? AND is_active = 1",
                [$userId, $deviceFingerprint]
            )->fetch();
            
            if (!$device) {
                return ['valid' => false, 'reason' => 'Device not registered'];
            }
            
            // Check if device is blocked
            if ($device['is_blocked']) {
                return ['valid' => false, 'reason' => 'Device blocked'];
            }
            
            // Update last used timestamp
            $this->db->query(
                "UPDATE user_devices SET last_used_at = NOW() WHERE id = ?",
                [$device['id']]
            );
            
            return ['valid' => true, 'device_id' => $device['id']];
        } catch (Exception $e) {
            error_log("Device validation failed: " . $e->getMessage());
            return ['valid' => false, 'reason' => 'Validation error'];
        }
    }
    
    /**
     * Block device
     */
    public function blockDevice($deviceId) {
        try {
            $this->db->query(
                "UPDATE user_devices SET is_blocked = 1, blocked_at = NOW() WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Device block failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unblock device
     */
    public function unblockDevice($deviceId) {
        try {
            $this->db->query(
                "UPDATE user_devices SET is_blocked = 0, blocked_at = NULL WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Device unblock failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user devices
     */
    public function getUserDevices($userId) {
        try {
            $devices = $this->db->query(
                "SELECT * FROM user_devices WHERE user_id = ? ORDER BY last_used_at DESC",
                [$userId]
            )->fetchAll();
            
            return $devices;
        } catch (Exception $e) {
            error_log("Get user devices failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Revoke device
     */
    public function revokeDevice($deviceId) {
        try {
            $this->db->query(
                "UPDATE user_devices SET is_active = 0, revoked_at = NOW() WHERE id = ?",
                [$deviceId]
            );
            return true;
        } catch (Exception $e) {
            error_log("Device revoke failed: " . $e->getMessage());
            return false;
        }
    }
}
