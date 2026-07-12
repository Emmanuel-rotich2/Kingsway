-- Admissions Workflow Redesign Migration
-- This migration updates the admissions workflow to match the 13-stage target workflow
-- with proper stage keys, communication helpers, and data integrity checks

-- ============================================================================
-- PART 1: Update workflow_stages table with new stage definitions
-- ============================================================================

-- First, let's see what workflow we're working with
SELECT id, name, code FROM workflow_definitions WHERE name LIKE '%admission%' OR code LIKE '%admission%';

-- Update existing stages and add new ones for the comprehensive workflow
-- The existing workflow_id for admissions is 102 (from our inspection)

-- Disable existing stages first
-- UPDATE workflow_stages SET is_active = 0 WHERE workflow_id = 102;

-- Insert new comprehensive workflow stages
INSERT INTO workflow_stages (workflow_id, code, name, required_permission, responsible_role_ids, description, sequence, required_role, allowed_transitions, action_config, timeout_hours, is_active) VALUES
(102, 'application_received', 'Application Received', 'admission_applications_view', NULL, 'Application has been received and awaits initial review', 1, 'registrar', '["application_review", "rejected"]', '{}', NULL, 1),
(102, 'application_review', 'Application Review', 'admission_applications_view', NULL, 'Application is being reviewed for completeness and basic requirements', 2, 'registrar', '["documents_upload", "rejected"]', '{}', NULL, 1),
(102, 'documents_upload', 'Documents Upload', 'admission_documents_upload', NULL, 'School admin/admissions office uploads required admission documents', 3, 'registrar', '["documents_verification", "rejected"]', '{}', NULL, 1),
(102, 'documents_verification', 'Documents Verification', 'admission_documents_verify', NULL, 'Verify uploaded documents for authenticity and completeness', 4, 'registrar', '["class_space_check", "documents_upload", "rejected"]', '{}', NULL, 1),
(102, 'class_space_check', 'Class Space Check', 'admission_applications_view', NULL, 'Check whether the applied class has available space for the admission period', 5, 'registrar', '["interview_scheduling", "rejected"]', '{}', NULL, 1),
(102, 'interview_scheduling', 'Interview Scheduling', 'admission_interviews_schedule', NULL, 'Schedule interview date, time, and venue for the applicant', 6, 'registrar', '["interview_results", "cancelled"]', '{}', NULL, 1),
(102, 'interview_results', 'Interview Results', 'admission_interviews_create', NULL, 'Record interview results and pass/fail recommendation', 7, 'headteacher', '["admission_decision", "rejected"]', '{}', NULL, 1),
(102, 'admission_decision', 'Admission Decision', 'admission_applications_approve', NULL, 'Make final admission decision based on interview and documents', 8, 'headteacher', '["provisional_student_creation", "rejected"]', '{}', NULL, 1),
(102, 'provisional_student_creation', 'Provisional Student Creation', 'admission_applications_create', NULL, 'Create provisional student record from approved application', 9, 'registrar', '["fees_payment", "rejected"]', '{}', NULL, 1),
(102, 'fees_payment', 'Fees Payment', 'admission_payments_create', NULL, 'Record admission fees payment for the provisionally admitted student', 10, 'accountant', '["student_id_generation", "cancelled"]', '{}', NULL, 1),
(102, 'student_id_generation', 'Student ID Generation', 'admission_applications_view', NULL, 'Generate student identity card with all required details', 11, 'registrar', '["final_approval", "rejected"]', '{}', NULL, 1),
(102, 'final_approval', 'Final Approval', 'admission_enrollment_confirm', NULL, 'Director or authorized approver provides final approval before enrollment', 12, 'director', '["enrollment", "rejected"]', '{}', NULL, 1),
(102, 'enrollment', 'Enrollment', 'admission_enrollment_complete', NULL, 'Complete enrollment: assign class, stream, dormitory, registers, and learning areas', 13, 'registrar', '["enrolled"]', '{}', NULL, 1),
(102, 'enrolled', 'Enrolled', 'admission_applications_view', NULL, 'Student has been fully enrolled and admission workflow is complete', 14, NULL, '[]', '{}', NULL, 1),
(102, 'rejected', 'Rejected', 'admission_applications_view', NULL, 'Application was rejected at some stage in the workflow', 99, NULL, '[]', '{}', NULL, 1)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    required_permission = VALUES(required_permission),
    description = VALUES(description),
    sequence = VALUES(sequence),
    required_role = VALUES(required_role),
    allowed_transitions = VALUES(allowed_transitions),
    is_active = VALUES(is_active);

