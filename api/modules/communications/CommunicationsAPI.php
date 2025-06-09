<?php

namespace App\API\Modules\communications;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/config.php';

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use App\API\Services\SMS\SMSGateway;


class CommunicationsAPI extends BaseAPI
{
    private $smsGateway;

    public function __construct()
    {
        parent::__construct('communications');
        $this->smsGateway = new SMSGateway([
            'provider' => \SMS_PROVIDER,
            'api_key' => \SMS_API_KEY,
            'username' => \SMS_USERNAME,
            'account_sid' => \SMS_ACCOUNT_SID,
            'auth_token' => \SMS_AUTH_TOKEN,
            'from' => \SMS_FROM_NUMBER
        ]);
    }

    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE subject LIKE ? OR message LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM communications $where");
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    c.*,
                    u.username as sender_name
                FROM communications c
                LEFT JOIN users u ON c.sender_id = u.id
                $where
                ORDER BY $sort $order 
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $communications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'communications' => $communications,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function get($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    u.username as sender_name
                FROM communications c
                LEFT JOIN users u ON c.sender_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$id]);
            $communication = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$communication) {
                return [
                    'status' => 'error',
                    'message' => 'Communication not found'
                ];
            }

            return [
                'status' => 'success',
                'data' => $communication
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function create($data)
    {
        try {
            $this->beginTransaction();

            $required = ['subject', 'message', 'type', 'recipients'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ];
            }

            $stmt = $this->db->prepare("
                INSERT INTO communications (
                    subject,
                    message,
                    type,
                    recipients,
                    sender_id,
                    status,
                    scheduled_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['subject'],
                $data['message'],
                $data['type'],
                json_encode($data['recipients']),
                $_SESSION['user_id'],
                $data['status'] ?? 'pending',
                $data['scheduled_at'] ?? null
            ]);

            $id = $this->db->lastInsertId();

            $this->logAction('create', $id, "Created new communication: {$data['subject']}");

            $this->commit();

            return [
                'status' => 'success',
                'message' => 'Communication created successfully',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function update($id, $data)
    {
        try {
            $this->beginTransaction();

            $stmt = $this->db->prepare("SELECT id FROM communications WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return [
                    'status' => 'error',
                    'message' => 'Communication not found'
                ];
            }

            $updates = [];
            $params = [];
            $allowedFields = ['subject', 'message', 'type', 'recipients', 'status', 'scheduled_at'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    if ($field === 'recipients') {
                        $updates[] = "$field = ?";
                        $params[] = json_encode($data[$field]);
                    } else {
                        $updates[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE communications SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->logAction('update', $id, "Updated communication details");

            $this->commit();

            return [
                'status' => 'success',
                'message' => 'Communication updated successfully'
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE communications SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return [
                    'status' => 'error',
                    'message' => 'Communication not found'
                ];
            }

            $this->logAction('delete', $id, "Deleted communication");

            return [
                'status' => 'success',
                'message' => 'Communication deleted successfully'
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAnnouncements($params)
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = "WHERE type = 'announcement'";
            $bindings = [];

            if (!empty($params['category'])) {
                $where .= " AND category = ?";
                $bindings[] = $params['category'];
            }

            // Get total count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM communications $where");
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    c.*,
                    u.username as sender_name
                FROM communications c
                LEFT JOIN users u ON c.sender_id = u.id
                $where
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'announcements' => $announcements,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getNotifications($params)
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $userId = $_SESSION['user_id'];
            $where = "WHERE type = 'notification' AND JSON_CONTAINS(recipients, ?, '$')";
            $bindings = [$userId];

            if (!empty($params['status'])) {
                $where .= " AND status = ?";
                $bindings[] = $params['status'];
            }

            // Get total count
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM communications $where");
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    c.*,
                    u.username as sender_name
                FROM communications c
                LEFT JOIN users u ON c.sender_id = u.id
                $where
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'notifications' => $notifications,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function sendAnnouncement($data)
    {
        try {
            $data['type'] = 'announcement';
            return $this->create($data);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function sendNotification($data)
    {
        try {
            $this->validateNotificationData($data);
            $results = [];
            
            if ($data['send_email']) {
                $results['email'] = $this->sendEmail([
                    'to' => $data['email'],
                    'subject' => $data['subject'],
                    'message' => $data['message'],
                    'attachments' => $data['attachments'] ?? []
                ]);
            }
            
            if ($data['send_sms']) {
                $results['sms'] = $this->sendSingleSMS($data['phone'], $data['message']);
            }
            
            return $this->response(['status' => 'success', 'data' => $results]);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send bulk SMS to a list of recipients
     */
    public function sendBulkSMS($data)
    {
        try {
            $this->validateBulkSMSData($data);
            $results = [];
            foreach ($data['recipients'] as $recipient) {
                $message = $this->parseTemplate($data['template'], $recipient['data'] ?? []);
                $results[] = $this->sendSingleSMS($recipient['phone'], $message);
            }
            return $this->response(['status' => 'success', 'data' => $results]);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send bulk Email to a list of recipients
     */
    public function sendBulkEmail($data)
    {
        try {
            $this->validateBulkEmailData($data);
            $results = [];
            foreach ($data['recipients'] as $recipient) {
                $message = $this->parseTemplate($data['template'], $recipient['data'] ?? []);
                $results[] = $this->sendSingleEmail(
                    $recipient['email'],
                    $data['subject'],
                    $message,
                    $data['attachments'] ?? []
                );
            }
            return $this->response(['status' => 'success', 'data' => $results]);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function sendEmail($data)
    {
        try {
            $this->validateEmailData($data);
            return $this->sendSingleEmail($data['to'], $data['subject'], $data['message'], $data['attachments'] ?? []);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function sendSingleEmail($to, $subject, $message, $attachments = [])
    {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = \SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = \SMTP_USERNAME;
            $mail->Password = \SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = \SMTP_PORT;
            $mail->setFrom(\SMTP_FROM_EMAIL, \SMTP_FROM_NAME);
            $mail->addAddress($to);

            // Set content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br($message);
            $mail->AltBody = strip_tags($message);

            // Add attachments
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && file_exists($attachment['path'])) {
                    $mail->addAttachment(
                        $attachment['path'],
                        $attachment['name'] ?? basename($attachment['path'])
                    );
                }
            }

            return $mail->send();
        } catch (PHPMailerException $e) {
            throw new Exception("Failed to send email: " . $e->getMessage());
        }
    }

    private function sendSingleSMS($to, $message)
    {
        try {
            return $this->smsGateway->send($to, $message);
        } catch (Exception $e) {
            throw new Exception("Failed to send SMS: " . $e->getMessage());
        }
    }

    private function validateEmailData($data)
    {
        $required = ['to', 'subject', 'message'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
        if (!filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
    }

    private function validateBulkEmailData($data)
    {
        $required = ['recipients', 'subject', 'template'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
        if (empty($data['recipients'])) {
            throw new Exception('Recipients list cannot be empty');
        }
    }

    private function validateSMSData($data)
    {
        $required = ['to', 'message'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }

    private function validateBulkSMSData($data)
    {
        $required = ['recipients', 'template'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
        if (empty($data['recipients'])) {
            throw new Exception('Recipients list cannot be empty');
        }
    }

    private function validateNotificationData($data)
    {
        $required = ['subject', 'message'];
        $missing = $this->validateRequired($data, $required);
        if (!empty($missing)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
        
        if ($data['send_email'] && empty($data['email'])) {
            throw new Exception('Email address required for email notification');
        }
        
        if ($data['send_sms'] && empty($data['phone'])) {
            throw new Exception('Phone number required for SMS notification');
        }
    }

    public function getTemplates($type = null)
    {
        try {
            $sql = "SELECT * FROM notification_templates";
            $params = [];

            if ($type) {
                $sql .= " WHERE type = ?";
                $params[] = $type;
            }

            $sql .= " ORDER BY name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $templates]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createTemplate($data)
    {
        try {
            $required = ['type', 'name', 'email_subject', 'email_body'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO notification_templates (
                    type,
                    name,
                    description,
                    email_subject,
                    email_body,
                    sms_body,
                    send_email,
                    send_sms,
                    variables,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['type'],
                $data['name'],
                $data['description'] ?? null,
                $data['email_subject'],
                $data['email_body'],
                $data['sms_body'] ?? null,
                $data['send_email'] ?? true,
                $data['send_sms'] ?? false,
                json_encode($data['variables'] ?? []),
                'active'
            ]);

            $templateId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Template created successfully',
                'data' => ['id' => $templateId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getGroups()
    {
        try {
            $sql = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT cr.recipient_id) as member_count,
                    u.username as created_by_name
                FROM communications c
                LEFT JOIN communication_recipients cr ON c.id = cr.communication_id
                LEFT JOIN users u ON c.sender_id = u.id
                WHERE c.type = 'group'
                GROUP BY c.id
                ORDER BY c.subject
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $groups]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createGroup($data)
    {
        try {
            $required = ['name', 'type'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->beginTransaction();

            // Create group as a special type of communication
            $sql = "
                INSERT INTO communications (
                    subject,
                    message,
                    type,
                    status,
                    sender_id
                ) VALUES (?, ?, 'group', 'active', ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $this->user_id
            ]);

            $groupId = $this->db->lastInsertId();

            // Add members if provided
            if (!empty($data['members'])) {
                $sql = "
                    INSERT INTO communication_recipients (
                        communication_id,
                        recipient_type,
                        recipient_id
                    ) VALUES (?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['members'] as $member) {
                    $stmt->execute([
                        $groupId,
                        $member['type'],
                        $member['id']
                    ]);
                }
            }

            $this->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Group created successfully',
                'data' => ['id' => $groupId]
            ], 201);
        } catch (Exception $e) {
            $this->rollback();
            return $this->handleException($e);
        }
    }

    public function getSMSTemplates()
    {
        try {
            $sql = "SELECT * FROM message_templates WHERE type = 'sms' AND status = 'active' ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $templates]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getEmailTemplates()
    {
        try {
            $sql = "SELECT * FROM message_templates WHERE type = 'email' AND status = 'active' ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response(['status' => 'success', 'data' => $templates]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSMSTemplate($data)
    {
        try {
            $required = ['name', 'content'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO message_templates (
                    name,
                    description,
                    content,
                    type,
                    variables,
                    status
                ) VALUES (?, ?, ?, 'sms', ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['content'],
                json_encode($data['variables'] ?? []),
                'active'
            ]);

            $templateId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'SMS template created successfully',
                'data' => ['id' => $templateId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEmailTemplate($data)
    {
        try {
            $required = ['name', 'subject', 'body'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO message_templates (
                    name,
                    description,
                    subject,
                    content,
                    type,
                    variables,
                    status
                ) VALUES (?, ?, ?, ?, 'email', ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['subject'],
                $data['body'],
                json_encode($data['variables'] ?? []),
                'active'
            ]);

            $templateId = $this->db->lastInsertId();

            return $this->response([
                'status' => 'success',
                'message' => 'Email template created successfully',
                'data' => ['id' => $templateId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSMSConfig()
    {
        try {
            $sql = "SELECT * FROM sms_config WHERE status = 'active' LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($config) {
                // Remove sensitive data
                unset($config['api_key']);
                unset($config['api_secret']);
            }

            return $this->response(['status' => 'success', 'data' => $config]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateSMSConfig($data)
    {
        try {
            $required = ['provider', 'api_key'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $sql = "
                INSERT INTO sms_config (
                    provider,
                    api_key,
                    api_secret,
                    sender_id,
                    webhook_url,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    api_key = VALUES(api_key),
                    api_secret = VALUES(api_secret),
                    sender_id = VALUES(sender_id),
                    webhook_url = VALUES(webhook_url)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['provider'],
                $data['api_key'],
                $data['api_secret'] ?? null,
                $data['sender_id'] ?? null,
                $data['webhook_url'] ?? null,
                'active'
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'SMS configuration updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Parse a template with given data
     */
    private function parseTemplate($template, $data = []) {
        $parsed = $template;
        foreach ($data as $key => $value) {
            $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
        }
        return $parsed;
    }
}
