<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Services\EmailProfileService;

/**
 * IMAP mail-client operations for a mailbox profile.
 *
 * Read operations keep the historical array shapes ({success, message, ...})
 * so existing callers keep working; write operations throw RuntimeException
 * with a caller-safe message, and the controller maps them to HTTP errors.
 *
 * The library is webklex/php-imap v6: folders expose query()/messages(),
 * messages are addressed by UID, and appends go through Folder::appendMessage.
 */
class EmailInboxService
{
    private \PDO $db;
    private EmailProfileService $profileService;

    /** Cached special-folder resolution per profile id. */
    private array $specialFolderCache = [];

    private const SPECIAL_FOLDER_CANDIDATES = [
        'sent'   => ['Sent', 'Sent Items', 'Sent Messages', 'INBOX.Sent'],
        'drafts' => ['Drafts', 'INBOX.Drafts'],
        'trash'  => ['Trash', 'Deleted Items', 'Deleted Messages', 'INBOX.Trash'],
    ];

    private const SETTABLE_FLAGS = ['Seen', 'Flagged', 'Answered', 'Draft'];

    private const ALLOWED_TAGS = [
        'a', 'b', 'blockquote', 'br', 'code', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'table', 'tbody',
        'td', 'th', 'thead', 'tr', 'u', 'ul',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'a'     => ['href', 'title'],
        'img'   => ['src', 'alt', 'width', 'height'],
        'td'    => ['colspan', 'rowspan'],
        'th'    => ['colspan', 'rowspan'],
        'table' => ['border', 'cellpadding', 'cellspacing'],
    ];

