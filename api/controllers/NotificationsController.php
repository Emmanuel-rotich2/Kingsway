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
            $service = $this->contract('App\API\Services\NotificationService', $pdo);
            $service->ensureEventReminders($pdo, (int) $userId);
            return $this->success(
                $service->feedForUser($pdo, (int)$userId),
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
            $service = $this->contract('App\API\Services\NotificationService', $this->db->getConnection());
            $updated = $service->setReadForUser($this->db->getConnection(), (int) $userId, $notificationId, $read);
            return $this->success(['updated' => $updated], $read ? 'Notification marked as read' : 'Notification marked as unread');
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

            $service = $this->contract('App\API\Services\NotificationService', $pdo);
            $service->markAllReadForUser($pdo, (int) $userId);

            return $this->success(
                ['unread_count' => $service->unreadTotal($pdo, (int)$userId)],
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
