<?php
namespace App\API\Modules\admission;

use PDO;
use Exception;
use App\API\Services\FinancialPostingCoordinator;

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

        $method = $this->normalizePaymentMethod((string) ($paymentData['method'] ?? $paymentData['payment_method'] ?? ''));
        if (!in_array($method, ['mpesa', 'bank_transfer'], true)) {
            throw new Exception('Admission and school fees may only be paid through M-Pesa or bank transfer');
        }
        $referenceNo = trim((string) ($paymentData['reference'] ?? $paymentData['reference_no'] ?? $paymentData['transaction_reference'] ?? ''));
        if ($referenceNo === '') {
            throw new Exception('A bank or M-Pesa transaction reference is required');
        }
        $receiptDocumentId = (int) ($paymentData['receipt_document_id'] ?? 0);
        if ($method === 'bank_transfer') {
            if ($receiptDocumentId < 1) {
                throw new Exception('A physical bank payment receipt is required');
            }
            $receiptCheck = $this->db->prepare(
                "SELECT id FROM admission_documents
                 WHERE id = :id AND application_id = :application_id
                   AND document_type = 'payment_receipt'
                 LIMIT 1"
            );
            $receiptCheck->execute(['id' => $receiptDocumentId, 'application_id' => $applicationId]);
            if (!$receiptCheck->fetchColumn()) {
                throw new Exception('The bank payment receipt is not registered against this application');
            }
        }
        $duplicate = $this->db->prepare(
            "SELECT id FROM admission_payments WHERE reference_no = :reference_no LIMIT 1"
        );
        $duplicate->execute(['reference_no' => $referenceNo]);
        if ($duplicate->fetchColumn()) {
            throw new Exception('This payment reference has already been recorded');
        }

        $receiptNo = trim((string) ($paymentData['receipt_no'] ?? ''));
        if ($receiptNo === '') {
            $receiptNo = 'ADM-' . $applicationId . '-' . date('YmdHis');
        }

        $paymentDate = $paymentData['payment_date'] ?? date('Y-m-d H:i:s');
        $notes = (string) ($paymentData['notes'] ?? '');

        $sql = "INSERT INTO admission_payments (
                    application_id, amount, payment_method, reference_no, receipt_no,
                    financial_account_id, payment_date, notes, status, recorded_by, created_at
                ) VALUES (
                    :application_id, :amount, :payment_method, :reference_no, :receipt_no,
                    :financial_account_id, :payment_date, :notes, 'pending_verification', :recorded_by, NOW()
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'application_id' => $applicationId,
            'amount' => $amount,
            'payment_method' => $method,
            'reference_no' => $referenceNo,
            'receipt_no' => $receiptNo,
            'financial_account_id' => !empty($paymentData['financial_account_id']) ? (int) $paymentData['financial_account_id'] : null,
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
            'status' => 'pending_verification',
            'can_enroll' => false,
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
            if (!in_array(($payment['status'] ?? ''), ['recorded', 'posted'], true)) {
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
            $paymentResult = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();

            $studentPaymentId = (int) ($paymentResult['transaction_id'] ?? 0);
            if ($studentPaymentId && !empty($payment['financial_account_id'])) {
                $this->db->prepare('UPDATE payments SET financial_account_id=?, payment_purpose=\'fees\' WHERE id=?')->execute([(int) $payment['financial_account_id'], $studentPaymentId]);
                $allocations = $this->db->prepare(
                    "SELECT apa.amount, COALESCE(coa.account_code,'120001') AS account_code, ec.name AS description
                     FROM admission_payment_allocations apa
                     JOIN extra_charge_application_obligations eao ON eao.id=apa.application_obligation_id
                     JOIN extra_charges ec ON ec.id=eao.extra_charge_id
                     LEFT JOIN chart_of_accounts coa ON coa.id=ec.gl_account_id
                     WHERE apa.admission_payment_id=?"
                );
                $allocations->execute([(int) $payment['id']]);
                (new FinancialPostingCoordinator($this->db))->postIncomingToChargeAccounts(
                    'payment', $studentPaymentId, (int) $payment['financial_account_id'],
                    $allocations->fetchAll(PDO::FETCH_ASSOC), $userId, (string) ($payment['reference_no'] ?? '')
                );
            }

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

        $allowed = ['bank_transfer', 'mpesa'];
        return in_array($normalized, $allowed, true) ? $normalized : 'other';
    }
}
