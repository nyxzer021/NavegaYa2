<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('navegaya:backup-database')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->when(fn () => config('operations.backups.enabled'));

Schedule::command('navegaya:backup-uploads')
    ->dailyAt('02:20')
    ->withoutOverlapping()
    ->when(fn () => config('operations.backups.enabled'));

Schedule::command('queue:prune-failed --hours=336')
    ->dailyAt('03:00')
    ->withoutOverlapping();

Schedule::command('navegaya:operations-health')
    ->everyFiveMinutes()
    ->withoutOverlapping();
