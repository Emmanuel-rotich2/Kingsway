<?php

namespace App\API\Includes;

use RuntimeException;

/**
 * ServiceContractRegistryException — contract-layer domain error.
 *
 * Carries an optional JSON-RPC-style error code (negative) and HTTP status,
 * so the RPC facade / HTTP controllers can map contract failures without
 * leaking internals.
 */
class ServiceContractRegistryException extends RuntimeException
{
    /** @var array<string,mixed> */
    private $meta = [];

    /**
     * @param array $meta e.g. ['http' => 403]
     */
    public function __construct(string $message = '', int $code = 0, array $meta = [])
    {
        parent::__construct($message, $code);
        $this->meta = $meta;
    }

    public function httpStatus(): int
    {
        return (int) ($this->meta['http'] ?? 400);
    }
}