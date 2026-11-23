<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$sName = htmlspecialchars(strtoupper((string) ($schoolName ?? 'KINGSWAY PREPARATORY SCHOOL')));
$sAddress = htmlspecialchars($schoolAddress ?? 'P.O BOX 203 – 20203, LONDIANI');
$sPhone = htmlspecialchars($schoolPhone ?? '0720113030 | 0720113031');
$sMotto = htmlspecialchars($schoolMotto ?? 'In God we soar');
$sWebsite = htmlspecialchars($schoolWebsite ?? 'www.kingswaypreparatoryschool.sc.ke');
$sEmail = htmlspecialchars($schoolEmail ?? 'info@kingswaypreparatoryschool.sc.ke');
$sLogo = $schoolLogo ?? '';
$period = $period ?? date('F Y');

$candidates = [];
if ($sLogo && !preg_match('/^(data:|https?:\/\/)/i', $sLogo)) { 
    if (is_file((string) $sLogo)) $candidates[] = (string) $sLogo;
    if (defined('UPLOAD_PATH')) $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/' . preg_replace('#^uploads/#', '', ltrim((string) $sLogo, '/\\')); 
    $sLogo = ''; 
}
if (!$sLogo) {
    if (defined('UPLOAD_PATH')) $candidates[] = rtrim(UPLOAD_PATH, '/\\') . '/school_assets/official_school_logo.png';
    foreach ($candidates as $path) {
        if (is_file($path) && is_readable($path)) {
            $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/png') : 'image/png'; 
            $bytes = file_get_contents($path);
            if ($bytes !== false) { 
                $sLogo = 'data:' . $mime . ';base64,' . base64_encode($bytes); 
                break; 
            }
        }
    }
}

// Corner Accent SVG: Gold outer arc (#c9941d), Navy inner fill (#062653)
$cornerSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="none"><path fill="#c9941d" d="M0,0 L100,0 L100,100 C100,45 55,0 0,0 Z"/><path fill="#07552f" d="M25,0 L100,0 L100,75 C100,35 65,0 25,0 Z"/></svg>';
$cornerSrc = 'data:image/svg+xml;base64,' . base64_encode($cornerSvg);
?>

<!-- Absolute Top-Right Corner Accent (Pinned to page shell edge) -->
<div class="fs-corner-accent-overlay">
    <img src="<?= $cornerSrc ?>" alt="">
</div>

<header class="fs-header">
    <table class="fs-header-table">
        <tr>
            <!-- Left Logo Column -->
            <td class="fs-header-logo-cell">
                <?php if ($sLogo): ?>
                    <img src="<?= htmlspecialchars($sLogo) ?>" alt="School Logo">
                <?php endif; ?>
            </td>

            <!-- Centered Details Column -->
            <td class="fs-header-details-cell">
                <div class="fs-school-name"><?= $sName ?></div>
                <div class="fs-school-meta"> <?= $sAddress ?></div>
                <div class="fs-school-meta">TEL: <?= $sPhone ?></div>
                <div class="fs-school-meta">EMAIL: <?= $sEmail ?> </div>
                <div class="fs-school-meta">WEBSITE: <?= $sWebsite ?> </div>
                <div class="fs-motto"><?= $sMotto ?></div>

                <!-- Gold Divider Line with Diamond Center -->
                <table role="presentation" style="width:100%;height:8px;border-collapse:collapse;margin:1.2mm 0 0">
                    <tr style="height:8px">
                        <td style="width:46%;height:8px;vertical-align:middle"><div style="height:0;border-top:0.55mm solid #c9941d;line-height:0"></div></td>
                        <td style="width:8%;height:8px;text-align:center;vertical-align:middle">
                            <span style="display:inline-block;width:2.2mm;height:2.2mm;background:#c9941d;transform:rotate(45deg)"></span>
                        </td>
                        <td style="width:46%;height:8px;vertical-align:middle"><div style="height:0;border-top:0.55mm solid #c9941d;line-height:0"></div></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</header>

<section class="fs-title-block">
    <h1><?= htmlspecialchars($documentTitle ?? 'EMPLOYEE PAYSLIP') ?></h1>
    
    <!-- Unified SVG Subtitle Ribbon: Zero-gap Gold Lines attached directly to Green Notched Badge -->
    <div class="fs-ribbon-wrapper">
        <?php
        $subText = htmlspecialchars($period ?? '2026');
        
        // Single SVG drawing:
        // 1. Left Gold Line (x1=0 to x2=165)
        // 2. Center Green Notched Ribbon (pointed left & right ends)
        // 3. Right Gold Line (x1=435 to x2=600)
        $ribbonSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 32" preserveAspectRatio="none" style="width:100%; height:6.8mm; display:block;">
            <!-- Left Gold Line flush to left notch -->
            <line x1="0" y1="16" x2="175" y2="16" stroke="#c9941d" stroke-width="2.5" />
            
            <!-- Center Green Notched Badge -->
            <polygon points="185,0 415,0 425,16 415,32 185,32 175,16" fill="#07552f" />
            
            <!-- Subtitle Text -->
            <text x="300" y="21" font-family="Arial, Helvetica, sans-serif" font-size="12" font-weight="900" fill="#ffffff" text-transform="uppercase" text-anchor="middle" letter-spacing="0.8">' . $subText . '</text>
            
            <!-- Right Gold Line flush to right notch -->
            <line x1="425" y1="16" x2="600" y2="16" stroke="#c9941d" stroke-width="2.5" />
        </svg>';
        ?>
        <img src="data:image/svg+xml;base64,<?= base64_encode($ribbonSvg) ?>" class="fs-ribbon-img" alt="">
    </div>
</section>
