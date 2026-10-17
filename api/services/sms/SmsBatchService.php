<?php

declare(strict_types=1);

namespace App\API\Services\sms;

use App\API\Includes\FileLogger;
use App\API\Services\JobQueue;
use App\API\Services\ReadReplicaService;
use App\API\Services\StorageRoot;
use App\API\Services\SystemCommsConfigService;
use App\API\Services\whatsapp\WhatsAppGateway;
use PDO;

/**
 * Durable bulk-messaging batch — one PHP-owned queue job per send.
 *
 * The operator's send creates the communications row, recipient rows and
 * endpoint rows exactly as the synchronous path does; this service then moves
 * delivery into a single `communications.sms_batch` job:
 *
 *   - Python claims the job, re-authorizes through the existing
 *     python-job-input boundary, sends through the TALKSASA provider in
 *     Python, and writes per-recipient results as JSONL under the shared
 *     storage root's python namespace.
 *   - PHP validates the bounded completion descriptor, applies the per-row
 *     outcomes to the communications ledger, then deletes the results file.
 *   - When Python is disabled or its lease is recovered, the PHP queue worker
 *     runs the same batch through SmsBatchService::runPhpFallback (the
 *     gateway itself still prefers the Python relay per call, so a Flask
 *     outage degrades to the stream transport instead of stopping bulk work).
 *
 * The results contract is intentionally bounded: the completion descriptor
 * carries counts plus at most 50 failures; the full per-row detail lives only
 * in the results file, which is read once and removed.
 */
final class SmsBatchService
{
    private const RESULTS_DIRECTORY = 'communications/sms_batches';
    private const RESULTS_PREFIX = 'sms_';
    private const MAX_RESULTS_BYTES = 64 * 1024 * 1024;
    private const MAX_REPORTED_FAILURES = 50;
    private const MAX_RECIPIENTS = 20000;
    private const APPLY_CHUNK = 500;

    /** Message modes the batch runner owns (campaign is one provider call). */
    public const BATCH_MODES = ['sms', 'whatsapp', 'voice', 'mms', 'hashed'];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Move an already-created communication onto the durable batch job.
     *
     * @param array<int, array{id:int, recipient_id:int, address:string}> $endpoints
     * @return array{job_id:int, queued:int}|null Null keeps the caller on its
     *         synchronous delivery path (relay unavailable or nothing to do).
     */
    public function stage(int $communicationId, string $mode, array $endpoints, ?int $operatorId = null): ?array
    {
        if ($communicationId <= 0 || $endpoints === [] || !in_array($mode, self::BATCH_MODES, true)) {
            return null;
        }
        if (!(new SmsPythonRelay())->available()) {
            return null;
        }

        // 'batched' is not a queued state: the outbox only selects queued and
        // due-scheduled rows, so the batch job is the single delivery owner.
        $this->db->prepare("UPDATE communications SET status = 'batched', next_attempt_at = NULL WHERE id = ?")
            ->execute([$communicationId]);

        $jobId = JobQueue::push('communications.sms_batch', [
            'communication_id' => $communicationId,
            'batch_ref' => (string) $communicationId,
            'mode' => $mode,
            'user_id' => $operatorId ?? 0,
            'idempotency_key' => 'sms_batch:' . $communicationId,
        ], 0, 3, 60);

        FileLogger::write('communications', [
            'type' => 'sms_batch_staged',
            'communication_id' => $communicationId,
            'mode' => $mode,
            'recipients' => count($endpoints),
            'job_id' => $jobId,
        ]);

        return ['job_id' => $jobId, 'queued' => count($endpoints)];
    }

