<?php

namespace App\API\Services;

use App\API\Modules\users\UserPermissionManager;
use PDO;

/**
 * Allowlisted read surface for the Node.js realtime gateway.
 *
 * The gateway never touches MySQL. When one of its offloaded job handlers or
 * view-models needs school data it calls PHP's internal-read endpoint, which:
 *   1. validates the shared worker secret (NodeController),
 *   2. resolves the RECORDED operator's permissions from the authoritative
 *      source (never trusting the permission list the gateway relays), then
 *   3. executes ONE named view from this fixed registry.
 *
 * This allowlist is for background reads that have no browser bearer. It is
 * intentionally separate from interactive Node view specs: those specs call
 * existing PHP API controllers with the request bearer. Adding a background
 * view is a deliberate, reviewed change to self::VIEWS. Each entry declares the
 * permission required to see it (null = authenticated operator context), and
 * the read is performed here so SQL never leaks into the controller, router,
 * or gateway.
 *
 * This mirrors the vetted "recorded-operator re-authorized" model already used
 * by POST /api/dashboard/agent-tool (AiAgentService::executeToolRequest).
 */
final class NodeReadService
{
    /**
     * view name => required permission code (null = authentication only).
     *
     * @var array<string, ?string>
     */
    private const VIEWS = [
        'system/health'        => null,
        'identity/permissions' => null,
        'students/id-cards'    => 'students_view',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function known(string $view): bool
    {
        return array_key_exists($view, self::VIEWS);
    }

    public function requiredPermission(string $view): ?string
    {
        return self::VIEWS[$view] ?? null;
    }

    /**
     * Execute one allowlisted view for an already-authorised operator.
     *
     * @param array<int, string>  $permissions Server-resolved effective permissions.
     * @param array<string,mixed> $input       View-specific bounded input.
     * @return array{data:mixed, meta:array<string,mixed>}
     */
    public function read(string $view, int $operatorId, array $permissions, array $input): array
    {
        return match ($view) {
            'system/health' => [
                'data' => [
                    'server_time'      => gmdate('c'),
                    'operator_id'      => $operatorId,
                    'permission_count' => count($permissions),
                ],
                'meta' => ['view' => $view],
            ],
            'identity/permissions' => [
                'data' => ['permissions' => array_values($permissions)],
                'meta' => ['view' => $view],
            ],
            'students/id-cards' => $this->readStudentsIdCards($operatorId, $input),
            default => throw new \DomainException('Unknown view', 404),
        };
    }

    /**
     * Students ID-cards view.
     *
     * Delegates to the vetted StudentService read methods so all SQL stays in
     * the students module. Row-level scoping is deliberately applied through
     * `full_management` here because the view is only reachable once the
     * authoritative `students_view` permission has passed the NodeController
     * gate; narrower contexts are a future refinement of the view registry.
     *
     * @param array<string,mixed> $input
     * @return array{data:array<string,mixed>, meta:array<string,mixed>}
     */
    private function readStudentsIdCards(int $operatorId, array $input): array
    {
        $filters = is_array($input['filters'] ?? null) ? $input['filters'] : (is_array($input) ? $input : []);
        $user = ['user_id' => $operatorId];

        $service = new \App\API\Modules\students\StudentService($this->db);
        $cards = $service->getIdCards($user, $filters);
        $meta = $service->getIdCardMeta($user);
        $status = $service->getCardStatus($user, $filters);

        $cardRows = is_array($cards['data'] ?? null) ? $cards['data'] : [];

        return [
            'data' => [
                'cards'  => $cardRows,
                'meta'   => is_array($meta['data'] ?? null) ? $meta['data'] : [],
                'status' => is_array($status['data'] ?? null) ? $status['data'] : [],
            ],
            'meta' => [
                'view'       => 'students/id-cards',
                'row_count'  => count($cardRows),
                'pagination' => $cards['pagination'] ?? null,
            ],
        ];
    }

    /**
     * Resolve the authoritative effective permissions for one operator.
     *
     * @return array<int, string>
     */
    public static function resolvePermissions(PDO $db, int $operatorId): array
    {
        $manager = new UserPermissionManager($db);
        $byUser = $manager->effectivePermissionsByUser([$operatorId]);
        return $byUser[$operatorId] ?? [];
    }

    /**
     * Authoritative single-permission gate (fn_user_has_permission), so role,
     * deny and wildcard semantics live in one place.
     */
    public static function permits(PDO $db, int $operatorId, string $permission): bool
    {
        $manager = new UserPermissionManager($db);
        $result = $manager->hasPermission($operatorId, $permission);
        return is_array($result) && !empty($result['has_permission']);
    }

    /**
     * Reauthorize access to a private result after the job has completed.
     * A durable job's original permission is not a permanent grant.
     */
    public static function canReadJobResult(PDO $db, array $job, int $operatorId): bool
    {
        if ($operatorId < 1) {
            return false;
        }

        $jobType = (string) ($job['job_type'] ?? '');
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        if ($jobType === 'students:id-cards:load') {
            $view = 'students/id-cards';
        } elseif (in_array($jobType, ['report:aggregate', 'export:bulk'], true)) {
            $view = trim((string) ($payload['view'] ?? ''));
            if (!str_contains($view, '/')) {
                $entity = trim((string) ($payload['entity'] ?? ''));
                $view = $entity !== '' && $view !== '' ? $entity . '/' . $view : '';
            }
        } else {
            return false;
        }

        $reader = new self($db);
        if (!$reader->known($view)) {
            return false;
        }
        $required = $reader->requiredPermission($view);
        return $required === null || self::permits($db, $operatorId, $required);
    }
}
