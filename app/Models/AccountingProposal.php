<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_id',
        'journal_id',
        'invoice_type',
        'journal_entry_id',
        'reviewed_by',
        'modified_by',
        'status',
        'model',
        'entry_description',
        'explanation',
        'raw_response',
        'usage',
        'warnings',
        'reviewed_at',
        'modified_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'invoice_id' => 'integer',
            'journal_id' => 'integer',
            'journal_entry_id' => 'integer',
            'reviewed_by' => 'integer',
            'modified_by' => 'integer',
            'raw_response' => 'array',
            'usage' => 'array',
            'warnings' => 'array',
            'reviewed_at' => 'datetime',
            'modified_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingProposalLine::class)->orderBy('line_number');
    }
}
