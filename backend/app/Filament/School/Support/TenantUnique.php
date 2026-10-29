<?php

namespace App\Filament\School\Support;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Validation\Rules\Unique;

/**
 * Restreint une règle `unique` de formulaire à l'école active : le code
 * « MATH » peut exister dans deux écoles différentes.
 */
final class TenantUnique
{
    public static function scope(): Closure
    {
        return fn (Unique $rule): Unique => $rule->where('tenant_id', Filament::getTenant()?->getKey());
    }
}
