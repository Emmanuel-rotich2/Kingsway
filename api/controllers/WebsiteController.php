<?php
namespace App\API\Controllers;

use App\API\Modules\website\WebsiteManager;

/**
 * WebsiteController — CRUD for all public website content tables.
 * Endpoint: /api/website/{resource}
 *
 * All DB/business logic lives in WebsiteManager; this controller only
 * validates auth/RBAC, reads input, delegates, and responds.
 */
class WebsiteController extends BaseController
{
    private $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = $this->contract('App\API\Modules\website\WebsiteManager');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERMISSION HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function getEffectivePerms(): array
    {
        // RBACMiddleware enriches the request context after authentication.
        // Read the live context instead of relying only on the controller's
        // constructor snapshot, which can predate permission resolution.
        $user = $_SERVER['auth_user'] ?? $this->user;
        if (!$user) return [];
        $permissions = (array) ($user['effective_permissions'] ?? $user['permissions'] ?? []);
        return array_values(array_unique(array_filter(array_map(
            static fn($permission) => is_array($permission)
                ? ($permission['code'] ?? $permission['permission_code'] ?? null)
                : $permission,
            $permissions
        ))));
    }

    private function hasPerm(string $perm): bool
    {
        if ($perm === 'website_view' && !$this->user && $this->isReadRequest()) {
            return true;
        }
        if (!($_SERVER['auth_user'] ?? $this->user)) return false;
        $perms = $this->getEffectivePerms();
        return in_array($perm, $perms) || in_array(str_replace('_', '.', $perm), $perms);
    }

    private function isReadRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    private function requirePerm(string $perm)
    {
        if (!$this->hasPerm($perm) && !$this->hasPerm('website_settings_manage')) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
        return null;
    }

