<?php

namespace App\Http\Requests;

class UpdateVideoRequest extends StoreVideoRequest
{
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['video_type' => $this->route('video')->video_type]);

        parent::prepareForValidation();
    }

    /**
     * Keeping the existing file is allowed when updating.
     */
    protected function videoFileRequirement(): string
    {
        return 'nullable';
    }
}
