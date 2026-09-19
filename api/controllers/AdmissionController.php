<?php
namespace App\API\Controllers;

use App\API\Modules\admission\AdmissionAdminManager;
use App\API\Modules\admission\AdmissionPolicy;
use App\API\Modules\payments\PaymentsAPI;
use App\API\Services\AiDraftService;
use DomainException;
use Exception;

/**
 * AdmissionController — thin endpoint exposer.
 *
 * All data access, workflow orchestration, scope/permission decisions and
 * SQL now live in AdmissionAdminManager / StudentAdmissionWorkflow /
 * AdmissionPolicy / AdmissionStageAuthorization / AdmissionPaymentService.
 * This controller only: reads input, validates required fields, runs the
 * RBAC gates, delegates, and formats the response via handleApiResponse().
 *
 * Live-schema notes (KingsWayAcademy):
 *   - `routes` → `routes_registry`, `class_streams` → `academic_year_class_streams`,
 *     `parents.email/phone_*` → `persons` (see AdmissionAdminManager docblock).
 */
class AdmissionController extends BaseController
{
    private AdmissionAdminManager $admin;
    private AdmissionPolicy $policy;

    public function __construct() {
        parent::__construct();
        $this->admin = $this->contract('App\API\Modules\admission\AdmissionAdminManager');
        $this->policy = $this->contract('App\API\Modules\admission\AdmissionPolicy');
    }

    public function index()
    {
        return $this->success(['message' => 'Admission API is running']);
    }

    /**
     * GET /api/admissions/pending - Get pending admissions for dashboard
     */
    public function getPending($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view pending admissions');
        }

