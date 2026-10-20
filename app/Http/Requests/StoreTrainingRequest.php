<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingRequest extends FormRequest
{
    /**
     * Authorization is handled by TrainingPolicy on the controller.
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
            'title' => ['required', 'string', 'max:255'],
            'trainer_id' => ['required', 'integer', 'exists:users,id'],
            'description' => ['nullable', 'string'],
            'training_date' => ['required', 'date'],
            'start_time' => ['required'],
            'end_time' => ['required'],
            'zoom_link' => ['required', 'string'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*' => ['integer', 'exists:users,id'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', 'exists:documents,id'],
            'send_wa_now' => ['nullable', 'boolean'],
            'quiz_mode' => ['nullable', 'in:formal,game'],
            'is_attendance_active' => ['nullable', 'boolean'],
            'require_attendance_proof' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Columns written to the trainings table.
     *
     * @return array<string, mixed>
     */
    public function trainingData(): array
    {
        return $this->safe()->only(['title', 'trainer_id', 'description', 'training_date', 'start_time', 'end_time', 'zoom_link']);
    }

    /**
     * @return array<int, int>
     */
    public function participantIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->validated('participants'))));
    }
}
