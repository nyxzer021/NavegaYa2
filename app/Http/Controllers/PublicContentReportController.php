<?php

namespace App\Http\Controllers;

use App\Models\CompanyReview;
use App\Models\ContentReport;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicContentReportController extends Controller
{
    public function review(Request $request, Organization $organization, CompanyReview $review): RedirectResponse
    {
        abort_unless($review->organization_id === $organization->id && $review->status === 'published', 404);

        return $this->create($request, 'company_review', $review->id);
    }

    public function profile(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->status === 'active', 404);

        return $this->create($request, 'company_profile', $organization->id);
    }

    private function create(Request $request, string $type, int $id): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(['inappropriate', 'harassment', 'discrimination', 'false_information', 'spam', 'personal_data', 'copyright', 'other'])],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
        $priority = in_array($data['reason'], ['inappropriate', 'harassment', 'discrimination', 'personal_data'], true) ? 'high' : 'medium';
        ContentReport::firstOrCreate([
            'reporter_id' => $request->user()->id,
            'content_type' => $type,
            'content_id' => $id,
            'status' => 'open',
        ], $data + ['priority' => $priority]);

        return back()->with('success', 'Recibimos tu reporte. El equipo de NavegaYA revisará el contenido.');
    }
}
