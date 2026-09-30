<?php
declare(strict_types=1);

namespace App\API\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use DateTimeImmutable;
use PDO;
use RuntimeException;
use Throwable;

/** Existing-staff migration only. Recruitment belongs to Phase 3. */
final class StaffMigrationService
{
    private const REQUIRED = [
        'first_name','last_name','email','role_name','staff_type','department_name',
        'staff_category','contract_type'
    ];
    private const OPTIONAL = [
        'staff_no','middle_name','phone','gender','date_of_birth','employment_date','supervisor_staff_no',
        'address','marital_status','tsc_no','bank_name','bank_account','mpesa_phone',
        'kra_pin','nssf_no','nhif_no','communication_email','communication_phone',
        'emergency_contact_name','emergency_contact_phone','work_start_time','work_end_time',
        'late_threshold_minutes','learning_areas','leadership_position_name'
    ];
    public function __construct(private PDO $db) {}

    public function templateHeaders(): array { return array_merge(self::REQUIRED, self::OPTIONAL); }

    public function templateCsv(): string
    {
        return "\xEF\xBB\xBF"
            . implode(',', array_map([$this, 'csvCell'], $this->templateHeaders()))
            . "\r\n";
    }

    public function writeTemplateXlsx(string $path): string
    {
        if (!$this->spreadsheetZipAvailable()) {
            $this->runSpreadsheetCli('writeTemplateXlsx', $path);
            return $path;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->templateHeaders();

        $sheet->fromArray($headers, null, 'A1');
        $sheet->setTitle('Staff Import');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $sheet->getHighestColumn() . '1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        $requiredLastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::REQUIRED));
        $sheet->getStyle('A1:' . $requiredLastColumn . '1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFD966');

        $this->applyTemplateDropdowns($spreadsheet, $sheet, $headers);

        for ($column = 1, $count = count($headers); $column <= $count; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndexByName('Staff Import');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function writeTemplateOds(string $path): string
    {
        if (!$this->spreadsheetZipAvailable()) {
            $this->runSpreadsheetCli('writeTemplateOds', $path);
            return $path;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff Import');
        $sheet->fromArray($this->templateHeaders(), null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $sheet->getHighestColumn() . '1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        $requiredLastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::REQUIRED));
        $sheet->getStyle('A1:' . $requiredLastColumn . '1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFD966');
        $this->applyTemplateDropdowns($spreadsheet, $sheet, $this->templateHeaders());
        for ($column = 1, $count = count(self::REQUIRED); $column <= $count; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
        (new Ods($spreadsheet))->save($path);
        $this->addOdsDropdowns($path, $this->templateHeaders(), $this->templateDropdownLists());
        return $path;
    }

    public function spreadsheetToCsv(string $path): string
    {
        if (!$this->spreadsheetZipAvailable()) {
            $csvPath = tempnam(sys_get_temp_dir(), 'staff-import-');
            if ($csvPath === false) {
                throw new RuntimeException('Unable to prepare spreadsheet conversion.');
            }
            try {
                $this->runSpreadsheetCli('spreadsheetToCsv', $path, $csvPath);
                $csv = file_get_contents($csvPath);
                if ($csv === false || trim($csv) === '') {
                    throw new RuntimeException('Uploaded spreadsheet is empty.');
                }
                return $csv;
            } finally {
                @unlink($csvPath);
            }
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheetByName('Staff Import');
        if ($sheet === null) {
            throw new RuntimeException('The Excel workbook must contain a "Staff Import" worksheet.');
        }
        $rows = $sheet->toArray(null, true, true, false);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Unable to prepare spreadsheet data.');
        }

        foreach ($rows as $row) {
            if (count(array_filter($row, fn($value) => trim((string)$value) !== '')) === 0) {
                continue;
            }
            // The $escape argument is required from PHP 8.4; omitting it is a
            // deprecation that became an error and broke every xlsx/ods import.
            fputcsv($handle, array_map(fn($value) => trim((string)$value), $row), ',', '"', '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false || trim($csv) === '') {
            throw new RuntimeException('Uploaded spreadsheet is empty.');
        }

        return $csv;
    }

    private function spreadsheetZipAvailable(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /** Use the project Composer autoloader under a CLI PHP that has ext-zip. */
    private function runSpreadsheetCli(string $operation, string $inputPath, ?string $outputPath = null): void
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Spreadsheet support is unavailable in this PHP runtime.');
        }

        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $code = <<<'PHP'
require $argv[1];
if (!class_exists(\ZipArchive::class)) { fwrite(STDERR, 'ZIP extension unavailable'); exit(2); }
$db = \App\Database\Database::getInstance()->getConnection();
$service = new \App\API\Services\StaffMigrationService($db);
switch ($argv[2]) {
    case 'spreadsheetToCsv':
        $csv = $service->spreadsheetToCsv($argv[3]);
        if (file_put_contents($argv[4], $csv, LOCK_EX) === false) { exit(3); }
        break;
    case 'writeTemplateXlsx': $service->writeTemplateXlsx($argv[3]); break;
    case 'writeTemplateOds': $service->writeTemplateOds($argv[3]); break;
    default: exit(4);
}
PHP;

        $candidates = array_values(array_unique(array_filter([
            PHP_BINDIR . '/php',
            '/usr/bin/php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
            '/usr/bin/php',
        ], static fn(string $binary): bool => is_file($binary) && is_executable($binary))));

        foreach ($candidates as $binary) {
            $probe = $this->runCliCommand([$binary, '-r', 'exit(class_exists("ZipArchive") ? 0 : 1);']);
            if ($probe['status'] !== 0) {
                continue;
            }
            $arguments = [$binary, '-r', $code, $autoload, $operation, $inputPath];
            if ($outputPath !== null) {
                $arguments[] = $outputPath;
            }
            $result = $this->runCliCommand($arguments);
            if ($result['status'] !== 0) {
                throw new RuntimeException('Unable to process the spreadsheet with the Composer PHP runtime.');
            }
            return;
        }

        throw new RuntimeException('Spreadsheet support is unavailable: enable the PHP ZIP extension for the web runtime.');
    }

    /** @return array{status:int,stdout:string,stderr:string} */
    private function runCliCommand(array $command): array
    {
        $pipes = [];
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return ['status' => 1, 'stdout' => '', 'stderr' => ''];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return ['status' => proc_close($process), 'stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }

    public function referenceData(): array
    {
        return [
            'departments' => $this->rows("SELECT id,code,name FROM departments WHERE status='active' ORDER BY name"),
            'roles' => $this->assignableSchoolRoles(),
            'staff_types' => $this->rows("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name"),
            'staff_categories' => $this->rows("SELECT sc.id,sc.category_name AS name,st.name AS staff_type FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE sc.is_active=1 ORDER BY st.name,sc.category_name"),
            'learning_areas' => $this->rows("SELECT id,name,code FROM learning_areas WHERE status='active' ORDER BY name"),
            'leadership_positions' => $this->rows("SELECT lp.id,lp.name FROM leadership_positions lp JOIN leadership_categories lc ON lc.id=lp.leadership_category_id WHERE lp.is_active=1 AND lc.is_active=1 AND lc.holder_scope IN ('staff','any_person') ORDER BY lp.display_order,lp.name"),
            'positions' => StaffPositionCatalog::list($this->db),
            'contracts' => ['permanent','contract','temporary'],
            'genders' => ['male','female','other'],
            'marital_statuses' => ['single','married','divorced','widowed','separated','unknown'],
            'supervisors' => $this->rows("SELECT s.staff_no, CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) AS name FROM staff s JOIN persons p ON p.id=s.person_id WHERE s.status='active' ORDER BY p.first_name,p.last_name"),
        ];
    }

    /** Add workbook dropdowns sourced from the same active lookups used by import validation. */
    private function applyTemplateDropdowns(Spreadsheet $spreadsheet, \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers): void
    {
        $lists = $this->templateDropdownLists();
        $referenceSheet = $spreadsheet->createSheet();
        $referenceSheet->setTitle('Reference Values');
        $referenceSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
        $column = 1;
        foreach ($lists as $field => $values) {
            $values = array_values(array_unique(array_filter(array_map('strval', $values), static fn(string $v): bool => trim($v) !== '')));
            if (!$values) continue;
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $referenceSheet->setCellValue($letter . '1', $field);
            foreach ($values as $index => $value) $referenceSheet->setCellValue($letter . ($index + 2), $value);
            $range = "'Reference Values'!\$$letter\$2:\$$letter\$" . (count($values) + 1);
            $spreadsheet->addNamedRange(new \PhpOffice\PhpSpreadsheet\NamedRange(ucfirst($field) . 'Values', $referenceSheet, $range));
            $fieldIndex = array_search($field, $headers, true);
            if ($fieldIndex !== false) {
                $target = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($fieldIndex + 1);
                $validation = new \PhpOffice\PhpSpreadsheet\Cell\DataValidation();
                $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
                $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
                $validation->setAllowBlank(true);
                // PhpSpreadsheet writes the inverse OOXML showDropDown flag;
                // true here keeps the arrow visible in Excel and LibreOffice.
                $validation->setShowDropDown(true);
                $validation->setShowInputMessage(true);
                $validation->setShowErrorMessage(true);
                $validation->setFormula1('=' . ucfirst($field) . 'Values');
                $sheet->setDataValidation($target . '2:' . $target . '1000', $validation);
            }
            $column++;
        }
    }

    /** @return array<string,list<string>> */
    private function templateDropdownLists(): array
    {
        $references = $this->referenceData();
        return [
            'department_name' => array_column($references['departments'], 'name'),
            'role_name' => array_column($references['roles'], 'name'),
            'staff_type' => array_column($references['staff_types'], 'name'),
            'staff_category' => array_column($references['staff_categories'], 'name'),
            'position' => array_column($references['positions'], 'name'),
            'contract_type' => $references['contracts'],
            'gender' => $references['genders'],
            'marital_status' => $references['marital_statuses'],
            'leadership_position_name' => array_column($references['leadership_positions'], 'name'),
        ];
    }

    /** PhpSpreadsheet's ODS writer omits validations; add standard ODF list rules. */
    private function addOdsDropdowns(string $path, array $headers, array $lists): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('ODS dropdown generation requires the PHP ZIP extension.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Unable to prepare ODS dropdowns.');
        }
        try {
            $content = $zip->getFromName('content.xml');
            if ($content === false) {
                throw new RuntimeException('Unable to prepare ODS dropdowns.');
            }
            $document = new \DOMDocument();
            $document->preserveWhiteSpace = false;
            if (!$document->loadXML($content, LIBXML_NONET)) {
                throw new RuntimeException('Unable to prepare ODS dropdowns.');
            }
            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('office', 'urn:oasis:names:tc:opendocument:xmlns:office:1.0');
            $xpath->registerNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
            $spreadsheet = $xpath->query('/office:document-content/office:body/office:spreadsheet')->item(0);
            $staffTable = $xpath->query("/office:document-content/office:body/office:spreadsheet/table:table[@table:name='Staff Import']")->item(0);
            $referenceTable = $xpath->query("/office:document-content/office:body/office:spreadsheet/table:table[@table:name='Reference Values']")->item(0);
            if (!$spreadsheet instanceof \DOMElement || !$staffTable instanceof \DOMElement || !$referenceTable instanceof \DOMElement) {
                throw new RuntimeException('Unable to prepare ODS dropdowns.');
            }

            $tableNs = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
            $validations = $document->createElementNS($tableNs, 'table:content-validations');
            $firstTable = $xpath->query('./table:table', $spreadsheet)->item(0);
            $spreadsheet->insertBefore($validations, $firstTable);

            $row = $xpath->query('./table:table-row[2]', $staffTable)->item(0);
            if (!$row instanceof \DOMElement) {
                $row = $document->createElementNS($tableNs, 'table:table-row');
                $staffTable->appendChild($row);
            }
            // Expand the first blank data row so each dropdown column can carry
            // its validation name, then repeat the row through the template area.
            while ($row->hasChildNodes()) $row->removeChild($row->firstChild);
            $row->removeAttributeNS($tableNs, 'number-columns-repeated');
            $row->setAttributeNS($tableNs, 'table:number-rows-repeated', '999');
            $letterFor = static function (int $index): string {
                $letter = '';
                while ($index > 0) { $index--; $letter = chr(65 + ($index % 26)) . $letter; $index = intdiv($index, 26); }
                return $letter;
            };
            $validationByColumn = [];
            $listIndex = 0;
            foreach ($lists as $field => $values) {
                $values = array_values(array_unique(array_filter(array_map('strval', $values), static fn(string $v): bool => trim($v) !== '')));
                if (!$values) continue;
                $column = array_search($field, $headers, true);
                if ($column === false) continue;
                $name = 'Staff' . ucfirst($field) . 'List';
                $referenceColumn = $letterFor($listIndex + 1);
                $listIndex++;
                $validation = $document->createElementNS($tableNs, 'table:content-validation');
                $validation->setAttributeNS($tableNs, 'table:name', $name);
                $validation->setAttributeNS($tableNs, 'table:condition', 'of:cell-content-is-in-list([' . ucfirst($field) . 'Values])');
                $validation->setAttributeNS($tableNs, 'table:allow-empty-cell', 'true');
                $validation->setAttributeNS($tableNs, 'table:display-list', 'unsorted');
                $validations->appendChild($validation);
                $validationByColumn[$column] = $name;
            }
            for ($column = 0; $column < count($headers); $column++) {
                $cell = $document->createElementNS($tableNs, 'table:table-cell');
                if (isset($validationByColumn[$column])) {
                    $cell->setAttributeNS($tableNs, 'table:content-validation-name', $validationByColumn[$column]);
                }
                $row->appendChild($cell);
            }
            $referenceTable->setAttributeNS($tableNs, 'table:display', 'false');
            $zip->addFromString('content.xml', $document->saveXML());
        } finally {
            $zip->close();
        }
    }

    public function stage(string $originalName, string $storedPath, string $csv, int $actorId): array
    {
        [$headers, $rows] = $this->parseCsv($csv);
        $missing = array_values(array_diff(self::REQUIRED, $headers));
        $legacySalaryColumns = ['salary', 'basic_salary', 'create_payroll_profile'];
        $ignored = array_values(array_intersect($headers, $legacySalaryColumns));
        // Accept the former position column from already prepared workbooks;
        // new templates omit it because the primary role supplies a default.
        $unknown = array_values(array_diff($headers, $this->templateHeaders(), $legacySalaryColumns, ['position']));
        $duplicateInFile = $this->duplicatesInFile($rows);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("INSERT INTO staff_import_batches
                (source_filename,stored_path,total_rows,valid_rows,invalid_rows,status,imported_by,created_at,updated_at)
                VALUES (?,?,?,0,?,'validating',?,NOW(),NOW())");
            $stmt->execute([$originalName,$storedPath,count($rows),count($rows),$actorId]);
            $batchId = (int)$this->db->lastInsertId();

            $valid = 0;
            foreach ($rows as $i => $row) {
                $row = $this->applyDefaultPosition($row);
                $errors = $this->validateRow($row, $i + 2, $duplicateInFile);
                if (!$errors && !$missing && !$unknown) $valid++;
                $stmt = $this->db->prepare("INSERT INTO staff_import_rows
                    (batch_id,`row_number`,row_data,validation_errors,status,created_at,updated_at)
                    VALUES (?,?,?,?,?,NOW(),NOW())");
                $stmt->execute([
                    $batchId,$i+2,json_encode($row,JSON_UNESCAPED_UNICODE),
                    $errors ? json_encode($errors,JSON_UNESCAPED_UNICODE) : null,
                    (!$errors && !$missing && !$unknown) ? 'valid' : 'invalid'
                ]);
            }
            $invalid = count($rows)-$valid;
            $status = (!$missing && !$unknown && count($rows)>0 && $invalid===0) ? 'validated' : 'validation_failed';
            $this->db->prepare("UPDATE staff_import_batches SET valid_rows=?,invalid_rows=?,status=?,validation_summary=?,updated_at=NOW() WHERE id=?")
                ->execute([$valid,$invalid,$status,json_encode(['missing_columns'=>$missing,'unknown_columns'=>$unknown,'ignored_columns'=>$ignored]),$batchId]);
            $this->audit($actorId,'staff_import_validated','staff_import_batch',$batchId,['valid'=>$valid,'invalid'=>$invalid,'missing'=>$missing,'unknown'=>$unknown,'ignored'=>$ignored]);
            $this->db->commit();
            return $this->batchDetail($batchId);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function commit(int $batchId, int $actorId): array
    {
        $created = [];
        $this->db->beginTransaction();
        $processingStarted = false;
        try {
            // The row lock only protects this workflow when acquired inside an
            // open transaction. This prevents two commit requests from creating
            // duplicate staff accounts from one validated batch.
            $batch = $this->lockBatch($batchId);
            if (!$batch) throw new RuntimeException('Import batch not found.');
            if ($batch['status'] !== 'validated') throw new RuntimeException('Only a fully validated batch can be imported.');
            $rows = $this->batchRows($batchId, 'valid');
            if (!$rows || count($rows)!==(int)$batch['total_rows']) throw new RuntimeException('Validated row count no longer matches the batch. Revalidate the file.');

            $this->db->prepare("UPDATE staff_import_batches SET status='processing',started_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$batchId]);
            $processingStarted = true;
            foreach ($rows as $rowRecord) {
                $row = json_decode($rowRecord['row_data'], true, 512, JSON_THROW_ON_ERROR);
                $created[] = $this->createStaffGraph($row, $batchId, (int)$rowRecord['id'], $actorId);
            }
            $this->db->prepare("UPDATE staff_import_batches SET status='completed',imported_rows=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([count($created),$batchId]);
            $this->audit($actorId,'staff_import_completed','staff_import_batch',$batchId,['created_count'=>count($created)]);
            $this->db->commit();
            // Do not make a bulk-import HTTP request perform SMTP delivery.
            // A large batch could time out after its transaction already
            // committed, making callers believe the import failed. The
            // scheduled worker drains this durable queue and retries failures.
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($processingStarted) {
                // Rolling back the creation transaction also rolls back the
                // temporary `processing` status, so the stored state is
                // `validated` here. Persist the failure explicitly afterward.
                $this->db->prepare("UPDATE staff_import_batches SET status='failed',failure_message=?,updated_at=NOW() WHERE id=? AND status IN ('validated','processing')")
                    ->execute([mb_substr($e->getMessage(),0,1000),$batchId]);
                $this->audit($actorId,'staff_import_failed','staff_import_batch',$batchId,['error'=>$e->getMessage()],'failure');
            }
            throw $e;
        }
        return ['batch'=>$this->batchDetail($batchId),'created'=>$created];
    }

    public function rollback(int $batchId, int $actorId): array
    {
        $this->db->beginTransaction();
        try {
            $batch = $this->lockBatch($batchId);
            if (!$batch || $batch['status']!=='completed') throw new RuntimeException('Only a completed batch can be rolled back.');
            $rows = $this->batchRows($batchId, 'created');
            foreach ($rows as $r) {
                $this->db->prepare('SELECT id FROM staff WHERE id=? FOR UPDATE')->execute([(int)$r['staff_id']]);
                if ($this->hasOperationalDependencies((int)$r['staff_id'])) throw new RuntimeException('Rollback is blocked because one or more imported staff members already have operational records.');
            }
            foreach (array_reverse($rows) as $r) {
                $uid=(int)$r['user_id']; $sid=(int)$r['staff_id'];
                $pidStmt=$this->db->prepare("SELECT person_id FROM staff WHERE id=?");$pidStmt->execute([$sid]);$pid=(int)$pidStmt->fetchColumn();
                foreach (['staff_attendance_profiles','staff_payroll_profiles','staff_employment_profiles'] as $table) {
                    $this->db->prepare("DELETE FROM `$table` WHERE staff_id=?")->execute([$sid]);
                }
                $this->db->prepare("DELETE FROM staff_department_assignments WHERE staff_id=?")->execute([$sid]);
                $this->db->prepare("DELETE FROM user_invitations WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM outbound_messages WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM user_two_factor_methods WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM user_permissions WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM user_roles WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM staff WHERE id=?")->execute([$sid]);
                $this->db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
                if($pid){$this->db->prepare("DELETE FROM emergency_contacts WHERE person_id=?")->execute([$pid]);$this->db->prepare("DELETE FROM persons WHERE id=?")->execute([$pid]);}
                $this->db->prepare("UPDATE staff_import_rows SET status='rolled_back',updated_at=NOW() WHERE id=?")->execute([(int)$r['id']]);
            }
            $this->db->prepare("UPDATE staff_import_batches SET status='rolled_back',rolled_back_at=NOW(),rolled_back_by=?,updated_at=NOW() WHERE id=?")
                ->execute([$actorId,$batchId]);
            $this->audit($actorId,'staff_import_rolled_back','staff_import_batch',$batchId,['records'=>count($rows)]);
            $this->db->commit();
            return $this->batchDetail($batchId);
        } catch(Throwable $e){ if($this->db->inTransaction())$this->db->rollBack(); throw $e; }
    }

    public function resendInvitation(int $userId, int $actorId): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare("SELECT u.id,u.status,u.password_changed_at,u.profile_completed_at,u.force_password_change,p.email,u.username,p.first_name,p.last_name,s.id staff_id FROM users u JOIN persons p ON p.id=u.person_id JOIN staff s ON s.person_id=p.id WHERE u.id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$userId]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$user) throw new RuntimeException('Staff account not found.');
            if (($user['status'] ?? '') !== 'active') throw new RuntimeException('This staff account is inactive. An administrator must restore the account before an invitation can be resent.');
            if (empty($user['force_password_change']) || !empty($user['password_changed_at']) || !empty($user['profile_completed_at'])) {
                throw new RuntimeException('This staff account has completed password setup. Do not resend a setup link; the staff member should sign in or use password recovery.');
            }
            $token=$this->createInvitation((int)$user['id'],(int)$user['staff_id'],$user['email'],$actorId);
            $url=self::invitationSetupUrl($token);
            $messageId = $this->queueEmail((int)$user['id'],$user['email'],'staff_account_invitation','Your Kingsway account is ready',[
                'name'=>trim($user['first_name'].' '.$user['last_name']),'username'=>$user['username'],'activation_url'=>$url,'setup_url'=>$url
            ]);
            $this->audit($actorId,'staff_invitation_resent','user',(int)$user['id']);
            if ($ownsTransaction) $this->db->commit();
            return ['user_id'=>(int)$user['id'],'message_id'=>$messageId,'queued'=>true];
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** Resend the post-password email code when setup was accepted but not completed. */
    public function resendSetupOtp(int $userId, int $actorId): array
    {
        $stmt = $this->db->prepare("SELECT u.id,u.status,u.password_changed_at,u.profile_completed_at,p.email,
                (SELECT ui.status FROM user_invitations ui WHERE ui.user_id=u.id ORDER BY ui.id DESC LIMIT 1) AS invitation_status
            FROM users u JOIN persons p ON p.id=u.person_id
            JOIN staff s ON s.person_id=u.person_id
            WHERE u.id=? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) throw new RuntimeException('Staff account not found.');
        if (($user['status'] ?? '') !== 'active' || empty($user['password_changed_at'])
            || !empty($user['profile_completed_at']) || ($user['invitation_status'] ?? '') !== 'accepted') {
            throw new RuntimeException('A setup verification code can only be resent after password setup and before profile completion.');
        }
        if (!(new StaffProfileCompletionService($this->db))->isRequired($userId)) {
            throw new RuntimeException('This staff profile is already complete; no setup verification is needed.');
        }

        $tfa = new \App\API\Services\TwoFactorService($this->db);
        $code = $tfa->generateOTP($userId, 'email', 'setup');
        if (!$code || !(new \App\API\Services\OTPDeliveryService())->sendEmailOTP((string)$user['email'], $code, 'invitation_setup')) {
            $this->audit($actorId, 'staff_setup_otp_delivery_failed', 'user', $userId);
            throw new RuntimeException('The verification email could not be sent. Try again later.');
        }
        $this->audit($actorId, 'staff_setup_otp_resent', 'user', $userId);
        return ['user_id' => $userId, 'email_sent' => true];
    }

    public function cancelInvitation(int $userId, int $actorId): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT u.id,u.force_password_change,u.password_changed_at,u.profile_completed_at,s.id AS staff_id
                FROM users u JOIN staff s ON s.person_id=u.person_id WHERE u.id=? LIMIT 1 FOR UPDATE");
            $stmt->execute([$userId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$account) throw new RuntimeException('Staff account not found.');
            if (empty($account['force_password_change']) || !empty($account['password_changed_at']) || !empty($account['profile_completed_at'])) {
                throw new RuntimeException('This staff member has already started or completed account setup. The invitation cannot be cancelled.');
            }

            $revoke = $this->db->prepare("UPDATE user_invitations SET status='revoked',revoked_at=NOW(),updated_at=NOW() WHERE user_id=? AND status='pending'");
            $revoke->execute([$userId]);
            if ($revoke->rowCount() < 1) throw new RuntimeException('There is no pending invitation to cancel.');
            $this->db->prepare("UPDATE outbound_messages SET status='cancelled',last_error='Invitation cancelled by administrator',updated_at=NOW() WHERE user_id=? AND template_key='staff_account_invitation' AND status IN ('queued','retry')")
                ->execute([$userId]);
            $this->audit($actorId, 'staff_invitation_cancelled', 'user', $userId, ['staff_id' => (int)$account['staff_id']]);
            if ($ownsTransaction) $this->db->commit();
            return ['user_id' => $userId, 'cancelled' => true];
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function processEmailQueue(int $limit=20, ?int $messageId=null): array
    {
        // Recover deliveries whose worker died after claiming the message.
        $this->db->prepare("UPDATE outbound_messages SET status='retry',next_attempt_at=NOW(),updated_at=NOW() WHERE channel='email' AND template_key='staff_account_invitation' AND status='processing' AND updated_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE)")->execute();
        if ($messageId !== null && $messageId > 0) {
            $stmt=$this->db->prepare("SELECT * FROM outbound_messages WHERE id=? AND channel='email' AND template_key='staff_account_invitation' AND status IN ('queued','retry') AND next_attempt_at<=NOW() LIMIT 1");
            $stmt->execute([$messageId]);
        } else {
            $stmt=$this->db->prepare("SELECT * FROM outbound_messages WHERE channel='email' AND template_key='staff_account_invitation' AND status IN ('queued','retry') AND next_attempt_at<=NOW() ORDER BY id LIMIT ?");
            $stmt->bindValue(1,max(1,min(100,$limit)),PDO::PARAM_INT); $stmt->execute();
        }
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $service = new MessageService($this->db);
        $sent = 0;
        $failed = 0;

        foreach ($messages as $message) {
            $claim = $this->db->prepare("UPDATE outbound_messages SET status='processing', attempts=attempts+1, updated_at=NOW() WHERE id=? AND status IN ('queued','retry') AND next_attempt_at<=NOW()");
            $claim
                ->execute([(int)$message['id']]);
            if ($claim->rowCount() !== 1) continue;
            try {
                $payload = json_decode($message['payload_json'], true, 512, JSON_THROW_ON_ERROR);
                $setupUrl = (string)($payload['setup_url'] ?? $payload['activation_url'] ?? '');
                if (!preg_match('/(?:\?|&)token=([a-f0-9]{64})(?:&|$)/i', $setupUrl, $tokenMatch)) {
                    throw new RuntimeException('Invitation email has no valid setup token.');
                }
                $liveInvitation = $this->db->prepare("SELECT ui.id FROM user_invitations ui JOIN users u ON u.id=ui.user_id WHERE ui.user_id=? AND ui.token_hash=? AND ui.status='pending' AND ui.expires_at>NOW() AND u.status='active' AND u.force_password_change=1 AND u.password_changed_at IS NULL AND u.profile_completed_at IS NULL LIMIT 1");
                $liveInvitation->execute([(int)$message['user_id'], hash('sha256', $tokenMatch[1])]);
                if (!$liveInvitation->fetchColumn()) {
                    $this->db->prepare("UPDATE outbound_messages SET status='cancelled',last_error='Invitation token is no longer active',updated_at=NOW() WHERE id=? AND status='processing'")
                        ->execute([(int)$message['id']]);
                    continue;
                }
                // Every system email must use the same branded renderer. Sending the
                // invitation fragment directly left the CID logo unused, so Gmail
                // exposed it as an attachment and the message lost the formal layout.
                $body = $service->renderFormalEmail(
                    $message['subject'] ?: 'Your Kingsway account is ready',
                    $this->renderStaffInvitationEmail($payload),
                    '',
                    ''
                );
                $ok = $service->sendEmail([$message['recipient'] => $payload['name'] ?? $message['recipient']], $message['subject'] ?: 'Your Kingsway account is ready', $body);
                if (!$ok) {
                    throw new RuntimeException('SMTP delivery failed.');
                }
                $this->db->prepare("UPDATE outbound_messages SET status='sent', sent_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=?")
                    ->execute([(int)$message['id']]);
                $sent++;
            } catch (Throwable $e) {
                $this->db->prepare("UPDATE outbound_messages SET status='retry', last_error=?, next_attempt_at=DATE_ADD(NOW(), INTERVAL 15 MINUTE), updated_at=NOW() WHERE id=?")
                    ->execute([mb_substr($e->getMessage(),0,1000),(int)$message['id']]);
                $failed++;
            }
        }

        return ['processed'=>count($messages),'sent'=>$sent,'failed'=>$failed];
    }

    public function batches(int $limit=50): array
    {
        $stmt=$this->db->prepare("SELECT b.*,CONCAT(p.first_name,' ',p.last_name) imported_by_name FROM staff_import_batches b LEFT JOIN users u ON u.id=b.imported_by LEFT JOIN persons p ON p.id=u.person_id ORDER BY b.id DESC LIMIT ?");
        $stmt->bindValue(1,max(1,min(200,$limit)),PDO::PARAM_INT);$stmt->execute();return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function batchDetail(int $batchId): array
    {
        $stmt=$this->db->prepare("SELECT b.*,CONCAT(p.first_name,' ',p.last_name) imported_by_name FROM staff_import_batches b LEFT JOIN users u ON u.id=b.imported_by LEFT JOIN persons p ON p.id=u.person_id WHERE b.id=?");
        $stmt->execute([$batchId]);$batch=$stmt->fetch(PDO::FETCH_ASSOC);if(!$batch)throw new RuntimeException('Import batch not found.');
        $stmt=$this->db->prepare("SELECT r.id,r.`row_number`,r.row_data,r.validation_errors,r.status,r.staff_id,r.user_id,
            (SELECT u.force_password_change FROM users u WHERE u.id=r.user_id LIMIT 1) AS setup_required,
            CASE WHEN ui.id IS NULL THEN 'not_sent' WHEN ui.status='pending' AND ui.expires_at<=NOW() THEN 'expired' ELSE ui.status END AS invitation_status,
            COALESCE(om.status,'not_queued') AS invitation_delivery_status,om.sent_at AS invitation_sent_at
            FROM staff_import_rows r
            LEFT JOIN user_invitations ui ON ui.id=(SELECT ui2.id FROM user_invitations ui2 WHERE ui2.user_id=r.user_id ORDER BY ui2.id DESC LIMIT 1)
            LEFT JOIN outbound_messages om ON om.id=(SELECT om2.id FROM outbound_messages om2 WHERE om2.user_id=r.user_id AND om2.template_key='staff_account_invitation' ORDER BY om2.id DESC LIMIT 1)
            WHERE r.batch_id=? ORDER BY r.`row_number`");
        $stmt->execute([$batchId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $profileGate = new StaffProfileCompletionService($this->db);
        foreach($rows as &$r){
            $r['data']=json_decode($r['row_data'],true);
            $r['errors']=$r['validation_errors']?json_decode($r['validation_errors'],true):[];
            $r['profile_completed'] = !empty($r['user_id']) && !$profileGate->isRequired((int)$r['user_id']) ? 1 : 0;
            unset($r['row_data'],$r['validation_errors']);
        }
        $summary=$batch['validation_summary']?json_decode($batch['validation_summary'],true):[];
        return ['batch'=>$batch,'rows'=>$rows,'summary'=>$summary,'can_commit'=>$batch['status']==='validated','can_rollback'=>$batch['status']==='completed'];
    }

    public function onboardingForUser(int $userId): array
    {
        $stmt=$this->db->prepare("
            SELECT
                s.id AS staff_id, s.staff_no,
                p.first_name, p.middle_name, p.last_name, p.national_id_no,
                payroll.bank_name, payroll.bank_account, payroll.mpesa_phone,
                (SELECT identifier_value FROM person_professional_identifiers pi WHERE pi.person_id=p.id AND pi.identifier_type='tsc' ORDER BY pi.is_primary DESC,pi.id DESC LIMIT 1) AS tsc_no,
                p.photo_url AS profile_pic_url,
                COALESCE(p.phone,'') AS phone,
                p.gender,
                p.dob AS date_of_birth,
                COALESCE(NULLIF(sep.position,''), NULLIF(s.position,''), '') AS position,
                COALESCE(sep.employment_date, s.employment_date) AS employment_date,
                COALESCE(NULLIF(sep.contract_type,''), NULLIF(s.contract_type,''), '') AS contract_type,
                COALESCE((SELECT sda.department_id FROM staff_department_assignments sda JOIN departments sd ON sd.id=sda.department_id AND sd.status='active' WHERE sda.staff_id=s.id AND (sda.effective_to IS NULL OR sda.effective_to>=CURDATE()) ORDER BY sda.effective_from DESC,sda.id DESC LIMIT 1), sep.department_id) AS department_id,
                s.supervisor_id,
                (SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=u.id ORDER BY ur.is_primary DESC,ur.id LIMIT 1) AS role_name,
                s.status, s.staff_type_id, s.staff_category_id,
                CASE
                    WHEN EXISTS (SELECT 1 FROM staff_import_rows sir WHERE sir.staff_id=s.id AND sir.status='created') THEN 'existing_staff_import'
                    WHEN EXISTS (SELECT 1 FROM staff_appointments sa WHERE sa.created_staff_id=s.id AND sa.status='onboarded' AND sa.candidate_notes LIKE '%[job_application_id=%') THEN 'new_staff_online_application'
                    WHEN EXISTS (SELECT 1 FROM staff_appointments sa WHERE sa.created_staff_id=s.id AND sa.status='onboarded' AND sa.candidate_notes LIKE '%[candidate_source=walk_in]%') THEN 'new_staff_walk_in'
                    WHEN EXISTS (SELECT 1 FROM staff_appointments sa WHERE sa.created_staff_id=s.id AND sa.status='onboarded') THEN 'new_staff_school_entered'
                    ELSE 'existing_staff_manual'
                END AS employment_source,
                NULL AS documents_folder, s.created_at, s.updated_at,
                COALESCE(p.email, '') AS communication_email,
                COALESCE((SELECT contact_value FROM person_contact_points WHERE person_id=p.id AND channel='phone' AND purpose='communication' LIMIT 1), p.phone, '') AS communication_phone,
                COALESCE((SELECT address_line FROM person_addresses WHERE person_id=p.id AND address_type='residential' AND valid_to IS NULL ORDER BY is_primary DESC, id DESC LIMIT 1), '') AS address,
                COALESCE((SELECT marital_status FROM person_marital_statuses WHERE person_id=p.id AND valid_to IS NULL ORDER BY id DESC LIMIT 1), '') AS marital_status,
                COALESCE((SELECT name FROM emergency_contacts WHERE person_id=p.id ORDER BY id LIMIT 1), '') AS emergency_contact_name,
                COALESCE((SELECT phone FROM emergency_contacts WHERE person_id=p.id ORDER BY id LIMIT 1), '') AS emergency_contact_phone,
                COALESCE((SELECT relationship FROM emergency_contacts WHERE person_id=p.id ORDER BY id LIMIT 1), '') AS emergency_contact_relationship,
                CASE WHEN u.password_changed_at IS NOT NULL THEN 1 ELSE 0 END AS password_completed,
                CASE WHEN NULLIF(TRIM(p.phone),'') IS NOT NULL AND NULLIF(TRIM(p.gender),'') IS NOT NULL AND p.dob IS NOT NULL
                    AND EXISTS (SELECT 1 FROM person_addresses pa WHERE pa.person_id=p.id AND pa.address_type='residential' AND pa.valid_to IS NULL AND NULLIF(TRIM(pa.address_line),'') IS NOT NULL)
                    THEN 1 ELSE 0 END AS profile_completed,
                CASE WHEN p.email IS NOT NULL AND p.phone IS NOT NULL THEN 1 ELSE 0 END AS communication_completed,
                CASE WHEN NULLIF(TRIM(p.phone),'') IS NOT NULL AND NULLIF(TRIM(p.gender),'') IS NOT NULL AND p.dob IS NOT NULL
                    AND EXISTS (SELECT 1 FROM person_addresses pa WHERE pa.person_id=p.id AND pa.address_type='residential' AND pa.valid_to IS NULL AND NULLIF(TRIM(pa.address_line),'') IS NOT NULL)
                    THEN 'completed' ELSE 'invited' END AS onboarding_status,
                COALESCE((SELECT sd.name FROM staff_department_assignments sda JOIN departments sd ON sd.id=sda.department_id AND sd.status='active' WHERE sda.staff_id=s.id AND (sda.effective_to IS NULL OR sda.effective_to>=CURDATE()) ORDER BY sda.effective_from DESC,sda.id DESC LIMIT 1), (SELECT sd.name FROM departments sd WHERE sd.id=sep.department_id)) AS department_name,
                st.name AS staff_type_name,
                sc.category_name AS staff_category_name,
                CONCAT(sp.first_name, ' ', sp.last_name) AS supervisor_name
            FROM users u
            JOIN persons p ON p.id = u.person_id
            JOIN staff s ON s.person_id = p.id
            LEFT JOIN staff_payroll_profiles payroll ON payroll.staff_id=s.id
            LEFT JOIN staff_employment_profiles sep ON sep.id=(
                SELECT current_sep.id
                FROM staff_employment_profiles current_sep
                WHERE current_sep.staff_id=s.id AND current_sep.status='active'
                ORDER BY COALESCE(current_sep.employment_date,'1000-01-01') DESC,
                         current_sep.updated_at DESC,current_sep.id DESC
                LIMIT 1
            )
            LEFT JOIN staff_types st ON st.id = s.staff_type_id
            LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
            LEFT JOIN staff su ON su.id = s.supervisor_id
            LEFT JOIN persons sp ON sp.id = su.person_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Staff onboarding profile not found.');
        $row['phone_valid'] = PhoneNumberNormalizer::isValid((string)($row['phone'] ?? ''));
        $row['email_valid'] = filter_var((string)($row['communication_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
        $row['gender_valid'] = in_array(strtolower(trim((string)($row['gender'] ?? ''))), ['male', 'female', 'other'], true);
        $dob = DateTimeImmutable::createFromFormat('!Y-m-d', (string)($row['date_of_birth'] ?? ''));
        $row['date_of_birth_valid'] = (bool)($dob && $dob->format('Y-m-d') === (string)$row['date_of_birth'] && $dob <= new DateTimeImmutable('today'));
        $row['profile_completed'] = !empty($row['phone_valid']) && !empty($row['gender_valid'])
            && !empty($row['date_of_birth_valid']) && (int)$row['profile_completed'] === 1 ? 1 : 0;
        $row['communication_completed'] = !empty($row['communication_email']) && !empty($row['phone_valid']) ? 1 : 0;
        $row['onboarding_status'] = $row['profile_completed'] === 1 ? 'completed' : 'invited';
        $qualificationStmt=$this->db->prepare("SELECT id,qualification_level,source,verification_status,title,institution,year_obtained,description,document_url FROM staff_qualifications WHERE staff_id=? ORDER BY year_obtained DESC,id DESC");
        $qualificationStmt->execute([(int)$row['staff_id']]);
        $row['qualification_claims']=$qualificationStmt->fetchAll(PDO::FETCH_ASSOC);
        $row['learning_areas']=$this->rows("SELECT id,name,code FROM learning_areas WHERE status='active' ORDER BY name");
        $row['requested_learning_area_ids'] = array_map('intval', array_column($this->rows("SELECT learning_area_id FROM staff_learning_area_specializations WHERE staff_id=? AND status IN ('pending','approved') ORDER BY is_primary DESC,id", [(int)$row['staff_id']]), 'learning_area_id'));
        $row['primary_learning_area_id'] = (int)($this->scalar("SELECT learning_area_id FROM staff_learning_area_specializations WHERE staff_id=? AND is_primary=1 AND status IN ('pending','approved') ORDER BY id DESC LIMIT 1", [(int)$row['staff_id']]) ?: 0);
        return $row;
    }

    public function completeProfile(int $userId,array $data): array
    {
        if ($this->db->inTransaction()) throw new RuntimeException('Profile completion cannot join another database transaction.');
        $this->db->beginTransaction();
        try {
            $result = $this->completeProfileInTransaction($userId, $data);
            $this->db->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    private function completeProfileInTransaction(int $userId, array $data): array
    {
        $stmt = $this->db->prepare("SELECT s.id AS sid, p.id AS pid, p.phone, p.gender, p.dob AS date_of_birth,
                p.national_id_no, p.middle_name,
                payroll.bank_name, payroll.bank_account, payroll.mpesa_phone,
                (SELECT identifier_value FROM person_professional_identifiers pi WHERE pi.person_id=p.id AND pi.identifier_type='tsc' ORDER BY pi.is_primary DESC,pi.id DESC LIMIT 1) AS tsc_no,
                COALESCE(NULLIF(sep.position, ''), NULLIF(s.position, '')) AS position,
                COALESCE(sep.employment_date, s.employment_date) AS employment_date,
                COALESCE(NULLIF(sep.contract_type, ''), NULLIF(s.contract_type, '')) AS contract_type,
                s.staff_type_id, s.staff_category_id, s.supervisor_id,
                (SELECT address_line FROM person_addresses pa WHERE pa.person_id=p.id AND pa.address_type='residential' AND pa.valid_to IS NULL ORDER BY pa.is_primary DESC,pa.id DESC LIMIT 1) AS address
            FROM staff s
            JOIN persons p ON p.id=s.person_id
            JOIN users u ON u.person_id=s.person_id
            LEFT JOIN staff_payroll_profiles payroll ON payroll.staff_id=s.id
            LEFT JOIN staff_employment_profiles sep ON sep.id=(
                SELECT current_sep.id
                FROM staff_employment_profiles current_sep
                WHERE current_sep.staff_id=s.id AND current_sep.status='active'
                ORDER BY COALESCE(current_sep.employment_date,'1000-01-01') DESC,
                         current_sep.updated_at DESC,current_sep.id DESC
                LIMIT 1
            )
            WHERE u.id=? FOR UPDATE");
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Staff profile not found.');
        $sid=(int)$row['sid'];$pid=(int)$row['pid'];
        $profileData=[];
        foreach(['phone','address','gender','date_of_birth'] as $f){
            $submitted=trim((string)($data[$f]??''));
            $stored=trim((string)($row[$f]??''));
            $profileData[$f]=$submitted!==''?$submitted:$stored;
            if($profileData[$f]==='')throw new RuntimeException("$f is required because it is missing from your staff record.");
        }
        $normalizedPhone = PhoneNumberNormalizer::normalize($profileData['phone']);
        if ($normalizedPhone === null) throw new RuntimeException('Enter a valid Kenyan mobile phone number.');
        $profileData['phone'] = $normalizedPhone;
        if (mb_strlen($profileData['address']) > 255) throw new RuntimeException('Residential address must be 255 characters or fewer.');
        $profileData['gender']=strtolower($profileData['gender']);
        if (!in_array($profileData['gender'], ['male','female','other'], true)) throw new RuntimeException('Select a valid gender.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$profileData['date_of_birth']);
        if(!$date || $date->format('Y-m-d')!==$profileData['date_of_birth'] || $date > new DateTimeImmutable('today')) throw new RuntimeException('Enter a valid date of birth that is not in the future.');
        $emergencyName=trim((string)($data['emergency_contact_name']??''));
        $emergencyPhone=trim((string)($data['emergency_contact_phone']??''));
        if (($emergencyName==='') !== ($emergencyPhone==='')) throw new RuntimeException('Enter both an emergency contact name and phone number, or leave both blank.');
        if ($emergencyName !== '' && mb_strlen($emergencyName) > 100) throw new RuntimeException('Emergency contact name must be 100 characters or fewer.');
        if ($emergencyPhone !== '') {
            $emergencyPhone = PhoneNumberNormalizer::normalize($emergencyPhone);
            if ($emergencyPhone === null) throw new RuntimeException('Enter a valid Kenyan mobile number for the emergency contact.');
        }
        $bankName = trim((string)($data['bank_name'] ?? $row['bank_name'] ?? ''));
        $bankAccount = trim((string)($data['bank_account'] ?? $row['bank_account'] ?? ''));
        if (($bankName === '') !== ($bankAccount === '')) throw new RuntimeException('Enter both bank name and account number, or leave both blank.');
        $mpesaPhone = trim((string)($data['mpesa_phone'] ?? $row['mpesa_phone'] ?? ''));
        if ($mpesaPhone !== '') {
            $mpesaPhone = PhoneNumberNormalizer::normalize($mpesaPhone);
            if ($mpesaPhone === null) throw new RuntimeException('Enter a valid Kenyan mobile number for M-Pesa payments.');
        }
        $tscNumber = strtoupper(trim((string)($data['tsc_no'] ?? $row['tsc_no'] ?? '')));
        if ($tscNumber !== '') {
            $duplicateTsc = $this->db->prepare("SELECT person_id FROM person_professional_identifiers WHERE identifier_type='tsc' AND LOWER(identifier_value)=LOWER(?) LIMIT 1");
            $duplicateTsc->execute([$tscNumber]);
            $owner = $duplicateTsc->fetchColumn();
            if ($owner && (int)$owner !== $pid) throw new RuntimeException('That TSC number is already recorded for another person.');
        }
        $employmentDate=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$row['employment_date']);
        if (!$row['position'] || !$employmentDate || $employmentDate->format('Y-m-d')!==$row['employment_date'] || !in_array((string)$row['contract_type'], ['permanent','contract','temporary'], true) || (int)$row['staff_type_id']<1 || (int)$row['staff_category_id']<1) throw new RuntimeException('The school must finish your employment assignment before profile completion. Contact the System Administrator.');
        $assignment = $this->db->prepare("SELECT department_id FROM (
                SELECT sda.department_id, sda.effective_from, sda.id AS assignment_id
                FROM staff_department_assignments sda
                JOIN departments d ON d.id=sda.department_id AND d.status='active'
                WHERE sda.staff_id=? AND (sda.effective_to IS NULL OR sda.effective_to>=CURDATE())
                UNION ALL
                SELECT sep.department_id, sep.employment_date AS effective_from, 0 AS assignment_id
                FROM staff_employment_profiles sep
                JOIN departments d ON d.id=sep.department_id AND d.status='active'
                WHERE sep.staff_id=? AND sep.status='active'
            ) current_assignments
            ORDER BY effective_from DESC, assignment_id DESC LIMIT 1");
        $assignment->execute([$sid, $sid]);
        $departmentId = (int)$assignment->fetchColumn();
        if($departmentId<=0) throw new RuntimeException('Your department has not been assigned. Contact the System Administrator.');
        $refs=$this->db->prepare("SELECT EXISTS(SELECT 1 FROM staff_types WHERE id=? AND is_active=1) t, EXISTS(SELECT 1 FROM staff_categories WHERE id=? AND staff_type_id=? AND is_active=1) c");
        $refs->execute([(int)$row['staff_type_id'],(int)$row['staff_category_id'],(int)$row['staff_type_id']]);
        if (in_array(0,array_map('intval',$refs->fetch(PDO::FETCH_ASSOC) ?: []),true)) throw new RuntimeException('The school employment classification is invalid. Contact the System Administrator.');
        if (!empty($row['supervisor_id'])) { $sup=$this->db->prepare("SELECT 1 FROM staff WHERE id=? AND status='active' AND data_scope='live'"); $sup->execute([(int)$row['supervisor_id']]); if(!$sup->fetchColumn()) throw new RuntimeException('The assigned supervisor is not active. Contact the System Administrator.'); }
            $this->db->prepare("UPDATE persons SET middle_name=NULLIF(?,''),phone=?,gender=?,dob=?,national_id_no=NULLIF(?, '') WHERE id=?")
                ->execute([trim((string)($data['middle_name']??$row['middle_name']??'')),$profileData['phone'],$profileData['gender'],$profileData['date_of_birth'],trim((string)($data['national_id_no']??$row['national_id_no']??'')),$pid]);
            $this->db->prepare("INSERT INTO staff_payroll_profiles (staff_id,bank_name,bank_account,mpesa_phone,status)
                VALUES (?,?,?,?,'draft') ON DUPLICATE KEY UPDATE bank_name=VALUES(bank_name),bank_account=VALUES(bank_account),mpesa_phone=VALUES(mpesa_phone),updated_at=NOW()")
                ->execute([$sid,$bankName !== '' ? $bankName : null,$bankAccount !== '' ? $bankAccount : null,$mpesaPhone !== '' ? $mpesaPhone : null]);
            if ($tscNumber !== '') {
                $this->db->prepare("INSERT INTO person_professional_identifiers(person_id,identifier_type,identifier_value,issuing_body,is_primary)
                    VALUES(?,'tsc',?,'Teachers Service Commission',1)
                    ON DUPLICATE KEY UPDATE person_id=VALUES(person_id),issuing_body=VALUES(issuing_body),is_primary=1,updated_at=NOW()")
                    ->execute([$pid,$tscNumber]);
            }
            $this->db->prepare("INSERT INTO person_addresses (person_id,address_type,address_line,is_primary,valid_from) VALUES (?, 'residential', ?, 1, CURDATE()) ON DUPLICATE KEY UPDATE address_line=VALUES(address_line), is_primary=1, valid_to=NULL, updated_at=NOW()")
                ->execute([$pid, $profileData['address']]);
            $this->db->prepare("UPDATE person_addresses SET valid_to=CURDATE() WHERE person_id=? AND address_type='residential' AND valid_to IS NULL AND address_line<>? AND valid_from<CURDATE()")
                ->execute([$pid, $profileData['address']]);
            if (array_key_exists('emergency_contact_name', $data)) {
                $this->db->prepare("DELETE FROM emergency_contacts WHERE person_id=?")->execute([$pid]);
            }
            if(!empty($data['emergency_contact_name'])){
                $this->db->prepare("INSERT INTO emergency_contacts(person_id,name,phone,relationship,created_at) VALUES(?,?,?,?,NOW())")
                    ->execute([$pid,$data['emergency_contact_name'],$data['emergency_contact_phone']??null,$data['emergency_contact_relationship']??null]);
            }
            if (array_key_exists('qualifications', $data)) {
                if (!is_array($data['qualifications']) || count($data['qualifications']) > 20) {
                    throw new RuntimeException('Qualifications must be an array containing at most 20 records.');
                }
                $allowedLevels = ['certificate','diploma','degree','postgraduate_diploma','masters','phd','professional','other'];
                $qualificationStmt = $this->db->prepare("INSERT INTO staff_qualifications
                    (staff_id, qualification_type, qualification_level, source, verification_status, submitted_by, title, institution, year_obtained, description, document_url)
                    VALUES (?, ?, ?, 'self_reported', 'pending', ?, ?, ?, ?, ?, ?)");
                foreach ($data['qualifications'] as $qualification) {
                    if (!is_array($qualification)) throw new RuntimeException('Each qualification must be an object.');
                    $title = trim((string)($qualification['title'] ?? ''));
                    $institution = trim((string)($qualification['institution'] ?? ''));
                    if ($title === '' || $institution === '') throw new RuntimeException('Each qualification requires a title and institution.');
                    $level = (string)($qualification['qualification_level'] ?? $qualification['level'] ?? 'other');
                    if (!in_array($level, $allowedLevels, true)) throw new RuntimeException('Invalid qualification level.');
                    $legacyType = in_array($level, ['certificate','diploma','degree'], true) ? $level : 'other';
                    $year = $qualification['year_obtained'] ?? $qualification['year'] ?? null;
                    if ($year !== null && $year !== '' && (!is_numeric($year) || (int)$year < 1900 || (int)$year > ((int)date('Y') + 1))) throw new RuntimeException('Qualification year is invalid.');
                    $description = trim((string)($qualification['description'] ?? '')) ?: null;
                    $documentUrl = trim((string)($qualification['document_url'] ?? '')) ?: null;
                    $duplicateStmt=$this->db->prepare("SELECT id FROM staff_qualifications WHERE staff_id=? AND source='self_reported' AND verification_status='pending' AND qualification_level=? AND title=? AND institution=? AND (year_obtained <=> ?) AND (description <=> ?) AND (document_url <=> ?) LIMIT 1");
                    $duplicateStmt->execute([$sid,$level,$title,$institution,$year ?: null,$description,$documentUrl]);
                    if (!$duplicateStmt->fetchColumn()) {
                        $qualificationStmt->execute([$sid, $legacyType, $level, $userId, $title, $institution, $year ?: null, $description, $documentUrl]);
                    }
                }
            }
            $selectedAreaIds = array_values(array_unique(array_filter(array_map('intval', (array)($data['learning_area_ids'] ?? [])), static fn(int $id): bool => $id > 0)));
            $primaryAreaId = (int)($data['primary_learning_area_id'] ?? 0);
            $teaching = $this->db->prepare("SELECT 1 FROM staff s JOIN staff_types st ON st.id=s.staff_type_id WHERE s.id=? AND LOWER(st.name) LIKE '%teach%' LIMIT 1");
            $teaching->execute([$sid]);
            $isTeachingStaff = (bool)$teaching->fetchColumn();
            if ($selectedAreaIds && !$isTeachingStaff) throw new RuntimeException('Only teaching staff can submit learning-area specializations.');
            if ($primaryAreaId > 0 && !in_array($primaryAreaId, $selectedAreaIds, true)) throw new RuntimeException('The primary learning area must be one of the selected areas.');
            $approvedPrimaryAreaId = (int)($this->scalar("SELECT learning_area_id FROM staff_learning_area_specializations WHERE staff_id=? AND is_primary=1 AND status='approved' ORDER BY id DESC LIMIT 1", [$sid]) ?: 0);
            if ($approvedPrimaryAreaId > 0 && $primaryAreaId > 0 && $primaryAreaId !== $approvedPrimaryAreaId) throw new RuntimeException('An approved primary learning area can only be changed by the school administrator.');
            if ($isTeachingStaff && array_key_exists('learning_area_ids', $data)) {
                if (array_key_exists('primary_learning_area_id', $data)) $this->db->prepare("UPDATE staff_learning_area_specializations SET is_primary=0,specialization_level='secondary' WHERE staff_id=? AND status='pending'")->execute([$sid]);
                if ($selectedAreaIds) {
                    $this->db->prepare("UPDATE staff_learning_area_specializations SET status='inactive' WHERE staff_id=? AND status='pending' AND learning_area_id NOT IN (" . implode(',', array_fill(0, count($selectedAreaIds), '?')) . ")")
                        ->execute(array_merge([$sid], $selectedAreaIds));
                } else {
                    $this->db->prepare("UPDATE staff_learning_area_specializations SET status='inactive' WHERE staff_id=? AND status='pending'")->execute([$sid]);
                }
                $areaCheck = $this->db->prepare("SELECT id FROM learning_areas WHERE id=? AND status='active'");
                $specialization = $this->db->prepare("INSERT INTO staff_learning_area_specializations (staff_id,learning_area_id,specialization_level,is_primary,status,created_by) VALUES (?,?,?,?,'pending',?) ON DUPLICATE KEY UPDATE specialization_level=IF(status='approved',specialization_level,VALUES(specialization_level)),is_primary=IF(status='approved',is_primary,VALUES(is_primary)),status=IF(status='approved','approved','pending')");
                foreach ($selectedAreaIds as $areaId) {
                    $areaCheck->execute([$areaId]);
                    if (!$areaCheck->fetchColumn()) throw new RuntimeException('A selected learning area is no longer active. Refresh your profile and try again.');
                    $isPrimary = $primaryAreaId > 0 && $areaId === $primaryAreaId;
                    $specialization->execute([$sid,$areaId,$isPrimary?'primary':'secondary',$isPrimary?1:0,$userId]);
                }
            }
        $this->db->prepare("UPDATE users SET profile_completed_at=NOW() WHERE id=?")->execute([$userId]);
        $this->audit($userId,'staff_profile_completed','staff',$sid);
        return $this->onboardingForUser($userId);
    }

    private function createStaffGraph(array $r,int $batchId,int $rowId,int $actorId): array
    {
        $dept=$this->departmentId($r['department_name'] ?? '');
        $role=$this->schoolRoleId($this->canonicalRoleName((string)$r['role_name']));
        $type=$this->nullableLookup('staff_types','name',$r['staff_type']??null,"is_active=1");
        $cat=$this->categoryId($r['staff_category']??'',$r['staff_type']??'');
        $roleIds=$this->roleIdsForStaff($role,$r['role_name'],$type);
        $username=UsernameService::generate($this->db,$r['email'],$r['first_name'],$r['last_name']);
        $temporary=$this->generateTemporaryPassword();
        $this->db->prepare("INSERT INTO persons(first_name,middle_name,last_name,dob,gender,email,phone) VALUES(?,?,?,?,?,?,?)")
            ->execute([$r['first_name'],$this->null($r,'middle_name'),$r['last_name'],$this->null($r,'date_of_birth'),$this->null($r,'gender'),strtolower($r['email']),$this->normalizedPhone($r,'phone')]);
        $pid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO users(person_id,username,password_hash,status,force_password_change,is_test_user,account_type,data_scope,two_factor_enabled,two_factor_method,created_at,updated_at) VALUES(?,?,?,'active',1,0,'real','live',1,'email',NOW(),NOW())")
            ->execute([$pid,$username,password_hash($temporary,PASSWORD_DEFAULT)]);
        $uid=(int)$this->db->lastInsertId();
        $roleManager = new \App\API\Modules\users\UserRoleManager($this->db);
        foreach ($roleIds as $index => $roleId) {
            $assigned = $roleManager->assignRole($uid, (int)$roleId, $index === 0);
            if (empty($assigned['success'])) throw new RuntimeException('A staff role could not be assigned for the imported account.');
        }
        $this->db->prepare("INSERT INTO user_two_factor_methods(user_id,method,label,is_primary,is_enabled,verified_at) VALUES(?,'email','Account email',1,1,NULL) ON DUPLICATE KEY UPDATE is_enabled=1,is_primary=1")
            ->execute([$uid]);
        // Auto-generate staff_no when blank; validate format when provided.
        $staffNoSvc = new StaffNumberService($this->db);
        $staffNo = trim((string)($r['staff_no'] ?? ''));
        if ($staffNo === '') {
            $staffNo = $staffNoSvc->generate();
        } elseif (!$staffNoSvc->isValid($staffNo)) {
            throw new RuntimeException("Row: staff_no '$staffNo' does not match the configured format");
        }
        $supervisorId=$this->supervisorId($r['supervisor_staff_no']??'');
        $employmentDate = trim((string)($r['employment_date'] ?? '')) ?: null;
        $position = StaffPositionCatalog::normalize((string)$r['position']);
        $positionId = StaffPositionCatalog::resolveId($this->db, $position);
        $this->db->prepare("INSERT INTO staff(person_id,staff_type_id,staff_category_id,staff_no,position,contract_type,employment_date,status,data_scope,supervisor_id) VALUES(?,?,?,?,?,?,?,'active','live',?)")
            ->execute([$pid,$type,$cat,$staffNo,$position,strtolower($r['contract_type']),$employmentDate,$supervisorId]);
        $sid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO staff_department_assignments(staff_id,department_id,role,effective_from,effective_to,created_at) VALUES(?,?,NULL,?,NULL,NOW())")
            ->execute([$sid,$dept,$employmentDate]);
        $this->db->prepare("INSERT INTO staff_employment_profiles(staff_id,department_id,position_id,position,employment_date,contract_type,status,created_at,updated_at) VALUES(?,?,?,?,?,?,'active',NOW(),NOW())")
            ->execute([$sid,$dept,$positionId,$position,$employmentDate,strtolower($r['contract_type'])]);
        if (($r['work_start_time'] ?? '') !== '' && ($r['work_end_time'] ?? '') !== '') {
            $this->db->prepare("INSERT INTO staff_attendance_profiles(staff_id,work_start_time,work_end_time,late_threshold_minutes,is_active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())")
                ->execute([$sid,$r['work_start_time'],$r['work_end_time'],(int)($r['late_threshold_minutes']?:15)]);
        }
        if(!empty($r['address']??'')){
            $this->db->prepare("INSERT INTO person_addresses(person_id,address_type,address_line,is_primary,valid_from) VALUES(?,'residential',?,1,CURDATE())")
                ->execute([$pid,trim($r['address'])]);
        }
        if(!empty($r['marital_status']??'')){
            $this->db->prepare("INSERT INTO person_marital_statuses(person_id,marital_status,valid_from) VALUES(?,?,CURDATE())")
                ->execute([$pid,strtolower(trim($r['marital_status']))]);
        }
        if(!empty($r['tsc_no']??'')){
            $this->db->prepare("INSERT INTO person_professional_identifiers(person_id,identifier_type,identifier_value,issuing_body,is_primary,created_at,updated_at) VALUES(?,'tsc',?,'Teachers Service Commission',1,NOW(),NOW())")
                ->execute([$pid,strtoupper(trim($r['tsc_no']))]);
        }
        // Salary is resolved from the primary role during payroll. Imports only
        // create the statutory/payment profile; personal compensation overrides
        // are entered later by an authorised payroll administrator.
        $this->db->prepare("INSERT INTO staff_payroll_profiles(staff_id,bank_name,bank_account,mpesa_phone,kra_pin,nssf_no,nhif_no,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?, 'active',NOW(),NOW())")
            ->execute([$sid,$this->null($r,'bank_name'),$this->null($r,'bank_account'),$this->normalizedPhone($r,'mpesa_phone'),$this->null($r,'kra_pin'),$this->null($r,'nssf_no'),$this->null($r,'nhif_no')]);
        $learningAreas = $this->learningAreaIds((string)($r['learning_areas'] ?? ''));
        foreach ($learningAreas as $learningAreaId) {
            $this->db->prepare("INSERT INTO staff_learning_area_specializations
                (staff_id,learning_area_id,specialization_level,is_primary,status,notes,created_by,effective_from)
                VALUES (?,?,'secondary',0,'pending','Imported staff learning-area claim; verify qualification evidence before approval.',?,?)")
                ->execute([$sid,$learningAreaId,$actorId,$employmentDate]);
        }
        if (($r['leadership_position_name'] ?? '') !== '') {
            $leaderPosition = $this->db->prepare("SELECT lp.id FROM leadership_positions lp JOIN leadership_categories lc ON lc.id=lp.leadership_category_id WHERE LOWER(TRIM(lp.name))=LOWER(TRIM(?)) AND lp.is_active=1 AND lc.is_active=1 AND lc.holder_scope IN ('staff','any_person') LIMIT 1");
            $leaderPosition->execute([$r['leadership_position_name']]);
            $positionId = (int)$leaderPosition->fetchColumn();
            $yearId = (int)$this->db->query("SELECT id FROM academic_years ORDER BY is_current DESC,id DESC LIMIT 1")->fetchColumn();
            if (!$positionId || !$yearId) throw new RuntimeException('The selected leadership position or current academic year is unavailable.');
            $this->db->prepare("INSERT INTO school_leader (academic_year_id,leadership_position_id,scope_type,person_id,staff_id,start_date,is_active) VALUES (?,?,'school',?,?,?,1)")
                ->execute([$yearId,$positionId,$pid,$sid,$employmentDate ?: date('Y-m-d')]);
        }
        if(!empty($r['communication_email']??'')){
            $this->db->prepare("INSERT INTO person_contact_points(person_id,channel,purpose,contact_value,is_primary) VALUES(?,'email','communication',?,1)")
                ->execute([$pid,strtolower(trim($r['communication_email']))]);
        }
        if(!empty($r['communication_phone']??'')){
            $this->db->prepare("INSERT INTO person_contact_points(person_id,channel,purpose,contact_value,is_primary) VALUES(?,'phone','communication',?,1)")
                ->execute([$pid,$this->normalizedPhone($r,'communication_phone')]);
        }
        if(!empty($r['emergency_contact_name']??'')){
            $this->db->prepare("INSERT INTO emergency_contacts(person_id,name,phone,created_at) VALUES(?,?,?,NOW())")
                ->execute([$pid,$r['emergency_contact_name'],$this->normalizedPhone($r,'emergency_contact_phone')]);
        }
        $token=$this->createInvitation($uid,$sid,$r['email'],$actorId);
        $baseUrl=self::applicationBaseUrl();$url=self::invitationSetupUrl($token);
        $messageId = $this->queueEmail($uid,$r['email'],'staff_account_invitation','Your Kingsway account is ready',[
            'name'=>$r['first_name'].' '.$r['last_name'],
            'username'=>$username,
            'activation_url'=>$url,
            'setup_url'=>$url,
            'login_url'=>rtrim($baseUrl,'/').'/index.php?route=r6d394ab20b0b',
            'expires_hours'=>72,
        ]);
        $this->db->prepare("UPDATE staff_import_rows SET staff_id=?,user_id=?,status='created',updated_at=NOW() WHERE id=?")->execute([$sid,$uid,$rowId]);
        return ['staff_id'=>$sid,'user_id'=>$uid,'staff_no'=>$staffNo,'username'=>$username,'email'=>$r['email'],'invitation_queued'=>true,'invitation_message_id'=>$messageId];
    }

    private function createInvitation(int $uid,int $sid,string $email,int $actor): string
    {
        $this->db->prepare("UPDATE outbound_messages SET status='cancelled',last_error='Replaced by a newer staff invitation',updated_at=NOW() WHERE user_id=? AND template_key='staff_account_invitation' AND status IN ('queued','retry')")
            ->execute([$uid]);
        $this->db->prepare("UPDATE user_invitations SET status='revoked',revoked_at=NOW() WHERE user_id=? AND status='pending'")->execute([$uid]);
        $token=bin2hex(random_bytes(32));
        $this->db->prepare("INSERT INTO user_invitations(user_id,staff_id,email,token_hash,status,expires_at,created_by,created_at,updated_at) VALUES(?,?,?,?,'pending',DATE_ADD(NOW(),INTERVAL 72 HOUR),?,NOW(),NOW())")
            ->execute([$uid,$sid,strtolower($email),hash('sha256',$token),$actor]);return $token;
    }

    /** Invitation links must use deployment configuration, never request data. */
    public static function applicationBaseUrl(): string
    {
        foreach (['BASE_URL', 'APP_URL'] as $constant) {
            if (!defined($constant)) continue;
            $candidate = trim((string)constant($constant));
            $parts = parse_url($candidate);
            if ($candidate !== '' && is_array($parts)
                && in_array(strtolower((string)($parts['scheme'] ?? '')), ['http', 'https'], true)
                && !empty($parts['host'])
                && empty($parts['user']) && empty($parts['pass'])) {
                return rtrim($candidate, '/');
            }
        }
        throw new RuntimeException('The public application URL is not configured. Set BASE_URL before sending staff invitations.');
    }

    public static function invitationSetupUrl(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            throw new RuntimeException('A valid staff invitation token is required to create the setup link.');
        }
        return self::applicationBaseUrl() . '/index.php?route=rf4a47967b780&token=' . rawurlencode($token);
    }

    private function queueEmail(int $uid,string $to,string $template,string $subject,array $payload): int
    {
        $this->db->prepare("INSERT INTO outbound_messages(user_id,channel,recipient,template_key,subject,payload_json,status,attempts,next_attempt_at,created_at,updated_at) VALUES(?,'email',?,?,?,?, 'queued',0,NOW(),NOW(),NOW())")
            ->execute([$uid,strtolower($to),$template,$subject,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        return (int)$this->db->lastInsertId();
    }
    private function renderStaffInvitationEmail(array $payload): string
    {
        $name=htmlspecialchars((string)($payload['name']??'Staff member'),ENT_QUOTES,'UTF-8');
        $username=htmlspecialchars((string)($payload['username']??''),ENT_QUOTES,'UTF-8');
        $setup=htmlspecialchars((string)($payload['setup_url']??$payload['activation_url']??''),ENT_QUOTES,'UTF-8');
        $login=htmlspecialchars((string)($payload['login_url']??''),ENT_QUOTES,'UTF-8');
        return "<p>Dear {$name},</p>"
            . "<p>Welcome to Kingsway Preparatory School. Your staff account is ready.</p>"
            . "<p><strong>Username:</strong> {$username}</p>"
            . "<p><strong>Before you can open your dashboard, please complete these steps:</strong></p>"
            . "<ol><li>Open the secure setup link below.</li><li>Create a private password.</li><li>Verify your email using the one-time code.</li><li>Complete the personal details missing from your staff profile.</li></ol>"
            . "<p><a href=\"{$setup}\" style=\"display:inline-block;padding:12px 20px;background:#075985;color:#fff;text-decoration:none;border-radius:6px\">Set up my Kingsway account</a></p>"
            . ($login ? "<p>After setup, sign in here: <a href=\"{$login}\">{$login}</a></p>" : '')
            . "<p>The secure setup link expires in 72 hours. If it expires, contact the School Administrator for a new invitation.</p>"
            . "<p>If you were not expecting this account, please do not use these credentials and contact the school.</p>";
    }
    private function validateRow(array $r,int $row,array $dupes): array
    {
        $e=[];foreach(self::REQUIRED as $f)if(trim((string)($r[$f]??''))==='')$e[]="$f is required";
        if(($r['email']??'')&&!filter_var($r['email'],FILTER_VALIDATE_EMAIL))$e[]='email is invalid';
        if(($r['communication_email']??'')&&!filter_var($r['communication_email'],FILTER_VALIDATE_EMAIL))$e[]='communication_email is invalid';
        if(($r['employment_date']??'')&&!$this->validDate($r['employment_date']))$e[]='employment_date must be YYYY-MM-DD';
        if(($r['date_of_birth']??'')&&!$this->validDate($r['date_of_birth']))$e[]='date_of_birth must be YYYY-MM-DD';
        if(!in_array(strtolower((string)($r['contract_type']??'')),['permanent','contract','temporary'],true))$e[]='contract_type is invalid';
        if(($r['gender']??'')&&!in_array(strtolower($r['gender']),['male','female','other'],true))$e[]='gender is invalid';
        if(($r['marital_status']??'')&&!in_array(strtolower($r['marital_status']),['single','married','divorced','widowed','separated','unknown'],true))$e[]='marital_status is invalid';
        if(($r['staff_no']??'')!==''&&$this->exists('staff','staff_no',$r['staff_no']))$e[]='staff_no already exists';
        if(($r['staff_no']??'')!==''&&!(new StaffNumberService($this->db))->isValid($r['staff_no']))$e[]='staff_no does not match the configured format';
        if(($r['email']??'')&&$this->exists('persons','email',$r['email']))$e[]='email already belongs to a user';
        if(($r['staff_no']??'')!==''&&in_array(strtolower($r['staff_no']),$dupes['staff_no'],true))$e[]='staff_no is duplicated in this file';
        if(in_array(strtolower($r['email']??''),$dupes['email'],true))$e[]='email is duplicated in this file';
        if(($r['tsc_no']??'')!==''&&in_array(strtolower($r['tsc_no']),$dupes['tsc_no'],true))$e[]='tsc_no is duplicated in this file';
        if(($r['tsc_no']??'')!==''&&$this->professionalIdentifierExists('tsc',$r['tsc_no']))$e[]='tsc_no already exists';
        foreach (['phone','communication_phone','emergency_contact_phone','mpesa_phone'] as $phoneField) {
            if (($r[$phoneField] ?? '') !== '' && PhoneNumberNormalizer::normalize((string)$r[$phoneField]) === null) {
                $e[] = "$phoneField is not a valid Kenyan mobile number";
            }
        }
        if ((($r['bank_name'] ?? '') === '') !== (($r['bank_account'] ?? '') === '')) {
            $e[] = 'bank_name and bank_account must be supplied together';
        }
        $department = trim((string)($r['department_name'] ?? ''));
        if($department !== '' && !$this->departmentExists($department))$e[]='department_name was not found or inactive';
        if(($r['role_name']??'')&&!$this->schoolRoleExists($this->canonicalRoleName((string)$r['role_name'])))$e[]='role_name was not found, inactive, or not an assignable school role';
        if(($r['staff_type']??'')&&!$this->lookupExists('staff_types','name',$r['staff_type'],"is_active=1"))$e[]='staff_type was not found';
        if(($r['staff_category']??'')&&!$this->lookupExists('staff_categories','category_name',$r['staff_category'],"is_active=1"))$e[]='staff_category was not found';
        if(($r['staff_category']??'')!==''&&trim((string)($r['staff_type']??''))==='')$e[]='staff_type is required when staff_category is supplied';
        if(($r['staff_type']??'')!==''&&($r['staff_category']??'')!==''&&!$this->categoryBelongsToType($r['staff_category'],$r['staff_type']))$e[]='staff_category does not belong to staff_type';
        if (($r['position'] ?? '') === '') $e[] = 'position is blank and this role has no configured default; provide a position or configure a default in Staff Setup';
        elseif (!$this->staffPositionExistsForRow($r)) $e[] = 'position is not an active position for the selected staff type, category and role';
        if (($r['leadership_position_name'] ?? '') !== '' && !$this->staffLeadershipPositionExists((string)$r['leadership_position_name'])) $e[] = 'leadership_position_name is not an active staff leadership position';
        if (($r['learning_areas'] ?? '') !== '' && !$this->learningAreasExist((string)$r['learning_areas'])) $e[] = 'learning_areas contains an unknown or inactive learning area';
        if(($r['supervisor_staff_no']??'')!==''&&!$this->lookupExists('staff','staff_no',$r['supervisor_staff_no'],"status='active'"))$e[]='supervisor_staff_no was not found or inactive';
        foreach(['work_start_time','work_end_time'] as $time){if(($r[$time]??'')!==''&&!$this->validTime($r[$time]))$e[]="$time must be HH:MM or HH:MM:SS";}
        if ((($r['work_start_time']??'')==='') !== (($r['work_end_time']??'')==='')) $e[]='work_start_time and work_end_time must be supplied together';
        if (($r['late_threshold_minutes']??'')!==''&&($r['work_start_time']??'')==='') $e[]='late_threshold_minutes requires a work schedule';
        if(($r['work_start_time']??'')!==''&&($r['work_end_time']??'')!==''&&strtotime($r['work_start_time'])>=strtotime($r['work_end_time']))$e[]='work_end_time must be later than work_start_time';
        if(($r['late_threshold_minutes']??'')!==''&&(!ctype_digit((string)$r['late_threshold_minutes'])||(int)$r['late_threshold_minutes']>1440))$e[]='late_threshold_minutes must be a whole number from 0 to 1440';
        if(($r['emergency_contact_phone']??'')!==''&&trim((string)($r['emergency_contact_name']??''))==='')$e[]='emergency_contact_name is required when emergency_contact_phone is supplied';
        if(($r['emergency_contact_name']??'')!==''&&trim((string)($r['emergency_contact_phone']??''))==='')$e[]='emergency_contact_phone is required when emergency_contact_name is supplied';
        return $e;
    }
    private function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $lines = preg_split('/\R/', $csv);
        $lines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));
        if (!$lines) throw new RuntimeException('CSV is empty.');

        $rawHeaders = str_getcsv(array_shift($lines), ',', '"', '');
        $headerMap = [];
        $headers = [];
        foreach ($rawHeaders as $index => $rawHeader) {
            $header = strtolower(trim((string)$rawHeader));
            if ($header === '') continue; // XLSX/ODS writers may pad the used range with empty columns.
            if ($header === 'department_code') $header = 'department_name'; // accept earlier template spelling too.
            if (isset($headerMap[$header])) throw new RuntimeException("The template contains a duplicate '$header' column.");
            $headerMap[$header] = $index;
            $headers[] = $header;
        }

        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv($line, ',', '"', '');
            $row = [];
            foreach ($headerMap as $header => $index) {
                $row[$header] = trim((string)($values[$index] ?? ''));
            }
            $rows[] = $row;
        }
        return [$headers, $rows];
    }

    private function departmentExists(string $value): bool
    {
        return $this->lookupExists('departments', 'name', $value, "status='active'")
            || $this->lookupExists('departments', 'code', $value, "status='active'");
    }

    private function departmentId(string $value): int
    {
        try { return $this->lookupId('departments', 'name', $value, "status='active'"); }
        catch (RuntimeException) { return $this->lookupId('departments', 'code', $value, "status='active'"); }
    }

    private function canonicalRoleName(string $value): string
    {
        return strcasecmp(trim($value), 'Intern Teacher') === 0 ? 'Intern/Student Teacher' : trim($value);
    }
    private function duplicatesInFile(array $rows): array{ $out=['staff_no'=>[],'email'=>[],'tsc_no'=>[]];foreach(array_keys($out)as$f){$vals=array_map(fn($r)=>strtolower(trim($r[$f]??'')),$rows);$counts=array_count_values(array_filter($vals));$out[$f]=array_keys(array_filter($counts,fn($c)=>$c>1));}return$out;}
    private function batchRows(int $id,string $status): array{$s=$this->db->prepare("SELECT * FROM staff_import_rows WHERE batch_id=? AND status=? ORDER BY `row_number`");$s->execute([$id,$status]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private function lockBatch(int $id): array|false{$s=$this->db->prepare("SELECT * FROM staff_import_batches WHERE id=? FOR UPDATE");$s->execute([$id]);return$s->fetch(PDO::FETCH_ASSOC);}
    private function hasOperationalDependencies(int $sid): bool
    {
        // The department assignment, employment profile, and payroll draft
        // are baseline rows created by the import itself, not later usage.
        foreach (['staff_attendance','payslips','staff_leaves'] as $table) {
            try {
                $stmt=$this->db->prepare("SELECT 1 FROM `$table` WHERE staff_id=? LIMIT 1");
                $stmt->execute([$sid]);
                if ($stmt->fetchColumn()) return true;
            } catch (Throwable) {
                // Older deployments may not have every optional operational table.
            }
        }
        $setup=$this->db->prepare("SELECT 1 FROM staff s JOIN users u ON u.person_id=s.person_id WHERE s.id=? AND (u.password_changed_at IS NOT NULL OR u.profile_completed_at IS NOT NULL OR EXISTS(SELECT 1 FROM user_invitations ui WHERE ui.user_id=u.id AND ui.status='accepted')) LIMIT 1");
        $setup->execute([$sid]);
        return (bool)$setup->fetchColumn();
    }
    private function audit(int $uid,string $action,string $entity,int $eid,array $details=[],string $status='success'):void{\App\API\Includes\FileLogger::write('audit',['type'=>'audit','action'=>$action,'entity'=>$entity,'entity_id'=>$eid,'user_id'=>$uid,'ip'=>$_SERVER['REMOTE_ADDR']??null,'user_agent'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,255),'details'=>$details,'status'=>$status]);}
    private function rows(string $sql, array $params = []):array
    {
        // Placeholders must go through prepare()/execute(). A bare query() with a
        // bound placeholder is a hard PDO error under EMULATE_PREPARES=false.
        if ($params === []) {
            return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function scalar(string $sql, array $params = []): mixed { $stmt = $this->db->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn(); }
    private function assignableSchoolRoles(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id,name,scope
             FROM roles
             WHERE is_active = 1
               AND scope = 'school'
               AND is_system = 0
               AND LOWER(name) <> 'parent'
             ORDER BY name"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function schoolRoleExists(string $name): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM roles WHERE LOWER(name)=LOWER(?) AND is_active=1 AND scope='school' AND is_system=0 LIMIT 1");
        $stmt->execute([trim($name)]);
        return (bool)$stmt->fetchColumn();
    }
    private function schoolRoleId(string $name): int
    {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE LOWER(name)=LOWER(?) AND is_active=1 AND scope='school' AND is_system=0 LIMIT 1");
        $stmt->execute([trim($name)]);
        $id = $stmt->fetchColumn();
        if (!$id) throw new RuntimeException("roles value '$name' was not found");
        return (int)$id;
    }
    private function roleIdsForStaff(int $primaryRoleId,string $primaryRoleName,?int $staffTypeId): array
    {
        $roleIds=[$primaryRoleId];
        $subjectTeacherId = $this->schoolRoleId('Subject Teacher');
        $teachingStaff = false;
        if ($staffTypeId !== null) {
            $type = $this->db->prepare("SELECT 1 FROM staff_types WHERE id=? AND is_active=1 AND LOWER(name)='teaching staff'");
            $type->execute([$staffTypeId]);
            $teachingStaff = (bool)$type->fetchColumn();
        }
        $impliesTeaching = $this->db->prepare('SELECT 1 FROM role_implied_roles WHERE role_id=? AND implies_role_id=? LIMIT 1');
        $impliesTeaching->execute([$primaryRoleId, $subjectTeacherId]);
        if ($teachingStaff || (bool)$impliesTeaching->fetchColumn() || $primaryRoleId === $subjectTeacherId) {
            $roleIds[] = $subjectTeacherId;
        }
        return array_values(array_unique(array_map('intval',$roleIds)));
    }
    private function exists(string $t,string $c,string $v):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupExists(string$t,string$c,string$v,string$w='1=1'):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupId(string$t,string$c,string$v,string$w='1=1'):int{$s=$this->db->prepare("SELECT id FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);$id=$s->fetchColumn();if(!$id)throw new RuntimeException("$t value '$v' was not found");return(int)$id;}
    private function nullableLookup(string$t,string$c,?string$v,string$w='1=1'):?int{return trim((string)$v)===''?null:$this->lookupId($t,$c,$v,$w);}
    private function validDate(string$v):bool{$d=\DateTime::createFromFormat('Y-m-d',$v);return$d&&$d->format('Y-m-d')===$v;}
    private function validTime(string $v):bool{return(bool)preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/',$v);}
    private function supervisorId(string $staffNo):?int{return trim($staffNo)===''?null:$this->lookupId('staff','staff_no',$staffNo,"status='active'");}
    private function professionalIdentifierExists(string $type,string $value):bool{$s=$this->db->prepare('SELECT 1 FROM person_professional_identifiers WHERE identifier_type=? AND LOWER(identifier_value)=LOWER(?) LIMIT 1');$s->execute([$type,trim($value)]);return(bool)$s->fetchColumn();}
    private function normalizedPhone(array $row, string $key): ?string
    {
        $value = trim((string)($row[$key] ?? ''));
        return $value === '' ? null : PhoneNumberNormalizer::normalize($value);
    }
    private function learningAreaIds(string $value): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;|]/', $value) ?: []))));
        if (!$names) return [];
        $ids = [];
        foreach ($names as $name) {
            $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) AND status='active'");
            $stmt->execute([$name]);
            $ids[] = (int)$stmt->fetchColumn();
        }
        return $ids;
    }
    private function categoryBelongsToType(string $category,string $type):bool{$s=$this->db->prepare('SELECT 1 FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE LOWER(sc.category_name)=LOWER(?) AND LOWER(st.name)=LOWER(?) AND sc.is_active=1 AND st.is_active=1 LIMIT 1');$s->execute([trim($category),trim($type)]);return(bool)$s->fetchColumn();}
    private function staffPositionExistsForRow(array $row): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM staff_positions p
            LEFT JOIN staff_types st ON st.id=p.staff_type_id
            LEFT JOIN staff_categories sc ON sc.id=p.staff_category_id
            LEFT JOIN roles selected_role ON LOWER(TRIM(selected_role.name))=LOWER(TRIM(?)) AND selected_role.is_active=1
            WHERE LOWER(TRIM(p.name))=LOWER(TRIM(?)) AND p.is_active=1
              AND (p.staff_type_id IS NULL OR LOWER(TRIM(st.name))=LOWER(TRIM(?)))
              AND (p.staff_category_id IS NULL OR LOWER(TRIM(sc.category_name))=LOWER(TRIM(?)))
              AND (NOT EXISTS (SELECT 1 FROM staff_position_roles spr WHERE spr.position_id=p.id)
                   OR EXISTS (SELECT 1 FROM staff_position_roles spr WHERE spr.position_id=p.id AND spr.role_id=selected_role.id)) LIMIT 1");
        $stmt->execute([(string)($row['role_name'] ?? ''), (string)($row['position'] ?? ''), (string)($row['staff_type'] ?? ''), (string)($row['staff_category'] ?? '')]);
        return (bool)$stmt->fetchColumn();
    }
    private function applyDefaultPosition(array $row): array
    {
        if (trim((string)($row['position'] ?? '')) !== '' || trim((string)($row['role_name'] ?? '')) === '') return $row;
        try {
            $roleId = $this->schoolRoleId($this->canonicalRoleName((string)$row['role_name']));
            $typeId = $this->nullableLookup('staff_types', 'name', $row['staff_type'] ?? null, 'is_active=1');
            $categoryId = $this->categoryId($row['staff_category'] ?? '', $row['staff_type'] ?? '');
            $default = StaffPositionCatalog::defaultForRole($this->db, $roleId, $typeId, $categoryId);
            if ($default) $row['position'] = (string)$default['name'];
        } catch (Throwable $error) {
            // Normal row validation below reports missing/invalid role values.
        }
        return $row;
    }
    private function staffLeadershipPositionExists(string $name): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM leadership_positions lp JOIN leadership_categories lc ON lc.id=lp.leadership_category_id WHERE LOWER(TRIM(lp.name))=LOWER(TRIM(?)) AND lp.is_active=1 AND lc.is_active=1 AND lc.holder_scope IN ('staff','any_person') LIMIT 1");
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    }
    private function learningAreasExist(string $value): bool
    {
        $names = array_values(array_filter(array_map('trim', preg_split('/[;|]/', $value) ?: [])));
        if (!$names) return false;
        foreach ($names as $name) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM learning_areas WHERE LOWER(TRIM(name))=LOWER(TRIM(?)) AND status='active'");
            $stmt->execute([$name]);
            if ((int)$stmt->fetchColumn() !== 1) return false;
        }
        return true;
    }
    private function categoryId(string $category,string $type):?int{if(trim($category)==='')return null;$s=$this->db->prepare('SELECT sc.id FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE LOWER(sc.category_name)=LOWER(?) AND LOWER(st.name)=LOWER(?) AND sc.is_active=1 AND st.is_active=1 LIMIT 1');$s->execute([trim($category),trim($type)]);$id=$s->fetchColumn();if(!$id)throw new RuntimeException("staff_category '$category' does not belong to staff_type '$type'");return(int)$id;}
    private function null(array$r,string$k):mixed{$v=trim((string)($r[$k]??''));return$v===''?null:$v;}
    private function decimal(array$r,string$k):?float{$v=trim((string)($r[$k]??''));return$v===''?null:(float)$v;}
    private function yes(string$v):bool{return in_array(strtolower(trim($v)),['1','yes','true','y'],true);}
    private function generateTemporaryPassword(): string{return 'Kwps-'.substr(bin2hex(random_bytes(4)),0,8).'!';}
    private function csvCell(string $value): string{return '"' . str_replace('"', '""', $value) . '"';}
}
