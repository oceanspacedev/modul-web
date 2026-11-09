<?php

namespace App\Http\Requests;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\LearningModuleItem;
use App\Models\Video;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LearningModuleRequest extends FormRequest
{
    /**
     * Authorization is handled by LearningModulePolicy on the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['shuffle_questions' => $this->boolean('shuffle_questions')]);
    }

    /**
     * Materi dikirim berurutan sebagai "video:{id}" / "document:{id}".
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'passing_score' => ['required', 'integer', 'min:0', 'max:100'],
            'shuffle_questions' => ['boolean'],
            'pretest_max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'posttest_max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'quiz_mode' => ['nullable', 'in:'.implode(',', LearningModule::MODES)],
            'question_seconds' => ['nullable', 'integer', 'min:'.LearningModule::MIN_QUESTION_SECONDS, 'max:'.LearningModule::MAX_QUESTION_SECONDS],
            'divisi_ids' => ['nullable', 'array'],
            'divisi_ids.*' => ['integer', 'exists:divisis,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'distinct', 'regex:/^(video|document):\d+$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul modul',
            'passing_score' => 'nilai minimal lulus (KKM)',
            'items' => 'materi',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $keys = collect($this->materialKeys());
                $found = $keys->groupBy(0)->map(fn ($group, string $type) => ($type === LearningModuleItem::TYPE_VIDEO ? Video::query() : Document::query())
                    ->whereIn('id', $group->pluck(1))
                    ->count())->sum();

                if ($found !== $keys->count()) {
                    $validator->errors()->add('items', 'Sebagian materi yang dipilih sudah tidak tersedia. Muat ulang halaman lalu pilih kembali.');
                }
            },
        ];
    }

    /**
     * Materi terpilih secara berurutan.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public function materialKeys(): array
    {
        return collect($this->validated('items') ?? [])
            ->map(function (string $key) {
                [$type, $id] = explode(':', $key);

                return [$type, (int) $id];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function moduleData(): array
    {
        return [
            ...$this->safe()->only(['title', 'description', 'passing_score', 'shuffle_questions']),
            'pretest_max_attempts' => $this->validated('pretest_max_attempts'),
            'posttest_max_attempts' => $this->validated('posttest_max_attempts'),
            'quiz_mode' => $this->validated('quiz_mode') ?? LearningModule::MODE_FORMAL,
            'question_seconds' => $this->validated('question_seconds') ?? 30,
        ];
    }
}
