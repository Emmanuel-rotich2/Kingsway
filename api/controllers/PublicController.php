<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Modules\website\WebsiteManager;
use App\Database\Database;
use App\API\Services\payments\UniformCatalogService;
use DomainException;

/**
 * PublicController - Unauthenticated write endpoints for the public website
 * forms (job applications, contact inquiries, admission applications,
 * newsletter subscriptions). The pages never touch the DB or filesystem; they
 * POST here and the manager owns every write.
 *
 * Routes (all public by design):
 *   POST /api/public/job-applications
 *   POST /api/public/inquiries
 *   POST /api/public/applications
 *   POST /api/public/subscribers
 */
class PublicController extends BaseAPI
{
    private WebsiteManager $manager;

    public function __construct()
    {
        parent::__construct('public');
        $this->manager = $this->contract('App\API\Modules\website\WebsiteManager');
    }

    /**
     * GET /api/public/setup-invitation?token=...
     * Read-only onboarding flags (parent vs staff invitation, whether the
     * password is already saved and the OTP step should resume) so the
     * account-setup page can render without any server-side DB access.
     */
    public function getSetupInvitation($id = null, $data = [], $segments = [])
    {
        $token = trim((string)($data['token'] ?? $_GET['token'] ?? ''));
        if ($token === '') {
            return $this->errorResponse('Token is required.', 422);
        }
        $service = $this->contract(\App\API\Services\StaffMigrationService::class, Database::getInstance()->getConnection());
        return $this->successResponse($service->setupInvitationFlags($token));
    }

