<?php
/**
 * public/layout/facade_routes.php — shared route catalogs for the public and
 * parent-portal front controllers.
 *
 * Every page behind these facades rejects direct file access (see .htaccess
 * deny-by-default); it may only be reached as:
 *
 *   index.php?route=<public-key>          (public website facade)
 *   parent_portal.php?route=<parents-key> (parent/guardian portal facade)
 *
 * Keys are lowercase-hyphenated. Requiring a target page is safe because every
 * public/parents template is self-contained: it recomputes $appBase from
 * SCRIPT_NAME (or honours $appBaseOverride) and sets its own $pageTitle /
 * $activePage / $parentPageTitle before including the shared layout parts.
 *
 * This file must not be served directly; .htaccess denies it.
 */
declare(strict_types=1);

$appBaseForRoutes = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBaseForRoutes === '.' || $appBaseForRoutes === '') {
    $appBaseForRoutes = '';
}

return [
    'public' => [
        'about'               => 'about.php',
        'admissions'          => 'admissions.php',
        'news'                => 'news.php',
        'news-article'        => 'news-article.php',
        'events'              => 'events.php',
        'event-detail'        => 'event-detail.php',
        'careers'             => 'careers.php',
        'job-detail'          => 'job-detail.php',
        'contact'             => 'contact.php',
        'downloads'           => 'downloads.php',
        'uniform-catalog'     => 'uniform_catalog.php',
        'product-details'     => 'product_details.php',
        'catalog-account'     => 'catalog_account.php',
        'calendar-download'   => 'calendar_download.php',
        'student-id-check'    => 'student_portal.php',
        'login'               => 'login.php',
        'forgot-password'     => 'forgot_password.php',
        'reset-password'      => 'reset_password.php',
        'reset-default-password' => 'reset_default_password.php',
    ],

    'parents' => [
        'dashboard'        => 'parents/dashboard.php',
        'children'         => 'parents/children.php',
        'updates'          => 'parents/updates.php',
        'results'          => 'parents/results.php',
        'learning'         => 'parents/learning.php',
        'health'           => 'parents/health.php',
        'activities'       => 'parents/activities.php',
        'attendance'       => 'parents/attendance.php',
        'messages'         => 'parents/messages.php',
        'documents'        => 'parents/documents.php',
        'downloads'        => 'parents/downloads.php',
        'fees'             => 'parents/fees.php',
        'transport'        => 'parents/transport.php',
        'community'        => 'parents/community.php',
        'account'          => 'parents/account.php',
        'uniform-catalog'  => 'parents/uniform_catalog.php',
        'my-family'        => 'my_family.php',
    ],
];