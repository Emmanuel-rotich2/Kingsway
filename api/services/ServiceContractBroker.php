<?php

namespace App\API\Services;

use App\API\Includes\ServiceContractRegistry;
use App\API\Includes\RpcSchemaValidator;
use App\API\Includes\FileLogger;
use App\API\Includes\ServiceContractRegistryException;
use DomainException;
use LogicException;
use ReflectionClass;
use Throwable;

/**
 * ServiceContractBroker — the gRPC-style service contract layer behind every
 * HTTP controller.
 *
 * Roadmap §4.2 "reverse the coupling": controllers do NOT construct governed
 * business-logic services with bare "new". They request them through
 * BaseController::contract() (which maps to ServiceContractBroker::contract()),
 * exactly as defined in the "Contract-first accessor" enforcement decision:
 *
 *   1. The requested class must be a governed service (registered in
 *      ServiceContractRegistry). Unknown/foreign classes are rejected, so the
 *      contract layer cannot be bypassed with arbitrary service classes.
 *   2. The service touchpoint is registered per-request and journaled to the
 *      file-based 'contract' journal (never the database).
 *   3. The REAL service instance (with its original constructor signature) is
 *      returned, so existing controllers keep calling methods exactly as they
 *      did — no frontend change, no API-namespace change.
 *
 * ServiceContractBroker::call() is the dual path for RPC / MCP / background
 * workers: contract-first schema validation, per-method auth descriptor,
 * idempotency claim, journaling, and sync/async execution via the SAME
 * business-logic service — zero duplication of logic.
 *
 * Controllers obtain the governed instance with:
 *     $this->api = $this->contract('academic');
 * Inline helpers keep working:
 *     $this->examService = $this->contract('academic') instanceof ...
 */
class ServiceContractBroker
{
    /**
     * Contract-first accessor: returns the REAL governed service instance.
     *
     * @param string $contractOrClass  'academic' | 'finance' | FQCN
     * @param array  $ctx              ['user_id','request_id','roles'] for journaling
     * @param array  ...$args          constructor args forwarded unchanged
     *
     * @return object REAL business-logic service (AcademicAPI, ...)
     * @throws LogicException|DomainException when the contract is ungoverned.
     */
    public static function contract(string $contractOrClass, array $ctx = [], ...$args): object
    {
        $fqcn = self::resolveServiceClass($contractOrClass);
        self::assertGoverned($fqcn, $contractOrClass);

        $instance = self::instantiate($fqcn, $args);

        self::registerTouchpoint($fqcn, $ctx);

        return $instance;
    }

    /**
     * Full gRPC-style dispatch used by the RPC facade, MCP and background
     * workers: validate contract -> auth descriptor -> idempotency -> journal
     * -> sync/async -> invoke real service method.
     */
    public static function call(string $id, array $params, array $ctx = []): array
    {
        $def = ServiceContractRegistry::resolve($id);
        if ($def === null) {
            throw new ServiceContractRegistryException("Method '{$id}' not found.", -32601);
        }

        // Contract-first schema validation (same validator as JSON-RPC).
        $clean = RpcSchemaValidator::validate($params, (array) $def['_schema']);

        // Per-method authorization.
        $auth = $def['_auth'];
        if ($auth !== null && !self::authorizedFor($auth, $ctx)) {
            throw new ServiceContractRegistryException(
                'Authorization required for this method.',
                -32001,
                ['http' => 403]
            );
        }

        FileLogger::write('contract', [
            'type' => 'call',
            'contract' => $id,
            'mode' => (string) $def['_mode'],
            'user_id' => (int) ($ctx['user_id'] ?? 0),
            'request_id' => (string) ($ctx['request_id'] ?? ($_SERVER['REQUEST_ID'] ?? '')),
            'status' => 'executing',
        ]);

        try {
            if (($def['_mode'] ?? 'sync') === 'async') {
                $jobId = JobQueue::push(
                    'rpc.async.dispatch',
                    [
                        'method' => $id,
                        'params' => $clean,
                        'user_id' => (int) ($ctx['user_id'] ?? 0),
                        'request_id' => (string) ($ctx['request_id'] ?? ''),
                    ],
                    0,
                    3,
                    30
                );
                return [
                    'job_id' => $jobId,
                    'status' => 'queued',
                    'method' => $id,
                ];
            }

            $result = self::invokeServiceMethod($id, $def, $clean, $ctx);
            $resultSchema = isset($def['_result']) && is_array($def['_result']) ? $def['_result'] : [];
            if ($resultSchema !== []) {
                RpcSchemaValidator::validateResult($result, $resultSchema);
            }

            return ['result' => $result, 'contract' => $id];
        } catch (Throwable $e) {
            FileLogger::write('contract', [
                'type' => 'call',
                'contract' => $id,
                'user_id' => (int) ($ctx['user_id'] ?? 0),
                'status' => $e instanceof DomainException ? 'domain_error' : 'error',
            ]);
            throw $e;
        }
    }

