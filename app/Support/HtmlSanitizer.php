<?php

namespace App\Support;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
    /**
     * Sanitize composer HTML into a safe fragment.
     *
     * Note: we intentionally disallow `<img>` tags because we inject the
     * open-tracking pixel at send-time (per recipient).
     */
    public static function sanitizeComposerHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $config = HTMLPurifier_Config::createDefault();

        // Keep this allowlist narrow to reduce the chance of XSS in email clients.
        $config->set('HTML.Allowed', implode(',', [
            'p',
            'br',
            'strong',
            'em',
            'u',
            'a[href|title|target]',
            'ul',
            'ol',
            'li',
            'blockquote',
        ]));

        // Keep fragments compact and predictable.
        $config->set('AutoFormat.AutoParagraph', 'false');
        $config->set('AutoFormat.RemoveEmpty', 'true');

        // Disable style attributes entirely (Gmail supports many styles, but they are unnecessary for v1).
        $config->set('CSS.AllowedProperties', '');

        return (new HTMLPurifier($config))->purify($html);
    }
}

