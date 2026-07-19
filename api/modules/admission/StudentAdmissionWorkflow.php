<?php
namespace App\API\Modules\admission;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
use App\Config\Config;
Config::init();
require_once __DIR__ . '/../../includes/WorkflowHandler.php';

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Student Admission Workflow Handler
 * 
 * 7-STAGE WORKFLOW:
 * 1. Application Submission → 2. Document Verification → 3. Interview Scheduling
 * → 4. Interview Assessment → 5. Placement Offer → 6. Fee Payment → 7. Enrollment
 * 
 * Database Objects Used:
 * - Tables: admission_applications, admission_documents
 * - Procedures: sp_get_class_fee_schedule, sp_process_student_payment, generate_student_number
 * - Functions: calculate_total_fees
 */
class StudentAdmissionWorkflow extends WorkflowHandler {
    private AdmissionPolicy $policy;
    private AdmissionPaymentService $paymentService;

    public function __construct() {
        parent::__construct('student_admission');
        $this->policy = new AdmissionPolicy();
        $this->paymentService = new AdmissionPaymentService($this->db);
    }

    /**
     * =======================================================================
     * STAGE 1: APPLICATION SUBMISSION
     * =======================================================================
     * Role: Registrar/Parent
     * Creates admission application and starts workflow
     */
    public function submitApplication($data) {
        try {
            // Validate required fields
            $required = ['applicant_name', 'date_of_birth', 'gender', 'grade_applying_for', 'academic_year', 'parent_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: $field");
                }
            }

            $applicationSource = $this->policy->resolveApplicationSource($data);
            $admissionCategory = $this->policy->resolveAdmissionCategory($data);
            $targetTermId = $this->policy->resolveTargetTermId($data);
            $normalizedGrade = $this->policy->normalizeGrade((string) $data['grade_applying_for']);
            $requiresInterview = $this->policy->requiresInterview($normalizedGrade) ? 1 : 0;
            $interviewReason = $this->policy->describeInterviewPolicy($normalizedGrade);

            // Generate application number (format: ADM/2025/001)
            $app_no = $this->generateApplicationNumber($data['academic_year']);

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
                'name' => $data['applicant_name'],
                'dob' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'grade' => $normalizedGrade,
                'year' => $data['academic_year'],
                'parent' => $data['parent_id'],
                'application_source' => $applicationSource,
                'admission_category' => $admissionCategory,
                'target_term_id' => $targetTermId,
                'requires_interview' => $requiresInterview,
                'interview_policy_reason' => $interviewReason,
                'prev_school' => $data['previous_school'] ?? null,
                'has_needs' => $data['has_special_needs'] ?? 0,
                'needs_details' => $data['special_needs_details'] ?? null
            ]);

            $application_id = $this->db->lastInsertId();

            $workflow_data = [
                'application_no' => $app_no,
                'applicant_name' => $data['applicant_name'],
                'grade' => $normalizedGrade,
                'parent_id' => (int) $data['parent_id'],
                'application_source' => $applicationSource,
                'admission_category' => $admissionCategory,
                'target_term_id' => $targetTermId,
                'requires_interview' => (bool) $requiresInterview,
                'interview_policy_reason' => $interviewReason,
                'created_by' => (int) $this->user_id,
                'submitted_by' => (int) $this->user_id
            ];

            $instance_id = $this->startWorkflow('admission_application', $application_id, $workflow_data);

            return formatResponse(true, [
                'application_id' => $application_id,
                'application_no' => $app_no,
                'workflow_instance_id' => $instance_id,
                'current_stage' => 'application_received',
                'next_stage' => 'application_review',
                'policy' => [
                    'requires_interview' => (bool) $requiresInterview,
                    'interview_reason' => $interviewReason,
                    'application_source' => $applicationSource,
                    'admission_category' => $admissionCategory,
                    'target_term_id' => $targetTermId,
                ],
                'required_documents' => $this->getRequiredDocuments($normalizedGrade, $admissionCategory)
            ], 'Application submitted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('admission_submit_failed', $e->getMessage());
            return formatResponse(false, null, 'Application submission failed: ' . $e->getMessage());
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
            $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
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
            $advanceEligibleStages = ['application_received', 'application_review', 'documents_upload'];
            if ($all_uploaded && in_array($currentStage, $advanceEligibleStages, true)) {
                $this->advance(
                    $application_id,
                    'documents_verification',
                    'all_documents_uploaded',
                    ['documents_uploaded' => true, 'documents_uploaded_at' => date('Y-m-d H:i:s')],
                    'All mandatory documents uploaded'
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
            return formatResponse(false, null, 'Document upload failed: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Verification failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 3: INTERVIEW SCHEDULING
     * =======================================================================
     * Role: Registrar
     * Schedule interview with applicant/parent
     * NOTE: Only for Grade2-6 students. ECD, PP1, PP2, Grade1, and Grade7 skip this stage.
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
            return formatResponse(false, null, 'Scheduling failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 4: INTERVIEW ASSESSMENT
     * =======================================================================
     * Role: Head Teacher
     * Conduct and record interview assessment
     * NOTE: Only for Grade2-6 students. ECD, PP1, PP2, Grade1, and Grade7 skip this stage.
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

            // Store assessment results
            $sql = "UPDATE workflow_instances 
                    SET data_json = JSON_SET(
                        COALESCE(data_json, '{}'),
                        '$.assessment_score', :score,
                        '$.assessment_notes', :notes,
                        '$.assessed_by', :assessor,
                        '$.assessment_date', NOW()
                    )
                    WHERE id = :instance_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'score' => $assessment_data['score'],
                'notes' => $assessment_data['notes'] ?? '',
                'assessor' => $this->user_id,
                'instance_id' => $instance['id']
            ]);

            // Determine if qualified (e.g., score >= 70)
            if ($assessment_data['score'] >= 70) {
                // Passed → admission decision stage
                $this->advance(
                    $application_id,
                    'admission_decision',
                    'assessment_passed',
                    [
                        'interview_passed' => true,
                        'interview_score' => $assessment_data['score'],
                        'interview_notes' => $assessment_data['notes'] ?? ''
                    ],
                    'Interview passed — proceeding to admission decision'
                );
            } else {
                // Failed → rejected stage (audit-logged). status stays visible to all.
                $this->advance(
                    $application_id,
                    'rejected',
                    'assessment_failed',
                    [
                        'interview_passed' => false,
                        'interview_score' => $assessment_data['score'],
                        'rejection_reason' => 'Did not meet interview requirements'
                    ],
                    'Interview failed — application rejected'
                );
            }

            $this->db->commit();

            return formatResponse(true, null, $assessment_data['score'] >= 70 ?
                'Assessment passed. Ready for placement offer.' :
                'Assessment not passed. Application cancelled.');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('interview_assessment_failed', $e->getMessage());
            return formatResponse(false, null, 'Assessment failed: ' . $e->getMessage());
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
            if (!$instance || !in_array(($instance['current_stage'] ?? ''), ['admission_decision', 'fees_payment'], true)) {
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
            return formatResponse(false, null, 'Placement offer failed: ' . $e->getMessage());
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
            if (!$instance || $instance['current_stage'] !== 'fee_payment') {
                throw new Exception("Invalid workflow state for fee payment");
            }

            $amount = isset($payment_data['amount']) ? (float) $payment_data['amount'] : 0.0;
            if ($amount <= 0) {
                throw new Exception("Payment amount must be greater than zero");
            }

            $payment = $this->paymentService->recordApplicationPayment((int) $application_id, $payment_data, (int) $this->user_id);

            $instanceData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $instanceData['last_payment_recorded_at'] = date('Y-m-d H:i:s');
            $instanceData['last_admission_payment_id'] = $payment['payment_id'];
            $this->saveWorkflowInstanceData((int) $instance['id'], $instanceData);

            // Update application status
            $this->updateApplicationStatus($application_id, 'fees_pending');

            // Any payment recorded allows advancement to enrollment
            // The school determines minimum payment requirements outside this workflow
            if ($amount > 0) {
                // Advance to student ID generation (proc maps stage → status 'fees_paid')
                $this->advance(
                    $application_id,
                    'student_id_generation',
                    'payment_received',
                    ['payment_status' => 'paid', 'last_payment_recorded_at' => date('Y-m-d H:i:s'), 'last_admission_payment_id' => $payment['payment_id'] ?? null],
                    'Admission fee payment recorded'
                );
            }

            $this->db->commit();

            return formatResponse(true, [
                'payment_id' => $payment['payment_id'],
                'amount_paid' => $amount,
                'receipt_no' => $payment['receipt_no'],
                'reference_no' => $payment['reference_no'],
                'can_enroll' => $amount > 0
            ], 'Payment recorded successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('fee_payment_failed', $e->getMessage());
            return formatResponse(false, null, 'Payment recording failed: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Class space check failed: ' . $e->getMessage());
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
            if (!in_array($stage, ['interview_results', 'admission_decision', 'class_space_check'], true)) {
                throw new Exception("Application cannot be admitted from stage '{$stage}'");
            }

            $this->advance(
                $applicationId,
                'provisional_student_creation',
                'student_admitted',
                ['admission_approved' => true, 'admitted_at' => date('Y-m-d H:i:s')],
                'Student admitted — proceed to provisional student creation'
            );

            return formatResponse(true, ['next_stage' => 'provisional_student_creation'], 'Student admitted.');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Admission failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 8: CREATE PROVISIONAL STUDENT
     * =======================================================================
     * Role: Registrar
     * Builds the real students row for the admitted application. Dedup-guarded:
     * if a students row already exists for this application it is returned
     * instead of creating a duplicate. Advances to fees_payment.
     */
    public function createProvisionalStudent(int $applicationId): array
    {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $applicationId);
            if (!$instance) {
                throw new Exception('No active workflow instance found');
            }
            if (($instance['current_stage'] ?? '') !== 'provisional_student_creation') {
                throw new Exception('Application is not at the provisional student creation stage');
            }

            $stmt = $this->db->prepare("SELECT * FROM admission_applications WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $applicationId]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) {
                throw new Exception('Admission application not found');
            }

            // Dedup guard: existing provisional/created student for this application.
            $stmt = $this->db->prepare("SELECT student_id FROM admission_applications WHERE id = :id AND enrolled_student_id IS NOT NULL LIMIT 1");
            $stmt->execute(['id' => $applicationId]);
            $existingStudentId = $stmt->fetchColumn();
            if ($existingStudentId) {
                $this->db->commit();
                return formatResponse(true, [
                    'student_id' => (int) $existingStudentId,
                    'admission_number' => $application['admission_no'] ?? null,
                    'reused' => true
                ], 'Student already created for this application.');
            }

            // Resolve the applied class by name, then a real stream (NOT NULL + capacity trigger).
            $classId = null;
            $className = trim((string) ($application['grade_applying_for'] ?? ''));
            if ($className !== '') {
                $stmt = $this->db->prepare("SELECT id FROM classes WHERE name = :name LIMIT 1");
                $stmt->execute(['name' => $className]);
                $classId = $stmt->fetchColumn() ?: null;
            }
            if (!$classId) {
                throw new Exception("Could not resolve a class for grade '{$className}'");
            }

            $stmt = $this->db->prepare("SELECT id FROM class_streams WHERE class_id = :class_id AND status = 'active' ORDER BY id ASC LIMIT 1");
            $stmt->execute(['class_id' => $classId]);
            $streamId = $stmt->fetchColumn() ?: null;
            if (!$streamId) {
                throw new Exception("No active class stream configured for '{$className}'");
            }

            $academicYearId = (int) ($application['academic_year'] ?? date('Y'));
            $studentNumber = $this->generateStudentNumber($academicYearId, (int) $classId);

            $names = explode(' ', trim((string) $application['applicant_name']));
            $firstName = $names[0] ?? 'Applicant';
            $lastName = isset($names[1]) ? implode(' ', array_slice($names, 1)) : '';

            $studentTypeId = $this->resolveDefaultStudentTypeId();
            if (!$studentTypeId) {
                throw new Exception('Unable to resolve an active student type');
            }

            $sql = "INSERT INTO students (
                admission_no, first_name, last_name, date_of_birth,
                gender, stream_id, student_type_id, admission_date, status, application_id
            ) VALUES (
                :student_no, :first_name, :last_name, :dob,
                :gender, :stream_id, :student_type_id, CURDATE(), 'inactive', :application_id
            )";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'student_no' => $studentNumber,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'dob' => $application['date_of_birth'],
                'gender' => $application['gender'],
                'stream_id' => (int) $streamId,
                'student_type_id' => (int) $studentTypeId,
                'application_id' => $applicationId,
            ]);
            $studentId = (int) $this->db->lastInsertId();

            // Link the parent record from the application, if present.
            if (!empty($application['parent_id'])) {
                $this->linkParentToStudent($studentId, (int) $application['parent_id']);
            }

            // Write back linkage to the application row.
            $stmt = $this->db->prepare("UPDATE admission_applications SET enrolled_student_id = :student_id WHERE id = :id");
            $stmt->execute(['student_id' => $studentId, 'id' => $applicationId]);

            $this->db->commit();

            $this->advance(
                $applicationId,
                'fees_payment',
                'provisional_student_created',
                ['student_id' => $studentId, 'student_number' => $studentNumber, 'admission_number' => $studentNumber],
                'Provisional student created — awaiting fee payment'
            );

            return formatResponse(true, [
                'student_id' => $studentId,
                'admission_number' => $studentNumber,
                'class_id' => (int) $classId,
                'stream_id' => (int) $streamId
            ], 'Provisional student created successfully.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Provisional student creation failed: ' . $e->getMessage());
        }
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

            $cardGenerator = new \App\Modules\Students\StudentIDCardGenerator();
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
                'final_approval',
                'student_id_card_generated',
                ['student_id_card_generated' => true, 'student_id_card_id' => $cardId],
                'Student ID card generated — awaiting final approval'
            );

            return formatResponse(true, ['card_id' => $cardId, 'next_stage' => 'final_approval'], 'Student ID card generated.');
        } catch (Exception $e) {
            return formatResponse(false, null, 'ID card generation failed: ' . $e->getMessage());
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
            if (!$instance || ($instance['current_stage'] ?? '') !== 'final_approval') {
                throw new Exception('Application is not at the final approval stage');
            }

            $this->advance(
                $applicationId,
                'enrollment',
                'final_approval_granted',
                ['final_approval_done' => true, 'final_approval_at' => date('Y-m-d H:i:s')],
                'Final approval granted'
            );

            return formatResponse(true, ['next_stage' => 'enrollment'], 'Final approval granted.');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Final approval failed: ' . $e->getMessage());
        }
    }

    /**
     * =======================================================================
     * STAGE 7: ENROLLMENT
     * =======================================================================
     * Role: Registrar
     * Complete student enrollment and create student record
     */
    public function completeEnrollment($application_id) {
        try {
            $this->db->beginTransaction();

            $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
            if (!$instance || $instance['current_stage'] !== 'enrollment') {
                throw new Exception("Invalid workflow state for enrollment");
            }

            // Get application details
            $sql = "SELECT * FROM admission_applications WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $application_id]);
            $application = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$application) {
                throw new Exception('Admission application not found');
            }
            if (($application['status'] ?? '') === 'enrolled') {
                throw new Exception('Application is already enrolled');
            }
            if (!$this->paymentService->hasPositivePayment((int) $application_id)) {
                throw new Exception('A positive admission payment is required before enrollment');
            }

            // The provisional student was created at step 8 (createProvisionalStudent).
            // Reuse it rather than inserting a second student row.
            $student_id = (int) ($application['enrolled_student_id'] ?? 0);
            if (!$student_id) {
                throw new Exception('No provisional student linked — run create provisional student first');
            }

            // Get assigned class and stream from workflow data.
            $instance_data = json_decode($instance['data_json'], true) ?: [];
            $class_id = $instance_data['assigned_class_id'] ?? null;
            $studentTypeId = $this->resolveDefaultStudentTypeId();
            if (!$studentTypeId) {
                throw new Exception('Unable to resolve an active student type for enrollment');
            }

            // Determine stream: prefer assigned, else the provisional student's stream.
            $stream_id = $instance_data['assigned_stream_id'] ?? null;
            if (!$stream_id) {
                $stmt = $this->db->prepare("SELECT stream_id FROM students WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $student_id]);
                $stream_id = $stmt->fetchColumn() ?: null;
            }
            if (!$stream_id) {
                $stmt = $this->db->prepare("SELECT id FROM class_streams WHERE class_id = :class_id LIMIT 1");
                $stmt->execute(['class_id' => $class_id]);
                $stream_id = $stmt->fetchColumn() ?: null;
            }
            if (!$stream_id) {
                throw new Exception('No class stream is configured for the selected placement class');
            }

            // Get current academic year
            $academic_year_id = (int) $this->getCurrentAcademicYearId();
            if (!$academic_year_id) {
                throw new Exception('No active academic year found for enrollment');
            }

            // Activate the provisional student and lock in placement.
            $stmt = $this->db->prepare("
                UPDATE students
                SET status = 'active', stream_id = :stream_id, student_type_id = :student_type_id,
                    admission_date = CURDATE()
                WHERE id = :id
            ");
            $stmt->execute([
                'stream_id' => (int) $stream_id,
                'student_type_id' => (int) $studentTypeId,
                'id' => $student_id,
            ]);

            // Create class enrollment record using stored procedure
            if ($class_id && $stream_id) {
                $stmt = $this->db->prepare("CALL sp_complete_student_enrollment(:student_id, :class_id, :stream_id, :year_id, @enr_id, @fees)");
                $stmt->execute([
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    'stream_id' => $stream_id,
                    'year_id' => $academic_year_id
                ]);
                $stmt->closeCursor();

                $result = $this->db->query("SELECT @enr_id as enrollment_id, @fees as fee_obligations")->fetch(PDO::FETCH_ASSOC);
                $enrollment_id = $result['enrollment_id'];
                $fee_obligations_created = $result['fee_obligations'];
            }

            // Link parent from application
            if (!empty($application['parent_id'])) {
                $this->linkParentToStudent($student_id, (int) $application['parent_id']);
            }

            // Post admission payments that were captured before enrollment.
            $postedPaymentCount = $this->paymentService->postApplicationPaymentsToStudent(
                (int) $application_id,
                (int) $student_id,
                !empty($application['parent_id']) ? (int) $application['parent_id'] : null,
                (int) $this->user_id,
                (string) ($application['application_no'] ?? '')
            );

            // Update application status and link created student
            $stmt = $this->db->prepare("UPDATE admission_applications SET status = 'enrolled', enrolled_student_id = :student_id, enrolled_at = NOW() WHERE id = :id");
            $stmt->execute([
                'student_id' => (int) $student_id,
                'id' => (int) $application_id,
            ]);

            $instance_data['student_id'] = (int) $student_id;
            $instance_data['enrollment_id'] = $enrollment_id ?? null;
            $instance_data['fee_obligations_created'] = $fee_obligations_created ?? 0;
            $instance_data['payments_posted'] = $postedPaymentCount;
            $instance_data['enrollment_date'] = date('Y-m-d H:i:s');
            $instance_data['enrollment_completed'] = true;
            $instance_data['class_assigned'] = !empty($class_id);
            $instance_data['attendance_register_added'] = !empty($class_id);
            $this->saveWorkflowInstanceData((int) $instance['id'], $instance_data);

            // Advance to the terminal 'enrolled' stage (final approval already done).
            $this->advance(
                (int) $application_id,
                'enrolled',
                'enrollment_completed',
                $instance_data,
                'Enrollment completed'
            );

            $this->db->commit();

            return formatResponse(true, [
                'student_id' => $student_id,
                'enrollment_id' => $enrollment_id ?? null,
                'fee_obligations_created' => $fee_obligations_created ?? 0,
                'student_number' => $application['admission_no'] ?? null
            ], 'Enrollment completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('enrollment_failed', $e->getMessage());
            return formatResponse(false, null, 'Enrollment failed: ' . $e->getMessage());
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

            $stmt = $this->db->prepare("INSERT INTO admission_enrollment_confirmations
                (application_id, student_id, confirmed_by, confirmed_at, notes, created_at)
                VALUES (:application_id, :student_id, :confirmed_by, NOW(), :notes, NOW())
                ON DUPLICATE KEY UPDATE notes = VALUES(notes)");
            $stmt->execute([
                'application_id' => $applicationId,
                'student_id' => (int) $application['enrolled_student_id'],
                'confirmed_by' => (int) $this->user_id,
                'notes' => $notes,
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
            return formatResponse(false, null, 'Director confirmation failed: ' . $e->getMessage());
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

    private function generateStudentNumber(int $year, ?int $classId = null): string
    {
        $classCode = 'STD';
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

    private function getRequiredDocuments($grade, string $category = 'standard') {
        return $this->policy->getRequiredDocuments((string) $grade, $category);
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
            SELECT COALESCE(SUM(fsd.amount), 0) AS total_fees
            FROM fee_structures_detailed fsd
            WHERE fsd.level_id = :level_id
              AND fsd.academic_year = :academic_year
              AND fsd.term_id = :term_id
              AND fsd.student_type_id = :student_type_id
              AND fsd.status = 'active'
        ");
        $sumStmt->execute([
            'level_id' => (int) $context['level_id'],
            'academic_year' => $academicYear,
            'term_id' => $termId,
            'student_type_id' => $studentTypeId
        ]);
        $totalFees = (float) $sumStmt->fetchColumn();

        if ($totalFees > 0) {
            return $totalFees;
        }

        // Fallback: use whichever student type has active fee lines for this level/term/year.
        $fallbackStmt = $this->db->prepare("
            SELECT COALESCE(SUM(fsd.amount), 0) AS total_fees
            FROM fee_structures_detailed fsd
            WHERE fsd.level_id = :level_id
              AND fsd.academic_year = :academic_year
              AND fsd.term_id = :term_id
              AND fsd.status = 'active'
        ");
        $fallbackStmt->execute([
            'level_id' => (int) $context['level_id'],
            'academic_year' => $academicYear,
            'term_id' => $termId
        ]);

        return (float) $fallbackStmt->fetchColumn();
    }

    private function resolveAcademicTermId(int $academicYear): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM academic_terms
            WHERE year = :year
              AND status = 'current'
            ORDER BY (status = 'current') DESC, term_number ASC
            LIMIT 1
        ");
        $stmt->execute(['year' => $academicYear]);
        $termId = $stmt->fetchColumn();
        if ($termId) {
            return (int) $termId;
        }

        $fallbackStmt = $this->db->prepare("
            SELECT id
            FROM academic_terms
            WHERE year = :year
            ORDER BY term_number ASC
            LIMIT 1
        ");
        $fallbackStmt->execute(['year' => $academicYear]);
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
        $financialResponsibility = $existingCount === 0 ? 100.00 : 0.00;

        $sql = "INSERT INTO student_parents (
                    student_id,
                    parent_id,
                    relationship,
                    is_primary_contact,
                    is_emergency_contact,
                    financial_responsibility,
                    created_at,
                    updated_at
                ) VALUES (
                    :student_id,
                    :parent_id,
                    :relationship,
                    :is_primary_contact,
                    :is_emergency_contact,
                    :financial_responsibility,
                    NOW(),
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    relationship = VALUES(relationship),
                    is_primary_contact = VALUES(is_primary_contact),
                    is_emergency_contact = VALUES(is_emergency_contact),
                    financial_responsibility = VALUES(financial_responsibility),
                    updated_at = NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'student_id' => $student_id,
            'parent_id' => $parent_id,
            'relationship' => $relationship,
            'is_primary_contact' => $isPrimaryContact,
            'is_emergency_contact' => $isEmergencyContact,
            'financial_responsibility' => $financialResponsibility
        ]);

        if ($isPrimaryContact === 1) {
            $unsetStmt = $this->db->prepare("
                UPDATE student_parents
                SET is_primary_contact = 0, updated_at = NOW()
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
            ORDER BY is_primary_contact DESC, is_emergency_contact DESC, id ASC
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

        $smsStmt = $this->db->prepare("
            CALL sp_send_sms_to_parents(
                :parent_ids,
                :message,
                :template_id,
                :message_type,
                :sent_by
            )
        ");
        $smsStmt->execute([
            'parent_ids' => (string) $application['parent_id'],
            'message' => $message,
            'template_id' => null,
            'message_type' => 'admission_interview',
            'sent_by' => (int) $this->user_id
        ]);
        $smsStmt->closeCursor();

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

        $smsStmt = $this->db->prepare("
            CALL sp_send_sms_to_parents(
                :parent_ids,
                :message,
                :template_id,
                :message_type,
                :sent_by
            )
        ");
        $smsStmt->execute([
            'parent_ids' => (string) $application['parent_id'],
            'message' => $message,
            'template_id' => null,
            'message_type' => 'admission_offer',
            'sent_by' => (int) $this->user_id
        ]);
        $smsStmt->closeCursor();

        $this->logAction('placement_offer_sent', $application_id, "Placement offer sent. Total fees: $fees");
    }
}
