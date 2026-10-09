<?php

namespace App\API\Modules\communications;

use App\API\Includes\BaseAPI;
use App\API\Services\ReadReplicaService;

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
use App\API\Modules\communications\InternalMessagingManager;



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
    private $internalMessagingManager;

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
        $this->internalMessagingManager = new InternalMessagingManager($this->db);
    }

    /**
     * Communications hub summary for the dashboard.
     * Returns global counts by channel/status plus per-user unread messaging counts
     * and recent activity, suitable for the Manage Communications landing page.
     *
     * @return array
     */
    public function getSummary(array $scope = [])
    {
        $result = [
            'totals' => [
                'communications' => 0,
                'by_type' => [
                    'email' => 0,
                    'sms' => 0,
                    'whatsapp' => 0,
                    'notification' => 0,
                    'internal' => 0,
                ],
                'by_status' => [
                    'draft' => 0,
                    'sent' => 0,
                    'scheduled' => 0,
                    'failed' => 0,
                ],
                'by_priority' => [
                    'low' => 0,
                    'medium' => 0,
                    'high' => 0,
                ],
                'announcements' => [
                    'total' => 0,
                    'published' => 0,
                    'draft' => 0,
                    'scheduled' => 0,
                ],
                'messaging' => [
                    'conversations' => 0,
                    'unread' => 0,
                ],
                'last_activity_at' => null,
            ],
            'recent' => [],
            'generated_at' => date('c'),
        ];

        try {
            $visibility = $scope['visibility'] ?? 'all';
            $userId = (int) ($scope['user_id'] ?? $this->user_id ?? 0);
            $visibilitySql = '';
            $visibilityParams = [];
            if ($visibility === 'self_or_tagged' && $userId > 0) {
                $visibilitySql = " WHERE (c.sender_id = :scope_user OR EXISTS (SELECT 1 FROM communication_recipients cr_scope WHERE cr_scope.communication_id = c.id AND cr_scope.recipient_id = :scope_recipient))";
                $visibilityParams = [':scope_user' => $userId, ':scope_recipient' => $userId];
            } elseif ($visibility === 'self_or_tagged') {
                $visibilitySql = ' WHERE 1 = 0';
            } elseif ($visibility === 'teacher_parent') {
                $visibilitySql = " WHERE EXISTS (SELECT 1 FROM user_roles sender_role WHERE sender_role.user_id = c.sender_id AND sender_role.role_id IN (7, 8, 9)) AND (c.sender_signature LIKE '%parent%' OR c.sender_signature LIKE '%selected_students%' OR c.sender_signature LIKE '%selected_class%' OR EXISTS (SELECT 1 FROM communication_recipients cr_parent JOIN users parent_user ON parent_user.id = cr_parent.recipient_id JOIN parents parent_record ON parent_record.person_id = parent_user.person_id WHERE cr_parent.communication_id = c.id))";
            }

            // Totals and breakdown by type/status/priority
            $stmt = $this->db->prepare("SELECT c.type, c.status, c.priority, COUNT(*) AS c FROM communications c" . $visibilitySql . " GROUP BY c.type, c.status, c.priority");
            $stmt->execute($visibilityParams);
            $total = 0;
            $lastActivity = null;
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $c = (int)$row['c'];
                $total += $c;
                if (isset($result['totals']['by_type'][$row['type']])) {
                    $result['totals']['by_type'][$row['type']] += $c;
                }
                if (isset($result['totals']['by_status'][$row['status']])) {
                    $result['totals']['by_status'][$row['status']] += $c;
                }
                if (isset($result['totals']['by_priority'][$row['priority']])) {
                    $result['totals']['by_priority'][$row['priority']] += $c;
                }
            }
            $result['totals']['communications'] = $total;

            // Announcement breakdown (type = notification)
            $announcementWhere = $visibilitySql === '' ? " WHERE c.type = 'notification'" : $visibilitySql . " AND c.type = 'notification'";
            $stmt = $this->db->prepare("SELECT c.status, COUNT(*) AS c FROM communications c" . $announcementWhere . " GROUP BY c.status");
            $stmt->execute($visibilityParams);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $result['totals']['announcements']['total'] += (int)$row['c'];
                if ($row['status'] === 'sent') {
                    $result['totals']['announcements']['published'] = (int)$row['c'];
                } elseif ($row['status'] === 'draft') {
                    $result['totals']['announcements']['draft'] = (int)$row['c'];
                } elseif ($row['status'] === 'scheduled') {
                    $result['totals']['announcements']['scheduled'] = (int)$row['c'];
                }
            }

            // Last activity
            $stmt = $this->db->prepare("SELECT MAX(c.created_at) AS last FROM communications c" . $visibilitySql);
            $stmt->execute($visibilityParams);
            $lastRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($lastRow && !empty($lastRow['last'])) {
                $result['totals']['last_activity_at'] = $lastRow['last'];
            }

            // Per-user messaging (conversations + unread)
            $userId = $this->user_id;
            if ($userId) {
                $stmt = $this->db->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(unread_count), 0) AS u FROM conversation_participants WHERE participant_id = :uid AND left_at IS NULL");
                $stmt->execute([':uid' => $userId]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $result['totals']['messaging']['conversations'] = (int)($row['c'] ?? 0);
                $result['totals']['messaging']['unread'] = (int)($row['u'] ?? 0);
            }

            // Recent communications (last 5), using exactly the same visibility scope.
            $recentWhere = $visibilitySql;
            $stmt = $this->db->prepare("SELECT c.id, c.type, c.subject, c.status, c.priority, c.sender_id, c.created_at FROM communications c" . $recentWhere . " ORDER BY c.id DESC LIMIT 5");
            $stmt->execute($visibilityParams);
            $recent = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $recent[] = [
                    'id' => (int)$row['id'],
                    'type' => $row['type'],
                    'subject' => $row['subject'],
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'sender_id' => $row['sender_id'] !== null ? (int)$row['sender_id'] : null,
                    'created_at' => $row['created_at'],
                ];
            }
            $result['recent'] = $recent;
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[CommunicationsAPI::getSummary] ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to build communications summary',
                'data' => null,
            ];
        }

        return [
            'status' => 'success',
            'message' => 'Communications summary',
            'data' => $result,
        ];
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

        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        $sent = 0;
        $failed = [];
        $sent_ids = [];
        $failed_ids = [];

        foreach ($recipients as $phone) {
            try {
                $queued = $platform->queueRenderedForContacts([['phone' => $phone]], $type === 'whatsapp' ? 'whatsapp' : 'sms', substr($message, 0, 100), $message, [
                    'purpose' => 'transactional',
                    'sender_id' => $this->user_id ?: 1,
                ]);
                if (($queued['recipient_count'] ?? 0) > 0) {
                    $sent++;
                    $sent_ids[] = $queued['communication_id'];
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
                \App\API\Services\Logger::legacyError("SMS Send Error: " . $e->getMessage());
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
     * Resend a failed/pending communication
     * POST /communications/resend
     * Expects: { id: communication_id }
     * Re-sends via the appropriate channel (sms/email/whatsapp) and updates status.
     */
    public function resendCommunication($data)
    {
        $commId = $data['id'] ?? $data['communication_id'] ?? null;
        if (!$commId) {
            return ['status' => 'error', 'message' => 'Communication ID is required'];
        }

        $comm = $this->manager->getCommunication((int)$commId);
        if (!$comm) {
            return ['status' => 'error', 'message' => 'Communication not found'];
        }
        $this->db->prepare("UPDATE communication_recipient_endpoints e JOIN communication_recipients r ON r.id = e.communication_recipient_id SET e.status = 'retry', e.next_attempt_at = NULL, e.last_error = NULL, r.status = 'retry', r.error_message = NULL WHERE r.communication_id = ? AND e.status IN ('failed','retry')")
            ->execute([(int) $commId]);
        $this->db->prepare("UPDATE communications SET status = 'queued', next_attempt_at = NULL, last_error = NULL, processed_at = NULL WHERE id = ?")
            ->execute([(int) $commId]);
        $result = (new \App\API\Services\CommunicationOutboxService($this->db))->processOne((int) $commId);
        return ['status' => $result === 'sent' ? 'success' : ($result === 'failed' ? 'error' : 'queued'), 'message' => 'Communication resend processed through the outbox.', 'communication_id' => (int) $commId, 'dispatch_result' => $result];
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

        return $this->sendEmail($recipients, $subject, $body, $attachments, $signature, $footer, $schoolDetails);
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

        if (empty($phone)) {
            return [
                'status' => 'error',
                'message' => 'Phone number is required',
                'data' => null
            ];
        }
        $phone = $this->formatPhoneNumber($phone);
        try {
            $window = (string) ($data['reminder_window'] ?? 'manual');
            $businessEvent = new \App\API\Services\CommunicationBusinessEventService($this->db);
            $eventId = $businessEvent->getOrCreate(
                'fee_reminder',
                (string) $studentId . ':' . $window . ':' . date('Y-m-d'),
                date('Y-m-d H:i:s'),
                $this->user_id ?: 1
            );
            if ($studentId) $businessEvent->linkFeeStudent($eventId, (int) $studentId, $window);
            $platform = new \App\API\Services\CommunicationPlatformService($this->db);
            $result = $platform->queueForContacts(
                [['user_id' => null, 'phone' => $phone, 'email' => $data['email'] ?? null]],
                $type === 'whatsapp' ? 'whatsapp' : 'sms',
                'fees',
                [
                    'parent_name' => $data['parent_name'] ?? 'Parent/Guardian',
                    'amount_due' => number_format((float) $balance, 2),
                    'student_name' => $studentName,
                    'class_name' => $data['class_name'] ?? '',
                    'due_date' => $data['due_date'] ?? '',
                ],
                [
                    'subject' => 'Fee Reminder: ' . $studentName,
                    'purpose' => 'fees',
                    'sender_id' => $this->user_id ?: 1,
                    'business_event_id' => $eventId,
                ]
            );
            $businessEvent->markProcessed($eventId);
            $this->logFeeReminderActivity($studentId, $phone, $balance, $type, $result['status']);
            return [
                'status' => $result['status'] === 'failed' ? 'error' : 'success',
                'message' => ucfirst($type) . ' fee reminder queued for delivery',
                'communication_id' => $result['communication_id'],
                'data' => $result,
            ];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("Fee Reminder Error: " . $e->getMessage());
            \App\API\Services\Logger::legacyError('[CommunicationsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['status' => 'error', 'message' => 'Unable to queue fee reminder.', 'data' => null];
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
            \App\API\Includes\FileLogger::write('communications', [
                'type' => 'fee_reminder',
                'action' => $type . '_reminder_' . $status,
                'entity' => 'student',
                'entity_id' => $studentId,
                'user_id' => $_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['sub'] ?? null,
                'details' => [
                    'phone' => $phone,
                    'balance' => $balance,
                    'type' => $type,
                    'status' => $status,
                ],
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail the main operation
            \App\API\Services\Logger::legacyError("Fee Reminder Log Error: " . $e->getMessage());
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

        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        $sent = 0;
        $failed = [];
        $sent_ids = [];
        $template_title = $template['name'] ?? $title;

        foreach ($recipients as $phone) {
            try {
                $queued = $platform->queueRenderedForContacts([['phone' => $phone]], $type === 'whatsapp' ? 'whatsapp' : 'sms', $template_title, $rendered, [
                    'purpose' => $title ?: 'transactional',
                    'sender_id' => $this->user_id ?: 1,
                    'legacy_template_id' => $template_id,
                ]);
                if (($queued['recipient_count'] ?? 0) > 0) {
                    $sent++;
                    $sent_ids[] = $queued['communication_id'];
                } else {
                    $failed[] = $phone;
                }
            } catch (\Exception $e) {
                $failed[] = $phone;
                \App\API\Services\Logger::legacyError("SMS Error: " . $e->getMessage());
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

        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        $queued = $platform->queueRenderedForContacts(
            array_map(static function ($phone) { return ['phone' => $phone]; }, $recipients),
            'whatsapp',
            $template['subject'] ?? 'WhatsApp Message',
            $message,
            ['purpose' => $category ?: 'transactional', 'sender_id' => $this->user_id ?: 1]
        );
        if (!empty($documents) && !empty($queued['communication_id'])) {
            foreach ((array) $documents as $document) {
                $url = is_array($document) ? ($document['url'] ?? $document['public_url'] ?? null) : (string) $document;
                if (!$url) continue;
                $name = is_array($document) ? ($document['name'] ?? basename(parse_url($url, PHP_URL_PATH) ?: 'document')) : basename(parse_url($url, PHP_URL_PATH) ?: 'document');
                $this->db->prepare("INSERT INTO communication_attachments (communication_id, file_name, file_path, mime_type, public_url) VALUES (?, ?, ?, ?, ?)")
                    ->execute([(int) $queued['communication_id'], $name, $url, is_array($document) ? ($document['mime_type'] ?? null) : null, $url]);
                $attachmentId = (int) $this->db->lastInsertId();
                $this->db->prepare("INSERT INTO communication_attachment_channels (attachment_id, channel, provider_media_url, status) VALUES (?, 'whatsapp', ?, 'ready')")
                    ->execute([$attachmentId, $url]);
            }
        }
        return [
            'status' => ($queued['recipient_count'] ?? 0) > 0 ? 'success' : 'error',
            'sent_count' => 0,
            'queued_count' => $queued['recipient_count'] ?? 0,
            'failed_count' => ($queued['recipient_count'] ?? 0) > 0 ? 0 : count($recipients),
            'failed' => ($queued['recipient_count'] ?? 0) > 0 ? [] : $recipients,
            'documents_sent' => !empty($documents),
            'communication_ids' => !empty($queued['communication_id']) ? [$queued['communication_id']] : []
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
        if (!is_array($recipients)) $recipients = [$recipients];
        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        $sent = 0;
        $failed = [];

        foreach ($recipients as $phone) {
            try {
                $queued = $platform->queueRenderedForContacts([['phone' => $phone]], $type === 'whatsapp' ? 'whatsapp' : 'sms', $message, $message, [
                    'purpose' => 'transactional',
                    'sender_id' => $this->user_id ?: 1,
                ]);
                if (($queued['recipient_count'] ?? 0) > 0) {
                    $sent++;
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
        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        if (!is_array($recipients)) $recipients = [$recipients];
        $queuedIds = [];
        $failed = [];
        foreach ($recipients as $email => $name) {
            if (is_int($email)) { $email = $name; $name = ''; }
            $email = trim((string) $email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $failed[] = $email; continue; }
            $renderedBody = is_array($body) ? $this->manager->formatFormalEmailBody($body) : (string) $body;
            if ($signature !== '') $renderedBody .= '<p>' . htmlspecialchars($signature, ENT_QUOTES, 'UTF-8') . '</p>';
            $queued = $platform->queueRenderedForContacts([['email' => $email]], 'email', $subject, $renderedBody, [
                'purpose' => 'transactional',
                'sender_id' => $this->user_id ?: 1,
            ]);
            if (($queued['recipient_count'] ?? 0) > 0) {
                $queuedIds[] = (int) $queued['communication_id'];
                foreach ((array) $attachments as $attachment) {
                    $path = is_array($attachment) ? ($attachment['file_path'] ?? $attachment['path'] ?? null) : (string) $attachment;
                    if (!$path) continue;
                    $name = is_array($attachment) ? ($attachment['file_name'] ?? basename($path)) : basename($path);
                    $this->db->prepare("INSERT INTO communication_attachments (communication_id, file_name, file_path, public_url) VALUES (?, ?, ?, ?)")
                        ->execute([(int) $queued['communication_id'], $name, $path, filter_var($path, FILTER_VALIDATE_URL) ? $path : null]);
                    $attachmentId = (int) $this->db->lastInsertId();
                    $this->db->prepare("INSERT INTO communication_attachment_channels (attachment_id, channel, status) VALUES (?, 'email', 'ready')")
                        ->execute([$attachmentId]);
                }
            } else $failed[] = $email;
        }
        return [
            'status' => $queuedIds ? 'success' : 'error',
            'message' => $queuedIds ? 'Email queued for delivery' : 'No email was queued',
            'queued_count' => count($queuedIds),
            'communication_ids' => $queuedIds,
            'failed' => $failed,
        ];
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

    // --- Internal User-to-User Messaging ---
    public function listConversations($userId)
    {
        return $this->internalMessagingManager->listConversations($userId);
    }
    public function getConversationThread($userId, $conversationId)
    {
        return $this->internalMessagingManager->getConversation($userId, (int)$conversationId);
    }
    public function createConversation($userId, $data)
    {
        return $this->internalMessagingManager->createConversation($userId, $data);
    }
    public function sendConversationReply($userId, $conversationId, $data)
    {
        return $this->internalMessagingManager->sendReply($userId, (int)$conversationId, $data);
    }
    public function searchMessageRecipients($userId, $term)
    {
        return $this->internalMessagingManager->searchRecipients($userId, (string)$term);
    }

    // --- Communications CRUD ---
    public function createCommunication($data)
    {
        return $this->manager->createCommunication($data);
    }

    public function getAudienceOptions(): array
    {
        $fetch = static function (\PDO $db, string $sql): array { $stmt = $db->query($sql); return $stmt->fetchAll(\PDO::FETCH_ASSOC); };
        $staffDirectory = ReadReplicaService::qualifiedRef('staff_directory');
        $personDirectory = ReadReplicaService::qualifiedRef('person_directory');
        return [
            'parents' => $fetch($this->db, "SELECT DISTINCT pr.parent_id AS id, CONCAT_WS(' ', pr.first_name, pr.last_name) AS name, pr.phone FROM " . $personDirectory . " pr WHERE pr.parent_status = 'active' AND pr.phone IS NOT NULL ORDER BY name"),
            'students' => $fetch($this->db, "SELECT s.student_id AS id, CONCAT_WS(' ', s.first_name, s.last_name) AS name, s.admission_no FROM " . $personDirectory . " s WHERE s.student_status = 'active' ORDER BY name"),
            'classes' => $fetch($this->db, "SELECT c.id, c.name, sl.name AS school_level FROM " . ReadReplicaService::qualifiedRef("classes") . " c JOIN school_levels sl ON sl.id = c.level_id ORDER BY sl.id, c.name"),
            'student_types' => $fetch($this->db, "SELECT id, code, name FROM student_types WHERE status = 'active' ORDER BY name"),
            'school_levels' => $fetch($this->db, "SELECT id, code, name FROM school_levels WHERE status = 'active' ORDER BY name"),
            'vendors' => $fetch($this->db, "SELECT id, name, phone FROM suppliers WHERE status = 'active' ORDER BY name"),
            'staff' => $fetch($this->db, "SELECT staff_id AS id, CONCAT_WS(' ', first_name, last_name) AS name, phone FROM {$staffDirectory} WHERE staff_status = 'active' ORDER BY name"),
        ];
    }

    /** Dispatch an immediate outbox record; scheduled records remain queued. */
    public function dispatchCommunication(int $id): array
    {
        $result = (new \App\API\Services\CommunicationOutboxService($this->db))->processOne($id);
        return $this->getCommunication($id) + ['dispatch_result' => $result];
    }
    public function updateDeliveryStatusByProvider($providerMessageId, $status, $deliveredAt = null, $errorMessage = null)
    {
        return $this->manager->updateDeliveryStatusByProvider($providerMessageId, $status, $deliveredAt, $errorMessage);
    }
    public function getCommunication($id, array $filters = [])
    {
        return $this->manager->getCommunication($id, $filters);
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

    public function storeIncomingWhatsappMessage(array $data)
    {
        return $this->manager->storeIncomingWhatsappMessage($data);
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
            $platform = new \App\API\Services\CommunicationPlatformService($this->db);
            $queued = $platform->queueProviderTemplateForContacts(
                array_map(static function ($phone) { return ['phone' => $phone]; }, $recipients),
                (string) $templateId,
                (array) $variables,
                ['purpose' => 'transactional', 'sender_id' => $this->user_id ?: 1]
            );
            $count = (int) ($queued['recipient_count'] ?? 0);

            return [
                'status' => $count > 0 ? 'success' : 'error',
                'message' => $count > 0 ? "Queued to {$count} recipient(s)" : 'No recipients were queued',
                'data' => [
                    'queued' => $count,
                    'communication_id' => $queued['communication_id'] ?? null,
                ]
            ];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("WhatsApp Template Error: " . $e->getMessage());
            \App\API\Services\Logger::legacyError('[CommunicationsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['status' => 'error', 'message' => 'An internal error occurred.', 'data' => null];
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
                $providerData = $result['data'] ?? [];
                $providerTemplateId = $providerData['templateId'] ?? $providerData['template_id'] ?? null;
                if ($providerTemplateId) {
                    $this->manager->registerProviderTemplate([
                        'provider_template_id' => $providerTemplateId,
                        'name' => $name,
                        'language' => $language,
                        'status' => $providerData['templateStatus'] ?? 'Pending',
                        'body' => $components['body']['text'] ?? '',
                    ]);
                }
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
            \App\API\Services\Logger::legacyError("Create WhatsApp Template Error: " . $e->getMessage());
            \App\API\Services\Logger::legacyError('[CommunicationsAPI] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['status' => 'error', 'message' => 'An internal error occurred.', 'data' => null];
        }
    }

    /** Normalize boolean and structured provider responses. */
    private function isDeliverySuccess($result): bool
    {
        if ($result === true) return true;
        if (!is_array($result)) return false;
        return in_array(strtolower((string) ($result['status'] ?? '')), ['success', 'sent', 'queued', 'accepted'], true);
    }
}
