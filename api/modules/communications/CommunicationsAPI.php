<?php

namespace App\API\Modules\communications;

use App\API\Includes\BaseAPI;

use App\API\Modules\communications\CommunicationsManager;
use App\API\Modules\communications\templates\TemplateLoader;
use App\API\Modules\communications\CommunicationWorkflowHandler;
use App\API\Modules\communications\ContactDirectoryManager;
use App\API\Modules\communications\ExternalInboundManager;
use App\API\Modules\communications\ForumManager;
use App\API\Modules\communications\InternalAnnouncementManager;
use App\API\Modules\communications\InternalCommManager;
use App\API\Modules\communications\ParentPortalMessageManager;
use App\API\Modules\communications\StaffForumManager;
use App\API\Modules\communications\StaffRequestManager;



class CommunicationsAPI extends BaseAPI
{


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

    /**
     * Send SMS directly with message text  
     * Mapped to: POST /communications/send-sms
     * @param array $recipients Array of phone numbers
     * @param string $message SMS message text
     * @param string $type Type of SMS (sms, whatsapp, etc)
     * @return array
     */
    public function postSendSms($recipients = null, $message = null, $type = 'sms')
    {
        // Get from JSON body if not passed as params
        if ($recipients === null || $message === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $recipients = $data['recipients'] ?? [];
            $message = $data['message'] ?? '';
            $type = $data['type'] ?? 'sms';
        }

        // Validate inputs
        if (empty($recipients) || empty($message)) {
            return [
                'status' => 'error',
                'message' => 'Recipients and message are required',
                'data' => null
            ];
        }

        // Ensure recipients is an array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        // Send using the SMS gateway directly
        $gateway = new \App\API\Services\sms\SMSGateway();
        $sent = 0;
        $failed = [];
        $sent_ids = [];
        $failed_ids = [];

        foreach ($recipients as $phone) {
            try {
                if ($type === 'whatsapp') {
                    $result = $gateway->sendWhatsApp($phone, $message);
                } else {
                    $result = $gateway->send($phone, $message);
                }

                // Handle both boolean and array responses
                $success = false;
                if (is_bool($result)) {
                    $success = $result === true;
                } else if (is_array($result) && isset($result['status']) && $result['status'] === 'success') {
                    $success = true;
                }

                if ($success) {
                    $sent++;

                    // Store in communications table with sent status
                    $comm = $this->manager->createCommunication([
                        'type' => 'sms',
                        'subject' => substr($message, 0, 100),
                        'body' => $message,
                        'recipients' => [$phone],
                        'status' => 'sent'
                    ]);

                    if ($comm && isset($comm['id'])) {
                        $sent_ids[] = $comm['id'];
                    }
                } else {
                    $failed[] = $phone;

                    // Store in communications table with failed status
                    $comm = $this->manager->createCommunication([
                        'type' => 'sms',
                        'subject' => substr($message, 0, 100),
                        'body' => $message,
                        'recipients' => [$phone],
                        'status' => 'failed'
                    ]);

                    if ($comm && isset($comm['id'])) {
                        $failed_ids[] = $comm['id'];
                    }
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
                error_log("SMS Send Error: " . $e->getMessage());
            }
        }

        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent SMS" : 'Failed to send SMS',
            'sent_count' => $sent,
            'failed_count' => count($failed),
            'failed' => $failed,
            'communication_ids' => $sent_ids
        ];
    }

    /**
     * Send email directly
     * Mapped to: POST /communications/send-email
     * @param array $recipients Array of emails or [email => name] pairs
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $attachments File attachments
     * @param string $signature Email signature
     * @param string $footer Email footer
     * @param array $schoolDetails School information for template
     * @return array
     */
    public function postSendEmail($recipients = null, $subject = null, $body = null, $attachments = [], $signature = '', $footer = '', $schoolDetails = [])
    {
        // Get from JSON body if not passed as params
        if ($recipients === null || $subject === null || $body === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $recipients = $data['recipients'] ?? [];
            $subject = $data['subject'] ?? '';
            $body = $data['body'] ?? '';
            $attachments = $data['attachments'] ?? [];
            $signature = $data['signature'] ?? '';
            $footer = $data['footer'] ?? '';
            $schoolDetails = $data['schoolDetails'] ?? [];
        }

        // Validate inputs
        if (empty($recipients) || empty($subject) || empty($body)) {
            return [
                'status' => 'error',
                'message' => 'Recipients, subject, and body are required',
                'data' => null
            ];
        }

        // Ensure recipients is an array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        // Send email
        $result = $this->manager->sendEmailToRecipients(
            $recipients,
            $subject,
            $body,
            $attachments,
            $signature,
            $footer,
            $schoolDetails
        );

        // Store in communications table
        if ($result['status'] === 'success') {
            $sent_ids = [];
            foreach ($recipients as $email => $name) {
                if (is_int($email)) {
                    $email = $name;
                }

                $comm = $this->manager->createCommunication([
                    'type' => 'email',
                    'subject' => $subject,
                    'body' => $body,
                    'recipients' => [$email],
                    'status' => 'sent'
                ]);

                if ($comm && isset($comm['id'])) {
                    $sent_ids[] = $comm['id'];
                }
            }

            $result['communication_ids'] = $sent_ids;
        }

        return $result;
    }

