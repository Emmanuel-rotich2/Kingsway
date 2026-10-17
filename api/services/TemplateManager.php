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

        return array_values($grouped);
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
                    $this->createChannel($versionId, $channelData);
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
                    $this->createChannel($versionId, $channelData);
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
        return $stmt->execute($params);
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
        $stmt = $this->db->prepare("
            SELECT variable_name, is_required, data_type
            FROM communication_template_variables
            WHERE catalog_id = ?
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
}