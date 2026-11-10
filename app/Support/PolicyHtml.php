<?php

namespace App\Support;

use App\Models\Organization;

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

    public static function present(?string $html, Organization $organization): string
    {
        return self::showPlaceholderValues(self::replaceBaggageSection(self::clean($html), $organization));
    }

    public static function hasPlaceholders(?string $html): bool
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_match('/\[[^\[\]]+\]/u', $text) === 1;
    }

    public static function showPlaceholderValues(string $html): string
    {
        return preg_replace('/\[([^\[\]]+)\]/u', '$1', $html) ?? $html;
    }

    public static function highlightPlaceholders(string $html): string
    {
        return preg_replace('/\[([^\[\]]+)\]/u', '<mark>[$1]</mark>', $html) ?? $html;
    }

    public static function replaceBaggageSection(string $html, Organization $organization): string
    {
        $section = self::baggageSection($organization);
        $pattern = '/<h([1-4])\b[^>]*>\s*(?:<(?:strong|em|b|i|span)[^>]*>\s*)*3\.\s*Equipaje\s*(?:<\/(?:strong|em|b|i|span)>\s*)*<\/h\1>\s*.*?(?=<h[1-4]\b|$)/isu';
        $replaced = preg_replace($pattern, $section, $html, 1, $count);
        if (! is_string($replaced) || $count === 0) {
            return $section.$html;
        }

        return $replaced;
    }

    public static function baggageSection(Organization $organization): string
    {
        $html = '<h1>3. Equipaje</h1>';
        foreach (BaggageAllowance::lines($organization) as $line) {
            $html .= '<p>'.e($line).'</p>';
        }

        return $html;
    }
}
