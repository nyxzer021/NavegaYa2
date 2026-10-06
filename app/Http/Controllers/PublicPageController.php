<?php

namespace App\Http\Controllers;

use App\Models\PublicPage;
use Illuminate\View\View;

class PublicPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = PublicPage::where('slug', $slug)->where('is_published', true)->firstOrFail();

        return view('pages.show', compact('page'));
    }
}
