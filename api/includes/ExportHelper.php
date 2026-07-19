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
        
        // Add school branding header
        $schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School';
        $schoolMotto = defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar';
        
        fputcsv($out, [$schoolName]);
        fputcsv($out, [$schoolMotto]);
        fputcsv($out, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($out, []); // Empty row as separator
        
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
        
        // Add school branding header
        $schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School';
        $schoolMotto = defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar';
        
        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', $schoolMotto);
        $sheet->setCellValue('A3', 'Generated: ' . date('Y-m-d H:i:s'));
        
        // Style header
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
        
        // Add data starting from row 5
        if (!empty($rows)) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A5');
            $sheet->fromArray($rows, null, 'A6');
            
            // Style header row
            $headerRow = 5;
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
                ->getFont()->setBold(true);
            $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8E8E8');
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
        
        // Add school branding header
        $schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School';
        $schoolMotto = defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar';
        
        $sheet->setCellValue('A1', $schoolName);
        $sheet->setCellValue('A2', $schoolMotto);
        $sheet->setCellValue('A3', 'Generated: ' . date('Y-m-d H:i:s'));
        
        // Style header
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A3')->getFont()->setSize(9)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF666666'));
        
        // Add data starting from row 5
        if (!empty($rows)) {
            $sheet->fromArray(array_keys($rows[0]), null, 'A5');
            $sheet->fromArray($rows, null, 'A6');
            
            // Style header row
            $headerRow = 5;
            $lastColumn = $sheet->getHighestColumn();
            $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
                ->getFont()->setBold(true);
            $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
                ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8E8E8');
        }
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        $writer = new Xls($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function exportPDF($rows, $filename) {
        // DEPRECATED: Use PrintService for professional PDF generation with school branding
        // This method is kept for backward compatibility but routes to PrintService
        try {
            // Try to use PrintService if available
            if (class_exists('App\API\Services\PrintService')) {
                $printService = new \App\API\Services\PrintService();
                
                // Convert array data to PrintService format
                $columns = [];
                if (!empty($rows)) {
                    foreach (array_keys($rows[0]) as $key) {
                        $columns[] = ['key' => $key, 'label' => ucfirst(str_replace('_', ' ', $key))];
                    }
                }
                
                $pdfPath = $printService->printTable($rows, [
                    'filename' => $filename,
                    'columns' => $columns,
                    'title' => 'Report',
                    'orientation' => 'landscape',
                    'paperSize' => 'A4'
                ]);
                
                // Output the PDF file
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '.pdf"');
                readfile($pdfPath);
                exit;
            }
        } catch (\Exception $e) {
            // Fallback to original basic implementation if PrintService fails
            error_log('PrintService not available, using basic PDF generation: ' . $e->getMessage());
        }
        
        // Original basic implementation as fallback
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
        require_once __DIR__ . '/../../../vendor/autoload.php';
        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream($filename . '.pdf');
        exit;
        }
    }

    private function exportWord($rows, $filename) {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        
        // Add school branding header
        $schoolName = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Kingsway Preparatory School';
        $schoolMotto = defined('SCHOOL_MOTTO') ? SCHOOL_MOTTO : 'In God We Soar';
        $schoolAddress = defined('SCHOOL_ADDRESS') ? SCHOOL_ADDRESS : 'P.O Box 203-20203, Londiani, Kenya';
        $schoolPhone = defined('SCHOOL_PHONE') ? SCHOOL_PHONE : '+254-720-113030 / +254-720-113031';
        $schoolEmail = defined('SCHOOL_EMAIL') ? SCHOOL_EMAIL : 'info@kingswaypreparatoryschool.sc.ke';
        
        $section->addText($schoolName, ['bold' => true, 'size' => 16]);
        $section->addText($schoolMotto, ['italic' => true, 'size' => 12]);
        $section->addText($schoolAddress, ['size' => 10]);
        $section->addText($schoolPhone, ['size' => 10]);
        $section->addText($schoolEmail, ['size' => 10]);
        $section->addText('Generated: ' . date('F j, Y g:i A'), ['size' => 9, 'color' => '666666']);
        $section->addTextBreak();
        
        if (!empty($rows)) {
            $table = $section->addTable(['borderSize' => 6, 'borderColor' => '000000']);
            
            // Add header row
            $table->addRow();
            foreach (array_keys($rows[0]) as $col) {
                $table->addCell(2000)->addText(ucfirst(str_replace('_', ' ', $col)), ['bold' => true]);
            }
            
            // Add data rows
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
