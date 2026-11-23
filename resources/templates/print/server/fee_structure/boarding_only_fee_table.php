<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$tplDir = __DIR__;
$grades = is_array($grades ?? null) ? $grades : [];
require_once $tplDir . '/fee_structure_variant_helpers.php';
$sections = fsCanonicalSections($grades, false, true);
$documentTitle = ($yearLabel ?? date('Y')) . ' BOARDER FEE STRUCTURE';
$documentSubtitle = 'PRIMARY & JUNIOR SCHOOL';
include $tplDir . '/fee_structure_simple.php';
