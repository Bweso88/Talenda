<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Modules\Academic\Models\Site;
use App\Modules\Core\Contracts\TracksUsage;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Enums\CashRegisterKind;
use App\Modules\Finances\Policies\FinanceSettingsPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * Caisse : guichet en espèces, compte mobile money ou compte en banque
 * (docs/TRESORERIE.md). Une école peut en avoir plusieurs, rattachées à ses
 * sites. Son solde se lit dans le journal comptable.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string $name
 * @property CashRegisterKind $kind
 * @property string $account_code
 * @property int $opening_balance
 * @property bool $is_active
 * @property Site|null $site
 * @property CashSession|null $openSession
 */
#[UsePolicy(FinanceSettingsPolicy::class)]
#[Fillable(['site_id', 'name', 'kind', 'account_code', 'opening_balance', 'is_active'])]
class CashRegister extends Model implements TracksUsage
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'kind' => 'cash',
        'opening_balance' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'kind' => CashRegisterKind::class,
            'opening_balance' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CashRegister $register): void {
            if (blank($register->getAttribute('account_code'))) {
                $register->account_code = $register->kind->defaultAccount();
            }

            if ($register->site_id === null) {
                $register->site_id = Site::main()?->id;
            }
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
     * @return HasMany<JournalLine, $this>
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Argent présent dans la caisse : solde de départ et mouvements du
     * journal.
     */
    public function balance(): int
    {
        return $this->opening_balance + (int) $this->journalLines()->sum(DB::raw('debit - credit'));
    }

    /**
     * Libellé du compte dans le journal : « Caisse — Caisse principale ».
     */
    public function accountLabel(): string
    {
        return match ($this->kind) {
            CashRegisterKind::Cash => 'Caisse',
            CashRegisterKind::MobileMoney => 'Monnaie électronique',
            CashRegisterKind::Bank => 'Banque',
        }.' — '.$this->name;
    }

    /**
     * @return HasMany<CashSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /**
     * @return HasOne<CashSession, $this>
     */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->whereNull('closed_at');
    }

    public function isInUse(): bool
    {
        return $this->sessions()->exists() || $this->journalLines()->exists();
    }
}
