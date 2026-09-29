# Architecture & décisions

## Source de vérité et livraison incrémentale

Le cahier des charges client est documenté dans [`requirements.md`](requirements.md). Les Phases 1–5 sont implémentées au niveau applicatif : fondation multi-cabinet, référentiels comptables, intake privé, OCR, structuration, analyse comptable et validation humaine. OCR.space Free Engine 3 est le provider actif par défaut (transcription texte) ; Qwen3.8 27B gratuit structure la transcription via OpenRouter avec `reasoning.effort=none` ; `openrouter/free` prépare les propositions comptables. Mistral demeure sélectionnable pour l’extraction directement structurée. Les tests/migrations Laravel doivent être vérifiés avec PHP/Composer. L’import `.mae` et le transfert Sage réel restent différés.

## Stack

- Laravel 13 et PHP 8.3+ pour l’application monolithique, les sessions, l’autorisation, les règles métier, le stockage et les jobs.
- Inertia.js, React 19 et TypeScript pour les pages et composants d’interface ; Tailwind CSS pour les utilitaires de présentation.
- MySQL comme base d’exécution principale. SQLite en mémoire reste réservé aux tests automatisés.
- Les mutations des Phases 1 et 2 sont synchrones. OCR, extraction structurée et propositions comptables des Phases 4–5 utilisent la queue Laravel `database` et un worker sur `ocr`.
- `OcrProvider` isole l’OCR : OCR.space Free Engine 3 est actif par défaut ; Mistral reste disponible via `OCR_PROVIDER=mistral`. Qwen3.8 27B gratuit via OpenRouter structure le texte OCR.space, avec le raisonnement désactivé et une limite de 2 500 tokens ; Laravel normalise et valide les valeurs. `openrouter/free` reste utilisé pour l’analyse comptable. Les clés API restent côté serveur.
- Les sources facture de la Phase 3 utilisent le disque Laravel `local` (`storage/app/private` par défaut) et ne sont accessibles qu’au travers d’une route de téléchargement authentifiée et autorisée.

Le serveur Vite `preview:ui` est un aperçu autonome non persistant pour examiner le tableau de bord et les données comptables fictives. L’application Laravel utilise `resources/views/app.blade.php` et `resources/js/app.tsx`; `ToastHost` et `RequestToastEvents` sont montés dans l’entrée Inertia.

## Modèle de données de la Phase 1

```mermaid
erDiagram
    CABINETS ||--o{ USERS : has
    CABINETS ||--o{ COMPANIES : manages
    USERS ||--o{ COMPANY_USER_ACCESS : has
    COMPANIES ||--o{ COMPANY_USER_ACCESS : grants

    CABINETS { bigint id PK string name string slug UK }
    USERS { bigint id PK bigint cabinet_id FK string cabinet_role string name string email UK string password }
    COMPANIES { bigint id PK bigint cabinet_id FK string name string tax_identifier string activity string sector string currency }
    COMPANY_USER_ACCESS { bigint id PK bigint company_id FK bigint user_id FK string role }
```

`cabinet_id` est obligatoire pour les utilisateurs. `cabinet_role` distingue les administrateurs du cabinet des membres ; `company_user_access.role` porte les rôles société (`invoice_manager`, `company_user`). L’administrateur de cabinet peut accéder à toutes les sociétés de son cabinet. Pour les autres utilisateurs, seule une ligne pivot accorde l’accès. Les policies et les validations contrôlent également que l’utilisateur et la société relèvent du même cabinet.

## Modèle de données de la Phase 2

```mermaid
erDiagram
    COMPANIES ||--o{ CHART_ACCOUNTS : has
    COMPANIES ||--o{ ANALYTICAL_ACCOUNTS : has
    COMPANIES ||--o{ THIRD_PARTIES : has
    COMPANIES ||--o{ JOURNALS : has
    COMPANIES ||--o{ JOURNAL_ENTRIES : owns
    CHART_ACCOUNTS ||--o{ THIRD_PARTIES : auxiliary_accounts
    JOURNALS ||--o{ JOURNAL_ENTRIES : records
    JOURNAL_ENTRIES ||--o{ JOURNAL_ENTRY_LINES : contains
    CHART_ACCOUNTS ||--o{ JOURNAL_ENTRY_LINES : posts_to
    THIRD_PARTIES ||--o{ JOURNAL_ENTRY_LINES : concerns
    ANALYTICAL_ACCOUNTS ||--o{ JOURNAL_ENTRY_LINES : classifies
```

