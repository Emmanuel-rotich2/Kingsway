<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use Throwable;
use App\API\Includes\FileLogger;
use App\API\Modules\communications\CommunicationsManager;
use App\API\Services\PhoneNumberNormalizer;
use App\API\Services\ReadReplicaService;

/**
 * Queues the parental fee-receipt SMS after money has been credited to the
 * learner's fee ledger.
 *
 * - Guardian-first: the primary contact on record gets the message; the payer
 *   phone is only used when no guardian phone can be resolved.
 * - Balances come from the master views (vw_academic_term,
 *   vw_student_fee_balances) so the current-term and academic-year figures
 *   include the payment that triggered the message.
 * - Delivery is handed to the communications outbox; the payment path is
 *   never blocked or failed by an SMS problem.
 */
class FeePaymentReceiptNotifier
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function notify(
        int $studentId,
        float $amount,
        string $reference,
        string $providerCode,
        string $providerTransactionId = '',
        ?string $payerPhone = null
    ): bool {
        try {
            if ($amount <= 0) {
                return false;
            }

            $subject = self::receiptSubject($providerTransactionId, $reference, $studentId);
            if ($this->alreadyQueued($subject)) {
                return false;
            }

            $student = $studentId > 0 ? $this->studentContext($studentId) : null;
            $recipient = self::pickPhone(
                array_values(array_filter((array) ($student['guardian_phones'] ?? []))),
                $payerPhone
            );
            if ($recipient === null) {
                FileLogger::write('payments', [
                    'type' => 'fee_receipt_sms',
                    'status' => 'skipped',
                    'reason' => 'no valid guardian or payer phone',
                    'student_id' => $studentId,
                    'reference' => $reference,
                    'provider_code' => $providerCode,
                ], 'warning');
                return false;
            }

            $balances = $studentId > 0 ? $this->feeBalances($studentId) : [null, null];
            $message = self::composeMessage(
                $amount,
                (string) ($student['student_name'] ?? ''),
                (string) ($student['admission_no'] ?? ''),
                $reference,
                $balances[0],
                $balances[1]
            );

            $communication = (new CommunicationsManager($this->db))->createCommunication([
                'sender_id' => 1,
                'subject' => $subject,
                'body' => $message,
                'type' => 'sms',
                'status' => 'sent',
                'priority' => 'high',
                'recipients' => [$recipient['phone']],
            ]);
            $queued = !empty($communication['id']);

            FileLogger::write('payments', [
                'type' => 'fee_receipt_sms',
                'status' => $queued ? 'queued' : 'failed',
                'recipient' => $recipient['source'],
                'phone' => $recipient['phone'],
                'student_id' => $studentId,
                'amount' => $amount,
                'reference' => $reference,
                'provider_code' => $providerCode,
                'provider_transaction_id' => $providerTransactionId,
                'term_balance' => $balances[0],
                'annual_balance' => $balances[1],
                'message' => $message,
            ], $queued ? 'info' : 'error');

            return $queued;
        } catch (Throwable $error) {
            FileLogger::write('payments', [
                'type' => 'fee_receipt_sms',
                'status' => 'failed',
                'student_id' => $studentId,
                'reference' => $reference,
                'provider_code' => $providerCode,
                'error' => $error->getMessage(),
            ], 'error');
            return false;
        }
    }

    public static function receiptSubject(string $providerTransactionId, string $reference, int $studentId): string
    {
        $identity = trim($providerTransactionId);
        if ($identity === '') {
            $identity = trim($reference);
        }
        if ($identity === '') {
            $identity = 'student-' . $studentId;
        }
        return 'Fee receipt ' . $identity;
    }

    /**
     * Guardian phones win (already ordered primary-first); the payer is the
     * fallback. Returns the chosen phone and where it came from.
     *
     * @param string[] $guardianPhones
     * @return array{phone: string, source: string}|null
     */
    public static function pickPhone(array $guardianPhones, ?string $payerPhone): ?array
    {
        foreach ($guardianPhones as $candidate) {
            $normalized = PhoneNumberNormalizer::normalize((string) $candidate);
            if ($normalized !== null) {
                return ['phone' => $normalized, 'source' => 'guardian'];
            }
        }
        $normalizedPayer = PhoneNumberNormalizer::normalize($payerPhone);
        if ($normalizedPayer !== null) {
            return ['phone' => $normalizedPayer, 'source' => 'payer'];
        }
        return null;
    }

    public static function composeMessage(
        float $amount,
        string $studentName,
        string $admissionNo,
        string $reference,
        ?float $termBalance,
        ?float $annualBalance
    ): string {
        $school = defined('SCHOOL_NAME') ? (string) SCHOOL_NAME : 'Kingsway Preparatory School';
        $learner = trim($admissionNo . ' ' . $studentName);
        $message = self::money($amount) . ' fee payment received';
        if ($learner !== '') {
            $message .= ' for ' . $learner;
        }
        $message .= '.';
        if ($termBalance !== null) {
            $message .= ' Term bal: ' . self::money($termBalance) . '.';
        }
        if ($annualBalance !== null) {
            $message .= ' Year bal: ' . self::money($annualBalance) . '.';
        }
        $reference = trim($reference);
        if ($reference !== '') {
            $message .= ' Ref: ' . $reference . '.';
        }
        return $message . ' -' . $school;
    }

    public static function money(float $value): string
    {
        return 'Ksh ' . number_format($value, $value == floor($value) ? 0 : 2);
    }

    private function alreadyQueued(string $subject): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM communications
              WHERE subject = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 2 DAY)
              LIMIT 1"
        );
        $stmt->execute([$subject]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array{student_name: string, admission_no: string, guardian_phones: string[]} */
    private function studentContext(int $studentId): array
    {
        $guardians = $this->db->prepare(
            "SELECT parent_phone, student_full_name, admission_no
               FROM " . ReadReplicaService::masterSourceRef('guardian_link') . "
              WHERE student_id = ?
              ORDER BY is_primary_contact DESC, parent_id ASC
              LIMIT 10"
        );
        $guardians->execute([$studentId]);
        $rows = $guardians->fetchAll(PDO::FETCH_ASSOC);

        $phones = [];
        foreach ($rows as $row) {
            $phone = trim((string) ($row['parent_phone'] ?? ''));
            if ($phone !== '') {
                $phones[] = $phone;
            }
        }

        return [
            'student_name' => trim((string) ($rows[0]['student_full_name'] ?? '')),
            'admission_no' => trim((string) ($rows[0]['admission_no'] ?? '')),
            'guardian_phones' => $phones,
        ];
    }

    /**
     * Current-term balance and current-academic-year balance, read from the
     * master views immediately after the payment was credited.
     *
     * @return array{0: float|null, 1: float|null}
     */
    private function feeBalances(int $studentId): array
    {
        try {
            $current = $this->db->query(
                "SELECT academic_year_term_id AS ayt_id, year_code AS ay_code
                   FROM " . ReadReplicaService::masterSourceRef('academic_term') . "
                  WHERE is_current_year = 1 AND term_period_status = 'current'
                  LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                return [null, null];
            }

            $term = $this->db->prepare(
                "SELECT balance FROM " . ReadReplicaService::masterSourceRef('student_fee_balances') . "
                  WHERE student_id = ? AND academic_year_term_id = ?
                  LIMIT 1"
            );
            $term->execute([$studentId, (int) $current['ayt_id']]);
            $termBalance = $term->fetchColumn();

            $annual = $this->db->prepare(
                "SELECT SUM(balance) FROM " . ReadReplicaService::masterSourceRef('student_fee_balances') . "
                  WHERE student_id = ? AND academic_year = ?"
            );
            $annual->execute([$studentId, (string) $current['ay_code']]);
            $annualBalance = $annual->fetchColumn();

            return [
                $termBalance !== false ? (float) $termBalance : null,
                $annualBalance !== false ? (float) $annualBalance : null,
            ];
        } catch (Throwable $error) {
            FileLogger::write('payments', [
                'type' => 'fee_receipt_sms',
                'status' => 'balance_lookup_failed',
                'student_id' => $studentId,
                'error' => $error->getMessage(),
            ], 'error');
            return [null, null];
        }
    }
}
