<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVideoCommentRequest extends FormRequest
{
    /**
     * Authorization is handled by VideoCommentPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['comment' => trim((string) $this->input('comment'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:2', 'max:2000'],
            'parent_id' => ['nullable', 'integer', Rule::exists('video_comments', 'id')->where('video_id', $this->route('video')->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => 'Isi komentar / pertanyaan tidak boleh kosong.',
            'comment.min' => 'Komentar minimal 2 karakter.',
            'comment.max' => 'Komentar maksimal 2000 karakter.',
        ];
    }
}
