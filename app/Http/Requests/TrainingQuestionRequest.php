<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrainingQuestionRequest extends FormRequest
{
    /**
     * Authorization is handled by TrainingPolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['type' => $this->input('type') === 'essay' ? 'essay' : 'multiple_choice']);
    }

    /**
     * Essay questions have no options; multiple choice needs at least options A and B.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->input('type') === 'essay') {
            return [
                'type' => ['required', 'in:essay'],
                'question' => ['required', 'string'],
                'correct_answer' => ['nullable', 'string'],
                'explanation' => ['nullable', 'string'],
            ];
        }

        return [
            'type' => ['required', 'in:multiple_choice'],
            'question' => ['required', 'string'],
            'option_a' => ['required', 'string'],
            'option_b' => ['required', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'option_e' => ['nullable', 'string'],
            'option_f' => ['nullable', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d,e,f'],
            'explanation' => ['nullable', 'string'],
        ];
    }

    /**
     * Validated data with the option columns cleared for essay questions.
     *
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        $options = array_fill_keys(['option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f'], null);

        return [...$options, ...$this->validated()];
    }
}
