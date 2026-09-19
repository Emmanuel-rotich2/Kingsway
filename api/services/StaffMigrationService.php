<?php
declare(strict_types=1);

namespace App\API\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PDO;
use RuntimeException;
use Throwable;

/** Existing-staff migration only. Recruitment belongs to Phase 3. */
final class StaffMigrationService
{
    private const REQUIRED = [
        'first_name','last_name','email','phone','department_code',
        'position','employment_date','contract_type','role_name'
    ];
    private const OPTIONAL = [
        'middle_name','staff_no','supervisor_staff_no','gender','date_of_birth','marital_status','staff_type','staff_category',
        'kra_pin','nssf_no','nhif_no','tsc_no','address','bank_name','bank_account',
        'salary','work_start_time','work_end_time','late_threshold_minutes',
        'create_payroll_profile','basic_salary','communication_email','communication_phone',
        'emergency_contact_name','emergency_contact_phone'
    ];
    private const ASSIGNABLE_SCHOOL_ROLES = [
        'Accountant',
        'Boarding Master',
        'Cateress',
        'Chaplain',
        'Class Teacher',
        'Deputy Head - Academic',
        'Deputy Head - Discipline',
        'Director',
        'Driver',
        'Headteacher',
        'Intern/Student Teacher',
        'Uniform Store Manager',
        'Food Store Manager',
        'Librarian',
        'Janitor',
        'Kitchen Staff',
        'School Administrator',
        'Security Staff',
        'Subject Teacher',
        'Talent Development',
    ];
    private const TEACHING_DUTY_ROLES = [
        'Subject Teacher',
        'Class Teacher',
        'Intern/Student Teacher',
        'Headteacher',
        'Deputy Head - Academic',
        'Deputy Head - Discipline',
    ];

    public function __construct(private PDO $db) {}

    public function templateHeaders(): array { return array_merge(self::REQUIRED, self::OPTIONAL); }

    public function templateCsv(): string
    {
        return "\xEF\xBB\xBF"
            . implode(',', array_map([$this, 'csvCell'], $this->templateHeaders()))
            . "\r\n"
            . implode(',', array_map([$this, 'csvCell'], $this->templateSample()))
            . "\r\n";
    }

    public function writeTemplateXlsx(string $path): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = $this->templateHeaders();

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($this->templateSample(), null, 'A2');
        $sheet->setTitle('Staff Import');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:' . $sheet->getHighestColumn() . '1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FFFFD966');

        for ($column = 1, $count = count($headers); $column <= $count; $column++) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $instructions = $spreadsheet->createSheet();
        $instructions->setTitle('Instructions');
        $instructions->fromArray([
            ['Kingsway existing-staff import'],
            ['Delete the example row in Staff Import, then enter one staff member per row. Do not rename, remove, or add columns.'],
            ['Yellow headers are required. Other headers are optional unless made conditionally required below.'],
            ['Dates', 'YYYY-MM-DD'],
            ['Times', 'HH:MM or HH:MM:SS; end time must be later than start time'],
            ['Contract type', 'permanent, contract, or temporary'],
            ['Payroll', 'Use yes/no. If yes, salary/basic_salary, bank_name, bank_account, kra_pin, nssf_no, and nhif_no are required.'],
            ['Teaching roles', 'tsc_no is required and must be unique.'],
            ['Supervisor', 'Use the staff_no of an existing active supervisor.'],
            ['Staff number', 'Leave blank to generate it automatically.'],
            ['Username', 'Generated automatically from the login email; do not add a username column.'],
            ['Import behavior', 'The entire batch must validate and commits atomically.'],
        ], null, 'A1');
        $instructions->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $instructions->getColumnDimension('A')->setWidth(24);
        $instructions->getColumnDimension('B')->setWidth(110);
        $instructions->getStyle('A1:B20')->getAlignment()->setWrapText(true)->setVertical('top');

        // The data worksheet must be the sheet users see first. Importing also
        // addresses it by name, so workbook active-sheet state can never turn
        // the Instructions sheet into staff data.
        $spreadsheet->setActiveSheetIndexByName('Staff Import');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function spreadsheetToCsv(string $path): string
    {
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
            fputcsv($handle, array_map(fn($value) => trim((string)$value), $row));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        if ($csv === false || trim($csv) === '') {
            throw new RuntimeException('Uploaded spreadsheet is empty.');
        }

        return $csv;
    }

    public function referenceData(): array
    {
        return [
            'departments' => $this->rows("SELECT id,code,name FROM departments WHERE status='active' ORDER BY name"),
            'roles' => $this->assignableSchoolRoles(),
            'staff_types' => $this->rows("SELECT id,name FROM staff_types WHERE is_active=1 ORDER BY name"),
            'staff_categories' => $this->rows("SELECT sc.id,sc.category_name AS name,st.name AS staff_type FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE sc.is_active=1 ORDER BY st.name,sc.category_name"),
            'contracts' => ['permanent','contract','temporary'],
            'genders' => ['male','female','other'],
            'marital_statuses' => ['single','married','divorced','widowed','separated','unknown'],
            'supervisors' => $this->rows("SELECT s.staff_no, CONCAT_WS(' ',p.first_name,p.middle_name,p.last_name) AS name FROM staff s JOIN persons p ON p.id=s.person_id WHERE s.status='active' ORDER BY p.first_name,p.last_name"),
        ];
    }

