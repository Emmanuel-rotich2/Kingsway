<?php
namespace App\API\Controllers;

/**
 * WebsiteController — CRUD for all public website content tables.
 * Endpoint: /api/website/{resource}
 *
 * Resources handled:
 *   news        → news_articles
 *   events      → school_events
 *   gallery     → gallery_items
 *   downloads   → page_downloads
 *   jobs        → job_vacancies
 *   settings    → school_settings
 *   content     → school_content
 *   applications→ admission_applications (read-only)
 *   inquiries   → contact_inquiries (read-only)
 *   stats       → aggregate counts for dashboard
 *   categories  → news_categories
 *   leadership  → leadership_team
 *   programs    → school_programs
 *   facilities  → school_facilities
 *   history     → school_history
 *   values      → school_values
 *   departments → department_contacts
 */
class WebsiteController extends BaseController
{
    public function __construct() {
        parent::__construct();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PERMISSION HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function canManage(): bool {
        $user = $this->user;
        if (!$user) return false;
        $perms = (array)($user['permissions'] ?? []);
        foreach (['website_news_manage','website_events_manage','website_gallery_manage',
                  'website_downloads_manage','website_jobs_manage','website_settings_manage',
                  'website_content_manage'] as $p) {
            if (in_array($p, $perms) || in_array(str_replace('_','.', $p), $perms)) return true;
        }
        return false;
    }

    private function hasPerm(string $perm): bool {
        $user = $this->user;
        if (!$user) return false;
        $perms = (array)($user['permissions'] ?? []);
        return in_array($perm, $perms) || in_array(str_replace('_', '.', $perm), $perms);
    }

    private function requirePerm(string $perm): ?string {
        if (!$this->hasPerm($perm) && !$this->hasPerm('website_settings_manage')) {
            return $this->forbidden('You do not have permission to perform this action.');
        }
        return null;
    }

    private function forbidden(string $msg): string {
        http_response_code(403);
        return json_encode(['status'=>'error','message'=>$msg,'data'=>null,'code'=>403]);
    }

    private function slugify(string $text): string {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim($text, '-');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATS  GET /api/website/stats
    // ─────────────────────────────────────────────────────────────────────────

    public function getStats($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $db = $this->db;
            $stats = [
                'news'         => (int)$db->query("SELECT COUNT(*) FROM news_articles WHERE deleted_at IS NULL")->fetchColumn(),
                'events'       => (int)$db->query("SELECT COUNT(*) FROM school_events")->fetchColumn(),
                'jobs'         => (int)$db->query("SELECT COUNT(*) FROM job_vacancies WHERE status='open'")->fetchColumn(),
                'gallery'      => (int)$db->query("SELECT COUNT(*) FROM gallery_items WHERE is_active=1")->fetchColumn(),
                'downloads'    => (int)$db->query("SELECT COUNT(*) FROM page_downloads WHERE is_active=1")->fetchColumn(),
                'applications' => (int)$db->query("SELECT COUNT(*) FROM admission_applications WHERE status='received'")->fetchColumn(),
                'inquiries'    => (int)$db->query("SELECT COUNT(*) FROM contact_inquiries WHERE status='new'")->fetchColumn(),
                'job_apps'     => (int)$db->query("SELECT COUNT(*) FROM job_applications WHERE status='received'")->fetchColumn(),
            ];
            return $this->success($stats, 'Website stats retrieved');
        } catch (\Throwable $e) { return $this->error('Stats failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS ARTICLES  /api/website/news
    // ─────────────────────────────────────────────────────────────────────────

    public function getNews($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $db = $this->db;
            if ($id) {
                $row = $db->query("SELECT * FROM news_articles WHERE id=? AND deleted_at IS NULL", [$id])->fetch();
                return $row ? $this->success($row) : $this->notFound('Article not found');
            }
            $cat    = $data['category'] ?? '';
            $status = $data['status']   ?? '';
            $search = $data['search']   ?? '';
            $limit  = min((int)($data['limit'] ?? 50), 200);
            $offset = (int)($data['offset'] ?? 0);
            $where  = ['deleted_at IS NULL'];
            $params = [];
            if ($cat)    { $where[] = 'category = ?';           $params[] = $cat; }
            if ($status) { $where[] = 'status = ?';             $params[] = $status; }
            if ($search) { $where[] = 'title LIKE ?';           $params[] = '%'.$search.'%'; }
            $sql = "SELECT id,title,slug,excerpt,category,image_url,author,status,views,created_at,updated_at
                    FROM news_articles WHERE ".implode(' AND ',$where)."
                    ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit; $params[] = $offset;
            $rows  = $db->query($sql, $params)->fetchAll();
            $total = (int)$db->query("SELECT COUNT(*) FROM news_articles WHERE ".implode(' AND ',$where), array_slice($params,0,-2))->fetchColumn();
            return $this->success(['items'=>$rows,'total'=>$total]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postNews($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['content'])) return $this->badRequest('Title and content are required.');
        try {
            $db   = $this->db;
            $slug = $this->slugify($data['title']);
            // Ensure unique slug
            $exists = $db->query("SELECT COUNT(*) FROM news_articles WHERE slug = ?", [$slug])->fetchColumn();
            if ($exists) $slug .= '-'.time();
            $author = ($this->user['first_name'] ?? '') . ' ' . ($this->user['last_name'] ?? '');
            $author = trim($author) ?: 'Admin';
            $db->query(
                "INSERT INTO news_articles (title,slug,excerpt,content,category,image_url,author,status) VALUES (?,?,?,?,?,?,?,?)",
                [$data['title'], $slug, $data['excerpt']??'', $data['content'], $data['category']??'Announcement',
                 $data['image_url']??null, $data['author']??$author, $data['status']??'published']
            );
            $newId = $db->lastInsertId();
            return $this->created(['id'=>$newId,'slug'=>$slug], 'Article published successfully');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putNews($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        try {
            $db = $this->db;
            $fields = []; $params = [];
            foreach (['title','excerpt','content','category','image_url','author','status'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = $data[$f]; }
            }
            if (empty($fields)) return $this->badRequest('No fields to update.');
            $params[] = $id;
            $db->query("UPDATE news_articles SET ".implode(',',$fields).",updated_at=NOW() WHERE id=?", $params);
            return $this->success(['id'=>(int)$id], 'Article updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteNews($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_news_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Article ID required.');
        try {
            $this->db->query("UPDATE news_articles SET deleted_at=NOW() WHERE id=?", [$id]);
            return $this->success(null, 'Article deleted');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // EVENTS  /api/website/events
    // ─────────────────────────────────────────────────────────────────────────

    public function getEvents($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $db = $this->db;
            if ($id) {
                $row = $db->query("SELECT * FROM school_events WHERE id=?", [$id])->fetch();
                return $row ? $this->success($row) : $this->notFound('Event not found');
            }
            $upcoming = ($data['upcoming'] ?? '') === '1';
            $where = $upcoming ? 'WHERE event_date >= CURDATE() AND status != "cancelled"' : '';
            $rows = $db->query("SELECT * FROM school_events $where ORDER BY event_date DESC LIMIT 100")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postEvents($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['event_date'])) return $this->badRequest('Title and event date are required.');
        try {
            $this->db->query(
                "INSERT INTO school_events (title,description,event_date,event_time,end_date,location,category,status) VALUES (?,?,?,?,?,?,?,?)",
                [$data['title'], $data['description']??'', $data['event_date'], $data['event_time']??null,
                 $data['end_date']??null, $data['location']??'', $data['category']??'Academic', $data['status']??'upcoming']
            );
            return $this->created(['id'=>$this->db->lastInsertId()], 'Event created');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putEvents($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        try {
            $fields = []; $params = [];
            foreach (['title','description','event_date','event_time','end_date','location','category','status'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = $data[$f]; }
            }
            if (empty($fields)) return $this->badRequest('No fields to update.');
            $params[] = $id;
            $this->db->query("UPDATE school_events SET ".implode(',',$fields).",updated_at=NOW() WHERE id=?", $params);
            return $this->success(['id'=>(int)$id], 'Event updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteEvents($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_events_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Event ID required.');
        try {
            $this->db->query("DELETE FROM school_events WHERE id=?", [$id]);
            return $this->success(null, 'Event deleted');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GALLERY  /api/website/gallery
    // ─────────────────────────────────────────────────────────────────────────

    public function getGallery($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT * FROM gallery_items ORDER BY display_order ASC, created_at DESC LIMIT 100")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postGallery($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (empty($data['image_url'])) return $this->badRequest('Image URL is required.');
        try {
            $maxOrder = (int)$this->db->query("SELECT COALESCE(MAX(display_order),0) FROM gallery_items")->fetchColumn();
            $this->db->query(
                "INSERT INTO gallery_items (image_url,caption,category,display_order,is_active) VALUES (?,?,?,?,1)",
                [$data['image_url'], $data['caption']??'', $data['category']??'General', $maxOrder+10]
            );
            return $this->created(['id'=>$this->db->lastInsertId()], 'Image added to gallery');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putGallery($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        try {
            $fields = []; $params = [];
            foreach (['image_url','caption','category','display_order','is_active'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = $data[$f]; }
            }
            if (empty($fields)) return $this->badRequest('No fields to update.');
            $params[] = $id;
            $this->db->query("UPDATE gallery_items SET ".implode(',',$fields)." WHERE id=?", $params);
            return $this->success(['id'=>(int)$id], 'Gallery item updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteGallery($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_gallery_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Gallery item ID required.');
        try {
            $this->db->query("DELETE FROM gallery_items WHERE id=?", [$id]);
            return $this->success(null, 'Image removed from gallery');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOADS  /api/website/downloads
    // ─────────────────────────────────────────────────────────────────────────

    public function getDownloads($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT * FROM page_downloads ORDER BY category, display_order ASC LIMIT 200")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postDownloads($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['file_url'])) return $this->badRequest('Title and file URL are required.');
        try {
            $max = (int)$this->db->query("SELECT COALESCE(MAX(display_order),0) FROM page_downloads WHERE category=?", [$data['category']??'General'])->fetchColumn();
            $this->db->query(
                "INSERT INTO page_downloads (title,description,file_url,file_type,file_size,category,icon,color,display_order) VALUES (?,?,?,?,?,?,?,?,?)",
                [$data['title'], $data['description']??'', $data['file_url'], $data['file_type']??'PDF',
                 $data['file_size']??'', $data['category']??'General',
                 $data['icon']??'bi-file-earmark-pdf-fill', $data['color']??'#198754', $max+10]
            );
            return $this->created(['id'=>$this->db->lastInsertId()], 'Download entry added');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putDownloads($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Download ID required.');
        try {
            $fields = []; $params = [];
            foreach (['title','description','file_url','file_type','file_size','category','icon','color','is_active'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = $data[$f]; }
            }
            if (empty($fields)) return $this->badRequest('No fields to update.');
            $params[] = $id;
            $this->db->query("UPDATE page_downloads SET ".implode(',',$fields).",updated_at=NOW() WHERE id=?", $params);
            return $this->success(['id'=>(int)$id], 'Download updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteDownloads($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_downloads_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Download ID required.');
        try {
            $this->db->query("UPDATE page_downloads SET is_active=0, updated_at=NOW() WHERE id=?", [$id]);
            return $this->success(null, 'Download removed');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB VACANCIES  /api/website/jobs
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobs($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $db = $this->db;
            if ($id) {
                $row = $db->query("SELECT * FROM job_vacancies WHERE id=?", [$id])->fetch();
                return $row ? $this->success($row) : $this->notFound('Job not found');
            }
            $rows = $db->query("SELECT * FROM job_vacancies ORDER BY created_at DESC LIMIT 100")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postJobs($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (empty($data['title']) || empty($data['deadline'])) return $this->badRequest('Title and deadline are required.');
        try {
            $req  = is_array($data['requirements']??null)    ? json_encode($data['requirements'])    : ($data['requirements']??'[]');
            $resp = is_array($data['responsibilities']??null) ? json_encode($data['responsibilities']) : ($data['responsibilities']??'[]');
            $this->db->query(
                "INSERT INTO job_vacancies (title,department,job_type,location,description,requirements,responsibilities,deadline,color,status) VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$data['title'], $data['department']??'Teaching', $data['job_type']??'Full-Time',
                 $data['location']??'Londiani Campus', $data['description']??'',
                 $req, $resp, $data['deadline'], $data['color']??'#198754', $data['status']??'open']
            );
            return $this->created(['id'=>$this->db->lastInsertId()], 'Job vacancy created');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putJobs($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        try {
            $fields = []; $params = [];
            foreach (['title','department','job_type','location','description','deadline','color','status'] as $f) {
                if (isset($data[$f])) { $fields[] = "$f=?"; $params[] = $data[$f]; }
            }
            foreach (['requirements','responsibilities'] as $f) {
                if (isset($data[$f])) {
                    $fields[] = "$f=?";
                    $params[] = is_array($data[$f]) ? json_encode($data[$f]) : $data[$f];
                }
            }
            if (empty($fields)) return $this->badRequest('No fields to update.');
            $params[] = $id;
            $this->db->query("UPDATE job_vacancies SET ".implode(',',$fields).",updated_at=NOW() WHERE id=?", $params);
            return $this->success(['id'=>(int)$id], 'Job updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteJobs($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_jobs_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Job ID required.');
        try {
            $this->db->query("UPDATE job_vacancies SET status='closed', updated_at=NOW() WHERE id=?", [$id]);
            return $this->success(null, 'Job vacancy closed');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SETTINGS  /api/website/settings
    // ─────────────────────────────────────────────────────────────────────────

    public function getSettings($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT id, setting_key, setting_value, label FROM school_settings ORDER BY setting_key")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putSettings($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_settings_manage');
        if ($guard) return $guard;
        if (empty($data['key'])) return $this->badRequest('Setting key is required.');
        try {
            $this->db->query(
                "INSERT INTO school_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [$data['key'], $data['value']??'']
            );
            return $this->success(null, 'Setting saved');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTENT BLOCKS  /api/website/content
    // ─────────────────────────────────────────────────────────────────────────

    public function getContent($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT * FROM school_content ORDER BY content_key")->fetchAll();
            // Also include leadership, programs, facilities, history, values
            $extra = [
                'leadership'  => $this->db->query("SELECT * FROM leadership_team WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'programs'    => $this->db->query("SELECT * FROM school_programs WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'facilities'  => $this->db->query("SELECT * FROM school_facilities WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'history'     => $this->db->query("SELECT * FROM school_history ORDER BY display_order")->fetchAll(),
                'values'      => $this->db->query("SELECT * FROM school_values WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'departments' => $this->db->query("SELECT * FROM department_contacts WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'categories'  => $this->db->query("SELECT * FROM news_categories WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'ad_steps'    => $this->db->query("SELECT * FROM admission_process_steps WHERE is_active=1 ORDER BY display_order")->fetchAll(),
                'benefits'    => $this->db->query("SELECT * FROM careers_benefits WHERE is_active=1 ORDER BY display_order")->fetchAll(),
            ];
            return $this->success(['blocks'=>$rows, 'sections'=>$extra]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putContent($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['key'])) return $this->badRequest('Content key is required.');
        try {
            $this->db->query(
                "INSERT INTO school_content (content_key, content_value) VALUES (?,?) ON DUPLICATE KEY UPDATE content_value=VALUES(content_value)",
                [$data['key'], $data['value']??'']
            );
            return $this->success(null, 'Content updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GENERIC TABLE CRUD  /api/website/table-name  (leadership, programs, etc.)
    // ─────────────────────────────────────────────────────────────────────────

    private $allowedTables = [
        'leadership'  => ['leadership_team',     ['name','title','bio','avatar_url','avatar_color','email','display_order','is_active']],
        'programs'    => ['school_programs',     ['name','level_range','icon','color','description','anchor','display_order','is_active']],
        'facilities'  => ['school_facilities',   ['icon','name','description','display_order','is_active']],
        'history'     => ['school_history',      ['year','event_title','description','display_order']],
        'values'      => ['school_values',       ['name','description','icon','color','display_order','is_active']],
        'departments' => ['department_contacts', ['icon','color','name','description','email','phone','display_order','is_active']],
        'categories'  => ['news_categories',     ['name','slug','color','display_order','is_active']],
        'steps'       => ['admission_process_steps',['step_number','icon','color','title','description','display_order','is_active']],
        'benefits'    => ['careers_benefits',    ['icon','title','description','display_order','is_active']],
    ];

    public function get($id = null, $data = [], $segments = []) {
        // Fallback for /api/website (no resource) → return stats
        return $this->getStats($id, $data, $segments);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // APPLICATIONS (read-only)  /api/website/applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getApplications($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        try {
            $status = $data['status'] ?? '';
            $where  = $status ? 'WHERE status = ?' : '';
            $params = $status ? [$status] : [];
            $rows = $this->db->query("SELECT id,child_full_name,grade_applying,parent_name,parent_phone,parent_email,boarding_preference,preferred_start,status,application_ref,created_at FROM admission_applications $where ORDER BY created_at DESC LIMIT 200", $params)->fetchAll();
            $total = (int)$this->db->query("SELECT COUNT(*) FROM admission_applications $where", $params)->fetchColumn();
            return $this->success(['items'=>$rows,'total'=>$total]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putApplications($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Application ID required.');
        try {
            if (!empty($data['status'])) {
                $this->db->query("UPDATE admission_applications SET status=?, updated_at=NOW() WHERE id=?", [$data['status'], $id]);
            }
            return $this->success(null, 'Application status updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // JOB APPLICATIONS (read-only)  /api/website/job-applications
    // ─────────────────────────────────────────────────────────────────────────

    public function getJobApplications($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_applications_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT id,job_title,first_name,last_name,email,phone,tsc_number,status,created_at FROM job_applications ORDER BY created_at DESC LIMIT 200")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONTACT INQUIRIES (read-only)  /api/website/inquiries
    // ─────────────────────────────────────────────────────────────────────────

    public function getInquiries($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT id,full_name,email,phone,subject,message,status,created_at FROM contact_inquiries ORDER BY created_at DESC LIMIT 200")->fetchAll();
            return $this->success(['items'=>$rows,'total'=>count($rows)]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function putInquiries($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_inquiries_view')) return $this->forbidden('Access denied.');
        if (!$id) return $this->badRequest('Inquiry ID required.');
        try {
            if (!empty($data['status'])) {
                $this->db->query("UPDATE contact_inquiries SET status=?, updated_at=NOW() WHERE id=?", [$data['status'], $id]);
            }
            return $this->success(null, 'Inquiry status updated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // NEWS CATEGORIES  /api/website/categories
    // ─────────────────────────────────────────────────────────────────────────

    public function getCategories($id = null, $data = [], $segments = []) {
        if (!$this->hasPerm('website_view')) return $this->forbidden('Access denied.');
        try {
            $rows = $this->db->query("SELECT * FROM news_categories ORDER BY display_order")->fetchAll();
            return $this->success(['items'=>$rows]);
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function postCategories($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (empty($data['name'])) return $this->badRequest('Category name required.');
        try {
            $slug = $this->slugify($data['name']);
            $max  = (int)$this->db->query("SELECT COALESCE(MAX(display_order),0) FROM news_categories")->fetchColumn();
            $this->db->query(
                "INSERT INTO news_categories (name,slug,color,display_order) VALUES (?,?,?,?)",
                [$data['name'], $slug, $data['color']??'#198754', $max+10]
            );
            return $this->created(['id'=>$this->db->lastInsertId()], 'Category added');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }

    public function deleteCategories($id = null, $data = [], $segments = []) {
        $guard = $this->requirePerm('website_content_manage');
        if ($guard) return $guard;
        if (!$id) return $this->badRequest('Category ID required.');
        try {
            $this->db->query("UPDATE news_categories SET is_active=0 WHERE id=?", [$id]);
            return $this->success(null, 'Category deactivated');
        } catch (\Throwable $e) { return $this->error('Failed: '.$e->getMessage()); }
    }
}
