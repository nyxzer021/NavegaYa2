@props(['eyebrow' => null, 'title', 'description' => null])
<section {{ $attributes->merge(['class' => 'ny-hero']) }} style="padding:30px">
    @if($eyebrow)<p style="margin:0;color:#f6ce63;font-size:11px;font-weight:800;letter-spacing:.12em">{{ $eyebrow }}</p>@endif
    <h1 style="margin:6px 0 0;font-size:clamp(30px,4vw,46px);line-height:1.1">{{ $title }}</h1>
    @if($description)<p style="max-width:720px;margin:10px 0 0;color:#ddf0e9;font-size:15px">{{ $description }}</p>@endif
    @if(isset($actions))<div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:18px">{{ $actions }}</div>@endif
</section>
