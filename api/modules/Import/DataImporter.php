<?php
declare(strict_types=1);

namespace App\API\Modules\Import;

use App\API\Includes\BulkOperationsHelper;
use Exception;
use PDO;

/**
 * DataImporter — handles validation and import for all data types.
 *
 * Each import type defines:
 *  - required: columns that must be present and non-empty
 *  - optional: columns that may be absent
 *  - table:    target DB table (for direct bulk inserts)
 *  - handler:  method name on this class for custom logic
 */
class DataImporter
{
    private $db;
    private BulkOperationsHelper $bulk;

    // ── Import type registry ────────────────────────────────────────────────
    public const TYPES = [
        // STUDENTS
        'students'          => ['category'=>'students',  'label'=>'Student Profiles',       'required'=>['first_name','last_name','admission_no','date_of_birth','gender','class_name']],
        'parents'           => ['category'=>'students',  'label'=>'Parents / Guardians',    'required'=>['student_admission_no','parent_name','relationship','phone']],
        // STAFF
        'staff'             => ['category'=>'staff',     'label'=>'Teaching & Admin Staff',  'required'=>['first_name','last_name','staff_number','designation']],
        // FINANCIAL
        'fee_structure'     => ['category'=>'financial', 'label'=>'Fee Structure',           'required'=>['class_name','fee_type','amount','term','year']],
        'fee_payments'      => ['category'=>'financial', 'label'=>'Fee Payments',            'required'=>['student_admission_no','amount','payment_date','term','year']],
        'expenses'          => ['category'=>'financial', 'label'=>'Expenditure Records',     'required'=>['date','category','description','amount']],
        'budget'            => ['category'=>'financial', 'label'=>'Budget Allocations',      'required'=>['year','term','department','category','budgeted_amount']],
        // ACADEMIC
        'classes'           => ['category'=>'academic',  'label'=>'Classes & Streams',       'required'=>['class_name','level']],
        'subjects'          => ['category'=>'academic',  'label'=>'Subjects',                'required'=>['subject_name','class_name']],
        'exam_results'      => ['category'=>'academic',  'label'=>'Exam Results',            'required'=>['student_admission_no','subject','term','year','score','max_score']],
        'formative_scores'  => ['category'=>'academic',  'label'=>'Formative Assessment Scores','required'=>['student_admission_no','subject','assessment_type','term','year','score']],
        'attendance'        => ['category'=>'academic',  'label'=>'Attendance Records',      'required'=>['student_admission_no','date','status']],
        'term_dates'        => ['category'=>'academic',  'label'=>'Term Dates',              'required'=>['academic_year','term_number','start_date','end_date']],
        // INVENTORY
        'inventory'         => ['category'=>'inventory', 'label'=>'General Inventory',       'required'=>['item_name','category','quantity','unit']],
        'food_stock'        => ['category'=>'inventory', 'label'=>'Food Stock',              'required'=>['item_name','quantity','unit','purchase_date']],
        'uniform_stock'     => ['category'=>'inventory', 'label'=>'Uniform Stock',           'required'=>['item_name','size','quantity']],
    ];

    public function __construct($db)
    {
        $this->db  = $db;
        $this->bulk = new BulkOperationsHelper($db);
    }

    // ── Public API ──────────────────────────────────────────────────────────

    public function preview(string $type, array $file): array
    {
        $this->validateType($type);
        $parsed = $this->bulk->processUploadedFile($file);
        if ($parsed['status'] !== 'success') {
            throw new Exception($parsed['message'] ?? 'File parsing failed');
        }

        $rows      = $parsed['data'];
        $headers   = $parsed['headers'] ?? [];
        $required  = self::TYPES[$type]['required'];
        $preview   = array_slice($rows, 0, 10);
        $errors    = $this->validateRows($type, $rows, $required);
        $missing   = array_diff($required, $headers);

        return [
            'type'          => $type,
            'label'         => self::TYPES[$type]['label'],
            'total_rows'    => count($rows),
            'preview_rows'  => $preview,
            'headers'       => $headers,
            'required_cols' => $required,
            'missing_cols'  => array_values($missing),
            'errors'        => array_slice($errors, 0, 50), // cap preview errors
            'error_count'   => count($errors),
            'valid_count'   => count($rows) - count(array_unique(array_column($errors, 'row'))),
        ];
    }

    public function execute(string $type, array $file, int $importedBy): array
    {
        $this->validateType($type);
        $parsed = $this->bulk->processUploadedFile($file);
        if ($parsed['status'] !== 'success') {
            throw new Exception($parsed['message'] ?? 'File parsing failed');
        }

        $rows     = $parsed['data'];
        $errors   = $this->validateRows($type, $rows, self::TYPES[$type]['required']);
        $errorRows = array_unique(array_column($errors, 'row'));

        // Filter out invalid rows
        $validRows = [];
        foreach ($rows as $idx => $row) {
            if (!in_array($idx + 1, $errorRows)) {
                $validRows[] = $row;
            }
        }

        $successCount = 0;
        $importErrors = $errors;

        if (!empty($validRows)) {
            try {
                $handler = 'import' . str_replace(' ', '', ucwords(str_replace('_', ' ', $type)));
                if (method_exists($this, $handler)) {
                    $result = $this->$handler($validRows);
                    $successCount = $result['inserted'] ?? count($validRows);
                    if (!empty($result['errors'])) {
                        $importErrors = array_merge($importErrors, $result['errors']);
                    }
                } else {
                    $successCount = count($validRows);
                }
            } catch (Exception $e) {
                $importErrors[] = ['row' => 0, 'field' => 'system', 'message' => 'An internal error occurred.'];
            }
        }

        // Log the import
        $this->logImport(
            $type, $file['name'] ?? 'unknown',
            count($rows), $successCount, count($errorRows),
            count($rows) - count($validRows) - count($errorRows),
            $importedBy, $importErrors,
            $successCount > 0 ? ($importErrors ? 'partial' : 'completed') : 'failed'
        );

        return [
            'type'         => $type,
            'label'        => self::TYPES[$type]['label'],
            'total_rows'   => count($rows),
            'success_rows' => $successCount,
            'error_rows'   => count(array_unique(array_column($importErrors, 'row'))),
            'skipped_rows' => count($rows) - count($validRows),
            'errors'       => array_slice($importErrors, 0, 100),
            'status'       => $successCount > 0 ? ($importErrors ? 'partial' : 'completed') : 'failed',
        ];
    }

