<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Infomaniak's task scheduler hits a token-protected URL once (as finely as
// its plan allows), which calls `schedule:run` — these entries own the real
// cadence. Import runs first so same-day events can appear in that day's
// digest once digests:send lands in Phase 2.
Schedule::command('events:import')->dailyAt('04:00')->withoutOverlapping();
