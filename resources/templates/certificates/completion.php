<?php
/**
 * Institutional / Completion Certificate Template
 *
 * Prestigious ornate design (dark green + platinum, heavier Victorian-style
 * frame with corner ornaments and a tassel seal) for grade completion,
 * transitions and graduation-adjacent school-wide honours.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName, $certificateCategoryName
 * - $recipientName, $achievement, $course, $academicYear, $certificateNumber,
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
$achievement = certificateValue($achievement ?? null, 'Successful Completion');
$course = certificateValue($course ?? null, '');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'GRADE COMPLETION');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'INSTITUTIONAL & PROGRESSIVE');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --deep-green: #0b3d2e;
            --deep-green-dark: #07291f;
            --emerald: #14603f;
            --platinum: #c0c6c9;
            --platinum-light: #e6eaec;
            --champagne: #f0ead6;
            --ivory: #fdfbf4;
            --text: #152b22;
            --muted: #4f6157;
            --white: #ffffff;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #d8d5cc; color: var(--text); font-family: Georgia, "Times New Roman", serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.97) 0%, var(--ivory) 60%, var(--champagne) 100%); }
        .certificate-border-outer { position: absolute; inset: 4.5mm; z-index: 5; border: 3.2mm solid var(--deep-green); pointer-events: none; }
        .certificate-border-platinum { position: absolute; inset: 8.5mm; z-index: 5; border: 1.1mm solid var(--platinum); pointer-events: none; }
        .certificate-border-rule { position: absolute; inset: 11mm; z-index: 5; border: 0.35mm solid var(--emerald); pointer-events: none; }
        .corner-flourish { position: absolute; z-index: 7; width: 18mm; height: 18mm; border: 1.4mm solid var(--platinum); border-style: double; pointer-events: none; }
        .corner-flourish::after { content: ""; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 7mm; height: 7mm; border: 0.6mm solid var(--emerald); }
        .corner-flourish-tl { top: 7.2mm; left: 7.2mm; }
        .corner-flourish-tr { top: 7.2mm; right: 7.2mm; transform: scaleX(-1); }
        .corner-flourish-bl { bottom: 7.2mm; left: 7.2mm; transform: scaleY(-1); }
        .corner-flourish-br { right: 7.2mm; bottom: 7.2mm; transform: scale(-1); }
        .tassel { position: absolute; z-index: 6; width: 16mm; height: 16mm; pointer-events: none; }
        .tassel::before { content: ""; position: absolute; top: 7mm; left: 50%; transform: translateX(-50%); width: 1.6mm; height: 9mm; background: var(--emerald); }
        .tassel::after { content: ""; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 5mm; height: 4mm; border-radius: 1mm; border: 0.7mm solid var(--platinum); }
        .tassel-tl { top: 10.5mm; left: 13mm; }
        .tassel-br { right: 13mm; bottom: 11mm; transform: scaleY(-1); }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 42mm 24mm minmax(0, 1fr) 36mm;
            width: 100%; height: 100%; padding: 14mm 24mm 13mm; }
        .certificate-header { display: grid; grid-template-columns: 36mm minmax(0, 1fr) 36mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 33mm; height: 33mm;
            border: 1.1mm double var(--deep-green); border-radius: 50%; background: var(--white); box-shadow: 0 1.5mm 4mm rgba(7, 41, 31, 0.18); }
        .school-logo { display: block; width: 27mm; height: 27mm; object-fit: contain; border-radius: 50%; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--deep-green-dark); font-size: 27pt; font-weight: 800; letter-spacing: 1.8px; line-height: 1.02; text-transform: uppercase; }
        .school-motto { margin: 1.8mm 0 1.5mm; color: var(--emerald); font-size: 11.5pt; font-style: italic; font-weight: 700; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.3; }
        .school-contact-separator { color: var(--emerald); font-weight: 900; }
        .certificate-emblem { display: flex; flex-direction: column; align-items: center; justify-content: center; justify-self: end;
            width: 33mm; height: 33mm; border: 1.1mm double var(--platinum); border-radius: 50%;
            background: radial-gradient(circle, var(--ivory) 0%, var(--champagne) 100%); color: var(--deep-green-dark); text-align: center; }
        .certificate-emblem-seal { display: block; color: var(--emerald); font-size: 13pt; line-height: 1; }
        .certificate-emblem-text { margin-top: 1mm; font-family: Arial, Helvetica, sans-serif; font-size: 6.6pt; font-weight: 800; letter-spacing: 0.6px; line-height: 1.35; text-transform: uppercase; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--deep-green-dark); font-size: 28pt; font-weight: 800; letter-spacing: 2.4px; line-height: 1.05; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--emerald); }
        .certificate-title-divider { display: grid; grid-template-columns: 46mm 5mm 46mm; align-items: center; gap: 2.5mm; margin-top: 3mm; }
        .certificate-title-divider-line { height: 0.55mm; background: linear-gradient(90deg, transparent 0%, var(--platinum) 30%, var(--emerald) 100%); }
        .certificate-title-divider-line:last-child { transform: scaleX(-1); }
        .certificate-title-divider-ornament { width: 4mm; height: 4mm; transform: rotate(45deg); border: 0.5mm solid var(--emerald); }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 16mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.6mm; color: var(--muted); font-size: 12.5pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 216mm; margin: 0; padding: 0 8mm 2.5mm; color: var(--deep-green-dark);
            font-size: 38pt; font-weight: 800; letter-spacing: 1.4px; line-height: 1.06; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.9mm;
            background: linear-gradient(90deg, transparent 0%, var(--platinum) 12%, var(--emerald) 88%, transparent 100%); }
        .certificate-recognition { max-width: 208mm; margin: 3.6mm 0 0; color: #234a39; font-size: 13pt; line-height: 1.6; }
        .certificate-recognition strong { color: var(--deep-green); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.6mm; padding: 1.6mm 8mm; border: 0.4mm solid var(--deep-green);
            background: rgba(192, 198, 201, 0.16); color: var(--deep-green-dark); font-size: 14pt; font-weight: 800; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 46mm 1fr; align-items: end; gap: 11mm; padding: 2mm 15mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 11mm; }
        .signature-line { border-top: 0.5mm solid var(--deep-green-dark); padding-top: 1.7mm; }
        .signature-name { color: var(--deep-green-dark); font-size: 10.5pt; font-weight: 800; }
        .signature-title { margin-top: 0.7mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 39mm; height: 39mm; justify-self: center;
            border: 1.3mm double var(--emerald); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, var(--ivory) 70%, var(--champagne) 120%); box-shadow: inset 0 0 0 1mm var(--platinum), 0 1.5mm 4mm rgba(0, 0, 0, 0.12); }
        .official-seal::before { content: ""; position: absolute; inset: 3.4mm; border: 0.4mm solid var(--platinum); border-radius: 50%; }
        .official-seal-content { position: relative; z-index: 2; color: var(--deep-green-dark); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; font-weight: 900; letter-spacing: 0.6px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-ornament { display: block; margin: 0.9mm 0; color: var(--emerald); font-size: 12pt; line-height: 1; }
        .certificate-details { position: absolute; right: 20mm; bottom: 14mm; left: 20mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--deep-green-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-24deg); color: rgba(11, 61, 46, 0.04); font-size: 60pt; font-weight: 900;
            letter-spacing: 5px; white-space: nowrap; pointer-events: none; }
        .recipient-name.name-long { font-size: 30pt; }
        .recipient-name.name-extra-long { font-size: 25pt; }
        @media screen { body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 12mm; } .certificate { box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2); } }
        @media print { html, body { width: 297mm; height: 210mm; overflow: hidden; background: #ffffff !important; } body { display: block; padding: 0; } .certificate { width: 297mm; height: 210mm; margin: 0; box-shadow: none; break-after: avoid; page-break-after: avoid; } }
    </style>
</head>
<body>
    <article class="certificate">
        <div class="certificate-border-outer"></div>
        <div class="certificate-border-platinum"></div>
        <div class="certificate-border-rule"></div>
        <div class="corner-flourish corner-flourish-tl"></div>
        <div class="corner-flourish corner-flourish-tr"></div>
        <div class="corner-flourish corner-flourish-bl"></div>
        <div class="corner-flourish corner-flourish-br"></div>
        <div class="tassel tassel-tl"></div>
        <div class="tassel tassel-br"></div>
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
                    <span class="certificate-emblem-seal">⚜</span>
                    <span class="certificate-emblem-text"><?= certificateEscape($certificateCategoryName) ?></span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-ornament"></span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong><?php if ($course !== ''): ?> of <strong><?= certificateEscape($course) ?></strong><?php endif; ?> in the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
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
                    <div class="official-seal-content">Kingsway<span class="official-seal-ornament">⚜</span>Official Seal</div>
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