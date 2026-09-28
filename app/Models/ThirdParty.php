<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThirdParty extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'code',
        'sage_identifier',
        'party_type',
        'name',
        'tax_identifier',
        'payables_account_id',
        'receivables_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'payables_account_id' => 'integer',
            'receivables_account_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function payablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'payables_account_id');
    }

    public function receivablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class, 'receivables_account_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
