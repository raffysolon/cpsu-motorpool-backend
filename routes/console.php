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
