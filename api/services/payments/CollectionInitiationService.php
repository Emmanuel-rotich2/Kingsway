<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\ReadReplicaService;
use PDO;
use RuntimeException;

/**
 * Purpose-aware mobile-money initiation for fees, transport and uniforms.
 *
 * The frontend sends one payload (student, purpose, amount, phone). This
 * service resolves the correct reference and ledger:
 *   - fees       -> learner admission number (bare C2B/STK reference)
 *   - transport  -> active transport entitlement + TRN-… reference
 *   - uniforms   -> outstanding sale/accumulated balance + U-/UC-… reference
 *
 * STK failover (PayHero primary, Daraja Express fallback) is delegated to
 * CollectionFallbackService / TransportPaymentService / UniformPaymentService;
 * this class only validates the learner-facing amount and picks the reference.
 */
final class CollectionInitiationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array{student_id?:int, purpose?:string, amount?:float|int|string,
     *              phone?:string, sale_id?:int, entitlement_id?:int,
     *              parent_id?:int, financial_account_id?:int,
     *              customer_name?:string, description?:string} $data
     * @return array<string, mixed>
     */
    public function initiate(array $data, int $actorUserId = 0): array
    {
        $studentId = (int) ($data['student_id'] ?? 0);
        $purpose = strtolower(trim((string) ($data['purpose'] ?? 'fees')));
        $amount = (float) ($data['amount'] ?? 0);
        $phone = $this->canonicalPhone((string) ($data['phone'] ?? $data['phone_number'] ?? ''));

        if ($studentId <= 0) {
            throw new RuntimeException('student_id is required.');
        }
        if (!in_array($purpose, ['fees', 'transport', 'uniforms'], true)) {
            throw new RuntimeException('purpose must be fees, transport or uniforms.');
        }
        if ($phone === null) {
            throw new RuntimeException('A valid phone number is required (2547XXXXXXXX).');
        }
        if ($amount <= 0) {
            throw new RuntimeException('Amount must be greater than zero.');
        }

        $student = $this->student($studentId);
        if (!$student) {
            throw new RuntimeException('Active student not found.');
        }

        return match ($purpose) {
            'fees' => $this->fees($student, $amount, $phone, $data),
            'transport' => $this->transport($student, $amount, $phone, $data, $actorUserId),
            'uniforms' => $this->uniforms($student, $amount, $phone, $data, $actorUserId),
        };
    }

    /** @return array<string, mixed> */
    private function fees(array $student, float $amount, string $phone, array $data): array
    {
        $balance = $this->feeBalance((int) $student['id']);
        if ($balance <= 0) {
            throw new RuntimeException('No outstanding fee balance to pay.');
        }
        if ($amount > $balance + 0.01) {
            throw new RuntimeException('Amount exceeds the outstanding fee balance.');
        }

        $reference = (string) $student['admission_no'];
        $result = (new CollectionFallbackService($this->db))->initiate('fees', [
            'amount' => $amount,
            'phone' => $phone,
            'reference' => $reference,
            'customer_name' => (string) ($data['customer_name'] ?? $student['student_name'] ?? ''),
            'description' => (string) ($data['description'] ?? 'School fees'),
            'financial_account_id' => (int) ($data['financial_account_id'] ?? 0),
        ]);
        if (empty($result['accepted'])) {
            throw new RuntimeException($result['message'] ?: 'No configured collection rail accepted the fee payment.');
        }

        return $this->accepted($result, 'fees', $reference, $amount, (int) $student['id']);
    }

    /** @return array<string, mixed> */
    private function transport(array $student, float $amount, string $phone, array $data, int $actorUserId): array
    {
        $entitlementId = (int) ($data['entitlement_id'] ?? 0);
        $entitlement = $entitlementId > 0
            ? $this->transportEntitlementById($entitlementId, (int) $student['id'])
            : $this->activeTransportEntitlement((int) $student['id']);
        if (!$entitlement) {
            throw new RuntimeException('No active transport entitlement found for this learner.');
        }
        $outstanding = max(0.0, (float) $entitlement['amount_due'] - (float) $entitlement['paid_amount']);
        if ($outstanding <= 0) {
            throw new RuntimeException('No outstanding transport balance to pay.');
        }
        if ($amount > $outstanding + 0.01) {
            throw new RuntimeException('Amount exceeds the outstanding transport balance.');
        }

        $intent = (new TransportPaymentService($this->db))->initiate([
            'entitlement_id' => (int) $entitlement['id'],
            'amount' => $amount,
            'phone_number' => $phone,
            'channel' => 'auto',
            'financial_account_id' => (int) ($data['financial_account_id'] ?? 0),
        ], $actorUserId);
        $result = $this->intentResult($intent, 'transport', $amount, (int) $student['id']);

        return $result;
    }

    /** @return array<string, mixed> */
    private function uniforms(array $student, float $amount, string $phone, array $data, int $actorUserId): array
    {
        $service = new UniformPaymentService($this->db);
        $saleId = (int) ($data['sale_id'] ?? 0);
        $parentId = (int) ($data['parent_id'] ?? 0) ?: $this->primaryParentId((int) $student['id']);

        if ($saleId > 0) {
            $sale = $this->uniformSale($saleId, (int) $student['id']);
            if (!$sale) {
                throw new RuntimeException('Outstanding uniform sale not found for this learner.');
            }
            $outstanding = max(0.0, (float) $sale['total_amount'] - (float) $sale['paid_amount']);
            if ($outstanding <= 0) {
                throw new RuntimeException('This uniform sale is already fully paid.');
            }
            if ($amount > $outstanding + 0.01) {
                throw new RuntimeException('Amount exceeds the outstanding balance for this uniform sale.');
            }
            $intent = $service->initiate([
                'sale_id' => $saleId,
                'amount' => $amount,
                'channel' => 'auto',
                'phone' => $phone,
                'financial_account_id' => (int) ($data['financial_account_id'] ?? 0),
            ], $actorUserId);
        } elseif ($parentId > 0) {
            $outstanding = $this->uniformOutstanding((int) $student['id']);
            if ($outstanding <= 0) {
                throw new RuntimeException('No outstanding uniform balance to pay.');
            }
            if ($amount > $outstanding + 0.01) {
                throw new RuntimeException('Amount exceeds the accumulated uniform balance.');
            }
            $intent = $service->initiateAccumulated([
                'student_id' => (int) $student['id'],
                'parent_id' => $parentId,
                'amount' => $amount,
                'channel' => 'auto',
                'phone' => $phone,
                'financial_account_id' => (int) ($data['financial_account_id'] ?? 0),
            ], $actorUserId);
        } else {
            $sale = $this->oldestUniformSale((int) $student['id']);
            if (!$sale) {
                throw new RuntimeException('No outstanding uniform balance to pay.');
            }
            $outstanding = max(0.0, (float) $sale['total_amount'] - (float) $sale['paid_amount']);
            if ($amount > $outstanding + 0.01) {
                throw new RuntimeException('Amount exceeds the outstanding balance for the oldest uniform sale.');
            }
            $intent = $service->initiate([
                'sale_id' => (int) $sale['id'],
                'amount' => $amount,
                'channel' => 'auto',
                'phone' => $phone,
                'financial_account_id' => (int) ($data['financial_account_id'] ?? 0),
            ], $actorUserId);
        }

        return $this->intentResult($intent, 'uniforms', $amount, (int) $student['id']);
    }

    /** @return array<string, mixed> */
    private function accepted(array $result, string $purpose, string $reference, float $amount, int $studentId): array
    {
        return [
            'status' => 'pending',
            'accepted' => true,
            'purpose' => $purpose,
            'student_id' => $studentId,
            'amount' => $amount,
            'reference' => $reference,
            'provider_code' => $result['provider_code'] ?? null,
            'channel' => $result['channel'] ?? null,
            'checkout_request_id' => $result['request_id'] ?? null,
            'fallback_used' => !empty($result['fallback_used']),
            'message' => (string) ($result['message'] ?? 'Payment prompt sent.'),
            'attempts' => $result['attempts'] ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function intentResult(array $intent, string $purpose, float $amount, int $studentId): array
    {
        $providerCode = match ((string) ($intent['channel'] ?? '')) {
            'payhero_mpesa' => 'payhero',
            'daraja_mpesa' => 'safaricom_daraja',
            'buni_mpesa' => 'kcb_buni',
            default => (string) ($intent['channel'] ?? ''),
        };

        return [
            'status' => 'pending',
            'accepted' => true,
            'purpose' => $purpose,
            'student_id' => $studentId,
            'amount' => $amount,
            'reference' => (string) ($intent['idempotency_reference'] ?? ''),
            'provider_code' => $providerCode,
            'channel' => (string) ($intent['channel'] ?? ''),
            'checkout_request_id' => $intent['provider_request_id'] ?? null,
            'intent_id' => (int) ($intent['id'] ?? 0),
            'fallback_used' => false,
            'message' => 'Payment prompt sent.',
            'intent' => $intent,
        ];
    }

    private function student(int $studentId): ?array
    {
        $s = $this->db->prepare(
            "SELECT s.id, s.admission_no, TRIM(CONCAT(COALESCE(per.first_name,''),' ',COALESCE(per.last_name,''))) AS student_name
               FROM students s
               LEFT JOIN persons per ON per.id=s.person_id
              WHERE s.id=? AND s.status IN ('active','enrolled') LIMIT 1"
        );
        $s->execute([$studentId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function feeBalance(int $studentId): float
    {
        $view = ReadReplicaService::qualifiedRef('student_fee_balances');
        $s = $this->db->prepare("SELECT COALESCE(SUM(balance),0) FROM {$view} WHERE student_id=?");
        $s->execute([$studentId]);

        return (float) $s->fetchColumn();
    }

    private function activeTransportEntitlement(int $studentId): ?array
    {
        $s = $this->db->prepare(
            "SELECT e.id, e.student_id, e.amount_due,
                    COALESCE((
                        SELECT SUM(a.amount)
                          FROM transport_entitlement_payment_allocations a
                          JOIN transport_entitlement_payments tp ON tp.id=a.payment_id
                         WHERE a.entitlement_id=e.id AND tp.payment_status='confirmed'
                    ),0) AS paid_amount
               FROM student_transport_entitlements e
               JOIN transport_entitlement_periods p ON p.id=e.period_id
              WHERE e.student_id=? AND e.entitlement_status='active'
                AND p.period_start<=CURDATE() AND p.period_end>=CURDATE()
              ORDER BY DATEDIFF(p.period_end,p.period_start) ASC, e.id DESC
              LIMIT 1"
        );
        $s->execute([$studentId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function transportEntitlementById(int $entitlementId, int $studentId): ?array
    {
        $s = $this->db->prepare(
            "SELECT e.id, e.student_id, e.amount_due,
                    COALESCE((
                        SELECT SUM(a.amount)
                          FROM transport_entitlement_payment_allocations a
                          JOIN transport_entitlement_payments tp ON tp.id=a.payment_id
                         WHERE a.entitlement_id=e.id AND tp.payment_status='confirmed'
                    ),0) AS paid_amount
               FROM student_transport_entitlements e
              WHERE e.id=? AND e.student_id=? AND e.entitlement_status='active'
              LIMIT 1"
        );
        $s->execute([$entitlementId, $studentId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function uniformSale(int $saleId, int $studentId): ?array
    {
        $s = $this->db->prepare(
            "SELECT us.*, us.quantity*us.unit_price AS total_amount,
                    COALESCE((SELECT SUM(upr.amount) FROM uniform_payment_records upr WHERE upr.sale_id=us.id),0) AS paid_amount
               FROM uniform_sales us
              WHERE us.id=? AND us.student_id=? AND us.payment_status IN ('pending','partial')
              LIMIT 1"
        );
        $s->execute([$saleId, $studentId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function oldestUniformSale(int $studentId): ?array
    {
        $s = $this->db->prepare(
            "SELECT us.*, us.quantity*us.unit_price AS total_amount,
                    COALESCE((SELECT SUM(upr.amount) FROM uniform_payment_records upr WHERE upr.sale_id=us.id),0) AS paid_amount
               FROM uniform_sales us
              WHERE us.student_id=? AND us.payment_status IN ('pending','partial')
              ORDER BY us.sale_date ASC, us.id ASC LIMIT 1"
        );
        $s->execute([$studentId]);
        $row = $s->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function primaryParentId(int $studentId): int
    {
        $s = $this->db->prepare(
            'SELECT parent_id FROM student_parents WHERE student_id=? ORDER BY is_primary_contact DESC, parent_id ASC LIMIT 1'
        );
        $s->execute([$studentId]);

        return (int) $s->fetchColumn();
    }

    private function uniformOutstanding(int $studentId): float
    {
        $s = $this->db->prepare(
            "SELECT COALESCE(SUM(GREATEST(0, us.quantity*us.unit_price - COALESCE((
                        SELECT SUM(upr.amount) FROM uniform_payment_records upr WHERE upr.sale_id=us.id
                    ),0))),0)
               FROM uniform_sales us
              WHERE us.student_id=? AND us.payment_status IN ('pending','partial')"
        );
        $s->execute([$studentId]);

        return (float) $s->fetchColumn();
    }

    private function canonicalPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $digits = '254' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
            $digits = '254' . $digits;
        }

        return preg_match('/^254[17][0-9]{8}$/', $digits) ? $digits : null;
    }
}
