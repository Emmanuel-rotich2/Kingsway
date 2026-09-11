<?php
namespace App\API\Modules\admission;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
use App\Config\Config;
Config::init();

use App\API\Includes\WorkflowHandler;
use App\API\Services\ExtraChargeService;
use PDO;
use Exception;
use InvalidArgumentException;
use function App\API\Includes\formatResponse;

/**
 * Student Admission Workflow Handler
 * 
 * CANONICAL WORKFLOW:
 * Application Applied → Application Received → Reviewed and Approved
 * → Interview for Grade 4-9 → Student Admission Number → Class/Stream Placement
 * → Fees/Transport/Uniform Payments → ID Generation → Final Enrollment
 * 
 * Database Objects Used:
 * - Tables: admission_applications, admission_documents
 * - Procedures: sp_get_class_fee_schedule, sp_process_student_payment, generate_student_number
 * - Functions: calculate_total_fees
 */
class StudentAdmissionWorkflow extends WorkflowHandler {
    private AdmissionPolicy $policy;
    private AdmissionPaymentService $paymentService;
    private ExtraChargeService $extraChargeService;
    private bool $parentPreExisting = false;

    public function __construct() {
        parent::__construct('student_admission');
        $this->policy = new AdmissionPolicy();
        $this->paymentService = new AdmissionPaymentService($this->db);
        $this->extraChargeService = new ExtraChargeService($this->db);
    }

    /**
     * =======================================================================
     * STAGE 1: APPLICATION SUBMISSION
     * =======================================================================
     * Role: Registrar/Parent
     * Creates admission application and starts workflow
     */
    public function submitApplication($data, $files = []) {
        try {
            // Normalize payload for every channel. The admin panel sends the
            // canonical field names; the public website sends child_/parent_
            // form fields. Both converge here.
            $applicantName = trim($data['applicant_name'] ?? $data['child_name'] ?? '');
            $dob = trim($data['date_of_birth'] ?? $data['child_dob'] ?? '');
            $gender = trim($data['gender'] ?? $data['child_gender'] ?? '');
            $gradeRaw = trim($data['grade_applying_for'] ?? $data['grade'] ?? '');
            $academicYear = $data['academic_year'] ?? null;

            if ($applicantName === '' || $dob === '' || $gender === '' || $gradeRaw === '') {
                throw new Exception('Missing required applicant details.');
            }

            $applicationSource = $this->policy->resolveApplicationSource($data);
            $admissionCategory = $this->policy->resolveAdmissionCategory($data);
            $normalizedGrade = $this->policy->normalizeGrade((string) $gradeRaw);
            $targetTermId = $this->resolveTargetTermId($data);
            $intake = $this->requireOpenAdmissionWindow($targetTermId, $normalizedGrade, $admissionCategory);
            $targetTermId = (int) $intake['academic_year_term_id'];
            $academicYear = (int) substr((string) $intake['year_code'], -4);
            $admissionCategory = $intake['default_admission_category'] ?: $admissionCategory;
            $requiresInterview = $this->policy->requiresInterview($normalizedGrade) ? 1 : 0;
            $interviewReason = $this->policy->describeInterviewPolicy($normalizedGrade);

            // Generate application number (format: ADM/2025/001)
            $app_no = $this->generateApplicationNumber((int) $academicYear);

            $this->db->beginTransaction();

            // Resolve the parent/guardian inside the transaction so person/parent
            // rows created here roll back if the application insert fails. Admin
            // passes parent_id (parents.id). Public forms pass identity fields
            // (phone / national id / email) so we can find an existing person or
            // create a new one — never duplicate a person record.
            $parentId = $this->resolveParentId($data);
            if (!$parentId) {
                throw new Exception('Parent/guardian information is required.');
            }

            $sql = "INSERT INTO admission_applications (
                application_no, applicant_name, date_of_birth, gender,
                grade_applying_for, academic_year, parent_id,
                application_source, admission_category, target_term_id,
                requires_interview, interview_policy_reason,
                previous_school, has_special_needs, special_needs_details,
                status, created_at
            ) VALUES (
                :app_no, :name, :dob, :gender, :grade, :year, :parent,
                :application_source, :admission_category, :target_term_id,
                :requires_interview, :interview_policy_reason,
                :prev_school, :has_needs, :needs_details,
                'submitted', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'app_no' => $app_no,
                'name' => $applicantName,
                'dob' => $dob,
                'gender' => $gender,
                'grade' => $normalizedGrade,
                'year' => $academicYear,
                'parent' => $parentId,
                'application_source' => $applicationSource,
                'admission_category' => $admissionCategory,
                'target_term_id' => $targetTermId,
                'admission_window_id' => (int) $intake['id'],
                'academic_year' => $intake['year_code'],
                'requires_interview' => $requiresInterview,
                'interview_policy_reason' => $interviewReason,
                'prev_school' => $data['previous_school'] ?? $data['child_prev_school'] ?? null,
                'has_needs' => isset($data['has_special_needs']) ? (int) $data['has_special_needs'] : (!empty($data['special_needs']) ? 1 : 0),
                'needs_details' => $data['special_needs_details'] ?? $data['special_needs'] ?? null
            ]);

            $application_id = $this->db->lastInsertId();

            // Persist any documents uploaded with the submission (public path).
            if (!empty($files)) {
                $this->storeSubmittedDocuments($application_id, $normalizedGrade, $files);
            }

            $initiatorId = (int) ($this->user_id ?? 1);

            $workflow_data = [
                'application_no' => $app_no,
                'applicant_name' => $applicantName,
                'grade' => $normalizedGrade,
                'parent_id' => (int) $parentId,
                'application_source' => $applicationSource,
                'admission_category' => $admissionCategory,
                'target_term_id' => $targetTermId,
                'requires_interview' => (bool) $requiresInterview,
                'interview_policy_reason' => $interviewReason,
                'created_by' => $initiatorId,
                'submitted_by' => $initiatorId
            ];

            $instance_id = $this->startWorkflow('admission_application', $application_id, $workflow_data, $initiatorId);

            // This method is the submit boundary for every channel. Once the
            // application is successfully saved, it is received by the
            // school. Online and physical submissions differ only by
            // application_source. Application Applied is reserved for an
            // unfinished draft before this method is called.
            $this->advance(
                (int) $application_id,
                'application_received',
                'application_submitted',
                [
                    'documents_uploaded' => !empty($files),
                    'documents_uploaded_at' => !empty($files) ? date('Y-m-d H:i:s') : null
                ],
                'Application successfully submitted — received for review'
            );
            $initialStage = 'application_received';

            $this->db->commit();

