<?php

use App\Modules\Finances\FinancesServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\SchoolPanelProvider;

return [
    AppServiceProvider::class,
    FinancesServiceProvider::class,
    PlatformPanelProvider::class,
    SchoolPanelProvider::class,
];
