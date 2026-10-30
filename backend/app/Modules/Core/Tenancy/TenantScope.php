<?php

declare(strict_types=1);

namespace App\Modules\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtre toute requête Eloquent d'un modèle d'école sur l'école active
 * (voir TenantContext pour la règle « fermée par défaut »).
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->check()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->id());

            return;
        }

        if (! $context->isBypassed()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
