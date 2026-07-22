-- Migration: 2026_07_20_repair_admission_stage_perms
-- Aligns the admissions workflow stage gate (workflow_stage_permissions) with the
-- business rules already encoded in AdmissionController::PERMISSIONS /
-- ACTION_STAGE_RULES. The DB (workflow_stage_permissions) is the CANONICAL stage
-- gate (read by AdmissionStageAuthorization::canAct), but it was missing rows for
-- two action groups, so canAct() denied them even though the controller's
-- ACTION_STAGE_RULES allowed the stage:
--
--   * admit_student      needs 'admission_applications_approve_final' at the
--                        decision stages (admission_decision / interview_results /
--                        class_space_check), not only at final_approval.
--   * confirm_enrollment needs 'admission_enrollment_confirm' at the enrolled /
--                        director_confirmation stages (had ZERO rows before).
--
-- Grants mirror the existing pattern: roles 3 (Director) and 4 (School
-- Administrator), consistent with the pre-existing final_approval rows.
--
-- Reversible: ROLLBACK block at the bottom.

SET @pid_admit_final  = (SELECT id FROM permissions WHERE code = 'admission_applications_approve_final' LIMIT 1);
SET @pid_enroll_conf  = (SELECT id FROM permissions WHERE code = 'admission_enrollment_confirm' LIMIT 1);

SET @st_decision         = (SELECT id FROM workflow_stages WHERE workflow_id = 102 AND code = 'admission_decision' LIMIT 1);
SET @st_interview_results= (SELECT id FROM workflow_stages WHERE workflow_id = 102 AND code = 'interview_results' LIMIT 1);
SET @st_class_space      = (SELECT id FROM workflow_stages WHERE workflow_id = 102 AND code = 'class_space_check' LIMIT 1);
SET @st_enrolled         = (SELECT id FROM workflow_stages WHERE workflow_id = 102 AND code = 'enrolled' LIMIT 1);
SET @st_dir_confirm      = (SELECT id FROM workflow_stages WHERE workflow_id = 102 AND code = 'director_confirmation' LIMIT 1);

INSERT IGNORE INTO workflow_stage_permissions
    (workflow_stage_id, permission_id, role_id, can_view, can_process, can_approve, is_responsible)
VALUES
    -- admit_student authorizable at the decision stages (roles 3 & 4)
    (@st_decision,          @pid_admit_final, 3, 1, 1, 1, 0),
    (@st_decision,          @pid_admit_final, 4, 1, 1, 0, 0),
    (@st_interview_results, @pid_admit_final, 3, 1, 1, 1, 0),
    (@st_interview_results, @pid_admit_final, 4, 1, 1, 0, 0),
    (@st_class_space,       @pid_admit_final, 3, 1, 1, 1, 0),
    (@st_class_space,       @pid_admit_final, 4, 1, 1, 0, 0),
    -- confirm_enrollment authorizable at enrolled / director_confirmation (roles 3 & 4)
    (@st_enrolled,          @pid_enroll_conf, 3, 1, 1, 1, 0),
    (@st_enrolled,          @pid_enroll_conf, 4, 1, 1, 0, 0),
    (@st_dir_confirm,       @pid_enroll_conf, 3, 1, 1, 1, 0),
    (@st_dir_confirm,       @pid_enroll_conf, 4, 1, 1, 0, 0);

-- ===========================================================================
-- ROLLBACK: delete exactly the rows inserted above.
-- ===========================================================================
-- DELETE wsp FROM workflow_stage_permissions wsp
-- JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
-- JOIN permissions p ON p.id = wsp.permission_id
-- WHERE ws.workflow_id = 102
--   AND wsp.role_id IN (3, 4)
--   AND p.code IN ('admission_applications_approve_final', 'admission_enrollment_confirm')
--   AND ws.code IN ('admission_decision','interview_results','class_space_check','enrolled','director_confirmation');
