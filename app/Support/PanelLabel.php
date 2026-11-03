<?php

namespace App\Support;

class PanelLabel
{
    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            'active' => 'Activo',
            'approved' => 'Aprobada',
            'boarded' => 'Embarcado',
            'boarding' => 'Embarcando',
            'delayed' => 'Retrasada',
            'cancelled' => 'Cancelada',
            'canceled' => 'Cancelada',
            'changes_requested' => 'Con observaciones',
            'completed' => 'Completada',
            'departed' => 'En ruta',
            'draft' => 'Borrador',
            'expired' => 'Vencida',
            'failed' => 'Fallida',
            'in_transit' => 'En ruta',
            'inactive' => 'Inhabilitada',
            'lead_pending' => 'Por revisar',
            'maintenance' => 'Mantenimiento',
            'overdue' => 'Por vencer',
            'paused' => 'Pausada',
            'pending' => 'Pendiente',
            'pending_review' => 'En revisión',
            'pending_verification' => 'Pendiente de verificación',
            'processing' => 'En proceso',
            'published' => 'Publicada',
            'ready' => 'Lista',
            'refunded' => 'Reembolsada',
            'rejected' => 'Rechazada',
            'rescheduled' => 'Reprogramada',
            'scheduled' => 'Programada',
            'succeeded' => 'Confirmada',
            'suspended' => 'Suspendida',
            'suspended_river_level' => 'Suspendida por el río',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function records(): array
    {
        return [
            'Advertisement' => 'Anuncio',
            'AirDeparture' => 'Salida aérea',
            'AirRoute' => 'Ruta aérea',
            'Aircraft' => 'Aeronave',
            'AuditLog' => 'Auditoría',
            'BusinessCategory' => 'Categoría de negocio',
            'BusinessConversation' => 'Conversación',
            'BusinessMessage' => 'Mensaje',
            'CargoShipment' => 'Encomienda',
            'CheckoutOrder' => 'Pedido',
            'CompanyReview' => 'Opinión',
            'ContentReport' => 'Reporte',
            'DestinationCity' => 'Ciudad',
            'DestinationListing' => 'Ficha turística',
            'DestinationListingImage' => 'Foto de ficha',
            'DestinationListingOffer' => 'Oferta',
            'DestinationReview' => 'Valoración',
            'HomeHeroSlide' => 'Portada',
            'MasterRoute' => 'Ruta oficial',
            'NavigationItem' => 'Menú',
            'Organization' => 'Empresa',
            'OrganizationDocument' => 'Documento de empresa',
            'OrganizationInvitation' => 'Invitación',
            'Payment' => 'Pago',
            'Port' => 'Puerto',
            'PublicPage' => 'Página pública',
            'Reservation' => 'Reserva',
            'ReservationSeat' => 'Asiento reservado',
            'Role' => 'Rol',
            'RouteDeparture' => 'Salida fluvial',
            'Settlement' => 'Liquidación',
            'SiteHero' => 'Portada de sección',
            'Subscription' => 'Suscripción',
            'SubscriptionPlan' => 'Plan',
            'SupportTicket' => 'Soporte',
            'SystemSetting' => 'Configuración',
            'Ticket' => 'Boleto',
            'TransportRoute' => 'Ruta de empresa',
            'User' => 'Usuario',
            'Vessel' => 'Embarcación',
            'VesselSeat' => 'Asiento',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function events(): array
    {
        return [
            'created' => 'Registro creado',
            'updated' => 'Registro modificado',
            'deleted' => 'Registro eliminado',
            'manual_subscription_activation' => 'Activación manual de plan',
            'payment_exception_resolved' => 'Excepción de pago resuelta',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'copyright' => 'Derechos de autor',
            'discrimination' => 'Discriminación',
            'false_information' => 'Información falsa',
            'harassment' => 'Acoso',
            'inappropriate' => 'Contenido inapropiado',
            'other' => 'Otro',
            'personal_data' => 'Datos personales',
            'spam' => 'Spam',
        ];
    }

    public static function reason(?string $reason): string
    {
        if ($reason === null || trim($reason) === '') {
            return 'Sin motivo';
        }

        $key = strtolower(trim($reason));

        return self::reasons()[$key] ?? str_replace('_', ' ', $reason);
    }

    public static function status(?string $status): string
    {
        if ($status === null || trim($status) === '') {
            return 'Sin estado';
        }

        $key = strtolower(trim($status));

        return self::statuses()[$key] ?? str_replace('_', ' ', $status);
    }

    public static function record(?string $class): string
    {
        $base = class_basename((string) $class);

        return self::records()[$base] ?? $base;
    }

    public static function event(?string $event): string
    {
        if ($event === null || $event === '') {
            return 'Acción registrada';
        }

        return self::events()[$event] ?? str_replace('_', ' ', $event);
    }

    public static function change(string $field, mixed $value): string
    {
        if (is_array($value)) {
            return (string) json_encode($value);
        }

        if (! is_scalar($value) || $value === null || $value === '') {
            return '—';
        }

        $text = (string) $value;

        if ($field === 'status' || str_ends_with($field, '_status')) {
            return self::status($text);
        }

        return $text;
    }
}
