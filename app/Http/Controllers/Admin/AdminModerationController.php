<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use App\Models\ContentReport;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminModerationController extends Controller
{
    public function index(Request $request): View
    {
        $tab = in_array($request->string('tab')->toString(), ['reports', 'profiles', 'reviews'], true) ? $request->string('tab')->toString() : 'reports';
        $status = $request->string('status')->toString() ?: 'open';
        $reports = ContentReport::with(['reporter', 'moderator'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByRaw("CASE priority WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
            ->latest()->paginate(20)->withQueryString();
        $reports->getCollection()->each(function (ContentReport $report): void {
            $report->setAttribute('content', match ($report->content_type) {
                'company_review' => CompanyReview::with(['organization', 'user'])->find($report->content_id),
                'company_profile' => Organization::find($report->content_id),
                default => null,
            });
        });

        return view('admin.moderation.index', [
            'activeTab' => $tab,
            'reports' => $reports,
            'pendingReviews' => CompanyReview::with(['organization', 'user'])->where('status', 'pending')->latest()->take(20)->get(),
            'openCount' => ContentReport::where('status', 'open')->count(),
            'highCount' => ContentReport::where('status', 'open')->whereIn('priority', ['critical', 'high'])->count(),
            'pendingReviewCount' => CompanyReview::where('status', 'pending')->count(),
            'pendingProfiles' => Organization::where('profile_content_status', 'pending')->latest()->get(),
            'resolvedTodayCount' => ContentReport::where('status', 'resolved')->whereDate('reviewed_at', today())->count(),
        ]);
    }

    public function moderateReview(Request $request, CompanyReview $review): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['publish', 'reject'])], 'notes' => ['nullable', 'string', 'max:1000']]);
        $review->update(['status' => $data['decision'] === 'publish' ? 'published' : 'rejected']);

        return back()->with('success', $data['decision'] === 'publish' ? 'Opinión publicada.' : 'Opinión rechazada.');
    }

    public function resolve(Request $request, ContentReport $report): RedirectResponse
    {
        $data = $request->validate(['resolution' => ['required', Rule::in(['keep', 'hide'])], 'moderator_notes' => ['required', 'string', 'max:1500']]);
        if ($data['resolution'] === 'hide') {
            match ($report->content_type) {
                'company_review' => CompanyReview::whereKey($report->content_id)->update(['status' => 'rejected']),
                'company_profile' => Organization::whereKey($report->content_id)->update(['profile_content_status' => 'hidden']),
                default => null,
            };
        } elseif ($report->content_type === 'company_profile') {
            Organization::whereKey($report->content_id)->update(['profile_content_status' => 'published']);
        }
        $report->update($data + ['status' => 'resolved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return back()->with('success', 'Caso resuelto y registrado en el historial.');
    }

    public function moderateProfile(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['publish', 'hide'])]]);
        $organization->update(['profile_content_status' => $data['decision'] === 'publish' ? 'published' : 'hidden']);

        return back()->with('success', $data['decision'] === 'publish' ? 'Imágenes del perfil publicadas.' : 'Imágenes del perfil rechazadas.');
    }
}
