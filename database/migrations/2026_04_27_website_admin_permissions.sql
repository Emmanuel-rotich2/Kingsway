-- =============================================================================
-- Migration: 2026_04_27_website_admin_permissions.sql
-- Description: Grant website management permissions to admin, headteacher,
--              deputy heads, and talent/sports teachers.
--              Creates routes, sidebar items, and permissions.
-- Run: /opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/2026_04_27_website_admin_permissions.sql
-- =============================================================================

USE KingsWayAcademy;

-- ── 1. Permissions ────────────────────────────────────────────────────────────
INSERT IGNORE INTO `permissions` (`id`, `code`, `module`, `action`, `description`) VALUES
(100177, 'website_view',               'website', 'view',    'View website management pages'),
(100178, 'website_news_manage',        'website', 'manage',  'Create, edit and delete news articles'),
(100179, 'website_events_manage',      'website', 'manage',  'Create, edit and delete school events'),
(100180, 'website_gallery_manage',     'website', 'manage',  'Add and remove gallery images'),
(100181, 'website_downloads_manage',   'website', 'manage',  'Add and remove downloadable files'),
(100182, 'website_jobs_manage',        'website', 'manage',  'Post, edit and close job vacancies'),
(100183, 'website_settings_manage',    'website', 'manage',  'Edit school settings (phone, address, stats)'),
(100184, 'website_content_manage',     'website', 'manage',  'Edit page content (mission, vision, programs)'),
(100185, 'website_applications_view',  'website', 'view',    'View admission and job applications from the public site'),
(100186, 'website_inquiries_view',     'website', 'view',    'View contact form enquiries from the public site');

-- ── 2. Routes ─────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `routes` (`id`, `name`, `url`, `domain`, `module`, `description`, `controller`, `action`) VALUES
(100032, 'manage_website',  'home.php?route=manage_website',  'SCHOOL', 'website', 'School website content management',    NULL, NULL),
(100033, 'website_news',    'api/website/news',               'SCHOOL', 'website', 'Website news articles API',            'WebsiteController', 'news'),
(100034, 'website_events',  'api/website/events',             'SCHOOL', 'website', 'Website school events API',            'WebsiteController', 'events'),
(100035, 'website_gallery', 'api/website/gallery',            'SCHOOL', 'website', 'Website gallery API',                  'WebsiteController', 'gallery'),
(100036, 'website_jobs',    'api/website/jobs',               'SCHOOL', 'website', 'Website job vacancies API',            'WebsiteController', 'jobs'),
(100037, 'website_settings','api/website/settings',           'SCHOOL', 'website', 'Website school settings API',          'WebsiteController', 'settings'),
(100038, 'website_content', 'api/website/content',            'SCHOOL', 'website', 'Website page content API',             'WebsiteController', 'content');

-- ── 3. Sidebar Menu Items ─────────────────────────────────────────────────────

-- Parent group for: System Admin (2), Director (3), School Admin (4)
INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100039, 'website_mgmt_group',    'Website',        'bi bi-globe2',           'manage_website', 100032, NULL, 'sidebar', 9,  'SCHOOL');

-- Children for admin-level (full access)
INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100040, 'website_news_menu',      'News & Blog',       'bi bi-newspaper',        'manage_website', 100032, 100039, 'sidebar', 1, 'SCHOOL'),
(100041, 'website_events_menu',    'School Events',     'bi bi-calendar-event',   'manage_website', 100032, 100039, 'sidebar', 2, 'SCHOOL'),
(100042, 'website_gallery_menu',   'Gallery',           'bi bi-images',           'manage_website', 100032, 100039, 'sidebar', 3, 'SCHOOL'),
(100043, 'website_downloads_menu', 'Downloads',         'bi bi-cloud-download',   'manage_website', 100032, 100039, 'sidebar', 4, 'SCHOOL'),
(100044, 'website_jobs_menu',      'Job Vacancies',     'bi bi-briefcase',        'manage_website', 100032, 100039, 'sidebar', 5, 'SCHOOL'),
(100045, 'website_apps_menu',      'Applications',      'bi bi-inbox-fill',       'manage_website', 100032, 100039, 'sidebar', 6, 'SCHOOL'),
(100046, 'website_settings_menu',  'Site Settings',     'bi bi-gear-wide-connected','manage_website',100032,100039, 'sidebar', 7, 'SCHOOL'),
(100047, 'website_content_menu',   'Page Content',      'bi bi-file-richtext',    'manage_website', 100032, 100039, 'sidebar', 8, 'SCHOOL');

-- Parent group for Headteacher (5) — separate item so it can be placed independently
INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100048, 'ht_website_mgmt_group', 'Website',           'bi bi-globe2',           'manage_website', 100032, NULL, 'sidebar', 9,  'SCHOOL'),
(100049, 'ht_website_news',       'News & Blog',       'bi bi-newspaper',        'manage_website', 100032, 100048, 'sidebar', 1, 'SCHOOL'),
(100050, 'ht_website_events',     'School Events',     'bi bi-calendar-event',   'manage_website', 100032, 100048, 'sidebar', 2, 'SCHOOL'),
(100051, 'ht_website_content',    'Page Content',      'bi bi-file-richtext',    'manage_website', 100032, 100048, 'sidebar', 3, 'SCHOOL'),
(100052, 'ht_website_apps',       'Applications',      'bi bi-inbox-fill',       'manage_website', 100032, 100048, 'sidebar', 4, 'SCHOOL'),
(100053, 'ht_website_jobs',       'Job Vacancies',     'bi bi-briefcase',        'manage_website', 100032, 100048, 'sidebar', 5, 'SCHOOL'),
(100054, 'ht_website_gallery',    'Gallery',           'bi bi-images',           'manage_website', 100032, 100048, 'sidebar', 6, 'SCHOOL'),
(100055, 'ht_website_downloads',  'Downloads',         'bi bi-cloud-download',   'manage_website', 100032, 100048, 'sidebar', 7, 'SCHOOL');