-- ============================================================================
-- PART 2: Add workflow_data_json column to admission_applications if not exists
-- ============================================================================

ALTER TABLE admission_applications 
ADD COLUMN IF NOT EXISTS workflow_data_json LONGTEXT COMMENT 'Workflow state data including stage-specific information' AFTER director_confirmation_notes;

-- ============================================================================
-- PART 3: Create workflow communication helper function
-- ============================================================================

DELIMITER $$

DROP FUNCTION IF EXISTS fn_get_admission_workflow_communication$$

CREATE FUNCTION fn_get_admission_workflow_communication(
    p_current_stage VARCHAR(50),
    p_status VARCHAR(50),
    p_doc_count INT,
    p_verified_count INT,
    p_has_rejected_docs INT,
    p_workflow_data_json LONGTEXT
) 
RETURNS JSON
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_result JSON;
    DECLARE v_workflow_data JSON;
    DECLARE v_label VARCHAR(255);
    DECLARE v_description TEXT;
    DECLARE v_waiting_for VARCHAR(255);
    DECLARE v_next_action_label VARCHAR(255);
    DECLARE v_next_action_method VARCHAR(255);
    DECLARE v_tone VARCHAR(50);
    DECLARE v_blocking_reason TEXT;
    
    -- Parse workflow data
    SET v_workflow_data = COALESCE(
        NULLIF(p_workflow_data_json, ''),
        NULLIF(JSON_VALID(p_workflow_data_json), FALSE),
        '{}'
    );
    
    -- Determine communication based on current stage and data
    CASE p_current_stage
        WHEN 'application_received' THEN
            SET v_label = 'Waiting for Application Review';
            SET v_description = 'Application has been received and awaits initial review.';
            SET v_waiting_for = 'School Admin / Admissions Office';
            SET v_next_action_label = 'Review Application';
            SET v_next_action_method = 'reviewApplication';
            SET v_tone = 'info';
            
        WHEN 'application_review' THEN
            SET v_label = 'Application Under Review';
            SET v_description = 'Application is being reviewed for completeness and basic requirements.';
            SET v_waiting_for = 'Admissions Office';
            SET v_next_action_label = 'Upload Documents';
            SET v_next_action_method = 'uploadDocuments';
            SET v_tone = 'info';
            
        WHEN 'documents_upload' THEN
            IF p_doc_count = 0 THEN
                SET v_label = 'Waiting for Document Upload';
                SET v_description = 'Admission documents have not been uploaded yet.';
                SET v_waiting_for = 'School Admin / Admissions Office';
                SET v_next_action_label = 'Upload Documents';
                SET v_next_action_method = 'uploadDocuments';
                SET v_tone = 'warning';
            ELSEIF p_has_rejected_docs = 1 THEN
                SET v_label = 'Documents Rejected - Corrections Required';
                SET v_description = 'Some documents were rejected. Upload corrected versions.';
                SET v_waiting_for = 'School Admin / Admissions Office';
                SET v_next_action_label = 'Upload Corrected Documents';
                SET v_next_action_method = 'uploadDocuments';
                SET v_tone = 'danger';
                SET v_blocking_reason = 'Document rejection requires corrections before proceeding.';
            ELSE
                SET v_label = 'Documents Uploaded - Pending Verification';
                SET v_description = 'Documents have been uploaded and must be verified.';
                SET v_waiting_for = 'Admissions Office';
                SET v_next_action_label = 'Verify Documents';
                SET v_next_action_method = 'verifyDocuments';
                SET v_tone = 'info';
            END IF;
            
        WHEN 'documents_verification' THEN
            IF p_verified_count < p_doc_count THEN
                SET v_label = 'Waiting for Document Verification';
                SET v_description = CONCAT('Documents have been uploaded. ', p_verified_count, ' of ', p_doc_count, ' documents verified.');
                SET v_waiting_for = 'Admissions Office';
                SET v_next_action_label = 'Verify Documents';
                SET v_next_action_method = 'verifyDocuments';
                SET v_tone = 'warning';
            ELSE
                SET v_label = 'Documents Verified - Ready for Space Check';
                SET v_description = 'All mandatory documents have been verified. Next: check class space availability.';
                SET v_waiting_for = 'Admissions Office / Academic Office';
                SET v_next_action_label = 'Check Space Availability';
                SET v_next_action_method = 'checkClassSpaceAvailability';
                SET v_tone = 'success';
            END IF;
            
        WHEN 'class_space_check' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.space_available')) = 'true' THEN
                SET v_label = 'Class Space Confirmed';
                SET v_description = CONCAT('Class space is available. ', JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.available_spaces')), ' slots available.');
                SET v_waiting_for = 'Admissions Office';
                SET v_next_action_label = 'Schedule Interview';
                SET v_next_action_method = 'scheduleInterview';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'No Class Space Available';
                SET v_description = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.space_message')), 'No space available in the applied class.');
                SET v_waiting_for = 'Academic Office';
                SET v_next_action_label = 'Review Alternatives';
                SET v_next_action_method = 'viewApplication';
                SET v_tone = 'danger';
                SET v_blocking_reason = 'No space available in the applied class for this admission period.';
            END IF;
            
        WHEN 'interview_scheduling' THEN
            SET v_label = 'Waiting for Interview Scheduling';
            SET v_description = 'Class space is available. Schedule interview date, time, and venue.';
            SET v_waiting_for = 'Admissions Office';
            SET v_next_action_label = 'Schedule Interview';
            SET v_next_action_method = 'scheduleInterview';
            SET v_tone = 'info';
            
        WHEN 'interview_results' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.interview_passed')) = 'true' THEN
                SET v_label = 'Interview Passed - Pending Decision';
                SET v_description = CONCAT('Applicant passed interview with score: ', JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.interview_score')));
                SET v_waiting_for = 'Admissions Office / Director';
                SET v_next_action_label = 'Admit Student';
                SET v_next_action_method = 'admitStudent';
                SET v_tone = 'success';
            ELSEIF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.interview_passed')) = 'false' THEN
                SET v_label = 'Interview Failed - Application Rejected';
                SET v_description = CONCAT('Applicant failed interview. Reason: ', COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.rejection_reason')), 'Not provided'));
                SET v_waiting_for = 'None';
                SET v_next_action_label = 'View Application';
                SET v_next_action_method = 'viewApplication';
                SET v_tone = 'danger';
                SET v_blocking_reason = 'Interview failure - application cannot proceed.';
            ELSE
                SET v_label = 'Waiting for Interview Results';
                SET v_description = 'Interview has been scheduled. Record results after interview is conducted.';
                SET v_waiting_for = 'Interview Panel / Admissions Office';
                SET v_next_action_label = 'Record Results';
                SET v_next_action_method = 'conductInterview';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'admission_decision' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.admission_approved')) = 'true' THEN
                SET v_label = 'Admission Approved - Awaiting Student Creation';
                SET v_description = 'Applicant has been admitted. Provisional student record creation pending.';
                SET v_waiting_for = 'Registrar / School Admin';
                SET v_next_action_label = 'Create Student Record';
                SET v_next_action_method = 'createProvisionalStudent';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'Waiting for Admission Decision';
                SET v_description = 'Applicant passed the interview. Confirm admission decision.';
                SET v_waiting_for = 'Admissions Office / Director';
                SET v_next_action_label = 'Admit Student';
                SET v_next_action_method = 'admitStudent';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'provisional_student_creation' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.provisional_student_created')) = 'true' THEN
                SET v_label = 'Student Record Created - Awaiting Fees Payment';
                SET v_description = CONCAT('Provisional student record created. Admission No: ', JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.admission_number')));
                SET v_waiting_for = 'Accounts Office';
                SET v_next_action_label = 'Record Payment';
                SET v_next_action_method = 'recordPayment';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'Waiting for Student Record Creation';
                SET v_description = 'Admission approved. Create provisional student record in the system.';
                SET v_waiting_for = 'Registrar / School Admin';
                SET v_next_action_label = 'Create Student Record';
                SET v_next_action_method = 'createProvisionalStudent';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'fees_payment' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.payment_status')) = 'paid' THEN
                SET v_label = 'Fees Paid - Awaiting ID Generation';
                SET v_description = 'Admission fees have been recorded. Student ID card generation pending.';
                SET v_waiting_for = 'School Admin';
                SET v_next_action_label = 'Generate ID Card';
                SET v_next_action_method = 'generateStudentIdCard';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'Waiting for Fees Payment';
                SET v_description = 'Student record has been created provisionally. Record admission fees payment.';
                SET v_waiting_for = 'Accounts Office';
                SET v_next_action_label = 'Record Payment';
                SET v_next_action_method = 'recordPayment';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'student_id_generation' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.student_id_card_generated')) = 'true' THEN
                SET v_label = 'Student ID Generated - Awaiting Final Approval';
                SET v_description = 'Student identity card has been generated. Final approval pending.';
                SET v_waiting_for = 'Director / Authorized Approver';
                SET v_next_action_label = 'Final Approval';
                SET v_next_action_method = 'finalApproval';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'Waiting for Student ID Generation';
                SET v_description = 'Fees are paid. Generate the student identity card.';
                SET v_waiting_for = 'School Admin';
                SET v_next_action_label = 'Generate ID Card';
                SET v_next_action_method = 'generateStudentIdCard';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'final_approval' THEN
            IF JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.final_approval_done')) = 'true' THEN
                SET v_label = 'Final Approval Complete - Ready for Enrollment';
                SET v_description = 'Final approval is complete. Student ready for enrollment assignment.';
                SET v_waiting_for = 'Registrar / School Admin';
                SET v_next_action_label = 'Complete Enrollment';
                SET v_next_action_method = 'completeEnrollment';
                SET v_tone = 'success';
            ELSE
                SET v_label = 'Waiting for Final Approval';
                SET v_description = 'Student ID card is generated. Final approval required before enrollment.';
                SET v_waiting_for = 'Director / Authorized Approver';
                SET v_next_action_label = 'Final Approval';
                SET v_next_action_method = 'finalApproval';
                SET v_tone = 'warning';
            END IF;
            
        WHEN 'enrollment' THEN
            SET v_label = 'Enrollment in Progress';
            SET v_description = 'Final approval complete. Assign class, dormitory if boarder, registers, and learning areas.';
            SET v_waiting_for = 'Registrar / School Admin';
            SET v_next_action_label = 'Complete Enrollment';
            SET v_next_action_method = 'completeEnrollment';
            SET v_tone = 'info';
            
        WHEN 'enrolled' THEN
            SET v_label = 'Enrolled';
            SET v_description = 'Student has been fully enrolled. No intake action is pending.';
            SET v_waiting_for = 'None';
            SET v_next_action_label = 'View Student';
            SET v_next_action_method = 'viewApplication';
            SET v_tone = 'success';
            
        WHEN 'rejected' THEN
            SET v_label = 'Rejected';
            SET v_description = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(v_workflow_data, '$.rejection_reason')), 'Application was rejected.');
            SET v_waiting_for = 'None';
            SET v_next_action_label = 'View Application';
            SET v_next_action_method = 'viewApplication';
            SET v_tone = 'danger';
            SET v_blocking_reason = 'Application rejected - workflow cannot continue.';
            
        ELSE
            -- Default/unknown stage
            SET v_label = CONCAT('Stage: ', COALESCE(p_current_stage, 'Unknown'));
            SET v_description = 'Application is currently being processed.';
            SET v_waiting_for = 'Admissions Office';
            SET v_next_action_label = 'Review Application';
            SET v_next_action_method = 'viewApplication';
            SET v_tone = 'info';
    END CASE;
    
    -- Build result JSON
    SET v_result = JSON_OBJECT(
        'stage', p_current_stage,
        'label', v_label,
        'description', v_description,
        'waitingFor', v_waiting_for,
        'nextActionLabel', v_next_action_label,
        'nextActionMethod', v_next_action_method,
        'tone', v_tone,
        'blockingReason', v_blocking_reason
    );
    
    RETURN v_result;
END$$

DELIMITER ;

-- ============================================================================
-- PART 4: Create stored procedure for safe workflow stage advancement
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_advance_admission_workflow_stage$$

CREATE PROCEDURE sp_advance_admission_workflow_stage(
    IN p_application_id INT,
    IN p_to_stage VARCHAR(50),
    IN p_action VARCHAR(100),
    IN p_user_id INT,
    IN p_remarks TEXT,
    IN p_workflow_updates LONGTEXT
)
BEGIN
    DECLARE v_workflow_instance_id INT;
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_from_stage VARCHAR(50);
    DECLARE v_error_msg VARCHAR(255);
    
    -- Get current workflow instance
    SELECT id, current_stage
    INTO v_workflow_instance_id, v_current_stage
    FROM workflow_instances
    WHERE reference_type = 'admission_application'
      AND reference_id = p_application_id
    LIMIT 1;
    
    -- If no workflow instance exists, create one
    IF v_workflow_instance_id IS NULL THEN
        INSERT INTO workflow_instances (
            workflow_id,
            reference_type,
            reference_id,
            current_stage,
            stage_code,
            status,
            started_by,
            started_at
        ) VALUES (
            102,  -- admissions workflow ID
            'admission_application',
            p_application_id,
            p_to_stage,
            p_to_stage,
            'in_progress',
            COALESCE(p_user_id, 1),
            NOW()
        );
        
        SET v_workflow_instance_id = LAST_INSERT_ID();
        SET v_from_stage = NULL;
    ELSE
        SET v_from_stage = v_current_stage;
    END IF;
    
    -- Update workflow stage history
    INSERT INTO workflow_stage_history (
        instance_id,
        stage_code,
        from_stage,
        to_stage,
        action_taken,
        processed_by,
        remarks,
        data_json
    ) VALUES (
        v_workflow_instance_id,
        p_to_stage,
        v_from_stage,
        p_to_stage,
        p_action,
        COALESCE(p_user_id, 1),
        p_remarks,
        p_workflow_updates
    );
    
    -- Update workflow instance current stage
    UPDATE workflow_instances
    SET current_stage = p_to_stage,
        stage_code = p_to_stage,
        data_json = COALESCE(p_workflow_updates, data_json)
    WHERE id = v_workflow_instance_id;
    
    -- Update admission_applications workflow_data_json
    IF p_workflow_updates IS NOT NULL THEN
        UPDATE admission_applications
        SET workflow_data_json = JSON_MERGE_PRESERVE(
            COALESCE(workflow_data_json, '{}'),
            p_workflow_updates
        )
        WHERE id = p_application_id;
    END IF;
    
    -- Sync admission_applications status with workflow stage where appropriate
    CASE p_to_stage
        WHEN 'documents_upload' THEN
            UPDATE admission_applications SET status = 'documents_pending' WHERE id = p_application_id;
        WHEN 'documents_verification' THEN
            UPDATE admission_applications SET status = 'documents_pending' WHERE id = p_application_id;
        WHEN 'class_space_check' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'interview_scheduling' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'interview_results' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'admission_decision' THEN
            UPDATE admission_applications SET status = 'documents_verified' WHERE id = p_application_id;
        WHEN 'provisional_student_creation' THEN
            UPDATE admission_applications SET status = 'placement_offered' WHERE id = p_application_id;
        WHEN 'fees_payment' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'student_id_generation' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'final_approval' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'enrollment' THEN
            UPDATE admission_applications SET status = 'fees_pending' WHERE id = p_application_id;
        WHEN 'enrolled' THEN
            UPDATE admission_applications SET status = 'enrolled', enrolled_at = NOW() WHERE id = p_application_id;
        WHEN 'rejected' THEN
            UPDATE admission_applications SET status = 'cancelled' WHERE id = p_application_id;
    END CASE;
    
    SELECT v_workflow_instance_id as workflow_instance_id, v_from_stage as from_stage, p_to_stage as to_stage;
END$$

DELIMITER ;

-- ============================================================================
-- PART 5: Migration/backfill procedure for existing applications
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_migrate_admission_applications_to_new_workflow$$

CREATE PROCEDURE sp_migrate_admission_applications_to_new_workflow()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_app_id INT;
    DECLARE v_current_stage VARCHAR(50);
    DECLARE v_app_status VARCHAR(50);
    DECLARE v_doc_count INT;
    DECLARE v_verified_count INT;
    DECLARE v_rejected_count INT;
    DECLARE v_has_student_id INT;
    DECLARE v_enrolled_student_id INT;
    DECLARE v_workflow_data_json LONGTEXT;
    
    -- Cursor for existing applications
    DECLARE app_cursor CURSOR FOR
        SELECT 
            aa.id,
            COALESCE(wi.current_stage, 'application') as current_stage,
            aa.status,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
            (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count,
            aa.enrolled_student_id,
            aa.workflow_data_json
        FROM admission_applications aa
        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
        WHERE aa.status NOT IN ('enrolled', 'cancelled');
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Temporary table for migration results
    CREATE TEMPORARY TABLE IF NOT EXISTS migration_results (
        application_id INT,
        old_stage VARCHAR(50),
        new_stage VARCHAR(50),
        migration_notes TEXT
    );
    
    OPEN app_cursor;
    
    read_loop: LOOP
        FETCH app_cursor INTO v_app_id, v_current_stage, v_app_status, v_doc_count, v_verified_count, v_rejected_count, v_enrolled_student_id, v_workflow_data_json;
        
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Initialize workflow data if empty
        SET v_workflow_data_json = COALESCE(v_workflow_data_json, '{}');
        
        -- Map old stages to new stages based on application state
        CASE v_current_stage
            WHEN 'application' THEN
                -- Check if documents exist
                IF v_doc_count = 0 THEN
                    -- No documents: move to application_review
                    UPDATE workflow_instances 
                    SET current_stage = 'application_review', stage_code = 'application_review'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                    
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'application_review', 'No documents uploaded - moved to application_review');
                ELSEIF v_verified_count = 0 THEN
                    -- Documents exist but not verified: move to documents_verification
                    UPDATE workflow_instances 
                    SET current_stage = 'documents_verification', stage_code = 'documents_verification'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                    
                    UPDATE admission_applications 
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_uploaded', 'true', '$.documents_uploaded_at', NOW())
                    WHERE id = v_app_id;
                    
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_verification', 'Documents uploaded but not verified');
                ELSE
                    -- Documents verified: move to class_space_check
                    UPDATE workflow_instances 
                    SET current_stage = 'class_space_check', stage_code = 'class_space_check'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                    
                    UPDATE admission_applications 
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_uploaded', 'true', '$.documents_verified', 'true', '$.documents_verified_at', NOW())
                    WHERE id = v_app_id;
                    
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'class_space_check', 'Documents verified - moved to class_space_check');
                END IF;
                
            WHEN 'document_verification' THEN
                IF v_rejected_count > 0 THEN
                    -- Has rejected documents: move back to documents_upload
                    UPDATE workflow_instances 
                    SET current_stage = 'documents_upload', stage_code = 'documents_upload'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                    
                    UPDATE admission_applications 
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_rejected', 'true')
                    WHERE id = v_app_id;
                    
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_upload', 'Rejected documents - moved back to documents_upload');
                ELSEIF v_verified_count < v_doc_count THEN
                    -- Partial verification: stay at documents_verification
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'documents_verification', 'Partial verification - kept at documents_verification');
                ELSE
                    -- All verified: move to class_space_check
                    UPDATE workflow_instances 
                    SET current_stage = 'class_space_check', stage_code = 'class_space_check'
                    WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                    
                    UPDATE admission_applications 
                    SET workflow_data_json = JSON_SET(v_workflow_data_json, '$.documents_verified', 'true', '$.documents_verified_at', NOW())
                    WHERE id = v_app_id;
                    
                    INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'class_space_check', 'All documents verified - moved to class_space_check');
                END IF;
                
            WHEN 'interview_scheduling' THEN
                -- Keep as is, just ensure stage_code matches
                UPDATE workflow_instances 
                SET stage_code = 'interview_scheduling'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'interview_scheduling', 'Stage preserved');
                
            WHEN 'interview_assessment' THEN
                -- Rename to interview_results
                UPDATE workflow_instances 
                SET current_stage = 'interview_results', stage_code = 'interview_results'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'interview_results', 'Renamed from interview_assessment to interview_results');
                
            WHEN 'placement_offer' THEN
                -- Move to admission_decision
                UPDATE workflow_instances 
                SET current_stage = 'admission_decision', stage_code = 'admission_decision'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'admission_decision', 'Moved from placement_offer to admission_decision');
                
            WHEN 'fee_payment' THEN
                -- Move to fees_payment
                UPDATE workflow_instances 
                SET current_stage = 'fees_payment', stage_code = 'fees_payment'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'fees_payment', 'Renamed from fee_payment to fees_payment');
                
            WHEN 'enrollment' THEN
                -- Move to final_approval
                UPDATE workflow_instances 
                SET current_stage = 'final_approval', stage_code = 'final_approval'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'final_approval', 'Moved from enrollment to final_approval');
                
            WHEN 'director_confirmation' THEN
                -- Move to final_approval
                UPDATE workflow_instances 
                SET current_stage = 'final_approval', stage_code = 'final_approval'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'final_approval', 'Director confirmation renamed to final_approval');
                
            ELSE
                -- Unknown stage: move to application_review
                UPDATE workflow_instances 
                SET current_stage = 'application_review', stage_code = 'application_review'
                WHERE reference_type = 'admission_application' AND reference_id = v_app_id;
                
                INSERT INTO migration_results VALUES (v_app_id, v_current_stage, 'application_review', CONCAT('Unknown stage mapped to application_review'));
        END CASE;
        
    END LOOP;
    
    CLOSE app_cursor;
    
    -- Output migration results
    SELECT * FROM migration_results ORDER BY application_id;
    
    DROP TEMPORARY TABLE IF EXISTS migration_results;
