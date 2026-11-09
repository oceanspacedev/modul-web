<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Models\User;

/**
 * Modul Belajar biasa dikelola dengan manage-learning-modules; kuis milik video/dokumen dikelola oleh pengelola
 * video/dokumen tersebut.
 */
class LearningModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->checkPermissionTo('manage-learning-modules');
    }

    public function view(User $user, LearningModule $module): bool
    {
        return $this->manages($user, $module);
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
        return $this->manages($user, $module);
    }

    public function delete(User $user, LearningModule $module): bool
    {
        return ! $module->isOwned() && $this->manages($user, $module);
    }

    /**
     * Modul biasa: hanya yang dipublikasikan dan menyasar divisi serta jabatan user. Kuis video: setiap user login
     * (galeri video publik). Kuis dokumen: yang boleh melihat dokumen itu.
     */
    public function take(User $user, LearningModule $module): bool
    {
        if (! $module->isOwned()) {
            return $user->checkPermissionTo('view-learning-modules') && $module->isVisibleTo($user);
        }

        if (! $module->is_published) {
            return false;
        }

        return $module->isOwnedByVideo()
            || ($module->source instanceof Document && $user->can('view', $module->source));
    }

    private function manages(User $user, LearningModule $module): bool
    {
        return $user->checkPermissionTo(match ($module->source_type) {
            LearningModuleItem::TYPE_VIDEO => 'manage-videos',
            LearningModuleItem::TYPE_DOCUMENT => 'manage-documents',
            default => 'manage-learning-modules',
        });
    }
}
