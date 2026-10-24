<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminRequestInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminRequestInboxController extends Controller
{
    public function index(Request $request, AdminRequestInbox $inbox): View
    {
        $request->session()->put('admin_inbox_seen_signature', $inbox->signature());
        $items = $inbox->items();
        $type = $request->string('type')->toString();

        if ($type !== '') {
            $items = $type === 'content'
                ? $items->whereIn('type', ['listing', 'advertisement'])->values()
                : $items->where('type', $type)->values();
        }

        return view('admin.requests.index', [
            'items' => $items,
            'summary' => $inbox->summary(),
            'activeType' => $type,
        ]);
    }

    public function markSeen(Request $request, AdminRequestInbox $inbox): Response
    {
        $request->session()->put('admin_inbox_seen_signature', $inbox->signature());

        return response()->noContent();
    }
}
