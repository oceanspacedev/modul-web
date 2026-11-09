<?php

namespace App\Services;

use App\Models\Document;
use App\Models\LearningModule;
use App\Models\Video;

/**
 * Kuis pretest/posttest yang dinyalakan langsung dari form Video Materi / Dokumen.
 *
 * Setiap materi ber-kuis memiliki satu LearningModule tersembunyi (source = materi itu) berisi satu item, sehingga
 * bank soal, AI, percobaan, rekap, dan export Modul Belajar dipakai ulang apa adanya.
 */
class MaterialQuizService
{
    /**
     * Aturan validasi field quiz[...] dari partial learning_module.partials.material-quiz-fields.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'quiz' => ['nullable', 'array'],
            'quiz.pretest' => ['boolean'],
            'quiz.posttest' => ['boolean'],
            'quiz.pretest_shows_answers' => ['boolean'],
            'quiz.posttest_shows_answers' => ['boolean'],
            'quiz.pretest_max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'quiz.posttest_max_attempts' => ['nullable', 'integer', 'min:1', 'max:255'],
            'quiz.passing_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'quiz.mode' => ['nullable', 'in:'.implode(',', LearningModule::MODES)],
            'quiz.question_seconds' => ['nullable', 'integer', 'min:'.LearningModule::MIN_QUESTION_SECONDS, 'max:'.LearningModule::MAX_QUESTION_SECONDS],
        ];
    }

    /**
     * Simpan pengaturan kuis dari form. Tanpa field quiz (mis. unggah video dari halaman pelatihan) tidak ada yang
     * diubah. Mematikan pretest dan posttest hanya menonaktifkan kuis; riwayat nilai tetap tersimpan.
     *
     * @param  array<string, mixed>|null  $settings
     */
    public function sync(Video|Document $material, ?array $settings): ?LearningModule
    {
        $module = LearningModule::withTrashed()
            ->where('source_type', $material->getMorphClass())
            ->where('source_id', $material->getKey())
            ->first();

        if ($settings === null) {
            return $module;
        }

        $pretest = (bool) ($settings['pretest'] ?? false);
        $posttest = (bool) ($settings['posttest'] ?? false);

        if (! $module && ! $pretest && ! $posttest) {
            return null;
        }

        $module ??= new LearningModule([
            'source_type' => $material->getMorphClass(),
            'source_id' => $material->getKey(),
            'shuffle_questions' => true,
        ]);

        $module->fill([
            'title' => $material instanceof Video ? $material->title : $material->name,
            'has_pretest' => $pretest,
            'has_posttest' => $posttest,
            'pretest_max_attempts' => $settings['pretest_max_attempts'] ?? null,
            'posttest_max_attempts' => $settings['posttest_max_attempts'] ?? null,
            'passing_score' => $settings['passing_score'] ?? 70,
            'quiz_mode' => $settings['mode'] ?? LearningModule::MODE_FORMAL,
            'question_seconds' => $settings['question_seconds'] ?? 30,
            // Form lama tanpa field ini: rincian jawaban tetap ditampilkan seperti sebelumnya.
            'pretest_shows_answers' => (bool) ($settings['pretest_shows_answers'] ?? true),
            'posttest_shows_answers' => (bool) ($settings['posttest_shows_answers'] ?? true),
            'deleted_at' => null,
        ])->save();

        $module->items()->firstOrCreate([
            'materialable_type' => $material->getMorphClass(),
            'materialable_id' => $material->getKey(),
        ]);

        $this->refresh($module);

        return $module;
    }

    /**
     * Kuis milik materi aktif bila pretest atau posttest nyala dan sudah ada minimal satu soal.
     */
    public function refresh(LearningModule $module): void
    {
        if (! $module->isOwned()) {
            return;
        }

        $module->update([
            'is_published' => ($module->has_pretest || $module->has_posttest) && $module->questions()->exists(),
        ]);
    }

    /**
     * Kuis aktif tetapi belum punya soal: pembuat perlu diarahkan ke halaman soal.
     */
    public function needsQuestions(?LearningModule $module): bool
    {
        return $module !== null
            && ($module->has_pretest || $module->has_posttest)
            && ! $module->questions()->exists();
    }
}
