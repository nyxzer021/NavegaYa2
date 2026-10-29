<?php

namespace App\Services;

use App\Models\Advertisement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdvertisementInventoryService
{
    public function select(string $placement, int $limit = 1, Collection|array $excluded = [], ?string $city = null, array $businessTypes = []): Collection
    {
        $excluded = collect($excluded)->filter();
        $ids = $excluded->pluck('id')->filter();
        $organizations = $excluded->pluck('organization_id')->filter();
        $names = $excluded->pluck('business_name')->filter();

        return Advertisement::query()->with('city')->active()->forPlacement($placement)
            ->when($ids->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('id', $ids))
            ->when($organizations->isNotEmpty(), fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->whereNull('organization_id')->orWhereNotIn('organization_id', $organizations)))
            ->when($names->isNotEmpty(), fn (Builder $query) => $query->whereNotIn('business_name', $names))
            ->when($businessTypes, fn (Builder $query) => $query->whereIn('business_type', $businessTypes))
            ->orderBy('views')->orderBy('id')->get()
            ->sortBy(fn (Advertisement $ad) => ($city && strcasecmp((string) $ad->city_destination, $city) === 0 ? '0-' : '1-').str_pad((string) $ad->views, 12, '0', STR_PAD_LEFT).'-'.$ad->id)
            ->unique(fn (Advertisement $ad) => $ad->organization_id ? 'organization-'.$ad->organization_id : 'business-'.$ad->business_name)
            ->take($limit)->values();
    }

    public function register(Collection|Advertisement|null $advertisements): void
    {
        if (! $advertisements) return;
        ($advertisements instanceof Advertisement ? collect([$advertisements]) : $advertisements)
            ->each(fn (Advertisement $advertisement) => $advertisement->increment('views'));
    }
}
