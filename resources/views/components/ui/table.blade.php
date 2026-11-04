<div {{ $attributes->merge(['class' => 'ny-ui-table']) }}>
    <table>
        @isset($head)<thead><tr>{{ $head }}</tr></thead>@endisset
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
