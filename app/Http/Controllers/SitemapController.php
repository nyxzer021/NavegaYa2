<?php

namespace App\Http\Controllers;

use App\Models\DestinationCity;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\PublicPage;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Páginas públicas que los buscadores pueden indexar. Nunca paneles, cuentas ni boletos.
     *
     * @var list<string>
     */
    private const PUBLIC_ROUTES = [
        'home', 'routes.index', 'companies.index', 'guide.index', 'discover.index', 'ports.public',
        'about', 'weather.index', 'cargo.track', 'claims.create', 'advertising.create', 'company.register',
    ];

    /**
     * Rutas privadas que los buscadores no deben recorrer.
     *
     * @var list<string>
     */
    private const PRIVATE_PATHS = [
        '/admin', '/empresa/', '/mi-negocio', '/carrito', '/mis-viajes', '/reserva', '/boleto',
        '/perfil', '/mensajes-empresas', '/centro-de-solicitudes', '/equipo', '/agencia',
        '/libro-de-reclamaciones/constancia', '/abordaje', '/login', '/register',
    ];

    public function sitemap(): Response
    {
        $urls = collect(self::PUBLIC_ROUTES)->map(fn (string $name): string => route($name));

        $urls = $urls
            ->merge(Organization::query()->where('type', 'transport_company')->where('status', 'active')
                ->orderBy('id')->get(['id'])->map(fn (Organization $organization): string => route('companies.show', $organization)))
            ->merge(DestinationListing::query()->publiclyVisible()->orderBy('id')->get()
                ->map(fn (DestinationListing $listing): string => route('guide.show', $listing)))
            ->merge(DestinationCity::query()->where('is_active', true)->orderBy('id')->get()
                ->map(fn (DestinationCity $city): string => route('discover.show', $city)))
            ->merge(PublicPage::query()->where('is_published', true)->orderBy('id')->pluck('slug')
                ->map(fn (string $slug): string => route('pages.show', $slug)))
            ->unique()
            ->values();

        $body = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->map(fn (string $url): string => '  <url><loc>'.e($url).'</loc></url>')->implode("\n")."\n"
            .'</urlset>'."\n";

        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        foreach (self::PRIVATE_PATHS as $path) {
            $lines[] = 'Disallow: '.$path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('sitemap');

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
