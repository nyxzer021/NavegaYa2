@props(['rows' => []])
<dl class="ny-ui-money">
    @foreach($rows as $row)
        <div>
            <dt>{{ $row['label'] }}</dt>
            <dd>{{ $row['value'] }}</dd>
        </div>
    @endforeach
</dl>
