-- ----------------------------------------------------------------------------
-- Re-wire admission workflow stage authorization to the NEW stage keys and to
-- the role responsibility split encoded in config/role_sidebars.php.
--
-- Context:
--   * workflow_stage_permissions (wsp) only had rows for the OLD stage codes
--     (application, document_verification, interview_assessment, placement_offer,
--     fee_payment, director_confirmation). The redesigned pipeline uses NEW keys
--     (application_received ... enrolled). With no wsp rows, AdmissionStageAuthorization
--     ->canAct() returns can_act=false for every NEW stage, so the action endpoints
--     all 403. This migration seeds the correct rows.
--   * The sidebar designates:
--       School Administrator (4)  -> operates the full pipeline (intake, documents,
--                                     ID generation, class space, provisional student,
--                                     final approval, enrollment).
--       Headteacher (5)            -> interview scheduling/results + admission decision.
--       Accountant (10)            -> admission fee payment.
--       Director (3)               -> oversight ONLY (can_view, never can_process/
--                                     can_approve) -- by design, NOT an approver.
--       System Administrator (2)   -> oversight only.
--   * workflow_stages.required_role had 'registrar' for stages 3-9; there is NO
--     registrar role in this RBAC. That column is informational (the permission
--     matrix is the real gate) so we correct it to the real owning role.
-- Idempotent: clears wsp rows for orphaned OLD admission keys, then re-seeds.
-- ----------------------------------------------------------------------------

SET @wf_id = (SELECT id FROM workflow_definitions WHERE code = 'student_admission' LIMIT 1);
SET @registrar_placeholder = -1; -- used to flag/clear the non-existent registrar alias rows

-- Permission ids (resolved from the permissions table).
SET @p_app_view        = (SELECT id FROM permissions WHERE code = 'admission_applications_view');
SET @p_manage          = (SELECT id FROM permissions WHERE code = 'admission_manage');
SET @p_doc_upload      = (SELECT id FROM permissions WHERE code = 'admission_documents_upload');
SET @p_doc_verify      = (SELECT id FROM permissions WHERE code = 'admission_documents_verify');
SET @p_iv_schedule     = (SELECT id FROM permissions WHERE code = 'admission_interviews_schedule');
SET @p_iv_create       = (SELECT id FROM permissions WHERE code = 'admission_interviews_create');
SET @p_app_approve     = (SELECT id FROM permissions WHERE code = 'admission_applications_approve');
SET @p_app_create      = (SELECT id FROM permissions WHERE code = 'admission_applications_create');
SET @p_pay_create      = (SELECT id FROM permissions WHERE code = 'admission_payments_create');
SET @p_app_approve_final = (SELECT id FROM permissions WHERE code = 'admission_applications_approve_final');
SET @p_enroll_complete = (SELECT id FROM permissions WHERE code = 'admission_enrollment_complete');

-- Role ids
SET @r_sysadmin  = 2;
SET @r_director  = 3;
SET @r_schooladm = 4;
SET @r_head      = 5;
SET @r_deputy    = 6;
SET @r_accountant= 10;
SET @r_boarding  = 18;

-- Helper to attach (role, permission, view, process, approve) to a stage code.
DROP PROCEDURE IF EXISTS adm_grant;
DELIMITER $$
CREATE PROCEDURE adm_grant(
    IN p_stage VARCHAR(64), IN p_role INT, IN p_perm INT,
    IN p_view TINYINT, IN p_process TINYINT, IN p_approve TINYINT
)
BEGIN
    SET @sid = (SELECT id FROM workflow_stages WHERE workflow_id = @wf_id AND code = p_stage LIMIT 1);
    IF @sid IS NOT NULL THEN
        INSERT INTO workflow_stage_permissions
            (workflow_stage_id, role_id, permission_id, can_view, can_process, can_approve)
        VALUES (@sid, p_role, p_perm, p_view, p_process, p_approve)
        ON DUPLICATE KEY UPDATE can_view = VALUES(can_view),
                                can_process = VALUES(can_process),
                                can_approve = VALUES(can_approve);
    END IF;
END$$
DELIMITER ;

-- 1) Clear stale wsp rows tied to OLD admission stage codes (no longer referenced).
DELETE wsp FROM workflow_stage_permissions wsp
JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
WHERE ws.workflow_id = @wf_id
  AND ws.code IN ('application','document_verification','interview_scheduling','interview_assessment',
                  'placement_offer','fee_payment','placement_offer_old','enrollment_confirmation',
                  'director_confirmation','class_capacity_check');

