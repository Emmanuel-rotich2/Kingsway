<?php
namespace App\API\Controllers;

use App\API\Modules\admission\StudentAdmissionWorkflow;
use App\API\Modules\admission\AdmissionPolicy;
use App\API\Modules\admission\AdmissionPaymentService;
use App\API\Modules\admission\AdmissionStageAuthorization;
use Exception;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;

class AdmissionController extends BaseController
{
    private StudentAdmissionWorkflow $api;
    private AdmissionPolicy $policy;
    private AdmissionPaymentService $paymentService;
    private ?AdmissionStageAuthorization $stageAuthorization = null;
    private bool $resolvedCurrentUserParentId = false;
    private ?int $currentUserParentId = null;
    private bool $resolvedAdmissionRouteAccess = false;
    private bool $admissionRouteAccess = false;
    private bool $resolvedWorkflowStages = false;
    private array $workflowStageConfig = [];
    private bool $resolvedAdmissionsRouteRoleAliases = false;
    private array $admissionsRouteRoleAliases = [];

    private const PERMISSIONS = [
        'view_any' => [
            'admission_view',
            'admission_applications_view_all',
            'admission_applications_view_own',
            'admission_applications_view'
        ],
        'view_all' => [
            'admission_applications_view_all'
        ],
        'view_own' => [
            'admission_applications_view_own'
        ],
        'submit_application' => [
            'admission_applications_create',
            'admission_applications_submit',
            'admission_manage'
        ],
        'review_application' => [
            'admission_manage'
        ],
        'upload_document' => [
            'admission_manage',
            'admission_documents_upload',
            'admission_documents_create',
            'admission_applications_upload'
        ],
        'verify_document' => [
            'admission_manage',
            'admission_documents_verify',
            'admission_documents_approve',
            'admission_documents_validate',
            'admission_applications_verify'
        ],
        'schedule_interview' => [
            'admission_manage',
            'admission_interviews_schedule',
            'admission_applications_schedule'
        ],
        'record_interview' => [
            'admission_manage',
            'admission_interviews_create',
            'admission_interviews_edit',
            'admission_interviews_approve',
            'admission_interviews_verify'
        ],
        'check_class_space' => [
            'admission_manage',
            'admission_applications_verify'
        ],
        'admit_student' => [
            'admission_approve',
            'admission_applications_approve_final'
        ],
        'create_provisional_student' => [
            'admission_approve',
            'admission_applications_create'
        ],
        'record_payment' => [
            'admission_payments_create',
            'admission_fee_payments_record',
            'admission_payments_record',
            'admission_applications_validate'
        ],
        'generate_id_card' => [
            'admission_manage',
            'admission_documents_create'
        ],
        'final_approval' => [
            'admission_approve',
            'admission_applications_approve_final'
        ],
        'complete_enrollment' => [
            'admission_enrollment_complete',
            'admission_applications_approve_final'
        ],
        'confirm_enrollment' => [
            'admission_enrollment_confirm'
        ],
    ];

    private const ACTION_STAGE_RULES = [
        'review_application' => ['application_received', 'application_review'],
        'upload_document' => ['application_review', 'documents_upload', 'documents_verification'],
        'verify_document' => ['documents_upload', 'documents_verification'],
        'check_class_space' => ['documents_verification', 'class_space_check'],
        'schedule_interview' => ['class_space_check', 'interview_scheduling'],
        'record_interview' => ['interview_scheduling', 'interview_results'],
        'placement_offer' => ['admission_decision', 'fees_payment'],
        'admit_student' => ['interview_results', 'admission_decision', 'class_space_check'],
        'create_provisional_student' => ['provisional_student_creation'],
        'record_payment' => ['fees_payment', 'student_id_generation'],
        'generate_id_card' => ['student_id_generation', 'final_approval'],
        'final_approval' => ['final_approval', 'enrollment'],
        'complete_enrollment' => ['enrollment'],
        'confirm_enrollment' => ['enrolled', 'director_confirmation'],
    ];

    public function __construct() {
        parent::__construct();
        $this->api = new StudentAdmissionWorkflow();
        $this->policy = new AdmissionPolicy();
        $this->paymentService = new AdmissionPaymentService($this->db->getConnection());
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
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view pending admissions');
            }

            $db = $this->db;
            $scopeFilter = $this->buildScopeFilter('aa', 'wi');

            // Get pending admissions count
            $countQuery = "
                SELECT COUNT(*) as total_pending
                FROM admission_applications aa
                LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                WHERE aa.status NOT IN ('enrolled', 'cancelled')
                {$scopeFilter}
            ";
            $countResult = $db->query($countQuery);
            $countRow = $countResult->fetch();
            $totalPending = (int) ($countRow['total_pending'] ?? 0);

            // Get recent pending admissions (last 8)
            $listQuery = "
                SELECT 
                    aa.id,
                    aa.application_no,
                    aa.applicant_name,
                    aa.grade_applying_for,
                    aa.status,
                    aa.created_at as admission_date,
                    wi.current_stage
                FROM admission_applications aa
                LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                WHERE aa.status NOT IN ('enrolled', 'cancelled')
                {$scopeFilter}
                ORDER BY aa.created_at DESC
                LIMIT 8
            ";

            $listResult = $db->query($listQuery);
            $recentAdmissions = [];
            while ($row = $listResult->fetch()) {
                $recentAdmissions[] = [
                    'id' => $row['id'],
                    'application_no' => $row['application_no'] ?? null,
                    'applicant_name' => $row['applicant_name'] ?? 'Unknown',
                    'grade_applying_for' => $row['grade_applying_for'] ?? null,
                    'current_stage' => $row['current_stage'] ?? null,
                    'admission_date' => $row['admission_date'],
                    'status' => $row['status'] ?? 'submitted'
                ];
            }

            return $this->success([
                'total_pending' => $totalPending,
                'recent' => $recentAdmissions,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Pending admissions retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch pending admissions: ' . $e->getMessage());
        }
    }

    // Explicit REST endpoints for all StudentAdmissionWorkflow public methods

    // 1. Application Submission
    public function postSubmitApplication($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAnyAdmissionPermission('submit_application')) {
            return $this->forbidden('Insufficient permission to submit admission applications');
        }

