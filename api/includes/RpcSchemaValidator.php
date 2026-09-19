<?php

namespace App\API\Includes;

use DomainException;

/**
 * RpcSchemaValidator — contract-first validation for the JSON-RPC facade.
 *
 * Mirrors the "contract-first safety similar to protobuf" goal of roadmap §4.2:
 * every registered RPC method declares a request schema, and this validator
 * coerces, bounds, and rejects client parameters BEFORE the method handler runs.
 * Unknown parameters are rejected so a misspelled field can never silently
 * change semantics.
 *
 * Supported property rules:
 *   type          string|int|float|bool|array (default: string for scalars)
 *   enum          list of allowed literal values
 *   default       applied when the key is absent (nullable=false)
 *   nullable      allow null to pass through unchanged
 *   minLength/maxLength   string length bounds
 *   min/max       numeric bounds (also applied to array count)
 *   pattern       PCRE regex (must be delimited, e.g. /^[0-9]+$/)
 *   required      list of top-level keys that must be present
 */
class RpcSchemaValidator
{
    private const TYPES = ['string', 'int', 'float', 'bool', 'array', 'integer', 'number', 'boolean', 'object'];

    /**
     * Coerce + validate an associative parameter map against a request schema.
     *
     * @param array $params Raw client parameters (JSON object decoded).
     * @param array $schema ['required' => string[], 'properties' => array<string,array>]
     * @return array Normalized parameter map (declared keys only; defaults applied).
     * @throws DomainException on contract violation (becomes JSON-RPC -32602).
     */
    public static function validate(array $params, array $schema): array
    {
        $props = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
        $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
        $allowExtra = (bool) ($schema['additionalProperties'] ?? false);

        foreach ($required as $key) {
            if (!array_key_exists((string) $key, $params)) {
                throw new DomainException("Missing required parameter '{$key}'.");
            }
        }

        $clean = [];
        foreach ($props as $key => $rule) {
            $key = (string) $key;
            $rule = is_array($rule) ? $rule : [];

            if (!array_key_exists($key, $params)) {
                if (array_key_exists('default', $rule)) {
                    $clean[$key] = $rule['default'];
                }
                continue;
            }

            $clean[$key] = self::coerce($key, $params[$key], $rule);
        }

        if (!$allowExtra) {
            foreach (array_keys($params) as $key) {
                if (!array_key_exists($key, $clean) && $props !== []) {
                    throw new DomainException("Unknown parameter '{$key}' for this method.");
                }
            }
        }

        return $clean;
    }

    /**
     * Validate a handler-produced result against an optional result schema.
     *
     * @param mixed $result Handler return value.
     * @param array $schema ['type' => string, 'structure' => array<string,string>] (optional).
     * @throws DomainException when the shape violates the declared contract.
     */
    public static function validateResult($result, array $schema = []): void
    {
        if ($schema === []) {
            return;
        }
        $type = isset($schema['type']) ? strtolower((string) $schema['type']) : 'array';
        if ($type === 'integer') {
            $type = 'int';
        }
        $matches = ($type === 'float') ? is_float($result) || is_int($result)
            : gettype($result) === $type;
        if (!$matches) {
            throw new DomainException('Handler produced an unexpected result type.');
        }
        if ((isset($schema['required']) || isset($schema['structure']) || isset($schema['properties'])) && is_array($result)) {
            $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
            if (isset($schema['structure']) && is_array($schema['structure'])) {
                $required = array_values(array_unique(array_merge($required, array_keys($schema['structure']))));
            }
            foreach ($required as $key) {
                if (!array_key_exists($key, $result)) {
                    throw new DomainException("Handler result is missing required field '{$key}'.");
                }
            }
            $properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
            foreach ($properties as $key => $rule) {
                if (!array_key_exists($key, $result)) {
                    continue;
                }
                $expected = is_array($rule) ? strtolower((string) ($rule['type'] ?? 'array')) : strtolower((string) $rule);
                if ($expected === 'integer') {
                    $expected = 'int';
                }
                if ($expected === 'number') {
                    $expected = 'float';
                }
                $value = $result[$key];
                if ($expected === 'float') {
                    $typeOk = is_float($value) || is_int($value);
                } else {
                    $actual = gettype($value);
                    $actual = str_replace(['boolean', 'integer', 'double'], ['bool', 'int', 'float'], $actual);
                    $typeOk = ($expected === 'object')
                        ? is_object($value)
                        : $actual === $expected;
                }
                if (!$typeOk) {
                    throw new DomainException("Handler result field '{$key}' must be of type '{$expected}'.");
                }
            }
        }
    }