-- Deputy Head Academic (6) and Discipline (63) — limited
INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100056, 'dha_website_group',     'Website',           'bi bi-globe2',           'manage_website', 100032, NULL, 'sidebar', 9,  'SCHOOL'),
(100057, 'dha_website_news',      'News & Blog',       'bi bi-newspaper',        'manage_website', 100032, 100056, 'sidebar', 1, 'SCHOOL'),
(100058, 'dha_website_events',    'School Events',     'bi bi-calendar-event',   'manage_website', 100032, 100056, 'sidebar', 2, 'SCHOOL');

INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100059, 'dhd_website_group',     'Website',           'bi bi-globe2',           'manage_website', 100032, NULL, 'sidebar', 9,  'SCHOOL'),
(100060, 'dhd_website_news',      'News & Blog',       'bi bi-newspaper',        'manage_website', 100032, 100059, 'sidebar', 1, 'SCHOOL'),
(100061, 'dhd_website_events',    'School Events',     'bi bi-calendar-event',   'manage_website', 100032, 100059, 'sidebar', 2, 'SCHOOL');

-- Talent Development (21) — sports/arts news, events, gallery
INSERT IGNORE INTO `sidebar_menu_items` (`id`,`name`,`label`,`icon`,`url`,`route_id`,`parent_id`,`menu_type`,`display_order`,`domain`) VALUES
(100062, 'td_website_group',      'Website',           'bi bi-globe2',           'manage_website', 100032, NULL, 'sidebar', 9,  'SCHOOL'),
(100063, 'td_website_news',       'Post News',         'bi bi-newspaper',        'manage_website', 100032, 100062, 'sidebar', 1, 'SCHOOL'),
(100064, 'td_website_events',     'School Events',     'bi bi-calendar-event',   'manage_website', 100032, 100062, 'sidebar', 2, 'SCHOOL'),
(100065, 'td_website_gallery',    'Gallery',           'bi bi-images',           'manage_website', 100032, 100062, 'sidebar', 3, 'SCHOOL');

-- ── 4. Role → Sidebar Menu Assignments ───────────────────────────────────────

-- System Administrator (2) — full website group
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(2, 100039, 1, 900), (2, 100040, 1, 901), (2, 100041, 1, 902), (2, 100042, 1, 903),
(2, 100043, 1, 904), (2, 100044, 1, 905), (2, 100045, 1, 906), (2, 100046, 1, 907),
(2, 100047, 1, 908);

-- Director (3)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(3, 100039, 1, 900), (3, 100040, 1, 901), (3, 100041, 1, 902), (3, 100042, 1, 903),
(3, 100043, 1, 904), (3, 100044, 1, 905), (3, 100045, 1, 906), (3, 100046, 1, 907),
(3, 100047, 1, 908);

-- School Administrator (4)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(4, 100039, 1, 900), (4, 100040, 1, 901), (4, 100041, 1, 902), (4, 100042, 1, 903),
(4, 100043, 1, 904), (4, 100044, 1, 905), (4, 100045, 1, 906), (4, 100046, 1, 907),
(4, 100047, 1, 908);

-- Headteacher (5)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(5, 100048, 1, 900), (5, 100049, 1, 901), (5, 100050, 1, 902), (5, 100051, 1, 903),
(5, 100052, 1, 904), (5, 100053, 1, 905), (5, 100054, 1, 906), (5, 100055, 1, 907);

-- Deputy Head Academic (6)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(6, 100056, 1, 900), (6, 100057, 1, 901), (6, 100058, 1, 902);

-- Deputy Head Discipline (63)
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(63, 100059, 1, 900), (63, 100060, 1, 901), (63, 100061, 1, 902);

-- Talent Development (21) — sports teacher
INSERT IGNORE INTO `role_sidebar_menus` (`role_id`, `menu_item_id`, `is_default`, `custom_order`) VALUES
(21, 100062, 1, 900), (21, 100063, 1, 901), (21, 100064, 1, 902), (21, 100065, 1, 903);

-- ── 5. Role → Permission Assignments ─────────────────────────────────────────

-- System Administrator (2) — ALL website permissions
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2,100177),(2,100178),(2,100179),(2,100180),(2,100181),(2,100182),(2,100183),(2,100184),(2,100185),(2,100186);

-- Director (3) — ALL
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3,100177),(3,100178),(3,100179),(3,100180),(3,100181),(3,100182),(3,100183),(3,100184),(3,100185),(3,100186);

-- School Administrator (4) — ALL
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(4,100177),(4,100178),(4,100179),(4,100180),(4,100181),(4,100182),(4,100183),(4,100184),(4,100185),(4,100186);

-- Headteacher (5) — news, events, gallery, downloads, jobs, content, applications (NOT settings)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(5,100177),(5,100178),(5,100179),(5,100180),(5,100181),(5,100182),(5,100184),(5,100185),(5,100186);

-- Deputy Head Academic (6) — news, events, applications view
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(6,100177),(6,100178),(6,100179),(6,100185);

-- Deputy Head Discipline (63) — news, events only
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(63,100177),(63,100178),(63,100179);

-- Talent Development / Sports (21) — news, events, gallery
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(21,100177),(21,100178),(21,100179),(21,100180);
