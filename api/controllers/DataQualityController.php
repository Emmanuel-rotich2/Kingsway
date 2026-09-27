<?php

namespace App\API\Controllers;

use App\API\Services\DataQualityService;
use DomainException;

/**
 * DataQualityController — governed read/apply surface for the legacy
 * re-normalization service.
 *
 *   GET  /api/data-quality/audit   read-only findings (fixes, review, missing, duplicates)
 *   POST /api/data-quality/apply   approval-gated application of audited fixes
 *
 * Both endpoints are audited by the automatic middleware stack (Auth, CSRF,
 * RBAC, device) and the contract layer. The apply endpoint is deliberately
 * permission-separated from the read endpoint: only accounts holding the
 * system manage permission (System Administrator / wildcard) may mutate, while
 * a read-only operator may inspect findings. apply() re-derives every canonical
 * value server-side from the audited original — a tampered client "to" is
 * ignored — runs in a single transaction, is idempotent, and never deletes or
 * merges rows.
 */
class DataQualityController extends BaseController
{
    private const VIEW_PERMISSIONS = ['system_view', 'system.view', 'data_quality_view'];
    private const APPLY_PERMISSIONS = ['system_manage', 'system.manage', 'data_quality_manage'];

    // GET /api/data-quality/audit?tables[]=persons&maxRows=100000&limit=50
    public function getAudit($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(self::VIEW_PERMISSIONS, [])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $options = [];
            $tables = $data['tables'] ?? $_GET['tables'] ?? null;
            if (is_array($tables) && $tables !== []) {
                $options['tables'] = array_map('strval', array_slice($tables, 0, 20));
            }
            $maxRows = intval($_GET['maxRows'] ?? 100000);
            $options['maxRows'] = max(1, min(500000, $maxRows));

            $service = $this->contract(DataQualityService::class, $this->db->getConnection());
            $findings = $service->audit($options);

            // Bound the response: full counts always, samples capped server-side.
            $limit = max(1, min(200, intval($_GET['limit'] ?? 50)));
            $findings['fixes'] = array_slice($findings['fixes'], 0, $limit);
            $findings['review'] = array_slice($findings['review'], 0, $limit);
            $findings['missing'] = array_slice($findings['missing'], 0, $limit);

            return $this->success($findings, 'Data quality audit complete');
        } catch (DomainException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            return $this->serverError('Data quality audit is temporarily unavailable');
        }
    }

    // POST /api/data-quality/apply   body: { confirm: "APPLY_DATA_QUALITY_FIXES", fixes: [ {table,id,column,from}, ... ] }
    public function postApply($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(self::APPLY_PERMISSIONS, [])) {
            return $this->forbidden('Data quality apply requires manage permission');
        }

        $confirm = $data['confirm'] ?? '';
        if ($confirm !== 'APPLY_DATA_QUALITY_FIXES') {
            return $this->badRequest('Missing explicit apply confirmation token');
        }

        $fixes = $data['fixes'] ?? null;
        if (!is_array($fixes) || $fixes === []) {
            return $this->badRequest('No fixes supplied for apply');
        }
        if (count($fixes) > 5000) {
            return $this->badRequest('Fix list exceeds the 5000 entry safety cap');
        }

        // Only the audited identity fields are accepted; "to" is never trusted
        // (apply() re-derives the canonical value from "from"). Channel is
        // forwarded only for channel-aware contact-point fixes so phone vs
        // email resolution is preserved.
        $cleanFixes = [];
        foreach ($fixes as $i => $fix) {
            if (!is_array($fix)) {
                continue;
            }
            $entry = [
                'table' => strval($fix['table'] ?? ''),
                'id' => intval($fix['id'] ?? 0),
                'column' => strval($fix['column'] ?? ''),
                'from' => strval($fix['from'] ?? ''),
            ];
            $channel = $fix['channel'] ?? null;
            if (is_string($channel) && $channel !== '' && in_array($channel, ['email', 'phone'], true)) {
                $entry['channel'] = $channel;
            }
            $cleanFixes[] = $entry;
        }

        try {
            $service = $this->contract(DataQualityService::class, $this->db->getConnection());
            $result = $service->apply($cleanFixes);
            return $this->success(
                [
                    'applied' => $result['applied'],
                    'skipped' => $result['skipped'],
                    'errors' => array_values($result['errors']),
                ],
                'Data quality fixes applied'
            );
        } catch (DomainException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            return $this->serverError('Data quality apply failed and was rolled back');
        }
    }
}