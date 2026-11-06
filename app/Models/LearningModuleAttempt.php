<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Satu percobaan pretest/posttest. Semua percobaan disimpan sebagai riwayat; batasnya diatur per modul.
 */
class LearningModuleAttempt extends Model
{
    public const PHASE_PRETEST = 'pretest';

    public const PHASE_POSTTEST = 'posttest';

    public const PHASES = [self::PHASE_PRETEST, self::PHASE_POSTTEST];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'score' => 'float',
            'is_passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(LearningModule::class, 'learning_module_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPretest(): bool
    {
        return $this->phase === self::PHASE_PRETEST;
    }

    /**
     * Nilai jawaban terhadap bank soal saat ini lalu simpan sebagai percobaan baru. Soal, opsi, dan kunci ikut
     * disimpan di kolom answers supaya riwayat tetap utuh walaupun soal diubah atau dihapus kemudian.
     *
     * @param  array<int|string, string|null>  $answers  id soal => huruf jawaban
     */
    public static function record(LearningModule $module, User $user, string $phase, array $answers): self
    {
        $snapshot = [];
        $correct = 0;

        foreach ($module->questions()->get() as $question) {
            $options = $question->options();
            $userAnswer = strtolower((string) ($answers[$question->id] ?? ''));
            $userAnswer = array_key_exists($userAnswer, $options) ? $userAnswer : null;
            $isCorrect = $userAnswer !== null && $userAnswer === $question->correct_answer;
            $correct += (int) $isCorrect;

            $snapshot[] = [
                'question_id' => $question->id,
                'question' => $question->question,
                'options' => $options,
                'question_image' => $question->question_image,
                'option_images' => $question->option_images ?: null,
                'correct_answer' => $question->correct_answer,
                'user_answer' => $userAnswer,
                'is_correct' => $isCorrect,
            ];
        }

        $total = count($snapshot);
        $score = $total > 0 ? round($correct / $total * 100, 2) : 0;

        return DB::transaction(function () use ($module, $user, $phase, $snapshot, $total, $correct, $score) {
            // Kiriman ganda menghasilkan nomor yang sama dan ditolak unique index.
            $attemptNumber = (int) static::query()
                ->where('learning_module_id', $module->id)
                ->where('user_id', $user->id)
                ->where('phase', $phase)
                ->lockForUpdate()
                ->max('attempt_number') + 1;

            return static::create([
                'learning_module_id' => $module->id,
                'user_id' => $user->id,
                'phase' => $phase,
                'attempt_number' => $attemptNumber,
                'total_questions' => $total,
                'correct_answers' => $correct,
                'score' => $score,
                'is_passed' => $score >= $module->passing_score,
                'answers' => $snapshot,
                'submitted_at' => now(),
            ]);
        });
    }
}
