<?php
/**
 * Invoice — Portrait A4
 *
 * For student fee invoices, vendor invoices, etc.
 * Variables:
 *   $invoiceNo, $date, $dueDate, $billedTo (name, address),
 *   $invoiceType (Student Fee / Vendor / Service),
 *   $items (array [{description, qty, unit_price, amount}]),
 *   $subtotal, $tax, $total, $amountDue,
 *   $notes, $schoolName, $schoolMotto, $schoolAddress, $schoolPhone
 */
declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
if (!function_exists('ie')) { function ie(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }}
if (!function_exists('im')) { function im($v): string { return number_format((float)($v ?? 0), 2); }}

$invoiceNo = $invoiceNo ?? 'INV-' . date('YmdHis');
$date = $date ?? date('d F Y');
$dueDate = $dueDate ?? '';
$items = $items ?? [];
$subtotal = $subtotal ?? 0;
$tax = $tax ?? 0;
$total = $total ?? 0;
$amountDue = $amountDue ?? $total;
$invoiceType = $invoiceType ?? 'Service';
$useSharedReportShell = $useSharedReportShell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
@page { size: A4 portrait; margin: 15mm 18mm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size:9pt; color:#1b2a23; }

.inv-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:5mm; padding-bottom:3mm; border-bottom:2pt solid #0f5b3b; }
.inv-header-left .school-name { font-size:14pt; font-weight:800; color:#0f5b3b; }
.inv-header-left .school-motto { font-size:8pt; color:#d3ad24; font-style:italic; }
.inv-header-left .school-addr { font-size:7pt; color:#5e6e65; }
.inv-header-right { text-align:right; }
.inv-header-right .inv-title { font-size:16pt; font-weight:800; color:#083f2b; }
.inv-header-right .inv-type { font-size:9pt; color:#0f5b3b; font-weight:700; }

.inv-meta { display:flex; gap:5mm; margin-bottom:5mm; }
.inv-meta-box { flex:1; }
.inv-meta-box .label { font-size:7pt; color:#5e6e65; text-transform:uppercase; font-weight:700; margin-bottom:0.5mm; }
.inv-meta-box .value { font-size:9pt; font-weight:700; }

.inv-billed { margin-bottom:5mm; }
.inv-billed .label { font-size:7pt; color:#5e6e65; text-transform:uppercase; font-weight:700; margin-bottom:0.5mm; }
.inv-billed .value { font-size:9pt; }

.inv-table { width:100%; border-collapse:collapse; font-size:8.5pt; }
.inv-table th { background:#0f5b3b; color:#fff; padding:2mm 3mm; text-align:left; font-weight:700; font-size:8pt; }
.inv-table th.amt { text-align:right; }
.inv-table th.qty { text-align:center; }
.inv-table td { padding:1.8mm 3mm; border:0.5pt solid #e5e7eb; }
.inv-table td.amt { text-align:right; font-family:'DejaVu Sans Mono',monospace; }
.inv-table td.qty { text-align:center; }
.inv-table tr:nth-child(even) { background:#f9fafb; }

.inv-totals { margin-top:3mm; float:right; width:45%; }
.inv-totals table { width:100%; border-collapse:collapse; font-size:9pt; }
.inv-totals td { padding:1.5mm 3mm; border:0.5pt solid #e5e7eb; }
.inv-totals td:first-child { font-weight:700; background:#f9fafb; width:50%; color:#0f5b3b; }
.inv-totals td:last-child { text-align:right; font-family:'DejaVu Sans Mono',monospace; font-weight:700; }
.inv-totals tr.due td { background:#0f5b3b; color:#fff; font-size:11pt; }

.inv-notes { clear:both; margin-top:5mm; padding:3mm; background:#f9fafb; border:0.5pt solid #e5e7eb; font-size:8pt; color:#374151; }
.inv-notes strong { color:#0f5b3b; }

.inv-footer { margin-top:6mm; }
.inv-footer table { width:100%; border-collapse:collapse; }
.inv-footer td { width:50%; text-align:center; vertical-align:top; padding-top:10mm; }
.inv-footer .sig-line { border-top:0.5pt solid #000; width:60%; margin:0 auto 1mm; }
.inv-footer .sig-name { font-size:8pt; font-weight:700; }
</style>
</head>
<body>
<?php if (!$useSharedReportShell): ?><div class="inv-header">
    <div class="inv-header-left">
        <div class="school-name"><?= ie($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL') ?></div>
        <div class="school-motto">"<?= ie($schoolMotto ?? 'In God We Soar') ?>"</div>
        <div class="school-addr"><?= ie($schoolAddress ?? '') ?></div>
    </div>
    <div class="inv-header-right">
        <div class="inv-title">INVOICE</div>
        <div class="inv-type"><?= ie($invoiceType) ?></div>
    </div>
</div><?php endif; ?>

<div class="inv-meta">
    <div class="inv-meta-box"><div class="label">Invoice No</div><div class="value"><?= ie($invoiceNo) ?></div></div>
    <div class="inv-meta-box"><div class="label">Date</div><div class="value"><?= ie($date) ?></div></div>
    <div class="inv-meta-box"><div class="label">Due Date</div><div class="value"><?= ie($dueDate ?: 'On Receipt') ?></div></div>
</div>

<div class="inv-billed">
    <div class="label">Bill To:</div>
    <div class="value"><strong><?= ie($billedTo['name'] ?? '') ?></strong></div>
    <div class="value" style="font-size:8pt;color:#5e6e65;"><?= ie($billedTo['address'] ?? '') ?></div>
</div>

<table class="inv-table">
    <thead>
        <tr>
            <th style="width:8%;">S/N</th>
            <th style="width:47%;">Description</th>
            <th class="qty" style="width:10%;">Qty</th>
            <th class="amt" style="width:18%;">Unit Price</th>
            <th class="amt" style="width:17%;">Amount (KES)</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:4mm;">No line items.</td></tr>
    <?php else: ?>
        <?php foreach ($items as $i => $item): ?>
        <tr>
            <td><?= ($i+1) ?></td>
            <td><?= ie($item['description'] ?? '') ?></td>
            <td class="qty"><?= ie((string)($item['qty'] ?? 1)) ?></td>
            <td class="amt"><?= im($item['unit_price'] ?? 0) ?></td>
            <td class="amt"><?= im($item['amount'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="inv-totals">
    <table>
        <tr><td>Subtotal</td><td>KES <?= im($subtotal) ?></td></tr>
        <?php if ($tax > 0): ?>
        <tr><td>VAT (16%)</td><td>KES <?= im($tax) ?></td></tr>
        <?php endif; ?>
        <tr class="due"><td>TOTAL DUE</td><td>KES <?= im($amountDue) ?></td></tr>
    </table>
</div>

<?php if (!empty($notes)): ?>
<div class="inv-notes">
    <strong>Payment Instructions:</strong><br>
    <?= ie($notes) ?>
</div>
<?php endif; ?>

<?php if (!$useSharedReportShell): ?><div class="inv-footer">
    <table>
        <tr>
            <td><div class="sig-line"></div><div class="sig-name">Authorized Signatory</div></td>
            <td><div class="sig-line"></div><div class="sig-name">Received By</div></td>
        </tr>
    </table>
</div><?php endif; ?>
</body>
</html>
