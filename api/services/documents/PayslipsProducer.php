<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Modules\staff\StaffPayrollManager;
use App\API\Services\PrintService;
use PDO;

/**
 * Bulk API for staff payslips.
 *
 * Entities are per-staff payslip data assembled via
 * StaffPayrollManager::viewPayslip() for a given payroll period.
 * Each entity renders as a self-contained HTML document through the exact
 * shared template/CSS path used by the synchronous flow.
 */
final class PayslipsProducer implements DocumentBatchProducer
{
    private StaffPayrollManager $payroll;
    private PrintService $prints;

    public function __construct(?PDO $db = null, ?StaffPayrollManager $payroll = null, ?PrintService $prints = null)
    {
        $this->payroll = $payroll ?? new StaffPayrollManager();
        $this->prints = $prints ?? new PrintService();
    }

    public static function kind(): string
    {
        return 'payslips';
    }

    public static function label(): string
    {
        return 'Staff Payslips';
    }

    /**
     * @return array{payroll_month: int, payroll_year: int, filename: string}
     */
    public static function defaultOptions(): array
    {
        return [
            'payroll_month' => (int) date('m'),
            'payroll_year' => (int) date('Y'),
            'filename' => 'payslips',
        ];
    }

    public function resolveEntities(array $selection, array $options, array $context): array
    {
        $staffIds = array_values(array_map('intval', (array) ($selection['staff_ids'] ?? [])));
        $staffIds = array_values(array_unique(array_filter($staffIds, static fn (int $id): bool => $id > 0)));
        if ($staffIds === []) {
            return ['total' => 0, 'unit' => 'payslips', 'entities' => []];
        }

        $month = (int) ($options['payroll_month'] ?? date('m'));
        $year = (int) ($options['payroll_year'] ?? date('Y'));

        $records = [];
        foreach ($staffIds as $sid) {
            $res = $this->payroll->viewPayslip($sid, [
                'payroll_month' => $month,
                'payroll_year' => $year,
            ]);
            if (($res['success'] ?? false) !== false && !empty($res['data']['payslip'])) {
                $records[] = [
                    'payslip' => $res['data']['payslip'],
                    'allowances_breakdown' => $res['data']['allowances_breakdown'] ?? [],
                    'deductions_breakdown' => $res['data']['deductions_breakdown'] ?? [],
                ];
            }
        }

        return [
            'total' => count($records),
            'unit' => 'payslips',
            'entities' => $records,
        ];
    }

    public function renderHtml(array $entity, array $options, array $context): ?array
    {
        try {
            $html = $this->prints->buildPayslipHtml($entity, [
                'orientation' => 'portrait',
                'paperSize' => 'A4',
                'filename' => 'payslip',
            ]);
            return ['html' => $html];
        } catch (\Throwable $e) {
            \App\API\Includes\FileLogger::write('document_generation', [
                'type' => 'payslip_render_failed',
                'entity_id' => (string) ($entity['payslip']['id'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}