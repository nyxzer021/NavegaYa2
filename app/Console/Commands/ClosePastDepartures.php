<?php

namespace App\Console\Commands;

use App\Models\AirDeparture;
use App\Models\RouteDeparture;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ClosePastDepartures extends Command
{
    protected $signature = 'navegaya:close-past-departures';

    protected $description = 'Marca como finalizadas las salidas cuya llegada estimada ya pasó';

    public function handle(): int
    {
        $now = now();
        $river = $this->close(RouteDeparture::query(), $now);
        $air = $this->close(AirDeparture::query(), $now);

        $this->components->info("Salidas finalizadas: {$river} fluviales y {$air} aéreas.");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<RouteDeparture>|Builder<AirDeparture>  $query
     */
    private function close(Builder $query, Carbon $now): int
    {
        return $query
            ->whereNotIn('status', ['completed', 'cancelled', 'rescheduled'])
            ->where(function (Builder $departures) use ($now): void {
                $departures->where(function (Builder $withArrival) use ($now): void {
                    $withArrival->whereNotNull('estimated_arrival_at')->where('estimated_arrival_at', '<=', $now);
                })->orWhere(function (Builder $withoutArrival) use ($now): void {
                    $withoutArrival->whereNull('estimated_arrival_at')->where('departure_at', '<=', $now);
                });
            })
            ->update([
                'status' => 'completed',
                'is_published' => false,
                'updated_at' => $now,
            ]);
    }
}
