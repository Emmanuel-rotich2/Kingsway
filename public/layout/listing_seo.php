<?php
/**
 * Server-rendered, crawlable first byte for public listing pages.
 *
 * The page controllers still enhance these lists through /api/website/*, but
 * important public content must exist in the initial HTML: Googlebot may not
 * render JavaScript, and JS-only cards leave thin shells in the index.
 *
 * No SQL lives here. All reads go through WebsiteManager (api/modules/website),
 * which is the same public-safe service the API endpoints use. Any failure
 * degrades to an empty list so the page itself always renders.
 */
declare(strict_types=1);

if (!function_exists('kw_public_seo_text')) {
    function kw_public_seo_text($value, int $max = 170): string
    {
        $plain = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
        if (mb_strlen($plain) <= $max) {
            return $plain;
        }
        $cut = mb_substr($plain, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        return rtrim($space > 60 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
    }
}

if (!function_exists('kw_public_seo_manager')) {
    function kw_public_seo_manager(): ?\App\API\Modules\website\WebsiteManager
    {
        static $manager = null;
        if ($manager !== null) {
            return $manager;
        }
        try {
            if (!class_exists(\App\API\Modules\website\WebsiteManager::class)) {
                require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            }
            \App\Config\Config::init();
            $manager = new \App\API\Modules\website\WebsiteManager();
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[listing_seo] ' . $e->getMessage());
            $manager = false;
        }
        return $manager ?: null;
    }
}

if (!function_exists('kw_public_seo_date')) {
    function kw_public_seo_date($value): string
    {
        $ts = $value ? strtotime((string) $value) : false;
        return $ts ? date('j F Y', $ts) : '';
    }
}

if (!function_exists('kw_public_seo_url')) {
    function kw_public_seo_url(string $routeKey, array $query = []): string
    {
        return rtrim(public_site_base(), '/') . '/' . public_route_url($routeKey, $query);
    }
}

if (!function_exists('kw_public_seo_items')) {
    function kw_public_seo_items(string $kind, int $limit = 6): array
    {
        $manager = kw_public_seo_manager();
        if ($manager === null) {
            return [];
        }
        try {
            switch ($kind) {
                case 'news':
                    return $manager->getNews(0, ['limit' => $limit])['data']['items'] ?? [];
                case 'events':
                    return $manager->getEvents(0)['data']['items'] ?? [];
                case 'jobs':
                    return $manager->getJobs(0)['data']['items'] ?? [];
                case 'gallery':
                    return $manager->getGallery()['data']['items'] ?? [];
                case 'downloads':
                    return $manager->getDownloads()['data']['items'] ?? [];
                default:
                    return [];
            }
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[listing_seo ' . $kind . '] ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('kw_public_seo_cards')) {
    function kw_public_seo_cards(string $kind, bool $wrap = true): void
    {
        $esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $items = array_slice(kw_public_seo_items($kind), 0, $kind === 'gallery' ? 12 : 6);
        if ($items === []) {
            return;
        }
        if ($wrap) {
            echo '<div class="row g-4 seo-initial-list">';
        }

        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? $row['caption'] ?? ''));
            if ($title === '') {
                continue;
            }
            echo '<div class="col-md-6 col-lg-4">';
            echo '<article class="card-modern h-100 p-4">';

            if ($kind === 'news') {
                $id = (int) ($row['id'] ?? 0);
                $date = kw_public_seo_date($row['created_at'] ?? null);
                echo '<div class="small text-success fw-semibold mb-2">', $esc($date !== '' ? $date : 'News'), '</div>';
                echo '<h3 class="h6 fw-bold mb-2">', $id > 0
                    ? '<a class="text-decoration-none" href="' . $esc(kw_public_seo_url('news-article', ['id' => $id])) . '">' . $esc($title) . '</a>'
                    : $esc($title), '</h3>';
                $summary = kw_public_seo_text(($row['excerpt'] ?? '') !== '' ? $row['excerpt'] : ($row['content'] ?? ''));
                if ($summary !== '') {
                    echo '<p class="text-muted small mb-0">', $esc($summary), '</p>';
                }
            } elseif ($kind === 'events') {
                $id = (int) ($row['id'] ?? 0);
                $date = kw_public_seo_date($row['start_at'] ?? null);
                echo '<div class="small text-success fw-semibold mb-2">', $esc($date !== '' ? $date : 'School event'), '</div>';
                echo '<h3 class="h6 fw-bold mb-2">', $id > 0
                    ? '<a class="text-decoration-none" href="' . $esc(kw_public_seo_url('event-detail', ['id' => $id])) . '">' . $esc($title) . '</a>'
                    : $esc($title), '</h3>';
                $summary = kw_public_seo_text($row['description'] ?? '');
                if ($summary !== '') {
                    echo '<p class="text-muted small mb-0">', $esc($summary), '</p>';
                }
            } elseif ($kind === 'jobs') {
                $id = (int) ($row['id'] ?? 0);
                $meta = trim((string) ($row['job_type'] ?? ''));
                $deadline = kw_public_seo_date($row['deadline'] ?? null);
                if ($deadline !== '') {
                    $meta = $meta !== '' ? $meta . ' · Apply by ' . $deadline : 'Apply by ' . $deadline;
                }
                echo '<div class="small text-success fw-semibold mb-2">', $esc($meta !== '' ? $meta : 'Vacancy'), '</div>';
                echo '<h3 class="h6 fw-bold mb-2">', $id > 0
                    ? '<a class="text-decoration-none" href="' . $esc(kw_public_seo_url('job-detail', ['id' => $id])) . '">' . $esc($title) . '</a>'
                    : $esc($title), '</h3>';
                $summary = kw_public_seo_text($row['description'] ?? '');
                if ($summary !== '') {
                    echo '<p class="text-muted small mb-0">', $esc($summary), '</p>';
                }
            } elseif ($kind === 'gallery') {
                $src = trim((string) ($row['image_url'] ?? ''));
                if ($src !== '') {
                    echo '<img src="', $esc($src), '" alt="', $esc($title), '" class="w-100 rounded-3 mb-3" loading="lazy" style="aspect-ratio:4/3;object-fit:cover">';
                }
                echo '<h3 class="h6 fw-bold mb-0">', $esc($title), '</h3>';
            } elseif ($kind === 'downloads') {
                $token = trim((string) ($row['public_token'] ?? ''));
                echo '<h3 class="h6 fw-bold mb-2">', $token !== ''
                    ? '<a class="text-decoration-none" href="' . $esc(rtrim(public_site_base(), '/') . '/api/download/public?token=' . rawurlencode($token)) . '">' . $esc($title) . '</a>'
                    : $esc($title), '</h3>';
                $category = trim((string) ($row['category'] ?? ''));
                if ($category !== '') {
                    echo '<div class="small text-success fw-semibold">', $esc($category), '</div>';
                }
            }

            echo '</article></div>';
        }
        if ($wrap) {
            echo '</div>';
        }
    }
}
