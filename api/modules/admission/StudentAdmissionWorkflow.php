<?php
namespace App\API\Modules\admission;

require_once __DIR__ . '/../vendor/autoload.php';
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
    
    public function __construct() {
        parent::__construct('student_admission');
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

            // Generate application number (format: ADM/2025/001)
            $app_no = $this->generateApplicationNumber($data['academic_year']);

            // Insert application (outside transaction, committed immediately)
            $sql = "INSERT INTO admission_applications (
                application_no, applicant_name, date_of_birth, gender,
                grade_applying_for, academic_year, parent_id,
                previous_school, has_special_needs, special_needs_details,
                status, created_at
            ) VALUES (
                :app_no, :name, :dob, :gender, :grade, :year, :parent,
                :prev_school, :has_needs, :needs_details,
                'submitted', NOW()
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'app_no' => $app_no,
                'name' => $data['applicant_name'],
                'dob' => $data['date_of_birth'],
                'gender' => $data['gender'],
                'grade' => $data['grade_applying_for'],
                'year' => $data['academic_year'],
                'parent' => $data['parent_id'],
                'prev_school' => $data['previous_school'] ?? null,
                'has_needs' => $data['has_special_needs'] ?? 0,
                'needs_details' => $data['special_needs_details'] ?? null
            ]);

            $application_id = $this->db->lastInsertId();

            // Start workflow (which will handle its own transaction)
            $workflow_data = [
                'application_no' => $app_no,
                'applicant_name' => $data['applicant_name'],
                'grade' => $data['grade_applying_for'],
                'parent_id' => (int) $data['parent_id'],
                'created_by' => (int) $this->user_id,
                'submitted_by' => (int) $this->user_id
            ];

            $instance_id = $this->startWorkflow('admission_application', $application_id, $workflow_data);

            return formatResponse(true, [
                'application_id' => $application_id,
                'application_no' => $app_no,
                'workflow_instance_id' => $instance_id,
                'next_stage' => 'document_verification',
                'required_documents' => $this->getRequiredDocuments($data['grade_applying_for'])
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

            // Upload file via MediaManager into uploads/documents/{application_id}
            $mediaManager = new \App\API\Modules\system\MediaManager($this->db);
            $mediaId = $mediaManager->upload($file, 'documents', $application_id, null, $this->user_id, 'admission document');
            $preview = $mediaManager->getPreviewUrl($mediaId) ?: $mediaId;

            // Save document record
            $sql = "INSERT INTO admission_documents (
                application_id, document_type, document_path,
                is_mandatory, verification_status, created_at
            ) VALUES (:app_id, :type, :path, :mandatory, 'pending', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'app_id' => $application_id,
                'type' => $document_type,
                'path' => $preview,
                'mandatory' => $isMandatory
            ]);

            // Check if all mandatory docs uploaded
            $all_uploaded = $this->checkMandatoryDocuments($application_id);
            $currentStage = $instance['current_stage'] ?? null;

            if ($all_uploaded && in_array($currentStage, ['application', 'application_submission'], true)) {
                // Advance to document verification stage
                $this->advanceStage($instance['id'], 'document_verification', 'all_documents_uploaded');
                $this->updateApplicationStatus($application_id, 'documents_pending');
            }

            $this->db->commit();

            return formatResponse(true, [
                'document_id' => $this->db->lastInsertId(),
                'all_mandatory_uploaded' => $all_uploaded
            ], 'Document uploaded successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('document_upload_failed', $e->getMessage());
            return formatResponse(false, null, 'Document upload failed: ' . $e->getMessage());
        }
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

            // Check if all documents verified
            if ($this->checkAllDocumentsVerified($application_id)) {
                $instance = $this->getWorkflowInstanceByReference('admission_application', $application_id);
                
                // Get application details to check grade
                $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['id' => $application_id]);
                $grade = $stmt->fetchColumn();
                
                // Playground/ECD, PP1, PP2, Grade1, Grade7-9 skip interview - advance directly to placement_offer stage.
                // Status stays documents_verified; placement_offered is only set once the admin generates the offer.
                if (!$this->requiresAssessment($grade)) {
                    $this->advanceStage($instance['id'], 'placement_offer', 'documents_verified_auto_qualify');
                    $this->updateApplicationStatus($application_id, 'documents_verified');
                } else {
                    // Grade 2-6 require interview assessment
                    $this->advanceStage($instance['id'], 'interview_scheduling', 'all_documents_verified');
                    $this->updateApplicationStatus($application_id, 'documents_verified');
                }
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

            // Advance to interview assessment
            $this->advanceStage($instance['id'], 'interview_assessment', 'interview_scheduled');

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
            if (!$instance || $instance['current_stage'] !== 'interview_assessment') {
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
                // Advance to placement offer
                $this->advanceStage($instance['id'], 'placement_offer', 'assessment_passed');
            } else {
                // Reject application
                $this->updateApplicationStatus($application_id, 'cancelled');
                $this->cancelWorkflow($instance['id'], 'Did not meet interview requirements');
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
            if (!$instance || $instance['current_stage'] !== 'placement_offer') {
                throw new Exception("Invalid workflow state for placement offer");
            }

            $total_fees = $this->calculatePlacementFees((int) $assigned_class_id, (int) $application_id);

            // Store placement details
            $sql = "UPDATE workflow_instances 
                    SET data_json = JSON_SET(
                        COALESCE(data_json, '{}'),
                        '$.assigned_class_id', :class_id,
                        '$.total_fees', :fees,
                        '$.offer_date', NOW()
                    )
                    WHERE id = :instance_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'class_id' => $assigned_class_id,
                'fees' => $total_fees,
                'instance_id' => $instance['id']
            ]);

            $this->updateApplicationStatus($application_id, 'placement_offered');

            // Send placement offer letter (SMS/Email)
            $this->sendPlacementOfferNotification($application_id, $total_fees);

            // Advance to fee payment
            $this->advanceStage($instance['id'], 'fee_payment', 'placement_offered');

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

            $paymentMethod = $this->normalizePaymentMethod((string) ($payment_data['method'] ?? $payment_data['payment_method'] ?? 'cash'));
            $paymentDate = $payment_data['payment_date'] ?? date('Y-m-d H:i:s');
            $referenceNo = trim((string) ($payment_data['reference'] ?? $payment_data['reference_no'] ?? ''));
            if ($referenceNo === '') {
                $referenceNo = 'ADM-' . $application_id . '-' . date('YmdHis');
            }

            $receiptNo = trim((string) ($payment_data['receipt_no'] ?? ''));
            if ($receiptNo === '') {
                $receiptNo = $this->generateAdmissionReceiptNumber((int) $application_id);
            }

            $instanceData = json_decode($instance['data_json'] ?? '{}', true) ?: [];
            $pendingPayments = $instanceData['pending_payments'] ?? [];
            $pendingPayments[] = [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_no' => $referenceNo,
                'receipt_no' => $receiptNo,
                'payment_date' => $paymentDate,
                'notes' => (string) ($payment_data['notes'] ?? ''),
                'recorded_by' => (int) $this->user_id,
                'recorded_at' => date('Y-m-d H:i:s')
            ];
            $instanceData['pending_payments'] = $pendingPayments;
            $instanceData['last_payment_recorded_at'] = date('Y-m-d H:i:s');
            $this->saveWorkflowInstanceData((int) $instance['id'], $instanceData);

            // Update application status
            $this->updateApplicationStatus($application_id, 'fees_pending');

            // Any payment recorded allows advancement to enrollment
            // The school determines minimum payment requirements outside this workflow
            if ($amount > 0) {
                // Advance to enrollment
                $this->advanceStage($instance['id'], 'enrollment', 'payment_received');
            }

            $this->db->commit();

            return formatResponse(true, [
                'amount_paid' => $amount,
                'receipt_no' => $receiptNo,
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

            // Get assigned class and stream from workflow data
            $instance_data = json_decode($instance['data_json'], true);
            $class_id = $instance_data['assigned_class_id'] ?? null;
            $stream_id = $instance_data['assigned_stream_id'] ?? null;

            // Generate student number based on class context (if available).
            $student_number = $this->generateStudentNumber(
                (int) $application['academic_year'],
                $class_id ? (int) $class_id : null
            );

            // If only class_id provided, get the default stream for that class
            if ($class_id && !$stream_id) {
                $stmt = $this->db->prepare("SELECT id FROM class_streams WHERE class_id = :class_id LIMIT 1");
                $stmt->execute(['class_id' => $class_id]);
                $stream_id = $stmt->fetchColumn() ?: null;
            }

            if (!$stream_id) {
                throw new Exception('No class stream is configured for the selected placement class');
            }

            // Get current academic year
            $stmt = $this->db->query("
                SELECT id
                FROM academic_years
                WHERE is_current = 1 OR status = 'active'
                ORDER BY is_current DESC, id DESC
                LIMIT 1
            ");
            $academic_year_id = $stmt->fetchColumn();
            if (!$academic_year_id) {
                throw new Exception('No active academic year found for enrollment');
            }

            // Parse name (simple split - adjust as needed)
            $names = explode(' ', $application['applicant_name']);
            $first_name = $names[0];
            $last_name = isset($names[1]) ? implode(' ', array_slice($names, 1)) : '';

            // Create student record with stream_id (not class_id)
            $sql = "INSERT INTO students (
                admission_no, first_name, last_name, date_of_birth,
                gender, stream_id, admission_date, status
            ) VALUES (
                :student_no, :first_name, :last_name, :dob,
                :gender, :stream_id, CURDATE(), 'active'
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'student_no' => $student_number,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'dob' => $application['date_of_birth'],
                'gender' => $application['gender'],
                'stream_id' => $stream_id
            ]);

            $student_id = $this->db->lastInsertId();

            // Create class enrollment record using stored procedure
            if ($class_id && $stream_id) {
                $stmt = $this->db->prepare("CALL sp_complete_student_enrollment(:student_id, :class_id, :stream_id, :year_id, @enr_id, @fees)");
                $stmt->execute([
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    'stream_id' => $stream_id,
                    'year_id' => $academic_year_id
                ]);

                $result = $this->db->query("SELECT @enr_id as enrollment_id, @fees as fee_obligations")->fetch(PDO::FETCH_ASSOC);
                $enrollment_id = $result['enrollment_id'];
                $fee_obligations_created = $result['fee_obligations'];
            }

            // Link parent from application
            if (!empty($application['parent_id'])) {
                $this->linkParentToStudent($student_id, $application['parent_id']);
            }

            // Post any fee payments that were captured before enrollment.
            $postedPaymentCount = $this->postPendingAdmissionPayments(
                (int) $instance['id'],
                $instance_data,
                (int) $student_id,
                !empty($application['parent_id']) ? (int) $application['parent_id'] : null,
                (string) ($application['application_no'] ?? '')
            );

            // Update application status
            $this->updateApplicationStatus($application_id, 'enrolled');

            // Complete workflow
            $this->completeWorkflow($instance['id'], [
                'student_id' => $student_id,
                'student_number' => $student_number,
                'enrollment_id' => $enrollment_id ?? null,
                'fee_obligations_created' => $fee_obligations_created ?? 0,
                'payments_posted' => $postedPaymentCount,
                'enrollment_date' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'student_id' => $student_id,
                'enrollment_id' => $enrollment_id ?? null,
                'fee_obligations_created' => $fee_obligations_created ?? 0,
                'student_number' => $student_number
            ], 'Enrollment completed successfully');

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('enrollment_failed', $e->getMessage());
            return formatResponse(false, null, 'Enrollment failed: ' . $e->getMessage());
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

    private function getRequiredDocuments($grade) {
        return [
            'birth_certificate' => ['mandatory' => true, 'label' => 'Birth Certificate'],
            'immunization_card' => ['mandatory' => true, 'label' => 'Immunization Card'],
            'progress_report' => ['mandatory' => in_array($grade, ['Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6']), 'label' => 'Latest Progress Report'],
            'passport_photo' => ['mandatory' => true, 'label' => 'Passport Photo'],
            'leaving_certificate' => ['mandatory' => in_array($grade, ['Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6']), 'label' => 'Leaving Certificate from Previous School']
        ];
    }

    private function getApplicationGrade($application_id) {
        $sql = "SELECT grade_applying_for FROM admission_applications WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $application_id]);
        return (string) ($stmt->fetchColumn() ?: '');
    }
    
    private function requiresAssessment($grade) {
        // Only Grade 2-6 require interview assessment.
        // Playground (ECD), PP1, PP2, Grade1, Grade7, Grade8, Grade9 are
        // auto-admitted once documents are verified.
        $requiresInterview = ['Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6'];
        return in_array($grade, $requiresInterview);
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

    private function normalizePaymentMethod(string $method): string
    {
        $normalized = strtolower(trim($method));
        if ($normalized === 'bank' || $normalized === 'bank transfer') {
            return 'bank_transfer';
        }

        $allowed = ['cash', 'bank_transfer', 'mpesa', 'cheque', 'other'];
        return in_array($normalized, $allowed, true) ? $normalized : 'other';
    }

    private function generateAdmissionReceiptNumber(int $applicationId): string
    {
        return sprintf('ADM-%d-%s', $applicationId, date('YmdHis'));
    }

    private function postPendingAdmissionPayments(
        int $instanceId,
        array $instanceData,
        int $studentId,
        ?int $fallbackParentId,
        string $applicationNo
    ): int {
        $pendingPayments = $instanceData['pending_payments'] ?? [];
        if (empty($pendingPayments) || !is_array($pendingPayments)) {
            return 0;
        }

        $processedPayments = $instanceData['processed_payments'] ?? [];
        $processedCount = 0;
        $suffix = $applicationNo !== '' ? " ({$applicationNo})" : '';

        foreach ($pendingPayments as $payment) {
            $amount = isset($payment['amount']) ? (float) $payment['amount'] : 0.0;
            if ($amount <= 0) {
                continue;
            }

            $paymentMethod = $this->normalizePaymentMethod((string) ($payment['payment_method'] ?? 'cash'));
            $referenceNo = trim((string) ($payment['reference_no'] ?? ''));
            if ($referenceNo === '') {
                $referenceNo = 'ADM-POST-' . $instanceId . '-' . date('YmdHis');
            }

            $receiptNo = trim((string) ($payment['receipt_no'] ?? ''));
            if ($receiptNo === '') {
                $receiptNo = $this->generateAdmissionReceiptNumber($instanceId);
            }

            $recordedBy = !empty($payment['recorded_by']) ? (int) $payment['recorded_by'] : (int) $this->user_id;
            $paymentDate = $payment['payment_date'] ?? date('Y-m-d H:i:s');
            $notes = trim((string) ($payment['notes'] ?? ''));
            if ($notes !== '') {
                $notes .= ' | ';
            }
            $notes .= 'Admission pre-enrollment payment posted after enrollment' . $suffix;

            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(
                    :student_id,
                    :parent_id,
                    :amount_paid,
                    :payment_method,
                    :reference_no,
                    :receipt_no,
                    :received_by,
                    :payment_date,
                    :notes
                )
            ");
            $stmt->execute([
                'student_id' => $studentId,
                'parent_id' => $fallbackParentId,
                'amount_paid' => $amount,
                'payment_method' => $paymentMethod,
                'reference_no' => $referenceNo,
                'receipt_no' => $receiptNo,
                'received_by' => $recordedBy,
                'payment_date' => $paymentDate,
                'notes' => $notes
            ]);
            $stmt->closeCursor();

            $processedPayments[] = [
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'reference_no' => $referenceNo,
                'receipt_no' => $receiptNo,
                'posted_at' => date('Y-m-d H:i:s')
            ];
            $processedCount++;
        }

        $instanceData['pending_payments'] = [];
        $instanceData['processed_payments'] = $processedPayments;
        $instanceData['payments_posted_at_enrollment'] = $processedCount;
        $this->saveWorkflowInstanceData($instanceId, $instanceData);

        return $processedCount;
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
