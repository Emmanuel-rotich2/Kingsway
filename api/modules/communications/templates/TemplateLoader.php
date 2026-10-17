<?php
namespace App\API\Modules\Communications\Templates;

use App\API\Services\CommunicationTemplateRenderer;

class TemplateLoader
{
    private $smsTemplates;
    private $whatsappTemplates;
    private $emailTemplates;
    private CommunicationTemplateRenderer $renderer;

    public function __construct(?CommunicationTemplateRenderer $renderer = null)
    {
        $this->smsTemplates = $this->loadTemplates(__DIR__ . '/communication_templates_sms.json');
        $this->whatsappTemplates = $this->loadTemplates(__DIR__ . '/communication_templates_whatsapp.json');
        $this->emailTemplates = $this->loadTemplates(__DIR__ . '/communication_templates_email.json');
        $this->renderer = $renderer ?? new CommunicationTemplateRenderer();
    }

    private function loadTemplates($file)
    {
        if (!file_exists($file))
            return [];
        $json = file_get_contents($file);
        return json_decode($json, true);
    }

    public function getTemplate($type, $category)
    {
        $templates = match (strtolower((string)$type)) {
            'sms' => $this->smsTemplates,
            'email' => $this->emailTemplates,
            'whatsapp' => $this->whatsappTemplates,
            default => [],
        };
        foreach ($templates as $tpl) {
            if ($tpl['category'] === $category) {
                return $tpl;
            }
        }
        return null;
    }

    public function renderTemplate($template, $variables, string $channel = 'sms')
    {
        $body = $template['template_body'];
        return $this->renderer->render($body, $variables, $channel);
    }

    public function getMedia($template)
    {
        return isset($template['media_urls']) ? $template['media_urls'] : [];
    }
}