    /** Tags whose contents are discarded entirely (never unwrapped). */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'link', 'meta', 'title', 'head', 'body', 'html',
        'video', 'audio', 'source', 'svg', 'math', 'base', 'applet',
    ];

    public function __construct(\PDO $db, EmailProfileService $profileService)
    {
        $this->db = $db;
        $this->profileService = $profileService;
    }

    /**
     * List all IMAP folders for a profile, with total/unread counts.
     */
    public function listMailboxes(int $profileId): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            $profile = $this->profileService->getCredentials($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'Email profile not found.'];
            }

            return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($profile): array {
                $folders = [];
                foreach ($client->getFolders() as $folder) {
                    $total = 0;
                    $unread = 0;
                    try {
                        $total = $folder->query()->count();
                        $unread = $folder->query()->whereUnseen()->count();
                    } catch (\Throwable $ignored) {
                        // A folder that cannot be examined still lists with zero counts.
                    }

                    $folders[] = [
                        'name'   => (string) $folder->name,
                        'path'   => (string) $folder->path,
                        'total'  => $total,
                        'unread' => $unread,
                    ];
                }

                return [
                    'success' => true,
                    'folders' => $folders,
                    'profile' => [
                        'id'    => (int) $profile['id'],
                        'label' => $profile['label'] ?? '',
                        'email' => $profile['imap_username'] ?? $profile['smtp_username'] ?? '',
                    ],
                ];
            });
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());

            return ['success' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * List messages in a folder with pagination and optional server-side
     * filters: q (full text), from, subject, unread, since, before.
     */
    public function listMessages(int $profileId, string $folder = 'INBOX', int $page = 1, int $limit = 25, array $filters = []): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $emptyPagination = ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0];

        try {
            $profile = $this->profileService->getCredentials($profileId);
            if (!$profile) {
                return ['success' => false, 'message' => 'Email profile not found.', 'messages' => [], 'pagination' => $emptyPagination];
            }

            $page = max(1, $page);
            $limit = max(1, min(100, $limit));

            return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $page, $limit, $filters, $emptyPagination): array {
                $imapFolder = $client->getFolder($folder);
                if (!$imapFolder) {
                    return ['success' => false, 'message' => "Folder '{$folder}' not found.", 'messages' => [], 'pagination' => $emptyPagination];
                }

                $query = $imapFolder->query()->leaveUnread();
                if (!$this->applyFilters($query, $filters)) {
                    // IMAP rejects "UID SEARCH" with no criteria ("Missing search
                    // parameters"); index the whole folder when nothing filtered.
                    $query->whereAll();
                }
                $query->setFetchOrderDesc();

                $paginator = $query->paginate($limit, $page);

                $messages = [];
                foreach ($paginator->items() as $message) {
                    $messages[] = $this->messageToSummary($message);
                }

                return [
                    'success'    => true,
                    'messages'   => $messages,
                    'pagination' => [
                        'total' => (int) $paginator->total(),
                        'page'  => $page,
                        'limit' => $limit,
                        'pages' => (int) $paginator->lastPage(),
                    ],
                ];
            });
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());

            return ['success' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage(), 'messages' => [], 'pagination' => $emptyPagination];
        }
    }

    /**
     * Fetch a single message (UID) with sanitized body and attachment index.
     */
    public function getMessage(int $profileId, string $folder, string $uid): ?array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            $profile = $this->profileService->getCredentials($profileId);
            if (!$profile) {
                return null;
            }

            return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $profileId): ?array {
                $imapFolder = $client->getFolder($folder);
                if (!$imapFolder) {
                    return null;
                }

                $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);

                return $this->messageToDetail($message, $profileId, $folder);
            });
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Unread message count for a folder.
     */
    public function getMessageCount(int $profileId, string $folder = 'INBOX'): int
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            $profile = $this->profileService->getCredentials($profileId);
            if (!$profile) {
                return 0;
            }

            return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder): int {
                $imapFolder = $client->getFolder($folder);
                if (!$imapFolder) {
                    return 0;
                }

                return (int) $imapFolder->query()->whereUnseen()->count();
            });
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * Add/remove message flags. Only Seen, Flagged, Answered and Draft are
     * settable; everything else is rejected.
     */
    public function setFlags(int $profileId, string $folder, string $uid, array $add = [], array $remove = []): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $add = $this->normalizeFlags($add);
        $remove = $this->normalizeFlags($remove);

        if ($add === [] && $remove === []) {
            throw new \InvalidArgumentException('No valid flags supplied.');
        }

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $add, $remove, $profileId): array {
            $imapFolder = $client->getFolder($folder) ?? throw new \RuntimeException("Folder '{$folder}' not found.");
            $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);

            $ok = true;
            if ($add !== []) {
                $ok = $message->addFlag($add) && $ok;
            }
            if ($remove !== []) {
                $ok = $message->unsetFlag($remove) && $ok;
            }

            Logger::legacyError('[EmailInbox] flags updated profile=' . $profileId . ' folder=' . $folder . ' uid=' . $uid . ' add=' . implode(',', $add) . ' remove=' . implode(',', $remove));

            return ['success' => $ok, 'message' => $ok ? 'Flags updated.' : 'Some flags could not be updated.'];
        });
    }

    /**
     * Copy a message to another folder.
     */
    public function copyMessage(int $profileId, string $folder, string $uid, string $targetFolder): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $targetFolder): array {
            $imapFolder = $client->getFolder($folder) ?? throw new \RuntimeException("Folder '{$folder}' not found.");
            $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);
            $message->copy($targetFolder, false);

            return ['success' => true, 'message' => 'Message copied to ' . $targetFolder . '.'];
        });
    }

    /**
     * Move a message to another folder.
     */
    public function moveMessage(int $profileId, string $folder, string $uid, string $targetFolder): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $targetFolder): array {
            $imapFolder = $client->getFolder($folder) ?? throw new \RuntimeException("Folder '{$folder}' not found.");
            $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);
            $message->move($targetFolder, false);

            return ['success' => true, 'message' => 'Message moved to ' . $targetFolder . '.'];
        });
    }

    /**
     * Delete a message. Default moves it to the Trash folder when one exists;
     * permanent deletion flags it \Deleted and expunges.
     */
    public function deleteMessage(int $profileId, string $folder, string $uid, bool $permanent = false): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $permanent, $profileId): array {
            $imapFolder = $client->getFolder($folder) ?? throw new \RuntimeException("Folder '{$folder}' not found.");
            $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);

            if (!$permanent) {
                $trash = $this->resolveSpecialFolderWithClient($client, $profileId, 'trash');
                if ($trash !== null && strcasecmp($trash, $folder) !== 0) {
                    $message->move($trash, false);

                    return ['success' => true, 'message' => 'Message moved to ' . $trash . '.', 'permanent' => false];
                }
            }

            $message->delete(true);

            return ['success' => true, 'message' => 'Message deleted.', 'permanent' => true];
        });
    }

    /**
     * Append a raw MIME message to a folder (Sent/Drafts copies).
     *
     * @param array $flags e.g. ['\\Seen'] for Sent, ['\\Draft'] for Drafts
     */
    public function appendMessage(int $profileId, string $folder, string $rawMime, array $flags = [], ?string $internalDate = null): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        $internalDate = $internalDate ?? date('d-M-Y H:i:s O');

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $rawMime, $flags, $internalDate): array {
            $imapFolder = $client->getFolder($folder) ?? throw new \RuntimeException("Folder '{$folder}' not found.");
            $response = $imapFolder->appendMessage($rawMime, $flags, $internalDate);

            return ['success' => true, 'response' => $response];
        });
    }

    /**
     * Fetch one attachment's content by index. Returns null when the message
     * or attachment does not exist.
     */
    public function getAttachmentContent(int $profileId, string $folder, string $uid, int $index): ?array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            $profile = $this->profileService->getCredentials($profileId);
            if (!$profile) {
                return null;
            }

            return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($folder, $uid, $index): ?array {
                $imapFolder = $client->getFolder($folder);
                if (!$imapFolder) {
                    return null;
                }

                $message = $imapFolder->query()->leaveUnread()->getMessageByUid($uid);
                if (!$message->hasAttachments()) {
                    return null;
                }

                $attachments = $message->getAttachments();
                $attachment = $attachments->get($index);
                if ($attachment === null) {
                    return null;
                }

                $filename = (string) ($attachment->filename !== '' ? $attachment->filename : $attachment->name);
                if ($filename === '') {
                    $filename = 'attachment-' . $index;
                }

                return [
                    'filename'    => $filename,
                    'mime'        => (string) ($attachment->content_type ?: $attachment->getMimeType() ?: 'application/octet-stream'),
                    'size'        => (int) $attachment->size,
                    'content'     => (string) $attachment->content,
                    'content_id'  => trim((string) $attachment->id, '<>'),
                ];
            });
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Resolve a special folder name (sent|drafts|trash) on this mailbox,
     * preferring the account's own folder names over the common defaults.
     */
    public function resolveSpecialFolder(int $profileId, string $special): ?string
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $special = strtolower($special);
        if (!isset(self::SPECIAL_FOLDER_CANDIDATES[$special])) {
            throw new \InvalidArgumentException('Unknown special folder: ' . $special);
        }

        $profile = $this->profileService->getCredentials($profileId);
        if (!$profile) {
            throw new \RuntimeException('Email profile not found.');
        }

        return $this->withClient($profile, function (\Webklex\PHPIMAP\Client $client) use ($profileId, $special): ?string {
            return $this->resolveSpecialFolderWithClient($client, $profileId, $special);
        });
    }

    private function resolveSpecialFolderWithClient(\Webklex\PHPIMAP\Client $client, int $profileId, string $special): ?string
    {
        if (isset($this->specialFolderCache[$profileId][$special])) {
            return $this->specialFolderCache[$profileId][$special];
        }

        $candidates = self::SPECIAL_FOLDER_CANDIDATES[$special] ?? [];
        $folders = [];
        foreach ($client->getFolders() as $folder) {
            $folders[] = (string) $folder->name;
        }

        $resolved = null;
        $fallback = null;
        foreach ($folders as $name) {
            $segments = preg_split('/[.\/]/', $name) ?: [$name];
            $last = strtolower(trim((string) end($segments)));
            foreach ($candidates as $candidate) {
                $candidateLast = strtolower((string) preg_replace('/^.*[.\/]/', '', $candidate));
                if (strcasecmp($name, $candidate) === 0 || $last === $candidateLast) {
                    $resolved = $name;
                    break 2;
                }
            }
            if ($fallback === null && stripos($name, $special) !== false) {
                $fallback = $name;
            }
        }

        $resolved = $resolved ?? $fallback;
        $this->specialFolderCache[$profileId][$special] = $resolved;

        return $resolved;
    }

    private function applyFilters(\Webklex\PHPIMAP\Query\WhereQuery $query, array $filters): bool
    {
        $applied = false;
        if (($filters['unread'] ?? false) === true || ($filters['unread'] ?? '') === '1') {
            $query->whereUnseen();
            $applied = true;
        }
        if (($filters['flagged'] ?? false) === true || ($filters['flagged'] ?? '') === '1') {
            $query->where('FLAGGED');
            $applied = true;
        }
        if (isset($filters['q']) && trim((string) $filters['q']) !== '') {
            $query->whereText(trim((string) $filters['q']));
            $applied = true;
        }
        if (isset($filters['from']) && trim((string) $filters['from']) !== '') {
            $query->whereFrom(trim((string) $filters['from']));
            $applied = true;
        }
        if (isset($filters['subject']) && trim((string) $filters['subject']) !== '') {
            $query->whereSubject(trim((string) $filters['subject']));
            $applied = true;
        }
        if (isset($filters['since']) && trim((string) $filters['since']) !== '') {
            $query->whereSince(trim((string) $filters['since']));
            $applied = true;
        }
        if (isset($filters['before']) && trim((string) $filters['before']) !== '') {
            $query->whereBefore(trim((string) $filters['before']));
            $applied = true;
        }

        return $applied;
    }

    private function messageToSummary(\Webklex\PHPIMAP\Message $message): array
    {
        $fromAddress = $this->addressFirst($message->from);
        $toAddress = $this->addressFirst($message->to);
        $text = (string) $message->text;
        $preview = trim($text) !== '' ? $text : strip_tags((string) $message->html);

        return [
            'id'              => (string) $message->uid,
            'uid'             => (string) $message->uid,
            'msgn'            => (int) $message->msgn,
            'from'            => $fromAddress['name'] !== '' ? $fromAddress['name'] : $fromAddress['email'],
            'from_email'      => $fromAddress['email'],
            'to'              => $toAddress['name'] !== '' ? $toAddress['name'] : $toAddress['email'],
            'subject'         => (string) ($message->subject ?? '(No Subject)'),
            'date'            => $this->formatDate($message->date),
            'is_read'         => $message->hasFlag('Seen'),
            'is_flagged'      => $message->hasFlag('Flagged'),
            'has_attachments' => $message->hasAttachments(),
            'size'            => (int) $message->size,
            'preview'         => mb_substr(trim($preview), 0, 200),
        ];
    }

    private function messageToDetail(\Webklex\PHPIMAP\Message $message, int $profileId, string $folder): array
    {
        $fromAddress = $this->addressFirst($message->from);

        $attachments = [];
        $cidMap = [];
        if ($message->hasAttachments()) {
            $index = 0;
            foreach ($message->getAttachments() as $attachment) {
                $filename = (string) ($attachment->filename !== '' ? $attachment->filename : $attachment->name);
                if ($filename === '') {
                    $filename = 'attachment-' . $index;
                }
                $contentId = trim((string) $attachment->id, '<>');
                $attachments[] = [
                    'index'      => $index,
                    'name'       => (string) ($attachment->name ?? $filename),
                    'filename'   => $filename,
                    'size'       => (int) $attachment->size,
                    'mime'       => (string) ($attachment->content_type ?: $attachment->getMimeType() ?: 'application/octet-stream'),
                    'content_id' => $contentId,
                ];
                if ($contentId !== '') {
                    $cidMap[$contentId] = '/api/communications/mailboxes/messages/attachments/' . $index
                        . '?profile_id=' . $profileId
                        . '&folder=' . rawurlencode($folder)
                        . '&uid=' . rawurlencode((string) $message->uid);
                }
                $index++;
            }
        }

        return [
            'id'              => (string) $message->uid,
            'uid'             => (string) $message->uid,
            'msgn'            => (int) $message->msgn,
            'message_id'      => trim((string) $message->message_id, '<>'),
            'in_reply_to'     => trim((string) $message->in_reply_to, '<>'),
            'references'      => (string) $message->references,
            'from'            => $fromAddress['name'] !== '' ? $fromAddress['name'] : $fromAddress['email'],
            'from_email'      => $fromAddress['email'],
            'to'              => $this->addressList($message->to),
            'cc'              => $this->addressList($message->cc),
            'reply_to'        => $this->addressList($message->reply_to),
            'subject'         => (string) ($message->subject ?? '(No Subject)'),
            'date'            => $this->formatDate($message->date),
            'is_read'         => $message->hasFlag('Seen'),
            'is_flagged'      => $message->hasFlag('Flagged'),
            'text'            => (string) $message->text,
            'html'            => $this->sanitizeHtml((string) $message->html, $cidMap),
            'attachments'     => $attachments,
            'has_attachments' => $message->hasAttachments(),
        ];
    }

    /**
     * Coerce an attribute (Attribute|Address|string) into the first address.
     *
     * @return array{name:string, email:string}
     */
    private function addressFirst(mixed $address): array
    {
        $list = $this->addressList($address);

        return $list[0] ?? ['name' => '', 'email' => ''];
    }

    /**
     * Coerce an attribute (Attribute|Address|string) into a list of addresses.
     *
     * @return array<int, array{name:string, email:string}>
     */
    private function addressList(mixed $address): array
    {
        if ($address === null || $address === '') {
            return [];
        }

        if ($address instanceof \Webklex\PHPIMAP\Attribute) {
            $result = [];
            foreach ($address->all() as $value) {
                foreach ($this->addressList($value) as $entry) {
                    $result[] = $entry;
                }
            }

            return $result;
        }

        if ($address instanceof \Webklex\PHPIMAP\Address) {
            return [[
                'name'  => (string) ($address->personal !== '' ? $address->personal : $address->mail),
                'email' => (string) $address->mail,
            ]];
        }

        $string = trim((string) $address);
        if ($string === '') {
            return [];
        }

        $result = [];
        foreach (preg_split('/,\s*(?=[^,]*@)/', $string) ?: [$string] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(?:"?([^"<]*)"?\s*)?<([^>]+)>$/', $part, $matches)) {
                $result[] = ['name' => trim($matches[1]), 'email' => trim($matches[2])];
            } else {
                $result[] = ['name' => '', 'email' => $part];
            }
        }

        return $result;
    }

    private function formatDate(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }
        if ($date instanceof \Webklex\PHPIMAP\Attribute) {
            $first = $date->first();
            if ($first instanceof \DateTimeInterface) {
                return $first->format('Y-m-d H:i:s');
            }
            if (is_string($first) && $first !== '') {
                return $first;
            }
        }

        return trim((string) $date);
    }

    /**
     * Content-safe HTML: whitelist elements/attributes, drop scripts and
     * event handlers, force safe link behaviour and rewrite cid: images to
     * same-origin attachment endpoints.
     *
     * @param array<string, string> $cidMap content-id => URL
     */
    private function sanitizeHtml(string $html, array $cidMap = []): string
    {
        $html = trim($html);
        if ($html === '' || !class_exists(\DOMDocument::class)) {
            return strip_tags($html);
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new \DOMDocument();
            $wrapped = '<?xml encoding="UTF-8"><div id="__kingsway_mail_root">' . $html . '</div>';
            if (!$dom->loadHTML($wrapped, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return strip_tags($html);
            }

            $xpath = new \DOMXPath($dom);
            $wrapper = $xpath->query('//*[@id="__kingsway_mail_root"]')->item(0);
            if ($wrapper === null) {
                return strip_tags($html);
            }

            $this->sanitizeNode($wrapper, $cidMap);

            $output = '';
            foreach ($wrapper->childNodes as $child) {
                $output .= $dom->saveHTML($child);
            }

            return trim($output);
        } catch (\Throwable $e) {
            Logger::legacyError('[EmailInbox] sanitizeHtml failed: ' . $e->getMessage());

            return strip_tags($html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function sanitizeNode(\DOMNode $node, array $cidMap): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!$child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                $node->removeChild($child);
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];
            if ($child->hasAttributes()) {
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name);
                    if (!in_array($name, $allowed, true)) {
                        $child->removeAttribute($attribute->name);
                        continue;
                    }

                    if ($name === 'href' || $name === 'src') {
                        $url = trim((string) $attribute->value);
                        if ($name === 'src' && stripos($url, 'cid:') === 0) {
                            $contentId = trim(substr($url, 4), '<>');
                            if (isset($cidMap[$contentId])) {
                                $child->setAttribute('src', $cidMap[$contentId]);
                            } else {
                                $child->removeAttribute('src');
                            }
                            continue;
                        }
                        if (!preg_match('~^(https?:|mailto:)~i', $url)) {
                            $child->removeAttribute($attribute->name);
                        }
                    }
                }
            }

            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }

            $this->sanitizeNode($child, $cidMap);
        }
    }

    /**
     * @return array<int, string> normalized flag names
     */
    private function normalizeFlags(array $flags): array
    {
        $clean = [];
        foreach ($flags as $flag) {
            $name = ucfirst(strtolower(ltrim((string) $flag, '\\')));
            if (in_array($name, self::SETTABLE_FLAGS, true)) {
                $clean[$name] = $name;
            }
        }

        return array_values($clean);
    }

    private function buildImapConfig(array $profile): array
    {
        $port = (int) ($profile['imap_port'] ?? 993);
        $encryption = ($port === 993 || $port === 990) ? 'ssl' : (($port === 143) ? 'tls' : 'none');

        return [
            'host'          => $profile['imap_host'] ?? 'mail.kingswaypreparatoryschool.sc.ke',
            'port'          => $port,
            'encryption'    => $encryption,
            'validate_cert' => false,
            'username'      => $profile['imap_username'] ?? $profile['smtp_username'] ?? '',
            'password'      => $profile['imap_password'] ?? $profile['smtp_password'] ?? '',
            'protocol'      => 'imap',
        ];
    }

    /**
     * Run a callback against a connected client, always disconnecting after.
     */
    private function withClient(array $profile, callable $callback): mixed
    {
        $client = null;

        try {
            $cm = new \Webklex\PHPIMAP\ClientManager();
            $client = $cm->make($this->buildImapConfig($profile));
            $client->connect();

            return $callback($client);
        } finally {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }
}
