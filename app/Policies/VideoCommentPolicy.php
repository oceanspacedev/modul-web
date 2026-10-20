<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoComment;

class VideoCommentPolicy
{
    public function create(User $user): bool
    {
        return $user->checkPermissionTo('comment-videos');
    }

    /**
     * Authors may remove their own comments; administrators pass via Gate::before.
     */
    public function delete(User $user, VideoComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}
