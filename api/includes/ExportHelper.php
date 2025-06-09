<?php
namespace App\API\Includes;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\Word2007;
use Dompdf\Dompdf;

class ExportHelper {
    public function export($rows, $format, $filename = 'export') {
        switch (strtolower($format)) {
            case 'csv':
                $this->exportCSV($rows, $filename);
                break;
            case 'xlsx':
            case 'excel':
                $this->exportExcel($rows, $filename);
                break;
            case 'xls':
                $this->exportXls($rows, $filename);
                break;
            case 'pdf':
                $this->exportPDF($rows, $filename);
                break;
            case 'word':
                $this->exportWord($rows, $filename);
                break;
            default:
                $this->exportCSV($rows, $filename);
        }
    }

    private function exportCSV($rows, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }

    private function exportExcel($rows, $filename) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if (!empty($rows)) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A1');
            $sheet->fromArray($rows, null, 'A2');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function exportXls($rows, $filename) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        if (!empty($rows)) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A1');
            $sheet->fromArray($rows, null, 'A2');
        }
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function exportPDF($rows, $filename) {
        $html = '<table border="1" cellpadding="5"><thead><tr>';
        if (!empty($rows)) {
            foreach (array_keys($rows[0]) as $col) {
                $html .= '<th>' . htmlspecialchars($col) . '</th>';
            }
            $html .= '</tr></thead><tbody>';
            foreach ($rows as $row) {
                $html .= '<tr>';
                foreach ($row as $cell) {
                    $html .= '<td>' . htmlspecialchars($cell) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf');
        exit;
    }

    private function exportWord($rows, $filename) {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        if (!empty($rows)) {
            $table = $section->addTable();
            $table->addRow();
            foreach (array_keys($rows[0]) as $col) {
                $table->addCell(2000)->addText($col);
            }
            foreach ($rows as $row) {
                $table->addRow();
                foreach ($row as $cell) {
                    $table->addCell(2000)->addText($cell);
                }
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . $filename . '.docx"');
        $writer = new Word2007($phpWord);
        $writer->save('php://output');
        exit;
    }
}
