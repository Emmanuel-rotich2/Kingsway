<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Database\Database;
use PDO;
use Throwable;

/**
 * Keeps balances brought forward in step with the year they came from.
 *
 * At year-end rollover each promoted learner's closing balance is carried
 * into the next year: arrears as opening_arrears_amount on the first bill,
 * an overpayment as a rollover credit note (ROL-...) applied to the new
 * year's bills. Rollover can run before the outgoing year ends, and the
 * outgoing year can still change afterwards (a late payment, a waiver, a
 * correction, a payment credited to it under the year-matching rule). Then
 * the amount carried no longer matches: a learner who paid after rollover
 * would be billed again for what they paid.
 *
 * sync() compares, for every carried learner, the outgoing year's balance
 * now with what was carried, and moves the brought-forward arrears or credit
 * to the new figure. Graduates' records (no next year) are only refreshed.
 * Every change is written to the finance journal. Idempotent: a learner
 * whose balance has not changed is not touched.
 */
class FeeRolloverSyncService
{
    private PDO $db;

    /** @var callable(string $level, array $entry): void */
    private $journal;

    /**
     * @param callable|null $journal receives each finance-journal entry;
     *        defaults to FileLogger (logs/<env>/finance). Tests inject a
     *        collector so a rolled-back run leaves no journal lines.
     */
    public function __construct(?PDO $db = null, ?callable $journal = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->journal = $journal ?? static function (string $level, array $entry): void {
            $level === 'error' ? FileLogger::error('finance', $entry) : FileLogger::write('finance', $entry);
        };
    }

    private function log(string $level, array $entry): void
    {
        ($this->journal)($level, $entry);
    }

