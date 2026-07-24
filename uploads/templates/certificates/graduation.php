<?php
/**
 * Graduation Certificate Template
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
 * - $course
 * - $academicYear
 * - $certificateNumber
 * - $dateAwarded
 * - $examOfficerName
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Accept values passed through PrintManager
|--------------------------------------------------------------------------
|
| A database-driven certificate endpoint is preferable in production.
| These query parameters maintain compatibility with the current
| PrintManager implementation.
|
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
$examOfficerName = $_GET['examOfficerName'] ?? $examOfficerName ?? null;

$recipientName = $_GET['recipientName'] ?? $recipientName ?? null;
$course = $_GET['course'] ?? $course ?? null;
$academicYear = $_GET['academicYear'] ?? $academicYear ?? null;
$certificateNumber = $_GET['certificateNumber'] ?? $certificateNumber ?? null;
$dateAwarded = $_GET['dateAwarded'] ?? $dateAwarded ?? null;

/**
 * Escape values before rendering them into HTML.
 */
function graduationEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Return a fallback when the supplied value is empty.
 */
function graduationValue(mixed $value, string $fallback = ''): string
{
    $value = trim((string) ($value ?? ''));

    return $value !== '' ? $value : $fallback;
}

$schoolName = graduationValue(
    $schoolName,
    'KINGSWAY PREPARATORY SCHOOL'
);

$schoolMotto = graduationValue(
    $schoolMotto,
    'In God We Soar'
);

$schoolLogo = graduationValue(
    $schoolLogo,
    '/uploads/school_assets/official_school_logo.png'
);

$schoolAddress = graduationValue(
    $schoolAddress,
    'P.O. Box 203-20203, Londiani, Kericho County, Kenya'
);

