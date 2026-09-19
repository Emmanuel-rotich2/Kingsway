<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use DomainException;
use PDO;
use Throwable;

/**
 * KICD policy-watch agent (roadmap P3b, pattern 2: event-driven hooks + day 1
 * of the KICD monitor automation backlog).
 *
 * Two tiers, mirroring the deterministic-first intelligence rule:
 *
 *  1. run(PDO, options)  - deterministic watch stage. Fetches the configured
 *     curriculum policy source (https URL, or a local file under storage/kicd/),
 *     normalizes + hashes it with CurriculumChangeDetector::hashContent, and
 *     compares against a small git-ignored baseline state file. On a real
 *     change it enqueues one curriculum.policy_interpret job for the worker and
 *     advances the baseline, so later same-content ticks report "unchanged"
 *     instead of re-queueing. Never holds a provider credential, never calls a
 *     provider.
 *
 *  2. interpret(PDO, payload) - worker stage. Re-authorizes the governed
 *     workflow with the recorded operator and creates a reviewable AiDraftService
 *     draft so staff decide what (if anything) to do with the change. The
 *     provider call happens here, safely inside the background job.
 *
 * The agent composes, but deliberately does not replace, CurriculumChangeDetector:
 * the detector remains a pure detector used by IntelligenceEngine; the agent
 * owns fetch + state + queueing decisions. Interpretation drafts are advisory
 * ("recommend"); AI never publishes or applies curriculum policy.
 */
final class CurriculumPolicyWatchAgent
{
    public const WORKFLOW = 'curriculum.kicd_change_interpretation';
    public const JOB_TYPE = 'curriculum.policy_interpret';
    public const PERMISSION = 'academic_view';
    public const STATE_FILE = 'storage/kicd/curriculum_baseline.json';

    /** @var string only https URLs and local files under storage/kicd/ are allowed source values. */
    private const MAX_SOURCE_BYTES = 200000;

    /** Prompt-policy scalar cap is 1000 chars; keep the document snippet bounded below it. */
    private const MAX_CONTENT_CHARS = 900;

    private const CATEGORY = 'curriculum_policy';

    /** @var callable(string,array):int */
    private $queuer;

    /** @var callable(PDO,string,string,array,array):int */
    private $broadcaster;

    /** @var string absolute path of the baseline state file */
    private $stateFile;

    /** @var AiDraftService|null */
    private $drafts;

    /**
     * @param callable(string,array):int   $queuer pushes a background job; tests inject a recorder.
     * @param string|null                  $stateFile override the baseline state file path (tests).
     * @param AiDraftService|null          $drafts override the draft service (tests).
     * @param callable|null                $broadcaster override the realtime dispatch (tests).
     */
    public function __construct(
        ?callable $queuer = null,
        ?string $stateFile = null,
        ?AiDraftService $drafts = null,
        ?callable $broadcaster = null
    ) {
        $this->queuer = $queuer ?? static function (string $type, array $payload): int {
            return JobQueue::push($type, $payload, 0, 3, 60);
        };
        $this->stateFile = $stateFile ?? dirname(__DIR__, 2) . '/' . self::STATE_FILE;
        $this->drafts = $drafts;
        $this->broadcaster = $broadcaster ?? static function (
            PDO $pdo,
            string $domain,
            string $eventName,
            array $payload,
            array $targetScopes
        ): int {
            return EventBroadcaster::dispatch($pdo, $domain, $eventName, $payload, $targetScopes);
        };
    }

