# Repository context

## Project

ComptaFlow is a Laravel application for multi-cabinet, multi-company supplier invoice processing and human-reviewed accounting proposals. The customer requirements in `docs/requirements.md` are the product source of truth. `README.md` explains setup and current delivery status; `docs/architecture.md` and the module docs under `docs/modules/` give more detailed decisions.

## Stack and entry points

- Backend: PHP 8.3+, Laravel 13, Eloquent, database queue; MySQL in normal use and in-memory SQLite for tests.
- Frontend: Inertia 2, React 19, TypeScript, Vite, Tailwind CSS 4.
- Laravel bootstrapping/routing: `bootstrap/app.php`, `routes/web.php`, `routes/console.php`.
- Inertia entry: `resources/js/app.tsx`; pages under `resources/js/Pages/`, shared UI under `resources/js/Components/`.
- Backend areas: `app/Http/Controllers`, `app/Http/Requests`, `app/Policies`, `app/Models`, `app/Services`, `app/Jobs`.
- Database schema and sample data: `database/migrations`, `database/seeders`, `database/factories`.
- the .env is not permissable for the AI model to read, it have sensible security keys, you can see .env.example instead

## Domain and security invariants

- A `Cabinet` owns its `Company` records. Users have a cabinet-level role; `company_user_access` grants non-admin access per company. `CompanyPolicy` is the key authorization boundary.
- Scope invoice, accounting-reference, and proposal reads/writes to the authorized company. Jobs carry both `invoiceId` and `companyId`; keep both constraints when loading records.
- Invoice files are private on Laravel's `local` disk. Do not expose storage paths or add public file URLs; use the authorized preview/download controller routes.
- Provider keys stay server-side in environment/config. Never commit keys or disable TLS verification.
- AI output is a draft. Creating a journal entry requires an explicit authorized approval action; do not silently post entries or alter extracted monetary values.
- Preserve raw OCR/extraction/proposal provider results and model/usage metadata where the existing flow does so.
- Monetary database precision supports Tunisian millimes (`DECIMAL(18,3)`). Do not round to two decimals without a domain requirement.
- Demo accounting data is fictitious and must remain limited to `local`/`testing`; there is no live Sage import/export integration.
- TLS CA bundles (`OCR_SPACE_CA_BUNDLE`, `MISTRAL_CA_BUNDLE`, `OPENROUTER_CA_BUNDLE`) must not be set to `false`; on Windows or servers without a system CA, provide an absolute PEM path in `.env`. Never define `verify=false`.

## Invoice processing flow

1. `InvoiceController` validates access and delegates upload storage/deduplication to `StoreInvoiceUpload`; accepted uploads queue `ProcessInvoiceOcr`.
2. `ProcessInvoiceOcr` calls the `OcrProvider` contract. OCR.space is the default provider; Mistral is an optional provider that can return structured invoice data.
3. For OCR.space text, `ExtractInvoiceData` structures the transcription through OpenRouter using the invoice JSON schema. `InvoiceDataPersistence`, completeness checks, and total consistency checks normalize/validate before saving.
4. Complete invoice data queues `AnalyzeAccountingProposal`, which calls `AccountingProposalService` with company-scoped accounting context. Proposals and lines are persisted as reviewable versions.
5. Authorized users edit, regenerate, reject, or explicitly approve a proposal through `AccountingProposalController`. Only approval creates the linked journal entry.

The three jobs use the `database` connection and `ocr` queue, with uniqueness/retries and status checks. Respect the existing invoice state machine and transaction/locking patterns when changing this workflow. Status names, error translation, and retry UI are coupled across jobs, controllers, and `InvoiceReviewPanel`.

## Implementation guidance

- Read the relevant module PRD/README and existing feature tests before changing behavior. Tests under `tests/Feature/` document tenant isolation, roles, queue flow, provider fakes, and approval rules.
- Put request validation in Form Requests, authorization in policies/controllers, and reusable business rules in services. Keep external provider calls behind the existing provider/client abstractions.
- Maintain the existing Laravel/Inertia patterns and use named routes rather than hard-coded URLs where practical.
- Add/modify migrations for schema changes; preserve historical tables and data unless a documented migration explicitly says otherwise.
- Do not add dependencies without a concrete need. Do not put secrets or real customer invoice data in fixtures, logs, or docs.

## Useful commands

```sh
npm run dev
npm run build
php artisan serve
php artisan queue:work database --queue=ocr --tries=3 --timeout=210
php artisan migrate --seed
composer test
```

Queue configuration matters: `DB_QUEUE_RETRY_AFTER` must exceed the job timeout (currently up to 210 seconds). Restart the queue worker after changing `.env`. `composer test` requires the PHP/Composer runtime described in `README.md`.

## Known product boundaries

- Current implemented application scope is described as Phases 1–5 in README/docs; verify actual code/tests before assuming a feature is complete.
- OCR.space Free has provider file/page limits; Mistral can be selected with configuration. OpenRouter extraction and accounting-analysis models are configured separately.
- Fiscal rules, expert-accountant learning/memory, credit-note handling, and real Sage synchronization remain out of scope or incomplete; do not invent behavior for them.
