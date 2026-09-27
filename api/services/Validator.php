<?php

namespace App\API\Services;

/**
 * Validator - Laravel-style fluent server-side validation, framework-free.
 *
 * Provides two styles:
 *
 *   1. Batch:  Validator::validate($data, ['name' => 'required|string|max:100', 'email' => 'required|email'])
 *              -> fails() / errors() / validated()
 *
 *   2. Chain:  Validator::make($data)->with('email', 'required|email') ... ->run()
 *
 * This mirrors Laravel's validation facade while only depending on native PHP,
 * removing duplicated validation blocks from controllers. The container's
 * result is a plain array of field => message for easy display.
 */
class Validator
{
    /** @var array<string, mixed> Data under validation. */
    private $data;

    /** @var array<string, string> field => rules string. */
    private $rules;

    /** @var array<string, string> field => first error message. */
    private $errors = [];

    /** @var array<string, mixed> Values that passed validation. */
    private $validated = [];

    /** @var string Current field being checked. */
    private $current = '';

    /**
     * Batch validation entry point.
     *
     * @param array $data
     * @param array<string,string|string[]> $rules field => pipeline
     * @return static
     */
    public static function validate(array $data, array $rules): self
    {
        $v = new self();
        $v->data = $data;
        foreach ($rules as $field => $pipeline) {
            $v->rules[$field] = is_array($pipeline) ? implode('|', $pipeline) : $pipeline;
        }
        $v->run();
        return $v;
    }

    /**
     * Chained entry point: Validator::make($data)->with('name','required')...
     *
     * @return static
     */
    public static function make(array $data): self
    {
        $v = new self();
        $v->data = $data;
        return $v;
    }

    /**
     * Add a rule (chain style).
     *
     * @return static
     */
    public function with(string $field, string $rules): self
    {
        $this->rules[$field] = $rules;
        return $this;
    }

    /**
     * Execute all rules for all fields.
     *
     * @return self
     */
    public function run(): self
    {
        $this->errors = [];
        $this->validated = [];
        $this->current = '';

        foreach ($this->rules as $field => $pipeline) {
            $this->current = $field;
            $value = $this->data[$field] ?? null;
            $present = array_key_exists($field, $this->data);

            foreach (explode('|', $pipeline) as $ruleToken) {
                if ($ruleToken === '') {
                    continue;
                }
                list($rule, $param) = $this->parseRule($ruleToken);

                // Skip non-required empties for the remaining (nullable style),
                // except when the rule itself is 'required'.
                if ($rule !== 'required' && !$present && $value === null) {
                    continue;
                }
                if ($value === '' && $this->shouldSkipEmpty($rule)) {
                    continue;
                }

                $this->applyRule($rule, $param, $value, $present);
                if (isset($this->errors[$field])) {
                    break; // first error per field wins
                }
            }

            $fieldValue = $this->data[$field] ?? null;
            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $this->cast($pipeline, $fieldValue);
            }
        }