    /**
     * Send Fee Reminder SMS/WhatsApp
     * Mapped to: POST /communications/fee-reminder
     * 
     * Sends a fee payment reminder to a parent about their child's outstanding balance
     * 
     * @param array $data Contains student_id, phone, message, type (sms/whatsapp), balance, student_name
     * @return array
     */
    public function sendFeeReminder($data = [])
    {
        // Get from JSON body if not passed
        if (empty($data)) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
        }

        $studentId = $data['student_id'] ?? null;
        $phone = $data['phone'] ?? null;
        $message = $data['message'] ?? null;
        $type = $data['type'] ?? 'sms';
        $balance = $data['balance'] ?? 0;
        $studentName = $data['student_name'] ?? 'Student';

        // Validate required fields
        if (empty($phone) || empty($message)) {
            return [
                'status' => 'error',
                'message' => 'Phone number and message are required',
                'data' => null
            ];
        }

        // Clean phone number (ensure proper format)
        $phone = $this->formatPhoneNumber($phone);

        // Send using the SMS gateway
        $gateway = new \App\API\Services\sms\SMSGateway();

        try {
            if ($type === 'whatsapp') {
                $result = $gateway->sendWhatsApp($phone, $message);
            } else {
                $result = $gateway->send($phone, $message);
            }

            // Handle both boolean and array responses
            $success = false;
            if (is_bool($result)) {
                $success = $result === true;
            } else if (is_array($result) && isset($result['status']) && $result['status'] === 'success') {
                $success = true;
            }

            if ($success) {
                // Store in communications table with sent status
                $comm = $this->manager->createCommunication([
                    'type' => $type,
                    'subject' => 'Fee Reminder: ' . $studentName,
                    'body' => $message,
                    'recipients' => [$phone],
                    'status' => 'sent',
                    'metadata' => json_encode([
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'balance' => $balance,
                        'reminder_type' => 'fee'
                    ])
                ]);

                // Log fee reminder activity
                $this->logFeeReminderActivity($studentId, $phone, $balance, $type, 'sent');

                return [
                    'status' => 'success',
                    'message' => ucfirst($type) . ' fee reminder sent successfully to ' . $phone,
                    'communication_id' => $comm['id'] ?? null,
                    'data' => [
                        'phone' => $phone,
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'balance' => $balance,
                        'type' => $type
                    ]
                ];
            } else {
                // Store failed attempt
                $this->manager->createCommunication([
                    'type' => $type,
                    'subject' => 'Fee Reminder: ' . $studentName,
                    'body' => $message,
                    'recipients' => [$phone],
                    'status' => 'failed'
                ]);

                // Log failed attempt
                $this->logFeeReminderActivity($studentId, $phone, $balance, $type, 'failed');

                return [
                    'status' => 'error',
                    'message' => 'Failed to send ' . $type . ' reminder. Please try again.',
                    'data' => null
                ];
            }
        } catch (\Exception $e) {
            error_log("Fee Reminder Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Error sending fee reminder: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Send Bulk Fee Reminders
     * Mapped to: POST /communications/fee-reminder-bulk
     * 
     * Sends fee reminders to multiple parents at once
     * 
     * @param array $data Contains students array, message_template, type
     * @return array
     */
    public function sendBulkFeeReminders($data = [])
    {
        // Get from JSON body if not passed
        if (empty($data)) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
        }

        $students = $data['students'] ?? [];
        $messageTemplate = $data['message_template'] ?? '';
        $type = $data['type'] ?? 'sms';

        if (empty($students)) {
            return [
                'status' => 'error',
                'message' => 'No students provided for bulk reminders',
                'data' => null
            ];
        }

        $sent = 0;
        $failed = [];
        $results = [];

        foreach ($students as $student) {
            $studentId = $student['student_id'] ?? null;
            $phone = $student['phone'] ?? null;
            $balance = $student['balance'] ?? 0;
            $studentName = $student['student_name'] ?? 'Student';

            if (empty($phone)) {
                $failed[] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'reason' => 'No phone number'
                ];
                continue;
            }

            // Replace placeholders in message template
            $message = str_replace(
                ['{student_name}', '{balance}', '{amount}'],
                [$studentName, number_format($balance, 2), number_format($balance, 2)],
                $messageTemplate
            );

            // Send individual reminder
            $result = $this->sendFeeReminder([
                'student_id' => $studentId,
                'phone' => $phone,
                'message' => $message,
                'type' => $type,
                'balance' => $balance,
                'student_name' => $studentName
            ]);

            if ($result['status'] === 'success') {
                $sent++;
                $results[] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'phone' => $phone,
                    'status' => 'sent'
                ];
            } else {
                $failed[] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'reason' => $result['message']
                ];
            }
        }

        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0
                ? "Successfully sent $sent of " . count($students) . " fee reminders"
                : 'Failed to send any fee reminders',
            'sent_count' => $sent,
            'failed_count' => count($failed),
            'failed' => $failed,
            'results' => $results
        ];
    }

    /**
     * Format phone number for SMS/WhatsApp
     * Ensures proper country code format
     * 
     * @param string $phone
     * @return string
     */
    private function formatPhoneNumber($phone)
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Handle Kenya phone numbers
        if (strlen($phone) === 9 && preg_match('/^[7]/', $phone)) {
            $phone = '254' . $phone;
        } else if (strlen($phone) === 10 && preg_match('/^0/', $phone)) {
            $phone = '254' . substr($phone, 1);
        }

        return $phone;
    }

    /**
     * Log fee reminder activity for audit/tracking
     * 
     * @param int $studentId
     * @param string $phone
     * @param float $balance
     * @param string $type
     * @param string $status
     */
    private function logFeeReminderActivity($studentId, $phone, $balance, $type, $status)
    {
        try {
            $sql = "INSERT INTO system_logs (log_type, action, entity_type, entity_id, details, ip_address, created_at) 
                    VALUES ('fee_reminder', :action, 'student', :student_id, :details, :ip, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'action' => $type . '_reminder_' . $status,
                'student_id' => $studentId,
                'details' => json_encode([
                    'phone' => $phone,
                    'balance' => $balance,
                    'type' => $type,
                    'status' => $status
                ]),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            error_log("Fee Reminder Log Error: " . $e->getMessage());
        }
    }

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

    /**
     * Send SMS with template selection - Maps to POST /communications/send-sms-template
     */
    public function postSendSmsTemplate($recipients = null, $title = null, $message = null, $variables = [], $template_id = null, $type = 'sms')
    {
        if ($recipients === null || $title === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $recipients = $data['recipients'] ?? [];
            $title = $data['title'] ?? $data['category'] ?? null;
            $message = $data['message'] ?? '';
            $variables = $data['variables'] ?? [];
            $template_id = $data['template_id'] ?? null;
            $type = $data['type'] ?? 'sms';
        }
        if (empty($recipients) || empty($title)) {
            return ['status' => 'error', 'message' => 'Recipients and title required', 'sent_count' => 0];
        }
        if (!is_array($recipients))
            $recipients = [$recipients];
        if (!is_array($variables))
            $variables = [];

        $template = null;
        if ($template_id)
            $template = $this->manager->getTemplate($template_id);
        if (!$template)
            $template = $this->templateLoader->getTemplate($type, $title);

        $rendered = $message;
        if ($template && isset($template['template_body'])) {
            $rendered = $this->templateLoader->renderTemplate($template, $variables);
        } else if (!$message) {
            return ['status' => 'error', 'message' => 'Template not found', 'sent_count' => 0];
        }

        $gateway = new \App\API\Services\sms\SMSGateway();
        $sent = 0;
        $failed = [];
        $sent_ids = [];
        $template_title = $template['name'] ?? $title;

        foreach ($recipients as $phone) {
            try {
                $result = ($type === 'whatsapp' && $template && isset($template['media_urls']))
                    ? $gateway->sendWhatsApp($phone, $rendered, $template['media_urls'])
                    : ($type === 'whatsapp' ? $gateway->sendWhatsApp($phone, $rendered) : $gateway->send($phone, $rendered));

                if ($result && isset($result['status']) && $result['status'] === 'success') {
                    $sent++;
                    $comm = $this->manager->createCommunication([
                        'type' => $type,
                        'subject' => substr($template_title, 0, 100),
                        'body' => $rendered,
                        'recipients' => [$phone],
                        'template_id' => $template_id ?? ($template['id'] ?? null),
                        'status' => 'sent'
                    ]);
                    if ($comm && isset($comm['id']))
                        $sent_ids[] = $comm['id'];
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
                error_log("SMS Error: " . $e->getMessage());
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'sent_count' => $sent,
            'failed_count' => count($failed),
            'failed' => $failed,
            'template_used' => $template_title,
            'communication_ids' => $sent_ids
        ];
    }

    /**
     * Send WhatsApp with optional document attachments - Maps to POST /communications/send-whatsapp
     */
    public function postSendWhatsapp($recipients = null, $message = null, $documents = [], $variables = [], $category = null)
    {
        if ($recipients === null || $message === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $recipients = $data['recipients'] ?? [];
            $message = $data['message'] ?? '';
            $documents = $data['documents'] ?? [];
            $variables = $data['variables'] ?? [];
            $category = $data['category'] ?? null;
        }
        if (empty($recipients) || empty($message)) {
            return ['status' => 'error', 'message' => 'Recipients and message required', 'sent_count' => 0];
        }
        if (!is_array($recipients))
            $recipients = [$recipients];

        $template = null;
        if ($category) {
            $template = $this->templateLoader->getTemplate('whatsapp', $category);
            if ($template && isset($template['template_body'])) {
                $message = $this->templateLoader->renderTemplate($template, $variables);
                if (isset($template['media_urls']))
                    $documents = array_merge($documents, $template['media_urls']);
            }
        }

        $gateway = new \App\API\Services\sms\SMSGateway();
        $sent = 0;
        $failed = [];
        $sent_ids = [];

        foreach ($recipients as $phone) {
            try {
                $result = !empty($documents)
                    ? $gateway->sendWhatsApp($phone, $message, $documents)
                    : $gateway->sendWhatsApp($phone, $message);

                if ($result && isset($result['status']) && $result['status'] === 'success') {
                    $sent++;
                    $comm = $this->manager->createCommunication([
                        'type' => 'whatsapp',
                        'subject' => 'WhatsApp Message',
                        'body' => $message,
                        'recipients' => [$phone],
                        'status' => 'sent'
                    ]);
                    if ($comm && isset($comm['id']))
                        $sent_ids[] = $comm['id'];
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
                error_log("WhatsApp Error: " . $e->getMessage());
            }
        }
        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'sent_count' => $sent,
            'failed_count' => count($failed),
            'failed' => $failed,
            'documents_sent' => !empty($documents),
            'communication_ids' => $sent_ids
        ];
    }

    /**
     * Send SMS directly with message text
     * @param array $recipients Array of phone numbers
     * @param string $message SMS message text
     * @param string $type Type of SMS (sms, whatsapp, etc)
     * @return array
     */
    public function sendSms($recipients, $message, $type = 'sms')
    {
        // Convert single message to template variables format
        $variables = ['message' => $message];

        // Send using the SMS gateway directly
        $gateway = new \App\API\Services\sms\SMSGateway();
        $sent = 0;
        $failed = [];

        foreach ($recipients as $phone) {
            try {
                if ($type === 'whatsapp') {
                    $result = $gateway->sendWhatsApp($phone, $message);
                } else {
                    $result = $gateway->send($phone, $message);
                }

                if ($result && isset($result['status']) && $result['status'] === 'success') {
                    $sent++;

                    // Store in communications table
                    $this->manager->createCommunication([
                        'type' => $type,
                        'subject' => $message,
                        'body' => $message,
                        'recipients' => [$phone],
                        'status' => 'sent'
                    ]);
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
            }
        }

        return [
            'status' => $sent > 0 ? 'success' : 'error',
            'message' => $sent > 0 ? "Sent $sent SMS" : 'Failed to send SMS',
            'sent_count' => $sent,
            'failed' => $failed
        ];
    }

    /**
     * Send email directly
     * @param array $recipients Array of emails or [email => name] pairs
     * @param string $subject Email subject
     * @param string $body Email body
     * @param array $attachments File attachments
     * @param string $signature Email signature
     * @param string $footer Email footer
     * @param array $schoolDetails School information for template
     * @return array
     */
    public function sendEmail($recipients, $subject, $body, $attachments = [], $signature = '', $footer = '', $schoolDetails = [])
    {
        $result = $this->manager->sendEmailToRecipients(
            $recipients,
            $subject,
            $body,
            $attachments,
            $signature,
            $footer,
            $schoolDetails
        );

        // Store in communications table
        if ($result['status'] === 'success') {
            foreach ($recipients as $email => $name) {
                if (is_int($email)) {
                    $email = $name;
                }

                $this->manager->createCommunication([
                    'type' => 'email',
                    'subject' => $subject,
                    'body' => $body,
                    'recipients' => [$email],
                    'status' => 'sent'
                ]);
            }
        }

        return $result;
    }    // --- Callback/Inbound Support Methods ---
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

    /**
     * Send WhatsApp Template Message
     * Mapped to: POST /communications/send-whatsapp-template
     * @param array $recipients Array of phone numbers
     * @param string $templateId WhatsApp template ID
     * @param array $variables Template variables/parameters
     * @return array
     */
    public function postSendWhatsappTemplate($recipients = null, $templateId = null, $variables = [])
    {
        // Get from JSON body if not passed as params
        if ($recipients === null || $templateId === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $recipients = $data['recipients'] ?? [];
            $templateId = $data['templateId'] ?? $data['template_id'] ?? '';
            $variables = $data['variables'] ?? [];
        }

        // Validate inputs
        if (empty($recipients) || empty($templateId)) {
            return [
                'status' => 'error',
                'message' => 'Recipients and templateId are required',
                'data' => null
            ];
        }

        // Ensure recipients is an array
        if (!is_array($recipients)) {
            $recipients = [$recipients];
        }

        try {
            $gateway = new \App\API\Services\whatsapp\WhatsAppGateway();
            $sent = 0;
            $failed = [];
            $sent_ids = [];

            foreach ($recipients as $phone) {
                try {
                    $result = $gateway->sendTemplate($phone, $templateId, $variables);

                    if (is_array($result) && $result['status'] === 'success') {
                        $sent++;
                        $sent_ids[] = [
                            'phone' => $phone,
                            'template_id' => $templateId,
                            'timestamp' => date('Y-m-d H:i:s')
                        ];
                    } else {
                        $failed[] = [
                            'phone' => $phone,
                            'error' => $result['message'] ?? 'Unknown error'
                        ];
                    }
                } catch (\Exception $e) {
                    $failed[] = [
                        'phone' => $phone,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $failedCount = count($failed);
            $sentStatus = $failedCount === 0 ? 'success' : ($sent > 0 ? 'partial' : 'error');

            return [
                'status' => $sentStatus,
                'message' => "Sent to $sent recipient(s), failed: " . $failedCount,
                'data' => [
                    'sent' => $sent,
                    'failed' => $failedCount,
                    'sent_ids' => $sent_ids,
                    'failed_details' => $failed
                ]
            ];
        } catch (\Exception $e) {
            error_log("WhatsApp Template Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Create WhatsApp Template
     * Mapped to: POST /communications/create-whatsapp-template
     * @param string $name Template name (must be unique)
     * @param string $language Language code (e.g., 'en')
     * @param string $category Template category (MARKETING, UTILITY, AUTHENTICATION)
     * @param array $components Template components (header, body, footer, buttons)
     * @return array
     */
    public function postCreateWhatsappTemplate($name = null, $language = null, $category = null, $components = null)
    {
        // Get from JSON body if not passed as params
        if ($name === null || $language === null || $category === null || $components === null) {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true) ?? [];
            $name = $data['name'] ?? '';
            $language = $data['language'] ?? 'en';
            $category = $data['category'] ?? 'UTILITY';
            $components = $data['components'] ?? [];
        }

        // Validate inputs
        if (empty($name) || empty($components)) {
            return [
                'status' => 'error',
                'message' => 'Template name and components are required',
                'data' => null
            ];
        }

        // Validate category
        $validCategories = ['MARKETING', 'UTILITY', 'AUTHENTICATION'];
        if (!in_array($category, $validCategories)) {
            return [
                'status' => 'error',
                'message' => "Category must be one of: " . implode(', ', $validCategories),
                'data' => null
            ];
        }

        try {
            $gateway = new \App\API\Services\whatsapp\WhatsAppGateway();

            $templateConfig = [
                'name' => $name,
                'language' => $language,
                'category' => $category,
                'components' => $components
            ];

            $result = $gateway->createTemplate($templateConfig);

            if (is_array($result) && $result['status'] === 'success') {
                return [
                    'status' => 'success',
                    'message' => 'Template created successfully',
                    'data' => $result['data'] ?? []
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => $result['message'] ?? 'Failed to create template',
                    'data' => $result['data'] ?? null
                ];
            }
        } catch (\Exception $e) {
            error_log("Create WhatsApp Template Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'data' => null
            ];
        }
    }

}