END$$

DELIMITER ;

-- ============================================================================
-- PART 6: Create class space check helper
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_check_class_space_availability$$

CREATE PROCEDURE sp_check_class_space_availability(
    IN p_application_id INT,
    IN p_user_id INT
)
BEGIN
    DECLARE v_grade_applying_for VARCHAR(50);
    DECLARE v_academic_year YEAR;
    DECLARE v_target_class_id INT;
    DECLARE v_class_capacity INT;
    DECLARE v_current_student_count INT;
    DECLARE v_available_spaces INT;
    DECLARE v_space_available BOOLEAN;
    DECLARE v_space_message TEXT;
    
    -- Get application details
    SELECT grade_applying_for, academic_year
    INTO v_grade_applying_for, v_academic_year
    FROM admission_applications
    WHERE id = p_application_id;
    
    -- Find target class based on grade and academic year
    SELECT c.id, c.capacity
    INTO v_target_class_id, v_class_capacity
    FROM classes c
    WHERE c.grade = v_grade_applying_for
      AND c.academic_year = v_academic_year
    LIMIT 1;
    
    -- Initialize results
    SET v_space_available = FALSE;
    SET v_available_spaces = 0;
    SET v_space_message = 'No class found for the applied grade and academic year';
    
    -- If class found, check space availability
    IF v_target_class_id IS NOT NULL THEN
        -- Get current student count for the class
        SELECT COUNT(*)
        INTO v_current_student_count
        FROM students
        WHERE class_id = v_target_class_id
          AND status = 'active';
        
        -- Calculate available spaces
        SET v_available_spaces = v_class_capacity - v_current_student_count;
        SET v_space_available = v_available_spaces > 0;
        
        -- Generate space message
        IF v_space_available THEN
            SET v_space_message = CONCAT('Class space available: ', v_available_spaces, ' slots out of ', v_class_capacity, ' total capacity.');
        ELSE
            SET v_space_message = CONCAT('No space available. Class is at capacity (', v_current_student_count, '/', v_class_capacity, ').');
        END IF;
    END IF;
    
    -- Return results
    SELECT 
        v_space_available as space_available,
        v_available_spaces as available_spaces,
        v_space_message as space_message,
        v_target_class_id as class_id,
        v_class_capacity as capacity,
        v_current_student_count as current_count;
END$$

DELIMITER ;

-- ============================================================================
-- Execution Notes
-- ============================================================================

-- To execute this migration:
-- 1. Review all SQL statements to ensure they match your environment
-- 2. Run the migration in a test environment first
-- 3. Backup your database before running in production
-- 4. Execute the migration: source /path/to/2026_07_08_admissions_workflow_redesign.sql
-- 5. Run the backfill procedure: CALL sp_migrate_admission_applications_to_new_workflow();
-- 6. Review migration results and address any applications that couldn't be migrated
-- 7. Test the new workflow with sample applications

-- Rollback instructions (if needed):
-- 1. Restore from backup
-- 2. Or manually revert specific changes based on migration results