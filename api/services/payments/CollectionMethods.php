<?php

declare(strict_types=1);

namespace App\API\Services\payments;

/**
 * The payment methods a payer or a staff operator may choose.
 *
 * Providers and channels are SYSTEM VARIABLES, not user choices: a prompt is
 * a prompt whether Buni, Daraja or PayHero carries it, and they all land in
 * the same configured account. The frontend therefore never names or picks a
 * rail. It submits a method; the backend reads the purpose (fees / transport /
 * uniforms), consults `payment_collection_routes.priority` via
 * `CollectionRailResolver`, and fails over at initiation time.
 *
 * The vocabulary a user sees is fixed:
 *
 *   prompt        an STK push to the M-Pesa line
 *   mobile_money  the payer paying a paybill/till themselves
 *                 (Lipa na M-Pesa / Lipa Karo)
 *   bank_deposit  a deposit the payer makes at the bank
 *   cash          recorded at the school cash office
 *   cheque        recorded at the school cash office
 *
 * `auto` is the historical name for `prompt` and is still accepted so
 * existing clients and stored intents keep working.
 */
final class CollectionMethods
{
    public const PROMPT = 'prompt';
    public const MOBILE_MONEY = 'mobile_money';
    public const BANK_DEPOSIT = 'bank_deposit';
    public const CASH = 'cash';
    public const CHEQUE = 'cheque';

    /** Legacy alias still accepted by every intake point. */
    public const LEGACY_PROMPT = 'auto';

    /** Methods that require the backend to pick an STK rail. */
    public const RAIL_METHODS = [self::PROMPT, self::LEGACY_PROMPT];

    /** Methods recorded without any provider call. */
    public const MANUAL_METHODS = [self::CASH, self::BANK_DEPOSIT, self::CHEQUE];

    /** Everything a client may submit. */
    public const CLIENT_METHODS = [
        self::PROMPT,
        self::MOBILE_MONEY,
        self::BANK_DEPOSIT,
        self::CASH,
        self::CHEQUE,
        self::LEGACY_PROMPT,
    ];

    /** Parent-facing online methods: no office-only methods, no provider names. */
    public const PARENT_METHODS = [self::PROMPT, self::MOBILE_MONEY];

    /**
     * Normalise a submitted method. Unknown values are rejected so a client
     * can never smuggle a provider code (daraja_mpesa, buni_mpesa, ...) into
     * a field the system is supposed to own.
     */
    public static function normalize(string $method): string
    {
        $method = strtolower(trim($method));
        if (in_array($method, self::RAIL_METHODS, true)) {
            return self::PROMPT;
        }
        if ($method === 'c2b_mpesa' || $method === 'mpesa_bank_paybill' || $method === 'lipa_na_mpesa') {
            return self::MOBILE_MONEY;
        }
        if ($method === 'bank_transfer' || $method === 'direct_deposit') {
            return self::BANK_DEPOSIT;
        }
        if (!in_array($method, [self::PROMPT, self::MOBILE_MONEY, self::BANK_DEPOSIT, self::CASH, self::CHEQUE], true)) {
            throw new \RuntimeException('Unsupported payment method.');
        }
        return $method;
    }

    public static function isPrompt(string $method): bool
    {
        $method = strtolower(trim($method));
        return in_array($method, self::RAIL_METHODS, true);
    }

    public static function isManual(string $method): bool
    {
        return in_array(strtolower(trim($method)), self::MANUAL_METHODS, true);
    }

    public static function isAccepted(string $method): bool
    {
        try {
            self::normalize((string) $method);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
