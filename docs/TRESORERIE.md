# Trésorerie : caisses, dépenses, salaires, avances, virements

Décision (2026-11-07) : chaque mouvement d'argent de l'école passe une
écriture **SYSCOHADA révisé** dans un journal comptable, exportable pour le
comptable. Les caissiers ne voient jamais de numéro de compte : ils
choisissent une caisse et une catégorie ; Talenda écrit le journal.
Pas de comptabilité complète (grand livre, balance, bilan) : le comptable
de l'école l'établit à partir de l'export.

## Sites et caisses

- **Sites** (Paramètres → Sites) : une école peut en avoir plusieurs. Les
  classes, donc les élèves, et les caisses y sont rattachées. Le caissier
  d'un site ne trouve à la caisse que les élèves de son site. Une école à
  un seul site n'a rien à régler (« Site principal »).
- **Caisses** (Paramètres financiers → Caisses), de trois natures, chacune
  avec son compte :

  | Nature | Compte proposé | Exemple |
  |---|---|---|
  | Caisse (espèces) | 5711 | Caisse principale, Caisse du site Nord, Caisse centrale |
  | Mobile money | 5521 | Compte MTN MoMo de l'école |
  | Banque | 5211 | BGFI, LCB |

  Un sous-compte par caisse est possible (57111, 57112…) si le comptable le
  souhaite. Seules les caisses d'espèces s'ouvrent au guichet.
- **Solde** : solde de départ (fond compté à la toute première ouverture)
  + mouvements du journal. Une caisse ne descend jamais sous zéro.

## Opérations et écritures

| Opération | Exemple | Écriture |
|---|---|---|
| Encaissement espèces | parent paie 50 000 F | D 5711 caisse / C 411 parents |
| Encaissement mobile money, virement, chèque | | D 5521 ou 5211 / C 411 |
| Annulation d'un encaissement | | écriture inverse |
| Dépense | eau, électricité, fournitures… | D compte de la catégorie (6051, 6052…) / C caisse |
| Salaire (paiement simple, sans calcul de paie) | 150 000 F à un enseignant pour novembre | D 661 / C 422, puis D 422 / C caisse |
| Avance à justifier | 20 000 F pour des craies | D 421 / C caisse |
| Justification de l'avance | dépensé 17 500, rendu 2 500 | D charge 17 500 + D caisse 2 500 / C 421 20 000 |
| Achat plus cher que l'avance | dépensé 22 000 pour 20 000 | D charge 22 000 / C 421 20 000 + C caisse 2 000 |
| Achat annulé | tout rendu | D caisse / C 421 |
| Virement entre caisses (envoi) | site → caisse centrale, caisse → banque | D 585 / C caisse de départ |
| Virement (réception, après comptage) | | D caisse d'arrivée / C 585 ; écart en 658 ou 758 |
| Écart au comptage (ouverture, clôture) | 500 F manquants | D 658 / C caisse (ou D caisse / C 758) |

Catégories de dépense et comptes proposés (Paramètres financiers →
Catégories de dépense, modifiables) : eau 6051, électricité 6052,
fournitures d'entretien 6054, fournitures de bureau 6055, petit matériel
et fournitures pédagogiques 6056, carburant et transport 6181, loyers
6222, entretien et réparations 624, téléphone et internet 628, frais
bancaires 631, autres charges 6588. **À faire valider par le comptable de
l'école.**

## Règles

- **Validation** : au-delà du seuil de l'école (50 000 F par défaut,
  Trésorerie → Comptabilité → Seuil de validation), une dépense, un salaire
  ou une avance saisis par un caissier attendent la validation de la
  direction ; l'argent ne sort qu'à la validation. Personne ne valide sa
  propre demande. Les virements entre caisses n'y sont pas soumis (l'argent
  reste dans l'école).
- **Qui sort l'argent d'une caisse** : le caissier qui la tient (session
  ouverte) ; la direction et la comptabilité pour toutes les caisses.
- **Virement** : « en route » tant que la caisse d'arrivée ne l'a pas
  compté ; un écart doit être expliqué. Annulable tant qu'il est en route.
- **Avance** : reste « à justifier », au nom de la personne, jusqu'au
  retour de la facture et de la monnaie ; la direction voit les avances en
  cours sur son tableau de bord.
- **Salaire** : un second versement pour le même mois exige un motif
  (complément, rappel).
- **Ouverture de caisse** : le fond proposé est le solde de la caisse ; un
  écart avec le comptage est expliqué et passé au journal.
- **Rien ne s'efface** : une dépense ou un salaire payés par erreur
  s'annulent par la direction (jamais par qui les a saisis), avec une
  écriture inverse ; l'opération reste visible, marquée annulée.

## Export pour le comptable

Trésorerie → Comptabilité → Exporter le journal comptable : fichier CSV
(Excel) d'une période, une ligne par compte mouvementé : date, journal (CA
caisse, MM mobile money, BQ banque, OD autres), n° de pièce (reçu R… ou
opération OP…), compte, libellés, débit, crédit.

## Plus tard

- Facturation au journal (D 411 / C 706) pour un journal des ventes.
- Pièces justificatives scannées (factures) : quand un stockage permanent
  (bucket) sera attaché.
- Paie complète (CNSS, impôts, bulletins de paie) : module à part.
