<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinetActivity extends Model
{
    protected $fillable = [
        'cabinet_id',
        'name',
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
}
