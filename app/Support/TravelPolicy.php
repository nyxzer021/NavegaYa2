<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\PolicyDocument;
use App\Models\PolicyVersion;
use App\Models\User;
use Illuminate\Support\Facades\File;

class TravelPolicy
{
    public static function template(): string
    {
        $path = base_path('docs/politicas-de-viaje-plantilla.md');
        if (File::exists($path)) {
            return File::get($path);
        }

        return 'Política de viaje de NavegaYA. Equipaje de mano 10 kg, bodega 20 kg, aéreo 10 kg y carga desde 50 kg.';
    }

    public static function ensureBase(): PolicyDocument
    {
        $document = PolicyDocument::query()->firstOrCreate(
            ['kind' => 'travel_base', 'organization_id' => null],
            ['title' => 'Política de viaje base'],
        );
        if (! $document->versions()->exists()) {
            $document->versions()->create([
                'version' => 1,
                'summary' => 'Plantilla base de NavegaYA.',
                'body_html' => PolicyHtml::clean('<p>'.nl2br(e(self::template())).'</p>'),
                'effective_on' => now()->toDateString(),
                'published_at' => now(),
            ]);
        }

        return $document;
    }

    public static function ensureCompany(Organization $organization): PolicyDocument
    {
        $base = self::ensureBase()->versions()->whereNotNull('published_at')->orderByDesc('version')->first();
        $document = PolicyDocument::query()->firstOrCreate(
            ['kind' => 'company_travel', 'organization_id' => $organization->id],
            ['title' => 'Política de viaje de '.($organization->commercial_name ?: $organization->legal_name)],
        );
        if (! $document->versions()->exists()) {
            $document->versions()->create([
                'version' => 1,
                'summary' => 'Copia de la plantilla base.',
                'body_html' => $base?->body_html ?: PolicyHtml::clean('<p>'.e(self::template()).'</p>'),
                'effective_on' => now()->toDateString(),
                'published_at' => now(),
            ]);
        }

        return $document->load('versions');
    }

    public static function publish(PolicyDocument $document, string $html, ?string $summary, ?User $editor, ?string $reason): PolicyVersion
    {
        $next = ((int) $document->versions()->max('version')) + 1;

        return $document->versions()->create([
            'version' => $next,
            'summary' => $summary,
            'body_html' => PolicyHtml::clean($html),
            'effective_on' => now()->toDateString(),
            'published_at' => now(),
            'edited_by' => $editor?->id,
            'reason' => $reason,
        ]);
    }

    public static function currentPlatformTerms(): ?PolicyVersion
    {
        return PolicyVersion::query()
            ->whereNotNull('published_at')
            ->whereHas('document', fn ($query) => $query->where('kind', 'platform_terms')->whereNull('organization_id'))
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function publicPoints(Organization $organization): array
    {
        $baggage = BaggageAllowance::for($organization);

        return [
            'Equipaje fluvial: mano '.$baggage['hand'].' kg y bodega '.$baggage['hold'].' kg.',
            'Equipaje aéreo: '.$baggage['air'].' kg.',
            'Si el equipaje supera '.$baggage['cargo'].' kg se registra como envío de carga.',
            'Si la empresa cancela o reprograma, el pasajero elige reembolso total o cambio sin costo.',
            'Si el pasajero desiste, aplican los plazos de la empresa, informados antes de pagar.',
        ];
    }
}
