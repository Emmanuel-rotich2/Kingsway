<?php

declare(strict_types=1);

namespace App\API\Services;

/** Loads only the deployment-owned research manifest; never accepts request URLs. */
final class ResearchSourceCatalog
{
    public function approved(): array
    {
        $path = getenv('AI_RESEARCH_MANIFEST') ?: dirname(__DIR__, 2) . '/storage/knowledge/research_sources.json';
        if (!is_file($path) || !is_readable($path)) return [];
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }
}
