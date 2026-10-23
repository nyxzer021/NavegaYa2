<?php

namespace Tests\Feature;

use App\Models\CompanyReview;
use App\Models\ContentReport;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_report_a_published_company_review(): void
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Selva SAC', 'ruc' => '20123456789', 'email' => 'selva@test.pe', 'status' => 'active']);
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $review = CompanyReview::create(['organization_id' => $organization->id, 'user_id' => $author->id, 'rating' => 2, 'comment' => 'Comentario público para la empresa.', 'status' => 'published']);

        $this->actingAs($reporter)->post(route('companies.reviews.report', [$organization, $review]), ['reason' => 'harassment', 'details' => 'Contiene un ataque personal.'])->assertRedirect();

        $this->assertDatabaseHas('content_reports', ['content_type' => 'company_review', 'content_id' => $review->id, 'priority' => 'high', 'status' => 'open']);
    }

    public function test_super_admin_can_hide_reported_content_with_a_recorded_reason(): void
    {
        $organization = Organization::create(['type' => 'transport_company', 'legal_name' => 'Selva SAC', 'ruc' => '20123456789', 'email' => 'selva@test.pe', 'status' => 'active']);
        $author = User::factory()->create();
        $review = CompanyReview::create(['organization_id' => $organization->id, 'user_id' => $author->id, 'rating' => 1, 'comment' => 'Comentario que será moderado.', 'status' => 'published']);
        $report = ContentReport::create(['reporter_id' => $author->id, 'content_type' => 'company_review', 'content_id' => $review->id, 'reason' => 'harassment', 'priority' => 'high']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin']));

        $this->actingAs($admin)->patch(route('admin.moderation.resolve', $report), ['resolution' => 'hide', 'moderator_notes' => 'Se oculta por incumplir las normas de convivencia.'])->assertRedirect();

        $this->assertSame('rejected', $review->fresh()->status);
        $this->assertDatabaseHas('content_reports', ['id' => $report->id, 'status' => 'resolved', 'resolution' => 'hide', 'reviewed_by' => $admin->id]);
        $this->actingAs($admin)->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertSee('Reportes de usuarios')
            ->assertDontSee('Imágenes de perfiles pendientes');
        $this->get(route('admin.moderation.index', ['tab' => 'profiles']))
            ->assertOk()
            ->assertSee('Imágenes de perfiles pendientes')
            ->assertDontSee('Reportes de usuarios');
        $this->get(route('admin.moderation.index', ['tab' => 'reviews']))
            ->assertOk()
            ->assertSee('Opiniones pendientes')
            ->assertDontSee('Reportes de usuarios');
    }
}
