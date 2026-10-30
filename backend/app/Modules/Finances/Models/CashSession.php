<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Session de caisse : de l'ouverture (fond de caisse) à la clôture
 * (comptage des espèces, écart justifié).
 *
 * @property int $id
 * @property int $cash_register_id
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property int $opening_float
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property int|null $expected_cash
 * @property int|null $counted_cash
 * @property string|null $closing_note
 * @property CashRegister $cashRegister
 * @property User $opener
 * @property User|null $closer
 */
#[Fillable(['cash_register_id', 'opened_by', 'opened_at', 'opening_float', 'closed_by', 'closed_at', 'expected_cash', 'counted_cash', 'closing_note'])]
class CashSession extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'integer',
            'expected_cash' => 'integer',
            'counted_cash' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CashRegister, $this>
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Espèces qui doivent se trouver dans la caisse : son solde dans le
     * journal (fond de départ, encaissements en espèces non annulés,
     * dépenses, avances, virements).
     */
    public function currentExpectedCash(): int
    {
        return $this->loadMissing('cashRegister')->cashRegister->balance();
    }

    /**
     * Écart de caisse à la clôture (positif : excédent).
     */
    public function difference(): ?int
    {
        return $this->counted_cash === null || $this->expected_cash === null
            ? null
            : $this->counted_cash - $this->expected_cash;
    }
}
