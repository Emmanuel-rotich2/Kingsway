<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * PaymentAlertPublisher - the missing PAYMENT_ALERT producer.
 *
 * The realtime protocol has carried a PAYMENT_ALERT event type since the
 * gateway build, but nothing ever published it, so finance staff never saw a
 * verified payment land on their dashboards without a manual refresh. This is
 * the single producer for that event, called from the one choke point every
 * verified incoming money posting already passes through
 * (FinancialPostingCoordinator), so fees, transport, uniforms and admission
 * extra charges all alert with zero per-service wiring.
 *
 * Safety rules (unchanged):
 *  - Descriptor only: record/status/domain/targets. No payer identity, no
 *    amount, no learner, no account numbers cross the stream. PHP re-authorizes
 *    every follow-up data fetch.
 *  - Audience-bounded: scope 'finance' reaches only connections whose
 *    PHP-minted capability includes the finance channel.
 *  - Non-blocking and failure-proof: RealtimeGatewayPublisher::publish is
 *    fire-and-forget with a 2s timeout and never throws, so a realtime outage
 *    can never slow or fail a money posting.
 *  - Idempotent by construction: callers pass only postings whose status is
 *    'posted' (a replayed/idempotent post returns 'already_posted' and must
 *    NOT alert again).
 */
final class PaymentAlertPublisher
{
    /** Purposes that represent verified money IN and therefore may alert. */
    private const ALERTABLE_PURPOSES = ['fees', 'transport', 'uniforms', 'extra_charge'];

    /** @var callable|null Injectable config reader for hermetic tests. */
    private static $configReader = null;

    /** Test hook: inject a config reader; pass null to restore. */
    public static function injectForTests(?callable $configReader = null): void
    {
        self::$configReader = $configReader;
    }

    /**
     * Announce that a verified payment completed its financial posting.
     *
     * @param int    $journalBatchId The posted accounting_journal_batches.id
     *                               (the durable, re-checkable record identity).
     * @param string $purpose       fees|transport|uniforms|extra_charge.
     */
    public static function posted(int $journalBatchId, string $purpose): bool
    {
        if ($journalBatchId < 1 || !in_array($purpose, self::ALERTABLE_PURPOSES, true)) {
            return false;
        }

        return RealtimeGatewayPublisher::publish('PAYMENT_ALERT', 'finance', [
            'record_id' => $journalBatchId,
            'status' => 'posted',
            'domain' => 'finance',
            'action' => $purpose,
            'targets' => ['finance', 'payments'],
        ]);
    }

    private static function reader(): callable
    {
        return self::$configReader ?? static fn (string $key, $default = null) => Config::get($key, $default);
    }
}
