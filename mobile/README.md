# Talenda — app des parents (Flutter)

L'app des parents : scolarité (reste à payer, échéancier, reçus), bulletins
publiés et absences de leurs enfants, dépôt d'un justificatif d'absence.
Architecture : [`../docs/PRODUCT_ARCHITECTURE.md`](../docs/PRODUCT_ARCHITECTURE.md) §10.

Elle utilise l'API v1 du backend. Son contrat est `../docs/openapi.json`
(généré par le backend, vérifié par ses tests).

## Lancer l'app

Prérequis : Flutter 3.47 (stable).

```bash
flutter pub get
flutter run --dart-define=API_URL=http://10.0.2.2:8000/api/v1   # émulateur Android, backend local (php artisan serve)
```

Pour le web (navigateur) : `flutter run -d chrome --dart-define=API_URL=http://127.0.0.1:8000/api/v1`.

Compte de démonstration : `06 333 33 33` / `talenda-demo` (mère de deux
élèves du Lycée Démo de Brazzaville, parent aussi au Collège de Pointe-Noire).

## Construire

```bash
flutter build apk --release --dart-define=API_URL=https://<domaine>/api/v1
```

En production, l'API doit être servie en HTTPS (Android refuse le HTTP
simple en version de production).

## Vérifications

Les mêmes que l'intégration continue (`.github/workflows/mobile.yml`) :

```bash
dart format --line-length 160 --set-exit-if-changed lib test
flutter analyze
flutter test
```

Les tests lisent de vraies réponses de l'API (`test/fixtures`) : si le
contrat change côté backend, ils le signalent.

## Organisation

- `lib/core/` : thème (couleurs du logo), client API (jeton, en-tête
  `X-Tenant`, erreurs en français), session, cache hors ligne, formats
  (FCFA, dates).
- `lib/features/` : connexion et mot de passe, choix de l'école, enfants
  (accueil, fiche), scolarité, bulletins, absences, aperçu des PDF.

Lecture hors ligne : chaque écran garde sa dernière réponse ; sans réseau,
l'app l'affiche avec un bandeau « Hors ligne — données du … ».
