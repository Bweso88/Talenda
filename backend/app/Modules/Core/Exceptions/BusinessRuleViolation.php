<?php

declare(strict_types=1);

namespace App\Modules\Core\Exceptions;

use RuntimeException;

/**
 * Règle métier non respectée (ex. ouvrir une deuxième année scolaire).
 * Le message, en français, est destiné à l'utilisateur : le back-office et
 * l'API l'affichent tel quel.
 */
final class BusinessRuleViolation extends RuntimeException {}
