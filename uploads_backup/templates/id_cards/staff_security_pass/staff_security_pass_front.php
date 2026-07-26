<?php

declare(strict_types=1);

if (!function_exists('staffPassEscape')) {
    function staffPassEscape($value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

$schoolName = $schoolName ?? 'Kingsway Preparatory School';
$schoolMotto = $schoolMotto ?? 'In God We Soar';
$schoolLogo = $schoolLogo ?? '';
$staffPhoto = $staffPhoto ?? '';
$staffName = $staffName ?? 'Staff Member';
$staffNumber = $staffNumber ?? '—';
$position = $position ?? '—';
$departmentName = $departmentName ?? '—';
$passNumber = $passNumber ?? '—';
?>
<article class="staff-pass staff-pass-front">
    <div class="staff-pass-slot-zone">
        <span class="staff-pass-slot"></span>
    </div>

    <header class="staff-pass-header">
        <?php if ($schoolLogo !== ''): ?>
            <img
                src="<?= staffPassEscape($schoolLogo) ?>"
                class="staff-pass-logo"
                alt="School logo"
            >
        <?php endif; ?>

        <div class="staff-pass-school-copy">
            <div class="staff-pass-school-name">
                <?= staffPassEscape($schoolName) ?>
            </div>
            <div class="staff-pass-type">Staff Security Pass</div>
        </div>
    </header>

    <main class="staff-pass-front-body">
        <div class="staff-pass-photo-frame">
            <?php if ($staffPhoto !== ''): ?>
                <img
                    src="<?= staffPassEscape($staffPhoto) ?>"
                    class="staff-pass-photo"
                    alt="Staff portrait"
                >
            <?php else: ?>
                <div class="staff-pass-photo-placeholder">Photo</div>
            <?php endif; ?>
        </div>

        <div class="staff-pass-name">
            <?= staffPassEscape($staffName) ?>
        </div>

        <div class="staff-pass-position">
            <?= staffPassEscape($position ?: 'Staff Member') ?>
        </div>

        <table class="staff-pass-details" role="presentation">
            <tr>
                <td>Staff No.</td>
                <td><?= staffPassEscape($staffNumber ?: '—') ?></td>
            </tr>
            <tr>
                <td>Department</td>
                <td><?= staffPassEscape($departmentName ?: '—') ?></td>
            </tr>
            <tr>
                <td>Pass No.</td>
                <td><?= staffPassEscape($passNumber ?: '—') ?></td>
            </tr>
        </table>
    </main>

    <footer class="staff-pass-footer">
        <span><?= staffPassEscape($schoolMotto) ?></span>
        <span>Wear visibly while on school premises</span>
    </footer>
</article>
