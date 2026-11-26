<?php
/**
 * Server-side SEO for the public detail pages (news article, event, vacancy).
 *
 * The page bodies are still rendered by their JS controllers, but search
 * engines and link previews read the <head>, so it must carry the item's own
 * title, description, image and structured data (NewsArticle, WebPage for
 * school events, JobPosting) on first byte. Included by news-article.php, event-detail.php
 * and job-detail.php after they set $canonicalRouteKey; sets $pageTitle,
 * $pageDescription, $socialImageOverride and $pageStructuredData for
 * header.php. Only published/open items are read (anonymous visitor rules).
 * Any failure leaves the page's generic defaults in place.
 */
declare(strict_types=1);

(static function (): void {
    $routeKey = $GLOBALS['canonicalRouteKey'] ?? '';
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0 || !in_array($routeKey, ['news-article', 'event-detail', 'job-detail'], true)) {
        return;
    }
    try {
        \App\Config\Config::init();
        $siteBase = public_site_base();
        $canonical = $siteBase . '/index.php?route=' . $routeKey . '&id=' . $id;
        $school = ['@type' => 'School', '@id' => $siteBase . '/#school', 'name' => 'Kingsway Preparatory School'];
        $address = [
            '@type' => 'PostalAddress',
            'streetAddress' => 'Simotwet',
            'addressLocality' => 'Londiani',
            'addressRegion' => 'Kericho County',
            'postalCode' => '20203',
            'addressCountry' => 'KE',
        ];
        $text = static function ($html, int $max = 158): string {
            $plain = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if (mb_strlen($plain) <= $max) {
                return $plain;
            }
            $cut = mb_substr($plain, 0, $max - 1);
            $space = mb_strrpos($cut, ' ');
            return rtrim($space > 80 ? mb_substr($cut, 0, $space) : $cut, " ,.;:") . '…';
        };
        $iso = static function ($value): ?string {
            $ts = $value ? strtotime((string) $value) : false;
            return $ts ? date('c', $ts) : null;
        };
        $absolute = static function ($url) use ($siteBase): ?string {
            $url = trim((string) $url);
            if ($url === '') {
                return null;
            }
            if (preg_match('#^https?://#i', $url)) {
                return $url;
            }
            return $siteBase . '/' . ltrim($url, '/');
        };

        $manager = new \App\API\Modules\website\WebsiteManager();
        if ($routeKey === 'news-article') {
            $row = $manager->getNews($id, ['published_only' => true])['data'] ?? null;
            if (!is_array($row) || empty($row['title'])) {
                return;
            }
            $GLOBALS['pageTitle'] = (string) $row['title'];
            $GLOBALS['pageDescription'] = $text(($row['excerpt'] ?? '') !== '' ? $row['excerpt'] : ($row['content'] ?? ''));
            $image = $absolute($row['image_url'] ?? '');
            if ($image) {
                $GLOBALS['socialImageOverride'] = $image;
            }
            $GLOBALS['pageStructuredData'] = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => mb_substr((string) $row['title'], 0, 110),
                'description' => $GLOBALS['pageDescription'],
                'image' => $image ? [$image] : null,
                'datePublished' => $iso($row['created_at'] ?? null),
                'dateModified' => $iso($row['updated_at'] ?? $row['created_at'] ?? null),
                'author' => ['@type' => 'Organization', 'name' => 'Kingsway Preparatory School', 'url' => $siteBase . '/'],
                'publisher' => $school,
                'mainEntityOfPage' => $canonical,
                'articleSection' => $row['category'] ?? null,
            ], static fn ($v) => $v !== null && $v !== '');
            return;
        }

        if ($routeKey === 'event-detail') {
            $row = $manager->getEvents($id)['data'] ?? null;
            if (!is_array($row) || empty($row['title'])) {
                return;
            }
            $GLOBALS['pageTitle'] = (string) $row['title'];
            $when = ($ts = strtotime((string) ($row['start_at'] ?? ''))) ? date('j F Y', $ts) : '';
            $summary = $text($row['description'] ?? '', 120);
            $GLOBALS['pageDescription'] = $text(trim(($when !== '' ? $when . ' at Kingsway Preparatory School, Londiani. ' : '') . $summary));
            // Google's Event rich results require a general-public event;
            // on-campus school events involving minors are ineligible. Keep a
            // valid WebPage node (plus the shared BreadcrumbList) instead of
            // marking up an ineligible Event.
            $GLOBALS['pageStructuredData'] = array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => (string) $row['title'],
                'description' => $GLOBALS['pageDescription'],
                'url' => $canonical,
                'inLanguage' => 'en-KE',
                'isPartOf' => ['@id' => $siteBase . '/#website'],
            ], static fn ($v) => $v !== null && $v !== '');
            return;
        }

        // job-detail
        $row = $manager->getJobs($id)['data'] ?? null;
        if (!is_array($row) || empty($row['title']) || strtolower((string) ($row['status'] ?? '')) !== 'open') {
            return;
        }
        $GLOBALS['pageTitle'] = (string) $row['title'] . ' — Job Vacancy';
        $GLOBALS['pageDescription'] = $text('Vacancy at Kingsway Preparatory School, Londiani: ' . $text($row['description'] ?? '', 120));
        $types = [
            'full-time' => 'FULL_TIME', 'full_time' => 'FULL_TIME', 'fulltime' => 'FULL_TIME',
            'part-time' => 'PART_TIME', 'part_time' => 'PART_TIME',
            'contract' => 'CONTRACTOR', 'temporary' => 'TEMPORARY', 'internship' => 'INTERN', 'volunteer' => 'VOLUNTEER',
        ];
        $employmentType = $types[strtolower(trim((string) ($row['job_type'] ?? '')))] ?? null;
        $deadline = $iso($row['deadline'] ?? null);
        $GLOBALS['pageStructuredData'] = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => (string) $row['title'],
            'description' => trim((string) ($row['description'] ?? '')) !== ''
                ? nl2br(htmlspecialchars((string) $row['description'], ENT_QUOTES, 'UTF-8'))
                : (string) $row['title'],
            'datePosted' => $iso($row['created_at'] ?? null),
            'validThrough' => $deadline,
            'employmentType' => $employmentType,
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => 'Kingsway Preparatory School',
                'sameAs' => $siteBase . '/',
            ],
            'jobLocation' => ['@type' => 'Place', 'address' => $address],
            'directApply' => true,
        ], static fn ($v) => $v !== null && $v !== '');
    } catch (\Throwable $e) {
        \App\API\Services\Logger::legacyError('[detail_seo] ' . $e->getMessage());
    }
})();
