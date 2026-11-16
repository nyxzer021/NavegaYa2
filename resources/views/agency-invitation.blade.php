<x-guest-layout>
 <h1 class="text-2xl font-bold">{{ __('agencies.invite') }}</h1>
 <p class="mt-2 text-sm">{{ $invitation->agency->name }}</p>
 <form method="POST" action="{{ route('agency.invitation.store', $invitation->token) }}" class="mt-6 space-y-4">
  @csrf
  <label class="block text-sm font-semibold">{{ __('agencies.contact') }}<input class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" name="name" required></label>
  <label class="block text-sm font-semibold">{{ __('agencies.password') }}<input class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" type="password" name="password" required></label>
  <label class="block text-sm font-semibold">{{ __('agencies.password_confirm') }}<input class="mt-1 w-full rounded-lg border px-3 py-2 text-sm" type="password" name="password_confirmation" required></label>
  <button class="min-h-11 rounded-lg px-5 py-3 text-sm font-bold text-white" style="background: var(--ny-primary-deep)" type="submit">{{ __('agencies.create_password') }}</button>
  @if($errors->any())<p class="text-sm text-red-700">{{ $errors->first() }}</p>@endif
 </form>
</x-guest-layout>
