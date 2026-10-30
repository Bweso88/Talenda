# Mise en ligne de démonstration sur Laravel Cloud

Objectif : une version de démonstration de Talenda en ligne, pour faire
tester le back-office et l'app parents par une école pilote. Même démarche
que pour Yosefa (`ged-pme`).

Durée : environ 20 minutes. Tout se fait depuis le tableau de bord
[cloud.laravel.com](https://cloud.laravel.com).

> Prérequis : la pull request vers `main` est fusionnée (Laravel Cloud
> déploie la branche `main`).

## 1. Créer l'application

1. **New application** → dépôt GitHub `Bweso88/Talenda`, branche `main`.
2. **Région** : Europe (Francfort, `eu-central`) — la plus proche de
   Brazzaville parmi celles proposées.
3. **Root directory** (dossier de l'application) : `backend`
   (le dépôt contient aussi `mobile/` et `docs/`).
4. **PHP** : 8.4.

## 2. Ajouter la base de données

Onglet **Resources** de l'environnement → **Add database** → **Laravel
Serverless Postgres** (PostgreSQL 16 ou plus récent ; Talenda a besoin de
PostgreSQL 15 au minimum). Les variables `DB_*` sont injectées
automatiquement.

## 3. Commandes de build et de déploiement

Onglet **Settings → Deployments** :

- **Build command**
  ```bash
  composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
  php artisan optimize
  ```
  (aucune étape `npm` : le thème des back-offices est en CSS simple.)
- **Deploy command**
  ```bash
  php artisan migrate --force && php artisan talenda:sync-permissions
  ```

## 4. Variables d'environnement

Onglet **Settings → Environment variables** (les variables `DB_*` et
`APP_KEY` sont déjà fournies par Laravel Cloud) :

```dotenv
APP_NAME=Talenda
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
SMS_DRIVER=log
SMS_SENDER=TALENDA
PUSH_DRIVER=log
```

`APP_URL` : à renseigner avec l'adresse `https://…laravel.cloud` donnée
après le premier déploiement, puis redéployer.

`SMS_DRIVER=log` : en démonstration, aucun SMS n'est réellement envoyé ;
ils apparaissent dans le Journal SMS du back-office.

## 5. File d'attente (SMS, notifications de bulletins)

Onglet **Resources** de l'environnement → **App cluster** → **Background
processes** → ajouter :

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Sans ce processus, les SMS restent « En attente » dans le journal.

## 6. Premier déploiement

**Deploy**. À la fin, Laravel Cloud donne l'adresse du site
(`https://talenda-…laravel.cloud`).

Puis, une seule fois, onglet **Commands** de l'environnement :

```bash
php artisan db:seed --force
```

Cela crée les deux écoles de démonstration et les comptes ci-dessous
(mot de passe `talenda-demo`). **À ne pas faire sur la future base de
production.**

| Compte | Identifiant | Accès |
|---|---|---|
| Super Admin | `admin@talenda.test` | `/platform` |
| Directeur | `06 111 11 11` | `/ecole` |
| Caissier | `06 444 44 10` | `/ecole` (caisse) |
| Enseignant | `06 444 44 02` | `/ecole` (ses classes) |
| Surveillant | `06 444 44 20` | `/ecole` (vie scolaire) |
| Parent | `06 333 33 33` | app parents |

Changez ces mots de passe avant de donner l'adresse à une école.

## 7. Vérifier

1. `https://<adresse>/` mène à la connexion du back-office.
2. Connexion directeur → tableau de bord, élèves, caisse, appel.
3. Annonce aux familles avec « Envoyer aussi par SMS » → le Journal SMS
   montre les SMS « Envoyé » (preuve que la file d'attente tourne).

## 8. App parents sur un téléphone Android

Sur un ordinateur où Flutter est installé (voir `mobile/README.md`) :

```bash
cd mobile
flutter build apk --release --dart-define=API_URL=https://<adresse>/api/v1
```

Le fichier `build/app/outputs/flutter-apk/app-release.apk` s'installe sur
un téléphone Android (autoriser les « sources inconnues »), ou se
distribue par WhatsApp à l'école pilote.

## À prévoir avant la vraie production

- Choisir l'offre (Laravel Cloud payant ou VPS avec Forge) selon le coût.
- Stockage objet (bucket) pour les logos et photos d'élèves : le disque du
  serveur Laravel Cloud est effacé à chaque déploiement.
- Fournisseur SMS réel (MTN, Airtel ou agrégateur) et projet Firebase
  (notifications push).
- Nom de domaine (ex. `app.talenda.cg`) et sauvegardes de la base.
- Ne plus lancer `db:seed` : les écoles sont créées depuis `/platform`.
