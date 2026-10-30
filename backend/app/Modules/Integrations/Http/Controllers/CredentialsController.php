<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers;

use App\Models\User;
use App\Modules\Core\Actions\RecordAudit;
use App\Modules\Core\Auth\LoginIdentifier;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Vérification d'identifiants pour l'application partenaire : le parent ou
 * l'enseignant se connecte à Scolia avec son compte Talenda (téléphone ou
 * e-mail, même mot de passe). Seuls les comptes membres de l'école de la
 * clé sont acceptés.
 */
final class CredentialsController
{
    public function __invoke(Request $request, TenantContext $tenantContext, RecordAudit $recordAudit): JsonResponse
    {
        $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $credentials = LoginIdentifier::toCredentials($request->string('login')->toString());
        $user = $credentials === null ? null : User::query()->where($credentials)->first();
        $tenant = $tenantContext->get();

        if ($user === null || $tenant === null || ! Hash::check($request->string('password')->toString(), $user->password) || $user->membershipFor($tenant) === null) {
            throw ValidationException::withMessages(['login' => 'Identifiants incorrects.']);
        }

        $recordAudit->handle('integration.credentials_checked', actor: $user, metadata: [
            'client' => $request->attributes->get('integration_client')?->name,
        ]);

        return response()->json([
            'account_uuid' => $user->uuid,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'must_change_password' => $user->must_change_password,
            'roles' => $user->roles()->pluck('name')->sort()->values(),
        ]);
    }
}
