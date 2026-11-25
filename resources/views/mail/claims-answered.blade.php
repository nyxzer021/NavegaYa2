<p>{{ __('claims.mail_answered_body', ['code' => $claim->code]) }}</p>
<p>{{ $claim->response_text }}</p>
<p><a href="{{ route('claims.show', $claim->public_token) }}">{{ __('claims.open_sheet') }}</a></p>
