<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_number',
        'reference',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'fodec_rate',
        'fodec_amount',
        'other_tax_amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'line_number' => 'integer',
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'discount_amount' => 'decimal:3',
            'subtotal' => 'decimal:3',
            'vat_rate' => 'decimal:3',
            'vat_amount' => 'decimal:3',
            'fodec_rate' => 'decimal:3',
            'fodec_amount' => 'decimal:3',
            'other_tax_amount' => 'decimal:3',
            'total_amount' => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
