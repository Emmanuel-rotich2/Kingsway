<?php
/**
 * Fee statement — Dompdf printable template.
 *
 * Renders statements built by App\API\Services\FeeStatementService:
 *   $statements    list of learner statements (one page each), or
 *   $statement     a single learner statement
 *   $summaryTable  optional first page: ['title', 'subtitle', 'rows', 'totals']
 *                  (class or school summary)
 *
 * Every learner page opens with what was brought forward into the year and
 * closes with a balance that equals its own lines.
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('se')) {
    function se(mixed $v): string {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('sm')) {
    function sm($v): string {
        return number_format((float)($v ?? 0), 2);
    }
}
if (!function_exists('fs_balance')) {
    /** Balances below zero are credit: show "CR" rather than a minus sign. */
    function fs_balance($v): string {
        $n = (float)($v ?? 0);
        return $n < 0 ? number_format(-$n, 2) . ' CR' : number_format($n, 2);
    }
}

$statements = $statements ?? (isset($statement) ? [$statement] : []);
$summaryTable = $summaryTable ?? null;
$useSharedReportShell = $useSharedReportShell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 12mm 15mm; }
* { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8.5pt; color: #17202a; background: #ffffff; }

.stmt-page { page-break-after: always; }
.stmt-page:last-child { page-break-after: auto; }

.stmt-header { text-align: center; margin-bottom: 4mm; }
.stmt-header .school-name { font-size: 15pt; font-weight: 800; color: #062653; text-transform: uppercase; }
.stmt-header .stmt-title { font-size: 10.5pt; font-weight: 800; color: #062653; margin-top: 2mm; padding: 1.5mm 0;
    border-top: 1pt solid #c9941d; border-bottom: 1pt solid #c9941d; text-transform: uppercase; letter-spacing: 0.5pt; }

.stmt-info { width: 100%; border-collapse: collapse; margin-bottom: 3mm; font-size: 8pt; }
.stmt-info td { padding: 1.2mm 2.5mm; border: 0.5pt solid #cbd5e1; }
.stmt-info td.lbl { font-weight: 700; background: #f8fafc; width: 18%; color: #062653; }

.stmt-h { font-size: 8.5pt; color: #062653; margin: 2.5mm 0 1.2mm; font-weight: 800; text-transform: uppercase; }

.stmt-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 2.5mm; }
.stmt-table th { background: #062653; color: #ffffff; padding: 1.5mm 2mm; text-align: left; font-weight: 800; font-size: 7.5pt; border: 0.3pt solid #062653; }
.stmt-table th.amt, .stmt-table td.amt { text-align: right; }
.stmt-table td { padding: 1.1mm 2mm; border: 0.5pt solid #cbd5e1; }
.stmt-table td.amt { font-weight: 600; }
.stmt-table tr.sub td { color: #475569; font-size: 7.5pt; background: #fcfbfa; }
.stmt-table tr.sub td.item { padding-left: 5mm; }
.stmt-table tr.bf td { background: #fff9e9; font-weight: 700; }
.stmt-table tr.total td { background: #f1f5f9; font-weight: 800; }

.status-cell { text-align: center; font-weight: 800; font-size: 7pt; text-transform: uppercase; }
.status-Paid, .status-Cleared { color: #07552f; }
.status-Partlypaid { color: #d97706; }
.status-Unpaid, .status-Balancedue { color: #dc2626; }
.status-Incredit { color: #1d4ed8; }
.status-Nofees { color: #475569; }

.stmt-summary table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
.stmt-summary td { padding: 1.4mm 2.5mm; border: 0.5pt solid #cbd5e1; }
.stmt-summary td.lbl { font-weight: 700; background: #f8fafc; width: 60%; color: #062653; }
.stmt-summary td.val { text-align: right; font-weight: 700; }
.stmt-summary tr.balance td { background: #062653; color: #ffffff; font-size: 10pt; font-weight: 800; }

.stmt-notes { margin-top: 3mm; font-size: 7.5pt; color: #1e293b; border: 0.5pt solid #c9941d; padding: 2mm 3mm; background: #fff9e9; }
.stmt-notes strong { color: #062653; }
.stmt-warning { margin-top: 2mm; font-size: 7.5pt; color: #7f1d1d; border: 0.5pt solid #dc2626; padding: 2mm 3mm; background: #fef2f2; }
</style>
</head>
<body>

<?php if ($summaryTable): ?>
<div class="stmt-page">
    <?php if (!$useSharedReportShell): ?><div class="stmt-header">
        <div class="school-name"><?= se($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
        <div class="stmt-title"><?= se($summaryTable['title'] ?? 'FEE STATEMENT SUMMARY') ?></div>
    </div><?php endif; ?>
    <h3 class="stmt-h"><?= se($summaryTable['title'] ?? '') ?></h3>
    <p style="margin-bottom:2mm; color:#475569;"><?= se($summaryTable['subtitle'] ?? '') ?></p>
    <table class="stmt-table">
        <thead>
            <tr>
                <th><?= se($summaryTable['label'] ?? 'Learner') ?></th>
                <th class="amt">Brought forward</th>
                <th class="amt">Fees billed</th>
                <th class="amt">Waived / credits</th>
                <th class="amt">Received</th>
                <th class="amt">Balance</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (($summaryTable['rows'] ?? []) as $row): ?>
            <tr>
                <td><?= se($row['label'] ?? '') ?></td>
                <td class="amt"><?= fs_balance(($row['arrears_brought_forward'] ?? 0) - ($row['credit_brought_forward'] ?? 0)) ?></td>
                <td class="amt"><?= sm($row['fees_billed'] ?? 0) ?></td>
                <td class="amt"><?= sm(($row['waived'] ?? 0) + ($row['credit_notes'] ?? 0)) ?></td>
                <td class="amt"><?= sm($row['payments_received'] ?? 0) ?></td>
                <td class="amt"><?= fs_balance($row['closing_balance'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php $t = $summaryTable['totals'] ?? null; if ($t): ?>
            <tr class="total">
                <td>Total (<?= (int)($t['learners'] ?? 0) ?> learners)</td>
                <td class="amt"><?= fs_balance(($t['arrears_brought_forward'] ?? 0) - ($t['credit_brought_forward'] ?? 0)) ?></td>
                <td class="amt"><?= sm($t['fees_billed'] ?? 0) ?></td>
                <td class="amt"><?= sm(($t['waived'] ?? 0) + ($t['credit_notes'] ?? 0)) ?></td>
                <td class="amt"><?= sm($t['payments_received'] ?? 0) ?></td>
                <td class="amt"><?= fs_balance($t['closing_balance'] ?? 0) ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php if ($t): ?>
    <p style="color:#475569;"><?= (int)($t['learners_owing'] ?? 0) ?> learners owe a balance; <?= (int)($t['learners_in_credit'] ?? 0) ?> are in credit.
        Brought forward is the balance carried from the previous academic year (credit shown as CR).</p>
    <?php if (!empty($t['not_reconciled'])): ?>
    <div class="stmt-warning"><strong><?= (int)$t['not_reconciled'] ?> statement(s) do not add up</strong> and must be checked by the accounts office before they are sent.</div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php foreach ($statements as $s):
    $student = $s['student'] ?? [];
    $bf = $s['brought_forward'] ?? [];
    $sum = $s['summary'] ?? [];
    $money = $s['money_in'] ?? [];
    $year = $s['academic_year']['code'] ?? '';
?>
<div class="stmt-page">
    <?php if (!$useSharedReportShell): ?><div class="stmt-header">
        <div class="school-name"><?= se($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
        <div class="stmt-title">STUDENT FEE STATEMENT</div>
    </div><?php endif; ?>

    <table class="stmt-info">
        <tr>
            <td class="lbl">Learner</td><td><strong><?= se($student['name'] ?? '') ?></strong></td>
            <td class="lbl">Admission No</td><td><?= se($student['admission_no'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="lbl">Class / Stream</td>
            <td><?= se($student['class_name'] ?? '') ?><?= !empty($student['stream_name']) ? ' (' . se($student['stream_name']) . ')' : '' ?></td>
            <td class="lbl">Learner Type</td><td><?= se($student['student_type'] ?? '') ?></td>
        </tr>
        <tr>
            <td class="lbl">Academic Year</td><td><?= se($year) ?></td>
            <td class="lbl">Status</td><td><?= se(ucfirst((string)($student['enrollment_status'] ?? ''))) ?></td>
        </tr>
    </table>

    <h3 class="stmt-h">Fees for <?= se($year) ?></h3>
    <table class="stmt-table">
        <thead>
            <tr>
                <th>Term / Item</th>
                <th class="amt">Charged</th>
                <th class="amt">Waived / credit</th>
                <th class="amt">Paid</th>
                <th class="amt">Balance</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php if (($bf['arrears'] ?? 0) > 0 || ($bf['credit'] ?? 0) > 0): ?>
            <tr class="bf">
                <td colspan="6">Brought forward from <?= se($bf['from_year'] ?? 'the previous year') ?>:
                    <?php if (($bf['arrears'] ?? 0) > 0): ?>arrears KES <?= sm($bf['arrears']) ?><?php endif; ?>
                    <?php if (($bf['credit'] ?? 0) > 0): ?>credit KES <?= sm($bf['credit']) ?> (overpayment)<?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach (($s['terms'] ?? []) as $term):
            $status = $term['status'] ?? '';
            $statusClass = 'status-' . preg_replace('/[^A-Za-z]/', '', $status);
            $charged = ($term['arrears_brought_forward'] ?? 0) + ($term['fees_billed'] ?? 0);
            $reduced = ($term['waived'] ?? 0) + ($term['credit_brought_forward'] ?? 0) + ($term['credit_notes'] ?? 0);
        ?>
            <tr>
                <td><strong><?= se($term['term'] ?? '') ?></strong></td>
                <td class="amt"><?= sm($charged) ?></td>
                <td class="amt"><?= sm($reduced) ?></td>
                <td class="amt"><?= sm($term['paid'] ?? 0) ?></td>
                <td class="amt"><?= fs_balance($term['balance'] ?? 0) ?></td>
                <td class="status-cell <?= $statusClass ?>"><?= se($status) ?></td>
            </tr>
            <?php if (($term['arrears_brought_forward'] ?? 0) > 0): ?>
            <tr class="sub"><td class="item">Arrears brought forward</td><td class="amt"><?= sm($term['arrears_brought_forward']) ?></td><td colspan="4"></td></tr>
            <?php endif; ?>
            <?php foreach (($term['items'] ?? []) as $item): ?>
            <tr class="sub"><td class="item"><?= se($item['item'] ?? '') ?></td><td class="amt"><?= sm($item['amount'] ?? 0) ?></td><td colspan="4"></td></tr>
            <?php endforeach; ?>
            <?php if (($term['credit_brought_forward'] ?? 0) > 0): ?>
            <tr class="sub"><td class="item">Credit brought forward applied</td><td></td><td class="amt"><?= sm($term['credit_brought_forward']) ?></td><td colspan="3"></td></tr>
            <?php endif; ?>
            <?php if (($term['credit_notes'] ?? 0) > 0): ?>
            <tr class="sub"><td class="item">Credit notes applied</td><td></td><td class="amt"><?= sm($term['credit_notes']) ?></td><td colspan="3"></td></tr>
            <?php endif; ?>
            <?php if (($term['waived'] ?? 0) > 0): ?>
            <tr class="sub"><td class="item">Waivers and discounts</td><td></td><td class="amt"><?= sm($term['waived']) ?></td><td colspan="3"></td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (empty($s['terms'])): ?>
            <tr><td colspan="6" style="text-align:center; color:#64748b;">No fees were billed for this year.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (!empty($s['payments']) || ($money['recorded_at_go_live'] ?? 0) > 0): ?>
    <h3 class="stmt-h">Money received for <?= se($year) ?></h3>
    <table class="stmt-table">
        <thead><tr><th>Date</th><th>Method</th><th>Receipt No.</th><th>Reference</th><th class="amt">Amount</th></tr></thead>
        <tbody>
        <?php if (($money['recorded_at_go_live'] ?? 0) > 0): ?>
            <tr><td colspan="4">Payments recorded before the online system</td><td class="amt"><?= sm($money['recorded_at_go_live']) ?></td></tr>
        <?php endif; ?>
        <?php foreach (($s['payments'] ?? []) as $payment): ?>
            <tr>
                <td><?= se(substr((string)($payment['payment_date'] ?? ''), 0, 10)) ?></td>
                <td><?= se($payment['method'] ?? '') ?></td>
                <td><?= se($payment['receipt_no'] ?? '') ?></td>
                <td><?= se($payment['reference'] ?? '') ?></td>
                <td class="amt"><?= sm($payment['amount'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total"><td colspan="4">Total received</td><td class="amt"><?= sm($money['total'] ?? 0) ?></td></tr>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="stmt-summary">
        <table>
            <?php if (($sum['arrears_brought_forward'] ?? 0) > 0): ?>
            <tr><td class="lbl">Arrears brought forward<?= !empty($bf['from_year']) ? ' from ' . se($bf['from_year']) : '' ?></td><td class="val">KES <?= sm($sum['arrears_brought_forward']) ?></td></tr>
            <?php endif; ?>
            <?php if (($sum['credit_brought_forward'] ?? 0) > 0): ?>
            <tr><td class="lbl">Less: credit brought forward<?= !empty($bf['from_year']) ? ' from ' . se($bf['from_year']) : '' ?></td><td class="val">KES <?= sm($sum['credit_brought_forward']) ?></td></tr>
            <?php endif; ?>
            <tr><td class="lbl">Fees billed for <?= se($year) ?></td><td class="val">KES <?= sm($sum['fees_billed'] ?? 0) ?></td></tr>
            <?php if (($sum['waived'] ?? 0) > 0): ?>
            <tr><td class="lbl">Less: waivers and discounts</td><td class="val">KES <?= sm($sum['waived']) ?></td></tr>
            <?php endif; ?>
            <?php if (($sum['credit_notes'] ?? 0) > 0): ?>
            <tr><td class="lbl">Less: credit notes</td><td class="val">KES <?= sm($sum['credit_notes']) ?></td></tr>
            <?php endif; ?>
            <tr><td class="lbl">Less: money received</td><td class="val">KES <?= sm($sum['payments_received'] ?? 0) ?></td></tr>
            <tr class="balance">
                <td class="lbl" style="background:#062653; color:#fff;"><?= ($sum['closing_balance'] ?? 0) < 0 ? 'IN CREDIT' : 'BALANCE DUE' ?></td>
                <td class="val">KES <?= sm(abs((float)($sum['closing_balance'] ?? 0))) ?></td>
            </tr>
        </table>
    </div>

    <?php if (($bf['credit_not_yet_applied'] ?? 0) > 0): ?>
    <div class="stmt-notes"><strong>Credit available:</strong> KES <?= sm($bf['credit_not_yet_applied']) ?> brought forward has not yet been applied to a bill and will reduce future fees.</div>
    <?php endif; ?>
    <?php if (empty($s['reconciliation']['ok'])): ?>
    <div class="stmt-warning"><strong>Check required:</strong> this statement does not add up (difference KES <?= sm($s['reconciliation']['difference'] ?? 0) ?>). Please contact the accounts office before relying on it.</div>
    <?php endif; ?>
    <div class="stmt-notes">
        <strong>Note:</strong> Balances from a completed year are brought forward into the next year's first term; a credit (CR) is an overpayment that reduces future fees.
        Payments should be made via official bank transfer or M-Pesa Paybill. Please keep this statement for your records.
    </div>
</div>
<?php endforeach; ?>

</body>
</html>
