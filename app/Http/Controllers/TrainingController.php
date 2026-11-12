<?php

namespace App\Http\Controllers;

use App\Enums\TrainingStatus;
use App\Http\Requests\StoreTrainingRequest;
use App\Http\Requests\UpdateTrainingRequest;
use App\Jobs\AiTask;
use App\Jobs\GenerateExportFile;
use App\Jobs\ScanZoomOffCam;
use App\Jobs\SendTrainingInvitation;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\QuizRules;
use App\Support\TrainingEvaluationForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

#[Authorize('viewAny', Training::class, only: ['index'])]
#[Authorize('create', Training::class, only: ['create', 'store'])]
#[Authorize('view', 'training', only: ['show', 'answerSheet', 'liveStats', 'export'])]
#[Authorize('delete', 'training', only: ['destroy'])]
#[Authorize('update', 'training', except: ['index', 'create', 'store', 'show', 'answerSheet', 'liveStats', 'export', 'destroy'])]
class TrainingController extends Controller
{
    /** Participants per page on the training detail page. */
    private const PARTICIPANTS_PER_PAGE = 50;

    /** Written answers shown per text question on the detail page; the export has all of them. */
    private const EVALUATION_COMMENTS_SHOWN = 20;

    /**
     * Display a listing of trainings
     */
    public function index(): View
    {
        // The list only shows how many participants each training has, so count them instead of loading them.
        $trainings = Training::with('trainer')
            ->withCount('participants')
            ->filter()
            ->latest('training_date')
            ->paginate(15);

        $stats = [
            'total' => Training::count(),
            'scheduled' => Training::where('status', TrainingStatus::Scheduled)->count(),
            'ongoing' => Training::where('status', TrainingStatus::Ongoing)->count(),
            'completed' => Training::where('status', TrainingStatus::Completed)->count(),
        ];

        return view('training.index', [
            'title' => 'Pelatihan & Training',
            'active' => 'training',
            'trainings' => $trainings,
            'stats' => $stats,
        ]);
    }

    /**
     * Show form for creating a new training
     */
    public function create(): View
    {
        $divisis = Divisi::withCount('users')->orderBy('name')->get();
        $documents = Document::with('dokumentype')->orderBy('name')->get();

        return view('training.create', [
            'title' => 'Buat Jadwal Pelatihan',
            'active' => 'training',
            'users' => $this->pickerUsers(),
            'divisis' => $divisis,
            'divisiNames' => $divisis->pluck('name', 'id'),
            'documents' => $documents,
        ]);
    }

    /**
     * Store a newly created training
     */
    public function store(StoreTrainingRequest $request): RedirectResponse
    {
        $participantIds = $request->participantIds();

        $training = DB::transaction(function () use ($request, $participantIds) {
            $training = Training::create([
                ...$request->trainingData(),
                'status' => TrainingStatus::Scheduled,
                'is_quiz_active' => false,
                'is_attendance_active' => $request->boolean('is_attendance_active'),
                'require_attendance_proof' => $request->boolean('require_attendance_proof'),
                'quiz_mode' => $request->validated('quiz_mode') ?? 'formal',
                'has_evaluation' => $request->boolean('has_evaluation'),
                ...$request->quizRules(),
            ]);

            $training->documents()->sync($request->validated('documents') ?? []);

            foreach ($participantIds as $userId) {
                $this->addParticipant($training, $userId);
            }

            return $training;
        });

        if ($request->boolean('send_wa_now')) {
            SendTrainingInvitation::dispatchForTraining($training);
        }

        if (! $training->hasQuiz()) {
            return to_route('training.setup', $training)->with([
                'success' => 'Pelatihan tanpa kuis dibuat dengan '.count($participantIds).' peserta. Langkah berikutnya: atur presensi.',
            ]);
        }

        return to_route('training.questions.index', $training)->with([
            'success' => 'Pelatihan dibuat dengan '.count($participantIds).' peserta. Langkah berikutnya: buat soal kuis.',
        ]);
    }

    /**
     * Pertanyaan pengaturan satu per satu (Ya/Tidak), setelah soal kuis dibuat.
     */
    public function setup(Training $training): View
    {
        return view('training.setup', [
            'title' => 'Pengaturan: '.$training->title,
            'active' => 'training',
            'training' => $training,
        ]);
    }

    public function saveSetup(Request $request, Training $training): RedirectResponse
    {
        $validated = $request->validate([
            'has_attendance' => ['sometimes', 'required', 'boolean'],
            'is_attendance_active' => ['required', 'boolean'],
            'require_attendance_proof' => ['required', 'boolean'],
        ]);
        $settings = array_map(fn ($value) => (bool) $value, $validated);

        // Tanpa presensi, dua jawaban lainnya tidak berlaku.
        if (! ($settings['has_attendance'] ?? $training->has_attendance)) {
            $settings = [...$settings, 'is_attendance_active' => false, 'require_attendance_proof' => false];
        }

        $training->update([...$settings, 'setup_completed_at' => now()]);

        return to_route('training.show', $training)->with('success', 'Pengaturan disimpan. Pelatihan siap dijalankan.');
    }

