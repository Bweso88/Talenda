<?php

declare(strict_types=1);

namespace App\Modules\Core\Tenancy;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * À utiliser sur tout modèle métier d'une école (colonne tenant_id).
 *
 * - applique TenantScope à toutes les requêtes ;
 * - renseigne tenant_id à la création depuis l'école active, et refuse la
 *   création si aucune école n'est active.
 *
 * Piège connu (hérité de l'expérience Scolia) : attach()/sync() sur une
 * relation BelongsToMany insèrent en SQL direct sans passer par ce trait.
 * Une table pivot portant tenant_id doit être alimentée via son propre
 * modèle Eloquent.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if (! empty($model->getAttribute('tenant_id'))) {
                return;
            }

            $context = app(TenantContext::class);

            if (! $context->check()) {
                throw new LogicException(sprintf(
                    'Impossible de créer %s sans école active (TenantContext).',
                    $model::class,
                ));
            }

            $model->setAttribute('tenant_id', $context->id());
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
