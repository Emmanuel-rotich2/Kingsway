<?php
namespace App\API\Controllers;

use App\API\Modules\communications\CommunicationsAPI;
use Exception;

/**
 * CommunicationsController
 *
 * REST endpoints for all communication operations. Handles:
 *  - SMS callbacks (delivery reports, opt-outs, incoming/subscription messages)
 *  - Contact directory CRUD
 *  - External inbound CRUD
 *  - Forum threads (staff/parent) CRUD
 *  - Internal announcements and internal requests CRUD
 *  - Parent portal messages CRUD
 *  - Staff forum topics and staff requests CRUD
 *  - Communications, recipients, attachments, templates, groups, logs CRUD
 *  - Communication workflow instances (initiate, approve, escalate, complete)
 *
 * Controller method convention:
 *  All public endpoint methods follow the signature:
 *      methodName($id = null, $data = [], $segments = [])
 *  - $id: optional resource identifier (required for update/delete/get specific)
 *  - $data: associative array of request payload or query params
 *  - $segments: optional URL segment array from the router
 *
 * Responses:
 *  - Uses helper responses: success($data, $message), badRequest($message)
 *  - Most methods delegate to App\API\Modules\communications\CommunicationsAPI
 *    and then pass results through handleResponse() which:
 *      - Interprets arrays that include a 'success' boolean
 *      - Returns a success response when appropriate
 *      - Returns badRequest on explicit failure indications
 *
 * Important behaviours and expectations per endpoint group:
 *
 * SMS Callback Endpoints
 *  - postSmsDeliveryReport($id = null, $data = [], $segments = [])
 *      Logs incoming delivery report. If 'message_id' and 'status' are present
 *      calls updateDeliveryStatus(message_id, status, delivered_at?, error_message?).
 *      Expected $data keys: message_id, status, delivered_at (optional), error_message (optional).
 *
 *  - postSmsOptOutCallback($id = null, $data = [], $segments = [])
 *      Logs opt-out and calls markOptOut(phone, channel). Expected $data keys:
 *      phone (required), channel (optional, defaults to 'sms').
 *
 *  - postSmsSubscriptionCallback($id = null, $data = [], $segments = [])
 *      Logs incoming SMS messages and stores them via storeIncomingMessage().
 *      Expected $data keys: phone (required), message (required), channel (optional),
 *      received_at (optional). Raw payload is persisted where supported.
 *
 * Contact Directory CRUD
 *  - getContact($id = null, $data = [], $segments = [])
 *      If $id provided -> getContact($id), otherwise -> listContacts($data).
 *  - postContact($id = null, $data = [], $segments = [])
 *      createContact($data)
 *  - putContact($id = null, $data = [], $segments = [])
 *      Requires $id -> updateContact($id, $data). Returns badRequest if no $id.
 *  - deleteContact($id = null, $data = [], $segments = [])
 *      Requires $id -> deleteContact($id). Returns badRequest if no $id.
 *
 * External Inbound CRUD
 *  - getInbound, postInbound, putInbound, deleteInbound
 *      Same patterns as contact CRUD. put/delete require $id.
 *
 * Forum CRUD (Threads)
 *  - getThread, postThread, putThread, deleteThread
 *      Same patterns as contact CRUD. put/delete require $id.
 *
 * Internal Announcement CRUD
 *  - getAnnouncement, postAnnouncement, putAnnouncement, deleteAnnouncement
 *      Same patterns. put/delete require $id.
 *
 * Internal Comm CRUD (Internal Requests)
 *  - getInternalRequest, postInternalRequest, putInternalRequest, deleteInternalRequest
 *      Same patterns. put/delete require $id.
 *
 * Parent Portal Message CRUD
 *  - getParentMessage, postParentMessage, putParentMessage, deleteParentMessage
 *      Same patterns. put/delete require $id.
 *
 * Staff Forum/Request CRUD
 *  - getStaffForumTopic / postStaffForumTopic / putStaffForumTopic / deleteStaffForumTopic
 *  - getStaffRequest / postStaffRequest / putStaffRequest / deleteStaffRequest
 *      Same patterns. put/delete require $id.
 *
 * Communications CRUD
 *  - getCommunication, postCommunication, putCommunication, deleteCommunication
 *      Same patterns. put/delete require $id.
 *
 * Attachments CRUD
 *  - getAttachment($id = null, $data = [], $segments = [])
 *      If $id provided -> getAttachment($id). If $data contains 'communication_id'
 *      -> listAttachments(communication_id). Otherwise returns badRequest.
 *  - postAttachment($id = null, $data = [], $segments = [])
 *      Requires 'communication_id' in $data -> addAttachment(communication_id, $data).
 *      Returns badRequest if communication_id missing.
 *  - deleteAttachment($id = null, $data = [], $segments = [])
 *      Requires $id -> deleteAttachment($id). Returns badRequest if no $id.
 *
 * Groups CRUD
 *  - getGroup, postGroup, putGroup, deleteGroup
 *      Same patterns. put/delete require $id.
 *
 * Logs CRUD
 *  - getLog($id = null, $data = [], $segments = [])
 *      If $id provided -> getLog($id), otherwise -> listLogs($data).
 *  - postLog($id = null, $data = [], $segments = [])
 *      addLog($data)
 *
 * Recipients CRUD
 *  - getRecipient($id = null, $data = [], $segments = [])
 *      If $id provided -> getRecipient($id). If $data contains 'communication_id'
 *      -> listRecipients(communication_id). Otherwise returns badRequest.
 *  - postRecipient($id = null, $data = [], $segments = [])
 *      addRecipient($data)
 *  - deleteRecipient($id = null, $data = [], $segments = [])
 *      Requires $id -> deleteRecipient($id). Returns badRequest if no $id.
 *
 * Templates CRUD
 *  - getTemplate, postTemplate, putTemplate, deleteTemplate
 *      Same patterns. put/delete require $id.
 *
 * Workflow Instances (Communication Workflows)
 *  - getWorkflowInstance($id = null, $data = [], $segments = [])
 *      If $id -> getCommunicationWorkflowInstance($id). Otherwise -> listCommunicationWorkflows($data).
 *  - postWorkflowInstance($id = null, $data = [], $segments = [])
 *      Initiates a workflow and requires both 'reference_type' and 'reference_id'
 *      in $data. Calls initiateCommunicationWorkflow(reference_type, reference_id, $data).
 *      Returns badRequest if missing required reference fields.
 *  - putWorkflowInstance($id = null, $data = [], $segments = [])
 *      Requires $id. Expects 'action' in $data. Supported actions:
 *        - 'approve'  -> approveCommunication($id, $data)
 *        - 'escalate' -> escalateCommunication($id, $data)
 *        - 'complete' -> completeCommunication($id, $data)
 *      Returns badRequest on unknown action or if 'action' not provided.
 *
 * Helper/Private Behavior
 *  - handleResponse($result)
 *      Internal formatter that inspects API module return values and maps them
 *      to controller success/badRequest responses. Treats arrays with a
 *      'success' boolean specially.
 *
 * Notes & Integration:
 *  - All heavy lifting is delegated to App\API\Modules\communications\CommunicationsAPI.
 *  - Callback endpoints log raw payloads (via error_log) for audit/debug.
 *  - Where endpoints require an identifier or specific data keys, controller
 *    returns badRequest() immediately if requirements are not met.
 *  - This controller is intended to be invoked by a router which passes $id,
 *    $data and $segments using the above conventions.
 *
 * @package App\API\Controllers
 * @see App\API\Modules\communications\CommunicationsAPI
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

    public function index()
    {
        return $this->success(['message' => 'Communications API is running']);
    }

    // --- SMS Callback Endpoints ---
    /**
     * Endpoint for SMS Delivery Reports Callback
     * Logs delivery report and updates delivery status if possible
     */
    public function postSmsDeliveryReport($id = null, $data = [], $segments = [])
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
    public function postSmsOptOutCallback($id = null, $data = [], $segments = [])
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
    public function postSmsSubscriptionCallback($id = null, $data = [], $segments = [])
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


    // --- Advanced SMS/Email/WhatsApp Sending ---
    /**
     * Send SMS with template selection
     * POST /communications/send-sms-template
     */
    public function postSendSmsTemplate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->postSendSmsTemplate());
    }

    /**
     * Send WhatsApp with document attachments
     * POST /communications/send-whatsapp
     */
    public function postSendWhatsapp($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->postSendWhatsapp());
    }

    /**
     * Send SMS directly with message
     * POST /communications/send-sms
     */
    public function postSendSms($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->postSendSms());
    }

    /**
     * Send Email directly with message
     * POST /communications/send-email
     */
    public function postSendEmail($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->postSendEmail());
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
        // Return all attachments if no filter provided (fallback for GET without params)
        return $this->handleResponse($this->api->listAttachments(null));
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
        // Return all recipients if no filter provided (fallback for GET without params)
        return $this->handleResponse($this->api->listRecipients(null));
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
