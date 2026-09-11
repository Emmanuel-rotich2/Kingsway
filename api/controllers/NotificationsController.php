<?php

namespace App\API\Controllers;

use App\API\Services\NotificationService;

/**
 * NotificationsController
 *
 * Aggregated, per-user notification feed for the authenticated application
 * header bell. Draws from three live sources:
 *
 *   - internal messages   (conversation_participants.unread_count, enriched
 *                          with the last sender's name)
 *   - notifications table (announcements, approvals, releases and any other
 *                          module push — populated via NotificationService)
 *   - school_events       (upcoming event reminders)
 *
 * Endpoints:
 *   GET  /api/notifications                        -> getNotifications()
 *   POST /api/notifications/mark-all-read          -> postMarkAllRead()
 *   POST /api/notifications/push                   -> postPush() (admin broadcast)
 */
class NotificationsController extends BaseController
{
    /**
     * GET /api/notifications
     *
     * Returns { unread_count, counts: {messages, notifications, events}, items: [...] }
     * Unread items first, then a few recent read notifications for context
     * (flagged read). Newest-first.
     */
    public function getNotifications($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $pdo = $this->db->getConnection();
            $this->ensureEventReminder($pdo, (int) $userId);
            return $this->success(
                $this->buildFeed($pdo, (int)$userId),
                'Notifications retrieved'
            );
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[NotificationsController::getNotifications] ' . $e->getMessage());
            return $this->serverError('Failed to load notifications');
        }
    }

    /** PUT /api/notifications/{id} with {read: true|false}. */
    public function putNotification($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        $notificationId = (int) $id;
        if (!$userId || $notificationId < 1) return $this->unauthorized('Authentication required');
        $read = filter_var($data['read'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($read === null) return $this->badRequest('read must be boolean');
        try {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE notifications SET read_status = :status WHERE id = :id AND user_id = :uid"
            );
            $stmt->execute([':status' => $read ? 'read' : 'unread', ':id' => $notificationId, ':uid' => (int) $userId]);
            return $this->success(['updated' => $stmt->rowCount() > 0], $read ? 'Notification marked as read' : 'Notification marked as unread');
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[NotificationsController::putNotification] ' . $e->getMessage());
            return $this->serverError('Failed to update notification');
        }
    }

    /**
     * POST /api/notifications/mark-all-read
     *
     * Marks the current user's unread notifications read AND clears their
     * unread conversation counters + message statuses, so nothing the user
     * marked as read ever surfaces as unread again.
     */
    public function postMarkAllRead($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $pdo = $this->db->getConnection();

            // 1. notifications table
            $pdo->prepare(
                "UPDATE notifications
                    SET read_status = 'read'
                  WHERE user_id = :uid AND read_status = 'unread'"
            )->execute([':uid' => (int)$userId]);

            // 2. unread conversations (counters + message status), consistent
            //    with InternalMessagingManager::markConversationRead().
            // NOTE: distinct placeholders (:uid1/:uid2) — with native prepared
            // statements (EMULATE_PREPARES=false) a named placeholder cannot
            // be bound to two positions in the same statement.
            $pdo->prepare(
                "UPDATE internal_messages im
                   JOIN conversation_participants cp
                     ON cp.conversation_id = im.conversation_id
                    SET im.status = 'read'
                  WHERE cp.participant_id = :uid1
                    AND cp.left_at IS NULL
                    AND im.sender_id <> :uid2"
            )->execute([':uid1' => (int)$userId, ':uid2' => (int)$userId]);

            $pdo->prepare(
                "UPDATE conversation_participants
                    SET unread_count = 0, last_read_at = NOW()
                  WHERE participant_id = :uid AND left_at IS NULL"
            )->execute([':uid' => (int)$userId]);

            return $this->success(
                ['unread_count' => $this->countUnread($pdo, (int)$userId)],
                'Notifications marked as read'
            );
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[NotificationsController::postMarkAllRead] ' . $e->getMessage());
            return $this->serverError('Failed to mark notifications as read');
        }
    }

    /**
     * POST /api/notifications/push
     *
     * Admin broadcast: push a notification to a chosen audience. System Admin
     * and communications managers only.
     *
     * Payload: { title, message, priority?, type?, audience?, role_id?,
     *            user_ids? }
     * audience: 'all_staff' | 'all_users' | 'role' | 'users' (default all_staff)
     */
    public function postPush($id = null, $data = [], $segments = [])
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->unauthorized('Authentication required');
        }

        if (!$this->canBroadcast()) {
            return $this->forbidden('Insufficient permission to broadcast notifications');
        }

        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        if ($title === '' || $message === '') {
            return $this->badRequest('title and message are required');
        }

        $priority = in_array($data['priority'] ?? '', ['low', 'medium', 'high'], true)
            ? $data['priority']
            : 'medium';
        $type = trim((string) ($data['type'] ?? 'announcement'));
        if ($type === '' || strlen($type) > 50) {
            return $this->badRequest('type must be 1-50 characters');
        }

        $audience = trim((string) ($data['audience'] ?? 'all_staff'));
        switch ($audience) {
            case 'all_staff':
            case 'all_users':
                $recipients = $audience;
                break;
            case 'role':
                $role = $data['role_id'] ?? $data['role'] ?? null;
                if ($role === null || $role === '') {
                    return $this->badRequest('role_id is required when audience is "role"');
                }
                $recipients = 'role:' . (is_numeric($role) ? (int)$role : (string)$role);
                break;
            case 'users':
                $userIds = array_values(array_filter(
                    array_map('intval', (array) ($data['user_ids'] ?? []))
                ));
                if (empty($userIds)) {
                    return $this->badRequest('user_ids are required when audience is "users"');
                }
                $recipients = $userIds;
                break;
            default:
                return $this->badRequest('Invalid audience');
        }

        try {
            $service = $this->contract('App\API\Services\NotificationService', $this->db->getConnection());
            $inserted = $service->push($recipients, $type, $title, $message, $priority);
            return $this->success(
                ['inserted' => $inserted],
                $inserted > 0 ? 'Notification broadcast sent' : 'Notification broadcast sent (0 recipients)'
            );
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError('[NotificationsController::postPush] ' . $e->getMessage());
            return $this->serverError('Failed to broadcast notification');
        }
    }

    /**
     * Build the unified, newest-first notification feed.
     */
    private function buildFeed(\PDO $pdo, int $userId): array
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
                          JOIN persons p ON p.id = u.person_id
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

    private function ensureEventReminder(\PDO $pdo, int $userId): void
    {
        $service = $this->contract('App\API\Services\NotificationService', $pdo);
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
    private function countUnread(\PDO $pdo, int $userId): int
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

    /**
     * Who may broadcast: System Admin, Director, or anyone holding a
     * communications/notifications permission.
     */
    private function canBroadcast(): bool
    {
        return $this->userHasAny(
            [
                'notifications_push',
                'notifications_manage',
                'communications_all_permissions',
                'communications_manage',
                'system_admin',
            ],
            [2],
            ['System Admin', 'Director', 'School Administrator']
        );
    }
}
