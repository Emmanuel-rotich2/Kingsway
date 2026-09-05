<?php

/**
 * Public data helpers — fetches school content for public pages.
 * Only queries publicly-safe data (no student/staff PII).
 *
 * Loaded eagerly via Composer "files" autoload so every process gets them.
 * The header/security side-effects that used to ship with these functions
 * live in public/layout/public_data.php (which pages must still include).
 */

function kw_db(): ?PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        \App\Config\Config::init();
        $pdo = new PDO(
            'mysql:host=' . \App\Config\Config::get('DB_HOST','127.0.0.1') .
            ';dbname=' . \App\Config\Config::get('DB_NAME','KingsWayAcademy') . ';charset=utf8mb4',
            \App\Config\Config::get('DB_USER','root'),
            \App\Config\Config::get('DB_PASS',''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
    } catch (\Throwable $e) { $pdo = null; }
    return $pdo;
}

/* ── News ──────────────────────────────────────────────────────────────────── */

function kw_latest_news(int $limit = 6, int $page = 1, string $category = ''): array {
    $db = kw_db();
    if (!$db) return [];
    try {
        $offset = ($page - 1) * $limit;
        $catSql = $category ? " AND category = ?" : "";
        $params = $category ? [$category, $limit, $offset] : [$limit, $offset];
        $st = $db->prepare(
            "SELECT id, title, slug, excerpt, content, category, image_url, author, views, created_at
             FROM news_articles
             WHERE status = 'published' AND deleted_at IS NULL{$catSql}
             ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $st->execute($params);
        return $st->fetchAll();
    } catch (\Throwable $e) { return []; }
}

function kw_news_count(string $category = ''): int {
    $db = kw_db();
    if (!$db) return 0;
    try {
        $catSql = $category ? " AND category = ?" : "";
        $params = $category ? [$category] : [];
        $st = $db->prepare(
            "SELECT COUNT(*) FROM news_articles WHERE status='published' AND deleted_at IS NULL{$catSql}"
        );
        $st->execute($params);
        return (int)$st->fetchColumn();
    } catch (\Throwable $e) { return 0; }
}

function kw_news_by_id(int $id): ?array {
    $db = kw_db();
    if (!$db) return null;
    try {
        $st = $db->prepare(
            "SELECT id, title, slug, excerpt, content, category, image_url, author, views, created_at
             FROM news_articles WHERE id = ? AND status='published' AND deleted_at IS NULL"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

function kw_increment_news_views(int $id): void {
    $db = kw_db();
    if (!$db) return;
    try { $db->prepare("UPDATE news_articles SET views = views + 1 WHERE id = ?")->execute([$id]); }
    catch (\Throwable $e) {}
}

/* ── Events ────────────────────────────────────────────────────────────────── */

function kw_upcoming_events(int $limit = 5): array {
    $db = kw_db();
    if (!$db) return [];
    try {
        $st = $db->prepare(
            "SELECT MAX(id) AS id, title,
                    MAX(description) AS description,
                    MIN(DATE(start_at)) AS event_date,
                    MAX(DATE(end_at)) AS end_date,
                    MAX(location) AS location,
                    MAX(type) AS category
             FROM school_events
             WHERE start_at >= CURDATE() AND status != 'cancelled'
             GROUP BY title
             ORDER BY MIN(start_at) ASC LIMIT ?"
        );
        $st->execute([$limit]);
        return $st->fetchAll();
    } catch (\Throwable $e) { return []; }
}

function kw_event_by_id(int $id): ?array {
    $db = kw_db();
    if (!$db) return null;
    try {
        $st = $db->prepare(
            "SELECT id, title, description, start_at AS event_date, end_at AS end_date, location, type AS category, status
             FROM school_events WHERE id = ?"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/* ── Academic Terms ────────────────────────────────────────────────────────── */

function kw_academic_terms(): array {
    $db = kw_db();
    if (!$db) return [];
    try {
        // Only terms a parent may actually apply for: current or upcoming.
        // Completed terms are excluded. Upcoming terms get priority (listed first)
        // so the next intake is the default choice on the admissions form.
        $st = $db->query(
            "SELECT aw.id AS admission_window_id, ayt.id, ayt.id AS target_term_id,
                    t.name, ay.year_code AS year, ay.year_name,
                    ayt.opening_date AS start_date, ayt.closing_date AS end_date,
                    t.code AS term_number, ayt.status, aw.eligible_grades,
                    aw.default_admission_category
             FROM admission_windows aw
             JOIN academic_year_terms ayt ON ayt.id = aw.academic_year_term_id
             JOIN terms t ON t.id = ayt.term_id
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE aw.status = 'open' AND aw.accepts_new_applications = 1
               AND (aw.application_open_at IS NULL OR NOW() >= aw.application_open_at)
               AND (aw.application_close_at IS NULL OR NOW() <= aw.application_close_at)
             ORDER BY aw.application_open_at ASC, ayt.opening_date ASC"
        );
        return $st->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/* ── Jobs ──────────────────────────────────────────────────────────────────── */

function kw_open_jobs(): array {
    $db = kw_db();
    if (!$db) return [];
    try {
        $st = $db->query(
            "SELECT id, title, department, job_type, location, description, requirements,
                    responsibilities, deadline, color
             FROM job_vacancies WHERE status='open' AND deadline >= CURDATE()
             ORDER BY deadline ASC"
        );
        $rows = $st->fetchAll();
        return $rows;
    } catch (\Throwable $e) { return []; }
}

function kw_job_by_id(int $id): ?array {
    $db = kw_db();
    if (!$db) return null;
    try {
        $st = $db->prepare(
            "SELECT id, title, department, job_type, location, description,
                    requirements, responsibilities, deadline, color, status
             FROM job_vacancies WHERE id = ?"
        );
        $st->execute([$id]);
        return $st->fetch() ?: null;
    } catch (\Throwable $e) { return null; }
}

/* ── School Settings / Stats ─────────────────────────────────────────────── */

function kw_school_stat(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $db = kw_db();
    if (!$db) return $cache[$key] = $default;
    try {
        $st = $db->prepare("SELECT setting_value FROM school_settings WHERE setting_key = ?");
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $cache[$key] = ($val !== false) ? $val : $default;
    } catch (\Throwable $e) { return $cache[$key] = $default; }
}

/* ── School profile (single-row identity table) ──────────────────────────── */

function kw_school_profile(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $db = kw_db();
    if (!$db) return $cache = [];
    try {
        $row = $db->query("SELECT * FROM school_profile LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        return $cache = $row ?: [];
    } catch (\Throwable $e) { return $cache = []; }
}

/* ── Live enrolment / staff counts (computed, not hand-entered) ───────────── */

/** Currently enrolled students = active rows in the students table. */
function kw_student_count(): int {
    static $cache = null;
    if ($cache !== null) return $cache;
    $db = kw_db();
    if (!$db) return $cache = 0;
    try {
        return $cache = (int)$db->query(
            "SELECT COUNT(*) FROM students WHERE status = 'active'"
        )->fetchColumn();
    } catch (\Throwable $e) { return $cache = 0; }
}

/** Currently employed staff = active rows in the staff table. */
function kw_staff_count(): int {
    static $cache = null;
    if ($cache !== null) return $cache;
    $db = kw_db();
    if (!$db) return $cache = 0;
    try {
        return $cache = (int)$db->query(
            "SELECT COUNT(*) FROM staff WHERE status = 'active'"
        )->fetchColumn();
    } catch (\Throwable $e) { return $cache = 0; }
}

function kw_grade_spaces(): array {
    // Spaces are intentionally "Available" for marketing: the school has not
    // declared per-grade capacity, so every grade shows as open. (Previously
    // this read spaces_* rows from school_settings, but those values were never
    // declared/kept current, so they've been dropped in favour of a uniform
    // "Available" state. Revisit if real capacity tracking is introduced.)
    $defaults = [
        'PP1 (Pre-Primary 1)' => ['4 – 5 years', 'Available'],
        'PP2 (Pre-Primary 2)' => ['5 – 6 years', 'Available'],
        'Grade 1'             => ['6 – 7 years', 'Available'],
        'Grade 2 – 3'         => ['7 – 9 years', 'Available'],
        'Grade 4 – 6'         => ['10 – 12 years', 'Available'],
        'Grade 7 – 9 (JSS)'   => ['12 – 15 years', 'Available'],
    ];
    return $defaults;
}

/* ── Active grades (intake grades pulled from real classes) ──────────────── */

function kw_active_grades(): array {
    $db = kw_db();
    // Canonical display order: pre-primary first, then grades low → high.
    $order = ['PP1','PP2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5',
              'Grade 6','Grade 7','Grade 8','Grade 9'];
    $found = [];
    if ($db) {
        try {
            $st = $db->query(
                "SELECT DISTINCT name FROM classes
                 WHERE status = 'active' AND name IS NOT NULL AND name <> ''
                 ORDER BY FIELD(name," . implode(',', array_map(fn($g) => $db->quote($g), $order)) . ")"
            );
            foreach ($st->fetchAll() as $r) {
                $found[] = $r['name'];
            }
        } catch (\Throwable $e) {
            // fall through to defaults below
        }
    }
    // Normalise names so they match the gradeMapping keys the application uses
    // (e.g. "Playgroup" → keep as-is; "Grade 6" already canonical).
    $normalised = [];
    foreach ($found as $name) {
        $normalised[] = in_array($name, $order, true) ? $name : $name;
    }
    // If the DB has nothing, fall back to the standard structure so the form
    // still renders (better a soft default than an empty dropdown).
    return $normalised !== [] ? $normalised : $order;
}

/* ── Content / Rich Text ─────────────────────────────────────────────────── */

function kw_content(string $key, string $default = ''): string {
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    $db = kw_db();
    if (!$db) return $cache[$key] = $default;
    try {
        $st = $db->prepare("SELECT content_value FROM school_content WHERE content_key = ?");
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $cache[$key] = ($val !== false) ? $val : $default;
    } catch (\Throwable $e) { return $cache[$key] = $default; }
}

/* ── Reusable generic table fetcher ─────────────────────────────────────── */

function kw_table(string $table, string $where = 'is_active = 1', string $order = 'display_order ASC'): array {
    $db = kw_db();
    if (!$db) return [];

    $allowedTables = [
        'school_values', 'school_history',
        'school_programs', 'school_facilities', 'gallery_items',
        'news_categories', 'news_articles', 'careers_benefits',
        'school_profile', 'school_testimonials',
    ];
    if (!in_array($table, $allowedTables, true)) {
        return [];
    }

    $cleanWhere = preg_replace('/[^a-zA-Z0-9_ .<>=!(),\'\"]/', '', $where);
    $cleanOrder = preg_replace('/[^a-zA-Z0-9_ ,\.]/', '', $order);

    try {
        $sql = "SELECT * FROM `{$table}`"
            . ($cleanWhere ? " WHERE {$cleanWhere}" : '')
            . ($cleanOrder ? " ORDER BY {$cleanOrder}" : '');
        return $db->query($sql)->fetchAll() ?: [];
    } catch (\Throwable $e) { return []; }
}

/* ── School Values ───────────────────────────────────────────────────────── */

function kw_school_values(): array {
    $rows = kw_table('school_values');
    if (!empty($rows)) return $rows;
    return [
        ['name'=>'Love',           'description'=>'Compassion and empathy in every interaction',    'icon'=>'bi-heart-fill',         'color'=>'#e91e63'],
        ['name'=>'Responsibility', 'description'=>'Accountability for our actions and learning',    'icon'=>'bi-person-check-fill',  'color'=>'#198754'],
        ['name'=>'Respect',        'description'=>'Honouring every person\'s dignity and worth',    'icon'=>'bi-hand-thumbs-up-fill','color'=>'#1976d2'],
        ['name'=>'Unity',          'description'=>'Together we achieve more, divided we fall',      'icon'=>'bi-people-fill',        'color'=>'#ff9800'],
        ['name'=>'Peace',          'description'=>'Harmony in our diverse school community',        'icon'=>'bi-peace',              'color'=>'#9c27b0'],
        ['name'=>'Patriotism',     'description'=>'Pride in our Kenyan heritage and culture',       'icon'=>'bi-flag-fill',          'color'=>'#f44336'],
        ['name'=>'Integrity',      'description'=>'Honesty and transparency in all we do',          'icon'=>'bi-shield-check-fill',  'color'=>'#00695c'],
        ['name'=>'Excellence',     'description'=>'Striving for the highest standard in all things','icon'=>'bi-star-fill',          'color'=>'#f9c80e'],
    ];
}

/* ── History Timeline ────────────────────────────────────────────────────── */

function kw_school_history(): array {
    $rows = kw_table('school_history', '', 'display_order ASC');
    if (!empty($rows)) return $rows;
    return [
        ['year'=>'2007','event_title'=>'The Calling',
         'description'=>'Local lay-ministry couple Daniel Bett and his wife, Sabina, shared a desire to provide Christian education for children in the Simotwet community near Londiani.'],
        ['year'=>'2008','event_title'=>'A School is Born',
         'description'=>'Daniel and Sabina Bett started Kingsway Preparatory School in 2008. Beginning with only a few children and limited facilities, they worked to make Christian education available to families in the community.'],
        ['year'=>'2008–2011','event_title'=>'Growth Through Faith',
         'description'=>'Despite the difficulties, Kingsway grew from its first few children into a flourishing school. It became a shining light — academic standards were high, and children were involved in Bible studies, preaching, singing groups, Pathfinder clubs, and Sabbath School programs.'],
        ['year'=>'2009–2015','event_title'=>'Community Impact',
         'description'=>'The school provided job opportunities for villagers in a community where unemployment had reached 90%. The morale of residents and the local economy continued to increase as the ministry expanded.'],
        ['year'=>'2015–Present','event_title'=>'Building for the Future',
         'description'=>'Kingsway began incorporating industries into its ministry — a farm with greenhouses, a vegetable garden, and a bakery. These industries support the school and train older students and adults for business development.'],
        ['year'=>date('Y'),'event_title'=>'Today',
         'description'=>'Kingsway Preparatory School continues to grow, now serving learners from Pre-Primary through Junior Secondary (Grade 9). The team hopes the children will leave prepared for eternity.'],
    ];
}

/* ── Leadership Hierarchy ───────────────────────────────────────────────── */

function kw_leadership(): array {
    $db = kw_db();
    if (!$db) return [];

    try {
        $ayId = (int) ($db->query("SELECT id FROM academic_years ORDER BY id DESC LIMIT 1")->fetchColumn() ?: 1);
        $stmt = $db->prepare(
            "SELECT sl.id, CONCAT(p.first_name,' ',p.last_name) AS name,
                    lp.name AS title, sl.public_bio AS bio, sl.public_photo_url AS avatar_url,
                    sl.display_order, sl.is_active,
                    ll.name AS level_name, ll.display_order AS level_order
             FROM school_leadership sl
             JOIN leadership_positions lp ON lp.id = sl.position_id
             JOIN leadership_levels ll ON ll.id = lp.level_id
             JOIN persons p ON p.id = sl.person_id
             WHERE sl.is_active = 1 AND sl.academic_year_id = ?
               AND p.data_scope = 'live'
               AND NOT EXISTS (
                   SELECT 1 FROM users u
                   WHERE u.person_id = sl.person_id AND u.is_test_user = 1
               )
             ORDER BY ll.display_order, sl.display_order"
        );
        $stmt->execute([$ayId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    } catch (\Throwable $e) { return []; }
}

/* ── Academic Programs ───────────────────────────────────────────────────── */

function kw_programs(): array {
    $rows = kw_table('school_programs');
    if (!empty($rows)) return $rows;
    return [
        ['name'=>'Pre-Primary (ECD)',  'level_range'=>'PP1–PP2 (Ages 4–5)',  'icon'=>'bi-emoji-smile-fill','color'=>'#198754','description'=>'Play-based learning, phonics, number recognition, social skills.','anchor'=>'early-years'],
        ['name'=>'Lower Primary',       'level_range'=>'Grade 1–3 (Ages 6–8)','icon'=>'bi-book-open-fill', 'color'=>'#1976d2','description'=>'Literacy, Mathematical Activities, Environmental Activities, Creative Arts.','anchor'=>'academics'],
        ['name'=>'Upper Primary',       'level_range'=>'Grade 4–6 (Ages 9–11)','icon'=>'bi-pencil-fill',   'color'=>'#f9c80e','description'=>'English, Kiswahili, Mathematics, Science & Technology, Social Studies.','anchor'=>'academics'],
        ['name'=>'Junior Secondary',    'level_range'=>'Grade 7–9 (Ages 12–14)','icon'=>'bi-mortarboard-fill','color'=>'#e91e63','description'=>'Integrated Science, Health Education, Business Studies, KJSEA prep.','anchor'=>'academics'],
        ['name'=>'Boarding',            'level_range'=>'All Grades',           'icon'=>'bi-house-heart-fill','color'=>'#9c27b0','description'=>'Full boarding with trained houseparents, nutritious meals, evening preps.','anchor'=>'boarding'],
        ['name'=>'Sports & Co-Curricular','level_range'=>'All Grades',         'icon'=>'bi-trophy-fill',   'color'=>'#ff9800','description'=>'Football, athletics, music, drama, clubs and leadership programs.','anchor'=>'co-curricular'],
    ];
}

/* ── Facilities ──────────────────────────────────────────────────────────── */

function kw_facilities(): array {
    $rows = kw_table('school_facilities');
    if (!empty($rows)) return $rows;
    return [
        ['icon'=>'bi-building',   'name'=>'Modern Classrooms', 'description'=>'32 well-ventilated, furnished classrooms equipped for CBC learning.'],
        ['icon'=>'bi-laptop',     'name'=>'Computer Lab',      'description'=>'40-station lab with high-speed internet and CBC educational software.'],
        ['icon'=>'bi-book',       'name'=>'School Library',    'description'=>'Over 12,000 books including CBC-aligned reference materials.'],
        ['icon'=>'bi-heart-pulse','name'=>'Sick Bay',          'description'=>'Fully equipped sick bay managed by a qualified nurse.'],
        ['icon'=>'bi-house-door', 'name'=>'Dormitories',       'description'=>'Separate boys and girls dormitories with houseparents on duty 24/7.'],
        ['icon'=>'bi-cup-hot',    'name'=>'Dining Hall',       'description'=>'Spacious dining hall serving three balanced meals daily.'],
        ['icon'=>'bi-flag',       'name'=>'Sports Grounds',    'description'=>'Full-size football pitch, basketball, netball, and athletics track.'],
        ['icon'=>'bi-music-note', 'name'=>'Music & Arts Room', 'description'=>'Dedicated room with instruments for lessons and choir practice.'],
        ['icon'=>'bi-flask',      'name'=>'Science Lab',       'description'=>'Equipped laboratory for Grade 7–9 integrated science experiments.'],
    ];
}

/* ── Department Contacts (from normalized departments + contact_directory) ─── */

function kw_departments(): array {
    $db = kw_db();
    $rows = [];
    if ($db) {
        try {
            $stmt = $db->query(
                "SELECT d.id, d.code, d.name, d.description,
                        cd.email, cd.phone
                 FROM departments d
                 JOIN contact_directory cd
                        ON cd.contact_type = 'department' AND cd.department_id = d.id
                 WHERE d.status = 'active'
                 ORDER BY d.id ASC"
            );
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
            foreach ($stmt->fetchAll() as $r) {
                $rows[] = [
                    'icon'        => $iconByCode[$r['code']] ?? 'bi-diagram-3',
                    'color'       => $colorByCode[$r['code']] ?? '#198754',
                    'name'        => $r['name'],
                    'description' => $r['description'],
                    'email'       => $r['email'],
                    'phone'       => $r['phone'],
                ];
            }
        } catch (\Throwable $e) {
            $rows = [];
        }
    }
    if (!empty($rows)) return $rows;
    return [
        ['icon'=>'bi-person-check-fill','color'=>'#198754','name'=>'Admissions Office','description'=>'New applications, transfers, placement tests','email'=>'admissions@kingswaypreparatoryschool.sc.ke','phone'=>'+254 720 113 030'],
        ['icon'=>'bi-cash-coin',         'color'=>'#1976d2','name'=>'Finance & Fees',   'description'=>'Fee structure, payments, balances, receipts','email'=>'finance@kingswaypreparatoryschool.sc.ke',   'phone'=>'+254 720 113 031'],
        ['icon'=>'bi-book-fill',         'color'=>'#9c27b0','name'=>'Academic Office',  'description'=>'Results, report cards, curriculum, timetables','email'=>'academic@kingswaypreparatoryschool.sc.ke',  'phone'=>'+254 720 113 030'],
        ['icon'=>'bi-house-fill',        'color'=>'#e65100','name'=>'Boarding Office',  'description'=>'Dormitory, exeats, welfare, health matters',   'email'=>'boarding@kingswaypreparatoryschool.sc.ke',  'phone'=>'+254 720 113 031'],
    ];
}

/* ── Gallery ─────────────────────────────────────────────────────────────── */

function kw_gallery(int $limit = 6): array {
    $rows = kw_table('gallery_items');
    if (!empty($rows)) return array_slice($rows, 0, $limit);
    return [
        ['image_url'=>'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=600&q=80','caption'=>'Classroom learning','category'=>'Academic'],
        ['image_url'=>'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=600&q=80','caption'=>'Sports day',       'category'=>'Sports'],
        ['image_url'=>'https://images.unsplash.com/photo-1581472723648-909f4851d4ae?w=600&q=80','caption'=>'Computer lab',    'category'=>'Facilities'],
        ['image_url'=>'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=600&q=80','caption'=>'Library',         'category'=>'Facilities'],
        ['image_url'=>'https://images.unsplash.com/photo-1543269865-cbf427effbad?w=600&q=80','caption'=>'Parent meeting',  'category'=>'Community'],
        ['image_url'=>'https://images.unsplash.com/photo-1514320291840-2e0a9bf2a9ae?w=600&q=80','caption'=>'Arts & drama',   'category'=>'Arts'],
    ];
}

/* ── Downloads ───────────────────────────────────────────────────────────── */

function kw_downloads(): array {
    $db = kw_db();
    if (!$db) return [];

    try {
        $statement = $db->query(
            "SELECT id, title, description, file_type, file_size,
                    category, icon, color, public_token
             FROM page_downloads
             WHERE is_active = 1
               AND token_revoked_at IS NULL
               AND public_token IS NOT NULL
               AND public_token <> ''
             ORDER BY category, display_order ASC"
        );

        $grouped = [];
        $baseUrl = defined('BASE_URL')
            ? rtrim((string) BASE_URL, '/')
            : '';

        foreach ($statement->fetchAll() as $row) {
            $row['download_url'] = $baseUrl
                . '/api/download/public?token='
                . rawurlencode((string) $row['public_token']);
            unset($row['public_token']);
            $grouped[$row['category']][] = $row;
        }

        return $grouped;
    } catch (\Throwable $exception) {
        return [];
    }
}

/* ── News Categories ─────────────────────────────────────────────────────── */

function kw_news_categories(): array {
    $rows = kw_table('news_categories');
    if (!empty($rows)) {
        $result = [];
        foreach ($rows as $r) { $result[$r['name']] = $r['color']; }
        return $result;
    }
    return ['Sports'=>'#198754','Academic'=>'#1976d2','Infrastructure'=>'#e91e63','Announcement'=>'#f9a825','Arts'=>'#9c27b0','Community'=>'#00695c'];
}

/* ── Admission Process Steps ─────────────────────────────────────────────── */

function kw_admission_steps(): array {
    $rows = kw_table('admission_process_steps');
    if (!empty($rows)) return $rows;
    return [
        ['step_number'=>1,'icon'=>'bi-file-earmark-plus-fill','color'=>'#198754','title'=>'Submit Application',   'description'=>'Complete and submit the application form below with all required documents.'],
        ['step_number'=>2,'icon'=>'bi-file-check-fill',        'color'=>'#1976d2','title'=>'Document Review',     'description'=>'Our admissions team reviews the application and verifies all submitted documents.'],
        ['step_number'=>3,'icon'=>'bi-chat-dots-fill',         'color'=>'#f9c80e','title'=>'Placement Assessment','description'=>'The applicant sits a short placement test and meets with the Head Teacher.'],
        ['step_number'=>4,'icon'=>'bi-envelope-check-fill',    'color'=>'#9c27b0','title'=>'Offer Letter',        'description'=>'Successful applicants receive an official offer letter within 5 working days.'],
        ['step_number'=>5,'icon'=>'bi-cash-coin',              'color'=>'#e65100','title'=>'Fee Payment',         'description'=>'A non-refundable admission fee secures the placement. Full term fees follow.'],
        ['step_number'=>6,'icon'=>'bi-mortarboard-fill',       'color'=>'#00695c','title'=>'Orientation',         'description'=>'The student attends new-student orientation before joining class.'],
    ];
}

/* ── Careers Benefits ────────────────────────────────────────────────────── */

function kw_careers_benefits(): array {
    $rows = kw_table('careers_benefits');
    if (!empty($rows)) return $rows;
    return [
        ['icon'=>'bi-cash-coin',       'title'=>'Competitive Salary', 'description'=>'TSC-scale pay with timely disbursement and annual reviews.'],
        ['icon'=>'bi-graph-up-arrow',  'title'=>'Career Growth',      'description'=>'Funded professional development, promotions, and CPD programs.'],
        ['icon'=>'bi-house-fill',      'title'=>'Staff Housing',      'description'=>'School accommodation available for teaching staff.'],
        ['icon'=>'bi-heart-pulse',     'title'=>'Medical Cover',      'description'=>'Staff and dependants medical insurance scheme.'],
        ['icon'=>'bi-calendar2-check', 'title'=>'Work-Life Balance',  'description'=>'Generous leave entitlement and a supportive management team.'],
    ];
}

/* ── Demo/fallback data ──────────────────────────────────────────────────────── */

/* ── Category → Unsplash photo ID map (used as fallbacks for articles with no image) ── */
function kw_category_image(string $category, int $w = 800): string {
    $map = [
        'Sports'         => 'photo-1571019614242-c5c5dee9f50b',
        'Academic'       => 'photo-1503676260728-1c00da094a0b',
        'Infrastructure' => 'photo-1581472723648-909f4851d4ae',
        'Announcement'   => 'photo-1543269865-cbf427effbad',
        'Arts'           => 'photo-1514320291840-2e0a9bf2a9ae',
        'Community'      => 'photo-1488521787991-ed7bbaae773c',
    ];
    $id = $map[$category] ?? 'photo-1503676260728-1c00da094a0b';
    return "https://images.unsplash.com/{$id}?w={$w}&q=80";
}
