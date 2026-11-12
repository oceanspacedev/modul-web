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
use App\Models\TrainingEvaluation;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Support\TrainingEvaluationForm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /** Written feedback shown on the detail page; the export has all of it. */
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
            'is_attendance_active' => ['required', 'boolean'],
            'require_attendance_proof' => ['required', 'boolean'],
        ]);

        $training->update([
            ...array_map(fn ($value) => (bool) $value, $validated),
            'setup_completed_at' => now(),
        ]);

        return to_route('training.show', $training)->with('success', 'Pengaturan disimpan. Pelatihan siap dijalankan.');
    }

    /**
     * Show training detail, participants attendance, and quiz scores
     */
    public function show(Request $request, Training $training): View
    {
        $training->load(['trainer', 'questions', 'documents.versions', 'documents.dokumentype'])->loadCount('participants');

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
     * Evaluation averages and the latest written feedback for the detail page; null while the form is off and nobody
     * has filled it in.
     *
     * @return array{count: int, average: float|null, questions: array<string, array{text: string, average: float|null}>, comments: Collection<int, TrainingEvaluation>, commentCount: int}|null
     */
    private function evaluationSummary(Training $training): ?array
    {
        $evaluations = $training->evaluations()->latest('submitted_at')->get();

        if (! $training->has_evaluation && $evaluations->isEmpty()) {
            return null;
        }

        $comments = $evaluations->filter(fn (TrainingEvaluation $evaluation) => filled($evaluation->comment))->values();
        $shown = $comments->take(self::EVALUATION_COMMENTS_SHOWN);
        $shown->load('user:id,nama_lengkap');

        return [
            ...TrainingEvaluationForm::summarize($evaluations),
            'comments' => $shown,
            'commentCount' => $comments->count(),
        ];
    }

    /**
     * One participant's answer sheet (and essay grading form), loaded into the detail page's modal on demand.
     */
    public function answerSheet(Training $training, TrainingQuizResult $quizResult): View
    {
        abort_unless($quizResult->answers, 404);

        $training->load('questions');
        $quizResult->load('user');

        return view('training.partials.answer-sheet', [
            'training' => $training,
            'quiz' => $quizResult,
            'userName' => $quizResult->user->nama_lengkap ?? 'Peserta',
            'hasEssay' => $training->questions->contains('type', 'essay'),
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
            $data['is_attendance_active'] = $request->boolean('is_attendance_active');
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
     * Toggle quiz active status
     */
    public function toggleQuiz(Training $training): RedirectResponse
    {
        $newStatus = ! $training->is_quiz_active;

        if ($newStatus && ! $training->has_posttest) {
            return back()->with('error', 'Posttest dimatikan di Aturan Kuis pelatihan ini.');
        }

        if ($newStatus && ! $training->questions()->exists()) {
            return back()->with('error', 'Kuis belum bisa dibuka: buat soal kuis terlebih dahulu.');
        }

        $training->update(['is_quiz_active' => $newStatus]);

        $message = $newStatus
            ? 'Kuis pelatihan telah DIBUKA untuk seluruh peserta!'
            : 'Kuis pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
    }

    /**
     * Open/close the pretest. It only uses multiple-choice questions, so at least one must exist.
     */
    public function togglePretest(Training $training): RedirectResponse
    {
        $newStatus = ! $training->is_pretest_active;

        if ($newStatus && ! $training->has_pretest) {
            return back()->with('error', 'Pretest dimatikan di Aturan Kuis pelatihan ini.');
        }

        if ($newStatus && ! $training->questions()->where('type', '!=', 'essay')->exists()) {
            return back()->with('error', 'Pretest belum bisa dibuka: buat minimal 1 soal pilihan ganda terlebih dahulu.');
        }

        $training->update(['is_pretest_active' => $newStatus]);

        return back()->with('success', $newStatus
            ? 'Pretest telah DIBUKA untuk seluruh peserta!'
            : 'Pretest telah DITUTUP.');
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

        return back()->with('success', $training->has_posttest
            ? 'Form evaluasi DIAKTIFKAN. Peserta mengisinya sebelum mengerjakan posttest.'
            : 'Form evaluasi DIAKTIFKAN. Peserta bisa mengisinya selama sesi berlangsung atau setelah selesai.');
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
        $newStatus = ! $training->is_attendance_active;
        $training->update(['is_attendance_active' => $newStatus]);

        $message = $newStatus
            ? 'Presensi kehadiran pelatihan telah DIBUKA untuk seluruh peserta!'
            : 'Presensi kehadiran pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
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
    public function gradeEssay(Request $request, Training $training, TrainingQuizResult $quizResult): RedirectResponse
    {
        $result = $quizResult;

        $validated = $request->validate([
            'essay_score' => 'required|numeric|min:0|max:100',
            'essay_feedback' => 'nullable|string|max:500',
        ]);

        $essayScore = (float) $validated['essay_score'];
        $mcScore = $result->mc_score !== null ? (float) $result->mc_score : 100;

        $mcQuestionsCount = $training->questions()->where('type', '!=', 'essay')->count();
        $essayQuestionsCount = $training->questions()->where('type', 'essay')->count();
        $totalQuestions = $mcQuestionsCount + $essayQuestionsCount;

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
     * @return array{total: int, attended: int, absent: int, pending: int, quizSubmitted: int, avgScore: float|int}
     */
    private function stats(Training $training): array
    {
        $attendance = $training->participants()->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN attendance_status = 'hadir' THEN 1 ELSE 0 END) as attended")
            ->selectRaw("SUM(CASE WHEN attendance_status = 'tidak_hadir' THEN 1 ELSE 0 END) as absent")
            ->first();
        $quiz = $training->quizResults()->toBase()->selectRaw('COUNT(*) as submitted, AVG(score) as average')->first();

        $total = (int) $attendance->total;
        $attended = (int) $attendance->attended;
        $absent = (int) $attendance->absent;
        $quizSubmitted = (int) $quiz->submitted;

        return [
            'total' => $total,
            'attended' => $attended,
            'absent' => $absent,
            'pending' => $total - ($attended + $absent),
            'quizSubmitted' => $quizSubmitted,
            'avgScore' => $quizSubmitted > 0 ? round((float) $quiz->average, 1) : 0,
        ];
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
