<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    public const CABINET_ROLE_ADMIN = 'cabinet_admin';

    public const CABINET_ROLE_MEMBER = 'member';

    public const COMPANY_ROLE_INVOICE_MANAGER = 'invoice_manager';

    public const COMPANY_ROLE_USER = 'company_user';

    protected $fillable = [
        'cabinet_id',
        'cabinet_role',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'cabinet_id' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function cabinet(): BelongsTo
    {
        return $this->belongsTo(Cabinet::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user_access')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function isCabinetAdmin(): bool
    {
        return $this->cabinet_role === self::CABINET_ROLE_ADMIN;
    }

    public function hasCompanyAccess(Company $company): bool
    {
        if ($this->cabinet_id !== $company->cabinet_id) {
            return false;
        }

        return $this->isCabinetAdmin()
            || $this->companies()->whereKey($company->getKey())->exists();
    }

    public function companyRole(Company $company): ?string
    {
        if ($this->cabinet_id !== $company->cabinet_id) {
            return null;
        }

        if ($this->isCabinetAdmin()) {
            return self::CABINET_ROLE_ADMIN;
        }

        if ($company->pivot !== null && (int) $company->pivot->user_id === (int) $this->getKey()) {
            return $company->pivot->role;
        }

        return $this->companies()
            ->whereKey($company->getKey())
            ->first()?->pivot?->role;
    }
}
