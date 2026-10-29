<?php

declare(strict_types=1);

namespace App\Modules\Core\Contracts;

/**
 * Élément de structure qui ne peut être supprimé tant qu'il est utilisé
 * (un niveau qui a des classes, une matière enseignée…). On évite ainsi
 * une erreur de clé étrangère au moment de la suppression.
 */
interface TracksUsage
{
    public function isInUse(): bool;
}
