<?php
/**
 * Spiritual & Chaplaincy Certificate Template
 *
 * Reverent, serene design (deep blue + gold, quiet single-rule frame, a gold
 * cross emblem and a Scripture verse strip) for Pathfinder honors, Bible
 * study mastery and chorister service under chaplaincy-owned types.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName, $certificateCategoryName
 * - $recipientName, $achievement, $academicYear, $certificateNumber,
 *   $dateAwarded
 * - $principalName ($principalTitle), $teacherName, $verseText
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
$teacherName = certificateValue($teacherName ?? null, 'Chaplain');
$recipientName = certificateValue($recipientName ?? null, 'Student Name');
$achievement = certificateValue($achievement ?? null, 'Faithful Spiritual Service');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'SPIRITUAL & CHAPLAINCY');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'SPIRITUAL & CHAPLAINCY');
$verseText = certificateValue($verseText ?? null, '“Let your light shine before others, that they may see your good deeds and glorify your Father in heaven.” &mdash; Matthew 5:16');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --indigo: #1e3a5f;
            --indigo-dark: #12233c;
            --indigo-light: #3a5f8f;
            --gold: #b98f2b;
            --gold-soft: #f0e2bc;
            --parchment: #faf6ec;
            --parchment-soft: #fffdf7;
            --text: #1e2a38;
            --muted: #54637a;
            --white: #ffffff;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #ddd8cd; color: var(--text); font-family: Georgia, "Times New Roman", serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden;
            background: linear-gradient(155deg, #ffffff 0%, var(--parchment-soft) 55%, var(--parchment) 100%); }
        .certificate-border { position: absolute; inset: 5mm; z-index: 5; border: 1.6mm solid var(--indigo); pointer-events: none; }
        .certificate-border-gold { position: absolute; inset: 7.5mm; z-index: 5; border: 0.8mm solid var(--gold); pointer-events: none; }
        .certificate-border-inner { position: absolute; inset: 10mm; z-index: 5; border: 0.3mm solid var(--indigo-light); pointer-events: none; }
        .cross { position: absolute; z-index: 6; width: 15mm; height: 22mm; border: 2.6mm solid var(--gold); pointer-events: none; opacity: 0.55; }
        .cross-h { position: absolute; top: 7mm; left: 1.5mm; width: 12mm; height: 2.6mm; background: var(--gold); }
        .cross::before { content: ""; position: absolute; left: 50%; transform: translateX(-50%); width: 2.6mm; height: 100%; background: var(--gold); }
        .cross-tl { top: 9mm; left: 11mm; }
        .cross-br { right: 11mm; bottom: 10mm; transform: scaleY(-1); }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 41mm 23mm minmax(0, 1fr) 37mm;
            width: 100%; height: 100%; padding: 14mm 22mm 12mm; }
        .certificate-header { display: grid; grid-template-columns: 34mm minmax(0, 1fr) 34mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 31mm; height: 31mm;
            border: 1mm solid var(--gold); border-radius: 50%; background: var(--white); box-shadow: 0 1.5mm 4mm rgba(18, 35, 60, 0.16); }
        .school-logo { display: block; width: 26mm; height: 26mm; object-fit: contain; border-radius: 50%; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--indigo-dark); font-size: 24pt; font-weight: 800; letter-spacing: 1.5px; line-height: 1.04; text-transform: uppercase; }
        .school-motto { margin: 1.8mm 0 1.5mm; color: var(--gold); font-size: 11.5pt; font-style: italic; font-weight: 700; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.3; }
        .school-contact-separator { color: var(--gold); font-weight: 900; }
        .certificate-emblem { display: flex; flex-direction: column; align-items: center; justify-content: center; justify-self: end;
            width: 31mm; height: 31mm; border: 1mm solid var(--gold); border-radius: 50%;
            background: radial-gradient(circle, var(--parchment-soft) 0%, var(--gold-soft) 100%); color: var(--indigo-dark); text-align: center; }
        .certificate-emblem-cross { display: block; color: var(--gold); font-size: 14pt; line-height: 1; font-weight: 900; }
        .certificate-emblem-text { margin-top: 1mm; font-family: Arial, Helvetica, sans-serif; font-size: 6.6pt; font-weight: 800; letter-spacing: 0.5px; line-height: 1.35; text-transform: uppercase; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--indigo-dark); font-size: 25pt; font-weight: 800; letter-spacing: 2px; line-height: 1.06; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--gold); font-style: italic; font-weight: 700; }
        .certificate-title-divider { display: grid; grid-template-columns: 42mm 6mm 42mm; align-items: center; gap: 2.5mm; margin-top: 2.6mm; }
        .certificate-title-divider-line { height: 0.5mm; background: linear-gradient(90deg, var(--indigo) 0%, var(--gold) 100%); }
        .certificate-title-divider-line:last-child { transform: scaleX(-1); }
        .certificate-title-divider-cross { color: var(--gold); font-size: 10pt; }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 15mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.5mm; color: var(--muted); font-size: 12pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 218mm; margin: 0; padding: 0 8mm 2.5mm; color: var(--indigo-dark);
            font-size: 30pt; font-weight: 800; letter-spacing: 0.9px; line-height: 1.1; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.8mm;
            background: linear-gradient(90deg, transparent 0%, var(--gold) 13%, var(--gold) 87%, transparent 100%); }
        .certificate-recognition { max-width: 210mm; margin: 3.5mm 0 0; color: #2c3c55; font-size: 12.5pt; line-height: 1.55; }
        .certificate-recognition strong { color: var(--indigo); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.5mm; padding: 1.5mm 7mm; border-top: 0.35mm solid var(--gold);
            border-bottom: 0.35mm solid var(--gold); background: rgba(240, 226, 188, 0.55); color: var(--gold); font-size: 13pt; font-weight: 800; }
        .verse-strip { margin: 2.6mm auto 0; max-width: 190mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.8pt; font-style: italic; line-height: 1.5; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 44mm 1fr; align-items: end; gap: 11mm; padding: 2mm 14mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 10mm; }
        .signature-line { border-top: 0.45mm solid var(--indigo-dark); padding-top: 1.7mm; }
        .signature-name { color: var(--indigo-dark); font-size: 10pt; font-weight: 800; }
        .signature-title { margin-top: 0.7mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 37mm; height: 37mm; justify-self: center;
            border: 1.2mm solid var(--gold); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, var(--parchment) 72%, var(--gold-soft) 130%); box-shadow: inset 0 0 0 1mm var(--indigo); }
        .official-seal::before { content: ""; position: absolute; inset: 3.2mm; border: 0.45mm dashed var(--gold); border-radius: 50%; }
        .official-seal-content { position: relative; z-index: 2; color: var(--indigo-dark); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; font-weight: 900; letter-spacing: 0.6px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-cross { display: block; margin: 0.8mm 0; color: var(--gold); font-size: 12pt; line-height: 1; }
        .certificate-details { position: absolute; right: 18mm; bottom: 13mm; left: 18mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--indigo-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-24deg); color: rgba(30, 58, 95, 0.04); font-size: 56pt; font-weight: 900;
            letter-spacing: 5px; white-space: nowrap; pointer-events: none; }
        .recipient-name.name-long { font-size: 24pt; }
        .recipient-name.name-extra-long { font-size: 20pt; }
        @media screen { body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 12mm; } .certificate { box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2); } }
        @media print { html, body { width: 297mm; height: 210mm; overflow: hidden; background: #ffffff !important; } body { display: block; padding: 0; } .certificate { width: 297mm; height: 210mm; margin: 0; box-shadow: none; break-after: avoid; page-break-after: avoid; } }
    </style>
</head>
<body>
    <article class="certificate">
        <div class="certificate-border"></div>
        <div class="certificate-border-gold"></div>
        <div class="certificate-border-inner"></div>
        <div class="cross cross-tl"></div>
        <div class="cross cross-br"></div>
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
                    <span class="certificate-emblem-cross">✝</span>
                    <span class="certificate-emblem-text"><?= certificateEscape($certificateCategoryName) ?></span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-cross">✝</span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong> during the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
                <div class="achievement-highlight"><?= certificateEscape($certificateTypeName) ?></div>
                <div class="verse-strip"><?= $verseText ?></div>
            </main>
            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($teacherName) ?></div>
                        <div class="signature-title">Chaplaincy</div>
                    </div>
                </div>
                <div class="official-seal">
                    <div class="official-seal-content">Kingsway<span class="official-seal-cross">✝</span>Official Seal</div>
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