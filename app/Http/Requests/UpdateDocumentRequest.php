<?php

namespace App\Http\Requests;

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
            'role_id' => ['required', 'integer', $this->validTargetRole()],
            'role_id.*' => [],
        ];
    }
}
