<?php
/**
 * Sports Achievement Certificate Template
 *
 * Expected variables:
 * - $schoolName
 * - $schoolMotto
 * - $schoolLogo
 * - $schoolAddress
 * - $schoolPhone
 * - $schoolEmail
 * - $schoolWebsite
 * - $principalName
 * - $principalTitle
 * - $recipientName
 * - $achievement
 * - $academicYear
 * - $sport
 * - $certificateNumber
 * - $dateAwarded
 * - $sportsCoordinatorName
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Accept values passed by PrintManager
|--------------------------------------------------------------------------
*/

$schoolName = $_GET['schoolName'] ?? $schoolName ?? null;
$schoolMotto = $_GET['schoolMotto'] ?? $schoolMotto ?? null;
$schoolLogo = $_GET['schoolLogo'] ?? $schoolLogo ?? null;
$schoolAddress = $_GET['schoolAddress'] ?? $schoolAddress ?? null;
$schoolPhone = $_GET['schoolPhone'] ?? $schoolPhone ?? null;
$schoolEmail = $_GET['schoolEmail'] ?? $schoolEmail ?? null;
$schoolWebsite = $_GET['schoolWebsite'] ?? $schoolWebsite ?? null;

$principalName = $_GET['principalName'] ?? $principalName ?? null;
$principalTitle = $_GET['principalTitle'] ?? $principalTitle ?? null;

$sportsCoordinatorName =
    $_GET['sportsCoordinatorName']
    ?? $sportsCoordinatorName
    ?? null;

$recipientName = $_GET['recipientName'] ?? $recipientName ?? null;
$achievement = $_GET['achievement'] ?? $achievement ?? null;
$academicYear = $_GET['academicYear'] ?? $academicYear ?? null;
$sport = $_GET['sport'] ?? $sport ?? null;
$certificateNumber =
    $_GET['certificateNumber']
    ?? $certificateNumber
    ?? null;

$dateAwarded = $_GET['dateAwarded'] ?? $dateAwarded ?? null;

/**
 * Escape output safely.
 */
function sportsCertificateEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Return a fallback for empty values.
 */
function sportsCertificateValue(
    mixed $value,
    string $fallback = ''
): string {
    $value = trim((string) ($value ?? ''));

    return $value !== '' ? $value : $fallback;
}

/*
|--------------------------------------------------------------------------
| Default values
|--------------------------------------------------------------------------
*/

$schoolName = sportsCertificateValue(
    $schoolName,
    'KINGSWAY PREPARATORY SCHOOL'
);

$schoolMotto = sportsCertificateValue(
    $schoolMotto,
    'In God We Soar'
);

$schoolLogo = sportsCertificateValue(
    $schoolLogo,
    '/uploads/school_assets/official_school_logo.png'
);

$schoolAddress = sportsCertificateValue(
    $schoolAddress,
    'P.O. Box 203-20203, Londiani, Kericho County, Kenya'
);

