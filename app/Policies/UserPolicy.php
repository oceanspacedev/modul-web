<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage-users');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-users');
    }

    public function update(User $user, User $model): bool
    {
        return $user->checkPermissionTo('manage-users');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->checkPermissionTo('manage-users') && ! $user->is($model);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->checkPermissionTo('manage-users');
    }
}
