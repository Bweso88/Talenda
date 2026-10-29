<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Identité et coordonnées de l'école : marque blanche (logo, couleur) et
 * en-tête des documents imprimés.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $short_name
 * @property string|null $motto
 * @property string|null $address
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $director_name
 * @property string|null $logo_path
 * @property string|null $primary_color
 */
#[Fillable(['short_name', 'motto', 'address', 'city', 'phone', 'email', 'director_name', 'logo_path', 'primary_color'])]
class TenantSetting extends Model
{
    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk('public')->url($this->logo_path);
    }
}
