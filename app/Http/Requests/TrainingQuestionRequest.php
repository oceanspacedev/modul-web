<?php

namespace App\Http\Requests;

use App\Models\TrainingQuestion;
use App\Support\QuizImage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
     * The question may be text, an image, or both. Essay questions have no options; multiple choice
     * needs at least options A and B (text or image).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $questionImage = [
            'question_image' => QuizImage::fileRules(),
            'remove_question_image' => ['nullable', 'boolean'],
            // Hanya dikirim form pelatihan yang punya pretest dan posttest; tanpa field ini pilihan lama dipertahankan.
            'used_in' => ['sometimes', 'required', 'in:'.implode(',', array_keys(TrainingQuestion::USED_IN))],
        ];

        if ($this->isEssay()) {
            return [
                'type' => ['required', 'in:essay'],
                'question' => ['nullable', 'string'],
                'correct_answer' => ['nullable', 'string'],
                'explanation' => ['nullable', 'string'],
                ...$questionImage,
            ];
        }

        return [
            'type' => ['required', 'in:multiple_choice'],
            'question' => ['nullable', 'string'],
            'option_a' => ['nullable', 'string'],
            'option_b' => ['nullable', 'string'],
            'option_c' => ['nullable', 'string'],
            'option_d' => ['nullable', 'string'],
            'option_e' => ['nullable', 'string'],
            'option_f' => ['nullable', 'string'],
            'correct_answer' => ['required', 'in:a,b,c,d,e,f'],
            'explanation' => ['nullable', 'string'],
            ...$questionImage,
            'option_images' => ['nullable', 'array'],
            'option_images.*' => QuizImage::fileRules(),
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
                if (blank($this->input('question')) && ! QuizImage::questionHasImageAfterSave($this, $this->existingQuestion())) {
                    $validator->errors()->add('question', 'Pertanyaan wajib diisi teks atau gambar.');
                }

                if ($this->isEssay()) {
                    return;
                }

                $images = QuizImage::optionsWithImageAfterSave($this, $this->existingQuestion(), TrainingQuestion::OPTION_LETTERS);
                $filled = fn (string $letter) => filled($this->input('option_'.$letter)) || $images[$letter];

                foreach (['a', 'b'] as $letter) {
                    if (! $filled($letter)) {
                        $validator->errors()->add('option_'.$letter, 'Pilihan '.strtoupper($letter).' wajib diisi teks atau gambar.');
                    }
                }

                $key = $this->input('correct_answer');
                if (in_array($key, TrainingQuestion::OPTION_LETTERS, true) && ! $filled($key)) {
                    $validator->errors()->add('correct_answer', 'Kunci jawaban menunjuk pilihan '.strtoupper($key).' yang masih kosong.');
                }
            },
        ];
    }

    /**
     * Validated data with the option columns cleared for essay questions (images excluded).
     *
     * @return array<string, mixed>
     */
    public function questionData(): array
    {
        $options = array_fill_keys(['option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'option_f'], null);

        return [
            ...$options,
            ...$this->safe()->except(['question_image', 'option_images', 'remove_question_image', 'remove_option_images']),
            // Image-only question: the column is NOT NULL, so store an empty text.
            'question' => (string) $this->input('question'),
        ];
    }

    /**
     * Question columns plus images stored on disk. Essay questions keep only the question image.
     *
     * @return array<string, mixed>
     */
    public function questionDataWithImages(): array
    {
        $letters = $this->isEssay() ? [] : TrainingQuestion::OPTION_LETTERS;

        return [
            ...$this->questionData(),
            ...QuizImage::fromForm($this, $this->existingQuestion(), $letters),
        ];
    }

    private function isEssay(): bool
    {
        return $this->input('type') === 'essay';
    }

    private function existingQuestion(): ?TrainingQuestion
    {
        $question = $this->route('question');

        return $question instanceof TrainingQuestion ? $question : null;
    }
}
