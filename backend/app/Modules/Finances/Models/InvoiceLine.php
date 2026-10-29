<?php

declare(strict_types=1);

namespace App\Modules\Finances\Models;

use App\Models\User;
use App\Modules\Core\Tenancy\BelongsToTenant;
use App\Modules\Finances\Enums\FeeBilling;
use App\Modules\Finances\Enums\InvoiceLineKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Ligne de facture : frais, réduction ou correction. Écriture seule.
 *
 * @property int $id
 * @property int $invoice_id
 * @property InvoiceLineKind $kind
 * @property int|null $fee_type_id
 * @property FeeBilling $billing
 * @property string $label
 * @property int $amount
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property User|null $author
 */
#[Fillable(['invoice_id', 'kind', 'fee_type_id', 'billing', 'label', 'amount', 'reason', 'created_by'])]
class InvoiceLine extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'kind' => InvoiceLineKind::class,
            'billing' => FeeBilling::class,
            'amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Une ligne de facture ne se modifie pas : ajoutez une correction.'));
        static::deleting(fn () => throw new LogicException('Une ligne de facture ne se supprime pas : ajoutez une correction.'));
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
