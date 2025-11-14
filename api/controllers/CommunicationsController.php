<?php
namespace App\API\Controllers;

use App\API\Modules\Communications\CommunicationsAPI;
use Exception;

/**
 * CommunicationsController - REST endpoints for all communication operations
 * Handles announcements, notifications, bulk SMS/Email, templates, groups, and SMS configuration
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class CommunicationsController extends BaseController
{
    private CommunicationsAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new CommunicationsAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/communications - List all communications
     * GET /api/communications/{id} - Get single communication
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications - Create new communication
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/communications/{id} - Update communication
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Communication ID is required for update');
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/communications/{id} - Delete communication
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Communication ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Announcements
    // ========================================

    /**
     * GET /api/communications/announcements/get
     */
    public function getAnnouncementsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAnnouncements($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/announcements/send
     */
    public function postAnnouncementsSend($id = null, $data = [], $segments = [])
    {
        $result = $this->api->sendAnnouncement($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Notifications
    // ========================================

    /**
     * GET /api/communications/notifications/get
     */
    public function getNotificationsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getNotifications($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/notifications/send
     */
    public function postNotificationsSend($id = null, $data = [], $segments = [])
    {
        $result = $this->api->sendNotification($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Bulk Messaging
    // ========================================

    /**
     * POST /api/communications/bulk/sms
     */
    public function postBulkSms($id = null, $data = [], $segments = [])
    {
        $result = $this->api->sendBulkSMS($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/bulk/email
     */
    public function postBulkEmail($id = null, $data = [], $segments = [])
    {
        $result = $this->api->sendBulkEmail($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Templates
    // ========================================

    /**
     * GET /api/communications/templates/get
     */
    public function getTemplatesGet($id = null, $data = [], $segments = [])
    {
        $type = $data['type'] ?? null;
        $result = $this->api->getTemplates($type);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/templates/create
     */
    public function postTemplatesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createTemplate($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/communications/templates/sms
     */
    public function getTemplatesSms($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSMSTemplates();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/communications/templates/email
     */
    public function getTemplatesEmail($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getEmailTemplates();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/templates/sms/create
     */
    public function postTemplatesSmsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createSMSTemplate($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/templates/email/create
     */
    public function postTemplatesEmailCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createEmailTemplate($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Groups
    // ========================================

    /**
     * GET /api/communications/groups/get
     */
    public function getGroupsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getGroups();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/communications/groups/create
     */
    public function postGroupsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createGroup($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: SMS Configuration
    // ========================================

    /**
     * GET /api/communications/sms/config
     */
    public function getSmsConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSMSConfig();
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/communications/sms/config/update
     */
    public function putSmsConfigUpdate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateSMSConfig($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }

        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
