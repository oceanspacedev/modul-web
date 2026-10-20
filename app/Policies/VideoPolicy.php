<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function create(User $user): bool
    {
        return $user->checkPermissionTo('manage-videos');
    }

    public function update(User $user, Video $video): bool
    {
        return $user->checkPermissionTo('manage-videos');
    }

    public function delete(User $user, Video $video): bool
    {
        return $user->checkPermissionTo('manage-videos');
    }
}