-- 2) Director + System Administrator: oversight (can_view) on EVERY new stage.
CALL adm_grant('application_received',        @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('application_received',        @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('application_review',           @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('application_review',           @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('documents_upload',             @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('documents_upload',             @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('documents_verification',       @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('documents_verification',       @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('class_space_check',            @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('class_space_check',            @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('interview_scheduling',         @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('interview_scheduling',         @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('interview_results',            @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('interview_results',            @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('admission_decision',           @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('admission_decision',           @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('provisional_student_creation', @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('provisional_student_creation', @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('fees_payment',                 @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('fees_payment',                 @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('student_id_generation',        @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('student_id_generation',        @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('final_approval',               @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('final_approval',               @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('enrollment',                   @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('enrollment',                   @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('enrolled',                     @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('enrolled',                     @r_sysadmin, @p_app_view, 1, 0, 0);
CALL adm_grant('rejected',                     @r_director, @p_app_view, 1, 0, 0);
CALL adm_grant('rejected',                     @r_sysadmin, @p_app_view, 1, 0, 0);

-- 3) Stage-by-stage operational grants (aligned to the authoritative 12-step
--    admissions workflow spec: school admin runs intake/docs/space/ID; headteacher
--    + deputy run interview/decision; accountant + director + school admin run
--    fees; DIRECTOR gives final approval; headteacher/deputy/boarding master run
--    enrollment. Roles use role_id (4=School Admin,5=Headteacher,6=Deputy Head
--    Academic,10=Accountant,3=Director,18=Boarding Master,2=System Administrator).
CALL adm_grant('application_received',        @r_schooladm, @p_app_view,   1, 0, 0);
CALL adm_grant('application_review',           @r_schooladm, @p_manage,     1, 1, 0);
CALL adm_grant('application_review',           @r_head,      @p_manage,     1, 1, 0);
CALL adm_grant('documents_upload',             @r_schooladm, @p_doc_upload, 1, 1, 0);
CALL adm_grant('documents_verification',       @r_schooladm, @p_doc_verify, 1, 1, 0);
CALL adm_grant('documents_verification',       @r_schooladm, @p_doc_upload, 1, 1, 0);
CALL adm_grant('documents_verification',       @r_head,      @p_doc_verify, 1, 1, 0);
CALL adm_grant('class_space_check',            @r_schooladm, @p_manage,     1, 1, 0);
CALL adm_grant('interview_scheduling',         @r_schooladm, @p_iv_schedule,1, 1, 0);
CALL adm_grant('interview_scheduling',         @r_head,      @p_iv_schedule,1, 1, 0);
CALL adm_grant('interview_scheduling',         @r_deputy,    @p_iv_schedule,1, 1, 0);
CALL adm_grant('interview_results',            @r_head,      @p_iv_create,  1, 1, 0);
CALL adm_grant('interview_results',            @r_deputy,    @p_iv_create,  1, 1, 0);
CALL adm_grant('interview_results',            @r_schooladm, @p_iv_create,  1, 1, 0);
CALL adm_grant('admission_decision',           @r_head,      @p_app_approve,1, 1, 0);
CALL adm_grant('admission_decision',           @r_deputy,    @p_app_approve,1, 1, 0);
CALL adm_grant('admission_decision',           @r_schooladm, @p_app_approve,1, 1, 0);
CALL adm_grant('provisional_student_creation', @r_schooladm, @p_app_create, 1, 1, 0);
CALL adm_grant('fees_payment',                 @r_accountant,@p_pay_create, 1, 1, 0);
CALL adm_grant('fees_payment',                 @r_director,  @p_pay_create, 1, 1, 0);
CALL adm_grant('fees_payment',                 @r_schooladm, @p_pay_create, 1, 1, 0);
CALL adm_grant('student_id_generation',        @r_schooladm, @p_manage,     1, 1, 0);
-- final_approval: DIRECTOR is the approver; School Admin + Director have view.
CALL adm_grant('final_approval',               @r_director,  @p_app_approve_final, 1, 1, 1);
CALL adm_grant('final_approval',               @r_schooladm, @p_app_approve_final, 1, 1, 0);
CALL adm_grant('enrollment',                   @r_head,      @p_enroll_complete, 1, 1, 0);
CALL adm_grant('enrollment',                   @r_deputy,    @p_enroll_complete, 1, 1, 0);
CALL adm_grant('enrollment',                   @r_schooladm, @p_enroll_complete, 1, 1, 0);
CALL adm_grant('enrollment',                   @r_boarding,  @p_enroll_complete, 1, 1, 0);

DROP PROCEDURE IF EXISTS adm_grant;

-- 4) Correct workflow_stages.required_role / required_permission (informational
--    only -- the wsp matrix above is what actually gates actions) so they no
--    longer reference the non-existent 'registrar' alias.
UPDATE workflow_stages
SET required_role = 'school_administrator'
WHERE workflow_id = @wf_id AND required_role = 'registrar';

UPDATE workflow_stages
SET required_role = 'headteacher'
WHERE workflow_id = @wf_id AND code IN ('interview_scheduling','interview_results','admission_decision');

UPDATE workflow_stages
SET required_role = 'accountant'
WHERE workflow_id = @wf_id AND code = 'fees_payment';

UPDATE workflow_stages
SET required_role = 'school_administrator'
WHERE workflow_id = @wf_id AND code = 'provisional_student_creation';

UPDATE workflow_stages
SET required_role = 'director'
WHERE workflow_id = @wf_id AND code = 'final_approval';

-- Clear the dangling required_permission strings (they named codes that do not
-- exist in the permissions table); the wsp permission_id column is authoritative.
UPDATE workflow_stages
SET required_permission = NULL
WHERE workflow_id = @wf_id AND required_permission IS NOT NULL;
