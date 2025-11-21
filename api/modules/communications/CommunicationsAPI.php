<?php

namespace App\API\Modules\communications;

use App\API\Includes\BaseAPI;

use App\API\Modules\Communications\CommunicationsManager;
use App\API\Modules\Communications\Templates\TemplateLoader;
use App\API\Modules\Communications\CommunicationWorkflowHandler;
use App\API\Modules\Communications\ContactDirectoryManager;
use App\API\Modules\Communications\ExternalInboundManager;
use App\API\Modules\Communications\ForumManager;
use App\API\Modules\Communications\InternalAnnouncementManager;
use App\API\Modules\Communications\InternalCommManager;
use App\API\Modules\Communications\ParentPortalMessageManager;
use App\API\Modules\Communications\StaffForumManager;
use App\API\Modules\Communications\StaffRequestManager;



class CommunicationsAPI extends BaseAPI
{
    /**
     * Send SMS using a template category and variables.
     * @param array $recipients
     * @param array $variables
     * @param string $category
     * @param string $type
     * @return array
     */
    public function sendTemplateSMS($recipients, $variables, $category = 'fee_payment_received', $type = 'sms')
    {
        return $this->manager->sendSMSToRecipients($recipients, $variables, $type, $category);
    }
    // Public method to send a reset email (or any email) using the manager
    public function sendResetEmail($recipients, $subject, $body, $attachments = [], $signature = '', $footer = '', $schoolDetails = [])
    {
        return $this->manager->sendEmailToRecipients($recipients, $subject, $body, $attachments, $signature, $footer, $schoolDetails);
    }

    // --- Callback/Inbound Support Methods ---
    /**
     * Update delivery status for a recipient (for delivery report callbacks)
     */
    public function updateDeliveryStatus($recipientId, $status, $deliveredAt = null, $errorMessage = null)
    {
        return $this->manager->updateDeliveryStatus($recipientId, $status, $deliveredAt, $errorMessage);
    }

    /**
     * Mark a recipient as opted out (for opt-out callbacks)
     */
    public function markOptOut($recipientIdentifier, $channel)
    {
        return $this->manager->markOptOut($recipientIdentifier, $channel);
    }

    /**
     * Store incoming message (for subscription/inbound callbacks)
     */
    public function storeIncomingMessage($data)
    {
        return $this->manager->storeIncomingMessage($data);
    }


    private $manager;
    private $templateLoader;
    private $workflowHandler;
    private $contactDirectoryManager;
    private $externalInboundManager;
    private $forumManager;
    private $internalAnnouncementManager;
    private $internalCommManager;
    private $parentPortalMessageManager;
    private $staffForumManager;
    private $staffRequestManager;

    public function __construct()
    {
        parent::__construct('communications');
        $this->manager = new CommunicationsManager($this->db);
        $this->templateLoader = new TemplateLoader();
        $this->workflowHandler = new CommunicationWorkflowHandler();
        $this->contactDirectoryManager = new ContactDirectoryManager($this->db);
        $this->externalInboundManager = new ExternalInboundManager($this->db);
        $this->forumManager = new ForumManager($this->db);
        $this->internalAnnouncementManager = new InternalAnnouncementManager($this->db);
        $this->internalCommManager = new InternalCommManager($this->db);
        $this->parentPortalMessageManager = new ParentPortalMessageManager($this->db);
        $this->staffForumManager = new StaffForumManager($this->db);
        $this->staffRequestManager = new StaffRequestManager($this->db);
    }
    // --- Contact Directory API ---
    public function createContact($data)
    {
        return $this->contactDirectoryManager->createContact($data);
    }
    public function getContact($id)
    {
        return $this->contactDirectoryManager->getContact($id);
    }
    public function updateContact($id, $data)
    {
        return $this->contactDirectoryManager->updateContact($id, $data);
    }
    public function deleteContact($id)
    {
        return $this->contactDirectoryManager->deleteContact($id);
    }
    public function listContacts($filters = [])
    {
        return $this->contactDirectoryManager->listContacts($filters);
    }

