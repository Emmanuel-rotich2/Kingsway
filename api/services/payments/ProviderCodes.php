<?php
declare(strict_types=1);

namespace App\API\Services\payments;

/**
 * Canonical payment provider codes.
 *
 * The database (`payment_providers.code`) stores `safaricom_daraja`, while
 * several API/UI call sites historically used `mpesa_daraja`. Route lookups
 * must match the database value, so all boundaries normalise through here.
 */
final class ProviderCodes
{
    private const CANONICAL = [
        'mpesa' => 'safaricom_daraja',
        'mpesa_daraja' => 'safaricom_daraja',
        'daraja' => 'safaricom_daraja',
        'safaricom' => 'safaricom_daraja',
        'safaricom_daraja' => 'safaricom_daraja',
        'buni' => 'kcb_buni',
        'kcb' => 'kcb_buni',
        'kcb_buni' => 'kcb_buni',
        'payhero_mpesa' => 'payhero',
        'payhero' => 'payhero',
        'generic_bank' => 'generic_bank',
    ];

    public static function canonical(string $code): string
    {
        $normalized = strtolower(trim($code));

        return self::CANONICAL[$normalized] ?? $normalized;
    }

    /** @return array<int, string> canonical code followed by accepted aliases */
    public static function variants(string $code): array
    {
        $canonical = self::canonical($code);
        $variants = [$canonical];
        foreach (self::CANONICAL as $alias => $mapped) {
            if ($mapped === $canonical) {
                $variants[] = $alias;
            }
        }

        return array_values(array_unique($variants));
    }
}
