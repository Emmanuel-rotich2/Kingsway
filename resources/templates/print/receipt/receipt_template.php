<?php
/**
 * Official Receipt — Portrait A4
 *
 * For all payment types: tuition, uniform, vendor, transport, etc.
 * Variables:
 *   $receiptNo, $date, $receivedFrom, $amount, $paymentMethod,
 *   $reference (bank ref / mpesa code), $items (array [{description, amount}]),
 *   $total, $receivedBy, $remarks,
 *   $schoolName, $schoolMotto, $schoolAddress, $schoolPhone, $schoolEmail
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
if (!function_exists('re')) { function re(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('rm')) { function rm($v): string { return number_format((float)($v ?? 0), 2); }}

$receiptNo = $receiptNo ?? 'RCP-' . date('YmdHis');
$date = $date ?? date('d F Y');
$items = $items ?? [];
$total = $total ?? 0;
$paymentMethod = $paymentMethod ?? 'Cash';
$useSharedReportShell = $useSharedReportShell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 15mm 20mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#1b2a23; }

.rct-header { text-align:center; margin-bottom:4mm; padding-bottom:3mm; border-bottom:2pt solid #0f5b3b; }
.rct-header .school-name { font-size:14pt; font-weight:800; color:#0f5b3b; }
.rct-header .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.rct-header .school-addr { font-size:7pt; color:#5e6e65; }
.rct-header .rct-title { font-size:11pt; font-weight:700; color:#083f2b; margin-top:2mm; background:#e8f5e9; padding:2mm; display:inline-block; padding:2mm 8mm; border:1pt solid #0f5b3b; }

.rct-info { display:flex; gap:5mm; margin-bottom:4mm; font-size:8.5pt; }
.rct-info-box { flex:1; border:0.5pt solid #e5e7eb; padding:2mm; }
.rct-info-box .label { font-weight:700; color:#0f5b3b; font-size:7.5pt; text-transform:uppercase; margin-bottom:0.5mm; }
.rct-info-box .value { font-size:9pt; }

.rct-table { width:100%; border-collapse:collapse; margin-top:3mm; font-size:8.5pt; border:1pt solid #c7d3cc; }
.rct-table th { background:#0f5b3b; color:#fff; padding:2mm 3mm; text-align:left; font-weight:700; font-size:8pt; }
.rct-table th.amt { text-align:right; }
.rct-table td { padding:2mm 3mm; border:0.5pt solid #e5e7eb; }
.rct-table td.amt { text-align:right; font-family:'DejaVu Sans Mono',monospace; }
.rct-table tr.total-row { background:#e8f5e9; font-weight:800; font-size:10pt; }
.rct-table tr.total-row td { border-top:1pt solid #0f5b3b; }

.rct-amount-words { margin-top:3mm; padding:2mm 3mm; background:#f9fafb; border:0.5pt solid #e5e7eb; font-size:8.5pt; }
.rct-amount-words strong { color:#0f5b3b; }

.rct-remarks { margin-top:3mm; font-size:8pt; color:#374151; }
.rct-remarks strong { color:#0f5b3b; }

.rct-footer { margin-top:6mm; }
.rct-footer table { width:100%; border-collapse:collapse; }
.rct-footer td { width:33%; text-align:center; vertical-align:top; padding-top:12mm; }
.rct-footer .sig-line { border-top:0.5pt solid #000; width:70%; margin:0 auto 1mm; }
.rct-footer .sig-name { font-size:7.5pt; font-weight:700; }
.rct-footer .sig-title { font-size:6.5pt; color:#5e6e65; }
</style>
</head>
<body>
<?php if (!$useSharedReportShell): ?><div class="rct-header">
    <div class="school-name"><?= re($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
    <div class="school-motto">"<?= re($schoolMotto ?? 'In God We Soar') ?>"</div>
    <div class="school-addr"><?= re($schoolAddress ?? '') ?></div>
    <div class="rct-title">OFFICIAL RECEIPT</div>
</div><?php endif; ?>

<div class="rct-info">
    <div class="rct-info-box">
        <div class="label">Receipt Number</div>
        <div class="value"><strong><?= re($receiptNo) ?></strong></div>
    </div>
    <div class="rct-info-box">
        <div class="label">Date</div>
        <div class="value"><?= re($date) ?></div>
    </div>
    <div class="rct-info-box">
        <div class="label">Received From</div>
        <div class="value"><?= re($receivedFrom ?? '') ?></div>
    </div>
    <div class="rct-info-box">
        <div class="label">Payment Method</div>
        <div class="value"><?= re($paymentMethod) ?></div>
    </div>
</div>

<table class="rct-table">
    <thead>
        <tr><th style="width:15%;">S/N</th><th style="width:55%;">Description</th><th class="amt" style="width:30%;">Amount (KES)</th></tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
        <tr><td>1</td><td>Payment received</td><td class="amt"><?= rm($amount ?? $total ?? 0) ?></td></tr>
    <?php else: ?>
        <?php foreach ($items as $i => $item): ?>
        <tr>
            <td><?= ($i+1) ?></td>
            <td><?= re($item['description'] ?? $item['name'] ?? '') ?></td>
            <td class="amt"><?= rm($item['amount'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <tr class="total-row">
        <td colspan="2"><strong>TOTAL RECEIVED</strong></td>
        <td class="amt"><strong>KES <?= rm($total) ?></strong></td>
    </tr>
    </tbody>
</table>

<div class="rct-amount-words">
    <strong>Amount in Words:</strong> <?= re($amountInWords ?? '') ?>
</div>

<?php if (!empty($reference)): ?>
<div class="rct-remarks"><strong>Reference:</strong> <?= re($reference) ?></div>
<?php endif; ?>

<?php if (!empty($remarks)): ?>
<div class="rct-remarks"><strong>Remarks:</strong> <?= re($remarks) ?></div>
<?php endif; ?>

<?php if (!$useSharedReportShell): ?><div class="rct-footer">
    <table>
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">Received By</div>
                <div class="sig-title"><?= re($receivedBy ?? '') ?></div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">Accounts Officer</div>
                <div class="sig-title">Signature &amp; Stamp</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">Authorized Signatory</div>
                <div class="sig-title">Headteacher</div>
            </td>
        </tr>
    </table>
</div><?php endif; ?>
</body>
</html>
