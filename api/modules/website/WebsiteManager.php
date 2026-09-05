<?php
namespace App\API\Modules\website;

use App\API\Includes\BaseAPI;
use Exception;

/**
 * WebsiteManager - All DB/business logic for the public website content CRUD.
 * The controller validates auth/RBAC, reads input, delegates here, and responds.
 *
 * Tables: news_articles, school_events, gallery_items, page_downloads,
 * job_vacancies, school_settings, school_content, admission_applications,
 * job_applications, contact_inquiries, news_categories plus the static
 * showcase tables (programs/facilities/history/values/departments/benefits)
 * and the leadership hierarchy (leadership_levels, leadership_positions,
 * school_leadership).
 */
class WebsiteManager extends BaseAPI
{
    private $allowedTables = [
        'programs'    => ['school_programs',       ['name','level_range','icon','color','description','anchor','display_order','is_active']],
        'facilities'  => ['school_facilities',     ['icon','name','description','display_order','is_active']],
        'history'     => ['school_history',        ['year','event_title','description','display_order']],
        'values'      => ['school_values',         ['name','description','icon','color','display_order','is_active']],
        'categories'  => ['news_categories',       ['name','slug','color','display_order','is_active']],
        'benefits'    => ['careers_benefits',      ['icon','title','description','display_order','is_active']],
        'testimonials'=> ['school_testimonials',   ['person_name','role_label','testimonial','video_url','stars','display_order','is_active']],
        'testimonials'=> ['school_testimonials',   ['person_name','role_label','testimonial','stars','display_order','is_active']],
    ];