    // --- External Inbound API ---
    public function createInbound($data)
    {
        return $this->externalInboundManager->createInbound($data);
    }
    public function getInbound($id)
    {
        return $this->externalInboundManager->getInbound($id);
    }
    public function updateInbound($id, $data)
    {
        return $this->externalInboundManager->updateInbound($id, $data);
    }
    public function deleteInbound($id)
    {
        return $this->externalInboundManager->deleteInbound($id);
    }
    public function listInbounds($filters = [])
    {
        return $this->externalInboundManager->listInbounds($filters);
    }

    // --- Forum API ---
    public function createThread($data)
    {
        return $this->forumManager->createThread($data);
    }
    public function getThread($id)
    {
        return $this->forumManager->getThread($id);
    }
    public function updateThread($id, $data)
    {
        return $this->forumManager->updateThread($id, $data);
    }
    public function deleteThread($id)
    {
        return $this->forumManager->deleteThread($id);
    }
    public function listThreads($filters = [])
    {
        return $this->forumManager->listThreads($filters);
    }

    // --- Internal Announcement API ---
    public function createAnnouncement($data)
    {
        return $this->internalAnnouncementManager->createAnnouncement($data);
    }
    public function getAnnouncement($id)
    {
        return $this->internalAnnouncementManager->getAnnouncement($id);
    }
    public function updateAnnouncement($id, $data)
    {
        return $this->internalAnnouncementManager->updateAnnouncement($id, $data);
    }
    public function deleteAnnouncement($id)
    {
        return $this->internalAnnouncementManager->deleteAnnouncement($id);
    }
    public function listAnnouncements($filters = [])
    {
        return $this->internalAnnouncementManager->listAnnouncements($filters);
    }

    // --- Internal Comm API ---
    public function createInternalRequest($data)
    {
        return $this->internalCommManager->createRequest($data);
    }
    public function getInternalRequest($id)
    {
        return $this->internalCommManager->getRequest($id);
    }
    public function updateInternalRequest($id, $data)
    {
        return $this->internalCommManager->updateRequest($id, $data);
    }
    public function deleteInternalRequest($id)
    {
        return $this->internalCommManager->deleteRequest($id);
    }
    public function listInternalRequests($filters = [])
    {
        return $this->internalCommManager->listRequests($filters);
    }

    // --- Parent Portal Message API ---
    public function createParentMessage($data)
    {
        return $this->parentPortalMessageManager->createMessage($data);
    }
    public function getParentMessage($id)
    {
        return $this->parentPortalMessageManager->getMessage($id);
    }
    public function updateParentMessage($id, $data)
    {
        return $this->parentPortalMessageManager->updateMessage($id, $data);
    }
    public function deleteParentMessage($id)
    {
        return $this->parentPortalMessageManager->deleteMessage($id);
    }
    public function listParentMessages($filters = [])
    {
        return $this->parentPortalMessageManager->listMessages($filters);
    }

    // --- Staff Forum API ---
    public function createStaffForumTopic($data)
    {
        return $this->staffForumManager->createForumTopic($data);
    }
    public function getStaffForumTopic($id)
    {
        return $this->staffForumManager->getForumTopic($id);
    }
    public function updateStaffForumTopic($id, $data)
    {
        return $this->staffForumManager->updateForumTopic($id, $data);
    }
    public function deleteStaffForumTopic($id)
    {
        return $this->staffForumManager->deleteForumTopic($id);
    }
    public function listStaffForumTopics($filters = [])
    {
        return $this->staffForumManager->listForumTopics($filters);
    }

    // --- Staff Request API ---
    public function createStaffRequest($data)
    {
        return $this->staffRequestManager->createRequest($data);
    }
    public function getStaffRequest($id)
    {
        return $this->staffRequestManager->getRequest($id);
    }
    public function updateStaffRequest($id, $data)
    {
        return $this->staffRequestManager->updateRequest($id, $data);
    }
    public function deleteStaffRequest($id)
    {
        return $this->staffRequestManager->deleteRequest($id);
    }
    public function listStaffRequests($filters = [])
    {
        return $this->staffRequestManager->listRequests($filters);
    }

