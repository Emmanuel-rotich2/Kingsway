<?php

declare(strict_types=1);

if (!function_exists('idCardEscape')) {
    function idCardEscape(mixed $value): string
    {
        return htmlspecialchars(
            (string) ($value ?? ''),
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }
}

$schoolName = $schoolName ?? 'Kingsway Preparatory School';
$schoolAddress = $schoolAddress ?? 'Londiani, Kenya';
$schoolMotto = $schoolMotto ?? 'In God We Soar';
$schoolPhone = $schoolPhone ?? '';
$schoolEmail = $schoolEmail ?? '';
$schoolLogo = $schoolLogo ?? '';

$studentPhoto = $studentPhoto ?? '';
$studentName = $studentName ?? 'Student Name';
$admissionNumber = $admissionNumber ?? '—';
$gender = $gender ?? '—';
$academicYear = $academicYear ?? '—';
$className = $className ?? '';
$streamName = $streamName ?? '';

$classDisplay = trim(
    implode(
        ' ',
        array_filter([$className, $streamName])
    )
);

if ($classDisplay === '') {
    $classDisplay = '—';
}
?>
<article class="id-card id-card-front">
    <table class="id-front-layout" role="presentation">
        <tr class="id-front-header-row" border="0">
            <td class="id-front-logo-cell">
                <?php if ($schoolLogo !== ''): ?>
                    <img
                        src="<?= idCardEscape($schoolLogo) ?>"
                        class="id-card-logo"
                        alt="School Logo"
                    >
                <?php endif; ?>
            </td>

            <td class="id-front-school-cell" colspan="4">
                <div class="id-card-school-name">
                    <?= idCardEscape($schoolName) ?>
                </div>

                <div class="id-card-school-meta">
                    <?= idCardEscape($schoolAddress) ?>
                </div>
            </td>
        </tr>

        <tr>
            <td colspan="5" class="id-card-title-strip">
                Student Identity Card
            </td>
        </tr>

        <tr class="id-front-body-row" >
            <td class="id-front-photo-cell" colspan="2">
                <?php if ($studentPhoto !== ''): ?>
                    <img
                        src="<?= idCardEscape($studentPhoto) ?>"
                        class="id-card-photo"
                        alt="Student Photo"
                    >
                <?php else: ?>
                    <div class="id-card-photo id-card-photo-placeholder">
                        Photo
                    </div>
                <?php endif; ?>
            </td>

            <td class="id-front-details-cell" colspan="3">
                <div class="id-card-name">
                    <?= idCardEscape($studentName) ?>
                </div>

                <table class="id-card-detail-table" role="presentation">
                    <tr>
                        <td>Adm No</td>
                        <td><?= idCardEscape($admissionNumber) ?></td>
                    </tr>
                    <tr>
                        <td>Gender</td>
                        <td><?= idCardEscape($gender) ?></td>
                    </tr>
                    <tr>
                        <td>Class</td>
                        <td><?= idCardEscape($classDisplay) ?></td>
                    </tr>
                    <tr>
                        <td>Acad. Year</td>
                        <td><?= idCardEscape($academicYear) ?></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td colspan="5" class="id-card-footer-strip">
                <span><?= idCardEscape($schoolMotto) ?></span>
                <span>
                    <?= idCardEscape(
                        $schoolPhone ?: ($schoolEmail ?: 'Official School ID')
                    ) ?>
                </span>
            </td>
        </tr>
    </table>
</article>