    /**
     * Build a JSON-Schema-flavoured description for an RPC discovery payload.
     */
    public static function describe(array $schema): array
    {
        return [
            'type' => 'object',
            'required' => isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [],
            'properties' => isset($schema['properties']) && is_array($schema['properties'])
                ? array_map(static function (array $rule): array {
                    $out = [];
                    foreach (['type', 'enum', 'minLength', 'maxLength', 'min', 'max', 'pattern', 'default', 'nullable'] as $field) {
                        if (array_key_exists($field, $rule)) {
                            $out[$field] = $rule[$field];
                        }
                    }
                    return $out;
                }, $schema['properties'])
                : (object) [],
            'additionalProperties' => (bool) ($schema['additionalProperties'] ?? false),
        ];
    }

    private static function coerce(string $key, $value, array $rule)
    {
        // Nullable: null passes through untouched.
        if ($value === null) {
            if (($rule['nullable'] ?? false) === true) {
                return null;
            }
            throw new DomainException("Parameter '{$key}' must not be null.");
        }

        $type = isset($rule['type']) ? strtolower((string) $rule['type']) : 'string';
        if ($type === 'integer') {
            $type = 'int';
        } elseif ($type === 'number') {
            $type = 'float';
        } elseif ($type === 'boolean') {
            $type = 'bool';
        } elseif ($type === 'object') {
            $type = 'array';
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new DomainException("Invalid schema type '{$type}' for parameter '{$key}'.");
        }

        switch ($type) {
            case 'int':
                if (is_int($value)) {
                    $value = $value;
                } elseif (is_numeric($value) && (float) $value == (int) $value) {
                    $value = (int) $value;
                } else {
                    throw new DomainException("Parameter '{$key}' must be an integer.");
                }
                break;
            case 'float':
                if (!is_numeric($value)) {
                    throw new DomainException("Parameter '{$key}' must be a number.");
                }
                $value = (float) $value;
                break;
            case 'bool':
                if (is_bool($value)) {
                    $value = $value;
                } elseif ($value === 'true' || $value === '1' || $value === 1) {
                    $value = true;
                } elseif ($value === 'false' || $value === '0' || $value === 0) {
                    $value = false;
                } else {
                    throw new DomainException("Parameter '{$key}' must be a boolean.");
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    throw new DomainException("Parameter '{$key}' must be an array.");
                }
                break;
            default:
                if (!is_scalar($value)) {
                    throw new DomainException("Parameter '{$key}' must be a string.");
                }
                $value = (string) $value;
        }

        if (isset($rule['enum']) && is_array($rule['enum']) && !in_array($value, $rule['enum'], true)) {
            throw new DomainException("Parameter '{$key}' has an invalid value.");
        }
        if (is_string($value)) {
            if (isset($rule['minLength']) && mb_strlen($value) < (int) $rule['minLength']) {
                throw new DomainException("Parameter '{$key}' is too short.");
            }
            if (isset($rule['maxLength']) && mb_strlen($value) > (int) $rule['maxLength']) {
                throw new DomainException("Parameter '{$key}' is too long.");
            }
            if (isset($rule['pattern']) && !preg_match((string) $rule['pattern'], $value)) {
                throw new DomainException("Parameter '{$key}' does not match the required pattern.");
            }
        }
        if (is_numeric($value) || is_array($value)) {
            if (isset($rule['min']) && $value < $rule['min']) {
                throw new DomainException("Parameter '{$key}' is below the minimum allowed value.");
            }
            if (isset($rule['max']) && $value > $rule['max']) {
                throw new DomainException("Parameter '{$key}' is above the maximum allowed value.");
            }
        }

        return $value;
    }
}