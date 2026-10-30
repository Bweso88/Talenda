<?php

declare(strict_types=1);

namespace App\Modules\Core\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Identifiant public stable (colonne `uuid`), distinct de l'`id` interne.
 *
 * C'est l'identifiant exposé par l'API et celui qui servira à la future
 * jonction avec Scolia (docs/PRODUCT_ARCHITECTURE.md §2) : les `id`
 * séquentiels ne quittent jamais Talenda.
 *
 * @mixin Model
 */
trait HasPublicUuid
{
    public static function bootHasPublicUuid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid7());
            }
        });
    }
}
