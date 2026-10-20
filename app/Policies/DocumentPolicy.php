<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('view-documents') || $user->checkPermissionTo('manage-documents');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-documents');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->checkPermissionTo('manage-documents');
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->checkPermissionTo('manage-documents');
    }

    public function restore(User $user, Document $document): bool
    {
        return $user->checkPermissionTo('manage-documents');
    }
}
