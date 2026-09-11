<?php

namespace App\API\Modules\admission;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * Admission Admin Manager
 *
 * Owns every data read, mutation, scope/permission decision and workflow
 * orchestration for the admission module so the AdmissionController stays a
 * thin endpoint exposer (no direct DB access, no business decisions).
 *
 * Live-schema mapping (verified against KingsWayAcademy):
 *   - `routes` (absent)            → `routes_registry`
 *   - `class_streams` (absent)     → `academic_year_classes` + `academic_year_class_streams` + `streams`
 *   - `parents.email/phone_*`      → `persons` (parents holds only person_id/occupation/address/status)
 *   - `classes.status/capacity`    → dropped (columns absent); capacity lives on `streams`
 *   - placement classes student counts → `student_academic_enrollments` by stream
 *   - workflow stages/permissions  → `workflow_definitions`/`workflow_stages`/`workflow_stage_permissions`
 *
 * Guard primitives take a user-context array built by the controller from the
 * JWT payload (no DB): `user_id`, `role_ids`, `role_names`, `permission_codes`,
 * `effective_permissions`, `email`.
 */
class AdmissionAdminManager extends BaseAPI
{
    private const PERMISSIONS = [
        'view_any' => [
            'admission_view',
            'admission_applications_view_all',
            'admission_applications_view_own',
            'admission_applications_view',
        ],
        'view_all' => [
            'admission_applications_view_all',
        ],
        'view_own' => [
            'admission_applications_view_own',
        ],
        'submit_application' => [
            'admission_applications_create',
            'admission_applications_submit',
            'admission_manage',
        ],
        'edit_application' => [
            'admission_applications_edit',
            'admission_applications_edit_own',
            'admission_manage',
        ],
        'manage_windows' => [
            'admission_applications_edit',
            'admission_manage',
        ],
        'review_application' => [
            'admission_manage',
        ],
        'upload_document' => [
            'admission_manage',
            'admission_documents_upload',
            'admission_documents_create',
            'admission_applications_upload',
        ],
        'verify_document' => [
            'admission_manage',
            'admission_documents_verify',
            'admission_documents_approve',
            'admission_documents_validate',
            'admission_applications_verify',
        ],
        'schedule_interview' => [
            'admission_manage',
            'admission_interviews_schedule',
            'admission_applications_schedule',
        ],
        'record_interview' => [
            'admission_manage',
            'admission_interviews_create',
            'admission_interviews_edit',
            'admission_interviews_approve',
            'admission_interviews_verify',
        ],
        'check_class_space' => [
            'admission_manage',
            'admission_applications_verify',
        ],
        'admit_student' => [
            'admission_approve',
            'admission_applications_approve_final',
        ],
        'create_provisional_student' => [
            'admission_approve',
            'admission_applications_create',
        ],
        'record_payment' => [
            'admission_payments_create',
            'admission_fee_payments_record',
            'admission_payments_record',
            'admission_applications_validate',
        ],
        'generate_id_card' => [
            'admission_manage',
            'admission_documents_create',
        ],
        'final_approval' => [
            'admission_approve',
            'admission_applications_approve_final',
        ],
        'complete_enrollment' => [
            'admission_enrollment_complete',
            'admission_applications_approve_final',
        ],
        'confirm_enrollment' => [
            'admission_enrollment_confirm',
        ],
    ];

    private const ACTION_STAGE_RULES = [
        'review_application' => ['application_received', 'application_review'],
        'upload_document' => ['application_applied'],
        'schedule_interview' => ['interview_scheduling'],
        'record_interview' => ['interview_scheduling', 'interview_results'],
        'admit_student' => ['interview_results'],
        'create_provisional_student' => ['student_admission_number'],
        'record_payment' => ['class_placement', 'fees_payment', 'student_id_generation'],
        'generate_id_card' => ['student_id_generation'],
        'final_approval' => ['final_enrollment'],
        'complete_enrollment' => ['class_placement', 'final_enrollment'],
        'confirm_enrollment' => ['enrolled'],
    ];

    private const VALID_TRANSITIONS = [
        'application_applied' => ['application_received', 'rejected'],
        'application_received' => ['application_review', 'rejected'],
        'application_review' => ['interview_scheduling', 'student_admission_number', 'rejected'],
        'interview_scheduling' => ['interview_results', 'rejected'],
        'interview_results' => ['student_admission_number', 'rejected'],
        'student_admission_number' => ['class_placement', 'rejected'],
        'class_placement' => ['fees_payment', 'rejected'],
        'fees_payment' => ['student_id_generation', 'cancelled'],
        'student_id_generation' => ['final_enrollment', 'rejected'],
        'final_enrollment' => ['enrolled'],
        'rejected' => [],
        'enrolled' => [],
    ];

    private const ADMISSION_ROUTE_NAMES = [
        'manage_students_admissions',
        'admissions/director_admissions',
        'admissions/enrollment_confirmations',
    ];

    private AdmissionPolicy $policy;
    private AdmissionPaymentService $paymentService;
    private ?AdmissionStageAuthorization $stageAuthorization = null;
    private ?StudentAdmissionWorkflow $workflow = null;

    private bool $resolvedWorkflowId = false;
    private int $workflowId = 0;

    private bool $resolvedWorkflowStages = false;
    private array $workflowStageConfig = [];

    private bool $resolvedAdmissionRouteAccess = false;
    private bool $admissionRouteAccess = false;
    private int $admissionRouteAccessUserId = 0;

    private bool $resolvedAdmissionsRouteRoleAliases = false;
    private array $admissionsRouteRoleAliases = [];

    private array $parentIdCache = [];

    public function __construct()
    {
        parent::__construct('admission');
        $this->policy = new AdmissionPolicy();
        $this->paymentService = new AdmissionPaymentService($this->db);
    }

    public function workflow(): StudentAdmissionWorkflow
    {
        if ($this->workflow === null) {
            $this->workflow = new StudentAdmissionWorkflow();
        }

        return $this->workflow;
    }

    public function paymentService(): AdmissionPaymentService
    {
        return $this->paymentService;
    }

    // ========================================================================
    // ENDPOINT OPERATIONS (return BaseAPI response arrays)
    // ========================================================================

    public function getPending(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);

            $stmt = $this->db->query(
                "SELECT COUNT(*) as total_pending
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.status NOT IN ('enrolled', 'cancelled'){$scopeFilter}"
            );
            $countRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalPending = (int) ($countRow['total_pending'] ?? 0);

