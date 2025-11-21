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
    /**
     * @var CommunicationsAPI
     */
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new CommunicationsAPI();
    }


    // --- SMS Callback Endpoints ---
    /**
     * Endpoint for SMS Delivery Reports Callback
     * Logs delivery report and updates delivery status if possible
     */
    public function smsDeliveryReport($id = null, $data = [], $segments = [])
    {
        // Log the incoming data
        error_log('SMS Delivery Report: ' . json_encode($data));
        // Update delivery status in DB if message_id/status present
        if (isset($data['message_id'], $data['status'])) {
            $deliveredAt = $data['delivered_at'] ?? null;
            $errorMessage = $data['error_message'] ?? null;
            $this->api->updateDeliveryStatus($data['message_id'], $data['status'], $deliveredAt, $errorMessage);
        }
        return $this->success(null, 'Delivery report received');
    }

    /**
     * Endpoint for SMS Bulk Opt-Out Callback
     * Logs opt-out and updates opt-out list if possible
     */
    public function smsOptOutCallback($id = null, $data = [], $segments = [])
    {
        // Log the incoming data
        error_log('SMS Opt-Out Callback: ' . json_encode($data));
        // Update opt-out list in DB if phone/channel present
        if (isset($data['phone'])) {
            $channel = $data['channel'] ?? 'sms';
            $this->api->markOptOut($data['phone'], $channel);
        }
        return $this->success(null, 'Opt-out received');
    }

    /**
     * Endpoint for SMS Subscription (incoming message) Callback
     * Logs incoming message and stores it if possible
     */
    public function smsSubscriptionCallback($id = null, $data = [], $segments = [])
    {
        // Log the incoming data
        error_log('SMS Subscription Callback: ' . json_encode($data));
        // Store incoming message in DB if phone/message present
        if (isset($data['phone'], $data['message'])) {
            $msgData = [
                'sender' => $data['phone'],
                'message' => $data['message'],
                'channel' => $data['channel'] ?? 'sms',
                'received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
                'raw_data' => $data
            ];
            $this->api->storeIncomingMessage($msgData);
        }
        return $this->success(null, 'Incoming message received');
    }


    // --- Contact Directory CRUD ---
    public function getContact($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getContact($id));
        }
        return $this->handleResponse($this->api->listContacts($data));
    }
    public function postContact($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createContact($data));
    }
    public function putContact($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateContact($id, $data));
    }
    public function deleteContact($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteContact($id));
    }

    // --- External Inbound CRUD ---
    public function getInbound($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getInbound($id));
        }
        return $this->handleResponse($this->api->listInbounds($data));
    }
    public function postInbound($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createInbound($data));
    }
    public function putInbound($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateInbound($id, $data));
    }
    public function deleteInbound($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteInbound($id));
    }

    // --- Forum CRUD ---
    public function getThread($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getThread($id));
        }
        return $this->handleResponse($this->api->listThreads($data));
    }
    public function postThread($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createThread($data));
    }
    public function putThread($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateThread($id, $data));
    }
    public function deleteThread($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteThread($id));
    }

    // --- Internal Announcement CRUD ---
    public function getAnnouncement($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getAnnouncement($id));
        }
        return $this->handleResponse($this->api->listAnnouncements($data));
    }
    public function postAnnouncement($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createAnnouncement($data));
    }
    public function putAnnouncement($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateAnnouncement($id, $data));
    }
    public function deleteAnnouncement($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteAnnouncement($id));
    }

    // --- Internal Comm CRUD ---
    public function getInternalRequest($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getInternalRequest($id));
        }
        return $this->handleResponse($this->api->listInternalRequests($data));
    }
    public function postInternalRequest($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createInternalRequest($data));
    }
    public function putInternalRequest($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateInternalRequest($id, $data));
    }
    public function deleteInternalRequest($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteInternalRequest($id));
    }

    // --- Parent Portal Message CRUD ---
    public function getParentMessage($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getParentMessage($id));
        }
        return $this->handleResponse($this->api->listParentMessages($data));
    }
    public function postParentMessage($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createParentMessage($data));
    }
    public function putParentMessage($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateParentMessage($id, $data));
    }
    public function deleteParentMessage($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteParentMessage($id));
    }

    // --- Staff Forum CRUD ---
    public function getStaffForumTopic($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getStaffForumTopic($id));
        }
        return $this->handleResponse($this->api->listStaffForumTopics($data));
    }
    public function postStaffForumTopic($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createStaffForumTopic($data));
    }
    public function putStaffForumTopic($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateStaffForumTopic($id, $data));
    }
    public function deleteStaffForumTopic($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteStaffForumTopic($id));
    }

    // --- Staff Request CRUD ---
    public function getStaffRequest($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getStaffRequest($id));
        }
        return $this->handleResponse($this->api->listStaffRequests($data));
    }
    public function postStaffRequest($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createStaffRequest($data));
    }
    public function putStaffRequest($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateStaffRequest($id, $data));
    }
    public function deleteStaffRequest($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteStaffRequest($id));
    }


    // --- Communications CRUD ---
    public function getCommunication($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getCommunication($id));
        }
        return $this->handleResponse($this->api->listCommunications($data));
    }
    public function postCommunication($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createCommunication($data));
    }
    public function putCommunication($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateCommunication($id, $data));
    }
    public function deleteCommunication($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteCommunication($id));
    }

    // --- Attachments CRUD ---
    public function getAttachment($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getAttachment($id));
        }
        if (isset($data['communication_id'])) {
            return $this->handleResponse($this->api->listAttachments($data['communication_id']));
        }
        return $this->badRequest('communication_id required');
    }
    public function postAttachment($id = null, $data = [], $segments = [])
    {
        if (!isset($data['communication_id'])) {
            return $this->badRequest('communication_id required');
        }
        return $this->handleResponse($this->api->addAttachment($data['communication_id'], $data));
    }
    public function deleteAttachment($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteAttachment($id));
    }

    // --- Groups CRUD ---
    public function getGroup($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getGroup($id));
        }
        return $this->handleResponse($this->api->listGroups($data));
    }
    public function postGroup($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createGroup($data));
    }
    public function putGroup($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateGroup($id, $data));
    }
    public function deleteGroup($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteGroup($id));
    }

    // --- Logs CRUD ---
    public function getLog($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getLog($id));
        }
        return $this->handleResponse($this->api->listLogs($data));
    }
    public function postLog($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->addLog($data));
    }

    // --- Recipients CRUD ---
    public function getRecipient($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getRecipient($id));
        }
        if (isset($data['communication_id'])) {
            return $this->handleResponse($this->api->listRecipients($data['communication_id']));
        }
        return $this->badRequest('communication_id required');
    }
    public function postRecipient($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->addRecipient($data));
    }
    public function deleteRecipient($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteRecipient($id));
    }

    // --- Templates CRUD ---
    public function getTemplate($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getTemplate($id));
        }
        return $this->handleResponse($this->api->listTemplates($data));
    }
    public function postTemplate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->createTemplate($data));
    }
    public function putTemplate($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->updateTemplate($id, $data));
    }
    public function deleteTemplate($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        return $this->handleResponse($this->api->deleteTemplate($id));
    }

    // --- Workflow Instances CRUD ---
    public function getWorkflowInstance($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->api->getCommunicationWorkflowInstance($id));
        }
        return $this->handleResponse($this->api->listCommunicationWorkflows($data));
    }
    public function postWorkflowInstance($id = null, $data = [], $segments = [])
    {
        // Expecting reference_type and reference_id in $data
        if (!isset($data['reference_type']) || !isset($data['reference_id'])) {
            return $this->badRequest('reference_type and reference_id required');
        }
        return $this->handleResponse($this->api->initiateCommunicationWorkflow($data['reference_type'], $data['reference_id'], $data));
    }
    public function putWorkflowInstance($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        // Support for advancing, escalating, or completing workflow
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'approve':
                    return $this->handleResponse($this->api->approveCommunication($id, $data));
                case 'escalate':
                    return $this->handleResponse($this->api->escalateCommunication($id, $data));
                case 'complete':
                    return $this->handleResponse($this->api->completeCommunication($id, $data));
                default:
                    return $this->badRequest('Unknown workflow action');
            }
        }
        return $this->badRequest('Action required (approve, escalate, complete)');
    }
    /**
     * Handle API response and format appropriately (copied from FinanceController)
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
}
