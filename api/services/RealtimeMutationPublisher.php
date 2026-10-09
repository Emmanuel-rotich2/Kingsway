<?php

namespace App\API\Services;

use App\Database\Database;

/**
 * Publishes privacy-safe cache invalidations after successful API mutations.
 * It deliberately never copies controller response data into static buffers.
 */
class RealtimeMutationPublisher
{
    /** @var string[] */
    private const MUTATION_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** @var string[] Endpoints with their own internal/credential semantics. */
    private const EXCLUDED_CONTROLLERS = ['auth', 'twofactor', 'realtime'];

    /** @var array<string,string[]> */
    private const DOMAIN_TARGETS = [
        'academic' => ['academic', 'assessments', 'dashboard_headteacher', 'dashboard_academic'],
        'admission' => ['admissions', 'students', 'dashboard_school_admin'],
        'attendance' => ['attendance', 'dashboard_headteacher', 'dashboard_academic'],
        'boarding' => ['boarding', 'attendance', 'dashboard_boarding'],
        'catering' => ['catering', 'inventory', 'dashboard_catering'],
        'communications' => ['communications', 'notifications'],
        'finance' => ['finance', 'payments', 'dashboard_finance'],
        'inventory' => ['inventory', 'dashboard_inventory'],
        'parent-portal' => ['parent_portal'],
        'reports' => ['reports'],
        'staff' => ['staff', 'dashboard_staff'],
        'students' => ['students', 'attendance', 'dashboard_school_admin'],
        'system' => ['system', 'dashboard_system_admin'],
        'transport' => ['transport', 'dashboard_transport'],
    ];

    public static function publish(string $httpMethod, string $controller, ?string $resource, array $result): void
    {
        $httpMethod = strtoupper($httpMethod);
        $controller = strtolower(trim($controller));
        if (!in_array($httpMethod, self::MUTATION_METHODS, true)
            || in_array($controller, self::EXCLUDED_CONTROLLERS, true)
            || !self::wasSuccessful($result)) {
            return;
        }

        $domain = self::domainFor($controller);
        $targets = array_values(array_unique(array_merge(
            [$domain],
            self::DOMAIN_TARGETS[$domain] ?? []
        )));
        $eventName = self::eventName($resource);

        try {
            EventBroadcaster::dispatch(
                Database::getInstance()->getConnection(),
                $domain,
                $eventName,
                [
                    'method' => $httpMethod,
                    'targets' => array_values(array_unique($targets)),
                ],
                [EventBroadcaster::DEFAULT_SCOPE]
            );
            // Node realtime gateway bridge (scaling masterplan): when the
            // gateway address is configured, mirror the mutation to connected
            // browsers over SSE. RealtimeGatewayPublisher is fire-and-forget,
            // dormant until configured, and swallows failures — a realtime
            // outage never turns a successful school operation into an HTTP
            // failure. Payload keys are the gateway's allowlisted change
            // descriptors; never record bodies. Scope 'all' reaches staff
            // 'all'-channel holders; parents (family channels) never inherit it.
            RealtimeGatewayPublisher::publish(
                'DATA_CHANGED',
                'all',
                [
                    'domain' => $domain,
                    'action' => $eventName,
                    'targets' => array_values(array_unique($targets)),
                    'method' => $httpMethod,
                ]
            );
        } catch (\Throwable $e) {
            // Realtime is an acceleration layer. It must never roll back or
            // turn a successful school operation into an HTTP failure.
            \App\API\Services\Logger::legacyError('Realtime mutation publish failed: ' . $e->getMessage());
        }
    }

    private static function wasSuccessful(array $result): bool
    {
        if (array_key_exists('success', $result)) {
            return $result['success'] === true;
        }
        if (isset($result['status'])) {
            return strtolower((string) $result['status']) === 'success';
        }
        $code = isset($result['code']) ? (int) $result['code'] : 200;
        return $code >= 200 && $code < 400;
    }

    private static function domainFor(string $controller): string
    {
        $aliases = [
            'payments' => 'finance',
            'student' => 'students',
            'admissions' => 'admission',
            'communication' => 'communications',
            'users' => 'system',
        ];
        return $aliases[$controller] ?? preg_replace('/[^a-z0-9_-]/', '', $controller);
    }

    private static function eventName(?string $resource): string
    {
        $resource = strtolower(trim((string) $resource));
        $resource = preg_replace('/[^a-z0-9_-]/', '', $resource);
        return ($resource !== '' ? $resource : 'resource') . '_changed';
    }
}
