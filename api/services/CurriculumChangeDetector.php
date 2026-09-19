<?php

namespace App\API\Services;

use PDO;

/**
 * Curriculum content-change detector (deterministic, P3a).
 *
 * Detects KICD/holiday/circular content changes via stable content hashing.
 * Feed it the current document text plus a stored baseline hash; it reports
 * whether the content changed and how. No provider call: the detector only
 * hashes and compares, and it never interprets the diff (interpretation, if
 * wanted later, belongs to the LLM layer, not here).
 *
 * The scheduled KICD policy-watch agent (P3b) is the caller that fetches
 * source documents and supplies this detector with content + baseline hash.
 */
final class CurriculumChangeDetector extends AbstractIntelligenceDetector
{
    /** Hash digest algorithm. */
    private const ALGO = 'sha256';

    public function detect(PDO $pdo, array $options = []): array
    {
        $source = trim((string) ($options['source'] ?? 'kurriculum_document'));
        $content = (string) ($options['content'] ?? '');
        $baselineHash = trim((string) ($options['baseline_hash'] ?? ''));

        if ($content === '') {
            return $this->result('curriculum', [
                'source' => $source,
                'changed' => null,
                'current_hash' => null,
                'baseline_hash' => null,
                'reason' => 'no_content',
            ], []);
        }

        $currentHash = self::hashContent($content);
        $changed = $baselineHash === '' ? null : ($currentHash !== $baselineHash);

        $alerts = [];
        if ($changed === true) {
            $alerts[] = [
                'level' => 'info',
                'code' => 'curriculum.content_change_detected',
                'message' => 'Content for ' . $source . ' changed since the stored baseline; request staff review.',
                'target_scopes' => ['academics', 'system_admin'],
            ];
        }

        return $this->result('curriculum', [
            'source' => $source,
            'changed' => $changed,
            'content_bytes' => strlen($content),
            'current_hash' => $currentHash,
            'baseline_hash' => $baselineHash === '' ? null : $baselineHash,
            'reason' => $baselineHash === '' ? 'no_baseline' : ($changed ? 'changed' : 'unchanged'),
        ], $alerts);
    }

    /**
     * Stable content hash for a document. Whitespace-insensitive so tidy
     * reformatting does not raise a false positive. Shared with the scheduled
     * KICD policy-watch agent so both hashing paths stay single-sourced.
     */
    public static function hashContent(string $content): string
    {
        return hash(self::ALGO, preg_replace('/\s+/', ' ', trim($content)) ?? '');
    }
}