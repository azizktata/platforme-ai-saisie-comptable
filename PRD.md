# PRD — ComptaFlow MVP

## 1. Source et statut des exigences

Aucun document d’exigences n’est présent dans le dépôt initial (seul un README de titre était fourni). Ce PRD formalise donc un périmètre provisoire choisi à partir du nom du projet et des technologies demandées dans la requête. Il doit être remplacé ou ajusté dès réception du cahier des charges, de l’ERD et du workflow métier de référence.

L’application ne prétend pas effectuer d’extraction IA : aucun fournisseur, modèle, format de sortie, seuil de confiance, politique de traitement des données ou secret d’API n’a été spécifié. Les documents importés entrent dans une file de vérification manuelle.

## 2. Objectif

Permettre à un utilisateur comptable de déposer une facture fournisseur, de vérifier et corriger les données essentielles, puis de créer une écriture synthétique à partir de cette facture. La vue d’ensemble donne le volume à vérifier, les écritures récentes et les dépenses comptabilisées.

## 3. Personnes et accès

- **Utilisateur comptable** : se connecte à son compte, gère ses propres documents et comptabilise ses factures.
- Chaque document appartient à un seul compte utilisateur. Les documents, fichiers et indicateurs ne sont jamais partagés entre comptes.
- Il n’y a pas d’inscription publique ni de flux de récupération du mot de passe. Les comptes sont créés par commande console ou par le compte local de démonstration.

## 4. Périmètre fonctionnel

### 4.1 Authentification

- Une personne non connectée est redirigée vers la page de connexion pour toute route métier.
- L’e-mail est normalisé en minuscules avant authentification.
- Cinq tentatives de connexion incorrectes au maximum sont autorisées avant temporisation.
- La connexion régénère l’identifiant de session ; la déconnexion invalide la session et son jeton CSRF.
- Il n’existe ni inscription, ni mot de passe oublié, ni rôles dans ce MVP.

### 4.2 Vue d’ensemble

- Afficher le nombre de documents à vérifier, les écritures comptabilisées pendant le mois, le total TTC comptabilisé pendant le mois et le nombre de documents reçus pendant le mois.
- Présenter les dépenses TTC des six derniers mois et jusqu’à quatre documents récents à vérifier.
- Afficher les six documents les plus récents et leur statut.
- Tous les indicateurs sont calculés à partir des documents appartenant à l’utilisateur connecté. Un état vide est valide lorsque le compte ne contient aucune donnée.

### 4.3 Documents et comptabilisation

- Formats importables : PDF, JPG/JPEG et PNG ; taille maximale : 20 Mo.
- Les fichiers sont enregistrés dans le stockage local privé et accessibles uniquement après authentification et vérification de propriété.
- À l’import, créer un document au statut `needs_review`. Aucun champ comptable n’est considéré comme extrait automatiquement.
- Permettre de compléter ou corriger le fournisseur, la référence de facture, les dates, le compte comptable, le libellé et les montants HT/TVA/TTC. La devise est EUR dans ce lot.
- Une facture ne peut être comptabilisée que si les champs obligatoires sont présents, si TTC est supérieur à zéro et si HT + TVA correspond à TTC à 0,01 € près.
- Comptabiliser dans une transaction atomique : créer une seule écriture liée à la facture et passer son statut à `posted`.
- Une écriture postée est définitive et non modifiable. Une requête de comptabilisation répétée retourne l’écriture existante sans en créer une seconde.
- Recherche textuelle par fournisseur, référence ou nom de fichier ; filtres par statut ; pagination des résultats.

## 5. Parcours principal

1. L’utilisateur se connecte.
2. Depuis la vue d’ensemble ou la page Documents, il sélectionne un fichier valide.
3. Le système stocke le fichier de façon privée et crée une facture **À vérifier**.
4. L’utilisateur ouvre la facture, consulte le document source et renseigne/corrige les champs comptables.
5. Il peut enregistrer une revue partielle sans comptabiliser, puis revenir plus tard ; seuls les champs nécessaires à la comptabilisation doivent alors être complets.
6. À la comptabilisation, le système vérifie les champs et l’équilibre des montants, crée l’écriture et verrouille la facture.
7. Le tableau de bord et la liste affichent le nouveau statut et mettent à jour leurs indicateurs.

## 6. Règles métier

- Statuts actuellement supportés : `needs_review` et `posted`.
- Une écriture comptable appartient à une seule facture (`accounting_entries.document_id` unique).
- La date d’écriture est la date de facture ; le montant TTC est conservé en EUR.
- Devise unique dans ce lot : EUR. Les montants sont stockés avec deux décimales, sans conversion monétaire.
- Toute opération de lecture, modification, comptabilisation ou téléchargement vérifie la propriété du document.
- Les données d’exemple du seeder sont fictives et réservées aux environnements `local` et `testing`.

## 7. Exigences non fonctionnelles

- Laravel 12, Inertia 2, React 19 et TypeScript strict.
- Validation serveur systématique ; ne pas se fier uniquement aux validations du navigateur.
- Stockage privé pour les pièces comptables ; sessions Laravel et limitation des tentatives de connexion.
- Responsivité pour ordinateur, tablette et mobile ; formulaires utilisables au clavier et erreurs exposées aux technologies d’assistance.
- Logique de comptabilisation testable indépendamment du contrôleur.

## 8. Hors périmètre (à ne pas déduire comme livré)

- OCR ou extraction par IA, sélection d’un fournisseur IA, entraînement, score de confiance et traitement asynchrone.
- Plan comptable administrable, lignes de facture, écritures équilibrées débit/crédit, grand livre et clôture.
- Connexion à une banque, export ou synchronisation avec un logiciel comptable.
- Espaces d’entreprise multi-utilisateurs, rôles, invitations, inscription publique, réinitialisation de mot de passe et MFA.
- Suppression/archivage des factures, détection de doublons et gestion des avoirs.

## 9. Critères d’acceptation

- Un utilisateur non connecté ne peut ni voir le tableau de bord, ni consulter/télécharger un document.
- L’import d’un PDF valide crée un document appartenant à l’utilisateur et au statut `needs_review`.
- Un autre utilisateur ne peut pas consulter, modifier ou comptabiliser ce document.
- Les champs obligatoires et l’équilibre des montants sont validés côté serveur.
- La comptabilisation crée une seule écriture liée et le document passe à `posted`.
- Les filtres, la pagination et les indicateurs respectent le compte utilisateur.
- L’aperçu Vite ne prétend pas persister les données et reste séparé du backend Laravel.