    public function postJobApplications($id = null, $data = [], $segments = [])
    {
        foreach (['apply_first_name', 'apply_last_name', 'apply_email', 'apply_phone'] as $field) {
            if (trim($data[$field] ?? '') === '') {
                return $this->errorResponse('Please fill in all required fields.', 422);
            }
        }
        if (!filter_var(trim($data['apply_email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse('Please enter a valid email address.', 422);
        }
        $cvFile = $_FILES['apply_cv'] ?? [];
        if (empty($cvFile['name']) || ($cvFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $this->errorResponse('Please upload your CV (PDF/DOC).', 422);
        }
        return $this->manager->createJobApplication($data, $cvFile);
    }

    public function postInquiries($id = null, $data = [], $segments = [])
    {
        $name = trim($data['cf_name'] ?? '');
        $email = filter_var(trim($data['cf_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $message = trim($data['cf_message'] ?? '');
        if ($name === '' || !$email || $message === '') {
            return $this->errorResponse('Please fill in your name, email and message.', 422);
        }
        return $this->manager->createInquiry($data);
    }

    public function postApplications($id = null, $data = [], $segments = [])
    {
        $childName = trim($data['child_name'] ?? '');
        $parentName = trim($data['parent_name'] ?? '');
        $phone = trim($data['parent_phone'] ?? '');
        $grade = trim($data['grade_applying'] ?? '');
        $startTerm = trim($data['preferred_start'] ?? '');
        $admissionWindowId = (int) ($data['admission_window_id'] ?? 0);

        if ($childName === '' || $parentName === '' || $phone === '' || $grade === '') {
            return $this->errorResponse('Please fill in all required fields.', 422);
        }

        if ($startTerm !== '') {
            $validTokens = $this->manager->openTermTokens();
            if (!in_array($startTerm, $validTokens, true)) {
                return $this->errorResponse('Selected start term is not open for applications.', 422);
            }
        }

        $validGrades = ['PP1', 'PP2', 'Playgroup', 'Grade1', 'Grade2', 'Grade3',
                        'Grade4', 'Grade5', 'Grade6', 'Grade7', 'Grade8',
                        'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5',
                        'Grade 6', 'Grade 7', 'Grade 8'];
        if (!in_array($grade, $validGrades, true)) {
            return $this->errorResponse('Selected grade is not open for applications.', 422);
        }

        $mappedFiles = [];
        $fileMap = [
            'birth_certificate'      => 'doc_birth_certificate',
            'passport_photo'         => 'doc_passport_photo',
            'parent_id'              => 'doc_parent_id',
            'previous_school_report' => 'doc_previous_school_report',
            'immunization_card'      => 'doc_immunization_card',
            'progress_report'        => 'doc_progress_report',
            'leaving_certificate'    => 'doc_leaving_certificate',
            'transfer_letter'        => 'doc_transfer_letter',
            'medical_records'        => 'doc_medical_records',
            'other'                  => 'doc_other',
        ];
        foreach ($fileMap as $docType => $field) {
            if (isset($_FILES[$field])) {
                $mappedFiles[$docType] = $_FILES[$field];
            }
        }

        // The public website is an anonymous application channel.  Do not use
        // an ambient bearer token/cookie to replace the guardian entered here:
        // a staff or parent session can exist in the same browser, but it must
        // not change an anonymous applicant's submitted guardian.  The
        // authenticated parent portal uses /api/parent-portal/admission-
        // application and binds the parent server-side there.
        $payload = [
            'applicant_name'       => $childName,
            'date_of_birth'        => trim($data['child_dob'] ?? ''),
            'gender'               => trim($data['child_gender'] ?? ''),
            'birth_certificate_no' => trim((string) ($data['birth_certificate_no'] ?? '')),
            'grade_applying_for'   => $grade,
            'current_grade_class'  => trim($data['child_prev_grade'] ?? ''),
            'previous_school'      => trim($data['child_prev_school'] ?? ''),
            'application_source'   => 'online',
            'target_term_token'    => $startTerm,
            'admission_window_id'  => $admissionWindowId > 0 ? $admissionWindowId : null,
            'boarding_preference'  => trim((string) ($data['boarding_preference'] ?? 'day')),
            'parent_id'            => 0,
            'parent_name'          => $parentName,
            'parent_national_id'   => trim($data['parent_id'] ?? ''),
            'parent_phone'         => $phone,
            'parent_email'         => filter_var(trim($data['parent_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: '',
            'parent_address'       => trim($data['parent_address'] ?? ''),
            'parent_relationship'  => trim($data['parent_relationship'] ?? $data['relationship'] ?? ''),
            'special_needs'        => trim($data['special_needs'] ?? ''),
        ];

        $workflow = $this->contract('App\API\Modules\admission\StudentAdmissionWorkflow');
        $result = $workflow->submitApplication($payload, $mappedFiles);

        if (($result['code'] ?? 0) < 400) {
            $response = $this->successResponse(
                ['ref' => $result['data']['ref'] ?? $result['data']['application_no'] ?? '', 'application_no' => $result['data']['application_no'] ?? ''],
                $result['message'] ?? 'Application received!',
                $result['code'] ?? 201
            );
            // The public form page reads json.ref at top level, so mirror it there.
            $response['ref'] = $result['data']['ref'] ?? $result['data']['application_no'] ?? '';
            $response['application_no'] = $result['data']['application_no'] ?? $response['ref'];
            return $response;
        }
        return $this->errorResponse(
            $result['message'] ?? 'Submission failed. Please try again.',
            $result['code'] ?? 422
        );
    }

public function postSubscribers($id = null, $data = [], $segments = [])
    {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return $this->errorResponse('Please enter a valid email address.', 422);
        }
        return $this->manager->createSubscriber($email, trim($data['name'] ?? ''));
    }

    /** POST /api/public/ai-faq */
    public function postAiFaq($id = null, $data = [], $segments = [])
    {
        try {
            $content = $this->manager->getContent();
            $payload = is_array($content['data'] ?? null) ? $content['data'] : (is_array($content) ? $content : []);
            $corpus = [];
            foreach ($payload as $key => $value) {
                $sourceKey = preg_replace('/[^a-z0-9_]+/i', '_', (string) $key);
                if (is_scalar($value) && trim((string) $value) !== '') {
                    $corpus[] = ['id' => 'public_' . $sourceKey, 'title' => (string) $key, 'content' => (string) $value];
                    continue;
                }
                if (!is_array($value)) continue;
                foreach (array_slice($value, 0, 40) as $index => $item) {
                    if (!is_array($item)) continue;
                    // WebsiteManager exposes published blocks as content_key /
                    // content_value, while normalized public sections use
                    // description/name/title. Include only public prose fields;
                    // never send IDs, ordering, flags, or internal metadata.
                    $parts = [];
                    foreach (['content_value', 'content', 'description', 'level_range', 'event_title', 'bio', 'setting_value'] as $field) {
                        $text = trim((string) ($item[$field] ?? ''));
                        if ($text !== '') $parts[] = $text;
                    }
                    if ($parts === []) continue;
                    $title = (string) ($item['title'] ?? $item['content_key'] ?? $item['name'] ?? $item['event_title'] ?? $key);
                    $corpus[] = [
                        'id' => 'public_' . $sourceKey . '_' . (int) $index,
                        'title' => $title,
                        'content' => implode("\n", array_unique($parts)),
                    ];
                }
            }
            // This is a public fact already displayed in the shared website
            // footer/login pages. Keep it explicit so a technology question
            // cannot be answered with the unrelated school-founder record.
            $corpus[] = [
                'id' => 'public_system_maintainer',
                'title' => 'School system maintenance',
                'content' => 'The Kingsway school management system is maintained by AngiSoft Technologies. Public company information is available at https://www.angisoft.co.ke. The published school information does not claim that the founders developed the software.',
            ];
            $corpus[] = [
                'id' => 'public_system_maintainer_contact',
                'title' => 'AngiSoft Technologies contact',
                'content' => 'To contact AngiSoft Technologies, visit https://www.angisoft.co.ke/contact, call or send SMS/WhatsApp to +254710398690, or email info@angisoft.co.ke. AngiSoft Technologies is based in Nairobi, Kenya.',
            ];
            $result = $this->contract('App\\API\\Services\\PublicAiAssistantService')->ask(
                (string) ($data['question'] ?? ''),
                $corpus,
                is_array($data['conversation'] ?? null) ? $data['conversation'] : []
            );
            return $this->successResponse($result, 'Public assistant response prepared');
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), (int) ($e->getCode() ?: 422));
        } catch (\Throwable $e) {
            // Keep the public response generic, but preserve the actionable
            // provider/normalization failure in structured logs for diagnosis.
            \App\API\Services\Logger::error('ai_generation', 'Public FAQ request failed', [
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'request_id' => $_SERVER['REQUEST_ID'] ?? null,
            ]);
            return $this->errorResponse('The public assistant is temporarily unavailable.', 503);
        }
    }

    /** GET /api/public/uniform-catalog OR /api/public/uniform-catalog/{id} */
    public function getUniformCatalog($id = null, $data = [], $segments = [])
    {
        $pdo = Database::getInstance()->getConnection();
        $svc = $this->contract('App\API\Services\payments\UniformCatalogService', $pdo);

        // Single product — includes all images and sizes
        if ($id !== null && is_numeric($id)) {
            $product = $svc->get((int) $id);
            if (empty($product) || ($product['status'] ?? '') !== 'active' || (int)($product['published'] ?? 0) !== 1) {
                return $this->errorResponse('Product not found.', 404);
            }
            // Images for this product from the catalogue service
            $product['images'] = ($this->contract('App\API\Services\payments\UniformCatalogService', $pdo))->imagesForProduct((int) $id);
            $uploadService = $this->contract('App\API\Services\UploadService');
            foreach ($product['images'] as &$image) {
                $image['url'] = $uploadService->publicUrl($image['url'] ?? null);
            }
            unset($image);

            // Available sizes + variants via the catalogue service
            $cat = $this->contract('App\API\Services\payments\UniformCatalogService', $pdo);
            $product['variants'] = $cat->variantsForProduct((int) $id);
            $product['sizes'] = $cat->sizesForProduct((int) $product['item_id'], (int) $id);
            $product['reviews'] = ($this->contract('App\API\Services\catalog\CatalogCommerceService', $pdo))->reviews((int)$id);

            return $this->successResponse(['product' => $product], 'Product details');
        }

        // Full catalogue listing
        return $this->successResponse(['products' => $svc->list($data)], 'Uniform catalogue');
    }

    /**
     * GET /api/public/student-verification/{studentId}?scope=transport
     *
     * Data source for the QR ID-card verification page. Public callers get
     * name+class only; signed-in staff get the sections their role allows
     * (decided server-side by StudentCardVerificationService::viewPolicy).
     * No SQL in the page, none here either — the service owns every query.
     */
    public function getStudentVerification($id = null, $data = [], $segments = [])
    {
        $studentId = (int) ($id ?? ($_GET['student_id'] ?? 0));
        if ($studentId < 1) {
            return $this->errorResponse('Learner identifier is required.', 422);
        }

        // Optional authentication: a verified staff session widens sections;
        // anonymous scans (security desk, parents with a phone) stay public.
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user && (session_status() === PHP_SESSION_ACTIVE || session_start()) && !empty($_SESSION['user'])) {
            $user = $_SESSION['user'];
        }

        $service = \App\API\Services\StudentCardVerificationService::shared();
        $student = $service->identity($studentId);
        if (!$student) {
            return $this->errorResponse('Learner not found.', 404);
        }

        $policy = $service->viewPolicy(is_array($user) ? $user : null, (string) ($_GET['scope'] ?? ''));
        $sections = $service->sections($studentId, $policy['sections']);

        return $this->successResponse([
            'student' => $student,
            'sections_allowed' => $policy['sections'],
            'viewing_as' => $policy['viewing_as'],
            'data' => $sections,
        ], 'Learner verification');
    }
}