    /**
     * Authorized input for the Python worker (bounded, fresh from the DB).
     *
     * @return array<string, mixed>
     */
    public function preparePythonJob(array $payload, PDO $pdo): array
    {
        $communicationId = $this->payloadCommunicationId($payload);
        $communication = $this->fetchCommunication($pdo, $communicationId);
        $mode = (string) ($payload['mode'] ?? 'sms');
        $channelMeta = json_decode((string) ($communication['channel_meta'] ?? ''), true);
        $channelMeta = is_array($channelMeta) ? $channelMeta : [];

        $endpoints = $this->fetchEndpoints($pdo, $communicationId);
        if ($endpoints === []) {
            throw new \RuntimeException('SMS batch has no deliverable recipients.');
        }
        if (count($endpoints) > self::MAX_RECIPIENTS) {
            throw new \RuntimeException('SMS batch exceeds the recipient limit.');
        }

        $configService = new SystemCommsConfigService($pdo);
        $providerConfig = $mode === 'whatsapp'
            ? $configService->effectiveWhatsAppConfig()
            : $configService->effectiveSmsConfig();

        return [
            'batch_ref' => (string) $communicationId,
            'mode' => $mode,
            'message' => (string) ($communication['body'] ?? ''),
            'schedule_time' => $channelMeta['schedule_time'] ?? null,
            'media_url' => $channelMeta['media_url'] ?? null,
            'language' => $channelMeta['language'] ?? null,
            'gender' => $channelMeta['gender'] ?? null,
            'recipient_format' => $channelMeta['recipient_format'] ?? null,
            'provider' => [
                'api_key' => (string) ($providerConfig['api_key'] ?? ''),
                'api_url' => (string) ($providerConfig['api_url'] ?? ''),
                'sender_id' => (string) ($providerConfig['sender_id'] ?? ($providerConfig['wa_number'] ?? '')),
            ],
            'recipients' => array_map(static function (array $endpoint): array {
                return ['endpoint_id' => (int) $endpoint['id'], 'address' => (string) $endpoint['address']];
            }, $endpoints),
        ];
    }

    /**
     * Validate the Python completion descriptor and apply the results file.
     *
     * @return array<string, mixed> Bounded descriptor persisted on the job.
     */
    public function applyPythonResult(array $payload, array $result, PDO $pdo): array
    {
        $communicationId = $this->payloadCommunicationId($payload);
        $batchRef = (string) ($result['batch_ref'] ?? '');
        if ($batchRef !== (string) $communicationId) {
            throw new \RuntimeException('SMS batch result does not match the staged communication.');
        }

        $resultsFile = (string) ($result['results_file'] ?? '');
        $expected = self::RESULTS_DIRECTORY . '/' . self::RESULTS_PREFIX . $communicationId . '.jsonl';
        if ($resultsFile !== $expected) {
            throw new \RuntimeException('SMS batch result file path is not authorized.');
        }
        $absolute = StorageRoot::path('python/' . $resultsFile);
        if (!is_file($absolute) || filesize($absolute) > self::MAX_RESULTS_BYTES) {
            throw new \RuntimeException('SMS batch result file is missing or exceeds the limit.');
        }

        $sent = 0;
        $failed = 0;
        $failures = [];
        $buffer = [];
        $handle = fopen($absolute, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('SMS batch result file could not be opened.');
        }
        try {
            while (($line = fgets($handle)) !== false) {
                $row = json_decode(trim($line), true);
                if (!is_array($row) || empty($row['endpoint_id'])) {
                    continue;
                }
                $buffer[] = $row;
                if (count($buffer) >= self::APPLY_CHUNK) {
                    [$chunkSent, $chunkFailed, $chunkFailures] = $this->applyRows($pdo, $communicationId, $buffer);
                    $sent += $chunkSent;
                    $failed += $chunkFailed;
                    $failures = array_slice(array_merge($failures, $chunkFailures), 0, self::MAX_REPORTED_FAILURES);
                    $buffer = [];
                }
            }
        } finally {
            fclose($handle);
        }
        if ($buffer !== []) {
            [$chunkSent, $chunkFailed, $chunkFailures] = $this->applyRows($pdo, $communicationId, $buffer);
            $sent += $chunkSent;
            $failed += $chunkFailed;
            $failures = array_slice(array_merge($failures, $chunkFailures), 0, self::MAX_REPORTED_FAILURES);
        }

        $this->finalizeCommunication($pdo, $communicationId, $sent, $failed);
        @unlink($absolute);

        $descriptor = [
            'batch_ref' => (string) $communicationId,
            'total' => $sent + $failed,
            'sent' => $sent,
            'failed' => $failed,
            'failures' => $failures,
        ];
        FileLogger::write('communications', [
            'type' => 'sms_batch_applied',
            'communication_id' => $communicationId,
            'sent' => $sent,
            'failed' => $failed,
            'transport' => 'python',
        ]);
        return $descriptor;
    }

