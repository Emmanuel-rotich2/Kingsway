<?php
/**
 * Co-Curricular & Talent Certificate Template
 *
 * Distinct energetic design (navy + gold with a medal/trophy motif) for
 * sports participation, music festival, performing arts, visual arts and
 * club/society recognition under department-owned types.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName   (e.g. "Music Festival" / "Visual Arts")
 * - $certificateCategoryName
 * - $recipientName, $achievement, $academicYear, $certificateNumber,
 *   $dateAwarded
 * - $principalName ($principalTitle), $teacherName, $sport, $eventName
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
$teacherName = certificateValue($teacherName ?? null, 'Arts & Culture Coordinator');
$recipientName = certificateValue($recipientName ?? null, 'Student Name');
$achievement = certificateValue($achievement ?? null, 'Outstanding Co-Curricular Achievement');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'CO-CURRICULAR ACHIEVEMENT');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'CO-CURRICULAR & TALENT');
$sport = certificateValue($sport ?? null, '');
$eventName = certificateValue($eventName ?? null, '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --navy: #002d62;
            --navy-dark: #001b3d;
            --navy-light: #1a4d8c;
            --gold: #c9a227;
            --gold-dark: #8f6f13;
            --royal: #5b2a86;
            --paper: #f4f9ff;
            --text: #15243a;
            --muted: #4c5c75;
            --white: #ffffff;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #dde4ee; color: var(--text); font-family: Arial, Helvetica, sans-serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden;
            background: linear-gradient(160deg, #ffffff 0%, var(--paper) 55%, #e3edfb 100%); }
        .certificate-border-outer { position: absolute; inset: 5mm; z-index: 5; border: 2.4mm solid var(--navy); pointer-events: none; }
        .certificate-border-gold { position: absolute; inset: 8.5mm; z-index: 5; border: 1.1mm dashed var(--gold); pointer-events: none; }
        .certificate-border-inner { position: absolute; inset: 11mm; z-index: 5; border: 0.4mm solid var(--navy-light); pointer-events: none; }
        .ribbon-bar { position: absolute; top: 0; left: 0; z-index: 6; height: 4.5mm; width: 100%;
            background: linear-gradient(90deg, var(--navy) 0%, var(--royal) 45%, var(--gold) 100%); pointer-events: none; }
        .medal { position: absolute; z-index: 7; width: 26mm; height: 26mm; border-radius: 50%; border: 1mm solid var(--gold);
            background: radial-gradient(circle at 35% 30%, var(--navy-light) 0%, var(--navy) 62%, var(--navy-dark) 100%);
            box-shadow: 0 1.5mm 4mm rgba(0, 29, 61, 0.3); }
        .medal::after { content: ""; position: absolute; inset: 4mm; border: 0.45mm dashed var(--gold); border-radius: 50%; }
        .medal::before { content: "★"; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            color: var(--gold); font-size: 15pt; }
        .medal-top-left { top: 13mm; left: 14mm; }
        .medal-bottom-right { right: 14mm; bottom: 15mm; transform: scaleX(-1); }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 40mm 26mm minmax(0, 1fr) 34mm;
            width: 100%; height: 100%; padding: 14mm 22mm 12mm; }
        .certificate-header { display: grid; grid-template-columns: 33mm minmax(0, 1fr) 33mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 30mm; height: 30mm;
            border: 1mm solid var(--navy); border-radius: 12%; background: var(--white); }
        .school-logo { display: block; width: 25mm; height: 25mm; object-fit: contain; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--navy); font-size: 24pt; font-weight: 800; letter-spacing: 1.4px; line-height: 1.04; text-transform: uppercase; }
        .school-motto { margin: 1.6mm 0 1.4mm; color: var(--gold-dark); font-size: 11pt; font-style: italic; font-weight: 700; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-size: 7.5pt; line-height: 1.3; }
        .school-contact-separator { color: var(--gold-dark); font-weight: 900; }
        .certificate-emblem { display: flex; flex-direction: column; align-items: center; justify-content: center; justify-self: end;
            width: 30mm; height: 30mm; border: 1mm solid var(--gold); border-radius: 12%;
            background: linear-gradient(160deg, var(--navy) 0%, var(--navy-dark) 100%); color: var(--white); text-align: center; }
        .certificate-emblem-star { display: block; color: var(--gold); font-size: 12pt; }
        .certificate-emblem-text { margin-top: 1mm; font-size: 6.6pt; font-weight: 800; letter-spacing: 0.5px; line-height: 1.35; text-transform: uppercase; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--navy-dark); font-size: 26pt; font-weight: 800; letter-spacing: 2px; line-height: 1.05; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--royal); }
        .certificate-title-divider { display: grid; grid-template-columns: 42mm 4mm 42mm; align-items: center; gap: 2.5mm; margin-top: 2.8mm; }
        .certificate-title-divider-line { height: 0.55mm; background: linear-gradient(90deg, var(--navy) 0%, var(--gold) 100%); }
        .certificate-title-divider-line:last-child { transform: scaleX(-1); }
        .certificate-title-divider-diamond { width: 3.8mm; height: 3.8mm; transform: rotate(45deg); background: var(--royal); }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 15mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.2mm; color: var(--muted); font-size: 12pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 220mm; margin: 0; padding: 0 8mm 2.2mm; color: var(--navy-dark); font-size: 32pt;
            font-weight: 800; letter-spacing: 1px; line-height: 1.08; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.8mm;
            background: linear-gradient(90deg, transparent 0%, var(--gold) 15%, var(--gold) 85%, transparent 100%); }
        .certificate-recognition { max-width: 214mm; margin: 3mm 0 0; color: #2b3d5c; font-size: 12.5pt; line-height: 1.55; }
        .certificate-recognition strong { color: var(--navy); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.2mm; padding: 1.4mm 7mm; border-radius: 6mm;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-dark) 100%); color: var(--white); font-size: 13pt; font-weight: 800; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 44mm 1fr; align-items: end; gap: 11mm; padding: 2mm 14mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 10mm; }
        .signature-line { border-top: 0.45mm solid var(--navy-dark); padding-top: 1.6mm; }
        .signature-name { color: var(--navy-dark); font-size: 9.5pt; font-weight: 800; }
        .signature-title { margin-top: 0.6mm; color: var(--muted); font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 36mm; height: 36mm; justify-self: center;
            border: 1.2mm solid var(--gold); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, var(--paper) 70%, #d7e5f7 100%); box-shadow: inset 0 0 0 0.8mm var(--navy); }
        .official-seal-content { color: var(--navy-dark); font-size: 6.8pt; font-weight: 900; letter-spacing: 0.5px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-star { display: block; margin: 0.6mm 0; color: var(--gold-dark); font-size: 12pt; }
        .certificate-details { position: absolute; right: 18mm; bottom: 12.5mm; left: 18mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--navy-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-22deg); color: rgba(0, 45, 98, 0.04); font-size: 58pt; font-weight: 900;
            letter-spacing: 5px; white-space: nowrap; pointer-events: none; }
        .recipient-name.name-long { font-size: 26pt; }
        .recipient-name.name-extra-long { font-size: 22pt; }
        @media screen { body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 12mm; } .certificate { box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2); } }
        @media print { html, body { width: 297mm; height: 210mm; overflow: hidden; background: #ffffff !important; } body { display: block; padding: 0; } .certificate { width: 297mm; height: 210mm; margin: 0; box-shadow: none; break-after: avoid; page-break-after: avoid; } }
    </style>
</head>
<body>
    <article class="certificate">
        <div class="ribbon-bar"></div>
        <div class="certificate-border-outer"></div>
        <div class="certificate-border-gold"></div>
        <div class="certificate-border-inner"></div>
        <div class="medal medal-top-left"></div>
        <div class="medal medal-bottom-right"></div>
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
                    <span class="certificate-emblem-star">★</span>
                    <span class="certificate-emblem-text">Talent &amp;<br><?= certificateEscape($certificateCategoryName) ?></span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-diamond"></span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong><?php if ($sport !== ''): ?> in <strong><?= certificateEscape($sport) ?></strong><?php endif; ?><?php if ($eventName !== ''): ?> during <strong><?= certificateEscape($eventName) ?></strong><?php endif; ?> during the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
                <div class="achievement-highlight"><?= certificateEscape($certificateTypeName) ?></div>
            </main>
            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($teacherName) ?></div>
                        <div class="signature-title">Department Coordinator</div>
                    </div>
                </div>
                <div class="official-seal">
                    <div class="official-seal-content">Kingsway<span class="official-seal-star">★</span>Official Seal</div>
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