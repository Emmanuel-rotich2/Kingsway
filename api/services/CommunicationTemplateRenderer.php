<?php

namespace App\API\Services;

/**
 * CommunicationTemplateRenderer — the single, canonical template renderer.
 *
 * End users draft templates as plain text: they press Enter for a new line and
 * quote people with a friendly token such as `Dear @first_name`. They never
 * write HTML or `{{ }}`. This class turns that authored content into the exact
 * bytes each provider expects.
 *
 * Placeholder syntax (both accepted so existing data keeps working):
 *   - `@first_name`   (user-facing, inserted by the template editor)
 *   - `{{first_name}}` (legacy / programmatic)
 *
 * Only keys present in the supplied variable map are substituted, so ordinary
 * email addresses (info@kingsway.ac.ke) and unknown tokens are left untouched.
 *
 * Channel formatting:
 *   - sms / whatsapp : plain text. Real newlines are preserved exactly. Legacy
 *                      HTML (`<br>`, `<b>`, …) is converted to plain text so a
 *                      provider that sends a plain `message` never shows tags.
 *   - email          : HTML. Authored HTML is passed through untouched; plain
 *                      text is escaped and newlines become `<br>`.
 *   - portal / in_app: HTML, passed through.
 */
class CommunicationTemplateRenderer
{
    /** Matches `@key` or `{{ key }}` tokens. */
    private const PLACEHOLDER = '/(@[A-Za-z_][A-Za-z0-9_]*|\{\{\s*[A-Za-z0-9_]+\s*\}\})/';

    /** Characters that occupy two septets in the GSM-7 extension table. */
    private const GSM7_EXTENSION = "^{}\\[~]|€";

    /**
     * Substitute placeholders, then normalise the result for the channel.
     */
    public function render(string $body, array $variables, string $channel = 'sms'): string
    {
        return $this->formatForChannel($this->substitute($body, $variables), $channel);
    }

    /**
     * Replace only known tokens. Unknown tokens (and bare '@') are preserved.
     */
    public function substitute(string $body, array $variables): string
    {
        if ($body === '' || $variables === []) {
            return $body;
        }

        return (string) preg_replace_callback(self::PLACEHOLDER, function (array $match) use ($variables): string {
            $key = $this->tokenKey($match[1]);
            if ($key === null || !array_key_exists($key, $variables)) {
                return $match[1];
            }
            return (string) $variables[$key];
        }, $body);
    }

    /**
     * Convert authored content into the representation the channel expects.
     */
    public function formatForChannel(string $text, string $channel): string
    {
        $channel = strtolower($channel);

        if (in_array($channel, ['sms', 'whatsapp'], true)) {
            return $this->htmlToPlainText($text);
        }

        if ($channel === 'email') {
            return $this->asEmailHtml($text);
        }

        return $text;
    }

    /**
     * Normalise legacy authored HTML into clean plain text (SMS/WhatsApp).
     * Plain text with no tags is returned byte-for-byte, including newlines.
     */
    public function htmlToPlainText(string $text): string
    {
        if (!preg_match('/<\s*\/?\s*[A-Za-z]/', $text)) {
            return $text;
        }

        $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*(p|div|h[1-6]|li)\s*>/i', "\n\n", $text) ?? $text;
        $text = preg_replace('/<\s*(p|div|h[1-6]|ul|ol|li)[^>]*>/i', '', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Normalise authored content into HTML (email). Authored HTML is preserved.
     */
    public function asEmailHtml(string $text): string
    {
        if (preg_match('/<\s*(p|div|table|h[1-6]|ul|ol|li|br|strong|b|em|i|a|img)\b/i', $text)) {
            return $text;
        }

        return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'), false);
    }

    /**
     * Extract the distinct placeholder keys used by a template body.
     *
     * @return string[]
     */
    public function extractPlaceholders(string $body): array
    {
        if (!preg_match_all(self::PLACEHOLDER, $body, $matches)) {
            return [];
        }

        $keys = [];
        foreach ($matches[1] as $token) {
            $key = $this->tokenKey($token);
            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Estimate SMS cost/segmentation for a rendered message.
     *
     * @return array{length:int,segments:int,encoding:string,unicode:bool,remaining:int}
     */
    public function segments(string $text): array
    {
        $unicode = !$this->isGsm7($text);
        $length = $unicode
            ? mb_strlen($text, 'UTF-8')
            : $this->gsm7Length($text);

        $single = $unicode ? 70 : 160;
        $multi = $unicode ? 67 : 153;

        if ($length <= 0) {
            return ['length' => 0, 'segments' => 0, 'encoding' => $unicode ? 'UCS-2' : 'GSM-7', 'unicode' => $unicode, 'remaining' => $single];
        }

        $segments = $length <= $single ? 1 : (int) ceil($length / $multi);
        $perSegment = $segments === 1 ? $single : $multi;
        $remaining = ($segments * $perSegment) - $length;

        return [
            'length' => $length,
            'segments' => $segments,
            'encoding' => $unicode ? 'UCS-2' : 'GSM-7',
            'unicode' => $unicode,
            'remaining' => $remaining,
        ];
    }

    /**
     * Whether the text is encodable as GSM-7 (no character outside 0x00–0x7F).
     * Non-ASCII forces UCS-2, which roughly halves the per-segment capacity.
     */
    public function isGsm7(string $text): bool
    {
        return !preg_match('/[^\x00-\x7F]/', $text);
    }

    private function gsm7Length(string $text): int
    {
        $length = 0;
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            $length += (strpos(self::GSM7_EXTENSION, $char) !== false) ? 2 : 1;
        }
        return $length;
    }

    private function tokenKey(string $token): ?string
    {
        if ($token !== '' && $token[0] === '@') {
            return substr($token, 1);
        }

        if (preg_match('/\{\{\s*([A-Za-z0-9_]+)\s*\}\}/', $token, $match)) {
            return $match[1];
        }

        return null;
    }
}
