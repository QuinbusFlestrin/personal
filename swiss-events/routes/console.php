<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Infomaniak's task scheduler hits a token-protected URL on a timer, which
// calls `schedule:run` — these entries own the real cadence.
//
// Deliberately hourly rather than ->dailyAt(): schedule:run only fires a task
// when invoked *during* its due minute, and shared hosting gives no guarantee
// about which minute the trigger lands on. A daily entry paired with a trigger
// that fires at, say, :17 would simply never run, and would never error either.
// Running hourly and letting the command decide what is actually due (see the
// staleness guard in ImportEvents) means a missed or misaligned tick self-heals
// on the next hour instead of silently losing a day's import.
Schedule::command('events:import')->hourly()->withoutOverlapping();
