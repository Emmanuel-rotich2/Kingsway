<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use Throwable;
use App\API\Includes\FileLogger;
use App\API\Modules\communications\CommunicationsManager;
use App\API\Services\CommunicationTemplateRenderer;
use App\API\Services\PhoneNumberNormalizer;
use App\API\Services\ReadReplicaService;
use App\API\Services\TemplateManager;

/**
 * Queues the parental fee-receipt SMS after money has been credited to the
 * learner's fee ledger.
 *
 * Recipient rule (how the money moved decides who is told):
 *   - a prompt / Lipa na M-Pesa debited one specific line, so the receipt
 *     belongs to that number. The payer is notified first and the guardian
 *     only when the paying number is unknown.
 *   - a direct deposit / Lipa Karo / bank or cheque tells the household, so
 *     the guardian contacts on record lead and the payer is the fallback.
 *
 * The message states the learner, class, admission number, the year's total
 * fees, and the outstanding current-term and academic-year balances, so a
 * parent sees what was charged, what was paid and what remains.
 *
 * Balances come from the master views (vw_academic_term,
 * vw_student_fee_balances) so the current-term and academic-year figures
 * include the payment that triggered the message.
 *
 * Delivery is handed to the communications outbox; the payment path is
 * never blocked or failed by an SMS problem.
 */
class FeePaymentReceiptNotifier
{
    /** Catalogue entry that holds the payer-facing receipt wording. */
    public const TEMPLATE_CODE = 'sms.fee_receipt';

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
            $guardianPhones = array_values(array_filter((array) ($student['guardian_phones'] ?? [])));
            // A prompt debited one line: that number owns the receipt. A
            // deposit/lipa karo is a household payment: the contacts on record
            // are told and the payer is the fallback.
            $payerFirst = self::isPayerDirected($providerCode);
            $recipient = $payerFirst
                ? self::pickPhone([], $payerPhone) ?? self::pickPhone($guardianPhones, $payerPhone)
                : self::pickPhone($guardianPhones, $payerPhone) ?? self::pickPhone([], $payerPhone);
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

