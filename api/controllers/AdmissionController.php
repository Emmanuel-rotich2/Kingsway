<?php
namespace App\API\Controllers;

use App\API\Modules\admission\StudentAdmissionWorkflow;
use Exception;

class AdmissionController extends BaseController
{
    private StudentAdmissionWorkflow $api;
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
            'admission_applications_submit'
        ],
        'upload_document' => [
            'admission_documents_upload',
            'admission_documents_create',
            'admission_applications_upload'
        ],
        'verify_document' => [
            'admission_documents_verify',
            'admission_documents_approve',
            'admission_documents_validate',
            'admission_applications_verify'
        ],
        'schedule_interview' => [
            'admission_interviews_schedule',
            'admission_applications_schedule'
        ],
        'record_interview' => [
            'admission_interviews_create',
            'admission_interviews_edit',
            'admission_interviews_approve',
            'admission_interviews_verify'
        ],
        'placement_offer' => [
            'admission_applications_generate',
            'admission_applications_approve',
            'admission_applications_assign'
        ],
        'record_payment' => [
            'admission_applications_approve',
            'admission_applications_validate',
            'admission_applications_edit'
        ],
        'complete_enrollment' => [
            'admission_applications_approve_final',
            'admission_applications_approve',
            'admission_applications_validate'
        ],
    ];

    private const ACTION_STAGE_RULES = [
        'upload_document' => ['application', 'document_verification'],
        'verify_document' => ['document_verification'],
        'schedule_interview' => ['interview_scheduling'],
        'record_interview' => ['interview_assessment'],
        'placement_offer' => ['placement_offer'],
        'record_payment' => ['fee_payment'],
        'complete_enrollment' => ['enrollment'],
    ];

    public function __construct() {
        parent::__construct();
        $this->api = new StudentAdmissionWorkflow();
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
            $canUploadDocuments = $this->canProcessAdmissionActionForStage('upload_document', 'application')
                || $this->canProcessAdmissionActionForStage('upload_document', 'document_verification');
            $canVerifyDocuments = $this->canProcessAdmissionActionForStage('verify_document', 'document_verification');
            $canScheduleInterview = $this->canProcessAdmissionActionForStage('schedule_interview', 'interview_scheduling');
            $canRecordInterview = $this->canProcessAdmissionActionForStage('record_interview', 'interview_assessment');
            $canPlacement = $this->canProcessAdmissionActionForStage('placement_offer', 'placement_offer');
            $canRecordPayment = $this->canProcessAdmissionActionForStage('record_payment', 'fee_payment');
            $canCompleteEnrollment = $this->canProcessAdmissionActionForStage('complete_enrollment', 'enrollment');

            // Get applications at each workflow stage
            $queues = [
                'documents_pending' => [],
                'interview_pending' => [],
                'placement_pending' => [],
                'payment_pending' => [],
                'enrollment_pending' => []
            ];

            // Documents Pending (status = submitted or documents_pending)
            if ($canUploadDocuments || $canVerifyDocuments) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id) as doc_count,
                           (SELECT COUNT(*) FROM admission_documents WHERE application_id = aa.id AND verification_status = 'verified') as verified_count
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.status IN ('submitted', 'documents_pending')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['documents_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Interview Pending (status = documents_verified)
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

            // Placement Pending: workflow is at placement_offer stage but placement hasn't been generated yet.
            // Auto-qualified students land here with status=documents_verified; interview-passed students also arrive here.
            if ($canPlacement) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for,
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'placement_offer'
                      AND aa.status NOT IN ('placement_offered', 'fees_pending', 'enrolled', 'cancelled')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['placement_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Payment Pending (status = placement_offered or fees_pending)
            if ($canRecordPayment) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.total_fees')) as total_fees,
                           JSON_UNQUOTE(JSON_EXTRACT(wi.data_json, '$.assigned_class_id')) as assigned_class_id
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.status IN ('placement_offered', 'fees_pending')
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['payment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Enrollment Pending (payment recorded, ready for final enrollment)
            if ($canCompleteEnrollment) {
                $sql = "SELECT aa.id, aa.application_no, aa.applicant_name, aa.grade_applying_for, 
                           aa.status, aa.created_at,
                           p.first_name as parent_first_name, p.last_name as parent_last_name, p.phone_1,
                           wi.current_stage, wi.data_json
                    FROM admission_applications aa
                    LEFT JOIN parents p ON aa.parent_id = p.id
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE wi.current_stage = 'enrollment' AND aa.status != 'enrolled'
                    {$scopeFilter}
                    ORDER BY aa.created_at DESC";
                $stmt = $db->query($sql);
                $queues['enrollment_pending'] = $this->attachQueueActions($stmt->fetchAll(\PDO::FETCH_ASSOC));
            }

            // Get summary counts
            $summary = [
                'documents_pending' => count($queues['documents_pending']),
                'interview_pending' => count($queues['interview_pending']),
                'placement_pending' => count($queues['placement_pending']),
                'payment_pending' => count($queues['payment_pending']),
                'enrollment_pending' => count($queues['enrollment_pending']),
                'total_pending' => count($queues['documents_pending']) + count($queues['interview_pending'])
                    + count($queues['placement_pending']) + count($queues['payment_pending'])
                    + count($queues['enrollment_pending'])
            ];

            return $this->success([
                'queues' => $queues,
                'summary' => $summary,
                'allowed_tabs' => [
                    'documents_pending' => ($canUploadDocuments || $canVerifyDocuments),
                    'interview_pending' => ($canScheduleInterview || $canRecordInterview),
                    'placement_pending' => $canPlacement,
                    'payment_pending' => $canRecordPayment,
                    'enrollment_pending' => $canCompleteEnrollment,
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
            $sql = "SELECT * FROM admission_documents WHERE application_id = ? ORDER BY is_mandatory DESC, document_type";
            $stmt = $connection->prepare($sql);
            $stmt->execute([$id]);
            $documents = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse workflow data
            $workflowData = json_decode($application['data_json'] ?? '{}', true) ?: [];

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

    /**
     * Get available actions based on workflow stage
     */
    private function getAvailableActions($currentStage, $status)
    {
        $actions = [];

        if (!$this->hasAnyAdmissionPermission('view_any')) {
            return $actions;
        }

        if (in_array($status, ['enrolled', 'cancelled'], true)) {
            return [];
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
            case 'application':
            case 'application_submission':
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage)) {
                    $actions[] = 'upload-documents';
                }
                break;
            case 'document_verification':
                if ($this->canProcessAdmissionActionForStage('upload_document', $normalizedStage)) {
                    $actions[] = 'upload-documents';
                }
                if ($this->canProcessAdmissionActionForStage('verify_document', $normalizedStage)) {
                    $actions[] = 'verify-documents';
                }
                break;
            case 'interview_scheduling':
                if ($this->canProcessAdmissionActionForStage('schedule_interview', $normalizedStage)) {
                    $actions = ['schedule-interview'];
                }
                break;
            case 'interview_assessment':
                if ($this->canProcessAdmissionActionForStage('record_interview', $normalizedStage)) {
                    $actions = ['record-interview'];
                }
                break;
            case 'placement_offer':
                if ($this->canProcessAdmissionActionForStage('placement_offer', $normalizedStage)) {
                    $actions = ['generate-placement'];
                }
                break;
            case 'fee_payment':
                if ($this->canProcessAdmissionActionForStage('record_payment', $normalizedStage)) {
                    $actions = ['record-payment'];
                }
                break;
            case 'enrollment':
                if ($this->canProcessAdmissionActionForStage('complete_enrollment', $normalizedStage)) {
                    $actions = ['complete-enrollment'];
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
            $sql = "SELECT status, COUNT(*) as count 
                    FROM admission_applications aa
                    LEFT JOIN workflow_instances wi ON wi.reference_type = 'admission_application' AND wi.reference_id = aa.id
                    WHERE aa.academic_year = YEAR(CURDATE())
                    {$scopeFilter}
                    GROUP BY status";
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
                        'link' => '/Kingsway/home.php?route=manage_students_admissions&tab=documents_pending'
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
                        'link' => '/Kingsway/home.php?route=manage_students_admissions&tab=interview_pending'
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
                        'link' => '/Kingsway/home.php?route=manage_students_admissions&tab=placement_pending'
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
                        'link' => '/Kingsway/home.php?route=manage_students_admissions&tab=payment_pending'
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
                        'link' => '/Kingsway/home.php?route=manage_students_admissions&tab=enrollment_pending'
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
            } else {
                $response = $this->success($result);
            }
        } else {
            $response = $this->success($result);
        }
        return $response;
    }

    private function hasAnyAdmissionPermission(string $group): bool
    {
        $permissionCodes = self::PERMISSIONS[$group] ?? [];
        $hasPermission = !empty($permissionCodes) && $this->userHasAny($permissionCodes);

        if ($hasPermission) {
            return true;
        }

        if ($group === 'view_any') {
            return $this->hasAdmissionRouteAccess();
        }

        return false;
    }

    private function attachQueueActions(array $records): array
    {
        foreach ($records as &$record) {
            $currentStage = $record['current_stage'] ?? null;
            $status = $record['status'] ?? null;
            $record['available_actions'] = $this->getAvailableActions($currentStage, $status);
        }
        unset($record);

        return $records;
    }

    private function canProcessAdmissionActionForApplication(string $actionGroup, array $application): bool
    {
        $hasActionPermission = $this->hasAnyAdmissionPermission($actionGroup);
        if (!$hasActionPermission && !$this->hasAdmissionRouteAccess()) {
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
        if (!$hasActionPermission && !$this->hasAdmissionRouteAccess()) {
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
        if ($this->hasAdmissionRouteAccess()) {
            return true;
        }

        return $this->userHasAny(
            [
                '*',
                'admission_view',
                'admission_applications_view_all',
                'admission_applications_approve',
                'admission_applications_approve_final'
            ],
            [],
            ['System Administrator', 'Director', 'School Administrator']
        );
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
                $userOverrideSql = "SELECT ur.is_allowed
                    FROM user_routes ur
                    JOIN routes r ON r.id = ur.route_id
                    WHERE ur.user_id = ?
                      AND r.name = ?
                      AND r.is_active = 1
                      AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
                    LIMIT 1";
                $stmt = $this->db->getConnection()->prepare($userOverrideSql);
                $stmt->execute([$userId, 'manage_students_admissions']);
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
            $sql = "SELECT 1
                FROM role_routes rr
                JOIN routes r ON r.id = rr.route_id
                WHERE rr.is_allowed = 1
                  AND r.is_active = 1
                  AND r.name = ?
                  AND rr.role_id IN ({$placeholders})
                LIMIT 1";
            $params = array_merge(['manage_students_admissions'], array_map('intval', $roleIds));
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
            return 'application';
        }

        return $stageCode;
    }

    private function inferStageFromApplication(array $application): ?string
    {
        $status = strtolower((string) ($application['status'] ?? ''));
        switch ($status) {
            case 'submitted':
                return 'application';
            case 'documents_pending':
                return 'document_verification';
            case 'documents_verified':
                return $this->requiresInterviewAssessmentForGrade($application['grade_applying_for'] ?? null)
                    ? 'interview_scheduling'
                    : 'placement_offer';
            case 'placement_offered':
            case 'fees_pending':
                return 'fee_payment';
            case 'enrolled':
                return 'enrollment';
            default:
                return null;
        }
    }

    private function requiresInterviewAssessmentForGrade(?string $grade): bool
    {
        if (!$grade) {
            return true;
        }

        $normalized = strtolower(str_replace(' ', '', trim($grade)));
        return in_array($normalized, ['grade2', 'grade3', 'grade4', 'grade5', 'grade6'], true);
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
            $sql = "SELECT aa.*, wi.data_json, wi.started_by
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
            $sql = "SELECT aa.*, wi.data_json, wi.started_by
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
