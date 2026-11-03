@props(['columns' => 1])
<div class="ny-stage">
    <div class="ny-hull" style="--ny-cols: {{ max(1, (int) $columns) }}">
        <p class="ny-bow">Proa</p>
        <div class="ny-cabin">{{ $slot }}</div>
        <p class="ny-stern">Popa</p>
    </div>
</div>
