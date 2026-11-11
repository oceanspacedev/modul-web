<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use App\Support\QuizRules;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    public const PHASE_PRETEST = 'pretest';

    public const PHASE_POSTTEST = 'posttest';

    public const PHASES = [self::PHASE_PRETEST, self::PHASE_POSTTEST];

    protected $guarded = ['id'];

    /**
     * Kuis utama pelatihan adalah posttest: is_quiz_active, quiz_max_attempts, dan show_quiz_answers miliknya.
     * Pretest punya kolom sendiri (is_pretest_active, pretest_max_attempts, pretest_shows_answers).
     */
    protected $attributes = [
        'has_pretest' => false,
        'has_posttest' => true,
        'show_quiz_answers' => true,
        'quiz_max_attempts' => 1,
        'pretest_shows_answers' => false,
        'pretest_max_attempts' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'status' => TrainingStatus::class,
            'is_quiz_active' => 'boolean',
            'is_attendance_active' => 'boolean',
            'require_attendance_proof' => 'boolean',
            'show_quiz_answers' => 'boolean',
            'quiz_max_attempts' => 'integer',
            'has_pretest' => 'boolean',
            'has_posttest' => 'boolean',
            'is_pretest_active' => 'boolean',
            'pretest_max_attempts' => 'integer',
            'pretest_shows_answers' => 'boolean',
            'setup_completed_at' => 'datetime',
        ];
    }

    /**
     * Pemateri belum menjawab pertanyaan pengaturan (presensi, bukti foto, jawaban benar/salah, batas percobaan kuis).
     */
    public function needsSetup(): bool
    {
        return $this->setup_completed_at === null;
    }

    /**
     * Aturan validasi blok Aturan Kuis (quiz[...]) untuk pelatihan.
     *
     * @return array<string, mixed>
     */
    public static function quizRuleValidation(): array
    {
        return [
            'quiz' => ['nullable', 'array'],
            'quiz.pretest' => ['boolean'],
            'quiz.posttest' => ['boolean'],
            'quiz.pretest_shows_answers' => ['boolean'],
            'quiz.posttest_shows_answers' => ['boolean'],
            'quiz.pretest_max_attempts' => QuizRules::attemptRules(),
            'quiz.posttest_max_attempts' => QuizRules::attemptRules(),
            'quiz.mode' => ['nullable', 'in:formal,game'],
        ];
    }

    /**
     * Petakan field quiz[...] ke kolom pelatihan. Fase yang dimatikan sekaligus ditutup untuk peserta.
     *
     * @param  array<string, mixed>  $quiz
     * @return array<string, mixed>
     */
    public static function quizRulesFromInput(array $quiz): array
    {
        $pretest = (bool) ($quiz['pretest'] ?? false);
        $posttest = (bool) ($quiz['posttest'] ?? false);
        $attempts = fn (string $key) => isset($quiz[$key]) ? (int) $quiz[$key] : null;

        return [
            'has_pretest' => $pretest,
            'has_posttest' => $posttest,
            'pretest_max_attempts' => $attempts('pretest_max_attempts'),
            'quiz_max_attempts' => $attempts('posttest_max_attempts'),
            'pretest_shows_answers' => (bool) ($quiz['pretest_shows_answers'] ?? false),
            'show_quiz_answers' => (bool) ($quiz['posttest_shows_answers'] ?? true),
            ...(isset($quiz['mode']) ? ['quiz_mode' => $quiz['mode']] : []),
            ...($pretest ? [] : ['is_pretest_active' => false]),
            ...($posttest ? [] : ['is_quiz_active' => false]),
        ];
    }

    public function hasQuiz(): bool
    {
        return $this->has_pretest || $this->has_posttest;
    }

    public function hasPhase(string $phase): bool
    {
        return $phase === self::PHASE_PRETEST ? $this->has_pretest : $this->has_posttest;
    }

    /**
     * Fase ini sedang dibuka pemateri untuk dikerjakan.
     */
    public function isPhaseActive(string $phase): bool
    {
        return $this->hasPhase($phase) && ($phase === self::PHASE_PRETEST ? $this->is_pretest_active : $this->is_quiz_active);
    }

    /**
     * Batas percobaan per fase; null berarti tanpa batas.
     */
    public function maxAttempts(string $phase): ?int
    {
        return $phase === self::PHASE_PRETEST ? $this->pretest_max_attempts : $this->quiz_max_attempts;
    }

    /**
     * Peserta melihat rincian jawaban (benar/salah + kunci) setelah fase ini; kalau tidak, hanya nilainya.
     */
    public function showsAnswers(string $phase): bool
    {
        return $phase === self::PHASE_PRETEST ? $this->pretest_shows_answers : $this->show_quiz_answers;
    }

    public static function phaseLabel(string $phase): string
    {
        return $phase === self::PHASE_PRETEST ? 'Pretest' : 'Posttest';
    }

    /**
     * Soal yang dipakai sebuah fase. Pretest hanya memakai pilihan ganda; essay dinilai pemateri di posttest saja.
     * Butuh relasi questions.
     */
    public function questionsFor(string $phase)
    {
        return $phase === self::PHASE_PRETEST
            ? $this->questions->where('type', '!=', 'essay')->values()
            : $this->questions;
    }

    /**
     * Fase boleh dikerjakan lebih dari sekali (null = tanpa batas).
     */
    public function allowsQuizRetake(string $phase = self::PHASE_POSTTEST): bool
    {
        return $this->maxAttempts($phase) !== 1;
    }

    /**
     * Sisa percobaan seorang peserta pada sebuah fase; null berarti tanpa batas.
     */
    public function quizAttemptsLeft(?TrainingQuizResult $result, string $phase = self::PHASE_POSTTEST): ?int
    {
        $max = $this->maxAttempts($phase);
        if ($max === null) {
            return null;
        }

        return max(0, $max - ($result ? ($result->attempt_count ?? 1) : 0));
    }

    /**
     * Peserta yang sudah mengerjakan boleh mengulang sekarang: masih ada sisa percobaan dan fase sedang dibuka.
     */
    public function canRetakeQuiz(?TrainingQuizResult $result, string $phase = self::PHASE_POSTTEST): bool
    {
        return $this->isPhaseActive($phase) && $this->quizAttemptsLeft($result, $phase) !== 0;
    }

    public function trainer()
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function participants()
    {
        return $this->hasMany(TrainingParticipant::class, 'training_id');
    }

    public function questions()
    {
        return $this->hasMany(TrainingQuestion::class, 'training_id');
    }

    /**
     * Hasil posttest (kuis utama); dipakai rekap, statistik, dan dashboard.
     */
    public function quizResults()
    {
        return $this->hasMany(TrainingQuizResult::class, 'training_id')->where('phase', self::PHASE_POSTTEST);
    }

    public function pretestResults()
    {
        return $this->hasMany(TrainingQuizResult::class, 'training_id')->where('phase', self::PHASE_PRETEST);
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'training_id');
    }

    public function video()
    {
        return $this->hasOne(Video::class, 'training_id')->latestOfMany();
    }

    public function documents()
    {
        return $this->belongsToMany(Document::class, 'training_documents', 'training_id', 'document_id')->withTimestamps();
    }

    public function scopeFilter($query)
    {
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('trainer', function ($t) use ($search) {
                        $t->where('nama_lengkap', 'like', "%{$search}%");
                    });
            });
        }

        if (request('status')) {
            $query->where('status', request('status'));
        }
    }
}
