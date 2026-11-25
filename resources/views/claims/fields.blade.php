<table>
    <tr>
        <td><span class="label">{{ __('claims.number') }}</span><strong>{{ $claim->code }}</strong></td>
        <td><span class="label">{{ __('claims.date') }}</span>{{ $date }}</td>
        <td><span class="label">{{ __('claims.due') }}</span>{{ $due }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="label">{{ __('claims.csv_provider') }}</span>{{ $provider_name }}</td>
        <td><span class="label">{{ __('claims.ruc') }}</span>{{ $provider_ruc }}</td>
    </tr>
    <tr>
        <td><span class="label">{{ __('claims.name') }}</span>{{ $claim->consumer_name }}</td>
        <td><span class="label">{{ __('claims.document_type') }}</span>{{ $document }}</td>
        <td><span class="label">{{ __('claims.phone') }}</span>{{ $claim->phone }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="label">{{ __('claims.address') }}</span>{{ $claim->address }}</td>
        <td><span class="label">{{ __('claims.email') }}</span>{{ $claim->email }}</td>
    </tr>
    @if($claim->is_minor)
        <tr>
            <td><span class="label">{{ __('claims.guardian_name') }}</span>{{ $claim->guardian_name }}</td>
            <td><span class="label">{{ __('claims.guardian_document') }}</span>{{ $claim->guardian_document }}</td>
            <td><span class="label">{{ __('claims.guardian_relationship') }}</span>{{ $guardian_role }}</td>
        </tr>
    @endif
    <tr>
        <td><span class="label">{{ __('claims.good_kind') }}</span>{{ $good }}</td>
        <td><span class="label">{{ __('claims.amount') }}</span>{{ $amount }}</td>
        <td><span class="label">{{ __('claims.type_heading') }}</span>{{ $type }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="label">{{ __('claims.description') }}</span>{{ $claim->good_description }}</td>
    </tr>
    @if($claim->reservation_code)
        <tr>
            <td colspan="3"><span class="label">{{ __('claims.reservation') }}</span>{{ $claim->reservation_code }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="3"><span class="label">{{ __('claims.detail') }}</span>{{ $claim->detail }}</td>
    </tr>
    <tr>
        <td colspan="3"><span class="label">{{ __('claims.request') }}</span>{{ $claim->request_text }}</td>
    </tr>
    @if($claim->answered())
        <tr>
            <td colspan="3"><span class="label">{{ __('claims.response') }}</span>{{ $claim->response_text }}</td>
        </tr>
    @endif
</table>
