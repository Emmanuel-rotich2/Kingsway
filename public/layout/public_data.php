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

function kw_latest_news(int $limit = 6): array {
    $db = kw_db();
    if (!$db) return kw_demo_news($limit);
    try {
        $st = $db->prepare(
            "SELECT id, title, content, created_at, category
             FROM announcements_bulletin
             WHERE status = 'active'
             ORDER BY created_at DESC LIMIT ?"
        );
        $st->execute([$limit]);
        $rows = $st->fetchAll();
        return $rows ?: kw_demo_news($limit);
    } catch (\Throwable $e) { return kw_demo_news($limit); }
}

function kw_upcoming_events(int $limit = 5): array {
    $db = kw_db();
    if (!$db) return kw_demo_events($limit);
    try {
        $st = $db->prepare(
            "SELECT id, title, description, event_date, event_time, location, category
             FROM school_events
             WHERE event_date >= CURDATE()
             ORDER BY event_date ASC LIMIT ?"
        );
        $st->execute([$limit]);
        $rows = $st->fetchAll();
        return $rows ?: kw_demo_events($limit);
    } catch (\Throwable $e) { return kw_demo_events($limit); }
}

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

/* ── Demo/fallback data ──────────────────────────────────────────────────── */
function kw_demo_news(int $limit): array {
    $items = [
        ['id'=>1,'title'=>'Term 2 Sports Day Highlights','content'=>'Students competed in track & field, football, and netball. Our Grade 6 team won the regional championship.','created_at'=>date('Y-m-d',strtotime('-2 days')),'category'=>'Sports'],
        ['id'=>2,'title'=>'Grade 9 KJSEA Preparation Workshop','content'=>'Teachers hosted an intensive revision workshop covering Mathematics, English, and Integrated Science.','created_at'=>date('Y-m-d',strtotime('-5 days')),'category'=>'Academic'],
        ['id'=>3,'title'=>'New Computer Lab Officially Opened','content'=>'The school officially opened its state-of-the-art computer lab equipped with 40 modern workstations.','created_at'=>date('Y-m-d',strtotime('-8 days')),'category'=>'Infrastructure'],
        ['id'=>4,'title'=>'Parent-Teacher Meeting — Term 2 Results','content'=>'Parents are invited to attend the feedback session for Term 2 academic performance reviews.','created_at'=>date('Y-m-d',strtotime('-12 days')),'category'=>'Announcement'],
        ['id'=>5,'title'=>'Music & Drama Festival Winners','content'=>'Kingsway Prep won gold at the Sub-County Music Festival in both choral verse and solo categories.','created_at'=>date('Y-m-d',strtotime('-15 days')),'category'=>'Arts'],
        ['id'=>6,'title'=>'School Library Expansion Complete','content'=>'Over 2,000 new books added to the library, including CBC-aligned reference materials and storybooks.','created_at'=>date('Y-m-d',strtotime('-20 days')),'category'=>'Announcement'],
    ];
    return array_slice($items, 0, $limit);
}

function kw_demo_events(int $limit): array {
    $year = date('Y');
    $items = [
        ['id'=>1,'title'=>'End of Term 2 Examinations','description'=>'All classes sit for end-of-term examinations.','event_date'=>"$year-08-10",'event_time'=>'08:00:00','location'=>'All Classrooms','category'=>'Academic'],
        ['id'=>2,'title'=>'Annual Prize-Giving Day','description'=>'Celebrating excellence in academics, sports, and co-curricular activities.','event_date'=>"$year-08-17",'event_time'=>'10:00:00','location'=>'School Assembly Ground','category'=>'Ceremony'],
        ['id'=>3,'title'=>'Term 3 Opening Day','description'=>'Students report back for Term 3. Boarding students to arrive by 4 PM.','event_date'=>"$year-09-02",'event_time'=>'07:30:00','location'=>'School Gate','category'=>'Academic'],
        ['id'=>4,'title'=>'Parent-Teacher Conference','description'=>'Review of Term 2 performance and Term 3 targets.','event_date'=>"$year-09-14",'event_time'=>'09:00:00','location'=>'School Hall','category'=>'Meeting'],
        ['id'=>5,'title'=>'Inter-Schools Athletics','description'=>'Regional track and field competition hosted at Kingsway.','event_date'=>"$year-10-05",'event_time'=>'08:00:00','location'=>'Sports Ground','category'=>'Sports'],
    ];
    return array_slice($items, 0, $limit);
}
