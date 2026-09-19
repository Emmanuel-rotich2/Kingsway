<?php

namespace App\API\Services;

use PDO;

/**
 * Contract for deterministic intelligence detectors (P3a).
 *
 * A detector computes values, flags, and alerts purely from SQL/rules over the
 * governed vw_* views and allowlisted tables - it NEVER calls a provider.
 * Detectors are aggregate-only: outputs carry counts, rates, and scope labels,
 * never learner identity or individual records.
 *
 * Contract returned by detect():
 *   {
 *     "domain": string,
 *     "as_of": "Y-m-d",
 *     "metrics": { code: scalar|list },
 *     "alerts": [
 *       {
 *         "level": "info|warning|critical",
 *         "code": "attendance.consecutive_absences",
 *         "message": string,
 *         "target_scopes": ["attendance", "headteacher"]
 *       }
 *     ]
 *   }
 */
interface IntelligenceDetector
{
    /**
     * Run the deterministic rule set for the given scope/domain.
     *
     * @param array $options detector-specific options (filter values, etc.)
     * @return array<string,mixed> domain/as_of/metrics/alerts contract
     */
    public function detect(PDO $pdo, array $options = []): array;
}