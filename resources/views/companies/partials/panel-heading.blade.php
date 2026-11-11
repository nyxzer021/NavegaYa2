@php
    $icons = [
        'salidas' => 'M4 20a1 1 0 0 1-1-1V4h2v14h15v2H4Zm3-3V12h3v5H7Zm5 0V7h3v10h-3Zm5 0v-6h3v6h-3Z',
        'informacion' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm1 15h-2v-6h2v6Zm0-8h-2V7h2v2Z',
        'flota' => 'M3 15.2 12 19l9-3.8v-2.2L12 16.2 3 13v2.2ZM12 3 4 8.2 12 12l8-3.8L12 3Z',
        'galeria' => 'M5 4h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm2.2 3.2a1.6 1.6 0 1 0 0 3.2 1.6 1.6 0 0 0 0-3.2ZM19 17l-4.2-5.2-2.6 3.1-1.7-2L6 17h13Z',
        'agencias' => 'M4 20V9.2L12 4l8 5.2V20h-5.2v-5.5H9.2V20H4Z',
        'carga' => 'M3 7.2 12 3l9 4.2v9.6L12 21l-9-4.2V7.2Z',
    ];
@endphp
<header class="company-panel-title">
    <span class="company-tone is-{{ $tone }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$tone] }}"/></svg></span>
    <h2>{{ $title }}</h2>
</header>
