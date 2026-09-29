<?php
namespace App\API\Services;

/**
 * PhoneNumberNormalizer — single canonical phone format for the whole system.
 *
 * Every phone the system stores or sends to a payment provider follows the
 * E.164-without-plus form: 254XXXXXXXXX (12 digits, no "+", no spaces).
 *
 * Kenyan mobile numbers using these prefixes are normalized without inferring
 * an operator from the prefix:
 *   07XXXXXXXX -> 2547XXXXXXXX
 *   01XXXXXXXX -> 2541XXXXXXXX
 * Users may type "+254...", "254...", "0...", "07...", "01...", "7...", "1...",
 * with spaces or dashes; normalize() collapses all of them to the canonical
 * value and returns null for anything clearly not a Kenyan mobile number (or an
 * email). Restricting the canonical form to 2547 only would silently discard
 * every 01 number, so both prefixes are accepted.
 *
 * This is the one source of truth; individual services must not re-implement
 * their own digit-shuffling.
 */
final class PhoneNumberNormalizer
{
    /** 2547XXXXXXXX (Safaricom) or 2541XXXXXXXX (Airtel). */
    private const CANONICAL_PATTERN = '/^254[71]\d{8}$/';

    public static function normalize(?string $input): ?string
    {
        $value = trim((string) $input);
        if ($value === '' || str_contains($value, '@')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '') {
            return null;
        }

        $length = strlen($digits);

        // International form -- the leading "+" is already stripped.
        if ($length === 12 && str_starts_with($digits, '254')) {
            // canonical already
        } elseif ($length === 13 && str_starts_with($digits, '254')) {
            // Extra digit after "254" (e.g. a stray leading 0) -- drop it.
            $digits = substr($digits, 0, 12);
        } elseif ($length === 10 && str_starts_with($digits, '0')) {
            // Local 10-digit form.
            $digits = '254' . substr($digits, 1);
        } elseif ($length === 9 && (str_starts_with($digits, '7') || str_starts_with($digits, '1'))) {
            // Local 9-digit form without the leading zero.
            $digits = '254' . $digits;
        } else {
            return null;
        }

        return preg_match(self::CANONICAL_PATTERN, $digits) === 1 ? $digits : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /**
     * Digits-only form of a phone, regardless of the "+", spaces, brackets or
     * leading-zero formatting stored in a row. Used for identity matching so a
     * stored "+254 797 000 000" / "07..." value still matches the canonical
     * "2547..." form a form submits. Returns '' for an empty/non-phone value.
     */
    public static function digits(?string $input): string
    {
        return preg_replace('/\D+/', '', trim((string) $input)) ?? '';
    }

    /**
     * Single canonical identity key for a phone number. Both the local
     * "0XXXXXXXXX" (10 digits) and international "254XXXXXXXXX" (12 digits)
     * representations of the same Kenyan number collapse onto "254XXXXXXXXX",
     * so stored and submitted values compare equal regardless of which form
     * each side used. Returns '' for an empty/non-phone value.
     */
    public static function matchKey(?string $input): string
    {
        $digits = self::digits($input);
        if ($digits !== '' && strlen($digits) === 10 && $digits[0] === '0') {
            return '254' . substr($digits, 1);
        }
        return $digits;
    }
}
