<?php
/**
 * Academic Excellence Certificate Template
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
 * - $certificateNumber
 * - $dateAwarded
 * - $teacherName
 */

declare(strict_types=1);

/**
 * Safely escape output.
 */
function certificateEscape(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Provide a fallback when a value is missing.
 */
function certificateValue(mixed $value, string $fallback = ''): string
{
    $value = trim((string) ($value ?? ''));

    return $value !== '' ? $value : $fallback;
}

$schoolName = certificateValue(
    $schoolName ?? null,
    'KINGSWAY PREPARATORY SCHOOL'
);

$schoolMotto = certificateValue(
    $schoolMotto ?? null,
    'In God We Soar'
);

$schoolLogo = certificateValue(
    $schoolLogo ?? null,
    '/uploads/school_assets/official_school_logo.png'
);

$schoolAddress = certificateValue(
    $schoolAddress ?? null,
    'P.O. Box 203-20203, Londiani, Kericho County, Kenya'
);

$schoolPhone = certificateValue(
    $schoolPhone ?? null,
    '0720 113 030 / 0720 113 031'
);

$schoolEmail = certificateValue(
    $schoolEmail ?? null,
    'info@kingswaypreparatoryschool.sc.ke'
);

$schoolWebsite = certificateValue(
    $schoolWebsite ?? null,
    'www.kingswaypreparatoryschool.sc.ke'
);

$principalName = certificateValue(
    $principalName ?? null,
    'Headteacher'
);

$principalTitle = certificateValue(
    $principalTitle ?? null,
    'Headteacher'
);

$teacherName = certificateValue(
    $teacherName ?? null,
    'Class Teacher'
);

$recipientName = certificateValue(
    $recipientName ?? null,
    'Student Name'
);

$achievement = certificateValue(
    $achievement ?? null,
    'Outstanding Academic Performance'
);

$academicYear = certificateValue(
    $academicYear ?? null,
    date('Y')
);

$certificateNumber = certificateValue(
    $certificateNumber ?? null,
    'KPS-' . date('YmdHis')
);

$dateAwarded = certificateValue(
    $dateAwarded ?? null,
    date('d F Y')
);
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
        <?= certificateEscape($recipientName) ?>
        - Academic Excellence Certificate
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
                    rgba(255, 255, 255, 0.96) 0%,
                    rgba(255, 253, 244, 0.98) 64%,
                    rgba(255, 248, 223, 1) 100%
                );
        }

        /*
         * Outer coloured frame
         */

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

        /*
         * Decorative corner blocks
         */

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

        .certificate-corner-top-left::before,
        .certificate-corner-top-left::after {
            top: 0;
            left: 0;
        }

        .certificate-corner-top-right {
            top: 8mm;
            right: 8mm;
            transform: scaleX(-1);
        }

        .certificate-corner-top-right::before,
        .certificate-corner-top-right::after {
            top: 0;
            left: 0;
        }

        .certificate-corner-bottom-left {
            bottom: 8mm;
            left: 8mm;
            transform: scaleY(-1);
        }

        .certificate-corner-bottom-left::before,
        .certificate-corner-bottom-left::after {
            top: 0;
            left: 0;
        }

        .certificate-corner-bottom-right {
            right: 8mm;
            bottom: 8mm;
            transform: scale(-1);
        }

        .certificate-corner-bottom-right::before,
        .certificate-corner-bottom-right::after {
            top: 0;
            left: 0;
        }

        /*
         * Main content
         */

        .certificate-content {
            position: relative;
            z-index: 10;
            display: grid;
            grid-template-rows:
                42mm
                24mm
                minmax(0, 1fr)
                35mm;
            width: 100%;
            height: 100%;
            padding: 15mm 22mm 13mm;
        }

        /*
         * Header
         */

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
            box-shadow:
                0 1.5mm 4mm rgba(8, 63, 43, 0.16);
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
            font-size: 7.5pt;
            line-height: 1.3;
        }

        .school-contact-separator {
            color: var(--school-gold-dark);
            font-weight: 900;
        }

        .certificate-emblem {
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
                    var(--school-cream-soft) 0%,
                    var(--school-cream) 100%
                );
            color: var(--school-green-dark);
            text-align: center;
        }

        .certificate-emblem-content {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7pt;
            font-weight: 800;
            letter-spacing: 0.4px;
            line-height: 1.35;
            text-transform: uppercase;
        }

        .certificate-emblem-star {
            display: block;
            margin-bottom: 1mm;
            color: var(--school-gold-dark);
            font-size: 14pt;
            line-height: 1;
        }

        /*
         * Certificate title
         */

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

        .certificate-title-gold {
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

        /*
         * Certificate statement
         */

        .certificate-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 0;
            padding: 1mm 15mm 0;
            text-align: center;
        }

        .presented-to {
            margin: 0 0 1.5mm;
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

        .certificate-recognition {
            max-width: 218mm;
            margin: 3.5mm 0 0;
            color: #34473d;
            font-size: 12.5pt;
            line-height: 1.55;
        }

        .certificate-recognition strong {
            color: var(--school-green);
            font-weight: 800;
        }

        .achievement-highlight {
            display: inline-block;
            margin-top: 2.5mm;
            padding: 1.5mm 7mm;
            border-right: 0.8mm solid var(--school-gold);
            border-left: 0.8mm solid var(--school-gold);
            background: rgba(255, 248, 223, 0.7);
            color: var(--school-gold-dark);
            font-size: 13.5pt;
            font-weight: 800;
        }

        /*
         * Footer and signatures
         */

        .certificate-footer {
            position: relative;
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

        /*
         * Official seal
         */

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
                    #ffffff 0%,
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
            font-size: 7pt;
            font-weight: 900;
            letter-spacing: 0.6px;
            line-height: 1.35;
            text-align: center;
            text-transform: uppercase;
        }

        .official-seal-star {
            display: block;
            margin: 0.8mm 0;
            color: var(--school-gold-dark);
            font-size: 13pt;
            line-height: 1;
        }

        /*
         * Certificate details
         */

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

        /*
         * Watermark
         */

        .certificate-watermark {
            position: absolute;
            top: 53%;
            left: 50%;
            z-index: 1;
            transform: translate(-50%, -50%) rotate(-24deg);
            color: rgba(15, 91, 59, 0.035);
            font-size: 61pt;
            font-weight: 900;
            letter-spacing: 5px;
            line-height: 1;
            white-space: nowrap;
            pointer-events: none;
        }

        /*
         * Decorative background patterns
         */

        .background-pattern {
            position: absolute;
            z-index: 2;
            width: 72mm;
            height: 72mm;
            border: 0.5mm solid rgba(211, 173, 36, 0.13);
            border-radius: 50%;
            pointer-events: none;
        }

        .background-pattern::before,
        .background-pattern::after {
            content: "";
            position: absolute;
            border: 0.4mm solid rgba(15, 91, 59, 0.08);
            border-radius: 50%;
        }

        .background-pattern::before {
            inset: 8mm;
        }

        .background-pattern::after {
            inset: 17mm;
        }

        .background-pattern-left {
            top: -33mm;
            left: -30mm;
        }

        .background-pattern-right {
            right: -30mm;
            bottom: -33mm;
        }

        /*
         * Long student names
         */

        .recipient-name.name-long {
            font-size: 24pt;
        }

        .recipient-name.name-extra-long {
            font-size: 20pt;
        }

        /*
         * Screen preview
         */

        @media screen {
            body {
                display: flex;
                min-height: 100vh;
                align-items: center;
                justify-content: center;
                padding: 12mm;
            }

            .certificate {
                box-shadow:
                    0 5mm 18mm rgba(0, 0, 0, 0.2);
            }
        }

        /*
         * Print output
         */

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
            ACADEMIC EXCELLENCE
        </div>

        <div class="certificate-content">
            <header class="certificate-header">
                <div class="school-logo-wrapper">
                    <img
                        src="<?= certificateEscape($schoolLogo) ?>"
                        alt="<?= certificateEscape($schoolName) ?> Logo"
                        class="school-logo"
                    >
                </div>

                <div class="school-identity">
                    <h1 class="school-name">
                        <?= certificateEscape($schoolName) ?>
                    </h1>

                    <p class="school-motto">
                        “<?= certificateEscape($schoolMotto) ?>”
                    </p>

                    <div class="school-contact">
                        <span>
                            <?= certificateEscape($schoolAddress) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            Tel: <?= certificateEscape($schoolPhone) ?>
                        </span>

                        <span class="school-contact-separator">•</span>

                        <span>
                            <?= certificateEscape($schoolEmail) ?>
                        </span>

                        <?php if ($schoolWebsite !== ''): ?>
                                <span class="school-contact-separator">•</span>

                                <span>
                                    <?= certificateEscape($schoolWebsite) ?>
                                </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="certificate-emblem">
                    <div class="certificate-emblem-content">
                        <span class="certificate-emblem-star">★</span>
                        Academic<br>
                        Achievement
                    </div>
                </div>
            </header>

            <section class="certificate-title-section">
                <h2 class="certificate-title">
                    Certificate of
                    <span class="certificate-title-gold">
                        Academic Excellence
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

                <?php
                $recipientLength = mb_strlen($recipientName);

                $recipientClass = '';

                if ($recipientLength > 42) {
                    $recipientClass = 'name-extra-long';
                } elseif ($recipientLength > 28) {
                    $recipientClass = 'name-long';
                }
                ?>

                <h3 class="recipient-name <?= certificateEscape($recipientClass) ?>">
                    <?= certificateEscape($recipientName) ?>
                </h3>

                <p class="certificate-recognition">
                    In recognition of
                    <strong>outstanding academic achievement</strong>,
                    dedication and exceptional performance during the
                    <strong>
                        <?= certificateEscape($academicYear) ?>
                        Academic Year
                    </strong>.
                </p>

                <div class="achievement-highlight">
                    <?= certificateEscape($achievement) ?>
                </div>
            </main>

            <footer class="certificate-footer">
                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= certificateEscape($principalName) ?>
                        </div>

                        <div class="signature-title">
                            <?= certificateEscape($principalTitle) ?>
                        </div>
                    </div>
                </div>

                <div class="official-seal">
                    <div class="official-seal-content">
                        Kingsway
                        <span class="official-seal-star">★</span>
                        Official Seal
                    </div>
                </div>

                <div class="signature-section">
                    <div class="signature-space"></div>

                    <div class="signature-line">
                        <div class="signature-name">
                            <?= certificateEscape($teacherName) ?>
                        </div>

                        <div class="signature-title">
                            Class Teacher
                        </div>
                    </div>
                </div>
            </footer>
        </div>

        <div class="certificate-details">
            <div class="certificate-detail">
                Certificate No:
                <strong>
                    <?= certificateEscape($certificateNumber) ?>
                </strong>
            </div>

            <div class="certificate-detail">
                Date Awarded:
                <strong>
                    <?= certificateEscape($dateAwarded) ?>
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
                    images.map((image) => {
                        if (
                            image.complete &&
                            image.naturalWidth > 0
                        ) {
                            return Promise.resolve();
                        }

                        return new Promise((resolve) => {
                            image.addEventListener(
                                "load",
                                resolve,
                                { once: true }
                            );

                            image.addEventListener(
                                "error",
                                () => {
                                    image.style.visibility = "hidden";
                                    resolve();
                                },
                                { once: true }
                            );
                        });
                    })
                );
            }

            window.addEventListener("load", async function () {
                await waitForCertificateAssets();

                /*
                 * The PrintManager opens this template in a separate window.
                 * Automatically open the print dialogue once the certificate
                 * and logo have loaded.
                 */
                window.setTimeout(function () {
                    window.focus();
                    window.print();
                }, 350);
            });
        })();
    </script>
</body>
</html>