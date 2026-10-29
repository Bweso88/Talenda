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
     * Permissions d'un tenant (§12). Chaque module ajoute les siennes
     * lorsqu'il est développé.
     */
    'permissions' => [
        'tenant.settings.manage',
        'membership.view',
        'membership.manage',
        'role.manage',
        'audit.view',
        // Structure scolaire (étape 1).
        'school_year.manage',
        'structure.view',
        'structure.manage',
        'staff.view',
        'staff.manage',
        // Inscriptions (étape 2).
        'student.view',
        'student.manage',
        'enrollment.manage',
        'document.issue',
        // Finances (étape 3).
        'finance.view',
        'finance.manage',
        'payment.record',
        'payment.reverse',
        // Évaluations et bulletins (étape 4).
        'assessment.enter',
        'assessment.manage',
        'term.manage',
        'term.reopen',
        'report_card.view',
    ],

    /*
     * Rôles fournis par défaut (§12). '*' = toutes les permissions.
     * Ce sont des rôles globaux (sans tenant) : l'attribution d'un rôle à
     * un utilisateur, elle, est toujours faite dans le contexte d'une école
     * (spatie/laravel-permission, teams = tenant_id).
     */
    'roles' => [
        'school_admin' => '*',
        'director' => [
            'membership.view', 'audit.view',
            'school_year.manage', 'structure.view', 'structure.manage',
            'staff.view', 'staff.manage',
            'student.view', 'student.manage', 'enrollment.manage', 'document.issue',
            'finance.view', 'finance.manage', 'payment.reverse',
            'assessment.enter', 'assessment.manage', 'term.manage', 'term.reopen', 'report_card.view',
        ],
        'academic_director' => [
            'membership.view',
            'school_year.manage', 'structure.view', 'structure.manage',
            'staff.view',
            'student.view', 'student.manage', 'enrollment.manage', 'document.issue',
            'assessment.enter', 'assessment.manage', 'term.manage', 'report_card.view',
        ],
        'secretary' => [
            'membership.view', 'structure.view', 'staff.view',
            'student.view', 'student.manage', 'enrollment.manage', 'document.issue',
            'report_card.view',
        ],
        'accountant' => [
            'structure.view', 'student.view',
            'finance.view', 'finance.manage', 'payment.record', 'payment.reverse',
        ],
        'cashier' => ['structure.view', 'student.view', 'finance.view', 'payment.record'],
        // Saisie limitée à ses propres matières (voir AssessmentPolicy).
        'teacher' => ['structure.view', 'assessment.enter'],
        'supervisor' => ['structure.view'],
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

    /*
     * Structure scolaire proposée à la création d'une école, par pays
     * (§1, §8.2). L'école choisit ses cycles, puis ajuste librement
     * niveaux, séries et matières.
     *
     * - school_year : mois de début et de fin de l'année scolaire ;
     * - terms : découpage de l'année (dates proposées, modifiables) ;
     * - levels : dans l'ordre de scolarité ; `exam` = examen national passé
     *   en fin de niveau.
     */
    'academic_presets' => [
        'CG' => [
            'school_year' => ['start_month' => 10, 'end_month' => 7],
            'terms' => [
                ['name' => 'Premier trimestre', 'start' => '10-01', 'end' => '12-20'],
                ['name' => 'Deuxième trimestre', 'start' => '01-05', 'end' => '03-31'],
                ['name' => 'Troisième trimestre', 'start' => '04-10', 'end' => '07-15'],
            ],
            'cycles' => [
                'PRESCOLAIRE' => [
                    'name' => 'Préscolaire',
                    'levels' => [
                        ['code' => 'PS', 'name' => 'Petite section'],
                        ['code' => 'MS', 'name' => 'Moyenne section'],
                        ['code' => 'GS', 'name' => 'Grande section'],
                    ],
                ],
                'PRIMAIRE' => [
                    'name' => 'Primaire',
                    'levels' => [
                        ['code' => 'CP1', 'name' => 'CP1'],
                        ['code' => 'CP2', 'name' => 'CP2'],
                        ['code' => 'CE1', 'name' => 'CE1'],
                        ['code' => 'CE2', 'name' => 'CE2'],
                        ['code' => 'CM1', 'name' => 'CM1'],
                        ['code' => 'CM2', 'name' => 'CM2', 'exam' => 'CEPE'],
                    ],
                ],
                'COLLEGE' => [
                    'name' => 'Collège',
                    'levels' => [
                        ['code' => '6E', 'name' => '6e'],
                        ['code' => '5E', 'name' => '5e'],
                        ['code' => '4E', 'name' => '4e'],
                        ['code' => '3E', 'name' => '3e', 'exam' => 'BEPC'],
                    ],
                ],
                'LYCEE' => [
                    'name' => 'Lycée',
                    'levels' => [
                        ['code' => '2ND', 'name' => 'Seconde'],
                        ['code' => '1RE', 'name' => 'Première'],
                        ['code' => 'TLE', 'name' => 'Terminale', 'exam' => 'Baccalauréat'],
                    ],
                ],
            ],
            'streams' => [
                'A' => 'Série A (littéraire)',
                'C' => 'Série C (mathématiques et sciences physiques)',
                'D' => 'Série D (mathématiques et sciences naturelles)',
            ],
            // Pièces demandées pour le dossier d'inscription.
            'enrollment_documents' => [
                'birth_certificate' => 'Acte de naissance',
                'photos' => 'Photos d\'identité',
                'previous_report_card' => 'Bulletin de l\'année précédente',
                'leaving_certificate' => 'Certificat de radiation (élève venant d\'une autre école)',
                'exam_transcript' => 'Relevé de notes de l\'examen (CEPE, BEPC)',
            ],
            // Types de frais proposés. billing : upfront = dû à l'inscription,
            // spread = réparti selon le plan de paiement de la famille.
            // applies_to : all, new (nouveaux élèves), re_enrollment.
            // optional : ajouté à la demande (tenue, transport…).
            'fee_types' => [
                ['code' => 'INSCRIPTION', 'name' => 'Frais d\'inscription', 'billing' => 'upfront', 'applies_to' => 'new', 'optional' => false],
                ['code' => 'REINSCRIPTION', 'name' => 'Frais de réinscription', 'billing' => 'upfront', 'applies_to' => 're_enrollment', 'optional' => false],
                ['code' => 'SCOLARITE', 'name' => 'Scolarité', 'billing' => 'spread', 'applies_to' => 'all', 'optional' => false],
                ['code' => 'TENUE', 'name' => 'Tenue scolaire', 'billing' => 'upfront', 'applies_to' => 'all', 'optional' => true],
                ['code' => 'EXAMEN', 'name' => 'Frais d\'examen', 'billing' => 'upfront', 'applies_to' => 'all', 'optional' => true],
            ],
            // Plans de paiement proposés pour chaque année (modifiables).
            // Échéances : [libellé, mois, jour, pourcentage de la scolarité].
            'payment_plans' => [
                'Mensuel' => [
                    ['Octobre', 10, 10, 11.12], ['Novembre', 11, 10, 11.11], ['Décembre', 12, 10, 11.11],
                    ['Janvier', 1, 10, 11.11], ['Février', 2, 10, 11.11], ['Mars', 3, 10, 11.11],
                    ['Avril', 4, 10, 11.11], ['Mai', 5, 10, 11.11], ['Juin', 6, 10, 11.11],
                ],
                'Trimestriel' => [
                    ['1re tranche', 10, 10, 33.34], ['2e tranche', 1, 10, 33.33], ['3e tranche', 4, 10, 33.33],
                ],
                'Annuel' => [
                    ['Paiement unique', 10, 10, 100],
                ],
            ],
            'default_payment_plan' => 'Mensuel',
            // Notation (§9). Moyenne d'une matière = moyenne pondérée des
            // moyennes de chaque type d'évaluation. À valider avec l'école
            // pilote (poids de la composition notamment).
            'grading' => [
                'max_score' => 20,
                'passing_average' => 10,
                'absence_policy' => 'ignore',
                'assessment_types' => [
                    ['code' => 'DEVOIR', 'name' => 'Devoirs et interrogations', 'weight' => 1],
                    ['code' => 'COMPOSITION', 'name' => 'Composition', 'weight' => 1],
                ],
                // Du plus haut au plus bas seuil.
                'distinctions' => [
                    ['name' => 'Félicitations', 'min' => 16],
                    ['name' => 'Encouragements', 'min' => 14],
                    ['name' => 'Tableau d\'honneur', 'min' => 12],
                ],
            ],
            // Matières proposées, par cycle concerné.
            'subjects' => [
                ['code' => 'FR', 'name' => 'Français', 'cycles' => ['PRIMAIRE', 'COLLEGE', 'LYCEE']],
                ['code' => 'MATH', 'name' => 'Mathématiques', 'cycles' => ['PRIMAIRE', 'COLLEGE', 'LYCEE']],
                ['code' => 'ANG', 'name' => 'Anglais', 'cycles' => ['COLLEGE', 'LYCEE']],
                ['code' => 'HG', 'name' => 'Histoire-Géographie', 'cycles' => ['PRIMAIRE', 'COLLEGE', 'LYCEE']],
                ['code' => 'SVT', 'name' => 'Sciences de la vie et de la Terre', 'cycles' => ['COLLEGE', 'LYCEE']],
                ['code' => 'PC', 'name' => 'Physique-Chimie', 'cycles' => ['COLLEGE', 'LYCEE']],
                ['code' => 'ECM', 'name' => 'Éducation civique et morale', 'cycles' => ['PRIMAIRE', 'COLLEGE', 'LYCEE']],
                ['code' => 'EPS', 'name' => 'Éducation physique et sportive', 'cycles' => ['PRIMAIRE', 'COLLEGE', 'LYCEE']],
                ['code' => 'SCI', 'name' => 'Sciences d\'observation', 'cycles' => ['PRIMAIRE']],
                ['code' => 'PHILO', 'name' => 'Philosophie', 'cycles' => ['LYCEE']],
                ['code' => 'ESP', 'name' => 'Espagnol', 'cycles' => ['COLLEGE', 'LYCEE']],
                ['code' => 'INFO', 'name' => 'Informatique', 'cycles' => ['COLLEGE', 'LYCEE']],
                ['code' => 'EVEIL', 'name' => 'Activités d\'éveil', 'cycles' => ['PRESCOLAIRE']],
            ],
        ],
    ],

];