        return $this->handleApiResponse($this->admin->getPending($this->buildAdmissionContext()));
    }

    // 1. Application Submission
    public function postSubmitApplication($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('submit_application')) {
            return $this->forbidden('Insufficient permission to submit admission applications');
        }

        return $this->handleApiResponse($this->admin->workflow()->submitApplication($data));
    }

    // 2. Document Upload
    public function postUploadDocument($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $document_type = $data['document_type'] ?? null;
        $file = $_FILES['document'] ?? $_FILES['file'] ?? ($data['file'] ?? null);

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        if (!$document_type) {
            return $this->badRequest('document_type is required');
        }

        if (!$file) {
            return $this->badRequest('document file is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'upload_document', 'upload admission documents', 'Insufficient permission to upload admission documents');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->uploadDocument($application_id, $document_type, $file));
    }

    // 3. Document Verification
    public function postVerifyDocument($id = null, $data = [], $segments = [])
    {
        $document_id = $data['document_id'] ?? $id;
        $status = $data['status'] ?? null;
        $notes = $data['notes'] ?? '';

        if (!$document_id) {
            return $this->badRequest('document_id is required');
        }
        if (!in_array($status, ['verified', 'rejected'], true)) {
            return $this->badRequest('status must be either verified or rejected');
        }

        $application = $this->admin->getApplicationScopeRecordByDocument((int) $document_id);
        if (!$application) {
            return $this->notFound('Document or application not found');
        }

        $guard = $this->guardApplication($application, 'verify_document', 'verify admission documents', 'Insufficient permission to verify admission documents');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->verifyDocument($document_id, $status, $notes));
    }

    // 4. Interview Scheduling
    public function postScheduleInterview($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $session_id = !empty($data['session_id']) ? (int) $data['session_id'] : 0;
        if ($session_id > 0) {
            $guard = $this->guardApplicationAction((int) $application_id, 'schedule_interview', 'schedule admission interviews', 'Insufficient permission to schedule admission interviews');
            if ($guard !== null) return $guard;
            return $this->handleApiResponse($this->admin->workflow()->scheduleInterviewSession((int) $application_id, $session_id));
        }
        return $this->badRequest('session_id is required; select an existing interview session');
    }

    public function getInterviewSessions($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) return $this->forbidden('Insufficient permission to view interview sessions');
        return $this->handleApiResponse($this->admin->getInterviewSessions($data, $this->buildAdmissionContext()));
    }

    public function postInterviewSessions($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->admin->saveInterviewSession($data, $this->buildAdmissionContext()));
    }

    public function putInterviewSessions($id = null, $data = [], $segments = [])
    {
        $data['id'] = $id ?: ($data['id'] ?? 0);
        return $this->handleApiResponse($this->admin->saveInterviewSession($data, $this->buildAdmissionContext()));
    }

    public function postInterviewAssignment($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($data['application_id'] ?? 0);
        $sessionId = (int) ($data['session_id'] ?? 0);
        $guard = $this->guardApplicationAction($applicationId, 'schedule_interview', 'change interview assignment', 'Insufficient permission to change interview assignments');
        if ($guard !== null) return $guard;
        return $this->handleApiResponse($this->admin->reassignInterviewSession($applicationId, $sessionId, $data['reason'] ?? '', $this->buildAdmissionContext()));
    }

    public function postInterviewNotifications($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->admin->notifyInterviewApplicant($data, $this->buildAdmissionContext()));
    }

    // 5. Interview Assessment
    public function postRecordInterviewResults($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assessment_data = $data['assessment_data'] ?? $data;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'record_interview', 'record interview results', 'Insufficient permission to record interview results');
        if ($guard !== null) {
            return $guard;
        }

        try {
            $assessment_data = $this->admin->normalizeInterviewAssessment($assessment_data);
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        }

        return $this->handleApiResponse($this->admin->workflow()->recordInterviewResults($application_id, $assessment_data));
    }

    // 6. Placement Offer
    public function postGeneratePlacementOffer($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assigned_class_id = $data['assigned_class_id'] ?? null;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'placement_offer', 'generate placement offers', 'Insufficient permission to generate placement offers');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->generatePlacementOffer($application_id, $assigned_class_id));
    }

    // 7. Fee Payment
    public function postRecordFeePayment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $payment_data = $this->admin->normalizePaymentData($data['payment_data'] ?? $data);

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        if (!isset($payment_data['amount']) || $payment_data['amount'] === '') {
            return $this->badRequest('amount is required');
        }
        if (empty($payment_data['method'])) {
            return $this->badRequest('payment method is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'record_payment', 'record admission fee payments', 'Insufficient permission to record admission fee payments');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->recordFeePayment($application_id, $payment_data));
    }

    public function postPaymentInstructions($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($data['application_id'] ?? $id);
        if (!$applicationId) return $this->badRequest('application_id is required');
        $guard = $this->guardApplicationAction($applicationId, 'record_payment', 'send admission payment instructions', 'Insufficient permission to send admission payment instructions');
        if ($guard !== null) return $guard;
        return $this->handleApiResponse($this->admin->workflow()->sendPaymentInstructions($applicationId));
    }

    /** POST /api/admission/prompt-payment - send the server-calculated admission amount by M-Pesa STK. */
    public function postPromptPayment($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($data['application_id'] ?? $id ?? $segments[0] ?? 0);
        $phone = trim((string) ($data['phone'] ?? $data['phone_number'] ?? ''));
        if (!$applicationId || $phone === '') {
            return $this->badRequest('application_id and parent phone are required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'record_payment', 'send admission payment prompts', 'Insufficient permission to send admission payment prompts');
        if ($guard !== null) return $guard;

        // Resolve the queue on the server. Do not trust an amount supplied by
        // the browser: only an applicant currently at fees_payment may be
        // prompted, and the queue-calculated admission obligation is used.
        $queueResponse = $this->admin->getQueues($this->buildAdmissionContext());
        $queueData = is_array($queueResponse) ? ($queueResponse['data'] ?? $queueResponse) : [];
        $rows = $queueData['queues']['payment_pending'] ?? [];
        $application = null;
        foreach ((array) $rows as $row) {
            if ((int) ($row['id'] ?? 0) === $applicationId && ($row['current_stage'] ?? '') === 'fees_payment') {
                $application = $row;
                break;
            }
        }
        if (!$application) return $this->badRequest('This applicant is not currently at the admission payment stage');

        $amount = (float) ($application['registration_fee_due'] ?? 0);
        $recorded = (float) ($application['recorded_payment_amount'] ?? 0);
        if ($amount <= 0 || $recorded >= $amount || !empty($application['pending_payment_id'])) {
            return $this->badRequest('This admission payment is already paid or has a prompt awaiting confirmation');
        }

        try {
            $result = ($this->contract('App\API\Modules\payments\PaymentsAPI'))->triggerStkPush([
                'account_reference' => (string) ($application['application_no'] ?? ''),
                'phone' => $phone,
                'amount' => $amount,
                'description' => 'Admission payment ' . (string) ($application['application_no'] ?? ''),
            ]);
            return $this->handleApiResponse($result);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[AdmissionController] admission payment prompt failed: ' . $e->getMessage());
            return $this->respond(null, 'Unable to send the admission payment prompt', 500, false);
        }
    }

    public function postConfirmFeePayment($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($data['application_id'] ?? $id ?? $segments[0] ?? 0);
        $paymentId = (int) ($data['payment_id'] ?? 0);
        if (!$applicationId || !$paymentId) {
            return $this->badRequest('application_id and payment_id are required');
        }
        $guard = $this->guardApplicationAction($applicationId, 'record_payment', 'verify admission payments', 'Insufficient permission to verify admission payments');
        if ($guard !== null) return $guard;
        return $this->handleApiResponse($this->admin->workflow()->confirmManualPayment($applicationId, $paymentId, $data));
    }

    // 8. Enrollment
    public function postCompleteEnrollment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }

        $guard = $this->guardApplicationAction((int) $application_id, 'complete_enrollment', 'complete enrollment', 'Insufficient permission to complete enrollment');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->completeEnrollment($application_id, $data));
    }

    public function getPolicy($id = null, $data = [], $segments = [])
    {
        return $this->success($this->policy->getPolicyPayload(), 'Admission policy retrieved');
    }

    public function getStageMatrix($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission stages');
        }

        return $this->handleApiResponse($this->admin->getStageMatrix($this->buildAdmissionContext()));
    }

    public function getPayments($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission payments');
        }

        return $this->handleApiResponse($this->admin->getPaymentsForApplication((int) $applicationId));
    }

    /** GET /api/admission/paid-payments?academic_year=2027 */
    public function getPaidPayments($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission payments');
        }
        $rawAcademicYear = trim((string) ($data['academic_year'] ?? $data['year'] ?? date('Y')));
        // The UI displays labels such as 2026/2027, while admission_applications
        // stores the ending year as YEAR(4): 2027.
        $academicYear = preg_match('/(\d{4})\s*$/', $rawAcademicYear, $matches)
            ? (int) $matches[1]
            : (int) $rawAcademicYear;
        return $this->handleApiResponse($this->admin->getPaidAdmissionPayments($academicYear));
    }

    public function postConfirmEnrollment($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($data['application_id'] ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAdmissionPermission('confirm_enrollment')) {
            return $this->forbidden('Insufficient permission to confirm enrollment');
        }

        return $this->handleApiResponse($this->admin->workflow()->confirmEnrollment((int) $applicationId, (string) ($data['notes'] ?? '')));
    }

    /**
     * POST /api/admission/check-class-space/{id} - Record the class-space decision.
     * The read-only availability is GET getCheckClassSpace; this persists the
     * registrar/director decision (available vs. blocked) via the workflow proc.
     */
    public function postCheckClassSpace($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'check_class_space', 'check class space', 'Insufficient permission to check class space');
        if ($guard !== null) {
            return $guard;
        }

        $available = !empty($data['available']) && $data['available'] !== 'false';
        $notes = $data['notes'] ?? null;

        return $this->handleApiResponse($this->admin->workflow()->checkClassSpace($applicationId, (bool) $available, $notes ? (string) $notes : null));
    }

    /**
     * POST /api/admission/admit-student/{id} - Director/Headteacher admits the student.
     */
    public function postAdmitStudent($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'admit_student', 'admit student', 'Insufficient permission to admit student');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->admitStudent($applicationId));
    }

    /**
     * POST /api/admission/generate-student-id-card/{id} - Generate the ID card.
     */
    public function postGenerateStudentIdCard($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'generate_id_card', 'generate student ID card', 'Insufficient permission to generate student ID card');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->generateStudentIdCard($applicationId));
    }

    /**
     * POST /api/admission/final-approval/{id} - Final approval before enrollment.
     */
    public function postFinalApproval($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $data['application_id'] ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $guard = $this->guardApplicationAction($applicationId, 'final_approval', 'grant final approval', 'Insufficient permission to grant final approval');
        if ($guard !== null) {
            return $guard;
        }

        return $this->handleApiResponse($this->admin->workflow()->finalApproval($applicationId));
    }

    /**
     * GET /api/admission/check-class-space/{id} - Check class space availability for an application
     */
    public function getCheckClassSpace($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to check class space');
        }

        return $this->handleApiResponse($this->admin->checkClassSpaceAvailability($applicationId, $this->buildAdmissionContext()));
    }

    /**
     * POST /api/admission/advance-workflow-stage - Advance workflow stage with proper validation
     */
    public function postAdvanceWorkflowStage($id = null, $data = [], $segments = [])
    {
        return $this->handleApiResponse($this->admin->advanceWorkflowStage($data, $this->buildAdmissionContext()));
    }

    /**
     * POST /api/admission/create-provisional-student/{id} - Create provisional student record
     */
    public function postCreateProvisionalStudent($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        if (!$this->hasAdmissionPermission('submit_application')) {
            return $this->forbidden('Insufficient permission to create student record');
        }

        // createStudentAdmissionNumber() already advances the workflow instance to
        // fees_payment and writes the student linkage, so no second
        // sp_advance_admission_workflow_stage call is needed here.
        return $this->handleApiResponse($this->admin->workflow()->createStudentAdmissionNumber($applicationId));
    }

    /** POST /api/admission/create-student-admission-number/{id} */
    public function postCreateStudentAdmissionNumber($id = null, $data = [], $segments = [])
    {
        return $this->postCreateProvisionalStudent($id, $data, $segments);
    }

    /**
     * GET /api/admission/queues - Get workflow queues by stage for role-based views
     */
    public function getQueues($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admissions queues');
        }

        return $this->handleApiResponse($this->admin->getQueues($this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/application/{id} - Get single application details with full workflow status
     */
    public function getApplication($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission applications');
        }

        if (!$id) {
            return $this->badRequest('Application ID is required');
        }

        return $this->handleApiResponse($this->admin->getApplication((int) $id, $this->buildAdmissionContext()));
    }

    /** POST /api/admission/ai-followup-draft-queue */
    public function postAiFollowupDraftQueue($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to prepare admissions assistance');
        }

        $applicationId = (int) ($data['application_id'] ?? $id ?? 0);
        if ($applicationId < 1) {
            return $this->badRequest('application_id is required');
        }

        $context = $this->buildAdmissionContext();
        $application = $this->admin->getApplicationScopeRecord($applicationId);
        if (!$application || !$this->admin->canViewApplicationRecord($application, $context)) {
            return $this->notFound('Application not found');
        }

        $prepared = $this->admin->getAiFollowupContext(
            $applicationId,
            $context,
            (string) ($data['channel'] ?? 'email')
        );
        if (($prepared['success'] ?? false) !== true) {
            return $this->handleApiResponse($prepared);
        }

        $workflowContext = [
            'user_id' => (int) ($context['user_id'] ?? 0),
            'permissions' => array_values(array_unique(array_merge(
                (array) ($context['effective_permissions'] ?? []),
                (array) ($context['permission_codes'] ?? [])
            ))),
            'request_id' => $_SERVER['REQUEST_ID'] ?? '',
        ];

        try {
            $service = $this->contract(AiDraftService::class);
            $queued = $service->queue(
                'admissions.application_followup_draft',
                $workflowContext,
                (array) ($prepared['data']['input'] ?? []),
                [
                    'subject_type' => 'admission_application',
                    'subject_id' => $applicationId,
                ]
            );
            return $this->accepted($queued, 'Admissions follow-up draft queued for review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionController] AI draft queue failed: ' . $e->getMessage());
            return $this->serverError('Unable to queue admissions assistance');
        }
    }

    /** POST /api/admission/ai-interview-preparation-queue */
    public function postAiInterviewPreparationQueue($id = null, $data = [], $segments = [])
    {
        return $this->queueAdmissionsAi($id, $data, 'admissions.interview_preparation', 'admission_interview_preparation', function (array $application, array $data): array {
            return ['stage' => (string) ($application['current_stage'] ?? $application['stage'] ?? ''), 'grade_band' => (string) ($application['grade_applying'] ?? $application['grade'] ?? ''), 'days_waiting' => (string) max(0, (int) ($application['days_waiting'] ?? 0)), 'missing_items' => array_values(array_map('strval', (array) ($application['missing_items'] ?? []))), 'interview_focus' => mb_substr(trim((string) ($data['interview_focus'] ?? 'Verify readiness, communication, and age-appropriate learning context.')), 0, 500)];
        });
    }

    /** POST /api/admission/ai-placement-review-queue */
    public function postAiPlacementReviewQueue($id = null, $data = [], $segments = [])
    {
        return $this->queueAdmissionsAi($id, $data, 'admissions.placement_review', 'admission_placement_review', function (array $application, array $data): array {
            $score = isset($application['interview_score']) ? (float) $application['interview_score'] : null;
            return ['stage' => (string) ($application['current_stage'] ?? $application['stage'] ?? ''), 'grade_band' => (string) ($application['grade_applying'] ?? $application['grade'] ?? ''), 'interview_status' => (string) ($application['interview_status'] ?? 'not_available'), 'placement_signal_band' => $score === null ? 'not_available' : ($score >= 75 ? 'strong' : ($score >= 50 ? 'review' : 'needs_review')), 'capacity_signal' => mb_substr(trim((string) ($data['capacity_signal'] ?? 'verify_with_authorized_capacity_service')), 0, 100), 'follow_up_intent' => 'Verify policy, assessment evidence, and class capacity before any official placement decision.'];
        });
    }

    private function queueAdmissionsAi($id, array $data, string $workflow, string $subjectType, callable $builder): array
    {
        if (!$this->hasAdmissionPermission('view_any')) return $this->forbidden('Admissions AI permission is required');
        $applicationId = (int) ($data['application_id'] ?? $id ?? 0);
        if ($applicationId < 1) return $this->badRequest('application_id is required');
        $context = $this->buildAdmissionContext(); $application = $this->admin->getApplicationScopeRecord($applicationId);
        if (!$application || !$this->admin->canViewApplicationRecord($application, $context)) return $this->notFound('Application not found');
        $workflowContext = ['user_id' => (int) ($context['user_id'] ?? 0), 'permissions' => array_values(array_unique(array_merge((array) ($context['effective_permissions'] ?? []), (array) ($context['permission_codes'] ?? [])))), 'request_id' => $_SERVER['REQUEST_ID'] ?? ''];
        try { return $this->accepted($this->contract(AiDraftService::class)->queue($workflow, $workflowContext, $builder($application, $data), ['subject_type' => $subjectType, 'subject_id' => $applicationId, 'scope' => 'authorized_admission_application']), 'Admissions AI review queued'); }
        catch (DomainException $e) { return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 422), false); }
        catch (Exception $e) { return $this->serverError('Unable to queue admissions AI review'); }
    }

    /** GET /api/admission/ai-drafts?scope=own|review */
    public function getAiDrafts($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admissions assistance');
        }

        $scope = strtolower((string) ($_GET['scope'] ?? $data['scope'] ?? 'own'));
        $review = $scope === 'review';
        if ($review && !$this->hasAdmissionPermission('review_application')) {
            return $this->forbidden('Admissions approval permission is required to review AI drafts');
        }

        try {
            $service = $this->contract(AiDraftService::class);
            return $this->success([
                'drafts' => $service->listForReview(
                    $this->getDb()->getConnection(),
                    (int) ($this->getUserId() ?? 0),
                    $review,
                    'admissions'
                ),
                'scope' => $review ? 'review' : 'own',
            ], 'Admissions AI drafts retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionController] AI draft list failed: ' . $e->getMessage());
            return $this->serverError('Unable to load admissions assistance');
        }
    }

    /** POST /api/admission/ai-drafts/{id}/approve */
    public function postAiDraftApprove($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('review_application')) {
            return $this->forbidden('Admissions approval permission is required to approve AI drafts');
        }
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) {
            return $this->badRequest('draft_id is required');
        }
        $workflow = (string) ($data['workflow_id'] ?? '');
        if (!in_array($workflow, $this->admissionsAiApprovalWorkflows(), true)) {
            return $this->badRequest('An explicit admissions AI workflow is required for approval');
        }
        return $this->approveAdmissionsAiDraft($draftId, $workflow);
    }

    /** POST /api/admission/ai-followup-draft-approve/{id} */
    public function postAiFollowupDraftApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAdmissionsAiDraftFromRequest($id, $data, $segments, 'admissions.application_followup_draft');
    }

    /** POST /api/admission/ai-interview-preparation-approve/{id} */
    public function postAiInterviewPreparationApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAdmissionsAiDraftFromRequest($id, $data, $segments, 'admissions.interview_preparation');
    }

    /** POST /api/admission/ai-placement-review-approve/{id} */
    public function postAiPlacementReviewApprove($id = null, $data = [], $segments = [])
    {
        return $this->approveAdmissionsAiDraftFromRequest($id, $data, $segments, 'admissions.placement_review');
    }

    private function approveAdmissionsAiDraftFromRequest($id, array $data, array $segments, string $workflow): array
    {
        if (!$this->hasAdmissionPermission('review_application')) {
            return $this->forbidden('Admissions approval permission is required to approve AI drafts');
        }
        $draftId = (int) ($id ?? $data['draft_id'] ?? $segments[0] ?? 0);
        if ($draftId < 1) return $this->badRequest('draft_id is required');
        return $this->approveAdmissionsAiDraft($draftId, $workflow);
    }

    private function approveAdmissionsAiDraft(int $draftId, string $workflow): array
    {
        try {
            $approved = $this->contract(AiDraftService::class)->approve(
                $this->getDb()->getConnection(),
                $draftId,
                (int) ($this->getUserId() ?? 0),
                $workflow
            );
            return $this->success([
                'draft_id' => $draftId,
                'workflow_id' => $workflow,
                'status' => 'approved',
                'review_only' => true,
                'draft' => $approved['draft'] ?? [],
            ], 'Admissions AI draft approved for staff review');
        } catch (DomainException $e) {
            return $this->respond(null, $e->getMessage(), (int) ($e->getCode() ?: 409), false);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AdmissionController] AI draft approval failed: ' . $e->getMessage());
            return $this->serverError('Unable to approve admissions assistance');
        }
    }

    private function admissionsAiApprovalWorkflows(): array
    {
        return [
            'admissions.application_followup_draft',
            'admissions.interview_preparation',
            'admissions.placement_review',
        ];
    }

    /**
     * PUT /api/admission/application/{id} - Inline edit applicant/application fields.
     */
    public function putApplication($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Application ID is required');
        }

        return $this->handleApiResponse($this->admin->updateApplicationFields((int) $id, $data, $this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/windows - List admission intake windows.
     */
    public function getAdmissionWindows($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission windows');
        }

        return $this->handleApiResponse($this->admin->getAdmissionWindows($this->buildAdmissionContext()));
    }

    // Router-compatible aliases for /api/admission/windows.
    public function getWindows($id = null, $data = [], $segments = [])
    {
        return $this->getAdmissionWindows($id, $data, $segments);
    }

    /**
     * POST /api/admission/windows - Create an admission intake window.
     */
    public function postAdmissionWindows($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to manage admission windows');
        }

        return $this->handleApiResponse($this->admin->saveAdmissionWindow($data, $this->buildAdmissionContext()));
    }

    public function postWindows($id = null, $data = [], $segments = [])
    {
        return $this->postAdmissionWindows($id, $data, $segments);
    }

    /**
     * PUT /api/admission/windows/{id} - Update or toggle an admission intake window.
     * When only `status` is supplied, it toggles the window; otherwise it saves
     * the full window (requires academic_year_id + academic_year_term_id).
     */
    public function putAdmissionWindows($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to manage admission windows');
        }
        $data['id'] = $id ? (int) $id : ($data['id'] ?? 0);
        $statusOnly = ($data['id'] ?? 0) > 0
            && array_key_exists('status', $data)
            && !array_key_exists('academic_year_id', $data)
            && !array_key_exists('academic_year_term_id', $data);

        if ($statusOnly) {
            return $this->handleApiResponse($this->admin->toggleAdmissionWindow((int) $data['id'], $data, $this->buildAdmissionContext()));
        }

        return $this->handleApiResponse($this->admin->saveAdmissionWindow($data, $this->buildAdmissionContext()));
    }

    public function putWindows($id = null, $data = [], $segments = [])
    {
        return $this->putAdmissionWindows($id, $data, $segments);
    }

    /**
     * POST /api/admission/windows/{id}/toggle - Open or close an intake window.
     */
    public function postToggleAdmissionWindow($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('Admission window ID is required');
        }
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to manage admission windows');
        }

        return $this->handleApiResponse($this->admin->toggleAdmissionWindow((int) $id, $data, $this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/open-terms - Open intake terms for the application form.
     */
    public function getOpenAdmissionTerms($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission terms');
        }

        return $this->handleApiResponse($this->admin->getOpenAdmissionTerms($this->buildAdmissionContext()));
    }

    // Router alias used by the frontend API wrapper.
    public function getOpenTerms($id = null, $data = [], $segments = [])
    {
        return $this->getOpenAdmissionTerms($id, $data, $segments);
    }

    /**
     * GET /api/admission/placement-classes - Get active classes for placement offers
     */
    public function getPlacementClasses($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view placement classes');
        }

        return $this->handleApiResponse($this->admin->getPlacementClasses());
    }

    /**
     * GET /api/admission/stats - Get admission statistics for dashboards
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission statistics');
        }

        return $this->handleApiResponse($this->admin->getStats($this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/placement-tests - List placement tests (optionally /{id})
     * Live: admission_placement_tests joined with admission_applications.
     */
    public function getPlacementTests($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view placement tests');
        }

        return $this->handleApiResponse($this->admin->getPlacementTests($id !== null ? (int) $id : null));
    }

    /**
     * POST /api/admission/placement-test - Create a placement test for an application
     * Body: { application_id, test_date, test_code?, subject_area?, max_score? }
     */
    public function postPlacementTest($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to create placement tests');
        }

        return $this->handleApiResponse($this->admin->createPlacementTest($data, $this->buildAdmissionContext()));
    }

    /**
     * PUT /api/admission/placement-test/{id} - Record results for a placement test
     * Body: { score, max_score?, percentage?, recommendation?, remarks? }
     */
    public function putPlacementTest($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to record placement test results');
        }

        if ($id === null) {
            return $this->badRequest('Placement test ID is required');
        }

        return $this->handleApiResponse($this->admin->recordPlacementTestResult((int) $id, $data, $this->buildAdmissionContext()));
    }

    /**
     * GET /api/admission/notifications - Get role-specific admission notifications for dashboards
     */
    public function getNotifications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission notifications');
        }

        return $this->handleApiResponse($this->admin->getNotifications($this->buildAdmissionContext()));
    }

    // ========================================================================
    // PRIVATE HELPERS (RBAC gates only — no SQL, no business logic)
    // ========================================================================

    private function hasAdmissionPermission(string $group): bool
    {
        return $this->admin->hasAnyAdmissionPermission($group, $this->buildAdmissionContext());
    }

    /**
     * Load the application scope record then run the full guard stack.
     *
     * @return mixed null when allowed, otherwise a BaseController error response
     */
    private function guardApplicationAction(int $applicationId, string $actionGroup, string $denyMessage, string $permissionDenyMessage)
    {
        $application = $this->admin->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }

        return $this->guardApplication($application, $actionGroup, $denyMessage, $permissionDenyMessage);
    }

    /**
     * @return mixed null when allowed, otherwise a BaseController error response
     */
    private function guardApplication(array $application, string $actionGroup, string $denyMessage, string $permissionDenyMessage)
    {
        $ctx = $this->buildAdmissionContext();

        if (!$this->admin->canViewApplicationRecord($application, $ctx)) {
            return $this->forbidden('You do not have access to this admission application');
        }

        if (!$this->admin->canProcessAdmissionActionForApplication($actionGroup, $application, $ctx)) {
            return $this->forbidden($permissionDenyMessage);
        }

        $actionGuard = $this->admin->ensureApplicationActionAllowed($application, $actionGroup);
        if ($actionGuard !== null) {
            return $this->handleApiResponse($actionGuard);
        }

        return null;
    }

    private function buildAdmissionContext(): array
    {
        return [
            'user_id' => (int) ($this->getUserId() ?? 0),
            'role_ids' => $this->getCurrentUserRoleIds(),
            'role_names' => $this->getUserRoleNames(),
            'permission_codes' => $this->getCurrentUserPermissionCodes(),
            'effective_permissions' => is_array($this->user['effective_permissions'] ?? null) ? $this->user['effective_permissions'] : [],
            'email' => $this->user['email'] ?? null,
        ];
    }

    private function getCurrentUserRoleIds(): array
    {
        $roleIds = $this->getUserRoleIds();
        if (!empty($roleIds)) {
            return array_values(array_unique(array_map('intval', $roleIds)));
        }

        if (isset($this->user['role_ids']) && is_array($this->user['role_ids'])) {
            return array_values(array_unique(array_map('intval', $this->user['role_ids'])));
        }
        if (!empty($this->user['role_id'])) {
            return [(int) $this->user['role_id']];
        }

        return [];
    }

    private function getCurrentUserPermissionCodes(): array
    {
        $permissions = [];
        foreach (['permissions', 'effective_permissions'] as $key) {
            $source = $this->user[$key] ?? [];
            if (is_array($source)) {
                foreach ($source as $permission) {
                    $permissions[] = is_array($permission) ? (string) ($permission['code'] ?? $permission['name'] ?? '') : (string) $permission;
                }
            }
        }

        return array_values(array_unique(array_filter($permissions)));
    }
}
