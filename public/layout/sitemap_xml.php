<?php
/**
 * sitemap.xml, generated on request (served via index.php?route=sitemap-xml,
 * which .htaccess maps /sitemap.xml to).
 *
 * Lists every indexable public page at its clean URL, plus each published
 * news article, current or upcoming event and open vacancy at the same
 * canonical URL its page declares (public/layout/header.php). Sign-in,
 * account and portal pages are deliberately absent. Search engines ignore
 * <priority>/<changefreq>, so only <lastmod> is emitted; for static pages it
 * is the template's modification date.
 */
declare(strict_types=1);

\App\Config\Config::init();
$siteBase = public_site_base();
$root = dirname(__DIR__, 2);
$facade = (require __DIR__ . '/facade_routes.php')['public'] ?? [];

$dateOf = static function ($value): ?string {
    if ($value === null || $value === '') {
        return null;
    }
    $ts = is_int($value) ? $value : strtotime((string) $value);
    return $ts ? gmdate('Y-m-d', $ts) : null;
};

$urls = [[
    'loc' => $siteBase . '/',
    'lastmod' => $dateOf(@filemtime($root . '/index.php') ?: null),
]];
foreach (public_clean_route_keys() as $key) {
    $template = $facade[$key] ?? null;
    $urls[] = [
        'loc' => $siteBase . '/' . public_clean_url($key),
        'lastmod' => $template ? $dateOf(@filemtime($root . '/' . $template) ?: null) : null,
    ];
}

try {
    $entries = (new \App\API\Modules\website\WebsiteManager())->sitemapEntries();
} catch (\Throwable $e) {
    \App\API\Services\Logger::legacyError('[sitemap] ' . $e->getMessage());
    $entries = [];
}
$detailRoutes = ['news' => 'news-article', 'events' => 'event-detail', 'jobs' => 'job-detail'];
foreach ($detailRoutes as $kind => $routeKey) {
    foreach ($entries[$kind] ?? [] as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $urls[] = [
            'loc' => $siteBase . '/index.php?route=' . $routeKey . '&id=' . $id,
            'lastmod' => $dateOf($row['lastmod'] ?? null),
        ];
    }
}

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', "\n";
foreach ($urls as $url) {
    echo "  <url>\n    <loc>", htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8'), "</loc>\n";
    if (!empty($url['lastmod'])) {
        echo '    <lastmod>', $url['lastmod'], "</lastmod>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
