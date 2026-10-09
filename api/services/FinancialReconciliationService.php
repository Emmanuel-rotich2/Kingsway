<?php
declare(strict_types=1);

namespace App\API\Services;

use App\API\Services\payments\FinancialReconciliationMatcher;
use App\API\Services\payments\ReferenceNormalizer;
use PDO;
use RuntimeException;

/** Imports immutable statement facts and records matching decisions separately. */
final class FinancialReconciliationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function import(string $provider, int $accountId, array $rows, int $userId): array
    {
        if ($provider === '' || $accountId <= 0 || !$rows) throw new RuntimeException('Provider, source account and statement rows are required.');
        $normalizer = new ReferenceNormalizer();
        $matcher = new FinancialReconciliationMatcher();
        $this->db->beginTransaction();
        try {
            $batch = bin2hex(random_bytes(16));
            $this->db->prepare('INSERT INTO financial_statement_imports (import_reference,provider_code,financial_account_id,imported_by,row_count) VALUES (?,?,?,?,?)')
                ->execute([$batch, $provider, $accountId, $userId, count($rows)]);
            $importId = (int) $this->db->lastInsertId();
            $insert = $this->db->prepare('INSERT INTO financial_statement_lines (import_id,provider_transaction_id,transaction_date,value_date,amount,currency,payer_name,payer_phone,raw_reference,normalized_reference,raw_payload,matching_status,matched_reference) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $seen = [];
            foreach ($rows as $row) {
                $providerTx = trim((string)($row['provider_transaction_id'] ?? ''));
                $reference = (string)($row['reference'] ?? '');
                $normalized = $normalizer->reference($reference);
                $existingTx = false;
                if ($providerTx !== '') {
                    $duplicateQuery = $this->db->prepare('SELECT 1 FROM ' . ReadReplicaService::masterRef('financial_statement_lines') . ' l JOIN ' . ReadReplicaService::masterRef('financial_statement_imports') . ' i ON i.id=l.import_id WHERE i.provider_code=? AND i.financial_account_id=? AND l.provider_transaction_id=? LIMIT 1');
                    $duplicateQuery->execute([$provider, $accountId, $providerTx]);
                    $existingTx = (bool)$duplicateQuery->fetchColumn();
                }
                $status = isset($seen[$providerTx]) || $existingTx ? 'duplicate' : 'unmatched';
                $matchedReference = null;
                if ($status !== 'duplicate' && $normalized !== '') {
                    $q = $this->db->prepare("SELECT r.reference, COALESCE(t.amount,u.amount,0) AS amount
                        FROM payment_routing_references r
                        LEFT JOIN transport_payment_intents t ON t.id=r.transport_intent_id
                        LEFT JOIN uniform_payment_intents u ON u.sale_id=r.uniform_sale_id AND u.status IN ('pending','accepted','confirmed')
                        WHERE r.normalized_reference=? AND r.status IN ('active','consumed') LIMIT 2");
                    $q->execute([$normalized]);
                    $candidates = $q->fetchAll(PDO::FETCH_ASSOC);
                    if (count($candidates) === 1) {
                        $matchedReference = $candidates[0]['reference'];
                        $status = (float)$candidates[0]['amount'] > 0
                            ? $matcher->classify($reference, (string)$matchedReference, (string)($row['amount'] ?? '0'), (string)$candidates[0]['amount'])
                            : 'matched';
                    } elseif (count($candidates) > 1) $status = 'conflict';
                }
                $insert->execute([$importId, $providerTx ?: null, $row['transaction_date'] ?? null, $row['value_date'] ?? null, $row['amount'] ?? 0, $row['currency'] ?? 'KES', $row['payer_name'] ?? null, $row['payer_phone'] ?? null, $reference, $normalized ?: null, json_encode($row), $status, $matchedReference]);
                $seen[$providerTx] = true;
            }
            $this->db->commit();
            return ['import_id' => $importId, 'import_reference' => $batch, 'row_count' => count($rows)];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function unresolved(int $limit = 200): array
    {
        $limit = min(1000, max(1, $limit));
        $s = $this->db->query("SELECT l.*,i.provider_code,i.financial_account_id FROM " . ReadReplicaService::qualifiedRef("financial_statement_lines") . " JOIN financial_statement_imports i ON i.id=l.import_id WHERE l.matching_status NOT IN ('matched','duplicate') ORDER BY l.id DESC LIMIT {$limit}");
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function resolve(int $lineId, string $status, int $userId, string $reason, ?string $reference = null): array
    {
        $allowed = ['matched','rejected','needs_review','reversed'];
        if (!in_array($status, $allowed, true) || trim($reason) === '') throw new RuntimeException('A valid resolution and reason are required.');
        $s = $this->db->prepare('UPDATE financial_statement_lines SET matching_status=?, matched_reference=?, resolved_by=?, resolved_at=NOW(), resolution_reason=? WHERE id=? AND matching_status NOT IN (\'matched\',\'rejected\')');
        $s->execute([$status, $reference, $userId, $reason, $lineId]);
        if ($s->rowCount() !== 1) throw new RuntimeException('Statement line is already resolved or does not exist.');
        return ['id' => $lineId, 'matching_status' => $status];
    }
}
