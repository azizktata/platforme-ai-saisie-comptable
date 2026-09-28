<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Models\Document;
use App\Services\PostDocumentToLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', Document::STATUS_NEEDS_REVIEW, Document::STATUS_POSTED], true)) {
            $status = 'all';
        }

        $documents = $request->user()->documents()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('supplier_name', 'like', "%{$search}%")
                        ->orWhere('invoice_number', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, [Document::STATUS_NEEDS_REVIEW, Document::STATUS_POSTED], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->setPath('/documents');

        return Inertia::render('Documents/Index', [
            'documents' => $documents->through(fn (Document $document): array => $this->summary($document)),
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function store(StoreDocumentRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('documents', 'local');

        if (! $path) {
            throw ValidationException::withMessages([
                'file' => 'Le document n’a pas pu être stocké. Réessayez.',
            ]);
        }

        $request->user()->documents()->create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'currency' => 'EUR',
            'status' => Document::STATUS_NEEDS_REVIEW,
        ]);

        return to_route('documents.index')->with('success', 'Document importé. Vérifiez les informations avant comptabilisation.');
    }

    public function show(Document $document): Response
    {
        $this->authorize('view', $document);

        return Inertia::render('Documents/Show', [
            'document' => $this->details($document),
        ]);
    }

    public function update(UpdateDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('update', $document);
        $document->update($request->validated());

        return back()->with('success', 'Les informations de la facture ont été enregistrées.');
    }

    public function post(PostDocumentRequest $request, Document $document, PostDocumentToLedger $postDocument): RedirectResponse
    {
        $this->authorize('post', $document);
        $postDocument->handle($document, $request->validated());

        return back()->with('success', 'L’écriture a été comptabilisée.');
    }

    public function file(Document $document): StreamedResponse
    {
        $this->authorize('view', $document);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
            'inline',
        );
    }

    /** @return array<string, mixed> */
    private function summary(Document $document): array
    {
        return [
            'id' => $document->id,
            'original_filename' => $document->original_filename,
            'supplier_name' => $document->supplier_name,
            'invoice_number' => $document->invoice_number,
            'invoice_date' => $document->invoice_date?->toDateString(),
            'total_amount' => $document->total_amount,
            'currency' => $document->currency,
            'status' => $document->status,
            'created_at' => $document->created_at?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function details(Document $document): array
    {
        return array_merge($this->summary($document), [
            'file_url' => $document->file_path ? route('documents.file', $document, false) : null,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'due_date' => $document->due_date?->toDateString(),
            'account_code' => $document->account_code,
            'description' => $document->description,
            'subtotal' => $document->subtotal,
            'vat_amount' => $document->vat_amount,
        ]);
    }
}
