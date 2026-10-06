<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('public_locale')
            ?? $request->cookie('public_locale')
            ?? 'es';

        if (! in_array($locale, ['es', 'en'], true)) {
            $locale = 'es';
        }

        $request->session()->put('public_locale', $locale);
        App::setLocale($locale);

        return $next($request);
    }
}
