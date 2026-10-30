<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Core\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Réglages de communication d'une école (SMS automatiques).
 *
 * @property int $id
 * @property string|null $sms_sender
 * @property bool $sms_on_absence
 * @property bool $sms_on_payment
 * @property bool $sms_on_report_card
 */
#[Fillable(['sms_sender', 'sms_on_absence', 'sms_on_payment', 'sms_on_report_card'])]
class CommunicationSetting extends Model
{
    use BelongsToTenant;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sms_on_absence' => false,
        'sms_on_payment' => false,
        'sms_on_report_card' => false,
    ];

    protected function casts(): array
    {
        return [
            'sms_on_absence' => 'boolean',
            'sms_on_payment' => 'boolean',
            'sms_on_report_card' => 'boolean',
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
