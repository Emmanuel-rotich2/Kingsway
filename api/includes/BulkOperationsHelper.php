<?php
namespace App\API\Includes;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Exception;

class BulkOperationsHelper
{
    private $db;
    private $allowedExtensions = ['csv', 'xlsx', 'xls', 'ods'];
    private $maxFileSize = 5242880; // 5MB

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Process uploaded file and return data array
     */
    public function processUploadedFile($file)
    {
        try {
            $this->validateFile($file);

            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $tempFile = $file['tmp_name'];

            try {
                if ($extension === 'csv') {
                    $handle = fopen($tempFile, 'rb');
                    if (!$handle) {
                        throw new Exception('Unable to read uploaded CSV file');
                    }
                    $firstLine = fgets($handle);
                    if ($firstLine === false) {
                        fclose($handle);
                        throw new Exception('File is empty');
                    }
                    $delimiter = $this->detectCsvDelimiter($firstLine);
                    rewind($handle);
                    $rows = [];
                    while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
                        if (isset($row[0])) {
                            $row[0] = preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $row[0]);
                        }
                        $rows[] = $row;
                    }
                    fclose($handle);
                } else {
                    $rows = $this->readOfficeSpreadsheet($tempFile);
                }

                if (empty($rows)) {
                    throw new Exception('File is empty');
                }

                // First row is headers
                $headers = array_map(static fn($header) => strtolower(trim((string) $header)), $rows[0]);
                $columns = [];
                foreach ($headers as $index => $header) {
                    if ($header !== '') $columns[] = $index;
                }
                if (!$columns) {
                    throw new Exception('The uploaded file has no column headers');
                }
                $headers = array_map(static fn($index) => $headers[$index], $columns);
                $data = [];

                // Process each row
                for ($i = 1; $i < count($rows); $i++) {
                    $sourceValues = $rows[$i];
                    $values = array_map(static fn($column) => $sourceValues[$column] ?? null, $columns);
                    $row = array_combine($headers, $values);
                    if (!$row || !array_filter($row, static fn($value) => trim((string) $value) !== '')) {
                        continue;
                    }
                    $data[] = $row;
                }

                return [
                    'status' => 'success',
                    'data' => $data,
                    'headers' => $headers
                ];
            } catch (SpreadsheetException $e) {
                throw new Exception('Error processing spreadsheet: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'An internal error occurred.'
            ];
        }
    }

    private function detectCsvDelimiter(string $line): string
    {
        $best = ',';
        $bestCount = 0;
        foreach ([',', ';', "\t"] as $delimiter) {
            $count = count(str_getcsv($line, $delimiter, '"', ''));
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }
        return $best;
    }

    /**
     * Read workbooks through PhpSpreadsheet installed by Composer. The active
     * web PHP runtime must have ext-zip enabled; composer.json declares this
     * platform requirement so deployment fails early when it is unavailable.
     */
    private function readOfficeSpreadsheet(string $file): array
    {
        if (!class_exists('ZipArchive') && function_exists('proc_open')) {
            // Development-only compatibility for local Apache builds whose
            // PHP SAPI lacks ext-zip while the matching CLI has it. Production
            // hosting should satisfy composer.json's ext-zip requirement.
            $binaryCandidates = array_unique(array_filter([
                PHP_BINDIR . '/php',
                '/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
                '/usr/bin/php',
            ], static fn($path) => is_file($path) && is_executable($path)));
            $readerCode = <<<'PHP'
require $argv[1];
$workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($argv[2]);
$rows = $workbook->getActiveSheet()->toArray(null, true, false, false);
echo json_encode($rows, JSON_THROW_ON_ERROR);
PHP;
            $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

            foreach ($binaryCandidates as $binary) {
                $probe = $this->runCli([$binary, '-r', 'exit(class_exists("ZipArchive") ? 0 : 1);']);
                if ($probe['exit_code'] !== 0) {
                    continue;
                }
                $result = $this->runCli([$binary, '-r', $readerCode, $autoload, $file]);
                if ($result['exit_code'] !== 0) {
                    throw new Exception('Unable to decode uploaded spreadsheet');
                }
                $rows = json_decode($result['stdout'], true);
                if (!is_array($rows)) {
                    throw new Exception('Spreadsheet reader returned invalid data');
                }
                return $rows;
            }
        }

        $spreadsheet = IOFactory::load($file);
        return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
    }

    private function runCli(array $command): array
    {
        $pipes = [];
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return ['exit_code' => 1, 'stdout' => '', 'stderr' => ''];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['exit_code' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }

    /**
     * Validate uploaded file
     */
    private function validateFile($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed with error code: ' . $file['error']);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $this->allowedExtensions));
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds limit of 5MB');
        }
    }

    /**
     * Perform bulk insert operation
     */
    public function bulkInsert($table, $data, $uniqueColumns = [])
    {
        try {
            if (empty($data)) {
                throw new Exception('No data provided for bulk insert');
            }

            $this->db->beginTransaction();

            // Prefer stored procedure if available (JSON contract)
            if ($this->procedureExists('sp_bulk_upsert_json')) {
                $payload = [
                    'mode' => 'insert',
                    'table' => $table,
                    'rows' => $data,
                    'unique' => array_values($uniqueColumns)
                ];
                $stmt = $this->db->prepare('CALL sp_bulk_upsert_json(?)');
                $stmt->execute([json_encode($payload)]);
                $this->db->commit();

                // Fallback event emission for UI auto-update
                $this->emitSystemEvent('bulk.' . $table . '.insert', [
                    'count' => count($data)
                ]);

                return [
                    'status' => 'success',
                    'message' => count($data) . ' records processed successfully',
                    'duplicates' => []
                ];
            }

            // Fallback: generic multi-row insert
            // Get columns from first row
            $columns = array_keys($data[0]);
            $values = [];
            $duplicates = [];

            $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ";
            $rowPlaceholders = "(" . implode(', ', array_fill(0, count($columns), '?')) . ")";
            $allPlaceholders = [];

            if (!empty($uniqueColumns)) {
                $updateClauses = [];
                foreach ($columns as $col) {
                    if (!in_array($col, $uniqueColumns)) {
                        $updateClauses[] = "$col = VALUES($col)";
                    }
                }
                $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateClauses);
            }

            foreach ($data as $row) {
                $allPlaceholders[] = $rowPlaceholders;
                foreach ($columns as $column) {
                    $values[] = $row[$column] ?? null;
                }
            }

            $sql .= implode(', ', $allPlaceholders);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $this->db->commit();

            // Event emission fallback
            $this->emitSystemEvent('bulk.' . $table . '.insert', [
                'count' => count($data)
            ]);

            return [
                'status' => 'success',
                'message' => count($data) . ' records processed successfully',
                'duplicates' => $duplicates
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Perform bulk update operation
     */
    public function bulkUpdate($table, $data, $identifierColumn)
    {
        try {
            if (empty($data)) {
                throw new Exception('No data provided for bulk update');
            }

            $this->db->beginTransaction();

            // Get columns from first row
            $updateColumns = array_keys($data[0]);
            $updateColumns = array_diff($updateColumns, [$identifierColumn]);

            // Build CASE statements for each column
            $cases = [];
            $ids = [];
            foreach ($updateColumns as $column) {
                $whenClauses = [];
                foreach ($data as $row) {
                    $whenClauses[] = "WHEN ? THEN ?";
                    $ids[] = $row[$identifierColumn];
                    $cases[] = $row[$column];
                }
                $setClauses[] = "$column = CASE $identifierColumn " .
                    implode(' ', $whenClauses) .
                    " ELSE $column END";
            }

            // Build and execute query
            $sql = "UPDATE $table SET " . implode(', ', $setClauses) .
                " WHERE $identifierColumn IN (" .
                implode(',', array_fill(0, count($data), '?')) . ")";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge(array_merge($ids, $cases), $ids));

            $this->db->commit();

            return [
                'status' => 'success',
                'message' => count($data) . ' records updated successfully'
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // Check existence of stored procedure
    private function procedureExists($name)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = 'PROCEDURE'");
        $stmt->execute([$name]);
        return (bool) $stmt->fetchColumn();
    }

    // Emit system event (fallback mechanism)
    private function emitSystemEvent($eventType, array $data = [])
    {
        try {
            \App\API\Includes\FileLogger::write('events', [
                'type' => 'event',
                'event_type' => $eventType,
                'event_data' => $data,
            ]);
        } catch (Exception $e) {
            // Swallow errors; bulk ops should not fail due to event emission
        }
    }
}
