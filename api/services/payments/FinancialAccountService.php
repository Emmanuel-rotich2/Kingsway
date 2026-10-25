<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use RuntimeException;
use App\API\Services\ReadReplicaService;

/**
 * Resolves verified school accounts for one purpose and one channel.
 *
 * These are the school's financial CONFIGURATION tables — a handful of rows
 * covering the accounts, their channels and their chart-of-accounts mapping,
 * not analytical aggregates. They answer "which ledger does this money belong
 * to", so every collection and disbursement service asks this class on the
 * money path while already inside a posting transaction.
 *
 * They are therefore read from the authoritative primary database, never from
 * a materialized projection. `qualifiedRef()` refuses a stale projection and
 * skips its own auto-repair while a transaction is open, which turned a
 * routine replica lag into a failed payment: the ledger account was never
 * resolved, the posting was abandoned and the provider was told to keep
 * retrying money it had already collected.
 */
final class FinancialAccountService
{
    private PDO $db;
    private ReferenceNormalizer $normalizer;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->normalizer = new ReferenceNormalizer();
    }

    public function requireFor(int $accountId, string $purpose, string $channel, bool $disbursement = false, ?int $actorUserId = null): array
    {
        if ($purpose === 'fees' && $channel === 'cash') {
            throw new RuntimeException('Cash payments are not supported for school fees.');
        }
        if ($accountId <= 0 && !$disbursement) {
            // The configured collection route is the authority for which
            // school account receives a purpose + channel. Account-channel
            // rows are the fallback for manual rails (cash/cheque/bank).
            $default = $this->db->prepare(
                "SELECT COALESCE(r.settlement_financial_account_id, r.financial_account_id) AS id
                   FROM payment_collection_routes r
                   JOIN payment_collection_route_channels rc ON rc.route_id=r.id
                   JOIN financial_channels fc ON fc.id=rc.channel_id AND fc.code=?
                   JOIN " . ReadReplicaService::masterRef("school_financial_accounts") . " a
                        ON a.id=COALESCE(r.settlement_financial_account_id, r.financial_account_id)
                       AND a.status='active'
                  WHERE r.active=1 AND r.purpose=?
                  ORDER BY r.priority, r.id LIMIT 1"
            );
            $default->execute([$channel, $purpose]);
            $accountId = (int) $default->fetchColumn();
        }
        if ($accountId <= 0 && !$disbursement) {
            $default = $this->db->prepare("SELECT a.id FROM " . ReadReplicaService::masterRef("school_financial_accounts") . " a
                JOIN financial_account_purposes fp ON fp.code=?
                JOIN school_financial_account_purposes sap ON sap.financial_account_id=a.id AND sap.purpose_id=fp.id
                JOIN " . ReadReplicaService::masterRef("school_financial_account_channels") . " ac ON ac.financial_account_id=a.id
                JOIN financial_channels fc ON fc.id=ac.channel_id
                    AND (fc.code=? OR (?='buni_transfer' AND fc.code='bank_transfer'))
                WHERE a.status='active' ORDER BY a.is_primary DESC,a.id LIMIT 1");
            $default->execute([$purpose, $channel, $channel]);
            $accountId=(int)$default->fetchColumn();
        }
        if ($accountId <= 0) throw new RuntimeException('A school financial account must be selected.');
        $sql = $disbursement
            ? "SELECT a.*, k.code AS account_kind, p.code AS provider_code, c.account_code AS ledger_code
                FROM " . ReadReplicaService::masterRef("school_financial_accounts") . " a
                JOIN financial_account_kinds k ON k.id=a.account_kind_id
                LEFT JOIN payment_providers p ON p.id=a.provider_id
                LEFT JOIN " . ReadReplicaService::masterRef("chart_of_accounts") . " c ON c.id=a.ledger_account_id
                JOIN financial_account_purposes fp ON fp.code=? AND EXISTS (
                    SELECT 1 FROM school_financial_account_purposes sap
                    WHERE sap.financial_account_id=a.id AND sap.purpose_id=fp.id
                )
                WHERE a.id=? AND a.status='active' LIMIT 1"
            : "SELECT a.*, k.code AS account_kind, p.code AS provider_code, c.account_code AS ledger_code
                FROM school_financial_accounts a
                JOIN financial_account_kinds k ON k.id=a.account_kind_id
                LEFT JOIN payment_providers p ON p.id=a.provider_id
                LEFT JOIN chart_of_accounts c ON c.id=a.ledger_account_id
                JOIN financial_account_purposes fp ON fp.code=?
                JOIN school_financial_account_purposes sap ON sap.financial_account_id=a.id AND sap.purpose_id=fp.id
                WHERE a.id=? AND a.status='active' LIMIT 1";
        $s = $this->db->prepare($sql);
        $s->execute($disbursement ? [$purpose, $accountId] : [$purpose, $accountId]);
        $account = $s->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new RuntimeException('Selected financial account is inactive or is not authorized for this purpose.');
        if (empty($account['ledger_code'])) throw new RuntimeException('Selected financial account has no chart-of-accounts mapping.');
        if ($disbursement && $actorUserId) {
            $permission = $this->db->prepare('SELECT 1 FROM ' . ReadReplicaService::masterRef('school_financial_account_permissions') . ' JOIN user_roles ur ON ur.role_id=ap.role_id WHERE ap.financial_account_id=? AND ur.user_id=? AND ap.can_disburse=1 LIMIT 1');
            $permission->execute([$accountId, $actorUserId]);
            if (!$permission->fetchColumn()) throw new RuntimeException('You are not authorized to disburse from the selected financial account.');
        }
        return $account;
    }

    public function listAvailable(string $purpose, string $channel): array
    {
        if ($purpose === 'fees' && $channel === 'cash') return [];
        $s = $this->db->prepare("SELECT DISTINCT a.id,a.account_name,a.account_identifier,a.bank_name,a.currency,a.ledger_account_id,p.code provider_code
            FROM " . ReadReplicaService::masterRef("school_financial_accounts") . " a
            JOIN financial_account_purposes fp ON fp.code=?
            JOIN school_financial_account_purposes sap ON sap.financial_account_id=a.id AND sap.purpose_id=fp.id
            LEFT JOIN payment_providers p ON p.id=a.provider_id
            WHERE a.status='active' AND (
                EXISTS (
                    SELECT 1 FROM payment_collection_routes r
                    JOIN payment_collection_route_channels rc ON rc.route_id=r.id
                    JOIN financial_channels rfc ON rfc.id=rc.channel_id AND rfc.code=?
                    WHERE COALESCE(r.settlement_financial_account_id,r.financial_account_id)=a.id
                      AND r.active=1 AND r.purpose=?
                )
                OR EXISTS (
                    SELECT 1 FROM " . ReadReplicaService::masterRef("school_financial_account_channels") . " ac
                    JOIN financial_channels fc ON fc.id=ac.channel_id
                        AND (fc.code=? OR (?='buni_transfer' AND fc.code='bank_transfer'))
                    WHERE ac.financial_account_id=a.id
                )
            )
            ORDER BY a.is_primary DESC,a.account_name");
        $s->execute([$purpose, $channel, $purpose, $channel, $channel]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function normalizeIdentifier(string $value): string
    {
        return $this->normalizer->accountIdentifier($value);
    }

    public function assertSamePurpose(string $purpose, string $reference): void
    {
        $detected = $this->normalizer->purposeFromReference($reference);
        if ($detected !== null && $detected !== $purpose) {
            throw new RuntimeException('Payment reference and financial purpose conflict.');
        }
    }
}
