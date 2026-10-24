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
 * ARCHITECTURE:
 *   - PayHero = PRIMARY STK Push (channel: payhero_stk)
 *   - Daraja Express = STK FALLBACK (channel: safaricom_stk)
 *   - KCB Buni = C2B/IPN ONLY — NOT in RAIL_CHANNELS (no STK)
 *   - Daraja C2B = Validation + Confirmation (channel: safaricom_c2b) — handled separately
 *
 * Gateways are rails, not account-bound: the receiving / settling accounts are
 * read from `payment_collection_routes` at transaction time. STK failover is a
 * business rule, not a database one: PayHero is always attempted first and
 * Daraja Express second. `routes.priority` still orders non-STK rails.
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
        'payhero'      => 'payhero_stk',    // PRIMARY STK
        'safaricom_daraja' => 'safaricom_stk',  // FALLBACK STK (LIPA_NA_MPESA_ONLINE)
        // 'kcb_buni'    => 'kcb_buni_ipn', // REMOVED: Buni does C2B/IPN only, no STK
    ];

    /** STK initiation order: PayHero primary, Daraja Express fallback. */
    private const STK_PROVIDER_RANK = [
        'payhero'      => 0,
        'safaricom_daraja' => 1,
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

        // STK failover is a business rule: PayHero is always the primary STK
        // rail and Daraja Express is the fallback, regardless of the database
        // priority used for other collection products.
        usort($out, static function (array $a, array $b): int {
            $rankA = self::STK_PROVIDER_RANK[$a['provider_code']] ?? 99;
            $rankB = self::STK_PROVIDER_RANK[$b['provider_code']] ?? 99;
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
