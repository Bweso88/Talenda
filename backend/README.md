# Talenda — backend

Application Laravel 13 (API mobile + back-offices Filament) de Talenda.
Architecture de référence : [`../docs/PRODUCT_ARCHITECTURE.md`](../docs/PRODUCT_ARCHITECTURE.md).

## Installation en local

Prérequis : PHP 8.3+ (8.4 recommandé), Composer, PostgreSQL 15+ (16 recommandé).

```bash
composer install
cp .env.example .env          # puis renseigner DB_PASSWORD
php artisan key:generate
createdb talenda && createdb talenda_test
php artisan migrate --seed    # écoles et comptes de démonstration
php artisan storage:link      # logos des écoles
php artisan serve
```

| Adresse | Usage |
|---|---|
| `/ecole` | Back-office des écoles (connexion par e-mail **ou** téléphone) |
| `/platform` | Back-office plateforme (Super Admins Talenda) |
| `/api/v1` | API de l'app mobile (jetons Sanctum, en-tête `X-Tenant`) |

Comptes de démonstration (mot de passe : `talenda-demo`) :

| Compte | Identifiant | Accès |
|---|---|---|
| Super Admin | `admin@talenda.test` | `/platform` |
| Directeur | `06 111 11 11` | Lycée Démo de Brazzaville |
| Directrice | `05 222 22 22` | Collège Démo de Pointe-Noire |
| Parent | `06 333 33 33` | Les deux écoles (un seul compte) |

Le Lycée Démo de Brazzaville contient une structure complète (collège et
lycée, 3 classes avec coefficients, 3 enseignants) et 7 élèves inscrits,
dont une fratrie.

## Vérifications

Les mêmes que l'intégration continue (`.github/workflows/backend.yml`) :

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/pest
```

Les tests utilisent la base PostgreSQL `talenda_test`.

## Organisation du code

- `app/Modules/<Module>/` : code métier par module (modèles, actions,
  politiques, contrôleurs API, middlewares).
  - `Core` : écoles, comptes, isolation des écoles, audit ;
  - `Academic` : années scolaires, périodes, cycles, niveaux, séries,
    classes, matières et coefficients, personnel ;
  - `Enrollments` : élèves, responsables, inscriptions et réinscriptions,
    import Excel, certificats de scolarité et cartes scolaires (PDF).
- `app/Filament/School/` et `app/Filament/Platform/` : écrans des deux
  back-offices.
- `config/talenda.php` : catalogues des modules, rôles, permissions et
  gabarits de pays.

Règle d'or de l'isolation des écoles : tout modèle métier d'une école
utilise le trait `BelongsToTenant`. Sans école active, ses requêtes ne
renvoient rien (voir `app/Modules/Core/Tenancy/TenantContext.php`).

## Déploiement de développement (Laravel Cloud)

Comme Yosefa (`ged-pme`), l'environnement de développement est hébergé sur
Laravel Cloud, avec sa base PostgreSQL :

1. Créer une application à partir du dépôt GitHub `Bweso88/Talenda`, avec
   `backend` comme dossier racine de l'application.
2. Attacher une base PostgreSQL.
3. Commande de déploiement :
   `php artisan migrate --force && php artisan talenda:sync-permissions`
4. Pour une base de démonstration, lancer une fois `php artisan db:seed --force`.
5. Fichiers : le disque local de Laravel Cloud n'est pas permanent.
   Attacher un stockage objet (bucket) avant de téléverser de vrais logos
   (disque `public`) et photos d'élèves (disque `local`, privé).

Le choix de l'hébergement de production (Laravel Cloud ou VPS avec Forge)
sera fait au moment de la mise en production.
