<?php

namespace App\API\Services;

use App\API\Services\PhoneNumberNormalizer;

/**
 * FieldCleaner — shared, deterministic data-cleaning layer.
 *
 * The single source of truth for normalizing user-submitted strings BEFORE
 * storage. Every field the system stores should pass through here so that what
 * lands in the database is consistent and analytics-ready, following the
 * pipeline: collection -> cleaning -> storage -> wrangling -> analysis.
 *
 * Rules applied here mirror the school's clean-data policy:
 *
 *   - names  : trim, collapse internal whitespace, strip digits/symbols,
 *              then proper title-case each word. Letters, spaces, hyphens and
 *              apostrophes only.
 *   - emails : trim + lowercase (case-insensitive dedupe safety).
 *   - dates  : any parseable user date folded to canonical Y-m-d.
 *   - dob    : canonical Y-m-d that is STRICTLY in the past (rejects today
 *              and the future — a date of birth can never be today).
 *   - phones : delegated to the shared PhoneNumberNormalizer (canonical
 *              2547XXXXXXXX).
 *
 * Batch entry point: clean() returns [cleaned => [...], errors => [..]] so
 * controllers get normalized values AND per-field failures in one pass.
 */
final class FieldCleaner
{
    private const NAME_CHARS_PATTERN = "/^[a-zA-Z'’\s\-]+$/";
    private const NAME_HAS_LETTER = '/[a-zA-Z]/';
    private const ADDRESS_CHARS_PATTERN = "/^[a-zA-Z0-9'’&@\/.,#()\s\-]+$/";

    /**
     * Clean a name to the canonical title-cased form.
     *
     * Returns null when the input is empty or contains characters that are
     * not letters, spaces, hyphens or apostrophes (e.g. digits or symbols),
     * because a name must never be stored with numbers in it.
     *
     * @param string|null $value
     * @param bool $titleCase when false, words keep their user casing
     * @return string|null
     */
    public static function cleanName(?string $value, bool $titleCase = true): ?string
    {
        if ($value === null) {
            return null;
        }
        $cleaned = trim(self::collapseWhitespace((string) $value));
        if ($cleaned === '') {
            return null;
        }
        if (preg_match(self::NAME_CHARS_PATTERN, $cleaned) !== 1) {
            return null;
        }
        if (preg_match(self::NAME_HAS_LETTER, $cleaned) !== 1) {
            return null;
        }
        return $titleCase ? self::titleCaseWords($cleaned) : $cleaned;
    }

    /**
     * Canonical email: trim + lowercase (never null content, only null input).
     *
     * @param string|null $value
     * @return string|null
     */
    public static function cleanEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $cleaned = strtolower(trim((string) $value));
        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * Canonical date: fold any parseable user date into Y-m-d.
     *
     * @param string|int|null $value
     * @return string|null canonical Y-m-d, or null when unparseable
     */
    public static function cleanDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /**
     * Canonical date-of-birth: like cleanDate but additionally rejects when the
     * value is today or in the future (a DOB must be firmly in the past).
     *
     * @param string|int|null $value
     * @return string|null canonical Y-m-d strictly in the past
     */
    public static function cleanDob($value): ?string
    {
        $canonical = self::cleanDate($value);
        if ($canonical === null) {
            return null;
        }
        if ($canonical >= date('Y-m-d')) {
            return null; // today or future — not a valid DOB
        }
        return $canonical;
    }

    /**
     * Canonical phone: delegate to the shared normalizer.
     *
     * @param string|null $value
     * @return string|null canonical 2547XXXXXXXX, or null
     */
    public static function cleanPhone(?string $value): ?string
    {
        return PhoneNumberNormalizer::normalize($value);
    }

    /**
     * Canonical national ID / passport number: digits only, 5-12 characters.
     * Separators (spaces, dashes, dots, slashes) are stripped, but any letter
     * or symbol in the value rejects it — identity is never silently rewritten.
     *
     * @param string|null $value
     * @return string|null canonical digits, or null when invalid
     */
    public static function cleanNationalId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $clean = (string) preg_replace('/[\s\-.\/]+/', '', trim((string) $value));
        if ($clean === '' || !ctype_digit($clean)) {
            return null;
        }
        $length = strlen($clean);
        return ($length < 5 || $length > 12) ? null : $clean;
    }

    /**
     * Canonical residential address: trim, collapse internal whitespace,
     * allow letters/digits and common address punctuation (commas, periods,
     * hashes, slashes, parentheses, hyphens, ampersands, at-signs). Rejects
     * control/symbol characters and over-length values so free-text addresses
     * stay analysable.
     *
     * @param string|null $value
     * @return string|null canonical collapsed address, or null when invalid
     */
    public static function cleanAddress(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $cleaned = trim((string) preg_replace('/\s+/', ' ', (string) $value));
        if ($cleaned === '' || strlen($cleaned) > 120) {
            return null;
        }
        if (preg_match(self::ADDRESS_CHARS_PATTERN, $cleaned) !== 1) {
            return null;
        }
        return $cleaned;
    }

    /**
     * Batch clean + report per-field errors in one pass.
     *
     * Spec maps field => cleaner kind. Supported kinds:
     *
     *   - name   : canonical title-cased name (letters/spaces/hyphens only)
     *   - email  : trimmed lowercase email
     *   - date   : canonical Y-m-d
     *   - dob    : canonical Y-m-d strictly in the past
     *   - phone  : canonical 2547XXXXXXXX
     *   - national_id : digits-only 5-12 character ID/passport
     *   - address : trimmed, whitespace-collapsed address text
     *
     * @param array<string,mixed> $data
     * @param array<string,string> $spec field => cleaner kind
     * @return array{cleaned: array<string,mixed>, errors: array<string,string>}
     */
    public static function clean(array $data, array $spec): array
    {
        $cleaned = [];
        $errors = [];
        foreach ($spec as $field => $kind) {
            $value = $data[$field] ?? null;
            switch ($kind) {
                case 'name':
                    $result = self::cleanName($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' must contain letters only (no digits or symbols)';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'email':
                    $result = self::cleanEmail($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' is not a valid email';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'date':
                    $result = self::cleanDate($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' is not a valid date';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'dob':
                    $result = self::cleanDob($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' must be a date strictly in the past (not today or future)';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'phone':
                    $result = self::cleanPhone($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' is not a valid Kenyan phone number';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'national_id':
                    $result = self::cleanNationalId($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' must be a valid national ID or passport number (digits only, 5-12 characters)';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                case 'address':
                    $result = self::cleanAddress($value);
                    if ($result === null) {
                        $errors[$field] = self::friendly($field) . ' is not a valid address';
                    } else {
                        $cleaned[$field] = $result;
                    }
                    break;
                default:
                    // Unknown cleaner never blocks data unexpectedly.
                    break;
            }
        }
        return ['cleaned' => $cleaned, 'errors' => $errors];
    }

    private static function collapseWhitespace(string $value): string
    {
        return preg_replace('/\s+/', ' ', $value);
    }


    private static function titleCaseWords(string $name): string
    {
        return preg_replace_callback(
            '/(^|[\s\'’\-])([a-z])/',
            static function (array $m): string {
                return $m[1] . strtoupper($m[2]);
            },
            strtolower($name)
        );
    }
    private static function friendly(string $field): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $field));
    }
}
