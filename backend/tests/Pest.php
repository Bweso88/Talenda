<?php

use App\Modules\Core\Actions\SyncRolesAndPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Les tests utilisent PostgreSQL (base talenda_test) : le schéma repose sur
 * des fonctionnalités propres à PostgreSQL (contraintes CHECK, jsonb).
 */
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => app(SyncRolesAndPermissions::class)->handle())
    ->in('Feature');
