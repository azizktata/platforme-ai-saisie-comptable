<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function view(User $user, Document $document): bool
    {
        return (int) $user->getKey() === (int) $document->user_id;
    }

    public function update(User $user, Document $document): bool
    {
        return $this->view($user, $document) && ! $document->isPosted();
    }

    public function post(User $user, Document $document): bool
    {
        // Repeated posting by the owner is safe: the service returns the existing entry.
        return $this->view($user, $document);
    }
}
