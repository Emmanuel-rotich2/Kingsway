<?php

namespace App\API\Services;

use PDO;
use App\API\Services\CommunicationTemplateRenderer;

class TemplateManager
{
    private PDO $db;
    private CommunicationTemplateRenderer $renderer;

    public function __construct(PDO $db, ?CommunicationTemplateRenderer $renderer = null)
    {
        $this->db = $db;
        $this->renderer = $renderer ?? new CommunicationTemplateRenderer();
    }

    /**
     * List all templates with their channel variants
     */
    public function listTemplates(?string $channel = null, ?string $status = null): array
    {
        $sql = "
            SELECT 
                ctc.id as catalog_id,
                ctc.code,
                ctc.name,
                ctc.purpose,
                ctc.status as catalog_status,
                ctv.id as version_id,
                ctv.version_no,
                ctv.status as version_status,
                ctc2.id as channel_id,
                ctc2.channel,
                ctc2.language_code,
                ctc2.subject,
                ctc2.body,
                ctc2.header,
                ctc2.footer,
                ctc2.body_format,
                ctc2.media_json,
                ctc2.provider_template_id,
                ctc2.provider_template_status,
                ctv.created_at as version_created,
                ctv.approved_at
            FROM communication_template_catalog ctc
            JOIN communication_template_versions ctv ON ctc.id = ctv.template_id
            LEFT JOIN communication_template_channels ctc2 ON ctv.id = ctc2.template_version_id
            WHERE 1=1
        ";

        $params = [];

        if ($status) {
            $sql .= " AND ctc.status = ?";
            $params[] = $status;
        }

        if ($channel) {
            $sql .= " AND ctc2.channel = ?";
            $params[] = $channel;
        }

        $sql .= " ORDER BY ctc.code, ctv.version_no DESC, ctc2.channel";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by catalog
        $grouped = [];
        foreach ($rows as $row) {
            $key = $row['catalog_id'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'catalog_id' => $row['catalog_id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'purpose' => $row['purpose'],
                    'status' => $row['catalog_status'],
                    'versions' => [],
                ];
            }

            if ($row['version_id']) {
                $verKey = $row['version_id'];
                if (!isset($grouped[$key]['versions'][$verKey])) {
                    $grouped[$key]['versions'][$verKey] = [
                        'version_id' => $row['version_id'],
                        'version_no' => $row['version_no'],
                        'status' => $row['version_status'],
                        'created_at' => $row['version_created'],
                        'approved_at' => $row['approved_at'],
                        'channels' => [],
                    ];
                }

                if ($row['channel_id']) {
                    $grouped[$key]['versions'][$verKey]['channels'][$row['channel']] = [
                        'channel_id' => $row['channel_id'],
                        'channel' => $row['channel'],
                        'language_code' => $row['language_code'],
                        'subject' => $row['subject'],
                        'body' => $row['body'],
                        'header' => $row['header'],
                        'footer' => $row['footer'],
                        'body_format' => $row['body_format'],
                        'media_json' => $row['media_json'] ? json_decode($row['media_json'], true) : null,
                        'provider_template_id' => $row['provider_template_id'],
                        'provider_template_status' => $row['provider_template_status'],
                    ];
                }
            }
        }

        // Convert to indexed arrays
        foreach ($grouped as &$cat) {
            $cat['versions'] = array_values($cat['versions']);
            foreach ($cat['versions'] as &$ver) {
                $ver['channels'] = array_values($ver['channels']);
            }
        }
        unset($cat, $ver);

        $this->attachDeclaredVariables($grouped);

        return array_values($grouped);
    }

