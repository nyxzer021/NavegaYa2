<?php

namespace Tests\Feature;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPaymentRequest;
use App\Models\SubscriptionPlan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubscriptionTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 0));
        Storage::fake('local');
    }

    public function test_a_business_sends_a_voucher_and_the_amount_comes_from_the_plan(): void
    {
        [$owner, $subscription] = $this->business();
        SystemSetting::updateOrCreate(['key' => 'subscription_payment_instructions'], ['value' => 'BCP 191-0000000 · Yape 965000000']);

        $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'plan']))
            ->assertOk()->assertSee('Pagar por transferencia, Yape o Plin')->assertSee('BCP 191-0000000');

        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher(['months' => 3, 'amount' => 1]))
            ->assertRedirect(route('marketplace.dashboard', ['tab' => 'plan']))->assertSessionHasNoErrors();

        $request = SubscriptionPaymentRequest::query()->firstOrFail();
        $this->assertSame('pending', $request->status);
        $this->assertSame('900.00', (string) $request->amount);
        Storage::disk('local')->assertExists($request->voucher_path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subscription.transfer_requested', 'organization_id' => $subscription->organization_id]);

        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher(['operation_number' => 'OP-2']))
            ->assertSessionHasErrors('voucher');
        $this->assertSame(1, SubscriptionPaymentRequest::query()->count());

        $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'plan']))
            ->assertOk()->assertSee('Tu comprobante está en revisión.');
    }

    public function test_approving_activates_the_plan_for_the_paid_months_only_once(): void
    {
        [$owner, $subscription] = $this->business();
        $admin = $this->superAdmin();
        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher(['months' => 3]))->assertSessionHasNoErrors();
        $request = SubscriptionPaymentRequest::query()->firstOrFail();

        $this->actingAs($admin)->get(route('admin.advertising-subscriptions.index'))
            ->assertOk()->assertSee('Comprobantes de pago por revisar')->assertSee('OP-123');
        $this->actingAs($admin)->get(route('admin.advertising-subscriptions.vouchers.show', $request))->assertOk();
        $this->actingAs($admin)->get(route('admin.requests.index'))
            ->assertOk()->assertSee('Comprobante por revisar')->assertSee('Hotel Transferencia');

        $this->actingAs($admin)->patch(route('admin.advertising-subscriptions.vouchers.approve', $request))->assertSessionHasNoErrors();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame(today()->addMonths(3)->toDateString(), $subscription->ends_on->toDateString());
        $this->assertSame('approved', $request->fresh()->status);
        $payment = Payment::query()->where('provider_reference', 'TRF-'.$request->id)->firstOrFail();
        $this->assertSame('900.00', (string) $payment->amount);
        $this->assertSame($payment->id, $request->fresh()->payment_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'subscription.transfer_approved', 'organization_id' => $subscription->organization_id]);

        $this->actingAs($admin)->patch(route('admin.advertising-subscriptions.vouchers.approve', $request))->assertSessionHasErrors('payment_request');
        $this->assertSame(1, Payment::query()->where('provider_reference', 'TRF-'.$request->id)->count());
        $this->assertSame(today()->addMonths(3)->toDateString(), $subscription->fresh()->ends_on->toDateString());
    }

    public function test_a_rejected_voucher_shows_the_reason_and_lets_the_business_try_again(): void
    {
        [$owner, $subscription] = $this->business();
        $admin = $this->superAdmin();
        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher())->assertSessionHasNoErrors();
        $request = SubscriptionPaymentRequest::query()->firstOrFail();

        $this->actingAs($admin)->patch(route('admin.advertising-subscriptions.vouchers.reject', $request), [
            'rejection_reason' => 'El monto no coincide con el plan.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('pending', $subscription->fresh()->status);
        $this->actingAs($owner)->get(route('marketplace.dashboard', ['tab' => 'plan']))
            ->assertOk()->assertSee('NavegaYA rechazó tu último comprobante.')->assertSee('El monto no coincide con el plan.');
        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher(['operation_number' => 'OP-NUEVA']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, SubscriptionPaymentRequest::query()->count());
    }

    public function test_only_the_super_admin_sees_vouchers_and_bad_files_are_rejected(): void
    {
        [$owner] = $this->business();
        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher([
            'voucher' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ]))->assertSessionHasErrors('voucher');

        $this->actingAs($owner)->post(route('marketplace.subscription.transfer'), $this->voucher())->assertSessionHasNoErrors();
        $request = SubscriptionPaymentRequest::query()->firstOrFail();

        $this->actingAs($owner)->get(route('admin.advertising-subscriptions.vouchers.show', $request))->assertForbidden();
        $this->actingAs($owner)->patch(route('admin.advertising-subscriptions.vouchers.approve', $request))->assertForbidden();
        $this->assertSame('pending', $request->fresh()->status);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function voucher(array $extra = []): array
    {
        return array_merge([
            'method' => 'yape',
            'months' => 1,
            'operation_number' => 'OP-123',
            'paid_on' => today()->toDateString(),
            'voucher' => UploadedFile::fake()->image('comprobante.jpg'),
        ], $extra);
    }

    /**
     * @return array{0: User, 1: Subscription}
     */
    private function business(): array
    {
        $plan = SubscriptionPlan::where('code', 'basic')->firstOrFail();
        $city = DestinationCity::where('name', 'Iquitos')->firstOrFail();
        $organization = Organization::create([
            'type' => 'marketplace_business', 'legal_name' => 'Hotel Transferencia SAC', 'commercial_name' => 'Hotel Transferencia',
            'ruc' => '20699333444', 'email' => 'hotel@transferencia.test', 'phone' => '965000222', 'status' => 'pending',
        ]);
        $owner = User::factory()->create();
        $owner->organizations()->attach($organization->id, ['status' => 'pending']);
        $owner->roles()->attach(Role::where('code', 'marketplace_admin')->firstOrFail()->id, ['organization_id' => $organization->id]);
        $subscription = Subscription::create([
            'organization_id' => $organization->id, 'subscription_plan_id' => $plan->id, 'status' => 'pending',
            'monthly_price' => $plan->monthly_price, 'currency' => 'PEN', 'starts_on' => today(),
        ]);
        DestinationListing::create([
            'organization_id' => $organization->id, 'owner_user_id' => $owner->id, 'destination_city_id' => $city->id,
            'type' => 'lodging', 'status' => 'draft', 'name' => 'Hotel Transferencia', 'sort_order' => 99, 'is_active' => false,
        ]);

        return [$owner, $subscription];
    }

    private function superAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Superadministrador'])->id);

        return $admin;
    }
}
