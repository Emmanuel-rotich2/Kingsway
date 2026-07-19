<?php

namespace App\API\Controllers;

/**
 * Push Controller
 *
 * Receives Web Push subscriptions posted by PushNotificationManager
 * (js/core/push_notification_manager.js). The browser calls:
 *   POST /api/push/subscribe    -> postSubscribe()
 *   POST /api/push/unsubscribe  -> postUnsubscribe()
 *
 * There is no push_subscriptions table yet, so subscriptions are persisted to a
 * JSON store on disk (one file per fingerprint). A writable-dir fallback chain
 * keeps the endpoint from failing loud when the web-server user can't write to
 * the app dir. This endpoint only ever acks success — push failures must never
 * surface to the client or trigger retry storms.
 *
 * NOTE: VAPID keys are not yet configured server-side (the client getPublicKey()
 * is a placeholder), so this stores subscriptions for later use but actual
 * outbound push dispatch is not wired up here.
 */
class PushController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * POST /api/push/subscribe
     * Body: { subscription: {...PushSubscription...}, device_fingerprint?: string }
     */
    public function postSubscribe($id = null, $data = [])
    {
        $subscription = $data['subscription'] ?? null;
        $fingerprint = $data['device_fingerprint'] ?? null;

        // We need at least a real endpoint to record the subscription.
        $endpoint = is_array($subscription) ? ($subscription['endpoint'] ?? null) : null;
        if (!$endpoint) {
            return $this->badRequest('Missing push subscription endpoint');
        }

        $record = [
            'user_id'           => $this->getUserId(),
            'device_fingerprint' => $fingerprint,
            'subscription'      => $subscription,
            'subscribed_at'     => date('c'),
        ];

        $this->appendSubscription($record);

        return $this->success(['subscribed' => true], 'Subscription saved');
    }

    /**
     * POST /api/push/unsubscribe
     * Body: { device_fingerprint?: string }  (or subscription.endpoint)
     */
    public function postUnsubscribe($id = null, $data = [])
    {
        $fingerprint = $data['device_fingerprint'] ?? null;
        $endpoint = is_array($data['subscription'] ?? null)
            ? ($data['subscription']['endpoint'] ?? null)
            : null;

        if (!$fingerprint && !$endpoint) {
            return $this->badRequest('Missing device fingerprint or endpoint');
        }

        $removed = $this->removeSubscription($fingerprint, $endpoint);

        return $this->success(['removed' => $removed], 'Unsubscribed');
    }

    // ------------------------------------------------------------------------
    // File-backed store helpers
    // ------------------------------------------------------------------------

    private function storeFile(): string
    {
        return $this->resolveStoreDir() . '/push_subscriptions.json';
    }

    private function resolveStoreDir(): string
    {
        $candidates = [
            dirname(__DIR__, 2) . '/storage/push',
            sys_get_temp_dir() . '/kingsway_push',
            sys_get_temp_dir(),
        ];
        foreach ($candidates as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }
        return sys_get_temp_dir();
    }

    private function loadAll(): array
    {
        $file = $this->storeFile();
        if (!file_exists($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function appendSubscription(array $record): void
    {
        try {
            $all = $this->loadAll();
            // De-dupe by endpoint.
            $endpoint = $record['subscription']['endpoint'] ?? null;
            $all = array_filter($all, function ($r) use ($endpoint) {
                return ($r['subscription']['endpoint'] ?? null) !== $endpoint;
            });
            $all[] = $record;
            @file_put_contents($this->storeFile(), json_encode(array_values($all), JSON_UNESCAPED_SLASHES), LOCK_EX);
        } catch (\Throwable $e) {
            error_log('PushController appendSubscription failed: ' . $e->getMessage());
        }
    }

    private function removeSubscription(?string $fingerprint, ?string $endpoint): int
    {
        try {
            $all = $this->loadAll();
            $removed = 0;
            $kept = [];
            foreach ($all as $r) {
                $match = ($fingerprint && ($r['device_fingerprint'] ?? null) === $fingerprint)
                    || ($endpoint && ($r['subscription']['endpoint'] ?? null) === $endpoint);
                if ($match) {
                    $removed++;
                } else {
                    $kept[] = $r;
                }
            }
            if ($removed > 0) {
                @file_put_contents($this->storeFile(), json_encode($kept, JSON_UNESCAPED_SLASHES), LOCK_EX);
            }
            return $removed;
        } catch (\Throwable $e) {
            error_log('PushController removeSubscription failed: ' . $e->getMessage());
            return 0;
        }
    }
}
