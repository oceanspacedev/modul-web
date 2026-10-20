<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
{
    /**
     * Authorization is handled by VideoPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'training_id' => ['nullable', 'integer', 'exists:trainings,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_type' => ['required', 'in:file,link'],
            'video_link' => ['exclude_unless:video_type,link', 'required', 'url', 'max:1000'],
            'video_file' => ['exclude_unless:video_type,file', $this->videoFileRequirement(), 'file', 'mimes:mp4,webm,ogg,mov,mkv', 'max:2097152'], // 2 GB
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing(['video_type' => 'file']);

        if ($this->filled('video_link')) {
            $this->merge(['video_link' => trim($this->input('video_link'))]);
        }
    }

    protected function videoFileRequirement(): string
    {
        return 'required';
    }
}
