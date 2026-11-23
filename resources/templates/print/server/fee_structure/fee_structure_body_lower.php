<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$mpesa = $paymentMpesa ?? []; 
$bank = $paymentBank ?? []; 
$charges = $otherCharges ?? [];
$notes = $importantNotes ?? []; 
$paymentMethods = array_values(array_filter($paymentMethods ?? [], static function ($method) {
    return !empty($method['type']);
}));
if (!$paymentMethods) {
    if (!empty($mpesa['paybill'])) $paymentMethods[] = array_merge(['type' => 'mpesa'], $mpesa);
    if (!empty($bank['bank'])) $paymentMethods[] = array_merge(['type' => 'bank'], $bank);
}
$formatImportantNote = static function (string $note): string {
    $labels = [
        'M-Pesa App or SIM Toolkit:' => 'fs-note-mpesa',
        'KCB Mobile App:' => 'fs-note-kcb',
        'Parent Portal:' => 'fs-note-portal',
        'Cash payment is not accepted.' => 'fs-note-warning',
    ];
    foreach ($labels as $label => $class) {
        if (strpos($note, $label) === 0) {
            return '<strong class="' . $class . '">' . htmlspecialchars($label) . '</strong>' . htmlspecialchars(substr($note, strlen($label)));
        }
    }
    return htmlspecialchars($note);
};
?>
<section class="fs-lower-area">
    <table class="fs-lower-grid">
        <tr>
            <!-- OTHER CHARGES -->
            <td class="fs-charges-cell">
                <div class="fs-card">
                    <div class="fs-card-header">OTHER CHARGES</div>
                    <div class="fs-card-body">
                        <?php if ($charges): foreach ($charges as $charge): ?>
                            <table class="fs-charge-table">
                                <tr>
                                    <td class="fs-charge-name"><?= htmlspecialchars($charge['name'] ?? '') ?>:</td>
                                    <td style="width:100%;"><div class="fs-charge-dots"></div></td>
                                    <td class="fs-charge-amount"><?= isset($charge['amount_text']) && $charge['amount_text'] !== '' ? htmlspecialchars((string) $charge['amount_text']) : 'KES ' . number_format((float) ($charge['amount'] ?? 0)) ?></td>
                                </tr>
                            </table>
                        <?php endforeach; else: ?>
                            <div style="text-align:center; color:#777; padding-top:2mm;">No additional charges</div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>

            <!-- PAYMENT OPTIONS -->
            <td class="fs-payments-cell">
                <div class="fs-card fs-card--navy">
                    <div class="fs-card-header">PAYMENT OPTIONS</div>
                    <div class="fs-card-body">
                        <table class="fs-pay-table"><tr>
                            <?php foreach ($paymentMethods as $method): ?>
                            <td class="fs-pay-box-cell">
                                <div class="fs-pay-box">
                                    <?php if (($method['type'] ?? '') === 'mpesa'): ?>
                                        <div class="fs-pay-title"><?= htmlspecialchars($method['title'] ?? 'LIPA NA M-PESA') ?></div>
                                        <div class="fs-pay-label">PAYBILL NO.</div>
                                        <div class="fs-pay-value"><?= htmlspecialchars($method['paybill'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label"><?= htmlspecialchars($method['reference_label'] ?? 'ACCOUNT NO.') ?></div>
                                        <div class="fs-pay-value"><?= htmlspecialchars($method['reference_value'] ?? 'Admission number') ?></div>
                                    <?php elseif (($method['type'] ?? '') === 'kcb_mobile'): ?>
                                        <div class="fs-pay-title fs-pay-title--bank"><?= htmlspecialchars($method['title'] ?? 'KCB MOBILE APP - LIPA KARO') ?></div>
                                        <div class="fs-pay-label">SCHOOL ACCOUNT NO.</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['account_no'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NAME</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['account_name'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label"><?= htmlspecialchars($method['reference_label'] ?? 'ADMISSION NO.') ?></div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['reference_value'] ?? 'Admission number') ?></div>
                                    <?php else: ?>
                                        <div class="fs-pay-title fs-pay-title--bank">BANK PAYMENT</div>
                                        <div class="fs-pay-label">BANK</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['bank_name'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NAME</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['account_name'] ?? '') ?></div>
                                        <div class="fs-pay-divider"></div>
                                        <div class="fs-pay-label">ACCOUNT NO.</div>
                                        <div class="fs-pay-value fs-pay-value--bank"><?= htmlspecialchars($method['account_no'] ?? '') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($method['instructions'])): ?><div class="fs-pay-instructions"><?= htmlspecialchars($method['instructions']) ?></div><?php endif; ?>
                                </div>
                            </td>
                            <?php endforeach; ?>
                        </tr></table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

   <!-- IMPORTANT INFORMATION -->
    <?php if ($notes): ?>
    <div class="fs-important-box">
        <table class="fs-important-table">
            <tr>
                <td class="fs-important-label-cell">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr>
                            <td class="fs-important-text">
                                IMPORTANT<br>INFORMATION
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="fs-important-list-cell">
                    <ol class="fs-important-list" type="I">
                        <?php foreach ($notes as $note): ?>
                            <li><?= $formatImportantNote((string) $note) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
</section>
