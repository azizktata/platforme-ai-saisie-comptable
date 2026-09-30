<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'third_party_id',
        'uploaded_by',
        'storage_disk',
        'file_path',
        'original_filename',
        'mime_type',
        'size_bytes',
        'file_sha256',
        'supplier_name',
        'supplier_tax_identifier',
        'supplier_address',
        'supplier_phone',
        'supplier_mobile',
        'supplier_email',
        'customer_name',
        'customer_tax_identifier',
        'customer_reference',
        'customer_address',
        'customer_phone',
        'invoice_number',
        'purchase_order_reference',
        'invoice_date',
        'due_date',
        'currency',
        'vat_rate',
        'fodec_rate',
        'subtotal',
        'vat_amount',
        'fodec_amount',
        'other_tax_amount',
        'stamp_amount',
        'withholding_rate',
        'withholding_amount',
        'total_amount',
        'total_discount_amount',
        'net_to_pay_amount',
        'payment_method',
        'payment_terms',
        'bank_name',
        'bank_account_reference',
        'description',
        'status',
        'ocr_response',
        'ocr_text',
        'extraction_response',
        'extraction_model',
        'extraction_usage',
        'extraction_corrected_at',
        'extraction_corrected_by',
        'ocr_data',
        'ocr_usage',
        'ocr_warnings',
        'ocr_model',
        'ocr_attempts',
        'ocr_started_at',
        'ocr_completed_at',
        'ocr_failed_at',
        'ocr_error_code',
        'ocr_error_message',
        'ocr_reviewed_at',
        'ocr_reviewed_by',
        'accounting_exported_at',
        'accounting_exported_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'third_party_id' => 'integer',
            'uploaded_by' => 'integer',
            'size_bytes' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'vat_rate' => 'decimal:3',
            'fodec_rate' => 'decimal:3',
            'subtotal' => 'decimal:3',
            'vat_amount' => 'decimal:3',
            'fodec_amount' => 'decimal:3',
            'other_tax_amount' => 'decimal:3',
            'stamp_amount' => 'decimal:3',
            'withholding_rate' => 'decimal:3',
            'withholding_amount' => 'decimal:3',
            'total_amount' => 'decimal:3',
            'total_discount_amount' => 'decimal:3',
            'net_to_pay_amount' => 'decimal:3',
            'ocr_response' => 'array',
            'extraction_response' => 'array',
            'extraction_usage' => 'array',
            'ocr_data' => 'array',
            'ocr_usage' => 'array',
            'ocr_warnings' => 'array',
            'ocr_attempts' => 'integer',
            'ocr_started_at' => 'datetime',
            'ocr_completed_at' => 'datetime',
            'ocr_failed_at' => 'datetime',
            'ocr_reviewed_at' => 'datetime',
            'ocr_reviewed_by' => 'integer',
            'extraction_corrected_by' => 'integer',
            'extraction_corrected_at' => 'datetime',
            'accounting_exported_at' => 'datetime',
            'accounting_exported_by' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function ocrReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ocr_reviewed_by');
    }

    public function extractionCorrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extraction_corrected_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }

    public function accountingProposal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(AccountingProposal::class)->latestOfMany();
    }

    public function journalEntry(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }
}
