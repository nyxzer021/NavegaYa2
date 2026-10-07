<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAdvertisingSubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::query()->with(['organization', 'plan'])->latest('starts_on');
        if ($request->filled('plan')) $query->whereHas('plan', fn ($q) => $q->where('code', $request->string('plan')));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->whereHas('organization', fn ($q) => $q->where('commercial_name','like',"%{$search}%")->orWhere('legal_name','like',"%{$search}%")->orWhere('ruc','like',"%{$search}%"));
        }
        return view('admin.advertising-subscriptions.index', [
            'subscriptions' => $query->paginate(20)->withQueryString(),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('monthly_price')->get(),
            'organizations' => Organization::where('status', 'active')->orderBy('commercial_name')->get(),
            'statusCounts' => Subscription::selectRaw('status, count(*) total')->groupBy('status')->pluck('total','status'),
            'monthlyRevenue' => (float) Subscription::where('status','active')->sum('monthly_price'),
            'annualRevenue' => (float) Subscription::where('status','active')->sum('monthly_price') * 12,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if (Subscription::where('organization_id', $data['organization_id'])->whereIn('status', ['active', 'overdue'])->exists()) {
            return back()->withErrors(['organization_id' => 'La empresa ya tiene una suscripción activa o pendiente de regularización.'])->withInput();
        }
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $startsOn = now()->parse($data['starts_on']);
        Subscription::create($data + ['monthly_price'=>$plan->monthly_price,'currency'=>$plan->currency,'ends_on'=>$startsOn->copy()->addMonths((int)$data['months']),'status'=>'active']);
        return back()->with('success','Suscripción publicitaria activada.');
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate(['subscription_plan_id'=>['required','exists:subscription_plans,id'],'status'=>['required',Rule::in(['active','overdue','cancelled','expired'])]]);
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);
        $subscription->update($data + ['monthly_price'=>$plan->monthly_price,'currency'=>$plan->currency,'cancelled_at'=>$data['status']==='cancelled'?now():null]);
        return back()->with('success','Suscripción actualizada.');
    }

    public function renew(Request $request, Subscription $subscription): RedirectResponse
    {
        $months = $request->validate(['months'=>['required','integer','min:1','max:24']])['months'];
        $base = $subscription->ends_on && $subscription->ends_on->isFuture() ? $subscription->ends_on->copy() : today();
        $subscription->update(['ends_on'=>$base->addMonths($months),'status'=>'active','cancelled_at'=>null]);
        return back()->with('success',"Suscripción renovada por {$months} mes(es).");
    }

    public function cancel(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['status'=>'cancelled','cancelled_at'=>now()]);
        return back()->with('success','Suscripción cancelada y registrada como baja.');
    }

    private function validated(Request $request): array
    {
        return $request->validate(['organization_id'=>['required','exists:organizations,id'],'subscription_plan_id'=>['required','exists:subscription_plans,id'],'starts_on'=>['required','date'],'months'=>['required','integer','min:1','max:24']]);
    }
}