    /**
     * PHP queue-worker resilience path: same batch, executed through the
     * gateways (which still prefer the Python relay per call).
     */
    public function runPhpFallback(array $payload, PDO $pdo): void
    {
        $envelope = $this->preparePythonJob($payload, $pdo);
        $communicationId = $this->payloadCommunicationId($payload);
        $mode = (string) $envelope['mode'];
        $configService = new SystemCommsConfigService($pdo);
        $gateway = $mode === 'whatsapp'
            ? new WhatsAppGateway($configService->effectiveWhatsAppConfig())
            : new SMSGateway($configService->effectiveSmsConfig());

        $sent = 0;
        $failed = 0;
        $failures = [];
        foreach ($envelope['recipients'] as $recipient) {
            $endpointId = (int) $recipient['endpoint_id'];
            try {
                $response = $this->sendOne($gateway, $mode, (string) $recipient['address'], $envelope);
                $this->applyRow($pdo, [
                    'endpoint_id' => $endpointId,
                    'ok' => true,
                    'provider_message_id' => $this->extractUid($response['data'] ?? null),
                    'status' => (string) ($response['status'] ?? 'accepted'),
                    'error' => '',
                ]);
                $sent++;
            } catch (\Throwable $error) {
                $this->applyRow($pdo, [
                    'endpoint_id' => $endpointId,
                    'ok' => false,
                    'provider_message_id' => null,
                    'status' => 'error',
                    'error' => $error->getMessage(),
                ]);
                $failed++;
                if (count($failures) < self::MAX_REPORTED_FAILURES) {
                    $failures[] = ['endpoint_id' => $endpointId, 'error' => mb_substr($error->getMessage(), 0, 300)];
                }
            }
        }
        $this->finalizeCommunication($pdo, $communicationId, $sent, $failed);
        FileLogger::write('communications', [
            'type' => 'sms_batch_applied',
            'communication_id' => $communicationId,
            'sent' => $sent,
            'failed' => $failed,
            'transport' => 'php',
        ]);
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function payloadCommunicationId(array $payload): int
    {
        $id = (int) ($payload['communication_id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('SMS batch payload is missing its communication id.');
        }
        return $id;
    }

    private function fetchCommunication(PDO $pdo, int $communicationId): array
    {
        $stmt = $pdo->prepare("SELECT * FROM communications WHERE id = ?");
        $stmt->execute([$communicationId]);
        $communication = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$communication) {
            throw new \RuntimeException('Staged communication no longer exists.');
        }
        return $communication;
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchEndpoints(PDO $pdo, int $communicationId): array
    {
        $stmt = $pdo->prepare(
            "SELECT e.id, e.address, e.channel, e.attempt_count, r.id AS recipient_row_id
               FROM " . ReadReplicaService::qualifiedRef('communication_recipient_endpoints') . "
               JOIN communication_recipients r ON r.id = e.communication_recipient_id
              WHERE r.communication_id = ?
              ORDER BY e.id"
        );
        $stmt->execute([$communicationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array{0:int,1:int,2:array<int, array{endpoint_id:int,error:string}>}
     */
    private function applyRows(PDO $pdo, int $communicationId, array $rows): array
    {
        $sent = 0;
        $failed = 0;
        $failures = [];
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!empty($row['ok'])) {
                    $this->applyRow($pdo, $row);
                    $sent++;
                } else {
                    $this->applyRow($pdo, $row);
                    $failed++;
                    if (count($failures) < self::MAX_REPORTED_FAILURES) {
                        $failures[] = [
                            'endpoint_id' => (int) ($row['endpoint_id'] ?? 0),
                            'error' => mb_substr((string) ($row['error'] ?? ''), 0, 300),
                        ];
                    }
                }
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        return [$sent, $failed, $failures];
    }

    private function applyRow(PDO $pdo, array $row): void
    {
        $endpointId = (int) ($row['endpoint_id'] ?? 0);
        if ($endpointId <= 0) {
            return;
        }
        $stmt = $pdo->prepare(
            "SELECT id, communication_recipient_id, attempt_count FROM communication_recipient_endpoints WHERE id = ?"
        );
        $stmt->execute([$endpointId]);
        $endpoint = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$endpoint) {
            return;
        }
        $attemptNo = ((int) $endpoint['attempt_count']) + 1;
        $providerId = $row['provider_message_id'] ?? null;
        $error = $row['error'] ?? '';
        $ok = !empty($row['ok']);

        $attempt = $pdo->prepare(
            "INSERT INTO communication_delivery_attempts
                (endpoint_id, attempt_no, request_status, provider_message_id, provider_status, error_message, request_started_at, request_finished_at, raw_response)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)"
        );
        $attempt->execute([
            $endpointId,
            $attemptNo,
            $ok ? 'accepted' : 'error',
            $providerId !== '' ? $providerId : null,
            substr((string) ($row['status'] ?? ($ok ? 'accepted' : 'error')), 0, 100),
            $ok ? null : mb_substr((string) $error, 0, 1000),
            json_encode(['transport' => 'sms_batch', 'status' => $row['status'] ?? null]),
        ]);

        if ($ok) {
            $pdo->prepare(
                "UPDATE communication_recipient_endpoints
                    SET status = 'sent', provider_message_id = ?, provider_status = ?, attempt_count = attempt_count + 1, last_attempt_at = NOW(), delivered_at = NOW(), last_error = NULL
                  WHERE id = ?"
            )->execute([$providerId !== '' ? $providerId : null, substr((string) ($row['status'] ?? 'sent'), 0, 100), $endpointId]);
            $pdo->prepare("UPDATE communication_recipients SET status = 'sent', delivered_at = NOW(), error_message = NULL WHERE id = ?")
                ->execute([(int) $endpoint['communication_recipient_id']]);
        } else {
            $pdo->prepare(
                "UPDATE communication_recipient_endpoints
                    SET status = 'failed', provider_status = 'failed', attempt_count = attempt_count + 1, last_attempt_at = NOW(), last_error = ?
                  WHERE id = ?"
            )->execute([mb_substr((string) $error, 0, 1000), $endpointId]);
            $pdo->prepare("UPDATE communication_recipients SET status = 'failed', error_message = ? WHERE id = ?")
                ->execute([mb_substr((string) $error, 0, 1000), (int) $endpoint['communication_recipient_id']]);
        }
    }

    private function finalizeCommunication(PDO $pdo, int $communicationId, int $sent, int $failed): void
    {
        if ($sent + $failed === 0) {
            $pdo->prepare("UPDATE communications SET status = 'failed', processed_at = NOW(), last_error = ? WHERE id = ?")
                ->execute(['No batch results were produced.', $communicationId]);
            return;
        }
        $status = $failed === 0 ? 'sent' : 'failed';
        $pdo->prepare("UPDATE communications SET status = ?, processed_at = NOW(), next_attempt_at = NULL, last_error = ? WHERE id = ?")
            ->execute([$status, $failed > 0 ? "{$failed} recipient(s) failed" : null, $communicationId]);
    }

    private function sendOne($gateway, string $mode, string $address, array $envelope): array
    {
        switch ($mode) {
            case 'whatsapp':
                return $gateway->sendWhatsApp($address, (string) $envelope['message'], $envelope['media_url'] ?? null);
            case 'voice':
                return $gateway->sendVoice($address, (string) $envelope['message'], $envelope['language'] ?? null, $envelope['gender'] ?? null);
            case 'mms':
                return $gateway->sendMMS($address, (string) $envelope['message'], (string) ($envelope['media_url'] ?? ''));
            case 'hashed':
                return $gateway->sendHashed($address, (string) $envelope['message'], (string) ($envelope['recipient_format'] ?? 'hashed'));
            case 'sms':
            default:
                return $gateway->send($address, (string) $envelope['message']);
        }
    }

    private function extractUid($data): ?string
    {
        if (!is_array($data)) {
            return null;
        }
        foreach (['uid', 'queue_uid', 'message_id', 'messageId', 'id'] as $key) {
            if (!empty($data[$key])) {
                return (string) $data[$key];
            }
        }
        return null;
    }
}
