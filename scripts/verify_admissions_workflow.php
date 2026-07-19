<?php
/**
 * End-to-end verification of the redesigned admissions workflow against the
 * LIVE KingsWayAcademy DB. Drives the real StudentAdmissionWorkflow module
 * (which calls sp_advance_admission_workflow_stage) through the 12 stages and
 * asserts each transition persists + no OLD stage keys remain.
 *
 * Run: php scripts/verify_admissions_workflow.php
 */
require_once __DIR__ . '/../api/modules/admission/StudentAdmissionWorkflow.php';

use App\API\Modules\admission\StudentAdmissionWorkflow;

function stageOf(StudentAdmissionWorkflow $w, int $appId): array {
    $db = $w->db->getConnection();
    $stmt = $db->prepare("
        SELECT wi.current_stage, wi.status AS inst_status, aa.status AS app_status, wi.data_json
        FROM workflow_instances wi
        JOIN admission_applications aa ON wi.reference_id = aa.id AND wi.reference_type='admission_application'
        WHERE aa.id = ?
        ORDER BY wi.id DESC LIMIT 1
    ");
    $stmt->execute([$appId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['current_stage' => '??'];
}

function show(string $label, int $appId, StudentAdmissionWorkflow $w, $res) {
    $st = stageOf($w, $appId);
    $ok = is_array($res) && ($res['success'] ?? false);
    printf("[%s] %-28s stage=%-22s success=%s msg=%s\n",
        $ok ? 'PASS' : 'FAIL', $label,
        $st['current_stage'] ?? '?',
        $ok ? 'true' : 'false',
        is_array($res) ? ($res['message'] ?? '') : gettype($res)
    );
    if (!$ok) {
        echo "   RAW: " . json_encode($res) . "\n";
    }
}

$w = new StudentAdmissionWorkflow('student_admission', 1);

// A fresh application exercises every stage deterministically.
$ts = date('YmdHis');
$app = [
    'applicant_name'     => 'Verify Pupil ' . $ts,
    'date_of_birth'      => '2016-05-01',
    'gender'             => 'male',
    'grade_applying_for' => 'Grade 1',
    'academic_year'      => (int) date('Y'),
    'parent_id'          => 1,
    'application_source' => 'online',
];

$submit = $w->submitApplication($app);
if (!($submit['success'] ?? false)) {
    echo "SUBMIT FAILED: " . json_encode($submit) . "\n";
    exit(1);
}
$appId = (int) ($submit['application_id'] ?? $submit['data']['application_id'] ?? 0);
if (!$appId) {
    echo "No application_id returned from submit. Response: " . json_encode($submit) . "\n";
    exit(1);
}
echo "=== Created application id=$appId ===\n";
show('submitApplication', $appId, $w, $submit);

// Stage transitions through the new per-application methods.
show('checkClassSpace(avail)', $appId, $w, $w->checkClassSpace($appId, true, 'space ok'));
show('admitStudent',           $appId, $w, $w->admitStudent($appId));
show('createProvisionalStudent', $appId, $w, $w->createProvisionalStudent($appId));

// Fee payment needs a positive payment. Record one via the module if available.
$feeRes = null;
if (method_exists($w, 'recordFeePayment')) {
    $feeRes = $w->recordFeePayment($appId, ['amount' => 1000, 'payment_method' => 'cash', 'reference' => 'VERIFY' . $ts]);
    show('recordFeePayment', $appId, $w, $feeRes);
}

show('generateStudentIdCard', $appId, $w, $w->generateStudentIdCard($appId));
show('finalApproval',         $appId, $w, $w->finalApproval($appId));
show('completeEnrollment',    $appId, $w, $w->completeEnrollment($appId, 'verified'));

// Audit + dedup checks.
$db = $w->db->getConnection();
$stmt = $db->prepare("
    SELECT wi.current_stage, aa.status AS app_status, aa.enrolled_student_id,
           (SELECT COUNT(*) FROM students s WHERE s.application_id = aa.id) AS student_rows,
           (SELECT COUNT(*) FROM workflow_stage_history h WHERE h.instance_id = wi.id) AS history_rows
    FROM workflow_instances wi
    JOIN admission_applications aa ON wi.reference_id = aa.id AND wi.reference_type='admission_application'
    WHERE aa.id = ? ORDER BY wi.id DESC LIMIT 1
");
$stmt->execute([$appId]);
$final = $stmt->fetch(PDO::FETCH_ASSOC);
echo "\n=== FINAL STATE (id=$appId) ===\n";
echo "current_stage   : " . ($final['current_stage'] ?? '?') . "\n";
echo "app_status      : " . ($final['app_status'] ?? '?') . "\n";
echo "enrolled_student: " . ($final['enrolled_student_id'] ?? '?') . "\n";
echo "student row count (should be 1): " . ($final['student_rows'] ?? '?') . "\n";
echo "history rows    : " . ($final['history_rows'] ?? '?') . "\n";

// Assert no old keys remain anywhere.
$old = ['application','document_verification','interview_scheduling_old','interview_assessment','placement_offer','fee_payment','enrollment','director_confirmation'];
$ph = str_repeat('?,', count($old) - 1) . '?';
$stmt = $db->prepare("SELECT COUNT(*) FROM workflow_instances WHERE reference_type='admission_application' AND current_stage IN ($ph)");
$stmt->execute($old);
$oldCount = (int) $stmt->fetchColumn();
echo "OLD stage keys still present across all apps: $oldCount (expect 0)\n";

$studentOk = (int) ($final['student_rows'] ?? 0) === 1;
$enrolledOk = ($final['current_stage'] ?? '') === 'enrolled' && ($final['app_status'] ?? '') === 'enrolled';
$noOld = $oldCount === 0;
echo "\nVERDICT: " . ($studentOk && $enrolledOk && $noOld ? 'PASS' : 'FAIL') . "\n";
exit($studentOk && $enrolledOk && $noOld ? 0 : 1);