    public function stage(string $originalName, string $storedPath, string $csv, int $actorId): array
    {
        [$headers, $rows] = $this->parseCsv($csv);
        $missing = array_values(array_diff(self::REQUIRED, $headers));
        $unknown = array_values(array_diff($headers, $this->templateHeaders()));
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
                $errors = $this->validateRow($row, $i + 2, $duplicateInFile);
                if (!$errors && !$missing && !$unknown) $valid++;
                $stmt = $this->db->prepare("INSERT INTO staff_import_rows
                    (batch_id,row_number,row_data,validation_errors,status,created_at,updated_at)
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
                ->execute([$valid,$invalid,$status,json_encode(['missing_columns'=>$missing,'unknown_columns'=>$unknown]),$batchId]);
            $this->audit($actorId,'staff_import_validated','staff_import_batch',$batchId,['valid'=>$valid,'invalid'=>$invalid,'missing'=>$missing,'unknown'=>$unknown]);
            $this->db->commit();
            return $this->batchDetail($batchId);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function commit(int $batchId, int $actorId): array
    {
        $batch = $this->lockBatch($batchId);
        if (!$batch) throw new RuntimeException('Import batch not found.');
        if ($batch['status'] !== 'validated') throw new RuntimeException('Only a fully validated batch can be imported.');
        $rows = $this->batchRows($batchId, 'valid');
        if (!$rows || count($rows)!==(int)$batch['total_rows']) throw new RuntimeException('Validated row count no longer matches the batch. Revalidate the file.');

        $created = [];
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE staff_import_batches SET status='processing',started_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$batchId]);
            foreach ($rows as $rowRecord) {
                $row = json_decode($rowRecord['row_data'], true, 512, JSON_THROW_ON_ERROR);
                $created[] = $this->createStaffGraph($row, $batchId, (int)$rowRecord['id'], $actorId);
            }
            $this->db->prepare("UPDATE staff_import_batches SET status='completed',imported_rows=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([count($created),$batchId]);
            $this->audit($actorId,'staff_import_completed','staff_import_batch',$batchId,['created_count'=>count($created)]);
            $this->db->commit();
            try {
                $this->processEmailQueue(count($created));
            } catch (Throwable $mailError) {
                \App\API\Services\Logger::legacyError('Staff import invitation delivery failed: '.$mailError->getMessage());
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->db->prepare("UPDATE staff_import_batches SET status='failed',failure_message=?,updated_at=NOW() WHERE id=?")
                ->execute([mb_substr($e->getMessage(),0,1000),$batchId]);
            $this->audit($actorId,'staff_import_failed','staff_import_batch',$batchId,['error'=>$e->getMessage()],'failure');
            throw $e;
        }
        return ['batch'=>$this->batchDetail($batchId),'created'=>$created];
    }

    public function rollback(int $batchId, int $actorId): array
    {
        $batch = $this->lockBatch($batchId);
        if (!$batch || $batch['status']!=='completed') throw new RuntimeException('Only a completed batch can be rolled back.');
        $rows = $this->batchRows($batchId, 'created');
        foreach ($rows as $r) {
            if ($this->hasOperationalDependencies((int)$r['staff_id'])) throw new RuntimeException('Rollback is blocked because one or more imported staff members already have operational records.');
        }
        $this->db->beginTransaction();
        try {
            foreach (array_reverse($rows) as $r) {
                $uid=(int)$r['user_id']; $sid=(int)$r['staff_id'];
                $pidStmt=$this->db->prepare("SELECT person_id FROM staff WHERE id=?");$pidStmt->execute([$sid]);$pid=(int)$pidStmt->fetchColumn();
                foreach (['staff_attendance_profiles','staff_payroll_profiles','staff_employment_profiles'] as $table) {
                    $this->db->prepare("DELETE FROM `$table` WHERE staff_id=?")->execute([$sid]);
                }
                $this->db->prepare("DELETE FROM staff_department_assignments WHERE staff_id=?")->execute([$sid]);
                $this->db->prepare("DELETE FROM user_invitations WHERE user_id=?")->execute([$uid]);
                $this->db->prepare("DELETE FROM outbound_messages WHERE user_id=?")->execute([$uid]);
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

    public function resendInvitation(int $userId, int $actorId, string $baseUrl): array
    {
        $stmt=$this->db->prepare("SELECT u.id,p.email,u.username,p.first_name,p.last_name,s.id staff_id FROM users u JOIN persons p ON p.id=u.person_id JOIN staff s ON s.person_id=p.id WHERE u.id=? LIMIT 1");
        $stmt->execute([$userId]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user) throw new RuntimeException('Imported staff user not found.');
        $token=$this->createInvitation((int)$user['id'],(int)$user['staff_id'],$user['email'],$actorId);
        $url=rtrim($baseUrl,'/').'/reset_default_password.php?token='.rawurlencode($token);
        $this->queueEmail((int)$user['id'],$user['email'],'staff_account_invitation','Your Kingsway account is ready',[
            'name'=>trim($user['first_name'].' '.$user['last_name']),'username'=>$user['username'],'activation_url'=>$url
        ]);
        $this->audit($actorId,'staff_invitation_resent','user',(int)$user['id']);
        return ['user_id'=>(int)$user['id'],'queued'=>true];
    }

    public function processEmailQueue(int $limit=20): array
    {
        $stmt=$this->db->prepare("SELECT * FROM outbound_messages WHERE channel='email' AND status IN ('queued','retry') AND next_attempt_at<=NOW() ORDER BY id LIMIT ?");
        $stmt->bindValue(1,max(1,min(100,$limit)),PDO::PARAM_INT); $stmt->execute();
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $service = new MessageService($this->db);
        $sent = 0;
        $failed = 0;

        foreach ($messages as $message) {
            $this->db->prepare("UPDATE outbound_messages SET status='processing', attempts=attempts+1, updated_at=NOW() WHERE id=?")
                ->execute([(int)$message['id']]);
            try {
                $payload = json_decode($message['payload_json'], true, 512, JSON_THROW_ON_ERROR);
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
        $stmt=$this->db->prepare("SELECT id,row_number,row_data,validation_errors,status,staff_id,user_id FROM staff_import_rows WHERE batch_id=? ORDER BY row_number");
        $stmt->execute([$batchId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$r){$r['data']=json_decode($r['row_data'],true);$r['errors']=$r['validation_errors']?json_decode($r['validation_errors'],true):[];unset($r['row_data'],$r['validation_errors']);}
        $summary=$batch['validation_summary']?json_decode($batch['validation_summary'],true):[];
        return ['batch'=>$batch,'rows'=>$rows,'summary'=>$summary,'can_commit'=>$batch['status']==='validated','can_rollback'=>$batch['status']==='completed'];
    }

    public function onboardingForUser(int $userId): array
    {
        $stmt=$this->db->prepare("
            SELECT
                s.id AS staff_id, s.staff_no,
                p.first_name, p.last_name,
                p.photo_url AS profile_pic_url,
                COALESCE(p.phone,'') AS phone,
                p.gender,
                p.dob AS date_of_birth,
                s.position, s.employment_date, s.contract_type,
                sda.department_id, s.supervisor_id,
                spp.kra_pin, spp.nssf_no, spp.nhif_no,
                s.bank_name, s.bank_account,
                s.salary, COALESCE((SELECT identifier_value FROM person_professional_identifiers WHERE person_id=p.id AND identifier_type='tsc' ORDER BY is_primary DESC,id DESC LIMIT 1),'') AS tsc_no,
                sap.work_start_time, sap.work_end_time, sap.late_threshold_minutes,
                s.status, s.staff_type_id, s.staff_category_id,
                NULL AS documents_folder, s.created_at, s.updated_at,
                COALESCE(p.email, '') AS communication_email,
                COALESCE((SELECT contact_value FROM person_contact_points WHERE person_id=p.id AND channel='phone' AND purpose='communication' LIMIT 1), p.phone, '') AS communication_phone,
                COALESCE((SELECT address_line FROM person_addresses WHERE person_id=p.id AND address_type='residential' AND valid_to IS NULL ORDER BY is_primary DESC, id DESC LIMIT 1), '') AS address,
                COALESCE((SELECT marital_status FROM person_marital_statuses WHERE person_id=p.id AND valid_to IS NULL ORDER BY id DESC LIMIT 1), '') AS marital_status,
                COALESCE((SELECT name FROM emergency_contacts WHERE person_id=p.id ORDER BY id LIMIT 1), '') AS emergency_contact_name,
                COALESCE((SELECT phone FROM emergency_contacts WHERE person_id=p.id ORDER BY id LIMIT 1), '') AS emergency_contact_phone,
                CASE WHEN u.password_changed_at IS NOT NULL THEN 1 ELSE 0 END AS password_completed,
                CASE WHEN p.phone IS NOT NULL AND p.gender IS NOT NULL AND p.dob IS NOT NULL
                    AND EXISTS (SELECT 1 FROM person_addresses pa WHERE pa.person_id=p.id AND pa.address_type='residential' AND pa.valid_to IS NULL)
                    AND EXISTS (SELECT 1 FROM person_marital_statuses pm WHERE pm.person_id=p.id AND pm.valid_to IS NULL) THEN 1 ELSE 0 END AS profile_completed,
                CASE WHEN p.email IS NOT NULL AND p.phone IS NOT NULL THEN 1 ELSE 0 END AS communication_completed,
                CASE WHEN p.phone IS NOT NULL AND p.gender IS NOT NULL AND p.dob IS NOT NULL
                    AND EXISTS (SELECT 1 FROM person_addresses pa WHERE pa.person_id=p.id AND pa.address_type='residential' AND pa.valid_to IS NULL)
                    AND EXISTS (SELECT 1 FROM person_marital_statuses pm WHERE pm.person_id=p.id AND pm.valid_to IS NULL) THEN 'completed' ELSE 'invited' END AS onboarding_status,
                d.name  AS department_name,
                st.name AS staff_type_name,
                sc.category_name AS staff_category_name,
                CONCAT(sp.first_name, ' ', sp.last_name) AS supervisor_name
            FROM users u
            JOIN persons p ON p.id = u.person_id
            JOIN staff s ON s.person_id = p.id
            LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND (sda.effective_to IS NULL OR sda.effective_to >= CURDATE())
            LEFT JOIN departments d ON d.id = sda.department_id
            LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = s.id
            LEFT JOIN staff_attendance_profiles sap ON sap.staff_id = s.id
            LEFT JOIN staff_types st ON st.id = s.staff_type_id
            LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
            LEFT JOIN staff su ON su.id = s.supervisor_id
            LEFT JOIN persons sp ON sp.id = su.person_id
            WHERE u.id = ?
        ");
        $stmt->execute([$userId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)throw new RuntimeException('Staff onboarding profile not found.');
        $qualificationStmt=$this->db->prepare("SELECT id,qualification_level,source,verification_status,title,institution,year_obtained,description,document_url FROM staff_qualifications WHERE staff_id=? ORDER BY year_obtained DESC,id DESC");
        $qualificationStmt->execute([(int)$row['staff_id']]);
        $row['qualification_claims']=$qualificationStmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function completeProfile(int $userId,array $data): array
    {
        $stmt=$this->db->prepare("SELECT s.id AS sid,p.id AS pid FROM staff s JOIN persons p ON p.id=s.person_id JOIN users u ON u.person_id=s.person_id WHERE u.id=?");$stmt->execute([$userId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);if(!$row)throw new RuntimeException('Staff profile not found.');
        $sid=(int)$row['sid'];$pid=(int)$row['pid'];
        foreach(['phone','address','gender','marital_status','date_of_birth','communication_email'] as $f){if(empty($data[$f]))throw new RuntimeException("$f is required.");}
        $this->db->beginTransaction();try{
            $this->db->prepare("UPDATE persons SET phone=?,gender=?,dob=?,email=NULLIF(?, '') WHERE id=?")
                ->execute([$data['phone'],$data['gender'],$data['date_of_birth'],$data['communication_email']??'',$pid]);
            $this->db->prepare("INSERT INTO person_addresses (person_id,address_type,address_line,is_primary,valid_from) VALUES (?, 'residential', ?, 1, CURDATE()) ON DUPLICATE KEY UPDATE address_line=VALUES(address_line), is_primary=1, valid_to=NULL, updated_at=NOW()")
                ->execute([$pid, trim((string) $data['address'])]);
            $this->db->prepare("UPDATE person_addresses SET valid_to=CURDATE() WHERE person_id=? AND address_type='residential' AND valid_to IS NULL AND address_line<>? AND valid_from<CURDATE()")
                ->execute([$pid, trim((string) $data['address'])]);
            $this->db->prepare("UPDATE person_marital_statuses SET valid_to=CURDATE() WHERE person_id=? AND valid_to IS NULL AND marital_status<>?")
                ->execute([$pid, $data['marital_status']]);
            $this->db->prepare("INSERT INTO person_marital_statuses (person_id,marital_status,valid_from) VALUES (?, ?, CURDATE()) ON DUPLICATE KEY UPDATE marital_status=VALUES(marital_status), valid_to=NULL")
                ->execute([$pid, $data['marital_status']]);
            if (array_key_exists('communication_phone', $data)) {
                $this->db->prepare("DELETE FROM person_contact_points WHERE person_id=? AND channel='phone' AND purpose='communication'")->execute([$pid]);
            }
            if (!empty($data['communication_phone'])) {
                $this->db->prepare("INSERT INTO person_contact_points (person_id,channel,purpose,contact_value,is_primary) VALUES (?, 'phone', 'communication', ?, 1) ON DUPLICATE KEY UPDATE contact_value=VALUES(contact_value), is_primary=1, updated_at=NOW()")
                    ->execute([$pid, trim((string) $data['communication_phone'])]);
            }
            if (array_key_exists('emergency_contact_name', $data)) {
                $this->db->prepare("DELETE FROM emergency_contacts WHERE person_id=?")->execute([$pid]);
            }
            if(!empty($data['emergency_contact_name'])){
                $this->db->prepare("INSERT INTO emergency_contacts(person_id,name,phone,created_at) VALUES(?,?,?,NOW())")
                    ->execute([$pid,$data['emergency_contact_name'],$data['emergency_contact_phone']??null]);
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
                    $description = trim((string)($qualification['description'] ?? '')) ?: null;
                    $documentUrl = trim((string)($qualification['document_url'] ?? '')) ?: null;
                    $duplicateStmt=$this->db->prepare("SELECT id FROM staff_qualifications WHERE staff_id=? AND source='self_reported' AND verification_status='pending' AND qualification_level=? AND title=? AND institution=? AND (year_obtained <=> ?) AND (description <=> ?) AND (document_url <=> ?) LIMIT 1");
                    $duplicateStmt->execute([$sid,$level,$title,$institution,$year ?: null,$description,$documentUrl]);
                    if (!$duplicateStmt->fetchColumn()) $qualificationStmt->execute([$sid, $legacyType, $level, $userId, $title, $institution, $year ?: null, $description, $documentUrl]);
                }
            }
            $this->db->prepare("UPDATE users SET profile_completed_at=NOW() WHERE id=?")->execute([$userId]);
            $this->audit($userId,'staff_profile_completed','staff',$sid);$this->db->commit();return $this->onboardingForUser($userId);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
    }

    private function createStaffGraph(array $r,int $batchId,int $rowId,int $actorId): array
    {
        $dept=$this->lookupId('departments','code',$r['department_code'],"status='active'");
        $role=$this->schoolRoleId($r['role_name']);
        $type=$this->nullableLookup('staff_types','name',$r['staff_type']??null,"is_active=1");
        $cat=$this->categoryId($r['staff_category']??'',$r['staff_type']??'');
        $roleIds=$this->roleIdsForStaff($role,$r['role_name'],$type);
        $username=UsernameService::generate($this->db,$r['email'],$r['first_name'],$r['last_name']);
        $temporary=$this->generateTemporaryPassword();
        $this->db->prepare("INSERT INTO persons(first_name,middle_name,last_name,dob,gender,email,phone) VALUES(?,?,?,?,?,?,?)")
            ->execute([$r['first_name'],$this->null($r,'middle_name'),$r['last_name'],$this->null($r,'date_of_birth'),$this->null($r,'gender'),strtolower($r['email']),$r['phone']]);
        $pid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO users(person_id,username,password_hash,status,force_password_change,created_at,updated_at) VALUES(?,?,?,'active',1,NOW(),NOW())")
            ->execute([$pid,$username,password_hash($temporary,PASSWORD_DEFAULT)]);
        $uid=(int)$this->db->lastInsertId();
        $roleStmt=$this->db->prepare("INSERT INTO user_roles(user_id,role_id,created_at) VALUES(?,?,NOW())");
        foreach($roleIds as $roleId)$roleStmt->execute([$uid,$roleId]);
        // Auto-generate staff_no when blank; validate format when provided.
        $staffNoSvc = new StaffNumberService($this->db);
        $staffNo = trim((string)($r['staff_no'] ?? ''));
        if ($staffNo === '') {
            $staffNo = $staffNoSvc->generate();
        } elseif (!$staffNoSvc->isValid($staffNo)) {
            throw new RuntimeException("Row: staff_no '$staffNo' does not match the configured format");
        }
        $supervisorId=$this->supervisorId($r['supervisor_staff_no']??'');
        $this->db->prepare("INSERT INTO staff(person_id,staff_type_id,staff_category_id,staff_no,position,contract_type,employment_date,status,supervisor_id,salary,bank_name,bank_account) VALUES(?,?,?,?,?,?,?,?,'active',?,?,?,?)")
            ->execute([$pid,$type,$cat,$staffNo,$r['position'],strtolower($r['contract_type']),$r['employment_date'],$supervisorId,$this->decimal($r,'salary'),$this->null($r,'bank_name'),$this->null($r,'bank_account')]);
        $sid=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO staff_department_assignments(staff_id,department_id,role,effective_from,effective_to,created_at) VALUES(?,?,?,?,?,NULL,NOW())")
            ->execute([$sid,$dept,$r['position'],$r['employment_date']]);
        $this->db->prepare("INSERT INTO staff_employment_profiles(staff_id,department_id,position,employment_date,contract_type,status,created_at,updated_at) VALUES(?,?,?,?,?,'active',NOW(),NOW())")
            ->execute([$sid,$dept,$r['position'],$r['employment_date'],strtolower($r['contract_type'])]);
        $this->db->prepare("INSERT INTO staff_attendance_profiles(staff_id,work_start_time,work_end_time,late_threshold_minutes,is_active,created_at,updated_at) VALUES(?,?,?,?,1,NOW(),NOW())")
            ->execute([$sid,$r['work_start_time']?:'08:00:00',$r['work_end_time']?:'17:00:00',(int)($r['late_threshold_minutes']?:15)]);
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
        if($this->yes($r['create_payroll_profile']??'no')){
            $this->db->prepare("INSERT INTO staff_payroll_profiles(staff_id,basic_salary,bank_name,bank_account,kra_pin,nssf_no,nhif_no,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,'active',NOW(),NOW())")
                ->execute([$sid,$this->decimal($r,'basic_salary')??$this->decimal($r,'salary')??0,$this->null($r,'bank_name'),$this->null($r,'bank_account'),$this->null($r,'kra_pin'),$this->null($r,'nssf_no'),$this->null($r,'nhif_no')]);
        }
        if(!empty($r['communication_email']??'')){
            $this->db->prepare("INSERT INTO person_contact_points(person_id,channel,purpose,contact_value,is_primary) VALUES(?,'email','communication',?,1)")
                ->execute([$pid,strtolower(trim($r['communication_email']))]);
        }
        if(!empty($r['communication_phone']??'')){
            $this->db->prepare("INSERT INTO person_contact_points(person_id,channel,purpose,contact_value,is_primary) VALUES(?,'phone','communication',?,1)")
                ->execute([$pid,trim($r['communication_phone'])]);
        }
        if(!empty($r['emergency_contact_name']??'')){
            $this->db->prepare("INSERT INTO emergency_contacts(person_id,name,phone,created_at) VALUES(?,?,?,NOW())")
                ->execute([$pid,$r['emergency_contact_name'],$r['emergency_contact_phone']??null]);
        }
        $token=$this->createInvitation($uid,$sid,$r['email'],$actorId);
        $baseUrl=(defined('BASE_URL')?BASE_URL:(defined('APP_URL')?APP_URL:''));$url=rtrim($baseUrl,'/').'/reset_default_password.php?token='.rawurlencode($token);
        $this->queueEmail($uid,$r['email'],'staff_account_invitation','Your Kingsway account is ready',[
            'name'=>$r['first_name'].' '.$r['last_name'],
            'username'=>$username,
            'default_password'=>$temporary,
            'temporary_password'=>$temporary,
            'activation_url'=>$url,
            'setup_url'=>$url,
            'login_url'=>rtrim($baseUrl,'/').'/index.php',
            'expires_hours'=>72,
        ]);
        $this->db->prepare("UPDATE staff_import_rows SET staff_id=?,user_id=?,status='created',updated_at=NOW() WHERE id=?")->execute([$sid,$uid,$rowId]);
        return ['staff_id'=>$sid,'user_id'=>$uid,'staff_no'=>$staffNo,'username'=>$username,'email'=>$r['email'],'invitation_queued'=>true];
    }

    private function createInvitation(int $uid,int $sid,string $email,int $actor): string
    {
        $this->db->prepare("UPDATE user_invitations SET status='revoked',revoked_at=NOW() WHERE user_id=? AND status='pending'")->execute([$uid]);
        $token=bin2hex(random_bytes(32));
        $this->db->prepare("INSERT INTO user_invitations(user_id,staff_id,email,token_hash,status,expires_at,created_by,created_at,updated_at) VALUES(?,?,?,?,'pending',DATE_ADD(NOW(),INTERVAL 72 HOUR),?,NOW(),NOW())")
            ->execute([$uid,$sid,strtolower($email),hash('sha256',$token),$actor]);return $token;
    }
    private function queueEmail(int $uid,string $to,string $template,string $subject,array $payload): void
    {
        $this->db->prepare("INSERT INTO outbound_messages(user_id,channel,recipient,template_key,subject,payload_json,status,attempts,next_attempt_at,created_at,updated_at) VALUES(?,'email',?,?,?,?, 'queued',0,NOW(),NOW(),NOW())")
            ->execute([$uid,strtolower($to),$template,$subject,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    }
    private function renderStaffInvitationEmail(array $payload): string
    {
        $name=htmlspecialchars((string)($payload['name']??'Staff member'),ENT_QUOTES,'UTF-8');
        $username=htmlspecialchars((string)($payload['username']??''),ENT_QUOTES,'UTF-8');
        $password=htmlspecialchars((string)($payload['default_password']??$payload['temporary_password']??''),ENT_QUOTES,'UTF-8');
        $setup=htmlspecialchars((string)($payload['setup_url']??$payload['activation_url']??''),ENT_QUOTES,'UTF-8');
        $login=htmlspecialchars((string)($payload['login_url']??''),ENT_QUOTES,'UTF-8');
        return "<p>Dear {$name},</p>"
            . "<p>Welcome to Kingsway Preparatory School. Your staff account is ready.</p>"
            . "<p><strong>Username:</strong> {$username}" . ($password !== '' ? "<br><strong>Temporary password:</strong> {$password}" : '') . "</p>"
            . "<p><strong>Before you can open your dashboard, please complete these steps:</strong></p>"
            . "<ol><li>Open the secure setup link below.</li><li>Create a new private password. Do not continue using the temporary password.</li><li>Sign in and complete every required staff-profile field.</li><li>After your profile is complete, the system will take you to your role dashboard.</li></ol>"
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
        if(($r['department_code']??'')&&!$this->lookupExists('departments','code',$r['department_code'],"status='active'"))$e[]='department_code was not found or inactive';
        if(($r['role_name']??'')&&!$this->schoolRoleExists($r['role_name']))$e[]='role_name was not found, inactive, or not an assignable school role';
        if(($r['staff_type']??'')&&!$this->lookupExists('staff_types','name',$r['staff_type'],"is_active=1"))$e[]='staff_type was not found';
        if(($r['staff_category']??'')&&!$this->lookupExists('staff_categories','category_name',$r['staff_category'],"is_active=1"))$e[]='staff_category was not found';
        if(($r['staff_category']??'')!==''&&trim((string)($r['staff_type']??''))==='')$e[]='staff_type is required when staff_category is supplied';
        if(($r['staff_type']??'')!==''&&($r['staff_category']??'')!==''&&!$this->categoryBelongsToType($r['staff_category'],$r['staff_type']))$e[]='staff_category does not belong to staff_type';
        if(($r['supervisor_staff_no']??'')!==''&&!$this->lookupExists('staff','staff_no',$r['supervisor_staff_no'],"status='active'"))$e[]='supervisor_staff_no was not found or inactive';
        foreach(['salary','basic_salary'] as $amount){if(($r[$amount]??'')!==''&&(!is_numeric($r[$amount])||(float)$r[$amount]<0))$e[]="$amount must be a non-negative number";}
        foreach(['work_start_time','work_end_time'] as $time){if(($r[$time]??'')!==''&&!$this->validTime($r[$time]))$e[]="$time must be HH:MM or HH:MM:SS";}
        if(($r['work_start_time']??'')!==''&&($r['work_end_time']??'')!==''&&strtotime($r['work_start_time'])>=strtotime($r['work_end_time']))$e[]='work_end_time must be later than work_start_time';
        if(($r['late_threshold_minutes']??'')!==''&&(!ctype_digit((string)$r['late_threshold_minutes'])||(int)$r['late_threshold_minutes']>1440))$e[]='late_threshold_minutes must be a whole number from 0 to 1440';
        $payrollValue=strtolower(trim((string)($r['create_payroll_profile']??'')));
        if($payrollValue!==''&&!in_array($payrollValue,['yes','no','y','n','true','false','1','0'],true))$e[]='create_payroll_profile must be yes or no';
        if($this->yes($payrollValue)){
            if(($this->decimal($r,'basic_salary')??$this->decimal($r,'salary')??0)<=0)$e[]='basic_salary or salary must be greater than zero when creating payroll';
            foreach(['bank_name','bank_account','kra_pin','nssf_no','nhif_no'] as $field)if(trim((string)($r[$field]??''))==='')$e[]="$field is required when creating payroll";
        }
        if(($this->isTeachingDutyRole((string)($r['role_name']??''))||strtolower(trim((string)($r['staff_type']??'')))==='teaching')&&trim((string)($r['tsc_no']??''))==='')$e[]='tsc_no is required for teaching staff';
        if(($r['emergency_contact_phone']??'')!==''&&trim((string)($r['emergency_contact_name']??''))==='')$e[]='emergency_contact_name is required when emergency_contact_phone is supplied';
        if(($r['emergency_contact_name']??'')!==''&&trim((string)($r['emergency_contact_phone']??''))==='')$e[]='emergency_contact_phone is required when emergency_contact_name is supplied';
        return $e;
    }
    private function parseCsv(string $csv): array
    {
        $csv=preg_replace('/^\xEF\xBB\xBF/','',$csv);$lines=preg_split('/\R/',$csv);$lines=array_values(array_filter($lines,fn($l)=>trim($l)!==''));if(!$lines)throw new RuntimeException('CSV is empty.');
        $headers=array_map(fn($v)=>strtolower(trim($v)),str_getcsv(array_shift($lines)));$rows=[];
        foreach($lines as $line){$v=str_getcsv($line);$v=array_pad($v,count($headers),'');$rows[]=array_combine($headers,array_slice($v,0,count($headers)));}
        return [$headers,$rows];
    }
    private function duplicatesInFile(array $rows): array{ $out=['staff_no'=>[],'email'=>[],'tsc_no'=>[]];foreach(array_keys($out)as$f){$vals=array_map(fn($r)=>strtolower(trim($r[$f]??'')),$rows);$counts=array_count_values(array_filter($vals));$out[$f]=array_keys(array_filter($counts,fn($c)=>$c>1));}return$out;}
    private function batchRows(int $id,string $status): array{$s=$this->db->prepare("SELECT * FROM staff_import_rows WHERE batch_id=? AND status=? ORDER BY row_number");$s->execute([$id,$status]);return$s->fetchAll(PDO::FETCH_ASSOC);}
    private function lockBatch(int $id): array|false{$s=$this->db->prepare("SELECT * FROM staff_import_batches WHERE id=? FOR UPDATE");$s->execute([$id]);return$s->fetch(PDO::FETCH_ASSOC);}
    private function hasOperationalDependencies(int $sid): bool{foreach(['staff_attendance','payslips','staff_leaves','staff_department_assignments']as$t){try{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE staff_id=? LIMIT 1");$s->execute([$sid]);if($s->fetchColumn())return true;}catch(Throwable){}}return false;}
    private function audit(int $uid,string $action,string $entity,int $eid,array $details=[],string $status='success'):void{\App\API\Includes\FileLogger::write('audit',['type'=>'audit','action'=>$action,'entity'=>$entity,'entity_id'=>$eid,'user_id'=>$uid,'ip'=>$_SERVER['REMOTE_ADDR']??null,'user_agent'=>substr($_SERVER['HTTP_USER_AGENT']??'',0,255),'details'=>$details,'status'=>$status]);}
    private function rows(string $sql):array{return$this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
    private function assignableSchoolRoles(): array
    {
        $placeholders = implode(',', array_fill(0, count(self::ASSIGNABLE_SCHOOL_ROLES), '?'));
        $stmt = $this->db->prepare(
            "SELECT id,name,scope
             FROM roles
             WHERE is_active = 1
               AND scope = 'school'
               AND is_system = 0
               AND name IN ($placeholders)
             ORDER BY name"
        );
        $stmt->execute(self::ASSIGNABLE_SCHOOL_ROLES);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function schoolRoleExists(string $name): bool
    {
        if (!$this->allowedSchoolRoleName($name)) return false;
        $stmt = $this->db->prepare("SELECT 1 FROM roles WHERE LOWER(name)=LOWER(?) AND is_active=1 AND scope='school' AND is_system=0 LIMIT 1");
        $stmt->execute([trim($name)]);
        return (bool)$stmt->fetchColumn();
    }
    private function schoolRoleId(string $name): int
    {
        if (!$this->allowedSchoolRoleName($name)) throw new RuntimeException("roles value '$name' was not found");
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE LOWER(name)=LOWER(?) AND is_active=1 AND scope='school' AND is_system=0 LIMIT 1");
        $stmt->execute([trim($name)]);
        $id = $stmt->fetchColumn();
        if (!$id) throw new RuntimeException("roles value '$name' was not found");
        return (int)$id;
    }
    private function roleIdsForStaff(int $primaryRoleId,string $primaryRoleName,?int $staffTypeId): array
    {
        $roleIds=[$primaryRoleId];
        if($staffTypeId===1||$this->isTeachingDutyRole($primaryRoleName)){
            $roleIds[]=$this->schoolRoleId('Subject Teacher');
        }
        return array_values(array_unique(array_map('intval',$roleIds)));
    }
    private function isTeachingDutyRole(string $name): bool
    {
        $normalized=strtolower(trim($name));
        foreach(self::TEACHING_DUTY_ROLES as $role){
            if($normalized===strtolower($role))return true;
        }
        return false;
    }
    private function allowedSchoolRoleName(string $name): bool
    {
        $normalized = strtolower(trim($name));
        foreach (self::ASSIGNABLE_SCHOOL_ROLES as $allowed) {
            if ($normalized === strtolower($allowed)) return true;
        }
        return false;
    }
    private function exists(string $t,string $c,string $v):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupExists(string$t,string$c,string$v,string$w='1=1'):bool{$s=$this->db->prepare("SELECT 1 FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);return(bool)$s->fetchColumn();}
    private function lookupId(string$t,string$c,string$v,string$w='1=1'):int{$s=$this->db->prepare("SELECT id FROM `$t` WHERE LOWER(`$c`)=LOWER(?) AND $w LIMIT 1");$s->execute([trim($v)]);$id=$s->fetchColumn();if(!$id)throw new RuntimeException("$t value '$v' was not found");return(int)$id;}
    private function nullableLookup(string$t,string$c,?string$v,string$w='1=1'):?int{return trim((string)$v)===''?null:$this->lookupId($t,$c,$v,$w);}
    private function validDate(string$v):bool{$d=\DateTime::createFromFormat('Y-m-d',$v);return$d&&$d->format('Y-m-d')===$v;}
    private function validTime(string $v):bool{return(bool)preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/',$v);}
    private function supervisorId(string $staffNo):?int{return trim($staffNo)===''?null:$this->lookupId('staff','staff_no',$staffNo,"status='active'");}
    private function professionalIdentifierExists(string $type,string $value):bool{$s=$this->db->prepare('SELECT 1 FROM person_professional_identifiers WHERE identifier_type=? AND LOWER(identifier_value)=LOWER(?) LIMIT 1');$s->execute([$type,trim($value)]);return(bool)$s->fetchColumn();}
    private function categoryBelongsToType(string $category,string $type):bool{$s=$this->db->prepare('SELECT 1 FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE LOWER(sc.category_name)=LOWER(?) AND LOWER(st.name)=LOWER(?) AND sc.is_active=1 AND st.is_active=1 LIMIT 1');$s->execute([trim($category),trim($type)]);return(bool)$s->fetchColumn();}
    private function categoryId(string $category,string $type):?int{if(trim($category)==='')return null;$s=$this->db->prepare('SELECT sc.id FROM staff_categories sc JOIN staff_types st ON st.id=sc.staff_type_id WHERE LOWER(sc.category_name)=LOWER(?) AND LOWER(st.name)=LOWER(?) AND sc.is_active=1 AND st.is_active=1 LIMIT 1');$s->execute([trim($category),trim($type)]);$id=$s->fetchColumn();if(!$id)throw new RuntimeException("staff_category '$category' does not belong to staff_type '$type'");return(int)$id;}
    private function null(array$r,string$k):mixed{$v=trim((string)($r[$k]??''));return$v===''?null:$v;}
    private function decimal(array$r,string$k):?float{$v=trim((string)($r[$k]??''));return$v===''?null:(float)$v;}
    private function yes(string$v):bool{return in_array(strtolower(trim($v)),['1','yes','true','y'],true);}
    private function generateTemporaryPassword(): string{return 'Kwps-'.substr(bin2hex(random_bytes(4)),0,8).'!';}
    private function templateSample(): array
    {
        return [
            'Jane', 'Wanjiku', 'jane.wanjiku@example.com', '0712345678',
            'ACAD', 'Class Teacher', '2024-01-08', 'permanent', 'Class Teacher',
            'Njeri', '', '', 'female', '1993-02-10', 'single', 'Teaching Staff', 'Lower Primary Teacher',
            'A123456789B', 'NSSF001', 'NHIF001', 'TSC001', 'Nairobi',
            'KCB', '1234567890', '45000', '08:00:00', '17:00:00',
            '15', 'yes', '45000', 'jane.wanjiku@example.com', '0712345678',
            'John Wanjiku', '0799999999',
        ];
    }
    private function csvCell(string $value): string{return '"' . str_replace('"', '""', $value) . '"';}
}
