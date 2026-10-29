<?php

use App\Modules\Assessments\AssessmentsServiceProvider;
use App\Modules\Finances\FinancesServiceProvider;
use App\Modules\SchoolLife\SchoolLifeServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\PlatformPanelProvider;
use App\Providers\Filament\SchoolPanelProvider;

return [
    AppServiceProvider::class,
    FinancesServiceProvider::class,
    AssessmentsServiceProvider::class,
    SchoolLifeServiceProvider::class,
    PlatformPanelProvider::class,
    SchoolPanelProvider::class,
];