    /**
     * Deterministic watch stage.
     *
     * @return array{status:string,source?:string,current_hash?:string,previous_hash?:string,job_id?:int,reason?:string}
     */
    public function run(PDO $pdo, array $options = []): array
    {
        $source = trim((string) ($options['source'] ?? ''));
        if ($source === '') {
            $source = trim((string) (Config::get('KICD_POLICY_SOURCE') ?? ''));
        }
        $content = (string) ($options['content'] ?? '');
        if ($content === '') {
            if ($source === '') {
                $this->log('no_source', ['source' => null, 'reason' => 'KICD_POLICY_SOURCE not configured']);
                return ['status' => 'no_source'];
            }
            $content = $this->fetchContent($source);
        }
        if ($content === '') {
            $this->log('no_content', ['source' => $source, 'reason' => 'source returned an empty document']);
            return ['status' => 'no_content', 'source' => $source];
        }

        $content = mb_substr($content, 0, self::MAX_SOURCE_BYTES);
        $currentHash = CurriculumChangeDetector::hashContent($content);
        $state = $this->readState();
        $baselineHash = trim((string) ($state['baseline_hash'] ?? ''));

        if ($baselineHash === '') {
            $this->writeState(['baseline_hash' => $currentHash, 'last_status' => 'first_baseline']);
            $this->log('first_baseline', ['source' => $source, 'current_hash' => $currentHash]);
            return ['status' => 'first_baseline', 'source' => $source, 'current_hash' => $currentHash];
        }

        if ($currentHash === $baselineHash) {
            $this->log('unchanged', ['source' => $source, 'current_hash' => $currentHash]);
            return ['status' => 'unchanged', 'source' => $source, 'current_hash' => $currentHash];
        }

        $operatorUserId = (int) ($options['operator_user_id'] ?? 0);
        if ($operatorUserId >= 1) {
            $permissions = array_values(array_map('strval', (array) ($options['permissions'] ?? [])));
            if ($permissions === []) {
                $permissions = [self::PERMISSION];
            }
            $operator = [$operatorUserId, $permissions];
        } else {
            $operator = $this->resolveOperator($pdo);
        }
        if ($operator === null) {
            $this->log('no_authorized_operator', [
                'source' => $source,
                'current_hash' => $currentHash,
                'reason' => 'no staff user holds academic_view; interpretation deferred',
            ], 'warning');
            return ['status' => 'no_authorized_operator', 'source' => $source, 'current_hash' => $currentHash];
        }

        [$userId, $permissions] = $operator;
        $payload = [
            'source' => $source,
            'report_date' => gmdate('Y-m-d'),
            'audience' => 'academics',
            'previous_hash' => $baselineHash,
            'current_hash' => $currentHash,
            'content' => mb_substr($content, 0, self::MAX_CONTENT_CHARS),
        ];
        $jobId = call_user_func($this->queuer, self::JOB_TYPE, [
            'workflow_id' => self::WORKFLOW,
            'input' => $payload,
            'user_id' => $userId,
            'permissions' => $permissions,
            'request_id' => 'scheduled:kicd:' . mb_substr($currentHash, 0, 12),
        ]);
        $this->writeState([
            'baseline_hash' => $currentHash,
            'last_status' => 'change_queued',
            'last_job_id' => $jobId,
            'last_source' => $source,
        ]);
        $this->log('change_queued', [
            'source' => $source,
            'previous_hash' => $baselineHash,
            'current_hash' => $currentHash,
            'job_id' => $jobId,
            'operator_id' => $userId,
        ]);
        call_user_func($this->broadcaster, $pdo, 'curriculum', 'curriculum.policy_change', [
            'source' => $source,
            'previous_hash' => $baselineHash,
            'current_hash' => $currentHash,
        ], ['academics', 'system_admin']);

        return ['status' => 'change_queued', 'source' => $source, 'current_hash' => $currentHash, 'previous_hash' => $baselineHash, 'job_id' => $jobId];
    }

