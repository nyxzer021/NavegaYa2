@props([
    'theme' => 'routes',
    'image' => null,
    'class' => 'min-h-[220px]',
    'overlay' => 'from-slate-950/90 via-slate-900/70 to-slate-950/50',
])

@php
    $approvedAmazonImage = asset('images/navegaya-hero-amazonas.webp');

    $backgrounds = [
        'home' => [
            'local_file' => 'images/branding/banner-home-viajeros-amazonia.webp',
            'badge' => 'Pasajes Fluviales y Aéreos en Loreto',
            'title' => 'Viaja por la Amazonía con Total Confianza',
            'subtitle' => 'Compara rutas oficiales, consulta horarios y compra tus boletos con confirmación inmediata.',
        ],
        'routes' => [
            'local_file' => 'images/navegaya-hero-amazonas.png',
            'title' => 'Rutas Fluviales y Vuelos en Loreto',
            'badge' => 'Rutas Fluviales y Aéreas de Loreto',
            'subtitle' => 'Itinerarios de zarpe y conexiones bimodales entre Iquitos, Yurimaguas, Nauta y la frontera amazónica.',
        ],
        'air' => [
            'local_file' => 'images/branding/banner-vuelos-amazonia.webp',
            'badge' => 'Conexiones Aéreas de la Amazonía',
            'title' => 'Vuelos Regionales en Loreto',
            'subtitle' => 'Horarios, aerotaxis e hidroaviones para conectar Iquitos con comunidades y destinos amazónicos.',
        ],
        'advertise' => [
            'local_file' => 'images/branding/banner-afiliar-empresa.webp',
            'badge' => 'Red Comercial NavegaYA',
            'title' => 'Haz Visible tu Negocio en Loreto',
            'subtitle' => 'Vende pasajes en el marketplace o anuncia tu hotel, lodge o restaurante ante miles de viajeros.',
        ],
        'companies' => [
            'local_file' => 'images/branding/banner-empresas-amazonia.webp',
            'badge' => 'Operadores Fluviales y Aéreos',
            'title' => 'Empresas de Transporte Certificadas',
            'subtitle' => 'Flota de embarcaciones rápidas, motonaves, aerotaxis e hidroaviones en la región Loreto.',
        ],
        'ports' => [
            'local_file' => 'images/branding/banner-puertos-nauta.webp',
            'badge' => 'Infraestructura Ribereña y Aérea',
            'title' => 'Puertos, Embarcaderos y Pistas de Aterrizaje',
            'subtitle' => 'Puntos de embarque oficiales: Puerto Enapu, Masusa, Embarcadero de Nauta y aeródromos locales.',
        ],
        'guide' => [
            'local_file' => 'images/branding/banner-guia-loreto.webp',
            'badge' => 'Turismo y Destinos Amazónicos',
            'title' => 'Guía de Viaje y Conectividad en Loreto',
            'subtitle' => 'Recomendaciones, tiempos de navegación por río y conexiones con lodges y reservas naturales.',
        ],
        'check_ticket' => [
            'local_file' => 'images/branding/banner-consultar-pasaje.webp',
            'badge' => 'Servicio al Pasajero',
            'title' => 'Consulta y Descarga tu Pasaje',
            'subtitle' => 'Ingresa tu código de reserva o DNI para verificar el estado de tu boleto y el muelle de embarque.',
        ],
        'affiliate' => [
            'local_file' => 'images/branding/banner-afiliar-empresa.webp',
            'badge' => 'Red Comercial NavegaYA',
            'title' => 'Haz Visible tu Negocio en Loreto',
            'subtitle' => 'Vende pasajes en el marketplace o anuncia tu hotel, lodge o restaurante ante miles de viajeros.',
        ],
    ];

    $selected = $backgrounds[$theme] ?? $backgrounds['routes'];
    $source = $image
        ? (str_starts_with($image, 'http') ? $image : asset(ltrim($image, '/')))
        : (file_exists(public_path($selected['local_file'])) ? asset($selected['local_file']) : $approvedAmazonImage);
@endphp

<section {{ $attributes->except('class')->merge(['class' => 'relative mx-auto mb-6 min-h-[300px] max-w-[1600px] overflow-hidden rounded-b-[2rem] bg-slate-950 text-white shadow-xl sm:min-h-[320px] sm:rounded-b-[2.5rem] '.$class]) }} data-amazon-theme="{{ $theme }}">
    <img
        src="{{ $source }}"
        alt="{{ $selected['title'] }}"
        class="absolute inset-0 h-full w-full object-cover object-center brightness-[.88] saturate-[1.08]"
        fetchpriority="{{ $theme === 'home' ? 'high' : 'auto' }}"
    >
    <div class="absolute inset-0 bg-gradient-to-r {{ $overlay }}"></div>
    <div class="absolute inset-0 bg-gradient-to-b from-slate-950/20 via-slate-950/10 to-slate-950/45"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgba(251,191,36,.12),transparent_34%)]"></div>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-b from-transparent to-slate-50/55 sm:h-12"></div>

    @if($slot->isEmpty())
        <div class="relative z-10 max-w-3xl px-6 py-8 sm:px-10">
            <span class="mb-2 inline-flex max-w-full whitespace-nowrap rounded border border-emerald-300/50 bg-emerald-950/75 px-2 py-1 text-[9px] font-bold tracking-normal text-emerald-100 shadow-sm sm:px-2.5 sm:text-[11px]">● {{ $selected['badge'] }}</span>
            <h1 class="m-0 text-2xl font-black tracking-tight text-white [text-shadow:0_2px_10px_rgba(0,0,0,.65)] sm:text-3xl lg:text-4xl">{{ $selected['title'] }}</h1>
            <p class="mt-2 text-xs font-semibold leading-relaxed text-white [text-shadow:0_1px_8px_rgba(0,0,0,.85)] sm:text-sm">{{ $selected['subtitle'] }}</p>
        </div>
    @else
        <div class="relative z-10 mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">{{ $slot }}</div>
    @endif
</section>

