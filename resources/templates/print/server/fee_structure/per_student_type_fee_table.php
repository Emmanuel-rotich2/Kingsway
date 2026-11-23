<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
$tplDir = __DIR__;
$allGrades = is_array($grades ?? null) ? $grades : [];
$filter = strtolower((string) ($gradeFilter ?? 'all'));
$type = strtolower((string) ($studentType ?? 'both'));
$matches = static function (array $grade) use ($filter): bool {
    if ($filter === '' || $filter === 'all') return true;
    $name = strtolower((string) ($grade['name'] ?? ''));
    $section = (int) ($grade['section'] ?? 0);
    if ($filter === 'primary') return in_array($section, [2, 3, 5], true);
    if ($filter === 'jss') return $section === 4;
    if ($filter === 'ecd') return $section === 5 || (bool) preg_match('/playgroup|pg|pp1|pp2/', $name);
    if ($filter === 'lower_primary') return (bool) preg_match('/grade\s*[1-3]/', $name);
    if ($filter === 'upper_primary') return (bool) preg_match('/grade\s*[4-6]/', $name);
    return true;
};
$filtered = array_values(array_filter($allGrades, $matches));
require_once $tplDir . '/fee_structure_variant_helpers.php';
$sections = fsCanonicalSections($filtered, $type === 'both' || $type === 'day', $type === 'both' || $type === 'boarder');
$scopeLabel = $filter === 'all' ? 'PRIMARY & JUNIOR SCHOOL' : strtoupper($filter);
$documentTitle = ($yearLabel ?? date('Y')) . ' ' . $scopeLabel . ' FEE STRUCTURE';
$documentSubtitle = $type === 'day' ? 'DAY SCHOLARS' : ($type === 'boarder' ? 'BOARDERS' : 'PRIMARY & JUNIOR SCHOOL');
include $tplDir . '/fee_structure_simple.php';