    /**
     * Worker stage: create the reviewable interpretation draft under the
     * governed workflow. Idempotent by construction - the change stage only
     * enqueues one job per hash, and a failed draft is journaled, never thrown,
     * keeping the worker safe to retry.
     *
     * @return array{status:string,draft_id?:int,reason?:string}
     */
    public function interpret(PDO $pdo, array $payload): array
    {
        $context = [
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'permissions' => array_values(array_map('strval', (array) ($payload['permissions'] ?? []))),
            'request_id' => substr((string) ($payload['request_id'] ?? 'ai-curriculum-change'), 0, 100),
        ];
        $input = (array) ($payload['input'] ?? []);
        $requestId = $context['request_id'];

        if ($this->drafts === null) {
            $this->drafts = new AiDraftService();
        }
        try {
            $draft = $this->drafts->create($pdo, self::WORKFLOW, $context, $input, [
                'subject_type' => 'curriculum_change',
                'subject_id' => 0,
                'scope' => 'academics',
                'execution' => 'background',
            ]);
            $draftId = (int) ($draft['draft_id'] ?? 0);
            $this->log('interpretation_created', [
                'workflow_id' => self::WORKFLOW,
                'draft_id' => $draftId,
                'operator_id' => $context['user_id'],
                'request_id' => $requestId,
            ]);
            call_user_func($this->broadcaster, $pdo, 'curriculum', 'curriculum.policy_change_interpreted', [
                'draft_id' => $draftId,
                'source' => (string) ($input['source'] ?? ''),
            ], ['academics', 'system_admin']);
            return ['status' => 'interpreted', 'draft_id' => $draftId];
        } catch (Throwable $e) {
            $this->log('interpretation_failed', [
                'workflow_id' => self::WORKFLOW,
                'operator_id' => $context['user_id'],
                'reason' => $e->getMessage(),
                'request_id' => $requestId,
            ], 'error');
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * Fetch the policy document from an allowlisted source.
     *
     * @throws DomainException unsupported source value
     */
    private function fetchContent(string $source): string
    {
        if (strpos($source, 'https://') === 0) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $source,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_USERAGENT => 'KingswayPolicyWatch/1.0',
                CURLOPT_MAXREDIRS => 3,
            ]);
            $body = curl_exec($curl);
            $errno = curl_errno($curl);
            curl_close($curl);
            if ($body === false || $errno !== 0) {
                throw new DomainException('Unable to fetch the KICD policy source.', 502);
            }
            return (string) $body;
        }

        $real = realpath($source);
        $kicdDir = rtrim(dirname($this->stateFile), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($real === false || strpos(rtrim($real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, $kicdDir) !== 0) {
            throw new DomainException('Unsupported KICD policy source; use an https URL or a file under storage/kicd/.', 422);
        }
        $file = (string) @file_get_contents($real);
        return $file === false ? '' : $file;
    }

    /**
     * Pick the staff operator for the interpretation draft. Only users who
     * hold the workflow permission (academic_view) - or the '*' superuser
     * marker - are eligible, mirroring AiWorkflowService::authorize. The SQL
     * projection derives a compact JSON list rather than exposing row details.
     *
     * @return array{0:int,1:array<int,string>}|null
     */
    private function resolveOperator(PDO $pdo): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT user_id
             FROM v_user_permissions_effective
             WHERE user_id IS NOT NULL AND user_id > 0
               AND permission_code IN (?, ?)
             ORDER BY user_id ASC
             LIMIT 1'
        );
        $stmt->execute([self::PERMISSION, '*']);
        $userId = (int) $stmt->fetchColumn();
        if ($userId < 1) {
            return null;
        }
        $stmtAll = $pdo->prepare(
            'SELECT DISTINCT permission_code FROM v_user_permissions_effective WHERE user_id = ?'
        );
        $stmtAll->execute([$userId]);
        $permissions = array_values(array_map('strval', $stmtAll->fetchAll(PDO::FETCH_COLUMN)));
        return [$userId, $permissions];
    }

    private function readState(): array
    {
        $raw = @file_get_contents($this->stateFile);
        if ($raw === false) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeState(array $state): void
    {
        $dir = dirname($this->stateFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $state['updated_at'] = gmdate('c');
        $bytes = @file_put_contents(
            $this->stateFile,
            json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
        if ($bytes === false) {
            throw new DomainException('Unable to persist the KICD policy baseline state.', 500);
        }
    }

    private function log(string $type, array $details, string $level = 'info'): void
    {
        FileLogger::write(self::CATEGORY, array_merge(['type' => $type], $details), $level);
    }
}