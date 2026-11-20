<x-guest-layout :title="($existing ? __('staff.accept_existing_title') : __('staff.accept_title')).' · NavegaYA'" :description="__('staff.accept_lead', ['company' => $invitation->organization->commercial_name ?: $invitation->organization->legal_name, 'profile' => __('company_portal.role.'.($invitation->profile === 'seller' ? 'counter' : $invitation->profile))])">
    @php
        $company = $invitation->organization->commercial_name ?: $invitation->organization->legal_name;
        $profile = __('company_portal.role.'.($invitation->profile === 'seller' ? 'counter' : $invitation->profile));
    @endphp
    <h1 class="text-2xl font-bold">{{ $existing ? __('staff.accept_existing_title') : __('staff.accept_title') }}</h1>
    <p class="mt-2 text-sm">{{ $existing ? __('staff.accept_existing', ['email' => $invitation->email, 'company' => $company]) : __('staff.accept_lead', ['company' => $company, 'profile' => $profile]) }}</p>
    <form method="POST" action="{{ route('staff.invitation.store', $invitation->token) }}" class="mt-6 space-y-4">
        @csrf
        @unless($existing)
            <label class="block text-sm font-semibold">{{ __('staff.password') }}
                <input class="mt-1 h-10 w-full rounded-lg border px-3 text-sm" type="password" name="password" required minlength="8">
            </label>
            <label class="block text-sm font-semibold">{{ __('staff.password_confirm') }}
                <input class="mt-1 h-10 w-full rounded-lg border px-3 text-sm" type="password" name="password_confirmation" required minlength="8">
            </label>
        @endunless
        <button class="min-h-11 rounded-lg px-5 py-3 text-sm font-bold text-white" style="background: var(--ny-primary-deep)" type="submit">{{ $existing ? __('staff.join') : __('staff.create_password') }}</button>
        @if($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
    </form>
</x-guest-layout>
