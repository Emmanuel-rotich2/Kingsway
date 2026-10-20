<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\website\WebsiteManager;

/**
 * Public FAQ corpus loader.
 *
 * The authoritative corpus is the versioned JSON file written by the Python
 * platform at storage/public/faqs.json. Python regenerates it from the
 * published website content, term calendar and department contacts so a new
 * term, academic year or school change reaches the assistant without a code
 * deployment. When the file is absent (fresh install or Python temporarily
 * unavailable), this service falls back to the same published website content
 * used before the corpus file existed.
 */
final class PublicFaqService
{
    private const MAX_SOURCES = 60;
    private const MAX_CONTENT_CHARS = 4000;

    private static ?int $cachedMtime = null;
    /** @var array<int, array{id:string,title:string,content:string}> */
    private static array $cachedSources = [];

    /**
     * @return array<int, array{id:string,title:string,content:string}>
     */
    public function corpus(WebsiteManager $manager): array
    {
        $path = StorageRoot::path('public/faqs.json');
        $mtime = @filemtime($path);

        if ($mtime !== false) {
            if (self::$cachedMtime === $mtime && self::$cachedSources !== []) {
                return self::$cachedSources;
            }

            $sources = $this->sourcesFromFile($path);
            if ($sources !== []) {
                self::$cachedMtime = $mtime;
                self::$cachedSources = $sources;
                return $sources;
            }
        }

        return $this->fallbackCorpus($manager);
    }

    /**
     * @return array<int, array{id:string,title:string,content:string}>
     */
    private function sourcesFromFile(string $path): array
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $sources = [];
        $push = static function (string $id, string $title, string $content) use (&$sources): void {
            if (count($sources) >= self::MAX_SOURCES) return;
            $content = trim($content);
            if ($content === '') return;
            $sources[] = [
                'id' => mb_substr($id !== '' ? $id : 'public_faq_' . count($sources), 0, 100),
                'title' => mb_substr(trim($title), 0, 200),
                'content' => mb_substr($content, 0, self::MAX_CONTENT_CHARS),
            ];
        };

        if (is_array($decoded['sources'] ?? null)) {
            foreach ($decoded['sources'] as $source) {
                if (!is_array($source)) continue;
                $push(
                    (string) ($source['id'] ?? ''),
                    (string) ($source['title'] ?? ''),
                    (string) ($source['content'] ?? '')
                );
            }
            return $sources;
        }

        if (is_array($decoded['sections'] ?? null)) {
            $flatten = static function (array $items, string $prefix, string $label) use (&$flatten, $push): void {
                foreach ($items as $index => $item) {
                    $segment = is_int($index)
                        ? (string) $index
                        : (preg_replace('/[^a-z0-9_]+/i', '_', (string) $index) ?: (string) $index);
                    $id = $prefix . '_' . $segment;
                    if (is_scalar($item)) {
                        $push($id, $label, (string) $item);
                        continue;
                    }
                    if (!is_array($item)) continue;

                    $parts = [];
                    foreach (['content', 'content_value', 'description', 'level_range', 'event_title', 'bio', 'name', 'email', 'phone'] as $field) {
                        $text = trim((string) ($item[$field] ?? ''));
                        if ($text !== '') $parts[] = $text;
                    }
                    if ($parts !== []) {
                        $title = (string) ($item['title'] ?? $item['content_key'] ?? $item['name'] ?? $item['event_title'] ?? $label);
                        $push($id, $title, implode("\n", array_unique($parts)));
                        continue;
                    }
                    $flatten($item, $id, (string) ($item['name'] ?? $label));
                }
            };
            $flatten($decoded['sections'], 'public', 'Kingsway Preparatory School');
        }

        if (is_string($decoded['maintainer_fact'] ?? null)) {
            $push('public_system_maintainer', 'School system maintenance', $decoded['maintainer_fact']);
        }

        return array_slice($sources, 0, self::MAX_SOURCES);
    }

    /**
     * Live fallback built from the published website content. This mirrors the
     * pre-existing behaviour so the assistant keeps working before Python has
     * produced its first corpus file.
     *
     * @return array<int, array{id:string,title:string,content:string}>
     */
    private function fallbackCorpus(WebsiteManager $manager): array
    {
        $content = $manager->getContent();
        $payload = is_array($content['data'] ?? null)
            ? $content['data']
            : (is_array($content) ? $content : []);

        $corpus = [];
        $pushRows = function (array $items, string $prefix, string $label) use (&$pushRows, &$corpus): void {
            foreach ($items as $index => $item) {
                if (count($corpus) >= self::MAX_SOURCES) return;
                $segment = is_int($index)
                    ? (string) $index
                    : (preg_replace('/[^a-z0-9_]+/i', '_', (string) $index) ?: (string) $index);
                $id = $prefix . '_' . $segment;
                if (is_scalar($item)) {
                    $text = trim((string) $item);
                    if ($text !== '') {
                        $corpus[] = ['id' => $id, 'title' => $label, 'content' => $text];
                    }
                    continue;
                }
                if (!is_array($item)) continue;

                $parts = [];
                foreach (['content_value', 'content', 'description', 'level_range', 'event_title', 'bio', 'setting_value', 'email', 'phone'] as $field) {
                    $text = trim((string) ($item[$field] ?? ''));
                    if ($text !== '') $parts[] = $text;
                }
                if ($parts !== []) {
                    $title = (string) ($item['title'] ?? $item['content_key'] ?? $item['name'] ?? $item['event_title'] ?? $label);
                    $corpus[] = [
                        'id' => $id,
                        'title' => $title,
                        'content' => implode("\n", array_unique($parts)),
                    ];
                    continue;
                }
                $pushRows($item, $id, $label);
            }
        };

        $blocks = is_array($payload['blocks'] ?? null) ? $payload['blocks'] : [];
        $pushRows($blocks, 'public_blocks', 'About Kingsway');

        $sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
        if (is_array($sections['term_dates'] ?? null)) {
            $pushRows($sections['term_dates'], 'public_term_dates', 'Term dates');
        }
        $pushRows($manager->publicDepartmentContacts(), 'public_contact_directory', 'Contact directory');

        $corpus[] = [
            'id' => 'public_system_maintainer',
            'title' => 'School system maintenance',
            'content' => 'The Kingsway school management system is maintained by AngiSoft Technologies. Public company information is available at https://www.angisoft.co.ke. The published school information does not claim that the founders developed the software.',
        ];
        $corpus[] = [
            'id' => 'public_system_maintainer_contact',
            'title' => 'AngiSoft Technologies contact',
            'content' => 'To contact AngiSoft Technologies, visit https://www.angisoft.co.ke/contact, call or send SMS/WhatsApp to +254710398690, or email info@angisoft.co.ke. AngiSoft Technologies is based in Nairobi, Kenya.',
        ];

        foreach ($sections as $key => $value) {
            if ($key === 'term_dates' || $key === 'leadership_positions' || !is_array($value)) continue;
            $sourceKey = preg_replace('/[^a-z0-9_]+/i', '_', (string) $key);
            $pushRows($value, 'public_' . $sourceKey, (string) $key);
        }

        return array_slice($corpus, 0, self::MAX_SOURCES);
    }
}