            return formatResponse(true, [
                'application_id' => $application_id,
                'application_no' => $app_no,
                'ref' => $app_no,
                'workflow_instance_id' => $instance_id,
                'current_stage' => $initialStage,
                'next_stage' => $initialStage === 'application_received' ? 'application_review' : 'application_received',
                'policy' => [
                    'requires_interview' => (bool) $requiresInterview,
                    'interview_reason' => $interviewReason,
                    'application_source' => $applicationSource,
                    'admission_category' => $admissionCategory,
                    'target_term_id' => $targetTermId,
                ],
                'required_documents' => $this->getRequiredDocuments($normalizedGrade, $admissionCategory, $this->parentWasExisting($parentId))
            ], 'Application submitted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('admission_submit_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Resolve the parent/guardian id for an application.
     *
     * Smart dedupe: admin passes a parents.id directly; public submissions pass
     * identity details (phone / national id / email / name). We search persons
     * by identity first so a parent who already exists — including staff who are
     * parents, whose persons row was created on onboarding — is linked to their
     * existing parents row instead of creating duplicates.
     */
    private function resolveParentId(array $data): int
    {
        // Explicit parents.id (admin panel / parent portal).
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $exists = $this->scalar("SELECT id FROM parents WHERE id = ?", [$parentId]);
            if ($exists) {
                $this->parentPreExisting = true;
                return $parentId;
            }
            throw new Exception('Selected parent/guardian does not exist.');
        }

        // Public form: identity-based find-or-create.
        $phone = trim((string) ($data['parent_phone'] ?? ''));
        $nationalId = trim((string) ($data['parent_id'] ?? $data['parent_national_id'] ?? ''));
        $email = trim((string) ($data['parent_email'] ?? ''));
        $name = trim((string) ($data['parent_name'] ?? ''));
        $address = trim((string) ($data['parent_address'] ?? ''));

        // 1. Match an existing parent by identity.
        if ($phone !== '' || $nationalId !== '' || $email !== '') {
            $criteria = [];
            $params = [];
            if ($phone !== '') {
                $criteria[] = 'pe.phone = ?';
                $params[] = $phone;
            }
            if ($nationalId !== '') {
                $criteria[] = 'pe.national_id_no = ?';
                $params[] = $nationalId;
            }
            if ($email !== '') {
                $criteria[] = 'pe.email = ?';
                $params[] = $email;
            }
            $stmt = $this->db->prepare(
                "SELECT pr.id FROM parents pr JOIN persons pe ON pe.id = pr.person_id
                 WHERE " . implode(' OR ', $criteria) . " LIMIT 1"
            );
            $stmt->execute($params);
            $existing = $stmt->fetchColumn();
            if ($existing) {
                $this->parentPreExisting = true;
                return (int) $existing;
            }
        }

        // 2. An existing person that is not yet a parent (staff who are parents).
        if ($phone !== '' || $nationalId !== '' || $email !== '') {
            $criteria = [];
            $params = [];
            if ($phone !== '') {
                $criteria[] = 'phone = ?';
                $params[] = $phone;
            }
            if ($nationalId !== '') {
                $criteria[] = 'national_id_no = ?';
                $params[] = $nationalId;
            }
            if ($email !== '') {
                $criteria[] = 'email = ?';
                $params[] = $email;
            }
            $stmt = $this->db->prepare(
                "SELECT id FROM persons WHERE " . implode(' OR ', $criteria) . " LIMIT 1"
            );
            $stmt->execute($params);
            $personId = $stmt->fetchColumn();
            if ($personId) {
                return $this->createParentForPerson((int) $personId, $address);
            }
        }

        // 3. Brand new person + parent.
        if ($name === '') {
            throw new Exception('Parent/guardian name is required.');
        }
        $nameParts = explode(' ', $name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? $firstName;

        $stmt = $this->db->prepare(
            "INSERT INTO persons (first_name, last_name, email, phone, national_id_no, data_scope)
             VALUES (?, ?, ?, ?, ?, 'live')"
        );
        $stmt->execute([
            $firstName,
            $lastName,
            $email !== '' ? $email : null,
            $phone !== '' ? $phone : null,
            $nationalId !== '' ? $nationalId : null,
        ]);
        $personId = (int) $this->db->lastInsertId();

        return $this->createParentForPerson($personId, $address);
    }

    private function createParentForPerson(int $personId, string $address = ''): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO parents (person_id, address, status)
             VALUES (?, ?, 'active')"
        );
        $stmt->execute([$personId, $address !== '' ? $address : null]);
        $parentId = (int) $this->db->lastInsertId();
        $this->db->prepare(
            "INSERT INTO user_roles (user_id, role_id)
             SELECT u.id, 73
             FROM users u
             JOIN roles r ON r.id = 73 AND r.name = 'Parent'
             WHERE u.person_id = ?
             ON DUPLICATE KEY UPDATE user_id = user_id"
        )->execute([$personId]);
        return $parentId;
    }

    private function parentWasExisting(int $parentId): bool
    {
        return $this->parentPreExisting;
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Resolve the target academic_year_terms.id for an application.
     *
     * Order of precedence:
     *   1. explicit target_term_id / intake_term_id;
     *   2. a human-readable token ("Term 1 2027") from preferred_start /
     *      target_term_token;
     *   3. an open admission window matching the application's academic_year;
     *   4. the current (then upcoming) open term as a last-resort default.
     *
     * This is the single place that guarantees a newly submitted application
     * always has a target term, so it can advance past review.
     */
    private function resolveTargetTermId(array $data): ?int
    {
        // 1. Explicit id.
        $termId = $data['target_term_id'] ?? $data['intake_term_id'] ?? null;
        if ($termId !== null && $termId !== '' && (int) $termId > 0) {
            $exists = $this->scalar("SELECT id FROM academic_year_terms WHERE id = ?", [(int) $termId]);
            if ($exists) {
                return (int) $exists;
            }
        }

        // 2. Human token, e.g. "Term 1 2027" / "Term 1 2026/2027".
        $token = trim((string) ($data['target_term_token'] ?? $data['preferred_start'] ?? ''));
        if ($token !== '') {
            $resolved = $this->scalar(
                "SELECT ayt.id
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE CONCAT(t.name, ' ', ay.year_code) = ?
                    OR CONCAT(t.name, ' ', ay.year_name) = ?
                    OR CONCAT(t.name, ' ', t.code) = ?
                 LIMIT 1",
                [$token, $token, $token]
            );
            if ($resolved) {
                return (int) $resolved;
            }
        }

        // 3. Open window matching the application's academic_year.
        $academicYear = trim((string) ($data['academic_year'] ?? ''));
        if ($academicYear !== '') {
            $resolved = $this->scalar(
                "SELECT ayt.id
                 FROM academic_year_terms ayt
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN admission_windows aw ON aw.academic_year_term_id = ayt.id
                    AND aw.status = 'open' AND aw.accepts_new_applications = 1
                    AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
                    AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
                 WHERE ay.year_code LIKE CONCAT('%', ?, '%')
                 ORDER BY FIELD(ayt.status, 'current', 'upcoming'), ayt.opening_date ASC
                 LIMIT 1",
                [$academicYear]
            );
            if ($resolved) {
                return (int) $resolved;
            }
        }

        // 4. Last-resort current/upcoming open term.
        $resolved = $this->scalar(
            "SELECT ayt.id
             FROM academic_year_terms ayt
             JOIN admission_windows aw ON aw.academic_year_term_id = ayt.id
                AND aw.status = 'open' AND aw.accepts_new_applications = 1
                AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
                AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
             WHERE ayt.status IN ('current', 'upcoming')
             ORDER BY FIELD(ayt.status, 'current', 'upcoming'), ayt.opening_date ASC
             LIMIT 1"
        );
        return $resolved ? (int) $resolved : null;
    }

    /**
     * Every channel must submit against an administrator-opened intake. This
     * prevents a stale/manual term or academic year from entering the ledger.
     */
    private function requireOpenAdmissionWindow(?int $termId, string $grade, string $category): array
    {
        if (!$termId) {
            throw new Exception('No open admission intake is currently accepting applications.');
        }
        $stmt = $this->db->prepare(
            "SELECT aw.id, aw.academic_year_term_id, aw.eligible_grades,
                    aw.default_admission_category, ay.year_code
             FROM admission_windows aw
             JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
             JOIN academic_years ay ON ay.id = aw.academic_year_id
             WHERE aw.academic_year_term_id = ?
               AND ayt.academic_year_id = aw.academic_year_id
               AND aw.status = 'open' AND aw.accepts_new_applications = 1
               AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
               AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
             ORDER BY aw.id DESC LIMIT 1"
        );
        $stmt->execute([$termId]);
        $window = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$window) {
            throw new Exception('The selected admission intake is not open.');
        }
        $allowed = json_decode((string) ($window['eligible_grades'] ?? ''), true);
        if (is_array($allowed) && $allowed && !in_array($grade, array_map(function ($value) {
            return $this->policy->normalizeGrade((string) $value);
        }, $allowed), true)) {
            throw new Exception('The selected grade is not offered in this admission intake.');
        }
        return $window;
    }

    private function storeSubmittedDocuments(int $applicationId, string $grade, array $files): void
    {
        $requiredConfig = $this->getRequiredDocuments($grade);
        $requiredTypes = [];
        foreach ($requiredConfig as $type => $config) {
            if (!empty($config['mandatory'])) {
                $requiredTypes[] = $type;
            }
        }

        $docMeta = [
            'birth_certificate'      => ['label' => 'Birth Certificate'],
            'passport_photo'         => ['label' => 'Passport Photo'],
            'parent_id'              => ['label' => 'Parent/Guardian ID'],
            'previous_school_report' => ['label' => 'Previous School Report'],
            'immunization_card'      => ['label' => 'Immunization Card'],
            'progress_report'        => ['label' => 'Progress Report'],
            'leaving_certificate'    => ['label' => 'Leaving Certificate'],
            'transfer_letter'        => ['label' => 'Transfer Letter'],
            'medical_records'        => ['label' => 'Health / Medical Records'],
            'other'                  => ['label' => 'Other (e.g. Student Portfolio)'],
        ];

        $mediaManager = $this->contract('App\API\Modules\system\MediaManager', $this->db);
        $docInsert = $this->db->prepare(
            "INSERT INTO admission_documents
             (application_id, document_type, document_path, is_mandatory, verification_status, created_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())"
        );

        foreach ($files as $docType => $file) {
            if (!isset($docMeta[$docType])) {
                continue;
            }
            if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $mediaId = $mediaManager->upload(
                $file, 'students/documents', $applicationId, null, null, 'admission document', '', $docType
            );
            $documentPath = $mediaManager->getFileUrl($mediaId)
                ?: $mediaManager->getPreviewUrl($mediaId)
                ?: (string) $mediaId;
            $isMandatory = in_array($docType, $requiredTypes, true) ? 1 : 0;
            $docInsert->execute([$applicationId, $docType, $documentPath, $isMandatory]);
        }
    }

    /**
     * Central transition helper.
     *
     * Every admission workflow movement is routed through the domain stored
     * procedure `sp_advance_admission_workflow_stage`. The proc is the single
     * source of truth that:
     *   - writes the audit row in workflow_stage_history (actor + remarks),
     *   - updates workflow_instances.current_stage (the state every logged-in
     *     user reads, so cross-user visibility is guaranteed),
     *   - merges admission_applications.workflow_data_json,
     *   - and syncs admission_applications.status to the stage.
     *
     * Note: the proc REPLACES workflow_instances.data_json with the passed JSON,
     * while it MERGES admission_applications.workflow_data_json. So we merge the
     * supplied updates into the current instance data before calling, to avoid
     * clobbering per-stage data (interview dates, scores, etc.).
     *
     * @param int    $applicationId The admission_applications.id
     * @param string $toStage       New stage key
     * @param string $action        Audit action code
     * @param array  $updates       Workflow data to merge into instance + application
     * @param string $remarks       Audit remarks
     */
    private function advance(int $applicationId, string $toStage, string $action, array $updates = [], string $remarks = ''): void
    {
        $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
        if (!$instance) {
            throw new Exception("No active workflow instance found for application {$applicationId}");
        }

        $currentData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
        $merged = array_merge($currentData, $updates);
        $workflowUpdatesJson = json_encode($merged, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare("CALL sp_advance_admission_workflow_stage(?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $applicationId,
            $toStage,
            $action,
            (int) ($this->user_id ?? 1),
            $remarks,
            $workflowUpdatesJson
        ]);
        $stmt->closeCursor();
    }

    /**
     * =======================================================================
     * STAGE 2: DOCUMENT VERIFICATION
     * =======================================================================
     * Role: Registrar
     * Upload and verify admission documents
     */
    public function uploadDocument($application_id, $document_type, $file) {
        try {
            $this->db->beginTransaction();

            // Validate workflow state
            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance) {
                throw new Exception("No active workflow found for this application");
            }

            $grade = $this->getApplicationGrade($application_id);
            $requiredDocuments = $this->getRequiredDocuments($grade);
            $isMandatory = !empty($requiredDocuments[$document_type]['mandatory']) ? 1 : 0;
            $application = $this->getApplicationSummary($application_id);
            $preferredBaseName = $this->buildAdmissionDocumentFilenameBase($application, $document_type);

            // Upload admission documents under uploads/students/documents/{application_id}
            $mediaManager = $this->contract('App\API\Modules\system\MediaManager', $this->db);
            $mediaId = $mediaManager->upload(
                $file,
                'students/documents',
                $application_id,
                null,
                $this->user_id,
                'admission document',
                '',
                $preferredBaseName
            );
            $documentPath = $mediaManager->getFileUrl($mediaId) ?: $mediaManager->getPreviewUrl($mediaId) ?: $mediaId;

            // Save document record
            $sql = "INSERT INTO admission_documents (
                application_id, document_type, document_path,
                is_mandatory, verification_status, created_at
            ) VALUES (:app_id, :type, :path, :mandatory, 'pending', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'app_id' => $application_id,
                'type' => $document_type,
                'path' => $documentPath,
                'mandatory' => $isMandatory
            ]);
            $documentId = $this->db->lastInsertId();

            // Check if all mandatory docs uploaded
            $all_uploaded = $this->checkMandatoryDocuments($application_id);
            $currentStage = $instance['current_stage'] ?? null;

            // Only advance forward from the early intake stages when every
            // mandatory document has now been uploaded. We never reset the stage
            // backward on an upload — that was the old bug that made "Start Intake"
            // reopen Upload Documents even after documents already existed.
            $advanceEligibleStages = ['application_applied'];
            if ($all_uploaded && in_array($currentStage, $advanceEligibleStages, true)) {
                $this->advance(
                    $application_id,
                    'application_received',
                    'all_documents_uploaded',
                    ['documents_uploaded' => true, 'documents_uploaded_at' => date('Y-m-d H:i:s')],
                    'Application documents uploaded — application received for review'
                );
            }

            $this->db->commit();

            return formatResponse(true, [
                'document_id' => $documentId,
                'document_type' => $document_type,
                'document_path' => $documentPath,
                'all_mandatory_uploaded' => $all_uploaded
            ], 'Document uploaded successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('document_upload_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    private function getApplicationSummary($application_id): array
    {
        $stmt = $this->db->prepare("SELECT application_no, applicant_name FROM admission_applications WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $application_id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    private function buildAdmissionDocumentFilenameBase(array $application, string $documentType): string
    {
        $applicantName = $application['applicant_name'] ?? 'Applicant';
        $applicationNo = $application['application_no'] ?? 'Application';
        $documentLabel = $this->formatDocumentTypeLabel($documentType);

        return "{$applicantName}_{$documentLabel}_{$applicationNo}";
    }

    private function formatDocumentTypeLabel(string $documentType): string
    {
        return ucwords(str_replace('_', ' ', $documentType));
    }

    public function verifyDocument($document_id, $status, $notes = '') {
        try {
            $this->db->beginTransaction();

            // Update document verification status
            $sql = "UPDATE admission_documents 
                    SET verification_status = :status,
                        verified_by = :verifier,
                        verified_at = NOW(),
                        notes = :notes
                    WHERE id = :doc_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'status' => $status, // 'verified' or 'rejected'
                'verifier' => $this->user_id,
                'notes' => $notes,
                'doc_id' => $document_id
            ]);

            // Get application_id
            $sql = "SELECT application_id FROM admission_documents WHERE id = :doc_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['doc_id' => $document_id]);
            $application_id = $stmt->fetchColumn();

            if ($status === 'rejected') {
                // A rejected document reopens the upload stage so the applicant can
                // supply corrected documents. The workflow stays auditable: the app
                // returns to documents_upload and Start Intake will surface
                // "Upload Corrected Documents" with the rejection note.
                $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
                if ($instance && ($instance['current_stage'] ?? '') === 'documents_verification') {
                    $this->advance(
                        $application_id,
                        'documents_upload',
                        'document_rejected',
                        ['documents_rejected' => true, 'document_rejection_notes' => $notes],
                        'Document rejected — awaiting corrected upload'
                    );
                }
            } elseif ($this->checkAllDocumentsVerified($application_id)) {
                // Get application details to check grade
                $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $application_id]);
                $grade = $stmt->fetchColumn();

                // Space availability is checked for ALL grades before any interview
                // is scheduled (workflow step 5). Non-assessment grades will move
                // from class_space_check straight to admission_decision; assessment
                // grades proceed to interview_scheduling from there.
                $this->advance(
                    $application_id,
                    'class_space_check',
                    'all_documents_verified',
                    ['documents_verified' => true, 'documents_verified_at' => date('Y-m-d H:i:s'), 'documents_rejected' => false],
                    'All documents verified — proceeding to class space check'
                );
            }

            $this->db->commit();

            return formatResponse(true, null, 'Document verification updated');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('document_verify_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 3: INTERVIEW SCHEDULING
     * =======================================================================
     * Role: Registrar
     * Schedule interview with applicant/parent
     * NOTE: Only Grade 4-9 applicants use this stage. Playgroup, PP1, PP2 and Grades 1-3 skip it.
     */
    public function scheduleInterview($application_id, $interview_date, $interview_time, $venue = 'Main Office') {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance || $instance['current_stage'] !== 'interview_scheduling') {
                throw new Exception("Invalid workflow state for interview scheduling");
            }
            
            // Verify this grade requires interview
            $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $application_id]);
            $grade = $stmt->fetchColumn();
            
            if (!$this->requiresAssessment($grade)) {
                throw new Exception("Grade $grade does not require interview assessment (auto-qualified)");
            }

            // Store interview details in workflow data
            $sql = "UPDATE workflow_instances 
                    SET data_json = JSON_SET(
                        COALESCE(data_json, '{}'),
                        '$.interview_date', :date,
                        '$.interview_time', :time,
                        '$.interview_venue', :venue
                    )
                    WHERE id = :instance_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'date' => $interview_date,
                'time' => $interview_time,
                'venue' => $venue,
                'instance_id' => $instance['id']
            ]);

            // Send SMS notification to parent
            $this->sendInterviewSMS($application_id, $interview_date, $interview_time, $venue);

            // Advance to interview results (awaiting assessment)
            // Advance to interview results (awaiting assessment). Include the interview
            // details here so advance() does not overwrite the data_json it set above.
            $this->advance(
                $application_id,
                'interview_results',
                'interview_scheduled',
                [
                    'interview_scheduled' => true,
                    'interview_date' => $interview_date,
                    'interview_time' => $interview_time,
                    'interview_venue' => $venue
                ],
                'Interview scheduled'
            );

            $this->db->commit();

            return formatResponse(true, [
                'date' => $interview_date,
                'time' => $interview_time,
                'venue' => $venue
            ], 'Interview scheduled successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('interview_schedule_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /** Assign an applicant to an existing intake interview session. */
    public function scheduleInterviewSession(int $applicationId, int $sessionId): array
    {
        try {
            $this->db->beginTransaction();
            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance || ($instance['current_stage'] ?? '') !== 'interview_scheduling') {
                throw new Exception('Invalid workflow state for interview scheduling');
            }
            $appStmt = $this->db->prepare('SELECT applicant_name, grade_applying_for, target_term_id FROM admission_applications WHERE id = ? FOR UPDATE');
            $appStmt->execute([$applicationId]);
            $application = $appStmt->fetch(PDO::FETCH_ASSOC);
            if (!$application || !$this->requiresAssessment($application['grade_applying_for'])) {
                throw new Exception('This applicant is not eligible for an interview.');
            }
            $sessionStmt = $this->db->prepare(
                "SELECT s.*, aw.academic_year_term_id, aw.application_open_at, aw.application_close_at,
                        DATE_ADD(COALESCE(DATE(aw.application_close_at), ayt.closing_date), INTERVAL 7 DAY) AS valid_until
                 FROM admission_interview_sessions s
                 JOIN admission_windows aw ON aw.id = s.admission_window_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
                 WHERE s.id = ? FOR UPDATE"
            );
            $sessionStmt->execute([$sessionId]);
            $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
            if (!$session || !in_array($session['status'], ['scheduled', 'full'], true)) {
                throw new Exception('Interview session is not available.');
            }
            if (empty($session['interviewer_id'])) {
                throw new Exception('This interview session has no teacher interviewer. Edit the session before assigning applicants.');
            }
            $teacherStmt = $this->db->prepare("SELECT id FROM staff WHERE id=? AND staff_type_id=1 AND status='active' LIMIT 1");
            $teacherStmt->execute([(int) $session['interviewer_id']]);
            if (!$teacherStmt->fetchColumn()) throw new Exception('This interview session interviewer is not an active teacher.');
            if ((int) ($application['target_term_id'] ?? 0) !== (int) ($session['academic_year_term_id'] ?? 0)) {
                throw new Exception('Interview session does not belong to the applicant admission intake.');
            }
            if ($session['application_open_at'] && $session['session_date'] < substr($session['application_open_at'], 0, 10)) {
                throw new Exception('Interview session is before the intake opening date.');
            }
            if ($session['valid_until'] && $session['session_date'] > $session['valid_until']) {
                throw new Exception('Interview session is outside the permitted intake period.');
            }
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM admission_interviews WHERE session_id = ? AND status <> 'cancelled'");
            $countStmt->execute([$sessionId]);
            $assigned = (int) $countStmt->fetchColumn();
            if ($assigned >= (int) $session['capacity']) {
                throw new Exception('Interview session is full.');
            }
            $existing = $this->db->prepare("SELECT id FROM admission_interviews WHERE application_id = ? AND status IN ('scheduled','completed','rescheduled') LIMIT 1");
            $existing->execute([$applicationId]);
            if ($existing->fetchColumn()) throw new Exception('Applicant already has an interview assignment.');
            $insert = $this->db->prepare("INSERT INTO admission_interviews (application_id, session_id, scheduled_date, scheduled_time, venue, status) VALUES (?, ?, ?, ?, ?, 'scheduled')");
            $insert->execute([$applicationId, $sessionId, $session['session_date'], $session['start_time'], $session['venue']]);
            $assigned++;
            $this->db->prepare("UPDATE admission_interview_sessions SET status = CASE WHEN ? >= capacity THEN 'full' ELSE 'scheduled' END WHERE id = ?")->execute([$assigned, $sessionId]);
            $insertId = (int) $this->db->lastInsertId();
            $this->advance($applicationId, 'interview_results', 'interview_session_assigned', [
                'interview_scheduled' => true,
                'interview_session_id' => $sessionId,
                'interview_date' => $session['session_date'],
                'interview_time' => $session['start_time'],
                'interview_venue' => $session['venue'],
            ], 'Applicant assigned to interview session');
            $this->db->commit();
            $session['session_id'] = $sessionId;
            // Dispatch after commit so an external message can never be sent
            // for an application whose workflow transaction later rolls back.
            $this->sendInterviewNotifications($insertId, $session, 'assigned');
            return formatResponse(true, ['session_id' => $sessionId, 'date' => $session['session_date'], 'time' => $session['start_time'], 'venue' => $session['venue']], 'Applicant assigned to interview session');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logError('interview_session_assignment_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage());
            return formatResponse(false, null, $e->getMessage());
        }
    }

    /** Queue parent SMS + email and create the teacher's in-system notice. */
    public function notifyInterviewAssignment(int $interviewId, int $sessionId, string $eventSuffix = 'rescheduled'): array
    {
        $stmt = $this->db->prepare("SELECT ai.id,ai.session_id,ai.scheduled_date,ai.scheduled_time,ai.venue,ai.interviewer_id,
                aa.applicant_name,aa.application_no,p.id AS parent_id,pp.phone AS parent_phone,pp.email AS parent_email
            FROM admission_interviews ai JOIN admission_applications aa ON aa.id=ai.application_id
            JOIN parents p ON p.id=aa.parent_id LEFT JOIN persons pp ON pp.id=p.person_id
            WHERE ai.id=? AND ai.session_id=? LIMIT 1");
        $stmt->execute([$interviewId, $sessionId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) return formatResponse(false, null, 'Interview assignment not found');
        $this->sendInterviewNotifications($interviewId, [
            'session_id' => $sessionId,
            'session_date' => $assignment['scheduled_date'],
            'start_time' => $assignment['scheduled_time'],
            'venue' => $assignment['venue'],
            'interviewer_id' => $assignment['interviewer_id'],
        ], $eventSuffix, $assignment);
        return formatResponse(true, null, 'Interview notifications queued');
    }

    /**
     * =======================================================================
     * STAGE 4: INTERVIEW ASSESSMENT
     * =======================================================================
     * Role: Head Teacher
     * Conduct and record interview assessment
     * NOTE: Only Grade 4-9 applicants use this stage. Playgroup, PP1, PP2 and Grades 1-3 skip it.
     */
    public function recordInterviewResults($application_id, $assessment_data) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance || $instance['current_stage'] !== 'interview_results') {
                throw new Exception("Invalid workflow state for interview assessment");
            }
            
            // Verify this grade requires interview
            $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $application_id]);
            $grade = $stmt->fetchColumn();
            
            if (!$this->requiresAssessment($grade)) {
                throw new Exception("Grade $grade does not require interview assessment (auto-qualified)");
            }

            $decision = strtolower(trim((string) ($assessment_data['decision'] ?? '')));
            if (!in_array($decision, ['pass', 'fail'], true)) {
                throw new Exception('An interview decision of pass or fail is required');
            }

            // Store assessment results. The score is evidence; the authorized
            // interviewer makes the actual decision.
            $sql = "UPDATE workflow_instances 
                    SET data_json = JSON_SET(
                        COALESCE(data_json, '{}'),
                        '$.assessment_score', :score,
                        '$.interview_decision', :decision,
                        '$.assessment_notes', :notes,
                        '$.assessed_by', :assessor,
                        '$.assessment_date', NOW()
                    )
                    WHERE id = :instance_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'score' => $assessment_data['score'],
                'decision' => $decision,
                'notes' => $assessment_data['notes'] ?? '',
                'assessor' => $this->user_id,
                'instance_id' => $instance['id']
            ]);

            if ($decision === 'pass') {
                // Passed → student admission-number creation. The interview
                // is the approval gate for Grade 4-9; a separate legacy
                // admission-decision stage is no longer part of the active
                // CBC intake workflow.
                $this->advance(
                    $application_id,
                    'student_admission_number',
                    'assessment_passed',
                    [
                        'interview_passed' => true,
                        'interview_decision' => $decision,
                        'interview_score' => $assessment_data['score'],
                        'interview_notes' => $assessment_data['notes'] ?? ''
                    ],
                    'Interview passed by authorized reviewer — proceeding to student admission-number creation'
                );
            } else {
                // Failed → rejected stage (audit-logged). status stays visible to all.
                $this->advance(
                    $application_id,
                    'rejected',
                    'assessment_failed',
                    [
                        'interview_passed' => false,
                        'interview_decision' => $decision,
                        'interview_score' => $assessment_data['score'],
                        'rejection_reason' => 'Did not meet interview requirements'
                    ],
                    'Interview failed — application rejected'
                );
            }

            $this->db->commit();

            return formatResponse(true, null, $decision === 'pass' ?
                'Interview marked passed. Student admission-number creation can proceed.' :
                'Assessment not passed. Application cancelled.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('interview_assessment_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 5: PLACEMENT OFFER
     * =======================================================================
     * Role: Head Teacher
     * Generate and send placement offer
     */
    public function generatePlacementOffer($application_id, $assigned_class_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance || ($instance['current_stage'] ?? '') !== 'fees_payment') {
                throw new Exception("Invalid workflow state for placement offer");
            }

            $total_fees = $this->calculatePlacementFees((int) $assigned_class_id, (int) $application_id);

            // Store placement details (no stage change — offer letter is informational;
            // the workflow is now driven by the 12-step keys).
            $this->advance(
                $application_id,
                $instance['current_stage'],
                'placement_offer_generated',
                [
                    'assigned_class_id' => (int) $assigned_class_id,
                    'total_fees' => $total_fees,
                    'offer_date' => date('Y-m-d H:i:s')
                ],
                'Placement offer generated'
            );

            // Send placement offer letter (SMS/Email)
            $this->sendPlacementOfferNotification($application_id, $total_fees);

            $this->db->commit();

            return formatResponse(true, [
                'total_fees' => $total_fees,
                'class_id' => $assigned_class_id
            ], 'Placement offer generated successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('placement_offer_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 6: FEE PAYMENT
     * =======================================================================
     * Role: Accountant
     * Process initial admission fee payment
     */
    public function recordFeePayment($application_id, $payment_data) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            $currentStage = (string) ($instance['current_stage'] ?? '');
            if (!$instance || !in_array($currentStage, ['class_placement', 'fees_payment'], true)) {
                throw new Exception("Invalid workflow state for fee payment");
            }

            $amount = isset($payment_data['amount']) ? (float) $payment_data['amount'] : 0.0;
            if ($amount <= 0) {
                throw new Exception("Payment amount must be greater than zero");
            }

            $admissionChargesDue = $this->getAdmissionExtraChargesDue((int) $application_id);
            if ($amount < $admissionChargesDue) {
                throw new Exception('The payment must include the required admission charges of KES ' . number_format($admissionChargesDue, 0));
            }

            $payment = $this->paymentService->recordApplicationPayment((int) $application_id, $payment_data, (int) $this->user_id);
            $this->extraChargeService->allocateAdmissionPayment(
                (int) $application_id,
                (int) $payment['payment_id'],
                $amount
            );

            $instanceData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $instanceData['last_payment_recorded_at'] = date('Y-m-d H:i:s');
            $instanceData['last_admission_payment_id'] = $payment['payment_id'];
            $this->saveWorkflowInstanceData((int) $instance['id'], $instanceData);

            // Update application status
            $this->updateApplicationStatus($application_id, 'fees_pending');

            $this->db->commit();

            return formatResponse(true, [
                'payment_id' => $payment['payment_id'],
                'amount_paid' => $amount,
                'receipt_no' => $payment['receipt_no'],
                'reference_no' => $payment['reference_no'],
                'can_enroll' => false,
                'stage' => $currentStage,
                'verification_status' => 'pending_verification'
            ], 'Payment submitted for verification');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('fee_payment_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    private function getAdmissionExtraChargesDue(int $applicationId): float
    {
        return $this->extraChargeService->admissionTotalDue($applicationId);
    }

    public function sendPaymentInstructions(int $applicationId): array
    {
        $this->sendPlacementPaymentNotification($applicationId, '', 0, 0, 'admission-payment-instructions');
        return formatResponse(true, ['application_id' => $applicationId], 'Payment instructions queued for SMS and email');
    }

    /** Advance an electronically confirmed payment after the ledger is safe. */
    public function advanceAfterConfirmedPayment(int $applicationId): bool
    {
        $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
        if (!$instance || ($instance['current_stage'] ?? '') !== 'fees_payment') {
            return false;
        }

        $registrationFee = $this->getAdmissionExtraChargesDue($applicationId);
        $totalPaid = $this->paymentService->getTotalRecorded($applicationId);
        if ($totalPaid < $registrationFee) {
            return false;
        }

        $this->advance(
            $applicationId,
            'student_id_generation',
            'payment_received',
            [
                'payment_status' => 'paid',
                'last_payment_recorded_at' => date('Y-m-d H:i:s'),
                'payment_total_recorded' => $totalPaid,
            ],
            'Electronic payment confirmed; admission payment requirement met'
        );
        return true;
    }

    /**
     * RPC-shaped contract surface for the cross-module payment advancement boundary.
     *
     * @see admission.api.advance_after_payment in ServiceContractRegistry
     */
    public function advanceApplicationAfterConfirmedPayment(array $raw): array
    {
        $applicationId = $raw['application_id'] ?? null;
        if (!is_numeric($applicationId) || (int) $applicationId <= 0) {
            throw new InvalidArgumentException('application_id must be a positive integer.');
        }
        $applicationId = (int) $applicationId;
        $advanced = $this->advanceAfterConfirmedPayment($applicationId);
        return [
            'advanced' => $advanced,
            'application_id' => $applicationId,
        ];
    }

    /** Confirm a bank/M-Pesa record after staff have matched it to the statement/reconciliation feed. */
    public function confirmManualPayment(int $applicationId, int $paymentId, array $data = []): array
    {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            $currentStage = (string) ($instance['current_stage'] ?? '');
            if (!$instance || !in_array($currentStage, ['class_placement', 'fees_payment'], true)) {
                throw new Exception('The application is not awaiting payment verification');
            }

            $stmt = $this->db->prepare(
                "SELECT * FROM admission_payments
                 WHERE id = :payment_id AND application_id = :application_id
                   AND status = 'pending_verification'
                 LIMIT 1"
            );
            $stmt->execute(['payment_id' => $paymentId, 'application_id' => $applicationId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$payment) {
                throw new Exception('This payment is already verified, posted, voided, or does not belong to this application');
            }

            $method = (string) ($payment['payment_method'] ?? '');
            if (!in_array($method, ['bank_transfer', 'mpesa'], true)) {
                throw new Exception('Only bank and M-Pesa payments can be verified here');
            }

            $source = trim((string) ($data['verification_source'] ?? ($method === 'mpesa' ? 'mpesa_reconciliation' : 'kcb_statement')));
            $notes = trim((string) ($data['verification_notes'] ?? $data['notes'] ?? ''));
            $update = $this->db->prepare(
                "UPDATE admission_payments
                 SET status = 'recorded', verification_source = :source,
                     verified_by = :verified_by, verified_at = NOW(),
                     verification_notes = :verification_notes, updated_at = NOW()
                 WHERE id = :id AND status = 'pending_verification'"
            );
            $update->execute([
                'source' => $source !== '' ? $source : 'staff_reconciliation',
                'verified_by' => (int) $this->user_id,
                'verification_notes' => $notes !== '' ? $notes : 'Matched to the official payment reconciliation record',
                'id' => $paymentId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new Exception('Payment verification could not be completed; refresh and try again');
            }

            $appStmt = $this->db->prepare("SELECT enrolled_student_id, parent_id, application_no FROM admission_applications WHERE id = :id LIMIT 1");
            $appStmt->execute(['id' => $applicationId]);
            $application = $appStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $studentId = (int) ($application['enrolled_student_id'] ?? 0);
            $posted = 0;
            if ($studentId > 0) {
                $posted = $this->paymentService->postApplicationPaymentsToStudent(
                    $applicationId,
                    $studentId,
                    (int) ($application['parent_id'] ?? 0) ?: null,
                    (int) $this->user_id,
                    (string) ($application['application_no'] ?? '')
                );
            }

            $instanceData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $instanceData['payment_verification_status'] = 'confirmed';
            $instanceData['last_payment_verified_at'] = date('Y-m-d H:i:s');
            $instanceData['last_verified_payment_id'] = $paymentId;
            $this->saveWorkflowInstanceData((int) $instance['id'], $instanceData);

            $advanced = false;
            if ($currentStage === 'fees_payment' && $this->paymentService->getTotalRecorded($applicationId) >= $this->getAdmissionExtraChargesDue($applicationId)) {
                $advanced = $this->advanceAfterConfirmedPayment($applicationId);
            }

            $this->db->commit();
            return formatResponse(true, [
                'application_id' => $applicationId,
                'payment_id' => $paymentId,
                'status' => 'recorded',
                'posted_to_ledger' => $posted,
                'advanced_to_id_generation' => $advanced,
            ], $advanced ? 'Payment verified and application advanced to ID generation' : 'Payment verified successfully');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logError('manual_payment_verification_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage());
            return formatResponse(false, null, 'Payment verification failed');
        }
    }

    private function sendPlacementPaymentNotification(int $applicationId, string $admissionNumber, int $classId, int $streamId, string $eventPrefix = 'admission-payment-request'): void
    {
        $stmt = $this->db->prepare("SELECT aa.applicant_name, aa.parent_id, aa.application_no, s0.admission_no AS student_admission_no, pp.phone AS parent_phone, pp.email AS parent_email, c.name AS class_name, s.name AS stream_name
            FROM admission_applications aa
            JOIN parents p ON p.id = aa.parent_id
            JOIN persons pp ON pp.id = p.person_id
            LEFT JOIN students s0 ON s0.id = aa.enrolled_student_id
            LEFT JOIN classes c ON c.id = :class_id
            LEFT JOIN streams s ON s.id = :stream_id
            WHERE aa.id = :application_id LIMIT 1");
        $stmt->execute(['application_id' => $applicationId, 'class_id' => $classId, 'stream_id' => $streamId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (empty($row['parent_phone']) && empty($row['parent_email']))) return;

            $registrationFee = $this->getAdmissionExtraChargesDue($applicationId);
        $accountReference = $admissionNumber ?: ($row['student_admission_no'] ?: $row['application_no']);
        $childName = htmlspecialchars($row['applicant_name'] ?: 'your child', ENT_QUOTES, 'UTF-8');
        $ref = htmlspecialchars($accountReference, ENT_QUOTES, 'UTF-8');
        $fee = number_format($registrationFee, 0);

        $smsBody = sprintf(
            'Kingsway Admissions: Payment is requested for %s. Use application/admission reference %s as the account number. Registration fee due: KES %s. You may pay by M-Pesa or bank transfer; cash is not accepted. Please retain the M-Pesa message or bank receipt.',
            $row['applicant_name'] ?: 'your child',
            $accountReference,
            $fee
        );

        $parentSalutation = htmlspecialchars($row['applicant_name'] ?: 'Parent/Guardian', ENT_QUOTES, 'UTF-8');
        $emailBody = '<p>Dear ' . $parentSalutation . ',</p>'
            . '<p>We are pleased to confirm that <strong>' . $childName . '</strong> has been placed at Kingsway Preparatory School.</p>'
            . '<p>To complete the admission, please settle the registration fee as detailed below:</p>'
            . '<table style="margin:16px 0;border-collapse:collapse;width:100%;max-width:480px;">'
            . '<tr><td style="padding:10px 14px;background:#f7fafc;border:1px solid #e5e7eb;font-weight:600;">Registration Fee</td><td style="padding:10px 14px;border:1px solid #e5e7eb;text-align:right;">KES ' . $fee . '</td></tr>'
            . '<tr><td style="padding:10px 14px;background:#f7fafc;border:1px solid #e5e7eb;font-weight:600;">Account Reference</td><td style="padding:10px 14px;border:1px solid #e5e7eb;text-align:right;">' . $ref . '</td></tr>'
            . '</table>'
            . '<p><strong>Payment methods:</strong></p>'
            . '<ul style="margin:8px 0 16px 20px;line-height:1.8;">'
            . '<li><strong>M-Pesa</strong> &mdash; use application/admission reference <strong>' . $ref . '</strong> as the account number</li>'
            . '<li><strong>Bank Transfer</strong> &mdash; quote reference <strong>' . $ref . '</strong></li>'
            . '</ul>'
            . '<p style="color:#a15c00;"><em>Cash payments are not accepted.</em> Please retain your M-Pesa confirmation message or bank receipt as proof of payment.</p>'
            . '<p>Should you have any questions, please contact the admissions office.</p>';

        $business = new \App\API\Services\CommunicationBusinessEventService($this->db);
        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        foreach (['sms', 'email'] as $channel) {
            $key = $eventPrefix . ':' . $applicationId . ':' . $channel;
            $check = $this->db->prepare("SELECT id FROM communication_business_events WHERE event_code='admission_payment_request' AND event_key=? LIMIT 1");
            $check->execute([$key]);
            if ($check->fetchColumn()) continue;
            $eventId = $business->getOrCreate('admission_payment_request', $key, date('Y-m-d H:i:s'), (int) ($this->user_id ?: 1));
            $channelBody = $channel === 'email' ? $emailBody : $smsBody;
            $queued = $platform->queueRenderedForContacts(
                [['user_id' => null, 'phone' => $row['parent_phone'] ?? null, 'email' => $row['parent_email'] ?? null]],
                $channel,
                'Admission placement and payment instructions',
                $channelBody,
                ['purpose' => 'admissions', 'sender_id' => (int) ($this->user_id ?: 1), 'business_event_id' => $eventId]
            );
            $business->markProcessed($eventId);
            if (!empty($queued['communication_id'])) {
                try {
                    (new \App\API\Services\CommunicationOutboxService($this->db))->processOne((int) $queued['communication_id']);
                } catch (\Throwable $dispatchError) {
                    \App\API\Services\Logger::legacyError('[AdmissionPaymentNotification] dispatch deferred: ' . $dispatchError->getMessage());
                }
            }
        }
    }

    /**
     * =======================================================================
     * STAGE: CLASS SPACE CHECK (workflow step 5)
     * =======================================================================
     * Role: Registrar / Admissions Office
     * Calls sp_check_class_space_availability, captures the result, and persists
     * it via sp_advance_admission_workflow_stage. If space is available we move
     * to interview_scheduling (assessment grades) or admission_decision
     * (non-assessment grades that auto-qualify). If there is no space we stay at
     * class_space_check with a blocking note so the intake cannot proceed.
     */
    public function checkClassSpace(int $applicationId, bool $available, ?string $notes = null): array
    {
        try {
            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance || ($instance['current_stage'] ?? '') !== 'class_space_check') {
                throw new Exception('Application is not at the class space check stage');
            }

            // Leverage the existing SQL routine to compute capacity vs. current count.
            $stmt = $this->db->prepare("CALL sp_check_class_space_availability(:app_id, :user_id)");
            $stmt->execute(['app_id' => $applicationId, 'user_id' => (int) ($this->user_id ?? 1)]);
            $spaceInfo = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();

            if (!$available) {
                $this->advance(
                    $applicationId,
                    'class_space_check',
                    'class_space_unavailable',
                    [
                        'space_checked' => true,
                        'space_available' => false,
                        'available_spaces' => (int) ($spaceInfo['available_spaces'] ?? 0),
                        'class_checked_id' => (int) ($spaceInfo['class_id'] ?? 0),
                        'period_checked_id' => (int) ($spaceInfo['academic_year_id'] ?? 0),
                        'space_message' => $notes ?? 'No space available in the applied class.'
                    ],
                    'Class space unavailable — intake blocked'
                );
                return formatResponse(true, ['space_available' => false], 'No space available; intake blocked.');
            }

            $requiresAssessment = (bool) ($spaceInfo['requires_assessment'] ?? $this->requiresAssessment($spaceInfo['grade'] ?? null));
            $nextStage = $requiresAssessment ? 'interview_scheduling' : 'admission_decision';
            $action = $requiresAssessment ? 'space_confirmed_to_interview' : 'space_confirmed_to_decision';

            $this->advance(
                $applicationId,
                $nextStage,
                $action,
                [
                    'space_checked' => true,
                    'space_available' => true,
                    'available_spaces' => (int) ($spaceInfo['available_spaces'] ?? 0),
                    'class_checked_id' => (int) ($spaceInfo['class_id'] ?? 0),
                    'period_checked_id' => (int) ($spaceInfo['academic_year_id'] ?? 0),
                    'space_message' => $notes
                ],
                'Class space confirmed'
            );

            return formatResponse(true, ['space_available' => true, 'next_stage' => $nextStage], 'Space confirmed.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE: ADMIT STUDENT (workflow step 8 entry)
     * =======================================================================
     * Role: Director / Headteacher
     * Marks the interview-passed / space-confirmed application as admitted. The
     * provisional student is NOT created here (that is createProvisionalStudent)
     * so the two steps are independently auditable.
     */
    public function admitStudent(int $applicationId): array
    {
        try {
            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance) {
                throw new Exception('No active workflow instance found');
            }
            $stage = $instance['current_stage'] ?? '';
            if ($stage !== 'interview_results') {
                throw new Exception("Application cannot be admitted from stage '{$stage}'");
            }

            $this->advance(
                $applicationId,
                'student_admission_number',
                'student_admitted',
                ['admission_approved' => true, 'admitted_at' => date('Y-m-d H:i:s')],
                'Student admitted — proceed to provisional student creation'
            );

            return formatResponse(true, ['next_stage' => 'student_admission_number'], 'Student admitted.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 8: CREATE PROVISIONAL STUDENT
     * =======================================================================
     * Role: Registrar
     * Builds the real students row for the admitted application. Dedup-guarded:
     * if a students row already exists for this application it is returned
     * instead of creating a duplicate. Advances to class_placement.
     */
    public function createStudentAdmissionNumber(int $applicationId): array
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM admission_applications WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmt->execute(['id' => $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) {
                throw new Exception('Admission application not found');
            }

            // Idempotency must be checked before the workflow-stage gate. A
            // second click happens after the first request has already moved
            // the application to class placement, and should return the
            // existing student instead of producing a misleading 400 error.
            if (!empty($application['enrolled_student_id'])) {
                $this->db->commit();
                return formatResponse(true, [
                    'student_id' => (int) $application['enrolled_student_id'],
                    'admission_number' => $application['admission_no'] ?? null,
                    'reused' => true
                ], 'Student admission number already exists for this application.');
            }

            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance) {
                throw new Exception('No active workflow instance found');
            }
            if (($instance['current_stage'] ?? '') !== 'student_admission_number') {
                throw new Exception('Application is not at the student admission-number stage');
            }

            $studentTypeId = $this->resolveDefaultStudentTypeId();
            if (!$studentTypeId) {
                throw new Exception('Unable to resolve an active student type');
            }

            $proc = $this->db->prepare("CALL sp_register_applicant_as_student(:application_id, :operator_id, :student_type_id, @out_student_id, @out_admission_no)");
            $proc->execute([
                'application_id' => $applicationId,
                'operator_id' => (int) ($this->user_id ?? 1),
                'student_type_id' => $studentTypeId,
            ]);
            $proc->closeCursor();

            $out = $this->db->query("SELECT @out_student_id AS student_id, @out_admission_no AS admission_no")->fetch(PDO::FETCH_ASSOC);
            $studentId = (int) ($out['student_id'] ?? 0);
            $admissionNo = $out['admission_no'] ?? null;
            if (!$studentId) {
                throw new Exception('Student creation via proc returned no ID');
            }

            $this->db->commit();

            $this->advance(
                $applicationId,
                'class_placement',
                'student_admission_number_created',
                ['student_id' => $studentId, 'admission_number' => $admissionNo, 'student_admission_number_created' => true],
                'Student record created with admission number — awaiting class placement'
            );

            return formatResponse(true, [
                'student_id' => $studentId,
                'admission_number' => $admissionNo
            ], 'Student admission number created successfully.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /** Backward-compatible API alias; the active workflow uses the canonical name. */
    public function createProvisionalStudent(int $applicationId): array
    {
        return $this->createStudentAdmissionNumber($applicationId);
    }

    /**
     * =======================================================================
     * STAGE 10: GENERATE STUDENT ID CARD
     * =======================================================================
     * Role: Registrar
     * Reuses StudentIDCardGenerator to produce the ID card + QR token, records it
     * in student_id_cards, and advances to final_approval.
     */
    public function generateStudentIdCard(int $applicationId): array
    {
        try {
            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance || ($instance['current_stage'] ?? '') !== 'student_id_generation') {
                throw new Exception('Application is not at the student ID generation stage');
            }

            $stmt = $this->db->prepare("SELECT enrolled_student_id FROM admission_applications WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $applicationId]);
            $studentId = (int) ($stmt->fetchColumn() ?: 0);
            if (!$studentId) {
                throw new Exception('No student record linked to this application');
            }

            $cardGenerator = $this->contract('App\API\Modules\students\StudentIDCardGenerator');
            $qrResult = $cardGenerator->generateEnhancedQRCode($studentId);
            $qrToken = is_array($qrResult) && !empty($qrResult['data']['qr_token']) ? $qrResult['data']['qr_token'] : null;
            if (!$qrToken) {
                $qrToken = bin2hex(random_bytes(16));
            }

            $academicYearId = (int) ($this->getCurrentAcademicYearId() ?? date('Y'));
            $cardNumber = 'IDC-' . str_pad((string) $studentId, 6, '0', STR_PAD_LEFT);
            $stmt = $this->db->prepare("
                INSERT INTO student_id_cards (student_id, card_number, qr_token, qr_payload, issue_date, expiry_year, status, generated_at, generated_by, created_at)
                VALUES (:student_id, :card_number, :qr_token, :qr_payload, CURDATE(), :expiry_year, 'generated', NOW(), :generated_by, NOW())
                ON DUPLICATE KEY UPDATE qr_token = VALUES(qr_token), status = 'generated', generated_at = NOW()
            ");
            $stmt->execute([
                'student_id' => $studentId,
                'card_number' => $cardNumber,
                'qr_token' => $qrToken,
                'qr_payload' => json_encode(['student_id' => $studentId]),
                'expiry_year' => $academicYearId,
                'generated_by' => (int) ($this->user_id ?? 1)
            ]);
            $cardId = (int) $this->db->lastInsertId();

            $this->advance(
                $applicationId,
                'final_enrollment',
                'student_id_card_generated',
                ['student_id_card_generated' => true, 'student_id_card_id' => $cardId],
                'Student ID card generated — awaiting final approval'
            );

            return formatResponse(true, ['card_id' => $cardId, 'next_stage' => 'final_enrollment'], 'Student ID card generated.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 11: FINAL APPROVAL
     * =======================================================================
     * Role: Director / Headteacher
     * Approves the provisioned student and advances to enrollment (the final
     * class/stream/dorm/register/subjects assignment step).
     */
    public function finalApproval(int $applicationId): array
    {
        try {
            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance || ($instance['current_stage'] ?? '') !== 'final_enrollment') {
                throw new Exception('Application is not at the final approval stage');
            }

            $this->advance(
                $applicationId,
                'enrolled',
                'final_approval_granted',
                ['final_enrollment_done' => true, 'final_enrollment_at' => date('Y-m-d H:i:s')],
                'Final enrollment completed'
            );

            return formatResponse(true, ['next_stage' => 'enrolled'], 'Final enrollment completed.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * =======================================================================
     * STAGE 7: ENROLLMENT
     * =======================================================================
     * Role: Registrar
     * Complete student enrollment and create student record
     */
    public function completeEnrollment($application_id, array $placement = []) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance || !in_array($instance['current_stage'] ?? '', ['class_placement', 'final_enrollment'], true)) {
                throw new Exception("Invalid workflow state for enrollment");
            }

            // Get application details
            $sql = "SELECT * FROM admission_applications WHERE id = :id FOR UPDATE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) {
                throw new Exception('Admission application not found');
            }
            if (($application['status'] ?? '') === 'enrolled') {
                throw new Exception('Application is already enrolled');
            }
            // Placement is intentionally before payment. The student must
            // first exist with an admission number so the class/stream,
            // learning areas, billing and boarding records can be linked.

            // The provisional student was created at step 8 (createProvisionalStudent).
            // Reuse it rather than inserting a second student row.
            $student_id = (int) ($application['enrolled_student_id'] ?? 0);
            if (!$student_id) {
                throw new Exception('No provisional student linked — run create provisional student first');
            }

            $instance_data = json_decode($instance['data_json'], true) ?: [];
            $class_id = !empty($placement['class_id'])
                ? (int) $placement['class_id']
                : ($instance_data['assigned_class_id'] ?? null);
            $academic_year_id = (int) $this->getCurrentAcademicYearId();
            if (!$academic_year_id) {
                throw new Exception('No active academic year found for enrollment');
            }
            $studentTypeId = $this->resolveDefaultStudentTypeId();
            if (!$studentTypeId) {
                throw new Exception('Unable to resolve an active student type for enrollment');
            }

            // Determine stream: prefer assigned, else the provisional student's stream.
            $stream_id = !empty($placement['stream_id'])
                ? (int) $placement['stream_id']
                : ($instance_data['assigned_stream_id'] ?? null);
            if (!$stream_id || !$class_id) {
                throw new Exception('Select a configured class stream before completing placement');
            }

            $aycsId = null;
            $aycsStmt = $this->db->prepare("
                SELECT aycs.id FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                WHERE ayc.academic_year_id = :year_id AND ayc.class_id = :class_id AND aycs.stream_id = :stream_id
                LIMIT 1
            ");
            $aycsStmt->execute(['year_id' => $academic_year_id, 'class_id' => $class_id, 'stream_id' => $stream_id]);
            $aycsId = $aycsStmt->fetchColumn() ?: null;

            if (!$aycsId) {
                throw new Exception('The selected stream is not configured for this class and academic year');
            }

            $enrollment_date = date('Y-m-d');

            $proc = $this->db->prepare("CALL sp_place_application_into_class(:application_id, :aycs_id, :enrollment_date, :operator_id, :student_type_id, @out_student_id, @out_admission_no, @out_enrollment_id, @out_obligations)");
            $proc->execute([
                'application_id' => $application_id,
                'aycs_id' => (int) $aycsId,
                'enrollment_date' => $enrollment_date,
                'operator_id' => (int) ($this->user_id ?? 1),
                'student_type_id' => $studentTypeId,
            ]);
            $proc->closeCursor();

            $out = $this->db->query("SELECT @out_student_id AS student_id, @out_admission_no AS admission_no, @out_enrollment_id AS enrollment_id, @out_obligations AS obligations_generated")->fetch(PDO::FETCH_ASSOC);
            $student_id = (int) ($out['student_id'] ?? 0);
            $enrollment_id = $out['enrollment_id'] ?? null;
            $fee_obligations_created = (int) ($out['obligations_generated'] ?? 0);
            if ($enrollment_id) {
                $this->extraChargeService->generateEnrollmentObligations((int) $enrollment_id);
            }

            // The placement procedure has now generated the student's fee
            // obligations. Allocate every verified pre-placement payment
            // only at this point; posting earlier would leave it unallocated.
            $postedPaymentCount = 0;
            if ($this->paymentService->hasPositivePayment((int) $application_id)) {
                $postedPaymentCount = $this->paymentService->postApplicationPaymentsToStudent(
                    (int) $application_id,
                    (int) $student_id,
                    !empty($application['parent_id']) ? (int) $application['parent_id'] : null,
                    (int) ($this->user_id ?? 1),
                    (string) ($application['application_no'] ?? '')
                );
            }

            $instance_data['student_id'] = (int) $student_id;
            $instance_data['enrollment_id'] = $enrollment_id;
            $instance_data['fee_obligations_created'] = $fee_obligations_created;
            $instance_data['payments_posted'] = $postedPaymentCount;
            $instance_data['enrollment_date'] = date('Y-m-d H:i:s');
            $instance_data['enrollment_completed'] = true;
            $instance_data['class_assigned'] = !empty($class_id);
            $instance_data['attendance_register_added'] = !empty($class_id);
            $this->saveWorkflowInstanceData((int) $instance['id'], $instance_data);

            $nextStage = ($instance['current_stage'] ?? '') === 'class_placement'
                ? 'fees_payment'
                : 'enrolled';
            if (($instance['current_stage'] ?? '') === 'class_placement'
                && $this->paymentService->getTotalRecorded((int) $application_id) >= $this->getAdmissionExtraChargesDue((int) $application_id)) {
                $nextStage = 'student_id_generation';
            }

            $this->advance(
                (int) $application_id,
                $nextStage,
                $nextStage === 'fees_payment'
                    ? 'class_placement_completed'
                    : ($nextStage === 'student_id_generation' ? 'payment_received' : 'enrollment_completed'),
                $instance_data,
                $nextStage === 'fees_payment'
                    ? 'Class/stream placement completed — awaiting payment'
                    : ($nextStage === 'student_id_generation'
                        ? 'Class/stream placement completed — verified payment already received'
                        : 'Enrollment completed')
            );

            $this->db->commit();

            try {
                $this->sendPlacementPaymentNotification((int) $application_id, (string) ($out['admission_no'] ?? ''), (int) $class_id, (int) $stream_id);
            } catch (\Throwable $notificationError) {
                \App\API\Services\Logger::legacyError('[AdmissionPaymentNotification] ' . $notificationError->getMessage());
            }

            return formatResponse(true, [
                'student_id' => $student_id,
                'enrollment_id' => $enrollment_id ?? null,
                'fee_obligations_created' => $fee_obligations_created ?? 0,
                'student_number' => $application['admission_no'] ?? null,
                'next_stage' => $nextStage
            ], 'Enrollment completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('enrollment_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    public function confirmEnrollment(int $applicationId, string $notes = ''): array
    {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            // Optional Director sign-off that runs after enrollment is complete.
            if (!$instance || !in_array(($instance['current_stage'] ?? ''), ['enrolled', 'director_confirmation'], true)) {
                throw new Exception('Application is not ready for Director confirmation');
            }

            $stmt = $this->db->prepare("SELECT * FROM admission_applications WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) {
                throw new Exception('Admission application not found');
            }
            if (($application['status'] ?? '') !== 'enrolled' || empty($application['enrolled_student_id'])) {
                throw new Exception('Only enrolled admission records can be confirmed');
            }
            if (!empty($application['director_confirmed_at'])) {
                throw new Exception('Admission record has already been confirmed');
            }

            $stmt = $this->db->prepare("UPDATE admission_applications
                SET director_confirmed_by = :confirmed_by,
                    director_confirmed_at = NOW(),
                    director_confirmation_notes = :notes
                WHERE id = :id");
            $stmt->execute([
                'confirmed_by' => (int) $this->user_id,
                'notes' => $notes,
                'id' => $applicationId,
            ]);

            \App\API\Includes\FileLogger::write('events', [
                'type' => 'event',
                'event_type' => 'enrollment_director_confirmed',
                'event_data' => [
                    'application_id' => (int) $applicationId,
                    'student_id' => (int) $application['enrolled_student_id'],
                    'confirmed_by' => (int) $this->user_id,
                    'notes' => $notes,
                ],
            ]);

            $instanceData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $instanceData['director_confirmed_by'] = (int) $this->user_id;
            $instanceData['director_confirmed_at'] = date('Y-m-d H:i:s');
            $instanceData['director_confirmation_notes'] = $notes;
            $this->saveWorkflowInstanceData((int) $instance['id'], $instanceData);

            $this->completeWorkflow((int) $instance['id'], $instanceData);
            $this->db->commit();

            return formatResponse(true, [
                'application_id' => $applicationId,
                'student_id' => (int) $application['enrolled_student_id'],
                'confirmed_at' => $instanceData['director_confirmed_at'],
                'workflow_status' => 'completed',
            ], 'Enrollment confirmed successfully');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('director_confirmation_failed', $e->getMessage());
            \App\API\Services\Logger::legacyError('[StudentAdmissionWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function generateApplicationNumber($year) {
        $sql = "SELECT COUNT(*) + 1 as next_num 
                FROM admission_applications 
                WHERE academic_year = :year";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['year' => $year]);
        $num = $stmt->fetchColumn();
        
        return sprintf("ADM/%s/%03d", $year, $num);
    }

    private function getCurrentAcademicYearId(): int
    {
        $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn();
        if ($yearId <= 0) {
            $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
        return $yearId;
    }

    private function generateStudentNumber(int $year, ?int $classId = null): string
    {
        if ($classId) {
            $classStmt = $this->db->prepare("SELECT name FROM classes WHERE id = :class_id LIMIT 1");
            $classStmt->execute(['class_id' => $classId]);
            $className = (string) ($classStmt->fetchColumn() ?: '');
            if ($className !== '') {
                $classCode = $this->deriveClassCode($className);
            }
        }

        $stmt = $this->db->prepare("
            SELECT COALESCE(
                MAX(CAST(SUBSTRING_INDEX(admission_no, '/', -1) AS UNSIGNED)),
                0
            ) + 1 AS next_num
            FROM students
            WHERE admission_no LIKE :prefix
        ");
        $stmt->execute(['prefix' => sprintf('%s/%d/%%', $classCode, $year)]);
        $num = (int) ($stmt->fetchColumn() ?: 1);

        return sprintf("%s/%d/%04d", $classCode, $year, $num);
    }

    private function deriveClassCode(string $className): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', strtoupper($className)));
        if ($normalized === '') {
            return 'STD';
        }

        return substr($normalized, 0, 10);
    }

    private function getRequiredDocuments($grade, string $category = 'standard', bool $isExistingParent = false) {
        return $this->policy->getRequiredDocuments((string) $grade, $category, $isExistingParent);
    }

    private function getApplicationGrade($application_id) {
        $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $application_id]);
        return $this->policy->normalizeGrade((string) ($stmt->fetchColumn() ?: ''));
    }

    private function requiresAssessment($grade) {
        return $this->policy->requiresInterview((string) $grade);
    }

    private function checkMandatoryDocuments($application_id) {
        $grade = $this->getApplicationGrade($application_id);
        $requiredConfig = $this->getRequiredDocuments($grade);
        $requiredTypes = [];

        foreach ($requiredConfig as $type => $config) {
            if (!empty($config['mandatory'])) {
                $requiredTypes[] = $type;
            }
        }

        if (empty($requiredTypes)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($requiredTypes), '?'));
        $sql = "SELECT DISTINCT document_type
                FROM admission_documents
                WHERE application_id = ?
                  AND document_type IN ({$placeholders})";
        $params = array_merge([(int) $application_id], $requiredTypes);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $uploadedTypes = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($requiredTypes as $requiredType) {
            if (!in_array($requiredType, $uploadedTypes, true)) {
                return false;
            }
        }

        return true;
    }

    private function checkAllDocumentsVerified($application_id) {
        $grade = $this->getApplicationGrade($application_id);
        $requiredConfig = $this->getRequiredDocuments($grade);
        $requiredTypes = [];

        foreach ($requiredConfig as $type => $config) {
            if (!empty($config['mandatory'])) {
                $requiredTypes[] = $type;
            }
        }

        if (empty($requiredTypes)) {
            return false;
        }

        $sql = "SELECT document_type, verification_status
                FROM admission_documents
                WHERE application_id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $application_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $verifiedByType = [];
        foreach ($rows as $row) {
            $docType = (string) ($row['document_type'] ?? '');
            if ($docType === '' || !in_array($docType, $requiredTypes, true)) {
                continue;
            }

            if (($row['verification_status'] ?? '') === 'verified') {
                $verifiedByType[$docType] = true;
            }
        }

        foreach ($requiredTypes as $requiredType) {
            if (empty($verifiedByType[$requiredType])) {
                return false;
            }
        }

        return true;
    }

    private function updateApplicationStatus($application_id, $status) {
        $sql = "UPDATE admission_applications SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $application_id]);
    }

    private function saveWorkflowInstanceData(int $instanceId, array $data): void
    {
        $stmt = $this->db->prepare("UPDATE workflow_instances SET data_json = :data_json WHERE id = :id");
        $stmt->execute([
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'id' => $instanceId
        ]);
    }

    private function calculatePlacementFees(int $classId, int $applicationId): float
    {
        $stmt = $this->db->prepare("
            SELECT c.level_id, aa.academic_year
            FROM classes c
            JOIN admission_applications aa ON aa.id = :application_id
            WHERE c.id = :class_id
            LIMIT 1
        ");
        $stmt->execute([
            'application_id' => $applicationId,
            'class_id' => $classId
        ]);
        $context = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$context) {
            throw new Exception("Unable to resolve class/application context for placement offer");
        }

        $academicYear = (int) $context['academic_year'];
        $termId = $this->resolveAcademicTermId($academicYear);
        if ($termId === null) {
            throw new Exception("Unable to resolve academic term for year {$academicYear}");
        }

        $studentTypeId = $this->resolveDefaultStudentTypeId();
        if ($studentTypeId === null) {
            throw new Exception("Unable to resolve an active student type for fee calculation");
        }

        $sumStmt = $this->db->prepare("
            SELECT COALESCE(SUM(ayfs.amount), 0) AS total_fees
            FROM academic_year_fee_schedules ayfs
            JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
            WHERE ayc.class_id IN (SELECT c2.id FROM classes c2 WHERE c2.level_id = :level_id)
              AND ayc.academic_year_id = :academic_year_id
              AND ayfs.academic_year_term_id = :term_id
              AND ayfs.student_type_id = :student_type_id
              AND ayfs.status = 'active'
        ");
        $ayStmt = $this->db->prepare("SELECT id FROM academic_years WHERE year_code = :year LIMIT 1");
        $ayStmt->execute(['year' => (string) $academicYear]);
        $academicYearId = (int) ($ayStmt->fetchColumn() ?: 0);
        $sumStmt->execute([
            'level_id' => (int) ($context['level_id'] ?? 0),
            'academic_year_id' => $academicYearId ?: $academicYear,
            'term_id' => $termId,
            'student_type_id' => $studentTypeId
        ]);
        $totalFees = (float) $sumStmt->fetchColumn();

        if ($totalFees > 0) {
            return $totalFees;
        }

        $fallbackStmt = $this->db->prepare("
            SELECT COALESCE(SUM(ayfs.amount), 0) AS total_fees
            FROM academic_year_fee_schedules ayfs
            JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
            WHERE ayc.class_id IN (SELECT c2.id FROM classes c2 WHERE c2.level_id = :level_id)
              AND ayc.academic_year_id = :academic_year_id
              AND ayfs.academic_year_term_id = :term_id
              AND ayfs.status = 'active'
        ");
        $fallbackStmt->execute([
            'level_id' => (int) ($context['level_id'] ?? 0),
            'academic_year_id' => $academicYearId ?: $academicYear,
            'term_id' => $termId
        ]);

        return (float) $fallbackStmt->fetchColumn();
    }

    private function resolveAcademicTermId(int $academicYear): ?int
    {
        $stmt = $this->db->prepare("
            SELECT ayt.id
            FROM academic_year_terms ayt
            JOIN academic_years ay ON ay.id = ayt.academic_year_id
            JOIN terms t ON t.id = ayt.term_id
            WHERE ay.year_code = :year_code
              AND ayt.status = 'current'
            ORDER BY t.id ASC
            LIMIT 1
        ");
        $stmt->execute(['year_code' => (string) $academicYear]);
        $termId = $stmt->fetchColumn();
        if ($termId) {
            return (int) $termId;
        }

        $fallbackStmt = $this->db->prepare("
            SELECT ayt.id
            FROM academic_year_terms ayt
            JOIN academic_years ay ON ay.id = ayt.academic_year_id
            JOIN terms t ON t.id = ayt.term_id
            WHERE ay.year_code = ?
            ORDER BY t.id ASC
            LIMIT 1
        ");
        $fallbackStmt->execute([(string) $academicYear]);
        $fallbackTermId = $fallbackStmt->fetchColumn();

        return $fallbackTermId ? (int) $fallbackTermId : null;
    }

    private function resolveDefaultStudentTypeId(): ?int
    {
        $stmt = $this->db->query("
            SELECT id
            FROM student_types
            WHERE code = 'DAY' AND status = 'active'
            LIMIT 1
        ");
        $studentTypeId = $stmt->fetchColumn();
        if ($studentTypeId) {
            return (int) $studentTypeId;
        }

        $fallbackStmt = $this->db->query("
            SELECT id
            FROM student_types
            WHERE status = 'active'
            ORDER BY id ASC
            LIMIT 1
        ");
        $fallbackId = $fallbackStmt->fetchColumn();
        return $fallbackId ? (int) $fallbackId : null;
    }


    private function getWorkflowInstanceByReference($ref_type, $ref_id) {
        $sql = "SELECT * FROM workflow_instances 
                WHERE reference_type = :type 
                AND reference_id = :id 
                AND status = 'in_progress'
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['type' => $ref_type, 'id' => $ref_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Link a parent to a student in the student_parents junction table
     */
    private function linkParentToStudent($student_id, $parent_id, $relationship = null)
    {
        $validRelationships = [
            'father',
            'mother',
            'guardian',
            'step_father',
            'step_mother',
            'grandparent',
            'uncle',
            'aunt',
            'sibling',
            'other'
        ];

        if (!in_array((string) $relationship, $validRelationships, true)) {
            $relationship = $this->resolveParentRelationship($parent_id);
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM student_parents WHERE student_id = :student_id");
        $countStmt->execute(['student_id' => $student_id]);
        $existingCount = (int) $countStmt->fetchColumn();

        $isPrimaryContact = $existingCount === 0 ? 1 : 0;
        $isEmergencyContact = $isPrimaryContact;

        $sql = "INSERT INTO student_parents (
                    student_id,
                    parent_id,
                    relationship,
                    is_primary_contact,
                    is_emergency_contact
                ) VALUES (
                    :student_id,
                    :parent_id,
                    :relationship,
                    :is_primary_contact,
                    :is_emergency_contact
                )
                ON DUPLICATE KEY UPDATE
                    relationship = VALUES(relationship),
                    is_primary_contact = VALUES(is_primary_contact),
                    is_emergency_contact = VALUES(is_emergency_contact)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id' => $student_id,
            'parent_id' => $parent_id,
            'relationship' => $relationship,
            'is_primary_contact' => $isPrimaryContact,
            'is_emergency_contact' => $isEmergencyContact
        ]);

        if ($isPrimaryContact === 1) {
            $unsetStmt = $this->db->prepare("
                UPDATE student_parents
                SET is_primary_contact = 0
                WHERE student_id = :student_id AND parent_id != :parent_id
            ");
            $unsetStmt->execute([
                'student_id' => $student_id,
                'parent_id' => $parent_id
            ]);
        }
    }

    private function resolveParentRelationship($parent_id)
    {
        $existingStmt = $this->db->prepare("
            SELECT relationship
            FROM student_parents
            WHERE parent_id = :parent_id
            ORDER BY is_primary_contact DESC, is_emergency_contact DESC
            LIMIT 1
        ");
        $existingStmt->execute(['parent_id' => $parent_id]);
        $existingRelationship = $existingStmt->fetchColumn();
        if ($existingRelationship) {
            return $existingRelationship;
        }

        $parentStmt = $this->db->prepare("SELECT gender FROM parents WHERE id = :parent_id LIMIT 1");
        $parentStmt->execute(['parent_id' => $parent_id]);
        $gender = strtolower((string) $parentStmt->fetchColumn());

        if ($gender === 'male') {
            return 'father';
        }
        if ($gender === 'female') {
            return 'mother';
        }

        return 'guardian';
    }

    private function sendInterviewNotifications(int $interviewId, array $session, string $eventSuffix, ?array $assignment = null): void
    {
        if ($assignment === null) {
            $stmt = $this->db->prepare("SELECT ai.id,ai.interviewer_id,aa.applicant_name,aa.application_no,
                    pp.phone AS parent_phone,pp.email AS parent_email
                FROM admission_interviews ai JOIN admission_applications aa ON aa.id=ai.application_id
                JOIN parents p ON p.id=aa.parent_id LEFT JOIN persons pp ON pp.id=p.person_id WHERE ai.id=? LIMIT 1");
            $stmt->execute([$interviewId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        if (!$assignment) return;

        $body = 'KingsWay Admissions: ' . ($assignment['applicant_name'] ?? 'Applicant') . ' (' . ($assignment['application_no'] ?? '') . ') interview is scheduled on ' . $session['session_date'] . ' at ' . substr((string) $session['start_time'], 0, 5) . ' at ' . ($session['venue'] ?? 'Main Office') . '.';
        $business = new \App\API\Services\CommunicationBusinessEventService($this->db);
        $platform = new \App\API\Services\CommunicationPlatformService($this->db);

        // Parent/guardian receives both channels. Each channel has its own
        // idempotency key so one failed/missing endpoint does not suppress the other.
        foreach (['sms', 'email'] as $channel) {
            $key = 'admission-interview:' . $interviewId . ':' . ($session['session_id'] ?? 0) . ':' . $channel . ':' . $eventSuffix;
            $check = $this->db->prepare("SELECT id FROM communication_business_events WHERE event_code='admission_interview_notification' AND event_key=? LIMIT 1");
            $check->execute([$key]);
            if ($check->fetchColumn()) continue;
            $eventId = $business->getOrCreate('admission_interview_notification', $key, date('Y-m-d H:i:s'), (int) ($this->user_id ?: 1));
            $queued = $platform->queueRenderedForContacts(
                [['user_id' => null, 'phone' => $assignment['parent_phone'] ?? null, 'email' => $assignment['parent_email'] ?? null]],
                $channel,
                'Admission interview schedule',
                $body,
                ['purpose' => 'admissions', 'sender_id' => (int) ($this->user_id ?: 1), 'business_event_id' => $eventId]
            );
            $business->markProcessed($eventId);
            if (!empty($queued['communication_id'])) {
                try {
                    $dispatch = new \App\API\Services\CommunicationOutboxService($this->db);
                    $dispatch->processOne((int) $queued['communication_id']);
                } catch (\Throwable $dispatchError) {
                    // The durable outbox record remains queued for the worker.
                    \App\API\Services\Logger::legacyError('[AdmissionInterviewNotification] immediate dispatch deferred: ' . $dispatchError->getMessage());
                }
            }
        }

        // Teacher notification is internal. The UI exposes the teacher phone for
        // the school's follow-up call; no unsolicited teacher SMS is sent here.
        $teacherId = (int) ($session['interviewer_id'] ?? $assignment['interviewer_id'] ?? 0);
        if ($teacherId > 0) {
            $title = 'Admission interview assignment';
            $message = 'You are assigned to interview ' . ($assignment['applicant_name'] ?? 'an applicant') . ' (' . ($assignment['application_no'] ?? '') . ') on ' . $session['session_date'] . ' at ' . substr((string) $session['start_time'], 0, 5) . ' at ' . ($session['venue'] ?? 'Main Office') . '. Follow-up calls may be made to your registered phone.';
            $check = $this->db->prepare("SELECT n.id FROM notifications n JOIN users u ON u.id=n.user_id JOIN staff s ON s.id=? WHERE s.id=? AND n.type='admission_interview' AND n.reference_id=? AND n.title=? LIMIT 1");
            $check->execute([$teacherId, $teacherId, $interviewId, $title]);
            if (!$check->fetchColumn()) {
                (new \App\API\Services\NotificationService($this->db))->push(
                    'staff:' . $teacherId,
                    'admission_interview',
                    $title,
                    $message,
                    'high',
                    ['reference_type' => 'admission_interview', 'reference_id' => $interviewId, 'action_url' => '/home.php?route=admission_interviews']
                );
            }
        }
    }

    private function sendInterviewSMS($application_id, $date, $time, $venue) {
        $stmt = $this->db->prepare("
            SELECT parent_id, applicant_name
            FROM admission_applications
            WHERE id = :application_id
            LIMIT 1
        ");
        $stmt->execute(['application_id' => $application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application || empty($application['parent_id'])) {
            $this->logAction('sms_skipped', $application_id, 'Interview SMS skipped - no linked parent');
            return;
        }

        $message = sprintf(
            "KingsWay Admissions: %s interview is scheduled on %s at %s, venue %s.",
            (string) ($application['applicant_name'] ?? 'Applicant'),
            (string) $date,
            (string) $time,
            (string) $venue
        );

        \App\API\Includes\FileLogger::write('communications', [
            'type' => 'sms_queue',
            'message_type' => 'admission_interview',
            'parent_ids' => (string) $application['parent_id'],
            'message' => $message,
            'template_id' => null,
            'sent_by' => (int) $this->user_id,
        ]);

        $this->logAction('sms_sent', $application_id, "Interview notification queued for $date at $time");
    }

    private function sendPlacementOfferNotification($application_id, $fees) {
        $stmt = $this->db->prepare("
            SELECT parent_id, applicant_name
            FROM admission_applications
            WHERE id = :application_id
            LIMIT 1
        ");
        $stmt->execute(['application_id' => $application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application || empty($application['parent_id'])) {
            $this->logAction('placement_offer_sent', $application_id, "Placement offer logged without SMS. Total fees: $fees");
            return;
        }

        $message = sprintf(
            "KingsWay Admissions: Placement offer ready for %s. Total term fees: KES %s.",
            (string) ($application['applicant_name'] ?? 'Applicant'),
            number_format((float) $fees, 2)
        );

        \App\API\Includes\FileLogger::write('communications', [
            'type' => 'sms_queue',
            'message_type' => 'admission_offer',
            'parent_ids' => (string) $application['parent_id'],
            'message' => $message,
            'template_id' => null,
            'sent_by' => (int) $this->user_id,
        ]);

        $this->logAction('placement_offer_sent', $application_id, "Placement offer sent. Total fees: $fees");
    }

    /**
     * Check if a parent already exists by national ID number.
     * Returns ['parent_id' => int, 'person_id' => int, 'first_name' => ..., ...] or null.
     */
    private function findExistingParentByNationalId(string $nationalIdNo): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.id AS person_id, p.first_name, p.last_name, p.email, p.phone,
                   p.national_id_no, p.gender, p.dob,
                   par.id AS parent_id, par.occupation, par.address
            FROM persons p
            JOIN parents par ON par.person_id = p.id
            WHERE p.national_id_no = :id_no
            LIMIT 1
        ");
        $stmt->execute(['id_no' => $nationalIdNo]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Look up all children for a given parent (via student_parents junction).
     */
    private function getParentChildren(int $parentId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.id AS student_id, s.admission_no,
                   CONCAT(ps.first_name, ' ', COALESCE(ps.middle_name,''), ' ', ps.last_name) AS child_name,
                   s.status, c.name AS class_name, ay.year_code
            FROM student_parents sp
            JOIN students s ON s.id = sp.student_id
            JOIN persons ps ON ps.id = s.person_id
            LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
            LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            LEFT JOIN academic_years ay ON ay.id = sae.academic_year_id
            WHERE sp.parent_id = ?
            ORDER BY s.admission_date DESC
        ");
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
