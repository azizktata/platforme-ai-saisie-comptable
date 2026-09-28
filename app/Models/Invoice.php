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
        'customer_name',
        'customer_tax_identifier',
        'invoice_number',
        'purchase_order_reference',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal',
        'vat_amount',
        'fodec_amount',
        'other_tax_amount',
        'stamp_amount',
        'withholding_rate',
        'withholding_amount',
        'total_amount',
        'payment_terms',
        'bank_name',
        'bank_account_reference',
        'description',
        'status',
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
            'subtotal' => 'decimal:3',
            'vat_amount' => 'decimal:3',
            'fodec_amount' => 'decimal:3',
            'other_tax_amount' => 'decimal:3',
            'stamp_amount' => 'decimal:3',
            'withholding_rate' => 'decimal:3',
            'withholding_amount' => 'decimal:3',
            'total_amount' => 'decimal:3',
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

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }
}
