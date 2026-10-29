<?php

declare(strict_types=1);

namespace App\Modules\Core\Models;

use Database\Factories\PlatformAdminFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Super Admin de la plateforme Talenda (guard `platform`). N'a aucune
 * appartenance à une école.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 */
#[UseFactory(PlatformAdminFactory::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class PlatformAdmin extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'platform';
    }
}
