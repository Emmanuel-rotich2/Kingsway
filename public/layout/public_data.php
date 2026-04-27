<?php
/**
 * Public data helper — fetches school content for public pages.
 * Only queries publicly-safe data (no student/staff PII).
 */

function kw_db(): ?PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        require_once __DIR__ . '/../../config/Config.php';
        \App\Config\Config::init();
        $pdo = new PDO(
            'mysql:host=' . \App\Config\Config::get('DB_HOST','127.0.0.1') .
            ';dbname=' . \App\Config\Config::get('DB_NAME','KingsWayAcademy') . ';charset=utf8mb4',
            \App\Config\Config::get('DB_USER','root'),
            \App\Config\Config::get('DB_PASS',''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (\Throwable $e) { $pdo = null; }
    return $pdo;
}

/* ── News ──────────────────────────────────────────────────────────────────── */

function kw_latest_news(int $limit = 6, int $page = 1, string $category = ''): array {
    $db = kw_db();
    if (!$db) return kw_demo_news($limit);
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
        $rows = $st->fetchAll();
        return $rows ?: kw_demo_news($limit);
    } catch (\Throwable $e) { return kw_demo_news($limit); }
}

function kw_news_count(string $category = ''): int {
    $db = kw_db();
    if (!$db) return count(kw_demo_news(100));
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
    if (!$db) {
        foreach (kw_demo_news(100) as $n) { if ($n['id'] == $id) return $n; }
        return null;
    }
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
    if (!$db) return kw_demo_events($limit);
    try {
        $st = $db->prepare(
            "SELECT id, title, description, event_date, event_time, location, category
             FROM school_events
             WHERE event_date >= CURDATE() AND status != 'cancelled'
             ORDER BY event_date ASC LIMIT ?"
        );
        $st->execute([$limit]);
        $rows = $st->fetchAll();
        return $rows ?: kw_demo_events($limit);
    } catch (\Throwable $e) { return kw_demo_events($limit); }
}

