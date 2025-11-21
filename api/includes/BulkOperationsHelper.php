<?php
namespace App\API\Includes;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use Exception;

class BulkOperationsHelper
{
    private $db;
    private $allowedExtensions = ['csv', 'xlsx', 'xls'];
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
                // Load the spreadsheet
                $spreadsheet = IOFactory::load($tempFile);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray();

                if (empty($rows)) {
                    throw new Exception('File is empty');
                }

                // First row is headers
                $headers = array_map('strtolower', array_map('trim', $rows[0]));
                $data = [];

                // Process each row
                for ($i = 1; $i < count($rows); $i++) {
                    if (count($rows[$i]) !== count($headers)) {
                        continue; // Skip malformed rows
                    }
                    $row = array_combine($headers, $rows[$i]);
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
                'message' => $e->getMessage()
            ];
        }
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
            $stmt = $this->db->prepare("INSERT INTO system_events (event_type, event_data, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$eventType, json_encode($data)]);
        } catch (Exception $e) {
            // Swallow errors; bulk ops should not fail due to event emission
        }
    }
}