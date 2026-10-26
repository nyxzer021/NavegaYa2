<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $roleCodes = $request->user()->roles()->pluck('code');
        $isAdmin = $roleCodes->intersect(['super_admin', 'admin'])->isNotEmpty();
        $tickets = SupportTicket::query()
            ->with(['user', 'organization', 'responder'])
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest()
            ->paginate(20);

        if (! $isAdmin) {
            SupportTicket::query()->where('user_id', $request->user()->id)
                ->whereNotNull('admin_response')
                ->where(fn ($query) => $query->whereNull('requester_viewed_at')->orWhereColumn('requester_viewed_at', '<', 'responded_at'))
                ->update(['requester_viewed_at' => now()]);
        }

        $organization = $isAdmin ? null : $request->user()->organizations()->with('vessels.basePort')->first();
        $view = $isAdmin ? 'support.admin'
            : ($roleCodes->contains('marketplace_admin') ? 'support.business'
            : ($roleCodes->contains('company_admin') ? 'support.company' : 'support.customer'));

        return view($view, compact('tickets', 'isAdmin', 'organization'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:reservation,payment,refund,operation,fleet,advertising,account,technical,other'],
            'priority' => ['required', 'in:normal,high'],
            'subject' => ['required', 'string', 'max:140'],
            'description' => ['required', 'string', 'max:3000'],
        ]);
        $user = $request->user();
        $roleCodes = $user->roles()->pluck('code');
        $requesterType = $roleCodes->contains('company_admin') ? 'transport_company'
            : ($roleCodes->contains('marketplace_admin') ? 'advertiser' : 'customer');

        SupportTicket::create($data + [
            'user_id' => $user->id,
            'organization_id' => $user->organizations()->value('organizations.id'),
            'requester_type' => $requesterType,
            'code' => 'SOL-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
        ]);

        return back()->with('success', 'Solicitud enviada. Puedes seguir su estado en esta bandeja.');
    }

    public function respond(Request $request, SupportTicket $supportTicket): RedirectResponse
    {
        abort_unless($request->user()->roles()->whereIn('code', ['super_admin', 'admin'])->exists(), 403);
        $data = $request->validate([
            'status' => ['required', 'in:in_progress,waiting_user,resolved,closed'],
            'admin_response' => ['required', 'string', 'max:3000'],
        ]);
        $supportTicket->update($data + [
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
            'closed_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        return back()->with('success', 'Solicitud actualizada y respuesta registrada.');
    }
}
