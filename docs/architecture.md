# Architecture & décisions

## État de la source de vérité

Le checkout initial ne contenait pas les fichiers annoncés (cahier des charges, diagramme ERD, workflow). L’architecture suivante est donc une base simple et provisoire. Elle doit être comparée aux fichiers métier dès qu’ils seront disponibles.

## Stack

- Laravel 12 / PHP 8.2 pour le routage, les sessions, la validation, les règles métier et la persistance.
- Inertia 2 pour servir les pages React depuis Laravel.
- React 19 + TypeScript strict pour l’interface.
- SQLite par défaut en local, configuration MySQL minimale disponible.
- Stockage Laravel `local` privé pour les pièces jointes.

Le serveur Vite sert aussi un aperçu autonome (racine `index.html`) avec des données fictives en mémoire. En production Laravel ne sert pas cet aperçu : l’application utilise `resources/views/app.blade.php` et l’entrée Inertia `resources/js/app.tsx`.

## Modèle de données

```mermaid
erDiagram
    USERS ||--o{ DOCUMENTS : owns
    DOCUMENTS ||--o| ACCOUNTING_ENTRIES : posts

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp created_at
    }
    DOCUMENTS {
        bigint id PK
        bigint user_id FK
        string original_filename
        string file_path
        string supplier_name
        string invoice_number
        date invoice_date
        date due_date
        string currency
        string account_code
        decimal subtotal
        decimal vat_amount
        decimal total_amount
        string status
    }
    ACCOUNTING_ENTRIES {
        bigint id PK
        bigint document_id FK_UK
        date entry_date
        string description
        string supplier_name
        string invoice_number
        string account_code
        decimal subtotal
        decimal vat_amount
        decimal total_amount
        string currency
    }
```

Un `Document` représente le fichier source et les valeurs corrigibles. Une `AccountingEntry` est créée uniquement à la comptabilisation ; la contrainte unique sur `document_id` garantit au plus une écriture par facture. Ce modèle ne représente pas encore les lignes de débit/crédit d’une comptabilité en partie double.

## Workflow des documents

```text
Import privé ──> needs_review ──(compléter / corriger)──> needs_review
                         │
                         └──(validation serveur + transaction)──> posted + 1 AccountingEntry
```

Une facture postée est en lecture seule. L’import ne lance pas de traitement IA : aucun fournisseur ou contrat de données n’a été fourni. Les documents importés restent à compléter manuellement, sans valeur générée présentée comme fiable.

## Responsabilités

- `AuthenticatedSessionController` et `LoginRequest` : ouverture/fermeture de session et limitation des tentatives.
- `DocumentController` : orchestration HTTP, pagination, stockage, rendu Inertia et autorisation.
- `StoreDocumentRequest` / `UpdateDocumentRequest` : validation des entrées.
- `DocumentPolicy` : accès par propriétaire, y compris le téléchargement du fichier.
- `PostDocumentToLedger` : contrôle métier, verrouillage, création de l’écriture et changement de statut dans une transaction.
- `DashboardController` : calcul des indicateurs à partir des données de l’utilisateur connecté.
- `resources/js/Components` : éléments visuels partagés sans accès direct à la base ou au routeur serveur.
- `resources/js/Pages` : adaptateurs Inertia reliant contrôleurs et vues React.

## Décisions simples et limites

1. **Authentification par session, sans inscription** : protège les pièces sensibles sans introduire de fournisseur d’identité ; les comptes sont provisionnés par console.
2. **Propriété directement portée par `documents.user_id`** : isolement utilisateur suffisant pour le premier lot, sans ajouter prématurément une hiérarchie société/équipe.
3. **Une écriture synthétique par facture** : le périmètre disponible ne décrit pas les lignes de facture ni un journal débit/crédit.
4. **Extraction IA reportée** : ne pas choisir OpenAI, un autre fournisseur, un modèle ou un format de sortie sans exigences et politique de confidentialité.
5. **Aperçu séparé** : le preview Vite réutilise les vues React mais utilise un petit jeu de données local ; il n’est pas une API de démonstration ni une alternative à Laravel.
6. **Devise EUR uniquement** : les agrégations du tableau de bord ne mélangent pas des montants de devises différentes. Aucun traitement de conversion n’est fait.
7. **Nom ComptaFlow** : nom de travail de l’interface, choisi faute de nom de marque dans les fichiers du dépôt initial.

## Sécurité et exploitation

- Routes métier derrière le middleware `auth`; connexion limitée par adresse e-mail et IP.
- Autorisation de propriété appliquée au rendu, à l’édition, à la comptabilisation et au fichier.
- Fichiers stockés sous `storage/app/private` et jamais exposés par `storage:link`.
- Les identifiants de démonstration et l’absence de sauvegardes/chiffrement du stockage local interdisent d’exposer l’installation de développement à de vraies pièces comptables.
- Avant production : spécifier le stockage sauvegardé/chiffré, la politique de rétention, l’audit, la récupération de compte, la journalisation et l’intégration IA.