    public function getLogs(?int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id,
                    JSON_UNQUOTE(JSON_EXTRACT(b.validation_summary, "$.import_type")) AS import_type,
                    b.source_filename AS original_filename,
                    b.total_rows, b.valid_rows AS success_rows, b.invalid_rows AS error_rows,
                    b.status,
                    CONCAT(p.first_name," ",p.last_name) AS imported_by_name,
                    b.created_at
             FROM staff_import_batches b
             LEFT JOIN staff st ON st.id = b.imported_by
             LEFT JOIN persons p ON p.id = st.person_id
             ORDER BY b.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLog(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT b.id,
                    JSON_UNQUOTE(JSON_EXTRACT(b.validation_summary, "$.import_type")) AS import_type,
                    b.source_filename AS original_filename,
                    b.total_rows, b.valid_rows AS success_rows, b.invalid_rows AS error_rows,
                    b.status, b.failure_message,
                    0 AS skipped_rows,
                    CONCAT(p.first_name," ",p.last_name) AS imported_by_name,
                    b.created_at, b.completed_at
             FROM staff_import_batches b
             LEFT JOIN staff st ON st.id = b.imported_by
             LEFT JOIN persons p ON p.id = st.person_id
             WHERE b.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['validation_summary']) {
            $summary = json_decode($row['validation_summary'], true);
            $row['error_details'] = $summary['errors'] ?? [];
        }
        return $row ?: null;
    }

    public function getTemplateFile(string $type): ?string
    {
        $this->validateType($type);
        $path = dirname(__DIR__, 3) . '/templates/import/' . $type . '.csv';
        return file_exists($path) ? $path : null;
    }

    // ── Validation ──────────────────────────────────────────────────────────

    private function validateRows(string $type, array $rows, array $required): array
    {
        $errors = [];
        foreach ($rows as $idx => $row) {
            $rowNum = $idx + 1;
            // Required fields
            foreach ($required as $col) {
                if (!isset($row[$col]) || trim((string)$row[$col]) === '') {
                    $errors[] = ['row' => $rowNum, 'field' => $col, 'message' => "Required field '$col' is empty"];
                }
            }
            // Type-specific validation
            $typeErrors = $this->validateRowForType($type, $row, $rowNum);
            $errors = array_merge($errors, $typeErrors);
        }
        return $errors;
    }