    /**
     * Website "Departments" section — read from the existing normalized tables:
     * departments + contact_directory (contact_type='department'). Departments
     * carry no icon/colour in the DB, so those are derived in code; email/phone
     * come from the linked contact_directory row.
     */
    private function departmentSections(): array
    {
        $iconByCode = [
            'ADMISSIONS_OFFICE' => 'bi-person-check-fill',
            'FINANCE_&_FEES'    => 'bi-cash-coin',
            'ACADEMIC_OFFICE'   => 'bi-book-fill',
            'BOARDING_OFFICE'   => 'bi-house-fill',
        ];
        $colorByCode = [
            'ADMISSIONS_OFFICE' => '#198754',
            'FINANCE_&_FEES'    => '#1976d2',
            'ACADEMIC_OFFICE'   => '#9c27b0',
            'BOARDING_OFFICE'   => '#e65100',
        ];

        $stmt = $this->db->query(
            "SELECT d.id, d.code, d.name, d.description, d.status,
                    cd.email, cd.phone
             FROM departments d
             JOIN contact_directory cd
                    ON cd.contact_type = 'department' AND cd.department_id = d.id
             WHERE d.status = 'active'
             ORDER BY d.id ASC"
        );

        $sections = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $sections[] = [
                'id'            => (int) $row['id'],
                'name'          => $row['name'],
                'description'   => $row['description'],
                'email'         => $row['email'],
                'phone'         => $row['phone'],
                'icon'          => $iconByCode[$row['code']] ?? 'bi-diagram-3',
                'color'         => $colorByCode[$row['code']] ?? '#198754',
                'display_order' => (int) $row['id'],
                'is_active'     => $row['status'] === 'active' ? 1 : 0,
            ];
        }
        return $sections;
    }

    public function __construct()
    {
        parent::__construct('website');
    }

    private function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    /** Keep visual-editor markup while removing executable/untrusted HTML. */
    private function sanitizeArticleContent(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="kw-story-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $allowed = ['div','p','br','h2','h3','h4','strong','b','em','i','u','ul','ol','li','blockquote','a','figure','figcaption','img','video','source'];
        $nodes = iterator_to_array($document->getElementsByTagName('*'));
        foreach ($nodes as $node) {
            if ($node->getAttribute('id') === 'kw-story-root') continue;
            if (!in_array(strtolower($node->nodeName), $allowed, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            foreach (iterator_to_array($node->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->name);
                if (!in_array($name, ['href','src','alt','title','controls','preload','class','data-story-slider'], true)) {
                    $node->removeAttribute($attribute->name);
                }
            }
            foreach (['href','src'] as $urlAttribute) {
                if (!$node->hasAttribute($urlAttribute)) continue;
                $url = trim($node->getAttribute($urlAttribute));
                if (!preg_match('#^(https?://|/|uploads/)#i', $url)) $node->removeAttribute($urlAttribute);
            }
            if ($node->hasAttribute('class') && $node->getAttribute('class') !== 'story-slider') {
                $node->removeAttribute('class');
            }
        }
        $root = $document->getElementById('kw-story-root');
        $clean = '';
        if ($root) foreach ($root->childNodes as $child) $clean .= $document->saveHTML($child);
        return trim($clean);
    }

    private function tableInfo(string $resource): ?array
    {
        return $this->allowedTables[$resource] ?? null;
    }

    private function tableColumns(string $table): array
    {
        static $cache = [];
        if (!isset($cache[$table])) {
            try {
                $stmt = $this->db->prepare("SHOW COLUMNS FROM `$table`");
                $stmt->execute();
                $cache[$table] = array_map(
                    fn($r) => $r['Field'] ?? $r['field'] ?? '',
                    $stmt->fetchAll(\PDO::FETCH_ASSOC)
                );
            } catch (\Throwable $e) {
                $cache[$table] = [];
            }
        }
        return $cache[$table];
    }

    // ───────────────────────── STATS ─────────────────────────

    public function getStats()
    {
        try {
            $stats = [
                'news'         => (int) $this->scalar("SELECT COUNT(*) FROM news_articles WHERE deleted_at IS NULL"),
                'events'       => (int) $this->scalar("SELECT COUNT(*) FROM school_events"),
                'jobs'         => (int) $this->scalar("SELECT COUNT(*) FROM job_vacancies WHERE status='open'"),
                'gallery'      => (int) $this->scalar("SELECT COUNT(*) FROM gallery_items WHERE is_active=1"),
                'downloads'    => (int) $this->scalar("SELECT COUNT(*) FROM page_downloads WHERE is_active=1"),
                'applications' => (int) $this->scalar("SELECT COUNT(*) FROM admission_applications WHERE status='received'"),
                'inquiries'    => (int) $this->scalar("SELECT COUNT(*) FROM contact_inquiries WHERE status='new'"),
                'job_apps'     => (int) $this->scalar("SELECT COUNT(*) FROM job_applications WHERE status='received'"),
                // Live enrolment / staff headcounts shown on the public homepage.
                'students'     => (int) $this->scalar("SELECT COUNT(*) FROM students WHERE status='active'"),
                'staff'        => (int) $this->scalar("SELECT COUNT(*) FROM staff WHERE status='active'"),
            ];
            return $this->successResponse($stats, 'Website stats retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── OPEN TERMS ─────────────────────────

    /**
     * Intake terms the public site may offer (current or upcoming). Completed
     * terms are excluded; upcoming terms are listed first so the next intake is
     * the default choice on the admissions form. Mirrors the old kw_academic_terms().
     */
    public function getOpenTerms()
    {
        try {
            $rows = $this->db->query(
                "SELECT aw.id AS admission_window_id, ayt.id, ayt.id AS target_term_id,
                        t.name, ay.year_code AS year, ay.year_name,
                        ay.id AS academic_year_id, ayt.opening_date AS start_date,
                        ayt.closing_date AS end_date, t.code AS term_number, ayt.status,
                        aw.application_open_at, aw.application_close_at,
                        aw.eligible_grades, aw.default_admission_category
                 FROM admission_windows aw
                 JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
                 JOIN terms t ON t.id = ayt.term_id
                 JOIN academic_years ay ON ay.id = ayt.academic_year_id
                 WHERE aw.status = 'open' AND aw.accepts_new_applications = 1
                   AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
                   AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
                 ORDER BY aw.application_open_at ASC, ayt.opening_date ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)], 'Open terms retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── ACTIVE GRADES ─────────────────────────

    /**
     * Intake grades derived from real classes, in the canonical display order
     * (pre-primary first, then grades low → high). Mirrors kw_active_grades().
     * NOTE: the classes table has no status column, so every class row counts
     * (the legacy query on status='active' always failed and fell back to the
     * static $order list below).
     */
    public function getActiveGrades()
    {
        $order = ['PP1', 'PP2', 'Playgroup', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4',
                  'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'];
        try {
            $quoted = implode(',', array_map(fn($g) => $this->db->quote($g), $order));
            $stmt = $this->db->query(
                "SELECT DISTINCT name FROM classes
                 WHERE name IS NOT NULL AND name <> ''
                 ORDER BY FIELD(name,{$quoted})"
            );
            $found = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $found[] = $r['name'];
            }
            $grades = $found !== [] ? $found : $order;
            return $this->successResponse(['items' => $grades, 'total' => count($grades)], 'Active grades retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->successResponse(['items' => $order, 'total' => count($order)], 'Active grades retrieved');
        }
    }

    // ───────────────────────── NEWS ─────────────────────────

    public function getNews($id, array $data = [])
    {
        try {
            if ($id) {
                // Increment the public view counter when the caller opts in
                // (news-article.php does this via ?view=1 so a plain prefetch
                // never inflates counts).
                if (($data['view'] ?? '') === '1') {
                    $this->db->prepare("UPDATE news_articles SET views = views + 1 WHERE id = ?")
                        ->execute([(int) $id]);
                }
                $stmt = $this->db->prepare("SELECT * FROM news_articles WHERE id=? AND deleted_at IS NULL");
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row
                    ? $this->successResponse($row)
                    : $this->errorResponse('Article not found', 404);
            }

            $cat    = $data['category'] ?? '';
            $status = $data['status']   ?? 'published';
            $search = $data['search']   ?? '';
            $limit  = min((int) ($data['limit'] ?? 50), 200);
            $offset = (int) ($data['offset'] ?? 0);

            $where  = ['deleted_at IS NULL'];
            $params = [];
            if ($cat)    { $where[] = 'category = ?';    $params[] = $cat; }
            if ($status) { $where[] = 'status = ?';      $params[] = $status; }
            if ($search) { $where[] = 'title LIKE ?';    $params[] = '%'.$search.'%'; }
            $whereSql = implode(' AND ', $where);

            $stmt = $this->db->prepare(
                "SELECT id,title,slug,excerpt,content,category,image_url,author,status,views,created_at,updated_at
                 FROM news_articles WHERE $whereSql
                 ORDER BY created_at DESC LIMIT ? OFFSET ?"
            );
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM news_articles WHERE $whereSql");
            $totalStmt->execute(array_slice($params, 0, count($params)));
            $total = (int) $totalStmt->fetchColumn();

            return $this->successResponse(['items' => $rows, 'total' => $total]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createNews(array $data, string $author = 'Admin')
    {
        try {
            $slug = $this->slugify($data['title'] ?? '');
            $exists = $this->scalar("SELECT COUNT(*) FROM news_articles WHERE slug = ?", [$slug]);
            if ($exists) {
                $slug .= '-' . time();
            }
            $author = trim($data['author'] ?? $author) ?: 'Admin';

            $stmt = $this->db->prepare(
                "INSERT INTO news_articles (title,slug,excerpt,content,category,image_url,author,status)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $data['title'], $slug, $data['excerpt'] ?? '', $this->sanitizeArticleContent((string)$data['content']),
                $data['category'] ?? 'Announcement', $data['image_url'] ?? null,
                $author, $data['status'] ?? 'published',
            ]);
            $newId = $this->db->lastInsertId();
            return $this->successResponse(['id' => $newId, 'slug' => $slug], 'Article published successfully', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateNews($id, array $data)
    {
        try {
            $fields = [];
            $params = [];
            foreach (['title','excerpt','content','category','image_url','author','status'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = $f === 'content'
                        ? $this->sanitizeArticleContent((string)$data[$f])
                        : $data[$f];
                }
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE news_articles SET " . implode(',', $fields) . ",updated_at=NOW() WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => (int) $id], 'Article updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteNews($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE news_articles SET deleted_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Article deleted');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── EVENTS ─────────────────────────

    public function getEvents($id, array $data = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->prepare("SELECT title FROM school_events WHERE id=?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$row) return $this->errorResponse('Event not found', 404);

                $stmt2 = $this->db->prepare(
                    "SELECT MIN(id) AS id, title,
                            SUBSTRING_INDEX(GROUP_CONCAT(description ORDER BY id SEPARATOR '|||'), '|||', 1) AS description,
                            MIN(start_at) AS start_at, MAX(end_at) AS end_at,
                            SUBSTRING_INDEX(GROUP_CONCAT(type ORDER BY id SEPARATOR '|||'), '|||', 1) AS type,
                            SUBSTRING_INDEX(GROUP_CONCAT(location ORDER BY id SEPARATOR '|||'), '|||', 1) AS location,
                            SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY id SEPARATOR '|||'), '|||', 1) AS status
                     FROM school_events WHERE title = ?"
                );
                $stmt2->execute([$row['title']]);
                $event = $stmt2->fetch(\PDO::FETCH_ASSOC);
                return $event
                    ? $this->successResponse($event)
                    : $this->errorResponse('Event not found', 404);
            }

            $showAll = ($data['upcoming'] ?? '1') === '0';
            $sql = $showAll
                ? "SELECT MIN(id) AS id, title,
                          SUBSTRING_INDEX(GROUP_CONCAT(description ORDER BY id SEPARATOR '|||'), '|||', 1) AS description,
                          MIN(start_at) AS start_at, MAX(end_at) AS end_at,
                          SUBSTRING_INDEX(GROUP_CONCAT(type ORDER BY id SEPARATOR '|||'), '|||', 1) AS type,
                          SUBSTRING_INDEX(GROUP_CONCAT(location ORDER BY id SEPARATOR '|||'), '|||', 1) AS location,
                          SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY id SEPARATOR '|||'), '|||', 1) AS status
                   FROM school_events
                   GROUP BY title
                   ORDER BY MIN(start_at) DESC LIMIT 100"
                : "SELECT MIN(id) AS id, title,
                          SUBSTRING_INDEX(GROUP_CONCAT(description ORDER BY id SEPARATOR '|||'), '|||', 1) AS description,
                          MIN(start_at) AS start_at, MAX(end_at) AS end_at,
                          SUBSTRING_INDEX(GROUP_CONCAT(type ORDER BY id SEPARATOR '|||'), '|||', 1) AS type,
                          SUBSTRING_INDEX(GROUP_CONCAT(location ORDER BY id SEPARATOR '|||'), '|||', 1) AS location,
                          SUBSTRING_INDEX(GROUP_CONCAT(status ORDER BY id SEPARATOR '|||'), '|||', 1) AS status
                   FROM school_events
                   WHERE start_at >= CURDATE() AND status != 'cancelled'
                   GROUP BY title
                   ORDER BY MIN(start_at) ASC LIMIT 100";
            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createEvent(array $data)
    {
        try {
            $startAt = $data['start_at'] ?? null;
            if (!$startAt && !empty($data['event_date'])) {
                $startAt = $data['event_date'] . (($data['event_time'] ?? '') ? ' ' . $data['event_time'] : '');
            }
            $endAt = $data['end_at'] ?? $data['end_date'] ?? null;
            $type = $data['type'] ?? $data['category'] ?? 'Academic';

            $stmt = $this->db->prepare(
                "INSERT INTO school_events (title,description,start_at,end_at,type,location,status) VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $data['title'], $data['description'] ?? '', $startAt, $endAt, $type,
                $data['location'] ?? '', $data['status'] ?? 'upcoming',
            ]);
            return $this->successResponse(['id' => $this->db->lastInsertId()], 'Event created', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateEvent($id, array $data)
    {
        try {
            $fields = [];
            $params = [];
            foreach (['title','description','location','status'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = $data[$f];
                }
            }
            if (isset($data['type']) || isset($data['category'])) {
                $fields[] = "type=?";
                $params[] = $data['type'] ?? $data['category'];
            }
            if (isset($data['start_at']) || isset($data['event_date'])) {
                $startAt = $data['start_at'] ?? null;
                if (!$startAt && !empty($data['event_date'])) {
                    $startAt = $data['event_date'] . (($data['event_time'] ?? '') ? ' ' . $data['event_time'] : '');
                }
                $fields[] = "start_at=?";
                $params[] = $startAt;
            }
            if (isset($data['end_at']) || isset($data['end_date'])) {
                $fields[] = "end_at=?";
                $params[] = $data['end_at'] ?? $data['end_date'] ?? null;
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE school_events SET " . implode(',', $fields) . ",updated_at=NOW() WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => (int) $id], 'Event updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteEvent($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM school_events WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Event deleted');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── GALLERY ─────────────────────────

    public function getGallery(array $data = [])
    {
        try {
            $activeOnly = ($data['active'] ?? '1') !== '0';
            $sql = $activeOnly
                ? "SELECT * FROM gallery_items WHERE is_active=1 ORDER BY display_order ASC, created_at DESC LIMIT 100"
                : "SELECT * FROM gallery_items ORDER BY display_order ASC, created_at DESC LIMIT 100";
            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createGalleryItem(array $data)
    {
        try {
            $maxOrder = (int) $this->scalar("SELECT COALESCE(MAX(display_order),0) FROM gallery_items");
            $stmt = $this->db->prepare(
                "INSERT INTO gallery_items (image_url,caption,category,display_order,is_active) VALUES (?,?,?,?,1)"
            );
            $stmt->execute([
                $data['image_url'], $data['caption'] ?? '', $data['category'] ?? 'General', $maxOrder + 10,
            ]);
            return $this->successResponse(['id' => $this->db->lastInsertId()], 'Image added to gallery', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateGalleryItem($id, array $data)
    {
        try {
            $fields = [];
            $params = [];
            foreach (['image_url','caption','category','display_order','is_active'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = $data[$f];
                }
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE gallery_items SET " . implode(',', $fields) . " WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => (int) $id], 'Gallery item updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteGalleryItem($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM gallery_items WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Image removed from gallery');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── DOWNLOADS ─────────────────────────

    public function getDownloads(array $data = [])
    {
        try {
            $activeOnly = ($data['active'] ?? '1') !== '0';
            $sql = $activeOnly
                ? "SELECT * FROM page_downloads WHERE is_active = 1 AND token_revoked_at IS NULL ORDER BY category, display_order ASC LIMIT 200"
                : "SELECT * FROM page_downloads ORDER BY category, display_order ASC LIMIT 200";
            $rows = array_map(
                [$this->downloads(), 'normalizedPublicDocument'],
                $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC)
            );
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createDownload(array $data, array $file, $userId = null)
    {
        try {
            $stored = $this->uploadManaged($file, 'school_document', [
                'prefix' => 'school_document',
                'preferred_name' => (string) $data['title'],
            ]);
            $token = $this->downloads()->createPublicToken();

            $maxOrder = (int) $this->scalar(
                "SELECT COALESCE(MAX(display_order), 0) FROM page_downloads WHERE category = ?",
                [$data['category'] ?? 'General']
            );

            $stmt = $this->db->prepare(
                "INSERT INTO page_downloads (
                    title, description, storage_filename, public_token, original_filename,
                    mime_type, file_size_bytes, file_type, file_size, category, icon, color,
                    display_order, is_active, token_created_at, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?, NOW(), NOW())"
            );
            $stmt->execute([
                $data['title'],
                $data['description'] ?? '',
                $stored['storage_filename'],
                $token,
                $stored['original_filename'],
                $stored['mime_type'],
                $stored['file_size_bytes'],
                strtoupper(pathinfo($stored['original_filename'], PATHINFO_EXTENSION)),
                $stored['file_size'],
                $data['category'] ?? 'General',
                $data['icon'] ?? 'bi-file-earmark-pdf-fill',
                $data['color'] ?? '#198754',
                $maxOrder + 10,
                (int) ($userId ?? 0) ?: null,
            ]);

            $recordId = (int) $this->db->lastInsertId();

            return $this->successResponse([
                'id' => $recordId,
                'download_url' => $this->downloads()->publicDownloadUrl($token),
            ], 'Download entry added.', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateDownload($id, array $data, array $file, $userId = null)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM page_downloads WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $id]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$existing) {
                return $this->errorResponse('Download record not found.', 404);
            }

            $fields = [];
            $params = [];

            foreach (['title','description','category','icon','color','is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            $hasReplacement = !empty($file)
                && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if ($hasReplacement) {
                $oldPath = !empty($existing['storage_filename'])
                    ? rtrim((string) SCHOOL_ASSETS_DOCUMENTS, '/\\')
                        . DIRECTORY_SEPARATOR
                        . basename((string) $existing['storage_filename'])
                    : null;

                $stored = $this->uploadManaged($file, 'school_document', [
                    'prefix' => 'school_document',
                    'preferred_name' => (string) ($data['title'] ?? $existing['title'] ?? 'school_document'),
                    'replace_path' => $oldPath,
                ]);

                $fields = array_merge($fields, [
                    'storage_filename = ?',
                    'public_token = ?',
                    'original_filename = ?',
                    'mime_type = ?',
                    'file_size_bytes = ?',
                    'file_type = ?',
                    'file_size = ?',
                    'token_created_at = NOW()',
                    'token_revoked_at = NULL',
                ]);

                array_push(
                    $params,
                    $stored['storage_filename'],
                    $this->downloads()->createPublicToken(),
                    $stored['original_filename'],
                    $stored['mime_type'],
                    $stored['file_size_bytes'],
                    strtoupper(pathinfo($stored['original_filename'], PATHINFO_EXTENSION)),
                    $stored['file_size']
                );
            }

            if ($fields === []) {
                return $this->errorResponse('No fields to update.', 400);
            }

            $fields[] = 'updated_by = ?';
            $params[] = (int) ($userId ?? 0) ?: null;
            $params[] = (int) $id;

            $stmt = $this->db->prepare(
                "UPDATE page_downloads SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?"
            );
            $stmt->execute($params);

            return $this->successResponse(
                ['id' => (int) $id],
                $hasReplacement
                    ? 'Download file replaced and public token regenerated.'
                    : 'Download metadata updated.'
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteDownload($id, $userId = null)
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE page_downloads
                 SET is_active = 0, token_revoked_at = NOW(), updated_by = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([(int) ($userId ?? 0) ?: null, (int) $id]);
            return $this->successResponse(null, 'Download removed and public access revoked.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── JOBS ─────────────────────────

    public function getJobs($id, array $data = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->prepare(
                    "SELECT j.*, d.name AS department
                     FROM job_vacancies j
                     LEFT JOIN departments d ON d.id = j.department_id
                     WHERE j.id=?"
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row
                    ? $this->successResponse($row)
                    : $this->errorResponse('Job not found', 404);
            }

            $status = $data['status'] ?? 'open';
            $sql = $status === 'all'
                ? "SELECT j.*, d.name AS department
                   FROM job_vacancies j
                   LEFT JOIN departments d ON d.id = j.department_id
                   ORDER BY j.created_at DESC LIMIT 100"
                : "SELECT j.*, d.name AS department
                   FROM job_vacancies j
                   LEFT JOIN departments d ON d.id = j.department_id
                   WHERE j.status = 'open'
                   ORDER BY j.created_at DESC LIMIT 100";
            $rows = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createJob(array $data)
    {
        try {
            $req  = is_array($data['requirements'] ?? null)    ? json_encode($data['requirements'])    : ($data['requirements'] ?? '[]');
            $resp = is_array($data['responsibilities'] ?? null) ? json_encode($data['responsibilities']) : ($data['responsibilities'] ?? '[]');
            $departmentId = $data['department_id'] ?? $data['department'] ?? null;

            $stmt = $this->db->prepare(
                "INSERT INTO job_vacancies (title,department_id,job_type,location,description,requirements,responsibilities,deadline,color,status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $data['title'], $departmentId, $data['job_type'] ?? 'Full-Time',
                $data['location'] ?? 'Londiani, Kenya', $data['description'] ?? '',
                $req, $resp, $data['deadline'], $data['color'] ?? '#198754', $data['status'] ?? 'open',
            ]);
            return $this->successResponse(['id' => $this->db->lastInsertId()], 'Job vacancy created', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateJob($id, array $data)
    {
        try {
            $fields = [];
            $params = [];
            foreach (['title','job_type','location','description','deadline','color','status'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = $data[$f];
                }
            }
            if (isset($data['department_id']) || isset($data['department'])) {
                $fields[] = "department_id=?";
                $params[] = $data['department_id'] ?? $data['department'];
            }
            foreach (['requirements','responsibilities'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                }
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE job_vacancies SET " . implode(',', $fields) . ",updated_at=NOW() WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => (int) $id], 'Job updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function closeJob($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE job_vacancies SET status='closed', updated_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Job vacancy closed');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── SETTINGS & CONTENT ─────────────────────────

    public function getSettings()
    {
        try {
            $rows = $this->db->query("SELECT id, setting_key, setting_value, label FROM school_settings ORDER BY setting_key")->fetchAll(\PDO::FETCH_ASSOC);
            // Merge school_profile columns as virtual setting rows so public
            // pages that read keys like school_name, school_motto, etc. still work.
            $profile = $this->db->query("SELECT * FROM school_profile LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
            if ($profile) {
                $profileMap = [
                    'school_name' => $profile['school_name'] ?? '',
                    'school_code' => $profile['school_code'] ?? '',
                    'school_motto' => $profile['motto'] ?? '',
                    'school_founded_year' => $profile['established_year'] ?? '',
                    'school_phone' => $profile['phone'] ?? '',
                    'school_phone_main' => $profile['phone'] ?? '',
                    'school_phone_alt' => $profile['alternative_phone'] ?? '',
                    'school_email' => $profile['email'] ?? '',
                    'school_email_main' => $profile['email'] ?? '',
                    'school_address' => $profile['address'] ?? '',
                    'school_address_physical' => $profile['city'] ?? '',
                    'school_address_postal' => $profile['postal_code'] ?? '',
                    'school_website' => $profile['website'] ?? '',
                    'office_hours_weekday' => $profile['office_hours_weekday'] ?? '',
                    'office_hours_saturday' => $profile['office_hours_saturday'] ?? '',
                    'social_facebook' => $profile['social_facebook'] ?? '',
                    'social_twitter' => $profile['social_twitter'] ?? '',
                    'social_instagram' => $profile['social_instagram'] ?? '',
                    'social_youtube' => $profile['social_youtube'] ?? '',
                    'social_whatsapp' => $profile['social_whatsapp'] ?? '',
                    'google_maps_url' => $profile['google_maps_url'] ?? '',
                    'currency' => $profile['currency'] ?? 'KES',
                ];
                $maxId = 0;
                foreach ($rows as $r) { if ((int)$r['id'] > $maxId) $maxId = (int)$r['id']; }
                foreach ($profileMap as $key => $val) {
                    if ($val !== '' && $val !== null) {
                        $maxId++;
                        $rows[] = ['id' => $maxId, 'setting_key' => $key, 'setting_value' => (string)$val, 'label' => $key];
                    }
                }

                // ── Computed: years of excellence (auto-increments yearly) ──
                $years = ($profile['established_year'] ?? null)
                    ? (int)date('Y') - (int)$profile['established_year'] : null;
                if ($years !== null && $years >= 0) {
                    $maxId++;
                    $rows[] = ['id' => $maxId, 'setting_key' => 'stat_years', 'setting_value' => (string)$years, 'label' => 'Years of Excellence'];
                }

                // ── Computed: headteacher name from staff table ──
                $hStmt = $this->db->query(
                    "SELECT CONCAT(p.first_name,' ',p.last_name)
                     FROM staff s
                     JOIN persons p ON s.person_id = p.id
                     WHERE s.position = 'Headteacher'
                       AND s.data_scope = 'live'
                       AND p.data_scope = 'live'
                       AND NOT EXISTS (
                           SELECT 1 FROM users u
                           WHERE u.person_id = p.id AND u.is_test_user = 1
                       )
                     LIMIT 1"
                );
                $headteacher = $hStmt->fetchColumn();
                if ($headteacher) {
                    $maxId++;
                    $rows[] = ['id' => $maxId, 'setting_key' => 'headteacher_name', 'setting_value' => $headteacher, 'label' => 'Headteacher'];
                }

                // ── Computed: all school leaders from staff table ──
                $lStmt = $this->db->query(
                    "SELECT CONCAT(p.first_name,' ',p.last_name) AS name, s.position
                     FROM staff s
                     JOIN persons p ON s.person_id = p.id
                     WHERE s.position IN ('Director','Headteacher','Deputy Headteacher','School Administrator','Accountant')
                       AND s.data_scope = 'live'
                       AND p.data_scope = 'live'
                       AND NOT EXISTS (
                           SELECT 1 FROM users u
                           WHERE u.person_id = p.id AND u.is_test_user = 1
                       )
                     ORDER BY FIELD(s.position,'Director','Headteacher','Deputy Headteacher','School Administrator','Accountant')"
                );
                $leaders = $lStmt->fetchAll(\PDO::FETCH_ASSOC);
                if ($leaders) {
                    $maxId++;
                    $rows[] = ['id' => $maxId, 'setting_key' => 'school_leaders_json', 'setting_value' => json_encode($leaders), 'label' => 'School Leaders'];
                }
            }
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function saveSetting(string $key, string $value = '')
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO school_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
            );
            $stmt->execute([$key, $value]);
            return $this->successResponse(null, 'Setting saved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getContent()
    {
        try {
            $rows = $this->db->query("SELECT * FROM school_content ORDER BY content_key")->fetchAll(\PDO::FETCH_ASSOC);
            // Only include sections whose backing table actually exists, so a
            // missing/renamed table never turns this endpoint into a 500.
            $sections = [
                'programs'    => ['school_programs', 'is_active=1'],
                'facilities'  => ['school_facilities', 'is_active=1'],
                'history'     => ['school_history', ''],
                'values'      => ['school_values', 'is_active=1'],
                'categories'  => ['news_categories', 'is_active=1'],
                'benefits'    => ['careers_benefits', 'is_active=1'],
            ];
            $extra = [];
            foreach ($sections as $key => [$table, $where]) {
                if ($this->tableColumns($table) !== []) {
                    $extra[$key] = $this->allOrdered($table, $where);
                }
            }
            // Leadership: grouped by level from normalized hierarchy tables
            try {
                $ayId = (int) ($this->db->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 1);
                $lStmt = $this->db->prepare(
                    "SELECT sl.id, sl.position_id, sl.person_id, sl.staff_id, sl.student_id,
                            CONCAT(p.first_name,' ',p.last_name) AS name,
                            sl.public_photo_url AS avatar_url, sl.public_bio AS bio,
                            sl.display_order, sl.is_active,
                            lp.name AS position_name,
                            ll.id AS level_id, ll.name AS level_name, ll.display_order AS level_order,
                            s.position AS staff_position,
                            CONCAT('person/', p.id, '/leadership') AS photo_target
                     FROM school_leadership sl
                     JOIN leadership_positions lp ON lp.id = sl.position_id
                     JOIN leadership_levels ll ON ll.id = lp.level_id
                     JOIN persons p ON p.id = sl.person_id
                     LEFT JOIN staff s ON s.id = sl.staff_id
                     WHERE sl.is_active = 1 AND sl.academic_year_id = ?
                       AND p.data_scope = 'live'
                       AND (s.id IS NULL OR s.data_scope = 'live')
                       AND NOT EXISTS (
                           SELECT 1 FROM users u
                           WHERE u.person_id = sl.person_id AND u.is_test_user = 1
                       )
                     ORDER BY ll.display_order, sl.display_order"
                );
                $lStmt->execute([$ayId]);
                $allLeaders = $lStmt->fetchAll(\PDO::FETCH_ASSOC);

                // Group by level
                $grouped = [];
                foreach ($allLeaders as $row) {
                    $lvl = $row['level_name'];
                    if (!isset($grouped[$lvl])) {
                        $grouped[$lvl] = [
                            'level_id'   => (int) $row['level_id'],
                            'level_name' => $lvl,
                            'level_order'=> (int) $row['level_order'],
                            'members'    => [],
                        ];
                    }
                    $grouped[$lvl]['members'][] = $row;
                }
                $extra['leadership'] = array_values($grouped);
                $extra['leadership_positions'] = $this->allOrdered('leadership_positions', 'is_active=1');
            } catch (\Exception $e) {
                $extra['leadership'] = [];
                $extra['leadership_positions'] = [];
            }
            $extra['departments'] = $this->departmentSections();
            return $this->successResponse(['blocks' => $rows, 'sections' => $extra]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function saveContent(string $key, string $value = '')
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO school_content (content_key, content_value) VALUES (?,?) ON DUPLICATE KEY UPDATE content_value=VALUES(content_value)"
            );
            $stmt->execute([$key, $value]);
            return $this->successResponse(null, 'Content updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── LEADERSHIP HIERARCHY CRUD ─────────────────────────

    public function getLeadershipHierarchy(?int $levelId = null)
    {
        try {
            $ayId = (int) ($this->db->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 1);
            $sql = "SELECT sl.id, sl.position_id, sl.person_id, sl.staff_id, sl.student_id,
                           CONCAT(p.first_name,' ',p.last_name) AS name,
                           sl.public_photo_url AS avatar_url, sl.public_bio AS bio,
                           sl.display_order, sl.is_active,
                           lp.name AS position_name,
                           ll.id AS level_id, ll.name AS level_name, ll.display_order AS level_order,
                           s.position AS staff_position,
                           CONCAT('person/', p.id, '/leadership') AS photo_target
                    FROM school_leadership sl
                    JOIN leadership_positions lp ON lp.id = sl.position_id
                    JOIN leadership_levels ll ON ll.id = lp.level_id
                    JOIN persons p ON p.id = sl.person_id
                    LEFT JOIN staff s ON s.id = sl.staff_id
                    WHERE sl.academic_year_id = ?";
            $params = [$ayId];
            if ($levelId !== null) {
                $sql .= " AND ll.id = ?";
                $params[] = $levelId;
            }
            $sql .= " ORDER BY ll.display_order, sl.display_order";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $allLeaders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($allLeaders as $row) {
                $lvl = $row['level_name'];
                if (!isset($grouped[$lvl])) {
                    $grouped[$lvl] = [
                        'level_id'    => (int) $row['level_id'],
                        'level_name'  => $lvl,
                        'level_order' => (int) $row['level_order'],
                        'members'     => [],
                    ];
                }
                $grouped[$lvl]['members'][] = $row;
            }
            return $this->successResponse(array_values($grouped));
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getLeadershipLevels()
    {
        try {
            $rows = $this->allOrdered('leadership_levels', 'is_active=1');
            return $this->successResponse($rows);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getLeadershipPositions(?int $levelId = null)
    {
        try {
            $where = 'is_active=1';
            if ($levelId !== null) {
                $where .= " AND level_id=" . (int) $levelId;
            }
            $rows = $this->allOrdered('leadership_positions', $where);
            return $this->successResponse($rows);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createLeadershipEntry(array $data)
    {
        try {
            if (empty($data['position_id'])) {
                return $this->errorResponse('position_id is required.', 400);
            }

            // Resolve person_id: create persons record if none provided
            if (empty($data['person_id'])) {
                if (empty($data['first_name']) || empty($data['last_name'])) {
                    return $this->errorResponse('person_id or first_name + last_name is required.', 400);
                }
                $pStmt = $this->db->prepare(
                    "INSERT INTO persons (first_name, last_name) VALUES (?,?)"
                );
                $pStmt->execute([$data['first_name'], $data['last_name']]);
                $data['person_id'] = (int) $this->db->lastInsertId();
            }

            // Auto-detect current academic year
            if (empty($data['academic_year_id'])) {
                $data['academic_year_id'] = (int) ($this->db->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 1);
            }

            // Auto-fill display_order if not set
            if (empty($data['display_order'])) {
                $data['display_order'] = (int) $this->scalar(
                    "SELECT COALESCE(MAX(display_order),0) FROM school_leadership WHERE academic_year_id=?",
                    [$data['academic_year_id']]
                ) + 10;
            }

            // Default is_active
            if (!isset($data['is_active'])) {
                $data['is_active'] = 1;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO school_leadership
                    (academic_year_id, position_id, person_id, staff_id, student_id,
                     public_photo_url, public_bio, display_order, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $data['academic_year_id'],
                $data['position_id'],
                $data['person_id'],
                $data['staff_id'] ?? null,
                $data['student_id'] ?? null,
                $data['public_photo_url'] ?? null,
                $data['public_bio'] ?? null,
                $data['display_order'],
                $data['is_active'],
            ]);
            return $this->successResponse(['id' => (int) $this->db->lastInsertId()], 'Leadership entry created.', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateLeadershipEntry(int $id, array $data)
    {
        try {
            $existing = $this->db->prepare("SELECT id FROM school_leadership WHERE id=?");
            $existing->execute([$id]);
            if (!$existing->fetch()) {
                return $this->errorResponse('Leadership entry not found.', 404);
            }

            $allowed = ['position_id','person_id','staff_id','student_id','public_photo_url','public_bio','display_order','is_active'];
            $fields = [];
            $params = [];
            foreach ($allowed as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col=?";
                    $params[] = $data[$col];
                }
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $fields[] = "updated_at=NOW()";
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE school_leadership SET " . implode(',', $fields) . " WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => $id], 'Leadership entry updated.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteLeadershipEntry(int $id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM school_leadership WHERE id=?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                return $this->errorResponse('Leadership entry not found.', 404);
            }
            return $this->successResponse(null, 'Leadership entry deleted.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── APPLICATIONS / JOB APPS / INQUIRIES ─────────────────────────

    public function getApplications(array $data = [])
    {
        try {
            $status = $data['status'] ?? '';
            $where = $status ? 'WHERE status = ?' : '';
            $params = $status ? [$status] : [];

            $stmt = $this->db->prepare(
                "SELECT id, application_no, applicant_name, grade_applying_for, parent_id, status, created_at
                 FROM admission_applications $where ORDER BY created_at DESC LIMIT 200"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM admission_applications $where");
            $totalStmt->execute($params);
            $total = (int) $totalStmt->fetchColumn();

            return $this->successResponse(['items' => $rows, 'total' => $total]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateApplicationStatus($id, string $status)
    {
        try {
            $stmt = $this->db->prepare("UPDATE admission_applications SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $id]);
            return $this->successResponse(null, 'Application status updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function getJobApplications()
    {
        try {
            $rows = $this->db->query(
                "SELECT a.id, a.job_id, a.job_title, a.first_name, a.last_name, a.email, a.phone,
                        a.tsc_number, a.status, a.created_at,
                        i.id AS interview_id, i.scheduled_at AS interview_scheduled_at,
                        i.mode AS interview_mode, i.location AS interview_location,
                        i.status AS interview_status, i.score AS interview_score, i.notes AS interview_notes
                 FROM job_applications a
                 LEFT JOIN job_application_interviews i ON i.id = (
                    SELECT i2.id FROM job_application_interviews i2
                    WHERE i2.application_id = a.id ORDER BY i2.scheduled_at DESC, i2.id DESC LIMIT 1
                 )
                 ORDER BY a.created_at DESC LIMIT 200"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateJobApplicationStatus(int $id, string $status, int $actorId, ?string $notes = null)
    {
        $allowed = ['received','shortlisted','interview_scheduled','interviewed','hired','rejected'];
        if (!in_array($status, $allowed, true)) return $this->errorResponse('Invalid application status.', 422);
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('SELECT status FROM job_applications WHERE id=? FOR UPDATE');
            $q->execute([$id]); $old = $q->fetchColumn();
            if ($old === false) { $this->db->rollBack(); return $this->errorResponse('Application not found.', 404); }
            $transitions = [
                'received' => ['shortlisted', 'rejected'],
                'shortlisted' => ['interview_scheduled', 'rejected'],
                'interview_scheduled' => ['rejected'],
                'interviewed' => ['hired', 'rejected'],
                'hired' => [],
                'rejected' => [],
            ];
            if ($old !== $status && !in_array($status, $transitions[$old] ?? [], true)) {
                $this->db->rollBack();
                return $this->errorResponse(
                    "Application cannot move from {$old} to {$status}.",
                    422
                );
            }
            if ($old !== $status) {
                $this->db->prepare('UPDATE job_applications SET status=?, updated_at=NOW() WHERE id=?')->execute([$status, $id]);
                $this->db->prepare('INSERT INTO job_application_status_history (application_id,from_status,to_status,changed_by,notes) VALUES (?,?,?,?,?)')
                    ->execute([$id, $old, $status, $actorId ?: null, $notes]);
            }
            $this->db->commit();
            return $this->successResponse(['id'=>$id,'from_status'=>$old,'status'=>$status], 'Application status updated.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[WebsiteManager] '.$e->getMessage());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function scheduleJobInterview(int $applicationId, array $data, int $actorId)
    {
        if (empty($data['scheduled_at'])) return $this->errorResponse('Interview date and time are required.', 422);
        try {
            $this->db->beginTransaction();
            $q=$this->db->prepare('SELECT status FROM job_applications WHERE id=? FOR UPDATE'); $q->execute([$applicationId]); $oldStatus=$q->fetchColumn();
            if ($oldStatus === false) { $this->db->rollBack(); return $this->errorResponse('Application not found.',404); }
            if (!in_array($oldStatus, ['received', 'shortlisted'], true)) { $this->db->rollBack(); return $this->errorResponse('Only received or shortlisted applications can be scheduled.', 422); }
            $this->db->prepare('INSERT INTO job_application_interviews (application_id,scheduled_at,mode,location,interviewer_user_id,created_by) VALUES (?,?,?,?,?,?)')
                ->execute([$applicationId,$data['scheduled_at'],$data['mode']??'in_person',$data['location']??null,$data['interviewer_user_id']??null,$actorId?:null]);
            $id=(int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE job_applications SET status=\'interview_scheduled\',updated_at=NOW() WHERE id=?')->execute([$applicationId]);
            $this->db->prepare('INSERT INTO job_application_status_history (application_id,from_status,to_status,changed_by,notes) VALUES (?,?,?,?,?)')->execute([$applicationId,$oldStatus,'interview_scheduled',$actorId?:null,'Interview scheduled']);
            $this->db->commit(); return $this->successResponse(['id'=>$id,'application_id'=>$applicationId],'Interview scheduled.',201);
        } catch (\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); \App\API\Services\Logger::legacyError('[WebsiteManager] '.$e->getMessage()); return $this->errorResponse('An internal error occurred.',500); }
    }

    public function completeJobInterview(int $interviewId, array $data, int $actorId)
    {
        try {
            $this->db->beginTransaction();
            $q=$this->db->prepare('SELECT i.application_id, i.status AS interview_status, a.status AS application_status FROM job_application_interviews i JOIN job_applications a ON a.id=i.application_id WHERE i.id=? FOR UPDATE'); $q->execute([$interviewId]); $interview=$q->fetch(\PDO::FETCH_ASSOC); $applicationId=$interview['application_id'] ?? null;
            if (!$applicationId) { $this->db->rollBack(); return $this->errorResponse('Interview not found.',404); }
            if (($interview['interview_status'] ?? '') === 'completed') { $this->db->rollBack(); return $this->errorResponse('This interview has already been completed.', 422); }
            $this->db->prepare("UPDATE job_application_interviews SET status='completed',score=?,notes=?,completed_by=?,completed_at=NOW(),updated_at=NOW() WHERE id=?")
                ->execute([$data['score']??null,$data['notes']??null,$actorId?:null,$interviewId]);
            $this->db->prepare("UPDATE job_applications SET status='interviewed',updated_at=NOW() WHERE id=? AND status='interview_scheduled'")->execute([$applicationId]);
            $this->db->prepare("INSERT INTO job_application_status_history (application_id,from_status,to_status,changed_by,notes) VALUES (?, 'interview_scheduled','interviewed',?,?)")
                ->execute([$applicationId,$actorId?:null,$data['notes']??null]);
            $this->db->commit(); return $this->successResponse(['id'=>$interviewId,'application_id'=>(int)$applicationId],'Interview recorded.');
        } catch (\Throwable $e) { if($this->db->inTransaction())$this->db->rollBack(); \App\API\Services\Logger::legacyError('[WebsiteManager] '.$e->getMessage()); return $this->errorResponse('An internal error occurred.',500); }
    }

    public function getInquiries()
    {
        try {
            $rows = $this->db->query(
                "SELECT id, full_name, email, phone, subject, message, status, created_at
                 FROM contact_inquiries ORDER BY created_at DESC LIMIT 200"
            )->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateInquiryStatus($id, string $status)
    {
        try {
            $stmt = $this->db->prepare("UPDATE contact_inquiries SET status=?, updated_at=NOW() WHERE id=?");
            $stmt->execute([$status, $id]);
            return $this->successResponse(null, 'Inquiry status updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /** Queue a tracked email reply to a public contact inquiry. */
    public function replyInquiry(int $id, string $reply, ?int $senderId = null)
    {
        $stmt = $this->db->prepare("SELECT id, full_name, email, subject FROM contact_inquiries WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $inquiry = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$inquiry) return $this->errorResponse('Inquiry not found.', 404);
        if (trim($reply) === '') return $this->errorResponse('Reply body is required.', 422);
        if (empty($inquiry['email'])) return $this->errorResponse('Inquiry has no reply email address.', 422);

        $eventService = new \App\API\Services\CommunicationBusinessEventService($this->db);
        $eventId = $eventService->getOrCreate(
            'public_inquiry_reply',
            $id . ':' . hash('sha256', $reply),
            date('Y-m-d H:i:s'),
            $senderId
        );

        $threadStmt = $this->db->prepare(
            "SELECT t.id FROM communication_threads t
             JOIN communication_thread_inquiries ti ON ti.thread_id = t.id
             WHERE ti.inquiry_id = ? LIMIT 1"
        );
        $threadStmt->execute([$id]);
        $threadId = (int) ($threadStmt->fetchColumn() ?: 0);
        if (!$threadId) {
            $this->db->prepare("INSERT INTO communication_threads (thread_type, subject, created_by) VALUES ('public_inquiry', ?, ?)")
                ->execute([$inquiry['subject'], $senderId]);
            $threadId = (int) $this->db->lastInsertId();
            $this->db->prepare("INSERT INTO communication_thread_inquiries (thread_id, inquiry_id) VALUES (?, ?)")
                ->execute([$threadId, $id]);
        }
        $this->db->prepare(
            "INSERT INTO communication_thread_messages (thread_id, sender_user_id, direction, subject, body) VALUES (?, ?, 'outbound', ?, ?)"
        )->execute([$threadId, $senderId, $inquiry['subject'], $reply]);

        $platform = new \App\API\Services\CommunicationPlatformService($this->db);
        $queued = $platform->queueForContacts(
            [['user_id' => null, 'email' => $inquiry['email']]],
            'email',
            'inquiry_reply',
            [
                'inquirer_name' => $inquiry['full_name'],
                'inquiry_subject' => $inquiry['subject'] ?: 'your inquiry',
                'reply_body' => $reply,
            ],
            [
                'sender_id' => $senderId ?: 1,
                'thread_id' => $threadId,
                'business_event_id' => $eventId,
                'purpose' => 'inquiry_reply',
            ]
        );
        $this->db->prepare("UPDATE contact_inquiries SET status = 'replied', updated_at = NOW() WHERE id = ?")->execute([$id]);
        $eventService->linkInquiry($eventId, $id);
        $eventService->markProcessed($eventId);
        return $this->successResponse($queued, 'Reply queued for delivery.');
    }

    // ───────────────────────── NEWS CATEGORIES ─────────────────────────

    public function getCategories()
    {
        try {
            $rows = $this->db->query("SELECT * FROM news_categories ORDER BY display_order")->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createCategory(string $name, string $color = '#198754')
    {
        try {
            $slug = $this->slugify($name);
            $max = (int) $this->scalar("SELECT COALESCE(MAX(display_order),0) FROM news_categories");
            $stmt = $this->db->prepare(
                "INSERT INTO news_categories (name,slug,color,display_order) VALUES (?,?,?,?)"
            );
            $stmt->execute([$name, $slug, $color, $max + 10]);
            return $this->successResponse(['id' => $this->db->lastInsertId()], 'Category added', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deactivateCategory($id)
    {
        try {
            $stmt = $this->db->prepare("UPDATE news_categories SET is_active=0 WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Category deactivated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── GENERIC TABLE CRUD ─────────────────────────

    public function getRecord(string $resource, $id = null)
    {
        $info = $this->tableInfo($resource);
        if (!$info) {
            return $this->errorResponse('Unknown resource.', 404);
        }
        [$table] = $info;
        try {
            if ($id) {
                $stmt = $this->db->prepare("SELECT * FROM `$table` WHERE id=?");
                $stmt->execute([$id]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $row
                    ? $this->successResponse($row)
                    : $this->errorResponse('Record not found.', 404);
            }
            $rows = $this->db->query("SELECT * FROM `$table` ORDER BY display_order ASC, id ASC LIMIT 200")->fetchAll(\PDO::FETCH_ASSOC);
            return $this->successResponse(['items' => $rows, 'total' => count($rows)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createRecord(string $resource, array $data)
    {
        $info = $this->tableInfo($resource);
        if (!$info) {
            return $this->errorResponse('Unknown resource.', 404);
        }
        [$table, $cols] = $info;
        try {
            if (in_array('display_order', $cols, true) && !isset($data['display_order'])) {
                $max = (int) $this->scalar("SELECT COALESCE(MAX(display_order),0) FROM `$table`");
                $data['display_order'] = $max + 10;
            }
            $fields = [];
            $placeholders = [];
            $params = [];
            foreach ($cols as $c) {
                if (!array_key_exists($c, $data)) {
                    continue;
                }
                $fields[] = $c;
                $placeholders[] = '?';
                $params[] = $data[$c];
            }
            if (empty($fields)) {
                return $this->errorResponse('No writable fields provided.', 400);
            }
            $stmt = $this->db->prepare(
                "INSERT INTO `$table` (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")"
            );
            $stmt->execute($params);
            return $this->successResponse(['id' => $this->db->lastInsertId()], ucfirst($resource) . ' record created.', 201);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateRecord(string $resource, array $data, $id = null)
    {
        if (!$id) {
            return $this->errorResponse('Record ID required.', 400);
        }
        $info = $this->tableInfo($resource);
        if (!$info) {
            return $this->errorResponse('Unknown resource.', 404);
        }
        [$table, $cols] = $info;
        try {
            $fields = [];
            $params = [];
            foreach ($cols as $c) {
                if (!array_key_exists($c, $data)) {
                    continue;
                }
                $fields[] = "$c=?";
                $params[] = $data[$c];
            }
            if (empty($fields)) {
                return $this->errorResponse('No fields to update.', 400);
            }
            $allCols = $this->tableColumns($table);
            if (in_array('updated_at', $allCols, true) && !in_array('updated_at', $cols, true)) {
                $fields[] = "updated_at=NOW()";
            }
            $params[] = $id;
            $stmt = $this->db->prepare("UPDATE `$table` SET " . implode(',', $fields) . " WHERE id=?");
            $stmt->execute($params);
            return $this->successResponse(['id' => (int) $id], ucfirst($resource) . ' record updated.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteRecord(string $resource, $id = null)
    {
        if (!$id) {
            return $this->errorResponse('Record ID required.', 400);
        }
        $info = $this->tableInfo($resource);
        if (!$info) {
            return $this->errorResponse('Unknown resource.', 404);
        }
        [$table, $cols] = $info;
        try {
            if (in_array('is_active', $cols, true)) {
                $stmt = $this->db->prepare("UPDATE `$table` SET is_active=0 WHERE id=?");
                $stmt->execute([$id]);
                return $this->successResponse(null, ucfirst($resource) . ' record deactivated.');
            }
            $stmt = $this->db->prepare("DELETE FROM `$table` WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, ucfirst($resource) . ' record deleted.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── DEPARTMENTS (normalized) ─────────────────────────

    public function getDepartments($id = null)
    {
        try {
            if ($id) {
                $row = $this->departmentSectionById((int) $id);
                return $row
                    ? $this->successResponse($row)
                    : $this->errorResponse('Department not found.', 404);
            }
            $items = $this->departmentSections();
            return $this->successResponse(['items' => $items, 'total' => count($items)]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function createDepartment(array $data)
    {
        try {
            $name = trim($data['name'] ?? '');
            if ($name === '') {
                return $this->errorResponse('Department name is required.', 400);
            }
            $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', $name), 0, 20));
            $slug = $slug === '' ? 'DEPT_' . time() : $slug;

            $this->db->beginTransaction();
            $deptStmt = $this->db->prepare(
                "INSERT INTO departments (name, code, description, status) VALUES (?,?,?,'active')"
            );
            $deptStmt->execute([$name, $slug, $data['description'] ?? '']);
            $departmentId = (int) $this->db->lastInsertId();

            $contactStmt = $this->db->prepare(
                "INSERT INTO contact_directory
                 (contact_type, linked_id, name, email, phone, department_id)
                 VALUES ('department', ?, ?, ?, ?, ?)"
            );
            $contactStmt->execute([
                $departmentId, $name, $data['email'] ?? null,
                $data['phone'] ?? null, $departmentId,
            ]);
            $this->db->commit();
            return $this->successResponse(['id' => $departmentId], 'Department created.', 201);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateDepartment($id, array $data)
    {
        if (!$id) {
            return $this->errorResponse('Department ID required.', 400);
        }
        try {
            $this->db->beginTransaction();

            $deptFields = [];
            $deptParams = [];
            foreach (['name', 'description', 'status'] as $f) {
                if (array_key_exists($f, $data)) {
                    $deptFields[] = "$f=?";
                    $deptParams[] = $data[$f];
                }
            }
            if ($deptFields !== []) {
                $deptParams[] = $id;
                $this->db->prepare("UPDATE departments SET " . implode(',', $deptFields) . ",updated_at=NOW() WHERE id=?")
                    ->execute($deptParams);
            }

            $contactFields = [];
            $contactParams = [];
            foreach (['name' => 'name', 'email' => 'email', 'phone' => 'phone'] as $inputKey => $col) {
                if (array_key_exists($inputKey, $data)) {
                    $contactFields[] = "$col=?";
                    $contactParams[] = $data[$inputKey];
                }
            }
            if ($contactFields !== []) {
                $contactParams[] = $id;
                $this->db->prepare(
                    "UPDATE contact_directory SET " . implode(',', $contactFields) . " WHERE contact_type='department' AND department_id=?"
                )->execute($contactParams);
            }

            $this->db->commit();
            return $this->successResponse(['id' => (int) $id], 'Department updated.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function deleteDepartment($id)
    {
        if (!$id) {
            return $this->errorResponse('Department ID required.', 400);
        }
        try {
            $stmt = $this->db->prepare("UPDATE departments SET status='inactive',updated_at=NOW() WHERE id=?");
            $stmt->execute([$id]);
            return $this->successResponse(null, 'Department deactivated.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function departmentSectionById(int $id): ?array
    {
        foreach ($this->departmentSections() as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }
        return null;
    }

    // ───────────────────────── PUBLIC FORM WRITES ─────────────────────────

    /**
     * Open intake term tokens ("Term 1 2027") accepted on the public admissions
     * form. Derived from getOpenTerms() so there is a single source of truth for
     * the current/upcoming intake (only those terms are offered to applicants).
     */
    public function openTermTokens(): array
    {
        $result = $this->getOpenTerms();
        if (($result['success'] ?? false) !== true) {
            return [];
        }
        $tokens = [];
        foreach (($result['data']['items'] ?? []) as $row) {
            $tokens[] = trim(($row['name'] ?? '') . ' ' . ($row['year'] ?? ''));
        }
        return $tokens;
    }

    /**
     * Public job application from the careers page. The CV is stored through the
     * canonical upload service (career_cv category) — never written by the page.
     */
    public function createJobApplication(array $d, array $cvFile)
    {
        try {
            $jobId = (int) ($d['apply_job_id'] ?? 0);
            $jobTitle = 'General Application';
            if (trim((string)($d['apply_first_name'] ?? '')) === '' || trim((string)($d['apply_last_name'] ?? '')) === '' || !filter_var(trim((string)($d['apply_email'] ?? '')), FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse('First name, last name, and a valid email are required.', 422);
            }
            if ($jobId > 0) {
                $jobStmt = $this->db->prepare("SELECT title FROM job_vacancies WHERE id = ? AND status='open' AND (deadline IS NULL OR deadline >= CURDATE()) LIMIT 1");
                $jobStmt->execute([$jobId]); $title = $jobStmt->fetchColumn();
                if (!$title) return $this->errorResponse('This vacancy is no longer accepting applications.', 422);
                $jobTitle = (string)$title;
            }

            $cvFilename = null;
            if (!empty($cvFile) && ($cvFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $stored = $this->uploadManaged($cvFile, 'career_cv', [
                    'prefix' => 'candidate_cv',
                    'preferred_name' => trim(
                        ($d['apply_first_name'] ?? '') . '-' . ($d['apply_last_name'] ?? '') . '-CV'
                    ),
                ]);
                $cvFilename = (string) ($stored['storage_filename'] ?? '');
            }

            $stmt = $this->db->prepare(
                "INSERT INTO job_applications
                 (job_id,job_title,first_name,last_name,email,phone,tsc_number,cv_filename,cover_letter,ip_address)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $stmt->execute([
                $jobId ?: null,
                $jobTitle,
                trim($d['apply_first_name'] ?? ''),
                trim($d['apply_last_name'] ?? ''),
                filter_var(trim($d['apply_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null,
                trim($d['apply_phone'] ?? ''),
                trim($d['apply_tsc'] ?? ''),
                $cvFilename,
                trim($d['apply_cover'] ?? ''),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            return $this->successResponse(
                ['id' => (int) $this->db->lastInsertId()],
                'Application submitted! We will be in touch.',
                201
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Public contact-form inquiry from the contact page.
     */
    public function createInquiry(array $d)
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO contact_inquiries (full_name,email,phone,subject,message,ip_address)
                 VALUES (?,?,?,?,?,?)"
            );
            $stmt->execute([
                trim($d['cf_name'] ?? ''),
                filter_var(trim($d['cf_email'] ?? ''), FILTER_VALIDATE_EMAIL) ?: null,
                trim($d['cf_phone'] ?? ''),
                trim($d['cf_subject'] ?? ''),
                trim($d['cf_message'] ?? ''),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            return $this->successResponse(
                ['id' => (int) $this->db->lastInsertId()],
                'Thank you for your message! We will respond within 24 hours on working days.',
                201
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    /**
     * Newsletter subscription from the events/newsletter forms.
     */
    public function createSubscriber(string $email, string $name = '')
    {
        try {
            $stmt = $this->db->prepare("SELECT status FROM newsletter_subscribers WHERE email=?");
            $stmt->execute([$email]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                if ($row['status'] === 'active') {
                    return $this->successResponse(null, 'You are already subscribed!');
                }
                $this->db->prepare(
                    "UPDATE newsletter_subscribers SET status='active',unsubscribed_at=NULL WHERE email=?"
                )->execute([$email]);
                return $this->successResponse(null, 'Successfully subscribed to event alerts.');
            }

            $this->db->prepare("INSERT INTO newsletter_subscribers (email,name) VALUES (?,?)")
                ->execute([$email, $name]);
            return $this->successResponse(
                null,
                'Successfully subscribed to event alerts.',
                201
            );
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[WebsiteManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function allOrdered(string $table, string $where)
    {
        $sql = "SELECT * FROM `$table`" . ($where ? " WHERE $where" : '') . " ORDER BY display_order";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