            $balances = $studentId > 0 ? $this->feeBalances($studentId) : [null, null, null, null];
            $message = $this->receiptBody(
                $amount,
                $student,
                $reference,
                $recipient['source'] === 'payer' ? $recipient['phone'] : null,
                $balances
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
     * True when the money left a specific M-Pesa line, so the receipt belongs
     * to whoever owned that line. This is about HOW it was paid, never about
     * which aggregator carried it — Buni, Daraja and PayHero STK are all a
     * prompt to one number, exactly as a bank deposit is not.
     */
    public static function isPayerDirected(string $providerCode): bool
    {
        // The payment channel identifies the rail in the callback payload:
        // safaricom_stk / kcb_buni_ipn / payhero_stk are all STK prompts.
        return in_array(strtolower(trim($providerCode)), ['safaricom_stk', 'kcb_buni_ipn', 'payhero_stk'], true);
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

    /**
     * The receipt a parent reads. States what was paid, for which learner and
     * class, what the year costs in total, and what is still owed — so the
     * message is an appreciation, not just a transaction echo.
     */
    /**
     * The receipt text. Rendered from the `sms.fee_receipt` template so an
     * operator can change the wording in the template catalogue; the inline
     * wording is only the fallback for a database where the template has not
     * been applied, so a payment is never left unsent over template data.
     */
    private function receiptBody(
        float $amount,
        array $student,
        string $reference,
        ?string $payerPhone,
        array $balances
    ): string {
        $termBalance = $balances[0] ?? null;
        $annualBalance = $balances[1] ?? null;
        $annualBilled = $balances[2] ?? null;
        $className = $balances[3] ?? null;

        $variables = [
            'amount_paid' => self::plainMoney($amount),
            'admission_no' => (string) ($student['admission_no'] ?? ''),
            'student_name' => (string) ($student['student_name'] ?? ''),
            'class_name' => $className !== null && trim((string) $className) !== '' ? trim((string) $className) : '-',
            'annual_fees' => $annualBilled !== null ? self::plainMoney($annualBilled) : '-',
            'term_balance' => $termBalance !== null ? self::plainMoney($termBalance) : '-',
            'year_balance' => $annualBalance !== null ? self::plainMoney($annualBalance) : '-',
            'reference' => trim($reference),
            'payer_name' => trim((string) ($student['payer_name'] ?? '')),
            'payer_phone' => $payerPhone !== null ? (string) $payerPhone : '',
        ];

        try {
            $template = (new TemplateManager($this->db))->getTemplateByCode(self::TEMPLATE_CODE);
            if ($template !== null) {
                foreach ($template['versions'] ?? [] as $version) {
                    if (($version['status'] ?? '') !== 'active') {
                        continue;
                    }
                    foreach ($version['channels'] ?? [] as $channel) {
                        if (($channel['channel'] ?? '') !== 'sms' || empty($channel['body'])) {
                            continue;
                        }
                        return (new CommunicationTemplateRenderer())->render((string) $channel['body'], $variables, 'sms');
                    }
                }
            }
        } catch (Throwable $error) {
            FileLogger::write('payments', [
                'type' => 'fee_receipt_sms',
                'status' => 'template_render_failed',
                'student_id' => $student['student_id'] ?? null,
                'error' => $error->getMessage(),
            ], 'error');
        }

        return self::composeMessage(
            $amount,
            (string) ($student['student_name'] ?? ''),
            (string) ($student['admission_no'] ?? ''),
            $reference,
            $termBalance,
            $annualBalance,
            $annualBilled,
            $className
        );
    }

    /** Amount without the currency prefix: the template carries "KES". */
    private static function plainMoney(float $value): string
    {
        return number_format($value, $value == floor($value) ? 0 : 2);
    }

    public static function composeMessage(
        float $amount,
        string $studentName,
        string $admissionNo,
        string $reference,
        ?float $termBalance,
        ?float $annualBalance,
        ?float $annualBilled = null,
        ?string $className = null
    ): string {
        $school = defined('SCHOOL_NAME') ? (string) SCHOOL_NAME : 'Kingsway Preparatory School';
        $learner = trim($admissionNo . ' ' . $studentName);
        if ($className !== null && trim($className) !== '') {
            $learner .= ' (' . trim($className) . ')';
        }
        $message = 'Thank you. ' . self::money($amount) . ' fee payment received';
        if ($learner !== '') {
            $message .= ' for ' . $learner;
        }
        $message .= '.';
        if ($annualBilled !== null) {
            $message .= ' Total fees this year: ' . self::money($annualBilled) . '.';
        }
        if ($termBalance !== null) {
            $message .= ' Term balance: ' . self::money($termBalance) . '.';
        }
        if ($annualBalance !== null) {
            $message .= ' Year balance: ' . self::money($annualBalance) . '.';
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
     * Current-term balance, current-year balance, the year's billed total and
     * the learner's class, read from the master views immediately after the
     * payment was credited.
     *
     * @return array{0: float|null, 1: float|null, 2: float|null, 3: string|null}
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
                return [null, null, null, null];
            }

            $term = $this->db->prepare(
                "SELECT balance FROM " . ReadReplicaService::masterSourceRef('student_fee_balances') . "
                  WHERE student_id = ? AND academic_year_term_id = ?
                  LIMIT 1"
            );
            $term->execute([$studentId, (int) $current['ayt_id']]);
            $termBalance = $term->fetchColumn();

            $year = $this->db->prepare(
                "SELECT SUM(balance) AS bal, SUM(amount_due) AS due
                   FROM " . ReadReplicaService::masterSourceRef('student_fee_balances') . "
                  WHERE student_id = ? AND academic_year = ?"
            );
            $year->execute([$studentId, (string) $current['ay_code']]);
            $yearRow = $year->fetch(PDO::FETCH_ASSOC) ?: [];

            $class = $this->db->prepare(
                "SELECT class_name FROM " . ReadReplicaService::masterSourceRef('student_directory') . "
                  WHERE student_id = ? ORDER BY academic_year_class_id DESC LIMIT 1"
            );
            $class->execute([$studentId]);
            $className = $class->fetchColumn();

            return [
                $termBalance !== false ? (float) $termBalance : null,
                isset($yearRow['bal']) ? (float) $yearRow['bal'] : null,
                !empty($yearRow['due']) ? (float) $yearRow['due'] : null,
                is_string($className) && trim($className) !== '' ? trim($className) : null,
            ];
        } catch (Throwable $error) {
            FileLogger::write('payments', [
                'type' => 'fee_receipt_sms',
                'status' => 'balance_lookup_failed',
                'student_id' => $studentId,
                'error' => $error->getMessage(),
            ], 'error');
            return [null, null, null, null];
        }
    }
}
