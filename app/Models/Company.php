<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}
