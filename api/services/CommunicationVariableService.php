<?php

namespace App\API\Services;

/**
 * Renders @variable placeholders in message bodies per recipient at dispatch
 * time. Supports contact-level tokens (@first_name, @last_name, @full_name,
 * @email, @phone) plus school-wide tokens (@school_name, @today, @year).
 *
 * Unknown tokens are left untouched so operators can see what did not resolve.
 */
class CommunicationVariableService
{
    private const TOKEN_PATTERN = '/@([a-z][a-z0-9_]*)\b/i';

    /**
     * @param array<string,string|null> $context Keyed by lowercase variable name.
     */
    public function render(string $body, array $context): string
    {
        if ($body === '' || strpos($body, '@') === false) {
            return $body;
        }

        $normalized = [];
        foreach (['school_name', 'today', 'year', 'first_name', 'last_name', 'full_name', 'recipient_name', 'email', 'phone', 'learner_name', 'learner_names', 'child_names', 'learner_count'] as $key) {
            if (isset($context[$key]) && $context[$key] !== '') {
                $normalized[$key] = (string) $context[$key];
            }
        }

        return preg_replace_callback(self::TOKEN_PATTERN, static function (array $m) use ($normalized): string {
            $key = strtolower($m[1]);
            return array_key_exists($key, $normalized) ? $normalized[$key] : $m[0];
        }, $body) ?? $body;
    }

    /**
     * Tokens advertised to the composer UI for insertion.
     *
     * @return array<int,array{token:string,label:string}>
     */
    public static function availableTokens(): array
    {
        return [
            ['token' => '@first_name', 'label' => 'Recipient first name'],
            ['token' => '@last_name', 'label' => 'Recipient last name'],
            ['token' => '@full_name', 'label' => 'Recipient full name'],
            ['token' => '@recipient_name', 'label' => 'Recipient name'],
            ['token' => '@email', 'label' => 'Recipient email'],
            ['token' => '@phone', 'label' => 'Recipient phone'],
            ['token' => '@learner_names', 'label' => 'Names of this parent’s linked learners'],
            ['token' => '@child_names', 'label' => 'Names of this parent’s linked learners'],
            ['token' => '@learner_name', 'label' => 'Learner name (only when exactly one is linked)'],
            ['token' => '@learner_count', 'label' => 'Number of this parent’s linked learners'],
            ['token' => '@school_name', 'label' => 'School name'],
            ['token' => '@today', 'label' => "Today's date"],
            ['token' => '@year', 'label' => 'Current year'],
        ];
    }
}
