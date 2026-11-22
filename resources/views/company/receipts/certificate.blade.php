<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #173b33; font-size: 13px; }
        h1 { margin: 0; font-size: 22px; }
        .banner { margin: 16px 0; padding: 12px 14px; border: 2px solid #173b33; font-size: 16px; font-weight: bold; }
        .note { margin: 0 0 16px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 8px 10px; border: 1px solid #d8e5e0; vertical-align: top; }
        .label { display: block; font-size: 10px; color: #678078; text-transform: uppercase; }
        .amount { font-size: 18px; font-weight: bold; text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <p class="note">{{ __('receipts.company_ruc') }} {{ $company_ruc ?: '—' }} · {{ __('receipts.issued_on') }} {{ $issued }}</p>
    <p class="banner">{{ $disclaimer }}</p>
    <p class="note">{{ $electronic }}</p>
    <table>
        <tr>
            <td><span class="label">{{ __('receipts.certificate') }}</span><strong>{{ $number }}</strong></td>
            <td><span class="label">{{ __('receipts.kind') }}</span>{{ $kind }}</td>
            <td><span class="label">{{ __('receipts.reference') }}</span>{{ $reference ?: '—' }}</td>
        </tr>
        <tr>
            <td><span class="label">{{ __('receipts.buyer') }}</span>{{ $client }}</td>
            <td><span class="label">{{ __('receipts.csv_document') }}</span>{{ $document }}</td>
            <td><span class="label">{{ __('receipts.address') }}</span>{{ $address ?: '—' }}</td>
        </tr>
        <tr>
            <td colspan="3"><span class="label">{{ __('receipts.amount') }}</span><div class="amount">{{ $amount }}</div></td>
        </tr>
    </table>
</body>
</html>
