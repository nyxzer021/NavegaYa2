<x-guest-layout :title="__('staff.closed_title').' · NavegaYA'" :description="__('staff.link_closed')">
    <h1 class="text-2xl font-bold">{{ __('staff.closed_title') }}</h1>
    <p class="mt-2 text-sm">
        @if($invitation->accepted_at)
            {{ __('staff.closed_used') }}
        @elseif($invitation->cancelled_at)
            {{ __('staff.closed_cancelled') }}
        @else
            {{ __('staff.closed_expired') }}
        @endif
    </p>
</x-guest-layout>
