<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'cabinet_id',
        'name',
        'legal_name',
        'tax_identifier',
        'activity',
        'sector',
        'country_code',
        'currency',
        'sage_identifier',
    ];

    protected function casts(): array
    {
        return [
            'cabinet_id' => 'integer',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user_access')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function chartAccounts(): HasMany
    {
        return $this->hasMany(ChartAccount::class);
    }

    public function analyticalAccounts(): HasMany
    {
        return $this->hasMany(AnalyticalAccount::class);
    }

    public function thirdParties(): HasMany
    {
        return $this->hasMany(ThirdParty::class);
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function accountingProposals(): HasMany
    {
        return $this->hasMany(AccountingProposal::class);
    }
}
