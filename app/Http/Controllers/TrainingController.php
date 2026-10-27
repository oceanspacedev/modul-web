<?php

namespace App\Http\Controllers;

use App\Enums\TrainingStatus;
use App\Exports\TrainingRekapExport;
use App\Http\Requests\StoreTrainingRequest;
use App\Http\Requests\UpdateTrainingRequest;
use App\Jobs\SendTrainingInvitation;
use App\Models\Divisi;
use App\Models\Document;
use App\Models\Training;
use App\Models\TrainingParticipant;
use App\Models\TrainingQuizResult;
use App\Models\User;
use App\Services\WhatsAppService;
use App\Services\ZoomAttendanceAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Authorize('viewAny', Training::class, only: ['index'])]
#[Authorize('create', Training::class, only: ['create', 'store'])]
#[Authorize('view', 'training', only: ['show', 'export'])]
#[Authorize('delete', 'training', only: ['destroy'])]
#[Authorize('update', 'training', except: ['index', 'create', 'store', 'show', 'export', 'destroy'])]
class TrainingController extends Controller
{
    /**
     * Display a listing of trainings
     */
    public function index(): View
    {
        $trainings = Training::with(['trainer', 'participants.user', 'quizResults', 'documents'])
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
        $users = User::with('divisi')->orderBy('full_name')->get();
        $divisis = Divisi::with('users')->orderBy('name')->get();
        $documents = Document::with('dokumentype')->orderBy('name')->get();

        return view('training.create', [
            'title' => 'Buat Jadwal Pelatihan',
            'active' => 'training',
            'users' => $users,
            'divisis' => $divisis,
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

        return to_route('training.show', $training)->with([
            'success' => 'Jadwal pelatihan berhasil dibuat dengan '.count($participantIds).' peserta terdaftar!',
        ]);
    }

    /**
     * Show training detail, participants attendance, and quiz scores
     */
    public function show(Training $training): View
    {
        $training->load([
            'trainer',
            'participants.user.divisi',
            'participants.quizResult',
            'questions',
            'quizResults.user',
            'documents.versions',
            'documents.dokumentype',
        ]);

        $allDocuments = Document::with('dokumentype')->orderBy('name')->get();

        $totalParticipants = $training->participants->count();
        $attendedCount = $training->participants->where('attendance_status', 'hadir')->count();
        $absentCount = $training->participants->where('attendance_status', 'tidak_hadir')->count();
        $quizSubmittedCount = $training->quizResults->count();
        $avgScore = $quizSubmittedCount > 0 ? round($training->quizResults->avg('score'), 1) : 0;

        return view('training.show', [
            'title' => 'Detail Pelatihan: '.$training->title,
            'active' => 'training',
            'training' => $training,
            'allDocuments' => $allDocuments,
            'stats' => [
                'total' => $totalParticipants,
                'attended' => $attendedCount,
                'absent' => $absentCount,
                'pending' => $totalParticipants - ($attendedCount + $absentCount),
                'quizSubmitted' => $quizSubmittedCount,
                'avgScore' => $avgScore,
            ],
        ]);
    }

    /**
     * Show form for editing training
     */
    public function edit(Training $training): View
    {
        $training->load(['participants', 'documents']);
        $users = User::with('divisi')->orderBy('full_name')->get();
        $divisis = Divisi::with('users')->orderBy('name')->get();
        $documents = Document::with('dokumentype')->orderBy('name')->get();
        $selectedParticipants = $training->participants->pluck('user_id')->toArray();
        $selectedDocuments = $training->documents->pluck('id')->toArray();

        return view('training.edit', [
            'title' => 'Edit Jadwal Pelatihan',
            'active' => 'training',
            'training' => $training,
            'users' => $users,
            'divisis' => $divisis,
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
        ];

        if ($request->has('is_attendance_active')) {
            $data['is_attendance_active'] = $request->boolean('is_attendance_active');
        }
        if ($request->has('require_attendance_proof')) {
            $data['require_attendance_proof'] = $request->boolean('require_attendance_proof');
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
        $training->update(['is_quiz_active' => $newStatus]);

        $message = $newStatus
            ? 'Kuis pelatihan telah DIBUKA untuk seluruh peserta!'
            : 'Kuis pelatihan telah DITUTUP sementara.';

        return back()->with('success', $message);
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
     * Quick toggle quiz mode (formal <-> game)
     */
    public function toggleQuizMode(Training $training): RedirectResponse
    {
        $newMode = ($training->quiz_mode === 'game') ? 'formal' : 'game';
        $training->update(['quiz_mode' => $newMode]);

        $label = ($newMode === 'game') ? 'Mode Game Interaktif (Quizizz Style)' : 'Mode Ujian Formal';

        return back()->with('success', "Mode tampilan kuis berhasil diubah ke: {$label}");
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
            return back()->with('success', "Pesan WhatsApp berhasil dikirim ke {$participant->user->full_name}.");
        } else {
            return back()->with('error', "Gagal kirim WA ke {$participant->user->full_name}: {$result['message']}");
        }
    }

    /**
     * Reset participant quiz results so they can retake
     */
    public function resetParticipantQuiz(Training $training, TrainingParticipant $participant): RedirectResponse
    {
        TrainingQuizResult::where('training_participant_id', $participant->id)->delete();
        $training->quizResults()->where('user_id', $participant->user_id)->delete();

        return back()->with('success', "Status kuis untuk {$participant->user->full_name} berhasil di-reset. Peserta dapat mengerjakan kuis kembali.");
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

        return back()->with('success', "Status presensi untuk {$participant->user->full_name} berhasil diubah menjadi BELUM ABSEN.");
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

        return back()->with('success', "Presensi {$participant->user->full_name} berhasil diperbarui.");
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
     * Export attendance and quiz results to Excel (.xlsx)
     */
    public function export(Training $training): BinaryFileResponse
    {
        $training->load([
            'trainer',
            'participants.user.divisi',
            'participants.quizResult',
        ]);

        $filename = 'rekap_pelatihan_'.Str::slug($training->title).'_'.date('Ymd_His').'.xlsx';

        return Excel::download(
            new TrainingRekapExport($training),
            $filename
        );
    }

    /**
     * Scan Zoom screenshots using Gemini AI to detect off-cam participants
     */
    public function scanZoomAi(Request $request, Training $training): JsonResponse
    {
        $training->load('participants.user');

        $request->validate([
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|string', // base64 strings compressed client-side
        ]);

        try {
            $participantsList = $training->participants->map(function ($part) {
                return [
                    'id' => $part->id,
                    'user_id' => $part->user_id,
                    'name' => $part->user->full_name ?? ($part->name ?? 'Peserta'),
                    'id_karyawan' => $part->user->id_karyawan ?? '-',
                    'email' => $part->user->email ?? '-',
                ];
            })->values()->toArray();

            $zoomService = new ZoomAttendanceAiService;
            $result = $zoomService->detectOffCamParticipants(
                $request->images,
                $participantsList
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'registered_participants' => $participantsList,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
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

        $name = $participant->user->full_name ?? ($participant->name ?? 'Peserta');

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

        $participantName = $participant->user->full_name ?? ($participant->name ?? 'Peserta');

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

    private function addParticipant(Training $training, int $userId): TrainingParticipant
    {
        return $training->participants()->create([
            'user_id' => $userId,
            'token' => Str::random(40).'_'.time(),
            'attendance_status' => 'pending',
        ]);
    }
}
