<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntry extends Model
{
    protected $fillable = [
        'document_id',
        'entry_date',
        'description',
        'supplier_name',
        'invoice_number',
        'account_code',
        'subtotal',
        'vat_amount',
        'total_amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
