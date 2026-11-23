@if ($present && $tail)
    {{ __('connections.masked', ['tail' => $tail]) }}
@elseif ($present)
    {{ __('connections.present') }}
@else
    {{ __('connections.absent') }}
@endif
