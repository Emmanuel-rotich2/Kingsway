<?php

namespace App\API\Includes;

use LogicException;

/**
 * ServiceContractRegistry — the governed service contract catalogue.
 *
 * Roadmap §4.2 "service/manager-level contract layer":
 *
 *   Frontend (js/api.js) -> HTTP Controller -> ServiceContractBroker
 *   (gRPC-style service contract layer) -> REAL Business Logic (Footnote:
 *   AcademicAPI / FinanceAPI / inline service classes) -> Database
 *
 * Controllers MUST obtain governed services through
 * ServiceContractBroker::contract() (wired as the BaseController::contract()
 * accessor) — never through bare "new X(...)". The
 * scripts/contract_coverage.php lint fails loudly in development when a
 * controller bypasses the accessor, so the "MUST apply to all controllers"
 * rule is enforced mechanically, not just documented.
 *
 * The governed inventory is AUTO-DISCOVERED from the Composer PSR-4 source
 * map (App\API\Services\ -> api/services/, App\API\Modules\ -> api/modules/):
 * every concrete, instantiable class in those namespaces is governed. Adding
 * a new service no longer requires editing this file. Semantic contract ids
 * (the remote-facing JSON-RPC / MCP / queue entries with auth + schema
 * descriptors) remain explicitly registered below, because they cannot be
 * inferred from a class name.
 */
class ServiceContractRegistry
{
    /** @var array<string, array>|null contract id -> contract definition */
    private static $registry = null;

    /** @var array<string, array>|null FQCN -> governed-service definition */
    private static $governed = null;

    /**
     * PSR-4 prefixes that hold governed business logic, mapped to their
     * on-disk roots relative to the project root. Kept in sync with
     * composer.json "autoload.psr-4".
     *
     * @var array<string, string>
     */
    private const GOVERNED_SOURCE_ROOTS = [
        'App\\API\\Services\\' => 'api/services',
        'App\\API\\Modules\\'  => 'api/modules',
    ];

    public static function boot(): void
    {
        if (self::$registry !== null) {
            return;
        }
        self::$registry = [];
        self::$governed = [];

        // 1) Auto-discover the governed-service inventory from source.
        self::discoverGoverned();

        // 2) Explicit remote-facing contract catalogue (auth + schema).
        self::registerCanonicalContracts();
    }

    /**
     * Govern a concrete FQCN so the accessor may grant it and the coverage
     * lint accepts it. Public so unit tests may register fixtures.
     */
    public static function govern(string $class, string $group, string $canonicalContract): void
    {
        self::boot();
        self::$governed[$class] = [
            'group' => $group,
            'contract' => $canonicalContract,
        ];
    }

    /**
     * Register a contract definition under the given id.
     *
     * @throws LogicException on duplicate or malformed id / auth / mode.
     */
    public static function register(array $def): void
    {
        self::boot();
        $id = (string) ($def['id'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9._]{1,120}$/', $id)) {
            throw new LogicException("Invalid contract id '{$id}'.");
        }
        if (isset(self::$registry[$id])) {
            throw new LogicException("Contract '{$id}' is already registered.");
        }

        $mode = (string) ($def['mode'] ?? 'sync');
        if (!in_array($mode, ['sync', 'async'], true)) {
            throw new LogicException("Contract '{$id}' has an invalid mode '{$mode}'.");
        }

        $auth = isset($def['auth']) ? (string) $def['auth'] : null;
        if ($auth !== null && !preg_match('/^(role:|permission:).{1,64}$/', $auth)) {
            throw new LogicException("Contract '{$id}' has an invalid auth descriptor.");
        }

        self::$registry[$id] = [
            '_summary' => (string) ($def['summary'] ?? ''),
            '_mode' => $mode,
            '_auth' => $auth,
            '_schema' => isset($def['schema']) && is_array($def['schema']) ? $def['schema'] : [],
            '_result' => isset($def['result']) && is_array($def['result']) ? $def['result'] : null,
            '_service' => (string) ($def['service'] ?? ''),
            '_method' => (string) ($def['method'] ?? ''),
            'handler' => $def['handler'] ?? null,
        ];
    }

    /**
     * Resolve a contract id to its definition, or null when unknown.
     */
    public static function resolve(string $id): ?array
    {
        self::boot();
        return self::$registry[$id] ?? null;
    }

