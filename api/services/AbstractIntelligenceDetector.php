<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use App\Database\Database;
use PDO;

/**
 * Shared plumbing for deterministic intelligence detectors.
 *
 * Detectors never talk to a provider. They get their data through the
 * ReadReplicaService view catalogue (falling back to the identical master view
 * when no replica is present) or through the master schema for allowlisted
 * tables. Both the PDO and the projection->reference resolver are injectable
 * so hermetic unit tests need no live database.
 */
abstract class AbstractIntelligenceDetector implements IntelligenceDetector
{
    /** @var PDO|null */
    private $connection;

    /** @var callable(string):string */
    private $refResolver;

    /**
     * @param PDO|null $connection injected for tests; defaults to the app DB
     * @param callable(string):string|null $refResolver projection->view ref
     */
    public function __construct(?PDO $connection = null, ?callable $refResolver = null)
    {
        $this->connection = $connection;
        $this->refResolver = $refResolver ?? static fn (string $projection): string =>
            ReadReplicaService::qualifiedRef($projection);
    }

    protected function pdo(): PDO
    {
        return $this->connection ?: Database::getInstance()->getConnection();
    }

    /** Schema-qualified replica/master view reference for a projection. */
    protected function view(string $projection): string
    {
        return ($this->refResolver)($projection);
    }

    /** Schema-qualified master view reference for views outside the replica catalogue. */
    protected function masterView(string $viewName): string
    {
        return ConnectionManager::schemaFor(ConnectionManager::NS_MASTER) . '.' . $viewName;
    }

    /** Schema-qualified master table reference for allowlisted tables. */
    protected function table(string $table): string
    {
        return ConnectionManager::schemaFor(ConnectionManager::NS_MASTER) . '.' . $table;
    }

    /** Build the {domain, as_of, metrics, alerts} contract shape. */
    protected function result(string $domain, array $metrics, array $alerts = []): array
    {
        return [
            'domain' => $domain,
            'as_of' => gmdate('Y-m-d'),
            'metrics' => $metrics,
            'alerts' => $alerts,
        ];
    }
}