    // --- Communication Workflow API ---
    public function initiateCommunicationWorkflow($reference_type, $reference_id, $data = [])
    {
        return $this->workflowHandler->initiateCommunicationWorkflow($reference_type, $reference_id, $data);
    }
    public function approveCommunication($instance_id, $action_data = [])
    {
        return $this->workflowHandler->approveCommunication($instance_id, $action_data);
    }
    public function escalateCommunication($instance_id, $action_data = [])
    {
        return $this->workflowHandler->escalateCommunication($instance_id, $action_data);
    }
    public function completeCommunication($instance_id, $completion_data = [])
    {
        return $this->workflowHandler->completeCommunication($instance_id, $completion_data);
    }
    public function getCommunicationWorkflowInstance($instance_id)
    {
        return $this->workflowHandler->getCommunicationWorkflowInstance($instance_id);
    }
    public function listCommunicationWorkflows($filters = [])
    {
        return $this->workflowHandler->listCommunicationWorkflows($filters);
    }

    // --- Communications CRUD ---
    public function createCommunication($data)
    {
        return $this->manager->createCommunication($data);
    }
    public function getCommunication($id)
    {
        return $this->manager->getCommunication($id);
    }
    public function updateCommunication($id, $data)
    {
        return $this->manager->updateCommunication($id, $data);
    }
    public function deleteCommunication($id)
    {
        return $this->manager->deleteCommunication($id);
    }
    public function listCommunications($filters = [])
    {
        return $this->manager->listCommunications($filters);
    }

    // --- Attachments CRUD ---
    public function addAttachment($communicationId, $fileData)
    {
        return $this->manager->addAttachment($communicationId, $fileData);
    }
    public function getAttachment($id)
    {
        return $this->manager->getAttachment($id);
    }
    public function deleteAttachment($id)
    {
        return $this->manager->deleteAttachment($id);
    }
    public function listAttachments($communicationId)
    {
        return $this->manager->listAttachments($communicationId);
    }

    // --- Groups CRUD ---
    public function createGroup($data)
    {
        return $this->manager->createGroup($data);
    }
    public function getGroup($id)
    {
        return $this->manager->getGroup($id);
    }
    public function updateGroup($id, $data)
    {
        return $this->manager->updateGroup($id, $data);
    }
    public function deleteGroup($id)
    {
        return $this->manager->deleteGroup($id);
    }
    public function listGroups($filters = [])
    {
        return $this->manager->listGroups($filters);
    }

    // --- Logs CRUD ---
    public function addLog($data)
    {
        return $this->manager->addLog($data);
    }
    public function getLog($id)
    {
        return $this->manager->getLog($id);
    }
    public function listLogs($filters = [])
    {
        return $this->manager->listLogs($filters);
    }

    // --- Recipients CRUD ---
    public function addRecipient($data)
    {
        return $this->manager->addRecipient($data);
    }
    public function getRecipient($id)
    {
        return $this->manager->getRecipient($id);
    }
    public function deleteRecipient($id)
    {
        return $this->manager->deleteRecipient($id);
    }
    public function listRecipients($communicationId)
    {
        return $this->manager->listRecipients($communicationId);
    }

    // --- Templates CRUD ---
    public function createTemplate($data)
    {
        return $this->manager->createTemplate($data);
    }
    public function getTemplate($id)
    {
        return $this->manager->getTemplate($id);
    }
    public function updateTemplate($id, $data)
    {
        return $this->manager->updateTemplate($id, $data);
    }
    public function deleteTemplate($id)
    {
        return $this->manager->deleteTemplate($id);
    }
    public function listTemplates($filters = [])
    {
        return $this->manager->listTemplates($filters);
    }

}
