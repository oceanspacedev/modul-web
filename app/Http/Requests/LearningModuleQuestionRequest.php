<?php

namespace App\Http\Requests;

use App\Models\LearningModuleQuestion;
use App\Support\QuizImage;
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
     * Pilihan ganda saja: minimal opsi A dan B (teks atau gambar), kunci harus menunjuk opsi yang terisi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string'],
            'option_a' => ['nullable', 'string'],
            'option_b' => ['nullable', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'option_e' => ['nullable', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d,e'],
            'explanation' => ['nullable', 'string'],
            'question_image' => QuizImage::fileRules(),
            'option_images' => ['nullable', 'array'],
            'option_images.*' => QuizImage::fileRules(),
            'remove_question_image' => ['nullable', 'boolean'],
            'remove_option_images' => ['nullable', 'array'],
            'remove_option_images.*' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $images = QuizImage::optionsWithImageAfterSave($this, $this->existingQuestion(), LearningModuleQuestion::OPTION_LETTERS);
                $filled = fn (string $letter) => filled($this->input('option_'.$letter)) || $images[$letter];

                foreach (['a', 'b'] as $letter) {
                    if (! $filled($letter)) {
                        $validator->errors()->add('option_'.$letter, 'Pilihan '.strtoupper($letter).' wajib diisi teks atau gambar.');
                    }
                }

                $key = $this->input('correct_answer');
                if (in_array($key, LearningModuleQuestion::OPTION_LETTERS, true) && ! $filled($key)) {
                    $validator->errors()->add('correct_answer', 'Kunci jawaban menunjuk pilihan '.strtoupper($key).' yang masih kosong.');
                }
            },
        ];
    }

    /**
     * Kolom soal tanpa gambar.
     *
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        return [
            ...array_fill_keys(['option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'explanation'], null),
            ...$this->safe()->only(['question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer', 'explanation']),
        ];
    }

    /**
     * Kolom soal beserta gambar yang sudah disimpan ke storage.
     *
     * @return array<string, mixed>
     */
    public function questionDataWithImages(): array
    {
        return [
            ...$this->questionData(),
            ...QuizImage::fromForm($this, $this->existingQuestion(), LearningModuleQuestion::OPTION_LETTERS),
        ];
    }

    private function existingQuestion(): ?LearningModuleQuestion
    {
        $question = $this->route('question');

        return $question instanceof LearningModuleQuestion ? $question : null;
    }
}
