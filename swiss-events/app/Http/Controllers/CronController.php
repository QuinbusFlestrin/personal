<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\HttpFoundation\Response as ResponseCode;

/**
 * Infomaniak shared hosting has no shell-level crontab access — its task
 * scheduler instead hits a URL on a timer. This is that URL: a thin,
 * token-protected trigger for Laravel's own scheduler, which owns the real
 * cadence (see routes/console.php).
 */
class CronController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('services.cron.secret');
        $token = (string) $request->query('token', '');

        if (! $secret || ! hash_equals($secret, $token)) {
            abort(ResponseCode::HTTP_FORBIDDEN);
        }

        Artisan::call('schedule:run');

        return response(Artisan::output(), ResponseCode::HTTP_OK)->header('Content-Type', 'text/plain');
    }
}