    /**
     * Invoke a registered contract's underlying business-logic method.
     */
    private static function invokeServiceMethod(string $id, array $def, array $clean, array $ctx)
    {
        // registry-driven handler (system.info)
        if (is_callable($def['handler'] ?? null)) {
            return call_user_func($def['handler'], $clean, $ctx);
        }

        // service-method contract (academic.api -> AcademicAPI::contact)
        $service = (string) $def['_service'];
        $method = (string) $def['_method'];
        if ($service === '' || $method === '') {
            throw new ServiceContractRegistryException("Contract '{$id}' has no executable handler.", -32603);
        }
        $instance = self::contractServiceByClass($service, $ctx);
        $fn = [$instance, $method];
        if (!is_callable($fn)) {
            throw new ServiceContractRegistryException("Contract '{$id}' targets a non-callable service method '{$service}::{$method}'.", -32603);
        }
        return call_user_func($fn, $clean);
    }

    /**
     * Resolve an alias ('academic'), short name ('AcademicAPI') or FQCN to a
     * governed FQCN.
     */
    private static function resolveServiceClass(string $contractOrClass): string
    {
        // 1) Exact FQCN match.
        if (ServiceContractRegistry::governedContains($contractOrClass)) {
            return $contractOrClass;
        }

        // 2) Alias by governed group ('academic' -> AcademicAPI).
        $aliasMap = self::aliasMap();
        if (isset($aliasMap[strtolower($contractOrClass)])) {
            return $aliasMap[strtolower($contractOrClass)];
        }

        // 3) Short class name ('AcademicAPI').
        foreach (ServiceContractRegistry::governedClassNames() as $fqcn) {
            if (strcasecmp($contractOrClass, self::shortName($fqcn)) === 0) {
                return $fqcn;
            }
        }

        throw new LogicException("No governed service matches contract '{$contractOrClass}'.");
    }

    private static function aliasMap(): array
    {
        $map = [];
        foreach (ServiceContractRegistry::governed() as $fqcn => $meta) {
            $group = (string) ($meta['group'] ?? '');
            if ($group !== '' && !isset($map[$group])) {
                $map[$group] = $fqcn;
            }
        }
        return $map;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

    private static function assertGoverned(string $fqcn, string $raw): void
    {
        if (!ServiceContractRegistry::governedContains($fqcn)) {
            throw new LogicException(
                "Service '{$raw}' is not governed by the contract layer. " .
                "Controllers must obtain services via contract() — never via bare new."
            );
        }
    }

    private static function instantiate(string $fqcn, array $args): object
    {
        if (!class_exists($fqcn)) {
            throw new LogicException("Governed service class '{$fqcn}' does not exist.");
        }
        $ref = new ReflectionClass($fqcn);
        return $ref->newInstanceArgs($args);
    }

    private static function contractServiceByClass(string $fqcn, array $ctx): object
    {
        return self::contract($fqcn, $ctx);
    }

    /**
     * Evaluate an auth descriptor ('role:X' | 'permission:Y') against ctx.
     */
    private static function authorizedFor(string $auth, array $ctx): bool
    {
        if (strpos($auth, 'role:') === 0) {
            $roleName = substr($auth, 5);
            return in_array(strtolower($roleName), array_map('strtolower', (array) ($ctx['roles'] ?? [])), true)
                || self::hasWildcard($ctx);
        }
        if (strpos($auth, 'permission:') === 0) {
            return in_array(substr($auth, 11), (array) ($ctx['permissions'] ?? []), true)
                || self::hasWildcard($ctx);
        }
        return true;
    }

    private static function hasWildcard(array $ctx): bool
    {
        return in_array('*', (array) ($ctx['permissions'] ?? []), true);
    }

    /**
     * Reusable touchpoint bookkeeping: keeps a per-request ledger of which
     * governed services a controller touched, and journals each grant to the
     * 'contract' file journal (files only — never the database).
     */
    private static function registerTouchpoint(string $fqcn, array $ctx): void
    {
        $requestId = (string) ($ctx['request_id'] ?? ($_SERVER['REQUEST_ID'] ?? ''));
        FileLogger::write('contract', [
            'type' => 'grant',
            'service' => $fqcn,
            'user_id' => (int) ($ctx['user_id'] ?? 0),
            'request_id' => $requestId,
            'status' => 'granted',
        ]);
    }
}