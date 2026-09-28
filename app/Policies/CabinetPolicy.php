<?php

namespace App\Policies;

use App\Models\Cabinet;
use App\Models\User;

class CabinetPolicy
{
    public function view(User $user, Cabinet $cabinet): bool
    {
        return $user->cabinet_id === $cabinet->id;
    }

    public function manageUsers(User $user, Cabinet $cabinet): bool
    {
        return $this->view($user, $cabinet) && $user->isCabinetAdmin();
    }
}
