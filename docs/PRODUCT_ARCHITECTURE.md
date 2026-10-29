# Talenda — Système de gestion scolaire (School Management System)

Document d'architecture produit et technique, rédigé **avant tout code**.
C'est la référence pendant toute l'implémentation : toute décision qui s'en
écarte doit être justifiée et reportée ici (section « Journal des décisions »
en fin de document).

Talenda repart de zéro. Scolia (cahier de liaison numérique) sert de
**référence fonctionnelle** : ses idées validées sont reprises (multi-tenant,
marque blanche, modules activables, règles de messagerie), ses limites
identifiées sont corrigées dès la conception (voir §2).

---

## 1. Vision et positionnement

Talenda est une plateforme **SaaS multi-écoles** qui gère **toute la vie
administrative, pédagogique et financière** d'un établissement scolaire,
de l'inscription de l'élève jusqu'à son bulletin de fin d'année, en passant
par les frais de scolarité, le personnel et la communication avec les familles.

| | Scolia (cahier de liaison) | Talenda (gestion scolaire complète) |
|---|---|---|
| Cœur du produit | Communication école ↔ parents | Gestion de l'établissement |
| Utilisateur principal | Parent (mobile) | Secrétariat, comptabilité, direction (web) |
| Argument d'achat | « Votre propre app de communication » | « Toute votre école dans un seul outil, et vos impayés sous contrôle » |
| Données centrales | Devoirs, absences, messages | Inscriptions, notes, paiements |

Promesse à l'école : *« Inscriptions, scolarité, notes, bulletins et
personnel dans un seul outil, accessible partout, sans installation. »*

Promesse au parent : *« Le solde de scolarité, les notes, les absences et les
annonces de vos enfants dans votre téléphone. »*

Contexte cible : écoles privées (maternelle → lycée) d'Afrique francophone,
de 100 à 3 000 élèves. Conséquences directes sur la conception :
- connexion internet parfois lente ou instable → pages légères, impressions
  PDF fiables, tolérance aux saisies interrompues ;
- paiements majoritairement en **espèces et mobile money** → la caisse est
  un poste de travail central, pas un écran secondaire ;
- documents papier toujours attendus (reçus, bulletins, cartes scolaires,
  certificats) → génération PDF de qualité dès le MVP ;
- systèmes éducatifs variés (trimestres ou semestres, notes sur 20 ou sur 10,
  coefficients, rangs) → **tout ce qui est réglementaire est paramétrable**,
  rien n'est codé en dur.

### Marché de lancement : République du Congo (Brazzaville)

Les premières écoles clientes sont au Congo-Brazzaville. Le produit reste
multi-pays (tout est paramétrable par tenant), mais les **valeurs par défaut
d'une nouvelle école** et le paramétrage de démo sont ceux du Congo :

| Paramètre | Valeur par défaut |
|---|---|
| Devise | **XAF** (franc CFA BEAC), sans décimales : montants stockés en francs entiers |
| Fuseau horaire | `Africa/Brazzaville` (UTC+1) |
| Langue | Français |
| Téléphone | Indicatif **+242**, numéros à 9 chiffres (06 MTN, 05 / 04 Airtel), normalisés au format E.164 |
| Année scolaire | Octobre → juin / juillet, découpée en **3 trimestres** |
| Notation | Sur **20**, coefficients par matière, rang dans la classe, mentions |
| Cycles et niveaux | Préscolaire · Primaire (CP1, CP2, CE1, CE2, CM1, CM2) · Collège (6e → 3e) · Lycée (2nde → Terminale) |
| Séries du lycée | Générales A (littéraire), C (mathématiques-physique), D (mathématiques-sciences naturelles) ; séries techniques activables au besoin |
| Examens nationaux | CEPE (fin CM2), BEPC (fin 3e), Baccalauréat (Terminale) : suivis comme classes d'examen (frais d'examen, listes de candidats, résultats) |
| Mobile money | **MTN Mobile Money** et **Airtel Money**, les deux opérateurs du pays |
| Données personnelles | Loi congolaise sur la protection des données à caractère personnel (loi n° 29-2019), à faire valider par un juriste local avant la mise en production |

Ces valeurs sont un **gabarit de pays** (`country_presets` : `CG` au
lancement) appliqué à la création d'une école, puis modifiable par
l'école. Ajouter le Gabon, le Cameroun ou la RDC revient à ajouter un
gabarit, sans changer le code.

À vérifier sur le terrain auprès de l'école pilote avant l'étape 4 : le
modèle exact de bulletin utilisé (disposition, appréciations, signatures,
cachet), le calcul de la moyenne trimestrielle (poids des devoirs par rapport
à la composition) et celui de la moyenne annuelle (moyenne simple des 3
trimestres ou pondérée).

---

## 2. Ce que l'on reprend de Scolia, et ce que l'on corrige

