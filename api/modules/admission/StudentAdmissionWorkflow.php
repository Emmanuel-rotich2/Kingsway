<?php
namespace App\API\Modules\admission;

require_once __DIR__ . '/../../../config/config.php';
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
 * - Procedures: sp_calculate_student_fees, sp_process_student_payment, generate_student_number
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
                'grade' => $data['grade_applying_for']
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

            // Upload file
            $uploaded = $this->uploadFile($file, [
                'allowed_types' => ['pdf', 'jpg', 'jpeg', 'png'],
                'max_size' => 5 * 1024 * 1024, // 5MB
                'destination' => UPLOAD_PATH . '/admission/' . $application_id
            ]);

            // Save document record
            $sql = "INSERT INTO admission_documents (
                application_id, document_type, document_path,
                is_mandatory, verification_status, created_at
            ) VALUES (:app_id, :type, :path, :mandatory, 'pending', NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'app_id' => $application_id,
                'type' => $document_type,
                'path' => $uploaded['path'],
                'mandatory' => in_array($document_type, ['birth_certificate', 'immunization_card']) ? 1 : 0
            ]);

            // Check if all mandatory docs uploaded
            $all_uploaded = $this->checkMandatoryDocuments($application_id);

            if ($all_uploaded && $instance['current_stage'] === 'application_submission') {
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
                
                // ECD, PP1, PP2, Grade1, and Grade7 skip interview - go directly to placement offer
                if (!$this->requiresAssessment($grade)) {
                    $this->advanceStage($instance['id'], 'placement_offer', 'documents_verified_auto_qualify');
                    $this->updateApplicationStatus($application_id, 'auto_qualified');
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

            // Calculate fees using stored procedure
            $sql = "CALL sp_calculate_student_fees(:class_id, @total_fees)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['class_id' => $assigned_class_id]);

            // Get the calculated fees
            $result = $this->db->query("SELECT @total_fees as total_fees")->fetch(PDO::FETCH_ASSOC);
            $total_fees = $result['total_fees'];

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

            // Process payment using stored procedure
            $sql = "CALL sp_process_student_payment(
                :amount, :payment_method, :reference, :student_id, :recorded_by
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'amount' => $payment_data['amount'],
                'payment_method' => $payment_data['method'],
                'reference' => $payment_data['reference'],
                'student_id' => $application_id, // Temporary - will be actual student_id after enrollment
                'recorded_by' => $this->user_id
            ]);

            // Update application status
            $this->updateApplicationStatus($application_id, 'fees_pending');

            // Check if minimum payment met (e.g., 50% of total fees)
            $instance_data = json_decode($instance['data_json'], true);
            $total_fees = $instance_data['total_fees'] ?? 0;
            $min_payment = $total_fees * 0.5;

            if ($payment_data['amount'] >= $min_payment) {
                // Advance to enrollment
                $this->advanceStage($instance['id'], 'enrollment', 'minimum_payment_received');
            }

            $this->db->commit();

            return formatResponse(true, [
                'amount_paid' => $payment_data['amount'],
                'can_enroll' => $payment_data['amount'] >= $min_payment
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

            // Generate student number using stored function/procedure
            $student_number = $this->generateStudentNumber($application['academic_year']);

            // Get assigned class from workflow data
            $instance_data = json_decode($instance['data_json'], true);
            $class_id = $instance_data['assigned_class_id'] ?? null;

            // Create student record
            $sql = "INSERT INTO students (
                student_number, first_name, last_name, date_of_birth,
                gender, admission_date, class_id, status, created_at
            ) VALUES (
                :student_no, :first_name, :last_name, :dob,
                :gender, NOW(), :class_id, 'active', NOW()
            )";

            // Parse name (simple split - adjust as needed)
            $names = explode(' ', $application['applicant_name']);
            $first_name = $names[0];
            $last_name = isset($names[1]) ? implode(' ', array_slice($names, 1)) : '';

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'student_no' => $student_number,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'dob' => $application['date_of_birth'],
                'gender' => $application['gender'],
                'class_id' => $class_id
            ]);

            $student_id = $this->db->lastInsertId();

            // Update application status
            $this->updateApplicationStatus($application_id, 'enrolled');

            // Complete workflow
            $this->completeWorkflow($instance['id'], [
                'student_id' => $student_id,
                'student_number' => $student_number,
                'enrollment_date' => date('Y-m-d H:i:s')
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'student_id' => $student_id,
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

    private function generateStudentNumber($year) {
        // Use stored function if available, otherwise generate
        try {
            $result = $this->db->query("SELECT generate_student_number($year) as student_no")->fetch();
            return $result['student_no'];
        } catch (Exception $e) {
            // Fallback generation
            $sql = "SELECT COUNT(*) + 1 as next_num FROM students WHERE YEAR(admission_date) = :year";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['year' => $year]);
            $num = $stmt->fetchColumn();
            return sprintf("STD/%s/%04d", $year, $num);
        }
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
    
    private function requiresAssessment($grade) {
        // Only Grade 2-6 require interview assessment
        // ECD, PP1, PP2, Grade1, and Grade7 are auto-admitted if documents are verified
        return in_array($grade, ['Grade2', 'Grade3', 'Grade4', 'Grade5', 'Grade6']);
    }

    private function checkMandatoryDocuments($application_id) {
        $sql = "SELECT COUNT(*) as pending 
                FROM admission_documents 
                WHERE application_id = :id 
                AND is_mandatory = 1 
                AND verification_status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $application_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['pending'] == 0;
    }

    private function checkAllDocumentsVerified($application_id) {
        $sql = "SELECT COUNT(*) as unverified 
                FROM admission_documents 
                WHERE application_id = :id 
                AND verification_status != 'verified'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $application_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['unverified'] == 0;
    }

    private function updateApplicationStatus($application_id, $status) {
        $sql = "UPDATE admission_applications SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['status' => $status, 'id' => $application_id]);
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

    private function sendInterviewSMS($application_id, $date, $time, $venue) {
        // TODO: Integrate with SMS service (sp_send_sms_to_parents)
        $this->logAction('sms_sent', $application_id, "Interview notification sent for $date at $time");
    }

    private function sendPlacementOfferNotification($application_id, $fees) {
        // TODO: Send placement offer via SMS/Email
        $this->logAction('placement_offer_sent', $application_id, "Placement offer sent. Total fees: $fees");
    }
}
