<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use App\Models\Organization;
use App\Models\OrganizationPhoto;
use App\Models\PhotoComment;
use App\Models\User;
use App\Models\UserSanction;
use App\Support\AuditRecorder;
use App\Support\TravelPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminContentPolicyController extends Controller
{
    public function base(Request $request): View
    {
        $document = TravelPolicy::ensureBase();

        return view('admin.policies.base', [
            'document' => $document,
            'current' => $document->current(),
            'versions' => $document->versions()->orderByDesc('version')->get(),
        ]);
    }

    public function updateBase(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:50000'],
            'summary' => ['nullable', 'string', 'max:300'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $document = TravelPolicy::ensureBase();
        $version = TravelPolicy::publish($document, $data['body_html'], $data['summary'] ?? null, $request->user(), $data['reason']);
        AuditRecorder::record($request, 'travel_base_policy_updated', $version, [
            'version' => $version->version,
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'La política base quedó publicada como versión '.$version->version.'.');
    }

    public function updateCompany(Request $request, Organization $organization): RedirectResponse
    {
        abort_unless($organization->type === 'transport_company', 404);
        $data = $request->validate([
            'body_html' => ['required', 'string', 'max:50000'],
            'summary' => ['nullable', 'string', 'max:300'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $document = TravelPolicy::ensureCompany($organization);
        $version = TravelPolicy::publish($document, $data['body_html'], $data['summary'] ?? null, $request->user(), $data['reason']);
        AuditRecorder::record($request, 'company_travel_policy_updated', $organization, [
            'version' => $version->version,
            'reason' => $data['reason'],
            'edited_by' => $request->user()->name,
        ]);

        return back()->with('success', 'La política de la empresa quedó actualizada. La empresa verá el motivo.');
    }

    public function moderation(): View
    {
        return view('admin.moderation.content', [
            'photos' => OrganizationPhoto::query()->where('status', 'pending')->with('organization')->latest()->limit(30)->get(),
            'comments' => PhotoComment::query()->where('status', 'pending')->with(['user', 'photo.organization'])->latest()->limit(30)->get(),
            'reviews' => CompanyReview::query()->where('status', 'pending')->with(['user', 'organization'])->latest()->limit(30)->get(),
        ]);
    }

    public function updatePhoto(Request $request, OrganizationPhoto $photo): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $photo->update(['status' => $data['status']]);
        AuditRecorder::record($request, 'organization_photo_moderated', $photo, $data);

        return back()->with('success', $data['status'] === 'published' ? 'La foto quedó publicada.' : 'La foto quedó oculta en la página pública.');
    }

    public function updateComment(Request $request, PhotoComment $comment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:published,hidden'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $comment->update(['status' => $data['status']]);
        AuditRecorder::record($request, 'photo_comment_moderated', $comment, $data);

        return back()->with('success', 'El comentario de la foto quedó moderado.');
    }

    public function storeSanction(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'kind' => ['required', 'in:warning,suspension,ban'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'ends_at' => ['nullable', 'date', 'required_if:kind,suspension'],
        ], [
            'ends_at.required_if' => 'Indica hasta cuándo dura la suspensión.',
        ]);
        $user = User::query()->findOrFail($data['user_id']);
        $sanction = UserSanction::create([
            'user_id' => $user->id,
            'kind' => $data['kind'],
            'reason' => $data['reason'],
            'ends_at' => $data['kind'] === 'suspension' ? $data['ends_at'] : null,
            'created_by' => $request->user()->id,
        ]);
        AuditRecorder::record($request, 'user_sanction_created', $sanction, [
            'user_id' => $user->id,
            'kind' => $data['kind'],
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'La sanción quedó registrada.');
    }
}