Les comptes généraux/analytique, tiers et journaux ont un code texte unique par société. `journal_entries` porte la société et le journal ; chaque ligne conserve son compte général et peut référencer un tiers et un axe analytique. Débit/crédit sont `DECIMAL(18,3)` pour ne pas perdre les millimes TND. Les actions de consultation sont scoppées par la relation de la société autorisée. Le petit jeu `SeedDemoAccountingData` est transactionnel et idempotent ; le seeder et la route de démonstration ne sont actifs qu’en `local`/`testing`.

### Intake des factures — Phase 3

Les migrations `000015`–`000016` créent `invoices` et `invoice_lines`. Une facture est rattachée à une société ; son tiers, son importateur et les valeurs OCR/montants sont nullable selon disponibilité. L’état initial est `uploaded`. Les lignes sont préparées par le schéma mais aucune ligne n’est générée avant OCR.

`InvoiceController` reçoit un fichier par requête et s’appuie sur `UploadInvoiceFileRequest`, `CompanyPolicy::manageInvoices` et `StoreInvoiceUpload`. Le client traite un lot séquentiellement (limite d’interface 300 fichiers ; limite serveur dynamique de 1 Mo avec OCR.space Free ou 20 Mo avec Mistral). Le service stocke le document sur le disque `local`, sous un chemin société, puis enregistre MIME, taille, nom d’origine et empreinte SHA-256. Les erreurs de persistance tentent de supprimer le fichier déjà écrit.

La détection SHA-256 s’effectue au niveau de la société, sous verrou de la ligne société afin de sérialiser les uploads concurrents. Si le contenu existe déjà, le serveur répond `409` sans nouvelle persistance, sauf confirmation explicite. La liste est paginée et n’expose jamais `file_path`. Le téléchargement vérifie le droit `view` de la société et l’égalité `invoice.company_id === company.id` avant de transmettre le document depuis le disque privé.

Chaque import accepté planifie `ProcessInvoiceOcr` après persistance. OCR.space envoie le fichier privé en multipart avec `apikey` dans l’en-tête et Engine 3 ; sa réponse brute et son texte sont conservés séparément. `ExtractInvoiceData` envoie la transcription à OpenRouter en JSON Schema strict et sauvegarde le contrat facture validé. Mistral reste disponible pour l’annotation JSON structurée et évite cette étape de normalisation. Les champs obligatoires sont contrôlés avant que `AnalyzeAccountingProposal` appelle OpenRouter avec le profil/référentiels/historique de la société. La proposition et ses lignes sont modifiables par un gestionnaire ; les modifications sont horodatées et chaque nouvelle analyse conserve les versions et réponses brutes précédentes. L’écriture comptable n’est créée qu’après validation humaine explicite.

## Responsabilités actuelles

- `AuthenticatedSessionController` / `LoginRequest` : connexion par session, normalisation d’e-mail, limitation des tentatives, déconnexion sécurisée.
- `Cabinet` / `Company` / `User` : relations entre cabinet, sociétés et accès.
- `CompanyPolicy` : contrôle d’accès cabinet/société, gestion des données comptables et gestion/import des factures.
- `StoreCompanyRequest` / `StoreCabinetUserRequest` / `UploadInvoiceFileRequest` : validation serveur, dont l’interdiction d’affecter une société d’un autre cabinet et les formats/tailles d’upload autorisés.
- `CompanyController` / `CabinetUserController` : liste de sociétés accessibles, création réservée à l’admin, création d’utilisateur et affectation de rôles.
- `AccountingDataController` : consulte les référentiels/historiques d’une société autorisée et expose le chargement démo uniquement en local/test ; `SeedDemoAccountingData` prépare le dataset sans lire Sage.
- `InvoiceController` : workspace global (première société accessible présélectionnée), historique société, upload/doublons, relance ciblée, téléchargement, dialogue de détails OCR et correction des données structurées. `StoreInvoiceUpload` gère stockage ; `ProcessInvoiceOcr`, `ExtractInvoiceData` et `AnalyzeAccountingProposal` orchestrent le workflow IA. `AccountingProposalController` borne les références société et gère correction/rejet/approbation humaine.
- `DashboardController` : sociétés visibles limitées au cabinet et aux rôles de l’utilisateur.
- `HandleInertiaRequests` : données authentifiées et cabinet partagé avec les pages Inertia.
- `resources/js/Pages` : adaptateurs/pages, dont `AccountingData/Show` et `Invoices/Index` ; `resources/js/Components/AppShell.tsx` : navigation globale et liens contextuels par société ; `ToastHost` / `RequestToastEvents` : retours de succès/erreur.
- Les formulaires d’action déclenchent un toast dans leurs callbacks `onSuccess`/`onError`, afin de produire un retour à chaque requête même si deux succès consécutifs ont le même libellé.

