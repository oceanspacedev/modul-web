<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Ubah dengan AI" untuk satu soal di layar tinjauan hasil AI (kuis pelatihan & modul).
 */
class ReviseAiQuestionRequest extends FormRequest
{
    /**
     * Authorization is handled by the policy on the controller.
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
            'part' => ['required', 'in:question,answers,all'],
            'instruction' => ['nullable', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'question' => ['required', 'array'],
            'question.type' => ['nullable', 'in:multiple_choice,essay'],
            'question.question' => ['required', 'string'],
            'question.option_a' => ['nullable', 'string'],
            'question.option_b' => ['nullable', 'string'],
            'question.option_c' => ['nullable', 'string'],
            'question.option_d' => ['nullable', 'string'],
            'question.option_e' => ['nullable', 'string'],
            'question.correct_answer' => ['nullable', 'string'],
            'question.explanation' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'question.question.required' => 'Pertanyaan wajib diisi sebelum diubah dengan AI.',
        ];
    }
}
