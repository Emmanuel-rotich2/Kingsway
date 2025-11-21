<?php
namespace App\API\Modules\Communications\Templates;

class TemplateLoader
{
    private $smsTemplates;
    private $whatsappTemplates;

    public function __construct()
    {
        $this->smsTemplates = $this->loadTemplates(__DIR__ . '/communication_templates_sms.json');
        $this->whatsappTemplates = $this->loadTemplates(__DIR__ . '/communication_templates_whatsapp.json');
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
        $templates = $type === 'sms' ? $this->smsTemplates : $this->whatsappTemplates;
        foreach ($templates as $tpl) {
            if ($tpl['category'] === $category) {
                return $tpl;
            }
        }
        return null;
    }

    public function renderTemplate($template, $variables)
    {
        $body = $template['template_body'];
        foreach ($variables as $key => $value) {
            $body = str_replace('{{' . $key . '}}', $value, $body);
        }
        return $body;
    }

    public function getMedia($template)
    {
        return isset($template['media_urls']) ? $template['media_urls'] : [];
    }
}
