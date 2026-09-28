<?php

namespace App\Services\Invoices;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreInvoiceUpload
{
    public function handle(Company $company, User $uploader, UploadedFile $file, string $sha256): Invoice
    {
        $path = $file->store("companies/{$company->id}/invoices", 'local');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The private invoice file could not be stored.');
        }

        try {
            return DB::transaction(fn (): Invoice => $company->invoices()->create([
                'uploaded_by' => $uploader->id,
                'storage_disk' => 'local',
                'file_path' => $path,
                'original_filename' => $this->originalFilename($file),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size_bytes' => $file->getSize(),
                'file_sha256' => $sha256,
                'status' => 'uploaded',
            ]));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);

            throw $exception;
        }
    }

    private function originalFilename(UploadedFile $file): string
    {
        $filename = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename) ?? '';
        $filename = trim($filename);

        if ($filename === '') {
            return 'facture';
        }

        return mb_substr($filename, 0, 255);
    }
}
