<?php

namespace App\Support;

use Illuminate\Http\Request;

class CompanyReturnUrl
{
    public static function remember(Request $request): void
    {
        $target = self::sanitize($request->query('redirect'));
        if ($target === null) {
            return;
        }

        redirect()->setIntendedUrl($target);
    }

    public static function pull(Request $request): ?string
    {
        $target = self::sanitize($request->session()->get('url.intended'));
        if ($target === null) {
            return null;
        }

        $request->session()->forget('url.intended');

        return $target;
    }

    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);
        if (str_contains($value, '://') || str_starts_with($value, '//') || str_contains($value, '..')) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $query = parse_url($value, PHP_URL_QUERY);
        if (! is_string($path) || preg_match('#^/empresas/\d+$#', $path) !== 1) {
            return null;
        }
        if (! is_string($query) || preg_match('#(?:^|&)foto=\d+(?:&|$)#', $query) !== 1) {
            return null;
        }

        return $path.'?'.$query;
    }
}
