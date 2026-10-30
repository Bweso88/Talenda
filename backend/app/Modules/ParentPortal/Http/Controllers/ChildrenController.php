<?php

declare(strict_types=1);

namespace App\Modules\ParentPortal\Http\Controllers;

use App\Models\User;
use App\Modules\ParentPortal\Http\Resources\ChildResource;
use App\Modules\ParentPortal\Support\GuardianChildren;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Enfants du parent dans l'école choisie (accueil de l'app).
 */
final class ChildrenController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ChildResource::collection(GuardianChildren::of($user)->orderBy('first_names')->get());
    }

    public function show(Request $request, string $child): ChildResource
    {
        /** @var User $user */
        $user = $request->user();

        return new ChildResource(GuardianChildren::findOrFail($user, $child));
    }
}
