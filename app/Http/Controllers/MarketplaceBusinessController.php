<?php
namespace App\Http\Controllers;

use App\Models\{BusinessCategory,DestinationCity,DestinationListing,DestinationListingImage,Organization,Role,Subscription,SubscriptionPlan,User};
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
        $user=DB::transaction(function() use($data){ $plan=SubscriptionPlan::findOrFail($data['plan_id']); $city=DestinationCity::findOrFail($data['destination_city_id']); $category=BusinessCategory::where('listing_type',$data['type'])->where('is_active',true)->orderBy('sort_order')->first(); $org=Organization::create(['type'=>'marketplace_business','legal_name'=>$data['legal_name'],'commercial_name'=>$data['business_name'],'contact_name'=>$data['contact_name'],'ruc'=>$data['ruc'],'email'=>$data['email'],'phone'=>$data['phone'],'whatsapp'=>$data['phone'],'status'=>'pending','base_city'=>$city->name]); $user=User::create(['name'=>$data['contact_name'],'email'=>$data['email'],'phone'=>$data['phone'],'password'=>Hash::make($data['password'])]); $role=Role::firstOrCreate(['code'=>'marketplace_admin'],['name'=>'Administrador de negocio turístico']); $user->organizations()->attach($org->id,['status'=>'pending']); $user->roles()->attach($role->id,['organization_id'=>$org->id]); Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'pending','monthly_price'=>$plan->monthly_price,'currency'=>$plan->currency,'starts_on'=>today()]); DestinationListing::create(['organization_id'=>$org->id,'owner_user_id'=>$user->id,'destination_city_id'=>$city->id,'business_category_id'=>$category?->id,'type'=>$data['type'],'category'=>$category?->name,'status'=>'draft','name'=>$data['business_name'],'contact_phone'=>$data['phone'],'sort_order'=>99,'is_active'=>false]); return $user; });
        Auth::login($user); return redirect()->route('marketplace.dashboard')->with('success','Cuenta creada. Completa tu ficha mientras validamos tu suscripción.');
    }

    public function dashboard(Request $request): View { [$org,$listing]=$this->owned($request); return view('marketplace.dashboard',['organization'=>$org,'listing'=>$listing->load('images','businessCategory'),'subscription'=>$org->subscriptions()->with('plan')->latest()->first(),'cities'=>DestinationCity::where('is_active',true)->orderBy('name')->get(),'types'=>self::TYPES,'categories'=>BusinessCategory::where('is_active',true)->orderBy('sort_order')->get()]); }

    public function update(Request $request): RedirectResponse
    {
        [$org,$listing]=$this->owned($request); $data=$request->validate(['destination_city_id'=>['required','exists:destination_cities,id'],'type'=>['required',Rule::in(array_keys(self::TYPES))],'business_category_id'=>['required','exists:business_categories,id'],'name'=>['required','string','max:150'],'description'=>['required','string','max:3000'],'services'=>['nullable','string','max:3000'],'address'=>['nullable','string','max:250'],'contact_phone'=>['required','string','max:40'],'website_url'=>['nullable','url','max:500'],'price_reference'=>['nullable','string','max:80'],'opening_hours'=>['nullable','string','max:160'],'banner'=>['nullable','image','mimes:jpg,jpeg,png,webp','max:5120'],'images'=>['nullable','array','max:6'],'images.*'=>['image','mimes:jpg,jpeg,png,webp','max:5120'],'action'=>['required',Rule::in(['draft','submit'])]]);
        $category=BusinessCategory::whereKey($data['business_category_id'])->where('listing_type',$data['type'])->where('is_active',true)->firstOrFail(); $data['category']=$category->name;
        if($listing->images()->count()+count($request->file('images',[]))>6) return back()->withErrors(['images'=>'La galería admite como máximo 6 imágenes.'])->withInput();
        if($request->hasFile('banner')){ if($listing->banner_path) Storage::disk('public')->delete(str_replace('/storage/','',$listing->banner_path)); $data['banner_path']=Storage::url($request->file('banner')->store('marketplace-banners','public')); }
        unset($data['banner'],$data['images'],$data['action']); $active=$org->subscriptions()->where('status','active')->where(fn($q)=>$q->whereNull('ends_on')->orWhereDate('ends_on','>=',today()))->exists(); if($request->input('action')==='submit'&&!$active) return back()->withErrors(['subscription'=>'El superadministrador debe confirmar el pago y activar tu plan antes de enviar la ficha a revisión.'])->withInput();
        $data['status']=$request->input('action')==='submit'?'pending_review':'draft'; $data['is_active']=false; $data['moderation_notes']=null; $listing->update($data);
        foreach($request->file('images',[]) as $image) $listing->images()->create(['path'=>Storage::url($image->store('marketplace-gallery','public')),'sort_order'=>($listing->images()->max('sort_order')??0)+1,'is_cover'=>$listing->images()->count()===0]);
        return back()->with('success',$data['status']==='pending_review'?'Ficha enviada a revisión.':'Borrador guardado.');
    }

    public function deleteImage(Request $request,DestinationListingImage $image): RedirectResponse { [, $listing]=$this->owned($request); abort_unless($image->destination_listing_id===$listing->id,404); $image->delete(); return back()->with('success','Imagen eliminada.'); }
    private function owned(Request $request): array { $org=$request->user()->organizations()->where('organizations.type','marketplace_business')->firstOrFail(); return [$org,DestinationListing::where('organization_id',$org->id)->where('owner_user_id',$request->user()->id)->firstOrFail()]; }
}
