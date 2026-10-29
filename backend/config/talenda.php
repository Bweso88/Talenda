<?php

/**
 * Catalogues de Talenda (docs/PRODUCT_ARCHITECTURE.md).
 *
 * Centralisés ici pour que l'application, les seeders et les tests restent
 * en phase : aucun rôle, module ou paramètre de pays n'est codé en dur
 * ailleurs.
 */
return [

    /*
     * Modules activables par école (§4). Le socle est toujours actif et
     * n'apparaît donc pas ici.
     */
    'modules' => [
        'enrollments',
        'finances',
        'assessments',
        'school_life',
        'communication',
        'parent_portal',
        'staff',
        'payroll',
        'exams',
        'services',
        'analytics',
    ],

    /*
     * Permissions d'un tenant (§12). Seul le socle existe à l'étape 0 ;
     * chaque module ajoute les siennes lorsqu'il est développé.
     */
    'permissions' => [
        'tenant.settings.manage',
        'membership.view',
        'membership.manage',
        'role.manage',
        'audit.view',
    ],

    /*
     * Rôles fournis par défaut (§12). '*' = toutes les permissions.
     * Ce sont des rôles globaux (sans tenant) : l'attribution d'un rôle à
     * un utilisateur, elle, est toujours faite dans le contexte d'une école
     * (spatie/laravel-permission, teams = tenant_id).
     */
    'roles' => [
        'school_admin' => '*',
        'director' => ['membership.view', 'audit.view'],
        'academic_director' => ['membership.view'],
        'secretary' => ['membership.view'],
        'accountant' => [],
        'cashier' => [],
        'teacher' => [],
        'supervisor' => [],
        'parent' => [],
    ],

    /*
     * Gabarits de pays (§1) : valeurs par défaut appliquées à la création
     * d'une école, modifiables ensuite par l'école.
     */
    'default_country' => 'CG',

    'countries' => [
        'CG' => [
            'name' => 'République du Congo',
            'currency' => 'XAF',
            'timezone' => 'Africa/Brazzaville',
            'locale' => 'fr',
            'dial_code' => '242',
            // Numéros nationaux à 9 chiffres, le 0 initial est conservé
            // dans le format international (+242 06 123 45 67).
            'national_number_length' => 9,
        ],
    ],

];
