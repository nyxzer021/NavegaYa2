<?php

namespace App\Support;

use App\Models\Advertisement;
use App\Models\CompanyReview;
use App\Models\ContentReport;
use App\Models\DestinationListing;
use App\Models\Organization;
use App\Models\OrganizationDocument;
use App\Models\OrganizationModality;
use App\Models\OrganizationPhoto;
use App\Models\Payment;
use App\Models\PhotoComment;
use App\Models\SupportTicket;
use App\Models\Vessel;
use Illuminate\Support\Collection;

class AdminRequestInbox
{
    public function signature(): string
    {
        return hash('sha256', $this->items()->map(fn (array $item) => [
            $item['type'],
            $item['title'],
            $item['status'],
            $item['created_at']?->timestamp,
        ])->toJson());
    }

    public function items(): Collection
    {
        return collect()
            ->concat(Payment::query()->whereIn('purpose', ['subscription', 'subscription_upgrade'])->where('requires_review', true)->with('subscription.organization')->get()->map(fn ($payment) => [
                'type' => 'payment', 'icon' => 'online_payments', 'area' => 'Excepciones de pago',
                'title' => $payment->subscription?->organization?->commercial_name ?: $payment->subscription?->organization?->legal_name ?: 'Empresa anunciante',
                'detail' => $payment->review_reason ?: 'El proveedor de pagos requiere una validación manual',
                'status' => 'Revisión necesaria', 'created_at' => $payment->updated_at,
                'url' => route('admin.advertising-subscriptions.index', ['status' => 'pending']), 'action' => 'Resolver excepción',
            ]))
            ->concat(Organization::query()->whereIn('type', ['transport_company', 'agency'])->where('status', 'pending')->get()->map(fn ($organization) => [
                'type' => 'organization', 'icon' => 'company_details', 'area' => 'Afiliaciones',
                'title' => $organization->commercial_name ?: $organization->legal_name,
                'detail' => ($organization->type === 'transport_company' ? 'Nueva empresa transportista' : 'Nueva agencia').' · RUC '.($organization->ruc ?: 'sin registrar'),
                'status' => 'Validación pendiente', 'created_at' => $organization->created_at,
                'url' => route('admin.organizations.index'), 'action' => 'Revisar empresa',
            ]))
            ->concat(Vessel::query()->where('status', 'pending_review')->with('organization')->get()->map(fn ($vessel) => [
                'type' => 'fleet', 'icon' => 'fleet', 'area' => 'Flota',
                'title' => $vessel->name,
                'detail' => ($vessel->organization?->commercial_name ?: $vessel->organization?->legal_name ?: 'Operador').' · matrícula '.$vessel->registration_number,
                'status' => 'Revisión técnica pendiente', 'created_at' => $vessel->submitted_for_review_at ?: $vessel->updated_at,
                'url' => route('admin.fleet-compliance.index'), 'action' => 'Revisar unidad',
            ]))
            ->concat(DestinationListing::query()->where('status', 'pending_review')->with('organization')->get()->map(fn ($listing) => [
                'type' => 'listing', 'icon' => 'page', 'area' => 'Página pública',
                'title' => $listing->name,
                'detail' => ($listing->organization?->commercial_name ?: 'Negocio anunciante').' envió su ficha para publicación',
                'status' => 'Contenido por moderar', 'created_at' => $listing->updated_at,
                'url' => route('admin.tourism-partners.index'), 'action' => 'Moderar ficha',
            ]))
            ->concat(Advertisement::query()->where('status', 'lead_pending')->get()->map(fn ($advertisement) => [
                'type' => 'advertisement', 'icon' => 'advertising', 'area' => 'Publicidad',
                'title' => $advertisement->business_name,
                'detail' => $advertisement->title ?: 'Nueva solicitud de campaña publicitaria',
                'status' => 'Solicitud comercial pendiente', 'created_at' => $advertisement->created_at,
                'url' => route('admin.advertisements.index', ['status' => 'lead_pending']), 'action' => 'Revisar campaña',
            ]))
            ->concat($this->contentToModerate())
            ->concat(ContentReport::query()->whereIn('status', ['open', 'in_review'])->with('reporter')->get()->map(fn ($report) => [
                'type' => 'report', 'icon' => 'alert', 'area' => 'Usuarios e incidencias',
                'title' => $report->reporter?->name ?: 'Reporte de visitante',
                'detail' => str_replace('_', ' ', $report->reason).($report->details ? ' · '.$report->details : ''),
                'status' => $report->priority === 'high' ? 'Prioridad alta' : 'Reporte pendiente', 'created_at' => $report->created_at,
                'url' => route('admin.moderation.index'), 'action' => 'Revisar reporte',
            ]))
            ->concat(OrganizationModality::query()->where('status', 'requested')->with('organization')->get()->map(fn ($modality) => [
                'type' => 'modality', 'icon' => 'company_details', 'area' => 'Modalidades por aprobar',
                'title' => ($modality->organization?->commercial_name ?: $modality->organization?->legal_name ?: 'Empresa').' · '.$modality->label(),
                'detail' => 'Permiso '.($modality->permit_number ?: 'sin número'),
                'status' => 'Solicitada', 'created_at' => $modality->updated_at,
                'url' => route('admin.companies.modalities.index'), 'action' => 'Revisar modalidad',
            ]))
            ->concat($this->expiringDocuments())
            ->concat(SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting_user'])->with(['user', 'organization'])->get()->map(fn ($ticket) => [
                'type' => 'support', 'icon' => 'support', 'area' => 'Consultas y soporte',
                'title' => $ticket->subject,
                'detail' => ($ticket->organization?->commercial_name ?: $ticket->user?->name ?: 'Usuario').' · '.$ticket->code,
                'status' => match ($ticket->status) {
                    'in_progress' => 'En atención', 'waiting_user' => 'Esperando respuesta', default => 'Nueva solicitud'
                },
                'created_at' => $ticket->created_at,
                'url' => route('support.index'), 'action' => 'Responder solicitud',
            ]))
            ->sortByDesc(fn ($item) => $item['created_at']?->timestamp ?? 0)
            ->values();
    }

    private function contentToModerate(): Collection
    {
        $photos = OrganizationPhoto::query()->where('status', 'pending')->with('organization')->get()->map(fn ($photo) => [
            'type' => 'content_moderation', 'icon' => 'page', 'area' => 'Contenido por moderar',
            'title' => $photo->organization?->commercial_name ?: $photo->organization?->legal_name ?: 'Foto de empresa',
            'detail' => $photo->caption ?: 'Foto pendiente de revisión',
            'status' => 'Pendiente', 'created_at' => $photo->created_at,
            'url' => route('admin.content-moderation.index'), 'action' => 'Moderar contenido',
        ]);
        $comments = PhotoComment::query()->where('status', 'pending')->with('user')->get()->map(fn ($comment) => [
            'type' => 'content_moderation', 'icon' => 'page', 'area' => 'Contenido por moderar',
            'title' => $comment->user?->name ?: 'Comentario de foto',
            'detail' => $comment->body,
            'status' => 'Pendiente', 'created_at' => $comment->created_at,
            'url' => route('admin.content-moderation.index'), 'action' => 'Moderar contenido',
        ]);
        $reviews = CompanyReview::query()->where('status', 'pending')->with('organization')->get()->map(fn ($review) => [
            'type' => 'content_moderation', 'icon' => 'page', 'area' => 'Contenido por moderar',
            'title' => $review->organization?->commercial_name ?: $review->organization?->legal_name ?: 'Opinión',
            'detail' => $review->comment,
            'status' => 'Pendiente', 'created_at' => $review->created_at,
            'url' => route('admin.content-moderation.index'), 'action' => 'Moderar contenido',
        ]);

        return $photos->concat($comments)->concat($reviews);
    }

    private function expiringDocuments(): Collection
    {
        $today = now()->toDateString();
        $until = now()->addDays(30)->toDateString();
        $documents = OrganizationDocument::query()->with('organization')->whereBetween('expires_on', [$today, $until])->get()->map(fn ($document) => [
            'type' => 'expiring_document', 'icon' => 'alert', 'area' => 'Documentos por vencer (≤ 30 días)',
            'title' => $document->organization?->commercial_name ?: $document->organization?->legal_name ?: 'Empresa',
            'detail' => $document->type.' · '.($document->expires_on?->format('d/m/Y') ?: ''),
            'status' => 'Por vencer', 'created_at' => $document->updated_at,
            'url' => $document->organization ? route('admin.companies.show', $document->organization).'#documentos' : route('admin.companies.documents.expiring'),
            'action' => 'Ver documento',
        ]);
        $permits = OrganizationModality::query()->with('organization')->where(function ($query) use ($today, $until): void {
            $query->whereBetween('permit_expires_on', [$today, $until])->orWhereBetween('aoc_expires_on', [$today, $until]);
        })->get()->map(fn ($modality) => [
            'type' => 'expiring_document', 'icon' => 'alert', 'area' => 'Documentos por vencer (≤ 30 días)',
            'title' => ($modality->organization?->commercial_name ?: $modality->organization?->legal_name ?: 'Empresa').' · '.$modality->label(),
            'detail' => 'Permiso '.($modality->permit_expires_on?->format('d/m/Y') ?: 'sin fecha'),
            'status' => 'Por vencer', 'created_at' => $modality->updated_at,
            'url' => route('admin.companies.documents.expiring'), 'action' => 'Ver documento',
        ]);

        return $documents->concat($permits);
    }

    public function summary(): array
    {
        $items = $this->items();

        return [
            'total' => $items->count(),
            'payments' => $items->where('type', 'payment')->count(),
            'organizations' => $items->where('type', 'organization')->count(),
            'fleet' => $items->where('type', 'fleet')->count(),
            'content' => $items->whereIn('type', ['listing', 'advertisement'])->count(),
            'reports' => $items->where('type', 'report')->count(),
            'support' => $items->where('type', 'support')->count(),
            'latest' => $items->take(5),
        ];
    }
}