function kw_event_by_id(int $id): ?array {
    $db = kw_db();
    if (!$db) {
        foreach (kw_demo_events(100) as $e) { if ($e['id'] == $id) return $e; }
        return null;
    }
    try {
        $st = $db->prepare(
            "SELECT id, title, description, event_date, event_time, end_date, location, category, status
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
        $st = $db->query(
            "SELECT name, start_date, end_date, term_number
             FROM academic_terms
             WHERE YEAR(start_date) >= YEAR(CURDATE())
             ORDER BY start_date ASC LIMIT 6"
        );
        return $st->fetchAll();
    } catch (\Throwable $e) { return []; }
}

/* ── Jobs ──────────────────────────────────────────────────────────────────── */

function kw_open_jobs(): array {
    $db = kw_db();
    if (!$db) return kw_demo_jobs();
    try {
        $st = $db->query(
            "SELECT id, title, department, job_type, location, description, requirements,
                    responsibilities, deadline, color
             FROM job_vacancies WHERE status='open' AND deadline >= CURDATE()
             ORDER BY deadline ASC"
        );
        $rows = $st->fetchAll();
        return $rows ?: kw_demo_jobs();
    } catch (\Throwable $e) { return kw_demo_jobs(); }
}

function kw_job_by_id(int $id): ?array {
    $db = kw_db();
    if (!$db) {
        foreach (kw_demo_jobs() as $j) { if ($j['id'] == $id) return $j; }
        return null;
    }
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

/* ── Form Handlers ─────────────────────────────────────────────────────────── */

function kw_save_contact(array $d): bool {
    $db = kw_db();
    if (!$db) return true; // fail silently to public
    try {
        $db->prepare(
            "INSERT INTO contact_inquiries (full_name,email,phone,subject,message,ip_address)
             VALUES (?,?,?,?,?,?)"
        )->execute([$d['name'],$d['email'],$d['phone']??'',$d['subject']??'',$d['message'],$d['ip']??'']);
        return true;
    } catch (\Throwable $e) { return false; }
}

function kw_save_admission_enquiry(array $d): bool {
    $db = kw_db();
    if (!$db) return true;
    try {
        $db->prepare(
            "INSERT INTO admission_enquiries (parent_name,phone,email,child_name,grade_applying,ip_address)
             VALUES (?,?,?,?,?,?)"
        )->execute([$d['parent_name'],$d['phone'],$d['email']??'',$d['child_name'],$d['grade'],$d['ip']??'']);
        return true;
    } catch (\Throwable $e) { return false; }
}

function kw_save_job_application(array $d): bool {
    $db = kw_db();
    if (!$db) return true;
    try {
        $db->prepare(
            "INSERT INTO job_applications (job_id,job_title,first_name,last_name,email,phone,tsc_number,cv_filename,cover_letter,ip_address)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $d['job_id']??null,$d['job_title'],$d['first_name'],$d['last_name'],
            $d['email'],$d['phone'],$d['tsc_number']??'',$d['cv_filename']??null,
            $d['cover_letter']??'',$d['ip']??''
        ]);
        return true;
    } catch (\Throwable $e) { return false; }
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

function kw_grade_spaces(): array {
    $db = kw_db();
    $defaults = [
        'PP1 (Pre-Primary 1)' => ['4 – 5 years', 'Limited'],
        'PP2 (Pre-Primary 2)' => ['5 – 6 years', 'Available'],
        'Grade 1'             => ['6 – 7 years', 'Available'],
        'Grade 2 – 3'         => ['7 – 9 years', 'Available'],
        'Grade 4 – 6'         => ['10 – 12 years', 'Limited'],
        'Grade 7 – 9 (JSS)'   => ['12 – 15 years', 'Limited'],
    ];
    if (!$db) return $defaults;
    try {
        $st = $db->query("SELECT setting_key, setting_value FROM school_settings WHERE setting_key LIKE 'spaces_%'");
        $rows = $st->fetchAll();
        $map = [];
        foreach ($rows as $r) { $map[$r['setting_key']] = $r['setting_value']; }
        if (!empty($map)) {
            $defaults['PP1 (Pre-Primary 1)'][1]  = $map['spaces_PP1']      ?? 'Limited';
            $defaults['PP2 (Pre-Primary 2)'][1]  = $map['spaces_PP2']      ?? 'Available';
            $defaults['Grade 1'][1]              = $map['spaces_Grade1']   ?? 'Available';
            $defaults['Grade 2 – 3'][1]          = $map['spaces_Grade2_3'] ?? 'Available';
            $defaults['Grade 4 – 6'][1]          = $map['spaces_Grade4_6'] ?? 'Limited';
            $defaults['Grade 7 – 9 (JSS)'][1]   = $map['spaces_Grade7_9'] ?? 'Limited';
        }
        return $defaults;
    } catch (\Throwable $e) { return $defaults; }
}

/* ── Full Admission Application ──────────────────────────────────────────── */

function kw_save_admission_application(array $d): string|false {
    $db = kw_db();
    $ref = 'KWA-' . strtoupper(substr(md5(uniqid()), 0, 6));
    if (!$db) return $ref;
    try {
        $db->prepare(
            "INSERT INTO admission_applications
             (child_full_name,child_dob,child_gender,child_nationality,child_prev_school,child_prev_grade,
              parent_name,parent_relationship,parent_id_number,parent_phone,parent_alt_phone,parent_email,parent_address,
              grade_applying,boarding_preference,preferred_start,referral_source,special_needs,application_ref,ip_address)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $d['child_name'], $d['child_dob'] ?: null, $d['child_gender'] ?: null,
            $d['child_nationality'] ?: 'Kenyan', $d['child_prev_school'] ?: null, $d['child_prev_grade'] ?: null,
            $d['parent_name'], $d['parent_relationship'] ?: null, $d['parent_id'] ?: null,
            $d['parent_phone'], $d['parent_alt_phone'] ?: null, $d['parent_email'] ?: null,
            $d['parent_address'] ?: null,
            $d['grade'], $d['boarding'] ?: 'day', $d['start_term'] ?: null,
            $d['referral'] ?: null, $d['special_needs'] ?: null,
            $ref, $d['ip'] ?? null
        ]);
        return $ref;
    } catch (\Throwable $e) { return false; }
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
    try {
        $sql = "SELECT * FROM `{$table}`" . ($where ? " WHERE {$where}" : '') . ($order ? " ORDER BY {$order}" : '');
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
    ];
}

/* ── History Timeline ────────────────────────────────────────────────────── */

function kw_school_history(): array {
    $rows = kw_table('school_history', '', 'display_order ASC');
    if (!empty($rows)) return $rows;
    return [
        ['year'=>'2005','event_title'=>'Foundation',            'description'=>'Kingsway Preparatory School was founded with 3 streams and 120 pupils committed to quality education in Londiani.'],
        ['year'=>'2010','event_title'=>'Growth & Recognition',  'description'=>'Enrolment surpassed 400 students. The school received its first regional award for academic excellence.'],
        ['year'=>'2015','event_title'=>'Boarding Programme',    'description'=>'Introduction of the full boarding programme. Dormitory facilities expanded for students from across the region.'],
        ['year'=>'2019','event_title'=>'CBC Transition',        'description'=>'Seamless transition to Kenya\'s Competency-Based Curriculum, positioning Kingsway as a model CBC school.'],
        ['year'=>'2022','event_title'=>'Digital Transformation','description'=>'Launch of the new 40-workstation ICT Computer Lab and introduction of smart classrooms.'],
        ['year'=>date('Y'),'event_title'=>'Today',             'description'=>'Over 1,200 students enrolled, 80+ qualified staff, and a track record of 98% KJSEA pass rates.'],
    ];
}

/* ── Leadership Team ─────────────────────────────────────────────────────── */

function kw_leadership(): array {
    $rows = kw_table('leadership_team');
    if (!empty($rows)) return $rows;
    return [
        ['name'=>'School Director',     'title'=>'School Founder & Director',    'bio'=>'20+ years in education leadership. Masters in Educational Management.',    'avatar_color'=>'#0d4f2a'],
        ['name'=>'Head Teacher',         'title'=>'Head Teacher',                 'bio'=>'B.Ed (Hons), experienced in CBC implementation and school administration.', 'avatar_color'=>'#198754'],
        ['name'=>'Deputy (Academic)',    'title'=>'Deputy Head — Academic',        'bio'=>'Oversees curriculum, lesson plans, timetabling, and academic performance.',  'avatar_color'=>'#1976d2'],
        ['name'=>'Deputy (Discipline)',  'title'=>'Deputy Head — Discipline',      'bio'=>'Manages student conduct, welfare, and community relations.',                 'avatar_color'=>'#7b1fa2'],
        ['name'=>'The Bursar',           'title'=>'School Bursar / Accountant',    'bio'=>'CPA-K certified. Manages school finances, fee collection, and budgets.',     'avatar_color'=>'#e65100'],
        ['name'=>'Admissions Officer',   'title'=>'Admissions Officer',            'bio'=>'Handles student intake, records, and parent liaison.',                       'avatar_color'=>'#00695c'],
    ];
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

/* ── Department Contacts ─────────────────────────────────────────────────── */

function kw_departments(): array {
    $rows = kw_table('department_contacts');
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
    $rows = kw_table('page_downloads');
    if (!empty($rows)) {
        $grouped = [];
        foreach ($rows as $r) { $grouped[$r['category']][] = $r; }
        return $grouped;
    }
    return [
        'Admissions' => [
            ['title'=>'Admission Application Form','file_url'=>'downloads/admission_form.pdf','file_type'=>'PDF','file_size'=>'245 KB','icon'=>'bi-file-earmark-pdf-fill','color'=>'#e91e63'],
            ['title'=>'School Prospectus',          'file_url'=>'downloads/prospectus.pdf',   'file_type'=>'PDF','file_size'=>'2.4 MB','icon'=>'bi-file-earmark-pdf-fill','color'=>'#e91e63'],
        ],
        'Academic' => [
            ['title'=>'School Calendar','file_url'=>'downloads/calendar.pdf','file_type'=>'PDF','file_size'=>'310 KB','icon'=>'bi-file-earmark-pdf-fill','color'=>'#1976d2'],
            ['title'=>'CBC Curriculum Guide','file_url'=>'downloads/cbc_guide.pdf','file_type'=>'PDF','file_size'=>'890 KB','icon'=>'bi-file-earmark-pdf-fill','color'=>'#1976d2'],
        ],
    ];
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
        ['icon'=>'bi-house-fill',      'title'=>'Staff Housing',      'description'=>'On-campus accommodation available for teaching staff.'],
        ['icon'=>'bi-heart-pulse',     'title'=>'Medical Cover',      'description'=>'Staff and dependants medical insurance scheme.'],
        ['icon'=>'bi-calendar2-check', 'title'=>'Work-Life Balance',  'description'=>'Generous leave entitlement and a supportive management team.'],
    ];
}

function kw_save_subscriber(string $email, string $name = ''): string {
    $db = kw_db();
    if (!$db) return 'ok';
    try {
        $existing = $db->prepare("SELECT status FROM newsletter_subscribers WHERE email=?");
        $existing->execute([$email]);
        $row = $existing->fetch();
        if ($row) {
            if ($row['status'] === 'active') return 'exists';
            $db->prepare("UPDATE newsletter_subscribers SET status='active',unsubscribed_at=NULL WHERE email=?")->execute([$email]);
            return 'resubscribed';
        }
        $db->prepare("INSERT INTO newsletter_subscribers (email,name) VALUES (?,?)")->execute([$email,$name]);
        return 'ok';
    } catch (\Throwable $e) { return 'ok'; }
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

function kw_demo_news(int $limit): array {
    $items = [
        ['id'=>1,'title'=>'Term 2 Sports Day — A Day of Champions','slug'=>'term-2-sports-day-a-day-of-champions','excerpt'=>'Over 400 students competed in track & field, football, and netball. Our Grade 6 relay team broke a school record.','content'=>'<p>Kingsway Preparatory School hosted its much-anticipated Term 2 Sports Day. Over 400 students competed in events ranging from sprints to football and netball tournaments. The Grade 6 relay team broke the school record.</p>','created_at'=>date('Y-m-d',strtotime('-3 days')),'category'=>'Sports','image_url'=>kw_category_image('Sports'),'author'=>'Sports Department','views'=>247],
        ['id'=>2,'title'=>'Grade 9 KJSEA Intensive Revision Workshop','slug'=>'grade-9-kjsea-intensive-revision-workshop','excerpt'=>'Our teaching staff hosted a full-day intensive revision workshop for all 87 Grade 9 students preparing for KJSEA.','content'=>'<p>The academic team organised an intensive one-day revision workshop for all Grade 9 students. Students rotated through focused sessions in Mathematics, English, Kiswahili, Integrated Science, and Social Studies.</p>','created_at'=>date('Y-m-d',strtotime('-7 days')),'category'=>'Academic','image_url'=>kw_category_image('Academic'),'author'=>'Academic Office','views'=>183],
        ['id'=>3,'title'=>'New ICT Computer Lab Officially Commissioned','slug'=>'new-ict-computer-lab-officially-commissioned','excerpt'=>'The school proudly unveils its brand-new 40-station computer lab equipped for CBC-aligned digital learning.','content'=>'<p>Kingsway Preparatory School officially commissioned its new 40-station ICT Computer Laboratory. The lab features modern workstations, high-speed internet, and CBC-aligned educational software.</p>','created_at'=>date('Y-m-d',strtotime('-10 days')),'category'=>'Infrastructure','image_url'=>kw_category_image('Infrastructure'),'author'=>'Administration','views'=>312],
        ['id'=>4,'title'=>'Term 2 Parent-Teacher Feedback Day — You Are Invited','slug'=>'term-2-parent-teacher-feedback-day-invitation','excerpt'=>'Parents and guardians are warmly invited. Report books will be handed directly to parents on the day.','content'=>'<p>Kingsway Prep will host its Term 2 Parent-Teacher Feedback Day. Class teachers will be available from 8:00 AM to 2:00 PM. Report books will be handed directly to parents.</p>','created_at'=>date('Y-m-d',strtotime('-14 days')),'category'=>'Announcement','image_url'=>kw_category_image('Announcement'),'author'=>'Administration','views'=>95],
        ['id'=>5,'title'=>'Music & Drama Club Wins Gold at Sub-County Festival','slug'=>'music-drama-club-wins-gold-sub-county-festival','excerpt'=>'Our Music and Drama Club brought home two gold trophies from the Sub-County Festival.','content'=>'<p>Kingsway Preparatory School\'s Music and Drama Club competed across four categories and came home with two gold trophies and one silver at the Sub-County Music and Cultural Festival.</p>','created_at'=>date('Y-m-d',strtotime('-18 days')),'category'=>'Arts','image_url'=>kw_category_image('Arts'),'author'=>'Arts Department','views'=>156],
        ['id'=>6,'title'=>'Library Expansion: 2,000 New Books Added','slug'=>'library-expansion-2000-new-books-added','excerpt'=>'Our library now holds over 12,000 volumes after the largest single-term acquisition in the library\'s history.','content'=>'<p>Kingsway\'s library has undergone a major expansion with 2,047 new books added, bringing the total collection to over 12,000 volumes — one of the largest primary school libraries in Kericho County.</p>','created_at'=>date('Y-m-d',strtotime('-22 days')),'category'=>'Announcement','image_url'=>'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=800&q=80','author'=>'Library Department','views'=>88],
        ['id'=>7,'title'=>'Grade 8 Science Fair: Innovation at Its Best','slug'=>'grade-8-science-fair-innovation-at-its-best','excerpt'=>'Grade 8 students showcased remarkable projects from solar-powered water purifiers to biodegradable plastics.','content'=>'<p>The annual Grade 8 Science Fair showcased 24 projects covering renewable energy, public health, and food technology. First place went to a solar-powered water purifier built from locally sourced materials.</p>','created_at'=>date('Y-m-d',strtotime('-28 days')),'category'=>'Academic','image_url'=>'https://images.unsplash.com/photo-1532094349884-543559fee3af?w=800&q=80','author'=>'Academic Office','views'=>134],
        ['id'=>8,'title'=>'Football Team Crowned Sub-County Champions','slug'=>'football-team-crowned-sub-county-champions','excerpt'=>'Our Grade 7-9 football team goes through the season unbeaten — 10 wins, 2 draws, 38 goals scored.','content'=>'<p>Kingsway Preparatory School\'s junior football team has been crowned champions of the Kericho Sub-County Inter-Schools Football League, going through the entire season unbeaten.</p>','created_at'=>date('Y-m-d',strtotime('-35 days')),'category'=>'Sports','image_url'=>'https://images.unsplash.com/photo-1560272564-c83b66b1ad12?w=800&q=80','author'=>'Sports Department','views'=>201],
    ];
    return array_slice($items, 0, $limit);
}

function kw_demo_events(int $limit): array {
    $year = date('Y');
    $items = [
        ['id'=>1,'title'=>'End of Term 2 Examinations Begin','description'=>'All classes sit their end-of-term written examinations.','event_date'=>"$year-08-10",'event_time'=>'07:30:00','location'=>'All Classrooms','category'=>'Academic'],
        ['id'=>2,'title'=>'Term 2 Parent-Teacher Feedback Day','description'=>'Parents meet class teachers to review Term 2 results. Report books handed directly to parents.','event_date'=>"$year-08-17",'event_time'=>'08:00:00','location'=>'All Classrooms & School Hall','category'=>'Meeting'],
        ['id'=>3,'title'=>'Annual Prize-Giving & Awards Ceremony','description'=>'Celebrating excellence in academics, sports, arts, and character development.','event_date'=>"$year-08-24",'event_time'=>'10:00:00','location'=>'School Assembly Ground','category'=>'Ceremony'],
        ['id'=>4,'title'=>'Term 2 Closing Day','description'=>'Last day of Term 2. Boarding students collected by 4:00 PM.','event_date'=>"$year-08-31",'event_time'=>'12:00:00','location'=>'School Campus','category'=>'Academic'],
        ['id'=>5,'title'=>'Term 3 Opening Day','description'=>'Students report back for Term 3. Day scholars by 7:30 AM.','event_date'=>"$year-09-15",'event_time'=>'07:30:00','location'=>'School Gates','category'=>'Academic'],
    ];
    return array_slice($items, 0, $limit);
}

function kw_demo_jobs(): array {
    return [
        ['id'=>1,'title'=>'Class Teacher — Grade 4','department'=>'Teaching','job_type'=>'Full-Time','location'=>'Londiani Campus','description'=>'We are looking for a dedicated Grade 4 class teacher with strong CBC implementation skills.','requirements'=>'["P1 or B.Ed (Primary Education)","TSC Registration (mandatory)","Minimum 2 years teaching experience"]','responsibilities'=>'["Deliver CBC-aligned lessons across all learning areas","Maintain class registers and portfolios","Communicate regularly with parents"]','deadline'=>date('Y-m-d',strtotime('+30 days')),'color'=>'#198754'],
        ['id'=>2,'title'=>'Mathematics & Science Teacher (Grade 7–9)','department'=>'Teaching','job_type'=>'Full-Time','location'=>'Londiani Campus','description'=>'Seeking an experienced JSS Mathematics and Integrated Science teacher to prepare Grade 9 students for KJSEA.','requirements'=>'["B.Ed (Science/Mathematics)","TSC Registration","3 years JSS experience"]','responsibilities'=>'["Teach Mathematics and Integrated Science to Grade 7–9","Administer SBA assessments","Provide KJSEA revision"]','deadline'=>date('Y-m-d',strtotime('+25 days')),'color'=>'#1976d2'],
        ['id'=>3,'title'=>'School Nurse (Residential)','department'=>'Health & Welfare','job_type'=>'Full-Time','location'=>'Londiani Campus','description'=>'Qualified nurse to manage the school sick bay and student health records. Residential position.','requirements'=>'["Diploma or Degree in Nursing","KNC registration","First Aid certification"]','responsibilities'=>'["Manage sick bay and health records","Administer first aid","Coordinate hospital referrals"]','deadline'=>date('Y-m-d',strtotime('+20 days')),'color'=>'#e91e63'],
    ];
}
