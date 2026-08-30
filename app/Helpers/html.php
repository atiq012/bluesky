<?php

// Rich-text sanitizer for Quill/helpdesk fields when HTML Purifier package is unavailable.
if (! function_exists('clean_rich_html')) {
    function clean_rich_html(?string $html): string
    {
        $html = (string) ($html ?? '');
        if ($html === '') {
            return '';
        }

        $allowed = '<p><br><strong><b><em><i><u><s><ol><ul><li><a><blockquote><code><pre>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on[a-z]+\s*=\s*("|\').*?\1/i', '', $clean) ?? $clean;
        $clean = preg_replace('/href\s*=\s*("|\')\s*javascript:[^"\']*\1/i', 'href="#"', $clean) ?? $clean;
        $clean = preg_replace('/href\s*=\s*("|\')\s*data:text\/html[^"\']*\1/i', 'href="#"', $clean) ?? $clean;

        return trim($clean);
    }
}
