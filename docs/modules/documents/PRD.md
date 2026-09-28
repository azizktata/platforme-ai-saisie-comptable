# PRD — Documents et écritures comptables

## Objectif
Importer des factures fournisseurs, contrôler leurs informations comptables et enregistrer une écriture synthétique une fois la facture vérifiée.

## Parcours

1. Depuis le tableau de bord ou la liste, l’utilisateur ouvre « Importer un document ».
2. Il choisit un PDF, JPG/JPEG ou PNG de 20 Mo maximum.
3. Le fichier est stocké en privé et un `Document` appartenant à l’utilisateur est créé en statut `needs_review`.
4. L’utilisateur ouvre la facture source et complète/corrige les champs de revue.
5. Il peut enregistrer une revue partielle pour continuer plus tard, ou choisir « Comptabiliser » lorsque les champs obligatoires sont complets.
6. Si les contrôles passent, le système crée une `AccountingEntry` dans la même transaction et marque le document `posted`.

## Champs de revue

Fournisseur, numéro de facture, date de facture, échéance facultative, devise EUR, compte comptable, libellé, montant HT, TVA et montant TTC.

## Règles métier

- L’import ne déduit aucune donnée automatiquement. Aucun OCR/IA n’est connecté ; un document vierge reste **À vérifier**.
- Fournisseur, référence, date, devise, compte, libellé, HT, TVA et TTC sont obligatoires pour comptabiliser.
- HT et TVA doivent être positifs ou nuls, TTC strictement positif, et `|TTC - (HT + TVA)| ≤ 0,01`.
- La comptabilisation crée exactement une écriture par document. Sa date est la date de facture.
- Une facture postée ne peut plus être modifiée ; une requête de comptabilisation répétée retourne l’écriture existante sans créer de doublon.
- Les actions et l’accès au fichier vérifient la propriété utilisateur ; le fichier est conservé sur le disque privé.
- La liste permet recherche fournisseur/référence/nom de fichier, filtre par statut et pagination.

## Critères d’acceptation

- Un fichier valide est conservé et apparaît dans la liste à vérifier.
- Les types interdits, les fichiers trop volumineux et les données invalides sont refusés côté serveur.
- Une facture équilibrée est postée et reliée à une seule écriture.
- Une facture non équilibrée reste en revue avec l’erreur sur TTC.
- Une facture postée est en lecture seule.
