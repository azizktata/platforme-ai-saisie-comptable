<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadInvoiceFileRequest;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoices\StoreInvoiceUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(Request $request, Company $company): Response
    {
        $this->authorize('view', $company);

        $invoices = $company->invoices()
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
                'status',
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
                'status' => $invoice->status,
                'created_at' => $invoice->created_at->toIso8601String(),
                'download_url' => route('companies.invoices.download', [$company, $invoice]),
            ]);

        return Inertia::render('Invoices/Index', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'currency' => $company->currency ?? 'TND',
            ],
            'invoices' => $invoices,
            'canUploadInvoices' => $request->user()->can('manageInvoices', $company),
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

        return response()->json([
            'invoice' => [
                'id' => $invoice->id,
                'original_filename' => $invoice->original_filename,
                'status' => $invoice->status,
            ],
        ], 201);
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
}
