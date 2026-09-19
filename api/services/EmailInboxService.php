<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Services\EmailProfileService;

class EmailInboxService
{
    private \PDO $db;
    private EmailProfileService $profileService;

    public function __construct(\PDO $db, EmailProfileService $profileService)
    {
        $this->db = $db;
        $this->profileService = $profileService;
    }

    /**
     * List all IMAP mailboxes for a given email profile.
     *
     * @param int $profileId
     * @return array
     */
    public function listMailboxes(int $profileId): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $client = null;

        try {
            $profile = $this->profileService->getCredentials($profileId);

            if (!$profile) {
                return ['success' => false, 'message' => 'Email profile not found.'];
            }

            $config = $this->buildImapConfig($profile);

            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($config);

            $folders = [];
            foreach ($client->getFolders() as $folder) {
                $folders[] = [
                    'name'   => $folder->name,
                    'total'  => $folder->getMessages()->count(),
                    'unread' => $folder->getUnseenMessages()->count(),
                ];
            }

            return [
                'success' => true,
                'folders' => $folders,
                'profile' => [
                    'id'    => (int) $profile['id'],
                    'label' => $profile['label'] ?? $profile['profile_name'] ?? '',
                    'email' => $profile['imap_username'] ?? $profile['smtp_username'] ?? '',
                ],
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());
            return ['success' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage()];
        } finally {
            if (isset($client)) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * List messages in a folder with pagination.
     *
     * @param int    $profileId
     * @param string $folder
     * @param int    $page
     * @param int    $limit
     * @return array
     */
    public function listMessages(int $profileId, string $folder = 'INBOX', int $page = 1, int $limit = 25): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $client = null;

        try {
            $profile = $this->profileService->getCredentials($profileId);

            if (!$profile) {
                return ['success' => false, 'message' => 'Email profile not found.', 'messages' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
            }

            $config = $this->buildImapConfig($profile);

            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($config);

            $imapFolder = $client->getFolder($folder);

            if (!$imapFolder) {
                return ['success' => false, 'message' => "Folder '{$folder}' not found.", 'messages' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
            }

            $totalMessages = $imapFolder->getMessages()->count();
            $pages = (int) ceil($totalMessages / $limit);

            $paginated = $imapFolder->getMessages()->setPage($page, $limit)->get();

            $messages = [];
            foreach ($paginated as $message) {
                $messages[] = [
                    'id'               => (string) $message->id,
                    'from'             => $this->extractName($message->from),
                    'from_email'       => $this->extractEmail($message->from),
                    'to'               => $this->extractName($message->to),
                    'subject'          => (string) ($message->subject ?? '(No Subject)'),
                    'date'             => $message->date instanceof \DateTimeImmutable
                        ? $message->date->format('Y-m-d H:i:s')
                        : (string) $message->date,
                    'is_read'          => $message->isRead(),
                    'has_attachments'  => $message->hasAttachments(),
                    'preview'          => mb_substr((string) $message->text, 0, 200),
                ];
            }

            return [
                'success'    => true,
                'messages'   => $messages,
                'pagination' => [
                    'total' => $totalMessages,
                    'page'  => $page,
                    'limit' => $limit,
                    'pages' => $pages,
                ],
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());
            return ['success' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage(), 'messages' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]];
        } finally {
            if (isset($client)) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Get a single message by ID.
     *
     * @param int    $profileId
     * @param string $folder
     * @param string $messageId
     * @return array|null
     */
    public function getMessage(int $profileId, string $folder, string $messageId): ?array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $client = null;

        try {
            $profile = $this->profileService->getCredentials($profileId);

            if (!$profile) {
                return null;
            }

            $config = $this->buildImapConfig($profile);

            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($config);

            $imapFolder = $client->getFolder($folder);

            if (!$imapFolder) {
                return null;
            }

            $message = $imapFolder->getMessageById($messageId);

            if (!$message) {
                return null;
            }

            $attachments = [];
            if ($message->hasAttachments()) {
                foreach ($message->getAttachments() as $attachment) {
                    $attachments[] = [
                        'name' => $attachment->name,
                        'size' => $attachment->size,
                        'mime' => $attachment->mime,
                    ];
                }
            }

            return [
                'id'              => (string) $message->id,
                'from'            => $this->extractName($message->from),
                'from_email'      => $this->extractEmail($message->from),
                'to'              => $this->extractName($message->to),
                'cc'              => $this->extractName($message->cc ?? ''),
                'subject'         => (string) ($message->subject ?? '(No Subject)'),
                'date'            => $message->date instanceof \DateTimeImmutable
                    ? $message->date->format('Y-m-d H:i:s')
                    : (string) $message->date,
                'is_read'         => $message->isRead(),
                'text'            => (string) $message->text,
                'html'            => $this->sanitizeHtml((string) $message->html),
                'attachments'     => $attachments,
                'has_attachments' => $message->hasAttachments(),
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());
            return null;
        } finally {
            if (isset($client)) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Get unread message count for a folder.
     *
     * @param int    $profileId
     * @param string $folder
     * @return int
     */
    public function getMessageCount(int $profileId, string $folder = 'INBOX'): int
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $client = null;

        try {
            $profile = $this->profileService->getCredentials($profileId);

            if (!$profile) {
                return 0;
            }

            $config = $this->buildImapConfig($profile);

            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($config);

            $imapFolder = $client->getFolder($folder);

            if (!$imapFolder) {
                return 0;
            }

            return (int) $imapFolder->getUnseenMessages()->count();
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());
            return 0;
        } finally {
            if (isset($client)) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Build IMAP connection config from a profile record.
     *
     * @param array $profile
     * @return array
     */
    private function buildImapConfig(array $profile): array
    {
        $host = $profile['imap_host'] ?? 'mail.kingswaypreparatoryschool.sc.ke';
        $port = (int) ($profile['imap_port'] ?? 993);

        $encryption = ($port === 993) ? 'ssl' : (($port === 990) ? 'ssl' : (($port === 143) ? 'tls' : 'none'));

        return [
            'host'          => $host,
            'port'          => $port,
            'encryption'    => $encryption,
            'validate_cert' => false,
            'username'      => $profile['imap_username'] ?? $profile['smtp_username'] ?? '',
            'password'      => $profile['imap_password'] ?? $profile['smtp_password'] ?? '',
        ];
    }

    /**
     * Extract a display name from an address string or object.
     *
     * @param mixed $address
     * @return string
     */
    private function extractName($address): string
    {
        if (is_object($address) && property_exists($address, 'mail')) {
            return (string) ($address->mail ?? $address->host ?? '');
        }

        return (string) $address;
    }

    /**
     * Extract an email address from an address string or object.
     *
     * @param mixed $address
     * @return string
     */
    private function extractEmail($address): string
    {
        if (is_object($address)) {
            if (property_exists($address, 'mail')) {
                return (string) $address->mail;
            }
            if (property_exists($address, 'host')) {
                $user = property_exists($address, 'user') ? (string) $address->user : '';
                return $user . '@' . (string) $address->host;
            }
        }

        if (preg_match('/<([^>]+)>/', (string) $address, $matches)) {
            return $matches[1];
        }

        return (string) $address;
    }

    /**
     * Sanitize HTML content for safe display.
     *
     * @param string $html
     * @return string
     */
    private function sanitizeHtml(string $html): string
    {
        if ($html === '') {
            return '';
        }

        return strip_tags($html);
    }
}
