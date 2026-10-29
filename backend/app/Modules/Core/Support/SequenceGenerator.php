<?php

declare(strict_types=1);

namespace App\Modules\Core\Support;

use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Numéros séquentiels par école et par compteur (matricules, reçus,
 * factures) : jamais de doublon ni de trou, même si deux secrétaires
 * enregistrent en même temps. La ligne du compteur est verrouillée jusqu'à
 * la fin de la transaction en cours ; si elle échoue, le numéro n'est pas
 * consommé.
 */
final class SequenceGenerator
{
    public function __construct(private readonly TenantContext $tenantContext) {}

    public function next(string $name): int
    {
        $tenantId = $this->tenantContext->id() ?? throw new LogicException('Aucune école active.');

        return DB::transaction(function () use ($tenantId, $name): int {
            DB::table('tenant_sequences')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'name' => $name,
                'last_value' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $current = (int) DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->lockForUpdate()
                ->value('last_value');

            DB::table('tenant_sequences')
                ->where('tenant_id', $tenantId)
                ->where('name', $name)
                ->update(['last_value' => $current + 1, 'updated_at' => now()]);

            return $current + 1;
        });
    }
}