$schoolPhone = sportsCertificateValue(
    $schoolPhone,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = sportsCertificateValue(
    $schoolEmail,
    'info@kingswaypreparatoryschool.sc.ke'
);

$schoolWebsite = sportsCertificateValue(
    $schoolWebsite,
    'www.kingswaypreparatoryschool.sc.ke'
);

$principalName = sportsCertificateValue(
    $principalName,
    'Headteacher'
);

$principalTitle = sportsCertificateValue(
    $principalTitle,
    'Headteacher'
);

$sportsCoordinatorName = sportsCertificateValue(
    $sportsCoordinatorName,
    'Sports Coordinator'
);

$recipientName = sportsCertificateValue(
    $recipientName,
    'Student Name'
);

$achievement = sportsCertificateValue(
    $achievement,
    'Outstanding Athletic Performance'
);

$sport = sportsCertificateValue(
    $sport,
    'Sports and Athletics'
);

$academicYear = sportsCertificateValue(
    $academicYear,
    date('Y')
);

$certificateNumber = sportsCertificateValue(
    $certificateNumber,
    'KPS-SPT-' . date('YmdHis')
);

$dateAwarded = sportsCertificateValue(
    $dateAwarded,
    date('d F Y')
);

/*
|--------------------------------------------------------------------------
| Dynamic size classes
|--------------------------------------------------------------------------
*/

$recipientLength = function_exists('mb_strlen')
    ? mb_strlen($recipientName)
    : strlen($recipientName);

$achievementLength = function_exists('mb_strlen')
    ? mb_strlen($achievement)
    : strlen($achievement);

$sportLength = function_exists('mb_strlen')
    ? mb_strlen($sport)
    : strlen($sport);

$recipientClass = '';

if ($recipientLength > 42) {
    $recipientClass = 'recipient-name-extra-long';
} elseif ($recipientLength > 28) {
    $recipientClass = 'recipient-name-long';
}

$achievementClass = $achievementLength > 75
    ? 'achievement-long'
    : '';

$sportClass = $sportLength > 45
    ? 'sport-name-long'
    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?= sportsCertificateEscape($recipientName) ?>
        - Sports Achievement Certificate
    </title>

    <style>
        :root {
            --school-green: #0f5b3b;
            --school-green-dark: #083f2b;
            --school-green-light: #19734d;

            --school-gold: #d3ad24;
            --school-gold-dark: #a88612;
            --school-gold-light: #f2dc82;

            --school-cream: #fff8df;
            --school-cream-soft: #fffdf4;

            --school-text: #1b2a23;
            --school-muted: #52645a;
            --school-white: #ffffff;
        }

        @page {
            size: A4 landscape;
            margin: 0;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        html,
        body {
            width: 297mm;
            height: 210mm;
            margin: 0;
            padding: 0;
        }

        body {
            overflow: hidden;
            background: #dfe5e1;
            color: var(--school-text);
            font-family: Georgia, "Times New Roman", serif;
        }

        .certificate {
            position: relative;
            width: 297mm;
            height: 210mm;
            margin: 0 auto;
            overflow: hidden;
            background:
                radial-gradient(circle at center,
                    rgba(255, 255, 255, 0.98) 0%,
                    rgba(255, 253, 244, 0.98) 64%,
                    var(--school-cream) 100%);
        }

        /* ================================================================
           Certificate borders
           ================================================================ */

        .certificate-border-outer {
            position: absolute;
            inset: 5mm;
            z-index: 5;
            border: 3mm solid var(--school-green-dark);
            pointer-events: none;
        }

        .certificate-border-gold {
            position: absolute;
            inset: 9mm;
            z-index: 5;
            border: 1.4mm solid var(--school-gold);
            pointer-events: none;
        }

        .certificate-border-inner {
            position: absolute;
            inset: 12mm;
            z-index: 5;
            border: 0.45mm solid var(--school-green);
            pointer-events: none;
        }

        /* ================================================================
           Decorative corners
           ================================================================ */

        .certificate-corner {
            position: absolute;
            z-index: 7;
            width: 31mm;
            height: 31mm;
            pointer-events: none;
        }

        .certificate-corner::before,
        .certificate-corner::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            background: var(--school-gold);
        }

        .certificate-corner::before {
            width: 31mm;
            height: 3mm;
        }

        .certificate-corner::after {
            width: 3mm;
            height: 31mm;
        }

        .certificate-corner-top-left {
            top: 8mm;
            left: 8mm;
        }

        .certificate-corner-top-right {
            top: 8mm;
            right: 8mm;
            transform: scaleX(-1);
        }

        .certificate-corner-bottom-left {
            bottom: 8mm;
            left: 8mm;
            transform: scaleY(-1);
        }

        .certificate-corner-bottom-right {
            right: 8mm;
            bottom: 8mm;
            transform: scale(-1);
        }

        /* ================================================================
           Decorative background
           ================================================================ */

        .background-pattern {
            position: absolute;
            z-index: 2;
            width: 74mm;
            height: 74mm;
            border: 0.5mm solid rgba(211, 173, 36, 0.14);
            border-radius: 50%;
            pointer-events: none;
        }

        .background-pattern::before,
        .background-pattern::after {
            content: "";
            position: absolute;
            border-radius: 50%;
        }

        .background-pattern::before {
            inset: 8mm;
            border: 0.4mm solid rgba(15, 91, 59, 0.09);
        }

        .background-pattern::after {
            inset: 18mm;
            border: 0.35mm solid rgba(211, 173, 36, 0.11);
        }

        .background-pattern-left {
            top: -34mm;
            left: -31mm;
        }

        .background-pattern-right {
            right: -31mm;
            bottom: -34mm;
        }

        .certificate-watermark {
            position: absolute;
            top: 53%;
            left: 50%;
            z-index: 1;
            transform: translate(-50%, -50%) rotate(-24deg);
            color: rgba(15, 91, 59, 0.034);
            font-size: 62pt;
            font-weight: 900;
            letter-spacing: 7px;
            line-height: 1;
            white-space: nowrap;
            pointer-events: none;
        }

        /* ================================================================
           Main layout
           ================================================================ */

        .certificate-content {
            position: relative;
            z-index: 10;
            display: grid;
            grid-template-rows:
                42mm 23mm minmax(0, 1fr) 36mm;
            width: 100%;
            height: 100%;
            padding: 15mm 22mm 13mm;
        }

        /* ================================================================
           School header
           ================================================================ */

        .certificate-header {
            display: grid;
            grid-template-columns: 35mm minmax(0, 1fr) 35mm;
            align-items: center;
            gap: 5mm;
        }

        .school-logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 31mm;
            height: 31mm;
            border: 1mm solid var(--school-gold);
            border-radius: 50%;
            background: var(--school-white);
            box-shadow: 0 1.5mm 4mm rgba(8, 63, 43, 0.16);
        }

        .school-logo {
            display: block;
            width: 26mm;
            height: 26mm;
            object-fit: contain;
            border-radius: 50%;
        }

        .school-identity {
            min-width: 0;
            text-align: center;
        }

        .school-name {
            margin: 0;
            color: var(--school-green);
            font-size: 25pt;
            font-weight: 800;
            letter-spacing: 1.6px;
            line-height: 1.04;
            text-transform: uppercase;
        }

        .school-motto {
            margin: 1.8mm 0 1.5mm;
            color: var(--school-gold-dark);
            font-size: 11.5pt;
            font-style: italic;
            font-weight: 700;
        }

        .school-contact {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 1mm 2.5mm;
            color: var(--school-muted);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.4pt;
            line-height: 1.3;
        }

        .school-contact-separator {
            color: var(--school-gold-dark);
            font-weight: 900;
        }

        /* ================================================================
           Trophy emblem
           ================================================================ */

        .sports-emblem {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            justify-self: end;
            width: 31mm;
            height: 31mm;
            border: 1mm solid var(--school-green);
            border-radius: 50%;
            background:
                radial-gradient(circle,
                    var(--school-white) 0%,
                    var(--school-cream) 100%);
        }

        .trophy {
            position: relative;
            width: 19mm;
            height: 22mm;
        }

        .trophy-cup {
            position: absolute;
            top: 1mm;
            left: 50%;
            width: 12mm;
            height: 10mm;
            transform: translateX(-50%);
            border: 1mm solid var(--school-gold-dark);
            border-top: 0;
            border-radius: 0 0 7mm 7mm;
            background: var(--school-gold);
        }

        .trophy-handle {
            position: absolute;
            top: 2mm;
            width: 6mm;
            height: 7mm;
            border: 1mm solid var(--school-gold-dark);
            border-radius: 50%;
        }

        .trophy-handle-left {
            left: 0;
        }

        .trophy-handle-right {
            right: 0;
        }

        .trophy-stem {
            position: absolute;
            top: 11mm;
            left: 50%;
            width: 2mm;
            height: 6mm;
            transform: translateX(-50%);
            background: var(--school-green-dark);
        }

        .trophy-base {
            position: absolute;
            bottom: 1mm;
            left: 50%;
            width: 13mm;
            height: 3mm;
            transform: translateX(-50%);
            border-radius: 1mm;
            background: var(--school-green-dark);
        }

        .trophy-star {
            position: absolute;
            top: 3.2mm;
            left: 50%;
            z-index: 3;
            transform: translateX(-50%);
            color: var(--school-green-dark);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            font-weight: 900;
        }

        /* ================================================================
           Certificate title
           ================================================================ */

        .certificate-title-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .certificate-title {
            margin: 0;
            color: var(--school-green-dark);
            font-size: 27pt;
            font-weight: 800;
            letter-spacing: 2.1px;
            line-height: 1.05;
            text-align: center;
            text-transform: uppercase;
        }

        .certificate-title-highlight {
            color: var(--school-gold-dark);
        }

        .certificate-title-divider {
            display: grid;
            grid-template-columns: 44mm 5mm 44mm;
            align-items: center;
            gap: 2.5mm;
            margin-top: 3mm;
        }

        .certificate-title-divider-line {
            height: 0.6mm;
            background:
                linear-gradient(90deg,
                    transparent 0%,
                    var(--school-gold) 25%,
                    var(--school-gold) 100%);
        }

        .certificate-title-divider-line:last-child {
            transform: scaleX(-1);
        }

        .certificate-title-divider-diamond {
            width: 4mm;
            height: 4mm;
            transform: rotate(45deg);
            background: var(--school-green);
        }

        /* ================================================================
           Certificate body
           ================================================================ */

        .certificate-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 1mm 13mm 0;
            text-align: center;
        }

        .presented-to {
            margin: 0 0 1.4mm;
            color: var(--school-muted);
            font-size: 12pt;
            font-style: italic;
        }

        .recipient-name {
            position: relative;
            max-width: 225mm;
            margin: 0;
            padding: 0 8mm 2.5mm;
            color: var(--school-green-dark);
            font-size: 29pt;
            font-weight: 800;
            letter-spacing: 1.1px;
            line-height: 1.08;
            text-align: center;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .recipient-name::after {
            content: "";
            position: absolute;
            right: 0;
            bottom: 0;
            left: 0;
            height: 0.8mm;
            background:
                linear-gradient(90deg,
                    transparent 0%,
                    var(--school-gold) 13%,
                    var(--school-gold) 87%,
                    transparent 100%);
        }

        .recipient-name-long {
            font-size: 24pt;
        }

        .recipient-name-extra-long {
            font-size: 20pt;
        }

        .recognition-statement {
            max-width: 224mm;
            margin: 3mm 0 0;
            color: #34473d;
            font-size: 11.7pt;
            line-height: 1.48;
        }

        .recognition-statement strong {
            color: var(--school-green);
            font-weight: 800;
        }

        .sports-result {
            display: flex;
            align-items: stretch;
            justify-content: center;
            max-width: 220mm;
            margin-top: 2.5mm;
            border: 0.4mm solid var(--school-gold);
            background: rgba(255, 248, 223, 0.8);
        }

        .sports-result-item {
            min-width: 72mm;
            max-width: 108mm;
            padding: 1.5mm 6mm;
        }

        .sports-result-item+.sports-result-item {
            border-left: 0.35mm solid var(--school-gold);
        }

        .sports-result-label {
            display: block;
            margin-bottom: 0.6mm;
            color: var(--school-muted);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .sports-result-value {
            display: block;
            color: var(--school-gold-dark);
            font-size: 12pt;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .achievement-long,
        .sport-name-long {
            font-size: 9.5pt;
        }

        /* ================================================================
           Signatures and seal
           ================================================================ */

        .certificate-footer {
            display: grid;
            grid-template-columns: 1fr 46mm 1fr;
            align-items: end;
            gap: 11mm;
            padding: 2mm 14mm 0;
        }

        .signature-section {
            text-align: center;
        }

        .signature-space {
            height: 11mm;
        }

        .signature-line {
            border-top: 0.45mm solid var(--school-green-dark);
            padding-top: 1.7mm;
        }

        .signature-name {
            color: var(--school-green-dark);
            font-size: 10pt;
            font-weight: 800;
        }

        .signature-title {
            margin-top: 0.7mm;
            color: var(--school-muted);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7.5pt;
            font-style: italic;
        }

        .official-seal {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38mm;
            height: 38mm;
            justify-self: center;
            border: 1.2mm solid var(--school-gold);
            border-radius: 50%;
            background:
                radial-gradient(circle,
                    var(--school-white) 0%,
                    var(--school-cream) 72%,
                    var(--school-gold-light) 100%);
            box-shadow:
                inset 0 0 0 1mm var(--school-green),
                0 1.5mm 4mm rgba(0, 0, 0, 0.12);
        }

        .official-seal::before {
            content: "";
            position: absolute;
            inset: 3.2mm;
            border: 0.45mm dashed var(--school-gold-dark);
            border-radius: 50%;
        }

        .official-seal-content {
            position: relative;
            z-index: 2;
            color: var(--school-green-dark);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.8pt;
            font-weight: 900;
            letter-spacing: 0.55px;
            line-height: 1.32;
            text-align: center;
            text-transform: uppercase;
        }

        .official-seal-star {
            display: block;
            margin: 0.7mm 0;
            color: var(--school-gold-dark);
            font-size: 13pt;
            line-height: 1;
        }

        /* ================================================================
           Certificate details
           ================================================================ */

        .certificate-details {
            position: absolute;
            right: 18mm;
            bottom: 13.5mm;
            left: 18mm;
            z-index: 15;
            display: flex;
            justify-content: space-between;
            gap: 5mm;
            color: var(--school-muted);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            line-height: 1.2;
        }

        .certificate-detail strong {
            color: var(--school-green-dark);
        }

        /* ================================================================
           Screen preview
           ================================================================ */

        @media screen {
            body {
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 12mm;
            }

            .certificate {
                box-shadow: 0 5mm 18mm rgba(0, 0, 0, 0.2);
            }
        }

        /* ================================================================
           Print
           ================================================================ */

        @media print {

            html,
            body {
                width: 297mm;
                height: 210mm;
                overflow: hidden;
                background: #ffffff !important;
            }

            body {
                display: block;
                padding: 0;
            }

            .certificate {
                width: 297mm;
                height: 210mm;
                margin: 0;
                box-shadow: none;
                break-after: avoid;
                page-break-after: avoid;
            }
        }
    </style>
</head>

<body>
    <article class="certificate">
        <div class="certificate-border-outer"></div>
        <div class="certificate-border-gold"></div>
        <div class="certificate-border-inner"></div>

        <div class="certificate-corner certificate-corner-top-left"></div>
        <div class="certificate-corner certificate-corner-top-right"></div>
        <div class="certificate-corner certificate-corner-bottom-left"></div>
        <div class="certificate-corner certificate-corner-bottom-right"></div>

        <div class="background-pattern background-pattern-left"></div>
        <div class="background-pattern background-pattern-right"></div>

        <div class="certificate-watermark">
            CHAMPION
        </div>

        <div class="certificate-content">
            <header class="certificate-header">
                <div class="school-logo-wrapper">
                    <img src="<?= sportsCertificateEscape($schoolLogo) ?>"
                        alt="<?= sportsCertificateEscape($schoolName) ?> Logo" class="school-logo">
                </div>

                <div class="school-identity">
                    <h1 class="school-name">
                        <?= sportsCertificateEscape($schoolName) ?>
                    </h1>

                    <p class="school-motto">
                        “<?= sportsCertificateEscape($schoolMotto) ?>”
                    </p>

                    <div class="school-contact">
                        <span>
                            <?= sportsCertificateEscape($schoolAddress) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            Tel: <?= sportsCertificateEscape($schoolPhone) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            <?= sportsCertificateEscape($schoolEmail) ?>
                        </span>

                        <?php if ($schoolWebsite !== ''): ?>
                            <span class="school-contact-separator">•</span>

                            <span>
                                <?= sportsCertificateEscape($schoolWebsite) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="sports-emblem" aria-label="Sports achievement trophy">
                    <div class="trophy">
                        <div class="trophy-handle trophy-handle-left"></div>
                        <div class="trophy-handle trophy-handle-right"></div>
                        <div class="trophy-cup"></div>
                        <div class="trophy-star">★</div>
                        <div class="trophy-stem"></div>
                        <div class="trophy-base"></div>
                    </div>
                </div>
            </header>

            <section class="certificate-title-section">
                <h2 class="certificate-title">
                    Certificate of
                    <span class="certificate-title-highlight">
                        Sports Achievement
                    </span>
                </h2>

                <div class="certificate-title-divider">
                    <span class="certificate-title-divider-line"></span>
                    <span class="certificate-title-divider-diamond"></span>
                    <span class="certificate-title-divider-line"></span>
                </div>
            </section>

            <main class="certificate-body">
                <p class="presented-to">
                    This certificate is proudly presented to
                </p>

                <h3 class="recipient-name <?= sportsCertificateEscape($recipientClass) ?>">
                    <?= sportsCertificateEscape($recipientName) ?>
                </h3>

                <p class="recognition-statement">
                    In recognition of
                    <strong>outstanding athletic performance</strong>,
                    commitment, discipline and exemplary sportsmanship
                    during the
                    <strong>
                        <?= sportsCertificateEscape($academicYear) ?>
                        Academic Year
                    </strong>.
                </p>

                <div class="sports-result">
                    <div class="sports-result-item">
                        <span class="sports-result-label">
                            Achievement
                        </span>

                        <span class="sports-result-value <?= sportsCertificateEscape($achievementClass) ?>">
                            <?= sportsCertificateEscape($achievement) ?>
                        </span>
                    </div>

                    <div class="sports-result-item">
                        <span class="sports-result-label">
                            Sport
                        </span>

                        <span class="sports-result-value <?= sportsCertificateEscape($sportClass) ?>">
                            <?= sportsCertificateEscape($sport) ?>
                        </span>
                    </div>
                </div>

                <p class="recognition-statement">
                    This award celebrates the learner's contribution to
                    the sporting excellence and proud tradition of
                    <strong>
                        <?= sportsCertificateEscape($schoolName) ?>
                    </strong>.
                </p>
            </main>

            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= sportsCertificateEscape($principalName) ?>
                        </div>

                        <div class="signature-title">
                            <?= sportsCertificateEscape($principalTitle) ?>
                        </div>
                    </div>
                </div>

                <div class="official-seal">
                    <div class="official-seal-content">
                        Kingsway
                        <span class="official-seal-star">★</span>
                        Official<br>
                        Sports Seal
                    </div>
                </div>

                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= sportsCertificateEscape(
                                $sportsCoordinatorName
                            ) ?>
                        </div>

                        <div class="signature-title">
                            Sports Coordinator
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <div class="certificate-details">
            <div class="certificate-detail">
                Certificate No:
                <strong>
                    <?= sportsCertificateEscape($certificateNumber) ?>
                </strong>
            </div>

            <div class="certificate-detail">
                Date Awarded:
                <strong>
                    <?= sportsCertificateEscape($dateAwarded) ?>
                </strong>
            </div>
        </div>
    </article>

    <script>
        (function () {
            "use strict";

            async function waitForCertificateAssets() {
                if (document.fonts && document.fonts.ready) {
                    try {
                        await document.fonts.ready;
                    } catch (error) {
                        console.warn(
                            "Certificate fonts did not finish loading.",
                            error
                        );
                    }
                }

                const images = Array.from(document.images);

                await Promise.all(
                    images.map(function (image) {
                        if (
                            image.complete &&
                            image.naturalWidth > 0
                        ) {
                            return Promise.resolve();
                        }

                        return new Promise(function (resolve) {
                            const timeout = window.setTimeout(
                                resolve,
                                10000
                            );

                            image.addEventListener(
                                "load",
                                function () {
                                    window.clearTimeout(timeout);
                                    resolve();
                                },
                                { once: true }
                            );

                            image.addEventListener(
                                "error",
                                function () {
                                    window.clearTimeout(timeout);
                                    image.style.visibility = "hidden";
                                    resolve();
                                },
                                { once: true }
                            );
                        });
                    })
                );
            }

            window.addEventListener(
                "load",
                async function () {
                    await waitForCertificateAssets();

                    window.setTimeout(function () {
                        window.focus();
                        window.print();
                    }, 350);
                },
                { once: true }
            );
        })();
    </script>
</body>

</html>