<?php
/**
 * Attendance & Engagement Certificate Template
 *
 * Clean, minimalist design (single teal accent bar, thin double-line frame,
 * generous white space) for perfect attendance and punctuality recognition
 * under academic-office-owned types.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName, $certificateCategoryName
 * - $recipientName, $achievement, $academicYear, $certificateNumber,
 *   $dateAwarded
 * - $principalName ($principalTitle), $teacherName
 */

declare(strict_types=1);
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request

if (!function_exists('certificateEscape')) {
    function certificateEscape(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
if (!function_exists('certificateValue')) {
    function certificateValue(mixed $value, string $fallback = ''): string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : $fallback;
    }
}

$schoolName = certificateValue($schoolName ?? null, 'KINGSWAY PREPARATORY SCHOOL');
$schoolMotto = certificateValue($schoolMotto ?? null, 'In God We Soar');
$schoolLogo = certificateValue($schoolLogo ?? null, '/uploads/school_assets/official_school_logo.png');
$schoolAddress = certificateValue($schoolAddress ?? null, 'P.O. Box 203-20203, Londiani, Kericho County, Kenya');
$schoolPhone = certificateValue($schoolPhone ?? null, '0720 113 030 / 0720 113 031');
$schoolEmail = certificateValue($schoolEmail ?? null, 'info@kingswaypreparatoryschool.sc.ke');
$schoolWebsite = certificateValue($schoolWebsite ?? null, 'www.kingswaypreparatoryschool.sc.ke');
$principalName = certificateValue($principalName ?? null, 'Headteacher');
$principalTitle = certificateValue($principalTitle ?? null, 'Headteacher');
$teacherName = certificateValue($teacherName ?? null, 'Academic Officer');
$recipientName = certificateValue($recipientName ?? null, 'Student Name');
$achievement = certificateValue($achievement ?? null, 'Perfect Attendance');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'PERFECT ATTENDANCE');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'ATTENDANCE & ENGAGEMENT');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --teal: #0b5563;
            --teal-dark: #073a44;
            --accent: #2b8a9e;
            --paper: #ffffff;
            --line: #cddde2;
            --text: #1f2f34;
            --muted: #5b6f75;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #e6ecee; color: var(--text); font-family: Arial, Helvetica, sans-serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden; background: var(--paper); }
        .accent-bar-top { position: absolute; top: 0; left: 0; z-index: 6; height: 6mm; width: 100%; background: var(--teal); pointer-events: none; }
        .accent-bar-bottom { position: absolute; bottom: 0; left: 0; z-index: 6; height: 2.5mm; width: 100%; background: var(--teal); pointer-events: none; }
        .certificate-border { position: absolute; inset: 9mm; z-index: 5; border: 0.35mm solid var(--line); pointer-events: none; }
        .certificate-border-inner { position: absolute; inset: 11mm; z-index: 5; border: 0.25mm solid var(--line); pointer-events: none; }
        .checked-line { position: absolute; inset: 13mm; z-index: 4; border: 1.6mm solid rgba(11, 85, 99, 0.10); pointer-events: none; }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 38mm 24mm minmax(0, 1fr) 36mm;
            width: 100%; height: 100%; padding: 15mm 20mm 13mm; }
        .certificate-header { display: grid; grid-template-columns: 32mm minmax(0, 1fr) 32mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 29mm; height: 29mm;
            border: 0.6mm solid var(--line); border-radius: 50%; background: var(--paper); }
        .school-logo { display: block; width: 24mm; height: 24mm; object-fit: contain; border-radius: 50%; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--teal-dark); font-size: 22pt; font-weight: 800; letter-spacing: 1.2px; line-height: 1.05; text-transform: uppercase; }
        .school-motto { margin: 1.5mm 0 1.3mm; color: var(--accent); font-size: 10.5pt; font-style: italic; font-weight: 600; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-size: 7.2pt; line-height: 1.3; }
        .school-contact-separator { color: var(--accent); font-weight: 900; }
        .certificate-emblem { display: flex; align-items: center; justify-content: center; justify-self: end; width: 29mm; height: 29mm;
            border: 0.6mm solid var(--line); border-radius: 50%; background: var(--paper); color: var(--teal); }
        .certificate-emblem-check { font-size: 15pt; font-weight: 900; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--teal-dark); font-size: 25pt; font-weight: 800; letter-spacing: 3.2px; line-height: 1.05; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--accent); }
        .certificate-title-divider { width: 52mm; height: 0.5mm; margin-top: 3mm; background: var(--teal); }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 16mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.4mm; color: var(--muted); font-size: 12pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 220mm; margin: 0; padding: 0 8mm 2mm; color: var(--teal-dark);
            font-size: 33pt; font-weight: 800; letter-spacing: 0.8px; line-height: 1.1; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.6mm; background: var(--line); }
        .certificate-recognition { max-width: 210mm; margin: 3.2mm 0 0; color: #33484f; font-size: 12.5pt; line-height: 1.6; }
        .certificate-recognition strong { color: var(--teal); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.4mm; padding: 1.2mm 6mm; background: rgba(11, 85, 99, 0.07); color: var(--teal-dark); font-size: 12.5pt; font-weight: 800; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 42mm 1fr; align-items: end; gap: 12mm; padding: 2mm 15mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 11mm; }
        .signature-line { border-top: 0.4mm solid var(--teal-dark); padding-top: 1.7mm; }
        .signature-name { color: var(--teal-dark); font-size: 9.5pt; font-weight: 800; }
        .signature-title { margin-top: 0.7mm; color: var(--muted); font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 34mm; height: 34mm; justify-self: center;
            border: 0.8mm solid var(--teal); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, #eef6f8 70%, #d5e7ec 100%); }
        .official-seal-content { color: var(--teal-dark); font-size: 6.6pt; font-weight: 900; letter-spacing: 0.5px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-check { display: block; margin: 0.7mm 0; color: var(--accent); font-size: 11pt; }
        .certificate-details { position: absolute; right: 17mm; bottom: 13mm; left: 17mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--teal-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-22deg); color: rgba(11, 85, 99, 0.035); font-size: 54pt; font-weight: 900;
            letter-spacing: 6px; white-space: nowrap; pointer-events: none; }
        .recipient-name.name-long { font-size: 27pt; }
        .recipient-name.name-extra-long { font-size: 23pt; }
        @media screen { body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 12mm; } .certificate { box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2); } }
        @media print { html, body { width: 297mm; height: 210mm; overflow: hidden; background: #ffffff !important; } body { display: block; padding: 0; } .certificate { width: 297mm; height: 210mm; margin: 0; box-shadow: none; break-after: avoid; page-break-after: avoid; } }
    </style>
</head>
<body>
    <article class="certificate">
        <div class="accent-bar-top"></div>
        <div class="accent-bar-bottom"></div>
        <div class="certificate-border"></div>
        <div class="certificate-border-inner"></div>
        <div class="checked-line"></div>
        <div class="certificate-watermark"><?= certificateEscape($certificateTypeName) ?></div>
        <div class="certificate-content">
            <header class="certificate-header">
                <div class="school-logo-wrapper">
                    <img src="<?= certificateEscape($schoolLogo) ?>" alt="<?= certificateEscape($schoolName) ?> Logo" class="school-logo">
                </div>
                <div class="school-identity">
                    <h1 class="school-name"><?= certificateEscape($schoolName) ?></h1>
                    <p class="school-motto">“<?= certificateEscape($schoolMotto) ?>”</p>
                    <div class="school-contact">
                        <span><?= certificateEscape($schoolAddress) ?></span>
                        <span class="school-contact-separator">•</span>
                        <span>Tel: <?= certificateEscape($schoolPhone) ?></span>
                        <span class="school-contact-separator">•</span>
                        <span><?= certificateEscape($schoolEmail) ?></span>
                        <?php if ($schoolWebsite !== ''): ?>
                            <span class="school-contact-separator">•</span>
                            <span><?= certificateEscape($schoolWebsite) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="certificate-emblem">
                    <span class="certificate-emblem-check">✓</span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider"></div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong> throughout the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
                <div class="achievement-highlight"><?= certificateEscape($certificateTypeName) ?></div>
            </main>
            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($teacherName) ?></div>
                        <div class="signature-title">Academic Office</div>
                    </div>
                </div>
                <div class="official-seal">
                    <div class="official-seal-content">Kingsway<span class="official-seal-check">✓</span>Official Seal</div>
                </div>
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($principalName) ?></div>
                        <div class="signature-title"><?= certificateEscape($principalTitle) ?></div>
                    </div>
                </div>
            </footer>
        </div>
        <div class="certificate-details">
            <div class="certificate-detail">Certificate No: <strong><?= certificateEscape($certificateNumber) ?></strong></div>
            <div class="certificate-detail">Date Awarded: <strong><?= certificateEscape($dateAwarded) ?></strong></div>
        </div>
    </article>
</body>
</html>