<?php

namespace App\Http\Controllers;

use App\Exceptions\OcrProviderException;
use App\Http\Requests\BulkReviewInvoicesRequest;
use App\Http\Requests\SaveInvoiceExtractionRequest;
use App\Http\Requests\UploadInvoiceFileRequest;
use App\Jobs\AnalyzeAccountingProposal;
use App\Jobs\ExtractInvoiceData;
use App\Jobs\ProcessInvoiceOcr;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\InvoiceDataCompletenessChecker;
use App\Services\Invoices\InvoiceDataPersistence;
use App\Services\Invoices\StoreInvoiceUpload;
use App\Services\Ocr\InvoiceOcrSchema;
use App\Services\Ocr\InvoiceTotalsConsistencyChecker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function workspace(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorize('viewAny', Company::class);
        $companies = $this->accessibleCompanies($user);
        $requestedCompanyId = $request->query('company_id');
        $company = null;

        if ($requestedCompanyId !== null && $requestedCompanyId !== '') {
            abort_unless(is_string($requestedCompanyId) && ctype_digit($requestedCompanyId), 404);
            $company = $companies->firstWhere('id', (int) $requestedCompanyId);
            abort_unless($company instanceof Company, 404);
        } else {
            $company = $companies->first();
        }

        return Inertia::render('Invoices/Index', [
            'mode' => 'workspace',
            'ocrProvider' => (string) config('services.ocr.provider', 'ocr_space'),
            'maxUploadFileSizeBytes' => $this->maxUploadFileSizeBytes(),
            'companies' => $this->companyOptions($companies),
            'company' => $company ? $this->companySummary($company) : null,
            'invoices' => $company ? $this->invoicePaginator($company) : null,
            'canUploadInvoices' => $company !== null && $user->can('manageInvoices', $company),
            'canReviewInvoices' => $company !== null && $user->can('manageInvoices', $company),
        ]);
    }

    public function index(Company $company): Response
    {
        $this->authorize('view', $company);

        return Inertia::render('Invoices/Index', [
            'mode' => 'history',
            'ocrProvider' => (string) config('services.ocr.provider', 'ocr_space'),
            'maxUploadFileSizeBytes' => $this->maxUploadFileSizeBytes(),
            'companies' => [],
            'company' => $this->companySummary($company),
            'invoices' => $this->invoicePaginator($company),
            'canUploadInvoices' => false,
            'canReviewInvoices' => false,
        ]);
    }

    public function show(Company $company, Invoice $invoice): Response
    {
        $this->authorize('view', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        return Inertia::render('Invoices/Show', [
            'company' => $this->companySummary($company),
            'invoice' => [
                'id' => $invoice->id,
                'original_filename' => $invoice->original_filename,
                'mime_type' => $invoice->mime_type,
                'preview_url' => route('companies.invoices.preview', [$company, $invoice], false),
                'download_url' => route('companies.invoices.download', [$company, $invoice], false),
            ],
            'back_url' => route('invoices.index', ['company_id' => $company->id], false),
        ]);
    }

    public function upload(
        UploadInvoiceFileRequest $request,
        Company $company,
        StoreInvoiceUpload $storeInvoiceUpload,
    ): JsonResponse {
        /** @var UploadedFile $file */
        $file = $request->file('file');
        $realPath = $file->getRealPath();
        $sha256 = is_string($realPath) ? hash_file('sha256', $realPath) : false;

        if (! is_string($sha256)) {
            return response()->json(['message' => 'Le fichier n’a pas pu être vérifié. Réessayez.'], 500);
        }

        /** @var User $uploader */
        $uploader = $request->user();
        $storedInvoice = null;

        try {
            $result = DB::transaction(function () use ($company, $file, $request, $sha256, $storeInvoiceUpload, $uploader, &$storedInvoice): array {
                // Serialize same-company uploads so concurrent requests cannot silently bypass duplicate detection.
                $company->newQuery()->whereKey($company->getKey())->lockForUpdate()->firstOrFail();

                $duplicate = $company->invoices()
                    ->where('file_sha256', $sha256)
                    ->first(['id', 'original_filename']);

                if ($duplicate !== null && ! $request->boolean('confirm_duplicate')) {
                    return ['duplicate' => $duplicate, 'invoice' => null];
                }

                $storedInvoice = $storeInvoiceUpload->handle($company, $uploader, $file, $sha256);
                $storedInvoice->forceFill(['status' => 'ocr_queued'])->save();

                return [
                    'duplicate' => null,
                    'invoice' => $storedInvoice,
                ];
            });
        } catch (\Throwable) {
            if ($storedInvoice instanceof Invoice) {
                Storage::disk('local')->delete($storedInvoice->file_path);
            }

            Log::warning('Invoice upload failed during private storage or persistence.', [
                'company_id' => $company->id,
            ]);

            return response()->json(['message' => 'Le fichier n’a pas pu être enregistré. Vous pouvez réessayer sans perdre les autres fichiers.'], 500);
        }

        if ($result['duplicate'] instanceof Invoice) {
            return response()->json([
                'message' => 'Un fichier identique existe déjà pour cette société. Confirmez l’import si vous souhaitez le conserver également.',
                'duplicate' => [
                    'id' => $result['duplicate']->id,
                    'filename' => $result['duplicate']->original_filename,
                ],
            ], 409);
        }

        /** @var Invoice $invoice */
        $invoice = $result['invoice'];

        try {
            ProcessInvoiceOcr::dispatch($invoice->id, $company->id);
        } catch (OcrProviderException $exception) {
            $invoice->refresh();
            Log::warning('Invoice OCR failed during synchronous queue execution.', [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
                'error_code' => $exception->errorCode,
                'transport_error' => $exception->diagnostic,
            ]);
        } catch (\Throwable) {
            $this->markOcrQueueFailure($invoice);
            Log::warning('Invoice OCR job could not be dispatched.', [
                'invoice_id' => $invoice->id,
                'company_id' => $company->id,
            ]);
        }

        $invoice->refresh();

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'original_filename' => $invoice->original_filename,
                'status' => $invoice->status,
            ],
        ], 201);
    }

    public function retryOcr(Company $company, Invoice $invoice): RedirectResponse
    {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        $stage = DB::transaction(function () use ($company, $invoice): ?string {
            $lockedInvoice = Invoice::query()
                ->where('company_id', $company->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->first();

            if ($lockedInvoice === null) {
                return null;
            }

            $stage = match ($lockedInvoice->status) {
                'uploaded', 'ocr_failed' => 'ocr',
                'data_extraction_failed' => 'data_extraction',
                'accounting_analysis_failed' => 'accounting_analysis',
                default => null,
            };

            if ($stage === null) {
                return null;
            }

            $lockedInvoice->forceFill([
                'status' => match ($stage) {
                    'ocr' => 'ocr_queued',
                    'data_extraction' => 'data_extraction',
                    default => 'accounting_analysis',
                },
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();

            return $stage;
        });

        if ($stage === null) {
            return back()->withErrors(['processing' => 'Aucune étape échouée ne peut être relancée pour cette facture.']);
        }

        try {
            match ($stage) {
                'ocr' => ProcessInvoiceOcr::dispatch($invoice->id, $company->id),
                'data_extraction' => ExtractInvoiceData::dispatch($invoice->id, $company->id),
                default => AnalyzeAccountingProposal::dispatch($invoice->id, $company->id),
            };
        } catch (\Throwable) {
            $this->markStageQueueFailure($company, $invoice, $stage);

            return back()->withErrors(['processing' => 'La relance du traitement n’a pas pu être planifiée. Réessayez plus tard.']);
        }

        return to_route('invoices.index', ['company_id' => $company->id])
            ->with('success', 'L’étape du traitement a été relancée.');
    }

    public function rerunExtraction(Company $company, Invoice $invoice): RedirectResponse
    {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        $canExtract = DB::transaction(function () use ($company, $invoice): bool {
            $lockedInvoice = $company->invoices()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (trim((string) $lockedInvoice->ocr_text) === '') {
                throw ValidationException::withMessages([
                    'extraction' => 'Aucun texte OCR n’est disponible. Lancez d’abord le traitement OCR du document.',
                ]);
            }

            if (in_array($lockedInvoice->status, [
                'ocr_queued',
                'ocr_processing',
                'data_extraction',
                'accounting_analysis',
                'accounting_validated',
            ], true) || $lockedInvoice->journalEntry()->exists()) {
                throw ValidationException::withMessages([
                    'extraction' => 'L’extraction ne peut pas être relancée pendant un traitement en cours ou après validation comptable.',
                ]);
            }

            $lockedInvoice->forceFill([
                'status' => 'data_extraction',
                'ocr_failed_at' => null,
                'ocr_error_code' => null,
                'ocr_error_message' => null,
            ])->save();

            return true;
        });

        if (! $canExtract) {
            return back()->withErrors(['extraction' => 'L’extraction n’a pas pu être planifiée.']);
        }

        try {
            ExtractInvoiceData::dispatch($invoice->id, $company->id);
        } catch (\Throwable) {
            $this->markStageQueueFailure($company, $invoice, 'data_extraction');

            return back()->withErrors(['extraction' => 'L’extraction n’a pas pu être planifiée. Vous pouvez réessayer.']);
        }

        return back()->with('success', 'L’extraction a été relancée à partir du texte OCR existant.');
    }

    public function details(Company $company, Invoice $invoice, InvoiceOcrSchema $schema): JsonResponse
    {
        $this->authorize('view', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        $invoice->load(['lines', 'accountingProposal.lines.chartAccount', 'accountingProposal.lines.thirdParty', 'accountingProposal.lines.analyticalAccount', 'accountingProposal.journal', 'accountingProposal.journalEntry']);
        $invoiceData = $this->currentInvoiceData($invoice, $schema);
        $proposal = $invoice->accountingProposal;
        $user = request()->user();

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'status' => $invoice->status,
                'original_filename' => $invoice->original_filename,
                'invoice_number' => $invoice->invoice_number,
                'ocr_text' => $invoice->ocr_text,
                'ocr_display_text' => $invoice->ocr_text ?? ($invoice->extraction_model === null && str_starts_with((string) $invoice->ocr_model, 'ocr.space-engine-') ? $invoice->description : null),
                'ocr_data' => $invoiceData,
                'ocr_warnings' => $invoice->ocr_warnings ?? [],
                'ocr_error_message' => $invoice->ocr_error_message,
                'ocr_model' => $invoice->ocr_model,
                'extraction_provider' => (string) config('services.invoice_extraction.provider', 'openrouter'),
                'extraction_model' => $invoice->extraction_model,
                'extraction_corrected_at' => $invoice->extraction_corrected_at?->toIso8601String(),
            ],
            'proposal' => $proposal === null ? null : [
                'id' => $proposal->id,
                'status' => $proposal->status,
                'journal_id' => $proposal->journal_id,
                'journal_code' => $proposal->journal?->code,
                'journal_label' => $proposal->journal?->label,
                'entry_description' => $proposal->entry_description,
                'explanation' => $proposal->explanation,
                'warnings' => $proposal->warnings ?? [],
                'model' => $proposal->model,
                'modified_at' => $proposal->modified_at?->toIso8601String(),
                'journal_entry_id' => $proposal->journal_entry_id,
                'lines' => $proposal->lines->map(fn ($line): array => [
                    'id' => $line->id,
                    'chart_account_id' => $line->chart_account_id,
                    'chart_account_code' => $line->chartAccount?->code,
                    'chart_account_label' => $line->chartAccount?->label,
                    'third_party_id' => $line->third_party_id,
                    'third_party_code' => $line->thirdParty?->code,
                    'third_party_name' => $line->thirdParty?->name,
                    'analytical_account_id' => $line->analytical_account_id,
                    'analytical_account_code' => $line->analyticalAccount?->code,
                    'analytical_account_label' => $line->analyticalAccount?->label,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                    'confidence' => $line->confidence,
                ])->values()->all(),
            ],
            'options' => [
                'chart_accounts' => $company->chartAccounts()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label', 'account_type'])->toArray(),
                'journals' => $company->journals()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label', 'journal_type'])->toArray(),
                'third_parties' => $company->thirdParties()->where('is_active', true)->whereIn('party_type', ['supplier', 'both'])->orderBy('code')->get(['id', 'code', 'name'])->toArray(),
                'analytical_accounts' => $company->analyticalAccounts()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'label'])->toArray(),
            ],
            'can_manage' => $user?->can('manageInvoices', $company) === true,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function saveExtraction(
        SaveInvoiceExtractionRequest $request,
        Company $company,
        Invoice $invoice,
        InvoiceOcrSchema $schema,
        InvoiceDataCompletenessChecker $completenessChecker,
        InvoiceDataPersistence $persistence,
        InvoiceTotalsConsistencyChecker $totalsChecker,
    ): RedirectResponse {
        $this->authorize('manageInvoices', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);

        try {
            $invoiceData = $schema->validate($request->validated('invoice_data'));
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'invoice_data' => 'Les champs de facture ne respectent pas le format attendu. Vérifiez les dates, les montants et les lignes.',
            ]);
        }

        $missingFields = $completenessChecker->missingFields($invoiceData);
        $warnings = array_values(array_unique([
            ...$totalsChecker->warnings($invoiceData),
            ...$missingFields,
        ]));
        $reviewer = $request->user();

        DB::transaction(function () use ($company, $invoice, $invoiceData, $missingFields, $warnings, $persistence, $reviewer): void {
            $lockedInvoice = $company->invoices()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedInvoice->status, [
                'ocr_completed',
                'invoice_incomplete',
                'data_extraction_failed',
                'accounting_analysis_failed',
                'proposal_ready',
                'proposal_rejected',
            ], true) || $lockedInvoice->journalEntry()->exists()) {
                throw ValidationException::withMessages([
                    'invoice_data' => 'Les données de cette facture ne peuvent plus être modifiées à cette étape.',
                ]);
            }

            $proposal = $lockedInvoice->accountingProposal;

            if ($proposal !== null) {
                if ($proposal->status === 'approved' || $proposal->journal_entry_id !== null) {
                    throw ValidationException::withMessages([
                        'invoice_data' => 'Une proposition validée ne peut plus être modifiée.',
                    ]);
                }

                if ($proposal->status === 'ready') {
                    $proposal->forceFill(['status' => 'superseded'])->save();
                }
            }

            $persistence->store($lockedInvoice, $invoiceData);
            $lockedInvoice->forceFill([
                'ocr_warnings' => $warnings,
                'extraction_corrected_at' => now(),
                'extraction_corrected_by' => $reviewer->id,
                'ocr_error_code' => $missingFields === [] ? null : 'invoice_data_incomplete',
                'ocr_error_message' => $missingFields === []
                    ? null
                    : 'Les données obligatoires de la facture sont incomplètes. Corrigez les champs signalés avant l’analyse comptable.',
                'status' => $missingFields === [] ? 'accounting_analysis' : 'invoice_incomplete',
            ])->save();
        });

        if ($missingFields === []) {
            try {
                AnalyzeAccountingProposal::dispatch($invoice->id, $company->id);
            } catch (\Throwable) {
                $this->markStageQueueFailure($company, $invoice, 'accounting_analysis');

                return back()->withErrors(['processing' => 'Les données sont enregistrées, mais l’analyse comptable n’a pas pu être planifiée. Vous pouvez la relancer.']);
            }

            return back()->with('success', 'Les données sont enregistrées. L’analyse comptable démarre automatiquement.');
        }

        return back()->with('success', 'Les corrections sont enregistrées. Complétez les champs obligatoires avant l’analyse comptable.');
    }

    public function bulkReview(BulkReviewInvoicesRequest $request, Company $company): RedirectResponse
    {
        $invoiceIds = array_values(array_unique(array_map('intval', $request->validated('invoice_ids'))));
        /** @var User $reviewer */
        $reviewer = $request->user();
        $reviewedAt = now();

        DB::transaction(function () use ($company, $invoiceIds, $reviewer, $reviewedAt): void {
            $invoices = Invoice::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get();

            if ($invoices->count() !== count($invoiceIds)) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Certaines factures ne sont pas accessibles dans cette société.',
                ]);
            }

            if ($invoices->contains(fn (Invoice $invoice): bool => $invoice->status !== 'ocr_completed')) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Seules les extractions OCR terminées peuvent être marquées comme vérifiées.',
                ]);
            }

            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'ocr_reviewed_at' => $reviewedAt,
                    'ocr_reviewed_by' => $reviewer->id,
                ])->save();
            }
        });

        return to_route('invoices.index', ['company_id' => $company->id]);
    }

    public function preview(Company $company, Invoice $invoice): StreamedResponse
    {
        $this->authorize('view', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);
        abort_unless($invoice->storage_disk === 'local', 404);
        abort_unless(Storage::disk('local')->exists($invoice->file_path), 404);

        return Storage::disk('local')->response(
            $invoice->file_path,
            $invoice->original_filename,
            [
                'Content-Type' => $invoice->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
            'inline',
        );
    }

    public function download(Company $company, Invoice $invoice): StreamedResponse
    {
        $this->authorize('view', $company);
        abort_unless((int) $invoice->company_id === (int) $company->id, 404);
        abort_unless($invoice->storage_disk === 'local', 404);
        abort_unless(Storage::disk('local')->exists($invoice->file_path), 404);

        return Storage::disk('local')->download(
            $invoice->file_path,
            $invoice->original_filename,
            [
                'Content-Type' => $invoice->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    private function maxUploadFileSizeBytes(): int
    {
        return config('services.ocr.provider') === 'ocr_space'
            ? max(1, (int) config('services.ocr_space.max_file_size_bytes', 1024 * 1024))
            : 20 * 1024 * 1024;
    }

    private function accessibleCompanies(User $user): Collection
    {
        return $user->isCabinetAdmin()
            ? $user->cabinet->companies()->orderBy('name')->get()
            : $user->companies()->where('companies.cabinet_id', $user->cabinet_id)->orderBy('name')->get();
    }

    private function companyOptions(Collection $companies): array
    {
        return $companies->map(fn (Company $company): array => [
            'id' => $company->id,
            'name' => $company->name,
        ])->values()->all();
    }

    private function companySummary(Company $company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'currency' => $company->currency ?? 'TND',
        ];
    }

    private function invoicePaginator(Company $company): LengthAwarePaginator
    {
        return $company->invoices()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, [
                'id',
                'company_id',
                'original_filename',
                'size_bytes',
                'supplier_name',
                'invoice_number',
                'invoice_date',
                'total_amount',
                'currency',
                'description',
                'status',
                'ocr_attempts',
                'ocr_error_message',
                'ocr_warnings',
                'ocr_reviewed_at',
                'created_at',
            ])
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'original_filename' => $invoice->original_filename,
                'size_bytes' => $invoice->size_bytes,
                'supplier_name' => $invoice->supplier_name,
                'invoice_number' => $invoice->invoice_number,
                'invoice_date' => $invoice->invoice_date?->toDateString(),
                'total_amount' => $invoice->total_amount,
                'currency' => $invoice->currency,
                'description' => $invoice->description,
                'status' => $invoice->status,
                'ocr_attempts' => $invoice->ocr_attempts,
                'ocr_error_message' => $invoice->ocr_error_message,
                'ocr_warnings' => $invoice->ocr_warnings ?? [],
                'ocr_reviewed_at' => $invoice->ocr_reviewed_at?->toIso8601String(),
                'created_at' => $invoice->created_at->toIso8601String(),
                'download_url' => route('companies.invoices.download', [$company, $invoice], false),
            ]);
    }

    private function markOcrQueueFailure(Invoice $invoice): void
    {
        $this->markStageQueueFailure($invoice->company, $invoice, 'ocr');
    }

    private function markStageQueueFailure(Company $company, Invoice $invoice, string $stage): void
    {
        $status = match ($stage) {
            'data_extraction' => 'data_extraction_failed',
            'accounting_analysis' => 'accounting_analysis_failed',
            default => 'ocr_failed',
        };
        $message = match ($stage) {
            'data_extraction' => 'L’extraction des données n’a pas pu être planifiée. Vous pouvez la relancer.',
            'accounting_analysis' => 'L’analyse comptable n’a pas pu être planifiée. Vous pouvez la relancer.',
            default => 'Le traitement OCR n’a pas pu être planifié. Vous pouvez le relancer.',
        };

        DB::transaction(function () use ($company, $invoice, $stage, $status, $message): void {
            $lockedInvoice = Invoice::query()
                ->where('company_id', $company->id)
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->first();

            $expectedStatus = match ($stage) {
                'data_extraction' => 'data_extraction',
                'accounting_analysis' => 'accounting_analysis',
                default => 'ocr_queued',
            };

            if ($lockedInvoice === null || $lockedInvoice->status !== $expectedStatus) {
                return;
            }

            $lockedInvoice->forceFill([
                'status' => $status,
                'ocr_failed_at' => now(),
                'ocr_error_code' => 'queue_unavailable',
                'ocr_error_message' => $message,
            ])->save();
        });
    }

    /** @return array<string, mixed> */
    private function currentInvoiceData(Invoice $invoice, InvoiceOcrSchema $schema): array
    {
        if (is_array($invoice->ocr_data) && isset($invoice->ocr_data['lines']) && is_array($invoice->ocr_data['lines'])) {
            $emptyData = $schema->emptyAnnotation();
            $data = array_replace($emptyData, array_intersect_key($invoice->ocr_data, $emptyData));
            $lineFields = [
                'reference' => null,
                'description' => null,
                'quantity' => null,
                'unit_price' => null,
                'discount_amount' => null,
                'subtotal' => null,
                'vat_rate' => null,
                'vat_amount' => null,
                'fodec_rate' => null,
                'fodec_amount' => null,
                'other_tax_amount' => null,
                'total_amount' => null,
            ];
            $data['lines'] = array_values(array_map(
                fn ($line): array => array_replace(
                    $lineFields,
                    is_array($line) ? array_intersect_key($line, $lineFields) : [],
                ),
                $invoice->ocr_data['lines'],
            ));

            return $data;
        }

        $data = $schema->emptyAnnotation();

        foreach (array_keys($data) as $field) {
            if ($field === 'lines') {
                continue;
            }

            $value = $invoice->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            } elseif ($value !== null && in_array($field, [
                'vat_rate', 'fodec_rate', 'subtotal', 'vat_amount', 'fodec_amount',
                'other_tax_amount', 'stamp_amount', 'withholding_rate', 'withholding_amount', 'total_amount',
                'total_discount_amount', 'net_to_pay_amount',
            ], true)) {
                $value = (string) $value;
            }

            $data[$field] = $value;
        }

        $data['lines'] = $invoice->lines->map(fn ($line): array => [
            'reference' => $line->reference,
            'description' => $line->description,
            'quantity' => $line->quantity,
            'unit_price' => $line->unit_price,
            'discount_amount' => $line->discount_amount,
            'subtotal' => $line->subtotal,
            'vat_rate' => $line->vat_rate,
            'vat_amount' => $line->vat_amount,
            'fodec_rate' => $line->fodec_rate,
            'fodec_amount' => $line->fodec_amount,
            'other_tax_amount' => $line->other_tax_amount,
            'total_amount' => $line->total_amount,
        ])->values()->all();

        return $data;
    }
}
