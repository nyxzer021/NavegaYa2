<p>{{ __('claims.mail_filed_body', ['code' => $claim->code]) }}</p>
<p><a href="{{ route('claims.show', $claim->public_token) }}">{{ __('claims.open_sheet') }}</a></p>
