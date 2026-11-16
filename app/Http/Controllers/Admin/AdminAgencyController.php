<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAgencyController extends Controller
{
    public function index(): View
    {
        $agencies = Agency::query()
            ->with('organization')
            ->orderBy('name')
            ->get();

        return view('admin.agencies.index', compact('agencies'));
    }

    public function suspend(Request $request, Agency $agency): RedirectResponse
    {
        $data = $request->validate([
            'suspension_reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);
        $agency->update([
            'status' => 'suspended',
            'suspension_reason' => $data['suspension_reason'],
            'suspended_at' => now(),
            'suspended_by_user_id' => $request->user()->id,
        ]);

        return back()->with('success', __('agencies.suspended_by_admin'));
    }

    public function reactivate(Agency $agency): RedirectResponse
    {
        $agency->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_at' => null,
            'suspended_by_user_id' => null,
        ]);

        return back()->with('success', __('agencies.reactivated_by_admin'));
    }
}
