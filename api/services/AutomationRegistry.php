<?php

declare(strict_types=1);

namespace App\API\Services;

/**
 * Allowlisted catalogue of non-AI automations the PHP edge may hand to the
 * Python engine.
 *
 * This is the general-purpose half of the PHP + Python split: PHP keeps every
 * session, permission, row-level scope, CSRF and financial mutation, and
 * Python provides the raw compute that PHP is slow at — file generation, batch
 * transformation, aggregation, reconciliation formatting, import assembly. An
 * automation is defined ONLY here (versioned in source like AiAgentRegistry):
 * adding one is a registry row plus tests, never a controller change.
 *
 * Security model, identical to the AI bridge:
 *   - the caller's permissions are captured when a job is enqueued and
 *     re-authorized here before the engine is ever contacted;
 *   - the job carries the ALREADY-authorized, bounded input (never SQL, never
 *     raw rows beyond the caller's scope, never credentials);
 *   - the Python side is an allowlisted registry with its own checks and no
 *     database credentials;
 *   - results are artifacts (never official school records) and are stored
 *     through PHP governance with a checksum and a retention marker.
 */
final class AutomationRegistry
{
    /**
     * CSV/JSON artifact generated from a bounded, caller-authorized dataset.
     * The UI already holds these rows; the engine only formats them faster
     * and more safely than PHP string building, then hands the bytes back so
     * PHP can store, checksum, journal, and schedule delivery.
     */
    public const ARTIFACT = 'exports.governed_artifact';
    public const EXAM_DOCUMENT_PREVIEW = 'academics.exam_document_preview';

    /** Upper bounds that keep one automation from being a resource escape. */
    public const MAX_ROWS = 5000;
    public const MAX_COLUMNS = 40;
    public const MAX_RESULT_BYTES = 4 * 1024 * 1024;

    /** @return list<array<string,mixed>> */
    public static function catalogue(): array
    {
        return [
            [
                'id' => self::ARTIFACT,
                'name' => 'Governed artifact export',
                'purpose' => 'Format a caller-authorized dataset into CSV or JSON, with checksum',
                'action_level' => 'execute',
                'permission' => 'analytics_catalogue_view',
                'engines' => ['python'],
                'formats' => ['csv', 'json'],
            ],
            [
                'id' => self::EXAM_DOCUMENT_PREVIEW,
                'name' => 'Summative exam document preview',
                'purpose' => 'Extract a local timetable or result document into a staff-reviewed preview',
                'action_level' => 'prepare',
                'permission' => 'academic_manage',
                'engines' => ['python'],
                'formats' => ['pdf', 'csv', 'xlsx', 'ods'],
            ],
        ];
    }

    /** @return array<string,mixed>|null */
    public static function resolve(string $id): ?array
    {
        foreach (self::catalogue() as $automation) {
            if (($automation['id'] ?? '') === $id) {
                return $automation;
            }
        }

        return null;
    }

    public static function isPermitted(array $effectivePermissions, string $id): bool
    {
        $automation = self::resolve($id);
        if ($automation === null) {
            return false;
        }
        $required = (string) ($automation['permission'] ?? '');
        if ($required === '') {
            return true;
        }

        return in_array($required, $effectivePermissions, true)
            || in_array('*', $effectivePermissions, true);
    }
}
