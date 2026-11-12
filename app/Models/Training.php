<?php

namespace App\Models;

use App\Enums\TrainingStatus;
use App\Support\QuizRules;
use App\Support\TrainingEvaluationForm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Training extends Model
{
    use HasFactory, SoftDeletes;

    public const PHASE_PRETEST = 'pretest';

    public const PHASE_POSTTEST = 'posttest';

    public const PHASES = [self::PHASE_PRETEST, self::PHASE_POSTTEST];

    /**
     * Yang punya jam buka dan jam tutup sendiri lewat mekanisme "fase" (isPhaseActive(), openPhase(), dst.): pretest,
     * posttest, presensi, dan form evaluasi. Presensi dan evaluasi bukan kuis, tapi dibuka, dijadwalkan, dan ditutup
     * pemateri dengan cara yang sama. Tidak ada yang terbuka otomatis: semuanya mengikuti pengaturan pemateri.
     */
    public const TIMED_STEPS = [self::PHASE_PRETEST, self::PHASE_POSTTEST, self::STEP_ATTENDANCE, self::STEP_EVALUATION];

    public const STEP_PRETEST = 'pretest';

    public const STEP_SESSION = 'session';

    public const STEP_ATTENDANCE = 'attendance';

    public const STEP_EVALUATION = 'evaluation';

    public const STEP_POSTTEST = 'posttest';

    /** Langkah peserta di portal pelatihan, dalam urutan bawaan. Pemateri bisa mengubah urutannya per pelatihan. */
    public const PORTAL_STEPS = [
        self::STEP_PRETEST => 'Pretest',
        self::STEP_SESSION => 'Ikuti sesi',
        self::STEP_ATTENDANCE => 'Presensi',
        self::STEP_EVALUATION => 'Form evaluasi',
        self::STEP_POSTTEST => 'Posttest',
    ];

    /** Menit toleransi kiriman form evaluasi setelah jam tutupnya lewat. */
    public const EVALUATION_GRACE_MINUTES = 5;

    /** Menit toleransi kiriman presensi (mis. foto yang sedang diunggah) setelah jam tutupnya lewat. */
    public const ATTENDANCE_GRACE_MINUTES = 2;

    protected $guarded = ['id'];

    /**
     * Kuis utama pelatihan adalah posttest: is_quiz_active, quiz_max_attempts, dan show_quiz_answers miliknya.
     * Pretest punya kolom sendiri (is_pretest_active, pretest_max_attempts, pretest_shows_answers).
     * Waktu per fase: *_duration_minutes (bawaan dari Aturan Kuis), lalu *_opens_at dan *_closes_at yang dicatat saat
     * pemateri membuka atau menjadwalkan fase itu.
     */
    protected $attributes = [
        'has_pretest' => false,
        'has_posttest' => true,
        'has_evaluation' => false,
        'has_attendance' => true,
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
            'attendance_opens_at' => 'datetime',
            'attendance_closes_at' => 'datetime',
            'has_attendance' => 'boolean',
            'portal_steps' => 'array',
            'is_evaluation_active' => 'boolean',
            'evaluation_opens_at' => 'datetime',
            'evaluation_closes_at' => 'datetime',
            'require_attendance_proof' => 'boolean',
            'show_quiz_answers' => 'boolean',
            'quiz_max_attempts' => 'integer',
            'has_pretest' => 'boolean',
            'has_posttest' => 'boolean',
            'has_evaluation' => 'boolean',
            'is_pretest_active' => 'boolean',
            'pretest_max_attempts' => 'integer',
            'pretest_shows_answers' => 'boolean',
            'pretest_duration_minutes' => 'integer',
            'pretest_opens_at' => 'datetime',
            'pretest_closes_at' => 'datetime',
            'quiz_duration_minutes' => 'integer',
            'quiz_opens_at' => 'datetime',
            'quiz_closes_at' => 'datetime',
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
            'quiz.pretest_duration_minutes' => QuizRules::durationRules(),
            'quiz.posttest_duration_minutes' => QuizRules::durationRules(),
            'quiz.mode' => ['nullable', 'in:formal,game'],
            'quiz.pretest_mode' => ['nullable', 'in:formal,game'],
            'quiz.posttest_mode' => ['nullable', 'in:formal,game'],
        ];
    }

    /**
     * Petakan field quiz[...] ke kolom pelatihan. Fase yang dimatikan sekaligus ditutup untuk peserta. Durasi baru
     * berlaku saat fase dibuka berikutnya; jam tutup fase yang sedang terbuka tidak ikut berubah.
     *
     * @param  array<string, mixed>  $quiz
     * @return array<string, mixed>
     */
    public static function quizRulesFromInput(array $quiz): array
    {
        $pretest = (bool) ($quiz['pretest'] ?? false);
        $posttest = (bool) ($quiz['posttest'] ?? false);
        $number = fn (string $key) => isset($quiz[$key]) ? (int) $quiz[$key] : null;

        return [
            'has_pretest' => $pretest,
            'has_posttest' => $posttest,
            'pretest_max_attempts' => $number('pretest_max_attempts'),
            'quiz_max_attempts' => $number('posttest_max_attempts'),
            'pretest_duration_minutes' => $number('pretest_duration_minutes'),
            'quiz_duration_minutes' => $number('posttest_duration_minutes'),
            'pretest_shows_answers' => (bool) ($quiz['pretest_shows_answers'] ?? false),
            'show_quiz_answers' => (bool) ($quiz['posttest_shows_answers'] ?? true),
            ...self::quizModesFromInput($quiz),
            ...($pretest ? [] : ['is_pretest_active' => false, 'pretest_opens_at' => null, 'pretest_closes_at' => null]),
            ...($posttest ? [] : ['is_quiz_active' => false, 'quiz_opens_at' => null, 'quiz_closes_at' => null]),
        ];
    }

    /**
     * Mode kuis per fase dari quiz[pretest_mode] / quiz[posttest_mode]. quiz[mode] (satu mode untuk keduanya) tetap
     * diterima dari form lama: posttest memakainya dan pretest kembali mengikuti posttest.
     *
     * @param  array<string, mixed>  $quiz
     * @return array<string, mixed>
     */
    private static function quizModesFromInput(array $quiz): array
    {
        $shared = $quiz['mode'] ?? null;
        $posttest = $quiz['posttest_mode'] ?? $shared;

        return [
            ...($posttest !== null ? ['quiz_mode' => $posttest] : []),
            ...(isset($quiz['pretest_mode']) ? ['pretest_quiz_mode' => $quiz['pretest_mode']] : ($shared !== null ? ['pretest_quiz_mode' => null] : [])),
        ];
    }

    /**
     * Mode kuis sebuah fase: 'formal' (semua soal satu halaman) atau 'game'. Pretest tanpa mode sendiri mengikuti
     * mode posttest.
     */
    public function quizMode(string $phase): string
    {
        $mode = $phase === self::PHASE_PRETEST ? ($this->pretest_quiz_mode ?? $this->quiz_mode) : $this->quiz_mode;

        return $mode === 'game' ? 'game' : 'formal';
    }

    /**
     * Urutan kelima langkah peserta untuk pelatihan ini. Urutan tersimpan dipakai apa adanya; langkah yang belum
     * tercantum (data lama, langkah baru) menyusul sesuai urutan bawaan.
     *
     * @return list<string>
     */
    public function portalStepOrder(): array
    {
        $known = array_keys(self::PORTAL_STEPS);
        $stored = array_values(array_unique(array_intersect($this->portal_steps ?? [], $known)));

        return [...$stored, ...array_values(array_diff($known, $stored))];
    }

    /**
     * Langkah yang benar-benar dipakai pelatihan ini, dalam urutan pilihan pemateri. Menentukan urutan kartu dan
     * nomor "Langkah N" di portal peserta.
     *
     * @return list<string>
     */
    public function portalSteps(): array
    {
        $used = [
            self::STEP_PRETEST => (bool) $this->has_pretest,
            self::STEP_SESSION => true,
            self::STEP_ATTENDANCE => (bool) $this->has_attendance,
            self::STEP_EVALUATION => $this->hasEvaluationForm(),
            self::STEP_POSTTEST => (bool) $this->has_posttest,
        ];

        return array_values(array_filter($this->portalStepOrder(), fn (string $step) => $used[$step]));
    }

    /**
     * Evaluasi diurutkan sebelum posttest: selama form evaluasi terbuka, peserta harus mengisinya dulu sebelum
     * mengerjakan posttest. Form evaluasi dibuka pemateri sendiri, terpisah dari posttest, jadi waktu mengisinya tidak
     * memotong waktu posttest bila dibuka lebih dulu.
     */
    public function evaluationFollowsPosttest(): bool
    {
        if (! $this->has_posttest) {
            return false;
        }

        $order = array_flip($this->portalStepOrder());

        return $order[self::STEP_EVALUATION] < $order[self::STEP_POSTTEST];
    }

    public function hasQuiz(): bool
    {
        return $this->has_pretest || $this->has_posttest;
    }

    /**
     * Fase yang nilainya menjadi "nilai pelatihan" di ringkasan, tabel peserta, dan rekap: posttest bila ada, kalau
     * tidak pretest (pelatihan pretest saja); null untuk pelatihan tanpa kuis.
     */
    public function mainPhase(): ?string
    {
        return match (true) {
            (bool) $this->has_posttest => self::PHASE_POSTTEST,
            (bool) $this->has_pretest => self::PHASE_PRETEST,
            default => null,
        };
    }

    /**
     * Sebutan fase utama: "Posttest" bila ada pretest juga, "Pretest" bila hanya pretest, selain itu "Kuis".
     */
    public function mainPhaseLabel(): string
    {
        return match (true) {
            $this->has_pretest && $this->has_posttest => 'Posttest',
            (bool) $this->has_pretest && ! $this->has_posttest => 'Pretest',
            default => 'Kuis',
        };
    }

    /**
     * Pelatihan ini memakai fase/langkah berjadwal itu (lihat TIMED_STEPS).
     */
    public function hasPhase(string $phase): bool
    {
        return (bool) match ($phase) {
            self::PHASE_PRETEST => $this->has_pretest,
            self::STEP_ATTENDANCE => $this->has_attendance,
            self::STEP_EVALUATION => $this->has_evaluation,
            default => $this->has_posttest,
        };
    }

    /**
     * Fase ini sedang terbuka untuk dikerjakan: dibuka pemateri, jam bukanya (bila dijadwalkan) sudah tiba, dan jam
     * tutupnya (bila berbatas waktu) belum lewat. Jadwal berjalan sendiri tanpa perlu dibuka atau dikunci manual.
     */
    public function isPhaseActive(string $phase): bool
    {
        if (! $this->hasPhase($phase) || ! $this->{self::activeColumn($phase)}) {
            return false;
        }

        $opensAt = $this->{self::opensAtColumn($phase)};
        $closesAt = $this->{self::closesAtColumn($phase)};

        return ($opensAt === null || ! $opensAt->isFuture()) && ($closesAt === null || $closesAt->isFuture());
    }

    /**
     * Jam fase ini terbuka sendiri; null bila tidak sedang dijadwalkan (tertutup, atau sudah terbuka).
     */
    public function phaseOpensAt(string $phase): ?Carbon
    {
        $opensAt = $this->{self::opensAtColumn($phase)};

        return $this->hasPhase($phase) && $this->{self::activeColumn($phase)} && $opensAt?->isFuture() ? $opensAt : null;
    }

    /**
     * Jam fase ini tutup otomatis; null bila fase tertutup atau tanpa batas waktu. Berlaku juga untuk fase yang baru
     * dijadwalkan dan belum terbuka.
     */
    public function phaseClosesAt(string $phase): ?Carbon
    {
        return $this->isPhaseActive($phase) || $this->phaseOpensAt($phase) ? $this->{self::closesAtColumn($phase)} : null;
    }

    /**
     * Sisa detik sampai fase yang sedang terbuka tutup otomatis; null bila tertutup atau tanpa batas waktu.
     */
    public function phaseSecondsLeft(string $phase): ?int
    {
        $closesAt = $this->isPhaseActive($phase) ? $this->{self::closesAtColumn($phase)} : null;

        return $closesAt ? max(0, (int) ceil(now()->diffInSeconds($closesAt))) : null;
    }

    /**
     * Fase ini pernah dibuka dengan batas waktu dan waktunya sudah habis (belum dibuka lagi atau dikunci manual).
     */
    public function phaseTimedOut(string $phase): bool
    {
        $closesAt = $this->{self::closesAtColumn($phase)};

        return $this->hasPhase($phase) && $this->{self::activeColumn($phase)} && $closesAt !== null && ! $closesAt->isFuture();
    }

    /**
     * Jam untuk dibaca orang: "pukul 09:40" bila hari ini, selain itu dengan tanggalnya ("13 Nov pukul 09:40").
     */
    public static function timeLabel(Carbon $at): string
    {
        return ($at->isToday() ? '' : $at->translatedFormat('j M').' ').'pukul '.$at->format('H:i');
    }

    /**
     * Tanggal yang dipakai jam buka terjadwal: hari pelatihan bila masih akan datang, selain itu hari ini.
     */
    public function scheduleDate(): Carbon
    {
        return $this->training_date?->isFuture() ? $this->training_date->copy() : now();
    }

    /**
     * Batas waktu fase dalam menit sejak dibuka; null berarti tanpa batas waktu.
     */
    public function durationMinutes(string $phase): ?int
    {
        return match ($phase) {
            self::PHASE_PRETEST => $this->pretest_duration_minutes,
            // Presensi dan form evaluasi tidak punya batas waktu bawaan; pemateri mengisinya saat membuka.
            self::STEP_ATTENDANCE, self::STEP_EVALUATION => null,
            default => $this->quiz_duration_minutes,
        };
    }

    /**
     * Jam tutup bawaan untuk fase yang dibuka pada $from (bawaan: sekarang), dari batas waktu di Aturan Kuis; null
     * berarti tanpa batas waktu.
     */
    public function defaultClosesAt(string $phase, ?Carbon $from = null): ?Carbon
    {
        $minutes = $this->durationMinutes($phase);

        return $minutes ? ($from ?? now())->copy()->addMinutes($minutes) : null;
    }

    /**
     * Buka fase untuk semua peserta. $opensAt null berarti terbuka sekarang, selain itu terbuka sendiri pada jam itu;
     * $closesAt null berarti terbuka sampai dikunci manual.
     */
    public function openPhase(string $phase, ?Carbon $closesAt, ?Carbon $opensAt = null): void
    {
        $this->update([
            self::activeColumn($phase) => true,
            self::opensAtColumn($phase) => $opensAt,
            self::closesAtColumn($phase) => $closesAt,
        ]);
    }

    /**
     * Ganti jam tutup fase yang sedang terbuka (null = tanpa batas waktu). False bila fase tidak sedang terbuka.
     */
    public function setPhaseClosesAt(string $phase, ?Carbon $closesAt): bool
    {
        if (! $this->isPhaseActive($phase)) {
            return false;
        }

        $this->update([self::closesAtColumn($phase) => $closesAt]);

        return true;
    }

    public function closePhase(string $phase): void
    {
        $this->update([
            self::activeColumn($phase) => false,
            self::opensAtColumn($phase) => null,
            self::closesAtColumn($phase) => null,
        ]);
    }

    /**
     * Mundurkan jam tutup fase yang sedang terbuka dengan batas waktu. False bila tidak ada jam tutup untuk diundur.
     */
    public function extendPhase(string $phase, int $minutes): bool
    {
        $closesAt = $this->phaseClosesAt($phase);
        if (! $closesAt) {
            return false;
        }

        $this->update([self::closesAtColumn($phase) => $closesAt->copy()->addMinutes($minutes)]);

        return true;
    }

    private static function activeColumn(string $phase): string
    {
        return match ($phase) {
            self::PHASE_PRETEST => 'is_pretest_active',
            self::STEP_ATTENDANCE => 'is_attendance_active',
            self::STEP_EVALUATION => 'is_evaluation_active',
            default => 'is_quiz_active',
        };
    }

    private static function opensAtColumn(string $phase): string
    {
        return match ($phase) {
            self::PHASE_PRETEST => 'pretest_opens_at',
            self::STEP_ATTENDANCE => 'attendance_opens_at',
            self::STEP_EVALUATION => 'evaluation_opens_at',
            default => 'quiz_opens_at',
        };
    }

    private static function closesAtColumn(string $phase): string
    {
        return match ($phase) {
            self::PHASE_PRETEST => 'pretest_closes_at',
            self::STEP_ATTENDANCE => 'attendance_closes_at',
            self::STEP_EVALUATION => 'evaluation_closes_at',
            default => 'quiz_closes_at',
        };
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
        return match ($phase) {
            self::PHASE_PRETEST => 'Pretest',
            self::STEP_ATTENDANCE => 'Presensi',
            self::STEP_EVALUATION => 'Form Evaluasi',
            default => 'Posttest',
        };
    }

    /**
     * Pelatihan punya pretest dan posttest sekaligus; hanya saat itulah pilihan "dipakai di" tiap soal berpengaruh.
     */
    public function hasBothPhases(): bool
    {
        return $this->has_pretest && $this->has_posttest;
    }

    /**
     * Soal yang dipakai sebuah fase. Dengan pretest dan posttest sekaligus, tiap soal mengikuti pilihan "dipakai di"
     * miliknya; pelatihan dengan satu fase memakai semua soalnya. Butuh relasi questions.
     */
    public function questionsFor(string $phase)
    {
        return $this->hasBothPhases()
            ? $this->questions->filter(fn (TrainingQuestion $question) => $question->isUsedIn($phase))->values()
            : $this->questions;
    }

    /**
     * Query soal sebuah fase, untuk hitungan dan pengecekan tanpa memuat soalnya.
     */
    public function phaseQuestions(string $phase)
    {
        return $this->questions()->when(
            $this->hasBothPhases(),
            fn ($query) => $query->whereIn('used_in', [TrainingQuestion::USED_IN_BOTH, $phase]),
        );
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

    /**
     * Pelatihan yang form evaluasinya baru dinyalakan dan belum punya pertanyaan mendapat template bawaan; pengelola
     * lalu bebas mengubahnya. Pertanyaan yang sudah ada tidak disentuh.
     */
    protected static function booted(): void
    {
        // Presensi yang baru ditutup (atau tidak lagi dipakai) tidak menyimpan jadwal. Hanya saat kolomnya memang
        // berubah, supaya model yang dimuat sebagian kolomnya tidak ikut tertutup.
        static::saving(function (Training $training): void {
            if (($training->isDirty('is_attendance_active') && ! $training->is_attendance_active)
                || ($training->isDirty('has_attendance') && ! $training->has_attendance)) {
                $training->is_attendance_active = false;
                $training->attendance_opens_at = null;
                $training->attendance_closes_at = null;
            }

            // Sama untuk form evaluasi yang ditutup atau dimatikan.
            if (($training->isDirty('is_evaluation_active') && ! $training->is_evaluation_active)
                || ($training->isDirty('has_evaluation') && ! $training->has_evaluation)) {
                $training->is_evaluation_active = false;
                $training->evaluation_opens_at = null;
                $training->evaluation_closes_at = null;
            }
        });

        static::saved(function (Training $training): void {
            if ($training->has_evaluation && ($training->wasRecentlyCreated || $training->wasChanged('has_evaluation'))) {
                $training->addDefaultEvaluationQuestions();
            }
        });
    }

    /**
     * Isi pertanyaan evaluasi dengan template bawaan bila pelatihan belum punya satu pun.
     */
    public function addDefaultEvaluationQuestions(): void
    {
        if ($this->evaluationQuestions()->exists()) {
            return;
        }

        foreach (TrainingEvaluationForm::defaultQuestions() as $position => $question) {
            $this->evaluationQuestions()->create([...$question, 'position' => $position + 1]);
        }

        $this->unsetRelation('evaluationQuestions');
    }

    /**
     * Form evaluasi dinyalakan dan punya pertanyaan. Tanpa pertanyaan, peserta tidak diminta mengisi apa pun.
     */
    public function hasEvaluationForm(): bool
    {
        if (! $this->has_evaluation) {
            return false;
        }

        return $this->relationLoaded('evaluationQuestions')
            ? $this->evaluationQuestions->isNotEmpty()
            : $this->evaluationQuestions()->exists();
    }

    /**
     * Form evaluasi bisa diisi sekarang: sedang dibuka pemateri dan formnya punya pertanyaan.
     */
    public function isEvaluationOpen(): bool
    {
        return $this->evaluationWindowOpen() && $this->hasEvaluationForm();
    }

    /**
     * Form evaluasi sedang dibuka, terlepas dari ada/tidaknya pertanyaan: dibuka pemateri, jam bukanya (bila
     * dijadwalkan) sudah tiba, dan jam tutupnya (bila berbatas waktu) belum lewat. Tidak mengikuti status pelatihan
     * maupun posttest; sebelum pemateri membukanya, form tertutup.
     */
    public function evaluationWindowOpen(): bool
    {
        return $this->isPhaseActive(self::STEP_EVALUATION);
    }

    /**
     * Jam form evaluasi terbuka sendiri; null bila tidak dijadwalkan atau sudah terbuka.
     */
    public function evaluationOpensAt(): ?Carbon
    {
        return $this->phaseOpensAt(self::STEP_EVALUATION);
    }

    /**
     * Jam form evaluasi tutup sendiri; null bila tertutup atau tanpa batas waktu.
     */
    public function evaluationClosesAt(): ?Carbon
    {
        return $this->phaseClosesAt(self::STEP_EVALUATION);
    }

    /**
     * Form evaluasi pernah dibuka dengan batas waktu dan waktunya sudah habis.
     */
    public function evaluationTimedOut(): bool
    {
        return $this->phaseTimedOut(self::STEP_EVALUATION);
    }

    /**
     * Kiriman form evaluasi masih diterima: form sedang terbuka, atau jam tutupnya baru saja lewat (peserta yang
     * sedang mengetik saat waktu habis tidak kehilangan isiannya). Form yang dikunci manual langsung tertutup.
     */
    public function acceptsEvaluationSubmission(): bool
    {
        if ($this->isEvaluationOpen()) {
            return true;
        }

        return $this->hasEvaluationForm() && $this->evaluationTimedOut()
            && $this->evaluation_closes_at->copy()->addMinutes(self::EVALUATION_GRACE_MINUTES)->isFuture();
    }

    /**
     * Presensi dipakai dan sedang terbuka: dibuka pemateri, jam bukanya (bila dijadwalkan) sudah tiba, dan jam
     * tutupnya (bila berbatas waktu) belum lewat.
     */
    public function isAttendanceOpen(): bool
    {
        return $this->isPhaseActive(self::STEP_ATTENDANCE);
    }

    /**
     * Presensi sedang terbuka atau dijadwalkan terbuka sendiri; saklar "Buka presensi" di form pelatihan dan
     * pengaturan awal menampilkan keadaan ini.
     */
    public function isAttendanceOpenOrScheduled(): bool
    {
        return $this->isAttendanceOpen() || $this->phaseOpensAt(self::STEP_ATTENDANCE) !== null;
    }

    /**
     * Kiriman presensi masih diterima: presensi sedang terbuka, atau jam tutupnya baru saja lewat (peserta yang
     * sedang mengunggah foto saat waktu habis tidak ditolak). Presensi yang dikunci manual langsung tertutup.
     */
    public function acceptsAttendanceSubmission(): bool
    {
        if ($this->isAttendanceOpen()) {
            return true;
        }

        return $this->phaseTimedOut(self::STEP_ATTENDANCE)
            && $this->attendance_closes_at->copy()->addMinutes(self::ATTENDANCE_GRACE_MINUTES)->isFuture();
    }

    /**
     * Kolom untuk saklar buka/tutup presensi (form pelatihan, pengaturan awal): buka sekarang tanpa batas waktu,
     * atau tutup. Kosong bila keadaannya sudah sesuai, supaya jadwal dan batas waktu yang diatur di Kontrol Sesi
     * tidak terhapus hanya karena form disimpan.
     *
     * @return array<string, mixed>
     */
    public function attendanceSwitchAttributes(bool $open): array
    {
        $open = $open && $this->has_attendance;

        return $open === $this->isAttendanceOpenOrScheduled() ? [] : [
            'is_attendance_active' => $open,
            'attendance_opens_at' => null,
            'attendance_closes_at' => null,
        ];
    }

    /**
     * Pelatihan daring punya tautan meeting; tanpa tautan berarti tatap muka, jadi fitur khusus Zoom tidak tampil.
     */
    public function isOnline(): bool
    {
        return filled($this->zoom_link);
    }

    /**
     * Peserta ini belum mengisi form evaluasi yang tersedia. Butuh relasi evaluation pada peserta.
     */
    public function awaitsEvaluationFrom(TrainingParticipant $participant): bool
    {
        return $participant->evaluation === null && $this->hasEvaluationForm();
    }

    /**
     * Peserta ini belum boleh masuk posttest karena form evaluasinya (yang diurutkan sebelum posttest) belum diisi.
     * Butuh relasi evaluation pada peserta.
     */
    public function requiresEvaluationBeforePosttest(TrainingParticipant $participant): bool
    {
        // Hanya selama form bisa diisi: jadwal evaluasi yang belum tiba atau sudah lewat tidak mengunci posttest.
        return $this->evaluationFollowsPosttest() && $this->awaitsEvaluationFrom($participant) && $this->evaluationWindowOpen();
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

    /**
     * Hasil kuis semua fase; dipakai route yang menerima hasil pretest maupun posttest (lembar jawaban).
     */
    public function results()
    {
        return $this->hasMany(TrainingQuizResult::class, 'training_id');
    }

    public function evaluations()
    {
        return $this->hasMany(TrainingEvaluation::class, 'training_id');
    }

    /**
     * Pertanyaan form evaluasi pelatihan ini, sesuai urutan tampil.
     */
    public function evaluationQuestions()
    {
        return $this->hasMany(TrainingEvaluationQuestion::class, 'training_id')->orderBy('position')->orderBy('id');
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
