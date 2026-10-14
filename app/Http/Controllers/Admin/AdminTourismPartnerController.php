<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DestinationListing;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTourismPartnerController extends Controller
{
    public function index(): View
    {
        $partners = DestinationListing::query()
            ->with(['city','organization.subscriptions.plan'])
            ->whereIn('type', array_keys(\App\Http\Controllers\MarketplaceBusinessController::TYPES))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(18);

        return view('admin.tourism-partners.index', [
            'partners' => $partners,
            'lodgingsCount' => DestinationListing::where('type', 'lodging')->where('is_active', true)->count(),
            'gastronomyCount' => DestinationListing::where('type', 'gastronomy')->where('is_active', true)->count(),
            'experiencesCount' => DestinationListing::where('type', 'attraction')->where('is_active', true)->count(),
            'pendingCount' => DestinationListing::where('status', 'pending_review')->count(),
            'businessesCount' => DestinationListing::whereNotNull('organization_id')->count(),
            'activeSubscriptionsCount' => \App\Models\Subscription::where('status', 'active')->whereHas('organization', fn($q)=>$q->where('type','marketplace_business'))->count(),
        ]);
    }

    public function moderate(Request $request, DestinationListing $listing): RedirectResponse
    {
        $data=$request->validate(['decision'=>['required',Rule::in(['publish','observe','reject'])],'moderation_notes'=>['nullable','string','max:1500']]);
        $listing->update(['status'=>match($data['decision']){'publish'=>'published','observe'=>'observed',default=>'rejected'},'is_active'=>$data['decision']==='publish','moderation_notes'=>$data['moderation_notes']??null]);
        if($data['decision']==='publish' && $listing->organization){ $listing->organization->update(['status'=>'active','verified_at'=>$listing->organization->verified_at?:now()]); if($listing->owner_user_id) $listing->organization->users()->updateExistingPivot($listing->owner_user_id,['status'=>'active']); }
        return back()->with('success','Decisión de moderación registrada.');
    }
}
