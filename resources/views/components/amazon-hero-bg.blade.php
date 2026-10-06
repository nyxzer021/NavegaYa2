@props([
    'theme' => 'routes',
    'class' => 'min-h-[220px]',
    'overlay' => 'from-slate-950/90 via-slate-900/70 to-slate-950/50',
])

@php
    $approvedAmazonImage = asset('images/navegaya-hero-amazonas.png');

    $backgrounds = [
        'home' => [
            'local_file' => 'images/navegaya-hero-rio.png',
            'badge' => 'Pasajes Fluviales y Aéreos en Loreto',
            'title' => 'Viaja por la Amazonía con Total Confianza',
            'subtitle' => 'Compara rutas oficiales, consulta horarios y compra tus boletos con confirmación inmediata.',
        ],
        'routes' => [
            'local_file' => 'images/branding/banner-rutas-rio.jpg',
            'title' => 'Rutas Fluviales y Vuelos en Loreto',
            'badge' => 'Rutas Fluviales y Aéreas de Loreto',
            'subtitle' => 'Itinerarios de zarpe y conexiones bimodales entre Iquitos, Yurimaguas, Nauta y la frontera amazónica.',
        ],
        'advertise' => [
            'local_file' => 'images/branding/banner-afiliar.jpg',
            'badge' => 'Red Comercial NavegaYA',
            'title' => 'Haz Visible tu Negocio en Loreto',
            'subtitle' => 'Vende pasajes en el marketplace o anuncia tu hotel, lodge o restaurante ante miles de viajeros.',
        ],
        'companies' => [
            'local_file' => 'images/branding/banner-empresas.jpg',
            'badge' => 'Operadores Fluviales y Aéreos',
            'title' => 'Empresas de Transporte Certificadas',
            'subtitle' => 'Flota de embarcaciones rápidas, motonaves, aerotaxis e hidroaviones en la región Loreto.',
        ],
        'ports' => [
            'local_file' => 'images/branding/banner-puertos.jpg',
            'badge' => 'Infraestructura Ribereña y Aérea',
            'title' => 'Puertos, Embarcaderos y Pistas de Aterrizaje',
            'subtitle' => 'Puntos de embarque oficiales: Puerto Enapu, Masusa, Embarcadero de Nauta y aeródromos locales.',
        ],
        'guide' => [
            'local_file' => 'images/branding/banner-guia.jpg',
            'badge' => 'Turismo y Destinos Amazónicos',
            'title' => 'Guía de Viaje y Conectividad en Loreto',
            'subtitle' => 'Recomendaciones, tiempos de navegación por río y conexiones con lodges y reservas naturales.',
        ],
        'check_ticket' => [
            'local_file' => 'images/branding/banner-tickets.jpg',
            'badge' => 'Servicio al Pasajero',
            'title' => 'Consulta y Descarga tu Pasaje',
            'subtitle' => 'Ingresa tu código de reserva o DNI para verificar el estado de tu boleto y el muelle de embarque.',
        ],
        'affiliate' => [
            'local_file' => 'images/branding/banner-afiliar.jpg',
            'badge' => 'Red Comercial NavegaYA',
            'title' => 'Haz Visible tu Negocio en Loreto',
            'subtitle' => 'Vende pasajes en el marketplace o anuncia tu hotel, lodge o restaurante ante miles de viajeros.',
        ],
    ];

    $selected = $backgrounds[$theme] ?? $backgrounds['routes'];
    $source = file_exists(public_path($selected['local_file']))
        ? asset($selected['local_file'])
        : $approvedAmazonImage;
@endphp

<section {{ $attributes->except('class')->merge(['class' => 'relative mx-auto mt-2 mb-6 max-w-[1600px] overflow-hidden rounded-2xl bg-slate-950 text-white '.$class]) }} data-amazon-theme="{{ $theme }}">
    <img
        src="{{ $source }}"
        alt="{{ $selected['title'] }}"
        class="absolute inset-0 h-full w-full object-cover object-center brightness-[.70]"
        fetchpriority="{{ $theme === 'home' ? 'high' : 'auto' }}"
    >
    <div class="absolute inset-0 bg-gradient-to-r {{ $overlay }}"></div>
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_75%_20%,rgba(251,191,36,.12),transparent_34%)]"></div>

    @if($slot->isEmpty())
        <div class="relative z-10 max-w-3xl px-6 py-8 sm:px-10">
            <span class="mb-2 inline-block rounded border border-emerald-500/40 bg-emerald-500/25 px-2.5 py-0.5 text-[11px] font-bold text-emerald-300">● {{ $selected['badge'] }}</span>
            <h1 class="m-0 text-2xl font-black tracking-tight text-white sm:text-3xl lg:text-4xl">{{ $selected['title'] }}</h1>
            <p class="mt-2 text-xs font-medium leading-relaxed text-slate-200 sm:text-sm">{{ $selected['subtitle'] }}</p>
        </div>
    @else
        <div class="relative z-10 mx-auto max-w-[1600px] px-4 py-6 sm:px-6 lg:px-8">{{ $slot }}</div>
    @endif
</section>
