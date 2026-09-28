<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Document extends Model
{
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_POSTED = 'posted';

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'mime_type',
        'size_bytes',
        'supplier_name',
        'invoice_number',
        'invoice_date',
        'due_date',
        'currency',
        'account_code',
        'description',
        'subtotal',
        'vat_amount',
        'total_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'size_bytes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountingEntry(): HasOne
    {
        return $this->hasOne(AccountingEntry::class);
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }
}
