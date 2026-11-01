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

    protected $description = 'Marca como finalizadas las salidas que ya debieron llegar, con margen por retraso de zarpe';

    public function handle(): int
    {
        $graceHours = max(0, (int) config('operations.departure_close_grace_hours', 3));
        $cutoff = now()->subHours($graceHours);
        $river = $this->close(RouteDeparture::query(), $cutoff);
        $air = $this->close(AirDeparture::query(), $cutoff);

        $this->components->info("Salidas finalizadas: {$river} fluviales y {$air} aéreas.");

        return self::SUCCESS;
    }

    /**
     * @param  Builder<RouteDeparture>|Builder<AirDeparture>  $query
     */
    private function close(Builder $query, Carbon $cutoff): int
    {
        return $query
            ->whereNotIn('status', ['completed', 'cancelled', 'rescheduled'])
            ->where(function (Builder $departures) use ($cutoff): void {
                $departures->where(function (Builder $withArrival) use ($cutoff): void {
                    $withArrival->whereNotNull('estimated_arrival_at')->where('estimated_arrival_at', '<=', $cutoff);
                })->orWhere(function (Builder $withoutArrival) use ($cutoff): void {
                    $withoutArrival->whereNull('estimated_arrival_at')->where('departure_at', '<=', $cutoff);
                });
            })
            ->update([
                'status' => 'completed',
                'is_published' => false,
                'updated_at' => now(),
            ]);
    }
}