    /**
     * @return array{checked:int, adjusted:int, graduates_refreshed:int, skipped_no_bills:int, failed:int}
     */
    public function sync(int $limit = 500): array
    {
        $result = ['checked' => 0, 'adjusted' => 0, 'graduates_refreshed' => 0, 'skipped_no_bills' => 0, 'failed' => 0];

        // The live fee view, not the read model: a write must not act on a
        // figure that is up to a minute old.
        $rows = $this->db->prepare(
            "SELECT r.id, r.student_id, r.from_academic_year_id, r.to_academic_year_id,
                    r.source_balance, r.arrears_carried, r.credit_carried, r.fee_credit_note_id,
                    COALESCE((SELECT SUM(v.balance) FROM vw_student_fee_balances v
                              WHERE v.student_id = r.student_id AND v.academic_year_id = r.from_academic_year_id), 0) AS balance_now
             FROM student_fee_rollover_balances r
             WHERE r.reconciled_at IS NOT NULL
             HAVING ROUND(balance_now, 2) <> ROUND(r.source_balance, 2)
             ORDER BY r.id
             LIMIT " . max(1, min(5000, $limit))
        );
        $rows->execute();

        // Each learner is its own unit of work: a transaction, or a
        // savepoint when the caller already holds a transaction.
        $nested = $this->db->inTransaction();
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result['checked']++;
            try {
                $nested ? $this->db->exec('SAVEPOINT fee_rollover_sync') : $this->db->beginTransaction();
                $outcome = (int) $row['from_academic_year_id'] === (int) $row['to_academic_year_id']
                    ? $this->refreshRecord($row)
                    : $this->adjustCarried($row);
                $nested ? $this->db->exec('RELEASE SAVEPOINT fee_rollover_sync') : $this->db->commit();
                $result[$outcome]++;
            } catch (Throwable $e) {
                if ($nested) {
                    $this->db->exec('ROLLBACK TO SAVEPOINT fee_rollover_sync');
                } elseif ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                $result['failed']++;
                $this->log('error', [
                    'action' => 'fee_rollover_sync_failed',
                    'student_id' => (int) $row['student_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $result;
    }

    /** Graduates and other learners with no next year: record only. */
    private function refreshRecord(array $row): string
    {
        $balance = round((float) $row['balance_now'], 2);
        $this->updateRecord((int) $row['id'], $balance);
        $this->log('info', [
            'action' => 'fee_rollover_record_refreshed',
            'student_id' => (int) $row['student_id'],
            'academic_year_id' => (int) $row['from_academic_year_id'],
            'previous_balance' => (float) $row['source_balance'],
            'balance' => $balance,
        ]);
        return 'graduates_refreshed';
    }

    private function adjustCarried(array $row): string
    {
        $studentId = (int) $row['student_id'];
        $fromYearId = (int) $row['from_academic_year_id'];
        $toYearId = (int) $row['to_academic_year_id'];
        $balance = round((float) $row['balance_now'], 2);
        $arrears = max(0.0, $balance);
        $credit = max(0.0, -$balance);

        // The learner's bills in the incoming year, first term first.
        $bills = $this->db->prepare(
            "SELECT sfo.id, sfo.amount_due, COALESCE(sfo.opening_arrears_amount, 0) AS opening_arrears,
                    COALESCE(sfo.sponsored_waiver_amount, 0) AS sponsored_waiver
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
             WHERE sae.student_id = ? AND sae.academic_year_id = ?
               AND sae.enrollment_status IN ('active','completed','graduated','transferred','withdrawn')
             ORDER BY ayt.opening_date, sfo.id
             FOR UPDATE"
        );
        $bills->execute([$studentId, $toYearId]);
        $bills = $bills->fetchAll(PDO::FETCH_ASSOC);
        if (!$bills) {
            return 'skipped_no_bills';
        }

        // Arrears live on exactly one bill: the first one.
        $laterBills = array_map(static fn ($b) => (int) $b['id'], array_slice($bills, 1));
        if ($laterBills) {
            $this->db->prepare(
                "UPDATE student_fee_obligations SET opening_arrears_amount = 0, updated_at = NOW()
                 WHERE id IN (" . implode(',', $laterBills) . ") AND opening_arrears_amount <> 0"
            )->execute();
        }
        $this->db->prepare(
            "UPDATE student_fee_obligations SET opening_arrears_amount = ?, updated_at = NOW() WHERE id = ?"
        )->execute([$arrears, (int) $bills[0]['id']]);
        // The credit is placed against the bills as they now stand.
        foreach ($bills as $index => &$bill) {
            $bill['opening_arrears'] = $index === 0 ? $arrears : 0.0;
        }
        unset($bill);

        $applied = $this->setRolloverCredit($studentId, $fromYearId, $toYearId, (int) ($row['fee_credit_note_id'] ?? 0), $credit, $bills);

        $this->updateRecord((int) $row['id'], $balance);
        $this->log('info', [
            'action' => 'fee_rollover_carried_balance_adjusted',
            'student_id' => $studentId,
            'from_academic_year_id' => $fromYearId,
            'to_academic_year_id' => $toYearId,
            'previous_balance' => (float) $row['source_balance'],
            'balance' => $balance,
            'arrears_brought_forward' => $arrears,
            'credit_brought_forward' => $credit,
            'credit_applied' => $applied,
        ]);
        return 'adjusted';
    }

    /**
     * Make the learner's rollover credit note equal $credit and re-apply it
     * to the incoming year's bills, first term first.
     */
    private function setRolloverCredit(int $studentId, int $fromYearId, int $toYearId, int $noteId, float $credit, array $bills): float
    {
        $number = 'ROL-' . substr(hash('sha256', $studentId . ':' . $fromYearId . ':' . $toYearId), 0, 20);
        if ($noteId < 1) {
            $lookup = $this->db->prepare("SELECT id FROM fee_credit_notes WHERE credit_number = ? LIMIT 1");
            $lookup->execute([$number]);
            $noteId = (int) ($lookup->fetchColumn() ?: 0);
        }
        if ($noteId < 1 && $credit <= 0) {
            return 0.0;
        }
        if ($noteId < 1) {
            $year = $this->db->prepare("SELECT year_code FROM academic_years WHERE id = ?");
            $year->execute([$fromYearId]);
            $this->db->prepare(
                "INSERT INTO fee_credit_notes (credit_number, student_id, academic_year, credit_amount, credit_reason, notes, created_by)
                 VALUES (?, ?, ?, ?, 'overpayment', 'Carried forward from academic-year rollover', NULL)"
            )->execute([$number, $studentId, (int) substr((string) $year->fetchColumn(), 0, 4), $credit]);
            $noteId = (int) $this->db->lastInsertId();
            $this->db->prepare(
                "UPDATE student_fee_rollover_balances SET fee_credit_note_id = ?
                 WHERE student_id = ? AND from_academic_year_id = ? AND to_academic_year_id = ?"
            )->execute([$noteId, $studentId, $fromYearId, $toYearId]);
        }

        $this->db->prepare("DELETE FROM fee_credit_applications WHERE fee_credit_note_id = ?")->execute([$noteId]);

        $allocated = 0.0;
        if ($credit > 0) {
            // Room on each bill: what it charges, less waivers and any other
            // credit notes already applied to it.
            $otherCredit = $this->db->prepare(
                "SELECT COALESCE(SUM(applied_amount), 0) FROM fee_credit_applications
                 WHERE student_fee_obligation_id = ? AND fee_credit_note_id <> ?"
            );
            $insert = $this->db->prepare(
                "INSERT INTO fee_credit_applications (fee_credit_note_id, student_fee_obligation_id, applied_amount, applied_by)
                 VALUES (?, ?, ?, NULL)"
            );
            foreach ($bills as $bill) {
                $otherCredit->execute([(int) $bill['id'], $noteId]);
                $room = (float) $bill['amount_due'] + (float) $bill['opening_arrears']
                    - (float) $bill['sponsored_waiver'] - (float) $otherCredit->fetchColumn();
                $piece = round(min($credit - $allocated, max(0.0, $room)), 2);
                if ($piece <= 0) {
                    continue;
                }
                $insert->execute([$noteId, (int) $bill['id'], $piece]);
                $allocated = round($allocated + $piece, 2);
                if ($allocated >= $credit) {
                    break;
                }
            }
        }

        $this->db->prepare(
            "UPDATE fee_credit_notes
             SET credit_amount = ?, applied_amount = ?,
                 status = CASE WHEN ? <= 0 THEN 'cancelled'
                               WHEN ? >= ? THEN 'fully_applied'
                               WHEN ? > 0 THEN 'partially_applied' ELSE 'available' END,
                 applied_at = CASE WHEN ? > 0 THEN NOW() ELSE applied_at END,
                 updated_at = NOW()
             WHERE id = ?"
        )->execute([$credit, $allocated, $credit, $allocated, $credit, $allocated, $allocated, $noteId]);

        return $allocated;
    }

    private function updateRecord(int $id, float $balance): void
    {
        $this->db->prepare(
            "UPDATE student_fee_rollover_balances
             SET source_balance = ?, arrears_carried = GREATEST(?, 0), credit_carried = GREATEST(-?, 0), updated_at = NOW()
             WHERE id = ?"
        )->execute([$balance, $balance, $balance, $id]);
    }
}