    public static function has(string $id): bool
    {
        return self::resolve($id) !== null;
    }

    /**
     * Governed-service inventory: FQCN -> ['group','contract'].
     */
    public static function governed(): array
    {
        self::boot();
        return self::$governed;
    }

    public static function governedContains(string $fqcn): bool
    {
        self::boot();
        return isset(self::$governed[$fqcn]);
    }

    /**
     * Full governed FQCN list.
     */
    public static function governedClassNames(): array
    {
        return array_keys(self::governed());
    }

    /**
     * Contract catalogue grouped by subsystem, sorted for deterministic tests.
     */
    public static function methodsGrouped(): array
    {
        self::boot();
        $byGroup = [];
        foreach (self::$registry as $id => $def) {
            $group = explode('.', $id, 2)[0];
            $byGroup[$group][] = [
                'id' => $id,
                'summary' => (string) $def['_summary'],
                'mode' => (string) $def['_mode'],
                'auth' => $def['_auth'],
                'service' => (string) $def['_service'],
                'method' => (string) $def['_method'],
                'schema' => RpcSchemaValidator::describe((array) $def['_schema']),
            ];
        }
        ksort($byGroup);
        foreach ($byGroup as &$list) {
            usort($list, static function (array $a, array $b): int {
                return strcmp($a['id'], $b['id']);
            });
        }
        return $byGroup;
    }

    /**
     * Discover every concrete governed class under the PSR-4 source roots and
     * register it with a deterministic group / canonical contract id.
     */
    private static function discoverGoverned(): void
    {
        $root = dirname(__DIR__, 2);

        // Collect [fqcn, group, contract, apiPriority] tuples first so the
        // insertion order can be made deterministic (canonical *API facades
        // first per group, so ServiceContractBroker::aliasMap() stays stable).
        $found = [];
        foreach (self::GOVERNED_SOURCE_ROOTS as $prefix => $relative) {
            $dir = $root . DIRECTORY_SEPARATOR . $relative;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (self::phpFilesIn($dir) as $path) {
                $rel = substr($path, strlen($dir) + 1);
                $rel = substr($rel, 0, -4); // strip ".php"
                $fqcn = $prefix . str_replace(DIRECTORY_SEPARATOR, '\\', $rel);

                $short = substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
                if (!self::declaresConcreteClass($path, $short)) {
                    continue;
                }

                [$group, $contract] = self::deriveMeta($fqcn);
                $found[$fqcn] = [
                    'group' => $group,
                    'contract' => $contract,
                    'priority' => self::isApiFacade($short, $group) ? 1 : 0,
                ];
            }
        }

        uksort($found, static function (string $a, string $b) use ($found): int {
            $ga = $found[$a]['group'];
            $gb = $found[$b]['group'];
            if ($ga !== $gb) {
                return strcmp($ga, $gb);
            }
            if ($found[$a]['priority'] !== $found[$b]['priority']) {
                return $found[$b]['priority'] <=> $found[$a]['priority'];
            }
            return strcmp($a, $b);
        });

        foreach ($found as $fqcn => $meta) {
            self::$governed[$fqcn] = [
                'group' => $meta['group'],
                'contract' => $meta['contract'],
            ];
        }
    }

    /**
     * Derive the logical group and canonical contract id for a governed FQCN.
     *
     * @return array{0: string, 1: string}
     */
    private static function deriveMeta(string $fqcn): array
    {
        $pos = strrpos($fqcn, '\\');
        $short = $pos === false ? $fqcn : substr($fqcn, $pos + 1);
        $lower = strtolower($short);

        if (strpos($fqcn, 'App\\API\\Modules\\') === 0) {
            $rest = substr($fqcn, strlen('App\\API\\Modules\\'));
            $group = strtolower(explode('\\', $rest)[0]);
            return [$group, $group . '.' . $lower];
        }

        if (strpos($fqcn, 'App\\API\\Services\\') === 0) {
            $rest = substr($fqcn, strlen('App\\API\\Services\\'));
            $parts = explode('\\', $rest);
            if (count($parts) > 1) {
                $group = strtolower($parts[0]);
                return [$group, $group . '.' . $lower];
            }
            return ['', $lower];
        }

        return ['', $lower];
    }

