<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Advertisement extends Model
{
    public const BUSINESS_TYPES = ['lodge_hotel' => 'Lodge / hotel', 'restaurant' => 'Restaurante', 'tour_operator' => 'Operador de tours', 'charter_transport' => 'Transporte chárter'];

    public const PLACEMENTS = ['home_hero' => 'Portada principal', 'routes_sidebar' => 'Catálogo de rutas', 'company_footer' => 'Perfil de empresas', 'ticket_voucher' => 'Boleto digital'];

    protected $fillable = ['destination_city_id', 'business_name', 'business_type', 'contact_name', 'phone_whatsapp', 'email', 'city_destination', 'ruc', 'placements', 'category', 'title', 'description', 'target_url', 'image_url', 'banner_image_path', 'starts_on', 'ends_on', 'monthly_fee', 'status', 'admin_notes', 'views', 'clicks'];

    protected function casts(): array
    {
        return ['placements' => 'array', 'starts_on' => 'date', 'ends_on' => 'date', 'monthly_fee' => 'decimal:2'];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(DestinationCity::class, 'destination_city_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(fn (Builder $builder) => $builder->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn (Builder $builder) => $builder->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->active();
    }

    public function scopeForPlacement(Builder $query, string $placement): Builder
    {
        return $query->whereJsonContains('placements', $placement);
    }

    public function getCreativeUrlAttribute(): ?string
    {
        return $this->banner_image_path ? Storage::url($this->banner_image_path) : $this->image_url;
    }
}