$schoolPhone = graduationValue(
    $schoolPhone,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = graduationValue(
    $schoolEmail,
    'info@kingswaypreparatoryschool.sc.ke'
);

$schoolWebsite = graduationValue(
    $schoolWebsite,
    'www.kingswaypreparatoryschool.sc.ke'
);

$principalName = graduationValue(
    $principalName,
    'Headteacher'
);

$principalTitle = graduationValue(
    $principalTitle,
    'Headteacher'
);

$examOfficerName = graduationValue(
    $examOfficerName,
    'Examinations Officer'
);

$recipientName = graduationValue(
    $recipientName,
    'Student Name'
);

$course = graduationValue(
    $course,
    'Primary School Education Programme'
);

$academicYear = graduationValue(
    $academicYear,
    date('Y')
);

$certificateNumber = graduationValue(
    $certificateNumber,
    'KPS-GRAD-' . date('YmdHis')
);

$dateAwarded = graduationValue(
    $dateAwarded,
    date('d F Y')
);

$recipientLength = function_exists('mb_strlen')
    ? mb_strlen($recipientName)
    : strlen($recipientName);

$courseLength = function_exists('mb_strlen')
    ? mb_strlen($course)
    : strlen($course);

$recipientClass = '';

if ($recipientLength > 42) {
    $recipientClass = 'recipient-name-extra-long';
} elseif ($recipientLength > 28) {
    $recipientClass = 'recipient-name-long';
}

$courseClass = $courseLength > 60
    ? 'course-name-long'
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= graduationEscape($recipientName) ?>
        - Graduation Certificate
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
                radial-gradient(
                    circle at center,
                    rgba(255, 255, 255, 0.98) 0%,
                    rgba(255, 253, 244, 0.98) 64%,
                    var(--school-cream) 100%
                );
        }

        /* ================================================================
           Certificate frame
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
           Main content grid
           ================================================================ */

        .certificate-content {
            position: relative;
            z-index: 10;
            display: grid;
            grid-template-rows:
                42mm
                23mm
                minmax(0, 1fr)
                36mm;
            width: 100%;
            height: 100%;
            padding: 15mm 22mm 13mm;
        }

        /* ================================================================
           Header
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
           Graduation emblem
           ================================================================ */

        .graduation-emblem {
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
                radial-gradient(
                    circle,
                    var(--school-white) 0%,
                    var(--school-cream) 100%
                );
            color: var(--school-green-dark);
            text-align: center;
        }

        .graduation-cap {
            position: relative;
            width: 19mm;
            height: 12mm;
        }

        .graduation-cap-top {
            position: absolute;
            top: 0;
            left: 50%;
            width: 17mm;
            height: 17mm;
            transform: translateX(-50%) rotate(45deg);
            background: var(--school-green-dark);
            border: 0.6mm solid var(--school-gold);
        }

        .graduation-cap-bottom {
            position: absolute;
            right: 3.5mm;
            bottom: 0;
            left: 3.5mm;
            height: 5mm;
            border-radius: 0 0 8mm 8mm;
            background: var(--school-green);
        }

        .graduation-cap-tassel {
            position: absolute;
            top: 5mm;
            right: -0.5mm;
            width: 0.6mm;
            height: 9mm;
            background: var(--school-gold-dark);
        }

        .graduation-cap-tassel::after {
            content: "";
            position: absolute;
            right: -1mm;
            bottom: -2mm;
            width: 2.5mm;
            height: 3.5mm;
            border-radius: 0 0 50% 50%;
            background: var(--school-gold);
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
            font-size: 29pt;
            font-weight: 800;
            letter-spacing: 2.4px;
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
                linear-gradient(
                    90deg,
                    transparent 0%,
                    var(--school-gold) 25%,
                    var(--school-gold) 100%
                );
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
           Certificate statement
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
                linear-gradient(
                    90deg,
                    transparent 0%,
                    var(--school-gold) 13%,
                    var(--school-gold) 87%,
                    transparent 100%
                );
        }

        .recipient-name-long {
            font-size: 24pt;
        }

        .recipient-name-extra-long {
            font-size: 20pt;
        }

        .graduation-statement {
            max-width: 224mm;
            margin: 3mm 0 0;
            color: #34473d;
            font-size: 11.7pt;
            line-height: 1.48;
        }

        .graduation-statement strong {
            color: var(--school-green);
            font-weight: 800;
        }

        .course-highlight {
            display: inline-block;
            max-width: 205mm;
            margin-top: 2.4mm;
            padding: 1.5mm 7mm;
            border-right: 0.8mm solid var(--school-gold);
            border-left: 0.8mm solid var(--school-gold);
            background: rgba(255, 248, 223, 0.75);
            color: var(--school-gold-dark);
            font-size: 13pt;
            font-weight: 800;
            line-height: 1.25;
            overflow-wrap: anywhere;
        }

        .course-name-long {
            font-size: 10.5pt;
        }

        /* ================================================================
           Footer and signatures
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

        /* ================================================================
           Official seal
           ================================================================ */

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
                radial-gradient(
                    circle,
                    var(--school-white) 0%,
                    var(--school-cream) 72%,
                    var(--school-gold-light) 100%
                );
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
           Printed output
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
            GRADUATION
        </div>

        <div class="certificate-content">
            <header class="certificate-header">
                <div class="school-logo-wrapper">
                    <img
                        src="<?= graduationEscape($schoolLogo) ?>"
                        alt="<?= graduationEscape($schoolName) ?> Logo"
                        class="school-logo"
                    >
                </div>

                <div class="school-identity">
                    <h1 class="school-name">
                        <?= graduationEscape($schoolName) ?>
                    </h1>

                    <p class="school-motto">
                        “<?= graduationEscape($schoolMotto) ?>”
                    </p>

                    <div class="school-contact">
                        <span>
                            <?= graduationEscape($schoolAddress) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            Tel: <?= graduationEscape($schoolPhone) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            <?= graduationEscape($schoolEmail) ?>
                        </span>

                        <?php if ($schoolWebsite !== ''): ?>
                                <span class="school-contact-separator">•</span>

                                <span>
                                    <?= graduationEscape($schoolWebsite) ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div
                    class="graduation-emblem"
                    aria-label="Graduation emblem"
                >
                    <div class="graduation-cap">
                        <div class="graduation-cap-top"></div>
                        <div class="graduation-cap-bottom"></div>
                        <div class="graduation-cap-tassel"></div>
                    </div>
                </div>
            </header>

            <section class="certificate-title-section">
                <h2 class="certificate-title">
                    Certificate of
                    <span class="certificate-title-highlight">
                        Graduation
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

                <h3
                    class="recipient-name <?= graduationEscape($recipientClass) ?>"
                >
                    <?= graduationEscape($recipientName) ?>
                </h3>

                <p class="graduation-statement">
                    Having successfully completed all prescribed academic
                    requirements and demonstrated commitment, discipline,
                    academic growth and exemplary conduct at
                    <strong>
                        <?= graduationEscape($schoolName) ?>
                    </strong>.
                </p>

                <div
                    class="course-highlight <?= graduationEscape($courseClass) ?>"
                >
                    <?= graduationEscape($course) ?>
                </div>

                <p class="graduation-statement">
                    This graduation is awarded for the
                    <strong>
                        <?= graduationEscape($academicYear) ?>
                        Academic Year
                    </strong>
                    and confirms the learner's successful completion of
                    the programme.
                </p>
            </main>

            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= graduationEscape($principalName) ?>
                        </div>

                        <div class="signature-title">
                            <?= graduationEscape($principalTitle) ?>
                        </div>
                    </div>
                </div>

                <div class="official-seal">
                    <div class="official-seal-content">
                        Kingsway
                        <span class="official-seal-star">★</span>
                        Official<br>
                        Graduation Seal
                    </div>
                </div>

                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= graduationEscape($examOfficerName) ?>
                        </div>

                        <div class="signature-title">
                            Examinations Officer
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <div class="certificate-details">
            <div class="certificate-detail">
                Certificate No:
                <strong>
                    <?= graduationEscape($certificateNumber) ?>
                </strong>
            </div>

            <div class="certificate-detail">
                Date Awarded:
                <strong>
                    <?= graduationEscape($dateAwarded) ?>
                </strong>
            </div>
        </div>
    </article>

    <script>
        (function () {
            "use strict";

            async function waitForGraduationCertificateAssets() {
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
                    await waitForGraduationCertificateAssets();

                    window.setTimeout(function () {
                        window.focus();
                        window.opener?.postMessage({ type: "kingsway-print-ready" }, window.location.origin);
                    }, 350);
                },
                { once: true }
            );
        })();
    </script>
</body>
</html>