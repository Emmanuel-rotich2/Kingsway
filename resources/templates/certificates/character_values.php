<?php
/**
 * Character & Values Certificate Template
 *
 * Warm, friendly design (teal + soft orange, rounded frame with a gentle
 * scallop and heart motif, off-white paper) for values, kindness and
 * character recognition under welfare-owned types.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName, $certificateCategoryName
 * - $recipientName, $achievement, $academicYear, $certificateNumber,
 *   $dateAwarded
 * - $principalName ($principalTitle), $teacherName, $eventName
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
$teacherName = certificateValue($teacherName ?? null, 'Welfare Coordinator');
$recipientName = certificateValue($recipientName ?? null, 'Student Name');
$achievement = certificateValue($achievement ?? null, 'Outstanding Character and Values');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'CHARACTER & VALUES');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'VALUES CHAMPION');
$eventName = certificateValue($eventName ?? null, '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --teal: #0f766e;
            --teal-dark: #0b4f4a;
            --teal-light: #14b8a6;
            --orange: #d97706;
            --orange-soft: #fde9d0;
            --sand: #fdf9f1;
            --sand-soft: #fffdf8;
            --text: #23332f;
            --muted: #5c6f69;
            --white: #ffffff;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #ebe4d8; color: var(--text); font-family: Arial, Helvetica, sans-serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden;
            background: linear-gradient(150deg, #ffffff 0%, var(--sand-soft) 55%, var(--sand) 100%); }
        .certificate-border-outer { position: absolute; inset: 5mm; z-index: 5; border: 2.2mm solid var(--orange); border-radius: 7mm; pointer-events: none; }
        .certificate-border-teal { position: absolute; inset: 8mm; z-index: 5; border: 1mm solid var(--teal); border-radius: 5.5mm; pointer-events: none; }
        .certificate-border-dotted { position: absolute; inset: 10.5mm; z-index: 5; border: 0.5mm dotted var(--teal-light); border-radius: 4.5mm; pointer-events: none; }
        .heart { position: absolute; z-index: 7; width: 9mm; height: 9mm; color: var(--orange); font-size: 8.5pt; line-height: 1; pointer-events: none; }
        .heart::before { content: "♥"; }
        .heart-tl { top: 7mm; left: 10mm; }
        .heart-tr { top: 7mm; right: 10mm; }
        .heart-bl { bottom: 7mm; left: 10mm; }
        .heart-br { right: 10mm; bottom: 7mm; }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 40mm 25mm minmax(0, 1fr) 35mm;
            width: 100%; height: 100%; padding: 13mm 20mm 12mm; }
        .certificate-header { display: grid; grid-template-columns: 33mm minmax(0, 1fr) 33mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 30mm; height: 30mm;
            border: 1mm solid var(--teal); border-radius: 50%; background: var(--white); }
        .school-logo { display: block; width: 25mm; height: 25mm; object-fit: contain; border-radius: 50%; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--teal-dark); font-size: 23pt; font-weight: 800; letter-spacing: 1.3px; line-height: 1.05; text-transform: uppercase; }
        .school-motto { margin: 1.6mm 0 1.4mm; color: var(--orange); font-size: 11pt; font-style: italic; font-weight: 700; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-size: 7.5pt; line-height: 1.3; }
        .school-contact-separator { color: var(--orange); font-weight: 900; }
        .certificate-emblem { display: flex; flex-direction: column; align-items: center; justify-content: center; justify-self: end;
            width: 30mm; height: 30mm; border: 1mm solid var(--orange); border-radius: 50%;
            background: linear-gradient(145deg, var(--orange-soft) 0%, var(--sand) 100%); color: var(--teal-dark); text-align: center; }
        .certificate-emblem-heart { display: block; color: var(--orange); font-size: 12pt; line-height: 1; }
        .certificate-emblem-text { margin-top: 1.2mm; font-size: 6.4pt; font-weight: 800; letter-spacing: 0.4px; line-height: 1.35; text-transform: uppercase; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--teal-dark); font-size: 26pt; font-weight: 800; letter-spacing: 1.8px; line-height: 1.05; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--orange); }
        .certificate-title-divider { display: grid; grid-template-columns: 42mm 5mm 42mm; align-items: center; gap: 2.5mm; margin-top: 2.8mm; }
        .certificate-title-divider-line { height: 0.55mm; background: linear-gradient(90deg, var(--teal) 0%, var(--orange) 100%); }
        .certificate-title-divider-line:last-child { transform: scaleX(-1); }
        .certificate-title-divider-heart { color: var(--orange); font-size: 9pt; }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 14mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.4mm; color: var(--muted); font-size: 12pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 218mm; margin: 0; padding: 0 8mm 2.2mm; color: var(--teal-dark);
            font-size: 31pt; font-weight: 800; letter-spacing: 0.8px; line-height: 1.1; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.8mm;
            background: linear-gradient(90deg, transparent 0%, var(--orange) 15%, var(--orange) 85%, transparent 100%); }
        .certificate-recognition { max-width: 208mm; margin: 3mm 0 0; color: #33504a; font-size: 12.5pt; line-height: 1.55; }
        .certificate-recognition strong { color: var(--teal); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.2mm; padding: 1.4mm 7mm; border-radius: 7mm;
            background: var(--orange-soft); border: 0.5mm solid var(--orange); color: var(--orange); font-size: 13pt; font-weight: 800; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 43mm 1fr; align-items: end; gap: 11mm; padding: 2mm 14mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 10mm; }
        .signature-line { border-top: 0.45mm solid var(--teal-dark); padding-top: 1.6mm; }
        .signature-name { color: var(--teal-dark); font-size: 9.5pt; font-weight: 800; }
        .signature-title { margin-top: 0.6mm; color: var(--muted); font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 35mm; height: 35mm; justify-self: center;
            border: 1mm solid var(--orange); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, var(--sand) 72%, var(--orange-soft) 120%); box-shadow: inset 0 0 0 0.8mm var(--teal); }
        .official-seal-content { color: var(--teal-dark); font-size: 6.8pt; font-weight: 900; letter-spacing: 0.4px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-heart { display: block; margin: 0.6mm 0; color: var(--orange); font-size: 11pt; }
        .certificate-details { position: absolute; right: 17mm; bottom: 12mm; left: 17mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--teal-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-20deg); color: rgba(15, 118, 110, 0.04); font-size: 56pt; font-weight: 900;
            letter-spacing: 5px; white-space: nowrap; pointer-events: none; }
        .recipient-name.name-long { font-size: 25pt; }
        .recipient-name.name-extra-long { font-size: 21pt; }
        @media screen { body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 12mm; } .certificate { box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2); } }
        @media print { html, body { width: 297mm; height: 210mm; overflow: hidden; background: #ffffff !important; } body { display: block; padding: 0; } .certificate { width: 297mm; height: 210mm; margin: 0; box-shadow: none; break-after: avoid; page-break-after: avoid; } }
    </style>
</head>
<body>
    <article class="certificate">
        <div class="certificate-border-outer"></div>
        <div class="certificate-border-teal"></div>
        <div class="certificate-border-dotted"></div>
        <div class="heart heart-tl"></div>
        <div class="heart heart-tr"></div>
        <div class="heart heart-bl"></div>
        <div class="heart heart-br"></div>
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
                    <span class="certificate-emblem-heart">♥</span>
                    <span class="certificate-emblem-text"><?= certificateEscape($certificateCategoryName) ?></span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-heart">♥</span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong><?php if ($eventName !== ''): ?> during <strong><?= certificateEscape($eventName) ?></strong><?php endif; ?> during the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
                <div class="achievement-highlight"><?= certificateEscape($certificateCategoryName) ?></div>
            </main>
            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($teacherName) ?></div>
                        <div class="signature-title">Welfare Department</div>
                    </div>
                </div>
                <div class="official-seal">
                    <div class="official-seal-content">Kingsway<span class="official-seal-heart">♥</span>Official Seal</div>
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