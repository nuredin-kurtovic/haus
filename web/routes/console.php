<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| HAUS automatika
|--------------------------------------------------------------------------
|
| Lokalno: php artisan schedule:work. U produkciji jedan cron red koji svake
| minute zove schedule:run. Sve tri komande su idempotentne, pa ponovljen
| pokusaj poslije pada ne duplira ni kredite ni fakture.
|
*/

// Rok je obecanje, pa se provjerava svake minute, ne jednom dnevno.
Schedule::command('haus:check-deadlines')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Naplata obnove ide prije radnog vremena, da dispecer zatekne gotove ishode.
Schedule::command('haus:process-renewals')
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Podsjetnik 60 dana prije isteka, u pristojno vrijeme.
Schedule::command('haus:renewal-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping();
