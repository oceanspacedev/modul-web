<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LearningModuleQuestionRequest extends FormRequest
{
    /**
     * Authorization is handled by LearningModulePolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Pilihan ganda saja: minimal opsi A dan B, kunci harus menunjuk opsi yang terisi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string'],
            'option_a' => ['required', 'string'],
            'option_b' => ['required', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'option_e' => ['nullable', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d,e'],
            'explanation' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $key = $this->input('correct_answer');
                if ($key && blank($this->input('option_'.$key))) {
                    $validator->errors()->add('correct_answer', 'Kunci jawaban menunjuk pilihan '.strtoupper($key).' yang masih kosong.');
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        return [
            ...array_fill_keys(['option_c', 'option_d', 'option_e', 'explanation'], null),
            ...$this->validated(),
        ];
    }
}
