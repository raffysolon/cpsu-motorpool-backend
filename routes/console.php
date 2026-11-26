<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('trips:check-departures', function () {
    app(\App\Services\TripDepartureMonitor::class)->check();
})->purpose('Notify admins of trips not started after the five-minute grace period');

Schedule::command('trips:check-departures')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trackers:check-heartbeats', function () {
    $count = app(\App\Services\TrackerHeartbeatMonitor::class)->check();
    $this->info("Marked {$count} stale trackers offline.");
})->purpose('Mark trackers offline after the connectivity timeout');

Schedule::command('trackers:check-heartbeats')->everyMinute()->withoutOverlapping();

Artisan::command('trackers:check-movement', function () {
    $count = app(\App\Services\VehicleMovementMonitor::class)->checkRecent();
    $this->info("Processed {$count} fresh live GPS fixes.");
})->purpose('Check fresh GPS fixes for sustained vehicle movement');

Schedule::command('trackers:check-movement')->everyMinute()->withoutOverlapping();
