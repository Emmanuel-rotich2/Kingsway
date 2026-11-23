<?php
/**
 * Leadership & Service Certificate Template
 *
 * Formal, stately design (deep maroon-red + gold, star cornerments, double-rule
 * frame) for prefect/office roles, community service and peer mentorship under
 * department-owned leadership types.
 *
 * Expected variables:
 * - $schoolName, $schoolMotto, $schoolLogo, $schoolAddress, $schoolPhone,
 *   $schoolEmail, $schoolWebsite
 * - $certificateTypeName   (e.g. "Leadership & Service")
 * - $certificateCategoryName
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
$teacherName = certificateValue($teacherName ?? null, 'Deputy Headteacher');
$recipientName = certificateValue($recipientName ?? null, 'Student Name');
$achievement = certificateValue($achievement ?? null, 'Outstanding Leadership and Service');
$academicYear = certificateValue($academicYear ?? null, date('Y'));
$certificateNumber = certificateValue($certificateNumber ?? null, 'KPS-' . date('YmdHis'));
$dateAwarded = certificateValue($dateAwarded ?? null, date('d F Y'));
$certificateTypeName = certificateValue($certificateTypeName ?? null, 'LEADERSHIP & SERVICE');
$certificateCategoryName = certificateValue($certificateCategoryName ?? null, 'LEADERSHIP & SERVICE');
$eventName = certificateValue($eventName ?? null, '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= certificateEscape($recipientName) ?> - <?= certificateEscape($certificateTypeName) ?></title>
    <style>
        :root {
            --crimson: #8b1e1e;
            --crimson-dark: #5f1111;
            --crimson-light: #b23a3a;
            --gold: #bf9b30;
            --gold-dark: #8f6f13;
            --ivory: #fbf7ef;
            --ivory-soft: #fffdf8;
            --text: #2a2118;
            --muted: #5f5340;
            --white: #ffffff;
        }
        @page { size: A4 landscape; margin: 0; }
        *, *::before, *::after { box-sizing: border-box; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        html, body { width: 297mm; height: 210mm; margin: 0; padding: 0; }
        body { overflow: hidden; background: #e7e0d3; color: var(--text); font-family: Georgia, "Times New Roman", serif; }
        .certificate { position: relative; width: 297mm; height: 210mm; margin: 0 auto; overflow: hidden;
            background: radial-gradient(circle at center, rgba(255, 255, 255, 0.97) 0%, var(--ivory-soft) 62%, var(--ivory) 100%); }
        .certificate-border-outer { position: absolute; inset: 5mm; z-index: 5; border: 2.8mm solid var(--crimson-dark); pointer-events: none; }
        .certificate-border-gold { position: absolute; inset: 8.8mm; z-index: 5; border: 1.3mm solid var(--gold); pointer-events: none; }
        .certificate-border-inner { position: absolute; inset: 12mm; z-index: 5; border: 0.4mm solid var(--crimson); pointer-events: none; }
        .corner-star { position: absolute; z-index: 7; width: 20mm; height: 20mm; color: var(--gold); font-size: 17pt;
            display: flex; align-items: center; justify-content: center; pointer-events: none; }
        .corner-star::before { content: "★"; }
        .corner-star-top-left { top: 6.5mm; left: 6.5mm; }
        .corner-star-top-right { top: 6.5mm; right: 6.5mm; }
        .corner-star-bottom-left { bottom: 6.5mm; left: 6.5mm; }
        .corner-star-bottom-right { right: 6.5mm; bottom: 6.5mm; }
        .certificate-content { position: relative; z-index: 10; display: grid; grid-template-rows: 41mm 25mm minmax(0, 1fr) 36mm;
            width: 100%; height: 100%; padding: 14mm 22mm 12mm; }
        .certificate-header { display: grid; grid-template-columns: 34mm minmax(0, 1fr) 34mm; align-items: center; gap: 5mm; }
        .school-logo-wrapper { display: flex; align-items: center; justify-content: center; width: 31mm; height: 31mm;
            border: 1mm solid var(--gold); border-radius: 50%; background: var(--white); box-shadow: 0 1.5mm 4mm rgba(95, 17, 17, 0.16); }
        .school-logo { display: block; width: 26mm; height: 26mm; object-fit: contain; border-radius: 50%; }
        .school-identity { min-width: 0; text-align: center; }
        .school-name { margin: 0; color: var(--crimson-dark); font-size: 25pt; font-weight: 800; letter-spacing: 1.6px; line-height: 1.04; text-transform: uppercase; }
        .school-motto { margin: 1.8mm 0 1.5mm; color: var(--gold-dark); font-size: 11.5pt; font-style: italic; font-weight: 700; }
        .school-contact { display: flex; flex-wrap: wrap; justify-content: center; gap: 1mm 2.5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; line-height: 1.3; }
        .school-contact-separator { color: var(--gold-dark); font-weight: 900; }
        .certificate-emblem { display: flex; flex-direction: column; align-items: center; justify-content: center; justify-self: end;
            width: 31mm; height: 31mm; border: 1mm solid var(--crimson); border-radius: 50%;
            background: linear-gradient(145deg, var(--ivory-soft) 0%, var(--ivory) 100%); color: var(--crimson-dark); text-align: center; }
        .certificate-emblem-star { display: block; color: var(--gold-dark); font-size: 13pt; }
        .certificate-emblem-text { margin-top: 0.8mm; font-family: Arial, Helvetica, sans-serif; font-size: 6.6pt; font-weight: 800; letter-spacing: 0.5px; line-height: 1.35; text-transform: uppercase; }
        .certificate-title-section { display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .certificate-title { margin: 0; color: var(--crimson-dark); font-size: 27pt; font-weight: 800; letter-spacing: 2.1px; line-height: 1.05; text-align: center; text-transform: uppercase; }
        .certificate-title-gold { color: var(--gold-dark); }
        .certificate-title-divider { display: grid; grid-template-columns: 44mm 5mm 44mm; align-items: center; gap: 2.5mm; margin-top: 3mm; }
        .certificate-title-divider-line { height: 0.6mm; background: linear-gradient(90deg, transparent 0%, var(--gold) 25%, var(--gold) 100%); }
        .certificate-title-divider-line:last-child { transform: scaleX(-1); }
        .certificate-title-divider-star { color: var(--crimson); font-size: 10pt; }
        .certificate-body { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 0; padding: 1mm 15mm 0; text-align: center; }
        .presented-to { margin: 0 0 1.5mm; color: var(--muted); font-size: 12pt; font-style: italic; }
        .recipient-name { position: relative; max-width: 220mm; margin: 0; padding: 0 8mm 2.5mm; color: var(--crimson-dark);
            font-size: 31pt; font-weight: 800; letter-spacing: 1px; line-height: 1.08; text-align: center; text-transform: uppercase; overflow-wrap: anywhere; }
        .recipient-name::after { content: ""; position: absolute; right: 0; bottom: 0; left: 0; height: 0.8mm;
            background: linear-gradient(90deg, transparent 0%, var(--gold) 13%, var(--gold) 87%, transparent 100%); }
        .certificate-recognition { max-width: 212mm; margin: 3.5mm 0 0; color: #453627; font-size: 12.5pt; line-height: 1.55; }
        .certificate-recognition strong { color: var(--crimson); font-weight: 800; }
        .achievement-highlight { display: inline-block; margin-top: 2.5mm; padding: 1.5mm 7mm; border-right: 0.8mm solid var(--gold);
            border-left: 0.8mm solid var(--gold); background: rgba(251, 247, 239, 0.85); color: var(--gold-dark); font-size: 13.5pt; font-weight: 800; }
        .certificate-footer { position: relative; display: grid; grid-template-columns: 1fr 44mm 1fr; align-items: end; gap: 11mm; padding: 2mm 14mm 0; }
        .signature-section { text-align: center; }
        .signature-space { height: 11mm; }
        .signature-line { border-top: 0.45mm solid var(--crimson-dark); padding-top: 1.7mm; }
        .signature-name { color: var(--crimson-dark); font-size: 10pt; font-weight: 800; }
        .signature-title { margin-top: 0.7mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7.5pt; font-style: italic; }
        .official-seal { display: flex; align-items: center; justify-content: center; width: 37mm; height: 37mm; justify-self: center;
            border: 1.2mm solid var(--gold); border-radius: 50%;
            background: radial-gradient(circle, #ffffff 0%, var(--ivory) 72%, var(--gold) 130%); box-shadow: inset 0 0 0 1mm var(--crimson), 0 1.5mm 4mm rgba(0, 0, 0, 0.12); }
        .official-seal::before { content: ""; position: absolute; inset: 3.2mm; border: 0.45mm dashed var(--gold-dark); border-radius: 50%; }
        .official-seal-content { position: relative; z-index: 2; color: var(--crimson-dark); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; font-weight: 900; letter-spacing: 0.6px; line-height: 1.35; text-align: center; text-transform: uppercase; }
        .official-seal-star { display: block; margin: 0.8mm 0; color: var(--gold-dark); font-size: 12pt; }
        .certificate-details { position: absolute; right: 18mm; bottom: 13mm; left: 18mm; z-index: 15;
            display: flex; justify-content: space-between; gap: 5mm; color: var(--muted); font-family: Arial, Helvetica, sans-serif; font-size: 7pt; line-height: 1.2; }
        .certificate-detail strong { color: var(--crimson-dark); }
        .certificate-watermark { position: absolute; top: 53%; left: 50%; z-index: 1;
            transform: translate(-50%, -50%) rotate(-24deg); color: rgba(139, 30, 30, 0.04); font-size: 58pt; font-weight: 900;
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
        <div class="certificate-border-gold"></div>
        <div class="certificate-border-inner"></div>
        <div class="corner-star corner-star-top-left"></div>
        <div class="corner-star corner-star-top-right"></div>
        <div class="corner-star corner-star-bottom-left"></div>
        <div class="corner-star corner-star-bottom-right"></div>
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
                    <span class="certificate-emblem-text"><?= certificateEscape($certificateCategoryName) ?></span>
                </div>
            </header>
            <section class="certificate-title-section">
                <h2 class="certificate-title">Certificate of <span class="certificate-title-gold"><?= certificateEscape($certificateTypeName) ?></span></h2>
                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-star">★</span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>
            <main class="certificate-body">
                <p class="presented-to">This certificate is proudly presented to</p>
                <?php $recipientLength = mb_strlen($recipientName); $recipientClass = $recipientLength > 42 ? 'name-extra-long' : ($recipientLength > 28 ? 'name-long' : ''); ?>
                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>"><?= certificateEscape($recipientName) ?></h3>
                <p class="certificate-recognition">In recognition of <strong><?= certificateEscape($achievement) ?></strong><?php if ($eventName !== ''): ?> during <strong><?= certificateEscape($eventName) ?></strong><?php endif; ?> during the <strong><?= certificateEscape($academicYear) ?> Academic Year</strong>.</p>
                <div class="achievement-highlight"><?= certificateEscape($certificateTypeName) ?></div>
            </main>
            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>
                    <div class="signature-line">
                        <div class="signature-name"><?= certificateEscape($teacherName) ?></div>
                        <div class="signature-title">Head of School Appointee</div>
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