    /**
     * A canonical grant façade: the class name matches "<Group>API".
     */
    private static function isApiFacade(string $short, string $group): bool
    {
        if ($group === '') {
            return false;
        }
        return strcasecmp($short, ucfirst($group) . 'API') === 0;
    }

    /**
     * Recursively list PHP files under a directory.
     *
     * @return list<string>
     */
    private static function phpFilesIn(string $dir): array
    {
        $files = [];
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        } catch (\Throwable $e) {
            return $files;
        }
        sort($files);
        return $files;
    }

    /**
     * True when the file declares a concrete (non-abstract) class with the
     * expected short name and is therefore safe to govern/instantiate.
     */
    private static function declaresConcreteClass(string $path, string $expectedShort): bool
    {
        $source = @file_get_contents($path);
        if ($source === false || $source === '') {
            return false;
        }

        $tokens = @token_get_all($source);
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || $token[0] !== T_CLASS) {
                continue;
            }

            // Skip "new class {}" and "Foo::class".
            $prev = self::previousSignificant($tokens, $i);
            if ($prev === T_NEW || $prev === T_DOUBLE_COLON) {
                continue;
            }

            // Abstract declarations are not instantiable.
            if ($prev === T_ABSTRACT) {
                continue;
            }

            // Confirm the declared name matches the PSR-4 filename.
            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];
                if (is_array($next) && $next[0] === T_WHITESPACE) {
                    continue;
                }
                if (is_array($next) && $next[0] === T_STRING) {
                    return $next[1] === $expectedShort;
                }
                break;
            }
        }

        return false;
    }

    /**
     * Token id of the previous significant token, or 0 when none.
     */
    private static function previousSignificant(array $tokens, int $index): int
    {
        for ($i = $index - 1; $i >= 0; $i--) {
            $token = $tokens[$i];
            if (is_array($token)
                && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            return is_array($token) ? $token[0] : 0;
        }
        return 0;
    }

    /**
     * Explicit remote-facing contract catalogue: these carry auth + schema
     * descriptors and cannot be inferred from a class name.
     */
    private static function registerCanonicalContracts(): void
    {
        self::register([
            'id' => 'system.info',
            'summary' => 'Read-only application and environment status (contract-layer mirror of system.info).',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'handler' => static function (array $params, array $ctx): array {
                return [
                    'app' => 'Kingsway Preparatory School',
                    'layer' => 'ServiceContractBroker (gRPC-style contract layer)',
                    'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
                    'database' => 'KingsWayAcademy',
                    'authenticated_user' => (int) ($ctx['user_id'] ?? 0),
                ];
            },
        ]);

        self::register([
            'id' => 'academic.api',
            'summary' => 'Grants the AcademicAPI governed service through the contract layer.',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\academic\\AcademicAPI',
            'method' => 'getTermTransitionContext',
        ]);

        self::register([
            'id' => 'finance.api',
            'summary' => 'Grants the FinanceAPI governed service through the contract layer.',
            'auth' => null,
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\finance\\FinanceAPI',
            'method' => 'getFinancialSummaryReport',
        ]);

        self::register([
            'id' => 'attendance.api',
            'summary' => 'Grants the AttendanceAPI governed service through the contract layer.',
            'auth' => 'permission:attendance.view',
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\attendance\\AttendanceAPI',
            'method' => 'getStaffAttendanceSummary',
        ]);

        self::register([
            'id' => 'system.api',
            'summary' => 'System Administrator: governed SystemAPI grant contract.',
            'auth' => 'role:System Administrator',
            'schema' => [],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\system\\SystemAPI',
            'method' => 'listAlbums',
        ]);

        self::register([
            'id' => 'admission.api.advance_after_payment',
            'summary' => 'Cross-module governed boundary: advance an admission application after a confirmed payment.',
            'auth' => null,
            'schema' => [
                'required' => ['application_id'],
                'additionalProperties' => false,
                'properties' => [
                    'application_id' => ['type' => 'int', 'min' => 1],
                ],
            ],
            'result' => [
                'required' => ['advanced', 'application_id'],
                'properties' => [
                    'advanced' => ['type' => 'bool'],
                    'application_id' => ['type' => 'int'],
                ],
            ],
            'mode' => 'sync',
            'service' => 'App\\API\\Modules\\admission\\StudentAdmissionWorkflow',
            'method' => 'advanceApplicationAfterConfirmedPayment',
        ]);
    }
}