    private function validateRowForType(string $type, array $row, int $rowNum): array
    {
        $errors = [];
        switch ($type) {
            case 'students':
                if (!empty($row['gender']) && !in_array(strtolower($row['gender']), ['male','female','m','f'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'gender','message'=>'Gender must be male or female'];
                }
                if (!empty($row['date_of_birth']) && !$this->isValidDate($row['date_of_birth'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'date_of_birth','message'=>'Invalid date format (use YYYY-MM-DD)'];
                }
                if (!empty($row['admission_date']) && !$this->isValidDate($row['admission_date'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'admission_date','message'=>'Invalid date format (use YYYY-MM-DD)'];
                }
                break;
            case 'fee_payments':
                if (!empty($row['amount']) && !is_numeric($row['amount'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'amount','message'=>'Amount must be numeric'];
                }
                if (!empty($row['payment_date']) && !$this->isValidDate($row['payment_date'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'payment_date','message'=>'Invalid date format (use YYYY-MM-DD)'];
                }
                if (!empty($row['term']) && !in_array((int)$row['term'], [1,2,3])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'term','message'=>'Term must be 1, 2, or 3'];
                }
                break;
            case 'exam_results':
                if (!empty($row['score']) && !is_numeric($row['score'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'score','message'=>'Score must be numeric'];
                }
                if (!empty($row['max_score']) && !is_numeric($row['max_score'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'max_score','message'=>'Max score must be numeric'];
                }
                if (!empty($row['score']) && !empty($row['max_score']) &&
                    is_numeric($row['score']) && is_numeric($row['max_score']) &&
                    (float)$row['score'] > (float)$row['max_score']) {
                    $errors[] = ['row'=>$rowNum,'field'=>'score','message'=>'Score cannot exceed max score'];
                }
                break;
            case 'attendance':
                if (!empty($row['date']) && !$this->isValidDate($row['date'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'date','message'=>'Invalid date format (use YYYY-MM-DD)'];
                }
                if (!empty($row['status']) && !in_array(strtolower($row['status']), ['present','absent','late','excused','half-day'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'status','message'=>'Status must be: present|absent|late|excused|half-day'];
                }
                break;
            case 'expenses':
            case 'budget':
                if (!empty($row['amount'] ?? $row['budgeted_amount']) &&
                    !is_numeric($row['amount'] ?? $row['budgeted_amount'])) {
                    $errors[] = ['row'=>$rowNum,'field'=>'amount','message'=>'Amount must be numeric'];
                }
                break;
            case 'term_dates':
                foreach (['start_date','end_date'] as $df) {
                    if (!empty($row[$df]) && !$this->isValidDate($row[$df])) {
                        $errors[] = ['row'=>$rowNum,'field'=>$df,'message'=>"Invalid date '$df' (use YYYY-MM-DD)"];
                    }
                }
                break;
        }
        return $errors;
    }

    // ── Per-type import handlers ────────────────────────────────────────────

    private function importStudents(array $rows): array
    {
        $inserted = 0; $errors = [];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $idx => $row) {
                $row = $this->normalise($row);
                // Map gender
                $gender = strtolower($row['gender'] ?? 'male');
                $gender = in_array($gender, ['f','female']) ? 'female' : 'male';

                $personStmt = $this->db->prepare(
                    'INSERT INTO persons (first_name, middle_name, last_name, dob, gender, national_id_no, email, phone)
                     VALUES (:fn,:mn,:ln,:dob,:g,:nid,:em,:ph)'
                );
                $personStmt->execute([
                    ':fn'  => $row['first_name'],
                    ':mn'  => $row['middle_name'] ?? null,
                    ':ln'  => $row['last_name'],
                    ':dob' => $row['date_of_birth'] ?? null,
                    ':g'   => $gender,
                    ':nid' => $row['national_id'] ?? null,
                    ':em'  => $row['email'] ?? null,
                    ':ph'  => $row['phone'] ?? null,
                ]);
                $personId = (int)$this->db->lastInsertId();

                $stmt = $this->db->prepare(
                    'INSERT INTO students
                     (person_id, admission_no, student_type_id, admission_date, status, blood_group, created_at, updated_at)
                     VALUES
                     (:pid,:a,:st,:ad,:s,:bg,NOW(),NOW())
                     ON DUPLICATE KEY UPDATE
                     person_id=VALUES(person_id), student_type_id=VALUES(student_type_id),
                     status=VALUES(status), updated_at=NOW()'
                );
                $stmt->execute([
                    ':pid' => $personId,
                    ':a'   => $row['admission_no'],
                    ':st'  => $this->resolveStudentTypeId($row['student_type'] ?? 'DAY'),
                    ':ad'  => $row['admission_date'] ?? date('Y-m-d'),
                    ':s'   => $row['status'] ?? 'active',
                    ':bg'  => $row['blood_group'] ?? null,
                ]);
                $studentId = (int)($this->db->lastInsertId() ?: $this->resolveStudentId($row['admission_no']));

                // Import parent if present
                if ($studentId && !empty($row['parent_name']) && !empty($row['parent_phone'])) {
                    $this->upsertParent($studentId, $row);
                }
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function importFeePayments(array $rows): array
    {
        $inserted = 0; $errors = [];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $idx => $row) {
                $row      = $this->normalise($row);
                $studentId = $this->resolveStudentId($row['student_admission_no'] ?? '');
                if (!$studentId) {
                    $errors[] = ['row'=>$idx+1,'field'=>'student_admission_no','message'=>"Student '{$row['student_admission_no']}' not found"];
                    continue;
                }
                $method = strtolower($row['payment_method'] ?? 'cash');
                if (!in_array($method, ['cash','bank_transfer','mpesa','cheque','other'])) {
                    $method = 'cash';
                }
                $stmt = $this->db->prepare(
                    'INSERT INTO payments
                     (student_id, receipt_no, amount, payment_date, method, reference, status, notes, created_at, updated_at)
                     VALUES
                     (:sid,:rno,:amt,:pd,:m,:ref,"confirmed",:notes,NOW(),NOW())
                     ON DUPLICATE KEY UPDATE amount=VALUES(amount), status=VALUES(status)'
                );
                $stmt->execute([
                    ':sid'  => $studentId,
                    ':rno'  => $row['receipt_no'] ?? $row['reference_number'] ?? null,
                    ':amt'  => (float)$row['amount'],
                    ':pd'   => $row['payment_date'] . ' 00:00:00',
                    ':m'    => $method,
                    ':ref'  => $row['reference_number'] ?? null,
                    ':notes'=> $row['description'] ?? $row['notes'] ?? null,
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function importStaff(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $gender = strtolower($row['gender'] ?? 'male');
                $gender = in_array($gender, ['f','female']) ? 'female' : 'male';

                $personStmt = $this->db->prepare(
                    'INSERT INTO persons (first_name, middle_name, last_name, dob, gender, national_id_no, email, phone)
                     VALUES (:fn,:mn,:ln,:dob,:g,:nid,:em,:ph)'
                );
                $personStmt->execute([
                    ':fn'  => $row['first_name'],
                    ':mn'  => $row['middle_name'] ?? null,
                    ':ln'  => $row['last_name'],
                    ':dob' => $row['date_of_birth'] ?? null,
                    ':g'   => $gender,
                    ':nid' => $row['national_id'] ?? null,
                    ':em'  => $row['email'] ?? null,
                    ':ph'  => $row['phone'] ?? null,
                ]);
                $personId = (int)$this->db->lastInsertId();

                $stmt = $this->db->prepare(
                    'INSERT INTO staff
                     (person_id, staff_no, position, contract_type, employment_date, status, created_at, updated_at)
                     VALUES
                     (:pid,:sn,:pos,:ct,:dj,:s,NOW(),NOW())
                     ON DUPLICATE KEY UPDATE
                     person_id=VALUES(person_id), position=VALUES(position),
                     status=VALUES(status), updated_at=NOW()'
                );
                $stmt->execute([
                    ':pid' => $personId,
                    ':sn'  => $row['staff_number'],
                    ':pos' => $row['designation'] ?? 'Teacher',
                    ':ct'  => $row['employment_type'] ?? 'permanent',
                    ':dj'  => $row['date_joined'] ?? date('Y-m-d'),
                    ':s'   => 'active',
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importParents(array $rows): array
    {
        $inserted = 0; $errors = [];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $idx => $row) {
                $row = $this->normalise($row);
                $studentId = $this->resolveStudentId($row['student_admission_no'] ?? '');
                if (!$studentId) {
                    $errors[] = ['row'=>$idx+1,'field'=>'student_admission_no','message'=>"Student not found: {$row['student_admission_no']}"];
                    continue;
                }
                $this->upsertParent($studentId, $row);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function importFeeStructure(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $feeName = trim($row['fee_type'] ?? '');
                if (!$feeName) continue;
                $catalogId = $this->resolveFeeCatalog($feeName, (float)$row['amount']);
                $ayId      = $this->resolveAcademicYearId((int)$row['year']);
                $aytId     = $this->resolveAcademicYearTermId($ayId, $this->resolveTermId((int)$row['term']));
                $classId   = $this->resolveClassId($row['class_name'] ?? '');
                $aycId     = $classId ? $this->resolveAcademicYearClassId($ayId, $classId) : null;

                $stmt = $this->db->prepare(
                    'INSERT INTO academic_year_fee_schedules
                     (academic_year_id, academic_year_term_id, academic_year_class_id,
                      student_type_id, fee_catalog_id, amount, status, created_at, updated_at)
                     VALUES (:ay,:ayt,:ayc,1,:fc,:amt,"active",NOW(),NOW())
                     ON DUPLICATE KEY UPDATE amount=VALUES(amount), status=VALUES(status)'
                );
                $stmt->execute([
                    ':ay'  => $ayId,
                    ':ayt' => $aytId,
                    ':ayc' => $aycId,
                    ':fc'  => $catalogId,
                    ':amt' => (float)$row['amount'],
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importExpenses(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $method = strtolower($row['payment_method'] ?? 'cash');
                if (!in_array($method, ['cash','mpesa','bank_transfer','cheque','direct_debit'])) {
                    $method = 'cash';
                }
                $stmt = $this->db->prepare(
                    'INSERT INTO expenses
                     (expense_number, category_id, description, academic_year, term,
                      payment_method, reference_number, receipt_number, notes, amount,
                      expense_date, created_by, status, created_at, updated_at)
                     VALUES (:num,:cat,:desc,:yr,:term,:pm,:ref,:rcpt,:notes,:amt,:d,1,"approved",NOW(),NOW())'
                );
                $stmt->execute([
                    ':num'  => $row['expense_number'] ?? null,
                    ':cat'  => $this->resolveExpenseCategoryId($row['category'] ?? ''),
                    ':desc' => $row['description'],
                    ':yr'   => (int)($row['year'] ?? date('Y')),
                    ':term' => isset($row['term']) ? (int)$row['term'] : null,
                    ':pm'   => $method,
                    ':ref'  => $row['reference_number'] ?? null,
                    ':rcpt' => $row['receipt_number'] ?? null,
                    ':notes'=> $row['notes'] ?? null,
                    ':amt'  => (float)$row['amount'],
                    ':d'    => $row['date'] ?? date('Y-m-d'),
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importBudget(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $budgetId = $this->resolveBudgetId(
                    (string)$row['department'],
                    (int)$row['year'],
                    isset($row['term']) ? (int)$row['term'] : null
                );
                $catId = $this->resolveExpenseCategoryId($row['category'] ?? '');
                $stmt = $this->db->prepare(
                    'INSERT INTO budget_line_items
                     (budget_id, category_id, description, allocated_amount, notes, created_at, updated_at)
                     VALUES (:bid,:cat,:desc,:amt,:notes,NOW(),NOW())'
                );
                $stmt->execute([
                    ':bid'  => $budgetId,
                    ':cat'  => $catId,
                    ':desc' => $row['description'] ?? null,
                    ':amt'  => (float)$row['budgeted_amount'],
                    ':notes'=> $row['notes'] ?? null,
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importExamResults(array $rows): array
    {
        return $this->importAssessmentScores($rows, 'exam');
    }

    private function importFormativeScores(array $rows): array
    {
        return $this->importAssessmentScores($rows, 'formative');
    }

    private function importAssessmentScores(array $rows, string $kind): array
    {
        $inserted = 0; $errors = [];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $idx => $row) {
                $row = $this->normalise($row);
                $studentId = $this->resolveStudentId($row['student_admission_no'] ?? '');
                if (!$studentId) {
                    $errors[] = ['row'=>$idx+1,'field'=>'student_admission_no','message'=>"Student not found: {$row['student_admission_no']}"];
                    continue;
                }
                $ayId  = $this->resolveAcademicYearId((int)$row['year']);
                $aytId = $this->resolveAcademicYearTermId($ayId, $this->resolveTermId((int)$row['term']));

                $enr = $this->db->prepare(
                    'SELECT id, academic_year_class_stream_id FROM student_academic_enrollments
                     WHERE student_id = :s AND academic_year_id = :a AND enrollment_status = "active"
                     ORDER BY id DESC LIMIT 1'
                );
                $enr->execute([':s'=>$studentId, ':a'=>$ayId]);
                $enrRow = $enr->fetch(PDO::FETCH_ASSOC);
                if (!$enrRow) {
                    $errors[] = ['row'=>$idx+1,'field'=>'enrollment','message'=>"Student {$row['student_admission_no']} has no active enrollment for {$row['year']}"];
                    continue;
                }
                $enrollmentId = (int)$enrRow['id'];
                $aycsId       = (int)$enrRow['academic_year_class_stream_id'];

                $subject  = trim($row['subject'] ?? '');
                if (!$subject) {
                    $errors[] = ['row'=>$idx+1,'field'=>'subject','message'=>'Subject is required'];
                    continue;
                }
                $laId = $this->resolveLearningAreaId($subject);
                $maxScore = (float)($kind === 'formative' ? ($row['max_score'] ?? 20) : $row['max_score']);
                $score    = (float)$row['score'];
                $date     = $row['date'] ?? ($kind === 'formative' ? date('Y-m-d') : date('Y-m-d'));
                $title    = $kind === 'formative'
                    ? ('Formative: ' . $subject . ' (' . strtoupper($row['assessment_type'] ?? '') . ')')
                    : ('Exam: ' . $subject);
                $assessmentId = $this->resolveAssessmentId($aycsId, $aytId, $laId, $title, $maxScore, $date);

                $pct   = $maxScore > 0 ? round($score / $maxScore * 100, 1) : 0;
                $grade = $row['grade'] ?? $this->computeCBCGrade($pct);

                $stmt = $this->db->prepare(
                    'INSERT INTO assessment_results
                     (assessment_id, student_academic_enrollment_id, marks_obtained,
                      grade, points, remarks, is_submitted, is_approved, responder_type, created_at)
                     VALUES (:aid,:eid,:sc,:grade,:pts,:rem,1,0,"teacher",NOW())
                     ON DUPLICATE KEY UPDATE
                       marks_obtained=VALUES(marks_obtained),
                       grade=VALUES(grade),
                       points=VALUES(points),
                       remarks=VALUES(remarks)'
                );
                $stmt->execute([
                    ':aid'   => $assessmentId,
                    ':eid'   => $enrollmentId,
                    ':sc'    => $score,
                    ':grade' => $grade,
                    ':pts'   => $row['points'] ?? null,
                    ':rem'   => $row['remarks'] ?? null,
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function importAttendance(array $rows): array
    {
        $inserted = 0; $errors = [];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $idx => $row) {
                $row = $this->normalise($row);
                $studentId = $this->resolveStudentId($row['student_admission_no'] ?? '');
                if (!$studentId) {
                    $errors[] = ['row'=>$idx+1,'field'=>'student_admission_no','message'=>"Student not found: {$row['student_admission_no']}"];
                    continue;
                }
                $ayId = $this->resolveAcademicYearId((int)($row['year'] ?? date('Y')));
                $enr = $this->db->prepare(
                    'SELECT id FROM student_academic_enrollments
                     WHERE student_id = :s AND academic_year_id = :a AND enrollment_status = "active"
                     ORDER BY id DESC LIMIT 1'
                );
                $enr->execute([':s'=>$studentId, ':a'=>$ayId]);
                $enrollmentId = $enr->fetchColumn();
                if (!$enrollmentId) {
                    $errors[] = ['row'=>$idx+1,'field'=>'enrollment','message'=>"Student {$row['student_admission_no']} has no active enrollment"];
                    continue;
                }
                $status = strtolower($row['status'] ?? 'present');
                if (!in_array($status, ['present','absent','late'])) {
                    $status = 'present';
                }
                $stmt = $this->db->prepare(
                    'INSERT INTO student_attendance
                     (student_academic_enrollment_id, date, status, absence_reason, notes, register_type, created_at)
                     VALUES (:eid,:d,:st,:reason,:notes,"class",NOW())
                     ON DUPLICATE KEY UPDATE status=VALUES(status), notes=VALUES(notes)'
                );
                $stmt->execute([
                    ':eid'    => (int)$enrollmentId,
                    ':d'      => $row['date'],
                    ':st'     => $status,
                    ':reason' => $row['reason'] ?? null,
                    ':notes'  => $row['notes'] ?? null,
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => $errors];
    }

    private function importClasses(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $name = trim($row['class_name']);
                if (!$name) continue;
                $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 10));
                $stmt = $this->db->prepare(
                    'INSERT INTO classes
                     (code, name, level_id, grade_level)
                     VALUES (:c,:n,:lv,:gl)
                     ON DUPLICATE KEY UPDATE level_id=VALUES(level_id)'
                );
                $stmt->execute([
                    ':c'  => $code ?: ('C' . mt_rand(100, 999)),
                    ':n'  => $name,
                    ':lv' => $this->resolveLevelForClass($name),
                    ':gl' => $name,
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importSubjects(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                if (empty($row['subject_name'])) continue;
                $this->resolveLearningAreaId($row['subject_name']);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importInventory(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $this->resolveInventoryItemId(
                    $row['item_name'] ?? '',
                    $this->resolveInventoryCategoryId($row['category'] ?? ''),
                    (float)($row['unit_price'] ?? 0),
                    (float)($row['quantity'] ?? 0),
                    $row['unit'] ?? 'pcs',
                    (int)($row['reorder_level'] ?? 10)
                );
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importFoodStock(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $itemId = $this->resolveInventoryItemId(
                    $row['item_name'] ?? '',
                    $this->resolveInventoryCategoryId($row['category'] ?? 'Food'),
                    (float)($row['unit_price'] ?? 0),
                    (float)($row['quantity'] ?? 0),
                    $row['unit'] ?? 'kg'
                );
                if (!$itemId) continue;
                $stmt = $this->db->prepare(
                    'INSERT INTO food_consumption_records
                     (consumption_date, inventory_item_id, quantity_planned, quantity_used, unit,
                      cost_per_unit, recorded_by, recorded_at, notes, created_at)
                     VALUES (:d,:iid,:qty,:qty,:unit,:cost,1,NOW(),:notes,NOW())'
                );
                $stmt->execute([
                    ':d'     => $row['purchase_date'],
                    ':iid'   => $itemId,
                    ':qty'   => (float)$row['quantity'],
                    ':unit'  => $row['unit'] ?? 'kg',
                    ':cost'  => (float)($row['unit_price'] ?? 0),
                    ':notes' => $row['notes'] ?? ($row['expiry_date'] ? 'expiry: ' . $row['expiry_date'] : null),
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importUniformStock(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $itemId = $this->resolveInventoryItemId(
                    $row['item_name'] ?? '',
                    $this->resolveInventoryCategoryId($row['category'] ?? 'Uniforms'),
                    (float)($row['unit_price'] ?? 0),
                    (int)($row['quantity'] ?? 0),
                    'pcs',
                    (int)($row['reorder_level'] ?? 5)
                );
                if (!$itemId || empty($row['size'])) continue;
                $stmt = $this->db->prepare(
                    'INSERT INTO uniform_sizes
                     (item_id, size, quantity_available, unit_price, reorder_level, last_restocked, created_at, updated_at)
                     VALUES (:iid,:sz,:qty,:price,:rl,NOW(),NOW(),NOW())
                     ON DUPLICATE KEY UPDATE
                       quantity_available=VALUES(quantity_available),
                       unit_price=VALUES(unit_price),
                       last_restocked=NOW()'
                );
                $stmt->execute([
                    ':iid'   => $itemId,
                    ':sz'    => $row['size'],
                    ':qty'   => (int)$row['quantity'],
                    ':price' => (float)($row['unit_price'] ?? 0),
                    ':rl'    => (int)($row['reorder_level'] ?? 5),
                ]);
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    private function importTermDates(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $ayId  = $this->resolveAcademicYearId((int)$row['academic_year']);
                $termId = $this->resolveTermId((int)$row['term_number']);
                $this->resolveAcademicYearTermId(
                    $ayId, $termId,
                    $row['opening_date'] ?? $row['start_date'],
                    $row['closing_date'] ?? $row['end_date'],
                    $row['midterm_break_start'] ?? null,
                    $row['midterm_break_end'] ?? null
                );
                $inserted++;
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
        return ['inserted' => $inserted, 'errors' => []];
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function splitName(string $full): array
    {
        $parts = preg_split('/\s+/', trim($full), 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function resolveStudentId(string $admissionNo): ?int
    {
        if (!$admissionNo) return null;
        $stmt = $this->db->prepare('SELECT id FROM students WHERE admission_no = :a LIMIT 1');
        $stmt->execute([':a' => trim($admissionNo)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    private function resolveClassId(string $className, string $stream = ''): ?int
    {
        if (!$className) return null;
        $stmt = $this->db->prepare('SELECT id FROM classes WHERE name = :cn LIMIT 1');
        $stmt->execute([':cn' => trim($className)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    private function resolveLevelForClass(string $className): int
    {
        $n = strtoupper($className);
        if (strpos($n, 'PLAYGROUP') !== false || strpos($n, 'PP') !== false) return 1;
        if (preg_match('/\b(7|8|9|JSS)\b/', $n) || strpos($n, 'JUNIOR') !== false) return 4;
        if (preg_match('/\b(4|5|6)\b/', $n)) return 3;
        return 2;
    }

    private function resolveStudentTypeId(string $code): int
    {
        $c = strtoupper(trim($code) ?: 'DAY');
        $stmt = $this->db->prepare('SELECT id FROM student_types WHERE UPPER(code) = :c OR UPPER(name) = :c LIMIT 1');
        $stmt->execute([':c' => $c]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : 1;
    }

    private function resolveAcademicYearId(int $year): int
    {
        $stmt = $this->db->prepare('SELECT id FROM academic_years WHERE year_code = :y LIMIT 1');
        $stmt->execute([':y' => (string)$year]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->db->prepare(
            'INSERT INTO academic_years (year_code, year_name, start_date, end_date, status)
             VALUES (:y,:n,:s,:e,"registration")'
        );
        $stmt->execute([
            ':y'  => (string)$year,
            ':n'  => $year . ' Academic Year',
            ':s'  => $year . '-01-01',
            ':e'  => $year . '-12-31',
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveTermId(int $termNo): int
    {
        $code = 'T' . $termNo;
        $stmt = $this->db->prepare('SELECT id FROM terms WHERE code = :c LIMIT 1');
        $stmt->execute([':c' => $code]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->db->prepare('INSERT INTO terms (name, code) VALUES (:n,:c)');
        $stmt->execute([':n' => 'Term ' . $termNo, ':c' => $code]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveAcademicYearTermId(
        int $ayId, int $termId,
        ?string $opening = null, ?string $closing = null,
        ?string $halfStart = null, ?string $halfEnd = null
    ): int {
        $stmt = $this->db->prepare('SELECT id FROM academic_year_terms WHERE academic_year_id = :a AND term_id = :t LIMIT 1');
        $stmt->execute([':a' => $ayId, ':t' => $termId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            $up = $this->db->prepare(
                'UPDATE academic_year_terms
                 SET opening_date = COALESCE(:o, opening_date),
                     closing_date = COALESCE(:c, closing_date),
                     half_term_start = COALESCE(:hs, half_term_start),
                     half_term_end = COALESCE(:he, half_term_end)
                 WHERE id = :id'
            );
            $up->execute([':o' => $opening, ':c' => $closing, ':hs' => $halfStart, ':he' => $halfEnd, ':id' => $id]);
            return (int)$id;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO academic_year_terms (academic_year_id, term_id, opening_date, closing_date, half_term_start, half_term_end, status)
             VALUES (:a,:t,:o,:c,:hs,:he,"upcoming")'
        );
        $stmt->execute([':a' => $ayId, ':t' => $termId, ':o' => $opening, ':c' => $closing, ':hs' => $halfStart, ':he' => $halfEnd]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveAcademicYearClassId(int $ayId, int $classId): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM academic_year_classes WHERE academic_year_id = :a AND class_id = :c LIMIT 1');
        $stmt->execute([':a' => $ayId, ':c' => $classId]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->db->prepare(
            'INSERT INTO academic_year_classes (academic_year_id, class_id, status) VALUES (:a,:c,"planning")'
        );
        $stmt->execute([':a' => $ayId, ':c' => $classId]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveLearningAreaId(string $name): int
    {
        $stmt = $this->db->prepare('SELECT id FROM learning_areas WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => trim($name)]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', trim($name)), 0, 10));
        $stmt = $this->db->prepare('INSERT INTO learning_areas (name, code, level_band, status) VALUES (:n,:c,"lower_primary","active")');
        $stmt->execute([':n' => trim($name), ':c' => $code ?: ('LA' . mt_rand(100, 999))]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveAssessmentId(
        int $aycsId, int $aytId, int $laId, string $title, float $maxMarks, string $date
    ): int {
        $stmt = $this->db->prepare(
            'SELECT id FROM assessments
             WHERE academic_year_class_stream_id = :c AND title = :t AND assessment_date = :d LIMIT 1'
        );
        $stmt->execute([':c' => $aycsId, ':t' => $title, ':d' => $date]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->db->prepare(
            'INSERT INTO assessments (academic_year_class_stream_id, academic_year_term_id, learning_area_id, title, max_marks, assessment_date, assigned_by, status)
             VALUES (:c,:t,:la,:title,:mm,:d,1,"pending_submission")'
        );
        $stmt->execute([':c' => $aycsId, ':t' => $aytId, ':la' => $laId, ':title' => $title, ':mm' => $maxMarks, ':d' => $date]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveFeeCatalog(string $name, float $amount): int
    {
        $stmt = $this->db->prepare('SELECT id FROM fee_catalog WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => trim($name)]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', trim($name)), 0, 15));
        $stmt = $this->db->prepare('INSERT INTO fee_catalog (code, name, default_amount, status) VALUES (:c,:n,:a,"active")');
        $stmt->execute([':c' => $code ?: ('FEE' . mt_rand(100, 999)), ':n' => trim($name), ':a' => $amount]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveExpenseCategoryId(string $name): ?int
    {
        if (!$name) return null;
        $stmt = $this->db->prepare('SELECT id FROM expense_categories WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => trim($name)]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    private function resolveBudgetId(string $department, int $year, ?int $term): int
    {
        $name = trim($department . ' - ' . $year . ($term ? ' Term ' . $term : ''));
        $stmt = $this->db->prepare('SELECT id FROM budgets WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => $name]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $stmt = $this->db->prepare(
            'INSERT INTO budgets (name, academic_year, term, total_amount, status, created_by, created_at, updated_at)
             VALUES (:n,:y,:t,0,"draft",1,NOW(),NOW())'
        );
        $stmt->execute([':n' => $name, ':y' => $year, ':t' => $term]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveInventoryCategoryId(string $name, bool $create = true): ?int
    {
        if (!$name) return null;
        $stmt = $this->db->prepare('SELECT id FROM inventory_categories WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => trim($name)]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        if (!$create) return null;
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', trim($name)), 0, 10));
        $stmt = $this->db->prepare('INSERT INTO inventory_categories (name, code, status) VALUES (:n,:c,"active")');
        $stmt->execute([':n' => trim($name), ':c' => $code ?: ('CAT' . mt_rand(100, 999))]);
        return (int)$this->db->lastInsertId();
    }

    private function resolveInventoryItemId(
        string $name, ?int $categoryId = null, float $unitCost = 0,
        float $quantity = 0, string $unit = 'pcs', int $reorderLevel = 10
    ): ?int {
        if (!$name) return null;
        $stmt = $this->db->prepare('SELECT id FROM inventory_items WHERE name = :n LIMIT 1');
        $stmt->execute([':n' => trim($name)]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', trim($name)), 0, 20));
        $stmt = $this->db->prepare(
            'INSERT INTO inventory_items
             (category_id, name, code, unit, current_quantity, unit_cost, reorder_level, status, created_at, updated_at)
             VALUES (:cat,:n,:c,:u,:qty,:cost,:rl,"active",NOW(),NOW())'
        );
        $stmt->execute([
            ':cat'  => $categoryId,
            ':n'    => trim($name),
            ':c'    => $code ?: ('ITM' . mt_rand(1000, 9999)),
            ':u'    => $unit,
            ':qty'  => $quantity,
            ':cost' => $unitCost,
            ':rl'   => $reorderLevel,
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function upsertParent(int $studentId, array $row): void
    {
        $name  = trim($row['parent_name'] ?? $row['guardian_name'] ?? '');
        $phone = $row['parent_phone'] ?? $row['phone'] ?? null;
        $email = $row['parent_email'] ?? $row['email'] ?? null;
        $rel   = $row['relationship'] ?? $row['parent_relationship'] ?? 'parent';
        $primary = isset($row['is_primary']) ? (int)$row['is_primary'] : 1;

        [$first, $last] = $this->splitName($name ?: 'Guardian');

        // Find existing parent by phone/email first
        $personId = null;
        if ($phone || $email) {
            $stmt = $this->db->prepare('SELECT id FROM persons WHERE (phone = :p OR email = :e) LIMIT 1');
            $stmt->execute([':p' => $phone, ':e' => $email]);
            $personId = $stmt->fetchColumn();
        }
        if (!$personId) {
            $stmt = $this->db->prepare(
                'INSERT INTO persons (first_name, last_name, email, phone) VALUES (:fn,:ln,:e,:p)'
            );
            $stmt->execute([':fn' => $first, ':ln' => $last, ':e' => $email, ':p' => $phone]);
            $personId = (int)$this->db->lastInsertId();
        }

        $stmt = $this->db->prepare(
            'INSERT INTO parents (person_id, occupation, address, status, created_at, updated_at)
             VALUES (:pid,:occ,:addr,"active",NOW(),NOW())
             ON DUPLICATE KEY UPDATE occupation=VALUES(occupation), updated_at=NOW()'
        );
        $stmt->execute([':pid' => (int)$personId, ':occ' => $row['occupation'] ?? null, ':addr' => $row['address'] ?? null]);
        $parentId = (int)$this->db->lastInsertId();
        if (!$parentId) {
            $stmt = $this->db->prepare('SELECT id FROM parents WHERE person_id = :pid LIMIT 1');
            $stmt->execute([':pid' => $personId]);
            $parentId = (int)$stmt->fetchColumn();
        }
        if (!$parentId) return;

        $stmt = $this->db->prepare(
            'INSERT INTO student_parents (student_id, parent_id, relationship, is_primary_contact, is_emergency_contact)
             VALUES (:sid,:pid,:rel,:ip,:ie)
             ON DUPLICATE KEY UPDATE relationship=VALUES(relationship), is_primary_contact=VALUES(is_primary_contact)'
        );
        $stmt->execute([':sid' => $studentId, ':pid' => $parentId, ':rel' => $rel, ':ip' => $primary, ':ie' => $primary]);
    }

    private function computeCBCGrade(float $pct): string
    {
        if ($pct >= 75) return 'EE';
        if ($pct >= 60) return 'ME';
        if ($pct >= 40) return 'AE';
        return 'BE';
    }

    private function isValidDate(string $date): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            [$y, $m, $d] = explode('-', $date);
            return checkdate((int)$m, (int)$d, (int)$y);
        }
        return false;
    }

    private function normalise(array $row): array
    {
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);
    }

    private function validateType(string $type): void
    {
        if (!isset(self::TYPES[$type])) {
            throw new Exception("Unknown import type: $type");
        }
    }

    private function logImport(
        string $type, string $filename,
        int $total, int $success, int $errorCount, int $skipped,
        int $importedBy, array $errors, string $status
    ): void {
        try {
            $summary = [
                'import_type' => $type,
                'errors'      => array_slice($errors, 0, 200),
            ];
            $stmt = $this->db->prepare(
                'INSERT INTO staff_import_batches
                   (source_filename, stored_path, total_rows, valid_rows, invalid_rows,
                    imported_rows, status, validation_summary, failure_message, imported_by,
                    started_at, completed_at, created_at, updated_at)
                 VALUES (:fn, "", :tot, :suc, :err, :imp, :st, :sum, NULL, :by, NULL, NOW(), NOW(), NOW())'
            );
            $stmt->execute([
                ':fn'  => $filename,
                ':tot' => $total,
                ':suc' => $success,
                ':err' => $errorCount,
                ':imp' => $success,
                ':st'  => ($status === 'partial') ? 'completed' : $status,
                ':sum' => json_encode($summary, JSON_UNESCAPED_UNICODE),
                ':by'  => $importedBy,
            ]);
        } catch (Exception $e) {
            // Non-fatal — log silently
        }
    }
}
