<?php
namespace App\API\Controllers;

use App\API\Modules\communications\CommunicationsAPI;
use App\API\Modules\communications\TemplatesAPI;
use App\API\Services\AiDraftService;
use App\API\Services\AiWorkflowService;
use App\API\Services\DataScopeService;
use App\API\Services\EmailProfileService;
use App\API\Services\EmailInboxService;
use App\API\Services\MailboxMailService;
use App\API\Services\UploadService;
use DomainException;
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
 *  - Callback endpoints use the application file-journal logging policy for
 *    audit/debug without writing directly to the PHP error log.
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
    private TemplatesAPI $templatesApi;
    private EmailProfileService $emailProfileService;
    private EmailInboxService $emailInboxService;
    private MailboxMailService $mailboxMailService;

    public function __construct()
    {
        parent::__construct();
        $this->api = $this->contract('App\API\Modules\communications\CommunicationsAPI');
        $this->templatesApi = $this->contract('App\API\Modules\communications\TemplatesAPI');
        $this->emailProfileService = $this->contract('App\API\Services\EmailProfileService',
            $this->getDb()->getConnection()
        );
        $this->emailInboxService = $this->contract('App\API\Services\EmailInboxService',
            $this->getDb()->getConnection(),
            $this->emailProfileService
        );
        $this->mailboxMailService = $this->contract('App\API\Services\MailboxMailService',
            $this->getDb()->getConnection(),
            $this->emailProfileService,
            $this->emailInboxService,
            new UploadService()
        );
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

    /** GET /api/communications/sms-balance */
    public function getSmsBalance($id = null, $data = [], $segments = [])
    {
        try {
            $service = $this->contract(
                'App\API\Services\SystemCommsConfigService',
                $this->getDb()->getConnection()
            );
            $result = $service->getSmsBalance();
        } catch (\Throwable $e) {
            return $this->badRequest('Unable to retrieve SMS balance.');
        }

        return $this->success([
            'balance' => $result['balance'] ?? 0,
            'available' => !empty($result['success']),
        ], $result['message'] ?? 'Balance retrieved');
    }

    // --- Provider-agnostic messaging surface (SMS/WhatsApp/Voice/MMS/Hashed) ---

    private function smsService(): \App\API\Services\sms\SMSService
    {
        return $this->contract('App\API\Services\sms\SMSService', $this->getDb()->getConnection());
    }

    /** GET /api/communications/sms-gateway */
    public function getSmsGateway($id = null, $data = [], $segments = [])
    {
        return $this->success($this->smsService()->gatewayInfo());
    }

    /** GET /api/communications/sms-profile */
    public function getSmsProfile($id = null, $data = [], $segments = [])
    {
        return $this->success($this->smsService()->profile());
    }

    /** GET /api/communications/sms-groups */
    public function getSmsGroups($id = null, $data = [], $segments = [])
    {
        return $this->success(['groups' => $this->smsService()->groups()]);
    }

    /** GET /api/communications/remote-sms-groups */
    public function getRemoteSmsGroups($id = null, $data = [], $segments = [])
    {
        return $this->success($this->smsService()->remoteGroups());
    }

    /** POST /api/communications/sms-groups */
    public function postSmsGroups($id = null, $data = [], $segments = [])
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->badRequest('Group name is required');
        }
        try {
            return $this->created($this->smsService()->createGroup($name), 'Contact group created');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** PUT /api/communications/sms-groups/{id} */
    public function putSmsGroups($id = null, $data = [], $segments = [])
    {
        if ($id === null || (int) $id < 1) {
            return $this->badRequest('Group ID required');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return $this->badRequest('Group name is required');
        }
        try {
            return $this->success($this->smsService()->updateGroup((int) $id, $name), 'Contact group updated');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** DELETE /api/communications/sms-groups/{id} */
    public function deleteSmsGroups($id = null, $data = [], $segments = [])
    {
        if ($id === null || (int) $id < 1) {
            return $this->badRequest('Group ID required');
        }
        try {
            return $this->success($this->smsService()->deleteGroup((int) $id), 'Contact group deleted');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/communications/sms-groups-sync */
    public function postSmsGroupsSync($id = null, $data = [], $segments = [])
    {
        try {
            return $this->success($this->smsService()->syncGroups(), 'Groups synchronised from provider');
        } catch (\Throwable $e) {
            return $this->badRequest('Group sync failed: ' . $e->getMessage());
        }
    }

    /** POST /api/communications/sms-groups-provision */
    public function postSmsGroupsProvision($id = null, $data = [], $segments = [])
    {
        try {
            return $this->success($this->smsService()->provisionGroups(), 'Groups provisioned with provider');
        } catch (\Throwable $e) {
            return $this->badRequest('Group provisioning failed: ' . $e->getMessage());
        }
    }

    /** GET /api/communications/sms-contacts?group_id= */
    public function getSmsContacts($id = null, $data = [], $segments = [])
    {
        $groupId = (int) ($data['group_id'] ?? $_GET['group_id'] ?? $id ?? 0);
        if ($groupId < 1) {
            return $this->badRequest('group_id is required');
        }
        $search = (string) ($data['search'] ?? $_GET['search'] ?? '');
        return $this->success(['contacts' => $this->smsService()->contacts($groupId, $search)]);
    }

    /** POST /api/communications/sms-contacts */
    public function postSmsContacts($id = null, $data = [], $segments = [])
    {
        $groupId = (int) ($data['group_id'] ?? 0);
        if ($groupId < 1) {
            return $this->badRequest('group_id is required');
        }
        try {
            return $this->created($this->smsService()->addContact($groupId, $data), 'Contact added');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** PUT /api/communications/sms-contacts/{id} */
    public function putSmsContacts($id = null, $data = [], $segments = [])
    {
        if ($id === null || (int) $id < 1) {
            return $this->badRequest('Contact ID required');
        }
        try {
            return $this->success($this->smsService()->updateContact((int) $id, $data), 'Contact updated');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** DELETE /api/communications/sms-contacts/{id} */
    public function deleteSmsContacts($id = null, $data = [], $segments = [])
    {
        if ($id === null || (int) $id < 1) {
            return $this->badRequest('Contact ID required');
        }
        try {
            return $this->success($this->smsService()->deleteContact((int) $id), 'Contact deleted');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        }
    }

    /** POST /api/communications/sms-contacts-sync */
    public function postSmsContactsSync($id = null, $data = [], $segments = [])
    {
        $groupId = (int) ($data['group_id'] ?? 0);
        if ($groupId < 1) {
            return $this->badRequest('group_id is required');
        }
        try {
            return $this->success($this->smsService()->syncContacts($groupId), 'Contacts synchronised');
        } catch (\Throwable $e) {
            return $this->badRequest('Contact sync failed: ' . $e->getMessage());
        }
    }

    /** POST /api/communications/sms-contacts-import */
    public function postSmsContactsImport($id = null, $data = [], $segments = [])
    {
        $groupId = (int) ($data['group_id'] ?? $_GET['group_id'] ?? 0);
        if ($groupId < 1) {
            return $this->badRequest('group_id is required');
        }
        try {
            $result = $this->smsService()->importAudience($groupId);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            return $this->badRequest('Import failed: ' . $e->getMessage());
        }

        $message = sprintf(
            'Imported %d new contact(s) (%d refreshed, %d removed).',
            (int) ($result['added'] ?? 0),
            (int) ($result['updated'] ?? 0),
            (int) ($result['removed'] ?? 0)
        );
        return $this->success($result, $message);
    }

    /** GET /api/communications/sms-audience?entity_type=parents|staff|students|vendors */
    public function getSmsAudience($id = null, $data = [], $segments = [])
    {
        $entityType = strtolower((string) ($data['entity_type'] ?? $_GET['entity_type'] ?? ''));
        if ($entityType === '') {
            return $this->badRequest('entity_type is required');
        }
        return $this->success(['contacts' => $this->smsService()->resolveAudience($entityType)]);
    }

    /** POST /api/communications/sms-send */
    public function postSmsSend($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardLiveExternalCommunication('Message delivery')) return $guard;
        try {
            return $this->success($this->smsService()->send($data), 'Message queued');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            return $this->badRequest('Message could not be sent: ' . $e->getMessage());
        }
    }

    /** GET /api/communications/sms-messages */
    public function getSmsMessages($id = null, $data = [], $segments = [])
    {
        return $this->success($this->smsService()->listMessages($data));
    }

    /** GET /api/communications/remote-sms-messages */
    public function getRemoteSmsMessages($id = null, $data = [], $segments = [])
    {
        return $this->success($this->smsService()->remoteMessages());
    }

    /** GET /api/communications/remote-sms-messages/{uid} */
    public function getRemoteSmsMessage($id = null, $data = [], $segments = [])
    {
        $uid = (string) ($id ?? $data['uid'] ?? $_GET['uid'] ?? '');
        if ($uid === '') {
            return $this->badRequest('Message UID required');
        }
        return $this->success($this->smsService()->remoteMessage($uid));
    }

    /** GET /api/communications/sms-queue?uid= */
    public function getSmsQueue($id = null, $data = [], $segments = [])
    {
        $uid = (string) ($data['uid'] ?? $_GET['uid'] ?? $id ?? '');
        if ($uid === '') {
            return $this->badRequest('queue uid is required');
        }
        return $this->success($this->smsService()->queueStatus($uid));
    }

    /** POST /api/communications/ai-message-draft-queue */
    public function postAiMessageDraftQueue($id = null, $data = [], $segments = [])
    {
        $context = $this->aiCommunicationContext();
        try {
            $this->contract(AiWorkflowService::class)->authorize('communications.parent_message_draft', $context);
        } catch (DomainException $e) {
            return $this->forbidden('Communication AI assistance is not available for your account');
        }
        $input = [
            'purpose' => trim((string) ($data['purpose'] ?? '')),
            'audience' => trim((string) ($data['audience'] ?? '')),
            'tone' => trim((string) ($data['tone'] ?? 'professional')),
            'facts' => $data['facts'] ?? '',
            'deadline' => trim((string) ($data['deadline'] ?? '')),
            'channel' => strtolower(trim((string) ($data['channel'] ?? 'email'))),
        ];
        if ($input['purpose'] === '' || $input['audience'] === '' || $input['facts'] === '') {
            return $this->badRequest('Purpose, audience, and approved facts are required');
        }
        if (!in_array($input['channel'], ['email', 'sms', 'whatsapp', 'internal'], true)) {
            return $this->badRequest('Unsupported communication channel');
        }
        if (!in_array($input['audience'], ['all_parents', 'selected_parents', 'selected_class', 'all_staff', 'selected_staff'], true)) {
            return $this->badRequest('Unsupported communication audience');
        }
        if (is_string($input['facts']) && mb_strlen($input['facts']) > 4000) {
            return $this->badRequest('Approved facts are too long');
        }

        try {
            $service = $this->contract(AiDraftService::class);
            $queued = $service->queue(
                'communications.parent_message_draft',
                $context,
                $input,
                [
                    'subject_type' => 'communication_draft',
                    'subject_id' => 0,
                    'channel' => $input['channel'],
                    'audience' => $input['audience'],
                ]
            );
            return $this->accepted($queued, 'Communication draft queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] AI draft queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue communication assistance');
        }
    }

    /** GET /api/communications/ai-drafts?scope=own|review */
    public function getAiDrafts($id = null, $data = [], $segments = [])
    {
        $scope = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own'));
        $review = $scope === 'review';
        if ($review && !$this->canApproveAiCommunication()) {
            return $this->forbidden('Communication approval permission is required to review AI drafts');
        }

        try {
            $service = $this->contract(AiDraftService::class);
            return $this->success([
                'drafts' => $service->listForReview(
                    $this->getDb()->getConnection(),
                    (int) ($this->getUserId() ?? 0),
                    $review,
                    'communications'
                ),
                'scope' => $review ? 'review' : 'own',
            ], 'Communication AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] AI draft list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load communication assistance');
        }
    }

    /** POST /api/communications/ai-draft-approve/{id} */
    public function postAiDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->canApproveAiCommunication()) {
            return $this->forbidden('Communication approval permission is required to approve AI drafts');
        }
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');

        try {
            $service = $this->contract(AiDraftService::class);
            $approved = $service->approve($this->getDb()->getConnection(), $draftId, (int) ($this->getUserId() ?? 0), 'communications.parent_message_draft');
            $metadata = (array) ($approved['metadata'] ?? []);
            if (!empty($metadata['materialized_communication_id'])) {
                return $this->success($approved + ['communication_id' => (int) $metadata['materialized_communication_id']], 'Communication draft already prepared');
            }

            $draft = (array) ($approved['draft'] ?? []);
            $channel = strtolower((string) ($metadata['channel'] ?? 'email'));
            $audience = (string) ($metadata['audience'] ?? '');
            if (!in_array($channel, ['email', 'sms', 'whatsapp', 'internal'], true) || $audience === '') {
                throw new DomainException('The approved communication draft has incomplete delivery metadata.', 422);
            }
            $communication = $this->api->createCommunication([
                'sender_id' => (int) ($this->getUserId() ?? 0),
                'subject' => (string) ($draft['title'] ?? 'AI communication draft'),
                'body' => (string) ($draft['body'] ?? ''),
                'type' => $channel,
                'status' => 'draft',
                'audit_bcc' => 1,
                'recipient_type' => $audience,
                'sender_signature' => json_encode([
                    'ai_draft_id' => $draftId,
                    'created_by_ai' => true,
                    'requires_recipient_review' => true,
                ], JSON_UNESCAPED_SLASHES),
            ]);
            $communicationId = (int) ($communication['id'] ?? $communication['communication_id'] ?? 0);
            if ($communicationId < 1) {
                throw new DomainException('Communication draft could not be created.', 500);
            }
            $service->markMaterialized($this->getDb()->getConnection(), $draftId, $communicationId);
            return $this->success([
                'draft_id' => $draftId,
                'communication_id' => $communicationId,
                'status' => 'draft',
            ], 'AI content approved and saved as a communication draft');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] AI draft approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve communication assistance');
        }
    }

    private function aiCommunicationContext(): array
    {
        return [
            'user_id' => (int) ($this->getUserId() ?? 0),
            'permissions' => array_values(array_unique(array_merge(
                (array) ($this->user['effective_permissions'] ?? []),
                (array) ($this->user['permissions'] ?? [])
            ))),
            'request_id' => $_SERVER['REQUEST_ID'] ?? '',
        ];
    }

    private function canApproveAiCommunication(): bool
    {
        $context = $this->aiCommunicationContext();
        try {
            $this->contract(AiWorkflowService::class)->authorize(
                'communications.parent_message_draft',
                $context
            );
            return true;
        } catch (DomainException $e) {
            return false;
        }
    }

    /** Internal worker endpoint called by the curl crontab. */
    public function postProcessOutbox($id = null, $data = [], $segments = [])
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string) COMMUNICATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->forbidden('Invalid worker credential');
        }
        $limit = max(1, min(100, (int) ($data['limit'] ?? 25)));
        $result = ($this->contract('App\API\Services\CommunicationOutboxService', $this->getDb()->getConnection()))->processPending($limit);
        return $this->success($result, 'Communication outbox processed');
    }

    /** POST /api/communications/sync-sms-contacts
     *  Provider-agnostic reconciliation of contact groups and contacts.
     *  Requires X-Kingsway-Worker-Secret authentication.
     *  Pulls provider groups, provisions missing local groups, pushes contacts.
     */
    public function postSyncSmsContacts($id = null, $data = [], $segments = [])
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string) COMMUNICATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        if ($expected === '' || !is_string($provided) || !hash_equals($expected, $provided)) {
            return $this->forbidden('Invalid worker credential');
        }

        try {
            $result = $this->smsService()->syncAll();
        } catch (\Throwable $e) {
            return $this->badRequest('Contact sync failed: ' . $e->getMessage());
        }

        return $this->success($result, 'Contacts synced successfully');
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
        $service = $this->contract(\App\API\Services\ParentPtaMembershipService::class, $this->getDb()->getConnection());
        if ($id !== null) {
            $row = $service->find((int) $id);
            return $row ? $this->success($row) : $this->notFound('PTA membership not found');
        }
        return $this->success($service->listAll());
    }

    public function postPta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        $parentId=(int)($data['parent_id']??0); $role=trim((string)($data['role']??'Member'));
        if (!$parentId) return $this->badRequest('parent_id is required');
        $service = $this->contract(\App\API\Services\ParentPtaMembershipService::class, $this->getDb()->getConnection());
        if (!$service->parentExists($parentId)) return $this->badRequest('Parent record not found');
        $data['parent_id'] = $parentId; $data['role'] = $role;
        return $this->success(['id'=>$service->create($data, (int)$this->getUserId())],'PTA member added');
    }

    public function putPta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        if ($id===null) return $this->badRequest('PTA membership ID is required');
        try {
            ($this->contract(\App\API\Services\ParentPtaMembershipService::class, $this->getDb()->getConnection()))->update((int)$id, $data);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }
        return $this->success(['id'=>(int)$id],'PTA member updated');
    }

    public function deletePta($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->requirePtaAccess()) return $guard;
        if ($id===null) return $this->badRequest('PTA membership ID is required');
        ($this->contract(\App\API\Services\ParentPtaMembershipService::class, $this->getDb()->getConnection()))->delete((int)$id);
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
            'allowed_audiences' => $teacher ? ['selected_students', 'selected_class', 'selected_parents', 'custom_selection'] : ['all_parents', 'selected_parents', 'selected_students', 'selected_class', 'student_type', 'school_level', 'all_staff', 'selected_staff', 'selected_vendors', 'all_vendors', 'contact_group', 'custom_selection', 'custom_numbers'],
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
        $data['audit_bcc'] = 1;
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
        $secret = defined('COMMUNICATION_WEBHOOK_SECRET') ? (string) COMMUNICATION_WEBHOOK_SECRET : '';
        if ($secret === '') {
            return strtolower((string) ($_ENV['APP_ENV'] ?? 'development')) !== 'production';
        }
        $provided = $_SERVER['HTTP_X_KINGSWAY_WEBHOOK_SECRET']
            ?? ($_SERVER['HTTP_X_KINGSWAY_WEBHOOK_TOKEN'] ?? ($_GET['token'] ?? ($data['webhook_secret'] ?? ($data['token'] ?? ''))));
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

    /**
     * GET /communications/audience-search?q={term}&limit={n}
     *   → unified recipient search for the shared recipient picker.
     */
    public function getAudienceSearch($id = null, $data = [], $segments = [])
    {
        $query = (string) ($data['q'] ?? $data['term'] ?? '');
        $limit = (int) ($data['limit'] ?? 30);
        $filters = [
            'type' => (string) ($data['type'] ?? ''),
            'class_id' => (int) ($data['class_id'] ?? 0),
            'stream_id' => (int) ($data['stream_id'] ?? 0),
            'student_type_id' => (int) ($data['student_type_id'] ?? 0),
            'school_level_id' => (int) ($data['school_level_id'] ?? 0),
            'role_id' => (int) ($data['role_id'] ?? 0),
            'group_id' => (int) ($data['group_id'] ?? 0),
        ];
        return $this->handleResponse($this->api->searchAudienceRecipients($query, $limit, $filters));
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

    // --- Advanced Templates CRUD (new catalog system) ---
    /** GET /api/communications/templates?channel=sms&status=active | /templates/{id} */
    public function getTemplates($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            return $this->handleResponse($this->templatesApi->getTemplate(['id' => (int) $id]));
        }
        return $this->handleResponse($this->templatesApi->getTemplates($data));
    }

    /** POST /api/communications/templates */
    public function postTemplates($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postTemplate($data));
    }

    /** PUT /api/communications/templates/{id} */
    public function putTemplates($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        $data['id'] = (int) $id;
        return $this->handleResponse($this->templatesApi->putTemplate($data));
    }

    /** DELETE /api/communications/templates/{id} */
    public function deleteTemplates($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('ID required');
        }
        $data['id'] = (int) $id;
        return $this->handleResponse($this->templatesApi->deleteTemplate($data));
    }

    /** GET /api/communications/templates/channels/{channelId} */
    public function getTemplatesChannels($id = null, $data = [], $segments = [])
    {
        $channelId = (int) ($id ?? 0);
        if (!$channelId) {
            return $this->badRequest('Channel ID required');
        }
        $data['channel_id'] = $channelId;
        return $this->handleResponse($this->templatesApi->getTemplateChannel($data));
    }

    /** PUT /api/communications/templates/channels/{channelId} */
    public function putTemplatesChannels($id = null, $data = [], $segments = [])
    {
        $channelId = (int) ($id ?? 0);
        if (!$channelId) {
            return $this->badRequest('Channel ID required');
        }
        $data['channel_id'] = $channelId;
        return $this->handleResponse($this->templatesApi->putTemplateChannel($data));
    }

    /** POST /api/communications/templates/{id}/versions */
    public function postTemplatesVersions($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Template ID required');
        }
        $data['id'] = (int) $id;
        return $this->handleResponse($this->templatesApi->postTemplateVersion($data));
    }

    /** POST /api/communications/templates/versions/{versionId}/approve */
    public function postTemplatesVersionsApprove($id = null, $data = [], $segments = [])
    {
        $versionId = (int) ($id ?? 0);
        if (!$versionId) {
            return $this->badRequest('Version ID required');
        }
        $data['version_id'] = $versionId;
        return $this->handleResponse($this->templatesApi->postApproveVersion($data));
    }

    /** POST /api/communications/templates/preview */
    public function postTemplatesPreview($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postPreview($data));
    }

    /** POST /api/communications/templates/extract-placeholders */
    public function postTemplatesExtractPlaceholders($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postExtractPlaceholders($data));
    }

    /** POST /api/communications/templates/validate-variables */
    public function postTemplatesValidateVariables($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postValidateVariables($data));
    }

    /** POST /api/communications/templates/estimate-sms-cost */
    public function postTemplatesEstimateSmsCost($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postEstimateSmsCost($data));
    }

    /** GET /api/communications/lists */
    public function getLists($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->listGroups($data));
    }

    /** GET /api/communications/channels */
    public function getChannels($id = null, $data = [], $segments = [])
    {
        return $this->success([
            'channels' => [
                ['id' => 'sms', 'name' => 'SMS', 'type' => 'messaging', 'is_active' => true],
                ['id' => 'whatsapp', 'name' => 'WhatsApp', 'type' => 'messaging', 'is_active' => true],
                ['id' => 'email', 'name' => 'Email', 'type' => 'messaging', 'is_active' => true],
                ['id' => 'in_app', 'name' => 'In-App', 'type' => 'internal', 'is_active' => true],
                ['id' => 'portal', 'name' => 'Parent Portal', 'type' => 'internal', 'is_active' => true],
            ],
        ]);
    }

    /** GET /api/communications/templates/code/{code} */
    public function getTemplateCode($id = null, $data = [], $segments = [])
    {
        $code = $segments[0] ?? ($data['code'] ?? '');
        if (!$code) {
            return $this->badRequest('Template code required');
        }
        $data['code'] = $code;
        return $this->handleResponse($this->templatesApi->getTemplateByCode($data));
    }

    /** POST /api/communications/templates/{id}/versions */
    public function postTemplateVersion($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Template ID required');
        }
        $data['id'] = $id;
        return $this->handleResponse($this->templatesApi->postTemplateVersion($data));
    }

    /** PUT /api/communications/templates/channels/{channelId} */
    public function putTemplateChannel($id = null, $data = [], $segments = [])
    {
        $channelId = (int) ($id ?? $data['channel_id'] ?? 0);
        if (!$channelId) {
            return $this->badRequest('Channel ID required');
        }
        $data['channel_id'] = $channelId;
        return $this->handleResponse($this->templatesApi->putTemplateChannel($data));
    }

    /** POST /api/communications/templates/versions/{versionId}/approve */
    public function postApproveVersion($id = null, $data = [], $segments = [])
    {
        $versionId = (int) ($id ?? $data['version_id'] ?? 0);
        if (!$versionId) {
            return $this->badRequest('Version ID required');
        }
        $data['version_id'] = $versionId;
        return $this->handleResponse($this->templatesApi->postApproveVersion($data));
    }

    /** POST /api/communications/templates/preview */
    public function postTemplatePreview($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postPreview($data));
    }

    /** POST /api/communications/templates/extract-placeholders */
    public function postExtractPlaceholders($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postExtractPlaceholders($data));
    }

    /** POST /api/communications/templates/validate-variables */
    public function postValidateVariables($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postValidateVariables($data));
    }

    /** POST /api/communications/templates/estimate-sms-cost */
    public function postEstimateSmsCost($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->templatesApi->postEstimateSmsCost($data));
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
                    if (array_key_exists('data', $result)) {
                        return $this->success($result['data'], $result['message'] ?? 'Success');
                    }
                    // Module payloads (e.g. TemplatesAPI) return their own keys
                    // (templates, template, channel, rendered, ...) without a
                    // 'data' wrapper. Forward them intact so callers receive the
                    // payload instead of null.
                    return $this->success($result, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }
        return $this->success($result);
    }

    /**
     * School-domain mailbox endpoints. Access rule (EmailProfileService):
     * School Admin (role 4) sees ALL mailboxes with send access; personal
     * mailboxes belong to their owner plus explicit members; group mailboxes
     * honour explicit members and the legacy assigned_role_ids rule (empty
     * list = all staff). System Administrator is not granted mailbox
     * visibility here — configuration only.
     */

    private function mailboxAccessContext(): array
    {
        $roles = $this->getUserRoleIds();

        return [
            'user_id' => (int) $this->getUserId(),
            'roles' => $roles,
            'is_school_admin' => in_array(4, $roles, true),
            'is_system_admin' => $this->userHasRole('System Administrator') || $this->userHasPermission('*'),
        ];
    }

    private function accessibleMailboxProfiles(): array
    {
        $context = $this->mailboxAccessContext();

        return $this->emailProfileService->mailboxesForUser(
            $context['user_id'],
            $context['roles'],
            $context['is_school_admin'],
            $context['is_system_admin']
        );
    }

    private function resolveAccessibleProfile(int $profileId, string $capability = 'read'): ?array
    {
        foreach ($this->accessibleMailboxProfiles() as $profile) {
            if ((int) $profile['id'] === $profileId && !empty($profile['can_' . $capability])) {
                return $profile;
            }
        }
        return null;
    }

    /**
     * Resolve a mailbox with decrypted credentials after checking access. At
     * least one of $capabilities ('read','reply','compose') must be granted.
     *
     * @param string[] $capabilities
     */
    private function resolveMailboxCredentials(int $profileId, array $capabilities = ['read']): ?array
    {
        $context = $this->mailboxAccessContext();

        $resolved = $this->emailProfileService->resolveAccessibleProfile(
            $profileId,
            $context['user_id'],
            $context['roles'],
            $context['is_school_admin'],
            $context['is_system_admin']
        );

        if ($resolved === null) {
            return null;
        }

        foreach ($capabilities as $capability) {
            if (!empty($resolved['can_' . $capability])) {
                return $resolved;
            }
        }

        return null;
    }

    private function maskProfile(array $profile): array
    {
        unset($profile['smtp_password'], $profile['imap_password'], $profile['smtp_pass'], $profile['imap_pass']);
        return $profile;
    }

    /**
     * Mailbox access management (assign who may read/reply/compose) is a School
     * Administrator responsibility; System Administrator configures accounts
     * only and does not grant mailbox access.
     */
    private function ensureMailboxManagementAccess()
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if ($this->userHasRole('School Administrator') || $this->userHasPermission('*')) {
            return null;
        }
        return $this->forbidden('School Administrator access is required to manage mailbox access.');
    }

    // GET /api/communications/mailboxes/{id}/members
    public function getMailboxesMembers($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureMailboxManagementAccess()) {
            return $auth;
        }

        $profileId = (int) ($id ?? ($data['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }

        try {
            $members = $this->emailProfileService->listMembers($profileId);
            return $this->success([
                'members'    => $members,
                'mailbox'    => $this->maskProfile((array) $this->emailProfileService->getProfile($profileId)),
            ], 'Mailbox members retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] member list failed: ' . $e->getMessage());
            return $this->serverError('Mailbox members could not be retrieved');
        }
    }

    // PUT /api/communications/mailboxes/{id}/members
    public function putMailboxesMembers($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureMailboxManagementAccess()) {
            return $auth;
        }

        $profileId = (int) ($id ?? ($data['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }

        $members = $data['members'] ?? [];
        if (!is_array($members)) {
            return $this->badRequest('members must be an array of {user_id, can_read, can_reply, can_compose}.');
        }

        try {
            $result = $this->emailProfileService->setMembers($profileId, $members, (int) $this->getUserId());
            return $this->success(['members' => $result], 'Mailbox access updated');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            return $this->notFound('Email profile not found');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] member update failed: ' . $e->getMessage());
            return $this->serverError('Mailbox access could not be updated');
        }
    }

    public function getMailboxes($id = null, $data = [], $segments = [])
    {
        $profiles = array_map(fn ($p) => $this->maskProfile($p), $this->accessibleMailboxProfiles());
        return $this->success(['mailboxes' => $profiles], 'Mailboxes retrieved');
    }

    public function getMailboxesFolders($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($id ?? 0);
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }
        if (!$this->resolveAccessibleProfile($profileId)) {
            return $this->forbidden('Mailbox not accessible to your role');
        }
        try {
            $result = $this->emailInboxService->listMailboxes($profileId);
            if (!($result['success'] ?? false)) {
                return $this->badRequest($result['message'] ?? 'Unable to connect to mailbox.');
            }
            return $this->success([
                'folders' => $result['folders'] ?? [],
                'profile' => $result['profile'] ?? null,
            ], 'Folders retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] folder listing failed: ' . $e->getMessage());
            return $this->badRequest('Unable to connect to mailbox. Please contact the System Administrator.');
        }
    }

    public function getMailboxesMessages($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($id ?? 0);
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }
        if (!$this->resolveAccessibleProfile($profileId)) {
            return $this->forbidden('Mailbox not accessible to your role');
        }
        $folder = (string) ($data['folder'] ?? 'INBOX');
        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($data['per_page'] ?? 25)));
        $filters = [
            'q'       => $data['q'] ?? null,
            'unread'  => $data['unread'] ?? null,
            'flagged' => $data['flagged'] ?? null,
            'from'    => $data['from'] ?? null,
            'subject' => $data['subject'] ?? null,
            'since'   => $data['since'] ?? null,
            'before'  => $data['before'] ?? null,
        ];
        try {
            $result = $this->emailInboxService->listMessages($profileId, $folder, $page, $perPage, $filters);
            return $this->success($result, 'Messages retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] message listing failed: ' . $e->getMessage());
            return $this->badRequest('Unable to read mailbox. Please contact the System Administrator.');
        }
    }

    public function getMailboxesMessage($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($data['profile_id'] ?? $segments[0] ?? 0);
        $messageId = (string) ($id ?? $data['message_id'] ?? '');
        $folder = (string) ($data['folder'] ?? 'INBOX');
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }
        if ($messageId === '') {
            return $this->badRequest('Message ID required');
        }
        if (!$this->resolveAccessibleProfile($profileId)) {
            return $this->forbidden('Mailbox not accessible to your role');
        }
        try {
            $message = $this->emailInboxService->getMessage($profileId, $folder, $messageId);
            return $this->success(['message' => $message], 'Message retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] message read failed: ' . $e->getMessage());
            return $this->badRequest('Unable to read message. Please contact the System Administrator.');
        }
    }

    /**
     * POST /api/communications/mailboxes/{profileId}/send
     */
    public function postMailboxesSend($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($id ?? ($data['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }

        $resolved = $this->resolveMailboxCredentials($profileId, ['reply', 'compose']);
        if ($resolved === null) {
            return $this->forbidden('You do not have send access to this mailbox.');
        }

        try {
            $result = $this->mailboxMailService->send($resolved['profile'], (int) $this->getUserId(), $data);
            return $this->success($result, $result['warning'] ?? 'Message sent successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] mailbox send failed: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * POST /api/communications/mailboxes/{profileId}/drafts — IMAP APPEND to
     * the account's Drafts folder, optionally replacing an existing draft.
     */
    public function postMailboxesDrafts($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($id ?? ($data['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }

        $resolved = $this->resolveMailboxCredentials($profileId, ['compose']);
        if ($resolved === null) {
            return $this->forbidden('You do not have compose access to this mailbox.');
        }

        $replaceUid = isset($data['draft_uid']) && $data['draft_uid'] !== '' ? (string) $data['draft_uid'] : null;

        try {
            $result = $this->mailboxMailService->saveDraft($resolved['profile'], (int) $this->getUserId(), $data, $replaceUid);
            return $this->success($result, 'Draft saved');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] draft save failed: ' . $e->getMessage());
            return $this->badRequest('Draft could not be saved: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/communications/mailboxes/messages/{uid} — flag/move/copy/delete
     * a message. Body: profile_id, folder, action (flags|move|copy|delete),
     * add[], remove[], target, permanent.
     */
    public function postMailboxesMessages($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($data['profile_id'] ?? 0);
        $uid = (string) ($id ?? ($data['uid'] ?? ''));
        $folder = (string) ($data['folder'] ?? 'INBOX');
        $action = strtolower(trim((string) ($data['action'] ?? 'flags')));

        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }
        if ($uid === '') {
            return $this->badRequest('Message UID required');
        }

        $resolved = $this->resolveMailboxCredentials($profileId, ['read']);
        if ($resolved === null) {
            return $this->forbidden('You do not have access to this mailbox.');
        }

        try {
            switch ($action) {
                case 'flags':
                    $add = is_array($data['add'] ?? null) ? $data['add'] : explode(',', (string) ($data['add'] ?? ''));
                    $remove = is_array($data['remove'] ?? null) ? $data['remove'] : explode(',', (string) ($data['remove'] ?? ''));
                    $result = $this->emailInboxService->setFlags($profileId, $folder, $uid, $add, $remove);
                    break;

                case 'move':
                    $target = trim((string) ($data['target'] ?? ''));
                    if ($target === '') {
                        return $this->badRequest('Target folder required for move.');
                    }
                    $result = $this->emailInboxService->moveMessage($profileId, $folder, $uid, $target);
                    break;

                case 'copy':
                    $target = trim((string) ($data['target'] ?? ''));
                    if ($target === '') {
                        return $this->badRequest('Target folder required for copy.');
                    }
                    $result = $this->emailInboxService->copyMessage($profileId, $folder, $uid, $target);
                    break;

                case 'delete':
                    $permanent = filter_var($data['permanent'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    $result = $this->emailInboxService->deleteMessage($profileId, $folder, $uid, $permanent);
                    break;

                default:
                    return $this->badRequest('Unsupported message action: ' . $action);
            }

            return $this->success($result, $result['message'] ?? 'Message updated');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] message action failed: ' . $e->getMessage());
            return $this->badRequest('The message could not be updated: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/communications/mailboxes/attachments — multipart upload for
     * compose attachments (communication_attachment category).
     */
    public function postMailboxesAttachments($id = null, $data = [], $segments = [])
    {
        $resolved = $this->resolveMailboxCredentials((int) ($data['profile_id'] ?? 0), ['compose']);
        if ($resolved === null) {
            return $this->forbidden('You do not have compose access to this mailbox.');
        }

        $files = $_FILES['file'] ?? $_FILES['files'] ?? null;
        if ($files === null) {
            return $this->badRequest('No file uploaded.');
        }

        $normalized = [];
        if (is_array($files['name'])) {
            foreach (array_keys($files['name']) as $index) {
                $normalized[] = [
                    'name'     => (string) $files['name'][$index],
                    'type'     => (string) ($files['type'][$index] ?? ''),
                    'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
                    'error'    => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size'     => (int) ($files['size'][$index] ?? 0),
                ];
            }
        } else {
            $normalized[] = $files;
        }

        if (count($normalized) > MailboxMailService::MAX_ATTACHMENTS) {
            return $this->badRequest('At most ' . MailboxMailService::MAX_ATTACHMENTS . ' attachments are allowed per message.');
        }

        $uploadService = new UploadService();
        $stored = [];
        try {
            foreach ($normalized as $file) {
                if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    return $this->badRequest('Attachment upload failed: ' . (string) ($file['name'] ?? 'file'));
                }
                $result = $uploadService->store($file, 'communication_attachment', [
                    'owner_id' => (string) $this->getUserId(),
                    'prefix'   => 'mail',
                ]);
                $stored[] = [
                    'path' => $result['relative_path'],
                    'name' => $result['original_filename'],
                    'mime' => $result['mime_type'],
                    'size' => $result['file_size_bytes'],
                ];
            }
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] attachment upload failed: ' . $e->getMessage());
            return $this->badRequest('Attachment upload failed: ' . $e->getMessage());
        }

        return $this->success(['attachments' => $stored], 'Attachments uploaded');
    }

    /**
     * GET /api/communications/mailboxes/messages/attachments/{index} —
     * stream one attachment. Query: profile_id, folder, uid.
     */
    public function getMailboxesMessagesAttachments($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($data['profile_id'] ?? 0);
        $uid = (string) ($data['uid'] ?? '');
        $folder = (string) ($data['folder'] ?? 'INBOX');
        $index = (int) ($id ?? ($data['index'] ?? -1));

        if ($profileId <= 0 || $uid === '' || $index < 0) {
            return $this->badRequest('profile_id, uid and attachment index are required.');
        }
        if (!$this->resolveAccessibleProfile($profileId)) {
            return $this->forbidden('Mailbox not accessible to your role');
        }

        $attachment = $this->emailInboxService->getAttachmentContent($profileId, $folder, $uid, $index);
        if ($attachment === null) {
            return $this->badRequest('Attachment not found.');
        }

        $filename = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $attachment['filename']) ?: 'attachment';
        header('Content-Type: ' . (string) ($attachment['mime'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $attachment['content']));
        header('X-Content-Type-Options: nosniff');
        echo (string) $attachment['content'];
        exit;
    }

    /**
     * GET /api/communications/mailboxes/{profileId}/outbox
     */
    public function getMailboxesOutbox($id = null, $data = [], $segments = [])
    {
        $profileId = (int) ($id ?? ($data['profile_id'] ?? 0));
        if ($profileId <= 0) {
            return $this->badRequest('Profile ID required');
        }
        if (!$this->resolveAccessibleProfile($profileId)) {
            return $this->forbidden('Mailbox not accessible to your role');
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($data['per_page'] ?? 25)));
        $result = $this->mailboxMailService->listOutbox([$profileId], $data, $page, $perPage);

        return $this->success($result, 'Outbox retrieved');
    }

    /**
     * GET /api/communications/email-outbox — outbox across every mailbox the
     * caller can see; GET /api/communications/email-outbox/{id} for one row.
     */
    public function getEmailOutbox($id = null, $data = [], $segments = [])
    {
        $profileIds = array_map('intval', array_column($this->accessibleMailboxProfiles(), 'id'));
        if ($profileIds === []) {
            return $this->success(['messages' => [], 'pagination' => ['total' => 0, 'page' => 1, 'limit' => 25, 'pages' => 0]], 'Outbox retrieved');
        }

        if ($id !== null && is_numeric($id)) {
            $row = $this->mailboxMailService->getOutbox((int) $id, $profileIds);
            if ($row === null) {
                return $this->badRequest('Outbox message not found.');
            }
            return $this->success(['message' => $row], 'Outbox message retrieved');
        }

        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = min(50, max(1, (int) ($data['per_page'] ?? 25)));
        $result = $this->mailboxMailService->listOutbox($profileIds, $data, $page, $perPage);

        return $this->success($result, 'Outbox retrieved');
    }

    /**
     * POST /api/communications/email-outbox/retry/{outboxId}
     */
    public function postEmailOutboxRetry($id = null, $data = [], $segments = [])
    {
        $outboxId = (int) ($id ?? ($data['outbox_id'] ?? 0));
        if ($outboxId <= 0) {
            return $this->badRequest('Outbox message ID required');
        }

        $row = $this->mailboxMailService->getOutbox($outboxId, array_map('intval', array_column($this->accessibleMailboxProfiles(), 'id')));
        if ($row === null) {
            return $this->badRequest('Outbox message not found.');
        }

        $resolved = $this->resolveMailboxCredentials((int) $row['profile_id'], ['reply', 'compose']);
        if ($resolved === null) {
            return $this->forbidden('You do not have send access to this mailbox.');
        }

        try {
            $result = $this->mailboxMailService->retry($outboxId, $resolved['profile'], (int) $this->getUserId());
            return $this->success($result, 'Message sent successfully.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsController] outbox retry failed: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * DELETE /api/communications/email-outbox/{outboxId}
     */
    public function deleteEmailOutbox($id = null, $data = [], $segments = [])
    {
        $outboxId = (int) ($id ?? ($data['outbox_id'] ?? 0));
        if ($outboxId <= 0) {
            return $this->badRequest('Outbox message ID required');
        }

        $profileId = $this->mailboxMailService->outboxProfileId($outboxId);
        if ($profileId !== null && !$this->resolveMailboxCredentials($profileId, ['reply', 'compose'])) {
            return $this->forbidden('You do not have send access to this mailbox.');
        }

        try {
            $this->mailboxMailService->deleteOutbox($outboxId, array_map('intval', array_column($this->accessibleMailboxProfiles(), 'id')));
            return $this->success(['id' => $outboxId], 'Outbox entry removed');
        } catch (\Throwable $e) {
            return $this->badRequest($e->getMessage());
        }
    }
}
