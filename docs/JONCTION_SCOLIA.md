# Jonction Talenda ↔ Scolia

Décision (2026-11-06) : **Talenda est la référence** pour l'école — élèves,
classes, matières, enseignants, responsables, et plus tard absences et
notes. **Scolia** garde ce qu'il fait de mieux : messagerie
parents-enseignants, cahier de texte et devoirs, emploi du temps.

## Principe

```
Talenda (référence)                          Scolia (cahier de liaison)
──────────────────                           ──────────────────────────
Applications connectées → clé « tlnd_… »  →  réglages de l'école (URL + clé)
GET  /api/integration/v1/snapshot         ←  synchronisation toutes les 15 min
                                              (et bouton « Synchroniser »)
POST /api/integration/v1/credentials/check ← connexion d'un parent ou d'un
                                              enseignant relié à Talenda
```

1. **Connexion des deux écoles** : dans Talenda, Paramètres → Applications
   connectées → « Connecter une application » → Scolia. La clé (affichée
   une seule fois) et l'adresse de l'API se saisissent dans Scolia.
2. **Synchronisation** : Scolia lit la photographie de l'année ouverte et
   rapproche par les `uuid` Talenda (colonne `talenda_uuid` dans Scolia) :
   - crée ou met à jour l'année, les matières, les classes, les élèves,
     les comptes parents et enseignants, les liens parent-enfant et les
     affectations enseignant-classe-matière ;
   - désactive ce qui a disparu de Talenda (élève parti, lien retiré) au
     lieu de le supprimer : l'historique de messagerie est conservé.
3. **Un seul mot de passe** : un compte Scolia relié à Talenda n'a pas de
   mot de passe propre. Scolia vérifie les identifiants (téléphone ou
   e-mail) auprès de Talenda. Le parent utilise partout le mot de passe
   de l'app Talenda.
4. **Lecture seule dans Scolia** : un élève, une classe, une matière ou un
   compte venu de Talenda ne se modifie pas dans Scolia (« Géré dans
   Talenda »).

## Contenu de la photographie (`GET /snapshot`)

`school`, `school_year`, `subjects[]`, `classes[]` (avec professeur
principal), `staff[]`, `teaching[]` (classe × matière × enseignant),
`students[]` (inscrits de l'année, avec leur classe), `guardians[]` (avec
leurs enfants, le lien de parenté et le contact principal). Format exact :
`docs/openapi.json`.

## Sécurité

- Une clé par école et par application ; seule son empreinte (SHA-256)
  est stockée. Révocable à tout moment depuis Talenda.
- La clé ne donne accès qu'à l'école qui l'a créée.
- Vérification d'identifiants limitée à 5 essais par minute et par
  identifiant ; seuls les comptes membres de l'école sont acceptés.

## Étapes suivantes (non faites)

- Talenda prévient Scolia d'un changement (webhook) au lieu d'attendre la
  synchronisation suivante.
- Absences et annonces : Talenda référence ; Scolia les affiche au lieu de
  les gérer en double.
- À terme, une seule app parents.
