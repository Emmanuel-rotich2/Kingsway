<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;

/**
 * NotificationService — central push pipeline for the Kingsway notification
 * system.
 *
 * Modules push notifications here instead of writing the `notifications`
 * table directly, so every notification is:
 *
 *   - addressed to a real audience (users, staff members, roles, everyone);
 *   - human-readable ("Your leave request was approved by Jennifer");
 *   - optionally de-duplicated so regenerations and re-sends never spam;
 *   - recorded once with one read_status, so "mark as read" works everywhere.
 *
 * Usage from any module:
 *   $service = new NotificationService($this->db);
 *   $service->push(42, 'leave_request', 'Leave approved', 'Your leave request was approved by Jennifer', 'medium');
 *   $service->push('all_staff', 'fee_structure', 'Fee structure released', 'The 2026 fee structure is now out.', 'high');
 *   $service->push('role:3', 'announcement', 'Results released', 'Term 2 results are available.', 'medium');
 *   $service->push([7, 9], 'expense', 'Expense approved', 'Your expense was approved by David.');
 */
class NotificationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Push a notification to one or many recipients.
     *
     * $recipients accepts:
     *   - int            a single user id
     *   - int[]          a list of user ids
     *   - 'all_staff'    every active user linked to a staff record
     *   - 'all_users'    every active user
     *   - 'role:{id}'    every active user with the role id
     *   - 'role:{name}'  every active user with the role name
     *   - 'staff:{id}'   the active user(s) linked to a staff member id
     *
     * $options:
     *   - dedup_minutes: skip if an identical (user_id, type, title) row was
     *                    created within this window (e.g. 60 for calendar
     *                    regeneration jobs). Default: no dedup.
     *   - created_at:    override the timestamp (MySQL 'Y-m-d H:i:s').
     *
     * @return int number of notification rows inserted
     */
    public function push($recipients, string $type, string $title, string $message, string $priority = 'medium', array $options = []): int
    {
        $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';
        $userIds = $this->resolveRecipients($recipients);
        if (empty($userIds)) {
            return 0;
        }

        $dedupMinutes = (int) ($options['dedup_minutes'] ?? 0);
        $createdAt = $options['created_at'] ?? date('Y-m-d H:i:s');

        $inserted = 0;
        $hasContext = isset($options['action_url']) || isset($options['reference_type'])
            || isset($options['reference_id']) || isset($options['reminder_window']);
        $insert = $this->db->prepare($hasContext
            ? "INSERT INTO notifications
                (user_id, type, title, message, action_url, reference_type, reference_id,
                 reminder_window, priority, read_status, created_at)
               VALUES (:uid, :type, :title, :message, :action_url, :reference_type,
                       :reference_id, :reminder_window, :priority, 'unread', :created_at)"
            : "INSERT INTO notifications (user_id, type, title, message, priority, read_status, created_at)
               VALUES (:uid, :type, :title, :message, :priority, 'unread', :created_at)");
        $dedup = $dedupMinutes > 0
            ? $this->db->prepare(
                "SELECT COUNT(*) FROM notifications
                  WHERE user_id = :uid AND type = :type AND title = :title
                    AND created_at >= NOW() - INTERVAL :minutes MINUTE"
            )
            : null;

        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($dedup !== null) {
                $dedup->execute([
                    ':uid' => $uid,
                    ':type' => $type,
                    ':title' => $title,
                    ':minutes' => $dedupMinutes,
                ]);
                if ((int) $dedup->fetchColumn() > 0) {
                    continue;
                }
            }
            $params = [
                ':uid' => $uid,
                ':type' => $type,
                ':title' => $title,
                ':message' => $message,
                ':priority' => $priority,
                ':created_at' => $createdAt,
            ];
            if ($hasContext) {
                $params[':action_url'] = $options['action_url'] ?? null;
                $params[':reference_type'] = $options['reference_type'] ?? null;
                $params[':reference_id'] = isset($options['reference_id']) ? (int) $options['reference_id'] : null;
                $params[':reminder_window'] = $options['reminder_window'] ?? null;
            }
            try {
                $insert->execute($params);
            } catch (\PDOException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                continue;
            }
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Convenience alias for a single push with a shorter call site.
     */
    public static function pushNotification($recipients, string $type, string $title, string $message, string $priority = 'medium', array $options = []): int
    {
        return (new self())->push($recipients, $type, $title, $message, $priority, $options);
    }

    /**
     * Human-readable message builders — keep notification copy consistent.
     */
    public static function messageText(string $senderName, string $snippet = ''): string
    {
        $text = "New message from {$senderName}.";
        if ($snippet !== '') {
            $text .= ' "' . mb_substr($snippet, 0, 120) . '"';
        }
        return $text;
    }

    public static function approvedText(string $requestLabel, string $byName): string
    {
        return "Your {$requestLabel} was approved by {$byName}.";
    }

    public static function deniedText(string $requestLabel, string $byName, string $reason = ''): string
    {
        $text = "Your {$requestLabel} was declined by {$byName}.";
        if ($reason !== '') {
            $text .= ' Reason: ' . mb_substr($reason, 0, 200) . '.';
        }
        return $text;
    }

    public static function publishedText(string $label): string
    {
        return $label . ' is now available.';
    }

    // ========================================================================
    // Recipient resolution
    // ========================================================================

    public function resolveRecipients($recipients): array
    {
        if (is_string($recipients)) {
            if ($recipients === 'all_staff') {
                return $this->allStaffUserIds();
            }
            if ($recipients === 'all_users') {
                return $this->allActiveUserIds();
            }
            if (strpos($recipients, 'role:') === 0) {
                return $this->userIdsForRole(substr($recipients, 5));
            }
            if (strpos($recipients, 'staff:') === 0) {
                return $this->userIdsForStaff([(int) substr($recipients, 6)]);
            }
            return is_numeric($recipients) ? [(int) $recipients] : [];
        }

        if (is_int($recipients)) {
            return [$recipients];
        }

        if (is_array($recipients)) {
            $ids = [];
            foreach ($recipients as $entry) {
                foreach ($this->resolveRecipients($entry) as $uid) {
                    $ids[] = (int) $uid;
                }
            }
            return array_values(array_unique(array_filter($ids)));
        }

        return [];
    }

    /**
     * Active user ids linked to staff member ids (users.person_id = staff.person_id).
     */
    public function userIdsForStaff(array $staffIds): array
    {
        $staffIds = array_values(array_unique(array_map('intval', array_filter($staffIds))));
        if (empty($staffIds)) {
            return [];
        }
        $in = implode(',', array_fill(0, count($staffIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT u.id
               FROM " . ReadReplicaService::qualifiedRef("person_directory") . " 
              WHERE s.id IN ($in) AND u.status = 'active'"
        );
        $stmt->execute($staffIds);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Active user ids holding a role (id or name).
     */
    public function userIdsForRole($roleIdOrName): array
    {
        if (is_numeric($roleIdOrName)) {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT ur.user_id
                   FROM user_roles ur
                   JOIN users u ON u.id = ur.user_id
                  WHERE ur.role_id = ? AND u.status = 'active'"
            );
            $stmt->execute([(int) $roleIdOrName]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT user_id
                   FROM " . ReadReplicaService::masterRef('user_role_grant') . "
                  WHERE role_name = ? AND user_status = 'active'"
            );
            $stmt->execute([(string) $roleIdOrName]);
        }
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Active users linked to a staff record (the "staff audience").
     */
    public function allStaffUserIds(int $excludeUserId = 0): array
    {
        $sql =
            "SELECT DISTINCT u.id
               FROM " . ReadReplicaService::qualifiedRef("person_directory") . " 
              WHERE u.status = 'active'";
        $params = [];
        if ($excludeUserId > 0) {
            $sql .= ' AND u.id <> ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Every active user.
     */
    public function allActiveUserIds(int $excludeUserId = 0): array
    {
        $sql = "SELECT id FROM users WHERE status = 'active'";
        $params = [];
        if ($excludeUserId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeUserId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Display name for a user id (first + last name from persons), or null.
     */
    public function userName(int $userId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' ', first_name, last_name)
             FROM " . ReadReplicaService::qualifiedRef("person_directory") . "
              WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        return $name !== null && $name !== '' ? (string) $name : null;
    }
    /** Unified newest-first feed for the notifications dropdown. */
    public function feedForUser(\PDO $pdo, int $userId): array
    {
        $items = [];
        $messagesUnread = 0;
        $notificationsUnread = 0;
        $eventsCount = 0;

        // 1. Unread internal messages (one item per conversation with unread count).
        $sql = "SELECT c.id, c.title, c.conversation_type, cp.unread_count,
                       (SELECT im.message_body
                          FROM internal_messages im
                         WHERE im.conversation_id = c.id
                         ORDER BY im.created_at DESC, im.id DESC
                         LIMIT 1) AS snippet,
                       (SELECT im.created_at
                          FROM internal_messages im
                         WHERE im.conversation_id = c.id
                         ORDER BY im.created_at DESC, im.id DESC
                         LIMIT 1) AS last_at,
                       (SELECT CONCAT_WS(' ', p.first_name, p.last_name)
                          FROM internal_messages im
                          JOIN users u ON u.id = im.sender_id
                          JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = u.person_id
                         WHERE im.conversation_id = c.id
                         ORDER BY im.created_at DESC, im.id DESC
                         LIMIT 1) AS sender_name
                  FROM conversation_participants cp
                  JOIN internal_conversations c ON c.id = cp.conversation_id
                 WHERE cp.participant_id = :uid
                   AND cp.left_at IS NULL
                   AND cp.unread_count > 0
                 ORDER BY last_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $unread = (int) $row['unread_count'];
            $messagesUnread += $unread;
            $sender = $row['sender_name'] !== null ? $row['sender_name'] : 'a colleague';

            $items[] = [
                'id' => 'message-' . (int) $row['id'],
                'type' => 'message',
                'category' => 'message',
                'title' => 'Message from ' . $sender,
                'message' => $row['snippet'] !== null ? $row['snippet'] : '',
                'priority' => 'medium',
                'created_at' => $row['last_at'],
                'unread' => true,
                'read' => false,
                'badge' => $unread,
                'route' => 'messages',
                'context' => $row['title'] !== null && $row['title'] !== '' ? $row['title'] : '',
                'action_url' => 'home.php?route=communications/messages_inbox&conversation_id=' . (int) $row['id'],
            ];
        }

        // 2. Notifications: unread first, then a handful of recent read items
        //    for context (faded in the UI, never counted as unread).
        $stmt = $pdo->prepare(
            "SELECT id, type, title, message, priority, read_status, created_at,
                    action_url, reference_type, reference_id, reminder_window
               FROM notifications
              WHERE user_id = :uid
              ORDER BY (read_status = 'unread') DESC, created_at DESC
              LIMIT 25"
        );
        $stmt->execute([':uid' => $userId]);

        $readShown = 0;
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $isUnread = $row['read_status'] === 'unread';
            if ($isUnread) {
                $notificationsUnread++;
            } else {
                if ($readShown >= 6) {
                    continue;
                }
                $readShown++;
            }

            $items[] = [
                'id' => 'notification-' . (int) $row['id'],
                'type' => 'notification',
                'category' => $row['type'] !== null && $row['type'] !== ''
                    ? $row['type']
                    : 'notification',
                'title' => $row['title'],
                'message' => $row['message'],
                'priority' => $row['priority'],
                'created_at' => $row['created_at'],
                'unread' => $isUnread,
                'read' => !$isUnread,
                'badge' => $isUnread ? 1 : 0,
                'route' => 'announcements',
                'context' => '',
                'action_url' => $row['action_url'] ?? null,
                'reference_type' => $row['reference_type'] ?? null,
                'reference_id' => isset($row['reference_id']) ? (int) $row['reference_id'] : null,
                'reminder_window' => $row['reminder_window'] ?? null,
            ];
        }

        // Events enter this feed only through scheduled reminder notifications.

        // Newest-first, capped so the dropdown stays readable.
        usort($items, function (array $a, array $b): int {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        });
        $items = array_slice($items, 0, 30);

        return [
            'unread_count' => $messagesUnread + $notificationsUnread,
            'counts' => [
                'messages' => $messagesUnread,
                'notifications' => $notificationsUnread,
                'events' => $eventsCount,
            ],
            'items' => $items,
        ];
    }

    /** Push upcoming-event reminders into the user feed (idempotent by window/type/reference). */
    public function ensureEventReminders(\PDO $pdo, int $userId): void
    {
        $service = $this;
        foreach ([['7_days', 3, 7], ['3_days', 1, 3], ['24_hours', 0, 1]] as [$window, $lower, $upper]) {
            $stmt = $pdo->prepare(
                "SELECT id, title, start_at, location FROM school_events
                  WHERE status IN ('upcoming','ongoing')
                    AND start_at > DATE_ADD(NOW(), INTERVAL {$lower} DAY)
                    AND start_at <= DATE_ADD(NOW(), INTERVAL {$upper} DAY)"
            );
            $stmt->execute();
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $event) {
                $when = date('D, d M Y \\a\\t H:i', strtotime($event['start_at']));
                $title = $window === '24_hours' ? 'Tomorrow: ' . $event['title'] : 'Upcoming: ' . $event['title'];
                $message = "Reminder: {$event['title']} is scheduled for {$when}.";
                if (!empty($event['location'])) $message .= ' Venue: ' . $event['location'] . '.';
                $service->push($userId, 'reminder', $title, $message, 'medium', [
                    'action_url' => 'home.php?route=school_events&event_id=' . (int) $event['id'],
                    'reference_type' => 'school_event',
                    'reference_id' => (int) $event['id'],
                    'reminder_window' => $window,
                ]);
            }
        }
    }

    /**
     * Total actionable unread count (messages + notifications), matching the
     * badge semantics in buildFeed().
     */
    /** Mark one notification read/unread for its owner. Returns whether it changed. */
    public function setReadForUser(\PDO $pdo, int $userId, int $notificationId, bool $read): bool
    {
        $stmt = $pdo->prepare(
            "UPDATE notifications SET read_status = :status WHERE id = :id AND user_id = :uid"
        );
        $stmt->execute([':status' => $read ? 'read' : 'unread', ':id' => $notificationId, ':uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Mark every unread notification and conversation message read for the user. */
    public function markAllReadForUser(\PDO $pdo, int $userId): void
    {
        $pdo->prepare(
            "UPDATE notifications SET read_status = 'read'
              WHERE user_id = :uid AND read_status = 'unread'"
        )->execute([':uid' => $userId]);

        $pdo->prepare(
            "UPDATE internal_messages im
               JOIN conversation_participants cp
                 ON cp.conversation_id = im.conversation_id
                SET im.status = 'read'
              WHERE cp.participant_id = :uid1
                AND cp.left_at IS NULL
                AND im.sender_id <> :uid2"
        )->execute([':uid1' => $userId, ':uid2' => $userId]);

        $pdo->prepare(
            "UPDATE conversation_participants SET unread_count = 0, last_read_at = NOW()
              WHERE participant_id = :uid AND left_at IS NULL"
        )->execute([':uid' => $userId]);
    }

    public function unreadTotal(\PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(unread_count), 0)
               FROM conversation_participants
              WHERE participant_id = :uid AND left_at IS NULL"
        );
        $stmt->execute([':uid' => $userId]);
        $messagesUnread = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
               FROM notifications
              WHERE user_id = :uid AND read_status = 'unread'"
        );
        $stmt->execute([':uid' => $userId]);
        $notificationsUnread = (int) $stmt->fetchColumn();

        return $messagesUnread + $notificationsUnread;
    }

}
