<?php
/**
 * scripts/test_admission_stage_auth.php
 *
 * Verifies the admissions workflow stage gate (workflow_stage_permissions) now
 * authorizes every action group declared in AdmissionController::PERMISSIONS /
 * ACTION_STAGE_RULES. Runs via LAMPP php and queries MySQL through the mysql CLI
 * (CLI php here has no pdo_mysql/mysqli driver), replicating the exact
 * (stage, role, permission) check that AdmissionStageAuthorization::canAct does.
 *
 * Focus: the two action groups that the repair migration had to fill in:
 *   - admit_student      -> admission_applications_approve_final at the decision
 *                           stages (admission_decision / interview_results /
 *                           class_space_check) for roles 3 (Director) & 4 (School Admin)
 *   - confirm_enrollment -> admission_enrollment_confirm at enrolled /
 *                           director_confirmation for roles 3 & 4
 *
 * Usage: /opt/lampp/bin/php scripts/test_admission_stage_auth.php
 */

$mysql = '/opt/lampp/bin/mysql -uroot -padmin123 KingsWayAcademy -N';

// action group -> [ [stage, permission, [roles]] ... ] drawn from PERMISSIONS +
// ACTION_STAGE_RULES + the repaired grant set.
$cases = [
    'admit_student' => [
        ['admission_decision',     'admission_applications_approve_final', [3, 4]],
        ['interview_results',       'admission_applications_approve_final', [3, 4]],
        ['class_space_check',       'admission_applications_approve_final', [3, 4]],
    ],
    'confirm_enrollment' => [
        ['enrolled',                'admission_enrollment_confirm',        [3, 4]],
        ['director_confirmation',   'admission_enrollment_confirm',        [3, 4]],
    ],
];

// Replicates AdmissionStageAuthorization::canAct for a single (role, stage, perm):
// a stage is can_act for a role if a wsp row exists with that stage, role, perm
// and can_process=1 OR can_approve=1.
function stageCanActForRole(string $mysql, string $stage, string $perm, int $role): bool {
    $out = shell_exec("$mysql -e \"SELECT COUNT(*) FROM workflow_stage_permissions wsp JOIN workflow_stages ws ON ws.id=wsp.workflow_stage_id JOIN permissions p ON p.id=wsp.permission_id WHERE ws.workflow_id=102 AND ws.code='$stage' AND p.code='$perm' AND wsp.role_id=$role AND (wsp.can_process=1 OR wsp.can_approve=1);\"");
    return (int) trim($out) > 0;
}

$pass = $fail = 0;
foreach ($cases as $group => $rows) {
    foreach ($rows as [$stage, $perm, $roles]) {
        foreach ($roles as $role) {
            $ok = stageCanActForRole($mysql, $stage, $perm, $role);
            $label = "$group @ $stage / $perm [role $role]";
            echo ($ok ? "  PASS " : "  FAIL ") . $label . "\n";
            $ok ? $pass++ : $fail++;
        }
    }
}

echo "\n== RESULT: $pass passed, $fail failed ==\n";
exit($fail === 0 ? 0 : 1);
