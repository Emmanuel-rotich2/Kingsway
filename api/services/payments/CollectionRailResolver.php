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
 * Gateways are rails, not account-bound: the receiving / settling accounts are
 * read from `payment_collection_routes` at transaction time, and the failover
 * order is driven by `routes.priority` (lower is attempted first). Routes whose
 * provider cannot initiate an online collection (generic bank etc.) or whose
 * channel list excludes the rail's channel are skipped.
 *
 * Reads go to the primary connection on purpose: rail configuration changes
 * must be visible to the very next initiation (no replica lag on money rails).
 */
class CollectionRailResolver
{
    /** provider code => financial channel code used to initiate a collection */
    private const RAIL_CHANNELS = [
        'kcb_buni' => 'buni_ipn',
        'payhero' => 'mpesa_stk',
        'mpesa_daraja' => 'mpesa_stk',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function isOnlineProvider(string $providerCode): bool
    {
        return isset(self::RAIL_CHANNELS[$providerCode]);
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
            $sql .= ' AND p.code = ?';
            $params[] = $providerCode;
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
            $providerCode = (string) $row['provider_code'];
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
