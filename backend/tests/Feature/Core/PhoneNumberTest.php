<?php

use App\Modules\Core\Auth\LoginIdentifier;
use App\Modules\Core\Support\PhoneNumber;

it('normalise les numéros congolais au format E.164', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('+242061234567');
})->with([
    'national' => '061234567',
    'national avec espaces' => '06 123 45 67',
    'national avec points' => '06.123.45.67',
    'international' => '+242 06 123 45 67',
    'international avec 00' => '00242061234567',
]);

it('rejette les numéros invalides', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    'trop court' => '0612345',
    'trop long' => '0612345678',
    'autre pays' => '+243812345678',
    'lettres' => '06 ABC 45 67',
    'vide' => '',
]);

it('rejette un pays sans gabarit', function () {
    expect(PhoneNumber::normalize('061234567', 'ZZ'))->toBeNull();
});

it('traduit l\'identifiant de connexion en e-mail ou téléphone', function () {
    expect(LoginIdentifier::toCredentials(' Directeur@Ecole.CG '))->toBe(['email' => 'directeur@ecole.cg'])
        ->and(LoginIdentifier::toCredentials('06 123 45 67'))->toBe(['phone' => '+242061234567'])
        ->and(LoginIdentifier::toCredentials('n\'importe quoi'))->toBeNull();
});
