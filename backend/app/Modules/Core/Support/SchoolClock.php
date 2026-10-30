<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Date du jour à l'heure de l'école active (Brazzaville : UTC+1), et non
 * à l'heure du serveur : un appel fait à 0 h 30 compte pour le bon jour.
 */
final class SchoolClock
{
    public static function today(): Carbon
    {
        $timezone = app(TenantContext::class)->get()->timezone ?? config('app.timezone');

        return Carbon::parse(now($timezone)->toDateString());
    }
}
