<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('research:collect')->hourly()->withoutOverlapping();
Schedule::command('research:collect')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('pipeline:sweep-stuck')->everyFifteenMinutes();