    /**
     * Show training detail, participants attendance, and quiz scores
     */
    public function show(Request $request, Training $training): View
    {
        $training->load(['trainer', 'questions', 'evaluationQuestions', 'documents.versions', 'documents.dokumentype'])->loadCount('participants');

        // Only one page of participants is loaded and rendered, so the page stays small for any class size.
        $search = trim((string) $request->query('peserta'));
        $participants = $training->participants()
            ->with(['user.divisi', 'quizResult', 'pretestResult', 'evaluation'])
            ->when($search !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user
                ->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('no_hp', 'like', "%{$search}%")
                ->orWhereHas('divisi', fn (Builder $divisi) => $divisi->where('name', 'like', "%{$search}%"))))
            ->orderBy('id')
            ->paginate(self::PARTICIPANTS_PER_PAGE)
            ->withQueryString()
            ->fragment('peserta');

        $allDocuments = Document::with('dokumentype')->orderBy('name')->get();

        return view('training.show', [
            'title' => 'Detail Pelatihan: '.$training->title,
            'active' => 'training',
            'training' => $training,
            'participants' => $participants,
            'zoomParticipants' => $this->zoomParticipants($training),
            'allDocuments' => $allDocuments,
            'stats' => $this->stats($training),
            'evaluation' => $this->evaluationSummary($training),
        ]);
    }

    /**
     * Evaluation results per question for the detail page; null while the form is off and nobody has filled it in.
     *
     * @return array{count: int, average: float|null, questions: list<array<string, mixed>>, questionCount: int}|null
     */
    private function evaluationSummary(Training $training): ?array
    {
        $evaluations = $training->evaluations()->with('user:id,nama_lengkap')->latest('submitted_at')->get();

        if (! $training->has_evaluation && $evaluations->isEmpty()) {
            return null;
        }

        return [
            ...TrainingEvaluationForm::summarize($training->evaluationQuestions, $evaluations, self::EVALUATION_COMMENTS_SHOWN),
            'questionCount' => $training->evaluationQuestions->count(),
        ];
    }

    /**
     * One participant's answer sheet (and essay grading form), loaded into the detail page's modal on demand.
     */
    public function answerSheet(Training $training, TrainingQuizResult $result): View
    {
        abort_unless($result->answers, 404);

        $training->load('questions');
        $result->load('user');

        return view('training.partials.answer-sheet', [
            'training' => $training,
            'quiz' => $result,
            'userName' => $result->user->nama_lengkap ?? 'Peserta',
            'hasEssay' => $training->questionsFor($result->phase)->contains('type', 'essay'),
        ]);
    }

    /**
     * Every user as a plain row for the trainer dropdown and the participant picker of the training form. Only the
     * columns the form shows are read, without building models, so the form stays light with thousands of employees.
     *
     * @return Collection<int, object{id: int, nama_lengkap: string, employee_id: ?string, username: ?string, divisi_id: ?int, no_hp: ?string}>
     */
    private function pickerUsers(): Collection
    {
        return User::query()->toBase()
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'employee_id', 'username', 'divisi_id', 'no_hp']);
    }

    /**
     * Every participant as a small list for the Zoom off-cam review step (the dropdown that matches a Zoom name to a
     * participant), without the quiz results the table rows need.
     *
     * @return Collection<int, array{id: int, name: string, id_karyawan: string, divisi: string, is_off_cam: bool}>
     */
    private function zoomParticipants(Training $training): Collection
    {
        return $training->participants()
            ->with(['user:id,nama_lengkap,employee_id,divisi_id', 'user.divisi:id,name'])
            ->orderBy('id')
            ->get(['id', 'training_id', 'user_id', 'is_off_cam'])
            ->map(fn (TrainingParticipant $participant) => [
                'id' => $participant->id,
                'name' => $participant->user->nama_lengkap ?? 'Peserta',
                'id_karyawan' => $participant->user->employee_id ?? '-',
                'divisi' => $participant->user?->divisi->name ?? '-',
                'is_off_cam' => (bool) $participant->is_off_cam,
            ])
            ->toBase();
    }

    /**
     * Attendance & quiz counters for the detail page cards, polled every few seconds by the admin page.
     */
    public function liveStats(Training $training): JsonResponse
    {
        return response()->json($this->stats($training));
    }

    /**
     * Show form for editing training
     */
    public function edit(Training $training): View
    {
        $training->load('documents');
        $divisis = Divisi::withCount('users')->orderBy('name')->get();
        $documents = Document::with('dokumentype')->orderBy('name')->get();
        $selectedParticipants = $training->participants()->pluck('user_id')->all();
        $selectedDocuments = $training->documents->pluck('id')->toArray();

        return view('training.edit', [
            'title' => 'Edit Jadwal Pelatihan',
            'active' => 'training',
            'training' => $training,
            'users' => $this->pickerUsers(),
            'divisis' => $divisis,
            'divisiNames' => $divisis->pluck('name', 'id'),
            'documents' => $documents,
            'selectedParticipants' => $selectedParticipants,
            'selectedDocuments' => $selectedDocuments,
        ]);
    }

    /**
     * Update training details and participants
     */
    public function update(UpdateTrainingRequest $request, Training $training): RedirectResponse
    {
        $data = [
            ...$request->trainingData(),
            'quiz_mode' => $request->validated('quiz_mode') ?? $training->quiz_mode ?? 'formal',
            ...$request->quizRules(),
        ];

        if ($request->has('is_attendance_active')) {
            $data['is_attendance_active'] = $training->has_attendance && $request->boolean('is_attendance_active');
        }
        if ($request->has('require_attendance_proof')) {
            $data['require_attendance_proof'] = $request->boolean('require_attendance_proof');
        }
        if ($request->has('has_evaluation')) {
            $data['has_evaluation'] = $request->boolean('has_evaluation');
        }

        DB::transaction(function () use ($request, $training, $data) {
            $training->update($data);
            $training->documents()->sync($request->validated('documents') ?? []);

            $newParticipantIds = $request->participantIds();
            $existingParticipantIds = $training->participants()->pluck('user_id')->all();

            foreach (array_diff($newParticipantIds, $existingParticipantIds) as $userId) {
                $this->addParticipant($training, $userId);
            }

            $removedParticipantIds = array_diff($existingParticipantIds, $newParticipantIds);
            if ($removedParticipantIds !== []) {
                $training->participants()->whereIn('user_id', $removedParticipantIds)->delete();
            }
        });

        return to_route('training.show', $training)->with([
            'success' => 'Pelatihan berhasil diperbarui!',
        ]);
    }

    /**
     * Attach documents to training from detail page
     */
    public function attachDocument(Request $request, Training $training): RedirectResponse
    {
        $request->validate([
            'document_ids' => 'required|array|min:1',
            'document_ids.*' => 'exists:documents,id',
        ]);

        $training->documents()->syncWithoutDetaching($request->document_ids);

        return back()->with('success', 'Dokumen materi berhasil dilampirkan ke pelatihan!');
    }

    /**
     * Detach document from training
     */
    public function detachDocument(Training $training, Document $document): RedirectResponse
    {
        $training->documents()->detach($document);

        return back()->with('success', 'Dokumen materi berhasil dilepas dari pelatihan.');
    }

    /**
     * Update training status
     */
    public function updateStatus(Request $request, Training $training): RedirectResponse
    {
        $request->validate(['status' => ['required', Rule::enum(TrainingStatus::class)]]);
        $status = $request->enum('status', TrainingStatus::class);

        $training->update(['status' => $status]);

        return back()->with('success', "Status pelatihan berhasil diubah menjadi: {$status->label()}");
    }

    /**
     * Open/close the posttest (the main quiz).
     */
    public function toggleQuiz(Request $request, Training $training): RedirectResponse
    {
        return $this->togglePhase($request, $training, Training::PHASE_POSTTEST);
    }

    /**
     * Open/close the pretest.
     */
    public function togglePretest(Request $request, Training $training): RedirectResponse
    {
        return $this->togglePhase($request, $training, Training::PHASE_PRETEST);
    }

    /**
     * Open or close a quiz phase. The buttons send what they mean (open=1/0), so "Kunci" pressed right after the time
     * ran out locks the phase instead of reopening it; without it the current state is flipped.
     */
    private function togglePhase(Request $request, Training $training, string $phase): RedirectResponse
    {
        $isPretest = $phase === Training::PHASE_PRETEST;
        $label = $isPretest ? 'Pretest' : 'Kuis pelatihan';
        $open = $request->has('open') ? $request->boolean('open') : ! $training->isPhaseActive($phase);

        if (! $open) {
            $training->closePhase($phase);

            return back()->with('success', $isPretest ? 'Pretest telah DITUTUP.' : 'Kuis pelatihan telah DITUTUP sementara.');
        }

        if (! $training->hasPhase($phase)) {
            return back()->with('error', Training::phaseLabel($phase).' dimatikan di Aturan Kuis pelatihan ini.');
        }

        if (! $training->phaseQuestions($phase)->exists()) {
            return back()->with('error', $isPretest
                ? 'Pretest belum bisa dibuka: buat minimal 1 soal yang dipakai di pretest terlebih dahulu.'
                : 'Kuis belum bisa dibuka: buat soal kuis terlebih dahulu.');
        }

        // Soal pilihan ganda tanpa kunci yang valid membuat semua jawaban peserta pada soal itu dihitung salah.
        $invalidKeys = $training->load('questions')->questionsFor($phase)->reject->hasValidKey()->count();
        if ($invalidKeys > 0) {
            return back()->with('error', "{$invalidKeys} soal pilihan ganda belum punya kunci jawaban yang valid. Perbaiki dulu di halaman Soal Kuis (soal bertanda \"Kunci jawaban tidak valid\").");
        }

        $opensAt = $this->openingTime($request, $training);
        $closesAt = $this->closingTime($request, $training, $phase, $opensAt);
        $training->openPhase($phase, $closesAt, $opensAt);

        if ($opensAt) {
            return back()->with('success', "{$label} DIJADWALKAN buka ".Training::timeLabel($opensAt).($closesAt
                ? " dan tutup otomatis pukul {$closesAt->format('H:i')}."
                : ', tanpa batas waktu.'));
        }

        return back()->with('success', "{$label} telah DIBUKA untuk seluruh peserta".($closesAt
            ? ", tutup otomatis pukul {$closesAt->format('H:i')}."
            : '!'));
    }

    /**
     * Change when an open phase closes: a fixed clock time, minutes from now, or no time limit.
     */
    public function updateQuizDeadline(Request $request, Training $training): RedirectResponse
    {
        $phase = $request->validate(['phase' => ['required', Rule::in(Training::PHASES)]])['phase'];
        $label = Training::phaseLabel($phase);

        if (! $training->isPhaseActive($phase)) {
            return back()->with('error', "Waktu hanya bisa diatur selama {$label} terbuka. Buka {$label} terlebih dahulu.");
        }

        $training->setPhaseClosesAt($phase, $this->closingTime($request, $training, $phase));
        $closesAt = $training->phaseClosesAt($phase);

        return back()->with('success', $closesAt
            ? "{$label} tutup otomatis pukul {$closesAt->format('H:i')}."
            : "{$label} sekarang tanpa batas waktu; kunci secara manual bila sudah selesai.");
    }

    /**
     * When a phase should open, as chosen in the "Buka" dialog: now (null), or by itself at a clock time
     * (open_mode=clock) on the training day. A time that has already passed opens the phase right away.
     */
    private function openingTime(Request $request, Training $training): ?Carbon
    {
        $choice = $request->validate([
            'open_mode' => ['nullable', Rule::in(['now', 'clock'])],
            'open_time' => ['nullable', 'required_if:open_mode,clock', 'date_format:H:i'],
        ], [
            'open_time.required_if' => 'Isi jam bukanya, mis. 09:40.',
            'open_time.date_format' => 'Jam buka harus berformat jam:menit, mis. 09:40.',
        ]);

        if (($choice['open_mode'] ?? null) !== 'clock') {
            return null;
        }

        $opensAt = $training->scheduleDate()->setTimeFromTimeString($choice['open_time']);

        return $opensAt->isFuture() ? $opensAt : null;
    }

    /**
     * When a phase should close, as chosen in the "Buka / Atur waktu" dialog: at a clock time (close_mode=clock),
     * some minutes after it opens (minutes), or never (none). Without a choice the time limit from Aturan Kuis
     * applies ($phase null, the evaluation form, has no such default). $opensAt is the scheduled opening; null
     * means it is open from now.
     */
    private function closingTime(Request $request, Training $training, ?string $phase, ?Carbon $opensAt = null): ?Carbon
    {
        $choice = $request->validate([
            'close_mode' => ['nullable', Rule::in(['clock', 'minutes', 'none'])],
            'close_time' => ['nullable', 'required_if:close_mode,clock', 'date_format:H:i'],
            'close_minutes' => ['nullable', 'required_if:close_mode,minutes', 'integer', 'min:1', 'max:'.QuizRules::MAX_DURATION_MINUTES],
        ], [
            'close_time.required_if' => 'Isi jam tutupnya, mis. 09:50.',
            'close_time.date_format' => 'Jam tutup harus berformat jam:menit, mis. 09:50.',
            'close_minutes.required_if' => 'Isi berapa menit kuis terbuka.',
        ]);
        $from = $opensAt ?? now();

        return match ($choice['close_mode'] ?? null) {
            'none' => null,
            'minutes' => $from->copy()->addMinutes((int) $choice['close_minutes']),
            'clock' => $this->closingClock($choice['close_time'], $from, scheduled: $opensAt !== null),
            default => $phase ? $training->defaultClosesAt($phase, $from) : null,
        };
    }

    /**
     * The closing clock time on the day the phase opens. It must come after the opening, otherwise the phase would
     * close the moment it opens.
     */
    private function closingClock(string $time, Carbon $from, bool $scheduled): Carbon
    {
        $closesAt = $from->copy()->setTimeFromTimeString($time);

        if ($closesAt->lessThanOrEqualTo($from)) {
            throw ValidationException::withMessages(['close_time' => $scheduled
                ? "Jam tutup {$time} harus setelah jam buka {$from->format('H:i')}."
                : "Jam tutup {$time} sudah lewat (sekarang pukul {$from->format('H:i')}). Isi jam yang lebih dari sekarang.",
            ]);
        }

        return $closesAt;
    }

    /**
     * Give participants more time on a phase that is open with a time limit.
     */
    public function extendQuizTime(Request $request, Training $training): RedirectResponse
    {
        $validated = $request->validate([
            'phase' => ['required', Rule::in(Training::PHASES)],
            'minutes' => ['required', 'integer', 'min:1', 'max:120'],
        ]);
        $phase = $validated['phase'];

        if (! $training->extendPhase($phase, (int) $validated['minutes'])) {
            return back()->with('error', 'Waktu hanya bisa ditambah selama '.Training::phaseLabel($phase).' terbuka dengan batas waktu.');
        }

        return back()->with('success', Training::phaseLabel($phase)." ditambah {$validated['minutes']} menit, tutup otomatis pukul {$training->phaseClosesAt($phase)->format('H:i')}.");
    }

    /**
     * Turn the evaluation form on/off. Answers already sent are kept either way.
     */
    public function toggleEvaluation(Training $training): RedirectResponse
    {
        $training->update(['has_evaluation' => ! $training->has_evaluation]);

        if (! $training->has_evaluation) {
            return back()->with('success', 'Form evaluasi DINONAKTIFKAN. Jawaban yang sudah masuk tetap tersimpan.');
        }

        return back()->with('success', ($training->evaluationFollowsPosttest()
            ? 'Form evaluasi DIAKTIFKAN. Peserta mengisinya sebelum mengerjakan posttest.'
            : 'Form evaluasi DIAKTIFKAN. Peserta bisa mengisinya selama sesi berlangsung atau setelah selesai.')
            .' Pertanyaannya bisa diubah lewat Kelola Pertanyaan.');
    }

    /**
     * Save the shared "Aturan Kuis" block (pretest/posttest on-off, attempts, answers, mode) from Kontrol Sesi.
     */
    public function updateQuizRules(Request $request, Training $training): RedirectResponse
    {
        $validated = $request->validate(Training::quizRuleValidation());
        $training->update(Training::quizRulesFromInput($validated['quiz'] ?? []));

        return back()->with('success', 'Aturan kuis disimpan.');
    }

    /**
     * Toggle attendance active status (Open/Close Absen)
     */
    public function toggleAttendance(Training $training): RedirectResponse
    {
        if (! $training->has_attendance) {
            return back()->with('error', 'Presensi tidak dipakai di pelatihan ini. Nyalakan dulu lewat Pakai Presensi.');
        }

        $newStatus = ! $training->is_attendance_active;
        $training->update(['is_attendance_active' => $newStatus]);

        $message = $newStatus
            ? 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta!'
            : 'Presensi kehadiran pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
    }

    /**
     * Turn attendance on/off for the whole training. Off means participants are never asked to confirm attendance
     * and the attendance figures are hidden; records already made are kept.
     */
    public function toggleAttendanceUse(Training $training): RedirectResponse
    {
        $uses = ! $training->has_attendance;
        $training->update(['has_attendance' => $uses, ...($uses ? [] : ['is_attendance_active' => false])]);

        return back()->with('success', $uses
            ? 'Presensi DIPAKAI lagi. Buka presensi saat sesi dimulai.'
            : 'Presensi TIDAK DIPAKAI di pelatihan ini. Data presensi yang sudah masuk tetap tersimpan.');
    }

    /**
     * Set when the evaluation form of a training without posttest opens and closes, or go back to following the
     * training status (reset=1).
     */
    public function updateEvaluationTime(Request $request, Training $training): RedirectResponse
    {
        if ($training->evaluationFollowsPosttest()) {
            return back()->with('error', 'Form evaluasi pelatihan ini diisi sebelum posttest dan terbuka bersamanya, jadi waktunya diatur lewat Akses Posttest. Pindahkan evaluasi ke sesudah posttest di Urutan Langkah bila ingin jamnya sendiri.');
        }

        if ($request->boolean('reset')) {
            $training->update(['evaluation_opens_at' => null, 'evaluation_closes_at' => null]);

            return back()->with('success', 'Form evaluasi kembali mengikuti status pelatihan: terbuka selama sesi berlangsung atau sudah selesai.');
        }

        $scheduled = $this->openingTime($request, $training);
        $closesAt = $this->closingTime($request, $training, null, $scheduled);
        $training->update(['evaluation_opens_at' => $scheduled ?? now(), 'evaluation_closes_at' => $closesAt]);

        return back()->with('success', 'Form evaluasi '
            .($scheduled ? 'DIJADWALKAN buka '.Training::timeLabel($scheduled) : 'DIBUKA sekarang')
            .($closesAt ? " dan tutup otomatis pukul {$closesAt->format('H:i')}." : ', tanpa batas waktu.'));
    }

    /**
     * Save the order of the participant steps (join the session, pretest, attendance, evaluation form, posttest).
     * The order drives the portal cards and their "Langkah N" numbers, the trainer's own step list, and whether the
     * evaluation form has to be filled in before the posttest.
     */
    public function updatePortalSteps(Request $request, Training $training): RedirectResponse
    {
        $steps = array_keys(Training::PORTAL_STEPS);
        $validated = $request->validate([
            'steps' => ['required', 'array', 'size:'.count($steps)],
            'steps.*' => ['required', 'distinct', Rule::in($steps)],
        ], [
            'steps.size' => 'Urutan langkah harus memuat kelima langkah.',
            'steps.*.distinct' => 'Tiap langkah hanya boleh muncul sekali dalam urutan.',
        ]);

        $training->update(['portal_steps' => array_values($validated['steps'])]);

        return back()->with('success', 'Urutan langkah peserta disimpan: '
            .implode(' → ', array_map(fn (string $step) => Training::PORTAL_STEPS[$step], $training->portalSteps())).'.');
    }

    /**
     * Toggle whether screenshot proof is required for attendance
     */
    public function toggleAttendanceProof(Training $training): RedirectResponse
    {
        $newStatus = ! $training->require_attendance_proof;
        $training->update(['require_attendance_proof' => $newStatus]);

        $message = $newStatus
            ? 'Screenshot bukti kehadiran (Zoom/Pelatihan) kini DIWAJIBKAN untuk peserta!'
            : 'Syarat upload screenshot bukti kehadiran kini DINONAKTIFKAN (opsional).';

        return back()->with('success', $message);
    }

    /**
     * Broadcast WhatsApp notification to all participants
     */
    public function broadcastWa(Training $training): RedirectResponse
    {
        $queued = SendTrainingInvitation::dispatchForTraining($training);

        return back()->with([
            'success' => "{$queued} pesan WhatsApp dimasukkan ke antrean dan dikirim di latar belakang. Status tiap peserta akan diperbarui otomatis.",
        ]);
    }

    /**
     * Send WA notification to a single participant
     */
    public function sendSingleWa(Training $training, TrainingParticipant $participant): RedirectResponse
    {
        $result = WhatsAppService::sendToParticipant($participant);

        if ($result['status']) {
            return back()->with('success', "Pesan WhatsApp berhasil dikirim ke {$participant->user->nama_lengkap}.");
        } else {
            return back()->with('error', "Gagal kirim WA ke {$participant->user->nama_lengkap}: {$result['message']}");
        }
    }

    /**
     * Reset participant quiz results so they can retake
     */
    public function resetParticipantQuiz(Training $training, TrainingParticipant $participant): RedirectResponse
    {
        // Pretest dan posttest sekaligus.
        TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
        TrainingQuizResult::where('training_id', $training->id)->where('user_id', $participant->user_id)->delete();

        return back()->with('success', "Status kuis untuk {$participant->user->nama_lengkap} berhasil di-reset. Peserta dapat mengerjakan kuis kembali.");
    }

    /**
     * Reset participant attendance status back to 'pending' (Belum Absen)
     */
    public function resetParticipantAttendance(Training $training, TrainingParticipant $participant): RedirectResponse
    {

        // Delete proof image file from disk if exists
        if ($participant->attendance_proof && Storage::exists($participant->attendance_proof)) {
            Storage::delete($participant->attendance_proof);
        }

        $participant->update([
            'attendance_status' => 'pending',
            'attended_at' => null,
            'attendance_notes' => null,
            'attendance_proof' => null,
        ]);

        return back()->with('success', "Status presensi untuk {$participant->user->nama_lengkap} berhasil diubah menjadi BELUM ABSEN.");
    }

    /**
     * Update participant attendance status & notes manually by admin
     */
    public function updateParticipantAttendance(Request $request, Training $training, TrainingParticipant $participant): RedirectResponse
    {

        $validated = $request->validate([
            'attendance_status' => 'required|in:pending,hadir,tidak_hadir',
            'attendance_notes' => 'nullable|string|max:255',
        ]);

        $data = [
            'attendance_status' => $validated['attendance_status'],
            'attendance_notes' => $validated['attendance_notes'] ?? null,
        ];

        if ($validated['attendance_status'] === 'pending') {
            if ($participant->attendance_proof && Storage::exists($participant->attendance_proof)) {
                Storage::delete($participant->attendance_proof);
            }
            $data['attended_at'] = null;
            $data['attendance_proof'] = null;
        } elseif ($validated['attendance_status'] === 'hadir' && ! $participant->attended_at) {
            $data['attended_at'] = now();
        } elseif ($validated['attendance_status'] === 'tidak_hadir') {
            $data['attended_at'] = now();
        }

        $participant->update($data);

        return back()->with('success', "Presensi {$participant->user->nama_lengkap} berhasil diperbarui.");
    }

    /**
     * Grade essay answers by trainer
     */
    public function gradeEssay(Request $request, Training $training, TrainingQuizResult $result): RedirectResponse
    {
        $validated = $request->validate([
            'essay_score' => 'required|numeric|min:0|max:100',
            'essay_feedback' => 'nullable|string|max:500',
        ]);

        $essayScore = (float) $validated['essay_score'];
        $mcScore = $result->mc_score !== null ? (float) $result->mc_score : 100;

        // Bobot dihitung dari soal fase yang dikerjakan (pretest dan posttest bisa memakai soal berbeda).
        $questions = $training->load('questions')->questionsFor($result->phase);
        $essayQuestionsCount = $questions->where('type', 'essay')->count();
        $mcQuestionsCount = $questions->count() - $essayQuestionsCount;
        $totalQuestions = $questions->count();

        if ($totalQuestions > 0 && $mcQuestionsCount > 0 && $essayQuestionsCount > 0) {
            // Proportional weighting based on questions count
            $mcWeight = $mcQuestionsCount / $totalQuestions;
            $essayWeight = $essayQuestionsCount / $totalQuestions;
            $finalScore = round(($mcScore * $mcWeight) + ($essayScore * $essayWeight), 2);
        } elseif ($essayQuestionsCount > 0 && $mcQuestionsCount === 0) {
            $finalScore = $essayScore;
        } else {
            $finalScore = $mcScore;
        }

        $result->update([
            'essay_score' => $essayScore,
            'essay_feedback' => $validated['essay_feedback'] ?? null,
            'essay_status' => 'graded',
            'reviewed_at' => now(),
            'score' => $finalScore,
        ]);

        return back()->with('success', "Nilai essay berhasil disimpan! Nilai akhir peserta kini {$finalScore}/100.");
    }

    /**
     * Re-score every quiz result of the training (pretest and posttest) against the current answer keys, for use
     * after a wrong key was corrected. Essay scores already given are kept.
     */
    public function regradeQuiz(Training $training): RedirectResponse
    {
        $questions = $training->questions()->get();
        $total = 0;
        $changed = 0;

        DB::transaction(function () use ($training, $questions, &$total, &$changed) {
            $training->results()->orderBy('id')->each(function (TrainingQuizResult $result) use ($questions, &$total, &$changed) {
                $total++;
                $changed += $result->regrade($questions) ? 1 : 0;
            });
        });

        return back()->with('success', match (true) {
            $total === 0 => 'Belum ada peserta yang mengerjakan kuis, jadi tidak ada nilai untuk dihitung ulang.',
            $changed === 0 => "Nilai {$total} hasil kuis sudah sesuai kunci jawaban saat ini; tidak ada yang berubah.",
            default => "Nilai dihitung ulang memakai kunci jawaban saat ini: {$changed} dari {$total} hasil kuis berubah.",
        });
    }

    /**
     * Delete a training
     */
    public function destroy(Training $training): RedirectResponse
    {
        $training->delete();

        return to_route('training.index')->with('success', 'Pelatihan berhasil dihapus!');
    }

    /**
     * Export attendance and quiz results to Excel (.xlsx) in the background; the file arrives as a notification.
     */
    public function export(Request $request, Training $training): RedirectResponse
    {
        GenerateExportFile::dispatch($request->user()->id, GenerateExportFile::TRAINING, $training->id);

        return back()->with('info', GenerateExportFile::QUEUED_MESSAGE);
    }

    /**
     * Start a Gemini scan of Zoom screenshots for off-cam participants. The scan can take minutes, so it runs on the
     * AI queue; the page polls zoomScanStatus for the result.
     */
    public function scanZoomAi(Request $request, Training $training): JsonResponse
    {
        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|string', // base64 strings compressed client-side
        ]);

        if (empty(config('services.gemini.api_key'))) {
            return response()->json(['success' => false, 'message' => 'GEMINI_API_KEY belum dikonfigurasi di file .env!'], 422);
        }

        $token = AiTask::newToken();

        if (ScanZoomOffCam::storeImages($token, $request->input('images')) === 0) {
            return response()->json(['success' => false, 'message' => 'Format gambar screenshot tidak valid atau file tidak dapat dibaca.'], 422);
        }

        ScanZoomOffCam::dispatch($token, $training->id);

        return response()->json(['success' => true, 'token' => $token]);
    }

    /**
     * Result of scanZoomAi, polled by the training detail page.
     */
    public function zoomScanStatus(Training $training, string $token): JsonResponse
    {
        $result = AiTask::status($token);

        if (! $result) {
            return response()->json(['status' => 'failed', 'message' => 'Proses scan tidak ditemukan atau sudah kedaluwarsa. Silakan ulangi.'], 404);
        }

        return response()->json($result);
    }

    /**
     * Save supervisor-verified off-cam participants
     */
    public function saveZoomOffCam(Request $request, Training $training): JsonResponse
    {

        $request->validate([
            'off_cam_items' => 'required|array',
            'off_cam_items.*.participant_id' => 'required|exists:training_participants,id',
            'off_cam_items.*.zoom_name' => 'nullable|string|max:255',
            'off_cam_items.*.reason' => 'nullable|string|max:255',
            'mark_as_tidak_hadir' => 'nullable|boolean',
        ]);

        $markAsTidakHadir = $request->boolean('mark_as_tidak_hadir', false);
        $updatedCount = 0;

        foreach ($request->off_cam_items as $item) {
            $participant = $training->participants()->find($item['participant_id']);

            if (! $participant) {
                continue;
            }

            $zoomName = $item['zoom_name'] ?? '-';
            $existingNote = $participant->attendance_notes ?: '';
            $offCamNote = 'Off Cam Zoom ('.$zoomName.')';

            if (! str_contains($existingNote, 'Off Cam Zoom')) {
                $newNote = trim(($existingNote ? $existingNote.'; ' : '').$offCamNote);
            } else {
                $newNote = $existingNote;
            }

            $itemCount = isset($item['off_cam_count']) ? (int) $item['off_cam_count'] : max(1, (int) $participant->off_cam_count);

            $updateData = [
                'is_off_cam' => true,
                'off_cam_count' => $itemCount,
                'zoom_display_name' => $zoomName,
                'zoom_off_cam_at' => now(),
                'attendance_notes' => $newNote,
            ];

            if ($markAsTidakHadir) {
                $updateData['attendance_status'] = 'tidak_hadir';
            }

            $participant->update($updateData);
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$updatedCount} peserta berhasil diverifikasi & dicatat sebagai OFF CAM!",
            'updated_count' => $updatedCount,
        ]);
    }

    /**
     * Reset/unmark off-cam status for a participant
     */
    public function resetZoomOffCam(Request $request, Training $training, TrainingParticipant $participant): JsonResponse|RedirectResponse
    {

        // Remove off-cam note if present
        $cleanNote = preg_replace('/;?\s*Off Cam Zoom [^;]+/', '', $participant->attendance_notes ?? '');
        $cleanNote = trim($cleanNote, ' ;');

        $participant->update([
            'is_off_cam' => false,
            'off_cam_count' => 0,
            'zoom_display_name' => null,
            'zoom_off_cam_at' => null,
            'attendance_notes' => $cleanNote ?: null,
        ]);

        $name = $participant->user->nama_lengkap ?? ($participant->name ?? 'Peserta');

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_off_cam' => false,
                'off_cam_count' => 0,
                'message' => "Status Off Cam untuk {$name} berhasil dibatalkan.",
            ]);
        }

        return back()->with('success', "Status Off Cam untuk {$name} berhasil dibatalkan.");
    }

    /**
     * Update participant's off-cam frequency count (increment / decrement / set)
     */
    public function updateOffCamCount(Request $request, Training $training, TrainingParticipant $participant): JsonResponse
    {

        $action = $request->input('action', 'increment');
        $currentCount = (int) ($participant->off_cam_count ?? ($participant->is_off_cam ? 1 : 0));

        if ($action === 'increment') {
            $newCount = $currentCount + 1;
        } elseif ($action === 'decrement') {
            $newCount = max(0, $currentCount - 1);
        } else {
            $newCount = max(0, (int) $request->input('count', 0));
        }

        $participantName = $participant->user->nama_lengkap ?? ($participant->name ?? 'Peserta');

        if ($newCount > 0) {
            $cleanNote = preg_replace('/;?\s*Off Cam Zoom [^;]+/', '', $participant->attendance_notes ?? '');
            $cleanNote = trim($cleanNote, ' ;');
            $offCamNote = "Off Cam Zoom ({$newCount}x)";
            $newNote = trim(($cleanNote ? $cleanNote.'; ' : '').$offCamNote);

            $participant->update([
                'is_off_cam' => true,
                'off_cam_count' => $newCount,
                'zoom_display_name' => $participant->zoom_display_name ?: $participantName,
                'zoom_off_cam_at' => $participant->zoom_off_cam_at ?: now(),
                'attendance_notes' => $newNote,
            ]);
        } else {
            $cleanNote = preg_replace('/;?\s*Off Cam Zoom [^;]+/', '', $participant->attendance_notes ?? '');
            $cleanNote = trim($cleanNote, ' ;');

            $participant->update([
                'is_off_cam' => false,
                'off_cam_count' => 0,
                'zoom_display_name' => null,
                'zoom_off_cam_at' => null,
                'attendance_notes' => $cleanNote ?: null,
            ]);
        }

        return response()->json([
            'success' => true,
            'is_off_cam' => (bool) ($newCount > 0),
            'off_cam_count' => $newCount,
            'message' => $newCount > 0
                ? "{$participantName} tercatat Off Cam {$newCount}x"
                : "Status Off Cam {$participantName} dibatalkan",
        ]);
    }

    /**
     * Counted by the database: this is polled every few seconds, so it must not load participants and quiz answers.
     *
     * @return array{total: int, attended: int, absent: int, pending: int, quizSubmitted: int, avgScore: float|int, pretestSubmitted: int, pretestAvg: float|int}
     */
    private function stats(Training $training): array
    {
        $attendance = $training->participants()->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN attendance_status = 'hadir' THEN 1 ELSE 0 END) as attended")
            ->selectRaw("SUM(CASE WHEN attendance_status = 'tidak_hadir' THEN 1 ELSE 0 END) as absent")
            ->first();
        // The counted quiz is the training's main phase: the posttest, or the pretest when that is all it has.
        $quiz = $this->phaseStats($training, $training->mainPhase());
        $pretest = $this->phaseStats($training, $training->has_pretest && $training->has_posttest ? Training::PHASE_PRETEST : null);

        $total = (int) $attendance->total;
        $attended = (int) $attendance->attended;
        $absent = (int) $attendance->absent;

        return [
            'total' => $total,
            'attended' => $attended,
            'absent' => $absent,
            'pending' => $total - ($attended + $absent),
            'quizSubmitted' => $quiz['submitted'],
            'avgScore' => $quiz['average'],
            'pretestSubmitted' => $pretest['submitted'],
            'pretestAvg' => $pretest['average'],
        ];
    }

    /**
     * How many participants finished a phase and their average score; zeros when the training has no such phase.
     *
     * @return array{submitted: int, average: float|int}
     */
    private function phaseStats(Training $training, ?string $phase): array
    {
        if ($phase === null) {
            return ['submitted' => 0, 'average' => 0];
        }

        $row = $training->results()->where('phase', $phase)->toBase()->selectRaw('COUNT(*) as submitted, AVG(score) as average')->first();
        $submitted = (int) $row->submitted;

        return ['submitted' => $submitted, 'average' => $submitted > 0 ? round((float) $row->average, 1) : 0];
    }

    private function addParticipant(Training $training, int $userId): TrainingParticipant
    {
        return $training->participants()->create([
            'user_id' => $userId,
            'token' => Str::random(40).'_'.time(),
            'attendance_status' => 'pending',
        ]);
    }
}
