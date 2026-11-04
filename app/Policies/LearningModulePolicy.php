<?php

namespace App\Policies;

use App\Models\LearningModule;
use App\Models\User;

class LearningModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    public function view(User $user, LearningModule $module): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    /**
     * Covers every management action on a module: materials, questions, publishing, results.
     */
    public function update(User $user, LearningModule $module): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    public function delete(User $user, LearningModule $module): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    /**
     * Participants only see published modules aimed at their division and job title.
     */
    public function take(User $user, LearningModule $module): bool
    {
        return $user->checkPermissionTo('view-learning-modules') && $module->isVisibleTo($user);
    }
}
