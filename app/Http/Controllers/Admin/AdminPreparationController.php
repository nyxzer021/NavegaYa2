<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AdminPreparationController extends Controller
{
    /**
     * @var list<string>
     */
    private const PAGES = [
        'agencies', 'payment_connections', 'reconciliation', 'base_policies',
    ];

    public function show(string $page): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view('admin.preparation', ['pageKey' => $page]);
    }
}