## Isolation multi-tenant

Chaque société appartient à un cabinet. Les listes admin partent de `cabinet_id`; les listes de membres partent de la relation many-to-many. Les affectations d’utilisateurs sont validées contre le cabinet de l’administrateur. Les lectures comptables et factures des Phases 2–5 utilisent les relations du `Company` autorisé ; le téléchargement/détail vérifient que la facture appartient à la société de la route. Le `company_id` de l’espace Factures est validé contre les sociétés accessibles avant toute requête. Les jobs IA sont limités au couple société/facture, et les comptes/journaux/tiers/axes de proposition sont revalidés côté serveur dans cette société.

## Décisions et compatibilité

1. **Rôles séparés par portée.** `cabinet_role` porte l’administration du cabinet ; le pivot `company_user_access` porte les droits société. Cela permet à un membre d’avoir des accès différents d’une société à l’autre.
2. **Données de société configurables.** Activité, secteur, matricule fiscal, code pays/devise et identifiant Sage externe sont séparés. Le jeu Phase 2 est explicitement fictif et n’est pas présenté comme un import Sage réel.
3. **Codes en chaînes et précision TND.** Les identifiants des comptes/axes/tiers/journaux conservent zéros initiaux et codes non numériques ; les montants utilisent trois décimales pour les millimes tunisiens.
4. **Migration depuis le premier MVP.** Les tables historiques `documents`/`accounting_entries` ont été créées dans le premier push et restent intactes. Les tables `invoices`/`invoice_lines` de la Phase 3 coexistent avec elles ; aucune donnée n’est copiée, réinterprétée ou supprimée silencieusement. Une migration de compatibilité rattache chaque ancien compte propriétaire à son propre cabinet afin de ne pas fusionner les espaces ; toute migration/suppression des anciennes tables exige une décision, une sauvegarde et un traitement explicites.
5. **Contexte fiscal.** Les données de démonstration reflètent un contexte tunisien et une devise TND, mais ne codent pas de règles fiscales ni de calculs de TVA non spécifiés.
6. **Aperçu Vite.** Il utilise des données fictives locales et n’est pas une API, une persistance de test, ni un traitement OCR.
7. **Nom d’interface.** « ComptaFlow » est le nom de travail du prototype ; le cahier des charges ne fournit pas de nom de marque officiel.

## Sécurité et exploitation

- Routes métier protégées par `auth`; policies et requêtes restent tenant-scoped.
- Les sources facture sont privées et téléchargées uniquement via une route authentifiée et limitée à une société autorisée ; les chemins n’apparaissent pas dans le navigateur. Les futures réponses externes devront suivre le même contrôle d’accès.
- Les jobs IA sont idempotents, journalisent les codes d’erreur sûrs, gèrent timeout/retry, conservent les réponses d’audit séparément et ne créent une écriture qu’après validation humaine.
- Ne jamais inclure de clé OCR.space, Mistral ou identifiants réels dans Git ; utiliser les variables d’environnement et garder les logs minimaux.
- Les tests DB utilisent SQLite en mémoire ; pour le lancement applicatif, configurer MySQL comme décrit dans le README.
- Le chargement du jeu Sage-like est bloqué hors des environnements `local` et `testing`; il ne doit jamais remplacer un import ou une restauration de données réelles.
