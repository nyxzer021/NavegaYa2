<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationPhoto;
use App\Models\PhotoComment;
use App\Models\PolicyDocument;
use App\Models\Port;
use App\Models\PublicPage;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\RouteDeparture;
use App\Models\SystemSetting;
use App\Models\TransportRoute;
use App\Models\User;
use App\Models\UserSanction;
use App\Models\Vessel;
use App\Support\TravelPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StageThreeContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_terms_page_uses_the_current_policy_version_and_keeps_the_public_page(): void
    {
        $page = PublicPage::query()->where('slug', 'terminos')->firstOrFail();
        $page->update(['content' => 'Texto de respaldo que no debe ser la fuente.']);
        $document = PolicyDocument::query()->where('kind', 'platform_terms')->firstOrFail();
        $document->versions()->create([
            'version' => 2,
            'body_html' => '<p>Versión vigente de los términos.</p>',
            'published_at' => now(),
        ]);

        $this->get(route('pages.show', 'terminos'))
            ->assertOk()
            ->assertSee('Versión vigente de los términos.')
            ->assertDontSee('Texto de respaldo que no debe ser la fuente.');

        $this->assertDatabaseHas('public_pages', ['slug' => 'terminos']);
    }

    public function test_public_company_page_opens_the_fleet_tab_from_the_anchor(): void
    {
        $company = $this->company('Flota Visible');

        $this->get(route('companies.show', $company).'#flota')
            ->assertOk()
            ->assertSee('id="flota"', false)
            ->assertSee('data-open="flota"', false)
            ->assertSee('names.indexOf(start)', false);
    }

    public function test_appearance_colors_stay_on_one_company(): void
    {
        [$admin, $company] = $this->companyAdmin('Color Propio', '20631000001');
        [, $other] = $this->companyAdmin('Color Ajeno', '20631000002');

        $this->actingAs($admin)->patch(route('company.appearance.update'), [
            'public_primary_color' => '#112233',
            'public_accent_color' => '#abcdef',
        ])->assertRedirect();

        $this->get(route('companies.show', $company))->assertOk()->assertSee('--company-primary:#112233', false);
        $this->get(route('companies.show', $other))->assertOk()->assertDontSee('--company-primary:#112233', false);
    }

    public function test_hidden_photo_is_absent_from_the_public_page(): void
    {
        $company = $this->company('Fotos');
        OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/oculta.jpg',
            'status' => 'hidden',
            'sort_order' => 1,
        ]);
        OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/visible.jpg',
            'status' => 'published',
            'sort_order' => 2,
        ]);

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('/storage/company-profiles/visible.jpg', false)
            ->assertDontSee('/storage/company-profiles/oculta.jpg', false);
    }

    public function test_only_the_company_admin_publishes_up_to_two_banners(): void
    {
        Storage::fake('public');
        [$admin] = $this->companyAdmin('Portadas', '20631000003');
        $seller = User::factory()->create();
        $seller->roles()->attach(Role::firstOrCreate(['code' => 'company_counter'], ['name' => 'Vendedor']), ['organization_id' => $admin->organizations()->value('organizations.id')]);
        $seller->organizations()->attach($admin->organizations()->value('organizations.id'), ['status' => 'active']);

        $this->actingAs($seller)->post(route('company.profile.banners.store'), [
            'banner' => UploadedFile::fake()->image('portada.jpg'),
        ])->assertForbidden();

        $this->actingAs($admin);
        $this->post(route('company.profile.banners.store'), ['banner' => UploadedFile::fake()->image('uno.jpg', 1200, 300)])->assertRedirect();
        $this->post(route('company.profile.banners.store'), ['banner' => UploadedFile::fake()->image('dos.jpg', 1200, 300)])->assertRedirect();
        $this->post(route('company.profile.banners.store'), ['banner' => UploadedFile::fake()->image('tres.jpg', 1200, 300)])
            ->assertSessionHasErrors('banner');
        $this->assertSame(2, $admin->organizations()->first()->banners()->count());
    }

    public function test_suspended_user_cannot_sign_in_or_rate_and_a_warning_is_only_shown(): void
    {
        $company = $this->company('Opiniones');
        $suspended = User::factory()->create(['email' => 'suspendido@example.test']);
        UserSanction::create([
            'user_id' => $suspended->id,
            'kind' => 'suspension',
            'reason' => 'Comentarios ofensivos',
            'ends_at' => now()->addDays(3),
        ]);
        $banned = User::factory()->create(['email' => 'bloqueado@example.test']);
        UserSanction::create(['user_id' => $banned->id, 'kind' => 'ban', 'reason' => 'Fraude reiterado']);
        $warned = User::factory()->create(['email' => 'advertido@example.test']);
        UserSanction::create(['user_id' => $warned->id, 'kind' => 'warning', 'reason' => 'Cuida el lenguaje']);

        $this->post(route('login'), ['email' => 'suspendido@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post(route('login'), ['email' => 'bloqueado@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->post(route('login'), ['email' => 'advertido@example.test', 'password' => 'password'])
            ->assertRedirect();
        $this->actingAs($warned)->get(route('home'))->assertOk()->assertSee('Cuida el lenguaje');

        $this->actingAs($suspended)->post(route('companies.review', $company), [
            'reservation_id' => 1,
            'rating' => 5,
            'comment' => 'No debería publicarse esta opinión.',
        ])->assertForbidden();
    }

    public function test_policy_html_is_sanitized_and_a_version_can_be_restored(): void
    {
        [$admin, $company] = $this->companyAdmin('Política', '20631000004');

        $this->actingAs($admin)->post(route('company.policies.update'), [
            'body_html' => '<p>Texto válido</p><script>alert(1)</script>',
            'summary' => 'Segunda',
        ])->assertRedirect();

        $document = PolicyDocument::query()->where('organization_id', $company->id)->where('kind', 'company_travel')->firstOrFail();
        $published = $document->versions()->where('version', 2)->firstOrFail();
        $this->assertStringNotContainsString('<script', $published->body_html);
        $this->assertStringContainsString('Texto válido', $published->body_html);

        $first = $document->versions()->where('version', 1)->firstOrFail();
        $this->actingAs($admin)->post(route('company.policies.restore', $first))->assertRedirect();
        $restored = $document->versions()->orderByDesc('version')->firstOrFail();
        $this->assertSame(3, $restored->version);
        $this->assertSame($first->body_html, $restored->body_html);
    }

    public function test_travel_policy_template_is_html_instead_of_raw_markdown(): void
    {
        $html = TravelPolicy::templateHtml();

        $this->assertStringContainsString('<h1>', $html);
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringNotContainsString('## ', $html);
        $this->assertStringNotContainsString('**', $html);
    }

    public function test_superadmin_policy_edit_is_audited_and_visible_to_the_company(): void
    {
        [$admin, $company] = $this->companyAdmin('Aviso', '20631000005');
        $super = User::factory()->create();
        $super->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super administrador']));

        $this->actingAs($super)->post(route('admin.companies.travel-policy.update', $company), [
            'body_html' => '<p>Cambio de NavegaYA sobre el reembolso.</p>',
            'reason' => 'Alinear el texto con la plantilla vigente',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['event' => 'company_travel_policy_updated']);
        $this->actingAs($admin)->get(route('company.policies.index'))
            ->assertOk()
            ->assertSee('Alinear el texto con la plantilla vigente');
    }

    public function test_public_baggage_summary_uses_company_values_or_the_platform_defaults(): void
    {
        SystemSetting::updateOrCreate(['key' => 'baggage_hand_kg'], ['value' => '10']);
        SystemSetting::updateOrCreate(['key' => 'baggage_hold_kg'], ['value' => '20']);
        SystemSetting::updateOrCreate(['key' => 'baggage_allowance_air_kg'], ['value' => '10']);
        SystemSetting::updateOrCreate(['key' => 'parcel_weight_threshold_kg'], ['value' => '50']);
        $plain = $this->company('Sin cambio');
        $custom = $this->company('Con cambio', '20631000007');
        $custom->update(['baggage_hand_kg' => 8, 'baggage_hold_kg' => 15, 'baggage_air_kg' => 7, 'cargo_threshold_kg' => 40]);

        $this->get(route('companies.show', $plain))
            ->assertOk()
            ->assertSee('mano 10 kg, bodega 20 kg, aéreo 10 kg. Desde 50 kg');
        $this->get(route('companies.show', $custom))
            ->assertOk()
            ->assertSee('mano 8 kg, bodega 15 kg, aéreo 7 kg. Desde 40 kg');
    }

    public function test_departure_card_shows_the_web_price_with_the_company_fee(): void
    {
        $company = $this->company('Tarifa Web', '20631000008');
        $company->update(['commission_rate' => 10]);
        $origin = Port::create(['name' => 'Puerto Iquitos', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Nauta', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Rápida Web', 'registration_number' => 'WEB-1', 'vessel_type' => 'lancha', 'seat_capacity' => 20, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->addDay(), 'fare' => 100, 'status' => 'scheduled']);

        $this->get(route('routes.index'))
            ->assertOk()
            ->assertSee('S/ 110.00', false)
            ->assertSee('Elegir lugar');
    }

    public function test_photo_comments_do_not_create_company_reviews(): void
    {
        $company = $this->company('Fotos comentario');
        $photo = OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/rio.jpg',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('companies.photos.comments.store', [$company, $photo]), [
            'body' => 'Bonito atardecer',
        ])->assertCreated();

        $this->assertDatabaseHas('photo_comments', ['organization_photo_id' => $photo->id, 'status' => 'pending']);
        $this->assertDatabaseCount('company_reviews', 0);
        $this->getJson(route('companies.photos.comments', [$company, $photo]))
            ->assertOk()
            ->assertJsonCount(0, 'comments');
    }

    public function test_public_company_page_uses_the_gallery_and_opens_policies_in_a_modal(): void
    {
        $company = $this->company('Galería Pública', '20631000021');
        OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/rio.jpg',
            'status' => 'published',
            'sort_order' => 1,
        ]);

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('data-open="galeria"', false)
            ->assertSee('data-open="agencias"', false)
            ->assertSee('data-open="carga"', false)
            ->assertSee('Terminales propios y agencias autorizadas')
            ->assertSee('id="policy-modal"', false)
            ->assertSee('object-fit:contain', false)
            ->assertSee("testimonios: 'galeria'", false)
            ->assertSee("'donde-comprar': 'agencias'", false)
            ->assertDontSee('data-open="opiniones"', false)
            ->assertDontSee('data-open="politicas"', false)
            ->assertDontSee('Establecer como portada', false)
            ->assertDontSee('Establecer como logo/perfil', false)
            ->assertDontSee('Copiar teléfono')
            ->assertDontSee('Compartir perfil')
            ->assertSee('Inicia sesión para comentar y reaccionar')
            ->assertSee('Iniciar sesión')
            ->assertSee('object-fit:cover', false)
            ->assertSee('Foto de Galería Pública', false);
    }

    public function test_only_the_company_admin_can_set_a_photo_as_cover_or_logo(): void
    {
        [$admin, $company] = $this->companyAdmin('Portada Propia', '20631000022');
        $photo = OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/portada.jpg',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->post(route('company.gallery.photos.cover', $photo))->assertForbidden();
        $this->actingAs($admin)->post(route('company.gallery.photos.cover', $photo))->assertRedirect();
        $this->assertDatabaseHas('organization_banners', ['organization_id' => $company->id, 'path' => $photo->path]);

        $this->actingAs($stranger)->post(route('company.gallery.photos.logo', $photo))->assertForbidden();
        $this->actingAs($admin)->postJson(route('company.gallery.photos.logo', $photo))
            ->assertOk()
            ->assertJsonPath('logo', $photo->path);
        $this->assertSame($photo->path, $company->fresh()->logo_path);

        $this->actingAs($admin)->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('data-set-cover="'.$photo->id.'"', false)
            ->assertSee('Establecer como logo/perfil');
    }

    public function test_a_completed_trip_marks_the_photo_comment_as_traveled_with_the_company(): void
    {
        $company = $this->company('Viaje Hecho', '20631000023');
        $origin = Port::create(['name' => 'Puerto Viaje', 'city' => 'Iquitos', 'region' => 'Loreto']);
        $destination = Port::create(['name' => 'Puerto Llegada', 'city' => 'Nauta', 'region' => 'Loreto']);
        $vessel = Vessel::create(['organization_id' => $company->id, 'name' => 'Lancha Viaje', 'registration_number' => 'VIA-1', 'vessel_type' => 'lancha', 'seat_capacity' => 10, 'status' => 'ready']);
        $route = TransportRoute::create(['organization_id' => $company->id, 'origin_port_id' => $origin->id, 'destination_port_id' => $destination->id, 'status' => 'active']);
        $departure = RouteDeparture::create(['transport_route_id' => $route->id, 'vessel_id' => $vessel->id, 'departure_at' => now()->subDay(), 'fare' => 40, 'status' => 'completed']);
        $traveler = User::factory()->create();
        Reservation::create([
            'user_id' => $traveler->id,
            'route_departure_id' => $departure->id,
            'code' => 'VIA-1',
            'contact_name' => 'Ana Viaje',
            'contact_email' => $traveler->email,
            'contact_phone' => '999111222',
            'base_amount' => 40,
            'discount_amount' => 0,
            'service_fee_amount' => 0,
            'total_amount' => 40,
            'status' => 'confirmed',
        ]);
        $photo = OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/viaje.jpg',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        PhotoComment::create([
            'organization_photo_id' => $photo->id,
            'user_id' => $traveler->id,
            'body' => 'El embarque fue puntual.',
            'status' => 'published',
        ]);

        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('Viajó con la empresa')
            ->assertSee('1 comentario · 0 reacciones')
            ->assertDontSee('★★★★★');
        $this->getJson(route('companies.photos.comments', [$company, $photo]))
            ->assertOk()
            ->assertJsonPath('comments.0.traveled', true);
    }

    public function test_the_company_admin_can_edit_a_photo_title(): void
    {
        [$admin, $company] = $this->companyAdmin('Título Foto', '20631000024');
        $photo = OrganizationPhoto::create([
            'organization_id' => $company->id,
            'path' => '/storage/company-profiles/titulo.jpg',
            'caption' => 'Atardecer en el muelle',
            'status' => 'published',
            'sort_order' => 1,
        ]);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->patch(route('company.gallery.photos.caption', $photo), [
            'caption' => 'No permitido',
        ])->assertForbidden();

        $this->actingAs($admin)->get(route('company.gallery.index'))
            ->assertOk()
            ->assertSee('Editar título');

        $this->actingAs($admin)->patch(route('company.gallery.photos.caption', $photo), [
            'caption' => str_repeat('a', 121),
        ])->assertSessionHasErrors('caption');

        $this->actingAs($admin)->patch(route('company.gallery.photos.caption', $photo), [
            'caption' => 'Zarpe de la mañana',
        ])->assertRedirect();

        $this->assertSame('Zarpe de la mañana', $photo->fresh()->caption);
        $this->get(route('companies.show', $company))
            ->assertOk()
            ->assertSee('data-photo-title="Zarpe de la mañana"', false);
    }

    private function company(string $name, string $ruc = '20631000006'): Organization
    {
        return Organization::create([
            'type' => 'transport_company',
            'legal_name' => $name,
            'commercial_name' => $name,
            'ruc' => $ruc,
            'email' => strtolower(str_replace(' ', '', $name)).'@test.pe',
            'status' => 'active',
        ]);
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function companyAdmin(string $name, string $ruc): array
    {
        $organization = $this->company($name, $ruc);
        $admin = User::factory()->create();
        $role = Role::firstOrCreate(['code' => 'company_admin'], ['name' => 'Administrador empresa']);
        $admin->roles()->attach($role, ['organization_id' => $organization->id]);
        $admin->organizations()->attach($organization->id, ['status' => 'active']);

        return [$admin, $organization];
    }
}
