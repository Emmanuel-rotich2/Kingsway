<?php
/**
 * InternalMessagingManager
 *
 * User-to-user internal messaging for system users. Reuses the existing
 * normalised (4NF) messaging schema only — no new tables:
 *   - internal_conversations      (master/context: a conversation between users)
 *   - internal_messages           (audit/history: every message in a conversation)
 *   - conversation_participants   (context: which users belong, per-user unread counts)
 *   - message_read_status         (audit: per-recipient read receipts)
 *
 * All queries are parameterised and run inside transactions where a write
 * touches more than one table, so the aggregate state stays consistent.
 */

namespace App\API\Modules\communications;

use App\API\Services\NotificationService;
use App\API\Services\ReadReplicaService;
use PDO;
use Exception;

class InternalMessagingManager
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Conversations the user participates in, most recent activity first.
     *
     * @param int $userId
     * @return array
     */
    public function listConversations(int $userId): array
    {
        $sql = "SELECT c.id, c.title, c.conversation_type, c.created_by,
                       c.last_message_at, c.participant_count,
                       cp.unread_count, cp.last_read_at, cp.is_muted,
                       (SELECT CONCAT_WS(' ', sp.first_name, sp.last_name)
                          FROM " . ReadReplicaService::qualifiedRef("person_directory") . " sp
                         WHERE sp.user_id = c.last_message_by) AS last_sender_name,
                       (SELECT im2.message_body
                          FROM internal_messages im2
                         WHERE im2.conversation_id = c.id
                           AND im2.status <> 'deleted'
                         ORDER BY im2.created_at DESC, im2.id DESC
                         LIMIT 1) AS last_message,
                       (SELECT im2.created_at
                          FROM internal_messages im2
                         WHERE im2.conversation_id = c.id
                           AND im2.status <> 'deleted'
                         ORDER BY im2.created_at DESC, im2.id DESC
                         LIMIT 1) AS last_message_at,
                       (SELECT GROUP_CONCAT(
                                  CONCAT_WS(' ', pp.first_name, pp.last_name)
                                  SEPARATOR ', ')
                          FROM conversation_participants cp2
                          JOIN users pu ON pu.id = cp2.participant_id
                          JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id = pu.person_id
                         WHERE cp2.conversation_id = c.id
                           AND cp2.participant_id <> :exclude_user_id
                            AND cp2.left_at IS NULL) AS participant_names
                   FROM internal_conversations c
                   JOIN conversation_participants cp
                     ON cp.conversation_id = c.id AND cp.participant_id = :user_id
                  WHERE cp.left_at IS NULL
                  ORDER BY COALESCE(c.last_message_at, c.created_at) DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':exclude_user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * One conversation: meta + full message thread. Marks messages read.
     *
     * @param int $userId
     * @param int $conversationId
     * @return array ['success' => bool, 'data' => [...], 'error' => string|null]
     */
    public function getConversation(int $userId, int $conversationId): array
    {
        if (!$this->isParticipant($userId, $conversationId)) {
            return ['success' => false, 'data' => null, 'error' => 'Conversation not found'];
        }

        $metaSql = "SELECT c.id, c.title, c.conversation_type, c.created_by,
                           c.last_message_at,
                           (SELECT GROUP_CONCAT(
                                      CONCAT_WS(' ', pp.first_name, pp.last_name)
                                      SEPARATOR ', ')
                              FROM conversation_participants cp2
                              JOIN users pu ON pu.id = cp2.participant_id
                              JOIN " . ReadReplicaService::qualifiedRef("persons") . " pp ON pp.id = pu.person_id
                             WHERE cp2.conversation_id = c.id
                               AND cp2.left_at IS NULL) AS participant_names
                      FROM internal_conversations c
                     WHERE c.id = :cid";
        $metaStmt = $this->db->prepare($metaSql);
        $metaStmt->execute([':cid' => $conversationId]);
        $meta = $metaStmt->fetch(PDO::FETCH_ASSOC);

        $msgSql = "SELECT im.id, im.sender_id, im.subject, im.message_body,
                          im.message_type, im.priority, im.status, im.created_at,
                          CASE WHEN im.sender_id = :user_id THEN 1 ELSE 0 END AS is_mine,
                          CONCAT_WS(' ', p.first_name, p.last_name) AS sender_name,
                          mrs.read_at
                     FROM internal_messages im
                     LEFT JOIN users u ON u.id = im.sender_id
                     LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = u.person_id
                     LEFT JOIN message_read_status mrs
                            ON mrs.message_id = im.id AND mrs.recipient_id = :reader_user_id
                    WHERE im.conversation_id = :cid
                      AND im.status <> 'deleted'
                    ORDER BY im.created_at ASC, im.id ASC";
        $msgStmt = $this->db->prepare($msgSql);
        $msgStmt->execute([':cid' => $conversationId, ':user_id' => $userId, ':reader_user_id' => $userId]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

        $this->markConversationRead($userId, $conversationId);

        return [
            'success' => true,
            'data' => ['conversation' => $meta, 'messages' => $messages],
            'error' => null,
        ];
    }

    /**
     * Create a conversation and send its first message.
     *
     * @param int   $userId      current authenticated user
     * @param array $data        recipients[] (user ids), subject, message, priority
     * @return array
     */
    public function createConversation(int $userId, array $data): array
    {
        $recipients = array_values(array_filter(
            array_map('intval', (array)($data['recipients'] ?? []))
        ));
        $recipients = array_values(array_unique($recipients));
        $recipients = array_diff($recipients, [$userId]); // cannot message yourself
        $subject  = trim((string)($data['subject'] ?? ''));
        $message  = trim((string)($data['message'] ?? ''));
        $priority = in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true)
            ? $data['priority']
            : 'normal';

        if (empty($recipients)) {
            return ['success' => false, 'data' => null, 'error' => 'At least one recipient is required'];
        }
        if ($message === '') {
            return ['success' => false, 'data' => null, 'error' => 'Message body is required'];
        }
        $validRecipients = $this->validateRecipients($recipients);
        if (count($validRecipients) !== count($recipients)) {
            return ['success' => false, 'data' => null, 'error' => 'One or more recipients are not active users'];
        }

        $title = $subject !== '' ? $subject : $this->deriveTitle($userId, $validRecipients);

        try {
            $senderName = $this->senderDisplayName($userId);
            $this->db->beginTransaction();

            $this->db->prepare(
                "INSERT INTO internal_conversations
                    (title, conversation_type, created_by, is_locked)
                 VALUES (?, 'one_on_one', ?, 0)"
            )->execute([$title, $userId]);
            $conversationId = (int)$this->db->lastInsertId();

            $this->addParticipant($conversationId, $userId);
            foreach ($validRecipients as $recipientId) {
                $this->addParticipant($conversationId, $recipientId);
            }

            $this->db->prepare(
                "UPDATE internal_conversations
                    SET participant_count =
                        (SELECT COUNT(*) FROM conversation_participants
                          WHERE conversation_id = ?)
                  WHERE id = ?"
            )->execute([$conversationId, $conversationId]);

            $messageId = $this->insertMessage($conversationId, $userId, $subject, $message, $priority);

            $this->bumpUnread($conversationId, $validRecipients);
            $this->notifyMessageRecipients($validRecipients, $conversationId, $message, false, $senderName);
            $this->markSenderRead($conversationId, $userId, $messageId);

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[InternalMessagingManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'data' => null, 'error' => 'An internal error occurred.'];
        }

        return $this->getConversation($userId, $conversationId);
    }

    /**
     * Reply inside an existing conversation.
     *
     * @param int $userId
     * @param int $conversationId
     * @param array $data  message (required), priority (optional)
     * @return array
     */
    public function sendReply(int $userId, int $conversationId, array $data): array
    {
        if (!$this->isParticipant($userId, $conversationId)) {
            return ['success' => false, 'data' => null, 'error' => 'Conversation not found'];
        }

        $message = trim((string)($data['message'] ?? ''));
        $priority = in_array($data['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true)
            ? $data['priority']
            : 'normal';

        if ($message === '') {
            return ['success' => false, 'data' => null, 'error' => 'Message body is required'];
        }

        try {
            $senderName = $this->senderDisplayName($userId);
            $this->db->beginTransaction();

            $messageId = $this->insertMessage($conversationId, $userId, '', $message, $priority);

            $others = $this->otherParticipants($conversationId, $userId);
            $this->bumpUnread($conversationId, $others);
            $this->notifyMessageRecipients($others, $conversationId, $message, true, $senderName);
            $this->markSenderRead($conversationId, $userId, $messageId);

            $this->db->commit();
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[InternalMessagingManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'data' => null, 'error' => 'An internal error occurred.'];
        }

        return $this->getConversation($userId, $conversationId);
    }

    /**
     * Search active system users to add as message recipients.
     *
     * @param int    $userId exclude self
     * @param string $term
     * @return array
     */
    public function searchRecipients(int $userId, string $term = ''): array
    {
        $sql = "SELECT u.id, u.username, u.status,
                       CONCAT_WS(' ', p.first_name, p.last_name) AS full_name,
                       p.email, p.photo_url,
                       GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles
                  FROM " . ReadReplicaService::qualifiedRef("person_directory") . " 
                 WHERE u.status = 'active'
                   AND u.id <> :user_id
                   AND (
                       CONCAT_WS(' ', p.first_name, p.last_name) LIKE :term1
                    OR p.email LIKE :term2
                    OR u.username LIKE :term3
                   )
                 GROUP BY u.id
                 ORDER BY full_name ASC
                 LIMIT 25";

        $like = '%' . $term . '%';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':term1' => $like, ':term2' => $like, ':term3' => $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    private function isParticipant(int $userId, int $conversationId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM conversation_participants
              WHERE conversation_id = ? AND participant_id = ? AND left_at IS NULL"
        );
        $stmt->execute([$conversationId, $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function addParticipant(int $conversationId, int $userId): void
    {
        $this->db->prepare(
            "INSERT IGNORE INTO conversation_participants
                (conversation_id, participant_id, role)
             VALUES (?, ?, 'participant')"
        )->execute([$conversationId, $userId]);
    }

    private function insertMessage(int $conversationId, int $senderId, string $subject, string $body, string $priority): int
    {
        $this->db->prepare(
            "INSERT INTO internal_messages
                (conversation_id, sender_id, subject, message_body,
                 message_type, priority, status)
             VALUES (?, ?, ?, ?, 'personal', ?, 'sent')"
        )->execute([$conversationId, $senderId, $subject, $body, $priority]);
        $messageId = (int)$this->db->lastInsertId();

        $this->db->prepare(
            "UPDATE internal_conversations
                SET last_message_at = NOW(), last_message_by = ?
              WHERE id = ?"
        )->execute([$senderId, $conversationId]);

        return $messageId;
    }

    private function bumpUnread(int $conversationId, array $participantIds): void
    {
        $stmt = $this->db->prepare(
            "UPDATE conversation_participants
                SET unread_count = unread_count + 1
              WHERE conversation_id = ? AND participant_id = ?"
        );
        foreach ($participantIds as $participantId) {
            $stmt->execute([$conversationId, $participantId]);
        }
    }

    private function senderDisplayName(int $userId): string
    {
        $people = ReadReplicaService::qualifiedRef('person_directory');
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', first_name, last_name)
               FROM {$people}
              WHERE user_id = ?
              LIMIT 1"
        );
        $stmt->execute([$userId]);
        return trim((string) $stmt->fetchColumn()) ?: 'a colleague';
    }

    private function notifyMessageRecipients(array $recipientIds, int $conversationId, string $message, bool $isReply, string $sender): void
    {
        if (empty($recipientIds)) return;
        $service = new NotificationService($this->db);
        $service->push($recipientIds, $isReply ? 'message_reply' : 'message',
            $isReply ? "New reply from {$sender}" : "New message from {$sender}",
            NotificationService::messageText($sender, $message),
            'medium', [
                'action_url' => 'home.php?route=communications/messages_inbox&conversation_id=' . $conversationId,
                'reference_type' => 'conversation',
                'reference_id' => $conversationId,
            ]);
    }

    private function markSenderRead(int $conversationId, int $userId, int $messageId): void
    {
        $this->db->prepare(
            "INSERT IGNORE INTO message_read_status (message_id, recipient_id)
             VALUES (?, ?)"
        )->execute([$messageId, $userId]);

        $this->db->prepare(
            "UPDATE conversation_participants
                SET unread_count = 0, last_read_at = NOW()
              WHERE conversation_id = ? AND participant_id = ?"
        )->execute([$conversationId, $userId]);
    }

    private function markConversationRead(int $userId, int $conversationId): void
    {
        $this->db->prepare(
            "INSERT IGNORE INTO message_read_status (message_id, recipient_id, read_at)
             SELECT im.id, ?, NOW()
               FROM internal_messages im
              WHERE im.conversation_id = ? AND im.sender_id <> ?"
        )->execute([$userId, $conversationId, $userId]);

        $this->db->prepare(
            "UPDATE conversation_participants
                SET unread_count = 0, last_read_at = NOW()
              WHERE conversation_id = ? AND participant_id = ?"
        )->execute([$conversationId, $userId]);
    }

    private function otherParticipants(int $conversationId, int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT participant_id FROM conversation_participants
              WHERE conversation_id = ? AND participant_id <> ? AND left_at IS NULL"
        );
        $stmt->execute([$conversationId, $userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function validateRecipients(array $recipientIds): array
    {
        if (empty($recipientIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($recipientIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT id FROM users
              WHERE id IN ($placeholders) AND status = 'active'"
        );
        $stmt->execute($recipientIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function deriveTitle(int $userId, array $recipientIds): string
    {
        $ids = array_merge([$userId], $recipientIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', pd.first_name, pd.last_name) AS full_name
               FROM " . ReadReplicaService::qualifiedRef("person_directory") . " 
              WHERE u.id IN ($placeholders)
              ORDER BY full_name ASC"
        );
        $stmt->execute($ids);
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return implode(', ', $names);
    }
}
