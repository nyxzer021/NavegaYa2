<?php

namespace App\Support;

class AdminPageTitle
{
    public static function fromRoute(?string $name): string
    {
        $titles = [
            'admin.dashboard' => 'Resumen operativo',
            'admin.requests.index' => 'Solicitudes',
            'admin.companies.index' => 'Empresas de transporte',
            'admin.companies.create' => 'Nueva empresa',
            'admin.companies.edit' => 'Editar empresa',
            'admin.companies.show' => 'Empresa de transporte',
            'admin.itineraries.index' => 'Itinerarios',
            'admin.itineraries.edit' => 'Editar salida',
            'admin.fleet-compliance.index' => 'Flota por validar',
            'admin.commissions.index' => 'Comisiones y facturación',
            'admin.sales.index' => 'Ventas globales',
            'admin.tours.index' => 'Tours y experiencias',
            'admin.tourism-partners.index' => 'Aliados turísticos',
            'admin.business-categories.index' => 'Categorías de negocios',
            'admin.advertisements.index' => 'Publicidad y pautas',
            'admin.advertising-subscriptions.index' => 'Suscripciones publicitarias',
            'admin.refunds.index' => 'Reembolsos y soporte',
            'admin.settings.index' => 'Configuración',
            'admin.navigation.index' => 'Menú y páginas',
            'admin.destinations.index' => 'Destinos',
            'admin.destinations.create' => 'Nuevo destino',
            'admin.destinations.edit' => 'Editar destino',
            'admin.destinations.reviews' => 'Valoraciones de destinos',
            'admin.audit.index' => 'Auditoría',
            'admin.reports.index' => 'Reportes',
            'admin.users.index' => 'Usuarios',
            'admin.users.create' => 'Nuevo usuario',
            'admin.users.edit' => 'Editar usuario',
            'admin.organizations.index' => 'Solicitudes de empresa',
            'admin.organization-directory.index' => 'Empresas y agencias',
            'admin.organization-directory.show' => 'Ficha de empresa',
            'admin.operation.index' => 'Operación de transporte',
            'admin.ports.index' => 'Puertos y terminales',
            'admin.ports.create' => 'Nuevo puerto',
            'admin.ports.edit' => 'Editar puerto',
            'admin.ports.show' => 'Puerto o terminal',
            'admin.cargo.index' => 'Carga y encomiendas',
            'admin.boarding.index' => 'Control de embarque',
            'admin.transport-routes.index' => 'Rutas y salidas',
            'admin.master-routes.index' => 'Rutas oficiales',
            'admin.master-routes.create' => 'Nueva ruta oficial',
            'admin.master-routes.edit' => 'Editar ruta oficial',
            'admin.vessels.index' => 'Embarcaciones',
            'admin.air-routes.index' => 'Rutas aéreas',
            'admin.home-hero.index' => 'Portada principal',
            'admin.site-heroes.index' => 'Portadas del sitio',
            'admin.moderation.index' => 'Moderación',
        ];

        if ($name !== null && isset($titles[$name])) {
            return $titles[$name].' · NavegaYA';
        }

        return 'Panel Administrador · NavegaYA';
    }
}
