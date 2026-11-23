<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$tplDir = __DIR__;
$allGrades = is_array($grades ?? null) ? $grades : [];
$targetId = (int) ($gradeId ?? 0);
$selected = [];
foreach ($allGrades as $grade) if ((int) ($grade['id'] ?? 0) === $targetId) { $selected[] = $grade; break; }
if (!$selected && $allGrades) $selected[] = $allGrades[0];
$type = strtolower((string) ($studentType ?? 'both'));
require_once $tplDir . '/fee_structure_variant_helpers.php';
$sections = fsCanonicalSections($selected, $type === 'both' || $type === 'day', $type === 'both' || $type === 'boarder');
$name = $selected[0]['name'] ?? 'CLASS FEE STRUCTURE';
$documentTitle = ($yearLabel ?? date('Y')) . ' ' . strtoupper($name) . ' FEE STRUCTURE';
$documentSubtitle = $type === 'day' ? 'DAY SCHOLAR' : ($type === 'boarder' ? 'BOARDER' : 'DAY SCHOLAR & BOARDER');
include $tplDir . '/fee_structure_simple.php';