    public function forbidden($message = 'Access forbidden')
    {
        http_response_code(403);
        return json_encode(['status' => 'error', 'message' => $message, 'data' => null, 'code' => 403]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESPONSE HANDLER
    // ─────────────────────────────────────────────────────────────────────────

    private function handleResponse($result)
    {
        if (is_array($result)) {
            $code = (int) ($result['code'] ?? 200);
            if (isset($result['success'])) {
                return $result['success']
                    ? ($code === 201
                        ? $this->created($result['data'] ?? [], $result['message'] ?? 'Created')
                        : $this->success($result['data'] ?? [], $result['message'] ?? 'OK'))
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? ($code === 201
                        ? $this->created($result['data'] ?? [], $result['message'] ?? 'Created')
                        : $this->success($result['data'] ?? [], $result['message'] ?? 'OK'))
                    : $this->mapError($code, $result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    private function mapError($code, $message, $data = [])
    {
        if ($code >= 500) {
            return $this->serverError($message, $data);
        }
        if ($code === 404) {
            return $this->notFound($message);
        }
        if ($code === 401) {
            return $this->unauthorized($message);
        }
        if ($code === 403) {
            return $this->forbidden($message);
        }
        return $this->badRequest($message, $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATS  GET /api/website/stats
    // ─────────────────────────────────────────────────────────────────────────

    public function getStats($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        $result = $this->manager->getStats();
        // Anonymous visitors may read the showcase counts (news/events/jobs/
        // gallery/downloads + the live students/staff headcounts shown on the
        // homepage) but not the admin workflow KPIs (received applications,
        // new inquiries, job applications) — those stay staff-only.
        if (!$this->user && is_array($result) && isset($result['data']) && is_array($result['data'])) {
            $result['data'] = array_intersect_key(
                $result['data'],
                array_flip(['news', 'events', 'jobs', 'gallery', 'downloads', 'students', 'staff'])
            );
        }
        return $this->handleResponse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // OPEN TERMS  GET /api/website/terms
    // ─────────────────────────────────────────────────────────────────────────

    public function getTerms($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getOpenTerms());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTIVE GRADES  GET /api/website/grades
    // ─────────────────────────────────────────────────────────────────────────

    public function getGrades($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getActiveGrades());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS ARTICLES  /api/website/news
    // ─────────────────────────────────────────────────────────────────────────

    public function getNews($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getNews($id, $data));
    }

    public function postNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['content'])) return $this->badRequest('Title and content are required.');
        $author = trim(($this->user['first_name'] ?? '') . ' ' . ($this->user['last_name'] ?? ''));
        return $this->handleResponse($this->manager->createNews($data, $author ?: 'Admin'));
    }

    public function putNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        return $this->handleResponse($this->manager->updateNews($id, $data));
    }

    public function deleteNews($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        return $this->handleResponse($this->manager->deleteNews($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVENTS  /api/website/events
    // ─────────────────────────────────────────────────────────────────────────

    public function getEvents($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getEvents($id, $data));
    }

    public function postEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['event_date'])) return $this->badRequest('Title and event date are required.');
        return $this->handleResponse($this->manager->createEvent($data));
    }

    public function putEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        return $this->handleResponse($this->manager->updateEvent($id, $data));
    }

    public function deleteEvents($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        return $this->handleResponse($this->manager->deleteEvent($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GALLERY  /api/website/gallery
    // ─────────────────────────────────────────────────────────────────────────

    public function getGallery($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getGallery($data));
    }

    public function postGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (empty($data['image_url'])) return $this->badRequest('Image URL is required.');
        return $this->handleResponse($this->manager->createGalleryItem($data));
    }

    public function putGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        return $this->handleResponse($this->manager->updateGalleryItem($id, $data));
    }

    public function deleteGallery($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        return $this->handleResponse($this->manager->deleteGalleryItem($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOADS  /api/website/downloads
    // ─────────────────────────────────────────────────────────────────────────

    public function getDownloads($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getDownloads($data));
    }

    /**
     * GET /api/website/printable-downloads
     *
     * Public catalog for generated school PDFs. The actual PDF is generated
     * only after the visitor requests a specific file, keeping the catalog
     * cheap while ensuring it always reflects the selected academic year.
     */
    public function getPrintableDownloads($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');

        try {
            $years = $this->db->query(
                "SELECT id, year_code, year_name, status
                 FROM academic_years
                 ORDER BY id DESC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            $years = array_map(static function (array $year): array {
                return [
                    'id' => (int) ($year['id'] ?? 0),
                    'year_code' => (string) ($year['year_code'] ?? ''),
                    'year_name' => (string) ($year['year_name'] ?? ''),
                    'status' => (string) ($year['status'] ?? ''),
                ];
            }, $years ?: []);

            if (!$years) {
                return $this->success(['items' => [], 'academic_years' => []], 'No academic years available.');
            }

            $yearInput = trim((string) ($data['academic_year'] ?? $data['academicYear'] ?? ''));
            $selectedYear = $this->resolvePrintableYear($years, $yearInput);
            if (!$selectedYear) {
                return $this->badRequest('The selected academic year is not available.');
            }

            $yearId = (int) $selectedYear['id'];
            $yearLabel = (string) ($selectedYear['year_code'] ?: $selectedYear['year_name']);
            $items = $this->buildPrintableDownloadCatalog($yearId, $yearLabel);

            return $this->success([
                'items' => $items,
                'academic_years' => $years,
                'selected_academic_year' => $yearLabel,
            ], 'Printable downloads available.');
        } catch (\Throwable $exception) {
            \App\API\Services\Logger::legacyError('[WebsiteController] printable downloads: ' . $exception->getMessage());
            return $this->serverError('Printable downloads are temporarily unavailable.');
        }
    }

    /**
     * GET /api/website/printable-download
     * Generate one public school PDF and return an attachment URL.
     */
    public function getPrintableDownload($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');

        try {
            $kind = strtolower(trim((string) ($data['kind'] ?? '')));
            if (!in_array($kind, ['fee', 'calendar'], true)) {
                return $this->badRequest('Invalid printable document type.');
            }

            $years = $this->db->query(
                "SELECT id, year_code, year_name, status FROM academic_years ORDER BY id DESC"
            )->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $years = array_map(static function (array $year): array {
                return [
                    'id' => (int) ($year['id'] ?? 0),
                    'year_code' => (string) ($year['year_code'] ?? ''),
                    'year_name' => (string) ($year['year_name'] ?? ''),
                    'status' => (string) ($year['status'] ?? ''),
                ];
            }, $years);
            $selectedYear = $this->resolvePrintableYear(
                $years,
                trim((string) ($data['academic_year'] ?? $data['academicYear'] ?? ''))
            );
            if (!$selectedYear) return $this->badRequest('The selected academic year is not available.');

            $yearValue = (string) ($selectedYear['year_code'] ?: $selectedYear['id']);
            if ($kind === 'calendar') {
                $pdfPath = $this->prints()->printAcademicCalendar([
                    'academicYear' => $yearValue,
                ]);
            } else {
                $scope = strtolower(trim((string) ($data['scope'] ?? 'all')));
                $studentType = strtolower(trim((string) ($data['student_type'] ?? $data['studentType'] ?? 'both')));
                if (!in_array($studentType, ['both', 'day', 'boarder'], true)) {
                    return $this->badRequest('Invalid student type.');
                }
                if (!preg_match('/^(all|ecd|lower_primary|upper_primary|primary|jss|class_[0-9]+)$/', $scope)) {
                    return $this->badRequest('Invalid fee-structure scope.');
                }

                $pdfPath = $this->prints()->printSimpleFeeStructure([
                    'academicYear' => $yearValue,
                    'scope' => $scope,
                    'studentType' => $studentType,
                ]);
            }

            return $this->success([
                'filename' => basename($pdfPath),
                'download_url' => $this->generatedDownloadUrl($pdfPath),
            ], 'PDF generated.');
        } catch (\Throwable $exception) {
            \App\API\Services\Logger::legacyError('[WebsiteController] printable download generation: ' . $exception->getMessage());
            return $this->serverError('Unable to generate this PDF right now.');
        }
    }

    private function resolvePrintableYear(array $years, string $input): ?array
    {
        if ($input !== '') {
            foreach ($years as $year) {
                if ((string) $year['id'] === $input || $year['year_code'] === $input || $year['year_name'] === $input) {
                    return $year;
                }
            }
            return null;
        }

        foreach ($years as $year) {
            if (strtolower($year['status']) === 'active') return $year;
        }
        return $years[0] ?? null;
    }

    private function buildPrintableDownloadCatalog(int $yearId, string $yearLabel): array
    {
        $items = [
            ['key' => 'calendar', 'title' => $yearLabel . ' Academic Year Calendar', 'category' => 'Academic Year Calendar', 'icon' => 'bi-calendar3', 'color' => '#0d6efd', 'kind' => 'calendar'],
            ['key' => 'fee-all-both', 'title' => $yearLabel . ' School Fee Structure — Day Scholars & Boarders', 'category' => 'Fee Structures', 'icon' => 'bi-cash-stack', 'color' => '#198754', 'kind' => 'fee', 'scope' => 'all', 'student_type' => 'both'],
            ['key' => 'fee-all-day', 'title' => $yearLabel . ' Day Scholars Fee Structure', 'category' => 'Fee Structures', 'icon' => 'bi-person-walking', 'color' => '#198754', 'kind' => 'fee', 'scope' => 'all', 'student_type' => 'day'],
            ['key' => 'fee-all-boarder', 'title' => $yearLabel . ' Boarders Fee Structure', 'category' => 'Fee Structures', 'icon' => 'bi-house-heart-fill', 'color' => '#1e40af', 'kind' => 'fee', 'scope' => 'all', 'student_type' => 'boarder'],
        ];

        $scopes = [
            'ecd' => 'ECD / Pre-primary',
            'lower_primary' => 'Lower Primary',
            'upper_primary' => 'Upper Primary',
            'primary' => 'Primary School',
            'jss' => 'Junior School',
        ];
        foreach ($scopes as $scope => $scopeLabel) {
            foreach (['both' => 'Both Types', 'day' => 'Day Scholars', 'boarder' => 'Boarders'] as $type => $typeLabel) {
                $items[] = [
                    'key' => 'fee-' . $scope . '-' . $type,
                    'title' => $yearLabel . ' ' . $scopeLabel . ' Fee Structure — ' . $typeLabel,
                    'category' => 'Fee Structures',
                    'icon' => $type === 'boarder' ? 'bi-house-heart-fill' : ($type === 'day' ? 'bi-person-walking' : 'bi-mortarboard-fill'),
                    'color' => $type === 'boarder' ? '#1e40af' : '#198754',
                    'kind' => 'fee',
                    'scope' => $scope,
                    'student_type' => $type,
                ];
            }
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT c.id, c.name
             FROM academic_year_classes ayc
             JOIN classes c ON c.id = ayc.class_id
             WHERE ayc.academic_year_id = ?
             ORDER BY c.id"
        );
        $stmt->execute([$yearId]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $class) {
            $classId = (int) $class['id'];
            $className = (string) $class['name'];
            foreach (['both' => 'Both Types', 'day' => 'Day Scholar', 'boarder' => 'Boarder'] as $type => $label) {
                $items[] = [
                    'key' => 'fee-class-' . $classId . '-' . $type,
                    'title' => $yearLabel . ' ' . $className . ' Fee Structure — ' . $label,
                    'category' => 'Class Fee Structures',
                    'icon' => $type === 'boarder' ? 'bi-house-heart-fill' : ($type === 'day' ? 'bi-person-walking' : 'bi-card-list'),
                    'color' => $type === 'boarder' ? '#1e40af' : '#198754',
                    'kind' => 'fee',
                    'scope' => 'class_' . $classId,
                    'student_type' => $type,
                ];
            }
        }

        return $items;
    }

    public function postDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (empty($data['title'])) {
            return $this->badRequest('Title is required.');
        }
        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return $this->badRequest('Choose a school document to upload.');
        }

        return $this->handleResponse($this->manager->createDownload($data, $_FILES['file'], $this->userId()));
    }

    public function putDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (!$id) {
            return $this->badRequest('Download ID required.');
        }

        $file = $_FILES['file'] ?? [];
        return $this->handleResponse($this->manager->updateDownload($id, $data, $file, $this->userId()));
    }

    public function deleteDownloads($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;

        if (!$id) {
            return $this->badRequest('Download ID required.');
        }

        return $this->handleResponse($this->manager->deleteDownload($id, $this->userId()));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB VACANCIES  /api/website/jobs
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobs($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getJobs($id, $data));
    }

    public function postJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['deadline'])) return $this->badRequest('Title and deadline are required.');
        return $this->handleResponse($this->manager->createJob($data));
    }

    public function putJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        return $this->handleResponse($this->manager->updateJob($id, $data));
    }

    public function deleteJobs($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        return $this->handleResponse($this->manager->closeJob($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS  /api/website/settings
    // ─────────────────────────────────────────────────────────────────────────

    public function getSettings($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getSettings());
    }

    public function putSettings($id = null, $data = [], $segments = [])
    {
        $isAdmissionNumberSetting = in_array((string) ($data['key'] ?? ''), [
            'admission_no_format', 'admission_no_start_sequence',
            'staff_no_format', 'staff_no_start_sequence'
        ], true);
        if (!$isAdmissionNumberSetting) {
            $guard = $this->requirePerm('website_settings_manage');
            if ($guard) return $guard;
        }
        if (empty($data['key'])) return $this->badRequest('Setting key is required.');
        return $this->handleResponse($this->manager->saveSetting($data['key'], (string) ($data['value'] ?? '')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTENT BLOCKS  /api/website/content
    // ─────────────────────────────────────────────────────────────────────────

    public function getContent($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getContent());
    }

    public function putContent($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['key'])) return $this->badRequest('Content key is required.');
        return $this->handleResponse($this->manager->saveContent($data['key'], (string) ($data['value'] ?? '')));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // APPLICATIONS (read-only)  /api/website/applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getApplications($data));
    }

    public function putApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Application ID required.');
        if (empty($data['status'])) return $this->badRequest('Status is required.');
        return $this->handleResponse($this->manager->updateApplicationStatus($id, (string) $data['status']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB APPLICATIONS /api/website/job-applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getJobApplications());
    }

    public function putJobApplications($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id || empty($data['status'])) return $this->badRequest('Application ID and status are required.');
        return $this->handleResponse($this->manager->updateJobApplicationStatus((int)$id, (string)$data['status'], $this->userId(), $data['notes'] ?? null));
    }

    public function postJobApplicationsInterview($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id) return $this->badRequest('Application ID required.');
        return $this->handleResponse($this->manager->scheduleJobInterview((int)$id, $data, $this->userId()));
    }

    public function putJobApplicationsInterview($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_applications_manage')) return $this->forbidden('Recruitment management access required.');
        if (!$id) return $this->badRequest('Interview ID required.');
        return $this->handleResponse($this->manager->completeJobInterview((int)$id, $data, $this->userId()));
    }

    // The browser route is /job-applications/interviews/{id}; keep the plural
    // REST resource mapped explicitly instead of allowing the generic router to
    // fall back to the application-status handler.
    public function putJobApplicationsInterviews($id = null, $data = [], $segments = [])
    {
        return $this->putJobApplicationsInterview($id, $data, $segments);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTACT INQUIRIES (read-only)  /api/website/inquiries
    // ─────────────────────────────────────────────────────────────────────────

    public function getInquiries($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getInquiries());
    }

    public function putInquiries($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Inquiry ID required.');
        if (!empty($data['reply'])) {
            return $this->handleResponse($this->manager->replyInquiry((int) $id, (string) $data['reply'], (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0)));
        }
        if (empty($data['status'])) return $this->badRequest('Status is required.');
        return $this->handleResponse($this->manager->updateInquiryStatus($id, (string) $data['status']));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS CATEGORIES  /api/website/categories
    // ─────────────────────────────────────────────────────────────────────────

    public function getCategories($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getCategories());
    }

    public function postCategories($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['name'])) return $this->badRequest('Category name required.');
        return $this->handleResponse($this->manager->createCategory((string) $data['name'], (string) ($data['color'] ?? '#198754')));
    }

    public function deleteCategories($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Category ID required.');
        return $this->handleResponse($this->manager->deactivateCategory($id));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GENERIC TABLE CRUD  /api/website/{leadership|programs|facilities|history|values|departments|benefits}
    // ─────────────────────────────────────────────────────────────────────────

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->getStats($id, $data, $segments);
    }

    public function getLeadership($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipHierarchy());
    }

    public function getLeadershipLevels($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipLevels());
    }

    public function getLeadershipPositions($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getLeadershipPositions($data['level_id'] ?? null));
    }
    public function getPrograms($id = null, $data = [], $segments = [])   { return $this->genericRead('programs', $id, $data); }
    public function getFacilities($id = null, $data = [], $segments = []) { return $this->genericRead('facilities', $id, $data); }
    public function getHistory($id = null, $data = [], $segments = [])    { return $this->genericRead('history', $id, $data); }
    public function getValues($id = null, $data = [], $segments = [])     { return $this->genericRead('values', $id, $data); }
    public function getBenefits($id = null, $data = [], $segments = [])   { return $this->genericRead('benefits', $id, $data); }
    public function getTestimonials($id = null, $data = [], $segments = []) { return $this->genericRead('testimonials', $id, $data); }

    public function getDepartments($id = null, $data = [], $segments = [])
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getDepartments($id));
    }

    public function postLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->createLeadershipEntry($data));
    }
    public function postPrograms($id = null, $data = [], $segments = [])   { return $this->genericWrite('createRecord', 'programs', $data); }
    public function postFacilities($id = null, $data = [], $segments = []){ return $this->genericWrite('createRecord', 'facilities', $data); }
    public function postHistory($id = null, $data = [], $segments = [])    { return $this->genericWrite('createRecord', 'history', $data); }
    public function postValues($id = null, $data = [], $segments = [])     { return $this->genericWrite('createRecord', 'values', $data); }
    public function postBenefits($id = null, $data = [], $segments = [])   { return $this->genericWrite('createRecord', 'benefits', $data); }
    public function postTestimonials($id = null, $data = [], $segments = []) { return $this->genericWrite('createRecord', 'testimonials', $data); }

    public function postDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->createDepartment($data));
    }

    public function putLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->updateLeadershipEntry($id, $data));
    }
    public function putPrograms($id = null, $data = [], $segments = [])   { return $this->genericWrite('updateRecord', 'programs', $data, $id); }
    public function putFacilities($id = null, $data = [], $segments = []) { return $this->genericWrite('updateRecord', 'facilities', $data, $id); }
    public function putHistory($id = null, $data = [], $segments = [])    { return $this->genericWrite('updateRecord', 'history', $data, $id); }
    public function putValues($id = null, $data = [], $segments = [])     { return $this->genericWrite('updateRecord', 'values', $data, $id); }
    public function putBenefits($id = null, $data = [], $segments = [])   { return $this->genericWrite('updateRecord', 'benefits', $data, $id); }
    public function putTestimonials($id = null, $data = [], $segments = []) { return $this->genericWrite('updateRecord', 'testimonials', $data, $id); }

    public function putDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->updateDepartment($id, $data));
    }

    public function deleteLeadership($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteLeadershipEntry($id));
    }
    public function deletePrograms($id = null, $data = [], $segments = [])   { return $this->genericDelete('programs', $id); }
    public function deleteFacilities($id = null, $data = [], $segments = []) { return $this->genericDelete('facilities', $id); }
    public function deleteHistory($id = null, $data = [], $segments = [])    { return $this->genericDelete('history', $id); }
    public function deleteValues($id = null, $data = [], $segments = [])     { return $this->genericDelete('values', $id); }
    public function deleteBenefits($id = null, $data = [], $segments = [])   { return $this->genericDelete('benefits', $id); }
    public function deleteTestimonials($id = null, $data = [], $segments = []) { return $this->genericDelete('testimonials', $id); }

    public function deleteDepartments($id = null, $data = [], $segments = [])
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteDepartment($id));
    }

    private function genericRead(string $resource, $id, array $data)
    {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        return $this->handleResponse($this->manager->getRecord($resource, $id));
    }

    private function genericWrite(string $method, string $resource, array $data, $id = null)
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->{$method}($resource, $data, $id));
    }

    private function genericDelete(string $resource, $id)
    {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        return $this->handleResponse($this->manager->deleteRecord($resource, $id));
    }

    private function userId()
    {
        return $this->user['user_id'] ?? $this->user['id'] ?? null;
    }
}
