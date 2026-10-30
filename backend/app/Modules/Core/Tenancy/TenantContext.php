<?php

declare(strict_types=1);

namespace App\Modules\Core\Tenancy;

use App\Modules\Core\Models\Tenant;
use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * École active pour la requête, la commande ou le job en cours.
 *
 * Renseignée uniquement par le code de confiance : les middlewares
 * ResolveApiTenant et SyncFilamentTenant (après vérification de
 * l'appartenance de l'utilisateur), ou explicitement via run() dans une
 * commande, un job ou un seeder.
 *
 * Isolation « fermée par défaut » (docs/PRODUCT_ARCHITECTURE.md §7) : sans
 * école active et sans contournement explicite, les modèles d'école ne
 * renvoient aucune ligne. Scolia faisait l'inverse (aucun filtre si aucun
 * tenant), ce qui transformait un oubli en fuite de données.
 *
 * Enregistrée en `scoped` : réinitialisée à chaque requête, y compris
 * sous Octane.
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    private int $bypassDepth = 0;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant?->getKey());
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Vrai uniquement à l'intérieur de withoutTenant() : les requêtes
     * portent alors sur toutes les écoles.
     */
    public function isBypassed(): bool
    {
        return $this->bypassDepth > 0;
    }

    /**
     * Exécute $callback dans le contexte de l'école donnée, puis restaure
     * le contexte précédent.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->set($tenant);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    /**
     * Exécute $callback sans filtre d'école. Réservé au back-office
     * plateforme et aux traitements techniques transverses : tout nouvel
     * appel doit être justifié en revue de code.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutTenant(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }
}