        $result = $this->api->submitApplication($data);
        return $this->handleResponse($result);
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

        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('upload_document', $application)) {
            return $this->forbidden('Insufficient permission to upload admission documents');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'upload_document');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->uploadDocument($application_id, $document_type, $file);
        return $this->handleResponse($result);
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

        $application = $this->getApplicationScopeRecordByDocument((int) $document_id);
        if (!$application) {
            return $this->notFound('Document or application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('verify_document', $application)) {
            return $this->forbidden('Insufficient permission to verify admission documents');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'verify_document');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->verifyDocument($document_id, $status, $notes);
        return $this->handleResponse($result);
    }

    // 4. Interview Scheduling
    public function postScheduleInterview($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $interview_date = $data['interview_date'] ?? null;
        $interview_time = $data['interview_time'] ?? null;
        $venue = $data['venue'] ?? 'Main Office';

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('schedule_interview', $application)) {
            return $this->forbidden('Insufficient permission to schedule admission interviews');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'schedule_interview');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->scheduleInterview($application_id, $interview_date, $interview_time, $venue);
        return $this->handleResponse($result);
    }

    // 5. Interview Assessment
    public function postRecordInterviewResults($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assessment_data = $data['assessment_data'] ?? $data;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('record_interview', $application)) {
            return $this->forbidden('Insufficient permission to record interview results');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'record_interview');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        // Normalise score/result: the frontend may send `result` (pass|fail) with an optional `score`.
        // The workflow uses `score >= 70` internally to determine qualification.
        if (!isset($assessment_data['score']) || $assessment_data['score'] === '' || $assessment_data['score'] === null) {
            $result_flag = strtolower($assessment_data['result'] ?? '');
            $assessment_data['score'] = ($result_flag === 'passed') ? 70 : 0;
        } else {
            $assessment_data['score'] = (int) $assessment_data['score'];
        }

        $result = $this->api->recordInterviewResults($application_id, $assessment_data);
        return $this->handleResponse($result);
    }

    // 6. Placement Offer
    public function postGeneratePlacementOffer($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $assigned_class_id = $data['assigned_class_id'] ?? null;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('placement_offer', $application)) {
            return $this->forbidden('Insufficient permission to generate placement offers');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'placement_offer');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->generatePlacementOffer($application_id, $assigned_class_id);
        return $this->handleResponse($result);
    }

    // 7. Fee Payment
    public function postRecordFeePayment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;
        $payment_data = $data['payment_data'] ?? $data;

        if (isset($payment_data['amount_paid']) && !isset($payment_data['amount'])) {
            $payment_data['amount'] = $payment_data['amount_paid'];
        }
        if (isset($payment_data['payment_method']) && !isset($payment_data['method'])) {
            $payment_data['method'] = $payment_data['payment_method'];
        }
        if (isset($payment_data['transaction_reference']) && !isset($payment_data['reference'])) {
            $payment_data['reference'] = $payment_data['transaction_reference'];
        }

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        if (!isset($payment_data['amount']) || $payment_data['amount'] === '') {
            return $this->badRequest('amount is required');
        }
        if (empty($payment_data['method'])) {
            return $this->badRequest('payment method is required');
        }

        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('record_payment', $application)) {
            return $this->forbidden('Insufficient permission to record admission fee payments');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'record_payment');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->recordFeePayment($application_id, $payment_data);
        return $this->handleResponse($result);
    }

    // 8. Enrollment
    public function postCompleteEnrollment($id = null, $data = [], $segments = [])
    {
        $application_id = $data['application_id'] ?? $id;

        if (!$application_id) {
            return $this->badRequest('application_id is required');
        }
        $application = $this->getApplicationScopeRecord((int) $application_id);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('complete_enrollment', $application)) {
            return $this->forbidden('Insufficient permission to complete enrollment');
        }
        $actionGuard = $this->ensureApplicationActionAllowed($application, 'complete_enrollment');
        if ($actionGuard !== true) {
            return $actionGuard;
        }

        $result = $this->api->completeEnrollment($application_id);
        return $this->handleResponse($result);
    }

    public function getPolicy($id = null, $data = [], $segments = [])
    {
        return $this->success($this->policy->getPolicyPayload(), 'Admission policy retrieved');
    }

    public function getStageMatrix($id = null, $data = [], $segments = [])
    {
        if (!$this->hasAnyAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission stages');
        }

        $matrix = $this->getStageAuthorization()->getStageMatrix(
            $this->getCurrentUserRoleIds(),
            $this->getCurrentUserPermissionCodes()
        );

        $allowedTabs = [
            'documents_pending' => !empty($matrix['application']['can_view']) || !empty($matrix['document_verification']['can_view']),
            'interview_pending' => !empty($matrix['interview_scheduling']['can_view']) || !empty($matrix['interview_assessment']['can_view']),
            'placement_pending' => !empty($matrix['placement_offer']['can_view']),
            'payment_pending' => !empty($matrix['fee_payment']['can_view']),
            'enrollment_pending' => !empty($matrix['enrollment']['can_view']),
            'director_confirmation_pending' => !empty($matrix['director_confirmation']['can_view']),
        ];

        return $this->success([
            'workflow' => 'student_admission',
            'stages' => array_values($matrix),
            'allowed_tabs' => $allowedTabs,
        ], 'Admission stage matrix retrieved');
    }

    public function getPayments($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAnyAdmissionPermission('view_any')) {
            return $this->forbidden('Insufficient permission to view admission payments');
        }

        return $this->success([
            'payments' => $this->paymentService->getPaymentsForApplication((int) $applicationId),
            'total_recorded' => $this->paymentService->getTotalRecorded((int) $applicationId),
        ], 'Admission payments retrieved');
    }

    public function postConfirmEnrollment($id = null, $data = [], $segments = [])
    {
        $applicationId = $id ?: ($data['application_id'] ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }
        if (!$this->hasAnyAdmissionPermission('confirm_enrollment')) {
            return $this->forbidden('Insufficient permission to confirm enrollment');
        }

        return $this->handleResponse($this->api->confirmEnrollment((int) $applicationId, (string) ($data['notes'] ?? '')));
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

        $application = $this->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('check_class_space', $application)) {
            return $this->forbidden('Insufficient permission to check class space');
        }
        $guard = $this->ensureApplicationActionAllowed($application, 'check_class_space');
        if ($guard !== true) {
            return $guard;
        }

        $available = !empty($data['available']) && $data['available'] !== 'false';
        $notes = $data['notes'] ?? null;
        return $this->handleResponse($this->api->checkClassSpace($applicationId, (bool) $available, $notes ? (string) $notes : null));
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

        $application = $this->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('admit_student', $application)) {
            return $this->forbidden('Insufficient permission to admit student');
        }
        $guard = $this->ensureApplicationActionAllowed($application, 'admit_student');
        if ($guard !== true) {
            return $guard;
        }

        return $this->handleResponse($this->api->admitStudent($applicationId));
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

        $application = $this->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('generate_id_card', $application)) {
            return $this->forbidden('Insufficient permission to generate student ID card');
        }
        $guard = $this->ensureApplicationActionAllowed($application, 'generate_id_card');
        if ($guard !== true) {
            return $guard;
        }

        return $this->handleResponse($this->api->generateStudentIdCard($applicationId));
    }

    /**
     * POST /api/admission/final-approval/{id} - Final approval before enrollment.
     */
    public function postFinalApproval($id = null, $data = [], $segments = [])
    {
        $applicationId = (int) ($id ?? $segments[0] ?? null);
        if (!$applicationId) {
            return $this->badRequest('Application ID is required');
        }

        $application = $this->getApplicationScopeRecord($applicationId);
        if (!$application) {
            return $this->notFound('Application not found');
        }
        if (!$this->canViewApplicationRecord($application)) {
            return $this->forbidden('You do not have access to this admission application');
        }
        if (!$this->canProcessAdmissionActionForApplication('final_approval', $application)) {
            return $this->forbidden('Insufficient permission to grant final approval');
        }
        $guard = $this->ensureApplicationActionAllowed($application, 'final_approval');
        if ($guard !== true) {
            return $guard;
        }

        return $this->handleResponse($this->api->finalApproval($applicationId));
    }

    /**
     * GET /api/admission/check-class-space/{id} - Check class space availability for an application
     */
    public function getCheckClassSpace($id = null, $data = [], $segments = [])
    {
        try {
            $applicationId = (int) ($id ?? $segments[0] ?? null);
            if (!$applicationId) {
                return $this->badRequest('Application ID is required');
            }

            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to check class space');
            }

            $db = $this->db->getConnection();
            $userId = $_SERVER['auth_user']['id'] ?? null;

            // Call the stored procedure
            $stmt = $db->prepare("CALL sp_check_class_space_availability(?, ?)");
            $stmt->execute([$applicationId, $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->handleResponse([
                'success' => true,
                'space_check' => $result
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to check class space: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admission/advance-workflow-stage - Advance workflow stage with proper validation
     */
    public function postAdvanceWorkflowStage($id = null, $data = [], $segments = [])
    {
        try {
            $applicationId = (int) ($data['application_id'] ?? null);
            $toStage = $data['to_stage'] ?? null;
            $action = $data['action'] ?? 'workflow_advance';
            $notes = $data['notes'] ?? null;
            $workflowUpdates = $data['workflow_updates'] ?? null;

            if (!$applicationId || !$toStage) {
                return $this->badRequest('Application ID and target stage are required');
            }

            // Validate workflow transition
            if (!$this->isValidWorkflowTransition($applicationId, $toStage)) {
                return $this->badRequest('Invalid workflow transition for current stage');
            }

            $db = $this->db->getConnection();
            $userId = $_SERVER['auth_user']['id'] ?? null;

            // Call the stored procedure for workflow advancement
            $stmt = $db->prepare("CALL sp_advance_admission_workflow_stage(?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $applicationId,
                $toStage,
                $action,
                $userId,
                $notes,
                $workflowUpdates
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $this->handleResponse([
                'success' => true,
                'message' => 'Workflow stage advanced successfully',
                'workflow_instance_id' => $result['workflow_instance_id'] ?? null,
                'from_stage' => $result['from_stage'] ?? null,
                'to_stage' => $result['to_stage'] ?? null
            ]);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to advance workflow stage: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/admission/create-provisional-student/{id} - Create provisional student record
     */
    public function postCreateProvisionalStudent($id = null, $data = [], $segments = [])
    {
        try {
            $applicationId = (int) ($id ?? $segments[0] ?? null);
            if (!$applicationId) {
                return $this->badRequest('Application ID is required');
            }

            if (!$this->hasAnyAdmissionPermission('submit_application')) {
                return $this->forbidden('Insufficient permission to create student record');
            }

            $result = $this->api->createProvisionalStudent($applicationId);

            // NOTE: createProvisionalStudent() already advances the workflow
            // instance to fees_payment and writes the student linkage, so no
            // second sp_advance_admission_workflow_stage call is needed here.

            return $this->handleResponse($result);
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create provisional student: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to validate workflow transitions
     */
    private function isValidWorkflowTransition($applicationId, $toStage)
    {
        $db = $this->db->getConnection();
        
        // Get current stage
        $stmt = $db->prepare("
            SELECT current_stage 
            FROM workflow_instances 
            WHERE reference_type = 'admission_application' AND reference_id = ?
        ");
        $stmt->execute([$applicationId]);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$current) {
            // No workflow instance exists, allow creation
            return true;
        }
        
        $currentStage = $current['current_stage'];
        
        // Define valid transitions
        $validTransitions = [
            'application_received' => ['application_review', 'rejected'],
            'application_review' => ['documents_upload', 'rejected'],
            'documents_upload' => ['documents_verification', 'rejected'],
            'documents_verification' => ['class_space_check', 'documents_upload', 'rejected'],
            'class_space_check' => ['interview_scheduling', 'rejected'],
            'interview_scheduling' => ['interview_results', 'cancelled'],
            'interview_results' => ['admission_decision', 'rejected'],
            'admission_decision' => ['provisional_student_creation', 'rejected'],
            'provisional_student_creation' => ['fees_payment', 'rejected'],
            'fees_payment' => ['student_id_generation', 'cancelled'],
            'student_id_generation' => ['final_approval', 'rejected'],
            'final_approval' => ['enrollment', 'rejected'],
            'enrollment' => ['enrolled'],
            'rejected' => [],
            'enrolled' => []
        ];
        
        return in_array($toStage, $validTransitions[$currentStage] ?? []);
    }

    /**
     * GET /api/admission/queues - Get workflow queues by stage for role-based views
     * Returns counts and lists of applications at each stage
     */
    public function getQueues($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view admissions queues');
            }

            $db = $this->db;
            $scopeFilter = $this->buildScopeFilter('aa', 'wi');
            $stageMatrix = $this->getStageAuthorization()->getStageMatrix(
                $this->getCurrentUserRoleIds(),
                $this->getCurrentUserPermissionCodes()
            );
            $hasAdmissionOversight = $this->hasAdmissionRouteAccess()
                && $this->userHasAny([], [3, 5, 6], ['Director', 'Headteacher', 'Deputy Head - Academic']);
            $canViewStage = static fn (array $stages): bool => array_reduce(
                $stages,
                static fn (bool $carry, string $stage): bool => $carry || !empty($stageMatrix[$stage]['can_view']) || $hasAdmissionOversight,
                false
            );
            $canViewReview = $canViewStage(['application_received', 'application_review']);
            $canViewDocuments = $canViewStage(['documents_upload', 'documents_verification']);
            $canViewSpace = $canViewStage(['class_space_check']);
            $canViewInterview = $canViewStage(['interview_scheduling', 'interview_results']);
            $canViewDecision = $canViewStage(['admission_decision', 'provisional_student_creation']);
            $canViewPayment = $canViewStage(['fees_payment']);
            $canViewId = $canViewStage(['student_id_generation']);
            $canViewFinalApproval = $canViewStage(['final_approval']);
            $canViewEnrollment = $canViewStage(['enrollment']);
            $canReview = $this->canProcessAdmissionActionForStage('review_application', 'application_review')
                || $this->canProcessAdmissionActionForStage('review_application', 'application_received');
            $canUploadDocuments = $this->canProcessAdmissionActionForStage('upload_document', 'application_review')
                || $this->canProcessAdmissionActionForStage('upload_document', 'documents_upload');
            $canVerifyDocuments = $this->canProcessAdmissionActionForStage('verify_document', 'documents_upload')
                || $this->canProcessAdmissionActionForStage('verify_document', 'documents_verification');
            $canCheckSpace = $this->canProcessAdmissionActionForStage('check_class_space', 'documents_verification')
                || $this->canProcessAdmissionActionForStage('check_class_space', 'class_space_check');
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'class_space_check')
                || $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling');
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_scheduling')
                || $this->canProcessAdmissionActionForStage('record_interview', 'interview_results');
            $canAdmit = $this->canProcessAdmissionActionForStage('admit_student', 'interview_results')
                || $this->canProcessAdmissionActionForStage('admit_student', 'admission_decision')
                || $this->canProcessAdmissionActionForStage('admit_student', 'class_space_check');
            $canCreateProvisional = $this->canProcessAdmissionActionForStage('create_provisional_student', 'provisional_student_creation');
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fees_payment')
                || $this->canProcessAdmissionActionForStage('record_payment', 'student_id_generation');
            $canGenerateId = $this->canProcessAdmissionActionForStage('generate_id_card', 'student_id_generation')
                || $this->canProcessAdmissionActionForStage('generate_id_card', 'final_approval');
            $canFinalApproval = $this->canProcessAdmissionActionForStage('final_approval', 'final_approval')
                || $this->canProcessAdmissionActionForStage('final_approval', 'enrollment');
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'enrollment');

            // NEW-key workflow buckets, keyed by current_stage.
            $queues = [
                'review_pending'        => [],
                'documents_pending'     => [],
                'space_check_pending'   => [],
                'interview_pending'      => [],
                'decision_pending'       => [],
                'payment_pending'        => [],
                'id_generation_pending'  => [],
                'final_approval_pending' => [],
                'enrollment_pending'     => [],
                'completed'              => [],
            ];

            $baseSelect = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.application_source, aa.updated_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json,
                           aa.workflow_data_json,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id";

            // Review pending (application_received / application_review)
            if ($canViewReview || $canReview) {
                $sql = "$baseSelect
                    WHERE wi.current_stage IN ('application_received', 'application_review')
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['review_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Documents Pending (documents_upload / documents_verification)
            if ($canViewDocuments || $canUploadDocuments || $canVerifyDocuments) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.application_source, aa.updated_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json,
                           aa.workflow_data_json,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage IN ('documents_upload', 'documents_verification')
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['documents_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Class space check pending (class_space_check)
            if ($canViewSpace || $canCheckSpace) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.application_source, aa.updated_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json,
                           aa.workflow_data_json,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'rejected') as rejected_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'class_space_check'
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['space_check_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Interview Pending (interview_scheduling / interview_results)
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

                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE ({$interviewStageSql})
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['interview_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Admission decision pending (admission_decision / provisional_student_creation)
            if ($canViewDecision || $canAdmit || $canCreateProvisional) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage IN ('admission_decision', 'provisional_student_creation')
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['decision_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            if ($canViewPayment || $canRecordPayment) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.total_fees')) as total_fees,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.assigned_class_id')) as assigned_class_id
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'fees_payment'
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['payment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Student ID generation pending (student_id_generation)
            if ($canViewId || $canGenerateId) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'student_id_generation'
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['id_generation_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Final approval pending (final_approval)
            if ($canViewFinalApproval || $canFinalApproval) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'final_approval'
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['final_approval_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Enrollment Pending (enrollment)
            if ($canViewEnrollment || $canCompleteEnrollment) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'enrollment'
                      AND aa.status NOT IN ('cancelled', 'enrolled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['enrollment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Completed (enrolled) — terminal, shown for confirmation/oversight
            {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at, aa.enrolled_student_id, aa.application_source,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'enrolled'
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['completed'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Get summary counts
            $summary = [
                'review_pending'        => count($queues['review_pending']),
                'documents_pending'     => count($queues['documents_pending']),
                'space_check_pending'   => count($queues['space_check_pending']),
                'interview_pending'      => count($queues['interview_pending']),
                'decision_pending'       => count($queues['decision_pending']),
                'payment_pending'        => count($queues['payment_pending']),
                'id_generation_pending'  => count($queues['id_generation_pending']),
                'final_approval_pending' => count($queues['final_approval_pending']),
                'enrollment_pending'     => count($queues['enrollment_pending']),
                'completed'              => count($queues['completed']),
                'total_pending' => count($queues['review_pending']) + count($queues['documents_pending'])
                    + count($queues['space_check_pending']) + count($queues['interview_pending'])
                    + count($queues['decision_pending']) + count($queues['payment_pending'])
                    + count($queues['id_generation_pending']) + count($queues['final_approval_pending'])
                    + count($queues['enrollment_pending'])
            ];

            return $this->success([
                'queues' => $queues,
                'summary' => $summary,
                'allowed_tabs' => [
                    'review_pending'        => $canReview,
                    'documents_pending'     => ($canUploadDocuments || $canVerifyDocuments),
                    'space_check_pending'   => $canCheckSpace,
                    'interview_pending'      => ($canScheduleInterview || $canRecordInterview),
                    'decision_pending'       => ($canAdmit || $canCreateProvisional),
                    'payment_pending'        => $canRecordPayment,
                    'id_generation_pending'  => $canGenerateId,
                    'final_approval_pending' => $canFinalApproval,
                    'enrollment_pending'     => $canCompleteEnrollment,
                    'completed'              => true,
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Workflow queues retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch workflow queues: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admission/application/{id} - Get single application details with full workflow status
     */
    public function getApplication($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view admission applications');
            }

            if (!$id) {
                return $this->badRequest('Application ID is required');
            }

            $db = $this->db;
            $connection = $db->getConnection();

            // Get application details
            $sql = "SELECT aa.*, 
                           p.first_name as parent_first_name, p.last_name as parent_last_name, 
                           p.phone_1, p.phone_2, p.email as parent_email,
                           wi.id as workflow_instance_id, wi.current_stage, wi.status as workflow_status, wi.data_json,
                           wi.started_by, wi.started_at
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.id = ?
                    ORDER BY wi.id DESC
                    LIMIT 1";
            $stmt = $connection->prepare($sql);
            $stmt->execute([$id]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$application) {
                return $this->notFound('Application not found');
            }

            if (!$this->canViewApplicationRecord($application)) {
                return $this->forbidden('You do not have access to this admission application');
            }

            // Get documents
            $sql = "SELECT ad.*,
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
                    ORDER BY ad.is_mandatory DESC, ad.document_type";
            $stmt = $connection->prepare($sql);
            $stmt->execute([$id]);
            $documents = $this->normalizeAdmissionDocuments($stmt->fetchAll(\PDO::FETCH_ASSOC));

            // Parse workflow data. Identity fields in workflow_instances.data_json are denormalized
            // and can become stale; the admission_applications row is the source of truth.
            $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
            $workflowData = $this->syncWorkflowIdentityData($workflowData, $application);

            // Determine what actions are available based on current stage
            $availableActions = $this->getAvailableActions($application['current_stage'], $application['status']);
            $stageMeta = $this->getCurrentStageMetadata($application['current_stage']);
            $currentStageCode = $this->normalizeStageCode($application['current_stage']) ?? $this->inferStageFromApplication($application);
            $currentStageRequiredRole = $stageMeta['required_role'] ?? null;

            return $this->success([
                'application' => $application,
                'documents' => $documents,
                'workflow_data' => $workflowData,
                'available_actions' => $availableActions,
                'stage_metadata' => [
                    'current_stage' => $currentStageCode,
                    'display_name' => $stageMeta['name'] ?? null,
                    'required_role' => $currentStageRequiredRole,
                    'user_matches_required_role' => $this->userMatchesRequiredRole($currentStageRequiredRole),
                    'allowed_transitions' => $this->getAllowedTransitionsForStage($currentStageCode),
                ]
            ], 'Application details retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch application: ' . $e->getMessage());
        }
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

    /**
     * Get available actions based on workflow stage
     */
    private function getAvailableActions($currentStage, $status)
    {
        $actions = [];

        if (!$this->hasAnyAdmissionPermission('view_any')) {
            return $actions;
        }

        if ($status === 'cancelled') {
            return [];
        }
        if ($status === 'enrolled') {
            $normalizedStage = $this->normalizeStageCode($currentStage);
            if ($normalizedStage !== 'director_confirmation') {
                return [];
            }
        }

        $normalizedStage = $this->normalizeStageCode($currentStage) ?? $this->inferStageFromApplication(['status' => $status]);
        $requiredRole = $this->getStageRequiredRole($normalizedStage);
        if (
            !$this->userMatchesRequiredRole($requiredRole)
            && !$this->canBypassAdmissionStageRole()
        ) {
            return [];
        }

        switch ($normalizedStage) {
            case 'application_received':
            case 'application_review':
                if ($this->canProcessAdmissionActionForStage('review_application', $normalizedStage)) {
                    $actions[] = 'review-application';
                }
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage)) {
                    $actions[] = 'upload-documents';
                }
                break;
            case 'documents_upload':
            case 'documents_verification':
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage)) {
                    $actions[] = 'upload-documents';
                }
                if ($this->canProcessAdmissionActionForStage('verify_document', $normalizedStage)) {
                    $actions[] = 'verify-documents';
                }
                break;
            case 'class_space_check':
                if ($this->canProcessAdmissionActionForStage('check_class_space', $normalizedStage)) {
                    $actions[] = 'check-class-space';
                }
                break;
            case 'interview_scheduling':
                if ($this->canProcessAdmissionActionForStage('schedule_interview', $normalizedStage)) {
                    $actions = ['schedule-interview'];
                }
                break;
            case 'interview_results':
                if ($this->canProcessAdmissionActionForStage('record_interview', $normalizedStage)) {
                    $actions = ['record-interview'];
                }
                if ($this->canProcessAdmissionActionForStage('admit_student', $normalizedStage)) {
                    $actions[] = 'admit-student';
                }
                break;
            case 'admission_decision':
                if ($this->canProcessAdmissionActionForStage('admit_student', $normalizedStage)) {
                    $actions = ['admit-student'];
                }
                break;
            case 'provisional_student_creation':
                if ($this->canProcessAdmissionActionForStage('create_provisional_student', $normalizedStage)) {
                    $actions = ['create-provisional-student'];
                }
                break;
            case 'fees_payment':
            case 'student_id_generation':
                if ($this->canProcessAdmissionActionForStage('record_payment', $normalizedStage)) {
                    $actions[] = 'record-payment';
                }
                if ($this->canProcessAdmissionActionForStage('generate_id_card', $normalizedStage)) {
                    $actions[] = 'generate-id-card';
                }
                break;
            case 'final_approval':
                if ($this->canProcessAdmissionActionForStage('final_approval', $normalizedStage)) {
                    $actions = ['final-approval'];
                }
                break;
            case 'enrollment':
                if ($this->canProcessAdmissionActionForStage('complete_enrollment', $normalizedStage)) {
                    $actions = ['complete-enrollment'];
                }
                break;
            case 'enrolled':
            case 'director_confirmation':
                if ($this->canProcessAdmissionActionForStage('confirm_enrollment', $normalizedStage)) {
                    $actions = ['confirm-enrollment'];
                }
                break;
            default:
                $actions = [];
        }

        return $actions;
    }

    /**
     * GET /api/admission/placement-classes - Get active classes for placement offers
     */
    public function getPlacementClasses($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view placement classes');
            }

            $sql = "SELECT c.id,
                           c.name,
                           c.capacity,
                           COALESCE(SUM(CASE WHEN cs.status = 'active' THEN cs.current_students ELSE 0 END), 0) AS student_count
                    FROM classes c
                    LEFT JOIN class_streams cs ON cs.class_id = c.id
                    WHERE c.status = 'active'
                    GROUP BY c.id, c.name, c.capacity
                    ORDER BY c.name ASC";

            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            $classes = array_map(static function (array $row): array {
                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => $row['name'] ?? '',
                    'capacity' => isset($row['capacity']) ? (int) $row['capacity'] : null,
                    'student_count' => (int) ($row['student_count'] ?? 0),
                ];
            }, $rows ?: []);

            return $this->success(['classes' => $classes], 'Placement classes retrieved');
        } catch (\Exception $e) {
            return $this->error('Failed to load placement classes: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admission/stats - Get admission statistics for dashboards
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view admission statistics');
            }

            $db = $this->db;
            $scopeFilter = $this->buildScopeFilter('aa', 'wi');

            $stats = [];

            // Total applications this year
            $sql = "SELECT COUNT(*) as total
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.academic_year = YEAR(CURDATE())
                    {$scopeFilter}";
            $stmt = $db->query($sql);
            $stats['total_applications'] = (int) $stmt->fetchColumn();

            // By status
            $sql = "SELECT aa.status, COUNT(*) as count
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.academic_year = YEAR(CURDATE())
                    {$scopeFilter}
                    GROUP BY aa.status";
            $stmt = $db->query($sql);
            $stats['by_status'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // By grade
            $sql = "SELECT grade_applying_for, COUNT(*) as count 
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.academic_year = YEAR(CURDATE())
                    {$scopeFilter}
                    GROUP BY grade_applying_for";
            $stmt = $db->query($sql);
            $stats['by_grade'] = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // This week
            $sql = "SELECT COUNT(*)
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    {$scopeFilter}";
            $stats['this_week'] = (int) $db->query($sql)->fetchColumn();

            // Enrolled (completed)
            $stats['enrolled'] = (int) ($stats['by_status']['enrolled'] ?? 0);

            // Pending (not enrolled or cancelled)
            $stats['pending'] = $stats['total_applications'] - $stats['enrolled'] - (int) ($stats['by_status']['cancelled'] ?? 0);

            return $this->success($stats, 'Admission statistics retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch admission stats: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admission/notifications - Get role-specific admission notifications for dashboards
     * Returns pending work items based on the user's role
     */
    public function getNotifications($id = null, $data = [], $segments = [])
    {
        try {
            if (!$this->hasAnyAdmissionPermission('view_any')) {
                return $this->forbidden('Insufficient permission to view admission notifications');
            }

            $db = $this->db;

            $notifications = [
                'pending_tasks' => [],
                'total_count' => 0,
                'role' => $this->getUserRole()
            ];

            $scopeFilter = $this->buildScopeFilter('aa', 'wi');
            $canUploadDocuments = $this->canProcessAdmissionActionForStage('upload_document', 'application')
                || $this->canProcessAdmissionActionForStage('upload_document', 'document_verification');
            $canVerifyDocuments = $this->canProcessAdmissionActionForStage('verify_document', 'document_verification');
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling');
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_assessment');
            $canPlacement = $this->canProcessAdmissionActionForStage('placement_offer', 'placement_offer');
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fee_payment');
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'enrollment');

            // Documents Pending
            if ($canUploadDocuments || $canVerifyDocuments) {
                $sql = "SELECT COUNT(*)
                        FROM admission_applications aa
                        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                        WHERE aa.status IN ('submitted', 'documents_pending'){$scopeFilter}";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'documents_pending',
                        'label' => $canVerifyDocuments ? 'Documents to Verify' : 'Documents to Upload',
                        'count' => $count,
                        'icon' => 'bi-file-earmark-text',
                        'color' => 'warning',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=documents_pending')
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Interview Pending
            if ($canScheduleInterview || $canRecordInterview) {
                $interviewStageFilters = [];
                if ($canScheduleInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_scheduling'";
                }
                if ($canRecordInterview) {
                    $interviewStageFilters[] = "wi.current_stage = 'interview_assessment'";
                }

                $interviewStageSql = empty($interviewStageFilters)
                    ? '1 = 0'
                    : implode(' OR ', $interviewStageFilters);

                $sql = "SELECT COUNT(*)
                        FROM admission_applications aa
                        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                        WHERE ({$interviewStageSql})
                          AND aa.status NOT IN ('cancelled', 'enrolled'){$scopeFilter}";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'interview_pending',
                        'label' => 'Interviews Pending',
                        'count' => $count,
                        'icon' => 'bi-calendar-event',
                        'color' => 'info',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=interview_pending')
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Placement Pending
            if ($canPlacement) {
                $sql = "SELECT COUNT(*)
                        FROM admission_applications aa
                        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                        WHERE wi.current_stage = 'placement_offer'
                          AND aa.status NOT IN ('placement_offered', 'fees_pending', 'enrolled', 'cancelled')
                        {$scopeFilter}";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'placement_pending',
                        'label' => 'Placements to Generate',
                        'count' => $count,
                        'icon' => 'bi-check-circle',
                        'color' => 'primary',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=placement_pending')
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Payment Pending
            if ($canRecordPayment) {
                $sql = "SELECT COUNT(*)
                        FROM admission_applications aa
                        LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                        WHERE aa.status IN ('placement_offered', 'fees_pending'){$scopeFilter}";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'payment_pending',
                        'label' => 'Payments to Record',
                        'count' => $count,
                        'icon' => 'bi-cash-stack',
                        'color' => 'success',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=payment_pending')
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            // Enrollment Pending
            if ($canCompleteEnrollment) {
                $sql = "SELECT COUNT(*) FROM admission_applications aa
                        JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'enrollment' AND aa.status != 'enrolled'{$scopeFilter}";
                $count = (int) $db->query($sql)->fetchColumn();
                if ($count > 0) {
                    $notifications['pending_tasks'][] = [
                        'type' => 'enrollment_pending',
                        'label' => 'Enrollments to Complete',
                        'count' => $count,
                        'icon' => 'bi-person-check',
                        'color' => 'dark',
                        'link' => $this->buildAppUrl('/home.php?route=manage_students_admissions&tab=enrollment_pending')
                    ];
                    $notifications['total_count'] += $count;
                }
            }

            return $this->success($notifications, 'Notifications retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch notifications: ' . $e->getMessage());
        }
    }

    // Helper for consistent API response
    private function handleResponse($result)
    {
        $response = null;
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    $response = $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $response = $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            } elseif (isset($result['status'])) {
                if ($result['status'] === 'success') {
                    $response = $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $response = $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            } else {
                $response = $this->success($result);
            }
        } else {
            $response = $this->success($result);
        }
        return $response;
    }

    /**
     * Build a fully-qualified (absolute) in-app URL usable in any deployment.
     *
     * Derived from the live request (scheme + HTTP_HOST + mount prefix from
     * SCRIPT_NAME), never from a hardcoded '/Kingsway'. Produces a portable
     * absolute URL (e.g. https://kingsway.ac.ke/home.php?route=...) so links
     * work even outside the app shell (email, cross-origin, deep links).
     * Mirrors AuthAPI::generateResetLink().
     *
     * @param string $path Path starting with '/' (e.g. '/home.php?route=...')
     * @return string Absolute URL (scheme://host[/base]/path)
     */
    private function buildAppUrl(string $path): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . rtrim($appBase, '/') . '/' . ltrim($path, '/');
    }

    private function hasAnyAdmissionPermission(string $group): bool
    {
        $permissionCodes = self::PERMISSIONS[$group] ?? [];
        $hasPermission = !empty($permissionCodes) && $this->userHasAny($permissionCodes);

        if ($hasPermission) {
            return true;
        }

        if ($this->admissionRoleCanProcessGroup($group)) {
            return true;
        }

        if ($group === 'view_any') {
            return $this->hasAdmissionRouteAccess();
        }

        return false;
    }

    private function admissionRoleCanProcessGroup(string $group): bool
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

        if (in_array($group, $schoolAdminGroups, true)
            && $this->userHasAny([], [4], ['School Administrator'])
        ) {
            return true;
        }

        if ($group === 'verify_document'
            && $this->userHasAny([], [5, 6], ['Headteacher', 'Deputy Head - Academic'])
        ) {
            return true;
        }

        return false;
    }

    private function attachQueueActions(array $records): array
    {
        foreach ($records as &$record) {
            $currentStage = $record['current_stage'] ?? null;
            $status = $record['status'] ?? null;
            if (($this->normalizeStageCode($currentStage) === 'director_confirmation' || $status === 'enrolled')
                && empty($record['director_confirmed_at'])
                && $this->canProcessAdmissionActionForStage('confirm_enrollment', 'director_confirmation')
            ) {
                $record['available_actions'] = ['confirm-enrollment'];
                continue;
            }

            $record['available_actions'] = $this->getAvailableActions($currentStage, $status);
        }
        unset($record);

        return $records;
    }

    private function canProcessAdmissionActionForApplication(string $actionGroup, array $application): bool
    {
        $hasActionPermission = $this->hasAnyAdmissionPermission($actionGroup);
        if (!$hasActionPermission) {
            return false;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return false;
        }

        return $this->canProcessAdmissionActionForStage($actionGroup, $currentStage);
    }

    private function canProcessAdmissionActionForStage(string $actionGroup, ?string $stageCode): bool
    {
        $hasActionPermission = $this->hasAnyAdmissionPermission($actionGroup);
        if (!$hasActionPermission) {
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

        if ($this->canActViaStagePermissions($actionGroup, $stageCode)) {
            return true;
        }

        $requiredRole = $this->getStageRequiredRole($stageCode);
        if (!$requiredRole) {
            return $hasActionPermission || $this->hasAdmissionRouteAccess();
        }

        if ($this->userMatchesRequiredRole($requiredRole)) {
            return true;
        }

        if ($hasActionPermission && $this->canBypassAdmissionStageRole()) {
            return true;
        }

        return false;
    }

    private function canBypassAdmissionStageRole(): bool
    {
        return $this->userHasAny(['*']);
    }

    private function ensureApplicationActionAllowed(array $application, string $actionGroup)
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        if (in_array($status, ['cancelled', 'enrolled'], true)) {
            return $this->conflict('This application can no longer be modified in its current status.');
        }

        $expectedStages = self::ACTION_STAGE_RULES[$actionGroup] ?? [];
        if (empty($expectedStages)) {
            return true;
        }

        $currentStage = $this->normalizeStageCode($application['current_stage'] ?? null)
            ?? $this->inferStageFromApplication($application);

        if (!$currentStage) {
            return $this->conflict('Workflow stage is not available for this application.');
        }

        $expectedNormalized = array_map([$this, 'normalizeStageCode'], $expectedStages);
        if (!in_array($currentStage, $expectedNormalized, true)) {
            $stageMeta = $this->getCurrentStageMetadata($currentStage);
            $stageLabel = $stageMeta['name'] ?? str_replace('_', ' ', $currentStage);
            return $this->conflict("Action is not allowed at workflow stage '{$stageLabel}'.");
        }

        return true;
    }

    private function buildScopeFilter(string $applicationAlias = 'aa', string $workflowAlias = 'wi'): string
    {
        if (
            $this->hasAnyAdmissionPermission('view_all')
            || $this->userHasPermission('admission_view')
            || $this->hasAdmissionRouteAccess()
        ) {
            return '';
        }

        if (!$this->hasAnyAdmissionPermission('view_own')) {
            return ' AND 1 = 0 ';
        }

        $userId = (int) $this->getUserId();
        $parentScopeSql = $this->buildParentScopeSql($applicationAlias);
        return " AND (
            CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_to')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.assigned_user_id')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.created_by')) AS UNSIGNED) = {$userId}
            OR CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE({$workflowAlias}.data_json, '{}'), '$.submitted_by')) AS UNSIGNED) = {$userId}
            OR {$workflowAlias}.started_by = {$userId}
            {$parentScopeSql}
        ) ";
    }

    private function getAdmissionRouteNames(): array
    {
        return [
            'manage_students_admissions',
            'admissions/director_admissions',
            'admissions/enrollment_confirmations',
        ];
    }

    private function hasAdmissionRouteAccess(): bool
    {
        if ($this->resolvedAdmissionRouteAccess) {
            return $this->admissionRouteAccess;
        }

        $this->resolvedAdmissionRouteAccess = true;
        $this->admissionRouteAccess = false;

        $userId = (int) $this->getUserId();
        $roleIds = $this->getUserRoleIds();

        if ($userId <= 0 && empty($roleIds)) {
            return false;
        }

        try {
            if ($userId > 0) {
                $routeNames = $this->getAdmissionRouteNames();
                $routePlaceholders = implode(',', array_fill(0, count($routeNames), '?'));
                $userOverrideSql = "SELECT ur.is_allowed
                    FROM user_routes ur
                    JOIN routes r ON r.id = ur.route_id
                    WHERE ur.user_id = ?
                      AND r.name IN ({$routePlaceholders})
                      AND r.is_active = 1
                      AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                    ORDER BY ur.is_allowed DESC
                    LIMIT 1";
                $stmt = $this->db->getConnection()->prepare($userOverrideSql);
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

            $routeNames = $this->getAdmissionRouteNames();
            $routePlaceholders = implode(',', array_fill(0, count($routeNames), '?'));
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $sql = "SELECT 1
                FROM role_routes rr
                JOIN routes r ON r.id = rr.route_id
                WHERE rr.is_allowed = 1
                  AND r.is_active = 1
                  AND r.name IN ({$routePlaceholders})
                  AND rr.role_id IN ({$placeholders})
                LIMIT 1";
            $params = array_merge($routeNames, array_map('intval', $roleIds));
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute($params);
            $this->admissionRouteAccess = (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->admissionRouteAccess = false;
        }

        return $this->admissionRouteAccess;
    }

    private function canViewApplicationRecord(array $application): bool
    {
        if (
            $this->hasAnyAdmissionPermission('view_all')
            || $this->userHasPermission('admission_view')
            || $this->hasAdmissionRouteAccess()
        ) {
            return true;
        }

        $applicationParentId = (int) ($application['parent_id'] ?? 0);

        if ($this->isParentLinkedToApplication($applicationParentId)) {
            return true;
        }

        if (!$this->hasAnyAdmissionPermission('view_own')) {
            return false;
        }

        $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];
        $userId = (int) $this->getUserId();
        $candidateOwnerIds = [
            (int) ($workflowData['assigned_to'] ?? 0),
            (int) ($workflowData['assigned_user_id'] ?? 0),
            (int) ($workflowData['created_by'] ?? 0),
            (int) ($workflowData['submitted_by'] ?? 0),
            (int) ($application['started_by'] ?? 0),
        ];

        return in_array($userId, $candidateOwnerIds, true);
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
            return 'application_received';
        }

        $legacyMap = [
            'application' => 'application_review',
            'document_verification' => 'documents_verification',
            'class_capacity_check' => 'class_space_check',
            'interview_assessment' => 'interview_results',
            'placement_offer' => 'admission_decision',
            'fee_payment' => 'fees_payment',
            'enrollment_confirmation' => 'enrollment',
            'director_confirmation' => 'enrolled',
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
                return 'application_review';
            case 'documents_pending':
                return 'documents_verification';
            case 'documents_verified':
                return $this->policy->requiresInterview((string) ($application['grade_applying_for'] ?? ''))
                    ? 'interview_scheduling'
                    : 'admission_decision';
            case 'placement_offered':
            case 'fees_pending':
                return 'fees_payment';
            case 'enrolled':
                return 'enrolled';
            default:
                return null;
        }
    }

    private function canActViaStagePermissions(string $actionGroup, string $stageCode): bool
    {
        $permissionCandidates = self::PERMISSIONS[$actionGroup] ?? [];
        if (empty($permissionCandidates)) {
            return false;
        }

        return $this->getStageAuthorization()->canAct(
            $stageCode,
            $permissionCandidates,
            $this->getCurrentUserRoleIds(),
            $this->getCurrentUserPermissionCodes()
        );
    }

    private function getStageAuthorization(): AdmissionStageAuthorization
    {
        if ($this->stageAuthorization === null) {
            $this->stageAuthorization = new AdmissionStageAuthorization(
                $this->db->getConnection(),
                $this->getStudentAdmissionWorkflowId()
            );
        }

        return $this->stageAuthorization;
    }

    private function getStudentAdmissionWorkflowId(): int
    {
        $stmt = $this->db->getConnection()->prepare("SELECT id FROM workflow_definitions WHERE code = 'student_admission' LIMIT 1");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
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

    private function getWorkflowStageConfig(): array
    {
        if ($this->resolvedWorkflowStages) {
            return $this->workflowStageConfig;
        }

        $this->resolvedWorkflowStages = true;
        $this->workflowStageConfig = [];

        try {
            $sql = "SELECT ws.code, ws.name, ws.required_role, ws.allowed_transitions, ws.sequence
                    FROM workflow_stages ws
                    JOIN workflow_definitions wd ON wd.id = ws.workflow_id
                    WHERE wd.code = 'student_admission'
                      AND ws.is_active = 1
                    ORDER BY ws.sequence ASC";

            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
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
        } catch (\Exception $e) {
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

    private function normalizeRoleAlias(?string $roleName): ?string
    {
        if ($roleName === null) {
            return null;
        }

        $normalized = strtolower(trim($roleName));
        $normalized = preg_replace('/[^a-z0-9]/', '', $normalized);
        return $normalized !== '' ? $normalized : null;
    }

    private function getAdmissionsRouteRoleAliases(): array
    {
        if ($this->resolvedAdmissionsRouteRoleAliases) {
            return $this->admissionsRouteRoleAliases;
        }

        $this->resolvedAdmissionsRouteRoleAliases = true;
        $this->admissionsRouteRoleAliases = [];

        try {
            $sql = "SELECT DISTINCT rl.name
                    FROM role_routes rr
                    JOIN routes rt ON rt.id = rr.route_id
                    JOIN roles rl ON rl.id = rr.role_id
                    WHERE rr.is_allowed = 1
                      AND rt.is_active = 1
                      AND rt.name = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
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
        } catch (\Exception $e) {
            $this->admissionsRouteRoleAliases = [];
        }

        return $this->admissionsRouteRoleAliases;
    }

    private function userMatchesRequiredRole(?string $requiredRole): bool
    {
        if (!$requiredRole) {
            return true;
        }

        $required = $this->normalizeRoleAlias($requiredRole);
        if (!$required) {
            return true;
        }

        $roleNames = $this->getUserRoleNames();
        foreach ($roleNames as $roleName) {
            if ($this->normalizeRoleAlias($roleName) === $required) {
                return true;
            }
        }

        // Fallback to permission-based capability checks where workflow role aliases
        // don't have a strict role-name equivalent in the roles table.
        if ($required === 'parent') {
            return $this->getCurrentUserParentId() !== null;
        }

        if ($required === 'registrar') {
            $admissionsRoleAliases = $this->getAdmissionsRouteRoleAliases();
            if (empty($admissionsRoleAliases)) {
                return false;
            }

            foreach ($roleNames as $roleName) {
                $alias = $this->normalizeRoleAlias($roleName);
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
            foreach ($roleNames as $roleName) {
                $alias = $this->normalizeRoleAlias($roleName);
                if (in_array($alias, ['headteacher', 'headmaster', 'headmistress'], true)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    private function getApplicationScopeRecord(int $applicationId): ?array
    {
        try {
            $sql = "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.id = ?
                    ORDER BY wi.id DESC
                    LIMIT 1";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$applicationId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $application ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getApplicationScopeRecordByDocument(int $documentId): ?array
    {
        try {
            $sql = "SELECT aa.*, wi.data_json, wi.current_stage, wi.started_by
                    FROM admission_documents ad
                    JOIN admission_applications aa ON aa.id = ad.application_id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE ad.id = ?
                    ORDER BY wi.id DESC
                    LIMIT 1";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$documentId]);
            $application = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $application ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buildParentScopeSql(string $applicationAlias): string
    {
        $parentId = $this->getCurrentUserParentId();
        if (!$parentId) {
            return '';
        }

        return " OR {$applicationAlias}.parent_id = {$parentId}";
    }

    private function isParentLinkedToApplication(int $applicationParentId): bool
    {
        if ($applicationParentId <= 0) {
            return false;
        }

        $parentId = $this->getCurrentUserParentId();
        if (!$parentId) {
            return false;
        }

        return $applicationParentId === $parentId;
    }

    private function getCurrentUserParentId(): ?int
    {
        if ($this->resolvedCurrentUserParentId) {
            return $this->currentUserParentId;
        }

        $this->resolvedCurrentUserParentId = true;
        $this->currentUserParentId = null;

        $user = $this->getUser();
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            return null;
        }

        try {
            $sql = "SELECT id FROM parents WHERE LOWER(TRIM(COALESCE(email, ''))) = ? LIMIT 1";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([strtolower($email)]);
            $parent = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->currentUserParentId = $parent ? (int) $parent['id'] : null;
            return $this->currentUserParentId;
        } catch (\Exception $e) {
            return null;
        }
    }
}