    /**
     * Declared variables for one channel variant, keyed by variable name.
     *
     * communication_template_variables is addressed by template_channel_id;
     * there is no catalog-level or version-level variable store.
     */
    public function channelVariables(int $channelId): array
    {
        if ($channelId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT variable_name, data_type, is_required, example_value
             FROM communication_template_variables
             WHERE template_channel_id = ?
             ORDER BY id'
        );
        $stmt->execute([$channelId]);

        $variables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $variables[(string) $row['variable_name']] = [
                'variable_name' => (string) $row['variable_name'],
                'data_type' => (string) ($row['data_type'] ?? 'string'),
                'is_required' => (int) $row['is_required'] === 1,
                'example_value' => $row['example_value'],
            ];
        }

        return $variables;
    }

    /**
     * Attach declared variables to every channel variant of a grouped catalog
     * list in one query, so list/get never issues a query per channel.
     */
    private function attachDeclaredVariables(array &$grouped): void
    {
        $channelIds = [];
        foreach ($grouped as $cat) {
            foreach ($cat['versions'] ?? [] as $ver) {
                foreach ($ver['channels'] ?? [] as $ver['ch']) {
                    if (!empty($ver['channel_id'])) {
                        $channelIds[] = (int) $ver['channel_id'];
                    }
                }
            }
        }
        $channelIds = array_values(array_unique($channelIds));
        if (!$channelIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT template_channel_id, variable_name, data_type, is_required, example_value
             FROM communication_template_variables
             WHERE template_channel_id IN ({$placeholders})
             ORDER BY id"
        );
        $stmt->execute($channelIds);

        $byChannel = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byChannel[(int) $row['template_channel_id']][] = [
                'variable_name' => (string) $row['variable_name'],
                'data_type' => (string) ($row['data_type'] ?? 'string'),
                'is_required' => (int) $row['is_required'] === 1,
                'example_value' => $row['example_value'],
            ];
        }

        foreach ($grouped as &$cat) {
            foreach ($cat['versions'] as &$ver) {
                foreach ($ver['channels'] as &$ch) {
                    $ch['variables'] = array_values($byChannel[(int) $ch['channel_id']] ?? []);
                }
            }
        }
    }

    /**
     * Register (or re-register) the declared variables of a channel variant.
     *
     * Placeholders already present in the body are always registered so that
     * validateVariables() can never report a template's own tokens as unknown.
     */
    public function saveChannelVariables(int $channelId, array $variables): void
    {
        if ($channelId <= 0) {
            return;
        }

        // Read only the body: the channel projection also carries header/footer/
        // body_format/media_json, which exist only once
        // 20261019_fix_communication_channels_columns.sql has been applied, and
        // registering variables must not depend on those optional columns.
        $bodyStmt = $this->db->prepare('SELECT body FROM communication_template_channels WHERE id = ?');
        $bodyStmt->execute([$channelId]);
        $body = (string) ($bodyStmt->fetchColumn() ?: '');

        $declared = [];
        foreach ($variables as $name => $meta) {
            $name = is_string($name) ? $name : (string) ($meta['variable_name'] ?? '');
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $declared[$name] = [
                'data_type' => is_array($meta) ? (string) ($meta['data_type'] ?? 'string') : 'string',
                'is_required' => is_array($meta) ? (int) !empty($meta['is_required']) : 0,
                'example_value' => is_array($meta) ? ($meta['example_value'] ?? null) : null,
            ];
        }

        foreach ($this->renderer->extractPlaceholders($body) as $placeholder) {
            if (!isset($declared[$placeholder])) {
                $declared[$placeholder] = ['data_type' => 'string', 'is_required' => 0, 'example_value' => null];
            }
        }

        if (!$declared) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO communication_template_variables
                 (template_channel_id, variable_name, data_type, is_required, example_value)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 data_type = VALUES(data_type),
                 is_required = VALUES(is_required),
                 example_value = VALUES(example_value)'
        );
        foreach ($declared as $name => $meta) {
            $stmt->execute([
                $channelId,
                $name,
                in_array($meta['data_type'], ['string', 'integer', 'decimal', 'date', 'datetime', 'url', 'boolean'], true)
                    ? $meta['data_type'] : 'string',
                $meta['is_required'],
                $meta['example_value'] !== null ? (string) $meta['example_value'] : null,
            ]);
        }
    }

    /**
     * Get a single template with all versions and channels
     */
    public function getTemplate(int $catalogId): ?array
    {
        $list = $this->listTemplates();
        foreach ($list as $tpl) {
            if ($tpl['catalog_id'] == $catalogId) {
                return $tpl;
            }
        }
        return null;
    }

    /**
     * Get template by code (e.g., 'sms.fees')
     */
    public function getTemplateByCode(string $code): ?array
    {
        $list = $this->listTemplates();
        foreach ($list as $tpl) {
            if ($tpl['code'] === $code) {
                return $tpl;
            }
        }
        return null;
    }

    /**
     * Create a new template catalog entry
     */
    public function createTemplate(array $data, int $createdBy): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO communication_template_catalog (code, name, purpose, status, created_by)
                VALUES (?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $data['code'],
                $data['name'],
                $data['purpose'] ?? 'general',
                $createdBy,
            ]);
            $catalogId = (int) $this->db->lastInsertId();

            // Create initial version
            $stmt = $this->db->prepare("
                INSERT INTO communication_template_versions (template_id, version_no, status, created_by)
                VALUES (?, 1, 'draft', ?)
            ");
            $stmt->execute([$catalogId, $createdBy]);
            $versionId = (int) $this->db->lastInsertId();

            // Create channel entries if provided
            if (!empty($data['channels'])) {
                foreach ($data['channels'] as $channelData) {
                    $channelId = $this->createChannel($versionId, $channelData);
                    // Always register: saveChannelVariables() also extracts the
                    // {{placeholders}} already present in the body, so seeded
                    // templates are never left with an empty variable registry.
                    $this->saveChannelVariables($channelId, (array) ($channelData['variables'] ?? []));
                }
            }

            $this->db->commit();
            return $catalogId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create a new version for an existing template
     */
    public function createVersion(int $catalogId, array $data, int $createdBy): int
    {
        $this->db->beginTransaction();
        try {
            // Get next version number
            $stmt = $this->db->prepare("
                SELECT MAX(version_no) as max_ver FROM communication_template_versions WHERE template_id = ?
            ");
            $stmt->execute([$catalogId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $nextVersion = (($row['max_ver'] ?? 0) + 1);

            $stmt = $this->db->prepare("
                INSERT INTO communication_template_versions (template_id, version_no, status, created_by)
                VALUES (?, ?, 'draft', ?)
            ");
            $stmt->execute([$catalogId, $nextVersion, $createdBy]);
            $versionId = (int) $this->db->lastInsertId();

            // Create channel entries
            if (!empty($data['channels'])) {
                foreach ($data['channels'] as $channelData) {
                    $channelId = $this->createChannel($versionId, $channelData);
                    // Always register: saveChannelVariables() also extracts the
                    // {{placeholders}} already present in the body, so seeded
                    // templates are never left with an empty variable registry.
                    $this->saveChannelVariables($channelId, (array) ($channelData['variables'] ?? []));
                }
            }

            $this->db->commit();
            return $versionId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Create or update a channel for a version
     */
    public function createChannel(int $versionId, array $data): int
    {
        $channel = $data['channel'] ?? 'sms';
        $languageCode = $data['language_code'] ?? 'en';

        $stmt = $this->db->prepare("
            INSERT INTO communication_template_channels
                (template_version_id, channel, language_code, subject, body, header, footer, body_format, media_json)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                subject = VALUES(subject),
                body = VALUES(body),
                header = VALUES(header),
                footer = VALUES(footer),
                body_format = VALUES(body_format),
                media_json = VALUES(media_json)
        ");

        $mediaJson = isset($data['media_json']) ? json_encode($data['media_json']) : null;

        $stmt->execute([
            $versionId,
            $channel,
            $languageCode,
            $data['subject'] ?? null,
            $data['body'] ?? '',
            $data['header'] ?? null,
            $data['footer'] ?? null,
            $data['body_format'] ?? ($channel === 'email' ? 'html' : 'plain'),
            $mediaJson,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a channel
     */
    public function updateChannel(int $channelId, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowed = ['subject', 'body', 'header', 'footer', 'body_format', 'media_json', 'provider_template_id', 'provider_template_name', 'provider_template_status'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                if ($field === 'media_json') {
                    $params[] = $data[$field] !== null ? json_encode($data[$field]) : null;
                } else {
                    $params[] = $data[$field];
                }
            }
        }

        if (!$fields) {
            return false;
        }

        $params[] = $channelId;

        $stmt = $this->db->prepare("
            UPDATE communication_template_channels
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        $updated = $stmt->execute($params);

        // Keep the declared-variable registry in step with the edited body so a
        // body edit can never leave its own placeholders unregistered.
        if (isset($data['body'])) {
            $this->saveChannelVariables($channelId, (array) ($data['variables'] ?? []));
        }

        return $updated;
    }

    /**
     * Get a single channel variant with its parent version/catalog metadata.
     */
    public function getChannel(int $channelId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ctc2.id AS channel_id,
                ctc2.channel,
                ctc2.language_code,
                ctc2.subject,
                ctc2.body,
                ctc2.header,
                ctc2.footer,
                ctc2.body_format,
                ctc2.media_json,
                ctc2.provider_template_id,
                ctc2.provider_template_status,
                ctv.id AS version_id,
                ctv.version_no,
                ctc.id AS catalog_id,
                ctc.code,
                ctc.name,
                ctc.purpose
            FROM communication_template_channels ctc2
            JOIN communication_template_versions ctv ON ctv.id = ctc2.template_version_id
            JOIN communication_template_catalog ctc ON ctc.id = ctv.template_id
            WHERE ctc2.id = ?
            LIMIT 1
        ");
        $stmt->execute([$channelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['media_json'] = $row['media_json'] ? json_decode($row['media_json'], true) : null;
        $row['variables'] = array_values($this->channelVariables($channelId));
        return $row;
    }

    /**
     * Update template catalog metadata
     */
    public function updateTemplate(int $catalogId, array $data): bool
    {
        $fields = [];
        $params = [];

        $allowed = ['name', 'purpose', 'status'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $params[] = $data[$field];
            }
        }

        if (!$fields) {
            return false;
        }

        $params[] = $catalogId;

        $stmt = $this->db->prepare("
            UPDATE communication_template_catalog
            SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute($params);
    }

    /**
     * Approve/activate a version
     */
    public function approveVersion(int $versionId, int $approvedBy): bool
    {
        $this->db->beginTransaction();
        try {
            // Retire other active versions of same template
            $stmt = $this->db->prepare("
                SELECT template_id FROM communication_template_versions WHERE id = ?
            ");
            $stmt->execute([$versionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new \InvalidArgumentException("Version not found");
            }

            $templateId = $row['template_id'];

            $stmt = $this->db->prepare("
                UPDATE communication_template_versions
                SET status = 'retired'
                WHERE template_id = ? AND status = 'active'
            ");
            $stmt->execute([$templateId]);

            // Approve this version
            $stmt = $this->db->prepare("
                UPDATE communication_template_versions
                SET status = 'active', approved_by = ?, approved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$approvedBy, $versionId]);

            // Also set catalog to active
            $stmt = $this->db->prepare("
                UPDATE communication_template_catalog
                SET status = 'active', updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$templateId]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Delete a template (cascades to versions/channels)
     */
    public function deleteTemplate(int $catalogId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM communication_template_catalog WHERE id = ?");
        return $stmt->execute([$catalogId]);
    }

    /**
     * Preview render a template with variables
     */
    public function previewRender(int $channelId, array $variables, ?string $channel = null): array
    {
        $stmt = $this->db->prepare("
            SELECT ctc2.channel, ctc2.body_format, ctc2.subject, ctc2.body, ctc2.header, ctc2.footer
            FROM communication_template_channels ctc2
            WHERE ctc2.id = ?
        ");
        $stmt->execute([$channelId]);
        $tpl = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tpl) {
            throw new \InvalidArgumentException("Channel not found");
        }

        $channel = $channel ?? $tpl['channel'];

        $rendered = [
            'subject' => $this->renderer->render($tpl['subject'] ?? '', $variables, $channel),
            'body' => $this->renderer->render($tpl['body'], $variables, $channel),
        ];

        if ($channel === 'email') {
            $rendered['header'] = $this->renderer->render($tpl['header'] ?? '', $variables, $channel);
            $rendered['footer'] = $this->renderer->render($tpl['footer'] ?? '', $variables, $channel);
        }

        if (!empty($tpl['media_json'])) {
            $rendered['media'] = json_decode($tpl['media_json'], true);
        }

        // Add segment estimate for SMS
        if ($channel === 'sms') {
            $rendered['segments'] = $this->renderer->segments($rendered['body']);
        }

        return $rendered;
    }

    /**
     * Get placeholder variables used in a template body
     */
    public function extractPlaceholders(string $body): array
    {
        return $this->renderer->extractPlaceholders($body);
    }

    /**
     * Validate template variables against registered variables
     */
    public function validateVariables(int $catalogId, array $variables): array
    {
        // communication_template_variables is keyed by template_channel_id, so
        // the registered set for a catalog spans every channel of every version.
        $stmt = $this->db->prepare("
            SELECT var.variable_name, var.is_required, var.data_type
            FROM communication_template_variables var
            JOIN communication_template_channels ch ON ch.id = var.template_channel_id
            JOIN communication_template_versions ver ON ver.id = ch.template_version_id
            WHERE ver.template_id = ?
        ");
        $stmt->execute([$catalogId]);
        $registered = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $errors = [];
        $warnings = [];

        $registeredNames = array_column($registered, 'variable_name');

        foreach ($registered as $reg) {
            if ($reg['is_required'] && !array_key_exists($reg['variable_name'], $variables)) {
                $errors[] = "Required variable '{$reg['variable_name']}' is missing";
            }
        }

        foreach ($variables as $key => $value) {
            if (!in_array($key, $registeredNames, true)) {
                $warnings[] = "Variable '$key' is not registered for this template";
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    /**
     * The school's own name, for brand text inside message bodies.
     */
    public function schoolBrand(): string
    {
        if (defined('SCHOOL_NAME') && trim((string) SCHOOL_NAME) !== '') {
            return trim((string) SCHOOL_NAME);
        }

        return 'Kingsway Preparatory School';
    }

    /**
     * Legacy brand string that was seeded into the catalog before the school
     * profile carried the real name. Kept here so the normalizer can find and
     * repair those rows without touching rows the school has since edited.
     */
    private const LEGACY_BRAND = 'Kingsway Academy';

    /**
     * Rewrite the legacy brand inside catalog channel rows.
     *
     * Idempotent: a row that already carries the configured school name is left
     * untouched, and only rows still containing the legacy string are updated,
     * so operator edits elsewhere are never clobbered.
     *
     * @return array{updated:int,brand:string}
     */
    public function normalizeBranding(): array
    {
        $brand = $this->schoolBrand();
        if ($brand === self::LEGACY_BRAND) {
            return ['updated' => 0, 'brand' => $brand];
        }

        $stmt = $this->db->prepare(
            "UPDATE communication_template_channels
                SET subject = REPLACE(subject, ?, ?),
                    body = REPLACE(body, ?, ?)
              WHERE subject LIKE ? OR body LIKE ?"
        );
        $stmt->execute([self::LEGACY_BRAND, $brand, self::LEGACY_BRAND, self::LEGACY_BRAND, self::LEGACY_BRAND, self::LEGACY_BRAND]);

        return ['updated' => $stmt->rowCount(), 'brand' => $brand];
    }

    /**
     * Bring the canonical catalog back in step with its legacy JSON source.
     *
     * The catalog is the single store that resolveTemplate() reads; the JSON
     * files under api/modules/communications/templates/ remain the seed source.
     * A code that already exists in the catalog is never overwritten, so a
     * school's edited wording survives every sync.
     *
     * @param list<string> $categories Legacy template categories to import
     * @return array{branded:int,seeded:list<array{code:string,channel:string}>,skipped:list<string>,failed:list<array{code:string,channel:string,error:string}>}
     */
    public function synchronizeCatalog(array $categories = ['results_ready', 'results', 'fees', 'announcement', 'notification', 'parent_event']): array
    {
        $loader = new \App\API\Modules\communications\templates\TemplateLoader($this->renderer);
        $seeded = [];
        $skipped = [];
        $failed = [];

        foreach (['sms', 'email', 'whatsapp'] as $channel) {
            foreach ($categories as $category) {
                $code = $channel . '.' . $category;
                if ($this->catalogCodeExists($code)) {
                    $skipped[] = $code;
                    continue;
                }

                $legacy = $loader->getTemplate($channel, $category);
                if (!$legacy || empty($legacy['template_body'])) {
                    continue;
                }

                try {
                    $this->ensureCatalogTemplate($code, [
                        'name' => (string) ($legacy['name'] ?? $category),
                        'purpose' => $category,
                        'channels' => [[
                            'channel' => $channel,
                            'subject' => $legacy['subject'] ?? null,
                            'body' => (string) $legacy['template_body'],
                            'body_format' => $channel === 'email' ? 'html' : 'plain',
                        ]],
                    ]);
                } catch (\Throwable $e) {
                    // A missing optional column or an edit collision must not
                    // abort the whole reconciliation; report it and continue so
                    // the remaining templates still get repaired.
                    $failed[] = ['code' => $code, 'channel' => $channel, 'error' => $e->getMessage()];
                    continue;
                }
                $seeded[] = ['code' => $code, 'channel' => $channel];
            }
        }

        $branding = $this->normalizeBranding();

        return [
            'branded' => $branding['updated'],
            'seeded' => $seeded,
            'skipped' => $skipped,
            'failed' => $failed,
            'brand' => $branding['brand'],
        ];
    }

    private function catalogCodeExists(string $code): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM communication_template_catalog WHERE code = ?');
        $stmt->execute([$code]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Resolve the acting user id for audit columns.
     *
     * TemplateManager is a service (not a BaseAPI), so it cannot call the
     * controller helper getCurrentUserId(). Read the middleware-populated
     * session context instead, and fall back to 0 for CLI/worker callers
     * (created_by is nullable and has no foreign key).
     */
    private function resolveActorId(): int
    {
        $context = $_SERVER['auth_user'] ?? [];
        if (!is_array($context)) {
            return 0;
        }
        return (int) ($context['id'] ?? $context['user_id'] ?? 0);
    }

    /**
     * Create a catalog template (draft version + active channel) if the code is
     * absent. Returns the catalog id, or 0 when it already existed.
     */
    private function ensureCatalogTemplate(string $code, array $definition): int
    {
        if ($this->catalogCodeExists($code)) {
            return 0;
        }

        $createdBy = $this->resolveActorId();
        $catalogId = $this->createTemplate([
            'code' => $code,
            'name' => (string) ($definition['name'] ?? $code),
            'purpose' => (string) ($definition['purpose'] ?? 'general'),
            'channels' => (array) ($definition['channels'] ?? []),
        ], $createdBy);

        $versionId = (int) $this->db->query(
            'SELECT id FROM communication_template_versions WHERE template_id = ' . (int) $catalogId . ' ORDER BY version_no LIMIT 1'
        )->fetchColumn();
        if ($versionId > 0) {
            $this->approveVersion($versionId, $createdBy);
        }

        return $catalogId;
    }
}
