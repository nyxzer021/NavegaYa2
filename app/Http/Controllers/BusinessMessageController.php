<?php

namespace App\Http\Controllers;

use App\Models\BusinessConversation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BusinessMessageController extends Controller
{
    public function index(Request $request): View
    {
        $isAdmin = $this->isAdmin($request);
        $organizationId = $request->user()->organizations()->value('organizations.id');
        abort_unless($isAdmin || $organizationId, 403);
        $conversations = BusinessConversation::with(['organization.users', 'assignedAdmin', 'messages.sender'])
            ->when(! $isAdmin, fn ($query) => $query->where('organization_id', $organizationId))
            ->latest('updated_at')->get();
        $selected = $conversations->firstWhere('id', (int) $request->query('conversation')) ?: $conversations->first();
        if ($selected) {
            $selected->update([$isAdmin ? 'admin_last_read_at' : 'business_last_read_at' => now()]);
        }
        $organizations = $isAdmin ? Organization::whereIn('type', ['transport_company', 'agency', 'marketplace_business'])->orderBy('commercial_name')->get() : collect();
        $administrators = User::whereHas('roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin']))->orderBy('name')->get();

        $organization = $isAdmin ? null : Organization::with(['vessels.basePort'])->findOrFail($organizationId);
        $isTransport = ! $isAdmin && in_array($organization->type, ['transport_company', 'agency'], true);
        $organizationTypes = ['transport_company' => 'Empresa de transporte', 'agency' => 'Agencia', 'marketplace_business' => 'Empresa anunciante'];

        return view($isAdmin ? 'business-messages.admin' : ($isTransport ? 'business-messages.company' : 'business-messages.business'), compact('conversations', 'selected', 'isAdmin', 'organizations', 'organization', 'administrators', 'organizationTypes'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isAdmin = $this->isAdmin($request);
        $organizationId = $isAdmin ? $request->validate(['organization_id' => ['required', 'exists:organizations,id']])['organization_id'] : $request->user()->organizations()->value('organizations.id');
        abort_unless($organizationId, 403);
        $data = $request->validate(['assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'], 'subject' => ['required', 'string', 'max:140'], 'body' => ['required', 'string', 'max:3000']]);
        $assignedAdminId = $isAdmin ? $request->user()->id : ($data['assigned_admin_id'] ?? null);
        if ($assignedAdminId) {
            abort_unless(User::whereKey($assignedAdminId)->whereHas('roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin']))->exists(), 422);
        } else {
            $assignedAdminId = User::whereHas('roles', fn ($query) => $query->whereIn('code', ['super_admin', 'admin']))->value('id');
        }
        $conversation = DB::transaction(function () use ($request, $data, $organizationId, $assignedAdminId, $isAdmin) {
            $conversation = BusinessConversation::create(['organization_id' => $organizationId, 'assigned_admin_id' => $assignedAdminId, 'subject' => $data['subject'], $isAdmin ? 'admin_last_read_at' : 'business_last_read_at' => now()]);
            $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);

            return $conversation;
        });

        return redirect()->route('messages.index', ['conversation' => $conversation])->with('success', 'Conversación creada.');
    }

    public function reply(Request $request, BusinessConversation $conversation): RedirectResponse
    {
        $isAdmin = $this->isAdmin($request);
        abort_unless($isAdmin || $request->user()->organizations()->whereKey($conversation->organization_id)->exists(), 403);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $conversation->messages()->create(['sender_id' => $request->user()->id, 'body' => $data['body']]);
        $conversation->update(['status' => 'open', $isAdmin ? 'admin_last_read_at' : 'business_last_read_at' => now()]);

        return redirect()->route('messages.index', ['conversation' => $conversation])->with('success', 'Mensaje enviado.');
    }

    public function close(Request $request, BusinessConversation $conversation): RedirectResponse
    {
        abort_unless($this->isAdmin($request), 403);
        $conversation->update(['status' => 'closed']);

        return back()->with('success', 'Conversación cerrada.');
    }

    private function isAdmin(Request $request): bool
    {
        return $request->user()->roles()->whereIn('code', ['super_admin', 'admin'])->exists();
    }
}
