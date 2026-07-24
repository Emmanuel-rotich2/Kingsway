<?php

declare(strict_types=1);

namespace App\API\Includes;

use App\API\Services\DownloadService;
use App\API\Services\PrintService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;

/**
 * Compatibility facade for legacy callers.
 *
 * Export generation is delegated to PrintService and delivery is delegated to
 * DownloadService. This class contains no response headers, readfile calls or
 * filesystem path construction.
 */
final class ExportHelper
{
    private PrintService $prints;
    private DownloadService $downloads;

    public function __construct()
    {
        $this->prints = new PrintService();
        $this->downloads = new DownloadService();
    }

    public function export(array $rows, string $format, string $filename = 'export'): never
    {
        $format = strtolower($format);
        $path = match ($format) {
            'xlsx', 'excel' => $this->generateSpreadsheet($rows, $filename, 'xlsx'),
            'xls' => $this->generateSpreadsheet($rows, $filename, 'xls'),
            'pdf' => $this->generatePdf($rows, $filename),
            'word', 'docx' => $this->generateWord($rows, $filename),
            default => $this->generateCsv($rows, $filename),
        };

        $this->downloads->streamAbsolutePath(
            $path,
            basename($path),
            null,
            'attachment'
        );
    }

    private function generateCsv(array $rows, string $filename): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create CSV buffer.');
        }

        fputcsv($stream, [$this->schoolName()]);
        fputcsv($stream, [$this->schoolMotto()]);
        fputcsv($stream, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($stream, []);

        if ($rows !== []) {
            fputcsv($stream, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to generate CSV content.');
        }

        return $this->prints->writeGeneratedFile(
            $this->safeFilename($filename, 'csv'),
            $contents
        );
    }

    private function generateSpreadsheet(array $rows, string $filename, string $format): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', $this->schoolName());
        $sheet->setCellValue('A2', $this->schoolMotto());
        $sheet->setCellValue('A3', 'Generated: ' . date('Y-m-d H:i:s'));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);

        if ($rows !== []) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A5');
            $sheet->fromArray($rows, null, 'A6');
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle('A5:' . $lastColumn . '5')->getFont()->setBold(true);
        }

        $path = $this->prints->generatedOutputPath(
            $this->safeFilename($filename, $format)
        );
        $writer = $format === 'xls'
            ? new Xls($spreadsheet)
            : new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    private function generatePdf(array $rows, string $filename): string
    {
        $columns = [];
        if ($rows !== []) {
            foreach (array_keys($rows[0]) as $key) {
                $columns[] = [
                    'key' => $key,
                    'label' => ucfirst(str_replace('_', ' ', (string) $key)),
                ];
            }
        }

        return $this->prints->printTable($rows, [
            'filename' => pathinfo($filename, PATHINFO_FILENAME),
            'columns' => $columns,
            'title' => 'Report',
            'orientation' => 'landscape',
            'paperSize' => 'A4',
        ]);
    }

    private function generateWord(array $rows, string $filename): string
    {
        $document = new PhpWord();
        $section = $document->addSection();
        $section->addText($this->schoolName(), ['bold' => true, 'size' => 16]);
        $section->addText($this->schoolMotto(), ['italic' => true, 'size' => 12]);
        $section->addText('Generated: ' . date('F j, Y g:i A'));
        $section->addTextBreak();

        if ($rows !== []) {
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => '000000',
            ]);
            $table->addRow();
            foreach (array_keys($rows[0]) as $column) {
                $table->addCell(2000)->addText(
                    ucfirst(str_replace('_', ' ', (string) $column)),
                    ['bold' => true]
                );
            }
            foreach ($rows as $row) {
                $table->addRow();
                foreach ($row as $cell) {
                    $table->addCell(2000)->addText((string) $cell);
                }
            }
        }

        $path = $this->prints->generatedOutputPath(
            $this->safeFilename($filename, 'docx')
        );
        (new Word2007($document))->save($path);
        return $path;
    }

    private function safeFilename(string $filename, string $extension): string
    {
        $base = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '_',
            pathinfo($filename, PATHINFO_FILENAME)
        );
        return ($base ?: 'export') . '.' . $extension;
    }

    private function schoolName(): string
    {
        return defined('SCHOOL_NAME')
            ? (string) SCHOOL_NAME
            : 'Kingsway Preparatory School';
    }

    private function schoolMotto(): string
    {
        return defined('SCHOOL_MOTTO')
            ? (string) SCHOOL_MOTTO
            : 'In God We Soar';
    }
}
