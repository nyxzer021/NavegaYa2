<?php

namespace Tests\Feature;

use App\Models\{BusinessCategory,DestinationCity,DestinationListing,Organization,Role,Subscription,SubscriptionPlan,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TourismMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_registration_creates_account_subscription_and_draft_listing(): void
    {
        $plan=SubscriptionPlan::where('code','basic')->firstOrFail();
        $city=DestinationCity::where('name','Iquitos')->firstOrFail();
        $response=$this->post(route('marketplace.register.store'),[
            'plan_id'=>$plan->id,'destination_city_id'=>$city->id,'type'=>'lodging',
            'business_name'=>'Lodge Río Verde','legal_name'=>'Servicios Río Verde SAC','ruc'=>'20123456789',
            'contact_name'=>'Ana Torres','email'=>'ana@rioverde.test','phone'=>'+51 999 111 222',
            'password'=>'Password123!','password_confirmation'=>'Password123!',
        ]);
        $response->assertRedirect(route('marketplace.dashboard'));
        $organization=Organization::where('ruc','20123456789')->firstOrFail();
        $this->assertSame('marketplace_business',$organization->type);
        $this->assertDatabaseHas('subscriptions',['organization_id'=>$organization->id,'status'=>'pending']);
        $this->assertDatabaseHas('destination_listings',['organization_id'=>$organization->id,'status'=>'draft','is_active'=>false]);
        $this->assertTrue(User::where('email','ana@rioverde.test')->firstOrFail()->roles()->where('code','marketplace_admin')->exists());
    }

    public function test_business_submits_six_image_listing_and_superadmin_publishes_it(): void
    {
        Storage::fake('public');
        $plan=SubscriptionPlan::where('code','professional')->firstOrFail();
        $city=DestinationCity::where('name','Iquitos')->firstOrFail();
        $org=Organization::create(['type'=>'marketplace_business','legal_name'=>'Sabores Loreto SAC','commercial_name'=>'Sabores Loreto','ruc'=>'20987654321','email'=>'negocio@test.pe','phone'=>'999222333','status'=>'pending']);
        $owner=User::factory()->create();
        $role=Role::where('code','marketplace_admin')->firstOrFail();
        $owner->organizations()->attach($org->id,['status'=>'pending']);
        $owner->roles()->attach($role->id,['organization_id'=>$org->id]);
        Subscription::create(['organization_id'=>$org->id,'subscription_plan_id'=>$plan->id,'status'=>'active','monthly_price'=>$plan->monthly_price,'currency'=>'PEN','starts_on'=>today(),'ends_on'=>today()->addMonth()]);
        $listing=DestinationListing::create(['organization_id'=>$org->id,'owner_user_id'=>$owner->id,'destination_city_id'=>$city->id,'type'=>'gastronomy','status'=>'draft','name'=>'Sabores Loreto','sort_order'=>99,'is_active'=>false]);
        $category=BusinessCategory::where('listing_type','gastronomy')->firstOrFail();
        $files=collect(range(1,6))->map(fn($n)=>UploadedFile::fake()->image("foto{$n}.jpg"))->all();
        $this->actingAs($owner)->patch(route('marketplace.update'),[
            'destination_city_id'=>$city->id,'type'=>'gastronomy','business_category_id'=>$category->id,'name'=>'Sabores Loreto','description'=>'Cocina amazónica con insumos regionales.','services'=>'Menú regional y reservas','contact_phone'=>'999222333','action'=>'submit','images'=>$files,
        ])->assertSessionHasNoErrors();
        $listing->refresh();
        $this->assertSame('pending_review',$listing->status);
        $this->assertCount(6,$listing->images);

        $admin=User::factory()->create();
        $adminRole=Role::firstOrCreate(['code'=>'super_admin'],['name'=>'Superadministrador']);
        $admin->roles()->attach($adminRole->id);
        $this->actingAs($admin)->patch(route('admin.tourism-partners.moderate',$listing),['decision'=>'publish'])->assertSessionHasNoErrors();
        $listing->refresh();
        $this->assertTrue($listing->is_active);
        $this->assertSame('published',$listing->status);
        $this->get(route('guide.show',$listing))->assertOk()->assertSee('Sabores Loreto')->assertSee('Cocina amazónica');
    }

    public function test_superadmin_manages_master_categories_and_assisted_form_cannot_publish_directly(): void
    {
        $admin=User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code'=>'super_admin'],['name'=>'Superadministrador']));

        $this->actingAs($admin)->post(route('admin.business-categories.store'),[
            'code'=>'eco_lodge','listing_type'=>'lodging','name'=>'Eco lodge','icon'=>'🛖','description'=>'Hospedaje sostenible','sort_order'=>15,'is_active'=>1,
        ])->assertSessionHasNoErrors();
        $category=BusinessCategory::where('code','eco_lodge')->firstOrFail();
        $this->get(route('admin.business-categories.index'))->assertOk()->assertSee('Eco lodge');

        $city=DestinationCity::where('name','Iquitos')->firstOrFail();
        $this->post(route('admin.destination-listings.store','lodging'),[
            'destination_city_id'=>$city->id,'business_category_id'=>$category->id,'name'=>'Lodge asistido','description'=>'Borrador creado con asistencia.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('destination_listings',['name'=>'Lodge asistido','status'=>'draft','is_active'=>false,'is_featured'=>false]);
        $this->get(route('admin.destination-listings.create','lodging'))->assertOk()->assertDontSee('Mostrar destacado en portada')->assertDontSee('> Publicar<',false);
    }
}
