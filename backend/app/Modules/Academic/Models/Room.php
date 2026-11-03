<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use App\Modules\Academic\Policies\StructurePolicy;
use App\Modules\Core\Concerns\HasPublicUuid;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Salle d'un site, préenregistrée : les classes la choisissent dans la
 * liste.
 *
 * @property int $id
 * @property string $uuid
 * @property int $site_id
 * @property string $name
 * @property int|null $capacity
 * @property bool $is_active
 * @property Site $site
 */
#[UsePolicy(StructurePolicy::class)]
#[Fillable(['site_id', 'name', 'capacity', 'is_active'])]
class Room extends Model implements TracksUsage
{
    use BelongsToTenant;
    use HasPublicUuid;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Room $room): void {
            $room->site_id ??= Site::main()?->id;
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function isInUse(): bool
    {
        return $this->schoolClasses()->exists();
    }
}
