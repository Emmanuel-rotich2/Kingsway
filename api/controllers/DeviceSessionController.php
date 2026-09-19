<?php
/**
 * Device-Bound Session Controller
 *
 * Thin endpoint layer; all business logic lives in DeviceSessionManager.
 */

namespace App\API\Controllers;

use App\API\Services\auth\DeviceSessionManager;

class DeviceSessionController extends BaseController
{
    private $manager;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->manager = $this->contract('App\API\Services\auth\DeviceSessionManager');
    }

    /**
     * Register device for user
     */
    public function registerDevice($userId, $deviceFingerprint, $deviceInfo)
    {
        return $this->manager->registerDevice($userId, $deviceFingerprint, $deviceInfo);
    }

    /**
     * Validate device for user session
     */
    public function validateDevice($userId, $deviceFingerprint)
    {
        return $this->manager->validateDevice($userId, $deviceFingerprint);
    }

    /**
     * Block device
     */
    public function blockDevice($deviceId)
    {
        return $this->manager->blockDevice($deviceId);
    }

    /**
     * Unblock device
     */
    public function unblockDevice($deviceId)
    {
        return $this->manager->unblockDevice($deviceId);
    }

    /**
     * Get user devices
     */
    public function getUserDevices($userId)
    {
        return $this->manager->getUserDevices($userId);
    }

    /**
     * Revoke device
     */
    public function revokeDevice($deviceId)
    {
        return $this->manager->revokeDevice($deviceId);
    }
}
