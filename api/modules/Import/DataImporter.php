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
                $importErrors[] = ['row' => 0, 'field' => 'system', 'message' => $e->getMessage()];
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
            'SELECT l.*, CONCAT(u.first_name," ",u.last_name) AS imported_by_name
             FROM import_logs l
             LEFT JOIN users u ON u.id = l.imported_by
             ORDER BY l.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLog(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT l.*, CONCAT(u.first_name," ",u.last_name) AS imported_by_name
             FROM import_logs l
             LEFT JOIN users u ON u.id = l.imported_by
             WHERE l.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['error_details']) {
            $row['error_details'] = json_decode($row['error_details'], true);
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
                // Resolve class_id
                $classId = $this->resolveClassId($row['class_name'] ?? '', $row['stream_name'] ?? '');
                // Map gender
                $gender = strtolower($row['gender'] ?? 'male');
                $gender = in_array($gender, ['f','female']) ? 'female' : 'male';

                $stmt = $this->db->prepare(
                    'INSERT INTO students
                     (admission_no,first_name,middle_name,last_name,date_of_birth,gender,
                      class_id,student_type,admission_date,status,nationality,religion,
                      blood_group,special_needs,address,county,created_at)
                     VALUES
                     (:a,:fn,:mn,:ln,:dob,:g,:cid,:st,:ad,:s,:nat,:rel,:bg,:sn,:addr,:co,NOW())
                     ON DUPLICATE KEY UPDATE
                     first_name=VALUES(first_name),last_name=VALUES(last_name),
                     class_id=VALUES(class_id),status=VALUES(status),updated_at=NOW()'
                );
                $stmt->execute([
                    ':a'   => $row['admission_no'],
                    ':fn'  => $row['first_name'],
                    ':mn'  => $row['middle_name'] ?? null,
                    ':ln'  => $row['last_name'],
                    ':dob' => $row['date_of_birth'] ?? null,
                    ':g'   => $gender,
                    ':cid' => $classId,
                    ':st'  => $row['student_type'] ?? 'DAY',
                    ':ad'  => $row['admission_date'] ?? date('Y-m-d'),
                    ':s'   => $row['status'] ?? 'active',
                    ':nat' => $row['nationality'] ?? null,
                    ':rel' => $row['religion'] ?? null,
                    ':bg'  => $row['blood_group'] ?? null,
                    ':sn'  => $row['special_needs'] ?? null,
                    ':addr'=> $row['address'] ?? null,
                    ':co'  => $row['county'] ?? null,
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
                $stmt = $this->db->prepare(
                    'INSERT INTO payments
                     (student_id,amount,payment_method,reference_number,payment_date,
                      term,year,description,status,created_at)
                     VALUES
                     (:sid,:amt,:pm,:ref,:pd,:term,:yr,:desc,"completed",NOW())
                     ON DUPLICATE KEY UPDATE amount=VALUES(amount)'
                );
                $stmt->execute([
                    ':sid'  => $studentId,
                    ':amt'  => (float)$row['amount'],
                    ':pm'   => $row['payment_method'] ?? 'cash',
                    ':ref'  => $row['reference_number'] ?? null,
                    ':pd'   => $row['payment_date'],
                    ':term' => (int)$row['term'],
                    ':yr'   => (int)$row['year'],
                    ':desc' => $row['description'] ?? null,
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
                $stmt = $this->db->prepare(
                    'INSERT INTO staff
                     (staff_number,first_name,middle_name,last_name,email,phone,gender,
                      date_of_birth,national_id,designation,department,employment_type,
                      date_joined,tsc_number,qualification,created_at)
                     VALUES
                     (:sn,:fn,:mn,:ln,:em,:ph,:g,:dob,:nid,:des,:dep,:et,:dj,:tsc,:qual,NOW())
                     ON DUPLICATE KEY UPDATE
                     first_name=VALUES(first_name),last_name=VALUES(last_name),
                     email=VALUES(email),updated_at=NOW()'
                );
                $stmt->execute([
                    ':sn'  => $row['staff_number'],
                    ':fn'  => $row['first_name'],
                    ':mn'  => $row['middle_name'] ?? null,
                    ':ln'  => $row['last_name'],
                    ':em'  => $row['email'] ?? null,
                    ':ph'  => $row['phone'] ?? null,
                    ':g'   => strtolower($row['gender'] ?? 'male'),
                    ':dob' => $row['date_of_birth'] ?? null,
                    ':nid' => $row['national_id'] ?? null,
                    ':des' => $row['designation'] ?? 'Teacher',
                    ':dep' => $row['department'] ?? null,
                    ':et'  => $row['employment_type'] ?? 'permanent',
                    ':dj'  => $row['date_joined'] ?? date('Y-m-d'),
                    ':tsc' => $row['tsc_number'] ?? null,
                    ':qual'=> $row['qualification'] ?? null,
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
                $classId = $this->resolveClassId($row['class_name'] ?? '');
                $stmt = $this->db->prepare(
                    'INSERT INTO fee_structure
                     (class_id,class_name,fee_type,amount,term,year,description,is_mandatory,created_at)
                     VALUES (:cid,:cn,:ft,:amt,:term,:yr,:desc,:mand,NOW())
                     ON DUPLICATE KEY UPDATE amount=VALUES(amount)'
                );
                $stmt->execute([
                    ':cid'  => $classId,
                    ':cn'   => $row['class_name'],
                    ':ft'   => $row['fee_type'],
                    ':amt'  => (float)$row['amount'],
                    ':term' => (int)$row['term'],
                    ':yr'   => (int)$row['year'],
                    ':desc' => $row['description'] ?? null,
                    ':mand' => (int)($row['is_mandatory'] ?? 1),
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
                $stmt = $this->db->prepare(
                    'INSERT INTO expenses
                     (expense_date,category,description,amount,payment_method,
                      receipt_number,vendor_name,approved_by,notes,status,created_at)
                     VALUES (:d,:cat,:desc,:amt,:pm,:rcpt,:vend,:appr,:notes,"approved",NOW())'
                );
                $stmt->execute([
                    ':d'    => $row['date'],
                    ':cat'  => $row['category'],
                    ':desc' => $row['description'],
                    ':amt'  => (float)$row['amount'],
                    ':pm'   => $row['payment_method'] ?? 'cash',
                    ':rcpt' => $row['receipt_number'] ?? null,
                    ':vend' => $row['vendor_name'] ?? null,
                    ':appr' => $row['approved_by'] ?? null,
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

    private function importBudget(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $stmt = $this->db->prepare(
                    'INSERT INTO budget_lines
                     (year,term,department,category,description,budgeted_amount,notes,created_at)
                     VALUES (:yr,:term,:dep,:cat,:desc,:amt,:notes,NOW())
                     ON DUPLICATE KEY UPDATE budgeted_amount=VALUES(budgeted_amount)'
                );
                $stmt->execute([
                    ':yr'  => (int)$row['year'],
                    ':term'=> (int)$row['term'],
                    ':dep' => $row['department'],
                    ':cat' => $row['category'],
                    ':desc'=> $row['description'] ?? null,
                    ':amt' => (float)$row['budgeted_amount'],
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
                $pct   = (float)$row['max_score'] > 0 ? round((float)$row['score'] / (float)$row['max_score'] * 100, 1) : 0;
                $grade = $this->computeCBCGrade($pct);
                $stmt  = $this->db->prepare(
                    'INSERT INTO student_results
                     (student_id,subject,exam_type,term,year,score,max_score,percentage,cbc_grade,remarks,created_at)
                     VALUES (:sid,:subj,:et,:term,:yr,:sc,:max,:pct,:grade,:rem,NOW())
                     ON DUPLICATE KEY UPDATE score=VALUES(score),percentage=VALUES(percentage),cbc_grade=VALUES(cbc_grade)'
                );
                $stmt->execute([
                    ':sid'  => $studentId,
                    ':subj' => $row['subject'],
                    ':et'   => $row['exam_type'] ?? 'End of Term',
                    ':term' => (int)$row['term'],
                    ':yr'   => (int)$row['year'],
                    ':sc'   => (float)$row['score'],
                    ':max'  => (float)$row['max_score'],
                    ':pct'  => $pct,
                    ':grade'=> $row['grade'] ?? $grade,
                    ':rem'  => $row['remarks'] ?? null,
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

    private function importFormativeScores(array $rows): array
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
                $stmt = $this->db->prepare(
                    'INSERT INTO formative_assessment_scores
                     (student_id,subject,assessment_type,term,year,score,max_score,assessment_date,remarks,created_at)
                     VALUES (:sid,:subj,:at,:term,:yr,:sc,:max,:d,:rem,NOW())
                     ON DUPLICATE KEY UPDATE score=VALUES(score)'
                );
                $stmt->execute([
                    ':sid'  => $studentId,
                    ':subj' => $row['subject'],
                    ':at'   => $row['assessment_type'],
                    ':term' => (int)$row['term'],
                    ':yr'   => (int)$row['year'],
                    ':sc'   => (float)$row['score'],
                    ':max'  => (float)($row['max_score'] ?? 20),
                    ':d'    => $row['date'] ?? date('Y-m-d'),
                    ':rem'  => $row['remarks'] ?? null,
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
                $stmt = $this->db->prepare(
                    'INSERT INTO attendance
                     (student_id,attendance_date,status,session,subject,reason,notes,created_at)
                     VALUES (:sid,:d,:st,:sess,:subj,:reason,:notes,NOW())
                     ON DUPLICATE KEY UPDATE status=VALUES(status)'
                );
                $stmt->execute([
                    ':sid'   => $studentId,
                    ':d'     => $row['date'],
                    ':st'    => strtolower($row['status']),
                    ':sess'  => $row['session'] ?? 'morning',
                    ':subj'  => $row['subject'] ?? null,
                    ':reason'=> $row['reason'] ?? null,
                    ':notes' => $row['notes'] ?? null,
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
                $stmt = $this->db->prepare(
                    'INSERT INTO classes
                     (class_name,level,stream_name,capacity,room,academic_year,created_at)
                     VALUES (:cn,:lv,:sn,:cap,:room,:yr,NOW())
                     ON DUPLICATE KEY UPDATE capacity=VALUES(capacity),room=VALUES(room)'
                );
                $stmt->execute([
                    ':cn'  => $row['class_name'],
                    ':lv'  => $row['level'] ?? null,
                    ':sn'  => $row['stream_name'] ?? null,
                    ':cap' => (int)($row['capacity'] ?? 40),
                    ':room'=> $row['room'] ?? null,
                    ':yr'  => (int)($row['academic_year'] ?? date('Y')),
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
                $stmt = $this->db->prepare(
                    'INSERT INTO subjects
                     (subject_name,subject_code,learning_area,class_name,stream_name,periods_per_week,created_at)
                     VALUES (:sn,:sc,:la,:cn,:str,:ppw,NOW())
                     ON DUPLICATE KEY UPDATE learning_area=VALUES(learning_area)'
                );
                $stmt->execute([
                    ':sn'  => $row['subject_name'],
                    ':sc'  => $row['subject_code'] ?? null,
                    ':la'  => $row['learning_area'] ?? $row['subject_name'],
                    ':cn'  => $row['class_name'],
                    ':str' => $row['stream_name'] ?? null,
                    ':ppw' => (int)($row['periods_per_week'] ?? 5),
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

    private function importInventory(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $stmt = $this->db->prepare(
                    'INSERT INTO inventory_items
                     (item_code,item_name,category,quantity,unit,unit_price,location,reorder_level,supplier,notes,created_at)
                     VALUES (:code,:nm,:cat,:qty,:unit,:price,:loc,:rl,:supp,:notes,NOW())
                     ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),unit_price=VALUES(unit_price)'
                );
                $stmt->execute([
                    ':code' => $row['item_code'] ?? null,
                    ':nm'   => $row['item_name'],
                    ':cat'  => $row['category'],
                    ':qty'  => (float)$row['quantity'],
                    ':unit' => $row['unit'],
                    ':price'=> (float)($row['unit_price'] ?? 0),
                    ':loc'  => $row['location'] ?? null,
                    ':rl'   => (int)($row['reorder_level'] ?? 10),
                    ':supp' => $row['supplier'] ?? null,
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

    private function importFoodStock(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $stmt = $this->db->prepare(
                    'INSERT INTO food_stock
                     (item_name,category,quantity,unit,unit_price,supplier,purchase_date,expiry_date,storage_location,notes,created_at)
                     VALUES (:nm,:cat,:qty,:unit,:price,:supp,:pd,:exp,:loc,:notes,NOW())'
                );
                $stmt->execute([
                    ':nm'   => $row['item_name'],
                    ':cat'  => $row['category'] ?? 'General',
                    ':qty'  => (float)$row['quantity'],
                    ':unit' => $row['unit'],
                    ':price'=> (float)($row['unit_price'] ?? 0),
                    ':supp' => $row['supplier'] ?? null,
                    ':pd'   => $row['purchase_date'],
                    ':exp'  => $row['expiry_date'] ?? null,
                    ':loc'  => $row['storage_location'] ?? null,
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

    private function importUniformStock(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $stmt = $this->db->prepare(
                    'INSERT INTO uniform_items
                     (item_name,category,size,gender,quantity,unit_price,reorder_level,notes,created_at)
                     VALUES (:nm,:cat,:sz,:gen,:qty,:price,:rl,:notes,NOW())
                     ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)'
                );
                $stmt->execute([
                    ':nm'   => $row['item_name'],
                    ':cat'  => $row['category'] ?? 'General',
                    ':sz'   => $row['size'],
                    ':gen'  => $row['gender'] ?? 'unisex',
                    ':qty'  => (int)$row['quantity'],
                    ':price'=> (float)($row['unit_price'] ?? 0),
                    ':rl'   => (int)($row['reorder_level'] ?? 5),
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

    private function importTermDates(array $rows): array
    {
        $inserted = 0;
        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $row = $this->normalise($row);
                $stmt = $this->db->prepare(
                    'INSERT INTO academic_terms
                     (academic_year,term_number,term_name,start_date,end_date,
                      opening_date,closing_date,midterm_break_start,midterm_break_end,notes,created_at)
                     VALUES (:yr,:tn,:nm,:sd,:ed,:od,:cd,:mbs,:mbe,:notes,NOW())
                     ON DUPLICATE KEY UPDATE start_date=VALUES(start_date),end_date=VALUES(end_date)'
                );
                $stmt->execute([
                    ':yr'  => (int)$row['academic_year'],
                    ':tn'  => (int)$row['term_number'],
                    ':nm'  => $row['term_name'] ?? 'Term '.$row['term_number'],
                    ':sd'  => $row['start_date'],
                    ':ed'  => $row['end_date'],
                    ':od'  => $row['opening_date'] ?? $row['start_date'],
                    ':cd'  => $row['closing_date'] ?? $row['end_date'],
                    ':mbs' => $row['midterm_break_start'] ?? null,
                    ':mbe' => $row['midterm_break_end'] ?? null,
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

    // ── Helpers ─────────────────────────────────────────────────────────────

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
        $sql = 'SELECT id FROM classes WHERE class_name = :cn';
        $params = [':cn' => trim($className)];
        if ($stream) {
            $sql .= ' AND (stream_name = :sn OR stream_name IS NULL)';
            $params[':sn'] = trim($stream);
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    private function upsertParent(int $studentId, array $row): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO student_parents
             (student_id,parent_name,relationship,phone,email,occupation,is_primary,created_at)
             VALUES (:sid,:pn,:rel,:ph,:em,:occ,:ip,NOW())
             ON DUPLICATE KEY UPDATE phone=VALUES(phone),email=VALUES(email)'
        );
        $stmt->execute([
            ':sid' => $studentId,
            ':pn'  => $row['parent_name'] ?? $row['guardian_name'] ?? '',
            ':rel' => $row['relationship'] ?? $row['parent_relationship'] ?? 'parent',
            ':ph'  => $row['parent_phone'] ?? $row['phone'] ?? null,
            ':em'  => $row['parent_email'] ?? $row['email'] ?? null,
            ':occ' => $row['occupation'] ?? null,
            ':ip'  => isset($row['is_primary']) ? (int)$row['is_primary'] : 1,
        ]);
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
            $stmt = $this->db->prepare(
                'INSERT INTO import_logs
                 (import_type,import_category,original_filename,total_rows,success_rows,error_rows,
                  skipped_rows,status,error_details,imported_by,completed_at)
                 VALUES (:t,:cat,:fn,:tot,:suc,:err,:skip,:st,:ed,:by,NOW())'
            );
            $stmt->execute([
                ':t'   => $type,
                ':cat' => self::TYPES[$type]['category'],
                ':fn'  => $filename,
                ':tot' => $total,
                ':suc' => $success,
                ':err' => $errorCount,
                ':skip'=> $skipped,
                ':st'  => $status,
                ':ed'  => json_encode(array_slice($errors, 0, 200), JSON_UNESCAPED_UNICODE),
                ':by'  => $importedBy,
            ]);
        } catch (Exception $e) {
            // Non-fatal — log silently
        }
    }
}
