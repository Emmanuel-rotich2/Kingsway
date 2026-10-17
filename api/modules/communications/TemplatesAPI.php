<?php

namespace App\API\Modules\communications;

use App\API\Includes\BaseAPI;
use App\API\Services\TemplateManager;
use App\API\Services\CommunicationTemplateRenderer;
use App\API\Services\AuthSessionService;

class TemplatesAPI extends BaseAPI
{
    private TemplateManager $templateManager;
    private CommunicationTemplateRenderer $renderer;

    public function __construct()
    {
        parent::__construct('communications/templates');
        $this->templateManager = new TemplateManager($this->db);
        $this->renderer = new CommunicationTemplateRenderer();
    }

    /**
     * List all templates with optional filtering
     * GET /api/communications/templates?channel=sms&status=active
     */
    public function getTemplates($data): array
    {
        $channel = $data['channel'] ?? null;
        $status = $data['status'] ?? null;
        $search = trim((string) ($data['search'] ?? ''));
        $page = max(1, (int) ($data['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($data['per_page'] ?? 15)));

        $templates = $this->templateManager->listTemplates($channel, $status);

        if ($search !== '') {
            $needle = strtolower($search);
            $templates = array_values(array_filter($templates, static function (array $t) use ($needle): bool {
                return str_contains(strtolower((string) ($t['code'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($t['name'] ?? '')), $needle)
                    || str_contains(strtolower((string) ($t['purpose'] ?? '')), $needle);
            }));
        }

        $total = count($templates);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $items = array_slice($templates, ($page - 1) * $perPage, $perPage);

        return [
            'success' => true,
            'templates' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Get a single template by ID
     * GET /api/communications/templates/{id}
     */
    public function getTemplate($data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if (!$id) {
            return ['success' => false, 'message' => 'Template ID required'];
        }

        $template = $this->templateManager->getTemplate($id);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        return [
            'success' => true,
            'template' => $template,
        ];
    }

    /**
     * Get template by code (e.g., sms.fees)
     * GET /api/communications/templates/code/sms.fees
     */
    public function getTemplateByCode($data): array
    {
        $code = $data['code'] ?? '';
        if (!$code) {
            return ['success' => false, 'message' => 'Template code required'];
        }

        $template = $this->templateManager->getTemplateByCode($code);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found'];
        }

        return [
            'success' => true,
            'template' => $template,
        ];
    }

    /**
     * Create a new template
     * POST /api/communications/templates
     * { code, name, purpose, channels: [{channel, subject, body, header, footer, body_format, media_json}] }
     */
    public function postTemplate($data): array
    {
        $user = $this->requireAuth();
        $userId = $user['id'] ?? 1;

        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');
        $purpose = $data['purpose'] ?? 'general';
        $channels = $data['channels'] ?? [];

        if (!$code || !$name) {
            return ['success' => false, 'message' => 'Code and name are required'];
        }

        if (!preg_match('/^[a-z]+\.[a-z_]+$/', $code)) {
            return ['success' => false, 'message' => 'Code must be in format "channel.category" (e.g., sms.fees)'];
        }

        try {
            $catalogId = $this->templateManager->createTemplate([
                'code' => $code,
                'name' => $name,
                'purpose' => $purpose,
                'channels' => $channels,
            ], $userId);

            return [
                'success' => true,
                'message' => 'Template created',
                'catalog_id' => $catalogId,
            ];
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['success' => false, 'message' => 'Template code already exists'];
            }
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update template metadata
     * PUT /api/communications/templates/{id}
     * { name, purpose, status }
     */
    public function putTemplate($data): array
    {
        $this->requireAuth();
        $id = (int) ($data['id'] ?? 0);
        if (!$id) {
            return ['success' => false, 'message' => 'Template ID required'];
        }

        $updated = $this->templateManager->updateTemplate($id, [
            'name' => $data['name'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        return [
            'success' => $updated,
            'message' => $updated ? 'Template updated' : 'No changes made',
        ];
    }

    /**
     * Create a new version for a template
     * POST /api/communications/templates/{id}/versions
     * { channels: [{channel, subject, body, header, footer, body_format, media_json}] }
     */
    public function postTemplateVersion($data): array
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $id = (int) ($data['id'] ?? 0);
        $channels = $data['channels'] ?? [];

        if (!$id || !$channels) {
            return ['success' => false, 'message' => 'Template ID and channels are required'];
        }

        try {
            $versionId = $this->templateManager->createVersion($id, ['channels' => $channels], $userId);
            return [
                'success' => true,
                'message' => 'Version created',
                'version_id' => $versionId,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get a single channel variant
     * GET /api/communications/templates/channels/{channelId}
     */
    public function getTemplateChannel($data): array
    {
        $channelId = (int) ($data['channel_id'] ?? $data['id'] ?? 0);
        if (!$channelId) {
            return ['success' => false, 'message' => 'Channel ID required'];
        }

        $channel = $this->templateManager->getChannel($channelId);
        if (!$channel) {
            return ['success' => false, 'message' => 'Channel not found'];
        }

        return [
            'success' => true,
            'channel' => $channel,
        ];
    }

    /**
     * Update a channel variant
     * PUT /api/communications/templates/channels/{channelId}
     * { subject, body, header, footer, body_format, media_json }
     */
    public function putTemplateChannel($data): array
    {
        $this->requireAuth();
        $channelId = (int) ($data['channel_id'] ?? $data['id'] ?? 0);
        if (!$channelId) {
            return ['success' => false, 'message' => 'Channel ID required'];
        }

        $updated = $this->templateManager->updateChannel($channelId, [
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'] ?? null,
            'header' => $data['header'] ?? null,
            'footer' => $data['footer'] ?? null,
            'body_format' => $data['body_format'] ?? null,
            'media_json' => isset($data['media_json']) ? $data['media_json'] : null,
        ]);

        return [
            'success' => $updated,
            'message' => $updated ? 'Channel updated' : 'No changes made',
        ];
    }

    /**
     * Approve/activate a version
     * POST /api/communications/templates/versions/{versionId}/approve
     */
    public function postApproveVersion($data): array
    {
        $this->requireAuth(['admin', 'communications_manager']);
        $userId = $this->getCurrentUserId();

        $versionId = (int) ($data['version_id'] ?? $data['id'] ?? 0);
        if (!$versionId) {
            return ['success' => false, 'message' => 'Version ID required'];
        }

        try {
            $this->templateManager->approveVersion($versionId, $userId);
            return ['success' => true, 'message' => 'Version approved and activated'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Preview render a template with variables
     * POST /api/communications/templates/preview
     * { channel_id, variables: {...}, channel }
     */
    public function postPreview($data): array
    {
        $channelId = (int) ($data['channel_id'] ?? 0);
        $variables = $data['variables'] ?? [];
        $channel = $data['channel'] ?? null;

        if (!$channelId) {
            return ['success' => false, 'message' => 'Channel ID required'];
        }

        try {
            $rendered = $this->templateManager->previewRender($channelId, $variables, $channel);
            return [
                'success' => true,
                'rendered' => $rendered,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Extract placeholders from a template body
     * POST /api/communications/templates/extract-placeholders
     * { body }
     */
    public function postExtractPlaceholders($data): array
    {
        $body = $data['body'] ?? '';
        $placeholders = $this->templateManager->extractPlaceholders($body);

        return [
            'success' => true,
            'placeholders' => $placeholders,
        ];
    }

    /**
     * Validate variables against template's registered variables
     * POST /api/communications/templates/validate-variables
     * { catalog_id, variables: {...} }
     */
    public function postValidateVariables($data): array
    {
        $catalogId = (int) ($data['catalog_id'] ?? 0);
        $variables = $data['variables'] ?? [];

        if (!$catalogId) {
            return ['success' => false, 'message' => 'Catalog ID required'];
        }

        $result = $this->templateManager->validateVariables($catalogId, $variables);

        return [
            'success' => true,
            'valid' => empty($result['errors']),
            'errors' => $result['errors'],
            'warnings' => $result['warnings'],
        ];
    }

    /**
     * Delete a template
     * DELETE /api/communications/templates/{id}
     */
    public function deleteTemplate($data): array
    {
        $this->requireAuth(['admin']);
        $id = (int) ($data['id'] ?? 0);
        if (!$id) {
            return ['success' => false, 'message' => 'Template ID required'];
        }

        $deleted = $this->templateManager->deleteTemplate($id);

        return [
            'success' => $deleted,
            'message' => $deleted ? 'Template deleted' : 'Template not found',
        ];
    }

    /**
     * Get segment/cost estimate for SMS
     * POST /api/communications/templates/estimate-sms-cost
     * { body, variables: {...} }
     */
    public function postEstimateSmsCost($data): array
    {
        $body = $data['body'] ?? '';
        $variables = $data['variables'] ?? [];

        $rendered = $this->renderer->render($body, $variables, 'sms');
        $segments = $this->renderer->segments($rendered);

        return [
            'success' => true,
            'rendered_body' => $rendered,
            'segments' => $segments,
            'estimated_cost' => $segments['segments'], // 1 unit per segment
        ];
    }
}