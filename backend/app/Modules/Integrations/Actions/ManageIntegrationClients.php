<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Actions;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Exceptions\BusinessRuleViolation;
use App\Modules\Integrations\Models\IntegrationClient;
use Illuminate\Support\Str;

/**
 * Création et révocation des clés d'intégration d'une école.
 */
final class ManageIntegrationClients
{
    public function __construct(private readonly RecordAudit $recordAudit) {}

    /**
     * @return array{0: IntegrationClient, 1: string} la clé en clair, affichée une seule fois
     */
    public function create(string $name, User $actor): array
    {
        $name = trim($name);

        if (IntegrationClient::query()->where('name', $name)->whereNull('revoked_at')->exists()) {
            throw new BusinessRuleViolation("{$name} est déjà connecté : révoquez l'ancienne clé avant d'en créer une nouvelle.");
        }

        $token = 'tlnd_'.Str::random(40);

        $client = IntegrationClient::create([
            'name' => $name,
            'token_hash' => IntegrationClient::hash($token),
            'token_prefix' => substr($token, 0, 10),
            'created_by' => $actor->id,
        ]);

        $this->recordAudit->handle('integration.client_created', actor: $actor, subject: $client, metadata: ['name' => $name]);

        return [$client, $token];
    }

    public function revoke(IntegrationClient $client, User $actor): void
    {
        $client->update(['revoked_at' => now()]);

        $this->recordAudit->handle('integration.client_revoked', actor: $actor, subject: $client, metadata: ['name' => $client->name]);
    }
}
