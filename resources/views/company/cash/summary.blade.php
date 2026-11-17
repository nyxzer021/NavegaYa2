@php($money = fn (float $amount): string => 'S/ '.number_format($amount, 2))
<dl class="ny-cash-grid">
 @if($summary['purposes'] !== [])
 <div>
  <dt>{{ __('cash.purposes') }}</dt>
  @foreach($summary['purposes'] as $purpose => $amount)
   @if((int) round($amount * 100) !== 0)<dd>{{ $purpose === 'cargo' ? __('cash.cargo') : __('cash.ticket') }} <b>{{ $money($amount) }}</b></dd>@endif
  @endforeach
 </div>
 @endif
 @if($summary['methods'] !== [])
 <div>
  <dt>{{ __('cash.methods') }}</dt>
  @foreach($summary['methods'] as $method => $amount)
   @if((int) round($amount * 100) !== 0)<dd>{{ __('cash.method_'.$method) }} <b>{{ $money($amount) }}</b></dd>@endif
  @endforeach
 </div>
 @endif
 @if(count($summary['sellers']) > 0)
 <div>
  <dt>{{ __('cash.sellers') }}</dt>
  @foreach($summary['sellers'] as $seller => $amount)
   @if((int) round($amount * 100) !== 0)<dd>{{ $seller }} <b>{{ $money($amount) }}</b></dd>@endif
  @endforeach
 </div>
 @endif
</dl>
@if($summary['operations'] !== [])
 <h3>{{ __('cash.operations') }}</h3>
 <ul class="ny-cash-list">
  @foreach($summary['operations'] as $operation)
   <li><strong>{{ __('cash.method_'.$operation['method']) }}</strong><span>{{ $operation['number'] }}</span><b>{{ $money($operation['amount']) }}</b></li>
  @endforeach
 </ul>
@endif
@if((int) round($summary['commission'] * 100) !== 0)
 <p class="ny-cash-commission">{{ __('cash.commission') }} <b>{{ $money($summary['commission']) }}</b></p>
@endif
<p class="ny-cash-expected">{{ __('cash.expected') }} <b>{{ $money($summary['expected']) }}</b></p>
