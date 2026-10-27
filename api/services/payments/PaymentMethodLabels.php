<?php

declare(strict_types=1);

namespace App\API\Services\payments;

/**
 * Generic, non-proprietary vocabulary for payment rails.
 *
 * Staff other than the System Administrator must never learn which
 * aggregator carried a payment. To them a rail is only what the parent
 * experiences:
 *
 *   - a prompt pushed to the M-Pesa line
 *   - a deposit the parent made themselves (bank transfer / cheque / cash)
 *   - Lipa Karo on the KCB mobile app
 *   - Lipa na M-Pesa on the Safaricom app or SIM toolkit
 *
 * The proprietary product names (Buni, Daraja, PayHero) belong to the
 * integration-configuration surface, which is gated by
 * `system.payment_integrations.configure` / the System Administrator role.
 * Everything a parent sees is already safe: KCB Bank, M-Pesa, Safaricom.
 *
 * Routed values keep their internal codes: this class only replaces the
 * strings that are displayed or serialised to a non-admin client.
 */
final class PaymentMethodLabels
{
    /** Method code => the label a payer or operator sees. */
    private const METHOD_LABELS = [
        'prompt'        => 'Prompt',
        'mobile_money'  => 'Lipa na M-Pesa / Lipa Karo',
        'bank_deposit'  => 'Bank deposit',
        'bank_transfer' => 'Bank deposit',
        'cash'          => 'Cash',
        'cheque'        => 'Cheque',
        'card_pos'      => 'Card',
        'internal_transfer' => 'Internal transfer',
    ];

    /**
     * Channel code => parent-safe label. Kept for values that still arrive on
     * the wire (a provider callback, a legacy intent row). These are internal
     * codes and must never be selected or rendered by a user surface; use
     * CollectionMethods plus label() instead.
     */
    private const CHANNEL_LABELS = [
        'daraja_mpesa'       => 'M-Pesa',
        'safaricom_daraja'   => 'M-Pesa',
        'mpesa_daraja'       => 'M-Pesa',
        'mpesa_stk'          => 'Prompt',
        'safaricom_stk'      => 'Prompt',
        'safaricom_c2b'      => 'M-Pesa',
        'c2b_mpesa'          => 'Lipa na M-Pesa / Lipa Karo',
        'buni_mpesa'         => 'KCB Bank',
        'kcb_buni'           => 'KCB Bank',
        'kcb_buni_ipn'       => 'Lipa Karo (KCB mobile app)',
        'payhero'            => 'Online Payment',
        'payhero_mpesa'      => 'Online Payment',
        'payhero_stk'        => 'Online Payment',
        'payhero_africa'     => 'Online Payment',
        'bank_transfer'      => 'Direct deposit',
        'bank_webhook'       => 'Direct deposit',
        'cash'               => 'Cash',
        'cheque'             => 'Cheque',
    ];

    /** Collection product => parent-safe label. */
    private const PRODUCT_LABELS = [
        'paybill'         => 'Lipa na M-Pesa (Safaricom app / SIM toolkit)',
        'till'            => 'Lipa na M-Pesa (Safaricom app / SIM toolkit)',
        'buni'            => 'Lipa Karo (KCB mobile app)',
        'bank_collection' => 'Direct deposit',
        'stk'             => 'M-Pesa prompt',
    ];

    /**
     * Parent-safe name for a method or a rail/channel code. Method codes
     * (prompt / mobile_money / bank_deposit) win first, because those are
     * what a user actually picked; the channel map only covers legacy rows
     * and provider-carried values. Unknown codes fall back to the code itself
     * rather than to an empty string, so a new rail degrades to something
     * legible instead of blanking the row.
     */
    public static function label(?string $code): string
    {
        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return '';
        }
        if (isset(self::METHOD_LABELS[$code])) {
            return self::METHOD_LABELS[$code];
        }
        if (isset(self::CHANNEL_LABELS[$code])) {
            return self::CHANNEL_LABELS[$code];
        }
        // Tolerate prefixed variants (e.g. payhero_africa_payout_phone).
        foreach (self::CHANNEL_LABELS as $prefix => $label) {
            if (str_starts_with($code, $prefix . '_') && $prefix !== 'payhero') {
                return $label;
            }
        }
        return (string) $code;
    }

    public static function product(?string $product): string
    {
        $product = strtolower(trim((string) $product));
        return self::PRODUCT_LABELS[$product] ?? ucwords(str_replace('_', ' ', $product));
    }

    /** True when the code is internal vocabulary that must not reach a non-admin. */
    public static function isInternal(string $code): bool
    {
        $code = strtolower(trim($code));
        return in_array($code, ['buni', 'kcb_buni', 'kcb_buni_ipn', 'daraja', 'mpesa_daraja', 'safaricom_daraja', 'daraja_mpesa', 'payhero', 'payhero_mpesa'], true)
            || str_starts_with($code, 'payhero_');
    }
}
