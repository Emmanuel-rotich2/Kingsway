<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string) $_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(404); exit; } // template only: never a direct request
/** Build the canonical fee-structure sections from any fee-structure scope. */
if (!function_exists('fsCanonicalSections')) {
    function fsCanonicalSections(array $grades, bool $showDay, bool $showBoarder): array
    {
        $primaryDay = $primaryBoarder = $juniorDay = $juniorBoarder = [];
        foreach ($grades as $grade) {
            $isJunior = (int) ($grade['section'] ?? 0) === 4;
            $name = (string) ($grade['name'] ?? '');
            if ($showDay && !empty($grade['day'])) {
                $row = ['label' => $name] + $grade['day'];
                if ($isJunior) $juniorDay[] = $row; else $primaryDay[] = $row;
            }
            if ($showBoarder && !empty($grade['boarder'])) {
                $row = ['label' => $name] + $grade['boarder'];
                if ($isJunior) $juniorBoarder[] = $row; else $primaryBoarder[] = $row;
            }
        }
        $sections = [];
        if ($primaryDay || $primaryBoarder) {
            $sections[] = ['title' => 'PRIMARY SCHOOL', 'variant' => 'primary', 'dayRows' => $primaryDay, 'boarderRows' => $primaryBoarder];
        }
        $collapse = static function (array $rows, string $type): array {
            if (!$rows) return [];
            $first = $rows[0];
            $same = count(array_filter($rows, static function ($row) use ($first): bool {
                return (float)($row['term1'] ?? 0) === (float)($first['term1'] ?? 0)
                    && (float)($row['term2'] ?? 0) === (float)($first['term2'] ?? 0)
                    && (float)($row['term3'] ?? 0) === (float)($first['term3'] ?? 0);
            })) === count($rows);
            if ($same && count($rows) > 1) {
                return [['category' => 'GRADE 7, 8 AND 9 – ' . strtoupper($type), 'term1' => $first['term1'] ?? 0, 'term2' => $first['term2'] ?? 0, 'term3' => $first['term3'] ?? 0, 'total' => $first['total'] ?? 0]];
            }
            return array_map(static function ($row) use ($type): array {
                return ['category' => ($row['label'] ?? '') . ' – ' . strtoupper($type), 'term1' => $row['term1'] ?? 0, 'term2' => $row['term2'] ?? 0, 'term3' => $row['term3'] ?? 0, 'total' => $row['total'] ?? 0];
            }, $rows);
        };
        $juniorRows = array_merge($collapse($juniorDay, 'DAY SCHOLARS'), $collapse($juniorBoarder, 'BOARDERS'));
        if ($juniorRows) $sections[] = ['title' => 'JUNIOR SCHOOL', 'variant' => 'junior', 'rows' => $juniorRows, 'firstCol' => 'CATEGORY'];
        return $sections;
    }
}
