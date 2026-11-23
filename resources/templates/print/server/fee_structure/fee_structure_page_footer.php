<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$pageFooterMessage = 'Thank you for partnering with us in nurturing excellence.';

/**
 * Rounded Corner Bucket Geometry:
 * 1. (0,0) -> Drops vertically/curving down fast at the left edge to Y=70 via (0,45 25,70)
 * 2. Flat horizontal line across the entire middle: (45,70) -> L (955,70)
 * 3. Curves back up steeply at the right edge: (975,70 1000,45) -> (1000,0)
 */
$footerSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 140" preserveAspectRatio="none">
    <path d="M 0,140 L 0,0 C 0,45 25,70 55,70 L 945,70 C 975,70 1000,45 1000,0 L 1000,140 Z" fill="#07552f" />
    <path d="M 0,0 C 0,45 25,70 55,70 L 945,70 C 975,70 1000,45 1000,0" fill="none" stroke="#c9941d" stroke-width="3" />
</svg>';
?>
<div class="fs-footer-dome-fixed">
    <img class="fs-footer-dome-svg" src="data:image/svg+xml;base64,<?= base64_encode($footerSvg) ?>" alt="">
    <div class="fs-footer-dome-text"><?= htmlspecialchars($pageFooterMessage) ?></div>
</div>