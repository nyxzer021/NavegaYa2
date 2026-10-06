<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicLocaleController
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', 'in:es,en']]);
        $request->session()->put('public_locale', $data['locale']);

        return back()->withCookie(cookie('public_locale', $data['locale'], 60 * 24 * 365));
    }
}
