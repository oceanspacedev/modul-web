<?php

namespace App\Policies;

use App\Models\Training;
use App\Models\User;

class TrainingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage-trainings');
    }

    public function view(User $user, Training $training): bool
    {
        return $user->checkPermissionTo('manage-trainings');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-trainings');
    }

    /**
     * Covers every management action on a training: participants, quiz, attendance, WhatsApp, Zoom.
     */
    public function update(User $user, Training $training): bool
    {
        return $user->checkPermissionTo('manage-trainings');
    }

    public function delete(User $user, Training $training): bool
    {
        return $user->checkPermissionTo('manage-trainings');
    }
}
