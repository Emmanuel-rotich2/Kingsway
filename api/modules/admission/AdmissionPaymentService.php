<?php
namespace App\API\Modules\admission;

use PDO;
use Exception;

class AdmissionPaymentService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function recordApplicationPayment(int $applicationId, array $paymentData, int $userId): array
    {
        $amount = isset($paymentData['amount']) ? (float) $paymentData['amount'] : 0.0;
        if ($amount <= 0) {
            throw new Exception('Payment amount must be greater than zero');
        }

        $method = $this->normalizePaymentMethod((string) ($paymentData['method'] ?? $paymentData['payment_method'] ?? 'cash'));
        $referenceNo = trim((string) ($paymentData['reference'] ?? $paymentData['reference_no'] ?? $paymentData['transaction_reference'] ?? ''));
        if ($referenceNo === '') {
            $referenceNo = 'ADM-' . $applicationId . '-' . date('YmdHis');
        }

        $receiptNo = trim((string) ($paymentData['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            $receiptNo = 'ADM-' . $applicationId . '-' . date('YmdHis');
        }

        $paymentDate = $paymentData['payment_date'] ?? date('Y-m-d H:i:s');
        $notes = (string) ($paymentData['notes'] ?? '');

        $sql = "INSERT INTO admission_payments (
                    application_id, amount, payment_method, reference_no, receipt_no,
                    payment_date, notes, status, recorded_by, created_at
                ) VALUES (
                    :application_id, :amount, :payment_method, :reference_no, :receipt_no,
                    :payment_date, :notes, 'recorded', :recorded_by, NOW()
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'application_id' => $applicationId,
            'amount' => $amount,
            'payment_method' => $method,
            'reference_no' => $referenceNo,
            'receipt_no' => $receiptNo,
            'payment_date' => $paymentDate,
            'notes' => $notes,
            'recorded_by' => $userId,
        ]);

        return [
            'payment_id' => (int) $this->db->lastInsertId(),
            'amount' => $amount,
            'payment_method' => $method,
            'reference_no' => $referenceNo,
            'receipt_no' => $receiptNo,
            'payment_date' => $paymentDate,
            'can_enroll' => true,
        ];
    }

    public function hasPositivePayment(int $applicationId): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM admission_payments WHERE application_id = :application_id AND amount > 0 AND status IN ('recorded', 'posted')");
        $stmt->execute(['application_id' => $applicationId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function getPaymentsForApplication(int $applicationId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM admission_payments WHERE application_id = :application_id ORDER BY payment_date DESC, id DESC");
        $stmt->execute(['application_id' => $applicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getTotalRecorded(int $applicationId): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM admission_payments WHERE application_id = :application_id AND status IN ('recorded', 'posted')");
        $stmt->execute(['application_id' => $applicationId]);
        return (float) $stmt->fetchColumn();
    }

    public function postApplicationPaymentsToStudent(int $applicationId, int $studentId, ?int $parentId, int $userId, string $applicationNo = ''): int
    {
        $payments = $this->getPaymentsForApplication($applicationId);
        $posted = 0;
        $suffix = $applicationNo !== '' ? " ({$applicationNo})" : '';

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'posted') {
                continue;
            }

            $amount = (float) ($payment['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $notes = trim((string) ($payment['notes'] ?? ''));
            if ($notes !== '') {
                $notes .= ' | ';
            }
            $notes .= 'Admission pre-enrollment payment posted after enrollment' . $suffix;

            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(
                    :student_id,
                    :parent_id,
                    :amount_paid,
                    :payment_method,
                    :reference_no,
                    :receipt_no,
                    :received_by,
                    :payment_date,
                    :notes
                )
            ");
            $stmt->execute([
                'student_id' => $studentId,
                'parent_id' => $parentId,
                'amount_paid' => $amount,
                'payment_method' => $this->normalizePaymentMethod((string) ($payment['payment_method'] ?? 'cash')),
                'reference_no' => (string) ($payment['reference_no'] ?? ''),
                'receipt_no' => (string) ($payment['receipt_no'] ?? ''),
                'received_by' => (int) ($payment['recorded_by'] ?? $userId),
                'payment_date' => $payment['payment_date'] ?? date('Y-m-d H:i:s'),
                'notes' => $notes,
            ]);
            $stmt->closeCursor();

            $update = $this->db->prepare("UPDATE admission_payments SET student_id = :student_id, status = 'posted', posted_at = NOW(), updated_at = NOW() WHERE id = :id");
            $update->execute([
                'student_id' => $studentId,
                'id' => (int) $payment['id'],
            ]);
            $posted++;
        }

        return $posted;
    }

    private function normalizePaymentMethod(string $method): string
    {
        $normalized = strtolower(trim($method));
        if ($normalized === 'bank' || $normalized === 'bank transfer') {
            return 'bank_transfer';
        }

        $allowed = ['cash', 'bank_transfer', 'mpesa', 'cheque', 'other'];
        return in_array($normalized, $allowed, true) ? $normalized : 'other';
    }
}
