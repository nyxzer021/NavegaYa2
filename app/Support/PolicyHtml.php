<?php

namespace App\Support;

class PolicyHtml
{
    public static function clean(?string $html): string
    {
        $html = (string) $html;
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#</?script\b[^>]*>#is', '', $html) ?? '';
        $html = preg_replace('#\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';
        $html = preg_replace('#(href|src)\s*=\s*([\'"])\s*javascript:[^\'"]*\2#i', '$1=$2#$2', $html) ?? '';

        return strip_tags($html, '<p><br><strong><em><b><i><ul><ol><li><h1><h2><h3><h4><a><blockquote><hr>');
    }
}
