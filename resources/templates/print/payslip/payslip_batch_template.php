<?php
/**
 * Batch Payslip Template
 *
 * This template is used for generating multiple payslips in a single PDF document.
 * Each payslip starts on a new page with proper page breaks.
 */

declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; }

if (!function_exists('pe')) { function pe(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('pm')) { function pm($v): string { return 'KES ' . number_format((float)($v ?? 0), 2); } }

$tplDir = __DIR__;
$cssText = trim((string) ($printStyles ?? ''));
$isBatch = $isBatch ?? false;
$batchIndex = $batchIndex ?? 0;
$batchTotal = $batchTotal ?? 1;
$period = $period ?? date('F Y');
$employeeName = trim((string)($employeeName ?? '')) ?: '-';
$staffNo = trim((string)($staffNo ?? '')) ?: '-';
$department = trim((string)($department ?? '')) ?: '-';
$designation = trim((string)($designation ?? '')) ?: '-';
$kraPin = trim((string)($kraPin ?? '')) ?: '-';
$nssfNo = trim((string)($nssfNo ?? '')) ?: '-';
$shifNo = trim((string)($shifNo ?? '')) ?: '-';
$bankName = trim((string)($bankName ?? '')) ?: '-';
$bankAccountNumber = trim((string)($bankAccountNumber ?? '')) ?: '-';
$paymentMethod = trim((string)($paymentMethod ?? '')) ?: 'Not Recorded';
$paymentReference = trim((string)($paymentReference ?? '')) ?: '-';
$datePaid = trim((string)($datePaid ?? '')) ?: 'Not Paid';
$basicSalary = $basicSalary ?? 0;
$allowances = $allowances ?? [];
$statutory = $statutory ?? ['paye'=>0,'nssf'=>0,'nhif_shif'=>0,'housing_levy'=>0];
$grossPay = $grossPay ?? 0;
$totalDeductions = $totalDeductions ?? 0;
$netPay = $netPay ?? 0;
$employerNssf = $employerNssf ?? 0;
$employerHousing = $employerHousing ?? 0;
$otherDeductions = isset($otherDeductions) ? (float)$otherDeductions : null;
$legacyDeductions = is_array($deductions ?? null) ? $deductions : [];
$childrenDeductions = is_array($childrenDeductions ?? null) ? $childrenDeductions : [];
$totalChildrenFees = 0.0;
foreach ($childrenDeductions as $child) {
    $totalChildrenFees += (float)($child['deducted_amount'] ?? $child['amount'] ?? 0);
}
$useSharedReportShell = false;

$statusKey = strtolower(trim((string)($status ?? '')));
$statusLabels = [
    'draft' => 'Draft', 'pending' => 'Pending', 'processing' => 'Processing',
    'approved' => 'Approved', 'paid' => 'Paid', 'cancelled' => 'Cancelled',
];
$statusLabel = $statusLabels[$statusKey] ?? ucfirst($statusKey !== '' ? $statusKey : 'unknown');
$badgeClassMap = [
    'draft' => 'b-pending', 'pending' => 'b-pending',
    'processing' => 'b-processing', 'approved' => 'b-processing',
    'paid' => 'b-paid', 'cancelled' => 'b-cancelled',
];
$badgeClass = $badgeClassMap[$statusKey] ?? 'b-pending';

$generatedOn = trim((string)($generatedOn ?? '')) ?: date('d/m/Y');

if (!function_exists('fsMoney')) {
    function fsMoney($amount) { return number_format((float) $amount); }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?> — Payslip</title>
    <style><?= $cssText ?></style>
    <style>
        /* Batch-specific styles */
        .fs-document {
            page-break-after: always;
        }
        .fs-document:last-child {
            page-break-after: auto;
        }
        @media print {
            .fs-document {
                page-break-after: always;
            }
            .fs-document:last-child {
                page-break-after: avoid;
            }
        }
        .batch-footer {
            text-align: center;
            font-size: 8pt;
            color: #666;
            margin-top: 1rem;
            padding-top: 0.5rem;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
<div class="fs-document">
    <div class="fs-inner-border">
        <?php include $tplDir . '/payslip_watermark.php'; ?>

        <div class="fs-content-wrap">
            <?php include $tplDir . '/payslip_header.php'; ?>

            <main class="fs-content">
                <div class="payslip-container" id="payslipPrintArea">

                    <table class="ps-grid">
                        <tr>
                            <td class="ps-grid-cell left">
                                <table class="ps-info-table">
                                    <tr><td class="lbl ps-bold">Employee Name:</td><td><?php echo pe($employeeName) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Staff Number:</td><td><?php echo pe($staffNo) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Position:</td><td><?php echo pe($designation) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Department:</td><td><?php echo pe($department) ?></td></tr>
                                    <tr><td class="lbl ps-bold">KRA PIN:</td><td><?php echo pe($kraPin) ?></td></tr>
                                    <tr><td class="lbl ps-bold">NSSF No:</td><td><?php echo pe($nssfNo) ?></td></tr>
                                    <tr><td class="lbl ps-bold">SHIF No:</td><td><?php echo pe($shifNo) ?></td></tr>
                                </table>
                            </td>
                            <td class="ps-grid-cell right">
                                <table class="ps-info-table">
                                    <tr><td class="lbl ps-bold">Bank:</td><td><?php echo pe($bankName) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Account Number:</td><td><?php echo pe($bankAccountNumber) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Payment Mode:</td><td><?php echo pe($paymentMethod) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Payment Ref:</td><td><?php echo pe($paymentReference) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Date Paid:</td><td><?php echo pe($datePaid) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Pay Period:</td><td><?php echo pe($period) ?></td></tr>
                                    <tr><td class="lbl ps-bold">Status:</td><td><span class="badge-pill <?php echo $badgeClass ?>"><?php echo pe($statusLabel) ?></span></td></tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <table class="ps-grid ps-mt4">
                        <tr>
                            <td class="ps-grid-cell left">
                                <div class="ps-h6 ps-success"><span class="icn">+</span>EARNINGS</div>
                                <table class="ps-table">
                                    <tr>
                                        <td>Basic Salary</td>
                                        <td class="amt"><?php echo pm($basicSalary) ?></td>
                                    </tr>
                                    <?php foreach ($allowances as $a): ?>
                                    <tr>
                                        <td><?php echo pe($a['name'] ?? '') ?></td>
                                        <td class="amt"><?php echo pm($a['amount'] ?? 0) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="ctx-success">
                                        <td class="ps-bold">Gross Salary</td>
                                        <td class="amt ps-bold"><?php echo pm($grossPay) ?></td>
                                    </tr>
                                </table>
                            </td>
                            <td class="ps-grid-cell right">
                                <div class="ps-h6 ps-danger"><span class="icn">&minus;</span>DEDUCTIONS</div>
                                <table class="ps-table">
                                    <tr>
                                        <td>NSSF</td>
                                        <td class="amt"><?php echo pm($statutory['nssf'] ?? 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>SHIF</td>
                                        <td class="amt"><?php echo pm($statutory['nhif_shif'] ?? 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>PAYE Tax</td>
                                        <td class="amt"><?php echo pm($statutory['paye'] ?? 0) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Employee Housing Levy (1.5%)</td>
                                        <td class="amt"><?php echo pm($statutory['housing_levy'] ?? 0) ?></td>
                                    </tr>
                                    <?php
                                    $db = $deduction_breakdown ?? [];
                                    if (!empty($db)) {
                                        if (($db['loan'] ?? 0) > 0) { ?>
                                            <tr>
                                                <td>Loan Deduction</td>
                                                <td class="amt"><?php echo pm($db['loan']) ?></td>
                                            </tr>
                                        <?php }
                                        if (($db['sacco'] ?? 0) > 0) { ?>
                                            <tr>
                                                <td>SACCO Contribution</td>
                                                <td class="amt"><?php echo pm($db['sacco']) ?></td>
                                            </tr>
                                        <?php }
                                        if (($db['salary_advance'] ?? 0) > 0) { ?>
                                            <tr>
                                                <td>Salary Advance Recovery</td>
                                                <td class="amt"><?php echo pm($db['salary_advance']) ?></td>
                                            </tr>
                                        <?php }
                                    }
                                    ?>
                                    <?php if (count($childrenDeductions) > 0): ?>
                                    <tr class="ctx-warning">
                                        <td colspan="2"><strong>Children School Fees Deductions</strong></td>
                                    </tr>
                                    <?php foreach ($childrenDeductions as $child): ?>
                                    <?php
                                        $childName = trim((string)($child['student_name'] ?? $child['name'] ?? 'Child'));
                                        $childClass = trim((string)($child['class_name'] ?? ''));
                                        $childAmount = (float)($child['deducted_amount'] ?? $child['amount'] ?? 0);
                                    ?>
                                    <tr>
                                        <td class="child-name"><span class="ps-small"><?php echo pe($childName) ?><?php echo $childClass !== '' ? ' (' . pe($childClass) . ')' : '' ?></span></td>
                                        <td class="amt"><?php echo pm($childAmount) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($totalChildrenFees > 0): ?>
                                    <tr class="ctx-warning">
                                        <td class="ps-bold">Total Children Fees</td>
                                        <td class="amt ps-bold"><?php echo pm($totalChildrenFees) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($otherDeductions !== null && $otherDeductions > 0): ?>
                                    <tr>
                                        <td>Other Deductions</td>
                                        <td class="amt"><?php echo pm($otherDeductions) ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="ctx-danger">
                                        <td class="ps-bold">Total Deductions</td>
                                        <td class="amt ps-bold"><?php echo pm($totalDeductions) ?></td>
                                    </tr>
                                </table>

                                <div class="ps-h6 ps-secondary ps-mt3"><span class="icn">&#9642;</span>EMPLOYER CONTRIBUTIONS</div>
                                <table class="ps-table">
                                    <tr>
                                        <td>Employer NSSF (school cost)</td>
                                        <td class="amt"><?php echo pm($employerNssf) ?></td>
                                    </tr>
                                    <tr>
                                        <td>Employer Housing Levy (school cost)</td>
                                        <td class="amt"><?php echo pm($employerHousing) ?></td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    <div class="net-card">
                        <div class="ps-h5">NET PAY</div>
                        <div class="ps-h2"><?php echo pm($netPay) ?></div>
                    </div>

                    <div class="gen-note">
                        <p>This is a computer generated payslip and does not require a signature.</p>
                        <p>Generated on <?php echo pe($generatedOn) ?></p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ROOT LEVEL FOOTER -->
    <?php include $tplDir . '/payslip_page_footer.php'; ?>

    <?php if ($isBatch && $batchTotal > 1): ?>
    <div class="batch-footer">
        Payslip <?php echo (int)$batchIndex + 1; ?> of <?php echo (int)$batchTotal; ?> &mdash; Generated on <?php echo pe($generatedOn) ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>