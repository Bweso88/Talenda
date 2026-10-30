<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de trésorerie de l'école.
 *
 * @property int $approval_threshold
 */
#[Fillable(['approval_threshold'])]
class TreasurySetting extends Model
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'approval_threshold' => 50000,
    ];

    protected function casts(): array
    {
        return [
            'approval_threshold' => 'integer',
        ];
    }

    /**
     * Réglages de l'école active (créés à la première lecture).
     */
    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
