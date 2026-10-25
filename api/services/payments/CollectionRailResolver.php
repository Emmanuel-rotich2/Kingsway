<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Resolves the ordered list of online collection rails for a payment purpose.
 *
 * ARCHITECTURE (STK failover is per-purpose, not global):
 *   - fees,      transport: BUNI primary -> Daraja -> PayHero
 *   - uniforms:              Daraja primary -> PayHero (no Buni uniforms route)
 *
 *   - fees / transport  = channel kcb_buni_ipn  (KCB Buni M-Pesa Express)
 *   - fallback STK      = channel safaricom_stk (Daraja LIPA_NA_MPESA_ONLINE)
 *   - last resort       = channel payhero_stk   (PayHero aggregator)
 *   - Daraja C2B        = validation + confirmation (channel safaricom_c2b), separately
 *
 * Gateways are rails, not account-bound: the receiving / settling accounts are
 * read from `payment_collection_routes` at transaction time.
 *
 * Fees and transport settle to KCB bank accounts, so KCB Buni is the primary
 * rail: it pushes the prompt against the school's own Safaricom shortcode and
 * settles into KCB directly, with no aggregator margin and no service-wallet
 * float to maintain. PayHero demotes to last resort because its bank channels
 * intermediate the collection: the fee is a service_charge debited from the
 * merchant wallet, and an unfunded (PENDING) wallet blocks the STK outright.
 *
 * `payment_collection_routes.priority` orders rails; STK_PROVIDER_ORDER
 * re-ranks STK candidates for the purposes that declare a failover contract.
 * A purpose with no declared order falls through to plain priority order.
 * Routes whose provider cannot initiate an online collection (generic bank
 * etc.) or whose channel list excludes the rail's channel are skipped.
 *
 * Reads go to the primary connection on purpose: rail configuration changes
 * must be visible to the very next initiation (no replica lag on money rails).
 */
class CollectionRailResolver
{
    /** provider code => canonical financial_channels.code used to initiate a collection */
    private const RAIL_CHANNELS = [
        'kcb_buni'        => 'kcb_buni_ipn',    // PRIMARY STK for fees/transport
        'safaricom_daraja' => 'safaricom_stk',  // STK fallback (LIPA_NA_MPESA_ONLINE)
        'payhero'         => 'payhero_stk',      // LAST RESORT STK (aggregator)
    ];

    /**
     * Per-purpose STK failover order. A purpose absent from this map keeps
     * plain `priority` ordering. Order here must mirror the
     * `payment_collection_routes.priority` values so intent and data agree.
     */
    private const STK_PROVIDER_ORDER = [
        'fees'      => ['kcb_buni', 'safaricom_daraja', 'payhero'],
        'transport' => ['kcb_buni', 'safaricom_daraja', 'payhero'],
        'uniforms'  => ['safaricom_daraja', 'payhero'],
    ];


    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function isOnlineProvider(string $providerCode): bool
    {
        return isset(self::RAIL_CHANNELS[ProviderCodes::canonical($providerCode)]);
    }

    /**
     * Candidate rails for a purpose, ordered by priority then route id.
     *
     * @param int    $financialAccountId optional account filter (parent/staff selection)
     * @param string|null $providerCode  optional single-provider filter (explicit caller choice)
     * @return array<int, array{
     *     route_id:int,
     *     provider_code:string,
     *     channel:string,
     *     account_identifier:string,
     *     collection_product:?string,
     *     financial_account_id:int,
     *     settlement_financial_account_id:?int,
     *     priority:int
     * }>
     */
    public function candidates(string $purpose, int $financialAccountId = 0, ?string $providerCode = null): array
    {
        $sql = "SELECT r.id, r.priority, r.account_identifier, r.collection_product,
                       r.financial_account_id, r.settlement_financial_account_id,
                       p.code AS provider_code
                  FROM payment_collection_routes r
                  JOIN payment_providers p ON p.id = r.provider_id AND p.active = 1
                  JOIN school_financial_accounts a ON a.id = r.financial_account_id AND a.status = 'active'
                 WHERE r.active = 1 AND r.purpose = ?";
        $params = [$purpose];

        if ($financialAccountId > 0) {
            $sql .= ' AND r.financial_account_id = ?';
            $params[] = $financialAccountId;
        }
        if ($providerCode !== null && $providerCode !== '') {
            $variants = ProviderCodes::variants($providerCode);
            $sql .= ' AND p.code IN (' . implode(',', array_fill(0, count($variants), '?')) . ')';
            array_push($params, ...$variants);
        }

        $sql .= ' ORDER BY r.priority ASC, r.id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }

        $channelsByRoute = $this->routeChannels(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        ));

        $out = [];
        foreach ($rows as $row) {
            $providerCode = ProviderCodes::canonical((string) $row['provider_code']);
            $channel = self::RAIL_CHANNELS[$providerCode] ?? null;
            if ($channel === null) {
                continue; // not an online-initiating provider (e.g. generic_bank)
            }
            $routeChannels = $channelsByRoute[(int) $row['id']] ?? [];
            if ($routeChannels !== [] && !in_array($channel, $routeChannels, true)) {
                continue; // route restricts channels and this rail is not allowed
            }

            $out[] = [
                'route_id' => (int) $row['id'],
                'provider_code' => $providerCode,
                'channel' => $channel,
                'account_identifier' => (string) $row['account_identifier'],
                'collection_product' => $row['collection_product'] !== null ? (string) $row['collection_product'] : null,
                'financial_account_id' => (int) $row['financial_account_id'],
                'settlement_financial_account_id' => $row['settlement_financial_account_id'] !== null
                    ? (int) $row['settlement_financial_account_id']
                    : null,
                'priority' => (int) $row['priority'],
            ];
        }

        // STK failover is a business rule declared per purpose. Providers with
        // no declared order for this purpose keep their database priority, so
        // an unlisted purpose or an unlisted provider still resolves in
        // priority order rather than being silently promoted or dropped.
        $order = self::STK_PROVIDER_ORDER[$purpose] ?? null;
        usort($out, static function (array $a, array $b) use ($order): int {
            $rankA = $order !== null ? (array_search($a['provider_code'], $order, true) === false
                ? 99
                : array_search($a['provider_code'], $order, true)) : $a['priority'];
            $rankB = $order !== null ? (array_search($b['provider_code'], $order, true) === false
                ? 99
                : array_search($b['provider_code'], $order, true)) : $b['priority'];
            return [$rankA, $a['priority'], $a['route_id']]
                <=> [$rankB, $b['priority'], $b['route_id']];
        });

        return $out;
    }

    /**
     * @param array<int, int> $routeIds
     * @return array<int, array<int, string>> route id => financial channel codes
     */
    private function routeChannels(array $routeIds): array
    {
        if ($routeIds === []) {
            return [];
        }

        $in = implode(',', array_fill(0, count($routeIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT rc.route_id, fc.code AS channel_code
               FROM payment_collection_route_channels rc
               JOIN financial_channels fc ON fc.id = rc.channel_id
              WHERE rc.route_id IN ($in)"
        );
        $stmt->execute($routeIds);

        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['route_id']][] = (string) $row['channel_code'];
        }

        return $out;
    }
}