        $this->current = '';
        return $this;
    }

    /**
     * @return bool Whether any rule failed.
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * @return array<string,string> field => error message.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string,mixed> fields that passed, transformed appropriately.
     */
    public function validated(): array
    {
        return $this->validated;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    // ---- rule plumbing ----

    private function parseRule(string $token): array
    {
        if (strpos($token, ':') !== false) {
            list($rule, $param) = explode(':', $token, 2);
            return [$rule, $param];
        }
        return [$token, null];
    }

    private function shouldSkipEmpty(string $rule): bool
    {
        // When a nullable field is empty, skip everything except required and
        // nullable itself (treated as pass). This mirrors Laravel's implicit
        // nullable conversion for empty strings on optional rules.
        return !in_array($rule, ['required', 'nullable'], true);
    }

    private function applyRule(string $rule, $param, $value, bool $present): void
    {
        $field = $this->current;
        switch ($rule) {
            case 'required':
                if (!$present || $value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->fail($this->friendly($field) . ' is required');
                }
                break;
            case 'nullable':
                break;
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->fail($this->friendly($field) . ' must be a string');
                }
                break;
            case 'integer':
            case 'int':
                if ($value !== null && !$this->isInt($value)) {
                    $this->fail($this->friendly($field) . ' must be an integer');
                }
                break;
            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->fail($this->friendly($field) . ' must be numeric');
                }
                break;
            case 'boolean':
            case 'bool':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', 'true', 'false'], true)) {
                    $this->fail($this->friendly($field) . ' must be a boolean');
                }
                break;
            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->fail($this->friendly($field) . ' must be an array');
                }
                break;
            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $this->fail($this->friendly($field) . ' must be a valid email address');
                }
                break;
            case 'url':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $this->fail($this->friendly($field) . ' must be a valid URL');
                }
                break;
            case 'name':
                if (!is_string($value) || FieldCleaner::cleanName($value) === null) {
                    $this->fail($this->friendly($field) . ' must contain letters only (no digits or symbols)');
                }
                break;
            case 'phone':
                if (!is_string($value) || FieldCleaner::cleanPhone($value) === null) {
                    $this->fail($this->friendly($field) . ' is not a valid Kenyan phone number');
                }
                break;
            case 'national_id':
                if (!is_string($value) || FieldCleaner::cleanNationalId($value) === null) {
                    $this->fail($this->friendly($field) . ' must be a valid national ID or passport number (digits only, 5-12 characters)');
                }
                break;
            case 'address':
                if (!is_string($value) || FieldCleaner::cleanAddress($value) === null) {
                    $this->fail($this->friendly($field) . ' is not a valid address');
                }
                break;
            case 'dob':
                if (!is_string($value) || FieldCleaner::cleanDob($value) === null) {
                    $this->fail($this->friendly($field) . ' must be a date strictly in the past (not today or future)');
                }
                break;
            case 'not_future':
                if (!is_string($value) || FieldCleaner::cleanDate($value) === null
                    || FieldCleaner::cleanDate($value) >= date('Y-m-d')) {
                    $this->fail($this->friendly($field) . ' must not be today or in the future');
                }
                break;
            case 'min':
                $this->failIf(strlen((string) $value) < (int) $param, $this->friendly($field) . " must be at least {$param} characters");
                break;
            case 'max':
                $this->failIf(strlen((string) $value) > (int) $param, $this->friendly($field) . " must not exceed {$param} characters");
                break;
            case 'date':
                if (!is_string($value) || (strtotime($value) === false)) {
                    $this->fail($this->friendly($field) . ' must be a valid date');
                }
                break;
            case 'regex':
                if (!is_string($value) || !preg_match($param, $value)) {
                    $this->fail($this->friendly($field) . ' has an invalid format');
                }
                break;
            case 'in':
                $allowed = array_map('trim', explode(',', (string) $param));
                if (!in_array($value, $allowed, true)) {
                    $this->fail($this->friendly($field) . ' must be one of: ' . implode(', ', $allowed));
                }
                break;
            case 'digits':
                if (!is_string($value) || !ctype_digit($value) || strlen($value) !== (int) $param) {
                    $this->fail($this->friendly($field) . " must be exactly {$param} digits");
                }
                break;
            case 'confirmed':
                $confirm = $this->data[$field . '_confirmation'] ?? $this->data[$field . '_confirm'] ?? null;
                if ($value !== $confirm) {
                    $this->fail($this->friendly($field) . ' confirmation does not match');
                }
                break;
            default:
                // Unknown rule is ignored (never blocks valid data unexpectedly).
                break;
        }
    }

    private function isInt($value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
    }

    /**
     * Cast validated scalar values according to their declared rules.
     */
    private function cast(string $pipeline, $value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $rules = explode('|', $pipeline);
        if (in_array('integer', $rules, true) || in_array('int', $rules, true)) {
            return (int) $value;
        }
        if (in_array('numeric', $rules, true)) {
            return $value + 0;
        }
        if (in_array('boolean', $rules, true) || in_array('bool', $rules, true)) {
            return in_array($value, [true, 'true', 1, '1'], true);
        }
        if (in_array('name', $rules, true)) {
            return FieldCleaner::cleanName($value);
        }
        if (in_array('phone', $rules, true)) {
            return FieldCleaner::cleanPhone($value);
        }
        if (in_array('national_id', $rules, true)) {
            return FieldCleaner::cleanNationalId($value);
        }
        if (in_array('address', $rules, true)) {
            return FieldCleaner::cleanAddress($value);
        }
        if (in_array('dob', $rules, true)) {
            return FieldCleaner::cleanDob($value);
        }
        if (in_array('not_future', $rules, true)) {
            return FieldCleaner::cleanDate($value);
        }
        return $value;
    }

    private function fail(string $message): void
    {
        $this->errors[$this->current] = $message;
    }

    private function failIf(bool $condition, string $message): void
    {
        if ($condition) {
            $this->fail($message);
        }
    }

    private function friendly(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }
}