            $rows = $this->db->query(
                "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, aa.status, aa.created_at as admission_date, wi.current_stage
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.status NOT IN ('enrolled', 'cancelled'){$scopeFilter}
                 ORDER BY aa.created_at DESC
                 LIMIT 8"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $recentAdmissions = [];
            foreach ($rows as $row) {
                $recentAdmissions[] = [
                    'id' => $row['id'],
                    'application_no' => $row['application_no'] ?? null,
                    'applicant_name' => $row['applicant_name'] ?? 'Unknown',
                    'grade_applying_for' => $row['grade_applying_for'] ?? null,
                    'current_stage' => $row['current_stage'] ?? null,
                    'admission_date' => $row['admission_date'],
                    'status' => $row['status'] ?? 'submitted',
                ];
            }

            return $this->successResponse([
                'total_pending' => $totalPending,
                'recent' => $recentAdmissions,
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Pending admissions retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getQueues(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);
            $stageMatrix = $this->getStageAuthorization()->getStageMatrix(
                $this->ctxRoleIds($ctx),
                $this->ctxPermissionCodes($ctx)
            );
            // School leadership must be able to monitor the complete intake
            // pipeline even where a stage belongs operationally to another
            // role. Stage-action permissions below still decide who may act.
            $hasAdmissionOversight = $this->ctxHasRole(
                [3, 4, 5, 6, 63],
                ['Director', 'School Administrator', 'Headteacher', 'Deputy Head - Academic', 'Deputy Head - Discipline'],
                $ctx
            );
            $canViewStage = static function (array $stages) use ($stageMatrix, $hasAdmissionOversight): bool {
                foreach ($stages as $stage) {
                    if (!empty($stageMatrix[$stage]['can_view']) || $hasAdmissionOversight) {
                        return true;
                    }
                }

                return false;
            };
            $canViewReview = $canViewStage(['application_received', 'application_review']);
            $canViewDocuments = $canViewStage(['application_applied']);
            $canViewSpace = false;
            $canViewInterview = $canViewStage(['interview_scheduling', 'interview_results']);
            $canViewDecision = $canViewStage(['student_admission_number']);
            $canViewPlacement = $canViewStage(['class_placement']);
            $canViewPayment = $canViewStage(['class_placement', 'fees_payment']);
            $canViewId = $canViewStage(['student_id_generation']);
            $canViewFinalApproval = $canViewStage(['final_enrollment']);
            $canViewEnrollment = $canViewStage(['final_enrollment']);
            $canReview = $this->canProcessAdmissionActionForStage('review_application', 'application_review', $ctx)
                || $this->canProcessAdmissionActionForStage('review_application', 'application_received', $ctx);
            // Documents are submitted with the application. They are never an
            // admissions-office queue action after Application Applied.
            $canUploadDocuments = false;
            $canVerifyDocuments = false;
            $canCheckSpace = false;
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling', $ctx);
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_scheduling', $ctx)
                || $this->canProcessAdmissionActionForStage('record_interview', 'interview_results', $ctx);
            $canAdmit = $this->canProcessAdmissionActionForStage('admit_student', 'interview_results', $ctx);
            $canCreateProvisional = $this->canProcessAdmissionActionForStage('create_provisional_student', 'student_admission_number', $ctx);
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'class_placement', $ctx)
                || $this->canProcessAdmissionActionForStage('record_payment', 'fees_payment', $ctx)
                || $this->canProcessAdmissionActionForStage('record_payment', 'student_id_generation', $ctx);
            $canGenerateId = $this->canProcessAdmissionActionForStage('generate_id_card', 'student_id_generation', $ctx);
            $canFinalApproval = $this->canProcessAdmissionActionForStage('final_approval', 'final_enrollment', $ctx);
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'class_placement', $ctx)
                || $this->canProcessAdmissionActionForStage('complete_enrollment', 'final_enrollment', $ctx);

            $queues = [
                'review_pending' => [],
                'documents_pending' => [],
                'space_check_pending' => [],
                'interview_pending' => [],
                'decision_pending' => [],
                'placement_pending' => [],
                'payment_pending' => [],
                'id_generation_pending' => [],
                'final_enrollment_pending' => [],
                'enrollment_pending' => [],
                'completed' => [],
                'all_applications' => [],
            ];

            $baseSelect = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.gender, aa.date_of_birth, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.application_source, aa.updated_at,
                           (SELECT s0.admission_no FROM students s0 WHERE s0.id = aa.enrolled_student_id LIMIT 1) AS admission_number,
                           (SELECT c2.name
                              FROM student_academic_enrollments sae2
                              JOIN academic_year_class_streams aycs2 ON aycs2.id = sae2.academic_year_class_stream_id
                              JOIN academic_year_classes ayc2 ON ayc2.id = aycs2.academic_year_class_id
                              JOIN classes c2 ON c2.id = ayc2.class_id
                             WHERE sae2.student_id = aa.enrolled_student_id
                               AND sae2.enrollment_status = 'active'
                             ORDER BY sae2.id DESC LIMIT 1) AS assigned_class_name,
                           (SELECT s2.name
                              FROM student_academic_enrollments sae3
                              JOIN academic_year_class_streams aycs3 ON aycs3.id = sae3.academic_year_class_stream_id
                              JOIN streams s2 ON s2.id = aycs3.stream_id
                             WHERE sae3.student_id = aa.enrolled_student_id
                               AND sae3.enrollment_status = 'active'
                             ORDER BY sae3.id DESC LIMIT 1) AS assigned_stream_name,
                           pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                           wi.current_stage, wi.data_json,
                           aa.workflow_data_json,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN persons pp ON pp.id = p.person_id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                      AND wi.id = (SELECT MAX(wi2.id) FROM workflow_instances wi2
                                   WHERE wi2.reference_type = 'admission_application' AND wi2.reference_id = aa.id)";

            $compactSelect = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.gender, aa.date_of_birth, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                           wi.current_stage, wi.data_json,
                           ai.id AS interview_id, ai.session_id AS interview_session_id,
                           ai.scheduled_date AS interview_date, ai.scheduled_time AS interview_time,
                           ai.venue AS interview_venue, ai.interviewer_id AS interview_interviewer_id,
                           ai.status AS interview_status, CONCAT(COALESCE(ipt.first_name,''),' ',COALESCE(ipt.last_name,'')) AS interview_interviewer_name,
                           ipt.phone AS interview_interviewer_phone
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN persons pp ON pp.id = p.person_id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                      AND wi.id = (SELECT MAX(wi2.id) FROM workflow_instances wi2
                                   WHERE wi2.reference_type = 'admission_application' AND wi2.reference_id = aa.id)
                    LEFT JOIN admission_interviews ai ON ai.application_id = aa.id AND ai.status <> 'cancelled'
                    LEFT JOIN staff ist ON ist.id=ai.interviewer_id
                    LEFT JOIN persons ipt ON ipt.id=ist.person_id";

            if ($hasAdmissionOversight || $this->hasAnyAdmissionPermission('view_all', $ctx)) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE 1 = 1
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['all_applications'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewReview || $canReview) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage IN ('application_received', 'application_review')
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['review_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            // Application Applied is the intake stage. Documents are already
            // submitted with the application; this queue is view-only and
            // must never present an admin upload/verification action.
            if ($canViewDocuments) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage = 'application_applied'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['documents_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewInterview || $canScheduleInterview || $canRecordInterview) {
                $interviewStageFilters = [];
                if ($canViewInterview) {
                    $interviewStageFilters[] = "wi.current_stage IN ('interview_scheduling', 'interview_results')";
                } else {
                    if ($canScheduleInterview) {
                        $interviewStageFilters[] = "wi.current_stage = 'interview_scheduling'";
                    }
                    if ($canRecordInterview) {
                        $interviewStageFilters[] = "wi.current_stage = 'interview_results'";
                    }
                }

                $interviewStageSql = empty($interviewStageFilters)
                    ? '1 = 0'
                    : implode(' OR ', $interviewStageFilters);

                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE ({$interviewStageSql})
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['interview_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewDecision || $canAdmit || $canCreateProvisional) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'student_admission_number'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['decision_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            // A successful admission-number creation moves the application
            // into class_placement. Keep that stage visible in both the
            // admissions workspace and the dedicated placement page.
            if ($canViewPlacement || $canCompleteEnrollment) {
                $stmt = $this->db->query(
                    "{$baseSelect}
                     WHERE wi.current_stage = 'class_placement'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['placement_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewPayment || $canRecordPayment) {
                $stmt = $this->db->query(
                    "SELECT aa.id, aa.application_no, aa.applicant_name, aa.gender, aa.grade_applying_for,
                            aa.status, aa.created_at,
                            CASE WHEN EXISTS (
                                SELECT 1 FROM student_parents sp0
                                WHERE sp0.parent_id = aa.parent_id
                                  AND (aa.enrolled_student_id IS NULL OR sp0.student_id <> aa.enrolled_student_id)
                            ) THEN COALESCE(
                                (SELECT tier.amount
                                 FROM extra_charge_pricing_tiers tier
                                 JOIN extra_charges ec ON ec.id=tier.extra_charge_id
                                 JOIN academic_years ay ON ay.id=ec.academic_year_id
                                 JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
                                 WHERE CAST(RIGHT(ay.year_code, 4) AS UNSIGNED)=aa.academic_year
                                   AND ec.name='Registration Fee' AND ec.status='active'
                                   AND ec.target_scope='new_admissions' AND ec.billing_model='paid_separately'
                                   AND tier.condition_code IN ('existing_parent','existing')
                                 ORDER BY tier.sort_order, tier.id LIMIT 1),
                                (SELECT ec.amount FROM extra_charges ec JOIN academic_years ay ON ay.id=ec.academic_year_id
                                 JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
                                 WHERE CAST(RIGHT(ay.year_code, 4) AS UNSIGNED)=aa.academic_year
                                   AND ec.name='Registration Fee' AND ec.status='active' AND ec.target_scope='new_admissions'
                                 ORDER BY ec.id LIMIT 1)
                            ) ELSE COALESCE(
                                (SELECT tier.amount
                                 FROM extra_charge_pricing_tiers tier
                                 JOIN extra_charges ec ON ec.id=tier.extra_charge_id
                                 JOIN academic_years ay ON ay.id=ec.academic_year_id
                                 JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
                                 WHERE CAST(RIGHT(ay.year_code, 4) AS UNSIGNED)=aa.academic_year
                                   AND ec.name='Registration Fee' AND ec.status='active'
                                   AND ec.target_scope='new_admissions' AND ec.billing_model='paid_separately'
                                   AND tier.condition_code IN ('new_parent','new')
                                 ORDER BY tier.sort_order, tier.id LIMIT 1),
                                (SELECT ec.amount FROM extra_charges ec JOIN academic_years ay ON ay.id=ec.academic_year_id
                                 JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
                                 WHERE CAST(RIGHT(ay.year_code, 4) AS UNSIGNED)=aa.academic_year
                                   AND ec.name='Registration Fee' AND ec.status='active' AND ec.target_scope='new_admissions'
                                 ORDER BY ec.id LIMIT 1)
                            ) END AS registration_fee_due,
                            s0.admission_no AS admission_number,
                            (SELECT c0.name
                               FROM student_academic_enrollments sae0
                               JOIN academic_year_class_streams aycs0 ON aycs0.id = sae0.academic_year_class_stream_id
                               JOIN academic_year_classes ayc0 ON ayc0.id = aycs0.academic_year_class_id
                               JOIN classes c0 ON c0.id = ayc0.class_id
                              WHERE sae0.student_id = aa.enrolled_student_id
                                AND sae0.enrollment_status = 'active'
                              ORDER BY sae0.id DESC LIMIT 1) AS assigned_class_name,
                            (SELECT s1.name
                               FROM student_academic_enrollments sae1
                               JOIN academic_year_class_streams aycs1 ON aycs1.id = sae1.academic_year_class_stream_id
                               JOIN streams s1 ON s1.id = aycs1.stream_id
                              WHERE sae1.student_id = aa.enrolled_student_id
                                AND sae1.enrollment_status = 'active'
                              ORDER BY sae1.id DESC LIMIT 1) AS assigned_stream_name,
                            pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                            wi.current_stage, wi.data_json,
                            JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.total_fees')) as total_fees,
                            JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.assigned_class_id')) as assigned_class_id,
                            (SELECT ap0.id FROM admission_payments ap0
                              WHERE ap0.application_id = aa.id AND ap0.status = 'pending_verification'
                              ORDER BY ap0.id DESC LIMIT 1) AS pending_payment_id,
                            (SELECT ap1.reference_no FROM admission_payments ap1
                              WHERE ap1.application_id = aa.id AND ap1.status = 'pending_verification'
                              ORDER BY ap1.id DESC LIMIT 1) AS pending_payment_reference,
                            (SELECT ap2.amount FROM admission_payments ap2
                              WHERE ap2.application_id = aa.id AND ap2.status = 'pending_verification'
                              ORDER BY ap2.id DESC LIMIT 1) AS pending_payment_amount
                            ,(SELECT COALESCE(SUM(CASE WHEN ap3.status IN ('recorded', 'posted') THEN ap3.amount ELSE 0 END), 0)
                              FROM admission_payments ap3 WHERE ap3.application_id = aa.id) AS recorded_payment_amount
                     FROM admission_applications aa
                     LEFT JOIN students s0 ON s0.id = aa.enrolled_student_id
                     LEFT JOIN parents p ON aa.parent_id = p.id
                     LEFT JOIN persons pp ON pp.id = p.person_id
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage IN ('class_placement', 'fees_payment')
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['payment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewId || $canGenerateId) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'student_id_generation'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['id_generation_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            if ($canViewFinalApproval || $canFinalApproval) {
                $stmt = $this->db->query(
                    "{$compactSelect}
                     WHERE wi.current_stage = 'final_enrollment'
                       AND aa.status NOT IN ('cancelled', 'enrolled')
                     {$scopeFilter}
                     ORDER BY aa.created_at DESC"
                );
                $queues['final_enrollment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);
            }

            $stmt = $this->db->query(
                "SELECT aa.id, aa.application_no, aa.applicant_name, aa.gender, aa.grade_applying_for,
                        aa.status, aa.created_at, aa.enrolled_student_id, aa.application_source,
                        pp.first_name as parent_first_name, pp.last_name as parent_last_name, pp.phone as phone_1,
                        wi.current_stage, wi.data_json
                 FROM admission_applications aa
                 LEFT JOIN parents p ON aa.parent_id = p.id
                 LEFT JOIN persons pp ON pp.id = p.person_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE wi.current_stage = 'enrolled'
                 {$scopeFilter}
                 ORDER BY aa.created_at DESC"
            );
            $queues['completed'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC), $ctx);

            $summary = [
                'review_pending' => count($queues['review_pending']),
                'documents_pending' => count($queues['documents_pending']),
                'space_check_pending' => count($queues['space_check_pending']),
                'interview_pending' => count($queues['interview_pending']),
                'decision_pending' => count($queues['decision_pending']),
                'placement_pending' => count($queues['placement_pending']),
                'payment_pending' => count($queues['payment_pending']),
                'id_generation_pending' => count($queues['id_generation_pending']),
                'final_enrollment_pending' => count($queues['final_enrollment_pending']),
                'enrollment_pending' => count($queues['enrollment_pending']),
                'completed' => count($queues['completed']),
                'all_applications' => count($queues['all_applications']),
                'total_pending' => count($queues['review_pending']) + count($queues['documents_pending'])
                    + count($queues['space_check_pending']) + count($queues['interview_pending'])
                    + count($queues['decision_pending']) + count($queues['placement_pending'])
                    + count($queues['payment_pending'])
                    + count($queues['id_generation_pending']) + count($queues['final_enrollment_pending'])
                    + count($queues['enrollment_pending']),
            ];

            return $this->successResponse([
                'queues' => $queues,
                'summary' => $summary,
                'allowed_tabs' => [
                    'review_pending' => $canReview,
                    'documents_pending' => false,
                    'space_check_pending' => false,
                    'interview_pending' => ($canScheduleInterview || $canRecordInterview),
                    'decision_pending' => ($canAdmit || $canCreateProvisional),
                    'placement_pending' => $canCompleteEnrollment,
                    'payment_pending' => $canRecordPayment,
                    'id_generation_pending' => $canGenerateId,
                    'final_enrollment_pending' => $canFinalApproval,
                    'enrollment_pending' => false,
                    'completed' => true,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ], 'Workflow queues retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getApplication(int $id, array $ctx): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*,
                        pp.first_name as parent_first_name, pp.last_name as parent_last_name,
                        pp.phone as phone_1, pp.phone as phone_2, pp.email as parent_email,
                        s0.admission_no as admission_number,
                        (SELECT c0.name
                           FROM student_academic_enrollments sae0
                           JOIN academic_year_class_streams aycs0 ON aycs0.id = sae0.academic_year_class_stream_id
                           JOIN academic_year_classes ayc0 ON ayc0.id = aycs0.academic_year_class_id
                           JOIN classes c0 ON c0.id = ayc0.class_id
                          WHERE sae0.student_id = aa.enrolled_student_id
                            AND sae0.enrollment_status = 'active'
                          ORDER BY sae0.id DESC LIMIT 1) as assigned_class_name,
                        (SELECT st0.name
                           FROM student_academic_enrollments sae1
                           JOIN academic_year_class_streams aycs1 ON aycs1.id = sae1.academic_year_class_stream_id
                           JOIN streams st0 ON st0.id = aycs1.stream_id
                          WHERE sae1.student_id = aa.enrolled_student_id
                            AND sae1.enrollment_status = 'active'
                          ORDER BY sae1.id DESC LIMIT 1) as assigned_stream_name,
                        wi.id as workflow_instance_id, wi.current_stage, wi.status as workflow_status, wi.data_json,
                        wi.started_by, wi.started_at
                 FROM admission_applications aa
                 LEFT JOIN parents p ON aa.parent_id = p.id
                 LEFT JOIN persons pp ON pp.id = p.person_id
                 LEFT JOIN students s0 ON s0.id = aa.enrolled_student_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([(int) $id]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->errorResponse('Application not found', 404);
            }

            if (!$this->canViewApplicationRecord($application, $ctx)) {
                return $this->errorResponse('You do not have access to this admission application', 403);
            }

            $stmt = $this->db->prepare(
                "SELECT ad.*,
                        mf.filename as media_filename,
                        mf.original_name as media_original_name,
                        mf.file_type as media_file_type,
                        mf.context as media_context,
                        mf.entity_id as media_entity_id,
                        mf.album_id as media_album_id
                 FROM admission_documents ad
                 LEFT JOIN media_files mf
                   ON ad.document_path REGEXP '^[0-9]+$'
                  AND mf.id = CAST(ad.document_path AS UNSIGNED)
                 WHERE ad.application_id = ?
                 ORDER BY ad.is_mandatory DESC, ad.document_type"
            );
            $stmt->execute([(int) $id]);
            $documents = $this->normalizeAdmissionDocuments($stmt->fetchAll(\PDO::FETCH_ASSOC));

            // Workflow instances may contain an older snapshot from before an
            // intake window was assigned. The application row and its own
            // workflow_data_json are authoritative for admissions metadata.
            $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
            $applicationWorkflowData = json_decode($application['workflow_data_json'] ?? '{}', true) ?: [];
            $workflowData = array_merge($workflowData, $applicationWorkflowData);
            if (array_key_exists('academic_year', $application)) {
                $workflowData['academic_year'] = $application['academic_year'];
            }
            if (array_key_exists('target_term_id', $application)) {
                $workflowData['target_term_id'] = $application['target_term_id'] !== null
                    ? (int) $application['target_term_id']
                    : null;
            }
            $workflowData = $this->syncWorkflowIdentityData($workflowData, $application);

            $availableActions = $this->getAvailableActions($application['current_stage'], $application['status'], $ctx);
            $stageMeta = $this->getCurrentStageMetadata($application['current_stage']);
            $currentStageCode = $this->normalizeStageCode($application['current_stage']) ?? $this->inferStageFromApplication($application);
            $currentStageRequiredRole = $stageMeta['required_role'] ?? null;

            return $this->successResponse([
                'application' => $application,
                'documents' => $documents,
                'workflow_data' => $workflowData,
                'available_actions' => $availableActions,
                'stage_metadata' => [
                    'current_stage' => $currentStageCode,
                    'display_name' => $stageMeta['name'] ?? null,
                    'required_role' => $currentStageRequiredRole,
                    'user_matches_required_role' => $this->userMatchesRequiredRole($currentStageRequiredRole, $ctx),
                    'allowed_transitions' => $this->getAllowedTransitionsForStage($currentStageCode),
                ],
            ], 'Application details retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    // ========================================================================
    // ADMISSION WINDOWS
    // ========================================================================

    public function getAdmissionWindows(array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('manage_windows', $ctx)
            && !$this->hasAnyAdmissionPermission('view_any', $ctx)) {
            return $this->errorResponse('Insufficient permission to view admission windows', 403);
        }

        try {
            $rows = $this->db->query(
                "SELECT aw.id, aw.academic_year_id, aw.academic_year_term_id, aw.label,
                        aw.status, aw.accepts_new_applications, aw.eligible_grades,
                        aw.default_admission_category, aw.application_open_at,
                        aw.application_close_at, aw.calendar_event_id, aw.notes, aw.opened_by,
                        aw.opened_at, aw.closed_at, aw.updated_at,
                        ay.year_code, ay.year_name,
                        ayt.opening_date, ayt.closing_date, ayt.status AS term_status,
                        t.name AS term_name, t.code AS term_number,
                        CASE WHEN aw.status <> 'open' OR aw.accepts_new_applications = 0 THEN 'closed'
                             WHEN aw.application_open_at IS NOT NULL AND NOW() < aw.application_open_at THEN 'scheduled'
                             WHEN aw.application_close_at IS NOT NULL AND NOW() > aw.application_close_at THEN 'closed'
                             ELSE 'open' END AS effective_status
                 FROM admission_windows aw
                 JOIN academic_years ay ON ay.id = aw.academic_year_id
                 LEFT JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
                 LEFT JOIN terms t ON t.id = ayt.term_id
                 ORDER BY ay.start_date DESC, ayt.opening_date ASC, aw.id ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->successResponse(['windows' => $rows ?: []], 'Admission windows retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function saveAdmissionWindow(array $data, array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('manage_windows', $ctx)) {
            return $this->errorResponse('Insufficient permission to manage admission windows', 403);
        }

        $yearId = (int) ($data['academic_year_id'] ?? 0);
        $termId = ($data['academic_year_term_id'] ?? null);
        $termId = ($termId === '' || $termId === null) ? null : (int) $termId;
        if ($yearId < 1 || $termId < 1) {
            return $this->errorResponse('academic_year_id and academic_year_term_id are required', 422);
        }
        $termCheck = $this->db->prepare('SELECT id FROM academic_year_terms WHERE id = ? AND academic_year_id = ?');
        $termCheck->execute([$termId, $yearId]);
        if (!$termCheck->fetchColumn()) {
            return $this->errorResponse('The selected term does not belong to the selected academic year', 422);
        }

        $status = (($data['status'] ?? 'open') === 'closed') ? 'closed' : 'open';
        $accepts = empty($data['accepts_new_applications']) ? 0 : 1;
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = $this->windowLabel($yearId, $termId);
        }
        $notes = $data['notes'] ?? null;
        $eligibleGrades = $data['eligible_grades'] ?? [];
        if (is_string($eligibleGrades)) {
            $decoded = json_decode($eligibleGrades, true);
            $eligibleGrades = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $eligibleGrades, -1, PREG_SPLIT_NO_EMPTY);
        }
        $gradePolicy = new AdmissionPolicy();
        $eligibleGrades = array_values(array_unique(array_filter(array_map(function ($grade) use ($gradePolicy) {
            return $gradePolicy->normalizeGrade((string) $grade);
        }, (array) $eligibleGrades))));
        $eligibleGradesJson = $eligibleGrades ? json_encode($eligibleGrades, JSON_UNESCAPED_UNICODE) : null;
        $category = strtolower(trim((string) ($data['default_admission_category'] ?? '')));
        if ($category !== '' && !in_array($category, ['standard', 'nursery_term_1', 'nursery_term_3'], true)) {
            return $this->errorResponse('Invalid default admission category', 422);
        }
        $category = $category ?: null;
        $openAt = $this->normalizeWindowDate($data['application_open_at'] ?? $data['open_at'] ?? null);
        $closeAt = $this->normalizeWindowDate($data['application_close_at'] ?? $data['close_at'] ?? null);
        if ($openAt !== null && $closeAt !== null && strtotime($closeAt) <= strtotime($openAt)) {
            return $this->errorResponse('Application close date must be after the open date', 422);
        }
        $userId = $this->ctxUserId($ctx);
        $now = date('Y-m-d H:i:s');
        $closedAt = $status === 'closed' ? $now : null;
        $openedAt = $status === 'open' ? $now : null;

        try {
            $this->db->beginTransaction();
            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                $stmt = $this->db->prepare(
                    "UPDATE admission_windows
                     SET academic_year_id = ?, academic_year_term_id = ?, label = ?,
                         status = ?, accepts_new_applications = ?, application_open_at = ?,
                         application_close_at = ?, eligible_grades = ?, default_admission_category = ?, notes = ?,
                         opened_by = ?, opened_at = ?, closed_at = ?
                     WHERE id = ?"
                );
                $stmt->execute([$yearId, $termId, $label, $status, $accepts, $openAt, $closeAt, $eligibleGradesJson, $category, $notes, $userId, $openedAt, $closedAt, $id]);
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO admission_windows
                        (academic_year_id, academic_year_term_id, label, status,
                         accepts_new_applications, eligible_grades, default_admission_category,
                         application_open_at, application_close_at,
                         notes, opened_by, opened_at, closed_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        label = VALUES(label),
                        status = VALUES(status),
                        accepts_new_applications = VALUES(accepts_new_applications),
                        eligible_grades = VALUES(eligible_grades),
                        default_admission_category = VALUES(default_admission_category),
                        application_open_at = VALUES(application_open_at),
                        application_close_at = VALUES(application_close_at),
                        notes = VALUES(notes),
                        opened_by = VALUES(opened_by),
                        opened_at = VALUES(opened_at),
                        closed_at = VALUES(closed_at)"
                );
                $stmt->execute([$yearId, $termId, $label, $status, $accepts, $eligibleGradesJson, $category, $openAt, $closeAt, $notes, $userId, $openedAt, $closedAt]);
            }

            $windowId = $id > 0 ? $id : (int) $this->db->lastInsertId();
            $this->syncWindowCalendarEvent($windowId, $label, $openAt, $closeAt, $notes);
            $this->db->commit();

            return $this->successResponse(['id' => $windowId], 'Admission window saved');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    private function normalizeWindowDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            throw new Exception('Invalid admission window date');
        }
        return date('Y-m-d H:i:s', $timestamp);
    }

    private function syncWindowCalendarEvent(int $windowId, string $label, ?string $openAt, ?string $closeAt, ?string $notes): void
    {
        if ($windowId < 1 || $openAt === null) {
            return;
        }
        $window = $this->db->prepare('SELECT calendar_event_id FROM admission_windows WHERE id = ?');
        $window->execute([$windowId]);
        $eventId = (int) $window->fetchColumn();
        $eventStatus = strtotime($openAt) > time() ? 'upcoming' : (($closeAt !== null && strtotime($closeAt) < time()) ? 'past' : 'ongoing');
        $title = 'Admissions: ' . $label;
        $endAt = $closeAt ?: $openAt;
        if ($eventId > 0) {
            $stmt = $this->db->prepare("UPDATE school_events SET title = ?, description = ?, start_at = ?, end_at = ?, type = 'admissions', status = ?, source = 'manual', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $notes, $openAt, $endAt, $eventStatus, $eventId]);
            return;
        }
        $stmt = $this->db->prepare("INSERT INTO school_events (title, description, start_at, end_at, type, location, status, source) VALUES (?, ?, ?, ?, 'admissions', 'Admissions Office', ?, 'manual')");
        $stmt->execute([$title, $notes, $openAt, $endAt, $eventStatus]);
        $eventId = (int) $this->db->lastInsertId();
        $this->db->prepare('UPDATE admission_windows SET calendar_event_id = ? WHERE id = ?')->execute([$eventId, $windowId]);
    }

    public function toggleAdmissionWindow(int $id, array $data, array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('manage_windows', $ctx)) {
            return $this->errorResponse('Insufficient permission to manage admission windows', 403);
        }

        try {
            $stmt = $this->db->prepare("SELECT status FROM admission_windows WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetchColumn();
            if ($current === false) {
                return $this->errorResponse('Admission window not found', 404);
            }
            $next = (($data['status'] ?? null) === 'open') ? 'open' : (($current === 'open') ? 'closed' : 'open');
            $openedAt = $next === 'open' ? date('Y-m-d H:i:s') : null;
            $closedAt = $next === 'closed' ? date('Y-m-d H:i:s') : null;
            $stmt = $this->db->prepare(
                "UPDATE admission_windows SET status = ?, opened_at = ?, closed_at = ?, updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute([$next, $openedAt, $closedAt, $id]);
            return $this->successResponse(['id' => $id, 'status' => $next], 'Admission window updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getOpenAdmissionTerms(array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('view_any', $ctx)) {
            return $this->errorResponse('Insufficient permission to view admission terms', 403);
        }

        try {
            $rows = $this->db->query(
                "SELECT aw.id AS admission_window_id, aw.label AS admission_window_label,
                        aw.eligible_grades, aw.default_admission_category,
                        aw.application_open_at, aw.application_close_at,
                        ayt.id AS target_term_id,
                        ayt.id AS academic_year_term_id,
                        t.name AS term_name,
                        t.code AS term_number,
                        ay.id AS academic_year_id,
                        ay.year_code, ay.year_name,
                        ayt.status AS term_status,
                        ayt.opening_date, ayt.closing_date
                 FROM admission_windows aw
                 JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
                 JOIN academic_years ay ON ay.id = aw.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE aw.status = 'open' AND aw.accepts_new_applications = 1
                   AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
                   AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
                 ORDER BY FIELD(ayt.status, 'current', 'upcoming'), ayt.opening_date ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            return $this->successResponse(['terms' => $rows ?: []], 'Open admission terms retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getInterviewSessions(array $data, array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('schedule_interview', $ctx)) {
            return $this->errorResponse('Insufficient permission to view interview sessions', 403);
        }
        try {
            $where = [];
            $params = [];
            if (!empty($data['admission_window_id'])) {
                $where[] = 's.admission_window_id = ?';
                $params[] = (int) $data['admission_window_id'];
            }
            $sql = "SELECT s.*, aw.label AS window_label, aw.application_open_at, aw.application_close_at,
                           CONCAT(COALESCE(ip.first_name,''),' ',COALESCE(ip.last_name,'')) AS interviewer_name,
                           ip.phone AS interviewer_phone,
                           COUNT(ai.id) AS assigned_count,
                           GROUP_CONCAT(CONCAT(aa.application_no, ' — ', aa.applicant_name) ORDER BY aa.applicant_name SEPARATOR '||') AS assigned_applicants
                    FROM admission_interview_sessions s
                    JOIN admission_windows aw ON aw.id = s.admission_window_id
                    LEFT JOIN staff iu ON iu.id = s.interviewer_id AND iu.status = 'active' AND iu.staff_type_id = 1
                    LEFT JOIN persons ip ON ip.id = iu.person_id
                    LEFT JOIN admission_interviews ai ON ai.session_id = s.id AND ai.status <> 'cancelled'
                    LEFT JOIN admission_applications aa ON aa.id = ai.application_id
                    " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
                    GROUP BY s.id ORDER BY s.session_date, s.start_time, s.id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $this->successResponse(['sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []], 'Interview sessions retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function saveInterviewSession(array $data, array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('schedule_interview', $ctx)) {
            return $this->errorResponse('Insufficient permission to manage interview sessions', 403);
        }
        $windowId = (int) ($data['admission_window_id'] ?? 0);
        $date = trim((string) ($data['session_date'] ?? ''));
        $start = trim((string) ($data['start_time'] ?? ''));
        $end = trim((string) ($data['end_time'] ?? ''));
        $venue = trim((string) ($data['venue'] ?? 'Main Office')) ?: 'Main Office';
        $capacity = max(1, (int) ($data['capacity'] ?? 20));
        if ($windowId < 1 || $date === '' || $start === '' || $end === '') {
            return $this->errorResponse('Admission window, session date, start time, and end time are required', 422);
        }
        if (strtotime($end) <= strtotime($start)) {
            return $this->errorResponse('Session end time must be after start time', 422);
        }
        try {
            $interviewerId = !empty($data['interviewer_id']) ? (int) $data['interviewer_id'] : 0;
            if ($interviewerId < 1) return $this->errorResponse('Select an active teacher as the interviewer.', 422);
            $teacherStmt = $this->db->prepare("SELECT id FROM staff WHERE id=? AND staff_type_id=1 AND status='active' LIMIT 1");
            $teacherStmt->execute([$interviewerId]);
            if (!$teacherStmt->fetchColumn()) return $this->errorResponse('Interviewer must be an active teacher.', 422);
            $windowStmt = $this->db->prepare(
                "SELECT aw.*, COALESCE(DATE(aw.application_open_at), ayt.opening_date) AS valid_from,
                        DATE_ADD(COALESCE(DATE(aw.application_close_at), ayt.closing_date), INTERVAL 7 DAY) AS valid_until
                 FROM admission_windows aw
                 LEFT JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
                 WHERE aw.id = ?"
            );
            $windowStmt->execute([$windowId]);
            $window = $windowStmt->fetch(PDO::FETCH_ASSOC);
            if (!$window) return $this->errorResponse('Admission window not found', 404);
            if ($window['valid_from'] && $date < $window['valid_from']) {
                return $this->errorResponse('Interview session cannot be before the intake opens', 422);
            }
            if ($window['valid_until'] && $date > $window['valid_until']) {
                return $this->errorResponse('Interview session must be within the intake period or seven days after closing', 422);
            }
            $id = (int) ($data['id'] ?? 0);
            if ($id > 0) {
                $oldStmt = $this->db->prepare('SELECT session_date,start_time,end_time,venue,interviewer_id FROM admission_interview_sessions WHERE id=?');
                $oldStmt->execute([$id]);
                $old = $oldStmt->fetch(PDO::FETCH_ASSOC);
                $sessionStatus = $data['status'] ?? 'scheduled';
                $stmt = $this->db->prepare("UPDATE admission_interview_sessions SET admission_window_id=?, session_date=?, start_time=?, end_time=?, venue=?, interviewer_id=?, capacity=?, status=?, notes=? WHERE id=?");
                $stmt->execute([$windowId, $date, $start, $end, $venue, $interviewerId ?: null, $capacity, in_array($sessionStatus, ['scheduled','full','completed','cancelled'], true) ? $sessionStatus : 'scheduled', $data['notes'] ?? null, $id]);
                if ($old && ($old['session_date'] !== $date || $old['start_time'] !== $start || $old['venue'] !== $venue || (int) $old['interviewer_id'] !== $interviewerId)) {
                    $assignedStmt = $this->db->prepare("SELECT id FROM admission_interviews WHERE session_id=? AND status IN ('scheduled','rescheduled')");
                    $assignedStmt->execute([$id]);
                    $updateAssigned = $this->db->prepare("UPDATE admission_interviews SET scheduled_date=?, scheduled_time=?, venue=?, interviewer_id=?, status='rescheduled' WHERE id=?");
                    $history = $this->db->prepare("INSERT INTO admission_interview_assignment_history (admission_interview_id,from_session_id,to_session_id,action,reason,changed_by) VALUES (?, ?, ?, 'rescheduled', ?, ?)");
                    foreach ($assignedStmt->fetchAll(PDO::FETCH_COLUMN) as $interviewId) {
                        $updateAssigned->execute([$date, $start, $venue, $interviewerId ?: null, (int) $interviewId]);
                        $history->execute([(int) $interviewId, $id, $id, 'Interview session details changed', $this->ctxUserId($ctx)]);
                        $this->workflow()->notifyInterviewAssignment((int) $interviewId, $id, 'rescheduled-' . date('YmdHis'));
                    }
                }
            } else {
                $stmt = $this->db->prepare("INSERT INTO admission_interview_sessions (admission_window_id, session_date, start_time, end_time, venue, interviewer_id, capacity, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$windowId, $date, $start, $end, $venue, $interviewerId ?: null, $capacity, $data['notes'] ?? null, $this->ctxUserId($ctx)]);
                $id = (int) $this->db->lastInsertId();
            }
            $eventId = (int) ($this->db->query('SELECT calendar_event_id FROM admission_interview_sessions WHERE id=' . $id)->fetchColumn() ?: 0);
            $title = 'Admissions Interview: ' . ($window['label'] ?? 'Intake') . ' — ' . $date;
            if ($eventId) {
                $this->db->prepare("UPDATE school_events SET title=?, start_at=?, end_at=?, type='admissions_interview', status='upcoming', updated_at=NOW() WHERE id=?")->execute([$title, $date . ' ' . $start, $date . ' ' . $end, $eventId]);
            } else {
                $this->db->prepare("INSERT INTO school_events (title,description,start_at,end_at,type,location,status,source) VALUES (?,?,?,?,'admissions_interview',?,'upcoming','manual')")->execute([$title, $data['notes'] ?? null, $date . ' ' . $start, $date . ' ' . $end, $venue]);
                $eventId = (int) $this->db->lastInsertId();
                $this->db->prepare('UPDATE admission_interview_sessions SET calendar_event_id=? WHERE id=?')->execute([$eventId, $id]);
            }
            return $this->successResponse(['id' => $id, 'calendar_event_id' => $eventId], 'Interview session saved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function reassignInterviewSession(int $applicationId, int $sessionId, string $reason, array $ctx): array
    {
        if ($applicationId < 1 || $sessionId < 1) return $this->errorResponse('Application and interview session are required', 422);
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare("SELECT ai.id, ai.session_id, ai.status, aa.applicant_name, aa.grade_applying_for, aa.target_term_id
                FROM admission_interviews ai JOIN admission_applications aa ON aa.id=ai.application_id
                WHERE ai.application_id=? FOR UPDATE");
            $q->execute([$applicationId]);
            $current = $q->fetch(PDO::FETCH_ASSOC);
            if (!$current || !in_array($current['status'], ['scheduled','rescheduled'], true)) throw new Exception('Applicant is not currently scheduled for an interview.');
            if ((int) $current['session_id'] === $sessionId) throw new Exception('Applicant is already scheduled for this interview session.');

            $q = $this->db->prepare("SELECT s.*, aw.academic_year_term_id, aw.application_open_at, aw.application_close_at,
                DATE_ADD(COALESCE(DATE(aw.application_close_at), ayt.closing_date), INTERVAL 7 DAY) valid_until
                FROM admission_interview_sessions s JOIN admission_windows aw ON aw.id=s.admission_window_id
                LEFT JOIN academic_year_terms ayt ON ayt.id=aw.academic_year_term_id WHERE s.id=? FOR UPDATE");
            $q->execute([$sessionId]);
            $session = $q->fetch(PDO::FETCH_ASSOC);
            if (!$session || !in_array($session['status'], ['scheduled','full'], true)) throw new Exception('The selected interview session is not available.');
            if ((int) $current['target_term_id'] !== (int) $session['academic_year_term_id']) throw new Exception('Selected session belongs to a different admission intake.');
            if ($session['application_open_at'] && $session['session_date'] < substr($session['application_open_at'], 0, 10)) throw new Exception('Session is before the intake opening date.');
            if ($session['valid_until'] && $session['session_date'] > $session['valid_until']) throw new Exception('Session is outside the permitted intake period.');
            $q = $this->db->prepare("SELECT COUNT(*) FROM admission_interviews WHERE session_id=? AND status <> 'cancelled'");
            $q->execute([$sessionId]);
            if ((int) $q->fetchColumn() >= (int) $session['capacity']) throw new Exception('The selected interview session is full.');
            if (!empty($session['interviewer_id'])) {
                $teacherStmt = $this->db->prepare("SELECT id FROM staff WHERE id=? AND staff_type_id=1 AND status='active' LIMIT 1");
                $teacherStmt->execute([(int) $session['interviewer_id']]);
                if (!$teacherStmt->fetchColumn()) throw new Exception('The selected session interviewer is not an active teacher.');
            }
            $this->db->prepare("UPDATE admission_interviews SET session_id=?, scheduled_date=?, scheduled_time=?, venue=?, interviewer_id=?, status='rescheduled' WHERE id=?")
                ->execute([$sessionId, $session['session_date'], $session['start_time'], $session['venue'], $session['interviewer_id'], $current['id']]);
            $this->db->prepare("INSERT INTO admission_interview_assignment_history (admission_interview_id,from_session_id,to_session_id,action,reason,changed_by) VALUES (?,?,?,?,?,?)")
                ->execute([$current['id'], $current['session_id'], $sessionId, 'switched', trim($reason) ?: null, $this->ctxUserId($ctx)]);
            $this->db->prepare("UPDATE admission_interview_sessions SET status=CASE WHEN (SELECT COUNT(*) FROM admission_interviews WHERE session_id=? AND status <> 'cancelled') >= capacity THEN 'full' ELSE 'scheduled' END WHERE id=?")
                ->execute([$sessionId, $sessionId]);
            if (!empty($current['session_id'])) {
                $this->db->prepare("UPDATE admission_interview_sessions SET status=CASE WHEN (SELECT COUNT(*) FROM admission_interviews WHERE session_id=? AND status <> 'cancelled') >= capacity THEN 'full' ELSE 'scheduled' END WHERE id=?")
                    ->execute([(int) $current['session_id'], (int) $current['session_id']]);
            }
            $this->db->commit();
            $this->workflow()->notifyInterviewAssignment((int) $current['id'], $sessionId, 'switched');
            return $this->successResponse(['application_id'=>$applicationId,'session_id'=>$sessionId], 'Applicant moved to the selected interview session.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function notifyInterviewApplicant(array $data, array $ctx): array
    {
        if (!$this->hasAnyAdmissionPermission('schedule_interview', $ctx)) return $this->errorResponse('Insufficient permission to send interview notifications', 403);
        $applicationId = (int) ($data['application_id'] ?? 0);
        if ($applicationId < 1) return $this->errorResponse('Application is required', 422);
        try {
            $stmt = $this->db->prepare("SELECT ai.id,ai.session_id FROM admission_interviews ai WHERE ai.application_id=? AND ai.status IN ('scheduled','rescheduled') LIMIT 1");
            $stmt->execute([$applicationId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $this->errorResponse('Applicant has no active interview assignment', 422);
            $result = $this->workflow()->notifyInterviewAssignment((int) $row['id'], (int) $row['session_id'], 'assigned');
            if (($result['status'] ?? '') !== 'success') return $this->errorResponse($result['message'] ?? 'Unable to queue interview notifications', 422);
            return $this->successResponse(null, $result['message'] ?? 'Interview notifications queued');
        } catch (Exception $e) { \App\API\Services\Logger::legacyError('[AdmissionAdminManager] '.$e->getMessage()); return $this->errorResponse('Unable to queue interview notification.', 422); }
    }

    // ========================================================================
    // INLINE APPLICATION EDIT
    // ========================================================================

    public function updateApplicationFields(int $id, array $data, array $ctx): array
    {
        $application = $this->getApplicationScopeRecord($id);
        if (!$application) {
            return $this->errorResponse('Application not found', 404);
        }
        if (!$this->canViewApplicationRecord($application, $ctx)) {
            return $this->errorResponse('You do not have access to this admission application', 403);
        }
        if (!$this->hasAnyAdmissionPermission('edit_application', $ctx)) {
            return $this->errorResponse('Insufficient permission to edit this application', 403);
        }

        // Admissions staff choose a configured intake window, not a raw term
        // identifier. Resolve the window atomically to its year and target term.
        $windowId = ($data['admission_window_id'] ?? '') === '' ? null : (int) ($data['admission_window_id'] ?? 0);
        if ($windowId !== null) {
            $windowStmt = $this->db->prepare(
                "SELECT aw.id, aw.academic_year_id, aw.academic_year_term_id, ay.year_code
                 FROM admission_windows aw
                 JOIN academic_years ay ON ay.id = aw.academic_year_id
                 WHERE aw.id = ?"
            );
            $windowStmt->execute([$windowId]);
            $window = $windowStmt->fetch(PDO::FETCH_ASSOC);
            if (!$window || empty($window['academic_year_term_id'])) {
                return $this->errorResponse('Invalid admission window', 422);
            }
            $data['target_term_id'] = (int) $window['academic_year_term_id'];

            // admission_applications.academic_year is a MySQL YEAR column,
            // while academic_years.year_code is commonly stored as a school
            // year label such as "2026/2027". Store the ending four-digit
            // year used by the existing application records.
            $yearCode = trim((string) ($window['year_code'] ?? ''));
            if (preg_match('/(\d{4})\s*$/', $yearCode, $matches)) {
                $yearCode = $matches[1];
            }
            if (!preg_match('/^\d{4}$/', $yearCode)) {
                return $this->errorResponse('Admission window has an invalid academic year', 422);
            }
            $data['academic_year'] = $yearCode;
        }

        $allowed = [
            'applicant_name', 'date_of_birth', 'gender', 'grade_applying_for',
            'academic_year', 'target_term_id', 'previous_school',
            'admission_category', 'application_source', 'has_special_needs',
            'special_needs_details',
        ];

        $updates = [];
        $params = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($field === 'target_term_id') {
                $value = ($value === '' || $value === null) ? null : (int) $value;
                if ($value !== null) {
                    $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE id = ?");
                    $stmt->execute([$value]);
                    if (!$stmt->fetchColumn()) {
                        return $this->errorResponse('Invalid target term', 422);
                    }
                }
            } elseif ($field === 'grade_applying_for') {
                $value = trim((string) $value);
                if (preg_match('/^grade\s*([1-9])$/i', $value, $m)) {
                    $value = 'Grade' . $m[1];
                }
            } else {
                $value = ($value === '' || $value === null) ? null : trim((string) $value);
            }
            $updates[] = "`{$field}` = ?";
            $params[] = $value;
        }

        if (empty($updates)) {
            return $this->errorResponse('No editable fields supplied', 422);
        }

        try {
            $params[] = $id;
            $stmt = $this->db->prepare(
                "UPDATE admission_applications SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute($params);

            $fresh = $this->getApplicationScopeRecord($id);
            if ($fresh) {
                $json = json_decode($fresh['workflow_data_json'] ?? '{}', true) ?: [];
                $json['applicant_name'] = $fresh['applicant_name'] ?? ($json['applicant_name'] ?? null);
                $json['grade'] = $fresh['grade_applying_for'] ?? ($json['grade'] ?? null);
                $json['application_no'] = $fresh['application_no'] ?? ($json['application_no'] ?? null);
                $this->db->prepare("UPDATE admission_applications SET workflow_data_json = ? WHERE id = ?")
                    ->execute([json_encode($json, JSON_UNESCAPED_UNICODE), $id]);
            }

            return $this->successResponse(['application_id' => $id], 'Application updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.');
        }
    }

    private function windowLabel(int $yearId, int $termId): string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT CONCAT(t.name, ' ', ay.year_code)
                 FROM academic_year_terms ayt
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 JOIN terms t ON t.id = ayt.term_id
                 WHERE ayt.academic_year_id = ? AND ayt.id = ?
                 LIMIT 1"
            );
            $stmt->execute([$yearId, $termId]);
            return (string) ($stmt->fetchColumn() ?: 'Admission Window');
        } catch (Exception $e) {
            return 'Admission Window';
        }
    }

    public function getPlacementClasses(): array
    {
        try {
            $rows = $this->db->query(
                "SELECT c.id,
                        aycs.id AS academic_year_class_stream_id,
                        s.id AS stream_id,
                        s.name AS stream_name,
                        c.name,
                        COALESCE(s.capacity, 0) AS capacity,
                        COUNT(sae.id) AS student_count
                 FROM academic_years ay
                 JOIN academic_year_classes ayc
                   ON ayc.academic_year_id = ay.id AND ayc.status = 'active'
                 JOIN classes c ON c.id = ayc.class_id
                 JOIN academic_year_class_streams aycs
                   ON aycs.academic_year_class_id = ayc.id AND aycs.status = 'active'
                 JOIN streams s ON s.id = aycs.stream_id
                 LEFT JOIN student_academic_enrollments sae
                   ON sae.academic_year_class_stream_id = aycs.id AND sae.enrollment_status = 'active'
                 WHERE (ay.status = 'active' OR ay.is_current = 1)
                 GROUP BY c.id, aycs.id, s.id, s.name, c.name, s.capacity
                 ORDER BY c.name ASC, s.name ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $classes = array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'academic_year_class_stream_id' => (int) ($row['academic_year_class_stream_id'] ?? 0),
                    'stream_id' => (int) ($row['stream_id'] ?? 0),
                    'stream_name' => $row['stream_name'] ?? '',
                    'name' => $row['name'] ?? '',
                    'capacity' => isset($row['capacity']) ? (int) $row['capacity'] : null,
                    'student_count' => (int) ($row['student_count'] ?? 0),
                ];
            }, $rows ?: []);

            return $this->successResponse(['classes' => $classes], 'Placement classes retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getStats(array $ctx): array
    {
        try {
            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);

            $stats = [];

            $stmt = $this->db->query(
                "SELECT COUNT(*) as total
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}"
            );
            $stats['total_applications'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->query(
                "SELECT aa.status, COUNT(*) as count
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}
                 GROUP BY aa.status"
            );
            $stats['by_status'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $stmt = $this->db->query(
                "SELECT grade_applying_for, COUNT(*) as count
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.academic_year = YEAR(CURDATE()){$scopeFilter}
                 GROUP BY grade_applying_for"
            );
            $stats['by_grade'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $stmt = $this->db->query(
                "SELECT COUNT(*)
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY){$scopeFilter}"
            );
            $stats['this_week'] = (int) $stmt->fetchColumn();

            $stats['enrolled'] = (int) ($stats['by_status']['enrolled'] ?? 0);
            $stats['pending'] = $stats['total_applications'] - $stats['enrolled'] - (int) ($stats['by_status']['cancelled'] ?? 0);

            return $this->successResponse($stats, 'Admission statistics retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getPlacementTests(?int $id): array
    {
        try {
            if ($id !== null) {
                $stmt = $this->db->prepare(
                    "SELECT apt.*,
                            aa.applicant_name,
                            aa.application_no,
                            aa.grade_applying_for
                     FROM admission_placement_tests apt
                     JOIN admission_applications aa ON aa.id = apt.application_id
                     WHERE apt.id = ?
                     LIMIT 1"
                );
                $stmt->execute([$id]);
                $test = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$test) {
                    return $this->errorResponse('Placement test not found', 404);
                }

                return $this->successResponse($test, 'Placement test retrieved');
            }

            $stmt = $this->db->prepare(
                "SELECT apt.*,
                        aa.applicant_name,
                        aa.application_no,
                        aa.grade_applying_for
                 FROM admission_placement_tests apt
                 JOIN admission_applications aa ON aa.id = apt.application_id
                 ORDER BY apt.created_at DESC"
            );
            $stmt->execute();
            $tests = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->successResponse($tests, 'Placement tests retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function createPlacementTest(array $data, array $ctx): array
    {
        try {
            $applicationId = (int) ($data['application_id'] ?? 0);
            if ($applicationId <= 0) {
                return $this->errorResponse('application_id is required', 400);
            }

            $application = $this->getApplicationScopeRecord($applicationId);
            if (!$application) {
                return $this->errorResponse('Application not found', 404);
            }
            if (!$this->canViewApplicationRecord($application, $ctx)) {
                return $this->errorResponse('You do not have access to this admission application', 403);
            }

            $testDate = trim((string) ($data['test_date'] ?? ''));
            if ($testDate === '') {
                return $this->errorResponse('test_date is required', 400);
            }

            $testCode = trim((string) ($data['test_code'] ?? ''));
            if ($testCode === '') {
                $testCode = 'PT-' . $application['application_no'];
            }

            $subjectArea = trim((string) ($data['subject_area'] ?? 'General'));
            $maxScore = (float) ($data['max_score'] ?? 100);
            if ($maxScore <= 0) {
                $maxScore = 100;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO admission_placement_tests (
                    application_id, test_code, test_date, subject_area, max_score, recorded_by
                 ) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $applicationId,
                $testCode,
                $testDate,
                $subjectArea,
                $maxScore,
                $this->ctxUserId($ctx),
            ]);

            $testId = (int) $this->db->lastInsertId();

            return $this->successResponse(
                ['id' => $testId, 'test_code' => $testCode, 'application_id' => $applicationId],
                'Placement test created successfully',
                201
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function recordPlacementTestResult(int $id, array $data, array $ctx): array
    {
        try {
            $score = isset($data['score']) ? (float) $data['score'] : null;
            if ($score === null || $score < 0) {
                return $this->errorResponse('score is required and must be a non-negative number', 400);
            }

            $maxScore = (float) ($data['max_score'] ?? 100);
            if ($maxScore <= 0) {
                $maxScore = 100;
            }

            $percentage = isset($data['percentage']) ? (int) $data['percentage'] : (int) round(($score / $maxScore) * 100);

            $recommendation = strtolower(trim((string) ($data['recommendation'] ?? '')));
            if ($recommendation !== '' && !in_array($recommendation, ['promote', 'retain', 'conditional'], true)) {
                return $this->errorResponse('recommendation must be one of: promote, retain, conditional', 400);
            }
            $recommendation = $recommendation !== '' ? $recommendation : null;

            $remarks = trim((string) ($data['remarks'] ?? ($data['recommended_class'] ?? '')));
            $remarks = $remarks !== '' ? $remarks : null;

            $stmt = $this->db->prepare(
                "UPDATE admission_placement_tests
                 SET score = ?, max_score = ?, percentage = ?, recommendation = ?, remarks = ?, recorded_by = ?
                 WHERE id = ?"
            );
            $stmt->execute([
                $score,
                $maxScore,
                $percentage,
                $recommendation,
                $remarks,
                $this->ctxUserId($ctx),
                (int) $id,
            ]);

            if ($stmt->rowCount() === 0) {
                return $this->errorResponse('Placement test not found', 404);
            }

            return $this->successResponse(['id' => (int) $id], 'Placement test results recorded successfully');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getNotifications(array $ctx): array
    {
        try {
            $roleIds = $this->ctxRoleIds($ctx);
            $notifications = [
                'pending_tasks' => [],
                'total_count' => 0,
                'role' => !empty($roleIds) ? $roleIds[0] : null,
            ];

            $scopeFilter = $this->buildScopeFilter('aa', 'wi', $ctx);
            $canViewApplicationIntake = $this->getStageAuthorization()->canView(
                'application_applied',
                $this->ctxRoleIds($ctx),
                $this->ctxPermissionCodes($ctx)
            );
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling', $ctx);
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_results', $ctx);
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fees_payment', $ctx);
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('final_approval', 'final_enrollment', $ctx);

            if ($canViewApplicationIntake) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage = 'application_applied'
                       AND aa.status NOT IN ('cancelled', 'enrolled'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'documents_pending',
                        'label' => 'Applications Awaiting Completion',
                        'count' => $count,
                        'icon' => 'bi-file-earmark-text',
                        'color' => 'warning',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=documents_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canScheduleInterview || $canRecordInterview) {
                $interviewStageFilters = [];
                if ($canScheduleInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_scheduling'";
                }
                if ($canRecordInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_results'";
                }

                $interviewStageSql = empty($interviewStageFilters)
                    ? '1 = 0'
                    : implode(' OR ', $interviewStageFilters);

                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE ({$interviewStageSql})
                       AND aa.status NOT IN ('cancelled', 'enrolled'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'interview_pending',
                        'label' => 'Interviews Pending',
                        'count' => $count,
                        'icon' => 'bi-calendar-event',
                        'color' => 'info',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=interview_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canRecordPayment) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*)
                     FROM admission_applications aa
                     LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE aa.status IN ('placement_offered', 'fees_pending'){$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'payment_pending',
                        'label' => 'Payments to Record',
                        'count' => $count,
                        'icon' => 'bi-cash-stack',
                        'color' => 'success',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=payment_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            if ($canCompleteEnrollment) {
                $stmt = $this->db->query(
                    "SELECT COUNT(*) FROM admission_applications aa
                     JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                     WHERE wi.current_stage = 'final_enrollment' AND aa.status != 'enrolled'{$scopeFilter}"
                );
                $count = (int) $stmt->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'final_enrollment_pending',
                        'label' => 'Final Enrollments to Complete',
                        'count' => $count,
                        'icon' => 'bi-person-check',
                        'color' => 'dark',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=final_enrollment_pending'),
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            return $this->successResponse($notifications, 'Notifications retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function checkClassSpaceAvailability(int $applicationId, array $ctx): array
    {
        try {
            $stmt = $this->db->prepare("CALL sp_check_class_space_availability(?, ?)");
            $stmt->execute([$applicationId, $this->ctxUserId($ctx)]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->successResponse(['space_check' => $result], 'Class space availability checked');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function advanceWorkflowStage(array $data, array $ctx): array
    {
        try {
            $applicationId = (int) ($data['application_id'] ?? 0);
            $toStage = $data['to_stage'] ?? null;
            $action = $data['action'] ?? 'workflow_advance';
            $notes = $data['notes'] ?? null;
            $workflowUpdates = $data['workflow_updates'] ?? null;

            if ($applicationId <= 0 || !$toStage) {
                return $this->errorResponse('Application ID and target stage are required', 400);
            }

            $application = $this->getApplicationScopeRecord($applicationId);
            if (!$application || !$this->canViewApplicationRecord($application, $ctx)) {
                return $this->errorResponse('You do not have access to this admission application', 403);
            }
            $currentStage = $application['current_stage']
                ?? $this->inferStageFromApplication($application)
                ?? 'application_received';
            if ($currentStage === 'application_review' && in_array($toStage, ['interview_scheduling', 'student_admission_number'], true)) {
                $grade = (string) ($application['grade_applying_for'] ?? '');
                // Grade values are stored as Grade5/Grade 5; preserve the
                // uppercase letters before removing display formatting.
                $normalizedGrade = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $grade));
                $requiresInterview = in_array($normalizedGrade, ['grade4', 'grade5', 'grade6', 'grade7', 'grade8', 'grade9'], true);
                if ($requiresInterview && $toStage === 'student_admission_number' && $action !== 'admin_skip_interview') {
                    return $this->errorResponse('Grade 4-9 applications require an interview, unless an authorized reviewer explicitly skips it with a reason.', 422);
                }
                if (!$requiresInterview && $toStage === 'interview_scheduling') {
                    return $this->errorResponse('This grade does not require an interview; proceed to student admission number.', 422);
                }
            }
            if ($action === 'admin_skip_interview') {
                if (!$this->hasAnyAdmissionPermission('review_application', $ctx)
                    || $currentStage !== 'application_review'
                    || $toStage !== 'student_admission_number') {
                    return $this->errorResponse('Only an authorized admissions reviewer may skip the interview from application review.', 403);
                }
                if (trim((string) $notes) === '') {
                    return $this->errorResponse('A reason is required when skipping the interview.', 422);
                }
            }
            if (in_array($toStage, ['application_review', 'rejected'], true)
                && !$this->canProcessAdmissionActionForStage('review_application', $currentStage, $ctx)) {
                return $this->errorResponse('Insufficient permission to review this application', 403);
            }

            if (!$this->isValidWorkflowTransition($applicationId, $toStage)) {
                return $this->errorResponse('Invalid workflow transition for current stage', 400);
            }

            $stmt = $this->db->prepare("CALL sp_advance_admission_workflow_stage(?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $applicationId,
                $toStage,
                $action,
                $this->ctxUserId($ctx),
                $notes,
                $workflowUpdates,
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->successResponse([
                'workflow_instance_id' => $result['workflow_instance_id'] ?? null,
                'from_stage' => $result['from_stage'] ?? null,
                'to_stage' => $result['to_stage'] ?? null,
            ], 'Workflow stage advanced successfully');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return $this->errorResponse('An internal error occurred.');
        }
    }

    public function getStageMatrix(array $ctx): array
    {
        $matrix = $this->getStageAuthorization()->getStageMatrix(
            $this->ctxRoleIds($ctx),
            $this->ctxPermissionCodes($ctx)
        );

        $allowedTabs = [
            'application_pending' => !empty($matrix['application_applied']['can_view']) || !empty($matrix['application_received']['can_view']),
            'review_pending' => !empty($matrix['application_review']['can_view']),
            'interview_pending' => !empty($matrix['interview_scheduling']['can_view']) || !empty($matrix['interview_results']['can_view']),
            'student_admission_number_pending' => !empty($matrix['student_admission_number']['can_view']),
            'class_placement_pending' => !empty($matrix['class_placement']['can_view']),
            'payment_pending' => !empty($matrix['fees_payment']['can_view']),
            'id_generation_pending' => !empty($matrix['student_id_generation']['can_view']),
            'final_enrollment_pending' => !empty($matrix['final_enrollment']['can_view']),
        ];

        return $this->successResponse([
            'workflow' => 'student_admission',
            'stages' => array_values($matrix),
            'allowed_tabs' => $allowedTabs,
        ], 'Admission stage matrix retrieved');
    }

    public function getPaymentsForApplication(int $applicationId): array
    {
        return $this->successResponse([
            'payments' => $this->paymentService->getPaymentsForApplication($applicationId),
            'total_recorded' => $this->paymentService->getTotalRecorded($applicationId),
        ], 'Admission payments retrieved');
    }

    /** List paid admission applications for the accountant's selected year. */
    public function getPaidAdmissionPayments(int $academicYear): array
    {
        if ($academicYear < 2000 || $academicYear > 2100) {
            return $this->errorResponse('A valid academic year is required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT aa.id AS application_id, aa.academic_year, aa.application_no,
                        COALESCE(s.admission_no, '—') AS admission_no,
                        aa.applicant_name,
                        COALESCE(MAX(JSON_UNQUOTE(JSON_EXTRACT(aa.workflow_data_json, '$.admission_window_label'))), aw.label, '—') AS application_window,
                        COALESCE((SELECT c0.name
                                  FROM student_academic_enrollments sae0
                                  JOIN academic_year_class_streams aycs0 ON aycs0.id = sae0.academic_year_class_stream_id
                                  JOIN academic_year_classes ayc0 ON ayc0.id = aycs0.academic_year_class_id
                                  JOIN classes c0 ON c0.id = ayc0.class_id
                                  WHERE sae0.student_id = aa.enrolled_student_id
                                    AND sae0.enrollment_status = 'active'
                                  ORDER BY sae0.id DESC LIMIT 1), '—') AS class_assigned,
                        ROUND(SUM(CASE WHEN ap.status IN ('recorded', 'posted') THEN ap.amount ELSE 0 END), 2) AS amount_paid,
                        MAX(CASE WHEN ap.status IN ('recorded', 'posted') THEN COALESCE(ap.payment_date, ap.created_at) END) AS paid_at,
                        GROUP_CONCAT(DISTINCT ap.reference_no ORDER BY ap.id DESC SEPARATOR ', ') AS payment_references
                 FROM admission_payments ap
                 JOIN admission_applications aa ON aa.id = ap.application_id
                 LEFT JOIN students s ON s.id = aa.enrolled_student_id
                 LEFT JOIN admission_windows aw ON aw.academic_year_term_id = aa.target_term_id
                 WHERE aa.academic_year = :academic_year
                   AND ap.status IN ('recorded', 'posted')
                 GROUP BY aa.id, aa.academic_year, aa.application_no, s.admission_no,
                          aa.applicant_name, aw.label
                 ORDER BY paid_at DESC, aa.id DESC"
            );
            $stmt->execute(['academic_year' => $academicYear]);
            return $this->successResponse(['payments' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []], 'Paid admission payments retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionAdminManager] paid admission payments: ' . $e->getMessage());
            return $this->errorResponse('Unable to load paid admission payments');
        }
    }

    // ========================================================================
    // GUARD PRIMITIVES (used by the controller before delegating writes)
    // ========================================================================

    public function getApplicationScopeRecord(int $applicationId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                 FROM admission_applications aa
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE aa.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $application ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getApplicationScopeRecordByDocument(int $documentId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                 FROM admission_documents ad
                 JOIN admission_applications aa ON aa.id = ad.application_id
                 LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                 WHERE ad.id = ?
                 ORDER BY wi.id DESC
                 LIMIT 1"
            );
            $stmt->execute([$documentId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $application ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function canViewApplicationRecord(array $application, array $ctx): bool
    {
        if (
            $this->hasAnyAdmissionPermission('view_all', $ctx)
            || $this->ctxHasAnyPermission(['admission_view'], $ctx)
            || $this->hasAdmissionRouteAccess($ctx)
        ) {
            return true;
        }

        $applicationParentId = (int) ($application['parent_id'] ?? 0);

        if ($this->isParentLinkedToApplication($applicationParentId, $ctx)) {
            return true;
        }

        if (!$this->hasAnyAdmissionPermission('view_own', $ctx)) {
            return false;
        }

        $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
        $userId = $this->ctxUserId($ctx);
        $candidateOwnerIds = [
            (int) ($workflowData['assigned_to'] ?? 0),
            (int) ($workflowData['assigned_user_id'] ?? 0),
            (int) ($workflowData['created_by'] ?? 0),
            (int) ($workflowData['submitted_by'] ?? 0),
            (int) ($application['started_by'] ?? 0),
        ];

        return in_array($userId, $candidateOwnerIds, true);
    }

    public function canProcessAdmissionActionForApplication(string $actionGroup, array $application, array $ctx): bool
    {
        // Payment instructions are an admissions-office communication action.
        // Keep School Administrator access available even when legacy role
        // permission rows have not yet been backfilled with the newer payment
        // permission names.
        if ($actionGroup === 'record_payment' && $this->ctxHasRole([4], ['School Administrator'], $ctx)) {
            $stage = $this->normalizeStageCode($application['current_stage'] ?? null)
                ?? $this->inferStageFromApplication($application);
            if (in_array($stage, ['class_placement', 'fees_payment', 'student_id_generation'], true)) {
                return true;
            }
        }

        if (!$this->hasAnyAdmissionPermission($actionGroup, $ctx)) {
            return false;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return false;
        }

        return $this->canProcessAdmissionActionForStage($actionGroup, $currentStage, $ctx);
    }

    public function canProcessAdmissionActionForStage(string $actionGroup, ?string $stageCode, array $ctx): bool
    {
        if (!$this->hasAnyAdmissionPermission($actionGroup, $ctx)) {
            return false;
        }

        $stageCode = $this->normalizeStageCode($stageCode);
        if (!$stageCode) {
            return false;
        }

        $expectedStages = self::ACTION_STAGE_RULES[$actionGroup] ?? [];
        if (empty($expectedStages)) {
            return false;
        }

        $expectedNormalized = array_values(array_filter(array_map([$this, 'normalizeStageCode'], $expectedStages)));
        if (!in_array($stageCode, $expectedNormalized, true)) {
            return false;
        }

        if ($this->canActViaStagePermissions($actionGroup, $stageCode, $ctx)) {
            return true;
        }

        $requiredRole = $this->getStageRequiredRole($stageCode);
        if (!$requiredRole) {
            return $this->hasAnyAdmissionPermission($actionGroup, $ctx) || $this->hasAdmissionRouteAccess($ctx);
        }

        if ($this->userMatchesRequiredRole($requiredRole, $ctx)) {
            return true;
        }

        if ($this->hasAnyAdmissionPermission($actionGroup, $ctx) && $this->canBypassAdmissionStageRole($ctx)) {
            return true;
        }

        return false;
    }

    public function getAvailableActions(?string $currentStage, string $status, array $ctx): array
    {
        $actions = [];

        if (!$this->hasAnyAdmissionPermission('view_any', $ctx)) {
            return $actions;
        }

        if ($status === 'cancelled') {
            return [];
        }
        if ($status === 'enrolled') {
            return [];
        }

        $normalizedStage = $this->normalizeStageCode($currentStage) ?? $this->inferStageFromApplication(['status' => $status]);
        $requiredRole = $this->getStageRequiredRole($normalizedStage);
        if (!$this->userMatchesRequiredRole($requiredRole, $ctx) && !$this->canBypassAdmissionStageRole($ctx)) {
            return [];
        }

        switch ($normalizedStage) {
            case 'application_applied':
                break;
            case 'application_received':
            case 'application_review':
                if ($this->canProcessAdmissionActionForStage('review_application', $normalizedStage, $ctx)) {
                    $actions[] = 'review-application';
                }
                break;
            case 'interview_scheduling':
                if ($this->canProcessAdmissionActionForStage('schedule_interview', $normalizedStage, $ctx)) {
                    $actions = ['schedule-interview'];
                }
                break;
            case 'interview_results':
                if ($this->canProcessAdmissionActionForStage('record_interview', $normalizedStage, $ctx)) {
                    $actions = ['record-interview'];
                }
                if ($this->canProcessAdmissionActionForStage('admit_student', $normalizedStage, $ctx)) {
                    $actions[] = 'admit-student';
                }
                break;
            case 'student_admission_number':
                if ($this->canProcessAdmissionActionForStage('create_provisional_student', $normalizedStage, $ctx)) {
                    $actions = ['create-student-admission-number'];
                }
                break;
            case 'class_placement':
                if ($this->canProcessAdmissionActionForStage('record_payment', $normalizedStage, $ctx)) {
                    $actions[] = 'record-payment';
                }
                if ($this->canProcessAdmissionActionForStage('complete_enrollment', $normalizedStage, $ctx)) {
                    $actions[] = 'complete-enrollment';
                }
                break;
            case 'fees_payment':
            case 'student_id_generation':
                if ($this->canProcessAdmissionActionForStage('record_payment', $normalizedStage, $ctx)) {
                    $actions[] = 'record-payment';
                }
                if ($this->canProcessAdmissionActionForStage('generate_id_card', $normalizedStage, $ctx)) {
                    $actions[] = 'generate-id-card';
                }
                break;
            case 'final_enrollment':
                if ($this->canProcessAdmissionActionForStage('final_approval', $normalizedStage, $ctx)) {
                    $actions = ['final-enrollment'];
                }
                break;
            case 'enrolled':
                $actions = [];
                break;
            default:
                $actions = [];
        }

        return $actions;
    }

    /**
     * @return array|null An error response array when the action is not allowed, or null when allowed.
     */
    public function ensureApplicationActionAllowed(array $application, string $actionGroup): ?array
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        if (in_array($status, ['cancelled', 'enrolled'], true)) {
            return $this->errorResponse('This application can no longer be modified in its current status.', 409);
        }

        $expectedStages = self::ACTION_STAGE_RULES[$actionGroup] ?? [];
        if (empty($expectedStages)) {
            return null;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return $this->errorResponse('Workflow stage is not available for this application.', 409);
        }

        $expectedNormalized = array_map([$this, 'normalizeStageCode'], $expectedStages);
        if (!in_array($currentStage, $expectedNormalized, true)) {
            $stageMeta = $this->getCurrentStageMetadata($currentStage);
            $stageLabel = $stageMeta['name'] ?? str_replace('_', ' ', $currentStage);

            return $this->errorResponse("Action is not allowed at workflow stage '{$stageLabel}'.", 409);
        }

        return null;
    }

    public function hasAnyAdmissionPermission(string $group, array $ctx): bool
    {
        $permissionCodes = self::PERMISSIONS[$group] ?? [];
        $hasPermission = !empty($permissionCodes) && $this->ctxHasAnyPermission($permissionCodes, $ctx);

        if ($hasPermission) {
            return true;
        }

        if ($this->admissionRoleCanProcessGroup($group, $ctx)) {
            return true;
        }

        if ($group === 'view_any') {
            return $this->hasAdmissionRouteAccess($ctx);
        }

        return false;
    }

    /**
     * Normalise legacy payment field aliases into the canonical workflow names.
     */
    public function normalizePaymentData(array $paymentData): array
    {
        if (isset($paymentData['amount_paid']) && !isset($paymentData['amount'])) {
            $paymentData['amount'] = $paymentData['amount_paid'];
        }
        if (isset($paymentData['payment_method']) && !isset($paymentData['method'])) {
            $paymentData['method'] = $paymentData['payment_method'];
        }
        if (isset($paymentData['transaction_reference']) && !isset($paymentData['reference'])) {
            $paymentData['reference'] = $paymentData['transaction_reference'];
        }

        return $paymentData;
    }

    /**
     * Normalise interview decisions. The reviewer decides the outcome; the
     * score is supporting evidence only and never determines pass/fail.
     */
    public function normalizeInterviewAssessment(array $assessmentData): array
    {
        $decision = strtolower(trim((string) ($assessmentData['decision'] ?? $assessmentData['result'] ?? '')));
        $aliases = ['passed' => 'pass', 'approved' => 'pass', 'qualified' => 'pass', 'failed' => 'fail', 'rejected' => 'fail'];
        $assessmentData['decision'] = $aliases[$decision] ?? $decision;
        if (!in_array($assessmentData['decision'], ['pass', 'fail'], true)) {
            throw new \InvalidArgumentException('Interview decision must be pass or fail');
        }
        if (isset($assessmentData['score']) && $assessmentData['score'] !== '' && $assessmentData['score'] !== null) {
            $assessmentData['score'] = (int) $assessmentData['score'];
        } else {
            $assessmentData['score'] = null;
        }

        return $assessmentData;
    }

    // ========================================================================
    // INTERNAL HELPERS
    // ========================================================================

    private function attachQueueActions(array $records, array $ctx): array
    {
        foreach ($records as &$record) {
            $currentStage = $record['current_stage'] ?? null;
            $status = $record['status'] ?? null;
            $record['available_actions'] = $this->getAvailableActions($currentStage, $status, $ctx);
        }
        unset($record);

        return $records;
    }

    private function admissionRoleCanProcessGroup(string $group, array $ctx): bool
    {
        $schoolAdminGroups = [
            'review_application',
            'upload_document',
            'verify_document',
            'check_class_space',
            'schedule_interview',
            'record_interview',
            'admit_student',
            'create_provisional_student',
            'record_payment',
            'generate_id_card',
            'final_approval',
            'complete_enrollment',
        ];

        if (in_array($group, $schoolAdminGroups, true) && $this->ctxHasRole([4], ['School Administrator'], $ctx)) {
            return true;
        }

        if ($group === 'verify_document' && $this->ctxHasRole([5, 6], ['Headteacher', 'Deputy Head - Academic'], $ctx)) {
            return true;
        }

        return false;
    }

    private function canBypassAdmissionStageRole(array $ctx): bool
    {
        return $this->ctxHasAnyPermission(['*'], $ctx);
    }

    private function canActViaStagePermissions(string $actionGroup, string $stageCode, array $ctx): bool
    {
        $permissionCandidates = self::PERMISSIONS[$actionGroup] ?? [];
        if (empty($permissionCandidates)) {
            return false;
        }

        return $this->getStageAuthorization()->canAct(
            $stageCode,
            $permissionCandidates,
            $this->ctxRoleIds($ctx),
            $this->ctxPermissionCodes($ctx)
        );
    }

    private function getStageAuthorization(): AdmissionStageAuthorization
    {
        if ($this->stageAuthorization === null) {
            $this->stageAuthorization = new AdmissionStageAuthorization($this->db, $this->getStudentAdmissionWorkflowId());
        }

        return $this->stageAuthorization;
    }

    private function getStudentAdmissionWorkflowId(): int
    {
        if ($this->resolvedWorkflowId) {
            return $this->workflowId;
        }

        $this->resolvedWorkflowId = true;
        $this->workflowId = 0;

        try {
            $stmt = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = 'student_admission' LIMIT 1");
            $stmt->execute();
            $this->workflowId = (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            $this->workflowId = 0;
        }

        return $this->workflowId;
    }

    private function getWorkflowStageConfig(): array
    {
        if ($this->resolvedWorkflowStages) {
            return $this->workflowStageConfig;
        }

        $this->resolvedWorkflowStages = true;
        $this->workflowStageConfig = [];

        try {
            $rows = $this->db->query(
                "SELECT ws.code, ws.name, ws.required_role, ws.allowed_transitions, ws.sequence
                 FROM workflow_stages ws
                 JOIN workflow_definitions wd ON wd.id = ws.workflow_id
                 WHERE wd.code = 'student_admission'
                   AND ws.is_active = 1
                 ORDER BY ws.sequence ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $code = $this->normalizeStageCode($row['code'] ?? null);
                if (!$code) {
                    continue;
                }

                $allowedTransitions = [];
                if (!empty($row['allowed_transitions'])) {
                    $decoded = json_decode($row['allowed_transitions'], true);
                    if (is_array($decoded)) {
                        $allowedTransitions = array_values(array_filter(array_map([$this, 'normalizeStageCode'], $decoded)));
                    }
                }

                $this->workflowStageConfig[$code] = [
                    'code' => $code,
                    'name' => $row['name'] ?? null,
                    'required_role' => $row['required_role'] ?? null,
                    'allowed_transitions' => $allowedTransitions,
                    'sequence' => (int) ($row['sequence'] ?? 0),
                ];
            }
        } catch (Exception $e) {
            $this->workflowStageConfig = [];
        }

        return $this->workflowStageConfig;
    }

    private function getCurrentStageMetadata(?string $stageCode): array
    {
        $stageCode = $this->normalizeStageCode($stageCode);
        if (!$stageCode) {
            return [];
        }

        $config = $this->getWorkflowStageConfig();

        return $config[$stageCode] ?? [];
    }

    private function getAllowedTransitionsForStage(?string $stageCode): array
    {
        $meta = $this->getCurrentStageMetadata($stageCode);

        return $meta['allowed_transitions'] ?? [];
    }

    private function getStageRequiredRole(?string $stageCode): ?string
    {
        $meta = $this->getCurrentStageMetadata($stageCode);

        return $meta['required_role'] ?? null;
    }

    private function hasAdmissionRouteAccess(array $ctx): bool
    {
        $userId = $this->ctxUserId($ctx);
        $roleIds = $this->ctxRoleIds($ctx);

        if ($this->resolvedAdmissionRouteAccess && $this->admissionRouteAccessUserId === $userId) {
            return $this->admissionRouteAccess;
        }

        $this->resolvedAdmissionRouteAccess = true;
        $this->admissionRouteAccessUserId = $userId;
        $this->admissionRouteAccess = false;

        if ($userId <= 0 && empty($roleIds)) {
            return false;
        }

        try {
            $routeNames = self::ADMISSION_ROUTE_NAMES;
            $routePlaceholders = implode(',', array_fill(0, count($routeNames), '?'));

            if ($userId > 0) {
                $stmt = $this->db->prepare(
                    "SELECT ur.is_allowed
                     FROM user_routes ur
                     JOIN routes_registry r ON r.id = ur.route_id
                     WHERE ur.user_id = ?
                       AND r.name IN ({$routePlaceholders})
                       AND r.is_active = 1
                       AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                     ORDER BY ur.is_allowed DESC
                     LIMIT 1"
                );
                $stmt->execute(array_merge([$userId], $routeNames));
                $override = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($override) {
                    $this->admissionRouteAccess = (bool) ($override['is_allowed'] ?? false);

                    return $this->admissionRouteAccess;
                }
            }

            if (empty($roleIds)) {
                return false;
            }

            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT 1
                 FROM role_routes rr
                 JOIN routes_registry r ON r.id = rr.route_id
                 WHERE rr.is_allowed = 1
                   AND r.is_active = 1
                   AND r.name IN ({$routePlaceholders})
                   AND rr.role_id IN ({$placeholders})
                 LIMIT 1"
            );
            $params = array_merge($routeNames, array_map('intval', $roleIds));
            $stmt->execute($params);
            $this->admissionRouteAccess = (bool) $stmt->fetchColumn();
        } catch (Exception $e) {
            $this->admissionRouteAccess = false;
        }

        return $this->admissionRouteAccess;
    }

    private function getAdmissionsRouteRoleAliases(): array
    {
        if ($this->resolvedAdmissionsRouteRoleAliases) {
            return $this->admissionsRouteRoleAliases;
        }

        $this->resolvedAdmissionsRouteRoleAliases = true;
        $this->admissionsRouteRoleAliases = [];

        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT rl.name
                 FROM role_routes rr
                 JOIN routes_registry rt ON rt.id = rr.route_id
                 JOIN roles rl ON rl.id = rr.role_id
                 WHERE rr.is_allowed = 1
                   AND rt.is_active = 1
                   AND rt.name = ?"
            );
            $stmt->execute(['manage_students_admissions']);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $aliases = [];
            foreach ($rows as $row) {
                $alias = $this->normalizeRoleAlias($row['name'] ?? null);
                if ($alias) {
                    $aliases[] = $alias;
                }
            }

            $this->admissionsRouteRoleAliases = array_values(array_unique($aliases));
        } catch (Exception $e) {
            $this->admissionsRouteRoleAliases = [];
        }

        return $this->admissionsRouteRoleAliases;
    }

    private function userMatchesRequiredRole(?string $requiredRole, array $ctx): bool
    {
        if (!$requiredRole) {
            return true;
        }

        $required = $this->normalizeRoleAlias($requiredRole);
        if (!$required) {
            return true;
        }

        $roleNames = array_map([$this, 'normalizeRoleAlias'], $this->ctxRoleNames($ctx));
        foreach ($roleNames as $alias) {
            if ($alias !== null && $alias === $required) {
                return true;
            }
        }

        if ($required === 'parent') {
            return $this->getCurrentUserParentId($ctx) !== null;
        }

        if ($required === 'registrar') {
            $admissionsRoleAliases = $this->getAdmissionsRouteRoleAliases();
            if (empty($admissionsRoleAliases)) {
                return false;
            }

            foreach ($roleNames as $alias) {
                if (!$alias) {
                    continue;
                }
                if ($alias === 'headteacher' || $alias === 'headmaster' || $alias === 'headmistress') {
                    continue;
                }
                if (in_array($alias, $admissionsRoleAliases, true)) {
                    return true;
                }
            }

            return false;
        }

        if ($required === 'headteacher') {
            foreach ($roleNames as $alias) {
                if (in_array($alias, ['headteacher', 'headmaster', 'headmistress'], true)) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function getCurrentUserParentId(array $ctx): ?int
    {
        $userId = $this->ctxUserId($ctx);
        if (array_key_exists($userId, $this->parentIdCache)) {
            return $this->parentIdCache[$userId];
        }

        $this->parentIdCache[$userId] = null;

        $email = strtolower(trim((string) ($ctx['email'] ?? '')));
        if ($email === '') {
            return null;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT p.id
                 FROM parents p
                 JOIN persons pp ON pp.id = p.person_id
                 WHERE LOWER(TRIM(COALESCE(pp.email, ''))) = ?
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $id = $stmt->fetchColumn();
            $this->parentIdCache[$userId] = $id ? (int) $id : null;
        } catch (Exception $e) {
            $this->parentIdCache[$userId] = null;
        }

        return $this->parentIdCache[$userId];
    }

    private function buildScopeFilter(string $applicationAlias, string $workflowAlias, array $ctx): string
    {
        if (
            $this->hasAnyAdmissionPermission('view_all', $ctx)
            || $this->ctxHasAnyPermission(['admission_view'], $ctx)
            || $this->hasAdmissionRouteAccess($ctx)
        ) {
            return '';
        }

        if (!$this->hasAnyAdmissionPermission('view_own', $ctx)) {
            return ' AND 1 = 0 ';
        }

        $userId = $this->ctxUserId($ctx);
        $parentScopeSql = $this->buildParentScopeSql($applicationAlias, $ctx);

        return " AND (
            CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_to')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_user_id')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.created_by')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.submitted_by')) AS UNSIGNED) = {$userId}
            OR {$workflowAlias}.started_by = {$userId}
            {$parentScopeSql}
        ) ";
    }

    private function buildParentScopeSql(string $applicationAlias, array $ctx): string
    {
        $parentId = $this->getCurrentUserParentId($ctx);
        if (!$parentId) {
            return '';
        }

        return " OR {$applicationAlias}.parent_id = {$parentId}";
    }

    private function isParentLinkedToApplication(int $applicationParentId, array $ctx): bool
    {
        if ($applicationParentId <= 0) {
            return false;
        }

        $parentId = $this->getCurrentUserParentId($ctx);
        if (!$parentId) {
            return false;
        }

        return $applicationParentId === $parentId;
    }

    private function isValidWorkflowTransition(int $applicationId, string $toStage): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT current_stage
                 FROM workflow_instances
                WHERE reference_type = 'admission_application' AND reference_id = ?
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([$applicationId]);
            $current = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$current) {
                return true;
            }

            $currentStage = $current['current_stage'];
            $validTransitions = self::VALID_TRANSITIONS;

            return in_array($toStage, $validTransitions[$currentStage] ?? [], true);
        } catch (Exception $e) {
            return false;
        }
    }

    private function normalizeStageCode(?string $stageCode): ?string
    {
        if ($stageCode === null) {
            return null;
        }

        $stageCode = strtolower(trim($stageCode));
        if ($stageCode === '') {
            return null;
        }

        if ($stageCode === 'application_submission') {
            return 'application_applied';
        }

        $legacyMap = [
            'application' => 'application_applied',
            'application_submission' => 'application_applied',
            'documents_upload' => 'application_applied',
            'document_verification' => 'application_received',
            'documents_verification' => 'application_received',
            'class_capacity_check' => 'student_admission_number',
            'class_space_check' => 'student_admission_number',
            'interview_assessment' => 'interview_results',
            'admission_decision' => 'student_admission_number',
            'placement_offer' => 'student_admission_number',
            'fee_payment' => 'fees_payment',
            'enrollment' => 'final_enrollment',
            'enrollment_confirmation' => 'final_enrollment',
            'director_confirmation' => 'final_enrollment',
            'final_approval' => 'final_enrollment',
        ];

        if (isset($legacyMap[$stageCode])) {
            return $legacyMap[$stageCode];
        }

        return $stageCode;
    }

    private function inferStageFromApplication(array $application): ?string
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        switch ($status) {
            case 'submitted':
                return 'application_received';
            case 'documents_pending':
                return 'application_applied';
            case 'documents_verified':
                return $this->policy->requiresInterview((string) ($application['grade_applying_for'] ?? ''))
                    ? 'interview_scheduling'
                    : 'student_admission_number';
            case 'placement_offered':
            case 'fees_pending':
                return 'fees_payment';
            case 'enrolled':
                return 'enrolled';
            default:
                return null;
        }
    }

    private function normalizeRoleAlias(?string $roleName): ?string
    {
        if ($roleName === null) {
            return null;
        }

        $normalized = strtolower(trim($roleName));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function syncWorkflowIdentityData(array $workflowData, array $application): array
    {
        $workflowData['application_no'] = $application['application_no'] ?? ($workflowData['application_no'] ?? null);
        $workflowData['applicant_name'] = $application['applicant_name'] ?? ($workflowData['applicant_name'] ?? null);
        $workflowData['grade'] = $application['grade_applying_for'] ?? ($workflowData['grade'] ?? null);

        return $workflowData;
    }

    private function normalizeAdmissionDocuments(array $documents): array
    {
        return array_map(function (array $document): array {
            $fileUrl = $this->resolveAdmissionDocumentUrl($document);
            $document['file_url'] = $fileUrl;
            $document['download_url'] = $fileUrl;
            $document['display_name'] = $document['media_original_name']
                ?: basename((string) ($fileUrl ?: $document['document_path'] ?? ''));

            return $document;
        }, $documents);
    }

    private function resolveAdmissionDocumentUrl(array $document): ?string
    {
        $path = trim((string) ($document['document_path'] ?? ''));
        if ($path !== '' && !ctype_digit($path)) {
            return $path;
        }

        if (empty($document['media_filename']) || empty($document['media_context'])) {
            return $path !== '' ? $path : null;
        }

        return $this->managedMediaUrl(
            (string) $document['media_context'],
            $document['media_entity_id'] ?? null,
            (string) $document['media_filename'],
            $document['media_album_id'] ?? null
        );
    }

    private function buildAppUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . rtrim($appBase, '/') . '/' . ltrim($path, '/');
    }

    // ------------------------------------------------------------------------
    // User-context helpers
    // ------------------------------------------------------------------------

    private function ctxUserId(array $ctx): int
    {
        return (int) ($ctx['user_id'] ?? 0);
    }

    private function ctxRoleIds(array $ctx): array
    {
        $ids = $ctx['role_ids'] ?? [];

        return array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
    }

    private function ctxRoleNames(array $ctx): array
    {
        return array_values(array_filter($ctx['role_names'] ?? [], 'is_string'));
    }

    private function ctxPermissionCodes(array $ctx): array
    {
        return array_values(array_unique(array_filter($ctx['permission_codes'] ?? [])));
    }

    private function ctxHasRole(array $roleIds, array $roleNames, array $ctx): bool
    {
        $userRoleIds = $this->ctxRoleIds($ctx);
        foreach ($roleIds as $rid) {
            if (in_array((int) $rid, $userRoleIds, true)) {
                return true;
            }
        }

        $userRoleNames = array_map('strtolower', $this->ctxRoleNames($ctx));
        foreach ($roleNames as $rname) {
            if (in_array(strtolower($rname), $userRoleNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function ctxHasAnyPermission(array $permissionCodes, array $ctx): bool
    {
        $effective = array_merge($this->ctxPermissionCodes($ctx), $ctx['effective_permissions'] ?? []);

        foreach ($permissionCodes as $code) {
            if (in_array($code, $effective, true)) {
                return true;
            }
        }

        return in_array('*', $effective, true);
    }
}
