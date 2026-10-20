<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends StoreDocumentRequest
{
    /**
     * A new file is optional (it creates a new version); one document belongs to one job level.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'job_level_id' => ['required', 'integer', Rule::exists('job_levels', 'id')->withoutTrashed()],
            'job_level_id.*' => [],
        ];
    }
}
