<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingProposalLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'accounting_proposal_id',
        'line_number',
        'chart_account_id',
        'third_party_id',
        'analytical_account_id',
        'description',
        'debit',
        'credit',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'accounting_proposal_id' => 'integer',
            'line_number' => 'integer',
            'chart_account_id' => 'integer',
            'third_party_id' => 'integer',
            'analytical_account_id' => 'integer',
            'debit' => 'decimal:3',
            'credit' => 'decimal:3',
            'confidence' => 'decimal:2',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(AccountingProposal::class, 'accounting_proposal_id');
    }

    public function chartAccount(): BelongsTo
    {
        return $this->belongsTo(ChartAccount::class);
    }

    public function thirdParty(): BelongsTo
    {
        return $this->belongsTo(ThirdParty::class);
    }

    public function analyticalAccount(): BelongsTo
    {
        return $this->belongsTo(AnalyticalAccount::class);
    }
}
