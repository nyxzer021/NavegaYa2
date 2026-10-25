<?php
namespace App\Http\Controllers;

use App\Models\{Advertisement,BusinessCategory,DestinationCity,DestinationListing,DestinationListingImage,DestinationListingOffer,Organization,Role,Subscription,SubscriptionPlan,User};
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\{RedirectResponse,Request};
use Illuminate\Support\Facades\{Auth,DB,Hash,Storage};
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MarketplaceBusinessController extends Controller
{
    public const TYPES=['lodging'=>'Hoteles, lodges y hospedajes','gastronomy'=>'Restaurantes y gastronomía','attraction'=>'Tours y experiencias','tourist_transport'=>'Transporte turístico','traveler_service'=>'Servicios para viajeros','local_business'=>'Comercios y otros negocios'];
    public function create(Request $request): View { return view('marketplace.register',['plans'=>SubscriptionPlan::where('is_active',true)->orderBy('monthly_price')->get(),'cities'=>DestinationCity::where('is_active',true)->orderBy('name')->get(),'types'=>self::TYPES,'selectedPlan'=>$request->string('plan')->toString()]); }

    public function store(Request $request): RedirectResponse
    {
        $data=$request->validate(['plan_id'=>['required','exists:subscription_plans,id'],'destination_city_id'=>['required','exists:destination_cities,id'],'type'=>['required',Rule::in(array_keys(self::TYPES))],'business_name'=>['required','string','max:150'],'legal_name'=>['required','string','max:180'],'ruc'=>['required','digits:11','unique:organizations,ruc'],'contact_name'=>['required','string','max:150'],'email'=>['required','email','unique:users,email'],'phone'=>['required','string','max:30'],'password'=>['required','confirmed','min:8']]);
        $user=DB::transaction(function() use($data){ $plan=SubscriptionPlan::findOrFail($data['plan_id']); $city=DestinationCity::findOrFail($data['destination_city_id']); $category=BusinessCategory::where('listing_type',$data['type'])->where('is_active',true)->orderBy('sort_order')->first(); $org=Organization::create(['type'=>'marketplace_business','legal_name'=>$data['legal_name'],'commercial_name'=>$data['business_name'],'contact_name'=>$data['contact_name'],'ruc'=>$data['ruc'],'email'=>$data['email'],'phone'=>$data['phone'],'whatsapp'=>$data['phone'],'status'=>'pending','base_city'=>$city->name]); $user=User::create(['name'=>$data['contact_name'],'email'=>$data['email'],'phone'=>$data['phone'],'password'=>Hash::make($data['password'])]); $role=Role::firstOrCreate(['code'=>'marketplace_admin'],['name'=>'Administrador de negocio turístico']); $user->organizations()->attach($org->id,['status'=>'pending']); $user->roles()->attach($role->id,['organization_id'=>$org->id]); Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'pending','monthly_price'=>$plan->monthly_price,'currency'=>$plan->currency,'benefits_snapshot'=>$plan->benefits,'starts_on'=>today()]); DestinationListing::create(['organization_id'=>$org->id,'owner_user_id'=>$user->id,'destination_city_id'=>$city->id,'business_category_id'=>$category?->id,'type'=>$data['type'],'category'=>$category?->name,'status'=>'draft','name'=>$data['business_name'],'contact_phone'=>$data['phone'],'sort_order'=>99,'is_active'=>false]); return $user; });
        event(new Registered($user));
        Auth::login($user); return redirect()->route('marketplace.dashboard',['tab'=>'plan'])->with('success','Cuenta creada. Revisa tu correo para confirmar la cuenta y luego administra tu suscripción.');
    }

    public function dashboard(Request $request): View
    {
        [$org,$listing]=$this->owned($request);
        $campaigns=$org->advertisements()->latest()->get();
        $reviews=$org->reviews()->with('user')->latest()->get();
        return view('marketplace.dashboard',[
            'organization'=>$org,
            'listing'=>$listing->load('images.reviews.user','businessCategory','offers'),
            'subscription'=>$org->subscriptions()->with('plan')->latest()->first(),
            'cities'=>DestinationCity::where('is_active',true)->orderBy('name')->get(),
            'types'=>self::TYPES,
            'categories'=>BusinessCategory::where('is_active',true)->orderBy('sort_order')->get(),
            'campaigns'=>$campaigns,
            'reviews'=>$reviews,
            'ratingAverage'=>round((float)$reviews->where('status','approved')->avg('rating'),1),
            'stats'=>['profile_views'=>$listing->views,'contact_clicks'=>$listing->contact_clicks,'campaign_views'=>$campaigns->sum('views'),'campaign_clicks'=>$campaigns->sum('clicks')],
            'placements'=>array_intersect_key(Advertisement::PLACEMENTS,array_flip(['home_carousel','home_sidebar','home_recommendations'])),
            'activeTab'=>in_array($request->query('tab'),['overview','profile','gallery','offers','reviews','campaigns','plan'],true)?$request->query('tab'):'overview',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        [$org,$listing]=$this->owned($request); $data=$request->validate(['destination_city_id'=>['required','exists:destination_cities,id'],'type'=>['required',Rule::in(array_keys(self::TYPES))],'business_category_id'=>['required','exists:business_categories,id'],'name'=>['required','string','max:150'],'description'=>['required','string','max:3000'],'services'=>['nullable','string','max:3000'],'address'=>['nullable','string','max:250'],'contact_phone'=>['required','string','max:40'],'website_url'=>['nullable','url','max:500'],'price_reference'=>['nullable','string','max:80'],'schedule'=>['nullable','array'],'schedule.*.closed'=>['nullable','boolean'],'schedule.*.open_1'=>['nullable','date_format:H:i'],'schedule.*.close_1'=>['nullable','date_format:H:i'],'schedule.*.open_2'=>['nullable','date_format:H:i'],'schedule.*.close_2'=>['nullable','date_format:H:i'],'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],'images'=>['nullable','array','max:6'],'images.*'=>['image','mimes:jpg,jpeg,png,webp','max:5120'],'action'=>['required',Rule::in(['draft','submit'])]]);
        $category=BusinessCategory::whereKey($data['business_category_id'])->where('listing_type',$data['type'])->where('is_active',true)->firstOrFail(); $data['category']=$category->name;
        if($listing->images()->count()+count($request->file('images',[]))>6) return back()->withErrors(['images'=>'La galería admite como máximo 6 imágenes.'])->withInput();
        if($request->hasFile('banner')){ if($listing->banner_path) Storage::disk('public')->delete(str_replace('/storage/','',$listing->banner_path)); $data['banner_path']=Storage::url($request->file('banner')->store('marketplace-banners','public')); }
        $days=['mon'=>'Lunes','tue'=>'Martes','wed'=>'Miércoles','thu'=>'Jueves','fri'=>'Viernes','sat'=>'Sábado','sun'=>'Domingo'];
        $schedule=[]; $summary=[];
        foreach($days as $key=>$label){ $row=$data['schedule'][$key]??[]; $closed=(bool)($row['closed']??false); $periods=[]; if(!$closed){ foreach([["open_1","close_1"],["open_2","close_2"]] as [$from,$to]) if(!empty($row[$from])&&!empty($row[$to])) $periods[]=['from'=>$row[$from],'to'=>$row[$to]]; } $schedule[$key]=['closed'=>$closed||empty($periods),'periods'=>$periods]; $summary[]=$label.': '.(($closed||empty($periods))?'Cerrado':collect($periods)->map(fn($p)=>$p['from'].'–'.$p['to'])->join(' / ')); }
        $data['opening_schedule']=$schedule; $data['opening_hours']=implode('; ',$summary);
        unset($data['schedule'],$data['banner'],$data['images'],$data['action']); $active=$org->subscriptions()->where('status','active')->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',today()))->exists(); if($request->input('action')==='submit'&&!$active) return back()->withErrors(['subscription'=>'Paga tu suscripción en Plan y facturación para activarla y enviar la ficha a revisión.'])->withInput();
        $data['status']=$request->input('action')==='submit'?'pending_review':'draft'; $data['is_active']=false; $data['moderation_notes']=null; $listing->update($data);
        foreach($request->file('images',[]) as $image) $listing->images()->create(['path'=>Storage::url($image->store('marketplace-gallery','public')),'sort_order'=>($listing->images()->max('sort_order')??0)+1,'is_cover'=>$listing->images()->count()===0]);
        return back()->with('success',$data['status']==='pending_review'?'Ficha enviada a revisión.':'Borrador guardado.');
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        [$org, $listing] = $this->owned($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'placement' => ['required', Rule::in(['home_carousel', 'home_sidebar', 'home_recommendations'])],
            'banner_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        if (! $request->user()->hasVerifiedEmail()) {
            return back()->withErrors(['campaign' => 'Confirma tu correo antes de solicitar una campaña publicitaria.']);
        }
        $activeSubscription = $org->subscriptions()->with('plan')->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))->latest()->first();
        if (! $activeSubscription) return back()->withErrors(['campaign' => 'Necesitas una suscripción activa para usar las campañas incluidas.']);
        $benefits = $activeSubscription->benefits_snapshot ?: ($activeSubscription->plan?->benefits ?? []);
        $limit = (int) data_get($benefits, 'ad_limit', 0);
        $used = $org->advertisements()->whereIn('status', ['lead_pending', 'active', 'paused'])->count();
        if ($limit > 0 && $used >= $limit) return back()->withErrors(['campaign' => "Tu plan permite {$limit} campaña(s) simultánea(s)."]);
        $allowedPlacements = data_get($benefits, 'allowed_placements', ['home_carousel']);
        if (! in_array($data['placement'], $allowedPlacements, true)) return back()->withErrors(['placement' => 'La ubicación elegida no está incluida en tu plan.']);

        $org->advertisements()->create([
            'destination_city_id' => $listing->destination_city_id,
            'business_name' => $listing->name,
            'business_type' => match ($listing->type) {
                'gastronomy' => 'restaurant', 'attraction' => 'tour_operator',
                'tourist_transport' => 'charter_transport', default => 'lodge_hotel',
            },
            'contact_name' => $org->contact_name,
            'phone_whatsapp' => $listing->contact_phone ?: $org->whatsapp,
            'email' => $org->email,
            'city_destination' => $listing->city?->name ?: $org->base_city,
            'ruc' => $org->ruc,
            'placements' => [$data['placement']],
            'category' => $listing->category ?: $listing->type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'target_url' => route('guide.show', $listing),
            'banner_image_path' => $request->file('banner_image')->store('advertisements', 'public'),
            'monthly_fee' => 0,
            'starts_on' => null,
            'ends_on' => null,
            'status' => 'lead_pending',
        ]);

        return back()->with('success', 'Campaña enviada a revisión usando el cupo incluido en tu plan.');
    }

    public function pauseCampaign(Request $request, Advertisement $advertisement): RedirectResponse
    {
        [$org] = $this->owned($request);
        abort_unless($advertisement->organization_id === $org->id, 404);
        $advertisement->update(['status' => 'paused']);

        return back()->with('success', 'Campaña pausada.');
    }
    public function storeOffer(Request $request): RedirectResponse
    {
        [, $listing]=$this->owned($request);
        $data=$request->validate(['title'=>['required','string','max:150'],'offer_type'=>['nullable','string','max:60'],'description'=>['nullable','string','max:1000'],'price'=>['nullable','numeric','min:0'],'price_unit'=>['nullable','string','max:60'],'capacity'=>['nullable','integer','min:1','max:999'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:4096']]);
        if($request->hasFile('image')) $data['image_path']=Storage::url($request->file('image')->store('marketplace-offers','public'));
        unset($data['image']);
        $listing->offers()->create($data+['is_active'=>true,'sort_order'=>($listing->offers()->max('sort_order')??0)+1]);
        return back()->with('success','Publicación agregada a tu página comercial.');
    }
    public function destroyOffer(Request $request, DestinationListingOffer $offer): RedirectResponse
    {
        [, $listing]=$this->owned($request); abort_unless($offer->destination_listing_id===$listing->id,404);
        if($offer->image_path) Storage::disk('public')->delete(str_replace('/storage/','',$offer->image_path));
        $offer->delete(); return back()->with('success','Publicación eliminada.');
    }
    public function replaceImage(Request $request, DestinationListingImage $image): RedirectResponse
    {
        [, $listing]=$this->owned($request); abort_unless($image->destination_listing_id===$listing->id,404);
        $request->validate(['image'=>['required','image','mimes:jpg,jpeg,png,webp','max:5120']]);
        Storage::disk('public')->delete(str_replace('/storage/','',$image->path));
        $image->update(['path'=>Storage::url($request->file('image')->store('marketplace-gallery','public'))]);
        return back()->with('success','Imagen reemplazada correctamente.');
    }
    public function storeMedia(Request $request): RedirectResponse
    {
        [, $listing]=$this->owned($request);
        $request->validate([
            'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'images'=>['nullable','array','max:6'],
            'images.*'=>['image','mimes:jpg,jpeg,png,webp','max:5120'],
        ]);
        if(!$request->hasFile('banner')&&!$request->hasFile('images')) return back()->withErrors(['images'=>'Selecciona una portada o al menos una fotografía.']);
        if($listing->images()->count()+count($request->file('images',[]))>6) return back()->withErrors(['images'=>'La galería admite como máximo 6 imágenes.']);
        if($request->hasFile('banner')){
            if($listing->banner_path) Storage::disk('public')->delete(str_replace('/storage/','',$listing->banner_path));
            $listing->update(['banner_path'=>Storage::url($request->file('banner')->store('marketplace-banners','public'))]);
        }
        foreach($request->file('images',[]) as $image){
            $isFirst=$listing->images()->count()===0;
            $created=$listing->images()->create(['path'=>Storage::url($image->store('marketplace-gallery','public')),'sort_order'=>($listing->images()->max('sort_order')??0)+1,'is_cover'=>$isFirst]);
            if($isFirst&&!$listing->banner_path) $listing->update(['banner_path'=>$created->path]);
        }
        return back()->with('success','Galería actualizada correctamente.');
    }
    public function setCover(Request $request, DestinationListingImage $image): RedirectResponse
    {
        [, $listing]=$this->owned($request); abort_unless($image->destination_listing_id===$listing->id,404);
        DB::transaction(function() use($listing,$image){
            $listing->images()->update(['is_cover'=>false]);
            $image->update(['is_cover'=>true]);
            $listing->update(['banner_path'=>$image->path]);
        });
        return back()->with('success','La fotografía seleccionada ahora es la portada pública.');
    }
    public function deleteImage(Request $request,DestinationListingImage $image): RedirectResponse
    {
        [, $listing]=$this->owned($request); abort_unless($image->destination_listing_id===$listing->id,404);
        Storage::disk('public')->delete(str_replace('/storage/','',$image->path));
        $wasCover=$image->is_cover; $image->delete();
        if($wasCover){ $next=$listing->images()->orderBy('sort_order')->first(); $next?->update(['is_cover'=>true]); $listing->update(['banner_path'=>$next?->path]); }
        return back()->with('success','Imagen eliminada.');
    }
    private function owned(Request $request): array { $org=$request->user()->organizations()->where('organizations.type','marketplace_business')->firstOrFail(); return [$org,DestinationListing::where('organization_id',$org->id)->where('owner_user_id',$request->user()->id)->firstOrFail()]; }
}