**Repris tel quel (validé)**
- Base unique multi-tenant avec isolation par `tenant_id` + scope global +
  policies + tests d'isolation obligatoires.
- Marque blanche chargée au runtime (logo, couleurs) dans **une seule** app
  mobile, pas une app par école.
- Modules activables par école (`tenant_modules`), plans et quotas pilotés
  par la base, jamais codés en dur.
- Règles d'autorisation de la messagerie (qui peut écrire à qui, plages
  horaires).

**Corrigé dès la conception**
| Limite constatée dans Scolia | Choix Talenda |
|---|---|
| Un compte `User` **par école** pour un même parent, recollés par `parent_identities` | **Une identité globale** (`users`) + des **appartenances** par école (`memberships`). Un seul login, plusieurs écoles. |
| L'élève est rattaché directement à une classe | Séparation **élève** (identité permanente) / **inscription** (élève × année × classe). L'historique multi-années est natif. |
| Notes et bulletins ajoutés après coup (colonnes `type`, `homework_id` en migrations correctives) | Modèle d'évaluation complet dès le départ : évaluations → notes → moyennes figées à la publication du bulletin. |
| Notifications envoyées de façon synchrone (contrainte d'hébergement) | File d'attente dès le jour 1 ; l'hébergement est choisi pour la supporter. |
| Aucun module financier | **Module Finances au cœur du MVP**, conçu comme un registre comptable (aucune suppression, uniquement des écritures correctives). |
| Migrations corrigées a posteriori pour être idempotentes en production | Migrations testées en CI sur une base vierge **et** sur une copie de la base de démo avant tout déploiement. |

---

## 3. Personas

| Persona | Contexte | Besoins clés |
|---|---|---|
| **Secrétaire (Mme Ngoy)** | Fait les inscriptions, imprime les certificats, répond aux parents au guichet | Inscrire un élève en moins de 3 minutes, retrouver un dossier instantanément, imprimer sans mise en page manuelle |
| **Caissier / comptable (M. Kabeya)** | Encaisse les frais, en espèces et en mobile money, fait la clôture de caisse | Encaisser vite, reçu imprimé immédiatement, savoir qui doit quoi, clôture de caisse juste |
| **Enseignant (Mme Koffi)** | Plusieurs classes et matières, saisit notes et absences | Saisir les notes d'une évaluation pour toute la classe sur un seul écran, sur téléphone ou PC |
| **Directeur des études / censeur (M. Diallo)** | Pilote la pédagogie, valide les bulletins, gère la discipline | Voir l'avancement de la saisie des notes, verrouiller une période, publier les bulletins |
| **Promoteur / fondateur (client)** | Décide de l'achat, suit la santé financière | Tableau de bord recettes / impayés / effectifs, accès de partout, données à lui |
| **Parent (Mariam)** | Smartphone d'entrée de gamme, 2 enfants | Voir le solde à payer, les notes, les absences, les annonces ; payer par mobile money (V2) |
| **Élève (V2)** | Collège / lycée | Emploi du temps, notes, devoirs |
| **Super Admin plateforme (équipe Talenda)** | Gère toutes les écoles clientes | Créer une école en quelques minutes, superviser, facturer, faire du support sans accéder aux données sans trace |

---

## 4. Périmètre fonctionnel : les modules

Talenda est découpé en **modules**. Le **socle** est toujours actif ; les
autres sont activables par école selon le plan souscrit.

| # | Module | Contenu | Version |
|---|---|---|---|
| 0 | **Socle** | Écoles (tenants), paramétrage, utilisateurs, rôles, années scolaires, périodes, niveaux, classes, matières, audit, notifications | MVP |
| 1 | **Inscriptions & dossiers élèves** | Préinscription, inscription, réinscription, dossier élève, responsables, pièces du dossier, transfert, départ, import Excel, carte scolaire, certificat de scolarité | MVP |
| 2 | **Finances / scolarité** | Grille tarifaire par niveau, échéancier, facturation à l'inscription, réductions et bourses, caisse (encaissement, reçu), relances, clôture de caisse, état des impayés | MVP |
| 3 | **Évaluations & bulletins** | Évaluations, saisie des notes, coefficients, moyennes, rangs, appréciations, conseil de classe, bulletin PDF, décisions de fin d'année (admis, redouble…) | MVP |
| 4 | **Vie scolaire** | Absences et retards (saisie, justification), discipline / sanctions, emploi du temps | MVP (absences) · V2 (reste) |
| 5 | **Communication** | Annonces ciblées, notifications push / SMS, messagerie avec règles d'autorisation, cahier de texte / devoirs | MVP (annonces + notifications) · V2 (messagerie, devoirs) |
| 6 | **Portail parents & élèves** | App mobile (Flutter) : solde, reçus, notes, bulletins, absences, annonces | MVP (parents, lecture seule) · V2 (élèves, paiement en ligne) |
| 7 | **Personnel / RH** | Dossiers du personnel, contrats, présences du personnel, congés | V2 |
| 8 | **Paie** | Bulletins de paie, avances, retenues | V3 |
| 9 | **Examens** | Sessions d'examen, salles, plan de placement, convocations, procès-verbaux | V2 |
| 10 | **Services annexes** | Cantine, transport scolaire, bibliothèque, inventaire / matériel | V3 (un sous-module à la fois, à la demande des clients) |
| 11 | **Pilotage** | Tableaux de bord direction (effectifs, recettes, impayés, résultats, absentéisme), exports | MVP (tableau de bord simple) · V2 (avancé) |
| 12 | **IA** | Résumés, détection des élèves en difficulté (validation humaine obligatoire), aide à la rédaction des appréciations | V3 |

**Règle de périmètre** : le MVP est ce qui permet à une école de **faire sa
rentrée entière** dans Talenda : inscrire, facturer, encaisser, noter,
imprimer les bulletins du premier trimestre. Tout le reste attend un retour
terrain.

---

## 5. MVP, V2, V3

### MVP — « une école fait sa rentrée dans Talenda »
- Socle complet (multi-tenant, rôles, paramétrage, audit).
- Inscriptions et réinscriptions, dossier élève, import Excel, certificat de
  scolarité et carte scolaire en PDF.
- Finances : grille tarifaire, échéancier, réductions, caisse avec reçu PDF,
  état des impayés, clôture de caisse journalière.
- Évaluations : saisie des notes, moyennes, rangs, bulletin PDF, verrouillage
  de période, décisions de fin d'année et passage à l'année suivante.
- Absences et retards (saisie par l'enseignant ou la vie scolaire).
- Annonces + notifications push et e-mail.
- App mobile parent en lecture seule : solde, reçus, notes, bulletins,
  absences, annonces.
- Tableau de bord direction simple.
- Back-office Super Admin : créer une école, plans, abonnements.

### V2
Paiement mobile money en ligne (via un agrégateur) · SMS / WhatsApp ·
messagerie · cahier de texte / devoirs · emploi du temps · discipline et
sanctions · personnel / RH · examens · portail élève · rôles personnalisés ·
statistiques avancées · export complet des données de l'école.

### V3
Paie · cantine · transport · bibliothèque · inventaire · IA · signature
électronique (autorisations parentales) · mode hors ligne partiel de l'app
enseignant.

**Risque signalé** : un système de gestion scolaire peut grossir sans fin.
Chaque module ajouté multiplie les tests d'isolation multi-tenant et de
droits. On ne lance la V2 qu'après **au moins une école réelle ayant
terminé un trimestre complet** sur le MVP.

---

## 6. Architecture technique

```
┌──────────────────────┐   ┌───────────────────────┐   ┌──────────────────────┐
│  Back-office école    │   │  Back-office plateforme│   │  App mobile unique    │
│  (Filament, web)      │   │  (Filament, web)       │   │  (Flutter)            │
│  Secrétariat, caisse, │   │  Super Admin Talenda   │   │  Parents (MVP)        │
│  direction, enseignants│  │                        │   │  Enseignants, élèves  │
└──────────┬────────────┘   └───────────┬────────────┘   └──────────┬───────────┘
           │ session web                │ session web (guard séparé) │ REST/JSON (Sanctum)
           ▼                            ▼                            ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                 Application Laravel — monolithe modulaire                    │
│  Middleware : auth ▸ résolution du tenant ▸ module actif ? ▸ policy          │
├─────────────────────────────────────────────────────────────────────────────┤
│  Modules : Socle · Inscriptions · Finances · Évaluations · VieScolaire ·      │
│            Communication · Pilotage · (RH · Examens · Services…)             │
├─────────────────────────────────────────────────────────────────────────────┤
│  Queue (Redis) : notifications, PDF en masse, imports, exports, relances     │
│  Scheduler : relances d'impayés, nettoyage tokens, sauvegardes               │
└───────────┬──────────────────────────┬───────────────────────┬───────────────┘
            ▼                          ▼                       ▼
      PostgreSQL               Stockage objets S3        Services externes
   (1 base, tenant_id)     (tenants/{id}/…, URLs signées)  FCM · e-mail · SMS ·
                                                           mobile money (V2)
```

### Choix structurants

- **Laravel 13 (PHP 8.4)** : capitalise sur l'expertise PHP existante, tout
  en adoptant les standards modernes (typage strict, enums natifs,
  `readonly`, Pest pour les tests, PHPStan niveau élevé, Pint pour le style).
  Écosystème complet pour un SaaS : Sanctum, Horizon, files d'attente,
  notifications, scheduler.
- **Monolithe modulaire**, pas de microservices : une seule application à
  déployer, mais un dossier par module (`app/Modules/Finances/…`) avec ses
  modèles, actions, policies et ressources Filament. Règle : un module
  n'accède aux tables d'un autre module **que via ses classes publiques**
  (actions, requêtes dédiées), jamais en écrivant directement dans ses
  tables. Cela garde la possibilité d'extraire un module plus tard sans en
  payer le coût aujourd'hui.
- **Logique métier dans des classes « Action »** (`EnrollStudent`,
  `RecordPayment`, `PublishReportCards`…), appelées à la fois par Filament,
  l'API et les commandes artisan. Les contrôleurs et les pages Filament ne
  contiennent pas de règles métier : une seule implémentation, testée une fois.
- **PostgreSQL** plutôt que MySQL : types plus stricts, `numeric` exact,
  contraintes `CHECK` et index partiels utiles pour les règles métier
  (« une seule inscription active par élève et par année »), meilleures
  requêtes analytiques pour le pilotage, et possibilité d'ajouter la
  *Row-Level Security* comme seconde barrière d'isolation des tenants.
- **Filament 4** pour les back-offices : formulaires, tableaux, filtres,
  exports et tableaux de bord sans construire un second front-end. Les
  écrans à forte productivité (saisie de notes en grille, caisse) sont des
  pages Filament personnalisées (Livewire).
- **API REST versionnée** (`/api/v1/…`) documentée en OpenAPI, consommée par
  l'app mobile. Ressources JSON (`JsonResource`) systématiques, jamais de
  modèle Eloquent renvoyé brut.
- **Flutter** pour l'app mobile unique (Android en priorité, iOS ensuite),
  avec marque blanche chargée au runtime.
- **PDF** : rendu HTML → PDF côté serveur (Browsershot / Chromium headless
  ou équivalent), en file d'attente pour les lots (bulletins d'une classe
  entière). Les gabarits (reçu, bulletin, certificat) sont des vues Blade
  versionnées, avec l'en-tête de l'école.
- **Redis** pour les files d'attente, le cache et le rate limiting.
- **Qualité** : CI GitHub Actions (Pint, PHPStan, Pest, tests d'isolation,
  migrations sur base vierge) obligatoire avant toute fusion.

---

## 7. Architecture multi-tenant

**Modèle : base unique, schéma partagé, isolation par `tenant_id`.**
Justification identique à Scolia : coût minimal, une seule migration, une
seule sauvegarde. À réévaluer au-delà de ~500 écoles ou si une école exige
contractuellement une base dédiée.

Mécanisme d'isolation (défense en profondeur) :
1. Toutes les tables métier portent `tenant_id` (non nullable, clé étrangère,
   en tête de tous les index composites).
2. Trait `BelongsToTenant` + scope global Eloquent appliqué à **tous** les
   modèles métier ; le `tenant_id` est rempli automatiquement à la création.
3. Le tenant courant est résolu une fois par requête : sous-domaine
   (`ecole-x.talenda.app`) pour le web, en-tête `X-Tenant` validé contre les
   appartenances de l'utilisateur pour l'API mobile.
4. Policies : vérification de la permission **et** du tenant.
5. Clés étrangères composites `(tenant_id, id)` sur les relations critiques
   (inscription → classe, paiement → facture) : la base refuse elle-même
   un lien entre deux écoles.
6. Tests d'isolation automatiques pour chaque ressource : un utilisateur de
   l'école A reçoit 404 sur une ressource de l'école B. Pré-requis de mise en
   production.
7. Fichiers sous `tenants/{tenant_id}/…`, servis uniquement par URL signée
   temporaire.
8. `withoutGlobalScope` interdit hors du contexte Super Admin ; règle vérifiée
   en revue de code et par une règle PHPStan personnalisée.

### Identité et appartenances

```
users (identité globale : téléphone / e-mail, mot de passe)
  └── memberships (user × tenant)  ── rôles spatie (team = tenant)
        ├── staff_members   (si personnel de l'école)
        ├── guardians       (si responsable d'élève)
        └── students        (si élève avec compte, V2)
```

Un parent avec des enfants dans deux écoles a **un seul compte** et deux
`memberships`. L'app propose le sélecteur d'école ; toutes les requêtes
métier restent filtrées par le tenant sélectionné. Une enseignante qui
travaille dans deux écoles est traitée de la même façon.

Le Super Admin plateforme utilise une table et un guard séparés
(`platform_admins`) et n'a pas de `membership`. L'accès aux données d'une
école passe par un **mode support** explicite, journalisé et visible par
l'école.

---

## 8. Modèle de données

Conventions : `id` bigint, `tenant_id` sur toute table métier,
`timestamps`, `soft_deletes` là où la suppression doit rester auditable.
**Montants** : entiers en unité monétaire minimale (`bigint`) + code devise
ISO 4217 au niveau du tenant (XAF au lancement ; XOF, CDF, GNF… ensuite). Jamais de `float` pour
l'argent ni pour les notes (`numeric(5,2)` pour les notes).

### 8.1 Plateforme (hors tenant)
- `tenants` : nom, slug (sous-domaine), pays, devise, fuseau horaire,
  langue, statut.
- `tenant_settings` : logo, couleurs, coordonnées, en-tête des documents,
  signature du directeur (image), paramètres pédagogiques par défaut.
- `plans`, `plan_features` : plans et quotas (nombre d'élèves, modules
  inclus). Aucun prix codé en dur.
- `subscriptions` : tenant, plan, statut (`trialing`, `active`, `past_due`,
  `suspended`, `canceled`), dates, effectif compté.
- `tenant_modules` : module activé / désactivé par école, indépendamment du
  plan.
- `platform_admins`, `audit_logs` (aussi utilisée par les tenants).

### 8.2 Socle (par tenant)
- `users` *(globale)*, `memberships` (user, tenant, statut, dernière
  connexion), tables spatie `roles` / `permissions` (team = tenant).
- `school_years` : libellé (« 2026-2027 »), début, fin, statut (`draft`,
  `open`, `closed`). Une seule année `open` par tenant (index partiel).
- `terms` (périodes) : année, nom (« Trimestre 1 »), ordre, début, fin,
  statut (`open`, `locked`, `published`).
- `cycles` (maternelle, primaire, collège, lycée), `levels` (niveaux :
  CP1, 6e, 2nde…, ordonnés, avec le niveau suivant pour les passages),
  `streams` (séries / filières, optionnel : A, C, D, scientifique…).
- `classes` : année, niveau, série, nom (« 6e B »), capacité, salle,
  professeur principal.
- `subjects` (matières de l'école), `class_subjects` (matière × classe ×
  coefficient, éventuellement regroupée dans un `subject_group` :
  « Matières littéraires »…).
- `staff_members` : personnel (lié à une `membership` s'il a un compte),
  fonction, matricule.
- `teaching_assignments` : enseignant × classe × matière × année.

### 8.3 Inscriptions & dossiers élèves
- `students` : identité **permanente** (matricule unique par tenant, nom,
  prénoms, date et lieu de naissance, sexe, photo, nationalité, informations
  médicales minimales et facultatives).
- `guardians` : responsables (nom, téléphone, e-mail, profession, lien avec
  une `membership` si le parent a un compte).
- `student_guardians` : lien de parenté, `is_primary_contact`,
  `is_financially_responsible`, `can_pick_up`.
- `enrollments` : **élève × année × classe**. Statut (`pre_registered`,
  `enrolled`, `transferred_out`, `withdrawn`, `completed`), type (`new`,
  `re_enrollment`), date, décision de fin d'année (`promoted`, `repeat`,
  `excluded`, `graduated`). Index partiel : une seule inscription active par
  élève et par année.
- `enrollment_documents` : pièces du dossier (acte de naissance, bulletin
  précédent…) avec statut fourni / manquant.
- `student_transfers` : école d'origine / de destination, motif.

### 8.4 Finances / scolarité
Conçu comme un **registre** : rien n'est supprimé ni modifié après
validation. Une erreur se corrige par une écriture inverse (avoir,
annulation de paiement), avec motif et auteur.
- `fee_types` : frais d'inscription, scolarité, uniforme, transport,
  cantine, examen… (obligatoire ou optionnel).
- `fee_schedules` : grille tarifaire = année × niveau (× série) × type de
  frais × montant.
- `installment_plans` + `installments` : échéancier (ex. 3 tranches, dates
  limites, pourcentages).
- `discounts` : réductions et bourses (pourcentage ou montant ; fratrie,
  enfant du personnel, bourse au mérite…), appliquées à une inscription
  avec motif et validation.
- `invoices` : une facture par inscription et par année (créée
  automatiquement à l'inscription), numéro séquentiel par tenant et par
  année, statut (`open`, `paid`, `cancelled`).
- `invoice_lines` : type de frais, montant, réduction, échéance.
- `payments` : montant, date, moyen (`cash`, `mobile_money`,
  `bank_transfer`, `cheque`), référence externe, caissier, caisse, numéro de
  reçu séquentiel, statut (`valid`, `reversed`).
- `payment_allocations` : répartition d'un paiement sur les lignes et
  échéances (plus ancienne échéance d'abord, par défaut).
- `credit_notes` : avoirs (remboursement, remise accordée après coup).
- `cash_registers` + `cash_sessions` : ouverture / clôture de caisse,
  montant théorique vs compté, écart justifié.
- Vues SQL (ou requêtes dédiées) : solde par élève, impayés par échéance,
  recettes par jour / moyen de paiement.

### 8.5 Évaluations & bulletins
- `grading_scales` : barème de l'école (sur 20, sur 10…), seuil de réussite,
  règles d'arrondi, mentions (« Très bien » ≥ 16…).
- `assessments` (évaluations) : classe × matière × période, type (devoir,
  interrogation, composition), date, barème, poids dans la moyenne.
- `scores` : évaluation × élève, note (nullable), statut (`present`,
  `absent`, `exempted`), commentaire.
- `term_results` : **résultats figés** à la publication de la période :
  moyenne par matière, moyenne générale, rang, appréciations, absences.
  On ne recalcule **jamais** un bulletin publié à partir des notes brutes :
  ce qui a été imprimé est ce qui est stocké.
- `term_subject_results` : moyenne par matière, rang dans la matière,
  appréciation de l'enseignant.
- `class_councils` : conseil de classe, décisions et observations.
- `report_cards` : fichier PDF généré, version, date de publication, visible
  par le parent oui / non (blocage possible en cas d'impayé, selon le
  paramétrage de l'école).

### 8.6 Vie scolaire
- `attendance_sessions` (appel : classe × date × créneau) et
  `attendance_records` (élève, statut `present` / `absent` / `late`, motif).
- `absence_justifications` : justification par le parent ou la vie
  scolaire, pièce jointe, validation.
- V2 : `disciplinary_incidents`, `sanctions`, `timetable_slots`,
  `rooms`.

### 8.7 Communication
- `announcements` + `announcement_targets` (école, cycle, niveau, classe,
  élève, rôle).
- `notifications` (table Laravel), `device_tokens`,
  `notification_preferences`.
- `message_logs` : traçabilité des SMS / e-mails envoyés (coût, statut).
- V2 : `conversations`, `messages`, `messaging_rules`, `homeworks`.

### 8.8 Personnel (V2) et modules suivants
- `staff_contracts`, `staff_attendance`, `leave_requests`.
- V3 : `payroll_runs`, `payslips`, tables cantine / transport /
  bibliothèque / inventaire.

---

## 9. Règles métier clés

**Cycle de vie d'une année scolaire**
1. L'administration crée l'année N+1 en `draft` (en copiant classes, matières,
   coefficients et grille tarifaire de l'année N, puis ajustement).
2. Réinscriptions : chaque élève de N ayant la décision `promoted` ou
   `repeat` est proposé au niveau correspondant ; le secrétariat confirme.
3. Ouverture de N+1 (`open`) ; clôture de N (`closed` : plus aucune
   modification des notes ni des inscriptions, les finances restent
   consultables et encaissables pour les arriérés).

**Inscription** (action `EnrollStudent`, transactionnelle)
- Vérifie la capacité de la classe et les doublons possibles (même nom +
  date de naissance).
- Crée l'inscription, puis la facture de l'année depuis la grille tarifaire
  du niveau, applique les réductions, génère l'échéancier.
- Une inscription peut être refusée par paramétrage si l'élève a des
  arriérés sur une année précédente.

**Encaissement** (action `RecordPayment`)
- Le caissier doit avoir une session de caisse ouverte.
- Le paiement est réparti automatiquement sur les échéances les plus
  anciennes (modifiable manuellement).
- Reçu PDF numéroté, imprimable immédiatement ; notification au parent.
- Annulation : uniquement par un rôle autorisé, avec motif, par écriture
  inverse. Le reçu d'origine reste dans l'historique.

**Calcul des moyennes** (action `ComputeTermResults`)
- Moyenne d'une matière = moyenne pondérée des évaluations de la période
  (poids par type d'évaluation, paramétrable).
- Moyenne générale = moyenne des matières pondérée par les coefficients de
  la classe.
- Élève absent à une évaluation : selon le paramètre de l'école (ignorer,
  compter 0, ou note à rattraper).
- Rang : ex æquo gérés (« 3e ex æquo »).
- Arrondis appliqués **une seule fois**, à l'affichage et au figeage, selon
  `grading_scales`.
- Le calcul est un service pur, couvert par des tests unitaires exhaustifs
  (cas limites : matière sans note, élève arrivé en cours de période,
  dispense…).

**Verrouillage et publication d'une période**
`open` (saisie) → `locked` (plus de saisie, contrôle, conseil de classe)
→ `published` (résultats figés, bulletins générés, parents notifiés).
Rouvrir une période publiée exige un rôle de direction et laisse une trace.

---

## 10. API (mobile)

- Préfixe `/api/v1`, authentification Sanctum (token par appareil), en-tête
  `X-Tenant` pour l'école sélectionnée.
- Endpoints MVP orientés parent :
  - `POST /auth/login`, `POST /auth/otp` (connexion par téléphone + code SMS
    en V2), `GET /me` (appartenances, enfants par école)
  - `GET /students/{id}` (profil, classe actuelle)
  - `GET /students/{id}/balance`, `GET /students/{id}/payments`,
    `GET /payments/{id}/receipt` (PDF signé)
  - `GET /students/{id}/terms/{term}/results`, `GET /report-cards/{id}` (PDF)
  - `GET /students/{id}/attendance`
  - `GET /announcements`, `GET /notifications`, `POST /device-tokens`
- Pagination par curseur, réponses compressées, champs minimaux (réseau
  mobile lent).
- Documentation OpenAPI générée et testée en CI (contrat stable pour l'app).

---

## 11. Écrans

### Back-office école (Filament)
- **Tableau de bord** : effectifs par niveau, recettes du jour / du mois,
  impayés, avancement de la saisie des notes.
- **Inscriptions** : liste des élèves (filtres classe, statut, impayé),
  fiche élève (onglets identité, responsables, scolarité, finances, notes,
  absences, documents), assistant d'inscription, import Excel, réinscriptions
  en masse, impressions (certificat, carte).
- **Caisse** : recherche d'élève rapide, solde, encaissement en un écran,
  impression du reçu, clôture de caisse.
- **Finances** : grille tarifaire, réductions, factures, paiements, état
  des impayés (export, relances SMS groupées), rapports de recettes.
- **Évaluations** : création d'évaluations, **grille de saisie des notes**
  (élèves en lignes, navigation au clavier, sauvegarde automatique),
  suivi de saisie, conseil de classe, génération et publication des
  bulletins.
- **Vie scolaire** : appel, absences, justifications.
- **Communication** : annonces, historique des envois.
- **Paramètres** : école, année scolaire, périodes, niveaux, classes,
  matières et coefficients, barème, utilisateurs et rôles, modèles de
  documents.

### Back-office plateforme (Filament, panel séparé)
Écoles, plans, abonnements, modules, utilisation, mode support journalisé.

### App mobile (Flutter)
Sélection école / enfant · accueil (solde, dernières notes, absences,
annonces) · finances (échéancier, reçus) · résultats et bulletins ·
absences · annonces · notifications · profil et sessions.

---

## 12. Rôles et permissions

Via `spatie/laravel-permission` (teams = tenant), permissions granulaires
par module (`enrollment.create`, `payment.record`, `payment.reverse`,
`score.enter`, `term.publish`…). Toute autorisation passe par une Policy.

| Rôle | Périmètre |
|---|---|
| `school_admin` | Tout dans son école, y compris rôles et paramètres |
| `director` (direction / promoteur) | Tout en lecture, validation (publication des bulletins, annulations de paiement, réductions) |
| `academic_director` (censeur, directeur des études) | Pédagogie : classes, évaluations, bulletins, vie scolaire |
| `secretary` | Inscriptions, dossiers, impressions |
| `accountant` | Finances complètes, sauf paramétrage de l'école |
| `cashier` | Encaissement et sa propre caisse uniquement |
| `teacher` | Ses classes et matières : notes, absences, appréciations |
| `supervisor` (surveillant) | Absences, retards, discipline |
| `parent` | Ses enfants uniquement, lecture (+ justification d'absence) |
| `student` (V2) | Lui-même, lecture |

Rôles personnalisés (combinaison de permissions existantes) en V2.
**Séparation des tâches** : le paramétrage par défaut empêche le caissier
d'annuler ses propres encaissements.

---

## 13. Notifications

- Canaux : push (FCM) et e-mail au MVP ; SMS / WhatsApp en V2 via une
  interface `SmsGateway` (fournisseur interchangeable par pays).
- Toujours en file d'attente ; envois de masse en `Bus::batch` avec limitation
  de débit.
- Événements : paiement enregistré (reçu), échéance proche, impayé,
  absence, nouvelle note / bulletin publié, annonce.
- Préférences par catégorie et par utilisateur ; les SMS (payants) sont
  plafonnés par école selon son plan.
- Deep link (`type` + `id`) vers l'écran correspondant de l'app.

---

## 14. Sécurité et données personnelles

- HTTPS partout (HSTS), mots de passe hachés (argon2id), règles
  `Password::defaults()` avec `uncompromised()`, changement obligatoire à la
  première connexion pour les comptes créés par import.
- Limitation des tentatives de connexion par IP et par compte.
- Sessions par appareil, révocables ; expiration des tokens mobiles.
- Authentification à deux facteurs obligatoire pour `school_admin`,
  `director`, `accountant` et les Super Admins (V2 pour les autres rôles).
- Isolation des tenants : §7. C'est la mesure la plus critique : les données
  concernent des mineurs et de l'argent.
- Journal d'audit sur toute action sensible : paiement et annulation,
  réduction, modification de note après verrouillage, publication de
  bulletin, changement de rôle, export, accès en mode support.
- Fichiers validés (type, taille), stockés hors webroot, servis par URL
  signée.
- Sauvegardes quotidiennes chiffrées, restauration testée chaque mois.
- Données des mineurs : minimisation (pas de données médicales détaillées),
  durée de conservation définie, export complet et suppression à la demande
  d'une école qui quitte le service. Conformité avec les lois locales de
  protection des données (ex. lois nationales inspirées de la convention de
  Malabo / du RGPD).
- Dépendances : `composer audit` et Dependabot en CI.

---

## 15. Modèle SaaS

- Abonnement annuel par école, prix selon l'effectif et les modules ; plans
  pilotés en base (exemples modifiables : **Essentiel** — socle,
  inscriptions, finances, bulletins ; **Pro** — + vie scolaire,
  communication, app parents ; **Premium** — + RH, examens, services
  annexes, statistiques avancées).
- Quota d'élèves : avertissement à l'approche de la limite, jamais de
  blocage brutal.
- Abonnement `past_due` → lecture seule, puis suspension ; **jamais de
  suppression de données**.
- Facturation des écoles gérée manuellement au lancement ; paiement en ligne
  de l'abonnement en V2.
- Mise en place assistée (import des élèves et de la grille tarifaire) :
  service facturable et facteur clé de réussite commerciale.

---

## 16. Plan de développement

**Étape 0 — Fondations**
Projet Laravel 13, PostgreSQL, CI (Pint, PHPStan, Pest), multi-tenant
(résolution, scope, policies, tests d'isolation génériques), identité +
appartenances + rôles, panels Filament école et plateforme, structure en
modules, gabarit PDF de base.
→ *Livrable : une école de démo se connecte, isolée d'une seconde école.*

**Étape 1 — Structure scolaire**
Années, périodes, cycles, niveaux, séries, classes, matières, coefficients,
personnel, affectations, paramètres de l'école.
→ *Livrable : une école configure son année scolaire seule.*

**Étape 2 — Inscriptions**
Élèves, responsables, inscriptions, import Excel, dossier élève,
certificats et cartes PDF.
→ *Livrable : une école inscrit tous ses élèves.*

**Étape 3 — Finances**
Grille tarifaire, échéancier, réductions, factures automatiques, caisse,
reçus, impayés, clôture de caisse, rapports.
→ *Livrable : la caisse fonctionne le jour de la rentrée.*

**Étape 4 — Évaluations & bulletins**
Évaluations, saisie des notes, calculs, verrouillage, conseil de classe,
bulletins PDF, décisions de fin d'année, passage à l'année suivante.
→ *Livrable : bulletins du premier trimestre imprimés depuis Talenda.*

**Étape 5 — Vie scolaire, communication, app parents**
Absences, annonces, notifications, API v1, app Flutter parent.
→ *Livrable : MVP complet.*

**Étape 6 — Durcissement et pilote**
Audit de sécurité, tests de charge (bulletins et notifications en masse),
sauvegardes testées, documentation d'exploitation, **école pilote**.
→ *Livrable : mise en production commerciale.*

À chaque étape : revue des risques avant de passer à la suivante. On ne
construit pas l'étape N+1 sur une isolation ou des calculs non validés à
l'étape N.

---

## 17. Décisions à valider

| Sujet | Proposition | Alternative |
|---|---|---|
| Back-office | Filament 4 | Inertia + Vue / React, si l'on veut une interface sur mesure (coût de développement bien plus élevé) |
| Mobile | Flutter (expérience Scolia) | React Native / Expo (même langage TypeScript que d'éventuels outils web) |
| Hébergement | Laravel Cloud ou VPS géré (Forge) avec Redis + worker de file d'attente | Mutualisé : **déconseillé** (pas de files d'attente fiables, ni de Chromium pour les PDF) |
| Tenant côté web | Sous-domaine par école | Sélection de l'école après connexion |
| Connexion parent | Téléphone + mot de passe au MVP, code SMS (OTP) en V2 | OTP dès le MVP (coût SMS) |
| Mobile money (V2) | Agrégateur couvrant **MTN MoMo et Airtel Money au Congo** (couverture, frais et délais de reversement à vérifier auprès des fournisseurs) | Intégration directe des API MTN MoMo et Airtel Money (deux contrats, deux intégrations) |
| SMS (V2) | Fournisseur local ou agrégateur avec bonne délivrabilité sur MTN et Airtel Congo, nom d'expéditeur au nom de l'école | — |

---

## 18. Journal des décisions

| Date | Décision | Raison |
|---|---|---|
| 2026-10-29 | Talenda repart de zéro ; Scolia sert de référence fonctionnelle | Passage d'un cahier de liaison à une gestion scolaire complète ; corriger les limites de modèle de Scolia (identité, inscriptions, notes) |
| 2026-10-29 | **PostgreSQL** retenu (au lieu de MySQL, pourtant mieux maîtrisé par l'équipe) | Contraintes `CHECK` et index partiels pour les règles métier (une inscription active par élève et par année), `numeric` exact pour l'argent et les notes, requêtes analytiques du pilotage, Row-Level Security possible en seconde barrière d'isolation. Coût d'apprentissage limité : Eloquent et les migrations Laravel masquent la plupart des différences |
| 2026-10-29 | Marché de lancement : Congo-Brazzaville (XAF, 3 trimestres, notes sur 20, MTN MoMo / Airtel Money) | Pays des premières écoles clientes ; paramètres portés par un gabarit de pays pour rester multi-pays |
