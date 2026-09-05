<?php
namespace App\API\Controllers;

use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\DataScopeService;
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
        $roles = $this->getUserRoleIds();
        $isManagement = !empty(array_intersect($roles, [2, 3, 4]));
        $isHeadteacher = in_array(5, $roles, true) && !$isManagement;
        return $this->handleResponse($this->api->getSummary([
            'user_id' => (int) $this->getUserId(),
            'visibility' => $isManagement ? 'all' : ($isHeadteacher ? 'teacher_parent' : 'self_or_tagged'),
        ]));
    }

    /** Internal worker endpoint for systemd/cron when CLI PHP lacks pdo_mysql. */
    public function postProcessOutbox($id = null, $data = [], $segments = [])
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string) COMMUNICATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->forbidden('Invalid worker credential');
        }
        $limit = max(1, min(100, (int) ($data['limit'] ?? 25)));
        $result = (new \App\API\Services\CommunicationOutboxService($this->getDb()->getConnection()))->processPending($limit);
        return $this->success($result, 'Communication outbox processed');
    }

    // --- SMS Callback Endpoints ---
    /**
     * Endpoint for SMS Delivery Reports Callback
     * Logs delivery report and updates delivery status if possible
     */
    public function postSmsDeliveryReport($id = null, $data = [], $segments = [])
    {
        if (!$this->validProviderCallback($data)) {
            return $this->badRequest('Invalid provider callback signature');
        }
        // Log the incoming data
        \App\API\Services\Logger::legacyError('SMS Delivery Report: ' . json_encode($data));
        // Update delivery status in DB if message_id/status present
        $providerMessageId = $data['message_id'] ?? $data['messageId'] ?? $data['id'] ?? null;
        $providerStatus = $data['status'] ?? $data['delivery_status'] ?? $data['statusCode'] ?? null;
        if ($providerMessageId && $providerStatus !== null) {
            $deliveredAt = $data['delivered_at'] ?? $data['date'] ?? null;
            $errorMessage = $data['error_message'] ?? $data['failureReason'] ?? $data['failure_reason'] ?? null;
            $this->api->updateDeliveryStatusByProvider($providerMessageId, $providerStatus, $deliveredAt, $errorMessage);
        }
        return $this->success(null, 'Delivery report received');
    }

    public function postWhatsappDeliveryReport($id = null, $data = [], $segments = [])
    {
        if (!$this->validProviderCallback($data)) {
            return $this->badRequest('Invalid provider callback signature');
        }
        $providerMessageId = $data['message_id'] ?? $data['messageId'] ?? $data['id'] ?? null;
        if ($providerMessageId && isset($data['status'])) {
            $this->api->updateDeliveryStatusByProvider(
                $providerMessageId,
                $data['status'],
                $data['delivered_at'] ?? null,
                $data['error_message'] ?? $data['failureReason'] ?? null
            );
        }
        \App\API\Services\Logger::legacyError('WhatsApp Delivery Report: ' . json_encode($data));
        return $this->success(null, 'WhatsApp delivery report received');
    }

    /**
     * Endpoint for SMS Bulk Opt-Out Callback
     * Logs opt-out and updates opt-out list if possible
     */
    public function postSmsOptOutCallback($id = null, $data = [], $segments = [])
    {
        if (!$this->validProviderCallback($data)) {
            return $this->badRequest('Invalid provider callback signature');
        }
        // Log the incoming data
        \App\API\Services\Logger::legacyError('SMS Opt-Out Callback: ' . json_encode($data));
        // Update opt-out list in DB if phone/channel present
        $phone = $data['phone'] ?? $data['phoneNumber'] ?? $data['from'] ?? null;
        if ($phone) {
            $this->api->markOptOut($phone, $data['channel'] ?? 'sms');
        }
        return $this->success(null, 'Opt-out received');
    }

    /**
     * Endpoint for SMS Subscription (incoming message) Callback
     * Logs incoming message and stores it if possible
     */
    public function postSmsSubscriptionCallback($id = null, $data = [], $segments = [])
    {
        if (!$this->validProviderCallback($data)) {
            return $this->badRequest('Invalid provider callback signature');
        }
        // Log the incoming data
        \App\API\Services\Logger::legacyError('SMS Subscription Callback: ' . json_encode($data));
        // Store incoming message in DB if phone/message present
        $phone = $data['phone'] ?? $data['phoneNumber'] ?? $data['from'] ?? null;
        $message = $data['message'] ?? $data['text'] ?? $data['body'] ?? null;
        if ($phone && $message !== null) {
            $msgData = [
                'sender' => $phone,
                'message' => $message,
                'channel' => $data['channel'] ?? 'sms',
                'received_at' => $data['received_at'] ?? date('Y-m-d H:i:s'),
                'raw_data' => $data
            ];
            $this->api->storeIncomingMessage($msgData);
        }
        return $this->success(null, 'Incoming message received');
    }

    /** Africa's Talking WhatsApp inbound callback. */
    public function postWhatsappIncoming($id = null, $data = [], $segments = [])
    {
        if (!$this->validProviderCallback($data)) return $this->badRequest('Invalid provider callback signature');
        try {
            return $this->success($this->api->storeIncomingWhatsappMessage($data), 'WhatsApp message received');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[WhatsApp inbound] ' . $e->getMessage());
            return $this->badRequest('Unable to process WhatsApp message');
        }
    }


    // --- PTA membership directory ---
    // PTA membership is stored against real parent records. It must never
    // read from communications, SMS, or outbox rows.
    private function requirePtaAccess()
    {
        if (!$this->userHasAny(['students_parents_view', 'students_parents_view_all', 'students_view'])) {
            return $this->forbidden('Permission required to manage PTA membership');
        }
        return null;
    }

    public function getPta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        $pdo = $this->getDb()->getConnection();
        if ($id !== null) {
            $stmt = $pdo->prepare("SELECT m.id,m.parent_id,m.role,m.membership_status AS status,m.appointed_at,m.ended_at,m.notes,
                    CONCAT_WS(' ',pp.first_name,pp.middle_name,pp.last_name) AS name,pp.phone,pp.email
                FROM parent_pta_memberships m JOIN parents p ON p.id=m.parent_id JOIN persons pp ON pp.id=p.person_id WHERE m.id=?");
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $this->success($row) : $this->notFound('PTA membership not found');
        }
        $rows = $pdo->query("SELECT m.id,m.parent_id,m.role,m.membership_status AS status,m.appointed_at,m.ended_at,m.notes,
                CONCAT_WS(' ',pp.first_name,pp.middle_name,pp.last_name) AS name,pp.phone,pp.email
            FROM parent_pta_memberships m JOIN parents p ON p.id=m.parent_id JOIN persons pp ON pp.id=p.person_id
            ORDER BY m.membership_status='active' DESC, pp.first_name,pp.last_name,m.id")->fetchAll(\PDO::FETCH_ASSOC);
        return $this->success($rows);
    }

    public function postPta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        $parentId=(int)($data['parent_id']??0); $role=trim((string)($data['role']??'Member'));
        if (!$parentId) return $this->badRequest('parent_id is required');
        $pdo=$this->getDb()->getConnection(); $check=$pdo->prepare('SELECT id FROM parents WHERE id=?'); $check->execute([$parentId]);
        if (!$check->fetchColumn()) return $this->badRequest('Parent record not found');
        $stmt=$pdo->prepare("INSERT INTO parent_pta_memberships (parent_id,role,membership_status,appointed_at,notes,created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$parentId,$role,$data['status']??'active',$data['appointed_at']??null,$data['notes']??null,$this->getUserId()]);
        return $this->success(['id'=>(int)$pdo->lastInsertId()],'PTA member added');
    }

    public function putPta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        if ($id===null) return $this->badRequest('PTA membership ID is required');
        $fields=[]; $values=[];
        foreach(['role','status','appointed_at','ended_at','notes'] as $field) if(array_key_exists($field,$data)) { $fields[]=$field==='status'?'membership_status=?':$field.'=?'; $values[]=$data[$field]; }
        if (!$fields) return $this->badRequest('No PTA membership changes supplied');
        $values[]=(int)$id; $stmt=$this->getDb()->getConnection()->prepare('UPDATE parent_pta_memberships SET '.implode(',', $fields).' WHERE id=?'); $stmt->execute($values);
        return $this->success(['id'=>(int)$id],'PTA member updated');
    }

    public function deletePta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        if ($id===null) return $this->badRequest('PTA membership ID is required');
        $stmt=$this->getDb()->getConnection()->prepare('DELETE FROM parent_pta_memberships WHERE id=?'); $stmt->execute([(int)$id]);
        return $this->success(['id'=>(int)$id],'PTA member removed');
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
        if ($guard = $this->guardLiveExternalCommunication('SMS delivery')) return $guard;
        return $this->handleResponse($this->api->postSendSmsTemplate());
    }

    /**
     * Send WhatsApp with document attachments
     * POST /communications/send-whatsapp
     */
    public function postSendWhatsapp($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('WhatsApp delivery')) return $guard;
        return $this->handleResponse($this->api->postSendWhatsapp());
    }

    public function postSendWhatsappTemplate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('WhatsApp template delivery')) return $guard;
        return $this->handleResponse($this->api->postSendWhatsappTemplate());
    }

    public function postCreateWhatsappTemplate($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->postCreateWhatsappTemplate());
    }

    public function getAudienceOptions($id = null, $data = [], $segments = [])
    {
        $roles = $this->getUserRoleIds();
        $full = !empty(array_intersect($roles, [2, 3, 4, 5, 6, 10, 63]));
        $teacher = !empty(array_intersect($roles, [7, 8, 9])) && !$full;
        return $this->success(array_merge($this->api->getAudienceOptions(), [
            'allowed_audiences' => $teacher ? ['selected_students', 'selected_class', 'selected_parents'] : ['all_parents', 'selected_parents', 'selected_students', 'selected_class', 'student_type', 'school_level', 'all_staff', 'selected_staff', 'selected_vendors', 'all_vendors', 'contact_group', 'custom_numbers'],
        ]));
    }

    /**
     * Send SMS directly with message
     * POST /communications/send-sms
     */
    public function postSendSms($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('SMS delivery')) return $guard;
        return $this->handleResponse($this->api->postSendSms());
    }

    /**
     * Send Email directly with message
     * POST /communications/send-email
     */
    public function postSendEmail($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Email delivery')) return $guard;
        return $this->handleResponse($this->api->postSendEmail());
    }

    /**
     * Send Fee Reminder (SMS/WhatsApp)
     * POST /communications/fee-reminder
     * 
     * Sends a fee reminder message to a parent about outstanding balance
     * 
     * @param int|null $id
     * @param array $data Contains: student_id, phone, message, type (sms/whatsapp), balance
     * @param array $segments
     * @return array
     */
    public function postFeeReminder($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Fee reminder delivery')) return $guard;
        return $this->handleResponse($this->api->sendFeeReminder($data));
    }

    /**
     * Send Bulk Fee Reminders
     * POST /communications/fee-reminder-bulk
     * 
     * Sends fee reminder messages to multiple parents
     * 
     * @param int|null $id
     * @param array $data Contains: students (array of {student_id, phone, balance}), message_template, type
     * @param array $segments
     * @return array
     */
    public function postFeeReminderBulk($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Bulk fee reminder delivery')) return $guard;
        return $this->handleResponse($this->api->sendBulkFeeReminders($data));
    }

    // --- Communications CRUD ---
    public function getCommunication($id = null, $data = [], $segments = [])
    {
        $roles = $this->getUserRoleIds();
        $isManagement = !empty(array_intersect($roles, [2, 3, 4]));
        $isHeadteacher = in_array(5, $roles, true) && !$isManagement;
        if ($id !== null) {
            $data['visibility_user_id'] = (int) $this->getUserId();
            $data['visibility'] = $isManagement ? 'all' : ($isHeadteacher ? 'teacher_parent' : 'self_or_tagged');
            return $this->handleResponse($this->api->getCommunication($id, $data));
        }
        $data['visibility_user_id'] = (int) $this->getUserId();
        $data['visibility'] = $isManagement ? 'all' : ($isHeadteacher ? 'teacher_parent' : 'self_or_tagged');
        return $this->handleResponse($this->api->listCommunications($data));
    }
    public function postCommunication($id = null, $data = [], $segments = [])
    {
        if (DataScopeService::current() === 'test') {
            return $this->forbidden('External communication creation is blocked in the test workspace until scoped communication storage is enabled.');
        }
        $audience = strtolower((string) ($data['recipient_type'] ?? ''));
        $roles = $this->getUserRoleIds();
        $fullAudienceRoles = [2, 3, 4, 5, 6, 10, 63];
        $teacherRoles = [7, 8, 9];
        if (array_intersect($roles, $teacherRoles) && !array_intersect($roles, $fullAudienceRoles) && !in_array($audience, ['selected_students', 'selected_class', 'selected_parents'], true)) return $this->forbidden('Your role may message only assigned learner and parent audiences.');
        if (in_array($audience, ['selected_vendors', 'all_vendors'], true) && !array_intersect($roles, [2, 3, 4, 10])) return $this->forbidden('Vendor messaging is restricted to finance and school management roles.');
        return $this->handleResponse($this->api->createCommunication($data));
    }
    public function postDispatchCommunication($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Communication dispatch')) return $guard;
        if ($id === null || (int) $id < 1) {
            return $this->badRequest('Communication ID required');
        }
        return $this->handleResponse($this->api->dispatchCommunication((int) $id));
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

    private function validProviderCallback(array $data): bool
    {
        $atToken = defined('AFRICASTALKING_WEBHOOK_TOKEN') ? (string) AFRICASTALKING_WEBHOOK_TOKEN : '';
        if ($atToken !== '') {
            $provided = $_SERVER['HTTP_X_KINGSWAY_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? ($data['token'] ?? ''));
            return is_string($provided) && hash_equals($atToken, $provided);
        }
        $secret = defined('COMMUNICATION_WEBHOOK_SECRET') ? (string) COMMUNICATION_WEBHOOK_SECRET : '';
        if ($secret === '') {
            return strtolower((string) ($_ENV['APP_ENV'] ?? 'development')) !== 'production';
        }
        $provided = $_SERVER['HTTP_X_KINGSWAY_WEBHOOK_SECRET'] ?? ($data['webhook_secret'] ?? '');
        return is_string($provided) && hash_equals($secret, $provided);
    }

    /**
     * Resend a failed/pending communication (SMS, email, whatsapp)
     * POST /communications/resend
     */
    public function postResend($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Communication resend')) return $guard;
        return $this->handleResponse($this->api->resendCommunication($data));
    }

    private function guardLiveExternalCommunication(string $operation)
    {
        try {
            DataScopeService::requireLiveExternalAction($operation);
            return null;
        } catch (\DomainException $error) {
            return $this->forbidden($error->getMessage());
        }
    }

    // --- Internal User-to-User Messaging ---
    /**
     * GET /communications/conversations
     *   → list conversations for the current user.
     * GET /communications/conversations/{id}
     *   → full thread for one conversation (marks messages read).
     */
    public function getConversations($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->badRequest('Authentication required');
        }
        if ($id !== null) {
            return $this->handleResponse($this->api->getConversationThread($userId, $id));
        }
        return $this->handleResponse($this->api->listConversations($userId));
    }

    /**
     * POST /communications/conversations
     *   → create a conversation and send the first message.
     *     Body: { recipients: [userIds], subject?, message, priority? }
     * POST /communications/conversations/{id}
     *   → reply inside an existing conversation.
     *     Body: { message, priority? }
     */
    public function postConversations($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->badRequest('Authentication required');
        }
        if ($id !== null) {
            return $this->handleResponse($this->api->sendConversationReply($userId, $id, $data));
        }
        return $this->handleResponse($this->api->createConversation($userId, $data));
    }

    /**
     * GET /communications/recipients?q={term}
     *   → search active system users to message (excludes self).
     */
    public function getRecipients($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if ($userId === null) {
            return $this->badRequest('Authentication required');
        }
        $term = isset($data['q']) ? (string)$data['q'] : (string)($data['term'] ?? '');
        return $this->handleResponse($this->api->searchMessageRecipients($userId, $term));
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
        $roles = $this->getUserRoleIds();
        if (empty(array_intersect($roles, [2, 3, 4]))) {
            $data['created_by'] = (int) $this->getUserId();
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
