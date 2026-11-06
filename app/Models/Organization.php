<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Organization extends Model
{
    protected static function booted(): void
    {
        static::created(fn (self $model) => $model->internal_code ?: $model->forceFill(['internal_code' => 'EMP-'.str_pad((string) $model->id, 6, '0', STR_PAD_LEFT)])->saveQuietly());
    }

    protected $fillable = [
        'type', 'modality', 'base_city', 'base_port_id', 'legal_name', 'commercial_name', 'contact_name',
        'representative_document_number', 'representative_email', 'representative_phone',
        'ruc', 'email', 'phone', 'whatsapp', 'whatsapp_message', 'whatsapp_cargo', 'whatsapp_hours_from', 'whatsapp_hours_until', 'whatsapp_show_outside_hours',
        'service_hours', 'show_public_whatsapp', 'whatsapp_hidden_by_admin', 'whatsapp_hidden_reason', 'whatsapp_hidden_by', 'whatsapp_hidden_at',
        'address', 'website', 'status', 'is_marketplace_paused', 'commission_rate', 'commercial_plan', 'agreement_number',
        'bank_name', 'bank_account', 'bank_cci',
        'commission_starts_on', 'commission_ends_on', 'commission_notes', 'physical_sales_commission_rate',
        'verified_at', 'contact_verified_at', 'public_description', 'logo_path', 'cover_image_path', 'profile_content_status', 'public_presentation',
        'public_primary_color', 'public_accent_color', 'baggage_hand_kg', 'baggage_hold_kg', 'baggage_air_kg', 'cargo_threshold_kg',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'contact_verified_at' => 'datetime',
            'is_marketplace_paused' => 'boolean',
            'show_public_whatsapp' => 'boolean',
            'whatsapp_show_outside_hours' => 'boolean',
            'whatsapp_hidden_by_admin' => 'boolean',
            'whatsapp_hidden_at' => 'datetime',
            'public_presentation' => 'array',
            'commission_rate' => 'decimal:2',
            'commission_starts_on' => 'date',
            'commission_ends_on' => 'date',
            'physical_sales_commission_rate' => 'decimal:2',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('status')->withTimestamps();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function marketplaceListings(): HasMany
    {
        return $this->hasMany(DestinationListing::class);
    }

    public function advertisements(): HasMany
    {
        return $this->hasMany(Advertisement::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(OrganizationDocument::class);
    }

    public function modalities(): HasMany
    {
        return $this->hasMany(OrganizationModality::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class);
    }

    public function basePort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'base_port_id');
    }

    public function hasModality(string $code): bool
    {
        $this->loadMissing('modalities');

        return $this->modalities->contains(fn (OrganizationModality $row): bool => $row->code === $code && $row->status === 'approved');
    }

    /**
     * Modalidades aprobadas. Si la empresa todavía no tiene filas, se usa la modalidad heredada.
     *
     * @return list<string>
     */
    public function approvedModalityCodes(): array
    {
        $this->loadMissing('modalities');
        if ($this->modalities->isNotEmpty()) {
            return $this->modalities
                ->where('status', 'approved')
                ->pluck('code')
                ->values()
                ->all();
        }

        return self::codesFromLegacy($this->modality);
    }

    /**
     * El catálogo no tiene un tipo propio de acuatizaje. El aéreo fluvial usa
     * los puertos y muelles fluviales activos; el aéreo usa aeródromos.
     */
    public function acceptsCatalogPort(Port $port): bool
    {
        if (! $port->is_active) {
            return false;
        }

        $codes = $this->approvedModalityCodes();
        $side = $port->publicSide();
        if (in_array('fluvial', $codes, true) && $side === 'fluvial') {
            return true;
        }
        if (in_array('aereo', $codes, true) && $side === 'aereo') {
            return true;
        }

        return in_array('aereo_fluvial', $codes, true) && $side === 'fluvial';
    }

    /**
     * @return Collection<int, Port>
     */
    public function catalogPorts(): Collection
    {
        return Port::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('city')
            ->get()
            ->filter(fn (Port $port): bool => $this->acceptsCatalogPort($port))
            ->values();
    }

    /**
     * @return list<string>
     */
    public static function codesFromLegacy(?string $modality): array
    {
        return match ($modality) {
            'aereo' => ['aereo'],
            'mixto' => ['fluvial', 'aereo'],
            default => ['fluvial'],
        };
    }

    /**
     * @param  list<string>  $codes
     */
    public static function legacyFromCodes(array $codes): string
    {
        $hasRiver = in_array('fluvial', $codes, true);
        $hasAir = in_array('aereo', $codes, true) || in_array('aereo_fluvial', $codes, true);

        return match (true) {
            $hasRiver && $hasAir => 'mixto',
            $hasAir => 'aereo',
            default => 'fluvial',
        };
    }

    public function syncLegacyModality(): void
    {
        $approved = $this->modalities()->where('status', 'approved')->pluck('code');
        if ($approved->isEmpty()) {
            return;
        }
        $legacy = self::legacyFromCodes($approved->all());
        if ($this->modality !== $legacy) {
            $this->forceFill(['modality' => $legacy])->save();
        }
    }

    public function publicWhatsapp(string $purpose = 'ticket'): ?string
    {
        if (! $this->show_public_whatsapp || $this->whatsapp_hidden_by_admin) {
            return null;
        }
        $raw = $purpose === 'cargo' ? ($this->whatsapp_cargo ?: $this->whatsapp) : $this->whatsapp;

        return PhoneNumber::peru($raw);
    }

    public function whatsappNotice(): ?string
    {
        if (! $this->whatsapp_show_outside_hours || ! $this->whatsapp_hours_from) {
            return null;
        }
        $now = now()->format('H:i');
        $from = substr((string) $this->whatsapp_hours_from, 0, 5);
        $until = $this->whatsapp_hours_until ? substr((string) $this->whatsapp_hours_until, 0, 5) : null;
        $inside = $until === null || $until === $from
            ? false
            : ($until > $from ? ($now >= $from && $now <= $until) : ($now >= $from || $now <= $until));

        return $inside ? null : 'Te responderemos desde las '.$from.'.';
    }

    public function transportRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    public function vessels(): HasMany
    {
        return $this->hasMany(Vessel::class);
    }

    public function aircraft(): HasMany
    {
        return $this->hasMany(Aircraft::class);
    }

    public function airRoutes(): HasMany
    {
        return $this->hasMany(AirRoute::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyReview::class);
    }

    public function banners(): HasMany
    {
        return $this->hasMany(OrganizationBanner::class)->orderBy('position');
    }

    public function albums(): HasMany
    {
        return $this->hasMany(OrganizationAlbum::class)->orderBy('position');
    }

    public function pageSections(): HasMany
    {
        return $this->hasMany(OrganizationPageSection::class)->orderBy('position');
    }

    public function travelPolicies(): HasMany
    {
        return $this->hasMany(PolicyDocument::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(OrganizationPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function routeDepartures(): HasManyThrough
    {
        return $this->hasManyThrough(RouteDeparture::class, TransportRoute::class);
    }

    public function airDepartures(): HasManyThrough
    {
        return $this->hasManyThrough(AirDeparture::class, AirRoute::class);
    }
}
